<?php
/**
 * Freedom Index Public AJAX: Unified Search
 * Zero bloat. One entry point. Clean routing.
 */

if (!defined('ABSPATH')) exit;

add_action('init', function(): void {
    add_action('wp_ajax_fi_unified_search', 'fi_public_ajax_handle_unified_search');
    add_action('wp_ajax_nopriv_fi_unified_search', 'fi_public_ajax_handle_unified_search');
    add_action('wp_ajax_fi_search_autocomplete', 'fi_public_ajax_handle_search_autocomplete');
    add_action('wp_ajax_nopriv_fi_search_autocomplete', 'fi_public_ajax_handle_search_autocomplete');
    add_action('wp_ajax_fi_load_selector', 'fi_public_ajax_handle_load_selector');
    add_action('wp_ajax_nopriv_fi_load_selector', 'fi_public_ajax_handle_load_selector');
});

/**
 * Main unified search handler.
 * Routes: ZIP/address → representatives, Name → legislator search.
 */
function fi_public_ajax_handle_unified_search(): void {
    check_ajax_referer('fi_ajax_nonce', 'nonce');

    $query = sanitize_text_field(wp_unslash($_POST['query'] ?? $_POST['search'] ?? ''));
    $query = trim($query);

    if ($query === '') {
        wp_send_json_success(['mode' => 'empty', 'html' => '', 'count' => 0]);
    }

    $route = fi_search_route($query, $_POST);

    if ($route === 'representatives') {
        $result = fi_search_representatives($query, $_POST);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        wp_send_json_success($result);
    }

    $result = fi_search_legislators($query);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    wp_send_json_success($result);
}

/**
 * Route detection: ZIP/address vs name search.
 */
function fi_search_route(string $query, array $source): string {
    $zip = fi_extract_zip($query);

    // Explicit address components
    if (!empty($source['zip']) && fi_extract_zip((string)$source['zip'])) return 'representatives';
    if ($zip && preg_match('/^\s*\d{5}(?:-\d{4})?\s*$/', $query)) return 'representatives';

    // Address indicators with ZIP
    $has_address = !empty($source['address']) || !empty($source['city']) || !empty($source['state']);
    if ($zip && $has_address) return 'representatives';
    if ($zip && strpos($query, ',') !== false) return 'representatives';
    if ($zip && preg_match('/\b[A-Za-z]{2}\b/', $query)) return 'representatives';
    if ($zip && !preg_match('/^\s*\d{5}(?:-\d{4})?\s*$/', $query)) return 'representatives';

    return 'legislators';
}

/**
 * Extract 5-digit ZIP from string.
 */
function fi_extract_zip(string $value): string {
    if (preg_match('/\b(\d{5})(?:-\d{4})?\b/', trim($value), $m)) return $m[1];
    return '';
}

/**
 * Build complete address from query + source fields.
 */
function fi_build_address(string $query, array $source): string {
    $zip = sanitize_text_field(wp_unslash($source['zip'] ?? ''));
    $street = sanitize_text_field(wp_unslash($source['address'] ?? ''));
    $city = sanitize_text_field(wp_unslash($source['city'] ?? ''));
    $state = sanitize_text_field(wp_unslash($source['state'] ?? ''));

    if ($zip && $street && $city && $state) {
        return trim($street . ' ' . $city . ' ' . $state . ' ' . $zip);
    }
    if ($zip) return $zip;
    return trim($query);
}

/**
 * Representatives search via Geocod.io (with caching)
 */
function fi_search_representatives(string $query, array $source) {
    $address = fi_build_address($query, $source);
    if ($address === '') {
        return new WP_Error('address_required', 'ZIP code or address is required.');
    }

    // Check cache first
    $cache_key = 'findmy/' . rawurlencode(strtolower($address));
    if (function_exists('fi_cache')) {
        $cached = fi_cache($cache_key);
        if (is_array($cached) && isset($cached['officials'])) {
            $district_check = fi_check_multiple_districts($cached['officials']);
            return [
                'mode' => 'representatives',
                'html' => fi_render_representatives($cached['officials'], $address, $district_check['has_multiple'], $district_check),
                'address' => $address,
                'count' => count($cached['officials']),
                'officials' => $cached['officials'],
                'has_multiple_districts' => $district_check['has_multiple'],
                'multiple_districts_type' => $district_check['type'],
                'multiple_districts_message' => $district_check['has_multiple']
                    ? "Your zip code spans {$district_check['count']} " . ($district_check['type'] === 'federal' ? 'congressional' : 'state legislative') . " districts."
                    : '',
            ];
        }
    }

    // Call Geocod API directly with address
    $officials = fi_geocod_fetch_officials(rawurlencode($address));
    if (!is_array($officials)) $officials = [];

    // Check for multiple districts
    $district_info = fi_check_multiple_districts($officials);
    $has_multiple = $district_info['has_multiple'];

    $result = [
        'mode' => 'representatives',
        'html' => fi_render_representatives($officials, $address, $has_multiple, $district_info),
        'address' => $address,
        'count' => count($officials),
        'officials' => $officials,
        'has_multiple_districts' => $has_multiple,
        'multiple_districts_type' => $district_info['type'],
        'multiple_districts_message' => $has_multiple
            ? "Your zip code spans {$district_info['count']} " . ($district_info['type'] === 'federal' ? 'congressional' : 'state legislative') . " districts."
            : '',
    ];

    // Cache the result
    if (function_exists('fi_cache')) {
        fi_cache($cache_key, $result);
    }

    return $result;
}

/**
 * Detect multiple districts in results.
 */
function fi_check_multiple_districts(array $officials): array {
    $us_districts = [];
    $state_districts = [];

    foreach ($officials as $official) {
        $chamber = strtolower($official['chamber'] ?? '');
        $type = strtolower($official['type'] ?? '');
        $gov = $official['legislator']['gov'] ?? '';
        $district = $official['division'] ?? $official['legislator']['district'] ?? '';

        $is_us_rep = ($gov === 'US' || $type === 'representative') && strpos($chamber, 'state') === false;
        $is_state_rep = strpos($chamber, 'state') !== false || ($gov !== 'US' && $type === 'representative');

        if ($is_us_rep && $district) $us_districts[$district] = true;
        elseif ($is_state_rep && $district) $state_districts[$district] = true;
    }

    if (count($us_districts) > 1) return ['has_multiple' => true, 'type' => 'federal', 'count' => count($us_districts)];
    if (count($state_districts) > 1) return ['has_multiple' => true, 'type' => 'state', 'count' => count($state_districts)];
    return ['has_multiple' => false, 'type' => '', 'count' => 0];
}

/**
 * Render representatives HTML with optional address refinement.
 */
function fi_render_representatives(array $officials, string $address, bool $has_multiple, array $district_info): string {
    ob_start();

    if ($has_multiple) {
        $zip = '';
        if (preg_match('/\b(\d{5}(-\d{4})?)\b/', $address, $m)) $zip = $m[1];
        ?>
        <div class="alert alert-warning p-2 mb-3">
            <div class="d-flex align-items-start gap-2">
                <span class="alert-icon ps-0">⚠️</span>
                <div><span class="text-muted">Your zip code spans multiple districts. Enter your full address for precise results.</span></div>
            </div>
            <form class="row g-2 mt-1 fi-address-refine-form" onsubmit="return false;">
                <div class="col-12 col-sm-6 col-md-4"><input type="text" class="form-control form-control-sm" name="street" placeholder="Street Address" required style="font-size: 0.875rem;"></div>
                <div class="col-6 col-sm-3 col-md-2"><input type="text" class="form-control form-control-sm" name="city" placeholder="City" required style="font-size: 0.875rem;"></div>
                <div class="col-3 col-sm-2 col-md-2">
                    <select class="form-control form-control-sm" name="state" required style="font-size: 0.875rem;">
                        <?php foreach (FI_GOVERNMENTS as $abbr => $state): if($abbr != 'US'): ?>
                        <option value="<?= esc_attr($abbr) ?>"><?= esc_html($state) ?></option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
                <div class="col-3 col-sm-3 col-md-2"><input type="text" class="form-control form-control-sm" name="zip" placeholder="Zip" value="<?= esc_attr($zip) ?>" style="font-size: 0.875rem;"></div>
                <div class="col-12 col-sm-4 col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100" style="font-size: 0.875rem; white-space: nowrap;">Find</button></div>
            </form>
        </div>
        <?php
    }

    if (!empty($officials)) {
        echo '<div class="container-xl"><div class="row g-3">';
        foreach ($officials as $official) {
            if (!is_array($official)) continue;

            $bio = $official['bio'] ?? [];
            $chamber = $official['chamber'] ?? '';
            $gov = $official['legislator']['gov'] ?? '';
            if ($gov === '') $gov = (strpos(strtolower($chamber), 'state') !== false) ? 'state' : 'US';
            $score = $official['score'] ?? null;

            $leg = [
                'id' => (int) ($official['id'] ?? 0),
                'display_name' => $official['name'] ?? '',
                'first_name' => $bio['first_name'] ?? '',
                'last_name' => $bio['last_name'] ?? '',
                'image_url' => $official['photo_url'] ?? ($bio['photo_url'] ?? null),
                'image_id' => null,
                'session_image_id' => null,
                'lazy_load' => true,
                'score' => ($score !== null && $score !== '') ? (int) $score : null,
                'chamber' => $chamber,
                'party' => $official['party'] ?? '',
                'state' => $official['legislator']['state'] ?? '',
                'district' => $official['legislator']['district'] ?? '',
                'district_name' => $official['division'] ?? '',
                'gov' => $gov,
                'url' => $official['legislator']['url'] ?? ($official['contact']['url'] ?? ''),
            ];

            echo '<div class="col-12 col-md-6">';
            fi_get_public_template('legislators-card', ['legislator' => $leg, 'gov' => $leg['gov']]);
            echo '</div>';
        }
        echo '</div></div>';
    } else {
        echo '<div class="alert alert-warning">No officials found' . ($address ? ': ' . esc_html($address) : '') . '.</div>';
    }

    return ob_get_clean();
}

/**
 * Legislator name search.
 */
function fi_search_legislators(string $query) {
    if (strlen($query) < 3) {
        return [
            'mode' => 'legislators',
            'html' => '<div class="alert alert-warning text-center">Please enter at least 3 characters</div>',
            'count' => 0,
        ];
    }

    global $wpdb;
    $like = '%' . $wpdb->esc_like($query) . '%';

    $sql = "
        SELECT ls.legislator_id AS id, ls.gov, l.display_name, l.first_name, l.last_name, l.image_id, l.image_url, l.legacy_image_url,
            ls.chamber, ls.party, ls.state, ls.district, ls.score, ls.session_id, s.name AS session_name, s.parent_id AS session_parent_id
        FROM {$wpdb->prefix}fi_legislators l
        INNER JOIN (SELECT legislator_id, MAX(session_id) AS max_session_id FROM {$wpdb->prefix}fi_legislator_sessions GROUP BY legislator_id) latest ON l.id = latest.legislator_id
        INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls ON ls.legislator_id = latest.legislator_id AND ls.session_id = latest.max_session_id
        INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
        WHERE (l.display_name LIKE %s OR l.first_name LIKE %s OR l.last_name LIKE %s)
        ORDER BY l.last_name ASC, l.first_name ASC
        LIMIT 50
    ";

    $rows = $wpdb->get_results($wpdb->prepare($sql, $like, $like, $like));

    ob_start();
    echo '<div class="row g-3">';
    $count = 0;

    if (empty($rows)) {
        echo '<div class="col-12 col-md-8 col-lg-6 mx-auto"><div class="alert alert-info text-center"><h4>No results found for: <strong>' . esc_html($query) . '</strong></h4><p>Try a different or partial name.</p></div></div>';
    } else {
        foreach ($rows as $row) {
            if (!function_exists('legislators_list_format_legislator')) break;
            $leg = legislators_list_format_legislator($row);
            if (empty($leg['gov'])) continue;
            $count++;
            echo '<div class="col-12 col-md-6">';
            fi_get_public_template('legislators-card', ['legislator' => $leg, 'gov' => $leg['gov']]);
            echo '</div>';
        }
    }

    echo '</div>';

    return ['mode' => 'legislators', 'html' => ob_get_clean(), 'count' => $count];
}

/**
 * Autocomplete handler.
 */
function fi_public_ajax_handle_search_autocomplete(): void {
    check_ajax_referer('fi_ajax_nonce', 'nonce');

    $term = sanitize_text_field(wp_unslash($_POST['term'] ?? ''));
    $limit = max(1, min(25, absint($_POST['limit'] ?? 10)));

    if (strlen($term) < 3) wp_send_json_success([]);

    // Check cache
    $cache_key = 'fi_ac_' . md5(strtolower($term) . '|' . $limit);
    if (function_exists('fi_cache')) {
        $cached = fi_cache($cache_key);
        if (is_array($cached)) wp_send_json_success($cached);
    }

    global $wpdb;
    $search_term = '%' . $wpdb->esc_like($term) . '%';

    $sql = "
        SELECT l.id, l.first_name, l.last_name, l.display_name, ls.party, ls.chamber, s.gov
        FROM {$wpdb->prefix}fi_legislators l
        INNER JOIN (SELECT legislator_id, MAX(session_id) AS max_session_id FROM {$wpdb->prefix}fi_legislator_sessions GROUP BY legislator_id) latest ON l.id = latest.legislator_id
        INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls ON ls.legislator_id = latest.legislator_id AND ls.session_id = latest.max_session_id
        INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
        WHERE (l.first_name LIKE %s OR l.last_name LIKE %s OR l.display_name LIKE %s)
        ORDER BY l.last_name ASC, l.first_name ASC
        LIMIT %d
    ";

    $results = $wpdb->get_results($wpdb->prepare($sql, $search_term, $search_term, $search_term, $limit));
    if (empty($results)) wp_send_json_success([]);

    $suggestions = [];
    foreach ($results as $r) {
        $label = (string) ($r->display_name ?? '');
        if (!empty($r->party)) $label .= ' (' . strtoupper($r->party) . ')';
        if (!empty($r->gov)) $label .= ' ' . strtoupper($r->gov);

        $url = function_exists('fi_get_legislator_url')
            ? fi_get_legislator_url((int) $r->id)
            : home_url('/legislator/' . (int) $r->id . '/');

        $suggestions[] = ['type' => 'legislator', 'label' => $label, 'value' => $r->display_name ?? '', 'url' => $url];
    }

    // Cache results
    if (function_exists('fi_cache')) {
        fi_cache($cache_key, $suggestions);
    }

    wp_send_json_success($suggestions);
}

/**
 * State/federal selector loader for bottom sheet.
 */
function fi_public_ajax_handle_load_selector(): void {
    check_ajax_referer('fi_ajax_nonce', 'nonce');

    $type = sanitize_text_field($_POST['type'] ?? '');
    if (!in_array($type, ['federal', 'state'], true)) {
        wp_send_json_error(['message' => 'Invalid selector type']);
    }

    ob_start();
    get_template_part('template-parts/sheet-select-state', '', ['type' => $type]);
    wp_send_json_success(['html' => ob_get_clean(), 'type' => $type]);
}

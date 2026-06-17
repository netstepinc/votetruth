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
    add_action('wp_ajax_fi_load_state_legislators', 'fi_public_ajax_handle_load_state_legislators');
    add_action('wp_ajax_nopriv_fi_load_state_legislators', 'fi_public_ajax_handle_load_state_legislators');
});



/**
 * Main unified search handler.
 * Routes: ZIP/address → representatives, Name → legislator search.
 */
function fi_public_ajax_handle_unified_search(): void {
    fi_log( 'AJAX SEARCH: fi_unified_search called, POST=' . json_encode( $_POST ), __FILE__, __LINE__ );
    check_ajax_referer('fi_ajax_nonce', 'nonce');

    $query = sanitize_text_field(wp_unslash($_POST['query'] ?? $_POST['search'] ?? ''));
    $query = trim($query);
    fi_log( 'AJAX SEARCH: query=' . $query, __FILE__, __LINE__ );

    if ($query === '') {
        fi_log( 'AJAX SEARCH: empty query, returning early', __FILE__, __LINE__ );
        wp_send_json_success(['mode' => 'empty', 'html' => '', 'count' => 0]);
    }

    $route = fi_search_route($query, $_POST);
    fi_log( 'AJAX SEARCH: route=' . $route, __FILE__, __LINE__ );

    if ($route === 'representatives') {
        fi_log( 'AJAX SEARCH: routing to fi_search_representatives', __FILE__, __LINE__ );
        $result = fi_search_representatives($query, $_POST);
        if (is_wp_error($result)) {
            fi_log( 'AJAX SEARCH ERROR: representatives error=' . $result->get_error_message(), __FILE__, __LINE__ );
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        fi_log( 'AJAX SEARCH: representatives result count=' . ( $result['count'] ?? 0 ), __FILE__, __LINE__ );
        wp_send_json_success($result);
    }

    fi_log( 'AJAX SEARCH: routing to fi_search_legislators', __FILE__, __LINE__ );
    $result = fi_search_legislators($query);
    if (is_wp_error($result)) {
        fi_log( 'AJAX SEARCH ERROR: legislators error=' . $result->get_error_message(), __FILE__, __LINE__ );
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    fi_log( 'AJAX SEARCH: legislators result count=' . ( $result['count'] ?? 0 ), __FILE__, __LINE__ );
    wp_send_json_success($result);
}

/**
 * Route detection: ZIP/address vs name search.
 */
function fi_search_route(string $query, array $source): string {
    $zip = fi_extract_zip($query);
    fi_log( 'AJAX ROUTE: query=' . $query . ', extracted_zip=' . $zip . ', source_zip=' . ( $source['zip'] ?? '' ), __FILE__, __LINE__ );

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
    fi_log( 'AJAX REPS: address built=' . $address, __FILE__, __LINE__ );
    if ($address === '') {
        fi_log( 'AJAX REPS ERROR: empty address', __FILE__, __LINE__ );
        return new WP_Error('address_required', 'ZIP code or address is required.');
    }
	$address_encoded = rawurlencode($address);

    // Check cache first
    $cache_key = fi_cache_key('findmy/' . $address_encoded);
    fi_log( 'AJAX REPS: cache_key=' . $cache_key, __FILE__, __LINE__ );
	$cached = fi_cache($cache_key);
	fi_log( 'AJAX REPS: cache hit=' . ( is_array($cached) && isset($cached['officials']) ? 'YES (' . count($cached['officials']) . ' officials)' : 'NO' ), __FILE__, __LINE__ );
	if (is_array($cached) && !empty($cached['officials'])) {
		$district_check = fi_check_multiple_districts($cached['officials']);
		return [
			'mode' => 'representatives',
			'html' => fi_render_representatives($cached['officials'], $address, $district_check['has_multiple'], $district_check, $cached['city'] ?? '', $cached['state'] ?? ''),
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


    // Call Geocod API directly with address
    fi_log( 'AJAX REPS: calling fi_geocod_fetch_officials with=' . $address_encoded, __FILE__, __LINE__ );
    $fetch        = fi_geocod_fetch_officials($address_encoded);
    $officials    = $fetch['officials'];
    $geocod_city  = $fetch['city'];
    $geocod_state = $fetch['state'];
    fi_log( 'AJAX REPS: fi_geocod_fetch_officials returned ' . count($officials) . ' officials, city=' . $geocod_city . ', state=' . $geocod_state, __FILE__, __LINE__ );

    // Check for multiple districts
    $district_info = fi_check_multiple_districts($officials);
    $has_multiple = $district_info['has_multiple'];

    $result = [
        'mode' => 'representatives',
        'html' => fi_render_representatives($officials, $address, $has_multiple, $district_info, $geocod_city, $geocod_state),
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
    //fi_cache($cache_key, $result);
    return $result;
}

/**
 * Detect multiple districts in results.
 */
function fi_check_multiple_districts(array $officials): array {
    $us_house_districts    = [];
    $state_house_districts = [];

    foreach ($officials as $official) {
        $chamber  = strtolower($official['chamber'] ?? '');
        $district = $official['division'] ?? $official['legislator']['district'] ?? '';
        if (!$district) continue;

        // Senators cover the entire state — two senators per state is expected, not ambiguous.
        // Only House/Representative members have district-level assignments.
        if (strpos($chamber, 'senator') !== false || strpos($chamber, 'senate') !== false) continue;

        $is_state = strpos($chamber, 'state') !== false;
        $is_house = strpos($chamber, 'representative') !== false
            || strpos($chamber, 'house') !== false
            || strpos($chamber, 'assembly') !== false;

        if ($is_house && !$is_state) {
            $us_house_districts[$district] = true;
        } elseif ($is_house && $is_state) {
            $state_house_districts[$district] = true;
        }
    }

    if (count($us_house_districts) > 1)    return ['has_multiple' => true, 'type' => 'federal', 'count' => count($us_house_districts)];
    if (count($state_house_districts) > 1) return ['has_multiple' => true, 'type' => 'state',   'count' => count($state_house_districts)];
    return ['has_multiple' => false, 'type' => '', 'count' => 0];
}

/**
 * Render representatives HTML with optional address refinement.
 */
function fi_render_representatives(array $officials, string $address, bool $has_multiple, array $district_info, string $geocod_city = '', string $geocod_state = ''): string {
    ob_start();

    if ($has_multiple) {
        $zip = '';
        if (preg_match('/\b(\d{5}(-\d{4})?)\b/', $address, $m)) $zip = $m[1];

        $detected_state = strtoupper($geocod_state);
        ?>
        <div class="alert alert-warning p-2 mb-3">
            <div class="d-flex align-items-start gap-2">
                <span class="alert-icon ps-0">⚠️</span>
                <div><span class="text-muted">Your zip code spans multiple districts. Enter your full address for precise results.</span></div>
            </div>
            <form class="row g-2 mt-1 fi-address-refine-form" onsubmit="return false;">
                <div class="col-12 col-sm-6 col-md-4"><input type="text" class="form-control form-control-sm" name="street" placeholder="Street Address" required style="font-size: 0.875rem;"></div>
                <div class="col-6 col-sm-3 col-md-2"><input type="text" class="form-control form-control-sm" name="city" placeholder="City" value="<?= esc_attr($geocod_city) ?>" style="font-size: 0.875rem;"></div>
                <div class="col-3 col-sm-2 col-md-2">
                    <select class="form-control form-control-sm" name="state" required style="font-size: 0.875rem;">
                        <?php foreach (FI_GOVERNMENTS as $abbr => $gov_name): if($abbr !== 'US'): ?>
                        <option value="<?= esc_attr($abbr) ?>" <?= $abbr === $detected_state ? 'selected' : ''; ?>><?= esc_html($gov_name) ?></option>
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
        // Group officials by gov so we can render a heading per group.
        $groups = [];
        foreach ($officials as $official) {
            if (!is_array($official)) continue;
            $chamber = $official['chamber'] ?? '';
            $gov = $official['legislator']['gov'] ?? '';
            if ($gov === '') $gov = (strpos(strtolower($chamber), 'state') !== false) ? 'state' : 'US';
            $groups[$gov][] = $official;
        }

        echo '<div class="container-xl">';
        foreach ($groups as $gov => $group_officials) {
            $heading = ($gov === 'US') ? 'Federal' : (FI_GOVERNMENTS[$gov] ?? $gov);
            echo '<h3 class="fs-7 fw-7 text-muted border-bottom pb-2 mt-3 mb-3">' . esc_html($heading) . '</h3>';
            echo '<div class="row g-3">';
            foreach ($group_officials as $official) {
                $bio = $official['bio'] ?? [];
                $chamber = $official['chamber'] ?? '';
                $score = $official['score'] ?? null;

                $leg = [
                    'id' => (int) ($official['legislator']['id'] ?? 0),
                    'display_name' => $official['name'] ?? '',
                    'first_name' => $bio['first_name'] ?? '',
                    'last_name' => $bio['last_name'] ?? '',
                    'image_id' => $official['legislator']['image_id'] ?? null,
                    'image_url' => empty($official['legislator']['image_id']) ? ($official['photo_url'] ?? ($bio['photo_url'] ?? '')) : '',
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
            echo '</div>';
        }
        echo '</div>';
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

    $rows = fi_legislators_search($query, ['limit' => 50, 'full' => true]);

    ob_start();
    echo '<div class="row g-3">';
    $count = 0;

    if (empty($rows)) {
        echo '<div class="col-12 col-md-8 col-lg-6 mx-auto"><div class="alert alert-info text-center"><h4>No results found for: <strong>' . esc_html($query) . '</strong></h4><p>Try a different or partial name.</p></div></div>';
    } else {
        foreach ($rows as $row) {
            $leg = fi_legislators_format_row($row);
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
    $cache_key = fi_cache_key('fi_ac_' . $term . '|' . $limit);
    $cached = fi_cache($cache_key);
    if (is_array($cached)) wp_send_json_success($cached);

    $results = fi_legislators_search($term, ['limit' => $limit, 'full' => false]);
    if (empty($results)) wp_send_json_success([]);

    $suggestions = [];
    foreach ($results as $r) {
        $label = (string) ($r->display_name ?? '');
        if (!empty($r->party)) $label .= ' (' . strtoupper($r->party) . ')';
        if (!empty($r->gov)) $label .= ' ' . strtoupper($r->gov);

        $url = fi_legislator_get_url((int) $r->id);

        $suggestions[] = ['type' => 'legislator', 'label' => $label, 'value' => $r->display_name ?? '', 'url' => $url];
    }

    // Cache results
    fi_cache($cache_key, $suggestions);

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
    get_template_part('global-templates/bottom-sheet-map', '', ['type' => $type]);
    wp_send_json_success(['html' => ob_get_clean(), 'type' => $type]);
}

/**
 * Load legislators for a selected state.
 */
function fi_public_ajax_handle_load_state_legislators(): void {
    check_ajax_referer('fi_ajax_nonce', 'nonce');

    $gov   = sanitize_text_field($_POST['gov'] ?? '');
    $state = sanitize_text_field($_POST['state'] ?? '');

    if (empty($state)) {
        wp_send_json_error(['message' => 'State code required']);
    }

    $results = fi_search_legislators('', ['state' => strtoupper($state), 'limit' => 50]);

    if (is_wp_error($results)) {
        wp_send_json_error(['message' => $results->get_error_message()]);
    }

    if (empty($results)) {
        wp_send_json_error(['message' => 'No legislators found for this state.']);
    }

    ob_start();
    foreach ($results as $legislator) {
        fi_render_legislator_card($legislator);
    }
    wp_send_json_success(['html' => ob_get_clean(), 'count' => count($results)]);
}

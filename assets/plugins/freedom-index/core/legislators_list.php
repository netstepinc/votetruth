<?php
if(!defined('ABSPATH')) { exit; }

/**
 * Legislators List - Lean Query Functions
 * 
 * Purpose-built for efficient legislator list rendering.
 * - Child session roll-up to parent sessions
 * - Minimal field selection (card display only)
 * - Two-level caching (request + persistent)
 * 
 * @package FreedomIndex
 */

// Request-level cache (single request deduplication)
static $legislators_request_cache = [];

const LEGISLATORS_DEFAULT_LIMIT = 24;
const LEGISLATORS_MAX_LIMIT = 600;

/**
 * Get legislators list with all filters and child session roll-up
 *
 * @param array $args {
 *     @type string $gov          Required. Government code (US, TX, etc)
 *     @type int    $session_id   Optional. Parent session ID (child sessions auto-included)
 *     @type string $chamber      Optional. 'S' or 'H'
 *     @type string $party        Optional. Party slug/name
 *     @type string $state        Optional. 2-letter state code (US only)
 *     @type string $search       Optional. Name search term
 *     @type string $sort         Optional. na|nd|sa|sd|pa|pd|oa|od
 *     @type int    $limit        Optional. Items per page (default 24, max 600)
 *     @type int    $offset       Optional. Offset for pagination
 *     @type bool   $no_cache     Optional. Disable cache for this call
 * }
 * @return array Array of legislator arrays for card display
 */
function fi_legislators_list_get(array $args): array {
    global $wpdb, $legislators_request_cache;

    $args = wp_parse_args($args, [
        'gov'        => '',
        'session_id' => 0,
        'chamber'    => '',
        'party'      => '',
        'state'      => '',
        'search'     => '',
        'sort'       => 'na',
        'limit'      => LEGISLATORS_DEFAULT_LIMIT,
        'offset'     => 0,
        'no_cache'   => false,
    ]);

    $gov = strtoupper(sanitize_text_field($args['gov']));
    if (empty($gov)) {
        return [];
    }

    // Build cache key
    $cache_key = legislators_list_build_cache_key($args);
    
    // Check request-level cache
    if (isset($legislators_request_cache[$cache_key])) {
        return $legislators_request_cache[$cache_key];
    }

    // Check persistent cache
    if (!$args['no_cache']) {
        $cached = fi_cache($cache_key);
        if ($cached !== null && $cached !== '' && $cached !== false) {
            // Handle serialized data from cache
            if (is_string($cached)) {
                $cached = maybe_unserialize($cached);
            }
            if (is_array($cached)) {
                $legislators_request_cache[$cache_key] = $cached;
                return $cached;
            }
        }
    }

    // Resolve session scope (parent + children)
    $session_ids = legislators_list_resolve_session_scope($args['session_id'], $gov);

    // Build WHERE conditions
    $where = ['ls.gov = %s'];
    $params = [$gov];

    // Session filter (parent + child roll-up)
    if (!empty($session_ids)) {
        $placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
        $where[] = "ls.session_id IN ($placeholders)";
        $params = array_merge($params, $session_ids);
    }

    // Chamber filter
    if (!empty($args['chamber'])) {
        $chamber = strtoupper(substr($args['chamber'], 0, 1));
        if (in_array($chamber, ['S', 'H'], true)) {
            $where[] = 'ls.chamber = %s';
            $params[] = $chamber;
        }
    }

    // Party filter (flexible match)
    if (!empty($args['party'])) {
        $party = sanitize_text_field($args['party']);
        $where[] = '(ls.party = %s OR ls.party LIKE %s)';
        $params[] = $party;
        $params[] = '%' . $wpdb->esc_like($party) . '%';
    }

    // State filter (US only)
    if (!empty($args['state'])) {
        $state = strtoupper(substr($args['state'], 0, 2));
        $where[] = 'ls.state = %s';
        $params[] = $state;
    }

    // Search filter (name)
    if (!empty($args['search'])) {
        $search = sanitize_text_field($args['search']);
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(l.display_name LIKE %s OR l.first_name LIKE %s OR l.last_name LIKE %s)';
        $params[] = $search_like;
        $params[] = $search_like;
        $params[] = $search_like;
    }

    // Build ORDER BY
    $order_by = legislators_list_build_order_by($args['sort']);

    // Build LIMIT
    $limit = min((int) $args['limit'], LEGISLATORS_MAX_LIMIT);
    $offset = max(0, (int) $args['offset']);

    // Build SQL
    $where_sql = implode(' AND ', $where);
    
    $sql = "
        SELECT 
            ls.legislator_id AS id,
            ls.gov,
            l.display_name,
            l.first_name,
            l.last_name,
            l.image_id,
            l.image_url,
            l.legacy_image_url,
            ls.chamber,
            ls.party,
            ls.state,
            ls.district,
            ls.score,
            ls.session_id,
            s.name AS session_name,
            s.parent_id AS session_parent_id,
            t.name AS district_name
        FROM {$wpdb->prefix}fi_legislator_sessions ls
        JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
        JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
        LEFT JOIN {$wpdb->prefix}fi_taxonomy t 
            ON ls.district = t.slug 
            AND t.gov = ls.gov 
            AND t.taxonomy = 'district'
        WHERE {$where_sql}
        ORDER BY {$order_by}
        LIMIT {$limit} OFFSET {$offset}
    ";

    // Prepare and execute
    $prepared_sql = $wpdb->prepare($sql, $params);
    $results = $wpdb->get_results($prepared_sql);

    if (!is_array($results)) {
        return [];
    }

    // Format results
    $formatted = array_map('legislators_list_format_legislator', $results);

    // Cache results
    if (!$args['no_cache']) {
        fi_cache($cache_key, $formatted, HOUR_IN_SECONDS);
    }
    $legislators_request_cache[$cache_key] = $formatted;

    return $formatted;
}

/**
 * Get total count for pagination
 */
function fi_legislators_list_count(array $args): int {
    global $wpdb;

    $args = wp_parse_args($args, [
        'gov'        => '',
        'session_id' => 0,
        'chamber'    => '',
        'party'      => '',
        'state'      => '',
        'search'     => '',
    ]);

    $gov = strtoupper(sanitize_text_field($args['gov']));
    if (empty($gov)) {
        return 0;
    }

    // Check cache
    $cache_key = legislators_list_build_cache_key($args, 'count');
    $cached = fi_cache($cache_key);
    if ($cached !== null && $cached !== '' && $cached !== false && !$args['no_cache']) {
        // Handle serialized data
        if (is_string($cached)) {
            $cached = maybe_unserialize($cached);
        }
        return (int) $cached;
    }

    // Resolve session scope
    $session_ids = legislators_list_resolve_session_scope($args['session_id'], $gov);

    // Build WHERE (same as fi_legislators_list_get)
    $where = ['ls.gov = %s'];
    $params = [$gov];

    if (!empty($session_ids)) {
        $placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
        $where[] = "ls.session_id IN ($placeholders)";
        $params = array_merge($params, $session_ids);
    }

    if (!empty($args['chamber'])) {
        $chamber = strtoupper(substr($args['chamber'], 0, 1));
        if (in_array($chamber, ['S', 'H'], true)) {
            $where[] = 'ls.chamber = %s';
            $params[] = $chamber;
        }
    }

    if (!empty($args['party'])) {
        $party = sanitize_text_field($args['party']);
        $where[] = '(ls.party = %s OR ls.party LIKE %s)';
        $params[] = $party;
        $params[] = '%' . $wpdb->esc_like($party) . '%';
    }

    if (!empty($args['state'])) {
        $state = strtoupper(substr($args['state'], 0, 2));
        $where[] = 'ls.state = %s';
        $params[] = $state;
    }

    if (!empty($args['search'])) {
        $search = sanitize_text_field($args['search']);
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(l.display_name LIKE %s OR l.first_name LIKE %s OR l.last_name LIKE %s)';
        $params[] = $search_like;
        $params[] = $search_like;
        $params[] = $search_like;
    }

    $where_sql = implode(' AND ', $where);

    $sql = "
        SELECT COUNT(DISTINCT ls.legislator_id)
        FROM {$wpdb->prefix}fi_legislator_sessions ls
        JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
        JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
        WHERE {$where_sql}
    ";

    $prepared_sql = $wpdb->prepare($sql, $params);
    $count = (int) $wpdb->get_var($prepared_sql);

    if (!$args['no_cache']) {
        fi_cache($cache_key, $count, HOUR_IN_SECONDS);
    }
    
    return $count;
}

/**
 * Resolve session scope - get parent + all child session IDs
 */
function legislators_list_resolve_session_scope(?int $session_id, string $gov): array {
    global $wpdb;

    if ($session_id === null || $session_id <= 0) {
        // No session specified - first try to find current parent session
        $current = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}fi_sessions
            WHERE gov = %s AND is_current = 1 AND (parent_id = 0 OR parent_id IS NULL)
            ORDER BY id DESC LIMIT 1
        ", $gov));
        
        $session_id = (int) $current;
        
        // If no current session, fall back to most recent parent session
        if ($session_id <= 0) {
            $current = $wpdb->get_var($wpdb->prepare("
                SELECT id FROM {$wpdb->prefix}fi_sessions
                WHERE gov = %s AND (parent_id = 0 OR parent_id IS NULL)
                ORDER BY date_end DESC, id DESC LIMIT 1
            ", $gov));
            
            $session_id = (int) $current;
        }
        
        if ($session_id <= 0) {
            return [];
        }
    }

    // Check cache for this session scope
    $cache_key = "fi_session_scope/{$gov}/{$session_id}";
    $cached = fi_cache($cache_key);
    if ($cached !== null && is_array($cached)) {
        return $cached;
    }

    // Get parent session to confirm it's actually a parent
    $parent = $wpdb->get_row($wpdb->prepare("
        SELECT id, parent_id FROM {$wpdb->prefix}fi_sessions
        WHERE id = %d AND gov = %s
    ", $session_id, $gov));

    if (!$parent) {
        return [];
    }

    // If this is a child session, find its parent
    $parent_id = (int) ($parent->parent_id ?: $parent->id);

    // Get all child sessions + the parent
    $ids = $wpdb->get_col($wpdb->prepare("
        SELECT id FROM {$wpdb->prefix}fi_sessions
        WHERE gov = %s AND (id = %d OR parent_id = %d)
    ", $gov, $parent_id, $parent_id));

    $result = array_map('intval', $ids);
    
    fi_cache($cache_key, $result, HOUR_IN_SECONDS);
    
    return $result;
}

/**
 * Build ORDER BY clause from sort code
 */
function legislators_list_build_order_by(string $sort): string {
    $sort = strtolower(trim($sort));
    
    $map = [
        'na' => 'l.last_name ASC, l.first_name ASC',
        'nd' => 'l.last_name DESC, l.first_name DESC',
        'sa' => 'ls.score ASC, l.last_name ASC, l.first_name ASC',
        'sd' => 'ls.score DESC, l.last_name ASC, l.first_name ASC',
        'pa' => 'ls.party ASC, l.last_name ASC, l.first_name ASC',
        'pd' => 'ls.party DESC, l.last_name ASC, l.first_name ASC',
        'oa' => 'ls.chamber ASC, ls.district ASC, l.last_name ASC, l.first_name ASC',
        'od' => 'ls.chamber DESC, ls.district ASC, l.last_name ASC, l.first_name ASC',
    ];

    return $map[$sort] ?? $map['na'];
}

/**
 * Build cache key from args
 */
function legislators_list_build_cache_key(array $args, string $type = 'list'): string {
    // Remove no_cache from key
    unset($args['no_cache']);
    
    // Handle null session_id
    $session_id = isset($args['session_id']) && $args['session_id'] !== null ? (int) $args['session_id'] : 0;
    
    $parts = [
        'legislators',
        $type,
        strtolower($args['gov'] ?? ''),
        $session_id,
        strtolower($args['chamber'] ?? ''),
        strtolower($args['party'] ?? ''),
        strtoupper($args['state'] ?? ''),
        md5($args['search'] ?? ''),
        $args['sort'] ?? 'na',
        (int) ($args['limit'] ?? LEGISLATORS_DEFAULT_LIMIT),
        (int) ($args['offset'] ?? 0),
    ];

    return implode('/', $parts);
}

/**
 * Get legislator by external reference IDs (procedural, returns array with score)
 *
 * @param array $references External IDs: bioguide_id, lis_id, legiscan_id, votesmart_id, ballotpedia_id
 * @return array|null Legislator array with score, or null if not found
 */
function fi_legislator_get_by_external_id_procedural(array $references): ?array {
    global $wpdb;
    
    if (empty($references)) {
        return null;
    }
    
    $where = [];
    $params = [];
    
    $external_fields = [
        'bioguide_id', 'lis_id', 'legiscan_id', 
        'votesmart_id', 'ballotpedia_id', 'openstates_id'
    ];
    
    foreach ($external_fields as $field) {
        if (!empty($references[$field])) {
            $where[] = "l.{$field} = %s";
            $params[] = $references[$field];
        }
    }
    
    if (empty($where)) {
        return null;
    }
    
    $sql = "SELECT l.*, ls.session_id, ls.chamber, ls.party AS session_party, ls.state AS session_state, ls.district AS session_district
            FROM {$wpdb->prefix}fi_legislators l
            LEFT JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
            WHERE " . implode(' OR ', $where) . "
            ORDER BY ls.session_id DESC
            LIMIT 1";
    
    $row = $wpdb->get_row($wpdb->prepare($sql, $params));
    
    if (!$row) {
        return null;
    }
    
    return legislators_list_format_legislator($row);
}

function legislators_list_format_legislator(object $row): array {
    // Use session-specific fields if available, fallback to main legislator fields
    $chamber = $row->chamber ?? null;
    $party = $row->session_party ?? $row->party ?? null;
    $state = $row->session_state ?? $row->state ?? null;
    $district = $row->session_district ?? $row->district ?? null;
    
    $leg = [
        'id' => (int) $row->id,
        'gov' => $row->gov,
        'display_name' => $row->display_name ?: trim($row->first_name . ' ' . $row->last_name),
        'first_name' => $row->first_name,
        'last_name' => $row->last_name,
        'image_id' => $row->image_id ? (int) $row->image_id : null,
        'image_url' => $row->image_url ?: $row->legacy_image_url ?: null,
        'score' => $row->score !== null ? (int) $row->score : null,
        'party' => $party,
        'chamber' => $chamber,
        'state' => $state,
        'district' => $district,
        'district_name' => $row->district_name,
        'session_id' => (int) ($row->session_id ?? 0),
        'session_name' => $row->session_name ?? null,
        'session_parent_id' => !empty($row->session_parent_id) ? (int) $row->session_parent_id : null,
    ];

    // Build URL
    $leg['url'] = fi_get_legislator_url($leg['id']);

    // Human-readable labels (use global functions)
    $leg['party_name'] = $leg['party'] ? fi_party_name($leg['party']) : '';
    $leg['chamber_label'] = $leg['chamber'] ? fi_chamber_label($row->gov, $leg['chamber']) : '';
    $leg['chamber_title'] = $leg['chamber'] ? fi_chamber_title($row->gov, $leg['chamber']) : '';
    $leg['state_name'] = $leg['state'] ? fi_state_name($leg['state']) : '';

    return $leg;
}

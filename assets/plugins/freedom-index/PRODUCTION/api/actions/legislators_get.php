<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
 * Action: legislators_get
 * Procedural Medoo query layer for FI API.
 *
 * Inputs (via $args from fi_api.php):
 * - gov (required for list)
 * - session_id (optional; if omitted, use current parent session for gov)
 * - legislator_id (optional; if present, fetch single full object)
 * - district_id (optional; taxonomy ID for district; resolved to slug)
 * - chamber (S|H)
 * - party (party slug; may resolve to name via taxonomy, but slug match is primary)
 * - name (search string)
 * - state (US congress only)
 * - sort (na|nd|sa|sd|pa|pd|oa|od)
 * - order (ASC|DESC override)
 *
 * Notes:
 * - Front-end typically uses parent sessions; we enforce parent session (parent_id=0) by default.
 * - For single legislator, we return a richer object (core + session row + optional district).
 * - Medoo instance: $fidb
 * - Constants: DB_PRE
 */

// Legislator GET vars: same order/semantics as rewrite list filters + single-legislator and district
$legislator_get_vars = [
	'gov',           // Government code (us, tx, wi, …); required for list
	'session_id',    // Session ID (numeric); rewrite fi_session_id
	'district_id',   // District filter
	'legislator_id', // Single legislator; rewrite fi_legislator_id
	'chamber',       // Chamber: H | S (rewrite fi_chamber)
	'party',         // Party slug (rewrite fi_party_slug)
	'name',          // Search/name filter (rewrite fi_search)
	'sort',          // na|nd|sa|sd|pa|pd|oa|od (rewrite fi_sort)
	'order',         // Optional order override
	'state',         // Congress only: two-letter state (rewrite fi_state)
];

//EXAMPLE: print_r($args);exit; Array ( [gov] => us [session_id] => 14 )

if (!isset($fidb) || !($fidb instanceof Medoo\Medoo)) {
	header('HTTP/1.1 500 Internal Server Error');
	exit('DB not initialized');
}
header('Content-Type: application/json; charset=utf-8');

// ------------------------------------------------------------
// Helpers (procedural-local)
// ------------------------------------------------------------

$fi_api_json_out = function(array $payload, int $status = 200): void {
	if (!headers_sent()) {
		http_response_code($status);
	}
	echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	exit;
};

$fi_api_arg = function(string $key, $default = null) use ($args) {
	return array_key_exists($key, $args) ? $args[$key] : $default;
};

$fi_api_int = function($v): int {
	if ($v === null) return 0;
	if (is_numeric($v)) return (int)$v;
	return 0;
};

$fi_api_upper = function($v): string {
	$v = is_string($v) ? trim($v) : '';
	return strtoupper($v);
};

$fi_api_sort_norm = function(string $sort): string {
	$sort = strtolower(trim($sort));
	$allowed = ['na','nd','sa','sd','pa','pd','oa','od'];
	return in_array($sort, $allowed, true) ? $sort : 'na';
};

// ------------------------------------------------------------
// Read + normalize args
// ------------------------------------------------------------

$gov = strtolower((string)$fi_api_arg('gov', ''));
if ($gov === '') {
	$fi_api_json_out([
		'ok' => false,
		'error' => 'Missing gov',
	], 400);
}

$session_id_in = $fi_api_int($fi_api_arg('session_id', 0));
$district_id = $fi_api_int($fi_api_arg('district_id', 0));
$legislator_id = $fi_api_int($fi_api_arg('legislator_id', 0));

$chamber = $fi_api_upper($fi_api_arg('chamber', '')); // S|H
if ($chamber !== '' && $chamber !== 'S' && $chamber !== 'H') {
	$chamber = '';
}

$party = (string)$fi_api_arg('party', '');
$party = trim($party);

$name = (string)$fi_api_arg('name', '');
$name = trim($name);

$state = $fi_api_upper($fi_api_arg('state', ''));
if ($state !== '' && !preg_match('/^[A-Z]{2}$/', $state)) {
	$state = '';
}

$sort = $fi_api_sort_norm((string)$fi_api_arg('sort', 'na'));
$order_override = $fi_api_upper($fi_api_arg('order', ''));
if ($order_override !== 'ASC' && $order_override !== 'DESC') {
	$order_override = '';
}

// Hard limit safety (list endpoints). Single endpoint ignores.
$limit = defined('DB_LIMIT') ? (int)DB_LIMIT : 600;
if ($limit < 1) $limit = 600;

// ------------------------------------------------------------
// Resolve session (prefer parent session_id for lists)
// ------------------------------------------------------------

$tbl_leg = DB_PRE . 'fi_legislators';
$tbl_ls  = DB_PRE . 'fi_legislator_sessions';
$tbl_ses = DB_PRE . 'fi_sessions';
$tbl_tax = DB_PRE . 'fi_taxonomy';

// Return parent session ID for given session ID (if it's a child)
$fi_api_resolve_parent_session_id = function(int $sid) use ($fidb, $tbl_ses): int {
	if ($sid <= 0) return 0;
	$row = $fidb->get($tbl_ses, ['id','parent_id'], ['id' => $sid]);
	if (!$row || empty($row['id'])) return 0;
	$pid = isset($row['parent_id']) ? (int)$row['parent_id'] : 0;
	return ($pid > 0) ? $pid : (int)$row['id'];
};

// Find "current" parent session for gov (is_current=1, parent_id=0/NULL)
$fi_api_get_current_parent_session = function(string $gov) use ($fidb, $tbl_ses): int {
	$row = $fidb->get($tbl_ses, ['id'], [
		'AND' => [
			'gov' => $gov,
			'is_current' => 1,
			'OR' => [
				'parent_id' => 0,
				'parent_id' => null,
			],
		],
		'ORDER' => ['id' => 'DESC'],
	]);
	return ($row && !empty($row['id'])) ? (int)$row['id'] : 0;
};

$parent_session_id = 0;

if ($session_id_in > 0) {
	$parent_session_id = $fi_api_resolve_parent_session_id($session_id_in);
} else {
	$parent_session_id = $fi_api_get_current_parent_session($gov);
}

// If still missing, we can list by gov only (not ideal but valid)
$session_required_for_list = false;

// ------------------------------------------------------------
// Resolve district filter: district_id -> district slug
// ------------------------------------------------------------

$district_slug = '';
if ($district_id > 0) {
	$district_row = $fidb->get($tbl_tax, ['id','slug','taxonomy','gov'], [
		'AND' => [
			'id' => $district_id,
			'gov' => $gov,
			'taxonomy' => 'district',
		],
	]);
	if ($district_row && !empty($district_row['slug'])) {
		$district_slug = (string)$district_row['slug'];
	}
}

// ------------------------------------------------------------
// Sorting (list)
// ------------------------------------------------------------

$sort_map = [
	'na' => ['last_name' => 'ASC',  'first_name' => 'ASC'],
	'nd' => ['last_name' => 'DESC', 'first_name' => 'DESC'],
	'sa' => ['score' => 'ASC',      'last_name' => 'ASC',  'first_name' => 'ASC'],
	'sd' => ['score' => 'DESC',     'last_name' => 'ASC',  'first_name' => 'ASC'],
	'pa' => ['party' => 'ASC',      'last_name' => 'ASC',  'first_name' => 'ASC'],
	'pd' => ['party' => 'DESC',     'last_name' => 'ASC',  'first_name' => 'ASC'],
	'oa' => ['chamber' => 'ASC',    'district' => 'ASC',   'last_name' => 'ASC', 'first_name' => 'ASC'],
	'od' => ['chamber' => 'DESC',   'district' => 'ASC',   'last_name' => 'ASC', 'first_name' => 'ASC'],
];

// Order override applies only if sort is single-field-ish; if provided, flip primary key direction.
if ($order_override !== '') {
	$primary_keys = array_keys($sort_map[$sort]);
	if (!empty($primary_keys)) {
		$primary = $primary_keys[0];
		$sort_map[$sort][$primary] = $order_override;
	}
}

// ------------------------------------------------------------
// Branch 1: Single legislator full object
// ------------------------------------------------------------

if ($legislator_id > 0) {

	// Resolve which session row to use:
	// - If session_id provided, use that exact session (admin or specific request)
	// - Else use parent_session_id if available (front-end default)
	// - Else best latest session row for this gov
	$session_for_single = $session_id_in > 0 ? $session_id_in : ($parent_session_id > 0 ? $parent_session_id : 0);

	// If we have a parent session, prefer a session row under that parent (child sessions roll up).
	// We do this by joining sessions and allowing either:
	// - ls.session_id = parent_session_id
	// - OR ls.session_id is a child of parent_session_id
	$join = [
		'[>]'.$tbl_ls.'(ls)'  => ['id' => 'legislator_id'],
		'[>]'.$tbl_ses.'(s)'  => ['ls.session_id' => 'id'],
	];

	$where = [
		'AND' => [
			$tbl_leg.'.id' => $legislator_id,
			'ls.gov' => $gov,
		],
		'LIMIT' => 20, // safety; we'll post-filter to pick best row
	];

	if ($chamber !== '') {
		$where['AND']['ls.chamber'] = $chamber;
	}
	if ($state !== '') {
		$where['AND']['ls.state'] = $state;
	}

	// Session scoping logic:
	if ($session_for_single > 0) {
		$where['AND']['OR'] = [
			// exact session
			'ls.session_id' => $session_for_single,
			// children of parent (if session_for_single is a parent)
			'AND #child_of_parent' => [
				's.parent_id' => $session_for_single,
			],
		];
	}

	$cols = [
		$tbl_leg.'.id(legislator_id)',
		$tbl_leg.'.legacy_id',
		$tbl_leg.'.first_name',
		$tbl_leg.'.middle_name',
		$tbl_leg.'.last_name',
		$tbl_leg.'.display_name',
		$tbl_leg.'.gov(leg_gov)',
		$tbl_leg.'.session_id(leg_session_id)',
		$tbl_leg.'.bioguide_id',
		$tbl_leg.'.legiscan_id',
		$tbl_leg.'.govtrack_id',
		$tbl_leg.'.votesmart_id',
		$tbl_leg.'.ballotpedia_id',
		$tbl_leg.'.openstates_id',
		$tbl_leg.'.legacy_image_url',
		$tbl_leg.'.image_id(leg_image_id)',
		$tbl_leg.'.score(leg_score)',
		$tbl_leg.'.score_data(leg_score_data)',
		$tbl_leg.'.score_date(leg_score_date)',
		$tbl_leg.'.meta(leg_meta)',
		$tbl_leg.'.date_created(leg_date_created)',
		$tbl_leg.'.date_updated(leg_date_updated)',

		'ls.id(ls_id)',
		'ls.session_id(ls_session_id)',
		'ls.legacy_id(ls_legacy_id)',
		'ls.gov(ls_gov)',
		'ls.state(ls_state)',
		'ls.chamber(ls_chamber)',
		'ls.district(ls_district)',
		'ls.party(ls_party)',
		'ls.image_id(ls_image_id)',
		'ls.date_start(ls_date_start)',
		'ls.date_end(ls_date_end)',
		'ls.score(ls_score)',
		'ls.score_data(ls_score_data)',
		'ls.score_date(ls_score_date)',
		'ls.meta(ls_meta)',

		's.id(session_id)',
		's.parent_id(session_parent_id)',
		's.slug(session_slug)',
		's.name(session_name)',
		's.date_start(session_date_start)',
		's.date_end(session_date_end)',
		's.is_current(session_is_current)',
		's.status(session_status)',
	];

	$rows = $fidb->select($tbl_leg, $join, $cols, $where);

	if (!is_array($rows) || empty($rows)) {
		$fi_api_json_out([
			'ok' => false,
			'error' => 'Legislator not found',
		], 404);
	}

	// Pick best LS row:
	// 1) exact requested session match (if session_id_in)
	// 2) child-of-parent match (if parent session in play)
	// 3) latest date_start
	$best = null;
	foreach ($rows as $r) {
		// If no ls_id (no session row), allow core-only object
		if (empty($r['ls_id'])) {
			if ($best === null) $best = $r;
			continue;
		}

		$ls_sid = isset($r['ls_session_id']) ? (int)$r['ls_session_id'] : 0;
		$s_pid  = isset($r['session_parent_id']) ? (int)$r['session_parent_id'] : 0;

		$score = 0;

		if ($session_id_in > 0 && $ls_sid === $session_id_in) {
			$score += 1000;
		}

		if ($parent_session_id > 0) {
			// exact parent match
			if ($ls_sid === $parent_session_id) $score += 600;
			// child-of-parent match
			if ($s_pid === $parent_session_id) $score += 500;
		}

		// date_start (prefer newest)
		$ds = isset($r['ls_date_start']) ? (string)$r['ls_date_start'] : '';
		if ($ds !== '') {
			// YYYY-MM-DD sorts lexicographically
			$score += (int)str_replace('-', '', $ds);
		}

		if ($best === null) {
			$best = $r;
			$best['_rank'] = $score;
		} else {
			$prev = isset($best['_rank']) ? (int)$best['_rank'] : -1;
			if ($score > $prev) {
				$best = $r;
				$best['_rank'] = $score;
			}
		}
	}

	if ($best === null) {
		$best = $rows[0];
	}

	// Optional district taxonomy enrichment (by slug in ls_district)
	$district = null;
	$ls_district = isset($best['ls_district']) ? trim((string)$best['ls_district']) : '';
	if ($ls_district !== '') {
		$district = $fidb->get($tbl_tax, ['id','name','slug','meta'], [
			'AND' => [
				'gov' => $gov,
				'taxonomy' => 'district',
				'slug' => $ls_district,
			],
		]);
		if (!is_array($district) || empty($district)) {
			$district = null;
		}
	}

	// Shape output
	$out = [
		'legislator' => [
			'id' => (int)$best['legislator_id'],
			'legacy_id' => $best['legacy_id'] ?? null,
			'first_name' => $best['first_name'] ?? '',
			'middle_name' => $best['middle_name'] ?? null,
			'last_name' => $best['last_name'] ?? '',
			'display_name' => $best['display_name'] ?? '',
			'gov' => $best['leg_gov'] ?? null,
			'session_id' => isset($best['leg_session_id']) ? (int)$best['leg_session_id'] : null,
			'bioguide_id' => $best['bioguide_id'] ?? null,
			'legiscan_id' => isset($best['legiscan_id']) ? (int)$best['legiscan_id'] : null,
			'govtrack_id' => $best['govtrack_id'] ?? null,
			'votesmart_id' => $best['votesmart_id'] ?? null,
			'ballotpedia_id' => $best['ballotpedia_id'] ?? null,
			'openstates_id' => $best['openstates_id'] ?? null,
			'legacy_image_url' => $best['legacy_image_url'] ?? null,
			'image_id' => isset($best['leg_image_id']) ? (int)$best['leg_image_id'] : null,
			'score' => isset($best['leg_score']) ? (int)$best['leg_score'] : null,
			'score_data' => $best['leg_score_data'] ?? null,
			'score_date' => $best['leg_score_date'] ?? null,
			'meta' => $best['leg_meta'] ?? null,
			'date_created' => $best['leg_date_created'] ?? null,
			'date_updated' => $best['leg_date_updated'] ?? null,
		],
		'session' => [
			'id' => isset($best['session_id']) ? (int)$best['session_id'] : null,
			'parent_id' => isset($best['session_parent_id']) ? (int)$best['session_parent_id'] : null,
			'slug' => $best['session_slug'] ?? null,
			'name' => $best['session_name'] ?? null,
			'date_start' => $best['session_date_start'] ?? null,
			'date_end' => $best['session_date_end'] ?? null,
			'is_current' => isset($best['session_is_current']) ? (int)$best['session_is_current'] : null,
			'status' => $best['session_status'] ?? null,
		],
		'legislator_session' => [
			'id' => isset($best['ls_id']) ? (int)$best['ls_id'] : null,
			'session_id' => isset($best['ls_session_id']) ? (int)$best['ls_session_id'] : null,
			'legacy_id' => $best['ls_legacy_id'] ?? null,
			'gov' => $best['ls_gov'] ?? null,
			'state' => $best['ls_state'] ?? null,
			'chamber' => $best['ls_chamber'] ?? null, // S|H
			'district' => $best['ls_district'] ?? null,
			'party' => $best['ls_party'] ?? null,
			'image_id' => isset($best['ls_image_id']) ? (int)$best['ls_image_id'] : null,
			'date_start' => $best['ls_date_start'] ?? null,
			'date_end' => $best['ls_date_end'] ?? null,
			'score' => isset($best['ls_score']) ? (int)$best['ls_score'] : null,
			'score_data' => $best['ls_score_data'] ?? null,
			'score_date' => $best['ls_score_date'] ?? null,
			'meta' => $best['ls_meta'] ?? null,
		],
		'district' => $district,
	];

	$fi_api_json_out([
		'ok' => true,
		'action' => 'legislators_get',
		'mode' => 'single',
		'gov' => $gov,
		'requested' => [
			'legislator_id' => $legislator_id,
			'session_id' => $session_id_in ?: null,
			'parent_session_id' => $parent_session_id ?: null,
			'chamber' => $chamber ?: null,
			'state' => $state ?: null,
		],
		'data' => $out,
	]);
}

// ------------------------------------------------------------
// Branch 2: List legislators (fast list)
// Strategy:
// - Anchor on fi_legislator_sessions by session (best selectivity)
// - Join fi_legislators for names/ids/image
// - Join fi_sessions to allow parent scoping and validate gov/session
// - Filters: gov, session, chamber, party, district, state, name
// ------------------------------------------------------------

$and = [
	'ls.gov' => $gov,
];

$join = [
	'[>]'.$tbl_leg.'(l)' => ['legislator_id' => 'id'],
	'[>]'.$tbl_ses.'(s)' => ['session_id' => 'id'],
];

// Session scoping:
// - If parent_session_id exists: use ls.session_id IN (parent + its children)
// - Else if session_id_in exists: use exact
// - Else: allow gov-only list (not preferred; can be large)
if ($parent_session_id > 0) {
	$and['OR'] = [
		'ls.session_id' => $parent_session_id,
		's.parent_id' => $parent_session_id,
	];
} elseif ($session_id_in > 0) {
	$and['ls.session_id'] = $session_id_in;
} else {
	// gov-only list; keep but warn
	$session_required_for_list = true;
}

// Filters
if ($chamber !== '') {
	$and['ls.chamber'] = $chamber;
}
if ($party !== '') {
	// party stored as VARCHAR(64) (often name). Your arg is slug.
	// Default: do a broad match (exact OR case-insensitive exact OR LIKE).
	// If you standardize party storage later, this can be tightened.
	$and['OR #party_match'] = [
		'ls.party' => $party,
		'ls.party[~]' => $party,
	];
}
if ($district_slug !== '') {
	$and['ls.district'] = $district_slug;
}
if ($state !== '') {
	$and['ls.state'] = $state;
}
if ($name !== '') {
	// Name search: match display_name OR first/last
	// Medoo: [~] is LIKE with wildcards automatically, but we control pattern.
	$pattern = $name;
	$and['OR #name_match'] = [
		'l.display_name[~]' => $pattern,
		'l.last_name[~]' => $pattern,
		'l.first_name[~]' => $pattern,
	];
}

// Columns for list cards (lean)
$cols = [
	'ls.legislator_id(id)',
	'l.display_name',
	'l.first_name',
	'l.last_name',
	'l.middle_name',
	'l.legacy_id',
	'l.bioguide_id',
	'l.legiscan_id',
	'l.openstates_id',
	'l.image_id(leg_image_id)',
	'l.legacy_image_url',

	'ls.session_id',
	'ls.state',
	'ls.chamber',
	'ls.district',
	'ls.party',
	'ls.image_id(ls_image_id)',
	'ls.score',
	'ls.score_date',
];

// Order
$order = [];
foreach ($sort_map[$sort] as $k => $dir) {
	// Map sort keys to actual selected columns / table aliases
	switch ($k) {
		case 'last_name':
		case 'first_name':
			$order['l.' . $k] = $dir;
			break;
		case 'score':
			$order['ls.score'] = $dir;
			break;
		case 'party':
			$order['ls.party'] = $dir;
			break;
		case 'chamber':
			$order['ls.chamber'] = $dir;
			break;
		case 'district':
			$order['ls.district'] = $dir;
			break;
		default:
			// ignore unknown
			break;
	}
}

// Guarantee deterministic ordering
if (!isset($order['l.last_name'])) $order['l.last_name'] = 'ASC';
if (!isset($order['l.first_name'])) $order['l.first_name'] = 'ASC';

$where = [
	'AND' => $and,
	'ORDER' => $order,
	'LIMIT' => $limit,
];

// Execute
$list = $fidb->select($tbl_ls, $join, $cols, $where);

if (!is_array($list)) {
	$fi_api_json_out([
		'ok' => false,
		'error' => 'Query failed',
		'debug' => [
			'message' => 'select returned non-array',
		],
	], 500);
}

// Optional: include district names in bulk (only if district filter not provided; keep lean)
$district_map = [];
$district_slugs = [];

foreach ($list as $row) {
	$ds = isset($row['district']) ? trim((string)$row['district']) : '';
	if ($ds !== '') $district_slugs[$ds] = true;
}

if (!empty($district_slugs)) {
	$slugs = array_keys($district_slugs);

	// Pull district names by slug (gov + taxonomy=district)
	$district_rows = $fidb->select($tbl_tax, ['id','name','slug'], [
		'AND' => [
			'gov' => $gov,
			'taxonomy' => 'district',
			'slug' => $slugs,
		],
	]);

	if (is_array($district_rows)) {
		foreach ($district_rows as $dr) {
			if (!empty($dr['slug'])) {
				$district_map[(string)$dr['slug']] = [
					'id' => isset($dr['id']) ? (int)$dr['id'] : null,
					'name' => $dr['name'] ?? null,
					'slug' => $dr['slug'],
				];
			}
		}
	}
}

// Attach district object (optional; helpful for UI)
for ($i = 0; $i < count($list); $i++) {
	$ds = isset($list[$i]['district']) ? (string)$list[$i]['district'] : '';
	if ($ds !== '' && isset($district_map[$ds])) {
		$list[$i]['district_obj'] = $district_map[$ds];
	}
}

$fi_api_json_out([
	'ok' => true,
	'action' => 'legislators_get',
	'mode' => 'list',
	'gov' => $gov,
	'resolved' => [
		'requested_session_id' => $session_id_in ?: null,
		'parent_session_id' => $parent_session_id ?: null,
		'using_session_scope' => !$session_required_for_list,
		'district_slug' => $district_slug ?: null,
	],
	'filters' => [
		'chamber' => $chamber ?: null,
		'party' => $party ?: null,
		'name' => $name ?: null,
		'state' => $state ?: null,
		'sort' => $sort,
		'order_override' => $order_override ?: null,
	],
	'count' => count($list),
	'limit' => $limit,
	'data' => $list,
]);

<?php if (!defined('ABSPATH')) exit;
// /api/inc/legislators_cards.php
// action=legislators_cards (NO WP). Returns list payload for gov+session with optional filters.
// - If session_id omitted => defaults to latest parent session for gov.
// - If session_id=0 => bounded search only (name/party/chamber/state required).
//fi_api_args_legislators_cards
/**
 * legislators_cards args:
 * - If session_id is OMITTED: API defaults to latest parent session.
 * - If session_id=0: "All Sessions" search is allowed ONLY when bounded by at least one other filter.
 * https://freedomindex.us/fi_api.php?key=5f71b6205a7fef749f412c21ec971e43&action=legislators_cards&gov=ga&session_id=91&chamber=S&sort=score&order=desc
 */
function fi_api_args_legislators_cards(array $args): array {
	$gov = fi_api_upper($args['gov'] ?? '');


	// Canonicalize legacy aliases → canonical keys (contained here; keeps WP code untouched)
	if (!empty($args['session']) && empty($args['session_id'])) {
		$args['session_id'] = $args['session'];
	}
	unset($args['session']);

	$session_provided = array_key_exists('session_id', $args);
	$session_id = $session_provided ? fi_api_int($args['session_id'], 0) : null; // null means "not provided"

	$party = fi_api_upper($args['party'] ?? '');
	$state = fi_api_upper($args['state'] ?? '');
	$chamber = fi_api_upper($args['chamber'] ?? '');
	$name = fi_api_clean_text($args['name'] ?? '', 120);

	$sort = fi_api_lower($args['sort'] ?? 'na');
	$order = fi_api_upper($args['order'] ?? '');

	if ($gov === '') {
		return ['ok' => false, 'error' => 'invalid_args', 'gov' => $gov];
	}

	$sort_map = [
		'na' => ['orderby' => 'name',   'order' => 'ASC'],
		'nd' => ['orderby' => 'name',   'order' => 'DESC'],
		'pa' => ['orderby' => 'party',  'order' => 'ASC'],
		'pd' => ['orderby' => 'party',  'order' => 'DESC'],

		'sa' => ['orderby' => 'score',  'order' => 'ASC'],
		'sd' => ['orderby' => 'score',  'order' => 'DESC'],
		'ca' => ['orderby' => 'chamber','order' => 'ASC'],
		'cd' => ['orderby' => 'chamber','order' => 'DESC'],
	];

	$orderby = $sort_map[$sort]['orderby'] ?? 'name';
	$final_order = $sort_map[$sort]['order'] ?? 'ASC';
	if ($order === 'ASC' || $order === 'DESC') $final_order = $order;
	if ($party !== '' && !preg_match('/^[A-Z]{1,3}$/', $party)) $party = '';
	if ($state !== '' && !preg_match('/^[A-Z]{2}$/', $state)) $state = '';
	if ($chamber !== '' && !preg_match('/^[A-Z]{1,2}$/', $chamber)) $chamber = '';


	// If session explicitly provided as 0 => All Sessions.
	// Enforce bounded search (otherwise you risk 12k+ rows for US, and much worse for states across terms).
	if ($session_provided && (int)$session_id === 0) {
		$has_bound = ($name !== '' || $party !== '' || $state !== '' || $chamber !== '');
		if (!$has_bound) {
			return [
				'ok' => false,
				'error' => 'unbounded_search',
				'gov' => $gov,
				'session_id' => 0,
				'message' => 'Select a session, or add a filter (name, party, chamber, or state) to search across all sessions.',
			];
		}
	}


	// If session provided and >0 it’s valid; if omitted (null) we will default later.
	if ($session_provided && $session_id < 0) $session_id = 0;


	return [
		'ok' => true,
		'gov' => $gov,
		'session_provided' => $session_provided,
		'session_id' => $session_id, // null = omitted; 0 = all sessions; >0 = specific session
		'party' => $party,
		'state' => $state,
		'chamber' => $chamber,
		'name' => $name,
		'sort' => $sort,
		'orderby' => $orderby,
		'order' => $final_order,
	];
}


/**
 * Cards/list rows for gov+session OR bounded all-sessions search.
 * $v = validated args from fi_api_args_legislators_cards()
 */
function fi_api_legislators_cards(array $v): array {
	global $fidb;
	if (!$fidb) return [];

	$gov = $v['gov'];
	$session_id = $v['session_id']; // null|0|>0

	// ORDER mapping
	$order = [];
	switch ($v['orderby']) {
		case 'score':
			$order = ['l.score' => $v['order'], 'l.last_name' => 'ASC', 'l.first_name' => 'ASC', 'ls.id' => 'DESC'];
			break;
		case 'party':
			$order = ['ls.party' => $v['order'], 'l.last_name' => 'ASC', 'l.first_name' => 'ASC', 'ls.id' => 'DESC'];
			break;
		case 'chamber':
			$order = ['ls.chamber' => $v['order'], 'l.last_name' => 'ASC', 'l.first_name' => 'ASC', 'ls.id' => 'DESC'];
			break;
		case 'name':
		default:
			$order = ['l.last_name' => $v['order'], 'l.first_name' => $v['order'], 'ls.id' => 'DESC'];
			break;
	}

	// Common filters
	$where_and = ['ls.gov' => $gov];

	if (!empty($v['party']))   $where_and['ls.party'] = $v['party'];
	if (!empty($v['chamber'])) $where_and['ls.chamber'] = $v['chamber'];
	if (!empty($v['state']))   $where_and['ls.state'] = $v['state'];

	if (!empty($v['name'])) {
		$where_and['OR'] = [
			'l.display_name[~]' => $v['name'],
			'l.first_name[~]' => $v['name'],
			'l.last_name[~]' => $v['name'],
		];
	}

	// Mode A: specific session (>0)
	if (is_int($session_id) && $session_id > 0) {
		$where_and['ls.session_id'] = $session_id;

		$rows = $fidb->select(TB_LEGISLATOR_SESSIONS . ' (ls)', [
			'[>]'.TB_LEGISLATORS.' (l)' => ['legislator_id' => 'id'],
		], [
			'ls.legislator_id(id)',
			'l.display_name',
			'l.first_name',
			'l.last_name',
			'l.image_id',
			'l.score',
			'l.image_url',
			'ls.gov',
			'ls.session_id',
			'ls.state',
			'ls.chamber',
			'ls.district',
			'ls.party',
			'ls.score(session_score)',
			'ls.image_id(session_image_id)',
			'ls.id(session_row_id)',
		], [
			'AND' => $where_and,
			'ORDER' => $order,
			'LIMIT' => DB_LIMIT,
		]);

		return is_array($rows) ? $rows : [];
	}

	// Mode B: all sessions (session_id=0) bounded search
	// Front-end "All Sessions" means: search across parent sessions only.
	if ($session_id === 0) {
		// Join sessions to filter only parent sessions (strictly parent_id IS NULL).
		$where_and['s.parent_id'] = null;

		$rows = $fidb->select(TB_LEGISLATOR_SESSIONS . ' (ls)', [
			'[>]'.TB_LEGISLATORS.' (l)' => ['legislator_id' => 'id'],
			'[>]'.TB_SESSIONS.' (s)' => ['session_id' => 'id'],
		], [
			'ls.legislator_id(id)',
			'l.display_name',
			'l.first_name',
			'l.last_name',
			'l.image_id',
			'l.score as freedom_score',
			'l.image_url',
			'ls.gov',
			'ls.session_id',
			'ls.state',
			'ls.chamber',
			'ls.district',
			'ls.party',
			'ls.score as session_score',
			'ls.image_id(session_image_id)',
			'ls.id(session_row_id)',
			's.name(session_name)',
			's.date_end(session_date_end)',
		], [
			'AND' => $where_and,
			'ORDER' => [
				// Prefer newer terms first in all-sessions search
				'ls.session_id' => 'DESC',
				'ls.id' => 'DESC',
			],
			'LIMIT' => 200,
		]);

		return is_array($rows) ? $rows : [];
	}


	// session_id omitted (null) should never reach here if action defaults it.
	return [];
}



header('Content-Type: application/json; charset=utf-8');

try {
	global $fidb, $args;

	$v = fi_api_args_legislators_cards($args ?? []);
	if (empty($v['ok'])) {
		echo json_encode([
			'success' => false,
			'error' => $v['error'] ?? 'invalid_args',
			'action' => 'legislators_cards',
			'gov' => $v['gov'] ?? null,
			'session_id' => $v['session_id'] ?? null,
			'message' => $v['message'] ?? null,
		], JSON_UNESCAPED_SLASHES);
		exit;
	}

	$session_is_default = false;
	$session = null;

	// If session_id omitted => pick latest parent session
	if ($v['session_provided'] === false) {
		$session = fi_api_session_parent_latest($v['gov']);
		if (!$session) {
			echo json_encode([
				'success' => false,
				'error' => 'no_session',
				'action' => 'legislators_cards',
				'gov' => $v['gov'],
				'message' => 'No parent sessions found for this government.',
			], JSON_UNESCAPED_SLASHES);
			exit;
		}
		$v['session_id'] = (int)$session['id'];
		$session_is_default = true;
	}

	// If session_id is a real session (>0), load session name for subtitle
	if (is_int($v['session_id']) && $v['session_id'] > 0) {
		$session = $session ?: $fidb->get(TB_SESSIONS, ['id','name','date_end','parent_id'], [
			'id' => (int)$v['session_id'],
			'LIMIT' => 1,
		]);
	}

	$rows = fi_api_legislators_cards($v);
	$count = is_array($rows) ? count($rows) : 0;

	// Filter Description builder (fast + deterministic; you can fancy this up later)
	$session_name = $session['name'] ?? '';
	$parts = [];
	if ($v['session_id'] === 0) {
		$parts[] = 'All Sessions';
	} else {
		if ($session_name) $parts[] = $session_name;
	}
	if (!empty($v['gov']) && $v['gov'] === 'US') {
		if (!empty($v['chamber'])) {
			if ($v['chamber'] === 'S') $parts[] = 'Senators';
			elseif ($v['chamber'] === 'H') $parts[] = 'Representatives';
		}
		if (!empty($v['state'])) $parts[] = 'from ' . $v['state'];
	}
	if (!empty($v['party'])) $parts[] = $v['party'] . ' Party';
	$filter_description = trim(implode(' ', $parts));
	if ($filter_description !== '') $filter_description .= ' | ';
	$filter_description .= $count . ' Found.';

	//Enhance with district names
	$district_names = fi_api_districts_get_names($v['gov']);
	foreach($rows as &$row){
		if(!empty($row['district'])){
			$dist_name = $district_names[$row['district']] ?? '';
			if($dist_name !== ''){
				//Deal with US Congress 'AL 4th' > remove state and trim
				if($v['gov'] === 'US'){
					$dist_name = str_replace($row['state'] . ' ', '', $dist_name);
					$dist_name = trim($dist_name);
				}
				// ordinal suffix: TH, ND, ST, RD, etc. Inject <span> around ordinal suffix so we can style it differently.
				$dist_name = preg_replace('/(\d+)(st|nd|rd|th)/', '$1<span class="ordsfx">$2</span>', $dist_name);
				$row['district_name'] = $dist_name;
			}else{
				$row['district_name'] = '';
			}
		}
	}
	unset($row); // break reference


	echo json_encode([
		'success' => true,
		'action' => 'legislators_cards',
		'gov' => $v['gov'],

		// Important for front-end defaults:
		'session_id_used' => $v['session_id'],
		'session_is_default' => $session_is_default ? 1 : 0,
		'session_name' => $session_name,

		'count' => $count,
		'filter_description' => $filter_description,

		'filters' => [
			'session_id' => $v['session_id'],
			'party' => $v['party'],
			'chamber' => $v['chamber'],
			'state' => $v['state'],
			'name' => $v['name'],
			'sort' => $v['sort'],
			'order' => $v['order'],
		],

		'results' => $rows,
	], JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
	echo json_encode([
		'success' => false,
		'error' => 'exception',
		'message' => 'API error',
		'detail' => [
			'msg' => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
		],
	], JSON_UNESCAPED_SLASHES);
}

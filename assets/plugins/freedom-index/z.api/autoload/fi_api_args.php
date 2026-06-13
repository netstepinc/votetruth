<?php
// /api/autoload/fi_api_args.php
// Standalone helpers (NO WP functions). Normalizes and validates FI API args.

if (!defined('ABSPATH')) exit;

/**
 * Universal FI API Args processor: normalizes, validates, and applies defaults to FI API arguments.
 * Can be used to process either input from $_GET (default behavior) or a custom array.
 *
 * Returns a cleaned argument array (or error details).
 * Usage: $args = fi_api_get_args(); // from $_GET, or fi_api_get_args($my_args);
 */
function fi_api_get_args(array $raw_args = null): array {
	static $defs = [
		'gov'           => ['type' => 'upper',  'default' => ''],
		'session_id'    => ['type' => 'int',    'default' => null],
		'district_id'   => ['type' => 'int',    'default' => 0],
		'district'      => ['type' => 'string', 'default' => ''],
		'legislator_id' => ['type' => 'int',    'default' => 0],
		'vote_id'       => ['type' => 'int',    'default' => 0],
		'vote_tag_id'   => ['type' => 'int',    'default' => 0],
		'report_id'     => ['type' => 'int',    'default' => 0],
		'chamber'       => ['type' => 'upper',  'default' => ''],
		'party'         => ['type' => 'upper',  'default' => ''],
		'name'          => ['type' => 'text',   'default' => '', 'max' => 120],
		'state'         => ['type' => 'upper',  'default' => ''],
		'compare'       => ['type' => 'string', 'default' => ''],
		'sort'          => ['type' => 'lower',  'default' => 'na'],
		'order'         => ['type' => 'upper',  'default' => ''],
		'session'       => ['type' => 'int',    'default' => null],
	];

	// Efficiently hydrate $raw_args if not provided
	if ($raw_args === null) {
		$raw_args = [];
		foreach ($defs as $key => $def) {
			if (isset($_GET[$key]) && is_scalar($_GET[$key])) {
				$raw_args[$key] = $_GET[$key];
			}
		}
	}

	// Normalize legacy keys: session → session_id, district → district_id
	if (!empty($raw_args['session']) && empty($raw_args['session_id'])) {
		$raw_args['session_id'] = $raw_args['session'];
	}
	unset($raw_args['session']);

	if (!empty($raw_args['district']) && empty($raw_args['district_id'])) {
		$raw_args['district_id'] = $raw_args['district'];
	}
	unset($raw_args['district']);

	$clean = [];
	foreach ($defs as $key => $def) {
		$type = $def['type'] ?? 'string';
		$default = array_key_exists('default', $def) ? $def['default'] : null;
		$val = $raw_args[$key] ?? $default;

		switch ($type) {
			case 'int':
				if (!is_scalar($val) || !is_numeric($val) || $val === '' || $val === null) {
					$val = (int)$default;
				} else {
					$val = (int)$val;
				}
				break;
			case 'upper':
				$val = is_scalar($val) ? strtoupper(trim((string)$val)) : '';
				break;
			case 'lower':
				$val = is_scalar($val) ? strtolower(trim((string)$val)) : '';
				break;
			case 'text':
				$max = $def['max'] ?? 180;
				$val = is_scalar($val) ? trim((string)$val) : '';
				$val = strip_tags($val);
				if ($max > 0 && strlen($val) > $max) {
					$val = mb_substr($val, 0, $max);
				}
				$val = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
				break;
			case 'string':
			default:
				$val = trim((string)$val);
				break;
		}
		$clean[$key] = $val;
	}

	// Sort logic mapping
	static $sort_map = [
		'na' => ['orderby' => 'name',    'order' => 'ASC'],
		'nd' => ['orderby' => 'name',    'order' => 'DESC'],
		'sa' => ['orderby' => 'score',   'order' => 'ASC'],
		'sd' => ['orderby' => 'score',   'order' => 'DESC'],
		'pa' => ['orderby' => 'party',   'order' => 'ASC'],
		'pd' => ['orderby' => 'party',   'order' => 'DESC'],
		'ca' => ['orderby' => 'chamber', 'order' => 'ASC'],
		'cd' => ['orderby' => 'chamber', 'order' => 'DESC'],
	];
	$sort_code = $clean['sort'];
	$orderby = $sort_map[$sort_code]['orderby'] ?? 'name';
	$final_order = $sort_map[$sort_code]['order'] ?? 'ASC';
	if ($clean['order'] === 'ASC' || $clean['order'] === 'DESC') {
		$final_order = $clean['order'];
	}
	$clean['orderby'] = $orderby;
	$clean['order'] = $final_order;

	// Validation constraints
	if ($clean['party'] !== '' && !preg_match('/^[A-Z]{1,3}$/', $clean['party'])) {
		$clean['party'] = '';
	}
	if ($clean['state'] !== '' && !preg_match('/^[A-Z]{2}$/', $clean['state'])) {
		$clean['state'] = '';
	}
	if ($clean['chamber'] !== '' && !preg_match('/^[A-Z]{1,2}$/', $clean['chamber'])) {
		$clean['chamber'] = '';
	}

	// 'session provided' logic - prevents unbounded search
	$session_provided = array_key_exists('session_id', $raw_args);
	$session_id = $session_provided ? (int)$clean['session_id'] : null;
	if ($session_provided && $session_id === 0) {
		$has_bound = (
			$clean['name'] !== '' ||
			$clean['party'] !== '' ||
			$clean['state'] !== '' ||
			$clean['chamber'] !== ''
		);
		if (!$has_bound) {
			return [
				'ok' => false,
				'error' => 'unbounded_search',
				'gov' => $clean['gov'],
				'session_id' => 0,
				'message' => 'Select a session, or add a filter (name, party, chamber, or state) to search across all sessions.',
			];
		}
	}
	// Clamp session_id: negative values are forced to 0 for safety
	if ($session_provided && $session_id < 0) $session_id = 0;

	$clean['session_provided'] = $session_provided;
	$clean['session_id'] = $session_id;
	$clean['ok'] = true;

	return $clean;
}
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* 
FI_Get.php is the standalone query engine for the Freedom Index API.
It uses Medoo as the database layer to query the database and return the results in a JSON format.
@PUBLIC_HTML\wp-content\plugins\freedom-index\api\fi_get.php

NOTE: Do not over complicate API requests with multiple files. Each Action can be self-contained except for reusable helpers.

DB Schema for reference: @PUBLIC_HTML\wp-content\plugins\freedom-index\admin\autoload\schema.php
- fi_votes:chamber and fi_legislator_sessions:chamber are deprecated and 'chamber' fields will be used instead.

Questions this file should answer:
What are the current legislators for a given gov? (gov = state or federal)
- Filter by party, district, chamber, sort by name, party, chamber, score

What are the sessions for this government?
What are the votes for this government?
What are the vote reports for this government?

Example Request URL: 
- https://freedomindex.us/fi_api.php?key=5f71b6205a7fef749f412c21ec971e43&gov=us&action=legislators_get&session_id=14
- https://freedomindex.us/fi_api.php?key=5f71b6205a7fef749f412c21ec971e43&gov=us&action=legislators_cards&session_id=14
- https://freedomindex.us/fi_api.php?key=5f71b6205a7fef749f412c21ec971e43&action=legislators_cards&gov=co&session_id=349&chamber=H&party=R&name=Armagost&sort=name&order=desc
*/
define('SITE_DOMAIN', 'freedomindex.us');
define('SITE_URL','https://'.SITE_DOMAIN);
define('FILE_APP','fi_api.php');
define('PATH_APP',ABSPATH.'wp-content/plugins/freedom-index/');
define('PATH_APP_CORE', PATH_APP.'core/');
define('PATH_APP_ACTION', PATH_APP.'api/actions/');
define('PATH_APP_LOAD', PATH_APP.'api/autoload/');
define('PATH_CACHE', ABSPATH.'wp-content/jbsfi/api/');
define('PATH_LOG', ABSPATH.'wp-content/jbsfi/api_log/');
define('SITE_KEY', md5('freedom-index-us'));
define('URL_APP','https://'.SITE_DOMAIN.'/'.FILE_APP.'?key='.SITE_KEY);



//Database settings
define( 'DB_NAME', 'jbs_wpmsn' );
define( 'DB_USER', 'jbs_wpdbadmin' );
define( 'DB_PASSWORD', 'l,{fCcmmY)6~wb,Q^W' );
define('DB_PRE','jbsw_5_');
define('DB_LIMIT',600);

define('TB_LEGISLATORS',DB_PRE.'fi_legislators');
define('TB_LEGISLATOR_SESSIONS',DB_PRE.'fi_legislator_sessions');
define('TB_REPORTS',DB_PRE.'fi_reports');
define('TB_LOG',DB_PRE.'fi_log');
define('TB_VOTES',DB_PRE.'fi_votes');
define('TB_VOTERC',DB_PRE.'fi_voterc');
define('TB_VOTE_TAGS',DB_PRE.'fi_vote_tags');
define('TB_SESSIONS',DB_PRE.'fi_sessions');
define('TB_TAXONOMY',DB_PRE.'fi_taxonomy');
define('TB_USER_LISTS',DB_PRE.'fi_user_lists');
//echo SITE_KEY;exit;

// Allowed actions:
$allowed_actions = [
	'legislator',
	'legislators_cards',
	'votes_legislator',
	'votes_tag_legislator',
	'votes_session_legislator',
	'votes_report_legislator',
];

// This file is included by PUBLIC_HTML/fi_api.php which sets ABSPATH
if (!defined('ABSPATH')) {
	header('HTTP/1.1 500 Internal Server Error');
	exit('Configuration error');
}

// Reject non-GET or missing/invalid key with explicit response (no info leak)
$key = isset($_GET['key']) && is_string($_GET['key']) ? $_GET['key'] : '';
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !hash_equals(SITE_KEY, $key)) {
	header('HTTP/1.1 403 Forbidden');
	exit('Forbidden');
}

// We are already validating $action by checking it against $allowed_actions;
// this is sufficient to trust it for constructing the action file path.
$action = isset($_GET['action']) && is_string($_GET['action']) ? trim($_GET['action']) : '';
if (!in_array($action, $allowed_actions, true)) {
	header('HTTP/1.1 403 Forbidden');
	exit('Forbidden');
}

//Load application constants
require_once PATH_APP_CORE . '_reference.php';

//Load all files in the autoload folder
$files = glob(PATH_APP_LOAD . '*.php') ?: [];
usort($files, function ($a, $b) {
	return strnatcasecmp(basename($a), basename($b));
});
foreach ($files as $file) {
	$base = basename($file);
	// Skip dev/disabled modules
	if (stripos($base, 'dev') !== false || stripos($base, 'copy') !== false) continue;
	include_once $file;
}

//GET, clean, validate, and normalize args
$args = fi_api_get_args($_GET);


// Medoo connection for API queries
$fidb = new Medoo\Medoo([
	'type' => 'mysql',
	'host' => 'localhost',
	'database' => DB_NAME,
	'username' => DB_USER,
	'password' => DB_PASSWORD,
	'charset' => 'utf8mb4',
	'collation' => 'utf8mb4_unicode_ci',
	'port' => 3306,
	'prefix' => '',
	'error' => PDO::ERRMODE_SILENT,
	'logging' => true,
	'option' => [
		PDO::ATTR_CASE        => PDO::CASE_NATURAL,
		PDO::ATTR_PERSISTENT  => true,  // Reuse connections per worker process — reduces "too many connections" under load
	],
	'command' => [
		'SET SQL_MODE=ANSI_QUOTES'
	]
]);

$action_file = PATH_APP_ACTION . $action . '.php';
$path_action = realpath(PATH_APP_ACTION);
$path_action_file = realpath($action_file);
// Resolve under actions only; prevents path traversal if actions is symlinked
if ($path_action_file !== false && $path_action !== false && strpos($path_action_file, $path_action) === 0) {
	require_once $action_file;
} else {
	header('HTTP/1.1 404 Not Found');
	exit('Action not permitted');
}
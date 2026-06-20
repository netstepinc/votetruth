<?php
/**
 * Plugin Name: Freedom Index
 * Description: The Freedom Index: schema, admin tools, read-only public API, display tools and Progressive Web App.
 * Version:     4.5.0
 * Author:      Sam Mittelstaedt
 * V1: 20?? Joomla PHP script @ TheNewAmerican.com
 * V2: 2021 WordPress theme-based Congress legislators @ TheNewAmerican.com
 * V3: 2023 WordPress multisite network: plugin-based State legislators @ TheFreedomIndex.org with each state as a subsite
 * V4: 2025 WordPress multisite network: plugin-based Combined State and Congress legislators in one site capable of serving data to other sites.
 * V5: 2026 WordPress stand alone site + plugin+theme based on Storybrand.com Zero Cognitive Load principles.
 */

if (!defined('ABSPATH')) { exit; }

//DEBUG: require_once FI_DIR . 'public/inc/boot-trace.php';

// Development mode - set to true to bypass caching during development
if (!defined('FI_DEV')) {
	define('FI_DEV', false);
}

// Enable display_errors for development only if constant FI_DEV_MODE is defined and true
//@ini_set('display_errors', '1'); @ini_set('display_startup_errors', '1'); error_reporting(E_ERROR);

define('FI_VERSION', '5.1.0');
define('FI_MIN_PHP', '8.0');
define('FI_DIR', plugin_dir_path(__FILE__));
define('FI_URL', plugin_dir_url(__FILE__));
define('FI_PUBLIC_DIR', FI_DIR . 'public/');
define('FI_PUBLIC_URL', FI_URL . 'public/');
define('FI_URL_IMG', FI_URL . 'assets/img/');
define('FI_URL_CSS', FI_URL . 'assets/css/');
define('FI_URL_JS', FI_URL . 'assets/js/');
define('FI_ADMIN_NS', 'FI\\Admin');
define('FI_AUTHOR', 'Sam Mittelstaedt');
define('FI_AUTHOR_EMAIL', 'smittelstaedt@jbs.org');
define('FI_SHARE_IMAGE', home_url() . '/assets/files/2026/share-freedom-index.jpg');

define('FI_DIR_CACHE',WP_CONTENT_DIR.'/cache/');
define('FI_DIR_PDF', FI_DIR_CACHE . 'pdf/');

define('FI_FINDMY_AUTO',false);

// LegiScan storage layout (keeps /legiscan clean and structured)
// - Extracted datasets: {FI_DIR_LEGISCAN}{GOV}/{SESSION_FOLDER}/...
// - API JSON reference cache: {FI_DIR_LEGISCAN_JSON}*.json
// - Downloaded dataset ZIPs: {FI_DIR_LEGISCAN_ZIP}*.zip
define('FI_DIR_LEGISCAN', rtrim(FI_DIR_CACHE, "/\\") . DIRECTORY_SEPARATOR . 'legiscan' . DIRECTORY_SEPARATOR);
define('FI_DIR_LEGISCAN_JSON', FI_DIR_LEGISCAN . '_json' . DIRECTORY_SEPARATOR);
define('FI_DIR_LEGISCAN_ZIP', FI_DIR_LEGISCAN . '_zip' . DIRECTORY_SEPARATOR);

// Image repository (manually uploaded, register-in-place)
// NOTE: historically FI_DIR_IMAGES was used as a URL. Keep it for backward compatibility.
define('FI_URL_IMAGES', site_url() . '/assets/files/fi/');
define('FI_PATH_IMAGES', ABSPATH . 'assets/files/fi/');
define('FI_DIR_IMAGES', FI_URL_IMAGES);

// Default local image directory lookup used by migration + queued image importer.
/*
add_filter('fi_migrate_images_local_dir', function ($dir, $gov) {
	return (defined('FI_PATH_IMAGES') ? FI_PATH_IMAGES : $dir);
}, 10, 2);
*/

// Capabilities/Roles (internal plugin access control)
define('FI_ROLE_MANAGER', 'fi_manager');
define('FI_CAP_MANAGE', 'fi_manage_freedom_index');

//1-site's tables will be used for all sites so WP site prefix is not needed.
//Admin module can use standard WP prefix, but the public module must use the 1-site prefix.
$tbl_prefix = 'vtttus_fi_';
// Freedom Index Table Names - Strictly follows schema in admin/autoload/schema.php

define('TBFI_LEGISLATORS',            $tbl_prefix . 'legislators');            // Legislators (core person identities)
define('TBFI_LEGISLATOR_SESSIONS',    $tbl_prefix . 'legislator_sessions');    // Legislator ↔ Session (per term, chamber, etc.)
define('TBFI_VOTES',                  $tbl_prefix . 'votes');                  // Votes (scored items/rollcalls)
define('TBFI_VOTERC',                 $tbl_prefix . 'voterc');                 // Roll-call (who voted how)
define('TBFI_REPORTS',                $tbl_prefix . 'reports');                // Shareable Reports (public by slug, scoped to session)
define('TBFI_VOTE_TAGS',              $tbl_prefix . 'vote_tags');              // Vote-to-Tag assignments (categorization/metadata)
define('TBFI_SESSIONS',               $tbl_prefix . 'sessions');               // Sessions (government-bounded time buckets)
define('TBFI_TAXONOMY',               $tbl_prefix . 'taxonomy');               // Consolidated Taxonomy (parties, tags, districts)
define('TBFI_LEGACY_REDIRECTS',       $tbl_prefix . 'legacy_redirects');       // Legacy redirects
define('TBFI_LOG',                    $tbl_prefix . 'log');                    // System logging table
define('TBFI_USER_LISTS',             $tbl_prefix . 'user_lists');             // User legislator lists


define('FI_API_KEY','5f71b6205a7fef749f412c21ec971e43');
define('FI_API_URL', home_url('/fi_api.php') );

//API Keys
define('API_KEY_LEGISCAN','56a5a51a0fba7e504126d62bfa5a6986');
define('API_KEY_GEOCOD', '73713543404bb2183071611b86a4605a8666a15');
define('API_KEY_GOVTRACK', '');
define('API_KEY_VOTESMART', '');
define('API_KEY_OPENSTATES', 'cbfc01f3-aa7f-4c13-b209-9975f0263592');
//Ballotpedia is a paid service. Do not use at this time.

//External API URLs
define('URL_BIOGUIDE', 'https://bioguide.congress.gov/search/bio/'); //https://bioguide.congress.gov/search/bio/A000055
define('URL_BIOGUIDE_SEARCH', 'https://bioguide.congress.gov/search?keyword='); //https://bioguide.congress.gov/search?keyword=Robert%20Aderholt
define('URL_GOVTRACK', 'https://www.govtrack.us/congress/members/'); //https://www.govtrack.us/congress/members/400004
define('URL_GOVTRACK_SEARCH', 'https://www.govtrack.us/search?q='); //https://www.govtrack.us/search?q=Robert%20Aderholt
define('URL_BALLOTPEDIA', 'https://ballotpedia.org/'); //https://ballotpedia.org/Robert_Aderholt
define('URL_BALLOTPEDIA_SEARCH', 'https://ballotpedia.org/wiki/index.php?search=');
define('URL_VOTESMART', 'https://justfacts.votesmart.org/candidate/biography/'); //https://justfacts.votesmart.org/candidate/biography/441
define('URL_LEGISCAN_BIO','https://legiscan.com/US/people//id/'); //Legiscan URLs = https://legiscan.com/US/people/robert-aderholt/id/9346...so we need the slug and the ID or full URL

// Optional Legislator Meta reference sites
//define('URL_OPENSTATES', ''); //Full URL saved in meta['url_openstates'] so we don't need a separate URL.
define('URL_OPENSECRETS','https://www.opensecrets.org/members-of-congress/summary?cid='); //https://www.opensecrets.org/members-of-congress/summary?cid=N00003028
define('URL_OPENSECRETS_SEARCH','https://www.opensecrets.org/search?type=indiv&q='); //https://www.opensecrets.org/search?q=Robert+Aderholt&type=indiv

define('FI_PRIVACY_PROMISE', 'We will <b>NEVER</b> share your information with any other organization.');


$governments = [
	'US' => 'Congress',
	'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
	'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia',
	'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
	'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
	'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri',
	'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
	'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
	'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
	'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
	'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming'
];
define('FI_GOVERNMENTS', $governments);


$states = [
	'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
	'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
	'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
	'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
	'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'
];
define('FI_STATES', $states);


/**
* Chamber labels by government
* Maps chamber codes (H/S) to display labels
* NOTE: This is chamber-centric.
*/
$chambers = [
	'default' => [
		'S' => ['short' => 'Sen.','title' => 'Senator', 'plural' => 'Senators', 'chamber' => 'Senate'],
		'H' => ['short' => 'Rep.','title' => 'Representative', 'plural' => 'Representatives', 'chamber' => 'House'],
	],
	'CA' => ['H' => ['short' => 'Mem.','title' => 'Assemblymember', 'plural' => 'Members', 'chamber' => 'Assembly'],],
	'MD' => ['H' => ['short' => 'Del.','title' => 'Delegate', 'plural' => 'Delegates', 'chamber' => 'House'],],
	'NJ' => ['H' => ['short' => 'Mem.','title' => 'Assemblymember', 'plural' => 'Members', 'chamber' => 'Assembly'],],
	'NV' => ['H' => ['short' => 'Mem.','title' => 'Assemblymember', 'plural' => 'Members', 'chamber' => 'Assembly'],],
	'NY' => ['H' => ['short' => 'Mem.','title' => 'Assemblymember', 'plural' => 'Members', 'chamber' => 'Assembly'],],
	'VA' => ['H' => ['short' => 'Del.','title' => 'Delegate', 'plural' => 'Delegates', 'chamber' => 'House'],],
	'WI' => ['H' => ['short' => 'Mem.','title' => 'Assemblymember', 'plural' => 'Members', 'chamber' => 'Assembly'],],
	'WV' => ['H' => ['short' => 'Del.','title' => 'Delegate', 'plural' => 'Delegates', 'chamber' => 'House'],],
	'NE' => ['H' => [], 'S' => ['short' => 'Sen.','title' => 'Senator', 'plural' => 'Senators', 'chamber' => 'Legislature']] // Unicameral legislature
];
//Hydrate array to show chambers for all governments
foreach($governments as $gov => $gov_name){
	$ch = $chambers['default'];
	if(isset($chambers[$gov])){
		$ch = array_merge($ch, $chambers[$gov]);
		foreach($chambers[$gov] as $ch_code => $ch_data){
			if(empty($ch_data)){
				unset($ch[$ch_code]);
			}
		}
	}
	$chambers[$gov] = $ch;
}
unset($chambers['default']); //This breaks things
define('FI_CHAMBERS', $chambers);


/**
* Congressional district count by state (US only)
* Number of U.S. House districts per state.
* 
* NOTE: U.S. Senators are elected AT-LARGE statewide (not district-based).
* Each state has 2 U.S. Senators elected statewide. Only U.S. House Representatives use districts.
* Therefore, congressional districts only apply to chamber 'H' (Representative).
* Chamber 'S' (Senator) at the US level does not use districts.
*/
$congressional_districts = [
	'AL' => 7, 'AK' => 1, 'AZ' => 9, 'AR' => 4, 'CA' => 52, 'CO' => 8, 'CT' => 5, 'DE' => 1, 'FL' => 28, 'GA' => 14,
	'HI' => 2, 'ID' => 2, 'IL' => 17, 'IN' => 9, 'IA' => 4, 'KS' => 4, 'KY' => 6, 'LA' => 6, 'ME' => 2, 'MD' => 8,
	'MA' => 9, 'MI' => 13, 'MN' => 8, 'MS' => 4, 'MO' => 8, 'MT' => 2, 'NE' => 3, 'NV' => 4, 'NH' => 2, 'NJ' => 13,
	'NM' => 3, 'NY' => 26, 'NC' => 14, 'ND' => 1, 'OH' => 15, 'OK' => 5, 'OR' => 6, 'PA' => 17, 'RI' => 2, 'SC' => 7,
	'SD' => 1, 'TN' => 9, 'TX' => 38, 'UT' => 4, 'VT' => 1, 'VA' => 11, 'WA' => 10, 'WV' => 2, 'WI' => 8, 'WY' => 1
];
define('FI_CONGRESSIONAL_DISTRICTS', $congressional_districts);

/**
* State legislative districts count by state and chamber
* Senate districts (S chamber) and House districts (H chamber)
* 
* IMPORTANT DISTRICT INFORMATION:
* 
* State Senate Districts:
* - ALL state senators are elected from districts (no at-large elections)
* - Districts are typically referred to as "State Senate District X" (e.g., "1st State Senate District")
* - Even small states (WY, VT, DE) use district-based representation
* - Historically, some states used multi-member Senate districts, but most now use single-member districts
* 
* State House Districts:
* - State Representatives/House members are elected from districts
* - Some small states historically had at-large lower chamber seats, but this is no longer common
* 
* Nebraska (Unicameral):
* - Nebraska has only one legislative chamber (Nebraska Legislature)
* - Functions as both Senate and House
* - Still uses 49 districts (not at-large)
* - Chamber 'S' is used, but 'H' is not applicable
* 
* US vs State:
* - US Senators (US): AT-LARGE (statewide, no districts)
* - US Representatives (US): DISTRICT-BASED
* - State Senators: DISTRICT-BASED
* - State Representatives: DISTRICT-BASED
*/
$state_districts = [
	'senate' => [
		'AL' => 35, 'AK' => 20, 'AZ' => 30, 'AR' => 35, 'CA' => 40, 'CO' => 35, 'CT' => 36, 'DE' => 21, 'FL' => 40,
		'GA' => 56, 'HI' => 25, 'ID' => 35, 'IL' => 59, 'IN' => 50, 'IA' => 50, 'KS' => 40, 'KY' => 38, 'LA' => 39,
		'ME' => 35, 'MD' => 47, 'MA' => 40, 'MI' => 38, 'MN' => 67, 'MS' => 52, 'MO' => 34, 'MT' => 50, 'NE' => 49,
		'NV' => 21, 'NH' => 24, 'NJ' => 40, 'NM' => 42, 'NY' => 63, 'NC' => 50, 'ND' => 47, 'OH' => 33, 'OK' => 48,
		'OR' => 30, 'PA' => 50, 'RI' => 38, 'SC' => 46, 'SD' => 35, 'TN' => 33, 'TX' => 31, 'UT' => 29, 'VT' => 30,
		'VA' => 40, 'WA' => 49, 'WV' => 17, 'WI' => 33, 'WY' => 31
	],
	'house' => [
		'AL' => 105, 'AK' => 40, 'AZ' => 60, 'AR' => 100, 'CA' => 80, 'CO' => 65, 'CT' => 151, 'DE' => 41, 'FL' => 120,
		'GA' => 180, 'HI' => 51, 'ID' => 70, 'IL' => 118, 'IN' => 100, 'IA' => 100, 'KS' => 125, 'KY' => 100, 'LA' => 105,
		'ME' => 151, 'MD' => 141, 'MA' => 160, 'MI' => 110, 'MN' => 134, 'MS' => 122, 'MO' => 163, 'MT' => 100, 'NE' => null,
		'NV' => 42, 'NH' => 400, 'NJ' => 80, 'NM' => 70, 'NY' => 150, 'NC' => 120, 'ND' => 94, 'OH' => 99, 'OK' => 101,
		'OR' => 60, 'PA' => 203, 'RI' => 75, 'SC' => 124, 'SD' => 70, 'TN' => 99, 'TX' => 150, 'UT' => 75, 'VT' => 150,
		'VA' => 100, 'WA' => 98, 'WV' => 100, 'WI' => 99, 'WY' => 62
	]
];
define('FI_STATE_DISTRICTS', $state_districts);


$parties = [
	'R' => [
		'name' => 'Republican',
		'bg_class' => 'bg-party-r',
		'bg_color' => '#E9141D',
		'text_class' => 'fi-party-r',
		'text_color' => '#fff',
	],
	'D' => [
		'name' => 'Democrat',
		'bg_class' => 'bg-party-d',
		'bg_color' => '#0015BC',
		'text_class' => 'fi-party-d',
		'text_color' => '#fff',
	],
	'DL' => [
		'name' => 'Democrat-Liberal',
		'bg_class' => 'bg-party-dl',
		'bg_color' => '#0015BC',
		'text_class' => 'fi-party-dl',
		'text_color' => '#fff',
	],
	'RC' => [
		'name' => 'Republican-Conservative',
		'bg_class' => 'bg-party-rc',
		'bg_color' => '#E9141D',
		'text_class' => 'fi-party-rc',
		'text_color' => '#fff',
	],
	'L' => [
		'name' => 'Libertarian',
		'bg_class' => 'bg-party-l',
		'bg_color' => '#FFDF00',
		'text_class' => 'fi-party-l',
		'text_color' => '#000',
	],
	'I' => [
		'name' => 'Independent',
		'bg_class' => 'bg-party-i',
		'bg_color' => '#666',
		'text_class' => 'fi-party-i',
		'text_color' => '#fff',
	],
	'P' => [
		'name' => 'Progressive',
		'bg_class' => 'bg-party-p',
		'bg_color' => '#0015BC',
		'text_class' => 'fi-party-p',
		'text_color' => '#fff',
	],
	'O' => [
		'name' => 'Other',
		'bg_class' => 'bg-party-o',
		'bg_color' => '#888',
		'text_class' => 'fi-party-o',
		'text_color' => '#fff',
	],
];
define('FI_PARTIES', $parties);


// Autoload global resources
fi_autoload_module(FI_DIR . 'core/');

//public is always loaded if plugin is active
fi_autoload_module(FI_DIR . 'public/autoload/');

// Autoload modules
if(is_admin() ) {
	// Register activation hook at bootstrap level.
	register_activation_hook(__FILE__, 'fi_plugin_activate');

	// Autoload admin functions (excludes files starting with 'DEV')
	fi_autoload_module(FI_DIR . 'admin/autoload/');
}

// Load PWA system (single drop-in file)
if (file_exists(FI_DIR . 'pwa.php')) {
	//if(function_exists('fi_boot_trace')) {fi_boot_trace('autoload:pwa:init');}
	require_once FI_DIR . 'pwa.php';
}

// Enqueue public assets
function fi_enqueue_public_assets() {

	//Enqueue Freedom Index inline JavaScript
	fi_public_inline_js();

	//dump query vars and exit
	//global $wp_query; echo '<pre>'; var_dump($wp_query->query_vars); echo '</pre>';

	// Check if this is a Freedom Index page
	if (get_query_var('fi_view') || get_query_var('fi_gov') || get_query_var('fi_entity')) {
		$css_ver = filemtime(FI_DIR . 'assets/css/public.css');
		wp_enqueue_style('fi-public-css',FI_URL . 'assets/css/public.css',[],$css_ver);
		
		// Enqueue jQuery if not already enqueued
		wp_enqueue_script('jquery');
		
		// Conditionally enqueue Swiper.js for government page
		if (get_query_var('fi_entity') == 'government') {
			wp_enqueue_style(
				'swiper-bundle-css',
				'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.css',
				array(),
				'11.0.5'
			);
			
			wp_enqueue_script(
				'swiper-bundle-js',
				'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.js',
				array(),
				'11.0.5',
				true
			);
		}
	}
}
add_action('wp_enqueue_scripts', 'fi_enqueue_public_assets');

function fi_autoload_module($autoload_dir) {
	//if(function_exists('fi_boot_trace')) {fi_boot_trace('START:'.$autoload_dir);}
	if (is_dir($autoload_dir)) {
		$files = glob($autoload_dir . '*.php') ?: [];
		usort($files, function ($a, $b) {
			return strnatcasecmp(basename($a), basename($b));
		});
		
		foreach ($files as $file) {
			$base = basename($file);
			// Skip dev/disabled modules
			if (stripos($base, 'dev') !== false || stripos($base, 'copy') !== false || stripos($base,'v1') !== false) continue;
			//if (function_exists('fi_boot_trace')) {fi_boot_trace('include:module:file', ['dir' => basename(rtrim($autoload_dir, "/\\")), 'file' => $base]);}
			include_once $file; // modules register hooks on load
		}
	}
	//if(function_exists('fi_boot_trace')) {fi_boot_trace('END:'.$autoload_dir);}
}
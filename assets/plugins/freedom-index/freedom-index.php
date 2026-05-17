<?php
/**
 * Plugin Name: Freedom Index
 * Description: The Freedom Index: schema, admin tools, read-only public API, display tools and Progressive Web App.
 * Version:     4.5.0
 * Author:      Sam Mittelstaedt
 * V1: 20?? Joomla PHP script @ TheNewAmerican.com
 * V2: 2021 WordPress theme-based Congress legislators @ TheNewAmerican.com
 * V3: 2023 WordPress plugin-based State legislators @ TheFreedomIndex.org with each state as a subsite
 * V4: 2025 WordPress plugin-based Combined State and Congress legislators in one site capable of serving data to other sites.
 * V5: 2026 WordPress plugin+theme based on Storybrand.com Zero Cognitive Load principles.
 */

if (!defined('ABSPATH')) { exit; }

//DEBUG: require_once FI_DIR . 'public/inc/boot-trace.php';

// Development mode - set to true to bypass caching during development
if (!defined('FI_DEV')) {
	define('FI_DEV', false);
}

// Enable display_errors for development only if constant FI_DEV_MODE is defined and true
//@ini_set('display_errors', '1'); @ini_set('display_startup_errors', '1'); error_reporting(E_ERROR);

define('FI_VERSION', '4.1.12');
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
define('FI_SHARE_IMAGE', 'https://freedomindex.us/assets/sites/5/2026/share-freedom-index.jpg');

define('FI_DIR_CACHE',WP_CONTENT_DIR.'/jbsfi/');
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
define('FI_URL_IMAGES', site_url() . '/assets/sites/5/fi/');
define('FI_PATH_IMAGES', ABSPATH . 'assets/sites/5/fi/');
define('FI_DIR_IMAGES', FI_URL_IMAGES);

//Icon images for PDF scorecards
define('FII_UPB', 'thumbs-up-black.png');
define('FII_UPW', 'thumbs-up-white.png');
define('FII_UPR', 'thumbs-up-red.png');
define('FII_UPG', 'thumbs-up-green.png');
define('FII_DNB', 'thumbs-down-black.png');
define('FII_DNW', 'thumbs-down-white.png');
define('FII_DNR', 'thumbs-down-red.png');
define('FII_DNG', 'thumbs-down-green.png');

// Default local image directory lookup used by migration + queued image importer.
add_filter('fi_migrate_images_local_dir', function ($dir, $gov) {
	return (defined('FI_PATH_IMAGES') ? FI_PATH_IMAGES : $dir);
}, 10, 2);

// Capabilities/Roles (internal plugin access control)
define('FI_ROLE_MANAGER', 'fi_manager');
define('FI_CAP_MANAGE', 'fi_manage_freedom_index');

//1-site's tables will be used for all sites so WP site prefix is not needed.
//Admin module can use standard WP prefix, but the public module must use the 1-site prefix.
$tbl_prefix = 'jbsw_5_fi_';
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
define('FI_API_URL','https://freedomindex.us/fi_api.php');

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

define('FI_BID',get_current_blog_id());

define('FI_SCORE_EVAL', false);
$fi_dir_core_dev = '';
$fi_dir_public_dev = '';
$fi_dir_core = FI_DIR . 'core/';
$fi_dir_public = FI_DIR . 'public/autoload/';

// Autoload global resources
fi_autoload_module($fi_dir_core,$fi_dir_core_dev);

//public is always loaded if plugin is active
fi_autoload_module($fi_dir_public,$fi_dir_public_dev);

// Autoload modules
if(is_admin() && FI_BID == 5) {
	// Load installer class
	require_once FI_DIR . 'admin/installer.php';
	register_activation_hook(__FILE__, function () {
		(new FI\Admin\Installer())->activate();
	});
	
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

	// Check if this is a Freedom Index page
	if (get_query_var('fi_view') || get_query_var('fi_gov') || get_query_var('fi_entity')) {
		$css_ver = filemtime(FI_DIR . 'assets/css/public.css');
		wp_enqueue_style('fi-public-css',FI_URL . 'assets/css/public.css',array('bn-bundle'),$css_ver);
		
		// Enqueue jQuery if not already enqueued
		wp_enqueue_script('jquery');
		
		// PDF contact delete/save is inlined in public-inline-js.php (only on legislator/account personalize/dashboard)
		
		// Conditionally enqueue Swiper.js for government page
		if (get_query_var('fi_entity') === 'government') {
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

function fi_autoload_module($autoload_dir,$override_dir='') {
	if(function_exists('fi_boot_trace')) {fi_boot_trace('START:'.$autoload_dir);}
	if (is_dir($autoload_dir)) {
		$files = glob($autoload_dir . '*.php') ?: [];
		usort($files, function ($a, $b) {
			return strnatcasecmp(basename($a), basename($b));
		});
		
		foreach ($files as $file) {
			$base = basename($file);
			// Skip dev/disabled modules
			if (stripos($base, 'dev') !== false || stripos($base, 'copy') !== false) continue;

			//if (function_exists('fi_boot_trace')) {
			//	fi_boot_trace('include:module:file', ['dir' => basename(rtrim($autoload_dir, "/\\")), 'file' => $base]);
			//}

			//Is there an override file? This enables us to only duplicate/fork specific files instead of the whole directory.
			if(in_array(FI_BID, FI_SITES_DEV) && $override_dir != '') {
				$override_file = str_replace($autoload_dir, $override_dir, $file);
				if(file_exists($override_file)) {
					$file = $override_file;
				}
			}
			include_once $file; // modules register hooks on load
		}
	}
	if(function_exists('fi_boot_trace')) {fi_boot_trace('END:'.$autoload_dir);}
}

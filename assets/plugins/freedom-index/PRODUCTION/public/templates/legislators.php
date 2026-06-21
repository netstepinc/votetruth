<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Legislators List Template
 * Displays a list of legislators for a specific government/session.
 * 
 * Available global variables:
 * - $fi_legislators: Array of legislator objects
 * - $fi_gov: Government code (e.g., 'US', 'TX', 'WI')
 * - $fi_session: Current session ID
 * - $fi_report_links: Array of report links for navigation (updates with session filter)
 */

// Get global variables set by rewrite handler
global $fi_legislators, $fi_gov, $fi_gov_name, $fi_session, $fi_reports, $fi_report_links, $fi_filter_description;

// SEO Meta Tags
$gov = $fi_gov ?? 'US';
$gov_slug = strtolower($gov ?? 'us');
$current_url = home_url('/' . $gov_slug . '/legislators/');
$gov_name = (string) ($fi_gov_name ?? 'Congress');
// Summary: for US only, breadcrumbs should say "Congress" but page title should use the adjective "Congressional".
$gov_name_adj = ($gov === 'US') ? 'Congressional' : $gov_name;
$page_title = $gov_name_adj . ($gov == 'US' ? '' : ' State') . ' Legislators';
$seo_page_title = $gov_name_adj . ($gov == 'US' ? '' : ' State') . ' Legislators | Freedom Index';

$description = 'Browse and search ' . $gov_name_adj . ' legislators. View voting records, scores, and contact information.';
if (!empty($fi_filter_description)) {
    $description = strip_tags($fi_filter_description) . ' | ' . $description;
}


fi_seo_tags([
    'title' => $seo_page_title,
    'description' => $description,
    'canonical' => $current_url,
    'robots' => 'index, follow',
    'og' => [
        'og:title' => $seo_page_title,
        'og:description' => $description,
        'og:url' => $current_url,
        'og:type' => 'website',
    ],
    'twitter' => [
        'twitter:card' => 'summary',
        'twitter:title' => $seo_page_title,
        'twitter:description' => $description,
    ],
]);

get_header();

// Get filter values from query vars
$filter_session = get_query_var('session') ?: ($_REQUEST['session'] ?? null);
$filter_party = get_query_var('party') ?: ($_REQUEST['party'] ?? '');
$filter_chamber = get_query_var('chamber') ?: ($_REQUEST['chamber'] ?? '');
$filter_search = urldecode(get_query_var('search') ?: ($_REQUEST['search'] ?? ''));
$filter_state = get_query_var('state') ?: ($_REQUEST['state'] ?? '');

// Use filter description from rewrite handler, or build it if not set
$description = $fi_filter_description ?? '';

//Debug output
//include_once(FI_PLUGIN_DIR . 'public/inc/legislators-debug.php');

$header_args = [
	'title' => $page_title,
	'gov' => $gov,
	'gov_name' => $gov_name,
	'description' => $description . ' | <span id="fi-results-count-placeholder">Loading...</span>',
	'breadcrumbs' => [
		['text' => $gov_name, 'url' => home_url('/' . strtolower($fi_gov) . '/')], 
		['text' => 'Legislators','url' => '','class' => 'fw-bold']
	],
	'id' => 'fi-legislators',
	'class' => 'fi-legislators-list',
	'session' => $filter_session ?: $fi_session,
	'party' => $filter_party,
	'chamber' => $filter_chamber,
	'search' => $filter_search,
	'filter_session' => $filter_session,
	'filter_party' => $filter_party,
	'filter_chamber' => $filter_chamber,
	'filter_search' => $filter_search,
	'breadcrumbs_args' => [
		'template_name' => 'legislators',
		'buttons' => [
			['text' => 'Legislators', 'url' => home_url($gov_slug . '/legislators/'),'class' => 'btn-outline-success d-none d-lg-block'],
			['text' => 'Votes', 'url' => home_url($gov_slug . '/votes/'),'class' => 'btn-outline-primary d-none d-lg-block'],
			['text' => 'Reports', 'url' => home_url($gov_slug . '/reports/'),'class' => 'btn-outline-primary d-none d-lg-block'],
		],
	],
];
fi_get_template('partials/template-header', $header_args);
?>
<!-- Legislators Grid View -->
<?php fi_get_template('partials/nav-reports'); ?>

<div id="fi-legislators-results">
	<!-- Loading state (shown initially, replaced by AJAX results) -->
	<div class="row" id="fi-legislators-loading">
		<div class="col-12">
			<div class="text-center py-5">
				<div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
					<span class="visually-hidden">Loading...</span>
				</div>
				<p class="mt-3 mb-0 text-muted">Loading legislators...</p>
			</div>
		</div>
	</div>
	<!-- Results will be inserted here via AJAX -->
</div>
<?php 
fi_get_template('partials/template-footer');
get_footer();
// Summary: footer should only render once.
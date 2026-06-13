<?php
/**
 * Legislators List Template
 * Displays a list of legislators for a specific government/session.
 * 
 * Available global variables:
 * - $fi_legislators: Array of legislator objects (server-side rendered)
 * - $fi_gov: Government code (e.g., 'US', 'TX', 'WI')
 * - $fi_session: Current session ID
 * - $fi_has_more: Boolean - more items available for Load More
 * - $fi_offset: Current offset for next page
 * - $fi_total_count: Total count of legislators
 */
if (!defined('ABSPATH')) exit;

// Get global variables set by rewrite handler
global $fi_legislators, $fi_gov, $fi_gov_name, $fi_session, $fi_reports, $fi_report_links, $fi_filter_description;
global $fi_has_more, $fi_offset, $fi_total_count;

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

// Build results count text
$results_count = $fi_total_count ?? count($fi_legislators ?? []);
$results_text = $results_count > 0 ? number_format($results_count) . ' legislator' . ($results_count !== 1 ? 's' : '') : 'No legislators found';

$header_args = [
	'title' => $page_title,
	'gov' => $gov,
	'gov_name' => $gov_name,
	'description' => $description . ' | <span id="fi-results-count">' . $results_text . '</span>',
	'breadcrumbs' => [
		['text' => $gov_name, 'url' => home_url('/' . strtolower($fi_gov) . '/')],
		['text' => 'Legislators','url' => '','class' => 'fw-bold']
	],
	'id' => 'fi-legislators',
	'class' => 'fi-legislators-list',
	'filter_enabled' => false, // Filters moved inline below
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
		'buttons' => [], // Remove nav buttons from legislators page
	],
];
fi_get_public_template('partials/template-header', $header_args);
?>
<!--// Legislators Grid View (Server-side rendered, HTMX-enhanced) -->

<div class="container-xl pt-3">
	<!-- Filter Bar (outside HTMX target - always preserved) -->
	<div id="fi-legislators-filters" class="mb-3">
		<?php echo fi_legislator_filters([
			'gov' => $gov,
			'session' => $filter_session ?: $fi_session,
			'party' => $filter_party,
			'chamber' => $filter_chamber,
			'search' => $filter_search,
			'form_label_class' => 'text-muted small mb-0',
		]); ?>
	</div>
	<!-- HTMX Loading Indicator (sibling of form for CSS targeting) -->
	<div class="fi-htmx-indicator d-none mb-3 text-center">
		<span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
		<span class="text-muted small ms-1">Loading legislators...</span>
	</div>
	
	<!-- HTMX Target - only grid content gets swapped -->
	<div id="fi-legislators-results">
		<?php 
		// Server-side render the grid
		fi_get_public_template('legislators-grid', [
			'fi_legislators' => $fi_legislators ?? [],
			'fi_gov' => $fi_gov ?? 'US',
			'fi_has_more' => $fi_has_more ?? false,
			'fi_offset' => $fi_offset ?? 0,
			'fi_limit' => $fi_limit ?? 24,
			'fi_total_count' => $fi_total_count ?? 0,
		]); 
		?>
	</div>
</div>
<?php 
fi_get_public_template('partials/template-footer');

// Include HTMX for refresh-less filtering (only on legislators page)
add_action('wp_footer', function() {
	?>
	<script>
		// Disable HTMX history cache for large pages (540 legislators exceeds storage)
		window.htmx = window.htmx || {};
		window.htmx.config = { historyCacheSize: 0 };
	</script>
	<script src="https://unpkg.com/htmx.org@1.9.12" integrity="sha384-ujb1lZYygJmzgSwoxRggbCHcjc0rB2XoQrxeTUQyRjrOnlCoYta87iKBWq3EsdM2" crossorigin="anonymous"></script>
	<style>
		/* HTMX indicator styles - show when form has htmx-request class */
		#fi-legislators-filters:has(form.htmx-request) + .fi-htmx-indicator {
			display: block !important;
		}
		/* Fallback: show indicator when results are loading */
		#fi-legislators-results.htmx-request ~ .fi-htmx-indicator {
			display: block !important;
		}
		/* Smooth transitions for HTMX swaps */
		#fi-legislators-results {
			transition: opacity 0.2s ease-in-out;
		}
		#fi-legislators-results.htmx-request {
			opacity: 0.6;
		}
	</style>
	<?php
}, 100);

get_footer();
// Summary: footer should only render once.
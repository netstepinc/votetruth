<?php
if (!defined('ABSPATH')) exit;

global $fi_legislators, $fi_gov, $fi_gov_name, $fi_session, $fi_total_count, $fi_filter_description, $fi_filters;

$gov          = $fi_gov;
$gov_slug     = strtolower($gov);
$gov_name     = $fi_gov_name;
$gov_name_adj = ($gov === 'US') ? 'Congressional' : $gov_name;
$page_title   = $gov_name_adj . ($gov === 'US' ? '' : ' State') . ' Legislators';

$results_count = $fi_total_count ?? count($fi_legislators ?? []);
$results_text  = $results_count > 0
	? number_format($results_count) . ' Found.'
	: 'No legislators found';

$description = !empty($fi_filter_description)
	? strip_tags($fi_filter_description) . ' | '
	: '';
$description .= 'Browse and search ' . $gov_name_adj . ' legislators. View voting records, scores, and contact information.';

fi_seo_tags([
	'title'       => $page_title . ' | Freedom Index',
	'description' => $description,
	'canonical'   => home_url('/' . $gov_slug . '/legislators/'),
	'robots'      => 'index, follow',
	'og'          => ['og:title' => $page_title, 'og:description' => $description, 'og:type' => 'website'],
	'twitter'     => ['twitter:card' => 'summary', 'twitter:title' => $page_title],
]);

get_header();

fi_get_template('template-header', [
	'title'           => $page_title,
	'gov'             => $gov,
	'gov_name'        => $gov_name,
	'description'     => ($fi_filter_description ? wp_kses_post($fi_filter_description) . ' | ' : '') . '<span id="fi-results-count">' . $results_text . '</span>',
	'breadcrumbs'     => [
		['text' => $gov_name, 'url' => home_url('/' . $gov_slug . '/')],
		['text' => 'Legislators'],
	],
	'id'              => 'fi-legislators',
	'class'           => 'fi-legislators-list',
	'filter_enabled'  => false,
	'breadcrumbs_args'=> ['template_name' => 'legislators', 'buttons' => []],
]);
?>
<div class="container-xl pt-3">
	<div id="fi-legislators-filters" class="mb-3">
		<?php echo fi_legislator_filters(['gov' => $gov]); ?>
	</div>
	<div class="fi-htmx-indicator d-none mb-3 text-center">
		<span class="spinner-border spinner-border-sm text-primary" role="status"></span>
		<span class="text-muted small ms-1">Loading...</span>
	</div>
	<div id="fi-legislators-results">
		<?php fi_get_template('legislators-grid', [
			'fi_legislators' => $fi_legislators ?? [],
			'fi_gov'         => $gov,
			'fi_total_count' => $fi_total_count ?? 0,
		]); ?>
	</div>
</div>
<?php
fi_get_template('template-footer');

add_action('wp_footer', function() {
	?>
	<script>
		window.htmx = window.htmx || {};
		window.htmx.config = { historyCacheSize: 0 };
	</script>
	<script src="https://unpkg.com/htmx.org@1.9.12" integrity="sha384-ujb1lZYygJmzgSwoxRggbCHcjc0rB2XoQrxeTUQyRjrOnlCoYta87iKBWq3EsdM2" crossorigin="anonymous"></script>
	<style>
		#fi-legislators-filters:has(form.htmx-request) + .fi-htmx-indicator { display: block !important; }
		#fi-legislators-results { transition: opacity 0.15s ease; }
		#fi-legislators-results.htmx-request { opacity: 0.5; }
	</style>
	<?php
}, 100);

get_footer();
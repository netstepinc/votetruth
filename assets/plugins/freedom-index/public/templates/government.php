<?php
if (!defined('ABSPATH')) exit;
/* Template Name: Gov Landing page
*/
if(!defined('FI_GOV_CARD_HEIGHT')){
	define('FI_GOV_CARD_HEIGHT', '480px');
}

// Get global variables set by rewrite handler
global $fi_gov, $fi_gov_name, $fi_current_session, $fi_sessions, $fi_reports, $fi_legislators;

$gov = $fi_gov ?? 'US'; //$fi_gov is upper case
$gov_slug = strtolower($gov);
$gov_name = $fi_gov_name ?? 'Congress';
$gov_name_adj = ($gov === 'US') ? 'Congressional' : $gov_name;
$current_session_id = $fi_current_session ?? null;
$reports = $fi_reports ?? [];
$legislators = $fi_legislators ?? [];
$has_house = fi_government_has_house($gov);
$page_title = $gov_name_adj . ($gov == 'US' ? '' : ' State') . ' Scorecard';
$seo_page_title = $page_title . ' | Freedom Index';

// SEO Meta Tags
$current_url = home_url('/' . $gov_slug . '/');
fi_seo_tags([
    'title' => $seo_page_title,
    'description' => 'Track how your ' . $gov_name . ' elected officials vote on constitutional issues. View legislator scores, vote history, and reports.',
    'canonical' => $current_url,
    'robots' => 'index, follow',
    'og' => [
        'og:title' => $seo_page_title,
        'og:description' => 'Track how your ' . $gov_name . ' elected officials vote on constitutional issues.',
        'og:url' => $current_url,
        'og:type' => 'website',
    ],
    'twitter' => [
        'twitter:card' => 'summary',
        'twitter:title' => $seo_page_title,
        'twitter:description' => 'Track how your ' . $gov_name . ' elected officials vote on constitutional issues.',
    ],
]);

get_header();

$header_args = [
	'gov' => $gov,
	'gov_name' => $gov_name,
	'title' => $page_title,
//	'pretext' => 'The Freedom Index Tracks how your elected officials vote',
//	'description' => 'The Freedom Index is a comprehensive tracking of how your elected officials vote on issues that matter to you. It is a measure of how well your elected officials represent your values and interests.',
	'id' => 'fi-government',
	'class' => 'fi-government-page',
	'breadcrumbs' => [
		['text' => $gov_name],
	],
	'breadcrumbs_args' => [
		'template_name' => 'government',
	],
	'filter_enabled' => true,
];
fi_get_template('partials/template-header', $header_args);
?>
<div class="row g-3 align-items-stretch mb-4">
	<div class="col-12 mb-1">
		<?php fi_get_template('partials/gov-averages', ['legislators' => $legislators, 'gov' => $gov]);?>
	</div>
	<div class="col-12 my-1">
		<h2 class="h3 mb-0">Best 20 Legislators</h2>
		<?php fi_get_template('partials/gov-leader-list', ['type' => 'best','gov' => $gov,'legislators' => $legislators]); ?>
	</div>
	<div class="col-12 my-1">
		<h2 class="h3 mb-0">Worst 20 Legislators</h2>
		<?php fi_get_template('partials/gov-leader-list', ['type' => 'worst','gov' => $gov,'legislators' => $legislators]); ?>
	</div>
	<div class="col-12 my-1 text-center">
		<a href="<?= home_url('/' . $gov_slug . '/legislators/'); ?>" class="btn fw-bold btn-success shadow fs-5 px-5">View All <?= $gov;?> Legislators</a>
	</div>
	<div class="col-12 mb-1">
		<div class="card border-danger rounded-4 shadow mt-4">
			<div class="card-body text-center fs-5 fw-bold">
				<?php echo fi_debt_clock(['gov' => $gov,'format' => 'row']);?>
			</div>
		</div>
	</div>
</div>

<div class="row g-4 mt-3">
	<div class="col-12 col-md-6 col-lg-4 pb-4">
		<?php fi_get_template('partials/gov-sessions', ['sessions' => $fi_sessions, 'gov' => $gov,'height' => FI_GOV_CARD_HEIGHT]); ?>
	</div>
	<div class="col-12 col-md-6 col-lg-4 pb-4">
		<?php fi_get_template('partials/gov-alerts', ['gov' => $gov,'height' => FI_GOV_CARD_HEIGHT]);	?>
	</div>

	<div class="col-12 col-lg-4 pb-4">
		<?php fi_get_template('partials/gov-votes-recent', ['gov' => $gov,'height' => FI_GOV_CARD_HEIGHT]); ?>
	</div>
</div>
<?php 
fi_get_template('partials/template-footer');
get_footer();
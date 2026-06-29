<?php
if (!defined('ABSPATH')) exit;

/**
 * Template Header Partial
 * 
 * Displays page header with filters, breadcrumbs, title, and description
 * 
 * @var string $title Page title
 * @var string $description Page description
 * @var array $breadcrumbs Breadcrumb items
 * @var string $gov Government code
 * @var string $gov_name Government name
 * @var string $filter_session Session filter value
 * @var string $filter_party Party filter value
 * @var string $filter_chamber Chamber filter value
 * @var string $filter_search Search filter value
 */

$defaults = array(
    'title' => '',
	'pretext' => '',
	'url_back' => '',
    'description' => '',
    'breadcrumbs' => [],
	'breadcrumbs_args' => [],
	'id' => 'fi-page',
	'class' => 'fi-page',
	'pdf_url' => '',
    'gov' => '',
    'gov_name' => '',
    'filter_session' => '',
    'filter_party' => '',
    'filter_chamber' => '',
    'filter_search' => '',
	'filter_enabled' => true,
);

$args = wp_parse_args($args ?? [], $defaults);

$gov = $args['gov'] ?? $fi_gov;
$gov_slug = strtolower($gov);
$title = $args['title'];
$pretext = $args['pretext'];
$description = $args['description'];
$breadcrumbs = $args['breadcrumbs'];
$breadcrumbs_args = $args['breadcrumbs_args'] ?? [];

// Get global variables for filter bar
global $fi_gov, $fi_session;

$account_pages = ['fi-account', 'fi-lists', 'fi-list-public', 'fi-scorecards', 'fi-notifications'];
$show_govnav   = !in_array($args['id'], $account_pages);

if ($show_govnav) {
	$all_govnav = [
		['text' => 'Legislators', 'url' => home_url($gov_slug . '/legislators/'), 'entity' => 'legislators'],
		['text' => 'Votes',       'url' => home_url($gov_slug . '/votes/'),       'entity' => 'votes'],
		['text' => 'Reports',     'url' => home_url($gov_slug . '/reports/'),     'entity' => 'reports'],
	];
	$current_entity = get_query_var('fi_entity') ?: '';
	$breadcrumbs_args['nav_links'] = array_values(
		array_filter($all_govnav, fn($b) => $b['entity'] !== $current_entity)
	);
}

//Legislator Search
/* MAYBE DELETE LATER: USE FILTER only on legislators page
if ($args['filter_enabled']):
	$gov = $args['gov'] ?? $fi_gov;
	$gov_name = $args['gov_name'];
	$filter_session = $args['filter_session'];
	$filter_party = $args['filter_party'];
	$filter_chamber = $args['filter_chamber'];
	$filter_search = $args['filter_search'];
?>
<div id="fi-gov-filters" class="container-fluid bg-primary px-0 px-lg-3">
	<div class="container-xl">
		<div class="row">
			<nav class="navbar navbar-expand navbar-dark justify-content-center py-0 d-lg-none">
				<ul class="navbar-nav mb-0">
					<li class="nav-item"><a href="#fi-filters-collapse" id="fi-filters-toggle" class="nav-link" role="button" data-bs-toggle="collapse" data-bs-target="#fi-filters-collapse" aria-expanded="false" aria-controls="fi-filters-collapse"><span class="fw-bold fi-filter-text">Search</span></a></li>
				</ul>
			</nav>

			<div class="col-12">
				<!-- Filter Bar (collapsed on mobile, always visible on desktop) -->
				<div class="pb-3 py-md-2 collapse d-lg-block show" id="fi-filters-collapse">
					<?php
					echo fi_legislator_filters([
						'gov' => $fi_gov ?? $gov,
						'session' => $filter_session ?: ($fi_session ?? ''),
						'party' => $filter_party,
						'chamber' => $filter_chamber,
						'search' => $filter_search,
						'form_label_class' => 'text-white small mb-0',
					]);
					?>
				</div>
			</div>			
		</div>
	</div>
</div>
<script>
(function() {
	var toggleBtn = document.getElementById('fi-filters-toggle');
	var filterCollapse = document.getElementById('fi-filters-collapse');
	var filterText = toggleBtn ? toggleBtn.querySelector('.fi-filter-text') : null;
	
	if (toggleBtn && filterCollapse && filterText) {
		filterCollapse.addEventListener('show.bs.collapse', function() {
			if (filterText) filterText.textContent = 'Close';
		});
		filterCollapse.addEventListener('hide.bs.collapse', function() {
			if (filterText) filterText.textContent = 'Search';
		});
	}
})();
</script>
<?php endif; */ ?>
<div id="content" class="bg-light ps-lg-4 pb-5">
	<div id="<?= $args['id'];?>" class="<?= $args['class'];?>">
		<div class="container-xl">
			<div class="row">
				<div class="col-12 pt-2">
					<?php echo fi_breadcrumbs($breadcrumbs, $breadcrumbs_args); ?>
				</div>
			</div>
			<!-- Legislator Search Results container-xl -->
			<div id="legislator-search-results"></div>

			<div class="row">
				<div class="col-12">
					<?php if (!empty($pretext) || !empty($url_back)) : ?>
					<div class="row py-0">
						<div class="col-12 py-0 my-0">
							<p class="fs-6 mb-0">
							<?php 
							if(!empty($url_back)){
								echo '<a href="' . esc_url($url_back) . '" class="btn btn-sm btn-outline-success py-1 me-3"><i class="fas fa-chevron-left me-2"></i>' . esc_html($url_back_text) . '</a>';
							}
							if(!empty($pretext)){
								echo '<span class="text-muted">' . esc_html($pretext) . '</span>';
							}
							?>
							</p>
						</div>
					</div>
					<?php endif; ?>

					<?php if (!empty($title)): ?>
					<div class="row text-start">
						<div class="col-12 col-lg-8 col-xl-9">
							<h1 class="mb-0"><?php echo wp_kses_post($title); ?></h1>
						</div>
						<div class="col-12 col-lg-4 col-xl-3 text-center text-lg-end pt-3">
<?php
// If not US: provide links to this state's US legislators because this page only shows the state legislators
/* Hide...probably delete later
if(!in_array($args['id'], $account_pages)){
	if ($gov !== 'US'){
		echo '<a href="' . home_url('/us/legislators/state/') . $gov_slug . '/" class="btn btn-outline-danger fw-bold py-2 w-100"><span class="d-none d-xl-inline">View U.S. </span>Congressional Legislators</a>';
	}elseif(!empty($args['pdf_url'])){
		echo '<a href="' . esc_url($args['pdf_url']) . '" class="btn btn-lg btn-danger d-none d-lg-inline ms-auto fw-bold py-2"><i class="fas fa-file-pdf me-2"></i>Download PDF</a>';
	}
}
*/
?>
						</div>
					</div>
					<?php endif; ?>

					<?php if (!empty($description)) : ?>
					<div class="row">
						<div class="col-12 m-0">
							<p id="fi-header-description" class="fs-7 text-muted my-2"><?php echo wp_kses_post($description); ?></p>
						</div>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<div class="container-xl">
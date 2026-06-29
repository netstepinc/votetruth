<?php
/**
 * Legislator Profile Header Partial
 *
 * Expected variables (from legislator.php controller):
 *   $legislator      array   Full legislator row with display fields
 *   $selected_session array   URL-selected session (for vote history context)
 *   $tag_scores      array   All career issue scores [{id,name,vote_count,score,grade,scored}]
 *   $base_url        string  Canonical legislator URL
 *   $legislator_id   int
 */

if (!defined('ABSPATH')) exit;

if (empty($legislator)) {
	echo '<div class="alert alert-danger m-3">Legislator data missing.</div>';
	return;
}



// All display fields pre-computed in fi_legislator_query() — read directly
$gov          = $legislator['gov'];
$display_name = $legislator['display_name'];
$freedom_score = $legislator['score'];
$freedom_grade = $legislator['score_grade'];

$image_html = fi_legislator_image(
	$legislator['image_id'],
	null,
	['size' => [200, 250], 'class' => 'img-fluid rounded-3 shadow', 'alt' => esc_attr($display_name)]
);

/*
Stack Legislator information with visual hierarchy:
	Thomas Massie
	U.S. Congress: Representative
	Kentucky District: 4th
	Republican
	{icon} massie.house.gov
*/
?>
<section class="fi-legislator-hero" aria-label="Legislator profile">
	<div class="container-xl py-3 py-md-4">
		<h1 class="h3 fs-4 fw-bold mb-1 d-sm-none"><?php echo esc_html($display_name); ?></h1>
		<div class="row align-items-center g-3">
			<div class="col-6 col-sm-2">
				<?php echo $image_html; ?>
			</div>
			<div class="col-6 col-sm-2 order-sm-last">
				<?= fi_score_display($freedom_score,'legislator'); ?>
			</div>
			<div class="col-12 col-sm-8">
				<h1 class="h3 fs-3 fw-bold mb-1 d-none d-sm-block"><?php echo esc_html($display_name); ?></h1>
				<div class="fs-7 text-muted"><?php echo esc_html($legislator['gov_name']) . ' ' . esc_html($legislator['chamber_title']); ?></div>
				<div class="fs-7 text-muted"><?php echo esc_html($legislator['district_name']); ?></div>
				<div class="fs-7 text-muted"><?php echo esc_html($legislator['party_name']); ?></div>
			</div>
		</div>
	</div>
</section>

<?php
// Action toolbar — below hero, above issue scores
$back_url = home_url('/' . $legislator['gov_slug'] . '/legislators/');
$toolbar_buttons = [
	['target' => '#fi-share-modal',       'icon' => 'bi-share',         'label' => 'Share This Page'],
	['target' => '#fi-contact-modal',     'icon' => 'bi-telephone',     'label' => 'Contact Info'],
	['target' => '#fi-lists-modal',       'icon' => 'bi-bookmark-plus', 'label' => 'Add to My Lists'],
	['target' => '#fi-personalize-modal', 'icon' => 'bi-person-vcard',  'label' => 'Personalize<span class="d-none d-md-inline"> PDFs</span>'],
	['target' => '#fi-print-modal',       'icon' => 'bi-printer',       'label' => 'Print Scorecard'],
];
?>
<div class="fi-action-toolbar bg-white border-bottom">
	<div class="container-xl py-2">

		<div class="row row-cols-2 row-cols-md-3 row-cols-lg-auto g-2 justify-content-lg-end">
			<div class="col order-first order-lg-first me-lg-auto">
				<a href="<?php echo esc_url($back_url); ?>"
					class="btn btn-sm btn-outline-secondary text-nowrap w-100">
					&larr; All Legislators
				</a>
			</div>
			<?php foreach ($toolbar_buttons as $b): ?>
			<div class="col">
				<button type="button"
					class="btn btn-sm btn-outline-primary w-100"
					data-bs-toggle="modal" data-bs-target="<?php echo esc_attr($b['target']); ?>">
					<i class="bi <?php echo esc_attr($b['icon']); ?> me-1" aria-hidden="true"></i><?php echo $b['label']; ?>
				</button>
			</div>
			<?php endforeach; ?>
		</div>

	</div>
</div>

<?php if (!empty($tag_scores)): ?>
<!-- ISSUE SCORES — horizontal ribbon, nowrap cells -->
<section class="bg-light border-bottom py-3" aria-label="Issue scores">
	<div class="container-xl">

		<h2 class="h6 text-muted mb-0">Issue Scores</h2>

		<div class="fi-scroll-rail mx-n3 px-3 py-1" id="fi-issue-rail" role="list" aria-label="Issue score filters">
			<?php foreach ($tag_scores as $tag): ?>
			<button type="button"
				class="btn fi-scroll-rail-item fi-issue-tile-filter d-inline-flex align-items-center p-0 bg-white border rounded text-start"
				data-tag-id="<?php echo (int) $tag['id']; ?>"
				title="View <?php echo esc_attr($tag['name']); ?> Votes"
				role="listitem">

				<span class="flex-shrink-0"><?php echo fi_score_display($tag['score'],'ribbon'); ?></span>

				<span class="lh-sm text-nowrap px-2 py-1">
					<span class="small fw-semibold d-block"><?php echo esc_html($tag['name']); ?></span>
					<span class="text-muted d-block xsmall">
						<?php echo (int) $tag['vote_count']; ?> vote<?php echo $tag['vote_count'] === 1 ? '' : 's'; ?>
					</span>
				</span>

			</button>
			<?php endforeach; ?>
		</div>

	</div>
</section>
<?php endif; ?>
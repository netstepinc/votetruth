<?php
/**
 * Legislator Profile Header Partial
 *
 * Expected variables (from legislator.php controller):
 *   $legislator      array   Full legislator row with display fields
 *   $current_session array   Active published session row
 *   $tag_scores      array   All career issue scores [{id,name,vote_count,score,grade,scored}]
 *   $base_url        string  Canonical legislator URL
 *   $legislator_id   int
 */

if (!defined('ABSPATH')) exit;

if (empty($legislator)) {
	echo '<div class="alert alert-danger m-3">Legislator data missing.</div>';
	return;
}

echo '<textarea style="width:100%;height:200px;">';print_r($legislator); echo '</textarea>';

$gov = $legislator['gov_name'];
$gov_name   = fi_gov_name($gov);

$display_name   = $legislator['display_name'] ?? '';
$party_name     = isset($legislator['party']) && $legislator['party'] != '' ? fi_party_name($legislator['party']) : '';
$state_name     = isset($legislator['state']) && $legislator['state'] != '' ? fi_state_name($legislator['state']) : '';
$chamber_label  = isset($legislator['chamber']) && $legislator['chamber'] != '' ? fi_chamber_label($gov, $legislator['chamber']) : '';
$chamber_title  = isset($legislator['chamber']) && $legislator['chamber'] != '' ? fi_chamber_title($gov, $legislator['chamber']) : '';
$district_name  = isset($legislator['district']) && $legislator['district'] != '' ? fi_district_name($legislator['district']) : '';
$session_name   = isset($legislator['session_name']) && $legislator['session_name'] != '' ? $legislator['session_name'] : '';
$freedom_score   = isset($legislator['score']) && $legislator['score'] != '' ? (int) $legislator['score'] : null;
$image_id       = (int) ($legislator['image_id'] ?? 0);
$website_url    = $legislator['website_url'] ?? 'meta www';

$image_html = fi_legislator_image(
	$image_id,
	$session_img_id ?: null,
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
		<h1 class="h3 fs-4 fw-bold mb-1 d-md-none"><?php echo esc_html($display_name); ?></h1>
		<div class="row align-items-center g-3">
			<div class="col-6 col-md-2">
				<?php echo $image_html; ?>
			</div>
			<div class="col-6 col-md-2 order-md-last">
				<?php if ($freedom_score !== null && $freedom_grade !== null): ?>
				<?php $grade_key = strtolower($freedom_grade); ?>
				<div class="fi-grade fi-bg-<?php echo esc_attr($grade_key); ?> p-3 text-center w-100 rounded-3" style="aspect-ratio:4/5">
					<div class="fs-1 text-white fw-8 lh-1"><?php echo esc_html($freedom_grade); ?></div>
					<div class="fs-3 text-white fw-6 lh-1"><?php echo (int) $freedom_score; ?>%</div>
					<div class="text-white">Freedom Score</div>
				</div>
				<?php endif; ?>
			</div>
			<div class="col-12 col-md-8">
				<h1 class="h3 fs-3 fw-bold mb-1 d-none d-md-block"><?php echo esc_html($display_name); ?></h1>
				<div class="fs-6 text-muted"><?php echo esc_html($gov_name); ?></div>
				<div class="fs-6 text-muted"><?php echo esc_html($chamber_label); ?></div>
				<div class="fs-6 text-muted"><?php echo esc_html($state_name); ?></div>
				<div class="fs-6 text-muted"><?php echo esc_html($district_name); ?></div>
				<div class="fs-6 text-muted"><?php echo esc_html($party_name); ?></div>
				<div class="fs-6 text-muted"><?php echo esc_html($website_url); ?></div>
			</div>

		</div>
	</div>
</section>

<?php
// Action toolbar — below hero, above issue scores
$back_url = home_url('/' . strtolower($gov ?: 'us') . '/legislators/');
$toolbar_buttons = [
	['target' => '#fi-share-modal',       'icon' => 'bi-share',         'label' => 'Share This Page'],
	['target' => '#fi-contact-modal',     'icon' => 'bi-telephone',     'label' => 'Contact Info'],
	['target' => '#fi-lists-modal',       'icon' => 'bi-bookmark-plus', 'label' => 'Add to My Lists'],
	['target' => '#fi-personalize-modal', 'icon' => 'bi-person-vcard',  'label' => 'Personalize PDFs'],
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
					<i class="bi <?php echo esc_attr($b['icon']); ?> me-1" aria-hidden="true"></i><?php echo esc_html($b['label']); ?>
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

				<span class="lh-sm text-nowrap px-2 py-2">
					<span class="small fw-semibold d-block"><?php echo esc_html($tag['name']); ?></span>
					<span class="text-muted d-block small">
						<?php echo (int) $tag['vote_count']; ?> vote<?php echo $tag['vote_count'] === 1 ? '' : 's'; ?>
					</span>
				</span>

			</button>
			<?php endforeach; ?>
		</div>

	</div>
</section>
<?php endif; ?>
<?php
/**
 * Legislator Profile Header Partial
 *
 * Expected variables (from legislator.php controller):
 *   $legislator      array   Full legislator row with display fields
 *   $current_session array   Active published session row
 *   $tag_scores      array   Top-8 career issue scores [{id,name,vote_count,score,grade,scored}]
 *   $base_url        string  Canonical legislator URL
 *   $legislator_id   int
 */

if (!defined('ABSPATH')) exit;

if (empty($legislator)) {
	echo '<div class="alert alert-danger m-3">Legislator data missing.</div>';
	return;
}

$display_name   = $legislator['display_name'] ?? '';
$party_name     = $legislator['party_name']   ?? '';
$state_name     = $current_session['state_name']    ?? ($legislator['state_name']    ?? '');
$chamber_label  = $current_session['chamber_label'] ?? ($legislator['chamber_label'] ?? '');
$chamber_title  = $current_session['chamber_title'] ?? ($legislator['chamber_title'] ?? '');
$district_name  = $current_session['district_name'] ?? '';
$gov_name       = $current_session['gov_name']      ?? ($legislator['gov_name']      ?? '');
$session_name   = $current_session['session_name']  ?? '';
$career_score   = $legislator['score'];
$career_grade   = ($career_score !== null && function_exists('fi_score_calculate_grade'))
	? fi_score_calculate_grade((int) $career_score)
	: null;
$image_id       = (int) ($legislator['image_id'] ?? 0);
$session_img_id = (int) ($current_session['image_id'] ?? 0);

$image_html = fi_legislator_image(
	$image_id,
	$session_img_id ?: null,
	['size' => [120, 150], 'class' => 'img-fluid rounded-3 shadow', 'alt' => esc_attr($display_name)]
);

// Subtitle: Chamber · Party · State / District
$subtitle_parts = array_filter([$chamber_label ? $chamber_title ?: $chamber_label : '', $party_name, $state_name ?: $gov_name]);
$district_str   = $district_name ? ', ' . $district_name : '';
?>

<!-- =====================================================================
     HERO
     ===================================================================== -->
<section class="fi-legislator-hero bg-primary text-white" aria-label="Legislator profile">
	<div class="container py-3 py-md-4">

		<div class="row align-items-center g-3">

			<!-- Photo -->
			<div class="col-auto">
				<?php echo $image_html; ?>
			</div>

			<!-- Identity + scores -->
			<div class="col">
				<h1 class="h3 fw-bold mb-1"><?php echo esc_html($display_name); ?></h1>

				<p class="mb-2 opacity-75 small">
					<?php echo esc_html(implode(' &bull; ', $subtitle_parts)); ?>
					<?php echo esc_html($district_str); ?>
				</p>

			<?php if ($career_score !== null && $career_grade !== null): ?>
			<div class="d-flex align-items-center gap-3 mt-1">
				<!-- Big grade badge — same format as the legislator card, scaled up -->
				<?php $grade_key = strtolower($career_grade); ?>
				<div class="fi-grade fi-grade--<?php echo esc_attr($grade_key); ?>"
				     style="min-width:72px; padding:10px 14px; border-radius:8px;">
					<span class="fi-gl" style="font-size:2.2rem;"><?php echo esc_html($career_grade); ?></span>
					<span class="fi-gs" style="font-size:1.2rem; margin-top:4px;"><?php echo (int) $career_score; ?>%</span>
				</div>
				<div class="lh-sm text-white">
					<div class="fw-bold fs-5">Freedom Score</div>
					<div class="opacity-75 small">Career</div>
				</div>
			</div>
			<?php endif; ?>
			</div><!-- /col identity -->

		</div><!-- /row -->
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
	<div class="container py-2">

		<!-- Desktop: back link left, buttons right -->
		<div class="d-none d-md-flex align-items-center justify-content-between gap-2">
			<a href="<?php echo esc_url($back_url); ?>" class="btn btn-sm btn-outline-secondary text-nowrap">
				&larr; All Legislators
			</a>
			<div class="d-flex flex-wrap gap-2 justify-content-end">
				<?php foreach ($toolbar_buttons as $b): ?>
				<button type="button" class="btn btn-sm btn-outline-primary"
					data-bs-toggle="modal" data-bs-target="<?php echo esc_attr($b['target']); ?>">
					<i class="bi <?php echo esc_attr($b['icon']); ?> me-1" aria-hidden="true"></i><?php echo esc_html($b['label']); ?>
				</button>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Mobile: buttons stacked full-width, back link at bottom -->
		<div class="d-flex d-md-none flex-column gap-2">
			<?php foreach ($toolbar_buttons as $b): ?>
			<button type="button" class="btn btn-sm btn-outline-primary w-100"
				data-bs-toggle="modal" data-bs-target="<?php echo esc_attr($b['target']); ?>">
				<i class="bi <?php echo esc_attr($b['icon']); ?> me-1" aria-hidden="true"></i><?php echo esc_html($b['label']); ?>
			</button>
			<?php endforeach; ?>
			<a href="<?php echo esc_url($back_url); ?>" class="btn btn-sm btn-outline-secondary w-100">
				&larr; All Legislators
			</a>
		</div>

	</div>
</div>

<?php if (!empty($tag_scores)): ?>
<!-- =====================================================================
     ISSUE SCORES — hidden when no tags exist
     ===================================================================== -->
<section class="fi-issue-scores bg-light border-bottom py-3" aria-label="Issue scores">
	<div class="container">

		<h2 class="h6 text-muted mb-2">Issue Scores</h2>

		<div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-xl-8 g-2">

			<?php foreach ($tag_scores as $tag): ?>
			<div class="col">
				<div class="fi-issue-tile d-flex align-items-center gap-2 bg-white border rounded p-2 h-100">

					<?php echo fi_score_badge($tag['score']); ?>

					<div class="lh-sm min-w-0">
						<div class="small fw-semibold text-truncate" title="<?php echo esc_attr($tag['name']); ?>">
							<?php echo esc_html($tag['name']); ?>
						</div>
						<div class="text-muted" style="font-size:.7rem;">
							<?php echo (int) $tag['vote_count']; ?> vote<?php echo $tag['vote_count'] === 1 ? '' : 's'; ?>
						</div>
					</div>

				</div>
			</div>
			<?php endforeach; ?>

		</div>
	</div>
</section>
<?php endif; ?>

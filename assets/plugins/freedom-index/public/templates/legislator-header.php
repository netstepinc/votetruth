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
$freedom_score   = $legislator['score'];
$freedom_grade   = ($freedom_score !== null && function_exists('fi_score_calculate_grade'))
	? fi_score_calculate_grade((int) $freedom_score)
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

			<?php if ($freedom_score !== null && $freedom_grade !== null): ?>
			<div class="d-flex align-items-center gap-3 mt-1">
				<!-- Big grade badge — same format as the legislator card, scaled up -->
				<?php $grade_key = strtolower($freedom_grade); ?>
				<div class="fi-grade fi-grade--<?php echo esc_attr($grade_key); ?>"
				     style="min-width:72px; padding:10px 14px; border-radius:8px;">
					<span class="fi-gl" style="font-size:2.2rem;"><?php echo esc_html($freedom_grade); ?></span>
					<span class="fi-gs" style="font-size:1.2rem; margin-top:4px;"><?php echo (int) $freedom_score; ?>%</span>
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

		<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-auto g-2 justify-content-lg-end">
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
<!-- =====================================================================
     ISSUE SCORES — horizontal ribbon, nowrap cells
     ===================================================================== -->
<section class="bg-light border-bottom py-3" aria-label="Issue scores">
	<div class="container">

		<h2 class="h6 text-muted mb-0">Issue Scores</h2>

		<div class="fi-scroll-rail mx-n3 px-3 py-1" id="fi-issue-rail" role="list" aria-label="Issue score filters">
			<?php foreach ($tag_scores as $tag): ?>
			<button type="button"
				class="btn fi-scroll-rail-item fi-issue-tile-filter d-inline-flex align-items-center gap-2 bg-white border rounded p-2 text-start"
				data-tag-id="<?php echo (int) $tag['id']; ?>"
				title="View <?php echo esc_attr($tag['name']); ?> Votes"
				role="listitem">

				<span class="flex-shrink-0"><?php echo fi_score_badge($tag['score']); ?></span>

				<span class="lh-sm text-nowrap">
					<span class="small fw-semibold d-block"><?php echo esc_html($tag['name']); ?></span>
					<span class="text-muted d-block" style="font-size:.7rem;">
						<?php echo (int) $tag['vote_count']; ?> vote<?php echo $tag['vote_count'] === 1 ? '' : 's'; ?>
					</span>
				</span>

			</button>
			<?php endforeach; ?>
		</div>

	</div>
</section>
<?php endif; ?>
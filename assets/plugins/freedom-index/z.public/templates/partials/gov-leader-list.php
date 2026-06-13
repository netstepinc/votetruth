<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$type = $args['type'] ?? 'worst';
$legislators = $args['legislators'] ?? [];
$gov = $args['gov'] ?? 'US';

// Get chamber info for labels
$chambers = fi_chamber_info($gov);

// Get top 10 and bottom 10 CURRENT legislators ordered by freedom score
$leaders_best = [];
$leaders_worst = [];

if (!empty($legislators)) {
	// Filter current legislators to only those with freedom scores
	$scored_legislators = array_filter($legislators, function($leg) {
		$freedom_score = $leg->freedom_score ?? null;
		return $freedom_score !== null && $freedom_score !== '';
	});
	
	// Sort by freedom score (descending)
	usort($scored_legislators, function($a, $b) {
		$score_a = (float) ($a->freedom_score ?? 0);
		$score_b = (float) ($b->freedom_score ?? 0);
		return $score_b <=> $score_a;
	});
	
	// Get top 10 (highest freedom scores)
	$leaders_best = array_slice($scored_legislators, 0, 20);
	
	// Get bottom 10 (lowest freedom scores)
	$leaders_worst = array_slice($scored_legislators, -20);
	$leaders_worst = array_reverse($leaders_worst); // Reverse so lowest is first
}

if ($type == 'worst') {
	$leaders = $leaders_worst;
	$title = 'Losers';
	$badge_class = 'danger';
} else {
	$leaders = $leaders_best;
	$title = 'Leaders';
	$badge_class = 'success';
}

$parties = fi_parties();
?>
<!-- <div class="text-center fs-4 mb-0 text-muted"><?php echo esc_html($title); ?></div> -->
<!-- Horizontal Scroll Container -->
<div class="fi-leader-list-scroll" data-scrollbar>
	<?php if (!empty($leaders)): ?>
		<?php foreach ($leaders as $leg): 
			$chamber = $leg->chamber ?? '';
			$chamber_label = '';
			if ($chamber && isset($chambers[$chamber])) {
				$chamber_label = $chambers[$chamber]['name'] ?? '';
			}
			//CHAMBERFLAG
			if (empty($chamber_label) && $chamber === 'H') {
				$chamber_label = 'Representative';
			} elseif (empty($chamber_label) && $chamber === 'S') {
				$chamber_label = 'Senator';
			}
			$party_name = null;
			if (isset($leg->party) && $leg->party != ''){
				$part = strtolower($leg->party);
				if (isset($parties[$part]) && $parties[$part] != ''){
					$party_name = $parties[$part]['name'];
				}
			}
		?>
			<div class="fi-leader-card">
				<a href="<?php echo esc_url(fi_get_legislator_url($leg->id ?? 0)); ?>">
				<div class="card bg-white shadow-sm rounded-3 h-100">
					<div class="card-body p-2">
						<div class="row g-2 align-items-center">
							<div class="col-4 p-0">
								<?php 
								if (!empty($leg->image_id)){
									echo fi_legislator_image($leg->image_id, null, ['size' => [80, 80], 'crop' => [0.5, 0], 'retina' => false, 'class' => 'rounded-circle', 'style' => 'width: 50px; height: 50px; object-fit: cover;']);
								} else {
									echo '<div class="rounded-circle bg-secondary" style="width: 50px; height: 50px;"></div>';
								}
								?>
							</div>
							<div class="col-8">
								<?php if ($party_name): ?>
									<div class="small text-muted mb-1"><?= $party_name; ?></div>
								<?php endif; ?>
								<?php if ($chamber_label): ?>
									<div class="small text-muted mb-1"><?php echo esc_html($chamber_label); ?></div>
								<?php endif; ?>
								<div class="mb-1 text-decoration-none fw-bold fs-6">
									<?php echo esc_html($leg->display_name ?? 'Unknown'); ?>
								</div>
								<div>
									<span class="badge bg-<?php echo esc_attr($badge_class); ?>"><?php echo esc_html($leg->freedom_score ?? 'N/A'); ?>% Freedom Score</span>
								</div>
							</div>
						</div>
					</div>
				</div>
				</a>
			</div>
		<?php endforeach; ?>
	<?php else:  /* HIDE THIS SECTION IF NO LEADERS ARE AVAILABLE ?>
		<div class="fi-leader-card">
			<div class="card bg-white shadow-sm rounded-3 h-100">
				<div class="card-body p-3 d-flex align-items-center justify-content-center" style="min-height: 150px;">
					<p class="text-muted mb-0">No scores available</p>
				</div>
			</div>
		</div>
	<?php */ endif; ?>
</div>

<?php fi_scrollbar_css(); ?>

<style>
/* Horizontal Scroll Container */
.fi-leader-list-scroll {
	display: flex;
	gap: 16px;
	overflow-x: auto;
	overflow-y: hidden;
	padding: 10px 0;
	scroll-behavior: smooth;
	-webkit-overflow-scrolling: touch;
	scrollbar-width: thin;
}

/* Leader Cards */
.fi-leader-card {
	flex: 0 0 300px;
	max-width: 300px;
}

.fi-leader-card .card {
	min-height: 100px;
}

/* Mobile adjustments */
@media (max-width: 575.98px) {
	.fi-leader-card {
		flex: 0 0 275px;
		max-width: 275px;
	}
}
</style>

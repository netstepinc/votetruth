<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$type = $args['type'] ?? 'worst';
$legislators = $args['legislators'] ?? [];
$gov = $args['gov'] ?? 'US';

// Get top 10 and bottom 10 CURRENT legislators ordered by freedom score
$leaders_best = [];
$leaders_worst = [];

if (!empty($legislators)) {
	// Filter current legislators to only those with freedom scores
	$scored_legislators = array_filter($legislators, function($leg) {
		$score = $leg['score'] ?? null;
		return $score !== null && $score !== '';
	});

	// Sort by score (descending)
	usort($scored_legislators, function($a, $b) {
		$score_a = (float) ($a['score'] ?? 0);
		$score_b = (float) ($b['score'] ?? 0);
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
} else {
	$leaders = $leaders_best;
}
?>
<div class="fi-leader-list-scroll" data-scrollbar>
	<?php foreach ($leaders as $leg): ?>
		<div class="fi-leader-card">
			<?php fi_get_public_template('legislators-card', ['legislator' => $leg, 'gov' => $gov]); ?>
		</div>
	<?php endforeach; ?>
</div>

<?php fi_scrollbar_css(); ?>

<style>
.fi-leader-list-scroll {
	display: flex;
	gap: 12px;
	overflow-x: auto;
	overflow-y: hidden;
	padding: 8px 0;
	scroll-behavior: smooth;
	-webkit-overflow-scrolling: touch;
	scrollbar-width: thin;
}
.fi-leader-card {
	flex: 0 0 280px;
	max-width: 280px;
}
@media (max-width: 575.98px) {
	.fi-leader-card {
		flex: 0 0 240px;
		max-width: 240px;
	}
}
</style>

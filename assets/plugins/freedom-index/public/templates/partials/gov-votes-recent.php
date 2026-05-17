<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$chamber = $args['chamber'] ?? '';
$gov = $args['gov'] ?? '';
$votes = [];
$height = $args['height'] ?? FI_GOV_CARD_HEIGHT;
$chamber_info = fi_chamber_info($gov);

// Build title based on whether chamber is specified
if (!empty($chamber) && isset($chamber_info[$chamber]['name'])) {
	$title = 'Most Recent ' . $chamber_info[$chamber]['name'] . ' Votes';
} else {
	$title = 'Most Recent Votes';
}


$vote_args = [
	'gov' => $gov,
	'status' => 'publish',
	'orderby' => 'date_voted',
	'order' => 'DESC',
	'per_page' => 10,
];
if(!empty($chamber)){
	$vote_args['chamber'] = $chamber;
}

$votes = fi_votes_get($vote_args);

?>
<div class="card rounded-4 shadow h-100 fi-gov-votes-recent">
	<div class="card-header rounded-top-4 bg-white">
		<h2 class="card-title fs-4 mb-0 text-muted text-center"><?php echo esc_html($title); ?></h2>	
	</div>
	<div class="card-body">
		<?php if (!empty($votes)): ?>
			<div class="list-group list-group-flush" style="max-height:<?php echo esc_attr($height); ?>; overflow-y: auto; overscroll-behavior: contain; scrollbar-width: thin;" data-scrollbar>
				<?php foreach ($votes as $vote): 
					$vote_date = $vote->date_voted ?? '';
					$vote_date_formatted = $vote_date ? date('F j, Y', strtotime($vote_date)) : '';
					$vote_meta = fi_vote_decode_meta($vote);
					$vote_text = $vote_meta['description_short'];
				?>
					<div class="list-group-item px-0 py-2 border-bottom">
						<div class="d-flex justify-content-between align-items-start">
							<div class="flex-grow-1">
								<h5 class="mb-1">
									<a href="<?php echo esc_url(fi_url_vote(strtolower($gov), $vote->id ?? 0)); ?>" class="text-decoration-none">
										<?php echo esc_html($vote->title ?? 'Untitled Vote'); ?>
									</a>
								</h5>
								<?php if ($vote_text): ?>
									<div class="card-text small"><?php echo wp_kses_post(wpautop($vote_text)); ?></div>
								<?php endif; ?>
								<?php if ($vote_date_formatted): ?>
									<small class="text-muted">
										<?php
											$chamber_label = '';
											if (!empty($vote->chamber)) {
												$chamber_info = fi_chamber_info($gov);
												$chamber_label = $chamber_info[$vote->chamber]['name'] ?? strtoupper($vote->chamber);
											}
											$bill_title = '';
											if (!empty($vote_meta['bill_number'])) {
												$bill_title = $vote_meta['bill_number'];
											} elseif (!empty($vote->bill_title)) {
												$bill_title = $vote->bill_title;
											} elseif (!empty($vote_meta['post_title'])) {
												$bill_title = $vote_meta['post_title'];
											}
											$parts = [];
											if (!empty($chamber_label)) {
												$parts[] = $chamber_label;
											}
											if (!empty($vote_date_formatted)) {
												$parts[] = $vote_date_formatted;
											}
											echo esc_html(implode(' | ', $parts));
										?>
									</small>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php fi_scrollbar_css(); ?>
		<?php else: ?>
			<p class="text-muted mb-0">No <?php echo esc_html($title); ?> votes tracked yet</p>
		<?php endif; ?>
	</div>
	<div class="card-footer p-0">
		<a href="<?php echo esc_url( home_url( strtolower($gov) . '/votes/' ) ); ?>" rel="noopener" class="btn btn-secondary w-100 fs-7 rounded-0 rounded-bottom-4">
			View All <?php echo fi_gov_name($gov); ?> Votes
		</a>
	</div>
</div>
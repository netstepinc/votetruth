<?php
if (!defined('ABSPATH')) exit;

/**
 * Legislator Card for Report Votes
 * Displays legislator info with report-specific score and vote history
 * 
 * @var array $args {
 *     @type object $legislator Legislator object
 *     @type int $report_score Report-specific score (0-100 or null)
 *     @type array $votes Array of vote objects with legislator's cast
 *     @type string $gov Government code
 * }
 */

$legislator = $args['legislator'] ?? null;
$report_score = $args['report_score'] ?? null;
$votes = $args['votes'] ?? [];
$gov = $args['gov'] ?? 'US';
$vote_start = $args['vote_start'] ?? 1;

if (!$legislator) return;

$name = $legislator->display_name ?? ($legislator->first_name . ' ' . $legislator->last_name);
$legislator_url = fi_get_legislator_url($legislator->id ?? 0);
$image_tag = fi_legislator_image($legislator->image_id ?? null, $legislator->session_image_id ?? null, [
    'alt' => $name,
    'class' => 'img-fluid w-100 h-100',
    'size' => [75, 75]
]);

$party_slug = isset($legislator->party) ? strtolower((string) $legislator->party) : '';
$party_name = $legislator->party_name ?? ($party_slug ? fi_party_name($party_slug) : '');
$state = $legislator->state ?? '';
$district = isset($legislator->district_info) && $legislator->district_info
	? ($legislator->district_info->name_short ?? $legislator->district_info->name ?? '')
	: ($legislator->district ?? '');
$is_federal = ($gov === 'US');

$chamber = $legislator->chamber ?? '';
$chamber_name = '';
if($chamber){
	$chamber_name = FI_CHAMBERS[$gov][$chamber]['title'];
}
?>

<div class="col-12 col-md-6 col-lg-4 col-xl-3 fi-legislator-card-vote mb-4" 
     data-leg-id="<?php echo esc_attr($legislator->id); ?>"
     data-name="<?php echo esc_attr(strtolower($name)); ?>"
     data-state="<?php echo esc_attr(strtoupper($state)); ?>"
     data-party="<?php echo esc_attr($party_slug); ?>">
    <div class="card h-100 shadow-sm rounded-4">
        <!-- Legislator Header -->
        <div class="card-header bg-white rounded-top-4 px-3 py-2">
            <div class="row g-2 align-items-center">
                <div class="col-auto">
                    <?php if ($image_tag): ?>
                        <div style="width: 75px; height: 75px; overflow: hidden; border-radius: 8px;">
                            <a href="<?php echo esc_url($legislator_url); ?>" class="text-decoration-none"><?php echo $image_tag; ?></a>
                        </div>
                    <?php else: ?>
                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <a href="<?php echo esc_url($legislator_url); ?>" class="text-decoration-none"><small class="text-muted text-center">No<br>Photo</small></a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col">
                    <h5 class="mb-0">
                        <a href="<?php echo esc_url($legislator_url); ?>" class="text-decoration-none fs-6">
                            <?php echo esc_html($name); ?>
                        </a>
                    </h5>
                    <div class="small text-muted">
                        <?php 
						if ($party_name){
							echo '<div>' . esc_html($party_name) . '</div>';
						}
						if($chamber_name){
							echo '<div>' . esc_html($chamber_name) . '</div>';
						}
						if ($is_federal && $state){
							$state_full = $legislator->state_name ?? strtoupper($state);
							echo '<div>' . esc_html($state_full) . '</div>';
						}
						if ($district){
							echo '<div>Dist. ' . esc_html($district) . '</div>';
						}
						?>
                    </div>
                </div>
                <?php //if ($report_score !== null):?>
                <div class="col-auto text-center">
                    <div class="fs-3 lh-1 fw-bold"><?php echo esc_html($report_score !== null ? (int) $report_score . '%' : 'N/A'); ?></div>
                    <div class="small text-muted">Report<br>Score</div>
                </div>
                <?php //endif; ?>
            </div>
        </div>
        
         <div class="card-body px-3 py-2">
            <?php if (!empty($votes)): $vote_count = count($votes); ?>
			<div class="vote-list-compact">
				<?php
				$v = 0;
				foreach ($votes as $vote_data):
					$vote_number = $vote_start + $v;
					$v++;
					$vote = $vote_data['vote'] ?? null;
					if (!$vote) continue;

					// Use pre-calculated vote_format from report-legislators.php if available
					// Otherwise, calculate it (fallback for backwards compatibility)
					if (isset($vote_data['vote_format']) && is_array($vote_data['vote_format'])) {
						$vote_format = $vote_data['vote_format'];
					} else {
						// Fallback: calculate vote format
						$cast = fi_rollcall_cast_normalize((string) ($vote_data['cast'] ?? ''));
						
						$vote_format = fi_vote_format([
							'cast' => $cast,
							'constitutional' => $vote->constitutional ?? '',
							'format' => 'full'
						]);
					}
					
					// Format date
					$date_formatted = '';
					if (!empty($vote->date_voted)) {
						$timestamp = strtotime($vote->date_voted);
						if ($timestamp) {
							$date_formatted = date('M j, Y', $timestamp);
						} else {
							$date_formatted = $vote->date_voted;
						}
					}

					$vote_title = $vote_number . '. ' . ($vote->title ?? $vote->bill_key ?? 'Untitled Vote');
				?>
					<div class="<?php echo ($v < $vote_count) ? ' pb-1 mb-1 border-bottom' : ''; ?>">
						<div class="small fw-bold mb-1"><?php echo esc_html($vote_title); ?></div>
						<div class="row g-2 small">
							<div class="col-6 text-center">
								<div class="fs-7 <?php echo esc_attr($vote_format['vote_class']); ?>">
									<i class="<?php echo esc_attr($vote_format['vote_class_icon']); ?> me-1"></i>
									<?php echo esc_html($vote_format['vote_text']); ?>
								</div>
								<div class="text-muted small">Constitutional</div>
							</div>
							<div class="col-6 text-center">
								<div class="fs-7 <?php echo esc_attr($vote_format['cast_class']); ?>">
									<i class="<?php echo esc_attr($vote_format['cast_class_icon']); ?> me-1"></i>
									<?php echo esc_html($vote_format['cast_text']); ?>
								</div>
								<div class="text-muted small">Vote Cast</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
            <?php endif; ?>
        </div>
        
        <!-- Footer with Link -->
        <div class="card-footer bg-transparent border-top p-0 rounded-bottom-4">
            <a href="<?php echo esc_url($legislator_url); ?>" class="btn btn-sm btn-primary w-100 rounded-bottom-4">
                View Profile
            </a>
        </div>
    </div>
</div>


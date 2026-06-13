<?php
/**
 * Freedom Index by Sam Mittelstaedt <smittelstaedt@jbs.org>
 *
 * AJAX handlers: vote detail modal
 */

namespace FI\Public {
	if ( ! defined( 'ABSPATH' ) ) { exit; }

	trait AjaxHandlersVoteDetailTrait {

		/**
		 * Handle vote detail modal content
		 */
		public function handle_vote_detail() {
			check_ajax_referer('fi_ajax_nonce', 'nonce');
			
			$vote_id = intval($_POST['vote_id'] ?? 0);
			$legislator_id = intval($_POST['legislator_id'] ?? 0);
			
			if (!$vote_id || !$legislator_id) {
				wp_send_json_error('Invalid parameters');
			}
			
			$vote = fi_vote_get($vote_id);
			if (!$vote) {
				wp_send_json_error('Vote not found');
			}
			
			// Get legislator's vote
			$rollcall = fi_rollcall_get($vote_id, $legislator_id);
			$cast = $rollcall->cast ?? 'X';
			
			// Format vote
			$vote_format = fi_vote_format([
				'cast' => $cast,
				'constitutional' => $vote->constitutional ?? '',
				'format' => 'full'
			]);
			
			$meta = fi_vote_decode_meta($vote);
			
			// Build HTML
			ob_start();
			?>
			<div class="fi-vote-detail">
				<h5><?php echo esc_html($vote->title ?? 'Untitled Vote'); ?></h5>
				
				<?php if (!empty($vote->bill_number)): ?>
					<p class="text-muted mb-2">
						<strong>Bill:</strong> <?php echo esc_html($vote->bill_number); ?>
					</p>
				<?php endif; ?>
				
				<?php if (!empty($vote->date_voted)): ?>
					<p class="text-muted mb-2">
						<strong>Date:</strong> <?php echo esc_html(date('F j, Y', strtotime($vote->date_voted))); ?>
					</p>
				<?php endif; ?>
				
				<div class="mb-3">
					<strong>Constitutional Position:</strong> 
					<?php 
					$constitutional_format = fi_vote_format([
						'constitutional' => $vote->constitutional ?? '',
						'format' => 'full'
					]);
					// Constitutional position displays in default color (no badge color)
					echo '<span class="' . esc_attr($constitutional_format['vote_class']) . '">';
					echo '<i class="' . esc_attr($constitutional_format['vote_class_icon']) . ' me-1"></i>';
					echo esc_html($constitutional_format['vote_text']);
					echo '</span>';
					?>
				</div>
				
				<div class="mb-3">
					<strong>Vote Cast:</strong> 
					<span class="<?php echo esc_attr($vote_format['cast_class']); ?>">
						<i class="<?php echo esc_attr($vote_format['cast_class_icon']); ?> me-1"></i>
						<?php echo esc_html($vote_format['cast_text']); ?>
					</span>
				</div>
				
				<?php if (!empty($meta['cost'])): ?>
					<div class="mb-3">
						<strong>Estimated Cost Per Household:</strong> 
						<span class="text-<?php echo (strpos($meta['cost'], '+') === 0) ? 'success' : 'danger'; ?>">
							$<?php echo esc_html(str_replace('+', '', $meta['cost'])); ?>
						</span>
					</div>
				<?php endif; ?>
				
				<?php 
				$description_short = fi_vote_get_description($meta, 'scorecard');
				if (!empty($description_short)): ?>
					<div class="mb-3">
						<h6>Effect on You</h6>
						<?php echo wp_kses_post(wpautop($description_short)); ?>
					</div>
				<?php endif; ?>
				
				<?php if (!empty($meta['description_excerpt'])): ?>
					<div class="mb-3">
						<h6>Details</h6>
						<?php echo wp_kses_post(wpautop($meta['description_excerpt'])); ?>
					</div>
				<?php endif; ?>
				
				<?php if (!empty($vote->rollcall_data)): ?>
					<div class="mb-3">
						<h6>Full Description</h6>
						<?php 
						$rollcall_data = json_decode($vote->rollcall_data, true);
						if (is_array($rollcall_data) && !empty($rollcall_data['description'])) {
							echo wp_kses_post(wpautop($rollcall_data['description']));
						}
						?>
					</div>
				<?php endif; ?>
				
				<?php if (!empty($meta['url_bill'])): ?>
					<div class="mt-3">
						<a href="<?php echo esc_url($meta['url_bill']); ?>" rel="noopener" class="btn btn-outline-primary btn-sm">
							<i class="bi bi-box-arrow-up-right me-1"></i> View Vote Details at Source
						</a>
					</div>
				<?php endif; ?>
			</div>
			<?php
			$html = ob_get_clean();
			
			wp_send_json_success(['html' => $html]);
		}
	}
}


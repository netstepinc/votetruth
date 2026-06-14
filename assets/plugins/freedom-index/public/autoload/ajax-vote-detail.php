<?php
/**
 * Freedom Index Public AJAX: Vote Detail Modal
 *
 * Handles AJAX requests for the public vote detail modal.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Register public AJAX handlers for vote detail modal.
 *
 * @return void
 */
function fi_public_ajax_vote_detail_init(): void {
	add_action('wp_ajax_fi_vote_detail', 'fi_public_ajax_handle_vote_detail');
	add_action('wp_ajax_nopriv_fi_vote_detail', 'fi_public_ajax_handle_vote_detail');

	// Compatibility with the public AJAX dispatcher if it routes sub_action=vote_detail.
	add_action('wp_ajax_fi_public_vote_detail', 'fi_public_ajax_handle_vote_detail');
	add_action('wp_ajax_nopriv_fi_public_vote_detail', 'fi_public_ajax_handle_vote_detail');
}
add_action('init', 'fi_public_ajax_vote_detail_init');

/**
 * AJAX handler for vote detail modal content.
 *
 * Expected POST:
 * - nonce
 * - vote_id
 * - legislator_id
 *
 * @return void
 */
function fi_public_ajax_handle_vote_detail(): void {
	check_ajax_referer('fi_ajax_nonce', 'nonce');

	$vote_id       = absint($_POST['vote_id'] ?? 0);
	$legislator_id = absint($_POST['legislator_id'] ?? 0);

	if ($vote_id <= 0 || $legislator_id <= 0) {
		wp_send_json_error('Invalid parameters');
	}

	$vote = function_exists('fi_vote_get') ? fi_vote_get($vote_id) : null;
	if (!$vote) {
		wp_send_json_error('Vote not found');
	}

	$html = fi_public_vote_detail_render_html($vote, $legislator_id);

	wp_send_json_success([
		'html' => $html,
	]);
}

/**
 * Render vote detail modal HTML.
 *
 * @param object $vote          Vote object.
 * @param int    $legislator_id Legislator ID.
 * @return string HTML.
 */
function fi_public_vote_detail_render_html(object $vote, int $legislator_id): string {
	$vote_id = absint($vote['id'] ?? 0);

	$rollcall = ($vote_id > 0 && function_exists('fi_rollcall_get'))
		? fi_rollcall_get($vote_id, $legislator_id)
		: null;

	$cast = is_array($rollcall) && isset($rollcall['cast'])
		? (string) $rollcall['cast']
		: 'X';

	$vote_format = function_exists('fi_vote_format')
		? fi_vote_format([
			'cast'           => $cast,
			'constitutional' => $vote['constitutional'] ?? '',
			'format'         => 'full',
		])
		: [
			'cast_class'      => '',
			'cast_class_icon' => '',
			'cast_text'       => $cast,
		];

	$constitutional_format = function_exists('fi_vote_format')
		? fi_vote_format([
			'constitutional' => $vote['constitutional'] ?? '',
			'format'         => 'full',
		])
		: [
			'vote_class'      => '',
			'vote_class_icon' => '',
			'vote_text'       => (string) ($vote['constitutional'] ?? ''),
		];

	$meta = function_exists('fi_vote_decode_meta')
		? fi_vote_decode_meta($vote)
		: fi_public_vote_detail_decode_meta($vote['meta'] ?? null);

	$description_short = function_exists('fi_vote_get_description')
		? fi_vote_get_description($meta, 'scorecard')
		: ($meta['description_short'] ?? '');

	$rollcall_description = fi_public_vote_detail_get_rollcall_description($vote);

	ob_start();
	?>
	<div class="fi-vote-detail">
		<h5><?php echo esc_html($vote['title'] ?? 'Untitled Vote'); ?></h5>

		<?php if (!empty($vote['bill_number'])): ?>
			<p class="text-muted mb-2">
				<strong>Bill:</strong> <?php echo esc_html($vote['bill_number']); ?>
			</p>
		<?php endif; ?>

		<?php if (!empty($vote['date_voted'])): ?>
			<p class="text-muted mb-2">
				<strong>Date:</strong> <?php echo esc_html(fi_public_vote_detail_format_date((string) $vote['date_voted'])); ?>
			</p>
		<?php endif; ?>

		<div class="mb-3">
			<strong>Constitutional Position:</strong>
			<span class="<?php echo esc_attr($constitutional_format['vote_class'] ?? ''); ?>">
				<?php if (!empty($constitutional_format['vote_class_icon'])): ?>
					<i class="<?php echo esc_attr($constitutional_format['vote_class_icon']); ?> me-1"></i>
				<?php endif; ?>
				<?php echo esc_html($constitutional_format['vote_text'] ?? ''); ?>
			</span>
		</div>

		<div class="mb-3">
			<strong>Vote Cast:</strong>
			<span class="<?php echo esc_attr($vote_format['cast_class'] ?? ''); ?>">
				<?php if (!empty($vote_format['cast_class_icon'])): ?>
					<i class="<?php echo esc_attr($vote_format['cast_class_icon']); ?> me-1"></i>
				<?php endif; ?>
				<?php echo esc_html($vote_format['cast_text'] ?? $cast); ?>
			</span>
		</div>

		<?php if (!empty($meta['cost'])): ?>
			<?php
			$cost = (string) $meta['cost'];
			$cost_class = (strpos($cost, '+') === 0) ? 'success' : 'danger';
			?>
			<div class="mb-3">
				<strong>Estimated Cost Per Household:</strong>
				<span class="text-<?php echo esc_attr($cost_class); ?>">
					$<?php echo esc_html(str_replace('+', '', $cost)); ?>
				</span>
			</div>
		<?php endif; ?>

		<?php if (!empty($description_short)): ?>
			<div class="mb-3">
				<h6>Effect on You</h6>
				<?php echo wp_kses_post(wpautop((string) $description_short)); ?>
			</div>
		<?php endif; ?>

		<?php if (!empty($meta['description_excerpt'])): ?>
			<div class="mb-3">
				<h6>Details</h6>
				<?php echo wp_kses_post(wpautop((string) $meta['description_excerpt'])); ?>
			</div>
		<?php endif; ?>

		<?php if ($rollcall_description !== ''): ?>
			<div class="mb-3">
				<h6>Full Description</h6>
				<?php echo wp_kses_post(wpautop($rollcall_description)); ?>
			</div>
		<?php endif; ?>

		<?php if (!empty($meta['url_bill'])): ?>
			<div class="mt-3">
				<a href="<?php echo esc_url($meta['url_bill']); ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
					<i class="bi bi-box-arrow-up-right me-1"></i> View Vote Details at Source
				</a>
			</div>
		<?php endif; ?>
	</div>
	<?php

	return (string) ob_get_clean();
}

/**
 * Decode vote meta fallback.
 *
 * @param mixed $meta Raw meta value.
 * @return array Meta array.
 */
function fi_public_vote_detail_decode_meta($meta): array {
	if (is_array($meta)) {
		return $meta;
	}

	if (is_object($meta)) {
		return (array) $meta;
	}

	if (is_string($meta) && $meta !== '') {
		$decoded = json_decode($meta, true);
		return is_array($decoded) ? $decoded : [];
	}

	return [];
}

/**
 * Extract rollcall description from vote rollcall_data.
 *
 * @param object $vote Vote object.
 * @return string Description.
 */
function fi_public_vote_detail_get_rollcall_description(object $vote): string {
	if (empty($vote['rollcall_data'])) {
		return '';
	}

	$rollcall_data = json_decode((string) $vote['rollcall_data'], true);

	if (!is_array($rollcall_data) || empty($rollcall_data['description'])) {
		return '';
	}

	return (string) $rollcall_data['description'];
}

/**
 * Format vote date for display.
 *
 * @param string $date_raw Raw date value.
 * @return string Formatted date.
 */
function fi_public_vote_detail_format_date(string $date_raw): string {
	$timestamp = strtotime($date_raw);

	if (!$timestamp) {
		return $date_raw;
	}

	return wp_date('F j, Y', $timestamp);
}
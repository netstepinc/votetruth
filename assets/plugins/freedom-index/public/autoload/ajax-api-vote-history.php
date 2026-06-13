<?php
/**
 * Freedom Index Public AJAX: Legislator Vote History
 *
 * Declassified replacement for the former FI\Public\AjaxHandlersApiVoteHistoryTrait.
 *
 * Recommended location:
 * /public/autoload/ajax-legislator-vote-history.php
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Register public AJAX handlers for legislator vote history.
 *
 * @return void
 */
function fi_public_ajax_legislator_vote_history_init(): void {
	add_action('wp_ajax_fi_legislator_vote_history', 'fi_public_ajax_handle_legislator_vote_history');
	add_action('wp_ajax_nopriv_fi_legislator_vote_history', 'fi_public_ajax_handle_legislator_vote_history');

	// Compatibility action name if existing JS uses a more API-style action.
	add_action('wp_ajax_fi_api_legislator_vote_history', 'fi_public_ajax_handle_legislator_vote_history');
	add_action('wp_ajax_nopriv_fi_api_legislator_vote_history', 'fi_public_ajax_handle_legislator_vote_history');
}
add_action('init', 'fi_public_ajax_legislator_vote_history_init');

/**
 * Handle legislator vote history loading.
 *
 * Supports:
 * - all
 * - session
 * - report
 * - tag
 *
 * Expected POST:
 * - nonce
 * - legislator_id
 * - chamber
 * - party optional
 * - view all|session|report|tag
 * - session_id optional/required for session/report
 * - report_id required for report
 * - tag_id required for tag
 *
 * @return void
 */
function fi_public_ajax_handle_legislator_vote_history(): void {
	check_ajax_referer('fi_ajax_nonce', 'nonce');

	$legislator_id = absint($_POST['legislator_id'] ?? 0);
	$chamber       = strtoupper(sanitize_text_field(wp_unslash($_POST['chamber'] ?? '')));
	$party         = strtoupper(sanitize_text_field(wp_unslash($_POST['party'] ?? '')));
	$view          = sanitize_key((string) wp_unslash($_POST['view'] ?? 'all'));
	$session_id    = !empty($_POST['session_id']) ? absint($_POST['session_id']) : null;
	$report_id     = !empty($_POST['report_id']) ? absint($_POST['report_id']) : null;
	$tag_id        = !empty($_POST['tag_id']) ? absint($_POST['tag_id']) : null;

	if ($legislator_id <= 0 || !in_array($chamber, ['H', 'S'], true)) {
		wp_send_json_error('Invalid parameters');
	}

	$gov = fi_public_ajax_vote_history_get_gov($session_id);
	$chambers = function_exists('fi_chamber_options') ? fi_chamber_options($gov) : [];

	$result = fi_public_ajax_vote_history_fetch_result([
		'view'          => $view,
		'legislator_id' => $legislator_id,
		'session_id'    => $session_id,
		'report_id'     => $report_id,
		'tag_id'        => $tag_id,
	]);

	if (is_wp_error($result)) {
		wp_send_json_error($result->get_error_message());
	}

	$result = fi_public_ajax_vote_history_normalize_result($result);
	$html = fi_public_ajax_vote_history_render_html($result, [
		'gov'           => $gov,
		'chambers'      => $chambers,
		'chamber'       => $chamber,
		'party'         => $party,
		'legislator_id' => $legislator_id,
	]);

	$result['html']  = $html;
	$result['title'] = $result['title'] ?: 'Votes';

	if (function_exists('fi_ajax_log')) {
		fi_ajax_log('Legislator vote history rendered', [
			'view'          => $view,
			'legislator_id' => $legislator_id,
			'session_id'    => $session_id,
			'report_id'     => $report_id,
			'tag_id'        => $tag_id,
			'vote_count'    => count($result['votes']),
			'score'         => $result['score'] ?? null,
			'title'         => $result['title'] ?? '',
			'html_length'   => strlen($html),
		], 'debug', __FILE__, __LINE__);
	}

	wp_send_json_success($result);
}

/**
 * Get gov from session ID when available.
 *
 * @param int|null $session_id Session ID.
 * @return string|null Government code.
 */
function fi_public_ajax_vote_history_get_gov(?int $session_id): ?string {
	if (!$session_id || !function_exists('fi_session_get')) {
		return null;
	}

	$session = fi_session_get($session_id);
	$gov = $session->gov ?? null;

	return $gov ? strtoupper((string) $gov) : null;
}

/**
 * Fetch vote-history result from the internal API request helper.
 *
 * @param array $args Request args.
 * @return array|WP_Error
 */
function fi_public_ajax_vote_history_fetch_result(array $args) {
	if (!function_exists('fi_api_request')) {
		return new WP_Error('missing_api_helper', 'Vote history API helper is unavailable.');
	}

	$view          = sanitize_key((string) ($args['view'] ?? 'all'));
	$legislator_id = absint($args['legislator_id'] ?? 0);
	$session_id    = !empty($args['session_id']) ? absint($args['session_id']) : null;
	$report_id     = !empty($args['report_id']) ? absint($args['report_id']) : null;
	$tag_id        = !empty($args['tag_id']) ? absint($args['tag_id']) : null;

	switch ($view) {
		case 'all':
			return fi_api_request([
				'action'        => 'votes_legislator',
				'legislator_id' => $legislator_id,
			]);

		case 'tag':
			if (!$tag_id) {
				return new WP_Error('missing_tag_id', 'Tag ID required');
			}

			return fi_api_request([
				'action'        => 'votes_tag_legislator',
				'tag_id'        => $tag_id,
				'legislator_id' => $legislator_id,
			]);

		case 'session':
			if (!$session_id) {
				return new WP_Error('missing_session_id', 'Session ID required');
			}

			return fi_api_request([
				'action'        => 'votes_session_legislator',
				'session_id'    => $session_id,
				'legislator_id' => $legislator_id,
			]);

		case 'report':
			if (!$session_id || !$report_id) {
				return new WP_Error('missing_report_context', 'Session ID and Report ID required');
			}

			return fi_api_request([
				'action'        => 'votes_report_legislator',
				'report_id'     => $report_id,
				'legislator_id' => $legislator_id,
			]);
	}

	return new WP_Error('invalid_view_type', 'Invalid view type');
}

/**
 * Normalize vote-history API result.
 *
 * @param mixed $result Raw result.
 * @return array Normalized result.
 */
function fi_public_ajax_vote_history_normalize_result($result): array {
	if (!is_array($result)) {
		$result = [];
	}

	$defaults = [
		'title'         => '',
		'subtitle'      => '',
		'score'         => 0,
		'report_header' => null,
		'report_format' => 'scorecard',
		'votes'         => [],
	];

	$result = wp_parse_args($result, $defaults);

	if (!is_array($result['votes'])) {
		$result['votes'] = [];
	}

	return $result;
}

/**
 * Render vote-history HTML from normalized result.
 *
 * @param array $result Normalized API result.
 * @param array $context Render context.
 * @return string HTML.
 */
function fi_public_ajax_vote_history_render_html(array $result, array $context = []): string {
	$gov           = $context['gov'] ?? null;
	$chambers      = is_array($context['chambers'] ?? null) ? $context['chambers'] : [];
	$report_format = $result['report_format'] ?? 'scorecard';

	ob_start();

	if (!empty($result['report_header']) && is_array($result['report_header'])) {
		echo '<div class="mb-3 p-2 p-lg-3">';
		echo '<div class="fs-7">' . wp_kses_post($result['report_header']['content'] ?? '') . '</div>';
		echo '</div>';
	}

	if (empty($result['votes'])) {
		echo '<div class="alert alert-info">No votes found for this selection.</div>';
		return (string) ob_get_clean();
	}

	echo '<div class="row g-3" id="fi-vote-cards-container">';

	foreach ($result['votes'] as $vote) {
		$vote = is_array($vote) ? (object) $vote : $vote;
		if (!is_object($vote)) {
			continue;
		}

		$vote_data = fi_public_ajax_vote_history_prepare_vote_card_data($vote, [
			'gov'           => $gov,
			'chambers'      => $chambers,
			'report_format' => $report_format,
		]);

		if (function_exists('fi_get_public_template')) {
			fi_get_public_template('partials/vote-card', $vote_data);
		} else {
			fi_public_ajax_vote_history_render_fallback_vote_card($vote_data);
		}
	}

	echo '</div>';

	return (string) ob_get_clean();
}

/**
 * Prepare vote-card template data.
 *
 * @param object $vote Vote object.
 * @param array $context Render context.
 * @return array Vote-card data.
 */
function fi_public_ajax_vote_history_prepare_vote_card_data(object $vote, array $context = []): array {
	$gov = $context['gov'] ?? ($vote->gov ?? '');
	$chambers = is_array($context['chambers'] ?? null) ? $context['chambers'] : [];
	$report_format = $context['report_format'] ?? 'scorecard';

	$vote_meta = function_exists('fi_vote_decode_meta')
		? fi_vote_decode_meta($vote)
		: fi_public_ajax_vote_history_decode_meta($vote->meta ?? null);

	$description = function_exists('fi_vote_get_description')
		? fi_vote_get_description($vote_meta, 'small')
		: ($vote_meta['description_short'] ?? '');

	$text_more = function_exists('fi_vote_get_description')
		? fi_vote_get_description($vote_meta, 'freedomindex')
		: ($vote_meta['description_long'] ?? '');

	$formatted_date = fi_public_ajax_vote_history_format_date((string) ($vote->date_voted ?? ''));

	$cast = (string) ($vote->cast ?? 'X');
	$vote_format = function_exists('fi_vote_format')
		? fi_vote_format([
			'cast'           => $cast,
			'constitutional' => $vote->constitutional ?? '',
			'format'         => 'full',
		])
		: [
			'cast_text'       => $cast,
			'cast_class'      => '',
			'cast_class_icon' => '',
		];

	$cost_html = '';
	$cost_value = $vote_meta['cost'] ?? '';
	if ($cost_value !== '' && function_exists('fi_vote_cost_format')) {
		$cost = fi_vote_cost_format($cost_value);
		$cost_html = is_array($cost) ? ($cost['html'] ?? '') : '';
	}

	$url_vote = function_exists('fi_url_vote')
		? fi_url_vote($vote->gov ?? '', $vote->id ?? 0)
		: '';

	$vote_chamber = (string) ($vote->chamber ?? '');
	$chamber_label = $chambers[$vote_chamber]['chamber'] ?? '';
	if ($chamber_label === '' && function_exists('fi_chamber_label')) {
		$chamber_label = fi_chamber_label($gov, $vote_chamber);
	}

	$tags = $vote_meta['tags'] ?? [];
	if (!is_array($tags)) {
		$tags = [];
	}

	$search_text = strtolower(
		(string) ($vote->title ?? '') . ' ' .
		(string) ($vote->bill_number ?? '') . ' ' .
		wp_strip_all_tags((string) $description)
	);

	return [
		'id'              => (int) ($vote->id ?? 0),
		'gov'             => (string) ($vote->gov ?? ''),
		'title'           => (string) ($vote->title ?? ''),
		'text'            => (string) $description,
		'text_more'       => (string) $text_more,
		'tags'            => $tags,
		'bill_number'     => (string) ($vote->bill_number ?? ''),
		'constitutional'  => (string) ($vote->constitutional ?? ''),
		'date_voted'      => (string) ($vote->date_voted ?? ''),
		'date_formatted'  => $formatted_date,
		'vote_format'     => $vote_format,
		'bill_url'        => (string) ($vote_meta['url_bill'] ?? ''),
		'cost_html'       => $cost_html,
		'url_vote'        => $url_vote,
		'search_text'     => $search_text,
		'report_format'   => $report_format,
		'chamber_title'   => true,
		'chamber_label'   => $chamber_label,
		'show_cast'       => true,
		'show_link'       => true,
		'cast'            => $cast,
	];
}

/**
 * Decode vote meta fallback.
 *
 * @param mixed $meta Raw meta.
 * @return array Meta array.
 */
function fi_public_ajax_vote_history_decode_meta($meta): array {
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
 * Format vote date for vote-card display.
 *
 * @param string $date_raw Raw date.
 * @return string Date string.
 */
function fi_public_ajax_vote_history_format_date(string $date_raw): string {
	if ($date_raw === '') {
		return '';
	}

	$timestamp = strtotime($date_raw);
	if ($timestamp === false) {
		return $date_raw;
	}

	return wp_date('n/j/Y', $timestamp);
}

/**
 * Fallback renderer if the vote-card partial is unavailable.
 *
 * @param array $vote_data Vote data.
 * @return void
 */
function fi_public_ajax_vote_history_render_fallback_vote_card(array $vote_data): void {
	?>
	<div class="col-12">
		<div class="card h-100">
			<div class="card-body">
				<h5 class="card-title"><?php echo esc_html($vote_data['title'] ?? 'Untitled Vote'); ?></h5>
				<?php if (!empty($vote_data['bill_number'])): ?>
					<div class="text-muted small mb-2"><?php echo esc_html($vote_data['bill_number']); ?></div>
				<?php endif; ?>
				<?php if (!empty($vote_data['text'])): ?>
					<div><?php echo wp_kses_post(wpautop((string) $vote_data['text'])); ?></div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}

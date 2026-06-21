<?php
/**
 * Vote Card Partial — simplified legislator vote display
 *
 * modal_mode: 'page' | 'bootstrap' | 'none'
 */

if (!defined('ABSPATH')) exit;

$args = $args ?? [];

$config = array_merge([
	'id'             => 0,
	'title'          => '',
	'text'           => '',
	'text_more'      => '',
	'tags'           => [],
	'constitutional' => '',
	'vote_format'    => [],
	'bill_number'    => '',
	'bill_url'       => '',
	'cost_html'      => '',
	'cost_sentence'  => '',
	'url_vote'       => '',
	'gov'            => '',
	'search_text'    => '',
	'show_cast'      => true,
	'modal_mode'     => 'page',
	'cast'           => '',
	'chamber_label'  => '',
	'chamber_title'  => false,
], $args);

if (isset($args['show_modal'])) {
	$config['modal_mode'] = $args['show_modal'] ? 'bootstrap' : 'page';
}

$modal_mode = $config['modal_mode'];
$vote_id    = (int) $config['id'];
$vf         = $config['vote_format'];
$gov        = $config['gov'] ?: 'US';

$is_match   = (int) ($vf['is_match'] ?? 0);
$is_no_vote = (int) ($vf['is_no_vote'] ?? 0);
$const_raw  = strtoupper((string) $config['constitutional']);
$const_label = ($const_raw === 'Y') ? 'YES' : (($const_raw === 'N') ? 'NO' : '—');
$cast_raw   = strtoupper((string) $config['cast']);
$cast_label = ($cast_raw === 'Y') ? 'YES' : (($cast_raw === 'N') ? 'NO' : '—');

$const_class = ($const_raw === 'Y') ? 'text-success' : (($const_raw === 'N') ? 'text-danger' : 'text-muted');
$cast_class  = $is_no_vote ? 'text-muted' : ($is_match ? 'text-success' : 'text-danger');
$match_class = $is_no_vote ? 'bg-secondary' : ($is_match ? 'bg-success' : 'bg-danger');

$details_html = '';
if ($modal_mode !== 'none' && !empty($config['text_more'])) {
	$details_html .= '<div class="fi-vote-detail-text mb-3">' . wp_kses_post(wpautop($config['text_more'])) . '</div>';
}
if ($modal_mode !== 'none' && !empty($config['bill_url'])) {
	$details_html .= '<p class="mb-3"><a href="' . esc_url($config['bill_url']) . '" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener noreferrer">View Bill Text</a></p>';
}
if ($modal_mode !== 'none' && !empty($config['tags'])) {
	$details_html .= '<div class="fi-vote-detail-tags d-flex flex-wrap gap-2 mt-2">';
	foreach ($config['tags'] as $tag) {
		$tag = is_array($tag) ? $tag : (array) $tag;
		$tid = (int) ($tag['id'] ?? 0);
		$tnm = (string) ($tag['name'] ?? '');
		if (!$tid || !$tnm) continue;
		$details_html .= '<a href="' . esc_url(fi_tag_url($tid, $gov)) . '" class="badge bg-primary text-decoration-none">' . esc_html($tnm) . '</a>';
	}
	$details_html .= '</div>';
}

$has_details = $details_html !== '';
$cost_line = $config['cost_sentence'] ?: $config['cost_html'];
?>

<div class="col-12 fi-vote-card"
	id="vcard-<?php echo $vote_id; ?>"
	data-vote-id="<?php echo $vote_id; ?>"
	data-search-text="<?php echo esc_attr($config['search_text']); ?>">

	<div class="card shadow-sm border rounded-3 overflow-hidden h-100">

		<div class="card-header bg-white py-2 px-3 border-bottom-0">
			<h6 class="card-title mb-0 fw-semibold lh-sm">
				<?php if (!empty($config['url_vote'])): ?>
					<a href="<?php echo esc_url($config['url_vote']); ?>" class="text-body text-decoration-none">
						<?php echo esc_html($config['title']); ?>
					</a>
				<?php else: ?>
					<?php echo esc_html($config['title']); ?>
				<?php endif; ?>
			</h6>
			<?php if (!empty($config['bill_number']) || ($config['chamber_title'] && !empty($config['chamber_label']))): ?>
			<div class="text-muted small mt-1">
				<?php
				$meta = array_filter([
					$config['bill_number'] ?? '',
					($config['chamber_title'] && !empty($config['chamber_label'])) ? $config['chamber_label'] : '',
				]);
				echo esc_html(implode(' · ', $meta));
				?>
			</div>
			<?php endif; ?>
		</div>

		<div class="card-body py-2 px-3">
			<?php if (!empty($config['text'])): ?>
			<p class="card-text small mb-2">
				<?php echo wp_kses_post($config['text']); ?>
				<?php if ($has_details && $modal_mode === 'page'): ?>
					<button type="button"
						class="badge bg-primary border-0 fi-vote-readmore ms-1"
						data-vote-id="<?php echo $vote_id; ?>"
						data-vote-title="<?php echo esc_attr($config['title']); ?>"
						data-vote-body="<?php echo esc_attr($details_html); ?>">Read More</button>
				<?php endif; ?>
			</p>
			<?php elseif ($has_details && $modal_mode === 'page'): ?>
			<p class="mb-2">
				<button type="button"
					class="badge bg-primary border-0 fi-vote-readmore"
					data-vote-id="<?php echo $vote_id; ?>"
					data-vote-title="<?php echo esc_attr($config['title']); ?>"
					data-vote-body="<?php echo esc_attr($details_html); ?>">Read More</button>
			</p>
			<?php endif; ?>

			<?php if (!empty($cost_line)): ?>
			<div class="small mb-2"><?php echo wp_kses_post($cost_line); ?></div>
			<?php endif; ?>

			<?php if ($config['show_cast']): ?>
			<div class="row g-2 fi-vote-indicators">
				<div class="col-12 col-md-6">
					<div class="small text-muted">Constitutional</div>
					<div class="fw-bold <?php echo esc_attr($const_class); ?>"><?php echo esc_html($const_label); ?></div>
				</div>
				<div class="col-12 col-md-6">
					<div class="small text-muted">Vote Cast</div>
					<div class="d-flex align-items-center gap-2">
						<span class="fw-bold <?php echo esc_attr($cast_class); ?>"><?php echo esc_html($cast_label); ?></span>
						<span class="badge <?php echo esc_attr($match_class); ?> rounded-pill">&nbsp;</span>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</div>

	</div>
</div>

<?php if ($modal_mode === 'bootstrap' && $has_details): ?>
<div class="modal fade" id="fi-vote-detail-modal-<?php echo $vote_id; ?>" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h2 class="modal-title fs-5 fw-bold"><?php echo esc_html($config['title']); ?></h2>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body"><?php echo $details_html; ?></div>
		</div>
	</div>
</div>
<?php endif; ?>

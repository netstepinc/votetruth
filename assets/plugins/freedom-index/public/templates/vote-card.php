<?php
/**
 * Vote Card Partial — Canonical single-vote display
 *
 * Used on legislator profile (server-render + AJAX), tag pages, and report pages.
 * All data processing happens in the parent; this file only renders.
 *
 * ## modal_mode
 *   'page'       No per-card modal. "Details" button gets class fi-vote-readmore +
 *                data-vote-title + data-vote-body. Page provides ONE shared modal.
 *   'bootstrap'  Per-card modal rendered inline (use for pages with few votes).
 *   'none'       No Details button rendered.
 *
 * ## Required $args keys
 *   id              int
 *   title           string
 *   constitutional  string  Y|N|U — constitutionally-correct vote direction
 *   vote_format     array   from fi_vote_format()
 *   show_cast       bool
 *
 * ## Optional $args keys
 *   text            string  Short description (always visible)
 *   text_more       string  Long description (shown in modal)
 *   tags            array   [{id,name}] — from session cache or AJAX
 *   bill_number     string
 *   bill_url        string
 *   date_formatted  string
 *   url_vote        string
 *   gov             string  Government code — for tag URLs
 *   search_text     string  Lowercased, for client-side filter
 *   cost_html       string
 *   modal_mode      string  'page'|'bootstrap'|'none'  (default: 'page')
 */

if (!defined('ABSPATH')) exit;

$args = $args ?? [];

$config = array_merge([
	'id'             => 0,
	'title'          => '',
	'text'           => '',
	'text_more'      => '',
	'tags'           => [],
	'date_formatted' => '',
	'constitutional' => '',
	'vote_format'    => [],
	'bill_number'    => '',
	'bill_url'       => '',
	'cost_html'      => '',
	'url_vote'       => '',
	'gov'            => '',
	'search_text'    => '',
	'show_cast'      => true,
	'modal_mode'     => 'page',
	'cast'           => '',
	'chamber_label'  => '',
	'chamber_title'  => false,
], $args);

// Backward-compat: legacy show_modal bool → modal_mode
if (isset($args['show_modal'])) {
	$config['modal_mode'] = $args['show_modal'] ? 'bootstrap' : 'page';
}
if ($config['modal_mode'] === true)  $config['modal_mode'] = 'bootstrap';
if ($config['modal_mode'] === false) $config['modal_mode'] = 'page';

$modal_mode = $config['modal_mode'];
$vote_id    = (int) $config['id'];
$vf         = $config['vote_format'];    // from fi_vote_format()
$gov        = $config['gov'] ?: 'US';

// Cast outcome — determines footer column color and icon
$is_match   = (int) ($vf['is_match']   ?? 0);
$is_no_vote = (int) ($vf['is_no_vote'] ?? 0);

if ($is_no_vote || !$config['show_cast']) {
	$cast_bg    = 'bg-secondary';
	$cast_text  = 'text-white';
	$cast_icon  = 'bi-dash-circle';
	$cast_label = $vf['cast_text'] ?: '—';
} elseif ($is_match) {
	$cast_bg    = 'bg-success';
	$cast_text  = 'text-white';
	$cast_icon  = 'bi-check-circle-fill';
	$cast_label = $vf['cast_text'] ?: 'Yes';
} else {
	$cast_bg    = 'bg-danger';
	$cast_text  = 'text-white';
	$cast_icon  = 'bi-x-circle-fill';
	$cast_label = $vf['cast_text'] ?: 'No';
}

// Details content for modal (built once, used in button data attrs or inline modal)
$details_html = '';
if ($modal_mode !== 'none' && !empty($config['text_more'])) {
	$details_html .= '<div class="fi-vote-detail-text mb-3">' . wp_kses_post(wpautop($config['text_more'])) . '</div>';
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
?>

<div class="col-12 fi-vote-card"
	id="vcard-<?php echo $vote_id; ?>"
	data-vote-id="<?php echo $vote_id; ?>"
	data-search-text="<?php echo esc_attr($config['search_text']); ?>">

	<div class="card shadow-sm border rounded-3 overflow-hidden">

		<!-- ── HEADER: Title ──────────────────────────────────────────── -->
		<div class="card-header bg-white py-2 px-3">
			<h6 class="card-title mb-0 fw-semibold lh-sm">
				<?php if (!empty($config['url_vote'])): ?>
					<a href="<?php echo esc_url($config['url_vote']); ?>" class="text-body text-decoration-none">
						<?php echo esc_html($config['title']); ?>
					</a>
				<?php else: ?>
					<?php echo esc_html($config['title']); ?>
				<?php endif; ?>
			</h6>

			<?php
			// One-line meta row under the title: bill number + freedom vote direction
			$meta_parts = [];
			if (!empty($config['bill_number'])) {
				$meta_parts[] = esc_html($config['bill_number']);
			}
			if (!empty($vf['vote_text'])) {
				$meta_parts[] = 'Freedom vote: <strong>' . esc_html($vf['vote_text']) . '</strong>';
			}
			if ($config['show_cast'] && $config['chamber_title'] && !empty($config['chamber_label'])) {
				$meta_parts[] = esc_html($config['chamber_label']);
			}
			if ($meta_parts):
			?>
			<div class="text-muted small mt-1"><?php echo implode(' &bull; ', $meta_parts); ?></div>
			<?php endif; ?>
		</div><!-- /card-header -->

		<!-- ── BODY: Description + tags ─────────────────────────────── -->
		<?php if (!empty($config['text']) || !empty($config['tags'])): ?>
		<div class="card-body py-2 px-3">

			<?php if (!empty($config['text'])): ?>
				<p class="card-text small mb-2"><?php echo wp_kses_post($config['text']); ?></p>
			<?php endif; ?>

			<?php if (!empty($config['tags'])): ?>
			<div class="d-flex flex-wrap gap-1">
				<?php foreach ($config['tags'] as $tag):
					$tag = is_array($tag) ? $tag : (array) $tag;
					$tid = (int) ($tag['id'] ?? 0);
					$tnm = (string) ($tag['name'] ?? '');
					if (!$tid || !$tnm) continue;
				?>
					<a href="<?php echo esc_url(fi_tag_url($tid, $gov)); ?>"
						class="badge bg-secondary text-decoration-none rounded-pill"
						title="Filter by <?php echo esc_attr($tnm); ?>">
						<?php echo esc_html($tnm); ?>
					</a>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

		</div>
		<?php endif; ?>

		<!-- ── FOOTER: Date · Cost · Cast column · Bill link · Details ── -->
		<div class="card-footer p-0 bg-light">
			<div class="row g-0 align-items-stretch text-center" style="font-size:.8rem;">

				<?php if (!empty($config['date_formatted'])): ?>
				<div class="col py-2 border-end">
					<div class="fw-bold lh-1"><?php echo esc_html($config['date_formatted']); ?></div>
					<small class="text-muted">Date</small>
				</div>
				<?php endif; ?>

				<?php if (!empty($config['cost_html'])): ?>
				<div class="col py-2 border-end">
					<div class="fw-bold lh-1"><?php echo wp_kses_post($config['cost_html']); ?></div>
					<small class="text-muted">Your Cost</small>
				</div>
				<?php endif; ?>

				<?php if ($config['show_cast']): ?>
				<!-- Cast column with outcome color — narrow, not full-width -->
				<div class="col py-2 <?php echo esc_attr($cast_bg . ' ' . $cast_text); ?> border-end">
					<div class="fw-bold lh-1">
						<i class="bi <?php echo esc_attr($cast_icon); ?> me-1" aria-hidden="true"></i>
						<?php echo esc_html($cast_label); ?>
					</div>
					<small class="opacity-75">They Voted</small>
				</div>
				<?php endif; ?>

				<?php if (!empty($config['bill_url'])): ?>
				<div class="col py-2 border-end">
					<a href="<?php echo esc_url($config['bill_url']); ?>"
						class="fw-bold d-block lh-1 text-decoration-none text-primary"
						target="_blank" rel="noopener noreferrer">
						<i class="bi bi-file-text" aria-hidden="true"></i>
					</a>
					<small class="text-muted">Bill Text</small>
				</div>
				<?php endif; ?>

				<?php if ($has_details): ?>
				<div class="col py-2">
					<?php if ($modal_mode === 'bootstrap'): ?>
						<a href="#" class="fw-bold d-block lh-1 text-decoration-none text-primary"
							data-bs-toggle="modal"
							data-bs-target="#fi-vote-detail-modal-<?php echo $vote_id; ?>">
							<i class="bi bi-info-circle" aria-hidden="true"></i>
						</a>
					<?php elseif ($modal_mode === 'page'): ?>
						<button type="button"
							class="btn btn-link p-0 lh-1 fw-bold text-primary fi-vote-readmore"
							data-vote-title="<?php echo esc_attr($config['title']); ?>"
							data-vote-body="<?php echo esc_attr($details_html); ?>">
							<i class="bi bi-info-circle" aria-hidden="true"></i>
						</button>
					<?php endif; ?>
					<small class="text-muted d-block">Details</small>
				</div>
				<?php endif; ?>

			</div>
		</div><!-- /card-footer -->

	</div><!-- /card -->
</div><!-- /fi-vote-card -->

<?php
// Per-card modal only for 'bootstrap' mode
if ($modal_mode === 'bootstrap' && $has_details):
?>
<div class="modal fade" id="fi-vote-detail-modal-<?php echo $vote_id; ?>" tabindex="-1"
	aria-labelledby="fi-vote-modal-label-<?php echo $vote_id; ?>" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h2 class="modal-title fs-5 fw-bold" id="fi-vote-modal-label-<?php echo $vote_id; ?>">
					<?php echo esc_html($config['title']); ?>
				</h2>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<?php echo $details_html; ?>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>
<?php endif; ?>

<?php
/**
 * Vote Card Partial — compact legislator vote row (mockup: fi-legislator-1.html)
 *
 * Two layouts:
 *   NEW  (impact_summary present) — structured meta line + Constitutional/Cast/Cost block
 *   LEGACY (no impact_summary)    — existing description_short layout, no change
 *
 * modal_mode: 'page' | 'bootstrap' | 'none'
 */

if (!defined('ABSPATH')) exit;

$args = $args ?? [];

$config = array_merge([
	'id'               => 0,
	'title'            => '',
	'text'             => '',       // description_short (legacy fallback)
	'text_more'        => '',
	'impact_summary'   => '',       // new — triggers new layout when non-empty
	'vote_outcome'     => '',       // '1' = Passed, '0' = Rejected
	'votes_for'        => null,
	'votes_against'    => null,
	'citation'         => '',
	'cost_value'       => '',       // raw cost string for new layout
	'tags'             => [],
	'constitutional'   => '',
	'vote_format'      => [],
	'bill_number'      => '',
	'bill_url'         => '',
	'cost_html'        => '',
	'cost_sentence'    => '',
	'cost_badge'       => '',
	'cost_badge_class' => '',
	'date_formatted'   => '',
	'url_vote'         => '',
	'gov'              => '',
	'search_text'      => '',
	'show_cast'        => true,
	'modal_mode'       => 'page',
	'cast'             => '',
	'chamber_label'    => '',
], $args);


//echo '<textarea style="width: 100%; height: 200px;">';print_r($config);echo '</textarea>';exit;
/*
Array
(
    [id] => 3476
    [title] => China-funded Schools
    [text] => <p><strong>HR 1069 China-funded Schools </strong>(Passed 247 to 164 on 12/4/2025, Roll Call 313). Prohibits federal education funds from being awarded to any elementary or secondary school that directly or indirectly receives support from the government of the People’s Republic of China. See U.S. Const., Art. I, Sec. 8.</p>

    [text_more] => <p>H.R. 1069, the “PROTECT Our Kids Act,” would prohibit federal education funds from being awarded to any elementary or secondary school that directly or indirectly receives support from the government of the People’s Republic of China. This includes partnerships with Confucius Institutes or Classrooms and other arrangements involving Chinese government-backed funding, materials, or personnel.</p>
<p>The House passed H.R. 1069 on December 4, 2025 by a vote of 247 to 164 (Roll Call 313). We have assigned pluses to the yeas because Article I, Section 8 of the Constitution does not permit Congress to fund or legislate on education. Furthermore, federal funds should not subsidize foreign governments’ propaganda, especially adversarial communist regimes with a documented record of censorship and influence operations. Allowing China to finance curricula, personnel, or materials in American schools threatens national sovereignty, undermines parental authority, and exposes students to ideological indoctrination incompatible with American principles.</p>

    [impact_summary] => <span data-teams="true">This bill stops the U.S. government from giving money to any public grade school or high school that gets support from the Chinese government.</span>
    [vote_outcome] => 1
    [votes_for] => 247
    [votes_against] => 164
    [citation] => Art. I Sec. 8
    [cost_value] => 
    [tags] => Array
        (
            [0] => Array
                (
                    [id] => 8
                    [name] => Limited Government
                )

            [1] => Array
                (
                    [id] => 7
                    [name] => Spending
                )

        )

    [constitutional] => Y
    [vote_format] => Array
        (
            [is_match] => 1
            [is_no_vote] => 0
        )

    [bill_number] => H.R. 1069
    [bill_url] => https://www.congress.gov/bill/119th-congress/house-bill/1069/all-info
    [cost_html] => 
    [cost_sentence] => 
    [cost_badge] => 
    [cost_badge_class] => 
    [date_formatted] => 12/4/2025
    [url_vote] => http://localhost/votetruth/us/vote/3476/
    [gov] => US
    [search_text] => china-funded schools h.r. 1069 h.r. 1069, the “protect our kids act,” would prohibit federal education funds from being awarded to any elementary or secondary school that directly or indirectly receives support from the government of the people’s republic of china. this includes partnerships with confucius institutes or classrooms and other arrangements involving chinese government-backed funding, materials, or personnel.
the house passed h.r. 1069 on december 4, 2025 by a vote of 247 to 164 (roll call 313). we have assigned pluses to the yeas because article i, section 8 of the constitution does not permit congress to fund or legislate on education. furthermore, federal funds should not subsidize foreign governments’ propaganda, especially adversarial communist regimes with a documented record of censorship and influence operations. allowing china to finance curricula, personnel, or materials in american schools threatens national sovereignty, undermines parental authority, and exposes students to ideological indoctrination incompatible with american principles.
    [show_cast] => 1
    [modal_mode] => page
    [cast] => Y
    [chamber_label] => House
    [date_voted] => 2025-12-04 00:00:00
)

*/


if (isset($args['show_modal'])) {
	$config['modal_mode'] = $args['show_modal'] ? 'bootstrap' : 'page';
}

$modal_mode = $config['modal_mode'];
$vote_id    = (int) $config['id'];
$vf         = $config['vote_format'];
$gov        = $config['gov'] ?: 'US';

if (empty($vf) && $config['show_cast']) {
	$vf = fi_vote_format([
		'cast'           => $config['cast'],
		'constitutional' => $config['constitutional'],
		'format'         => 'full',
	]);
}

$is_match   = (int) ($vf['is_match'] ?? 0);
$is_no_vote = (int) ($vf['is_no_vote'] ?? 0);

$card_variant   = 'is-neutral';
$status_variant = 'neutral';
$status_icon    = 'bi-x-circle-fill';
if ($config['show_cast']) {
	if ($is_no_vote) {
		$card_variant   = 'is-neutral';
		$status_variant = 'neutral';
		$status_icon    = 'bi-x-circle-fill';
	} elseif ($is_match) {
		$card_variant   = 'is-good';
		$status_variant = 'good';
		$status_icon    = 'bi-check-lg';
	} else {
		$card_variant   = 'is-bad';
		$status_variant = 'bad';
		$status_icon    = 'bi-x-lg';
	}
}

// ── Layout switch ────────────────────────────────────────────────────────────
$use_new_layout = $config['impact_summary'] !== '';

// ── Legacy: cost badge (fallback layout only) ────────────────────────────────
$cost_badge = (string) $config['cost_badge'];
$cost_badge_class = (string) $config['cost_badge_class'];
if ($cost_badge === '' && !empty($config['cost_html'])) {
	$cost_badge = wp_strip_all_tags((string) $config['cost_html']);
	$cost_badge_class = (strpos(trim($cost_badge), '+') === 0) ? 'bad' : 'good';
}
$cost_badge_bs = ($cost_badge_class === 'bad') ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success';

$status_bs = match ($status_variant) {
	'good'    => 'bg-success-subtle text-success',
	'bad'     => 'bg-danger-subtle text-danger',
	default   => 'bg-light text-secondary',
};

$has_modal_details = $modal_mode !== 'none' && (!empty($config['text_more']) || !empty($config['impact_summary']));
$interactive = $has_modal_details && $modal_mode === 'page';

$details_html = '';
if ($has_modal_details) {
	if (!empty($config['impact_summary'])) {
		$details_html .= '<div class="fi-vote-impact-summary mb-3">' . wp_kses_post(wpautop($config['impact_summary'])) . '</div>';
	}
	if (!empty($config['text_more'])) {
		$details_html .= '<div class="fi-vote-detail-text mb-3">' . wp_kses_post(wpautop($config['text_more'])) . '</div>';
	}
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
		if (!$tid || !$tnm) {
			continue;
		}
		$details_html .= '<a href="' . esc_url(fi_tag_url($tid, $gov)) . '" class="badge bg-primary text-decoration-none">' . esc_html($tnm) . '</a>';
	}
	$details_html .= '</div>';
}

$date_label    = (string) ($config['date_formatted'] ?? '');
$chamber_label = strtoupper((string) ($config['chamber_label'] ?? ''));

// ── New layout: Constitutional/Cast/Cost block ───────────────────────────────
$new_const_label = '';
$new_cast_label  = '';
$new_cost_label  = '';
$new_const_class = 'text-muted';
$new_cast_class  = 'text-muted';

if ($use_new_layout) {
	$const_val = strtoupper((string) $config['constitutional']);
	if ($const_val === 'Y') {
		$new_const_label = 'Yes';
		$new_const_class = 'text-success fw-semibold';
	} elseif ($const_val === 'N') {
		$new_const_label = 'No';
		$new_const_class = 'text-danger fw-semibold';
	}

	$cast_val = strtoupper((string) $config['cast']);
	if ($cast_val === 'Y') {
		$new_cast_label = 'Yes';
		$new_cast_class = 'text-success fw-semibold';
	} elseif ($cast_val === 'N') {
		$new_cast_label = 'No';
		$new_cast_class = 'text-danger fw-semibold';
	} elseif ($cast_val === 'X') {
		$new_cast_label = 'No Vote';
		$new_cast_class = 'text-muted';
	}

	$cost_raw = (string) ($config['cost_value'] ?? $config['cost_html'] ?? '');
	if ($cost_raw !== '') {
		$new_cost_label = wp_strip_all_tags($cost_raw);
	}

	// Outcome badge
	$outcome_val   = (string) $config['vote_outcome'];
	$outcome_label = '';
	$outcome_class = '';
	if ($outcome_val === '1') {
		$outcome_label = 'Passed';
		$outcome_class = 'bg-success-subtle text-success';
	} elseif ($outcome_val === '0') {
		$outcome_label = 'Rejected';
		$outcome_class = 'bg-danger-subtle text-danger';
	}

	// Vote counts
	$votes_for     = (string) ($config['votes_for'] ?? '');
	$votes_against = (string) ($config['votes_against'] ?? '');
	$has_counts    = $votes_for !== '' || $votes_against !== '';

	$citation = (string) ($config['citation'] ?? '');
}
?>

<?php if ($use_new_layout): ?>

<div class="card">
new


</div>

<?php else: ?>
	<div class="fi-vote-card <?php echo esc_attr($card_variant); ?><?php echo $interactive ? ' fi-vote-card--interactive' : ''; ?><?php echo ($use_new_layout || !empty($config['text'])) ? ' fi-vote-card--has-desc' : ''; ?>"
	id="vcard-<?php echo $vote_id; ?>"
	data-vote-id="<?php echo $vote_id; ?>"
	data-search-text="<?php echo esc_attr($config['search_text']); ?>"
	data-has-details="<?php echo $interactive ? '1' : '0'; ?>"
	<?php if ($interactive): ?>
	role="button" tabindex="0"
	aria-label="<?php echo esc_attr($config['title'] . ' — view details'); ?>"
	<?php endif; ?>>

	<?php /* ── LEGACY LAYOUT (no impact_summary) — unchanged ──────────────── */ ?>

	<div class="fi-vote-meta d-flex align-items-center flex-wrap gap-1 gap-sm-2 small text-muted ps-2">
		<?php if ($date_label !== ''): ?>
		<span><?php echo esc_html($date_label); ?></span>
		<?php endif; ?>
		<?php if ($chamber_label !== ''): ?>
		<span class="badge bg-light text-secondary text-uppercase fw-bold rounded-1"><?php echo esc_html($chamber_label); ?></span>
		<?php endif; ?>
		<?php if (!empty($config['bill_number'])): ?>
		<span class="text-muted"><?php echo esc_html($config['bill_number']); ?></span>
		<?php endif; ?>
		<?php if ($cost_badge !== ''): ?>
		<span class="badge rounded-pill fw-bold <?php echo esc_attr($cost_badge_bs); ?>"><?php echo esc_html($cost_badge); ?></span>
		<?php endif; ?>
	</div>

	<h3 class="fi-vote-title fw-bold lh-sm ps-2 mb-0 fs-6">
		<?php if (!empty($config['url_vote'])): ?>
			<a href="<?php echo esc_url($config['url_vote']); ?>" class="text-body text-decoration-none fi-vote-title-link">
				<?php echo esc_html($config['title']); ?>
			</a>
		<?php else: ?>
			<?php echo esc_html($config['title']); ?>
		<?php endif; ?>
	</h3>

	<?php if (!empty($config['text'])): ?>
	<p class="fi-vote-desc small text-muted ps-2 mb-0">
		<?php echo wp_kses_post($config['text']); ?>
		<?php if ($interactive): ?>
		<button type="button"
			class="badge bg-primary border-0 fi-vote-readmore ms-1"
			data-vote-id="<?php echo $vote_id; ?>">Read More</button>
		<?php endif; ?>
	</p>
	<?php endif; ?>

	<?php if ($config['show_cast']): ?>
	<div class="fi-vote-status d-flex align-items-center justify-content-center rounded-circle flex-shrink-0 fs-5 <?php echo esc_attr($status_bs); ?>" aria-hidden="true">
		<i class="bi <?php echo esc_attr($status_icon); ?>"></i>
	</div>
	<?php endif; ?>
	</div>

<?php endif; ?>

<?php if ($modal_mode === 'bootstrap' && $has_modal_details): ?>
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

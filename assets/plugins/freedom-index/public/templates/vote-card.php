<?php
/**
 * Vote Card Partial — compact legislator vote row (mockup: fi-legislator-1.html)
 *
 * Two layouts:
 *   NEW    (impact_summary present) — structured meta line + Constitutional/Cast/Cost block
 *   LEGACY (no impact_summary)      — existing description_short layout, kept separate until merged
 *
 * Always used in vote lists. Modal is always JS-driven ('page' mode).
 */

if (!defined('ABSPATH')) exit;

$args = $args ?? [];

$config = array_merge([
	'id'               => 0,
	'title'            => '',
	'text'             => '',       // description_short (legacy fallback)
	'text_more'        => '',
	'impact_summary'   => '',       // new — triggers new layout when non-empty
	// vote_outcome removed — derived from votes_for vs votes_against
	'votes_for'        => null,
	'votes_against'    => null,
	'citation'         => [],
	'cost_value'       => '',       // raw cost string for new layout
	'tags'             => [],
	'constitutional'   => '',
	'vote_format'      => [],
	'bill_number'      => '',
	'bill_url'         => '',
	'rollcall_number'  => '',
	'cost_html'        => '',
	'cost_sentence'    => '',
	'cost_badge'       => '',
	'cost_badge_class' => '',
	'date_formatted'   => '',
	'url_vote'         => '',
	'gov'              => '',
	'search_text'      => '',
	'show_cast'        => true,
	// modal_mode removed — always 'page' (JS-driven)
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
REMOVE FROM CONFIG:    [cost_html] => 
REMOVE FROM CONFIG:    [cost_sentence] => 
REMOVE FROM CONFIG:    [cost_badge] => 
REMOVE FROM CONFIG:    [cost_badge_class] => 
    [date_formatted] => 12/4/2025
    [url_vote] => http://localhost/votetruth/us/vote/3476/
    [gov] => US
    [search_text] => china-funded schools h.r. 1069 h.r. 1069, the “protect our kids act,” would prohibit federal education funds from being awarded to any elementary or secondary school that directly or indirectly receives support from the government of the people’s republic of china. this includes partnerships with confucius institutes or classrooms and other arrangements involving chinese government-backed funding, materials, or personnel.
the house passed h.r. 1069 on december 4, 2025 by a vote of 247 to 164 (roll call 313). we have assigned pluses to the yeas because article i, section 8 of the constitution does not permit congress to fund or legislate on education. furthermore, federal funds should not subsidize foreign governments’ propaganda, especially adversarial communist regimes with a documented record of censorship and influence operations. allowing china to finance curricula, personnel, or materials in american schools threatens national sovereignty, undermines parental authority, and exposes students to ideological indoctrination incompatible with american principles.
REMOVE FROM CONFIG:   [show_cast] => 1
    [modal_mode] => page
    [cast] => Y
    [chamber_label] => House
    [date_voted] => 2025-12-04 00:00:00
)

*/

$gov        = $config['gov'] ?: 'US';
$vote_id = (int) $config['id'];
$date_label    = (string) ($config['date_formatted'] ?? '');
$chamber_label = strtoupper((string) ($config['chamber_label'] ?? ''));

//What is the simplest way to determine the card variant and status icon?
$cast = $config['cast'];
$constitutional = $config['constitutional'];
$match = $cast === $constitutional;
$no_vote = $cast === 'X';

if ($no_vote) {
	$status_variant = 'neutral';
	$status_icon    = '<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-dash" viewBox="0 0 16 16">
  <path fill-rule="evenodd" d="M4 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 4 8"/>
</svg>';
	$status_icon_sm = '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-dash" viewBox="0 0 16 16">
  <path d="M4 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 4 8"/>
</svg>';
} elseif ($match) {
	$status_variant = 'good';
	$status_icon    = '<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-check-lg" viewBox="0 0 16 16">
  <path fill-rule="evenodd" d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425z"/>
</svg>';
	$status_icon_sm = '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-check-lg" viewBox="0 0 16 16">
  <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425z"/>
</svg>';
}else {
	$status_variant = 'bad';
	$status_icon    = '<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16">
  <path fill-rule="evenodd" d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
</svg>';
	$status_icon_sm = '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16">
  <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
</svg>';
}

//Constitutional label and class
$const_label = '';
$cast_label  = '';
$cost_label  = '';
$const_class = 'text-muted';
$cast_class  = 'text-muted';

if ($constitutional === 'Y') {
	$const_label = 'Yes';
	$const_class = 'text-success fw-bold';
} elseif ($constitutional === 'N') {
	$const_label = 'No';
	$const_class = 'text-danger fw-bold';
}

//Cast label and class
$cast_val = strtoupper((string) $config['cast']);
if ($cast_val === 'Y') {
	$cast_label = 'Yes';
	$cast_class = 'text-success fw-semibold';
} elseif ($cast_val === 'N') {
	$cast_label = 'No';
	$cast_class = 'text-danger fw-semibold';
} elseif ($cast_val === 'X') {
	$cast_label = 'None';
	$cast_class = 'text-muted';
}

//Cost label
$cost_value = '';
$cost_label = 'impact';
$cost_class = '';
$cost_raw = (string) ($config['cost_value'] ?? $config['cost_html'] ?? '');
if ($cost_raw !== '') {
	$cost = wp_strip_all_tags($cost_raw);
	//check if + prefix
	if (substr($cost, 0, 1) === '+') {
		$cost_value = '+$' . number_format_i18n((float) $cost, 2);
		$cost_label = 'benefit';
		$cost_class = 'text-success';
	}else{
		$cost_value = '-$' . number_format_i18n((float) $cost, 2);
		$cost_label = 'cost';
		$cost_class = 'text-danger';
	}
}


//Citation
$citation_text = '';
foreach ($config['citation'] as $citation) {
	$citation_text .= ' <a href="' . esc_url(fi_constitution_url($citation)) . '" class="text-muted" target="_blank" rel="noopener noreferrer">' . fi_constitution_citation($citation) . '</a>';
}

// ── Layout switch ───────────────────────────────
$structured = $config['impact_summary'] !== '';
$outcome_label = '';
$outcome_class = '';

if ($structured) {
	$config['text'] = $config['impact_summary'];

	// Vote counts
	$votes_for     = (string) ($config['votes_for'] ?? '');
	$votes_against = (string) ($config['votes_against'] ?? '');
	$has_counts    = $votes_for !== '' || $votes_against !== '';

	// Outcome derived from vote counts — no manual toggle needed
	if ($votes_for !== '' && $votes_against !== '') {
		if ((int) $votes_for > (int) $votes_against) {
			$outcome_label = 'Passed';
			$outcome_class = 'bg-success-subtle text-success';
		} elseif ((int) $votes_for < (int) $votes_against) {
			$outcome_label = 'Rejected';
			$outcome_class = 'bg-danger-subtle text-danger';
		}
	}
}
?>
<div class="card mb-3 fi-vote-card is-<?= esc_attr($status_variant); ?>" 
	id="vcard-<?= $vote_id; ?>"
	data-vote-id="<?= $vote_id; ?>"
	data-search-text="<?= esc_attr($config['search_text']); ?>"
	role="button" tabindex="0"
	aria-label="<?= esc_attr($config['title'] . ' — view details'); ?>">


	<div class="row">
		<div class="col-12 col-md-10 col-lg-11">
			<div class="card-body">
				<h3 class="fs-6 lh-1"><?= esc_html($config['title']); ?></h3>
				<div class="fs-8"><?= wp_kses_post($config['text']); ?> <button type="button" class="btn btn-link btn-sm lh-1 fw-bold" data-vote-id="<?= $vote_id; ?>">Read More</button></div>
				<div class="d-flex flex-wrap text-muted mt-1">
					<?php if ($const_label !== ''): ?>
					<span class="text-nowrap">Constitutional Vote: <span class="<?= esc_attr($const_class); ?>"><?= esc_html($const_label); ?></span></span>
					<?php endif; ?>
					<?php if ($cast_label !== ''): ?>
					<span class="text-nowrap ms-2">Vote Cast: <span class="<?= esc_attr($cast_class); ?>"><?= esc_html($cast_label); ?></span></span>
					<?php endif; ?>
					<?php if ($cost_value !== ''): ?>
					<span class="text-nowrap ms-md-2">Annual <?= esc_html($cost_label); ?> per household: <span class="fw-bold <?= esc_attr($cost_class); ?>"><?= esc_html($cost_value); ?></span></span>
					<?php endif; ?>
				</div>
				<?php if ($structured): ?>
				<div class="fi-vote-meta d-flex align-items-center flex-wrap small text-muted mt-1">
					<span class="nowrap">
						<?php //echo $chamber_label !== '' ? ' ' . esc_html($chamber_label) : ''; ?>
						<?php echo !empty($config['bill_number']) ? ' ' . esc_html($config['bill_number']) : ''; ?>
						<?php echo !empty($config['rollcall_number']) ? ' Roll Call ' . esc_html($config['rollcall_number']) : ''; ?>
					</span>
					<?php if ($outcome_label !== '' || $has_counts): ?>
					<span class="nowrap ms-2">
						<?php echo $outcome_label !== '' ? '<span class="fw-bold">' . esc_html($outcome_label) . '</span>' : ''; ?>
						<?php echo $has_counts ? '<span class="fw-bold ms-1">' . esc_html(trim($votes_for . ' to ' . $votes_against, ' to')) . '</span>' : ''; ?>
						<?php echo $date_label !== '' ? '<span class="ms-1">on ' . esc_html($date_label) . '</span>' : ''; ?>
					</span>
					<?php endif; ?>
					<span class="nowrap">
					<?php echo $citation_text != '' ? 'See U.S. Const. ' . $citation_text : ''; ?>
					</span>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<div class="col-12 col-md-2 col-lg-1">
			<div class="h-100 fi-vote-status d-none d-md-block d-md-flex align-items-center">
				<?= $status_icon; ?>
			</div>
			<div class="fi-vote-status d-md-none" style="height: 48px;">
				<?= $status_icon_sm; ?>
			</div>
		</div>
	</div>
</div>
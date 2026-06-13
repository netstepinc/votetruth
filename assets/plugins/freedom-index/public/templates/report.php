<?php if (!defined('ABSPATH')) exit;
/* Single Report Page Template
 * by Sam Mittelstaedt <smittelstaedt@jbs.org>
 */

global $fi_gov, $fi_report;

// Report is already loaded by the rewrite handler
$report = $fi_report;
// SEO Meta Tags
$gov = $fi_gov ?? $report->gov ?? 'US';
$gov_slug = strtolower($gov);
$gov_name = fi_gov_name($gov);

if (!$report) {
    status_header(404);
    get_template_part('404');
    exit;
}

// Decode payload to get report content and options
$payload = fi_report_decode_payload($report->payload_json ?? null);
//Check if pay load has report_pdf_url
$report_pdf_url = $payload['report_pdf_url'] ?? '';



$format = $report->format ?? 'scorecard';
if($format === 'freedomindex') {
	$report_format_text = 'Freedom Index';
}else{
	if($gov === 'US') {
		$report_format_text = 'Congressional Scorecard';
	}else{
		$report_format_text = 'Legislative Scorecard';
	}
}

// Legislator filter from pretty URL (fi_report_*); party is slug (r, d, etc.) like legislators
$filter_state = get_query_var('fi_report_state') ?: (isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '');
$filter_state = $filter_state ? strtoupper(sanitize_text_field($filter_state)) : '';
$party_slug_raw = get_query_var('fi_report_party') ? sanitize_text_field(get_query_var('fi_report_party')) : '';
$party_slug_lower = $party_slug_raw !== '' ? strtolower($party_slug_raw) : '';
$filter_party_slug = (fi_party_validate($party_slug_lower) ? $party_slug_lower : '');
$filter_party = $filter_party_slug !== '' ? fi_party_name($filter_party_slug) : '';
$filter_name = get_query_var('fi_report_search') ? rawurldecode(sanitize_text_field(get_query_var('fi_report_search'))) : '';

$current_url = fi_url_report($report->id, $gov_slug);
$intro_text = $payload['content'] ?? '';
$description = wp_trim_words(strip_tags($intro_text), 25, '...');
if (empty($description)) {
    $description = $report->title . ' - Vote report for ' . ($report->session_name ?? '') . '. View legislator scores and voting records.';
}

$vote_start = (int)($payload['vote_start'] ?? 1);

// Get vote IDs for House and Senate
$votes_h_ids = $payload['votes_h'] ?? [];
$votes_s_ids = $payload['votes_s'] ?? [];
$votes_h_order = $payload['votes_h_order'] ?? [];
$votes_s_order = $payload['votes_s_order'] ?? [];

// Determine which chambers have votes
$has_rep = !empty($votes_h_ids);
$has_sen = !empty($votes_s_ids);

// Get position from query var (from rewrite rule)
$chamber = get_query_var('fi_chamber') ?: '';

// If no position specified, determine from available votes
if (empty($chamber)) {
    if ($has_rep && $has_sen) {
        $chamber = 'S'; // Default to Senate if both exist
    } elseif ($has_rep) {
        $chamber = 'H';
    } elseif ($has_sen) {
        $chamber = 'S';
    } else {
        $chamber = 'H'; // Fallback
    }
}
$chamber_label = fi_chamber_label($gov, $chamber);

// Get votes for selected chamber
$vote_ids = [];
$vote_order = [];
if ($chamber === 'H' && $has_rep) {
    $vote_ids = $votes_h_ids;
    $vote_order = $votes_h_order;
} elseif ($chamber === 'S' && $has_sen) {
    $vote_ids = $votes_s_ids;
    $vote_order = $votes_s_order;
}


// Get vote objects and apply manual ordering if specified
$votes = [];
if (!empty($vote_ids)) {
    foreach ($vote_ids as $vote_id) {
        $vote = fi_vote_get($vote_id);
        if ($vote) {
            $votes[] = $vote;
        }
    }
    
    // Apply manual ordering if specified
    if (!empty($vote_order)) {
        $ordered_votes = [];
        foreach ($vote_order as $ordered_id) {
            foreach ($votes as $key => $vote) {
                if ($vote->id == $ordered_id) {
                    $ordered_votes[] = $vote;
                    unset($votes[$key]);
                    break;
                }
            }
        }
        // Add any remaining votes that weren't in the order array
        $votes = array_merge($ordered_votes, array_values($votes));
    }
    
    // Batch load tags for all votes to prevent N+1 queries
    $tags_by_vote = [];
    if (!empty($votes)) {
        $all_vote_ids = array_map(static fn($vote) => (int) $vote->id, $votes);
        $tag_rows = fi_vote_tags_get_tags_by_vote_ids($all_vote_ids);
        foreach ($tag_rows as $row) {
            $tags_by_vote[$row->vote_id][] = (object) [
                'id' => $row->tag_id,
                'name' => $row->tag_name,
                'slug' => $row->tag_slug,
            ];
        }
    }
}


//Report Title
if(strpos($report->title, 'Freedom Index') !== false) {
	$report_title = $report->title;
	$gov_title = 'Congressional';
}else{
	if($gov === 'US') {
		$gov_title = 'Congressional';
	}else{
		$gov_title = 'Legislative';
	}
	$report_title = fi_report_title_reformat($gov, $report->title);
}

fi_seo_tags([
    'title' => $report_title . ' | Vote Report | Freedom Index',
    'description' => $description,
    'canonical' => $current_url,
    'robots' => 'index, follow',
    'og' => [
        'og:title' => $report_title . ' | Vote Report',
        'og:description' => $description,
        'og:url' => $current_url,
        'og:type' => 'article',
    ],
    'twitter' => [
        'twitter:card' => 'summary',
        'twitter:title' => $report_title,
        'twitter:description' => $description,
    ],
]);
get_header();

$header_args = [
    'title' => $report_title ?? 'Report',
	'gov' => $gov,
	'gov_name' => $gov_name,
	'pretext' => '', //$gov_title . ' Vote Report',
    'id' => 'fi-report',
    'class' => 'fi-report-page',
	'pdf_url' => $report_pdf_url,
    'breadcrumbs' => [
        ['text' => $gov_name, 'url' => home_url('/' . strtolower($gov) . '/')],
        ['text' => 'Reports', 'url' => home_url('/' . strtolower($gov) . '/reports/')],
        ['text' => $report_title ?? 'Report'],
    ],
    'breadcrumbs_args' => [
        'template_name' => 'report',
		'buttons' => [
			['text' => 'Legislators', 'url' => home_url($gov_slug . '/legislators/'),'class' => 'btn-outline-success d-none d-lg-block'],
			['text' => 'Votes', 'url' => home_url($gov_slug . '/votes/'),'class' => 'btn-outline-primary d-none d-lg-block'],
			['text' => 'Reports', 'url' => home_url($gov_slug . '/reports/'),'class' => 'btn-outline-primary d-none d-lg-block'],
		],
    ],
	'filter_enabled' => false,
];

fi_get_public_template('partials/template-header', $header_args);
?>
<div class="row">
	<div class="col-12">
		<div class="card shadow mb-4">
			<div class="card-body">
<?php if($format == 'freedomindex'){ fi_get_public_template('partials/report-tna-promo'); }?>

<!--
				<p>The <?= $report_format_text;?> is a nationwide educational program of The <a href="https://jbs.org" target="_blank">John Birch Society</a> based on the U.S. Constitution.</p>
				<p>Its purpose is to create an informed electorate on how members of Congress are voting.</p>
				<p>This report is nonpartisan; it does not promote any candidate or political party. Bills are selected for their constitutional implications and cost to the taxpayers.</p>
-->
				<?= wp_kses_post(wpautop($intro_text)); ?>
				<p class="fw-bold">Share this <?= $report_format_text;?> in your district to inform people about the constitutionality of their elected officials' votes.</p>
<!--			<p class="small text-muted">U.S. Constitution, Amendment I --- 11 C.F.R. §114(4)(c)(4) --- 616 F.2d 45 (2d Cir. 1980)</p> -->
			</div>
		</div>

		<!-- Chamber Switcher (if both chambers have votes) -->
		<?php if ($has_rep && $has_sen): 
			// Pretty path: preserve legislator filter when switching chamber (party = slug)
			$filter_suffix = '';
			if (!empty($filter_state)) { $filter_suffix .= '/state/' . strtolower($filter_state); }
			if (!empty($filter_party_slug)) { $filter_suffix .= '/party/' . $filter_party_slug; }
			if (!empty($filter_name)) { $filter_suffix .= '/search/' . rawurlencode($filter_name); }
			$sen_url = home_url($gov_slug . '/report/' . (int) $report->id . '/chamber/S' . $filter_suffix . '/');
			$rep_url = home_url($gov_slug . '/report/' . (int) $report->id . '/chamber/H' . $filter_suffix . '/');
		?>
			<div class="row mb-4 py-1">
				<div class="col-6 col-md-4 mx-auto">
					<a class="btn btn-sm shadow fs-5 fw-bold w-100 <?php echo ($chamber === 'S' ? 'btn-primary' : 'btn-outline-primary'); ?>" 
						href="<?php echo esc_url($sen_url); ?>">
						<?php echo esc_html(fi_chamber_label($gov, 'S')); ?><span class="d-none d-md-inline"> Votes</span>
					</a>
				</div>
				<div class="col-6 col-md-4 mx-auto">
					<a class="btn btn-sm shadow fs-5 fw-bold w-100 <?php echo ($chamber === 'H' ? 'btn-primary' : 'btn-outline-primary'); ?>" 
						href="<?php echo esc_url($rep_url); ?>">
						<?php echo esc_html(fi_chamber_label($gov, 'H')); ?><span class="d-none d-md-inline"> Votes</span>
					</a>
				</div>
			</div>
		<?php 
		endif;
		
		if (!empty($chamber_label)){
			echo '<h2 class="text-center fs-1">' . esc_html($chamber_label) . ' Votes</h2>';
		}
		
		//Votes Display
		if (!empty($votes)): 
			// Display votes in a grid
			//echo '<div class="row g-4 mb-4">';
			echo '<div class="accordion shadow" id="accordionReportVotes">';
			$a=0;
			$v = $vote_start - 1;
			foreach ($votes as $vote) {
				$v++;
				$a++;
				$vote_id = $vote->id;
				$vote_meta = fi_vote_decode_meta($vote);
				$constitutional = $vote->constitutional ?? '';
				
				// Get descriptions based on format
				$descriptions = fi_vote_get_description($vote_meta);
				$description_short = $descriptions['short'] ?? '';
				$description_long = $descriptions['long'] ?? '';

				//Get Image: Default proportion is 1800x1200 = 3:2 | Display size is 300x200
				$img_tag = '';
				$attachment_id = $vote_meta['image_id'] ?? null;
				if($attachment_id) {
					$img_html = jis_get_attachment_image($attachment_id,[300,200],true,['retina' => true,'alt' => $vote->title ?? '','class' => 'img-fluid vote-image','id' => 'vote-image-'.$attachment_id]);
					$caption = wp_strip_all_tags(get_post_field('post_excerpt', $attachment_id));
					if($caption != ''){
						$img_html .= '<div class="text-muted small">' . $caption . '</div>';
					}
					$desc_long_html = '<div class="row">';
					$desc_long_html .= '<div class="col-12 col-md-7 col-lg-8">' . $description_long . '</div>';
					$desc_long_html .= '<div class="col-12 col-md-5 col-lg-4 pb-3">' . $img_html . '</div>';
					$desc_long_html .= '</div>';
					$description_long = $desc_long_html;
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
				
				// Get vote format (constitutional position only, no cast for report view)
				$vote_format = fi_vote_format([
					'constitutional' => $constitutional,
					'format' => 'full'
				]);
				
				// Format cost
				$cost_html = '';
				if (!empty($vote_meta['cost']) && $vote_meta['cost'] != 0) {
					$cost = fi_vote_cost_format($vote_meta['cost']);
					$cost_html = $cost['html'] ?? '';
				}
				
				// Get bill info
				$bill_number = $vote->bill_number ?? '';
				$bill_url = $vote_meta['url_bill'] ?? '';
				
				// Build vote URL
				$url_vote = fi_url_vote($gov, $vote->id ?? 0);
				
				// Get tags from batch-loaded array
				$tags = $tags_by_vote[$vote->id] ?? [];
				
				// Build title with number
				$vote_number = $v;
				if(isset($vote_meta['vote_start'])){
					$vote_number = (int) $vote_meta['vote_start'] + $v;
				}
				$title = $vote_number . '. ' . ($vote->title ?? $bill_number ?? 'Untitled Vote');
				
				// Build search text
				$search_text = strtolower($title . ' ' . $bill_number . ' ' . strip_tags($description_short ?? ''));


?>
<div class="accordion-item border-bottom border-primary">
	<h2 class="accordion-header">
		<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapse<?= $vote_id;?>" aria-expanded="false" aria-controls="flush-collapse<?= $vote_id;?>">
			<span class="fs-5 ff-h lh-1 fw-bold"><?= $title;?></span>
		</button>
	</h2>
	<div id="flush-collapse<?= $vote_id;?>" class="accordion-collapse collapse<?= $a == 1 ? ' show' : '';?>" data-bs-parent="#accordionReportVotes">
		<div class="accordion-body">
			<div class="row">
				<div class="col-12"><?= $description_long;?></div>
			</div>
			<div class="row border-top">
				<div class="col-6 d-none d-md-block p-0">
					<div class="row">
						<div class="col-4 text-center py-2 fi-vote-card-date">
							<div class="fs-7 ff-h lh-1 fw-bold"><?= $date_formatted;?></div>
							<small class="text-muted ff-h">Vote Date</small>
						</div>

						<div class="col-4 text-center py-2 fi-vote-card-link">
							<a href="<?= esc_url($url_vote);?>" rel="noopener noreferrer" class="d-block fs-7 ff-h lh-1 fw-bold">More</a>
							<small class="text-muted fw-bold ff-h">Info</small>
						</div>

						<div class="col-4 text-center py-2 fi-vote-card-bill-url">
							<?php if (!empty($bill_url)): ?>
								<a href="<?php echo esc_url($bill_url); ?>" rel="noopener noreferrer" class="d-block fs-7 ff-h lh-1 fw-bold text-decoration-none">View Bill</a>
								<small class="text-muted fw-bold ff-h">Vote Text</small>
							<?php else: ?>
							<!-- No bill URL -->
							<?php endif; ?>
						</div>
					</div>
				</div>
				<div class="col-4 col-lg-2 text-center py-2 border-md-top border-lg-0 fi-vote-card-cost">
					<?php if (!empty($config['cost_html'])): ?>
					<div class="fs-7 ff-h lh-1 fw-bold"><?php echo wp_kses_post($config['cost_html']); ?></div>
					<small class="text-muted fw-bold ff-h">Your Cost</small>
					<?php endif; ?>
				</div>
				<div class="col-4 col-lg-2 text-center py-2 border-md-top border-lg-0 fi-vote-card-good">
					<div class="fs-7 ff-h lh-1 fw-bold <?php echo esc_attr($vote_format['vote_class'] ?? ''); ?>">
						<i class="<?php echo esc_attr($vote_format['vote_class_icon'] ?? ''); ?> me-1"></i>
						<?php echo esc_html($vote_format['vote_text'] ?? ''); ?>
					</div>
					<small class="text-muted fw-bold ff-h">Constitutional</small>
				</div>
				<div class="col-4 col-lg-2 text-center py-2 border-md-top border-lg-0 fi-vote-card-good">
					<div class="fs-7 ff-h lh-1 fw-bold">
						<?php echo esc_html($chamber_label); ?>
					</div>
					<small class="text-muted fw-bold ff-h">Chamber</small>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
				// Build config for vote-card
				/*
				fi_get_public_template('partials/vote-card', [
					'id' => $vote->id,
					'title' => $title,
					'text' => $description_long, //$description_short,
					'text_more' => '', //$description_long,
					'tags' => $tags,
					'date_formatted' => $date_formatted,
					'vote_format' => $vote_format,
					'bill_number' => $bill_number,
					'bill_url' => $bill_url,
					'cost_html' => $cost_html,
					'url_vote' => $url_vote,
					'search_text' => $search_text,
					'show_cast' => false, // No individual legislator cast in report view
					'show_link' => true,
					'collapse' => true,
					'collapse_text' => 'Read why it matters',
					'collapse_class' => '',
					'footer_class_col' => 'col-4 col-lg-2 text-center',
				]);
				*/
			}

			echo '</div>'; //row
		endif;


		// Legislator list: shared data built once; filter and grid/table receive args.
		if (!empty($votes) && !empty($report->session_id)):
			$report_base_path = '/' . $gov_slug . '/report/' . (int) $report->id . '/chamber/' . $chamber.'/';
			$leg_data = fi_report_legislators_build_data(
				$report->session_id,
				$chamber,
				$gov,
				$votes,
				$filter_party_slug ?? ''
			);

			if (!empty($leg_data['empty'])) {
				echo '<p class="text-muted">No legislators found for this session and chamber.</p>';
			} else {

				/* Add Grid/Table toggle; grid is default so omit view=grid from URL */
				$view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'grid';
				$other_view = ($view === 'grid') ? 'table' : 'grid';
				$other_label = ($other_view === 'grid') ? 'Grid' : 'Table';
				$other_icon = ($other_view === 'grid') ? 'fas fa-th' : 'fas fa-table';
				$other_url = home_url($report_base_path) . ($other_view === 'table' ? '?view=table' : '') . '#legislators';
				?>
				<div id="legislators" class="row mb-4 py-2 text-center mt-5 mb-3">
					<a class="btn btn-sm btn-outline-primary col-6 col-md-4 col-lg-3 col-xl-2 mx-auto fw-bold" href="<?php echo esc_url($other_url); ?>"><i class="<?php echo esc_attr($other_icon); ?> me-2"></i>Change to <?php echo esc_html($other_label); ?> View</a>
				</div>
				<?php
				fi_get_public_template('partials/report-legislators-filter', [
					'session_id' => $report->session_id,
					'chamber' => $chamber,
					'gov' => $gov,
					'filter_state' => $filter_state,
					'filter_party' => $filter_party,
					'filter_party_slug' => $filter_party_slug,
					'filter_name' => $filter_name,
					'report_base_path' => $report_base_path,
					'states' => $leg_data['states'],
					'parties' => $leg_data['parties'],
					'is_federal' => $leg_data['is_federal'],
				]);

				echo '<div id="fi-report-legislators-results">';
				if ($view === 'table') {
					fi_get_public_template('partials/report-legislators-table', [
						'legislator_data' => $leg_data['legislator_data'],
						'votes' => $votes,
						'gov' => $gov,
						'parties' => $leg_data['parties'],
						'vote_start' => $vote_start,
					]);
				} else {
					fi_get_public_template('partials/report-legislators-grid', [
						'legislator_data' => $leg_data['legislator_data'],
						'gov' => $gov,
						'vote_start' => $vote_start,
					]);
				}
				echo '</div>';
			}
		endif; 

		fi_get_public_template('partials/report-footer', [
			'format' => $format
		]);
		?>
	</div>
</div>
<?php
fi_get_public_template('partials/template-footer');
get_footer();
<?php
if (!defined('ABSPATH')) exit;

/* Single Vote Page Template
 * Modeled after V2 vote-single.php but using vote meta data instead of post data
 * by Sam Mittelstaedt <smittelstaedt@jbs.org>
*/

// Get global variables set by rewrite handler
global $fi_vote, $fi_gov;

$vote = $fi_vote ?? null;
$gov = $fi_gov ?? 'US';
$gov_name = fi_gov_name($gov);
$gov_slug = strtolower($gov);

if (!$vote) {
	// If vote not found, show 404
	status_header(404);
	get_template_part('404');
	exit;
}

// Get vote data
$ID = $vote->id;
$session_id = $vote->session_id;
$session_name = $vote->session_name;
//SESSIONSLUG: Remove $session_slug variable - no longer needed, session_id is sufficient

$chamber = $vote->chamber;
$chamber_label = fi_chamber_label($gov, $chamber); // Senate or House

$constitutional = $vote->constitutional;
$constitutional_evaluation_text = "Constitutional Vote: ";
if($constitutional == 'Y'){
	$constitutional_text = 'Constitutional';	
	$constitutional_evaluation_text .= "Yes";
	$constitutional_position_class = 'bg-success text-white';
	//$constitutional_position_badge = '<span class="badge bg-success">Yes</span>';
	$commentary_title = 'A Vote for Freedom';
	$constitutional_icon = '<i class="bi bi-hand-thumbs-up"></i>';
	$constitutional_icon_color = '<i class="bi bi-hand-thumbs-up text-success"></i>';
} else {
	$constitutional_text = 'Unconstitutional';
	$constitutional_evaluation_text .= "No";
	$constitutional_position_class = 'bg-danger text-white';
	//$constitutional_position_badge = '<span class="badge bg-danger">No</span>';
	$commentary_title = 'A Vote Against Freedom';
	$constitutional_icon = '<i class="bi bi-hand-thumbs-down"></i>';
	$constitutional_icon_color = '<i class="bi bi-hand-thumbs-down text-danger"></i>';
}

$bill_number = $vote->bill_number ?? '';
$title = $vote->title ?? '';
$slug = $vote->slug ?? '';
$page_title = $title;

$page_title = $constitutional_icon_color . ' ' . $title;

$rollcall_number = $vote->rollcall_number;

$chambers = fi_chamber_info($gov);
$chamber = strtoupper((string) ($fi_chamber ?? ''));
if (!in_array($chamber, ['H', 'S'], true)) {
	$chamber = '';
}

$vote_date = $vote->date_voted ?? '';
$vote_date_formatted = $vote_date ? date('n/j/Y', strtotime($vote_date)) : ''; // 11/18/2014 NOT November 18, 2014

// Get vote meta data
$vote_meta = fi_vote_decode_meta($vote);
// Get vote description (excerpt) - use description_short as excerpt equivalent
$descriptions = fi_vote_get_description($vote_meta);

$text = $descriptions['short'] ?? '';
$text_more = $descriptions['long'] ?? '';

// Get meta fields
$subtitle = $vote_meta['subtitle'] ?? '';

// Format cost HTML
$cost = !empty($vote_meta['cost']) ? fi_vote_cost_format($vote_meta['cost']) : ['html' => ''];
$cost_html = $cost['html'] ?? '';

$url_source = $vote_meta['url_source'] ?? $vote_meta['url'] ?? '';

$url_bill = $vote_meta['url_bill'] ?? '';

// Get roll call records
$rollcalls = fi_rollcalls_get_by_vote($vote->id);

$tags = fi_vote_tags_get_tags_by_vote($vote->id);

// Process vote data for vote-card partial
$report_format = 'scorecard';

// Get vote format (constitutional only, no cast for detail page)
$vote_format = fi_vote_format([
	'constitutional' => $constitutional,
	'format' => 'full'
]);

// Build URLs
$url_vote = fi_url_vote($gov, $vote->id);

// Build search text
$search_description = $descriptions['long'] ?? ($descriptions['medium'] ?? $descriptions['short']);
$search_text = strtolower($title . ' ' . $bill_number . ' ' . strip_tags($description ?? ''));


// SEO Meta Tags
$seo_title = $chamber_label . ' Vote ' . $bill_number . ' ' . $title;
$seo_description =  wp_trim_words(strip_tags(($descriptions['short'] ?? $descriptions['medium'] ?? $descriptions['long'])), 25, '...');

fi_seo_tags([
    'title' => $seo_title . ' | Freedom Index',
    'description' => $seo_description,
    'canonical' => $url_vote,
    'robots' => 'index, follow',
    'og' => [
        'og:title' => $seo_title,
        'og:description' => $seo_description,
        'og:url' => $url_vote,
        'og:type' => 'article',
    ],
    'twitter' => [
        'twitter:card' => 'summary',
        'twitter:title' => $seo_title,
        'twitter:description' => $seo_description,
    ],
]);

get_header();

$header_args = [
	'title' => $page_title ?? 'Vote Details',
	'pretext' => $bill_number ?? '',
	'gov' => $gov,
	'gov_name' => $gov_name,
	'id' => 'fi-vote',
	'class' => 'fi-vote-page',
	'breadcrumbs' => [
		['text' => fi_gov_name($gov), 'url' => home_url(strtolower($gov) . '/')],
		['text' => 'Votes', 'url' => home_url(strtolower($gov) . '/votes/')],
		['text' => $title ?? 'Vote'],
	],
	'breadcrumbs_args' => [
		'template_name' => 'vote',
		'buttons' => [
			['text' => 'Legislators', 'url' => home_url($gov_slug . '/legislators/'),'class' => 'btn-outline-success d-none d-lg-block'],
			['text' => 'Votes', 'url' => home_url($gov_slug . '/votes/'),'class' => 'btn-outline-primary d-none d-lg-block'],
			['text' => 'Reports', 'url' => home_url($gov_slug . '/reports/'),'class' => 'btn-outline-primary d-none d-lg-block'],
		],
	],
];

fi_get_template('partials/template-header', $header_args);

/*
if(get_current_user_id() == 1){
	echo '<textarea style="width:100%; height:200px;">'; print_r($vote_meta); echo '</textarea>';
	echo '<textarea style="width:100%; height:200px;">'; print_r($descriptions); echo '</textarea>';
}
*/
?>
<div class="row">
	<!-- Main Content -->
	<div class="col-md-8">
		<article class="fi-vote-single">
			<?php
			// Prepare vote card data
			$vote_data = [
				'id' => $ID,
				'gov' => $gov,
				'slug' => $slug,
				'title' => $constitutional_evaluation_text,
				'text' => $text,
				'bill_number' => $bill_number,
				'constitutional' => $constitutional,
				'date_voted' => $vote_date,
				'date_formatted' => $vote_date_formatted,
				'vote_format' => $vote_format,
				'chamber' => $vote->chamber,
				'chamber_label' => $chambers[$vote->chamber]['chamber'] ?? '',
				'bill_url' => $url_bill,
				'cost_html' => $cost_html,
				'url_vote' => $url_vote,
				'search_text' => $search_text,
				'report_format' => $report_format,
				// For vote detail page: no cast, no link (we're already on the page)
				'show_cast' => false,
				'show_link' => false,
				// Custom header for vote detail page
				'header_class' => 'rounded-top-4 ' . $constitutional_position_class,
				'header_title_class' => 'fs-5',
				'card_class' => 'mb-4 rounded-4 shadow-sm',
				'body_class' => 'lead mb-0 p-3',
				'body_text_class' => '', // Remove 'small' class for detail page
				'footer_class' => 'bg-light p-0 rounded-bottom-4',
				'footer_class_col' => 'col-4 text-center',
			];
			
			fi_get_template('partials/vote-card', $vote_data);
			?>

			<!-- Vote Tags -->
			<?php if (!empty($tags)): ?>
			<div class="pt-2 pb-4">
				<h2>Related Votes</h2>
				<div class="d-flex flex-wrap gap-2">
					<?php foreach ($tags as $tag): 
						// Build URL to votes page filtered by tag using helper function
						$tag_url = fi_tag_url($tag->slug ?? '', strtolower($gov));
					?>
						<a href="<?php echo esc_url($tag_url); ?>" class="btn btn-sm btn-outline-primary">
							<?php echo esc_html($tag->name ?? ''); ?>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Vote Content -->
			<?php if (!empty($text_more)): ?>
			<div class="card rounded-4 shadow-sm mb-5 p-3">
				<h2><?php echo esc_html($commentary_title); ?></h2>
				<div class="entry-content post-content">
					<?php echo wp_kses_post(wpautop($text_more)); ?>
				</div>
			</div>
			<?php endif; ?>
		</article>
	</div>

	<!-- Sidebar - Roll Call -->
	<div class="col-md-4">
		<?php if (!empty($rollcalls)):
			//Tally votes and display at the top of the card in a summary table
			$tally = [
				'Y' => 0,
				'N' => 0,
				'X' => 0,
				'P' => 0,
			];

			ob_start();
			foreach ($rollcalls as $rc): 
				$legislator_name = $rc->display_name ? $rc->display_name : $rc->first_name . ' ' . $rc->last_name;
				$legislator_url = !empty($rc->legislator_id) ? fi_get_legislator_url($rc->legislator_id) : '';
				$cast = fi_rollcall_cast_normalize((string) ($rc->cast ?? ''));
				$vote_format = fi_vote_format_badge([
					'cast' => $cast,
					'constitutional' => $constitutional
				]);
				if (!isset($tally[$cast])) {
					$cast = 'X';
				}
				$tally[$cast]++;
			?>
				<li class="list-group-item d-flex justify-content-between align-items-center px-2 py-2">
					<span>
						<?php if ($legislator_url): ?>
							<a href="<?php echo esc_url($legislator_url); ?>" class="text-decoration-none fw-bold">
								<?php echo esc_html($legislator_name); ?>
							</a>
						<?php else: ?>
							<strong><?php echo esc_html($legislator_name); ?></strong>
						<?php endif; ?>
					</span>
					<span class="ms-3"><?php echo $vote_format; ?></span>
				</li>
			<?php 
			endforeach;
			$rollcall_list = ob_get_clean();
			?>
			<div class="card mb-4 rounded-4 shadow-sm">
				<div class="card-header rounded-top-4">
					<h5 class="card-title fs-5 mb-0">Rollcall Votes</h5>
				</div>
				<div id="fi-vote-rollcall" class="card-body">
					<!-- Summary Table -->
					<table class="table table-bordered table-sm mb-2">
						<thead>
							<tr>
								<th>Name</th>
								<th class="text-center">Vote</th>
							</tr>
						</thead>
						<tbody>
					<?php 
					foreach ($tally as $cast => $count){
						if($count > 0){
							$cast_label = fi_vote_format_text(['cast' => $cast]);
							echo '<tr><td class="fw-bold lead">' . $cast_label . '</td><td class="text-center lead fw-bold">' . $count . '</td></tr>';
						}
					}
					?>
						</tbody>
						<tfoot>
							<tr>
								<td class="fw-bold lead">Total</td>
								<td class="text-center lead fw-bold"><?php echo array_sum($tally); ?></td>
							</tr>
						</tfoot>
					</table>
					<ul class="list-group list-group-flush">
						<?php echo wp_kses_post($rollcall_list); ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
<?php 
fi_get_template('partials/template-footer');
get_footer();

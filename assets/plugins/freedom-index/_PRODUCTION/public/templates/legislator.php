<?php
if (!defined('ABSPATH')) exit;

$legislator_id = (int)fi_public_get_legislator_id();
$current_session_id = fi_public_get_legislator_session_id();
$current_report_id = fi_public_get_legislator_report_id();
$current_tag_id = fi_public_get_legislator_tag_id();

// Get legislator data - REPLACE WP METHOD WITH API METHOD
$data = $legislator_id ? fi_legislator_get($legislator_id) : null;

if (empty($data)) {
    status_header(404);
    echo '<h1>Legislator Not Found</h1>';
    get_footer();
    exit;
}


// Check if legislator has valid session data - redirect to legislators list if not
if (empty($data->gov)) {
    $redirect_url = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : home_url('/');
    wp_safe_redirect($redirect_url);
    exit;
}

$legislator_name = $data->display_name ?? '';
$chamber_label = $data->chamber_label ?? '';
$chamber_title = $data->chamber_title ?? '';
$party_name = $data->party_name ?? '';
$score = $data->freedom_score ?? null;
$score_text = $score !== null ? $score . '%' : 'N/A';

// Use actual current URL (including session/report/issue parameters) for og:url
// This ensures social media shares link to the exact page being viewed, not just the base legislator URL
$current_url = home_url($_SERVER['REQUEST_URI']);
// Ensure URL ends with / for consistency
if (!str_ends_with($current_url, '/')) {
	$current_url .= '/';
}
/* Canonical URL
Our legislator pages generate dozens of different URLs based on session, report, and issue parameters, but that's dilluting the SEO value of the page.
The canonical URL is the original legislator page URL without any session, report, or issue parameters, and is used by search engines to determine the "true" URL of the page.
Social media shares must be able to share the exact page vairant being viewed, not just the base legislator page.
home / legislator / legislator-id
*/
$canonical_url = home_url('/legislator/' . $legislator_id);




$description = $legislator_name . ' (' . $chamber_label . ', ' . $party_name . ') - Freedom Score: ' . $score_text . '. View voting record, scores, and reports.';

$gov = $data->gov;
$gov_name = fi_gov_name($gov);
$gov_slug = strtolower($gov);
$meta = is_array($data->meta ?? null) ? $data->meta : [];
$sessions = $data->sessions ?? [];

// Basic info
$state_name = ($gov == 'US' && !empty($data->state_name)) ? $data->state_name . ' ' : '';

// US Senators don't have districts - only check if district ID exists
$district = null;
$district_name = '';
if (!empty($data->district) && is_numeric($data->district)) {
	$district = fi_district_get((int) $data->district);
	$district_name = !empty($district->name_short) ? $district->name_short . ' District' : '';
}
$websites = fi_legislator_websites($data);

// Status
$is_active = true; // Could check meta['status'] if available
$status_text = $is_active ? 'Active Legislator' : 'Former Legislator';

// Contact info
$contact = [
    'website' => $meta['website'] ?? '',
    'phone' => $meta['phone'] ?? '',
    'email' => $meta['email'] ?? '',
    'office' => $meta['office_address'] ?? $meta['office'] ?? '',
    'local' => $meta['local'] ?? '',
    'townname' => $meta['townname'] ?? '',
];

// Committees (for senators)
$committees = [];
if ($data->chamber === 'S' && !empty($meta['committee'])) {
    $committees_raw = is_string($meta['committee']) ? json_decode($meta['committee'], true) : $meta['committee'];
    if (is_array($committees_raw)) {
        $committees = array_filter($committees_raw, function($c) {
            return strlen($c) > 8;
        });
    }
}

// Image
$image_url = $data->image_id ? wp_get_attachment_url($data->image_id) : '';
$image_html = fi_legislator_image(
	$data->image_id, 
	$data->session_image_id ?? null,
	[
		'size' => [200,250],
		'crop' => true,
		'alt' => $data->display_name, 
		'class' => 'img-fluid rounded-4 shadow'
	]
);

// Get vote data
$legislator_votes = [];
$legislator_tags = [];
$tag_votes = [];

if (!empty($data->id) && !empty($data->chamber)) {
    if ($current_tag_id) {
        $tag_votes = fi_legislator_votes_get_by_tag($data->id, $data->chamber, $current_tag_id);
    } else {
        $legislator_votes = fi_legislator_votes_get($data->id, $data->chamber);
    }
    $legislator_tags = fi_legislator_tags_get($data->id, $data->chamber);
}

// Build vote scores table
$vote_scores = [];
$score_freedom = $data->freedom_score ?? null;
if ($score_freedom !== null) {
    $vote_scores[] = [
        'score' => $score_freedom,
        'label' => 'Freedom Score',
        'is_freedom' => true
    ];
}

// Sort sessions by date descending (most recent first)
$sorted_sessions = $sessions;
usort($sorted_sessions, function($a, $b) {
    $date_a = !empty($a->date_start) ? strtotime($a->date_start) : 0;
    $date_b = !empty($b->date_start) ? strtotime($b->date_start) : 0;
    return $date_b - $date_a; // Descending order
});

foreach ($sorted_sessions as $session) {
    if (isset($session->score) && $session->score !== null) {
        $vote_scores[] = [
            'score' => $session->score,
            'label' => $session->session_name ?? '',
            'session_id' => $session->session_id ?? null,
            'url' => $session->session_id ? add_query_arg(['session' => $session->session_id], home_url('/legislator/' . $legislator_id . '/')) : ''
        ];
    }
}

// SEO Meta Tags - Microsoft Teams also recognizes og: properties.
fi_seo_tags([
	'title' => $legislator_name . ' | ' . $chamber_label . ' | Freedom Index',
	'description' => $description,
	'canonical' => $canonical_url,
	'robots' => 'index, follow',
	'og' => [
		'og:title'         => $legislator_name . ' | ' . $chamber_label . ' | Freedom Index', // Facebook/Teams title
		'og:description'   => $description, // Facebook/Teams description
		'og:url'           => $current_url, // Facebook/Teams url
		'og:type'          => 'profile',   // Facebook/Teams type
		'og:image'         => $image_url,  // Facebook/Teams main image
		'og:image:alt'     => $legislator_name, // Facebook/Teams image alt text
		//'og:image:width'   => '1200', // Optional: recommended width
		//'og:image:height'  => '630',  // Optional: recommended height
	],
	'twitter' => [
		'twitter:card'        => 'summary',
		'twitter:title'       => $legislator_name . ' | ' . $chamber_label,
		'twitter:description' => $description,
		'twitter:image'       => $image_url,
	],
]);

get_header();

$header_args = [
	'title' => '',
	'gov' => $gov,
	'gov_name' => $gov_name,
    'pretext' => '', //$gov_name . ' Legislator',
	'url_back' => home_url('/' . strtolower($data->gov) . '/legislators/'),
	'url_back_text' => 'Back to Legislators',
    'id' => 'fi-legislator',
    'class' => '',
    'breadcrumbs' => [
        ['text' => $data->gov_name, 'url' => home_url('/' . strtolower($data->gov) . '/')],
        ['text' => 'Legislators', 'url' => home_url('/' . strtolower($data->gov) . '/legislators/')],
        ['text' => $data->display_name, 'class' => 'fw-bold'],
    ],
    'filter_enabled' => false,
    'breadcrumbs_args' => [
        'template_name' => 'legislator',
		'buttons' => [
			['text' => 'Legislators', 'url' => home_url($gov_slug . '/legislators/'),'class' => 'btn-outline-success d-none d-lg-block'],
			['text' => 'Votes', 'url' => home_url($gov_slug . '/votes/'),'class' => 'btn-outline-primary d-none d-lg-block'],
			['text' => 'Reports', 'url' => home_url($gov_slug . '/reports/'),'class' => 'btn-outline-primary d-none d-lg-block'],
		],
    ],
];

//Adjust gov name display for US.
if($gov == 'US'){
	$gov_name = 'U.S.';
}

fi_get_template('partials/template-header', $header_args);
?>
<div class="row mb-3">
	<div class="col-12 col-md-6">
		<div class="row">
			<!-- Image Column -->
			<div class="col-4">
				<?php if ($image_html): ?>
					<div class="text-center text-md-start">
						<?= $image_html; ?>
					</div>
				<?php endif; ?>
			</div>
			<div class="col-8">
				<div class="fs-5 fw-bold text-muted lh-1 mb-0"><?= esc_html($gov_name . ' ' . $chamber_title); ?></div>
				<h1 class="entry-title fs-1 fw-bold mb-1"><?= esc_html($legislator_name); ?></h1>
				<div class="fs-6 mb-1"><?= esc_html($party_name); ?></div>
				<div class="fs-6 mb-2 text-muted"><?= esc_html($state_name . $district_name); ?></div>
				<?php if (!empty($websites)): ?>
				<div class="d-none d-lg-block mb-3">
					<!-- <label class="form-label small fw-bold">Website<?php //echo count($websites) > 1 ? 's' : ''; ?></label> -->
					<?php foreach ($websites as $website): ?>
						<div><a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener" class="mb-1">
							<i class="bi bi-globe"></i> <?php echo esc_html(parse_url($website, PHP_URL_HOST) ?: $website); ?>
						</a></div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<div class="col-12 col-md-6">
		<div class="row">
			<div class="col-4 col-xxl-6">
				<?php if ($score_freedom){
					echo '<div class="d-flex justify-content-center align-items-center w-100 pt-4">'.fi_score_donut($score_freedom, 'Freedom Score', null, null, 200).'</div>';
					//echo '<div class="freedom-score text-center" style="font-size: 7rem; font-weight:700; line-height:1; color: var(--bs-primary);">'.$score_freedom.'</div><div class="freedom-score-label text-center">Freedom Score</div>';
				}?>
			</div>
			<div class="col-8 col-xxl-6">
				<?php
				//echo 'Session ID: '.$data->session_id.'<br>Current Session ID: '.$current_session_id.'<br>Gov: '.$gov.'<br>';
				$latest_scorecard = fi_report_latest_scorecard($gov, $data->session_id);
				$modal_data = (array) $data;
				$modal_data['latest_scorecard'] = (array) $latest_scorecard;
				?>
				<div class="fi-modal-container pt-lg-4">
					<?php fi_get_template('partials/legislator-modal-share', $modal_data); ?>
					<?php fi_get_template('partials/legislator-modal-contact', $modal_data); ?>
					<?php fi_get_template('partials/legislator-modal-list', $modal_data); ?>
					<?php fi_get_template('partials/legislator-modal-personalize', $modal_data); ?>
					<?php fi_get_template('partials/legislator-modal-print', $modal_data); ?>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
// Vote History Section
fi_get_template('partials/legislator-vote-history', [
	'legislator' => $data,
	'current_session_id' => $current_session_id,
	'current_report_id' => $current_report_id,
	'current_tag_id' => $current_tag_id,
]);


//echo '<textarea style="width:100%; height:400px;">'; print_r($modal_data); echo '</textarea>';
echo "<!-- Legislator Object: \n"; print_r($data); echo "\n -->\n";

//Include PDF Layout testing template for faster design iterations.
/*
if(get_current_user_id() == 1){
	$pdf_mockup = FI_DIR . 'public/pdf/html-legislator-scb.php';
	if(file_exists($pdf_mockup)){
		include $pdf_mockup;
	}
}
*/


fi_get_template('partials/template-footer');
get_footer();
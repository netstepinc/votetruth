<?php if (!defined('ABSPATH')) exit;
/* 
* Legislator Vote History Display
 * 
 * Displays vote history with sidebar navigation (sessions, reports, issues) and main content area for vote cards
 * Features:
	Card-based vote display with Bootstrap 5 styling
	Mobile-responsive with sliding navigation panel
	Real-time search filtering (no page refresh)
	Vote detail modal (no navigation away)
	Color-coded vote indicators (green/red/gray)
	Report headers displayed above vote lists
	Session-level caching support (already implemented in core)

https://freedomindex.us/legislator/1414/?TEST=payload
https://freedomindex.us/legislator/1414/?TEST=vote_groups
*/

$legislator_id = (int)fi_public_get_legislator_id();
$current_session_id = fi_public_get_legislator_session_id();
$current_report_id = fi_public_get_legislator_report_id();
$current_tag_id = fi_public_get_legislator_tag_id();

// Get legislator data - REPLACE WP METHOD WITH API METHOD
$legislator = fi_api_legislator_get_by_id($legislator_id);
if(isset($_GET['TEST']) && $_GET['TEST'] == 'payload'){
	echo '<textarea style="width:100%; height:800px;">'; print_r($legislator); echo '</textarea>';exit;
}

if (empty($legislator)) {
    status_header(404);
    echo '<div style="margin-top:100px; text-align:center;"><h1>Legislator Not Found</h1></div>';
    get_footer();
    exit;
}

// Check if legislator has valid session data - redirect to legislators list if not
if (empty($legislator['gov'])) {
    $redirect_url = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : home_url('/');
    wp_safe_redirect($redirect_url);
    exit;
}

//CHUNK DOWN the massive data array into smaller arrays
$meta = $legislator['meta'] ?? [];
//unset($legislator['meta']);

$votes = $legislator['votes'] ?? [];
unset($legislator['votes']);

$vote_tags = $legislator['vote_tags'] ?? [];
unset($legislator['vote_tags']);

$votes_cast = $legislator['votes_cast'] ?? [];
unset($legislator['votes_cast']);

$sessions = $legislator['sessions'] ?? [];
unset($legislator['sessions']);

//Basic Legislator Info
$legislator_name = stripslashes($legislator['display_name'] ?? '');

$chamber = $legislator['chamber'] ?? '';
$chamber_label = $legislator['chamber_label'] ?? '';
$chamber_title = $legislator['chamber_title'] ?? '';

$party = $legislator['party'] ?? '';
$party_name = $legislator['party_name'] ?? '';

$freedom_score = $legislator['freedom_score'] ?? null;
$freedom_score_text = $freedom_score !== null ? $freedom_score . '%' : 'N/A';

// Use actual current URL (including session/report/issue parameters) for og:url
// This ensures social media shares link to the exact page being viewed, not just the base legislator URL
$current_url = home_url($_SERVER['REQUEST_URI']);
// Ensure URL ends with / for consistency
if (!str_ends_with($current_url, '/')) {
	$current_url .= '/';
}


$gov = $legislator['gov'];
$gov_name = fi_gov_name($gov);
$gov_slug = strtolower($gov);
//Adjust gov name display for US.
if($gov == 'US'){
	$gov_name = 'U.S.';
}

// Basic info
$state_name = ($gov == 'US' && !empty($legislator['state_name'])) ? $legislator['state_name'] . ' ' : '';

// US Senators don't have districts - only check if district ID exists
$district_name = '';
if(!empty($legislator['district_info'])){
    $district_name = 'District: ';
    $district_name .= $legislator['district_info']['name_short'] ?? $legislator['district_info']['name'] ?? '';
}

$websites = fi_legislator_websites((object) $legislator);

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
if ($legislator['chamber'] === 'S' && !empty($meta['committee'])) {
    $committees_raw = is_string($meta['committee']) ? json_decode($meta['committee'], true) : $meta['committee'];
    if (is_array($committees_raw)) {
        $committees = array_filter($committees_raw, function($c) {
            return strlen($c) > 8;
        });
    }
}

// Image
if(!empty($legislator['image_url'])){
	$image_url = $legislator['image_url'];
	$image_html = '<img width="200" height="250" src="'.$image_url.'" class="img-fluid rounded-4 shadow" alt="'.esc_attr($legislator_name).'" data-jis="true" loading="lazy" id="image-'.$legislator['id'].'" srcset="'.$image_url.' 1x, '.$image_url.' 2x">';
}else{
	$image_url = $legislator['image_id'] ? wp_get_attachment_url($legislator['image_id']) : '';
	$image_html = fi_legislator_image(
		$legislator['image_id'], 
		$legislator['session_image_id'] ?? null,
		[
			'size' => [200,250],
			'crop' => true,
			'alt' => $legislator_name,
			'class' => 'img-fluid rounded-4 shadow'
		]
	);
}

$text_sm_menu_close = 'Close Menu';
$text_sm_menu_open = 'Select Session or Report';


// Default view: All Votes for base URL; session/report/issue only when present in URL
$default_view = 'all';
$default_session_id = $current_session_id ? (int) $current_session_id : null;
$default_report_id = $current_report_id ? (int) $current_report_id : null;
$default_tag_id = $current_tag_id ? (int) $current_tag_id : null;

if ($default_tag_id) {
    $default_view = 'tag';
} elseif ($default_report_id) {
    $default_view = 'report';
} elseif ($default_session_id) {
    $default_view = 'session';
}


//BUILD VOTE GROUP ARRAY (Distill from the full Legislator Array)
$scoring = fi_score_format($freedom_score);
$vote_groups = [
	'all' => [
		'menu' => 'All Votes',
		'title' => 'Complete Vote History',
		'subtitle' => '',
		'actions' => [ 'search'=> true ],
		'count' => null,
		'score_text' => $scoring['text'],
		'score_badge' => $scoring['badge'],
		'votes' => array_keys($votes), //$votes_cast
	],
	'tags' => [],
	'sessions' => [],
];
//Vote Tags
if (!empty($vote_tags)){
	foreach ($vote_tags as $tag){
		$tag_id = $tag['id'];
		$scoring = fi_score_format($tag['score']);
		$vote_groups['tags'][$tag_id] = [
			'current' => ($current_tag_id == $tag_id ? true : false),
			'menu' => $tag['name'],
			'title' => 'Voting on ' .$tag['name'],
			'subtitle' => null,
			'content' => null,
			'actions' => [
				'share' => true,
				'score' => $scoring['button'],
			],
			'count' => $tag['vote_count'],
			'score' => isset($tag['score']) ? $scoring['score'] : null,
			'score_text' => $scoring['text'],
			'score_badge' => $scoring['badge'],
			'score_button' => $scoring['button'],
			'votes' => $tag['votes'],
		];
	}
}

//Sessions
if (!empty($sessions)){
	foreach ($sessions as $session){
		$session_id = $session['session_id'];

		//Session Reports
		$reports = [];
		foreach($session['reports'] as $report){
			if(isset($report['payload']) && isset($report['payload']['content'])){
				//$content = wp_kses_post(wpautop($report['payload']['content']));
				$content = fi_clean_content($report['payload']['content']);
			}else{
				$content = null;
			}
			$scoring = fi_score_format($report['score']);

			$actions = ['share' => true];
			if(isset($report['payload']) && isset($report['payload']['report_pdf_url']) && !empty($report['payload']['report_pdf_url'])){
				$actions['pdf'] = $report['payload']['report_pdf_url'];
			}else{
				$actions['pdfa'] = home_url('/legislator/' . $legislator_id . '/session/' . $session_id . '/report/' . $report['id'] . '/pdf/sca/');
				$actions['pdfb'] = home_url('/legislator/' . $legislator_id . '/session/' . $session_id . '/report/' . $report['id'] . '/pdf/scb/');
			}
			$actions['score'] = $scoring['button'];

			if(isset($report['title_menu']) && !empty($report['title_menu'])){
				$menu_title = $report['title_menu'];
			}else{
				$menu_title = $report['title'];
			}

			$report_title = fi_report_title_reformat($session['gov'], $report['title']);

			$reports[] = [
				'id' => $report['id'],
				'menu' => $menu_title,
				'title' => $report_title,
				'subtitle' => $session['gov'] . ' ' . $session['chamber_label'],
				'content' => $content,
				'actions' => $actions,
				'score' => isset($report['score']) ? $scoring['score'] : null,
				'score_text' => $scoring['text'],
				'score_badge' => $scoring['badge'],
				'score_button' => $scoring['button'],
				'votes' => isset($report['votes']) ? $report['votes'] : null,
			];
		}
		//Session Compiled
		$scoring = fi_score_format($session['score']);
		$vote_groups['sessions'][$session_id] = [
			'current' => ($current_session_id == $session_id ? true : false),
			'menu' => $session['session_name'],
			'title' => $session['session_name'],
			'subtitle' => $session['gov'] . ' ' . $session['chamber_label'] . ' ' . $session['chamber_title'],
			'content' => null,
			'actions' => [
				'share' => true, 
				'score' => $scoring['button'],
			],
			'count' => null,
			'score' => $session['score'],
			'score_text' => $scoring['text'],
			'score_badge' => $scoring['badge'],
			'score_button' => $scoring['button'],
			'votes' => $session['votes'],
			'reports' => $reports,
		];
	}
}


// Default print modal base URL (from latest_scorecard) for "no report selected" fallback
$default_print_modal_report_base = '';
if (!empty($legislator['latest_scorecard']) && !empty($legislator['latest_scorecard']['session_id']) && isset($legislator['latest_scorecard']['id'])) {
	$default_print_modal_report_base = home_url('/legislator/' . $legislator_id . '/session/' . $legislator['latest_scorecard']['session_id'] . '/report/' . $legislator['latest_scorecard']['id'] . '/');
}

// SEO & Meta Tags
$page_title = $legislator_name . ' | ' . $chamber_label . ' | Freedom Index';
$page_description = $legislator_name . ' (' . $chamber_label . ', ' . $party_name . ') - Freedom Score: ' . $freedom_score_text . '. View voting record, scores, and reports.';


// SEO Meta Tags - Microsoft Teams also recognizes og: properties.
fi_seo_tags([
	'title' => $page_title,
	'description' => $page_description,
	'canonical' => $current_url,
	'robots' => 'index, follow',
	'og' => [
		'og:title'         => $page_title, // Facebook/Teams title
		'og:description'   => $page_description, // Facebook/Teams description
		'og:url'           => $current_url, // Facebook/Teams url
		'og:type'          => 'profile',   // Facebook/Teams type
		'og:image'         => $image_url,  // Facebook/Teams main image
		'og:image:alt'     => $legislator_name, // Facebook/Teams image alt text
		//'og:image:width'   => '1200', // Optional: recommended width
		//'og:image:height'  => '630',  // Optional: recommended height
	],
	'twitter' => [
		'twitter:card'        => 'summary',
		'twitter:title'       => $page_title,
		'twitter:description' => $page_description,
		'twitter:image'       => $image_url,
	],
]);

get_header();

$header_args = [
	'title' => '',
	'gov' => $gov,
	'gov_name' => $gov_name,
    'pretext' => '', //$gov_name . ' Legislator',
	'url_back' => home_url('/' . strtolower($legislator['gov']) . '/legislators/'),
	'url_back_text' => 'Back to Legislators',
    'id' => 'fi-legislator',
    'class' => '',
    'breadcrumbs' => [
        ['text' => $legislator['gov_name'], 'url' => home_url('/' . strtolower($legislator['gov']) . '/')],
        ['text' => 'Legislators', 'url' => home_url('/' . strtolower($legislator['gov']) . '/legislators/')],
        ['text' => $legislator_name, 'class' => 'fw-bold'],
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
			<div class="col-4 col-xxl-6"><!-- SCORE:<?= $freedom_score;?> -->
				<?php if ($freedom_score != ''){
					echo '<div class="d-flex justify-content-center align-items-center w-100 pt-4">'.fi_score_donut($freedom_score, 'Freedom Score', null, null, 200).'</div>';
					//echo '<div class="freedom-score text-center" style="font-size: 7rem; font-weight:700; line-height:1; color: var(--bs-primary);">'.$score_freedom.'</div><div class="freedom-score-label text-center">Freedom Score</div>';
				}?>
			</div>
			<div class="col-8 col-xxl-6">
				<div class="fi-modal-container pt-lg-4">
					<?php fi_get_template('partials/legislator-modal-share', $legislator); ?>
					<?php fi_get_template('partials/legislator-modal-contact', $legislator); ?>
					<?php fi_get_template('partials/legislator-modal-list', $legislator); ?>
					<?php fi_get_template('partials/legislator-modal-personalize', $legislator); ?>
					<?php fi_get_template('partials/legislator-modal-print', $legislator); ?>
				</div>
			</div>
		</div>
	</div>
</div>
<!-- VOTE HISTORY -->
<div class="row g-3">
	<!-- Left Sidebar Navigation (LG+) / Mobile Sliding Panel -->
	<div class="col-12 col-lg-4 col-xxl-3">
		<!-- Mobile Toggle Button -->
		<button id="fi-vote-nav-toggle" class="btn btn-sm btn-outline-primary w-100 d-lg-none mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#fi-vote-nav-collapse" aria-expanded="false" aria-controls="fi-vote-nav-collapse">
			<i class="bi bi-list me-2"></i><span class="fi-nav-text fw-bold"><?= $text_sm_menu_open;?></span>
		</button>
		
		<!-- Navigation Panel -->
		<div class="collapse d-lg-block" id="fi-vote-nav-collapse">
			<div class="card rounded-4 shadow-sm">
				<div class="card-header rounded-top-4 bg-white">
					<h5 class="fs-3 mb-0">Voting History</h5>
				</div>
				<div class="card-body p-0 pb-4">
					<div class="accordion accordion-flush" id="accordionVoteNav">
						<div class="accordion-item">
							<h2 class="accordion-header">
							<button class="accordion-button caret-tight py-2 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseAllVotes" aria-expanded="false" aria-controls="flush-collapseAllVotes">
								<span class="me-auto fs-7">All Votes</span>
								<?php echo $vote_groups['all']['score_badge']; ?>
							</button>
							</h2>
							<div id="flush-collapseAllVotes" class="accordion-collapse collapse" data-bs-parent="#accordionVoteNav">
								<div class="accordion-body p-0">
									<ul class="list-group list-group-flush mb-0">
										<li class="list-group-item border-0">
											<a href="<?php echo esc_url(home_url('/legislator/' . $legislator_id . '/')); ?>" class="list-group-item list-group-item-action fi-nav-item fs-7" data-view="all" data-type="all">All Votes</a>
										</li>
										<!-- Issues/Tags (ID-based URLs) -->
									<?php if (!empty($vote_groups['tags'])): ?>
										<?php foreach ($vote_groups['tags'] as $tag_id => $tag): ?>
										<li class="list-group-item border-0">
											<a href="<?php echo esc_url(home_url('/legislator/' . $legislator_id . '/issue/' . $tag_id . '/')); ?>" class="list-group-item list-group-item-action fi-nav-item fs-7" data-view="tag" data-tag-id="<?php echo esc_attr($tag_id); ?>">
												<?php echo esc_html($tag['menu']); ?>
												<span class="badge bg-secondary float-end"><?php echo esc_html($tag['count']); ?></span>
											</a>
										</li>
										<?php endforeach; ?>
									<?php endif;?>
									</ul>
								</div>
							</div>
						</div>
						<!-- Sessions -->
						<?php 
						foreach ($vote_groups['sessions'] as $session_id => $session):
							$is_current_session = ($current_session_id && $session_id == $current_session_id);
							$collapse_id = 'fi-session-' . $session_id;
						?>
						<div class="accordion-item">
							<h2 class="accordion-header">
								<button class="accordion-button caret-tight py-2 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapse<?php echo esc_attr($session_id); ?>" aria-expanded="false" aria-controls="flush-collapse<?php echo esc_attr($session_id); ?>">
									<span class="me-auto fs-7"><?php echo esc_html($session['menu']); ?></span>
									<?php echo $session['score_badge']; ?>
								</button>
							</h2>
							<div id="flush-collapse<?php echo esc_attr($session_id); ?>" class="accordion-collapse collapse<?php echo $is_current_session ? ' show' : ''; ?>" data-bs-parent="#accordionVoteNav">
								<div class="accordion-body p-0">
									<!-- All Session Votes + Reports List -->
									<ul class="list-group list-group-flush mb-0">
										<!-- All Session Votes -->
										<li class="list-group-item border-0">
											<a href="<?php echo esc_url(home_url('/legislator/' . $legislator_id . '/session/' . $session_id . '/')); ?>" class="list-group-item list-group-item-action fi-nav-item fs-7 <?php echo ($is_current_session && !$current_report_id) ? ' active' : ''; ?>" 
												data-view="session" 
												data-session-id="<?php echo esc_attr($session_id); ?>">
												All Session Votes
											</a>
										</li>
										<!-- Reports -->
										<?php 
										foreach ($session['reports'] as $report): 
											$report_id = (int) ($report['id'] ?? 0);
											$is_current_report = ($is_current_session && $current_report_id && $report_id === (int) $current_report_id);
										?>
										<li class="list-group-item border-0">
											<a href="<?php echo esc_url(home_url('/legislator/' . $legislator_id . '/session/' . $session_id . '/report/' . $report_id . '/')); ?>" class="list-group-item list-group-item-action fs-7 fi-nav-item ps-4<?php echo $is_current_report ? ' active bg-primary text-white' : ''; ?>" 
												data-view="report" 
												data-session-id="<?php echo esc_attr($session_id); ?>" 
												data-report-id="<?php echo esc_attr($report_id); ?>">
												<?php echo esc_html($report['menu']); ?>
											</a>
										</li>
										<?php endforeach; ?>
									</ul>
								</div>
							</div>
						</div>
						<?php endforeach;?>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Main Content Area -->
	<div class="col-12 col-lg-8 col-xxl-9">
		<div class="card rounded-4 shadow-sm">
			<div class="card-header rounded-top-4 bg-white border-bottom">
				<div class="row align-items-center g-2">
					<div class="col-12 col-md">
						<h4 class="fs-3 mb-0" id="fi-vote-list-title"><?php echo esc_html($vote_groups['all']['title']); ?></h4>
					</div>
					<div class="col-12 col-md-auto">
							<?php
						$share_session_id = $current_session_id ?? '';
						$share_report_id = $current_report_id ?? '';
						?>
						<div id="fi-vote-score-container" style="display: none;">
							<div class="btn-group btn-group-sm w-100 w-md-auto" role="group">
								<button
									type="button"
									class="btn btn-outline-success fs-7 fw-bold flex-fill"
									data-bs-toggle="modal"
									data-bs-target="#shareModal"
									data-share-session="<?php echo esc_attr($share_session_id); ?>"
									data-share-report="<?php echo esc_attr($share_report_id); ?>"
									data-share-legislator-id="<?php echo esc_attr($legislator_id); ?>"
									id="fi-vote-share-btn"
									style="display: none;"
								>
									<i class="bi bi-share me-2"></i>Share
								</button>
								<a
									href="#"
									class="btn btn-outline-danger fs-7 fw-bold flex-fill"
									target="_blank"
									id="fi-vote-pdf-btn"
									style="display: none;"
								>
									<i class="bi bi-file-pdf me-2"></i>PDF
								</a>
								<a
									href="#"
									class="btn btn-outline-danger fs-7 fw-bold flex-fill"
									target="_blank"
									id="fi-vote-pdf-portrait-btn"
									data-format="sca"
									style="display: none;"
								>
									<i class="bi bi-file-pdf me-2"></i>PDF
								</a>
								<a
									href="#"
									class="btn btn-outline-danger fs-7 fw-bold flex-fill"
									target="_blank"
									id="fi-vote-pdf-bifold-btn"
									data-format="scb"
									style="display: none;"
								>
									<i class="bi bi-file-pdf me-2"></i>PDF Bi-Fold
								</a>
								<span id="fi-vote-score-action"></span>
							</div>
						</div>
						<!-- Search Box (for All Votes view) -->
						<?php if(isset($vote_groups['all']['actions']['search']) && !empty($vote_groups['all']['actions']['search'])): ?>
						<div id="fi-vote-search-container">
							<input type="text" class="form-control form-control-sm" id="fi-vote-search" placeholder="Search votes..." style="min-width: 200px;">
						</div>
						<?php endif;?>
					</div>
				</div>
			</div>

			<!-- Vote List Content -->
			<div class="card-body p-3">
				<div id="fi-vote-list-subtitle" class="text-muted fs-7 mb-2" style="display:none;"></div>
				<div id="fi-vote-list-content" class="mb-3" style="display:none;"></div>
				<div id="fi-vote-list-container">
<?php
//TODO: REMOVE
if(isset($_GET['TEST']) && $_GET['TEST'] == 'vote_groups'):
	echo '<textarea style="width: 100%; height: 500px;">'.print_r($vote_groups, true).'</textarea>';
else:

	if(count($votes) == 0):
		echo '<div id="no-votes-found" class="alert alert-info d-none">No votes found for this selection.</div>';
	else:
		foreach($votes as $VID => $vote):
			$meta = $vote['meta'];
			$text_short = fi_clean_content($meta['description_short'] ?? '');
			//$medium = fi_clean_content($meta['description_medium'] ?? '');
			$text_long = fi_clean_content($meta['description_long'] ?? '');

			$vote_format = fi_vote_format([
				'cast' => $vote['cast'],
				'constitutional' => $vote['constitutional'],
				'format' => 'full'
			]);

			// Format cost
			$cost_html = '';
			$cost_value = $meta['cost'] ?? '';
			if (!empty($cost_value)) {
				$cost = fi_vote_cost_format($cost_value);
				$cost_html = $cost['html'] ?? '';
			}

			// Prepare vote card data
			$card_data = [
				'id' => $VID,
				'gov' => $vote['gov'],
				'title' => $vote['title'],
				'show_link_title' => true,
				'text' => $text_short,
				'text_more' => $text_long,
				'bill_number' => $vote['bill_number'],
				'constitutional' => $vote['constitutional'],
				'date_voted' => $vote['date_voted'],
				'date_formatted' => $vote['date_formatted'],
				'vote_format' => $vote_format,
				'bill_url' => $meta['url_bill'],
				'cost_html' => $cost_html,
				'url_vote' => $vote['url_vote'],
				'search_text' => $vote['search_text'],
				'chamber_title' => true,
				'chamber_label' => $vote['chamber_label'],
				// For legislator vote history, show cast and link
				'show_cast' => true,
				'show_link' => true,
				'modal_mode' => 'page',
				'cast' => $vote['cast'],
				'card_class' => 'rounded rounded-4 shadow mb-3',
			];
			fi_get_template('partials/vote-card', $card_data);
		endforeach;
	endif;

endif; //end if test=api2
?>
				</div>
			</div>
		</div>
	</div>
</div>



<style>
.accordion-button.fi-nav-parent-active {
    background: var(--bs-primary-bg-subtle);
    color: var(--bs-primary-text-emphasis);
    font-weight: 600;
}
</style>

<!-- Vote Detail Modal -->
<div class="modal fade" id="fi-vote-detail-modal" tabindex="-1" aria-labelledby="fi-vote-detail-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
		<div class="modal-content rounded-4">
			<div class="modal-header bg-light rounded-top-4">
				<h5 class="modal-title" id="fi-vote-detail-modal-label">Vote Details</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body" id="fi-vote-detail-content">
				<!-- Content loaded via AJAX -->
			</div>
			<div class="modal-footer p-0 rounded-bottom-4">
				<button type="button" class="btn btn-sm btn-secondary rounded-0 rounded-bottom-4 w-100 fw-bold m-0" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<!-- GSAP error suppression - runs immediately before any other scripts -->
<script>
(function() {
    'use strict';
    // Defensive patch for GSAP errors - suppress "target not found" warnings/errors
    // This runs immediately to catch errors even if GSAP hasn't loaded yet
    if (typeof console !== 'undefined') {
        const originalWarn = console.warn;
        const originalError = console.error;
        const originalLog = console.log;
        
        // Helper to check if message is a GSAP target error
        function isGSAPTargetError(args) {
            if (!args || args.length === 0) return false;
            const fullMessage = Array.from(args).map(arg => {
                if (typeof arg === 'string') return arg;
                if (typeof arg === 'object' && arg !== null) {
                    try {
                        return JSON.stringify(arg);
                    } catch (e) {
                        return String(arg);
                    }
                }
                return String(arg);
            }).join(' ');
            
			const lowerMessage = fullMessage.toLowerCase();
			const hasGSAP = lowerMessage.includes('gsap');
			const hasTarget = lowerMessage.includes('target') || 
				lowerMessage.includes('.fi-legislator-card') || 
				lowerMessage.includes('.fi-');
			const hasNotFound = lowerMessage.includes('not found') || 
				lowerMessage.includes('notfound');
            
            return hasGSAP && hasTarget && (hasNotFound || lowerMessage.includes('greensock'));
        }
        
        console.warn = function() {
            if (isGSAPTargetError(arguments)) return;
            originalWarn.apply(console, arguments);
        };
        
        console.error = function() {
            if (isGSAPTargetError(arguments)) return;
            originalError.apply(console, arguments);
        };
        
        console.log = function() {
            if (isGSAPTargetError(arguments)) return;
            originalLog.apply(console, arguments);
        };
    }
})();
</script>

<script>
(function($) {
    const legislatorId = <?php echo esc_js($legislator_id); ?>;
    const defaultView = <?php echo wp_json_encode($default_view); ?>;
    const defaultSessionId = <?php echo wp_json_encode($default_session_id); ?>;
    const defaultReportId = <?php echo wp_json_encode($default_report_id); ?>;
    const defaultTagId = <?php echo wp_json_encode($default_tag_id); ?>;
    const defaultPrintModalReportBase = <?php echo wp_json_encode($default_print_modal_report_base); ?>;
    const voteGroups = <?php echo wp_json_encode($vote_groups); ?>;

    function getDefaultState() {
        return {
            view: defaultView,
            sessionId: defaultSessionId,
            reportId: defaultReportId,
            tagId: defaultTagId,
        };
    }

    let state = getDefaultState();

    const $container = $('#fi-vote-list-container');
    const $cards = $container.find('.fi-vote-card');
    const $title = $('#fi-vote-list-title');
    const $subtitle = $('#fi-vote-list-subtitle');
    const $content = $('#fi-vote-list-content');
    const $search = $('#fi-vote-search');
    const $searchWrap = $('#fi-vote-search-container');
    const $scoreWrap = $('#fi-vote-score-container');
    const $shareBtn = $('#fi-vote-share-btn');
    const $pdfBtn = $('#fi-vote-pdf-btn');
    const $pdfPortraitBtn = $('#fi-vote-pdf-portrait-btn');
    const $pdfBifoldBtn = $('#fi-vote-pdf-bifold-btn');
    const $scoreAction = $('#fi-vote-score-action');
    const $noVotes = $('#no-votes-found');

    const navCollapse = document.getElementById('fi-vote-nav-collapse');

    function getBootstrapModal(el) {
        if (!window.bootstrap || !window.bootstrap.Modal) return null;
        return window.bootstrap.Modal.getOrCreateInstance(el);
    }

    function buildPathFromState() {
        let path = `/legislator/${legislatorId}/`;

        if (state.view === 'tag' && state.tagId) {
            path += `issue/${state.tagId}/`;
        } else if (state.sessionId) {
            path += `session/${state.sessionId}/`;
            if (state.reportId) {
                path += `report/${state.reportId}/`;
            }
        }

        return path;
    }

    function pushStateFromSelection() {
        const nextUrl = `${window.location.origin}${buildPathFromState()}`;
        window.history.pushState({...state}, '', nextUrl);
        updateOgAndCanonical(nextUrl);
    }

    function updateOgAndCanonical(url) {
        const ogUrlMeta = document.querySelector('meta[property="og:url"]');
        if (ogUrlMeta) {
            ogUrlMeta.setAttribute('content', url);
        }

        const canonicalLink = document.querySelector('link[rel="canonical"]');
        if (canonicalLink) {
            canonicalLink.setAttribute('href', url);
        }
    }

    function getActiveGroup() {
        if (state.view === 'tag' && state.tagId && voteGroups.tags[state.tagId]) {
            return voteGroups.tags[state.tagId];
        }

        if (state.sessionId && voteGroups.sessions[state.sessionId]) {
            if (state.reportId) {
                const report = (voteGroups.sessions[state.sessionId].reports || []).find(function(item) {
                    return Number(item.id) === Number(state.reportId);
                });
                if (report) return report;
            }

            return voteGroups.sessions[state.sessionId];
        }

        return voteGroups.all;
    }

    function getVisibleVoteIds(group) {
        if (!group || !Array.isArray(group.votes)) return [];
        return group.votes.map(function(id) { return Number(id); });
    }

    function updateHeader(group) {
        $title.text((group && group.title) ? group.title : 'Complete Vote History');

        if ($subtitle.length) {
            if (group && group.subtitle) {
                $subtitle.text(group.subtitle).show();
            } else {
                $subtitle.text('').hide();
            }
        }

        if ($content.length) {
            if (group && group.content) {
                $content.html(group.content).show();
            } else {
                $content.empty().hide();
            }
        }

        const actions = (group && group.actions) ? group.actions : {};
        const hasActionControls = !!(actions.share || actions.score || actions.pdf || actions.pdfa || actions.pdfb);

        $searchWrap.toggle(!!actions.search);
        $scoreWrap.toggle(hasActionControls);

        if ($shareBtn.length) {
            $shareBtn.attr('data-share-session', state.sessionId || '');
            $shareBtn.attr('data-share-report', state.reportId || '');
            $shareBtn.toggle(!!actions.share);
        }

        if ($scoreAction.length) {
            if (actions.score) {
                $scoreAction.html(actions.score).show();
            } else {
                $scoreAction.empty().hide();
            }
        }

        if ($pdfBtn.length) {
            if (actions.pdf) {
                $pdfBtn.attr('href', actions.pdf).show();
            } else {
                $pdfBtn.hide();
            }
        }

        if ($pdfPortraitBtn.length) {
            if (actions.pdfa) {
                $pdfPortraitBtn.attr('href', actions.pdfa).show();
            } else {
                $pdfPortraitBtn.hide();
            }
        }

        if ($pdfBifoldBtn.length) {
            if (actions.pdfb) {
                $pdfBifoldBtn.attr('href', actions.pdfb).show();
            } else {
                $pdfBifoldBtn.hide();
            }
        }

        // Keep Print modal PDF base in sync: selected report when session+report set, else PHP default (latest_scorecard)
        updatePrintModalReportBase();
    }

    function updatePrintModalReportBase() {
        var baseUrl = '';
        if (state.sessionId && state.reportId) {
            baseUrl = window.location.origin + '/legislator/' + legislatorId + '/session/' + state.sessionId + '/report/' + state.reportId + '/';
        } else if (defaultPrintModalReportBase) {
            baseUrl = defaultPrintModalReportBase;
        }
        if (!baseUrl) return;
        var modal = document.getElementById('printModal');
        if (!modal) return;
        var btns = modal.querySelectorAll('.fi-print-pdf-btn');
        for (var i = 0; i < btns.length; i++) {
            btns[i].setAttribute('data-pdf-base', baseUrl);
        }
        modal.dispatchEvent(new CustomEvent('fi-print-report-base-changed'));
    }

    function applyVoteFilter() {
        const group = getActiveGroup();
        const visibleVoteIds = new Set(getVisibleVoteIds(group));
        const searchTerm = (state.view === 'all') ? String($search.val() || '').toLowerCase().trim() : '';

        let visibleCount = 0;
        $cards.each(function() {
            const $card = $(this);
            const voteId = Number($card.data('vote-id'));
            const inGroup = visibleVoteIds.has(voteId);
            const searchText = String($card.data('search-text') || '').toLowerCase();
            const matchesSearch = !searchTerm || searchText.includes(searchTerm);
            const show = inGroup && matchesSearch;
            $card.toggle(show);
            if (show) visibleCount += 1;
        });

        if ($noVotes.length) {
            $noVotes.toggleClass('d-none', visibleCount > 0);
        }

        updateHeader(group);
    }

    function highlightNavigation() {
        $('.fi-nav-item').removeClass('active bg-primary text-white');
        $('.accordion-button').removeClass('fi-nav-parent-active');

        if (state.view === 'tag' && state.tagId) {
            $(`.fi-nav-item[data-view="tag"][data-tag-id="${state.tagId}"]`).addClass('active bg-primary text-white');
            $('#flush-collapseAllVotes').addClass('show');
            return;
        }

        if (state.sessionId) {
            const sessionCollapseId = `#flush-collapse${state.sessionId}`;
            const $collapse = $(sessionCollapseId);
            const $sessionButton = $(`.accordion-button[data-bs-target="${sessionCollapseId}"]`);
            $sessionButton.addClass('fi-nav-parent-active');

            if ($collapse.length && !$collapse.hasClass('show')) {
                $collapse.addClass('show');
            }

            if (state.reportId) {
                $(`.fi-nav-item[data-view="session"][data-session-id="${state.sessionId}"]`).removeClass('active bg-primary text-white');
                $(`.fi-nav-item[data-view="report"][data-session-id="${state.sessionId}"][data-report-id="${state.reportId}"]`).addClass('active bg-primary text-white');
            } else {
                $(`.fi-nav-item[data-view="session"][data-session-id="${state.sessionId}"]`).first().addClass('active bg-primary text-white');
            }

            return;
        }

        $('.fi-nav-item[data-view="all"]').first().addClass('active bg-primary text-white');
    }

    function parseStateFromPath(pathname) {
        const parts = pathname.split('/').filter(Boolean);
        let idx = parts.indexOf('legislator');
        if (idx === -1) {
            idx = parts.indexOf('legislators');
        }

        if (idx === -1 || !parts[idx + 1]) return null;

        const parsed = {
            view: 'all',
            sessionId: null,
            reportId: null,
            tagId: null,
        };

        const mode = parts[idx + 2] || null;
        if (mode === 'issue' && parts[idx + 3]) {
            const tagId = Number(parts[idx + 3]);
            if (!tagId) return parsed;
            parsed.view = 'tag';
            parsed.tagId = tagId;
            return parsed;
        }

        if (mode === 'session' && parts[idx + 3]) {
            const sessionId = Number(parts[idx + 3]);
            if (!sessionId) return parsed;
            parsed.view = 'session';
            parsed.sessionId = sessionId;

            if (parts[idx + 4] === 'report' && parts[idx + 5]) {
                const reportId = Number(parts[idx + 5]);
                if (reportId) {
                parsed.view = 'report';
                    parsed.reportId = reportId;
                }
            }

            return parsed;
        }

        return parsed;
    }

    function setStateFromNav($item) {
        const view = $item.data('view');
        const sessionId = Number($item.data('session-id')) || null;
        const reportId = Number($item.data('report-id')) || null;
        const tagId = Number($item.data('tag-id')) || null;

        if (view === 'tag' && tagId) {
            state = { view: 'tag', sessionId: null, reportId: null, tagId };
            return;
        }

        if (view === 'report' && sessionId && reportId) {
            state = { view: 'report', sessionId, reportId, tagId: null };
            return;
        }

        if (view === 'session' && sessionId) {
            state = { view: 'session', sessionId, reportId: null, tagId: null };
            return;
        }

        state = { view: 'all', sessionId: null, reportId: null, tagId: null };
    }

    function syncUi() {
        highlightNavigation();
        applyVoteFilter();
    }

    $(document).on('click', '.fi-nav-item', function(e) {
        e.preventDefault();
        setStateFromNav($(this));
        pushStateFromSelection();
        syncUi();

        if (window.innerWidth < 992 && navCollapse) {
            if (window.bootstrap && window.bootstrap.Collapse) {
                window.bootstrap.Collapse.getOrCreateInstance(navCollapse).hide();
            }
        }
    });

    $(document).on('click', '#accordionVoteNav .accordion-button', function(e) {
        const targetSelector = $(this).attr('data-bs-target') || '';
        const targetId = targetSelector.replace('#', '');
        const $target = $(targetSelector);
        const isAlreadyOpen = $target.length && $target.hasClass('show');

        if (!isAlreadyOpen) {
            return;
        }

        if (targetId === 'flush-collapseAllVotes') {
            e.preventDefault();
            state = { view: 'all', sessionId: null, reportId: null, tagId: null };
            pushStateFromSelection();
            syncUi();
            return;
        }

        const sessionMatch = targetId.match(/^flush-collapse(\d+)$/);
        if (sessionMatch) {
            e.preventDefault();
            const sessionId = Number(sessionMatch[1]);
            if (sessionId) {
                state = { view: 'session', sessionId: sessionId, reportId: null, tagId: null };
                pushStateFromSelection();
                syncUi();
            }
        }
    });

    $(document).on('show.bs.collapse', '#accordionVoteNav .accordion-collapse', function() {
        const id = this.id || '';

        if (id === 'flush-collapseAllVotes') {
            state = { view: 'all', sessionId: null, reportId: null, tagId: null };
            pushStateFromSelection();
            syncUi();
            return;
        }

        const sessionMatch = id.match(/^flush-collapse(\d+)$/);
        if (sessionMatch) {
            const sessionId = Number(sessionMatch[1]);
            if (sessionId) {
                state = { view: 'session', sessionId: sessionId, reportId: null, tagId: null };
                pushStateFromSelection();
                syncUi();
            }
        }
    });

    $search.on('input', function() {
        if (state.view === 'all') {
            applyVoteFilter();
        }
    });

    // Read More on vote cards: modal_mode='page' pre-renders content into data-vote-title / data-vote-body
    // on the button (vote-card.php). One shared modal is reused for all votes — no per-vote modal HTML.
    $(document).on('click', '.fi-vote-card .fi-vote-readmore', function(e) {
        e.preventDefault();

        const title = $(this).data('vote-title') || 'Vote Details';
        const body  = $(this).data('vote-body')  || '';

        const targetModal = document.getElementById('fi-vote-detail-modal');
        if (!targetModal) return;

        const titleEl = targetModal.querySelector('#fi-vote-detail-modal-label');
        const bodyEl  = targetModal.querySelector('#fi-vote-detail-content');
        if (titleEl) titleEl.textContent = title + ' Details';
        if (bodyEl)  bodyEl.innerHTML = body;

        const modal = getBootstrapModal(targetModal);
        if (modal) modal.show();
    });

    window.addEventListener('popstate', function() {
        const parsedState = parseStateFromPath(window.location.pathname);
        state = parsedState || getDefaultState();
        updateOgAndCanonical(window.location.href);
        syncUi();
    });

    $(function() {
        const parsedState = parseStateFromPath(window.location.pathname);
        state = parsedState || getDefaultState();
        updateOgAndCanonical(window.location.href);

        // Ensure session panel for selected report/session is visible on initial load.
        if (state.sessionId) {
            $(`#flush-collapse${state.sessionId}`).addClass('show');
        }

        syncUi();
    });

})(jQuery);
</script>

<!-- Defensive patch for theme navMenu error - runs immediately -->
<script>
(function() {
    'use strict';
    // Prevent "can't access property classList, navMenu is null" error
    // Patch the function to add null checking
    if (typeof window.hideNavMenuScrollHideSm === 'function') {
        const original = window.hideNavMenuScrollHideSm;
        window.hideNavMenuScrollHideSm = function() {
            try {
                return original.apply(this, arguments);
            } catch (e) {
                if (e && e.message && (e.message.includes('navMenu') || e.message.includes('classList'))) {
                    console.warn('hideNavMenuScrollHideSm: Navigation menu element not found');
                    return;
                }
                throw e;
            }
        };
    }
    
    // Also create a safe stub if function doesn't exist yet (will be overwritten by theme)
    if (typeof window.hideNavMenuScrollHideSm === 'undefined') {
        window.hideNavMenuScrollHideSm = function() {
            // Safe no-op until theme defines it
        };
    }
    
})();
</script>
<?php
fi_get_template('partials/template-footer');
get_footer();
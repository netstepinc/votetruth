<?php
/**
 * The template for displaying 404 pages (not found).
 */
if ( ! defined( 'ABSPATH' ) ) {exit;}

/*Attempt to reconcile 404 URLs with new URLs.
Only activate FI redirect IF URL pattern is coming from the old Freedom Index site.
EXAMPLES:
CONGRESS=========
https://thefreedomindex.org/legislator/congress/119/ 
https://thefreedomindex.org/legislator/a000370/ 
https://thefreedomindex.org/report/scorecard-119-1/
https://thefreedomindex.org/legislator/a000370/votes/report-scorecard-118-2/ 
https://thefreedomindex.org/vote/2023h304/

ALL 50 STATES=======
https://thefreedomindex.org/wi 
https://thefreedomindex.org/wi/legislator/15724/ 
https://thefreedomindex.org/wi/vote/
https://thefreedomindex.org/wi/report/

IF URL starts with 
https://thefreedomindex.org/legislator/
https://thefreedomindex.org/report/
https://thefreedomindex.org/vote/
OR: same with state prefix after domain. In old site 'us' doesn't exist and it's just the main site.

https://thenewamerican.com/freedom-index/*
https://thenewamerican.com/freedom-index/legislator/congress/119/
https://thenewamerican.com/freedom-index/legislator/a000055/votes/report-scorecard-119-1/
*/
$fs_redirect = false;
$legacy_domain = $_SERVER['HTTP_HOST'];
$legacy_path = $_SERVER['REQUEST_URI'];

// Static redirects
if(rtrim(parse_url($legacy_path, PHP_URL_PATH), '/') === '/shortcut') {
	wp_redirect(home_url('/app'), 301);
	exit();
}

//Is this an image request? Can we return 404 without showing the page?
if(substr($legacy_path, -3,3) == 'jpg' || substr($legacy_path, -4,4) == 'jpeg' || substr($legacy_path, -3,3) == 'png' || substr($legacy_path, -3,3) == 'gif' || substr($legacy_path, -3,3) == 'ico' || substr($legacy_path, -4,4) == 'webp') {
	//I am getting MANY errors logged with links to the old site images. assets/sites/5/img/8864/7457-Kelly-Kortum-1-80x80-f50_0.jpg
	//But the old site is no longer active...so I'm curious if another site is pulling our images.
	//Can we also log the site that's requesting the image?
	$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'No Referer';
	wp_die('404: Image not found: ' . $legacy_path . ' | Referer: ' . $referer);
}

/* Need to test with current domain so we should go ahead and set up a more robust version
if(strpos($legacy_path, 'https://thefreedomindex.org/') !== false) {
	$fs_redirect = true;
}
*/
if(strpos($legacy_path, '/legislator/') !== false
 || strpos($legacy_path, '/report/') !== false
 || strpos($legacy_path, '/vote/') !== false
) {
	$fs_redirect = true;
}


//Don't redirect or save /wp-admin redirects - skip to normal 404 page
if($fs_redirect):
	$path = trim(parse_url($legacy_path, PHP_URL_PATH), '/');
	$redirect_to = null;
	// STEP 1: Check for exact match in legacy redirects table (FAST - cached from previous matches)
	// This is the caching mechanism - successful pattern matches are saved here so future requests can skip expensive pattern matching and go straight to database
	$redirect = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}fs_legacy_redirects WHERE legacy_path = %s AND status = 301 LIMIT 1",$legacy_path));
	if ($redirect) {
		$id = $redirect->id;
		$redirect_to = $redirect->target_slug;
		// Update existing record: increment hits by 1
		$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}fs_legacy_redirects SET hits = hits + 1 WHERE id = %d",$id));
	}else{
		/*STEP 2: Check for pattern matches (SLOWER - only runs if not in cache)
		* Match legacy URL patterns using parsing + lookup approach
		* Parses URL segments and resolves each independently to build target URL
		* 
		* Examples:
		* - /legislator/a000055/ -> /legislator/1020/
		* - /legislator/a000055/votes/report-scorecard-119-1/ -> /legislator/1020/session/14/report/37/
		* - /{state}/legislator/12345/ -> /legislator/{id}/
		* - /{state}/vote/{slug}/ -> /{gov}/vote/{id}/
		*/

		//https://freedomindex.us/legislator/congress/119/ => https://freedomindex.us/us/legislators/session/14/
		$skipgov = false;
		if(strpos($path, 'legislator/congress/') !== false) {
			$redirect_to .= '/us/legislators';
			$skipgov = true;
			$path = str_replace('legislator/congress/','congress/',$path);
		}

		// Parse URL into segments
		$segments = array_filter(explode('/', trim($path, '/')), function($seg) {
			return $seg !== '' && $seg !== 'freedom-index'; // Remove empty segments and TNA prefix
		});
		$segments = array_values($segments); // Re-index array
		if (empty($segments)) {
			return null;
		}

		//echo '<br>'.__LINE__.'<br>';print_r($segments);

		//IS THIS A STATE URL?
		$gov = 'US';
		$gov_arg = 'us';
		//Lower case array of state abbreviations
		$states = ['ak', 'al', 'ar', 'az', 'ca', 'co', 'ct', 'de', 'fl', 'ga', 'hi', 'ia', 'id', 'il', 'in', 'ks', 'ky', 'la', 'ma', 'md', 'me', 'mi', 'mn', 'mo', 'ms', 'mt', 'nc', 'nd', 'ne', 'nh', 'nj', 'nm', 'nv', 'ny', 'oh', 'ok', 'or', 'pa', 'ri', 'sc', 'sd', 'tn', 'tx', 'ut', 'va', 'vt', 'wa', 'wi', 'wv', 'wy'];
		if (in_array(strtolower($segments[0]), $states)) {
			$gov_arg = strtolower($segments[0]);
			$gov = strtoupper($segments[0]);
			//Remove the state segment from the array
			array_shift($segments);
		}

		//echo '<br>'.__LINE__.'<br>';print_r($segments);

		//Convert array into pairs: odd = key, even = value
		$args = [];
		$segment_count = count($segments);
		for ($i = 0; $i < $segment_count; $i += 2) {
			$key = $segments[$i];
			$value = ($i + 1 < $segment_count) ? $segments[$i + 1] : null;
			$args[$key] = $value;
		}

		/* EXAMPLES
		CONGRESS (V1)
		https://thefreedomindex.org/legislator/a000055/votes/report-scorecard-119-1/ => https://freedomindex.us/legislator/1020/session/14/report/37/
		https://thefreedomindex.org/vote/2025h107/ => https://freedomindex.us/us/vote/1164/
		https://thefreedomindex.org/legislator/a000055/votes/congress-119/ => https://freedomindex.us/legislator/1020/session/14/ 
		https://thefreedomindex.org/legislator/congress/119/ => 

		STATES (V2)
		https://thefreedomindex.org/co/report/2024/ => https://freedomindex.us/co/report/59/
		https://thefreedomindex.org/co/legislator/23965/votes/session-20231/ => https://freedomindex.us/legislator/2892/session/58/
		https://thefreedomindex.org/co/legislator/23965/votes/report-2024/ => https://freedomindex.us/legislator/2892/session/58/report/59/

		https://thefreedomindex.org/co/legislator/session/20231/party/d/role/rep/search/bacon/ => https://freedomindex.us/co/legislators/session/58/office/R/party/d/search/bacon/
		*/

		//Exclude gov if the target is /legislator/
		if(!array_key_exists('legislator',$args) && !$skipgov) {
			$redirect_to .= '/'.$gov_arg;
		}
		/*
		Handle state legislator lists
		https://thefreedomindex.org/ut/legislator/ = https://freedomindex.us/ut/legislators/
		https://thefreedomindex.org/ut/legislator/session/2023-11/ = https://freedomindex.us/ut/legislators/session/305/
		https://thefreedomindex.org/legislator/ = https://freedomindex.us/us/legislators/

		Handle Searches (query string stripped by parse_url; treated same as plain list URL)
		https://thefreedomindex.org/legislator/?search=comprovante+-+di = /us/legislators/
		https://thefreedomindex.org/ut/legislator/?search=comprovante+-+di = /ut/legislators/
		*/

		// Fix mis-parsed legislator list + filter URLs.
		// Pattern: /legislator/session/{slug}/... has 3 segments (odd), pairing gives {legislator:'session','slug':null}.
		// When legislator's "value" is actually a filter keyword, re-pair from that keyword onward.
		$list_filter_keys = ['session', 'party', 'role', 'search', 'state', 'chamber'];
		if (isset($args['legislator']) && in_array(strtolower((string)$args['legislator']), $list_filter_keys)) {
			$leg_idx = array_search('legislator', $segments);
			if ($leg_idx !== false) {
				$filter_segs = array_slice($segments, $leg_idx + 1);
				$filter_args = [];
				for ($fi = 0; $fi < count($filter_segs); $fi += 2) {
					$filter_args[$filter_segs[$fi]] = ($fi + 1 < count($filter_segs)) ? $filter_segs[$fi + 1] : null;
				}
				$args = array_merge(['legislator' => ''], $filter_args);
			}
		}

		foreach ($args as $key => $value) {
			switch ($key) {
				case 'legislator':
					//Handle similar pattern but wrong info: /legislator/346686/ is a legiscan ID != to any legislator ID.


					//Handle if blog view on old site /legislator/ or /wi/legislator/ {no slug = show all legislators}
					if($value == '') {
						// Always include state prefix for list view (/ut/legislators/ not /legislators/)
						$redirect_to = '/' . $gov_arg . '/legislators/';
					}else{
						$value = strtoupper($value);
						// Query fs_legislators for ID where either bioguid_id(V1) or legiscan_id(V2) = strtoupper($value)
						$legislator = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}fs_legislators WHERE (bioguide_id = %s OR legiscan_id = %s) LIMIT 1",$value,$value));
						if ($legislator) {
							$redirect_to .= '/legislator/'.$legislator->id;
						}
					}
					break;
				case 'votes':
					// https://freedomindex.us/legislator/a000055/votes/report-scorecard-119-1/
					// https://freedomindex.us/co/legislator/23965/votes/session-20231/
					// https://freedomindex.us/legislator/a000055/votes/congress-119/
					if (strpos($value, 'report-') !== false) {
						$value = str_replace('report-','',$value);
						//Query fs_reports for ID where slug = $value
						$report = $wpdb->get_row($wpdb->prepare("SELECT id,session_id FROM {$wpdb->prefix}fs_reports WHERE gov = %s AND slug = %s LIMIT 1",$gov,$value));
						if ($report) {
							$redirect_to .= '/session/'.$report->session_id.'/report/'.$report->id;
						}
					}else if (strpos($value, 'session-') !== false) {
						$value = str_replace('session-','',$value);
						//Query fs_sessions for ID where slug = $value
						$session = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}fs_sessions WHERE gov = %s AND slug = %s LIMIT 1",$gov,$value));
						if ($session) {
							$redirect_to .= '/session/'.$session->id;
						}
					}else if (strpos($value, 'congress-') !== false) {
						$value = str_replace('congress-','',$value);
						//Query fs_sessions for ID where slug = $value
						$session = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}fs_sessions WHERE gov = %s AND slug = %s LIMIT 1",$gov,$value));
						if ($session) {
							$redirect_to .= '/session/'.$session->id;
						}
					}
					break;
				case 'vote':
					// https://thefreedomindex.org/vote/2025h107/ V1 slug = manual term slug
					// https://freedomindex.us/co/vote/1439123/ V2 slug = legiscan_id
					// /us/vote/1167/
					// /us/vote/1173/
					// /wi/vote/

					//Handle if blog view on old site /vote/ or /wi/vote/ {no slug = show all votes}
					if($value == '') {
						$redirect_to .= '/votes/';
					}else{
						// Query fs_votes for ID where slug = $value
						$vote = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}fs_votes WHERE gov = %s AND slug = %s LIMIT 1",$gov,$value));
						if ($vote) {
							$redirect_to .= '/vote/'.$vote->id;
						}
					}

					break;
				case 'report':
					// https://thefreedomindex.org/co/report/2024/ => https://freedomindex.us/co/report/59/
					// https://thefreedomindex.org/report/scorecard-119-1/ => https://freedomindex.us/us/report/37/
					// Query fs_reports for ID where slug = $value

					//Handle if blog view on old site /report/ or /wi/report/ {no slug = show all reports}
					if($value == '') {
						$redirect_to .= '/reports/';
					}else{
						// Query fs_reports for ID where slug = $value
						$report = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}fs_reports WHERE gov = %s AND slug = %s LIMIT 1",$gov,$value));
						if ($report) {
							$redirect_to .= '/report/'.$report->id;
						}
					}
					break;
				case 'congress':
				case 'session':
					//Query fs_sessions for ID where slug = $value
					$session = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}fs_sessions WHERE gov = %s AND slug = %s LIMIT 1",$gov,$value));
					if ($session) {
						$redirect_to .= '/session/'.$session->id;
					}
					break;

				// https://thefreedomindex.org/co/legislator/session/20231/party/d/role/rep/search/bacon/

				// https://freedomindex.us/co/legislators/session/58/office/R/party/d/search/bacon/
				// https://freedomindex.us/us/legislators/state/co/session/14/office/S/party/d/search/john/
				case 'state':
					$redirect_to .= '/state/'.$value;
					break;
				case 'party':
					$redirect_to .= '/party/'.$value;
					break;
				case 'chamber':
					// V1 filter parameter - skip
					break;
				case 'role':
					// V2 filter parameter
					if($value == 'rep') {
						$value = 'R';
					}else if($value == 'sen') {
						$value = 'S';
					}
					$redirect_to .= '/role/'.$value;
					break;
				case 'search':
					$redirect_to .= '/search/'.$value;
					break;
/* /legislator/5666/pdf/scb NO URL CAN BE /pdf/scb
				case 'pdf':
					if($value == 'sca'){
						$redirect_to .= '/pdf/sca';
					}elseif($value == 'scb'){
						$redirect_to .= '/pdf/scb';
					}elseif($value == 'scc'){
						$redirect_to .= '/pdf/scc';
					}elseif($value == 'fia'){
						$redirect_to .= '/pdf/fia';
					}
					break;
*/
			}
		}

		/* /legislator/5666/pdf/scb NO URL CAN BE /pdf/scb
			If redirect_to starts with /pdf fall back to the $legacy_path without the /pdf/sc? value.
		*/
		if(substr($redirect_to, 0,4) == '/pdf') {
			$redirect_to = $legacy_path;
			$redirect_to = str_replace(['/pdf/scb','/pdf/sca','/pdf/scc','/pdf/fia'],'',$redirect_to);
		}

		//Save the redirect to the database
		if($redirect_to) {
			$result = $wpdb->insert(
				"{$wpdb->prefix}fs_legacy_redirects",
				[
					'gov' => $gov,
					'legacy_path' => $legacy_path,
					'target_slug' => $redirect_to,
					'hits' => 1,
				],
				['%s', '%s', '%s', '%d']
			);
		}
	}

	fs_log('404: '.$legacy_path.' TO '.$redirect_to);

	if($redirect_to) {
		wp_redirect($redirect_to);
		exit();
	}
endif;


//echo 'Redirect ' . $legacy_path . ' to '.$redirect_to;exit;

//NORMAL 404 PAGE
get_header();
?>
<!--Content start-->
<div class="container-xl p-0 m-0 mx-auto"><div id="legislator-search-results"></div></div>
<main id="content">
	<div class="container-xl">
		<div class="not-found py-5">
			<div class="row">
				<div class="col-12 col-sm-10 col-md-8 mx-auto">
					<div class="mb-4 text-center">
						<h1 class="display-3 display-1-lg"><?php _e( '404', 'bootnews' ); ?></h1>
						<h3 class="h2-md"><?php _e( 'Oops! That page can&rsquo;t be found.', 'bootnews' ); ?></h3>
					</div>
					<div class="post-content">

						<div class="row d-print-none mb-3">
							<div class="col-12 col-lg-10 col-xl-8 mx-auto">

<a href="<?php echo home_url(); ?>" class="btn btn-primary fw-bold w-100 text-center">Go to Home</a>

							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</main>
<!--End Content-->
<?php get_footer();?>
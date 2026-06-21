<?php
if (!defined('ABSPATH')) exit;
define( 'DONOTCACHEPAGE', true ); //Prevent WPRocket caching of the PDF part

/*
 * Legislator PDF Template
 * Handle multiple formats: Portrait (Freedom Index / Scorecard) and Landscape bi-fold scorecard.
 * A4 11x8.5 inches = 280x216mm = @72dpi = 792x612px
 * Icon fonts won't work in PDF so we need static images: thumbs-up/down black,white, red, green

Random Notes:
For detailed bill descriptions and thorough explanations of their constitutional merits or violations, scan the QR code above or visit thefreedomindex.org/me/.
 */
$pdf_parts_path = FI_PUBLIC_DIR . "pdf/";
$img_logo = FI_URL . 'assets/img/freedomindexus-60.png';

// Extract template variables
$leg = $legislator ?? null;
$report = $report ?? null;
$session_id = $session_id ?? null;
$pdf_format = $pdf_format ?? null; // PDF format/template from URL (sca, scb, scc, fia)
$gov = $gov ?? 'US';

if (!$leg || !$report) {
    status_header(404);
    echo '<h1>Report Not Found</h1>';
    exit;
}
$gov = $leg->gov;
$gov_name = fi_gov_name($gov);
if($gov == 'US'){
	$legBody = 'Congressional';
	$gov_text = 'U.S.';
}else{
	$legBody = 'Legislative';
	$gov_text = $gov;
}
$scorecard_about = "The {$legBody} Scorecard is a nationwide, nonpartisan educational program of The John Birch Society intended to inform voters about legislators' voting records. It does not promote any candidate or political party. Bills are chosen for their constitutional implications and taxpayer costs.";
$scorecard_cta = "Find out if your legislators vote for freedom at freedomindex.us.";
$fi_title = $legBody . ' Scorecard';
$fi_subtitle = $legBody . ' Scorecard based on the Constitution.';
$report_basedon = 'Based on the the U.S. Constitution';
$report_basedonp = 'Based on the Principles of the U.S. Constitution';

// Get legislator data
$leg_id = $leg->id;
$first_name = $leg->first_name ?? null;
$last_name = $leg->last_name ?? null;
//Adjust Vote Table Footer Font Size if last name is too long
if(strlen($last_name) > 16){
	$vote_table_footer_font_size = '12px';
}elseif(strlen($last_name) > 12){
	$vote_table_footer_font_size = '13px';
}else{
	$vote_table_footer_font_size = '14px';
}
$vote_table_footer_font_size = '20px';


$display_name = $leg->display_name ?? ($leg->first_name . ' ' . $leg->last_name);
$leg_slug = '-legislator-'.$leg_id;
$party = strtoupper($leg->party ?? null);
$district_id = $leg->district_id ?? null;
$freedom_score = $leg->score ?? null;
$chamber = $leg->chamber ?? null;
$chamber_title = ($gov ? fi_chamber_title($gov, $chamber) : null);
$chamber_label = $leg->chamber_label ?? null;
$leg_phone = $leg->meta['phone'] ?? null;

//Define PDF constants
define('PDF_PAGE_TITLE', $display_name . ' Freedom Score' );
define('PDF_KEYWORDS', 'Legislators, ' . $gov_name . ', ' . $display_name . ', Scorecard, Vote Record, Freedom Index, The John Birch Society, The New American');
define('FI_TEXT_SHARE','<strong>Share this Scorecard</strong> to inform others about your legislator\'s record on key votes.');
define("FI_TEXT_DISCLAIMER","U.S. Constitution, Amendment I --- 11 C.F.R. &sect;114(4)(c)(4) --- 616 F.2d 45 (2d Cir. 1980)");
define("FI_TEXT_SCORE_DISCLAIMER","The selected votes may not be reflective of legislators' overall records. Their cumulative scores will change as we add more votes. Please check regularly for updates.");
define("FI_TEXT_SCORE_INSUFFICIENT","*Insufficient votes cast to score.");

$server_url = $_SERVER['SERVER_NAME'];
$canonical_url = preg_replace('#/pdf/.*$#', '', $server_url);
define('PDF_CANONICAL_URL', $canonical_url);



//Websites are an array. Show the first one
$leg_website = $leg->meta['website'][0] ?? null;
if($leg_website){
	$leg_website_text = preg_replace(
		['#^https://#i', '#^www\.#i', '#/$#'],
		['', '', ''],
		$leg_website
	);
	$leg_website = $leg_website_text;
}
//Legislator Image
$image_id = $leg->image_id ?? null;
if($image_id){
	$img_html = fi_legislator_image($image_id,null, ['size' => [400,500],'crop' => [0.5,0], 'retina' => false]);
}else{
	$img_html = '';
}
/*
PDF report is specific to session so these values must override the current legislator values. 
i.e. Now senator but prince scorecard from previous session when they were a Representative.
*/
$sess = $leg->sessions[$session_id] ?? null;
$sscore = $sess->score ?? null;

$sgov = $sess->gov ?? null;
$sgov_name = $sess->gov_name ?? null;
if($sgov){
	$gov = $sgov;
	$gov_name = $sgov_name;
}

$schamber = $sess->chamber ?? null;
$schamber_label = $sess->chamber_label ?? null;
$schamber_title = $sess->chamber_title ?? null;
if($schamber){
	$chamber = $schamber;
	$chamber_label = $schamber_label;
	$chamber_title = $schamber_title;
}

$sstate = $sess->state ?? null;
if($sstate){
	$state = $sstate;
	$state_name = $sess->state_name ?? null;
}

$sparty = $sess->party ?? null;
$sparty_name = $sess->party_name ?? null;
if($sparty){
	$party = $sparty;
	//$party_name = fi_party_name($party);
}

//US Sen do not have districts, but all other gov-senators do
$sdistrict = $sess->district ?? null;
if($sdistrict){
	$district_id = $sdistrict;
}
if($district_id){
	$dist_info = fi_district_get($district_id); //stdClass Object ( [id] => 255 [gov] => US [name] => AL 4th [slug] => al-4 [name_short] => 4th )
	if($gov == 'US'){
		$represents = $state_name . ' ' . $dist_info->name_short;
	}else{
		$represents = $dist_info->name;
	}
}else{
	if($gov == 'US'){
		$represents = $state_name; //US Senators do not have districts but we show the state
	}else{
		$represents = '';
	}
}
$represents .= ' (' . $party . ')';

$url_shortcut = str_replace('https://', '', fi_get_legislator_short_url($leg_id));
ob_start();
?>
<table cellspacing="0" cellpadding="0" class="table table-borderless mb-1">
	<tr>
		<td class="leg-photo">
			<?= $img_html; ?>
		</td>
		<td class="leg-info">
			<div class="leg-name"><?= $display_name; ?></div>
			<table cellspacing="0" cellpadding="0" class="table table-borderless mb-0">
				<tr>
					<td>
						<div class="leg-gov"><?= $gov_name;?></div>
						<div class="leg-represents"><?= $chamber_title;?>, <?= $represents; ?></div>
						<?php if($leg_phone): ?>
						<div class="leg-phone"><span class="fw-normal">Phone:</span> <?= $leg_phone; ?></div>
						<?php endif; ?>
						<div class="leg-url"><?= $url_shortcut; ?></div>
					</td>
					<td class="leg-score-container">
						<?php if($freedom_score): ?>
						<div class="leg-score">
							<div class="fi-blue leg-score-number"><?= $freedom_score; ?></div>
							<div class="leg-score-title">Lifetime<br>Freedom<br>Score</div>
						</div>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<?php
$legislator_info_html = ob_get_clean();

////////////////////////////
// Get report payload
// Handle payload_json - may already be decoded as array or still be JSON string
$payload_raw = $report->payload_json ?? '{}';
$payload = is_array($payload_raw) ? $payload_raw : json_decode($payload_raw, true);

$report_format = $report->format ?? 'scorecard';
$report_title = $report->title ?? '';
if($gov == 'US'){
	$report_title = 'Congressional '.$report->title;
}else{
	//Staff has included state abbrevition in the title: CT Scorecard 2025
	//We must inject 'Legislative ' into the title
	if(strpos($report_title,'Legislative') === false){
		$report_title = str_replace('Scorecard', 'Legislative Scorecard', $report_title);
	}
}

$report_intro = wp_kses_post(wpautop($payload['content'] ?? ''));
$report_meta = $payload['meta'] ?? [];

/////////////////
// Get vote data
$ckey = strtolower($chamber);
$vote_ids = $payload['votes_'.$ckey.'_ordered'] ?? [];
if(empty($vote_ids)){
	$vote_ids = $payload['votes_'.$ckey] ?? [];
}
//Compile vote data
$count_cost = 0;
$votes = [];
foreach($vote_ids as $vid){
	$v = fi_vote_get($vid);
	//echo '<textarea style="width:100%; height:200px;">'; print_r($v); echo '</textarea>';
	$vm = json_decode($v->meta, true);
	$rc = fi_rollcall($v->id, $leg_id);
	$cast = $rc['cast'] ?? 'X';
	$cost = $vm['cost'] ?? '';

	$constitutional = $v->constitutional ?? '';
	$vote_format = fi_vote_format([
		'cast' => $cast,
		'constitutional' => $constitutional,
		'format' => 'full'
	]);
	$vote_cost = fi_vote_cost_format($cost);

	//How many significant cost values do we have?
	//if(!$vote_cost['minor']){
	//	$count_cost++;
	//}
	$has_cost = 0;
	if($vote_cost['raw'] != ''){
		$count_cost++;
		$has_cost = 1;
	}

	//260512: FIX: PHP Fatal error: Uncaught TypeError: fi_clean_content(): Argument #1 ($content) must be of type string, null given, called in /home2/jbs/public_html/wp-content/plugins/freedom-index/public/pdf/legislator.php on line 250 and defined in /home2/jbs/public_html/wp-content/plugins/freedom-index/core/formatting.php:24
	$description_short = $vm['description_short'] ?? '';
	$description_medium = $vm['description_medium'] ?? '';
	$description_long = $vm['description_long'] ?? '';

	$vd = [];
	$vd['title'] = $v->title;
	$vd['text_sm'] = !empty($description_short) ? fi_clean_content($description_short, ['exclude' => ['a']]) : '';
	$vd['text_md'] = !empty($description_medium) ? fi_clean_content($description_medium, ['exclude' => ['a']]) : '';
	$vd['text_lg'] = !empty($description_long) ? fi_clean_content($description_long, ['exclude' => ['a']]) : '';
	$vd['date_formatted'] = date('m/d/Y', strtotime($v->date_voted));
	$vd['vote_format'] = $vote_format;
	$vd['bill_number'] = $v->bill_number;
	$vd['cost'] = $vote_cost;
	$vd['has_cost'] = $has_cost;

	$votes[] = $vd;
}
//TESTING
if(isset($_GET['test']) && $_GET['test'] == 'votes'){
	echo '<textarea style="width:100%; height:800px;">'; print_r($votes); echo '</textarea>'; exit;
}

//Score the votes
$votes_good = 0;
$votes_counted = 0;
foreach($votes as $v){
	if($v['vote_format']['is_counted']){
		$votes_counted++;
		if($v['vote_format']['is_match']){
			$votes_good++;
		}
	}
}
$report_score = fi_score_calc($votes_good, $votes_counted);

/* Build Vote Text display options
$vote_texts_lg: Title + descriiption_long
$vote_table: Compact text table rows
IF $count_cost > 3 then show CPH column and CPH legend text ELSE show cost text
*/
$count = 0;
$vote_table = '';
//SHOW ALL COSTS
if($count_cost > 0){
//	$vote_table .= '<div class="text-center border-top table-cph-legend">CPH: Estimated annual cost per household.</div>';
}
$vote_table .= '<table id="vote-table" class="table table-borderless mb-0"><tr><th class="border-bottom"><span class="fw-normal me-3">'
	. fi_vote_img('good',[10,10]).' Constitutional</span><span class="fw-normal me-3">'
	. fi_vote_img('bad',[10,10]).' Unconstitutional</span><span class="fw-normal">'
	. fi_vote_img('none',[10,10]).' Did not Vote</span></th>';
if($count_cost > 2){
	$vote_table .= '<th class="border-bottom text-end text-nowrap">$/Year</th>';
}
$vote_table .= '<th class="border-bottom text-center">Vote</th></tr>';
$vote_texts_lg = [];
if(!empty($votes)){
	//Full Text Blocks
	foreach($votes as $v):
		$count++;
		//Build Vote Texts Large
		$vote_texts_lg[] = '<div class="vote-text-lg"><h5>'.$count.'. '.$v['title'].'</h5><div class="content">'.($v['text_md'] ?? $v['text_lg']).'</div></div>';

		//Compact Text Table Rows
		$cost = $v['cost'];
		$cost_html = '';
		$cost_sentence = '';

		//if($v['cost']['raw'] == '' || $v['cost']['minor'] == true){
		if($v['cost']['raw'] != ''){
			$cost_html = $cost['html'];
			$cost_sentence = $cost['sentence'];
		}

		$cast = strtoupper($v['vote_format']['cast_text']);
		$match = $v['vote_format']['is_match'];
		if($match){
			$match = 'good';
		}else{
			if($cast == 'NONE'){
				$match = 'none';
			}else{
				$match = 'bad';
			}
		}
		$vote_icon = fi_vote_img($match,[24,24]);

		//Remove any links or paragraphs from the text_sm
		$text_sm = fi_clean_content($v['text_sm'],['exclude' => ['a','p'],'autop' => false]);

		//Start row output
		$row = '<tr>';
		$row .= '<td><b>'.$count.'.</b> ' . $text_sm;
		if($count_cost > 2){
			$row .= '</td><td class="text-end text-nowrap fw-bold ps-2">' . $cost_html;
		}else{
			$row .= $cost_sentence;
		}
		$row .=	'</td>';
		$row .=	'<td style="padding-left:8px;"><div class="vote-cast text-center">'.$cast.'</div>
				<div class="vote-image text-center">'.$vote_icon.'</div></td></tr>';
		$vote_table .= $row;

	endforeach;
}
$vote_table .= '</table>';
//$vote_table .= '<div class="report-score">'.$last_name.' voted for <b>freedom</b> on <span>'.$report_score.'%</span> of the votes below.</div>';
//$vote_table .= '<div class="vote-table-score">'.$report_title . ' Score: '.$report_score.'%</div>';
//$vote_table .= '<div class="vote-table-score">Of the votes above, '.$last_name.' voted for freedom <span class="fw-bold">'.$report_score.'%.</span></div>';
$vote_table .= '<div class="vote-table-score">Scorecard Votes: <span class="fw-bold">'.$report_score.'%</span></div>';
$vote_sep = '<div class="vote-separator"></div>';



// Build URLs
$url_qr = home_url('/legislator/' . $leg_id . '/');
if ($session_id) {
    $url_qr .= 'session/' . $session_id . '/';
}
if ($report->id) {
    $url_qr .= 'report/' . $report->id . '/';
	$report_slug = '-report-'.$report->id;
}else{
	$report_slug = '';
}
//$url_qr .= '?utm_source=qr&utm_medium=pdf'; //CAUSES QR CODE TO FAIL

if(isset($_GET['pbug']) && $_GET['pbug'] == 1){
	echo 'QR CODE URL: '.$url_qr;
}

// PDF contact(s) from Print modal: resolved in handler from GET contacts= and u=, passed as $pdf_contacts (array of {name, phone, email}).
$pdf_contacts = isset($pdf_contacts) && is_array($pdf_contacts) ? $pdf_contacts : [];

$pdf_contacts_title = 'Want to help?';
//$pdf_contacts_text = 'Contact the local JBS member'.(count($pdf_contacts) > 1 ? 's' : '').' below to learn how you can stand up for freedom in your community.';
//$pdf_contacts_text = 'Learn how you can stand up for freedom in your community.';


//Gender
$pronoun = 'they';
if(isset($leg->meta) && isset($leg->meta['gender']) && !empty($leg->meta['gender'])){
	if($leg->meta['gender'] == 'female'){
		$pronoun = 'she';
	}elseif($leg->meta['gender'] == 'male'){
		$pronoun = 'he';
	}
}


//QR Codes
$qr_codes = [
	'scorecard' => [
		'title' => 'Does Your Legislator Vote for Freedom?',
		'text' => 'Scan to view '.$display_name.'\'s voting history.<div class="fw-bold">'.$url_shortcut.'</div>',
		'url' => urlencode($url_qr),
	],
	'tools' => [
		'title' => 'View the Freedom Toolbox',
		//'text' => 'Scan to learn more about the Freedom Index, the Constitution, and the principles of liberty.<div class="fw-bold">freedomindex.us/tools</div>',
		//'text' => 'Scan to learn more about the Freedom Index and its methodology, view and subscribe to legislative alerts, get informed about the U.S. Constitution and America\'s founding principles, and more.<div class="fw-bold">freedomindex.us/tools</div>',
		'text' => 'Scan to learn more about the Freedom Index, view legislative alerts, and deepen your understanding of the U.S. Constitution and America\'s founding principles. Visit <span class="fw-bold">freedomindex.us/tools</span>',
		'url' => urlencode('https://freedomindex.us/tools/?utm_source=qr'),
	],

/*
	'scorecard' => [
		'title' => 'Does Your Legislator Vote for Freedom?',
		'text' => 'Scan to view '.$display_name.'\'s voting history to see how '.$pronoun.' has voted on key issues.',
		'url' => urlencode($url_qr),
	],
	'alerts' => [
		'title' => 'Get Legislative Alerts',
		'text' => 'Scan to view and subscribe to our legislative action alerts.',
		'url' => urlencode('https://jbs.org/alerts/?utm_source=qr&utm_medium=pdf'),
	],
	'blueprint' => [
		'title' => 'The Blueprint for Liberty',
		'text' => "What does the U.S. Constitution say about the most important issues facing our nation?",
		'url' => urlencode('https://jbs.org/constitution/blueprint/?utm_source=qr&utm_medium=pdf'),
	],
	'methodology' => [
		'title' => 'How Do We Choose Votes?',
		'text' => 'What criteria do we use when selecting votes? Why do these votes matter? Learn more about the methodology we use to choose votes that reveal where legislators really stand.',
		'url' => urlencode('https://freedomindex.us/about/?utm_source=qr&utm_medium=pdf'),
	],
	'constitution' => [
		'title' => 'The Constitution Is the Solution',
		'text' => 'Learn more about the U.S. Constitution and our founding principles to protect your rights.',
		'url' => urlencode('https://jbs.org/constitution/?utm_source=qr&utm_medium=pdf'),
	],
	'overview' => [
		'title' => 'Overview of America',
		'text' => "<p>Learn more about America's founding principles and what made our country great.</p>",
		'url' => urlencode('https://gojt.us/rqq7?utm_source=qr&utm_medium=pdf'),
	],
	*/
];


/* staff decided to omit 260310
$quote_declaration_of_independence = '<div class="text-di">We hold these truths to be self-evident, that all men are created equal, that they are endowed by their Creator with certain unalienable Rights, that among these are Life, Liberty and the pursuit of Happiness.--That to secure these rights, Governments are instituted among Men, deriving their just powers from the consent of the governed.</div>
<div class="text-di-att">&mdash; Declaration of Independence</div>';

$quote_debt = '<div class="mx-auto treasure-debt-quote">Do not be one who shakes hands in pledge or puts up security for debts; if you lack the means to pay, your very bed will be snatched from under you.<div class="text-debt-att">&mdash; Proverbs 22:26-27</div></div>';
*/

//Construct the PDF filename
$pdf_filename = 'Freedom-Index-'.$gov.$leg_slug.$report_slug.'-'.$pdf_format;
$pdf_file_path = FI_DIR_PDF.$pdf_filename.'.pdf';

//Determine the page orientation
switch($pdf_format){
	case 'sca':
		$orientation = 'P';
		break;
	default:
		$orientation = 'L';
		break;
}

$format_file = FI_PUBLIC_DIR . 'pdf/legislator-'.$pdf_format.'.php';

require_once FI_DIR . 'public/pdf/pdf_output.php';
<?php
if (!defined('ABSPATH')) exit;
define( 'DONOTCACHEPAGE', true ); //Prevent WPRocket caching of the PDF part


$gov = $gov ?? 'US';
$gov_name = fi_gov_name($gov);
//Adapt descriptions based on state or federal government
if($gov === 'US'){
	$gov_name = 'U.S.';
	$intro_text = 'The Freedom Index tracks how well members of Congress uphold constitutional limits<br>on government, fiscal responsibility, national sovereignty, and non-intervention abroad.';
}else{
	//$intro_text = 'The Freedom Index tracks how well state legislators uphold constitutional limits on government,<br>fiscal responsibility, individual liberty, and the powers reserved to the states and the people.';
	$intro_text = 'The Freedom Index tracks how well state legislators uphold constitutional principles,<br>including limited government, fiscal responsibility, individual liberty, and state sovereignty.';
}

// Extract template variables
$legislators = $legislators ?? [];
$session_id = $session_id ?? null;
$session_obj = $session_obj ?? null;
$filters = $filters ?? [];

if (empty($legislators) || !$session_obj) {
    status_header(404);
    echo '<h1>No Legislators Found</h1>';
    exit;
}

$session_name = $session_obj->name ?? '';

// Build filter description
$filter_parts = [];
if (!empty($filters['chamber'])) {
	$chambers = fi_chamber_options($gov);
	$chamber_plural = $chambers[$filters['chamber']]['plural'];
    $filter_parts[] = $chamber_plural;
}else{
	$chamber_plural = 'Legislators';
}
if (!empty($filters['party_slug'])) {
    $party_name = fi_party_name($filters['party_slug']);
    if ($party_name) {
        $filter_parts[] = $party_name;
    }
}
if (!empty($filters['state'])) {
    $filter_parts[] = strtoupper($filters['state']);
}
if (!empty($filters['search'])) {
    $filter_parts[] = 'Search: ' . esc_html($filters['search']);
}
$filter_description = !empty($filter_parts) ? ' (' . implode(', ', $filter_parts) . ')' : '';
$cacheKey = 'pdf/legislators-'.$pdf_format.'-'.$gov.'-'.$chamber_plural.'-'.implode('-', $filters);

$pdf_filename = 'Freedom-Index-'.strtoupper($gov).'-Legislators';
$pdf_filename .= '-' . str_replace(' ', '-', $session_name);
$pdf_filename .= !empty($filter_parts) ? '-'.implode('-', $filter_parts) : '';
$pdf_file_path = FI_DIR_PDF.$pdf_filename.'.pdf';


define('PDF_PAGE_TITLE', $gov_name.' '.$chamber_plural.' - '.$session_name);
define('PDF_KEYWORDS', 'Legislators, ' . $gov_name . ', ' . $session_name . ', ' . $filter_description . 'Scorecard, Vote Record, Freedom Index, The John Birch Society, The New American');

//Example: https://votestellthetruth.us/us/legislators/state/ak/session/14/chamber/S/party/R/pdf/cards
//Get the server URL and remove "pdf/*"
$server_url = $_SERVER['SERVER_NAME'];
$canonical_url = preg_replace('#/pdf/.*$#', '', $server_url);
define('PDF_CANONICAL_URL', $canonical_url);

$format_file = FI_PUBLIC_DIR . 'pdf/legislators-'.$pdf_format.'.php';
$orientation = 'P';
require_once FI_DIR . 'public/pdf/pdf_output.php';
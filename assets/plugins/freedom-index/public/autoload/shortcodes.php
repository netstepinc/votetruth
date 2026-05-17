<?php if(!defined('ABSPATH')) exit;

/* Shortcodes for the Freedom Index
- fi_latest_freedom_index_pdf
*/

/* fi_latest_freedom_index_pdf — uses fi_report_latest_freedom_index() (format column). */
function fi_latest_freedom_index_pdf($args = []) {
	$args = shortcode_atts(array(
		'class' => 'btn-sm btn-outline-danger fw-bold',
		'text' => 'Download the latest Freedom Index',
	), $args);
	$report = fi_report_latest_freedom_index();
	if($report){
		$payload = json_decode($report->payload_json ?? '', true);
		$report_pdf_url = $payload['report_pdf_url'] ?? '';
		if($report_pdf_url){
			return '<a href="'.$report_pdf_url.'" class="btn '.$args['class'].' w-100" target="_blank"><i class="fas fa-file-pdf me-2"></i>'.$args['text'].'</a>';
		}
	}
}
add_shortcode('fi_latest_freedom_index_pdf', 'fi_latest_freedom_index_pdf');
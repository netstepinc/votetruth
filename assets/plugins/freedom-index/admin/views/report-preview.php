<?php if (!defined('ABSPATH')) {exit;}

global $wpdb;

$votes = $wpdb->get_results($wpdb->prepare(
	"SELECT v.*, s.name as session_name, s.gov 
		FROM {$wpdb->prefix}fi_votes v
		INNER JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
		WHERE v.id IN (" . implode(',', array_map('absint', $vote_ids)) . ")
		ORDER BY v.date ASC",
	$vote_ids
));

$html = '<div class="fi-report-preview-content">';

// Report header
$html .= '<div class="fi-report-header">';
$html .= '<h1>Freedom Index Report</h1>';
$html .= '<p class="fi-report-meta">Session: ' . esc_html($votes[0]->session_name ?? '') . '</p>';
$html .= '</div>';

// Introduction text
if (!empty($options['intro_text'])) {
	$html .= '<div class="fi-report-intro">';
	$html .= wp_kses_post($options['intro_text']);
	$html .= '</div>';
}

// Votes
$html .= '<div class="fi-report-votes">';
$vote_number = 1;

foreach ($votes as $vote) {
	$html .= '<div class="fi-vote-item">';
	
	if ($options['number_votes'] ?? true) {
		$html .= '<h3>Vote ' . $vote_number . ': ' . esc_html($vote->bill_number ?? $vote->slug ?? 'N/A') . '</h3>';
	} else {
		$html .= '<h3>' . esc_html($vote->bill_number ?? $vote->slug ?? 'N/A') . '</h3>';
	}
	
	$html .= '<p><strong>' . esc_html($vote->title) . '</strong></p>';
	$chamber = $vote->chamber ?? '';
	$gov = $vote->gov ?? 'US';
	$chamber_label = $chamber ? fi_chamber_label($gov, $chamber) : '';
	$html .= '<p>Date: ' . esc_html($vote->date_voted ?? $vote->date ?? '') . ' | Chamber: ' . esc_html($chamber_label) . '</p>';
	$html .= '<p>Good Position: ' . esc_html($vote->constitutional) . '</p>';
	
	if (!empty($vote->description)) {
		$html .= '<div class="fi-vote-description">';
		$html .= wp_kses_post($vote->description);
		$html .= '</div>';
	}
	
	$html .= '</div>';
	$vote_number++;
}

$html .= '</div>';

// Contact information
if ($options['show_contact'] ?? true) {
	$html .= '<div class="fi-report-contact">';
	$html .= '<h3>Contact Your Legislators</h3>';
	$html .= '<p>Use this report to contact your elected officials and let them know how they voted on these important issues.</p>';
	$html .= '</div>';
}

$html .= '</div>';

return $html;
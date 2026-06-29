<?php if (!defined('ABSPATH')) { exit; }

/**
 * Render reports page
 */
function fi_admin_reports_render(): void {
	include __DIR__ . '/../views/reports.php';
}

/**
 * Render report builder
 */
function fi_admin_reports_render_edit(): void {
	include __DIR__ . '/../views/report-edit.php';
}

/**
 * No-op stub kept because views/reports.php calls this before scope is available.
 * Actual save is handled via admin_init in actions.php (fi_admin_actions_handle).
 */
function fi_admin_reports_maybe_handle_save_early(): void {
	return;
}

/**
 * Handle report save
 */
function fi_admin_reports_handle_save(array $scope): void {
	$report_id = isset($_POST['report_id']) ? absint($_POST['report_id']) : null;
	if (!wp_verify_nonce($_POST['fi_report_nonce'], 'fi_save_report')) {
		wp_die('Security check failed');
	}
	
	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die('Insufficient permissions');
	}
	
	// Build payload_json using ReportsPayload class (similar to LegislatorsMeta)
	$existing_report = $report_id ? fi_report_get($report_id) : null;
	$existing_payload = null;
	if ($existing_report && !empty($existing_report['payload_json'])) {
		$existing_payload = json_decode($existing_report['payload_json'], true);
	}
	
	$submitted_data = [
		'intro_text' => $_POST['intro_text'] ?? '',
		'report_format' => $_POST['report_format'] ?? 'scorecard',
		'report_cph' => $_POST['report_cph'] ?? 'hide',
		'vote_start' => $_POST['vote_start'] ?? '1',
		'contact_location' => $_POST['contact_location'] ?? 'back',
		'constitution_qr' => $_POST['constitution_qr'] ?? 'none',
		'fi_vote_paging' => $_POST['fi_vote_paging'] ?? '2,3,3,2',
		'report_pdf_url' => $_POST['report_pdf_url'] ?? '',
		'selected_votes_h' => $_POST['selected_votes_h'] ?? [],
		'selected_votes_s' => $_POST['selected_votes_s'] ?? [],
		'votes_h_order' => $_POST['votes_h_order'] ?? [],
		'votes_s_order' => $_POST['votes_s_order'] ?? [],
	];

	$submitted_data = fi_prepare_richedit_save($submitted_data);

	$payload = fi_report_payload_build($submitted_data, $existing_payload);
	
	$format = in_array($submitted_data['report_format'] ?? '', ['scorecard', 'freedomindex'], true)
		? $submitted_data['report_format'] : 'scorecard';
	$report_data = [
		'title' => sanitize_text_field($_POST['report_title']),
		'title_menu' => sanitize_text_field($_POST['title_menu']),
		'session_id' => absint($_POST['session_id']),
		'gov' => $existing_report['gov'] ?? $scope['gov'],
		'payload_json' => json_encode($payload),
		'format' => $format,
		'status' => sanitize_text_field($_POST['status'] ?? 'draft'),
		'date_publish' => !empty($_POST['date_publish']) ? sanitize_text_field($_POST['date_publish']) : null,
		'owner_user_id' => get_current_user_id()
	];

//echo 'REPORT ID: ' . $report_id . '; '; print_r($report_data); var_dump($_POST); exit;
/* Debug log the report data before saving
REPORT ID: 244; 
Array ( [title] => 2025-2 MT Legislative Scorecard [title_menu] => 2025-2 Scorecard [session_id] => 192 [gov] => MT 
[payload_json] => {"content":"The following scorecard lists several key votes in the Montana Legislature in 2025 and ranks state representatives and senators based on their fidelity to (U.S.) constitutional and limited-government principles.",
"format":"scorecard","cph":"hide","vote_start":"1","contact":"back","constitution_qr":"none",
"fi_vote_paging":"2,3,3,2","votes_h":[3623,3622,3624,3620,3621,3619],"votes_s":[3625,3626,3629,3627,3628,3630],
"votes_h_order":[3623,3622,3624,3620,3621,3619],"votes_s_order":[3625,3626,3629,3627,3628,3630],"report_pdf_url":""}
[format] => scorecard [status] => publish [date_publish] => 2026-04-27 [owner_user_id] => 1 ) 

array(19) { ["fi_report_nonce"]=> string(10) "ab52a57a15" ["_wp_http_referer"]=> string(68) "/wp-admin/admin.php?page=fi-reports&action=edit&report_id=244&gov=MT" ["report_id"]=> string(3) "244" ["report_title"]=> string(31) "2025-2 MT Legislative Scorecard" ["title_menu"]=> string(16) "2025-2 Scorecard" ["session_id"]=> string(3) "192" ["status"]=> string(7) "publish" ["date_publish"]=> string(10) "2026-04-27" ["report_format"]=> string(9) "scorecard" ["vote_start"]=> string(1) "1" ["report_cph"]=> string(4) "hide" ["contact_location"]=> string(4) "back" ["constitution_qr"]=> string(4) "none" ["fi_vote_paging"]=> string(7) "2,3,3,2" ["intro_text"]=> string(211) "The following scorecard lists several key votes in the Montana Legislature in 2025 and ranks state representatives and senators based on their fidelity to (U.S.) constitutional and limited-government principles." ["selected_votes_h"]=> array(6) { [0]=> string(4) "3623" [1]=> string(4) "3622" [2]=> string(4) "3624" [3]=> string(4) "3620" [4]=> string(4) "3621" [5]=> string(4) "3619" } ["votes_h_order"]=> array(6) { [0]=> string(4) "3623" [1]=> string(4) "3622" [2]=> string(4) "3624" [3]=> string(4) "3620" [4]=> string(4) "3621" [5]=> string(4) "3619" } ["selected_votes_s"]=> array(6) { [0]=> string(4) "3625" [1]=> string(4) "3626" [2]=> string(4) "3629" [3]=> string(4) "3627" [4]=> string(4) "3628" [5]=> string(4) "3630" } ["votes_s_order"]=> array(6) { [0]=> string(4) "3625" [1]=> string(4) "3626" [2]=> string(4) "3629" [3]=> string(4) "3627" [4]=> string(4) "3628" [5]=> string(4) "3630" } }
*/

	$saved_id = fi_report_save($report_data, $report_id);

	if (!$saved_id) {
		wp_die('Failed to save report');
	}

	add_settings_error('fi_reports', 'report_saved', 'Report saved successfully.', 'updated');

	wp_safe_redirect(fi_admin_url('fi-reports', [
		'action'    => 'edit',
		'report_id' => (int) $saved_id,
	]));
	exit;
}

/**
 * Default report blueprint
 */
function fi_admin_reports_get_defaults(array $scope): array {
	return [
		'id' => null,
		'gov' => $scope['gov'] ?? 'US',
		'session_id' => $scope['session_id'] ?? null,
		'title' => '',
		'slug' => '',
		'format' => 'scorecard',
		'intro_text' => '',
		'selected_votes' => json_encode([]),
		'show_contact' => 1,
		'contact_location' => 'bottom',
		'number_votes' => 1,
		'show_scores' => 1,
	];
}

/**
 * Vote IDs already used in other reports for the same session (exclude current report).
 * Used to show only "unassigned" votes in report-edit vote selection.
 *
 * @param int $session_id Session ID
 * @param int $exclude_report_id Report ID to exclude (current report)
 * @return int[] Vote IDs
 */
function fi_admin_reports_get_vote_ids_used_in_session(int $session_id, int $exclude_report_id = 0): array {
	global $wpdb;
	$table = $wpdb->prefix . 'fi_reports';
	$exclude_report_id = absint($exclude_report_id);
	$session_id = absint($session_id);
	if (!$session_id) {
		return [];
	}
	$where = $exclude_report_id > 0
		? $wpdb->prepare('session_id = %d AND id != %d', $session_id, $exclude_report_id)
		: $wpdb->prepare('session_id = %d', $session_id);
	$rows = $wpdb->get_results("SELECT id, payload_json FROM {$table} WHERE {$where}", ARRAY_A);
	if (!is_array($rows) || empty($rows)) {
		return [];
	}
	$ids = [];
	foreach ($rows as $row) {
		$payload = isset($row['payload_json']) ? json_decode($row['payload_json'], true) : null;
		if (!is_array($payload)) {
			continue;
		}
		$payload = fi_report_payload_normalize($payload);
		foreach (['votes_h', 'votes_s'] as $key) {
			if (!empty($payload[$key]) && is_array($payload[$key])) {
				foreach ($payload[$key] as $vid) {
					$ids[] = (int) $vid;
				}
			}
		}
	}
	return array_values(array_unique($ids));
}

/**
 * Get report statistics for a government
 */
function fi_admin_reports_get_stats(string $gov): array {
	$stats = fi_reports_stats($gov, null, true);
	return [
		'total' => (int) ($stats['total'] ?? 0),
		'publish' => (int) ($stats['publish'] ?? 0),
		'draft' => (int) ($stats['draft'] ?? 0),
	];
}

/**
 * Generate report preview HTML
 */
function fi_admin_reports_generate_preview_html(array $vote_ids, array $options): string {
	include __DIR__ . '/../views/report-preview.php';
}

/**
 * Generate report PDF
 */
function fi_admin_reports_generate_pdf(array $data): ?string {
	// This would integrate with MPDF or similar PDF generation library
	// For now, return a placeholder URL
	
	$report_id = wp_generate_uuid4();
	$upload_dir = wp_upload_dir();
	$pdf_path = $upload_dir['path'] . '/fi-report-' . $report_id . '.pdf';
	$pdf_url = $upload_dir['url'] . '/fi-report-' . $report_id . '.pdf';
	
	// In a real implementation, this would:
	// 1. Generate the HTML content
	// 2. Use MPDF to convert to PDF
	// 3. Save to the uploads directory
	// 4. Return the URL
	
	// For now, create a placeholder file
	file_put_contents($pdf_path, 'PDF placeholder - would contain actual report content');
	
	return $pdf_url;
}


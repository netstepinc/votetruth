<?php
if (!defined('ABSPATH')) exit;

/**
 * Legiscan Add Votes Partial
 * View showing bills with nested roll_calls, allowing granular import
 * Uses compiled data if available for better performance
 */

$fi_votes_dir = $data_dir_for_partial . 'fi/';
	
// Get session ID for this dataset (if available)
$session_id_for_votes = null;
if (!empty($dataset_data['session_id'])) {
	$legiscan_session = fi_session_get_by_legiscan_id((int) $dataset_data['session_id'], $current_gov);
	if ($legiscan_session) {
		$session_id_for_votes = $legiscan_session->id;
	}
}

// Simple lookup: Query votes for this session and create array of legiscan_rcid => vote_id
// Only include votes that have a legiscan_rcid set
$fi_votes = []; // Format: roll_call_id => vote_id
if (!empty($session_id_for_votes)) {
	global $wpdb;
	$votes_query = $wpdb->prepare(
		"SELECT id, legiscan_rcid FROM {$wpdb->prefix}fi_votes 
		WHERE session_id = %d AND legiscan_rcid IS NOT NULL AND legiscan_rcid != ''",
		$session_id_for_votes
	);
	$votes_results = $wpdb->get_results($votes_query);
	
	foreach ($votes_results as $vote_row) {
		if (!empty($vote_row->legiscan_rcid)) {
			$fi_votes[(int)$vote_row->legiscan_rcid] = (int)$vote_row->id;
		}
	}
}
?>
<div class="table-responsive">
	<table class="table table-hover align-middle">
	<thead>
		<tr>
				<th scope="col" style="width:200px;">Bill Number</th>
				<th scope="col" style="width:30%;">Title</th>
				<th scope="col">Description</th>
		</tr>
	</thead>
	<tbody id="the-list">
		<?php
$b = 0;
$v = 0;
// Load all files from /fi/ directory
$fi_files = glob($fi_votes_dir . '*.json');
foreach ($fi_files as $fi_file):
	$file_content = @file_get_contents($fi_file);
	if ($file_content === false) continue;
	
	$bill_data = @json_decode($file_content, true);
	if (!$bill_data || empty($bill_data['bill_id'])) continue;
	
	$bill_id = $bill_data['bill_id'];
	$bill_roll_calls_raw = $bill_data['votes'] ?? [];

	if (!$bill_id || empty($bill_roll_calls_raw)) continue;
	
	// Convert roll_calls from associative array to indexed array and add result field
	$bill_roll_calls = [];
	foreach ($bill_roll_calls_raw as $roll_call) {
		// Add result field derived from passed (0/1) -> Pass/Fail
		$roll_call['result'] = (!empty($roll_call['passed']) && $roll_call['passed'] == 1) ? 'Pass' : 'Fail';
		$bill_roll_calls[] = $roll_call;
	}
				
				$b++;
				$url_bill = $bill_data['state_link'] ?? $bill_data['url'] ?? '';
				?>
				<tr>
					<td><strong><?php echo esc_html($bill_data['bill_number'] ?? ''); ?></strong></td>
					<td>
			<?php echo ($url_bill ? '<a href="' . esc_url($url_bill) . '" target="_blank">' : ''); ?>
							<strong><?php echo esc_html($bill_data['title'] ?? ''); ?></strong>
			<?php echo ($url_bill ? '</a>' : ''); ?>
		</td>
		<td>
			<?php echo (!empty($bill_data['description']) ? esc_html($bill_data['description']) : ''); ?>
					</td>
				</tr>
	<?php if (!empty($bill_roll_calls)): ?>
					<tr>
			<td colspan="3" class="ps-5">
				<div class="table-responsive">
					<table class="table table-sm table-hover align-middle">
								<thead>
									<tr>
								<th scope="col" style="width:100px;">Action</th>
								<th scope="col" style="width:80px;">RollCallID</th>
								<th scope="col" style="width:80px;">BillID</th>
								<th scope="col" style="width:120px;">Date</th>
								<th scope="col">Description</th>
								<th scope="col" style="width:70px;">Yea</th>
								<th scope="col" style="width:70px;">Nay</th>
								<th scope="col" style="width:70px;">None</th>
								<th scope="col" style="width:70px;">Absent</th>
								<th scope="col" style="width:70px;">Total</th>
								<th scope="col" style="width:70px;">Result</th>
									</tr>
								</thead>
								<tbody id="the-sublist">
									<?php
						foreach ($bill_roll_calls as $roll_call) {
											$v++;
											$roll_call_id = $roll_call['roll_call_id'] ?? null;
											if (!$roll_call_id) continue;
											
							// Simple lookup: check if this roll_call_id exists in fi_votes array
							$existing_vote_id = isset($fi_votes[$roll_call_id]) && !empty($roll_call_id) ? $fi_votes[$roll_call_id] : null;
							$vote_exists = !empty($existing_vote_id);
							
							if ($vote_exists) {
								$row_action = '<a href="' . esc_url(add_query_arg([
															'page' => 'fi-votes',
															'action' => 'edit',
															'vote_id' => $existing_vote_id,
															'gov' => $current_gov,
								], admin_url('admin.php'))) . '" class="btn btn-sm btn-success text-white fw-bold text-nowrap" target="_blank">View Vote #' . esc_html($existing_vote_id) . '</a>';
								$css_row = ' class="table-success"';
							} else {
								$row_action = (function() use ($current_gov, $current_fetch, $current_subtask, $roll_call_id, $dataset_data, $bill_data) {
									$action_url = admin_url('admin-post.php');
									$ls_session_id = (int) ($dataset_data['session_id'] ?? 0); // Legiscan session ID
									ob_start();
									?>
									<form method="post" action="<?php echo esc_url($action_url); ?>" class="d-inline">
										<?php wp_nonce_field('fi_legiscan_add_vote'); ?>
										<input type="hidden" name="action" value="fi_legiscan_add_vote">
										<input type="hidden" name="fetch" value="<?php echo esc_attr($current_fetch); ?>">
										<input type="hidden" name="subtask" value="<?php echo esc_attr($current_subtask); ?>">
										<input type="hidden" name="LS_session_id" value="<?php echo esc_attr($ls_session_id); ?>">
										<input type="hidden" name="roll_call_id" value="<?php echo esc_attr((int) $roll_call_id); ?>">
										<input type="hidden" name="bill_number" value="<?php echo esc_attr($bill_data['bill_number'] ?? ''); ?>">
										<button type="submit" class="btn btn-sm btn-primary">Add Vote</button>
									</form>
									<?php
									return ob_get_clean();
								})();
								$css_row = '';
							}
							?>
							<tr id="rc-<?php echo esc_attr((int) $roll_call_id); ?>"<?php echo $css_row; ?>>
								<td><?php echo $row_action; ?></td>
												<td><?php echo esc_html($roll_call_id); ?></td>
												<td><?php echo esc_html($roll_call['bill_id']); ?></td>
												<td><?php echo esc_html($roll_call['date']); ?></td>
												<td><?php echo esc_html($roll_call['desc']); ?></td>
												<td><?php echo esc_html($roll_call['yea']); ?></td>
												<td><?php echo esc_html($roll_call['nay']); ?></td>
												<td><?php echo esc_html($roll_call['nv']); ?></td>
												<td><?php echo esc_html($roll_call['absent']); ?></td>
												<td><?php echo esc_html($roll_call['total']); ?></td>
												<td><?php echo esc_html($roll_call['result']); ?></td>
											</tr>
							<?php
						}
						?>
								</tbody>
							</table>
				</div>
						</td>
					</tr>
				<?php else: ?>
					<tr>
			<td colspan="2" class="ps-5 text-muted">No votes found for this bill yet.</td>
					</tr>
				<?php endif; ?>
		<?php endforeach; ?>
	</tbody>
	<tfoot>
			<tr class="table-warning">
				<td colspan="9" class="fw-bold">
				<?php echo $b; ?> Bills and <?php echo $v; ?> Votes Found.
			</td>
		</tr>
	</tfoot>
</table>
</div>
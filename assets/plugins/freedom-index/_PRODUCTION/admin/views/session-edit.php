<?php if (!defined('ABSPATH')) {exit;}

$is_edit = ($action === 'edit');
$session_id = $is_edit ? absint($_GET['session_id'] ?? 0) : 0;

if ($is_edit && !$session_id) {
	wp_die('Missing session ID.');
}

$session = $is_edit ? fi_session_get($session_id) : fi_admin_sessions_get_defaults($scope);
if ($is_edit && !$session) {
	wp_die('Session not found.');
}

$gov = strtoupper($scope['gov'] ?? 'US');
$parent_options = fi_admin_sessions_get_parent_options($gov, $session_id);
$extra_meta = fi_admin_sessions_get_extra_meta($session);
$form_action = $is_edit
	? fi_admin_edit_session_url($session_id)
	: fi_admin_url('fi-sessions', ['action' => 'add']);
$page_title = $is_edit ? 'Edit Session' : 'Add Session';


$session_id = $session->id ?? 0;
$is_edit = !empty($session_id);
$extra_meta = $extra_meta ?? [];

// Get connected records for delete validation
$connected_votes = [];
$connected_reports = [];
$child_sessions = [];
$has_connected_records = false;

if ($is_edit) {
	global $wpdb;
	
	// Get votes
	$connected_votes = $wpdb->get_results($wpdb->prepare(
		"SELECT id, title, bill_number, chamber FROM {$wpdb->prefix}fi_votes WHERE session_id = %d ORDER BY date_voted DESC",
		$session_id
	));
	
	// Get reports
	$connected_reports = $wpdb->get_results($wpdb->prepare(
		"SELECT id, title, slug FROM {$wpdb->prefix}fi_reports WHERE session_id = %d ORDER BY title ASC",
		$session_id
	));
	
	// Get child sessions (only for this gov and this parent)
	$child_sessions = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}fi_sessions WHERE gov = %s AND parent_id = %d ORDER BY date_start DESC",
		$session->gov ?? $gov,
		$session_id
	));
	
	$has_connected_records = !empty($connected_votes) || !empty($connected_reports) || !empty($child_sessions);
}
?>
<?php fi_scope_render_selector(); ?>
<div class="wrap fi-session-edit">
	<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 bg-light p-2 rounded-3" style="position: sticky; top: 32px; z-index: 100;">
		<h1 class="wp-heading-inline m-0"><?php echo esc_html($page_title); ?></h1>
		<div class="d-flex align-items-center gap-2 ms-auto">
			<div class="btn-group" role="group" aria-label="Session actions">
				<a href="<?php echo esc_url(fi_admin_url('fi-sessions')); ?>" class="btn btn-sm btn-outline-secondary">Back to Sessions</a>
				<button type="submit" form="fi-session-form" class="btn btn-sm btn-primary">Save</button>
				<a
					href="<?php echo esc_url(fi_admin_url('fi-sessions')); ?>"
					class="btn btn-sm btn-outline-secondary"
					onclick="return confirm('Discard changes and return to the list?');"
				>Cancel</a>
				<?php if ($is_edit): ?>
					<?php if ($has_connected_records): ?>
						<button type="button" class="btn btn-sm btn-outline-danger" id="fi-session-delete-blocked">Delete</button>
					<?php else: ?>
						<a
							href="<?php echo esc_url(wp_nonce_url(fi_admin_url('fi-sessions', ['action' => 'delete', 'session_id' => $session_id]), 'fi_delete_session_' . $session_id)); ?>"
							class="btn btn-sm btn-outline-danger"
							onclick="return confirm('Are you sure you want to delete this session instead of editing it?');"
						>Delete</a>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<hr class="wp-header-end">

	<?php settings_errors('fi_sessions'); ?>
	
	<?php if (!empty($_GET['message'])): 
		$message = sanitize_text_field($_GET['message']);
		$skipped = absint($_GET['skipped'] ?? 0);
	?>
		<?php if (strpos($message, 'added_') === 0): 
			$count = absint(str_replace('added_', '', $message));
		?>
			<div class="notice notice-success is-dismissible">
				<p><strong>Success!</strong> Added <?php echo esc_html($count); ?> legislator<?php echo $count !== 1 ? 's' : ''; ?> to this session from child sessions.<?php if ($skipped > 0): ?> (<?php echo esc_html($skipped); ?> already assigned)<?php endif; ?></p>
			</div>
		<?php elseif ($message === 'all_exist'): ?>
			<div class="notice notice-info is-dismissible">
				<p>All legislators from child sessions are already assigned to this session.</p>
			</div>
		<?php elseif ($message === 'no_children'): ?>
			<div class="notice notice-warning is-dismissible">
				<p>No child sessions found.</p>
			</div>
		<?php elseif ($message === 'no_legislators'): ?>
			<div class="notice notice-warning is-dismissible">
				<p>No legislators found in child sessions.</p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ($has_connected_records): ?>
		<div class="alert alert-danger" id="fi-delete-warning" style="display: none;">
			<h4 class="alert-heading">You must reassign the records below before deleting this session.</h4>
			<p class="mb-0">Sessions are a cornerstone of our legislator data because everything connects to it. Review the data below and either delete the records or assign them to a different session. Then you will be able to delete this session.</p>
		</div>
	<?php endif; ?>

	<div class="row g-4">
		<div class="col-12 col-xl-9">
			<form method="post" action="<?php echo esc_url($form_action); ?>" id="fi-session-form">
				<?php wp_nonce_field('fi_save_session', 'fi_session_nonce'); ?>
				<?php fi_form_field('session_id', ['type' => 'hidden', 'value' => $session_id]); ?>

				<!-- Session Details Section -->
				<div class="card shadow-sm mb-4">
					<div class="card-header border-0 shadow-sm pb-0">
						<h2 class="h4 mb-0">Session Details</h2>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<!-- Row 1 -->
							<div class="col-md-3">
								<?php fi_form_field_government('gov', [
									'label' => 'Government',
									'value' => $session->gov ?? '',
									'required' => true,
									'attributes' => ['readonly' => 'readonly']
								]); ?>
							</div>
							<div class="col-md-3">
								<?php fi_form_field('name', [
									'label' => 'Session Name',
									'value' => $session->name ?? '',
									'required' => true,
									'placeholder' => 'e.g., 118th Congress'
								]); ?>
							</div>
							<div class="col-md-3">
								<?php fi_form_field('parent_id', [
									'label' => 'Parent Session',
									'type' => 'select',
									'options' => $parent_options,
									'value' => $session->parent_id ?? '',
									'help' => 'Optional. Link this session as a child of another session.'
								]); ?>
							</div>
							<div class="col-md-3">
							<?php fi_form_field('status', [
								'label' => 'Status',
								'type' => 'radio-group',
								'value' => $session->status ?? 'draft',
								'options' => [
									'draft' => 'Draft',
									'publish' => 'Published'
								],
								'button_size' => 'sm',
								'button_classes' => [
									'publish' => [
										'publish' => 'btn-success',
										'draft' => 'btn-outline-success'
									],
									'draft' => [
										'publish' => 'btn-outline-secondary',
										'draft' => 'btn-secondary'
									]
								],
								'help' => 'Draft sessions are hidden from public display.'
							]); ?>
							</div>						

							<div class="col-md-3">
								<?php fi_form_field('date_start', [
									'label' => 'Start Date',
									'type' => 'date',
									'value' => $session->date_start ?? ''
								]); ?>
							</div>
							<div class="col-md-3">
								<?php fi_form_field('date_end', [
									'label' => 'End Date',
									'type' => 'date',
									'value' => $session->date_end ?? ''
								]); ?>
							</div>
							<div class="col-md-3">
								<?php fi_form_field('legiscan_id', [
									'label' => 'LegiScan Session ID',
									'type' => 'number',
									'value' => $session->legiscan_id ?? '',
									'help' => 'Optional. Numeric session_id used to map LegiScan datasets to this FI session.'
								]); ?>
							</div>
						</div>
					</div>
				</div>

				<?php if ($is_edit): ?>
				<!-- Session Records Section -->
				<div class="card shadow-sm mb-4" id="fi-session-records-card">
					<div class="card-header border-0 shadow-sm pb-0">
						<h2 class="h4 mb-0">Session Records</h2>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<!-- Votes Column -->
							<div class="col-12 col-md-8">
								<div class="card" id="fi-votes-card">
									<div class="card-header bg-light" id="fi-votes-card-header">
										<h3 class="h6 mb-0">Votes (<?php echo count($connected_votes); ?>)</h3>
									</div>
									<div class="card-body">
										<?php if (empty($connected_votes)): ?>
											<p class="text-muted mb-0">No votes connected to this session.</p>
										<?php else: ?>
											<ul class="list-unstyled mb-0">
												<?php foreach ($connected_votes as $vote): ?>
													<li class="mb-2">
														<a href="<?php echo esc_url(fi_admin_url('fi-votes', ['action' => 'edit', 'vote_id' => $vote->id])); ?>">
															<?php 
															$chamber_label = $vote->chamber === 'S' ? 'Senate' : 'House';
															echo esc_html($chamber_label . ': ' . $vote->bill_number . ' - ' . $vote->title); 
															?>
														</a>
													</li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									</div>
								</div>
							</div>
							
							<!-- Reports Column -->
							<div class="col-12 col-md-4">
								<div class="card" id="fi-reports-card">
									<div class="card-header bg-light" id="fi-reports-card-header">
										<h3 class="h6 mb-0">Reports (<?php echo count($connected_reports); ?>)</h3>
									</div>
									<div class="card-body">
										<?php if (empty($connected_reports)): ?>
											<p class="text-muted mb-0">No reports connected to this session.</p>
										<?php else: ?>
											<ul class="list-unstyled mb-0">
												<?php foreach ($connected_reports as $report): ?>
													<li class="mb-2">
														<a href="<?php echo esc_url(fi_admin_url('fi-reports', ['action' => 'edit', 'report_id' => $report->id])); ?>">
															<?php echo esc_html($report->title); ?>
														</a>
													</li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<?php
				// Summary: page actions are in the sticky top action bar; avoid duplicate bottom buttons.
				?>
			</form>
		</div>

		<div class="col-12 col-xl-3">
			<?php if ($is_edit): ?>
				<div class="card shadow-sm mb-4">
					<div class="card-header border-0 shadow-sm">
						<h2 class="h5 mb-0">Session Statistics</h2>
					</div>
					<div class="card-body">
						<?php
						global $wpdb;
						$stats = [
							'legislators' => (int) $wpdb->get_var($wpdb->prepare(
								"SELECT COUNT(DISTINCT legislator_id) FROM {$wpdb->prefix}fi_legislator_sessions WHERE session_id = %d",
								$session_id
							)),
							'votes' => (int) $wpdb->get_var($wpdb->prepare(
								"SELECT COUNT(*) FROM {$wpdb->prefix}fi_votes WHERE session_id = %d",
								$session_id
							)),
							'reports' => (int) $wpdb->get_var($wpdb->prepare(
								"SELECT COUNT(*) FROM {$wpdb->prefix}fi_reports WHERE session_id = %d",
								$session_id
							)),
						];
						?>
						<ul class="list-unstyled mb-0">
							<li><strong>Legislators:</strong> <?php echo esc_html($stats['legislators']); ?></li>
							<li><strong>Votes:</strong> <?php echo esc_html($stats['votes']); ?></li>
							<li><strong>Reports:</strong> <?php echo esc_html($stats['reports']); ?></li>
						</ul>
					</div>
				</div>

				<div class="card shadow-sm mb-4">
					<div class="card-header border-0 shadow-sm">
						<h2 class="h5 mb-0">Quick Links</h2>
					</div>
					<div class="card-body">
						<div class="d-grid gap-2">
							<a href="<?php echo esc_url(fi_admin_url('fi-legislators', ['session_id' => $session_id])); ?>" class="btn btn-secondary">
								View Legislators
							</a>
							<a href="<?php echo esc_url(fi_admin_url('fi-votes', ['session_id' => $session_id])); ?>" class="btn btn-secondary">
								View Votes
							</a>
							<a href="<?php echo esc_url(fi_admin_url('fi-reports', ['session_id' => $session_id])); ?>" class="btn btn-secondary">
								View Reports
							</a>
							<?php if (!empty($session->legiscan_id) && !empty($session->gov)): ?>
								<?php
								// Summary: LegiScan's most stable public entrypoint is the datasets page for the gov.
								// We include the session_id in the label so staff can quickly confirm they're looking at the right dataset.
								$ls_gov = strtoupper((string) $session->gov);
								$legiscan_url = 'https://legiscan.com/' . rawurlencode($ls_gov) . '/datasets';
								?>
								<a href="<?php echo esc_url($legiscan_url); ?>" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">
									View on LegiScan
								</a>
							<?php endif; ?>
						</div>
					</div>
				</div>





			<?php if (!empty($child_sessions)): ?>
			<div class="card shadow-sm mb-4" id="fi-special-sessions-card">
				<div class="card-header border-0">
					<h2 class="h5 mb-0">Child Sessions</h2>
				</div>
				<div class="card-body">
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<?php wp_nonce_field('fi_add_child_legislators_' . $session_id, 'fi_add_child_legislators_nonce'); ?>
						<input type="hidden" name="action" value="fi_add_child_legislators">
						<input type="hidden" name="session_id" value="<?php echo esc_attr($session_id); ?>">
						<button type="submit" class="btn btn-sm btn-outline-primary w-100">
							Add Child Legislators to this Session
						</button>
					</form>
					<div class="d-grid gap-2">
						<h5 class="mt-3">View/Edit Child Sessions</h5>
						<?php foreach ($child_sessions as $child): ?>
							<a href="<?php echo esc_url(fi_admin_edit_session_url($child->id)); ?>" class="btn btn-sm btn-outline-success">
								<?php echo esc_html($child->name); ?>
							</a>
						<?php endforeach; ?>
					</div>
<?php
// Overwrite parent session assignment with child session data from the SELECTED child session.
// Child sessions linked to Legiscan will be the conduit for updating the legislator data, but we must copy the data to the parent session.
// This is necessary because the parent session is the one that is displayed on the public site.
?>
					<div class="d-grid gap-2">
						<h5 class="mt-3">Update Parent Session with Child Data</h5>
						<?php foreach ($child_sessions as $child): ?>
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mb-3">
							<?php wp_nonce_field('fi_update_parent_legislators_' . $child->id, 'fi_update_parent_legislators_nonce'); ?>
							<input type="hidden" name="action" value="fi_update_parent_legislators">
							<input type="hidden" name="parent_session_id" value="<?php echo esc_attr($session_id); ?>">
							<input type="hidden" name="child_session_id" value="<?php echo esc_attr($child->id); ?>">
							<button type="submit" class="btn btn-sm btn-outline-danger w-100">
								Merge <?php echo esc_html($child->name); ?>
							</button>
						</form>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
			<?php endif; //if (!empty($child_sessions)) ?>
		<?php endif; //if ($is_edit) ?>

			<div class="card shadow-sm mb-4">
				<div class="card-header border-0 shadow-sm">
					<h2 class="h5 mb-0">Additional Meta</h2>
				</div>
				<div class="card-body">
					<?php if (empty($extra_meta)): ?>
						<p class="text-muted mb-0">No additional metadata stored.</p>
					<?php else: ?>
						<div class="table-responsive">
							<table class="table table-sm mb-0">
								<tbody>
									<?php foreach ($extra_meta as $key => $value): ?>
										<tr>
											<th scope="row" class="text-muted" style="width: 40%;"><?php echo esc_html($key); ?></th>
											<td><?php echo esc_html(is_scalar($value) ? $value : wp_json_encode($value)); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="card shadow-sm">
				<div class="card-header border-0 shadow-sm">
					<h2 class="h5 mb-0">Legiscan Sessions</h2>
				</div>
				<div class="card-body">
					<table class="table table-sm mb-0">
						<thead>
							<tr>
								<th>Session ID</th>
								<th>Session Name</th>
								<th>Session Title</th>
							</tr>
						</thead>
					<?php
					$ls_data = fi_legiscan_get_datasets($gov);
					foreach ($ls_data as $ls_session) {
						if($session->legiscan_id == $ls_session['session_id']){
							$class = 'bg-success-subtle';
						}else{
							$class = '';
}
						echo '<tr>';
						echo '<td class="'.$class.' fw-bold">' . $ls_session['session_id'] . '</td>';
						echo '<td class="'.$class.'">' . $ls_session['session_name'] . '</td>';
						echo '<td class="'.$class.'">' . $ls_session['session_title'] .'</td>';
						echo '</tr>';
					}
					?>
					</table>
				</div>
			</div>


		</div>
	</div>
</div>

<?php if ($has_connected_records): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
	const deleteBtn = document.getElementById('fi-session-delete-blocked');
	const warningBox = document.getElementById('fi-delete-warning');
	const sessionRecordsCard = document.getElementById('fi-session-records-card');
	const votesCard = document.getElementById('fi-votes-card');
	const votesCardHeader = document.getElementById('fi-votes-card-header');
	const reportsCard = document.getElementById('fi-reports-card');
	const reportsCardHeader = document.getElementById('fi-reports-card-header');
	const specialSessionsCard = document.getElementById('fi-special-sessions-card');
	
	const hasVotes = <?php echo !empty($connected_votes) ? 'true' : 'false'; ?>;
	const hasReports = <?php echo !empty($connected_reports) ? 'true' : 'false'; ?>;
	const hasChildSessions = <?php echo !empty($child_sessions) ? 'true' : 'false'; ?>;
	
	if (deleteBtn && warningBox) {
		deleteBtn.addEventListener('click', function() {
			// Show warning box
			warningBox.style.display = 'block';
			
			// Scroll to warning box
			warningBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
			
			// Flash animation
			warningBox.style.animation = 'none';
			setTimeout(() => {
				warningBox.style.animation = 'pulse 0.5s ease-in-out 2';
			}, 10);
			
			// Add red styling to Session Records card if has votes or reports
			if ((hasVotes || hasReports) && sessionRecordsCard) {
				sessionRecordsCard.classList.add('border-danger');
			}
			
			// Add red styling to Votes card if has votes
			if (hasVotes && votesCard && votesCardHeader) {
				votesCard.classList.add('border-danger');
				votesCardHeader.classList.remove('bg-light');
				votesCardHeader.classList.add('bg-danger', 'text-white');
			}
			
			// Add red styling to Reports card if has reports
			if (hasReports && reportsCard && reportsCardHeader) {
				reportsCard.classList.add('border-danger');
				reportsCardHeader.classList.remove('bg-light');
				reportsCardHeader.classList.add('bg-danger', 'text-white');
			}
			
			// Add red styling to Special Sessions card if has child sessions
			if (hasChildSessions && specialSessionsCard) {
				specialSessionsCard.classList.add('border-danger', 'border-3');
			}
		});
	}
});
</script>
<style>
@keyframes pulse {
	0%, 100% { transform: scale(1); }
	50% { transform: scale(1.02); }
}
</style>
<?php endif; ?>
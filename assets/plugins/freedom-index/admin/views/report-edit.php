<?php if (!defined('ABSPATH')) {exit;}

$report_id = absint($_GET['report_id'] ?? 0);
$action = $_GET['action'] ?? 'add';
$scope = fi_scope_get_current();

global $wpdb;

$report = null;
if ($report_id) {
	$report = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}fi_reports WHERE id = %d",
		$report_id
	));
}

if (!$report) {
	$report = fi_admin_reports_get_defaults($scope);
}

$sessions = fi_sessions_get_by_gov($scope['gov'] ?? 'US', [
	'orderby' => 'date_start',
	'order' => 'DESC',
]);

$selected_vote_ids = fi_admin_reports_decode_selected_votes($report);
$session_for_votes = $report['session_id']
	?: ($scope['session_id'] ?? null)
	?: (!empty($sessions) ? (int) ($sessions[0]->id ?? null) : null);
// Summary: only show vote selector after report session is saved.
$has_report_session = !empty($report['session_id']) && (int) $report['session_id'] > 0;

$votes = $session_for_votes ? fi_votes_get_by_session((int) $session_for_votes, ['status' => null, 'cache' => false]) : [];

// Vote IDs already used in other reports for this session (exclude current report)
$used_in_other_reports = $session_for_votes
	? fi_admin_reports_get_vote_ids_used_in_session((int) $session_for_votes, $report_id ? (int) $report_id : 0)
	: [];

// Extract payload data using ReportsPayload class (normalized)
$payload = fi_report_payload_normalize($report['payload_json'] ?? null);

// Get intro_text from payload content (migrated from post_content)
$intro_text = $payload['content'] ?? '';

// Report format from DB column (not payload)
$report_format = $report['format'] ?? 'scorecard';
$report_cph = $payload['cph'] ?? 'hide';
$vote_start = $payload['vote_start'] ?? '1';

/*DEPRECATED*/ $contact_location = $payload['contact'] ?? 'back';
/*DEPRECATED*/ $constitution_qr = $payload['constitution_qr'] ?? 'none';
/*DEPRECATED*/ $fi_vote_paging = $payload['fi_vote_paging'] ?? '2,3,3,2';

$report_pdf_url = $payload['report_pdf_url'] ?? '';

// Get selected votes by chamber (already normalized as arrays of ints)
$selected_votes_h = $payload['votes_h'] ?? [];
$selected_votes_s = $payload['votes_s'] ?? [];

// Get sort order arrays (manual ordering)
$votes_h_order = $payload['votes_h_order'] ?? [];
$votes_s_order = $payload['votes_s_order'] ?? [];

// Get status (from report object, not payload)
$report_status = $report['status'] ?? 'draft';

//Report Gov
$report_gov = $report['gov'] ?? '';

$session_for_votes = $session_for_votes ?? null;
$action = $action ?? 'add';

// Get GOV from scope
$scope = fi_scope_get_current();
$current_gov = $scope['gov'] ?? 'US';
$is_us_gov = (strtoupper($current_gov) === 'US');
?>
<?php fi_scope_render_selector(); ?>
<?php fi_scope_content_check($current_gov, $report_gov, 'report'); ?>
<div class="wrap fi-report-edit" data-selected-h='<?php echo esc_attr(wp_json_encode($selected_votes_h)); ?>' data-selected-s='<?php echo esc_attr(wp_json_encode($selected_votes_s)); ?>' data-order-h='<?php echo esc_attr(wp_json_encode($votes_h_order)); ?>' data-order-s='<?php echo esc_attr(wp_json_encode($votes_s_order)); ?>'>
	<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 bg-light p-2 rounded-3" style="position: sticky; top: 32px; z-index: 100;">
		<h1 class="wp-heading-inline m-0"><?php echo $action === 'edit' ? 'Edit Report' : 'Create Report'; ?></h1>
		<div class="d-flex align-items-center gap-2 ms-auto">
			<div class="btn-group" role="group" aria-label="Report actions">
				<a href="<?php echo esc_url(fi_admin_url('fi-reports')); ?>" class="btn btn-sm btn-outline-secondary">Back to Reports</a>
				<button type="submit" form="fi-report-form" class="btn btn-sm btn-primary">Save</button>
				<a
					href="<?php echo esc_url(fi_admin_url('fi-reports')); ?>"
					class="btn btn-sm btn-outline-secondary"
					onclick="return confirm('Discard changes and return to the list?');"
				>Cancel</a>
				<?php if ($report_id): ?>
					<a
						href="<?php echo esc_url(wp_nonce_url(fi_admin_url('fi-reports', ['action' => 'delete', 'report_id' => $report_id]), 'fi_delete_report_' . $report_id)); ?>"
						class="btn btn-sm btn-outline-danger"
						onclick="return confirm('Are you sure you want to delete this report?');"
					>Delete</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<hr class="wp-header-end">
	<?php if ($has_report_session): ?>
	<div id="fi-selected-count" class="text-muted small w-100">
		Selected Votes: <span data-count-h><?php echo esc_html(count($selected_votes_h)); ?></span> House, <span data-count-s><?php echo esc_html(count($selected_votes_s)); ?></span> Senate
	</div>
	<?php endif; ?>
	<?php if (!empty($_GET['updated'])): ?>
		<div class="notice notice-success is-dismissible">
			<p>Report saved successfully.</p>
		</div>
	<?php endif; ?>
	
	<form id="fi-report-form" method="post">
		<?php wp_nonce_field('fi_save_report', 'fi_report_nonce'); ?>
		<input type="hidden" name="report_id" value="<?php echo esc_attr($report_id); ?>">

		<div class="row g-4">
			<div class="col-12 col-lg-6">
				<div class="card shadow-sm mb-4">
					<div class="card-body">
						<div class="row g-3">
							<div class="col-md-4">
								<?php fi_form_field('report_title', [
									'label' => 'Report Title',
									'value' => $report['title'] ?? '',
									'required' => true,
									'attributes' => ['id' => 'fi-report-title']
								]); ?>
							</div>

							<div class="col-md-4">
								<?php fi_form_field('title_menu', [
									'label' => 'Menu Title (Short Title)',
									'value' => $report['title_menu'] ?? '',
									'attributes' => ['id' => 'fi-menu-title']
								]); ?>
							</div>

							<div class="col-md-4">
								<?php
								$session_options = ['' => 'Select Session'];
								foreach ($sessions as $session) {
									$session_options[$session['id']] = ($session['parent_id'] ? '— ' : '') . $session['name'];
								}
								fi_form_field('session_id', [
									'label' => 'Session',
									'type' => 'select',
									'options' => $session_options,
									'value' => $report['session_id'] ?? $session_for_votes,
									'required' => true,
									'attributes' => ['id' => 'fi-report-session']
								]);
								?>
							</div>

							<div class="col-md-3">
								<?php fi_form_field('status', [
									'label' => 'Status',
									'type' => 'select',
									'options' => [
										'draft' => 'Draft',
										'publish' => 'Published'
									],
									'value' => $report_status ?? 'draft',
									'attributes' => ['id' => 'fi-report-status']
								]); ?>
							</div>
							<div class="col-md-3">
								<?php 
								// Format date for HTML5 date input (requires Y-m-d format)
								$date_publish_value = '';
								if (!empty($report['date_publish'])) {
									// Extract date portion from DATETIME (first 10 characters: YYYY-MM-DD)
									$date_publish_value = substr($report['date_publish'], 0, 10);
								}
								fi_form_field('date_publish', [
									'label' => 'Date Published',
									'type' => 'date',
									'value' => $date_publish_value
								]); ?>
							</div>

							<?php if ($is_us_gov): ?>
								<!-- RMFORMAT -->
								<div class="col-md-3">
									<?php fi_form_field('report_format', [
										'label' => 'Report Format',
										'type' => 'select',
										'options' => [
											'scorecard' => 'Congressional Score Card',
											'freedomindex' => 'Freedom Index',
										],
										'value' => $report_format,
										'attributes' => ['id' => 'fi-report-format']
									]); ?>
								</div>
							<?php else: ?>
								<input type="hidden" name="report_format" value="scorecard">
							<?php endif; ?>

							<?php if ($is_us_gov): ?>
								<div class="col-md-3">
									<?php fi_form_field('vote_start', [
										'label' => 'Vote Number Start',
										'desc' => 'Specify the first vote number. 1, 11, 21, etc.',
										'value' => $vote_start,
										'attributes' => ['id' => 'fi-vote-start']
									]); ?>
								</div>
							<?php else: ?>
								<input type="hidden" name="vote_start" value="1">
							<?php endif; ?>

							<!-- DEPRECATED but preserving for now -->
							<input type="hidden" name="report_cph" value="<?php echo esc_attr($report_cph); ?>">
							<input type="hidden" name="contact_location" value="<?php echo esc_attr($contact_location); ?>">
							<input type="hidden" name="constitution_qr" value="<?php echo esc_attr($constitution_qr); ?>">
							<input type="hidden" name="fi_vote_paging" value="<?php echo esc_attr($fi_vote_paging); ?>">
							<?php 
							/* DEPRECATED
							<div class="col-md-3">
								<?php fi_form_field('report_cph', [
									'label' => 'Economic Impact',
									'type' => 'select',
									'options' => [
										'show' => 'Show',
										'hide' => 'Hide',
									],
									'value' => $report_cph,
									'attributes' => ['id' => 'fi-report-cph']
								]); ?>
							</div>
							<div class="col-md-4">
								<?php fi_form_field('contact_location', [
									'label' => 'Contact Info Location',
									'type' => 'select',
									'options' => [
										'front' => 'Front below intro text',
										'back' => 'Back above footer',
									],
									'value' => $contact_location,
									'attributes' => ['id' => 'fi-contact-location']
								]); ?>
							</div>
							<div class="col-md-4">
								<?php fi_form_field('constitution_qr', [
									'label' => 'Constitution QR Code',
									'desc' => '<strong>Select where to display The Constitution is the Solution QR code.</strong><br>NOTE: It will not fit in the compact two per page format.',
									'type' => 'select',
									'options' => [
										'none' => 'Hide it: I can\'t make it fit.',
										'front' => 'Front Page below intro text',
										'back' => 'Back page above logo footer',
									],
									'value' => $constitution_qr,
									'attributes' => ['id' => 'fi-constitution-qr']
								]); ?>
							</div>
							<div class="col-md-4">
								<?php fi_form_field('fi_vote_paging', [
									'label' => 'Freedom Index PDF Vote Paging',
									'desc' => 'Specify the number of votes to show on each page.<br>Enter numbers separated by commas.<br>Please carefully review the output to make sure it renders as expected.',
									'value' => $fi_vote_paging,
									'attributes' => ['id' => 'fi-vote-paging']
								]); ?>
							</div>
							*/ ?>

							<?php //RMFORMAT
							if($report_format === 'freedomindex'){
							echo '<div class="col-12">';
								fi_form_field('report_pdf_url', [
									'label' => 'Freedom Index PDF URL',
									'value' => $report_pdf_url,
									'attributes' => ['id' => 'fi-pdf-url']
								]);
							echo '</div>';
							}?>
						</div>
					</div>
				</div>
			</div>

			<div class="col-12 col-lg-6">
				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white border-0">
						<h2 class="h5 mb-0">Introduction</h2>
					</div>
					<div class="card-body" style="margin-top:-20px;">
						<?php
						wp_editor(
							$intro_text,
							'intro_text',
							[
								'textarea_name' => 'intro_text',
								'media_buttons' => false,
								'textarea_rows' => 8,
								'teeny' => true,
							]
						);
						?>
					</div>
				</div>
			</div>
		</div><!-- row -->



		<div class="row g-4">
			<?php if ($has_report_session): ?>
			<div class="col-12 col-lg-6">
				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white border-0 d-flex flex-wrap justify-content-between align-items-center">
						<h2 class="h5 mb-0">Select House Votes</h2>
					</div>
					<div class="card-body">
						<?php
						// Separate House votes into selected and unselected
						$house_votes = array_filter($votes, function($v) { return ($v->chamber ?? '') === 'H'; });
						$house_votes_map = [];
						foreach ($house_votes as $vote) {
							$house_votes_map[$vote['id']] = $vote;
						}
						
						// Sort selected votes by order array if it exists, otherwise by date
						$selected_house_votes = [];
						if (!empty($votes_h_order)) {
							// Use manual sort order
							foreach ($votes_h_order as $vote_id) {
								if (isset($house_votes_map[$vote_id]) && in_array($vote_id, $selected_votes_h, true)) {
									$selected_house_votes[] = $house_votes_map[$vote_id];
								}
							}
							// Add any selected votes not in order array (newly selected)
							foreach ($selected_votes_h as $vote_id) {
								if (!in_array($vote_id, $votes_h_order, true) && isset($house_votes_map[$vote_id])) {
									$selected_house_votes[] = $house_votes_map[$vote_id];
								}
							}
						} else {
							// Default order: by date_voted
							foreach ($selected_votes_h as $vote_id) {
								if (isset($house_votes_map[$vote_id])) {
									$selected_house_votes[] = $house_votes_map[$vote_id];
								}
							}
							usort($selected_house_votes, function($a, $b) {
								$date_a = $a->date_voted ?? $a->date ?? '';
								$date_b = $b->date_voted ?? $b->date ?? '';
								return strcmp($date_a, $date_b);
							});
						}
						
						// Unselected votes: all session votes not selected for this report (default order by date)
						$unselected_house_votes = [];
						foreach ($house_votes as $vote) {
							$vid = (int) $vote['id'];
							if (!in_array($vid, $selected_votes_h, true)) {
								$unselected_house_votes[] = $vote;
							}
						}
						usort($unselected_house_votes, function($a, $b) {
							$date_a = $a->date_voted ?? $a->date ?? '';
							$date_b = $b->date_voted ?? $b->date ?? '';
							return strcmp($date_a, $date_b);
						});
						?>
						
						<!-- Selected House Votes -->
						<?php if (!empty($selected_house_votes)): ?>
							<div class="mb-4">
								<h4 class="h6 text-primary mb-2">Selected (<?php echo count($selected_house_votes); ?>)</h4>
								<div class="list-group mb-3" id="fi-selected-votes-h" data-chamber="h">
									<?php foreach ($selected_house_votes as $idx => $vote): ?>
										<div class="list-group-item d-flex align-items-start gap-2 fi-vote-item-selected border-success" data-vote-id="<?php echo esc_attr($vote['id']); ?>">
											<div class="d-flex flex-column gap-1 me-2">
												<button type="button" class="btn btn-sm btn-outline-secondary p-1 fi-vote-move-up" style="width: 24px; height: 24px; line-height: 1;" title="Move Up" <?php echo $idx === 0 ? 'disabled' : ''; ?>>
													<span class="dashicons dashicons-arrow-up-alt" style="font-size: 16px; width: 16px; height: 16px;"></span>
												</button>
												<button type="button" class="btn btn-sm btn-outline-secondary p-1 fi-vote-move-down" style="width: 24px; height: 24px; line-height: 1;" title="Move Down" <?php echo $idx === count($selected_house_votes) - 1 ? 'disabled' : ''; ?>>
													<span class="dashicons dashicons-arrow-down-alt" style="font-size: 16px; width: 16px; height: 16px;"></span>
												</button>
											</div>
											<input type="checkbox" class="form-check-input mt-2" name="selected_votes_h[]" value="<?php echo esc_attr($vote['id']); ?>" checked>
											<input type="hidden" name="votes_h_order[]" value="<?php echo esc_attr($vote['id']); ?>" class="fi-vote-order-input">
											<div class="flex-grow-1">
												<div class="d-flex align-items-center gap-2 mb-1">
													<button type="button" class="btn btn-sm btn-link p-0 text-muted fi-vote-preview" data-vote-id="<?php echo esc_attr($vote['id']); ?>" title="Preview Vote" style="line-height: 1; font-size: 14px;">
														<i class="bi bi-file-earmark" style="font-size: 16px;"></i>
													</button>
													<strong>
														<a href="<?php echo esc_url(fi_admin_edit_vote_url((int) $vote['id'])); ?>" target="_blank" rel="noopener" class="text-decoration-none fw-normal fs-5">
															<i class="bi bi-pencil-square me-1"></i><?php echo esc_html($vote['title']); ?>
														</a>
													</strong>
												</div>
												<span class="text-muted small">
													<?php 
													$bill_number = $vote['bill_number'] ?? '';
													$date_voted = $vote['date_voted'] ?? $vote['date_created'] ?? '';
													$formatted_date = '';
													if ($date_voted) {
														$ts = strtotime($date_voted);
														if ($ts && $date_voted !== '0000-00-00' && $date_voted !== '0000-00-00 00:00:00') {
															$formatted_date = date('m/d/Y', $ts);
														} else {
															$formatted_date = $date_voted;
														}
													}
													?>
													<?php if ($bill_number): ?>
														<?php echo esc_html($bill_number); ?>
														<?php if ($formatted_date): ?> · <?php endif; ?>
													<?php endif; ?>
													<?php if ($formatted_date): ?>
														<?php echo esc_html($formatted_date); ?>
													<?php endif; ?>
													<?php if ($formatted_date || $bill_number): ?> · <?php endif; ?>
													Good: <?php echo esc_html($vote['constitutional']); ?>
												</span>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; ?>
						
						<!-- Unselected House Votes (only votes not used in other reports) -->
						<div class="mt-3 border-top pt-3">
							<h4 class="h6 mb-2">Unselected (<?php echo count($unselected_house_votes); ?>) <span class="text-muted ms-4 fw-normal" style="font-size: 12px;">Check boxes and click Save.</span></h4>
							<div id="fi-vote-list-h" class="list-group">
								<?php if (empty($unselected_house_votes)): ?>
									<p class="text-muted mb-0">No House votes available or all are selected.</p>
								<?php else: ?>
									<?php foreach ($unselected_house_votes as $vote): ?>
										<label class="list-group-item d-flex align-items-start gap-3 fi-vote-item fi-vote-item-h">
											<input type="checkbox" class="form-check-input mt-2" name="selected_votes_h[]" value="<?php echo esc_attr($vote['id']); ?>">
											<div class="flex-grow-1">
												<div class="d-flex align-items-center gap-2 mb-1">
													<button type="button" class="btn btn-sm btn-link p-0 text-muted fi-vote-preview" data-vote-id="<?php echo esc_attr($vote['id']); ?>" title="Preview Vote" style="line-height: 1; font-size: 14px;">
														<i class="bi bi-file-earmark" style="font-size: 16px;"></i>
													</button>
													<strong>
														<a href="<?php echo esc_url(fi_admin_edit_vote_url((int) $vote['id'])); ?>" target="_blank" rel="noopener" class="text-decoration-none fw-normal fs-5">
															<i class="bi bi-pencil-square me-1"></i><?php echo esc_html($vote['title']); ?>
														</a>
													</strong>
												</div>
												<span class="text-muted small">
													<?php 
													$bill_number = $vote['bill_number'] ?? $vote['slug'] ?? '';
													$date_voted = $vote['date_voted'] ?? $vote['date'] ?? '';
													$formatted_date = '';
													if ($date_voted) {
														$ts = strtotime($date_voted);
														if ($ts && $date_voted !== '0000-00-00' && $date_voted !== '0000-00-00 00:00:00') {
															$formatted_date = date('m/d/Y', $ts);
														} else {
															$formatted_date = $date_voted;
														}
													}
													?>
													<?php if ($bill_number): ?>
														<?php echo esc_html($bill_number); ?>
														<?php if ($formatted_date): ?> · <?php endif; ?>
													<?php endif; ?>
													<?php if ($formatted_date): ?>
														<?php echo esc_html($formatted_date); ?>
													<?php endif; ?>
													<?php if ($formatted_date || $bill_number): ?> · <?php endif; ?>
													Good: <?php echo esc_html($vote['constitutional']); ?>
												</span>
											</div>
										</label>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>

					</div><!-- card body -->
				</div><!-- card -->
			</div><!-- col -->

			<div class="col-12 col-lg-6">
				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white border-0 d-flex flex-wrap justify-content-between align-items-center">
						<h2 class="h5 mb-0">Select Senate Votes</h2>
					</div>
					<div class="card-body">
						<?php
						// Separate Senate votes into selected and unselected
						$senate_votes = array_filter($votes, function($v) { return ($v->chamber ?? '') === 'S'; });
						$senate_votes_map = [];
						foreach ($senate_votes as $vote) {
							$senate_votes_map[$vote['id']] = $vote;
						}
						
						// Sort selected votes by order array if it exists, otherwise by date
						$selected_senate_votes = [];
						if (!empty($votes_s_order)) {
							// Use manual sort order
							foreach ($votes_s_order as $vote_id) {
								if (isset($senate_votes_map[$vote_id]) && in_array($vote_id, $selected_votes_s, true)) {
									$selected_senate_votes[] = $senate_votes_map[$vote_id];
								}
							}
							// Add any selected votes not in order array (newly selected)
							foreach ($selected_votes_s as $vote_id) {
								if (!in_array($vote_id, $votes_s_order, true) && isset($senate_votes_map[$vote_id])) {
									$selected_senate_votes[] = $senate_votes_map[$vote_id];
								}
							}
						} else {
							// Default order: by date_voted
							foreach ($selected_votes_s as $vote_id) {
								if (isset($senate_votes_map[$vote_id])) {
									$selected_senate_votes[] = $senate_votes_map[$vote_id];
								}
							}
							usort($selected_senate_votes, function($a, $b) {
								$date_a = $a->date_voted ?? $a->date ?? '';
								$date_b = $b->date_voted ?? $b->date ?? '';
								return strcmp($date_a, $date_b);
							});
						}
						
						// Unselected votes: all session votes not selected for this report (default order by date)
						$unselected_senate_votes = [];
						foreach ($senate_votes as $vote) {
							$vid = (int) $vote['id'];
							if (!in_array($vid, $selected_votes_s, true)) {
								$unselected_senate_votes[] = $vote;
							}
						}
						usort($unselected_senate_votes, function($a, $b) {
							$date_a = $a->date_voted ?? $a->date ?? '';
							$date_b = $b->date_voted ?? $b->date ?? '';
							return strcmp($date_a, $date_b);
						});
						?>
							
						<!-- Selected Senate Votes -->
						<?php if (!empty($selected_senate_votes)): ?>
							<div class="mb-4">
								<h4 class="h6 text-primary mb-2">Selected (<?php echo count($selected_senate_votes); ?>)</h4>
								<div class="list-group mb-3" id="fi-selected-votes-s" data-chamber="s">
									<?php foreach ($selected_senate_votes as $idx => $vote): ?>
										<div class="list-group-item d-flex align-items-start gap-2 fi-vote-item-selected border-success" data-vote-id="<?php echo esc_attr($vote['id']); ?>">
											<div class="d-flex flex-column gap-1 me-2">
												<button type="button" class="btn btn-sm btn-outline-secondary p-1 fi-vote-move-up" style="width: 24px; height: 24px; line-height: 1;" title="Move Up" <?php echo $idx === 0 ? 'disabled' : ''; ?>>
													<span class="dashicons dashicons-arrow-up-alt" style="font-size: 16px; width: 16px; height: 16px;"></span>
												</button>
												<button type="button" class="btn btn-sm btn-outline-secondary p-1 fi-vote-move-down" style="width: 24px; height: 24px; line-height: 1;" title="Move Down" <?php echo $idx === count($selected_senate_votes) - 1 ? 'disabled' : ''; ?>>
													<span class="dashicons dashicons-arrow-down-alt" style="font-size: 16px; width: 16px; height: 16px;"></span>
												</button>
											</div>
											<input type="checkbox" class="form-check-input mt-2" name="selected_votes_s[]" value="<?php echo esc_attr($vote['id']); ?>" checked>
											<input type="hidden" name="votes_s_order[]" value="<?php echo esc_attr($vote['id']); ?>" class="fi-vote-order-input">
											<div class="flex-grow-1">
												<div class="d-flex align-items-center gap-2 mb-1">
													<button type="button" class="btn btn-sm btn-link p-0 text-muted fi-vote-preview" data-vote-id="<?php echo esc_attr($vote['id']); ?>" title="Preview Vote" style="line-height: 1; font-size: 14px;">
														<i class="bi bi-file-earmark" style="font-size: 16px;"></i>
													</button>
													<strong>
														<a href="<?php echo esc_url(fi_admin_edit_vote_url((int) $vote['id'])); ?>" target="_blank" rel="noopener" class="text-decoration-none fw-normal fs-5">
															<i class="bi bi-pencil-square me-1"></i><?php echo esc_html($vote['title']); ?>
														</a>
													</strong>
												</div>
												<span class="text-muted small">
													<?php 
													$bill_number = $vote['bill_number'] ?? $vote['slug'] ?? '';
													$date_voted = $vote['date_voted'] ?? $vote['date'] ?? '';
													$formatted_date = '';
													if ($date_voted) {
														$ts = strtotime($date_voted);
														if ($ts && $date_voted !== '0000-00-00' && $date_voted !== '0000-00-00 00:00:00') {
															$formatted_date = date('m/d/Y', $ts);
														} else {
															$formatted_date = $date_voted;
														}
													}
													?>
													<?php if ($bill_number): ?>
														<?php echo esc_html($bill_number); ?>
														<?php if ($formatted_date): ?> · <?php endif; ?>
													<?php endif; ?>
													<?php if ($formatted_date): ?>
														<?php echo esc_html($formatted_date); ?>
													<?php endif; ?>
													<?php if ($formatted_date || $bill_number): ?> · <?php endif; ?>
													Good: <?php echo esc_html($vote['constitutional']); ?>
												</span>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; ?>
							
						<!-- Unselected Senate Votes (only votes not used in other reports) -->
						<div class="mt-3 border-top pt-3">
							<h4 class="h6 mb-2">Unselected (<?php echo count($unselected_senate_votes); ?>) <span class="text-muted ms-4 fw-normal" style="font-size: 12px;">Check boxes and click Save.</span></h4>
							<div id="fi-vote-list-s" class="list-group">
								<?php if (empty($unselected_senate_votes)): ?>
									<p class="text-muted mb-0">No Senate votes available or all are selected.</p>
								<?php else: ?>
									<?php foreach ($unselected_senate_votes as $vote): ?>
										<label class="list-group-item d-flex align-items-start gap-3 fi-vote-item fi-vote-item-s">
											<input type="checkbox" class="form-check-input mt-2" name="selected_votes_s[]" value="<?php echo esc_attr($vote['id']); ?>">
											<div class="flex-grow-1">
												<div class="d-flex align-items-center gap-2 mb-1">
													<button type="button" class="btn btn-sm btn-link p-0 text-muted fi-vote-preview" data-vote-id="<?php echo esc_attr($vote['id']); ?>" title="Preview Vote" style="line-height: 1; font-size: 14px;">
														<i class="bi bi-file-earmark" style="font-size: 16px;"></i>
													</button>
													<strong>
														<a href="<?php echo esc_url(fi_admin_edit_vote_url((int) $vote['id'])); ?>" target="_blank" rel="noopener" class="text-decoration-none fw-normal fs-5">
															<i class="bi bi-pencil-square me-1"></i><?php echo esc_html($vote['title']); ?>
														</a>
													</strong>
												</div>
												<span class="text-muted small">
													<?php 
													$bill_number = $vote['bill_number'] ?? $vote['slug'] ?? '';
													$date_voted = $vote['date_voted'] ?? $vote['date'] ?? '';
													$formatted_date = '';
													if ($date_voted) {
														$ts = strtotime($date_voted);
														if ($ts && $date_voted !== '0000-00-00' && $date_voted !== '0000-00-00 00:00:00') {
															$formatted_date = date('m/d/Y', $ts);
														} else {
															$formatted_date = $date_voted;
														}
													}
													?>
													<?php if ($bill_number): ?>
														<?php echo esc_html($bill_number); ?>
														<?php if ($formatted_date): ?> · <?php endif; ?>
													<?php endif; ?>
													<?php if ($formatted_date): ?>
														<?php echo esc_html($formatted_date); ?>
													<?php endif; ?>
													<?php if ($formatted_date || $bill_number): ?> · <?php endif; ?>
													Good: <?php echo esc_html($vote['constitutional']); ?>
												</span>
											</div>
										</label>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
					</div><!-- card body -->
				</div><!-- card -->
			</div><!-- col -->
			<?php else: ?>
				<div class="col-12">
					<div class="alert alert-info">
						Select report session and save report to show vote selector.
					</div>
				</div>
			<?php endif; ?>

		</div><!-- row -->
	</form>
<?php if ($has_report_session): ?>
</div>

<script>
(function($) {
	// Sync TinyMCE to textarea before submit (required when submit button is outside the form).
	$('#fi-report-form').on('submit', function() {
		if (typeof tinyMCE !== 'undefined') {
			tinyMCE.triggerSave();
		}
	});

	const root = $('.fi-report-edit');
	if (!root.length) return;

	const selectedSetH = new Set(JSON.parse(root.attr('data-selected-h') || '[]').map(Number));
	const selectedSetS = new Set(JSON.parse(root.attr('data-selected-s') || '[]').map(Number));
	const voteListH = $('#fi-vote-list-h');
	const voteListS = $('#fi-vote-list-s');
	const countH = root.find('[data-count-h]');
	const countS = root.find('[data-count-s]');

	function updateSelectedCount() {
		// Count checked checkboxes (selected + unselected lists)
		const hChecked = root.find('input[name="selected_votes_h[]"]:checked').length;
		const sChecked = root.find('input[name="selected_votes_s[]"]:checked').length;
		countH.text(hChecked);
		countS.text(sChecked);
	}

	function bindVoteEvents() {
		root.find('input[name="selected_votes_h[]"]').off('change').on('change', updateSelectedCount);
		root.find('input[name="selected_votes_s[]"]').off('change').on('change', updateSelectedCount);
	}

	function updateSortButtons(container) {
		const items = container.find('.fi-vote-item-selected');
		items.each(function(index) {
			const $item = $(this);
			$item.find('.fi-vote-move-up').prop('disabled', index === 0);
			$item.find('.fi-vote-move-down').prop('disabled', index === items.length - 1);
		});
	}

	function updateOrderInputs(container) {
		container.find('.fi-vote-item-selected').each(function() {
			const voteId = $(this).data('vote-id');
			$(this).find('.fi-vote-order-input').val(voteId);
		});
	}

	$(document).on('click', '.fi-vote-move-up', function(e) {
		e.preventDefault();
		const $item = $(this).closest('.fi-vote-item-selected');
		const $container = $item.closest('[id^="fi-selected-votes"]');
		const $prev = $item.prev('.fi-vote-item-selected');
		if ($prev.length) {
			$item.insertBefore($prev);
			updateSortButtons($container);
			updateOrderInputs($container);
		}
	});

	$(document).on('click', '.fi-vote-move-down', function(e) {
		e.preventDefault();
		const $item = $(this).closest('.fi-vote-item-selected');
		const $container = $item.closest('[id^="fi-selected-votes"]');
		const $next = $item.next('.fi-vote-item-selected');
		if ($next.length) {
			$item.insertAfter($next);
			updateSortButtons($container);
			updateOrderInputs($container);
		}
	});

	updateSortButtons($('#fi-selected-votes-h'));
	updateSortButtons($('#fi-selected-votes-s'));

	$(document).on('click', '.fi-vote-preview', function(e) {
		e.preventDefault();
		e.stopPropagation();
		const voteId = $(this).data('vote-id');
		if (!voteId || typeof ajaxurl === 'undefined') return;
		const $modal = $('#fi-vote-preview-modal');
		const $content = $('#fi-vote-preview-content');
		$content.html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
		if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
			new bootstrap.Modal($modal[0]).show();
		} else {
			$modal.show();
		}
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: { action: 'fi_admin_action', sub_action: 'get_vote_preview', vote_id: voteId, nonce: (window.fiAdmin && window.fiAdmin.nonce) || '' },
			success: function(response) {
				$content.html(response?.success && response.data?.html ? response.data.html : '<div class="alert alert-danger">Error loading vote preview.</div>');
			},
			error: function() {
				$content.html('<div class="alert alert-danger">Error loading vote preview.</div>');
			}
		});
	});

	bindVoteEvents();
	updateSelectedCount();
})(jQuery);
</script>

<!-- Vote Preview Modal -->
<div class="modal fade" id="fi-vote-preview-modal" tabindex="-1" aria-labelledby="fi-vote-preview-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="fi-vote-preview-modal-label">Vote Preview</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body" id="fi-vote-preview-content">
				<!-- Content loaded via AJAX -->
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>
<?php else: ?>
</div>
<?php endif; ?>

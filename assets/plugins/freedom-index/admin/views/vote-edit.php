<?php if (!defined('ABSPATH')) {exit;}

$is_edit = ($action === 'edit');
$vote_id = $is_edit ? absint($_GET['vote_id'] ?? 0) : 0;

if ($is_edit && !$vote_id) {
	wp_die('Missing vote ID.');
}

$vote = $is_edit ? fi_vote_get($vote_id) : fi_admin_votes_get_defaults($scope);
if ($is_edit && !$vote) {
	wp_die('Vote not found.');
}

$sessions = fi_sessions_get_by_gov($scope['gov'] ?? 'US');
$session_options = [];
foreach ($sessions as $session) {
	$session_options[$session['id']] = $session['name'];
}

$chamber_options = fi_chamber_options($scope['gov'] ?? 'US');
$status_options = fi_admin_votes_get_status_options();
$constitutional_options = ['Y' => 'Constitutional (Y)', 'N' => 'Unconstitutional (N)'];
$tag_options = fi_admin_votes_get_tag_options($scope['gov'] ?? null);
$meta_fields = fi_admin_votes_get_meta_fields();

$selected_tags = [];
$vote_tags = $is_edit ? fi_vote_tags_get_tags_by_vote($vote_id) : [];
foreach ($vote_tags as $tag) {
	$selected_tags[] = (int) ($tag['id'] ?? 0);
}

$vote_meta = fi_admin_votes_decode_meta($vote);

// Ensure vote_meta is always an array
if (!is_array($vote_meta)) {
	$vote_meta = [];
}
$extra_meta = fi_admin_votes_get_extra_meta($vote, $meta_fields);

$rollcall_summary = $is_edit ? fi_rollcall_summary($vote_id) : null;
$form_action = $is_edit
	? fi_admin_edit_vote_url($vote_id)
	: fi_admin_url('fi-votes', ['action' => 'add']);
$page_title = $is_edit ? 'Edit Vote' : 'Add Vote';

$vote_id = $vote['id'] ?? 0;
$session_name = $vote['session_name'] ?? '';
$is_edit = !empty($vote_id);
$assigned_tags = $tag_objects ?? [];
$scope = $scope ?? [];

$gov = $vote['gov'] ?? ($scope['gov'] ?? 'US');
$gov_name = fi_gov_name($gov);

?>
<?php fi_scope_render_selector(); ?>
<?php fi_scope_content_check($scope['gov'], $gov, 'vote'); ?>
<div class="wrap fi-vote-edit" data-vote-id="<?php echo esc_attr($vote_id); ?>">
<?php //if(get_current_user_id() == 1){echo '<textarea style="width:100%; height:400px;">'; print_r($vote); echo '</textarea>';}?>
	<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 bg-light p-2 rounded-3" style="position: sticky; top: 32px; z-index: 100;">
		<h1 class="wp-heading-inline m-0"><?php echo esc_html($page_title); ?></h1>
		<div class="d-flex align-items-center gap-2 ms-auto">
			<div class="btn-group" role="group" aria-label="Vote actions">
				<a href="<?php echo esc_url(fi_admin_url('fi-votes')); ?>" class="btn btn-sm btn-outline-secondary">Back to Votes</a>
				<button type="submit" form="fi-vote-form" class="btn btn-sm btn-primary">Save</button>
				<a
					href="<?php echo esc_url(fi_admin_url('fi-votes')); ?>"
					class="btn btn-sm btn-outline-secondary"
					onclick="return confirm('Discard changes and return to the list?');"
				>Cancel</a>
				<?php if ($is_edit): ?>
					<button
						type="button"
						class="btn btn-sm btn-outline-danger"
						onclick="if (confirm('Are you sure you want to delete this vote? This will also delete all related roll-call records. This action cannot be undone.')) { document.getElementById('fi-vote-delete-form').submit(); }"
					>Delete</button>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<hr class="wp-header-end">

	<?php settings_errors('fi_votes'); ?>
	

	<?php if (!empty($_GET['meta_fixed'])): ?>
		<div class="notice notice-success is-dismissible">
			<p>Vote meta normalized successfully.</p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url($form_action); ?>" id="fi-vote-form">
		<?php wp_nonce_field('fi_save_vote', 'fi_vote_nonce'); ?>
		<?php fi_form_field('vote_id', ['type' => 'hidden', 'value' => $vote_id]); ?>
		<?php fi_form_field('slug', ['type' => 'hidden', 'value' => $vote['slug']]); ?>

		<?php if ($is_edit && $vote_id && function_exists('fi_admin_votes_meta_is_json_string') && fi_admin_votes_meta_is_json_string((int) $vote_id)): ?>
			<div class="notice notice-warning">
				<p><strong>Notice:</strong> This vote’s <code>meta</code> is stored as a JSON string (double-encoded). Normalize it once to restore fields like <code>url_bill</code>.</p>
				<div class="mt-2">
					<button type="submit" form="fi-vote-meta-normalize-form" class="button button-primary">Normalize Meta Now</button>
				</div>
			</div>
		<?php endif; ?>
		<div class="row g-4">
			<div class="col-12 col-xl-8">
				<!-- Vote Details Section -->
				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white border-0 pb-0">
						<h2 class="h4 mb-0 pb-0">Vote Details</h2>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<div class="col-md-5">
								<?php fi_form_field('title', [
									'label' => 'Vote Title',
									'value' => $vote['title'] ?? '',
									'required' => true
								]); ?>
							</div>
							<div class="col-md-4">
								<?php fi_form_field('session_id', [
									'label' => 'Session',
									'type' => 'select',
									'options' => $session_options,
									'value' => $vote['session_id'] ?? '',
									'required' => true
								]); ?>
							</div>
							<div class="col-md-3">
								<?php fi_form_field_status('status', [
									'label' => 'Status',
									'value' => $vote['status'] ?? 'publish'
								]); ?>
							</div>

							<div class="col-md-2">
								<?php 
								// Format date for HTML5 date input (requires Y-m-d format)
								$date_value = '';
								if (!empty($vote['date_voted'])) {
									// Extract date portion from DATETIME (first 10 characters: YYYY-MM-DD)
									$date_value = substr($vote['date_voted'], 0, 10);
								}
								fi_form_field('date_voted', [
									'label' => 'Date Voted',
									'type' => 'date',
									'value' => $date_value
								]); ?>
							</div>
							<div class="col-md-2">
								<?php fi_form_field_chamber('chamber', $gov, [
									'label' => 'Chamber',
									'value' => $vote['chamber'] ?? '',
									'required' => true,
								]); ?>
							</div>

							<div class="col-md-2">
								<?php fi_form_field('bill_number', [
									'label' => 'Bill Number',
									'value' => $vote['bill_number'] ?? ''
								]); ?>
							</div>
							<div class="col-md-2">
								<?php fi_form_field('rollcall_number', [
									'label' => 'Roll-call Number',
									'value' => $vote['rollcall_number'] ?? ''
								]); ?>
							</div>

							<div class="col-md-4">
							<?php
							$ls_yea = isset($vote_meta['votes_yea']) ? (int) $vote_meta['votes_yea'] : null;
							$ls_nay = isset($vote_meta['votes_nay']) ? (int) $vote_meta['votes_nay'] : null;
							if ($ls_yea !== null || $ls_nay !== null){
								$vote_outcome_text = '<div class="text-center"><span class="text-muted small">Yea: <strong>' . esc_html((string) ($ls_yea ?? '—')) . '</strong>&nbsp;·&nbsp;Nay: <strong>' . esc_html((string) ($ls_nay ?? '—')) . '</strong></span></div>';
							}else{
								$vote_outcome_text = '';
							}
							?>
							<?php fi_form_field('meta_vote_outcome', [
								'name'    => 'meta[vote_outcome]',
								'label'   => 'Vote Outcome',
								'type'    => 'radio-group',
								'options' => ['1' => 'Passed', '0' => 'Rejected'],
								'value'   => $vote_meta['vote_outcome'] ?? '',
								'help'    => $vote_outcome_text,
							]); ?>
							</div>

						<div class="col-md-6">
							<?php fi_form_field('meta_url_bill', [
								'name' => 'meta[url_bill]',
								'label' => 'Bill URL',
								'label_html' => ($vote_meta['url_bill'] != '' ? '<a href="' . $vote_meta['url_bill'] . '" target="_blank" rel="noopener">Bill URL</a>' : 'Bill URL'),
								'type' => 'url',
								'value' => $vote_meta['url_bill'] ?? '',
								'help' => 'Official bill URL',
							]);?>
						</div>
						<div class="col-md-6">
							<?php fi_form_field('meta_url_rollcall', [
								'name' => 'meta[url_rollcall]',
								'label' => 'Roll-call URL',
								'label_html' => ($vote_meta['url_rollcall'] != '' ? '<a href="' . $vote_meta['url_rollcall'] . '" target="_blank" rel="noopener">Roll-call URL</a>' : 'Roll-call URL'),
								'type' => 'url',
								'value' => $vote_meta['url_rollcall'] ?? '',
								'help' => 'Official roll-call URL',
							]);?>
						</div>
						</div>
					</div>
				</div>

				<!-- Position Statements Section -->
				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white border-0 pb-0">
						<h2 class="h4 mb-0 pb-0">Position Statements</h2>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<div class="col-md-3">
								<?php fi_form_field_constitutional('constitutional', [
									'label' => 'Constitutional Position',
									'value' => $vote['constitutional'] ?? 'U',
									'required' => false
								]); ?>
							</div>
							
							<div class="col-md-2">
								<?php 
								fi_form_field('meta_cost', [
									'name' => 'meta[cost]',
									'label' => 'Cost/Impact',
									'type' => 'text',
									'value' => $vote_meta['cost'] ?? '',
								]);
								?>
							</div>
							<div class="col-md-7">
							<?php
							// Parse existing value: may be array (new), JSON string, or legacy plain text (ignored).
							$_citation_raw = $vote_meta['citation'] ?? [];
							if (is_string($_citation_raw)) {
								$_decoded = json_decode($_citation_raw, true);
								$_citation_keys = (is_array($_decoded)) ? $_decoded : [];
							} else {
								$_citation_keys = is_array($_citation_raw) ? $_citation_raw : [];
							}
							?>
							<label class="form-label fw-semibold" for="meta_citation">Constitutional Citation</label>
							<select id="meta_citation" name="meta[citation][]" multiple
								class="form-select"
								placeholder="Select citations...">
								<?php foreach (FI_CONSTITUTION_LINKS as $_ckey => $_cval): ?>
									<?php if (is_array($_cval)): ?>
									<optgroup label="<?php echo esc_attr($_cval['title']); ?>">
										<option value="<?php echo esc_attr($_ckey); ?>"<?php echo in_array($_ckey, $_citation_keys, true) ? ' selected' : ''; ?>>
											<?php echo esc_html($_cval['title']); ?>
										</option>
										<?php foreach ($_cval['sections'] as $_skey => $_slabel): ?>
										<option value="<?php echo esc_attr($_skey); ?>"<?php echo in_array($_skey, $_citation_keys, true) ? ' selected' : ''; ?>>
											<?php echo esc_html($_slabel); ?>
										</option>
										<?php endforeach; ?>
									</optgroup>
									<?php else: ?>
									<option value="<?php echo esc_attr($_ckey); ?>"<?php echo in_array($_ckey, $_citation_keys, true) ? ' selected' : ''; ?>>
										<?php echo esc_html($_cval); ?>
									</option>
									<?php endif; ?>
								<?php endforeach; ?>
							</select>
						</div>
							<div class="col-12">
							<?php fi_form_field('meta_impact_summary', [
								'name'            => 'meta[impact_summary]',
								'label'           => 'Impact Summary',
								'type'            => 'wysiwyg',
								'value'           => $vote_meta['impact_summary'] ?? '',
								'help'            => "Answer the user's question: Why should this matter to me?",
								'editor_settings' => [
									'textarea_rows' => 2,
									'media_buttons' => false,
									'teeny'         => 1,
									'tinymce'       => ['height' => 50],
								],
							]); ?>
						</div>
						<div class="col-12">
							<?php fi_form_field('meta_description_short', [
								'name' => 'meta[description_short]',
								'label' => 'Short Description (legacy)',
								'type' => 'wysiwyg',
								'value' => $vote_meta['description_short'] ?? '',
								'help' => 'Fallback text used on cards when Impact Summary is empty.',
								'editor_settings' => [
									'textarea_rows' => 3,
									'media_buttons' => false,
									'teeny' => 1,
									'tinymce' => ['height' => 75]
								],
							]); ?>
						</div>
							<div class="col-12">
								<?php fi_form_field('meta_description_medium', [
									'name' => 'meta[description_medium]',
									'label' => 'Medium Description',
									'type' => 'wysiwyg',
									'value' => $vote_meta['description_medium'] ?? '',
									'editor_settings' => [
										'textarea_rows' => 6,
										'media_buttons' => false,
										'teeny' => false,
										'tinymce' => ['height' => 150]
									],
								]); ?>
							</div>
							<div class="col-12">
								<?php fi_form_field('meta_description_long', [
									'name' => 'meta[description_long]',
									'label' => 'Long Description',
									'type' => 'wysiwyg',
									'value' => $vote_meta['description_long'] ?? '',
									'editor_settings' => [
										'textarea_rows' => 10,
										'media_buttons' => false,
										'teeny' => false,
										'tinymce' => ['height' => 250]
									],
								]); ?>
							</div>
							<div class="col-12 border-top pt-3 mt-2">
								<label class="form-label small text-muted">Image</label>
								<?php echo fi_admin_helpers_render_vote_image_media_picker((int) ($vote_meta['image_id'] ?? 0)); ?>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="col-12 col-xl-4">
			<div class="card shadow-sm mb-4">
				<div class="card-header bg-white border-0">
					<h2 class="h5 mb-0 pb-0">Vote Snapshot</h2>
				</div>
				<div class="card-body">
					<div class="row g-3">
						<div class="col-lg-6">
							<ul class="list-unstyled mb-0">
								<li><strong>Session:</strong> <?php echo esc_html((string) ($session_name ?: '—')); ?></li>
								<li><strong>Status:</strong> <?php 
									$status_value = $vote['status'] ?? 'publish';
									$status_display = $status_options[$status_value] ?? ucfirst($status_value);
									// Ensure status_display is a string
									$status_display = is_array($status_display) ? (string) $status_value : (string) ($status_display ?? $status_value);
									echo esc_html($status_display);
								?></li>
								<li><strong>Government:</strong> <?php echo $gov_name; ?></li>
								<li><strong>Chamber:</strong> <?php 
									$chamber = $vote['chamber'] ?? '';
									if ($chamber) {
										$chamber_label = fi_chamber_label($gov, $chamber);
										echo esc_html((string) ($chamber_label ?? $chamber));
									} else {
										echo '—';
									}
								?></li>
							</ul>
						</div>

						<div class="col-lg-6">
							<ul class="list-unstyled mb-0">
								<li><strong>Constitutional:</strong> <?php 
									$const = $vote['constitutional'] ?? 'U';
									echo esc_html($const === 'Y' ? 'Yes' : ($const === 'N' ? 'No' : 'Unknown'));
								?></li>
								<li><strong>Date:</strong> 
									<?php 
										$date_voted_display = isset($date_voted) && $date_voted ? date('n/j/Y', strtotime($date_voted)) : '—';
										echo esc_html((string) $date_voted_display); 
									?>
								</li>
								<li><strong>Date Created:</strong> 
									<?php 
										$date_created = $vote['date_created'] ?? null;
										echo esc_html($date_created ? date('n/j/Y', strtotime($date_created)) : '—'); 
									?>
								</li>
								<li><strong>Date Updated:</strong> 
									<?php 
										$date_updated = $vote['date_updated'] ?? null;
										echo esc_html($date_updated ? date('n/j/Y', strtotime($date_updated)) : '—'); 
									?>
								</li>
							</ul>
						</div>
					</div>

					<?php if ($is_edit && $rollcall_summary && ($rollcall_summary['total_votes'] ?? 0) > 0): ?>
						<div class="mt-1 pt-1 border-top">
							<div class="small mt-0">
							<strong>Roll Call: </strong>
							<strong>Total:</strong> <?php echo esc_html((string) ($rollcall_summary['total_votes'] ?? 0)); ?> | 
							<strong>Yes:</strong> <?php echo esc_html((string) ($rollcall_summary['yes'] ?? 0)); ?> | 
							<strong>No:</strong> <?php echo esc_html((string) ($rollcall_summary['no'] ?? 0)); ?> | 
							<strong>Present:</strong> <?php echo esc_html((string) ($rollcall_summary['present'] ?? 0)); ?> |
							<strong>Not Voted:</strong> <?php echo esc_html((string) ($rollcall_summary['not_voting'] ?? 0)); ?>
							</div>
							<?php
							$ls_total = isset($vote_meta['votes_total']) ? (int) $vote_meta['votes_total'] : null;
							$ls_yea = isset($vote_meta['votes_yea']) ? (int) $vote_meta['votes_yea'] : null;
							$ls_nay = isset($vote_meta['votes_nay']) ? (int) $vote_meta['votes_nay'] : null;
							$ls_nv = isset($vote_meta['votes_nv']) ? (int) $vote_meta['votes_nv'] : null;
							$ls_absent = isset($vote_meta['votes_absent']) ? (int) $vote_meta['votes_absent'] : null;
							$ls_not_voted = ($ls_nv !== null ? $ls_nv : 0) + ($ls_absent !== null ? $ls_absent : 0);
							$has_legiscan_counts = $ls_total !== null || $ls_yea !== null || $ls_nay !== null || $ls_nv !== null || $ls_absent !== null;
							?>
							<?php if ($has_legiscan_counts): ?>
								<div class="small text-muted mt-1">
									<strong>Legiscan:</strong>
									<?php if ($ls_total !== null): ?> <strong>Total:</strong> <?php echo esc_html((string) $ls_total); ?> |<?php endif; ?>
									<?php if ($ls_yea !== null): ?> <strong>Yea:</strong> <?php echo esc_html((string) $ls_yea); ?> |<?php endif; ?>
									<?php if ($ls_nay !== null): ?> <strong>Nay:</strong> <?php echo esc_html((string) $ls_nay); ?> |<?php endif; ?>
									<?php if ($ls_nv !== null || $ls_absent !== null): ?> <strong>Not Voted:</strong> <?php echo esc_html((string) $ls_not_voted); ?><?php endif; ?>
								</div>
							<?php endif; ?>
						</div>
					<?php elseif ($is_edit): ?>
						<div class="mt-1 pt-1 border-top">
							<strong>Roll Call:</strong>
							<p class="text-muted small mb-0 mt-0">No roll-call data yet.</p>
						</div>
					<?php endif; ?>
					<div class="mt-1 pt-2 border-top">
					<?php 
					if ($is_edit){
						echo '<a href="' . esc_url(fi_admin_roll_call_edit_url($vote_id)) . '" class="btn btn-sm btn-primary">Edit Roll Call</a>';
					}
					if (!empty($vote_meta['url_legiscan'])){
						echo '<a href="' . esc_url((string) $vote_meta['url_legiscan']) . '" class="btn btn-sm btn-outline-primary ms-2" target="_blank">Legiscan Bill Info</a>';
					}
					if (!empty($vote_meta['url_bill'])){
						echo '<a href="' . esc_url((string) $vote_meta['url_bill']) . '" class="ms-2 btn btn-sm btn-outline-primary" target="_blank">View Bill <i class="fa-solid fa-arrow-up-right-from-square"></i></a>';
					}
					if (!empty($vote_meta['url_rollcall'])){
						echo '<a href="' . esc_url((string) $vote_meta['url_rollcall']) . '" class="ms-2 btn btn-sm btn-outline-primary" target="_blank">View Rollcall <i class="fa-solid fa-arrow-up-right-from-square"></i></a>';
					}
					?>
					</div>
				</div>
				<?php
				// Summary: page actions are in the sticky top action bar; avoid duplicate bottom buttons.
				?>
			</div>

			<div class="card shadow-sm mb-4">
				<div class="card-header bg-white border-0">
					<h2 class="h5 mb-0 pb-0">Legiscan</h2>
				</div>
				<div class="card-body">
					<div class="row g-3 mb-0">
						<div class="col-md-6">
							<?php fi_form_field('legiscan_bid', [
								'label' => 'Legiscan Bill ID',
								'type' => 'number',
								'value' => $vote['legiscan_bid'] ?? '',
								'help' => 'Legiscan bill ID for cross-reference'
							]); ?>
						</div>
						<div class="col-md-6">
							<?php fi_form_field('legiscan_rcid', [
								'label' => 'Legiscan Rollcall ID',
								'type' => 'number',
								'value' => $vote['legiscan_rcid'] ?? '',
								'help' => 'Legiscan rollcall ID for cross-reference'
							]); ?>
						</div>
					</div>
					<?php 
					$legiscan_bill_title = $vote_meta['bill_title'] ?? '';
					$legiscan_bill_description = $vote_meta['bill_description'] ?? '';
					$legiscan_vote_title = $vote_meta['vote_title'] ?? '';
					if ($legiscan_bill_title || $legiscan_bill_description):
					?>
						<div class="pt-3 border-top">
							<?php if ($legiscan_bill_title): ?>
								<div class="mb-3">
									<strong>Bill Title:</strong>
									<p class="mb-0"><?php echo esc_html((string) $legiscan_bill_title); ?></p>
								</div>
							<?php endif; ?>
							
							<?php if ($legiscan_bill_description): ?>
								<div class="mb-3">
									<strong>Bill Description:</strong>
									<p class="mb-0 text-muted small"><?php echo esc_html((string) $legiscan_bill_description); ?></p>
								</div>
							<?php endif; ?>

							<?php if ($legiscan_vote_title): ?>
								<div class="mb-3">
									<strong>Vote Title:</strong>
									<p class="mb-0"><?php echo esc_html((string) $legiscan_vote_title); ?></p>
								</div>
							<?php endif; ?>
							
						</div>
					<?php else: ?>
						<div class="pt-3 border-top">
							<p class="text-muted mb-0 small">No Legiscan data available.</p>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="card shadow-sm mb-4">
				<div class="card-header bg-white border-0">
					<h2 class="h5 mb-0 pb-0">Tags / Issues</h2>
				</div>
				<div class="card-body">
					<label class="form-label fw-semibold" for="vote_tags_select">Issues</label>
					<select id="vote_tags_select" name="vote_tags[]" multiple
						class="form-select" 
						placeholder="Select issues…">
						<?php foreach ($tag_options as $_tid => $_tlabel): ?>
						<option value="<?php echo esc_attr($_tid); ?>"<?php echo in_array((int) $_tid, $selected_tags, true) ? ' selected' : ''; ?>>
							<?php echo esc_html($_tlabel); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="card shadow-sm mb-4">
				<div class="card-header bg-white border-0">
					<h2 class="h5 mb-0 pb-0">Additional Meta</h2>
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
											<td>
												<div class="fs-5 fw-bold text-muted"><?php echo esc_html((string) ($key ?? '')); ?></div>
												<div style="max-height:200px; overflow-y:auto;"><?php 
												$display_value = is_scalar($value) ? (string) $value : wp_json_encode($value);
												echo esc_html((string) ($display_value !== false ? $display_value : ''));
											?></div></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>

<?php 
echo "\n<!-- US Congres Rollcall Fetch\nGOV=" . $gov . "\nVOTE_ID=" . $vote_id . "\nURL_ROLLCALL=" . $vote_meta['url_rollcall'] . "\n-->";
/* VALUES CHECK OUT but not showing.
<!-- US Congres Rollcall Fetch
GOV=US
VOTE_ID=351
URL_ROLLCALL=http://www.senate.gov/legislative/LIS/roll_call_lists/roll_call_vote_cfm.cfm?congress=108&session=2&vote=00088
-->
*/
if($gov == 'US' && $vote['session_id'] && $vote_meta['url_rollcall']){
	fi_api_gov_data($vote['session_id'],$vote_id,$vote_meta['url_rollcall']);
}
?>

		</div>
	</form>
</div>

<?php if ($is_edit && $vote_id && function_exists('fi_admin_votes_meta_is_json_string') && fi_admin_votes_meta_is_json_string((int) $vote_id)): ?>
	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="fi-vote-meta-normalize-form" style="display:none;">
		<?php wp_nonce_field('fi_votes_fix_meta_json'); ?>
		<input type="hidden" name="action" value="fi_votes_fix_meta_json">
		<input type="hidden" name="vote_id" value="<?php echo esc_attr((int) $vote_id); ?>">
	</form>
<?php endif; ?>

<?php if ($is_edit && $vote_id): ?>
	<form
		id="fi-vote-delete-form"
		method="post"
		action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
		style="display:none;"
	>
		<?php wp_nonce_field('fi_delete_vote', 'fi_delete_vote_nonce'); ?>
		<input type="hidden" name="action" value="fi_delete_vote">
		<input type="hidden" name="vote_id" value="<?php echo esc_attr($vote_id); ?>">
	</form>
<?php endif; ?>
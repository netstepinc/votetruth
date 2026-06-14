<?php
if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
	wp_safe_redirect(home_url('/account/'));
	exit;
}

global $fi_list_id;
$list_id = $fi_list_id ?? get_query_var('fi_list_id');

if (!$list_id) {
	wp_safe_redirect(home_url('/account/lists/'));
	exit;
}

$list_obj = fi_list_get_by_id((int) $list_id);

if (!$list_obj || $list_obj->user_id != get_current_user_id()) {
	wp_safe_redirect(home_url('/account/lists/'));
	exit;
}

$legislator_ids = json_decode($list_obj->legislators, true);
$list = !empty($legislator_ids) ? fi_legislators_get_by_ids($legislator_ids, true) : [];
$title = (isset($list_obj->name) ? $list_obj->name . ' ' : '') . 'Legislator List';

$list_meta = !empty($list_obj->meta) ? json_decode($list_obj->meta, true) : [];
$current_contact_index = $list_meta['contact_index'] ?? '';
$pdf_contacts = fi_pdf_contacts_get($list_obj->user_id);

$instructions = '<h4>How does this work?</h4>
<p class="mb-1">Find the legislators you want in this list via the search tools then click the <b>Add to My Lists</b> button on the legislator page.</p>
<p class="mb-0">We will add more list features as soon as possible including PDF printing.</p>';
?>
<div class="row">
	<?php fi_get_public_template('partials/account-nav', ['current_page' => 'lists']); ?>
	<div id="fi-list-content" class="col-12 col-md-9">
		<div class="row g-3">
			<div class="col-12 col-md-6 col-lg-4">
				<div class="card rounded-3 h-100">
					<div class="card-body">
						<h4 class="card-title">Edit List Name</h4>
						<form id="fi-list-name-form" class="row g-2 align-items-end">
						<label for="fi-list-name-input" class="form-label visually-hidden">List name</label>
						<input type="text" id="fi-list-name-input" class="form-control" name="name" value="<?php echo esc_attr($list_obj->name); ?>" placeholder="List name" required />
						<button type="submit" id="fi-list-name-btn" class="btn btn-sm btn-primary w-100">Save name</button>
						<div class="text-danger fw-bold small">ATTENTION: Use of profane or offensive words will result in your account being DELETED.</div>
						</form>
					</div>
				</div>
			</div>
		<?php if (empty($list)): ?>
			<div class="col-12 col-md-6 col-lg-8">
				<div class="alert alert-info">
					<h4>Empty List</h4>
					<p class="mb-0">This list doesn't contain any legislators yet.</p>
					<div class="mt-3"><?php echo $instructions; ?></div>
				</div>
			</div>
		<?php else: ?>
			<div class="col-12 col-md-6 col-lg-4">
				<div class="card rounded-3 h-100">
					<div class="card-body">
						<h4 class="card-title">List Summary</h4>
						<div class="card-text mb-1">
							<strong><?php echo count($list); ?></strong> legislator<?php echo count($list) !== 1 ? 's' : ''; ?> in this list
						</div>

						<!-- <h4 class="card-title">Party Breakdown</h4> -->
						<?php
						$party_list = fi_parties();
						$party_counts = [];
						foreach ($list as $legislator) {
							$party = is_object($legislator) ? ($legislator['party'] ?? 'Unknown') : ($legislator['party'] ?? 'Unknown');
							if (!isset($party_list[$party])) {
								$party_list[$party] = 0;
							}
							$party_counts[$party] = ($party_counts[$party] ?? 0) + 1;
						}
						?>
						<div class="fi-party-breakdown">
							<?php foreach ($party_counts as $party => $count): ?>
								<div class="card-text mb-1">
									<strong><?php echo esc_html($count); ?></strong>
									<?php echo esc_html($party_list[$party]['name']); ?>
								</div>
							<?php endforeach; ?>
						</div>

						<?php
						$average_stats = fi_score_calculate_average($list);
						$average_score = $average_stats['average'];
						?>
						<div class="card-text"><strong><?= $average_score; ?>%</strong> Average Score</div>
						<div class="mt-3">
							<button type="button" class="btn btn-outline-success btn-sm" onclick="FI.copyListURL()">
								<i class="bi bi-clipboard me-1"></i> Copy Link
							</button>
						</div>
					</div>
				</div>
			</div>
			<div class="col-12 col-md-6 col-lg-4">
				<div class="card rounded-3 h-100">
					<div class="card-body">
						<h4 class="card-title">List Contact Info</h4>
						<div class="mb-3">
							<select id="fi-list-contact-select" class="form-select" data-list-id="<?php echo esc_attr($list_id); ?>">
								<option value="">Do not display a contact</option>
								<?php foreach ($pdf_contacts as $index => $contact): ?>
									<option value="<?php echo esc_attr($index); ?>" <?php selected($current_contact_index, $index); ?>>
										<?php echo esc_html($contact['name'] ?? 'Contact ' . ($index + 1)); ?>
									</option>
								<?php endforeach; ?>
							</select>
<?php
//TODO: Add multiple contacts
//TODO: Option to show on public view | default is NO. Coded as NO for now, but make it optional.
?>

							<div class="form-text">Choose which contact information to display on the PDF versions of this list.<br><span class="text-danger">Note: The PDF option is coming soon.</span></div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-12">
				<div class="alert alert-info mb-3"><?php echo $instructions; ?></div>
				<?php echo fi_list_render_legislators($list, $list_id); ?>
			</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	window.FI = window.FI || {};
	window.FI.copyListURL = function() {
		const publicUrl = '<?php echo esc_js(home_url('/list/' . (int) $list_obj->id . '/')); ?>';
		navigator.clipboard.writeText(publicUrl).then(function() {
			alert('Public list URL copied to clipboard!');
		}).catch(function() {
			alert('Failed to copy URL. Please copy it manually.');
		});
	};
	$('#fi-list-name-form').on('submit', function(e) {
		e.preventDefault();
		var $btn = $('#fi-list-name-btn').prop('disabled', true);
		var name = $('#fi-list-name-input').val().trim();
		if (!name) {
			$btn.prop('disabled', false);
			alert('Please enter a list name.');
			return;
		}
		$.ajax({
			url: '<?php echo admin_url('admin-ajax.php'); ?>',
			type: 'POST',
			data: {
				action: 'fi_update_list_name',
				list_id: <?php echo (int) $list_id; ?>,
				name: name,
				nonce: '<?php echo wp_create_nonce('fi_list_manage'); ?>'
			},
			success: function(response) {
				$btn.prop('disabled', false);
				if (response.success) {
					location.reload();
				} else {
					alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Failed to update name'));
				}
			},
			error: function() {
				$btn.prop('disabled', false);
				alert('Error updating list name. Please try again.');
			}
		});
	});
	$('#fi-list-contact-select').on('change', function() {
		const listId = $(this).data('list-id');
		const contactIndex = $(this).val();
		$.ajax({
			url: '<?php echo admin_url('admin-ajax.php'); ?>',
			type: 'POST',
			data: {
				action: 'fi_update_list_contact',
				list_id: listId,
				contact_index: contactIndex,
				nonce: '<?php echo wp_create_nonce('fi_list_manage'); ?>'
			},
			success: function(response) {
				if (response.success) {
					const $select = $('#fi-list-contact-select');
					const originalBg = $select.css('background-color');
					$select.css('background-color', '#d4edda');
					setTimeout(function() {
						$select.css('background-color', originalBg);
					}, 1000);
				} else {
					alert('Error: ' + (response.data?.message || 'Failed to update contact selection'));
					location.reload();
				}
			},
			error: function() {
				alert('Error updating contact selection. Please try again.');
				location.reload();
			}
		});
	});
});
</script>
<?php
if (!defined('ABSPATH')) exit;
/*
 * REFACTOR PLANNED: Create/delete list use AJAX + bustReload() (timestamp URL).
 * This works correctly but gains nothing over a traditional form POST.
 * Plan: convert to standard POST + wp_safe_redirect (same pattern as login-logout.php).
 * Backend AJAX handlers: fi_create_list (nonce: fi_list_manage), fi_delete_list (nonce: fi_delete_list).
 * Find those handlers before refactoring to reuse their logic server-side.
 * WARNING: legislator-modal-list.php uses DIFFERENT actions (fi_modal_create_list, fi_update_list, nonce: fi_list_nonce).
 * Those are true AJAX DOM updates — do NOT convert or touch them.
 */

if (!is_user_logged_in()) {
	wp_safe_redirect(home_url('/account/'));
	exit;
}

// Single list: /account/lists/{id}/ — show account-list.php
$list_id = get_query_var('fi_list_id');
if ($list_id) {
	global $fi_list_id;
	$fi_list_id = $list_id;
	include FI_PUBLIC_DIR . 'account/account-list.php';
	return;
}

$user_id = get_current_user_id();
$user_lists = fi_lists_get_by_user($user_id);
?>
<div class="row">
	<?php fi_get_public_template('account-nav', ['current_page' => 'lists']); ?>
	<div class="col-12 col-md-9">
		<div class="row g-3">
			<div class="col-12 col-md-6 col-lg-4">
				<!-- Inline create list (card, same style as list cards) -->
				<div class="card mb-4 rounded-4 shadow h-100">
					<div class="card-header">
						<h5 class="card-title mb-0">Create New List</h5>
					</div>
					<div class="card-body pb-1">
						<form id="fi-list-create-form" class="row g-2 align-items-end">
							<label for="fi-list-name" class="form-label visually-hidden">List Name</label>
							<input type="text" id="fi-list-name" name="name" class="form-control" placeholder="List name" required>
							<button type="submit" class="btn btn-sm btn-primary w-100" id="fi-create-list-btn">
								<i class="bi bi-plus-circle me-1"></i> Create List
							</button>
						</form>
					</div>
				</div>
			</div>
	<?php if (!empty($user_lists)): ?>
		<?php foreach ($user_lists as $list):
			$legislator_ids = json_decode($list->legislators, true);
			$legislator_count = is_array($legislator_ids) ? count($legislator_ids) : 0;
			$list_manage_url = home_url('/account/lists/' . $list->id . '/');
			?>
			<div class="col-12 col-md-6 col-lg-4">
				<div class="card h-100 rounded-4 shadow h-100">
					<div class="card-body pb-1">
						<h4 class="card-title">
							<a href="<?php echo esc_url($list_manage_url); ?>" class="text-decoration-none">
								<?php echo esc_html($list->name ?? 'Unnamed List'); ?>
							</a>
						</h4>
						<p class="card-text text-muted small mb-2">
							<?php echo esc_html($legislator_count); ?> legislator<?php echo $legislator_count !== 1 ? 's' : ''; ?>
						</p>
						<p class="card-text text-muted small mb-3">
							Created <?php echo esc_html(date('M j, Y', strtotime($list->date_created))); ?>
						</p>
						<div class="d-flex gap-2">
							<a href="<?php echo esc_url($list_manage_url); ?>"
								class="btn btn-sm btn-primary flex-fill">
								View
							</a>
							<a href="<?php echo esc_url($list_manage_url); ?>"
								class="btn btn-sm btn-outline-secondary"
								title="Edit">
								<i class="bi bi-pencil"></i>
							</a>
							<button type="button"
									class="btn btn-sm btn-outline-danger fi-delete-list"
									data-list-id="<?php echo esc_attr($list->id); ?>"
									title="Delete">
								<i class="bi bi-trash"></i>
							</button>
						</div>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	<?php else: ?>
			<div class="col-12 col-md-6 col-lg-4">
				<div class="alert alert-info">
					<h4>No Lists Yet</h4>
					<p class="mb-0">You haven't created any lists yet. Use the form above to create one, or find your elected officials and add them to a list.</p>
				</div>
			</div>
	<?php endif; ?>
		</div><!-- .row -->
	</div><!-- .col-12 col-md-9 -->
</div><!-- .row -->

<script>
jQuery(document).ready(function($) {
	function bustReload() {
		var base = window.location.pathname;
		window.location.href = base + '?_=' + Date.now();
	}

	// Inline create list
	$('#fi-list-create-form').on('submit', function(e) {
		e.preventDefault();
		var listName = $('#fi-list-name').val().trim();
		if (!listName) {
			alert('Please enter a list name.');
			return;
		}
		var $btn = $('#fi-create-list-btn').prop('disabled', true);
		$.ajax({
			url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
			type: 'POST',
			data: {
				action: 'fi_create_list',
				name: listName,
				nonce: '<?php echo wp_create_nonce('fi_list_manage'); ?>'
			},
			success: function(response) {
				$btn.prop('disabled', false);
				if (response.success) {
					bustReload();
				} else {
					alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
				}
			},
			error: function() {
				$btn.prop('disabled', false);
				alert('Error saving list. Please try again.');
			}
		});
	});

	$('.fi-delete-list').on('click', function() {
		if (!confirm('Are you sure you want to delete this list? This action cannot be undone.')) {
			return;
		}
		var listId = $(this).data('list-id');
		$.ajax({
			url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
			type: 'POST',
			data: {
				action: 'fi_delete_list',
				list_id: listId,
				nonce: '<?php echo wp_create_nonce('fi_delete_list'); ?>'
			},
			success: function(response) {
				if (response.success) {
					bustReload();
				} else {
					alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
				}
			},
			error: function() {
				alert('Error deleting list. Please try again.');
			}
		});
	});
});
</script>

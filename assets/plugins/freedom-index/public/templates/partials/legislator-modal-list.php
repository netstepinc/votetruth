<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
My List functionality
Add legislators to lists to create custom scorecards and compare legislators.
*/
$name = $args['display_name'];
$legislator_id = $args['id'] ?? 0;
$is_logged_in = is_user_logged_in();
$user_id = get_current_user_id();

// Get user's lists if logged in
$user_lists = [];
if ($is_logged_in && function_exists('fi_lists_get_by_user')) {
	$user_lists = fi_lists_get_by_user($user_id);
}

// Start Modal Content
ob_start();
?>

<?php if (!$is_logged_in): ?>
<div class="fi-lists-login-prompt text-center">
	<p class="mb-3">Create a free account to save legislators to lists and create custom scorecards.</p>
	<a href="<?php echo esc_url(home_url('/account/')); ?>" class="btn btn-primary">
		Create Free Account
	</a>
	<a href="<?php echo esc_url(wp_login_url(home_url('/account/')) . '?redirect_to=' . urlencode( (is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])); ?>" class="btn btn-outline-primary">Log in</a>
	<p class="small text-muted mb-0">Already have a Freedom Index/JBS account?</p>
</div>
<?php else: ?>
<div class="fi-lists-content">
	<p class="mb-3">Add legislators to lists to create custom scorecards and compare legislators.</p>
	
	<!-- Existing Lists -->
	<?php if (!empty($user_lists)): ?>
	<div class="mb-3">
		<!-- <label class="form-label small fw-bold">Your Lists</label> -->
		<div class="list-group list-group-flush">
			<?php foreach ($user_lists as $list): 
				$list_legislators = json_decode($list->legislators ?? '[]', true);
				$is_in_list = in_array($legislator_id, $list_legislators);
			?>
			<div class="list-group-item px-0 py-2 border-0">
				<div class="form-check">
					<input class="form-check-input fi-list-checkbox" type="checkbox" 
						data-list-id="<?php echo esc_attr($list->id); ?>"
						id="fiList<?php echo esc_attr($list->id); ?>"
						<?php checked($is_in_list); ?>>
					<label class="form-check-label" for="fiList<?php echo esc_attr($list->id); ?>">
						<?php echo esc_html($list->name); ?>
						<small class="text-muted d-block"><?php echo count($list_legislators); ?> legislator<?php echo count($list_legislators) !== 1 ? 's' : ''; ?></small>
					</label>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>
	
	<!-- Create New List -->
	<div class="border-top pt-3">
		<div class="d-flex align-items-center justify-content-between">
			<label class="form-label small fw-bold mb-2">Create New List</label>
			<a href="<?php echo esc_url(home_url('/account/lists/')); ?>" class="form-label small fw-bold mb-2 text-decoration-underline">Manage Lists</a>
		</div>
		<div class="input-group input-group-sm">
			<input type="text" class="form-control" id="fiNewListName" placeholder="List name...">
			<button class="btn btn-outline-primary" type="button" id="fiCreateListBtn">
				<i class="bi bi-plus"></i>
			</button>
		</div>
	</div>
</div>
<?php endif; ?>

<?php
$modal_body = ob_get_clean();

$args = [
	'id' => 'list',
	'button_text' => 'Add to My Lists',
	'button_icon' => 'bi bi-list-ul',
	'modal_title' => 'My Lists',
	'modal_body' => $modal_body,
	'legislator_id' => $legislator_id,
];
fi_legislator_modal($args);
?>

<script>
// Modal list: use legislator ID from the button that opened the modal; bind create form once so input is not replaced
(function () {
	'use strict';
	
	const modalSelector = '#listModal';
	
	function getCurrentLegislatorId() {
		var modal = document.querySelector(modalSelector);
		return modal ? parseInt(modal.getAttribute('data-current-legislator-id') || '0', 10) : 0;
	}
	
	function initListModal(modalEl) {
		if (!modalEl) return;
		
		<?php if ($is_logged_in): ?>
		bindListCheckboxes(modalEl);
		
		// Create new list: bind once so user's typed name is not wiped by clone/replace
		const createBtn = modalEl.querySelector('#fiCreateListBtn');
		const nameInput = modalEl.querySelector('#fiNewListName');
		if (createBtn && nameInput && !createBtn.getAttribute('data-fi-bound')) {
			createBtn.setAttribute('data-fi-bound', '1');
			createBtn.addEventListener('click', function() {
				var name = nameInput.value.trim();
				if (!name) { alert('Please enter a list name'); return; }
				createList(name, nameInput);
			});
			nameInput.addEventListener('keypress', function(e) {
				if (e.key === 'Enter') { createBtn.click(); }
			});
		}
		<?php endif; ?>
	}


	function bindListCheckboxes(modalEl) {
		modalEl.querySelectorAll('.fi-list-checkbox').forEach(checkbox => {
			const newCheckbox = checkbox.cloneNode(true);
			checkbox.parentNode.replaceChild(newCheckbox, checkbox);
			newCheckbox.addEventListener('change', function() {
				updateList(parseInt(this.getAttribute('data-list-id'), 10), this.checked);
			});
		});
	}

	function escapeHtml(str) {
		return String(str || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function ensureListContainer(modalEl) {
		let wrapper = modalEl.querySelector('.fi-lists-content .list-group.list-group-flush');
		if (wrapper) {
			return wrapper;
		}

		const content = modalEl.querySelector('.fi-lists-content');
		if (!content) {
			return null;
		}

		const listSection = document.createElement('div');
		listSection.className = 'mb-3';
		listSection.innerHTML = '<div class="list-group list-group-flush"></div>';
		const createSection = content.querySelector('.border-top.pt-3');
		if (createSection) {
			content.insertBefore(listSection, createSection);
		} else {
			content.appendChild(listSection);
		}

		return listSection.querySelector('.list-group.list-group-flush');
	}

	function prependCreatedList(modalEl, listId, listName, legislatorId) {
		const listGroup = ensureListContainer(modalEl);
		if (!listGroup || !listId) {
			return;
		}

		if (modalEl.querySelector('#fiList' + listId)) {
			return;
		}

		const safeName = escapeHtml(listName);
		const item = document.createElement('div');
		item.className = 'list-group-item px-0 py-2 border-0';
		item.innerHTML =
			'<div class="form-check">' +
				'<input class="form-check-input fi-list-checkbox" type="checkbox" data-list-id="' + String(listId) + '" id="fiList' + String(listId) + '" checked>' +
				'<label class="form-check-label" for="fiList' + String(listId) + '">' +
					safeName +
					'<small class="text-muted d-block">' + (legislatorId ? '1 legislator' : '0 legislators') + '</small>' +
				'</label>' +
			'</div>';

		listGroup.insertBefore(item, listGroup.firstChild);
		bindListCheckboxes(modalEl);
	}

	function updateList(listId, add) {
		var legislatorId = getCurrentLegislatorId();
		const formData = new FormData();
		formData.append('action', 'fi_update_list');
		formData.append('nonce', '<?php echo wp_create_nonce('fi_list_nonce'); ?>');
		formData.append('list_id', listId);
		formData.append('legislator_id', legislatorId);
		formData.append('add', add ? '1' : '0');
		
		fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
		.then(function(response) {
			if (response.status === 403) return { _nonceFail: true };
			return response.json();
		})
		.then(function(data) {
			if (data && data._nonceFail === true) {
				alert('Security check expired. Please refresh the page and try again.');
				return;
			}
			if (data && !data.success) {
				var msg = (data.data && typeof data.data.message === 'string') ? data.data.message : (typeof data.data === 'string' ? data.data : 'Failed to update list');
				alert('Error: ' + msg);
			}
		})
		.catch(function(error) {
			console.error('Error:', error);
			alert('An error occurred. Please try again.');
		});
	}
	
	function createList(name, nameInput) {
		const modalEl = document.querySelector(modalSelector);
		var legislatorId = getCurrentLegislatorId();
		const formData = new FormData();
		formData.append('action', 'fi_modal_create_list');
		formData.append('nonce', '<?php echo wp_create_nonce('fi_list_nonce'); ?>');
		formData.append('name', name);
		// JSON.stringify converts a JavaScript value (in this case, an array) to a JSON string.
		// If legislatorId = 10238, then [legislatorId] is [10238], and JSON.stringify([10238]) becomes "[10238]".
		formData.append('legislators', JSON.stringify(legislatorId ? [legislatorId] : [])); // "[10238]" is submitted if legislatorId==10238
		
		const ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
		fetch(ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
		.then(function(response) {
			if (response.status === 403) {
				return { _nonceFail: true };
			}
			return response.json();
		})
		.then(function(data) {
			if (data && data._nonceFail === true) {
				alert('Security check expired. Please refresh the page and try again.');
				return;
			}
			if (data && data.success) {
				if (nameInput) nameInput.value = '';
				const createdId = data.data && data.data.list_id ? parseInt(data.data.list_id, 10) : 0;
				prependCreatedList(modalEl, createdId, name, legislatorId);
				if (data.data && data.data.message) {
					console.log(data.data.message);
				}
			} else {
				var msg = (data && data.data && typeof data.data.message === 'string') ? data.data.message : 'Failed to create list';
				alert('Error: ' + msg);
			}
		})
		.catch(function(error) {
			console.error('Error:', error);
			alert('An error occurred. Please try again.');
		});
	}
	
	// When modal is shown: set legislator ID from the button that opened it, then init
	const modalEl = document.querySelector(modalSelector);
	if (modalEl) {
		modalEl.addEventListener('shown.bs.modal', function(e) {
			var trigger = e.relatedTarget;
			if (trigger && trigger.getAttribute('data-legislator-id') != null) {
				e.target.setAttribute('data-current-legislator-id', trigger.getAttribute('data-legislator-id'));
			}
			initListModal(e.target);
		});
		if (modalEl.classList.contains('show')) {
			initListModal(modalEl);
		}
	}
})();
</script>

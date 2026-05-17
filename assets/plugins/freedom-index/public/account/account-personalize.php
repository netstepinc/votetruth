<?php
if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
	wp_safe_redirect(home_url('/account/'));
	exit;
}

$user = wp_get_current_user();
$user_id = $user->ID;
$pdf_contacts = fi_pdf_contacts_get($user_id);
$default_contact_index = fi_pdf_contacts_default_index_get($user_id);
$edit_index = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$editing_contact = ($edit_index !== null) ? fi_pdf_contacts_get_by_index($user_id, $edit_index) : null;
?>
<div class="row">
	<?php fi_get_template('partials/account-nav', ['current_page' => 'personalize']); ?>
	<div class="col-12 col-md-9">
		<div class="row">
			<div class="col-12 col-md-7">
				<div class="card rounded-4 shadow mb-4">
					<div class="card-header rounded-4 rounded-bottom-0">
						<h4 class="card-title mb-0">PDF Contact Info</h4>
					</div>
					<div class="card-body">
						<p>You may save personal or organization/chapter contacts below for use when printing scorecard PDFs.</p>
						<p class="mb-0">You can add multiple contact entries and select up to four when you print PDFs.</p>
					</div>
				</div>
				<div id="fiAccountContactsCard" class="card mb-4 rounded-4 shadow">
					<div class="card-header rounded-4 rounded-bottom-0">
						<h4 class="card-title mb-0">Saved Contacts</h4>
					</div>
					<div class="card-body" id="fiAccountContactsListContainer">
						<?php if (!empty($pdf_contacts)): ?>
						<div class="list-group list-group-flush">
							<?php foreach ($pdf_contacts as $index => $contact): ?>
								<div class="list-group-item d-flex justify-content-between align-items-start">
									<div class="flex-grow-1">
										<div class="fw-bold mb-1">
											<?php echo esc_html($contact['name'] ?? 'Unnamed'); ?>
											<?php if ($default_contact_index !== null && (int) $default_contact_index === (int) $index): ?>
												<span class="badge text-bg-primary ms-2">Default</span>
											<?php endif; ?>
										</div>
										<?php if (!empty($contact['phone'])): ?>
											<div class="small text-muted mb-1">
												<i class="bi bi-telephone me-1"></i><?php echo esc_html($contact['phone']); ?>
											</div>
										<?php endif; ?>
										<?php if (!empty($contact['email'])): ?>
											<div class="small text-muted">
												<i class="bi bi-envelope me-1"></i><?php echo esc_html($contact['email']); ?>
											</div>
										<?php endif; ?>
									</div>
									<div class="ms-3">
										<button type="button" class="btn btn-sm btn-outline-primary me-1 fi-account-set-default" data-index="<?php echo esc_attr($index); ?>" title="Set Default">
											<i class="bi bi-star"></i>
										</button>
										<a href="<?php echo esc_url(home_url('/account/personalize/?edit=' . $index)); ?>"
											class="btn btn-sm btn-outline-secondary me-1 fi-account-edit-link" data-index="<?php echo esc_attr($index); ?>" title="Edit">
											<i class="bi bi-pencil"></i>
										</a>
										<button type="button" class="btn btn-sm btn-outline-danger fi-delete-pdf-contact" data-index="<?php echo esc_attr($index); ?>" title="Delete">
											<i class="bi bi-trash"></i>
										</button>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
						<?php else: ?>
						<p class="text-muted mb-0 small">No saved contacts yet. Add one below.</p>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="col-12 col-md-5">
				<div class="card mb-4 rounded-4 shadow">
					<div class="card-header rounded-4 rounded-bottom-0">
						<h4 class="card-title mb-0"><?php echo $editing_contact ? 'Edit Contact' : 'Add Contact'; ?></h4>
					</div>
					<div class="card-body">
						<?php
						$privacy_notice = '<div class="alert alert-light mb-3 p-2">
							<small class="text-danger">
								<strong>Privacy:</strong> ' . FI_PRIVACY_PROMISE . '
							</small>
						</div>';
						$form_html = fi_get_personalize_form_html([
							'name_value' => $editing_contact['name'] ?? '',
							'phone_value' => $editing_contact['phone'] ?? '',
							'email_value' => $editing_contact['email'] ?? '',
							'show_edit_index' => false,
							'show_cancel' => ($editing_contact !== null),
							'submit_text' => $editing_contact ? 'Update Contact' : 'Add Contact',
							'submit_text_id' => '',
							'label_class' => 'form-label',
							'submit_class' => 'btn btn-primary',
							'form_id' => 'fi-pdf-contact-form',
							'form_action' => admin_url('admin-post.php'),
							'form_method' => 'post',
							'name_id' => 'contact_name',
							'name_name' => 'contact_name',
							'phone_id' => 'contact_phone',
							'phone_name' => 'contact_phone',
							'email_id' => 'contact_email',
							'email_name' => 'contact_email',
							'privacy_notice' => $privacy_notice,
							'cancel_url' => $editing_contact !== null ? home_url('/account/personalize/') : '',
						]);
						$nonce_fields = wp_nonce_field('fi_save_pdf_contact', 'fi_pdf_contact_nonce', true, false);
						$nonce_fields .= '<input type="hidden" name="action" value="fi_save_pdf_contact">';
						if ($editing_contact !== null) {
							$nonce_fields .= '<input type="hidden" name="edit_index" value="' . esc_attr($edit_index) . '">';
						}
						$form_html = preg_replace(
							'/(<form[^>]*>)/',
							'$1' . $nonce_fields,
							$form_html,
							1
						);
						echo $form_html;
						?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
(function() {
	window.fiAccountPdfContacts = {
		ajaxUrl: <?php echo json_encode(admin_url('admin-ajax.php')); ?>,
		saveNonce: <?php echo json_encode(wp_create_nonce('fi_save_pdf_contact')); ?>,
		deleteNonce: <?php echo json_encode(wp_create_nonce('fi_delete_pdf_contact')); ?>,
		getContactNonce: <?php echo json_encode(wp_create_nonce('fi_get_pdf_contact')); ?>,
		setDefaultNonce: <?php echo json_encode(wp_create_nonce('fi_set_default_pdf_contact')); ?>,
		personalizeUrl: <?php echo json_encode(home_url('/account/personalize/')); ?>,
		defaultIndex: <?php echo $default_contact_index === null ? 'null' : (int) $default_contact_index; ?>
	};
	var cfg = window.fiAccountPdfContacts;
	var container = document.getElementById('fiAccountContactsListContainer');
	var formEl = document.getElementById('fi-pdf-contact-form');
	var formCardTitle = formEl && formEl.closest('.card') ? formEl.closest('.card').querySelector('.card-title') : null;

	function esc(s) {
		if (s == null) return '';
		var d = document.createElement('div');
		d.textContent = s;
		return d.innerHTML;
	}

	function renderAccountContactsList(contacts, defaultIndex) {
		if (!container) return;
		if (!contacts || contacts.length === 0) {
			container.innerHTML = '<p class="text-muted mb-0 small">No saved contacts yet. Add one below.</p>';
			return;
		}
		var personalizeUrl = (cfg.personalizeUrl || '').replace(/\/$/, '');
		var html = '<div class="list-group list-group-flush">';
		for (var i = 0; i < contacts.length; i++) {
			var c = contacts[i];
			var name = esc(c.name || 'Unnamed');
			var phone = (c.phone && c.phone.trim()) ? '<div class="small text-muted mb-1"><i class="bi bi-telephone me-1"></i>' + esc(c.phone) + '</div>' : '';
			var email = (c.email && c.email.trim()) ? '<div class="small text-muted"><i class="bi bi-envelope me-1"></i>' + esc(c.email) + '</div>' : '';
			var defaultBadge = (defaultIndex !== null && defaultIndex !== undefined && defaultIndex === i) ? ' <span class="badge text-bg-primary ms-2">Default</span>' : '';
			html += '<div class="list-group-item d-flex justify-content-between align-items-start">';
			html += '<div class="flex-grow-1"><div class="fw-bold mb-1">' + name + defaultBadge + '</div>' + phone + email + '</div>';
			html += '<div class="ms-3">';
			html += '<button type="button" class="btn btn-sm btn-outline-primary me-1 fi-account-set-default" data-index="' + i + '" title="Set Default"><i class="bi bi-star"></i></button>';
			html += '<a href="' + personalizeUrl + (personalizeUrl.indexOf('?') >= 0 ? '&' : '?') + 'edit=' + i + '" class="btn btn-sm btn-outline-secondary me-1 fi-account-edit-link" data-index="' + i + '" title="Edit"><i class="bi bi-pencil"></i></a>';
			html += '<button type="button" class="btn btn-sm btn-outline-danger fi-delete-pdf-contact" data-index="' + i + '" title="Delete"><i class="bi bi-trash"></i></button>';
			html += '</div></div>';
		}
		html += '</div>';
		container.innerHTML = html;
	}

	window.fiAccountDeleteContact = function(btn) {
		var i = parseInt(btn.getAttribute('data-index'), 10);
		if (isNaN(i)) return;
		var nonce = cfg.deleteNonce || (window.fiPdfContacts && window.fiPdfContacts.nonce);
		if (!nonce || !window.fiDeletePdfContact) return;
		window.fiDeletePdfContact(i, nonce, function(data) {
			if (data && data.data && Array.isArray(data.data.contacts)) renderAccountContactsList(data.data.contacts, data.data.default_index);
		}, function() {});
	};

	window.fiAccountSetDefault = function(index) {
		var nonce = cfg.setDefaultNonce;
		if (!nonce || !cfg.ajaxUrl) return;
		fetch(cfg.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({ action: 'fi_set_default_pdf_contact', default_index: index, nonce: nonce })
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			if (data.success && data.data && Array.isArray(data.data.contacts)) renderAccountContactsList(data.data.contacts, data.data.default_index);
		});
	};

	function switchToEditMode(contact, index) {
		if (!formEl) return;
		var nameEl = formEl.querySelector('[name="contact_name"]');
		var phoneEl = formEl.querySelector('[name="contact_phone"]');
		var emailEl = formEl.querySelector('[name="contact_email"]');
		if (nameEl) nameEl.value = contact.name || '';
		if (phoneEl) phoneEl.value = contact.phone || '';
		if (emailEl) emailEl.value = contact.email || '';
		var editInput = formEl.querySelector('[name="edit_index"]');
		if (!editInput) {
			editInput = document.createElement('input');
			editInput.type = 'hidden';
			editInput.name = 'edit_index';
			formEl.appendChild(editInput);
		}
		editInput.value = String(index);
		if (formCardTitle) formCardTitle.textContent = 'Edit Contact';
		var submitBtn = formEl.querySelector('button[type="submit"]');
		if (submitBtn) submitBtn.textContent = 'Update Contact';
		if (cfg.personalizeUrl && window.history && window.history.replaceState) {
			var editUrl = cfg.personalizeUrl.replace(/\/$/, '') + (cfg.personalizeUrl.indexOf('?') >= 0 ? '&' : '?') + 'edit=' + index;
			window.history.replaceState({}, '', editUrl);
		}
	}

	if (container) {
		container.addEventListener('click', function(e) {
			var editLink = e.target.closest('.fi-account-edit-link');
			if (editLink) {
				e.preventDefault();
				var idx = parseInt(editLink.getAttribute('data-index'), 10);
				if (isNaN(idx) || !cfg.getContactNonce || !cfg.ajaxUrl) return;
				fetch(cfg.ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({ action: 'fi_get_pdf_contact', index: idx, nonce: cfg.getContactNonce })
				})
				.then(function(r) { return r.json(); })
				.then(function(data) {
					if (data.success && data.data && data.data.contact) switchToEditMode(data.data.contact, data.data.index);
				});
				return;
			}
			var del = e.target.closest('.fi-delete-pdf-contact');
			if (del) { e.preventDefault(); window.fiAccountDeleteContact(del); return; }
			var setDef = e.target.closest('.fi-account-set-default');
			if (setDef) { e.preventDefault(); var i = parseInt(setDef.getAttribute('data-index'), 10); if (!isNaN(i)) window.fiAccountSetDefault(i); }
		});
	}

	if (formEl) {
		formEl.addEventListener('submit', function(e) {
			e.preventDefault();
			if (!formEl.checkValidity()) { formEl.classList.add('was-validated'); return; }
			var name = (formEl.querySelector('[name="contact_name"]') || {}).value || '';
			var phone = (formEl.querySelector('[name="contact_phone"]') || {}).value || '';
			var email = (formEl.querySelector('[name="contact_email"]') || {}).value || '';
			var editInput = formEl.querySelector('[name="edit_index"]');
			var editIndex = (editInput && editInput.value !== '') ? editInput.value : '';
			var submitBtn = formEl.querySelector('button[type="submit"]');
			if (submitBtn) submitBtn.disabled = true;
			fetch(cfg.ajaxUrl || '', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({
					action: 'fi_save_pdf_contact',
					name: name,
					phone: phone,
					email: email,
					edit_index: editIndex,
					nonce: cfg.saveNonce || ''
				})
			})
			.then(function(r) { return r.json(); })
			.then(function(data) {
				if (submitBtn) submitBtn.disabled = false;
				if (data.success && data.data && Array.isArray(data.data.contacts)) {
					renderAccountContactsList(data.data.contacts, data.data.default_index);
					formEl.reset();
					formEl.classList.remove('was-validated');
					if (editInput) editInput.remove();
					if (formCardTitle) formCardTitle.textContent = 'Add Contact';
					if (window.history && window.history.replaceState && cfg.personalizeUrl) window.history.replaceState({}, '', cfg.personalizeUrl);
				} else {
					alert(data.data && data.data.message ? data.data.message : 'Error saving contact.');
				}
			})
			.catch(function() {
				if (submitBtn) submitBtn.disabled = false;
				alert('Error saving contact. Please try again.');
			});
		});
	}
})();
</script>
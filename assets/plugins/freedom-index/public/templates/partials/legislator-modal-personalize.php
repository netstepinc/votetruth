<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
Features
	For logged-in users
	Displays saved personalization from user meta
	Shows name, phone, and email (or "Not set")
	Link to account settings (profile.php) to edit
	Privacy note about data usage
For logged-out users
	Form with:
		Name/Organization (required)
		Phone (optional)
		Email (optional)
Saves to:
	localStorage (for client-side access)
	Cookies (for server-side access, 1-year expiration)
Account creation encouragement:
	"Create Free Account" button
	"Log In" button
	Message about permanent saving
Privacy notice about local storage
Technical details
	Follows the same structure as other legislator modals
	Uses Bootstrap 5.3 styling and icons
	HTML5 form validation
	JavaScript handles localStorage and cookie saving
	Data loads automatically when the modal opens
	Success feedback on save
The modal is integrated into the legislator page template and appears alongside the other modals (Share, Contact, List).
When PDF generation is implemented, the saved personalization data (from user meta for logged-in users, or cookies/localStorage for guests) can be used to customize the PDFs.
*/

$name = $args['display_name'] ?? '';
$url = $args['url'] ?? '';
$legislator_id = $args['id'] ?? null;
$sessions = $args['sessions'] ?? [];
$is_logged_in = is_user_logged_in();
$current_user = $is_logged_in ? wp_get_current_user() : null;

// Get saved personalization data
$saved_name = '';
$saved_phone = '';
$saved_email = '';

if ($is_logged_in && $current_user) {
	// Get from user meta using centralized function
	$personalize_data = fi_user_personalize_get($current_user->ID);
	$saved_name = $personalize_data['name'];
	$saved_phone = $personalize_data['phone'];
	$saved_email = $personalize_data['email'];
} else {
	// Get from cookie/localStorage (will be handled by JavaScript)
	$saved_name = $_COOKIE['fi_personalize_name'] ?? '';
	$saved_phone = $_COOKIE['fi_personalize_phone'] ?? '';
	$saved_email = $_COOKIE['fi_personalize_email'] ?? '';
}

// Account settings URL
$account_url = $is_logged_in ? admin_url('profile.php') : wp_registration_url();

// Start Modal Content
ob_start();
?>

<?php 
// Get user's PDF contacts if logged in
$pdf_contacts = [];
$selected_contact_index = null;
if ($is_logged_in):
	$pdf_contacts = fi_pdf_contacts_get($current_user->ID);
	$selected_contact_index = fi_pdf_contacts_default_index_get($current_user->ID);
?>
<div class="row">
	<div class="col-12 col-lg-6">

		<!-- Logged In: Saved contacts list (wrapper always present for AJAX list updates) -->
		<div id="fiPdfContactsListWrapper" class="mb-3" data-contacts="<?php echo esc_attr(wp_json_encode($pdf_contacts)); ?>">
			<?php if (!empty($pdf_contacts)): ?>
				<label class="form-label fw-bold">Saved Contacts</label>
				<div class="list-group list-group-flush">
					<?php foreach ($pdf_contacts as $index => $contact): ?>
						<div class="list-group-item d-flex justify-content-between align-items-start px-0">
							<div class="flex-grow-1">
								<div class="fw-bold"><?php echo esc_html($contact['name'] ?? 'Unnamed'); ?></div>
								<div class="small text-muted">
								<?php if (!empty($contact['phone'])): ?>
									<div class="text-small text-muted"><?= esc_html($contact['phone']); ?></div>
								<?php endif; ?>
								<?php if (!empty($contact['email'])): ?>
									<div class="text-small text-muted"><?= esc_html($contact['email']); ?></div>
								<?php endif; ?>
								</div>
							</div>
							<div class="ms-2">
								<button type="button" 
										class="btn btn-sm btn-outline-secondary fi-edit-contact me-1" 
										data-index="<?php echo esc_attr($index); ?>" 
										title="Edit">
									<i class="bi bi-pencil"></i>
								</button>
								<button type="button" 
										class="btn btn-sm btn-outline-danger fi-delete-contact" 
										data-index="<?php echo esc_attr($index); ?>" 
										title="Delete"
										onclick="window.fiModalDeleteContact&amp;&amp;window.fiModalDeleteContact(this);return false;">
									<i class="bi bi-trash"></i>
								</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<div class="col-12 col-lg-6">
		<!-- Add/Edit Form -->
		<div class="mb-3">
			<label id="fiAddContactLabel" class="form-label fw-bold"><?php echo !empty($pdf_contacts) ? 'Add New Contact' : 'Add Contact'; ?></label>
			<div id="fiAddContactForm">
				<div class="card border-success rounded-3 p-2 shadow">
			<?php
			// Generate form HTML
			$form_html = fi_get_personalize_form_html([
				'name_value' => '',
				'phone_value' => '',
				'email_value' => '',
				'show_edit_index' => true,
				'show_cancel' => true,
				'submit_text' => 'Save Contact',
				'submit_text_id' => 'fiPersonalizeSubmitText',
				'label_class' => 'form-label mb-0'
			]);
			echo $form_html;
			?>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="alert alert-danger bg-white mt-3 mb-3 p-2 text-danger text-center small shadow">
	<?php echo FI_PRIVACY_PROMISE; ?>
</div>

<?php else: ?>

<div class="row">
	<div class="col-12 col-lg-6">

		<!-- Instructions and Account Invitation -->
		<p>Add your contact information below to personalize printed scorecards. Your name, phone, and email will appear on legislator report PDFs.</p>

		<div class="border border-2 border-success rounded-4 p-3 mb-3">
			<h4 class="mb-1">Save Your Contact Information for Next Time</h4>
			<p class="mb-2 small">You may create a free account or log in to your existing account to save your contact information for future use.</p>
			<div class="row g-2">
				<div class="col-12 col-lg-8">
					<a href="<?php echo esc_url(wp_registration_url()); ?>" class="btn btn-outline-primary shadow btn-sm w-100">
						<i class="bi bi-person-plus"></i> Create Free Account
					</a>
				</div>
				<div class="col-12 col-lg-4">
					<?php
					// Use custom account login page with redirect back to current page
					$redirect_url = urlencode(get_permalink());
					$custom_login_url = home_url('/account/?redirect_to=' . $redirect_url);
					?>
					<a href="<?php echo esc_url($custom_login_url); ?>" class="btn btn-outline-secondary shadow btn-sm w-100">
						<i class="bi bi-box-arrow-in-right"></i> Log In
					</a>
				</div>
			</div>
		</div>
		
	</div>
	<div class="col-12 col-lg-6">

		<!-- Guest: single contact card (shown after save; hide form) -->
		<div id="fi-guest-contact-card" class="mb-3 d-none">
			<label class="form-label fw-bold">Saved Contact</label>
			<div class="d-flex justify-content-between align-items-start px-0">
				<div class="flex-grow-1">
					<div class="fw-bold" id="fi-guest-card-name"></div>
					<div class="small text-muted" id="fi-guest-card-phone"></div>
					<div class="small text-muted" id="fi-guest-card-email"></div>
				</div>
				<div class="ms-2">
					<button type="button" class="btn btn-sm btn-outline-secondary fi-guest-edit-contact me-1" title="Edit"><i class="bi bi-pencil"></i></button>
					<button type="button" class="btn btn-sm btn-outline-danger fi-guest-delete-contact" title="Delete"><i class="bi bi-trash"></i></button>
				</div>
			</div>
		</div>

		<!-- Guest: form (shown when no contact or when editing) -->
		<div id="fi-guest-personalize-form-wrap" class="card border-success rounded-3 p-2 shadow">
		<?php
		$form_html = fi_get_personalize_form_html([
			'name_value' => $saved_name,
			'phone_value' => $saved_phone,
			'email_value' => $saved_email,
			'show_edit_index' => false,
			'show_cancel' => false,
			'submit_text' => 'Save Personalization',
			'submit_text_id' => 'fiGuestSubmitText',
			'label_class' => 'form-label fw-bold',
			'submit_class' => 'btn btn-primary w-100 fw-bold'
		]);
		echo $form_html;
		?>
		</div>
	</div>
</div>

<!-- Privacy Notice -->
<div class="alert alert-danger bg-white mt-3 mb-3 shadow">
	<small class="text-danger">
		<strong>Privacy:</strong> Your information is saved locally in your browser and is only used to customize PDF scorecards. 
		We do not collect or share this information with third parties.
	</small>
</div>
<?php endif; ?>

<button type="button" class="btn btn-success fw-bold shadow fs-6 w-100 btn-sm mb-3" data-bs-toggle="modal" data-bs-target="#printModal" data-bs-dismiss="modal">Print Scorecards</button>

<?php
$modal_body = ob_get_clean();

$args = [
	'id' => 'personalize',
	'button_text' => 'Personalize PDFs',
	'button_icon' => 'bi bi-printer',
	'button_class' => 'btn btn-sm btn-outline-primary shadow-sm col-12 fw-bold mb-2 fs-7',
	'modal_title' => 'Personalize Your Printed Scorecards',
	'modal_body' => $modal_body,
	'modal_size' => 'modal-lg',
];
fi_legislator_modal($args);
?>

<script>
(function() {
	const modalSelector = '#personalizeModal';
	const formId = 'fiPersonalizeForm';
	
	// Guest: load card vs form state from fi_guest_pdf_contacts (called on modal show)
	
	<?php if ($is_logged_in): ?>
	// Edit contact: use live contacts from wrapper (updated after AJAX save) or initial PHP list
	function getPdfContacts() {
		const w = document.getElementById('fiPdfContactsListWrapper');
		if (w && w.dataset.contacts) {
			try { return JSON.parse(w.dataset.contacts); } catch (e) {}
		}
		return <?php echo json_encode($pdf_contacts); ?>;
	}
	function esc(s) { if (s == null) return ''; const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
	function updatePdfContactsList(contacts) {
		const wrapper = document.getElementById('fiPdfContactsListWrapper');
		if (!wrapper) return;
		wrapper.dataset.contacts = JSON.stringify(contacts);
		if (contacts.length === 0) {
			wrapper.innerHTML = '';
		} else {
			const items = contacts.map(function(c, i) {
				const name = esc(c.name || 'Unnamed');
				const phone = (c.phone && c.phone.trim()) ? '<div class="small text-muted">' + esc(c.phone) + '</div>' : '';
				const email = (c.email && c.email.trim()) ? '<div class="small text-muted">' + esc(c.email) + '</div>' : '';
				return '<div class="list-group-item d-flex justify-content-between align-items-start px-0">' +
					'<div class="flex-grow-1"><div class="fw-bold">' + name + '</div>' + phone + email + '</div>' +
					'<div class="ms-2">' +
					'<button type="button" class="btn btn-sm btn-outline-secondary fi-edit-contact me-1" data-index="' + i + '" title="Edit"><i class="bi bi-pencil"></i></button>' +
					'<button type="button" class="btn btn-sm btn-outline-danger fi-delete-contact" data-index="' + i + '" title="Delete" onclick="window.fiModalDeleteContact&amp;&amp;window.fiModalDeleteContact(this);return false;"><i class="bi bi-trash"></i></button>' +
					'</div></div>';
			}).join('');
			wrapper.innerHTML = '<label class="form-label fw-bold">Saved Contacts</label><div class="list-group list-group-flush">' + items + '</div>';
		}
		const addLabel = document.getElementById('fiAddContactLabel');
		if (addLabel) addLabel.textContent = contacts.length > 0 ? 'Add New Contact' : 'Add Contact';
		// Notify Print modal so it can refresh its contact list
		document.dispatchEvent(new CustomEvent('fi:pdf-contacts-changed', { detail: { contacts: contacts }, bubbles: true }));
	}
	window.fiModalDeleteContact = function(btn) {
		const i = parseInt(btn.getAttribute('data-index'), 10);
		if (isNaN(i)) return;
		const nonce = window.fiPdfContacts && window.fiPdfContacts.nonce;
		if (!nonce) return;
		window.fiDeletePdfContact(i, nonce, function(data) {
			if (data && data.data && Array.isArray(data.data.contacts)) updatePdfContactsList(data.data.contacts);
		}, function() {});
	};
	document.addEventListener('click', function(e) {
		if (e.target.closest('.fi-edit-contact')) {
			const btn = e.target.closest('.fi-edit-contact');
			const index = parseInt(btn.dataset.index);
			const contacts = getPdfContacts();
			const contact = contacts[index];
			
			if (contact) {
				document.getElementById('fiPersonalizeName').value = contact.name || '';
				document.getElementById('fiPersonalizePhone').value = contact.phone || '';
				document.getElementById('fiPersonalizeEmail').value = contact.email || '';
				document.getElementById('fiEditContactIndex').value = index;
				document.getElementById('fiPersonalizeSubmitText').textContent = 'Update Contact';
				document.getElementById('fiCancelEdit').style.display = '';
				document.getElementById('fiPersonalizeName').focus();
			}
		}
	});
	
	// Cancel edit
	const cancelBtn = document.getElementById('fiCancelEdit');
	if (cancelBtn) {
		cancelBtn.addEventListener('click', function() {
			document.getElementById('fiPersonalizeForm').reset();
			document.getElementById('fiEditContactIndex').value = '';
			document.getElementById('fiPersonalizeSubmitText').textContent = 'Save Contact';
			this.style.display = 'none';
		});
	}
	<?php endif; ?>
	
	// Guest: single contact storage key (array of one)
	const guestContactsKey = 'fi_guest_pdf_contacts';
	const guestCookieDays = 14;

	function setGuestCookie(arr) {
		const expires = new Date();
		expires.setDate(expires.getDate() + guestCookieDays);
		let exp = '; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';
		if (location.protocol === 'https:') exp += '; Secure';
		document.cookie = guestContactsKey + '=' + encodeURIComponent(JSON.stringify(arr)) + exp;
	}

	function getGuestContacts() {
		try {
			const raw = localStorage.getItem(guestContactsKey);
			if (raw) {
				const p = JSON.parse(raw);
				return Array.isArray(p) ? p : [];
			}
		} catch (e) {}
		return [];
	}

	function saveGuestContact(name, phone, email) {
		const arr = [{ name: (name || '').trim(), phone: (phone || '').trim(), email: (email || '').trim() }];
		try {
			localStorage.setItem(guestContactsKey, JSON.stringify(arr));
		} catch (e) {
			console.error('Error saving guest contact:', e);
		}
		setGuestCookie(arr);
	}

	function clearGuestContact() {
		try {
			localStorage.removeItem(guestContactsKey);
		} catch (e) {}
		setGuestCookie([]);
	}

	function showGuestCardHideForm(contact) {
		const card = document.getElementById('fi-guest-contact-card');
		const formWrap = document.getElementById('fi-guest-personalize-form-wrap');
		if (card && formWrap) {
			document.getElementById('fi-guest-card-name').textContent = (contact && contact.name) ? contact.name : 'Unnamed';
			document.getElementById('fi-guest-card-phone').textContent = (contact && contact.phone) ? contact.phone : '';
			document.getElementById('fi-guest-card-email').textContent = (contact && contact.email) ? contact.email : '';
			card.classList.remove('d-none');
			formWrap.classList.add('d-none');
		}
	}

	function showGuestFormHideCard() {
		const card = document.getElementById('fi-guest-contact-card');
		const formWrap = document.getElementById('fi-guest-personalize-form-wrap');
		if (card && formWrap) {
			card.classList.add('d-none');
			formWrap.classList.remove('d-none');
		}
	}

	function loadGuestState() {
		const contacts = getGuestContacts();
		if (contacts.length >= 1) {
			showGuestCardHideForm(contacts[0]);
		} else {
			showGuestFormHideCard();
		}
	}
	
	const modalEl = document.querySelector(modalSelector);
	<?php if (!$is_logged_in): ?>
	// Guest: Edit/Delete contact (delegated)
	if (modalEl) {
		modalEl.addEventListener('click', function(e) {
			if (e.target.closest('.fi-guest-delete-contact')) {
				e.preventDefault();
				clearGuestContact();
// Contact is deleted, but the form is populated with the last contact so it's not obvious the contact was deleted. Show empty form instead of the last contact.
				document.getElementById('fiPersonalizeName').value = '';
				document.getElementById('fiPersonalizePhone').value = '';
				document.getElementById('fiPersonalizeEmail').value = '';
				document.dispatchEvent(new CustomEvent('fi:guest-contacts-changed', { bubbles: true }));
				showGuestFormHideCard();
				const btn = modalEl.querySelector('#fi-guest-personalize-form-wrap button[type="submit"]');
				if (btn) btn.textContent = 'Save Personalization';
			}
			if (e.target.closest('.fi-guest-edit-contact')) {
				e.preventDefault();
				const contacts = getGuestContacts();
				if (contacts.length < 1) return;
				const c = contacts[0];
				const form = modalEl.querySelector('#' + formId);
				if (form) {
					form.querySelector('#fiPersonalizeName').value = c.name || '';
					form.querySelector('#fiPersonalizePhone').value = c.phone || '';
					form.querySelector('#fiPersonalizeEmail').value = c.email || '';
				}
				const submitBtn = modalEl.querySelector('#fi-guest-personalize-form-wrap button[type="submit"]');
				if (submitBtn) submitBtn.textContent = 'Update';
				showGuestFormHideCard();
			}
		});
	}
	<?php endif; ?>
	
	// Single form submit handler (bind once to avoid duplicate requests / double dispatch)
	const form = modalEl ? modalEl.querySelector('#' + formId) : null;
	if (form) {
		form.addEventListener('submit', function(e) {
			e.preventDefault();
			if (!form.checkValidity()) {
				form.classList.add('was-validated');
				return;
			}
			const name = form.querySelector('#fiPersonalizeName').value.trim();
			const phone = form.querySelector('#fiPersonalizePhone').value.trim();
			const email = form.querySelector('#fiPersonalizeEmail').value.trim();
			<?php if ($is_logged_in): ?>
			const editIndex = document.getElementById('fiEditContactIndex');
			const editIndexVal = (editIndex && editIndex.value) ? editIndex.value : '';
			const submitBtn = form.querySelector('button[type="submit"]');
			if (submitBtn) submitBtn.disabled = true;
			fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({
					action: 'fi_save_pdf_contact',
					name: name,
					phone: phone,
					email: email,
					edit_index: editIndexVal,
					nonce: '<?php echo wp_create_nonce('fi_save_pdf_contact'); ?>'
				})
			})
			.then(function(response) { return response.json(); })
			.then(function(data) {
				if (data.success) {
					const contacts = Array.isArray(data.data && data.data.contacts) ? data.data.contacts : [];
					updatePdfContactsList(contacts);
					form.reset();
					form.classList.remove('was-validated');
					if (editIndex) editIndex.value = '';
					var st = document.getElementById('fiPersonalizeSubmitText');
					if (st) st.textContent = 'Save Contact';
					var cancelEl = document.getElementById('fiCancelEdit');
					if (cancelEl) cancelEl.style.display = 'none';
					if (submitBtn) {
						var origHtml = submitBtn.innerHTML;
						submitBtn.innerHTML = '<i class="bi bi-check"></i> Saved!';
						submitBtn.classList.remove('btn-primary');
						submitBtn.classList.add('btn-success');
						setTimeout(function() {
							submitBtn.innerHTML = origHtml;
							submitBtn.classList.remove('btn-success');
							submitBtn.classList.add('btn-primary');
							submitBtn.disabled = false;
						}, 2000);
					}
				} else {
					if (submitBtn) submitBtn.disabled = false;
					alert('Error saving contact: ' + (data.data && data.data.message ? data.data.message : 'Unknown error'));
				}
			})
			.catch(function() {
				if (submitBtn) submitBtn.disabled = false;
				alert('Error saving contact. Please try again.');
			});
			<?php else: ?>
			saveGuestContact(name, phone, email);
			document.dispatchEvent(new CustomEvent('fi:guest-contacts-changed', { bubbles: true }));
			var contact = { name: name, phone: phone, email: email };
			var submitBtn = form.querySelector('button[type="submit"]');
			if (submitBtn) {
				submitBtn.innerHTML = '<i class="bi bi-check"></i> Saved!';
				submitBtn.classList.remove('btn-primary');
				submitBtn.classList.add('btn-success');
			}
			showGuestCardHideForm(contact);
			setTimeout(function() {
				if (submitBtn) {
					submitBtn.textContent = 'Save Personalization';
					submitBtn.classList.remove('btn-success');
					submitBtn.classList.add('btn-primary');
				}
			}, 1500);
			<?php endif; ?>
		});
	}

	if (modalEl) {
		modalEl.addEventListener('shown.bs.modal', function() {
			<?php if (!$is_logged_in): ?>
			loadGuestState();
			<?php endif; ?>
		});
	}
	
})();
</script>
<?php
/*
Features
	For logged-in users
	Displays saved personalization from user meta
	Shows name, phone, and email (or "Not set")
	Link to account settings (profile.php) to edit
	Privacy note about data usage
For logged-out users
	Form with:
		Name/Organization (required)
		Phone (optional)
		Email (optional)
Saves to:
	localStorage (for client-side access)
	Cookies (for server-side access, 1-year expiration)
Account creation encouragement:
	"Create Free Account" button
	"Log In" button
	Message about permanent saving
Privacy notice about local storage
Technical details
	Follows the same structure as other legislator modals
	Uses Bootstrap 5.3 styling and icons
	HTML5 form validation
	JavaScript handles localStorage and cookie saving
	Data loads automatically when the modal opens
	Success feedback on save
The modal is integrated into the legislator page template and appears alongside the other modals (Share, Contact, List).
When PDF generation is implemented, the saved personalization data (from user meta for logged-in users, or cookies/localStorage for guests) can be used to customize the PDFs.
*/
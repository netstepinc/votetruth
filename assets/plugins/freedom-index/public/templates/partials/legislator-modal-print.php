<?php if (!defined('ABSPATH')) exit;

$legislator_id = $args['id'] ?? null;
$scorecard = $args['latest_scorecard'] ?? null; //this is an array

$report_gov = $args['report_gov'] ?? ($args['gov'] ?? '');
$is_logged_in = is_user_logged_in();
$current_user = $is_logged_in ? wp_get_current_user() : null;

// Single modal definition; keep all print UI here.
$report_base_url = '';
if ($legislator_id && $scorecard) {
	$report_base_url = home_url('/legislator/' . $legislator_id . '/session/' . $scorecard['session_id'] . '/report/' . $scorecard['id'] . '/');
}

$pdf_contacts = [];
$selected_contact_index = null;
if ($is_logged_in && $current_user) {
	$pdf_contacts = fi_pdf_contacts_get($current_user->ID);
	$selected_contact_index = fi_pdf_contacts_default_index_get($current_user->ID);
} else {
	// Guest: use cookie-backed list so server can resolve contacts= when PDF is requested
	$pdf_contacts = fi_pdf_contacts_guest_get();
	$selected_contact_index = !empty($pdf_contacts) ? 0 : null; // optional: pre-check first guest contact
}

//Does this report have a pdf url?
$has_pdf_url = false;
$show_personalization = true;
/*
if($scorecard['format'] == 'freedomindex'){
	$show_personalization = false;
	if(isset($scorecard['payload']['report_pdf_url']) && !empty($scorecard['payload']['report_pdf_url'])){
		$report_buttons = [
			['label' => 'Freedom Index', 'url' => $scorecard['payload']['report_pdf_url']],
		];	
	}
}else{
*/
	$report_buttons = [
		['label' => 'Half-Sheet Bi-Fold', 'format' => 'scb'],
		['label' => 'Compact (2/page)', 'format' => 'scc'],
		['label' => 'Full Sheet (portrait)', 'format' => 'sca'],
		['label' => 'Post Cards (4/page)', 'format' => 'scp'],
	];
//}
ob_start();

if ($show_personalization):
?>
<div class="card border-danger mb-3 p-2 rounded-4 shadow">
	<p class="mb-1 text-center">Feel free to add your contact information to Scorecards, print them, and share them with others.</p>
	<p class="mb-0 fw-bold text-danger text-center">Do not make any other modifications or unauthorized changes to the Scorecards.</p>
</div>
<?php 
endif; //show_personalization

if ($report_base_url):
?>
	<div class="row mb-3">
	<?php if(is_array($report_buttons) && count($report_buttons) > 0): ?>
		<?php foreach ($report_buttons as $btn): ?>
			<?php if(isset($btn['url'])): ?>
			<div class="col-12 col-lg-6">
			<a href="<?php echo esc_url($btn['url']); ?>"
				class="btn btn-sm btn-outline-primary shadow fw-bold fi-print-pdf-btn w-100 mb-2 fs-6"
				target="_blank">
				<i class="bi bi-file-pdf me-2"></i><?php echo esc_html($btn['label']); ?>
			</a>
			</div>
			<?php else: ?>
			<div class="col-12 col-lg-6 mx-auto">
			<a href="<?php echo esc_url($report_base_url . 'pdf/' . $btn['format'] . '/'); ?>"
				class="btn btn-sm btn-outline-primary shadow fw-bold fi-print-pdf-btn w-100 mb-2 fs-6"
					data-format="<?php echo esc_attr($btn['format']); ?>"
					data-pdf-base="<?php echo esc_attr($report_base_url); ?>"
					target="_blank">
					<i class="bi bi-file-pdf me-2"></i><?php echo esc_html($btn['label']); ?>
				</a>
			</div>
			<?php endif; ?>
		<?php endforeach; ?>
	<?php else: ?>
		<div class="alert alert-info mb-3">
			<small>We're sorry. There are no printable versions available for this report.</small>
		</div>
	<?php endif; ?>
	</div>
<?php else: ?>
	<div class="alert alert-warning mb-3">
		<small>No scorecards are available for this legislator. Please check back later.</small>
	</div>
<?php endif; ?>

<?php if ($show_personalization): ?>
<div class="mb-3">
	<label class="form-label fw-bold fs-7">Include on scorecard<button type="button" class="btn btn-link text-success fw-bold fs-7 btn-sm ms-4" data-bs-toggle="modal" data-bs-target="#personalizeModal" data-bs-dismiss="modal"><i class="bi bi-person-lines-fill me-2"></i>Manage Contacts</button></label>
	<?php if ($is_logged_in): ?>
		<div id="fi-print-logged-in-wrap">
		<?php if (!empty($pdf_contacts)): ?>
		<div class="fi-print-contact-checkboxes">
			<?php foreach ($pdf_contacts as $index => $contact): ?>
				<?php
				$checked = ($selected_contact_index !== null && (int) $selected_contact_index === (int) $index);
				$id = 'fi-print-contact-' . (int) $index;
				?>
				<div class="form-check">
					<input type="checkbox" class="form-check-input fi-print-contact-cb" name="fi_print_contacts[]" value="<?php echo esc_attr($index); ?>" id="<?php echo esc_attr($id); ?>" <?php echo $checked ? ' checked' : ''; ?>>
					<label class="form-check-label" for="<?php echo esc_attr($id); ?>">
						<?php
						echo esc_html($contact['name'] ?? 'Unnamed');
						if (!empty($contact['phone'])) echo ' | ' . esc_html($contact['phone']);
						if (!empty($contact['email'])) echo ' | ' . esc_html($contact['email']);
						?>
					</label>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="small text-muted mb-0">Leave all unchecked for no personalization.</p>
		<?php else: ?>
		<div class="small text-muted">No saved contacts. Add one in Personalize PDFs.</div>
		<?php endif; ?>
		</div>
	<?php else: ?>
		<!-- Guest: list built from localStorage when modal is shown (fi-print-guest-contacts) -->
		<div id="fi-print-guest-contacts" class="fi-print-contact-checkboxes" data-is-guest="1">
			<div class="small text-muted fi-print-guest-empty">Save your contact info in Personalize PDFs to include it on scorecards.</div>
		</div>
		<p class="small text-muted mb-0 fi-print-guest-hint d-none">Leave unchecked for no personalization.</p>
	<?php endif; ?>
</div>
<?php
endif; //show_personalization

$modal_body = ob_get_clean();

$args = [
	'id' => 'print',
	'button_text' => 'Print Scorecard',
	'button_icon' => 'bi bi-file-pdf',
	'button_class' => 'btn btn-sm btn-outline-danger shadow-sm col-12 fw-bold mb-2 fs-7',
	'modal_title' => 'Print Legislator Scorecard',
	'modal_body' => $modal_body,
	'modal_size' => 'modal-lg',
];
fi_legislator_modal($args);
?>

<script>
(function() {
	const modalEl = document.querySelector('#printModal');
	if (!modalEl) return;
	const buttons = modalEl.querySelectorAll('.fi-print-pdf-btn');
	const pdfUserId = <?php echo ($is_logged_in && $current_user) ? (int) $current_user->ID : 'null'; ?>;
	const isGuest = <?php echo $is_logged_in ? 'false' : 'true'; ?>;

	function getSelectedIndexes() {
		const out = [];
		modalEl.querySelectorAll('.fi-print-contact-cb').forEach(function(cb) {
			if (cb.checked && cb.value !== '') out.push(cb.value);
		});
		return out.sort(function(a, b) { return (a | 0) - (b | 0); });
	}

	function updateUrls() {
		const indexes = getSelectedIndexes();
		const baseS = (btn) => (btn.getAttribute('data-pdf-base') || '').replace(/\/$/, '');
		const format = (btn) => btn.getAttribute('data-format') || '';
		// Only add personalization segment when at least one contact is selected; no /pdf/scb/1_ or /pdf/scb/0_
		const segment = indexes.length > 0 ? (pdfUserId || 0) + '_' + indexes.join('-') : '';

		buttons.forEach(function(btn) {
			const base = baseS(btn);
			const fmt = format(btn);
			if (!base || !fmt) return;
			const url = segment ? base + '/pdf/' + fmt + '/' + segment : base + '/pdf/' + fmt + '/';
			btn.setAttribute('href', url);
		});
	}

	function esc(s) { if (s == null) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

	// Logged-in: rebuild contact list when Personalize modal updates contacts
	function buildLoggedInContactList(contacts) {
		const wrap = modalEl.querySelector('#fi-print-logged-in-wrap');
		if (!wrap) return;
		if (!Array.isArray(contacts) || contacts.length === 0) {
			wrap.innerHTML = '<div class="small text-muted">No saved contacts. Add one in Personalize PDFs.</div>';
		} else {
			var html = '<div class="fi-print-contact-checkboxes">';
			contacts.forEach(function(c, i) {
				var name = esc(c.name || 'Unnamed');
				var phone = (c.phone && String(c.phone).trim()) ? ' | ' + esc(c.phone) : '';
				var email = (c.email && String(c.email).trim()) ? ' | ' + esc(c.email) : '';
				var label = name + phone + email;
				var id = 'fi-print-contact-' + i;
				html += '<div class="form-check"><input type="checkbox" class="form-check-input fi-print-contact-cb" name="fi_print_contacts[]" value="' + i + '" id="' + id + '">' +
					'<label class="form-check-label" for="' + id + '">' + (label || ('Contact ' + (i + 1))) + '</label></div>';
			});
			html += '</div><p class="small text-muted mb-0">Leave all unchecked for no personalization.</p>';
			wrap.innerHTML = html;
			wrap.querySelectorAll('.fi-print-contact-cb').forEach(function(cb) {
				cb.addEventListener('change', updateUrls);
			});
			updateUrls();
		}
	}

	// Logged-in: bind existing checkboxes once
	if (!isGuest) {
		modalEl.querySelectorAll('.fi-print-contact-cb').forEach(function(cb) {
			cb.addEventListener('change', updateUrls);
		});
		document.addEventListener('fi:pdf-contacts-changed', function(e) {
			if (e.detail && Array.isArray(e.detail.contacts)) buildLoggedInContactList(e.detail.contacts);
		});
	}

	// Guest: build contact list from localStorage when modal is shown
	function buildGuestContactList() {
		const container = modalEl.querySelector('#fi-print-guest-contacts');
		if (!container || !container.dataset.isGuest) return;
		const guestKey = 'fi_guest_pdf_contacts';
		let contacts = [];
		try {
			const raw = localStorage.getItem(guestKey);
			if (raw) {
				const parsed = JSON.parse(raw);
				if (Array.isArray(parsed)) contacts = parsed;
			}
		} catch (e) {}
		const emptyEl = container.querySelector('.fi-print-guest-empty');
		const hintEl = modalEl.querySelector('.fi-print-guest-hint');
		if (contacts.length === 0) {
			if (emptyEl) emptyEl.classList.remove('d-none');
			if (hintEl) hintEl.classList.add('d-none');
			container.querySelectorAll('.form-check').forEach(function(n) { n.remove(); });
		} else {
			if (emptyEl) emptyEl.classList.add('d-none');
			if (hintEl) hintEl.classList.remove('d-none');
			container.querySelectorAll('.form-check').forEach(function(n) { n.remove(); });
			contacts.forEach(function(c, i) {
				const name = (c.name || 'Unnamed').trim();
				const phone = (c.phone || '').trim();
				const email = (c.email || '').trim();
				const label = name + (phone ? ' | ' + phone : '') + (email ? ' | ' + email : '');
				const id = 'fi-print-contact-' + i;
				const div = document.createElement('div');
				div.className = 'form-check';
				div.innerHTML = '<input type="checkbox" class="form-check-input fi-print-contact-cb" name="fi_print_contacts[]" value="' + i + '" id="' + id + '" checked>' +
					'<label class="form-check-label" for="' + id + '">' + (label || 'Contact ' + (i + 1)) + '</label>';
				container.appendChild(div);
				div.querySelector('input').addEventListener('change', updateUrls);
			});
		}
		updateUrls();
	}

	if (isGuest) {
		modalEl.addEventListener('shown.bs.modal', buildGuestContactList);
		// Keep Print modal list in sync when guest saves/deletes in Personalize modal
		document.addEventListener('fi:guest-contacts-changed', buildGuestContactList);
	}

	// When legislator-api changes report base (session/report selection), refresh hrefs from data-pdf-base
	modalEl.addEventListener('fi-print-report-base-changed', updateUrls);

	updateUrls();
})();
</script>
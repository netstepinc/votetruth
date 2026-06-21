<?php
/**
 * Legislator action modals.
 * Buttons live in the toolbar (legislator-header.php); this file renders modal HTML only.
 * Modal IDs must match data-bs-target values in the toolbar.
 *
 * Variables passed from legislator.php controller:
 *   $legislator, $contact, $base_url, $current_url, $current_session,
 *   $session_reports, $current_report_id, $user_lists, $pdf_contacts,
 *   $pdf_default_idx, $current_user_id, $legislator_id, $gov
 */
if (!defined('ABSPATH')) exit;

$fi_name       = $legislator['display_name'] ?? '';
$fi_first_name = $legislator['first_name']   ?? '';
$fi_score      = is_numeric($legislator['score'] ?? null) ? (int) $legislator['score'] : null;
$fi_is_logged  = is_user_logged_in();

/* Resolve current session's latest report for the print modal */
$fi_session_id  = (int) ($current_session['session_id'] ?? 0);
$fi_scorecard   = null;
if ($fi_session_id && !empty($session_reports[$fi_session_id])) {
	$fi_sess_rpts = $session_reports[$fi_session_id];
	if ($current_report_id) {
		foreach ($fi_sess_rpts as $fi_r) {
			if ((int) $fi_r['id'] === (int) $current_report_id) { $fi_scorecard = $fi_r; break; }
		}
	}
	if (!$fi_scorecard) $fi_scorecard = $fi_sess_rpts[0] ?? null;
}
$fi_report_base = '';
if ($legislator_id && $fi_scorecard) {
	$fi_report_base = home_url('/legislator/' . $legislator_id
		. '/session/' . $fi_scorecard['session_id']
		. '/report/'  . $fi_scorecard['id'] . '/');
}

/* ──────────────────────────────────────────────────────────────────
   HELPER: wrap modal HTML — shared structure for all 5 modals
   Single-use macros inlined; function avoids name-collision with core
   ────────────────────────────────────────────────────────────────── */
function fi_modal_open(string $id, string $title, string $size = ''): void {
	$cls = 'modal-dialog modal-dialog-centered modal-dialog-scrollable' . ($size ? " $size" : '');
	echo '<div class="mt-4 modal fade" id="' . esc_attr($id) . '" tabindex="-1"'
	   . ' aria-labelledby="' . esc_attr($id) . 'Label" aria-hidden="true">'
	   . '<div class="' . esc_attr($cls) . '"><div class="modal-content rounded-4">'
	   . '<div class="modal-header py-2">'
	   . '<h3 class="modal-title fs-6" id="' . esc_attr($id) . 'Label">' . esc_html($title) . '</h3>'
	   . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
	   . '</div>'
	   . '<div class="modal-body" style="max-height:calc(100vh - 150px);">';
}
function fi_modal_close(): void {
	echo '</div>'   // .modal-body
	   . '<div class="modal-footer p-0 border-0">'
	   . '<button type="button" class="btn btn-sm btn-dark w-100 rounded-bottom-4 m-0 fs-6 fw-bold"'
	   . ' data-bs-dismiss="modal"'
	   . ' style="border-top-left-radius:0;border-top-right-radius:0;">Close</button>'
	   . '</div>'
	   . '</div></div></div>'; // .modal-content / .modal-dialog / .modal
}

/* ═══════════════════════════════════════════════════════════════════
   1. CONTACT MODAL
   ═══════════════════════════════════════════════════════════════════ */
fi_modal_open('fi-contact-modal', $fi_name . '\'s Contact Information');

$fi_phone   = (string) ($contact['phone']   ?? '');
$fi_email   = (string) ($contact['email']   ?? '');
$fi_website = (string) ($contact['website'] ?? '');
$fi_social  = is_array($contact['social']  ?? null) ? $contact['social']  : [];
$fi_offices = is_array($contact['offices'] ?? null) ? $contact['offices'] : [];

$fi_has_contact = $fi_phone || $fi_email || $fi_website || !empty($fi_social) || !empty($fi_offices);

if (!$fi_has_contact): ?>
	<p class="text-muted text-center">Contact information not available for this legislator.</p>
<?php else: ?>

	<?php if ($fi_website):
		$fi_wtext = parse_url($fi_website, PHP_URL_HOST) ?: $fi_website;
		if (strpos($fi_website, '|') !== false) {
			[$fi_website, $fi_wtext] = explode('|', $fi_website, 2);
			$fi_wtext = urldecode($fi_wtext);
		}
	?>
	<div class="mb-3">
		<label class="form-label small fw-bold">Website</label>
		<a href="<?php echo esc_url($fi_website); ?>" target="_blank" rel="noopener" class="d-block mb-1">
			<i class="bi bi-box-arrow-up-right"></i> <?php echo esc_html($fi_wtext); ?>
		</a>
	</div>
	<?php endif; ?>

	<?php if ($fi_email):
		$fi_esubj = rawurlencode("I'm concerned about...");
		$fi_ebody = rawurlencode("Dear $fi_name,\n\nI am writing to express my concern about...\n\nThank you for your time and consideration.\n\nSincerely,\n[Your Name]");
		$fi_elink = "mailto:?subject=$fi_esubj&body=$fi_ebody";
	?>
	<div class="mb-3">
		<label class="form-label small fw-bold">Email</label>
		<a href="<?php echo esc_url($fi_elink); ?>" class="d-block">
			<i class="bi bi-envelope"></i> <?php echo esc_html($fi_email); ?>
		</a>
	</div>
	<?php endif; ?>

	<?php if ($fi_phone): ?>
	<div class="mb-3">
		<label class="form-label small fw-bold">Phone</label>
		<a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $fi_phone)); ?>" class="d-block">
			<i class="bi bi-telephone"></i> <?php echo esc_html($fi_phone); ?>
		</a>
	</div>
	<?php endif; ?>

	<?php if (!empty($fi_social)):
		$fi_social_def = [
			'twitter'     => ['bi-twitter-x',    'X / Twitter',  'btn-outline-info'],
			'facebook'    => ['bi-facebook',      'Facebook',     'btn-outline-primary'],
			'instagram'   => ['bi-instagram',     'Instagram',    'btn-outline-danger'],
			'linkedin'    => ['bi-linkedin',      'LinkedIn',     'btn-outline-primary'],
			'youtube'     => ['bi-youtube',       'YouTube',      'btn-outline-danger'],
			'tiktok'      => ['bi-tiktok',        'TikTok',       'btn-outline-dark'],
			'telegram'    => ['bi-telegram',      'Telegram',     'btn-outline-primary'],
			'gab'         => ['bi-shield',        'Gab',          'btn-outline-secondary'],
			'truthsocial' => ['bi-person-circle', 'Truth Social', 'btn-outline-secondary'],
		];
	?>
	<div class="d-block mb-3">
		<label class="form-label small fw-bold">Social Media</label>
		<div class="d-block">
			<?php foreach ($fi_social_def as $fi_sk => [$fi_sicon, $fi_slabel, $fi_sbtn]):
				if (empty($fi_social[$fi_sk])) continue; ?>
			<a href="<?php echo esc_url($fi_social[$fi_sk]); ?>" target="_blank" rel="noopener"
				class="btn <?php echo esc_attr($fi_sbtn); ?> btn-sm w-100 mb-2">
				<i class="bi <?php echo esc_attr($fi_sicon); ?>"></i> <?php echo esc_html($fi_slabel); ?>
			</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php if (!empty($fi_offices)): ?>
		<?php foreach ($fi_offices as $fi_off):
			$fi_oname = is_array($fi_off) ? ($fi_off['name']    ?? 'Office') : ($fi_off->name    ?? 'Office');
			$fi_oadr  = is_array($fi_off) ? ($fi_off['address'] ?? '')       : ($fi_off->address ?? '');
			$fi_ocity = is_array($fi_off) ? ($fi_off['city']    ?? '')       : ($fi_off->city    ?? '');
			$fi_ost   = is_array($fi_off) ? ($fi_off['state']   ?? '')       : ($fi_off->state   ?? '');
			$fi_ozip  = is_array($fi_off) ? ($fi_off['zip']     ?? '')       : ($fi_off->zip     ?? '');
			$fi_oph   = is_array($fi_off) ? ($fi_off['phone']   ?? '')       : ($fi_off->phone   ?? '');
			$fi_oem   = is_array($fi_off) ? ($fi_off['email']   ?? '')       : ($fi_off->email   ?? '');
			$fi_onote = is_array($fi_off) ? ($fi_off['note']    ?? '')       : ($fi_off->note    ?? '');
		?>
		<div class="mt-3">
			<label class="form-label small fw-bold"><?php echo esc_html($fi_oname); ?></label>
			<address class="small mb-0">
				<?php if ($fi_oadr)  echo esc_html($fi_oadr) . '<br>'; ?>
				<?php if ($fi_ocity || $fi_ost || $fi_ozip) echo esc_html(trim("$fi_ocity, $fi_ost $fi_ozip")) . '<br>'; ?>
			</address>
			<?php if ($fi_oph): ?>
				<div class="mt-1"><small><i class="bi bi-telephone"></i>
					<a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $fi_oph)); ?>"><?php echo esc_html($fi_oph); ?></a></small></div>
			<?php endif; ?>
			<?php if ($fi_oem): ?>
				<div class="mt-1"><small><i class="bi bi-envelope"></i>
					<a href="mailto:<?php echo esc_attr($fi_oem); ?>"><?php echo esc_html($fi_oem); ?></a></small></div>
			<?php endif; ?>
			<?php if ($fi_onote): ?>
				<div class="mt-0"><small class="text-muted"><?php echo esc_html($fi_onote); ?></small></div>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
	<?php endif; ?>

<?php endif;
fi_modal_close();

/* ═══════════════════════════════════════════════════════════════════
   2. SHARE MODAL
   ═══════════════════════════════════════════════════════════════════ */
fi_modal_open('fi-share-modal', 'Share ' . $fi_first_name . '\'s Scorecard', 'modal-sm');

$fi_share_esubj = rawurlencode("Check out this legislator's Freedom Scorecard");
$fi_share_ebody = rawurlencode("Did you know {$fi_name}'s Freedom Score is {$fi_score}%?\n\n{$base_url}\n\nClick here to see how they voted on important issues.");
$fi_share_elink = "mailto:?subject=$fi_share_esubj&body=$fi_share_ebody";
?>

<div class="row g-2 mb-3">
	<div class="col-6">
		<button type="button" class="btn btn-sm btn-outline-primary w-100" id="fiCopyLinkBtn">
			<i class="bi bi-link-45deg"></i> Copy Link
		</button>
	</div>
	<div class="col-6">
		<a href="<?php echo esc_url($fi_share_elink); ?>" class="btn btn-sm btn-outline-primary w-100">
			<i class="bi bi-envelope"></i> Email
		</a>
	</div>
</div>
<div class="row g-2 mb-3">
	<div class="col-6">
		<a href="https://x.com/intent/post?url=<?php echo rawurlencode($base_url); ?>&text=<?php echo rawurlencode("$fi_name - Freedom Score: {$fi_score}%"); ?>"
			target="_blank" class="btn btn-outline-info btn-sm w-100"><i class="bi bi-twitter-x"></i> X</a>
	</div>
	<div class="col-6">
		<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode($base_url); ?>"
			target="_blank" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-facebook"></i> Facebook</a>
	</div>
</div>
<div class="row g-2 mb-3">
	<div class="col-6">
		<a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo rawurlencode($base_url); ?>"
			target="_blank" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-linkedin"></i> LinkedIn</a>
	</div>
</div>
<div class="d-block">
	<label class="form-label small text-muted text-center w-100">Scan to share this page</label>
	<div id="fiQRCode" style="width:160px;height:160px;margin:0 auto;"></div>
</div>

<?php fi_modal_close(); ?>

<script>
(function () {
	var modalEl = document.querySelector('#fi-share-modal');
	if (!modalEl) return;

	function initShareModal() {
		var shareUrl = window.location.origin + window.location.pathname;
		if (!shareUrl.endsWith('/')) shareUrl += '/';

		var copyBtn = modalEl.querySelector('#fiCopyLinkBtn');
		if (copyBtn) {
			var newBtn = copyBtn.cloneNode(true);
			copyBtn.parentNode.replaceChild(newBtn, copyBtn);
			newBtn.addEventListener('click', function () {
				navigator.clipboard.writeText(shareUrl).then(function() {
					var orig = newBtn.innerHTML;
					newBtn.innerHTML = '<i class="bi bi-check"></i> Copied!';
					newBtn.classList.replace('btn-outline-primary', 'btn-success');
					setTimeout(function() { newBtn.innerHTML = orig; newBtn.classList.replace('btn-success', 'btn-outline-primary'); }, 2000);
				});
			});
		}

		var encoded = encodeURIComponent(shareUrl);
		modalEl.querySelectorAll('a[href*="intent/post"], a[href*="facebook.com/sharer"], a[href*="linkedin.com/sharing"]').forEach(function (link) {
			var href = link.getAttribute('href');
			if (href) link.setAttribute('href', href.replace(/[?&]url=[^&]*/, '') + (href.includes('?') ? '&' : '?') + 'url=' + encoded);
		});

		var emailLink = modalEl.querySelector('a[href^="mailto:"]');
		if (emailLink) {
			var href = emailLink.getAttribute('href');
			var subj = (href.match(/subject=([^&]*)/) || [])[1] || '';
			var body = decodeURIComponent((href.match(/body=([^&]*)/) || [])[1] || '').replace(/https?:\/\/[^\s]+/, shareUrl);
			emailLink.setAttribute('href', 'mailto:?subject=' + subj + '&body=' + encodeURIComponent(body));
		}

		var qrEl = modalEl.querySelector('#fiQRCode');
		if (qrEl) {
			qrEl.innerHTML = '';
			if (typeof QRCodeStyling !== 'undefined') {
				var qr = new QRCodeStyling({ width:200, height:200, type:'svg', data:shareUrl, margin:4,
					qrOptions:{ typeNumber:0, mode:'Byte', errorCorrectionLevel:'M' },
					backgroundOptions:{ color:'#ffffff' }, dotsOptions:{ color:'#000000', type:'rounded' } });
				qr.append(qrEl);
			} else {
				var img = document.createElement('img');
				img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encoded;
				img.alt = 'QR Code'; img.className = 'img-fluid';
				qrEl.appendChild(img);
			}
		}
	}

	modalEl.addEventListener('shown.bs.modal', initShareModal);
	if (modalEl.classList.contains('show')) initShareModal();
})();
</script>

<?php
/* ═══════════════════════════════════════════════════════════════════
   3. ADD TO MY LISTS MODAL
   ═══════════════════════════════════════════════════════════════════ */
fi_modal_open('fi-lists-modal', 'My Lists');
?>

<?php if (!$fi_is_logged): ?>
<div class="fi-lists-login-prompt text-center">
	<p class="mb-3">Create a free account to save legislators to lists and create custom scorecards.</p>
	<a href="<?php echo esc_url(home_url('/account/')); ?>" class="btn btn-primary">Create Free Account</a>
	<a href="<?php echo esc_url(wp_login_url(home_url('/account/')) . '?redirect_to=' . urlencode((is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])); ?>"
		class="btn btn-outline-primary">Log in</a>
	<p class="small text-muted mb-0">Already have a Freedom Index/JBS account?</p>
</div>

<?php else: ?>
<div class="fi-lists-content">
	<p class="mb-3">Add legislators to lists to create custom scorecards and compare legislators.</p>

	<?php if (!empty($user_lists)): ?>
	<div class="mb-3">
		<div class="list-group list-group-flush">
			<?php foreach ($user_lists as $fi_list):
				$fi_list_legs = json_decode($fi_list['legislators'] ?? '[]', true);
				$fi_in_list   = in_array($legislator_id, (array) $fi_list_legs);
			?>
			<div class="list-group-item px-0 py-2 border-0">
				<div class="form-check">
					<input class="form-check-input fi-list-checkbox" type="checkbox"
						data-list-id="<?php echo esc_attr($fi_list['id']); ?>"
						id="fiList<?php echo esc_attr($fi_list['id']); ?>"
						<?php checked($fi_in_list); ?>>
					<label class="form-check-label" for="fiList<?php echo esc_attr($fi_list['id']); ?>">
						<?php echo esc_html($fi_list['name']); ?>
						<small class="text-muted d-block"><?php echo count((array) $fi_list_legs); ?> legislator<?php echo count((array) $fi_list_legs) !== 1 ? 's' : ''; ?></small>
					</label>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

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
<?php endif;
fi_modal_close();
?>

<script>
(function () {
	'use strict';
	var modalEl = document.querySelector('#fi-lists-modal');
	if (!modalEl) return;

	function getCurrentLegislatorId() {
		return parseInt(modalEl.getAttribute('data-current-legislator-id') || '<?php echo (int) $legislator_id; ?>', 10);
	}

	function bindListCheckboxes(el) {
		el.querySelectorAll('.fi-list-checkbox').forEach(function (cb) {
			var newCb = cb.cloneNode(true);
			cb.parentNode.replaceChild(newCb, cb);
			newCb.addEventListener('change', function () {
				updateList(parseInt(this.getAttribute('data-list-id'), 10), this.checked);
			});
		});
	}

	function escHtml(str) {
		return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
	}

	function ensureListContainer(el) {
		var wrap = el.querySelector('.fi-lists-content .list-group.list-group-flush');
		if (wrap) return wrap;
		var content = el.querySelector('.fi-lists-content');
		if (!content) return null;
		var sec = document.createElement('div'); sec.className = 'mb-3';
		sec.innerHTML = '<div class="list-group list-group-flush"></div>';
		var create = content.querySelector('.border-top.pt-3');
		if (create) content.insertBefore(sec, create); else content.appendChild(sec);
		return sec.querySelector('.list-group.list-group-flush');
	}

	function prependCreatedList(listId, listName, legId) {
		var listGroup = ensureListContainer(modalEl);
		if (!listGroup || !listId || modalEl.querySelector('#fiList' + listId)) return;
		var item = document.createElement('div');
		item.className = 'list-group-item px-0 py-2 border-0';
		item.innerHTML = '<div class="form-check"><input class="form-check-input fi-list-checkbox" type="checkbox" data-list-id="' + listId + '" id="fiList' + listId + '" checked>' +
			'<label class="form-check-label" for="fiList' + listId + '">' + escHtml(listName) +
			'<small class="text-muted d-block">' + (legId ? '1 legislator' : '0 legislators') + '</small></label></div>';
		listGroup.insertBefore(item, listGroup.firstChild);
		bindListCheckboxes(modalEl);
	}

	function updateList(listId, add) {
		var fd = new FormData();
		fd.append('action', 'fi_update_list');
		fd.append('nonce', '<?php echo wp_create_nonce('fi_list_nonce'); ?>');
		fd.append('list_id', listId);
		fd.append('legislator_id', getCurrentLegislatorId());
		fd.append('add', add ? '1' : '0');
		fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { method:'POST', body:fd, credentials:'same-origin' })
			.then(function(r) { return r.status === 403 ? { _nonceFail:true } : r.json(); })
			.then(function(data) {
				if (data && data._nonceFail) { alert('Security check expired. Please refresh the page and try again.'); return; }
				if (data && !data.success) {
					var msg = (data.data && typeof data.data.message === 'string') ? data.data.message : (typeof data.data === 'string' ? data.data : 'Failed to update list');
					alert('Error: ' + msg);
				}
			}).catch(function(e) { console.error('fi_update_list error:', e); alert('An error occurred. Please try again.'); });
	}

	function createList(name, nameInput) {
		var legId = getCurrentLegislatorId();
		var fd = new FormData();
		fd.append('action', 'fi_modal_create_list');
		fd.append('nonce', '<?php echo wp_create_nonce('fi_list_nonce'); ?>');
		fd.append('name', name);
		fd.append('legislators', JSON.stringify(legId ? [legId] : []));
		fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { method:'POST', body:fd, credentials:'same-origin' })
			.then(function(r) { return r.status === 403 ? { _nonceFail:true } : r.json(); })
			.then(function(data) {
				if (data && data._nonceFail) { alert('Security check expired. Please refresh the page and try again.'); return; }
				if (data && data.success) {
					if (nameInput) nameInput.value = '';
					prependCreatedList(data.data && data.data.list_id ? parseInt(data.data.list_id, 10) : 0, name, legId);
				} else {
					alert('Error: ' + ((data && data.data && typeof data.data.message === 'string') ? data.data.message : 'Failed to create list'));
				}
			}).catch(function(e) { console.error('fi_modal_create_list error:', e); alert('An error occurred. Please try again.'); });
	}

	function initListModal(el) {
		if (!el) return;
		<?php if ($fi_is_logged): ?>
		bindListCheckboxes(el);
		var createBtn = el.querySelector('#fiCreateListBtn');
		var nameInput = el.querySelector('#fiNewListName');
		if (createBtn && nameInput && !createBtn.getAttribute('data-fi-bound')) {
			createBtn.setAttribute('data-fi-bound', '1');
			createBtn.addEventListener('click', function () {
				var n = nameInput.value.trim();
				if (!n) { alert('Please enter a list name'); return; }
				createList(n, nameInput);
			});
			nameInput.addEventListener('keypress', function (e) { if (e.key === 'Enter') createBtn.click(); });
		}
		<?php endif; ?>
	}

	modalEl.addEventListener('shown.bs.modal', function (e) {
		var trigger = e.relatedTarget;
		if (trigger && trigger.getAttribute('data-legislator-id') != null) {
			e.target.setAttribute('data-current-legislator-id', trigger.getAttribute('data-legislator-id'));
		}
		initListModal(e.target);
	});
	if (modalEl.classList.contains('show')) initListModal(modalEl);
})();
</script>

<?php
/* ═══════════════════════════════════════════════════════════════════
   4. PERSONALIZE PDFs MODAL
   ═══════════════════════════════════════════════════════════════════ */
fi_modal_open('fi-personalize-modal', 'Personalize Your Printed Scorecards', 'modal-lg');

$fi_saved_name = $fi_saved_phone = $fi_saved_email = '';
if ($fi_is_logged && $current_user_id) {
	$fi_personalize  = fi_user_personalize_get($current_user_id);
	$fi_saved_name   = $fi_personalize['name'];
	$fi_saved_phone  = $fi_personalize['phone'];
	$fi_saved_email  = $fi_personalize['email'];
} else {
	$fi_saved_name  = $_COOKIE['fi_personalize_name']  ?? '';
	$fi_saved_phone = $_COOKIE['fi_personalize_phone'] ?? '';
	$fi_saved_email = $_COOKIE['fi_personalize_email'] ?? '';
}
?>

<?php if ($fi_is_logged): ?>
<div class="row">
	<div class="col-12 col-lg-6">
		<div id="fiPdfContactsListWrapper" class="mb-3" data-contacts="<?php echo esc_attr(wp_json_encode($pdf_contacts)); ?>">
			<?php if (empty($pdf_contacts)): ?>
			<p class="text-muted small">You don't have any contacts saved yet.</p>
			<?php else: ?>
			<label class="form-label fw-bold">Saved Contacts</label>
			<div class="list-group list-group-flush">
				<?php foreach ($pdf_contacts as $fi_ci => $fi_pc): ?>
				<div class="list-group-item d-flex justify-content-between align-items-start px-0">
					<div class="flex-grow-1">
						<div class="fw-bold"><?php echo esc_html($fi_pc['name'] ?? 'Unnamed'); ?></div>
						<div class="small text-muted">
							<?php if (!empty($fi_pc['phone'])): ?><div><?php echo esc_html($fi_pc['phone']); ?></div><?php endif; ?>
							<?php if (!empty($fi_pc['email'])): ?><div><?php echo esc_html($fi_pc['email']); ?></div><?php endif; ?>
						</div>
					</div>
					<div class="ms-2">
						<button type="button" class="btn btn-sm btn-outline-secondary fi-edit-contact me-1"
							data-index="<?php echo esc_attr($fi_ci); ?>" title="Edit"><i class="bi bi-pencil"></i></button>
						<button type="button" class="btn btn-sm btn-outline-danger fi-delete-contact"
							data-index="<?php echo esc_attr($fi_ci); ?>" title="Delete"
							onclick="window.fiModalDeleteContact&&window.fiModalDeleteContact(this);return false;">
							<i class="bi bi-trash"></i></button>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>
	<div class="col-12 col-lg-6">
		<div class="mb-3">
			<label id="fiAddContactLabel" class="form-label fw-bold"><?php echo !empty($pdf_contacts) ? 'Add New Contact' : 'Add Contact'; ?></label>
			<div id="fiAddContactForm">
				<div class="card border-success rounded-3 p-2 shadow">
				<?php echo fi_get_personalize_form_html([
					'name_value'     => '',
					'phone_value'    => '',
					'email_value'    => '',
					'show_edit_index'=> true,
					'show_cancel'    => true,
					'submit_text'    => 'Save Contact',
					'submit_text_id' => 'fiPersonalizeSubmitText',
					'label_class'    => 'form-label mb-0',
				]); ?>
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
		<p>Add your contact information below to personalize printed scorecards. Your name, phone, and email will appear on legislator report PDFs.</p>
		<div class="border border-2 border-success rounded-4 p-3 mb-3">
			<h4 class="mb-1">Save Your Contact Information for Next Time</h4>
			<p class="mb-2 small">Create a free account or log in to save your information for future use.</p>
			<div class="row g-2">
				<div class="col-12 col-lg-8">
					<a href="<?php echo esc_url(wp_registration_url()); ?>" class="btn btn-outline-primary shadow btn-sm w-100">
						<i class="bi bi-person-plus"></i> Create Free Account
					</a>
				</div>
				<div class="col-12 col-lg-4">
					<a href="<?php echo esc_url(home_url('/account/?redirect_to=' . urlencode((string) get_permalink()))); ?>" class="btn btn-outline-secondary shadow btn-sm w-100">
						<i class="bi bi-box-arrow-in-right"></i> Log In
					</a>
				</div>
			</div>
		</div>
	</div>
	<div class="col-12 col-lg-6">
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
		<div id="fi-guest-personalize-form-wrap" class="card border-success rounded-3 p-2 shadow">
		<?php echo fi_get_personalize_form_html([
			'name_value'     => $fi_saved_name,
			'phone_value'    => $fi_saved_phone,
			'email_value'    => $fi_saved_email,
			'show_edit_index'=> false,
			'show_cancel'    => false,
			'submit_text'    => 'Save Personalization',
			'submit_text_id' => 'fiGuestSubmitText',
			'label_class'    => 'form-label fw-bold',
			'submit_class'   => 'btn btn-primary w-100 fw-bold',
		]); ?>
		</div>
	</div>
</div>
<div class="alert alert-danger bg-white mt-3 mb-3 shadow">
	<small class="text-danger"><strong>Privacy:</strong> Your information is saved locally in your browser and is only used to customize PDF scorecards. We do not collect or share this information with third parties.</small>
</div>
<?php endif; ?>

<button type="button" class="btn btn-success fw-bold shadow fs-6 w-100 btn-sm mb-3"
	data-bs-toggle="modal" data-bs-target="#fi-print-modal" data-bs-dismiss="modal">
	Print Scorecards
</button>

<?php fi_modal_close(); ?>

<script>
/* Global PDF-contact helpers — needed by fiModalDeleteContact below */
(function(){
	'use strict';
	window.fiPdfContacts = {
		ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
		nonce:   '<?php echo esc_js(wp_create_nonce('fi_delete_pdf_contact')); ?>'
	};
	window.fiDeletePdfContact = function(index, nonce, onSuccess, onError) {
		fetch(window.fiPdfContacts.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({ action: 'fi_delete_pdf_contact', index: index, nonce: nonce })
		})
		.then(function(r) { return r.text().then(function(t) { return { ok: r.ok, body: t }; }); })
		.then(function(res) {
			var data; try { data = JSON.parse(res.body); } catch(e) { data = null; }
			if (data && data.success === true) { if (onSuccess) onSuccess(data); else location.reload(); return; }
			if (onError) onError(data || {}); else alert('Error deleting contact: ' + (data && data.data && data.data.message ? data.data.message : 'Unknown error'));
		})
		.catch(function() { if (onError) onError({}); else alert('Error deleting contact. Please try again.'); });
	};
})();

(function() {
	var modalEl = document.querySelector('#fi-personalize-modal');
	if (!modalEl) return;

	<?php if ($fi_is_logged): ?>
	function getPdfContacts() {
		var w = document.getElementById('fiPdfContactsListWrapper');
		if (w && w.dataset.contacts) { try { return JSON.parse(w.dataset.contacts); } catch(e) {} }
		return <?php echo wp_json_encode($pdf_contacts); ?>;
	}
	function escHtml(s) { if (s==null)return''; var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
	function updatePdfContactsList(contacts) {
		var wrapper = document.getElementById('fiPdfContactsListWrapper');
		if (!wrapper) return;
		wrapper.dataset.contacts = JSON.stringify(contacts);
		if (contacts.length === 0) {
			wrapper.innerHTML = '<p class="text-muted small">You don\'t have any contacts saved yet.</p>';
		} else {
			var items = contacts.map(function(c, i) {
				var n = escHtml(c.name||'Unnamed');
				var p = (c.phone&&c.phone.trim()) ? '<div class="small text-muted">'+escHtml(c.phone)+'</div>' : '';
				var em= (c.email&&c.email.trim()) ? '<div class="small text-muted">'+escHtml(c.email)+'</div>' : '';
				return '<div class="list-group-item d-flex justify-content-between align-items-start px-0"><div class="flex-grow-1"><div class="fw-bold">'+n+'</div>'+p+em+'</div>' +
					'<div class="ms-2"><button type="button" class="btn btn-sm btn-outline-secondary fi-edit-contact me-1" data-index="'+i+'" title="Edit"><i class="bi bi-pencil"></i></button>' +
					'<button type="button" class="btn btn-sm btn-outline-danger fi-delete-contact" data-index="'+i+'" title="Delete" onclick="window.fiModalDeleteContact&&window.fiModalDeleteContact(this);return false;"><i class="bi bi-trash"></i></button></div></div>';
			}).join('');
			wrapper.innerHTML = '<label class="form-label fw-bold">Saved Contacts</label><div class="list-group list-group-flush">'+items+'</div>';
		}
		var addLabel = document.getElementById('fiAddContactLabel');
		if (addLabel) addLabel.textContent = contacts.length > 0 ? 'Add New Contact' : 'Add Contact';
		document.dispatchEvent(new CustomEvent('fi:pdf-contacts-changed', { detail:{ contacts:contacts }, bubbles:true }));
	}
	window.fiModalDeleteContact = function(btn) {
		var i = parseInt(btn.getAttribute('data-index'), 10);
		if (isNaN(i)) return;
		var nonce = window.fiPdfContacts && window.fiPdfContacts.nonce;
		if (!nonce) return;
		window.fiDeletePdfContact(i, nonce, function(data) {
			if (data && data.data && Array.isArray(data.data.contacts)) updatePdfContactsList(data.data.contacts);
		}, function() {});
	};
	document.addEventListener('click', function(e) {
		var btn = e.target.closest('.fi-edit-contact');
		if (!btn) return;
		var i = parseInt(btn.dataset.index);
		var c = getPdfContacts()[i];
		if (c) {
			document.getElementById('fiPersonalizeName').value  = c.name  || '';
			document.getElementById('fiPersonalizePhone').value = c.phone || '';
			document.getElementById('fiPersonalizeEmail').value = c.email || '';
			document.getElementById('fiEditContactIndex').value = i;
			document.getElementById('fiPersonalizeSubmitText').textContent = 'Update Contact';
			document.getElementById('fiCancelEdit').style.display = '';
			document.getElementById('fiPersonalizeName').focus();
		}
	});
	var cancelBtn = document.getElementById('fiCancelEdit');
	if (cancelBtn) {
		cancelBtn.addEventListener('click', function() {
			document.getElementById('fiPersonalizeForm').reset();
			document.getElementById('fiEditContactIndex').value = '';
			document.getElementById('fiPersonalizeSubmitText').textContent = 'Save Contact';
			this.style.display = 'none';
		});
	}
	<?php endif; ?>

	/* Guest contact storage (cookie + localStorage) */
	var guestKey = 'fi_guest_pdf_contacts';
	function getGuestContacts() { try { var r=localStorage.getItem(guestKey); if(r){var p=JSON.parse(r);return Array.isArray(p)?p:[];} }catch(e){} return []; }
	function saveGuestContact(n,p,em) {
		var arr=[{name:(n||'').trim(),phone:(p||'').trim(),email:(em||'').trim()}];
		try{localStorage.setItem(guestKey,JSON.stringify(arr));}catch(e){}
		var exp=new Date(); exp.setDate(exp.getDate()+14);
		var cookie=guestKey+'='+encodeURIComponent(JSON.stringify(arr))+'; expires='+exp.toUTCString()+'; path=/; SameSite=Lax';
		if(location.protocol==='https:') cookie+='; Secure';
		document.cookie=cookie;
	}
	function clearGuestContact() { try{localStorage.removeItem(guestKey);}catch(e){} saveGuestContact('','',''); }
	function showGuestCard(c) {
		var card=document.getElementById('fi-guest-contact-card'), fw=document.getElementById('fi-guest-personalize-form-wrap');
		if(card&&fw){
			document.getElementById('fi-guest-card-name').textContent  = (c&&c.name)  || 'Unnamed';
			document.getElementById('fi-guest-card-phone').textContent = (c&&c.phone) || '';
			document.getElementById('fi-guest-card-email').textContent = (c&&c.email) || '';
			card.classList.remove('d-none'); fw.classList.add('d-none');
		}
	}
	function showGuestForm() { var card=document.getElementById('fi-guest-contact-card'),fw=document.getElementById('fi-guest-personalize-form-wrap'); if(card&&fw){card.classList.add('d-none'); fw.classList.remove('d-none');} }
	function loadGuestState() { var c=getGuestContacts(); if(c.length>=1) showGuestCard(c[0]); else showGuestForm(); }

	<?php if (!$fi_is_logged): ?>
	modalEl.addEventListener('click', function(e) {
		if (e.target.closest('.fi-guest-delete-contact')) {
			e.preventDefault(); clearGuestContact();
			document.getElementById('fiPersonalizeName').value=''; document.getElementById('fiPersonalizePhone').value=''; document.getElementById('fiPersonalizeEmail').value='';
			document.dispatchEvent(new CustomEvent('fi:guest-contacts-changed',{bubbles:true}));
			showGuestForm();
		}
		if (e.target.closest('.fi-guest-edit-contact')) {
			e.preventDefault();
			var c=getGuestContacts(); if(c.length<1)return;
			var form=modalEl.querySelector('#fiPersonalizeForm');
			if(form){form.querySelector('#fiPersonalizeName').value=c[0].name||''; form.querySelector('#fiPersonalizePhone').value=c[0].phone||''; form.querySelector('#fiPersonalizeEmail').value=c[0].email||'';}
			var sb=modalEl.querySelector('#fi-guest-personalize-form-wrap button[type="submit"]');
			if(sb) sb.textContent='Update';
			showGuestForm();
		}
	});
	<?php endif; ?>

	var form = modalEl.querySelector('#fiPersonalizeForm');
	if (form) {
		form.addEventListener('submit', function(e) {
			e.preventDefault();
			if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
			var name  = form.querySelector('#fiPersonalizeName').value.trim();
			var phone = form.querySelector('#fiPersonalizePhone').value.trim();
			var email = form.querySelector('#fiPersonalizeEmail').value.trim();
			<?php if ($fi_is_logged): ?>
			var editIndex  = document.getElementById('fiEditContactIndex');
			var editVal    = (editIndex && editIndex.value) ? editIndex.value : '';
			var submitBtn  = form.querySelector('button[type="submit"]');
			if (submitBtn) submitBtn.disabled = true;
			fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
				method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
				body:new URLSearchParams({ action:'fi_save_pdf_contact', name:name, phone:phone, email:email, edit_index:editVal, nonce:'<?php echo wp_create_nonce('fi_save_pdf_contact'); ?>' })
			}).then(function(r){return r.json();}).then(function(data){
				if(data.success){
					var contacts=Array.isArray(data.data&&data.data.contacts)?data.data.contacts:[];
					updatePdfContactsList(contacts); form.reset(); form.classList.remove('was-validated');
					if(editIndex)editIndex.value='';
					var st=document.getElementById('fiPersonalizeSubmitText'); if(st) st.textContent='Save Contact';
					var ce=document.getElementById('fiCancelEdit'); if(ce)ce.style.display='none';
					if(submitBtn){var oh=submitBtn.innerHTML; submitBtn.innerHTML='<i class="bi bi-check"></i> Saved!'; submitBtn.classList.replace('btn-primary','btn-success');
						setTimeout(function(){submitBtn.innerHTML=oh; submitBtn.classList.replace('btn-success','btn-primary'); submitBtn.disabled=false;},2000);}
				} else {
					if(submitBtn)submitBtn.disabled=false;
					alert('Error saving contact: '+(data.data&&data.data.message?data.data.message:'Unknown error'));
				}
			}).catch(function(){if(submitBtn)submitBtn.disabled=false; alert('Error saving contact. Please try again.');});
			<?php else: ?>
			saveGuestContact(name, phone, email);
			document.dispatchEvent(new CustomEvent('fi:guest-contacts-changed',{bubbles:true}));
			var submitBtn=form.querySelector('button[type="submit"]');
			if(submitBtn){submitBtn.innerHTML='<i class="bi bi-check"></i> Saved!'; submitBtn.classList.replace('btn-primary','btn-success');}
			showGuestCard({name:name,phone:phone,email:email});
			setTimeout(function(){if(submitBtn){submitBtn.textContent='Save Personalization'; submitBtn.classList.replace('btn-success','btn-primary');}},1500);
			<?php endif; ?>
		});
	}

	modalEl.addEventListener('shown.bs.modal', function() {
		<?php if (!$fi_is_logged): ?>loadGuestState();<?php endif; ?>
	});
})();
</script>

<?php
/* ═══════════════════════════════════════════════════════════════════
   5. PRINT SCORECARD MODAL
   ═══════════════════════════════════════════════════════════════════ */
fi_modal_open('fi-print-modal', 'Print Legislator Scorecard', 'modal-lg');

$fi_print_contacts  = [];
$fi_print_def_idx   = null;
if ($fi_is_logged) {
	$fi_print_contacts = $pdf_contacts;
	$fi_print_def_idx  = $pdf_default_idx;
} elseif (function_exists('fi_pdf_contacts_guest_get')) {
	$fi_print_contacts = fi_pdf_contacts_guest_get();
	$fi_print_def_idx  = !empty($fi_print_contacts) ? 0 : null;
}

$fi_pdf_btns = [
	['label' => 'Half-Sheet Bi-Fold',   'format' => 'scb'],
	['label' => 'Compact (2/page)',      'format' => 'scc'],
	['label' => 'Full Sheet (portrait)', 'format' => 'sca'],
	['label' => 'Post Cards (4/page)',   'format' => 'scp'],
];
?>

<div class="card border-danger mb-3 p-2 rounded-4 shadow">
	<p class="mb-1 text-center">Feel free to add your contact information to Scorecards, print them, and share them with others.</p>
	<p class="mb-0 fw-bold text-danger text-center">Do not make any other modifications or unauthorized changes to the Scorecards.</p>
</div>

<?php if ($fi_report_base): ?>
<div class="row mb-3">
	<?php foreach ($fi_pdf_btns as $fi_pbtn): ?>
	<div class="col-12 col-lg-6">
		<a href="<?php echo esc_url($fi_report_base . 'pdf/' . $fi_pbtn['format'] . '/'); ?>"
			class="btn btn-sm btn-outline-primary shadow fw-bold fi-print-pdf-btn w-100 mb-2 fs-6"
			data-format="<?php echo esc_attr($fi_pbtn['format']); ?>"
			data-pdf-base="<?php echo esc_attr($fi_report_base); ?>"
			target="_blank">
			<i class="bi bi-file-pdf me-2"></i><?php echo esc_html($fi_pbtn['label']); ?>
		</a>
	</div>
	<?php endforeach; ?>
</div>
<?php else: ?>
<div class="alert alert-warning mb-3">
	<small>No scorecards are available for this legislator. Please check back later.</small>
</div>
<?php endif; ?>

<div class="mb-3">
	<label class="form-label fw-bold fs-7">Include on scorecard
		<button type="button" class="btn btn-link text-success fw-bold fs-7 btn-sm ms-4"
			data-bs-toggle="modal" data-bs-target="#fi-personalize-modal" data-bs-dismiss="modal">
			<i class="bi bi-person-lines-fill me-2"></i>Manage Contacts
		</button>
	</label>

	<?php if ($fi_is_logged): ?>
	<div id="fi-print-logged-in-wrap">
		<?php if (!empty($fi_print_contacts)): ?>
		<div class="fi-print-contact-checkboxes">
			<?php foreach ($fi_print_contacts as $fi_pci => $fi_pcc):
				$fi_pchecked = ($fi_print_def_idx !== null && (int) $fi_print_def_idx === (int) $fi_pci);
				$fi_pcbid    = 'fi-print-contact-' . (int) $fi_pci;
			?>
			<div class="form-check">
				<input type="checkbox" class="form-check-input fi-print-contact-cb" name="fi_print_contacts[]"
					value="<?php echo esc_attr($fi_pci); ?>" id="<?php echo esc_attr($fi_pcbid); ?>"<?php echo $fi_pchecked ? ' checked' : ''; ?>>
				<label class="form-check-label" for="<?php echo esc_attr($fi_pcbid); ?>">
					<?php
					echo esc_html($fi_pcc['name'] ?? 'Unnamed');
					if (!empty($fi_pcc['phone'])) echo ' | ' . esc_html($fi_pcc['phone']);
					if (!empty($fi_pcc['email'])) echo ' | ' . esc_html($fi_pcc['email']);
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
	<div id="fi-print-guest-contacts" class="fi-print-contact-checkboxes" data-is-guest="1">
		<div class="small text-muted fi-print-guest-empty">Save your contact info in Personalize PDFs to include it on scorecards.</div>
	</div>
	<p class="small text-muted mb-0 fi-print-guest-hint d-none">Leave unchecked for no personalization.</p>
	<?php endif; ?>
</div>

<?php fi_modal_close(); ?>

<script>
(function() {
	var modalEl = document.querySelector('#fi-print-modal');
	if (!modalEl) return;
	var buttons   = modalEl.querySelectorAll('.fi-print-pdf-btn');
	var pdfUserId = <?php echo ($fi_is_logged && $current_user_id) ? (int) $current_user_id : 'null'; ?>;
	var isGuest   = <?php echo $fi_is_logged ? 'false' : 'true'; ?>;

	function getSelectedIndexes() {
		var out = [];
		modalEl.querySelectorAll('.fi-print-contact-cb').forEach(function(cb) { if(cb.checked&&cb.value!=='') out.push(cb.value); });
		return out.sort(function(a,b){return(a|0)-(b|0);});
	}

	function updateUrls() {
		var indexes = getSelectedIndexes();
		var segment = indexes.length > 0 ? (pdfUserId||0) + '_' + indexes.join('-') : '';
		buttons.forEach(function(btn) {
			var base = (btn.getAttribute('data-pdf-base')||'').replace(/\/$/, '');
			var fmt  = btn.getAttribute('data-format') || '';
			if (!base || !fmt) return;
			btn.setAttribute('href', segment ? base+'/pdf/'+fmt+'/'+segment : base+'/pdf/'+fmt+'/');
		});
	}

	function escHtml(s){if(s==null)return'';var d=document.createElement('div');d.textContent=s;return d.innerHTML;}

	function buildLoggedInContactList(contacts) {
		var wrap = modalEl.querySelector('#fi-print-logged-in-wrap');
		if (!wrap) return;
		if (!Array.isArray(contacts)||contacts.length===0) {
			wrap.innerHTML='<div class="small text-muted">No saved contacts. Add one in Personalize PDFs.</div>';
		} else {
			var html='<div class="fi-print-contact-checkboxes">';
			contacts.forEach(function(c,i){
				var n=escHtml(c.name||'Unnamed'),p=(c.phone&&String(c.phone).trim())?' | '+escHtml(c.phone):'',em=(c.email&&String(c.email).trim())?' | '+escHtml(c.email):'';
				var id='fi-print-contact-'+i;
				html+='<div class="form-check"><input type="checkbox" class="form-check-input fi-print-contact-cb" name="fi_print_contacts[]" value="'+i+'" id="'+id+'"><label class="form-check-label" for="'+id+'">'+(n+p+em||'Contact '+(i+1))+'</label></div>';
			});
			html+='</div><p class="small text-muted mb-0">Leave all unchecked for no personalization.</p>';
			wrap.innerHTML=html;
			wrap.querySelectorAll('.fi-print-contact-cb').forEach(function(cb){cb.addEventListener('change',updateUrls);});
			updateUrls();
		}
	}

	if (!isGuest) {
		modalEl.querySelectorAll('.fi-print-contact-cb').forEach(function(cb){cb.addEventListener('change',updateUrls);});
		document.addEventListener('fi:pdf-contacts-changed', function(e){
			if(e.detail&&Array.isArray(e.detail.contacts)) buildLoggedInContactList(e.detail.contacts);
		});
	}

	function buildGuestContactList() {
		var container=modalEl.querySelector('#fi-print-guest-contacts');
		if(!container||!container.dataset.isGuest) return;
		var contacts=[];
		try{var r=localStorage.getItem('fi_guest_pdf_contacts');if(r){var p=JSON.parse(r);if(Array.isArray(p))contacts=p;}}catch(e){}
		var emptyEl=container.querySelector('.fi-print-guest-empty'), hintEl=modalEl.querySelector('.fi-print-guest-hint');
		if(contacts.length===0){if(emptyEl)emptyEl.classList.remove('d-none');if(hintEl)hintEl.classList.add('d-none');container.querySelectorAll('.form-check').forEach(function(n){n.remove();});}
		else {
			if(emptyEl)emptyEl.classList.add('d-none');if(hintEl)hintEl.classList.remove('d-none');container.querySelectorAll('.form-check').forEach(function(n){n.remove();});
			contacts.forEach(function(c,i){
				var n=(c.name||'Unnamed').trim(),p=(c.phone||'').trim(),em=(c.email||'').trim();
				var label=n+(p?' | '+p:'')+(em?' | '+em:'');
				var id='fi-print-contact-'+i;
				var div=document.createElement('div'); div.className='form-check';
				div.innerHTML='<input type="checkbox" class="form-check-input fi-print-contact-cb" name="fi_print_contacts[]" value="'+i+'" id="'+id+'" checked><label class="form-check-label" for="'+id+'">'+(label||'Contact '+(i+1))+'</label>';
				container.appendChild(div);
				div.querySelector('input').addEventListener('change',updateUrls);
			});
		}
		updateUrls();
	}

	if (isGuest) {
		modalEl.addEventListener('shown.bs.modal', buildGuestContactList);
		document.addEventListener('fi:guest-contacts-changed', buildGuestContactList);
	}

	/* When vote-history JS updates the active report, refresh PDF href attributes */
	modalEl.addEventListener('fi-print-report-base-changed', updateUrls);

	updateUrls();
})();
</script>

<?php
if (!defined('ABSPATH')) exit;
/*
 * REFACTOR PLANNED: Both forms (address + profile) use AJAX + timestamp redirect (bustNav/redirectSuccess).
 * This works correctly but gains nothing over a traditional form POST since a full navigation happens anyway.
 * Plan: convert to standard POST handled by template_redirect (same pattern as login-logout.php).
 * Backend logic lives in:
 *   - fi_user_meta_save()       core/users.php          (address)
 *   - fi_user_profile_update()  (unknown — find it)     (profile/email/password)
 * Keep the AJAX handlers in trait-account.php intact until the new POST handlers are tested.
 * NOTE: legislator-modal-list.php uses SEPARATE actions (fi_modal_create_list, fi_update_list) — do not touch those.
 */

if (!is_user_logged_in()) {
	wp_safe_redirect(home_url('/account/'));
	exit;
}

$user = wp_get_current_user();
$user_id = $user->ID;
$address = fi_user_meta_get($user_id, 'address');
if (!is_array($address)) {
	$address = [];
}
$has_address = !empty($address) && (!empty($address['address_1']) || !empty($address['postcode']));
$edit_address = isset($_GET['edit_address']) || !$has_address;

?>
<div class="row">
	<?php fi_get_public_template('account-nav', ['current_page' => 'profile']); ?>
	<div class="col-12 col-md-9">
		<?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>
			<div class="alert alert-success alert-dismissible fade show" role="alert">
				<strong>Success!</strong> Your profile has been updated.
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
			</div>
		<?php endif; ?>

		<?php if (isset($_GET['error'])):
			$error_messages = [
				'invalid_email' => 'Please enter a valid email address.',
				'email_exists' => 'This email address is already in use by another account.',
				'password_mismatch' => 'Passwords do not match.',
				'password_short' => 'Password must be at least 8 characters long.',
				'user_not_found' => 'User account not found.',
				'update_failed' => 'Failed to update profile. Please try again.',
				'save_failed' => 'Failed to save address. Please try again.',
			];
			$error_code = sanitize_text_field($_GET['error']);
			$error_message = $error_messages[$error_code] ?? 'An error occurred. Please try again.';
			?>
			<div class="alert alert-danger alert-dismissible fade show" role="alert">
				<strong>Error:</strong> <?php echo esc_html($error_message); ?>
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
			</div>
		<?php endif; ?>
		<div class="row">

			<div class="col-12 col-md-6">
				<div class="card mb-4 rounded-4 shadow">
					<div class="card-header rounded-4 rounded-bottom-0">
						<h4 class="card-title mb-0">Legislator Address</h4>
					</div>
					<div class="card-body bg-warning">
						<p class="mb-0">This address will be used to automatically populate your legislator search when you sign into your account.</p>
					</div>
					<div class="card-body">
						<?php if ($has_address && !$edit_address): ?>
							<div id="fi-address-display" class="mb-3">
								<?php if (!empty($address['first_name']) || !empty($address['last_name'])): ?>
									<strong><?php echo esc_html(trim(($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? ''))); ?></strong><br>
								<?php endif; ?>
								<?php if (!empty($address['address_1'])): ?>
									<?php echo esc_html($address['address_1']); ?><br>
								<?php endif; ?>
									<?php
								$city_state_zip = [];
								if (!empty($address['city'])) $city_state_zip[] = $address['city'];
								if (!empty($address['state'])) $city_state_zip[] = $address['state'];
								if (!empty($address['postcode'])) $city_state_zip[] = $address['postcode'];
								if (!empty($city_state_zip)) echo esc_html(implode(', ', $city_state_zip));
								?>
							</div>
							<a href="<?php echo esc_url(add_query_arg('edit_address', '1', home_url('/account/profile/'))); ?>" class="btn btn-sm btn-outline-primary" id="fi-address-edit-link">
								<i class="bi bi-pencil me-1"></i> Edit Address
							</a>
						<?php else: ?>
							<?php if (!$has_address): ?>
								<div class="alert alert-info mb-3">
									<p class="mb-2">Enter an address to automatically see your legislators when you sign into your account.</p>
									<p class="mb-0 small"><?php echo FI_PRIVACY_PROMISE; ?></p>
								</div>
							<?php endif; ?>
							<form id="fi-address-form" method="post" action="">
								<div class="row">
									<div class="col-md-6 mb-3">
										<label for="fi_first_name" class="form-label">First Name</label>
										<input type="text" class="form-control" id="fi_first_name" name="fi_first_name"
												value="<?php echo esc_attr($address['first_name'] ?? ''); ?>">
									</div>
									<div class="col-md-6 mb-3">
										<label for="fi_last_name" class="form-label">Last Name</label>
										<input type="text" class="form-control" id="fi_last_name" name="fi_last_name"
												value="<?php echo esc_attr($address['last_name'] ?? ''); ?>">
									</div>
								</div>
								<div class="mb-3">
									<label for="fi_address_1" class="form-label">Address Line 1</label>
									<input type="text" class="form-control" id="fi_address_1" name="fi_address_1"
											value="<?php echo esc_attr($address['address_1'] ?? ''); ?>">
								</div>
								<div class="row">
									<div class="col-md-6 mb-3">
										<label for="fi_city" class="form-label">City</label>
										<input type="text" class="form-control" id="fi_city" name="fi_city"
												value="<?php echo esc_attr($address['city'] ?? ''); ?>">
									</div>
									<div class="col-md-3 mb-3">
										<label for="fi_state" class="form-label">State</label>
										<input type="text" class="form-control" id="fi_state" name="fi_state"
												value="<?php echo esc_attr($address['state'] ?? ''); ?>" maxlength="2">
									</div>
									<div class="col-md-3 mb-3">
										<label for="fi_postcode" class="form-label">ZIP Code</label>
										<input type="text" class="form-control" id="fi_postcode" name="fi_postcode"
												value="<?php echo esc_attr($address['postcode'] ?? ''); ?>" maxlength="10">
									</div>
								</div>
								<div class="col-12">
									<button type="submit" class="btn btn-primary"><?php echo $has_address ? 'Update Address' : 'Save Address'; ?></button>
									<?php if ($has_address): ?>
										<a href="<?php echo esc_url(remove_query_arg('edit_address', home_url('/account/profile/'))); ?>" class="btn btn-outline-secondary" id="fi-address-cancel-link">Cancel</a>
									<?php endif; ?>
								</div>
							</form>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="col-12 col-md-6">
				<div class="card mb-4 rounded-4 shadow">
					<div class="card-header rounded-4 rounded-bottom-0">
						<h4 class="card-title mb-0">Account Information</h4>
					</div>
					<div class="card-body">
						<form id="fi-profile-form" method="post" action="">
							<div class="mb-3">
								<label for="user_email" class="form-label">Email <span class="text-danger">*</span></label>
								<input type="email" class="form-control" id="user_email" name="user_email"
										value="<?php echo esc_attr($user->user_email); ?>" required>
							</div>
							<div class="mb-3">
								<label for="display_name" class="form-label">Display Name</label>
								<input type="text" class="form-control" id="display_name" name="display_name"
										value="<?php echo esc_attr($user->display_name); ?>">
							</div>
							<div class="mb-3">
								<label for="user_pass" class="form-label">New Password</label>
								<input type="password" class="form-control" id="user_pass" name="user_pass"
										autocomplete="new-password" minlength="8">
								<div class="form-text">Leave blank to keep current password. Minimum 8 characters.</div>
							</div>
							<div class="mb-3">
								<label for="user_pass_confirm" class="form-label">Confirm New Password</label>
								<input type="password" class="form-control" id="user_pass_confirm" name="user_pass_confirm"
										autocomplete="new-password">
							</div>
							<button type="submit" class="btn btn-primary">Update Profile</button>
						</form>
					</div>
				</div>
			</div>

		</div>
	</div>
</div>
<script>
(function() {
	var profileUrl = <?php echo json_encode( isset($page_link) && $page_link !== '' ? $page_link : home_url('/account/profile/') ); ?>;
	var cfg = {
		ajaxUrl: <?php echo json_encode( admin_url('admin-ajax.php') ); ?>,
		profileNonce: <?php echo json_encode( wp_create_nonce('fi_update_profile') ); ?>,
		addressNonce: <?php echo json_encode( wp_create_nonce('fi_update_address') ); ?>,
		profileUrl: profileUrl
	};

	// Password match validation
	var pass = document.getElementById('user_pass');
	var passConfirm = document.getElementById('user_pass_confirm');
	if (pass && passConfirm) {
		passConfirm.addEventListener('input', function() {
			this.setCustomValidity(pass.value && pass.value !== this.value ? 'Passwords do not match' : '');
		});
		pass.addEventListener('input', function() { passConfirm.setCustomValidity(''); });
	}

	// Cache-bust Edit Address and Cancel links so the browser always fetches fresh.
	function bustNav(el) {
		if (!el) return;
		el.addEventListener('click', function(e) {
			e.preventDefault();
			var href = this.href.replace(/[&?]_=\d+/, '');
			window.location.href = href + (href.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now();
		});
	}
	bustNav(document.getElementById('fi-address-edit-link'));
	bustNav(document.getElementById('fi-address-cancel-link'));

	function redirectSuccess() {
		var base = cfg.profileUrl.replace(/\?.*$/, '');
		window.location.href = base + '?updated=1&_=' + Date.now();
	}

	var profileForm = document.getElementById('fi-profile-form');
	if (profileForm) {
		profileForm.addEventListener('submit', function(e) {
			e.preventDefault();
			if (!profileForm.checkValidity()) { profileForm.classList.add('was-validated'); return; }
			var btn = profileForm.querySelector('button[type="submit"]');
			if (btn) btn.disabled = true;
			var body = new URLSearchParams({
				action: 'fi_update_profile',
				nonce: cfg.profileNonce,
				user_email: (profileForm.querySelector('[name="user_email"]') || {}).value || '',
				display_name: (profileForm.querySelector('[name="display_name"]') || {}).value || '',
				user_pass: (profileForm.querySelector('[name="user_pass"]') || {}).value || '',
				user_pass_confirm: (profileForm.querySelector('[name="user_pass_confirm"]') || {}).value || ''
			});
			fetch(cfg.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
				.then(function(r) { return r.json(); })
				.then(function(data) {
					if (btn) btn.disabled = false;
					if (data.success) redirectSuccess();
					else alert(data.data && data.data.message ? data.data.message : 'Update failed.');
				})
				.catch(function() { if (btn) btn.disabled = false; alert('Update failed. Please try again.'); });
		});
	}

	var addressForm = document.getElementById('fi-address-form');
	if (addressForm) {
		addressForm.addEventListener('submit', function(e) {
			e.preventDefault();
			var btn = addressForm.querySelector('button[type="submit"]');
			if (btn) btn.disabled = true;
			var body = new URLSearchParams({
				action: 'fi_update_address',
				nonce: cfg.addressNonce,
				fi_first_name: (addressForm.querySelector('[name="fi_first_name"]') || {}).value || '',
				fi_last_name: (addressForm.querySelector('[name="fi_last_name"]') || {}).value || '',
				fi_address_1: (addressForm.querySelector('[name="fi_address_1"]') || {}).value || '',
				fi_city: (addressForm.querySelector('[name="fi_city"]') || {}).value || '',
				fi_state: (addressForm.querySelector('[name="fi_state"]') || {}).value || '',
				fi_postcode: (addressForm.querySelector('[name="fi_postcode"]') || {}).value || '',
				fi_country: (addressForm.querySelector('[name="fi_country"]') || {}).value || ''
			});
			fetch(cfg.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
				.then(function(r) { return r.json(); })
				.then(function(data) {
					if (btn) btn.disabled = false;
					if (data.success) redirectSuccess();
					else alert(data.data && data.data.message ? data.data.message : 'Save failed.');
				})
				.catch(function() { if (btn) btn.disabled = false; alert('Save failed. Please try again.'); });
		});
	}
})();
</script>
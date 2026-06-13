<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
Find My Legislator Form
- Single row address/zip search bar
- Stacks on mobile
- All fields always visible
*/

$gov_options = fi_govs();
$gov_options_html = '';
foreach($gov_options as $gov_code => $gov_name) {
	$gov_options_html .= '<option value="' . strtolower($gov_code) . '" class="fs-4 py-2">' . $gov_name . '</option>';
}
// Validate ZIP code format (5 digits or 9 digits with hyphen)
function validate_zip_code($zip) {
	if (empty($zip)) {
		return false;
	}
	// Remove any spaces
	$zip = trim($zip);
	// Check for 5 digits OR 9 digits with hyphen (12345 or 12345-6789)
	return preg_match('/^\d{5}(-\d{4})?$/', $zip) === 1;
}

// Check for GET parameters first (for sharing URLs)
$form_address = null;
$auto_load = false;
$zip_error = false;

if (isset($_GET['zip']) && !empty($_GET['zip'])) {
	// GET parameters take priority (for sharing)
	$zip = sanitize_text_field($_GET['zip']);
	if (validate_zip_code($zip)) {
		$form_address = [
			'address' => isset($_GET['address']) ? sanitize_text_field($_GET['address']) : '',
			'city' => isset($_GET['city']) ? sanitize_text_field($_GET['city']) : '',
			'state' => isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '',
			'zip' => $zip
		];
		// Auto-load if ZIP is valid
		$auto_load = true;
	} else {
		// Invalid ZIP - set error but still populate form
		$form_address = [
			'address' => isset($_GET['address']) ? sanitize_text_field($_GET['address']) : '',
			'city' => isset($_GET['city']) ? sanitize_text_field($_GET['city']) : '',
			'state' => isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '',
			'zip' => $zip
		];
		$zip_error = true;
		$auto_load = false;
	}
	fi_log('form_address: ' . json_encode($form_address), __FILE__, __LINE__);
//} elseif (FI_FINDMY_AUTO == true && is_user_logged_in()) {
} elseif (is_user_logged_in()) {
	// Fallback to logged-in user's saved address
	$current_user = wp_get_current_user();
	if ($current_user->ID) {
		// Get address using fi_user_meta_get (checks fi_* > shipping_* > billing_*)
		$address = fi_user_meta_get($current_user->ID, 'address');
		
		if (!empty($address) && !empty($address['postcode']) && validate_zip_code($address['postcode'])) {
			// Map address fields to form format
			$form_address = [
				'address' => $address['address_1'] ?? '',
				'city' => $address['city'] ?? '',
				'state' => $address['state'] ?? '',
				'zip' => $address['postcode'] ?? ''
			];
			$auto_load = true;
		}
	}
}
?>
<div class="container-fluid bg-primary p-0 p-lg-2 pb-lg-0 border-bottom shadow-sm">
	<div class="container-xl">
		<div class="row align-items-center mb-1">
			<div class="col-12 col-md-6 col-lg-5 px-0 px-lg-2">
				<div class="d-flex align-items-center justify-content-between">
					<h2 class="fs-5 ps-1 mb-0 text-white d-none d-md-block">Find My Legislators</h2>
					<!-- Mobile Toggle Button -->
					<button class="btn btn-sm btn-primary w-100 p-1 d-md-none mx-auto" type="button" data-bs-toggle="collapse" data-bs-target="#find-legislators-collapse" aria-expanded="false" aria-controls="find-legislators-collapse">
						<span class="toggle-text px-4 fw-bold">Find My Legislators</span>
						<i class="fas fa-chevron-down ms-1"></i>
					</button>
				</div>
			</div>
			<div class="col-12 col-md-6 col-lg-7 d-none d-md-block">
				<div class="d-flex align-items-center gap-2 flex-wrap justify-content-md-end">
					<!-- <a href="#" id="clear-form-link" class="btn btn-sm btn-light small fw-bold text-white">Clear Form</a> -->
					<span class="small text-white">Enter Zip or full address for more accuracy.</span>
				</div>
			</div>
		</div>
		<!-- Collapsible form - hidden on mobile, always visible on tablet+ -->
		<style>
			/* Force collapse to be hidden on mobile, always visible on tablet+ */
			@media (max-width: 767.98px) {
				#find-legislators-collapse:not(.show) {
					display: none !important;
				}
			}
			@media (min-width: 768px) {
				#find-legislators-collapse {
					display: block !important;
				}
			}
		</style>
		<div class="collapse d-md-block pb-3" id="find-legislators-collapse">
			<div class="row d-md-none mb-4 mt-2">
				<div class="col-12">
					<div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
						<a href="#" id="clear-form-link-mobile" class="text-decoration-none small">Clear Form</a>
						<span class="text-white small">Enter Zip or full address for more accuracy.</span>
					</div>
				</div>
			</div>
			<form id="find-representatives-form" method="post" action="<?= home_url(); ?>">
				<div class="row g-2">
					<div class="col-12 col-md-4">
						<input type="text" class="form-control form-control-sm" id="address" name="address" placeholder="Address (optional)" value="<?php echo !empty($form_address['address']) ? esc_attr($form_address['address']) : ''; ?>">
					</div>
					<div class="col-12 col-md-3">
						<input type="text" class="form-control form-control-sm" id="city" name="city" placeholder="City (optional)" value="<?php echo !empty($form_address['city']) ? esc_attr($form_address['city']) : ''; ?>">
					</div>
					<div class="col-12 col-md-2">
						<select class="form-select form-select-sm" id="state" name="state">
							<option value="">State (optional)</option>
							<?php
							foreach($gov_options as $gov_code => $gov_name) {
								$selected = (!empty($form_address['state']) && strtolower($gov_code) === strtolower($form_address['state'])) ? ' selected' : '';
								echo '<option value="' . esc_attr(strtolower($gov_code)) . '"' . $selected . '>' . esc_html( $gov_name ) . '</option>';
							}
							?>
						</select>
					</div>
					<div class="col-12 col-md-3">
						<div class="input-group input-group-sm">
							<input type="text" class="form-control form-control-sm <?php echo $zip_error ? 'is-invalid' : ''; ?>" id="zip" name="zip" placeholder="ZIP code" required value="<?php echo !empty($form_address['zip']) ? esc_attr($form_address['zip']) : ''; ?>" pattern="\d{5}(-\d{4})?">
							<button type="submit" class="btn btn-sm btn-success fw-bold px-3" id="find-officials-btn">Find</button>
							<a href="#" id="clear-form-link" class="btn btn-sm btn-warning px-1">Reset</a>
							<?php if ($zip_error): ?>
								<div class="invalid-feedback d-block">Invalid zip code</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>
<?php
/*
<script>
(function() {
	'use strict';
	
	// Wait for DOM to be ready
	function initForm() {
		// Handle form submission via AJAX
		const form = document.getElementById('find-representatives-form');
		const resultsContainer = document.getElementById('find-representatives-results');
		const submitButton = document.getElementById('find-officials-btn');
		const clearFormLink = document.getElementById('clear-form-link');
		const clearFormLinkMobile = document.getElementById('clear-form-link-mobile');
		const collapseElement = document.getElementById('find-legislators-collapse');
		const toggleButton = document.querySelector('[data-bs-target="#find-legislators-collapse"]');
		const toggleText = toggleButton ? toggleButton.querySelector('.toggle-text') : null;
		const toggleIcon = toggleButton ? toggleButton.querySelector('.fas') : null;
		
		if (!form || !resultsContainer || !submitButton) {
			console.error('Form elements not found:', {
				form: !!form,
				resultsContainer: !!resultsContainer,
				submitButton: !!submitButton
			});
			return;
		}
		
		// Store initial hero content
		const initialHeroContent = resultsContainer ? resultsContainer.innerHTML : '';
		
		// Handle collapse toggle button text/icon
		if (collapseElement && toggleButton && toggleText && toggleIcon) {
			collapseElement.addEventListener('show.bs.collapse', function() {
				if (toggleText) toggleText.textContent = 'Hide Form';
				if (toggleIcon) toggleIcon.classList.remove('fa-chevron-down');
				if (toggleIcon) toggleIcon.classList.add('fa-chevron-up');
			});
			collapseElement.addEventListener('hide.bs.collapse', function() {
				if (toggleText) toggleText.textContent = 'Show Form';
				if (toggleIcon) toggleIcon.classList.remove('fa-chevron-up');
				if (toggleIcon) toggleIcon.classList.add('fa-chevron-down');
			});
		}
		
		// Handle clear form link (desktop)
		function handleClearForm(e) {
			if (e) e.preventDefault();
			// Clear all form fields
			document.getElementById('address').value = '';
			document.getElementById('city').value = '';
			document.getElementById('state').value = '';
			document.getElementById('zip').value = '';
			// Restore hero if it exists
			if (resultsContainer && initialHeroContent) {
				resultsContainer.innerHTML = initialHeroContent;
				// Scroll to top smoothly
				window.scrollTo({ top: 0, behavior: 'smooth' });
			}
			// Focus on zip field
			document.getElementById('zip').focus();
		}
		
		if (clearFormLink) {
			clearFormLink.addEventListener('click', handleClearForm);
		}
		
		// Handle clear form link (mobile)
		if (clearFormLinkMobile) {
			clearFormLinkMobile.addEventListener('click', handleClearForm);
		}
	
		// Validate ZIP code format
		function validateZip(zip) {
			if (!zip) return false;
			// Remove spaces
			zip = zip.trim();
			// Check for 5 digits OR 9 digits with hyphen (12345 or 12345-6789)
			return /^\d{5}(-\d{4})?$/.test(zip);
		}
		
		// Show/hide ZIP error message
		function showZipError(show) {
			const zipInput = document.getElementById('zip');
			const errorDiv = zipInput.parentElement.querySelector('.invalid-feedback');
			
			if (show) {
				zipInput.classList.add('is-invalid');
				if (!errorDiv) {
					const errorMsg = document.createElement('div');
					errorMsg.className = 'invalid-feedback d-block';
					errorMsg.textContent = 'Invalid zip code';
					zipInput.parentElement.appendChild(errorMsg);
				}
			} else {
				zipInput.classList.remove('is-invalid');
				if (errorDiv) {
					errorDiv.remove();
				}
			}
		}
		
		// Validate ZIP on input
		const zipInput = document.getElementById('zip');
		if (zipInput) {
			zipInput.addEventListener('input', function() {
				const zip = this.value.trim();
				if (zip && !validateZip(zip)) {
					showZipError(true);
				} else {
					showZipError(false);
				}
			});
		}
		
		// Attach submit handler immediately
		form.addEventListener('submit', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			// Validate ZIP before submission
			const zip = document.getElementById('zip').value.trim();
			if (!validateZip(zip)) {
				showZipError(true);
				document.getElementById('zip').focus();
				return;
			}
			
			// Clear any previous error
			showZipError(false);
			
			// Disable submit button and show loading state
			const originalButtonText = submitButton.textContent;
			submitButton.disabled = true;
			submitButton.textContent = 'Searching...';
			resultsContainer.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
			
			// Collect form data
			const formData = new FormData(form);
			formData.append('action', 'fi_find_representatives');
			
			// Submit via AJAX
			fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
				method: 'POST',
				body: formData
			})
			.then(response => response.json())
			.then(data => {
				// Re-enable submit button
				submitButton.disabled = false;
				submitButton.textContent = originalButtonText;
				
				if (data.success) {
					// Display results
					resultsContainer.innerHTML = data.data.html;
					
					// Scroll to results
					resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
				} else {
					// Display error
					resultsContainer.innerHTML = '<div class="alert alert-danger">' + (data.data?.message || 'An error occurred. Please try again.') + '</div>';
				}
			})
			.catch(error => {
				// Re-enable submit button
				submitButton.disabled = false;
				submitButton.textContent = originalButtonText;
				
				// Display error
				resultsContainer.innerHTML = '<div class="alert alert-danger">An error occurred. Please try again.</div>';
				console.error('Error:', error);
			});
		});
		
		// Auto-submit for GET parameters or logged-in users with saved address
		<?php if ($auto_load && !empty($form_address['zip'])): ?>
		// Small delay to ensure form and event listener are ready
		setTimeout(function() {
			// Trigger form submission programmatically
			if (form && submitButton) {
				submitButton.click();
			}
		}, 300);
		<?php endif; ?>
	}
	
	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initForm);
	} else {
		// DOM already loaded
		initForm();
	}
})();
</script>
*/
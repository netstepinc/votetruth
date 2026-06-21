<?php if (!defined('ABSPATH')) {exit;}

$is_edit       = ($action === 'edit');
$legislator_id = $is_edit ? absint($_GET['legislator_id'] ?? 0) : 0;
//Current Gov
$gov = strtoupper(sanitize_text_field($_REQUEST['gov'] ?? $scope['gov'] ?? 'US'));

$return_url = '';
$return_url_raw = $_REQUEST['return_url'] ?? '';
if (is_string($return_url_raw) && $return_url_raw !== '') {
	// Summary: only allow safe redirects back into wp-admin, preserve list filters.
	$candidate = esc_url_raw(wp_unslash($return_url_raw));
	$return_url = wp_validate_redirect($candidate, '');
}
$back_to_list_url = $return_url ?: fi_admin_url('fi-legislators');

if ($is_edit && !$legislator_id) {
	wp_die('Missing legislator ID.');
}

$legislator = $is_edit ? fi_legislator_get($legislator_id) : fi_admin_legislators_get_defaults();
if ($is_edit && !$legislator) {
	wp_die('Legislator not found.');
}

//echo "\n<!-- LEGISLATOR DATA \n"; print_r($legislator); echo "\n-->\n";

//Parse legislator object into variables.
$meta_groups   = fi_admin_legislators_get_meta_field_groups();
$extra_meta    = fi_admin_legislators_get_extra_meta($legislator, $meta_groups);
$sessions      = is_array($legislator->sessions ?? null) ? $legislator->sessions : [];
$addresses = fi_legislator_addresses($legislator);
$websites = fi_legislator_websites($legislator);

// Session assignments as full list (one row per fi_legislator_sessions record) for edit/delete by record id.
$session_assignments = ($is_edit && $legislator_id && function_exists('fi_legislator_sessions_get_by_legislator'))
	? fi_legislator_sessions_get_by_legislator($legislator_id,['orderby' => 'date_end'])
	: [];

// Session Assignment UI state: edit by fi_legislator_sessions.id (record id).
$edit_ls_id = $is_edit ? absint($_GET['edit_ls_id'] ?? 0) : 0;
$edit_legislator_session_row = null;
if ($edit_ls_id > 0 && $legislator_id && function_exists('fi_legislator_session_get')) {
	$row = fi_legislator_session_get($edit_ls_id);
	if ($row && isset($row->legislator_id) && (int) $row->legislator_id === $legislator_id) {
		$edit_legislator_session_row = $row;
	}
}

// Get normalized meta for form fields
$normalized_meta = fi_legislator_meta_normalize(
	is_array($legislator->meta ?? null) ? $legislator->meta : []
);

$form_action   = $is_edit
	? fi_admin_edit_legislator_url($legislator->id ?? 0, ['return_url' => $return_url])
	: fi_admin_url('fi-legislators', ['action' => 'add']);
$page_title    = $is_edit ? 'Edit Legislator' : 'Add Legislator';

$view_url      = $legislator_id ? fi_get_legislator_url($legislator_id) : '';
$updated       = isset($_GET['updated']) ? (int) $_GET['updated'] : 0;


$api_sources = [
	// LegiScan is our primary source and should always be visible on the edit screen.
	'legiscan_local' => 'LegiScan',
];
// Only show additional API check panels when the required identifier AND API key (if required) exists.
$votesmart_key = fi_get_api_key('votesmart_key', 'API_KEY_VOTESMART');
if (!empty($legislator->votesmart_id ?? '') && !empty($votesmart_key)) {
	$api_sources['votesmart'] = 'VoteSmart';
}
if (
	(!empty($legislator->gov ?? '') && strtoupper((string) $legislator->gov) === 'US')
	&& (!empty($legislator->govtrack_id ?? '') || !empty($legislator->bioguide_id ?? ''))
) {
	$api_sources['govtrack'] = 'GovTrack';
}

// LegiScan local cache check: use latest assigned session so we can show session + freshness.
$legiscan_local_cache = '';
$legiscan_local_person_file = '';
$legiscan_file_message = '';
$legiscan_session_display = '';
$legiscan_folder_slug = '';

$legiscan_id = (int) ($legislator->legiscan_id ?? 0);
$latest_session = !empty($sessions) ? reset($sessions) : null;

if (is_object($latest_session) && !empty($legiscan_id)) {
	$legiscan_session_display =
		(string) ($latest_session->name ?? '')
		?: (string) ($latest_session->session_name ?? '')
		?: $legiscan_session_slug;

	//get legiscan session folder slug
	$legiscan_session_id = ($latest_session->legiscan_id ?? '');
	if($legiscan_session_id) {
		$legiscan_datasets = fi_legiscan_get_datasets($gov);
		if(isset($legiscan_datasets[$legiscan_session_id]) && isset($legiscan_datasets[$legiscan_session_id]['directory'])) {
			$legiscan_folder_slug = $legiscan_datasets[$legiscan_session_id]['directory'];
		}
	}
	//build path to local cache file
	if($legiscan_folder_slug) {
		$legiscan_local_cache = $gov . '/' . $legiscan_folder_slug . '/people/' . $legiscan_id;
		$legiscan_file_message .= '<div class="text-muted small ms-2">File: ' . $legiscan_local_cache . '</div>';
		$legiscan_local_person_file = (defined('FI_DIR_LEGISCAN') ? FI_DIR_LEGISCAN : (rtrim(FI_DIR_CACHE, '/\\') . DIRECTORY_SEPARATOR . 'legiscan' . DIRECTORY_SEPARATOR)) . $legiscan_local_cache . '.json';
		if (is_readable($legiscan_local_person_file)) {
			$legiscan_filetime = (int) @filemtime($legiscan_local_person_file);
			$legiscan_file_message .= '<div class="text-muted small ms-2">Last cached: ' . wp_date('Y-m-d H:i', $legiscan_filetime) . '</div>';
		}else{
			$legiscan_file_message .= '<div class="text-muted small ms-2">File not found</div>';
		}
	}
}

$state_options_for_template = fi_state_options();
if (isset($state_options_for_template['DC'])) {
	$dc_label = $state_options_for_template['DC'];
	unset($state_options_for_template['DC']);
} else {
	$dc_label = 'District of Columbia';
}
$state_options_for_template = array_merge(['' => 'Select State'], ['DC' => $dc_label], $state_options_for_template);
$state_select_options_html = '';
foreach ($state_options_for_template as $value => $label) {
	$state_select_options_html .= '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
}
?>
<?php fi_scope_render_selector(); ?>
<div class="wrap fi-legislator-edit" data-legislator-id="<?php echo esc_attr($legislator_id); ?>">
	<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 bg-light p-2 rounded-3" style="position: sticky; top: 32px; z-index: 100;">
		<h1 class="wp-heading-inline m-0"><?php echo esc_html($page_title); ?></h1>
		<div class="d-flex align-items-center gap-2 ms-auto">
			<div class="btn-group" role="group" aria-label="Legislator actions">
				<a href="<?php echo esc_url($back_to_list_url); ?>" class="btn btn-sm btn-outline-secondary">Back to list</a>
				<?php if ($view_url): ?>
					<a href="<?php echo esc_url($view_url); ?>" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">View Public Page</a>
				<?php endif; ?>
				<button type="submit" form="fi-legislator-form" class="btn btn-sm btn-primary">Save</button>
				<a
					href="<?php echo esc_url($back_to_list_url); ?>"
					class="btn btn-sm btn-outline-secondary"
					onclick="return confirm('Discard changes and return to the list?');"
				>Cancel</a>
				<?php if ($is_edit && $legislator_id): ?>
					<button
						type="button"
						class="btn btn-sm btn-outline-danger"
						onclick="if (confirm('Are you sure you want to delete this legislator? This will also delete related session links and rollcall votes for this legislator.')) { document.getElementById('fi-legislator-delete-form').submit(); }"
					>Delete</button>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<hr class="wp-header-end">

	<?php if ($updated): ?>
		<div class="notice notice-success is-dismissible">
			<p>Legislator saved successfully.</p>
		</div>
	<?php endif; ?>
	<?php if (!empty($_GET['session_deleted'])): ?>
		<div class="notice notice-<?php echo ((int) $_GET['session_deleted'] === 1) ? 'success' : 'error'; ?> is-dismissible">
			<p><?php echo ((int) $_GET['session_deleted'] === 1) ? 'Session assignment deleted.' : 'Failed to delete session assignment.'; ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url($form_action); ?>" id="fi-legislator-form">
		<?php wp_nonce_field('fi_save_legislator', 'fi_legislator_nonce'); echo "\n"; ?>
		<?php fi_form_field('legislator_id', ['type' => 'hidden', 'value' => $legislator_id]); echo "\n"; ?>
		<?php fi_form_field('return_url', ['type' => 'hidden', 'value' => $return_url]); echo "\n"; ?>
		<div class="row g-4 pt-4">
			<div class="col-12 col-lg-6">

				<!-- Profile Section -->
				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white border-0 pb-0">
						<h2 class="h4 mb-0">Profile <span class="text-muted">#<?= $legislator_id ? (string) $legislator_id : '(new)' ?></span></h2>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<div class="col-12 col-md-6">
<?php
//Populate image_url if the image_id is set. Refresh it every time the form is loaded in case the id was changed.
if($legislator->image_id > 0){
	$image_url = jis_get_attachment_image_src($legislator->image_id, [200,250],'face'); //[0.5,0]
	if($image_url['src'] != ''){
		$legislator->image_url = $image_url['src'];
	}
}
echo "<!-- ".$legislator->image_id." -->\n";
fi_form_field('image_url', ['type' => 'hidden', 'value' => $legislator->image_url]); echo "\n";

//Media Picker and custom image uploader and URL fetcher.
$image_picker_html = fi_admin_helpers_render_image_media_picker($legislator->image_id ?? 0);
fi_form_field('image_id', [
	'type' => 'html',
	'label' => 'Profile Image',
	'no_wrapper' => true,
	'html' => $image_picker_html
]);
?>
							</div>
							<div class="col-12 col-md-6">
								<div class="row g-3">
									<div class="col-12">
										<?php fi_form_field('display_name', [
											'label' => 'Display Name',
											'value' => $legislator->display_name ?? '',
											'no_wrapper' => true,
											'required' => true
										]); ?>
									</div>
<!--
									<div class="col-md-6">
										<?php /*fi_form_field('id_display', [
											'type' => 'text',
											'label' => 'Legislator ID',
											'value' => $legislator_id ? (string) $legislator_id : '(new)',
											'disabled' => true,
											'no_wrapper' => true,
											'help' => ''
										]);*/ ?>
									</div>
-->
									<div class="col-12">
										<?php fi_form_field('first_name', [
											'label' => 'First Name',
											'value' => $legislator->first_name ?? '',
											'no_wrapper' => true,
											'required' => true
										]); ?>
									</div>
									<div class="col-12">
										<?php fi_form_field('middle_name', [
											'label' => 'Middle Name',
											'no_wrapper' => true,
											'value' => $legislator->middle_name ?? ''
										]); ?>
									</div>
									<div class="col-12">
										<?php fi_form_field('last_name', [
											'label' => 'Last Name',
											'value' => $legislator->last_name ?? '',
											'no_wrapper' => true,
											'required' => true
										]); ?>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Contact Information Section -->
				<?php if (!empty($meta_groups['Contact Information'])): ?>
				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white border-0 pb-0">
						<h2 class="h4 mb-0">Contact Information</h2>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<?php 
							$contact_meta = $normalized_meta['contact'] ?? [];
							foreach ($meta_groups['Contact Information'] as $meta_key => $config):
							?>
							<div class="<?php echo esc_attr($config['cols'] ?? 'col-md-6'); ?>">
								<?php 
								$field_value = $contact_meta[$meta_key] ?? '';
								fi_form_field('meta[' . esc_attr($meta_key) . ']', [
									'label' => $config['label'] ?? ucfirst($meta_key),
									'type' => $config['type'] ?? 'text',
									'no_wrapper' => true,
									'value' => $field_value
								]);
								?>
							</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<!-- Social Media Section -->
				<?php if (!empty($meta_groups['Social Media'])): ?>
				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white border-0 pb-0">
						<h2 class="h4 mb-0">Social Media</h2>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<?php 
							$social_meta = $normalized_meta['social'] ?? [];
							foreach ($meta_groups['Social Media'] as $meta_key => $config):
								// Map field names to social meta keys (e.g., 'twitter' -> 'twitter', 'truth_social' -> 'truthsocial')
								$social_key = $meta_key;
								if ($meta_key === 'truth_social') {
									$social_key = 'truthsocial';
								} elseif (strpos($meta_key, '_') !== false) {
									$social_key = str_replace('_', '', $meta_key);
								}
								$field_value = $social_meta[$social_key] ?? $social_meta[$meta_key] ?? '';
							?>
							<div class="<?php echo esc_attr($config['cols'] ?? 'col-md-6'); ?>">
								<?php 
								fi_form_field('meta[' . esc_attr($meta_key) . ']', [
									'label' => $config['label'] ?? ucfirst(str_replace('_', ' ', $meta_key)),
									'type' => $config['type'] ?? 'text',
									'no_wrapper' => true,
									'value' => $field_value
								]);
								?>
							</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<!-- Websites Repeater -->
				<div class="card border mb-4">
					<div class="card-header bg-light">
						<h3 class="h6 mb-0">Websites</h3>
						<small class="text-muted">Add multiple websites (Government profile, Campaign site, etc.)</small>
					</div>
					<div class="card-body">
						<div id="fi-websites-repeater">
							<?php if (empty($websites)): ?>
								<div class="fi-website-item input-group mb-2" data-index="0">
									<input type="url" name="websites[0]" class="form-control" placeholder="https://example.com">
									<button type="button" class="btn btn-outline-secondary fi-move-website-up" title="Move Up" disabled>Up</button>
									<button type="button" class="btn btn-outline-secondary fi-move-website-down" title="Move Down" disabled>Down</button>
									<button type="button" class="btn btn-outline-danger fi-remove-website" title="Delete" disabled>Delete</button>
								</div>
							<?php else: ?>
								<?php foreach ($websites as $idx => $website): ?>
									<div class="fi-website-item input-group mb-2" data-index="<?php echo esc_attr($idx); ?>">
										<input type="url" name="websites[<?php echo esc_attr($idx); ?>]" class="form-control" value="<?php echo esc_attr($website); ?>" placeholder="https://example.com">
										<button type="button" class="btn btn-outline-secondary fi-move-website-up" title="Move Up" <?php echo $idx === 0 ? 'disabled' : ''; ?>>Up</button>
										<button type="button" class="btn btn-outline-secondary fi-move-website-down" title="Move Down" <?php echo $idx === count($websites) - 1 ? 'disabled' : ''; ?>>Down</button>
										<button type="button" class="btn btn-outline-danger fi-remove-website" title="Delete" <?php echo count($websites) === 1 ? 'disabled' : ''; ?>>Delete</button>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
						<button type="button" class="btn btn-sm btn-outline-primary" id="fi-add-website">
							<i class="bi bi-plus"></i> Add Website
						</button>
					</div>
				</div>

				<!-- Addresses Repeater -->
				<div class="card border mb-4">
					<div class="card-header bg-light">
						<h3 class="h6 mb-0">Addresses</h3>
						<small class="text-muted">Add multiple office locations (Capitol, District, Local, etc.)</small>
					</div>
					<div class="card-body">
						<div id="fi-addresses-repeater">
							<?php if (empty($addresses)): ?>
								<div class="fi-address-item border rounded p-3 mb-3" data-index="0">
									<div class="d-flex justify-content-between align-items-center mb-2">
										<h5 class="h6 mb-0">
											<span class="fi-address-number">Address #1</span>
										</h5>
										<div class="btn-group btn-group-sm" role="group">
											<button type="button" class="btn btn-outline-secondary fi-move-up" title="Move Up" disabled>
												<i class="bi bi-arrow-up"></i> Up
											</button>
											<button type="button" class="btn btn-outline-secondary fi-move-down" title="Move Down" disabled>
												<i class="bi bi-arrow-down"></i> Down
											</button>
											<button type="button" class="btn btn-outline-danger fi-remove-address" title="Delete" disabled>
												<i class="bi bi-trash"></i> Delete
											</button>
										</div>
									</div>
									<div class="row g-3">
										<div class="col-md-6">
											<?php fi_form_field('addresses[0][name]', [
												'label' => 'Name',
												'label_class' => 'form-label small',
												'no_wrapper' => true,
												'input_size' => 'sm',
												'placeholder' => 'e.g., Capitol Office'
											]); ?>
										</div>
										<div class="col-md-6">
											<?php fi_form_field_address_type('addresses[0][type]', [
												'label' => 'Type',
												'label_class' => 'form-label small',
												'no_wrapper' => true,
												'input_size' => 'sm'
											]); ?>
										</div>
										<div class="col-12">
											<?php fi_form_field('addresses[0][address]', [
												'label' => 'Street Address',
												'label_class' => 'form-label small',
												'no_wrapper' => true,
												'input_size' => 'sm'
											]); ?>
										</div>
										<div class="col-md-3">
											<?php fi_form_field('addresses[0][city]', [
												'label' => 'City',
												'label_class' => 'form-label small',
												'no_wrapper' => true,
												'input_size' => 'sm'
											]); ?>
										</div>
										<div class="col-md-2">
											<?php fi_form_field_state('addresses[0][state]', [
												'label' => 'State',
												'label_class' => 'form-label small',
												'no_wrapper' => true,
												'input_size' => 'sm'
											]); ?>
										</div>
										<div class="col-md-2">
											<?php fi_form_field('addresses[0][zip]', [
												'label' => 'ZIP',
												'label_class' => 'form-label small',
												'no_wrapper' => true,
												'input_size' => 'sm'
											]); ?>
										</div>
										<div class="col-md-2">
											<?php fi_form_field('addresses[0][phone]', [
												'label' => 'Phone',
												'label_class' => 'form-label small',
												'no_wrapper' => true,
												'input_size' => 'sm'
											]); ?>
										</div>
											<div class="col-md-3">
												<?php fi_form_field('addresses[0][email]', [
													'label' => 'Email',
													'type' => 'email',
													'label_class' => 'form-label small',
													'no_wrapper' => true,
													'input_size' => 'sm'
												]); ?>
											</div>
											<div class="col-12">
												<?php fi_form_field('addresses[0][note]', [
													'label' => 'Note',
													'type' => 'textarea',
													'label_class' => 'form-label small',
													'no_wrapper' => true,
													'input_size' => 'sm',
													'placeholder' => 'Optional note about this address location'
												]); ?>
											</div>
										</div>
									</div>
							<?php else: ?>
								<?php foreach ($addresses as $idx => $address): ?>
									<div class="fi-address-item border rounded p-3 mb-3" data-index="<?php echo esc_attr($idx); ?>">
										<div class="d-flex justify-content-between align-items-center mb-2">
											<h5 class="h6 mb-0">
												<span class="fi-address-number">Address #<?php echo esc_html($idx + 1); ?></span>
											</h5>
											<div class="btn-group btn-group-sm" role="group">
												<button type="button" class="btn btn-outline-secondary fi-move-up" title="Move Up" <?php echo $idx === 0 ? 'disabled' : ''; ?>>
													<i class="bi bi-arrow-up"></i> Up
												</button>
												<button type="button" class="btn btn-outline-secondary fi-move-down" title="Move Down" <?php echo $idx === count($addresses) - 1 ? 'disabled' : ''; ?>>
													<i class="bi bi-arrow-down"></i> Down
												</button>
												<button type="button" class="btn btn-outline-danger fi-remove-address" title="Delete" <?php echo count($addresses) === 1 ? 'disabled' : ''; ?>>
													<i class="bi bi-trash"></i> Delete
												</button>
											</div>
										</div>
										<div class="row g-3">
											<div class="col-md-6">
												<?php fi_form_field('addresses[' . esc_attr($idx) . '][name]', [
													'label' => 'Name',
													'label_class' => 'form-label small',
													'no_wrapper' => true,
													'input_size' => 'sm',
													'value' => $address['name'] ?? '',
													'placeholder' => 'e.g., Capitol Office'
												]); ?>
											</div>
											<div class="col-md-6">
												<?php fi_form_field_address_type('addresses[' . esc_attr($idx) . '][type]', [
													'label' => 'Type',
													'value' => $address['type'] ?? '',
													'label_class' => 'form-label small',
													'no_wrapper' => true,
													'input_size' => 'sm'
												]); ?>
											</div>
											<div class="col-12">
												<?php fi_form_field('addresses[' . esc_attr($idx) . '][address]', [
													'label' => 'Street Address',
													'label_class' => 'form-label small',
													'no_wrapper' => true,
													'input_size' => 'sm',
													'value' => $address['address'] ?? ''
												]); ?>
											</div>
											<div class="col-md-3">
												<?php fi_form_field('addresses[' . esc_attr($idx) . '][city]', [
													'label' => 'City',
													'label_class' => 'form-label small',
													'no_wrapper' => true,
													'input_size' => 'sm',
													'value' => $address['city'] ?? ''
												]); ?>
											</div>
											<div class="col-md-2">
												<?php fi_form_field_state('addresses[' . esc_attr($idx) . '][state]', [
													'label' => 'State',
													'value' => $address['state'] ?? '',
													'label_class' => 'form-label small',
													'no_wrapper' => true,
													'input_size' => 'sm'
												]); ?>
											</div>
											<div class="col-md-2">
												<?php fi_form_field('addresses[' . esc_attr($idx) . '][zip]', [
													'label' => 'ZIP',
													'label_class' => 'form-label small',
													'no_wrapper' => true,
													'input_size' => 'sm',
													'value' => $address['zip'] ?? ''
												]); ?>
											</div>
											<div class="col-md-2">
												<?php fi_form_field('addresses[' . esc_attr($idx) . '][phone]', [
													'label' => 'Phone',
													'label_class' => 'form-label small',
													'no_wrapper' => true,
													'input_size' => 'sm',
													'value' => $address['phone'] ?? ''
												]); ?>
											</div>
											<div class="col-md-3">
												<?php fi_form_field('addresses[' . esc_attr($idx) . '][email]', [
													'label' => 'Email',
													'type' => 'email',
													'label_class' => 'form-label small',
													'no_wrapper' => true,
													'input_size' => 'sm',
													'value' => $address['email'] ?? ''
												]); ?>
											</div>
											<div class="col-12">
												<?php fi_form_field('addresses[' . esc_attr($idx) . '][note]', [
													'label' => 'Note',
													'type' => 'textarea',
													'label_class' => 'form-label small',
													'no_wrapper' => true,
													'input_size' => 'sm',
													'value' => $address['note'] ?? '',
													'placeholder' => 'Optional note about this address location'
												]); ?>
											</div>
										</div>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
						<button type="button" class="btn btn-sm btn-outline-primary" id="fi-add-address">
							<i class="bi bi-plus"></i> Add Address
						</button>
					</div>
				</div>
				
				<?php
				// Summary: page actions are in the sticky top action bar; avoid duplicate bottom buttons.
				?>
			</div>

			<div class="col-12 col-lg-6">
			<div class="card shadow-sm mb-4">
				<div class="card-header bg-white border-0 d-flex align-items-center justify-content-between" id="fi-session-assignments">
					<h2 class="h5 mb-0">Session Assignments</h2>
				</div>
				<div class="card-body">
					<?php if (!empty($_GET['session_updated'])): ?>
						<div class="alert alert-success py-2 mb-3">Session assignment saved.</div>
					<?php endif; ?>

					<?php if ($is_edit && $legislator_id): ?>
						<?php
						// Build defaults from V2-style meta if present.
						$role_raw = (string) (($legislator->meta['legislator_role'] ?? '') ?: '');
						$role_raw = strtolower(trim($role_raw));
						//CHAMBERFLAG
						$default_chamber = $role_raw === 'sen' ? 'S' : ($role_raw === 'rep' ? 'R' : 'R');
						// Summary: when editing an existing assignment, use that row's gov for dropdown options.
						// This avoids mismatched district lists when a legislator has mixed US+state history.
						$session_form_gov = strtoupper((string) ($edit_legislator_session_row->gov ?? $gov));
						// Use the same cached filter options as the public legislator filter bar.
						// Summary: one transient read; if stale, staff will see missing options and can refresh by visiting Admin > Sessions.
						$filter_options = function_exists('fi_filter_get_options') ? fi_filter_get_options($session_form_gov, false) : ['sessions' => [], 'parties' => [], 'chambers' => []];
						$gov_sessions = is_array($filter_options['sessions'] ?? null) ? $filter_options['sessions'] : [];
						$gov_parties = is_array($filter_options['parties'] ?? null) ? $filter_options['parties'] : [];
						$gov_chambers = is_array($filter_options['chambers'] ?? null) ? $filter_options['chambers'] : [];
						$gov_districts = function_exists('fi_districts_get') ? fi_districts_get($session_form_gov, ['per_page' => -1]) : [];
						?>
						<div id="fi-session-assignment-form" class="border rounded p-3 mb-3 bg-light">
							<?php
							// Summary: editing uses selected row (by fi_legislator_sessions.id); otherwise fall back to role-based defaults for add mode.
							$row = $edit_legislator_session_row;
							$edit_vals = [
								'session_id' => $row ? (int) ($row->session_id ?? 0) : 0,
								'state' => $row ? strtoupper((string) ($row->state ?? '')) : '',
								'chamber' => $row ? strtoupper((string) ($row->chamber ?? '')) : '',
								'party' => $row ? strtoupper((string) ($row->party ?? '')) : '',
								'district_id' => 0,
							];
							$district_raw = $row ? (string) ($row->district ?? '') : '';
							if ($district_raw !== '' && is_numeric($district_raw)) {
								$edit_vals['district_id'] = (int) $district_raw;
							}
							$selected_chamber = $edit_vals['chamber'] !== '' ? $edit_vals['chamber'] : $default_chamber;
							$form_ls_id = $row ? (int) ($row->id ?? 0) : 0;
							?>
							<?php if ($edit_legislator_session_row): ?>
								<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
									<div class="small text-muted">
										Editing session assignment #<?php echo esc_html((string) $form_ls_id); ?> (session_id <?php echo esc_html((string) ($edit_legislator_session_row->session_id ?? '')); ?>)
									</div>
									<a
										class="btn btn-sm btn-outline-secondary"
										href="<?php echo esc_url(fi_admin_edit_legislator_url($legislator_id, ['return_url' => $return_url])); ?>#fi-session-assignments"
									>Cancel edit</a>
								</div>
							<?php endif; ?>
							<input type="hidden" name="ls_id" form="fi-session-assignment-post" value="<?php echo esc_attr((string) $form_ls_id); ?>" />
							<div class="row g-3 align-items-end">
								<div class="col-12 col-md-4">
									<label class="form-label fw-semibold">Session</label>
									<select name="session_id" class="form-select" form="fi-session-assignment-post" <?php echo $edit_legislator_session_row ? 'disabled' : ''; ?>>
										<option value="">Select session...</option>
										<?php foreach ((array) $gov_sessions as $s): ?>
											<?php
											$sid = (int) ($s->id ?? ($s['id'] ?? 0));
											$sname = (string) ($s->name ?? ($s['name'] ?? ''));
											?>
											<option value="<?php echo esc_attr((string) $sid); ?>" <?php selected($edit_vals['session_id'], $sid); ?>>
												<?php echo esc_html($sname); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<?php if ($edit_legislator_session_row): ?>
										<input type="hidden" name="session_id" form="fi-session-assignment-post" value="<?php echo esc_attr((string) $edit_vals['session_id']); ?>" />
									<?php endif; ?>
								</div>
								<div class="col-12 col-md-2">
									<label class="form-label fw-semibold">State</label>
									<select name="state" class="form-select" form="fi-session-assignment-post">
										<?php
										// Summary: state is primarily used for US (Congress) session assignments, but we show the field always.
										foreach ($state_options_for_template as $val => $label) {
											$val = (string) $val;
											$label = (string) $label;
											echo '<option value="' . esc_attr($val) . '" ' . selected($edit_vals['state'], $val, false) . '>' . esc_html($label) . '</option>';
										}
										?>
									</select>
								</div>
								<div class="col-6 col-md-2">
									<label class="form-label fw-semibold">Chamber</label>
									<select name="chamber" class="form-select" form="fi-session-assignment-post">
										<?php
										$chamber_choices = [];
										foreach ((array) $gov_chambers as $c) {
											$c = strtoupper((string) ($c->abbreviation ?? ($c['abbreviation'] ?? $c)));
											if (in_array($c, ['H','S'], true)) {
												$chamber_choices[] = $c;
											}
										}
										if (empty($chamber_choices)) {
											$chamber_choices = ['H','S'];
										}
										?>
										<?php foreach ($chamber_choices as $c): ?>
											<option value="<?php echo esc_attr($c); ?>" <?php selected($selected_chamber, $c); ?>>
												<?php echo esc_html(($c === 'S' ? 'SENATE' : 'HOUSE')); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-6 col-md-2">
									<label class="form-label fw-semibold">Party</label>
									<select name="party" class="form-select" form="fi-session-assignment-post">
										<option value="">—</option>
										<?php foreach ((array) $gov_parties as $p): ?>
											<?php
											$abbr = strtoupper((string) ($p->abbreviation ?? ($p['abbreviation'] ?? '')));
											$name = (string) ($p->name ?? ($p['name'] ?? ''));
											if ($abbr === '') { continue; }
											?>
											<option value="<?php echo esc_attr($abbr); ?>" <?php selected($edit_vals['party'], $abbr); ?>>
												<?php echo esc_html($abbr . ($name !== '' ? ' — ' . $name : '')); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-12 col-md-2">
									<label class="form-label fw-semibold">District</label>
									<select name="district_id" class="form-select" form="fi-session-assignment-post">
										<option value="">—</option>
										<?php foreach ((array) $gov_districts as $d): ?>
											<option value="<?php echo esc_attr((string) ($d->id ?? 0)); ?>" <?php selected($edit_vals['district_id'], (int) ($d->id ?? 0)); ?>>
												<?php echo esc_html(($d->name_short ?? $d->name ?? $d->slug ?? 'District')); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-12">
									<button type="submit" form="fi-session-assignment-post" class="btn btn-primary btn-sm"><?php echo $edit_legislator_session_row ? 'Save Changes' : 'Save Assignment'; ?></button>
									<a
										class="btn btn-sm btn-outline-secondary"
										href="<?php echo esc_url(fi_admin_edit_legislator_url($legislator_id, ['return_url' => $return_url])); ?>#fi-session-assignments"
									>Close</a>
								</div>
							</div>
						</div>
					<?php endif; ?>

					<?php if (empty($session_assignments)): ?>
						<div class="p-3 text-muted text-center">No sessions linked. Add one from the Sessions screen.</div>
					<?php else: ?>
						<div class="table-responsive">
							<table class="table table-sm table-hover mb-0">
								<thead class="table-light">
									<tr>
										<th>Gov</th>										
										<th>State</th>
										<th>Session</th>
										<th>Chamber</th>
										<th>District</th>
										<th>Party</th>
										<th class="text-end">Score</th>
										<th class="text-end">Actions</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($session_assignments as $session):
										$row_ls_id = (int) ($session->id ?? 0);
										$row_session_id = (int) ($session->session_id ?? 0);
										$sstatus = (string) ($session->status ?? '');
										$rowclass = $sstatus === 'draft' ? 'text-muted' : '';
										$chamber_disp = strtoupper((string) ($session->chamber ?? ''));
										if ($chamber_disp === 'H') { $chamber_disp = 'House'; } elseif ($chamber_disp === 'S') { $chamber_disp = 'Senate'; } elseif ($chamber_disp === '') { $chamber_disp = '—'; }
										//$chamber_disp = substr($chamber_disp, 0, 3);
									?>
										<tr>
											<td class="<?php echo esc_attr($rowclass); ?>"><?php echo esc_html($session->gov ?? ''); ?></td>
											<td class="<?php echo esc_attr($rowclass); ?>"><?php echo esc_html(strtoupper((string) ($session->state ?? '')) ?: '—'); ?></td>
											<td class="<?php echo esc_attr($rowclass); ?>"><strong><?php echo (isset($session->parent_id) && (int) $session->parent_id > 0 ? '— ' : '') . esc_html($session->session_name ?? 'Session'); ?></strong></td>
											<td class="<?php echo esc_attr($rowclass); ?>"><?php echo esc_html($chamber_disp); ?></td>
											<td class="<?php echo esc_attr($rowclass); ?>">
												<?php
												$district_display = $session->district ?? '';
												if ($district_display !== '' && is_numeric($district_display) && function_exists('fi_district_get')) {
													$d = fi_district_get((int) $district_display);
													$district_display = $d->name_short ?? $d->name ?? $district_display;
												}
												echo esc_html($district_display ?: '—');
												?>
											</td>
											<td><span class="badge <?php echo esc_attr(fi_party_bg_class($session->party)); ?>"><?php echo esc_html(strtoupper($session->party ?? '—')); ?></span></td>
											<td class="text-end">
												<?php if (isset($session->score) && $session->score !== '' && $session->score !== null): ?>
													<span class="badge bg-primary"><?php echo esc_html($session->score); ?>%</span>
												<?php elseif (isset($session->parent_id) && (int) $session->parent_id > 0): ?>
													<span class="text-muted">N/A</span>
												<?php else: ?>
													<span class="text-muted">—</span>
												<?php endif; ?>
											</td>
											<td class="text-end">
												<a class="btn btn-sm btn-outline-primary" href="<?php echo esc_url(fi_admin_edit_legislator_url($legislator_id, ['return_url' => $return_url, 'edit_ls_id' => $row_ls_id])); ?>#fi-session-assignments">Edit</a>
												<button
													type="button"
													class="btn btn-sm btn-outline-danger"
													title="Delete session assignment"
													aria-label="Delete session assignment"
													<?php echo $row_ls_id > 0 ? '' : ' disabled'; ?>
													onclick="if(<?php echo (int) $row_ls_id; ?><=0){return false;} if(confirm('Are you sure you want to delete this session assignment? This cannot be undone.')){ var el=document.getElementById('fi-session-delete-ls-id'); if(el){ el.value='<?php echo esc_attr((string) $row_ls_id); ?>'; } var f=document.getElementById('fi-session-assignment-delete-post'); if(f){ f.submit(); } }"
												>X</button>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Biographical Data Section -->
			<?php if (!empty($meta_groups['Biographical Data'])): ?>
			<div class="card shadow-sm mb-4">
				<div class="card-header bg-white border-0 pb-0">
					<h2 class="h4 mb-0">Biographical Data</h2>
				</div>
				<div class="card-body">
					<div class="row g-3">
						<?php 
						$raw_meta = is_array($legislator->meta ?? null) ? $legislator->meta : [];
						foreach ($meta_groups['Biographical Data'] as $meta_key => $config):
							$field_value = $raw_meta[$meta_key] ?? '';
							if ($field_value === '' && $meta_key === 'birth_date') {
								$field_value = $normalized_meta['personal']['birthdate'] ?? '';
							}
						?>
						<div class="<?php echo esc_attr($config['cols'] ?? 'col-md-6'); ?>">
							<?php 
							fi_form_field('meta[' . esc_attr($meta_key) . ']', [
								'label'   => $config['label'] ?? ucfirst(str_replace('_', ' ', $meta_key)),
								'type'    => $config['type'] ?? 'text',
								'options' => $config['options'] ?? [],
								'no_wrapper' => true,
								'value'   => $field_value,
							]);
							?>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<!-- External Sources -->
			<?php $search_name = urlencode(trim(($legislator->first_name ?? '') . ' ' . ($legislator->last_name ?? ''))); ?>
			<div class="card shadow-sm mb-4">
				<div class="card-header bg-white border-0 pb-0">
					<h2 class="h4 mb-0">External Sources</h2>
				</div>
				<div class="card-body">
					<p class="text-muted">Identifiers/URLs used for syncing with external sources.</p>
					<div class="row g-3">
						<div class="col-6 col-md-4 col-lg-3">
							<?php
							$bioguide_id = $legislator->bioguide_id ?? '';
							$bioguide_url = $bioguide_id ? URL_BIOGUIDE . esc_attr($bioguide_id) : URL_BIOGUIDE_SEARCH . $search_name;
							fi_form_field('bioguide_id', [
								'label_html' => '<a href="' . $bioguide_url . '" target="_blank" rel="noopener">Bioguide ID</a>',
								'no_wrapper' => true,
								'value' => $bioguide_id
							]); ?>
						</div>
						<div class="col-6 col-md-4 col-lg-3">
							<?php
							$lis_id = $legislator->lis_id ?? '';
							fi_form_field('lis_id', [
								'label' => 'LIS ID (US Sen)', //Legislative Information System ID
								'type' => 'text',
								'no_wrapper' => true,
								'value' => $lis_id
							]); ?>
						</div>
						<div class="col-6 col-md-4 col-lg-3">
							<?php
							$govtrack_id = $legislator->govtrack_id ?? '';
							$govtrack_url = $govtrack_id ? URL_GOVTRACK . esc_attr($govtrack_id) : URL_GOVTRACK_SEARCH . $search_name;
							fi_form_field('govtrack_id', [
								'label_html' => '<a href="' . $govtrack_url . '" target="_blank" rel="noopener">GovTrack ID</a>',
								'no_wrapper' => true,
								'value' => $govtrack_id
							]); ?>
						</div>
						<div class="col-6 col-md-4 col-lg-3">
							<?php 
							$votesmart_id = $legislator->votesmart_id ?? '';
							$votesmart_args = [
								'type'  => 'number',
								'value' => $votesmart_id,
								'no_wrapper' => true,
							];
							if ($votesmart_id) {
								$votesmart_args['label_html'] = '<a href="' . URL_VOTESMART . esc_attr($votesmart_id) . '" target="_blank" rel="noopener">VoteSmart ID</a>';
							} else {
								$votesmart_args['label'] = 'VoteSmart ID';
							}
							fi_form_field('votesmart_id', $votesmart_args);
							?>
						</div>
						<div class="col-6 col-md-4 col-lg-3">
							<?php
							$legiscan_id = $legislator->legiscan_id ?? '';
							$legiscan_args = [
								'type' => 'number',
								'value' => $legiscan_id,
								'no_wrapper' => true,
							];
							if($legiscan_id){
								$legiscan_slug = strtolower(str_replace(' ', '-', $legislator->first_name . ' ' . $legislator->last_name));
								$legiscan_url = str_replace('/people//id/','/people/' . $legiscan_slug . '/id/',URL_LEGISCAN_BIO) . $legiscan_id;
								$legiscan_args['label_html'] = '<a href="' . $legiscan_url . '" target="_blank" rel="noopener">LegiScan People ID</a>';
							}else{
								$legiscan_args['label'] = 'LegiScan People ID';
							}
							fi_form_field('legiscan_id', $legiscan_args); ?>
						</div>

						<div class="col-6 col-md-4 col-lg-3">
							<?php
							$ballotpedia_id = $legislator->ballotpedia_id ?? '';
							$ballotpedia_url = $ballotpedia_id ? URL_BALLOTPEDIA . esc_attr($ballotpedia_id) : URL_BALLOTPEDIA_SEARCH . $search_name;
							fi_form_field('ballotpedia_id', [
								'type' => 'text',
								'label_html' => '<a href="' . $ballotpedia_url . '" target="_blank" rel="noopener">Ballotpedia Slug</a>',
								'no_wrapper' => true,
								'value' => $ballotpedia_id
							]); ?>
						</div>

						<div class="col-6 col-md-4 col-lg-3">
							<?php
							$opensecrets_id = $legislator->meta['opensecrets_id'] ?? '';
							if($opensecrets_id){
								$opensecrets_url = URL_OPENSECRETS . esc_attr($opensecrets_id);
							}else{
								$opensecrets_url = URL_OPENSECRETS_SEARCH . $search_name;
							}
							fi_form_field('meta[opensecrets_id]', [
								'type' => 'text',
								'label_html' => '<a href="' . $opensecrets_url . '" target="_blank" rel="noopener">OpenSecrets ID</a>',
								'no_wrapper' => true,
								'value' => $opensecrets_id
							]); ?>
						</div>

						<div class="col-md-6">
							<?php
							$raw_meta = is_array($legislator->meta ?? null) ? $legislator->meta : [];
							$url_wikipedia = (string) ($raw_meta['url_wikipedia'] ?? '');
							$derived = '';
							if(!$url_wikipedia){
								$url_wikipedia = 'https://en.wikipedia.org/wiki/' . $legislator->first_name . '_' . $legislator->last_name;
								$derived = '<span class="text-muted ms-1">(?)</span>';
							}
							fi_form_field('meta[url_wikipedia]', [
								'type' => 'url',
								'label_html' => '<a href="' . esc_url($url_wikipedia) . '" target="_blank" rel="noopener">Wikipedia Profile</a>' . $derived,
								'no_wrapper' => true,
								'value' => $url_wikipedia,
							]);
							?>
						</div>

						<div class="col-md-6">
							<?php
							$url_wikidata = (string) ($raw_meta['url_wikidata'] ?? '');
							// If not present, link search on Wikidata by name as header, but use derived as input value.
							$search_url = 'https://www.wikidata.org/w/index.php?search=' . urlencode(trim($legislator->first_name . ' ' . $legislator->last_name)) . '&language=en';
							$label_url = $url_wikidata ? $url_wikidata : $search_url;
							fi_form_field('meta[url_wikidata]', [
								'label_html' => '<a href="' . esc_url($label_url) . '" target="_blank" rel="noopener">Wikidata Profile</a>',
								'type' => 'url',
								'no_wrapper' => true,
								'value' => $url_wikidata
							]); ?>
						</div>

						<div class="col-md-6">
							<?php
							$raw_meta = is_array($legislator->meta ?? null) ? $legislator->meta : [];
							$url_openstates = (string) ($raw_meta['url_openstates'] ?? '');
							fi_form_field('meta[url_openstates]', [
								'type' => 'url',
								'label' => 'OpenStates Profile URL',
								'no_wrapper' => true,
								'value' => $url_openstates,
							]);
							?>
						</div>
					</div>
				</div>
			</div>

			<div class="card shadow-sm mb-4" id="fi-api-checks">
					<div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
						<h2 class="h5 mb-0">API Data Checks</h2>
						<?php if (!empty($api_sources)): ?>
							<button type="button" class="btn btn-secondary btn-sm" id="fi-refresh-all-api">
								Run All Checks
							</button>
						<?php endif; ?>
					</div>
					<div class="list-group list-group-flush fi-api-panels">
						<?php foreach ($api_sources as $source_key => $label): ?>
							<div
								class="list-group-item fi-api-panel"
								data-fi-api-source="<?php echo esc_attr($source_key); ?>"
								<?php if ($source_key === 'legiscan_local'): ?>
									data-legiscan-cache-rel="<?php echo esc_attr($legiscan_local_cache ?? ''); ?>"
								<?php endif; ?>
							>
								<div class="d-flex justify-content-between align-items-center mb-2">
									<div>
										<?php if ($source_key === 'legiscan_local'): ?>
											<strong>
												LegiScan<?php echo $legiscan_session_display !== '' ? ': ' . esc_html($legiscan_session_display) : ''; ?>
											</strong>
										<?php else: ?>
											<strong><?php echo esc_html($label); ?></strong>
										<?php endif; ?>
										<span class="badge bg-secondary ms-2 fi-api-status">Not checked</span>

										<?php 
										if ($source_key === 'legiscan_local' && !empty($legiscan_file_message)){
											echo $legiscan_file_message;
										}
										?>
									</div>
									<button type="button" class="btn btn-sm btn-outline-secondary fi-fetch-source mb-auto" data-source="<?php echo esc_attr($source_key); ?>">
										Check
									</button>
								</div>
								<div class="fi-api-results" style="display: none;">
									<!-- Diff results will be inserted here -->
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="card-footer text-muted small">
						API checks compare remote data to known fields. Green = match, Red = different, Blue = new field.
					</div>
				</div>

			<!-- Additional Meta (moved to bottom) -->
			<div class="card shadow-sm mb-4 bg-light">
				<div class="card-header border-0">
					<h2 class="h5 mb-0">Additional Meta</h2>
				</div>
				<div class="card-body">
					<?php if (empty($extra_meta)): ?>
						<p class="text-muted mb-0">No extra metadata found.</p>
					<?php else: ?>
						<div class="table-responsive">
							<table class="table table-sm align-middle mb-0">
								<tbody>
									<?php foreach ($extra_meta as $key => $value):
									//Tweak value display for some fields
									if(is_string($value) && substr($value,0,4) === 'http'){
										$value = '<a href="' . urldecode($value) . '" target="_blank" rel="noopener">' . urldecode($value) . '</a>';
									}elseif(is_scalar($value)){
										$value = esc_html($value);
									}else{
										$value = wp_json_encode($value);
									}
									?>
										<tr>
											<th scope="row" class="text-muted" style="width: 40%;"><?php echo esc_html($key); ?></th>
											<td><?php echo $value; ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>

<?php
//All votes by this legislator
global $wpdb;
$rc_history_query = $wpdb->prepare("SELECT rc.id, rc.vote_id, rc.legislator_id, rc.cast, v.session_id, v.title as vote_title, v.bill_number as bill_key, v.constitutional, v.gov, v.chamber, s.name as session_name, v.date_voted 
	FROM {$wpdb->prefix}fi_voterc rc 
	LEFT JOIN {$wpdb->prefix}fi_votes v ON rc.vote_id = v.id
	LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
	WHERE rc.legislator_id = %d ORDER BY v.date_voted DESC, rc.id DESC", $legislator_id);
$rc_history = $wpdb->get_results($rc_history_query);
?>
			<div class="card shadow-sm mb-4 bg-white">
				<div class="card-header border-0">
					<h2 class="h5 mb-0">All Votes Cast by <?php echo esc_html($legislator->display_name); ?></h2>
				</div>
				<div class="card-body p-0">
					<?php if (empty($rc_history)): ?>
						<p class="text-muted mb-0">No voting history found.</p>
					<?php else: ?>
						<div class="table-responsive" style="max-height:720px;overflow-y:auto;">
							<table class="table table-sm align-middle mb-0">
								<thead>
									<tr>
										<th>Gov</th>										
										<th>Bill</th>
										<th>Cast</th>
										<th>Office</th>
										<th>Vote</th>
										<th>Session</th>
									</tr>
								</thead>
								<tbody>
								<?php 
								foreach ($rc_history as $rc){
									$bill_html = '<a href="'.fi_admin_url('fi-votes', ['action' => 'edit', 'vote_id' => $rc->vote_id]).'" target="_blank" rel="noopener">'.$rc->bill_key.'</a>';
									$cast_html = $rc->constitutional == $rc->cast ? '<span class="text-success fw-bold">'.$rc->cast.'</span>' : '<span class="text-danger fw-bold">'.$rc->cast.'</span>';
									echo '<tr>';
									echo '<th scope="row">'.$rc->gov.'</td>';
									echo '<td class="align-text-top">'.$bill_html.'</th>';
									echo '<td class="align-text-top">'.$cast_html.'</td>';
									echo '<td class="align-text-top">'.$rc->chamber.'</td>';
									echo '<td class="align-text-top">'.$rc->vote_title.'</td>';
									echo '<td class="align-text-top text-nowrap">'.$rc->session_name.'</td>';
									echo '</tr>';
								} ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
<?php fi_merge_legislators_duplicate_check($legislator);?>
			</div>
		</div>
	</div>
	</form>

	<!-- Standalone (non-nested) forms for session assignment actions -->
	<?php if ($is_edit && $legislator_id): ?>
		<form id="fi-session-assignment-post" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:none;">
			<input type="hidden" name="action" value="fi_legislator_session_save" />
			<?php wp_nonce_field('fi_legislator_session_save', 'fi_legislator_session_nonce'); ?>
			<input type="hidden" name="legislator_id" value="<?php echo esc_attr((string) $legislator_id); ?>" />
			<input type="hidden" name="gov" value="<?php echo esc_attr((string) $session_form_gov); ?>" />
			<?php if ($return_url !== ''): ?>
				<input type="hidden" name="return_url" value="<?php echo esc_attr($return_url); ?>" />
			<?php endif; ?>
		</form>
		<form id="fi-session-assignment-delete-post" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:none;">
			<input type="hidden" name="action" value="fi_legislator_session_delete" />
			<?php wp_nonce_field('fi_legislator_session_delete', 'fi_legislator_session_delete_nonce'); ?>
			<input type="hidden" id="fi-session-delete-ls-id" name="ls_id" value="" />
			<input type="hidden" name="legislator_id" value="<?php echo esc_attr((string) $legislator_id); ?>" />
			<?php if ($return_url !== ''): ?>
				<input type="hidden" name="return_url" value="<?php echo esc_attr($return_url); ?>" />
			<?php endif; ?>
		</form>
	<?php endif; ?>
</div>

<?php if ($is_edit && $legislator_id): ?>
	<form
		id="fi-legislator-delete-form"
		method="post"
		action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
		style="display:none;"
	>
		<?php wp_nonce_field('fi_delete_legislator', 'fi_delete_legislator_nonce'); ?>
		<input type="hidden" name="action" value="fi_delete_legislator">
		<input type="hidden" name="legislator_id" value="<?php echo esc_attr($legislator_id); ?>">
		<input type="hidden" name="return_url" value="<?php echo esc_attr($return_url); ?>">
	</form>
<?php endif; ?>

<script>
(function($) {
	const legislatorId = $('.fi-legislator-edit').data('legislator-id');
	
	// Fetch all API data
	$('#fi-refresh-all-api').on('click', function() {
		$('.fi-fetch-source').each(function() {
			$(this).click();
		});
	});
	
	// Fetch single source
	$('.fi-fetch-source').on('click', function() {
		const $btn = $(this);
		const source = $btn.data('source');
		const $panel = $btn.closest('.fi-api-panel');
		const $results = $panel.find('.fi-api-results');
		const $status = $panel.find('.fi-api-status');
		
		$btn.prop('disabled', true).text('Checking...');
		$status.removeClass('bg-success bg-danger bg-warning').addClass('bg-secondary').text('Checking...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'fi_admin_action',
				sub_action: 'fetch_api_data',
				legislator_id: legislatorId,
				source: source,
				// For LegiScan local, we already computed the exact cache path in PHP; send it to the server.
				cache_rel: (source === 'legiscan_local') ? ($panel.data('legiscan-cache-rel') || '') : '',
				nonce: fiAdmin?.nonce || ''
			}
		}).done(function(response) {
			const apiPayload = response?.data?.api_data?.[source];
			
			// The handler always returns success, even when a source payload contains an _fi_error object.
			if (response?.success && apiPayload?._fi_error) {
				$status.removeClass('bg-secondary').addClass('bg-danger').text('Error');
				const msg = apiPayload?.error ? _.escape(apiPayload.error) : 'Error fetching data.';
				$results.html(`<p class="text-danger mb-0 small">${msg}</p>`).show();
				return;
			}
			
			if (response?.success && response.data?.comparisons?.[source]) {
				displayApiDiff($panel, source, response.data.comparisons[source], apiPayload);
				$status.removeClass('bg-secondary').addClass('bg-success').text('Checked');
				return;
			}
			
			$status.removeClass('bg-secondary').addClass('bg-warning').text('No data');
			$results.html('<p class="text-muted mb-0 small">No data available from this source.</p>').show();
		}).fail(function() {
			$status.removeClass('bg-secondary').addClass('bg-danger').text('Error');
			$results.html('<p class="text-danger mb-0 small">Error fetching data.</p>').show();
		}).always(function() {
			$btn.prop('disabled', false).text('Check');
		});
	});
	
	function displayApiDiff($panel, source, comparison, apiData) {
		const $results = $panel.find('.fi-api-results');
		let html = '<div class="mt-3"><table class="table table-sm table-bordered mb-0">';
		
		let hasUpdates = false;
		
		Object.keys(comparison).forEach(function(field) {
			const comp = comparison[field];
			if (comp.status === 'match') {
				html += `<tr class="table-success"><td><strong>${_.escape(comp.label)}</strong></td><td>${_.escape(comp.our_value || '—')}</td><td><span class="badge bg-success">Match</span></td></tr>`;
			} else if (comp.status === 'diff') {
				hasUpdates = true;
				html += `<tr class="table-warning">
					<td><strong>${_.escape(comp.label)}</strong></td>
					<td>
						<div class="small text-muted">Current: ${_.escape(comp.our_value || '—')}</div>
						<div class="small">API: ${_.escape(comp.api_value || '—')}</div>
					</td>
					<td>
						<button class="btn btn-sm btn-primary fi-update-field" 
								data-field="${_.escape(field)}" 
								data-value="${_.escape(comp.api_value)}"
								data-source="${_.escape(source)}">
							Update
						</button>
					</td>
				</tr>`;
			} else if (comp.status === 'missing') {
				hasUpdates = true;
				html += `<tr class="table-info">
					<td><strong>${_.escape(comp.label)}</strong></td>
					<td>${_.escape(comp.api_value || '—')}</td>
					<td>
						<button class="btn btn-sm btn-primary fi-update-field" 
								data-field="${_.escape(field)}" 
								data-value="${_.escape(comp.api_value)}"
								data-source="${_.escape(source)}">
							Add
						</button>
					</td>
				</tr>`;
			}
		});
		
		html += '</table></div>';
		
		if (!hasUpdates) {
			html += '<p class="text-muted mb-0 small mt-2">All fields match or are already set.</p>';
		}
		
		$results.html(html).show();
		
		// Bind update buttons
		$results.find('.fi-update-field').on('click', function() {
			const $updateBtn = $(this);
			const field = $updateBtn.data('field');
			const value = $updateBtn.data('value');
			const source = $updateBtn.data('source');
			
			$updateBtn.prop('disabled', true).text('Updating...');
			
			const updates = {};
			updates[field] = value;
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'fi_admin_action',
					sub_action: 'update_from_api',
					legislator_id: legislatorId,
					source: source,
					updates: updates,
					nonce: fiAdmin?.nonce || ''
				}
			}).done(function(response) {
				if (response?.success) {
					$updateBtn.removeClass('button-primary').addClass('button-secondary').text('Updated').prop('disabled', true);
					setTimeout(function() {
						// Reload back to the API section so the user doesn't lose context.
						window.location.href = window.location.pathname + window.location.search + '#fi-api-checks';
					}, 250);
				} else {
					$updateBtn.prop('disabled', false).text('Update');
					alert('Update failed: ' + (response?.data || 'Unknown error'));
				}
			}).fail(function() {
				$updateBtn.prop('disabled', false).text('Update');
				alert('Update failed. Please try again.');
			});
		});
	}
	
// Address Repeater
let addressIndex = $('.fi-address-item').length;

$('#fi-add-address').on('click', function() {
	const template = `
		<div class="fi-address-item border rounded p-3 mb-3" data-index="${addressIndex}">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<h5 class="h6 mb-0">
					<span class="fi-address-number">Address #${addressIndex + 1}</span>
				</h5>
				<div class="btn-group btn-group-sm" role="group">
					<button type="button" class="btn btn-outline-secondary fi-move-up" title="Move Up" disabled>
						<i class="bi bi-arrow-up"></i> Up
					</button>
					<button type="button" class="btn btn-outline-secondary fi-move-down" title="Move Down" disabled>
						<i class="bi bi-arrow-down"></i> Down
					</button>
					<button type="button" class="btn btn-outline-danger fi-remove-address" title="Delete" disabled>
						<i class="bi bi-trash"></i> Delete
					</button>
				</div>
			</div>
			<div class="row g-3">
				<div class="col-md-6">
					<label class="form-label small">Name</label>
					<input type="text" name="addresses[${addressIndex}][name]" class="form-control form-control-sm" placeholder="e.g., Capitol Office">
				</div>
				<div class="col-md-6">
					<label class="form-label small">Type</label>
					<select name="addresses[${addressIndex}][type]" class="form-select form-select-sm">
						<option value="">Select Type</option>
						<option value="capitol">Capitol</option>
						<option value="district">District</option>
						<option value="local">Local</option>
						<option value="other">Other</option>
					</select>
				</div>
				<div class="col-12">
					<label class="form-label small">Street Address</label>
					<input type="text" name="addresses[${addressIndex}][address]" class="form-control form-control-sm">
				</div>
				<div class="col-md-3">
					<label class="form-label small">City</label>
					<input type="text" name="addresses[${addressIndex}][city]" class="form-control form-control-sm">
				</div>
				<div class="col-md-2">
					<label class="form-label small">State</label>
					<select name="addresses[${addressIndex}][state]" class="form-select form-select-sm">
						<?php echo $state_select_options_html; ?>
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label small">ZIP</label>
					<input type="text" name="addresses[${addressIndex}][zip]" class="form-control form-control-sm">
				</div>
				<div class="col-md-2">
					<label class="form-label small">Phone</label>
					<input type="text" name="addresses[${addressIndex}][phone]" class="form-control form-control-sm">
				</div>
				<div class="col-md-3">
					<label class="form-label small">Email</label>
					<input type="email" name="addresses[${addressIndex}][email]" class="form-control form-control-sm">
				</div>
				<div class="col-12">
					<label class="form-label small">Note</label>
					<textarea name="addresses[${addressIndex}][note]" class="form-control form-control-sm" rows="2" placeholder="Optional note about this address location"></textarea>
				</div>
			</div>
		</div>
	`;
	$('#fi-addresses-repeater').append(template);
	addressIndex++;
	renumberAddresses();
});

// Move address up
$(document).on('click', '.fi-move-up', function() {
		const $item = $(this).closest('.fi-address-item');
		const $prev = $item.prev('.fi-address-item');
		if ($prev.length) {
			$item.insertBefore($prev);
			renumberAddresses();
		}
	});
	
	// Move address down
	$(document).on('click', '.fi-move-down', function() {
		const $item = $(this).closest('.fi-address-item');
		const $next = $item.next('.fi-address-item');
		if ($next.length) {
			$item.insertAfter($next);
			renumberAddresses();
		}
	});
	
	$(document).on('click', '.fi-remove-address', function() {
		$(this).closest('.fi-address-item').remove();
		renumberAddresses();
	});
	
	function renumberAddresses() {
		const $items = $('.fi-address-item');
		$items.each(function(index) {
			const $item = $(this);
			const newIndex = index;
			
			// Update data-index
			$item.attr('data-index', newIndex);
			
			// Update address number display
			$item.find('.fi-address-number').text('Address #' + (newIndex + 1));
			
			// Update all form field names
			$item.find('input, select, textarea').each(function() {
				const $field = $(this);
				const name = $field.attr('name');
				if (name && name.startsWith('addresses[')) {
					const match = name.match(/^addresses\[\d+\](.+)$/);
					if (match) {
						$field.attr('name', 'addresses[' + newIndex + ']' + match[1]);
					}
				}
			});
		});
		addressIndex = $items.length;
		updateAddressControls();
	}
	
	function updateAddressControls() {
		const $items = $('.fi-address-item');
		const count = $items.length;
		
		$items.each(function(index) {
			const $item = $(this);
			const $upBtn = $item.find('.fi-move-up');
			const $downBtn = $item.find('.fi-move-down');
			const $deleteBtn = $item.find('.fi-remove-address');
			
			$upBtn.prop('disabled', index === 0);
			$downBtn.prop('disabled', index === count - 1);
			$deleteBtn.prop('disabled', count <= 1);
		});
	}
	
	// Website Repeater
	let websiteIndex = $('.fi-website-item').length;
	
	$('#fi-add-website').on('click', function() {
		const template = `
			<div class="fi-website-item input-group mb-2" data-index="${websiteIndex}">
				<input type="url" name="websites[${websiteIndex}]" class="form-control" placeholder="https://example.com">
				<button type="button" class="btn btn-outline-secondary fi-move-website-up" title="Move Up" disabled><i class="bi bi-arrow-up"></i> Up</button>
				<button type="button" class="btn btn-outline-secondary fi-move-website-down" title="Move Down" disabled><i class="bi bi-arrow-down"></i> Down</button>
				<button type="button" class="btn btn-outline-danger fi-remove-website" title="Delete" disabled><i class="bi bi-trash"></i> Delete</button>
			</div>
		`;
		$('#fi-websites-repeater').append(template);
		websiteIndex++;
		renumberWebsites();
	});
	
	$(document).on('click', '.fi-remove-website', function() {
		$(this).closest('.fi-website-item').remove();
		renumberWebsites();
	});
	
	$(document).on('click', '.fi-move-website-up', function() {
		const $item = $(this).closest('.fi-website-item');
		const $prev = $item.prev('.fi-website-item');
		if ($prev.length) {
			$item.insertBefore($prev);
			renumberWebsites();
		}
	});
	
	$(document).on('click', '.fi-move-website-down', function() {
		const $item = $(this).closest('.fi-website-item');
		const $next = $item.next('.fi-website-item');
		if ($next.length) {
			$item.insertAfter($next);
			renumberWebsites();
		}
	});
	
	function renumberWebsites() {
		const $items = $('.fi-website-item');
		$items.each(function(index) {
			const $item = $(this);
			$item.attr('data-index', index);
			$item.find('input[type="url"]').attr('name', `websites[${index}]`);
		});
		websiteIndex = $items.length;
		updateWebsiteControls();
	}
	
	function updateWebsiteControls() {
		const $items = $('.fi-website-item');
		const count = $items.length;
		
		$items.each(function(index) {
			const $item = $(this);
			const $upBtn = $item.find('.fi-move-website-up');
			const $downBtn = $item.find('.fi-move-website-down');
			const $deleteBtn = $item.find('.fi-remove-website');
			
			$upBtn.prop('disabled', index === 0);
			$downBtn.prop('disabled', index === count - 1);
			$deleteBtn.prop('disabled', count <= 1);
		});
	}
	
	// Initialize controls
	renumberAddresses();
	renumberWebsites();
})(jQuery);
</script>
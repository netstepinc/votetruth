<?php if ( ! defined( 'ABSPATH' ) ) { exit; }


$gov = strtoupper($_GET['gov'] ?? 'US');
$tab = $_GET['tab'] ?? 'images';
// Redirect logging tab to images (logging tab is disabled)
if ($tab === 'logging') {
	$tab = 'images';
}

// Handle disk cache clearing (uses same fi_clear_disk_cache() as merge-legislators)
if (isset($_POST['clear_disk_cache']) && wp_verify_nonce($_POST['fi_disk_cache_nonce'], 'fi_clear_disk_cache')) {
	if (!current_user_can(FI_CAP_MANAGE)) {
		return;
	}
	$result = fi_clear_disk_cache();
	if ($result['errors']) {
		add_settings_error('fi_cache', 'disk_cache_errors', "Cleared {$result['cleared']} files with errors: " . implode(', ', $result['errors']), 'error');
	} else {
		add_settings_error('fi_cache', 'disk_cache_cleared', "Successfully cleared {$result['cleared']} cache files from disk.", 'updated');
	}
}

// Handle cache clearing
if (isset($_POST['clear_cache']) && wp_verify_nonce($_POST['fi_cache_nonce'], 'fi_clear_cache')) {
	if (!current_user_can(FI_CAP_MANAGE)) {
		return;
	}
	
	if (isset($_POST['clear_all'])) {
		$governments = fi_govs();
		$cleared = 0;
		foreach (array_keys($governments) as $gov_code) {
			$key = "fi_filter_options_{$gov_code}";
			if (delete_transient($key)) {
				$cleared++;
			}
		}
		add_settings_error('fi_cache', 'cache_cleared', "Cleared {$cleared} filter option caches.", 'updated');
	} elseif (isset($_POST['clear_selected']) && !empty($_POST['clear_cache'])) {
		$cleared = 0;
		foreach ((array) $_POST['clear_cache'] as $gov_code) {
			$gov_code = strtoupper(sanitize_text_field($gov_code));
			$key = "fi_filter_options_{$gov_code}";
			if (delete_transient($key)) {
				$cleared++;
			}
		}
		add_settings_error('fi_cache', 'cache_cleared', "Cleared {$cleared} filter option caches.", 'updated');
	} elseif (isset($_POST['clear_single'])) {
		$gov_code = strtoupper(sanitize_text_field($_POST['clear_single']));
		$key = "fi_filter_options_{$gov_code}";
		if (delete_transient($key)) {
			add_settings_error('fi_cache', 'cache_cleared', "Cleared cache for {$gov_code}.", 'updated');
		}
	}
}

// Handle settings save
if ($_POST && isset($_POST['fi_settings_nonce'])) {
	if (!wp_verify_nonce($_POST['fi_settings_nonce'], 'fi_save_settings')) {
		return;
	}
	
	if (!current_user_can(FI_CAP_MANAGE)) {
		return;
	}

	$current_settings = fi_settings_all($gov);
	$current_global = fi_settings_all('US'); // Global settings (API keys, logging)

	$raw_settings = [];

	// -------------------------------------------------------------------------
	// Images (gov-specific)
	// -------------------------------------------------------------------------
	if (isset($_POST['images']) && is_array($_POST['images'])) {
		$images_current = $current_settings['images'] ?? [];
		$images_new = $images_current;

		if (isset($_POST['images']['default_size'])) {
			$images_new['default_size'] = sanitize_text_field($_POST['images']['default_size']);
		}
		if (isset($_POST['images']['fallback_image'])) {
			$images_new['fallback_image'] = absint($_POST['images']['fallback_image']);
		}

		$raw_settings['images'] = $images_new;
	}

	// -------------------------------------------------------------------------
	// API (global - stored in US)
	// -------------------------------------------------------------------------
	if (
		isset($_POST['api']) ||
		isset($_POST['api_cors_origins_json'])
	) {
		$api_current = $current_global['api'] ?? [];
		$api_new = $api_current;

		if (isset($_POST['api']['version'])) {
			$api_new['version'] = sanitize_text_field($_POST['api']['version']);
		}
		if (isset($_POST['api']['rate_limit'])) {
			$api_new['rate_limit'] = absint($_POST['api']['rate_limit']);
		}
		if (isset($_POST['api']['legiscan_key'])) {
			$api_new['legiscan_key'] = sanitize_text_field($_POST['api']['legiscan_key']);
		}
		if (isset($_POST['api']['geocod_key'])) {
			$api_new['geocod_key'] = sanitize_text_field($_POST['api']['geocod_key']);
		}
		if (isset($_POST['api']['govtrack_key'])) {
			$api_new['govtrack_key'] = sanitize_text_field($_POST['api']['govtrack_key']);
		}
		if (isset($_POST['api']['votesmart_key'])) {
			$api_new['votesmart_key'] = sanitize_text_field($_POST['api']['votesmart_key']);
		}
		if (isset($_POST['api']['openstates_key'])) {
			$api_new['openstates_key'] = sanitize_text_field($_POST['api']['openstates_key']);
		}

		// Handle CORS origins (from textarea or JSON)
		if (isset($_POST['api_cors_origins_json'])) {
			$decoded = json_decode(stripslashes($_POST['api_cors_origins_json']), true);
			if (is_array($decoded)) {
				$api_new['cors_origins'] = array_map('sanitize_text_field', $decoded);
			}
		} elseif (isset($_POST['api']['cors_origins'])) {
			if (is_array($_POST['api']['cors_origins'])) {
				$api_new['cors_origins'] = array_map('sanitize_text_field', $_POST['api']['cors_origins']);
			} else {
				$lines = explode("\n", (string) $_POST['api']['cors_origins']);
				$api_new['cors_origins'] = array_filter(array_map('trim', array_map('sanitize_text_field', $lines)));
			}
		}

		$raw_settings['api'] = $api_new;
	}

	// -------------------------------------------------------------------------
	// Logging (global - stored in US)
	// -------------------------------------------------------------------------
	if (isset($_POST['logging']) && is_array($_POST['logging'])) {
		$logging_current = $current_global['logging'] ?? [];

		$allowed_areas = [
			'general',
			'ajax',
			'rewrite',
			'filters',
			'template_loader',
			'legislators',
			'votes',
			'lists',
			'legiscan',
			'taxonomy',
			'geocod',
			'images',
			'fluentcrm',
			'cache',
		];

		$areas = [];
		foreach ($allowed_areas as $area_key) {
			$areas[$area_key] = !empty($_POST['logging']['areas'][$area_key]);
		}

		$raw_settings['logging'] = [
			'enabled' => !empty($_POST['logging']['enabled']),
			'min_level' => sanitize_text_field($_POST['logging']['min_level'] ?? ($logging_current['min_level'] ?? 'debug')),
			'areas' => $areas,
		];
	}

	$sanitized_settings = fi_settings_sanitize($raw_settings);
	$errors = fi_settings_validate($sanitized_settings);
	
	if (empty($errors)) {
		foreach ($sanitized_settings as $key => $value) {
			$target_gov = in_array($key, ['api', 'logging'], true) ? 'US' : $gov;
			fi_settings_set($target_gov, $key, $value);
		}
		
		add_settings_error('fi_settings', 'settings_saved', 'Settings saved successfully.', 'updated');
	} else {
		foreach ($errors as $error) {
			add_settings_error('fi_settings', 'settings_error', $error, 'error');
		}
	}
}

$settings = fi_settings_all($gov);
$settings_global = fi_settings_all('US');
$logging_areas = [
	'general' => 'General',
	'ajax' => 'AJAX',
	'rewrite' => 'Rewrite / Routing',
	'filters' => 'Legislator Filters',
	'template_loader' => 'Template Loader',
	'legislators' => 'Legislators',
	'votes' => 'Votes',
	'lists' => 'Lists',
	'legiscan' => 'Legiscan',
	'taxonomy' => 'Taxonomy',
	'geocod' => 'Geocod.io',
	'images' => 'Images',
	'fluentcrm' => 'FluentCRM',
	'cache' => 'Cache',
];
$sessions = fi_sessions_get_by_gov($gov);
$governments = fi_govs();
$cache_data = fi_admin_settings_get_cache_status();

settings_errors('fi_settings');
settings_errors('fi_cache');

fi_scope_render_selector();
?>
<div class="wrap">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1 class="mb-0">Settings</h1>
		<form method="get" class="d-inline-flex align-items-center gap-2">
			<input type="hidden" name="page" value="fi-settings">
			<input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">
			<label class="mb-0 fw-semibold">Government:</label>
			<select name="gov" id="fi-gov-select" class="form-select form-select-sm" style="min-width: 200px;" onchange="this.form.submit()">
				<?php foreach ($governments as $gov_code => $gov_name): ?>
					<option value="<?php echo esc_attr($gov_code); ?>" <?php selected($gov, $gov_code); ?>>
						<?php echo esc_html($gov_name); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</form>
	</div>

	<ul class="nav nav-tabs mb-4" role="tablist">
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo $tab === 'images' ? 'active' : ''; ?>" 
					id="images-tab" data-bs-toggle="tab" data-bs-target="#images" 
					type="button" role="tab" aria-controls="images" aria-selected="<?php echo $tab === 'images' ? 'true' : 'false'; ?>">
				Images
			</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo $tab === 'api' ? 'active' : ''; ?>" 
					id="api-tab" data-bs-toggle="tab" data-bs-target="#api" 
					type="button" role="tab" aria-controls="api" aria-selected="<?php echo $tab === 'api' ? 'true' : 'false'; ?>">
				API
			</button>
		</li>
		<?php /* Logging tab disabled
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo $tab === 'logging' ? 'active' : ''; ?>" 
					id="logging-tab" data-bs-toggle="tab" data-bs-target="#logging" 
					type="button" role="tab" aria-controls="logging" aria-selected="<?php echo $tab === 'logging' ? 'true' : 'false'; ?>">
				Logging
			</button>
		</li>
		*/ ?>
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo $tab === 'cache' ? 'active' : ''; ?>" 
					id="cache-tab" data-bs-toggle="tab" data-bs-target="#cache" 
					type="button" role="tab" aria-controls="cache" aria-selected="<?php echo $tab === 'cache' ? 'true' : 'false'; ?>">
				Cache Management
			</button>
		</li>
	</ul>

	<div class="tab-content">
		<!-- Images Settings -->
		<div class="tab-pane fade <?php echo $tab === 'images' ? 'show active' : ''; ?>" 
			 id="images" role="tabpanel" aria-labelledby="images-tab">
			<form method="post" class="fi-settings-form">
				<?php wp_nonce_field('fi_save_settings', 'fi_settings_nonce'); ?>
				
				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white border-0 pb-0">
						<h2 class="h4 mb-0">Image Settings</h2>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<div class="col-md-6">
								<?php fi_form_field('images[default_size]', [
									'label' => 'Default Image Size',
									'type' => 'select',
									'options' => [
										'thumbnail' => 'Thumbnail',
										'medium' => 'Medium',
										'large' => 'Large',
										'full' => 'Full'
									],
									'value' => $settings['images']['default_size'] ?? 'medium',
									'help' => 'Default size for legislator images'
								]); ?>
							</div>
							<div class="col-md-6">
								<?php fi_form_field('images[fallback_image]', [
									'label' => 'Fallback Image ID',
									'value' => $settings['images']['fallback_image'] ?? '',
									'help' => 'WordPress media library ID for default legislator image'
								]); ?>
							</div>
						</div>
					</div>
				</div>

				<div class="mb-3">
					<?php submit_button('Save Image Settings', 'primary', 'submit', false); ?>
				</div>
			</form>
<?php include FI_PUBLIC_DIR . 'admin/views/settings-image-check.php'; ?>
		</div>

		<!-- API Settings -->
		<div class="tab-pane fade <?php echo $tab === 'api' ? 'show active' : ''; ?>" 
			 id="api" role="tabpanel" aria-labelledby="api-tab">
			<form method="post" class="fi-settings-form">
				<?php wp_nonce_field('fi_save_settings', 'fi_settings_nonce'); ?>
				
				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white border-0 pb-0">
						<h2 class="h4 mb-0">API Configuration</h2>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<div class="col-md-6">
								<?php fi_form_field('api[version]', [
									'label' => 'API Version',
									'value' => $settings_global['api']['version'] ?? '1.0',
									'help' => 'API version number'
								]); ?>
							</div>
							<div class="col-md-6">
								<?php fi_form_field('api[rate_limit]', [
									'label' => 'Rate Limit',
									'type' => 'number',
									'value' => $settings_global['api']['rate_limit'] ?? 1000,
									'help' => 'Maximum API requests per hour'
								]); ?>
							</div>
							<div class="col-12">
								<hr>
								<h5 class="mb-3">API Keys</h5>
							</div>
							<div class="col-md-6">
								<?php fi_form_field('api[legiscan_key]', [
									'label' => 'Legiscan API Key',
									'type' => 'password',
									'value' => $settings_global['api']['legiscan_key'] ?? (defined('API_KEY_LEGISCAN') ? API_KEY_LEGISCAN : ''),
									'help' => 'API key for Legiscan data imports. Get your key from <a href="https://legiscan.com/gaits/documentation/legiscan" target="_blank">legiscan.com</a>',
									'attributes' => ['autocomplete' => 'new-password']
								]); ?>
							</div>
							<div class="col-md-6">
								<?php fi_form_field('api[geocod_key]', [
									'label' => 'Geocod.io API Key',
									'type' => 'password',
									'value' => $settings_global['api']['geocod_key'] ?? (defined('API_KEY_GEOCOD') ? API_KEY_GEOCOD : ''),
									'help' => 'API key for Geocod.io geocoding service',
									'attributes' => ['autocomplete' => 'new-password']
								]); ?>
							</div>
							<div class="col-md-6">
								<?php fi_form_field('api[govtrack_key]', [
									'label' => 'GovTrack API Key',
									'type' => 'password',
									'value' => $settings_global['api']['govtrack_key'] ?? (defined('API_KEY_GOVTRACK') ? API_KEY_GOVTRACK : ''),
									'help' => 'API key for GovTrack.us service',
									'attributes' => ['autocomplete' => 'new-password']
								]); ?>
							</div>
							<div class="col-md-6">
								<?php fi_form_field('api[votesmart_key]', [
									'label' => 'VoteSmart API Key',
									'type' => 'password',
									'value' => $settings_global['api']['votesmart_key'] ?? (defined('API_KEY_VOTESMART') ? API_KEY_VOTESMART : ''),
									'help' => 'API key for VoteSmart.org service',
									'attributes' => ['autocomplete' => 'new-password']
								]); ?>
							</div>
							<div class="col-md-6">
								<?php fi_form_field('api[openstates_key]', [
									'label' => 'OpenStates API Key',
									'type' => 'password',
									'value' => $settings_global['api']['openstates_key'] ?? (defined('API_KEY_OPENSTATES') ? API_KEY_OPENSTATES : ''),
									'help' => 'API key for OpenStates.org service',
									'attributes' => ['autocomplete' => 'new-password']
								]); ?>
							</div>
							<div class="col-12">
								<hr>
								<h5 class="mb-3">CORS Configuration</h5>
							</div>
							<div class="col-12">
								<?php 
								$cors_value = is_array($settings_global['api']['cors_origins'] ?? []) 
									? implode("\n", $settings_global['api']['cors_origins']) 
									: '';
								fi_form_field('api[cors_origins]', [
									'label' => 'CORS Origins',
									'type' => 'textarea',
									'value' => $cors_value,
									'help' => 'Allowed CORS origins (one per line)',
									'attributes' => ['rows' => 5]
								]);
								?>
							</div>
						</div>
					</div>
				</div>

				<div class="mb-3">
					<?php submit_button('Save API Settings', 'primary', 'submit', false); ?>
				</div>
			</form>
		</div>

		<?php /* Logging Settings tab disabled
		<div class="tab-pane fade <?php echo $tab === 'logging' ? 'show active' : ''; ?>"
			 id="logging" role="tabpanel" aria-labelledby="logging-tab">
			<form method="post" class="fi-settings-form">
				<?php wp_nonce_field('fi_save_settings', 'fi_settings_nonce'); ?>

				<?php
				$logging = $settings_global['logging'] ?? [];
				$logging_enabled = !empty($logging['enabled']);
				$logging_min_level = $logging['min_level'] ?? 'debug';
				$logging_area_values = is_array($logging['areas'] ?? null) ? $logging['areas'] : [];
				?>

				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white border-0 pb-0">
						<h2 class="h4 mb-0">Logging</h2>
					</div>
					<div class="card-body">
						<p class="text-muted mb-3">
							Logging is global across all governments. Enable only what you need while debugging.
						</p>

						<div class="row g-3">
							<div class="col-12">
								<div class="form-check">
									<input class="form-check-input" type="checkbox" id="fi-logging-enabled" name="logging[enabled]" value="1" <?php checked($logging_enabled); ?>>
									<label class="form-check-label fw-semibold" for="fi-logging-enabled">Enable Freedom Index logging</label>
								</div>
							</div>

							<div class="col-md-6">
								<label for="fi-logging-min-level" class="form-label fw-semibold">Minimum level</label>
								<select id="fi-logging-min-level" name="logging[min_level]" class="form-select">
									<option value="debug" <?php selected($logging_min_level, 'debug'); ?>>Debug</option>
									<option value="info" <?php selected($logging_min_level, 'info'); ?>>Info</option>
									<option value="warning" <?php selected($logging_min_level, 'warning'); ?>>Warning</option>
									<option value="error" <?php selected($logging_min_level, 'error'); ?>>Error</option>
								</select>
								<div class="form-text">Lower levels are more verbose.</div>
							</div>

							<div class="col-12">
								<hr>
								<h5 class="mb-2">Areas</h5>
								<div class="row g-2">
									<?php foreach ($logging_areas as $area_key => $area_label): ?>
										<?php
										$checked = !empty($logging_area_values[$area_key]);
										$input_id = 'fi-logging-area-' . $area_key;
										?>
										<div class="col-12 col-md-6 col-lg-4">
											<div class="form-check">
												<input class="form-check-input" type="checkbox"
													   id="<?php echo esc_attr($input_id); ?>"
													   name="logging[areas][<?php echo esc_attr($area_key); ?>]"
													   value="1"
													   <?php checked($checked); ?>>
												<label class="form-check-label" for="<?php echo esc_attr($input_id); ?>">
													<?php echo esc_html($area_label); ?>
												</label>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="mb-3">
					<?php submit_button('Save Logging Settings', 'primary', 'submit', false); ?>
				</div>
			</form>
		</div>
		*/ ?>

		<!-- Cache Management -->
		<div class="tab-pane fade <?php echo $tab === 'cache' ? 'show active' : ''; ?>" 
			 id="cache" role="tabpanel" aria-labelledby="cache-tab">
			
			<!-- Disk Cache Clearing -->
			<div class="mb-3">
				<form method="post" id="fi-disk-cache-form" onsubmit="return confirm('Are you sure you want to clear all disk cache files? This will delete cached data for findmy, ajax, legislators, reports, rollcalls, search, sessions, taxonomy, votes, and user directories.');">
					<?php wp_nonce_field('fi_clear_disk_cache', 'fi_disk_cache_nonce'); ?>
					<button type="submit" name="clear_disk_cache" value="1" class="btn btn-danger">
						Clear Disk Cache
					</button>
				</form>
			</div>
			
			<div class="card mb-3">
				<div class="card-body">
					<h5 class="card-title">Filter Options Cache</h5>
					<p class="text-muted mb-3">
						Clear cached filter options. Useful after elections or data migrations.
					</p>
					
					<form method="post" id="fi-cache-form">
						<?php wp_nonce_field('fi_clear_cache', 'fi_cache_nonce'); ?>
						
						<div class="table-responsive">
							<table class="table table-striped table-hover">
								<thead>
									<tr>
										<th style="width: 50px;">
											<input type="checkbox" id="select-all-cache" class="form-check-input">
										</th>
										<th>Government</th>
										<th>Cache Status</th>
										<th>Actions</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($cache_data as $gov_code => $cache_info): ?>
										<tr>
											<td>
												<input type="checkbox" name="clear_cache[]" 
													   value="<?php echo esc_attr($gov_code); ?>" 
													   class="form-check-input cache-checkbox">
											</td>
											<td>
												<strong><?php echo esc_html($cache_info['name']); ?></strong>
												<small class="text-muted d-block"><?php echo esc_html($gov_code); ?></small>
											</td>
											<td>
												<?php if ($cache_info['exists']): ?>
													<span class="badge bg-success">Cached</span>
												<?php else: ?>
													<span class="badge bg-secondary">Not Cached</span>
												<?php endif; ?>
											</td>
											<td>
												<?php if ($cache_info['exists']): ?>
													<button type="submit" name="clear_single" 
															value="<?php echo esc_attr($gov_code); ?>" 
															class="btn btn-sm btn-outline-danger">
														Clear
													</button>
												<?php else: ?>
													<span class="text-muted">—</span>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						
						<div class="d-flex gap-2 mt-3">
							<button type="submit" name="clear_selected" class="btn btn-secondary">
								Clear Selected
							</button>
							<button type="submit" name="clear_all" class="btn btn-danger" 
									onclick="return confirm('Clear all filter option caches? This cannot be undone.');">
								Clear All
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
(function($) {
	// Handle tab navigation with URL updates
	$('.nav-tabs button').on('shown.bs.tab', function(e) {
		const tabId = $(e.target).attr('data-bs-target').replace('#', '');
		const url = new URL(window.location);
		url.searchParams.set('tab', tabId);
		window.history.pushState({}, '', url);
	});

	// Select all checkbox functionality
	$('#select-all-cache').on('change', function() {
		$('.cache-checkbox').prop('checked', this.checked);
	});

	// Handle CORS origins textarea (convert to array on submit)
	$('form').on('submit', function() {
		const corsTextarea = $('textarea[name="api[cors_origins]"]');
		if (corsTextarea.length) {
			const lines = corsTextarea.val().split('\n').filter(line => line.trim());
			// Store as JSON in hidden field for processing
			const hidden = $('<input>').attr({
				type: 'hidden',
				name: 'api_cors_origins_json',
				value: JSON.stringify(lines)
			});
			$(this).append(hidden);
		}
	});
})(jQuery);
</script>

<?php if ( ! defined( 'ABSPATH' ) ) { exit; }


$scope = function_exists('fi_scope_get_current') ? fi_scope_get_current() : ['gov' => 'US'];
$gov = strtoupper((string) ($scope['gov'] ?? 'US'));
$tab = $_GET['tab'] ?? 'cache';
// Redirect removed/disabled tabs to cache
if (in_array($tab, ['images', 'api', 'logging'], true)) {
	$tab = 'cache';
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
	</div>

	<ul class="nav nav-tabs mb-4" role="tablist">
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo $tab === 'cache' ? 'active' : ''; ?>" 
					id="cache-tab" data-bs-toggle="tab" data-bs-target="#cache" 
					type="button" role="tab" aria-controls="cache" aria-selected="<?php echo $tab === 'cache' ? 'true' : 'false'; ?>">
				Cache Management
			</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo $tab === 'data' ? 'active' : ''; ?>" 
					id="data-tab" data-bs-toggle="tab" data-bs-target="#data" 
					type="button" role="tab" aria-controls="data" aria-selected="<?php echo $tab === 'data' ? 'true' : 'false'; ?>">
				Legislator Cache
			</button>
		</li>
	</ul>

	<div class="tab-content">

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

		<div class="tab-pane fade <?php echo $tab === 'data' ? 'show active' : ''; ?>"
		     id="data" role="tabpanel" aria-labelledby="data-tab">
			<p id="fi-rebuild-status" class="description" style="margin:8px 0;"></p>
			<table class="wp-list-table widefat fixed striped" style="font-size:12px;">
				<thead>
					<tr>
						<th style="width:70px;">ID</th>
						<th>Name</th>
						<th>Cached Values</th>
					</tr>
				</thead>
				<tbody>
				<?php
				global $wpdb;
				$all_legs = $wpdb->get_results(
					"SELECT id, display_name, gov, state, chamber, district, party, session_id FROM {$wpdb->prefix}fi_legislators ORDER BY id ASC",
					ARRAY_A
				);
				foreach ($all_legs as $leg):
					$cached = implode(' | ', array_filter([
						$leg['gov'],
						$leg['state'],
						$leg['chamber'],
						$leg['district'],
						$leg['party'],
						$leg['session_id'] ? 'session:' . $leg['session_id'] : '',
					]));
				?>
				<tr>
					<td><?php echo (int) $leg['id']; ?></td>
					<td><?php echo esc_html($leg['display_name']); ?></td>
					<td id="fi-row-<?php echo (int) $leg['id']; ?>"><code><?php echo esc_html($cached ?: '—'); ?></code></td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

	</div><!-- /.tab-content -->
</div><!-- /.wrap -->

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

	// -------------------------------------------------------------------------
	// Legislator cache rebuild — one-pass, ID-driven, concurrent batches
	// -------------------------------------------------------------------------
	(function() {
		var nonce          = <?php echo wp_json_encode(wp_create_nonce('fi_admin_nonce')); ?>;
		var BATCH_SIZE     = 50;
		var MAX_CONCURRENT = 3;
		var started        = false;
		var queue          = [];   // all pending IDs
		var total          = 0;
		var done           = 0;
		var active         = 0;

		function setStatus(msg) {
			var el = document.getElementById('fi-rebuild-status');
			if (el) el.textContent = msg;
		}

		function updateCell(r) {
			var cell = document.getElementById('fi-row-' + r.id);
			if (!cell) return;
			if (r.status === 'ok') {
				cell.innerHTML = '<code>' + r.cached + '</code>';
			} else if (r.status === 'skip') {
				cell.innerHTML = '<em style="color:#999;font-size:11px;">no session assignments</em>';
			} else {
				cell.innerHTML = '<em style="color:#c00;font-size:11px;">error</em>';
			}
		}

		function processBatch(ids) {
			active++;
			var body = 'action=fi_admin_action&sub_action=sync_legislators_by_ids&nonce=' + nonce;
			ids.forEach(function(id) { body += '&ids[]=' + id; });

			var xhr = new XMLHttpRequest();
			xhr.open('POST', ajaxurl, true);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			xhr.onload = function() {
				active--;
				var res;
				try { res = JSON.parse(xhr.responseText); } catch(e) {
					setStatus('Error: bad response'); pump(); return;
				}
				if (res.success && res.data.results) {
					res.data.results.forEach(function(r) {
						done++;
						updateCell(r);
					});
					setStatus('Rebuilding: ' + done + ' of ' + total);
				}
				if (done >= total) setStatus('✓ Complete — ' + done + ' legislators processed.');
				else pump();
			};
			xhr.onerror = function() { active--; setStatus('Network error'); pump(); };
			xhr.send(body);
		}

		function pump() {
			while (active < MAX_CONCURRENT && queue.length > 0) {
				processBatch(queue.splice(0, BATCH_SIZE));
			}
		}

		function startRebuild() {
			if (started) return;
			started = true;
			setStatus('Purging cached data…');

			var xhr = new XMLHttpRequest();
			xhr.open('POST', ajaxurl, true);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			xhr.onload = function() {
				var res;
				try { res = JSON.parse(xhr.responseText); } catch(e) {
					setStatus('Purge error: bad response'); return;
				}
				if (!res.success) { setStatus('Purge failed: ' + (res.data || 'unknown')); return; }

				// Collect all row IDs from the DOM now that the purge is done
				queue = Array.from(document.querySelectorAll('#data tbody tr')).map(function(tr) {
					return parseInt(tr.querySelector('td').textContent, 10);
				}).filter(function(id) { return id > 0; });

				total = queue.length;
				setStatus('Purged. Rebuilding ' + total + ' legislators…');
				pump();
			};
			xhr.onerror = function() { setStatus('Network error on purge'); };
			xhr.send('action=fi_admin_action&sub_action=purge_cached_sessions&nonce=' + nonce);
		}

		// Fire immediately if data tab is already active, or when it gets shown
		if (document.querySelector('#data.active')) {
			startRebuild();
		}
		document.getElementById('data-tab').addEventListener('shown.bs.tab', startRebuild);
	})();

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

<?php if (!defined('ABSPATH')) { exit; }

function fi_admin_ajax_log(string $message, $file='', int $line=0, string $level = 'info'): void {
	//fi_log($message, $file, $line, $level);
}


/**
 * Handle AJAX actions
 */
function fi_admin_ajax_handle(): void {
	// Summary: avoid wp_die() on nonce failure so we can return JSON + log diagnostics.
	$nonce = (string) ($_POST['nonce'] ?? '');
	if ($nonce === '' || !wp_verify_nonce($nonce, 'fi_admin_nonce')) {
		if (function_exists('fi_log')) {
			fi_admin_ajax_log('AJAX fi_admin_action invalid nonce | action=' . (string) ($_POST['action'] ?? '') . ' | sub_action=' . (string) ($_POST['sub_action'] ?? ''), __FILE__, __LINE__, 'error');
		}
		wp_send_json_error(['message' => 'Invalid nonce']);
	}
	
	if (!current_user_can(FI_CAP_MANAGE)) {
		if (function_exists('fi_log')) {
			fi_admin_ajax_log('AJAX fi_admin_action insufficient permissions | action=' . (string) ($_POST['action'] ?? '') . ' | sub_action=' . (string) ($_POST['sub_action'] ?? ''), __FILE__, __LINE__, 'error');
		}
		wp_send_json_error('Insufficient permissions');
	}
	
	$action = $_POST['action'] ?? '';
	
	switch ($action) {
		case 'fi_admin_action':
			$sub_action = $_POST['sub_action'] ?? '';
			fi_admin_ajax_handle_sub_action($sub_action);
			break;
	}
}
add_action('wp_ajax_fi_admin_action', 'fi_admin_ajax_handle');

/**
 * Dedicated AJAX log endpoint (client-side breadcrumbs).
 *
 * Summary:
 * - Allows JS to log "before request" / "after response" markers into FI log.
 * - Requires admin nonce + FI manage capability.
 */
function fi_ajax_log_endpoint(): void {
	$nonce = (string) ($_POST['nonce'] ?? '');
	if ($nonce === '' || !wp_verify_nonce($nonce, 'fi_admin_nonce')) {
		// Log even when nonce fails so we can confirm whether the request hit WordPress at all.
		fi_ajax_log('fi_ajax_log invalid nonce', [
			'event' => sanitize_text_field((string) ($_POST['event'] ?? '')),
			'page' => sanitize_text_field((string) ($_POST['page'] ?? '')),
			'rid' => sanitize_text_field((string) ($_POST['rid'] ?? '')),
		], 'error', __FILE__, __LINE__);
		wp_send_json_error(['message' => 'Invalid nonce']);
	}
	if (!current_user_can(FI_CAP_MANAGE)) {
		fi_ajax_log('fi_ajax_log insufficient permissions', [
			'event' => sanitize_text_field((string) ($_POST['event'] ?? '')),
			'page' => sanitize_text_field((string) ($_POST['page'] ?? '')),
			'rid' => sanitize_text_field((string) ($_POST['rid'] ?? '')),
		], 'error', __FILE__, __LINE__);
		wp_send_json_error(['message' => 'Insufficient permissions']);
	}

	$msg = sanitize_text_field((string) ($_POST['message'] ?? ''));
	$rid = sanitize_text_field((string) ($_POST['rid'] ?? ''));
	$data = $_POST['data'] ?? null;
	if (is_string($data)) {
		$decoded = json_decode(wp_unslash($data), true);
		if (is_array($decoded)) {
			$data = $decoded;
		}
	}
	if (!is_array($data)) {
		$data = [];
	}

	$context = [
		'rid' => $rid,
		'user_id' => get_current_user_id(),
		'page' => sanitize_text_field((string) ($_POST['page'] ?? '')),
		'event' => sanitize_text_field((string) ($_POST['event'] ?? '')),
		'data' => $data,
	];

	//fi_ajax_log($msg !== '' ? $msg : 'client', $context, 'debug', __FILE__, __LINE__);
	wp_send_json_success(['ok' => true]);
}
add_action('wp_ajax_fi_ajax_log', 'fi_ajax_log_endpoint');

/**
 * Handle AJAX sub-actions
 */
function fi_admin_ajax_handle_sub_action(string $sub_action): void {
	switch ($sub_action) {
			case 'fetch_legislator_image_url':
				if (function_exists('fi_log')) {
					fi_admin_ajax_log('AJAX fetch_legislator_image_url start | legislator_id=' . (string) ($_POST['legislator_id'] ?? '') . ' | url=' . (string) ($_POST['url'] ?? ''), __FILE__, __LINE__, 'debug');
				}
				$legislator_id = absint($_POST['legislator_id'] ?? 0);
				$url = esc_url_raw((string) ($_POST['url'] ?? ''));
				if ($legislator_id <= 0) {
					if (function_exists('fi_log')) { fi_admin_ajax_log('AJAX fetch_legislator_image_url error: Missing legislator_id', __FILE__, __LINE__, 'error'); }
					wp_send_json_error('Missing legislator_id');
				}
				if ($url === '' || !preg_match('#^https?://#i', $url)) {
					if (function_exists('fi_log')) { fi_admin_ajax_log('AJAX fetch_legislator_image_url error: Invalid URL ' . $url, __FILE__, __LINE__, 'error'); }
					wp_send_json_error('Invalid URL');
				}

				$dest_dir = defined('FI_PATH_IMAGES') ? (string) FI_PATH_IMAGES : '';
				$dest_url_base = defined('FI_URL_IMAGES') ? (string) FI_URL_IMAGES : '';
				$dest_dir = rtrim($dest_dir, "/\\") . DIRECTORY_SEPARATOR;
				if ($dest_dir === DIRECTORY_SEPARATOR || $dest_dir === '') {
					if (function_exists('fi_log')) { fi_admin_ajax_log('AJAX fetch_legislator_image_url error: FI_PATH_IMAGES not configured', __FILE__, __LINE__, 'error'); }
					wp_send_json_error('Local image directory is not configured');
				}
				if (!is_dir($dest_dir)) {
					wp_mkdir_p($dest_dir);
				}
				if (!is_dir($dest_dir) || !is_writable($dest_dir)) {
					if (function_exists('fi_log')) { fi_admin_ajax_log('AJAX fetch_legislator_image_url error: dest_dir not writable ' . $dest_dir, __FILE__, __LINE__, 'error'); }
					wp_send_json_error('Local image directory is not writable');
				}

				// Determine basename/ext
				$path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
				$base = sanitize_file_name(basename($path));
				$ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
				if ($base === '' || $base === '.' || $base === '/') {
					$base = 'image.jpg';
					$ext = 'jpg';
				}
				if ($ext === '') {
					$ext = 'jpg';
					$base .= '.jpg';
				}
				$allowed = ['jpg','jpeg','png','webp','gif'];
				if (!in_array($ext, $allowed, true)) {
					// Unknown extension; default to jpg for storage.
					$ext = 'jpg';
					$base = preg_replace('/\.[a-z0-9]+$/i', '', $base) . '.jpg';
				}

				$prefixed = sanitize_file_name($legislator_id . '_' . $base);
				if ($prefixed === '' || strpos($prefixed, (string) $legislator_id . '_') !== 0) {
					$prefixed = $legislator_id . '_image.' . $ext;
				}
				$dest_abs = $dest_dir . $prefixed;

				// Download remote bytes
				$res = wp_safe_remote_get($url, [
					'timeout' => 30,
					'redirection' => 5,
					'user-agent' => 'FreedomIndex/AdminImageFetch; ' . home_url('/'),
				]);
				if (is_wp_error($res)) {
					if (function_exists('fi_log')) { fi_admin_ajax_log('AJAX fetch_legislator_image_url wp_error: ' . $res->get_error_message(), __FILE__, __LINE__, 'error'); }
					wp_send_json_error($res->get_error_message());
				}
				$status = (int) wp_remote_retrieve_response_code($res);
				$body = (string) wp_remote_retrieve_body($res);
				if ($status < 200 || $status >= 300 || $body === '') {
					if (function_exists('fi_log')) { fi_admin_ajax_log('AJAX fetch_legislator_image_url error: HTTP ' . $status, __FILE__, __LINE__, 'error'); }
					wp_send_json_error('Failed to fetch image (HTTP ' . $status . ')');
				}

				// Save/overwrite local repo file
				if (file_exists($dest_abs)) {
					@unlink($dest_abs);
				}
				if (@file_put_contents($dest_abs, $body) === false) {
					if (function_exists('fi_log')) { fi_admin_ajax_log('AJAX fetch_legislator_image_url error: Failed to save image file ' . $dest_abs, __FILE__, __LINE__, 'error'); }
					wp_send_json_error('Failed to save image file');
				}

				// Register attachment in place (no copying into uploads).
				$img = \FI\Core\media_import_local_image($dest_abs, $prefixed, [
					'copy_into_uploads' => false,
					'generate_thumbnail' => true,
					'public_url_base' => $dest_url_base,
					'overwrite_basename' => $prefixed,
					'desc' => 'Legislator image fetch (legislator_id=' . $legislator_id . ')',
					'meta' => [
						'fi_source' => 'url_fetch',
						'fi_source_url' => $url,
						'fi_legislator_id' => $legislator_id,
					],
				]);
				$att_id = (int) ($img['id'] ?? 0);
				if ($att_id <= 0) {
					if (function_exists('fi_log')) { fi_admin_ajax_log('AJAX fetch_legislator_image_url error: Failed to register attachment ' . wp_json_encode($img), __FILE__, __LINE__, 'error'); }
					wp_send_json_error([
						'message' => 'Failed to register image attachment',
						'error' => $img['error'] ?? null,
					]);
				}

				$ok = \FI\Core\Legislators::save(['image_id' => $att_id], $legislator_id);
				if (!$ok) {
					if (function_exists('fi_log')) { fi_admin_ajax_log('AJAX fetch_legislator_image_url error: Failed updating legislator image_id (legislator_id=' . $legislator_id . ', attachment_id=' . $att_id . ')', __FILE__, __LINE__, 'error'); }
					wp_send_json_error('Failed updating legislator image_id');
				}

				// Prefer an explicit thumbnail URL to avoid issues with "in place" attachments.
				$preview = wp_get_attachment_image_url($att_id, 'thumbnail')
					?: wp_get_attachment_url($att_id)
					?: ($dest_url_base ? (rtrim($dest_url_base, '/') . '/' . $prefixed) : '');
				if (function_exists('fi_log')) {
					fi_admin_ajax_log('AJAX fetch_legislator_image_url success | legislator_id=' . $legislator_id . ' | attachment_id=' . $att_id . ' | file=' . $prefixed, __FILE__, __LINE__, 'debug');
				}
				wp_send_json_success([
					'legislator_id' => $legislator_id,
					'attachment_id' => $att_id,
					'filename' => $prefixed,
					'url' => $preview,
				]);
				break;

			case 'upload_legislator_image':
				if (function_exists('fi_log')) {
					fi_admin_ajax_log('AJAX upload_legislator_image start | legislator_id=' . (string) ($_POST['legislator_id'] ?? '') . ' | file=' . (string) ($_FILES['file']['name'] ?? ''), __FILE__, __LINE__, 'debug');
				}
				$legislator_id = absint($_POST['legislator_id'] ?? 0);
				if ($legislator_id <= 0) {
					if (function_exists('fi_log')) { fi_admin_ajax_log('AJAX upload_legislator_image error: Missing legislator_id', __FILE__, __LINE__, 'error'); }
					wp_send_json_error('Missing legislator_id');
				}
				if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
					if (function_exists('fi_log')) { fi_admin_ajax_log('AJAX upload_legislator_image error: Missing upload file', __FILE__, __LINE__, 'error'); }
					wp_send_json_error('Missing upload file');
				}

				$file = $_FILES['file'];
				if (!empty($file['error'])) {
					wp_send_json_error('Upload error: ' . (int) $file['error']);
				}
				$tmp_name = (string) ($file['tmp_name'] ?? '');
				if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
					if (function_exists('fi_log')) { fi_admin_ajax_log('AJAX upload_legislator_image error: Invalid uploaded file', __FILE__, __LINE__, 'error'); }
					wp_send_json_error('Invalid uploaded file');
				}

				// Validate extension/mime quickly (defense-in-depth).
				$orig_name = sanitize_file_name((string) ($file['name'] ?? 'image.jpg'));
				$ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
				if ($ext === '') $ext = 'jpg';
				$allowed = ['jpg','jpeg','png','webp','gif'];
				if (!in_array($ext, $allowed, true)) {
					wp_send_json_error('Unsupported image type');
				}

				$dest_dir = defined('FI_PATH_IMAGES') ? (string) FI_PATH_IMAGES : '';
				$dest_url_base = defined('FI_URL_IMAGES') ? (string) FI_URL_IMAGES : '';
				$dest_dir = rtrim($dest_dir, "/\\") . DIRECTORY_SEPARATOR;
				if ($dest_dir === DIRECTORY_SEPARATOR || $dest_dir === '') {
					if (function_exists('fi_log')) { fi_admin_ajax_log('AJAX upload_legislator_image error: FI_PATH_IMAGES not configured', __FILE__, __LINE__, 'error'); }
					wp_send_json_error('Local image directory is not configured');
				}
				if (!is_dir($dest_dir)) {
					wp_mkdir_p($dest_dir);
				}
				if (!is_dir($dest_dir) || !is_writable($dest_dir)) {
					if (function_exists('fi_log')) { fi_admin_ajax_log('AJAX upload_legislator_image error: dest_dir not writable ' . $dest_dir, __FILE__, __LINE__, 'error'); }
					wp_send_json_error('Local image directory is not writable');
				}

				$prefixed = $legislator_id . '_' . $orig_name;
				$prefixed = sanitize_file_name($prefixed);
				if ($prefixed === '' || strpos($prefixed, (string) $legislator_id . '_') !== 0) {
					$prefixed = $legislator_id . '_image.' . $ext;
				}

				$dest_abs = $dest_dir . $prefixed;
				// Overwrite if already present.
				if (file_exists($dest_abs)) {
					@unlink($dest_abs);
				}
				if (!@move_uploaded_file($tmp_name, $dest_abs)) {
					// Fallback to copy+unlink for hosts where rename fails.
					if (!@copy($tmp_name, $dest_abs)) {
						if (function_exists('fi_log')) { fi_admin_ajax_log('AJAX upload_legislator_image error: Failed to save uploaded file to ' . $dest_abs, __FILE__, __LINE__, 'error'); }
						wp_send_json_error('Failed to save uploaded file');
					}
					@unlink($tmp_name);
				}

				// Register attachment in place (no copying into uploads).
				$img = \FI\Core\media_import_local_image($dest_abs, $prefixed, [
					'copy_into_uploads' => false,
					'generate_thumbnail' => true,
					'public_url_base' => $dest_url_base,
					'overwrite_basename' => $prefixed,
					'desc' => 'Legislator image upload (legislator_id=' . $legislator_id . ')',
					'meta' => [
						'fi_source' => 'manual_upload',
						'fi_legislator_id' => $legislator_id,
					],
				]);
				$att_id = (int) ($img['id'] ?? 0);
				if ($att_id <= 0) {
					if (function_exists('fi_log')) { fi_admin_ajax_log('AJAX upload_legislator_image error: Failed to register attachment ' . wp_json_encode($img), __FILE__, __LINE__, 'error'); }
					wp_send_json_error([
						'message' => 'Failed to register image attachment',
						'error' => $img['error'] ?? null,
					]);
				}

				// Update legislator image_id immediately.
				$ok = \FI\Core\Legislators::save(['image_id' => $att_id], $legislator_id);
				if (!$ok) {
					if (function_exists('fi_log')) { fi_admin_ajax_log('AJAX upload_legislator_image error: Failed updating legislator image_id (legislator_id=' . $legislator_id . ', attachment_id=' . $att_id . ')', __FILE__, __LINE__, 'error'); }
					wp_send_json_error('Failed updating legislator image_id');
				}

				// Prefer an explicit thumbnail URL to avoid issues with "in place" attachments.
				$preview = wp_get_attachment_image_url($att_id, 'thumbnail')
					?: wp_get_attachment_url($att_id)
					?: ($dest_url_base ? (rtrim($dest_url_base, '/') . '/' . $prefixed) : '');
				if (function_exists('fi_log')) {
					fi_admin_ajax_log('AJAX upload_legislator_image success | legislator_id=' . $legislator_id . ' | attachment_id=' . $att_id . ' | file=' . $prefixed, __FILE__, __LINE__, 'debug');
				}
				wp_send_json_success([
					'legislator_id' => $legislator_id,
					'attachment_id' => $att_id,
					'filename' => $prefixed,
					'url' => $preview,
				]);
				break;

			case 'fetch_remote_image':
				$url = esc_url_raw((string) ($_POST['url'] ?? ''));
				$preferred = sanitize_file_name((string) ($_POST['preferred_filename'] ?? ''));
				$overwrite = sanitize_file_name((string) ($_POST['overwrite_basename'] ?? ''));

				if ($url === '' || !preg_match('#^https?://#i', $url)) {
					wp_send_json_error('Invalid URL');
				}
				if ($preferred === '') {
					$preferred = basename((string) (parse_url($url, PHP_URL_PATH) ?: 'remote.jpg'));
					$preferred = sanitize_file_name($preferred);
					if ($preferred === '') {
						$preferred = 'remote.jpg';
					}
				}

				$img = \FI\Core\media_sideload_image_from_url($url, $preferred, [
					'desc' => 'Freedom Index remote image fetch',
					'attach_post_id' => 0,
					'overwrite_basename' => $overwrite !== '' ? $overwrite : null,
					'meta' => [
						'fi_source_url' => $url,
					],
				]);
				$att_id = (int) ($img['id'] ?? 0);
				if ($att_id <= 0) {
					wp_send_json_error([
						'message' => 'Image fetch failed',
						'url' => $url,
						'error' => $img['error'] ?? null,
					]);
				}

				wp_send_json_success([
					'attachment_id' => $att_id,
					'url' => $url,
				]);
				break;

			case 'process_legacy_legislator_images':
				global $wpdb;

				$limit = absint($_POST['limit'] ?? 1);
				if ($limit <= 0) $limit = 1;
				if ($limit > 25) $limit = 25;

				$processed = 0;
				$updated = 0;
				$skipped = 0;
				$errors = [];

				$local_dir = defined('FI_PATH_IMAGES') ? (string) FI_PATH_IMAGES : '';
				$local_dir = rtrim($local_dir, "/\\");
				$public_url_base = defined('FI_URL_IMAGES') ? (string) FI_URL_IMAGES : '';

				while ($processed < $limit) {
					$row = $wpdb->get_row(
						"SELECT id, legacy_id, legacy_image_url, image_id, display_name
						 FROM {$wpdb->prefix}fi_legislators
						 WHERE legacy_image_url IS NOT NULL
						   AND legacy_image_url != ''
						   AND legacy_image_url NOT LIKE 'MISSING:%'
						 ORDER BY id ASC
						 LIMIT 1",
						ARRAY_A
					);

					if (!$row) {
						break;
					}

					$leg_id = (int) ($row['id'] ?? 0);
					$legacy_id = (string) ($row['legacy_id'] ?? '');
					$url = trim((string) ($row['legacy_image_url'] ?? ''));
					$image_id = (int) ($row['image_id'] ?? 0);
					$display_name = trim((string) ($row['display_name'] ?? ''));

					// Summary: common context for troubleshooting. Included in returned errors and fi_log entries.
					$err_ctx = [
						'legislator_id' => $leg_id,
						'legacy_id' => $legacy_id,
						'display_name' => $display_name,
						'legacy_image_url' => $url,
					];

					// If image already exists, clear legacy_image_url so we don't retry.
					if ($image_id > 0) {
						$wpdb->update(
							$wpdb->prefix . 'fi_legislators',
							['legacy_image_url' => null],
							['id' => $leg_id],
							['%s'],
							['%d']
						);
						$processed++;
						$skipped++;
						continue;
					}

					$gov = '';
					if ($legacy_id !== '' && preg_match('/^([A-Z]{2})-/', strtoupper($legacy_id), $m)) {
						$gov = $m[1];
					}
					if ($gov === '') {
						$e = $err_ctx + ['stage' => 'derive_gov', 'error' => 'Cannot derive gov from legacy_id'];
						$errors[] = $e;
						if (function_exists('fi_log')) fi_admin_ajax_log('Legacy image import error: ' . json_encode($e), __FILE__, __LINE__, 'error');
						$processed++;
						// Don't clear the URL; allow manual fix and retry.
						continue;
					}

					if ($url === '' || !preg_match('#^https?://#i', $url)) {
						$e = $err_ctx + ['stage' => 'validate_url', 'error' => 'Invalid legacy_image_url'];
						$errors[] = $e;
						if (function_exists('fi_log')) fi_admin_ajax_log('Legacy image import error: ' . json_encode($e), __FILE__, __LINE__, 'error');
						$processed++;
						continue;
					}

					$path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
					$basename = sanitize_file_name(basename($path));
					if ($basename === '') {
						$basename = 'image.jpg';
					}
					if (!preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $basename)) {
						$basename .= '.jpg';
					}

					if ($local_dir === '' || !is_dir($local_dir) || !is_writable($local_dir)) {
						$e = $err_ctx + ['stage' => 'local_dir', 'error' => 'Local image directory not configured/writable (FI_PATH_IMAGES)', 'local_dir' => $local_dir];
						$errors[] = $e;
						if (function_exists('fi_log')) fi_admin_ajax_log('Legacy image import error: ' . json_encode($e), __FILE__, __LINE__, 'error');
						$processed++;
						continue;
					}

					// Destination filename (deterministic):
					// - Stored in: FI_PATH_IMAGES (typically /assets/sites/{blog_id}/fi/)
					// - Name: {fi_legislator_id}-{original-file-name} (your preference; avoids collisions cleanly)
					$dest_name = sanitize_file_name((string) $leg_id . '-' . $basename);
					if ($dest_name === '') {
						$dest_name = (string) $leg_id . '-image.jpg';
					}
					$dest_abs = $local_dir . DIRECTORY_SEPARATOR . $dest_name;

					// Source file preference:
					// 1) {legislator_id}-{basename} (manual uploads; preferred)
					// 2) {legislator_id}_{basename} (legacy manual uploads)
					// 3) {gov}_{legislator_id}_{basename} (older importer output)
					// 3) {basename} (raw copied images) -> copy to dest
					$src_candidates = [
						$local_dir . DIRECTORY_SEPARATOR . sanitize_file_name($leg_id . '-' . $basename),
						$local_dir . DIRECTORY_SEPARATOR . sanitize_file_name($leg_id . '_' . $basename),
						$local_dir . DIRECTORY_SEPARATOR . sanitize_file_name($gov . '_' . $leg_id . '_' . $basename),
						$local_dir . DIRECTORY_SEPARATOR . $basename,
					];

					$have_local = false;
					$src_used = '';
					foreach ($src_candidates as $src) {
						if ($src !== '' && file_exists($src)) {
							$src_used = (string) $src;
							// Summary: do NOT copy legacy images (it creates duplicates). Prefer a true rename/move.
							// If rename fails (permissions/cross-volume), fall back to copy+unlink so the source
							// directory still ends with a single file.
							if (realpath($src_used) !== realpath($dest_abs)) {
								// If dest already exists, do not overwrite. Use it as canonical.
								if (!file_exists($dest_abs)) {
									$moved = @rename($src_used, $dest_abs);
									if (!$moved) {
										// Fallback: copy then delete source (best-effort).
										if (@copy($src_used, $dest_abs)) {
											@unlink($src_used);
										}
									}
								}
							}

							$have_local = file_exists($dest_abs) && is_file($dest_abs);
							break;
						}
					}

					// If local file is missing, do not spin forever: mark as missing and move on.
					// Summary: you can still see the original URL in the legacy_image_url field for manual resolution.
					if (!$have_local) {
						$miss = 'MISSING:' . $url;
						$wpdb->update(
							$wpdb->prefix . 'fi_legislators',
							['legacy_image_url' => $miss],
							['id' => $leg_id],
							['%s'],
							['%d']
						);
						$e = $err_ctx + [
							'stage' => 'missing_local',
							'error' => 'Local file not found in FI_PATH_IMAGES; marked as missing and skipped',
							'dest_name' => $dest_name,
							'dest_abs' => $dest_abs,
							'legacy_image_url_marked' => $miss,
						];
						$errors[] = $e;
						if (function_exists('fi_log')) fi_admin_ajax_log('Legacy image import missing: ' . json_encode($e), __FILE__, __LINE__, 'warning');
						$processed++;
						continue;
					}

					// If not local, fetch remote -> save to dest_abs
					if (!$have_local) {
						$res = wp_safe_remote_get($url, [
							'timeout' => 30,
							'redirection' => 5,
							'user-agent' => 'FreedomIndex/LegacyImageImport; ' . home_url('/'),
						]);
						if (is_wp_error($res)) {
							$e = $err_ctx + [
								'stage' => 'remote_get',
								'error' => $res->get_error_message(),
								'wp_error_code' => $res->get_error_code(),
								'dest_name' => $dest_name,
								'dest_abs' => $dest_abs,
							];
							$errors[] = $e;
							if (function_exists('fi_log')) fi_admin_ajax_log('Legacy image import error: ' . json_encode($e), __FILE__, __LINE__, 'error');
							$processed++;
							continue;
						}
						$status = (int) wp_remote_retrieve_response_code($res);
						$body = (string) wp_remote_retrieve_body($res);
						if ($status < 200 || $status >= 300 || $body === '') {
							// Mark 404/410 as permanently missing so the runner can advance.
							if ($status === 404 || $status === 410) {
								$miss = 'MISSING:' . $url;
								$wpdb->update(
									$wpdb->prefix . 'fi_legislators',
									['legacy_image_url' => $miss],
									['id' => $leg_id],
									['%s'],
									['%d']
								);
							}
							$e = $err_ctx + [
								'stage' => 'remote_http',
								'error' => 'HTTP ' . $status,
								'http_status' => $status,
								'content_type' => wp_remote_retrieve_header($res, 'content-type'),
								'content_length' => wp_remote_retrieve_header($res, 'content-length'),
								'dest_name' => $dest_name,
								'dest_abs' => $dest_abs,
								'legacy_image_url_marked' => (($status === 404 || $status === 410) ? ('MISSING:' . $url) : null),
							];
							$errors[] = $e;
							if (function_exists('fi_log')) fi_admin_ajax_log('Legacy image import error: ' . json_encode($e), __FILE__, __LINE__, 'error');
							$processed++;
							continue;
						}
						if (file_exists($dest_abs)) {
							@unlink($dest_abs);
						}
						if (@file_put_contents($dest_abs, $body) === false) {
							$e = $err_ctx + ['stage' => 'write_file', 'error' => 'Failed to write file', 'dest_name' => $dest_name, 'dest_abs' => $dest_abs];
							$errors[] = $e;
							if (function_exists('fi_log')) fi_admin_ajax_log('Legacy image import error: ' . json_encode($e), __FILE__, __LINE__, 'error');
							$processed++;
							continue;
						}
					}

					$img = \FI\Core\media_import_local_image($dest_abs, $dest_name, [
						'copy_into_uploads' => false,
						'generate_thumbnail' => true,
						'public_url_base' => $public_url_base,
						'overwrite_basename' => $dest_name,
						// Summary: title/slug optimized for Media Library search; avoid "Legiscan" in any attachment-facing fields.
						'desc' => '',
						'post_title' => trim($gov . ' ' . $display_name . ' ' . (string) $leg_id),
						'post_name' => (string) $leg_id . '-' . (string) $basename,
						'meta' => [
							'fi_source_url' => $url,
							'fi_source' => 'legacy_image_url',
							'fi_legislator_id' => $leg_id,
							'fi_gov' => $gov,
						],
					]);
					$att_id = (int) ($img['id'] ?? 0);
					if ($att_id <= 0) {
						$e = $err_ctx + [
							'stage' => 'media_import_local_image',
							'error' => 'Image import failed',
							'media_error' => $img['error'] ?? null,
							'dest_name' => $dest_name,
							'dest_abs' => $dest_abs,
						];
						$errors[] = $e;
						if (function_exists('fi_log')) fi_admin_ajax_log('Legacy image import error: ' . json_encode($e), __FILE__, __LINE__, 'error');
						$processed++;
						continue;
					}

					// Update legislator and clear legacy_image_url so it won't retry.
					$wpdb->update(
						$wpdb->prefix . 'fi_legislators',
						['image_id' => $att_id, 'legacy_image_url' => null],
						['id' => $leg_id],
						['%d','%s'],
						['%d']
					);

					$processed++;
					$updated++;
				}

				wp_send_json_success([
					'processed' => $processed,
					'updated' => $updated,
					'skipped' => $skipped,
					'errors' => $errors,
					'local_dir' => $local_dir,
				]);
				break;

		case 'search_legislators':
			$query = sanitize_text_field($_POST['query'] ?? '');
			$results = fi_admin_legislators_search($query);
			wp_send_json_success($results);
			break;
			
		case 'get_roll_call_data':
			$vote_id = absint($_POST['vote_id'] ?? 0);
			if (!$vote_id) {
				wp_send_json_error('Invalid vote ID');
			}
			$results = fi_rollcalls_get_by_vote($vote_id);
			$summary = fi_rollcall_summary($vote_id);
			wp_send_json_success([
				'rollcalls' => $results,
				'summary' => $summary,
			]);
			break;
			
		case 'save_rollcall':
			$vote_id = absint($_POST['vote_id'] ?? 0);
			$roll_call_data = $_POST['roll_call_data'] ?? [];

			if (is_string($roll_call_data)) {
				$decoded = json_decode(wp_unslash($roll_call_data), true);
				if (is_array($decoded)) {
					$roll_call_data = $decoded;
				}
			}
			
			if (!$vote_id || !is_array($roll_call_data)) {
				wp_send_json_error('Invalid data');
			}
		
		$saved = 0;
		$skipped = 0;
			foreach ($roll_call_data as $roll_call) {
			$rollcall_id = absint($roll_call['rollcall_id'] ?? $roll_call['id'] ?? 0);
				$legislator_id = absint($roll_call['legislator_id'] ?? 0);
			$cast = sanitize_text_field($roll_call['cast'] ?? '');
			$cast = $cast !== '' ? fi_rollcall_cast_normalize($cast) : '';
			if (!$rollcall_id || !$legislator_id || $cast === '') {
				$skipped++;
					continue;
				}
			
			// Update existing rollcall row by fi_voterc.id.
			global $wpdb;
			$result = $wpdb->update(
				$wpdb->prefix . 'fi_voterc',
				[
					'cast' => $cast,
					'is_override' => !empty($roll_call['is_override']) ? 1 : 0,
				],
				[
					'id' => $rollcall_id,
					'vote_id' => $vote_id,
					'legislator_id' => $legislator_id,
				],
				['%s', '%d'],
				['%d', '%d', '%d']
			);

				if ($result !== false) {
					$saved++;
				}
			}

			$summary = fi_rollcall_summary($vote_id);

			wp_send_json_success([
				'saved' => $saved,
			'skipped' => $skipped,
				'summary' => $summary,
			]);
			break;

		case 'import_legiscan_rollcall':
			$vote_id = absint($_POST['vote_id'] ?? 0);
			$gov = strtoupper(sanitize_text_field($_POST['gov'] ?? ''));
			$payload = wp_unslash($_POST['payload'] ?? '');

			if (!$vote_id || $gov === '' || $payload === '') {
				wp_send_json_error('Missing vote, government, or payload data.');
			}

			$imported = fi_rollcall_import($vote_id, $payload, $gov);
			$updated = fi_rollcalls_get_by_vote($vote_id);
			$summary = fi_rollcall_summary($vote_id);

			wp_send_json_success([
				'imported' => $imported,
				'rollcalls' => $updated,
				'summary' => $summary,
			]);
			break;

		case 'get_votes_by_session':
			$session_id = absint($_POST['session_id'] ?? 0);
			if (!$session_id) {
				wp_send_json_error('Invalid session ID');
			}
			// Admin needs to see all votes (core defaults to publish-only when status key is missing).
			$votes = fi_votes_get_by_session($session_id, ['status' => null, 'cache' => false]);
			$formatted = array_map(static function ($vote) {
				return [
					'id' => (int) $vote->id,
					'title' => $vote->title,
					'bill_number' => $vote->bill_number ?? $vote->slug ?? '',
					'chamber' => $vote->chamber,
					'date_voted' => $vote->date_voted ?? $vote->date ?? '',
					'constitutional' => $vote->constitutional,
				];
			}, $votes);

			wp_send_json_success(['votes' => $formatted]);
			break;
			
		case 'get_vote_preview':
			$vote_id = absint($_POST['vote_id'] ?? 0);
			if (!$vote_id) {
				wp_send_json_error('Invalid vote ID');
			}
			
			$vote = fi_vote_get($vote_id);
			if (!$vote) {
				wp_send_json_error('Vote not found');
			}
			
			$meta = fi_vote_decode_meta($vote);
			
			// Format date
			$formatted_date = '';
			if (!empty($vote->date_voted)) {
				$date_obj = \DateTime::createFromFormat('Y-m-d', $vote->date_voted);
				if ($date_obj) {
					$formatted_date = $date_obj->format('m/d/Y');
				} else {
					$formatted_date = $vote->date_voted;
				}
			}
			
			// Build HTML
			ob_start();
			?>
			<div class="fi-vote-preview-content">
				<h5 class="mb-3"><?php echo esc_html($vote->title ?? 'Untitled Vote'); ?></h5>
				
				<div class="mb-3">
					<div class="row g-2">
						<?php if (!empty($vote->bill_number)): ?>
							<div class="col-12">
								<strong>Bill:</strong> <?php echo esc_html($vote->bill_number); ?>
							</div>
						<?php endif; ?>
						
						<?php if ($formatted_date): ?>
							<div class="col-12">
								<strong>Date:</strong> <?php echo esc_html($formatted_date); ?>
							</div>
						<?php endif; ?>
						
						<div class="col-12">
							<strong>Constitutional Position:</strong> 
							<span class="badge bg-<?php echo ($vote->constitutional === 'Y') ? 'success' : 'danger'; ?>">
								<?php echo esc_html(($vote->constitutional === 'Y') ? 'Yea' : 'Nay'); ?>
							</span>
						</div>
						
						<?php if (!empty($meta['cost'])): ?>
							<div class="col-12">
								<strong>Estimated Cost Per Household:</strong> 
								<span class="text-<?php echo (strpos($meta['cost'], '+') === 0) ? 'success' : 'danger'; ?>">
									$<?php echo esc_html(str_replace('+', '', $meta['cost'])); ?>
								</span>
							</div>
						<?php endif; ?>
					</div>
				</div>
				
				<div class="mb-3">
					<h6>Short Description (Effect on You)</h6>
					<div class="small">
						<?php 
						$description_short = fi_vote_get_description($meta, 'scorecard');
						if (!empty($description_short)): ?>
							<?php echo wp_kses_post(wpautop($description_short)); ?>
						<?php else: ?>
							<span class="text-muted">--</span>
						<?php endif; ?>
					</div>
				</div>
				
				<div class="mb-3">
					<h6>Medium Description</h6>
					<div>
						<?php if (!empty($meta['description_medium'])): ?>
							<?php echo wp_kses_post(wpautop($meta['description_medium'])); ?>
						<?php else: ?>
							<span class="text-muted">--</span>
						<?php endif; ?>
					</div>
				</div>
				
				<div class="mb-3">
					<h6>Excerpt Description (Details)</h6>
					<div>
						<?php if (!empty($meta['description_excerpt'])): ?>
							<?php echo wp_kses_post(wpautop($meta['description_excerpt'])); ?>
						<?php else: ?>
							<span class="text-muted">--</span>
						<?php endif; ?>
					</div>
				</div>
				
				<div class="mb-3">
					<h6>Long Description</h6>
					<div>
						<?php if (!empty($meta['description_long'])): ?>
							<?php echo wp_kses_post(wpautop($meta['description_long'])); ?>
						<?php else: ?>
							<span class="text-muted">--</span>
						<?php endif; ?>
					</div>
				</div>
				
				<div class="mb-3">
					<h6>Rollcall Data Description</h6>
					<div class="small">
						<?php 
						if (!empty($vote->rollcall_data)) {
							$rollcall_data = json_decode($vote->rollcall_data, true);
							if (is_array($rollcall_data) && !empty($rollcall_data['description'])) {
								echo wp_kses_post(wpautop($rollcall_data['description']));
							} else {
								echo '<span class="text-muted">--</span>';
							}
						} else {
							echo '<span class="text-muted">--</span>';
						}
						?>
					</div>
				</div>
			</div>
			<?php
			$html = ob_get_clean();
			
			wp_send_json_success(['html' => $html]);
			break;
			
		case 'generate_report_preview':
			$votes = $_POST['votes'] ?? [];
			$options = $_POST['options'] ?? [];
			
			if (empty($votes)) {
				wp_send_json_error('No votes selected');
			}
			
			$preview_html = fi_admin_reports_generate_preview_html($votes, $options);
			wp_send_json_success(['html' => $preview_html]);
			break;
			
		case 'generate_report_pdf':
			$form_data = $_POST['data'] ?? '';
			parse_str($form_data, $data);
			
			if (empty($data['selected_votes'])) {
				wp_send_json_error('No votes selected');
			}
			
			$pdf_url = fi_admin_reports_generate_pdf($data);
			
			if ($pdf_url) {
				wp_send_json_success(['pdf_url' => $pdf_url]);
			} else {
				wp_send_json_error('Failed to generate PDF');
			}
			break;

		case 'fetch_api_data':
			$legislator_id = absint($_POST['legislator_id'] ?? 0);
			$source = sanitize_text_field($_POST['source'] ?? '');
			
			if (!$legislator_id) {
				wp_send_json_error('Invalid legislator ID');
			}
			
			// Ensure legislator exists (also used for meta patching below).
			$legislator = fi_legislator_get($legislator_id);
			if (!$legislator) {
				wp_send_json_error('Legislator not found');
			}
			
			if ($source === 'all') {
				$api_data = fi_api_fetch_all($legislator_id);
			} else {
				// Fetch single source
				$api_data = [];
				switch ($source) {
					case 'votesmart':
						if (!empty($legislator->votesmart_id)) {
							// VoteSmart requires an API key.
							$votesmart_key = fi_get_api_key('votesmart_key', 'API_KEY_VOTESMART');
							if (empty($votesmart_key)) {
								$api_data['votesmart'] = [
									'_fi_error' => true,
									'error' => 'Missing VoteSmart API key. Configure it in FI Settings first.',
								];
							} else {
								$api_data['votesmart'] = fi_api_fetch_votesmart($legislator->votesmart_id);
							}
						} else {
							$api_data['votesmart'] = [
								'_fi_error' => true,
								'error' => 'Missing VoteSmart ID for this legislator.',
							];
						}
						break;
					case 'govtrack':
						if (!empty($legislator->govtrack_id)) {
							$api_data['govtrack'] = fi_api_fetch_govtrack($legislator->govtrack_id);
						} elseif (!empty($legislator->bioguide_id)) {
							$api_data['govtrack'] = fi_api_fetch_govtrack_by_bioguide($legislator->bioguide_id);
						} else {
							$api_data['govtrack'] = [
								'_fi_error' => true,
								'error' => 'Missing GovTrack ID / Bioguide ID for this legislator.',
							];
						}
						break;
					case 'legiscan_local':
						// Local LegiScan cache (no API key):
						// Prefer the exact cache-relative path supplied by the edit screen (avoids re-deriving session folder logic).
						$legiscan_id = (int) ($legislator->legiscan_id ?? 0);
						$cache_rel = sanitize_text_field($_POST['cache_rel'] ?? '');
						$cache_rel = trim($cache_rel);
						
						if ($cache_rel !== '' && (defined('FI_DIR_LEGISCAN') || defined('FI_DIR_CACHE'))) {
							// Safety: prevent path traversal / weird inputs.
							if (strpos($cache_rel, '..') !== false) {
								$api_data['legiscan_local'] = [
									'_fi_error' => true,
									'error' => 'Invalid LegiScan cache path.',
								];
								break;
							}
							// Expected shape: GOV/FOLDER/people/12345  (no .json extension)
							if (!preg_match('#^[A-Z]{2}/[A-Za-z0-9._-]+/people/[0-9]+$#', $cache_rel)) {
								$api_data['legiscan_local'] = [
									'_fi_error' => true,
									'error' => 'Invalid LegiScan cache path format: ' . $cache_rel,
								];
								break;
							}
							
							$base = defined('FI_DIR_LEGISCAN')
								? rtrim(FI_DIR_LEGISCAN, '/\\') . DIRECTORY_SEPARATOR
								: (rtrim(FI_DIR_CACHE, '/\\') . DIRECTORY_SEPARATOR . 'legiscan' . DIRECTORY_SEPARATOR);
							$people_file = $base . str_replace('/', DIRECTORY_SEPARATOR, $cache_rel) . '.json';
							
							// Ensure resolved path stays under LegiScan dataset base.
							$real_base = realpath($base);
							$real_file = realpath($people_file);
							if ($real_base && $real_file && strpos($real_file, $real_base) !== 0) {
								$api_data['legiscan_local'] = [
									'_fi_error' => true,
									'error' => 'Invalid LegiScan cache path (outside cache directory).',
								];
								break;
							}
							
							if (!is_readable($people_file)) {
								$api_data['legiscan_local'] = [
									'_fi_error' => true,
									'error' => 'LegiScan cache file not found: ' . $cache_rel . '.json',
								];
								break;
							}
							
							$raw = json_decode((string) @file_get_contents($people_file), true);
							$person = is_array($raw) ? ($raw['person'] ?? null) : null;
							if (!is_array($person)) {
								$api_data['legiscan_local'] = [
									'_fi_error' => true,
									'error' => 'LegiScan cache file could not be parsed (missing person node).',
								];
								break;
							}
							
							// Normalize capitol address: combine address1/address2 into a single "address" field
							// and unset the parts so users don't try to update individual components in the UI.
							if (isset($person['bio']['capitol_address']) && is_array($person['bio']['capitol_address'])) {
								$ca = $person['bio']['capitol_address'];
								$address1 = (string) ($ca['address1'] ?? '');
								$address2 = (string) ($ca['address2'] ?? '');
								$combined = trim($address1 . ($address2 !== '' ? (' ' . $address2) : ''));
								if ($combined !== '') {
									$person['bio']['capitol_address']['address'] = $combined;
								}
								unset($person['bio']['capitol_address']['address1'], $person['bio']['capitol_address']['address2']);
							}

							$api_data['legiscan_local'] = $person;
							break;
						}
						
						// Fallback: derive from latest assigned session (legacy behavior).
						$session_folder = '';
						$session_gov = strtoupper((string) ($legislator->gov ?? ''));
						$sessions = is_array($legislator->sessions ?? null) ? $legislator->sessions : [];
						if (!empty($sessions)) {
							$latest_session = reset($sessions);
							if (is_object($latest_session)) {
								// Prefer session meta folder slug if available (real cache directory name).
								$session_id = (int) ($latest_session->session_id ?? 0);
								if ($session_id) {
									$session_obj = fi_session_get($session_id);
									$session_meta = is_object($session_obj) ? ($session_obj->meta ?? null) : null;
									if (is_array($session_meta)) {
										$session_folder = (string) ($session_meta['legiscan_folder'] ?? '');
										if ($session_folder === '' && isset($session_meta['legiscan_data']) && is_array($session_meta['legiscan_data'])) {
											$session_folder = (string) ($session_meta['legiscan_data']['directory'] ?? '');
										}
									} elseif (is_object($session_obj) && is_string($session_obj->meta ?? null)) {
										$decoded = json_decode($session_obj->meta, true);
										if (is_array($decoded)) {
											$session_folder = (string) ($decoded['legiscan_folder'] ?? '');
											if ($session_folder === '' && isset($decoded['legiscan_data']) && is_array($decoded['legiscan_data'])) {
												$session_folder = (string) ($decoded['legiscan_data']['directory'] ?? '');
											}
										}
									}
								}
								// Fallback to FI session slug (may not match cache directory naming for older data).
								if ($session_folder === '') {
									//SESSIONSLUG: This is admin code - OK to keep for admin reference, but consider using session_id if folder naming changes
									$session_folder = (string) ($latest_session->session_slug ?? '');
								}
								if (!empty($latest_session->gov ?? '')) {
									$session_gov = strtoupper((string) $latest_session->gov);
								}
							}
						}
						
						if (!$legiscan_id || $session_folder === '' || $session_gov === '' || (!defined('FI_DIR_LEGISCAN') && !defined('FI_DIR_CACHE'))) {
							$api_data['legiscan_local'] = [
								'_fi_error' => true,
								'error' => 'Missing legiscan_id and/or latest session folder; cannot locate cached LegiScan people file.',
							];
							break;
						}
						
						$legiscan_base = defined('FI_DIR_LEGISCAN')
							? rtrim(FI_DIR_LEGISCAN, '/\\') . DIRECTORY_SEPARATOR
							: (rtrim(FI_DIR_CACHE, '/\\') . DIRECTORY_SEPARATOR . 'legiscan' . DIRECTORY_SEPARATOR);

						$people_file = $legiscan_base
							. $session_gov . DIRECTORY_SEPARATOR . $session_folder . DIRECTORY_SEPARATOR
							. 'people' . DIRECTORY_SEPARATOR . $legiscan_id . '.json';
						
						if (!is_readable($people_file)) {
							$api_data['legiscan_local'] = [
								'_fi_error' => true,
								'error' => 'LegiScan cache file not found for latest session: ' . $session_gov . '/' . $session_folder . '/people/' . $legiscan_id . '.json',
							];
							break;
						}
						
						$raw = json_decode((string) @file_get_contents($people_file), true);
						$person = is_array($raw) ? ($raw['person'] ?? null) : null;
						if (!is_array($person)) {
							$api_data['legiscan_local'] = [
								'_fi_error' => true,
								'error' => 'LegiScan cache file could not be parsed (missing person node).',
							];
							break;
						}
						
						// Normalize capitol address: combine address1/address2 into a single "address" field
						// and unset the parts so users don't try to update individual components in the UI.
						if (isset($person['bio']['capitol_address']) && is_array($person['bio']['capitol_address'])) {
							$ca = $person['bio']['capitol_address'];
							$address1 = (string) ($ca['address1'] ?? '');
							$address2 = (string) ($ca['address2'] ?? '');
							$combined = trim($address1 . ($address2 !== '' ? (' ' . $address2) : ''));
							if ($combined !== '') {
								$person['bio']['capitol_address']['address'] = $combined;
							}
							unset($person['bio']['capitol_address']['address1'], $person['bio']['capitol_address']['address2']);
						}

						$api_data['legiscan_local'] = $person;
						break;
					default:
						if ($source !== '') {
							$api_data[$source] = [
								'_fi_error' => true,
								'error' => 'Unsupported API source.',
							];
						}
						break;
				}
			}

			// Persist raw API payloads into legislator meta (audit trail).
			// Stored as meta keys: api_{source}, with a timestamp wrapper.
			$api_meta_patch = [];
			foreach ($api_data as $src => $data) {
				$src_key = sanitize_key((string) $src);
				if ($src_key === '') {
					continue;
				}
				$api_meta_patch['api_' . $src_key] = [
					'fetched_at' => current_time('mysql'),
					'data' => $data,
				];
			}
			// Always update the same key on repeat checks (overwrites api_{source}).
			if (!empty($api_meta_patch)) {
				fi_legislator_save(['meta' => $api_meta_patch], $legislator_id);
			}
			
			// Compare with existing data
			$comparisons = [];
			foreach ($api_data as $src => $data) {
				if (is_array($data) && !empty($data) && empty($data['_fi_error'])) {
					$comparisons[$src] = fi_api_compare($legislator_id, $src, $data);
				}
			}
			
			wp_send_json_success([
				'api_data' => $api_data,
				'comparisons' => $comparisons
			]);
			break;

		case 'update_from_api':
			$legislator_id = absint($_POST['legislator_id'] ?? 0);
			$source = sanitize_text_field($_POST['source'] ?? '');
			$updates = $_POST['updates'] ?? []; // Array of field => value pairs
			
			if (!$legislator_id || empty($source) || !is_array($updates)) {
				wp_send_json_error('Invalid data');
			}
			
			$updated = fi_admin_legislators_apply_api_updates($legislator_id, $source, $updates);
			
			wp_send_json_success([
				'updated' => $updated,
				'message' => "Updated {$updated} field(s) from {$source}"
			]);
			break;
			
		case 'compile_legiscan_data':
			$gov = strtoupper(sanitize_text_field($_POST['gov'] ?? ''));
			$session_id = absint($_POST['session_id'] ?? 0);
			$data_dir = sanitize_text_field($_POST['data_dir'] ?? '');
			$action = sanitize_text_field($_POST['compile_action'] ?? 'start');
			
			if (empty($gov) || !$session_id || empty($data_dir)) {
				wp_send_json_error('Missing required parameters');
			}
			
			// For 'start' action, trigger compilation
			if ($action === 'start') {
				// Path to compiler script
				$compiler_url = content_url('/jbsfi/legiscan/compile_data.php');
				
				// Prepare data for compiler
				$compile_data = [
					'auth_key' => 'legiscan_compile_2024_v1',
					'gov' => $gov,
					'session_id' => $session_id,
					'data_dir' => $data_dir,
					'action' => 'start',
				];
				
				// Call compiler - use very short timeout to trigger but not wait
				// The compiler will run and update status file
				$response = wp_remote_post($compiler_url, [
					'body' => json_encode($compile_data),
					'headers' => ['Content-Type' => 'application/json'],
					'timeout' => 2, // 2 seconds - enough to start, not wait for completion
				]);
				
				// Don't check response - compilation runs in background via status file
				wp_send_json_success(['message' => 'Compilation started']);
			}
			
			// For 'status' action, read status file from session directory
			if ($action === 'status') {
				$status_file = rtrim($data_dir, '/') . '/__compile_status.json';
				
				if (!file_exists($status_file)) {
					wp_send_json_success([
						'status' => 'not_started',
						'progress' => 0,
						'current_step' => 'Not started',
					]);
				}
				
				$status = json_decode(file_get_contents($status_file), true);
				wp_send_json_success($status ?: []);
			}
			
			wp_send_json_error('Invalid action');
			break;
			
		default:
			wp_send_json_error('Unknown action');
	}
}


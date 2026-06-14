<?php
/**
 * Freedom Index Admin AJAX Handlers
 */

if (!defined('ABSPATH')) { exit; }

/**
 * Admin AJAX scoped logger.
 *
 * @param string $message Log message.
 * @param string $file File path.
 * @param int $line Line number.
 * @param string $level Log level.
 * @return void
 */
function fi_admin_ajax_log(string $message, $file = '', int $line = 0, string $level = 'info'): void {
	if (function_exists('fi_log_area')) {
		fi_log_area('admin_ajax', $message, (string) $file, $line, $level);
	}
}

/**
 * Get FI admin capability.
 *
 * @return string Capability.
 */
function fi_admin_ajax_capability(): string {
	return defined('FI_CAP_MANAGE') ? FI_CAP_MANAGE : 'manage_options';
}

/**
 * Verify FI admin AJAX nonce and capability.
 *
 * @return void
 */
function fi_admin_ajax_verify_request(): void {
	$nonce = isset($_POST['nonce']) ? (string) wp_unslash($_POST['nonce']) : '';

	if ($nonce === '' || !wp_verify_nonce($nonce, 'fi_admin_nonce')) {
		fi_admin_ajax_log(
			'AJAX invalid nonce | action=' . (string) ($_POST['action'] ?? '') . ' | sub_action=' . (string) ($_POST['sub_action'] ?? ''),
			__FILE__,
			__LINE__,
			'error'
		);
		wp_send_json_error(['message' => 'Invalid nonce']);
	}

	if (!current_user_can(fi_admin_ajax_capability())) {
		fi_admin_ajax_log(
			'AJAX insufficient permissions | action=' . (string) ($_POST['action'] ?? '') . ' | sub_action=' . (string) ($_POST['sub_action'] ?? ''),
			__FILE__,
			__LINE__,
			'error'
		);
		wp_send_json_error(['message' => 'Insufficient permissions']);
	}
}

/**
 * Main admin AJAX action dispatcher.
 *
 * @return void
 */
function fi_admin_ajax_handle(): void {
	fi_admin_ajax_verify_request();

	$action = sanitize_key((string) ($_POST['action'] ?? ''));

	if ($action === 'fi_admin_action') {
		$sub_action = sanitize_key((string) ($_POST['sub_action'] ?? ''));
		fi_admin_ajax_handle_sub_action($sub_action);
	}

	wp_send_json_error(['message' => 'Unknown action']);
}
add_action('wp_ajax_fi_admin_action', 'fi_admin_ajax_handle');

/**
 * Dedicated AJAX log endpoint for client-side breadcrumbs.
 *
 * @return void
 */
function fi_ajax_log_endpoint(): void {
	$nonce = isset($_POST['nonce']) ? (string) wp_unslash($_POST['nonce']) : '';
	$context = [
		'event' => sanitize_text_field((string) wp_unslash($_POST['event'] ?? '')),
		'page'  => sanitize_text_field((string) wp_unslash($_POST['page'] ?? '')),
		'rid'   => sanitize_text_field((string) wp_unslash($_POST['rid'] ?? '')),
	];

	if ($nonce === '' || !wp_verify_nonce($nonce, 'fi_admin_nonce')) {
		if (function_exists('fi_ajax_log')) {
			fi_ajax_log('fi_ajax_log invalid nonce', $context, 'error', __FILE__, __LINE__);
		} else {
			fi_admin_ajax_log('fi_ajax_log invalid nonce | ' . wp_json_encode($context), __FILE__, __LINE__, 'error');
		}
		wp_send_json_error(['message' => 'Invalid nonce']);
	}

	if (!current_user_can(fi_admin_ajax_capability())) {
		if (function_exists('fi_ajax_log')) {
			fi_ajax_log('fi_ajax_log insufficient permissions', $context, 'error', __FILE__, __LINE__);
		} else {
			fi_admin_ajax_log('fi_ajax_log insufficient permissions | ' . wp_json_encode($context), __FILE__, __LINE__, 'error');
		}
		wp_send_json_error(['message' => 'Insufficient permissions']);
	}

	$msg = sanitize_text_field((string) wp_unslash($_POST['message'] ?? ''));
	$data = $_POST['data'] ?? [];

	if (is_string($data)) {
		$decoded = json_decode(wp_unslash($data), true);
		$data = is_array($decoded) ? $decoded : [];
	}

	if (!is_array($data)) {
		$data = [];
	}

	$context['user_id'] = get_current_user_id();
	$context['data'] = $data;

	if (function_exists('fi_ajax_log')) {
		fi_ajax_log($msg !== '' ? $msg : 'client', $context, 'debug', __FILE__, __LINE__);
	} else {
		fi_admin_ajax_log(($msg !== '' ? $msg : 'client') . ' | ' . wp_json_encode($context), __FILE__, __LINE__, 'debug');
	}

	wp_send_json_success(['ok' => true]);
}
add_action('wp_ajax_fi_ajax_log', 'fi_ajax_log_endpoint');

/**
 * Get configured FI local image directory and public base URL.
 *
 * @return array{dir:string,url_base:string}|WP_Error
 */
function fi_admin_ajax_get_image_repo_config() {
	$dest_dir = defined('FI_PATH_IMAGES') ? (string) FI_PATH_IMAGES : '';
	$dest_url_base = defined('FI_URL_IMAGES') ? (string) FI_URL_IMAGES : '';
	$dest_dir = rtrim($dest_dir, "/\\") . DIRECTORY_SEPARATOR;

	if ($dest_dir === DIRECTORY_SEPARATOR || $dest_dir === '') {
		return new WP_Error('missing_image_dir', 'Local image directory is not configured');
	}

	if (!is_dir($dest_dir)) {
		wp_mkdir_p($dest_dir);
	}

	if (!is_dir($dest_dir) || !is_writable($dest_dir)) {
		return new WP_Error('image_dir_not_writable', 'Local image directory is not writable');
	}

	return [
		'dir'      => $dest_dir,
		'url_base' => $dest_url_base,
	];
}

/**
 * Build a safe image filename for a legislator.
 *
 * @param int $legislator_id Legislator ID.
 * @param string $source_name Source filename or URL path basename.
 * @param string $separator Separator between ID and basename.
 * @return string Filename.
 */
function fi_admin_ajax_build_legislator_image_filename(int $legislator_id, string $source_name, string $separator = '_'): string {
	$source_name = sanitize_file_name($source_name);
	$ext = strtolower(pathinfo($source_name, PATHINFO_EXTENSION));
	$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

	if ($source_name === '' || $source_name === '.' || $source_name === '/') {
		$source_name = 'image.jpg';
		$ext = 'jpg';
	}

	if ($ext === '') {
		$ext = 'jpg';
		$source_name .= '.jpg';
	}

	if (!in_array($ext, $allowed, true)) {
		$ext = 'jpg';
		$source_name = preg_replace('/\.[a-z0-9]+$/i', '', $source_name) . '.jpg';
	}

	$filename = sanitize_file_name($legislator_id . $separator . $source_name);
	if ($filename === '' || strpos($filename, (string) $legislator_id . $separator) !== 0) {
		$filename = $legislator_id . $separator . 'image.' . $ext;
	}

	return $filename;
}

/**
 * Register a local FI image file as a WP attachment.
 *
 * @param string $dest_abs Absolute image path.
 * @param string $filename Filename.
 * @param string $public_url_base Public URL base.
 * @param array $opts Import options.
 * @return array Import result.
 */
function fi_admin_ajax_register_local_image(string $dest_abs, string $filename, string $public_url_base, array $opts = []): array {
	if (!function_exists('fi_media_import_local_image')) {
		return [
			'id'    => 0,
			'error' => ['message' => 'fi_media_import_local_image() is unavailable. Confirm /core/media.php loads before this admin AJAX file.'],
		];
	}

	$defaults = [
		'copy_into_uploads' => false,
		'generate_thumbnail' => true,
		'public_url_base'    => $public_url_base,
		'overwrite_basename' => $filename,
	];

	return fi_media_import_local_image($dest_abs, $filename, array_merge($defaults, $opts));
}

/**
 * Update legislator image ID using the legislator save helper.
 *
 * @param int $legislator_id Legislator ID.
 * @param int $attachment_id Attachment ID.
 * @return bool Success.
 */
function fi_admin_ajax_update_legislator_image_id(int $legislator_id, int $attachment_id): bool {
	if (function_exists('fi_legislator_save')) {
		return (bool) fi_legislator_save(['image_id' => $attachment_id], $legislator_id);
	}

	global $wpdb;
	$result = $wpdb->update(
		$wpdb->prefix . 'fi_legislators',
		['image_id' => $attachment_id],
		['id' => $legislator_id],
		['%d'],
		['%d']
	);

	return $result !== false;
}

/**
 * Get attachment preview URL.
 *
 * @param int $attachment_id Attachment ID.
 * @param string $fallback_url Fallback URL.
 * @return string URL.
 */
function fi_admin_ajax_get_attachment_preview_url(int $attachment_id, string $fallback_url = ''): string {
	return wp_get_attachment_image_url($attachment_id, 'thumbnail')
		?: wp_get_attachment_url($attachment_id)
		?: $fallback_url;
}

/**
 * Handle fetch legislator image by URL.
 *
 * @return void
 */
function fi_admin_ajax_fetch_legislator_image_url(): void {
	$legislator_id = absint($_POST['legislator_id'] ?? 0);
	$url = esc_url_raw((string) wp_unslash($_POST['url'] ?? ''));

	fi_admin_ajax_log('AJAX fetch_legislator_image_url start | legislator_id=' . $legislator_id . ' | url=' . $url, __FILE__, __LINE__, 'debug');

	if ($legislator_id <= 0) {
		wp_send_json_error('Missing legislator_id');
	}

	if ($url === '' || !preg_match('#^https?://#i', $url)) {
		wp_send_json_error('Invalid URL');
	}

	$config = fi_admin_ajax_get_image_repo_config();
	if (is_wp_error($config)) {
		wp_send_json_error($config->get_error_message());
	}

	$path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
	$base = sanitize_file_name(basename($path));
	$filename = fi_admin_ajax_build_legislator_image_filename($legislator_id, $base, '_');
	$dest_abs = $config['dir'] . $filename;

	$res = wp_safe_remote_get($url, [
		'timeout'     => 30,
		'redirection' => 5,
		'user-agent'  => 'FreedomIndex/AdminImageFetch; ' . home_url('/'),
	]);

	if (is_wp_error($res)) {
		wp_send_json_error($res->get_error_message());
	}

	$status = (int) wp_remote_retrieve_response_code($res);
	$body = (string) wp_remote_retrieve_body($res);

	if ($status < 200 || $status >= 300 || $body === '') {
		wp_send_json_error('Failed to fetch image (HTTP ' . $status . ')');
	}

	if (file_exists($dest_abs)) {
		@unlink($dest_abs);
	}

	if (@file_put_contents($dest_abs, $body) === false) {
		wp_send_json_error('Failed to save image file');
	}

	$img = fi_admin_ajax_register_local_image($dest_abs, $filename, $config['url_base'], [
		'desc' => 'Legislator image fetch (legislator_id=' . $legislator_id . ')',
		'meta' => [
			'fi_source'        => 'url_fetch',
			'fi_source_url'    => $url,
			'fi_legislator_id' => $legislator_id,
		],
	]);

	$attachment_id = (int) ($img['id'] ?? 0);
	if ($attachment_id <= 0) {
		wp_send_json_error([
			'message' => 'Failed to register image attachment',
			'error'   => $img['error'] ?? null,
		]);
	}

	if (!fi_admin_ajax_update_legislator_image_id($legislator_id, $attachment_id)) {
		wp_send_json_error('Failed updating legislator image_id');
	}

	$preview = fi_admin_ajax_get_attachment_preview_url($attachment_id, $config['url_base'] ? (rtrim($config['url_base'], '/') . '/' . $filename) : '');

	fi_admin_ajax_log('AJAX fetch_legislator_image_url success | legislator_id=' . $legislator_id . ' | attachment_id=' . $attachment_id . ' | file=' . $filename, __FILE__, __LINE__, 'debug');

	wp_send_json_success([
		'legislator_id' => $legislator_id,
		'attachment_id' => $attachment_id,
		'filename'      => $filename,
		'url'           => $preview,
	]);
}

/**
 * Handle uploaded legislator image.
 *
 * @return void
 */
function fi_admin_ajax_upload_legislator_image(): void {
	$legislator_id = absint($_POST['legislator_id'] ?? 0);

	fi_admin_ajax_log('AJAX upload_legislator_image start | legislator_id=' . $legislator_id . ' | file=' . (string) ($_FILES['file']['name'] ?? ''), __FILE__, __LINE__, 'debug');

	if ($legislator_id <= 0) {
		wp_send_json_error('Missing legislator_id');
	}

	if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
		wp_send_json_error('Missing upload file');
	}

	$file = $_FILES['file'];
	if (!empty($file['error'])) {
		wp_send_json_error('Upload error: ' . (int) $file['error']);
	}

	$tmp_name = (string) ($file['tmp_name'] ?? '');
	if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
		wp_send_json_error('Invalid uploaded file');
	}

	$orig_name = sanitize_file_name((string) ($file['name'] ?? 'image.jpg'));
	$ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
	$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

	if ($ext === '' || !in_array($ext, $allowed, true)) {
		wp_send_json_error('Unsupported image type');
	}

	$config = fi_admin_ajax_get_image_repo_config();
	if (is_wp_error($config)) {
		wp_send_json_error($config->get_error_message());
	}

	$filename = fi_admin_ajax_build_legislator_image_filename($legislator_id, $orig_name, '_');
	$dest_abs = $config['dir'] . $filename;

	if (file_exists($dest_abs)) {
		@unlink($dest_abs);
	}

	if (!@move_uploaded_file($tmp_name, $dest_abs)) {
		if (!@copy($tmp_name, $dest_abs)) {
			wp_send_json_error('Failed to save uploaded file');
		}
		@unlink($tmp_name);
	}

	$img = fi_admin_ajax_register_local_image($dest_abs, $filename, $config['url_base'], [
		'desc' => 'Legislator image upload (legislator_id=' . $legislator_id . ')',
		'meta' => [
			'fi_source'        => 'manual_upload',
			'fi_legislator_id' => $legislator_id,
		],
	]);

	$attachment_id = (int) ($img['id'] ?? 0);
	if ($attachment_id <= 0) {
		wp_send_json_error([
			'message' => 'Failed to register image attachment',
			'error'   => $img['error'] ?? null,
		]);
	}

	if (!fi_admin_ajax_update_legislator_image_id($legislator_id, $attachment_id)) {
		wp_send_json_error('Failed updating legislator image_id');
	}

	$preview = fi_admin_ajax_get_attachment_preview_url($attachment_id, $config['url_base'] ? (rtrim($config['url_base'], '/') . '/' . $filename) : '');

	fi_admin_ajax_log('AJAX upload_legislator_image success | legislator_id=' . $legislator_id . ' | attachment_id=' . $attachment_id . ' | file=' . $filename, __FILE__, __LINE__, 'debug');

	wp_send_json_success([
		'legislator_id' => $legislator_id,
		'attachment_id' => $attachment_id,
		'filename'      => $filename,
		'url'           => $preview,
	]);
}

/**
 * Handle generic remote image fetch.
 *
 * @return void
 */
function fi_admin_ajax_fetch_remote_image(): void {
	$url = esc_url_raw((string) wp_unslash($_POST['url'] ?? ''));
	$preferred = sanitize_file_name((string) wp_unslash($_POST['preferred_filename'] ?? ''));
	$overwrite = sanitize_file_name((string) wp_unslash($_POST['overwrite_basename'] ?? ''));

	if ($url === '' || !preg_match('#^https?://#i', $url)) {
		wp_send_json_error('Invalid URL');
	}

	if ($preferred === '') {
		$preferred = sanitize_file_name(basename((string) (parse_url($url, PHP_URL_PATH) ?: 'remote.jpg')));
		if ($preferred === '') {
			$preferred = 'remote.jpg';
		}
	}

	if (!function_exists('fi_media_sideload_image_from_url')) {
		wp_send_json_error('fi_media_sideload_image_from_url() is unavailable. Confirm /core/media.php loads before this admin AJAX file.');
	}

	$img = fi_media_sideload_image_from_url($url, $preferred, [
		'desc'               => 'Freedom Index remote image fetch',
		'attach_post_id'     => 0,
		'overwrite_basename' => $overwrite !== '' ? $overwrite : '',
		'meta'               => [
			'fi_source_url' => $url,
		],
	]);

	$attachment_id = (int) ($img['id'] ?? 0);
	if ($attachment_id <= 0) {
		wp_send_json_error([
			'message' => 'Image fetch failed',
			'url'     => $url,
			'error'   => $img['error'] ?? null,
		]);
	}

	wp_send_json_success([
		'attachment_id' => $attachment_id,
		'url'           => $url,
	]);
}

/**
 * Handle legacy legislator image processing.
 *
 * @return void
 */
function fi_admin_ajax_process_legacy_legislator_images(): void {
	global $wpdb;

	$limit = absint($_POST['limit'] ?? 1);
	$limit = max(1, min(25, $limit));

	$processed = 0;
	$updated = 0;
	$skipped = 0;
	$errors = [];

	$local_dir = defined('FI_PATH_IMAGES') ? rtrim((string) FI_PATH_IMAGES, "/\\") : '';
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

		$legislator_id = (int) ($row['id'] ?? 0);
		$legacy_id = (string) ($row['legacy_id'] ?? '');
		$url = trim((string) ($row['legacy_image_url'] ?? ''));
		$image_id = (int) ($row['image_id'] ?? 0);
		$display_name = trim((string) ($row['display_name'] ?? ''));

		$err_ctx = [
			'legislator_id'    => $legislator_id,
			'legacy_id'        => $legacy_id,
			'display_name'     => $display_name,
			'legacy_image_url' => $url,
		];

		if ($image_id > 0) {
			$wpdb->update(
				$wpdb->prefix . 'fi_legislators',
				['legacy_image_url' => null],
				['id' => $legislator_id],
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
			$errors[] = $err_ctx + ['stage' => 'derive_gov', 'error' => 'Cannot derive gov from legacy_id'];
			$processed++;
			continue;
		}

		if ($url === '' || !preg_match('#^https?://#i', $url)) {
			$errors[] = $err_ctx + ['stage' => 'validate_url', 'error' => 'Invalid legacy_image_url'];
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
			$errors[] = $err_ctx + ['stage' => 'local_dir', 'error' => 'Local image directory not configured/writable (FI_PATH_IMAGES)', 'local_dir' => $local_dir];
			$processed++;
			continue;
		}

		$dest_name = fi_admin_ajax_build_legislator_image_filename($legislator_id, $basename, '-');
		$dest_abs = $local_dir . DIRECTORY_SEPARATOR . $dest_name;

		$src_candidates = [
			$local_dir . DIRECTORY_SEPARATOR . sanitize_file_name($legislator_id . '-' . $basename),
			$local_dir . DIRECTORY_SEPARATOR . sanitize_file_name($legislator_id . '_' . $basename),
			$local_dir . DIRECTORY_SEPARATOR . sanitize_file_name($gov . '_' . $legislator_id . '_' . $basename),
			$local_dir . DIRECTORY_SEPARATOR . $basename,
		];

		$have_local = false;
		foreach ($src_candidates as $src) {
			if ($src !== '' && file_exists($src)) {
				if (realpath($src) !== realpath($dest_abs)) {
					if (!file_exists($dest_abs)) {
						$moved = @rename($src, $dest_abs);
						if (!$moved && @copy($src, $dest_abs)) {
							@unlink($src);
						}
					}
				}
				$have_local = file_exists($dest_abs) && is_file($dest_abs);
				break;
			}
		}

		if (!$have_local) {
			$missing = 'MISSING:' . $url;
			$wpdb->update(
				$wpdb->prefix . 'fi_legislators',
				['legacy_image_url' => $missing],
				['id' => $legislator_id],
				['%s'],
				['%d']
			);

			$errors[] = $err_ctx + [
				'stage'                   => 'missing_local',
				'error'                   => 'Local file not found in FI_PATH_IMAGES; marked as missing and skipped',
				'dest_name'               => $dest_name,
				'dest_abs'                => $dest_abs,
				'legacy_image_url_marked' => $missing,
			];
			$processed++;
			continue;
		}

		$img = fi_admin_ajax_register_local_image($dest_abs, $dest_name, $public_url_base, [
			'desc'       => '',
			'post_title' => trim($gov . ' ' . $display_name . ' ' . (string) $legislator_id),
			'post_name'  => (string) $legislator_id . '-' . (string) $basename,
			'meta'       => [
				'fi_source_url'    => $url,
				'fi_source'        => 'legacy_image_url',
				'fi_legislator_id' => $legislator_id,
				'fi_gov'           => $gov,
			],
		]);

		$attachment_id = (int) ($img['id'] ?? 0);
		if ($attachment_id <= 0) {
			$errors[] = $err_ctx + [
				'stage'       => 'fi_media_import_local_image',
				'error'       => 'Image import failed',
				'media_error' => $img['error'] ?? null,
				'dest_name'   => $dest_name,
				'dest_abs'    => $dest_abs,
			];
			$processed++;
			continue;
		}

		$wpdb->update(
			$wpdb->prefix . 'fi_legislators',
			[
				'image_id'         => $attachment_id,
				'legacy_image_url' => null,
			],
			['id' => $legislator_id],
			['%d', '%s'],
			['%d']
		);

		$processed++;
		$updated++;
	}

	wp_send_json_success([
		'processed' => $processed,
		'updated'   => $updated,
		'skipped'   => $skipped,
		'errors'    => $errors,
		'local_dir' => $local_dir,
	]);
}

/**
 * Get vote preview HTML.
 *
 * @return void
 */
function fi_admin_ajax_get_vote_preview(): void {
	$vote_id = absint($_POST['vote_id'] ?? 0);
	if (!$vote_id) {
		wp_send_json_error('Invalid vote ID');
	}

	$vote = function_exists('fi_vote_get') ? fi_vote_get($vote_id) : null;
	if (!$vote) {
		wp_send_json_error('Vote not found');
	}

	$meta = function_exists('fi_vote_decode_meta') ? fi_vote_decode_meta($vote) : [];

	$formatted_date = '';
	if (!empty($vote['date_voted'])) {
		$date_obj = DateTime::createFromFormat('Y-m-d', (string) $vote['date_voted']) ?: DateTime::createFromFormat('Y-m-d H:i:s', (string) $vote['date_voted']);
		$formatted_date = $date_obj ? $date_obj->format('m/d/Y') : (string) $vote['date_voted'];
	}

	ob_start();
	?>
	<div class="fi-vote-preview-content">
		<h5 class="mb-3"><?php echo esc_html($vote['title'] ?? 'Untitled Vote'); ?></h5>

		<div class="mb-3">
			<div class="row g-2">
				<?php if (!empty($vote['bill_number'])): ?>
					<div class="col-12"><strong>Bill:</strong> <?php echo esc_html($vote['bill_number']); ?></div>
				<?php endif; ?>

				<?php if ($formatted_date): ?>
					<div class="col-12"><strong>Date:</strong> <?php echo esc_html($formatted_date); ?></div>
				<?php endif; ?>

				<div class="col-12">
					<strong>Constitutional Position:</strong>
					<span class="badge bg-<?php echo ($vote['constitutional'] === 'Y') ? 'success' : 'danger'; ?>">
						<?php echo esc_html(($vote['constitutional'] === 'Y') ? 'Yea' : 'Nay'); ?>
					</span>
				</div>

				<?php if (!empty($meta['cost'])): ?>
					<div class="col-12">
						<strong>Estimated Cost Per Household:</strong>
						<span class="text-<?php echo (strpos((string) $meta['cost'], '+') === 0) ? 'success' : 'danger'; ?>">
							$<?php echo esc_html(str_replace('+', '', (string) $meta['cost'])); ?>
						</span>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<?php
		$sections = [
			'Short Description (Effect on You)' => function_exists('fi_vote_get_description') ? fi_vote_get_description($meta, 'scorecard') : '',
			'Medium Description'                => $meta['description_medium'] ?? '',
			'Excerpt Description (Details)'     => $meta['description_excerpt'] ?? '',
			'Long Description'                  => $meta['description_long'] ?? '',
		];
		?>

		<?php foreach ($sections as $title => $content): ?>
			<div class="mb-3">
				<h6><?php echo esc_html($title); ?></h6>
				<div<?php echo ($title === 'Short Description (Effect on You)') ? ' class="small"' : ''; ?>>
					<?php echo !empty($content) ? wp_kses_post(wpautop((string) $content)) : '<span class="text-muted">--</span>'; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<div class="mb-3">
			<h6>Rollcall Data Description</h6>
			<div class="small">
				<?php
				$rollcall_description = '';
				if (!empty($vote['rollcall_data'])) {
					$rollcall_data = json_decode((string) $vote['rollcall_data'], true);
					if (is_array($rollcall_data) && !empty($rollcall_data['description'])) {
						$rollcall_description = (string) $rollcall_data['description'];
					}
				}
				echo $rollcall_description !== '' ? wp_kses_post(wpautop($rollcall_description)) : '<span class="text-muted">--</span>';
				?>
			</div>
		</div>
	</div>
	<?php
	wp_send_json_success(['html' => ob_get_clean()]);
}

/**
 * Handle API data fetch for legislator edit screen.
 *
 * @return void
 */
function fi_admin_ajax_fetch_api_data(): void {
	$legislator_id = absint($_POST['legislator_id'] ?? 0);
	$source = sanitize_key((string) ($_POST['source'] ?? ''));

	if (!$legislator_id) {
		wp_send_json_error('Invalid legislator ID');
	}

	$legislator = function_exists('fi_legislator_get') ? fi_legislator_get($legislator_id) : null;
	if (!$legislator) {
		wp_send_json_error('Legislator not found');
	}

	if ($source === 'all') {
		$api_data = function_exists('fi_api_fetch_all') ? fi_api_fetch_all($legislator_id) : [];
	} else {
		$api_data = fi_admin_ajax_fetch_single_api_source($legislator_id, $legislator, $source);
	}

	$api_meta_patch = [];
	foreach ($api_data as $src => $data) {
		$src_key = sanitize_key((string) $src);
		if ($src_key !== '') {
			$api_meta_patch['api_' . $src_key] = [
				'fetched_at' => current_time('mysql'),
				'data'       => $data,
			];
		}
	}

	if (!empty($api_meta_patch) && function_exists('fi_legislator_save')) {
		fi_legislator_save(['meta' => $api_meta_patch], $legislator_id);
	}

	$comparisons = [];
	foreach ($api_data as $src => $data) {
		if (is_array($data) && !empty($data) && empty($data['_fi_error']) && function_exists('fi_api_compare')) {
			$comparisons[$src] = fi_api_compare($legislator_id, $src, $data);
		}
	}

	wp_send_json_success([
		'api_data'    => $api_data,
		'comparisons' => $comparisons,
	]);
}

/**
 * Fetch a single API source for a legislator.
 *
 * @param int $legislator_id Legislator ID.
 * @param object $legislator Legislator object.
 * @param string $source Source key.
 * @return array API data keyed by source.
 */
function fi_admin_ajax_fetch_single_api_source(int $legislator_id, object $legislator, string $source): array {
	$api_data = [];

	switch ($source) {
		case 'votesmart':
			if (empty($legislator['votesmart_id'])) {
				$api_data['votesmart'] = ['_fi_error' => true, 'error' => 'Missing VoteSmart ID for this legislator.'];
				break;
			}

			$votesmart_key = function_exists('fi_get_api_key') ? fi_get_api_key('votesmart_key', 'API_KEY_VOTESMART') : '';
			if (empty($votesmart_key)) {
				$api_data['votesmart'] = ['_fi_error' => true, 'error' => 'Missing VoteSmart API key. Configure it in FI Settings first.'];
				break;
			}

			$api_data['votesmart'] = function_exists('fi_api_fetch_votesmart') ? fi_api_fetch_votesmart($legislator['votesmart_id']) : ['_fi_error' => true, 'error' => 'VoteSmart fetch helper unavailable.'];
			break;

		case 'govtrack':
			if (!empty($legislator['govtrack_id']) && function_exists('fi_api_fetch_govtrack')) {
				$api_data['govtrack'] = fi_api_fetch_govtrack($legislator['govtrack_id']);
			} elseif (!empty($legislator['bioguide_id']) && function_exists('fi_api_fetch_govtrack_by_bioguide')) {
				$api_data['govtrack'] = fi_api_fetch_govtrack_by_bioguide($legislator['bioguide_id']);
			} else {
				$api_data['govtrack'] = ['_fi_error' => true, 'error' => 'Missing GovTrack ID / Bioguide ID for this legislator.'];
			}
			break;

		case 'legiscan_local':
			$api_data['legiscan_local'] = fi_admin_ajax_fetch_legiscan_local_person($legislator);
			break;

		default:
			if ($source !== '') {
				$api_data[$source] = ['_fi_error' => true, 'error' => 'Unsupported API source.'];
			}
			break;
	}

	return $api_data;
}

/**
 * Fetch cached local LegiScan person data.
 *
 * @param object $legislator Legislator object.
 * @return array Person data or error array.
 */
function fi_admin_ajax_fetch_legiscan_local_person(object $legislator): array {
	$legiscan_id = (int) ($legislator['legiscan_id'] ?? 0);
	$cache_rel = trim(sanitize_text_field((string) wp_unslash($_POST['cache_rel'] ?? '')));

	if ($cache_rel !== '') {
		$result = fi_admin_ajax_fetch_legiscan_local_person_by_cache_rel($cache_rel);
		return fi_admin_ajax_normalize_legiscan_person_payload($result);
	}

	$session_folder = '';
	$session_gov = strtoupper((string) ($legislator['gov'] ?? ''));
	$sessions = is_array($legislator['sessions'] ?? null) ? $legislator['sessions'] : [];

	if (!empty($sessions)) {
		$latest_session = reset($sessions);
		if (is_object($latest_session)) {
			$session_id = (int) ($latest_session->session_id ?? 0);
			if ($session_id && function_exists('fi_session_get')) {
				$session_obj = fi_session_get($session_id);
				$session_meta = is_object($session_obj) ? ($session_obj->meta ?? null) : null;
				if (is_string($session_meta)) {
					$session_meta = json_decode($session_meta, true);
				}
				if (is_array($session_meta)) {
					$session_folder = (string) ($session_meta['legiscan_folder'] ?? '');
					if ($session_folder === '' && isset($session_meta['legiscan_data']) && is_array($session_meta['legiscan_data'])) {
						$session_folder = (string) ($session_meta['legiscan_data']['directory'] ?? '');
					}
				}
			}

			if ($session_folder === '') {
				$session_folder = (string) ($latest_session->session_slug ?? '');
			}

			if (!empty($latest_session->gov ?? '')) {
				$session_gov = strtoupper((string) $latest_session->gov);
			}
		}
	}

	if (!$legiscan_id || $session_folder === '' || $session_gov === '') {
		return ['_fi_error' => true, 'error' => 'Missing legiscan_id and/or latest session folder; cannot locate cached LegiScan people file.'];
	}

	$base = fi_admin_ajax_legiscan_base_dir();
	if ($base === '') {
		return ['_fi_error' => true, 'error' => 'LegiScan cache directory is not configured.'];
	}

	$file = $base . $session_gov . DIRECTORY_SEPARATOR . $session_folder . DIRECTORY_SEPARATOR . 'people' . DIRECTORY_SEPARATOR . $legiscan_id . '.json';

	if (!is_readable($file)) {
		return ['_fi_error' => true, 'error' => 'LegiScan cache file not found for latest session: ' . $session_gov . '/' . $session_folder . '/people/' . $legiscan_id . '.json'];
	}

	$raw = json_decode((string) @file_get_contents($file), true);
	$person = is_array($raw) ? ($raw['person'] ?? null) : null;

	return is_array($person)
		? fi_admin_ajax_normalize_legiscan_person_payload($person)
		: ['_fi_error' => true, 'error' => 'LegiScan cache file could not be parsed (missing person node).'];
}

/**
 * Get LegiScan base directory.
 *
 * @return string Base path with trailing slash.
 */
function fi_admin_ajax_legiscan_base_dir(): string {
	if (defined('FI_DIR_LEGISCAN')) {
		return rtrim(FI_DIR_LEGISCAN, '/\\') . DIRECTORY_SEPARATOR;
	}

	if (defined('FI_DIR_CACHE')) {
		return rtrim(FI_DIR_CACHE, '/\\') . DIRECTORY_SEPARATOR . 'legiscan' . DIRECTORY_SEPARATOR;
	}

	return '';
}

/**
 * Fetch LegiScan person by validated cache-relative path.
 *
 * @param string $cache_rel Cache-relative path without .json.
 * @return array Person or error.
 */
function fi_admin_ajax_fetch_legiscan_local_person_by_cache_rel(string $cache_rel): array {
	if (strpos($cache_rel, '..') !== false || !preg_match('#^[A-Z]{2}/[A-Za-z0-9._-]+/people/[0-9]+$#', $cache_rel)) {
		return ['_fi_error' => true, 'error' => 'Invalid LegiScan cache path format: ' . $cache_rel];
	}

	$base = fi_admin_ajax_legiscan_base_dir();
	if ($base === '') {
		return ['_fi_error' => true, 'error' => 'LegiScan cache directory is not configured.'];
	}

	$file = $base . str_replace('/', DIRECTORY_SEPARATOR, $cache_rel) . '.json';
	$real_base = realpath($base);
	$real_file = realpath($file);

	if ($real_base && $real_file && strpos($real_file, $real_base) !== 0) {
		return ['_fi_error' => true, 'error' => 'Invalid LegiScan cache path (outside cache directory).'];
	}

	if (!is_readable($file)) {
		return ['_fi_error' => true, 'error' => 'LegiScan cache file not found: ' . $cache_rel . '.json'];
	}

	$raw = json_decode((string) @file_get_contents($file), true);
	$person = is_array($raw) ? ($raw['person'] ?? null) : null;

	return is_array($person)
		? $person
		: ['_fi_error' => true, 'error' => 'LegiScan cache file could not be parsed (missing person node).'];
}

/**
 * Normalize LegiScan person payload.
 *
 * @param array $person Person payload.
 * @return array Normalized person payload.
 */
function fi_admin_ajax_normalize_legiscan_person_payload(array $person): array {
	if (!empty($person['_fi_error'])) {
		return $person;
	}

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

	return $person;
}

/**
 * Handle AJAX sub-actions.
 *
 * @param string $sub_action Sub-action key.
 * @return void
 */
function fi_admin_ajax_handle_sub_action(string $sub_action): void {
	switch ($sub_action) {
		case 'fetch_legislator_image_url':
			fi_admin_ajax_fetch_legislator_image_url();
			break;

		case 'upload_legislator_image':
			fi_admin_ajax_upload_legislator_image();
			break;

		case 'fetch_remote_image':
			fi_admin_ajax_fetch_remote_image();
			break;

		case 'process_legacy_legislator_images':
			fi_admin_ajax_process_legacy_legislator_images();
			break;

		case 'search_legislators':
			$query = sanitize_text_field((string) wp_unslash($_POST['query'] ?? ''));
			$results = function_exists('fi_admin_legislators_search') ? fi_admin_legislators_search($query) : [];
			wp_send_json_success($results);
			break;

		case 'get_roll_call_data':
			$vote_id = absint($_POST['vote_id'] ?? 0);
			if (!$vote_id) {
				wp_send_json_error('Invalid vote ID');
			}
			wp_send_json_success([
				'rollcalls' => function_exists('fi_rollcalls_get_by_vote') ? fi_rollcalls_get_by_vote($vote_id) : [],
				'summary'   => function_exists('fi_rollcall_summary') ? fi_rollcall_summary($vote_id) : [],
			]);
			break;

		case 'save_rollcall':
			fi_admin_ajax_save_rollcall();
			break;

		case 'import_legiscan_rollcall':
			fi_admin_ajax_import_legiscan_rollcall();
			break;

		case 'get_votes_by_session':
			fi_admin_ajax_get_votes_by_session();
			break;

		case 'get_vote_preview':
			fi_admin_ajax_get_vote_preview();
			break;

		case 'generate_report_preview':
			$votes = $_POST['votes'] ?? [];
			$options = $_POST['options'] ?? [];
			if (empty($votes)) {
				wp_send_json_error('No votes selected');
			}
			$preview_html = function_exists('fi_admin_reports_generate_preview_html') ? fi_admin_reports_generate_preview_html($votes, $options) : '';
			wp_send_json_success(['html' => $preview_html]);
			break;

		case 'generate_report_pdf':
			$form_data = (string) wp_unslash($_POST['data'] ?? '');
			parse_str($form_data, $data);
			if (empty($data['selected_votes'])) {
				wp_send_json_error('No votes selected');
			}
			$pdf_url = function_exists('fi_admin_reports_generate_pdf') ? fi_admin_reports_generate_pdf($data) : '';
			$pdf_url ? wp_send_json_success(['pdf_url' => $pdf_url]) : wp_send_json_error('Failed to generate PDF');
			break;

		case 'fetch_api_data':
			fi_admin_ajax_fetch_api_data();
			break;

		case 'update_from_api':
			$legislator_id = absint($_POST['legislator_id'] ?? 0);
			$source = sanitize_key((string) ($_POST['source'] ?? ''));
			$updates = $_POST['updates'] ?? [];
			if (!$legislator_id || $source === '' || !is_array($updates)) {
				wp_send_json_error('Invalid data');
			}
			$updated = function_exists('fi_admin_legislators_apply_api_updates') ? fi_admin_legislators_apply_api_updates($legislator_id, $source, $updates) : 0;
			wp_send_json_success([
				'updated' => $updated,
				'message' => "Updated {$updated} field(s) from {$source}",
			]);
			break;

		case 'purge_cached_sessions':
			fi_admin_ajax_purge_cached_sessions();
			break;

		case 'sync_cached_sessions_batch':
			fi_admin_ajax_sync_cached_sessions_batch();
			break;

		case 'sync_legislators_by_ids':
			fi_admin_ajax_sync_legislators_by_ids();
			break;

		case 'sync_cached_sessions':
			fi_admin_ajax_sync_cached_sessions();
			break;

		case 'compile_legiscan_data':
			fi_admin_ajax_compile_legiscan_data();
			break;

		default:
			wp_send_json_error('Unknown action');
	}
}

/**
 * Save roll-call edits.
 *
 * @return void
 */
function fi_admin_ajax_save_rollcall(): void {
	global $wpdb;

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
		$cast = sanitize_text_field((string) ($roll_call['cast'] ?? ''));
		$cast = ($cast !== '' && function_exists('fi_rollcall_cast_normalize')) ? fi_rollcall_cast_normalize($cast) : $cast;

		if (!$rollcall_id || !$legislator_id || $cast === '') {
			$skipped++;
			continue;
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'fi_voterc',
			[
				'cast'        => $cast,
				'is_override' => !empty($roll_call['is_override']) ? 1 : 0,
			],
			[
				'id'            => $rollcall_id,
				'vote_id'       => $vote_id,
				'legislator_id' => $legislator_id,
			],
			['%s', '%d'],
			['%d', '%d', '%d']
		);

		if ($result !== false) {
			$saved++;
		}
	}

	wp_send_json_success([
		'saved'   => $saved,
		'skipped' => $skipped,
		'summary' => function_exists('fi_rollcall_summary') ? fi_rollcall_summary($vote_id) : [],
	]);
}

/**
 * Import LegiScan roll-call payload.
 *
 * @return void
 */
function fi_admin_ajax_import_legiscan_rollcall(): void {
	$vote_id = absint($_POST['vote_id'] ?? 0);
	$gov = strtoupper(sanitize_key((string) ($_POST['gov'] ?? '')));
	$payload = wp_unslash($_POST['payload'] ?? '');

	if (!$vote_id || $gov === '' || $payload === '') {
		wp_send_json_error('Missing vote, government, or payload data.');
	}

	$imported = function_exists('fi_rollcall_import') ? fi_rollcall_import($vote_id, $payload, $gov) : 0;

	wp_send_json_success([
		'imported'  => $imported,
		'rollcalls' => function_exists('fi_rollcalls_get_by_vote') ? fi_rollcalls_get_by_vote($vote_id) : [],
		'summary'   => function_exists('fi_rollcall_summary') ? fi_rollcall_summary($vote_id) : [],
	]);
}

/**
 * Get votes by session for admin selectors.
 *
 * @return void
 */
function fi_admin_ajax_get_votes_by_session(): void {
	$session_id = absint($_POST['session_id'] ?? 0);
	if (!$session_id) {
		wp_send_json_error('Invalid session ID');
	}

	$votes = function_exists('fi_votes_get_by_session') ? fi_votes_get_by_session($session_id, ['status' => null, 'cache' => false]) : [];
	$formatted = array_map(static function($vote) {
		return [
			'id'             => (int) $vote['id'],
			'title'          => $vote['title'],
			'bill_number'    => $vote['bill_number'] ?? '',
			'chamber'        => $vote['chamber'],
			'date_voted'     => $vote['date_voted'] ?? $vote['date'] ?? '',
			'constitutional' => $vote['constitutional'],
		];
	}, is_array($votes) ? $votes : []);

	wp_send_json_success(['votes' => $formatted]);
}

/**
 * Compile LegiScan data through external compiler endpoint.
 *
 * @return void
 */
function fi_admin_ajax_compile_legiscan_data(): void {
	$gov = strtoupper(sanitize_key((string) ($_POST['gov'] ?? '')));
	$session_id = absint($_POST['session_id'] ?? 0);
	$data_dir = sanitize_text_field((string) wp_unslash($_POST['data_dir'] ?? ''));
	$compile_action = sanitize_key((string) ($_POST['compile_action'] ?? 'start'));

	if ($gov === '' || !$session_id || $data_dir === '') {
		wp_send_json_error('Missing required parameters');
	}

	if ($compile_action === 'start') {
		$compiler_url = content_url('/jbsfi/legiscan/compile_data.php');
		$response = wp_remote_post($compiler_url, [
			'body'    => wp_json_encode([
				'auth_key'   => 'legiscan_compile_2024_v1',
				'gov'        => $gov,
				'session_id' => $session_id,
				'data_dir'   => $data_dir,
				'action'     => 'start',
			]),
			'headers' => ['Content-Type' => 'application/json'],
			'timeout' => 2,
		]);

		wp_send_json_success([
			'message' => 'Compilation started',
			'posted'  => !is_wp_error($response),
		]);
	}

	if ($compile_action === 'status') {
		$status_file = rtrim($data_dir, '/\\') . DIRECTORY_SEPARATOR . '__compile_status.json';
		if (!file_exists($status_file)) {
			wp_send_json_success([
				'status'       => 'not_started',
				'progress'     => 0,
				'current_step' => 'Not started',
			]);
		}

		$status = json_decode((string) file_get_contents($status_file), true);
		wp_send_json_success(is_array($status) ? $status : []);
	}

	wp_send_json_error('Invalid action');
}
/**
 * Bulk-sync cached session fields on fi_legislators.
 * AJAX handler for action=sync_cached_sessions.
 *
 * POST params:
 *   gov  (optional) – limit to one gov code, e.g. 'US' or 'WI'
 *
 * @return void
 */
function fi_admin_ajax_sync_cached_sessions(): void {
if (!current_user_can('manage_options')) {
wp_send_json_error('Unauthorized');
}

$gov = !empty($_POST['gov']) ? strtoupper(sanitize_key((string) $_POST['gov'])) : null;

$count = fi_legislators_sync_cached_sessions($gov);

wp_send_json_success([
'updated' => $count,
'gov'     => $gov ?? 'all',
'message' => "Synced cached session data for {$count} legislators" . ($gov ? " in gov {$gov}" : '') . '.',
]);
}

/**
 * Purge cached session fields from fi_legislators (step 1 of full rebuild).
 * POST params: gov (optional)
 */
function fi_admin_ajax_purge_cached_sessions(): void {
if (!current_user_can('manage_options')) {
wp_send_json_error('Unauthorized');
}

$count = fi_legislators_purge_cached_sessions(null);
$total = fi_legislators_count_uncached();

wp_send_json_success([
'purged'    => $count,
'remaining' => $total,
'message'   => "Purged {$count} rows. {$total} legislators ready to rebuild.",
]);
}

/**
 * Process one batch of the rebuild (step 2, called repeatedly from JS).
 * POST params:
 *   gov        (optional) – constrain to one gov
 *   batch_size (optional) – default 25
 *
 * Returns remaining count so JS knows when to stop.
 */
function fi_admin_ajax_sync_cached_sessions_batch(): void {
if (!current_user_can('manage_options')) {
wp_send_json_error('Unauthorized');
}

$batch_size = min(absint($_POST['batch_size'] ?? 25), 100);

$rows_written = fi_legislators_sync_missing_cached_sessions($batch_size, true);
$updated = count($rows_written);
$remaining = fi_legislators_count_uncached();

wp_send_json_success([
'updated'   => $updated,
'remaining' => $remaining,
'done'      => $remaining === 0,
'rows'      => $rows_written,
]);
}


/**
 * Process explicit legislator IDs passed from JS (one-pass cache builder).
 * POST params: ids[] — array of legislator IDs to process.
 */
function fi_admin_ajax_sync_legislators_by_ids(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	global $wpdb;

	$raw_ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? $_POST['ids'] : [];
	$ids     = array_values( array_unique( array_map( 'intval', $raw_ids ) ) );
	if ( empty( $ids ) ) {
		wp_send_json_error( 'No IDs provided' );
	}

	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
				ls.legislator_id,
				ls.session_id,
				ls.gov,
				ls.state,
				ls.chamber,
				ls.district,
				ls.party,
				l.display_name
			FROM {$wpdb->prefix}fi_legislator_sessions ls
			INNER JOIN {$wpdb->prefix}fi_sessions s
				ON s.id = ls.session_id AND s.parent_id IS NULL
			INNER JOIN {$wpdb->prefix}fi_legislators l
				ON l.id = ls.legislator_id
			INNER JOIN (
				SELECT ls2.legislator_id,
					MAX( CONCAT(
						LPAD( UNIX_TIMESTAMP( COALESCE( s2.date_start, '9999-12-31' ) ), 12, '0' ),
						LPAD( s2.id, 12, '0' )
					) ) AS best_key
				FROM {$wpdb->prefix}fi_legislator_sessions ls2
				INNER JOIN {$wpdb->prefix}fi_sessions s2
					ON s2.id = ls2.session_id AND s2.parent_id IS NULL
				WHERE ls2.legislator_id IN ($placeholders)
				GROUP BY ls2.legislator_id
			) ranked
				ON ranked.legislator_id = ls.legislator_id
				AND CONCAT(
					LPAD( UNIX_TIMESTAMP( COALESCE( s.date_start, '9999-12-31' ) ), 12, '0' ),
					LPAD( s.id, 12, '0' )
				) = ranked.best_key
			WHERE ls.legislator_id IN ($placeholders)",
			...$ids,
			...$ids
		),
		ARRAY_A
	);

	$by_id = [];
	foreach ( $rows as $row ) {
		$by_id[ (int) $row['legislator_id'] ] = $row;
	}

	$results = [];
	foreach ( $ids as $lid ) {
		if ( isset( $by_id[ $lid ] ) ) {
			$row    = $by_id[ $lid ];
			$cached = implode( ' | ', array_filter( [
				$row['gov'], $row['state'], $row['chamber'],
				$row['district'], $row['party'], 'session:' . $row['session_id'],
			] ) );
			$ok = $wpdb->update(
				$wpdb->prefix . 'fi_legislators',
				[
					'session_id' => (int) $row['session_id'],
					'gov'        => $row['gov'],
					'state'      => $row['state'],
					'chamber'    => $row['chamber'],
					'district'   => $row['district'],
					'party'      => $row['party'],
				],
				[ 'id' => $lid ],
				[ '%d', '%s', '%s', '%s', '%s', '%s' ],
				[ '%d' ]
			);
			$results[] = [
				'id'      => $lid,
				'status'  => $ok !== false ? 'ok' : 'error',
				'cached'  => $ok !== false ? $cached : '',
				'skipped' => false,
			];
		} else {
			$wpdb->update(
				$wpdb->prefix . 'fi_legislators',
				[ 'session_id' => 0 ],
				[ 'id' => $lid ],
				[ '%d' ],
				[ '%d' ]
			);
			$results[] = [
				'id'      => $lid,
				'status'  => 'skip',
				'cached'  => '',
				'skipped' => true,
				'reason'  => 'no session assignments',
			];
		}
	}

	wp_send_json_success( [ 'results' => $results ] );
}

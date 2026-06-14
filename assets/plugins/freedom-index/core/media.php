<?php
/**
 * Freedom Index Core Media Helpers
 *
 * Canonical media/download/import/attachment helper file.
 *
 * This file consolidates reusable media logic from:
 * - /core/media.php
 * - /public/autoload/image.php media-processing helpers
 * - /admin/autoload image helper logic
 *
 * Responsibilities:
 * - Load WP media/file/image includes when needed.
 * - Sideload remote images into the Media Library.
 * - Import/register local image files.
 * - Overwrite existing attachments by basename.
 * - Track original remote URLs.
 * - Support externally registered attachment paths/URLs.
 * - Resolve legislator/session/career image URLs.
 * - Validate remote image URLs and inspect image metadata.
 * - Provide compatibility wrappers for old public image processing helpers.
 *
 * Display-only helpers should remain in /public/autoload/image.php:
 * - initials/color placeholder generation
 * - placeholder HTML rendering
 * - public-facing image card/display fallbacks
 */

if (!defined('ABSPATH')) exit;

/**
 * Load WordPress media/file includes required for sideload/import operations.
 *
 * @return void
 */
function fi_media_require_wp_files(): void {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
}

/**
 * Media-scoped log helper.
 *
 * @param string $message Log message.
 * @param string $file Optional file path.
 * @param int $line Optional line number.
 * @param string $level Log level.
 * @return bool
 */
function fi_media_log(string $message, string $file = '', int $line = 0, string $level = 'debug'): bool {
	// Enable if needed for media debugging.
	// return function_exists('fi_log_area') ? fi_log_area('images', $message, $file, $line, $level) : false;
	return false;
}

/**
 * Return a callback that limits generated image sizes to thumbnail only.
 *
 * @param bool $generate_thumbnail Whether to generate thumbnail size.
 * @return callable
 */
function fi_media_thumbnail_only_filter(bool $generate_thumbnail = true): callable {
	return static function($sizes) use ($generate_thumbnail) {
		if (!$generate_thumbnail || !is_array($sizes)) {
			return [];
		}

		return array_intersect_key($sizes, ['thumbnail' => true]);
	};
}

/**
 * Apply scalar attachment postmeta.
 *
 * @param int $attachment_id Attachment ID.
 * @param array $meta Meta key/value pairs.
 * @return void
 */
function fi_media_update_attachment_meta_values(int $attachment_id, array $meta): void {
	foreach ($meta as $k => $v) {
		if (is_scalar($v) || $v === null) {
			update_post_meta($attachment_id, (string) $k, $v);
		}
	}
}

/**
 * Update attachment title and slug when provided.
 *
 * @param int $attachment_id Attachment ID.
 * @param string $post_title Optional post title.
 * @param string $post_name Optional post slug.
 * @return void
 */
function fi_media_update_attachment_post_fields(int $attachment_id, string $post_title = '', string $post_name = ''): void {
	$upd = ['ID' => $attachment_id];

	if ($post_title !== '') {
		$upd['post_title'] = sanitize_text_field($post_title);
	}

	if ($post_name !== '') {
		$upd['post_name'] = sanitize_title($post_name);
	}

	if (count($upd) > 1) {
		wp_update_post($upd);
	}
}

/**
 * Get a safe image file extension from a URL path.
 *
 * @param string $url Image URL.
 * @return string|null Extension without dot.
 */
function fi_media_file_extension_from_url(string $url): ?string {
	$path = parse_url($url, PHP_URL_PATH);
	$extension = $path ? strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) : '';

	$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
	return in_array($extension, $allowed_extensions, true) ? $extension : null;
}

/**
 * Build safe remote image filename.
 *
 * @param string $image_url Remote image URL.
 * @param string $alt_text Alt/title text.
 * @return string Filename.
 */
function fi_media_build_remote_image_filename(string $image_url, string $alt_text = ''): string {
	$file_extension = fi_media_file_extension_from_url($image_url) ?: 'jpg';
	$base = sanitize_file_name($alt_text);

	if ($base === '' || $base === '-') {
		$base = 'freedom-index-image';
	}

	return $base . '-' . wp_unique_id() . '.' . $file_extension;
}

/**
 * Find an attachment by attached-file basename.
 *
 * @param string $basename File basename.
 * @return int Attachment ID or 0.
 */
function fi_media_find_attachment_by_basename(string $basename): int {
	global $wpdb;

	$basename = sanitize_file_name($basename);
	if ($basename === '') {
		return 0;
	}

	$like = '%' . $wpdb->esc_like('/' . $basename) . '%';

	return (int) $wpdb->get_var($wpdb->prepare(
		"SELECT post_id
		FROM {$wpdb->postmeta}
		WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s
		ORDER BY post_id DESC
		LIMIT 1",
		$like
	));
}

/**
 * Get attachment ID by original remote URL.
 *
 * Supports both legacy `_fi_original_url` and optional external URL meta.
 *
 * @param string $url Original image URL.
 * @return int|null Attachment ID or null.
 */
function fi_media_get_attachment_by_original_url(string $url): ?int {
	global $wpdb;

	$url = esc_url_raw($url);
	if ($url === '') {
		return null;
	}

	$attachment_id = $wpdb->get_var($wpdb->prepare(
		"SELECT post_id
		FROM {$wpdb->postmeta}
		WHERE meta_key IN ('_fi_original_url', 'fi_external_url')
		AND meta_value = %s
		ORDER BY post_id DESC
		LIMIT 1",
		$url
	));

	return $attachment_id ? (int) $attachment_id : null;
}

/**
 * Check whether a remote URL returns HTTP 200.
 *
 * Uses WordPress HTTP transport instead of get_headers().
 *
 * @param string $url URL to check.
 * @return bool True if URL returns HTTP 200.
 */
function fi_media_url_exists(string $url): bool {
	$url = esc_url_raw($url);
	if ($url === '') {
		return false;
	}

	$response = wp_remote_head($url, [
		'timeout'     => 10,
		'redirection' => 3,
	]);

	if (is_wp_error($response)) {
		return false;
	}

	return (int) wp_remote_retrieve_response_code($response) === 200;
}

/**
 * Validate that a remote URL is accessible and appears to be an image.
 *
 * @param string $url Image URL.
 * @return bool True if URL is valid, accessible, and image-like.
 */
function fi_media_validate_image_url(string $url): bool {
	$url = esc_url_raw($url);
	if ($url === '') {
		return false;
	}

	if (!filter_var($url, FILTER_VALIDATE_URL)) {
		return false;
	}

	$response = wp_remote_head($url, [
		'timeout'     => 10,
		'redirection' => 3,
	]);

	if (is_wp_error($response)) {
		return false;
	}

	if ((int) wp_remote_retrieve_response_code($response) !== 200) {
		return false;
	}

	$content_type = (string) wp_remote_retrieve_header($response, 'content-type');
	return str_starts_with(strtolower($content_type), 'image/');
}

/**
 * Get dimensions for a remote image URL.
 *
 * @param string $image_url Remote image URL.
 * @return array|null Array with width, height, mime_type or null.
 */
function fi_media_get_remote_image_dimensions(string $image_url): ?array {
	$image_url = esc_url_raw($image_url);
	if ($image_url === '') {
		return null;
	}

	$response = wp_safe_remote_get($image_url, [
		'timeout'     => 15,
		'redirection' => 3,
	]);

	if (is_wp_error($response)) {
		return null;
	}

	if ((int) wp_remote_retrieve_response_code($response) !== 200) {
		return null;
	}

	$image_body = wp_remote_retrieve_body($response);
	if ($image_body === '') {
		return null;
	}

	$image_info = @getimagesizefromstring($image_body);
	if (!$image_info) {
		return null;
	}

	return [
		'width'     => (int) $image_info[0],
		'height'    => (int) $image_info[1],
		'mime_type' => (string) ($image_info['mime'] ?? ''),
	];
}

/**
 * Overwrite an existing attachment file and regenerate metadata.
 *
 * @param int $attachment_id Attachment ID.
 * @param string $source_file Source absolute file path.
 * @param callable $sizes_filter Image size filter callback.
 * @param string $post_title Optional post title.
 * @param string $post_name Optional post slug.
 * @param array $meta Attachment meta.
 * @return array Result array {id:int,error:mixed|null}.
 */
function fi_media_overwrite_existing_attachment(int $attachment_id, string $source_file, callable $sizes_filter, string $post_title = '', string $post_name = '', array $meta = []): array {
	$existing_file = get_attached_file($attachment_id);

	if (!is_string($existing_file) || $existing_file === '' || !file_exists($existing_file)) {
		return ['id' => 0, 'error' => ['type' => 'overwrite_target_missing', 'attachment_id' => $attachment_id]];
	}

	if (!@copy($source_file, $existing_file)) {
		return ['id' => 0, 'error' => ['type' => 'overwrite_copy_failed', 'src' => $source_file, 'dst' => $existing_file]];
	}

	add_filter('intermediate_image_sizes_advanced', $sizes_filter, 999);
	$meta_data = wp_generate_attachment_metadata($attachment_id, $existing_file);
	remove_filter('intermediate_image_sizes_advanced', $sizes_filter, 999);

	if (is_array($meta_data)) {
		wp_update_attachment_metadata($attachment_id, $meta_data);
	}

	fi_media_update_attachment_post_fields($attachment_id, $post_title, $post_name);
	fi_media_update_attachment_meta_values($attachment_id, $meta);

	return ['id' => $attachment_id, 'error' => null];
}

/**
 * Sideload or overwrite-by-basename an image from a remote URL into the WP Media Library.
 *
 * @param string $url Remote URL.
 * @param string $preferred_filename Basename to use when creating a new attachment.
 * @param array $opts Options.
 * @return array { id:int, error:mixed|null }
 */
function fi_media_sideload_image_from_url(string $url, string $preferred_filename, array $opts = []): array {
	$url = esc_url_raw($url);
	if ($url === '' || !preg_match('#^https?://#i', $url)) {
		return ['id' => 0, 'error' => ['type' => 'invalid_url', 'url' => $url]];
	}

	$timeout = isset($opts['timeout']) ? (int) $opts['timeout'] : 30;
	$redirection = isset($opts['redirection']) ? (int) $opts['redirection'] : 5;
	$desc = (string) ($opts['desc'] ?? '');
	$post_title = (string) ($opts['post_title'] ?? '');
	$post_name = (string) ($opts['post_name'] ?? '');
	$attach_post_id = isset($opts['attach_post_id']) ? (int) $opts['attach_post_id'] : 0;
	$overwrite_basename = sanitize_file_name((string) ($opts['overwrite_basename'] ?? ''));
	$generate_thumbnail = array_key_exists('generate_thumbnail', $opts) ? (bool) $opts['generate_thumbnail'] : true;
	$meta = $opts['meta'] ?? [];

	if (!is_array($meta)) {
		$meta = [];
	}

	$meta['_fi_original_url'] = $url;

	$preferred_filename = sanitize_file_name($preferred_filename);
	if ($preferred_filename === '') {
		$preferred_filename = fi_media_build_remote_image_filename($url, $post_title ?: $desc);
	}

	fi_media_require_wp_files();

	$only_thumbnail_sizes = fi_media_thumbnail_only_filter($generate_thumbnail);

	$res = wp_safe_remote_get($url, [
		'timeout'     => max(1, $timeout),
		'redirection' => max(0, $redirection),
		'user-agent'  => 'FreedomIndex/RemoteImage; ' . home_url('/'),
	]);

	if (is_wp_error($res)) {
		return [
			'id'    => 0,
			'error' => [
				'type'    => 'wp_error',
				'code'    => $res->get_error_code(),
				'message' => $res->get_error_message(),
				'data'    => $res->get_error_data(),
			],
		];
	}

	$status = (int) wp_remote_retrieve_response_code($res);
	$body = (string) wp_remote_retrieve_body($res);

	if ($status < 200 || $status >= 300) {
		return [
			'id'    => 0,
			'error' => [
				'type'           => 'http_error',
				'status'         => $status,
				'content_type'   => wp_remote_retrieve_header($res, 'content-type'),
				'content_length' => wp_remote_retrieve_header($res, 'content-length'),
			],
		];
	}

	if ($body === '') {
		return [
			'id'    => 0,
			'error' => [
				'type'           => 'empty_body',
				'status'         => $status,
				'content_type'   => wp_remote_retrieve_header($res, 'content-type'),
				'content_length' => wp_remote_retrieve_header($res, 'content-length'),
			],
		];
	}

	$tmp = wp_tempnam($preferred_filename);
	if (!$tmp) {
		return ['id' => 0, 'error' => ['type' => 'tempfile', 'message' => 'wp_tempnam failed']];
	}

	$bytes = @file_put_contents($tmp, $body);
	if ($bytes === false) {
		@unlink($tmp);
		return ['id' => 0, 'error' => ['type' => 'tempfile', 'message' => 'file_put_contents failed']];
	}

	if ($overwrite_basename !== '') {
		$existing_attachment_id = fi_media_find_attachment_by_basename($overwrite_basename);
		if ($existing_attachment_id > 0) {
			$result = fi_media_overwrite_existing_attachment($existing_attachment_id, $tmp, $only_thumbnail_sizes, $post_title, $post_name, $meta);
			if (!empty($result['id'])) {
				@unlink($tmp);
				return $result;
			}
		}
	}

	$file_array = [
		'name'     => $preferred_filename,
		'tmp_name' => $tmp,
	];

	add_filter('intermediate_image_sizes_advanced', $only_thumbnail_sizes, 999);
	try {
		$title_for_insert = $post_title !== '' ? sanitize_text_field($post_title) : sanitize_text_field(pathinfo($preferred_filename, PATHINFO_FILENAME));
		$att_id = media_handle_sideload($file_array, $attach_post_id, $desc, [
			'post_title' => $title_for_insert,
		]);
	} finally {
		remove_filter('intermediate_image_sizes_advanced', $only_thumbnail_sizes, 999);
	}

	if (is_wp_error($att_id)) {
		@unlink($tmp);
		return [
			'id'    => 0,
			'error' => [
				'type'    => 'wp_error',
				'code'    => $att_id->get_error_code(),
				'message' => $att_id->get_error_message(),
				'data'    => $att_id->get_error_data(),
			],
		];
	}

	$att_id = (int) $att_id;
	fi_media_update_attachment_post_fields($att_id, $post_title, $post_name);
	fi_media_update_attachment_meta_values($att_id, $meta);

	if ($desc !== '') {
		update_post_meta($att_id, '_wp_attachment_image_alt', sanitize_text_field($desc));
	}

	return ['id' => $att_id, 'error' => null];
}

/**
 * Download and store image from URL, returning only attachment ID or null.
 *
 * This is the canonical replacement for the old public/admin image upload helpers.
 *
 * @param string $image_url URL of image to download.
 * @param string $alt_text Alt/title text for the image.
 * @param array $opts Optional sideload options.
 * @return int|null WordPress attachment ID or null on failure.
 */
function fi_media_download_and_store_image(string $image_url, string $alt_text = '', array $opts = []): ?int {
	$image_url = esc_url_raw($image_url);
	if ($image_url === '') {
		return null;
	}

	$existing_id = fi_media_get_attachment_by_original_url($image_url);
	if ($existing_id) {
		return $existing_id;
	}

	$filename = $opts['preferred_filename'] ?? fi_media_build_remote_image_filename($image_url, $alt_text);
	$opts = array_merge([
		'desc'       => $alt_text,
		'post_title' => $alt_text,
		'meta'       => [
			'_fi_original_url' => $image_url,
		],
	], $opts);

	$result = fi_media_sideload_image_from_url($image_url, (string) $filename, $opts);

	if (!empty($result['id'])) {
		fi_media_log('Image successfully downloaded and stored | URL: ' . $image_url . ' | Attachment ID: ' . $result['id'], __FILE__, __LINE__, 'info');
		return (int) $result['id'];
	}

	if (!empty($result['error'])) {
		fi_media_log('Failed to download/store image | URL: ' . $image_url . ' | Error: ' . wp_json_encode($result['error']), __FILE__, __LINE__, 'warning');
	}

	return null;
}

/**
 * Import/register a local image file, optionally copying into uploads first.
 *
 * @param string $source_abs_path Absolute path to existing file.
 * @param string $preferred_filename Basename to use.
 * @param array $opts Options.
 * @return array { id:int, error:mixed|null }
 */
function fi_media_import_local_image(string $source_abs_path, string $preferred_filename, array $opts = []): array {
	$source_abs_path = (string) $source_abs_path;
	if ($source_abs_path === '' || !file_exists($source_abs_path) || !is_file($source_abs_path)) {
		return ['id' => 0, 'error' => ['type' => 'missing_file', 'path' => $source_abs_path]];
	}

	fi_media_require_wp_files();

	$uploads = wp_upload_dir();
	$basedir = (string) ($uploads['basedir'] ?? '');
	if ($basedir === '') {
		return ['id' => 0, 'error' => ['type' => 'uploads', 'message' => 'wp_upload_dir basedir empty']];
	}

	$copy_into_uploads = array_key_exists('copy_into_uploads', $opts) ? (bool) $opts['copy_into_uploads'] : true;
	$generate_thumbnail = array_key_exists('generate_thumbnail', $opts) ? (bool) $opts['generate_thumbnail'] : true;
	$public_url_base = (string) ($opts['public_url_base'] ?? '');

	$preferred_filename = sanitize_file_name((string) $preferred_filename);
	if ($preferred_filename === '') {
		$preferred_filename = sanitize_file_name(basename($source_abs_path));
	}
	if ($preferred_filename === '') {
		$preferred_filename = 'image.jpg';
	}

	$overwrite_basename = sanitize_file_name((string) ($opts['overwrite_basename'] ?? ''));
	$desc = (string) ($opts['desc'] ?? '');
	$post_title = (string) ($opts['post_title'] ?? '');
	$post_name = (string) ($opts['post_name'] ?? '');
	$meta = $opts['meta'] ?? [];
	if (!is_array($meta)) {
		$meta = [];
	}

	$only_thumbnail_sizes = fi_media_thumbnail_only_filter($generate_thumbnail);

	if ($overwrite_basename !== '') {
		$existing_attachment_id = fi_media_find_attachment_by_basename($overwrite_basename);
		if ($existing_attachment_id > 0) {
			$result = fi_media_overwrite_existing_attachment($existing_attachment_id, $source_abs_path, $only_thumbnail_sizes, $post_title, $post_name, $meta);
			if (!empty($result['id'])) {
				return $result;
			}
		}
	}

	if (!$copy_into_uploads) {
		$source_abs_norm = str_replace(['\\'], '/', $source_abs_path);
		$basedir_norm = str_replace(['\\'], '/', $basedir);
		$target_abs = $source_abs_path;

		$in_uploads = strpos($source_abs_norm, rtrim($basedir_norm, '/') . '/') === 0;
		$rel = $in_uploads ? ltrim(substr($source_abs_norm, strlen(rtrim($basedir_norm, '/'))), '/') : '';

		$filetype = wp_check_filetype($preferred_filename, null);
		$public_url = '';

		if ($in_uploads) {
			$baseurl = (string) ($uploads['baseurl'] ?? '');
			$public_url = ($baseurl !== '' && $rel !== '') ? rtrim($baseurl, '/') . '/' . $rel : '';
		} elseif ($public_url_base !== '') {
			$public_url = rtrim($public_url_base, '/') . '/' . basename($source_abs_path);
		}

		$attachment = [
			'post_mime_type' => $filetype['type'] ?: 'image/jpeg',
			'post_title'     => sanitize_text_field($post_title !== '' ? $post_title : $preferred_filename),
			'post_name'      => $post_name !== '' ? sanitize_title($post_name) : '',
			'post_content'   => $desc,
			'post_status'    => 'inherit',
			'guid'           => $public_url !== '' ? $public_url : '',
		];

		$att_id = wp_insert_attachment($attachment, $target_abs, 0);
		if (is_wp_error($att_id) || !$att_id) {
			return ['id' => 0, 'error' => ['type' => 'wp_error', 'message' => is_wp_error($att_id) ? $att_id->get_error_message() : 'wp_insert_attachment failed']];
		}

		$att_id = (int) $att_id;

		if (!$in_uploads) {
			if ($public_url !== '') {
				update_post_meta($att_id, 'fi_external_url', $public_url);
			}
			update_post_meta($att_id, 'fi_external_path', $target_abs);
		}

		add_filter('intermediate_image_sizes_advanced', $only_thumbnail_sizes, 999);
		$meta_data = wp_generate_attachment_metadata($att_id, $target_abs);
		remove_filter('intermediate_image_sizes_advanced', $only_thumbnail_sizes, 999);

		if (is_array($meta_data)) {
			wp_update_attachment_metadata($att_id, $meta_data);
		}

		fi_media_update_attachment_meta_values($att_id, $meta);

		return ['id' => $att_id, 'error' => null];
	}

	$subdir = trim((string) ($opts['uploads_subdir'] ?? 'fi-import'), '/\\');
	$target_dir = rtrim($basedir, '/\\') . DIRECTORY_SEPARATOR . $subdir;

	if (!is_dir($target_dir)) {
		wp_mkdir_p($target_dir);
	}

	$target_abs = $target_dir . DIRECTORY_SEPARATOR . $preferred_filename;
	if (!@copy($source_abs_path, $target_abs)) {
		return ['id' => 0, 'error' => ['type' => 'copy_failed', 'src' => $source_abs_path, 'dst' => $target_abs]];
	}

	$filetype = wp_check_filetype($preferred_filename, null);
	$attachment = [
		'post_mime_type' => $filetype['type'] ?: 'image/jpeg',
		'post_title'     => sanitize_text_field($post_title !== '' ? $post_title : $preferred_filename),
		'post_name'      => $post_name !== '' ? sanitize_title($post_name) : '',
		'post_content'   => $desc,
		'post_status'    => 'inherit',
	];

	$att_id = wp_insert_attachment($attachment, $target_abs, 0);
	if (is_wp_error($att_id) || !$att_id) {
		return ['id' => 0, 'error' => ['type' => 'wp_error', 'message' => is_wp_error($att_id) ? $att_id->get_error_message() : 'wp_insert_attachment failed']];
	}

	$att_id = (int) $att_id;

	add_filter('intermediate_image_sizes_advanced', $only_thumbnail_sizes, 999);
	$meta_data = wp_generate_attachment_metadata($att_id, $target_abs);
	remove_filter('intermediate_image_sizes_advanced', $only_thumbnail_sizes, 999);

	if (is_array($meta_data)) {
		wp_update_attachment_metadata($att_id, $meta_data);
	}

	fi_media_update_attachment_meta_values($att_id, $meta);

	return ['id' => $att_id, 'error' => null];
}

/**
 * Get standard FI square image dimensions for named sizes.
 *
 * @param string $size Size key.
 * @return array|null Width/height or null.
 */
function fi_media_image_size_data(string $size): ?array {
	$sizes = [
		'thumbnail' => ['width' => 150, 'height' => 150],
		'medium'    => ['width' => 300, 'height' => 300],
		'large'     => ['width' => 600, 'height' => 600],
		'full'      => ['width' => 1200, 'height' => 1200],
	];

	return $sizes[$size] ?? null;
}

/**
 * Resize an attachment through JBS Image Resizer when available.
 *
 * Falls back to native WP attachment image URL.
 *
 * @param int $attachment_id Attachment ID.
 * @param string $size Size key.
 * @return string|null Image URL.
 */
function fi_media_resize_image(int $attachment_id, string $size): ?string {
	$attachment_id = absint($attachment_id);
	if ($attachment_id <= 0) {
		return null;
	}

	if (!function_exists('jbs_resize_image')) {
		$url = wp_get_attachment_image_url($attachment_id, $size);
		return $url ?: null;
	}

	$original_url = wp_get_attachment_url($attachment_id);
	if (!$original_url) {
		return null;
	}

	$size_data = fi_media_image_size_data($size);
	if (!$size_data) {
		return $original_url;
	}

	$resized_url = jbs_resize_image($original_url, $size_data['width'], $size_data['height'], true);
	return $resized_url ?: $original_url;
}

/**
 * Generate configured JBS-resized image sizes for an attachment.
 *
 * @param int $attachment_id Attachment ID.
 * @return bool True if at least one size generated.
 */
function fi_media_generate_image_sizes(int $attachment_id): bool {
	if (!function_exists('jbs_resize_image')) {
		return false;
	}

	$attachment_id = absint($attachment_id);
	$original_url = $attachment_id > 0 ? wp_get_attachment_url($attachment_id) : '';
	if (!$original_url) {
		return false;
	}

	$generated = 0;
	foreach (['thumbnail', 'medium', 'large'] as $size) {
		$size_data = fi_media_image_size_data($size);
		if (!$size_data) {
			continue;
		}

		$resized_url = jbs_resize_image($original_url, $size_data['width'], $size_data['height'], true);
		if ($resized_url) {
			$generated++;
		}
	}

	return $generated > 0;
}

/**
 * Get admin/default placeholder image URL.
 *
 * @param string $size Size key.
 * @return string Default image URL or empty string.
 */
function fi_media_get_default_image_url(string $size = 'medium'): string {
	$base_url = defined('FI_URL') ? trailingslashit(FI_URL) : plugin_dir_url(__FILE__);

	$default_images = [
		'thumbnail' => $base_url . 'assets/default-thumbnail.jpg',
		'medium'    => $base_url . 'assets/default-medium.jpg',
		'large'     => $base_url . 'assets/default-large.jpg',
		'full'      => $base_url . 'assets/default-full.jpg',
	];

	return $default_images[$size] ?? $default_images['medium'];
}

/**
 * Get session-specific legislator image URL.
 *
 * @param int $legislator_id Legislator ID.
 * @param int $session_id Session ID.
 * @param string $size Image size.
 * @return string|null Image URL.
 */
function fi_media_get_legislator_session_image_url(int $legislator_id, int $session_id, string $size = 'medium'): ?string {
	global $wpdb;

	$legislator_id = absint($legislator_id);
	$session_id = absint($session_id);
	if ($legislator_id <= 0 || $session_id <= 0) {
		return null;
	}

	$image_id = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT image_id
		FROM {$wpdb->prefix}fi_legislator_sessions
		WHERE legislator_id = %d AND session_id = %d
		LIMIT 1",
		$legislator_id,
		$session_id
	));

	if ($image_id <= 0) {
		return null;
	}

	$image_url = wp_get_attachment_image_url($image_id, $size);
	return $image_url ?: null;
}

/**
 * Get career/default legislator image URL.
 *
 * @param int $legislator_id Legislator ID.
 * @param string $size Image size.
 * @return string|null Image URL.
 */
function fi_media_get_legislator_career_image_url(int $legislator_id, string $size = 'medium'): ?string {
	global $wpdb;

	$legislator_id = absint($legislator_id);
	if ($legislator_id <= 0) {
		return null;
	}

	$image_id = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT image_id
		FROM {$wpdb->prefix}fi_legislators
		WHERE id = %d
		LIMIT 1",
		$legislator_id
	));

	if ($image_id <= 0) {
		return null;
	}

	$image_url = wp_get_attachment_image_url($image_id, $size);
	return $image_url ?: null;
}

/**
 * Get legislator image URL with session-to-career-to-default fallback.
 *
 * @param int $legislator_id Legislator ID.
 * @param int|null $session_id Optional session ID.
 * @param string $size Image size.
 * @return string|null Image URL.
 */
function fi_media_get_legislator_image_url(int $legislator_id, ?int $session_id = null, string $size = 'medium'): ?string {
	if ($session_id) {
		$session_image = fi_media_get_legislator_session_image_url($legislator_id, $session_id, $size);
		if ($session_image) {
			return $session_image;
		}
	}

	$career_image = fi_media_get_legislator_career_image_url($legislator_id, $size);
	if ($career_image) {
		return $career_image;
	}

	$default_image = fi_media_get_default_image_url($size);
	return $default_image !== '' ? $default_image : null;
}

/**
 * Get legislator image HTML with fallback.
 *
 * @param int $legislator_id Legislator ID.
 * @param int|null $session_id Optional session ID.
 * @param string $size Image size.
 * @param array $attributes HTML attributes.
 * @return string Image HTML or empty string.
 */
function fi_media_get_legislator_image_html(int $legislator_id, ?int $session_id = null, string $size = 'medium', array $attributes = []): string {
	$image_url = fi_media_get_legislator_image_url($legislator_id, $session_id, $size);
	if (!$image_url) {
		return '';
	}

	$default_attributes = [
		'src'   => $image_url,
		'alt'   => 'Legislator Image',
		'class' => 'fi-legislator-image',
	];

	$attributes = array_merge($default_attributes, $attributes);

	$html = '<img';
	foreach ($attributes as $key => $value) {
		if ($value === null || $value === false) {
			continue;
		}
		$html .= ' ' . esc_attr((string) $key) . '="' . esc_attr((string) $value) . '"';
	}
	$html .= '>';

	return $html;
}

/**
 * Get attachment metadata summary.
 *
 * @param int $attachment_id Attachment ID.
 * @return array Metadata summary.
 */
function fi_media_get_image_metadata(int $attachment_id): array {
	$attachment_id = absint($attachment_id);
	if ($attachment_id <= 0) {
		return [];
	}

	$metadata = wp_get_attachment_metadata($attachment_id);
	if (!is_array($metadata)) {
		$metadata = [];
	}

	$file_url = wp_get_attachment_url($attachment_id);
	$file_path = get_attached_file($attachment_id);
	$file_size = (is_string($file_path) && file_exists($file_path)) ? filesize($file_path) : 0;

	return [
		'id'          => $attachment_id,
		'url'         => $file_url ?: '',
		'width'       => (int) ($metadata['width'] ?? 0),
		'height'      => (int) ($metadata['height'] ?? 0),
		'file_size'   => (int) $file_size,
		'mime_type'   => (string) get_post_mime_type($attachment_id),
		'alt_text'    => (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
		'caption'     => (string) wp_get_attachment_caption($attachment_id),
		'description' => (string) get_post_field('post_content', $attachment_id),
	];
}

/**
 * Get IDs for all FI-referenced image attachments.
 *
 * @return array Attachment IDs.
 */
function fi_media_get_referenced_image_ids(): array {
	global $wpdb;

	$ids = $wpdb->get_col(
		"SELECT DISTINCT image_id FROM {$wpdb->prefix}fi_legislators WHERE image_id IS NOT NULL AND image_id > 0
		UNION
		SELECT DISTINCT image_id FROM {$wpdb->prefix}fi_legislator_sessions WHERE image_id IS NOT NULL AND image_id > 0"
	);

	return array_values(array_unique(array_filter(array_map('absint', (array) $ids))));
}

/**
 * Get FI image statistics.
 *
 * Important: orphaned_images here means WP image attachments not referenced by FI legislator/session image fields.
 * That does NOT prove they are globally orphaned from WordPress content.
 *
 * @return array Stats.
 */
function fi_media_get_image_stats(): array {
	global $wpdb;

	$stats = [];

	$stats['legislator_images'] = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->prefix}fi_legislators WHERE image_id IS NOT NULL AND image_id > 0"
	);

	$stats['session_images'] = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->prefix}fi_legislator_sessions WHERE image_id IS NOT NULL AND image_id > 0"
	);

	$stats['total_image_attachments'] = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
	);

	$referenced_ids = fi_media_get_referenced_image_ids();
	if (empty($referenced_ids)) {
		$stats['unreferenced_by_fi_images'] = $stats['total_image_attachments'];
		return $stats;
	}

	$placeholders = implode(',', array_fill(0, count($referenced_ids), '%d'));
	$stats['unreferenced_by_fi_images'] = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*)
		FROM {$wpdb->posts}
		WHERE post_type = 'attachment'
		AND post_mime_type LIKE 'image/%'
		AND ID NOT IN ($placeholders)",
		...$referenced_ids
	));

	return $stats;
}

/**
 * Return FI-unreferenced image attachment IDs.
 *
 * This is intentionally non-destructive. Review results before deleting anything.
 *
 * @param int $limit Max IDs to return.
 * @return array Attachment IDs.
 */
function fi_media_get_unreferenced_image_ids(int $limit = 500): array {
	global $wpdb;

	$limit = max(1, min(5000, absint($limit)));
	$referenced_ids = fi_media_get_referenced_image_ids();

	if (empty($referenced_ids)) {
		$ids = $wpdb->get_col($wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'attachment'
			AND post_mime_type LIKE 'image/%'
			ORDER BY ID ASC
			LIMIT %d",
			$limit
		));
		return array_values(array_filter(array_map('absint', (array) $ids)));
	}

	$placeholders = implode(',', array_fill(0, count($referenced_ids), '%d'));
	$params = array_merge($referenced_ids, [$limit]);

	$ids = $wpdb->get_col($wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts}
		WHERE post_type = 'attachment'
		AND post_mime_type LIKE 'image/%'
		AND ID NOT IN ($placeholders)
		ORDER BY ID ASC
		LIMIT %d",
		...$params
	));

	return array_values(array_filter(array_map('absint', (array) $ids)));
}

/**
 * Delete FI-unreferenced image attachments.
 *
 * This is intentionally disabled unless $confirm is true because FI-unreferenced does not mean globally unused.
 *
 * @param bool $confirm Must be true to delete.
 * @param int $limit Max images to delete.
 * @return int Number deleted.
 */
function fi_media_cleanup_unreferenced_images(bool $confirm = false, int $limit = 100): int {
	if (!$confirm) {
		return 0;
	}

	$ids = fi_media_get_unreferenced_image_ids($limit);
	$cleaned = 0;

	foreach ($ids as $attachment_id) {
		if (wp_delete_attachment($attachment_id, true)) {
			$cleaned++;
		}
	}

	return $cleaned;
}

/**
 * Use an external/local URL when an attachment was registered from outside uploads.
 */
add_filter('wp_get_attachment_url', function($url, $post_id) {
	$ext = get_post_meta($post_id, 'fi_external_url', true);
	return (is_string($ext) && $ext !== '') ? $ext : $url;
}, 10, 2);

/**
 * Use an external/local path when an attachment was registered from outside uploads.
 */
add_filter('get_attached_file', function($file, $post_id) {
	$ext = get_post_meta($post_id, 'fi_external_path', true);
	return (is_string($ext) && $ext !== '') ? $ext : $file;
}, 10, 2);

/* -------------------------------------------------------------------------
 * Compatibility wrappers for old function names.
 * ---------------------------------------------------------------------- */

/**
 * Legacy public alias for remote image download/store.
 */
function fi_image_download_and_store(string $image_url, string $alt_text = ''): ?int {
	return fi_media_download_and_store_image($image_url, $alt_text);
}

/**
 * Legacy public alias for remote image download/store.
 */
function fi_download_and_store_image(string $image_url, string $alt_text = ''): ?int {
	return fi_media_download_and_store_image($image_url, $alt_text);
}

/**
 * Legacy/admin-style alias for remote image upload.
 */
function fi_media_upload_image(string $image_url, string $title = '', string $alt = ''): ?int {
	return fi_media_download_and_store_image($image_url, $alt ?: $title, [
		'post_title' => $title,
		'desc'       => $alt,
	]);
}

/**
 * Legacy public alias for attachment lookup by original URL.
 */
function fi_image_get_attachment_by_url(string $url): ?int {
	return fi_media_get_attachment_by_original_url($url);
}

/**
 * Legacy public alias for image extension parsing.
 */
function fi_image_get_file_extension_from_url(string $url): ?string {
	return fi_media_file_extension_from_url($url);
}

/**
 * Legacy public alias for remote URL exists check.
 */
function fi_url_image_exists($url): bool {
	return fi_media_url_exists((string) $url);
}

/**
 * Legacy alias for older unprefixed media sideload helper.
 */
function media_sideload_image_from_url(string $url, string $preferred_filename, array $opts = []): array {
	return fi_media_sideload_image_from_url($url, $preferred_filename, $opts);
}

/**
 * Legacy alias for older unprefixed local image import helper.
 */
function media_import_local_image(string $source_abs_path, string $preferred_filename, array $opts = []): array {
	return fi_media_import_local_image($source_abs_path, $preferred_filename, $opts);
}

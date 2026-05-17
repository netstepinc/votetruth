<?php
namespace FI\Core{
	if (!defined('ABSPATH')) exit;

	/**
	 * Remote media helpers.
	 *
	 * Summary:
	 * - Centralizes remote image fetch + sideload logic so admin tools and migrations share the same codepath.
	 * - Uses wp_safe_remote_get for better diagnostics vs download_url().
	 */

	/**
	 * Sideload (or overwrite-by-basename) an image from a remote URL into the WP Media Library.
	 *
	 * @param string $url Remote URL (http/https).
	 * @param string $preferred_filename Basename to use when creating a new attachment.
	 * @param array $opts {
	 *   @type int    $timeout            Request timeout seconds. Default 30.
	 *   @type int    $redirection        Redirect limit. Default 5.
	 *   @type string $desc               Attachment description. Default ''.
	 *   @type int    $attach_post_id     Parent post id. Default 0.
	 *   @type string $overwrite_basename If provided, attempts to find an existing attachment by basename and overwrite it.
	 *   @type array  $meta               Postmeta to set on the attachment (key => scalar).
	 * }
	 * @return array { id:int, error:mixed|null }
	 */
	function media_sideload_image_from_url(string $url, string $preferred_filename, array $opts = []): array {
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
		$overwrite_basename = (string) ($opts['overwrite_basename'] ?? '');
		$meta = $opts['meta'] ?? [];
		if (!is_array($meta)) $meta = [];

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Generate only the WP "thumbnail" size (150x150) for Media Library previews.
		// Summary: FI stores originals as-is and relies on one small preview; we do not generate other sizes.
		$only_thumbnail_sizes = static function ($sizes) {
			if (!is_array($sizes)) {
				return [];
			}
			return array_intersect_key($sizes, ['thumbnail' => true]);
		};

		// Fetch remote bytes (robust + diagnosable).
		$ua = 'FreedomIndex/RemoteImage; ' . home_url('/');
		$res = wp_safe_remote_get($url, [
			'timeout' => max(1, $timeout),
			'redirection' => max(0, $redirection),
			'user-agent' => $ua,
		]);
		if (is_wp_error($res)) {
			return [
				'id' => 0,
				'error' => [
					'type' => 'wp_error',
					'code' => $res->get_error_code(),
					'message' => $res->get_error_message(),
					'data' => $res->get_error_data(),
				],
			];
		}
		$status = (int) wp_remote_retrieve_response_code($res);
		$body = (string) wp_remote_retrieve_body($res);
		if ($status < 200 || $status >= 300) {
			return [
				'id' => 0,
				'error' => [
					'type' => 'http_error',
					'status' => $status,
					'content_type' => wp_remote_retrieve_header($res, 'content-type'),
					'content_length' => wp_remote_retrieve_header($res, 'content-length'),
				],
			];
		}
		if ($body === '') {
			return [
				'id' => 0,
				'error' => [
					'type' => 'empty_body',
					'status' => $status,
					'content_type' => wp_remote_retrieve_header($res, 'content-type'),
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

		// Optional overwrite-by-basename (keeps one evolving attachment/file).
		if ($overwrite_basename !== '') {
			global $wpdb;
			$like = '%' . $wpdb->esc_like('/' . $overwrite_basename) . '%';
			$existing_attachment_id = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT post_id
				 FROM {$wpdb->postmeta}
				 WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s
				 ORDER BY post_id DESC
				 LIMIT 1",
				$like
			));
			if ($existing_attachment_id > 0) {
				$existing_file = get_attached_file($existing_attachment_id);
				if (is_string($existing_file) && $existing_file !== '' && file_exists($existing_file)) {
					@copy($tmp, $existing_file);
					@unlink($tmp);
					add_filter('intermediate_image_sizes_advanced', $only_thumbnail_sizes, 999);
					$meta_data = wp_generate_attachment_metadata($existing_attachment_id, $existing_file);
					remove_filter('intermediate_image_sizes_advanced', $only_thumbnail_sizes, 999);
					if (is_array($meta_data)) {
						wp_update_attachment_metadata($existing_attachment_id, $meta_data);
					}
					// Summary: keep attachment title/slug aligned with the latest import rules.
					$upd = ['ID' => $existing_attachment_id];
					if ($post_title !== '') $upd['post_title'] = sanitize_text_field($post_title);
					if ($post_name !== '') $upd['post_name'] = sanitize_title($post_name);
					if (count($upd) > 1) {
						wp_update_post($upd);
					}
					foreach ($meta as $k => $v) {
						if (is_scalar($v) || $v === null) {
							update_post_meta($existing_attachment_id, (string) $k, $v);
						}
					}
					return ['id' => $existing_attachment_id, 'error' => null];
				}
			}
			// Fall through to "create new" if overwrite target wasn't usable.
		}

		$file_array = [
			'name' => $preferred_filename,
			'tmp_name' => $tmp,
		];
		add_filter('intermediate_image_sizes_advanced', $only_thumbnail_sizes, 999);
		try {
			$title_for_insert = $post_title !== '' ? sanitize_text_field($post_title) : sanitize_text_field($preferred_filename);
			$att_id = media_handle_sideload($file_array, $attach_post_id, $desc, [
				'post_title' => $title_for_insert,
			]);
		} finally {
			remove_filter('intermediate_image_sizes_advanced', $only_thumbnail_sizes, 999);
		}
		if (is_wp_error($att_id)) {
			@unlink($tmp);
			return [
				'id' => 0,
				'error' => [
					'type' => 'wp_error',
					'code' => $att_id->get_error_code(),
					'message' => $att_id->get_error_message(),
					'data' => $att_id->get_error_data(),
				],
			];
		}

		// Summary: set custom slug/title after insert (media_handle_sideload doesn't support post_name directly).
		if ($post_name !== '' || $post_title !== '') {
			$upd = ['ID' => (int) $att_id];
			if ($post_title !== '') $upd['post_title'] = sanitize_text_field($post_title);
			if ($post_name !== '') $upd['post_name'] = sanitize_title($post_name);
			if (count($upd) > 1) {
				wp_update_post($upd);
			}
		}

		foreach ($meta as $k => $v) {
			if (is_scalar($v) || $v === null) {
				update_post_meta((int) $att_id, (string) $k, $v);
			}
		}

		return ['id' => (int) $att_id, 'error' => null];
	}

	/**
	 * Import/register a local image file (optionally copying into uploads first).
	 *
	 * Summary:
	 * - Useful when you already have the files on disk (e.g., state site images) and want a reliable, fast import.
	 *
	 * @param string $source_abs_path Absolute path to existing file.
	 * @param string $preferred_filename Basename to use (e.g. KS_1234_photo.jpg).
	 * @param array $opts {
	 *   @type string $uploads_subdir       Subdir under uploads to place the file (default: 'fi-import').
	 *   @type bool   $copy_into_uploads    Copy into uploads (default true). If false, tries to register in place.
	 *   @type bool   $generate_thumbnail   Generate only the WP "thumbnail" size (150x150). Default true.
	 *   @type string $overwrite_basename   If set, overwrite existing attachment/file by basename.
	 *   @type string $desc                Attachment description.
	 *   @type array  $meta                Attachment postmeta key=>scalar.
	 * }
	 * @return array { id:int, error:mixed|null }
	 */
	function media_import_local_image(string $source_abs_path, string $preferred_filename, array $opts = []): array {
		$source_abs_path = (string) $source_abs_path;
		if ($source_abs_path === '' || !file_exists($source_abs_path) || !is_file($source_abs_path)) {
			return ['id' => 0, 'error' => ['type' => 'missing_file', 'path' => $source_abs_path]];
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

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
		if (!is_array($meta)) $meta = [];

		// Generate only the WP "thumbnail" size (150x150) for Media Library previews.
		// Summary: FI stores originals as-is and relies on one small preview; we do not generate other sizes.
		$only_thumbnail_sizes = static function ($sizes) use ($generate_thumbnail) {
			if (!$generate_thumbnail) {
				return [];
			}
			if (!is_array($sizes)) {
				return [];
			}
			return array_intersect_key($sizes, ['thumbnail' => true]);
		};

		// Overwrite-by-basename if requested
		if ($overwrite_basename !== '') {
			global $wpdb;
			$like = '%' . $wpdb->esc_like('/' . $overwrite_basename) . '%';
			$existing_attachment_id = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT post_id
				 FROM {$wpdb->postmeta}
				 WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s
				 ORDER BY post_id DESC
				 LIMIT 1",
				$like
			));
			if ($existing_attachment_id > 0) {
				$existing_file = get_attached_file($existing_attachment_id);
				if (is_string($existing_file) && $existing_file !== '' && file_exists($existing_file)) {
					@copy($source_abs_path, $existing_file);
					add_filter('intermediate_image_sizes_advanced', $only_thumbnail_sizes, 999);
					$meta_data = wp_generate_attachment_metadata($existing_attachment_id, $existing_file);
					remove_filter('intermediate_image_sizes_advanced', $only_thumbnail_sizes, 999);
					if (is_array($meta_data)) {
						wp_update_attachment_metadata($existing_attachment_id, $meta_data);
					}
					$upd = ['ID' => $existing_attachment_id];
					if ($post_title !== '') $upd['post_title'] = sanitize_text_field($post_title);
					if ($post_name !== '') $upd['post_name'] = sanitize_title($post_name);
					if (count($upd) > 1) {
						wp_update_post($upd);
					}
					foreach ($meta as $k => $v) {
						if (is_scalar($v) || $v === null) {
							update_post_meta($existing_attachment_id, (string) $k, $v);
						}
					}
					return ['id' => $existing_attachment_id, 'error' => null];
				}
			}
		}

		// Register-in-place (no copying) when requested.
		if (!$copy_into_uploads) {
			$source_abs_norm = str_replace(['\\'], '/', $source_abs_path);
			$basedir_norm = str_replace(['\\'], '/', $basedir);
			$target_abs = $source_abs_path;

			// If file is NOT within uploads basedir, we can still register it, but must store an explicit public URL
			// and rely on our attachment URL/path filters.
			$in_uploads = (strpos($source_abs_norm, rtrim($basedir_norm, '/') . '/') === 0);
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
				'post_title' => sanitize_text_field($post_title !== '' ? $post_title : $preferred_filename),
				'post_name' => $post_name !== '' ? sanitize_title($post_name) : '',
				'post_content' => $desc,
				'post_status' => 'inherit',
				'guid' => $public_url !== '' ? $public_url : '',
			];

			$att_id = wp_insert_attachment($attachment, $target_abs, 0);
			if (is_wp_error($att_id) || !$att_id) {
				return ['id' => 0, 'error' => ['type' => 'wp_error', 'message' => is_wp_error($att_id) ? $att_id->get_error_message() : 'wp_insert_attachment failed']];
			}

			// Ensure URL/path resolve even if this file is outside uploads.
			if (!$in_uploads) {
				if ($public_url !== '') update_post_meta((int) $att_id, 'fi_external_url', $public_url);
				update_post_meta((int) $att_id, 'fi_external_path', $target_abs);
			}

			// Generate only "thumbnail" so Media Library grid has previews (no other sizes).
			add_filter('intermediate_image_sizes_advanced', $only_thumbnail_sizes, 999);
			$meta_data = wp_generate_attachment_metadata((int) $att_id, $target_abs);
			remove_filter('intermediate_image_sizes_advanced', $only_thumbnail_sizes, 999);
			if (is_array($meta_data)) {
				wp_update_attachment_metadata((int) $att_id, $meta_data);
			}

			foreach ($meta as $k => $v) {
				if (is_scalar($v) || $v === null) {
					update_post_meta((int) $att_id, (string) $k, $v);
				}
			}

			return ['id' => (int) $att_id, 'error' => null];
		}

		// Copy into uploads (legacy behavior)
		$subdir = (string) ($opts['uploads_subdir'] ?? 'fi-import');
		$subdir = trim($subdir, '/\\');
		$target_dir = rtrim($basedir, '/\\') . DIRECTORY_SEPARATOR . $subdir;
		if (!is_dir($target_dir)) {
			wp_mkdir_p($target_dir);
		}
		$target_abs = $target_dir . DIRECTORY_SEPARATOR . $preferred_filename;
		if (!@copy($source_abs_path, $target_abs)) {
			return ['id' => 0, 'error' => ['type' => 'copy_failed', 'src' => $source_abs_path, 'dst' => $target_abs]];
		}

		// Register attachment
		$filetype = wp_check_filetype($preferred_filename, null);
		$attachment = [
			'post_mime_type' => $filetype['type'] ?: 'image/jpeg',
			'post_title' => sanitize_text_field($post_title !== '' ? $post_title : $preferred_filename),
			'post_name' => $post_name !== '' ? sanitize_title($post_name) : '',
			'post_content' => $desc,
			'post_status' => 'inherit',
		];

		$att_id = wp_insert_attachment($attachment, $target_abs, 0);
		if (is_wp_error($att_id) || !$att_id) {
			return ['id' => 0, 'error' => ['type' => 'wp_error', 'message' => is_wp_error($att_id) ? $att_id->get_error_message() : 'wp_insert_attachment failed']];
		}

		add_filter('intermediate_image_sizes_advanced', $only_thumbnail_sizes, 999);
		$meta_data = wp_generate_attachment_metadata((int) $att_id, $target_abs);
		remove_filter('intermediate_image_sizes_advanced', $only_thumbnail_sizes, 999);
		if (is_array($meta_data)) {
			wp_update_attachment_metadata((int) $att_id, $meta_data);
		}

		foreach ($meta as $k => $v) {
			if (is_scalar($v) || $v === null) {
				update_post_meta((int) $att_id, (string) $k, $v);
			}
		}

		return ['id' => (int) $att_id, 'error' => null];
	}
}

namespace{
	// If an attachment was registered from an external/local path outside uploads, honor that URL/path.
	add_filter('wp_get_attachment_url', function ($url, $post_id) {
		$ext = get_post_meta($post_id, 'fi_external_url', true);
		return (is_string($ext) && $ext !== '') ? $ext : $url;
	}, 10, 2);

	add_filter('get_attached_file', function ($file, $post_id) {
		$ext = get_post_meta($post_id, 'fi_external_path', true);
		return (is_string($ext) && $ext !== '') ? $ext : $file;
	}, 10, 2);
}

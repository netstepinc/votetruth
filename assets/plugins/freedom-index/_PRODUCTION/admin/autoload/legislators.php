<?php if (!defined('ABSPATH')) { exit; }

/**
 * Render legislators page
 */
function fi_admin_legislators_render(): void {
	include __DIR__ . '/../views/legislators.php';
}

/**
 * Render legislator add/edit form
 */
function fi_admin_legislators_render_form(array $scope, string $action): void {
	include __DIR__ . '/../views/legislator-edit.php';
}

/**
 * Handle legislator form submissions when present
 */
function fi_admin_legislators_maybe_handle_save(array $scope): void {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}

	if (!isset($_POST['fi_legislator_nonce'])) {
		return;
	}

	fi_admin_legislators_handle_save($scope);
}

/**
 * Persist legislator data
 */
function fi_admin_legislators_handle_save(array $scope): void {
	if (!wp_verify_nonce($_POST['fi_legislator_nonce'], 'fi_save_legislator')) {
		wp_die('Security check failed.');
	}

	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die('Insufficient permissions.');
	}

	$legislator_id = isset($_POST['legislator_id']) ? absint($_POST['legislator_id']) : null;

	$data = [
		'display_name'   => sanitize_text_field($_POST['display_name'] ?? ''),
		'first_name'     => sanitize_text_field($_POST['first_name'] ?? ''),
		'middle_name'    => sanitize_text_field($_POST['middle_name'] ?? ''),
		'last_name'      => sanitize_text_field($_POST['last_name'] ?? ''),
	];

	// Handle external IDs - convert empty strings to null for proper database handling
	$external_ids = ['bioguide_id', 'lis_id', 'govtrack_id', 'votesmart_id', 'ballotpedia_id'];
	foreach ($external_ids as $field) {
		$value = sanitize_text_field($_POST[$field] ?? '');
		$data[$field] = ($value === '') ? null : $value;
	}

	if (isset($_POST['legiscan_id']) && $_POST['legiscan_id'] !== '') {
		$data['legiscan_id'] = absint($_POST['legiscan_id']);
	}

	if (isset($_POST['image_id']) && $_POST['image_id'] !== '') {
		$image_id = absint($_POST['image_id']);
		// Summary: allow explicit clearing (image_id=0 means "remove image").
		$data['image_id'] = ($image_id > 0) ? $image_id : null;
	}

	if (isset($_POST['image_url']) && $_POST['image_url'] !== '') {
		$image_url = sanitize_text_field($_POST['image_url']);
		$data['image_url'] = $image_url;
	}

	// Filter out empty strings (but null values are preserved for external IDs to allow clearing)
	$data = array_filter($data, static function ($value, $key) {
		// Preserve null values for external IDs (allows clearing fields)
		if ($value === null && in_array($key, ['bioguide_id', 'lis_id', 'govtrack_id', 'votesmart_id', 'ballotpedia_id', 'image_id'], true)) {
			return true;
		}
		return $value !== '';
	}, ARRAY_FILTER_USE_BOTH);

	if (!$legislator_id && (empty($data['first_name']) || empty($data['last_name']))) {
		wp_die('First and last name are required for new legislators.');
	}

	$meta_groups = fi_admin_legislators_get_meta_field_groups();
	$meta_input  = is_array($_POST['meta'] ?? null) ? $_POST['meta'] : [];
	$data['meta'] = fi_admin_legislators_build_meta_payload($legislator_id, $meta_groups, $meta_input);

	$saved_id = fi_legislator_save($data, $legislator_id);
	if (!$saved_id) {
		add_settings_error('fi_legislator', 'save_error', 'Unable to save legislator.', 'error');
		return;
	}

	// Invalidate file cache so frontend/AJAX reflects the update immediately.
	fi_cache_clear('legislators');

	$return_url = '';
	if (isset($_POST['return_url']) && is_string($_POST['return_url'])) {
		$candidate = esc_url_raw(wp_unslash($_POST['return_url']));
		$return_url = wp_validate_redirect($candidate, '');
	}

	// Use transient-based notice (WP standard) so redirect URL stays clean.
	add_settings_error('fi_legislator', 'legislator_saved', 'Legislator saved successfully.', 'updated');

	$redirect = fi_admin_edit_legislator_url($saved_id, ['return_url' => $return_url]);

	// Redirect after successful save (called during admin_init, so no output yet)
	wp_safe_redirect($redirect);
	exit;
}

/**
 * Default legislator blueprint for new entries
 */
function fi_admin_legislators_get_defaults(): object {
	return (object) [
		'id'             => null,
		'first_name'     => '',
		'middle_name'    => '',
		'last_name'      => '',
		'display_name'   => '',
		'image_id'       => null,
		'image_url'      => '',
		'bioguide_id'    => '',
		'lis_id'         => '',
		'legiscan_id'    => '',
		'govtrack_id'    => '',
		'votesmart_id'   => '',
		'ballotpedia_id' => '',
		'meta'           => [],
		'sessions'       => [],
	];
}

/**
 * Editable meta field groups
 * Note: Addresses and websites are handled via custom repeater UI, not through this method
 */
function fi_admin_legislators_get_meta_field_groups(): array {
	return [
		'Contact Information' => [
			'phone'            => ['label' => 'Primary Phone', 'type' => 'text', 'cols' => 'col-md-4'],
			'fax'              => ['label' => 'Fax', 'type' => 'text', 'cols' => 'col-md-4'],
			'email'            => ['label' => 'Primary Email', 'type' => 'email', 'cols' => 'col-md-4'],
		],
		'Social Media' => [
			'facebook'      => ['label' => 'Facebook', 'type' => 'text', 'cols' => 'col-md-6'],
			'twitter'       => ['label' => 'Twitter / X', 'type' => 'text', 'cols' => 'col-md-6'],
			'instagram'     => ['label' => 'Instagram', 'type' => 'text', 'cols' => 'col-md-6'],
			'youtube'       => ['label' => 'YouTube', 'type' => 'text', 'cols' => 'col-md-6'],
			'rumble'        => ['label' => 'Rumble', 'type' => 'text', 'cols' => 'col-md-6'],
			'tiktok'        => ['label' => 'TikTok', 'type' => 'text', 'cols' => 'col-md-6'],
			'truth_social'  => ['label' => 'Truth Social', 'type' => 'text', 'cols' => 'col-md-6'],
			'gab'           => ['label' => 'Gab', 'type' => 'text', 'cols' => 'col-md-6'],
			'linkedin'      => ['label' => 'LinkedIn', 'type' => 'text', 'cols' => 'col-md-6'],
		],
		'Biographical Data' => [
			'gender'        => [
				'label' => 'Gender',
				'type'  => 'select',
				'options' => [
					''       => 'Select Gender',
					'male'   => 'Male',
					'female' => 'Female',
					'other'  => 'Other / Unknown',
				],
				'cols'  => 'col-md-3',
			],
			'birth_date'    => ['label' => 'Birth Date', 'type' => 'date', 'cols' => 'col-md-3'],
			'death_date'    => ['label' => 'Death Date', 'type' => 'date', 'cols' => 'col-md-3'],
			'hometown'     => ['label' => 'Home Town', 'type' => 'text', 'cols' => 'col-md-3'],
		],
		'External IDs (Meta)' => [
			'opensecrets_id' => ['label' => 'OpenSecrets ID', 'type' => 'text', 'cols' => 'col-md-6'],
			'url_openstates' => ['label' => 'OpenStates Profile URL', 'type' => 'url', 'cols' => 'col-md-6'],
			'url_wikipedia' => ['label' => 'Wikipedia Profile URL', 'type' => 'url', 'cols' => 'col-md-6'],
			'url_wikidata' => ['label' => 'Wikidata Profile URL', 'type' => 'url', 'cols' => 'col-md-6'],
		],
	];
}

/**
 * Meta entries not surfaced via the form
 */
function fi_admin_legislators_get_extra_meta(object $legislator, array $meta_groups): array {
	$meta = is_array($legislator->meta ?? null) ? $legislator->meta : [];
	if (empty($meta)) {
		return [];
	}

	$known = [];
	foreach ($meta_groups as $fields) {
		foreach ($fields as $key => $config) {
			$known[] = $key;
		}
	}

	// Always exclude normalized meta group keys (these are rendered elsewhere on the edit screen).
	// Summary: "Additional Meta" should only show unknown leftovers, not core structured meta.
	$known = array_merge($known, [
		'contact',
		'social',
		'address',
		'website',
		'personal',
		'legacy',
		'legiscan_data',
	]);

	$extra = array_diff_key($meta, array_flip($known));

	// Hide audit payloads (api_*), which are intentionally stored but not edited directly here.
	foreach (array_keys($extra) as $k) {
		if (is_string($k) && str_starts_with($k, 'api_')) {
			unset($extra[$k]);
		}
	}

	return $extra;
}

/**
 * Merge and sanitize meta payload
 * Converts flat form fields to structured meta format (offices, social, contact)
 */
function fi_admin_legislators_build_meta_payload(?int $legislator_id, array $meta_groups, array $submitted_meta): array {
	// Get existing meta and normalize it
	$existing_meta = [];
	if ($legislator_id) {
		$existing = fi_legislator_get($legislator_id);
		if ($existing && is_array($existing->meta ?? null)) {
			$existing_meta = fi_legislator_meta_normalize($existing->meta);
		}
	} else {
		$existing_meta = [];
	}

	// Start with existing structured meta (preserves address array, etc.)
	$meta = $existing_meta;

	// Initialize structured groups if they don't exist
	if (!isset($meta['contact'])) $meta['contact'] = [];
	if (!isset($meta['social'])) $meta['social'] = [];
	if (!isset($meta['address'])) $meta['address'] = [];
	if (!isset($meta['website'])) $meta['website'] = [];

	// Process addresses array from form submission
	if (isset($_POST['addresses']) && is_array($_POST['addresses'])) {
		$addresses = [];
		foreach ($_POST['addresses'] as $idx => $addr_data) {
			if (!is_array($addr_data)) continue;
			
			$address = [];
			if (!empty($addr_data['name'])) $address['name'] = sanitize_text_field($addr_data['name']);
			if (!empty($addr_data['type'])) $address['type'] = sanitize_text_field($addr_data['type']);
			if (!empty($addr_data['address'])) $address['address'] = sanitize_text_field($addr_data['address']);
			if (!empty($addr_data['city'])) $address['city'] = sanitize_text_field($addr_data['city']);
			if (!empty($addr_data['state'])) $address['state'] = sanitize_text_field($addr_data['state']);
			if (!empty($addr_data['zip'])) $address['zip'] = sanitize_text_field($addr_data['zip']);
			if (!empty($addr_data['phone'])) $address['phone'] = sanitize_text_field($addr_data['phone']);
			if (!empty($addr_data['email'])) $address['email'] = sanitize_email($addr_data['email']);
			if (!empty($addr_data['note'])) $address['note'] = sanitize_textarea_field($addr_data['note']);
			
			// Require at least one content field beyond 'type' (a select that always has a value)
			if (!empty(array_diff_key($address, ['type' => true]))) {
				$addresses[] = $address;
			}
		}
		$meta['address'] = $addresses;
	}

	// Process websites array from form submission
	if (isset($_POST['websites']) && is_array($_POST['websites'])) {
		$websites = [];
		foreach ($_POST['websites'] as $website) {
			$clean = esc_url_raw($website);
			if (!empty($clean)) {
				$websites[] = $clean;
			}
		}
		$meta['website'] = array_values(array_filter($websites)); // Remove empty and reindex
	}

	// Convert flat form fields to structured format
	foreach ($meta_groups as $fields) {
		foreach ($fields as $key => $config) {
			$raw_value = isset($submitted_meta[$key]) ? (string) $submitted_meta[$key] : '';
			$clean     = fi_admin_helpers_sanitize_field_value($config['type'] ?? 'text', $raw_value);

			if ($clean === '') {
				// Remove empty values from structured format
				switch ($key) {
					case 'email':
					case 'phone':
					case 'fax':
						unset($meta['contact'][$key]);
						break;
					case 'facebook':
					case 'twitter':
					case 'instagram':
					case 'youtube':
					case 'tiktok':
					case 'truth_social':
					case 'gab':
					case 'linkedin':
						$social_key = str_replace('_', '', $key);
						unset($meta['social'][$social_key]);
						break;
					case 'opensecrets_id':
						unset($meta['opensecrets_id']);
						break;
					case 'url_wikipedia':
						unset($meta['url_wikipedia']);
						break;
					case 'url_wikidata':
						unset($meta['url_wikidata']);
						break;
					case 'gender':
					case 'birth_date':
					case 'death_date':
					case 'url_openstates':
						unset($meta[$key]);
						break;
				}
			} else {
				// Map to structured format
				switch ($key) {
					// Contact fields
					case 'email':
					case 'phone':
					case 'fax':
						$meta['contact'][$key] = $clean;
						break;
					
					// Note: addresses and websites are handled separately via repeater UI
					
					// Social media fields
					case 'facebook':
					case 'twitter':
					case 'instagram':
					case 'youtube':
					case 'tiktok':
					case 'gab':
					case 'linkedin':
						$social_key = str_replace('_', '', $key);
						$meta['social'][$social_key] = $clean;
						break;
					case 'truth_social':
						$meta['social']['truthsocial'] = $clean;
						break;
					
					// External IDs stored in meta
					case 'opensecrets_id':
						// Store at top level of meta (not in legacy)
						$meta['opensecrets_id'] = $clean;
						break;
					case 'url_wikipedia':
						$meta['url_wikipedia'] = $clean;
						break;
					case 'url_wikidata':
						$meta['url_wikidata'] = $clean;
						break;
					case 'gender':
					case 'birth_date':
					case 'death_date':
					case 'url_openstates':
						$meta[$key] = $clean;
						break;
					
					// Other fields go to legacy
					default:
						if (!isset($meta['legacy'])) $meta['legacy'] = [];
						$meta['legacy'][$key] = $clean;
						break;
				}
			}
		}
	}

	//fi_log('LegMeta: '.json_encode($meta), __FILE__, __LINE__, 'debug');

	// Clean up empty arrays
	if (empty($meta['contact'])) $meta['contact'] = null;
	if (empty($meta['social'])) $meta['social'] = null;
	if (empty($meta['address'])) $meta['address'] = null;
	if (empty($meta['website'])) $meta['website'] = null; // null patches out the key via JSON_MERGE_PATCH

	return $meta;
}

/**
 * Apply API updates to a legislator
 */
function fi_admin_legislators_apply_api_updates(int $legislator_id, string $source, array $updates): int {
	$legislator = fi_legislator_get($legislator_id);
	if (!$legislator) {
		return 0;
	}

	$update_data = [];
	$meta = is_array($legislator->meta ?? null) ? $legislator->meta : [];
	$updated_count = 0;
	$needs_legislator_save = false;

	foreach ($updates as $field => $value) {
		$field = sanitize_text_field($field);
		$value = is_scalar($value) ? sanitize_text_field($value) : $value;

		// ---------------------------------------------------------------------
		// Special LegiScan (Local) mappings (repeaters + session image)
		// ---------------------------------------------------------------------
		if ($source === 'legiscan_local') {
			// URLs -> websites repeater (meta.website array)
			// Summary: LegiScan provides multiple authoritative URLs (biography/webmail/ballotpedia/votesmart).
			if (strpos($field, 'websites.') === 0) {
				$url = is_string($value) ? esc_url_raw($value) : '';

				// Do not store external reference sites in our Websites repeater.
				// Summary: Websites repeater is for official/owned sites; reference sites are noise.
				if ($url !== '') {
					$host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
					$host = strtolower($host);
					$deny = ['ballotpedia.org', 'votesmart.org'];
					foreach ($deny as $domain) {
						if ($host === $domain || (str_ends_with($host, '.' . $domain))) {
							continue 2; // skip this field
						}
					}
				}

				if ($url !== '') {
					$meta_norm = fi_legislator_meta_normalize(is_array($meta) ? $meta : []);
					$websites = $meta_norm['website'] ?? [];
					$websites = is_array($websites) ? $websites : [];
					if (!in_array($url, $websites, true)) {
						$websites[] = $url;
					}
					$meta_norm['website'] = array_values(array_filter($websites));
					$meta = $meta_norm;
					$updated_count++;
					$needs_legislator_save = true;
				}
				continue;
			}

			// Capitol address -> addresses repeater (update existing type=capitol, else add)
			if ($field === 'addresses.capitol') {
				$decoded = is_string($value) ? json_decode($value, true) : null;
				if (!is_array($decoded)) {
					continue;
				}
				// LegiScan payload is normalized in ajax to provide a single "address" field (no address1/address2),
				// but accept either shape for backward compatibility.
				$address = sanitize_text_field((string) ($decoded['address'] ?? ''));
				$address1 = sanitize_text_field((string) ($decoded['address1'] ?? ''));
				$address2 = sanitize_text_field((string) ($decoded['address2'] ?? ''));
				$city = sanitize_text_field((string) ($decoded['city'] ?? ''));
				$state = strtoupper(sanitize_text_field((string) ($decoded['state'] ?? '')));
				$zip = sanitize_text_field((string) ($decoded['zip'] ?? ''));

				$combined = $address !== '' ? $address : trim($address1 . ($address2 !== '' ? (' ' . $address2) : ''));

				$meta_norm = fi_legislator_meta_normalize(is_array($meta) ? $meta : []);
				$addresses = $meta_norm['address'] ?? [];
				$addresses = is_array($addresses) ? $addresses : [];

				$capitol = [
					'name' => 'Capitol Office',
					'type' => 'capitol',
				];
				if ($combined !== '') $capitol['address'] = $combined;
				if ($city !== '') $capitol['city'] = $city;
				if ($state !== '') $capitol['state'] = $state;
				if ($zip !== '') $capitol['zip'] = $zip;

				$updated = false;
				foreach ($addresses as $i => $addr) {
					if (is_array($addr) && (($addr['type'] ?? '') === 'capitol')) {
						$addresses[$i] = array_merge($addr, $capitol);
						$updated = true;
						break;
					}
				}
				if (!$updated) {
					$addresses[] = $capitol;
				}

				$meta_norm['address'] = array_values($addresses);
				$meta = $meta_norm;
				$updated_count++;
				$needs_legislator_save = true;
				continue;
			}

			// Bio Social Image -> sideload -> write fi_legislator_sessions.image_id for latest session
			if ($field === 'session_image_url') {
				$url = is_string($value) ? esc_url_raw($value) : '';
				if ($url === '') {
					continue;
				}
				// LegiScan image URLs sometimes omit .jpg. If not jpg/jpeg, append .jpg.
				if (!preg_match('/\.jpe?g(\?.*)?$/i', $url)) {
					$url .= '.jpg';
				}

				// Latest assigned session on the legislator object (ordered DESC by session start date).
				$latest = null;
				if (is_array($legislator->sessions ?? null) && !empty($legislator->sessions)) {
					$latest = reset($legislator->sessions);
				}
				$session_id = is_object($latest) ? (int) ($latest->session_id ?? 0) : 0;

				if ($session_id <= 0) {
					continue;
				}

				// Determine target basename: {session_id}_{remote_filename}.jpg
				$remote_name = basename((string) (parse_url($url, PHP_URL_PATH) ?: 'session-image.jpg'));
				$remote_name = sanitize_file_name($remote_name);
				if (!preg_match('/\.jpe?g$/i', $remote_name)) {
					$remote_name .= '.jpg';
				}
				$target_basename = $session_id . '_' . $remote_name;

				$gov_label = strtoupper((string) ($legislator->gov ?? ($latest->gov ?? 'US')));
				$display = trim((string) ($legislator->display_name ?? ''));
				$attachment_title = trim($gov_label . ' ' . $display . ' ' . (string) $legislator_id . ' s' . (string) $session_id);
				$attachment_slug = (string) $legislator_id . '-s' . (string) $session_id . '-' . (string) $target_basename;

				$img = \FI\Core\media_sideload_image_from_url($url, $target_basename, [
					// Summary: never include "Legiscan" in attachment-facing fields; title/slug should help staff search.
					'desc' => '',
					'post_title' => $attachment_title,
					'post_name' => $attachment_slug,
					'attach_post_id' => 0,
					'overwrite_basename' => $target_basename,
					'meta' => [
						'fi_source_url' => $url,
						'fi_session_id' => $session_id,
						'fi_legislator_id' => $legislator_id,
					],
				]);
				$attachment_id = (int) ($img['id'] ?? 0);
				if ($attachment_id <= 0) {
					continue;
				}

				// Update ONLY image_id on the exact legislator/session pair to avoid wiping other fields.
				global $wpdb;
				$wpdb->update(
					$wpdb->prefix . 'fi_legislator_sessions',
					['image_id' => (int) $attachment_id],
					['legislator_id' => $legislator_id, 'session_id' => $session_id],
					['%d'],
					['%d', '%d']
				);

				// Also set the main legislator image_id (current profile image policy).
				fi_legislator_save(['image_id' => (int) $attachment_id], $legislator_id);

				$updated_count++;
				continue;
			}
		}
		
		// Handle meta fields
		if (strpos($field, 'meta.') === 0) {
			$meta_key = substr($field, 5);
			if ($value !== null && $value !== '') {
				// Keep meta normalized: certain keys live in groups (contact/social).
				if (!is_array($meta)) {
					$meta = [];
				}
				
				// Contact group
				if (in_array($meta_key, ['email', 'phone', 'fax'], true)) {
					if (!isset($meta['contact']) || !is_array($meta['contact'])) {
						$meta['contact'] = [];
					}
					$meta['contact'][$meta_key] = $value;
					$updated_count++;
					$needs_legislator_save = true;
					continue;
				}
				
				// Social group
				$social_key = $meta_key;
				if ($meta_key === 'truth_social') {
					$social_key = 'truthsocial';
				} elseif (strpos($meta_key, '_') !== false) {
					$social_key = str_replace('_', '', $meta_key);
				}
				$social_keys = ['facebook','twitter','instagram','youtube','tiktok','gab','linkedin','truthsocial','bluesky','telegram'];
				if (in_array($social_key, $social_keys, true)) {
					if (!isset($meta['social']) || !is_array($meta['social'])) {
						$meta['social'] = [];
					}
					$meta['social'][$social_key] = $value;
					$updated_count++;
					$needs_legislator_save = true;
					continue;
				}
				
				// Default: top-level meta key
				$meta[$meta_key] = $value;
				$updated_count++;
				$needs_legislator_save = true;
			}
		} else {
			// Direct field updates
			if ($value !== null && $value !== '') {
				$update_data[$field] = $value;
				$updated_count++;
				$needs_legislator_save = true;
			}
		}
	}

	if ($updated_count > 0 && $needs_legislator_save) {
		if (!empty($update_data)) {
			$update_data['meta'] = $meta;
		} else {
			// Only meta updates
			$update_data = ['meta' => $meta];
		}
		fi_legislator_save($update_data, $legislator_id);
	}

	return $updated_count;
}

/**
 * Search legislators
 */
function fi_admin_legislators_search(string $query): array {
	return fi_legislators_search($query, 20);
}


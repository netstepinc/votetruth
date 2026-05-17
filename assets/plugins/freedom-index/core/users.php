<?php if (!defined('ABSPATH')) exit;

/**
 * Get user meta address data
 * Priority: fi_* fields > shipping_* fields > billing_* fields
 * 
 * @param int $user_id User ID
 * @param string $field Field name or 'address' for full address array
 * @return array|string|null Address array, specific field value, or null if not found
 */
function fi_user_meta_get($user_id, $field = 'address') {
	if (empty($user_id)) {
		return null;
	}
	$cacheKey = 'user/' . $user_id . '/' . $field;
	$address = fi_cache($cacheKey);
	if ($address) {
		return $address;
	}



	// Get all user meta at once (more efficient than multiple get_user_meta calls)
	$all_meta = get_user_meta($user_id);
	
	//Predefined sets of fields
	$meta = [];
	switch ($field) {
		case 'address':
			// Build address array with priority: fi_* > shipping_* > billing_*
			$fields = [
				'first_name',
				'last_name',
				'address_1',
				'address_2',
				'city',
				'state',
				'postcode',
				'country',
			];
			foreach ($fields as $field) {
				// Check fi_* first
				$fi_key = 'fi_' . $field;
				if (isset($all_meta[$fi_key]) && !empty($all_meta[$fi_key][0])) {
					$meta[$field] = $all_meta[$fi_key][0];
					continue;
				}
				
				// Check shipping_*
				$shipping_key = 'shipping_' . $field;
				if (isset($all_meta[$shipping_key]) && !empty($all_meta[$shipping_key][0])) {
					$meta[$field] = $all_meta[$shipping_key][0];
					continue;
				}
				
				// Check billing_*
				$billing_key = 'billing_' . $field;
				if (isset($all_meta[$billing_key]) && !empty($all_meta[$billing_key][0])) {
					$meta[$field] = $all_meta[$billing_key][0];
				}
			}
			// Return null if no address data found
			if (empty($meta)) {
				return null;
			}
			return $meta;

			break;
		default:
			$meta = (isset($all_meta[$field]) && !empty($all_meta[$field][0])) ? $all_meta[$field][0] : null;
			return $meta;
			break;
	}
}

/**
 * Save user meta values
 * Saves to fi_* prefixed fields to avoid overwriting WooCommerce data
 * 
 * @param int $user_id User ID
 * @param array $data Array of field => value pairs to save
 * @return bool True on success, false on failure
 */
function fi_user_meta_save($user_id, $data = []) {
	if (empty($user_id) || !is_array($data) || empty($data)) {
		return false;
	}
	
	// Define allowed address fields
	$allowed_fields = [
		'first_name',
		'last_name',
		'address_1',
		'address_2',
		'city',
		'state',
		'postcode',
		'country',
	];
	
	$saved = false;
	
	foreach ($data as $field => $value) {
		// Only save allowed fields
		if (!in_array($field, $allowed_fields)) {
			continue;
		}
		
		// Sanitize value
		$sanitized_value = sanitize_text_field($value);
		
		// Save to fi_* prefixed field
		$meta_key = 'fi_' . $field;
		$result = update_user_meta($user_id, $meta_key, $sanitized_value);
		
		if ($result !== false) {
			$saved = true;
		}
	}
	
	return $saved;
}

/**
 * Get PDF contacts for a user
 * 
 * @param int $user_id User ID
 * @return array Array of contact arrays, or empty array if none found
 */
function fi_pdf_contacts_get(int $user_id): array {
	if (empty($user_id)) {
		return [];
	}
	
	$pdf_contacts = get_user_meta($user_id, 'fi_pdf_contacts', true);
	if (!is_array($pdf_contacts)) {
		return [];
	}
	
	return $pdf_contacts;
}

/**
 * Get the user's default PDF contact index.
 *
 * @param int $user_id User ID
 * @return int|null Default index or null if not set/invalid
 */
function fi_pdf_contacts_default_index_get(int $user_id): ?int {
	if (empty($user_id)) {
		return null;
	}

	$contacts = fi_pdf_contacts_get($user_id);
	if (empty($contacts)) {
		return null;
	}

	$raw = get_user_meta($user_id, 'fi_pdf_contact_default_index', true);

	// If "default" is set and valid, use it; else fallback to first contact (index 0)
	if ($raw !== '' && $raw !== null) {
		$index = (int) $raw;
		if ($index >= 0 && isset($contacts[$index])) {
			return $index;
		}
	}

	// If default not set or invalid, but at least one contact exists, use first contact (index 0)
	$first_index = array_key_first($contacts);
	return ($first_index !== null && isset($contacts[$first_index])) ? $first_index : null;
}

/**
 * Set or clear the user's default PDF contact index.
 *
 * @param int $user_id User ID
 * @param int|null $index Contact index or null to clear
 * @return bool True on success
 */
function fi_pdf_contacts_default_index_set(int $user_id, ?int $index): bool {
	if (empty($user_id)) {
		return false;
	}

	if ($index === null) {
		return delete_user_meta($user_id, 'fi_pdf_contact_default_index') !== false;
	}

	if ($index < 0) {
		return false;
	}

	$contacts = fi_pdf_contacts_get($user_id);
	if (!isset($contacts[$index])) {
		return false;
	}

	return update_user_meta($user_id, 'fi_pdf_contact_default_index', (int) $index) !== false;
}

/**
 * Get the user's default PDF contact (contact array).
 *
 * @param int $user_id User ID
 * @return array|null Contact array or null if none
 */
function fi_pdf_contacts_default_get(int $user_id): ?array {
	$index = fi_pdf_contacts_default_index_get($user_id);
	if ($index === null) {
		return null;
	}
	return fi_pdf_contacts_get_by_index($user_id, $index);
}

/**
 * Get a specific PDF contact by index
 * 
 * @param int $user_id User ID
 * @param int $index Contact index
 * @return array|null Contact array or null if not found
 */
function fi_pdf_contacts_get_by_index(int $user_id, int $index): ?array {
	$pdf_contacts = fi_pdf_contacts_get($user_id);
	
	if (isset($pdf_contacts[$index])) {
		return $pdf_contacts[$index];
	}
	
	return null;
}

/**
 * Get guest PDF contacts from cookie (for server-side PDF generation).
 * Supports: (1) fi_guest_pdf_contacts = JSON array of {name, phone, email}; (2) fallback to single fi_personalize_* cookies as index 0.
 * Caller must validate indexes; returns full list only.
 *
 * @return array List of contact arrays (keys: name, phone, email)
 */
function fi_pdf_contacts_guest_get(): array {
	$cookie_name = 'fi_guest_pdf_contacts';
	if (!empty($_COOKIE[$cookie_name])) {
		$decoded = json_decode(stripslashes($_COOKIE[$cookie_name]), true);
		if (is_array($decoded)) {
			$out = [];
			foreach ($decoded as $c) {
				if (!is_array($c)) continue;
				$out[] = [
					'name'  => isset($c['name']) ? sanitize_text_field($c['name']) : '',
					'phone' => isset($c['phone']) ? sanitize_text_field($c['phone']) : '',
					'email' => isset($c['email']) ? sanitize_email($c['email']) : '',
				];
			}
			return $out;
		}
	}
	// Single-contact fallback from personalize cookies (index 0 only when used with contacts=0)
	$name  = isset($_COOKIE['fi_personalize_name']) ? sanitize_text_field($_COOKIE['fi_personalize_name']) : '';
	$phone = isset($_COOKIE['fi_personalize_phone']) ? sanitize_text_field($_COOKIE['fi_personalize_phone']) : '';
	$email = isset($_COOKIE['fi_personalize_email']) ? sanitize_email($_COOKIE['fi_personalize_email']) : '';
	if ($name !== '' || $phone !== '' || $email !== '') {
		return [ [ 'name' => $name, 'phone' => $phone, 'email' => $email ] ];
	}
	return [];
}

/**
 * Save PDF contact (add or update)
 * 
 * @param int $user_id User ID
 * @param array $contact_data Contact data with keys: name, phone, email
 * @param int|null $index Index to update (null to add new)
 * @return bool True on success, false on failure
 */
function fi_pdf_contacts_save(int $user_id, array $contact_data, ?int $index = null): bool {
	if (empty($user_id)) {
		return false;
	}
	
	// Get existing contacts
	$pdf_contacts = fi_pdf_contacts_get($user_id);
	
	// Sanitize contact data; name required, phone and email optional (keep all keys)
	$sanitized_contact = [
		'name'  => sanitize_text_field($contact_data['name'] ?? ''),
		'phone' => sanitize_text_field($contact_data['phone'] ?? ''),
		'email' => sanitize_email($contact_data['email'] ?? ''),
	];
	// Require at least name
	if ($sanitized_contact['name'] === '') {
		return false;
	}

	// Update existing or add new
	if ($index !== null && isset($pdf_contacts[$index])) {
		$pdf_contacts[$index] = $sanitized_contact;
	} else {
		$pdf_contacts[] = $sanitized_contact;
	}
	// Normalize to sequential 0-based keys so serialization and comparison are consistent
	$pdf_contacts = array_values($pdf_contacts);

	// Save contacts (update_user_meta returns false when value unchanged; treat as success)
	$result = update_user_meta($user_id, 'fi_pdf_contacts', $pdf_contacts);
	if ($result === false) {
		$current = get_user_meta($user_id, 'fi_pdf_contacts', true);
		if (!is_array($current)) {
			return false;
		}
		$current = array_values($current);
		// Value equality: unchanged save is success (WP returns false when nothing written)
		if ($current == $pdf_contacts) {
			return true;
		}
		return false;
	}

	// If no default exists yet, set the first saved contact as default (quality-of-life).
	$default_index = get_user_meta($user_id, 'fi_pdf_contact_default_index', true);
	if ($default_index === '' || $default_index === null) {
		$new_index = ($index !== null && isset($pdf_contacts[$index])) ? $index : (count($pdf_contacts) - 1);
		fi_pdf_contacts_default_index_set($user_id, (int) $new_index);
	}

	return true;
}

/**
 * Delete PDF contact by index
 * 
 * @param int $user_id User ID
 * @param int $index Contact index to delete
 * @return bool True on success, false on failure
 */
function fi_pdf_contacts_delete(int $user_id, int $index): bool {
	if (empty($user_id)) {
		return false;
	}
	
	// Get existing contacts
	$pdf_contacts = fi_pdf_contacts_get($user_id);
	$default_raw = get_user_meta($user_id, 'fi_pdf_contact_default_index', true);
	$default_index = ($default_raw === '' || $default_raw === null) ? null : (int) $default_raw;
	
	// Check if index exists
	if (!isset($pdf_contacts[$index])) {
		return false;
	}
	
	// Store original count to verify deletion
	$original_count = count($pdf_contacts);
	
	// Remove contact at index
	unset($pdf_contacts[$index]);
	
	// Re-index array
	$pdf_contacts = array_values($pdf_contacts);

	// Determine new default index (keep friction low):
	// - If the default contact was deleted, select the "next" contact (same position if possible),
	//   otherwise the last remaining contact.
	// - If the default contact was after the deleted index, it shifts down by 1.
	$new_default_index = null;
	if ($default_index !== null && $default_index >= 0) {
		if ($default_index === $index) {
			if (!empty($pdf_contacts)) {
				$new_default_index = min($index, count($pdf_contacts) - 1);
			} else {
				$new_default_index = null;
			}
		} elseif ($default_index > $index) {
			$new_default_index = $default_index - 1;
		} else {
			$new_default_index = $default_index;
		}
	}
	
	// Verify the contact was actually removed
	if (count($pdf_contacts) !== ($original_count - 1)) {
		return false;
	}
	
	// If no contacts remain, delete the meta entirely
	if (empty($pdf_contacts)) {
		// Clear default index too.
		delete_user_meta($user_id, 'fi_pdf_contact_default_index');
		$result = delete_user_meta($user_id, 'fi_pdf_contacts');
		// delete_user_meta returns true if deleted, false if not found or error
		// If meta doesn't exist, that's fine - it means it's already deleted
		return $result !== false;
	} else {
		// Save updated contacts (order preserved: oldest -> newest)
		$result = update_user_meta($user_id, 'fi_pdf_contacts', $pdf_contacts);

		// Persist default index AFTER saving contacts so we never validate against stale DB meta.
		if ($new_default_index === null) {
			delete_user_meta($user_id, 'fi_pdf_contact_default_index');
		} else {
			update_user_meta($user_id, 'fi_pdf_contact_default_index', (int) $new_default_index);
		}

		// update_user_meta returns meta_id (int) if new, true if updated, false on error
		// However, it can return false if value hasn't changed (even though we know it has)
		// So we verify the deletion was successful by checking the stored value
		if ($result === false) {
			// Double-check: verify the contact is actually gone from the database
			$verify_contacts = fi_pdf_contacts_get($user_id);
			return !isset($verify_contacts[$index]) && count($verify_contacts) === count($pdf_contacts);
		}
		return true;
	}
}

/**
 * Update user profile (email, display name, password)
 * Follows WordPress best practices for user updates
 * 
 * @param int $user_id User ID
 * @param array $data Array with keys: user_email, display_name, user_pass
 * @return int|WP_Error User ID on success, WP_Error on failure
 */
function fi_user_profile_update(int $user_id, array $data) {
	if (empty($user_id)) {
		return new \WP_Error('invalid_user', 'Invalid user ID.');
	}
	
	// Verify user exists
	$user = get_userdata($user_id);
	if (!$user) {
		return new \WP_Error('user_not_found', 'User not found.');
	}
	
	$update_data = ['ID' => $user_id];
	
	// Validate and update email
	if (isset($data['user_email'])) {
		$email = sanitize_email($data['user_email']);
		
		if (empty($email) || !is_email($email)) {
			return new \WP_Error('invalid_email', 'Invalid email address.');
		}
		
		// Check if email is already in use by another user
		$email_exists = email_exists($email);
		if ($email_exists && $email_exists !== $user_id) {
			return new \WP_Error('email_exists', 'Email address is already in use.');
		}
		
		$update_data['user_email'] = $email;
	}
	
	// Update display name if provided
	if (isset($data['display_name']) && $data['display_name'] !== '') {
		$update_data['display_name'] = sanitize_text_field($data['display_name']);
	}
	
	// Update password if provided
	if (!empty($data['user_pass'])) {
		$password = $data['user_pass'];
		
		// Validate password length
		if (strlen($password) < 8) {
			return new \WP_Error('password_short', 'Password must be at least 8 characters.');
		}
		
		// If password confirmation is provided, verify they match
		if (isset($data['user_pass_confirm']) && $password !== $data['user_pass_confirm']) {
			return new \WP_Error('password_mismatch', 'Passwords do not match.');
		}
		
		$update_data['user_pass'] = $password;
	}
	
	// Use WordPress core function for user update
	$result = wp_update_user($update_data);
	
	return $result;
}

/**
 * Get all user preferences
 * 
 * @param int $user_id User ID
 * @return array Array of preferences
 */
function fi_user_prefs_get(int $user_id): array {
	if (empty($user_id)) {
		return [];
	}
	
	$prefs = get_user_meta($user_id, 'fi_prefs', true);
	if (!is_array($prefs)) {
		return [];
	}
	
	return $prefs;
}

/**
 * Get user preference
 * 
 * @param int $user_id User ID
 * @param string $key Preference key
 * @param mixed $default Default value if not found
 * @return mixed Preference value or default
 */
function fi_user_pref_get(int $user_id, string $key, $default = null) {
	if (empty($user_id) || empty($key)) {
		return $default;
	}
	
	$prefs = fi_user_prefs_get($user_id);
	
	return $prefs[$key] ?? $default;
}

/**
 * Save all user preferences
 * 
 * @param int $user_id User ID
 * @param array $prefs Array of preferences
 * @return bool True on success, false on failure
 */
function fi_user_prefs_save(int $user_id, array $prefs): bool {
	if (empty($user_id) || !is_array($prefs)) {
		return false;
	}
	
	$result = update_user_meta($user_id, 'fi_prefs', $prefs);
	
	return $result !== false;
}

/**
 * Save user preference
 * 
 * @param int $user_id User ID
 * @param string $key Preference key
 * @param mixed $value Preference value
 * @return bool True on success, false on failure
 */
function fi_user_pref_save(int $user_id, string $key, $value): bool {
	if (empty($user_id) || empty($key)) {
		return false;
	}
	
	$prefs = fi_user_prefs_get($user_id);
	$prefs[$key] = $value;
	
	return fi_user_prefs_save($user_id, $prefs);
}

/**
 * Get user personalize data (name, phone, email for PDFs)
 * 
 * @param int $user_id User ID
 * @return array Array with keys: name, phone, email
 */
function fi_user_personalize_get(int $user_id): array {
	if (empty($user_id)) {
		return ['name' => '', 'phone' => '', 'email' => ''];
	}
	
	$user = get_userdata($user_id);
	
	return [
		'name' => get_user_meta($user_id, 'fi_personalize_name', true) ?: '',
		'phone' => get_user_meta($user_id, 'fi_personalize_phone', true) ?: '',
		'email' => get_user_meta($user_id, 'fi_personalize_email', true) ?: ($user->user_email ?? ''),
	];
}

/**
 * Save user personalize data
 * 
 * @param int $user_id User ID
 * @param array $data Array with keys: name, phone, email
 * @return bool True on success, false on failure
 */
function fi_user_personalize_save(int $user_id, array $data): bool {
	if (empty($user_id)) {
		return false;
	}
	
	$saved = false;
	
	if (isset($data['name'])) {
		$result = update_user_meta($user_id, 'fi_personalize_name', sanitize_text_field($data['name']));
		if ($result !== false) {
			$saved = true;
		}
	}
	
	if (isset($data['phone'])) {
		$result = update_user_meta($user_id, 'fi_personalize_phone', sanitize_text_field($data['phone']));
		if ($result !== false) {
			$saved = true;
		}
	}
	
	if (isset($data['email'])) {
		$email = sanitize_email($data['email']);
		if (!empty($email) && is_email($email)) {
			$result = update_user_meta($user_id, 'fi_personalize_email', $email);
			if ($result !== false) {
				$saved = true;
			}
		}
	}
	
	return $saved;
}
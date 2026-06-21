<?php if (!defined('ABSPATH')) { exit; }


//Disable automatic conversion of markdown-style patterns (e.g., "1. ") into lists.
add_filter('tiny_mce_before_init', function($init_array) {
    // Disables the automatic conversion of markdown-style patterns (e.g., "1. ") into lists.
    $init_array['text_patterns'] = false;
    return $init_array;
});

/**
 * Get count for menu display
 */
if (!function_exists('fi_admin_helpers_get_menu_count')) {
function fi_admin_helpers_get_menu_count(string $type): int {
	global $wpdb;
	
	switch ($type) {
		case 'legislators':
			return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fi_legislators");
		case 'votes':
			return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fi_votes");
		case 'sessions':
			return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fi_sessions");
		case 'reports':
			return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fi_reports");
		default:
			return 0;
	}
}
}

/**
 * Get government display name
 */
function fi_admin_helpers_get_gov_display_name(string $gov): string {
	$state_names = [
		'US' => 'Congress',
		'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
		'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
		'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
		'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
		'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
		'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
		'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
		'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
		'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
		'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
		'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
		'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
		'WI' => 'Wisconsin', 'WY' => 'Wyoming'
	];
	
	return $state_names[$gov] ?? $gov;
}

/**
 * Sanitize a single field value
 */
function fi_admin_helpers_sanitize_field_value(string $type, string $value): string {
	$value = trim($value);
	if ($value === '') {
		return '';
	}

	return match ($type) {
		'email'    => sanitize_email($value),
		'url'      => esc_url_raw($value),
		'textarea' => sanitize_textarea_field($value),
		'wysiwyg', 'editor' => \FI\Core\Votes::normalize_meta_description_string($value),
		default    => sanitize_text_field($value),
	};
}

/**
 * Party options keyed by abbreviation
 */
function fi_admin_helpers_get_party_options(): array {
	$parties = fi_parties();
	$options = [];
	foreach ($parties as $abbr => $data) {
		$options[strtoupper($abbr)] = $data['name'] ?? strtoupper($abbr);
	}
	return $options;
}

/**
 * Render image media picker HTML
 */
function fi_admin_helpers_render_image_media_picker(int $image_id = 0): string {
	$image_url = '';
	$image_alt = '';
	
	if ($image_id > 0) {
		$image_url = wp_get_attachment_image_url($image_id, 'medium');
		$image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
	}
	ob_start();
?>
<div class="fi-image-media-picker">
	<input type="hidden" name="image_id" id="fi-legislator-image-id" value="<?php echo esc_attr($image_id); ?>">
	<input type="file" id="fi-legislator-image-upload-input" accept="image/*" style="display:none;">
	<?php if ($image_url): ?>
	<div class="fi-image-preview mb-3">
		<img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>" style="width: 100%;  max-width: 200px; height: auto; border: 1px solid #ddd; padding: 4px; background: #fff;" id="fi-legislator-image-preview">';
	</div>
	<div class="d-flex gap-2">
		<button type="button" class="btn btn-sm btn-primary" id="fi-legislator-image-select">Change Image</button>
		<button type="button" class="btn button-link-delete" id="fi-legislator-image-remove">Remove</button>
	</div>
	<?php else: ?>
	<div class="fi-image-preview mb-3" style="display: none;">
		<img src="" alt="" style="max-width: 200px; height: auto;" id="fi-legislator-image-preview" class="border p-2 bg-white">
	</div>
	<div class="d-flex gap-2">
		<button type="button" class="btn btn-sm btn-primary" id="fi-legislator-image-select">Select Image</button>
	</div>
	<?php endif; ?>
	<div class="mt-3">
		<label class="form-label fw-bold mb-0" for="fi-legislator-image-upload-input">Upload Image</label>
		<div class="description mb-2 small text-muted">Selecting a file uploads it and updates the image immediately.</div>
		<input type="file" id="fi-legislator-image-upload-input-visible" accept="image/*" class="regular-text" />
	</div>
	<div class="mt-3">
		<label class="form-label fw-bold" for="fi-legislator-image-url">Image URL</label>
		<div class="input-group">
			<input type="url" class="form-control regular-text flex-nowrap" id="fi-legislator-image-url" placeholder="https://example.com/photo.jpg">
			<button type="button" class="btn btn-sm btn-primary" id="fi-legislator-image-fetch">Fetch</button>
		</div>
	</div>
	<div class="description mt-2 small text-muted">Uploads are saved with a <strong>Legislator ID</strong> prefix to avoid collisions.</div>
	<div class="description small text-muted">Tip: you can also paste an image URL and click <strong>Fetch</strong>.</div>
</div>
<?php
	return ob_get_clean();
}

/**
 * Render vote image media picker (media library select only; no upload/URL fetch).
 * Stores attachment ID in meta[image_id] for fi_votes.meta.
 */
function fi_admin_helpers_render_vote_image_media_picker(int $image_id = 0): string {
	$image_url = '';
	$image_alt = '';
	if ($image_id > 0) {
		$image_url = wp_get_attachment_image_url($image_id, 'medium');
		$image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
	}
	ob_start();
?>
<div class="fi-vote-image-media-picker">
	<input type="hidden" name="meta[image_id]" id="fi-vote-image-id" class="fi-vote-image-id" value="<?php echo esc_attr($image_id); ?>">
	<?php if ($image_url): ?>
	<div class="fi-image-preview mb-2">
		<img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>" class="fi-vote-image-preview-img" style="max-width: 200px; height: auto; border: 1px solid #ddd; padding: 4px; background: #fff;" id="fi-vote-image-preview">
	</div>
	<div class="d-flex gap-2">
		<button type="button" class="btn btn-sm btn-outline-primary fi-vote-image-select-btn" id="fi-vote-image-select">Change Image</button>
		<button type="button" class="btn btn-sm btn-outline-secondary fi-vote-image-remove-btn" id="fi-vote-image-remove">Remove</button>
	</div>
	<?php else: ?>
	<div class="fi-image-preview mb-2" style="display: none;">
		<img src="" alt="" class="fi-vote-image-preview-img border p-2 bg-white" style="max-width: 200px; height: auto;" id="fi-vote-image-preview">
	</div>
	<div class="d-flex gap-2">
		<button type="button" class="btn btn-sm btn-outline-primary fi-vote-image-select-btn" id="fi-vote-image-select">Select Image</button>
		<button type="button" class="btn btn-sm btn-outline-secondary fi-vote-image-remove-btn" id="fi-vote-image-remove" style="display: none;">Remove</button>
	</div>
	<?php endif; ?>
</div>
<?php
	return ob_get_clean();
}


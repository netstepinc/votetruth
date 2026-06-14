<?php
/*
 * Freedom Index Member Dashboard Integration
 *
 * Straight function version of the former FICore\MemberIntegration class file.
 *
 * Integrates Freedom Index with member dashboard and WooCommerce.
 * Provides elected officials lookup and FI score display.
 
Key adjustments:

Removed the FICore\MemberIntegration class/namespace wrapper.
Registered hooks directly through fi_member_integration_init().
Converted public methods into global functions:
fi_member_get_elected_officials()
fi_member_get_user_address()
fi_member_save_user_address()
fi_member_get_user_lists()
fi_member_save_user_list()
fi_member_add_dashboard_section()
fi_member_add_address_form()
fi_member_save_fi_address()
Converted private helpers into reusable functions:
fi_member_get_congressional_officials()
fi_member_get_state_officials()
fi_member_format_address()
fi_member_state_options()
Fixed the AJAX address save issue: the form sends serialized string data, but the original code treated $_POST['data'] as an array. The refactor parses it with parse_str() before sanitizing.
Removed slug output from official arrays where this file generated fallback official data.
*/

if (!defined('ABSPATH')) exit;

/**
 * Initialize member integration hooks.
 *
 * @return void
 */
function fi_member_integration_init(): void {
	add_action('wp_ajax_fi_get_elected_officials', 'fi_member_ajax_get_elected_officials');
	add_action('wp_ajax_fi_save_user_address', 'fi_member_ajax_save_user_address');
	add_action('wp_ajax_fi_get_user_lists', 'fi_member_ajax_get_user_lists');
	add_action('wp_ajax_fi_save_user_list', 'fi_member_ajax_save_user_list');

	if (class_exists('WooCommerce')) {
		add_action('woocommerce_account_dashboard', 'fi_member_add_dashboard_section');
		add_action('woocommerce_edit_account_form', 'fi_member_add_address_form');
		add_action('woocommerce_save_account_details', 'fi_member_save_fi_address');
	}
}
add_action('plugins_loaded', 'fi_member_integration_init');

/**
 * Get elected officials for user's saved address.
 *
 * @param int $user_id User ID.
 * @return array Officials array.
 */
function fi_member_get_elected_officials(int $user_id): array {
	$address = fi_member_get_user_address($user_id);
	if (!$address) {
		return [];
	}

	$zip = $address['postcode'] ?? '';
	if (!$zip) {
		return [];
	}

	if (class_exists('\FI\Public\LegislatorLookup') && method_exists('\FI\Public\LegislatorLookup', 'get_officials_with_scores')) {
		return \FI\Public\LegislatorLookup::get_officials_with_scores($zip);
	}

	return [];
}

/**
 * Get user's address from WooCommerce billing fields.
 *
 * @param int $user_id User ID.
 * @return array|null Address array or null if incomplete/unavailable.
 */
function fi_member_get_user_address(int $user_id): ?array {
	if (!class_exists('WooCommerce')) {
		return null;
	}

	$address = [
		'first_name' => get_user_meta($user_id, 'billing_first_name', true),
		'last_name'  => get_user_meta($user_id, 'billing_last_name', true),
		'address_1'  => get_user_meta($user_id, 'billing_address_1', true),
		'address_2'  => get_user_meta($user_id, 'billing_address_2', true),
		'city'       => get_user_meta($user_id, 'billing_city', true),
		'state'      => get_user_meta($user_id, 'billing_state', true),
		'postcode'   => get_user_meta($user_id, 'billing_postcode', true),
		'country'    => get_user_meta($user_id, 'billing_country', true),
	];

	if (empty($address['postcode']) || empty($address['state'])) {
		return null;
	}

	return $address;
}

/**
 * Get current congressional officials.
 *
 * NOTE: This is retained as a fallback/helper. It does not filter by actual district.
 * The primary lookup path should use the Geocod.io-backed LegislatorLookup service.
 *
 * @param object $zip_data ZIP lookup data.
 * @return array Officials array.
 */
function fi_member_get_congressional_officials(object $zip_data): array {
	global $wpdb;

	$officials = [];

	$us_session = $wpdb->get_row(
		"SELECT * FROM {$wpdb->prefix}fi_sessions
		WHERE gov = 'US'
		ORDER BY date_start DESC
		LIMIT 1",
		ARRAY_A
	);

	if (!$us_session) {
		return $officials;
	}

	$legislators = $wpdb->get_results($wpdb->prepare(
		"SELECT l.*, ls.*, s.name as session_name
		FROM {$wpdb->prefix}fi_legislators l
		INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
		INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
		WHERE ls.session_id = %d AND s.gov = 'US'
		ORDER BY ls.chamber, l.last_name",
		$us_session['id']
	), ARRAY_A);

	foreach ($legislators as $legislator) {
		$score = $wpdb->get_row($wpdb->prepare(
			"SELECT ls.score FROM {$wpdb->prefix}fi_legislator_sessions ls
			WHERE legislator_id = %d AND session_id = %d",
			$legislator['id'],
			$us_session['id']
		), ARRAY_A);

		$officials[] = [
			'id'        => $legislator['id'],
			'name'      => $legislator['display_name'],
			'chamber'   => $legislator['chamber'],
			'district'  => $legislator['district'],
			'party'     => $legislator['party'],
			'gov'       => 'US',
			'session'   => $us_session['name'],
			'score'     => $score ? $score['score'] : null,
			'grade'     => $score && isset($score['score']) ? fi_score_calculate_grade((float) $score['score']) : null,
			'image_url' => function_exists('fi_legislator_image') ? fi_legislator_image($legislator['id'], $us_session['id'], 'medium') : '',
		];
	}

	return $officials;
}

/**
 * Get current state officials.
 *
 * NOTE: This is retained as a fallback/helper. It does not filter by actual district.
 * The primary lookup path should use the Geocod.io-backed LegislatorLookup service.
 *
 * @param object $zip_data ZIP lookup data.
 * @param string $state State code.
 * @return array Officials array.
 */
function fi_member_get_state_officials(object $zip_data, string $state): array {
	global $wpdb;

	$officials = [];
	$state = strtoupper($state);

	if ($state === '') {
		return $officials;
	}

	$state_session = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}fi_sessions
		WHERE gov = %s
		ORDER BY date_start DESC
		LIMIT 1",
		$state
	), ARRAY_A);

	if (!$state_session) {
		return $officials;
	}

	$legislators = $wpdb->get_results($wpdb->prepare(
		"SELECT l.*, ls.*, s.name as session_name
		FROM {$wpdb->prefix}fi_legislators l
		INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
		INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
		WHERE ls.session_id = %d AND s.gov = %s
		ORDER BY ls.chamber, l.last_name",
		$state_session['id'],
		$state
	), ARRAY_A);

	foreach ($legislators as $legislator) {
		$score = $wpdb->get_row($wpdb->prepare(
			"SELECT ls.score FROM {$wpdb->prefix}fi_legislator_sessions ls
			WHERE legislator_id = %d AND session_id = %d",
			$legislator['id'],
			$state_session['id']
		), ARRAY_A);

		$officials[] = [
			'id'        => $legislator['id'],
			'name'      => $legislator['display_name'],
			'chamber'   => $legislator['chamber'],
			'district'  => $legislator['district'],
			'party'     => $legislator['party'],
			'gov'       => $state,
			'session'   => $state_session['name'],
			'score'     => $score ? $score['score'] : null,
			'grade'     => $score && isset($score['score']) ? fi_score_calculate_grade((float) $score['score']) : null,
			'image_url' => function_exists('fi_legislator_image') ? fi_legislator_image($legislator['id'], $state_session['id'], 'medium') : '',
		];
	}

	return $officials;
}

/**
 * Save user address to WooCommerce billing fields.
 *
 * @param int $user_id User ID.
 * @param array $address Address data.
 * @return bool
 */
function fi_member_save_user_address(int $user_id, array $address): bool {
	if (!class_exists('WooCommerce')) {
		return false;
	}

	$fields = [
		'billing_first_name',
		'billing_last_name',
		'billing_address_1',
		'billing_address_2',
		'billing_city',
		'billing_state',
		'billing_postcode',
		'billing_country',
	];

	foreach ($fields as $field) {
		$key = str_replace('billing_', '', $field);
		if (isset($address[$key])) {
			update_user_meta($user_id, $field, sanitize_text_field($address[$key]));
		}
	}

	return true;
}

/**
 * Get user lists.
 *
 * @param int $user_id User ID.
 * @return array
 */
function fi_member_get_user_lists(int $user_id): array {
	return function_exists('fi_lists_get_by_user') ? fi_lists_get_by_user($user_id) : [];
}

/**
 * Save user list.
 *
 * @param int $user_id User ID.
 * @param string $name List name.
 * @param array $legislators Legislator IDs.
 * @return int|null New list ID or null.
 */
function fi_member_save_user_list(int $user_id, string $name, array $legislators): ?int {
	global $wpdb;

	$legislators = array_values(array_filter(array_map('absint', $legislators)));

	$result = $wpdb->insert(
		"{$wpdb->prefix}fi_user_lists",
		[
			'user_id'     => $user_id,
			'name'        => sanitize_text_field($name),
			'legislators' => wp_json_encode($legislators),
		],
		['%d', '%s', '%s']
	);

	return $result === false ? null : (int) $wpdb->insert_id;
}

/**
 * Format address for display.
 *
 * @param array $address Address data.
 * @return string Formatted address.
 */
function fi_member_format_address(array $address): string {
	$parts = [];

	foreach (['address_1', 'address_2', 'city', 'state', 'postcode'] as $key) {
		if (!empty($address[$key])) {
			$parts[] = $address[$key];
		}
	}

	return implode(', ', $parts);
}

/**
 * Get state options for member address forms.
 *
 * @return array State options.
 */
function fi_member_state_options(): array {
	if (function_exists('fi_state_options')) {
		$options = fi_state_options();
		unset($options['US']);
		return $options;
	}

	return [
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
		'WI' => 'Wisconsin', 'WY' => 'Wyoming',
	];
}

/**
 * Add FI section to WooCommerce dashboard.
 *
 * @return void
 */
function fi_member_add_dashboard_section(): void {
	$user_id = get_current_user_id();
	$address = fi_member_get_user_address($user_id);
	?>
	<div class="fi-member-dashboard">
		<h2>Your Elected Officials</h2>

		<?php if ($address): ?>
			<div class="fi-address-info">
				<p><strong>Address:</strong> <?php echo esc_html(fi_member_format_address($address)); ?></p>
			</div>

			<div class="fi-officials-list">
				<?php
				$officials = fi_member_get_elected_officials($user_id);
				if (!empty($officials)):
					foreach ($officials as $official):
						$official_id = (int) ($official['id'] ?? 0);
						$profile_url = function_exists('fi_url_legislator') ? fi_url_legislator($official_id) : '#';
						?>
						<div class="fi-official-card">
							<div class="fi-official-image">
								<?php if (!empty($official['image_url'])): ?>
									<img src="<?php echo esc_url($official['image_url']); ?>" alt="<?php echo esc_attr($official['name'] ?? ''); ?>">
								<?php endif; ?>
							</div>

							<div class="fi-official-info">
								<h3><?php echo esc_html($official['name'] ?? ''); ?></h3>
								<p class="fi-official-title">
									<?php echo esc_html(ucfirst((string) ($official['chamber'] ?? ''))); ?>
									<?php if (!empty($official['district'])): ?>
										- District <?php echo esc_html($official['district']); ?>
									<?php endif; ?>
								</p>
								<p class="fi-official-party"><?php echo esc_html($official['party'] ?? ''); ?></p>

								<?php if (array_key_exists('score', $official) && $official['score'] !== null): ?>
									<div class="fi-official-score">
										<span class="fi-score-value"><?php echo esc_html($official['score']); ?>%</span>
										<?php if (!empty($official['grade'])): ?>
											<span class="fi-score-grade">(<?php echo esc_html($official['grade']); ?>)</span>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							</div>

							<div class="fi-official-actions">
								<a href="<?php echo esc_url($profile_url); ?>" class="button" target="_blank" rel="noopener">View Profile</a>
							</div>
						</div>
						<?php
					endforeach;
				else:
					?>
					<p>No elected officials found for your address.</p>
					<?php
				endif;
				?>
			</div>
		<?php else: ?>
			<div class="fi-no-address">
				<p>Please add your address to see your elected officials.</p>
				<button type="button" class="button" id="fi-add-address">Add Address</button>
			</div>

			<div class="fi-address-form" style="display: none;">
				<form id="fi-address-form">
					<table class="form-table">
						<tr>
							<th scope="row">First Name</th>
							<td><input type="text" name="first_name" class="regular-text"></td>
						</tr>
						<tr>
							<th scope="row">Last Name</th>
							<td><input type="text" name="last_name" class="regular-text"></td>
						</tr>
						<tr>
							<th scope="row">Address</th>
							<td><input type="text" name="address_1" class="regular-text"></td>
						</tr>
						<tr>
							<th scope="row">City</th>
							<td><input type="text" name="city" class="regular-text"></td>
						</tr>
						<tr>
							<th scope="row">State</th>
							<td>
								<select name="state" class="regular-text">
									<option value="">Select State</option>
									<?php foreach (fi_member_state_options() as $code => $name): ?>
										<option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">ZIP Code</th>
							<td><input type="text" name="postcode" class="regular-text"></td>
						</tr>
					</table>

					<p class="submit">
						<input type="submit" class="button button-primary" value="Save Address">
						<button type="button" class="button" id="fi-cancel-address">Cancel</button>
					</p>
				</form>
			</div>
		<?php endif; ?>
	</div>

	<script>
	jQuery(function($) {
		$('#fi-add-address').on('click', function() {
			$('.fi-no-address').hide();
			$('.fi-address-form').show();
		});

		$('#fi-cancel-address').on('click', function() {
			$('.fi-address-form').hide();
			$('.fi-no-address').show();
		});

		$('#fi-address-form').on('submit', function(e) {
			e.preventDefault();

			$.ajax({
				url: typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
				type: 'POST',
				data: {
					action: 'fi_save_user_address',
					data: $(this).serialize(),
					nonce: '<?php echo esc_js(wp_create_nonce('fi_save_address')); ?>'
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert('Error saving address: ' + response.data);
					}
				}
			});
		});
	});
	</script>
	<?php
}

/**
 * Add FI address form to WooCommerce account edit.
 *
 * @return void
 */
function fi_member_add_address_form(): void {
	$user_id = get_current_user_id();
	$address = fi_member_get_user_address($user_id) ?: [];
	?>
	<fieldset>
		<legend>Freedom Index Address</legend>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="fi_address_1">Address</label>
			<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="fi_address_1" id="fi_address_1" value="<?php echo esc_attr($address['address_1'] ?? ''); ?>">
		</p>

		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="fi_city">City</label>
			<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="fi_city" id="fi_city" value="<?php echo esc_attr($address['city'] ?? ''); ?>">
		</p>

		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="fi_state">State</label>
			<select class="woocommerce-Input woocommerce-Input--select input-select" name="fi_state" id="fi_state">
				<option value="">Select State</option>
				<?php foreach (fi_member_state_options() as $code => $name): ?>
					<option value="<?php echo esc_attr($code); ?>" <?php selected($address['state'] ?? '', $code); ?>><?php echo esc_html($name); ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="fi_postcode">ZIP Code</label>
			<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="fi_postcode" id="fi_postcode" value="<?php echo esc_attr($address['postcode'] ?? ''); ?>">
		</p>
	</fieldset>
	<?php
}

/**
 * Save FI address from WooCommerce account edit form.
 *
 * @param int $user_id User ID.
 * @return void
 */
function fi_member_save_fi_address(int $user_id): void {
	if (!class_exists('WooCommerce')) {
		return;
	}

	$fields = [
		'fi_address_1' => 'billing_address_1',
		'fi_city'      => 'billing_city',
		'fi_state'     => 'billing_state',
		'fi_postcode'  => 'billing_postcode',
	];

	foreach ($fields as $form_field => $meta_field) {
		if (isset($_POST[$form_field])) {
			update_user_meta($user_id, $meta_field, sanitize_text_field(wp_unslash($_POST[$form_field])));
		}
	}
}

/**
 * AJAX handler for getting elected officials.
 *
 * @return void
 */
function fi_member_ajax_get_elected_officials(): void {
	check_ajax_referer('fi_admin_nonce', 'nonce');

	$user_id = get_current_user_id();
	if (!$user_id) {
		wp_send_json_error('User not logged in');
	}

	wp_send_json_success(fi_member_get_elected_officials($user_id));
}

/**
 * AJAX handler for saving user address.
 *
 * @return void
 */
function fi_member_ajax_save_user_address(): void {
	check_ajax_referer('fi_save_address', 'nonce');

	$user_id = get_current_user_id();
	if (!$user_id) {
		wp_send_json_error('User not logged in');
	}

	$raw_data = $_POST['data'] ?? [];
	if (is_string($raw_data)) {
		parse_str($raw_data, $parsed_data);
		$raw_data = $parsed_data;
	}

	if (!is_array($raw_data)) {
		$raw_data = [];
	}

	$address = [
		'first_name' => sanitize_text_field(wp_unslash($raw_data['first_name'] ?? '')),
		'last_name'  => sanitize_text_field(wp_unslash($raw_data['last_name'] ?? '')),
		'address_1'  => sanitize_text_field(wp_unslash($raw_data['address_1'] ?? '')),
		'address_2'  => sanitize_text_field(wp_unslash($raw_data['address_2'] ?? '')),
		'city'       => sanitize_text_field(wp_unslash($raw_data['city'] ?? '')),
		'state'      => sanitize_text_field(wp_unslash($raw_data['state'] ?? '')),
		'postcode'   => sanitize_text_field(wp_unslash($raw_data['postcode'] ?? '')),
		'country'    => 'US',
	];

	$result = fi_member_save_user_address($user_id, $address);

	if ($result) {
		wp_send_json_success('Address saved successfully');
	}

	wp_send_json_error('Failed to save address');
}

/**
 * AJAX handler for getting user lists.
 *
 * @return void
 */
function fi_member_ajax_get_user_lists(): void {
	check_ajax_referer('fi_admin_nonce', 'nonce');

	$user_id = get_current_user_id();
	if (!$user_id) {
		wp_send_json_error('User not logged in');
	}

	wp_send_json_success(fi_member_get_user_lists($user_id));
}

/**
 * AJAX handler for saving user list.
 *
 * @return void
 */
function fi_member_ajax_save_user_list(): void {
	check_ajax_referer('fi_admin_nonce', 'nonce');

	$user_id = get_current_user_id();
	if (!$user_id) {
		wp_send_json_error('User not logged in');
	}

	$name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
	$legislators = isset($_POST['legislators']) && is_array($_POST['legislators']) ? array_map('absint', $_POST['legislators']) : [];

	if (empty($name) || empty($legislators)) {
		wp_send_json_error('Name and legislators are required');
	}

	$list_id = fi_member_save_user_list($user_id, $name, $legislators);

	if ($list_id) {
		wp_send_json_success(['list_id' => $list_id]);
	}

	wp_send_json_error('Failed to save list');
}

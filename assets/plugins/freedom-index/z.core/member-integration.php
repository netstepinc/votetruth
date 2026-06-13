<?php
namespace FI\Core{

	if (!defined('ABSPATH')) exit;

	/**
	* Member Dashboard Integration for Freedom Index Admin
	* 
	* Integrates Freedom Index with member dashboard and WooCommerce.
	* Provides elected officials lookup and FI score display.
	*/
	final class MemberIntegration {

		/**
		* Initialize member integration
		*/
		public static function init(): void {
			add_action('wp_ajax_fi_get_elected_officials', [self::class, 'ajax_get_elected_officials']);
			add_action('wp_ajax_fi_save_user_address', [self::class, 'ajax_save_user_address']);
			add_action('wp_ajax_fi_get_user_lists', [self::class, 'ajax_get_user_lists']);
			add_action('wp_ajax_fi_save_user_list', [self::class, 'ajax_save_user_list']);
			
			// WooCommerce integration
			if (class_exists('WooCommerce')) {
				add_action('woocommerce_account_dashboard', [self::class, 'add_fi_dashboard_section']);
				add_action('woocommerce_edit_account_form', [self::class, 'add_fi_address_form']);
				add_action('woocommerce_save_account_details', [self::class, 'save_fi_address']);
			}
		}

		/**
		* Get elected officials for user's address
		*/
		public static function get_elected_officials(int $user_id): array {
			$address = self::get_user_address($user_id);
			if (!$address) {
				return [];
			}
			
			$zip = $address['postcode'] ?? '';
			if (!$zip) {
				return [];
			}
			
			// Use Geocod.io API for real-time legislator lookup
			$officials = \FI\Public\LegislatorLookup::get_officials_with_scores($zip);
			return $officials;
		}

		/**
		* Get user's address from WooCommerce billing
		*/
		public static function get_user_address(int $user_id): ?array {
			if (!class_exists('WooCommerce')) {
				return null;
			}
			
			$address = [
				'first_name' => get_user_meta($user_id, 'billing_first_name', true),
				'last_name' => get_user_meta($user_id, 'billing_last_name', true),
				'address_1' => get_user_meta($user_id, 'billing_address_1', true),
				'address_2' => get_user_meta($user_id, 'billing_address_2', true),
				'city' => get_user_meta($user_id, 'billing_city', true),
				'state' => get_user_meta($user_id, 'billing_state', true),
				'postcode' => get_user_meta($user_id, 'billing_postcode', true),
				'country' => get_user_meta($user_id, 'billing_country', true)
			];
			
			// Check if address is complete
			if (empty($address['postcode']) || empty($address['state'])) {
				return null;
			}
			
			return $address;
		}

		/**
		* Get congressional officials for ZIP
		*/
		private static function get_congressional_officials(object $zip_data): array {
			global $wpdb;
			
			$officials = [];
			
			// Get current US session
			$us_session = $wpdb->get_row(
				"SELECT * FROM {$wpdb->prefix}fi_sessions 
				WHERE gov = 'US' 
				ORDER BY date_start DESC 
				LIMIT 1"
			);
			
			if (!$us_session) {
				return $officials;
			}
			
			// Get congressional legislators
			$legislators = $wpdb->get_results($wpdb->prepare(
				"SELECT l.*, ls.*, s.name as session_name
				FROM {$wpdb->prefix}fi_legislators l
				INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
				INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
				WHERE ls.session_id = %d AND s.gov = 'US'
				ORDER BY ls.chamber, l.last_name",
				$us_session->id
			));
			
			foreach ($legislators as $legislator) {
				// Get score
				$score = $wpdb->get_row($wpdb->prepare(
					"SELECT ls.score FROM {$wpdb->prefix}fi_legislator_sessions ls 
					WHERE legislator_id = %d AND session_id = %d",
					$legislator->id, $us_session->id
				));
				
				$officials[] = [
					'id' => $legislator->id,
					'name' => $legislator->display_name,
					'slug' => $legislator->slug,
					'chamber' => $legislator->chamber,
					'district' => $legislator->district,
					'party' => $legislator->party,
					'gov' => 'US',
					'session' => $us_session->name,
					'score' => $score ? $score->score : null,
					'grade' => $score ? $score->grade : null,
					'image_url' => ImageHelper::get_legislator_image($legislator->id, $us_session->id, 'medium')
				];
			}
			
			return $officials;
		}

		/**
		* Get state officials for ZIP
		*/
		private static function get_state_officials(object $zip_data, string $state): array {
			global $wpdb;
			
			$officials = [];
			
			if (empty($state)) {
				return $officials;
			}
			
			// Get current state session
			$state_session = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fi_sessions 
				WHERE gov = %s 
				ORDER BY date_start DESC 
				LIMIT 1",
				strtoupper($state)
			));
			
			if (!$state_session) {
				return $officials;
			}
			
			// Get state legislators
			$legislators = $wpdb->get_results($wpdb->prepare(
				"SELECT l.*, ls.*, s.name as session_name
				FROM {$wpdb->prefix}fi_legislators l
				INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
				INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
				WHERE ls.session_id = %d AND s.gov = %s
				ORDER BY ls.chamber, l.last_name",
				$state_session->id, strtoupper($state)
			));
			
			foreach ($legislators as $legislator) {
				// Get score
				$score = $wpdb->get_row($wpdb->prepare(
					"SELECT ls.score FROM {$wpdb->prefix}fi_legislator_sessions ls 
					WHERE legislator_id = %d AND session_id = %d",
					$legislator->id, $state_session->id
				));
				
				$officials[] = [
					'id' => $legislator->id,
					'name' => $legislator->display_name,
					'slug' => $legislator->slug,
					'chamber' => $legislator->chamber,
					'district' => $legislator->district,
					'party' => $legislator->party,
					'gov' => strtoupper($state),
					'session' => $state_session->name,
					'score' => $score ? $score->score : null,
					'grade' => $score ? $score->grade : null,
					'image_url' => ImageHelper::get_legislator_image($legislator->id, $state_session->id, 'medium')
				];
			}
			
			return $officials;
		}

		/**
		* Save user address
		*/
		public static function save_user_address(int $user_id, array $address): bool {
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
				'billing_country'
			];
			
			foreach ($fields as $field) {
				$key = str_replace('billing_', '', $field ?? '');
				if (isset($address[$key])) {
					update_user_meta($user_id, $field, sanitize_text_field($address[$key]));
				}
			}
			
			return true;
		}

		/**
		* Get user lists
		*/
		public static function get_user_lists(int $user_id): array {
			return fi_lists_get_by_user($user_id);
		}

		/**
		* Save user list
		*/
		public static function save_user_list(int $user_id, string $name, array $legislators): ?int {
			global $wpdb;
			
		$result = $wpdb->insert(
			"{$wpdb->prefix}fi_user_lists",
			[
				'user_id' => $user_id,
				'name' => $name,
				'legislators' => json_encode($legislators)
			],
			['%d', '%s', '%s']
		);
			
			if ($result === false) {
				return null;
			}
			
			return $wpdb->insert_id;
		}

		/**
		* Add FI section to WooCommerce dashboard
		*/
		public static function add_fi_dashboard_section(): void {
			$user_id = get_current_user_id();
			$address = self::get_user_address($user_id);
			
			?>
			<div class="fi-member-dashboard">
				<h2>Your Elected Officials</h2>
				
				<?php if ($address): ?>
					<div class="fi-address-info">
						<p><strong>Address:</strong> <?php echo esc_html(self::format_address($address)); ?></p>
					</div>
					
					<div class="fi-officials-list">
						<?php
						$officials = self::get_elected_officials($user_id);
						if (!empty($officials)):
							foreach ($officials as $official):
								?>
								<div class="fi-official-card">
									<div class="fi-official-image">
										<?php if ($official['image_url']): ?>
											<img src="<?php echo esc_url($official['image_url']); ?>" alt="<?php echo esc_attr($official['name']); ?>">
										<?php endif; ?>
									</div>
									
									<div class="fi-official-info">
										<h3><?php echo esc_html($official['name']); ?></h3>
										<p class="fi-official-title">
											<?php echo esc_html(ucfirst($official['chamber'])); ?>
											<?php if ($official['district']): ?>
												- District <?php echo esc_html($official['district']); ?>
											<?php endif; ?>
										</p>
										<p class="fi-official-party"><?php echo esc_html($official['party']); ?></p>
										
										<?php if ($official['score'] !== null): ?>
											<div class="fi-official-score">
												<span class="fi-score-value"><?php echo esc_html($official['score']); ?>%</span>
												<span class="fi-score-grade">(<?php echo esc_html($official['grade']); ?>)</span>
											</div>
										<?php endif; ?>
									</div>
									
									<div class="fi-official-actions">
										<a href="<?php echo esc_url(URLs::get_legislator_url($official['id'])); ?>" class="button" target="_blank">
											View Profile
										</a>
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
											<?php
											$states = [
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
											foreach ($states as $code => $name): ?>
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
			jQuery(document).ready(function($) {
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
					
					var formData = $(this).serialize();
					
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'fi_save_user_address',
							data: formData,
							nonce: '<?php echo wp_create_nonce('fi_save_address'); ?>'
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
		* Add FI address form to WooCommerce account edit
		*/
		public static function add_fi_address_form(): void {
			$user_id = get_current_user_id();
			$address = self::get_user_address($user_id);
			
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
						<?php
						$states = [
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
						foreach ($states as $code => $name): ?>
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
		* Save FI address from WooCommerce form
		*/
		public static function save_fi_address(int $user_id): void {
			if (!class_exists('WooCommerce')) {
				return;
			}
			
			$fields = [
				'fi_address_1' => 'billing_address_1',
				'fi_city' => 'billing_city',
				'fi_state' => 'billing_state',
				'fi_postcode' => 'billing_postcode'
			];
			
			foreach ($fields as $form_field => $meta_field) {
				if (isset($_POST[$form_field])) {
					update_user_meta($user_id, $meta_field, sanitize_text_field($_POST[$form_field]));
				}
			}
		}

		/**
		* Format address for display
		*/
		private static function format_address(array $address): string {
			$parts = [];
			
			if (!empty($address['address_1'])) {
				$parts[] = $address['address_1'];
			}
			
			if (!empty($address['address_2'])) {
				$parts[] = $address['address_2'];
			}
			
			if (!empty($address['city'])) {
				$parts[] = $address['city'];
			}
			
			if (!empty($address['state'])) {
				$parts[] = $address['state'];
			}
			
			if (!empty($address['postcode'])) {
				$parts[] = $address['postcode'];
			}
			
			return implode(', ', $parts);
		}

		/**
		* AJAX handler for getting elected officials
		*/
		public static function ajax_get_elected_officials(): void {
			check_ajax_referer('fi_admin_nonce', 'nonce');
			
			$user_id = get_current_user_id();
			if (!$user_id) {
				wp_send_json_error('User not logged in');
			}
			
			$officials = self::get_elected_officials($user_id);
			
			wp_send_json_success($officials);
		}

		/**
		* AJAX handler for saving user address
		*/
		public static function ajax_save_user_address(): void {
			check_ajax_referer('fi_save_address', 'nonce');
			
			$user_id = get_current_user_id();
			if (!$user_id) {
				wp_send_json_error('User not logged in');
			}
			
			$address = [
				'first_name' => sanitize_text_field($_POST['data']['first_name'] ?? ''),
				'last_name' => sanitize_text_field($_POST['data']['last_name'] ?? ''),
				'address_1' => sanitize_text_field($_POST['data']['address_1'] ?? ''),
				'address_2' => sanitize_text_field($_POST['data']['address_2'] ?? ''),
				'city' => sanitize_text_field($_POST['data']['city'] ?? ''),
				'state' => sanitize_text_field($_POST['data']['state'] ?? ''),
				'postcode' => sanitize_text_field($_POST['data']['postcode'] ?? ''),
				'country' => 'US'
			];
			
			$result = self::save_user_address($user_id, $address);
			
			if ($result) {
				wp_send_json_success('Address saved successfully');
			} else {
				wp_send_json_error('Failed to save address');
			}
		}

		/**
		* AJAX handler for getting user lists
		*/
		public static function ajax_get_user_lists(): void {
			check_ajax_referer('fi_admin_nonce', 'nonce');
			
			$user_id = get_current_user_id();
			if (!$user_id) {
				wp_send_json_error('User not logged in');
			}
			
			$lists = self::get_user_lists($user_id);
			
			wp_send_json_success($lists);
		}

		/**
		* AJAX handler for saving user list
		*/
		public static function ajax_save_user_list(): void {
			check_ajax_referer('fi_admin_nonce', 'nonce');
			
			$user_id = get_current_user_id();
			if (!$user_id) {
				wp_send_json_error('User not logged in');
			}
			
			$name = sanitize_text_field($_POST['name'] ?? '');
			$legislators = array_map('absint', $_POST['legislators'] ?? []);
			
			if (empty($name) || empty($legislators)) {
				wp_send_json_error('Name and legislators are required');
			}
			
			$list_id = self::save_user_list($user_id, $name, $legislators);
			
			if ($list_id) {
				wp_send_json_success(['list_id' => $list_id]);
			} else {
				wp_send_json_error('Failed to save list');
			}
		}
	}
}


// Sam Mittelstaedt <smittelstaedt@jbs.org>
// Initialize MemberIntegration class at plugin load
namespace {

	add_action('plugins_loaded', [\FI\Core\MemberIntegration::class, 'init']);
}

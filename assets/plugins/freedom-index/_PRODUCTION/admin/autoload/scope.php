<?php
namespace FI\Admin {

	if (!defined('ABSPATH')) exit;

	/**
	 * Sticky Scope System for Freedom Index Admin
	 *
	 * Summary:
	 * - Persistent scope is stored per-user in usermeta (fi_admin_scope).
	 * - URL arg gov=XX is treated as a one-time setter for deep links and is then stripped via redirect,
	 *   so it won't keep overriding subsequent user changes.
	 */
	final class Scope {

		private static $current_scope = null;

		/**
		 * Get current admin scope
		 */
		public static function get_current(): array {
			if (self::$current_scope === null) {
				// URL-based gov takes priority — each gov has a unique URL for bfcache safety.
				if (isset($_GET['gov'])) {
					$g = strtoupper(sanitize_text_field((string) $_GET['gov']));
					if (in_array($g, self::get_available_governments(), true)) {
						self::$current_scope = ['gov' => $g];
						return self::$current_scope;
					}
				}

				$user_id = get_current_user_id();
				$scope = get_user_meta($user_id, 'fi_admin_scope', true);

				if (!$scope || !is_array($scope)) {
					$scope = self::get_default_scope();
				}

				// Migrate legacy saved scope (gov+session_id) to gov-only scope.
				if (isset($scope['session_id'])) {
					unset($scope['session_id']);
					self::set_current($scope);
				}

				self::$current_scope = $scope;
			}

			return self::$current_scope;
		}

		/**
		 * Set current admin scope
		 */
		public static function set_current(array $scope): bool {
			$user_id = get_current_user_id();
			$result = update_user_meta($user_id, 'fi_admin_scope', $scope);
			self::set_recent_govs($scope['gov'],$user_id);
			if ($result) {
				self::$current_scope = $scope;
			}

			return (bool) $result;
		}

		/**
		 * Add a government code to a user's list of recently accessed governments (max 5).
		 *
		 * @param string $gov
		 * @param int $user_id
		 */
		public static function set_recent_govs(string $gov, int $user_id): void {
			$recent_govs = get_user_meta($user_id, 'fi_admin_scope_recent', true);
			if (!is_array($recent_govs)) {
				$recent_govs = [];
			}
			//Skip if in array
			if (in_array($gov, $recent_govs)) {
				return;
			}
			// Remove if exists
			$recent_govs = array_values(array_diff($recent_govs, [$gov]));
			array_unshift($recent_govs, $gov);
			$recent_govs = array_slice($recent_govs, 0, 10);
			//Once we have the final array, sort it by the gov (abbreviation alphabetically)
			//usort($recent_govs, function($a, $b) {
			//	return strcmp($a, $b);
			//});
			update_user_meta($user_id, 'fi_admin_scope_recent', $recent_govs);
		}

		/**
		 * Get the recent governments for the current user (returns array)
		 *
		 * @return array
		 */
		public static function get_recent_govs(): array {
			$user_id = get_current_user_id();
			$recent_govs = get_user_meta($user_id, 'fi_admin_scope_recent', true);
			if (!is_array($recent_govs)) {
				return [];
			}
			return $recent_govs;
		}

		/**
		 * Display recent govs as a Bootstrap inline button group.
		 *
		 * @return string
		 */
		public static function display_recent_govs(): string {
			$recent_govs = self::get_recent_govs();

			if (empty($recent_govs)) {
				return '';
			}

			// Build a clean action URL (remove gov + scope flags).
			$action_url = self::get_current_admin_url_clean(['gov', 'scope_updated', 'scope_set']);

			$html = '<ul class="list-inline mb-0">';
			foreach ($recent_govs as $gov) {
				$html .= '<li class="list-inline-item mb-0"><a href="' . esc_url($action_url . '&gov=' . urlencode($gov)) . '" class="btn btn-sm p-1 btn-warning text-black fw-bold px-3 fs-7">' . esc_html($gov) . '</a></li>';
			}
			$html .= '</ul>';
			return $html;
		}

		/**
		 * Get default scope (US)
		 */
		public static function get_default_scope(): array {
			return [
				'gov' => 'US',
			];
		}

		/**
		 * Get current government
		 */
		public static function get_gov(): string {
			$scope = self::get_current();
			return $scope['gov'] ?? 'US';
		}

		/**
		 * Get current session ID (legacy placeholder)
		 */
		public static function get_session_id(): ?int {
			$scope = self::get_current();
			return $scope['session_id'] ?? null;
		}

		/**
		 * Get current session object
		 */
		public static function get_session(): ?object {
			$session_id = self::get_session_id();
			if (!$session_id) return null;

			global $wpdb;
			return $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fi_sessions WHERE id = %d",
				$session_id
			));
		}

		/**
		 * Render scope selector HTML
		 *
		 * IMPORTANT:
		 * - Force the form action to a "clean" URL with no gov=XX so posting never re-inherits the deep-link arg.
		 */
		public static function render_selector(?array $sessions = null): void {
			$current_scope = self::get_current();
			$governments   = self::get_available_governments();

			// Build a clean action URL (remove gov + scope flags).
			$action_url = self::get_current_admin_url_clean(['gov', 'scope_updated', 'scope_set']);

			?>
			<div class="container-fluid shadow-sm ps-0">
				<div class="fi-scope-selector">
					<div class="row">
						<div class="col-12 col-lg-6 col-xl-4 py-1">
							<form id="fi-scope-form" method="post" action="<?php echo esc_url($action_url); ?>">
								<?php wp_nonce_field('fi_update_scope', 'fi_scope_nonce'); ?>
								<label for="fi-gov" class="form-label fs-4 mb-0">Government:</label>
								<select id="fi-gov" name="gov" class="form-select form-select-sm fw-bold fs-5 lh-1" style="width: 220px; padding: 4px 8px 10px 8px; line-height: 1.4 !important;" onchange="this.form.submit()">
									<?php foreach ($governments as $gov): ?>
										<option value="<?php echo esc_attr($gov); ?>" <?php selected($current_scope['gov'], $gov); ?>>
											<?php echo esc_html(self::get_gov_display_name($gov)); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<noscript>
									<button type="submit" class="btn btn-success btn-sm fw-bold">Change Government</button>
								</noscript>
							</form>
						</div>
						<div class="d-none d-lg-inline col-lg-6 col-xl-8 pt-2">
							<?php echo self::display_recent_govs(); ?>
						</div>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Handle scope form submission
		 *
		 * IMPORTANT:
		 * - Always redirect to a clean URL after POST (PRG pattern).
		 */
		public static function handle_form_submission(): void {
			if (empty($_POST['fi_scope_nonce']) || !wp_verify_nonce($_POST['fi_scope_nonce'], 'fi_update_scope')) {
				return;
			}

			if (!current_user_can(FI_CAP_MANAGE)) {
				wp_die('Insufficient permissions');
			}

			$gov = strtoupper(sanitize_text_field($_POST['gov'] ?? ''));
			if ($gov === '') return;

			$allowed = self::get_available_governments();
			if (!in_array($gov, $allowed, true)) return;

			self::set_current(['gov' => $gov]);

			// Redirect to prevent resubmission (PRG pattern). Include gov so bfcache is keyed per-scope.
			$redirect_url = self::get_current_admin_url_clean(['gov', 'scope_updated', 'scope_set', '_']);
			$redirect_url = add_query_arg('gov', $gov, $redirect_url);

			wp_safe_redirect($redirect_url, 302);
			exit;
		}

		/**
		 * Handle scope updates from URL query string (e.g., admin.php?page=fi-legislators&gov=AR).
		 *
		 * Summary:
		 * - gov=XX is now permanent in the URL; no redirect needed.
		 * - Persists to usermeta so scope is remembered when navigating to URLs without gov.
		 */
		public static function handle_query_scope(): void {
			if (!current_user_can(FI_CAP_MANAGE)) return;

			// Never run on POST requests.
			if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;

			if (!isset($_GET['gov'])) return;

			$gov = strtoupper(sanitize_text_field((string) ($_GET['gov'] ?? '')));
			if ($gov === '') return;

			$allowed = self::get_available_governments();
			if (!in_array($gov, $allowed, true)) return;

			// Always persist to usermeta — ensures scope is up-to-date even after
			// bfcache restores a page or a browser navigates without the gov param.
			// No redirect — gov stays in the URL for bfcache safety.
			self::set_current(['gov' => $gov]);
		}

		/**
		 * Get government display name
		 */
		private static function get_gov_display_name(string $gov): string {
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

		public static function get_available_governments(): array {
			return [
				'US', 'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'HI',
				'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN',
				'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH',
				'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA',
				'WV', 'WI', 'WY'
			];
		}

		/**
		 * Apply scope filter to admin queries
		 */
		public static function apply_scope_filter(array $query_vars): array {
			$scope = self::get_current();
			if (!empty($scope['gov'])) {
				$query_vars['gov'] = $scope['gov'];
			}
			return $query_vars;
		}

		/**
		 * Get scope-aware admin URL
		 *
		 * NOTE:
		 * - If you keep this, it will intentionally append gov to links you build with this helper.
		 * - That’s fine for sharable deep links, but don’t use it everywhere by default.
		 */
		public static function get_admin_url(string $page, array $args = []): string {
			$scope = self::get_current();
			$args['gov'] = $scope['gov'];
			return add_query_arg($args, admin_url("admin.php?page={$page}"));
		}

		/**
		 * Check if scope is valid
		 */
		public static function is_valid_scope(array $scope): bool {
			return !empty($scope['gov']);
		}

		/**
		 * Reset scope to default
		 */
		public static function reset_to_default(): bool {
			return self::set_current(self::get_default_scope());
		}

		/**
		 * Build the current admin URL and remove specific query args.
		 *
		 * IMPORTANT:
		 * - Used by both the form action and redirects to guarantee we strip gov from the URL.
		 */
	private static function get_current_admin_url_clean(array $remove_keys = []): string {
		// Base: admin.php + preserve "page" if present.
		$base = admin_url('admin.php');

		if (isset($_GET['page'])) {
			$base = add_query_arg('page', sanitize_text_field((string) $_GET['page']), $base);
		}

		// Preserve other common admin args if you use them (optional):
		// if (isset($_GET['post_type'])) $base = add_query_arg('post_type', sanitize_text_field((string) $_GET['post_type']), $base);

		// Now remove any keys we explicitly want stripped.
		if (!empty($remove_keys)) {
			$base = remove_query_arg($remove_keys, $base);
		}

		return $base;
	}
	}

	// Hook into WordPress.
	add_action('admin_init', [Scope::class, 'handle_form_submission'], 0);
	add_action('admin_init', [Scope::class, 'handle_query_scope'], 1);

	// Prevent browser/proxy caching on FI admin pages so scope switches and nonces are always fresh.
	// Priority -10 ensures these fire on POST requests (save handlers) before they exit via wp_safe_redirect(),
	// so the 302 redirect response carries no-store. Without no-store on the redirect, some browsers
	// treat the 302 as cacheable and reuse the destination URL from a prior visit.
	add_action('admin_init', static function (): void {
		$page = sanitize_key((string) ($_GET['page'] ?? ''));
		if (str_starts_with($page, 'fi-')) {
			header('Cache-Control: no-cache, no-store, must-revalidate');
			header('Pragma: no-cache');
			header('Expires: 0');
		}
	}, -10);

	// WordPress's nocache_headers() (called in admin-header.php during page render) overwrites the
	// Cache-Control header above, dropping `no-store`. Re-assert at admin_head, which fires after
	// admin-header.php but before any body output, so headers are still modifiable.
	add_action('admin_head', static function (): void {
		$page = sanitize_key((string) ($_GET['page'] ?? ''));
		if (str_starts_with($page, 'fi-') && !headers_sent()) {
			header('Cache-Control: no-cache, no-store, must-revalidate');
		}
	}, 1);

}

// Global helper functions
namespace {

	function fi_scope_render_selector(?array $sessions = null): void {
		\FI\Admin\Scope::render_selector($sessions);
	}

	function fi_scope_get_current(): array {
		return \FI\Admin\Scope::get_current();
	}

	function fi_scope_set_current(array $scope): bool {
		return \FI\Admin\Scope::set_current($scope);
	}

	function fi_scope_get_session() {
		return \FI\Admin\Scope::get_session();
	}

	function fi_scope_reset(): bool {
		return \FI\Admin\Scope::reset_to_default();
	}

	function fi_scope_content_check(string $scope_gov, string $content_gov, string $content_type): void {
		if($scope_gov != $content_gov) {
			echo '<div class="container-fluid pt-3"><div class="card bg-white border border-2 border-danger rounded-3 text-danger p-2 fw-bold text-center fs-3">';
			echo 'ATTENTION: This is a '.$content_gov . ' '.ucfirst($content_type).'. CHANGE GOV before editing.';
			echo '</div></div>';
		}
	}

	/**
	 * On fi-* admin pages: rewrite admin menu links to include gov, and update the current URL
	 * via history.replaceState so bfcache keys on a per-gov URL.
	 *
	 * Summary:
	 * - Gov is now permanent in the URL (e.g. ?page=fi-legislators&gov=US).
	 * - Each scope+page combo is a unique URL, so bfcache correctly serves scoped content.
	 * - Menu links are rewritten in JS because WP renders them without knowledge of our scope.
	 * - history.replaceState handles the first load when gov isn't yet in the URL (e.g., bookmarks).
	 */
	add_action('admin_footer', static function (): void {
		if (defined('DOING_AJAX') && DOING_AJAX) {
			return;
		}
		$page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
		if ($page === '' || strpos($page, 'fi-') !== 0) {
			return;
		}
		$gov = \FI\Admin\Scope::get_gov();
		?>
		<script>
		(function(gov) {
			// Rewrite FI admin menu links to include current gov.
			document.querySelectorAll('#adminmenu a[href*="page=fi-"]').forEach(function(a) {
				try {
					var url = new URL(a.href);
					url.searchParams.set('gov', gov);
					a.href = url.toString();
				} catch(e) {}
			});
			// Update current URL so bfcache keys on the correct gov (handles bookmarks / direct URLs).
			if (history && history.replaceState) {
				try {
					var cur = new URL(window.location.href);
					if (!cur.searchParams.has('gov')) {
						cur.searchParams.set('gov', gov);
						history.replaceState(null, '', cur.toString());
					}
				} catch(e) {}
			}
		})(<?php echo json_encode(\FI\Admin\Scope::get_gov()); ?>);
		</script>
		<?php
	}, 1);

}
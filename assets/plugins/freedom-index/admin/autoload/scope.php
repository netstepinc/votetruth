<?php
/*
 * Freedom Index Admin Sticky Scope System
 *
 * Straight function version of the former FIAdmin\Scope class file.
 *
 * Summary:
 * - Stores the current admin government scope per user in usermeta: fi_admin_scope.
 * - Stores recent governments per user in usermeta: fi_admin_scope_recent.
 * - Handles scope selector rendering and scope switching via admin-post.
 * Refactored the admin sticky scope system into straight functions.
Key adjustments:
	Removed the FIAdmin\Scope class/namespace wrapper.
Preserved existing global helper names:
	fi_scope_render_selector()
	fi_scope_get_current()
	fi_scope_set_current()
	fi_scope_get_session()
	fi_scope_reset()
	fi_scope_content_check()
Added helpers:
	fi_scope_cache()
	fi_scope_normalize_gov()
	fi_scope_get_default()
	fi_scope_get_available_governments()
	fi_scope_is_valid_gov()
	fi_scope_set_recent_govs()
	fi_scope_get_recent_govs()
	fi_scope_get_gov_display_name()
	fi_scope_display_recent_govs()
	fi_scope_get_gov()
	fi_scope_get_session_id()
	fi_scope_handle_form_submission()
	fi_scope_apply_filter()
	fi_scope_is_valid_scope()
	fi_scope_get_current_admin_url_clean()
	fi_scope_get_switch_url()
	fi_scope_handle_switch_action()
	fi_scope_get_redirect_url()
	fi_scope_send_admin_no_cache_headers()
Tuning:
	Replaced the hardcoded state display array with fi_gov_name() / fi_govs() when available, with fallback to the hardcoded government list only if needed.
	Kept US display label as Congress.
Removed the unused session-scope behavior by making:
	fi_scope_get_session_id()
	fi_scope_get_session()
	return null. The stored scope is now clearly government-only.
Sanitized and validated government codes consistently.
	Fixed recent-gov handling so selecting an already-recent gov moves it to the front instead of returning early.
	Kept PRG redirect behavior.
	Kept no-cache headers for fi-* admin screens.
Added compatibility aliases:
	fi_scope_get_available_govs()
	fi_scope_get_display_name()
 */

if (!defined('ABSPATH')) exit;

/**
 * Get request-level current scope cache.
 *
 * @param array|null $scope Scope to set.
 * @param bool $set Whether to set cache.
 * @return array|null
 */
function fi_scope_cache(?array $scope = null, bool $set = false): ?array {
	static $current_scope = null;

	if ($set) {
		$current_scope = $scope;
	}

	return $current_scope;
}

/**
 * Normalize a government code.
 *
 * @param string $gov Government code.
 * @return string Normalized code.
 */
function fi_scope_normalize_gov(string $gov): string {
	return strtoupper(sanitize_key($gov));
}

/**
 * Get default admin scope.
 *
 * @return array Scope.
 */
function fi_scope_get_default(): array {
	return [
		'gov' => 'US',
	];
}

/**
 * Get available government codes.
 *
 * @return array Government codes.
 */
function fi_scope_get_available_governments(): array {
	if (function_exists('fi_govs')) {
		$govs = fi_govs();
		if (is_array($govs) && !empty($govs)) {
			return array_values(array_unique(array_map('strtoupper', array_map('sanitize_key', array_keys($govs)))));
		}
	}

	if (defined('FI_GOVERNMENTS') && is_array(FI_GOVERNMENTS)) {
		return array_values(array_unique(array_map('strtoupper', array_map('sanitize_key', array_keys(FI_GOVERNMENTS)))));
	}

	return [
		'US', 'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'HI',
		'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN',
		'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH',
		'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA',
		'WV', 'WI', 'WY',
	];
}

/**
 * Check whether a government code is available for admin scope.
 *
 * @param string $gov Government code.
 * @return bool
 */
function fi_scope_is_valid_gov(string $gov): bool {
	$gov = fi_scope_normalize_gov($gov);
	return $gov !== '' && in_array($gov, fi_scope_get_available_governments(), true);
}

/**
 * Get current admin scope.
 *
 * @return array Scope.
 */
function fi_scope_get_current(): array {
	$cached = fi_scope_cache();
	if ($cached !== null) {
		return $cached;
	}

	$user_id = get_current_user_id();
	$scope = $user_id > 0 ? get_user_meta($user_id, 'fi_admin_scope', true) : [];

	if (!is_array($scope)) {
		$scope = [];
	}

	$gov = fi_scope_normalize_gov((string) ($scope['gov'] ?? ''));
	if (!fi_scope_is_valid_gov($gov)) {
		$scope = fi_scope_get_default();
	} else {
		$scope = ['gov' => $gov];
	}

	fi_scope_cache($scope, true);

	return $scope;
}

/**
 * Set current admin scope for current user.
 *
 * @param array $scope Scope data.
 * @return bool True on success.
 */
function fi_scope_set_current(array $scope): bool {
	$user_id = get_current_user_id();
	if ($user_id <= 0) {
		return false;
	}

	$gov = fi_scope_normalize_gov((string) ($scope['gov'] ?? ''));
	if (!fi_scope_is_valid_gov($gov)) {
		return false;
	}

	$new_scope = ['gov' => $gov];
	$result = update_user_meta($user_id, 'fi_admin_scope', $new_scope);

	fi_scope_set_recent_govs($gov, $user_id);
	fi_scope_cache($new_scope, true);

	return $result !== false;
}

/**
 * Add government code to a user's recent governments list.
 *
 * @param string $gov Government code.
 * @param int|null $user_id User ID. Defaults to current user.
 * @return void
 */
function fi_scope_set_recent_govs(string $gov, ?int $user_id = null): void {
	$user_id = $user_id ?: get_current_user_id();
	if ($user_id <= 0) {
		return;
	}

	$gov = fi_scope_normalize_gov($gov);
	if (!fi_scope_is_valid_gov($gov)) {
		return;
	}

	$recent_govs = get_user_meta($user_id, 'fi_admin_scope_recent', true);
	if (!is_array($recent_govs)) {
		$recent_govs = [];
	}

	$recent_govs = array_values(array_filter(array_map('fi_scope_normalize_gov', $recent_govs)));
	$recent_govs = array_values(array_diff($recent_govs, [$gov]));
	array_unshift($recent_govs, $gov);
	$recent_govs = array_slice(array_values(array_unique($recent_govs)), 0, 10);

	update_user_meta($user_id, 'fi_admin_scope_recent', $recent_govs);
}

/**
 * Get recent governments for current user.
 *
 * @return array Government codes.
 */
function fi_scope_get_recent_govs(): array {
	$user_id = get_current_user_id();
	if ($user_id <= 0) {
		return [];
	}

	$recent_govs = get_user_meta($user_id, 'fi_admin_scope_recent', true);
	if (!is_array($recent_govs)) {
		return [];
	}

	$available = fi_scope_get_available_governments();
	$recent_govs = array_values(array_filter(array_map('fi_scope_normalize_gov', $recent_govs), static function($gov) use ($available) {
		return in_array($gov, $available, true);
	}));

	return array_values(array_unique($recent_govs));
}

/**
 * Get government display name.
 *
 * @param string $gov Government code.
 * @return string Display name.
 */
function fi_scope_get_gov_display_name(string $gov): string {
	$gov = fi_scope_normalize_gov($gov);

	if ($gov === 'US') {
		return 'Congress';
	}

	if (function_exists('fi_gov_name')) {
		$name = fi_gov_name($gov);
		if ($name) {
			return (string) $name;
		}
	}

	if (function_exists('fi_govs')) {
		$govs = fi_govs();
		if (isset($govs[$gov])) {
			return (string) $govs[$gov];
		}
	}

	return $gov;
}

/**
 * Display recent govs as a Bootstrap inline button group.
 *
 * @return string HTML.
 */
function fi_scope_display_recent_govs(): string {
	$recent_govs = fi_scope_get_recent_govs();
	if (empty($recent_govs)) {
		return '';
	}

	$html = '<ul class="list-inline mb-0">';
	foreach ($recent_govs as $gov) {
		$html .= '<li class="list-inline-item mb-0"><a href="' . esc_url(fi_scope_get_switch_url($gov)) . '" class="btn btn-sm p-1 btn-warning text-black fw-bold px-3 fs-7">' . esc_html($gov) . '</a></li>';
	}
	$html .= '</ul>';

	return $html;
}

/**
 * Get current government code from admin scope.
 *
 * @return string Government code.
 */
function fi_scope_get_gov(): string {
	$scope = fi_scope_get_current();
	return $scope['gov'] ?? 'US';
}

/**
 * Legacy placeholder: scope no longer stores session ID.
 *
 * @return int|null
 */
function fi_scope_get_session_id(): ?int {
	return null;
}

/**
 * Legacy placeholder: scope no longer stores session object.
 *
 * @return null
 */
function fi_scope_get_session(): null {
	return null;
}

/**
 * Render admin scope selector HTML.
 *
 * @param array|null $sessions Unused legacy parameter.
 * @return void
 */
function fi_scope_render_selector(?array $sessions = null): void {
	$current_scope = fi_scope_get_current();
	$governments = fi_scope_get_available_governments();
	$action_url = fi_scope_get_switch_url();
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
								<option value="<?php echo esc_attr($gov); ?>" <?php selected($current_scope['gov'] ?? 'US', $gov); ?>>
									<?php echo esc_html(fi_scope_get_gov_display_name($gov)); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<noscript>
							<button type="submit" class="btn btn-success btn-sm fw-bold">Change Government</button>
						</noscript>
					</form>
				</div>
				<div class="d-none d-lg-inline col-lg-6 col-xl-8 pt-2">
					<?php echo fi_scope_display_recent_govs(); ?>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Handle scope form submission.
 *
 * Uses PRG pattern.
 *
 * @return void
 */
function fi_scope_handle_form_submission(): void {
	if (empty($_POST['fi_scope_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fi_scope_nonce'])), 'fi_update_scope')) {
		return;
	}

	$cap = defined('FI_CAP_MANAGE') ? FI_CAP_MANAGE : 'manage_options';
	if (!current_user_can($cap)) {
		wp_die(esc_html__('Insufficient permissions', 'freedom-index'));
	}

	$gov = fi_scope_normalize_gov((string) ($_POST['gov'] ?? ''));
	if ($gov === '' || !fi_scope_is_valid_gov($gov)) {
		return;
	}

	fi_scope_set_current(['gov' => $gov]);

	wp_safe_redirect(fi_scope_get_redirect_url(), 302);
	exit;
}

/**
 * Apply scope filter to admin query vars.
 *
 * @param array $query_vars Query vars.
 * @return array Query vars.
 */
function fi_scope_apply_filter(array $query_vars): array {
	$scope = fi_scope_get_current();
	if (!empty($scope['gov'])) {
		$query_vars['gov'] = $scope['gov'];
	}
	return $query_vars;
}

/**
 * Check if a scope array is valid.
 *
 * @param array $scope Scope.
 * @return bool
 */
function fi_scope_is_valid_scope(array $scope): bool {
	return !empty($scope['gov']) && fi_scope_is_valid_gov((string) $scope['gov']);
}

/**
 * Reset scope to default.
 *
 * @return bool
 */
function fi_scope_reset(): bool {
	return fi_scope_set_current(fi_scope_get_default());
}

/**
 * Build current admin URL and remove specified query args.
 *
 * @param array $remove_keys Query keys to remove.
 * @return string URL.
 */
function fi_scope_get_current_admin_url_clean(array $remove_keys = []): string {
	$base = admin_url('admin.php');

	if (isset($_GET['page'])) {
		$base = add_query_arg('page', sanitize_key((string) $_GET['page']), $base);
	}

	if (!empty($remove_keys)) {
		$base = remove_query_arg($remove_keys, $base);
	}

	return $base;
}

/**
 * Get scope switch URL.
 *
 * @param string $gov Optional government code.
 * @return string URL.
 */
function fi_scope_get_switch_url(string $gov = ''): string {
	$args = ['action' => 'fi_switch_scope'];

	$gov = fi_scope_normalize_gov($gov);
	if ($gov !== '') {
		$args['gov'] = $gov;
	}

	$args['redirect_to'] = rawurlencode(fi_scope_get_redirect_url(false));

	return wp_nonce_url(add_query_arg($args, admin_url('admin-post.php')), 'fi_switch_scope', 'fi_scope_nonce');
}

/**
 * Handle admin-post scope switch action.
 *
 * @return void
 */
function fi_scope_handle_switch_action(): void {
	$cap = defined('FI_CAP_MANAGE') ? FI_CAP_MANAGE : 'manage_options';
	if (!current_user_can($cap)) {
		wp_die(esc_html__('Insufficient permissions', 'freedom-index'));
	}

	check_admin_referer('fi_switch_scope', 'fi_scope_nonce');

	$gov = fi_scope_normalize_gov((string) ($_REQUEST['gov'] ?? ''));
	if (!fi_scope_is_valid_gov($gov)) {
		wp_die(esc_html__('Invalid government', 'freedom-index'));
	}

	fi_scope_set_current(['gov' => $gov]);

	wp_safe_redirect(fi_scope_get_redirect_url(true));
	exit;
}

/**
 * Get redirect URL after scope switch.
 *
 * @param bool $from_request Whether to read redirect_to from request.
 * @return string URL.
 */
function fi_scope_get_redirect_url(bool $from_request = true): string {
	$redirect = '';

	if ($from_request && isset($_REQUEST['redirect_to']) && is_string($_REQUEST['redirect_to'])) {
		$redirect = wp_validate_redirect(rawurldecode(wp_unslash($_REQUEST['redirect_to'])), '');
	}

	if ($redirect === '') {
		$redirect = fi_scope_get_current_admin_url_clean(['gov', 'scope_updated', 'scope_set', '_']);
	}

	return remove_query_arg(['gov', 'scope_updated', 'scope_set', '_'], $redirect);
}

/**
 * Render warning when content government differs from current scope.
 *
 * @param string $scope_gov Current scope government.
 * @param string $content_gov Content government.
 * @param string $content_type Content type label.
 * @return void
 */
function fi_scope_content_check(string $scope_gov, string $content_gov, string $content_type): void {
	$scope_gov = fi_scope_normalize_gov($scope_gov);
	$content_gov = fi_scope_normalize_gov($content_gov);
	$content_type = sanitize_text_field($content_type);

	if ($scope_gov === $content_gov) {
		return;
	}

	echo '<div class="container-fluid pt-3"><div class="card bg-white border border-2 border-danger rounded-3 text-danger p-2 fw-bold text-center fs-3">';
	echo esc_html('ATTENTION: This is a ' . $content_gov . ' ' . ucfirst($content_type) . '. CHANGE GOV before editing.');
	echo '</div></div>';
}

add_action('admin_init', 'fi_scope_handle_form_submission', 0);
add_action('admin_post_fi_switch_scope', 'fi_scope_handle_switch_action');

// Prevent browser/proxy caching on FI admin pages so saves and nonces are always fresh.
// Priority -10 ensures this fires before save handlers exit via wp_safe_redirect(),
// so the 302 redirect response itself carries no-store.
add_action('admin_init', static function (): void {
	$page = sanitize_key((string) ($_GET['page'] ?? ''));
	if (str_starts_with($page, 'fi-')) {
		header('Cache-Control: no-cache, no-store, must-revalidate');
		header('Pragma: no-cache');
		header('Expires: 0');
	}
}, -10);

// WordPress's nocache_headers() (called in admin-header.php during render) overwrites the
// Cache-Control header above, dropping no-store. Re-assert at admin_head which fires after
// admin-header.php but before any body output, so headers are still modifiable.
add_action('admin_head', static function (): void {
	$page = sanitize_key((string) ($_GET['page'] ?? ''));
	if (str_starts_with($page, 'fi-') && !headers_sent()) {
		header('Cache-Control: no-cache, no-store, must-revalidate');
	}
}, 1);

<?php
/*
 * Freedom Index Admin Log Viewer
 *
 * Straight function version of the former FIAdmin\Logs class file.
 *
 * Handles:
 * - Admin submenu registration.
 * - Clear-log action.
 * - Admin log viewer page rendering.
 * Refactored the admin log viewer into straight functions.
Key adjustments:
	Removed the FIAdmin\Logs class/namespace wrapper.
Replaced:
	\FICore\Logger::clear_log()
	\FICore\Logger::get_log_file()
	\FICore\Logger::get_log_size()
	\FICore\Logger::get_logs()

	with the helpers we already refactored:

	fi_log_clear()
	fi_log_get_file()
	fi_log_get_size()
	fi_log_get()
Added:
	fi_admin_logs_init()
	fi_admin_logs_add_menu_page()
	fi_admin_logs_handle_actions()
	fi_admin_logs_line_color()
	fi_admin_logs_render_page()
Changed wp_redirect() to wp_safe_redirect().
Replaced the raw submit button with WordPress submit_button().
*/

if (!defined('ABSPATH')) exit;

/**
 * Initialize the FI admin log viewer.
 *
 * @return void
 */
function fi_admin_logs_init(): void {
	add_action('admin_menu', 'fi_admin_logs_add_menu_page', 20);
	add_action('admin_init', 'fi_admin_logs_handle_actions');
}
add_action('plugins_loaded', 'fi_admin_logs_init');

/**
 * Add Logs submenu page.
 *
 * @return void
 */
function fi_admin_logs_add_menu_page(): void {
	add_submenu_page(
		'fi-dashboard',
		'Logs',
		'Logs',
		'manage_options',
		'fi-logs',
		'fi_admin_logs_render_page'
	);
}

/**
 * Handle log viewer form actions.
 *
 * @return void
 */
function fi_admin_logs_handle_actions(): void {
	if (!is_admin()) {
		return;
	}

	if (($_GET['page'] ?? '') !== 'fi-logs') {
		return;
	}

	if (!current_user_can('manage_options')) {
		return;
	}

	if (isset($_POST['fi_clear_log'])) {
		check_admin_referer('fi_clear_log');

		if (function_exists('fi_log_clear')) {
			fi_log_clear();
		}

		wp_safe_redirect(add_query_arg([
			'page'    => 'fi-logs',
			'cleared' => '1',
		], admin_url('admin.php')));
		exit;
	}
}

/**
 * Get display color for a log line based on level marker.
 *
 * @param string $line Log line.
 * @return string Hex color.
 */
function fi_admin_logs_line_color(string $line): string {
	if (stripos($line, '[ERROR]') !== false) {
		return '#cc0000';
	}

	if (stripos($line, '[WARNING]') !== false) {
		return '#c03f02';
	}

	if (stripos($line, '[DEBUG]') !== false) {
		return '#005092';
	}

	if (stripos($line, '[INFO]') !== false) {
		return '#1a7000';
	}

	return '#444444';
}

/**
 * Render admin log viewer page.
 *
 * @return void
 */
function fi_admin_logs_render_page(): void {
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have permission to access this page.', 'freedom-index'));
	}

	$log_file = function_exists('fi_log_get_file') ? fi_log_get_file() : '';
	$log_size = function_exists('fi_log_get_size') ? fi_log_get_size() : 0;
	$logs = function_exists('fi_log_get') ? fi_log_get(1000) : [];
	$logs = is_array($logs) ? $logs : [];
	?>
	<style>
		.fi-logs-page .form-table th,
		.fi-logs-page .form-table td {
			padding: 4px 10px;
		}
		.fi-logs-page .form-table th {
			padding-right: 15px;
		}
		.fi-log-output {
			background: rgba(243, 243, 243, 0.9);
			color: #000;
			padding: 15px;
			border-radius: 0;
			font-family: Consolas, Monaco, 'Courier New', monospace;
			font-size: 12px;
			max-height: 600px;
			overflow: auto;
			white-space: nowrap;
		}
	</style>

	<div class="wrap fi-logs-page">
		<h1><?php echo esc_html__('Freedom Index Logs', 'freedom-index'); ?></h1>

		<?php if (isset($_GET['cleared'])): ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html__('Log file cleared successfully.', 'freedom-index'); ?></p>
			</div>
		<?php endif; ?>

		<div class="card mb-4" style="max-width: 100%;">
			<div class="card-body">
				<h2 class="title"><?php echo esc_html__('Log File Information', 'freedom-index'); ?></h2>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__('Log File Path', 'freedom-index'); ?></th>
						<td><code><?php echo esc_html($log_file); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__('File Size', 'freedom-index'); ?></th>
						<td><?php echo esc_html(function_exists('fi_format_size') ? fi_format_size((int) $log_size) : (string) $log_size . ' bytes'); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__('Log Entries', 'freedom-index'); ?></th>
						<td><?php echo esc_html((string) count($logs)); ?> <?php echo esc_html__('lines', 'freedom-index'); ?></td>
					</tr>
				</table>

				<form method="post" action="" style="margin-top: 20px;">
					<?php wp_nonce_field('fi_clear_log'); ?>
					<?php submit_button(__('Clear Log File', 'freedom-index'), 'secondary', 'fi_clear_log', false); ?>
				</form>
			</div>
		</div>

		<div class="card" style="max-width: 100%; margin-top: 20px;">
			<div class="card-body">
				<h2 class="title"><?php echo esc_html__('Log Entries', 'freedom-index'); ?></h2>

				<div class="fi-log-output">
					<?php if (empty($logs)): ?>
						<p style="color: #444;"><?php echo esc_html__('No log entries found.', 'freedom-index'); ?></p>
					<?php else: ?>
						<?php foreach (array_reverse($logs) as $line): ?>
							<?php $color = fi_admin_logs_line_color((string) $line); ?>
							<div style="color: <?php echo esc_attr($color); ?>;">
								<?php echo esc_html((string) $line); ?>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	<?php
}
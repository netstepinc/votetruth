<?php
namespace FI\Admin {

	if (!defined('ABSPATH')) exit;

	/**
	 * Freedom Index Log Viewer
	 * Admin page for viewing and managing Freedom Index logs
	 * 
	 * @author Sam Mittelstaedt <smittelstaedt@jbs.org>
	 */
	class Logs {
		
		/**
		 * Initialize log viewer
		 */
		public static function init() {
			add_action('admin_menu', [self::class, 'add_menu_page'], 20);
			add_action('admin_init', [self::class, 'handle_actions']);
		}
		
		/**
		 * Add admin menu page
		 */
		public static function add_menu_page() {
			add_submenu_page(
				'fi-dashboard',
				'Logs',
				'Logs',
				'manage_options',
				'fi-logs',
				[self::class, 'render_page']
			);
		}
		
		/**
		 * Handle form actions (clear log)
		 */
		public static function handle_actions() {
			if (!isset($_GET['page']) || $_GET['page'] !== 'fi-logs') {
				return;
			}
			
			if (!current_user_can('manage_options')) {
				return;
			}
			
			if (isset($_POST['fi_clear_log']) && check_admin_referer('fi_clear_log')) {
				\FI\Core\Logger::clear_log();
				wp_redirect(add_query_arg(['page' => 'fi-logs', 'cleared' => '1'], admin_url('admin.php')));
				exit;
			}
		}
		
		/**
		 * Render log viewer page
		 */
		public static function render_page() {
			if (!current_user_can('manage_options')) {
				wp_die('You do not have permission to access this page.');
			}
			
			$log_file = \FI\Core\Logger::get_log_file();
			$log_size = \FI\Core\Logger::get_log_size();
			$logs = \FI\Core\Logger::get_logs(1000); // Get last 1000 lines
			
			?>
			<style>
				.fi-logs-page .form-table th,
				.fi-logs-page .form-table td {
					padding: 4px 10px;
				}
				.fi-logs-page .form-table th {
					padding-right: 15px;
				}
			</style>
			<div class="wrap fi-logs-page">
				<h1>Freedom Index Logs</h1>
				
				<?php if (isset($_GET['cleared'])): ?>
				<div class="notice notice-success is-dismissible">
					<p>Log file cleared successfully.</p>
				</div>
				<?php endif; ?>
				
				<div class="card mb-4" style="max-width: 100%;">
					<div class="card-body">
						<h2 class="title">Log File Information</h2>
						<table class="form-table">
							<tr>
								<th scope="row">Log File Path</th>
								<td><code><?php echo esc_html($log_file); ?></code></td>
							</tr>
							<tr>
								<th scope="row">File Size</th>
								<td><?php echo esc_html(fi_format_size($log_size)); ?></td>
							</tr>
							<tr>
								<th scope="row">Log Entries</th>
								<td><?php echo esc_html(count($logs)); ?> lines</td>
							</tr>
						</table>
						<form method="post" action="" style="margin-top: 20px;">
							<?php wp_nonce_field('fi_clear_log'); ?>
							<input type="submit" name="fi_clear_log" class="btn btn-secondary" value="Clear Log File">
						</form>
					</div>
				</div>
				
				<div class="card" style="max-width: 100%; margin-top: 20px;">
					<div class="card-body">
						<h2 class="title">Log Entries</h2>
						<div style="background:rgba(243, 243, 243, 0.9); color: #000; padding: 15px; border-radius: 0px; font-family: 'Courier New', monospace; font-size: 12px; max-height: 600px; overflow: auto; white-space:nowrap;">
							<?php if (empty($logs)): ?>
								<p style="color: #444;">No log entries found.</p>
							<?php else: ?>
								<?php foreach (array_reverse($logs) as $line): ?>
									<?php
									// Color code by log level
									$color = '#444444';
									if (stripos($line, '[ERROR]') !== false) {
										$color = '#cc0000';
									} elseif (stripos($line, '[WARNING]') !== false) {
										$color = '#c03f02';
									} elseif (stripos($line, '[DEBUG]') !== false) {
										$color = '#005092';
									} elseif (stripos($line, '[INFO]') !== false) {
										$color = '#1a7000';
									}
									?>
									<div style="color: <?php echo esc_attr($color); ?>;">
										<?php echo esc_html($line); ?>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
	}
}

namespace {
	// Initialize the logs viewer
	\FI\Admin\Logs::init();


}

<?php if (!defined('ABSPATH')) {exit;}

$scope = fi_scope_get_current();
$stats = fi_dashboard_get_stats($scope['gov'] ?? 'US', $scope['session_id'] ?? null);
?>
<?php fi_scope_render_selector(); ?>
<div class="wrap">
	<h1>Freedom Index Dashboard</h1>

	<?php if (!empty($_GET['fi_cache_cleared'])): ?>
		<?php
		$cache_type = sanitize_key($_GET['fi_cache_type'] ?? '');
		$cache_files = (int) ($_GET['fi_cache_files'] ?? 0);
		$cache_errors = (int) ($_GET['fi_cache_errors'] ?? 0);
		$cache_skipped = !empty($_GET['fi_cache_skipped']);

		if ($cache_skipped) {
			echo '<div class="notice notice-warning is-dismissible"><p>Cache clear skipped for type: <code>' . esc_html($cache_type) . '</code>.</p></div>';
		} else {
			echo '<div class="notice notice-success is-dismissible"><p>Cache cleared: <code>' . esc_html($cache_type) . '</code> — removed ' . esc_html((string) $cache_files) . ' item(s)';
			if ($cache_errors > 0) {
				echo ' with ' . esc_html((string) $cache_errors) . ' error(s)';
			}
			echo '.</p></div>';
		}
		?>
	<?php endif; ?>

	<div class="fi-dashboard-stats">
		<div class="fi-stat-card">
			<h3>Legislators</h3>
			<div class="fi-stat-number"><?php echo esc_html($stats['legislators']); ?></div>
		</div>
		
		<div class="fi-stat-card">
			<h3>Sessions</h3>
			<div class="fi-stat-number"><?php echo esc_html($stats['sessions']); ?></div>
		</div>
		
		<div class="fi-stat-card">
			<h3>Votes</h3>
			<div class="fi-stat-number"><?php echo esc_html($stats['votes']); ?></div>
		</div>
		
		<div class="fi-stat-card">
			<h3>Roll Calls</h3>
			<div class="fi-stat-number"><?php echo esc_html($stats['roll_calls']); ?></div>
		</div>
		
		<div class="fi-stat-card">
			<h3>Scores</h3>
			<div class="fi-stat-number"><?php echo esc_html($stats['scores']); ?></div>
		</div>
		
		<div class="fi-stat-card">
			<h3>Reports</h3>
			<div class="fi-stat-number"><?php echo esc_html($stats['reports']); ?></div>
		</div>
	</div>
	
	<div class="fi-dashboard-actions">
		<a href="<?php echo esc_url(fi_admin_url('fi-legislators')); ?>" class="btn btn-primary">
			Manage Legislators
		</a>
		<a href="<?php echo esc_url(fi_admin_url('fi-votes')); ?>" class="btn btn-primary">
			Manage Votes
		</a>
		<a href="<?php echo esc_url(fi_admin_url('fi-reports')); ?>" class="btn btn-primary">
			Manage Reports
		</a>
		<a href="<?php echo esc_url(fi_admin_url('fi-legiscan-import')); ?>" class="btn btn-secondary">
			Import Legiscan Data
		</a>
	</div>

	<hr>

	<div class="card shadow-sm" style="max-width: 900px;">
		<div class="card-header bg-white border-0">
			<h2 class="h5 mb-0">Cache Tools</h2>
		</div>
		<div class="card-body">
			<p class="text-muted mb-3">Clears file caches under <code><?php echo esc_html(FI_DIR_CACHE); ?></code>. Note: <strong>Legiscan cache is never cleared in bulk</strong> here.</p>

			<div class="d-flex flex-wrap gap-2">
				<?php
				$buttons = [
					'all' => 'Clear All (Safe)',
					'findmy' => 'Clear Find My',
					'legislators' => 'Clear Legislators',
					'search' => 'Clear Search',
					'user' => 'Clear User',
				];

				foreach ($buttons as $type => $label):
				?>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="d-inline">
						<?php wp_nonce_field('fi_cache_clear'); ?>
						<input type="hidden" name="action" value="fi_cache_clear">
						<input type="hidden" name="cache_type" value="<?php echo esc_attr($type); ?>">
						<button type="submit" class="button <?php echo $type === 'all' ? 'button-primary' : 'button-secondary'; ?>">
							<?php echo esc_html($label); ?>
						</button>
					</form>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>
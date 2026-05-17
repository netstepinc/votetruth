<?php
if(!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html>
<head>
	<title>Freedom Index Import - Site <?php echo esc_html($blog_id); ?></title>
	<style>
		body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
		.fi-import-container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
		.fi-progress { background: #f0f0f0; border-radius: 5px; padding: 15px; margin: 20px 0; }
		.fi-progress-bar { background: #0073aa; height: 25px; border-radius: 3px; transition: width 0.3s; position: relative; }
		.fi-progress-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-weight: bold; }
		.fi-log { background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin: 20px 0; max-height: 400px; overflow-y: auto; border-radius: 5px; }
		.fi-log-entry { margin: 8px 0; padding: 8px; border-left: 4px solid #0073aa; background: #fff; border-radius: 3px; }
		.fi-log-error { border-left-color: #dc3232; background: #fef7f7; }
		.fi-log-success { border-left-color: #46b450; background: #f7fef7; }
		.fi-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
		.fi-stat-card { background: #fff; border: 1px solid #ddd; padding: 20px; text-align: center; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
		.fi-stat-number { font-size: 2em; font-weight: bold; color: #0073aa; }
		.fi-stat-label { color: #666; margin-top: 5px; }
		.fi-complete { background: #f7fef7; border: 1px solid #46b450; padding: 20px; border-radius: 5px; text-align: center; }
		.fi-error { background: #fef7f7; border: 1px solid #dc3232; padding: 20px; border-radius: 5px; text-align: center; }
	</style>
</head>
<body>
	<div class="fi-import-container">
		<h1>Freedom Index Import - Site <?php echo esc_html($blog_id); ?></h1>
		<div class="fi-progress">
			<div class="fi-progress-bar" style="width: 0%;">
				<div class="fi-progress-text" id="fi-progress-text">Starting import...</div>
			</div>
		</div>
		<div class="fi-stats" id="fi-stats"></div>
		<div class="fi-log" id="fi-log">
			<h3>Import Log</h3>
			<div id="fi-log-entries"></div>
		</div>
		<div id="fi-complete" style="display: none;" class="fi-complete">
			<h2>✅ Import Complete!</h2>
			<p>The import process has finished successfully.</p>
			<p><a href="<?php echo admin_url('admin.php?page=freedom-index-admin'); ?>" class="btn btn-primary">Return to Admin Dashboard</a></p>
		</div>
		<div id="fi-error" style="display: none;" class="fi-error">
			<h2>❌ Import Failed</h2>
			<p id="fi-error-message">An error occurred during the import process.</p>
			<p><a href="<?php echo admin_url('admin.php?page=freedom-index-admin&tab=import'); ?>" class="btn btn-secondary">Try Again</a></p>
		</div>
	</div>
	<script>
	jQuery(document).ready(function($) {
		let progress = 0;
		let isComplete = false;
		
		function updateProgress(percent, text) {
			$('#fi-progress-bar').css('width', percent + '%');
			$('#fi-progress-text').text(text);
		}
		
		function addLogEntry(message, type = 'info') {
			const timestamp = new Date().toLocaleTimeString();
			const entryClass = type === 'error' ? 'fi-log-error' : (type === 'success' ? 'fi-log-success' : 'fi-log-entry');
			$('#fi-log-entries').append(`<div class="${entryClass}">${timestamp}: ${message}</div>`);
			$('#fi-log').scrollTop($('#fi-log')[0].scrollHeight);
		}
		
		function updateStats(stats) {
			let statsHtml = '';
			for (const [key, value] of Object.entries(stats)) {
				if (key !== 'errors') {
					statsHtml += `
						<div class="fi-stat-card">
							<div class="fi-stat-number">${value}</div>
							<div class="fi-stat-label">${key.charAt(0).toUpperCase() + key.slice(1)}</div>
						</div>
					`;
				}
			}
			$('#fi-stats').html(statsHtml);
		}
		
		function runImport() {
			addLogEntry('Starting import process...');
			updateProgress(10, 'Connecting to legacy database...');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'fi_import_site',
					blog_id: <?php echo $blog_id; ?>,
					nonce: '<?php echo wp_create_nonce('fi_import_site'); ?>'
				},
				success: function(response) {
					if (response.success) {
						updateProgress(100, 'Import completed successfully!');
						addLogEntry('Import completed successfully!', 'success');
						updateStats(response.data);
						$('#fi-complete').show();
					} else {
						updateProgress(100, 'Import failed');
						addLogEntry('Import failed: ' + response.data.message, 'error');
						$('#fi-error-message').text(response.data.message);
						$('#fi-error').show();
					}
				},
				error: function() {
					updateProgress(100, 'Import failed');
					addLogEntry('Import failed: Network error', 'error');
					$('#fi-error-message').text('Network error occurred');
					$('#fi-error').show();
				}
			});
		}
		
		// Start import
		runImport();
	});
	</script>
</body>
</html>
<?php
if(!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html>
<head>
	<title>Freedom Index Import - Phase <?php echo esc_html($phase); ?></title>
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
		.fi-stat-number { font-size: 28px; font-weight: bold; color: #0073aa; margin-bottom: 5px; }
		.fi-stat-label { color: #666; font-size: 14px; }
		.fi-complete { background: #f7fef7; border: 1px solid #46b450; padding: 20px; border-radius: 5px; text-align: center; }
		.fi-error { background: #fef7f7; border: 1px solid #dc3232; padding: 20px; border-radius: 5px; text-align: center; }
	</style>
</head>
<body>
	<div class="fi-import-container">
		<h1>Freedom Index Import - Phase <?php echo esc_html($phase); ?></h1>
		
		<div class="fi-progress">
			<div class="fi-progress-bar" style="width: 0%;">
				<div class="fi-progress-text" id="fi-progress-text">Starting import...</div>
			</div>
		</div>
		
		<div class="fi-stats" id="fi-stats">
			<!-- Stats will be populated by JavaScript -->
		</div>
		
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
	// Real-time progress updates
	function updateProgress(percent, text) {
		document.querySelector('.fi-progress-bar').style.width = percent + '%';
		document.getElementById('fi-progress-text').textContent = text;
	}
	
	function addLogEntry(message, type = 'info') {
		const logEntries = document.getElementById('fi-log-entries');
		const entry = document.createElement('div');
		entry.className = 'fi-log-entry fi-log-' + type;
		entry.textContent = new Date().toLocaleTimeString() + ': ' + message;
		logEntries.appendChild(entry);
		logEntries.scrollTop = logEntries.scrollHeight;
	}
	
	function updateStats(stats) {
		const statsContainer = document.getElementById('fi-stats');
		statsContainer.innerHTML = '';
		
		for (const [label, value] of Object.entries(stats)) {
			const card = document.createElement('div');
			card.className = 'fi-stat-card';
			card.innerHTML = `
				<div class="fi-stat-number">${value}</div>
				<div class="fi-stat-label">${label}</div>
			`;
			statsContainer.appendChild(card);
		}
	}
	
	function showError(message) {
		document.getElementById('fi-error-message').textContent = message;
		document.getElementById('fi-error').style.display = 'block';
	}
	
	function showComplete() {
		document.getElementById('fi-complete').style.display = 'block';
	}
	
	// Start the import process
	addLogEntry('Starting import process...', 'info');
	updateProgress(10, 'Connecting to database...');
	
	fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		body: new URLSearchParams({
			action: 'fi_import_data',
			phase: '<?php echo esc_js($phase); ?>',
			nonce: '<?php echo wp_create_nonce('fi_import_data'); ?>'
		})
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			updateProgress(100, 'Import completed successfully!');
			addLogEntry('Import completed successfully!', 'success');
			
			if (data.data.logs) {
				data.data.logs.forEach(log => addLogEntry(log, 'info'));
			}
			
			if (data.data.stats) {
				updateStats(data.data.stats);
			}
			
			setTimeout(() => showComplete(), 1000);
		} else {
			updateProgress(0, 'Import failed');
			addLogEntry('Import failed: ' + (data.data?.message || 'Unknown error'), 'error');
			showError(data.data?.message || 'Unknown error occurred');
		}
	})
	.catch(error => {
		updateProgress(0, 'Import failed');
		addLogEntry('Import failed: ' + error.message, 'error');
		showError('Network error: ' + error.message);
	});
	</script>
</body>
</html>
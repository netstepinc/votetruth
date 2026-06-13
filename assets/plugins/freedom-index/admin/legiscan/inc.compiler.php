<?php
/**
 * Standalone Legiscan Data Compiler (AJAX-based)
 * Simple AJAX processor that calls extractor.php for each bill file
 * 
 * Usage: 
 *   GET with params: ?auth_key=...&gov=...&session=...
 * 
 * TEST: https://votestellthetruth.us/wp-content/jbsfi/legiscan/compiler.php?auth_key=aba1fcf8e23f67cd261842c7a8f30012&gov=US&session=2025-2026_119th_Congress
 */

// Referrer check (set to false to disable for direct testing)
define('CHECK_REFERRER', false);
define('FI_DOMAIN',home_url());

$compiler_auth_key = md5(strtotime(date('Y-m-d') . ' 00:00:01'));

// Get input data
$input = [];
if (!empty($_REQUEST)) {
	$input = $_REQUEST;
}

// Validate authentication
$auth_key = $input['auth_key'] ?? '';
if ($auth_key !== $compiler_auth_key) {
	header('Content-Type: application/json');
	echo json_encode(['error' => 'Invalid authentication key']);
	exit;
}

// Get config
$gov = $input['gov'] ?? '';
$session = $input['session'] ?? '';

// Validate required config
if (empty($gov) || empty($session)) {
	header('Content-Type: application/json');
	echo json_encode(['error' => 'Missing required parameters: gov, session']);
	exit;
}

// Construct data directory path
$data_dir = rtrim(__DIR__, '/') . '/' . $gov . '/' . $session . '/';
$bills_dir = rtrim($data_dir, '/') . '/bill/';
$votes_dir = rtrim($data_dir, '/') . '/vote/';
$fi_dir = rtrim($data_dir, '/') . '/fi/';
if (!is_dir($fi_dir)) {
	mkdir($fi_dir, 0755, true);
}

// Handle completion action (create __compiled flag file)
$action = $input['action'] ?? '';
if ($action === 'complete') {
	header('Content-Type: application/json');
	$compiled_file = rtrim($data_dir, '/') . '/__compiled';
	// Create empty file as completion flag
	file_put_contents($compiled_file, '');
	echo json_encode(['success' => true, 'message' => 'Compilation marked as complete']);
	exit;
}

// Main page output (non-AJAX)
header('Content-Type: text/html; charset=utf-8');

// Simple referrer check
if (CHECK_REFERRER) {
	$referrer = $_SERVER['HTTP_REFERER'] ?? '';
	if (strpos($referrer, FI_DOMAIN) === false) {
		echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Access Denied</title></head><body>";
		echo "<h1>Access Denied</h1>";
		echo "<meta http-equiv='refresh' content='3;url=" . FI_DOMAIN . "'>";
		echo "</body></html>";
		exit;
	}
}

// Get file lists
$bill_files = is_dir($bills_dir) ? glob($bills_dir . '*.json') : [];
$bill_file_list = [];
foreach ($bill_files as $file) {
	$file_name = basename($file, '.json');
	$fi_file_path = $fi_dir . $file_name . '.json';
	$bill_file_list[] = [
		'filename' => $file_name,
		'already_processed' => file_exists($fi_file_path),
	];
}

// Count votes found by counting JSON files in vote directory
$vote_files = is_dir($votes_dir) ? glob($votes_dir . '*.json') : [];
$total_votes_found = count($vote_files);

// Count already processed files
$already_processed = 0;
$already_saved = 0;
$already_votes_processed = 0;
foreach ($bill_file_list as $item) {
	if ($item['already_processed']) {
		$already_processed++;
		// Check if it has votes (was saved)
		$fi_file = json_decode(@file_get_contents($fi_dir . $item['filename'] . '.json'), true);
		if ($fi_file && !empty($fi_file['votes'])) {
			$already_saved++;
			$already_votes_processed += count($fi_file['votes']);
		}
	}
}

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset='utf-8'>
	<title>Legiscan Compiler</title>
	<style>
		body { font-family: monospace; padding: 4px; background: #f5f5f5; }
		h1,h2 { margin-top: 0; margin-bottom: 5px; }
		.status { background: #fff; padding: 10px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px; }
		.status h2 { margin-top: 0; }
		.status-line { margin: 5px 0; font-size: 1.1em; }
		.file-list { background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 4px; max-height: 220px; overflow-y: auto; }
		.file-item { padding: 3px 0; border-bottom: 1px solid #eee; }
		.file-item:last-child { border-bottom: none; }
		.pending { color: #666; }
		.processing { color: #0073aa; }
		.processed { color: #46b450; font-weight: bold; }
		.saved { color: #46b450; font-weight: bold; }
		.error { color: #dc3232; font-weight: bold; }
		.success { color: #46b450; font-weight: bold; font-size: 1.2em; margin-top: 15px; }
	</style>
</head>
<body>
	<h1>Compiling Legiscan Data from <?php echo $gov; ?>/<?php echo $session; ?></h1>
	
	<div class="status">
		<h2>Summary</h2>
		<div class="status-line">
			<strong>Bills Found:</strong> <span id="bills-found"><?php echo count($bill_file_list); ?></span> | 
			<strong>Votes Found:</strong> <span id="votes-found"><?php echo $total_votes_found; ?></span> | 
			<strong>Processed:</strong> <span id="bills-processed"><?php echo $already_processed; ?></span> | 
			<strong>Bills with Votes Saved:</strong> <span id="bills-saved"><?php echo $already_saved; ?></span> | 
			<strong>Votes Processed:</strong> <span id="votes-processed"><?php echo $already_votes_processed; ?></span>
		</div>
		<div id="final-status" style="display:none;" class="success">
			All bills processed! Refresh the page to see the latest data.
		</div>
	</div>
	
	<div class="file-list">
		<h3>Bill Files</h3>
		<div id="bill-files">
			<?php foreach ($bill_file_list as $idx => $item): ?>
				<div class="file-item" id="bill-<?php echo $idx; ?>" data-index="<?php echo $idx; ?>">
					<span class="<?php echo $item['already_processed'] ? 'saved' : 'pending'; ?>">
						<?php echo $item['already_processed'] ? 'Saved: ' : 'Pending: '; ?>
						<?php echo htmlspecialchars($item['filename']); ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	
	<script>
	(function() {
		const gov = <?php echo json_encode($gov); ?>;
		const session = <?php echo json_encode($session); ?>;
		const authKey = <?php echo json_encode($compiler_auth_key); ?>;
		const billCount = <?php echo count($bill_file_list); ?>;
		const billFiles = <?php echo json_encode($bill_file_list); ?>;
		const votesFound = <?php echo $total_votes_found; ?>;
		
		let processed = <?php echo $already_processed; ?>;
		let saved = <?php echo $already_saved; ?>;
		let votesProcessed = <?php echo $already_votes_processed; ?>;
		let currentIndex = 0;
		let activeRequests = 0;
		const MAX_CONCURRENT = 20;
		
		function updateStatus() {
			document.getElementById('bills-processed').textContent = processed;
			document.getElementById('bills-saved').textContent = saved;
			document.getElementById('votes-processed').textContent = votesProcessed;
		}
		
		function markFileStatus(index, status, message) {
			const el = document.getElementById('bill-' + index);
			if (el) {
				const span = el.querySelector('span');
				if (span) {
					span.className = status;
					span.textContent = message + ': ' + billFiles[index].filename;
				}
			}
		}
		
		function processBillFile(index) {
			if (index >= billCount) return;
			
			// Skip if already processed
			if (billFiles[index].already_processed) {
				currentIndex++;
				processNextBatch();
				return;
			}
			
			activeRequests++;
			markFileStatus(index, 'processing', 'Processing');
			
			const url = '<?php echo FI_DOMAIN; ?>/wp-content/jbsfi/legiscan/extractor.php?key=' + 
				encodeURIComponent(authKey) + 
				'&gov=' + encodeURIComponent(gov) + 
				'&session=' + encodeURIComponent(session) + 
				'&file=' + encodeURIComponent(billFiles[index].filename);
			
			const xhr = new XMLHttpRequest();
			xhr.open('GET', url);
			
			xhr.onload = function() {
				activeRequests--;
				processed++;
				
				try {
					const response = JSON.parse(xhr.responseText);
					if (response.status === 'processed' && response.saved) {
						saved++;
						const votesCount = response.votes_count || 0;
						votesProcessed += votesCount;
						markFileStatus(index, 'saved', 'Saved');
					} else if (response.status === 'processed') {
						// Processed but no votes - neutral status, not an error
						const votesCount = response.votes_count || 0;
						votesProcessed += votesCount;
						markFileStatus(index, 'processed', 'Processed');
					} else if (response.error) {
						markFileStatus(index, 'error', 'Error: ' + response.error);
					} else {
						markFileStatus(index, 'processed', 'Processed');
					}
				} catch (e) {
					console.error('Error parsing response for ' + billFiles[index].filename + ':', e);
					markFileStatus(index, 'error', 'Error: Parse failed');
				}
				
				updateStatus();
				processNextBatch();
			};
			
			xhr.onerror = function() {
				activeRequests--;
				processed++;
				markFileStatus(index, 'error', 'Error: Request failed');
				updateStatus();
				processNextBatch();
			};
			
			xhr.send();
		}
		
		function processNextBatch() {
			// Start up to MAX_CONCURRENT requests
			while (activeRequests < MAX_CONCURRENT && currentIndex < billCount) {
				processBillFile(currentIndex);
				currentIndex++;
			}
			
			// Check if all done
			if (activeRequests === 0 && currentIndex >= billCount) {
				document.getElementById('final-status').style.display = 'block';
				// Signal compilation complete by creating __compiled file
				fetch('<?php echo FI_DOMAIN; ?>/wp-content/jbsfi/legiscan/compiler.php?auth_key=' + 
					encodeURIComponent(authKey) + 
					'&gov=' + encodeURIComponent(gov) + 
					'&session=' + encodeURIComponent(session) + 
					'&action=complete')
					.catch(err => console.error('Failed to mark compilation complete:', err));
			}
		}
		
		// Start processing
		if (billCount > 0) {
			processNextBatch();
		} else {
			document.getElementById('final-status').style.display = 'block';
			document.getElementById('final-status').textContent = 'No files found to process.';
		}
	})();
	</script>
</body>
</html>

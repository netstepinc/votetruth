<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="border border-dark bg-info bg-opacity-10">
<?php
// Check if compiled data exists in session directory
$session_id_legiscan = $data['session_id'] ?? 0;
$compiled_file = rtrim($data_dir, '/') . '/__compiled';
$compiled_data_exists = file_exists($compiled_file);

// Only show subtask buttons if compiled data exists
if ($compiled_data_exists):

	// Run the people loop via output buffer to get the accurate $todo count.
	// This ensures reconciliation (filling in legiscan_id values) happens before the "missing" check.
	$current_fetch = $data_fetch;
	$current_subtask = $subtask;
	$current_gov = $gov;
	$dataset_data = $data;
	$dataset_files = [
		'people' => glob(rtrim($data_dir, '/') . '/people/*.json') ?: [],
	];

	// Always render people list fresh so form nonces (Add to Session, Add Legislator) are valid.
	ob_start();
	include FI_DIR . 'admin/legiscan/legiscan-people.php';
	$people_html = ob_get_clean();
	$missing_people_count = $todo ?? 0;

	// Subtask buttons
	echo '<div id="legiscan-subtask-buttons" class="p-3 d-flex flex-wrap gap-2 mb-3">';
	foreach ($fi_subtasks as $button) {
		// Hide votes button if people are missing.
		if (!empty($missing_people_count) && ($button['url'] ?? '') === 'votes') {
			continue;
		}
		// Skip refresh button if data doesn't exist (unless it's the active subtask)
		if (!empty($button['requires_data']) && !$data_dir_exists && $subtask !== $button['url']) {
			continue;
		}
		
		$is_active = ($subtask === $button['url']);
		$button_class = 'btn-' . ($is_active ? 'secondary' : ($button['url'] === 'refresh' ? 'danger' : 'primary'));
		
		if ($button['url'] === 'refresh') {
			// Refresh button with confirmation
			ob_start(); ?>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="d-inline" onsubmit="return confirm('ATTENTION: Legiscan limits API requests. We must minimize API calls to avoid rate limiting. Delete saved data only if you need to fetch the latest data.');">
				<?php wp_nonce_field('fi_legiscan_refresh_data'); ?>
				<input type="hidden" name="action" value="fi_legiscan_refresh_data">
				<input type="hidden" name="gov" value="<?php echo esc_attr($gov); ?>">
				<input type="hidden" name="fetch" value="<?php echo esc_attr($data_fetch); ?>">
				<button type="submit" class="btn btn-sm <?php echo esc_attr($button_class); ?> px-3"><?php echo esc_html($button['label']); ?></button>
			</form>
			<?php echo ob_get_clean();
		} else {
			$button_url = add_query_arg([
				'page'    => 'fi-legiscan-import',
				'gov'     => $gov,
				'fetch'   => $data_fetch,
				'subtask' => $button['url'],
			], admin_url('admin.php'));
			echo '<a href="' . esc_url($button_url) . '" class="btn btn-sm ' . esc_attr($button_class) . ' px-3">' . esc_html($button['label']) . '</a>';
		}
	}
	echo '</div>';
	
	// Votes import gating notice + auto-show people list if missing
	if ($missing_people_count > 0) {
		// Force subtask to 'people' so the list displays automatically
		$subtask = 'people';
		
		$mpc = esc_html((string) $missing_people_count);

		echo '<div class="px-3 pb-3">';
		echo '<div class="alert alert-warning mb-0" role="alert">';
		echo '<div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-2">';
		echo '<div><strong>Votes import is disabled.</strong> This session has <strong>' . $mpc . '</strong> '.($mpc == 1 ? 'person' : 'people').' not yet imported as legislators.</div>';
		echo '<div>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="d-inline">';
		wp_nonce_field('fi_legiscan_add_person');
		echo '<input type="hidden" name="action" value="fi_legiscan_add_person">';
		echo '<input type="hidden" name="gov" value="' . esc_attr($gov) . '">';
		echo '<input type="hidden" name="fetch" value="' . esc_attr($data_fetch) . '">';
		echo '<input type="hidden" name="subtask" value="people">';
		echo '<input type="hidden" name="people_id" value="ALL">';
		echo '<input type="hidden" name="dataset_state" value="' . esc_attr($gov) . '">';
		echo '<input type="hidden" name="data_dir_name" value="' . esc_attr($data_dir_name) . '">';
		echo '<button type="submit" class="btn btn-sm btn-primary">Import All ' . $mpc . ' Missing</button>';
		echo '</form>';
		echo '</div>';
		echo '</div>';
		echo '<div class="small mt-2">Import the missing people first, then you can import votes.</div>';
		echo '</div>';
		echo '</div>';
	}
endif;

// Compilation section - show iframe if data not compiled
if ($data_dir_exists && !$compiled_data_exists):
	// Build compiler URL
	$compiler_url = content_url('jbsfi/legiscan/compiler.php');
	$compiler_url = add_query_arg([
		'auth_key' => $compiler_auth_key,
		'gov' => $gov,
		'session' => $data_dir_name,
	], $compiler_url);

//echo '<div>COMPILER URL: ' . $compiler_url . '</div>';
	?>
	<div id="legiscan-compile-container">
		<iframe 
			id="legiscan-compile-iframe"
			src="<?php echo esc_url($compiler_url); ?>"
			class="w-100 border bg-white"
			style="height: 400px;"
			frameborder="0"
			scrolling="auto">
		</iframe>
	</div>
	<?php
elseif ($data_dir_exists && $compiled_data_exists):
	// Data is compiled - show recompile option (optional)
endif;

// Use local data directory (already fetched)
$files = null;
if ($data_dir_exists) {
	$files = $legiscan->dir_tree($data_dir);
}

if ($files && $subtask && $subtask !== 'refresh') {
	$partial_file = __DIR__ . '/legiscan-' . $subtask . '.php';
	if (file_exists($partial_file)) {
		echo '<div class="p-3">';
		echo '<h3>' . esc_html($governments[$gov] ?? $gov) . ': ' . esc_html($data['session_name'] ?? '') . ': ' . esc_html(ucfirst($subtask)) . '</h3>';
		
		// For 'people' subtask, reuse the already-captured output from the $todo calculation.
		if ($subtask === 'people' && isset($people_html)) {
			echo $people_html;
		} else {
			// Pass variables to partial
			$dataset_data = $data;
			$dataset_files = $files;
			$current_gov = $gov;
			$current_subtask = $subtask;
			$current_fetch = $data_fetch;
			$current_import_person_id = $import_person_id;
			$data_dir_for_partial = $data_dir;
			
			include $partial_file;
		}
		echo '</div>';
	}
} elseif ($subtask === 'refresh') {
	echo '<p class="text-muted">Click the "Delete Saved Legiscan Data and Refresh" button above to delete cached data and force a fresh fetch.</p>';
} elseif (!$session_exists) {
	echo '<h3>You must add this session before you can add information.</h3>';
}
?>
</div>
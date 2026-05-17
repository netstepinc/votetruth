<?php
namespace FI\Admin\MigrateJson;

if (!defined('ABSPATH')) exit;

/**
 * JSON-based migration runner (V2 export -> V3 tables).
 *
 * Summary:
 * - Uses exported JSON files in wp-content/jbsfi/migrate/ as the ONLY source.
 * - No legacy DB connection, no AJAX.
 * - Hard-stop on any missing cross-reference and print maximum diagnostics.
 */

require_once __DIR__ . '/json-migrate-lib.php';

function render_page(): void {
	if (!current_user_can('manage_network_options')) {
		wp_die(esc_html__('Sorry, you are not allowed to access this page.', 'freedom-index'));
	}

	$export_dir = fi_migrate_json_export_dir();
	$files = fi_migrate_json_list_exports();

	$selected = sanitize_text_field($_REQUEST['export_file'] ?? '');
	$run = isset($_POST['fi_migrate_json_run']);
	$run_overrides = isset($_POST['fi_migrate_json_run_overrides']);
	$run_overrides_all = isset($_POST['fi_migrate_json_run_overrides_all']);

	echo '<div class="wrap">';
	echo '<h1>Migration (JSON Exports)</h1>';
	//echo '<p>This migration reads V2 export JSON files from <code>' . esc_html(str_replace(ABSPATH, '/', $export_dir)) . '</code> and imports into V3 tables. It is <strong>non-AJAX</strong> and will <strong>stop immediately on the first error</strong> with detailed diagnostics.</p>';

	if (empty($files)) {
		echo '<div class="notice notice-error"><p>No export files found. Expected files like <code>*-fi-export.json</code> in: <code>' . esc_html($export_dir) . '</code></p></div>';
		echo '</div>';
		return;
	}

	echo '<form method="post">';
	wp_nonce_field('fi_migrate_json_run');

	echo '<table class="form-table"><tbody>';
	echo '<tr><th scope="row"><label for="export_file">Export file</label></th><td>';
	echo '<select name="export_file" id="export_file" style="min-width: 420px">';
	foreach ($files as $file) {
		$val = basename($file);
		$sel = ($selected === $val) ? ' selected' : '';
		echo '<option value="' . esc_attr($val) . '"' . $sel . '>' . esc_html($val) . '</option>';
	}
	echo '</select>';
	echo '<p class="description">Select an export JSON to import (example: <code>tx-fi-export.json</code>).</p>';
	echo '</td></tr>';
	echo '</tbody></table>';

	echo '<p>';
//DONE  echo '<button type="submit" class="button button-primary" name="fi_migrate_json_run" value="1">Run Migration</button> ';
//DONE	echo '<button type="submit" class="button button-secondary" name="fi_migrate_json_run_overrides" value="1">Import Vote Overrides Only (Selected File)</button> ';
//DONE	echo '<button type="submit" class="button button-secondary" name="fi_migrate_json_run_overrides_all" value="1">Import Vote Overrides Only (All Files)</button>';
	echo '</p>';
	echo '</form>';

//SINGLE USE PROCESS ON PAGE LOAD
//1. OPEN EACH file in the migration directory
//2. legislator_photo (filename) + empty url_photo/image_url -> build legacy_image_url, output UPDATEs by legacy_id

/* PROCESS COMPLETED
	$legacy_image_updates = [];
	foreach ($files as $file_path) {
		$raw = file_get_contents($file_path);
		if ($raw === false) {
			continue;
		}
		$data = json_decode($raw, true);
		if (!is_array($data)) {
			continue;
		}
		$gov = strtoupper((string) ($data['gov'] ?? ''));
		if ($gov === '') {
			continue;
		}
		$posts = $data['post_types']['legislator'] ?? [];
		foreach ($posts as $post_id_str => $row) {
			$legacy_post_id = absint($row['fields']['ID'] ?? $post_id_str);
			if (!$legacy_post_id) {
				continue;
			}
			$meta = $row['meta'] ?? [];
			$legislator_photo = trim((string) ($meta['legislator_photo'] ?? ''));
			$url_photo = trim((string) ($meta['url_photo'] ?? $meta['image_url'] ?? ''));
			if ($legislator_photo === '' || $url_photo !== '') {
				continue;
			}
			$legacy_key = fi_migrate_json_legacy_key($gov, $legacy_post_id);
			$existing = fi_migrate_json_db_find_by_legacy_id('fi_legislators', $legacy_key);
			if (!$existing || empty($existing['id'])) {
				continue;
			}
			$url = 'https://thefreedomindex.org/assets/' . $legislator_photo;
			$url_esc = str_replace(['\\', "'"], ['\\\\', "''"], $url);
			global $wpdb;
			$tbl = $wpdb->prefix . 'fi_legislators';
			$id = (int) $existing['id'];
			$legacy_image_updates[] = "UPDATE {$tbl} SET legacy_image_url = '{$url_esc}' WHERE id = {$id};";
		}
	}
	if (!empty($legacy_image_updates)) {
		echo '<hr /><h2>Legacy image_url UPDATEs (run in phpMyAdmin)</h2>';
		echo '<pre style="background:#fafafa;border:1px solid #ccc;padding:10px;max-height:40vh;overflow:auto;">' . esc_html(implode("\n", $legacy_image_updates)) . '</pre>';
	}
*/


//EXPOSE THE LEGACY IMAGE UPDATE PROCESS WITHOUT SELECTING A FILE
// Legacy image import runner (AJAX, one row at a time; resumable)
echo '<hr />';
echo '<h2>Legacy Image Import</h2>';
echo '<p class="description">Migration stores source images in <code>fi_legislators.legacy_image_url</code>. This runner processes them in small batches and clears the URL when <code>image_id</code> is set.</p>';
echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:12px;">';
echo '<div class="d-flex align-items-center gap-2 flex-wrap">';
echo '<label for="fi-img-limit"><strong>Batch</strong></label> ';
echo '<input id="fi-img-limit" type="number" value="5" min="1" max="25" style="width:90px;">';
echo '<button type="button" class="button button-secondary" id="fi-img-run">Run Image Import</button>';
echo '</div>';
echo '<div style="margin-top:10px;max-height:35vh;overflow:auto;border:1px solid #eee;padding:10px;background:#fafafa;">';
echo '<pre id="fi-img-log" style="margin:0;white-space:pre-wrap;"></pre>';
echo '</div>';
echo '</div>';

echo '<script>
(function($){
	function logLine(s){
		var el = document.getElementById("fi-img-log");
		if(!el) return;
		el.textContent += s + "\n";
	}
	function logErrorObj(e){
		try{
			logLine(" - " + JSON.stringify(e));
		}catch(_){
			logLine(" - [unserializable error]");
		}
	}
	var running=false;
	$(document).on("click", "#fi-img-run", function(){
		if(running) return;
		running=true;
		logLine("Starting legacy image import...");
		var limit = parseInt($("#fi-img-limit").val()||"5",10);
		var nonce = (window.fiAdmin && window.fiAdmin.nonce) ? window.fiAdmin.nonce : "";
		function tick(){
			$.ajax({
				url: ajaxurl,
				type: "POST",
				data: {
					action: "fi_admin_action",
					sub_action: "process_legacy_legislator_images",
					limit: limit,
					nonce: nonce
				},
				success: function(resp){
					if(!resp || !resp.success){
						logLine("ERROR: " + JSON.stringify(resp && resp.data ? resp.data : resp));
						running=false;
						return;
					}
					var d = resp.data || {};
					logLine("Batch: processed="+d.processed+" updated="+d.updated+" skipped="+d.skipped);
					if(d.errors && d.errors.length){
						logLine("Errors: "+d.errors.length);
						for(var i=0;i<d.errors.length;i++){
							logErrorObj(d.errors[i]);
						}
					}
					if((d.processed||0) === 0){
						logLine("Done (no more legacy_image_url rows found).");
						running=false;
						return;
					}
					setTimeout(tick, 250);
				},
				error: function(xhr){
					logLine("XHR ERROR: " + (xhr && xhr.status ? xhr.status : "") );
					running=false;
				}
			});
		}
		tick();
	});
})(jQuery);
</script>';





















	if ($run) {
		check_admin_referer('fi_migrate_json_run');
		$file = fi_migrate_json_resolve_export_path($selected);
		echo '<hr />';
		echo '<h2>Run Output</h2>';
		echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:60vh;overflow:auto;">';
		echo '<pre style="margin:0;white-space:pre-wrap;">';
		fi_migrate_json_run_with_output($file);
		echo '</pre>';
		echo '</div>';

		// Legacy image import runner (AJAX, one row at a time; resumable)
		echo '<hr />';
		echo '<h2>Legacy Image Import</h2>';
		echo '<p class="description">Migration stores source images in <code>fi_legislators.legacy_image_url</code>. This runner processes them in small batches and clears the URL when <code>image_id</code> is set.</p>';
		echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:12px;">';
		echo '<div class="d-flex align-items-center gap-2 flex-wrap">';
		echo '<label for="fi-img-limit"><strong>Batch</strong></label> ';
		echo '<input id="fi-img-limit" type="number" value="5" min="1" max="25" style="width:90px;">';
		echo '<button type="button" class="button button-secondary" id="fi-img-run">Run Image Import</button>';
		echo '</div>';
		echo '<div style="margin-top:10px;max-height:35vh;overflow:auto;border:1px solid #eee;padding:10px;background:#fafafa;">';
		echo '<pre id="fi-img-log" style="margin:0;white-space:pre-wrap;"></pre>';
		echo '</div>';
		echo '</div>';

		echo '<script>
		(function($){
			function logLine(s){
				var el = document.getElementById("fi-img-log");
				if(!el) return;
				el.textContent += s + "\n";
			}
			function logErrorObj(e){
				try{
					logLine(" - " + JSON.stringify(e));
				}catch(_){
					logLine(" - [unserializable error]");
				}
			}
			var running=false;
			$(document).on("click", "#fi-img-run", function(){
				if(running) return;
				running=true;
				logLine("Starting legacy image import...");
				var limit = parseInt($("#fi-img-limit").val()||"5",10);
				var nonce = (window.fiAdmin && window.fiAdmin.nonce) ? window.fiAdmin.nonce : "";
				function tick(){
					$.ajax({
						url: ajaxurl,
						type: "POST",
						data: {
							action: "fi_admin_action",
							sub_action: "process_legacy_legislator_images",
							limit: limit,
							nonce: nonce
						},
						success: function(resp){
							if(!resp || !resp.success){
								logLine("ERROR: " + JSON.stringify(resp && resp.data ? resp.data : resp));
								running=false;
								return;
							}
							var d = resp.data || {};
							logLine("Batch: processed="+d.processed+" updated="+d.updated+" skipped="+d.skipped);
							if(d.errors && d.errors.length){
								logLine("Errors: "+d.errors.length);
								for(var i=0;i<d.errors.length;i++){
									logErrorObj(d.errors[i]);
								}
							}
							if((d.processed||0) === 0){
								logLine("Done (no more legacy_image_url rows found).");
								running=false;
								return;
							}
							setTimeout(tick, 250);
						},
						error: function(xhr){
							logLine("XHR ERROR: " + (xhr && xhr.status ? xhr.status : "") );
							running=false;
						}
					});
				}
				tick();
			});
		})(jQuery);
		</script>';
	}

	if ($run_overrides || $run_overrides_all) {
		check_admin_referer('fi_migrate_json_run');
		echo '<hr />';
		echo '<h2>Vote Override Import Output</h2>';
		echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:60vh;overflow:auto;">';
		echo '<pre style="margin:0;white-space:pre-wrap;">';
		
		if ($run_overrides_all) {
			// Process all files
			foreach ($files as $file) {
				$file_path = fi_migrate_json_resolve_export_path(basename($file));
				fi_migrate_json_import_vote_overrides($file_path);
			}
		} else {
			// Process selected file only
			$file = fi_migrate_json_resolve_export_path($selected);
			fi_migrate_json_import_vote_overrides($file);
		}
		
		echo '</pre>';
		echo '</div>';
	}

	echo '</div>';
}


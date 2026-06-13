<?php if(!defined('ABSPATH')) exit;

/* We have legislators who were imported in two different governments: i.e. FL-REP then elected as US-REP from FL.
We need to flag for investigation based on display name match. If it is the same person, we need to merge state legislators into federal legislators.
1. Query legislators by display name excluding current <legislator->id
2. Link to the other legislator's edit page for investigation.
3. If is duplicate: 1-click merge button with Are you Sure confirmation.
4. Merge process should:
	a. Copy current (from) fi_legislators record into the other legislator's fi_legislators:meta:merged_legislator (dedicated meta key for this)
	b. Update rollcall votes from old to new legislator_id
	c. Update session assignments from old to new legislator_id
	d. Delete the old fi_legislators record
*/

function fi_merge_legislators_duplicate_check($legislator){
	global $wpdb;
	$similar_legislators = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}fi_legislators WHERE first_name = %s AND last_name = %s AND id != %d", $legislator->first_name, $legislator->last_name, $legislator->id));
	if($similar_legislators){
		foreach($similar_legislators as $similar_legislator){
			echo '<div class="card-footer bg-warning">';
			echo '<a href="'.fi_admin_url('fi-legislators', ['action' => 'edit', 'legislator_id' => $similar_legislator->id]).'" target="_blank" rel="noopener" class="me-3 btn btn-sm btn-dark">Is ['.$similar_legislator->id.'] '.$similar_legislator->display_name.' a duplicate?</a>';
			if(isset($legislator->gov) && $legislator->gov != 'US'){
				echo '<button 
					type="button" 
					class="btn btn-sm btn-danger ms-auto fi-merge-legislators-btn" 
					data-merge-from-legislator="'.esc_attr($legislator->id).'" 
					data-merge-to-legislator="'.esc_attr($similar_legislator->id).'"
					data-from-display-name="'.esc_attr($legislator->display_name).'"
					data-to-display-name="'.esc_attr($similar_legislator->display_name).'"
				>Merge into ['.$similar_legislator->id.'] '.$similar_legislator->display_name.'</button>';
			}
			echo '</div>';
fi_log("DUPLICATE CHECK: {$legislator->id} = {$similar_legislator->id}", __FILE__, __LINE__, 'info');
			// Inline script for merge confirmation dialog and redirect to admin handler
			?>
			<script>
			document.addEventListener('DOMContentLoaded', function() {
				const mergeBtns = document.querySelectorAll('.fi-merge-legislators-btn');
				mergeBtns.forEach(function(btn) {
					btn.addEventListener('click', function(e) {
						const fromId = btn.getAttribute('data-merge-from-legislator');
						const toId = btn.getAttribute('data-merge-to-legislator');
						const fromName = btn.getAttribute('data-from-display-name');
						const toName = btn.getAttribute('data-to-display-name');
						const msg = `Are you sure you want to merge ${fromName} (ID: ${fromId}) into ${toName} (ID: ${toId})?\n\nTHIS IS NOT REVERSIBLE!!!`;
						if(confirm(msg)) {
							// Redirect to an admin-ajax or admin-post URL to process merge
							// Adjust the action and parameters as needed in your handler
							const mergeUrl = '<?php echo esc_js(admin_url('admin-post.php')); ?>'
								+ '?action=fi_merge_legislators'
								+ '&from_legislator_id=' + encodeURIComponent(fromId)
								+ '&to_legislator_id=' + encodeURIComponent(toId)
								+ '&_wpnonce=<?php echo esc_js(wp_create_nonce('fi_merge_legislators')); ?>';
							window.location.href = mergeUrl;
						}
					});
				});
			});
			</script>
			<?php
		}
	}
}


/* This file is autoloaded in the admin area
 * Register admin_post_fi_merge_legislators handler to receive GET (from_legislator_id, to_legislator_id, _wpnonce), verify nonce and capability, call fi_merge_legislators(), then redirect to to-legislator edit page.
*/
add_action('admin_post_fi_merge_legislators', 'fi_admin_post_fi_merge_legislators');
function fi_admin_post_fi_merge_legislators(){
	if(!current_user_can(FI_CAP_MANAGE)){
		wp_die(__('Insufficient permissions.', 'freedom-scorecard'));
	}
	if (!isset($_GET['from_legislator_id']) || !isset($_GET['to_legislator_id']) || !isset($_GET['_wpnonce'])) {
		fi_log("MERGE: INVALID REQUEST: from_legislator_id={$_GET['from_legislator_id']} to_legislator_id={$_GET['to_legislator_id']} _wpnonce={$_GET['_wpnonce']}", __FILE__, __LINE__, 'info');
		wp_die(__('Invalid request.', 'freedom-scorecard'));
	}
	if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'fi_merge_legislators')) {
		fi_log("MERGE: INVALID NONCE: from_legislator_id={$_GET['from_legislator_id']} to_legislator_id={$_GET['to_legislator_id']} _wpnonce={$_GET['_wpnonce']}", __FILE__, __LINE__, 'info');
		wp_die(__('Invalid nonce.', 'freedom-scorecard'));
	}
	$from_id = absint($_GET['from_legislator_id']);
	$to_id   = absint($_GET['to_legislator_id']);
	fi_log("MERGE READY: from_legislator_id={$from_id} to_legislator_id={$to_id}", __FILE__, __LINE__, 'info');
	$ok = fi_merge_legislators($from_id, $to_id);
	$redirect = fi_admin_url('fi-legislators', ['action' => 'edit', 'legislator_id' => $to_id]);
	$redirect = add_query_arg('merge', $ok ? '1' : '0', $redirect);
	wp_safe_redirect($redirect);
	exit;
}

/**
 * Execute Step 4: merge from_legislator into to_legislator (state into federal).
 * a. Copy from fi_legislators row into to's meta key merged_legislator_{from_legislator_id}.
 * b. Reassign fi_voterc rows from from to to (resolve vote_id+legislator_id conflicts by keeping to).
 * c. Reassign fi_legislator_sessions rows from from to to (resolve unique conflicts by dropping from row).
 * d. Delete the from fi_legislators record.
 *
 * When $execute is false: no DB writes; each intended query is logged via fi_log() (single-row SQL)
 * so you can run them 1-by-1 in PHPMyAdmin. Return true if validation passed.
 *
 * @param int $from_legislator_id Legislator being merged away (e.g. state).
 * @param int $to_legislator_id Legislator that receives data (e.g. federal).
 * @return bool True on success (or simulate validation ok), false on validation or DB failure.
 */
function fi_merge_legislators($from_legislator_id, $to_legislator_id) {
	$execute = true;

	global $wpdb;
	$pre = $wpdb->prefix;
	$from_legislator_id = absint($from_legislator_id);
	$to_legislator_id   = absint($to_legislator_id);
	if ($from_legislator_id === 0 || $to_legislator_id === 0 || $from_legislator_id === $to_legislator_id) {
		return false;
	}

	$from_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$pre}fi_legislators WHERE id = %d",$from_legislator_id),ARRAY_A);
	$to_exists = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$pre}fi_legislators WHERE id = %d",$to_legislator_id),ARRAY_A);
	if (!$from_row || !$to_exists) {
		fi_log("MERGE: INVALID LEGISLATORS: from_legislator_id={$from_legislator_id} to_legislator_id={$to_legislator_id}", __FILE__, __LINE__, 'info');
		return false;
	}

	//Alway log the merge operation for reference
	fi_log("MERGE from_legislator_id={$from_legislator_id} to_legislator_id={$to_legislator_id}", __FILE__, __LINE__, 'info');

	// 4a: UPDATE fi_legislators meta (merged_legislator_{id})
	$meta_payload = ['merged_legislator_' . $from_legislator_id => $from_row];
	$json = wp_json_encode($meta_payload);
	$escaped = str_replace(["\\", "'"], ["\\\\", "''"], $json);
	$sql = "UPDATE {$pre}fi_legislators SET meta = JSON_MERGE_PATCH(IFNULL(meta, '{}'), '{$escaped}') WHERE id = {$to_legislator_id};";
	fi_log("1:fi_legislators meta: " . $sql, __FILE__, __LINE__, 'info');
	if ($execute) {
		if ($wpdb->query($sql) === false) {
			return false;
		}
	}

	//Update external ID columns directly on $to_legislator_id if missing/null. || State people will not have lis_id
	$fields_to_update = [];
	foreach (['legiscan_id', 'govtrack_id', 'votesmart_id', 'ballotpedia_id', 'openstates_id'] as $field) {
		if (empty($to_exists[$field]) && !empty($from_row[$field])) {
			$fields_to_update[$field] = $from_row[$field];
		}
	}
	if (!empty($fields_to_update)) {
		$where = ['id' => $to_legislator_id];
		fi_log("1b: UPDATE externals: " . json_encode($fields_to_update), __FILE__, __LINE__, 'info');
		if ($execute) {
			$wpdb->update($pre . 'fi_legislators', $fields_to_update, $where);
		}
	}

	// 4b: Rollcall votes
	$voterc_from = $wpdb->get_results($wpdb->prepare("SELECT id FROM {$pre}fi_voterc WHERE legislator_id = %d", $from_legislator_id), ARRAY_A);
	foreach ($voterc_from as $rc) {
		$sql = "UPDATE {$pre}fi_voterc SET legislator_id = {$to_legislator_id} WHERE id = " . (int) $rc['id'] . ";";
		fi_log("2: fi_voterc: " . $sql, __FILE__, __LINE__, 'info');
		if ($execute) {
			$wpdb->query($sql);
		}
	}
	// Add CSV list string containing fi_voterc:id to fi_legislators meta as merged_legislator_{from_legislator_id}_votercs
	$meta_payload = ['merged_legislator_' . $from_legislator_id . '_votercs' => implode(',', array_column($voterc_from, 'id'))];
	$json = wp_json_encode($meta_payload);
	$escaped = str_replace(["\\", "'"], ["\\\\", "''"], $json);
	$sql = "UPDATE {$pre}fi_legislators SET meta = JSON_MERGE_PATCH(IFNULL(meta, '{}'), '{$escaped}') WHERE id = {$to_legislator_id};";
	fi_log("3: fi_legislators meta: " . $sql, __FILE__, __LINE__, 'info');
	if ($execute) {
		$wpdb->query($sql);
	}

	// 4c: Session assignments
	$ls_from = $wpdb->get_results($wpdb->prepare("SELECT id FROM {$pre}fi_legislator_sessions WHERE legislator_id = %d", $from_legislator_id), ARRAY_A);
	foreach ($ls_from as $ls) {
		$sql = "UPDATE {$pre}fi_legislator_sessions SET legislator_id = {$to_legislator_id} WHERE id = " . (int) $ls['id'] . ";";
		fi_log("4c fi_legislator_sessions: " . $sql, __FILE__, __LINE__, 'info');
		if ($execute) {
			$wpdb->query($sql);
		}
	}
	// Add CSV list string containing fi_legislator_sessions:id to fi_legislators meta as merged_legislator_{from_legislator_id}_sessions
	$meta_payload = ['merged_legislator_' . $from_legislator_id . '_sessions' => implode(',', array_column($ls_from, 'id'))];
	$json = wp_json_encode($meta_payload);
	$escaped = str_replace(["\\", "'"], ["\\\\", "''"], $json);
	$sql = "UPDATE {$pre}fi_legislators SET meta = JSON_MERGE_PATCH(IFNULL(meta, '{}'), '{$escaped}') WHERE id = {$to_legislator_id};";
	fi_log("4d: fi_legislators meta: " . $sql, __FILE__, __LINE__, 'info');
	if ($execute) {
		$wpdb->query($sql);
	}

	// 4d: Delete the from fi_legislators record
	$sql = "DELETE FROM {$pre}fi_legislators WHERE id = {$from_legislator_id};";
	fi_log("4: fi_legislators delete: " . $sql, __FILE__, __LINE__, 'info');
	fi_log("MERGE SIMULATE end — no changes written.", __FILE__, __LINE__, 'info');
	if ($execute) {
		if ($wpdb->query($sql) === false) {
			return false;
		}
		fi_clear_disk_cache();
		return true;
	}
	return false;
}
<?php if (!defined('ABSPATH')) { exit; }

/**
 * Render votes page (list + filters)
 */
function fi_admin_votes_render(): void {
	include __DIR__ . '/../views/votes.php';
}

/**
 * Render vote add/edit form
 */
function fi_admin_votes_render_form(array $scope, string $action): void {
	include __DIR__ . '/../views/vote-edit.php';
}

/**
 * Handle POST submissions for votes
 */
function fi_admin_votes_maybe_handle_save(array $scope): void {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}

	if (!isset($_POST['fi_vote_nonce'])) {
		return;
	}

	fi_admin_votes_handle_save($scope);
}

/**
 * Persist a vote record and tags
 */
function fi_admin_votes_handle_save(array $scope): void {
	//fi_log('VOTE SAVE: Starting handle_save', __FILE__, __LINE__);
	
	// Ensure no output has been sent
	if (headers_sent($file, $line)) {
		//fi_log("Vote save: Headers already sent in {$file} at line {$line}", __FILE__, __LINE__);
		add_settings_error('fi_votes', 'save_error', 'Cannot save vote: output already sent.', 'error');
		return;
	}

	if (!wp_verify_nonce($_POST['fi_vote_nonce'] ?? '', 'fi_save_vote')) {
		//fi_log('VOTE SAVE: Nonce verification failed', __FILE__, __LINE__);
		add_settings_error('fi_votes', 'save_error', 'Security check failed. Please try again.', 'error');
		return;
	}

	if (!current_user_can(FI_CAP_MANAGE)) {
		//fi_log('VOTE SAVE: Insufficient permissions', __FILE__, __LINE__);
		add_settings_error('fi_votes', 'save_error', 'Insufficient permissions.', 'error');
		return;
	}

	$vote_id = isset($_POST['vote_id']) ? absint($_POST['vote_id']) : null;
	$session_id = absint($_POST['session_id'] ?? 0);
	$title = sanitize_text_field($_POST['title'] ?? '');

	//fi_log("VOTE SAVE: vote_id={$vote_id}, session_id={$session_id}, title={$title}", __FILE__, __LINE__);

	if (!$session_id) {
		//fi_log('VOTE SAVE: Session ID is missing', __FILE__, __LINE__);
		add_settings_error('fi_votes', 'save_error', 'Session is required.', 'error');
		return;
	}

	if ($title === '') {
		//fi_log('VOTE SAVE: Title is missing', __FILE__, __LINE__);
		add_settings_error('fi_votes', 'save_error', 'Title is required.', 'error');
		return;
	}

	$slug = sanitize_text_field($_POST['slug'] ?? '');

	$existing_vote = $vote_id ? fi_vote_get($vote_id) : null;

	$data = [
		'session_id' => $session_id,
		'gov' => $existing_vote['gov'] ?? $scope['gov'] ?? null,
		'chamber' => strtoupper(sanitize_text_field($_POST['chamber'] ?? '')),
		'title' => $title,
		'slug' => $slug,
		'bill_number' => sanitize_text_field($_POST['bill_number'] ?? ''),
		'constitutional' => strtoupper(sanitize_text_field($_POST['constitutional'] ?? 'U')),
		'rollcall_number' => sanitize_text_field($_POST['rollcall_number'] ?? ''),
		'status' => sanitize_key($_POST['status'] ?? 'publish') ?: 'publish',
		'date_voted' => sanitize_text_field($_POST['date_voted'] ?? ''),
		'legiscan_bid' => isset($_POST['legiscan_bid']) ? absint($_POST['legiscan_bid']) : null,
		'legiscan_rcid' => isset($_POST['legiscan_rcid']) ? absint($_POST['legiscan_rcid']) : null,
	];

	if ($data['chamber'] === '') {
		unset($data['chamber']);
	}

	$meta_fields = fi_admin_votes_get_meta_fields();
	$meta_input = is_array($_POST['meta'] ?? null) ? $_POST['meta'] : [];
	$data['meta'] = fi_admin_votes_build_meta_payload($vote_id, $meta_fields, $meta_input);
	
	// Auto-populate Legiscan meta fields if blank but legiscan IDs exist
	$legiscan_bid = $data['legiscan_bid'] ?? ($existing_vote['legiscan_bid'] ?? null);
	$legiscan_rcid = $data['legiscan_rcid'] ?? ($existing_vote['legiscan_rcid'] ?? null);
	
	$legiscan_meta_keys = ['bill_title', 'bill_description', 'url_bill', 'url_rollcall'];
	$has_blank_legiscan_meta = false;
	foreach ($legiscan_meta_keys as $key) {
		if (empty($data['meta'][$key])) {
			$has_blank_legiscan_meta = true;
			break;
		}
	}
	
	if ($has_blank_legiscan_meta && !empty($legiscan_bid) && !empty($legiscan_rcid)) {
		// Get bill_number from bill_number or use legiscan_bid as fallback
		$bill_number = $data['bill_number'] ?: ($existing_vote['bill_number'] ?? (string) $legiscan_bid);
		
		// Try to fetch Legiscan data
		$legiscan_args = [
			'gov' => $data['gov'] ?? ($existing_vote['gov'] ?? 'US'),
			'LS_bill_id' => $bill_number,
			'LS_roll_call_id' => $legiscan_rcid,
		];
		
		// If we have a vote_id, use it to get session info
		if ($vote_id) {
			$legiscan_args['fi_vote_id'] = $vote_id;
		}
		
		$legiscan_data = fi_legiscan_vote_data($legiscan_args);
		
		if (!isset($legiscan_data['error']) && !empty($legiscan_data['bill'])) {
			$bill = $legiscan_data['bill'];
			$roll_call = $legiscan_data['roll_call'] ?? [];
			
			// Fill in blank fields only (using same mapping logic as create_vote)
			if (empty($data['meta']['bill_title']) && !empty($bill['title'])) {
				$data['meta']['bill_title'] = sanitize_text_field($bill['title']);
			}
			if (empty($data['meta']['bill_description']) && !empty($bill['description'])) {
				$data['meta']['bill_description'] = sanitize_textarea_field($bill['description']);
			}
			if (empty($data['meta']['url_bill'])) {
				if (!empty($bill['state_link'])) {
					$data['meta']['url_bill'] = esc_url_raw($bill['state_link']);
				} elseif (!empty($bill['url'])) {
					$data['meta']['url_bill'] = esc_url_raw($bill['url']);
				}
			}
			if (empty($data['meta']['url_rollcall']) && !empty($roll_call['state_link'])) {
				$data['meta']['url_rollcall'] = esc_url_raw($roll_call['state_link']);
			}
			if (empty($data['meta']['url_legiscan']) && !empty($bill['url'])) {
				$data['meta']['url_legiscan'] = esc_url_raw($bill['url']);
			}
			if (empty($data['meta']['vote_title']) && !empty($roll_call['desc'])) {
				$data['meta']['vote_title'] = sanitize_text_field($roll_call['desc']);
			}
			
			// Add vote counts if not present
			if (!isset($data['meta']['votes_yea']) && isset($roll_call['yea'])) {
				$data['meta']['votes_yea'] = (int) $roll_call['yea'];
			}
			if (!isset($data['meta']['votes_nay']) && isset($roll_call['nay'])) {
				$data['meta']['votes_nay'] = (int) $roll_call['nay'];
			}
			if (!isset($data['meta']['votes_nv']) && isset($roll_call['nv'])) {
				$data['meta']['votes_nv'] = (int) $roll_call['nv'];
			}
			if (!isset($data['meta']['votes_absent']) && isset($roll_call['absent'])) {
				$data['meta']['votes_absent'] = (int) $roll_call['absent'];
			}
			if (!isset($data['meta']['votes_total']) && isset($roll_call['total'])) {
				$data['meta']['votes_total'] = (int) $roll_call['total'];
			}
			
			// Add legiscan_session_id if not present
			if (!isset($data['meta']['legiscan_session_id']) && isset($bill['session_id'])) {
				$data['meta']['legiscan_session_id'] = (int) $bill['session_id'];
			}
		}
	}

	$saved_id = fi_vote_save($data, $vote_id);

	if (!$saved_id) {
		global $wpdb;
		$error_msg = 'Unable to save vote.';
		if ($wpdb->last_error) {
			$error_msg .= ' Database error: ' . $wpdb->last_error;
		}
		add_settings_error('fi_votes', 'save_error', $error_msg, 'error');
		return;
	}

	$tag_ids = array_map('absint', $_POST['vote_tags'] ?? []);
	$tag_ids = array_filter($tag_ids);
	fi_vote_tags_set_tags($saved_id, $tag_ids);

	fi_cache_clear('votes');
	add_settings_error('fi_votes', 'vote_saved', 'Vote saved successfully.', 'updated');

	wp_safe_redirect(fi_admin_edit_vote_url($saved_id));
	exit;
}

/**
 * Detect whether a vote's meta column is a JSON string (i.e., was double-encoded).
 */
function fi_admin_votes_meta_is_json_string(int $vote_id): bool {
	global $wpdb;

	$type = $wpdb->get_var($wpdb->prepare(
		"SELECT JSON_TYPE(meta) FROM {$wpdb->prefix}fi_votes WHERE id = %d LIMIT 1",
		$vote_id
	));

	return strtoupper((string) $type) === 'STRING';
}

/**
 * Normalize fi_votes.meta JSON for one vote (or all votes) by converting JSON strings into JSON objects.
 * This fixes double-encoded Legiscan meta at the data layer (no runtime decoding hacks).
 */
function fi_admin_votes_handle_fix_meta_json(): void {
	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die('Insufficient permissions');
	}

	check_admin_referer('fi_votes_fix_meta_json');

	global $wpdb;
	$vote_id = absint($_POST['vote_id'] ?? 0);

	if ($vote_id) {
		$sql = "UPDATE {$wpdb->prefix}fi_votes
			SET meta = CAST(JSON_UNQUOTE(meta) AS JSON)
			WHERE id = %d
				AND JSON_TYPE(meta) = 'STRING'
				AND JSON_VALID(JSON_UNQUOTE(meta))";
		$wpdb->query($wpdb->prepare($sql, $vote_id));

		$redirect = fi_admin_edit_vote_url($vote_id, ['meta_fixed' => 1]);
		wp_safe_redirect($redirect);
		exit;
	}

	// Bulk: fix any votes with JSON_TYPE(meta)='STRING'
	$sql = "UPDATE {$wpdb->prefix}fi_votes
		SET meta = CAST(JSON_UNQUOTE(meta) AS JSON)
		WHERE JSON_TYPE(meta) = 'STRING'
			AND JSON_VALID(JSON_UNQUOTE(meta))";
	$wpdb->query($sql);

	wp_safe_redirect(fi_admin_url('fi-votes', ['meta_fixed' => 1]));
	exit;
}
add_action('admin_post_fi_votes_fix_meta_json', 'fi_admin_votes_handle_fix_meta_json');

/**
 * Default vote stub
 */
function fi_admin_votes_get_defaults(array $scope): array {
	return [
		'id' => null,
		'session_id' => $scope['session_id'] ?? null,
		'gov' => $scope['gov'] ?? null,
		'title' => '',
		'slug' => '',
		'bill_number' => '',
		'rollcall_number' => '',
		'chamber' => '',
		'constitutional' => 'U',
		'status' => 'publish',
		'date_voted' => '',
		'meta' => [],
	];
}

/**
 * Structured meta field definitions for votes
 */
function fi_admin_votes_get_meta_fields(): array {
	return [
		'bill_title' => [
			'label' => 'Bill Title (from Legiscan)',
			'type' => 'text',
			'cols' => 'col-12',
			'help' => 'Official bill title from Legiscan',
		],
		'bill_description' => [
			'label' => 'Bill Description (from Legiscan)',
			'type' => 'textarea',
			'cols' => 'col-12',
			'help' => 'Official bill description from Legiscan',
		],
		'vote_outcome' => [
			'label'   => 'Vote Outcome',
			'type'    => 'radio-group',
			'options' => ['1' => 'Passed', '0' => 'Rejected'],
			'cols'    => 'col-md-3',
			'help'    => '',
		],
		'citation' => [
			'label' => 'Constitutional Citation',
			'type'  => 'multiselect',
			'cols'  => 'col-md-5',
		],
		'url_bill' => [
			'label' => 'Bill URL',
			'type' => 'url',
			'cols' => 'col-md-6',
		],
		'url_rollcall' => [
			'label' => 'Roll-call URL',
			'type' => 'url',
			'cols' => 'col-md-6',
		],
		'url_legiscan' => [
			'label' => 'Legiscan Bill URL',
			'type' => 'url',
			'cols' => 'col-12',
			'hidden' => true, // Hidden field, preserved but not displayed
		],
		'cost' => [
			'label' => 'Cost/Impact',
			'type' => 'text',
			'cols' => 'col-md-6',
		],
		'impact_summary' => [
			'label'           => 'Impact Summary',
			'type'            => 'wysiwyg',
			'cols'            => 'col-12',
			'help'            => "Answer the user's question: Why should this matter to me?",
			'editor_settings' => [
				'textarea_rows' => 3,
				'media_buttons' => false,
				'teeny'         => true,
				'tinymce'       => ['height' => 75],
			],
		],
		'description_short' => [
			'label' => 'Short Description (legacy)',
			'type' => 'wysiwyg',
			'cols' => 'col-12',
			'help' => 'Brief summary used in cards.',
			'editor_settings' => [
				'textarea_rows' => 3,
				'media_buttons' => false,
				'teeny' => true,
				'tinymce' => [
					'height' => 75,
				],
			],
		],
		'description_medium' => [
			'label' => 'Medium Description',
			'type' => 'wysiwyg',
			'cols' => 'col-12',
			'editor_settings' => [
				'textarea_rows' => 6,
				'media_buttons' => false,
				'teeny' => false,
				'tinymce' => [
					'height' => 150,
				],
			],
		],
		'description_long' => [
			'label' => 'Long Description',
			'type' => 'wysiwyg',
			'cols' => 'col-12',
			'editor_settings' => [
				'textarea_rows' => 10,
				'media_buttons' => false,
				'teeny' => false,
				'tinymce' => [
					'height' => 250,
				],
			],
		],
		'image_id' => [
			'label' => 'Image',
			'type' => 'image_id',
			'cols' => 'col-12',
			'help' => 'Optional image for this vote (e.g. for cards); image processor will fetch and right-size.',
		],
	];
}


/**
 * Get tag options for the vote form
 */
function fi_admin_votes_get_tag_options(?string $gov): array {
	$options = [];
	foreach (fi_vote_tags_get_tag_counts($gov, null) as $tag) {
		$label = (string) ($tag['name'] ?? $tag['slug'] ?? '');
		if ($label === '') {
			continue;
		}
		$options[(int) $tag['id']] = sprintf('%s (%d)', $label, (int) ($tag['vote_count'] ?? 0));
	}
	return $options;
}

/**
 * Extra meta not surfaced in the form
 */
function fi_admin_votes_get_extra_meta(array $vote, array $meta_fields): array {
	$meta = fi_admin_votes_decode_meta($vote);
	if (empty($meta)) {
		return [];
	}

	$exclude = array_merge(array_keys($meta_fields), fi_admin_votes_get_preserved_meta_keys());
	return array_diff_key($meta, array_flip($exclude));
}

/**
 * Vote status options
 */
function fi_admin_votes_get_status_options(): array {
	return fi_vote_get_status_options();
}

/**
 * Decode vote meta safely
 */
function fi_admin_votes_decode_meta(array $vote): array {
	return fi_vote_decode_meta($vote);
}

/**
 * Meta keys that we preserve even if they are not exposed as editable form fields.
 * These should NOT appear in "Additional Meta" since they are first-class data used elsewhere in admin UI.
 */
function fi_admin_votes_get_preserved_meta_keys(): array {
	return [
		'vote_title',
		'votes_yea',
		'votes_nay',
		'votes_nv',
		'votes_absent',
		'votes_total',
		'legiscan_rollcall_audit',
		'legiscan_session_id',
		'legiscan', // Sub-array for unmapped Legiscan data
	];
}

/**
 * Build meta payload merging existing values
 */
function fi_admin_votes_build_meta_payload(?int $vote_id, array $meta_fields, array $submitted): array {
	$meta = [];

	if ($vote_id) {
		$existing = fi_vote_get($vote_id);
		if ($existing) {
			$meta = fi_admin_votes_decode_meta($existing);
		}
	}

	$preserved_meta_keys = fi_admin_votes_get_preserved_meta_keys();

	// Only process fields that are actually submitted in POST
	// This preserves existing meta values that aren't in the form
	foreach ($submitted as $meta_key => $raw) {
		// Process if it's a known meta field OR a preserved meta key
		if (isset($meta_fields[$meta_key])) {
			$config = $meta_fields[$meta_key];
			// image_id: store as int; omit when 0
			if (($config['type'] ?? '') === 'image_id') {
				$val = absint($raw);
				if ($val === 0) {
					unset($meta[$meta_key]);
				} else {
					$meta[$meta_key] = $val;
				}
				continue;
			}
			// multiselect: validate each value against the known flat list; preserves case.
			if (($config['type'] ?? '') === 'multiselect') {
				$valid_keys = function_exists('fi_constitution_links_flat') ? array_keys(fi_constitution_links_flat()) : [];
				$raw_arr    = is_array($raw) ? $raw : [];
				$keys       = array_values(array_filter($raw_arr, fn($v) => in_array($v, $valid_keys, true)));
				if (empty($keys)) {
					unset($meta[$meta_key]);
				} else {
					$meta[$meta_key] = $keys;
				}
				continue;
			}
			$clean = fi_admin_helpers_sanitize_field_value($config['type'] ?? 'text', (string) $raw);

			if ($clean === '') {
				unset($meta[$meta_key]);
			} else {
				$meta[$meta_key] = $clean;
			}
		} elseif (in_array($meta_key, $preserved_meta_keys, true)) {
			// Handle preserved meta keys
			if ($meta_key === 'legiscan') {
				// Decode JSON if it's a string
				if (is_string($raw)) {
					$decoded = json_decode($raw, true);
					if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
						$meta[$meta_key] = $decoded;
					} elseif ($raw !== '') {
						$meta[$meta_key] = $raw; // Keep as string if decode fails
					}
				} elseif (is_array($raw)) {
					$meta[$meta_key] = $raw;
				}
			} else {
				// For numeric fields (votes_*), ensure they're integers
				if (in_array($meta_key, ['votes_yea', 'votes_nay', 'votes_nv', 'votes_absent', 'votes_total', 'legiscan_session_id'], true)) {
					$meta[$meta_key] = $raw !== '' ? (int) $raw : null;
					if ($meta[$meta_key] === null) {
						unset($meta[$meta_key]);
					}
				} else {
					// For text fields (vote_title), sanitize
					$clean = sanitize_text_field((string) $raw);
					if ($clean === '') {
						unset($meta[$meta_key]);
					} else {
						$meta[$meta_key] = $clean;
					}
				}
			}
		}
		// Ignore unknown keys that aren't preserved
	}

	return $meta;
}

/**
 * Tag options for filter select (session scoped)
 */
function fi_admin_votes_get_filter_tags(array $scope): array {
	$tags = fi_vote_tags_get_tag_counts($scope['gov'] ?? null, $scope['session_id'] ?? null);
	$options = [];

	foreach ($tags as $tag) {
		$label = $tag->name ?? $tag->slug ?? '';
		if ($label === '') {
			continue;
		}
		$options[$tag->id] = sprintf('%s (%d)', $label, (int) ($tag->vote_count ?? 0));
	}

	return $options;
}

/**
 * Apply search/constitutional filters to vote arrays
 */
function fi_admin_votes_apply_collection_filters(array $votes, array $filters): array {
	if (empty($filters['search']) && empty($filters['constitutional'])) {
		return $votes;
	}

	$search = strtolower($filters['search'] ?? '');
	$constitutional = $filters['constitutional'] ?? '';

	return array_values(array_filter($votes, static function ($vote) use ($search, $constitutional) {
		if ($constitutional && strtoupper($vote['constitutional'] ?? '') !== $constitutional) {
			return false;
		}

		if ($search) {
			$haystack = strtolower(
				($vote['title'] ?? '') . ' ' .
				($vote['bill_number'] ?? '') . ' ' .
				($vote['slug'] ?? '')
			);

			if (!str_contains($haystack, $search)) {
				return false;
			}
		}

		return true;
	}));
}


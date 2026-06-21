<?php
/*
 * Freedom Index User Lists Table I/O Operations
 *
 * Straight function version of the former FICore\Lists class file.
 * Handles all database operations and rendering helpers for fi_user_lists.
 *
 * Notes:
 * - Slug generation is intentionally omitted. List URLs should be ID-based.
Refactored the user lists file into straight functions.

Key adjustments:

Removed the FICore\Lists class/namespace wrapper.
Preserved the existing public API:
fi_lists_get()
fi_list_get_by_id()
fi_lists_get_by_user()
fi_list_get_legislators()
fi_list_create()
fi_list_save()
fi_list_delete()
fi_lists_stats()
fi_list_render_legislators()
Added:
fi_list_normalize_legislators_for_storage()
fi_list_db_formats()
fi_list_check_duplicates()
fi_list_validate_data()
Removed slug generation because list URLs should remain ID-based.
Fixed a bug in fi_list_get_legislators(): the original could reach return fi_legislators_get_by_ids($legislator_ids, true); with $legislator_ids undefined. 
*/

if (!defined('ABSPATH')) exit;

/**
 * Get lists with optional filtering.
 *
 * @param array $args Query args.
 * @return array|int Array of list objects or count if count=true.
 */
function fi_lists_get(array $args = []): array|int {
	global $wpdb;

	$defaults = [
		'id'       => null,
		'user_id'  => null,
		'orderby'  => 'date_created',
		'order'    => 'DESC',
		'per_page' => -1,
		'page'     => 1,
		'count'    => false,
	];

	$args = wp_parse_args($args, $defaults);

	$where_conditions = [];
	$where_values = [];

	if (!empty($args['id'])) {
		$where_conditions[] = 'id = %d';
		$where_values[] = absint($args['id']);
	}

	if (!empty($args['user_id'])) {
		$where_conditions[] = 'user_id = %d';
		$where_values[] = absint($args['user_id']);
	}

	$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

	if (!empty($args['count'])) {
		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}fi_user_lists {$where_clause}";
		$prepared = !empty($where_values) ? $wpdb->prepare($sql, $where_values) : $sql;
		return (int) $wpdb->get_var($prepared);
	}

	$allowed_orderby = ['id', 'user_id', 'name', 'date_created', 'date_updated', 'is_public'];
	$orderby_field = sanitize_key((string) $args['orderby']);
	if (!in_array($orderby_field, $allowed_orderby, true)) {
		$orderby_field = 'date_created';
	}

	$order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';
	$orderby = "{$orderby_field} {$order}";

	$limit_clause = '';
	if ((int) $args['per_page'] > 0) {
		$limit = absint($args['per_page']);
		$page = max(1, absint($args['page']));
		$offset = ($page - 1) * $limit;
		$limit_clause = $wpdb->prepare('LIMIT %d OFFSET %d', $limit, $offset);
	}

	$sql = "
		SELECT * FROM {$wpdb->prefix}fi_user_lists
		{$where_clause}
		ORDER BY {$orderby}
		{$limit_clause}
	";

	$prepared = !empty($where_values) ? $wpdb->prepare($sql, $where_values) : $sql;

	return $wpdb->get_results($prepared, ARRAY_A);
}

/**
 * Get a single list by ID.
 *
 * @param int $list_id List ID.
 * @return object|null
 */
function fi_list_get_by_id(int $list_id): ?array {
	$results = fi_lists_get([
		'id'       => $list_id,
		'per_page' => 1,
	]);

	return is_array($results) ? ($results[0] ?? null) : null;
}

/**
 * Get all lists for a user.
 *
 * @param int $user_id User ID.
 * @return array
 */
function fi_lists_get_by_user(int $user_id): array {
	$results = fi_lists_get([
		'user_id'  => $user_id,
		'orderby'  => 'date_created',
		'order'    => 'DESC',
		'per_page' => -1,
	]);

	return is_array($results) ? $results : [];
}

/**
 * Normalize legislators field for storage.
 *
 * @param mixed $raw Legislator IDs as array, JSON string, or empty value.
 * @return string|null JSON string or null.
 */
function fi_list_normalize_legislators_for_storage($raw): ?string {
	if (is_array($raw)) {
		$ids = array_values(array_filter(array_map('absint', $raw)));
		return !empty($ids) ? wp_json_encode($ids) : null;
	}

	if (is_string($raw) && $raw !== '') {
		$decoded = json_decode($raw, true);
		if (is_array($decoded)) {
			$ids = array_values(array_filter(array_map('absint', $decoded)));
			return !empty($ids) ? wp_json_encode($ids) : null;
		}

		return $raw;
	}

	return null;
}

/**
 * Build dynamic database format specifiers for fi_user_lists writes.
 *
 * @param array $db_data Data being written.
 * @return array Format specifiers.
 */
function fi_list_db_formats(array $db_data): array {
	$formats = [];

	foreach ($db_data as $key => $value) {
		$formats[] = in_array($key, ['user_id', 'is_public'], true) ? '%d' : '%s';
	}

	return $formats;
}

/**
 * Save or update list.
 *
 * @param array $data List data.
 * @param int|null $list_id Existing list ID.
 * @return int|false List ID on success, false on failure.
 */
function fi_list_save(array $data, ?int $list_id = null): int|false {
	global $wpdb;

	if (empty($data['user_id']) || empty($data['name'])) {
		return false;
	}

	$db_data = [
		'user_id' => absint($data['user_id']),
		'name'    => sanitize_text_field($data['name']),
	];

	if (array_key_exists('description', $data)) {
		$db_data['description'] = $data['description'] !== '' && $data['description'] !== null
			? wp_kses_post($data['description'])
			: null;
	}

	if (array_key_exists('legislators', $data)) {
		$db_data['legislators'] = fi_list_normalize_legislators_for_storage($data['legislators']);
	}

	if (array_key_exists('is_public', $data)) {
		$db_data['is_public'] = !empty($data['is_public']) ? 1 : 0;
	}

	if (array_key_exists('meta', $data)) {
		$db_data['meta'] = !empty($data['meta'])
			? (is_array($data['meta']) ? wp_json_encode($data['meta']) : (string) $data['meta'])
			: null;
	}

	$formats = fi_list_db_formats($db_data);

	if ($list_id) {
		$result = $wpdb->update(
			$wpdb->prefix . 'fi_user_lists',
			$db_data,
			['id' => $list_id],
			$formats,
			['%d']
		);

		return $result !== false ? $list_id : false;
	}

	$result = $wpdb->insert(
		$wpdb->prefix . 'fi_user_lists',
		$db_data,
		$formats
	);

	return $result !== false ? (int) $wpdb->insert_id : false;
}

/**
 * Delete list.
 *
 * @param int $list_id List ID.
 * @return bool
 */
function fi_list_delete(int $list_id): bool {
	global $wpdb;

	$list = fi_list_get_by_id($list_id);
	if (!$list) {
		return false;
	}

	$result = $wpdb->delete(
		$wpdb->prefix . 'fi_user_lists',
		['id' => $list_id],
		['%d']
	);

	return $result !== false;
}

/**
 * Check for duplicate lists.
 *
 * Kept for API compatibility. Current list URLs are ID-based and do not require slug checks.
 *
 * @param array $data List data.
 * @param int|null $exclude_id Excluded list ID.
 * @return array{is_duplicate:bool,existing_id:int|null}
 */
function fi_list_check_duplicates(array $data, ?int $exclude_id = null): array {
	return [
		'is_duplicate' => false,
		'existing_id'   => null,
	];
}

/**
 * Get list statistics.
 *
 * @param int|null $user_id Optional user ID.
 * @return array
 */
function fi_lists_stats(?int $user_id = null): array {
	global $wpdb;

	$where_clause = $user_id ? 'WHERE user_id = %d' : '';
	$values = $user_id ? [$user_id] : [];

	$sql = "
		SELECT
			COUNT(*) as total,
			COUNT(CASE WHEN is_public = 1 THEN 1 END) as public_lists
		FROM {$wpdb->prefix}fi_user_lists
		{$where_clause}
	";

	if (!empty($values)) {
		$sql = $wpdb->prepare($sql, $values);
	}

	$result = $wpdb->get_row($sql, ARRAY_A);

	return is_array($result) ? $result : [
		'total'        => 0,
		'public_lists' => 0,
	];
}

/**
 * Validate list data.
 *
 * @param array $data List data.
 * @return array{valid:bool,errors:array}
 */
function fi_list_validate_data(array $data): array {
	$errors = [];

	if (empty($data['user_id'])) {
		$errors[] = 'User ID is required';
	}

	if (empty($data['name'])) {
		$errors[] = 'List name is required';
	}

	return [
		'valid'  => empty($errors),
		'errors' => $errors,
	];
}

/**
 * Get list and return legislators.
 *
 * @param mixed $list_param List ID or compatible numeric param.
 * @return array Legislator objects.
 */
function fi_list_get_legislators($list_param): array {
	$legislator_ids = [];

	if (is_numeric($list_param)) {
		$list = fi_list_get_by_id((int) $list_param);

		if ($list && !empty($list['legislators'])) {
			$decoded = json_decode($list['legislators'], true);
			if (is_array($decoded)) {
				$legislator_ids = array_values(array_filter(array_map('absint', $decoded)));
			}
		}
	}

	if (empty($legislator_ids)) {
		return [];
	}

	return fi_legislators_get_by_ids($legislator_ids, true);
}

/**
 * Create-list logic. Returns success payload or error payload; caller sends JSON.
 *
 * @param string $name List name.
 * @param array $legislator_ids Legislator IDs.
 * @return array Success or error payload.
 */
function fi_list_create(string $name, array $legislator_ids = []): array {
	global $wpdb;

	$user_id = get_current_user_id();
	$name = trim($name);

	if ($user_id <= 0) {
		return ['message' => 'You must be logged in to create a list.'];
	}

	if ($name === '') {
		return ['message' => 'List name required'];
	}

	$existing = $wpdb->get_var($wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}fi_user_lists WHERE user_id = %d AND name = %s",
		$user_id,
		$name
	));

	if ($existing) {
		return ['message' => 'A list with this name already exists.'];
	}

	$legislator_ids = array_values(array_filter(array_map('absint', $legislator_ids)));

	if (function_exists('fi_log')) {
		fi_log('fi_list_create: NAME: ' . $name . ' | LEGISLATORS: ' . wp_json_encode($legislator_ids), __FILE__, __LINE__);
	}

	$list_id = fi_list_save([
		'user_id'     => $user_id,
		'name'        => $name,
		'legislators' => $legislator_ids,
	]);

	if ($list_id) {
		return [
			'list_id' => $list_id,
			'url'     => home_url('/account/lists/' . $list_id . '/'),
			'message' => 'List created successfully',
		];
	}

	return [
		'message' => 'Failed to create list.',
		'debug'   => (defined('WP_DEBUG') && WP_DEBUG && !empty($wpdb->last_error)) ? $wpdb->last_error : null,
	];
}

/**
 * Render legislator cards for a user list.
 *
 * @param array $legislators Legislator objects/arrays from fi_legislators_get_by_ids().
 * @param int $list_id List ID for remove functionality.
 * @param string $class_col Bootstrap column classes.
 * @return string HTML output.
 */
function fi_list_render_legislators(array $legislators, int $list_id, string $class_col = 'col-12 col-md-6 p-3'): string {
	if (empty($legislators)) {
		return '';
	}

	$list_obj = fi_list_get_by_id($list_id);
	$can_edit = is_user_logged_in() && $list_obj && (int) get_current_user_id() === (int) ($list_obj['user_id'] ?? 0);
	$party_list = function_exists('fi_parties') ? fi_parties() : [];

	ob_start();
	?>
	<div class="row g-2">
		<?php foreach ($legislators as $legislator): ?>
			<?php
			$leg_id = (int) ($legislator['id'] ?? 0);
			$name   = $legislator['display_name'] ?? trim(($legislator['first_name'] ?? '') . ' ' . ($legislator['last_name'] ?? ''));
			$gov    = $legislator['gov'] ?? '';

			$chamber  = '';
			$district = '';
			$state    = '';
			if (!empty($legislator['sessions'])) {
				$latest   = end($legislator['sessions']);
				$chamber  = $latest['chamber']  ?? '';
				$district = $latest['district'] ?? '';
				$state    = $latest['state']    ?? '';
			} else {
				$chamber  = $legislator['chamber']  ?? '';
				$district = $legislator['district'] ?? '';
				$state    = $legislator['state']    ?? '';
			}

			$freedom_score = $legislator['freedom_score'] ?? null;
			if (is_array($freedom_score)) {
				$freedom_score = $freedom_score['score'] ?? null;
			}

			$leg_array = [
				'id'               => $leg_id,
				'display_name'     => $name,
				'party'            => $legislator['party'] ?? '',
				'gov'              => $gov,
				'chamber'          => $chamber,
				'state'            => $state,
				'state_name'       => $legislator['state_name'] ?? '',
				'district'         => $district,
				'district_name'    => $legislator['district_name'] ?? '',
				'image_id'         => $legislator['image_id'] ?? null,
				'session_image_id' => $legislator['session_image_id'] ?? null,
				'score'            => $freedom_score,
			];
			?>
			<div class="<?php echo esc_attr($class_col); ?> position-relative">
				<?php if ($can_edit && $leg_id): ?>
					<button type="button"
						class="btn btn-sm btn-outline-danger p-1 bg-white position-absolute top-0 start-0 m-1"
						style="z-index:1;"
						onclick="if(confirm('Remove <?php echo esc_js($name); ?> from this list?')) { FI.removeLegislatorFromList(<?php echo (int) $list_id; ?>, <?php echo $leg_id; ?>); }"
						title="Remove from list">
						<i class="bi bi-x-lg"></i>
					</button>
				<?php endif; ?>
				<?php fi_get_template('legislators-card', ['legislator' => $leg_array, 'gov' => $gov]); ?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

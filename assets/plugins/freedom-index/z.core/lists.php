<?php
namespace FI\Core{

	if (!defined('ABSPATH')) exit;

	/**
	* User Lists Table I/O Operations
	* All database operations for the fi_user_lists table.
	* IMPORTANT: Public Lists will have 
	*/
	final class Lists {

		/**
		* Get lists with optional filtering
		*/
		public static function get(array $args = []): array|int {
			global $wpdb;
			
			$defaults = [
				'id' => null,
				'user_id' => null,
				'orderby' => 'date_created',
				'order' => 'DESC',
				'per_page' => -1,
				'page' => 1,
				'count' => false,
			];
			
			$args = wp_parse_args($args, $defaults);
			
			$where_conditions = [];
			$where_values = [];
			
			if (!empty($args['id'])) {
				$where_conditions[] = 'id = %d';
				$where_values[] = (int) $args['id'];
			}
			
			if (!empty($args['user_id'])) {
				$where_conditions[] = 'user_id = %d';
				$where_values[] = (int) $args['user_id'];
			}
			
			$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
			
			if ($args['count']) {
				$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}fi_user_lists {$where_clause}";
				$prepared = !empty($where_values) ? $wpdb->prepare($sql, $where_values) : $sql;
				return (int) $wpdb->get_var($prepared);
			}
			
			$orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
			if (!$orderby) {
				$orderby = 'date_created DESC';
			}
			
			$limit_clause = '';
			if ((int) $args['per_page'] > 0) {
				$limit = (int) $args['per_page'];
				$offset = max(0, ((int) $args['page'] - 1) * $limit);
				$limit_clause = $wpdb->prepare("LIMIT %d OFFSET %d", $limit, $offset);
			}
			
			$sql = "
				SELECT * FROM {$wpdb->prefix}fi_user_lists 
				{$where_clause} 
				ORDER BY {$orderby} 
				{$limit_clause}
			";

			$prepared = !empty($where_values) ? $wpdb->prepare($sql, $where_values) : $sql;
			return $wpdb->get_results($prepared);
		}

		/**
		* Get user lists
		*/
		public static function get_by_user(int $user_id): array {
			return self::get([
				'user_id' => $user_id,
				'orderby' => 'date_created',
				'order' => 'DESC',
				'per_page' => -1,
			]);
		}

		/**
		* Get a single list by ID
		*/
		public static function get_by_id(int $list_id): ?object {
			$results = self::get(['id' => $list_id, 'per_page' => 1]);
			return $results[0] ?? null;
		}

		/**
		* Save/Update list
		*/
		public static function save(array $data, ?int $list_id = null): int|false {
			global $wpdb;
			
			// Validate required fields
			if (empty($data['user_id']) || empty($data['name'])) {
				return false;
			}
			
			// Prepare data for database - only include fields that were explicitly provided (Sessions pattern)
			$db_data = [];
			$db_data['user_id'] = (int) $data['user_id'];
			$db_data['name'] = $data['name'];
			
			// Optional fields - only include if key exists in input (allows clearing via empty/null)
			if (array_key_exists('description', $data)) {
				$db_data['description'] = $data['description'] ?: null;
			}

			if (array_key_exists('legislators', $data)) {
				$raw = $data['legislators'];
				if (is_array($raw)) {
					$db_data['legislators'] = count($raw) > 0 ? json_encode($raw) : null;
				} elseif (is_string($raw) && $raw !== '') {
					$decoded = json_decode($raw, true);
					$db_data['legislators'] = is_array($decoded) ? json_encode($decoded) : $raw;
				} else {
					$db_data['legislators'] = null;
				}
			}

			if (array_key_exists('is_public', $data)) {
				$db_data['is_public'] = !empty($data['is_public']) ? 1 : 0;
			}
			if (array_key_exists('meta', $data)) {
				$db_data['meta'] = !empty($data['meta']) ? (is_array($data['meta']) ? json_encode($data['meta']) : $data['meta']) : null;
			}
			
			// Build format specifiers dynamically based on what's in $db_data (Sessions pattern)
			$formats = [];
			foreach ($db_data as $key => $value) {
				if (in_array($key, ['user_id', 'is_public'])) {
					$formats[] = '%d';
				} else {
					$formats[] = '%s';
				}
			}
			
			if ($list_id) {
				// Update existing
				$result = $wpdb->update(
					$wpdb->prefix . 'fi_user_lists',
					$db_data,
					['id' => $list_id],
					$formats,
					['%d']
				);
				return $result !== false ? $list_id : false;
			} else {
				// Insert new
				$result = $wpdb->insert(
					$wpdb->prefix . 'fi_user_lists',
					$db_data,
					$formats
				);
				if ($result !== false) {
					return (int) $wpdb->insert_id;
				}
				return false;
			}
		}

		/**
		* Update list
		*/
/* DEPRECATED: THIS SHOULD NOT BE USED - Verify and replace with save()
		public static function update(int $list_id, string $name, array $legislators): bool {
			global $wpdb;
			
			$result = $wpdb->update(
				$wpdb->prefix . 'fi_user_lists',
				[
					'name' => $name,
					'legislators' => json_encode($legislators)
				],
				['id' => $list_id],
				['%s', '%s'],
				['%d']
			);
			
			return $result !== false;
		}
*/
		/**
		* Delete list
		*/
		public static function delete(int $list_id): bool {
			global $wpdb;
			
			// Check if list exists
			$list = self::get_by_id($list_id);
			if (!$list) {
				return false;
			}
			
			// Delete list
			$result = $wpdb->delete($wpdb->prefix . 'fi_user_lists', ['id' => $list_id]);
			
			return $result !== false;
		}

		/**
		* Check for duplicate lists
		*/
		public static function check_duplicates(array $data, ?int $exclude_id = null): array {
			// Slug is now dynamically generated, so no need to check for duplicate slugs
			// This method is kept for API compatibility but always returns no duplicates
			return [
				'is_duplicate' => false,
				'existing_id' => null
			];
		}

		/**
		* Generate slug in format {user_id}{listid}
		* This ensures non-sequential, non-guessable URLs
		*/
		public static function generate_slug(int $user_id, int $list_id): string {
			return (string) $user_id . (string) $list_id;
		}

		/**
		* Get list statistics
		*/
		public static function get_stats(?int $user_id = null): array {
			global $wpdb;
			
			$where_clause = $user_id ? "WHERE user_id = %d" : "";
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
			
			return (array) $wpdb->get_row($sql);
		}

		/**
		* Validate list data
		*/
		public static function validate_data(array $data): array {
			$errors = [];
			
			// Required fields
			if (empty($data['user_id'])) {
				$errors[] = 'User ID is required';
			}
			
			if (empty($data['name'])) {
				$errors[] = 'List name is required';
			}
			
			
			return [
				'valid' => empty($errors),
				'errors' => $errors
			];
		}
	}
}

//namespace for global functions
namespace {

	/* Get user lists */
	function fi_lists_get(array $args = []): array|int {
		return \FI\Core\Lists::get($args);
	}

	/* Get list by ID */
	function fi_list_get_by_id(int $list_id): ?object {
		return \FI\Core\Lists::get_by_id($list_id);
	}

	/* Get user lists */
	function fi_lists_get_by_user(int $user_id): array {
		return \FI\Core\Lists::get_by_user($user_id);
	}

	/* Get list and return legislators */
	function fi_list_get_legislators($list_param): array {
		global $wpdb;
		
		// Check if it's a saved list (slug format: {user_id}{list_id})
		// Try to parse as numeric slug first
		if (is_numeric($list_param) && strlen($list_param) > 1) {
			// Try to find matching list by iterating through possible user_id/list_id combinations
			// For now, query by list_id if it's a pure numeric value
			$list_id = intval($list_param);
			$list = fi_list_get_by_id($list_id);
			
			if ($list) {
				$legislator_ids = json_decode($list->legislators, true);
				if (!empty($legislator_ids) && is_array($legislator_ids)) {
					return fi_legislators_get_by_ids($legislator_ids, true);
				}
			}
		}

		return fi_legislators_get_by_ids($legislator_ids, true);
	}


	/**
	* Create-list logic. Returns success payload or error payload; caller sends JSON.
	* @param string $name List name
	* @param array $legislator_ids Array of legislator IDs | Used by modal to prefill legislators
	* @return array ['list_id' => int, 'url' => string, 'message' => string] or ['message' => string, 'debug' => ?string]
	*/
	function fi_list_create(string $name, array $legislator_ids = []): array {
		$user_id = get_current_user_id();
		global $wpdb;

		if (strlen($name) === 0) {
			return ['message' => 'List name required'];
		}
		//Check for duplicate user+name combination
		$existing = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}fi_user_lists WHERE user_id = %d AND name = %s",
			$user_id, $name
		));
		if ($existing) {
			return ['message' => 'A list with this name already exists.'];
		}
		//NULL acceptable. Ensure array values are all integers. Encode to JSON.
		$legislator_ids = array_map('intval', $legislator_ids);
		$legislator_ids = is_array($legislator_ids) ? json_encode($legislator_ids) : null;
		if (empty($legislator_ids)) {
			$legislator_ids = null;
		}

fi_log('fi_list_create: NAME: '.$name.' | LEGISLATORS: '.json_encode($legislator_ids),__FILE__,__LINE__);

		$list_id = fi_list_save([
			'user_id' => $user_id,
			'name' => $name,
			'legislators' => $legislator_ids
		]);
		if ($list_id) {
			return [
				'list_id' => $list_id,
				'url' => home_url('/account/lists/' . $list_id . '/'),
				'message' => 'List created successfully',
			];
		}
		return [
			'message' => 'Failed to create list.',
			'debug' => (defined('WP_DEBUG') && WP_DEBUG && !empty($wpdb->last_error)) ? $wpdb->last_error : null,
		];
	}


	/* Save/Update list */
	function fi_list_save(array $data, ?int $list_id = null): int|false {
		return \FI\Core\Lists::save($data, $list_id);
	}

	/* Delete list */
	function fi_list_delete(int $list_id): bool {
		return \FI\Core\Lists::delete($list_id);
	}

	/* Get list statistics */
	function fi_lists_stats(?int $user_id = null): array {
		return \FI\Core\Lists::get_stats($user_id);
	}

	/**
	 * Render legislator cards for a user list using the existing legislator-card-sm template
	 * 
	 * @param array $legislators Array of legislator objects from fi_legislators_get_by_ids()
	 * @param int $list_id List ID for remove functionality
	 * @return string HTML output
	 */
	function fi_list_render_legislators(array $legislators, int $list_id,string $class_col = 'col-12 col-md-6 p-3'): string {
		if (empty($legislators)) {
			return '';
		}
		
		// Check if current user owns this list (only show remove button if owner)
		$list_obj = fi_list_get_by_id($list_id);
		$can_edit = is_user_logged_in() && $list_obj && get_current_user_id() == $list_obj->user_id;
		
		ob_start();
		?>
		<div class="row">
			<?php foreach ($legislators as $legislator): 
				// Normalize legislator data (handle both object and array formats)
				$leg_id = is_object($legislator) ? $legislator->id : $legislator['id'];
				$name = is_object($legislator) ? ($legislator->display_name ?? '') : ($legislator['display_name'] ?? '');
				$party = is_object($legislator) ? ($legislator->party ?? '') : ($legislator['party'] ?? '');
				$gov = is_object($legislator) ? ($legislator->gov ?? '') : ($legislator['gov'] ?? '');
				$party_list = fi_parties();
				$party = $party_list[$party]['name'] ?? $party;
				
				// Get chamber/district info from current session or latest session
				$chamber = '';
				$district = '';
				$state = '';
				if (is_object($legislator) && !empty($legislator->sessions)) {
					// Get latest session data
					$latest_session = end($legislator->sessions);
					$chamber = $latest_session->chamber ?? '';
					$district = $latest_session->district ?? '';
					$state = $latest_session->state ?? '';
				} elseif (isset($legislator['sessions']) && !empty($legislator['sessions'])) {
					$latest_session = end($legislator['sessions']);
					$chamber = $latest_session['chamber'] ?? '';
					$district = $latest_session['district'] ?? '';
					$state = $latest_session['state'] ?? '';
				}
				
				// Build chamber display
				if($gov) {
					$chamber = FI_CHAMBERS[$gov][$chamber]['title'] ?? $chamber;
					if($gov == 'US') {
						$gov_name = 'United States Congress';
					}else{
						$gov_name = FI_GOVERNMENTS[$gov];
					}
				}else{
					$gov_name = '';
				}
				$chamber_display = $chamber;
				if ($district && $state) {
					$chamber_display .= ' - ' . $state . ' ' . $district;
				} elseif ($state) {
					$chamber_display .= ' - ' . $state;
				}

				// Get legislator URL
				$legislator_url = fi_get_legislator_url($leg_id);
				
				// Get photo URL
				$photo_url = '';
				if (is_object($legislator) && !empty($legislator->image_id)) {
					$photo_url = wp_get_attachment_image_url($legislator->image_id, 'thumbnail');
				} elseif (isset($legislator['image_id']) && !empty($legislator['image_id'])) {
					$photo_url = wp_get_attachment_image_url($legislator['image_id'], 'thumbnail');
				}
				
				// Get freedom score
				$freedom_score = null;
				$score_label = 'Freedom Score';
				if (is_object($legislator) && isset($legislator->freedom_score)) {
					$freedom_score = is_array($legislator->freedom_score) ? ($legislator->freedom_score['score'] ?? null) : $legislator->freedom_score;
				} elseif (isset($legislator['freedom_score'])) {
					$freedom_score = is_array($legislator['freedom_score']) ? ($legislator['freedom_score']['score'] ?? null) : $legislator['freedom_score'];
				}
				
				// Prepare card args in the format expected by legislator-card-sm template
				$card_args = [
					'name' => $name,
					'party' => $party,
					'gov' => $gov,
					'gov_name' => $gov_name,
					'chamber' => $chamber_display,
					'photo_url' => $photo_url,
					'score' => $freedom_score,
					'score_label' => $score_label,
					'legislator' => [
						'id' => $leg_id,
						'url' => $legislator_url,
					],
					'class_col' => $class_col,
				];
				
				// Add list_id if user can edit (enables remove button)
				if ($can_edit) {
					$card_args['list_id'] = $list_id;
				}
				
				// Render using existing template
				fi_get_template('partials/legislator-card-sm', $card_args);
			?>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
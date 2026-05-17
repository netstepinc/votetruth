<?php
namespace FI\Core{

	if (!defined('ABSPATH')) exit;

	/*
	* Vote Tags Table I/O Operations
	* Handles vote ↔ tag relationships (fi_vote_tags junction table)
	*/
	final class VoteTags {

		/**
		* Get all tags for a vote
		* 
		* @param int $vote_id
		* @return array Array of tag objects
		*/
		public static function get_tags_by_vote(int $vote_id): array {
			global $wpdb;
			
			return $wpdb->get_results($wpdb->prepare(
				"SELECT t.* 
				FROM {$wpdb->prefix}fi_taxonomy t
				INNER JOIN {$wpdb->prefix}fi_vote_tags vt ON t.id = vt.tag_id
				WHERE vt.vote_id = %d
				ORDER BY t.name ASC",
				$vote_id
			));
		}

		/**
		* Get all votes with a specific tag
		* 
		* @param int $tag_id
		* @param array $filters Additional filters
		* @return array Array of vote objects
		*/
		public static function get_votes_by_tag(int $tag_id, array $filters = []): array {
			// Use the unified Votes::get() query builder (single source of truth for vote queries).
			// Note: Votes::get() defaults to published-only when status key is missing.
			$args = is_array($filters) ? $filters : [];
			$args['tag_id'] = $tag_id;

			return Votes::get($args);
		}

		/**
		* Add a tag to a vote
		* 
		* @param int $vote_id
		* @param int $tag_id
		* @return bool
		*/
		public static function add_tag(int $vote_id, int $tag_id): bool {
			global $wpdb;
			
			// Check if already exists
			$exists = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}fi_vote_tags 
				WHERE vote_id = %d AND tag_id = %d",
				$vote_id, $tag_id
			));
			
			if ($exists > 0) {
				return true; // Already exists, consider success
			}
			
			$result = $wpdb->insert(
				$wpdb->prefix . 'fi_vote_tags',
				[
					'vote_id' => $vote_id,
					'tag_id' => $tag_id
				],
				['%d', '%d']
			);
			
			return $result !== false;
		}

		/**
		* Remove a tag from a vote
		* 
		* @param int $vote_id
		* @param int $tag_id
		* @return bool
		*/
		public static function remove_tag(int $vote_id, int $tag_id): bool {
			global $wpdb;
			
			$result = $wpdb->delete(
				$wpdb->prefix . 'fi_vote_tags',
				[
					'vote_id' => $vote_id,
					'tag_id' => $tag_id
				],
				['%d', '%d']
			);
			
			return $result !== false;
		}

		/**
		* Set all tags for a vote (replace existing tags)
		* 
		* @param int $vote_id
		* @param array $tag_ids Array of tag IDs
		* @return bool
		*/
		public static function set_tags(int $vote_id, array $tag_ids): bool {
			global $wpdb;
			
			// Remove all existing tags
			self::delete_by_vote($vote_id);
			
			// Add new tags
			if (empty($tag_ids)) {
				return true;
			}
			
			$values = [];
			$placeholders = [];
			
			foreach ($tag_ids as $tag_id) {
				$placeholders[] = "(%d, %d)";
				$values[] = $vote_id;
				$values[] = absint($tag_id);
			}
			
			$placeholders_str = implode(', ', $placeholders);
			
			$sql = "
				INSERT INTO {$wpdb->prefix}fi_vote_tags (vote_id, tag_id) 
				VALUES {$placeholders_str}
			";
			
			return $wpdb->query($wpdb->prepare($sql, $values)) !== false;
		}

		/**
		* Delete all tags for a vote
		* 
		* @param int $vote_id
		* @return bool
		*/
		public static function delete_by_vote(int $vote_id): bool {
			global $wpdb;
			
			$result = $wpdb->delete(
				$wpdb->prefix . 'fi_vote_tags',
				['vote_id' => $vote_id],
				['%d']
			);
			
			return $result !== false;
		}

		/**
		* Delete all votes with a specific tag (when tag is deleted)
		* 
		* @param int $tag_id
		* @return bool
		*/
		public static function delete_by_tag(int $tag_id): bool {
			global $wpdb;
			
			$result = $wpdb->delete(
				$wpdb->prefix . 'fi_vote_tags',
				['tag_id' => $tag_id],
				['%d']
			);
			
			return $result !== false;
		}

		/**
		* Get tags for multiple vote IDs (bulk fetch)
		* Optimized for fetching all tags for a set of votes
		* 
		* @param array $vote_ids Array of vote IDs
		* @return array Array of tag objects with vote_id
		*/
		public static function get_tags_by_vote_ids(array $vote_ids): array {
			global $wpdb;
			
			if (empty($vote_ids)) {
				return [];
			}
			
			$vote_ids = array_map('intval', $vote_ids);
			$vote_ids = array_unique($vote_ids);
			$placeholders = implode(',', array_fill(0, count($vote_ids), '%d'));
			
			$sql = "
				SELECT 
					vt.vote_id,
					t.id as tag_id,
					t.name as tag_name,
					t.slug as tag_slug
				FROM {$wpdb->prefix}fi_vote_tags vt
				INNER JOIN {$wpdb->prefix}fi_taxonomy t ON vt.tag_id = t.id
				WHERE vt.vote_id IN ($placeholders)
				AND t.taxonomy = 'tag'
				ORDER BY vt.vote_id ASC, t.name ASC
			";
			
			return $wpdb->get_results($wpdb->prepare($sql, $vote_ids));
		}

		/**
		* Get tag counts (how many votes per tag)
		* 
		* @param string|null $gov Optional government filter
		* @param int|null $session_id Optional session filter
	* @param string $orderby Sort order: 'count' (default) or 'name'
		* @return array Array of tag objects with vote_count
		*/
	public static function get_tag_counts(?string $gov = null, ?int $session_id = null, string $orderby = 'count'): array {
			global $wpdb;
			
			$where_conditions = ["t.taxonomy = 'tag'"];
			$where_values = [];
			
			if ($gov) {
				$where_conditions[] = "t.gov = %s";
				$where_values[] = $gov;
			}
			
			$join_clause = "LEFT JOIN {$wpdb->prefix}fi_vote_tags vt ON t.id = vt.tag_id";
			
			if ($session_id) {
				$join_clause .= " LEFT JOIN {$wpdb->prefix}fi_votes v ON vt.vote_id = v.id";
				$where_conditions[] = "v.session_id = %d";
				$where_values[] = $session_id;
			}
			
			$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
			
		$orderby = strtolower(trim($orderby));
		$order_by_clause = 'ORDER BY vote_count DESC, t.name ASC';
		if ($orderby === 'name') {
			$order_by_clause = 'ORDER BY t.name ASC';
		}

		$sql = "
			SELECT t.*, COUNT(vt.vote_id) as vote_count
			FROM {$wpdb->prefix}fi_taxonomy t
			{$join_clause}
			{$where_clause}
			GROUP BY t.id
			HAVING vote_count > 0
			{$order_by_clause}
		";
			
			if (!empty($where_values)) {
				return $wpdb->get_results($wpdb->prepare($sql, $where_values));
			}
			
			return $wpdb->get_results($sql);
		}
	}
}

//namespace for global functions
namespace {
	/* Get all tags for a vote */
	function fi_vote_tags_get_tags_by_vote(int $vote_id): array {
		return \FI\Core\VoteTags::get_tags_by_vote($vote_id);
	}

	/* Get all votes with a specific tag */
	function fi_vote_tags_get_votes_by_tag(int $tag_id, array $filters = []): array {
		return \FI\Core\VoteTags::get_votes_by_tag($tag_id, $filters);
	}

	/* Add a tag to a vote */
	function fi_vote_tags_add_tag(int $vote_id, int $tag_id): bool {
		return \FI\Core\VoteTags::add_tag($vote_id, $tag_id);
	}

	/* Remove a tag from a vote */
	function fi_vote_tags_remove_tag(int $vote_id, int $tag_id): bool {
		return \FI\Core\VoteTags::remove_tag($vote_id, $tag_id);
	}

	/* Set all tags for a vote (replace existing tags) */	
	function fi_vote_tags_set_tags(int $vote_id, array $tag_ids): bool {
		return \FI\Core\VoteTags::set_tags($vote_id, $tag_ids);
	}

	/* Delete all tags for a vote */
	function fi_vote_tags_delete_by_vote(int $vote_id): bool {
		return \FI\Core\VoteTags::delete_by_vote($vote_id);
	}

	/* Delete all votes with a specific tag */
	function fi_vote_tags_delete_by_tag(int $tag_id): bool {
		return \FI\Core\VoteTags::delete_by_tag($tag_id);
	}

	/* Get tag counts (how many votes per tag) */
	function fi_vote_tags_get_tag_counts(?string $gov = null, ?int $session_id = null, string $orderby = 'count'): array {
		return \FI\Core\VoteTags::get_tag_counts($gov, $session_id, $orderby);
	}

	/* Get tags by vote IDs */
	function fi_vote_tags_get_tags_by_vote_ids(array $vote_ids): array {
		return \FI\Core\VoteTags::get_tags_by_vote_ids($vote_ids);
	}
}
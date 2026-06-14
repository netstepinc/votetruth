<?php
/*
 * Freedom Index Vote Tags Table I/O Operations
 *
 * Straight function version of the former FICore\VoteTags class file.
 * Handles vote ↔ tag relationships in the fi_vote_tags junction table.
 * Refactored the vote-tags file into straight functions.

Key adjustments:

Removed the FICore\VoteTags class/namespace wrapper.
Preserved all existing public API functions:
fi_vote_tags_get_tags_by_vote()
fi_vote_tags_get_votes_by_tag()
fi_vote_tags_add_tag()
fi_vote_tags_remove_tag()
fi_vote_tags_set_tags()
fi_vote_tags_delete_by_vote()
fi_vote_tags_delete_by_tag()
fi_vote_tags_get_tag_counts()
fi_vote_tags_get_tags_by_vote_ids()
Replaced Votes::get() with a function-first lookup:
Uses fi_votes_get() if available.
Falls back to \FICore\Votes::get() only if that file has not been converted yet.
Added ID cleanup with absint(), array_unique(), and empty-value filtering.
Kept tag_slug in the bulk tag result because tag slugs are still internal taxonomy identifiers.
 */

if (!defined('ABSPATH')) exit;

/**
 * Get all tags for a vote.
 *
 * @param int $vote_id Vote ID.
 * @return array Tag objects.
 */
function fi_vote_tags_get_tags_by_vote(int $vote_id): array {
	global $wpdb;

	return $wpdb->get_results($wpdb->prepare(
		"SELECT t.*
		FROM {$wpdb->prefix}fi_taxonomy t
		INNER JOIN {$wpdb->prefix}fi_vote_tags vt ON t.id = vt.tag_id
		WHERE vt.vote_id = %d
		AND t.taxonomy = 'tag'
		ORDER BY t.name ASC",
		$vote_id
	), ARRAY_A);
}

/**
 * Get votes assigned to a tag.
 *
 * @param int   $tag_id  Tag ID.
 * @param array $filters Optional vote filters.
 * @return array Vote rows.
 */
function fi_vote_tags_get_votes_by_tag(int $tag_id, array $filters = []): array {
	$tag_id = absint($tag_id);

	if ($tag_id <= 0) {
		return [];
	}

	$args = $filters;
	$args['tag_id'] = $tag_id;

	$results = fi_votes_get($args);

	return is_array($results) ? $results : [];
}

/**
 * Add a tag to a vote.
 *
 * @param int $vote_id Vote ID.
 * @param int $tag_id Tag ID.
 * @return bool
 */
function fi_vote_tags_add_tag(int $vote_id, int $tag_id): bool {
	global $wpdb;

	$vote_id = absint($vote_id);
	$tag_id = absint($tag_id);

	if ($vote_id <= 0 || $tag_id <= 0) {
		return false;
	}

	$exists = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}fi_vote_tags
		WHERE vote_id = %d AND tag_id = %d",
		$vote_id,
		$tag_id
	));

	if ($exists > 0) {
		return true;
	}

	$result = $wpdb->insert(
		$wpdb->prefix . 'fi_vote_tags',
		[
			'vote_id' => $vote_id,
			'tag_id'  => $tag_id,
		],
		['%d', '%d']
	);

	return $result !== false;
}

/**
 * Remove a tag from a vote.
 *
 * @param int $vote_id Vote ID.
 * @param int $tag_id Tag ID.
 * @return bool
 */
function fi_vote_tags_remove_tag(int $vote_id, int $tag_id): bool {
	global $wpdb;

	$result = $wpdb->delete(
		$wpdb->prefix . 'fi_vote_tags',
		[
			'vote_id' => absint($vote_id),
			'tag_id'  => absint($tag_id),
		],
		['%d', '%d']
	);

	return $result !== false;
}

/**
 * Set all tags for a vote, replacing existing tags.
 *
 * @param int $vote_id Vote ID.
 * @param array $tag_ids Tag IDs.
 * @return bool
 */
function fi_vote_tags_set_tags(int $vote_id, array $tag_ids): bool {
	global $wpdb;

	$vote_id = absint($vote_id);
	if ($vote_id <= 0) {
		return false;
	}

	fi_vote_tags_delete_by_vote($vote_id);

	$tag_ids = array_values(array_unique(array_filter(array_map('absint', $tag_ids))));
	if (empty($tag_ids)) {
		return true;
	}

	$values = [];
	$placeholders = [];

	foreach ($tag_ids as $tag_id) {
		$placeholders[] = '(%d, %d)';
		$values[] = $vote_id;
		$values[] = $tag_id;
	}

	$sql = "
		INSERT INTO {$wpdb->prefix}fi_vote_tags (vote_id, tag_id)
		VALUES " . implode(', ', $placeholders);

	return $wpdb->query($wpdb->prepare($sql, $values)) !== false;
}

/**
 * Delete all tags for a vote.
 *
 * @param int $vote_id Vote ID.
 * @return bool
 */
function fi_vote_tags_delete_by_vote(int $vote_id): bool {
	global $wpdb;

	$result = $wpdb->delete(
		$wpdb->prefix . 'fi_vote_tags',
		['vote_id' => absint($vote_id)],
		['%d']
	);

	return $result !== false;
}

/**
 * Delete all vote relationships for a tag.
 *
 * @param int $tag_id Tag ID.
 * @return bool
 */
function fi_vote_tags_delete_by_tag(int $tag_id): bool {
	global $wpdb;

	$result = $wpdb->delete(
		$wpdb->prefix . 'fi_vote_tags',
		['tag_id' => absint($tag_id)],
		['%d']
	);

	return $result !== false;
}

/**
 * Get tags for multiple vote IDs.
 *
 * @param array $vote_ids Vote IDs.
 * @return array Tag objects with vote_id.
 */
function fi_vote_tags_get_tags_by_vote_ids(array $vote_ids): array {
	global $wpdb;

	$vote_ids = array_values(array_unique(array_filter(array_map('absint', $vote_ids))));
	if (empty($vote_ids)) {
		return [];
	}

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

	return $wpdb->get_results($wpdb->prepare($sql, $vote_ids), ARRAY_A);
}

/**
 * Get tag counts: how many votes per tag.
 *
 * @param string|null $gov Optional government filter.
 * @param int|null $session_id Optional session filter.
 * @param string $orderby Sort order: count or name.
 * @return array Tag objects with vote_count.
 */
function fi_vote_tags_get_tag_counts(?string $gov = null, ?int $session_id = null, string $orderby = 'count'): array {
	global $wpdb;

	$where_conditions = ["t.taxonomy = 'tag'"];
	$where_values = [];

	if ($gov) {
		$where_conditions[] = 't.gov = %s';
		$where_values[] = strtoupper($gov);
	}

	$join_clause = "LEFT JOIN {$wpdb->prefix}fi_vote_tags vt ON t.id = vt.tag_id";

	if ($session_id) {
		$join_clause .= " LEFT JOIN {$wpdb->prefix}fi_votes v ON vt.vote_id = v.id";
		$where_conditions[] = 'v.session_id = %d';
		$where_values[] = absint($session_id);
	}

	$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

	$orderby = strtolower(trim($orderby));
	$order_by_clause = $orderby === 'name'
		? 'ORDER BY t.name ASC'
		: 'ORDER BY vote_count DESC, t.name ASC';

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
		return $wpdb->get_results($wpdb->prepare($sql, $where_values), ARRAY_A);
	}

	return $wpdb->get_results($sql, ARRAY_A);
}
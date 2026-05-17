<?php
namespace FI\Core {

if (!defined('ABSPATH')) exit;

/**
 * Legislator Votes Data Management
 * 
 * Builds comprehensive session cache with votes + rollcalls for instant page loads.
 * Calculates report scores on-the-fly from cached data.
 * 
 * Cache Strategy:
 * - Cache key: `fi_session_votes_{session_id}` (1 month expiry)
 * - Contains: All votes for session + all rollcalls for those votes
 * - Invalidated when votes/rollcalls are updated
 */
final class LegislatorVotes {
	
	/**
	 * Request-level cache for get_legislator_tags() results
	 * Key: "{$legislator_id}_{$chamber}"
	 */
	private static $cache_tags = [];

	/**
	 * Initialize cache invalidation hooks
	 */
	public static function init(): void {
		// Hook into vote saves to invalidate cache
		add_action('fi_vote_saved', [self::class, 'on_vote_saved'], 10, 2);
		add_action('fi_rollcall_saved', [self::class, 'on_rollcall_saved'], 10, 2);
	}
	
	/**
	 * Handle vote saved - invalidate session cache
	 */
	public static function on_vote_saved(int $vote_id, array $data): void {
		if (!empty($data['session_id'])) {
			self::invalidate_session_cache((int) $data['session_id']);
		}
	}
	
	/**
	 * Handle rollcall saved - invalidate session cache
	 */
	public static function on_rollcall_saved(int $rollcall_id, array $data): void {
		if (!empty($data['vote_id'])) {
			// Get vote to find session_id
			$vote = fi_vote_get((int) $data['vote_id']);
			if ($vote && !empty($vote->session_id)) {
				self::invalidate_session_cache((int) $vote->session_id);
			}
		}
	}

	/**
	 * Get complete vote structure for a legislator
	 * 
	 * Returns hierarchical structure:
	 * - Sessions (with stored scores)
	 *   - Reports (with calculated scores)
	 *     - Votes (with legislator's rollcall)
	 *   - All session votes (not in reports)
	 * 
	 * @param int $legislator_id Legislator ID
	 * @param string $chamber Legislator chamber (H or S) - required for report vote filtering
	 * @return array Structured vote data
	 */
	public static function get_for_legislator(int $legislator_id, string $chamber): array {
		// Get legislator's sessions
		$legislator = fi_legislator_get($legislator_id);
		if (!$legislator || empty($legislator->sessions)) {
			return [];
		}
		
		$sessions_data = [];
		
		foreach ($legislator->sessions as $session) {
			$session_id = $session->session_id;
			
			// Get cached session data (votes + rollcalls)
			$session_cache = self::get_session_cache($session_id);
			if (empty($session_cache)) {
				continue; // Skip if cache build failed
			}
			
			// Get reports for this session
			$reports = fi_reports_get_by_session($session_id);
			
			// Build session structure
			//SESSIONSLUG: Remove 'session_slug' from $session_data array - no longer needed in data objects
			$session_data = [
				'session_id' => $session_id,
				'session_name' => $session->session_name ?? $session_cache['session_name'],
				'gov' => $session->gov,
				'date_start' => $session->date_start,
				'date_end' => $session->date_end,
				'score' => $session->score, // Stored session score
				'score_data' => is_string($session->score_data) ? json_decode($session->score_data, true) : ($session->score_data ?? null),
				'reports' => [],
				'all_votes' => []
			];
			
			// Process reports
			foreach ($reports as $report) {
				$report_data = self::build_report_data($report, $session_cache, $legislator_id, $chamber);
				if ($report_data) {
					$session_data['reports'][] = $report_data;
				}
			}
			
			// Build "all session votes" list (votes not in any report)
			$report_vote_ids = [];
			foreach ($session_data['reports'] as $report) {
				foreach ($report['votes'] as $vote) {
					$report_vote_ids[] = $vote['vote_id'];
				}
			}
			
			// Filter session votes to exclude report votes
			foreach ($session_cache['votes'] as $vote) {
				if (!in_array($vote['vote_id'], $report_vote_ids)) {
					// Add this legislator's rollcall
					$vote['rollcall'] = self::get_legislator_rollcall($vote['vote_id'], $legislator_id, $session_cache['rollcalls']);
					$session_data['all_votes'][] = $vote;
				}
			}
			
			$sessions_data[] = $session_data;
		}
		
		return $sessions_data;
	}
	
	/**
	 * Build report data with calculated score
	 * 
	 * @param object $report Report object
	 * @param array $session_cache Cached session data
	 * @param int $legislator_id Legislator ID
	 * @param string $chamber Legislator chamber (H or S)
	 * @return array|null Report data or null if invalid
	 */
	private static function build_report_data(object $report, array $session_cache, int $legislator_id, string $chamber): ?array {
		// Parse payload_json
		$payload = json_decode($report->payload_json ?? '{}', true);
		if (!is_array($payload)) {
			return null;
		}
		
		// Get vote IDs based on chamber (H or S)
		$vote_ids = [];
		if ($chamber === 'H' && !empty($payload['votes_h'])) {
			$vote_ids = array_map('intval', (array) $payload['votes_h']);
		} elseif ($chamber === 'S' && !empty($payload['votes_s'])) {
			$vote_ids = array_map('intval', (array) $payload['votes_s']);
		}
		
		if (empty($vote_ids)) {
			return null; // No votes for this chamber
		}
		
		// Get votes from cache
		$report_votes = [];
		foreach ($vote_ids as $vote_id) {
			$vote = self::find_vote_in_cache($vote_id, $session_cache['votes']);
			if ($vote) {
				// Add this legislator's rollcall
				$vote['rollcall'] = self::get_legislator_rollcall($vote_id, $legislator_id, $session_cache['rollcalls']);
				$report_votes[] = $vote;
			}
		}
		
		// Calculate report score
		$score_data = self::calculate_score_from_votes($report_votes, $legislator_id);
		
		return [
			'report_id' => $report->id,
			'report_name' => $report->title,
			'report_slug' => $report->slug,
			'content' => $payload['content'] ?? '',
			'format' => $payload['format'] ?? 'scorecard',
			'score' => $score_data['score'] ?? null,
			'score_data' => $score_data,
			'votes' => $report_votes
		];
	}
	
	/**
	 * Get or build session cache
	 * 
	 * @param int $session_id Session ID
	 * @return array Cached session data
	 */
	public static function get_session_cache(int $session_id): array {
		$cache_key = 'fi_session_votes_' . $session_id;
		
		// Check cache (bypass if FI_DEV is true)
		if (!defined('FI_DEV') || !FI_DEV) {
			$cached = get_transient($cache_key);
			if ($cached !== false) {
				return $cached;
			}
		}
		
		// Build cache
		$cache_data = self::build_session_cache($session_id);
		
		// Store cache (1 month expiry)
		if (!defined('FI_DEV') || !FI_DEV) {
			set_transient($cache_key, $cache_data, MONTH_IN_SECONDS);
		}
		
		return $cache_data;
	}
	
	/**
	 * Build comprehensive session cache
	 * 
	 * @param int $session_id Session ID
	 * @return array Session cache data
	 */
	private static function build_session_cache(int $session_id): array {
		// Get session info
		$session = fi_session_get($session_id);
		if (!$session) {
			return [];
		}
		
		// Get all votes for this session using Votes class
		$votes = \FI\Core\Votes::get_by_session($session_id, [
			'status' => 'publish',
			'orderby' => 'date_voted',
			'order' => 'ASC',
			'per_page' => -1
		]);
		
		// Filter to only constitutional votes (Y or N) and format
		$formatted_votes = [];
		$vote_ids = [];
		foreach ($votes as $vote) {
			// Skip if not Y or N
			if (!in_array($vote->constitutional, ['Y', 'N'])) {
				continue;
			}
			
			$vote_ids[] = (int) $vote->id;
			$meta = $vote->meta ? (is_string($vote->meta) ? json_decode($vote->meta, true) : $vote->meta) : [];
			if (!is_array($meta)) {
				$meta = [];
			}
			$formatted_votes[] = [
				'vote_id' => (int) $vote->id,
				'slug' => $vote->slug,
				'title' => $vote->title,
				'bill_number' => $vote->bill_number,
				'bill_number' => $vote->bill_number ?? ($meta['bill_number'] ?? $meta['bill_key'] ?? null),
				'date_voted' => $vote->date_voted,
				'constitutional' => $vote->constitutional,
				'chamber' => $vote->chamber,
				'gov' => $vote->gov,
				'session_id' => (int) $vote->session_id,
				'description_short' => $meta['description_short'] ?? $meta['text_scorecard'] ?? null,
				'description_medium' => $meta['description_medium'] ?? $meta['text_freedomindex'] ?? null,
				'description_long' => $meta['description_long'] ?? $meta['text_scorecard_more'] ?? null,
				'url_source' => $meta['url'] ?? $meta['url_source'] ?? null,
				'url_bill' => $meta['url_bill'] ?? null,
				'url_rollcall' => $meta['url_rollcall'] ?? null,
				'meta' => $meta
			];
		}
		
		// Get all rollcalls for these votes using Rollcalls class bulk method
		$rollcalls = [];
		if (!empty($vote_ids)) {
			$rollcall_results = \FI\Core\Rollcalls::get_by_vote_ids($vote_ids);
			
			// Index rollcalls by vote_id for quick lookup
			foreach ($rollcall_results as $rc) {
				if (!isset($rollcalls[$rc->vote_id])) {
					$rollcalls[$rc->vote_id] = [];
				}
				$rollcalls[$rc->vote_id][$rc->legislator_id] = [
					'cast' => $rc->cast,
					'is_override' => (bool) $rc->is_override,
					'date_created' => $rc->date_created
				];
			}
		}
		
		// Get all tags for these votes using VoteTags class bulk method
		$vote_tags = [];
		if (!empty($vote_ids)) {
			$tag_results = \FI\Core\VoteTags::get_tags_by_vote_ids($vote_ids);
			
			// Index tags by vote_id
			foreach ($tag_results as $tag) {
				if (!isset($vote_tags[$tag->vote_id])) {
					$vote_tags[$tag->vote_id] = [];
				}
				$vote_tags[$tag->vote_id][] = [
					'id' => (int) $tag->tag_id,
					'name' => $tag->tag_name,
					'slug' => $tag->tag_slug
				];
			}
		}
		
		// Add tags to formatted votes
		foreach ($formatted_votes as &$vote) {
			$vote['tags'] = $vote_tags[$vote['vote_id']] ?? [];
		}
		unset($vote);
		
		return [
			'session_id' => $session_id,
			'session_name' => $session->name ?? '',
			'session_id' => $session->id ?? '',
			'gov' => $session->gov ?? '',
			'votes' => $formatted_votes,
			'rollcalls' => $rollcalls, // Indexed by vote_id => legislator_id => rollcall data
			'tags' => $vote_tags // Indexed by vote_id => array of tag objects
		];
	}
	
	/**
	 * Find vote in cache by ID
	 * 
	 * @param int $vote_id Vote ID
	 * @param array $votes_cache Votes array from cache
	 * @return array|null Vote data or null
	 */
	private static function find_vote_in_cache(int $vote_id, array $votes_cache): ?array {
		foreach ($votes_cache as $vote) {
			if ($vote['vote_id'] === $vote_id) {
				return $vote;
			}
		}
		return null;
	}
	
	/**
	 * Get legislator's rollcall for a vote from cache
	 * 
	 * @param int $vote_id Vote ID
	 * @param int $legislator_id Legislator ID
	 * @param array $rollcalls_cache Rollcalls cache (indexed by vote_id => legislator_id)
	 * @return array|null Rollcall data or null
	 */
	private static function get_legislator_rollcall(int $vote_id, int $legislator_id, array $rollcalls_cache): ?array {
		if (isset($rollcalls_cache[$vote_id][$legislator_id])) {
			return $rollcalls_cache[$vote_id][$legislator_id];
		}
		return null;
	}
	
	/**
	 * Calculate score from votes and rollcalls
	 * Delegates to Scoring class helper function
	 * 
	 * @param array $votes Array of vote data with rollcall
	 * @param int $legislator_id Legislator ID (for logging, not used in calculation)
	 * @return array Score data
	 */
	private static function calculate_score_from_votes(array $votes, int $legislator_id): array {
		// Calculate score inline
		$votes_good = 0;
		$votes_bad = 0;
		$votes_not = 0;
		$votes_scored = 0;
		$votes_total = count($votes);
		
		foreach ($votes as $vote) {
			$cast = $vote['cast'] ?? 'X';
			$constitutional = $vote['constitutional'] ?? '';
			
			// Only Y/N count toward scored votes (P/A/X are "not voted")
			if (in_array($cast, ['P', 'A', 'X', ''], true) || empty($constitutional)) {
				$votes_not++;
				continue;
			}
			
			$votes_scored++;
			if ($cast === $constitutional) {
				$votes_good++;
			} else {
				$votes_bad++;
			}
		}
		
		// Calculate percentage score
		$score = ($votes_scored > 0) ? round(($votes_good / $votes_scored) * 100, 0) : 0;
		$grade = fi_score_calculate_grade($score);
		
		return [
			'score' => $score,
			'grade' => $grade,
			'total' => $votes_total,
			'good' => $votes_good,
			'bad' => $votes_bad,
			'not' => $votes_not,
			'scored' => $votes_scored,
		];
	}
	
	/**
	 * Invalidate session cache
	 * Call this when votes or rollcalls are updated
	 * 
	 * @param int $session_id Session ID
	 * @return bool Success
	 */
	public static function invalidate_session_cache(int $session_id): bool {
		$cache_key = 'fi_session_votes_' . $session_id;
		return delete_transient($cache_key);
	}
	
	/**
	 * Invalidate all session caches
	 * Useful for bulk updates
	 * 
	 * @return int Number of caches deleted
	 */
	public static function invalidate_all_caches(): int {
		global $wpdb;
		
		// Get all session IDs
		$sessions = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}fi_sessions");
		
		$deleted = 0;
		foreach ($sessions as $session_id) {
			if (self::invalidate_session_cache($session_id)) {
				$deleted++;
			}
		}
		
		return $deleted;
	}
	
	/**
	 * Get all unique tags used across all votes for a legislator
	 * 
	 * @param int $legislator_id Legislator ID
	 * @param string $chamber Legislator chamber (H or S)
	 * @return array Array of tag objects with vote_count
	 */
	public static function get_legislator_tags(int $legislator_id, string $chamber): array {
		// Check request-level cache first
		$cache_key = "{$legislator_id}_{$chamber}";
		if (isset(self::$cache_tags[$cache_key])) {
			return self::$cache_tags[$cache_key];
		}
		
		global $wpdb;
		
		// Get legislator's sessions
		$legislator = fi_legislator_get($legislator_id);
		if (!$legislator || empty($legislator->sessions)) {
			return [];
		}
		
		$session_ids = [];
		foreach ($legislator->sessions as $session) {
			$session_ids[] = $session->session_id;
		}
		
		if (empty($session_ids)) {
			return [];
		}
		
		$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
		
		// Get all vote IDs for this legislator across all sessions
		$vote_ids = $wpdb->get_col($wpdb->prepare(
			"SELECT DISTINCT v.id
			FROM {$wpdb->prefix}fi_votes v
			INNER JOIN {$wpdb->prefix}fi_voterc rc ON v.id = rc.vote_id
			WHERE v.session_id IN ($placeholders)
			AND v.chamber = %s
			AND v.status = 'publish'
			AND rc.legislator_id = %d",
			...array_merge($session_ids, [$chamber, $legislator_id])
		));
		
		if (empty($vote_ids)) {
			return [];
		}
		
		$vote_placeholders = implode(',', array_fill(0, count($vote_ids), '%d'));
		
		// Get all unique tags with vote counts
		$tags = $wpdb->get_results($wpdb->prepare(
			"SELECT 
				t.id,
				t.name,
				t.slug,
				COUNT(DISTINCT vt.vote_id) as vote_count
			FROM {$wpdb->prefix}fi_taxonomy t
			INNER JOIN {$wpdb->prefix}fi_vote_tags vt ON t.id = vt.tag_id
			WHERE vt.vote_id IN ($vote_placeholders)
			AND t.taxonomy = 'tag'
			GROUP BY t.id
			ORDER BY t.name ASC",
			...$vote_ids
		));
		
		$result = $tags ?: [];
		
		// Store in request-level cache
		self::$cache_tags[$cache_key] = $result;
		
		return $result;
	}
	
	/**
	 * Get votes for a legislator filtered by tag
	 * 
	 * @param int $legislator_id Legislator ID
	 * @param string $chamber Legislator chamber (H or S)
	 * @param int $tag_id Tag ID
	 * @return array Array of vote data with rollcalls
	 */
	public static function get_votes_by_tag(int $legislator_id, string $chamber, int $tag_id): array {
		// Get legislator's sessions
		$legislator = fi_legislator_get($legislator_id);
		if (!$legislator || empty($legislator->sessions)) {
			return [];
		}
		
		$session_ids = [];
		foreach ($legislator->sessions as $session) {
			$session_ids[] = $session->session_id;
		}
		
		if (empty($session_ids)) {
			return [];
		}
		
		// Get votes with this tag using Votes class
		$tag_votes = \FI\Core\Votes::get_by_tag($tag_id, [
			'session_ids' => $session_ids,
			'chamber' => $chamber,
			'status' => 'publish',
			'orderby' => 'date_voted',
			'order' => 'DESC'
		]);
		
		// Filter to only votes where legislator has a rollcall
		$votes = [];
		foreach ($tag_votes as $vote) {
			$rollcall = \FI\Core\Rollcalls::get_by_ids($vote->id, $legislator_id);
			if ($rollcall) {
				$votes[] = $vote;
			}
		}
		
		if (empty($votes)) {
			return [];
		}
		
		// Collect vote IDs for bulk fetching
		$vote_ids = [];
		foreach ($votes as $vote) {
			$vote_ids[] = (int) $vote->id;
		}
		
		// Bulk fetch rollcalls for this legislator
		$rollcall_results = \FI\Core\Rollcalls::get([
			'legislator_id' => $legislator_id,
			'per_page' => -1
		]);
		$rollcalls_by_vote = [];
		foreach ($rollcall_results as $rc) {
			if (in_array($rc->vote_id, $vote_ids)) {
				$rollcalls_by_vote[$rc->vote_id] = [
					'cast' => $rc->cast,
					'is_override' => (bool) $rc->is_override,
					'date_created' => $rc->date_created
				];
			}
		}
		
		// Bulk fetch tags for all votes
		$tag_results = \FI\Core\VoteTags::get_tags_by_vote_ids($vote_ids);
		$tags_by_vote = [];
		foreach ($tag_results as $tag) {
			if (!isset($tags_by_vote[$tag->vote_id])) {
				$tags_by_vote[$tag->vote_id] = [];
			}
			$tags_by_vote[$tag->vote_id][] = [
				'id' => (int) $tag->tag_id,
				'name' => $tag->tag_name,
				'slug' => $tag->tag_slug
			];
		}
		
		// Get session info cache
		$session_cache = [];
		
		// Format votes with rollcalls and tags
		$formatted_votes = [];
		foreach ($votes as $vote) {
			// Get session info if not cached
			if (!isset($session_cache[$vote->session_id])) {
				$session_obj = fi_session_get($vote->session_id);
				$session_cache[$vote->session_id] = [
					'name' => $session_obj->name ?? '',
					'slug' => $session_obj->slug ?? ''
				];
			}
			
			$meta = $vote->meta ? (is_string($vote->meta) ? json_decode($vote->meta, true) : $vote->meta) : [];
			if (!is_array($meta)) {
				$meta = [];
			}
			
			$formatted_votes[] = [
				'vote_id' => (int) $vote->id,
				'slug' => $vote->slug,
				'title' => $vote->title,
				'bill_number' => $vote->bill_number,
				'bill_number' => $vote->bill_number ?? ($meta['bill_number'] ?? $meta['bill_key'] ?? null),
				'date_voted' => $vote->date_voted,
				'constitutional' => $vote->constitutional,
				'chamber' => $vote->chamber,
				'gov' => $vote->gov,
				'session_id' => (int) $vote->session_id,
				'session_name' => $session_cache[$vote->session_id]['name'],
				//SESSIONSLUG: Remove 'session_slug' from vote data array - no longer needed
				'description_short' => $meta['description_short'] ?? $meta['text_scorecard'] ?? null,
				'description_medium' => $meta['description_medium'] ?? $meta['text_freedomindex'] ?? null,
				'description_long' => $meta['description_long'] ?? $meta['text_scorecard_more'] ?? null,
				'url_source' => $meta['url'] ?? $meta['url_source'] ?? null,
				'url_bill' => $meta['url_bill'] ?? null,
				'url_rollcall' => $meta['url_rollcall'] ?? null,
				'rollcall' => $rollcalls_by_vote[$vote->id] ?? null,
				'tags' => $tags_by_vote[$vote->id] ?? [],
				'meta' => $meta
			];
		}
		
		return $formatted_votes;
	}
}

}

// Public helper functions
namespace {
	/**
	 * Get complete vote structure for a legislator
	 * 
	 * @param int $legislator_id Legislator ID
	 * @param string $chamber Legislator chamber (H or S)
	 * @return array Structured vote data
	 */
	function fi_legislator_votes_get(int $legislator_id, string $chamber): array {
		return \FI\Core\LegislatorVotes::get_for_legislator($legislator_id, $chamber);
	}
	
	/**
	 * Get session cache (for debugging or direct access)
	 * 
	 * @param int $session_id Session ID
	 * @return array Cached session data
	 */
	function fi_session_votes_cache_get(int $session_id): array {
		return \FI\Core\LegislatorVotes::get_session_cache($session_id);
	}
	
	/**
	 * Invalidate session cache
	 * 
	 * @param int $session_id Session ID
	 * @return bool Success
	 */
	function fi_session_votes_cache_invalidate(int $session_id): bool {
		return \FI\Core\LegislatorVotes::invalidate_session_cache($session_id);
	}
	
	/**
	 * Get all unique tags for a legislator
	 * 
	 * @param int $legislator_id Legislator ID
	 * @param string $chamber Legislator chamber (H or S)
	 * @return array Array of tag objects with vote_count
	 */
	function fi_legislator_tags_get(int $legislator_id, string $chamber): array {
		return \FI\Core\LegislatorVotes::get_legislator_tags($legislator_id, $chamber);
	}
	
	/**
	 * Get votes for a legislator filtered by tag
	 * 
	 * @param int $legislator_id Legislator ID
	 * @param string $chamber Legislator chamber (H or S)
	 * @param int $tag_id Tag ID
	 * @return array Array of vote data with rollcalls
	 */
	function fi_legislator_votes_get_by_tag(int $legislator_id, string $chamber, int $tag_id): array {
		return \FI\Core\LegislatorVotes::get_votes_by_tag($legislator_id, $chamber, $tag_id);
	}
}


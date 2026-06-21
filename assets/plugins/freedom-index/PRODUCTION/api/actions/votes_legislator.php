<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * votes_legislator – List all votes for a legislator, across all governments served.
 *
 * - For a legislator, list all roll call votes (from any government they've served in).
 * - Each returned vote entry includes a context field indicating the 'gov' (government code) of that vote.
 * - Special session votes may have their own fi_sessions row, and may point to a parent session via parent_id.
 *   We want to present ONLY the parent session info to users, but need to fetch and map this after fetching the votes.
 *
 * Note: This file generates a fi_api_sessions() function for in-file only use (to be moved out later).
 * TEST: https://freedomindex.us/fi_api.php?key=5f71b6205a7fef749f412c21ec971e43&action=votes_legislator&legislator_id=1024
 */

header('Content-Type: application/json; charset=utf-8');

try {

	// Get sanitized input arguments
	$legislator_id = isset($args['legislator_id']) ? (int)$args['legislator_id'] : 0;

	// Basic validation
	if ($legislator_id <= 0) {
		echo json_encode([
			'success' => false,
			'error' => 'invalid_args',
			'message' => 'A valid legislator_id is required.',
		], JSON_UNESCAPED_SLASHES);
		exit;
	}

	// Get all session info for this gov context to minimize repeated DB calls.
	// Since we don't know all gov values yet (multi-gov result set!), we will gather all sessions for all govs.
	list($sessions_by_id, $child_to_parent) = fi_api_sessions($fidb);

	// Only select fields that verifiably exist in the schema.
	// According to the schema, fi_voterc has: id, vote_id, legislator_id, cast, is_override, meta
	// fi_votes verified fields (referenced in schema)
	$rows = $fidb->select(TB_VOTERC . ' (r)', [
		'[>]'.TB_VOTES.' (v)' => ['vote_id' => 'id'],
	], [
		// fi_votes fields
		'v.id',
		'v.gov',
		'v.title',
		'v.date_voted',
		'v.chamber',
		'v.status',
		'v.constitutional',
		'v.session_id',
		'v.rollcall_number',
		'v.bill_number',
		'v.meta',
		'r.cast'
	], [
		'AND' => [
			'r.legislator_id' => $legislator_id
		],
		'ORDER' => [
			'v.date_voted' => 'DESC',
			'v.id'         => 'DESC'
		],
		'LIMIT' => 1000,
	]);
/* mySQLQUERY:
SELECT v.id, v.gov, v.title, v.date_voted, v.chamber, v.status, v.slug, v.constitutional, v.bill_number, v.session_id, v.meta, v.rollcall_number, r.cast FROM jbsw_5_fi_voterc r LEFT JOIN jbsw_5_fi_votes v ON r.vote_id = v.id WHERE r.legislator_id = 1024 ORDER BY v.date_voted DESC, v.id DESC LIMIT 1000
*/


	// Hydrate session info, traversing to parent if necessary, and expose parent_id in result for quick lookup.
	if (is_array($rows)) {
		foreach ($rows as &$vote) {
			$v_session_id = $vote['v.session_id'] ?? null;
			$child = null;
			$parent = null;
			if ($v_session_id && isset($sessions_by_id[$v_session_id])) {
				$child = $sessions_by_id[$v_session_id];
				if (!empty($child['parent_id']) && isset($sessions_by_id[$child['parent_id']])) {
					$parent = $sessions_by_id[$child['parent_id']];
				}
			}

			// Always include: session_id, session_name, session_date_start, session_date_end, parent_id
			if ($parent) {
				// Use parent session info for public-facing fields, but still attach the actual session_id used (child)
				$vote['session_id']            = $parent['id'];
				$vote['session_name']          = $parent['name'];
				$vote['session_date_start']    = $parent['date_start'];
				$vote['session_date_end']      = $parent['date_end'];
				$vote['parent_id']             = null; // parent session's parent_id not relevant here
				$vote['subsession_id']         = $child['id']; // expose subsession (actual fi_votes:session_id)
				$vote['subsession_name']       = $child['name'];
				$vote['subsession_parent_id']  = $child['parent_id'];
			} elseif ($child) {
				$vote['session_id']         = $child['id'];
				$vote['session_name']       = $child['name'];
				$vote['session_date_start'] = $child['date_start'];
				$vote['session_date_end']   = $child['date_end'];
				$vote['parent_id']          = $child['parent_id'];
				// No subsession, so don't set those fields.
			} else {
				// If there is no session info, return empty values (votes must always have sessions; problems will surface)
				$vote['session_id']         = $v_session_id;
				$vote['session_name']       = null;
				$vote['session_date_start'] = null;
				$vote['session_date_end']   = null;
				$vote['parent_id']          = null;
			}

			// Label context (gov)
			$vote['context'] = $vote['v.gov'] ?? null;
		}
		unset($vote);
	}

	echo json_encode([
		'success'      => true,
		'legislator_id'=> $legislator_id,
		'count'        => is_array($rows) ? count($rows) : 0,
		'rows'         => $rows,
	], JSON_UNESCAPED_SLASHES);
	exit;

} catch (Throwable $e) {
	echo json_encode([
		'success' => false,
		'error'   => 'exception',
		'message' => 'API error',
		'detail'  => [
			'msg'  => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
		],
	], JSON_UNESCAPED_SLASHES);
}
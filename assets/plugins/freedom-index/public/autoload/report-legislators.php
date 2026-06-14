<?php
if (!defined('ABSPATH')) exit;

/**
 * Shared report legislators data builder.
 * Single source for legislator list, rollcall, scores, states, and parties used by filter, grid, and table.
 *
 * @param int $session_id Session ID
 * @param string $chamber Chamber code (S or H)
 * @param string $gov Government code
 * @param array $votes Array of vote objects in the report
 * @param string $filter_party_slug Optional party slug so selected party stays in dropdown
 * @return array {
 *     @type bool $empty True if no session/votes or no legislators
 *     @type array $legislator_data Per-legislator items: legislator, report_score, votes (with vote_format)
 *     @type array $states State code => name (Congress only)
 *     @type array $parties Party code => display name
 *     @type bool $is_federal True if gov === 'US'
 * }
 */
function fi_report_legislators_build_data($session_id, $chamber, $gov, $votes, $filter_party_slug = '') {
    $empty = true;
    $legislator_data = [];
    $states = [];
    $parties = [];
    $is_federal = ($gov === 'US');

    if (empty($session_id) || empty($votes)) {
        return compact('empty', 'legislator_data', 'states', 'parties', 'is_federal');
    }
fi_log('session_id: '.$session_id . ' chamber: '.$chamber . ' gov: '.$gov . ' votes: '.count($votes),__FILE__,__LINE__);
    $legislators = fi_legislators_get_by_session($session_id, ['chamber' => $chamber]);
    if (empty($legislators)) {
        return compact('empty', 'legislator_data', 'states', 'parties', 'is_federal');
    }

    // Rollcall: vote_id => legislator_id => cast
    $rollcall_data = [];
    foreach ($votes as $vote) {
        if (!isset($vote['id'])) continue;
        $rollcalls = fi_rollcalls_get_by_vote($vote['id']);
        foreach ($rollcalls as $rc) {
            if (!isset($rc->legislator_id)) continue;
            $rollcall_data[$vote['id']][$rc->legislator_id] = fi_rollcall_cast_normalize((string) ($rc->cast ?? ''));
        }
    }

    foreach ($legislators as $leg) {
        $leg_votes = [];
        $votes_for_scoring = [];
        foreach ($votes as $vote) {
            if (!isset($vote['id'])) continue;
            $cast = fi_rollcall_cast_normalize((string) ($rollcall_data[$vote['id']][$leg->id] ?? ''));
            $vote_format = fi_vote_format([
                'cast' => $cast,
                'constitutional' => $vote['constitutional'] ?? '',
                'format' => 'full'
            ]);
            $votes_for_scoring[] = [
                'id' => $vote['id'],
                'good' => $vote['constitutional'] ?? '',
                'cast' => $cast
            ];
            $leg_votes[] = [
                'vote' => $vote,
                'cast' => $cast,
                'vote_format' => $vote_format
            ];
        }
        $report_score = !empty($votes_for_scoring) ? fi_score_calculate_batch($votes_for_scoring) : null;
        $legislator_data[] = [
            'legislator' => $leg,
            'report_score' => $report_score,
            'votes' => $leg_votes
        ];
    }

    // States: Congress only
    if ($is_federal && defined('FI_GOVERNMENTS')) {
        $states = FI_GOVERNMENTS;
        unset($states['US']);
        ksort($states);
    }

    // Parties: transient by session+chamber; ensure filter party in list
    $transient_key = 'fi_report_parties_' . (int) $session_id . '_' . $chamber;
    $parties = get_transient($transient_key);
    if ($parties === false) {
        $parties = [];
        foreach ($legislators as $leg) {
            if (!empty($leg->party)) {
                $name = fi_party_name($leg->party);
                if ($name) $parties[$leg->party] = $name;
            }
        }
        ksort($parties);
        set_transient($transient_key, $parties, WEEK_IN_SECONDS);
    }
    if ($filter_party_slug !== '' && $filter_party_slug !== null && !isset($parties[$filter_party_slug])) {
        $parties[$filter_party_slug] = fi_party_name($filter_party_slug);
        ksort($parties);
    }

    $empty = false;
    return compact('empty', 'legislator_data', 'states', 'parties', 'is_federal');
}

<?php if(!defined('ABSPATH')) exit;
/* Single Legislator by ID
* Replicate full Legislator object like the example below.

fi_legislators[L]:WHERE ID
fi_legislator_sessions[LS]:WHERE LEGISLATOR_ID|ORDER BY date_end,id DESC  
	LEFT JOIN(?) Session Info: fi_sessions[S]:WHERE ID = LS.session_id
	NOTE: We only want LS where S parent_id IS NULL
	Copy most recent session data into L.session_id, 
		L.session_name, L.gov, L.state, L.party, L.chamber, L.district, 
		L.session_score, L.session_score_data, L.session_image_id, L.date_start, L.date_end,
	L.state_name = FI_GOVERNMENTS[L.state]
	L.gov_name = FI_GOVERNMENTS[L.gov]
	L.party_name = FI_PARTIES[L.party]
	L.chamber_label = FI_CHAMBERS[L.chamber]
	L.chamber_title = FI_CHAMBERS[L.chamber]	
District Info: fi_taxonomy[T]:WHERE type=district|WHERE ID = L.district
*/




//SCORE a batch of votes
function fi_api_vote_score($votes_data, $vote_ids){
	$counted = 0;
	$matched = 0;
	foreach($vote_ids as $vid){
		if(isset($votes_data[$vid])){
			$v = $votes_data[$vid];
			if(isset($v['counted']) && $v['counted'] === true){
				$counted++;
			}
			if(isset($v['matched']) && $v['matched'] === true){
				$matched++;
			}
		}
	}
	$score = round(($matched / $counted) * 100, 0);
	return $score;
}


header('Content-Type: application/json; charset=utf-8');

//Check query execution time
$start_time = microtime(true);

try {

	if (!$fidb || $args['legislator_id'] <= 0) return null;
	$LID = $args['legislator_id'];

	$leg = $fidb->get(TB_LEGISLATORS, [
		'id',
		'first_name',
		'middle_name',
		'last_name',
		'display_name',
		'bioguide_id',
		'lis_id',
		'legiscan_id',
		'govtrack_id',
		'votesmart_id',
		'ballotpedia_id',
		'openstates_id',
		'image_id',
		'image_url',
		'score(freedom_score)',
		'score_data(freedom_score_data)',
		'score_date(freedom_score_date)',
		'meta'
	], [
		'id' => $LID,
		'LIMIT' => 1
	]);

	if (!$leg) return null;

	//Construct all the data as arrays then objectify at the end to aling with the existing object structure
	$leg['freedom_score_data'] = json_decode($leg['freedom_score_data'], true);
	$meta = json_decode($leg['meta'], true);
	//Cleanup meta
	unset($meta['legacy_post']);
	unset($meta['legiscan_data']);
	$leg['meta'] = $meta;

	$leg['url'] = SITE_URL.'/legislator/'.$LID.'/';

	$leg['votes'] = [];
	$leg['vote_tags'] = [];
	$leg['votes_cast'] = [];

	// Get legislator PARENT sessions and session info only
	$sessions = $fidb->select(
		TB_LEGISLATOR_SESSIONS . ' (ls)',
		[
			'[>]'.TB_SESSIONS.' (s)' => ['session_id' => 'id'],
		],
		[
			'ls.id(session_link_id)',
			'ls.session_id',
			'ls.gov',
			'ls.state',
			'ls.chamber',
			'ls.district',
			'ls.party',
			'ls.image_id(session_image_id)',
			'ls.score(session_score)',
			'ls.date_start',
			'ls.date_end',
			//'s.id(session_pk)',
			's.name(session_name)',
			//'s.parent_id(session_parent_id)',
		],
		[
			'AND' => [
				'ls.legislator_id' => $LID,
				's.parent_id'      => null,   // ONLY parent sessions
			],
			'ORDER' => [
				'ls.date_end' => 'DESC',
				'ls.id'       => 'DESC',
			],
		]
	);

	//VOTES CAST
	$voterc = $fidb->select(
		TB_VOTERC,
		[
			'vote_id',
			'cast',
		],
		[
			'legislator_id' => $LID,
		]
	);
	$votes_cast = [];
	$vote_ids = [];
	foreach($voterc as $vrc){
		$votes_cast[$vrc['vote_id']] = $vrc['cast'];
		$vote_ids[] = $vrc['vote_id'];
	}
	$leg['votes_cast'] = $votes_cast;

	// VOTE IDs: Clean + dedupe (important for performance + safety)
	$vote_ids = array_values(array_unique(array_map('intval', $vote_ids)));

	//GET VOTES
	//Can we get 250-400 votes in this payload without slowing down the API?
	$votes_data = [];
	if (!empty($vote_ids)) {
		$votes = $fidb->select(
			TB_VOTES,
			[
				'id',
				'session_id',
				'gov',
				'chamber',
				'title',
				'bill_number',
				'constitutional',
				'status',
				'date_voted',
				'meta',
			],
			[
				'id' => $vote_ids,
				'ORDER' => ['date_voted' => 'DESC'],
			]
		);
		foreach ((array) $votes as $v) {
			$vid = (int) $v['id'];
			$meta = json_decode($v['meta'], true);
			unset($meta['legacy']);
			unset($meta['legiscan']);
			unset($meta['legiscan_rollcall_audit']);
			unset($meta['legiscan_session_id']);

			$v['meta'] = $meta;

			$chamber = FI_CHAMBERS[$v['gov']][$v['chamber']]['chamber'];
			$v['chamber_label'] = $chamber;

			//format date
			$formatted_date = '';
			if (!empty($v['date_voted'])) {
				$timestamp = strtotime($v['date_voted']);
				if ($timestamp !== false) {
					$formatted_date = date('n/j/Y', $timestamp);
				} else {
					$formatted_date = $v['date_voted'];
				}
			}
			$v['date_formatted'] = $formatted_date;

			//Compare vote cast with constitutional and determine if counted
			$cast = $votes_cast[$vid] ?? '';
			$v['cast'] = $cast;
			$v['matched'] = null;
			$v['counted'] = null;

			$constitutional = $v['constitutional'] ?? '';
			// Only Y/N count toward scored votes (P/A/X are "not voted")
			if (!in_array($cast, ['P', 'A', 'X', ''], true) && !empty($constitutional)) {
				$const = $v['constitutional'] ?? '';
				$v['counted'] = true;
				if($const == $cast){
					$v['matched'] = true;
				}else{
					$v['matched'] = false;
				}
			}
			$votes_data[$vid] = $v;
		}
	}
	$leg['votes'] = $votes_data;

	//VOTE TAGS
	if (!empty($vote_ids)) {
		$vote_tags = $fidb->select(
			TB_TAXONOMY . ' (t)',
			[
				'[>]'.TB_VOTE_TAGS.' (vt)' => ['id' => 'tag_id'],
			],
			[
				't.id',
				't.name',
				't.slug',
				'vote_count' => Medoo\Medoo::raw('COUNT(DISTINCT vt.vote_id)'),
			],
			[
				'AND' => [
					'vt.vote_id'  => $vote_ids, // Medoo turns array into IN (...)
					't.taxonomy'  => 'tag',
				],
				'GROUP' => 't.id',
				'ORDER' => ['t.name' => 'ASC'],
			]
		);

		//Add the vote IDs to the tags array
		// Fetch all tag_id/vote_id pairs for these votes (one query)
		$links = $fidb->select(
			TB_VOTE_TAGS,
			[
				'tag_id',
				'vote_id',
			],
			[
				'vote_id' => $vote_ids, // IN (...)
				'ORDER' => [
					'tag_id'  => 'ASC',
					'vote_id' => 'ASC',
				],
			]
		);

		// Build map tag_id => [vote_id, ...]
		$tag_votes_map = [];
		foreach ((array) $links as $lnk) {
			$tid = (int) $lnk['tag_id'];
			$tag_votes_map[$tid][] = (int) $lnk['vote_id'];
		}

		// Attach to your existing $vote_tags result
		foreach ($vote_tags as &$tag) {
			$tid = (int) $tag['id'];
			$tv = $tag_votes_map[$tid] ?? [];
			$tag['votes'] = $tv;
			$tag['score'] = fi_api_vote_score($votes_data, $tv);
		}
		unset($tag);

		$leg['vote_tags'] = $vote_tags;
	}


//Note: Limit votes to the chamber the person is in for each session

	//SESSION AND VOTE DATA
	$session_ids = [];
	if ($sessions) {
		$i=0;
		$sess = [];
		foreach ($sessions as $s) {
			$i++;
			$SID = $s['session_id'];
			$session_ids[] = $SID;
			$chamber = $s['chamber'];

			$dist_info = fi_api_taxonomy_get('district', $s['district']);
			// IF GOVE=US Strip state name from district name
			if(isset($s['state']) && !empty($s['state'])){
				$dist_info['name_short'] = str_replace($s['state'].' ', '', $dist_info['name']);
			}

			$s['district_info'] = $dist_info;
			$s['gov_name'] = FI_GOVERNMENTS[$s['gov']];
			$s['party_name'] = FI_PARTIES[$s['party']]['name'];
			$s['chamber_label'] = FI_CHAMBERS[$s['gov']][$chamber]['chamber'];
			$s['chamber_title'] = FI_CHAMBERS[$s['gov']][$chamber]['title'];
			$s['state_name'] = FI_GOVERNMENTS[$s['state']];

			// Add " Congress" suffix for US sessions
			if (!empty($s['session_name']) && strtoupper($s['gov'] ?? '') === 'US' && strpos($s['session_name'], 'Congress') === false) {
				$s['session_name'] .= ' Congress';
			}
			//flatten most recent into leg object as "current" session
			if($i == 1){
				$leg = array_merge($leg, $s);
			}

			/* Get session vote ids */
			$session_vote_ids = $fidb->select(
				TB_VOTES,
				'id',
				[
					'session_id' => $SID,
					'chamber' => $chamber,
					'ORDER' => ['date_voted' => 'DESC'],
				]
			);
			$s['votes'] = $session_vote_ids;

			/*Get session reports	
			* Rank formats:
			* 2 = scorecard (first)
			* 1 = freedomindex (second)
			* 0 = everything else (last)
			*/
			$now_mysql = date('Y-m-d H:i:s'); // lean (no WP current_time)
			$order_sql = "
				CASE
					WHEN LOWER(TRIM(COALESCE(format,''))) = 'scorecard' THEN 2
					WHEN LOWER(TRIM(COALESCE(format,''))) = 'freedomindex' THEN 1
					ELSE 0
				END DESC,
				date_publish DESC,
				id DESC
			";

			$reports = $fidb->select(
				TB_REPORTS,
				[
					'id',
					'session_id',
					'gov',
					'title',
					'slug',
					'format',
					'status',
					'date_publish',
					'payload_json',
				],
				[
					'AND' => [
						'session_id' => (int) $SID,
						'status'     => 'publish',
						'OR' => [
							'date_publish'     => null,
							'date_publish[<=]' => $now_mysql,
						],
					],
					// IMPORTANT: raw ORDER string so Medoo doesn't backtick-quote it as a column name
					'ORDER' => $fidb->raw($order_sql),
				]
			);
			//Decode json payload
			foreach ($reports as &$report) {
				$payload = json_decode($report['payload_json'], true);
				if($chamber == 'S'){
					unset($payload['votes_h']);
					unset($payload['votes_h_order']);
					$vscore = $payload['votes_s'] ?? null;
				}elseif($chamber == 'H'){
					unset($payload['votes_s']);
					unset($payload['votes_s_order']);
					$vscore = $payload['votes_h'] ?? null;
				}
				$report['payload_json'] = $payload;
				$report['score'] = fi_api_vote_score($votes_data, $vscore);
			}
			unset($report); // break reference
			$s['reports'] = $reports;

			// Add this session to the sessions array
			$sess[$s['session_id']] = $s;
		}
		$leg['sessions'] = $sess;
	}




//Output time after vote tags query
$runTime = microtime(true) - $start_time;
//echo "Time after all queries: " . (microtime(true) - $start_time) . " seconds\n";

//print_r($leg);exit;



	echo json_encode([
		'success' => true,
		'message' => 'Legislator data fetched in ' . round($runTime, 4) . ' seconds',
		'action' => 'legislator',
		'results' => $leg,
	], JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
	echo json_encode([
		'success' => false,
		'error' => 'exception',
		'message' => 'API error',
		'detail' => [
			'msg' => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
		],
	], JSON_UNESCAPED_SLASHES);
}
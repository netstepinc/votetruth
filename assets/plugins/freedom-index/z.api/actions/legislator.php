<?php if(!defined('ABSPATH')) exit;
/* Single Legislator by ID
* Replicate full Legislator object like the example below.
* Legislator
* 	Sessions in which they served
*		All votes for session by the legislator's chamber
*		Reports for session by the legislator's chamber
*	Votes Cast
*	Vote Tags containing votes cast by the legislator

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
https://votetruth.us/fi_api.php?key=5f71b6205a7fef749f412c21ec971e43&action=legislator&legislator_id=995
https://votetruth.us/fi_api.php?key=5f71b6205a7fef749f412c21ec971e43&action=legislator&legislator_id=1414
*/
header('Content-Type: application/json; charset=utf-8');

//SCORE a batch of votes
function fi_api_vote_score($votes_data, $vote_ids){
	$counted = 0;
	$matched = 0;
	$total = count($vote_ids);
	$half = round($total / 2,0);
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
	if($counted == 0){
		return ['total' => $total, 'counted' => $counted, 'matched' => $matched, 'score' => 'NA'];
	}

	if($counted < $half){
		$score = 'NA';
	}else{
		$score = round(($matched / $counted) * 100, 0);
	}
	$scoring = [
		'total' => $total,
		'counted' => $counted,
		'matched' => $matched,
		'score' => $score,
	];
	return $scoring;
	//return ['ids' => $vote_ids,'data' => $votes_data,'counted' => $counted,'matched' => $matched,];
}

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
	if(isset($leg['freedom_score_data']) && !empty($leg['freedom_score_data'])){
		$leg['freedom_score_data'] = json_decode($leg['freedom_score_data'], true);
	}

	$leg['url'] = SITE_URL.'/legislator/'.$LID.'/';

	$meta = json_decode($leg['meta'], true);
	//Cleanup meta
	unset($meta['legacy_post']);
	unset($meta['legiscan_data']);
	$leg['meta'] = $meta;

	//$leg['sessions'] = [];
	$leg['votes'] = [];
	$leg['vote_tags'] = [];
	$leg['votes_cast'] = [];

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

	$votes_cast_ids = array_keys($votes_cast);
	$votes_cast_ids = array_values(array_unique(array_map('intval', $votes_cast_ids)));


	// SESSIONS
	// Get legislator PARENT sessions and session info only for public display
	// BUT we must fetch all votes in the child sessions because Legiscan data/votes may be tied to child sessions.
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
			's.date_start',
			's.date_end',
			//'s.id(session_pk)',
			's.name(session_name)',
			//'s.parent_id(session_parent_id)',
		],
		[
			'AND' => [
				'ls.legislator_id' => $LID,
				's.parent_id'      => null,   // ONLY parent sessions
				's.status'         => 'publish',
			],
			'ORDER' => [
				's.date_end' => 'DESC',
				's.id'       => 'DESC',
			],
		]
	);

	//SESSION AND VOTE DATA
	$votes_data = [];
	if ($sessions) {
		$i=0;
		$sess = [];
		foreach ($sessions as $s) {
			$i++;
			$SID = $s['session_id'];
			$chamber = $s['chamber'];

			if(isset($s['district']) && !empty($s['district'])){
				$district_info = fi_api_taxonomy_get('district', $s['district']);
				// IF GOVE=US Strip state name from district name
				if(isset($s['state']) && !empty($s['state'])){
					$district_info['name_short'] = str_replace($s['state'].' ', '', $district_info['name']);
				}
			}else{
				$district_info = [];
			}

			$s['district_info'] = $district_info;
			$s['gov_name'] = FI_GOVERNMENTS[$s['gov']];
			if(isset($s['party']) && !empty($s['party']) && isset(FI_PARTIES[$s['party']])){
				$s['party_name'] = FI_PARTIES[$s['party']]['name'];
			}else{
				$s['party_name'] = '';
			}
			$s['chamber_label'] = FI_CHAMBERS[$s['gov']][$chamber]['chamber'];
			$s['chamber_title'] = FI_CHAMBERS[$s['gov']][$chamber]['title'];
			if(isset($s['state']) && !empty($s['state'])){
				$s['state_name'] = FI_GOVERNMENTS[$s['state']];
			}

			// Add " Congress" suffix for US sessions
			if (!empty($s['session_name']) && strtoupper($s['gov'] ?? '') === 'US' && strpos($s['session_name'], 'Congress') === false) {
				$s['session_name'] .= ' Congress';
			}
			//flatten most recent into leg object as "current" session
			if($i == 1){
				$leg = array_merge($leg, $s);
			}


			//Queue up child and parent sessions for votes query
			$vote_query_sessions = $fidb->select(
				TB_SESSIONS,
				'id',
				[
					'parent_id' => $SID,
					'status'    => 'publish',
				]
			);
			$vote_query_sessions[] = $SID;

			//Get session vote ids
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
					'session_id' => $vote_query_sessions,
					'chamber'    => $chamber,
					'status'     => 'publish',
					'ORDER'      => ['date_voted' => 'DESC'],
				]
			);

			//Hydrate and prune votes data
			$scoring_vote_data = [];//session vote score data
			$session_vote_ids = [];
			foreach ((array) $votes as $v) {
				$vid = (int) $v['id'];
				$session_vote_ids[] = $vid;
				$gov = $v['gov'];
				$chamber = $v['chamber'];

				$meta = json_decode($v['meta'], true);
				unset($meta['legacy']);
				unset($meta['legiscan']);
				unset($meta['legiscan_rollcall_audit']);
				unset($meta['legiscan_session_id']);
				$v['meta'] = $meta;

				$v['chamber_label'] = FI_CHAMBERS[$gov][$chamber]['chamber'];

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
				$v['url_vote'] = 'https://votetruth.us/'.strtolower($gov).'/vote/'.$vid.'/';

				$search_description = '';
				if(isset($meta['description_long']) && !empty($meta['description_long'])){
					$search_description .= $meta['description_long'];
				}elseif(isset($meta['description_medium']) && !empty($meta['description_medium'])){
					$search_description .= $meta['description_medium'];
				}elseif(isset($meta['description_short']) && !empty($meta['description_short'])){
					$search_description .= $meta['description_short'];
				}
				$v['search_text'] = strtolower(($v['title']) . ' ' . $v['bill_number'] . ' '. strip_tags($search_description));

				//Compare vote cast with constitutional and determine if counted. Handle votes if not cast.
				if(in_array($vid, $votes_cast_ids)){
					$cast = $votes_cast[$vid];
					if(in_array($cast, ['P', 'A', 'X', ''])){
						$cast = 'X';
					}
				}else{
					$cast = 'X';
				}

				$v['cast'] = $cast;
				$v['matched'] = null;
				$v['counted'] = null;

				$constitutional = $v['constitutional'] ?? '';
				// Only Y/N count toward scored votes (P/A/X are "not voted")
				if ($cast != 'X' && !empty($constitutional)) {
					$const = $v['constitutional'] ?? '';
					$v['counted'] = true;
					if($const == $cast){
						$v['matched'] = true;
					}else{
						$v['matched'] = false;
					}
				}

				$scoring_vote_data[$vid] = [
					'cast' => $cast,
					'matched' => $v['matched'],
					'counted' => $v['counted'],
					'constitutional' => $constitutional,
				];

				$votes_data[$vid] = $v;
			}
			$scoring = fi_api_vote_score($scoring_vote_data, $session_vote_ids);
			$s['score'] = $scoring['score'];
			$s['score_data'] = $scoring;
			$s['votes'] = $session_vote_ids;


			//Get session reports | Rank formats: 2 = scorecard (first) | 1 = freedomindex (second) | 0 = everything else (last)
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
					'title_menu',
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
			$report_list = [];
			$count_scorecard = 0;
			foreach ($reports as $report) {
				$rdata = $report;
				$report_votes = [];
				$payload = json_decode($report['payload_json'], true);
				unset($rdata['payload_json']);
				unset($payload['legacy_votes_s']);
				unset($payload['legacy_votes_h']);
				if($chamber == 'S'){
					unset($payload['votes_h']);
					unset($payload['votes_h_order']);
					//Specify 1 list of votes for the report and score calculatoin
					if(isset($payload['votes_s_order']) && is_array($payload['votes_s_order']) && !empty($payload['votes_s_order'])){
						$report_votes = $payload['votes_s_order'];
					}elseif(isset($payload['votes_s']) && is_array($payload['votes_s']) && !empty($payload['votes_s'])){
						$report_votes = $payload['votes_s'];
					}
					unset($payload['votes_s']);
					unset($payload['votes_s_order']);
				}elseif($chamber == 'H'){
					unset($payload['votes_s']);
					unset($payload['votes_s_order']);
					if(isset($payload['votes_h_order']) && is_array($payload['votes_h_order']) && !empty($payload['votes_h_order'])){
						$report_votes = $payload['votes_h_order'];
					}elseif(isset($payload['votes_h']) && is_array($payload['votes_h']) && !empty($payload['votes_h'])){
						$report_votes = $payload['votes_h'];
					}
					unset($payload['votes_h']);
					unset($payload['votes_h_order']);
				}
				$rdata['payload'] = $payload;
				$scoring = fi_api_vote_score($scoring_vote_data, $report_votes);
				$rdata['score'] = $scoring['score'];
				$rdata['score_data'] = $scoring;
				$rdata['votes'] = $report_votes;
				//If this is the first session and the first report where format=scorecard then add $leg['latest_scorecard'] = $rdata;
				if($rdata['format'] == 'scorecard'){
					$count_scorecard++;
					if($count_scorecard == 1 && $i == 1){
						//Only send what is necessary
						$lsc = $rdata;
						unset($lsc['slug']);
						unset($lsc['score']);
						unset($lsc['votes']);
						unset($lsc['score_data']);
						unset($lsc['payload']);
						$leg['latest_scorecard'] = $lsc;
					}
				}
				$report_list[] = $rdata;
			}
			$s['reports'] = $report_list;

			// Add this session to the sessions array
			$sess[$s['session_id']] = $s;
		}
		$leg['sessions'] = $sess;
	}
	$leg['votes'] = $votes_data;

	//VOTE TAGS
	if (!empty($votes_cast_ids)) {
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
					'vt.vote_id'  => $votes_cast_ids, // Medoo turns array into IN (...)
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
				'vote_id' => $votes_cast_ids, // IN (...)
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
			$scoring = fi_api_vote_score($votes_data, $tv);
			$tag['score'] = $scoring['score'];
			$tag['score_data'] = $scoring;
		}
		unset($tag);

		$leg['vote_tags'] = $vote_tags;
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
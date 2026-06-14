<?php if(!defined('ABSPATH')) exit;
/* us_senate_rollcall fetcher
code to parse and display xml feed data array.
US Senate Rollcall (HTML): https://www.senate.gov/legislative/LIS/roll_call_votes/vote1182/vote_118_2_00176.htm
US Senate Rollcall (XML):  https://www.senate.gov/legislative/LIS/roll_call_votes/vote1182/vote_118_2_00176.xml


//HANDLE OLD URLS
US Senate Rollcall (HTML): https://www.senate.gov/legislative/LIS/roll_call_lists/roll_call_vote_cfm.cfm?congress=108&session=2&vote=00088
US Senate Rollcall (XML):  https://www.senate.gov/legislative/LIS/roll_call_votes/vote1082/vote_108_2_00088.xml

**** PROBLEM: The LIS ID is not the same as the Bioguide ID. ****
CROSS REFERENCE DATA: https://github.com/unitedstates/congress-legislators
*/

//If the URL is a HTML file, convert it to a XML file
if(stripos($url, '.htm') !== false){
	$url = str_replace('.htm', '.xml', $url);
}
//handle old URL conversion
else{
	//CONVERT THIS FORMAT: https://www.senate.gov/legislative/LIS/roll_call_lists/roll_call_vote_cfm.cfm?congress=108&session=2&vote=00088
	//INTO THIS FORMAT:  https://www.senate.gov/legislative/LIS/roll_call_votes/vote1082/vote_108_2_00088.xml
	$parts = parse_url($url);
	if (!empty($parts['query'])) {
		parse_str($parts['query'], $q);
		if (isset($q['congress'], $q['session'], $q['vote'])) {
			$base = 'https://www.senate.gov/legislative/LIS/roll_call_votes';
			$dir = 'vote' . (int) $q['congress'] . (int) $q['session'];
			$file = 'vote_' . (int) $q['congress'] . '_' . (int) $q['session'] . '_' . str_pad((string) $q['vote'], 5, '0', STR_PAD_LEFT) . '.xml';
			$url = $base . '/' . $dir . '/' . $file;
		}
	}
}

global $wpdb;
$data = fi_api_gov_fetch_data($url);
if(is_wp_error($data)){
	echo '<div class="alert alert-danger">'.$data->get_error_message().'</div>';
	return;
}
fi_api_gov_card_top('Senate Rollcall Data');
?>
<table class="table table-sm table-bordered table-striped">
	<thead>
		<tr>
			<th style="width: 180px;">Key</th>
			<th colspan="2">Value</th>
		</tr>
	</thead>
	<tbody>
<?php
$vote_totals = [];
if (isset($data['vote-metadata']['vote-totals'])) {
	$totals = $data['vote-metadata']['vote-totals']; 
	unset($data['vote-metadata']['vote-totals']);
	$vote_totals = $totals['totals-by-vote'] ?? [];
}

$meta = $data['vote-metadata'] ?? $data; 
unset($meta['vote-desc']);
unset($meta['action-time']);
unset($meta['vote-totals']);
unset($meta['members']);
unset($meta['vote-data']);

if (empty($vote_totals) && isset($data['count'])) {
	$vote_totals = $data['count'];
}

$lis_xref = fi_legislators_get_lis_xref($session_id);
$fi_rc = fi_rollcalls_legislators_cast_by_vote($vote_id);
$rc_data = $data['vote-data']['recorded-vote'] ?? ($data['members']['member'] ?? []); 
if (!is_array($rc_data)) {
	$rc_data = [];
}

foreach($meta as $key => $value){
	switch($key){
		case 'action-date':
			//Convert 10-Sep-2025 to mysql datetime format 
			$value = date('Y-m-d', strtotime($value));
			$import['date_votes'] = $value;
			break;
	}
	//Determine which other values to import with rollcall data then compile all into hiddend fields that can be posted to a vote update form with one button to import
	$value_display = is_array($value) ? wp_json_encode($value) : (string) $value;
	echo '<tr><td>'.esc_html($key).'</td><td colspan="2">'.esc_html($value_display).'</td></tr>';
}

//Vote totals
echo '<tr><td colspan="3"><b>Vote Totals</b></td></tr>';
foreach($vote_totals as $key => $value){
	$value_display = is_array($value) ? wp_json_encode($value) : (string) $value;
	echo '<tr><td class="ps-3">'.esc_html($key).'</td><td colspan="2">'.esc_html($value_display).'</td></tr>';
}

//Rollcall data evaluation
echo '<tr><td colspan="2" class="fw-bold bg-dark text-white">Recorded Votes</td><td class="fw-bold bg-dark text-white">Vote Cast</td><tr>';
foreach($rc_data as $vote){
	$legislator_id = null;
	$lis_id = $vote['legislator']['@attributes']['name-id']
		?? $vote['lis_member_id']
		?? '';
	$state = $vote['legislator']['@attributes']['state']
		?? $vote['state']
		?? '';
	$sort_field = $vote['legislator']['@attributes']['sort-field']
		?? $vote['last_name']
		?? $vote['member_full']
		?? '';
	if($state != 'XX'){
		if($lis_id && isset($lis_xref[$lis_id])){
			$legislator_id = $lis_xref[$lis_id];
		}
		//Convert vote to Y, N, X cast ('Y', 'N', 'P', 'A', 'X')
		$vote_cast = $vote['vote'] ?? $vote['vote_cast'] ?? '';
		$cast = fi_rollcall_cast_normalize($vote_cast);


		//Determine if the vote cast is in the rollcalls array
		if(isset($fi_rc[$legislator_id])){
			$rollcall_id = $fi_rc[$legislator_id]['id'];
			$rollcall_cast = $fi_rc[$legislator_id]['cast'];
		}else{
			$rollcall_id = null;
			$rollcall_cast = null;
		}
		$link_text = $sort_field;

		if($legislator_id){
			if($rollcall_id){
				if($rollcall_cast !== $cast){
					$rollcall_eval = '<span class="text-warning fw-bold">' . $legislator_id . '=' . $cast .' VOTE DIFF</span>';
				}else{
					$rollcall_eval = '<span class="text-success fw-bold">' . $legislator_id . '=' . $cast .' SAVED</span>';
				}
			}else{
				$rollcall_eval = '<span class="text-dark">' . $legislator_id . '=' . $cast .'</span>';
				//Import rollcall dataon the fly: fi_voterc::id, vote_id, legislator_id, cast, is_override, date_created
				$import = [
					'vote_id' => $vote_id,
					'legislator_id' => $legislator_id,
					'cast' => $cast,
				];
				$insert = $wpdb->insert(TBFI_VOTERC, $import);
				if($insert){
					$rollcall_eval .= '<span class="text-success fw-bold ms-2">IMPORTED</span>';
				}else{
					$rollcall_eval .= '<span class="text-danger ms-2">FAILED</span>';
				}
			}
		}else{
			$rollcall_eval = '<span class="text-danger">[No Match=' . $cast .']</span>';
			$link_text = $vote['first_name'].' '.$vote['last_name'];
		}

		echo '<tr><td class="ps-3">'.$lis_id;
		echo '<span class="text-muted ms-1"><a href="' . admin_url('admin.php?page=fi-legislators&search='.$sort_field.'&session_id&chamber=senate&party') . '" target="_blank">' . $link_text.'|'.$state.'</a></span>';
		echo '</td>';
		echo '<td>'.$vote_cast.'</td>';
		echo '<td class="text-nowrap">'.$rollcall_eval.'</td></tr>';
	}
}
?>
	</tbody>
</table>
<?php
fi_api_gov_card_bottom();


/* EXAMPLE $data array
Array
(
    [congress] => 119
    [session] => 1
    [congress_year] => 2025
    [vote_number] => 644
    [vote_date] => December 11, 2025,  12:29 PM
    [modify_date] => December 11, 2025,  01:15 PM
    [vote_question_text] => On the Cloture Motion S. 3385
    [vote_document_text] => A bill to amend the Internal Revenue Code of 1986 to extend the enhancement of the health care premium tax credit.
    [vote_result_text] => Cloture Motion Rejected (51-48, 3/5 majority required)
    [question] => On the Cloture Motion
    [vote_title] => Motion to Invoke Cloture: Motion to Proceed to S. 3385
    [majority_requirement] => 3/5
    [vote_result] => Cloture Motion Rejected
    [document] => Array
        (
            [document_congress] => 119
            [document_type] => S.
            [document_number] => 3385
            [document_name] => S. 3385
            [document_title] => A bill to amend the Internal Revenue Code of 1986 to extend the enhancement of the health care premium tax credit.
            [document_short_title] => Array
                (
                )

        )

    [amendment] => Array
        (
            [amendment_number] => Array
                (
                )

            [amendment_to_amendment_number] => Array
                (
                )

            [amendment_to_amendment_to_amendment_number] => Array
                (
                )

            [amendment_to_document_number] => Array
                (
                )

            [amendment_to_document_short_title] => Array
                (
                )

            [amendment_purpose] => No Statement of Purpose on File.
        )

    [count] => Array
        (
            [yeas] => 51
            [nays] => 48
            [present] => Array
                (
                )

            [absent] => 1
        )

    [tie_breaker] => Array
        (
            [by_whom] => Array
                (
                )

            [tie_breaker_vote] => Array
                (
                )

        )

    [members] => Array
        (
            [member] => Array
                (
                    [0] => Array
                        (
                            [member_full] => Alsobrooks (D-MD)
                            [last_name] => Alsobrooks
                            [first_name] => Angela
                            [party] => D
                            [state] => MD
                            [vote_cast] => Yea
                            [lis_member_id] => S428
                        )

                    [1] => Array
                        (
                            [member_full] => Baldwin (D-WI)
                            [last_name] => Baldwin
                            [first_name] => Tammy
                            [party] => D
                            [state] => WI
                            [vote_cast] => Yea
                            [lis_member_id] => S354
                        )

                    [2] => Array
                        (
                            [member_full] => Banks (R-IN)
                            [last_name] => Banks
                            [first_name] => Jim
                            [party] => R
                            [state] => IN
                            [vote_cast] => Nay
                            [lis_member_id] => S429
                        )

                    [3] => Array
                        (
                            [member_full] => Barrasso (R-WY)
                            [last_name] => Barrasso
                            [first_name] => John
                            [party] => R
                            [state] => WY
                            [vote_cast] => Nay
                            [lis_member_id] => S317
                        )

                    [4] => Array
                        (
                            [member_full] => Bennet (D-CO)
                            [last_name] => Bennet
                            [first_name] => Michael
                            [party] => D
                            [state] => CO
                            [vote_cast] => Yea
                            [lis_member_id] => S330
                        )

                    [5] => Array
                        (
                            [member_full] => Blackburn (R-TN)
                            [last_name] => Blackburn
                            [first_name] => Marsha
                            [party] => R
                            [state] => TN
                            [vote_cast] => Nay
                            [lis_member_id] => S396
                        )

                    [6] => Array
                        (
                            [member_full] => Blumenthal (D-CT)
                            [last_name] => Blumenthal
                            [first_name] => Richard
                            [party] => D
                            [state] => CT
                            [vote_cast] => Yea
                            [lis_member_id] => S341
                        )

                    [7] => Array
                        (
                            [member_full] => Blunt Rochester (D-DE)
                            [last_name] => Blunt Rochester
                            [first_name] => Lisa
                            [party] => D
                            [state] => DE
                            [vote_cast] => Yea
                            [lis_member_id] => S430
                        )

                    [8] => Array
                        (
                            [member_full] => Booker (D-NJ)
                            [last_name] => Booker
                            [first_name] => Cory
                            [party] => D
                            [state] => NJ
                            [vote_cast] => Yea
                            [lis_member_id] => S370
                        )

                    [9] => Array
                        (
                            [member_full] => Boozman (R-AR)
                            [last_name] => Boozman
                            [first_name] => John
                            [party] => R
                            [state] => AR
                            [vote_cast] => Nay
                            [lis_member_id] => S343
                        )

                    [10] => Array
                        (
                            [member_full] => Britt (R-AL)
                            [last_name] => Britt
                            [first_name] => Katie
                            [party] => R
                            [state] => AL
                            [vote_cast] => Nay
                            [lis_member_id] => S416
                        )

                    [11] => Array
                        (
                            [member_full] => Budd (R-NC)
                            [last_name] => Budd
                            [first_name] => Ted
                            [party] => R
                            [state] => NC
                            [vote_cast] => Nay
                            [lis_member_id] => S417
                        )

                    [12] => Array
                        (
                            [member_full] => Cantwell (D-WA)
                            [last_name] => Cantwell
                            [first_name] => Maria
                            [party] => D
                            [state] => WA
                            [vote_cast] => Yea
                            [lis_member_id] => S275
                        )

                    [13] => Array
                        (
                            [member_full] => Capito (R-WV)
                            [last_name] => Capito
                            [first_name] => Shelley
                            [party] => R
                            [state] => WV
                            [vote_cast] => Nay
                            [lis_member_id] => S372
                        )

                    [14] => Array
                        (
                            [member_full] => Cassidy (R-LA)
                            [last_name] => Cassidy
                            [first_name] => Bill
                            [party] => R
                            [state] => LA
                            [vote_cast] => Nay
                            [lis_member_id] => S373
                        )

                    [15] => Array
                        (
                            [member_full] => Collins (R-ME)
                            [last_name] => Collins
                            [first_name] => Susan
                            [party] => R
                            [state] => ME
                            [vote_cast] => Yea
                            [lis_member_id] => S252
                        )

                    [16] => Array
                        (
                            [member_full] => Coons (D-DE)
                            [last_name] => Coons
                            [first_name] => Christopher
                            [party] => D
                            [state] => DE
                            [vote_cast] => Yea
                            [lis_member_id] => S337
                        )

                    [17] => Array
                        (
                            [member_full] => Cornyn (R-TX)
                            [last_name] => Cornyn
                            [first_name] => John
                            [party] => R
                            [state] => TX
                            [vote_cast] => Nay
                            [lis_member_id] => S287
                        )

                    [18] => Array
                        (
                            [member_full] => Cortez Masto (D-NV)
                            [last_name] => Cortez Masto
                            [first_name] => Catherine
                            [party] => D
                            [state] => NV
                            [vote_cast] => Yea
                            [lis_member_id] => S385
                        )

                    [19] => Array
                        (
                            [member_full] => Cotton (R-AR)
                            [last_name] => Cotton
                            [first_name] => Tom
                            [party] => R
                            [state] => AR
                            [vote_cast] => Nay
                            [lis_member_id] => S374
                        )

                    [20] => Array
                        (
                            [member_full] => Cramer (R-ND)
                            [last_name] => Cramer
                            [first_name] => Kevin
                            [party] => R
                            [state] => ND
                            [vote_cast] => Nay
                            [lis_member_id] => S398
                        )

                    [21] => Array
                        (
                            [member_full] => Crapo (R-ID)
                            [last_name] => Crapo
                            [first_name] => Mike
                            [party] => R
                            [state] => ID
                            [vote_cast] => Nay
                            [lis_member_id] => S266
                        )

                    [22] => Array
                        (
                            [member_full] => Cruz (R-TX)
                            [last_name] => Cruz
                            [first_name] => Ted
                            [party] => R
                            [state] => TX
                            [vote_cast] => Nay
                            [lis_member_id] => S355
                        )

                    [23] => Array
                        (
                            [member_full] => Curtis (R-UT)
                            [last_name] => Curtis
                            [first_name] => John
                            [party] => R
                            [state] => UT
                            [vote_cast] => Nay
                            [lis_member_id] => S431
                        )

                    [24] => Array
                        (
                            [member_full] => Daines (R-MT)
                            [last_name] => Daines
                            [first_name] => Steve
                            [party] => R
                            [state] => MT
                            [vote_cast] => Not Voting
                            [lis_member_id] => S375
                        )

                    [25] => Array
                        (
                            [member_full] => Duckworth (D-IL)
                            [last_name] => Duckworth
                            [first_name] => Tammy
                            [party] => D
                            [state] => IL
                            [vote_cast] => Yea
                            [lis_member_id] => S386
                        )

                    [26] => Array
                        (
                            [member_full] => Durbin (D-IL)
                            [last_name] => Durbin
                            [first_name] => Richard
                            [party] => D
                            [state] => IL
                            [vote_cast] => Yea
                            [lis_member_id] => S253
                        )

                    [27] => Array
                        (
                            [member_full] => Ernst (R-IA)
                            [last_name] => Ernst
                            [first_name] => Joni
                            [party] => R
                            [state] => IA
                            [vote_cast] => Nay
                            [lis_member_id] => S376
                        )

                    [28] => Array
                        (
                            [member_full] => Fetterman (D-PA)
                            [last_name] => Fetterman
                            [first_name] => John
                            [party] => D
                            [state] => PA
                            [vote_cast] => Yea
                            [lis_member_id] => S418
                        )

                    [29] => Array
                        (
                            [member_full] => Fischer (R-NE)
                            [last_name] => Fischer
                            [first_name] => Deb
                            [party] => R
                            [state] => NE
                            [vote_cast] => Nay
                            [lis_member_id] => S357
                        )

                    [30] => Array
                        (
                            [member_full] => Gallego (D-AZ)
                            [last_name] => Gallego
                            [first_name] => Ruben
                            [party] => D
                            [state] => AZ
                            [vote_cast] => Yea
                            [lis_member_id] => S432
                        )

                    [31] => Array
                        (
                            [member_full] => Gillibrand (D-NY)
                            [last_name] => Gillibrand
                            [first_name] => Kirsten
                            [party] => D
                            [state] => NY
                            [vote_cast] => Yea
                            [lis_member_id] => S331
                        )

                    [32] => Array
                        (
                            [member_full] => Graham (R-SC)
                            [last_name] => Graham
                            [first_name] => Lindsey
                            [party] => R
                            [state] => SC
                            [vote_cast] => Nay
                            [lis_member_id] => S293
                        )

                    [33] => Array
                        (
                            [member_full] => Grassley (R-IA)
                            [last_name] => Grassley
                            [first_name] => Chuck
                            [party] => R
                            [state] => IA
                            [vote_cast] => Nay
                            [lis_member_id] => S153
                        )

                    [34] => Array
                        (
                            [member_full] => Hagerty (R-TN)
                            [last_name] => Hagerty
                            [first_name] => Bill
                            [party] => R
                            [state] => TN
                            [vote_cast] => Nay
                            [lis_member_id] => S407
                        )

                    [35] => Array
                        (
                            [member_full] => Hassan (D-NH)
                            [last_name] => Hassan
                            [first_name] => Maggie
                            [party] => D
                            [state] => NH
                            [vote_cast] => Yea
                            [lis_member_id] => S388
                        )

                    [36] => Array
                        (
                            [member_full] => Hawley (R-MO)
                            [last_name] => Hawley
                            [first_name] => Josh
                            [party] => R
                            [state] => MO
                            [vote_cast] => Yea
                            [lis_member_id] => S399
                        )

                    [37] => Array
                        (
                            [member_full] => Heinrich (D-NM)
                            [last_name] => Heinrich
                            [first_name] => Martin
                            [party] => D
                            [state] => NM
                            [vote_cast] => Yea
                            [lis_member_id] => S359
                        )

                    [38] => Array
                        (
                            [member_full] => Hickenlooper (D-CO)
                            [last_name] => Hickenlooper
                            [first_name] => John
                            [party] => D
                            [state] => CO
                            [vote_cast] => Yea
                            [lis_member_id] => S408
                        )

                    [39] => Array
                        (
                            [member_full] => Hirono (D-HI)
                            [last_name] => Hirono
                            [first_name] => Mazie
                            [party] => D
                            [state] => HI
                            [vote_cast] => Yea
                            [lis_member_id] => S361
                        )

                    [40] => Array
                        (
                            [member_full] => Hoeven (R-ND)
                            [last_name] => Hoeven
                            [first_name] => John
                            [party] => R
                            [state] => ND
                            [vote_cast] => Nay
                            [lis_member_id] => S344
                        )

                    [41] => Array
                        (
                            [member_full] => Husted (R-OH)
                            [last_name] => Husted
                            [first_name] => Jon
                            [party] => R
                            [state] => OH
                            [vote_cast] => Nay
                            [lis_member_id] => S438
                        )

                    [42] => Array
                        (
                            [member_full] => Hyde-Smith (R-MS)
                            [last_name] => Hyde-Smith
                            [first_name] => Cindy
                            [party] => R
                            [state] => MS
                            [vote_cast] => Nay
                            [lis_member_id] => S395
                        )

                    [43] => Array
                        (
                            [member_full] => Johnson (R-WI)
                            [last_name] => Johnson
                            [first_name] => Ron
                            [party] => R
                            [state] => WI
                            [vote_cast] => Nay
                            [lis_member_id] => S345
                        )

                    [44] => Array
                        (
                            [member_full] => Justice (R-WV)
                            [last_name] => Justice
                            [first_name] => James
                            [party] => R
                            [state] => WV
                            [vote_cast] => Nay
                            [lis_member_id] => S437
                        )

                    [45] => Array
                        (
                            [member_full] => Kaine (D-VA)
                            [last_name] => Kaine
                            [first_name] => Timothy
                            [party] => D
                            [state] => VA
                            [vote_cast] => Yea
                            [lis_member_id] => S362
                        )

                    [46] => Array
                        (
                            [member_full] => Kelly (D-AZ)
                            [last_name] => Kelly
                            [first_name] => Mark
                            [party] => D
                            [state] => AZ
                            [vote_cast] => Yea
                            [lis_member_id] => S406
                        )

                    [47] => Array
                        (
                            [member_full] => Kennedy (R-LA)
                            [last_name] => Kennedy
                            [first_name] => John
                            [party] => R
                            [state] => LA
                            [vote_cast] => Nay
                            [lis_member_id] => S389
                        )

                    [48] => Array
                        (
                            [member_full] => Kim (D-NJ)
                            [last_name] => Kim
                            [first_name] => Andy
                            [party] => D
                            [state] => NJ
                            [vote_cast] => Yea
                            [lis_member_id] => S426
                        )

                    [49] => Array
                        (
                            [member_full] => King (I-ME)
                            [last_name] => King
                            [first_name] => Angus
                            [party] => I
                            [state] => ME
                            [vote_cast] => Yea
                            [lis_member_id] => S363
                        )

                    [50] => Array
                        (
                            [member_full] => Klobuchar (D-MN)
                            [last_name] => Klobuchar
                            [first_name] => Amy
                            [party] => D
                            [state] => MN
                            [vote_cast] => Yea
                            [lis_member_id] => S311
                        )

                    [51] => Array
                        (
                            [member_full] => Lankford (R-OK)
                            [last_name] => Lankford
                            [first_name] => James
                            [party] => R
                            [state] => OK
                            [vote_cast] => Nay
                            [lis_member_id] => S378
                        )

                    [52] => Array
                        (
                            [member_full] => Lee (R-UT)
                            [last_name] => Lee
                            [first_name] => Mike
                            [party] => R
                            [state] => UT
                            [vote_cast] => Nay
                            [lis_member_id] => S346
                        )

                    [53] => Array
                        (
                            [member_full] => Lujan (D-NM)
                            [last_name] => Lujan
                            [first_name] => Ben
                            [party] => D
                            [state] => NM
                            [vote_cast] => Yea
                            [lis_member_id] => S409
                        )

                    [54] => Array
                        (
                            [member_full] => Lummis (R-WY)
                            [last_name] => Lummis
                            [first_name] => Cynthia
                            [party] => R
                            [state] => WY
                            [vote_cast] => Nay
                            [lis_member_id] => S410
                        )

                    [55] => Array
                        (
                            [member_full] => Markey (D-MA)
                            [last_name] => Markey
                            [first_name] => Edward
                            [party] => D
                            [state] => MA
                            [vote_cast] => Yea
                            [lis_member_id] => S369
                        )

                    [56] => Array
                        (
                            [member_full] => Marshall (R-KS)
                            [last_name] => Marshall
                            [first_name] => Roger
                            [party] => R
                            [state] => KS
                            [vote_cast] => Nay
                            [lis_member_id] => S411
                        )

                    [57] => Array
                        (
                            [member_full] => McConnell (R-KY)
                            [last_name] => McConnell
                            [first_name] => Mitch
                            [party] => R
                            [state] => KY
                            [vote_cast] => Nay
                            [lis_member_id] => S174
                        )

                    [58] => Array
                        (
                            [member_full] => McCormick (R-PA)
                            [last_name] => McCormick
                            [first_name] => David
                            [party] => R
                            [state] => PA
                            [vote_cast] => Nay
                            [lis_member_id] => S433
                        )

                    [59] => Array
                        (
                            [member_full] => Merkley (D-OR)
                            [last_name] => Merkley
                            [first_name] => Jeff
                            [party] => D
                            [state] => OR
                            [vote_cast] => Yea
                            [lis_member_id] => S322
                        )

                    [60] => Array
                        (
                            [member_full] => Moody (R-FL)
                            [last_name] => Moody
                            [first_name] => Ashley
                            [party] => R
                            [state] => FL
                            [vote_cast] => Nay
                            [lis_member_id] => S439
                        )

                    [61] => Array
                        (
                            [member_full] => Moran (R-KS)
                            [last_name] => Moran
                            [first_name] => Jerry
                            [party] => R
                            [state] => KS
                            [vote_cast] => Nay
                            [lis_member_id] => S347
                        )

                    [62] => Array
                        (
                            [member_full] => Moreno (R-OH)
                            [last_name] => Moreno
                            [first_name] => Bernie
                            [party] => R
                            [state] => OH
                            [vote_cast] => Nay
                            [lis_member_id] => S434
                        )

                    [63] => Array
                        (
                            [member_full] => Mullin (R-OK)
                            [last_name] => Mullin
                            [first_name] => Markwayne
                            [party] => R
                            [state] => OK
                            [vote_cast] => Nay
                            [lis_member_id] => S419
                        )

                    [64] => Array
                        (
                            [member_full] => Murkowski (R-AK)
                            [last_name] => Murkowski
                            [first_name] => Lisa
                            [party] => R
                            [state] => AK
                            [vote_cast] => Yea
                            [lis_member_id] => S288
                        )

                    [65] => Array
                        (
                            [member_full] => Murphy (D-CT)
                            [last_name] => Murphy
                            [first_name] => Christopher
                            [party] => D
                            [state] => CT
                            [vote_cast] => Yea
                            [lis_member_id] => S364
                        )

                    [66] => Array
                        (
                            [member_full] => Murray (D-WA)
                            [last_name] => Murray
                            [first_name] => Patty
                            [party] => D
                            [state] => WA
                            [vote_cast] => Yea
                            [lis_member_id] => S229
                        )

                    [67] => Array
                        (
                            [member_full] => Ossoff (D-GA)
                            [last_name] => Ossoff
                            [first_name] => Jon
                            [party] => D
                            [state] => GA
                            [vote_cast] => Yea
                            [lis_member_id] => S414
                        )

                    [68] => Array
                        (
                            [member_full] => Padilla (D-CA)
                            [last_name] => Padilla
                            [first_name] => Alex
                            [party] => D
                            [state] => CA
                            [vote_cast] => Yea
                            [lis_member_id] => S413
                        )

                    [69] => Array
                        (
                            [member_full] => Paul (R-KY)
                            [last_name] => Paul
                            [first_name] => Rand
                            [party] => R
                            [state] => KY
                            [vote_cast] => Nay
                            [lis_member_id] => S348
                        )

                    [70] => Array
                        (
                            [member_full] => Peters (D-MI)
                            [last_name] => Peters
                            [first_name] => Gary
                            [party] => D
                            [state] => MI
                            [vote_cast] => Yea
                            [lis_member_id] => S380
                        )

                    [71] => Array
                        (
                            [member_full] => Reed (D-RI)
                            [last_name] => Reed
                            [first_name] => John
                            [party] => D
                            [state] => RI
                            [vote_cast] => Yea
                            [lis_member_id] => S259
                        )

                    [72] => Array
                        (
                            [member_full] => Ricketts (R-NE)
                            [last_name] => Ricketts
                            [first_name] => Pete
                            [party] => R
                            [state] => NE
                            [vote_cast] => Nay
                            [lis_member_id] => S423
                        )

                    [73] => Array
                        (
                            [member_full] => Risch (R-ID)
                            [last_name] => Risch
                            [first_name] => James
                            [party] => R
                            [state] => ID
                            [vote_cast] => Nay
                            [lis_member_id] => S323
                        )

                    [74] => Array
                        (
                            [member_full] => Rosen (D-NV)
                            [last_name] => Rosen
                            [first_name] => Jacky
                            [party] => D
                            [state] => NV
                            [vote_cast] => Yea
                            [lis_member_id] => S402
                        )

                    [75] => Array
                        (
                            [member_full] => Rounds (R-SD)
                            [last_name] => Rounds
                            [first_name] => Mike
                            [party] => R
                            [state] => SD
                            [vote_cast] => Nay
                            [lis_member_id] => S381
                        )

                    [76] => Array
                        (
                            [member_full] => Sanders (I-VT)
                            [last_name] => Sanders
                            [first_name] => Bernie
                            [party] => I
                            [state] => VT
                            [vote_cast] => Yea
                            [lis_member_id] => S313
                        )

                    [77] => Array
                        (
                            [member_full] => Schatz (D-HI)
                            [last_name] => Schatz
                            [first_name] => Brian
                            [party] => D
                            [state] => HI
                            [vote_cast] => Yea
                            [lis_member_id] => S353
                        )

                    [78] => Array
                        (
                            [member_full] => Schiff (D-CA)
                            [last_name] => Schiff
                            [first_name] => Adam
                            [party] => D
                            [state] => CA
                            [vote_cast] => Yea
                            [lis_member_id] => S427
                        )

                    [79] => Array
                        (
                            [member_full] => Schmitt (R-MO)
                            [last_name] => Schmitt
                            [first_name] => Eric
                            [party] => R
                            [state] => MO
                            [vote_cast] => Nay
                            [lis_member_id] => S420
                        )

                    [80] => Array
                        (
                            [member_full] => Schumer (D-NY)
                            [last_name] => Schumer
                            [first_name] => Charles
                            [party] => D
                            [state] => NY
                            [vote_cast] => Yea
                            [lis_member_id] => S270
                        )

                    [81] => Array
                        (
                            [member_full] => Scott (R-FL)
                            [last_name] => Scott
                            [first_name] => Rick
                            [party] => R
                            [state] => FL
                            [vote_cast] => Nay
                            [lis_member_id] => S404
                        )

                    [82] => Array
                        (
                            [member_full] => Scott (R-SC)
                            [last_name] => Scott
                            [first_name] => Tim
                            [party] => R
                            [state] => SC
                            [vote_cast] => Nay
                            [lis_member_id] => S365
                        )

                    [83] => Array
                        (
                            [member_full] => Shaheen (D-NH)
                            [last_name] => Shaheen
                            [first_name] => Jeanne
                            [party] => D
                            [state] => NH
                            [vote_cast] => Yea
                            [lis_member_id] => S324
                        )

                    [84] => Array
                        (
                            [member_full] => Sheehy (R-MT)
                            [last_name] => Sheehy
                            [first_name] => Tim
                            [party] => R
                            [state] => MT
                            [vote_cast] => Nay
                            [lis_member_id] => S435
                        )

                    [85] => Array
                        (
                            [member_full] => Slotkin (D-MI)
                            [last_name] => Slotkin
                            [first_name] => Elissa
                            [party] => D
                            [state] => MI
                            [vote_cast] => Yea
                            [lis_member_id] => S436
                        )

                    [86] => Array
                        (
                            [member_full] => Smith (D-MN)
                            [last_name] => Smith
                            [first_name] => Tina
                            [party] => D
                            [state] => MN
                            [vote_cast] => Yea
                            [lis_member_id] => S394
                        )

                    [87] => Array
                        (
                            [member_full] => Sullivan (R-AK)
                            [last_name] => Sullivan
                            [first_name] => Dan
                            [party] => R
                            [state] => AK
                            [vote_cast] => Yea
                            [lis_member_id] => S383
                        )

                    [88] => Array
                        (
                            [member_full] => Thune (R-SD)
                            [last_name] => Thune
                            [first_name] => John
                            [party] => R
                            [state] => SD
                            [vote_cast] => Nay
                            [lis_member_id] => S303
                        )

                    [89] => Array
                        (
                            [member_full] => Tillis (R-NC)
                            [last_name] => Tillis
                            [first_name] => Thomas
                            [party] => R
                            [state] => NC
                            [vote_cast] => Nay
                            [lis_member_id] => S384
                        )

                    [90] => Array
                        (
                            [member_full] => Tuberville (R-AL)
                            [last_name] => Tuberville
                            [first_name] => Tommy
                            [party] => R
                            [state] => AL
                            [vote_cast] => Nay
                            [lis_member_id] => S412
                        )

                    [91] => Array
                        (
                            [member_full] => Van Hollen (D-MD)
                            [last_name] => Van Hollen
                            [first_name] => Chris
                            [party] => D
                            [state] => MD
                            [vote_cast] => Yea
                            [lis_member_id] => S390
                        )

                    [92] => Array
                        (
                            [member_full] => Warner (D-VA)
                            [last_name] => Warner
                            [first_name] => Mark
                            [party] => D
                            [state] => VA
                            [vote_cast] => Yea
                            [lis_member_id] => S327
                        )

                    [93] => Array
                        (
                            [member_full] => Warnock (D-GA)
                            [last_name] => Warnock
                            [first_name] => Raphael
                            [party] => D
                            [state] => GA
                            [vote_cast] => Yea
                            [lis_member_id] => S415
                        )

                    [94] => Array
                        (
                            [member_full] => Warren (D-MA)
                            [last_name] => Warren
                            [first_name] => Elizabeth
                            [party] => D
                            [state] => MA
                            [vote_cast] => Yea
                            [lis_member_id] => S366
                        )

                    [95] => Array
                        (
                            [member_full] => Welch (D-VT)
                            [last_name] => Welch
                            [first_name] => Peter
                            [party] => D
                            [state] => VT
                            [vote_cast] => Yea
                            [lis_member_id] => S422
                        )

                    [96] => Array
                        (
                            [member_full] => Whitehouse (D-RI)
                            [last_name] => Whitehouse
                            [first_name] => Sheldon
                            [party] => D
                            [state] => RI
                            [vote_cast] => Yea
                            [lis_member_id] => S316
                        )

                    [97] => Array
                        (
                            [member_full] => Wicker (R-MS)
                            [last_name] => Wicker
                            [first_name] => Roger
                            [party] => R
                            [state] => MS
                            [vote_cast] => Nay
                            [lis_member_id] => S318
                        )

                    [98] => Array
                        (
                            [member_full] => Wyden (D-OR)
                            [last_name] => Wyden
                            [first_name] => Ron
                            [party] => D
                            [state] => OR
                            [vote_cast] => Yea
                            [lis_member_id] => S247
                        )

                    [99] => Array
                        (
                            [member_full] => Young (R-IN)
                            [last_name] => Young
                            [first_name] => Todd
                            [party] => R
                            [state] => IN
                            [vote_cast] => Nay
                            [lis_member_id] => S391
                        )

                )

        )

)
*/
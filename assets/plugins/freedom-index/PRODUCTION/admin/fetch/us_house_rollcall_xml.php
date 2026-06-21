<?php if(!defined('ABSPATH')) exit;
/* us_house_rollcall_xml fetcher
Procedural code to parse and display xml feed data array.
*/
global $wpdb;
$data = fi_api_gov_fetch_data($url);
if(is_wp_error($data)){
	echo '<div class="alert alert-danger">'.$data->get_error_message().'</div>';
	return;
}

fi_api_gov_card_top('House Rollcall Data');
?>
<table class="table table-sm table-bordered table-striped">
	<thead>
		<tr>
			<th>Key</th>
			<th colspan="2">Value</th>
		</tr>
	</thead>
	<tbody>
<?php
$totals = $data['vote-metadata']['vote-totals']; 
unset($data['vote-metadata']['vote-totals']);
$vote_totals = $totals['totals-by-vote'];

$meta = $data['vote-metadata']; 
unset($meta['vote-desc']);
unset($meta['action-time']);

$bioguide_xref = fi_legislators_get_bioguide_xref($session_id);
$fi_rc = fi_rollcalls_legislators_cast_by_vote($vote_id);
$rc_data = $data['vote-data']['recorded-vote']; 

foreach($meta as $key => $value){
	switch($key){
		case 'action-date':
			//Convert 10-Sep-2025 to mysql datetime format 
			$value = date('Y-m-d', strtotime($value));
			$import['date_votes'] = $value;
			break;
	}
	//Determine which other values to import with rollcall data then compile all into hiddend fields that can be posted to a vote update form with one button to import
	echo '<tr><td>'.$key.'</td><td colspan="2">'.$value.'</td></tr>';
}

//Vote totals
echo '<tr><td colspan="3"><b>Vote Totals</b></td></tr>';
foreach($vote_totals as $key => $value){
	echo '<tr><td class="ps-3">'.$key.'</td><td colspan="2">'.$value.'</td></tr>';
}

//Rollcall data evaluation
echo '<tr><td colspan="2" class="fw-bold bg-dark text-white">Recorded Votes</td><td class="fw-bold bg-dark text-white">Vote Cast</td><tr>';
foreach($rc_data as $vote){
	$legislator_id = null;
	$bioguide_id = $vote['legislator']['@attributes']['name-id'];
	$state = $vote['legislator']['@attributes']['state'];
	$sort_field = $vote['legislator']['@attributes']['sort-field'];
	if($state != 'XX'){
		if($bioguide_id && isset($bioguide_xref[$bioguide_id])){
			$legislator_id = $bioguide_xref[$bioguide_id];
		}
		//Convert vote to Y, N, X cast ('Y', 'N', 'P', 'A', 'X')
		$vote_cast = $vote['vote'];
		$cast = fi_rollcall_cast_normalize($vote_cast);


		//Determine if the vote cast is in the rollcalls array
		if(isset($fi_rc[$legislator_id])){
			$rollcall_id = $fi_rc[$legislator_id]['id'];
			$rollcall_cast = $fi_rc[$legislator_id]['cast'];
		}else{
			$rollcall_id = null;
			$rollcall_cast = null;
		}

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
			$rollcall_eval = '<span class="text-danger">[Legislator NOT FOUND=' . $cast .']</span>';
		}
		echo '<tr><td class="ps-3">'.$bioguide_id;
		echo '<span class="text-muted ms-2">[' . $sort_field .'|'.$state.']</span>';
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

/*
<rollcall-vote>
<vote-metadata>
<majority>R</majority>
<congress>119</congress>
<session>1st</session>
<committee>U.S. House of Representatives</committee>
<rollcall-num>258</rollcall-num>
<legis-num>H R 3838</legis-num>
<vote-question>On Agreeing to the Amendment</vote-question>
<amendment-num>20</amendment-num>
<amendment-author>McCormick of Georgia Part A Amendment No. 25</amendment-author>
<vote-type>RECORDED VOTE</vote-type>
<vote-result>Agreed to</vote-result>
<action-date>10-Sep-2025</action-date>
<action-time time-etz="17:33">5:33 PM</action-time>
<vote-desc></vote-desc>
<vote-totals>
<totals-by-party-header>
<party-header>Party</party-header>
<yea-header>Ayes</yea-header>
<nay-header>Noes</nay-header>
<present-header>Answered “Present”</present-header>
<not-voting-header>Not Voting</not-voting-header>
</totals-by-party-header>
<totals-by-party>
<party>Republican</party>
<yea-total>217</yea-total>
<nay-total>1</nay-total>
<present-total>0</present-total>
<not-voting-total>3</not-voting-total>
</totals-by-party>
<totals-by-party>
<party>Democratic</party>
<yea-total>2</yea-total>
<nay-total>210</nay-total>
<present-total>0</present-total>
<not-voting-total>4</not-voting-total>
</totals-by-party>
<totals-by-party>
<party>Independent</party>
<yea-total>0</yea-total>
<nay-total>0</nay-total>
<present-total>0</present-total>
<not-voting-total>0</not-voting-total>
</totals-by-party>
<totals-by-vote>
<total-stub>Totals</total-stub>
<yea-total>219</yea-total>
<nay-total>211</nay-total>
<present-total>0</present-total>
<not-voting-total>7</not-voting-total>
</totals-by-vote>
</vote-totals>
</vote-metadata>
<vote-data>
<recorded-vote><legislator name-id="A000370" sort-field="Adams" unaccented-name="Adams" party="D" state="NC" role="legislator">Adams</legislator><vote>No</vote></recorded-vote>
<recorded-vote><legislator name-id="A000055" sort-field="Aderholt" unaccented-name="Aderholt" party="R" state="AL" role="legislator">Aderholt</legislator><vote>Aye</vote></recorded-vote>
<recorded-vote><legislator name-id="A000371" sort-field="Aguilar" unaccented-name="Aguilar" party="D" state="CA" role="legislator">Aguilar</legislator><vote>No</vote></recorded-vote>
</vote-data>
</rollcall-vote>
*/
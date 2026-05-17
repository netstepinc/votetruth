<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
Legiscan does not provide complete data for some of the US Congressional votes, specifically votes on some bill ammendments.
We must provide a simple method for fetching vote data and importing rollcalls from the US Congress API.

V1 includes an extremely primitive method for fetching and parsing the rollcall XML data, but it is accurate.
D:\Dropbox\WEB.JBS\jbs.org\PUBLIC_HTML\wp-content\plugins\freedom-index\docs\V1.fetch-rollcall-example.md
Example XML feed file.
D:\Dropbox\WEB.JBS\jbs.org\PUBLIC_HTML\wp-content\plugins\freedom-index\docs\V1.rollcall-data.xml

USER WORKFLOW:
1. Votes > Add Vote
2. Enter required fields: Title, Session, Chamber
3. Enter meta[url_rollcall] > SAVE
IF gov=US && meta[url_rollcall] is not empty then show Congressional Data Card on the right with all available data listed with the option to add just like we do with the Legislator External Sources data.

Example Rollcall URL entered by user: 
META[url_rollcall] = https://clerk.house.gov/Votes/2025258?RollCallNum=258 
Corresponding XML feed we'll fetch: https://clerk.house.gov/evs/2025/roll258.xml

IMPORTANT: This process and all realated code only applies to if gov=US.
IMPORTANT: Rollcall data is keyed to bioguide_id
*/


/**
 * Fetches and parses congressional rollcall XML data from a given URL.
 * Returns the data as an array suitable for processing in PHP.
 * 
 * @param string $url_rollcall The congressional rollcall XML feed URL.
 * @return array|WP_Error Array of parsed vote data, or WP_Error on failure.
*/
function fi_us_fetch_rollcall_data($url_rollcall) {
    $response = wp_remote_get($url_rollcall);
    if (is_wp_error($response)) {
        return new WP_Error('fetch_error', 'Failed to fetch rollcall data');
    }

    $body = $response['body'];
    if (empty($body)) {
        return new WP_Error('empty_body', 'Rollcall data is empty');
    }

    // Suppress errors but allow detection for malformed XML.
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
    if ($xml === false) {
        $msg = "Failed to parse XML: ";
        foreach(libxml_get_errors() as $error) {
            $msg .= $error->message . "; ";
        }
        libxml_clear_errors();
        return new WP_Error('xml_parse_error', trim($msg));
    }

    // Call to the recursive function for the root XML object
    $array = fi_us_simplexml_to_array($xml);
    return $array;
}

/* Convert Rollcall URL to XML feed URL
 * META[url_rollcall] = https://clerk.house.gov/Votes/2025258?RollCallNum=258 
 * Corresponding XML feed we'll fetch: https://clerk.house.gov/evs/2025/roll258.xml
*/
function fi_us_url_rollcall_to_xml_feed($url_rollcall) {
	$xml = null;
	if(strpos($url_rollcall, 'https://clerk.house.gov/Votes/') !== false){
		//explode url into parts
		$parts = explode('/', $url_rollcall);
		$parts = explode('?', $parts[4]);
		$year = substr($parts[0], 0, 4);
		$rc_parts = explode('=', $parts[1]);
		$rollcall_num = $rc_parts[1];
		$xml['xml'] = 'https://clerk.house.gov/evs/'.$year.'/roll'.$rollcall_num.'.xml';
		$xml['chamber'] = 'House';
	}

    return $xml;
}


/**
* Recursively convert a SimpleXMLElement (including attributes) to a PHP array.
* Attributes will be stored in an '@attributes' key at each level.
*
* @param SimpleXMLElement $xml
* @return array
*/
function fi_us_simplexml_to_array($xml) {
	$arr = [];

	if ($xml instanceof SimpleXMLElement) {
		// Add attributes under '@attributes'
		foreach ($xml->attributes() as $attrKey => $attrValue) {
			$arr['@attributes'][$attrKey] = (string)$attrValue;
		}

		// Add children elements, merge with text if available
		foreach ($xml->children() as $key => $child) {
			$value = fi_us_simplexml_to_array($child);
			// Handle repeating elements
			if (isset($arr[$key])) {
				if (!is_array($arr[$key]) || !isset($arr[$key][0])) {
					$arr[$key] = [$arr[$key]];
				}
				$arr[$key][] = $value;
			} else {
				$arr[$key] = $value;
			}
		}

		// If there are no children, get the text content
		$textValue = trim((string)$xml);
		if (empty($arr) && $textValue !== '') {
			$arr = $textValue;
		} elseif ($textValue !== '' && count($arr) > 0) {
			$arr['@value'] = $textValue;
		}
	} else {
		$arr = $xml;
	}

	return $arr;
}

//SELECT l.bioguide_id, ls.legislator_id FROM jbsw_5_fi_legislator_sessions ls LEFT JOIN jbsw_5_fi_legislators l ON ls.legislator_id = l.id WHERE ls.session_id = 14
function fi_us_get_bioguide_xref($session_id){
	global $wpdb;
	$xref = [];
	$query = $wpdb->prepare(
		"SELECT l.bioguide_id, ls.legislator_id
		 FROM {$wpdb->prefix}fi_legislator_sessions ls
		 LEFT JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
		 WHERE ls.session_id = %d",
		$session_id
	);
	$results = $wpdb->get_results($query);
	foreach($results as $row){
		$xref[$row->bioguide_id] = $row->legislator_id;
	}
	return $xref;
}


function fi_us_convert_vote_cast($vote_cast){
	$cast = null;
	switch($vote_cast){
		case 'Present':
		case 'Not Voting':
			$cast = 'X';
		break;

		case 'Yes':
		case 'Aye':
		case 'Yea':
		case 'Guilty':
			$cast = 'Y';
		break;

		case 'No':
		case 'Nay':
		case 'Not Guilty':
			$cast = 'N';
		break;
	}
	return $cast;
}



function fi_us_fetch_rollcall_display($session_id,$url_rollcall) {
	$html = '';
	$source = fi_us_url_rollcall_to_xml_feed($url_rollcall);

	if($source){
		$rollcall_data = fi_us_fetch_rollcall_data($source['xml']);
		ob_start();
		?>
<div class="card shadow-sm mb-4">
	<div class="card-header bg-white border-0">
		<h2 class="h5 mb-0"><?= $source['chamber'];?> Data</h2>
	</div>
	<div class="card-body" style="max-height: 400px; overflow-y: auto;">
		<table class="table table-sm table-bordered table-striped">
			<thead>
				<tr>
					<th>Key</th>
					<th>Value</th>
				</tr>
			</thead>
			<tbody>
<?php
if($source['chamber'] == 'House'){
	$totals = $rollcall_data['vote-metadata']['vote-totals']; 
	unset($rollcall_data['vote-metadata']['vote-totals']);
	$vote_totals = $totals['totals-by-vote'];

	$meta = $rollcall_data['vote-metadata']; 
	unset($meta['vote-desc']);
	unset($meta['action-time']);

	$bioguide_xref = fi_us_get_bioguide_xref($session_id);
	$rc_data = $rollcall_data['vote-data']['recorded-vote']; 

	$import = [];

	foreach($meta as $key => $value){
		switch($key){
			case 'action-date':
				//Convert 10-Sep-2025 to mysql datetime format 
				$value = date('Y-m-d', strtotime($value));
				$import['date_votes'] = $value;
				break;
		}
//Determine which other values to import with rollcall data then compile all into hiddend fields that can be posted to a vote update form with one button to import

		echo '<tr><td>'.$key.'</td><td>'.$value.'</td></tr>';
	}

	echo '<tr><td colspan="2"><b>Vote Totals</b></td></tr>';
	foreach($vote_totals as $key => $value){
		echo '<tr><td class="ps-3">'.$key.'</td><td>'.$value.'</td></tr>';
	}

	echo '<tr><td colspan="2"><b>Recorded Votes</b></td></tr>';
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
			$cast = fi_us_convert_vote_cast($vote_cast);

			echo '<tr><td class="ps-3">'.$bioguide_id;
			echo '<span class="text-muted ms-2">[' . $sort_field .'|'.$state.']</span>';
			echo '</td>';
			echo '<td>'.$vote_cast;
			if($legislator_id){
				echo '<span class="text-success ms-3">[' . $legislator_id . '=' . $cast .']</span>';
			}else{
				echo '<span class="text-danger ms-3">[Legislator NOT FOUND=' . $cast .']</span>';
			}
			echo '</td></tr>';
		}
	}
}
?>
			</tbody>
		</table>
	</div>
</div>
		<?php
		//echo '<textarea style="width: 100%; height: 400px;">'. json_encode($rollcall_data, JSON_PRETTY_PRINT).'</textarea>';
		$html = ob_get_clean();
		return $html;
	}
}










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
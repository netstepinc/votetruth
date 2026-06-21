<?php 
if ( ! defined( 'ABSPATH' ) ) { exit; }
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


/*
API/XML data for government sources
Build a handler for each URL pattern
*/
function fi_api_gov_data($session_id,$vote_id,$url){
    $url = trim($url);
	$type = fi_api_gov_data_type($url);
fi_log('fi_api_gov_data | session_id='.$session_id.' | vote_id='.$vote_id.'|TYPE='.$type.'|url='.$url,__FILE__,__LINE__);
	if($type){
		$type_handler = FI_DIR . 'admin/fetch/' . $type . '.php';
		if(file_exists($type_handler)){
			require_once $type_handler;
		}
	}
}


// Determine how to handle the data based on the URL pattern
function fi_api_gov_data_type($url) {
    $type = null;
    
    // US House Rollcall XML (Clerk): https://clerk.house.gov/evs/2025/roll258.xml
    if (stripos($url, 'https://clerk.house.gov/evs/') !== false) {
        $type = 'us_house_rollcall_xml';
    }
    // US House Rollcall: https://clerk.house.gov/Votes/2025258?RollCallNum=258 = https://clerk.house.gov/evs/2025/roll258.xml
    elseif (stripos($url, 'https://clerk.house.gov/Votes/') !== false) {
        $type = 'us_house_rollcall_html';
    }
    // US Senate Rollcall (HTML): https://www.senate.gov/legislative/LIS/roll_call_votes/vote1182/vote_118_2_00176.htm
    // US Senate Rollcall (XML):  https://www.senate.gov/legislative/LIS/roll_call_votes/vote1182/vote_118_2_00176.xml
    elseif (stripos($url, 'https://www.senate.gov/legislative/LIS/roll_call_votes/') !== false) {
       	$type = 'us_senate_rollcall';
    }
	//http://www.senate.gov/legislative/LIS/roll_call_lists/roll_call_vote_cfm.cfm?congress=108&session=2&vote=00088
	//https://www.senate.gov/legislative/LIS/roll_call_votes/vote1082/vote_108_2_00088.xml
    elseif (stripos($url, 'senate.gov/legislative/LIS/roll_call_lists') !== false) {
       	$type = 'us_senate_rollcall';
    }


    // US Senate Bill (Congress.gov): https://www.congress.gov/bill/119th-congress/senate-bill/3385/all-info
	/*
    elseif (stripos($url, 'https://www.congress.gov/bill/') !== false) {
        $type = 'us_senate_bill';
    }
    // US Senate Amendment (Congress.gov): https://www.congress.gov/amendment/118th-congress/senate-amendment/2
    elseif (stripos($url, 'https://www.congress.gov/amendment/') !== false) {
        $type = 'us_senate_amendment';
    }
    // US House Bill (Congress.gov): https://www.congress.gov/bill/119th-congress/house-bill/5125/all-info
    elseif (stripos($url, 'https://www.congress.gov/bill/') !== false) {
        $type = 'us_house_bill';
    }
	*/
    // Extend here for states and other sources as needed.


    return $type;
}

/*Do we have a local XML file?
https://clerk.house.gov/Votes/2025258?RollCallNum=258
https://clerk.house.gov/evs/2025/roll258.xml
Manually saved file name: roll258.xml
*/
/*
function fi_api_gov_get_local_xml($url_xml){
	$filename = basename($url_xml);
	$file = FI_DIR_CACHE . 'xml/' . $filename;
	if(file_exists($file)){
		return file_get_contents($file);
	}
	return false;
}

function fi_api_gov_save_local_xml($url_xml,$xml){
	$filename = basename($url_xml);
	$file = FI_DIR_CACHE . 'xml/' . $filename;
	file_put_contents($file,$xml);
}
*/

/**
 * Fetches and parses congressional rollcall XML data from a given URL.
 * Returns the data as an array suitable for processing in PHP.
 * 
 * @param string $url_rollcall The congressional rollcall XML feed URL.
 * @return array|WP_Error Array of parsed vote data, or WP_Error on failure.
*/
function fi_api_gov_fetch_data($url_xml) {
	$cacheKey = 'fetch/gov_data_' . md5($url_xml);
	$result = fi_cache($cacheKey,'',90);
	if(!$result){
		$response = wp_remote_get($url_xml);

		if (is_wp_error($response)) {
			echo '<div class="alert alert-danger">Failed to fetch rollcall data: '.$response->get_error_message().'</div>';
			return;
		}

		$body = $response['body'];
		if (empty($body)) {
			echo '<div class="alert alert-danger">Rollcall data is empty</div>';
			return;
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
			echo '<div class="alert alert-danger">'.$msg.'</div>';
			return;
		}

		// Call to the recursive function for the root XML object
		$result = fi_api_gov_simplexml_to_array($xml);
	}
    return $result;
}

/**
* Recursively convert a SimpleXMLElement (including attributes) to a PHP array.
* Attributes will be stored in an '@attributes' key at each level.
*
* @param SimpleXMLElement $xml
* @return array
*/
function fi_api_gov_simplexml_to_array($xml) {
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

function fi_api_gov_card_top($title){
	echo '<div class="card shadow-sm mb-4"><div class="card-header bg-white border-0"><h2 class="h5 mb-0">'.$title.'</h2></div><div class="card-body"><div style="max-height: 400px; overflow-y: auto;">';
}

function fi_api_gov_card_bottom(){
	echo '</div></div></div>';
}
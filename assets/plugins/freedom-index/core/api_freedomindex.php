<?php
if (!defined('ABSPATH')) exit;

/**
* Freedom Index API Request Handler
* 
* Single function to handle API requests for the Freedom Index backend.
* Handles args, builds URL, sends request, and returns result.
* If more complexity arises later (more error cases, retries, advanced batching, etc.), we can expand this to a class.

* Reproduce the WP-based queries with very specific and lean requests.
* EXAMPLE: https://freedomindex.us/fi_api.php?key=5f71b6205a7fef749f412c21ec971e43&action=legislator&id=1414
*/

function fi_api_request(array $args): array {
	$defaults = [
		'action'		=> '',  // Required: API action, e.g. 'legislators_cards'
		'gov'			=> '',  // E.g. 'US'
		'session_id'	=> null,
		'legislator_id' => null,
		'vote_id'		=> null,
		'report_id'		=> null,
		'district_id'	=> null,
		'tag_id'		=> null,
		'search' 		=> '',
		'party'			=> '',
		'chamber'		=> '',
		'state'			=> '',
		'name'			=> '',
		'orderby'		=> '',
		'order'     	=> '',
		'limit'     	=> 1000,
		'offset'    	=> 0,
	];
	$args = array_merge($defaults, $args);

	if (!$args['action']) {
		return [
			'success' => false,
			'message' => 'API request missing required arguments (action).',
		];
	}

	$query = [
		'key'    => defined('FI_API_KEY') ? FI_API_KEY : '',
		'action' => $args['action'],
	];

	// Check each argument in $defaults to confirm handling.
	if (!empty($args['gov']))
		$query['gov'] = strtolower($args['gov']);
	if (!empty($args['session_id']) && (int)$args['session_id'] > 0)
		$query['session_id'] = (int)$args['session_id'];
	if (!empty($args['legislator_id']) && (int)$args['legislator_id'] > 0)
		$query['legislator_id'] = (int)$args['legislator_id'];
	if (!empty($args['vote_id']) && (int)$args['vote_id'] > 0)
		$query['vote_id'] = (int)$args['vote_id'];
	if (!empty($args['report_id']) && (int)$args['report_id'] > 0)
		$query['report_id'] = (int)$args['report_id'];
	if (!empty($args['tag_id']) && (int)$args['tag_id'] > 0)
		$query['tag_id'] = (int)$args['tag_id'];
	if (!empty($args['district_id']) && (int)$args['district_id'] > 0)
		$query['district_id'] = (int)$args['district_id'];
	if (!empty($args['search']))
		$query['search'] = $args['search'];
	if (!empty($args['party']))
		$query['party'] = strtolower($args['party']);
	if (!empty($args['chamber']))
		$query['chamber'] = strtolower($args['chamber']);
	if (!empty($args['state']))
		$query['state'] = strtolower($args['state']);
	if (!empty($args['name']))
		$query['name'] = $args['name'];
	if (!empty($args['orderby']))
		$query['orderby'] = $args['orderby'];
	if (!empty($args['order']))
		$query['order'] = $args['order'];
	if (isset($args['limit']) && is_numeric($args['limit']))
		$query['limit'] = (int)$args['limit'];
	if (isset($args['offset']) && is_numeric($args['offset']))
		$query['offset'] = (int)$args['offset'];

	if (!defined('FI_API_URL') || !FI_API_URL) {
		return [
			'success' => false,
			'message' => 'API URL is not defined.',
		];
	}

	$url = add_query_arg($query, FI_API_URL);
	$res = wp_remote_get($url, [
		'timeout' => 10,
		'redirection' => 0,
		'headers' => [
			'Accept' => 'application/json',
		],
	]);

//if(get_current_user_id() == 1){echo '<textarea style="width:100%; height:600px;">'; print_r($res); echo '</textarea>';exit;}


	if (is_wp_error($res)) {
		return [
			'success' => false,
			'message' => 'API request failed.',
		];
	}

	$code = (int) wp_remote_retrieve_response_code($res);
	$body = (string) wp_remote_retrieve_body($res);

	if ($code !== 200 || $body === '') {
		return [
			'success' => false,
			'message' => 'API returned an invalid response.',
		];
	}

	$json = json_decode($body, true);
	if (!is_array($json)) {
		return [
			'success' => false,
			'message' => 'API returned invalid JSON for '.$url,
		];
	}
	return $json;
}


//GET LEGISLATOR BY ID
function fi_api_legislator_get_by_id($id){
	$cacheKey = 'legislators/fi_api_legislator_get_by_id_'.$id;
	$data = ''; //fi_cache($cacheKey,'',1,true);
	if($data){return $data;	}
	$return = fi_api_request([
		'action' => 'legislator',
		'legislator_id' => $id,
	]);
	$data = $return['results'];
	//fi_cache($cacheKey,$data,1,true);

	//Output the returned message to the console?
	echo '<script>console.log("'.htmlspecialchars($return['message']).'");</script>';
	return $data;
}
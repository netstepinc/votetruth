<?php if(!defined('ABSPATH')) exit;


/* All-Purpose API Fetch Function
*/
function fi_http_fetch_json($url): array {
	$res = wp_remote_get($url, [
		'timeout' => 10,
		'redirection' => 0,
		'headers' => [
			'Accept' => 'application/json',
		],
	]);

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
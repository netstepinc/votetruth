<?php if(!defined('ABSPATH')) exit;
/* Multiple Legislators
Replicate /core/legislators.php:get() with leaner query.

*/





header('Content-Type: application/json; charset=utf-8');
//Check query execution time
$start_time = microtime(true);
try {











	//Output time after vote tags query
	$runTime = microtime(true) - $start_time;
	//echo "Time after all queries: " . (microtime(true) - $start_time) . " seconds\n";

	//print_r($leg);exit;

	echo json_encode([
		'success' => true,
		'message' => 'Legislators data fetched in ' . round($runTime, 4) . ' seconds',
		'action' => 'legislators',
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
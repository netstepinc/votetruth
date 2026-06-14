<?php if ( ! defined( 'ABSPATH' ) ) { exit; }


//Build cache key from array of arguments
function fi_cache_key($base_key, array $args = []): string {
    $cache_key = $base_key;
    foreach ($args as $arg_key => $value) {
        if ($value === null || $value === false || $value === '') {
            continue;
        }
		if($arg_key == 'cache'){
			continue;
		}
		if (is_array($value)) {
			$value = implode(',', $value);
		}
        $cache_key .= '_' . $arg_key . '-' . $value;
    }
    return strtolower($cache_key);
}


//CACHE TO FILE: DB queries, data or code
//Return false instead of 0 / Many instances rely on '0' so need a switch until all instances are updated.
function fi_cache($key,$data='',$expires=1){ //default time = 1 day.
	//Stop if no key is provided.
	if($key == ''){
		return false;
	}
	$expires = $expires * DAY_IN_SECONDS;
	//fi_log('HIT: '.$key.' | expires: '.$expires, __FILE__, __LINE__);
	//Enable override of cache by setting $force to true.
/*
	if($force === false){
		// If the cache root isn't set or doesn't exist, fail soft.
		if (defined('DOING_AJAX') && DOING_AJAX) {
			//fi_log('AJAX-READ: '.$key, __FILE__, __LINE__);
		}else{
			//SKIP IF NOT on front side...this may be too aggressive
			//fi_log('SKIP: '.$key, __FILE__, __LINE__);
			return '';
		}
	}
*/
	
	//fi_log('CONTINUE: '.$key, __FILE__, __LINE__);
	if (!defined('FI_DIR_CACHE') || !FI_DIR_CACHE) {
		return false;
	}
	$file = FI_DIR_CACHE.$key;
	//fi_log('FILE: '.$file, __FILE__, __LINE__);

	//If no data is provided, this is a read request. Return the cached data
	if($data == ''){
		//fi_log('READ: '.$file, __FILE__, __LINE__);
		//Handle data that is evaluated by hash {expires=0} instead of time.
		if( file_exists($file) && ( time() - ($expires) < filemtime($file) ) ) {
			$contents = file_get_contents($file);
			if(is_serialized( $contents )){
				return unserialize( $contents );
			}else{
				return $contents;
			}
		}else{
			return false;
		}
	}elseif($data == 'DUMP'){
		if( file_exists($file) ){
			unlink($file);
		}
	}else{
		if(is_array($data) || is_object($data)){
			$data = serialize($data); //We already handle json files as string }elseif( substr($key,-4,4) == 'json' ){
		}
		// file_put_contents returns the number of bytes written on success, or false on failure.
//TEMP DISABLE		$result = file_put_contents($file, $data);
		//fi_log('CACHE WRITE '. ($result === false ? 'FAIL' : 'SUCCESS').': '.$file, __FILE__, __LINE__);
		return $result;
	}
}

/**
 * Clear Freedom Index file cache directories under FI_DIR_CACHE.
 *
 * Rules:
 * - Cache types are directory names under FI_DIR_CACHE (e.g. findmy/, legislators/, search/, user/).
 * - NEVER bulk-delete `legiscan/` (only delete specific gov/session folders from the Legiscan UI).
 *
 * @param string $type Cache type, or 'all' to clear all safe types.
 * @return array{type:string,cleared:int,errors:int,skipped:bool}
 */
function fi_cache_clear(string $type = 'all'): array {
	$type = strtolower(trim($type));
	if ($type === '') {
		$type = 'all';
	}

	$base = rtrim(FI_DIR_CACHE, '/\\') . DIRECTORY_SEPARATOR;

	// Safe, explicitly allowed cache types.
	$allowed = [
		'findmy',
		'legislators',
		'reports',
		'rollcalls',
		'search',
		'sessions',
		'taxonomy',
		'user',
		'votes',
		'pdf',
		'ajax',
		'api',
	];

	// Never allow bulk deletion of legiscan.
	if ($type === 'legiscan') {
		return [
			'type' => 'legiscan',
			'cleared' => 0,
			'errors' => 0,
			'skipped' => true,
		];
	}

	$targets = [];
	if ($type === 'all') {
		$targets = $allowed;
	} else {
		// Only allow known types.
		if (!in_array($type, $allowed, true)) {
			return [
				'type' => $type,
				'cleared' => 0,
				'errors' => 0,
				'skipped' => true,
			];
		}
		$targets = [$type];
	}

	$cleared = 0;
	$errors = 0;

	foreach ($targets as $cache_type) {
		$dir = $base . $cache_type . DIRECTORY_SEPARATOR;
		if (!is_dir($dir)) {
			continue;
		}

		$result = fi_cache_clear_dir($dir);
		$cleared += $result['cleared'];
		$errors += $result['errors'];
	}

	return [
		'type' => $type,
		'cleared' => $cleared,
		'errors' => $errors,
		'skipped' => false,
	];
}

/**
 * Recursively delete all files and folders under a directory, but keep the root directory.
 *
 * @param string $dir Absolute directory path.
 * @return array{cleared:int,errors:int}
 */
function fi_cache_clear_dir(string $dir): array {
	$cleared = 0;
	$errors = 0;

	$dir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
	if (!is_dir($dir)) {
		return ['cleared' => 0, 'errors' => 0];
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($iterator as $fileinfo) {
		$path = $fileinfo->getRealPath();
		if (!$path) {
			continue;
		}

		if ($fileinfo->isDir()) {
			if (@rmdir($path)) {
				$cleared++;
			} else {
				$errors++;
			}
		} else {
			if (@unlink($path)) {
				$cleared++;
			} else {
				$errors++;
			}
		}
	}
	return ['cleared' => $cleared, 'errors' => $errors];
}
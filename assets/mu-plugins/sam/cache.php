<?php if ( ! defined( 'ABSPATH' ) ) exit;


//CACHE TO FILE: DB queries, data or code
//Return false instead of 0 / Many instances rely on '0' so need a switch until all instances are updated.
function sam_cache($key,$data='',$expires=86400,$empty=0){ //default time = 1 day.
	$file = DIR_SAM_CACHE.$key;
	if($data == ''){
		//Handle data that is evaluated by hash {expires=0} instead of time.
		if( file_exists($file) && ( time() - ($expires) < filemtime($file) ) ) {
			if(is_serialized( file_get_contents($file) )){
				return unserialize( file_get_contents($file) );
			}else{
				return file_get_contents($file);
			}
		}else{
			return $empty;
		}
	}elseif($data == 'EMPTY' || $data == 'CLEAR'){
		if( file_exists($file) ){
			unlink($file);
		}
	}else{
		if(is_array($data)){
			$data = serialize($data);
		}
		file_put_contents($file, $data);
	}
}



// -----------------------------------------------------------------------------
// Cache prevention for auth-related pages
// -----------------------------------------------------------------------------
function sam_prevent_caching_check(){
	static $prevent_cache = null;
	if ($prevent_cache !== null) return $prevent_cache;

	$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
	$path = (string) $path;

	$prevent_cache = false;
	if (
		strpos($path, '/login') !== false ||
		strpos($path, '/account') !== false ||
		strpos($path, 'wp-login.php') !== false ||
		strpos($path, '/register') !== false ||
		strpos($path, '/lost-password') !== false ||
		strpos($path, '/reset') !== false ||
		(defined('REST_REQUEST') && REST_REQUEST)
	) {
		$prevent_cache = true;
	}
	return $prevent_cache;
}

function sam_should_start_session_for_request(): bool {
	$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
	$path = (string) $path;

	// Keep session scope narrow: auth form pages only (exclude REST to avoid lock contention).
	return (
		strpos($path, '/login') !== false ||
		strpos($path, '/account') !== false ||
		strpos($path, 'wp-login.php') !== false ||
		strpos($path, '/register') !== false ||
		strpos($path, '/lost-password') !== false ||
		strpos($path, '/reset') !== false
	);
}

add_action('send_headers', 'sam_prevent_login_page_caching');
function sam_prevent_login_page_caching() {
	if (!sam_prevent_caching_check()) return;
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	header('Pragma: no-cache');
	header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
	header('X-Content-Type-Options: nosniff');
	header('X-Robots-Tag: noindex, noarchive');
}

add_action('wp_head', 'sam_output_meta_cache_control');
function sam_output_meta_cache_control() {
	if (!sam_prevent_caching_check()) return;
	echo '<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">' . "";
	echo '<meta http-equiv="Pragma" content="no-cache">' . "";
	echo '<meta http-equiv="Expires" content="0">' . "";
}

add_action('init', 'sam_force_user_eval_on_login', 1);
function sam_force_user_eval_on_login() {
	if (!sam_prevent_caching_check()) return;
	wp_get_current_user();
	if (!is_user_logged_in()) {
		wp_set_current_user(0);
	}
}
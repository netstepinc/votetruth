<?php if ( ! defined( 'ABSPATH' ) ) exit;

/*
Freedom Scorecard by Sam Mittelstaedt <smittelstaedt@jbs.org>
*/
//get paths once use many
define('THEME_DIR', get_theme_root() . '/bootscore' );
define('THEME_URI', WP_CONTENT_URL . '/themes/bootscore' );

define('STYLE_URI', get_stylesheet_directory_uri() );
define('STYLE_DIR', get_stylesheet_directory() );
define('STYLE_IMG', STYLE_URI.'/assets/img/' );
define('STYLE_JS', STYLE_URI.'/assets/js/' );

define('FI_SEARCH_PLACEHOLDER', 'Enter ZIP code or legislator name');
define('FI_SEARCH_PLACEHOLDER_SMALL', 'Enter ZIP code or name');


$version = filemtime( STYLE_DIR . '/style.css' );
define('STYLE_VER', $version);

//Autoload files from theme dir
if(defined('FI_VERSION')):
	foreach (glob(STYLE_DIR.'/autoload/'."*.php") as $filename) {
		if( strpos($filename,'DEV') === false ){
			include $filename;
		}
	}
endif;

add_action( 'send_headers', function() {
    if ( ! is_admin() ) {
        header( 'Cache-Control: no-cache, must-revalidate' );
    }
} );

//DEBUG
/*
add_action('admin_init', function() {
    if (defined('DOING_AJAX') && DOING_AJAX && $_POST['action'] === 'query-attachments') {
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            error_log("AJAX ERROR: $errstr in $errfile:$errline");
            return false;
        });
    }
}, 1);

add_action('wp_ajax_query-attachments', function() {
    error_log('Before prepare_attachments_for_js');
    
    // Check if wp_prepare_attachment_for_js is failing
    add_filter('wp_prepare_attachment_for_js', function($response, $attachment, $meta) {
        error_log('Processing attachment ID: ' . $attachment->ID);
        return $response;
    }, 0, 3);
    
    // Log any output buffering issues
    $level = ob_get_level();
    error_log('Output buffer level: ' . $level);
    
}, 1);

// Add temporarily to debug
add_action('wp_ajax_query-attachments', function() {
    $attachments = get_posts([
        'post_type' => 'attachment',
        'posts_per_page' => 2,
        'post_status' => 'inherit'
    ]);
    
    $prepared = array_map('wp_prepare_attachment_for_js', $attachments);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON ERROR: ' . json_last_error_msg());
        error_log('Failed data: ' . print_r($prepared, true));
    }
    
    wp_send_json_success($prepared);
    exit;
}, 0); // Priority 0 to run before default

*/

// sudo tail -50 /var/log/apache2/error.log

/*
add_action('admin_init', function() {
	global $wpdb;
	if (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && $_POST['action'] === 'query-attachments') {
        error_log('AJAX query-attachments initiated');
        
        // Log post data
        error_log('POST data: ' . print_r($_POST, true));
        
        // Try the query and catch errors
        try {
            $query = new WP_Query([
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'posts_per_page' => 40,
                'post_mime_type' => 'image'
            ]);
            error_log('Query completed. Found: ' . $query->found_posts);
        } catch (Exception $e) {
            error_log('Query ERROR: ' . $e->getMessage());
        }
    }
}, 1);
*/
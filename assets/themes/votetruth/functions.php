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
    //if ( is_admin() ) {
        header( 'Cache-Control: no-cache, must-revalidate' );
    //}
} );
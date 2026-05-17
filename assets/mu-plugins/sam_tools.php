<?php if(!defined('ABSPATH')) { exit; }
/*
Plugin Name: Site Asset Manager (SAM Tools)
Author: Sam Mittelstaedt <smittelstaedt@jbs.org>
Version: 5.0
Date: 2026-05-15
Description: The plugin loads site wide resources.
*/

define('DIR_SAM_PLUGIN',	WPMU_PLUGIN_DIR . '/sam/');

define('URL_SAM_JS', 		WPMU_PLUGIN_URL . '/sam/js/');
define('DIR_SAM_JS', 		WPMU_PLUGIN_DIR . '/sam/js/');

define('URL_SAM_CSS', 		WPMU_PLUGIN_URL . '/sam/css/');
define('DIR_SAM_CSS', 		WPMU_PLUGIN_DIR . '/sam/css/');

define('URL_SAM_SRC', 		WPMU_PLUGIN_URL . '/sam/src/');
define('DIR_SAM_SRC',		WPMU_PLUGIN_DIR . '/sam/src/');

define('DIR_SAM_CACHE', 	WPMU_PLUGIN_DIR . '/sam/cache/');

define('DIR_SAM_LOG', 		WPMU_PLUGIN_DIR . '/sam/__L0g/');
define('LOG_E','errors');
define('LOG_SAM_DETAIL',1); //enable/disable detailed logging for products and other functions typically used for debugging


//Autoload files from plugin dir
foreach (glob(DIR_SAM_PLUGIN."*.php") as $filename) {
	if( strpos($filename,'DEV') === false && strpos($filename,'copy') === false ){
		include $filename;
	}
}
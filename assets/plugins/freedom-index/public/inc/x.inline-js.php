<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Inline JavaScript Template
 * Embeds public.js with PHP variables
 */

// Prepare variables for inline JavaScript
$ajax_url = esc_js(admin_url('admin-ajax.php'));
$nonce = wp_create_nonce('fi_ajax_nonce');
$is_logged_in = is_user_logged_in() ? 'true' : 'false';
$current_user_id = get_current_user_id();

$inline_js = file_get_contents(FI_DIR . 'assets/js/public.js');

// Replace FI.ajaxurl references with direct variable
$inline_js = str_replace(
	"ajaxurl: ajaxurl || '/wp-admin/admin-ajax.php',",
	"ajaxurl: '{$ajax_url}',",
	$inline_js
);

// Replace FI.nonce initialization
$inline_js = str_replace(
	"FI.nonce = $('meta[name=\"fi-nonce\"]').attr('content') || '';",
	"FI.nonce = '{$nonce}';",
	$inline_js
);

// Replace FI.isLoggedIn initialization
$inline_js = str_replace(
	"FI.isLoggedIn = $('body').hasClass('logged-in');",
	"FI.isLoggedIn = {$is_logged_in};",
	$inline_js
);

// Add current user ID to FI object
$inline_js = str_replace(
	"currentSession: ''",
	"currentSession: '',\n\t\tcurrentUserId: {$current_user_id}",
	$inline_js
);

echo $inline_js;


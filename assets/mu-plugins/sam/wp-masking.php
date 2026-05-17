<?php
/**
 * Freedom Index - WordPress Fingerprint Cleanup
 *
 * Removes common public WordPress indicators from the front end.
 * This is white-labeling, not security hardening.
 */

defined( 'ABSPATH' ) || exit;

//Disable Yoast SEO debug markers
add_filter( 'wpseo_debug_markers', '__return_false' );

/**
 * Remove WordPress meta/head fingerprints.
 */
add_action( 'init', function () {

	// Generator tags.
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );

	// Shortlinks.
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'template_redirect', 'wp_shortlink_header', 11 );

	// REST API discovery links.
	remove_action( 'wp_head', 'rest_output_link_wp_head' );
	remove_action( 'template_redirect', 'rest_output_link_header', 11 );

	// oEmbed discovery links.
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );

	// Emoji assets.
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
}, 20 );

/**
 * Remove WordPress version from generator output anywhere it still leaks.
 */
add_filter( 'the_generator', '__return_empty_string' );

/**
 * Remove WordPress version from scripts and styles.
 */
add_filter( 'style_loader_src', 'jbsfi_remove_wp_asset_versions', 9999 );
add_filter( 'script_loader_src', 'jbsfi_remove_wp_asset_versions', 9999 );

function jbsfi_remove_wp_asset_versions( $src ) {
	if ( empty( $src ) ) {
		return $src;
	}

	return remove_query_arg( 'ver', $src );
}

/**
 * Remove emoji DNS prefetch.
 */
add_filter( 'emoji_svg_url', '__return_false' );

add_filter( 'wp_resource_hints', function ( $urls, $relation_type ) {
	if ( 'dns-prefetch' !== $relation_type ) {
		return $urls;
	}

	return array_filter( $urls, function ( $url ) {
		return false === strpos( $url, 's.w.org' );
	} );
}, 10, 2 );

/**
 * Disable XML-RPC if the site does not need it.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );

add_filter( 'wp_headers', function ( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
} );

/**
 * Remove adjacent post links from head.
 */
remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );

/**
 * Disable REST API user enumeration for unauthenticated visitors.
 */
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( is_user_logged_in() ) {
		return $endpoints;
	}

	unset( $endpoints['/wp/v2/users'] );
	unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );

	return $endpoints;
} );

/**
 * Remove WordPress body classes that expose too much.
 */
add_filter( 'body_class', function ( $classes ) {
	$remove = array(
		'wp-embed-responsive',
		'logged-in',
		'admin-bar',
	);

	return array_values( array_diff( $classes, $remove ) );
}, 20 );

/**
 * Remove "WordPress" from outgoing email sender name.
 */
add_filter( 'wp_mail_from_name', function () {
	return get_bloginfo( 'name' );
} );

/**
 * Optional: change default WordPress email address.
 */
add_filter( 'wp_mail_from', function () {
	return 'noreply@freedomindex.us';
} );
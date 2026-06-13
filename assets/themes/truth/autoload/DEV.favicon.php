<?php if ( ! defined( 'ABSPATH' ) ) exit;


function vttt_favicon() {
	$html = '<link rel="manifest" href="'.site_url().'/manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="New American">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" sizes="180x180" href="'.site_url().'/assets/theme/tna/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="'.site_url().'/assets/theme/tna/favicon-32x32.png">
<link rel="mask-icon" href="'.site_url().'/assets/theme/tna/safari-pinned-tab.svg" color="#0053a4">
<link rel="shortcut icon" href="'.site_url().'/assets/theme/tna/favicon.ico">
<meta name="msapplication-TileColor" content="#da532c">
<meta name="msapplication-config" content="'.site_url().'/assets/theme/tna/browserconfig.xml">
<meta name="theme-color" content="#ffffff">';
	return $html;
}
add_action('wp_head', 'vttt_favicon');
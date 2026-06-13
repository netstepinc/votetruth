<?php if(!defined('ABSPATH')) exit;
/**
* Template Name: FI Account
* Template Post Type: page
* Freedom Index Account Tools
* NOTE: I think this page is obsolete. Verify still used by plugin
*/
global $post;
$slug = $post->post_name;
//$form_redirect_to = '<input type="hidden" name="fi_redirect_to" value="' . esc_url(get_permalink($post->ID)) . '">';
$page_link = esc_url(get_permalink($post->ID));
define('DONOTCACHEPAGE', true);
sam_prevent_caching_check();
/*
/account/ (login/landing)
/account/dashboard/
/account/lists/
/account/lists/{id}/  (single list — handled inside account-lists.php)
/account/profile/
/account/personalize/
/account/notifications/
*/

get_header();
get_template_part('global-templates/page','top',['title' => get_the_title()]);

echo '<div class="container-xl p-0 m-0 mx-auto"><div id="legislator-search-results"></div></div>';
if(defined('FI_VERSION')):
	switch($slug):
		case 'account':
			include FI_PUBLIC_DIR . 'account/account.php';
			break;
		case 'dashboard':
			include FI_PUBLIC_DIR . 'account/account-dashboard.php';
			break;
		case 'lists':
			include FI_PUBLIC_DIR . 'account/account-lists.php';
			break;
	// Handle edit in lists page
	//	case 'list':
	//		include FI_PUBLIC_DIR . 'account/account-list.php';
	//		break;
		case 'profile':
			include FI_PUBLIC_DIR . 'account/account-profile.php';
			break;
		case 'personalize':
			include FI_PUBLIC_DIR . 'account/account-personalize.php';
			break;
		case 'notifications':
			include FI_PUBLIC_DIR . 'account/account-notifications.php';
			break;
		default:
			echo '<h2>Page Not Found</h2>';
			break;
	endswitch;
endif;
get_template_part('global-templates/page','bottom');
get_footer();
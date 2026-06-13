<?php if(!defined('ABSPATH')) { exit; }

//Debug environment variables
echo '<textarea cols="120" rows="30">';
echo 'UPLOADS=' . UPLOADS . "\n";
echo 'WP_CONTENT_DIR=' . WP_CONTENT_DIR . "\n";
echo 'WP_CONTENT_URL=' . WP_CONTENT_URL . "\n\n";

global $wpdb;
echo 'DB_PREFIX=' . $wpdb->prefix . "\n";
$results = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->prefix}options WHERE option_name IN ('upload_path', 'upload_url_path', 'siteurl', 'home')");
echo print_r($results, true);echo "\n\n";
/* MANUAL phpMyAdmin Dumping data for table `vtttus_options`
INSERT INTO `vtttus_options` VALUES(2, 'siteurl', 'http://localhost/votestellthetruth', 'on');
INSERT INTO `vtttus_options` VALUES(3, 'home', 'http://localhost/votestellthetruth', 'on');
INSERT INTO `vtttus_options` VALUES(49, 'upload_path', '', 'on');
INSERT INTO `vtttus_options` VALUES(56, 'upload_url_path', '', 'on');
*/
echo "WP UPLOAD DIR FUNCTION: \n";
echo print_r(wp_upload_dir(), true);

echo "ATTACHMENT COUNT:\n";
$count = wp_count_attachments();
echo print_r($count, true);
 
echo "\n\nSAMPLE ATTACHMENTS:\n";
$attachments = get_posts([
    'post_type' => 'attachment',
    'posts_per_page' => 5,
    'post_status' => 'any'
]);
echo print_r($attachments, true);

echo "\n\nVERIFY USER CAPABILITIES:\n";
$user = wp_get_current_user();
echo print_r($user->allcaps, true);

echo '</textarea>';
<?php if ( ! defined( 'ABSPATH' ) ) {exit;}?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="profile" href="http://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>
<body>
    <div class="bg-image"></div>
    <div class="wrapper">
<?php
if(defined('FS_VERSION')):
get_template_part( 'global-templates/header','2604' );
endif;
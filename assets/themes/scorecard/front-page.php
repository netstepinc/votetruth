<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
 * Front Page Template — Freedom Index Home v2
 *
 * Hero-led civic accountability landing page. Replaces the legacy
 * dashboard-style two-column front page.
 *
 * Header: global-templates/header-2604.php
 * Footer: global-templates/footer-2604.php
 *
 * @package bootnews
 */

get_header();
if(defined('FS_VERSION')):

get_template_part('template-parts/home','hero');

//Legislators + Votes + Rollcalls
get_template_part('template-parts/home','stats');

//Spending/Debt
get_template_part('template-parts/home','debt');

//CTA?
get_template_part('template-parts/home','cta');

//Scoring methodology
get_template_part('template-parts/home','method');

//Secondary action cards
get_template_part('template-parts/home','actions');

endif;
get_footer();
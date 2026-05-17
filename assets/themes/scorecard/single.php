<?php
/**
 * The template for displaying all single posts.
 *
 * @package bootnews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header();
setPostViews(get_the_ID());
get_template_part('global-templates/page','top',['title' => get_the_title()]);
while ( have_posts() ) : the_post();
    get_template_part( 'loop-templates/content', 'single' );
    echo '<hr>';
    echo jbs_social_share(['link' => get_the_permalink(),'text' => true]);
    echo '<hr>';
    get_template_part( 'global-templates/post/prev-next' );
endwhile; // end of the loop.
get_template_part('global-templates/page','bottom');
get_footer();
<?php
/**
 * The template for displaying all pages.
 */
if ( ! defined( 'ABSPATH' ) ) {exit;}
get_header();
echo '<div class="container-xl p-0 m-0 mx-auto"><div id="legislator-search-results"></div></div>';
get_template_part('global-templates/page','top',['title' => get_the_title()]);
while ( have_posts() ) : the_post();
?>
<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
	<div class="entry-content post-content post-page">
		<?php the_content(); ?>
	</div>
</article>
<?php 
endwhile; // end of the loop.
get_template_part('global-templates/page','bottom');
get_footer();
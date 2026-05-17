<?php
/**
 * The template related
 *
 * @package bootnews
 */
 
if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

// Get a list of the current post's categories
global $post;

$related_style = 'choice-3'; //get_theme_mod( 'related-select', customizer_library_get_default( 'related-select' ) );
$related_number = 3; //get_theme_mod( 'related-text', customizer_library_get_default( 'related-text' ) );

// Build our category based custom query arguments
$custom_query_args = array( 
	'posts_per_page' => $related_number, // Number of related posts to display
	'post__not_in' => array($post->ID), // Ensure that the current post is not displayed
//	'orderby' => 'rand', // Randomize the results
	'orderby' => 'date', //get most recent
	'order' => 'DESC',
);

//USE TAGS IF AVAILABLE
//https://wordpress.stackexchange.com/questions/136756/retrieve-all-posts-within-tag-or-category
$posttags = get_the_tags( $post->ID );
if( $posttags):
	$tag_ids = array();
    foreach($posttags as $tag) {
		$tag_ids[] = $tag->term_id;
	}
	//$custom_query_args['tag__in'] = $tag_ids;

	$categories = get_the_category( $post->ID );
	$cat_ids = array();
	foreach( $categories as $category) {
		$cat_ids[] = $category->cat_ID;
	}

    $custom_query_args['tax_query'] = array(
        'relation' => 'OR',
        array(
            'taxonomy' => 'post_tag',
            'terms' => $tag_ids,
        ),
        array(
            'taxonomy' => 'category',
            'terms' => $cat_ids,
        ),
	);

else:
	$categories = get_the_category( $post->ID );
	$cat_ids = array();
	foreach( $categories as $category) {
		$cat_ids[] = $category->cat_ID;
	}
	$custom_query_args['category__in'] = $cat_ids; // Select posts in the same categories as the current post
endif;


// Initiate the custom query
$custom_query = new WP_Query( $custom_query_args );

// Run the loop and output data for the results
if ( $custom_query->have_posts() ) : ?>
<div class="related-post mb-4 d-print-none">
	<div class="block-title-2"><h4 class="h5"><span>Related Posts</span></h4></div>
	<?php if ( $related_style == 'choice-3' ) { ?>
    	<?php while ( $custom_query->have_posts() ) : $custom_query->the_post(); ?>
    	<?php get_template_part( 'loop-templates/content', 'smalls', get_post_format() ); ?>
    	<?php endwhile; ?>
    	<div class="gap-0"></div>
	<?php } else if ( $related_style == 'choice-4' ) { ?>
	<div class="row">
	    <?php while ( $custom_query->have_posts() ) : $custom_query->the_post(); ?>
	    <div class="col-sm-6">
	        <h3 class="card-title h5"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
	    </div>
	    <?php endwhile; ?>
	</div>
	<?php } ?>
</div>
<?php else : ?>
<?php endif;
// Reset postdata
wp_reset_postdata();
?>
<!--End Related Posts-->
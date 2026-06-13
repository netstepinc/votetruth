<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Single place for KB archive query: main archive (all kb, including uncategorized) or term archive (kb in this term).
$is_main_kb = true;
if ( is_tax( 'kb_cat' ) ) {
	$is_main_kb = false;
	$term = get_queried_object();
	if ( $term instanceof WP_Term ) {
		//For the updates tag (11) order by date descending
		if($term->term_id == 11){
			$orderby = 'date';
			$order = 'DESC';
		}else{
			$orderby = 'title';
			$order = 'ASC';
		}


		$GLOBALS['wp_query'] = new WP_Query( array(
			'post_type'           => 'kb',
			'posts_per_page'      => get_option( 'posts_per_page' ),
			'paged'               => max( 1, (int) get_query_var( 'paged' ) ),
			'tax_query'           => array(
				array(
					'taxonomy' => 'kb_cat',
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			),
			'orderby'             => $orderby,
			'order'                => $order,
			'ignore_sticky_posts'  => true,
		) );
	}
} elseif ( is_post_type_archive( 'kb' ) ) {
	// Main KB archive: all kb posts including uncategorized (no tax_query).
	$GLOBALS['wp_query'] = new WP_Query( array(
		'post_type'           => 'kb',
		'posts_per_page'      => get_option( 'posts_per_page' ),
		'paged'               => max( 1, (int) get_query_var( 'paged' ) ),
		'orderby'             => 'title',
		'order'               => 'ASC',
		'ignore_sticky_posts' => true,
		'tax_query'           => array(
			array(
				'taxonomy' => 'kb_cat',
				'operator' => 'NOT EXISTS',
			),
		),
	) );
}

$page_title = is_tax( 'kb_cat' ) && ( $qo = get_queried_object() ) && $qo instanceof WP_Term
	? $qo->name
	: 'Freedom Index Knowledge Base';

get_header();
echo '<div class="container-xl p-0 m-0 mx-auto"><div id="legislator-search-results"></div></div>';
get_template_part( 'global-templates/page', 'top', array( 'title' => $page_title ) );
?>
<?php //the_archive_description( '<div class="taxonomy-description text-start">', '</div>' );?>
<div class="row">
	<div class="col-12 col-md-4 col-lg-3">
		<?php get_template_part('template-parts/kb-nav'); ?>
	</div>
	<div class="col-12 col-md-8 col-lg-9">
<?php 
//If has term description, show it above the posts in a card to match the post cards. Otherwise, just show the posts.
if(is_tax( 'kb_cat' ) && ( $qo = get_queried_object() ) && $qo instanceof WP_Term && !empty($qo->description) ){
	echo '<div class="card mb-4 rounded-4 shadow"><div class="card-body">'.$qo->description.'</div></div>';
}


if ( have_posts() ) :
	while ( have_posts() ) : the_post();
?>
<div class="card mb-4 rounded-4">
	<div class="card-header rounded-top-4">
		<h2 class="fs-3 lh-1 ff-h mb-0"><a href="<?php the_permalink(); ?>" class="fs-2"><?php the_title(); ?></a></h2>
	</div>
	<div class="card-body">
<?php 
//if($is_main_kb){
	the_content();
//}else{
///	the_excerpt();
//}
?>
	</div>
</div>
<?php
	endwhile;
else :
	echo '<div class="card mb-4 rounded-4"><div class="card-body">';
	get_template_part( 'loop-templates/content', 'none' );
	echo '</div></div>';
endif;
?>
<?php vt_pagination(); ?>
	</div>
</div>
<?php 
get_template_part('global-templates/page','bottom');
get_footer();
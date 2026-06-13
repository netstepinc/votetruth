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
echo '<div class="container-xl p-0 m-0 mx-auto"><div id="legislator-search-results"></div></div>';
get_template_part('global-templates/page','top',['title' => 'Freedom Index Knowledge Base']);
?>
<div class="row">
	<div class="col-12 col-md-4 col-lg-3">
		<?php get_template_part('template-parts/kb-nav'); ?>
	</div>
	<div class="col-12 col-md-8 col-lg-9">
		<?php while ( have_posts() ) : the_post(); ?>
		<div id="post-<?php the_ID(); ?>" class="card mb-4 rounded-4 shadow">
			<div class="card-header rounded-top-4">
				<h2 class="fs-3 lh-1 ff-h mb-0"><?php echo get_the_title(); ?></h2>
			</div>
			<div class="card-body">
				<?php if ( has_post_thumbnail() ):	?>
				<figure class="image-single-wrapper">
					<?php echo get_the_post_thumbnail( $post->ID, 'large', array( 'alt' => the_title_attribute( array('echo' => false)), 'class' => 'img-fluid lazy', 'src'=> get_template_directory_uri(). '/assets/img/assets/lazy-empty.png', 'data-src'=> get_the_post_thumbnail_url( $post->ID, 'large' ) ) ); ?>
					<figcaption class="bg-themes"><?php echo get_the_post_thumbnail_caption( $post );?></figcaption>
				</figure>
				<?php endif;?>
				<?php the_content(); ?>
			</div>
		</div>
		<?php endwhile; // end of the loop. ?>
	</div>
</div>
<?php
get_template_part('global-templates/page','bottom');
get_footer();
<?php
/**
 * Single post partial template.
 *
 * @package bootnews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
	<div class="entry-content post-content">
		<?php
		$author = get_post_meta( get_the_ID(), 'jbs_author', true );
		$source = get_post_meta( get_the_ID(), 'jbs_source', true );
		$author_bio = get_post_meta( get_the_ID(), 'jbs_author_bio', true );
		if ( $author || $source ) {
			echo '<div class="card mb-5 bg-light border-primary"><div class="card-body fs-5">';
			if ( $author ) {
				echo '<p class="card-text text-center">Written by <strong>'.$author.'</strong></p>';
			}
			if ( $source ) {
				echo '<p class="card-text text-center">Reprinted with permission from '.$source.'</p>';
			}
			if ( $author_bio ) {
				echo $author_bio;
			}
			echo '</div></div>';
		}

		if ( has_post_thumbnail() ) {
		?>
		<figure class="image-single-wrapper">
			<?php echo get_the_post_thumbnail( $post->ID, 'large', array( 'alt' => the_title_attribute( array('echo' => false)), 'class' => 'img-fluid lazy', 'src'=> get_template_directory_uri(). '/assets/img/assets/lazy-empty.png', 'data-src'=> get_the_post_thumbnail_url( $post->ID, 'large' ) ) ); ?>
			<figcaption class="bg-themes"><?php echo get_the_post_thumbnail_caption( $post );?></figcaption>
		</figure>
		<?php
		}
		?>
		<?php the_content(); ?>
		<?php
//Provide Wayback Machine link for editor reference
if( is_user_logged_in() && current_user_can('edit_posts') ):
	$wayback_url = get_post_meta( $post->ID, 'jbs_wayback_url', true );
	if($wayback_url):
		echo '<div><a href="'.$wayback_url.'" target="_blank">WBM <i class="fa-solid fa-arrow-up-right-from-square"></i></a></div>';
	endif;
endif;

		wp_link_pages(
			array(
				'before' => '<div class="page-links">' . __( 'Pages:', 'scorecard' ),
				'after'  => '</div>',
			)
		);
		?>
	</div>
</article>
<?php
/**
 * Partial template for content in page.php
 */
if ( ! defined( 'ABSPATH' ) ) {exit;}
?>

<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
	<header class="entry-header post-title">
		<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
	</header>
	<div class="entry-content post-content post-page">
		<?php the_content(); ?>
	</div>
</article>
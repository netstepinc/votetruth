<?php
/**
 * Post rendering content according to caller of get_template_part.
 *
 * @package bootnews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<article class="card card-full hover-a mb-module mb-4">
    <div class="card-body pt-0">
        <h2 class="card-title h5 h4-sm h3-lg">
        	<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h2>
        <p class="card-text mb-2 d-none d-md-block"><?php echo excerpt(30); ?></p>
    </div>
</article>
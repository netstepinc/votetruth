<?php if(!defined('ABSPATH')){exit;}

function sam_pagination( $args = array(), $class = 'pagination' ) {
	if ( $GLOBALS['wp_query']->max_num_pages <= 1 ) {
		return;
	}

	$args = wp_parse_args(
		$args,
		array(
			'mid_size'           => 2,
			'prev_next'          => true,
			'prev_text'          => __( '&laquo;', 'scorecard' ),
			'next_text'          => __( '&raquo;', 'scorecard' ),
			'screen_reader_text' => __( 'Posts navigation', 'scorecard' ),
			'type'               => 'array',
			'current'            => max( 1, get_query_var( 'paged' ) ),
		)
	);

	$links = paginate_links( $args );

	if ( ! is_array( $links ) ) {
		return;
	}
	?>
	<div class="clearfix my-4">
		<nav class="float-start" aria-label="<?php echo esc_attr( $args['screen_reader_text'] ); ?>">
			<ul class="<?php echo esc_attr( $class ); ?>">
				<?php foreach ( $links as $link ) : ?>
					<li class="page-item <?php echo strpos( $link, 'current' ) ? 'active' : ''; ?>">
						<?php echo str_replace( 'page-numbers', 'page-link', $link ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<span class="py-2 float-end"></span>
	</div>
	<?php
}

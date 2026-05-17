<?php if(!defined('ABSPATH')){exit;}

function sam_breadcrumb() {
	$sep = "&nbsp;&nbsp;&#187;&nbsp;&nbsp;";
	$query_obj = get_queried_object();

	if ( ! is_front_page() ) {
		echo '<div class="breadcrumb u-breadcrumb pt-0 px-0 mb-0 bg-transparent small">';
		echo '<a class="breadcrumb-item" href="' . esc_url( get_option( 'home' ) ) . '">Home</a>' . $sep;

		if ( is_page() ) {
			$ancestors = get_post_ancestors( get_the_ID() );
			if ( is_object( $query_obj ) && property_exists( $query_obj, 'post_name' ) && $query_obj->post_name === 'checkout' ) {
				echo '<a href="' . esc_url( home_url() ) . '/shop/">Shop</a>' . $sep . '<a href="' . esc_url( home_url() ) . '/shop/cart/">Cart</a>' . $sep;
			} elseif ( $ancestors ) {
				foreach ( array_reverse( $ancestors ) as $ancestor ) {
					echo '<a href="' . esc_url( get_permalink( $ancestor ) ) . '">' . esc_html( get_the_title( $ancestor ) ) . '</a>' . $sep;
				}
			}
			the_title();
		} elseif ( is_category() || is_single() ) {
			the_category( $sep );
			if ( is_single() ) {
				echo $sep . '<span class="d-none d-md-inline">' . get_the_title() . '</span>';
			}
		} elseif ( is_search() ) {
			echo $sep . 'Search Results for "<em>' . esc_html( get_search_query() ) . '</em>"';
		} elseif ( is_404() ) {
			echo '404 Page Not Found';
		} elseif ( is_home() ) {
			$page_for_posts_id = get_option( 'page_for_posts' );
			if ( $page_for_posts_id ) {
				$post = get_page( $page_for_posts_id );
				setup_postdata( $post );
				the_title();
				rewind_posts();
			}
		}
		echo '</div>';
	}
}

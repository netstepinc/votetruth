<?php if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'FI_KB' ) ) {
	class FI_KB {
		public $post_type;

		function __construct() {
			// Knowledge Base CPT (Freedom Index)
			$this->post_type = 'kb';
			add_action( 'init', array( $this, 'register_post_type' ) );
			add_action( 'init', array( $this, 'register_taxonomy' ) );
			add_action( 'init', array( $this, 'add_help_archive_rewrite_rules' ), 20 );
			add_action( 'template_redirect', array( $this, 'redirect_help_to_archive' ) );
		}

		/**
		 * Main KB archive at /help/all/ (two segments so it doesn't conflict with taxonomy /help/{term}/).
		 * Pagination: /help/all/page/n/
		 */
		function add_help_archive_rewrite_rules() {
			add_rewrite_rule( 'help/all/?$', 'index.php?post_type=' . $this->post_type, 'top' );
			add_rewrite_rule( 'help/all/page/([0-9]+)/?$', 'index.php?post_type=' . $this->post_type . '&paged=$matches[1]', 'top' );
		}

		/** Redirect /help/ to /help/all/ so the main archive is reachable. */
		function redirect_help_to_archive() {
			if ( ! is_404() ) {
				return;
			}
			$uri = isset( $_SERVER['REQUEST_URI'] ) ? strtok( $_SERVER['REQUEST_URI'], '?' ) : '';
			$uri = rtrim( $uri, '/' );
			$path = trim( parse_url( home_url( '/' ), PHP_URL_PATH ) );
			if ( $path ) {
				$uri = preg_replace( '#^' . preg_quote( $path, '#' ) . '#', '', $uri );
			}
			$uri = trim( $uri, '/' );
			if ( $uri === 'help' || $uri === 'help/' ) {
				wp_safe_redirect( home_url( '/help/all/' ), 302 );
				exit;
			}
		}

		function register_post_type() {
			$args	= array(
				"label" => __( "Articles", "jbs" ),
				"labels" => array(
					"name"	  				=> esc_html_x( "Articles", "post type general name", "jbs" ),
					"singular_name"			=> esc_html_x( "Article", "post type singular name", "jbs" ),
					"menu_name"				=> esc_html__( "Knowledge Base", "jbs" ),
					"all_items"				=> esc_html__( "All Articles", "jbs" ),
					"add_new"	 			=> esc_html_x( "Add New", "jbs" ),
					"add_new_item"			=> esc_html__( "Add New Article", "jbs" ),
					"edit_item"				=> esc_html__( "Edit Article", "jbs" ),
					"new_item"	  			=> esc_html__( "New Article", "jbs" ),
					"view_item"				=> esc_html__( "View Article", "jbs" ),
					"search_items"			=> esc_html__( "Search Articles", "jbs" ),
					"not_found"				=> esc_html__( "No Articles Found", "jbs" ),
					"not_found_in_trash"	=> esc_html__( "No Articles Found In Trash", "jbs" ),
					"parent" 				=> __( "Parent Article:", "jbs" ),
					"featured_image" 		=> __( "Featured image for this Article", "jbs" ),
					"set_featured_image" 	=> __( "Set featured image for this Article", "jbs" ),
					"remove_featured_image" => __( "Remove featured image for this Article", "jbs" ),
					"use_featured_image" 	=> __( "Use as featured image for this Article", "jbs" ),
					"archives" 				=> __( "Article archives", "jbs" ),
					"insert_into_item"		=> __( "Insert into Article", "jbs" ),
					"uploaded_to_this_item" => __( "Upload to this Article", "jbs" ),
					"filter_items_list"		=> __( "Filter Articles list", "jbs" ),
					"items_list_navigation" => __( "Articles list navigation", "jbs" ),
					"items_list"			=> __( "Articles list", "jbs" ),
					"attributes" 			=> __( "Article attributes", "jbs" ),
					"name_admin_bar" 		=> __( "Article", "jbs" ),
					"item_published" 		=> __( "Article published", "jbs" ),
					"item_published_privately" => __( "Article published privately.", "jbs" ),
					"item_reverted_to_draft" => __( "Article reverted to draft.", "jbs" ),
					"item_scheduled" 		=> __( "Article scheduled", "jbs" ),
					"item_updated" 			=> __( "Article updated.", "jbs" ),
					"parent_item_colon" 	=> __( "Parent Article:", "jbs" ),
				),
				"description" 			=> "",
				"public"			 	=> true,
				"publicly_queryable" 	=> true,
				"show_ui"	 			=> true,
				"show_in_rest"			=> true,
				"rest_base"				=> 'help',
				"rest_controller_class" => "WP_REST_Posts_Controller",
				"has_archive" 			=> true,
				"show_in_menu"			=> true,
				"show_in_nav_menus"		=> true,
				"delete_with_user" 		=> false,
				"exclude_from_search" 	=> false,
				"capability_type" 		=> "post",
				"map_meta_cap" 			=> true,
				"hierarchical" 			=> false,
				// Pretty permalinks: /help/{kb_cat}/{post_slug}
				// The %kb_cat% token is replaced by fi_kb_post_type_link() filter below.
				"rewrite" => array(
					"slug"       => 'help/%kb_cat%',
					"with_front" => true,
				),
				"query_var"		  		=> true,
				"menu_position"	  		=> 5,
				"menu_icon"		  		=> "dashicons-editor-help",
				"exclude_from_search"	=> true,
				"can_export"			=> true,
				"show_in_admin_bar"		=> true,
				"supports" => array(
					'title',
					'editor',
					'author',
					'thumbnail',
					'excerpt',
					'custom-fields',
					'revisions',
					'page-attributes',
					'post-formats'
				),
				"show_in_rest" => true, // Ensures full block editor support
				"taxonomies"			=> array('kb_cat'),
			);

			$register_result = register_post_type( $this->post_type, $args );
			if (is_wp_error($register_result)) {
				error_log('Failed to register Knowledge Base post type: ' . $register_result->get_error_message());
			}
		}

		function register_taxonomy() {
			if (!function_exists('register_taxonomy')) {
				return;
			}

			$args = array(
				"label" => __( "Knowledge Base Categories", "jbs" ),
				"labels" => array(
					"name" => __( "Knowledge Base Categories", "jbs" ),
					"singular_name" => __( "Category", "jbs" ),
					"menu_name" => __( "KB Categories", "jbs" ),
					"all_items" => __( "All Categories", "jbs" ),
					"edit_item" => __( "Edit Category", "jbs" ),
					"view_item" => __( "View Category", "jbs" ),
					"update_item" => __( "Update Category name", "jbs" ),
					"add_new_item" => __( "Add new Category", "jbs" ),
					"new_item_name" => __( "New Category name", "jbs" ),
					"parent_item" => __( "Parent Category", "jbs" ),
					"parent_item_colon" => __( "Parent Category:", "jbs" ),
					"search_items" => __( "Search Categories", "jbs" ),
					"popular_items" => __( "Popular Categories", "jbs" ),
					"separate_items_with_commas" => __( "Separate Categories with commas", "jbs" ),
					"add_or_remove_items" => __( "Add or remove Categories", "jbs" ),
					"choose_from_most_used" => __( "Choose from the most used Categories", "jbs" ),
					"not_found" => __( "No Categories found", "jbs" ),
					"no_terms" => __( "No Categories", "jbs" ),
					"items_list_navigation" => __( "Categories list navigation", "jbs" ),
					"items_list" => __( "Categories list", "jbs" ),
					"back_to_items" => __( "Back to Categories", "jbs" ),
				),
				"public" => true,
				"publicly_queryable" => true,
				"hierarchical" => true,
				"show_ui" => true,
				"show_in_menu" => true,
				"show_in_nav_menus" => true,
				"query_var" => true,
				// Term archive: /help/{kb_cat} (hierarchical for nested topics if needed)
				"rewrite" => array(
					'slug'         => 'help',
					'with_front'   => true,
					'hierarchical' => true,
				),
				"show_admin_column" => true,
				"show_in_rest" => true,
				"rest_base" => "kb_cat",
				"rest_controller_class" => "WP_REST_Terms_Controller",
				"show_in_quick_edit" => true,
				// Explicit caps so REST/block editor can assign terms; assign_terms = edit_posts (same as CPT).
/*
				"capabilities" => array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_posts',
				),
*/
			);

			$register_result = register_taxonomy( 'kb_cat', $this->post_type, $args );
			if (is_wp_error($register_result)) {
				error_log('Failed to register kb_cat taxonomy: ' . $register_result->get_error_message());
			}
		}
	}
}
new FI_KB();

/**
 * Replace %kb_cat% placeholder in KB permalinks with the primary category slug.
 * Fallbacks:
 * - First kb_cat term if multiple are assigned.
 * - 'general' if no kb_cat is assigned (still under /help/general/{slug}).
 */
function fi_kb_post_type_link( $post_link, $post, $leavename ) {
	if ( $post->post_type !== 'kb' ) {
		return $post_link;
	}

	if ( strpos( $post_link, '%kb_cat%' ) === false ) {
		return $post_link;
	}

	$terms = get_the_terms( $post->ID, 'kb_cat' );
	if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
		// Use first term slug; could be extended later for "primary" term logic.
		$term_slug = $terms[0]->slug;
	} else {
		$term_slug = 'general';
	}

	return str_replace( '%kb_cat%', $term_slug, $post_link );
}
add_filter( 'post_type_link', 'fi_kb_post_type_link', 10, 3 );



//ADMIN LIST: Custom Post Type Filter Admin By Custom Taxonomy
function filter_kb_by_taxonomies( $post_type, $which ) {

	// Apply this only on a specific post type
	if ( 'kb' !== $post_type ){
		return;
	}

	// A list of taxonomy slugs to filter by
	$taxonomies = array( 'kb_cat');
	foreach ( $taxonomies as $taxonomy_slug ) {
		$selected = isset($_GET[$taxonomy_slug]) ? $_GET[$taxonomy_slug] : '';
		$field_name = $taxonomy_slug;
		wp_dropdown_categories(
			array(
				'show_option_none' => get_taxonomy($taxonomy_slug)->labels->all_items,
				'option_none_value' => '',
				'hide_empty' => 0,
				'hierarchical' => 1,
				'show_count' => 1,
				'selected' => $selected,
				'orderby' => 'name',
				'name' => $field_name,
				'value_field' => 'slug',
				'taxonomy' => $taxonomy_slug,
			)
		);
	}
}
add_action( 'restrict_manage_posts', 'filter_kb_by_taxonomies' , 10, 2);


function fi_kb_by_slug($slug) {
	$args = array(
		'name'           => $slug,
		'post_type'      => 'kb',
		'post_status'    => 'publish',
		'numberposts'    => 1,
		'suppress_filters' => false,
	);
	$posts = get_posts($args);
	$post = !empty($posts) ? $posts[0] : null;
	$data = [];
	if($post) {
		$data['title'] = wp_kses_post($post->post_title);
		$data['content'] = wp_kses_post($post->post_content);
		$data['excerpt'] = wp_kses_post($post->post_excerpt);
		$data['thumbnail'] = get_post_thumbnail_id($post->ID);
		$data['url'] = get_the_permalink($post->ID);
	}else{
		return null;
	}
	return $data;
}
<?php
/**
 * Plugin Name: FI Custom SEO
 * Description: Lightweight per-page SEO meta tag control
 * Version:     1.0
 * Author:      Your Name
 */

namespace FI\Core {

    class SEO {

        /** @var array Holds all prepared meta data for the current page */
        private static $data = [
            'title'       => '',
            'description' => '',
            'canonical'   => '',
            'robots'      => '',
            'og'          => [],   // Open Graph tags
            'twitter'     => [],   // Twitter card tags
        ];

        /** Initialize everything */
        public static function init() {
            add_action( 'template_redirect', [ __CLASS__, 'prepare_data' ] );

            // Modern title handling
            add_filter( 'pre_get_document_title',  [ __CLASS__, 'filter_title' ], 99 );
            add_filter( 'document_title_parts',    [ __CLASS__, 'filter_title_parts' ], 99 );

            // Output meta tags very early in <head>
            add_action( 'wp_head', [ __CLASS__, 'output_tags' ], 1 );
        }

        /** Runs once per page – this is where you decide what the meta should be */
        public static function prepare_data() {
            // Example: allow external call via fi_seo_tags() – if nothing passed, do nothing
            if ( empty( self::$data['title'] ) && ! doing_filter( 'wp_head' ) ) {
                // You can auto-detect here if you want, but usually you'll call fi_seo_tags()
                return;
            }
        }

        /** Filter the final <title> */
        public static function filter_title( $title ) {
            return ! empty( self::$data['title'] ) ? self::$data['title'] : $title;
        }

        public static function filter_title_parts( $parts ) {
            if ( ! empty( self::$data['title'] ) ) {
                $parts['title'] = self::$data['title'];
            }
            return $parts;
        }

        /** Main output function – called inside wp_head */
        public static function output_tags() {

            // Title
            if ( ! empty( self::$data['title'] ) ) {
                echo '<title>' . esc_attr( self::$data['title'] ) . '</title>' . PHP_EOL;
            }

            // Description
            if ( ! empty( self::$data['description'] ) ) {
                echo '<meta name="description" content="' . esc_attr( self::$data['description'] ) . '">' . PHP_EOL;
            }

            // Robots
            if ( ! empty( self::$data['robots'] ) ) {
                echo '<meta name="robots" content="' . esc_attr( self::$data['robots'] ) . '">' . PHP_EOL;
            }

            // Canonical
            if ( ! empty( self::$data['canonical'] ) ) {
                echo '<link rel="canonical" href="' . esc_url( self::$data['canonical'] ) . '">' . PHP_EOL;
            }

            // Open Graph
			//Set default share image if not set: FI_SHARE_IMAGE
			if ( empty( self::$data['og']['og:image'] ) ) {
				self::$data['og']['og:image'] = FI_SHARE_IMAGE;
			}
            foreach ( self::$data['og'] as $property => $content ) {
                if ( $content ) {
                    echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '">' . PHP_EOL;
                }
            }

            // Twitter Cards
			//Set default share image if not set: FI_SHARE_IMAGE
			if ( empty( self::$data['twitter']['twitter:image'] ) ) {
				self::$data['twitter']['twitter:image'] = FI_SHARE_IMAGE;
			}
            foreach ( self::$data['twitter'] as $name => $content ) {
                if ( $content ) {
                    echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $content ) . '">' . PHP_EOL;
                }
            }
        }

        /** Helper to set all data at once (used by the global function below) */
        public static function set_data( array $args ) {
            $defaults = [
                'title'       => '',
                'description' => '',
                'canonical'   => '',
                'robots'      => '',
                'og'          => [],
                'twitter'     => [],
            ];

            $args = wp_parse_args( $args, $defaults );

            // Auto-fill canonical if not provided
            if ( empty( $args['canonical'] ) && is_singular() ) {
                $args['canonical'] = get_permalink();
            }

            self::$data = $args;
        }
    }

    // Auto-start the class
    SEO::init();

} // End namespace FI\Core

namespace {

    /**
     * Global helper function – just call this anywhere before wp_head()
     *
     * @param array $args {
     *     @type string $title        Page title
     *     @type string $description  Meta description
     *     @type string $canonical    Canonical URL
     *     @type string $robots       noindex,nofollow etc.
     *     @type array  $og           Open Graph tags (property => content)
     *     @type array  $twitter      Twitter card tags (name => content)
     * }
     */
    function fi_seo_tags( $args = [] ) {
        if ( ! class_exists( 'FI\Core\SEO' ) ) {
            return;
        }
        \FI\Core\SEO::set_data( $args );

		// This will disable Yoast on all pages where fi_seo_tags() is called *before* wp_head runs,
		add_filter( 'wpseo_frontend_presenters', function( $presenters ) { return array(); } );
    }

} // End global namespace


/* EXAMPLE USAGE:

// In your template, shortcode, or page-specific plugin file:
fi_seo_tags([
    'title'       => 'My Custom Page Title | Brand Name',
    'description' => 'This is a custom meta description set dynamically.',
    'canonical'   => 'https://example.com/my-page/',
    'robots'      => 'index, follow',
    'og' => [
        'og:title'       => 'My Custom Page Title | Brand Name',
        'og:description' => 'This is a custom meta description set dynamically.',
        'og:url'         => 'https://example.com/my-page/',
        'og:type'        => 'article',
        'og:image'       => 'https://example.com/og-image.jpg',
    ],
    'twitter' => [
        'twitter:card'        => 'summary_large_image',
        'twitter:title'       => 'My Custom Page Title',
        'twitter:description' => 'Custom description for Twitter',
        'twitter:image'       => 'https://example.com/og-image.jpg',
    ],
]);

*/
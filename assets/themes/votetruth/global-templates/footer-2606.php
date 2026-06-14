<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Footer Template — v2605
 *
 * Replaces the legacy "footercustom" centered nav + copyright with the
 * civic-tone footer from the redesign:
 *
 *   - Closing tagline
 *   - Inline link row (uses the WP "footer" menu location if populated;
 *     falls back to hardcoded About · Methodology · Help · Privacy)
 *   - Attribution line (JBS · The New American)
 *   - Copyright line
 *
 * @package bootnews
 */

// Bottom sheet container for search results and state/federal selectors
get_template_part('global-templates/bottom-sheet');

$has_footer_menu = has_nav_menu( 'footer' );
?>
<footer class="fi-footer d-print-none" role="contentinfo">
	<div class="container-xl text-center">
		<p class="fi-footer-closing">Politicians say a lot of things. Their votes tell the truth.</p>

		<?php if ( $has_footer_menu ) : ?>
			<?php
			wp_nav_menu(
				array(
					'theme_location'  => 'footer',
					'container'       => false,
					'menu_class'      => 'fi-footer-nav list-unstyled d-flex flex-wrap justify-content-center',
					'menu_id'         => 'fi-footer-menu',
					'fallback_cb'     => '',
					'depth'           => 1,
					'walker'          => class_exists( 'bootstrap_5_wp_nav_menu_walker' ) ? new bootstrap_5_wp_nav_menu_walker() : null,
				)
			);
			?>
		<?php else : ?>
			<p class="fi-footer-links">
				<a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About</a> &nbsp;·&nbsp;
				<a href="<?php echo esc_url( home_url( '/about/#methodology' ) ); ?>">Methodology</a> &nbsp;·&nbsp;
				<a href="<?php echo esc_url( home_url( '/help/' ) ); ?>">Help</a> &nbsp;·&nbsp;
				<a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>">Privacy</a>
			</p>
		<?php endif; ?>

		<p class="fi-footer-attribution">
			<a href="https://jbs.org" target="_blank" class="fs-7" rel="noopener">The John Birch Society</a> &nbsp;·&nbsp;
			<a href="https://thenewamerican.com" target="_blank" class="fs-7" rel="noopener">The New American</a>
		</p>

		<p class="fi-footer-copy text-white">
			&copy; <?php echo esc_html( date( 'Y' ) ); ?> VoteTruth.us &nbsp;·&nbsp;Know the Score. Hold them Accountable.
		</p>
	</div>
</footer>
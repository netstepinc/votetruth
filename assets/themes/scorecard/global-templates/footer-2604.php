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

// Fall back to a static link list if the "footer" menu has no items assigned.
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
			<a href="https://jbs.org" target="_blank" rel="noopener">The John Birch Society</a> &nbsp;·&nbsp;
			<a href="https://thenewamerican.com" target="_blank" rel="noopener">The New American</a>
		</p>

		<p class="fi-footer-copy">
			&copy; <?php echo esc_html( date( 'Y' ) ); ?> FreedomIndex.us &nbsp;·&nbsp;
			Nonpartisan tracking of constitutional votes since 1971
		</p>
	</div>
</footer>

<style>
/* ──────────────────────────────────────────────────────────────
   FreedomIndex — v2605 footer styles
   ────────────────────────────────────────────────────────────── */
:root {
	--fi-navy:    #002b62;
}

.fi-footer {
	background: var(--fi-navy);
	color: rgba(255, 255, 255, 0.5);
	padding: 36px 24px 28px;
	font-size: 14px;
	line-height: 1.8;
}
.fi-footer a { color: rgba(255, 255, 255, 0.65); text-decoration: none; }
.fi-footer a:hover, .fi-footer a:focus { color: #ffffff; }

.fi-footer-closing {
	font-size: 18px;
	font-weight: 600;
	color: rgba(255, 255, 255, 0.88);
	margin-bottom: 20px;
	letter-spacing: -0.01em;
}

/* WP-rendered footer menu */
.fi-footer-nav {
	gap: 0;
	margin: 0 0 12px;
	padding: 0;
}
.fi-footer-nav li {
	display: inline-flex;
	align-items: center;
}
.fi-footer-nav li::after {
	content: "·";
	color: rgba(255, 255, 255, 0.35);
	margin: 0 10px;
}
.fi-footer-nav li:last-child::after { content: ""; margin: 0; }
.fi-footer-nav li a { padding: 0; }

.fi-footer-links { margin-bottom: 12px; }

.fi-footer-attribution {
	font-size: 13px;
	color: rgba(255, 255, 255, 0.4);
	margin-bottom: 8px;
}
.fi-footer-attribution a { color: rgba(255, 255, 255, 0.45); }

.fi-footer-copy { margin: 4px 0 0; }
</style>

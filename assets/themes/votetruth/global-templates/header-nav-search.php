<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Header Template — v2606
 *
 * Single-row: Logo | Search (flex-grow, always visible) | ≡ Menu dropdown
 *   - Logo: full text on sm+, "VT" abbreviation on xs
 *   - Search: primary feature, always visible, takes all remaining space
 *   - Menu: hamburger icon + "Menu" label (label hidden on xs)
 *   - Nav links in a BS5 dropdown panel (right-aligned)
 *   - Admin-bar top offset retained
 */

$logged_in   = is_user_logged_in();
$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$account_url = $logged_in
	? esc_url( home_url( '/account/' ) )
	: esc_url( home_url( '/account/' ) . '?redirect_to=' . urlencode( $current_url ) );

// Admin bar offset for sticky header
$header_style = '';
if ( is_admin_bar_showing() ) {
	$header_style = ' style="top:32px"';
}

// Build nav links
$nav_links = [];

if ( ! is_front_page() ) {
	$nav_links[] = [
		'label' => 'U.S. Congress',
		'url'   => '#',
		'attrs' => 'data-bs-toggle="bottom-sheet" data-content="federal"',
	];
	$nav_links[] = [
		'label' => 'Select a State',
		'url'   => '#',
		'attrs' => 'data-bs-toggle="bottom-sheet" data-content="state"',
	];
}

$nav_links[] = [ 'label' => 'Get the App', 'url' => esc_url( home_url( '/app/' ) ) ];
$nav_links[] = [ 'label' => 'About',       'url' => esc_url( home_url( '/about/' ) ) ];
$nav_links[] = [ 'label' => 'Help',        'url' => esc_url( home_url( '/help/' ) ) ];

// Separate "secondary" links (shown after divider)
$secondary_start = ( ! is_front_page() ) ? 3 : 1; // After congress/state/app on inner pages; after app on home
?>
<header class="fi-header sticky-top"<?php echo $header_style; ?> role="banner">
	<nav class="fi-navbar py-2" aria-label="Main navigation">
	<div class="container-xl d-flex align-items-center gap-2 gap-sm-3">

		<!-- ── Logo ── -->
		<a class="fi-logo flex-shrink-0 text-decoration-none"
		   rel="home"
		   href="<?php echo esc_url( home_url( '/' ) ); ?>"
		   title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>">
			<!-- Full logo: sm and up -->
			<span class="text-logo lh-1 d-none d-sm-block">
				<span class="text-action">Vote</span><span class="text-white">Truth</span>
			</span>
			<!-- Compact logo: xs only -->
			<span class="fi-logo-compact d-sm-none">
				<span class="text-action">V</span><span class="text-white">T</span>
			</span>
		</a>

		<!-- ── Search — always visible, centered, max 480px ── -->
		<form id="header-legislator-search-form"
			  class="fi-header-search-form flex-grow-1 position-relative"
			  method="get"
			  action="<?php echo esc_url( home_url( '/' ) ); ?>"
			  role="search"
			  novalidate>
			<div class="input-group fi-search-group">
				<span class="input-group-text fi-search-prefix bg-warm pe-1 d-none d-sm-flex">
					<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
				</span>
				<input id="header-legislator-search-input"
					   class="form-control search-box bg-warm ps-2"
					   name="fi_search"
					   type="search"
					   placeholder="<?php echo esc_attr( FI_SEARCH_PLACEHOLDER ); ?>"
					   value="<?php echo esc_attr( isset( $_GET['fi_search'] ) ? wp_unslash( $_GET['fi_search'] ) : '' ); ?>"
					   aria-label="Enter ZIP code or legislator name"
					   autocomplete="off"
					   minlength="3">
				<button id="header-search-clear-btn"
						class="btn fi-search-clear bg-warm px-2 d-none"
						type="button"
						aria-label="Clear search">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
				</button>
				<button class="btn fi-search-submit fw-bold" type="submit" aria-label="Search">
					<!-- Icon only on xs -->
					<i class="bi bi-search d-sm-none" aria-hidden="true"></i>
					<!-- Text on sm+ -->
					<span class="d-none d-sm-inline">Search</span>
				</button>
			</div>
			<!-- Autocomplete suggestions -->
			<div id="header-search-suggestions"
				 class="position-absolute top-100 start-0 w-100 bg-white border rounded-bottom shadow-lg d-none"
				 style="z-index:1050;max-height:400px;overflow-y:auto;"></div>
		</form>

		<!-- ── Menu dropdown ── -->
		<div class="dropdown flex-shrink-0">
			<button class="fi-menu-btn btn d-flex align-items-center gap-1 gap-sm-2"
					type="button"
					data-bs-toggle="dropdown"
					aria-expanded="false"
					aria-label="Toggle navigation">
				<!-- Hamburger icon (hidden when open) -->
				<svg class="fi-menu-icon-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
					<path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
				</svg>
				<!-- X icon (shown when open) -->
				<svg class="fi-menu-icon-close" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
					<path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
				</svg>
				<span class="fi-menu-label d-none d-sm-inline fw-bold">Menu</span>
			</button>

			<ul class="dropdown-menu dropdown-menu-end fi-nav-dropdown shadow mt-1">
				<?php
				$divider_added = false;
				foreach ( $nav_links as $i => $link ) :
					// Add divider before secondary links (About, Help)
					if ( ! $divider_added && in_array( $link['label'], [ 'About', 'Help' ] ) ) :
						$divider_added = true;
						echo '<li><hr class="dropdown-divider my-1"></li>';
					endif;
				?>
				<li>
					<?php if ( $link['url'] === '#' ) : ?>
						<button type="button"
								class="dropdown-item"
								<?php echo isset( $link['attrs'] ) ? $link['attrs'] : ''; ?>>
							<?php echo esc_html( $link['label'] ); ?>
						</button>
					<?php else : ?>
						<a class="dropdown-item" href="<?php echo esc_url( $link['url'] ); ?>">
							<?php echo esc_html( $link['label'] ); ?>
						</a>
					<?php endif; ?>
				</li>
				<?php endforeach; ?>
				<li><hr class="dropdown-divider my-1"></li>
				<li class="px-3 py-1">
					<a class="btn btn-primary w-100 fw-bold"
					   href="<?php echo $account_url; ?>">
						<?php echo $logged_in ? 'My Account' : 'Login'; ?>
					</a>
				</li>
			</ul>
		</div>

	</div><!-- /.container-xl -->
	</nav>
</header>

<script>
/* Swap hamburger ↔ X icon based on menu state */
(function () {
	document.addEventListener('DOMContentLoaded', function () {
		var btn = document.querySelector('.fi-menu-btn');
		if (!btn) return;
		btn.addEventListener('shown.bs.dropdown', function () { btn.setAttribute('aria-expanded', 'true'); });
		btn.addEventListener('hidden.bs.dropdown', function () { btn.setAttribute('aria-expanded', 'false'); });

		/* Swap placeholder on small screens */
		var input = document.getElementById('header-legislator-search-input');
		if (!input) return;
		function updatePlaceholder() {
			input.placeholder = window.innerWidth < 576
				? 'ZIP code / name'
				: '<?php echo esc_js( FI_SEARCH_PLACEHOLDER ); ?>';
		}
		updatePlaceholder();
		window.addEventListener('resize', updatePlaceholder);
	});
})();
</script>

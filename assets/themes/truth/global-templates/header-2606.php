<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Header Template — v2605
 *
 * Streamlined header for the redesigned site. Replaces the legacy two-bar
 * desktop sidebar layout with a single top navigation matching the
 * "freedom-index-home-v2" design.
 *
 *   - Top navbar: App · About · Help · My Account (or Login)
 *   - Mobile: BS5 hamburger collapse (replaces offcanvas for primary nav)
 *   - PWA bottom nav retained from v2603
 *   - Admin-bar offset retained
 *
 * Notes for inner pages:
 *   - The legacy desktop "Government Sidebar" is intentionally NOT rendered
 *     here. State/government selection is now handled via the front-page
 *     hero chips and (future) a state-selector modal. Inner pages that
 *     still depend on the sidebar should be updated; until then, there is
 *     no `has-desktop-sidebar` body class to push content over.
 *
 * @package bootnews
 */

$img_logo = STYLE_IMG . 'fi-logo-w.png';
$img_logo_mobile = STYLE_IMG . 'fi-logo-w.png';

$logged_in = is_user_logged_in();

// Build a redirect-aware account URL for unauthenticated users
$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$account_url = $logged_in
	? esc_url( home_url( '/account/' ) )
	: esc_url( home_url( '/account/' ) . '?redirect_to=' . urlencode( $current_url ) );


// Get governments from Freedom Index plugin - core/references.php
$gov_links = function_exists('get_fi_gov_links') ? get_fi_gov_links() : '';

// Check if Freedom Index page
$is_fi_page = get_query_var('fi_view') || get_query_var('fi_gov') || get_query_var('fi_entity');

// Check if admin bar is showing and get its height
$admin_bar_top = '';
if (is_admin_bar_showing()) {
	// Admin bar is typically 32px on desktop (lg+), 46px on smaller screens
	// Since sidebar is position: fixed with top: 0, we need to adjust top position
	$admin_bar_top = ' style="top: 32px; height: calc(100vh - 32px);"';
	echo '<style>@media (min-width: 992px) {.sidebar-toggle {top: 44px;}}</style>';
}

$nav_links = [];
//Show Federal and State Select modal triggers when not on home page
//<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#fi-modal-federal">Federal Legislators</button>
//<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#fi-modal-state">State Legislators</button>

if ( ! is_front_page() ) {
	$nav_links[] = [
		'label' => 'Select Federal',
		'url' => '#',
		'attrs' => 'data-bs-toggle="modal" data-bs-target="#fi-modal-federal"',
		'class' => 'btn btn-link fw-bold nav-link',
	];
	
	$nav_links[] = [
		'label' => 'Select State',
		'url' => '#',
		'attrs' => 'data-bs-toggle="modal" data-bs-target="#fi-modal-state"',
		'class' => 'btn btn-link fw-bold nav-link',
	];
}

$nav_links[] = [
	'label' => 'App',
	'url' => esc_url( home_url( '/app/' ) ),
];

$nav_links[] = [
	'label' => 'About',
	'url' => esc_url( home_url( '/about/' ) ),
];

$nav_links[] = [
	'label' => 'Help',
	'url' => esc_url( home_url( '/help/' ) ),
];

$nav_links[] = [
	'label' => $logged_in ? 'My Account' : 'Login',
	'url' => $account_url,
	'class' => 'fi-nav-cta',
];
?>
<header class="fi-header sticky-top" role="banner">
	<nav class="navbar navbar-expand-lg fi-navbar" aria-label="Main navigation">
		<div class="container-xl">
			<a class="navbar-brand" rel="home" href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>">
<!--
			<img src="<?php //echo esc_url( $img_logo ); ?>" alt="<?php //echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>">
 -->
<div class="text-logo"><span class="text-amber">Votes</span><span class="text-white">Tellthe</span><span class="text-amber">Truth</span></div>
			</a>

<!-- Inline Search (Desktop) from old template. Must make work with new template -->
<?php if(!is_front_page()): ?>
<div class="d-none d-lg-block top-search-form">
	<form id="header-legislator-search-form" class="d-flex mb-0" method="#" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search" novalidate>
		<div class="input-group position-relative">
			<input id="header-legislator-search-input" class="form-control search-box" name="fi_search" type="search" placeholder="<?= FI_SEARCH_PLACEHOLDER;?>" value="<?php echo esc_attr( isset( $_GET['fi_search'] ) ? $_GET['fi_search'] : '' ); ?>" aria-label="Search" autocomplete="off" minlength="3">
			<div id="header-search-suggestions" class="position-absolute top-100 start-0 w-100 bg-white border rounded shadow-lg d-none" style="z-index: 1050; max-height: 400px; overflow-y: auto;"></div>
			<button id="header-search-clear-btn" class="btn btn-warning p-2 d-none" type="button" aria-label="Clear search" title="Clear search">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
			<button class="btn btn-outline-light p-2" type="submit" aria-label="Search">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="11" cy="11" r="8"></circle>
					<path d="m21 21-4.35-4.35"></path>
				</svg>
			</button>
		</div>
	</form>
</div>
<?php endif; ?>

			<!-- Search Icon (Mobile) -->
			<?php if ( ! is_front_page() ):?>
			<button class="btn btn-link text-white me-2 navbar-toggler p-2 d-lg-none flex-shrink-0 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#mobileSearch" aria-controls="mobileSearch" aria-expanded="false" aria-label="Toggle search">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="11" cy="11" r="8"></circle>
					<path d="m21 21-4.35-4.35"></path>
				</svg>
			</button>
			<?php endif; ?>
			<button class="navbar-toggler" type="button" data-bs-toggle="collapse"
					data-bs-target="#fiNavMain" aria-controls="fiNavMain"
					aria-expanded="false" aria-label="Toggle navigation">
				<span class="navbar-toggler-icon"></span>
			</button>

			<div class="collapse navbar-collapse justify-content-end" id="fiNavMain">
				<ul class="navbar-nav align-items-lg-center">
				<?php foreach ( $nav_links as $link ){
					$li_class = '';
					if(isset($link['class']) && !empty($link['class']) && is_string($link['class'])){
						$li_class = $link['class'];
					}
					echo '<li class="nav-item">';
					if($link['url'] === '#'){
						echo '<button type="button" class="' . ( !empty($li_class) ? ' ' . $li_class : '' ) . '"' . ( isset( $link['attrs'] ) ? ' ' . $link['attrs'] : '' ) . '>' . esc_html( $link['label'] ) . '</button>';
					}else{
						echo '<a class="nav-link' . ( !empty($li_class) ? ' ' . $li_class : '' ) . '" href="' . esc_url( $link['url'] ) . '"' . ( isset( $link['attrs'] ) ? ' ' . $link['attrs'] : '' ) . '>' . esc_html( $link['label'] ) . '</a>';
					}
					echo '</li>';
				} ?>
				</ul>
			</div>
		</div>
	</nav>
	<!-- Mobile Search Collapse -->
	<div class="collapse mt-2" id="mobileSearch">
		<form id="mobile-legislator-search-form" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search" novalidate>
			<div class="input-group shadow position-relative">
				<input id="mobile-legislator-search-input" class="form-control rounded-0 search-box" name="fi_search" type="search" placeholder="<?= FI_SEARCH_PLACEHOLDER;?>" value="<?php echo esc_attr( isset( $_GET['fi_search'] ) ? $_GET['fi_search'] : '' ); ?>" aria-label="Search" autocomplete="off" minlength="3">
				<div id="mobile-search-suggestions" class="position-absolute top-100 start-0 w-100 bg-white border rounded shadow-lg d-none" style="z-index: 1050; max-height: 300px; overflow-y: auto;"></div>
				<button id="mobile-search-clear-btn" class="btn btn-outline-light rounded-0 d-none" type="button" aria-label="Clear search" title="Clear search">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
				<button class="btn btn-primary fw-bold rounded-0" type="submit" aria-label="Search">
					Search
				</button>
			</div>
		</form>
	</div>
</header>

<?php
/**
 * Bottom Navigation Bar — Mobile / Standalone PWA only (hidden on lg+)
 * Carried over from v2603. Hidden when running as installed PWA via
 * window.FI_PWA.isStandalone (the App tab specifically; full bar still
 * visible to give thumb-friendly nav on phones).
 */
?>
<nav id="fi-bottom-nav" class="d-lg-none" aria-label="Mobile primary">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="fi-bnav-item" data-match="home">
		<i class="bi bi-house-door" aria-hidden="true"></i>
		<span>Home</span>
	</a>
	<a id="fi-bnav-app" href="<?php echo esc_url( home_url( '/app/' ) ); ?>" class="fi-bnav-item" data-match="/app">
		<i class="bi bi-download" aria-hidden="true"></i>
		<span>App</span>
	</a>
	<a href="<?php echo esc_url( home_url( '/about/' ) ); ?>" class="fi-bnav-item" data-match="/about">
		<i class="bi bi-info-circle" aria-hidden="true"></i>
		<span>About</span>
	</a>
	<a href="<?php echo esc_url( home_url( '/help/' ) ); ?>" class="fi-bnav-item" data-match="/help">
		<i class="bi bi-question-circle" aria-hidden="true"></i>
		<span>Help</span>
	</a>
	<?php if ( $logged_in ) : ?>
		<a href="<?php echo esc_url( home_url( '/account/' ) ); ?>" class="fi-bnav-item" data-match="/account">
			<i class="bi bi-person-circle" aria-hidden="true"></i>
			<span>Account</span>
		</a>
	<?php else : ?>
		<a href="<?php echo $account_url; ?>" class="fi-bnav-item" data-match="/account">
			<i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
			<span>Login</span>
		</a>
	<?php endif; ?>
</nav>
<style>
/* ──────────────────────────────────────────────────────────────
   FreedomIndex — v2605 header styles
   Tokens are defined in the theme's main stylesheet; this file
   only adds component-specific rules. If --fi-* tokens are not
   yet defined globally, the fallbacks below keep the header
   on-brand standalone.
   ────────────────────────────────────────────────────────────── */
:root {
	--fi-blue:      #0055a4;
	--fi-blue-mid:  #00408a;
	--fi-navy:      #002b62;
	--fi-navy-deep: #001840;
	--fi-r-sm:      6px;
}

/* ── Navbar ── */
.fi-navbar {
	background: var(--fi-navy);
	border-bottom: 1px solid rgba(255, 255, 255, 0.08);
	min-height: 54px;
	padding-top: 0;
	padding-bottom: 0;
}
.fi-navbar .container-xl { min-height: 54px; }
.fi-navbar .navbar-brand { padding: 0; margin-right: auto; }
.fi-navbar .navbar-brand img {
	height: 32px;
	filter: brightness(0) invert(1);
	display: block;
	max-width: 100%;
}
.fi-navbar .navbar-toggler {
	border-color: rgba(255, 255, 255, 0.3);
	padding: 0.25rem 0.5rem;
}
.fi-navbar .navbar-toggler:focus { box-shadow: 0 0 0 0.15rem rgba(255,255,255,0.2); }
.fi-navbar .navbar-toggler-icon {
	background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.85%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
}
.fi-navbar .nav-link {
	color: rgba(255, 255, 255, 0.75);
	font-size: 1rem;
	font-weight: 500;
	padding: 7px 12px !important;
	border-radius: var(--fi-r-sm);
	transition: color 0.12s, background 0.12s;
	white-space: nowrap;
}
.fi-navbar .nav-link:hover,
.fi-navbar .nav-link:focus {
	color: #fff;
	background: rgba(255, 255, 255, 0.09);
}
.fi-navbar .fi-nav-cta {
	background: var(--fi-blue);
	color: #fff !important;
	margin-left: 6px;
	padding: 7px 16px !important;
}
.fi-navbar .fi-nav-cta:hover,
.fi-navbar .fi-nav-cta:focus { background: #0066c4; }

@media (max-width: 991.98px) {
	.fi-navbar .navbar-collapse { padding: 8px 0 12px; }
	.fi-navbar .fi-nav-cta { margin-left: 0; }
}

/* Admin-bar offset for sticky header */
.admin-bar .fi-header { top: 32px; }
@media (max-width: 782px) {
	.admin-bar .fi-header { top: 46px; }
}

/* ── Bottom Mobile Nav (PWA-friendly) ── */
#fi-bottom-nav {
	position: fixed;
	bottom: 0; left: 0; right: 0;
	z-index: 1040;
	display: flex;
	background: var(--fi-navy);
	border-top: 1px solid rgba(255, 255, 255, 0.15);
	height: 60px;
	padding-bottom: env(safe-area-inset-bottom, 0px);
}
.fi-bnav-item {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	color: rgba(255, 255, 255, 0.55);
	text-decoration: none;
	font-size: 0.6rem;
	letter-spacing: 0.03em;
	text-transform: uppercase;
	gap: 3px;
	transition: color 0.15s ease;
	-webkit-tap-highlight-color: transparent;
}
.fi-bnav-item i { font-size: 1.4rem; line-height: 1; }
.fi-bnav-item.active,
.fi-bnav-item:hover { color: #ffffff; }

@media (max-width: 991.98px) {
	body { padding-bottom: calc(60px + env(safe-area-inset-bottom, 0px)) !important; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Hide the App tab when already running as an installed PWA.
	var appTab = document.getElementById('fi-bnav-app');
	if (appTab && window.FI_PWA && window.FI_PWA.isStandalone) {
		appTab.style.display = 'none';
	}

	// Mark the active tab by matching the current path.
	var path = window.location.pathname.replace(/\/$/, '') || '/';
	document.querySelectorAll('#fi-bottom-nav .fi-bnav-item').forEach(function(el) {
		var match = el.getAttribute('data-match');
		if (!match) return;
		var active = (match === 'home')
			? (path === '/' || path === '')
			: path === match || path.startsWith(match + '/');
		if (active) el.classList.add('active');
	});
});
</script>

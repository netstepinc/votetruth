<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Header Template — v2605
 *
 * Streamlined header for the redesigned site. Replaces the legacy two-bar
 * desktop sidebar layout with a single top navigation matching the
 * "freedom-index-home-v2" design.
 *
 *   - Top navbar: U.S. Congress · Select State · App · About · Help · Account
 *   - Mobile: BS5 hamburger collapse for primary navigation
 *   - Legislator search remains visible below the navbar at every breakpoint
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
		'label' => 'U.S. Congress',
		'url' => '#',
		'attrs' => 'data-bs-toggle="bottom-sheet" data-content="federal"',
		'class' => 'btn btn-link fw-bold nav-link',
	];

	$nav_links[] = [
		'label' => 'Select State',
		'url' => '#',
		'attrs' => 'data-bs-toggle="bottom-sheet" data-content="state"',
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
	'class' => 'fi-nav-cta ms-1 px-3 py-2 rounded',
];

?>
<header class="fi-header sticky-top" role="banner">
	<nav class="navbar navbar-expand-lg navbar-dark py-0 fi-navbar" aria-label="Main navigation">
		<div class="container-xl d-flex justify-content-between">
			<a class="navbar-brand flex-shrink-0 p-0" rel="home" href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>">
				<div class="text-logo"><span class="text-amber">Vote</span><span class="text-white">Truth</span></div>
			</a>


			<button class="navbar-toggler" type="button" data-bs-toggle="collapse"
					data-bs-target="#fiNavMain" aria-controls="fiNavMain"
					aria-expanded="false" aria-label="Toggle navigation">
				<span class="navbar-toggler-icon"></span>
			</button>

			<div class="collapse navbar-collapse justify-content-end flex-shrink-0" id="fiNavMain">
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

	<!-- Primary site action: always-visible legislator search -->
	<div class="fi-header-search bg-light border-bottom py-2">
		<div class="container-xl">
			<form id="header-legislator-search-form"
				  class="fi-header-search-form d-flex align-items-center gap-3 mx-auto"
				  method="get"
				  action="<?php echo esc_url( home_url( '/' ) ); ?>"
				  role="search"
				  novalidate>
				<label class="fi-header-search-label flex-shrink-0 fw-bold text-nowrap d-none d-md-inline" for="header-legislator-search-input">
					Find Legislators
				</label>

				<div class="input-group position-relative flex-grow-1">
					<input id="header-legislator-search-input"
						   class="form-control search-box"
						   name="fi_search"
						   type="search"
						   placeholder="<?php echo esc_attr( FI_SEARCH_PLACEHOLDER ); ?>"
						   value="<?php echo esc_attr( isset( $_GET['fi_search'] ) ? wp_unslash( $_GET['fi_search'] ) : '' ); ?>"
						   aria-label="Enter ZIP code or legislator name"
						   autocomplete="off"
						   minlength="3">

					<div id="header-search-suggestions"
						 class="position-absolute top-100 start-0 w-100 bg-white border rounded-bottom shadow-lg d-none"
						 style="z-index: 1050; max-height: 400px; overflow-y: auto;"></div>

					<button id="header-search-clear-btn"
							class="btn btn-outline-secondary d-none"
							type="button"
							aria-label="Clear search"
							title="Clear search">
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<line x1="18" y1="6" x2="6" y2="18"></line>
							<line x1="6" y1="6" x2="18" y2="18"></line>
						</svg>
					</button>

					<button class="btn btn-primary fw-bold fi-header-search-submit"
							type="submit">
						Search
					</button>
				</div>
			</form>
		</div>
	</div>
</header>
<?php
/**
 * Bottom Navigation Bar — Mobile / Standalone PWA only (hidden on lg+)
 * Carried over from v2603. Hidden when running as installed PWA via
 * window.FI_PWA.isStandalone (the App tab specifically; full bar still
 * visible to give thumb-friendly nav on phones).
 */

/* REMOVE FOR NOW - CONSIDERING DELETION
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

*/
<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
HERO
Votes Tell the Truth
Are your legislators working for you?
Promises are easy. Votes are proof.
Find your legislators.
[Enter ZIP code or legislator name] [Find My Legislators]
Ignore their slogans. See their record.
*/
?>
<div id="home-hero" class="container-fluid border-bottom">
	<div class="container">
		<div class="row">
			<div class="col-12 px-0 px-md-3 px-lg-5">
				<h1 class="text-center mx-auto mt-4">Votes Tell the <span class="text-amber">Truth</span></h1>
				<div class="my-3">
					<p class="fs-3 mb-2 text-fade-amber fw-7 text-center">Are your legislators working for you?</p>
					<p class="fs-3 mb-2 text-fade fw-7 text-center">Promises are easy. Votes are proof.</p>
					<p class="fs-3 mb-2 text-fade fw-7 text-center">Find your legislators.</p>
				</div>
				<form id="header-legislator-search-form" class="mb-4 col-12 col-lg-10 col-xl-8 mx-auto" method="#" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search" novalidate>
					<div class="input-group position-relative">
						<input id="header-legislator-search-input" class="form-control form-control-lg fs-4 bg-white" name="fi_search" type="search" placeholder="<?= FI_SEARCH_PLACEHOLDER;?>" value="<?php echo esc_attr( isset( $_GET['fi_search'] ) ? $_GET['fi_search'] : '' ); ?>" aria-label="Search" autocomplete="off" minlength="3">
						<div id="header-search-suggestions" class="position-absolute bg-white border rounded shadow d-none" style="z-index: 1050; max-height: 400px; overflow-y: auto;"></div>
						<button id="header-search-clear-btn" class="btn btn-warning p-2 d-none" type="button" aria-label="Clear search" title="Clear search">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
								<line x1="18" y1="6" x2="6" y2="18"></line>
								<line x1="6" y1="6" x2="18" y2="18"></line>
							</svg>
						</button>
						<button class="btn btn-amber fw-4 fs-4" type="submit" aria-label="Search">
							<span class="d-none d-xl-inline">Find My Legislator</span>
							<span class="d-none d-lg-inline d-xl-none">Find Legislator</span>
							<span class="d-lg-none">Search</span>
						</button>
					</div>
				</form>
				<p class="fs-5 text-fade fw-7 text-center">Ignore their slogans. See their record.</p>
			</div>
		</div>
<!--
		<div class="row">
			<div class="col-12 col-md-10 col-lg-8 col-xl-6 py-4 mx-auto">
				<div class="row">
					<div class="col-12 col-md-6 pb-3">
						<button type="button" class="btn btn-sm btn-glass px-4 fw-5 fs-7" data-bs-toggle="modal" data-bs-target="#fi-modal-federal">Federal Legislators</button>
					</div>
					<div class="col-12 col-md-6 pb-3">
						<button type="button" class="btn btn-sm btn-glass px-4 fw-5 fs-7" data-bs-toggle="modal" data-bs-target="#fi-modal-state">State Legislators</button>
					</div>
				</div>
			</div>
		</div>
-->
	</div>
	<div class="container text-center pt-3 pb-5">
		<button type="button" class="btn btn-sm btn-glass px-4 fw-5 fs-7 m-2" data-bs-toggle="modal" data-bs-target="#fi-modal-federal">Federal Legislators</button>
		<button type="button" class="btn btn-sm btn-glass px-4 fw-5 fs-7 m-2" data-bs-toggle="modal" data-bs-target="#fi-modal-state">State Legislators</button>
	</div>
</div>
<script>
const searchBox = document.getElementById('header-legislator-search-input');
// Define the Bootstrap md breakpoint rule (768px)
const mdBreakpoint = window.matchMedia('(min-width: 768px)');
function updatePlaceholder(e) {
	if (e.matches) {
		// Large screens
		searchBox.placeholder = "<?= FI_SEARCH_PLACEHOLDER;?>";
	} else {
		// Small screens
		searchBox.placeholder = "<?= FI_SEARCH_PLACEHOLDER_SMALL;?>";
	}
}
// Register listener for real-time window resizing
mdBreakpoint.addEventListener('change', updatePlaceholder);
// Initial check on page load
updatePlaceholder(mdBreakpoint);
</script>
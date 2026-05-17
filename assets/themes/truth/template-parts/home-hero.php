<?php if ( ! defined( 'ABSPATH' ) ) { exit; }?>
<div id="home-hero" class="container-fluid">
	<div class="container">
		<div class="row">
			<div class="col-12">
				<h1 class="text-center">Votes Tell the Truth</h1>
				<p class="lead text-fade text-center mb-5">See how your legislators vote on the issues that affect your freedom.</p>

				<form id="header-legislator-search-form" class="mb-3 col-12 col-md-9 col-lg-8 mx-auto" method="#" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search" novalidate>
					<div class="input-group position-relative">
						<input id="header-legislator-search-input" class="form-control form-control-lg bg-white" name="fs_search" type="search" placeholder="<?= FS_SEARCH_PLACEHOLDER;?>" value="<?php echo esc_attr( isset( $_GET['fs_search'] ) ? $_GET['fs_search'] : '' ); ?>" aria-label="Search" autocomplete="off" minlength="3">
						<div id="header-search-suggestions" class="position-absolute bg-white border rounded shadow d-none" style="z-index: 1050; max-height: 400px; overflow-y: auto;"></div>
						<button id="header-search-clear-btn" class="btn btn-warning p-2 d-none" type="button" aria-label="Clear search" title="Clear search">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
								<line x1="18" y1="6" x2="6" y2="18"></line>
								<line x1="6" y1="6" x2="18" y2="18"></line>
							</svg>
						</button>
						<button class="btn btn-amber fw-4 fs-5" type="submit" aria-label="Search">
							Find My Legislator
						</button>
					</div>
				</form>
				<p class="text-fade text-center">Know the facts. Hold them accountable.</p>
			</div>
		</div>
		<div class="row">
			<div class="col-12 col-md-10 col-lg-8 col-xl-6 pt-5 mx-auto">
				<div class="row">
					<div class="col-12 col-md-6">
						<button type="button" class="btn btn-sm btn-glass px-4 fw-5 fs-7" data-bs-toggle="modal" data-bs-target="#fi-modal-federal">Federal Legislators</button>
					</div>
					<div class="col-12 col-md-6">
						<button type="button" class="btn btn-sm btn-glass px-4 fw-5 fs-7" data-bs-toggle="modal" data-bs-target="#fi-modal-state">State Legislators</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
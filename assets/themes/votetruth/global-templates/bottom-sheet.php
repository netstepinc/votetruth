<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
Bottom Sheet Template - Unified container for search results and state/federal selectors
Replaces modal-select (now sheet-select) for consistent UX across home page interactions

Usage:
- From search: data-bs-toggle="bottom-sheet" data-content="search"
- From federal button: data-bs-toggle="bottom-sheet" data-content="federal"
- From state button: data-bs-toggle="bottom-sheet" data-content="state"

Include in footer with: get_template_part('template-parts/bottom-sheet');

All CSS moved to autoloaded inline CSS: assets/themes/truth/assets/customizer/99.freedomindex.css
All JavaScript moved to public-inline-js.php for unified handling
*/
?>
<!-- Bottom Sheet Container -->
<div id="fi-bottom-sheet" class="fi-bottom-sheet" role="dialog" aria-modal="true" aria-labelledby="fi-bottom-sheet-title" hidden>
	<!-- Backdrop -->
	<div class="fi-bottom-sheet-backdrop" data-bs-dismiss="bottom-sheet"></div>
	
	<!-- Sheet Panel -->
	<div class="fi-bottom-sheet-panel">
		<!-- Drag Handle (mobile) -->
		<div class="fi-bottom-sheet-handle d-md-none" aria-hidden="true">
			<div class="fi-bottom-sheet-handle-bar"></div>
		</div>
		
		<!-- Header -->
		<div class="fi-bottom-sheet-header">
			<div class="d-flex align-items-center justify-content-between">
				<h2 id="fi-bottom-sheet-title" class="fi-bottom-sheet-title fs-6 mb-0">Search Results</h2>
				<button type="button" class="btn-close fi-bottom-sheet-close" aria-label="Close" data-bs-dismiss="bottom-sheet"></button>
			</div>
			
			<!-- Search Bar (for refinement) -->
			<div class="fi-bottom-sheet-search mt-3" id="fi-bottom-sheet-search-container" style="display: none;">
				<form id="fi-bottom-sheet-search-form" class="position-relative">
					<div class="input-group">
						<span class="input-group-text bg-white border-end-0 ps-3">
							<i class="bi bi-search text-muted"></i>
						</span>
						<input 
							type="search" 
							id="fi-bottom-sheet-search-input" 
							class="form-control border-start-0 ps-0" 
							placeholder="Search legislators or enter ZIP code..."
							autocomplete="off"
						>
						<button class="btn btn-amber" type="submit">Search</button>
					</div>
				</form>
			</div>
		</div>
		
		<!-- Content Area -->
		<div class="fi-bottom-sheet-content" id="fi-bottom-sheet-content">
			<!-- Content injected via JS -->
		</div>
		
		<!-- Footer -->
		<div class="fi-bottom-sheet-footer">
			<button type="button" class="btn btn-secondary w-100" data-bs-dismiss="bottom-sheet">Close</button>
		</div>
	</div>
</div>
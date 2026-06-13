<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
Bottom Sheet Template - Unified container for search results and state/federal selectors
Replaces modal-select (now sheet-select) for consistent UX across home page interactions

Usage:
- From search: data-bs-toggle="bottom-sheet" data-content="search"
- From federal button: data-bs-toggle="bottom-sheet" data-content="federal"
- From state button: data-bs-toggle="bottom-sheet" data-content="state"

Include in footer with: get_template_part('template-parts/bottom-sheet');
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

<style>
/* ============================================
   BOTTOM SHEET STYLES
   ============================================ */

/* Container */
.fi-bottom-sheet {
	position: fixed;
	inset: 0;
	z-index: 1060;
	display: flex;
	flex-direction: column;
	justify-content: flex-end;
	pointer-events: none;
}

.fi-bottom-sheet[hidden] {
	display: none !important;
}

.fi-bottom-sheet.active {
	pointer-events: auto;
}

/* Backdrop */
.fi-bottom-sheet-backdrop {
	position: absolute;
	inset: 0;
	background: rgba(0, 0, 0, 0);
	transition: background 0.3s ease;
}

.fi-bottom-sheet.active .fi-bottom-sheet-backdrop {
	background: rgba(0, 0, 0, 0.5);
}

/* Panel - Mobile (default) */
.fi-bottom-sheet-panel {
	position: relative;
	background: #fff;
	border-radius: 16px 16px 0 0;
	max-height: 85vh;
	max-height: 85dvh;
	width: 100%;
	display: flex;
	flex-direction: column;
	transform: translateY(100%);
	transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
}

.fi-bottom-sheet.active .fi-bottom-sheet-panel {
	transform: translateY(0);
}

/* Drag Handle */
.fi-bottom-sheet-handle {
	padding: 12px 0 8px;
	cursor: grab;
	touch-action: pan-y;
}

.fi-bottom-sheet-handle-bar {
	width: 40px;
	height: 4px;
	background: #ccc;
	border-radius: 2px;
	margin: 0 auto;
}

.fi-bottom-sheet-handle:active {
	cursor: grabbing;
}

/* Header */
.fi-bottom-sheet-header {
	padding: 16px 20px;
	border-bottom: 1px solid #e9ecef;
	flex-shrink: 0;
}

.fi-bottom-sheet-title {
	font-weight: 600;
	color: #002b62;
}

.fi-bottom-sheet-search .input-group-text {
	border-radius: 8px 0 0 8px;
}

.fi-bottom-sheet-search .form-control {
	border-radius: 0;
}

.fi-bottom-sheet-search .btn {
	border-radius: 0 8px 8px 0;
}

/* Content */
.fi-bottom-sheet-content {
	flex: 1;
	overflow-y: auto;
	-webkit-overflow-scrolling: touch;
	padding: 16px 20px;
}

.fi-bottom-sheet-content:empty::before {
	content: '';
	display: block;
	width: 100%;
	height: 100%;
	background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
	background-size: 200% 100%;
	animation: skeleton-loading 1.5s infinite;
	border-radius: 8px;
}

@keyframes skeleton-loading {
	0% { background-position: 200% 0; }
	100% { background-position: -200% 0; }
}

/* Footer */
.fi-bottom-sheet-footer {
	padding: 12px 20px 20px;
	border-top: 1px solid #e9ecef;
	flex-shrink: 0;
}

/* ============================================
   DESKTOP (> 768px) - Side Panel
   ============================================ */
@media (min-width: 768px) {
	.fi-bottom-sheet {
		justify-content: flex-end;
		align-items: flex-end;
	}
	
	.fi-bottom-sheet-panel {
		width: 700px;
		max-width: 90vw;
		max-height: 100vh;
		height: 100vh;
		border-radius: 16px 0 0 16px;
		transform: translateX(100%);
		margin-left: auto;
	}
	
	/* Map SVG responsive sizing */
	.fi-bottom-sheet-content svg {
		max-width: 100%;
		height: auto;
		display: block;
	}
	
	/* Legislator cards - single column on mobile, 2-col on tablet+ */
	.fi-bottom-sheet-content .fi-legislator-card {
		flex-direction: column;
		align-items: flex-start;
	}
	
	@media (min-width: 576px) {
		.fi-bottom-sheet-content .fi-legislator-card {
			flex-direction: row;
			align-items: center;
		}
	}
	
	.fi-bottom-sheet.active .fi-bottom-sheet-panel {
		transform: translateX(0);
	}
	
	.fi-bottom-sheet-handle {
		display: none;
	}
}

/* ============================================
   CONTENT STYLES (Federal/State Selectors)
   ============================================ */

/* Map container in bottom sheet */
.fi-bottom-sheet-content .fi-map-container {
	min-height: 300px;
}

/* List styling */
.fi-bottom-sheet-content .fi-state-list {
	list-style: none;
	padding: 0;
	margin: 0;
	columns: 2;
	column-gap: 12px;
}

@media (min-width: 576px) {
	.fi-bottom-sheet-content .fi-state-list {
		columns: 3;
	}
}

.fi-bottom-sheet-content .fi-state-list li {
	break-inside: avoid;
	margin-bottom: 8px;
}

.fi-bottom-sheet-content .fi-state-list a {
	display: block;
	padding: 8px 12px;
	border-radius: 6px;
	background: #f8f9fa;
	color: #002b62;
	text-decoration: none;
	font-weight: 500;
	transition: background 0.2s, color 0.2s;
}

.fi-bottom-sheet-content .fi-state-list a:hover {
	background: #002b62;
	color: #fff;
}

/* Federal button in bottom sheet */
.fi-bottom-sheet-content .fi-federal-btn {
	display: block;
	width: 100%;
	padding: 16px;
	background: #ffc107;
	color: #000;
	text-decoration: none;
	border-radius: 8px;
	font-weight: 600;
	text-align: center;
	margin-bottom: 16px;
	transition: transform 0.2s, box-shadow 0.2s;
}

.fi-bottom-sheet-content .fi-federal-btn:hover {
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
	color: #000;
}

/* Legislator cards in results */
.fi-bottom-sheet-content .fi-legislator-card {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px;
	border: 1px solid #e9ecef;
	border-radius: 8px;
	margin-bottom: 12px;
	transition: box-shadow 0.2s;
}

.fi-bottom-sheet-content .fi-legislator-card:hover {
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.fi-bottom-sheet-content .fi-legislator-img {
	width: 60px;
	height: 60px;
	border-radius: 50%;
	object-fit: cover;
	flex-shrink: 0;
}

.fi-bottom-sheet-content .fi-legislator-info {
	flex: 1;
	min-width: 0;
}

.fi-bottom-sheet-content .fi-legislator-name {
	font-weight: 600;
	color: #002b62;
	margin-bottom: 2px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.fi-bottom-sheet-content .fi-legislator-meta {
	font-size: 13px;
	color: #666;
}

.fi-bottom-sheet-content .fi-legislator-score {
	font-size: 18px;
	font-weight: 700;
	color: #002b62;
}
</style>
<?php /* All JavaScript moved to public-inline-js.php for unified handling */ ?>
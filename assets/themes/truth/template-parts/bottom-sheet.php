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

<script>
(function() {
	'use strict';
	
	const sheet = document.getElementById('fi-bottom-sheet');
	const backdrop = sheet?.querySelector('.fi-bottom-sheet-backdrop');
	const panel = sheet?.querySelector('.fi-bottom-sheet-panel');
	const handle = sheet?.querySelector('.fi-bottom-sheet-handle');
	const content = document.getElementById('fi-bottom-sheet-content');
	const title = document.getElementById('fi-bottom-sheet-title');
	const searchContainer = document.getElementById('fi-bottom-sheet-search-container');
	const searchInput = document.getElementById('fi-bottom-sheet-search-input');
	const searchForm = document.getElementById('fi-bottom-sheet-search-form');
	
	if (!sheet || !content) return;
	
	let startY = 0;
	let currentY = 0;
	let isDragging = false;
	let touchStartTime = 0;
	
	// Show bottom sheet
	window.fiShowBottomSheet = function(options) {
		options = options || {};
		
		// Set title
		if (options.title) {
			title.textContent = options.title;
		}
		
		// Show/hide search bar
		if (options.showSearch) {
			searchContainer.style.display = 'block';
			if (options.searchValue) {
				searchInput.value = options.searchValue;
			}
		} else {
			searchContainer.style.display = 'none';
		}
		
		// Load content
		if (options.content) {
			content.innerHTML = options.content;
		} else if (options.url) {
			content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
			fetch(options.url)
				.then(r => r.text())
				.then(html => { content.innerHTML = html; })
				.catch(err => { content.innerHTML = '<div class="alert alert-danger">Failed to load content.</div>'; });
		} else {
			content.innerHTML = '';
		}
		
		// Show
		sheet.hidden = false;
		document.body.style.overflow = 'hidden';
		
		// Trigger animation
		requestAnimationFrame(() => {
			sheet.classList.add('active');
		});
		
		// Focus search input if visible
		if (options.showSearch && options.focusSearch) {
			setTimeout(() => searchInput?.focus(), 100);
		}
		
		// Trigger custom event
		sheet.dispatchEvent(new CustomEvent('fi:show', { detail: options }));
	};
	
	// Hide bottom sheet
	window.fiHideBottomSheet = function() {
		sheet.classList.remove('active');
		
		setTimeout(() => {
			sheet.hidden = true;
			content.innerHTML = '';
			document.body.style.overflow = '';
		}, 300);
		
		sheet.dispatchEvent(new CustomEvent('fi:hide'));
	};
	
	// Handle dismiss clicks
	backdrop?.addEventListener('click', fiHideBottomSheet);
	sheet.querySelectorAll('[data-bs-dismiss="bottom-sheet"]').forEach(btn => {
		btn.addEventListener('click', fiHideBottomSheet);
	});
	
	// Handle triggers
	document.addEventListener('click', function(e) {
		const trigger = e.target.closest('[data-bs-toggle="bottom-sheet"]');
		if (!trigger) return;
		
		e.preventDefault();
		const contentType = trigger.dataset.content;
		
		// Set data-content for CSS width rules
		sheet.dataset.content = contentType || 'default';
		
		if (contentType === 'federal') {
			// Load federal selector
			fiShowBottomSheet({
				title: 'Congressional Legislators',
				showSearch: false,
				content: '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>'
			});
			// Load content via AJAX
			fetch(FI.ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({ action: 'fi_load_selector', nonce: FI.nonce, type: 'federal', _t: Date.now() })
			})
			.then(r => r.json())
			.then(data => {
				if (data.success && data.data.html) {
					const contentDiv = document.getElementById('fi-bottom-sheet-content');
					if (contentDiv) {
						contentDiv.innerHTML = data.data.html;
						// Initialize map for federal type after sheet fully opens
						setTimeout(() => fi_init_map_from_content(contentDiv, 'federal'), 50);
					}
				}
			})
			.catch(() => {
				const contentDiv = document.getElementById('fi-bottom-sheet-content');
				if (contentDiv) {
					contentDiv.innerHTML = '<a href="' + FI.homeUrl + 'us/legislators/" class="fi-federal-btn">View All Congressional Legislators</a>';
				}
			});
		} else if (contentType === 'state') {
			// Load state selector
			fiShowBottomSheet({
				title: 'State Legislators',
				showSearch: false,
				content: '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>'
			});
			fetch(FI.ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({ action: 'fi_load_selector', nonce: FI.nonce, type: 'state', _t: Date.now() })
			})
			.then(r => r.json())
			.then(data => {
				if (data.success && data.data.html) {
					const contentDiv = document.getElementById('fi-bottom-sheet-content');
					if (contentDiv) {
						contentDiv.innerHTML = data.data.html;
						// Initialize map for state type after sheet fully opens
						setTimeout(() => fi_init_map_from_content(contentDiv, 'state'), 50);
					}
				}
			})
			.catch(() => {
				const contentDiv = document.getElementById('fi-bottom-sheet-content');
				if (contentDiv) contentDiv.innerHTML = '<div class="alert alert-warning">Failed to load state list. Please try again.</div>';
			});
		}
	});
	
	// Initialize map from loaded content
	function fi_init_map_from_content(container, type) {
		if (!container || typeof jsVectorMap === 'undefined') return;
		
		const mapEl = container.querySelector('#map-' + type);
		if (!mapEl || mapEl.offsetWidth <= 0) return;
		
		// Check if already initialized
		if (mapEl.dataset.initialized === '1') return;
		mapEl.dataset.initialized = '1';
		
		const mapBg = '#F5C87A';
		const mapBgHover = '#E8934A';
		const mapBorder = '#333';
		const mapText = '#333';
		
		const regionStyle = {
			initial: { fill: mapBg, 'fill-opacity': 1, stroke: mapBorder, 'stroke-width': 1 },
			hover:   { fill: mapBgHover },
			selected:{ fill: mapBgHover }
		};
		
		const stateNames = {
			'AL': 'Alabama', 'AK': 'Alaska', 'AZ': 'Arizona', 'AR': 'Arkansas', 'CA': 'California',
			'CO': 'Colorado', 'CT': 'Connecticut', 'DE': 'Delaware', 'FL': 'Florida', 'GA': 'Georgia',
			'HI': 'Hawaii', 'ID': 'Idaho', 'IL': 'Illinois', 'IN': 'Indiana', 'IA': 'Iowa',
			'KS': 'Kansas', 'KY': 'Kentucky', 'LA': 'Louisiana', 'ME': 'Maine', 'MD': 'Maryland',
			'MA': 'Massachusetts', 'MI': 'Michigan', 'MN': 'Minnesota', 'MS': 'Mississippi', 'MO': 'Missouri',
			'MT': 'Montana', 'NE': 'Nebraska', 'NV': 'Nevada', 'NH': 'New Hampshire', 'NJ': 'New Jersey',
			'NM': 'New Mexico', 'NY': 'New York', 'NC': 'North Carolina', 'ND': 'North Dakota', 'OH': 'Ohio',
			'OK': 'Oklahoma', 'OR': 'Oregon', 'PA': 'Pennsylvania', 'RI': 'Rhode Island', 'SC': 'South Carolina',
			'SD': 'South Dakota', 'TN': 'Tennessee', 'TX': 'Texas', 'UT': 'Utah', 'VT': 'Vermont',
			'VA': 'Virginia', 'WA': 'Washington', 'WV': 'West Virginia', 'WI': 'Wisconsin', 'WY': 'Wyoming'
		};
		
		const smallStates = ['CT','DE','MA','MD','NH','NJ','RI','VT'];
		
		const labelOffsets = {
			AK: { x: 0.7, y: 0.35 }, CA: { x: 0.4, y: 0.5 }, FL: { x: 0.75, y: 0.40 },
			HI: { x: 0.85, y: 0.9 }, ID: { x: 0.5, y: 0.72 }, KY: { x: 0.6, y: 0.5 },
			LA: { x: 0.3, y: 0.5 }, MI: { x: 0.7, y: 0.8 }, MN: { x: 0.4, y: 0.5 },
			OK: { x: 0.65, y: 0.5 }, VA: { x: 0.6, y: 0.6 }, WV: { x: 0.4, y: 0.6 }
		};
		
		const isFederal = type === 'federal';
		const stateLinks = {};
		Object.keys(stateNames).forEach(function(abbr) {
			stateLinks['US-' + abbr] = isFederal
				? (FI.homeUrl || '/') + 'us/legislators/state/' + abbr.toLowerCase() + '/'
				: (FI.homeUrl || '/') + abbr.toLowerCase() + '/legislators/';
		});
		
		let mapInstance = null;
		
		function fi_draw_state_labels() {
			if (!mapInstance || !mapEl) return;
			const group = mapEl.querySelector('#jvm-regions-group');
			if (!group) return;
			
			group.querySelectorAll('.fi-map-state-label').forEach(el => el.remove());
			
			Object.keys(stateLinks).forEach(function(code) {
				const abbr = code.replace('US-', '');
				if (smallStates.includes(abbr)) return;
				
				const region = mapInstance.regions && mapInstance.regions[code] ? mapInstance.regions[code] : null;
				const shape = region && region.element && region.element.shape ? region.element.shape : null;
				if (!shape || typeof shape.getBBox !== 'function') return;
				
				let bbox;
				try { bbox = shape.getBBox(); } catch (e) { return; }
				
				const offset = labelOffsets[abbr];
				const cx = offset ? bbox.x + (bbox.width * (offset.x ?? 0.5)) : bbox.x + (bbox.width / 2);
				const cy = offset ? bbox.y + (bbox.height * (offset.y ?? 0.5)) : bbox.y + (bbox.height / 2);
				
				const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
				label.setAttribute('x', cx);
				label.setAttribute('y', cy);
				label.setAttribute('text-anchor', 'middle');
				label.setAttribute('dominant-baseline', 'central');
				label.setAttribute('fill', mapText);
				label.setAttribute('font-size', '20');
				label.setAttribute('font-family', 'inherit, sans-serif');
				label.setAttribute('font-weight', '500');
				label.classList.add('fi-map-state-label');
				label.style.pointerEvents = 'none';
				label.textContent = abbr;
				group.appendChild(label);
			});
		}
		
		function fi_apply_map_aspect() {
			if (!mapEl) return;
			const w = mapEl.offsetWidth;
			if (w <= 0) return;
			mapEl.style.height = Math.round(w * 0.7) + 'px';
			if (mapInstance && typeof mapInstance.updateSize === 'function') {
				mapInstance.updateSize();
			}
			const container = mapEl.closest('.map-vector-container');
			if (container) container.classList.add('fi-map-ready');
		}
		
		mapInstance = new jsVectorMap({
			selector: mapEl,
			map: 'us_aea_en',
			regionStyle: regionStyle,
			zoomButtons: false,
			zoomOnScroll: false,
			zoomOnDoubleClick: false,
			zoomMax: 1,
			onRegionTooltipShow: function(event, tooltip, code) {
				const abbr = (code || '').replace('US-', '');
				tooltip.text(stateNames[abbr] || abbr);
			},
			onRegionClick: function(event, code) {
				if (stateLinks[code]) {
					window.location.href = stateLinks[code];
					event.preventDefault();
				}
			},
			onLoaded: function() {
				fi_apply_map_aspect();
				setTimeout(fi_draw_state_labels, 10);
			}
		});
		
		// Tiny-badge click handlers
		container.querySelectorAll('.fi-us-map-tiny button').forEach(function(btn) {
			btn.addEventListener('click', function() {
				const code = 'US-' + btn.dataset.state;
				if (stateLinks[code]) window.location.href = stateLinks[code];
			});
		});
	}
	
	// Touch/drag handling for mobile swipe-to-dismiss
	if (handle && panel) {
		handle.addEventListener('touchstart', function(e) {
			isDragging = true;
			startY = e.touches[0].clientY;
			currentY = startY;
			touchStartTime = Date.now();
			panel.style.transition = 'none';
		}, { passive: true });
		
		handle.addEventListener('touchmove', function(e) {
			if (!isDragging) return;
			currentY = e.touches[0].clientY;
			const deltaY = currentY - startY;
			if (deltaY > 0) {
				panel.style.transform = `translateY(${deltaY}px)`;
			}
		}, { passive: true });
		
		handle.addEventListener('touchend', function() {
			if (!isDragging) return;
			isDragging = false;
			panel.style.transition = '';
			
			const deltaY = currentY - startY;
			const timeElapsed = Date.now() - touchStartTime;
			const velocity = deltaY / timeElapsed;
			
			// Close if dragged down > 100px or fast swipe
			if (deltaY > 100 || (deltaY > 50 && velocity > 0.5)) {
				fiHideBottomSheet();
			} else {
				panel.style.transform = '';
			}
		}, { passive: true });
	}
	
	// Search form in bottom sheet
	searchForm?.addEventListener('submit', function(e) {
		e.preventDefault();
		const query = searchInput.value.trim();
		if (query.length < 3) return;
		
		// Show loading
		content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
		
		// Perform search
		fetch(FI.ajaxurl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({
				action: 'fi_unified_search',
				nonce: FI.nonce,
				query: query
			})
		})
		.then(r => r.json())
		.then(data => {
			console.log('[fi_search] response:', data);
			if (data.success && data.data.html) {
				content.innerHTML = data.data.html;
				title.textContent = data.data.mode === 'representatives' 
					? 'Your Representatives' 
					: 'Search Results';
			} else {
				console.warn('[fi_search] no html in response. success=' + data.success + ' mode=' + (data.data?.mode));
				content.innerHTML = '<div class="alert alert-warning">No results found.</div>';
			}
		})
		.catch(err => {
			console.error('[fi_search] fetch error:', err);
			content.innerHTML = '<div class="alert alert-danger">Search failed. Please try again.</div>';
		});
	});
	
	// Keyboard: ESC to close
	document.addEventListener('keydown', function(e) {
		if (e.key === 'Escape' && !sheet.hidden) {
			fiHideBottomSheet();
		}
	});
	
	// Expose content loading for search results
	window.fiLoadSearchResults = function(query, html) {
		fiShowBottomSheet({
			title: /^\d/.test(query) ? 'Your Representatives' : 'Search Results',
			showSearch: true,
			searchValue: query,
			focusSearch: false,
			content: html
		});
	};
})();
</script>
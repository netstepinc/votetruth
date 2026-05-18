<?php if(!defined('ABSPATH')) exit;
/*
US Vector Map
Interactive vector map of the United States with state-level details.
CT,DE,MA,MD,NH,NJ,RI,VT
*/
$map_bg = '#F5C87A';
$map_bg_hover = '#E8934A';
$map_bg_active = '#D6813D';
$map_text_hover = '#F8F5F0';
$map_text = '#333';
$map_border = '#333';

//Switch between federal and state links based on template args.
if(isset($args['type']) && $args['type'] === 'federal') {
	$type = 'federal';
	echo '<p class="text-center">Select a state to view U.S. Congressional legislators representing that state.</p>';
} else {
	$type = 'state';
	echo '<p class="text-center">Select a state to view state legislators.</p>';
}

$states = ['AL'=>'Alabama','AK'=>'Alaska','AZ'=>'Arizona','AR'=>'Arkansas','CA'=>'California','CO'=>'Colorado','CT'=>'Connecticut','DE'=>'Delaware','FL'=>'Florida','GA'=>'Georgia','HI'=>'Hawaii','ID'=>'Idaho','IL'=>'Illinois','IN'=>'Indiana','IA'=>'Iowa','KS'=>'Kansas','KY'=>'Kentucky','LA'=>'Louisiana','ME'=>'Maine','MD'=>'Maryland','MA'=>'Massachusetts','MI'=>'Michigan','MN'=>'Minnesota','MS'=>'Mississippi','MO'=>'Missouri','MT'=>'Montana','NE'=>'Nebraska','NV'=>'Nevada','NH'=>'New Hampshire','NJ'=>'New Jersey','NM'=>'New Mexico','NY'=>'New York','NC'=>'North Carolina','ND'=>'North Dakota','OH'=>'Ohio','OK'=>'Oklahoma','OR'=>'Oregon','PA'=>'Pennsylvania','RI'=>'Rhode Island','SC'=>'South Carolina','SD'=>'South Dakota','TN'=>'Tennessee','TX'=>'Texas','UT'=>'Utah','VA'=>'Virginia','VT'=>'Vermont','WA'=>'Washington','WV'=>'West Virginia','WI'=>'Wisconsin','WY'=>'Wyoming'];

//Load the assets only once
if($type == 'federal'):
?>
<style>
svg{-ms-touch-action:none; touch-action:none;}

/* Leave 40px of space on the right for the tiny buttons */
.map-vector-container {
	position: relative;
	width: 100%;
	max-width: 750px;
	margin: auto 0;
	padding-right: 40px; /* <--- Reserve space for badges */
}
#map {
	width: 100%;
	aspect-ratio: 1/1;
	height: auto;
	min-height: 300px;
}

/* Tiny state badges */
.fi-us-map-tiny {
	display: flex;
	flex-direction: column;
	position: absolute;
	right: 0;
	top: 6vh;
	gap: 4px;
	z-index: 2;
	visibility: hidden;
}
.map-vector-container.fi-map-ready .fi-us-map-tiny {
	visibility: visible;
}
.fi-us-map-tiny button {
	background: <?= $map_bg?>;
	font-weight: 500;
	font-size: 14px;
	border-radius: 8px;
	border: 1px solid <?= $map_border ?>;
	color: <?=$map_text?>;
	padding: 2px 4px;
	cursor: pointer;
	transition: background 0.2s;
	box-shadow: 0 1px 4px rgba(30,40,80,0.07);
}
.fi-us-map-tiny button:hover {background: <?= $map_bg_hover?>; color: <?= $map_text_hover?>;}

/* jsVectorMap tooltip (this is the missing piece for hover) */
.jvm-tooltip{
	position:absolute;
	display:none;
	padding:6px 8px;
	border-radius:10px;
	background:#111827;
	color:<?=$map_text?>;
	font-size:13px;
	line-height:1.2;
	white-space:nowrap;
	box-shadow:0 8px 24px rgba(0,0,0,.2);
	z-index: 10;
}
.jvm-tooltip.active{display:block;}
/* State abbreviation labels (centered in each state); white so visible on red fill */
.fi-map-state-label { fill: <?=$map_text?> !important; }

@media (min-width: 1400px) {}
@media (min-width: 1200px) and (max-width: 1399.98px) {
	.fi-us-map-tiny { top: 4vh; }
}
@media (min-width: 992px) and (max-width: 1199.98px) {
	.fi-us-map-tiny { top: 0vh; }
}
@media (min-width: 768px) and (max-width: 991.98px) { /* Bootstrap MD */
	.fi-us-map-tiny { top: 0vh; }
}
@media (min-width: 576px) and (max-width: 767.98px) { /* Bootstrap SM */
	.fi-us-map-tiny { top: 0vh; gap: 5px; }
}
@media (max-width: 575.98px) { /* Bootstrap XS */
	.fi-us-map-tiny { top: 0; gap: 4px; }
	.fi-us-map-tiny button{font-size: 10px;}
	.map-vector-container {padding-right: 30px;}
}
</style>
<script src="<?= STYLE_JS; ?>jsvectormap.min.js"></script>
<script src="<?= STYLE_JS; ?>jsvectormap-us-en.js"></script>
<?php endif; ?>
<div class="map-vector-container">
	<div id="map-<?= $type; ?>"></div>
	<!-- Tiny states floating badges: -->
	<div class="fi-us-map-tiny">
	<?php foreach(['CT','DE','MA','MD','NH','NJ','RI','VT'] as $tiny): ?>
		<button type="button" data-state="<?= esc_attr($tiny) ?>" title="<?= esc_attr($states[$tiny]) ?>"><?= esc_html($tiny) ?></button>
	<?php endforeach; ?>
	</div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
	// State -> URL (map uses US-XX region codes for this map)
	const stateLinks = {
<?php
foreach($states as $state => $name) {
	if($type == 'federal'){
		echo "'US-" . $state . "': '" . home_url() . "/us/legislators/state/" . strtolower($state) . "/',\n";
	}else{
		echo "'US-" . $state . "': '" . home_url() . "/" . strtolower($state) . "/legislators/',\n";
	}
}
?>
	};

	// State abbrev -> full name (used for tooltips + future UI)
	const stateNames = {
<?php
foreach($states as $abbr => $name) {
	echo "'" . $abbr . "': " . json_encode($name) . ",\n";
}
?>
	};

	// Tiny states that get external buttons instead of on-map labels
	const smallStates = ['CT','DE','MA','MD','NH','NJ','RI','VT'];

	// Per-state label position as fraction of bbox (0–1). Default center is (0.5, 0.5). Use to fix odd-shaped states.
	const labelOffsets = {
		AK: { x: 0.7,  y: 0.35 },  // Alaska: southern mainland
		CA: { x: 0.4, y: 0.5 },
		FL: { x: 0.75, y: 0.40 },
		HI: { x: 0.85,  y: 0.9 },
		ID: { x: 0.5,  y: 0.72 },
		KY: { x: 0.6,  y: 0.5 },
		LA: { x: 0.3,  y: 0.5 },
		MI: { x: 0.7,  y: 0.8 },
		MN: { x: 0.4,  y: 0.5 },
		OK: { x: 0.65,  y: 0.5 },
		VA: { x: 0.6,  y: 0.6 },
		WV: { x: 0.4,  y: 0.6 },

	};

	const regionStyle = {
		initial: { fill: '<?= $map_bg?>', 'fill-opacity': 1, stroke: '<?= $map_border?>', 'stroke-width': 1 },
		hover:   { fill: '<?= $map_bg_hover?>' },
		selected:{ fill: '<?= $map_bg_hover?>' }
	};

	const element = document.querySelector("#map-<?= $type ?>");
	let mapInstance = null;

	/** Force map container height to 70% of width so map is shorter than square; call updateSize so library redraws. */
	function fi_apply_map_aspect() {
		if (!element) return;
		var w = element.offsetWidth;
		if (w <= 0) return;
		var h = Math.round(w * 0.7);
		element.style.aspectRatio = "auto";
		element.style.minHeight = "0";
		element.style.height = h + "px";
		if (mapInstance && typeof mapInstance.updateSize === "function") {
			mapInstance.updateSize();
		}
		var mapContainer = element.closest('.map-vector-container');
		if (mapContainer) {
			mapContainer.classList.add('fi-map-ready');
		}
	}

	/**
	 * Draw (or redraw) state abbreviation labels centered in each region.
	 * - Uses getBBox() which is stable for centered labels.
	 * - Rebuilds on load + resize to keep positions correct for responsive scaling.
	 */
	function fi_draw_state_labels() {
		if (!mapInstance || !element) return;
		// Append to the transformed group so labels use same coordinate system as paths (scale/translate)
		const group = element.querySelector("#jvm-regions-group");
		if (!group) return;

		group.querySelectorAll('.fi-map-state-label').forEach(el => el.remove());

		Object.keys(stateLinks).forEach(function(code) {
			const abbr = code.replace('US-','');
			if (smallStates.includes(abbr)) return;

			const region = mapInstance.regions && mapInstance.regions[code] ? mapInstance.regions[code] : null;
			const shape = region && region.element && region.element.shape ? region.element.shape : null;
			if (!shape || typeof shape.getBBox !== "function") return;

			let bbox;
			try {
				bbox = shape.getBBox();
			} catch (e) {
				return;
			}

			const offset = labelOffsets[abbr];
			const cx = offset
				? bbox.x + (bbox.width  * (offset.x ?? 0.5))
				: bbox.x + (bbox.width / 2);
			const cy = offset
				? bbox.y + (bbox.height * (offset.y ?? 0.5))
				: bbox.y + (bbox.height / 2);

			const label = document.createElementNS("http://www.w3.org/2000/svg", "text");
			label.setAttribute("x", cx);
			label.setAttribute("y", cy);
			label.setAttribute("text-anchor", "middle");
			label.setAttribute("dominant-baseline", "central");
			label.setAttribute("fill", "<?=$map_text?>");
			label.setAttribute("font-size", "20");
			label.setAttribute("font-family", "inherit, sans-serif");
			label.setAttribute("font-weight", "500");
			label.classList.add("fi-map-state-label");

			label.style.pointerEvents = "none";

			label.textContent = abbr;

			group.appendChild(label);
		});
	}

	function fi_init_map() {
		if (!element || mapInstance || element.offsetWidth <= 0) return;
		mapInstance = new jsVectorMap({
			selector: element,
			map: "us_aea_en",
			regionStyle: regionStyle,

			zoomButtons: false,
			zoomOnScroll: false,
			zoomOnDoubleClick: false,
			zoomMax: 1,

			// 1) Enable (and customize) state name on hover:
			onRegionTooltipShow: function (event, tooltip, code) {
				// code is like "US-TX"
				const abbr = (code || '').replace('US-','');
				const full = stateNames[abbr] || abbr;
				tooltip.text(full); // full state name on hover
			},

			onLoaded: function () {
				fi_apply_map_aspect();
				fi_draw_state_labels();
				if (element.offsetWidth > 0) {
					requestAnimationFrame(function () {
						requestAnimationFrame(function () {
							fi_apply_map_aspect();
							fi_draw_state_labels();
						});
					});
				}
				if (typeof ResizeObserver !== "undefined" && element && !element.closest('.modal')) {
					var roScheduled = false;
					var ro = new ResizeObserver(function () {
						if (roScheduled) return;
						roScheduled = true;
						requestAnimationFrame(function () {
							roScheduled = false;
							fi_apply_map_aspect();
							fi_draw_state_labels();
						});
					});
					ro.observe(element);
				}
			},

			onRegionClick: function (event, code) {
				if (stateLinks[code]) {
					window.open(stateLinks[code], '_blank');
					event.preventDefault();
				}
			}
		});

	}

	if (element) {
		var fi_modal = element.closest('.modal');
		if (fi_modal) {
			fi_modal.addEventListener('shown.bs.modal', function () {
				setTimeout(function () {
					fi_init_map();
					fi_apply_map_aspect();
					fi_draw_state_labels();
				}, 50);
			});
		} else {
			fi_init_map();
		}

		// On window resize: reapply 70% aspect and redraw labels
		var fi_resize_timer = null;
		window.addEventListener("resize", function () {
			clearTimeout(fi_resize_timer);
			fi_resize_timer = setTimeout(function () {
				fi_apply_map_aspect();
				fi_draw_state_labels();
			}, 120);
		});
	}

	// Tiny-badge clicks (CT, DE, etc.)
	const mapContainer = element ? element.closest('.map-vector-container') : null;
	(mapContainer ? mapContainer : document).querySelectorAll('.fi-us-map-tiny button').forEach(function(btn) {
		btn.addEventListener('click', function() {
			const code = 'US-' + btn.dataset.state;
			if (mapInstance) {
				mapInstance.setSelectedRegions([code]);
			}
			if (stateLinks[code]) {
				window.open(stateLinks[code], '_blank');
			}
		});
	});

});
</script>
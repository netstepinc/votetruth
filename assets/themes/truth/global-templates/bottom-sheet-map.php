<?php if(!defined('ABSPATH')) exit;
/*
US Vector Map
Interactive vector map of the United States with state-level details.
CT,DE,MA,MD,NH,NJ,RI,VT
*/

//Switch between federal and state links based on template args.
if(isset($args['type']) && $args['type'] === 'federal') {
	$type = 'federal';
	echo '<a href="' . esc_url(home_url('/us/legislators/')) . '" class="btn btn-sm btn-amber fs-7 fw-bold w-100 rounded-3 mb-4 shadow">View All Congressional Legislators</a>';
	echo '<p class="text-center">Select a state to view U.S. Congressional legislators representing that state.</p>';
} else {
	$type = 'state';
	echo '<p class="text-center">Select a state to view state legislators.</p>';
}
$states = ['AL'=>'Alabama','AK'=>'Alaska','AZ'=>'Arizona','AR'=>'Arkansas','CA'=>'California','CO'=>'Colorado','CT'=>'Connecticut','DE'=>'Delaware','FL'=>'Florida','GA'=>'Georgia','HI'=>'Hawaii','ID'=>'Idaho','IL'=>'Illinois','IN'=>'Indiana','IA'=>'Iowa','KS'=>'Kansas','KY'=>'Kentucky','LA'=>'Louisiana','ME'=>'Maine','MD'=>'Maryland','MA'=>'Massachusetts','MI'=>'Michigan','MN'=>'Minnesota','MS'=>'Mississippi','MO'=>'Missouri','MT'=>'Montana','NE'=>'Nebraska','NV'=>'Nevada','NH'=>'New Hampshire','NJ'=>'New Jersey','NM'=>'New Mexico','NY'=>'New York','NC'=>'North Carolina','ND'=>'North Dakota','OH'=>'Ohio','OK'=>'Oklahoma','OR'=>'Oregon','PA'=>'Pennsylvania','RI'=>'Rhode Island','SC'=>'South Carolina','SD'=>'South Dakota','TN'=>'Tennessee','TX'=>'Texas','UT'=>'Utah','VA'=>'Virginia','VT'=>'Vermont','WA'=>'Washington','WV'=>'West Virginia','WI'=>'Wisconsin','WY'=>'Wyoming'];
?>
<div id="select-state-list" class="d-md-none">
<ul class="list-group list-group-flush">
<?php 
foreach($states as $abr => $name){
	if($type == 'federal') {
		$li_url = esc_url(home_url('/us/legislators/state/' . strtolower($abr)));
	} else {
		$li_url = esc_url(home_url('/' . strtolower($abr) . '/legislators/'));
	}
	echo '<li class="list-group-item"><a href="' . $li_url . '" class="text-decoration-none fs-3 lh-1">' . $name . '</a></li>';
} ?>
</ul>
</div>

<div class="map-vector-container d-none d-md-block">
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
foreach($states as $abr => $name) {
    if($type == 'federal') {
        $url = esc_url(home_url('/us/legislators/state/' . strtolower($abr)));
    } else {
        $url = esc_url(home_url('/' . strtolower($abr) . '/legislators/'));
    }
    echo "'US-" . $abr . "': '" . $url . "',\n";
}
?>
	};

	// State abbrev -> full name (used for tooltips + future UI)
	const stateNames = {
<?php
foreach($states as $abr => $name) {
    echo "'" . $abr . "': \"" . $name . "\",\n";
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
		initial: { fill: '#c41425', 'fill-opacity': 1, stroke: '#fff', 'stroke-width': 1 },
		hover:   { fill: '#cccccc' },
		selected:{ fill: '#228B22' }
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
			label.setAttribute("fill", "#fff");
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
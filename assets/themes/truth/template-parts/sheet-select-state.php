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

	echo '<a href="' . esc_url(home_url('/us/legislators/')) . '" class="btn btn-sm btn-amber fs-7 fw-bold w-100 rounded-3 mb-4 shadow">View All Congressional Legislators</a>';
	echo '<p class="text-center">Select a state to view U.S. Congressional legislators representing that state.</p>';
} else {
	$type = 'state';
	echo '<p class="text-center">Select a state to view state legislators.</p>';
}

$states = ['AL'=>'Alabama','AK'=>'Alaska','AZ'=>'Arizona','AR'=>'Arkansas','CA'=>'California','CO'=>'Colorado','CT'=>'Connecticut','DE'=>'Delaware','FL'=>'Florida','GA'=>'Georgia','HI'=>'Hawaii','ID'=>'Idaho','IL'=>'Illinois','IN'=>'Indiana','IA'=>'Iowa','KS'=>'Kansas','KY'=>'Kentucky','LA'=>'Louisiana','ME'=>'Maine','MD'=>'Maryland','MA'=>'Massachusetts','MI'=>'Michigan','MN'=>'Minnesota','MS'=>'Mississippi','MO'=>'Missouri','MT'=>'Montana','NE'=>'Nebraska','NV'=>'Nevada','NH'=>'New Hampshire','NJ'=>'New Jersey','NM'=>'New Mexico','NY'=>'New York','NC'=>'North Carolina','ND'=>'North Dakota','OH'=>'Ohio','OK'=>'Oklahoma','OR'=>'Oregon','PA'=>'Pennsylvania','RI'=>'Rhode Island','SC'=>'South Carolina','SD'=>'South Dakota','TN'=>'Tennessee','TX'=>'Texas','UT'=>'Utah','VA'=>'Virginia','VT'=>'Vermont','WA'=>'Washington','WV'=>'West Virginia','WI'=>'Wisconsin','WY'=>'Wyoming'];

//Load the assets only once
?>
<style>
svg{-ms-touch-action:none; touch-action:none;}

/* Leave 40px of space on the right for the tiny buttons */
.map-vector-container {
	position: relative;
	width: 100%;
	max-width: 100%;
	margin: 0;
	padding-right: 40px; /* <--- Reserve space for badges */
}
[id^="map-"] {
	width: 100%;
	min-height: 434px;
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
<div id="select-state-list" class="d-md-none">
<ul class="list-group list-group-flush">
<?php foreach($states as $abr => $name){
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
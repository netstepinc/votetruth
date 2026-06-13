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
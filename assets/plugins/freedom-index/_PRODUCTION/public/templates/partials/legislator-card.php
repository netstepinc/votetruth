<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Legislator Card Partial Template
 * Displays a single legislator card
 * 
 * @var object $legislator Legislator object
 * @var string $gov Government code (e.g., 'US', 'TX', 'WI')
 */
//echo "<!-- LEGISLATOR: ".$legislator->id."\n";print_r($legislator);echo "\n -->\n";

//Name
$name = isset($legislator->display_name) && $legislator->display_name ? stripslashes($legislator->display_name) : stripslashes($legislator->first_name . ' ' . $legislator->last_name);

// Get legislator image
//Lazy loading failed. Needs to be fixed. loading="lazy" decoding="async" we need to handle this in the image generation function
if(isset($legislator->lazy_load) && $legislator->lazy_load == true){
	$img_lazy = ' loading="lazy"';
}else{
	$img_lazy = '';
}

if(isset($legislator->image_url) && $legislator->image_url !== ''){
	$image_html = '<img src="'.$legislator->image_url.'" alt="'.$name.'" width="200" height="250" class="img-fluid w-100 h-100 img-saved legislator-card-image">';
}else{
	$image_html = fi_legislator_image($legislator->image_id, $legislator->session_image_id,
		[
		'size' => [200, 250],
		'crop' => true,
		'alt' => $name,
		'class' => 'img-fluid w-100 h-100 legislator-card-image',
		]);
}

// Get score and class
$score = $legislator->score ?? null;
$score_class = $score !== null ? fi_score_class( $score ) : 'bg-light text-dark';

//Legislator Chamber
$chamber_title = isset($legislator->chamber) && $legislator->chamber ? fi_chamber_title( $gov, $legislator->chamber ) : '';

// Get party abbreviation and class
$party_abbr = $legislator->party ?? '';
$party = strtolower($party_abbr); // For display/class matching
$party_class = ''; //JDA fi_party_bg_class($party_abbr);

// Get state
$state = isset($legislator->state) && $legislator->state && $gov == 'US' ? $legislator->state : '';

// Get district
$district = isset($legislator->district_name) && $legislator->district_name ? $legislator->district_name : '';
if($district){
	$district = $district == 'At Large' ? '@LG' : $district;
}

echo "<!-- LEGISLATOR: ".$legislator->id."\n";print_r($legislator);echo "\n -->\n";
?>
<div class="col-12 fi-legislator-card mb-4">
	<a id="fi-legislator-card-<?= $legislator->id; ?>" href="<?php echo esc_url( $legislator->url ); ?>" class="text-decoration-none d-block h-100<?= $lazy_load; ?>" style="color: inherit;">
		<div class="card h-100 shadow-sm rounded-4">
			<?php if ( $image_html ) : ?>
			<div class="card-img-top rounded-top-4" style="aspect-ratio: 4 / 5; overflow: hidden; background: #f8f9fa;">
				<?php echo $image_html;?>
			</div>
			<?php endif; ?>
			<div class="card-body d-flex flex-column p-2 text-center">
			<?php if ($chamber_title) : ?>
				<h5 class="mb-0 text-muted"><?= $chamber_title; ?></h5>
			<?php endif; ?>
				<h3 class="mb-0"><?= esc_html($name); ?></h3>
			</div>
			<?php if ( $score !== null ) : ?>
			<div class="mt-auto mb-1">
				<?php
				// '<div class="d-flex justify-content-center align-items-center w-100">'.fi_score_donut($score, 'Session Score', null, null, 120).'</div>';
				//I like the donut graphic, but it takes up too much space.
				echo '<div class="fw-bold fs-1 lh-1 text-center">' . $score . '</div>';
				echo '<div class="text-muted text-center">Freedom Score</div>';
				?>
			</div>
			<?php endif; ?>


			<div class="card-footer p-0 bg-transparent">
				<div class="row g-0">
				<?php
				//Determine how many columns to display for the row below.
				$col_party = '<div class="text-center p-0 '. esc_attr($party_class).'"><div class="fw-bold fs-5">'. esc_html(fi_party_abbr($party_abbr)).'</div><small>Party</small></div>';
				$col_state = '<div class="text-center p-0 bg-light text-dark"><div class="fw-bold fs-5">'. esc_html($state).'</div><small>State</small></div>';
				if($district){
					$col_district = '<div class="text-center p-0 bg-light text-dark"><div class="fw-bold fs-5">'. $district.'</div><small>Dist<span class="d-none d-md-inline">rict</span><span class="d-inline d-md-none">.</span></small></div>';
				}else{
					$col_district = '';
				}

				if($gov == 'US'){
					echo '<div class="col-4">'.$col_party.'</div>';
					echo '<div class="col-4">'.$col_state.'</div>';
					echo '<div class="col-4">'.$col_district.'</div>';
				}else{
					echo '<div class="col-4">'.$col_party.'</div>';
					echo '<div class="col-8">'.$col_district.'</div>';
				}
				?>
				</div>
				<div class="btn btn-sm btn-dark text-white w-100 rounded-bottom-4 rounded-top-0">
					View Report
				</div>
			</div>
		</div>
	</a>
</div>
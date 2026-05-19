<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

define('FI_GRADES',[
    'A' => ['min' => 90, 'max' => 100, 'label' => 'Constitutional Champion'],
    'B' => ['min' => 80, 'max' => 89, 'label' => 'Mostly Reliable'],
    'C' => ['min' => 70, 'max' => 79, 'label' => 'Mixed Record'],
    'D' => ['min' => 60, 'max' => 69, 'label' => 'Votes Against Freedom'],
    'F' => ['min' => 0, 'max' => 59, 'label' => 'Failing the Constitution'],
]);

function fi_grade($args){
	$type = $args['type'] ?? 'pill'; //pill, text
	$grade = $args['grade'] ?? '';
	$score = $args['score'] ?? '';
	$size = $args['size'] ?? '36px';
	$font_size = $args['fs'] ?? '16px';

	//Determine if we received a grade or a score and assing grade for score
	if(!empty($score) && is_numeric($score)){
		foreach(FI_GRADES as $g => $data){
			if($score >= $data['min'] && $score <= $data['max']){
				$grade = $g;
				break;
			}
		}
	}
	$str = '<div class="fi-grade-'.$type.'" ';
	$str .= 'style="background:var(--fi-g-'.strtolower($grade).'); width:'.$size.'; height:'.$size.'; font-size:'.$font_size.';">';
	$str .= $grade.'</div>';

	return $str;
}

?>
<!--
INLINE STYLE FOR DEVELOPMENT: Consolidate when perfected.
-->
<style>
.fi-grade-pill {
	/*width: 2.25rem; height: 2.25rem;*/
	border-radius: 0.5rem;
	display: flex; align-items: center; justify-content: center;
	/*font-size: 0.9375rem; */
	font-weight: 800;
	flex-shrink: 0; color: #fff;
}
.fi-scale-range {
	font-size: 0.875rem; font-weight: 600;
	color: var(--bs-gray-800); min-width: 60px;
}
.fi-scale-desc {
	font-size: 0.875rem; color: var(--bs-gray-600); flex: 1;
}
#home-score-scale{
	max-width:340px;
}
</style>
<div id="home-score-scale" class="card rounded-4 mx-auto">
	<div class="card-header rounded-top-4 bg-anchor">
		<div class="fs-7 fw-bold text-uppercase text-fade">Freedom Score</div>
	</div>
	<div class="card-body">
		<ul class="list-unstyled list-flush">
		<?php
		foreach(FI_GRADES as $grade => $data) {
			echo '<li class="d-flex align-items-center gap-3 pb-2">
				'.fi_grade(['grade' => $grade, 'size' => '36px', 'fs' => '16px']).'
				<div class="fi-scale-range">'.$data['min'].'-'.$data['max'].'</div>
				<div class="fi-scale-desc">'.$data['label'].'</div>
			</li>';
		}
		?>
		</ul>
	</div>
</div>
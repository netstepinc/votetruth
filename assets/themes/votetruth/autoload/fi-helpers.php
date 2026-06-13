<?php if(!defined('ABSPATH')) exit;

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
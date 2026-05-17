<?php if ( ! defined( 'ABSPATH' ) ) {exit;}

function sam_term_config(){
	//Get the current alert area
	$term = get_queried_object();

	//Initialize the config array
	$config = [
		'title' => $term->name,
		'description' => $term->description,
		'header' => [
			'alt' => $term->name,
			'src' => '',
		],
	];

	return $config;
}
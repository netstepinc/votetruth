<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Taxonomy Autoload
 * Helper functions for taxonomy used by other modules.
 */


function fi_api_districts_get_names($gov) : array {
	global $fidb;
	$districts = $fidb->select(TB_TAXONOMY, ['id', 'name'], [
		'taxonomy' => 'district',
		'gov' => $gov
	]);
	$district_names = [];
	foreach($districts as $d){
		$district_names[$d['id']] = $d['name'];
	}
	return $district_names;
}


function fi_api_taxonomy_get($type,$id) : array {
	global $fidb;
	$taxonomy = $fidb->get(TB_TAXONOMY, ['id','gov','name'], [
		'taxonomy' => $type,
		'id' => $id
	]);
	return $taxonomy;
}
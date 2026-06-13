<?php
/*
Freedom Index Product list by Sam Mittelstaedt <smittelstaedt@jbs.org>
Direct query of ShopJBS tables: jbsw_3_posts, jbsw_3_postmeta, jbsw_3_term_relationships
*/
define('FI_SHOP_TAG',4364); //freedom-index

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<div class="row">
<?php
global $wpdb;
$tag_query = $wpdb->get_results( "SELECT `object_id` FROM `jbsw_3_term_relationships` WHERE `term_taxonomy_id` ='".FI_SHOP_TAG."' order by object_id desc", ARRAY_A );
//print_r($tag_query);exit;
/*
Array ( [0] => Array ( [object_id] => 2259713 ) [1] => Array ( [object_id] => 1741816 ) [2] => Array ( [object_id] => 1734328 ) [3] => Array ( [object_id] => 1444938 ) [4] => Array ( [object_id] => 1428961 ) [5] => Array ( [object_id] => 1423275 ) [6] => Array ( [object_id] => 1118415 ) [7] => Array ( [object_id] => 119514 ) [8] => Array ( [object_id] => 75037 ) [9] => Array ( [object_id] => 74771 ) [10] => Array ( [object_id] => 74741 ) [11] => Array ( [object_id] => 71233 ) [12] => Array ( [object_id] => 64190 ) [13] => Array ( [object_id] => 52621 ) [14] => Array ( [object_id] => 46616 ) [15] => Array ( [object_id] => 46571 ) [16] => Array ( [object_id] => 40829 ) [17] => Array ( [object_id] => 25075 ) [18] => Array ( [object_id] => 3765 ) [19] => Array ( [object_id] => 3745 ) [20] => Array ( [object_id] => 3730 ) [21] => Array ( [object_id] => 3685 ) [22] => Array ( [object_id] => 2568 ) )
*/

foreach($tag_query as $product):
	$pID = $product['object_id'];
	if($pID > 0):
		$product = $wpdb->get_results( "SELECT post_title,post_name FROM jbsw_3_posts WHERE `post_type` = 'product' and `post_status`='publish' and ID='".$pID."'", OBJECT );
		if(count($product) > 0):
			$product_meta = $wpdb->get_results( "SELECT meta_value FROM jbsw_3_postmeta WHERE post_id ='".$pID."' AND meta_key = '_thumbnail_id'", OBJECT );
			$thumbnail_id = $product_meta[0]->meta_value;
			$thumbnail = $wpdb->get_results( "SELECT `guid` FROM `jbsw_3_posts` WHERE ID='".$thumbnail_id."'", OBJECT );
?>
<div class="col-6 col-md-4 col-lg-3 mb-4">
	<div class="card h-100 shadow">
		<div class="card-image pt-4">
			<a href="https://shopjbs.org/product/<?php echo $product[0]->post_name;?>" target="_blank">
			<img src="<?php echo $thumbnail[0]->guid;?>" class="img-fluid" alt="<?php echo $product[0]->post_title;?>">
			</a>
		</div>
		<div class="small text-center h-100">
			<a href="https://shopjbs.org/product/<?php echo $product[0]->post_name;?>" target="_blank">
			<?php echo $product[0]->post_title;?>
			</a>
		</div>
		<div class="card-footer text-center p-0">
			<a href="https://shopjbs.org/product/<?php echo $product[0]->post_name;?>" target="_blank" class="btn btn-success rounded-0 font-weight-bold w-100">Select Options</a>
		</div>
	</div>
	</a>
</div>
<?php
		endif;
    endif;
endforeach;
?>
</div>
<?php
/**
 * The right sidebar containing the main widget area.
 *
 * @package understrap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<aside class="col-md-4 col-lg-3 widget-area right-sidebar-lg d-print-none" id="right-sidebar">
    <div class="sticky">
<?php
if (function_exists('is_woocommerce') && ( is_woocommerce() || is_shop() || is_product_category() || is_product() || is_page('shop') )):
	dynamic_sidebar( 'sidebar-shop' );
	?>
		<aside>
			<div class="block-title-2">
				<h4 class="h5"><span>Subscribe</span></h4>
			</div>
			<a href="<?php echo site_url('/subscribe/');?>"><img src="<?php site_url();?>/assets/sites/3/tna-subscription.jpg" alt="Subscribe to the New American" class="img-fluid" /></a>
	    </aside>
<?php
else:
	//Disable dynamic widgets: dynamic_sidebar( 'right-sidebar' );
	get_template_part('global-templates/sidebar','getheadlines');
	//Do not show on breaking news.
	//$cat = get_queried_object();
	//if(is_object($cat) && property_exists($cat,'slug') && $cat->slug != 'news'){
	//	get_template_part('global-templates/sidebar','breakingnews');
	//}
	get_template_part('placement/sidebar','',['adgroup' => 'side_banner_3']);
	if(is_singular('insider')){
		$toc_args = array(
			'class' => 'card my-4 border-danger',
			'header_class' => 'card-header bg-danger text-white py-2',
			'title' => 'Insider Report Contents',
			'max_height' => 0,
			'links' => 'relative'
		);
		echo jbs_get_toc(get_the_ID(),$toc_args);
	}else{
		get_template_part('global-templates/sidebar','latest');
	}
	get_template_part('placement/sidebar','',['adgroup' => 'side_banner_4']);
	dynamic_sidebar( 'sidebar-right' );
	get_template_part('placement/sidebar','',['adgroup' => 'side_banner_5']);
	get_template_part('placement/sidebar','',['adgroup' => 'side_banner_6']);
	get_template_part('placement/sidebar','',['adgroup' => 'side_banner_7']);
	get_template_part('placement/sidebar','',['adgroup' => 'side_banner_8']);
	get_template_part('placement/sidebar','',['adgroup' => 'side_banner_9']);
endif; 
?>
    </div>
</aside>
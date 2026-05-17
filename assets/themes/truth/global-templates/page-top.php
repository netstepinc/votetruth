<?php if(!defined('ABSPATH')){exit;}
/* Handle if args are included or not
Args provided by:
	cpt_alert
	ctax_alertarea
Fetch args for: 
	post
	page
	term
*/
if(empty($args)){
	$args = jbs_page_header_args();
}
$page_title_class = 'page-title';
if(isset($args['title']) && strlen($args['title']) > 100){
	$page_title_class = ' title-long';
}
//echo "\n<!-- PAGE TOP ARGS: ";print_r($args,true); echo " -->\n";
?>
<main id="content" class="bg-light">
	<?php //get_template_part('global-templates/navbar-submenu');?>
	<?php //jbs_page_header($args);?>
	<div class="container-xl bg-wrapper">
			<div class="row">
				<div class="col-12 py-2 pb-lg-3 ps-2">
					<?php sam_breadcrumb();?>
				</div>
			</div>
			<?php if( (isset($args['title']) && !empty($args['title'])) || (isset($args['show_title']) && $args['show_title'] == true) ): ?>
			<div class="row">
				<div class="col-12 ps-2">
					<h1 class="<?php echo $page_title_class; ?>"><?php echo $args['title']; ?></h1>
				</div>
			</div>
			<?php endif; ?>
	</div>
	<div class="container-xl p-0">
			<div class="row g-0">
				<div class="col-12 p-2">
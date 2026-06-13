<?php
/**
* Template Name: FI Full Width
* Template Post Type: page
* Freedom Index Full Width Template
*/
if ( ! defined( 'ABSPATH' ) ) {exit;}
get_header();


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
	<div class="container-fluid p-0">
			<div class="row g-0">
				<div class="col-12 p-2">
<?php
while ( have_posts() ) : the_post();
?>
<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
	<div class="entry-content post-content post-page">
		<?php the_content(); ?>
	</div>
</article>
<?php 
endwhile; // end of the loop.
get_template_part('global-templates/page','bottom');
get_footer();
<?php if ( ! defined( 'ABSPATH' ) ) {exit;}
get_header();

$termConf = fi_term_config();

get_template_part('global-templates/page','top',$termConf);
?>
<?php the_archive_description( '<div class="taxonomy-description">', '</div>' );?>
<?php 
if ( have_posts() ) :
	while ( have_posts() ) : the_post();
        get_template_part( 'loop-templates/content', 'text', get_post_format() );
		//get_template_part( 'loop-templates/content', 'smalls', get_post_format() );
		//get_template_part( 'loop-templates/content', 'box', get_post_format() );
		//get_template_part( 'loop-templates/content', 'six', get_post_format() );
		//get_template_part( 'loop-templates/content', get_post_format() );
		//get_template_part( 'loop-templates/content', 'vertical', get_post_format() );
		//get_template_part( 'loop-templates/content', 'bigvertical', get_post_format() );
	endwhile;
else :
	get_template_part( 'loop-templates/content', 'none' );
endif;
?>
<?php vt_pagination(); ?>
<div class="gap-2"></div>
<?php 
get_template_part('global-templates/page','bottom');
get_footer();
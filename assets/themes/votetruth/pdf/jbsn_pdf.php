<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/*
Package: JBS Multisite Network
Author: Sam Mittelstaedt <smittelstaedt@jbs.org>
*/

$pdf_style .= 'body,td,div,table{font-family: Verdana, sans-serif; font-size:16px;}';


//PDF header
ob_start();
?>
<div class="row border-bottom border-2" style="margin-bottom:20px;">
<div class="col-10">
	<div class="text-left"><img width="312" height="60" alt="The John Birch Society" src="<?=  STYLE_URI;?>/assets/img/logo-sidebar.png"></div>
</div>
<div class="col-2 text-right pb-1">
	<barcode code="<?php echo $args['url']; //.'?utm_soruce=_pdf&utm_medium=qr'; ?>" type="QR" class="barcode" size="0.75" error="M" disableborder="1" />
</div>
</div>
<?php
$pdf_header = ob_get_clean();

//$pdf_footer = '<div class="text-normal text-center unbold border-top" style="font-size:11px;">Page {PAGENO} of {nbpg}</div>';

//CONTENT
ob_start();

//Authors are not WP Users, so we need to get the author from the post meta
$author = get_post_meta( get_the_ID(), 'jbs_author', true );
$source = get_post_meta( get_the_ID(), 'jbs_source', true );
$author_bio = get_post_meta( get_the_ID(), 'jbs_author_bio', true );
if ( $author || $source ) {
	echo '<div class="border-bottom border-2" style="font-size:14px; margin-bottom:20px;">';
	if ( $author ) {
		echo '<p class="text-center">Written by <strong>'.$author.'</strong></p>';
	}
	if ( $source ) {
		echo '<p class="text-center">Reprinted with permission from '.$source.'</p>';
	}
	if ( $author_bio ) {
		echo $author_bio;
	}
	echo '</div>';
}
?>
<div>
<h1 class="h2 text-center"><?php echo wp_kses_post($args['title']); ?></h1>
<?php if(!empty($args['img'])): ?>
<div class="img-fluid pl-2" style="width:400px; float:right;">
	<?php echo $args['img'];?>
	<?php echo ( !empty($args['img_attribution']) ? '<div class="text-center text-small">' . $args['img_attribution'] . '</div>' : '');?>
	<?php echo (!empty($args['img_caption']) ? '<div class="text-center text-small">' . $args['img_caption'] . '</div>' : '');?>
</div>
<?php endif;?>
<?php echo $args['content'];?>
</div>
<?php
$pdf_content = ob_get_clean();
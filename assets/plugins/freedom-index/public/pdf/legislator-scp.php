<?php if(!defined('ABSPATH')) exit;
/*
* 
* A4 11x8.5 inches = 280x216mm = @72dpi = 792x612px
* 24px x 2 margins = 744x564px
* POSTCARD format: 4 per page - double sided
*/
$page_width = 792;
$page_height = 612;
$margin = 20;

$legNameLen = strlen($display_name);
if($legNameLen > 30){$nameFS = 16;}
elseif($legNameLen > 28){$nameFS = 17;}
elseif($legNameLen > 26){$nameFS = 18;}
elseif($legNameLen > 24){$nameFS = 20;}
elseif($legNameLen > 22){$nameFS = 22;}
elseif($legNameLen > 20){$nameFS = 24;}
else{$nameFS = 26;}
require_once FI_PUBLIC_DIR . 'pdf/legislator-css.php';

ob_start();

echo $legislator_info_html;
$qr = $qr_codes['scorecard'];
?>
<div class="postcard-hook-container">
	<div class="postcard-hook">How did <?= $display_name; ?> vote</div>
	<div class="postcard-hook-sub">on Constitutional freedom issues?</div>
</div>
<table class="table table-borderless mb-0">
	<tr>
		<td class="postcard-footer-logo"><div class="postcard-footer-logo-pretext">Find out at</div><img src="<?= FI_URL . 'assets/img/freedomindexus-60.png'; ?>" alt="FreedomIndex.US"></td>
		<td class="postcard-footer-qr"><?= fi_qr_code($qr['url'], 50); ?></td>
	</tr>
</table>
<?php $postcard = ob_get_clean(); ?>


<div class="page-break"><!-- Page 1: Left=Back|Page 4 :: Right=Front -->
	<div class="page-half">
		<div class="page-quarter"><?= $postcard; ?></div>
		<div class="page-cut"> </div>
		<div class="page-quarter"><?= $postcard; ?></div>
	</div>
	<div class="page-fold"> </div>
	<div class="page-half">
		<div class="page-quarter"><?= $postcard; ?></div>
		<div class="page-cut"> </div>
		<div class="page-quarter"><?= $postcard; ?></div>
	</div>
</div>
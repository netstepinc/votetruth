<?php if(!defined('ABSPATH')) exit;
/*
* A4 11x8.5 inches = 280x216mm = @72dpi = 792x612px
* 24px x 2 margins = 744x564px
* Compact Layout: 2 per page: Front side is 2 copies of SCB front cover and back is 2 copies of SCB back cover
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
?>
<div class="page-break"><!-- Page 1: Left=Back|Page 4 :: Right=Front -->
	<div class="page-half">
		<?php include $pdf_parts_path .'legislator-front.php'; ?>
	</div>
	<div class="page-fold"></div>
	<div class="page-half">
		<?php include $pdf_parts_path .'legislator-front.php'; ?>
	</div>
</div>

<div class="page-break"><!-- Page 1: Left=Back|Page 4 :: Right=Front -->
	<div class="page-half">
		<?php include $pdf_parts_path .'legislator-back.php'; ?>
	</div>
	<div class="page-fold"></div>
	<div class="page-half">
		<?php include $pdf_parts_path .'legislator-back.php'; ?>
	</div>
</div>
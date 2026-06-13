<?php if(!defined('ABSPATH')) exit;
/*
* A4 11x8.5 inches = 280x216mm = @72dpi = 792x612px
* 24px x 2 margins = 744x564px

<div class="fi-cta"><?= $scorecard_cta; ?></div>
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
		<?php include $pdf_parts_path .'legislator-back.php'; ?>
	</div>
	<div class="page-fold"> </div>
	<div class="page-half">
		<?php include $pdf_parts_path .'legislator-front.php'; ?>
	</div>
</div>

<div class="page-break"><!-- Page 1: Left=Back|Page 4 :: Right=Front -->
	<div class="page-half">
		<div class="vote-text-header border-bottom">Why do these votes matter?</div>
		<?php echo $vote_texts_lg[0].$vote_sep.$vote_texts_lg[1].$vote_sep.$vote_texts_lg[2];?>
	</div>
	<div class="page-fold"> </div>
	<div class="page-half">
		<?php echo $vote_texts_lg[3].$vote_sep.$vote_texts_lg[4].$vote_sep.$vote_texts_lg[5]; ?>
	</div>
</div>
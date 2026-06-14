<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
* Legislators List PDF Template
*/
$page_width = 612;
$page_height = 792;
$margin = 20;
$nameFS = 12;
require_once FI_PUBLIC_DIR . 'pdf/legislator-css.php';
?>
<div class="fi-list-header border-bottom">
	<div class="fi-logo-p"><img src="<?= FI_URL . 'assets/img/freedomindexus-60.png'; ?>" alt="FreedomIndex.US"></div>
	<div class="fi-list-title"><?= PDF_PAGE_TITLE;?></div>
</div>
<table class="table table-borderless">
	<tr>
		<td class="p-0">
			<div class="fw-bold mb-1" style="font-size:18px;">Do your legislators vote for freedom?</div>
			<div class="mb-1" style="font-size:11.5px;"><?= $intro_text;?></div>
			<div class="fw-bold p-0" style="font-size:15px;">Find out at votetruth.us/<?= strtolower($gov);?></div>
		</td>
		<td class="p-0 text-end" style="width: 80px; padding-left:8px;">
			<?= fi_qr_code('https://votetruth.us/' . strtolower($gov) . '/legislators', 72); ?>
		</td>
	</tr>
</table>
<table class="table table-borderless">
<?php
$colCount = 0;
foreach ($legislators as $leg) {
	$display_name = $leg->display_name ?? ($leg->first_name . ' ' . $leg->last_name);
	$nameFontSize = strlen($display_name) > 18 ? 12 : 13;
	$party = strtoupper($leg->party ?? null);
	$district = (isset($leg->district_info) && !empty($leg->district_info->name)) ? ' ' . $leg->district_info->name : '';
	$freedom_score = $leg->freedom_score ?? $leg->score ?? null;
	$score = $freedom_score !== null ? (int)$freedom_score : 'N/A';
	$chamber = $leg->chamber_label ?? null;
	$image_id = $leg->image_id ?? null;
	$img = fi_legislator_image($image_id,null, ['size' => [160,200],'crop' => [0.5,0], 'retina' => false]);

	// Start a new row every 3 items
	if ($colCount % 3 == 0) {
		echo '<tr>';
	}
	?>
		<td class="p-0" style="width: 33.33%;">
			<table class="table table-borderless">
				<tr>
					<td class="p-0" style="width:20%;"><?= $img;?></td>';
					<td class="p-0 ps-2" style="width:80%; line-height: 1.1;">
						<span style="font-size: <?= $nameFontSize;?>px; font-weight: bold;"><?= $display_name;?></span><br>
						<span style="font-size: 11px; color: #333;">(<?= $party;?>) <?= $chamber;?> <?= $district;?></span><br>
						<span style=" font-size: 11px; font-weight: bold;">Freedom Score: <?= $score;?></span>
					</td>
				</tr>
			</table>
		</td>
	<?php
	$colCount++;
	// Close the row after 3 items
	if ($colCount % 3 == 0) {
		echo '</tr>';
	}
}
// Close row if the last row didn't have 3 items
if ($colCount % 3 != 0) {
	echo '</tr>';
}
?>
</table>
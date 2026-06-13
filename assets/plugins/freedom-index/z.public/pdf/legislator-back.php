<?php if(!defined('ABSPATH')) exit;
/* 
SCB Back Cover
SCC Back side 2x

QR code images can't be stacked too close together. Alternate left-right placement.
*/
?>
<div class="fi-header border-bottom">
	<div class="fi-logo"><img src="<?= FI_URL_IMG . 'freedomindexus-60.png'; ?>" alt="FreedomIndex.US"></div>
	<div class="fi-title"><?= $fi_title; ?></div>
	<div class="fi-subtitle"><?= $report_basedon; ?></div>
	<div class="fi-about"><?= $scorecard_about; ?></div>
</div>

<div class="fi-about">
<?php 
$q=0; 
foreach($qr_codes as $key => $qr):
	$q++;

	//Odd image left with padding right | even image right with padding left
	$qr_img_class = ($q % 2 == 0 ? 'qr-image-end' : 'qr-image' );
	$td_qr = '<td class="align-top '.$qr_img_class.'">' . fi_qr_code($qr['url'], 50) . '</td>';
	$td_text = '<td class="align-top"><div class="qr-title">' . $qr['title'] . '</div><div class="qr-text">' . $qr['text'] . '</div></td>';
?>
	<div class="qr-block">
		<table>
			<tr>
				<?php echo ($q % 2 == 0 ? $td_text . $td_qr : $td_qr . $td_text); ?>
			</tr>
		</table>
	</div>
	<?php endforeach; ?>
</div>
<div class="border-bottom"><?= fi_debt_clock(['gov' => $gov]); ?><!-- Debt Clock: <?= $gov;?> --></div>
<?php if($pdf_contacts): ?>
<div style="padding-top: 5px; margin-top: 10px;">
	<div class="contacts">
		<div class="title"><?= $pdf_contacts_title ?></div>
		<div class="text">Learn how you can stand up for freedom in your community.</div>
		<table class="table table-borderless mb-0">';
			<?php foreach($pdf_contacts as $contact): ?>
			<tr>
				<td class="name"><?= $contact['name'] ?></td>
				<td class="phone"><?= $contact['phone'] ?></td>
				<td class="email"><?= $contact['email'] ?></td>
			</tr>
			<?php endforeach; ?>
		</table>
	</div>
</div>
<?php else: 
/* ?>
<div style="padding-top: 5px; margin-top: 10px;">
	<div class="text-center" style="width: 50%; float: left;">
		<img src="<?= FI_URL_IMG . 'logo-jbs-lg.jpg'; ?>" alt="The John Birch Society" style="width:80%;">
		<div class="text-center mt-0">www.jbs.org</div>
	</div>
	<div class="text-center" style="width: 50%; float: right;">
		<img src="<?= FI_URL_IMG . 'logo-tna-lg.jpg'; ?>" alt="The New American Magazine" style="width:80%;">
		<div class="text-center mt-1">www.thenewamerican.com</div>
	</div>
</div>
<?php */ endif; ?>
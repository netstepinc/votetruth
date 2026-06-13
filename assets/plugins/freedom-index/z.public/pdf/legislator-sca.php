<?php if(!defined('ABSPATH')) exit;
/*
* A4 8.5x11 inches = 216x280mm = @72dpi = 612x792px
* 		<div class="report-score report-score-p"><?= $last_name; ?> voted for <b>freedom</b> on <span><?= $report_score; ?>%</span> of the votes below.</div>
*/
$page_width = 612;
$page_height = 792;
$margin = 20;
$nameFS = 26;
require_once FI_PUBLIC_DIR . 'pdf/legislator-css.php';
?>
<div class="page-break"><!-- Front -->

	<div class="fi-header border-bottom">
		<div class="fi-logo-p"><img src="<?= FI_URL . 'assets/img/freedomindexus-60.png'; ?>" alt="FreedomIndex.US"></div>
	</div>

	<table cellspacing="0" cellpadding="0" class="table table-borderless mb-1">
		<tr>
			<td style="width: 75%;">
				<table cellspacing="0" cellpadding="0" class="table table-borderless mb-0">
					<tr>
						<td class="leg-photo">
							<?= $img_html; ?>
						</td>
						<td class="leg-info">
							<div class="leg-name"><?= $display_name; ?></div>
							<table cellspacing="0" cellpadding="0" class="table table-borderless mb-0">
								<tr>
									<td>
										<div class="leg-gov"><?= $gov_name;?></div>
										<div class="leg-represents"><?= $chamber_title;?>, <?= $represents; ?></div>
										<?php if($leg_phone): ?>
										<div class="leg-phone"><span class="fw-normal">Phone:</span> <?= $leg_phone; ?></div>
										<?php endif; ?>
										<div class="leg-url"><?= $url_shortcut; ?></div>
									</td>
									<td class="leg-score-container">
										<?php if($freedom_score): ?>
										<div class="leg-score">
											<div class="fi-blue leg-score-number"><?= $freedom_score; ?></div>
											<div class="leg-score-title">Lifetime<br>Freedom<br>Score</div>
										</div>
										<?php endif; ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
			</td>
			<td style="width: 10px;">&nbsp;</td>
			<td class="" style="width: 15%;">
				<div class="text-center">
					<?php $qr = $qr_codes['scorecard']; unset($qr_codes['scorecard']); ?>
					<div>Scan to view<br>vote history</div>
					<?= fi_qr_code($qr['url'], 60); ?>
				</div>
			</td>
		</tr>
	</table>

	<div class="report-header border-top">
		<div class="report-title"><?= $report_title; ?></div>
		<div class="report-basedon fw-bold"><?= $report_basedonp; ?></div>
		<div class="fi-about border-bottom"><?= $scorecard_about; ?></div>
	</div>
	<?php echo $vote_table; ?>

	<div class="border-top mt-1">
		<table class="table table-borderless mb-0">
			<tr>
				<td style="width: 55%;">
					<?php unset($qr_codes['scorecard']); ?>
					<?php foreach($qr_codes as $key => $qr): ?>
					<div class="qr-block" style="padding-top:0;">
						<table>
							<tr>
								<td class="align-top qr-image"><?= fi_qr_code($qr['url'], 50) ?></td>
								<td class="align-top">
									<div class="qr-title"><?= $qr['title']; ?></div>
									<div class="qr-text"><?= $qr['text']; ?></div>
								</td>
							</tr>
						</table>
					</div>
					<?php endforeach; ?>

<?= fi_debt_clock(); ?>					

				</td>
				<td style="width: 45%; padding-top:4px;">
				<?php 
				if($pdf_contacts){
					echo '<div class="text-center ps-2 contacts"><div class="title">'.$pdf_contacts_title.'</div>';
					echo '<div class="text">Learn how you can stand up<br>for freedom in your community.</div>';
					foreach($pdf_contacts as $contact){	
						echo '<div class="contact text-center">';
						echo '<div class="name mt-2">'.$contact['name'] . '</div>';
						if(isset($contact['phone']) && !empty($contact['phone'])){
							echo '<div class="phone">'.$contact['phone'] . '</div>';
						}
						if(isset($contact['email']) && !empty($contact['email'])){
							echo '<div class="email">'.$contact['email'] . '</div>';
						}
						echo '</div>';
					}
					echo '</div>';

				}else{ /* ?>
					<div style="padding-top: 5px;">
						<div class="text-center">
							<img src="<?= FI_URL_IMG . 'logo-jbs-lg.jpg'; ?>" alt="The John Birch Society" style="width:70%;">
							<div class="text-center">www.jbs.org</div>
						</div>
						<div class="text-center mt-5">
							<img src="<?= FI_URL_IMG . 'logo-tna-lg.jpg'; ?>" alt="The New American Magazine" style="width:70%;">
							<div class="text-center">www.thenewamerican.com</div>
						</div>
					</div>
				<?php  */ } ?>
				</td>
			</tr>
		</table>
	</div>
</div>
<div class="page-break"><!-- Back - vote details -->
	<div class="vote-text-header border-bottom">Why do these votes matter?</div>
	<?php echo $vote_texts_lg[0].$vote_sep.$vote_texts_lg[1].$vote_sep.$vote_texts_lg[2].$vote_sep.$vote_texts_lg[3].$vote_sep.$vote_texts_lg[4].$vote_sep.$vote_texts_lg[5]; ?>
</div>
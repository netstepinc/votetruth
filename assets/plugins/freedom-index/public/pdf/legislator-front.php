<?php if(!defined('ABSPATH')) exit;
/* 
SCB Front Cover
SCC Front side 2x

<div class="report-score"><?= $last_name; ?> voted for <b>freedom</b> on <span><?= $report_score; ?>%</span> of the votes below.</div>
*/

echo $legislator_info_html;
?>

<div class="report-header border-top">
	<div class="report-title"><?= $report_title; ?></div>
	<div class="report-basedon"><?= $report_basedonp; ?></div>
</div>
<?php echo $vote_table; ?>
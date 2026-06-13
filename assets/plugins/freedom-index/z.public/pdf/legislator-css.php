<?php if(!defined('ABSPATH')) exit;
$halfPage = ($page_width/2) - ($margin); // 792/2 = 396 - 2*20 = 356
$quarterPage = $page_height/2 - ($margin*2.5); // 612 - 2*16 = 580px / 2 = 290px
$pageFold = $margin*2;
$pageCut = $margin*2;
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.8/css/bootstrap.min.css" integrity="sha512-2bBQCjcnw658Lho4nlXJcc6WkV/UxpE/sAokbXPxQNGqmNdQrWqtw26Ns9kFF/yG792pKR1Sx8/Y1Lf1XN4GKA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
@page {
	margin: <?= $margin; ?>px; /* Sets top, right, bottom, and left margins*/
}
body,h1, h2, h3, h4, h5, h6, div,table,td {font-family: Helvetica; font-size:11px; margin-bottom:0; line-height:1.125;}
.page-half {width:<?= $halfPage; ?>px; float:left;} /*  background-color: #ddd;  border:1px dashed purple; */
.page-quarter{height:<?= $quarterPage;?>px;}
.page-fold {width:<?= $pageFold; ?>px; float:left; height:100%;}
.page-cut{height:<?= $pageCut; ?>px; width:100%; line-height: 0;}
.page-break {page-break-after: always;}
.auto-break {page-break-inside: auto;}
.clearfix {clear: both;}
.fi-blue{color:#0055a4;}
.fi-red{color:#c41425;}
.fi-green{color:#198754;}
.border-bottom{border-bottom: 1px solid #999;}
.border-top{border-top:1px solid #999;}
.border-y{border-bottom: 1px solid #999; border-top:1px solid #999;}
.fi-header {padding-bottom: 5px; margin-bottom: 5px;}
.fi-list-header {padding-bottom: 3px; margin-bottom: 3px;}
.fi-logo,.fi-logo-p {text-align:center; margin-bottom: 5px;}
.fi-logo img {width: 70%; height: auto;}
.fi-logo-p img {width: 40%; height: auto;}
.fi-title {text-align: center; font-size:18px; font-weight: bold; text-transform: uppercase;}
.fi-list-title {text-align: center; font-size:18px; font-weight: bold;}
.fi-subtitle {text-align: center; font-size:13px; font-weight: bold; text-transform: uppercase; margin-bottom: 6px;}
.fi-about{text-align: justify;}
.fi-cta {font-size:12px; text-align:center; font-weight: bold; margin-top: 10px;}
.qr-block {width: 100%; padding:5px 0;border-bottom: 1px solid #999;}
.qr-block table {width: 100%;}
.qr-image {width: 50px; padding-right:10px;}
.qr-image-end {width: 50px; padding-left:10px;}
.qr-title {font-size:13px; font-weight: bold;}
.qr-text {padding-top:2px;}
/*Legislator*/
.leg-photo {width: 72px;}
.leg-info {text-align:left; font-size:12px; padding-left:5px; vertical-align: top;}
.leg-name {font-size:<?= $nameFS; ?>px; font-weight: bold; line-height: .9;}
.leg-title {font-size:16px; font-weight: bold;}
.leg-represents {font-size:13px;}
.leg-phone {font-size:13px; }
.leg-gov {font-size:13px; font-weight: bold;}
.leg-url {font-size:16px; margin-top: 5px; font-weight: bold;}
.leg-score-container{width:64px; padding-top:0;}
.leg-score{border:1px solid #0055a4; padding:0 0 0 0; text-align:center;}
.leg-score-number {font-size:32px; line-height: 1; font-weight: bold;  text-align:center;}
.leg-score-title {font-size:9px; line-height: 1; font-weight: bold; text-align:center;}
/* Report*/
.report-header {padding-top:5px; padding-bottom: 5px; margin-top:4px; margin-bottom: 0px;}
.report-title {font-size:18px; font-weight: bold; line-height:1; text-align:center;}
.report-basedon {font-size:12px; line-height:1.2; text-align:center;}
.report-score {font-size:13px; text-align:center; line-height: 1.2; padding-top:3px;}
.report-score-p {font-size:16px; padding-top:3px;}
.report-score span {font-weight: bold;}
/*Votes*/
#vote-table td {padding-top:4px; border-bottom: 1px solid #999; text-align: justify; font-size:10px;}
/* #vote-table tr:last-child td {border-bottom: none; padding-bottom:0; margin-bottom:0;}*/
#vote-table th {padding-bottom:2px; margin-bottom:2px; font-size:11px;}
.vote-table-score {font-size:<?= $vote_table_footer_font_size; ?>; font-weight:bold; text-align:right; padding-top:4px;}
.vote-text-header{font-size:18px; font-weight: bold; line-height:1; text-align:center; margin-bottom: 4px;}
.vote-cost{line-height: 1.2;}
.vote-cast{font-size:12px; font-weight: bold; padding-bottom:2px;}
h5 {font-size:12px; font-weight: bold; margin-bottom:3px; line-height: 1;}
.content {font-size:10px; text-align: justify; line-height: 1.1;}
.content p,td p{margin-bottom:4px;}
.vote-separator{height:1px; background-color:#999; margin:4px 0;}
.vote-text-lg{font-size:10px;}
.vote-text-lg h5{font-size:11px;margin-bottom:2px;}
/* Declaration of Independence */
.text-di{font-size:12px; text-align: justify; font-style: italic;}
.text-di-att{font-size:10px; text-align: right; margin-top:2px;}
/* PDF Contacts */
.contacts{}
.contacts .title{font-size:14px; font-weight: bold;}
.contacts .text{font-size:12px; margin-bottom: 5px; margin-top: 5px;}
.contacts .name{font-weight: bold; line-height: 1.2;}
.contacts td.phone{padding-left: 10px; line-height: 1.2; text-align: right;}
.contacts td.email{padding-left: 10px; line-height: 1.2; text-align: right;}
/* Postcard */
.postcard-hook-container {height:88px; border: 0px dotted red;}
.postcard-hook {font-size: 20px;font-weight: bold;text-align: center;margin-top: 20px; margin-bottom:0; line-height: 1;}
.postcard-hook-sub {font-size: 18px;font-weight: bold;text-align: center;margin-top: 10px; margin-bottom:0; line-height: 1;}
.postcard-footer-logo-pretext {font-weight:bold;color:#666;line-height: 1;font-size:18px;	padding-bottom:1px;}
.postcard-footer-logo {text-align:left;vertical-align:bottom;}
.postcard-footer-logo img {width: 216px;height: 30px;}
.postcard-footer-qr {width: 60px;padding-left: 10px;text-align:right;}
/* National Debt */
.treasury-debt-amount {font-size: 36px; font-weight: bold; text-align: center; line-height: 1.2; margin-bottom: 2px; color: #cc0000;}
.treasury-debt-date {font-size: 11px; font-weight:bold; text-align: center; line-height: 1.2; margin-bottom: 2px;}
.treasure-debt-quote{width: 70%; font-size: 11px; text-align: center; line-height: 1.2; margin-bottom: 2px; font-style: italic;}
.text-debt-att{font-size: 10px; text-align: right; margin-top:2px; font-style:normal;}
</style>
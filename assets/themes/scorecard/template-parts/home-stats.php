<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
Home page widget display showing our system totals from the database.
- Legislators tracked
- Votes scored
- Rollcalls counted
*/ 
global $wpdb;

//Get total published votes from #_fs_legislators table
$legislators_tracked = $wpdb->get_var("SELECT COUNT(*) FROM ".TBFS_LEGISLATORS);
$tracked = number_format($legislators_tracked);

//Get total publish votes from #_fs_votes table
$votes_scored = $wpdb->get_var("SELECT COUNT(*) FROM ".TBFS_VOTES." WHERE status = 'publish'");
$scored = number_format($votes_scored);

//Get total published rollcalls from #_fs_voterc table
$rollcalls_counted = $wpdb->get_var("SELECT COUNT(*) FROM ".TBFS_VOTERC);
$counted = number_format($rollcalls_counted);
?>
<div id="home-stats" class="container-fluid p-0">
	<div class="container">
		<div class="row g-0">
			<div class="col-12 col-sm-4 p-4 border-end">
				<div class="fs-1 ff-h fw-7 lh-1 text-center text-primary"><?= $tracked; ?></div>
				<div class="fs-7 text-secondary text-center text-uppercase">Legislators Scored</div>
			</div>
			<div class="col-12 col-sm-4 p-4 border-end">
				<div class="fs-1 ff-h fw-7 lh-1 text-center text-primary"><?= $scored; ?></div>
				<div class="fs-7 text-secondary text-center text-uppercase">Votes Analyzed</div>
			</div>
			<div class="col-12 col-sm-4 p-4">
				<div class="fs-1 ff-h fw-7 lh-1 text-center text-primary"><?= $counted; ?></div>
				<div class="fs-7 text-secondary text-center text-uppercase">Roll Calls Counted</div>
			</div>
		</div>
	</div>
</div>
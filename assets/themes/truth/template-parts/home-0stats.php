<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
STATS
[Legislators tracked] Legislators Scored
[Votes scored] Votes on Record
[Rollcalls counted] Roll Calls Verified
*/

$stats = function_exists('fi_content_stats') ? fi_content_stats() : array('tracked' => '0', 'scored' => '0', 'counted' => '0');
?>
<div id="home-stats" class="container-fluid p-0 border-bottom border-2">
	<div class="container">
		<div class="row g-0">
			<div class="col-12 col-sm-4 p-4 border-end">
				<div class="fs-1 ff-h fw-7 lh-1 text-center text-primary"><?= $stats['tracked']; ?></div>
				<div class="fs-7 text-secondary text-center text-uppercase">Legislators Scored</div>
			</div>
			<div class="col-12 col-sm-4 p-4 border-end">
				<div class="fs-1 ff-h fw-7 lh-1 text-center text-primary"><?= $stats['scored']; ?></div>
				<div class="fs-7 text-secondary text-center text-uppercase">Votes on Record</div>
			</div>
			<div class="col-12 col-sm-4 p-4">
				<div class="fs-1 ff-h fw-7 lh-1 text-center text-primary"><?= $stats['counted']; ?></div>
				<div class="fs-7 text-secondary text-center text-uppercase">Roll Calls Verified</div>
			</div>
		</div>
	</div>
</div>
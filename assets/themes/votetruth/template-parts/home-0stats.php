<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
STATS
[Legislators tracked] Legislators Scored
[Votes scored] Votes on Record
[Rollcalls counted] Roll Calls Verified
*/

$stats = function_exists('fi_content_stats') ? fi_content_stats() : array('tracked' => '0', 'scored' => '0', 'counted' => '0');
?>
<div id="home-stats" class="container-fluid bg-light p-0 d-none d-md-block border-bottom">
	<div class="container py-3">
		<div class="row g-0">
			<div class="col-6 col-md-4 p-4">
				<div class="fs-3 ff-h fw-7 lh-1 text-center text-anchor"><?= $stats['tracked']; ?></div>
				<div class="fs-8 mt-1 text-secondary text-center text-uppercase">Legislators Scored</div>
			</div>
			<div class="col-6 col-md-4 p-4">
				<div class="fs-3 ff-h fw-7 lh-1 text-center text-anchor"><?= $stats['scored']; ?></div>
				<div class="fs-8 mt-1 text-secondary text-center text-uppercase">Votes Rated</div>
			</div>
			<div class="col-md-4 p-4 d-none d-md-block">
				<div class="fs-3 ff-h fw-7 lh-1 text-center text-anchor"><?= $stats['counted']; ?></div>
				<div class="fs-8 mt-1 text-secondary text-center text-uppercase">Roll Calls Verified</div>
			</div>
		</div>
	</div>
</div>
<?php if ( ! defined( 'ABSPATH' ) ) { exit; }?>
<div id="home-score-scale" class="card rounded-4 mx-auto">
	<div class="card-header rounded-top-4 bg-anchor">
		<div class="fs-7 fw-bold text-uppercase text-fade">Freedom Score</div>
	</div>
	<div class="card-body">
		<ul class="list-unstyled list-flush">
		<?php
		foreach(FI_GRADES as $grade => $data) {
			echo '<li class="d-flex align-items-center gap-3 pb-2">
				'.fi_grade(['grade' => $grade, 'size' => '36px', 'fs' => '16px']).'
				<div class="fi-scale-range">'.$data['min'].'-'.$data['max'].'</div>
				<div class="fi-scale-desc">'.$data['label'].'</div>
			</li>';
		}
		?>
		</ul>
	</div>
</div>
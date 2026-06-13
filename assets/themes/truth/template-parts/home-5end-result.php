<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
STAKES
Your money. Your freedom. Their votes.
Every vote for bigger government reaches your paycheck, your family, and your future.
Knowing the record doesn't just inform your vote.
It changes what politicians dare to do with theirs.
IMAGE: The Stakes section could use an abstract visual representing scale — national debt clock imagery, or simply a strong typographic treatment of "Your money. Your freedom. Their votes." large enough to be a visual element in itself.
//get_template_part('template-parts/home','debt');
*/
?>
<div id="home-stakes" class="container-fluid py-5 border-bottom bg-anchor">
	<div class="container py-lg-5">
		<div class="row">
			<div class="col-12 col-lg-8 p-0 pb-3 pt-lg-3 pe-lg-5">
				<p class="text-uppercase text-white fs-6">Your money. Your freedom.</p>
				<p class="fs-7 text-fade ext-white">Every vote for bigger government reaches your paycheck, your family, and your future.</p>
				<p class="fs-7 fw-6 ff-h text-white">Knowing the score doesn't <span class="text-nowrap">just inform your vote.</span></p>
				<p class="fs-7 fw-6 ff-h text-white">It changes what politicians <span class="text-nowrap">dare to do with theirs.</span></p>
			</div>
			<div class="col-12 col-lg-4 px-3">
				<?php get_template_part('template-parts/home-result-debt'); ?>
			</div>
		</div>
	</div>
</div>

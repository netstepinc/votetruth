<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
ANSWER
A voting record shows the pattern.
[SCORING GRID]
The Freedom Index scores every legislator against one standard: the Constitution. Not speeches. Not slogans. Not party labels. Recorded votes.

<h2 class="fs-4 ff-h fw-7">What Is a Freedom Score?</h2>
<p class="fs-5">Your Freedom Score shows how often your lawmaker voted to protect it — based on their actual votes, not speeches or promises.</p>
<p class="fs-5">A voting record shows the pattern.</p>
<p class="fs-5">The Freedom Index scores every legislator against one standard: the Constitution. Not speeches. Not slogans. Not party labels. Recorded votes.</p>
<p class="fs-5">Every lawmaker swore an oath to uphold the Constitution. The score shows whether they kept it.</p>
*/
?>
<div id="home-answer" class="container-fluid p-0 py-lg-5 border-bottom bg-amber-light-1">
	<div class="container py-5">
		<div class="row g-0">
			<div class="col-12 col-md-6 pb-4 pb-md-0">
				<p class="text-uppercase text-anchor fs-6">One number tells a story</p>
				<p class="fs-6 fw-5">The Constitution was written to keep government small and your life big.</p>
				<p class="fs-7">The <span class="fw-7">Freedom Score</span> tells you how often your legislators voted to protect your rights, wallet, country, and independence.</p>
			</div>
			<div class="col-12 col-md-6">
				<?php get_template_part('template-parts/score-chart'); ?>
			</div>
		</div>
	</div>
</div>
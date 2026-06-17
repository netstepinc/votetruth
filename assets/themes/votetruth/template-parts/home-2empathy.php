<?php if ( ! defined( 'ABSPATH' ) ) { exit; }?>
<!--
<div class="container-fluid p-5">
	<div class="container pb-5">
		<div class="mx-auto col-12 col-md-11 col-lg-10 col-xl-9 col-xxl-8">
			<p class="fs-4 mb-5 text-anchor text-center">We know it's frustrating when politicians say one thing then do another.</p>
		</div>
		<p class="fs-4 fw-7 text-center">That's why we made <span class="text-anchor text-nowrap">the Freedom Score</span>.</p>
	</div>
</div>
<p class="fs-5 text-white text-center">We know it's discouraging when politicians say one thing then do another.</p>
<p class="fs-4 fw-6 mb-5 text-amber text-center">Promises are easy. <span class="text-nowrap">Votes are proof.</span></p>
-->

<div class="container-fluid py-5 bg-black">
	<div class="container">
		<div class="mx-auto col-12 col-md-11 col-lg-10 col-xl-9 col-xxl-8">
			<p class="fs-5 text-white text-center">We know how frustrating it is to be ignored by the people elected to represent us.</p>
<?php if(isset($_GET['words']) && $_GET['words'] == 'more'): ?>
			<p class="fs-4 fw-6 mb-5 text-amber text-center">Promises are easy. <span class="text-nowrap">Votes are proof.</span></p>
<?php endif; ?>
		</div>
		<p class="fs-3 fw-6 text-center text-white">That's why we created</p>
		<p class="fs-3 fw-7 ff-h lh-1 text-center text-amber">The Freedom Score</p>
	</div>
</div>
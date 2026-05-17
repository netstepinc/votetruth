<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/*

*/
get_header();
get_template_part('global-templates/page','top',['title' => 'Offline']);
?>
<section class="pwa-hero text-center py-4">
	<div class="container-xl">
		<div class="card rounded-4 shadow h-100">
			<div class="card-body">
				<h1 class="display-5 fw-bold">You are Offline...</h1>
				<p class="lead mt-3">This app can’t reach the network right now.</p>
				<div class="mt-4"><a href="<?php echo home_url(); ?>" class="btn btn-primary btn-lg px-4">Go to Home</a></div>
				<p class="mt-3 text-danger">If you installed the app, some pages may still open from your device.</p>
				<p class="mt-3 text-danger">When you’re back online, everything will load normally.</p>
			</div>
		</div>
	</div>
</section>
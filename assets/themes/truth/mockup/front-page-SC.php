<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
/*
Custom Front Page Dashboard Style Template - Mobile First - All content in responsive columns and widget blocks.
*/
function fi_front_page_content($args = array()) {
	$content = scorecard_static_block($args['id']);
	$class = $args['class'] ?? '';
	echo '<div class="card rounded-4 shadow bg-white mb-4 gsap-duration-1 gsap-zoom-in '.$class.'"><div class="card-body p-3 p-lg-4"><h2>'.$content['title'].'</h2>'.$content['content'].'</div></div>';
}

get_header();
//get_template_part('template-parts/sc','find-my-bar');
fi_legislators_find_mine();
?>
<div class="container-xl p-0 m-0 mx-auto">
	<div id="legislator-search-results"></div>
</div>
<div id="hero-search-section" class="hero-search-section">
	<div class="container-fluid p-0 shadow">
		<div class="row g-0">
			<div class="col-12 p-0">
				<img src="<?= STYLE_IMG . 'hero-sm2.jpg'; ?>" alt="Hold Elected Officials Accountable" class="img-fluid d-md-none">
				<img src="<?= STYLE_IMG . 'hero-md.jpg'; ?>" alt="Hold Elected Officials Accountable" class="img-fluid d-none d-md-block d-lg-none">
				<img src="<?= STYLE_IMG . 'hero-lg.jpg'; ?>" alt="Hold Elected Officials Accountable" class="img-fluid d-none d-lg-block">
			</div>
		</div>
	</div>
</div>
<div class="container-fluid border-top bg-light p-0">
	<div class="container-xl py-4">
		<div class="row mb-lg-5">
			<div class="col-12 col-md-6">
				<?php get_template_part('template-parts/sc','stats');?>
				<?php get_template_part('template-parts/sc','map-vector');?>
				<?php get_template_part('template-parts/us-debt-clock');?>
				<?php fi_front_page_content(['id' => 2076]); //Features ?>
			</div>
			<div class="col-12 col-md-6">
				<?php fi_front_page_content(['id' => 2074,'class' => 'bg-light']); //about ?>
				<?php fi_front_page_content(['id' => 2075]); //Video ?>
				<?php fi_front_page_content(['id' => 2079,'class' => 'border-primary text-primary']); //History ?>
			</div>
		</div>
	</div>
</div>
<div class="container-fluid pb-5 bg-light">
	<div class="container-xl">
		<div class="row">
			<div class="col-12 col-md-10 offset-md-1 text-center brushfire">It does not take a majority to prevail... but rather an irate, tireless minority, keen on setting brush fires of freedom in the minds of men.</div>
		</div>
	</div>
</div>
<?php
//Generate static examples of bootstrap vote icons to save as assets
/*
if(get_current_user_id() == 1):
?>
<div class="container-fluid py-5 bg-white" style="font-size: 2rem;">
<div class="text-black"><i class="bi bi-question-circle"></i> <i class="bi bi-hand-thumbs-down"></i><i class="bi bi-hand-thumbs-down-fill"></i><i class="bi bi-hand-thumbs-up"></i><i class="bi bi-hand-thumbs-up-fill"></i></div>
<div class="text-danger"><i class="bi bi-x-circle"></i> <i class="bi bi-x-square"> <i class="bi bi-x-lg"></i> <i class="bi bi-x-circle-fill"></i> <i class="bi bi-x-square-fill"></i> <i class="bi bi-x-lg-fill"></i> <i class="bi bi-hand-thumbs-down"></i><i class="bi bi-hand-thumbs-down-fill"></i><i class="bi bi-hand-thumbs-up"></i><i class="bi bi-hand-thumbs-up-fill"></i></div>
<div class="text-success"><i class="bi bi-plus-circle-fill"></i> <i class="bi bi-plus-circle"></i> <i class="bi bi-star-fill"></i> <i class="bi bi-star-half-fill"></i> <i class="bi bi-hand-thumbs-down"></i><i class="bi bi-hand-thumbs-down-fill"></i><i class="bi bi-hand-thumbs-up"></i><i class="bi bi-hand-thumbs-up-fill"></i></div>
</div>
<?php endif;*/ ?>
<?php get_footer();
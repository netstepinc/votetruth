<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
 * Front Page Template — Freedom Index Home v2
 * PEACE stands for Problem, Empathy, Answer, Change, and End Result.
 */
$debt_data = fi_api_treasurygov_debt_now(); 
$households = fi_api_census_households() ?: 134790000;
$debt_household = '$'.number_format(round(($debt_data['amount'] / $households),2),2);

$stats = function_exists('fi_content_stats') ? fi_content_stats() : ['tracked' => '0', 'scored' => '0', 'counted' => '0'];

$actions = [];
$actions[] = [
    'title' => 'Scorecards',
    'desc' => 'Learn how to customize, print and share at meetings, events, and community gatherings.',
    'icon' => 'bi bi-card-checklist',
    'link' => home_url('/help/printing/'),
    'button_text' => 'Get scorecards'
];
$actions[] = [
    'title' => 'Mobile Apps',
    'desc' => 'Check any legislator\'s score from your phone — anytime, anywhere.',
    'icon' => 'bi bi-phone-fill',
    'link' => home_url('/app/'),
    'button_text' => 'Get the App'
];
$actions[] = [
    'title' => 'Resources',
    'desc' => 'Learn more about the Constitution and how to hold legislators accountable.',
    'icon' => 'bi bi-tools',
    'link' => home_url('/tools/'),
    'button_text' => 'Learn More'
];

get_header();
?>
<style>
#findmy {
  background:
    linear-gradient(160deg,
      rgba(11,47,107,0.62) 0%,
      rgba(11,47,107,0.87) 30%,
      rgba(11,47,107,0.92) 60%),
    repeating-linear-gradient(
      45deg,
      transparent 0px,
      transparent 18px,
      rgba(255,255,255,0.012) 18px,
      rgba(255,255,255,0.012) 19px
    ),
    url('<?= STYLE_IMG ?>constitution-cropped.jpg') top left / cover no-repeat;
/*  padding: 3em 2em 3em; */
  text-align: center;
  position: relative;
}
</style>
<div id="findmy" class="container-fluid border-bottom">
	<div class="container">
		<div class="row">
			<div class="col-12 px-0 px-md-3 px-lg-5">
				<h1 class="text-center mx-auto fs-1 my-5"><span class="text-action">Votes</span> Tell <span class="text-nowrap">the <span class="text-action">Truth</span></span></h1>
				<div class="my-4">
					<p class="fs-2 text-white fw-7 text-center mb-4">Are your legislators <span class="text-nowrap">working for you?</span></p>
					<p class="fs-4 mb-3 text-fade-action fw-7 text-center">See how they voted.</p>
				</div>
				<form id="header-legislator-search-form" class="mb-4 col-12 col-md-11 col-lg-10 col-xl-9 col-xxl-8 mx-auto" method="#" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search" novalidate>
					<div class="input-group position-relative">
						<input id="header-legislator-search-input" class="form-control form-control-lg fs-7 bg-white" name="fi_search" type="search" placeholder="<?= FI_SEARCH_PLACEHOLDER;?>" value="<?php echo esc_attr( isset( $_GET['fi_search'] ) ? $_GET['fi_search'] : '' ); ?>" aria-label="Search" autocomplete="off" minlength="3">
						<div id="header-search-suggestions" class="position-absolute bg-white border rounded shadow d-none" style="z-index: 1050; max-height: 400px; overflow-y: auto;"></div>
						<button id="header-search-clear-btn" class="btn btn-warning p-2 d-none" type="button" aria-label="Clear search" title="Clear search">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
								<line x1="18" y1="6" x2="6" y2="18"></line>
								<line x1="6" y1="6" x2="18" y2="18"></line>
							</svg>
						</button>
						<button class="btn btn-action fw-4 fs-6" type="submit" aria-label="Search">
							<span class="d-none d-xl-inline">Find My Legislators</span>
							<span class="d-none d-lg-inline d-xl-none">Find Legislators</span>
							<span class="d-lg-none">Search</span>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<div class="container text-center py-5 mb-3">
		<button type="button" class="btn btn-sm btn-glass px-3 fw-5 fs-8 m-2" data-bs-toggle="bottom-sheet" data-content="federal">View Federal Legislators</button>
		<button type="button" class="btn btn-sm btn-glass px-3 fw-5 fs-8 m-2" data-bs-toggle="bottom-sheet" data-content="state">View State Legislators</button>
	</div>
</div>
<!--
<div id="home-statement" class="container-fluid py-5 bg-warm border-top">
	<div class="container mb-4">
		<div class="row">
			<div class="col-12 py-4">
				<h2 class="fs-3 fw-7 text-center text-brand">Have politicians <span class="text-nowrap">ever let you down?</span></h2>	
			</div>
			<div class="col-12 col-lg-6 pb-4 pt-lg-4">
				<p class="fs-6 fw-6 lh-1 text-center">Campaigns full of promises.</p>
				<p class="fs-6 fw-6 lh-1 text-center">Terms full of excuses.</p>
				<p class="fs-6 fw-6 lh-1 text-center">Both sides grow government.</p>
				<p class="fs-6 fw-6 lh-1 text-center">Bad politicians keep their jobs.</p>
				<p class="fs-5 fw-7 lh-1 text-center text-brand mt-4">We pay for all of it.</p>
			</div>
			<div class="col-12 col-lg-6 px-4 px-lg-5">
				<img src="<?= STYLE_IMG; ?>/home-1problem.png" alt="Problem" class="img-fluid rounded-4">
			</div>
		</div>
	</div>
</div>
-->
<div id="home-problem" class="container-fluid py-5 border-bottom bg-white">
	<div class="container">
		<div class="row">
			<div class="col-12">
				<h2 class="fs-2 fw-7 text-center text-action"><?= $debt_household ?></h2>
				<p class="fs-3 fw-6 text-center">U.S. National Debt Per Household.</p>
			</div>
		</div>
	</div>
</div>
<div id="home-agitate" class="container-fluid py-5 border-bottom bg-white">
	<div class="container">
		<div class="row">
			<div class="col-12">
				<p class="fs-4 text-center">Every vote for bigger government reaches your paycheck, your family, and your future.</p>
			</div>
		</div>
	</div>
</div>


<div class="container-fluid py-5 bg-black">
	<div class="container">
		<div class="mx-auto col-12 col-md-11 col-lg-10 col-xl-9 col-xxl-8">
			<p class="fs-5 text-white text-center">We know how frustrating it is to be ignored by the people elected to represent us.</p>
		</div>
		<p class="fs-3 fw-6 text-center text-white">That's why we created</p>
		<p class="fs-3 fw-7 ff-h lh-1 text-center text-action">The Freedom Score</p>
	</div>
</div>


<div id="home-answer" class="container-fluid p-0 py-lg-5 border-bottom bg-action-light-1">
	<div class="container py-5">
		<div class="row g-0">
			<div class="col-12 col-md-6 pb-4 pb-md-0">
				<p class="text-uppercase text-brand fs-6 text-center text-lg-start">One number tells a story</p>
				<p class="fs-6 fw-5 text-center text-lg-start">The Freedom Score shows whether your legislators voted to keep government small and your life big.</p>
			</div>
			<div class="col-12 col-md-6">
				<?php get_template_part('template-parts/score-chart'); ?>
			</div>
		</div>
	</div>
</div>


<div id="home-stats" class="container-fluid bg-light p-0 d-none d-md-block border-bottom">
	<div class="container py-3">
		<div class="row g-0">
			<div class="col-6 col-md-4 p-4">
				<div class="fs-3 ff-h fw-7 lh-1 text-center text-brand"><?= $stats['tracked']; ?></div>
				<div class="fs-8 mt-1 text-secondary text-center text-uppercase">Legislators Scored</div>
			</div>
			<div class="col-6 col-md-4 p-4">
				<div class="fs-3 ff-h fw-7 lh-1 text-center text-brand"><?= $stats['scored']; ?></div>
				<div class="fs-8 mt-1 text-secondary text-center text-uppercase">Votes Rated</div>
			</div>
			<div class="col-md-4 p-4 d-none d-md-block">
				<div class="fs-3 ff-h fw-7 lh-1 text-center text-brand"><?= $stats['counted']; ?></div>
				<div class="fs-8 mt-1 text-secondary text-center text-uppercase">Roll Calls Verified</div>
			</div>
		</div>
	</div>
</div>


<div id="home-answer" class="container-fluid border-bottom p-0" style="background-image: url('<?= STYLE_IMG; ?>/home-change-bg2.jpg'); background-size: cover; background-position: center;">
	<div class="py-5" style="background: linear-gradient(to right, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.8) 25%, rgba(255, 255, 255, 0) 100%);">
		<div class="container py-5">
			<div class="row g-0">
				<div class="col-12 col-md-6 py-5">
					<div class="gsap-duration-2 gsap-slide-left scrollTrigger">
						<p class="text-uppercase text-brand fs-6">This is how it works.</p>
						<p class="fs-5 fw-6 ff-h">1. Find your legislators</p>
						<p class="fs-5 fw-6 ff-h">2. See their <span class="d-none d-md-inline fw-6 ff-h">Freedom </span>Score</p>
						<p class="fs-5 fw-6 ff-h">3. Hold them accountable</p>
					</div>
				</div>
				<div class="col-12 col-lg-6 px-4 px-lg-5"></div>
			</div>
		</div>
	</div>
</div>


<div id="home-stakes" class="container-fluid py-5 border-bottom bg-brand">
	<div class="container py-lg-5">
		<div class="row">
			<div class="col-12 col-lg-8 p-0 pb-3 pt-3 pe-lg-5">
				<p class="fs-6 fw-5 ff-h text-white pt-4 text-center text-lg-start">Knowing the score doesn't <span class="text-nowrap">just inform your vote.</span></p>
				<p class="fs-6 fw-6 ff-h text-white text-center text-lg-start">It changes what politicians <span class="text-nowrap">dare to do with theirs.</span></p>
			</div>
		</div>
	</div>
</div>


<div class="container-fluid py-5 bg-action-light-2">
	<div class="container">
		<div class="row">
			<div class="col-12">
				<p class="fs-4 text-brand text-center mt-5">Slogans lose power when <span class="text-nowrap">you know the score.</span></p>
				<p class="fs-3 fw-7 text-black text-center">Check Your <span class="text-nowrap">Legislators' Scores.</span></p>
				<form id="footer-legislator-search-form" class="mx-auto col-12 col-md-10 col-lg-9 col-xl-8 mt-4 mb-5 pb-5" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search" novalidate>
					<div class="input-group position-relative">
						<input id="footer-legislator-search-input" class="form-control form-control-lg fs-7 bg-white" name="fi_search" type="search" placeholder="Enter ZIP code or legislator name" value="" aria-label="Enter ZIP code" autocomplete="off" minlength="3">
						<div id="footer-search-suggestions" class="position-absolute bg-white border rounded shadow d-none" style="z-index: 1050; max-height: 400px; overflow-y: auto;"></div>
						<button id="footer-search-clear-btn" class="btn btn-warning p-2 d-none" type="button" aria-label="Clear search" title="Clear search">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
								<line x1="18" y1="6" x2="6" y2="18"></line>
								<line x1="6" y1="6" x2="18" y2="18"></line>
							</svg>
						</button>
						<button class="btn btn-action fw-4 fs-6" type="submit" aria-label="Search">
							<span class="d-none d-xl-inline">Find My Legislators</span>
							<span class="d-none d-lg-inline d-xl-none">Find Legislators</span>
							<span class="d-lg-none">Search</span>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<section class="container-fluid bg-primary text-light py-5">
	<div class="container">
		<div class="row">
			<?php foreach ($actions as $action): ?>
			<div class="col-12 col-md-4 py-3">
				<div class="card bg-primary border-white h-100">
					<div class="card-body">
						<div class="card-title text-white fw-bold fs-7"><?php echo $action['title']; ?></div>
						<p class="card-text text-white"><?php echo $action['desc']; ?></p>
					</div>
					<div class="card-footer p-0">
						<a href="<?php echo $action['link']; ?>" class="btn btn-primary rounded-0 rounded-bottom w-100"><?php echo $action['button_text']; ?> →</a>
					</div>
				</div>
            </div>
			<?php endforeach; ?>
		</div>
	</div>
</section>


<script>
const searchBox = document.getElementById('header-legislator-search-input');
// Define the Bootstrap md breakpoint rule (768px)
const mdBreakpoint = window.matchMedia('(min-width: 768px)');
function updatePlaceholder(e) {
	if (e.matches) {
		// Large screens
		searchBox.placeholder = "<?= FI_SEARCH_PLACEHOLDER;?>";
	} else {
		// Small screens
		searchBox.placeholder = "<?= FI_SEARCH_PLACEHOLDER_SMALL;?>";
	}
}
// Register listener for real-time window resizing
mdBreakpoint.addEventListener('change', updatePlaceholder);
// Initial check on page load
updatePlaceholder(mdBreakpoint);

document.addEventListener('DOMContentLoaded', function () {
  document.getElementById('header-legislator-search-input')?.focus();
});
</script>
<?php
get_footer();

/*
"Government gets bigger. Your life gets smaller."
<p class="fs-4 fw-6 mb-5 text-action text-center">Promises are easy. <span class="text-nowrap">Votes are proof.</span></p>

<p class="fs-6 fw-5">The Constitution was written to keep government small and your life big.</p>
<p class="fs-7">The <span class="fw-7">Freedom Score</span> tells you how often your legislators voted to protect your rights, wallet, country, and independence.</p>
<p class="fs-6 fw-7 text-brand">Scores are all backed by vote records.</p>
<p class="fs-6 fw-7 text-brand mt-4 text-center text-lg-start">Every score is backed by voting records.</p>





*/
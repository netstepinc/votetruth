<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
 * Front Page Template — Freedom Index Home v2
 * PEACE stands for Problem, Empathy, Answer, Change, and End Result.
 */
$debt_data = fi_api_treasurygov_debt_now(); 
$households = fi_api_census_households() ?: 134790000;
$debt_national = '$'.number_format(round( ($debt_data['amount'] / 1000000000000),2),2).' Trillion';
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

<div id="home-agitate" class="container-fluid py-5 border-bottom bg-white">
	<div class="container">
		<div class="row">
			<div class="col-12">
				<p class="fs-4 mb-4 text-center"><span class="text-nowrap">Every vote for bigger</span> <span class="text-nowrap">government touches</span> <span class="text-nowrap">your paycheck, your family,</span> <span class="text-nowrap">and your future.</span></p>
				<p class="fs-2 lh-1 fw-7 text-center text-red"><?= $debt_household ?></p>
				<p class="fs-6 lh-1 fw-6 text-center">U.S. National Debt Per Household</p>
			</div>
		</div>
	</div>
</div>

<div id="home-empathy" class="container-fluid py-5">
	<div class="container py-4">
		<p class="fs-5 text-white text-center mb-4">
			<span class="text-nowrap">We know it's frustrating </span>
			<span class="text-nowrap">to be ignored by the people </span> 
			<span class="text-nowrap">elected to represent us.</span>
		</p>
		<p class="fs-4 fw-6 text-center text-white">That's why we created</p>
		<p class="fs-2 fw-7 ff-h lh-1 text-center text-action">The Freedom Score</p>
	</div>
</div>

<div id="home-answer" class="container-fluid p-0 py-lg-5 border-bottom bg-action-light-1">
	<div class="container py-3">
		<div class="row g-0">
			<div class="col-12 col-md-6">
				<p class="text-uppercase text-brand fs-6 text-center text-lg-start">Promises are easy. <span class="text-nowrap">Votes are proof.</span></p>
				<p class="fs-6 fw-5 text-center text-lg-start mb-0">The <b>Freedom Score</b> shows whether your legislators voted to keep government small and your life big.</p>

				<div class="row g-0 py-4">
					<div class="col-4 p-2">
						<div class="fs-5 ff-h fw-7 lh-1 text-center text-brand"><?= $stats['tracked']; ?></div>
						<div class="fs-sub mt-1 text-secondary text-center text-uppercase">Legislators<span class="d-none d-lg-inline"> Scored</span></div>
					</div>
					<div class="col-4 p-2">
						<div class="fs-5 ff-h fw-7 lh-1 text-center text-brand"><?= $stats['scored']; ?></div>
						<div class="fs-sub mt-1 text-secondary text-center text-uppercase">Votes<span class="d-none d-lg-inline"> Rated</span></div>
					</div>
					<div class="col-4 p-2">
						<div class="fs-5 ff-h fw-7 lh-1 text-center text-brand"><?= $stats['counted']; ?></div>
						<div class="fs-sub mt-1 text-secondary text-center text-uppercase">Roll Calls<span class="d-none d-lg-inline"> Verified</span></div>
					</div>
				</div>

			</div>
			<div class="col-12 col-md-6 pb-md-0">
				<div id="home-score-scale" class="card rounded-4 mx-auto">
					<div class="card-header rounded-top-4 bg-brand">
						<div class="fs-7 fw-bold text-uppercase text-fade">Freedom Score</div>
					</div>
					<div class="card-body">
						<ul class="list-unstyled list-flush mb-0">
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
						<p class="fs-5 fw-6 ff-h">3. Be informed when you vote</p>
					</div>
				</div>
				<div class="col-12 col-lg-6 px-4 px-lg-5"></div>
			</div>
		</div>
	</div>
</div>

<div class="container-fluid py-5 bg-action-light-2">
	<div class="container py-5">
		<div class="row">
			<div class="col-12">
				<p class="fs-6 fw-6 text-center">Knowing the score doesn't <span class="text-nowrap">just inform your vote.</span></p>
				<p class="fs-6 fw-6 text-center">It changes what politicians <span class="text-nowrap">dare to do with theirs.</span></p>

				<p class="fs-4 fw-6 text-black text-center">Check Your <span class="text-nowrap">Legislators' Scores.</span></p>
				<form id="footer-legislator-search-form" class="mx-auto col-12 col-md-10 col-lg-9 col-xl-8 mt-4 mb-5" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search" novalidate>
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

<p class="fs-5 text-white text-center">We know how frustrating it is <span class="text-nowrap">to be ignored by the people</span> <span class="text-nowrap">elected to represent us.</span></p>

<p class="fs-6 fw-7 text-brand mt-4 text-center text-lg-start">Every score is backed by voting records.</p>

<p class="fs-6 fw-5">The Constitution was written to keep government small and your life big.</p>
<p class="fs-7">The <span class="fw-7">Freedom Score</span> tells you how often your legislators voted to protect your rights, wallet, country, and independence.</p>
<p class="fs-6 fw-7 text-brand">Scores are all backed by vote records.</p>
<p class="fs-6 fw-7 text-brand mt-4 text-center text-lg-start">Every score is backed by voting records.</p>

<p class="fs-4 text-brand text-center mt-5">Slogans lose power when <span class="text-nowrap">you know the score.</span></p>


*/
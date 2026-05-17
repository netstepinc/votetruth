<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$sessions = $args['sessions'] ?? [];

$gov = $args['gov'] ?? 'US';
$gov_slug = strtolower($gov);
$height = $args['height'] ?? FI_GOV_CARD_HEIGHT;

// Get session reports grouped by session
$session_reports = [];
foreach ($sessions as $session) {
	$session_reports_list = fi_reports_get([
		'session_id' => $session->id,
		'status' => 'publish',
		'orderby' => 'date_publish',
		'order' => 'DESC'
	]);
	$session_reports_list = fi_reports_sort_by_format($gov, $session_reports_list);
	$session_reports[$session->id] = $session_reports_list ?? [];
}

// Filter sessions to only show those with reports
$sessions_with_reports = array_filter($sessions, function($session) use ($session_reports) {
	return !empty($session_reports[$session->id]);
});
?>
<div class="card rounded-4 shadow h-100">
	<div class="card-header rounded-top-4 bg-white">
		<h2 class="card-title fs-4 mb-0 text-muted text-center">Vote Reports by Session</h2>	
	</div>
	<div class="card-body p-0">
		<?php if (!empty($sessions_with_reports)): ?>
		<!-- Swiper Carousel -->
		<div class="swiper fi-sessions-swiper">
			<div class="swiper-wrapper">
				<?php foreach ($sessions_with_reports as $session): 
					$session_reports_list = $session_reports[$session->id] ?? [];
					// Build "All Votes" URL for this session - link to legislators list filtered by session
					$all_votes_url = home_url($gov_slug . '/legislators/');
					if (!empty($session->id)) {
						$all_votes_url = home_url($gov_slug . '/legislators/session/' . $session->id . '/');
					}
				?>
					<div class="swiper-slide">
						<div class="card h-100 border-0 rounded-bottom-4" style="min-height:<?php echo esc_attr($height); ?>; margin: 0 36px;">
							<div class="card-header bg-white border-bottom">
								<h3 class="card-title fs-5 mb-0 text-center"><?php echo esc_html($session->name ?? 'Unknown Session'); ?></h3>
							</div>
							<div class="card-body p-1">
								<a href="<?php echo esc_url($all_votes_url); ?>" class="btn btn-sm btn-outline-success fw-bold w-100 mb-2 fi-nav-item fs-7">
									View All Legislators
								</a>
							<?php foreach ($session_reports_list as $report): ?>
<?php //if(get_current_user_id() == 1){print_r($report);}?>
								<a href="<?php echo esc_url(fi_url_report($report->id ?? 0, $gov_slug)); ?>" class="btn btn-sm btn-outline-primary fw-bold w-100 mb-2 fi-nav-item fs-7">
								<?php 
								if(!empty($report->title_menu)){
									$title = $report->title_menu;
								} elseif(!empty($report->title)){
									$title = $report->title;
								} else {
									$title = 'Untitled Report';
								}
								echo esc_html($title);
								?>
								</a>
							<?php endforeach; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<!-- Navigation -->
			<div class="swiper-button-next"></div>
			<div class="swiper-button-prev"></div>
		</div>
		<?php else: ?>
		<div class="p-3">
			<p class="text-muted mb-0">No reports available</p>
		</div>
		<?php endif; ?>
	</div>
	<div class="card-footer p-0 bg-white border-top rounded-bottom-4">
		<a href="<?php echo esc_url(home_url($gov_slug . '/reports/')); ?>" class="btn btn-secondary w-100 fi-nav-item fs-7 rounded-top-0 rounded-bottom-4">
			View All Reports
		</a>
	</div>
</div>

<style>
/* Swiper Sessions Carousel */
.fi-sessions-swiper {
	position: relative;
	height: 100%;
	min-height: 400px;
}

.fi-sessions-swiper .swiper-slide {
	height: auto;
	display: flex;
}

.fi-sessions-swiper .swiper-slide .card {
	width: 100%;
}

.fi-sessions-swiper .swiper-button-next,
.fi-sessions-swiper .swiper-button-prev {
	color: white;
	background-color: var(--bs-primary);
	width: 30px;
	height: 30px;
	border-radius: 50%;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
	transition: all 0.3s ease;
}

.fi-sessions-swiper .swiper-button-prev {
	left: 4px;
}

.fi-sessions-swiper .swiper-button-next {
	right: 4px;
}

.fi-sessions-swiper .swiper-button-next:hover,
.fi-sessions-swiper .swiper-button-prev:hover {
	background-color: var(--bs-primary);
	color: white;
	transform: scale(1.1);
	opacity: 0.9;
}

.fi-sessions-swiper .swiper-button-next::after,
.fi-sessions-swiper .swiper-button-prev::after {
	font-size: 18px;
	font-weight: bold;
}

/* Mobile adjustments */
@media (max-width: 767.98px) {
	.fi-sessions-swiper {
		min-height: 350px;
	}
	
	.fi-sessions-swiper .swiper-button-next,
	.fi-sessions-swiper .swiper-button-prev {
		width: 35px;
		height: 35px;
	}
	
	.fi-sessions-swiper .swiper-button-next::after,
	.fi-sessions-swiper .swiper-button-prev::after {
		font-size: 16px;
	}
}
</style>

<script>
// Initialize Sessions Swiper
document.addEventListener('DOMContentLoaded', function() {
	if (typeof Swiper !== 'undefined') {
		new Swiper('.fi-sessions-swiper', {
			slidesPerView: 1,      // Always show 1 card
			spaceBetween: 0,
			navigation: {
				nextEl: '.swiper-button-next',
				prevEl: '.swiper-button-prev',
			},
			// Disable auto-scroll
			autoplay: false,
			// Enable touch/swipe
			touchEventsTarget: 'container',
			// No loop
			loop: false,
			// Fixed height - don't adapt to content
			autoHeight: false,
			// Allow slide to next/prev
			allowSlideNext: true,
			allowSlidePrev: true,
		});
	} else {
		console.warn('Swiper.js is not loaded. Sessions carousel will not function.');
	}
});
</script>

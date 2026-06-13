<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$legislators = $args['legislators'] ?? [];
$gov = $args['gov'] ?? null;
$chambers = fi_chamber_info($gov);

$senate_chamber_title = $chambers['S']['chamber'] ?? 'Senate';
$house_chamber_title = $chambers['H']['chamber'] ?? 'House';

// Check if government has both chambers (Nebraska is unicameral - only Senate)
$has_house = fi_government_has_house($gov);

// Calculate average scores using centralized helper functions
$avg_score_all = 0;
$avg_score_house = 0;
$avg_score_senate = 0;
$house_count = 0;
$senate_count = 0;
$all_count = 0;

// Party averages
$avg_score_by_party = [];
$party_counts = [];

if (!empty($legislators)) {
	// Calculate overall average
	$all_stats = fi_score_calculate_average($legislators);
	$avg_score_all = $all_stats['average'];
	$all_count = $all_stats['count'];
	
	// Calculate house average
	$house_stats = fi_score_calculate_average($legislators, 'chamber', 'H');
	$avg_score_house = $house_stats['average'];
	$house_count = $house_stats['count'];
	
	// Calculate senate average
	$senate_stats = fi_score_calculate_average($legislators, 'chamber', 'S');
	$avg_score_senate = $senate_stats['average'];
	$senate_count = $senate_stats['count'];
	
	// Calculate party averages using helper function
	$party_averages = fi_score_calculate_average_by_party($legislators);
	foreach ($party_averages as $party => $stats) {
		$avg_score_by_party[$party] = $stats['average'];
		$party_counts[$party] = $stats['count'];
	}
}

// Build score items array for horizontal display
$score_items = [];

// Overall (always show if we have any scores)
if ($all_count > 0) {
	$score_items[] = [
		'label' => 'Overall',
		'score' => $avg_score_all,
		'count' => $all_count
	];
}

// Senate (only if exists)
if ($senate_count > 0) {
	$score_items[] = [
		'label' => $senate_chamber_title,
		'score' => $avg_score_senate,
		'count' => $senate_count
	];
}

// House (only if exists)
if ($house_count > 0) {
	$score_items[] = [
		'label' => $house_chamber_title,
		'score' => $avg_score_house,
		'count' => $house_count
	];
}

// Party averages (prioritize R, D, I, then others)
$party_priority = ['R' => 1, 'D' => 2, 'I' => 3];
$party_order = [];
foreach ($avg_score_by_party as $party_code => $avg_score) {
	$priority = $party_priority[$party_code] ?? 999;
	$party_order[] = ['code' => $party_code, 'score' => $avg_score, 'priority' => $priority];
}
usort($party_order, function($a, $b) {
	if ($a['priority'] === $b['priority']) {
		return $b['score'] <=> $a['score']; // Descending by score if same priority
	}
	return $a['priority'] <=> $b['priority'];
});

// Add parties to score items
foreach ($party_order as $party_data) {
	$party_code = $party_data['code'];
	$party_name = fi_party_name($party_code) ?? $party_code;
	$score_items[] = [
		'label' => $party_name,
		'score' => $party_data['score'],
		'count' => $party_counts[$party_code] ?? 0
	];
}

// If no scores, show message
if (empty($score_items)) {
	$score_items[] = [
		'label' => 'No scores available',
		'score' => 0,
		'count' => 0,
		'empty' => true
	];
}
/*
?>
<!--
<div class="card rounded-4 shadow h-100 text-center bg-white">
	<div class="card-header rounded-top-4 bg-white">
		<h2 class="card-title fs-4 mb-0 text-center text-muted">Average Scores</h2>	
	</div>
	<div class="card-body py-3">
<div class="text-center fs-4 mb-0 text-muted">Average Scores</div>
-->
<?php*/ 
if (count($score_items) > 0):  //Hide if no scores
?>
		<!-- Swiper Carousel -->
		<div class="swiper fi-averages-swiper">
			<div class="swiper-wrapper">
				<?php foreach ($score_items as $item): ?>
					<?php if (empty($item['empty'])): ?>
					<div class="swiper-slide">
						<div class="d-flex flex-column align-items-center text-center">
							<div class="fi-score-donut shadow" data-score="<?php echo esc_attr($item['score']); ?>" data-size="100"></div>
							<div class="small fw-bold text-center text-muted mt-1"><?php echo esc_html($item['label']); ?></div><!-- <br>Average -->
						</div>
					</div>
					<?php else: ?>
					<div class="swiper-slide">
						<div class="d-flex align-items-center justify-content-center h-100">
							<p class="text-muted mb-0"><?php echo esc_html($item['label']); ?></p>
						</div>
					</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			
			<!-- Navigation arrows -->
			<?php if (count($score_items) > 1): ?>
			<div class="swiper-button-next"></div>
			<div class="swiper-button-prev"></div>
			<?php endif; ?>
		</div>
<!--	
</div>
</div>
-->

<style>
/* Donut chart styles - simple CSS-based donut */
.fi-score-donut {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  position: relative;
  background: conic-gradient(
    var(--bs-success) 0% calc(var(--score) * 1%),
    rgba(255,255,255,0.3) calc(var(--score) * 1%) 100%
  );
  font-weight: bold;
  font-size: 1.2rem;
  margin: 0 auto;
}

.fi-score-donut::before {
  content: attr(data-score) '%';
  position: absolute;
  background: var(--bs-primary);
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 0.9rem;
}

.fi-score-donut[data-size="125"] {
  width: 125px;
  height: 125px;
}

.fi-score-donut[data-size="125"]::before {
  width: 100px;
  height: 100px;
  font-size: 1.1rem;
}

/* Swiper customizations */
.fi-averages-swiper {
  padding: 0 50px;
  position: relative;
}

.fi-averages-swiper .swiper-slide {
  height: auto;
  display: flex;
  align-items: center;
  justify-content: center;
}



.fi-averages-swiper .swiper-button-prev {
  left: 4px;
}
.fi-averages-swiper .swiper-button-next {
  right: 4px;
}

.fi-averages-swiper .swiper-button-next,
.fi-averages-swiper .swiper-button-prev {
  color: white;
  background-color: var(--bs-primary);
  width: 40px;
  height: 40px;
  border-radius: 50%;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  transition: all 0.3s ease;
}

.fi-averages-swiper .swiper-button-next:hover,
.fi-averages-swiper .swiper-button-prev:hover {
  background-color: var(--bs-primary);
  color: white;
  transform: scale(1.1);
  opacity: 0.9;
}

.fi-averages-swiper .swiper-button-next::after,
.fi-averages-swiper .swiper-button-prev::after {
  font-size: 18px;
  font-weight: bold;
}

/* Mobile adjustments */
@media (max-width: 767.98px) {
  .fi-averages-swiper {
    padding: 0 40px;
  }
  
  .fi-averages-swiper .swiper-button-next,
  .fi-averages-swiper .swiper-button-prev {
    width: 35px;
    height: 35px;
  }
  
  .fi-averages-swiper .swiper-button-next::after,
  .fi-averages-swiper .swiper-button-prev::after {
    font-size: 16px;
  }
}
</style>

<script>
// Initialize donut charts and Swiper
document.addEventListener('DOMContentLoaded', function() {
  // Initialize donut charts
  const donuts = document.querySelectorAll('.fi-score-donut');
  donuts.forEach(function(donut) {
    const score = parseFloat(donut.getAttribute('data-score')) || 0;
    donut.style.setProperty('--score', score);
  });
  
  // Initialize Swiper if available
  if (typeof Swiper !== 'undefined') {
    new Swiper('.fi-averages-swiper', {
      slidesPerView: 2,      // Default: 2 slides on mobile (SM)
      spaceBetween: 20,
      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
      breakpoints: {
        // Small devices (landscape phones, 576px and up)
        576: {
          slidesPerView: 2,
          spaceBetween: 20,
        },
        // Medium devices (tablets, 768px and up)
        768: {
          slidesPerView: 4,
          spaceBetween: 24,
        },
        // Large devices (desktops, 992px and up)
        992: {
          slidesPerView: 6,
          spaceBetween: 24,
        },
      },
      // Disable auto-scroll
      autoplay: false,
      // Enable touch/swipe
      touchEventsTarget: 'container',
      // Loop if more slides than visible
      loop: false,
      // Allow slide to next/prev
      allowSlideNext: true,
      allowSlidePrev: true,
    });
  } else {
    console.warn('Swiper.js is not loaded. Carousel will not function.');
  }
});
</script>
<?php endif; //hide if no scores ?>
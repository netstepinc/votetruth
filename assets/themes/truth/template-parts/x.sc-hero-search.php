<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
Hero Section with Search Form
- Displays hero image/content initially
- Gets replaced by search results when user searches
*/
$hero_image_sm = STYLE_IMG . 'hero-sm2.jpg';
$hero_image_md = STYLE_IMG . 'hero-md.jpg';
$hero_image_lg = STYLE_IMG . 'hero-lg.jpg';
?>
<div id="hero-search-section" class="hero-search-section">
	<div class="container-fluid p-0 shadow">
		<div class="row g-0">
			<div class="col-12">
				<!-- Hero Image/Content -->
				<!-- Responsive heights: Mobile portrait (600px), Tablet/Desktop (400px) -->
				<div class="hero-image-wrapper position-relative" style="height: 600px; overflow: hidden;">
					<style>
						/* Tablet and Desktop: 400px height */
/*
						@media (min-width: 768px) {
							.hero-image-wrapper {
								height: 300px !important;
							}
						}
*/
					</style>
					<?php
					if ($hero_image_lg) {
						// Use picture element for responsive images
						echo '<picture>';
						// Mobile (up to 767px) - portrait format image (e.g., 400x600 or 500x750)
						if ($hero_image_sm) {
							echo '<source media="(max-width: 767px)" srcset="' . esc_url($hero_image_sm) . '">';
						}
						// Tablet (768px to 991px) - share image format (1200x630)
						if ($hero_image_md) {
							echo '<source media="(min-width: 768px) and (max-width: 991px)" srcset="' . esc_url($hero_image_md) . '">';
						}
						// Desktop (992px+) - full width landscape (1840x400)
						echo '<img src="' . esc_url($hero_image_lg) . '" alt="Hero" style="width: 100%; height: 100%; object-fit: cover; object-position: center;">';
						echo '</picture>';
					} else {
						// Fallback gradient or solid color
						echo '<div class="w-100 h-100 bg-primary d-flex align-items-center justify-content-center">';
						echo '<div class="text-center text-white p-4">';
						echo '<h1 class="display-3 fw-bold mb-3">Find Your Representatives</h1>';
						echo '<p class="lead fs-4">See how your legislators are rated on the Freedom Scorecard</p>';
						echo '</div>';
						echo '</div>';
					}
					?>
				</div>
			</div>
		</div>
	</div>
</div>
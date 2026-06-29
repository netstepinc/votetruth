<?php if(!defined('ABSPATH')) exit;


//QR Codes - Function so we can change the method if necessary in one place
//<img src="https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=' . $qr['url'] . '" alt="QR Code" style="width:50px; height:50px;">
function fi_qr_code($url, $size = 50,$alt = 'QR Code') {
	return '<img src="https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=' . $url . '" alt="' . esc_attr($alt) . '" style="width:'.$size.'px; height:'.$size.'px;">';
}


function fi_content_stats(): array {
	return [
		'tracked' => number_format(fi_legislators_count_all()),
		'scored'  => number_format((int) fi_votes_get(['count' => true, 'status' => 'publish'])),
		'counted' => number_format((int) fi_rollcalls_query(['count' => true])),
	];
}


/**
 * Get legislator image HTML
 * 
 * @param int|null $image_id Main legislator image ID
 * @param int|null $session_image_id Session-specific image ID
 * @param array $args Optional arguments
 * @return string Image HTML
 */
function fi_legislator_image(?int $image_id, ?int $session_image_id = null, array $args = []): string {
	$defaults = [
		'size' => [200, 250],
		'class' => 'img-fluid',
		'alt' => '',
		'image_url' => '',
	];
	$args = wp_parse_args($args, $defaults);

	//Do we have an image URL?...this tends to get stale. Use the image generator unless we really need more caching.
	/*
	//Get width and height from size
	$width = $args['size'][0];
	$height = $args['size'][1];

	if (!empty($args['image_url'])) {
		//We need to make sure this image exists
		return '<img src="' . esc_url($args['image_url']) . '" width="' . esc_attr($width) . '" height="' . esc_attr($height) . '" class="' . esc_attr($args['class']) . '" alt="' . esc_attr($args['alt']) . '">';
	}
	*/

	// Prefer session-specific image if available
	$use_image_id = $session_image_id ?: $image_id;
	
	if (!$use_image_id) {
		// Return placeholder
		return '<div class="legislator-placeholder ' . esc_attr($args['class']) . '"><i class="bi bi-person-fill"></i></div>';
	}

	//Use Custom Better Image Sizes method
	$image_html = sis_get_attachment_image($use_image_id, $args['size'], false, [
		'class' => $args['class'],
		'alt' => $args['alt'],
	]);

	//If that fails, try regular wp_get_attachment_image
	if (empty($image_html)) {
		$image_html = wp_get_attachment_image($use_image_id, $args['size'], false, [
			'class' => $args['class'],
			'alt' => $args['alt'],
		]);
	}

	//No Image placeholder
	if (empty($image_html)) {
		$image_html = '<div class="legislator-placeholder ' . esc_attr($args['class']) . '"><i class="bi bi-person-fill"></i></div>';
	}

	return $image_html;
}


/**
* Display score as progress bar
* 
* @param int|float $score Score percentage (0-100)
* @param string $title Title text to display (e.g., "Freedom Score", "Session Score")
* @param int $height Height in pixels (default: 32)
* @return string HTML for progress bar
*/
/*
function fi_score_bar($score, string $title = 'Score', int $height = 32): string {
	if ($score === null || $score === '') {
		return '';
	}
	
	$score = (int) round($score);
	
	// Get color class using shared function
	//Staff said to not color code the scores so disable the classes.
	//ONLY EVAL @ 90+
	//$barClass = 'bg-info '.fi_score_class_bg($score);
	//$textClass = 'text-dark'.fi_score_class_bg_text($score);
	$barClass = ($score >= 90) ? 'bg-success' : 'bg-info';	
	$textClass = ($score >= 90) ? 'text-white' : 'text-dark';

	$html = '<div class="bg-light position-relative">';
	$html .= '<div class="progress rounded-0" style="height: ' . esc_attr($height) . 'px;">';
	
	// Always display text as overlay to prevent cutoff on small displays
	$html .= '<div class="progress-bar progress-bar-striped progress-bar-animated ' . esc_attr($barClass) . '" role="progressbar" style="width: ' . esc_attr($score) . '%;" aria-valuenow="' . esc_attr($score) . '" aria-valuemin="0" aria-valuemax="100"></div>';
	$html .= '<span class="position-absolute top-50 start-0 translate-middle-y ms-2 ' . esc_attr($textClass) . ' fw-bold fs-8" style="z-index: 1;">' . esc_html($score) . ' ' . esc_html($title) . '</span>';
	
	$html .= '</div>';
	$html .= '</div>';
	
	return $html;
}
*/

/**
* Display score as CSS donut chart (responsive SVG-based)
* 
* @param int|float $good Good votes percentage (0-100) or total score if bad/none not provided
* @param string $title Title text to display (e.g., "Freedom Score", "Session Score")
* @param int|float|null $bad Bad votes percentage (optional - if provided, shows 3-color donut)
* @param int|float|null $none None/Not Scored votes percentage (optional - if provided, shows 3-color donut)
* @param int|string $size Size in pixels for donut (default: 120) or CSS value (e.g., "100%", "10vw", "clamp(80px, 15vw, 120px)")
* @param int|string $stroke_width Stroke width in pixels (default: 16) or percentage of size (e.g., "13%")
* @return string HTML for donut chart
*/
/*
function fi_score_donut(int $good, string $title = 'Score', ?int $bad = null, ?int $none = null, int|string $size = 120, int|string $stroke_width = 16): string {
	if ($good === null || $good === '') {
		return '';
	}
	
	$good = (float) $good;
	$bad = $bad !== null ? (float) $bad : null;
	$none = $none !== null ? (float) $none : null;
	
	// Determine if we're showing 3-color or single-color donut
	$show_breakdown = ($bad !== null && $bad > 0) || ($none !== null && $none > 0);
	
	// Normalize size to a base value for calculations (use 100 as base for viewBox)
	$base_size = 100;
	$size_css = is_numeric($size) ? $size . 'px' : $size;
	
	// Calculate stroke width as percentage of base_size for viewBox coordinates
	// Default: 16px on 120px = ~13.3% of base_size
	$stroke_pct = is_numeric($stroke_width) ? ($stroke_width / (is_numeric($size) ? $size : 120)) * 100 : (float) str_replace('%', '', $stroke_width);
	$stroke_width_css = is_numeric($stroke_width) ? $stroke_width . 'px' : $stroke_width;
	
	// Calculate radius in viewBox coordinates (center is 50, 50)
	$center = 50;
	$radius = $center - ($stroke_pct / 2);
	$circumference = 2 * M_PI * $radius;
	
	if ($show_breakdown) {
		// 3-color donut: Good (green), Bad (red), None (gray)
		$bad = $bad ?? 0;
		$none = $none ?? 0;
		$total = $good + $bad + $none;
		
		if ($total <= 0) {
			return '';
		}
		
		// Normalize to 100%
		$good_pct = ($good / $total) * 100;
		$bad_pct = ($bad / $total) * 100;
		$none_pct = ($none / $total) * 100;
		
		$good_dash = ($good_pct / 100) * $circumference;
		$bad_dash = ($bad_pct / 100) * $circumference;
		$none_dash = ($none_pct / 100) * $circumference;
		
		// Starting positions for each segment
		$good_offset = 0;
		$bad_offset = $good_dash;
		$none_offset = $good_dash + $bad_dash;
		
		$html = '<div class="fi-score-donut" style="width: ' . esc_attr($size_css) . '; max-width: 100%; height: auto; position: relative; display: inline-block; aspect-ratio: 1 / 1;">';
		$html .= '<svg viewBox="0 0 100 100" preserveAspectRatio="xMidYMid meet" style="width: 100%; height: 100%; transform: rotate(-90deg); display: block;">';
		
		// Good votes (darker red)
		if ($good_pct > 0) {
			$html .= '<circle cx="' . esc_attr($center) . '" cy="' . esc_attr($center) . '" r="' . esc_attr($radius) . '" fill="none" stroke="#761318" stroke-width="' . esc_attr($stroke_pct) . '" stroke-dasharray="' . esc_attr($good_dash) . ' ' . esc_attr($circumference) . '" stroke-dashoffset="' . esc_attr($circumference - $good_offset) . '" />';
		}
		
		// Bad votes (darker blue)
		if ($bad_pct > 0) {
			$html .= '<circle cx="' . esc_attr($center) . '" cy="' . esc_attr($center) . '" r="' . esc_attr($radius) . '" fill="none" stroke="#02275D" stroke-width="' . esc_attr($stroke_pct) . '" stroke-dasharray="' . esc_attr($bad_dash) . ' ' . esc_attr($circumference) . '" stroke-dashoffset="' . esc_attr($circumference - $bad_offset) . '" />';
		}
		
		// None/Not Scored (gray)
		if ($none_pct > 0) {
			$html .= '<circle cx="' . esc_attr($center) . '" cy="' . esc_attr($center) . '" r="' . esc_attr($radius) . '" fill="none" stroke="#e9ecef" stroke-width="' . esc_attr($stroke_pct) . '" stroke-dasharray="' . esc_attr($none_dash) . ' ' . esc_attr($circumference) . '" stroke-dashoffset="' . esc_attr($circumference - $none_offset) . '" />';
		}
		
		$html .= '</svg>';
		
		// Center text with score
		$score = round(($good / $total) * 100, 0);
		$html .= '<div class="position-absolute top-50 start-50 translate-middle text-center" style="transform: translate(-50%, -50%); width: 100%;">';
		$html .= '<div class="fi_donut_score">' . esc_html($score) . '</div>';
		$html .= '<div class="fi_donut_score_subtext">' . str_replace(' ','<br>',esc_html($title)) . '</div>';
		$html .= '</div>';
		
		$html .= '</div>';
	} else {
		// Single-color donut: Good (colored) + remainder (light gray)
		$good_pct = min(100, max(0, $good)); // Ensure 0-100
		
		// Get color using shared function
		//Staff said to not color code the scores
		$color = 'var('.fi_score_css_var($good_pct).')';  //'#0d6efd';

		$good_dash = ($good_pct / 100) * $circumference;
		$remainder_dash = $circumference - $good_dash;
		
		$html = '<div class="fi-score-donut" style="width: ' . esc_attr($size_css) . '; max-width: 100%; height: auto; position: relative; display: inline-block; aspect-ratio: 1 / 1;">';
		$html .= '<svg viewBox="0 0 100 100" preserveAspectRatio="xMidYMid meet" style="width: 100%; height: 100%; transform: rotate(-90deg); display: block;">';
		
		// Good/Score portion (colored)
		$html .= '<circle cx="' . esc_attr($center) . '" cy="' . esc_attr($center) . '" r="' . esc_attr($radius) . '" fill="none" stroke="' . esc_attr($color) . '" stroke-width="' . esc_attr($stroke_pct) . '" stroke-dasharray="' . esc_attr($good_dash) . ' ' . esc_attr($circumference) . '" stroke-dashoffset="0" />';
		
		// Remainder (light gray)
		if ($remainder_dash > 0) {
			$html .= '<circle cx="' . esc_attr($center) . '" cy="' . esc_attr($center) . '" r="' . esc_attr($radius) . '" fill="none" stroke="#e9ecef" stroke-width="' . esc_attr($stroke_pct) . '" stroke-dasharray="' . esc_attr($remainder_dash) . ' ' . esc_attr($circumference) . '" stroke-dashoffset="' . esc_attr(-$good_dash) . '" />';
		}
		
		$html .= '</svg>';
		
		// Center text with score
		$html .= '<div class="position-absolute top-50 start-50 translate-middle text-center" style="transform: translate(-50%, -50%); width: 100%;">';
		$html .= '<div class="fi_donut_score">' . esc_html(round($good_pct, 0)) . '</div>';
		$html .= '<div class="fi_donut_score_subtext">' . str_replace(' ','<br>',esc_html($title)) . '</div>';
		$html .= '</div>';
		
		$html .= '</div>';
	}
	
	return $html;
}
*/
<?php
/**
 * Plugin Name: FI Custom SEO
 * Description: Lightweight per-page SEO meta tag control.
 * Version:     1.0
 * Author:      Your Name
 */

if (!defined('ABSPATH')) exit;

/**
 * Get or update the current page SEO data store.
 *
 * This replaces the former FICore\SEO::$data static property.
 *
 * @param array|null $new_data Optional replacement data.
 * @return array Current SEO data.
 */
function fi_seo_data_store(?array $new_data = null): array {
	static $data = [
		'title'       => '',
		'description' => '',
		'canonical'   => '',
		'robots'      => '',
		'og'          => [],
		'twitter'     => [],
	];

	if ($new_data !== null) {
		$data = wp_parse_args($new_data, [
			'title'       => '',
			'description' => '',
			'canonical'   => '',
			'robots'      => '',
			'og'          => [],
			'twitter'     => [],
		]);
	}

	return $data;
}

/**
 * Initialize FI custom SEO hooks.
 *
 * @return void
 */
function fi_seo_init(): void {
	add_action('template_redirect', 'fi_seo_prepare_data');

	add_filter('pre_get_document_title', 'fi_seo_filter_title', 99);
	add_filter('document_title_parts', 'fi_seo_filter_title_parts', 99);

	add_action('wp_head', 'fi_seo_output_tags', 1);
}

/**
 * Runs once per page before template output.
 *
 * Placeholder for future auto-detection. Manual usage should call fi_seo_tags()
 * before wp_head runs.
 *
 * @return void
 */
function fi_seo_prepare_data(): void {
	$data = fi_seo_data_store();

	if (empty($data['title']) && !doing_filter('wp_head')) {
		return;
	}
}

/**
 * Filter the final document title.
 *
 * @param string $title Existing title.
 * @return string Filtered title.
 */
function fi_seo_filter_title($title): string {
	$data = fi_seo_data_store();

	return !empty($data['title']) ? (string) $data['title'] : (string) $title;
}

/**
 * Filter document title parts.
 *
 * @param array $parts Existing title parts.
 * @return array Filtered title parts.
 */
function fi_seo_filter_title_parts($parts): array {
	$data = fi_seo_data_store();

	if (!empty($data['title'])) {
		$parts['title'] = (string) $data['title'];
	}

	return $parts;
}

/**
 * Output SEO meta tags in wp_head.
 *
 * @return void
 */
function fi_seo_output_tags(): void {
	$data = fi_seo_data_store();

	if (!empty($data['title'])) {
		echo '<title>' . esc_html($data['title']) . '</title>' . PHP_EOL;
	}

	if (!empty($data['description'])) {
		echo '<meta name="description" content="' . esc_attr($data['description']) . '">' . PHP_EOL;
	}

	if (!empty($data['robots'])) {
		echo '<meta name="robots" content="' . esc_attr($data['robots']) . '">' . PHP_EOL;
	}

	if (!empty($data['canonical'])) {
		echo '<link rel="canonical" href="' . esc_url($data['canonical']) . '">' . PHP_EOL;
	}

	if (!is_array($data['og'])) {
		$data['og'] = [];
	}

	if (defined('FI_SHARE_IMAGE') && empty($data['og']['og:image'])) {
		$data['og']['og:image'] = FI_SHARE_IMAGE;
	}

	foreach ($data['og'] as $property => $content) {
		if ($content) {
			echo '<meta property="' . esc_attr($property) . '" content="' . esc_attr($content) . '">' . PHP_EOL;
		}
	}

	if (!is_array($data['twitter'])) {
		$data['twitter'] = [];
	}

	if (defined('FI_SHARE_IMAGE') && empty($data['twitter']['twitter:image'])) {
		$data['twitter']['twitter:image'] = FI_SHARE_IMAGE;
	}

	foreach ($data['twitter'] as $name => $content) {
		if ($content) {
			echo '<meta name="' . esc_attr($name) . '" content="' . esc_attr($content) . '">' . PHP_EOL;
		}
	}
}

/**
 * Set SEO data for the current page.
 *
 * Call this anywhere before wp_head().
 *
 * @param array $args {
 *     SEO arguments.
 *
 *     @type string $title       Page title.
 *     @type string $description Meta description.
 *     @type string $canonical   Canonical URL.
 *     @type string $robots      Robots value, e.g. noindex,nofollow.
 *     @type array  $og          Open Graph tags, property => content.
 *     @type array  $twitter     Twitter card tags, name => content.
 * }
 * @return void
 */
function fi_seo_tags($args = []): void {
	$defaults = [
		'title'       => '',
		'description' => '',
		'canonical'   => '',
		'robots'      => '',
		'og'          => [],
		'twitter'     => [],
	];

	$args = wp_parse_args((array) $args, $defaults);

	if (empty($args['canonical']) && is_singular()) {
		$args['canonical'] = get_permalink();
	}

	fi_seo_data_store($args);

	if (!empty($args['title'])) {
		add_filter('pre_get_document_title', static function () use ($args) {
			return (string) $args['title'];
		}, 99);
	}

	// Disable Yoast output on pages where this custom SEO helper is used before wp_head runs.
	add_filter('wpseo_frontend_presenters', '__return_empty_array', 99);
}



fi_seo_init();

/*
Example usage:

fi_seo_tags([
	'title'       => 'My Custom Page Title | Brand Name',
	'description' => 'This is a custom meta description set dynamically.',
	'canonical'   => 'https://example.com/my-page/',
	'robots'      => 'index, follow',
	'og' => [
		'og:title'       => 'My Custom Page Title | Brand Name',
		'og:description' => 'This is a custom meta description set dynamically.',
		'og:url'         => 'https://example.com/my-page/',
		'og:type'        => 'article',
		'og:image'       => 'https://example.com/og-image.jpg',
	],
	'twitter' => [
		'twitter:card'        => 'summary_large_image',
		'twitter:title'       => 'My Custom Page Title',
		'twitter:description' => 'Custom description for Twitter',
		'twitter:image'       => 'https://example.com/og-image.jpg',
	],
]);
*/
<?php if(!defined('ABSPATH')) exit;

// Get RSS feed URL based on gov
if(isset($args['url']) && !empty($args['url'])){
	$rss_url = $args['url'];
	// Fetch and parse RSS feed
	$rss = fetch_feed($rss_url);
	if (!is_wp_error($rss)){
		$max_items = $rss->get_item_quantity(5);
		$rss_items = $rss->get_items(0, $max_items);
		if (!empty($rss_items)){
			foreach ($rss_items as $item){
				$title = $item->get_title();
				$link = $item->get_link();
				$date = $item->get_date('M j, Y');
				$description = $item->get_description();
				$thumbnail = '';
				
				// Try to extract thumbnail from description
				if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $description, $matches)) {
					$thumbnail = $matches[1];
				}
				$thumbnail_tag = '';
				if ($thumbnail){
					$thumbnail_tag = '<img src="' . esc_url($thumbnail) . '" alt="" class="rounded me-2" style="width: 60px; height: 60px; object-fit: cover;">';
				}
				$title = '<a href="' . esc_url($link) . '" target="_blank" rel="noopener" class="text-dark text-decoration-none">' . esc_html($title) . '</a>';
				$date = ($date) ? '<small class="text-muted">' . esc_html($date) . '</small>' : '';
				?>
<div class="list-group-item px-0 py-2 border-bottom bg-transparent">
	<div class="d-flex align-items-start">
		<?= $thumbnail_tag; ?><div class="flex-grow-1"><h5 class="mb-1"><?= $title; ?></h5><?= $date; ?></div>
	</div>
</div>
			<?php
			}
		}
	}
}
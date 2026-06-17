<?php
if (!defined('ABSPATH')) exit;

/**
 * Generate breadcrumb navigation HTML
 * 
 * @param array $items Array of breadcrumb items. Each item can be:
 *   - string: Text only (active item)
 *   - array: ['text' => 'Label', 'url' => 'URL'] for linked items
 * @param array $args Optional arguments:
 *   - 'gov' => Government code (auto-adds gov link if provided)
 *   - 'gov_name' => Government name (used if gov provided)
 *   - 'class' => Additional CSS classes
 *   - 'aria_label' => ARIA label (default: 'breadcrumb')
 *   - 'buttons' => Array of button arrays, each with 'text', 'url', and optional 'class'
 * @return string HTML breadcrumb navigation
 */
function fi_breadcrumbs($items = array(), $args = array()) {
    $defaults = array(
        'gov' => null,
        'gov_name' => null,
        'class' => 'mb-2',
        'aria_label' => 'breadcrumb',
        'buttons' => array()
    );
    
    $args = wp_parse_args($args, $defaults);

    // Check for help file and add help button
    $template_name = $args['template_name'] ?? fi_get_current_template_name();
    if ($template_name) {
        $help_link = fi_get_help_button($template_name);
    } else {
        $help_link = '';
    }

    // Build breadcrumb items array
    $breadcrumb_items = array();
    
    // Always start with Home
    $breadcrumb_items[] = array(
        'text' => 'Home',
        'url' => home_url('/'),
		'class' => '',
    );
    
    // Add government link if gov provided
    if ($args['gov']) {
        $gov_name = $args['gov_name'] ?: fi_gov_name($args['gov']);
        $breadcrumb_items[] = array(
            'text' => $gov_name,
            'url' => home_url('/' . strtolower($args['gov']) . '/')
        );
    }
    
    // Add custom items
    foreach ($items as $item) {
        if (is_string($item)) {
            // String = active item (no link)
            $breadcrumb_items[] = array(
                'text' => $item,
                'url' => null
            );
        } elseif (is_array($item)) {
            // Array = linked item
            $breadcrumb_items[] = array(
                'text' => $item['text'] ?? '',
                'url' => $item['url'] ?? null
            );
        }
    }
    
    // Build breadcrumb list HTML
    $breadcrumb_list = '<ol class="breadcrumb mb-0 d-flex flex-wrap align-items-center">';
    
    foreach ($breadcrumb_items as $index => $item) {
        $is_last = ($index === count($breadcrumb_items) - 1);
		$item_class = 'breadcrumb-item'. (isset($item['class']) ? ' ' . $item['class'] : '' );
        
        if ($is_last || !$item['url']) {
            // Active item (last or no URL)
            $breadcrumb_list .= '<li class="' . $item_class . ' active" aria-current="page">';
            $breadcrumb_list .= esc_html($item['text']);
            $breadcrumb_list .= '</li>';
        } else {
            // Linked item
            $breadcrumb_list .= '<li class="' . $item_class.'">';
            $breadcrumb_list .= '<a href="' . esc_url($item['url']) . '">';
            $breadcrumb_list .= esc_html($item['text']);
            $breadcrumb_list .= '</a>';
            $breadcrumb_list .= '</li>';
        }
    }

	// Inline secondary nav links (pipe-separated, appended to breadcrumb trail)
	$nav_links = $args['nav_links'] ?? [];
	if (!empty($nav_links)) {
		$breadcrumb_list .= '<li class="fi-nav-divider text-muted mx-2" aria-hidden="true">|</li>';
		foreach ($nav_links as $i => $link) {
			if ($i > 0) {
				$breadcrumb_list .= '<li class="fi-nav-divider text-muted mx-1" aria-hidden="true">|</li>';
			}
			$breadcrumb_list .= '<li class="fi-nav-link">';
			if (!empty($link['url'])) {
				$breadcrumb_list .= '<a href="' . esc_url($link['url']) . '" class="fw-semibold text-decoration-none">' . esc_html($link['text']) . '</a>';
			} else {
				$breadcrumb_list .= '<span class="text-muted">' . esc_html($link['text']) . '</span>';
			}
			$breadcrumb_list .= '</li>';
		}
	}

	// Right-aligned action buttons (help link, PDF, etc.)
/* Probably will remove this
	$buttons = $args['buttons'] ?? array();
	$has_buttons = !empty($buttons) || !empty($help_link);

	if ($has_buttons) {
		$breadcrumb_list .= '<li class="flex-fill text-end">';
		$breadcrumb_list .= '<div class="btn-group" role="group" aria-label="Page actions">';
		
		foreach ($buttons as $button) {
			$button_text = $button['text'] ?? '';
			$button_url = $button['url'] ?? '#';
			$button_class = $button['class'] ?? 'btn-primary';
			$button_target = '';
			if (isset($button['icon'])) {
				$button_text = '<i class="fas fa-chevron-right me-2"></i>' . $button_text;
			}
			if (isset($button['target'])) {
				$button_target = ' target="' . esc_attr($button['target']) . '"';
			}
			$breadcrumb_list .= '<a href="' . esc_url($button_url) . '" class="btn btn-sm ' . esc_attr($button_class) . ' ff-h fs-7"' . $button_target . '>' . esc_html($button_text) . '</a>';
		}
		
		if ($help_link) {
			$breadcrumb_list .= $help_link;
		}
		
		$breadcrumb_list .= '</div>';
		$breadcrumb_list .= '</li>';
	}
*/
    $breadcrumb_list .= '</ol>';
    
    // Add share link for legislators page with filters
    global $fi_entity;
    $current_entity = get_query_var('fi_entity') ?: ($fi_entity ?? '');
    $is_legislators_page = ($current_entity === 'legislators');
    
    // Check if there are active filters
    $has_filters = false;
    if ($is_legislators_page) {
        //SESSIONSLUG: Change to get_query_var('fi_session_id') and check if numeric
        $session_id = get_query_var('fi_session_id') ?: '';
        $party_slug = get_query_var('fi_party_slug') ?: '';
        $chamber = get_query_var('fi_chamber') ?: '';
        $search = get_query_var('fi_search') ?: '';
        $state = get_query_var('fi_state') ?: '';
        $has_filters = !empty($session_id) || !empty($party_slug) || !empty($chamber) || !empty($search) || !empty($state);
    }
    
    // Check for help file and add help button
    $template_name = $args['template_name'] ?? fi_get_current_template_name();
    
    // Build final HTML using Bootstrap row/col for responsive layout
    $html = '<nav aria-label="' . esc_attr($args['aria_label']) . '" class="' . esc_attr($args['class']) . '">';
	$html .= '<div class="row">';
	$html .= '<div class="col-12">' . $breadcrumb_list . '</div>';
	$html .= '</div>';
    $html .= '</nav>';
    
    // Add JavaScript for share button if needed
    if ($is_legislators_page && $has_filters) {
        ob_start();
        ?>
<script>
jQuery(document).ready(function($) {
	$("#fi-breadcrumb-share").on("click", function() {
		var url = window.location.href;
		if (navigator.clipboard) {
			navigator.clipboard.writeText(url).then(function() {
				$(this).html('<i class="fas fa-check"></i>');
				var btn = $(this);
				setTimeout(function() {
					btn.html('<i class="fas fa-share-alt"></i>');
				}, 2000);
			}.bind(this));
		} else {
			var tempInput = $('<input>').val(url).appendTo('body').select();
			document.execCommand('copy');
			tempInput.remove();
			$(this).html('<i class="fas fa-check"></i>');
			var btn = $(this);
			setTimeout(function() {
				btn.html('<i class="fas fa-share-alt"></i>');
			}, 2000);
		}
	});
});
</script>
        <?php
        $html .= ob_get_clean();
    }
    
    return $html;
}
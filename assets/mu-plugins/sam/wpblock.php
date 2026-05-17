<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Override WP Block classes if user specifies class
 */

/* Override Block Classes
IF custom CSS classes are added to a block, remove WP default classes to prevent WP blocks from overrideing BS grids and other classes.
Merge Bootstrap classes with WP classes
Add animation classes and data-animation attributes
*/


/*What it does:
First preg_replace_callback: Detects <figure><a><img></a></figure> and injects the extra classes into the img tag, preserving the <a>.
Second fallback regex: Handles the case where there’s no link—just <figure><img></figure>.
Both remove <figure> entirely, since you're aiming for Bootstrap compatibility (optional—let me know if you want to keep it).
*/
function wpblock_classes_override($block_content, $block) {
    $block_types = [
        'core/group',
        'core/columns',
        'core/column',
        'core/image',
        'core/paragraph',
        'core/heading',
    ];

    if (in_array($block['blockName'], $block_types)) {
        if ($block['blockName'] === 'core/image') {
            if (!empty($block['attrs']['className'])) {
                $additional_classes = esc_attr($block['attrs']['className']);

                // Handle image inside <figure> and possibly wrapped in <a>
                $block_content = preg_replace_callback(
                    '/<figure[^>]*>(.*?)<a([^>]*)><img([^>]*)class="([^"]*)"([^>]*)><\/a>(.*?)<\/figure>/s',
                    function ($matches) use ($additional_classes) {
                        return '<a' . $matches[2] . '><img' . $matches[3] . ' class="' . $matches[4] . ' ' . $additional_classes . '"' . $matches[5] . '></a>';
                    },
                    $block_content
                );

                // Fallback if there's no <a>, just <img> inside figure
                $block_content = preg_replace_callback(
                    '/<figure[^>]*>(.*?)<img([^>]*)class="([^"]*)"([^>]*)>(.*?)<\/figure>/s',
                    function ($matches) use ($additional_classes) {
                        return '<img' . $matches[2] . ' class="' . $matches[3] . ' ' . $additional_classes . '"' . $matches[4] . '>';
                    },
                    $block_content
                );
            }
        } else {
            if (!empty($block['attrs']['className'])) {
                $additional_classes = esc_attr($block['attrs']['className']);
                //Replaces first class="..." anywhere in block content, hits children if wrapper has no class.
                $block_content = preg_replace('/class="[^"]*"/', 'class="' . $additional_classes . '"', $block_content, 1);

                // Capture only the opening wrapper tag, then strip class and style within it
/* TODO: Test this
                $block_content = preg_replace_callback('/^(\s*<\w+)([^>]*)(>)/s', function($m) use ($additional_classes) {
                    $attrs = $m[2];
                    $attrs = preg_replace('/\s*class="[^"]*"/', '', $attrs);
                    $attrs = preg_replace('/\s*style="[^"]*"/', '', $attrs);
                    return $m[1] . ' class="' . $additional_classes . '"' . $attrs . $m[3];
                }, $block_content, 1);
*/

/* BAD: Removes style from first match in entire block content, hits children if wrapper has no style.
                if ($block['blockName'] === 'core/column') {
                    $block_content = preg_replace('/\s*style="[^"]*"/', '', $block_content, 1);
                }
*/
            }
        }
    }

    return $block_content;
}
add_filter('render_block', 'wpblock_classes_override', 10, 2);


/*
function wpblock_classes_override($block_content, $block) {
    $block_types = [
        'core/group',
        'core/columns',
        'core/column',
        'core/image',
        'core/paragraph',
        'core/heading',
    ];

    if (in_array($block['blockName'], $block_types)) {
        if ($block['blockName'] === 'core/image') {
            // Check if the block has additional CSS classes
            if (!empty($block['attrs']['className'])) {
                $additional_classes = esc_attr($block['attrs']['className']);

                // Move class from figure to img and remove figure
                $block_content = preg_replace_callback(
                    '/<figure[^>]*>(.*?)<img([^>]*)class="([^"]*)"([^>]*)>(.*?)<\/figure>/s',
                    function ($matches) use ($additional_classes) {
                        return '<img ' . $matches[2] . ' class="' . $matches[3] . ' ' . $additional_classes . '" ' . $matches[4] . '>';
                    },
                    $block_content
                );
            }
        } else {
            if (!empty($block['attrs']['className'])) {
                $additional_classes = esc_attr($block['attrs']['className']);
                $block_content = preg_replace('/class="[^"]*"/', 'class="' . $additional_classes . '"', $block_content, 1);
            }
        }
    }

    return $block_content;
}
add_filter('render_block', 'wpblock_classes_override', 10, 2);
*/


//Enqueue Bootstrap5 custom block scripts and styles
//Enqueue GSAP (GreenSock Animation Platform) block manager
function enqueue_block_bootstrap_classes() {
    if (is_admin()) { // Double-check it's in the admin area
        wp_enqueue_script(
            'block-features',
            URL_SAM_JS . 'block-controls.js', // Adjust path as needed
            array('wp-blocks', 'wp-editor', 'wp-element', 'wp-components', 'wp-data'),
            filemtime(DIR_SAM_JS . 'block-controls.js'),
            true
        );
    }
}
add_action('enqueue_block_editor_assets', 'enqueue_block_bootstrap_classes');
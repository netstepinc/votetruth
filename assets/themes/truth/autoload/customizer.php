<?php
if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.
/*****
 * by Sam Mittelstaedt 
*****/

function vttt_customizer_styles() {
    //do_action( 'customizer_library_styles' );
    //$css = Customizer_Library_Styles()->build();
	ob_start();
	?>
<style id="vttt-css">
<?php //echo $css;C52029?>
:root {
	--bs-font-headings:"Barlow","Roboto Condensed", "Roboto", Helvetica, Arial, sans-serif; /* headings & hero */
	--bs-font-primary:"DM Sans", "Roboto Condensed", "Roboto", Helvetica, Arial, sans-serif; /*DM Sans 400 body copy*/
	--bs-font-secondary: var(--bs-font-primary);
	--bs-font-sans-serif: var(--bs-font-primary);

	--bs-text-color:#111111;
	--bs-red: #c41425;
    --bs-red-rgb: 196, 20, 37;
    --bs-reddk: #761318;
    --bs-reddk-rgb: 118, 19, 24;
    --bs-blue: #0055a4;	/* Primary blue #0055a4 */
    --bs-blue-rgb: 0, 85, 164;
    --bs-bluedk: #02275D;	/* Anchor */
    --bs-bluedk-rgb: 2, 39, 93;
	--bs-green: #146c43;
	--bs-green-rgb: 20, 108, 67;
	--bs-greendk:#157347;
	--bs-greendk-rgb: 21, 115, 71;
	--bs-orange: #E8934A;	/* Amber accent #E8934A */
	--bs-orange-rgb: 232, 147, 74;
    --bs-yellow: #F5C87A;	/* Amber light #F5C87A */
    --bs-yellow-rgb: 245, 200, 122;
	--bs-warm: #F8F5F0;		/* Warm white #F8F5F0 */
	--bs-warm-rgb: 248, 245, 240;
    --bs-primary: #0055a4;	/* Primary blue #0055a4 */
    --bs-primary-rgb: 0, 85, 164;
    --bs-secondary: #666666;
    --bs-secondary-rgb: 102, 102, 102;
	--bs-dark: #333333;
	--bs-dark-rgb: 51, 51, 51;
	--bs-teal: #087990;
	--bs-teal-rgb: 8, 121, 144;
	--bs-navlink-color-hover: #333;
	--bs-info: #0d6efd;

    --bs-primary-text: var(--bs-blue);
    --bs-secondary-text: var(--bs-secondary);
    --bs-success-text: var(--bs-green);
    --bs-info-text: var(--bs-teal);
    --bs-warning-text: var(--bs-orange);
	--bs-caution-text: var(--bs-orange);
    --bs-danger-text: var(--bs-red);
    --bs-light-text: var(--bs-secondary);
    --bs-dark-text: var(--bs-dark);
	

    --bs-footer-custom: rgb(var(--bs-bluedk-rgb));
    --bs-nav-custom: var(--bs-blue);
	--bs-navlink-color: var(--bs-blue);
	--bs-link-color: var(--bs-primary);
    --bs-link-hover-color: var(--bs-dark);
    --bs-border-width: 1px;
    --bs-border-style: solid;
    --bs-breakpoint-mobile-max: 991.98px;
    --bs-navbar-nav-link-padding-x: 0.6rem;
    --bs-navbar-nav-link-padding-y: 0.3rem;
    --bs-navbar-toggler-padding-y: 0.1rem;
    --bs-nav-link-padding-x: 0;
    --bs-nav-link-padding-y: 0.1em;
    --bs-nav-link-font-size: 1em;
    --bs-header-navitem-border: 1px solid #000;
	--bs-header-navitem-border-hover: 1px solid #ccc;
	--bs-header-navitem-bg-hover: var(--bs-red);
    --bs-header-background: var(--bs-bluedk); /* linear-gradient(90deg, rgb(255,255,255), rgb(255,255,255), var(--bs-red-rgb), var(--bs-blue-rgb));  Changed CSS syntax: 90deg is standard for left-to-right, use commas for color stops. */
    --bs-header-background-hover: var(--bs-blue);
    --bs-header-background-sticky: var(--bs-bluedk);
    --bs-main-menu-item-hover: var(--bs-blue);
	--bs-main-menu-mobile-border: 1px solid #000;
    --bs-footer-bg: var(--bs-bluedk);
    --footer-copyright-bg: #000;
    --bs-cr-border: 1px solid var(--bs-reddk);
    --bs-btn-success-bg: var(--bs-success);
    --bs-btn-success-bg-hover: var(--bs-secondary);
    --bs-btn-primary-bg: var(--bs-primary);
    --bs-btn-primary-bg-hover: var(--bs-secondary);
	--bs-body-color: var(--bs-text-color);
	--post-color: var(--bs-text-color);;
	--bs-post-color: var(--bs-text-color);;
	--bs-post-color-hover: var(--bs-gray-700); 
	--bs-card-spacer: 1rem;

	/* Grade scale — functional, not brand */
	--fi-g-a: #1d7a45;
	--fi-g-b: #5aab6b;
	--fi-g-c: #D4B000;
	--fi-g-d: #c95e1a;
	--fi-g-f: #bf2b2b;

	--fi-navy: #02275D; /*  #002b62; */

	--map-bg: #F5C87A;
	--map-bg-hover: #E8934A;
	--map-bg-active: #D6813D;
	--map-text-hover: #F8F5F0;
	--map-text: #333;
	--map-border: #333;
}
<?php
//Load Customizer Styles
if(function_exists('vttt_load_css')){
	vttt_load_css(STYLE_DIR.'/assets/customizer/');
}else{
	echo "\n/*Customizer Loading Failed*/\n";
}

?>
</style>
<?php
	$css = ob_get_clean();
	$css = vttt_css_inline_minify($css);
    echo "\n".$css."\n";
}
add_action( 'wp_head', 'vttt_customizer_styles', 11 );


function vttt_load_css($cssdir){
    if(is_dir($cssdir)){
        $files = glob($cssdir.'*.css');
		$upload_dir = wp_upload_dir()['baseurl'];
        foreach($files as $file){
            if( strpos($file,'.css') !== false ){
                $css = file_get_contents($file);
                if(defined('STYLE_IMG')){
                    $css = str_replace('{{STYLE_IMG}}', STYLE_IMG, $css);
                }
                $css = str_replace('{{UPLOAD}}', $upload_dir, $css);
                echo "\n".$css;
            }
        }
    }
}

function vttt_css_inline_minify($css){
	$css = preg_replace('/\s+/', ' ', $css); // Convert multiple whitespace to single space
	$css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css); // Remove comments
	$css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $css); // Remove tabs/newlines
	return $css;
}
<?php
if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.
/*****
 * by Sam Mittelstaedt 
*****/

function vt_customizer_styles() {
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

/* V1 */
	--vt-brand: #02275D;
	--vt-brand-rgb: 2, 39, 93;
	--vt-brand-secondary: #0055a4;
	--vt-brand-secondary-rgb: 0, 85, 164;
	--vt-action: #E8934A;
	--vt-action-rgb: 232, 147, 74;
	--vt-action-light: #F5C87A;
	--vt-action-light-rgb: 245, 200, 122;
	--vt-surface-warm: #F8F5F0;
	--vt-surface-warm-rgb: 248, 245, 240;
/* V2 
	--vt-brand: #0B2F6B;
	--vt-brand-rgb: 11, 47, 107;
	--vt-brand-secondary: #1F5FAF;
	--vt-brand-secondary-rgb: 31, 95, 175;
	--vt-action: #B22234;
	--vt-action-rgb: 178, 34, 52;
	--vt-action-light: #e04254;
	--vt-action-light-rgb: 224, 66, 84;
	--vt-surface-warm: #F8F7F3;
	--vt-surface-warm-rgb: 248, 245, 240;
*/
	--vt-heading: var(--vt-brand);
	--vt-link: var(--vt-brand-secondary);
	--vt-link-hover: var(--vt-brand);
	--vt-cta-bg: var(--vt-action);
	--vt-cta-text: #111827;
	--vt-dark-bg: var(--vt-brand);
	--vt-dark-text: #FFFFFF;
	--vt-card-bg: #FFFFFF;
	--vt-navy: #02275D; /*  #002b62; */

	/* Grade scale — functional, not brand */
	--vt-g-a: #1D7A45;
	--vt-g-b: #3F8F52;
	--vt-g-c: #9A7200;
	--vt-g-d: #B8522E;
	--vt-g-f: #A61E2A;

	--map-bg: #3F8F52;
	--map-bg-hover: #1D7A45;
	--map-bg-active: #1D7A45;
	--map-text-hover: #F8F5F0;
	--map-text: #333;
	--map-border: #333;

	--bs-text-color:#111111;
	--bs-red: #c41425;
    --bs-red-rgb: 196, 20, 37;
    --bs-reddk: #761318;
    --bs-reddk-rgb: 118, 19, 24;
	--bs-blue: #1F5FAF;	/* Primary blue #0055a4 */
	--bs-blue-rgb: 0, 85, 164;
	--bs-bluedk: #0B2F6B;	/* Anchor */
	--bs-bluedk-rgb: 2, 39, 93;
	--bs-green: #146c43;
	--bs-green-rgb: 20, 108, 67;
	--bs-greendk: #157347;
	--bs-greendk-rgb: 21, 115, 71;
	--bs-orange: #E8934A;	/* Amber accent #E8934A */
	--bs-orange-rgb: 232, 147, 74;
	--bs-yellow: #F5C87A;	/* Amber light #F5C87A */
	--bs-yellow-rgb: 245, 200, 122;
	--bs-primary: #1F5FAF;	/* Primary blue #0055a4 */
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
}
<?php
//Load Customizer Styles
if(function_exists('vt_load_css')){
	vt_load_css(STYLE_DIR.'/assets/customizer/');
}else{
	echo "\n/*Customizer Loading Failed*/\n";
}

?>
</style>
<?php
	$css = ob_get_clean();
	$css = vt_css_inline_minify($css);
    echo "\n".$css."\n";
}
add_action( 'wp_head', 'vt_customizer_styles', 11 );


function vt_load_css($cssdir){
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

function vt_css_inline_minify($css){
	$css = preg_replace('/\s+/', ' ', $css); // Convert multiple whitespace to single space
	$css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css); // Remove comments
	$css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $css); // Remove tabs/newlines
	return $css;
}
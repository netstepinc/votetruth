<?php if(!defined('ABSPATH')){exit;}

function vt_enqueue_assets() {
	wp_enqueue_script( 'jquery' );

	wp_enqueue_style( 'fa', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css', [], '6.7.2' );
	wp_enqueue_style('fi-fonts', 'https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap', false );
	wp_enqueue_style('bs-icons', 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.13.1/font/bootstrap-icons.css', [], null, 'all');
	//wp_enqueue_style('swiper', 'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.css', [], null, 'all');

	if( is_singular('legislator') ){
		wp_enqueue_script('qr-code-styling', 'https://cdn.jsdelivr.net/npm/qr-code-styling/lib/qr-code-styling.min.js', array(), null, true);
	}
/*
	if ( is_front_page() || is_page() || is_singular('post') ) {
		wp_enqueue_script('gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js', [], null, true);
		wp_enqueue_script('gsap-scrolltrigger', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js', ['gsap'], null, true);
		wp_enqueue_script('gsap-init', STYLE_JS . 'gsap-animations.js', ['gsap', 'gsap-scrolltrigger'], '1.0.0', true);
	}
*/
	wp_enqueue_script('jsvectormap', STYLE_JS . 'jsvectormap.min.js', [], null, true);
	wp_enqueue_script('jsvectormap-us-en', STYLE_JS . 'jsvectormap-us-en.js', ['jsvectormap'], null, true);
	//wp_enqueue_script('swiper', 'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.js', [], null, true);
}
add_action( 'wp_enqueue_scripts', 'vt_enqueue_assets' );

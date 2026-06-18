<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
 * Front Page Template — Freedom Index Home v2
 * PEACE stands for Problem, Empathy, Answer, Change, and End Result.
 */

get_header();

//get_template_part('template-parts/debug_system');

get_template_part('template-parts/home','0hero');
get_template_part('template-parts/home','1problem'); 	//Problem: Agitate
get_template_part('template-parts/home','2empathy'); 	//Empathy
get_template_part('template-parts/home','3answer'); 	//Answer
get_template_part('template-parts/home','4stats');
get_template_part('template-parts/home','5change'); 	//Change
get_template_part('template-parts/home','6end-result');	//End Result
get_template_part('template-parts/home','7cta');
get_template_part('template-parts/home','8actions');

get_footer();

/*
"Government gets bigger. Your life gets smaller."
Or the inverse we've been working with:
"Keep government small and your life big."
*/
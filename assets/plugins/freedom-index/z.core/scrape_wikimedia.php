<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
https://commons.wikimedia.org/w/index.php?search=Robert+Aderholt+official+photo&title=Special%3AMediaSearch&type=image
https://commons.wikimedia.org/wiki/File:Robert_Aderholt_official_photo.jpg

Can we automatically look for 
https://commons.wikimedia.org/wiki/File:{firstname}_{lastname}_official_photo.jpg
If URL found > extract image URL from DOM - use DOMDocument and DOMXPath to find the image URL
If URL is valid > download image and save to WordPress media library

Randome test: https://commons.wikimedia.org/wiki/File:Troy_Balderson_official_photo.jpg = NOPE.
- https://commons.wikimedia.org/wiki/File:Troy_Balderson,_official_portrait,_116th_Congress.jpg

https://commons.wikimedia.org/wiki/File:Sen._Angela_Alsobrooks_official_Senate_photo,_119th_Congress.jpg

So, we may be able to try a few combinations.

Is scrape necessary or can we guess the image URL? Hummm...no idea what /f/ff/ and other URL parts designate.
https://upload.wikimedia.org/wikipedia/commons/f/ff/Robert_Aderholt_official_photo.jpg
https://upload.wikimedia.org/wikipedia/commons/f/f6/Troy_Balderson%2C_official_portrait%2C_116th_Congress.jpg
https://upload.wikimedia.org/wikipedia/commons/4/47/Sen._Angela_Alsobrooks_official_Senate_photo%2C_119th_Congress.jpg


*/
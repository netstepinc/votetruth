US Vector Map
Interactive vector map of the United States with state-level details.
jsvectormap.min.js
jsvectormap-us-en.js

FL,VA,MI,AK,HI


CT,DE,MA,MD,NH,NJ,RI,VT

Question: "Is there an official method with this map for supporting clickable badges on the right for the small states like CT,DE,MA,MD,NH,NJ,RI,VT?"
Answer: No, jsVectorMap (and its predecessor jVectorMap) does not provide a built-in or "official" feature for displaying external clickable badges/labels for "tiny" states such as those in the Northeast. Instead, the standard approach is to implement a custom UI using your own HTML/CSS for those badges and trigger map behavior (or navigation) via JavaScript event handlers. 

In other words: clickable badges for small states are not supported natively as a JSVM feature; the only official support for small states is their inclusion in the SVG map regions. For compact, easily-clickable labels, you must custom code their appearance and behavior.

References:
- https://jvectormap.com/manual/#regions
- https://github.com/themustafaomar/jsvectormap/issues/24
- https://github.com/themustafaomar/jsvectormap/issues/71

Supporting Information:
- See the README and documentation: https://github.com/themustafaomar/jsvectormap and https://jvectormap.com/tutorials/
- It's common, like in your earlier PNG map, to create a `.fi-us-map-tiny` button group and handle click events yourself.
- On click, you can call jsVectorMap's `setSelectedRegions` or navigate to a URL (like you currently do for map clicks).
- Some people overlay additional tooltips or refactor SVG maps to have labels, but this is uncommon and not recommended for interactivity.
 
The following CSS is the default from jsVectorMap (and jVectorMap before it). 
It supports map internal UI: tooltips, zoom buttons, and the legend overlay.
If you do not display legends, tooltips, zoom-btns (since zoomButtons = false), etc, then most of these selectors aren't actually used by our implementation.
As of now, these rules can be safely deleted or commented out for a lighter stylesheet, except for maybe image,text user-select for SVG (if selecting unwanted).
For completeness, keeping here as a common baseline, but feel free to trim.

image,text,.jvm-zoomin,.jvm-zoomout{-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none;}
.jvm-container{-ms-touch-action:none;touch-action:none;position:relative;overflow:hidden;height:100%;width:100%;}
.jvm-tooltip{border-radius:3px;background-color:#5c5cff;font-family:sans-serif,Verdana;font-size:smaller;box-shadow:1px 2px 12px rgba(0,0,0,.2);padding:3px 5px;white-space:nowrap;position:absolute;display:none;color:#fff;}
.jvm-tooltip.active{display:block;}
.jvm-zoom-btn{border-radius:3px;background-color:#292929;padding:3px;box-sizing:border-box;position:absolute;line-height:10px;cursor:pointer;color:#fff;height:15px;width:15px;left:10px;}
.jvm-zoom-btn.jvm-zoomout{top:30px;}
.jvm-zoom-btn.jvm-zoomin{top:10px;}
.jvm-series-container{right:15px;position:absolute;}
.jvm-series-container.jvm-series-h{bottom:15px;}
.jvm-series-container.jvm-series-v{top:15px;}
.jvm-series-container .jvm-legend{background-color:#fff;border:1px solid #e5e7eb;margin-left:.75rem;border-radius:.25rem;border-color:#e5e7eb;padding:.6rem;box-shadow:0 1px 2px 0 rgba(0,0,0,.05);float:left;}
.jvm-series-container .jvm-legend .jvm-legend-title{line-height:1;border-bottom:1px solid #e5e7eb;padding-bottom:.5rem;margin-bottom:.575rem;text-align:left;}
.jvm-series-container .jvm-legend .jvm-legend-inner{overflow:hidden;}
.jvm-series-container .jvm-legend .jvm-legend-inner .jvm-legend-tick{overflow:hidden;min-width:40px;}
.jvm-series-container .jvm-legend .jvm-legend-inner .jvm-legend-tick:not(:first-child){margin-top:.575rem;}
.jvm-series-container .jvm-legend .jvm-legend-inner .jvm-legend-tick .jvm-legend-tick-sample{border-radius:4px;margin-right:.65rem;height:16px;width:16px;float:left;}
.jvm-series-container .jvm-legend .jvm-legend-inner .jvm-legend-tick .jvm-legend-tick-text{font-size:12px;text-align:center;float:left;}
.jvm-line[animation=true]{-webkit-animation:jvm-line-animation 10s linear forwards infinite;animation:jvm-line-animation 10s linear forwards infinite;}
@-webkit-keyframes jvm-line-animation{from{stroke-dashoffset:250}}
@keyframes jvm-line-animation{from{stroke-dashoffset:250}}







We're not using highcharts at all are we?
	//Home page only: Can't serve off highchargs.com
	//https://code.highcharts.com/maps/highmaps.js
	//https://code.highcharts.com/mapdata/countries/us/us-all.js
	//https://code.highcharts.com/modules/accessibility.js
	/*
	if( is_front_page() ){
		wp_enqueue_script('highcharts','https://cdnjs.cloudflare.com/ajax/libs/highmaps/6.0.3/highmaps.js',[],null,true);
		wp_enqueue_script('highcharts-us-map','https://code.highcharts.com/mapdata/countries/us/us-all.js',['highcharts'],null,true);
		wp_enqueue_script('highcharts-accessibility','https://code.highcharts.com/modules/accessibility.js',['highcharts'],null,true);
		wp_enqueue_script('usa-map', STYLE_JS . 'usa-map.js', ['highcharts-us-map'], '1.0.7', true);
		wp_localize_script('usa-map','usaMap',array('base' => trailingslashit( home_url( '/' ) ), ));
	}
	*/
<?php if(!defined('ABSPATH')){exit;}

function sam_social_share($args = array()){
	$link = $args['link'];
	$class = isset($args['class']) && $args['class'] != '' ? $args['class'] : 'btn-share btn btn-sm text-white me-2';
	$preText = isset($args['preText']) ? $args['preText'] : '';
	$text = isset($args['text']) && $args['text'] == true ? true : false;

	$icons = [];
	$icons['x'] = [
		'url' => 'https://x.com/share?url=',
		'title' => 'Share to X',
		'icon' => 'fa-brands fa-x-twitter',
		'class' => 'bg-dark',
	];
	$icons['facebook'] = [
		'url' => 'https://www.facebook.com/sharer.php?u=',
		'title' => 'Share to Facebook',
		'icon' => 'fa-brands fa-facebook',
		'class' => 'btn-facebook',
	];
	$icons['whatsapp'] = [
		'url' => 'whatsapp://send?text=',
		'title' => 'Share to Whatsapp',
		'icon' => 'fa-brands fa-whatsapp',
		'class' => 'btn-success',
	];
	$icons['email'] = [
		'url' => 'mailto:?subject=',
		'title' => 'Share by Email',
		'icon' => 'fa fa-envelope',
		'class' => 'btn-envelope',
	];
	$icons['pdf'] = [
		'url' => '',
		'title' => 'Print to PDF',
		'icon' => 'fa-regular fa-file-pdf',
		'class' => 'btn-danger',
	];

	$str = '';
	if($preText){
		$str .= $preText;
	}
	foreach($icons as $key => $icon){
		if(substr($class,0,9) == 'btn-share'){
			$iconClass = $class.' '.$icon['class'];
		}else{
			$iconClass = $class;
		}
		$str .= '<a href="' . ($key == 'pdf' ? $link . '_pdf/' : $icon['url'] . $link) . '" target="_blank" class="'.$iconClass.' d-print-none" title="' . $icon['title'] . '" target="_blank"><i class="' . $icon['icon'] . '"></i>' . ($text ? ' '.$icon['title'] : '') . '</a>';
	}
	return $str;
}

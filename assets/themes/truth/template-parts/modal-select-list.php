<?php if(!defined('ABSPATH')) exit;
$govs = defined('FS_GOVERNMENTS') ? FS_GOVERNMENTS : [];
unset($govs['US']);

if(isset($args['type']) && $args['type'] == 'federal') {
	$type = 'federal';
}else{
	$type = 'state';
}
?>
<ul class="list-group list-group-flush">
<?php foreach($govs as $abr => $name){
if($type == 'federal') {
    $li_url = esc_url(home_url('/us/legislators/state/' . strtolower($abr)));
} else {
    $li_url = esc_url(home_url('/' . strtolower($abr) . '/legislators/'));
}
echo '<li class="list-group-item"><a href="' . $li_url . '" class="text-decoration-none fs-3 lh-1">' . $name . '</a></li>';
} ?>
</ul>

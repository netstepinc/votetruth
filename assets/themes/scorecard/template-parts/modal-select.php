<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
Select state legislators modal
State map with clickable states that link to home_url('/us/legislators/state/{state-slug}/')  //https://freedomindex.us/us/legislators/state/az/session/14/

ON HOME PAGE:
- Button: "State Legislators" that opens this modal

ON SUB PAGES:
- Top nav bar: "State Legislators" link that opens this modal

Include this modal in header file.
*/

if(isset($args['type']) && $args['type'] == 'federal') {
    $type = 'federal';
	$attrID = 'fi-modal-federal';
	$title = 'Congressional Legislators';
} else {
    $type = 'state';
	$attrID = 'fi-modal-state';
	$title = 'State Legislators';
}


?>
<div class="modal fade" id="<?php echo $attrID; ?>" tabindex="-1" aria-labelledby="<?php echo $attrID; ?>-label" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="<?php echo $attrID; ?>-label"><?php echo $title; ?></h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-2">
<?php
if($type == 'federal') {
	echo '<a href="' . esc_url(home_url('/us/legislators/')) . '" class="btn btn-sm btn-primary fs-5 fw-bold w-100 rounded-3 mb-4 shadow">View All Congressional Legislators</a>';
}
//Switch to state list on xsmobile / hide map
?>
<div class="d-sm-none">
<?php
if($type == 'federal') {
	get_template_part('template-parts/modal-select-list','',['type' => 'federal']);
} else {
    get_template_part('template-parts/modal-select-list','',['type' => 'state']);
}
?>
</div>
<div class="d-none d-sm-block">
<?php
if($type == 'federal') {
	get_template_part('template-parts/modal-select-map','',['type' => 'federal']);
} else {
    get_template_part('template-parts/modal-select-map','',['type' => 'state']);
}
?>
</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script>
document.getElementById('<?php echo esc_js($attrID); ?>')?.addEventListener('hide.bs.modal', function () {
	if (this.contains(document.activeElement)) {
		document.activeElement.blur();
	}
});
</script>
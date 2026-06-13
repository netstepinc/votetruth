<?php
$date_end = strtotime("18 December 2021");
if(time() < $date_end):
/*
https://codepen.io/sanjeevbeekeeper/pen/aJoWJJ
*/
?>
<style>
.modal-sm{max-width:302px;}
.modal-body img{width:100%; height:auto;}
</style>
<div class="modal fade" id="tnaModal" role="dialog">
	<div class="modal-dialog modal-dialog-centered modal-sm">
		<div class="modal-content">
<?php
/*
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">Close</button>
				<h4 class="modal-title text-primary">Trumpworld</h4>
			</div>
*/
?>
			<div class="modal-body p-0">
				<ins data-revive-zoneid="420" data-revive-id="5ba266ecba9126ec545d05b74521692f"></ins>
<?php
/*
<!-- Revive Adserver Asynchronous JS Tag - Generated with Revive Adserver v5.0.5 -->
<ins data-revive-zoneid="421" data-revive-id="5ba266ecba9126ec545d05b74521692f"></ins>
<script async src="//adserve.jbs.org/www/delivery/asyncjs.php"></script>
*/
?>
			</div>
			<div class="modal-footer p-0 m-0">
				<button type="button" class="btn btn-secondary btn-sm col-12 text-center m-0" data-dismiss="modal">Close Ad</button>
			</div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function(){
	// sessionStorage.getItem('key');
	if (sessionStorage.getItem("popup") !== 'true') {
		// sessionStorage.setItem('key', 'value'); pair
		sessionStorage.setItem("popup", "true");
		// Calling the bootstrap modal
		$("#tnaModal").modal();
	}
});
</script>
<?php endif;?>
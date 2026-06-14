<?php if(!defined('ABSPATH')) exit;
/*
Admin Legislator image check and pre-generate.
*/
?>
<!-- Legislator Images -->
<div class="card shadow-sm mb-4" style="margin-top: 20px;">
	<div class="card-header bg-white border-0 pb-0">
		<h2 class="h4 mb-0">Legislator Images</h2>
	</div>
	<div class="card-body">
		<h5>Generate cached images for legislators with no image URL</h5>
		<table class="table table-striped table-hover">
		<thead>
			<tr>
				<th>Legislator ID</th>
				<th>Legislator Name</th>
				<th>Image ID</th>
				<th>Image URL</th>
				<th>Actions</th>
			</tr>
		</thead>
		<tbody>
		<?php
		global $wpdb;
		$legquery = "SELECT id,display_name,image_id FROM ".TBFI_LEGISLATORS." WHERE image_id > 0 and image_url IS NULL LIMIT 1000;";
		$legislators = $wpdb->get_results($legquery);
		foreach($legislators as $legislator){
			$image_url = jis_get_attachment_image_src($legislator['image_id'], [200,250],true);
			$img_saved = '-';
			$update_query = "UPDATE ".TBFI_LEGISLATORS." SET image_url = '".$image_url['src']."' WHERE id = ".$legislator['id'].";";
			$result = $wpdb->query($update_query);
			if($result){
				$img_saved = 'Saved';
			}else{
				$img_saved = 'Failed';
			}
			echo '<tr>';
			echo '<td>'.$legislator['id'].'</td>';
			echo '<td>'.$legislator['display_name'].'</td>';
			echo '<td>'.$legislator['image_id'].'</td>';
			echo '<td><a href="'.$image_url['src'].'" target="_blank">'.$image_url['src'].'</a></td>';
			echo '<td>'.$img_saved.'</td>';
			echo '</tr>';
		}
		?>
		</tbody>
		</table>
	</div>
</div>
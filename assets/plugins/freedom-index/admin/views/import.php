<?php
if(!defined('ABSPATH')) exit;

// Check if import was triggered
if (isset($_GET['action']) && $_GET['action'] === 'import') {
	fi_admin_import_handle_simple_action();
	return;
}
?>
<div class="wrap">
	<h1>Import Data from V1/V2 Systems</h1>
	
	<div class="fi-import-status">
		<h2>Import Status</h2>
		<p>Legacy database connection: 
			<span class="fi-status success">Ready</span>
		</p>
		<p>Available govs: <strong>Congress + 50 States</strong></p>
	</div>
	
	<div class="fi-import-actions">
		<h2>Import by Site</h2>
		<p>Import all data for a specific site (taxonomies first, then posts):</p>
		
		<div class="fi-site-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 20px 0;">
			<?php
			$sites = [
				5 => ['gov' => 'US', 'name' => 'Congress'],
				19 => ['gov' => 'UT', 'name' => 'Utah'],
				13 => ['gov' => 'TX', 'name' => 'Texas'],
				14 => ['gov' => 'FL', 'name' => 'Florida'],
				35 => ['gov' => 'NY', 'name' => 'New York'],
				34 => ['gov' => 'CA', 'name' => 'California'],
				15 => ['gov' => 'PA', 'name' => 'Pennsylvania'],
				16 => ['gov' => 'OH', 'name' => 'Ohio'],
				17 => ['gov' => 'IL', 'name' => 'Illinois'],
				18 => ['gov' => 'GA', 'name' => 'Georgia']
			];
			
			foreach ($sites as $site_id => $site_data): ?>
				<div style="border: 1px solid #ddd; padding: 15px; text-align: center; border-radius: 5px;">
					<h4><?php echo esc_html($site_data['name']); ?> (<?php echo esc_html($site_data['gov']); ?>)</h4>
					<p>Site ID: <?php echo $site_id; ?></p>
					<a href="<?php echo esc_url(admin_url('admin.php?page=fi-import&action=import&blog_id=' . $site_id)); ?>" class="btn btn-primary">
						Import All Data
					</a>
				</div>
			<?php endforeach; ?>
		</div>
		
		<div class="fi-import-info">
			<h3>Import Process</h3>
			<p><strong>For each site, the import will:</strong></p>
			<ol>
				<li><strong>Taxonomies:</strong> Sessions (congress/session), Parties, Vote Groups, Districts</li>
				<li><strong>Posts:</strong> Legislators, Votes, Reports</li>
				<li><strong>Cross-references:</strong> Link votes to sessions, legislators to sessions</li>
			</ol>
			
			<p><strong>Data Structure:</strong></p>
			<ul>
				<li><strong>Congress (Site 5):</strong> Uses 'congress' taxonomy</li>
				<li><strong>States:</strong> Use 'session' taxonomy</li>
				<li><strong>All sites:</strong> Use 'party', 'fi_vote_group', 'district' taxonomies</li>
			</ul>
		</div>
	</div>
	
	<div class="fi-import-govs">
		<h2>Available Jurisdictions</h2>
		<p>The import will process data from all 51 govs:</p>
		<ul style="columns: 3; column-gap: 20px;">
			<li>Congress (US)</li>
			<li>Alabama (AL)</li>
			<li>Alaska (AK)</li>
			<li>Arizona (AZ)</li>
			<li>Arkansas (AR)</li>
			<li>California (CA)</li>
			<li>Colorado (CO)</li>
			<li>Connecticut (CT)</li>
			<li>Delaware (DE)</li>
			<li>Florida (FL)</li>
			<li>Georgia (GA)</li>
			<li>Hawaii (HI)</li>
			<li>Idaho (ID)</li>
			<li>Illinois (IL)</li>
			<li>Indiana (IN)</li>
			<li>Iowa (IA)</li>
			<li>Kansas (KS)</li>
			<li>Kentucky (KY)</li>
			<li>Louisiana (LA)</li>
			<li>Maine (ME)</li>
			<li>Maryland (MD)</li>
			<li>Massachusetts (MA)</li>
			<li>Michigan (MI)</li>
			<li>Minnesota (MN)</li>
			<li>Mississippi (MS)</li>
			<li>Missouri (MO)</li>
			<li>Montana (MT)</li>
			<li>Nebraska (NE)</li>
			<li>Nevada (NV)</li>
			<li>New Hampshire (NH)</li>
			<li>New Jersey (NJ)</li>
			<li>New Mexico (NM)</li>
			<li>New York (NY)</li>
			<li>North Carolina (NC)</li>
			<li>North Dakota (ND)</li>
			<li>Ohio (OH)</li>
			<li>Oklahoma (OK)</li>
			<li>Oregon (OR)</li>
			<li>Pennsylvania (PA)</li>
			<li>Rhode Island (RI)</li>
			<li>South Carolina (SC)</li>
			<li>South Dakota (SD)</li>
			<li>Tennessee (TN)</li>
			<li>Texas (TX)</li>
			<li>Utah (UT)</li>
			<li>Vermont (VT)</li>
			<li>Virginia (VA)</li>
			<li>Washington (WA)</li>
			<li>West Virginia (WV)</li>
			<li>Wisconsin (WI)</li>
			<li>Wyoming (WY)</li>
		</ul>
	</div>
	
	<div class="fi-import-validation">
		<h2>Data Validation</h2>
		<p>After import, use the validation tools to check data integrity:</p>
		<a href="<?php echo esc_url(admin_url('admin.php?page=fi-legislators')); ?>" class="btn btn-secondary">
			Check Legislators
		</a>
		<a href="<?php echo esc_url(admin_url('admin.php?page=fi-votes')); ?>" class="btn btn-secondary">
			Check Votes
		</a>
	</div>
</div>
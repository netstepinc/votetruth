<?php
if (!defined('ABSPATH')) exit;

global $fi_list;

if (!$fi_list) {
	wp_safe_redirect(home_url('/'));
	exit;
}

get_header();

$legislator_ids = json_decode($fi_list->legislators, true);
$list = !empty($legislator_ids) ? fi_legislators_get_by_ids($legislator_ids, true) : [];

// Get list meta for contact selection
$list_meta = !empty($fi_list->meta) ? json_decode($fi_list->meta, true) : [];
$contact_index = $list_meta['contact_index'] ?? null;

// Get selected contact if one is chosen
$selected_contact = null;
if ($contact_index !== null && $contact_index !== '') {
	$user_id = $fi_list->user_id;
	$pdf_contacts = fi_pdf_contacts_get($user_id);
	if (isset($pdf_contacts[$contact_index])) {
		$selected_contact = $pdf_contacts[$contact_index];
	}
}

// Boilerplate description
$description = ''; //'This list contains legislators from the Freedom Index. Use this information to contact your representatives and share your views on important issues.';

$header_args = [
	'title' => $fi_list->name ?? 'Legislator List',
	'pretext' => '',
	'id' => 'fi-list-public',
	'class' => 'fi-list-public-page',
	'filter_enabled' => false,
];
fi_get_template('partials/template-header', $header_args);
?>
<div class="container-xl">
	<div class="row">
		<div class="col-12">
			<?php if (!empty($description)): ?>
			<div class="card">
				<div class="card-body">
					<p class="lead mb-4"><?php echo esc_html($description); ?></p>
				</div>
			</div>
			<?php endif; ?>
			
			<!-- List Content -->
			<div id="fi-list-content">
				<?php if (empty($list)): ?>
					<div class="alert alert-info">
						<h5>Empty List</h5>
						<p class="mb-0">This list doesn't contain any legislators yet.</p>
					</div>
				<?php else: ?>
					<?php echo fi_list_render_legislators($list, $fi_list->id,'col-12 col-md-6 col-lg-4 p-3'); ?>
				<?php endif; ?>
			</div>
			
			<!-- Contact Information (if selected) -->
			<?php /* if ($selected_contact): ?>
				<div class="card mt-4 rounded-4">
					<div class="card-header rounded-top-4">
						<h5 class="card-title mb-0">Contact Information</h5>
					</div>
					<div class="card-body">
						<?php if (!empty($selected_contact['name'])): ?>
							<p class="mb-2"><strong><?php echo esc_html($selected_contact['name']); ?></strong></p>
						<?php endif; ?>
						<?php if (!empty($selected_contact['phone'])): ?>
							<p class="mb-2">
								<i class="bi bi-telephone me-2"></i>
								<a href="tel:<?php echo esc_attr($selected_contact['phone']); ?>"><?php echo esc_html($selected_contact['phone']); ?></a>
							</p>
						<?php endif; ?>
						<?php if (!empty($selected_contact['email'])): ?>
							<p class="mb-0">
								<i class="bi bi-envelope me-2"></i>
								<a href="mailto:<?php echo esc_attr($selected_contact['email']); ?>"><?php echo esc_html($selected_contact['email']); ?></a>
							</p>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; */ ?>
			
		</div>
	</div>
</div>

<?php 
fi_get_template('partials/template-footer');
get_footer();

<?php
if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
	wp_safe_redirect(home_url('/account/'));
	exit;
}

$user = wp_get_current_user();
$user_id = $user->ID;
$address = fi_user_meta_get($user_id, 'address');
$has_address = !empty($address) && (!empty($address['address_1']) || !empty($address['postcode']));
$pdf_contacts = fi_pdf_contacts_get($user_id);
$user_lists = fi_lists_get_by_user($user_id);
?>
<div class="row">
	<?php fi_get_template('account-nav', ['current_page' => 'dashboard']); ?>
	<div class="col-12 col-md-9">
		<div class="mb-2">
			<h1 class="h2">Welcome back, <?php echo esc_html($user->display_name); ?>!</h1>
			<!-- <p class="text-muted">Manage your account settings, lists, and preferences.</p> -->
		</div>

		<div class="row">

			<div class="col-12 col-md-6 col-lg-4">

				<div class="card mb-4 rounded-4 shadow">
					<div class="card-header d-flex justify-content-between align-items-center">
						<h4 class="card-title mb-0">PDF Contact Info</h4>
						<a href="<?php echo esc_url(home_url('/account/personalize/')); ?>" class="btn btn-sm btn-outline-primary">
							<i class="bi bi-plus-circle me-1"></i> Add Contact
						</a>
					</div>
					<div class="card-body">
						<?php if (!empty($pdf_contacts)): ?>
							<div class="list-group list-group-flush">
								<?php foreach ($pdf_contacts as $index => $contact): ?>
									<div class="list-group-item d-flex justify-content-between align-items-start p-1">
										<div class="flex-grow-1">
											<div class="fw-bold"><?php echo esc_html($contact['name'] ?? 'Unnamed'); ?></div>
											<?php if (!empty($contact['phone'])): ?>
												<div class="small text-muted"><?php echo esc_html($contact['phone']); ?></div>
											<?php endif; ?>
											<?php if (!empty($contact['email'])): ?>
												<div class="small text-muted"><?php echo esc_html($contact['email']); ?></div>
											<?php endif; ?>
										</div>
										<div class="ms-3">
											<a href="<?php echo esc_url(home_url('/account/personalize/?edit=' . $index)); ?>"
												class="btn btn-sm btn-outline-secondary me-1" title="Edit">
												<i class="bi bi-pencil"></i>
											</a>
											<button type="button"
													class="btn btn-sm btn-outline-danger fi-delete-pdf-contact"
													data-index="<?php echo esc_attr($index); ?>"
													title="Delete"
													onclick="var i=parseInt(this.getAttribute('data-index'),10);if(!isNaN(i)&amp;&amp;window.fiDeletePdfContact){window.fiDeletePdfContact(i,window.fiPdfContacts.nonce);}return false;">
												<i class="bi bi-trash"></i>
											</button>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else: ?>
							<p class="text-muted mb-0">No contact information saved. Add contact info to personalize the PDFs you share with others.</p>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="col-12 col-md-6 col-lg-4">

				<div class="card mb-4 rounded-4 shadow">
					<div class="card-header d-flex justify-content-between align-items-center">
						<h4 class="card-title mb-0">Legislator Lists</h4>
						<a href="<?php echo esc_url(home_url('/account/lists/')); ?>" class="btn btn-sm btn-outline-primary">
							<i class="bi bi-eye me-1"></i> View All
						</a>
					</div>
					<div class="card-body">
						<?php if (!empty($user_lists)): ?>
							<div class="list-group list-group-flush">
								<?php foreach (array_slice($user_lists, 0, 5) as $list):
									$legislator_ids = json_decode($list->legislators, true);
									$legislator_count = is_array($legislator_ids) ? count($legislator_ids) : 0;
									?>
									<div class="list-group-item p-1">
										<div class="d-flex justify-content-between align-items-center">
											<div>
												<a href="<?php echo esc_url(home_url('/account/lists/' . $list->id . '/')); ?>" class="text-decoration-none fw-bold">
													<?php echo esc_html($list->name); ?>
												</a>
												<div class="small text-muted">
													<?php echo esc_html($legislator_count); ?> legislator<?php echo $legislator_count !== 1 ? 's' : ''; ?>
												</div>
											</div>
											<a href="<?php echo esc_url(home_url('/account/lists/' . $list->id . '/')); ?>"
												class="btn btn-sm btn-outline-primary">
												View
											</a>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
							<?php if (count($user_lists) > 5): ?>
								<div class="mt-3 text-center">
									<a href="<?php echo esc_url(home_url('/account/lists/')); ?>" class="btn btn-sm btn-outline-secondary">
										View All <?php echo count($user_lists); ?> Lists
									</a>
								</div>
							<?php endif; ?>
						<?php else: ?>
							<p class="text-muted mb-0">You haven't created any lists yet. Start by finding your elected officials or browsing legislators.</p>
						<?php endif; ?>
					</div>
				</div>

				<div class="card mb-4 rounded-4 shadow border-warning">
					<div class="card-header bg-warning rounded-4 rounded-bottom-0">
						<h4 class="card-title mb-0"><i class="bi bi-exclamation-diamond-fill text-danger"></i> Legislator Lists</h4>
					</div>
					<div class="card-body">
						<p class="card-text">You may create lists of your elected officials and share them with others. For example, you might create a list for your local district to share with neighbors.</p>
						<p class="card-text">This is a new feature and we will be adding more list tools for you as soon as possible including PDF printing.</p>
					</div>
				</div>

			</div>

			<div class="col-12 col-md-6 col-lg-4">
				<div class="card mb-4 rounded-4 shadow">
					<div class="card-header">
						<h4 class="card-title mb-0">Legislator Lookup Address</h4>
					</div>
					<div class="card-body">
						<?php if ($has_address): ?>
							<div class="mb-3">
								<a href="<?php echo esc_url(home_url('/account/profile/')); ?>" class="btn btn-sm btn-outline-primary p-1 float-end">
									<i class="bi bi-pencil me-1"></i> Edit
								</a>
								<?php if (!empty($address['first_name']) || !empty($address['last_name'])): ?>
									<strong><?php echo esc_html(trim(($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? ''))); ?></strong><br>
								<?php endif; ?>
								<?php if (!empty($address['address_1'])): ?>
									<?php echo esc_html($address['address_1']); ?><br>
								<?php endif; ?>
								<?php if (!empty($address['address_2'])): ?>
									<?php echo esc_html($address['address_2']); ?><br>
								<?php endif; ?>
								<?php
								$city_state_zip = [];
								if (!empty($address['city'])) $city_state_zip[] = $address['city'];
								if (!empty($address['state'])) $city_state_zip[] = $address['state'];
								if (!empty($address['postcode'])) $city_state_zip[] = $address['postcode'];
								if (!empty($city_state_zip)) echo esc_html(implode(', ', $city_state_zip));
								?>
							</div>

						<?php else: ?>
							<div class="alert alert-info mb-3">
								<p class="mb-2">Enter an address to automatically see your legislators when you sign into your account.</p>
								<p class="mb-0 small"><?php echo FI_PRIVACY_PROMISE; ?></p>
							</div>
							<a href="<?php echo esc_url(home_url('/account/profile/')); ?>" class="btn btn-primary">
								<i class="bi bi-plus-circle me-1"></i> Add my Address
							</a>
						<?php endif; ?>
					</div>
				</div>

				<?php if ($has_address){fi_get_template('account-findmy');}?>

			</div>
		</div>
	</div>
</div>
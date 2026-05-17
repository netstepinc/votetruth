<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Get legislator object from args (may be passed directly or as data property)
$legislator = $args['legislator'] ?? (object) $args;
$name = $legislator->display_name ?? $args['display_name'] ?? '';
//$first_name = $legislator->first_name ?? $args['first_name'] ?? '';
$contact_button_text = 'Legislator Contact Info'; //$first_name ? 'Contact ' . $first_name : 'Contact Info.';

// Get structured meta data using helper methods
$addresses = fi_legislator_addresses($legislator);
$websites = fi_legislator_websites($legislator);
$contact = fi_legislator_contact($legislator);
$social = fi_legislator_social($legislator);

//Email Contact Legislator
$legislator_email_subject = rawurlencode("I'm concerned about...");
$legislator_email_body = rawurlencode("Dear " . $name . ",\n\nI am writing to express my concern about...\n\nThank you for your time and consideration.\n\nSincerely,\n[Your Name]");
$legislator_email_link = "mailto:?subject=" . $legislator_email_subject . "&body=" . $legislator_email_body;


// Start Modal Content
ob_start();
?>

<?php if (empty($addresses) && empty($websites) && empty($contact) && empty($social)): ?>
	<p class="text-muted text-center">Contact information not available for this legislator.</p>
<?php else: ?>
	<!-- Websites -->
	<?php if (!empty($websites)): ?>
	<div class="mb-3">
		<label class="form-label small fw-bold">Website<?php echo count($websites) > 1 ? 's' : ''; ?></label>
		<?php foreach ($websites as $website):
			//Word around to occasionally display text instead of the URL: Value saved as: {url}|{text}
			$website_text = parse_url($website, PHP_URL_HOST) ?: $website;
			if (strpos($website, '|') !== false) {
				list($website, $website_text) = explode('|', $website, 2);
				$website_text = urldecode($website_text);
			}
			?>
			<a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener" class="d-block mb-1">
				<i class="bi bi-box-arrow-up-right"></i> <?php echo esc_html($website_text); ?>
			</a>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<!-- Email -->
	<?php if (!empty($contact['email'])): ?>
	<div class="mb-3">
		<label class="form-label small fw-bold">Email</label>
		<a href="<?php echo esc_url($legislator_email_link); ?>" class="d-block">
			<i class="bi bi-envelope"></i> <?php echo esc_html($contact['email']); ?>
		</a>
	</div>
	<?php endif; ?>
		
	<!-- Phone -->
	<?php if (!empty($contact['phone'])): ?>
	<div class="mb-3">
		<label class="form-label small fw-bold">Phone</label>
		<a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $contact['phone'])); ?>" class="d-block">
			<i class="bi bi-telephone"></i> <?php echo esc_html($contact['phone']); ?>
		</a>
	</div>
	<?php endif; ?>
		
	<!-- Fax -->
	<?php if (!empty($contact['fax'])): ?>
	<div class="mb-3">
		<label class="form-label small fw-bold">Fax</label>
		<span class="d-block">
			<i class="bi bi-printer"></i> <?php echo esc_html($contact['fax']); ?>
		</span>
	</div>
	<?php endif; ?>

	
	<!-- Social Media -->
	<?php if (!empty($social)): ?>
	<div class="d-block mb-3">
		<label class="form-label small fw-bold">Social Media</label>
		<div class="d-block">
			<?php if (!empty($social['twitter'])): ?>
			<a href="<?php echo esc_url($social['twitter']); ?>" target="_blank" rel="noopener" class="btn btn-outline-info btn-sm w-100 mb-2">
				<i class="bi bi-twitter-x"></i> X / Twitter
			</a>
			<?php endif; ?>
			
			<?php if (!empty($social['facebook'])): ?>
			<a href="<?php echo esc_url($social['facebook']); ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm w-100 mb-2">
				<i class="bi bi-facebook"></i> Facebook
			</a>
			<?php endif; ?>
			
			<?php if (!empty($social['instagram'])): ?>
			<a href="<?php echo esc_url($social['instagram']); ?>" target="_blank" rel="noopener" class="btn btn-outline-danger btn-sm w-100 mb-2">
				<i class="bi bi-instagram"></i> Instagram
			</a>
			<?php endif; ?>
			
			<?php if (!empty($social['linkedin'])): ?>
			<a href="<?php echo esc_url($social['linkedin']); ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm w-100 mb-2">
				<i class="bi bi-linkedin"></i> LinkedIn
			</a>
			<?php endif; ?>
			
			<?php if (!empty($social['youtube'])): ?>
			<a href="<?php echo esc_url($social['youtube']); ?>" target="_blank" rel="noopener" class="btn btn-outline-danger btn-sm w-100 mb-2">
				<i class="bi bi-youtube"></i> YouTube
			</a>
			<?php endif; ?>

			<?php if (!empty($social['tiktok'])): ?>
			<a href="<?php echo esc_url($social['tiktok']); ?>" target="_blank" rel="noopener" class="btn btn-outline-danger btn-sm w-100 mb-2">
				<i class="bi bi-tiktok"></i> TikTok
			</a>
			<?php endif; ?>

			<?php if (!empty($social['telegram'])): ?>
			<a href="<?php echo esc_url($social['telegram']); ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm w-100 mb-2">
				<i class="bi bi-telegram"></i> Telegram
			</a>
			<?php endif; ?>

			<?php if (!empty($social['gab'])): ?>
			<a href="<?php echo esc_url($social['gab']); ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm w-100 mb-2">
				<i class="bi bi-gab"></i> Gab
			</a>
			<?php endif; ?>
			
			<?php if (!empty($social['truthsocial'])): ?>
			<a href="<?php echo esc_url($social['truthsocial']); ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm w-100 mb-2">
				<i class="bi bi-truthsocial"></i> TruthSocial
			</a>
			<?php endif; ?>

		</div>
	</div>
	<?php endif; ?>

	<!-- Addresses -->
	<?php if (!empty($addresses)): ?>
		<?php foreach ($addresses as $address): ?>
			<div class="mt-3">
				<label class="form-label small fw-bold"><?php echo esc_html($address['name'] ?? 'Address'); ?></label>
				<address class="small mb-0">
					<?php echo fi_legislator_format_address($address); ?>
				</address>
				<?php if (!empty($address['phone'])): ?>
					<div class="mt-1">
						<small><i class="bi bi-telephone"></i> <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $address['phone'])); ?>"><?php echo esc_html($address['phone']); ?></a></small>
					</div>
				<?php endif; ?>
				<?php if (!empty($address['email'])): ?>
					<div class="mt-1">
						<small><i class="bi bi-envelope"></i> <a href="mailto:<?php echo esc_attr($address['email']); ?>"><?php echo esc_html($address['email']); ?></a></small>
					</div>
				<?php endif; ?>
				<?php if (!empty($address['note'])): ?>
					<div class="mt-0">
						<small class="text-small text-muted"><?php echo esc_html($address['note']); ?></small>
					</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

<?php endif; ?>

<?php
$modal_body = ob_get_clean();

$args = [
	'id' => 'contact',
	'button_text' => $contact_button_text,
	'button_icon' => 'bi bi-envelope',
	'modal_title' => $name.'\'s Contact Information',
	'modal_body' => $modal_body,
];
fi_legislator_modal($args);
?>
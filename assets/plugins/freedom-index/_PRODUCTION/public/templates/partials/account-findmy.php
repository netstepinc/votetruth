<?php if (!defined('ABSPATH')) exit;

$address = fi_user_meta_get(get_current_user_id(), 'address');
		
if (!empty($address) && !empty($address['postcode'])) {
	// Map address fields to form format
	$address_str = implode(' ', array_filter([
		$address['address_1'],
		$address['address_2'],
		$address['city'],
		$address['state'],
		$address['postcode']
	]));

	$data = fi_geocod_get_officials_for_user_dashboard($address_str);

	// Generate HTML for results using compact card partial
	ob_start();
	echo '<div class="mt-3">';
	if ( isset($data['officials']) && !empty($data['officials']) && is_array($data['officials'])) {
		echo '<div class="row">';
		foreach ($data['officials'] as $official) {
			$official['class_col'] = 'col-12 pb-2';
			fi_get_template('partials/legislator-card-sm', $official);
		}
		echo '</div>';
	} else {
		echo '<div class="alert alert-warning small">No officials found for address: ' . esc_html($address_str) . '</div>';
	}
	echo '</div>';
	echo ob_get_clean();
}
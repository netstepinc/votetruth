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
		echo '<div class="row g-2">';
		foreach ($data['officials'] as $official) {
			$leg_id = $official['legislator']['id'] ?? ($official['id'] ?? 0);
			$gov    = $official['gov'] ?? 'US';
			$leg_array = [
				'id'           => (int) $leg_id,
				'display_name' => $official['name'] ?? trim(($official['first_name'] ?? '') . ' ' . ($official['last_name'] ?? '')),
				'image_id'     => $official['image_id'] ?? null,
				'image_url'    => $official['photo_url'] ?? '',
				'score'        => $official['score'] ?? ($official['freedom_score'] ?? null),
				'party'        => $official['party'] ?? '',
				'party_name'   => $official['party_name'] ?? '',
				'chamber'      => $official['chamber'] ?? '',
				'state'        => $official['state'] ?? '',
				'state_name'   => $official['state_name'] ?? '',
				'gov'          => $gov,
			];
			echo '<div class="col-12">';
			fi_get_template('legislators-card', ['legislator' => $leg_array, 'gov' => $gov]);
			echo '</div>';
		}
		echo '</div>';
	} else {
		echo '<div class="alert alert-warning small">No officials found for address: ' . esc_html($address_str) . '</div>';
	}
	echo '</div>';
	echo ob_get_clean();
}
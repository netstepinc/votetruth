<?php
/*
 * Freedom Index Legislator Meta Data Helpers
 *
 * Straight function version of the former LegislatorsMeta class file.
 *
 * Handles legislator metadata normalization, extraction, and formatting.
 * Refactored the legislator meta file into straight functions.

Key adjustments:
Removed the LegislatorsMeta class/namespace wrapper.
Preserved the existing public helper:
fi_legislator_meta_normalize()
fi_legislator_address_format()
Added direct public equivalents for the former class methods:
fi_legislator_meta_from_object()
fi_legislator_meta_get_addresses()
fi_legislator_meta_get_primary_address()
fi_legislator_meta_get_capitol_address()
fi_legislator_meta_get_websites()
fi_legislator_meta_get_social()
fi_legislator_meta_get_contact()
fi_legislator_meta_get_personal()
fi_legislator_address_format_full()
Added support for $legislator->meta being either an array or JSON string.
Kept the large-payload cleanup behavior for legiscan_data, nested legacy, and api_* keys.
 */

if (!defined('ABSPATH')) exit;

/**
 * Normalize meta array from a flat structure to organized groups.
 *
 * Automatically migrates older flat structures into organized groups:
 * - address
 * - social
 * - contact
 * - website
 * - personal
 *
 * @param array $meta Raw meta array from database.
 * @return array Normalized meta array.
 */
function fi_legislator_meta_normalize(array $meta): array {
	if (isset($meta['address']) || isset($meta['social']) || isset($meta['contact']) || isset($meta['website'])) {
		if (isset($meta['offices']) && !isset($meta['address'])) {
			$meta['address'] = $meta['offices'];
			unset($meta['offices']);
		}

		return $meta;
	}

	$normalized = [];

	$contact = [];
	if (!empty($meta['email'])) {
		$contact['email'] = $meta['email'];
	}
	if (!empty($meta['phone'])) {
		$contact['phone'] = $meta['phone'];
	}
	if (!empty($meta['fax'])) {
		$contact['fax'] = $meta['fax'];
	}
	if (!empty($contact)) {
		$normalized['contact'] = $contact;
	}

	$websites = [];
	if (!empty($meta['website'])) {
		$websites = is_array($meta['website']) ? $meta['website'] : [$meta['website']];
	}

	for ($i = 2; $i <= 10; $i++) {
		$key = 'website' . $i;
		if (!empty($meta[$key])) {
			$websites[] = $meta[$key];
		}
	}

	if (!empty($websites)) {
		$normalized['website'] = array_values(array_filter($websites));
	}

	$addresses = [];

	if (!empty($meta['office_address']) || !empty($meta['office_city'])) {
		$capitol_address = [
			'name'       => 'Capitol Office',
			'type'       => 'capitol',
			'is_primary' => true,
		];

		if (!empty($meta['office_address'])) {
			$capitol_address['address'] = $meta['office_address'];
		}
		if (!empty($meta['office_city'])) {
			$capitol_address['city'] = $meta['office_city'];
		}
		if (!empty($meta['office_state'])) {
			$capitol_address['state'] = $meta['office_state'];
		}
		if (!empty($meta['office_zip'])) {
			$capitol_address['zip'] = $meta['office_zip'];
		}
		if (!empty($meta['phone'])) {
			$capitol_address['phone'] = $meta['phone'];
		}
		if (!empty($meta['email'])) {
			$capitol_address['email'] = $meta['email'];
		}

		$addresses[] = $capitol_address;
	}

	if (!empty($meta['district_address']) || !empty($meta['district_city'])) {
		$district_address = [
			'name'       => 'District Office',
			'type'       => 'district',
			'is_primary' => false,
		];

		if (!empty($meta['district_address'])) {
			$district_address['address'] = $meta['district_address'];
		}
		if (!empty($meta['district_city'])) {
			$district_address['city'] = $meta['district_city'];
		}
		if (!empty($meta['district_state'])) {
			$district_address['state'] = $meta['district_state'];
		}
		if (!empty($meta['district_zip'])) {
			$district_address['zip'] = $meta['district_zip'];
		}
		if (!empty($meta['district_phone'])) {
			$district_address['phone'] = $meta['district_phone'];
		} elseif (!empty($meta['local_phone'])) {
			$district_address['phone'] = $meta['local_phone'];
		}
		if (!empty($meta['district_email'])) {
			$district_address['email'] = $meta['district_email'];
		} elseif (!empty($meta['local_email'])) {
			$district_address['email'] = $meta['local_email'];
		}

		$addresses[] = $district_address;
	}

	if (!empty($meta['local']) && empty($district_address)) {
		$local_parts = explode('|', $meta['local']);
		if (count($local_parts) >= 2) {
			$addresses[] = [
				'name'       => 'Local Office',
				'type'       => 'local',
				'is_primary' => false,
				'address'    => trim($local_parts[0] ?? ''),
				'city'       => trim($local_parts[1] ?? ''),
				'state'      => trim($local_parts[2] ?? ''),
				'zip'        => trim($local_parts[3] ?? ''),
				'phone'      => trim($local_parts[4] ?? ''),
			];
		}
	}

	if (!empty($addresses)) {
		$normalized['address'] = $addresses;
	}

	$social = [];
	$social_keys = [
		'twitter'     => ['twitter', 'twitter-x', 'social_twitter'],
		'facebook'    => ['facebook', 'social_facebook'],
		'instagram'   => ['instagram', 'social_instagram'],
		'linkedin'    => ['linkedin', 'social_linkedin'],
		'youtube'     => ['youtube', 'social_youtube'],
		'gab'         => ['gab', 'social_gab'],
		'truthsocial' => ['truthsocial', 'social_truthsocial'],
		'tiktok'      => ['tiktok', 'social_tiktok'],
		'telegram'    => ['telegram', 'social_telegram'],
	];

	foreach ($social_keys as $platform => $possible_keys) {
		foreach ($possible_keys as $key) {
			if (isset($meta['social_links'][$platform]) && !empty($meta['social_links'][$platform])) {
				$social[$platform] = $meta['social_links'][$platform];
				break;
			}

			if (isset($meta[$key]) && !empty($meta[$key])) {
				$social[$platform] = $meta[$key];
				break;
			}
		}
	}

	if (!empty($social)) {
		$normalized['social'] = $social;
	}

	$personal = [];
	if (!empty($meta['birthdate']) || !empty($meta['legislator_birthdate'])) {
		$personal['birthdate'] = $meta['birthdate'] ?? $meta['legislator_birthdate'];
	}
	if (!empty($meta['hometown']) || !empty($meta['townname']) || !empty($meta['legislator_hometown'])) {
		$personal['hometown'] = $meta['hometown'] ?? $meta['townname'] ?? $meta['legislator_hometown'];
	}
	if (!empty($meta['bio']) || !empty($meta['legislator_bio'])) {
		$personal['bio'] = $meta['bio'] ?? $meta['legislator_bio'];
	}
	if (!empty($meta['education'])) {
		$personal['education'] = $meta['education'];
	}
	if (!empty($meta['profession'])) {
		$personal['profession'] = $meta['profession'];
	}
	if (!empty($personal)) {
		$normalized['personal'] = $personal;
	}

	$bio_keys = ['gender', 'birth_date', 'death_date', 'url_openstates'];
	foreach ($bio_keys as $bio_key) {
		if (!empty($meta[$bio_key])) {
			$normalized[$bio_key] = $meta[$bio_key];
		}
	}

	if (empty($normalized['birth_date']) && !empty($personal['birthdate'])) {
		$normalized['birth_date'] = $personal['birthdate'];
	}

	$legacy = $meta;
	unset($legacy['legiscan_data'], $legacy['legacy']);

	foreach (array_keys($legacy) as $k) {
		if (is_string($k) && str_starts_with($k, 'api_')) {
			unset($legacy[$k]);
		}
	}

	if (!empty($legacy)) {
		$normalized['legacy'] = $legacy;
	}

	return $normalized;
}

/**
 * Get a normalized meta array from a legislator object.
 *
 * @param object $legislator Legislator object with meta property.
 * @return array Normalized meta array.
 */
function fi_legislator_meta_from_object(object $legislator): array {
	$meta = $legislator['meta'] ?? [];

	if (is_string($meta) && $meta !== '') {
		$decoded = json_decode($meta, true);
		$meta = is_array($decoded) ? $decoded : [];
	}

	if (!is_array($meta)) {
		$meta = [];
	}

	return fi_legislator_meta_normalize($meta);
}

/**
 * Get addresses array from legislator meta.
 *
 * @param object $legislator Legislator object with meta property.
 * @return array Array of address arrays.
 */
function fi_legislator_meta_get_addresses(object $legislator): array {
	$meta = fi_legislator_meta_from_object($legislator);
	$address = $meta['address'] ?? [];

	if (is_string($address)) {
		$decoded = json_decode($address, true);
		return is_array($decoded) ? $decoded : [];
	}

	return is_array($address) ? $address : [];
}

/**
 * Get primary address.
 *
 * @param object $legislator Legislator object.
 * @return array|null Address array or null.
 */
function fi_legislator_meta_get_primary_address(object $legislator): ?array {
	$addresses = fi_legislator_meta_get_addresses($legislator);

	foreach ($addresses as $address) {
		if (!empty($address['is_primary'])) {
			return $address;
		}
	}

	foreach ($addresses as $address) {
		if (($address['type'] ?? '') === 'capitol') {
			return $address;
		}
	}

	return !empty($addresses) ? $addresses[0] : null;
}

/**
 * Get capitol address.
 *
 * @param object $legislator Legislator object.
 * @return array|null Address array or null.
 */
function fi_legislator_meta_get_capitol_address(object $legislator): ?array {
	$addresses = fi_legislator_meta_get_addresses($legislator);

	foreach ($addresses as $address) {
		if (($address['type'] ?? '') === 'capitol') {
			return $address;
		}
	}

	return null;
}

/**
 * Get websites array from legislator meta.
 *
 * @param object $legislator Legislator object.
 * @return array Website URLs.
 */
function fi_legislator_meta_get_websites(object $legislator): array {
	$meta = fi_legislator_meta_from_object($legislator);

	return is_array($meta['website'] ?? null) ? $meta['website'] : [];
}

/**
 * Get social media links.
 *
 * @param object $legislator Legislator object.
 * @return array Platform => URL.
 */
function fi_legislator_meta_get_social(object $legislator): array {
	$meta = fi_legislator_meta_from_object($legislator);

	return is_array($meta['social'] ?? null) ? $meta['social'] : [];
}

/**
 * Get primary contact information.
 *
 * @param object $legislator Legislator object.
 * @return array Contact info.
 */
function fi_legislator_meta_get_contact(object $legislator): array {
	$meta = fi_legislator_meta_from_object($legislator);

	return is_array($meta['contact'] ?? null) ? $meta['contact'] : [];
}

/**
 * Get personal information.
 *
 * @param object $legislator Legislator object.
 * @return array Personal info.
 */
function fi_legislator_meta_get_personal(object $legislator): array {
	$meta = fi_legislator_meta_from_object($legislator);

	return is_array($meta['personal'] ?? null) ? $meta['personal'] : [];
}

/**
 * Format address for display as sanitized HTML.
 *
 * @param array $address Address array.
 * @return string Formatted address HTML.
 */
function fi_legislator_address_format(array $address): string {
	$parts = [];

	if (!empty($address['address'])) {
		$parts[] = esc_html($address['address']);
	}

	$city_state_zip = '';
	if (!empty($address['city'])) {
		$city_state_zip .= esc_html($address['city']);
	}
	if (!empty($address['state'])) {
		$city_state_zip .= ', ' . esc_html($address['state']);
	}
	if (!empty($address['zip'])) {
		$city_state_zip .= ' ' . esc_html($address['zip']);
	}

	if ($city_state_zip !== '') {
		$parts[] = $city_state_zip;
	}

	return !empty($parts) ? wp_kses_post(implode('<br>', $parts)) : '';
}

/**
 * Format full address as single-line plain text.
 *
 * @param array $address Address array.
 * @return string Formatted address string.
 */
function fi_legislator_address_format_full(array $address): string {
	$parts = [];

	if (!empty($address['address'])) {
		$parts[] = (string) $address['address'];
	}

	$city_state_zip = [];
	if (!empty($address['city'])) {
		$city_state_zip[] = (string) $address['city'];
	}
	if (!empty($address['state'])) {
		$city_state_zip[] = (string) $address['state'];
	}
	if (!empty($address['zip'])) {
		$city_state_zip[] = (string) $address['zip'];
	}

	if (!empty($city_state_zip)) {
		$parts[] = implode(', ', $city_state_zip);
	}

	return implode(', ', $parts);
}
<?php if(!defined('ABSPATH')) exit;
/* CROSS REFERENCE DATA: https://github.com/unitedstates/congress-legislators

We have some US Congressional legislator meta data like external reference IDs, social media handles and local offices. However, we're missing many of those details.
I found a feed from https://github.com/unitedstates/congress-legislators  that can help fill in those holes, and we should overwrite what we have with these records since they are actively maintained.
I've downloaded the files I want to reference. They can be found in FI_DIR_CACHE.'reference/'.
My idea is to load all three of these and consolidate them into an update array matching our legislator data structure such as social media and multiple addresses. @jbs.org/PUBLIC_HTML/wp-content/plugins/freedom-index/admin/views/legislator-edit.php 
We'll need to evaluate how to deal with addresses where they are stored as an array. We don't want to create duplicates, and if we have an address that's no longer in use, we should remove it. Special care must be given to reconciling the data with what we have an updating everything from the reference data.
In the case of US legislators, we have bioguide ID for all so we can key this infor by bioguide ID. When saving, we don't care about their gov because we have a positive match by bioguide ID.
I'd like this to be a supplemental hidden process only I run such has by appending the URL with &reference=US.
freedomindex.us/wp-admin/admin.php?page=fi-legislators&reference=US.
Then at the bottom of the Legislator list or in place of the legislator list, display a detailed report of what data is being imported to who, etc.
I don't want this to be some complicated process. I will only run it once or twice per year. I want to use procedural code with a complete output of the data being handled.

fi_legislators external IDs fields: bioguide_id,lis_id, legiscan_id, govtrack_id, votesmart_id, ballotpedia_id, openstates_id
	Other data will be stored in the meta field as JSON.

See: fi_admin_legislators_get_meta_field_groups()
*/

//Load reference date
$reference_files = [];
$reference_files[] = [
	'name' => 'Current Legislators',
	'file' => FI_DIR_CACHE.'reference/legislators-current.json',
	'url' => 'https://unitedstates.github.io/congress-legislators/legislators-current.json',
	'key' => ['id' => 'bioguide_id'],
	'fields' => [
		'id' => [
			'lis' => 'lis_id',
			'govtrack' => 'govtrack_id',
			'votesmart' => 'votesmart_id',
			'ballotpedia' => 'ballotpedia_id',
			'openstates' => 'openstates_id',
			'opensecrets' => 'meta[opensecrets_id]',
			'wikipedia' => 'meta[url_wikipedia]', // https://en.wikipedia.org/wiki/'.strreplace(' ', '_',{id:wikipedia});
			'wikidata' => 'meta[url_wikidata]', //https://www.wikidata.org/wiki/{id:wikidata}
		],
		'bio' => [
			'birthdate' => 'meta[birth_date]',
			'gender' => 'meta[gender]',
		],
	],
];
$reference_files[] = [
	'name' => 'District Offices',
	'file' => FI_DIR_CACHE.'reference/legislators-district-offices.json',
	'url' => 'https://unitedstates.github.io/congress-legislators/legislators-district-offices.json',
	'key' => ['id' => 'bioguide_id'],
	'fields' => [
		//Examine how to save the office addresses array
		'offices' => [], //See how to save the office addresses array. See function fi_legislator_addresses($legislator_id);
	],
];
$reference_files[] = [
	'name' => 'Social Media',
	'file' => FI_DIR_CACHE.'reference/legislators-social-media.json',
	'url' => 'https://unitedstates.github.io/congress-legislators/legislators-social-media.json',
	'key' => ['id' => 'bioguide_id'],
	'fields' => [
		'social' => [
			'twitter' => 'meta[twitter]', //https://www.x.com/{social:twitter}
			'facebook' => 'meta[facebook]', //https://www.facebook.com/{social:facebook}
			'youtube' => 'meta[youtube]', //https://www.youtube.com/user/{social:youtube}
			'instagram' => 'meta[instagram]', //https://www.instagram.com/{social:instagram}
		],
	],
];

// Summary: run only when explicitly requested via ?reference=US.
$reference_action = strtoupper(sanitize_text_field($_GET['reference'] ?? ''));
if ($reference_action !== 'US') {
	return;
}
if (!current_user_can('manage_options')) {
	wp_die('Insufficient permissions to run reference updates.');
}

global $wpdb;
$table = $wpdb->prefix . 'fi_legislators';

// Summary: load reference JSON files once and fail fast if missing.
$read_json = static function (string $file, string $label): array {
	if (!is_readable($file)) {
		return ['error' => $label . ' file not readable: ' . $file];
	}
	$raw = file_get_contents($file);
	if ($raw === false) {
		return ['error' => $label . ' file could not be read: ' . $file];
	}
	$decoded = json_decode($raw, true);
	if (!is_array($decoded)) {
		return ['error' => $label . ' file invalid JSON: ' . $file];
	}
	return ['data' => $decoded];
};

$current_ref = $read_json($reference_files[0]['file'], $reference_files[0]['name']);
$offices_ref = $read_json($reference_files[1]['file'], $reference_files[1]['name']);
$social_ref  = $read_json($reference_files[2]['file'], $reference_files[2]['name']);

$errors = array_filter([$current_ref['error'] ?? null, $offices_ref['error'] ?? null, $social_ref['error'] ?? null]);
if (!empty($errors)) {
	echo '<div class="notice notice-error"><p><strong>Reference import failed.</strong></p><ul>';
	foreach ($errors as $error) {
		echo '<li>' . esc_html($error) . '</li>';
	}
	echo '</ul></div>';
	return;
}

// Summary: index reference data by bioguide ID.
$ref_current_by_bio = [];
foreach ($current_ref['data'] as $row) {
	$bio = strtoupper(trim((string) ($row['id']['bioguide'] ?? '')));
	if ($bio === '') {
		continue;
	}
	$ref_current_by_bio[$bio] = $row;
}
$ref_offices_by_bio = [];
foreach ($offices_ref['data'] as $row) {
	$bio = strtoupper(trim((string) ($row['id']['bioguide'] ?? '')));
	if ($bio === '') {
		continue;
	}
	$ref_offices_by_bio[$bio] = $row;
}
$ref_social_by_bio = [];
foreach ($social_ref['data'] as $row) {
	$bio = strtoupper(trim((string) ($row['id']['bioguide'] ?? '')));
	if ($bio === '') {
		continue;
	}
	$ref_social_by_bio[$bio] = $row;
}

// Summary: detect available columns so we only update what exists.
$columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
$has_lis = in_array('lis_id', $columns, true);
$has_openstates = in_array('openstates_id', $columns, true);

// Summary: load legislators indexed by bioguide ID.
$legislators = $wpdb->get_results(
	"SELECT id, display_name, bioguide_id, lis_id, govtrack_id, votesmart_id, ballotpedia_id, openstates_id, meta
	 FROM {$table}
	 WHERE bioguide_id IS NOT NULL AND bioguide_id <> ''",
	ARRAY_A
);
$legislators_by_bio = [];
foreach ($legislators as $row) {
	$bio = strtoupper(trim((string) ($row['bioguide_id'] ?? '')));
	if ($bio !== '') {
		$legislators_by_bio[$bio] = $row;
	}
}

// Summary: helpers for normalization/reporting.
$normalize_text = static function ($value): string {
	return trim((string) ($value ?? ''));
};
$format_address = static function (array $address): string {
	$parts = [];
	if (!empty($address['type'])) $parts[] = $address['type'];
	if (!empty($address['name'])) $parts[] = $address['name'];
	if (!empty($address['address'])) $parts[] = $address['address'];
	if (!empty($address['suite'])) $parts[] = $address['suite'];
	$city_state_zip = [];
	if (!empty($address['city'])) $city_state_zip[] = $address['city'];
	if (!empty($address['state'])) $city_state_zip[] = $address['state'];
	if (!empty($address['zip'])) $city_state_zip[] = $address['zip'];
	if (!empty($city_state_zip)) $parts[] = implode(', ', $city_state_zip);
	return implode(' | ', $parts);
};
$format_capitol_line = static function (string $raw): string {
	$raw = trim($raw);
	if ($raw === '') {
		return '';
	}
	if (preg_match('/^(.*)\s+Washington\s+DC\s+(\d{5}(?:-\d{4})?)$/i', $raw, $matches)) {
		$line = trim($matches[1]);
		$zip = $matches[2] ?? '';
		$out = [$line, 'Washington DC'];
		if ($zip !== '') {
			$out[] = $zip;
		}
		return implode(' | ', $out);
	}
	return $raw;
};
$address_key = static function (array $address) use ($normalize_text): string {
	$parts = [
		$normalize_text($address['address'] ?? ''),
		$normalize_text($address['suite'] ?? ''),
		$normalize_text($address['city'] ?? ''),
		$normalize_text($address['state'] ?? ''),
		$normalize_text($address['zip'] ?? ''),
		$normalize_text($address['phone'] ?? ''),
	];
	return strtolower(implode('|', $parts));
};
$social_url = static function (string $platform, string $handle): string {
	$handle = trim($handle);
	if ($handle === '') {
		return '';
	}
	if (preg_match('/^https?:\/\//i', $handle)) {
		return $handle;
	}
	$roots = [
		'twitter' => 'https://www.x.com/',
		'facebook' => 'https://www.facebook.com/',
		'instagram' => 'https://www.instagram.com/',
		'youtube' => 'https://www.youtube.com/user/',
	];
	$root = $roots[$platform] ?? '';
	return $root !== '' ? $root . ltrim($handle, '@/') : $handle;
};

$report = [
	'updated' => [],
	'unchanged' => [],
	'missing' => [],
	'errors' => [],
];
$cache_needs_clear = false;

// Summary: reconcile reference data against existing legislators.
foreach ($ref_current_by_bio as $bio => $ref_row) {
	if (!isset($legislators_by_bio[$bio])) {
		$report['missing'][] = $bio;
		continue;
	}
	$leg = $legislators_by_bio[$bio];
	$leg_id = (int) ($leg['id'] ?? 0);
	if ($leg_id <= 0) {
		continue;
	}
	$display_name = (string) ($leg['display_name'] ?? $bio);

	$existing_meta = is_string($leg['meta'] ?? null) ? json_decode($leg['meta'], true) : (array) ($leg['meta'] ?? []);
	$existing_meta = is_array($existing_meta) ? $existing_meta : [];
	$normalized_meta = function_exists('fi_legislator_meta_normalize') ? fi_legislator_meta_normalize($existing_meta) : $existing_meta;
	$new_meta = $normalized_meta;

	// Summary: map reference IDs and bio data into our meta structure.
	$ref_id = $ref_row['id'] ?? [];
	$ref_bio = $ref_row['bio'] ?? [];
	$managed_meta_keys = ['opensecrets_id', 'url_wikipedia', 'url_wikidata', 'birth_date', 'gender', 'phone','fax'];
	$wiki_name = trim((string) ($ref_id['wikipedia'] ?? ''));
	$wiki_url = $wiki_name !== '' ? 'https://en.wikipedia.org/wiki/' . str_replace(' ', '_', $wiki_name) : '';
	$wikidata_url = trim((string) ($ref_id['wikidata'] ?? ''));
	$wikidata_url = $wikidata_url !== '' ? 'https://www.wikidata.org/wiki/' . $wikidata_url : '';
	//Convert F=female, M=male
	$gender = $ref_bio['gender'] === 'F' ? 'female' : 'male';
	$meta_updates = [
		'opensecrets_id' => trim((string) ($ref_id['opensecrets'] ?? '')),
		'url_wikipedia' => $wiki_url,
		'url_wikidata' => $wikidata_url,
		'birth_date' => trim((string) ($ref_bio['birthday'] ?? '')),
		'gender' => trim((string) ($gender ?? '')),
	];
	foreach ($meta_updates as $key => $value) {
		if ($value === '') {
			unset($new_meta[$key]);
		} else {
			$new_meta[$key] = $value;
		}
	}

	// Summary: update social media links (stored as full URLs).
	$social_existing = is_array($normalized_meta['social'] ?? null) ? $normalized_meta['social'] : [];
	$social_ref = $ref_social_by_bio[$bio]['social'] ?? [];
	$social_platforms = ['twitter', 'facebook', 'instagram', 'youtube'];
	foreach ($social_platforms as $platform) {
		$url = $social_url($platform, (string) ($social_ref[$platform] ?? ''));
		if ($url === '') {
			unset($social_existing[$platform]);
		} else {
			$social_existing[$platform] = $url;
		}
	}
	if (!empty($social_existing)) {
		$new_meta['social'] = $social_existing;
	} else {
		unset($new_meta['social']);
	}

	// Summary: update capitol address using last term address string (if provided).
	$capitol_raw = '';
	$last_term_url = '';
	$last_term_contact = '';
	$last_term_phone = '';
	$terms = $ref_row['terms'] ?? [];
	if (is_array($terms) && !empty($terms)) {
		$last_term = end($terms);
		$capitol_raw = trim((string) ($last_term['address'] ?? ''));
		$last_term_url = trim((string) ($last_term['url'] ?? ''));
		$last_term_contact = trim((string) ($last_term['contact_form'] ?? ''));
		$last_term_phone = trim((string) ($last_term['phone'] ?? ''));
		$last_term_fax = trim((string) ($last_term['fax'] ?? ''));
	}
	$capitol_line = '';
	$capitol_state = '';
	$capitol_zip = '';
	$capitol_change = null;
	if ($capitol_raw !== '') {
		$capitol_line = $capitol_raw;
		if (preg_match('/^(.*)\s+Washington\s+DC\s+(\d{5}(?:-\d{4})?)$/i', $capitol_raw, $matches)) {
			$capitol_line = trim($matches[1]);
			$capitol_state = 'Washington DC';
			$capitol_zip = $matches[2] ?? '';
		}
	}
	// Summary: merge last term url/contact_form into meta website array (do not remove).
	$website_existing = is_array($new_meta['website'] ?? null) ? $new_meta['website'] : [];
	$website_existing = array_values(array_filter(array_map('trim', $website_existing)));
	$website_seen = [];
	foreach ($website_existing as $url) {
		$website_seen[strtolower($url)] = true;
	}
	foreach ([$last_term_url, $last_term_contact] as $candidate) {
		if ($candidate === '') continue;
		$key = strtolower($candidate);
		if (!isset($website_seen[$key])) {
			$website_existing[] = $candidate;
			$website_seen[$key] = true;
		}
	}
	if (!empty($website_existing)) {
		$new_meta['website'] = $website_existing;
	}
	if ($last_term_phone !== '') {
		$new_meta['phone'] = $last_term_phone;
	}
	if ($last_term_fax !== '') {
		$new_meta['fax'] = $last_term_fax;
	}

	// Summary: replace district/local offices with the reference district offices.
	$existing_addresses = is_array($normalized_meta['address'] ?? null) ? $normalized_meta['address'] : [];
	$keep_addresses = [];
	$has_primary = false;
	foreach ($existing_addresses as $address) {
		if (!is_array($address)) continue;
		$type = strtolower(trim((string) ($address['type'] ?? '')));
		if ($type === 'district' || $type === 'local') {
			continue;
		}
		if (!empty($address['is_primary'])) {
			$has_primary = true;
		}
		$keep_addresses[] = $address;
	}
	if ($capitol_raw !== '') {
		$capitol_index = null;
		foreach ($keep_addresses as $idx => $address) {
			if (!is_array($address)) continue;
			$type = strtolower(trim((string) ($address['type'] ?? '')));
			if ($type === 'capitol') {
				$capitol_index = $idx;
				break;
			}
		}
		if ($capitol_index === null) {
			$keep_addresses[] = [
				'name' => 'Capitol Office',
				'type' => 'capitol',
				'is_primary' => !$has_primary,
				'address' => $capitol_line,
				'city' => '',
				'state' => $capitol_state,
				'zip' => $capitol_zip,
			];
			$has_primary = $has_primary ?: !empty($keep_addresses[count($keep_addresses) - 1]['is_primary']);
			$capitol_change = ['old' => '', 'new' => $capitol_raw];
		} else {
			$current_line = trim((string) ($keep_addresses[$capitol_index]['address'] ?? ''));
			$current_state = trim((string) ($keep_addresses[$capitol_index]['state'] ?? ''));
			$current_zip = trim((string) ($keep_addresses[$capitol_index]['zip'] ?? ''));
			$needs_split = ($current_line !== '' && preg_match('/Washington\s+DC\s+\d{5}(?:-\d{4})?/i', $current_line));
			$needs_update = ($capitol_line !== '' && $current_line !== $capitol_line) || $needs_split || $current_state === '' || $current_zip === '';
			if ($needs_update && $capitol_line !== '') {
				$keep_addresses[$capitol_index]['address'] = $capitol_line;
				$keep_addresses[$capitol_index]['city'] = '';
				if ($capitol_state !== '') {
					$keep_addresses[$capitol_index]['state'] = $capitol_state;
				}
				if ($capitol_zip !== '') {
					$keep_addresses[$capitol_index]['zip'] = $capitol_zip;
				}
				$capitol_change = ['old' => $current_line, 'new' => $capitol_raw];
			}
		}
	}
	$ref_offices = $ref_offices_by_bio[$bio]['offices'] ?? [];
	$ref_addresses = [];
	$ref_seen = [];
	foreach ($ref_offices as $office) {
		if (!is_array($office)) continue;
		$address = [
			'name' => '',
			'type' => 'district',
			'is_primary' => false,
			'address' => trim((string) ($office['address'] ?? '')),
			'suite' => trim((string) ($office['suite'] ?? '')),
			'city' => trim((string) ($office['city'] ?? '')),
			'state' => trim((string) ($office['state'] ?? '')),
			'zip' => trim((string) ($office['zip'] ?? '')),
			'phone' => trim((string) ($office['phone'] ?? '')),
			'hours' => trim((string) ($office['hours'] ?? '')),
		];
		if ($address['address'] === '' && $address['city'] === '') {
			continue;
		}
		$address['name'] = $address['city'] !== '' ? ($address['city'] . ' Office') : 'District Office';
		$key = $address_key($address);
		if ($key === '' || isset($ref_seen[$key])) {
			continue;
		}
		$ref_seen[$key] = true;
		$ref_addresses[] = $address;
	}
	if (!$has_primary && !empty($ref_addresses)) {
		$ref_addresses[0]['is_primary'] = true;
	}
	$final_addresses = array_values(array_merge($keep_addresses, $ref_addresses));
	if (!empty($final_addresses)) {
		$new_meta['address'] = $final_addresses;
	} else {
		unset($new_meta['address']);
	}

	// Summary: build top-level field updates (only columns that exist).
	$db_updates = [];
	$db_updates['govtrack_id'] = trim((string) ($ref_id['govtrack'] ?? ''));
	$db_updates['votesmart_id'] = trim((string) ($ref_id['votesmart'] ?? ''));
	$db_updates['ballotpedia_id'] = trim((string) ($ref_id['ballotpedia'] ?? ''));
	if ($has_openstates) {
		$db_updates['openstates_id'] = trim((string) ($ref_id['openstates'] ?? ''));
	}
	if ($has_lis) {
		$db_updates['lis_id'] = trim((string) ($ref_id['lis'] ?? ''));
	}

	// Summary: compute changes for reporting and avoid unnecessary writes.
	$changes = ['fields' => [], 'meta' => [], 'social' => [], 'addresses' => ['added' => [], 'removed' => []], 'capitol' => null];
	foreach ($db_updates as $field => $value) {
		$old = trim((string) ($leg[$field] ?? ''));
		$new = trim((string) $value);
		if ($old !== $new) {
			$changes['fields'][$field] = ['old' => $old, 'new' => $new];
		}
	}
	foreach ($managed_meta_keys as $key) {
		$old = trim((string) ($normalized_meta[$key] ?? ''));
		$new = trim((string) ($new_meta[$key] ?? ''));
		if ($old !== $new) {
			$changes['meta'][$key] = ['old' => $old, 'new' => $new];
		}
	}
	foreach ($social_platforms as $platform) {
		$old = trim((string) ($normalized_meta['social'][$platform] ?? ''));
		$new = trim((string) ($new_meta['social'][$platform] ?? ''));
		if ($old !== $new) {
			$changes['social'][$platform] = ['old' => $old, 'new' => $new];
		}
	}
	$old_address_keys = [];
	foreach ($existing_addresses as $address) {
		if (!is_array($address)) continue;
		$old_address_keys[$address_key($address)] = $address;
	}
	$new_address_keys = [];
	foreach ($final_addresses as $address) {
		if (!is_array($address)) continue;
		$new_address_keys[$address_key($address)] = $address;
	}
	foreach ($old_address_keys as $key => $address) {
		if (!isset($new_address_keys[$key])) {
			$changes['addresses']['removed'][] = $address;
		}
	}
	foreach ($new_address_keys as $key => $address) {
		if (!isset($old_address_keys[$key])) {
			$changes['addresses']['added'][] = $address;
		}
	}
	if ($capitol_change !== null) {
		$changes['capitol'] = $capitol_change;
	}

	$has_changes = !empty($changes['fields']) || !empty($changes['meta']) || !empty($changes['social']) || !empty($changes['addresses']['added']) || !empty($changes['addresses']['removed']) || !empty($changes['capitol']);
	if (!$has_changes) {
		$report['unchanged'][] = ['bio' => $bio, 'name' => $display_name];
		continue;
	}

	// Summary: apply database updates.
	$update_ok = true;

	if (!empty($changes['fields'])) {
		$update_data = [];
		$formats = [];
		foreach ($db_updates as $field => $value) {
			if (!in_array($field, $columns, true)) {
				continue;
			}
			$update_data[$field] = (string) $value;
			$formats[] = '%s';
		}
		if (!empty($update_data)) {
			$result = $wpdb->update($table, $update_data, ['id' => $leg_id], $formats, ['%d']);
			if ($result === false) {
				$update_ok = false;
			}
		}
	}
	$meta_changed = !empty($changes['meta']) || !empty($changes['social']) || !empty($changes['addresses']['added']) || !empty($changes['addresses']['removed']);
	if ($meta_changed && function_exists('fi_legislator_set_all_meta')) {
		$meta_ok = fi_legislator_set_all_meta($leg_id, $new_meta);
		if (!$meta_ok) {
			$update_ok = false;
		}
	}

	if ($update_ok) {
		$cache_needs_clear = true;
		$report['updated'][] = [
			'bio' => $bio,
			'name' => $display_name,
			'changes' => $changes,
		];
	} else {
		$report['errors'][] = [
			'bio' => $bio,
			'name' => $display_name,
			'error' => 'Database update failed.',
		];
	}
}

if ($cache_needs_clear && function_exists('fi_cache_clear')) {
	fi_cache_clear('legislators');
}

// Summary: render a detailed report.
echo '<div class="wrap"><h1>Legislator Reference Import (US)</h1>';
echo '<div class="notice notice-info"><p>Processed ' . esc_html((string) count($ref_current_by_bio)) . ' reference records. ';
echo 'Updated ' . esc_html((string) count($report['updated'])) . ', unchanged ' . esc_html((string) count($report['unchanged'])) . ', missing ' . esc_html((string) count($report['missing'])) . '.</p></div>';

if (!empty($report['errors'])) {
	echo '<div class="notice notice-error"><p><strong>Errors:</strong></p><ul>';
	foreach ($report['errors'] as $err) {
		echo '<li>' . esc_html($err['name'] . ' (' . $err['bio'] . '): ' . $err['error']) . '</li>';
	}
	echo '</ul></div>';
}

echo '<h2>Updated Records</h2>';
if (empty($report['updated'])) {
	echo '<p>No updates were necessary.</p>';
} else {
	foreach ($report['updated'] as $row) {
		echo '<div class="card" style="padding:12px; margin-bottom:12px;">';
		echo '<strong>' . esc_html($row['name']) . '</strong> <span class="text-muted">(' . esc_html($row['bio']) . ')</span>';

		if (!empty($row['changes']['fields'])) {
			echo '<h4>Fields</h4><ul>';
			foreach ($row['changes']['fields'] as $field => $change) {
				echo '<li>' . esc_html($field) . ': ' . esc_html($change['old']) . ' → ' . esc_html($change['new']) . '</li>';
			}
			echo '</ul>';
		}
		if (!empty($row['changes']['meta'])) {
			echo '<h4>Meta</h4><ul>';
			foreach ($row['changes']['meta'] as $field => $change) {
				echo '<li>' . esc_html($field) . ': ' . esc_html($change['old']) . ' → ' . esc_html($change['new']) . '</li>';
			}
			echo '</ul>';
		}
		if (!empty($row['changes']['social'])) {
			echo '<h4>Social</h4><ul>';
			foreach ($row['changes']['social'] as $field => $change) {
				echo '<li>' . esc_html($field) . ': ' . esc_html($change['old']) . ' → ' . esc_html($change['new']) . '</li>';
			}
			echo '</ul>';
		}
		if (!empty($row['changes']['capitol'])) {
			echo '<h4>Capitol Address</h4><ul>';
			$cap_old = (string) ($row['changes']['capitol']['old'] ?? '');
			$cap_new = (string) ($row['changes']['capitol']['new'] ?? '');
			echo '<li>' . esc_html($cap_old) . ' → ' . esc_html($format_capitol_line($cap_new)) . '</li>';
			echo '</ul>';
		}
		if (!empty($row['changes']['addresses']['added']) || !empty($row['changes']['addresses']['removed'])) {
			echo '<h4>Addresses</h4>';
			if (!empty($row['changes']['addresses']['added'])) {
				echo '<div><strong>Added:</strong><ul>';
				foreach ($row['changes']['addresses']['added'] as $address) {
					echo '<li>' . esc_html($format_address($address)) . '</li>';
				}
				echo '</ul></div>';
			}
			if (!empty($row['changes']['addresses']['removed'])) {
				echo '<div><strong>Removed:</strong><ul>';
				foreach ($row['changes']['addresses']['removed'] as $address) {
					echo '<li>' . esc_html($format_address($address)) . '</li>';
				}
				echo '</ul></div>';
			}
		}
		echo '</div>';
	}
}

echo '<h2>Unchanged Records</h2>';
if (empty($report['unchanged'])) {
	echo '<p>None.</p>';
} else {
	echo '<ul>';
	foreach ($report['unchanged'] as $row) {
		echo '<li>' . esc_html($row['name']) . ' (' . esc_html($row['bio']) . ')</li>';
	}
	echo '</ul>';
}

if (!empty($report['missing'])) {
	echo '<h2>Missing Bioguide IDs (No Match in FI)</h2><ul>';
	$ignore_states = ['DC', 'PR', 'VI', 'GU', 'MP', 'AS'];
	foreach ($report['missing'] as $bio) {
		$ref_row = $ref_current_by_bio[$bio] ?? null;
		if (!$ref_row) {
			echo '<li>' . esc_html($bio) . '</li>';
			continue;
		}
		$terms = $ref_row['terms'] ?? [];
		$last_term_state = '';
		if (is_array($terms) && !empty($terms)) {
			$last_term = end($terms);
			$last_term_state = strtoupper(trim((string) ($last_term['state'] ?? '')));
		}
		if ($last_term_state !== '' && in_array($last_term_state, $ignore_states, true)) {
			continue;
		}
		//name
		$first_name = trim((string) ($ref_row['name']['first'] ?? ''));
		$last_name = trim((string) ($ref_row['name']['last'] ?? ''));
		$label = $bio;
		if ($first_name !== '' && $last_name !== '') {
			$label .= ' - ' . $first_name . ' ' . $last_name;
		}
		if ($last_term_state !== '') {
			$label .= ' (' . $last_term_state . ')';
		}
		echo '<li>' . esc_html($label) . '</li>';
	}
	echo '</ul>';
}
echo '</div>';





/* EXAMPLE: legislators-social-media.json
  {
    "id": {
      "bioguide": "Y000064",
      "thomas": "02019",
      "govtrack": 412428
    },
    "social": {
      "twitter": "SenToddYoung",
      "facebook": "SenatorToddYoung",
      "youtube": "RepToddYoung",
SKIP      "youtube_id": "UCuknj4PGn91gHDNAfboZEgQ",
SKIP      "twitter_id": "234128524",
      "instagram": "sentoddyoung"
    }
  },
*/



/* EXAMPLE: legislators-district-offices.json
  {
    "id": {
      "bioguide": "A000148",
      "govtrack": 456825
    },
    "offices": [
      {
SKIP        "id": "A000148-newton",
        "address": "29 Crafts St.",
        "suite": "Suite 375",
        "city": "Newton",
        "state": "MA",
        "zip": "02458",
SKIP        "latitude": 42.354825,
SKIP        "longitude": -71.1998658,
        "phone": "617-332-3333"
      },
      {
        "id": "A000148-attleboro",
        "address": "8 N. Main St.",
        "suite": "Suite 200",
        "city": "Attleboro",
        "state": "MA",
        "zip": "02703",
        "latitude": 41.9446048,
        "longitude": -71.284441,
        "phone": "508-431-1110"
      },
      {
        "id": "A000148-fall_river",
        "address": "1 Government Center",
        "suite": "Office 237B",
        "city": "Fall River",
        "state": "MA",
        "zip": "02722",
        "hours": "Wednesday 10AM to 4PM",
        "latitude": 41.7008714,
        "longitude": -71.15463729999999
      }
    ]
  },
*/




/* EXAMPLE: legislators-current.json
 {
    "id": {
KEY      "bioguide": "C000127",
SKIP      "thomas": "00172",
FIELD      "lis": "S275",
FIELD      "govtrack": 300018,
META      "opensecrets": "N00007836",
FIELD      "votesmart": 27122,
SKIP      "fec": ["S8WA00194","H2WA01054"],
SKIP      "cspan": 26137, //https://www.c-span.org/person/adelita-grijalva/146389/ ignore this for now...maybe add later as meta.
META      "wikipedia": "Maria Cantwell",
SKIP      "house_history": 10608,
FIELD      "ballotpedia": "Maria Cantwell",
SKIP      "maplight": 544, //https://www.maplight.org/
SKIP      "icpsr": 39310,
META      "wikidata": "Q22250",
SKIP      "google_entity_id": "kg:/m/01x68t",
SKIP      "pictorial": 13398
    },
SKIP    "name": {"first": "Maria","last": "Cantwell","official_full": "Maria Cantwell"},
    "bio": {
META      "birthday": "1958-10-13",
META      "gender": "F"
    },

SKIP ALL TERM DATE
    "terms": [
      {
        "type": "rep",
        "start": "1993-01-05",
        "end": "1995-01-03",
        "state": "WA",
        "district": 1,
        "party": "Democrat"
      },
      {
        "type": "sen",
        "start": "2001-01-03",
        "end": "2007-01-03",
        "state": "WA",
        "class": 1,
        "party": "Democrat",
        "url": "http://cantwell.senate.gov"
      },
      {
        "type": "sen",
        "start": "2007-01-04",
        "end": "2013-01-03",
        "state": "WA",
        "class": 1,
        "party": "Democrat",
        "url": "http://cantwell.senate.gov",
        "address": "311 HART SENATE OFFICE BUILDING WASHINGTON DC 20510",
        "phone": "202-224-3441",
        "fax": "202-228-0514",
        "contact_form": "http://www.cantwell.senate.gov/contact/",
        "office": "311 Hart Senate Office Building"
      },
      {
        "type": "sen",
        "start": "2013-01-03",
        "end": "2019-01-03",
        "state": "WA",
        "party": "Democrat",
        "class": 1,
        "url": "https://www.cantwell.senate.gov",
        "address": "511 Hart Senate Office Building Washington DC 20510",
        "phone": "202-224-3441",
        "fax": "202-228-0514",
        "contact_form": "http://www.cantwell.senate.gov/public/index.cfm/email-maria",
        "office": "511 Hart Senate Office Building",
        "state_rank": "junior",
        "rss_url": "http://www.cantwell.senate.gov/public/index.cfm/rss/feed"
      },
      {
        "type": "sen",
        "start": "2019-01-03",
        "end": "2025-01-03",
        "state": "WA",
        "class": 1,
        "party": "Democrat",
        "state_rank": "junior",
        "url": "https://www.cantwell.senate.gov",
        "rss_url": "http://www.cantwell.senate.gov/public/index.cfm/rss/feed",
        "contact_form": "https://www.cantwell.senate.gov/public/index.cfm/email-maria",
        "address": "511 Hart Senate Office Building Washington DC 20510",
        "office": "511 Hart Senate Office Building",
        "phone": "202-224-3441"
      },
      {
        "type": "sen",
        "start": "2025-01-03",
        "end": "2031-01-03",
        "state": "WA",
        "class": 1,
        "state_rank": "junior",
        "party": "Democrat",
        "url": "https://www.cantwell.senate.gov",
        "rss_url": "http://www.cantwell.senate.gov/public/index.cfm/rss/feed",
        "contact_form": "https://www.cantwell.senate.gov/public/index.cfm/email-maria",
        "address": "511 Hart Senate Office Building Washington DC 20510",
        "office": "511 Hart Senate Office Building",
        "phone": "202-224-3441"
      }
    ]
  },
*/
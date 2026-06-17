<?php
/**
 * Freedom Index Geocodio integration.
 *
 * Retrieves elected officials from Geocodio, merges them with Freedom Index
 * legislator records, and updates available legislator metadata.
 * 
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build an address from the submitted legislator search fields.
 *
 * Uses the complete address when address, city, and state are supplied.
 * Otherwise, searches using the ZIP code.
 *
 * @return string
 */
function fi_geocod_build_submitted_address() {
	$zip = isset( $_POST['zip'] )
		? sanitize_text_field( wp_unslash( $_POST['zip'] ) )
		: '';

	if ( empty( $zip ) ) {
		wp_send_json_error(
			array(
				'message' => 'ZIP code is required',
			)
		);
	}

	$street_address = isset( $_POST['address'] )
		? sanitize_text_field( wp_unslash( $_POST['address'] ) )
		: '';

	$city = isset( $_POST['city'] )
		? sanitize_text_field( wp_unslash( $_POST['city'] ) )
		: '';

	$state = isset( $_POST['state'] )
		? sanitize_text_field( wp_unslash( $_POST['state'] ) )
		: '';

	if (
		! empty( $street_address ) &&
		! empty( $city ) &&
		! empty( $state )
	) {
		return trim(
			$street_address . ' ' .
			$city . ' ' .
			$state . ' ' .
			$zip
		);
	}

	return $zip;
}

/**
 * Convert a political party name to its abbreviated code.
 *
 * @param string $party_name Full political party name.
 *
 * @return string
 */
function fi_geocod_get_party_code( $party_name ) {
	switch ( $party_name ) {
		case 'Democrat':
			return 'D';

		case 'Republican':
			return 'R';

		case 'Libertarian':
			return 'L';

		default:
			return '';
	}
}

/**
 * Normalize a Geocodio legislator record into the format used by the site.
 *
 * @param array  $legislator Geocodio legislator data.
 * @param string $chamber    Chamber label.
 * @param string $division   Congressional or legislative district.
 *
 * @return array
 */
function fi_geocod_build_official_record( $legislator, $chamber, $division ) {
	$bio = isset( $legislator['bio'] ) && is_array( $legislator['bio'] )
		? $legislator['bio']
		: array();

	$party_name = isset( $bio['party'] )
		? $bio['party']
		: '';

	return array(
		'name'       => trim(
			( $bio['first_name'] ?? '' ) . ' ' .
			( $bio['last_name'] ?? '' )
		),
		'party'      => fi_geocod_get_party_code( $party_name ),
		'party_name' => $party_name,
		'chamber'    => $chamber,
		'division'   => $division,
		'contact'    => isset( $legislator['contact'] ) && is_array( $legislator['contact'] )
			? $legislator['contact']
			: array(),
		'social'     => isset( $legislator['social'] ) && is_array( $legislator['social'] )
			? $legislator['social']
			: array(),
		'bio'        => $bio,
		'photo_url'  => $bio['photo_url'] ?? null,
		'birthday'   => $bio['birthday'] ?? null,
		'gender'     => $bio['gender'] ?? null,
		'seniority'  => $legislator['seniority'] ?? null,
		'references' => isset( $legislator['references'] ) && is_array( $legislator['references'] )
			? $legislator['references']
			: array(),
	);
}

/**
 * Retrieve elected officials from the Geocodio API.
 *
 * Results are cached using the encoded address.
 *
 * @param string $address_encoded URL-encoded address.
 *
 * @return array
 */
function fi_geocod_fetch_officials( $address_encoded ) {
	fi_log( 'GEOCOD FETCH START: address_encoded=' . $address_encoded, __FILE__, __LINE__ );

	if ( empty( $address_encoded ) ) {
		fi_log( 'GEOCOD FETCH ERROR: Empty address_encoded', __FILE__, __LINE__ );
		return array( 'officials' => array(), 'city' => '', 'state' => '' );
	}

	$cache_key = fi_cache_key('findmy/' . $address_encoded);
	$cached = fi_cache( $cache_key );
	fi_log( 'GEOCOD CACHE CHECK: cache_key=' . $cache_key . ', cached=' . ( false !== $cached && null !== $cached ? 'YES' : 'NO' ), __FILE__, __LINE__ );

	if ( false !== $cached && null !== $cached && is_array( $cached ) ) {
		fi_log( 'GEOCOD CACHE HIT: Returning ' . count( $cached['officials'] ?? array() ) . ' officials from cache', __FILE__, __LINE__ );
		return $cached;
	}

	if ( ! defined( 'API_KEY_GEOCOD' ) || empty( API_KEY_GEOCOD ) ) {
		fi_log( 'GEOCOD FETCH ERROR: API_KEY_GEOCOD not defined or empty', __FILE__, __LINE__ );
		return array( 'officials' => array(), 'city' => '', 'state' => '' );
	}
	fi_log( 'GEOCOD API KEY: Present', __FILE__, __LINE__ );

	$geocode_url = add_query_arg(
		array(
			'q'       => $address_encoded,
			'fields'  => 'cd,stateleg',
			'api_key' => API_KEY_GEOCOD,
		),
		'https://api.geocod.io/v1.9/geocode'
	);

	fi_log( 'GEOCOD API URL: ' . $geocode_url, __FILE__, __LINE__ );

	$geocode_response = wp_remote_get(
		$geocode_url,
		array(
			'timeout' => 20,
		)
	);

	if ( is_wp_error( $geocode_response ) ) {
		fi_log(
			'GEOCOD API ERROR: ' . $geocode_response->get_error_message(),
			__FILE__,
			__LINE__
		);

		return array( 'officials' => array(), 'city' => '', 'state' => '' );
	}

	$response_code = wp_remote_retrieve_response_code( $geocode_response );

	if ( 200 !== $response_code ) {
		fi_log(
			'GEOCOD API HTTP ERROR: ' . $response_code,
			__FILE__,
			__LINE__
		);

		return array( 'officials' => array(), 'city' => '', 'state' => '' );
	}

	$response_body = wp_remote_retrieve_body( $geocode_response );
	fi_log( 'GEOCOD API RAW RESPONSE: ' . substr( $response_body, 0, 500 ), __FILE__, __LINE__ );

	$geocode_data = json_decode( $response_body, true );

	if ( ! is_array( $geocode_data ) ) {
		fi_log( 'GEOCOD FETCH ERROR: Invalid JSON response', __FILE__, __LINE__ );
		fi_log(
			'GEOCOD API ERROR: Invalid JSON response.',
			__FILE__,
			__LINE__
		);

		return array( 'officials' => array(), 'city' => '', 'state' => '' );
	}

	fi_log( 'GEOCOD API RESULTS COUNT: ' . ( isset( $geocode_data['results'] ) ? count( $geocode_data['results'] ) : 0 ), __FILE__, __LINE__ );

	$fields = $geocode_data['results'][0]['fields'] ?? array();
	fi_log( 'GEOCOD API FIELDS: ' . json_encode( array_keys( $fields ) ), __FILE__, __LINE__ );

	if ( ! is_array( $fields ) || empty( $fields ) ) {
		fi_log( 'GEOCOD FETCH ERROR: No fields data in API response', __FILE__, __LINE__ );
		$addr_comp    = $geocode_data['results'][0]['address_components'] ?? array();
		$empty_result = array( 'officials' => array(), 'city' => $addr_comp['city'] ?? '', 'state' => $addr_comp['state'] ?? '' );
		fi_cache( $cache_key, $empty_result );
		return $empty_result;
	}

	$officials       = array();
	$senators       = array();
	$representatives = array();

	/*
	 * Process congressional legislators.
	 *
	 * ZIP-only searches may return overlapping congressional districts, so
	 * legislators are keyed by type and name to prevent duplicate senators.
	 */
	$congressional_districts = $fields['congressional_districts'] ?? array();
	fi_log( 'GEOCOD CONGRESSIONAL DISTRICTS COUNT: ' . ( is_array( $congressional_districts ) ? count( $congressional_districts ) : 0 ), __FILE__, __LINE__ );

	if ( is_array( $congressional_districts ) ) {
		foreach ( $congressional_districts as $district ) {
			if ( ! is_array( $district ) ) {
				continue;
			}

			$current_legislators = $district['current_legislators'] ?? array();

			if ( ! is_array( $current_legislators ) || empty( $current_legislators ) ) {
				continue;
			}

			$district_name = isset( $district['name'] )
				? $district['name']
				: '';

			foreach ( $current_legislators as $legislator ) {
				if ( ! is_array( $legislator ) ) {
					continue;
				}

				$bio = isset( $legislator['bio'] ) && is_array( $legislator['bio'] )
					? $legislator['bio']
					: array();

				$legislator_type = isset( $legislator['type'] )
					? $legislator['type']
					: '';

				$key = sanitize_key(
					$legislator_type . '-' .
					( $bio['first_name'] ?? '' ) . '-' .
					( $bio['last_name'] ?? '' )
				);

				$official = fi_geocod_build_official_record(
					$legislator,
					ucfirst( $legislator_type ) . ' - ' . $district_name,
					$district_name
				);

				if ( 'representative' === $legislator_type ) {
					$representatives[ $key ] = $official;
				} else {
					$senators[ $key ] = $official;
				}
			}
		}
	}

	$officials = array_merge(
		array_values( $senators ),
		array_values( $representatives )
	);
	fi_log( 'GEOCOD CONGRESSIONAL OFFICIALS: ' . count( $senators ) . ' senators, ' . count( $representatives ) . ' representatives', __FILE__, __LINE__ );

	/*
	 * Process state legislative districts.
	 */
	$state_districts = $fields['state_legislative_districts'] ?? array();
	fi_log( 'GEOCOD STATE DISTRICTS: ' . ( is_array( $state_districts ) ? json_encode( array_keys( $state_districts ) ) : 'none' ), __FILE__, __LINE__ );

	if ( is_array( $state_districts ) ) {
		/*
		 * State senators.
		 */
		$state_senate_districts = $state_districts['senate'] ?? array();

		if ( is_array( $state_senate_districts ) ) {
			foreach ( $state_senate_districts as $district ) {
				if ( ! is_array( $district ) ) {
					continue;
				}

				$current_legislators = $district['current_legislators'] ?? array();

				if ( ! is_array( $current_legislators ) || empty( $current_legislators ) ) {
					continue;
				}

				$district_name = isset( $district['name'] )
					? $district['name']
					: '';

				foreach ( $current_legislators as $legislator ) {
					if ( ! is_array( $legislator ) ) {
						continue;
					}

					$officials[] = fi_geocod_build_official_record(
						$legislator,
						'State Senator - ' . $district_name,
						$district_name
					);
				}
			}
		}

		/*
		 * State representatives.
		 */
		$state_house_districts = $state_districts['house'] ?? array();

		if ( is_array( $state_house_districts ) ) {
			foreach ( $state_house_districts as $district ) {
				if ( ! is_array( $district ) ) {
					continue;
				}

				$current_legislators = $district['current_legislators'] ?? array();

				if ( ! is_array( $current_legislators ) || empty( $current_legislators ) ) {
					continue;
				}

				$district_name = isset( $district['name'] )
					? $district['name']
					: '';

				foreach ( $current_legislators as $legislator ) {
					if ( ! is_array( $legislator ) ) {
						continue;
					}

					$officials[] = fi_geocod_build_official_record(
						$legislator,
						'State Representative - ' . $district_name,
						$district_name
					);
				}
			}
		}
	}

	$officials = fi_geocod_merge_legislators_to_officials( $officials );
	fi_log( 'GEOCOD MERGE COMPLETE: ' . count( $officials ) . ' officials after merge', __FILE__, __LINE__ );

	$addr_comp = $geocode_data['results'][0]['address_components'] ?? array();
	$result    = array( 'officials' => $officials, 'city' => $addr_comp['city'] ?? '', 'state' => $addr_comp['state'] ?? '' );
	fi_cache( $cache_key, $result );
	fi_log( 'GEOCOD CACHE SAVED: cache_key=' . $cache_key . ', count=' . count( $officials ), __FILE__, __LINE__ );

	fi_log( 'GEOCOD FETCH END: Returning ' . count( $officials ) . ' officials', __FILE__, __LINE__ );
	return $result;
}

/**
 * Merge Freedom Index legislator data into Geocodio official records.
 *
 * @param array $officials Geocodio official records.
 *
 * @return array
 */
function fi_geocod_merge_legislators_to_officials( $officials ) {
	if ( ! is_array( $officials ) ) {
		return array();
	}

	$merged = array();

	foreach ( $officials as $official ) {
		if ( ! is_array( $official ) ) {
			continue;
		}

		$references = isset( $official['references'] ) && is_array( $official['references'] )
			? $official['references']
			: array();

		$legislator_data = fi_geocod_get_legislator_data( $references );
		$official        = array_merge( $official, $legislator_data );

		$legislator_id = isset( $official['legislator']['id'] )
			? absint( $official['legislator']['id'] )
			: 0;

		if ( $legislator_id > 0 ) {
			fi_geocod_update_legislator( $official );
		}

		$merged[] = $official;
	}

	return $merged;
}

/**
 * Find a Freedom Index legislator using external IDs.
 *
 * @param array $references Geocodio external legislator IDs.
 *
 * @return array
 */
function fi_geocod_get_legislator_data( $references ) {
	if ( ! is_array( $references ) ) {
		$references = array();
	}

	$legislator = fi_legislator_get_by_external_id( $references );

	if ( ! $legislator || ! is_array( $legislator ) ) {
		return array();
	}

	$legislator_id = isset( $legislator['id'] ) ? absint( $legislator['id'] ) : 0;

	if ( $legislator_id < 1 ) {
		return array();
	}

	$legislator_url = $legislator['url'] ?? home_url('/legislator/' . $legislator_id . '/');

	$image_id  = isset( $legislator['image_id'] ) ? absint( $legislator['image_id'] ) : 0;
	$image_url = $legislator['image_url'] ?? '';

	$legislator_data = array(
		'name'        => $legislator['display_name'] ?? '',
		'score'       => $legislator['score'] ?? '',
		'score_label' => 'Freedom Score',
		'legislator'  => array(
			'id'         => $legislator_id,
			'url'        => $legislator_url,
			'image_id'   => $image_id,
			'first_name' => $legislator['first_name'] ?? '',
			'last_name'  => $legislator['last_name'] ?? '',
			'district'   => $legislator['district'] ?? '',
			'gov'        => $legislator['gov'] ?? '',
			'state'      => $legislator['state'] ?? '',
			'state_name' => $legislator['state_name'] ?? '',
		),
	);

	if ( ! empty( $image_url ) ) {
		$legislator_data['photo_url'] = $image_url;
	}

	return $legislator_data;
}

/**
 * Update a Freedom Index legislator using current Geocodio data.
 *
 * @param array $official Merged Geocodio and Freedom Index official record.
 *
 * @return void
 */
function fi_geocod_update_legislator( $official ) {
	if ( ! is_array( $official ) ) {
		return;
	}

	$legislator_id = isset( $official['legislator']['id'] ) ? absint( $official['legislator']['id'] ) : 0;

	if ( $legislator_id < 1 ) {
		return;
	}

	$references = isset( $official['references'] ) && is_array( $official['references'] ) ? $official['references'] : array();

	/*
	 * Reference fields stored directly on the legislator table.
	 */
	$direct_fields = array(
		'bioguide_id',
		'legiscan_id',
		'govtrack_id',
		'votesmart_id',
		'ballotpedia_id',
		'openstates_id',
	);

	$legislator_update = array();

	foreach ( $direct_fields as $field ) {
		if (
			isset( $references[ $field ] ) &&
			'' !== $references[ $field ]
		) {
			$legislator_update[ $field ] = $references[ $field ];
		}
	}

	/*
	 * Geocodio fields stored in the legislator meta column.
	 */
	$meta_fields = array(
		'contact'    => array(
			'url',
			'address',
			'phone',
			'contact_form',
		),
		'social'     => array(
			'rss_url',
			'twitter',
			'facebook',
			'youtube',
			'youtube_id',
		),
		'bio'        => array(
			'birthday',
			'gender',
			'party',
			'photo_url',
			'photo_attribution',
		),
		'birthday',
		'gender',
		'seniority',
		'references' => array(
			'openstates_id',
			'wikipedia_id',
			'thomas_id',
			'opensecrets_id',
			'lis_id',
			'washington_post_id',
		),
	);

	$meta_data = array();

	foreach ( $meta_fields as $section_name => $fields ) {
		if ( is_array( $fields ) ) {
			$section = 'references' === $section_name
				? $references
				: (
					isset( $official[ $section_name ] ) &&
					is_array( $official[ $section_name ] )
						? $official[ $section_name ]
						: array()
				);

			foreach ( $fields as $field ) {
				if (
					isset( $section[ $field ] ) &&
					'' !== $section[ $field ]
				) {
					$meta_data[ $field ] = $section[ $field ];
				}
			}

			continue;
		}

		$field = $fields;

		if (
			isset( $official[ $field ] ) &&
			'' !== $official[ $field ]
		) {
			$meta_data[ $field ] = $official[ $field ];
		}
	}

	if ( ! empty( $meta_data ) ) {
		$legislator_update['meta'] = $meta_data;
	}

	if ( ! empty( $legislator_update ) ) {
		fi_legislator_save(
			$legislator_update,
			$legislator_id
		);
	}

}

/**
 * Retrieve officials using the submitted public search fields.
 *
 * This is the primary public entry point for the legislator search.
 *
 * @return array
 */
function fi_geocod_get_officials() {
	fi_log( 'GEOCOD START: fi_geocod_get_officials() called', __FILE__, __LINE__ );
	fi_log( 'GEOCOD POST data: ' . json_encode( $_POST ), __FILE__, __LINE__ );

	$address         = fi_geocod_build_submitted_address();
	fi_log( 'GEOCOD ADDRESS BUILT: ' . $address, __FILE__, __LINE__ );

	$address_encoded = rawurlencode( $address );
	fi_log( 'GEOCOD ADDRESS ENCODED: ' . $address_encoded, __FILE__, __LINE__ );

	$fetch           = fi_geocod_fetch_officials( $address_encoded );
	$officials       = $fetch['officials'];
	fi_log( 'GEOCOD FETCH COMPLETE: Found ' . count( $officials ) . ' officials', __FILE__, __LINE__ );

	$data = array(
		'address'   => $address,
		'officials' => $officials,
	);
	fi_log( 'GEOCOD FINAL RESULT: ' . json_encode( $data ), __FILE__, __LINE__ );
	return $data;
}

/**
 * Retrieve officials for a user's saved dashboard address.
 *
 * This is the primary public entry point for dashboard address lookups.
 *
 * @param string $address Complete user address.
 *
 * @return array
 */
function fi_geocod_get_officials_for_user_dashboard( string $address ) {
	$address = trim( $address );

	if ( empty( $address ) ) {
		return array(
			'address'   => '',
			'officials' => array(),
		);
	}

	$address_encoded = rawurlencode( $address );
	$fetch     = fi_geocod_fetch_officials( $address_encoded );
	$officials = $fetch['officials'];

	return array(
		'address'   => $address,
		'officials' => $officials,
	);
}
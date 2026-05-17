<?php
namespace FI\Core {

if (!defined('ABSPATH')) exit;

/**
 * Reports Payload Handler
 * 
 * Handles all report payload_json operations including normalization, extraction, and validation.
 * Similar to LegislatorsMeta but for report payload data.
 */
final class ReportsPayload {

	/**
	 * Normalize payload array from various formats to standard structure
	 * Handles migration from legacy formats and ensures consistent structure
	 * 
	 * @param array|string|null $payload Raw payload from database (JSON string or array)
	 * @return array Normalized payload array
	 */
	public static function normalize($payload): array {
		// Decode if string
		if (is_string($payload)) {
			$decoded = json_decode($payload, true);
			if (!is_array($decoded)) {
				$decoded = [];
			}
		} elseif (is_array($payload)) {
			$decoded = $payload;
		} else {
			$decoded = [];
		}
		
		//RMFORMAT
		// Ensure required structure exists
		$normalized = [
			'content' => $decoded['content'] ?? '',
			'format' => $decoded['format'] ?? 'scorecard',
			'cph' => $decoded['cph'] ?? 'hide',
			'vote_start' => $decoded['vote_start'] ?? '1',
			'contact' => $decoded['contact'] ?? 'back',
			'constitution_qr' => $decoded['constitution_qr'] ?? 'none',
			'fi_vote_paging' => $decoded['fi_vote_paging'] ?? '2,3,3,2',
			'votes_h' => array_map('intval', (array) ($decoded['votes_h'] ?? [])),
			'votes_s' => array_map('intval', (array) ($decoded['votes_s'] ?? [])),
			'votes_h_order' => array_map('intval', (array) ($decoded['votes_h_order'] ?? [])), // Manual sort order
			'votes_s_order' => array_map('intval', (array) ($decoded['votes_s_order'] ?? [])), // Manual sort order
			'report_pdf_url' => $decoded['report_pdf_url'] ?? '',
		];
		
		// Preserve legacy vote IDs if they exist (for reference)
		if (isset($decoded['legacy_votes_h'])) {
			$normalized['legacy_votes_h'] = array_map('intval', (array) $decoded['legacy_votes_h']);
		}
		if (isset($decoded['legacy_votes_s'])) {
			$normalized['legacy_votes_s'] = array_map('intval', (array) $decoded['legacy_votes_s']);
		}
		
		// Preserve any other fields that might exist
		foreach ($decoded as $key => $value) {
			if (!isset($normalized[$key]) && !in_array($key, ['legacy_votes_h', 'legacy_votes_s'], true)) {
				$normalized[$key] = $value;
			}
		}
		
		return $normalized;
	}
	
	/**
	 * Build payload from form submission data
	 * Validates and sanitizes all fields
	 * 
	 * @param array $submitted_data Form submission data
	 * @param array|null $existing_payload Existing payload to merge with (optional)
	 * @return array Normalized payload array ready for storage
	 */
	public static function build_payload(array $submitted_data, ?array $existing_payload = null): array {
		// Start with existing payload if provided
		if ($existing_payload !== null) {
			$payload = self::normalize($existing_payload);
		} else {
			$payload = self::normalize([]);
		}

		// Process content (intro text): unslash then sanitize so apostrophes etc. store correctly
		if (isset($submitted_data['intro_text'])) {
			$payload['content'] = fi_prepare_richedit_save($submitted_data['intro_text']);
		}
		
		//RMFORMAT
		// Process report format
		if (isset($submitted_data['report_format'])) {
			$format = sanitize_text_field($submitted_data['report_format']);
			$payload['format'] = in_array($format, ['scorecard', 'freedomindex'], true) ? $format : 'scorecard';
		}
		
		// Process CPH (Financial Effect Per Household)
		if (isset($submitted_data['report_cph'])) {
			$cph = sanitize_text_field($submitted_data['report_cph']);
			$payload['cph'] = in_array($cph, ['show', 'hide'], true) ? $cph : 'hide';
		}
		
		// Process vote start number
		if (isset($submitted_data['vote_start'])) {
			$payload['vote_start'] = sanitize_text_field($submitted_data['vote_start']);
		}
		
		// Process contact location
		if (isset($submitted_data['contact_location'])) {
			$contact = sanitize_text_field($submitted_data['contact_location']);
			$payload['contact'] = in_array($contact, ['front', 'back'], true) ? $contact : 'back';
		}
		
		// Process constitution QR code location
		if (isset($submitted_data['constitution_qr'])) {
			$qr = sanitize_text_field($submitted_data['constitution_qr']);
			$payload['constitution_qr'] = in_array($qr, ['none', 'front', 'back'], true) ? $qr : 'none';
		}
		
		// Process vote paging
		if (isset($submitted_data['fi_vote_paging'])) {
			$payload['fi_vote_paging'] = sanitize_text_field($submitted_data['fi_vote_paging']);
		}

		// Process Freedom Index PDF URL
		if (isset($submitted_data['report_pdf_url'])) {
			$payload['report_pdf_url'] = sanitize_text_field($submitted_data['report_pdf_url']);
		}

		// Process vote selections
		if (isset($submitted_data['selected_votes_h']) && is_array($submitted_data['selected_votes_h'])) {
			$payload['votes_h'] = array_map('intval', array_filter($submitted_data['selected_votes_h'], 'is_numeric'));
		}
		
		if (isset($submitted_data['selected_votes_s']) && is_array($submitted_data['selected_votes_s'])) {
			$payload['votes_s'] = array_map('intval', array_filter($submitted_data['selected_votes_s'], 'is_numeric'));
		}
		
		// Process vote sort order (manual ordering)
		if (isset($submitted_data['votes_h_order']) && is_array($submitted_data['votes_h_order'])) {
			$payload['votes_h_order'] = array_map('intval', array_filter($submitted_data['votes_h_order'], 'is_numeric'));
		}
		
		if (isset($submitted_data['votes_s_order']) && is_array($submitted_data['votes_s_order'])) {
			$payload['votes_s_order'] = array_map('intval', array_filter($submitted_data['votes_s_order'], 'is_numeric'));
		}
		
		// Clean up empty arrays
		if (empty($payload['votes_h'])) {
			$payload['votes_h'] = [];
		}
		if (empty($payload['votes_s'])) {
			$payload['votes_s'] = [];
		}
		
		return $payload;
	}
	
	/**
	 * Get a specific value from payload
	 * 
	 * @param array|string|null $payload Payload data
	 * @param string $key Key to retrieve
	 * @param mixed $default Default value if key doesn't exist
	 * @return mixed
	 */
	public static function get($payload, string $key, $default = null) {
		$normalized = self::normalize($payload);
		return $normalized[$key] ?? $default;
	}
	
	/**
	 * Validate payload structure
	 * 
	 * @param array $payload Payload to validate
	 * @return array ['valid' => bool, 'errors' => array]
	 */
	public static function validate(array $payload): array {
		$errors = [];
		
		//RMFORMAT
		
		// Validate format
		if (isset($payload['format']) && !in_array($payload['format'], ['scorecard', 'freedomindex'], true)) {
			$errors[] = 'Invalid report format';
		}
		
		// Validate CPH
		if (isset($payload['cph']) && !in_array($payload['cph'], ['show', 'hide'], true)) {
			$errors[] = 'Invalid CPH value';
		}
		
		// Validate contact location
		if (isset($payload['contact']) && !in_array($payload['contact'], ['front', 'back'], true)) {
			$errors[] = 'Invalid contact location';
		}
		
		// Validate constitution QR
		if (isset($payload['constitution_qr']) && !in_array($payload['constitution_qr'], ['none', 'front', 'back'], true)) {
			$errors[] = 'Invalid constitution QR location';
		}
		
		// Validate vote arrays are arrays of integers
		if (isset($payload['votes_h']) && !is_array($payload['votes_h'])) {
			$errors[] = 'votes_h must be an array';
		}
		if (isset($payload['votes_s']) && !is_array($payload['votes_s'])) {
			$errors[] = 'votes_s must be an array';
		}
		
		return [
			'valid' => empty($errors),
			'errors' => $errors
		];
	}
}

}

// Global helper functions
namespace {
	
	/**
	 * Normalize report payload
	 * 
	 * @param array|string|null $payload Raw payload data
	 * @return array Normalized payload
	 */
	function fi_report_payload_normalize($payload): array {
		return \FI\Core\ReportsPayload::normalize($payload);
	}
	
	/**
	 * Build report payload from form data
	 * 
	 * @param array $submitted_data Form submission data
	 * @param array|null $existing_payload Existing payload to merge with
	 * @return array Normalized payload
	 */
	function fi_report_payload_build(array $submitted_data, ?array $existing_payload = null): array {
		return \FI\Core\ReportsPayload::build_payload($submitted_data, $existing_payload);
	}
	
	/**
	 * Get value from report payload
	 * 
	 * @param array|string|null $payload Payload data
	 * @param string $key Key to retrieve
	 * @param mixed $default Default value
	 * @return mixed
	 */
	function fi_report_payload_get($payload, string $key, $default = null) {
		return \FI\Core\ReportsPayload::get($payload, $key, $default);
	}
	
	/**
	 * Validate report payload
	 * 
	 * @param array $payload Payload to validate
	 * @return array ['valid' => bool, 'errors' => array]
	 */
	function fi_report_payload_validate(array $payload): array {
		return \FI\Core\ReportsPayload::validate($payload);
	}
}


<?php
namespace FI\Core{

	if (!defined('ABSPATH')) exit;

	/**
	* Government Constants and Helpers
	* 
	* Centralized government management for the Freedom Index system.
	* All government codes are 2-letter state abbreviations or 'US' for Congress.
	*/

	final class Governments {

		/**
		* US (Congress) government code
		*/
		public const US = 'US';

		/**
		* Validate government code (2-letter state or 'US')
		*/
		public static function validate(string $gov): bool {
			return array_key_exists(strtoupper($gov), FI_GOVERNMENTS);
		}
		
		/**
		* Get government name from code
		*/
		public static function get_name(string $gov): string {
			return FI_GOVERNMENTS[strtoupper($gov)] ?? 'Unknown';
		}
		
		/**
		* Normalize government code to uppercase
		*/
		public static function normalize(string $gov): string {
			return strtoupper(trim($gov));
		}

		/**
		* Check if government is US (Congress)
		*/
		public static function is_us(string $gov): bool {
			return self::normalize($gov) === self::US;
		}

		/**
		* Check if government is a state
		*/
		public static function is_state(string $gov): bool {
			return in_array(self::normalize($gov), FI_STATES);
		}

		/**
		* Get all government codes as array
		*/
		public static function get_all_codes(): array {
			return array_keys(FI_GOVERNMENTS);
		}

		/**
		* Get all government names as array
		*/
		public static function get_all_names(): array {
			return array_values(FI_GOVERNMENTS);
		}

		/**
		* Get government options for select dropdowns
		*/
		public static function get_options(): array {
			$options = [];
			foreach (FI_GOVERNMENTS as $code => $name) {
				$options[$code] = $name;
			}
			return $options;
		}

		/**
		* Get state options only (excludes US)
		*/
		public static function get_state_options(): array {
			$options = [];
			foreach (FI_STATES as $code) {
				$options[$code] = FI_GOVERNMENTS[$code];
			}
			return $options;
		}

		/**
		* Get both Senate (S) and House (H) chamber info for a government.
		* Returns array: ['S' => [...], 'H' => [...]]
		* Defaults S to 'default', only overrides H if a custom value for this gov exists.
		*
		* @param string $gov Government code
		* @return array Array with keys 'S' and 'H'
		*/
		public static function get_chamber_info(string $gov): array {
			$gov = self::normalize($gov);
			/* USE HYDRATED CHAMBERS
			$default_labels = FI_CHAMBERS['default'];
			$gov_labels = FI_CHAMBERS[$gov] ?? [];
			// Always use 'default' S
			$out = [
				'S' => $default_labels['S'] ?? [],
				'H' => $default_labels['H'] ?? [],
			];

			// If government has a special value for 'H', use it
			if (isset($gov_labels['H']) && !empty($gov_labels['H'])) {
				$out['H'] = $gov_labels['H'];
			}
			*/
			return FI_CHAMBERS[$gov] ?? [];
		}

		/**
		* Get all chamber options for a government
		* 
		* @param string $gov Government code
		* @return array Array of chamber codes => labels
		* FI_CHAMBERS should be hydrated for all governments.
		*/
		public static function get_chamber_options(string $gov): array {
			$gov = self::normalize($gov);
			/* USE HYDRATED CHAMBERS
			$default_labels = FI_CHAMBERS['default'];
			$gov_labels = FI_CHAMBERS[$gov] ?? [];
			return array_replace($default_labels, $gov_labels);
			*/
			return FI_CHAMBERS[$gov] ?? [];
		}

		/**
		* Get chamber label for a government
		* Returns the chamber name (e.g., "Representative", "Senator", "Delegate")
		* 
		* @param string $gov Government code
		* @param string $chamber Chamber code (H or S)
		* @return string Chamber label
		*/
		public static function get_chamber_label(string $gov, string $chamber): string {
			$gov = self::normalize($gov);
			$chamber = strtoupper($chamber);
			
			// Get labels for this government, fallback to default

			/* USE HYDRATED CHAMBERS
			$gov_labels = FI_CHAMBERS[$gov] ?? [];
			$default_labels = FI_CHAMBERS['default'];
			
			// For Senate, always use default (same for all governments)
			if ($chamber === 'S') {
				return $default_labels['S']['chamber'] ?? 'Senate';
			}

			// For Representative, check for exceptions first, then default
			if ($chamber === 'H') {
				// Check if this gov has an exception for R
				if (isset($gov_labels['H']) && !empty($gov_labels['H'])) {
					return $gov_labels['H']['chamber'] ?? $default_labels['H']['chamber'] ?? 'House';
				}
				// Use default
				return $default_labels['H']['chamber'] ?? 'House';
			}
			*/
			$chamber = FI_CHAMBERS[$gov][$chamber]['chamber'] ?? $chamber;
			return ucfirst($chamber);
		}

		/* Name: Senator, Representative, Assemblyperson, etc. */
		public static function get_chamber_title(string $gov, string $chamber): string {
			$gov = self::normalize($gov);
			$chamber = strtoupper($chamber);
			
			// Get labels for this government, fallback to default
			/* USE HYDRATED CHAMBERS
			$gov_labels = FI_CHAMBERS[$gov] ?? [];
			$default_labels = FI_CHAMBERS['default'];
			
			// For Senate, always use default (same for all governments)
			if ($chamber === 'S') {
				return $default_labels['S']['title'] ?? 'Senator';
			}

			// For Representative, check for exceptions first, then default
			if ($chamber === 'H') {
				// Check if this gov has an exception for R
				if (isset($gov_labels['H']) && !empty($gov_labels['H'])) {
					return $gov_labels['H']['title'] ?? $default_labels['H']['title'] ?? 'Representative';
				}
				// Use default
				return $default_labels['H']['title'] ?? 'Representative';
			}
			return ucfirst($chamber);
			*/
			$title = FI_CHAMBERS[$gov][$chamber]['title'] ?? $chamber;
			return $title;
		}


		/**
		* Check if government has unicameral legislature
		* 
		* @param string $gov Government code
		* @return bool True if unicameral
		*/
		public static function is_unicameral(string $gov): bool {
			$gov = self::normalize($gov);
			//CHAMBERFLAG
			return isset(FI_CHAMBERS[$gov]) && !isset(FI_CHAMBERS[$gov]['H']);
		}

		/**
		* Get congressional district count for a state (US only)
		* 
		* @param string $state State code (2-letter)
		* @return int|null Number of districts or null if not found
		*/
		public static function get_congressional_district_count(string $state): ?int {
			$state = self::normalize($state);
			return FI_CONGRESSIONAL_DISTRICTS[$state] ?? null;
		}

		/**
		* Get state legislative district count
		* 
		* @param string $state State code (2-letter)
		* @param string $chamber Chamber code (H for House/Representative, S for Senate)
		* @return int|null Number of districts or null if not found
		*/
		public static function get_state_district_count(string $state, string $chamber): ?int {
			$state = self::normalize($state);
			$chamber = strtoupper($chamber);
			
			if ($chamber === 'S') {
				return FI_STATE_DISTRICTS['senate'][$state] ?? null;
			}
			//CHAMBERFLAG
			elseif ($chamber === 'H') {
				return FI_STATE_DISTRICTS['house'][$state] ?? null;
			}
			
			return null;
		}

		/**
		* Get district count for a government and chamber
		* 
		* @param string $gov Government code (US for Congress, or state code)
		* @param string $chamber Chamber code (H or S)
		* @param string|null $state State code (required for US, ignored for state governments)
		* @return int|null Number of districts or null if not found
		* 
		* NOTE: For US government:
		* - Chamber 'H' (Representative): Returns district count for the specified state
		* - Chamber 'S' (Senator): Returns null (U.S. Senators are elected at-large, not by district)
		* 
		* For State governments:
		* - Chamber 'H' (Representative/House): Returns House district count
		* - Chamber 'S' (Senator): Returns Senate district count
		* - Nebraska (unicameral): Only 'S' chamber is valid, returns 49 districts
		*/
		public static function get_district_count(string $gov, string $chamber, ?string $state = null): ?int {
			$gov = self::normalize($gov);
			$chamber = strtoupper($chamber);
			
			if ($gov === self::US) {
				// US: use state parameter
				if (!$state) {
					return null;
				}
				// US Senators are at-large (no districts)
				if ($chamber === 'S') {
					return null;
				}
				// Only Representatives/House use districts
				return self::get_congressional_district_count($state);
			} else {
				// State government - both chambers use districts
				return self::get_state_district_count($gov, $chamber);
			}
		}


		/**
		* Generate district options for select dropdown
		* Returns array of district numbers formatted as "1st", "2nd", "3rd", etc.
		* 
		* @param string $gov Government code
		* @param string $chamber Chamber code (H or S)
		* @param string|null $state State code (required for US)
		* @return array Array of district options ['value' => '1', 'label' => '1st District'], etc.
		* 
		* USAGE NOTES:
		* - For US Senators (US, S): Returns empty array (no districts - at-large elections)
		* - For US Representatives (US, R, state): Returns districts for that state
		* - For State Senators: Returns "1st State Senate District", "2nd State Senate District", etc.
		* - For State Representatives: Returns "1st District", "2nd District", etc.
		* 
		* When displaying in forms, you may want to customize labels:
		* - US: "TX 1st Congressional District"
		* - State Senate: "1st State Senate District" or "State Senate District 1"
		* - State House: "1st District" or "House District 1"
		*/
		public static function get_district_options(string $gov, string $chamber, ?string $state = null): array {
			$count = self::get_district_count($gov, $chamber, $state);
			
			if (!$count || $count <= 0) {
				return [];
			}
			
			$options = [];
			for ($i = 1; $i <= $count; $i++) {
				$options[] = [
					'value' => (string) $i,
					'label' => fi_format_ordinal($i) . ' District'
				];
			}
			
			return $options;
		}

		/**
		* Generate district options as simple array
		* Returns array where keys are district numbers and values are formatted labels
		* 
		* @param string $gov Government code
		* @param string $chamber Chamber code (H or S)
		* @param string|null $state State code (required for US)
		* @return array Array of ['1' => '1st District', '2' => '2nd District', ...]
		*/
		public static function get_district_options_array(string $gov, string $chamber, ?string $state = null): array {
			$options = self::get_district_options($gov, $chamber, $state);
			$result = [];
			
			foreach ($options as $option) {
				$result[$option['value']] = $option['label'];
			}
			
			return $result;
		}

	}
}


namespace {
	function fi_govs(): array {
		return FI_GOVERNMENTS;
	}

	function fi_gov_name(string $gov): string {
		return \FI\Core\Governments::get_name($gov);
	}

	function get_fi_gov_links(): array {
		$links = [];
		foreach (fi_govs() as $gov_code => $gov_name) {
			$gov_slug = strtolower($gov_code);
			$links[$gov_code] = [
				'url' => home_url( '/' . $gov_slug . '/'),
				'name' => $gov_name
			];
		}
		return $links;
	}

	/**
	 * Get congressional district count for a state (US only)
	 * 
	 * @param string $state State code (2-letter)
	 * @return int|null Number of districts or null if not found
	 */
	function fi_district_congressional_count(string $state): ?int {
		return \FI\Core\Governments::get_congressional_district_count($state);
	}

	/**
	 * Get state legislative district count
	 * 
	 * @param string $state State code (2-letter)
	 * @param string $chamber Chamber code (H for House, S for Senate)
	 * @return int|null Number of districts or null if not found
	 */
	function fi_district_state_count(string $state, string $chamber): ?int {
		return \FI\Core\Governments::get_state_district_count($state, $chamber);
	}

	/**
	 * Get district count for a government and chamber
	 * 
	 * @param string $gov Government code (US for Congress, or state code)
	 * @param string $chamber Chamber code (H or S)
	 * @param string|null $state State code (required for US)
	 * @return int|null Number of districts or null if not found
	 */
	function fi_district_count(string $gov, string $chamber, ?string $state = null): ?int {
		return \FI\Core\Governments::get_district_count($gov, $chamber, $state);
	}

	/**
	 * Generate district options for select dropdown
	 * 
	 * @param string $gov Government code
	 * @param string $chamber Chamber code (H or S)
	 * @param string|null $state State code (required for US)
	 * @return array Array of district options ['value' => '1', 'label' => '1st District'], etc.
	 */
	function fi_district_options(string $gov, string $chamber, ?string $state = null): array {
		return \FI\Core\Governments::get_district_options($gov, $chamber, $state);
	}

	/**
	 * Generate district options as simple array
	 * 
	 * @param string $gov Government code
	 * @param string $chamber Chamber code (H or S)
	 * @param string|null $state State code (required for US)
	 * @return array Array of ['1' => '1st District', '2' => '2nd District', ...]
	 */
	function fi_district_options_array(string $gov, string $chamber, ?string $state = null): array {
		return \FI\Core\Governments::get_district_options_array($gov, $chamber, $state);
	}

	/**
	 * Format number as ordinal string (1st, 2nd, 3rd, etc.)
	 * 
	 * @param int $number Number to format
	 * @return string Formatted ordinal
	 */
	function fi_format_ordinal(int $number): string {
		$suffixes = ['th', 'st', 'nd', 'rd'];
		$v = $number % 100;
		
		// Special cases for 11th, 12th, 13th
		if ($v >= 11 && $v <= 13) {
			return $number . 'th';
		}
		
		return $number . ($suffixes[$number % 10] ?? 'th');
	}

	//Get full name from state code
	function fi_state_name(string $state): string {
		$govs = fi_govs();
		return $govs[$state] ?? '';
	}

	function fi_state_options(): array {
		return \FI\Core\Governments::get_state_options();
	}

	/**
	 * Format district number as ordinal string
	 * 
	 * @param int|string $district District number
	 * @return string Formatted as "1st", "2nd", "3rd", etc.
	 */
	function fi_district_format(int|string $district): string {
		$number = is_numeric($district) ? (int) $district : (int) preg_replace('/\D/', '', $district);
		return fi_format_ordinal($number);
	}


	function fi_chamber_options(string $gov): array {
		return \FI\Core\Governments::get_chamber_options($gov);
	}

	function fi_chamber_info(string $gov): array {
		return \FI\Core\Governments::get_chamber_info($gov);
	}

	function fi_chamber_label(string $gov, string $chamber): string {
		return \FI\Core\Governments::get_chamber_label($gov, $chamber); //return Senate, House, Assembly
	}

	function fi_chamber_title(string $gov, string $chamber): string {
		return \FI\Core\Governments::get_chamber_title($gov, $chamber); //return Senator, Representative, Assemblymember
	}

	function fi_gov_is_unicameral(string $gov): bool {
		return \FI\Core\Governments::is_unicameral($gov);
	}

	function fi_government_has_house(string $gov): bool {
		return !\FI\Core\Governments::is_unicameral($gov);
	}

	/**
	 * Validate government code
	 * 
	 * @param string $gov Government code (2-letter state or 'US')
	 * @return bool True if valid government code
	 */
	function fi_gov_validate(string $gov): bool {
		return \FI\Core\Governments::validate($gov);
	}

}
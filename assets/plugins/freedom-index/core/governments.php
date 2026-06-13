<?php
/**
 * Freedom Index Government Constants and Helpers
  * All government codes are 2-letter state abbreviations or 'US' for Congress.
 */

if (!defined('ABSPATH')) exit;

/**
 * US (Congress) government code.
 *
 * @return string
 */
function fi_gov_us_code(): string {
	return 'US';
}

/**
 * Normalize government code to uppercase.
 *
 * @param string $gov Government code.
 * @return string Normalized government code.
 */
function fi_gov_normalize(string $gov): string {
	return strtoupper(trim($gov));
}

/**
 * Validate government code.
 *
 * @param string $gov Government code (2-letter state or 'US').
 * @return bool True if valid government code.
 */
function fi_gov_validate(string $gov): bool {
	return array_key_exists(fi_gov_normalize($gov), FI_GOVERNMENTS);
}

/**
 * Get all governments.
 *
 * @return array Government codes => names.
 */
function fi_govs(): array {
	return FI_GOVERNMENTS;
}

/**
 * Get all government codes.
 *
 * @return array Government codes.
 */
function fi_gov_codes(): array {
	return array_keys(FI_GOVERNMENTS);
}

/**
 * Get all government names.
 *
 * @return array Government names.
 */
function fi_gov_names(): array {
	return array_values(FI_GOVERNMENTS);
}

/**
 * Get government name from code.
 *
 * @param string $gov Government code.
 * @return string Government name or Unknown.
 */
function fi_gov_name(string $gov): string {
	return FI_GOVERNMENTS[fi_gov_normalize($gov)] ?? 'Unknown';
}

/**
 * Get full state name from state code.
 *
 * @param string $state State code.
 * @return string State name or empty string.
 */
function fi_state_name(string $state): string {
	$govs = fi_govs();
	$state = fi_gov_normalize($state);
	return $govs[$state] ?? '';
}

/**
 * Check if government is US Congress.
 *
 * @param string $gov Government code.
 * @return bool
 */
function fi_gov_is_us(string $gov): bool {
	return fi_gov_normalize($gov) === fi_gov_us_code();
}

/**
 * Check if government is a state.
 *
 * @param string $gov Government code.
 * @return bool
 */
function fi_gov_is_state(string $gov): bool {
	return in_array(fi_gov_normalize($gov), FI_STATES, true);
}

/**
 * Get government options for select dropdowns.
 *
 * @return array Government codes => government names.
 */
function fi_gov_options(): array {
	$options = [];
	foreach (FI_GOVERNMENTS as $code => $name) {
		$options[$code] = $name;
	}
	return $options;
}

/**
 * Get state options only (excludes US).
 *
 * @return array State codes => state names.
 */
function fi_state_options(): array {
	$options = [];
	foreach (FI_STATES as $code) {
		$options[$code] = FI_GOVERNMENTS[$code] ?? $code;
	}
	return $options;
}

/**
 * Get government links.
 *
 * @return array Government link data keyed by government code.
 */
function get_fi_gov_links(): array {
	$links = [];
	foreach (fi_govs() as $gov_code => $gov_name) {
		$gov_slug = strtolower($gov_code);
		$links[$gov_code] = [
			'url'  => home_url('/' . $gov_slug . '/'),
			'name' => $gov_name,
		];
	}
	return $links;
}

/**
 * Get both Senate (S) and House (H) chamber info for a government.
 *
 * Returns array: ['S' => [...], 'H' => [...]]
 * FI_CHAMBERS should be hydrated for all governments.
 *
 * @param string $gov Government code.
 * @return array Array with chamber keys.
 */
function fi_chamber_info(string $gov): array {
	$gov = fi_gov_normalize($gov);
	return FI_CHAMBERS[$gov] ?? [];
}

/**
 * Get all chamber options for a government.
 *
 * @param string $gov Government code.
 * @return array Array of chamber codes => labels/data.
 */
function fi_chamber_options(string $gov): array {
	$gov = fi_gov_normalize($gov);
	return FI_CHAMBERS[$gov] ?? [];
}

/**
 * Get chamber label for a government.
 *
 * Returns the chamber name, e.g. Senate, House, Assembly.
 *
 * @param string $gov Government code.
 * @param string $chamber Chamber code.
 * @return string Chamber label.
 */
function fi_chamber_label(string $gov, string $chamber): string {
	$gov = fi_gov_normalize($gov);
	$chamber = strtoupper($chamber);
	$label = FI_CHAMBERS[$gov][$chamber]['chamber'] ?? $chamber;
	return ucfirst($label);
}

/**
 * Get chamber title for a government.
 *
 * Returns the member title, e.g. Senator, Representative, Assemblymember.
 *
 * @param string $gov Government code.
 * @param string $chamber Chamber code.
 * @return string Chamber title.
 */
function fi_chamber_title(string $gov, string $chamber): string {
	$gov = fi_gov_normalize($gov);
	$chamber = strtoupper($chamber);
	return FI_CHAMBERS[$gov][$chamber]['title'] ?? $chamber;
}

/**
 * Check if government has unicameral legislature.
 *
 * @param string $gov Government code.
 * @return bool True if unicameral.
 */
function fi_gov_is_unicameral(string $gov): bool {
	$gov = fi_gov_normalize($gov);
	return isset(FI_CHAMBERS[$gov]) && !isset(FI_CHAMBERS[$gov]['H']);
}

/**
 * Check whether government has a House chamber.
 *
 * @param string $gov Government code.
 * @return bool
 */
function fi_government_has_house(string $gov): bool {
	return !fi_gov_is_unicameral($gov);
}

/**
 * Get congressional district count for a state (US only).
 *
 * @param string $state State code.
 * @return int|null Number of districts or null if not found.
 */
function fi_district_congressional_count(string $state): ?int {
	$state = fi_gov_normalize($state);
	return FI_CONGRESSIONAL_DISTRICTS[$state] ?? null;
}

/**
 * Get state legislative district count.
 *
 * @param string $state State code.
 * @param string $chamber Chamber code (H for House/Representative, S for Senate).
 * @return int|null Number of districts or null if not found.
 */
function fi_district_state_count(string $state, string $chamber): ?int {
	$state = fi_gov_normalize($state);
	$chamber = strtoupper($chamber);

	if ($chamber === 'S') {
		return FI_STATE_DISTRICTS['senate'][$state] ?? null;
	}

	if ($chamber === 'H') {
		return FI_STATE_DISTRICTS['house'][$state] ?? null;
	}

	return null;
}

/**
 * Get district count for a government and chamber.
 *
 * NOTE: For US government:
 * - Chamber 'H': Returns congressional district count for the specified state.
 * - Chamber 'S': Returns null because U.S. Senators are elected at-large.
 *
 * For state governments:
 * - Chamber 'H': Returns House district count.
 * - Chamber 'S': Returns Senate district count.
 * - Nebraska unicameral: only 'S' chamber is valid.
 *
 * @param string $gov Government code (US for Congress, or state code).
 * @param string $chamber Chamber code (H or S).
 * @param string|null $state State code required for US House.
 * @return int|null Number of districts or null if not found.
 */
function fi_district_count(string $gov, string $chamber, ?string $state = null): ?int {
	$gov = fi_gov_normalize($gov);
	$chamber = strtoupper($chamber);

	if ($gov === fi_gov_us_code()) {
		if (!$state) {
			return null;
		}

		if ($chamber === 'S') {
			return null;
		}

		return fi_district_congressional_count($state);
	}

	return fi_district_state_count($gov, $chamber);
}

/**
 * Format number as ordinal string (1st, 2nd, 3rd, etc.).
 *
 * @param int $number Number to format.
 * @return string Formatted ordinal.
 */
function fi_format_ordinal(int $number): string {
	$suffixes = ['th', 'st', 'nd', 'rd'];
	$v = $number % 100;

	if ($v >= 11 && $v <= 13) {
		return $number . 'th';
	}

	return $number . ($suffixes[$number % 10] ?? 'th');
}

/**
 * Generate district options for select dropdown.
 *
 * @param string $gov Government code.
 * @param string $chamber Chamber code (H or S).
 * @param string|null $state State code required for US House.
 * @return array Array of district options ['value' => '1', 'label' => '1st District'].
 */
function fi_district_options(string $gov, string $chamber, ?string $state = null): array {
	$count = fi_district_count($gov, $chamber, $state);

	if (!$count || $count <= 0) {
		return [];
	}

	$options = [];
	for ($i = 1; $i <= $count; $i++) {
		$options[] = [
			'value' => (string) $i,
			'label' => fi_format_ordinal($i) . ' District',
		];
	}

	return $options;
}

/**
 * Generate district options as simple array.
 *
 * @param string $gov Government code.
 * @param string $chamber Chamber code (H or S).
 * @param string|null $state State code required for US House.
 * @return array Array of ['1' => '1st District', '2' => '2nd District'].
 */
function fi_district_options_array(string $gov, string $chamber, ?string $state = null): array {
	$options = fi_district_options($gov, $chamber, $state);
	$result = [];

	foreach ($options as $option) {
		$result[$option['value']] = $option['label'];
	}

	return $result;
}

/**
 * Format district number as ordinal string.
 *
 * @param int|string $district District number.
 * @return string Formatted as 1st, 2nd, 3rd, etc.
 */
function fi_district_format(int|string $district): string {
	$number = is_numeric($district) ? (int) $district : (int) preg_replace('/\D/', '', (string) $district);
	return fi_format_ordinal($number);
}

/**
 * Check if request is from a search engine crawler.
 *
 * Used to show all content to crawlers for SEO while paginating for users.
 *
 * @return bool True if crawler detected.
 */
function fi_is_crawler(): bool {
	$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
	if (empty($user_agent)) {
		return false;
	}

	$crawlers = [
		'googlebot',
		'bingbot',
		'slurp',
		'duckduckbot',
		'baiduspider',
		'yandexbot',
		'facebookexternalhit',
		'twitterbot',
		'rogerbot',
		'linkedinbot',
		'embedly',
		'quora link preview',
		'showyoubot',
		'outbrain',
		'pinterest',
		'slackbot',
		'vkshare',
		'w3c_validator',
		'petalbot',
		'ahrefsbot',
		'semrushbot',
		'mj12bot',
		'screaming frog',
		'bot',
		'crawler',
		'spider',
		'crawl',
	];

	$lower_ua = strtolower($user_agent);
	foreach ($crawlers as $crawler) {
		if (strpos($lower_ua, $crawler) !== false) {
			return true;
		}
	}

	return false;
}
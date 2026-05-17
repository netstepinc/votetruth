<?php if ( ! defined( 'ABSPATH' ) ) { exit; }


$governments = [
	'US' => 'Congress',
	'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
	'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia',
	'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
	'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
	'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri',
	'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
	'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
	'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
	'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
	'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming'
];
define('FI_GOVERNMENTS', $governments);


$states = [
	'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
	'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
	'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
	'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
	'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'
];
define('FI_STATES', $states);


/**
* Chamber labels by government
* Maps chamber codes (H/S) to display labels
* NOTE: This is chamber-centric.
*/
$chambers = [
	'default' => [
		'S' => ['short' => 'Sen.','title' => 'Senator', 'plural' => 'Senators', 'chamber' => 'Senate'],
		'H' => ['short' => 'Rep.','title' => 'Representative', 'plural' => 'Representatives', 'chamber' => 'House'],
	],
	'CA' => ['H' => ['short' => 'Mem.','title' => 'Assemblymember', 'plural' => 'Members', 'chamber' => 'Assembly'],],
	'MD' => ['H' => ['short' => 'Del.','title' => 'Delegate', 'plural' => 'Delegates', 'chamber' => 'House'],],
	'NJ' => ['H' => ['short' => 'Mem.','title' => 'Assemblymember', 'plural' => 'Members', 'chamber' => 'Assembly'],],
	'NV' => ['H' => ['short' => 'Mem.','title' => 'Assemblymember', 'plural' => 'Members', 'chamber' => 'Assembly'],],
	'NY' => ['H' => ['short' => 'Mem.','title' => 'Assemblymember', 'plural' => 'Members', 'chamber' => 'Assembly'],],
	'VA' => ['H' => ['short' => 'Del.','title' => 'Delegate', 'plural' => 'Delegates', 'chamber' => 'House'],],
	'WI' => ['H' => ['short' => 'Mem.','title' => 'Assemblymember', 'plural' => 'Members', 'chamber' => 'Assembly'],],
	'WV' => ['H' => ['short' => 'Del.','title' => 'Delegate', 'plural' => 'Delegates', 'chamber' => 'House'],],
	'NE' => ['H' => [], 'S' => ['short' => 'Sen.','title' => 'Senator', 'plural' => 'Senators', 'chamber' => 'Legislature']] // Unicameral legislature
];
//Hydrate array to show chambers for all governments
foreach($governments as $gov => $gov_name){
	$ch = $chambers['default'];
	if(isset($chambers[$gov])){
		$ch = array_merge($ch, $chambers[$gov]);
		foreach($chambers[$gov] as $ch_code => $ch_data){
			if(empty($ch_data)){
				unset($ch[$ch_code]);
			}
		}
	}
	$chambers[$gov] = $ch;
}
unset($chambers['default']); //This breaks things
define('FI_CHAMBERS', $chambers);


/**
* Congressional district count by state (US only)
* Number of U.S. House districts per state.
* 
* NOTE: U.S. Senators are elected AT-LARGE statewide (not district-based).
* Each state has 2 U.S. Senators elected statewide. Only U.S. House Representatives use districts.
* Therefore, congressional districts only apply to chamber 'H' (Representative).
* Chamber 'S' (Senator) at the US level does not use districts.
*/
$congressional_districts = [
	'AL' => 7, 'AK' => 1, 'AZ' => 9, 'AR' => 4, 'CA' => 52, 'CO' => 8, 'CT' => 5, 'DE' => 1, 'FL' => 28, 'GA' => 14,
	'HI' => 2, 'ID' => 2, 'IL' => 17, 'IN' => 9, 'IA' => 4, 'KS' => 4, 'KY' => 6, 'LA' => 6, 'ME' => 2, 'MD' => 8,
	'MA' => 9, 'MI' => 13, 'MN' => 8, 'MS' => 4, 'MO' => 8, 'MT' => 2, 'NE' => 3, 'NV' => 4, 'NH' => 2, 'NJ' => 13,
	'NM' => 3, 'NY' => 26, 'NC' => 14, 'ND' => 1, 'OH' => 15, 'OK' => 5, 'OR' => 6, 'PA' => 17, 'RI' => 2, 'SC' => 7,
	'SD' => 1, 'TN' => 9, 'TX' => 38, 'UT' => 4, 'VT' => 1, 'VA' => 11, 'WA' => 10, 'WV' => 2, 'WI' => 8, 'WY' => 1
];
define('FI_CONGRESSIONAL_DISTRICTS', $congressional_districts);

/**
* State legislative districts count by state and chamber
* Senate districts (S chamber) and House districts (H chamber)
* 
* IMPORTANT DISTRICT INFORMATION:
* 
* State Senate Districts:
* - ALL state senators are elected from districts (no at-large elections)
* - Districts are typically referred to as "State Senate District X" (e.g., "1st State Senate District")
* - Even small states (WY, VT, DE) use district-based representation
* - Historically, some states used multi-member Senate districts, but most now use single-member districts
* 
* State House Districts:
* - State Representatives/House members are elected from districts
* - Some small states historically had at-large lower chamber seats, but this is no longer common
* 
* Nebraska (Unicameral):
* - Nebraska has only one legislative chamber (Nebraska Legislature)
* - Functions as both Senate and House
* - Still uses 49 districts (not at-large)
* - Chamber 'S' is used, but 'H' is not applicable
* 
* US vs State:
* - US Senators (US): AT-LARGE (statewide, no districts)
* - US Representatives (US): DISTRICT-BASED
* - State Senators: DISTRICT-BASED
* - State Representatives: DISTRICT-BASED
*/
$state_districts = [
	'senate' => [
		'AL' => 35, 'AK' => 20, 'AZ' => 30, 'AR' => 35, 'CA' => 40, 'CO' => 35, 'CT' => 36, 'DE' => 21, 'FL' => 40,
		'GA' => 56, 'HI' => 25, 'ID' => 35, 'IL' => 59, 'IN' => 50, 'IA' => 50, 'KS' => 40, 'KY' => 38, 'LA' => 39,
		'ME' => 35, 'MD' => 47, 'MA' => 40, 'MI' => 38, 'MN' => 67, 'MS' => 52, 'MO' => 34, 'MT' => 50, 'NE' => 49,
		'NV' => 21, 'NH' => 24, 'NJ' => 40, 'NM' => 42, 'NY' => 63, 'NC' => 50, 'ND' => 47, 'OH' => 33, 'OK' => 48,
		'OR' => 30, 'PA' => 50, 'RI' => 38, 'SC' => 46, 'SD' => 35, 'TN' => 33, 'TX' => 31, 'UT' => 29, 'VT' => 30,
		'VA' => 40, 'WA' => 49, 'WV' => 17, 'WI' => 33, 'WY' => 31
	],
	'house' => [
		'AL' => 105, 'AK' => 40, 'AZ' => 60, 'AR' => 100, 'CA' => 80, 'CO' => 65, 'CT' => 151, 'DE' => 41, 'FL' => 120,
		'GA' => 180, 'HI' => 51, 'ID' => 70, 'IL' => 118, 'IN' => 100, 'IA' => 100, 'KS' => 125, 'KY' => 100, 'LA' => 105,
		'ME' => 151, 'MD' => 141, 'MA' => 160, 'MI' => 110, 'MN' => 134, 'MS' => 122, 'MO' => 163, 'MT' => 100, 'NE' => null,
		'NV' => 42, 'NH' => 400, 'NJ' => 80, 'NM' => 70, 'NY' => 150, 'NC' => 120, 'ND' => 94, 'OH' => 99, 'OK' => 101,
		'OR' => 60, 'PA' => 203, 'RI' => 75, 'SC' => 124, 'SD' => 70, 'TN' => 99, 'TX' => 150, 'UT' => 75, 'VT' => 150,
		'VA' => 100, 'WA' => 98, 'WV' => 100, 'WI' => 99, 'WY' => 62
	]
];
define('FI_STATE_DISTRICTS', $state_districts);


$parties = [
	'R' => [
		'name' => 'Republican',
		'bg_class' => 'bg-party-r',
		'bg_color' => '#E9141D',
		'text_class' => 'fi-party-r',
		'text_color' => '#fff',
	],
	'D' => [
		'name' => 'Democrat',
		'bg_class' => 'bg-party-d',
		'bg_color' => '#0015BC',
		'text_class' => 'fi-party-d',
		'text_color' => '#fff',
	],
	'DL' => [
		'name' => 'Democrat-Liberal',
		'bg_class' => 'bg-party-dl',
		'bg_color' => '#0015BC',
		'text_class' => 'fi-party-dl',
		'text_color' => '#fff',
	],
	'RC' => [
		'name' => 'Republican-Conservative',
		'bg_class' => 'bg-party-rc',
		'bg_color' => '#E9141D',
		'text_class' => 'fi-party-rc',
		'text_color' => '#fff',
	],
	'L' => [
		'name' => 'Libertarian',
		'bg_class' => 'bg-party-l',
		'bg_color' => '#FFDF00',
		'text_class' => 'fi-party-l',
		'text_color' => '#000',
	],
	'I' => [
		'name' => 'Independent',
		'bg_class' => 'bg-party-i',
		'bg_color' => '#666',
		'text_class' => 'fi-party-i',
		'text_color' => '#fff',
	],
	'P' => [
		'name' => 'Progressive',
		'bg_class' => 'bg-party-p',
		'bg_color' => '#0015BC',
		'text_class' => 'fi-party-p',
		'text_color' => '#fff',
	],
	'O' => [
		'name' => 'Other',
		'bg_class' => 'bg-party-o',
		'bg_color' => '#888',
		'text_class' => 'fi-party-o',
		'text_color' => '#fff',
	],
];
define('FI_PARTIES', $parties);


//Scoring function here to be shared with the API
//Standardize the score calc with pre-determined vote counts.
function fi_score_calc($votes_good, $votes_scored): int|null {
	if($votes_scored <= 0){
		return null;
	}
	$score_rounded = 0;
	if ($votes_scored > 0) {
		$score_percentage = ($votes_good / $votes_scored) * 100;
		$score_rounded = round($score_percentage, 0);
	}
	return $score_rounded;
}
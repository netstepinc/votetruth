<?php
if (!defined('ABSPATH')) exit;

/*
 * Legiscan People Import Partial
 * Displays list of people from Legiscan dataset with granular import controls
 * SAMPLE DATA
[
    "person" => [
        "people_id" => 9019,
        "person_hash" => "lcudj1xk",
        "party_id" => "1",
        "state_id" => 52,
        "party" => "D",
        "role_id" => 1,
        "role" => "Rep",
        "name" => "Andre Carson",
        "first_name" => "Andre",
        "middle_name" => "",
        "last_name" => "Carson",
        "suffix" => "",
        "nickname" => "",
        "district" => "HD-IN-7",
        "ftm_eid" => 17658782,
        "votesmart_id" => 84917,
        "opensecrets_id" => "N00029513",
        "knowwho_pid" => 263260,
        "ballotpedia" => "Andr%C3%A9_Carson",
        "bioguide_id" => "C001072",
        "committee_sponsor" => 0,
        "committee_id" => 0,
        "state_federal" => 0,
        "bio" => [
            "social" => [
                "capitol_phone" => "202-225-4011",
                "district_phone" => "",
                "email" => "",
                "webmail" => "https://carson.house.gov/",
                "biography" => "https://clerk.house.gov/members/C001072",
                "image" => "https://clerk.house.gov/content/assets/img/members/C001072",
                "ballotpedia" => "https://ballotpedia.org/Andr%25C3%25A9_Carson",
                "votesmart" => "https://justfacts.votesmart.org/candidate/biography/84917"
            ],
            "capitol_address" => [
                "address1" => "2135 Rayburn House Office Building",
                "address2" => "",
                "city" => "Washington",
                "state" => "DC",
                "zip" => "20515"
            ],
            "links" => [
                "official" => [
                    "bluesky" => "",
                    "facebook" => "https://www.facebook.com/CongressmanAndreCarson",
                    "instagram" => "https://www.instagram.com/repandrecarson/",
                    "linkedin" => "",
                    "tiktok" => "",
                    "twitter" => "https://www.twitter.com/RepAndreCarson",
                    "website" => "https://clerk.house.gov/members/C001072",
                    "youtube" => "https://www.youtube.com/user/RepAndreCarson"
                ],
                "personal" => [
                    "bluesky" => "",
                    "facebook" => "https://www.facebook.com/andre.carson.7",
                    "instagram" => "https://www.instagram.com/andrechronicles/",
                    "linkedin" => "https://www.linkedin.com/in/rep-andre-carson-16a100161/",
                    "tiktok" => "",
                    "twitter" => "",
                    "website" => "",
                    "youtube" => ""
                ]
            ]
        ]
    ]
]
 */

global $wpdb;

// Get session ID from dataset
$session_id_legiscan = $dataset_data['session_id'] ?? null;
$session_id = null;
if ($session_id_legiscan) {
	$session = fi_session_get_by_legiscan_id((int) $session_id_legiscan, $current_gov);
	$session_id = $session ? $session['id'] : null;
}

// Build people lookup array as we process files (check by legiscan_id, then external IDs)
$people = [];
$people_xref = [];//legiscan_id => fi_legislator_id compile for cache and use to match people to legislators.

// Get people already in this session
$session_people = [];
if ($session_id) {
	$session_people_records = $wpdb->get_results($wpdb->prepare(
		"SELECT legislator_id FROM {$wpdb->prefix}fi_legislator_sessions WHERE session_id = %d",
		$session_id
	), ARRAY_A);
	$session_people = array_column($session_people_records, 'legislator_id');
}

// District taxonomy reference maps for evaluation (name => id, id => name)
$district_name_to_id = [];
$district_id_to_name = [];
if ($session_id) {
	$district_rows = $wpdb->get_results($wpdb->prepare(
		"SELECT id, name FROM {$wpdb->prefix}fi_taxonomy WHERE taxonomy = 'district' AND gov = %s",
		$current_gov
	), ARRAY_A);
	foreach (($district_rows ?: []) as $d) {
		$district_name_to_id[$d['name']] = (int) $d['id'];
		$district_id_to_name[(int) $d['id']] = $d['name'];
	}
}

// Session district map: legislator_id => district taxonomy ID (VARCHAR stored in session row)
$legislator_sessions_map = [];
if ($session_id) {
	$sd_rows = $wpdb->get_results($wpdb->prepare(
		"SELECT id,legislator_id,gov,chamber,party, district FROM {$wpdb->prefix}fi_legislator_sessions WHERE session_id = %d",
		$session_id
	), ARRAY_A);
	foreach (($sd_rows ?: []) as $sd) {
		$legislator_sessions_map[(int) $sd['legislator_id']] = [
			'id' => $sd['id'],
			'district' => $sd['district'],
			'gov' => $sd['gov'],
			'chamber' => $sd['chamber'],
			'party' => $sd['party'],
		];
	}
}

// Pre-load all legislators with external IDs for efficient matching
// Build lookup arrays by legiscan_id, bioguide_id, votesmart_id, ballotpedia_id, display_name
$legislators_by_legiscan_id = [];
$legislators_by_bioguide_id = [];
$legislators_by_votesmart_id = [];
$legislators_by_ballotpedia_id = [];
$legislators_by_display_name = []; // key => [ids]
$legislators_by_name_state = [];   // key => state => [ids] (US-only disambiguation)
$all_legislators = [];

// Summary: load legislators in this gov scope (including those without external IDs) so name matching works for US/V1 imports.
$legislator_records = $wpdb->get_results($wpdb->prepare(
	"SELECT DISTINCT l.id, l.display_name, l.legiscan_id, l.bioguide_id, l.votesmart_id, l.ballotpedia_id, l.meta
	FROM {$wpdb->prefix}fi_legislators l
	INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls ON ls.legislator_id = l.id
	WHERE ls.gov = %s",
	$current_gov
), OBJECT);

foreach ($legislator_records as $leg) {
	$all_legislators[(int) $leg->id] = $leg;
	
	// Build lookup by legiscan_id
	if (!empty($leg->legiscan_id)) {
		$legislators_by_legiscan_id[(int) $leg->legiscan_id] = (int) $leg->id;
	}
	
	// Build lookup by bioguide_id (column, or legacy_post.post_name from meta for US/V1 imports)
	$bio = (string) ($leg->bioguide_id ?? '');
	if ($bio === '' && !empty($leg->meta)) {
		$meta = [];
		if (is_string($leg->meta)) {
			$meta = json_decode($leg->meta, true) ?: [];
		} elseif (is_array($leg->meta)) {
			$meta = $leg->meta;
		}
		$legacy_post_name = strtoupper(trim((string) (($meta['legacy_post']['post_name'] ?? '') ?: '')));
		if ($legacy_post_name !== '') {
			$bio = $legacy_post_name;
		}
	}
	if ($bio !== '') {
		$legislators_by_bioguide_id[strtoupper($bio)] = (int) $leg->id;
	}
	
	// Build lookup by votesmart_id
	if (!empty($leg->votesmart_id)) {
		$legislators_by_votesmart_id[(string) $leg->votesmart_id] = (int) $leg->id;
	}
	
	// Build lookup by ballotpedia_id
	if (!empty($leg->ballotpedia_id)) {
		$legislators_by_ballotpedia_id[$leg->ballotpedia_id] = (int) $leg->id;
	}

	// Build lookup by display_name
	$dn = fi_legiscan_people_normalize_name((string) ($leg->display_name ?? ''));
	if ($dn !== '') {
		if (!isset($legislators_by_display_name[$dn])) {
			$legislators_by_display_name[$dn] = [];
		}
		$legislators_by_display_name[$dn][] = (int) $leg->id;
	}
}

// US-only: preload name+state disambiguation map (helps for common names).
if ((string) $current_gov === 'US') {
	$rows = $wpdb->get_results(
		"SELECT l.id, l.display_name, ls.state
		FROM {$wpdb->prefix}fi_legislators l
		INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls ON ls.legislator_id = l.id
		WHERE ls.gov = 'US' AND ls.state IS NOT NULL AND ls.state != ''",
		OBJECT
	);
	foreach (($rows ?: []) as $r) {
		$k = fi_legiscan_people_normalize_name((string) ($r->display_name ?? ''));
		$st = strtoupper(trim((string) ($r->state ?? '')));
		$lid = (int) ($r->id ?? 0);
		if ($k !== '' && $st !== '' && $lid > 0) {
			if (!isset($legislators_by_name_state[$k])) $legislators_by_name_state[$k] = [];
			if (!isset($legislators_by_name_state[$k][$st])) $legislators_by_name_state[$k][$st] = [];
			$legislators_by_name_state[$k][$st][] = $lid;
		}
	}
}

// Person import is now handled in admin-legiscan.php before page renders
// This prevents redirect issues and allows proper feedback messages

$p = 0;
$todo = 0;
$excluded_nonvoting = 0;
$unassigned_people_ids = [];
?>

<table class="wp-list-table widefat fixed striped legiscan people">
	<thead>
		<tr>
			<th scope="col" class="manage-column">Action</th>
			<th scope="col" class="manage-column column-title column-primary"><span>Name</span></th>
			<th scope="col" class="manage-column">PersonID</th>
			<th scope="col" class="manage-column" style="width:60px;">Role</th>
			<th scope="col" class="manage-column" style="width:60px;">Party</th>
			<th scope="col" class="manage-column column-tags">State</th>
			<th scope="col" class="manage-column column-tags">District</th>
		</tr>
	</thead>
	<tbody id="the-list">
<?php
//Compile list of legislators to update with targeted data
$update_legislator_district = [];



if (!empty($dataset_files['people'])):
	foreach ($dataset_files['people'] as $file):
		if (!file_exists($file)) continue;

		$file_data = file_get_contents($file);
		$file_data = json_decode($file_data, true);
		$file_data = $file_data['person'] ?? [];
		$personID = $file_data['people_id'] ?? null;

		if (empty($file_data['party']) || !$personID) continue;
		
		$p++;
		
		// Determine state code
		// For US (Congress), LegiScan person.state_id is typically "US" (numeric ID 52), so derive state from district (HD-IN-7, SD-WI, etc.).
		$state_code = $current_gov; // Default to current gov
		$district_raw = (string) ($file_data['district'] ?? '');
		if ($current_gov === 'US' && $district_raw !== '') {
			if (preg_match('/^(?:HD|SD)-([A-Z]{2})\b/', strtoupper($district_raw), $m)) {
				$state_code = $m[1];
			}
		} else {
			$state_id_legiscan = $file_data['state_id'] ?? null;
			if ($state_id_legiscan && is_numeric($state_id_legiscan) && (int) $state_id_legiscan > 0) {
				$state_code = fi_legiscan_abbreviation((int) $state_id_legiscan);
			} elseif ($state_id_legiscan && strlen((string) $state_id_legiscan) === 2) {
				$state_code = strtoupper((string) $state_id_legiscan);
			}
		}
		$file_data['state_id'] = $state_code;

		// Exclude non-voting entities from US people list (territories + DC).
		if ($current_gov === 'US') {
			$exclude = ['DC', 'PR', 'VI', 'GU', 'MP', 'AS'];
			if (in_array($state_code, $exclude, true)) {
				$excluded_nonvoting++;
				continue;
			}
		}
		
		// Check if legislator exists using in-memory lookups
		$legislator_id = null;
		$matched_legislator = null;
		
		// Strategy 1: Match by legiscan_id
		if (isset($legislators_by_legiscan_id[$personID])) {
			$legislator_id = $legislators_by_legiscan_id[$personID];
			$matched_legislator = $all_legislators[$legislator_id] ?? null;
		} else {
			// Strategy 2: Match by external IDs (priority: bioguide > votesmart > ballotpedia)
			if (!empty($file_data['bioguide_id']) && isset($legislators_by_bioguide_id[strtoupper((string) $file_data['bioguide_id'])])) {
				$legislator_id = $legislators_by_bioguide_id[strtoupper((string) $file_data['bioguide_id'])];
			} elseif (!empty($file_data['votesmart_id']) && isset($legislators_by_votesmart_id[(string) $file_data['votesmart_id']])) {
				$legislator_id = $legislators_by_votesmart_id[(string) $file_data['votesmart_id']];
			} elseif (!empty($file_data['ballotpedia'])) {
				$ballotpedia_id = urldecode($file_data['ballotpedia']);
				if (isset($legislators_by_ballotpedia_id[$ballotpedia_id])) {
					$legislator_id = $legislators_by_ballotpedia_id[$ballotpedia_id];
				}
			}

			// Strategy 3 (US/V1 friendly): Match by name (gov-scoped) when no external IDs are present in FI.
			if (!$legislator_id) {
				$name_keys = fi_legiscan_people_name_keys($file_data);
				$candidates = [];
				foreach ($name_keys as $k) {
					foreach (($legislators_by_display_name[$k] ?? []) as $cid) {
						$candidates[] = (int) $cid;
					}
				}
				$candidates = array_values(array_unique(array_filter($candidates)));

				if (count($candidates) === 1) {
					$legislator_id = $candidates[0];
				} elseif (count($candidates) > 1 && (string) $current_gov === 'US' && $state_code !== '') {
					// Disambiguate by derived state (from district) when possible.
					foreach ($name_keys as $k) {
						$by_state = $legislators_by_name_state[$k][$state_code] ?? [];
						$by_state = array_values(array_unique(array_filter(array_map('intval', $by_state))));
						if (count($by_state) === 1) {
							$legislator_id = $by_state[0];
							break;
						}
					}
				}
			}
					
			if ($legislator_id) {
				$matched_legislator = $all_legislators[$legislator_id] ?? null;
			}
		}

		// When we have a match: fill missing external IDs from Legiscan; save only if there's new data.
		if ($legislator_id && $matched_legislator) {
			$update_data = [];
			if (empty($matched_legislator->legiscan_id) && !empty($personID)) {
				$update_data['legiscan_id'] = $personID;
				$legislators_by_legiscan_id[$personID] = $legislator_id;
			}
			if (empty($matched_legislator->bioguide_id) && !empty($file_data['bioguide_id'])) {
				$update_data['bioguide_id'] = $file_data['bioguide_id'];
				$legislators_by_bioguide_id[strtoupper((string) $file_data['bioguide_id'])] = $legislator_id;
			}
			if (empty($matched_legislator->votesmart_id) && !empty($file_data['votesmart_id'])) {
				$update_data['votesmart_id'] = (string) $file_data['votesmart_id'];
				$legislators_by_votesmart_id[(string) $file_data['votesmart_id']] = $legislator_id;
			}
			if (empty($matched_legislator->ballotpedia_id) && !empty($file_data['ballotpedia'])) {
				$bp = urldecode($file_data['ballotpedia']);
				$update_data['ballotpedia_id'] = $bp;
				$legislators_by_ballotpedia_id[$bp] = $legislator_id;
			}
			if (!empty($file_data['opensecrets_id'])) {
				$meta = [];
				if (!empty($matched_legislator->meta)) {
					$meta = is_string($matched_legislator->meta) ? (json_decode($matched_legislator->meta, true) ?: []) : $matched_legislator->meta;
				}
				if (empty($meta['opensecrets_id'])) {
					$meta['opensecrets_id'] = $file_data['opensecrets_id'];
					$update_data['meta'] = $meta;
				}
			}
			if (!empty($update_data)) {
				fi_legislator_save($update_data, $legislator_id);
				$matched_legislator = fi_legislator_get($legislator_id);
				if ($matched_legislator) {
					$all_legislators[$legislator_id] = $matched_legislator;
				}
			}
		}

		// Store in people array for later use
		if ($legislator_id) {
			$people[$personID] = [
				'id' => $legislator_id,
				'name' => $file_data['name'] ?? '',
				'status' => 'publish',
			];
			// Store in people_xref array for cache
			$people_xref[$personID] = $legislator_id;
		}
		
		$person_exists = isset($people[$personID]);
		$in_session = $person_exists && $session_id && in_array($people[$personID]['id'], $session_people);
		if ($person_exists && $session_id && !$in_session) {
			$unassigned_people_ids[] = $personID;
		}
		?>
		<tr id="<?= $personID;?>">
			<td class="column-start">
				<?php if ($person_exists): ?>
					<?php
					// Legislator exists: show View link; if not in this session, show "Add to Session" button (no auto-add).
					if ($in_session): ?>
						<a href="<?php echo esc_url(fi_admin_edit_legislator_url($people[$personID]['id'])); ?>" class="btn btn-sm btn-outline-success" target="_blank">View <?php echo esc_html($people[$personID]['id']); ?></a>
						<span class="text-muted small ms-1">Assigned</span>
					<?php elseif ($session_id): ?>
						<a href="<?php echo esc_url(fi_admin_edit_legislator_url($people[$personID]['id'])); ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="d-inline ms-1">
							<?php wp_nonce_field('fi_legiscan_add_person_to_session'); ?>
							<input type="hidden" name="action" value="fi_legiscan_add_person_to_session">
							<input type="hidden" name="gov" value="<?php echo esc_attr($current_gov); ?>">
							<input type="hidden" name="people_id" value="<?php echo esc_attr((string) $personID); ?>">
							<input type="hidden" name="fetch" value="<?php echo esc_attr($current_fetch); ?>">
							<input type="hidden" name="subtask" value="<?php echo esc_attr($current_subtask); ?>">
							<input type="hidden" name="dataset_state" value="<?php echo esc_attr((string) ($data['state'] ?? $current_gov)); ?>">
							<input type="hidden" name="data_dir_name" value="<?php echo esc_attr((string) $data_dir_name); ?>">
							<button type="submit" class="btn btn-primary btn-sm">Add to Session</button>
						</form>
					<?php else: ?>
						<a href="<?php echo esc_url(fi_admin_edit_legislator_url($people[$personID]['id'])); ?>" class="btn btn-sm btn-outline-success" target="_blank">View <?php echo esc_html($people[$personID]['id']); ?></a>
					<?php endif; ?>
					<?php else: ?>
						<?php $todo++; ?>
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="d-inline">
							<?php wp_nonce_field('fi_legiscan_add_person'); ?>
							<input type="hidden" name="action" value="fi_legiscan_add_person">
							<input type="hidden" name="gov" value="<?php echo esc_attr($current_gov); ?>">
							<input type="hidden" name="fetch" value="<?php echo esc_attr($current_fetch); ?>">
							<input type="hidden" name="subtask" value="<?php echo esc_attr($current_subtask); ?>">
							<input type="hidden" name="people_id" value="<?php echo esc_attr((string) $personID); ?>">
							<input type="hidden" name="dataset_state" value="<?php echo esc_attr((string) ($data['state'] ?? $current_gov)); ?>">
							<input type="hidden" name="data_dir_name" value="<?php echo esc_attr((string) $data_dir_name); ?>">
							<button type="submit" class="btn btn-primary btn-sm">Add Legislator</button>
						</form>
					<?php endif; ?>
					</td>
					<td><?php echo esc_html($file_data['name'] ?? ''); ?></td>
					<td><?php echo esc_html($personID); ?></td>
					<td><?php echo esc_html($file_data['role'] ?? ''); ?></td>
					<td><?php echo esc_html($file_data['party'] ?? ''); ?></td>
				<td><?php echo esc_html($state_code); ?></td>
				<td><?php
/*
$legislator_sessions_map[(int) $sd['legislator_id']] = [
	'id' => $sd['id'],
	'district' => $sd['district'],
	'gov' => $sd['gov'],
	'chamber' => $sd['chamber'],
	'party' => $sd['party'],
];
*/

				// District eval: US Senate has no district — skip comparison entirely.
				$is_senate = ($current_gov === 'US' && ($file_data['role'] ?? '') === 'Sen');
				if ($is_senate) {
					echo '<span class="text-muted">—</span>';
				} elseif ($in_session && $legislator_id) {
					$str = '<span class="';
					//Do we have a taxonomy:name type=district for this people district?
					$LS_DistId = null;
					$LS_DistName = $district_raw;
					if($LS_DistName !== ''){
						$LS_DistId = $district_name_to_id[$LS_DistName] ?? null;
						if($LS_DistId !== null){
							$str .= 'fw-bold ';
						}
					}

					//Does the legislator have a district in the session?
					$FI_SessId = $legislator_sessions_map[$legislator_id]['id'] ?? null;
					$FI_SessDistId = $legislator_sessions_map[$legislator_id]['district'] ?? null;
					$FI_SessDistName = $district_id_to_name[$FI_SessDistId] ?? null;

					//If Legiscan District but not Legislator District
					if($LS_DistId !== null && $FI_SessDistId === null){
						$str .= 'text-danger ';
						$update_legislator_district[$legislator_id] = ['id' => $FI_SessId, 'district' => $LS_DistId];
					}else
					//If Legiscan District does not match Legislator District
					if($LS_DistId !== null && $FI_SessDistId !== null && $LS_DistId != $FI_SessDistId){
						$str .= 'text-primary ';
						$update_legislator_district[$legislator_id] = ['id' => $FI_SessId, 'district' => $LS_DistId];
					}else
					//If Legiscan District matches Legislator District
					if($LS_DistId !== null && $FI_SessDistId !== null && $LS_DistId == $FI_SessDistId){
						$str .= 'text-success ';
					}
					//If Legislator District but not Legiscan District
					//TODO..
					$str .= '">' . esc_html($LS_DistName) . '</span>';
					echo $str;
				} else {
					// Not matched or not in session — plain display.
					echo esc_html($district_raw);
				}
				?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
	<tfoot>
		<tr>
			<td colspan="7" style="font-weight:bold; background:#FFFFCC">
				<?php echo $p; ?> People Found.
				<?php if ($excluded_nonvoting > 0): ?>
					<span class="ms-2 text-muted">(Excluded <?php echo (int) $excluded_nonvoting; ?> non-voting entities)</span>
				<?php endif; ?>
				<?php if ($todo > 0): ?>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="d-inline">
						<?php wp_nonce_field('fi_legiscan_add_person'); ?>
						<input type="hidden" name="action" value="fi_legiscan_add_person">
						<input type="hidden" name="gov" value="<?php echo esc_attr($current_gov); ?>">
						<input type="hidden" name="fetch" value="<?php echo esc_attr($current_fetch); ?>">
						<input type="hidden" name="subtask" value="<?php echo esc_attr($current_subtask); ?>">
						<input type="hidden" name="people_id" value="ALL">
						<input type="hidden" name="dataset_state" value="<?php echo esc_attr((string) ($data['state'] ?? $current_gov)); ?>">
						<input type="hidden" name="data_dir_name" value="<?php echo esc_attr((string) $data_dir_name); ?>">
						<button type="submit" class="btn btn-primary btn-sm" style="font-weight:bold;">Create <?php echo (int) $todo; ?> new legislators (and add to this session)</button>
					</form>
				<?php endif; ?>
			</td>
		</tr>
		<?php
		// Max to include in one form (matches FI_LEGISCAN_BULK_ADD_TO_SESSION_MAX; handler preloads to avoid timeout).
		$bulk_add_to_session_batch = defined('FI_LEGISCAN_BULK_ADD_TO_SESSION_MAX') ? (int) FI_LEGISCAN_BULK_ADD_TO_SESSION_MAX : 550;
		$num_unassigned = count($unassigned_people_ids);
		if ($num_unassigned > 0 && $session_id):
			$data_dir_name_val = (string) ($dataset_data['directory'] ?? $data_dir_name ?? '');
			$batch_ids = array_slice($unassigned_people_ids, 0, $bulk_add_to_session_batch);
			$batch_count = count($batch_ids);
			$more_count = $num_unassigned - $batch_count;
			?>
		<tr>
			<td colspan="7" class="bg-light">
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<?php wp_nonce_field('fi_legiscan_add_person_to_session'); ?>
					<input type="hidden" name="action" value="fi_legiscan_bulk_add_person_to_session">
					<input type="hidden" name="gov" value="<?php echo esc_attr($current_gov); ?>">
					<input type="hidden" name="fetch" value="<?php echo esc_attr($current_fetch); ?>">
					<input type="hidden" name="subtask" value="<?php echo esc_attr($current_subtask); ?>">
					<input type="hidden" name="data_dir_name" value="<?php echo esc_attr($data_dir_name_val); ?>">
					<?php foreach ($batch_ids as $uid): ?>
						<input type="hidden" name="people_ids[]" value="<?php echo esc_attr((string) $uid); ?>">
					<?php endforeach; ?>
					<button type="submit" class="btn btn-primary btn-sm">Assign <?php echo (int) $batch_count; ?> to this Session</button>
					<?php if ($more_count > 0): ?>
						<span class="text-muted small ms-2">(<?php echo (int) $more_count; ?> more — reload and run again for next batch.)</span>
					<?php endif; ?>
				</form>
			</td>
		</tr>
		<?php endif; ?>
	</tfoot>
</table>
<?php
global $wpdb;

// Update legislators with targeted data: This fi_legislator_sessions row needs to be updated with the new district ID
if(!empty($update_legislator_district)){
	echo '<h2>Update Legislators with Legiscan District</h2>';
	foreach($update_legislator_district as $legislator_id => $data){
		//$session_id
		$legislator_id = (int) $legislator_id;
		$legislator_sessions_id = (int) $data['id'];
		$district = (string) $data['district'];
		//UPDATE {$wpdb->prefix}fi_legislator_sessions SET district = %s WHERE id = %d
		$wpdb->update(
			$wpdb->prefix . 'fi_legislator_sessions',
			['district' => $district],
			['id' => $legislator_sessions_id]
		);
		echo 'Updated legislator_sessions_id: '.$legislator_sessions_id.' with district: '.$district.' for legislator_id: '.$legislator_id.'<br>';
	}
}
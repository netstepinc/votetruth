<?php if (!defined('ABSPATH')) {exit;}

$vote_id = absint($_GET['vote_id'] ?? 0);

if (!$vote_id) {
	wp_die('Invalid vote ID');
}

$vote = fi_vote_get($vote_id);

if (!$vote) {
	wp_die('Vote not found');
}

$gov_code = strtoupper($vote['gov'] ?? 'US');
$chamber_options = fi_chamber_options($gov_code);
$vote_chamber_code = strtoupper($vote['chamber'] ?? '');
$vote_chamber_label = $vote_chamber_code ? ($chamber_options[$vote_chamber_code]['chamber'] ?? $vote_chamber_code) : '';
/*
print_r($chamber_options);exit;
Array ( [S] => Array ( [short] => Sen. [name] => Senator [plural] => Senators [chamber] => Senate ) [R] => Array ( [short] => Rep. [name] => Representative [plural] => Representatives [chamber] => House ) )
*/



// ── Manual Rollcall: process form submissions ────────────────────────────────
$fi_manual_notice = null; // ['type' => 'success|error|warning', 'message' => '...', 'details' => [...]]

if (
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& !empty($_POST['fi_manual_action'])
	&& check_admin_referer('fi_rollcall_manual_' . $vote_id)
) {
	$fi_manual_action = sanitize_text_field($_POST['fi_manual_action']);
	$session_id = (int) ($vote['session_id'] ?? 0);
	$chamber    = strtoupper($vote['chamber'] ?? '');

	if ($fi_manual_action === 'create_empty_rollcall') {
		if (!$session_id || !$chamber) {
			$fi_manual_notice = ['type' => 'error', 'message' => 'Vote is missing session or chamber data.'];
		} else {
			$legislators = fi_legislators_get_by_session($session_id, ['chamber' => $chamber, 'limit' => LEGISLATORS_MAX_LIMIT]);
			if (empty($legislators)) {
				$fi_manual_notice = [
					'type'    => 'error',
					'message' => "No legislators found for session #{$session_id}, chamber '{$chamber}', gov '{$gov_code}'.",
				];
			} else {
				$existing_rcs     = fi_rollcalls_get_by_vote($vote_id);
				$existing_leg_ids = array_map(fn($rc) => (int) $rc->legislator_id, $existing_rcs);
				global $wpdb;
				$created = 0;
				foreach ($legislators as $leg) {
					$leg_id = (int) ($leg['id'] ?? 0);
					if (!$leg_id || in_array($leg_id, $existing_leg_ids, true)) {
						continue;
					}
					$result = $wpdb->insert(
						$wpdb->prefix . 'fi_voterc',
						['vote_id' => $vote_id, 'legislator_id' => $leg_id, 'cast' => 'X', 'is_override' => 1],
						['%d', '%d', '%s', '%d']
					);
					if ($result !== false) {
						$created++;
					}
				}
				$fi_manual_notice = ['type' => 'success', 'message' => "Created {$created} rollcall rows. Refresh to view the blank rollcall or paste CSV/Excel rollcall data into the box below."];
			}
		}
	}

	if ($fi_manual_action === 'import_rollcall') {
		$payload  = wp_unslash($_POST['fi_tsv_payload'] ?? '');
		$match_by = sanitize_text_field($_POST['fi_match_by'] ?? 'last_name');

		if (empty(trim($payload))) {
			$fi_manual_notice = ['type' => 'warning', 'message' => 'Paste TSV data before importing.'];
		} elseif (!$session_id || !$chamber) {
			$fi_manual_notice = ['type' => 'error', 'message' => 'Vote is missing session or chamber data.'];
		} else {
			// Build lastname → ID map from DB
			$legislators  = fi_legislators_get_by_session($session_id, ['chamber' => $chamber, 'limit' => LEGISLATORS_MAX_LIMIT]);
			$lastname_map = [];
			foreach ($legislators as $leg) {
				$leg_id    = (int) ($leg['id'] ?? 0);
				$last_name = strtolower(trim($leg['last_name'] ?? ''));
				if (!$leg_id || !$last_name) {
					continue;
				}
				if (isset($lastname_map[$last_name])) {
					if (!is_array($lastname_map[$last_name])) {
						$lastname_map[$last_name] = [$lastname_map[$last_name]];
					}
					$lastname_map[$last_name][] = $leg_id;
				} else {
					$lastname_map[$last_name] = $leg_id;
				}
			}

			$known_cast_values = [
				'y', 'n', 'p', 'x', 'a', 'nv', 'absent', 'abstain', 'paired',
				'1', '2', '0', 'yes', 'aye', 'yea', 'guilty',
				'no', 'nay', 'not guilty', 'present', 'not voting',
			];

			$lines   = preg_split('/\r\n|\r|\n/', trim($payload));
			$matched = 0;
			$flagged = [];
			$unrecognized = null;

			global $wpdb;

			foreach ($lines as $line) {
				$line = trim($line);
				if ($line === '') {
					continue;
				}
				$parts = explode("\t", $line);
				if (count($parts) < 2) {
					continue;
				}
				$member_raw = trim($parts[0]);
				$vote_raw   = trim(preg_replace('/[^a-zA-Z ]+/', '', trim($parts[1])));

				// Skip header row
				if (in_array(strtolower($member_raw), ['member', 'name', 'legislator'], true)) {
					continue;
				}

				// Halt on unrecognised cast value
				if (!in_array(strtolower($vote_raw), $known_cast_values, true)) {
					$unrecognized = esc_html($vote_raw) . ' (for "' . esc_html($member_raw) . '")';
					break;
				}

				$cast = fi_rollcall_cast_normalize($vote_raw);

				// Extract lookup key
				$leg_id  = null;
				$is_dupe = false;

				if ($match_by === 'name_of_district') {
					$of_pos = stripos($member_raw, ' of ');
					$key    = strtolower(trim($of_pos !== false ? substr($member_raw, 0, $of_pos) : $member_raw));
					$hits   = [];
					foreach ($lastname_map as $map_key => $map_val) {
						if (strpos($map_key, $key) === 0) {
							$hits[] = $map_val;
						}
					}
					if (count($hits) === 1) {
						$candidate = $hits[0];
						is_array($candidate) ? $is_dupe = true : $leg_id = (int) $candidate;
					} elseif (count($hits) > 1) {
						$is_dupe = true;
					}
				} else {
					$key       = strtolower($member_raw);
					$candidate = $lastname_map[$key] ?? null;
					if ($candidate !== null) {
						is_array($candidate) ? $is_dupe = true : $leg_id = (int) $candidate;
					}
				}

				if ($is_dupe) {
					$flagged[] = $member_raw . ': ' . $vote_raw . ' (ambiguous — set manually)';
					continue;
				}
				if ($leg_id === null) {
					$flagged[] = $member_raw . ': ' . $vote_raw . ' (no match found)';
					continue;
				}

				$wpdb->update(
					$wpdb->prefix . 'fi_voterc',
					['cast' => $cast, 'is_override' => 1],
					['vote_id' => $vote_id, 'legislator_id' => $leg_id],
					['%s', '%d'],
					['%d', '%d']
				);
				$matched++;
			}

			if ($unrecognized !== null) {
				$fi_manual_notice = [
					'type'    => 'error',
					'message' => "Unrecognized vote value: {$unrecognized}. Import halted — send this page link to Sam.",
				];
			} else {
				$msg = "Matched {$matched} votes.";
				$fi_manual_notice = [
					'type'    => empty($flagged) ? 'success' : 'warning',
					'message' => $msg,
					'details' => $flagged,
				];
			}
		}
	}
}

// Base rows on fi_voterc (rollcalls) so edits map to rollcall IDs.
$roll_calls = fi_rollcalls_get_by_vote($vote_id);

/*
echo '<textarea style="width:100%; height:300px;">'; print_r($roll_calls); echo '</textarea>';
[0] => stdClass Object
(
	[id] => 487908
	[vote_id] => 3471
	[legislator_id] => 2920
	[cast] => N
	[is_override] => 0
	[date_created] => 2026-01-26 16:22:57
	[first_name] => Ryan
	[last_name] => Armagost
	[display_name] => Ryan Armagost
	[vote_title] => Department of Education Funding
	[bill_key] => SB091
	[constitutional] => N
	[session_name] => 2025 Regular Session
)
*/

$legislators = fi_legislators_get_by_session((int) $vote['session_id'], [
	'limit' => LEGISLATORS_MAX_LIMIT,
]);
$legislator_lookup = [];
foreach ($legislators as $legislator) {
	$legislator_lookup[(int) ($legislator['id'] ?? 0)] = $legislator;
}

$cast_options = [
	''  => 'Unrecorded',
	'Y' => 'Yes',
	'N' => 'No',
	'P' => 'Present',
	'X' => 'Not Voted',
];

$legislator_rows = [];
$party_filters = [];
$chamber_filters = [];

foreach ($roll_calls as $roll_call) {
	$legislator_id = (int) ($roll_call->legislator_id ?? 0);
	$legislator = $legislator_lookup[$legislator_id] ?? null;
	$chamber_code = strtoupper($legislator['chamber'] ?? $vote_chamber_code ?? '');
	$party_code = strtoupper($legislator['party'] ?? '');

	if ($chamber_code && !isset($chamber_filters[$chamber_code])) {
		$chamber_label = $chamber_options[$chamber_code] ?? $chamber_code;
		// Handle arrays from chamber options (extract 'name' or 'short' key)
		$chamber_filters[$chamber_code] = is_array($chamber_label) 
			? ($chamber_label['name'] ?? $chamber_label['short'] ?? (string) $chamber_code)
			: (string) ($chamber_label ?? $chamber_code);
	}

	if ($party_code && !isset($party_filters[$party_code])) {
		$party_filters[$party_code] = function_exists('fi_party_name')
			? (fi_party_name($party_code) ?: $party_code)
			: $party_code;
	}

	$legislator_rows[] = (object) [
		'id' => $legislator_id,
		'rollcall_id' => (int) ($roll_call->id ?? 0),
		'display_name' => $roll_call->display_name
			?? trim(($roll_call->first_name ?? '') . ' ' . ($roll_call->last_name ?? ''))
			?? ($legislator['display_name'] ?? ''),
		'slug' => $legislator['slug'] ?? '',
		'district' => ($legislator['district_info']['name_short'] ?? $legislator['district_info']['name'] ?? $legislator['district'] ?? ''),
		'state' => $legislator['state'] ?? '',
		'chamber' => $chamber_code,
		'party' => $party_code,
		'cast' => $roll_call->cast ?? '',
		'is_override' => !empty($roll_call->is_override),
	];
}

ksort($party_filters);
ksort($chamber_filters);

$rollcall_summary = fi_rollcall_summary($vote_id);
$vote_meta = fi_admin_votes_decode_meta($vote);
$legiscan_meta = array_filter(
	$vote_meta,
	static fn($value, $key) => str_starts_with((string) $key, 'legiscan'),
	ARRAY_FILTER_USE_BOTH
);


$legislator_rows   = $legislator_rows ?? [];
$party_filters     = $party_filters ?? [];
$chamber_filters  = $chamber_filters ?? [];
$cast_options      = $cast_options ?? [];
$rollcall_summary  = $rollcall_summary ?? [];
$gov_code          = $gov_code ?? '';
$session_name      = $vote['session_name'] ?? '';
$legiscan_meta     = $legiscan_meta ?? [];
$vote_id           = (int) ($vote['id'] ?? 0);
$chamber_options  = $chamber_options ?? [];
$const = $vote['constitutional'] ?? 'U';
$constitutional = $const === 'Y' ? 'Constitutional (Y)' : ($const === 'N' ? 'Unconstitutional (N)' : 'Unknown (U)');
?>

<div class="wrap fi-rollcall-admin" data-vote-id="<?php echo esc_attr($vote_id); ?>" data-gov="<?php echo esc_attr($gov_code); ?>">
	<h1 class="wp-heading-inline">Roll Call · <?php echo esc_html($vote['title'] ?? ''); ?></h1>
	<div class="d-flex flex-wrap gap-2 mb-3">
		<a href="<?php echo esc_url(fi_admin_edit_vote_url($vote_id)); ?>" class="btn btn-secondary">Back to Vote</a>
		<a href="<?php echo esc_url(fi_admin_url('fi-votes')); ?>" class="btn btn-outline-secondary">All Votes</a>
	</div>
	<div id="fi-rollcall-alert"></div>

	<div class="row g-4">
		<div class="col-12 col-lg-9">
			<div class="card shadow-sm mb-3">
				<div class="card-body">
					<div class="row g-3 align-items-end">
						<div class="col-md-4">
							<label for="fi-rollcall-filter-search" class="form-label">Search Legislators</label>
							<input type="search" id="fi-rollcall-filter-search" class="form-control" placeholder="Name, district, slug">
						</div>
						<div class="col-md-3">
							<label for="fi-rollcall-filter-party" class="form-label">Party</label>
							<select id="fi-rollcall-filter-party" class="form-select">
								<option value="">All Parties</option>
								<?php foreach ($party_filters as $code => $label): ?>
									<option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($label); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-2">
							<label for="fi-rollcall-filter-cast" class="form-label">Vote</label>
							<select id="fi-rollcall-filter-cast" class="form-select">
								<option value="">All Votes</option>
								<?php foreach ($cast_options as $cast_code => $cast_label): ?>
									<?php if ($cast_code === '') { continue; } ?>
									<option value="<?php echo esc_attr($cast_code); ?>"><?php echo esc_html($cast_label); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>
			</div>

			<div class="card shadow-sm">
				<div class="card-header bg-white bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
					<h2 class="h5 mb-0"><?php echo esc_html($vote_chamber_label ?: ''); ?> Legislators</h2>
					<div class="d-flex gap-2">
						<button type="button" class="btn btn-secondary btn-sm" id="fi-rollcall-refresh">Reload</button>
						<button type="button" class="btn btn-primary btn-sm" id="fi-rollcall-save">Save Changes</button>
					</div>
				</div>
				<div class="table-responsive">
					<table class="table table-hover align-middle mb-0" id="fi-rollcall-table">
						<thead class="table-light">
							<tr>
								<th>Legislator</th>
								<th>Party</th>
								<th>Chamber</th>
								<th>District</th>
								<th>Vote</th>
								<th class="text-center">Override</th>
							</tr>
						</thead>
						<tbody id="fi-rollcall-body">
							<?php foreach ($legislator_rows as $row): ?>
								<tr
									data-rollcall-row
									data-legislator-id="<?php echo esc_attr($row->id); ?>"
									data-rollcall-id="<?php echo esc_attr($row->rollcall_id); ?>"
									data-name="<?php echo esc_attr(strtolower($row->display_name)); ?>"
									data-slug="<?php echo esc_attr(strtolower($row->slug)); ?>"
									data-chamber="<?php echo esc_attr($row->chamber); ?>"
									data-party="<?php echo esc_attr($row->party); ?>"
									data-district="<?php echo esc_attr(strtolower($row->district ?? '')); ?>"
								>
									<td>
										<strong><?php echo esc_html($row->display_name); ?></strong><br>
									</td>
									<td>
										<span class="badge bg-secondary">
											<?php echo esc_html($party_filters[$row->party] ?? $row->party ?: '—'); ?>
										</span>
									</td>
									<td><?php 
										$chamber = $row->chamber ?? '';
										$gov = $gov_code ?? 'US';
										if ($chamber) {
											echo esc_html(fi_chamber_label($gov, $chamber));
										} else {
											echo '—';
										}
									?></td>
									<td><?php echo esc_html($row->district ?: '—'); ?></td>
									<td style="min-width: 200px;">
										<div class="fi-rollcall-cast-group" data-legislator-id="<?php echo esc_attr($row->id); ?>">
											<?php foreach ($cast_options as $cast_code => $cast_label): ?>
												<div class="form-check form-check-inline">
													<input
														type="radio"
														class="form-check-input fi-rollcall-cast"
														name="cast_<?php echo esc_attr($row->id); ?>"
														id="cast_<?php echo esc_attr($row->id); ?>_<?php echo esc_attr($cast_code); ?>"
														value="<?php echo esc_attr($cast_code); ?>"
														data-legislator-id="<?php echo esc_attr($row->id); ?>"
														<?php checked($row->cast, $cast_code); ?>
													>
													<label class="form-check-label" for="cast_<?php echo esc_attr($row->id); ?>_<?php echo esc_attr($cast_code); ?>">
														<?php echo esc_html($cast_label); ?>
													</label>
												</div>
											<?php endforeach; ?>
										</div>
									</td>
									<td class="text-center">
										<div class="form-check justify-content-center d-inline-flex">
											<input
												type="checkbox"
												class="form-check-input fi-rollcall-override"
												data-legislator-id="<?php echo esc_attr($row->id); ?>"
												<?php checked($row->is_override); ?>
											>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php if (empty($legislator_rows)): ?>
								<tr>
									<td colspan="6" class="text-center text-muted py-4">No roll-call entries found for this vote.</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<div class="col-12 col-lg-3">
			<div class="card shadow-sm mb-4">
				<div class="card-header bg-white border-0">
					<h2 class="h5 mb-0">Roll Call Summary</h2>
				</div>
				<div class="card-body">
					<div class="row text-center">
						<div class="col-4 mb-3">
							<p class="text-muted mb-1">Total</p>
							<div class="fs-4" id="fi-summary-total"><?php echo esc_html($rollcall_summary['total_votes'] ?? 0); ?></div>
						</div>
						<div class="col-4 mb-3">
							<p class="text-muted mb-1">Yes</p>
							<div class="fs-4 text-success" id="fi-summary-yes"><?php echo esc_html($rollcall_summary['yes'] ?? 0); ?></div>
						</div>
						<div class="col-4 mb-3">
							<p class="text-muted mb-1">No</p>
							<div class="fs-4 text-danger" id="fi-summary-no"><?php echo esc_html($rollcall_summary['no'] ?? 0); ?></div>
						</div>
						<div class="col-4">
							<p class="text-muted mb-1">Present</p>
							<div class="fs-6" id="fi-summary-present"><?php echo esc_html($rollcall_summary['present'] ?? 0); ?></div>
						</div>
						<div class="col-4">
							<p class="text-muted mb-1">Not Voted</p>
							<div class="fs-6" id="fi-summary-notvoted"><?php echo esc_html($rollcall_summary['not_voting'] ?? 0); ?></div>
						</div>
					</div>
				</div>
			</div>

			<div class="card shadow-sm mb-4">
				<div class="card-header bg-white border-0">
					<h2 class="h5 mb-0">Vote Details</h2>
				</div>
				<div class="card-body">
					<ul class="list-unstyled mb-3">
						<li><strong>Session:</strong> <?php echo esc_html($session_name ?: '—'); ?></li>
						<li><strong>Chamber:</strong> <?php 
							$chamber = strtoupper($vote['chamber'] ?? '');
							$gov = $vote['gov'] ?? $gov_code ?? 'US';
							if ($chamber) {
								echo esc_html(fi_chamber_label($gov, $chamber));
							} else {
								echo '—';
							}
						?></li>
						<li><strong>Constitutional:</strong> <?php echo esc_html($constitutional); ?></li>
						<li><strong>Roll-call #:</strong> <?php echo esc_html($vote['rollcall_number'] ?? '—'); ?></li>
						<li><strong>Date:</strong> <?php echo esc_html($vote['date_voted'] ?? '—'); ?></li>
					</ul>
					<?php if (!empty($legiscan_meta)): ?>
						<p class="fw-semibold mb-2">LegiScan Metadata</p>
						<ul class="list-unstyled small">
							<?php foreach ($legiscan_meta as $key => $value): ?>
								<li><strong><?php echo esc_html($key); ?>:</strong> <?php echo esc_html(is_scalar($value) ? $value : wp_json_encode($value)); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>

			<?php $has_rollcalls = !empty($roll_calls); ?>
			<div class="card shadow-sm mb-4">
				<div class="card-header bg-white border-0">
					<h2 class="h5 mb-0">Manual Rollcall</h2>
				</div>
				<div class="card-body">
					<?php if ($fi_manual_notice): ?>
						<div class="alert alert-<?php echo esc_attr($fi_manual_notice['type'] === 'success' ? 'success' : ($fi_manual_notice['type'] === 'warning' ? 'warning' : 'danger')); ?> py-2 small">
							<?php echo esc_html($fi_manual_notice['message']); ?>
							<?php if (!empty($fi_manual_notice['details'])): ?>
								<ul class="mb-0 mt-1 list-group list-group-flush">
									<?php foreach ($fi_manual_notice['details'] as $d): ?>
										<li class="list-group-item"><?php echo esc_html($d); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if (!$has_rollcalls): ?>
						<p class="text-muted small">No rollcall rows exist yet. Click below to create a row for every <?php echo esc_html($vote_chamber_label ?: 'chamber'); ?> legislator in this session, defaulting each vote to <strong>Not Voted (X)</strong>.</p>
						<form method="post">
							<?php wp_nonce_field('fi_rollcall_manual_' . $vote_id); ?>
							<input type="hidden" name="fi_manual_action" value="create_empty_rollcall">
							<button type="submit" class="btn btn-warning w-100">Create Empty Rollcall</button>
						</form>
					<?php else: ?>
						<p class="text-muted small">Paste tab-separated vote data (e.g. copied from Excel). Header row is skipped automatically.</p>
						<form method="post">
							<?php wp_nonce_field('fi_rollcall_manual_' . $vote_id); ?>
							<input type="hidden" name="fi_manual_action" value="import_rollcall">
							<div class="mb-2">
								<label for="fi-manual-match-by" class="form-label">Match by</label>
								<select id="fi-manual-match-by" name="fi_match_by" class="form-select form-select-sm">
									<option value="last_name">Last Name</option>
									<option value="name_of_district">Name of District (e.g. "Bailey of Hyde Park")</option>
								</select>
							</div>
							<textarea name="fi_tsv_payload" class="form-control mb-3" rows="8" placeholder="Member&#9;Vote&#10;Bailey of Hyde Park&#9;Yea&#10;Jones of Montpelier&#9;Nay"></textarea>
							<button type="submit" class="btn btn-primary w-100">Import Rollcall</button>
						</form>
					<?php endif; ?>
				</div>
			</div>

			<div class="card shadow-sm mb-4">
				<div class="card-header bg-white border-0">
					<h2 class="h5 mb-0">Bulk JSON Import</h2>
				</div>
				<div class="card-body">
					<p class="text-muted small">
						Paste the LegiScan (or other) roll-call JSON. Keys should be external legislator IDs (bioguide, LegiScan, VoteSmart, etc.)
						and values must be one of Y, N, P, or X. (Abstain → P, Absent → X, NV → X)
					</p>
					<textarea id="fi-legiscan-json" class="form-control mb-3" rows="6" placeholder='{"A000360": "Y", "A000055": "N"}'></textarea>
					<div class="d-flex gap-2">
						<button type="button" class="btn btn-secondary w-50" id="fi-legiscan-clear">Clear</button>
						<button type="button" class="btn btn-primary w-50" id="fi-legiscan-import">Import JSON</button>
					</div>
				</div>
			</div>

		</div>
	</div>
</div>

<style>
tr.fi-rc-unset td { background-color: #fff3cd !important; }
</style>

<script>
(function() {
	const root = document.querySelector('.fi-rollcall-admin');
	if (!root || typeof ajaxurl === 'undefined') {
		return;
	}

	const voteId = parseInt(root.dataset.voteId || '0', 10);
	const govCode = root.dataset.gov || '';
	const noticeEl = document.getElementById('fi-rollcall-alert');
	const tbody = document.getElementById('fi-rollcall-body');
	const searchInput = document.getElementById('fi-rollcall-filter-search');
	const partyFilter = document.getElementById('fi-rollcall-filter-party');
	const castFilter = document.getElementById('fi-rollcall-filter-cast');
	const saveBtn = document.getElementById('fi-rollcall-save');
	const refreshBtn = document.getElementById('fi-rollcall-refresh');
	const importBtn = document.getElementById('fi-legiscan-import');
	const clearImportBtn = document.getElementById('fi-legiscan-clear');
	const importTextarea = document.getElementById('fi-legiscan-json');


	const summaryMap = {
		total_votes: 'fi-summary-total',
		yes: 'fi-summary-yes',
		no: 'fi-summary-no',
		abstain: 'fi-summary-abstain',
		present: 'fi-summary-present',
		not_voting: 'fi-summary-notvoting'
	};

	const sanitize = (value) => (value || '').toString().toLowerCase();

	function rows() {
		return Array.from(tbody.querySelectorAll('[data-rollcall-row]'));
	}

	function setNotice(type, message) {
		if (!noticeEl) {
			return;
		}
		if (!message) {
			noticeEl.innerHTML = '';
			return;
		}
		noticeEl.innerHTML = `
			<div class="notice notice-${type} is-dismissible">
				<p>${message}</p>
			</div>
		`;
		const dismiss = noticeEl.querySelector('.notice-dismiss');
		if (dismiss) {
			dismiss.addEventListener('click', () => noticeEl.innerHTML = '');
		}
	}

	function applySummary(summary) {
		if (!summary) {
			return;
		}
		Object.entries(summaryMap).forEach(([key, elementId]) => {
			const el = document.getElementById(elementId);
			if (el && Object.prototype.hasOwnProperty.call(summary, key)) {
				el.textContent = summary[key];
			}
		});
	}

	function getCastValue(row) {
		const checked = row.querySelector('.fi-rollcall-cast:checked');
		return checked ? checked.value : '';
	}

	function setCastValue(row, value) {
		const radios = row.querySelectorAll('.fi-rollcall-cast');
		radios.forEach((radio) => {
			radio.checked = (radio.value === value);
		});
	}

	function updateOverrideHighlight(row) {
		const overrideToggle = row.querySelector('.fi-rollcall-override');
		if (overrideToggle && overrideToggle.checked) {
			row.classList.add('bg-warning');
		} else {
			row.classList.remove('bg-warning');
		}
	}

	function updateLocalCounts() {
		const counts = { Y: 0, N: 0, A: 0, P: 0, X: 0 };
		rows().forEach((row) => {
			const value = getCastValue(row);
			if (value && Object.prototype.hasOwnProperty.call(counts, value)) {
				counts[value]++;
			}
		});
		applySummary({
			total_votes: Object.values(counts).reduce((total, value) => total + value, 0),
			yes: counts.Y,
			no: counts.N,
			abstain: counts.A,
			present: counts.P,
			not_voting: counts.X
		});
	}

	function applyFilters() {
		const searchValue = sanitize(searchInput.value);
		const selectedParty = sanitize(partyFilter.value);
		const selectedCast = sanitize(castFilter.value);

		rows().forEach((row) => {
			const name = row.dataset.name || '';
			const slug = row.dataset.slug || '';
			const district = row.dataset.district || '';
			const party = sanitize(row.dataset.party || '');
			const cast = sanitize(getCastValue(row));

			let visible = true;

			if (searchValue && !`${name} ${slug} ${district}`.includes(searchValue)) {
				visible = false;
			}
			if (selectedParty && party !== selectedParty) {
				visible = false;
			}
			if (selectedCast && cast !== selectedCast) {
				visible = false;
			}

			row.classList.toggle('d-none', !visible);
		});
	}

	function collectPayload() {
		const payload = [];
		rows().forEach((row) => {
			const legislatorId = parseInt(row.dataset.legislatorId || '0', 10);
			const rollcallId = parseInt(row.dataset.rollcallId || '0', 10);
			const castGroup = row.querySelector('.fi-rollcall-cast-group');
			const overrideToggle = row.querySelector('.fi-rollcall-override');

			if (!rollcallId || !legislatorId || !castGroup) {
				return;
			}

			const cast = getCastValue(row);
			if (!cast) {
				return;
			}

			payload.push({
				rollcall_id: rollcallId,
				legislator_id: legislatorId,
				cast,
				is_override: overrideToggle && overrideToggle.checked ? 1 : 0
			});
		});
		return payload;
	}

	function postAjax(data) {
		const body = new URLSearchParams(data);
		return fetch(ajaxurl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body
		}).then((response) => response.json());
	}

	function refreshFromServer(showNotice = false) {
		if (!voteId) {
			return;
		}
		if (showNotice) {
			setNotice('info', 'Refreshing roll-call data…');
		}
		postAjax({
			action: 'fi_admin_action',
			sub_action: 'get_roll_call_data',
			vote_id: voteId,
			nonce: fiAdmin?.nonce || ''
		}).then((response) => {
			if (!response?.success) {
				setNotice('error', response?.data || 'Unable to load roll-call data.');
				return;
			}
			applyRemoteRollcalls(response.data.rollcalls || []);
			applySummary(response.data.summary || null);
			setNotice('success', 'Roll-call data reloaded.');
		}).catch(() => {
			setNotice('error', 'Network error while reloading roll-call data.');
		});
	}

	function applyRemoteRollcalls(rollcalls) {
		const lookupById = {};
		const lookupByLegislator = {};
		rollcalls.forEach((row) => {
			const rollcallId = parseInt(row.id || 0, 10);
			const legislatorId = parseInt(row.legislator_id || 0, 10);
			if (rollcallId) {
				lookupById[rollcallId] = row;
			}
			if (legislatorId) {
				lookupByLegislator[legislatorId] = row;
			}
		});
		rows().forEach((row) => {
			const legislatorId = parseInt(row.dataset.legislatorId || '0', 10);
			const rollcallId = parseInt(row.dataset.rollcallId || '0', 10);
			const remote = lookupById[rollcallId] || lookupByLegislator[legislatorId];
			const overrideToggle = row.querySelector('.fi-rollcall-override');

			setCastValue(row, remote?.cast || '');
			if (overrideToggle) {
				overrideToggle.checked = !!(remote?.is_override);
			}
			updateOverrideHighlight(row);
			updateUnsetHighlight(row);
		});
		updateLocalCounts();
	}

	function saveRollcall() {
		const payload = collectPayload();
		setNotice('info', 'Saving roll-call votes…');

		postAjax({
			action: 'fi_admin_action',
			sub_action: 'save_rollcall',
			vote_id: voteId,
			nonce: fiAdmin?.nonce || '',
			roll_call_data: JSON.stringify(payload)
		}).then((response) => {
			if (!response?.success) {
				setNotice('error', response?.data || 'Unable to save roll-call votes.');
				return;
			}
			setNotice('success', `Saved ${response.data.saved ?? 0} roll-call entries.`);
			applySummary(response.data.summary || null);
		}).catch(() => {
			setNotice('error', 'Network error while saving roll-call votes.');
		});
	}

	function importRollcall() {
		const payload = importTextarea.value.trim();
		if (!payload) {
			setNotice('warning', 'Paste LegiScan JSON before importing.');
			return;
		}
		setNotice('info', 'Importing JSON data…');

		postAjax({
			action: 'fi_admin_action',
			sub_action: 'import_legiscan_rollcall',
			vote_id: voteId,
			gov: govCode,
			payload,
			nonce: fiAdmin?.nonce || ''
		}).then((response) => {
			if (!response?.success) {
				setNotice('error', response?.data || 'Unable to import roll-call data.');
				return;
			}
			applyRemoteRollcalls(response.data.rollcalls || []);
			applySummary(response.data.summary || null);
			setNotice('success', `Imported ${response.data.imported ?? 0} roll-call votes.`);
			importTextarea.value = '';
		}).catch(() => {
			setNotice('error', 'Network error while importing data.');
		});
	}

	function updateUnsetHighlight(row) {
		const cast = getCastValue(row);
		row.classList.toggle('fi-rc-unset', !cast || cast === 'X');
	}

	function bindEvents() {
		[searchInput, partyFilter, castFilter].forEach((input) => {
			if (input) {
				input.addEventListener('input', applyFilters);
				input.addEventListener('change', applyFilters);
			}
		});

		rows().forEach((row) => {
			const castRadios = row.querySelectorAll('.fi-rollcall-cast');
			const toggle = row.querySelector('.fi-rollcall-override');
			if (castRadios.length > 0) {
				castRadios.forEach((radio) => {
					radio.addEventListener('change', () => {
						updateLocalCounts();
						updateUnsetHighlight(row);
						applyFilters();
					});
				});
			}
			if (toggle) {
				// Update highlight on initial load
				updateOverrideHighlight(row);
				// Update highlight and counts when checkbox changes
				toggle.addEventListener('change', () => {
					updateOverrideHighlight(row);
					updateLocalCounts();
				});
			}
		});

		if (saveBtn) {
			saveBtn.addEventListener('click', saveRollcall);
		}

		if (refreshBtn) {
			refreshBtn.addEventListener('click', () => refreshFromServer(true));
		}

		if (importBtn) {
			importBtn.addEventListener('click', importRollcall);
		}

		if (clearImportBtn) {
			clearImportBtn.addEventListener('click', () => {
				importTextarea.value = '';
			});
		}

	}

	bindEvents();
	updateLocalCounts();
	rows().forEach(updateUnsetHighlight);
})();
</script>
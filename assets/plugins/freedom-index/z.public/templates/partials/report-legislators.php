<?php if (!defined('ABSPATH')) exit;

/**
 * Report Legislators Display
 * Shows legislators in card format with report-specific scores and vote history
 * 
 * @param array $args {
 *     @type int $session_id Session ID
 *     @type string $chamber Chamber code (S or H)
 *     @type string $gov Government code
 *     @type array $votes Array of vote objects in the report
 *     @type string|null $filter_state Optional state filter (for Congress reports)
 *     @type string|null $filter_party Optional party display name (for labels)
 *     @type string|null $filter_party_slug Optional party slug for URL/option value (r, d, etc.)
 *     @type string|null $filter_name Optional name search (from pretty URL)
 *     @type string $report_base_path Base path for pretty filter URLs (e.g. /us/report/29/chamber/H)
 * }
 */

$session_id = $args['session_id'] ?? null;
$chamber = $args['chamber'] ?? 'S';
$gov = $args['gov'] ?? 'US';
$votes = $args['votes'] ?? [];
$filter_state = $args['filter_state'] ?? null;
$filter_party = $args['filter_party'] ?? null;
$filter_party_slug = $args['filter_party_slug'] ?? null;
$filter_name = $args['filter_name'] ?? null;
$report_base_path = $args['report_base_path'] ?? '';

if (empty($session_id) || empty($votes)) {
    return;
}

// Get full legislator list for this session and chamber; state/party/name affect only dropdown pre-select and client-side visibility (JS)
$legislators = fi_legislators_get_by_session($session_id, ['chamber' => $chamber]);

if (empty($legislators)) {
    echo '<p class="text-muted">No legislators found for this session and chamber.</p>';
    return;
}

// Get rollcall data for all votes
$rollcall_data = [];
foreach ($votes as $vote) {
    if (!isset($vote->id)) continue;
    $rollcalls = fi_rollcalls_get_by_vote($vote->id);
    foreach ($rollcalls as $rc) {
        if (!isset($rc->legislator_id)) continue;
        // Handle both null and empty string for cast value
        $rollcall_data[$vote->id][$rc->legislator_id] = fi_rollcall_cast_normalize((string) ($rc->cast ?? ''));
    }
}

// Calculate report-specific scores for each legislator
$legislator_data = [];
foreach ($legislators as $leg) {
    $leg_votes = [];
    $votes_for_scoring = [];
    
    foreach ($votes as $vote) {
        if (!isset($vote->id)) continue;

        $cast = fi_rollcall_cast_normalize((string) ($rollcall_data[$vote->id][$leg->id] ?? ''));
        
        // Use consolidated vote formatting function
        $vote_format = fi_vote_format([
            'cast' => $cast,
            'constitutional' => $vote->constitutional ?? '',
            'format' => 'full'
        ]);
        
        // Prepare vote data for scoring function
        $votes_for_scoring[] = [
            'id' => $vote->id,
            'good' => $vote->constitutional ?? '',
            'cast' => $cast
        ];
        
        // Store vote data for card display
        $leg_votes[] = [
            'vote' => $vote,
            'cast' => $cast,
            'vote_format' => $vote_format
        ];
    }

    // Calculate report score using centralized function
    $report_score = null;
    if (!empty($votes_for_scoring)) {
        $report_score = fi_score_calculate_batch($votes_for_scoring);
    }
    
    $legislator_data[] = [
        'legislator' => $leg,
        'report_score' => $report_score,
        'votes' => $leg_votes
    ];
}


// State options: from FI_GOVERNMENTS excluding US (Congress only), so list is fixed and not derived from visible legislators
$states = [];
$parties = [];
$is_federal = ($gov === 'US');
if ($is_federal && defined('FI_GOVERNMENTS')) {
    $states = FI_GOVERNMENTS;
    unset($states['US']);
    ksort($states);
}

// Party options: from transient (parties that have ≥1 legislator in full session+chamber list) so options don’t disappear when one is selected
$transient_key = 'fi_report_parties_' . (int) $session_id . '_' . $chamber;
$parties = get_transient($transient_key);
if ($parties === false) {
    $parties = [];
    $all_legs = fi_legislators_get_by_session($session_id, ['chamber' => $chamber]);
    foreach ($all_legs as $leg) {
        if (!empty($leg->party)) {
            $name = fi_party_name($leg->party);
            if ($name) {
                $parties[$leg->party] = $name;
            }
        }
    }
    ksort($parties);
    set_transient($transient_key, $parties, WEEK_IN_SECONDS);
}
// Ensure current filter party remains in list so dropdown can show selected value
if ($filter_party_slug !== null && $filter_party_slug !== '' && !isset($parties[$filter_party_slug])) {
    $parties[$filter_party_slug] = fi_party_name($filter_party_slug);
    ksort($parties);
}

//Filter Bar
?>
<div class="row">
    <div class="col-12 col-md-10 col-lg-8 mx-auto pt-5 pb-4">
		<div class="card rounded-4 bg-primary">
			<h3 class="card-header text-white text-center">How did your legislators vote?</h3>
			<div class="card-body p-3 text-white">
				<form id="fi-report-legislator-filter" class="row g-3" method="get" action="" data-report-base-path="<?php echo esc_attr($report_base_path); ?>">
					<div class="col-12 col-md-4">
						<label for="fi-report-name-filter" class="form-label small d-none">Name</label>
						<div class="input-group input-group-sm">
							<input type="text" class="form-control" id="fi-report-name-filter" placeholder="Search by name..." autocomplete="off" value="<?php echo esc_attr($filter_name ?? ''); ?>">
							<button type="button" class="btn btn-outline-secondary d-none" id="fi-report-name-clear" title="Clear search"><i class="bi bi-x"></i></button>
						</div>
					</div>
					
					<?php if ($is_federal && !empty($states)): ?>
					<div class="col-12 col-md-4">
						<label for="fi-report-state-filter" class="form-label small d-none">State</label>
						<select class="form-select form-select-sm" id="fi-report-state-filter">
							<option value="">All States</option>
							<?php foreach ($states as $state_code => $state_name): 
								$state_val = strtoupper($state_code);
								$selected = ($filter_state && $filter_state === $state_val) ? ' selected' : '';
								?>
								<option value="<?php echo esc_attr($state_val); ?>" 
										data-full="<?php echo esc_attr($state_name); ?>"
										data-abbr="<?php echo esc_attr($state_val); ?>"<?php echo $selected; ?>>
									<span class="d-none d-md-inline"><?php echo esc_html($state_name); ?></span>
									<span class="d-inline d-md-none"><?php echo esc_html($state_val); ?></span>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php endif; ?>
					
					<?php if (!empty($parties)): ?>
					<div class="col-12 col-md-4">
						<label for="fi-report-party-filter" class="form-label small d-none">Party</label>
						<select class="form-select form-select-sm" id="fi-report-party-filter">
							<option value="">All Parties</option>
							<?php foreach ($parties as $party_code => $party_name): 
								$pselected = ($filter_party_slug !== null && $filter_party_slug !== '' && strtolower($party_code) === $filter_party_slug) ? ' selected' : '';
								?>
								<option value="<?php echo esc_attr($party_code); ?>"<?php echo $pselected; ?>>
									<?php echo esc_html($party_name); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php endif; ?>
					
					<input type="hidden" name="session_id" value="<?php echo esc_attr($session_id); ?>">
					<input type="hidden" name="chamber" value="<?php echo esc_attr($chamber); ?>">
					<input type="hidden" name="gov" value="<?php echo esc_attr($gov); ?>">
					<input type="hidden" name="action" value="fi_report_legislator_filter">
					<input type="hidden" name="nonce" value="<?php echo wp_create_nonce('fi_ajax_nonce'); ?>">
				</form>
			</div>
		</div>
	</div>
</div>

<div id="fi-report-legislators-results" class="row g-4">
    <?php foreach ($legislator_data as $data): 
        fi_get_template('partials/legislator-card-vote', [
            'legislator' => $data['legislator'],
            'report_score' => $data['report_score'],
            'votes' => $data['votes'],
            'gov' => $gov,
        ]);
    endforeach; ?>
</div>

<script>
(function() {
    'use strict';
    
    var $ = jQuery;
    var filterForm = $('#fi-report-legislator-filter');
    var resultsContainer = $('#fi-report-legislators-results');
    var nameInput = $('#fi-report-name-filter');
    var stateSelect = $('#fi-report-state-filter');
    var partySelect = $('#fi-report-party-filter');
    var debounceTimer;
    var isSubmitting = false;
    
    // Update state select display based on screen size
    function updateStateDisplay() {
        if (!stateSelect.length) return;
        var isMd = window.matchMedia('(min-width: 768px)').matches;
        stateSelect.find('option').each(function() {
            var $opt = $(this);
            if ($opt.val()) {
                $opt.text(isMd ? $opt.data('full') : $opt.data('abbr'));
            }
        });
    }
    
    // Handle window resize for state display
    $(window).on('resize', updateStateDisplay);
    updateStateDisplay();
    
    var clearBtn = $('#fi-report-name-clear');
    
    function filterLegislators() {
        if (isSubmitting) return;
        
        var name = nameInput.val().trim();
        
        // Show/hide clear button
        if (name.length > 0) {
            clearBtn.removeClass('d-none');
        } else {
            clearBtn.addClass('d-none');
        }
        
        // Require 2+ characters for name search, but if empty, show all
        if (name.length > 0 && name.length < 2) {
            return;
        }
        
        isSubmitting = true;
        
        // Client-side filtering (instant, no AJAX needed)
        var stateValue = stateSelect.length ? stateSelect.val() : '';
        var partyValue = partySelect.length ? partySelect.val() : '';
        
        var visibleCount = 0;
        $('.fi-legislator-card-vote').each(function() {
            var $card = $(this);
            var cardName = $card.data('name') || '';
            var cardState = $card.data('state') || '';
            var cardParty = $card.data('party') || '';
            
            // Name match: if name is empty or 1 char, show all; if 2+ chars, require match
            var nameMatch = true;
            if (name.length >= 2) {
                nameMatch = cardName.indexOf(name.toLowerCase()) !== -1;
            }
            
            var stateMatch = !stateValue || cardState === stateValue.toUpperCase();
            // Party: option value and data-party are slug (r, d, etc.)
            var partyMatch = !partyValue || cardParty.toLowerCase() === partyValue.toLowerCase();
            
            if (nameMatch && stateMatch && partyMatch) {
                $card.show();
                visibleCount++;
            } else {
                $card.hide();
            }
        });
        
        // Show no results message if needed
        var $noResults = resultsContainer.find('.fi-no-results');
        if (visibleCount === 0) {
            if (!$noResults.length) {
                resultsContainer.append('<div class="col-12 fi-no-results"><div class="alert alert-info text-center"><p class="mb-0">No legislators match your filters.</p></div></div>');
            }
        } else {
            $noResults.remove();
        }
        
        // Pretty URL: base/state/XX/party/slug/search/term (party = slug like legislators)
        var basePath = filterForm.attr('data-report-base-path') || '';
        if (basePath && window.history && window.history.replaceState) {
            var segs = basePath;
            if (stateValue) { segs += '/state/' + stateValue.toLowerCase(); }
            if (partyValue) { segs += '/party/' + encodeURIComponent(partyValue.toLowerCase()); }
            if (name.length >= 2) { segs += '/search/' + encodeURIComponent(name); }
            segs += '/';
            window.history.replaceState({}, '', segs);
        }
        
        isSubmitting = false;
    }
    
    // Prevent form submit (e.g. Enter in search) so we never reload; run filter instead
    filterForm.on('submit', function(e) {
        e.preventDefault();
        filterLegislators();
        return false;
    });
    
    // Debounced name search (2+ characters or empty)
    nameInput.on('input', function() {
        clearTimeout(debounceTimer);
        var name = $(this).val().trim();
        // Filter if empty (show all) or 2+ characters
        if (name.length === 0 || name.length >= 2) {
            debounceTimer = setTimeout(filterLegislators, 300);
        }
    });
    
    // Clear search button
    clearBtn.on('click', function() {
        nameInput.val('').focus();
        clearBtn.addClass('d-none');
        filterLegislators();
    });
    
    // Instant filter on state/party change
    if (stateSelect.length) {
        stateSelect.on('change', filterLegislators);
    }
    if (partySelect.length) {
        partySelect.on('change', filterLegislators);
    }
    
    // Form is pre-filled from server (pretty URL query vars); run filter once on load
    (function applyFiltersOnLoad() {
        var name = nameInput.val().trim();
        var state = stateSelect.length ? stateSelect.val() : '';
        var party = partySelect.length ? partySelect.val() : '';
        if (name.length > 0) { clearBtn.removeClass('d-none'); }
        if (name || state || party) {
            updateStateDisplay();
            filterLegislators();
        }
    })();
})();
</script>

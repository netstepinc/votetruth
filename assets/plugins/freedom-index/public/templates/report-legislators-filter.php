<?php if (!defined('ABSPATH')) exit;

/**
 * Legislator filter bar. Expects $args: session_id, chamber, gov, filter_state, filter_party, filter_party_slug,
 * filter_name, report_base_path, states, parties, is_federal (from fi_report_legislators_build_data + report context).
 */
$session_id = $args['session_id'] ?? null;
$chamber = $args['chamber'] ?? 'S';
$gov = $args['gov'] ?? 'US';
$filter_state = $args['filter_state'] ?? null;
$filter_party = $args['filter_party'] ?? null;
$filter_party_slug = $args['filter_party_slug'] ?? null;
$filter_name = $args['filter_name'] ?? '';
$report_base_path = $args['report_base_path'] ?? '';
$states = $args['states'] ?? [];
$parties = $args['parties'] ?? [];
$is_federal = !empty($args['is_federal']);
?>
<div class="row">
    <div class="col-12 col-md-10 col-lg-8 mx-auto pb-4">
        <div class="card rounded-4 bg-primary">
            <h3 class="card-header text-white text-center">How did your legislators vote?</h3>
            <div class="card-body p-3 text-white">
                <form id="fi-report-legislator-filter" class="row g-3" method="get" action="" data-report-base-path="<?php echo esc_attr($report_base_path); ?>">
                    <div class="col-12 col-md-4">
                        <label for="fi-report-name-filter" class="form-label small d-none">Name</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="fi-report-name-filter" placeholder="Search by name..." autocomplete="off" value="<?php echo esc_attr($filter_name); ?>">
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
    $(window).on('resize', updateStateDisplay);
    updateStateDisplay();

    var clearBtn = $('#fi-report-name-clear');

    function filterLegislators() {
        if (isSubmitting) return;
        var name = nameInput.val().trim();
        if (name.length > 0) {
            clearBtn.removeClass('d-none');
        } else {
            clearBtn.addClass('d-none');
        }
        if (name.length > 0 && name.length < 2) {
            return;
        }
        isSubmitting = true;

        var stateValue = stateSelect.length ? stateSelect.val() : '';
        var partyValue = partySelect.length ? partySelect.val() : '';
        var nameLower = name.length >= 2 ? name.toLowerCase() : '';

        // Target both grid cards and table rows
        var visibleCount = 0;
        $('.fi-legislator-card-vote, .scorecard-row').each(function() {
            var $el = $(this);
            var cardName = $el.data('name') || '';
            var cardState = $el.data('state') || '';
            var cardParty = $el.data('party') || '';

            var nameMatch = name.length < 2 || cardName.indexOf(nameLower) !== -1;
            var stateMatch = !stateValue || cardState === stateValue.toUpperCase();
            var partyMatch = !partyValue || cardParty.toLowerCase() === partyValue.toLowerCase();

            if (nameMatch && stateMatch && partyMatch) {
                $el.show();
                visibleCount++;
            } else {
                $el.hide();
            }
        });

        if (resultsContainer.length) {
            var $noResults = resultsContainer.find('.fi-no-results');
            if (visibleCount === 0) {
                if (!$noResults.length) {
                    resultsContainer.append('<div class="col-12 fi-no-results"><div class="alert alert-info text-center"><p class="mb-0">No legislators match your filters.</p></div></div>');
                }
            } else {
                $noResults.remove();
            }
        }

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

    filterForm.on('submit', function(e) {
        e.preventDefault();
        filterLegislators();
        return false;
    });

    nameInput.on('input', function() {
        clearTimeout(debounceTimer);
        var name = $(this).val().trim();
        if (name.length === 0 || name.length >= 2) {
            debounceTimer = setTimeout(filterLegislators, 300);
        }
    });

    clearBtn.on('click', function() {
        nameInput.val('').focus();
        clearBtn.addClass('d-none');
        filterLegislators();
    });

    if (stateSelect.length) stateSelect.on('change', filterLegislators);
    if (partySelect.length) partySelect.on('change', filterLegislators);

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

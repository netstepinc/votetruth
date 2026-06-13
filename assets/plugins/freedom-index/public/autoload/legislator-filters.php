<?php
/*
 * Freedom Index Legislator Filter Bar
 *
 * Straight function version of the former FI\Public\LegislatorFilters class file.
 *
 * Generates the public legislator filter form and supporting filter-option helpers.
 * Public routing remains ID-based for sessions; party slugs remain internal filter keys.
 * Refactored the legislator filter bar into straight functions.

Key adjustments:

Removed the FI\Public\LegislatorFilters class/namespace wrapper.
Preserved existing public API:
fi_legislator_filters()
fi_filter_get_options()
fi_filter_clear_cache()
fi_party_get_distinct()
fi_chamber_get_distinct()
fi_filter_build_description()
Added:
fi_filter_log()
fi_filter_debug_enabled()
fi_filter_normalize_gov()
fi_filter_transient_key()
Removed session slug output from the session filter options.
Kept party slug because in this file it is really the party abbreviation/filter key, not public entity routing.
 */

if (!defined('ABSPATH')) exit;

/**
 * Log filter diagnostics through the FI logging area system.
 *
 * @param string $message Log message.
 * @param string $file File path.
 * @param int $line Line number.
 * @param string $level Log level.
 * @return bool
 */
function fi_filter_log(string $message, string $file = '', int $line = 0, string $level = 'debug'): bool {
	if (!function_exists('fi_log_area')) {
		return false;
	}

	return fi_log_area('filters', $message, $file, $line, $level);
}

/**
 * Check whether filter debugging is enabled for the current request.
 *
 * @return bool
 */
function fi_filter_debug_enabled(): bool {
	return isset($_GET['fi_debug']) && sanitize_text_field(wp_unslash($_GET['fi_debug'])) === 'filters';
}

/**
 * Normalize government code for filter helpers.
 *
 * @param string $gov Government code.
 * @return string Normalized government code.
 */
function fi_filter_normalize_gov(string $gov): string {
	return strtoupper(sanitize_key($gov));
}

/**
 * Get transient key for a government's filter options.
 *
 * @param string $gov Government code.
 * @return string Transient key.
 */
function fi_filter_transient_key(string $gov): string {
	return 'fi_filter_options_' . fi_filter_normalize_gov($gov);
}

/**
 * Clear the filter-options transient for a government.
 *
 * Use when staff changes sessions/setup so the next front load rebuilds with correct sessions.
 *
 * @param string $gov Government code.
 * @return void
 */
function fi_filter_clear_cache(string $gov): void {
	delete_transient(fi_filter_transient_key($gov));
}

/**
 * Get cached filter options for a government.
 *
 * Returns sessions, parties, and chambers in one cached array.
 *
 * @param string $gov Government code.
 * @param bool $force_refresh Force transient rebuild.
 * @return array{sessions:array,parties:array,chambers:array}
 */
function fi_filter_get_options(string $gov, bool $force_refresh = false): array {
	global $wpdb;

	$gov = fi_filter_normalize_gov($gov);
	if ($gov === '') {
		return [
			'sessions' => [],
			'parties'  => [],
			'chambers' => [],
		];
	}

	$transient_key = fi_filter_transient_key($gov);
	$debug_filters = fi_filter_debug_enabled();

	if (!$force_refresh && (!defined('FI_DEV') || !FI_DEV)) {
		$options = get_transient($transient_key);
		if ($options !== false && is_array($options)) {
			$sessions_empty = empty($options['sessions']);
			$parties_empty  = empty($options['parties']);
			$chambers_empty = empty($options['chambers']);

			if ($sessions_empty || $parties_empty || $chambers_empty) {
				if ($debug_filters) {
					fi_filter_log('fi_filter_get_options - stale transient detected for ' . $gov . '; rebuilding', __FILE__, __LINE__, 'debug');
				}

				$options = fi_filter_get_options($gov, true);
			}

			return [
				'sessions' => is_array($options['sessions'] ?? null) ? $options['sessions'] : [],
				'parties'  => is_array($options['parties'] ?? null) ? $options['parties'] : [],
				'chambers' => is_array($options['chambers'] ?? null) ? $options['chambers'] : [],
			];
		}
	}

	$sessions_raw = function_exists('fi_sessions_get_by_gov')
		? fi_sessions_get_by_gov($gov, [
			'orderby' => 'date_start',
			'order'   => 'DESC',
		])
		: [];

	$sessions = [];
	foreach ($sessions_raw as $session) {
		$sessions[] = (object) [
			'id'         => (int) ($session->id ?? 0),
			'name'       => $session->name ?? '',
			'date_start' => $session->date_start ?? '',
		];
	}

	$party_chamber_data = $wpdb->get_results($wpdb->prepare(
		"SELECT DISTINCT ls.party, ls.chamber
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
		WHERE s.gov = %s
		AND ((ls.party IS NOT NULL AND ls.party != '') OR (ls.chamber IS NOT NULL AND ls.chamber IN ('H', 'S')))
		ORDER BY ls.party ASC, ls.chamber ASC",
		$gov
	));

	$used_party_abbrs = [];
	foreach ($party_chamber_data as $row) {
		$party = strtoupper(trim((string) ($row->party ?? '')));
		if ($party !== '' && !in_array($party, $used_party_abbrs, true)) {
			$used_party_abbrs[] = $party;
		}
	}

	if ($debug_filters) {
		fi_filter_log('fi_filter_get_options - Found party abbreviations in DB for ' . $gov . ': ' . wp_json_encode($used_party_abbrs), __FILE__, __LINE__, 'debug');
	}

	$all_parties = function_exists('fi_parties') ? fi_parties() : [];
	$parties = [];

	foreach ($used_party_abbrs as $abbr) {
		if (isset($all_parties[$abbr])) {
			$parties[] = (object) [
				'name'         => $all_parties[$abbr]['name'] ?? $abbr,
				'slug'         => $abbr,
				'abbreviation' => $abbr,
			];
		}
	}

	if ($debug_filters) {
		fi_filter_log('fi_filter_get_options - Final parties array for ' . $gov . ': ' . wp_json_encode(array_map(static function($p) { return $p->abbreviation; }, $parties)), __FILE__, __LINE__, 'debug');
	}

	usort($parties, static function($a, $b) {
		return strcmp((string) $a->name, (string) $b->name);
	});

	$chambers = [];
	foreach ($party_chamber_data as $row) {
		$chamber = strtoupper(trim((string) ($row->chamber ?? '')));
		if (in_array($chamber, ['H', 'S'], true) && !in_array($chamber, $chambers, true)) {
			$chambers[] = $chamber;
		}
	}

	sort($chambers);

	if (function_exists('fi_gov_is_unicameral') && fi_gov_is_unicameral($gov)) {
		$chambers = ['S'];
	} elseif (empty($chambers)) {
		$chambers = ['H', 'S'];
	}

	$options = [
		'sessions' => $sessions,
		'parties'  => $parties,
		'chambers' => $chambers,
	];

	set_transient($transient_key, $options, MONTH_IN_SECONDS);

	return $options;
}

/**
 * Get distinct party names for a government.
 *
 * @param string $gov Government code.
 * @return array Party names.
 */
function fi_party_get_distinct(string $gov): array {
	$options = fi_filter_get_options($gov);
	return array_column($options['parties'], 'name');
}

/**
 * Get distinct chamber codes for a government.
 *
 * @param string $gov Government code.
 * @return array Chamber codes.
 */
function fi_chamber_get_distinct(string $gov): array {
	$options = fi_filter_get_options($gov);
	return $options['chambers'];
}

/**
 * Build filter description string from active filters.
 *
 * @param array $args Filter arguments.
 * @return string Description string.
 */
function fi_filter_build_description(array $args): string {
	$defaults = [
		'gov'     => '',
		'session' => null,
		'party'   => '',
		'chamber' => '',
		'state'   => '',
	];

	$args = wp_parse_args($args, $defaults);

	if (empty($args['gov'])) {
		return '';
	}

	$gov = fi_filter_normalize_gov((string) $args['gov']);
	$description_parts = [];

	if (!empty($args['session']) && is_numeric($args['session'])) {
		$session_obj = function_exists('fi_session_get') ? fi_session_get((int) $args['session']) : null;
		if ($session_obj && !empty($session_obj->name)) {
			$description_parts[] = $session_obj->name;
		}
	}

	if (!empty($args['party']) && function_exists('fi_party_name')) {
		$party_name = fi_party_name((string) $args['party']);
		if ($party_name) {
			$description_parts[] = $party_name . '<span class="d-none d-lg-inline"> Party</span>';
		}
	}

	if (!empty($args['chamber']) && function_exists('fi_chamber_label')) {
		$chamber_label = fi_chamber_label($gov, (string) $args['chamber']);
		if ($chamber_label) {
			$description_parts[] = $chamber_label;
		}
	}

	if (!empty($args['state']) && $gov === 'US' && function_exists('fi_gov_name')) {
		$state_name = fi_gov_name(strtoupper((string) $args['state']));
		if ($state_name) {
			$description_parts[] = 'from ' . $state_name;
		}
	}

	return !empty($description_parts) ? implode(' ', $description_parts) : '';
}

/**
 * Render legislator filter bar.
 *
 * @param array $args Filter arguments.
 * @return string HTML filter form.
 */
function fi_legislator_filters($args = []): string {
	$defaults = [
		'gov'              => '',
		'session'          => null,
		'party'            => '',
		'chamber'          => '',
		'search'           => '',
		'sort'             => 'na',
		'form_id'          => 'fi-legislator-filters',
		'form_label_class' => 'text-muted small mb-0',
		'htmx'             => true,
		'htmx_target'      => '#fi-legislators-results',
		'htmx_indicator'   => '.fi-htmx-indicator',
	];

	$args = wp_parse_args((array) $args, $defaults);

	if (empty($args['gov'])) {
		global $fi_gov;
		$args['gov'] = $fi_gov ?? get_query_var('fi_gov') ?? '';
	}

	if (empty($args['gov'])) {
		return '';
	}

	$gov = fi_filter_normalize_gov((string) $args['gov']);
	$gov_name = function_exists('fi_gov_name') ? fi_gov_name($gov) : $gov;

	$filter_options = fi_filter_get_options($gov);
	$sessions = $filter_options['sessions'];
	$parties = $filter_options['parties'];
	$chambers = $filter_options['chambers'];
	$chamber_labels = function_exists('fi_chamber_options') ? fi_chamber_options($gov) : [];

	$current_filters = function_exists('fi_parse_filters') ? fi_parse_filters($gov) : [];

	$current_session_id = !empty($current_filters['session_id']) ? (int) $current_filters['session_id'] : null;
	$current_party_slug = $current_filters['party_slug'] ?? '';
	$current_chamber = $current_filters['chamber'] ?? '';
	$current_search = $current_filters['search'] ?? '';
	$current_state = $current_filters['state'] ?? '';
	$current_sort = $current_filters['sort'] ?? 'na';

	if (!$current_session_id) {
		$current_session_obj = function_exists('fi_session_get_current') ? fi_session_get_current($gov) : null;
		if ($current_session_obj) {
			$current_session_id = (int) $current_session_obj->id;
		} elseif (!empty($sessions)) {
			$current_session_id = (int) $sessions[0]->id;
		}
	}

	global $fi_entity;
	$current_entity = get_query_var('fi_entity') ?: ($fi_entity ?? '');
	$is_legislators_page = ($current_entity === 'legislators');

	$classFormLabel = (string) $args['form_label_class'];
	$col_name = ($gov === 'US') ? '1' : '3';

	$htmx_attrs = '';
	if (!empty($args['htmx']) && $is_legislators_page) {
		$htmx_attrs = sprintf(
			' hx-get="%s" hx-target="%s" hx-indicator="%s" hx-push-url="true" hx-select="%s" hx-trigger="change from:select, keyup changed delay:500ms from:input"',
			esc_url(home_url('/' . strtolower($gov) . '/legislators/')),
			esc_attr($args['htmx_target'] ?? '#fi-legislators-results'),
			esc_attr($args['htmx_indicator'] ?? '.fi-htmx-indicator'),
			esc_attr('#fi-legislators-grid, #fi-legislators-results > *')
		);
	}

	ob_start();
	?>
	<form id="<?php echo esc_attr($args['form_id']); ?>" class="w-100" method="get" action="<?php echo esc_url(home_url('/' . strtolower($gov) . '/legislators/')); ?>" data-gov="<?php echo esc_attr(strtolower($gov)); ?>"<?php echo $htmx_attrs; ?>>
		<div class="row">
			<div class="col-6 col-md-4 col-lg-<?php echo esc_attr($col_name); ?> px-md-1 py-1 py-lg-0">
				<div class="form-group">
					<?php $gov_name_adj = ($gov === 'US') ? 'Congressional' : $gov_name; ?>
					<input type="text" id="fi-search" name="search" class="form-control form-control-sm" value="<?php echo esc_attr($current_search); ?>" placeholder="Search Name" aria-label="<?php echo esc_attr($gov_name_adj); ?> legislator name search" />
				</div>
			</div>

			<?php if ($gov === 'US'): ?>
			<div class="col-6 col-md-4 col-lg-2 px-md-1 py-1 py-lg-0">
				<div class="form-group">
					<select id="fi-state" name="state" class="form-select form-select-sm" aria-label="State filter">
						<option value="">All States</option>
						<?php
						$states = function_exists('fi_state_options') ? fi_state_options() : [];
						foreach ($states as $state_code => $state_name) {
							echo '<option value="' . esc_attr(strtolower((string) $state_code)) . '" ' . selected(strtolower((string) $current_state), strtolower((string) $state_code), false) . '>' . esc_html($state_name) . '</option>';
						}
						?>
					</select>
				</div>
			</div>
			<?php endif; ?>

			<div class="col-6 col-md-4 col-lg-2 px-md-1 py-1 py-lg-0">
				<div class="form-group">
					<select id="fi-session" name="session" class="form-select form-select-sm" aria-label="Session filter">
						<?php if ($is_legislators_page): ?>
							<option value="" data-session-id="">All Sessions</option>
						<?php endif; ?>
						<?php foreach ($sessions as $session): ?>
							<option value="<?php echo esc_attr($session->id); ?>" data-session-id="<?php echo esc_attr($session->id); ?>" <?php selected($current_session_id, $session->id); ?>><?php echo esc_html($session->name); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<?php if (count($chambers) > 1): ?>
			<div class="col-6 col-md-4 col-lg-2 px-md-1 py-1 py-lg-0">
				<div class="form-group">
					<select id="fi-chamber" name="chamber" class="form-select form-select-sm" aria-label="Chamber filter">
						<option value="">All Chambers</option>
						<?php foreach ($chambers as $chamber): ?>
							<?php if (!empty($chamber_labels[$chamber]['chamber'])): ?>
								<option value="<?php echo esc_attr($chamber); ?>" <?php selected($current_chamber, $chamber); ?>><?php echo esc_html($chamber_labels[$chamber]['chamber']); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<?php endif; ?>

			<div class="col-6 col-md-4 col-lg-2 px-md-1 py-1 py-lg-0">
				<div class="form-group">
					<select id="fi-party" name="party" class="form-select form-select-sm" aria-label="Party filter">
						<option value="">All Parties</option>
						<?php foreach ($parties as $party): ?>
							<option value="<?php echo esc_attr($party->slug); ?>" <?php selected($current_party_slug, $party->slug); ?>><?php echo esc_html($party->name); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="col-6 col-md-4 col-lg-2 px-md-1 py-1 py-lg-0">
				<div class="form-group">
					<select id="fi-sort" name="sort" class="form-select form-select-sm" aria-label="Sort filter">
						<?php
						$sorts = function_exists('fi_legislators_sort_options') ? fi_legislators_sort_options() : ['na' => 'Name A-Z'];
						$selected_sort = strtolower((string) ($current_sort ?? 'na'));
						foreach ($sorts as $sort_code => $sort_name):
							$option_value = strtolower((string) $sort_code);
							?>
							<option value="<?php echo esc_attr($option_value); ?>" <?php selected($selected_sort, $option_value); ?>><?php echo esc_html($sort_name); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<?php if (empty($args['htmx'])): ?>
			<div class="col-6 col-md-4 col-lg-1 px-md-1 py-1 py-lg-0">
				<div class="form-group">
					<button type="submit" id="fi-submit" class="form-control form-control-sm btn btn-outline-success fi-filter-submit">Search</button>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</form>

	<script>
	jQuery(document).ready(function($) {
		var filterForm = $('#<?php echo esc_js($args['form_id']); ?>');
		var isLegislatorsPage = <?php echo $is_legislators_page ? 'true' : 'false'; ?>;
		var htmxEnabled = <?php echo !empty($args['htmx']) ? 'true' : 'false'; ?>;
		var debounceTimer;
		var isSubmitting = false;
		var initialFormState = null;
		var pageLoaded = false;

		if (htmxEnabled && typeof htmx !== 'undefined') {
			filterForm.on('htmx:configRequest', function(evt) {
				var gov = filterForm.data('gov') || '<?php echo esc_js(strtolower($gov)); ?>';
				var chamber = $('#fi-chamber').val();
				var partySlug = $('#fi-party').val();
				var search = $('#fi-search').val();
				var state = $('#fi-state').val();
				var sort = $('#fi-sort').val();
				var sessionId = $('#fi-session').val();

				var url = '<?php echo esc_js(home_url('/')); ?>' + gov + '/legislators/';
				var segments = [];

				if (state && gov === 'us') segments.push('state/' + state);
				if (sessionId) segments.push('session/' + sessionId);
				if (chamber) segments.push('chamber/' + chamber);
				if (partySlug) segments.push('party/' + partySlug);
				if (search) segments.push('search/' + encodeURIComponent(search));
				if (sort && sort !== 'na') segments.push('sort/' + sort);

				if (segments.length > 0) url += segments.join('/') + '/';

				evt.detail.path = url;
				evt.detail.parameters = {};
			});

			return;
		}

		<?php if ($is_legislators_page): ?>
		function buildFilterUrl() {
			var gov = filterForm.data('gov') || '<?php echo esc_js(strtolower($gov)); ?>';
			var chamber = $('#fi-chamber').val();
			var partySlug = $('#fi-party').val();
			var search = $('#fi-search').val();
			var state = $('#fi-state').val();
			var sort = $('#fi-sort').val();
			var sessionId = $('#fi-session').val();
			var url = '<?php echo esc_js(home_url('/')); ?>' + gov + '/legislators/';
			var segments = [];

			if (state && gov === 'us') segments.push('state/' + state);
			if (sessionId) segments.push('session/' + sessionId);
			if (chamber) segments.push('chamber/' + chamber);
			if (partySlug) segments.push('party/' + partySlug);
			if (search) segments.push('search/' + encodeURIComponent(search));
			if (sort && sort !== 'na') segments.push('sort/' + sort);

			if (segments.length > 0) url += segments.join('/') + '/';
			return url;
		}

		function getFormState() {
			return $('#fi-session').val() + '|' +
				$('#fi-chamber').val() + '|' +
				$('#fi-party').val() + '|' +
				$('#fi-search').val() + '|' +
				$('#fi-state').val() + '|' +
				($('#fi-sort').length ? $('#fi-sort').val() : '');
		}

		function formMatchesUrl() {
			var currentUrl = window.location.pathname + window.location.search;
			var formUrl = buildFilterUrl();
			currentUrl = currentUrl.replace(/\/$/, '');
			formUrl = formUrl.replace(/\/$/, '');
			return currentUrl === formUrl || currentUrl + '/' === formUrl || currentUrl === formUrl + '/';
		}

		function submitFilters(skipUrlCheck) {
			if (isSubmitting) return;
			if (!skipUrlCheck && formMatchesUrl()) return;

			isSubmitting = true;

			var formData = filterForm.serialize();
			var resultsContainer = $('#fi-legislators-results');
			var prettyUrl = buildFilterUrl();

			if (!resultsContainer || !resultsContainer.length) {
				resultsContainer = $('.fi-legislators-list .row.g-4').parent();
				if (!resultsContainer || !resultsContainer.length) resultsContainer = $();
			}

			if (resultsContainer.length && !resultsContainer.find('#fi-legislators-loading').length) {
				resultsContainer.html('<div class="row" id="fi-legislators-loading"><div class="col-12"><div class="text-center py-5"><div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"><span class="visually-hidden">Loading...</span></div><p class="mt-3 mb-0 text-muted">Loading legislators...</p></div></div></div>');
			}

			$.ajax({
				url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
				type: 'POST',
				data: formData,
				success: function(response) {
					if (response.success) {
						if (resultsContainer.length) {
							resultsContainer.html(response.data.html);
						} else {
							$('.fi-legislators-list .row.g-4').html(response.data.html);
						}

						if (response.data.execution_time) {
							console.log('Execution time: ' + response.data.execution_time + ' seconds');
						}

						if (response.data.reports_nav_html) {
							var reportsNavRow = $('#fi-reports-nav').closest('.row');
							if (reportsNavRow.length) {
								reportsNavRow.replaceWith(response.data.reports_nav_html);
							} else if (resultsContainer.length) {
								resultsContainer.before(response.data.reports_nav_html);
							}
						} else {
							$('#fi-reports-nav').closest('.row').remove();
						}

						window.history.pushState({}, '', prettyUrl);

						var count = response.data.count || 0;
						var countContainer = $('#fi-results-count-container');
						if (count > 0) {
							$('#fi-results-count').text(count);
							$('#fi-results-plural').text(count !== 1 ? 's' : '');
							countContainer.show();
						} else {
							countContainer.hide();
						}

						if (response.data.filter_description !== undefined) {
							var descriptionText = response.data.filter_description;
							if (count <= 240) {
								var pdfUrl = prettyUrl + 'pdf/cards';
								descriptionText += ' <a href="' + pdfUrl + '" target="_blank" class="ms-3" style="color:#0055a4 !important;"><i class="bi bi-file-pdf"></i> Print</a>';
							}

							var descriptionElement = $('#fi-header-description');
							if (descriptionElement.length) descriptionElement.html(descriptionText);

							var placeholderElement = $('#fi-results-count-placeholder');
							if (placeholderElement.length && count > 0) {
								placeholderElement.text(count + ' legislator' + (count !== 1 ? 's' : '') + ' found');
							}
						}

						initialFormState = getFormState();
					}
				},
				complete: function() {
					isSubmitting = false;
					if (resultsContainer.length) resultsContainer.removeClass('opacity-50');
				}
			});
		}

		setTimeout(function() {
			initialFormState = getFormState();
			pageLoaded = true;
			var sessionValue = $('#fi-session').val();
			if (sessionValue) submitFilters(true);
		}, 100);

		filterForm.find('select, input[type="text"]').on('change input', function() {
			if (!pageLoaded) return;
			var currentState = getFormState();
			if (currentState !== initialFormState) {
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(function() {
					submitFilters(false);
				}, 500);
			}
		});
		<?php else: ?>
		function buildFilterUrl() {
			var gov = filterForm.data('gov') || '<?php echo esc_js(strtolower($gov)); ?>';
			var segments = [];
			var state = $('#fi-state').val();
			var sessionId = $('#fi-session').val();
			var chamber = $('#fi-chamber').val();
			var partySlug = $('#fi-party').val();
			var search = $('#fi-search').val();
			var url = '<?php echo esc_js(home_url('/')); ?>' + gov + '/legislators/';

			if (state && gov === 'us') segments.push('state/' + state);
			if (sessionId) segments.push('session/' + sessionId);
			if (chamber) segments.push('chamber/' + chamber);
			if (partySlug) segments.push('party/' + partySlug);
			if (search) segments.push('search/' + encodeURIComponent(search));

			if (segments.length > 0) url += segments.join('/') + '/';
			return url;
		}

		filterForm.on('submit', function(e) {
			e.preventDefault();
			window.location.href = buildFilterUrl();
		});
		<?php endif; ?>
	});
	</script>
	<?php
	return ob_get_clean();
}
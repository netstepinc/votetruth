<?php
if (!defined('ABSPATH')) exit;

function fi_filter_log(string $message, string $file = '', int $line = 0, string $level = 'debug'): bool {
	return function_exists('fi_log_area') && fi_log_area('filters', $message, $file, $line, $level);
}

function fi_filter_debug_enabled(): bool {
	return isset($_GET['fi_debug']) && sanitize_text_field(wp_unslash($_GET['fi_debug'])) === 'filters';
}

function fi_filter_normalize_gov(string $gov): string {
	return strtoupper(sanitize_key($gov));
}

function fi_filter_transient_key(string $gov): string {
	return 'fi_filter_options_' . fi_filter_normalize_gov($gov);
}

function fi_filter_clear_cache(string $gov): void {
	delete_transient(fi_filter_transient_key($gov));
}

/**
 * Return active filter values set by the rewrite handler.
 * Consumed by fi_legislator_filters() to pre-select the correct options.
 *
 * @param string $gov Government code (unused — context already in $fi_filters).
 * @return array Keys: session_id, chamber, party_slug, state, sort, search.
 */
function fi_parse_filters(string $gov = ''): array {
	global $fi_filters;
	return is_array($fi_filters) ? $fi_filters : [];
}

/**
 * Get cached filter options (sessions, parties, chambers) for a government.
 *
 * @param string $gov Government code.
 * @param bool $force_refresh Force transient rebuild.
 * @return array{sessions:array,parties:array,chambers:array}
 */
function fi_filter_get_options(string $gov, bool $force_refresh = false): array {
	$gov = fi_filter_normalize_gov($gov);
	if ($gov === '') {
		return ['sessions' => [], 'parties' => [], 'chambers' => []];
	}

	$transient_key = fi_filter_transient_key($gov);

	if (!$force_refresh && (!defined('FI_DEV') || !FI_DEV)) {
		$cached = get_transient($transient_key);
		if (is_array($cached) && !empty($cached['sessions']) && !empty($cached['parties']) && !empty($cached['chambers'])) {
			return $cached;
		}
	}

	$sessions = [];
	foreach (fi_sessions_get_by_gov($gov, ['orderby' => 'date_start', 'order' => 'DESC']) as $s) {
		$sessions[] = ['id' => (int) $s['id'], 'name' => $s['name'] ?? '', 'date_start' => $s['date_start'] ?? ''];
	}

	$rows = fi_legislator_sessions_get_party_chamber($gov);

	$party_abbrs = [];
	$chambers    = [];
	foreach ($rows as $row) {
		$party   = strtoupper(trim((string) ($row->party ?? '')));
		$chamber = strtoupper(trim((string) ($row->chamber ?? '')));
		if ($party !== '' && !in_array($party, $party_abbrs, true)) {
			$party_abbrs[] = $party;
		}
		if (in_array($chamber, ['H', 'S'], true) && !in_array($chamber, $chambers, true)) {
			$chambers[] = $chamber;
		}
	}

	$all_parties = fi_parties();
	$parties = [];
	foreach ($party_abbrs as $abbr) {
		if (isset($all_parties[$abbr])) {
			$parties[] = ['name' => $all_parties[$abbr]['name'] ?? $abbr, 'slug' => $abbr];
		}
	}
	usort($parties, fn($a, $b) => strcmp($a['name'], $b['name']));

	sort($chambers);
	if (function_exists('fi_gov_is_unicameral') && fi_gov_is_unicameral($gov)) {
		$chambers = ['S'];
	} elseif (empty($chambers)) {
		$chambers = ['H', 'S'];
	}

	$options = ['sessions' => $sessions, 'parties' => $parties, 'chambers' => $chambers];
	set_transient($transient_key, $options, MONTH_IN_SECONDS);
	return $options;
}

/**
 * Build filter description string from active filters.
 *
 * @param array $args Filter arguments (gov, session, party, chamber, state).
 * @return string Description string.
 */
function fi_filter_build_description(array $args): string {
	$gov = fi_filter_normalize_gov((string) ($args['gov'] ?? ''));
	if ($gov === '') return '';

	$parts = [];

	if (!empty($args['session']) && is_numeric($args['session'])) {
		$session = fi_session_get((int) $args['session']);
		if (!empty($session['name'])) {
			$parts[] = $session['name'];
		}
	}

	if (!empty($args['party'])) {
		$party_name = fi_party_name((string) $args['party']);
		if ($party_name) {
			$parts[] = $party_name . '<span class="d-none d-lg-inline"> Party</span>';
		}
	}

	if (!empty($args['chamber'])) {
		$label = function_exists('fi_chamber_label') ? fi_chamber_label($gov, (string) $args['chamber']) : '';
		if ($label) $parts[] = $label;
	}

	if (!empty($args['state']) && $gov === 'US') {
		$state_name = fi_gov_name(strtoupper((string) $args['state']));
		if ($state_name) $parts[] = 'from ' . $state_name;
	}

	return implode(' ', $parts);
}

/**
 * Render legislator filter bar.
 *
 * @param array $args Filter arguments.
 * @return string HTML filter form.
 */
function fi_legislator_filters(array $args = []): string {
	$args = wp_parse_args($args, [
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
	]);

	if (empty($args['gov'])) {
		global $fi_gov;
		$args['gov'] = $fi_gov ?? get_query_var('fi_gov') ?? '';
	}

	if (empty($args['gov'])) return '';

	$gov      = fi_filter_normalize_gov((string) $args['gov']);
	$gov_name = fi_gov_name($gov);

	['sessions' => $sessions, 'parties' => $parties, 'chambers' => $chambers] = fi_filter_get_options($gov);
	$chamber_labels = fi_chamber_options($gov);

	$current_filters    = function_exists('fi_parse_filters') ? fi_parse_filters($gov) : [];
	$current_session_id = !empty($current_filters['session_id']) ? (int) $current_filters['session_id'] : null;
	$current_party_slug = $current_filters['party_slug'] ?? '';
	$current_chamber    = $current_filters['chamber']    ?? '';
	$current_search     = $current_filters['search']     ?? '';
	$current_state      = $current_filters['state']      ?? '';
	$current_sort       = $current_filters['sort']       ?? 'na';

	if (!$current_session_id) {
		$current_session    = fi_session_get_current($gov);
		$current_session_id = $current_session ? (int) $current_session['id'] : (int) ($sessions[0]['id'] ?? 0);
	}

	global $fi_entity;
	$is_legislators_page = (get_query_var('fi_entity') ?: ($fi_entity ?? '')) === 'legislators';
	$col_name = ($gov === 'US') ? '1' : '3';

	$htmx_attrs = '';
	if (!empty($args['htmx']) && $is_legislators_page) {
		$htmx_attrs = sprintf(
			' hx-get="%s" hx-target="%s" hx-indicator="%s" hx-select="%s" hx-push-url="true" hx-trigger="change from:select, keyup changed delay:500ms from:input"',
			esc_url(home_url('/' . strtolower($gov) . '/legislators/')),
			esc_attr($args['htmx_target']),
			esc_attr($args['htmx_indicator']),
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
							<option value="<?php echo esc_attr($session['id']); ?>" data-session-id="<?php echo esc_attr($session['id']); ?>" <?php selected($current_session_id, $session['id']); ?>><?php echo esc_html($session['name']); ?></option>
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
							<option value="<?php echo esc_attr($party['slug']); ?>" <?php selected($current_party_slug, $party['slug']); ?>><?php echo esc_html($party['name']); ?></option>
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
	(function() {
		var formId  = '<?php echo esc_js($args['form_id']); ?>';
		var gov     = '<?php echo esc_js(strtolower($gov)); ?>';
		var baseUrl = '<?php echo esc_js(home_url('/')); ?>' + gov + '/legislators/';

		function q(id) { return document.getElementById(id); }

		function buildFilterUrl() {
			var segments = [];
			var state   = (q('fi-state')   || {}).value || '';
			var session = (q('fi-session') || {}).value || '';
			var chamber = (q('fi-chamber') || {}).value || '';
			var party   = (q('fi-party')   || {}).value || '';
			var search  = (q('fi-search')  || {}).value || '';
			var sort    = (q('fi-sort')    || {}).value || '';

			if (state && gov === 'us') segments.push('state/' + state);
			if (session)               segments.push('session/' + session);
			if (chamber)               segments.push('chamber/' + chamber);
			if (party)                 segments.push('party/' + party);
			if (search)                segments.push('search/' + encodeURIComponent(search));
			if (sort && sort !== 'na') segments.push('sort/' + sort);

			return baseUrl + (segments.length ? segments.join('/') + '/' : '');
		}

		// HTMX path override — document-level so it works regardless of HTMX load order.
		// Native CustomEvent bubbles up; no jQuery wrapper, so evt.detail is directly mutable.
		document.addEventListener('htmx:configRequest', function(evt) {
			if (!evt.target || evt.target.id !== formId) return;
			evt.detail.path       = buildFilterUrl();
			evt.detail.parameters = {};
		});

		// After HTMX swaps the grid, sync the results counter in the page header.
		document.addEventListener('htmx:afterSwap', function() {
			var grid    = document.getElementById('fi-legislators-grid');
			var counter = document.getElementById('fi-results-count');
			if (grid && counter) {
				var total = parseInt(grid.dataset.total || '0', 10);
				counter.textContent = (total > 0 ? total.toLocaleString() + ' Found.' : 'No legislators found');
			}
		});

		// Non-HTMX fallback: navigate on form submit OR on any filter change.
		document.addEventListener('DOMContentLoaded', function() {
			var form = document.getElementById(formId);
			if (!form) return;

			form.addEventListener('submit', function(e) {
				e.preventDefault();
				window.location.href = buildFilterUrl();
			});

			// If HTMX is not present, navigate on change / search input.
			form.addEventListener('change', function(e) {
				if (typeof htmx !== 'undefined') return; // HTMX handles it
				if (e.target.tagName === 'SELECT') {
					window.location.href = buildFilterUrl();
				}
			});

			var searchDebounce;
			var searchEl = q('fi-search');
			if (searchEl) {
				searchEl.addEventListener('keyup', function() {
					if (typeof htmx !== 'undefined') return;
					clearTimeout(searchDebounce);
					searchDebounce = setTimeout(function() {
						window.location.href = buildFilterUrl();
					}, 500);
				});
			}
		});
	})();
	</script>
	<?php
	return ob_get_clean();
}

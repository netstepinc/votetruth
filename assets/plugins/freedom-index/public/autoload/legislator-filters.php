<?php
namespace FI\Public {

	if (!defined('ABSPATH')) exit;

	/**
	 * Legislator Filter Bar
	 * Generates filter form for searching/filtering legislators
	 * 
	 * @author Sam Mittelstaedt <smittelstaedt@jbs.org>
	 */
	class LegislatorFilters {
		
		/**
		 * Get cached filter options for a government
		 * Returns sessions, parties, and chambers in one cached array
		 * 
		 * @param string $gov Government code
		 * @return array {
		 *     @type array $sessions Array of session objects with id, slug, name
		 *     @type array $parties Array of party objects with name, slug
		 *     @type array $chambers Array of chamber codes (H, S)
		 * }
		 */
		public static function get_filter_options(string $gov, bool $force_refresh = false): array {
			$gov = strtoupper($gov);
			$transient_key = "fi_filter_options_{$gov}";
			$debug_filters = (isset($_GET['fi_debug']) && $_GET['fi_debug'] === 'filters');
			
			// Try to get from transient cache (unless forcing refresh or in dev mode)
			if (!$force_refresh && (!defined('FI_DEV') || !FI_DEV)) {
				$options = get_transient($transient_key);
				if ($options !== false && is_array($options)) {
					// Safety: after imports/migrations, stale transients can leave sessions empty which prevents
					// the initial legislators AJAX load from firing (session select has no value => spinner forever).
					// If we detect an "empty options" cache but the DB now has data, rebuild once.
					$sessions_empty = empty($options['sessions']);
					$parties_empty  = empty($options['parties']);
					$chambers_empty  = empty($options['chambers']);

					// Simplified stale cache detection: if any array is empty, rebuild
					// We'll detect actual data availability during the rebuild process
					if ($sessions_empty || $parties_empty || $chambers_empty) {
						if ($debug_filters) {
							self::log('LegislatorFilters::get_filter_options - stale transient detected for ' . $gov . '; rebuilding', __FILE__, __LINE__, 'debug');
						}
						$options = self::get_filter_options($gov, true);
					}

					return $options;
				}
			}
			
			global $wpdb;
			
			// Get sessions using core helper function (leverages request-level caching)
			$sessions_raw = fi_sessions_get_by_gov($gov, [
				'orderby' => 'date_start',
				'order' => 'DESC'
			]);
			
			// Map to expected format (id, slug, name, date_start)
			$sessions = [];
			foreach ($sessions_raw as $session) {
				$sessions[] = (object)[
					'id' => (int) $session->id,
					'slug' => $session->slug ?? '',
					'name' => $session->name ?? '',
					'date_start' => $session->date_start ?? ''
				];
			}
			
			// Get parties and chambers in a single combined query
			$party_chamber_data = $wpdb->get_results($wpdb->prepare(
				"SELECT DISTINCT ls.party, ls.chamber
				FROM {$wpdb->prefix}fi_legislator_sessions ls
				INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
				WHERE s.gov = %s 
				AND ((ls.party IS NOT NULL AND ls.party != '') OR (ls.chamber IS NOT NULL AND ls.chamber IN ('H', 'S')))
				ORDER BY ls.party ASC, ls.chamber ASC",
				$gov
			));
//fi_log('party_chamber_data: ' . json_encode($party_chamber_data),__FILE__,__LINE__);	
			// Extract distinct parties
			$used_party_abbrs = [];
			foreach ($party_chamber_data as $row) {
				if (!empty($row->party) && !in_array($row->party, $used_party_abbrs)) {
					$used_party_abbrs[] = $row->party;
				}
			}
			
			// Debug logging is opt-in only via ?fi_debug=filters (prevents runaway log writes under traffic).
			if ($debug_filters) {
				self::log('LegislatorFilters::get_filter_options - Found party abbreviations in DB for ' . $gov . ': ' . json_encode($used_party_abbrs), __FILE__, __LINE__, 'debug');
			}
			
			// Filter global parties array to only include those actually used
			$all_parties = fi_parties(); // Returns array with lowercase keys ('R', 'D', 'DL', etc.)
			$parties = [];
			foreach ($used_party_abbrs as $abbr) {
				// Normalize: trim whitespace and convert to lowercase for lookup (keys are lowercase)
				$abbr = strtoupper(trim($abbr));
				
				// Debug: Log each party lookup (opt-in only)
				//if ($debug_filters) {
				//	self::log('LegislatorFilters::get_filter_options - Checking party: ' . $abbr . ' (lower: ' . $abbr_lower . ', upper: ' . $abbr_upper . ') - exists: ' . (isset($all_parties[$abbr_lower]) ? 'yes' : 'no'), __FILE__, __LINE__, 'debug');
				//}
				
				if (isset($all_parties[$abbr])) {
					$parties[] = (object)[
						'name' => $all_parties[$abbr]['name'],
						'slug' => $abbr,
						'abbreviation' => $abbr
					];
				}
			}
			
			// Debug: Log final parties array (opt-in only)
			if ($debug_filters) {
				self::log('LegislatorFilters::get_filter_options - Final parties array for ' . $gov . ': ' . json_encode(array_map(function($p) { return $p->abbreviation; }, $parties)), __FILE__, __LINE__, 'debug');
			}

			// Sort by name (already sorted by abbreviation from query, but ensure name sort)
			usort($parties, function($a, $b) {
				return strcmp($a->name, $b->name);
			});
			
			// Extract distinct chambers from combined query results
			$chambers = [];
			foreach ($party_chamber_data as $row) {
				if (!empty($row->chamber) && in_array($row->chamber, ['H', 'S']) && !in_array($row->chamber, $chambers)) {
					$chambers[] = $row->chamber;
				}
			}
			sort($chambers);
			
			// Check if unicameral
			if (fi_gov_is_unicameral($gov)) {
				$chambers = ['S'];
			} elseif (empty($chambers)) {
				$chambers = ['H', 'S'];
			}
		
			$options = [
				'sessions' => $sessions ?: [],
				'parties' => $parties ?: [],
				'chambers' => $chambers
			];
			
			// Cache for 1 month (parties are stable across sessions for a government)
			// NOTE: To clear cache, see admin/TRANSIENTS-MANAGEMENT.md for implementation notes
			// Transient key pattern: fi_filter_options_{$gov} (e.g., fi_filter_options_US)
			set_transient($transient_key, $options, MONTH_IN_SECONDS);
			
			return $options;
		}
		
		/**
		 * Get distinct parties for a government (cached in transient)
		 * 
		 * @param string $gov Government code
		 * @return array Array of distinct party values
		 */
		public static function get_distinct_parties(string $gov): array {
			$options = self::get_filter_options($gov);
			return array_column($options['parties'], 'name');
		}
		
		/**
		 * Get distinct chambers for a government
		 * 
		 * @param string $gov Government code
		 * @return array Array of chamber codes (H, S)
		 */
		public static function get_distinct_chambers(string $gov): array {
			$options = self::get_filter_options($gov);
			return $options['chambers'];
		}
		
		/**
		 * Build filter description string from active filters
		 * 
		 * @param array $args {
		 *     Filter arguments
		 *     
		 *     @type string $gov           Government code (required)
		 *     @type int|string $session   Session ID or slug
		 *     @type string $party         Party slug
		 *     @type string $chamber       Chamber code (S or H)
		 *     @type string $state         State code (for congressional legislators)
		 * }
		 * @return string Description string (e.g., "119th Session Republican Party Senators from Arizona.")
		 */
		public static function build_filter_description(array $args): string {
			$defaults = array(
				'gov' => '',
				'session' => null,
				'party' => '',
				'chamber' => '',
				'state' => ''
			);
			
			$args = wp_parse_args($args, $defaults);
			
			if (empty($args['gov'])) {
				return '';
			}
			
			$gov = strtoupper($args['gov']);
			$description_parts = array();
			
			// Get session name if session filter is active
			if (!empty($args['session'])) {
				$session_id = null;
				// Session should now always be numeric ID
				if (is_numeric($args['session'])) {
					$session_id = (int) $args['session'];
				}
				
				if ($session_id) {
					$session_obj = fi_session_get($session_id);
					if ($session_obj && $session_obj->name) {
						$description_parts[] = $session_obj->name;
					}
				}
			}
			
			// Get party name if party filter is active
			if (!empty($args['party'])) {
				$party_name = fi_party_name($args['party']);
				if ($party_name) {
					$description_parts[] = $party_name . '<span class="d-none d-lg-inline"> Party</span>';
				}
			}
				
			// Get chamber label if chamber filter is active
			if (!empty($args['chamber'])) {
				$chamber_label = fi_chamber_label($gov, $args['chamber']);
				if ($chamber_label) {
					$description_parts[] = $chamber_label . 's';
				}
			}
			
			// Add state if filtering by state (for congressional legislators)
			if (!empty($args['state']) && $gov === 'US') {
				$state_name = fi_gov_name(strtoupper($args['state']));
				if ($state_name) {
					$description_parts[] = 'from ' . $state_name;
				}
			}
			
			// Build description string
			if (!empty($description_parts)) {
				return implode(' ', $description_parts);
			}
			
			return '';
		}
		
		/**
		 * Render legislator filter bar
		 * 
		 * @param array $args {
		 *     Optional. Filter arguments.
		 *     
		 *     @type string $gov           Government code (required)
		 *     @type int    $session       Current session ID (defaults to most recent)
		 *     @type string $party         Selected party filter
		 *     @type string $chamber      Selected chamber filter (H or S)
		 *     @type string $search        Search term
		 *     @type string $form_id       Form ID (default: 'fi-legislator-filters')
		 *     @type string $container_class Additional CSS classes
		 * }
		 * @return string HTML filter form
		 */
		public static function render($args = array()): string {
			$defaults = array(
				'gov' => '',
				'session' => null,
				'party' => '',
				'chamber' => '',
				'search' => '',
				'sort' => 'na',
				'form_id' => 'fi-legislator-filters',
				'form_label_class' => 'text-muted small mb-0'
			);
			
			$args = wp_parse_args($args, $defaults);
			
			// Read from query vars if not provided in args
			if (empty($args['gov'])) {
				global $fi_gov;
				$args['gov'] = $fi_gov ?? get_query_var('fi_gov') ?? '';
			}
			
			if (empty($args['gov'])) {
				return '';
			}
			
			$gov = strtoupper($args['gov']);
			$gov_name = fi_gov_name($gov);
			
			// Get cached filter options (sessions, parties, chambers)
			$filter_options = self::get_filter_options($gov);
			$sessions = $filter_options['sessions'];
			$parties = $filter_options['parties'];
			$chambers = $filter_options['chambers'];
			$chamber_labels = fi_chamber_options($gov);
			
			// Parse current filters from URL/request
			$current_filters = fi_parse_filters($gov);
			
			// Set current values from parsed filters
			//SESSIONSLUG: Change to $current_filters['session_id'] and validate as int, remove slug variables
			$current_session_id = !empty($current_filters['session_id']) ? (int) $current_filters['session_id'] : null;
			$current_party_slug = $current_filters['party_slug'] ?? '';
			$current_chamber = $current_filters['chamber'] ?? '';
			$current_search = $current_filters['search'] ?? '';
			$current_state = $current_filters['state'] ?? '';
			$current_sort = $current_filters['sort'] ?? 'na';
			
			//SESSIONSLUG: Remove slug-to-ID conversion, use session_id directly from filters
			// If no session selected, use current session
			//SESSIONSLUG: Remove $current_session_slug assignments, only set $current_session_id
			if (!$current_session_id) {
				$current_session_obj = fi_session_get_current($gov);
				if ($current_session_obj) {
					$current_session_id = $current_session_obj->id;
				} elseif (!empty($sessions)) {
					$current_session_id = $sessions[0]->id;
				}
			}
			
			// Get current page to determine behavior
			global $fi_entity;
			$current_entity = get_query_var('fi_entity') ?: ($fi_entity ?? '');
			$is_legislators_page = ($current_entity === 'legislators');

			$classFormLabel = $args['form_label_class'];			
			// Build form
			ob_start();

if (strtoupper($gov) === 'US'){
	$col_name = '1';
}else{
	$col_name = '3';
}
			?>
			<form id="<?php echo esc_attr($args['form_id']); ?>" class="w-100" method="get" action="<?php echo esc_url(home_url('/' . strtolower($gov) . '/legislators/')); ?>" data-gov="<?php echo esc_attr(strtolower($gov)); ?>">
				<div class="row">
					<!-- Search by Name -->
					<div class="col-6 col-md-4 col-lg-<?php echo $col_name; ?> px-md-1 py-1 py-lg-0">
						<div class="form-group">
							<?php
							// Summary: US legislators page should read "Congressional" while breadcrumbs/nav use "Congress".
							$gov_name_adj = (strtoupper((string) $gov) === 'US') ? 'Congressional' : $gov_name;
							?>
							<!-- <label for="fi-search" class="form-label <?= $classFormLabel; ?>"><span class="d-none d-lg-inline"><?= $gov_name_adj ?> Legislators</span></label> -->
							<input type="text" id="fi-search" name="search" class="form-control form-control-sm" value="<?php echo esc_attr($current_search); ?>" placeholder="Search Name" />
						</div>
					</div>

					<?php if (strtoupper($gov) === 'US'): ?>
					<!-- State Filter (US Congress only) -->
					<div class="col-6 col-md-4 col-lg-2 px-md-1 py-1 py-lg-0">
						<div class="form-group">
<!--						<label for="fi-state" class="<?= $classFormLabel; ?>">State</label> -->
							<select id="fi-state" name="state" class="form-select form-select-sm">
								<option value="">All States</option>
								<?php 
								$states = fi_state_options();
								foreach ($states as $state_code => $state_name){
									echo '<option value="' . esc_attr(strtolower($state_code)) . '" '. selected(strtolower($current_state), strtolower($state_code)).'>'.esc_html($state_name) . '</option>';
								 } ?>
							</select>
						</div>
					</div>
					<?php endif; ?>

					<!-- Session -->
					<div class="col-6 col-md-4 col-lg-2 px-md-1 py-1 py-lg-0">
						<div class="form-group">
							<!-- <label for="fi-session" class="<?= $classFormLabel; ?>">Session</label> -->
							<select id="fi-session" name="session" class="form-select form-select-sm"><!-- required --> <!-- commented to allow "All Sessions" for past-legislator search -->
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
					<!-- Chamber -->
					<div class="col-6 col-md-4 col-lg-2 px-md-1 py-1 py-lg-0">
						<div class="form-group">
							<!-- <label for="fi-chamber" class="<?= $classFormLabel; ?>">Chamber</label> -->
							<select id="fi-chamber" name="chamber" class="form-select form-select-sm">
								<option value="">All Chambers</option>
								<?php foreach ($chambers as $chamber) : ?>
									<?php if (!empty($chamber_labels[$chamber]['chamber'])) : ?>
										<option value="<?php echo esc_attr($chamber); ?>" 
												<?php selected($current_chamber, $chamber); ?>>
											<?php echo esc_html($chamber_labels[$chamber]['chamber']); ?>
										</option>
									<?php endif; ?>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<?php endif; ?>
					
					<!-- Party -->
					<div class="col-6 col-md-4 col-lg-2 px-md-1 py-1 py-lg-0">
						<div class="form-group">
							<!-- <label for="fi-party" class="<?= $classFormLabel; ?>">Party</label> -->
							<select id="fi-party" name="party" class="form-select form-select-sm">
								<option value="">All Parties</option>
								<?php foreach ($parties as $party): ?>
									<option value="<?php echo esc_attr($party->slug); ?>" 
											<?php selected($current_party_slug, $party->slug); ?>>
										<?php echo esc_html($party->name); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<?php //if (strtoupper($gov) !== 'US' && $is_legislators_page): ?>
					<?php //if ($is_legislators_page): ?>
					<!-- Sort by Filter (States only) -->
					<div class="col-6 col-md-4 col-lg-2 px-md-1 py-1 py-lg-0">
						<div class="form-group">
							<!-- <label for="fi-sort" class="<?= $classFormLabel; ?>">Sort by</label> -->
							<select id="fi-sort" name="sort" class="form-select form-select-sm">
								<?php 
								$sorts = fi_legislators_sort_options();
								// Set default sort value
								$default_sort = 'na';
								$selected_sort = strtolower($current_sort ?? $default_sort);
								foreach ($sorts as $sort_code => $sort_name): 
									$option_value = strtolower($sort_code);
								?>
									<option value="<?php echo esc_attr($option_value); ?>" 
											<?php selected($selected_sort, $option_value); ?>>
										<?php echo esc_html($sort_name); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<?php //endif; ?>


					<!-- Submit Button -->
					<div class="col-6 col-md-4 col-lg-1 px-md-1 py-1 py-lg-0">
						<div class="form-group">
							<!-- <label for="fi-submit" class="<?= $classFormLabel; ?>">&nbsp;</label> -->
							<button type="submit" id="fi-submit" class="form-control form-control-sm btn btn-outline-sucess fi-filter-submit">
								Search
							</button>
						</div>
					</div>
				</div>
				
				<!-- Hidden fields for AJAX -->
				<input type="hidden" name="gov" value="<?php echo esc_attr($gov); ?>">
				<input type="hidden" name="action" value="fi_api_legislator_filter">
				<input type="hidden" name="nonce" value="<?php echo wp_create_nonce('fi_ajax_nonce'); ?>">
			</form>
			<script>
			jQuery(document).ready(function($) {
				var filterForm = $('#<?php echo esc_js($args['form_id']); ?>');
				var isLegislatorsPage = <?php echo $is_legislators_page ? 'true' : 'false'; ?>;
				var debounceTimer;
				var isSubmitting = false;
				var initialFormState = null; // Store initial form state to prevent double load
				var pageLoaded = false; // Track if page has finished initial load
				
				<?php if ($is_legislators_page): ?>
				// AJAX filtering for legislators page
				function buildFilterUrl() {
					var gov = filterForm.data('gov') || '<?php echo esc_js(strtolower($gov)); ?>';
					var filters = {};
					
					var chamber = $('#fi-chamber').val();
					var partySlug = $('#fi-party').val();
					var search = $('#fi-search').val();
					var state = $('#fi-state').val();
					var sort = $('#fi-sort').val();
					
					// Session is always required - ensure it's set (commented out below to allow "All Sessions")
					//SESSIONSLUG: Change variable name from sessionSlug to sessionId, update filters to use session_id instead of session_slug
					var sessionId = $('#fi-session').val();
					// if (!sessionId) {
					// 	// Fallback to first option if somehow empty
					// 	sessionId = $('#fi-session option:first').val();
					// 	$('#fi-session').val(sessionId);
					// }
					// filters.session_id = parseInt(sessionId);
					if (sessionId) {
						filters.session_id = parseInt(sessionId);
					}
					
					if (chamber) filters.chamber = chamber;
					if (partySlug) filters.party_slug = partySlug;
					if (search) filters.search = search;
					if (state && gov === 'us') filters.state = state;
					
					// Build pretty URL - session segment only when a session is selected (omit for "All Sessions")
					var url = '<?php echo esc_js(home_url('/')); ?>' + gov + '/legislators/';
					var segments = [];
					
					if (state && gov === 'us') {
						segments.push('state/' + state);
					}
					// Session is always included (commented out to allow "All Sessions"): segments.push('session/' + sessionId);
					if (sessionId) {
						segments.push('session/' + sessionId);
					}
					if (chamber) {
						segments.push('chamber/' + chamber);
					}
					if (partySlug) {
						segments.push('party/' + partySlug);
					}
					if (search) {
						segments.push('search/' + encodeURIComponent(search));
					}
					if (sort && sort !== 'na') {
						// Only include sort in URL if it's not the default
						segments.push('sort/' + sort);
					}
					
					if (segments.length > 0) {
						url += segments.join('/') + '/';
					}
					
					return url;
				}
				
				// Get current form state as a string for comparison
				function getFormState() {
					return $('#fi-session').val() + '|' + 
					       $('#fi-chamber').val() + '|' + 
					       $('#fi-party').val() + '|' + 
					       $('#fi-search').val() + '|' + 
					       $('#fi-state').val() + '|' +
					       ($('#fi-sort').length ? $('#fi-sort').val() : '');
				}
				
				// Check if form state matches current URL
				function formMatchesUrl() {
					var currentUrl = window.location.pathname + window.location.search;
					var formUrl = buildFilterUrl();
					// Normalize URLs for comparison (remove trailing slashes, etc.)
					currentUrl = currentUrl.replace(/\/$/, '');
					formUrl = formUrl.replace(/\/$/, '');
					return currentUrl === formUrl || currentUrl + '/' === formUrl || currentUrl === formUrl + '/';
				}
				
				function submitFilters(skipUrlCheck) {
					if (isSubmitting) return;
					
					// Skip if form state matches current URL (prevents double load on initial page load)
					if (!skipUrlCheck && formMatchesUrl()) {
						return;
					}
					
					isSubmitting = true;
					
					var formData = filterForm.serialize();
					var resultsContainer = $('#fi-legislators-results');
					var prettyUrl = buildFilterUrl();
					
					// Ensure resultsContainer is a valid jQuery object (never undefined)
					if (typeof resultsContainer === 'undefined' || !resultsContainer || !resultsContainer.length) {
						// Fallback: try to find container
						resultsContainer = $('.fi-legislators-list .row.g-4').parent();
						if (typeof resultsContainer === 'undefined' || !resultsContainer || !resultsContainer.length) {
							resultsContainer = $('#fi-legislators-results');
						}
						// If still undefined, create empty jQuery object
						if (typeof resultsContainer === 'undefined') {
							resultsContainer = $();
						}
					}
					
					// Show loading state
					if (resultsContainer && resultsContainer.length) {
						// Show loading spinner if not already showing
						if (!resultsContainer.find('#fi-legislators-loading').length) {
							resultsContainer.html('<div class="row" id="fi-legislators-loading"><div class="col-12"><div class="text-center py-5"><div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"><span class="visually-hidden">Loading...</span></div><p class="mt-3 mb-0 text-muted">Loading legislators...</p></div></div></div>');
						}
					}
					
					$.ajax({
						url: '<?php echo admin_url('admin-ajax.php'); ?>',
						type: 'POST',
						data: formData,
						success: function(response) {
							if (response.success) {
								// Update results
								if (resultsContainer && resultsContainer.length) {
									resultsContainer.html(response.data.html);
								} else {
									// Fallback: replace entire grid
									$('.fi-legislators-list .row.g-4').html(response.data.html);
								}
							//Add execution time to console
							console.log('Execution time: ' + response.data.execution_time + ' seconds');


							// Update reports navigation using pre-generated HTML from PHP
							if (response.data.reports_nav_html) {
								var reportsNavRow = $('#fi-reports-nav').closest('.row');
								var navHtml = response.data.reports_nav_html;
								
								if (reportsNavRow.length) {
									// Replace existing reports nav row
									reportsNavRow.replaceWith(navHtml);
								} else {
									// Reports nav doesn't exist yet, insert it before the results
									if (resultsContainer && resultsContainer.length) {
										resultsContainer.before(navHtml);
									} else {
										// Fallback: try to find container
										var container = $('.fi-legislators-list .container-xl.p-3, .fi-legislators-list .container-xl.p-lg-4, .fi-legislators-list .container-xl.p-lg-5');
										if (container.length) {
											container.find('.row').first().before(navHtml);
										}
									}
								}
							} else {
								// No reports for this session - hide reports nav
								$('#fi-reports-nav').closest('.row').remove();
							}
							
							// Update URL without reload (use pretty URL)
							window.history.pushState({}, '', prettyUrl);
							
							// Update results count
							var count = response.data.count || 0;
							var countContainer = $('#fi-results-count-container');
							if (count > 0) {
								$('#fi-results-count').text(count);
								$('#fi-results-plural').text(count !== 1 ? 's' : '');
								countContainer.show();
							} else {
								countContainer.hide();
							}
							
							// Update filter description
							if (response.data.filter_description !== undefined) {
								var descriptionText = response.data.filter_description;
								/*if (count > 0) {
									descriptionText += ' | ' + count + '<span class="d-none d-lg-inline"> legislator' + (count !== 1 ? 's' : '') + '</span> found';
								}*/
								
								// Add PDF print link only when filtered count <= 100 (dompdf times out on large sets)
								if (count <= 240) {
									var pdfUrl = prettyUrl + 'pdf/cards';
									descriptionText += ' <a href="' + pdfUrl + '" target="_blank" class="ms-3" style="color:#0055a4 !important;"><i class="bi bi-file-pdf"></i> Print</a>';
								}
								
								// Update description in header using ID selector
								var descriptionElement = $('#fi-header-description');
								if (descriptionElement.length) {
									descriptionElement.html(descriptionText);
								}
								// Also update placeholder if it exists
								var placeholderElement = $('#fi-results-count-placeholder');
								if (placeholderElement.length && count > 0) {
									placeholderElement.text(count + ' legislator' + (count !== 1 ? 's' : '') + ' found');
								}
							}
							
							// Update initial form state after successful submit
							initialFormState = getFormState();
							}
						},
						complete: function() {
							isSubmitting = false;
							if (resultsContainer && resultsContainer.length) {
								resultsContainer.removeClass('opacity-50');
							}
						}
					});
				}
				
				// Store initial form state on page load AFTER a short delay
				// This ensures form is fully populated and URL is stable
				setTimeout(function() {
					initialFormState = getFormState();
					pageLoaded = true;
					
					// Trigger initial AJAX load with default form values (since we don't load server-side)
					// This loads legislators once on page load with the default session
					var sessionValue = $('#fi-session').val();
					if (sessionValue) {
						submitFilters(true); // true = skip URL check for initial load
					}
				}, 100);
				
				// Debounced change handler - only trigger if form state actually changed
				filterForm.find('select, input[type="text"]').on('change input', function() {
					// Don't trigger on initial page load (before pageLoaded flag is set)
					if (!pageLoaded) {
						return;
					}
					
					var currentState = getFormState();
					// Only submit if form state changed from initial state
					if (currentState !== initialFormState) {
						clearTimeout(debounceTimer);
						debounceTimer = setTimeout(function() {
							submitFilters(false); // false = check URL match
						}, 500); // 500ms debounce
					}
				});
				
				// Share button
/*
				$('#fi-share').on('click', function() {
					var url = window.location.href;
					
					// Copy to clipboard
					if (navigator.clipboard) {
						navigator.clipboard.writeText(url).then(function() {
							$(this).html('<i class="fas fa-check"></i> Copied!');
							var btn = $(this);
							setTimeout(function() {
								btn.html('<i class="fas fa-share-alt"></i><span class="d-none d-md-inline"> Share</span>');
							}, 2000);
						}.bind(this));
					} else {
						// Fallback: select text
						var tempInput = $('<input>').val(url).appendTo('body').select();
						document.execCommand('copy');
						tempInput.remove();
						$(this).html('<i class="fas fa-check"></i> Copied!');
						var btn = $(this);
						setTimeout(function() {
							btn.html('<i class="fas fa-share-alt"></i><span class="d-none d-md-inline"> Share</span>');
						}, 2000);
					}
				});
*/
				<?php else: ?>
				// Regular form submission for non-legislators pages - build pretty URL
				function buildFilterUrl() {
					var gov = filterForm.data('gov') || '<?php echo esc_js(strtolower($gov)); ?>';
					var segments = [];
					
					var state = $('#fi-state').val();
					var sessionId = $('#fi-session').val();
					var chamber = $('#fi-chamber').val();
					var partySlug = $('#fi-party').val();
					var search = $('#fi-search').val();
					
					// Session is always required - ensure it's set (commented out below to allow "All Sessions")
					// if (!sessionId) {
					// 	// Fallback to first option if somehow empty
					// 	sessionId = $('#fi-session option:first').val();
					// 	$('#fi-session').val(sessionId);
					// }
					
					if (state && gov === 'us') {
						segments.push('state/' + state);
					}
					// Session is always included (commented out to allow "All Sessions"): segments.push('session/' + sessionId);
					if (sessionId) {
						segments.push('session/' + sessionId);
					}
					if (chamber) {
						segments.push('chamber/' + chamber);
					}
					if (partySlug) {
						segments.push('party/' + partySlug);
					}
					if (search) {
						segments.push('search/' + encodeURIComponent(search));
					}
					
					var url = '<?php echo esc_js(home_url('/')); ?>' + gov + '/legislators/';
					if (segments.length > 0) {
						url += segments.join('/') + '/';
					}
					
					return url;
				}
				
				filterForm.on('submit', function(e) {
					e.preventDefault();
					var prettyUrl = buildFilterUrl();
					window.location.href = prettyUrl;
				});
				<?php endif; ?>
			});
			</script>
			<?php
			return ob_get_clean();
		}

		public static function log($message, $file, $line, $level = 'debug') {
			fi_log_area('filters', (string) $message, (string) $file, (int) $line, (string) $level);
		}
	}
}

// Global namespace for public helper functions
namespace {
	
	/**
	 * Render legislator filter bar
	 * 
	 * @param array $args Filter arguments (gov, session, party, chamber, search, etc.)
	 * @return string HTML filter form
	 */
	function fi_legislator_filters($args = array()) {
		return \FI\Public\LegislatorFilters::render($args);
	}
	
	/**
	 * Get cached filter options for a government
	 * 
	 * @param string $gov Government code
	 * @param bool $force_refresh When true, rebuilds the cached options for this gov
	 * @return array Array with sessions, parties, and chambers
	 */
	function fi_filter_get_options(string $gov, bool $force_refresh = false): array {
		return \FI\Public\LegislatorFilters::get_filter_options($gov, $force_refresh);
	}

	/**
	 * Clear the filter-options transient for a government.
	 * Use when staff changes sessions/setup so the next front load rebuilds with correct (e.g. top-level only) sessions.
	 *
	 * @param string $gov Government code
	 */
	function fi_filter_clear_cache(string $gov): void {
		delete_transient('fi_filter_options_' . strtoupper($gov));
	}
	
	/**
	 * Get distinct parties for a government
	 * 
	 * @param string $gov Government code
	 * @return array Array of distinct party values
	 */
	function fi_party_get_distinct(string $gov): array {
		return \FI\Public\LegislatorFilters::get_distinct_parties($gov);
	}
	
	/**
	 * Get distinct chambers for a government
	 * 
	 * @param string $gov Government code
	 * @return array Array of chamber codes (H, S)
	 */
	function fi_chamber_get_distinct(string $gov): array {
		return \FI\Public\LegislatorFilters::get_distinct_chambers($gov);
	}
	
	/**
	 * Build filter description string from active filters
	 * 
	 * @param array $args Filter arguments (gov, session, party, chamber, state)
	 * @return string Description string
	 */
	function fi_filter_build_description(array $args): string {
		return \FI\Public\LegislatorFilters::build_filter_description($args);
	}
}
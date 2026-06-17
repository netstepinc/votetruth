<?php
namespace FI\Public{

	if (!defined('ABSPATH')) exit;

	/**
	* URL Routing for Freedom Index Public Front-End
	* 
	* Handles rewrite rules for clean URLs.
	* Legislator URLs are stable without gov prefix: /legislator/{slug}/
	* Government-specific routes use /[gov]/ prefix: /[gov]/legislators, /[gov]/reports, etc.
	* 
	* URL Structure:
	* ----------------------------
	* Legislator Routes (stable URLs, no gov prefix):
	*   /legislator/[id]            -> legislator.php (single legislator - gov determined from current session)
	*   /[id]                       -> 301 redirect to /legislator/[id]/ (short URL: numeric ID only; no /l/ to avoid l/I confusion)
	*   /legislator/[id]/report/[slug] -> legislator.php (legislator's specific report)
	* 
	* Government Routes (require [gov] two-letter code: us, tx, wi, etc.):
	*   /[gov]/                    -> legislators.php (default to most recent main session)
	*   /[gov]/legislators          -> legislators.php (legislator list with filters)
	*   /[gov]/reports              -> reports.php (reports list)
	*   /[gov]/report/[slug]        -> report.php (single report)
	*   /[gov]/votes                -> votes.php (votes list) by tag or chamber /issue/{tag_slug} | /chamber/{chamber_slug}
	*   /[gov]/vote/[id]            -> vote.php (single vote)
	* 
	* Account/login pages are handled via WordPress pages + shortcodes (no rewrites).
	*
	* Legacy Support (V1/V2 compatibility - handled separately at 404):
	*   /{gov}/legislator/[slug]    -> redirects to /legislator/[slug] (handled at 404)
	*   /freedom-index/legislator/[slug] -> redirects to /legislator/[slug] (handled at 404)
	* 
	* Template Loading Priority:
	* 1. Theme override: get_template_directory() . "/freedom-index/{template}.php"
	* 2. Plugin template: public/templates/{template}.php
	* 
	* @author Sam Mittelstaedt <smittelstaedt@jbs.org>
	*/
	final class Rewrite {
		
		public function __construct() {
			self::log('Rewrite class instantiated');
			add_action('init', [$this, 'add_rewrite_rules']);
			add_filter('query_vars', [$this, 'add_query_vars']);
			add_action('template_redirect', [$this, 'handle_rewrite_requests'], 1);
			//add_action('pre_get_posts', [$this, 'prevent_fi_search_post_query'], 1);

			// Prevent redirect_canonical from redirecting legislators URLs; it can build a URL that
			// omits custom query vars (e.g. sort) via _remove_qs_args_if_not_in_url(), so form submissions
			// from other pages lose sort when a redirect runs before parse_filters() is used.
			add_filter('redirect_canonical', [$this, 'prevent_legislators_canonical_redirect'], 10, 2);
		}
		
		/**
		* Get legislator URL (public static method for URL generation)
		* Returns stable URL without gov prefix: /legislator/{id}/
		*/
		public static function get_legislator_url(int $legislator_id, ?string $gov = null): string {
			// Gov parameter is ignored - legislator URLs are stable and don't include gov
			return home_url('/legislator/' . $legislator_id . '/');
		}

		/**
		* Get legislator short URL: /{id}/ (numeric only; redirects to /legislator/{id}/)
		*/
		public static function get_legislator_short_url(int $legislator_id): string {
			return home_url('/' . $legislator_id . '/');
		}
		
		/**
		* Get report URL (public static method for URL generation)
		* 
		* @param int $report_id Report ID
		* @param string|null $gov Optional government code
		* @param array $args Optional arguments:
		*   - 'chamber' => 'H' or 'S' to include chamber in URL
		*   - 'compare' => true to include /compare/ segment
		*   - 'state' => state code (added as query arg)
		* @return string Report URL
		*/
		public static function url_report(int $report_id, ?string $gov = null, array $args = []): string {
			if (!$gov) {
				$gov = 'us'; // Default fallback if caller omitted gov
			}
			
			// Get chamber parameter
			$chamber = null;
			if (!empty($args['chamber'])) {
				$chamber = strtoupper($args['chamber']);
			}
			
			// Build URL with chamber if provided
			if ($chamber && ($chamber === 'H' || $chamber === 'S')) {
				$base_url = home_url('/' . ($gov ?: 'us') . '/report/' . $report_id . '/chamber/' . $chamber . '/');
			} else {
				$base_url = home_url('/' . ($gov ?: 'us') . '/report/' . $report_id . '/');
			}
			
			// Add compare segment if requested
			if (!empty($args['compare'])) {
				if ($chamber) {
					$base_url = home_url('/' . ($gov ?: 'us') . '/report/' . $report_id . '/chamber/' . $chamber . '/compare/');
				} else {
					$base_url = home_url('/' . ($gov ?: 'us') . '/report/' . $report_id . '/compare/');
				}
			}
			
			// Add query args for state if provided
			if (!empty($args['state'])) {
				$base_url = add_query_arg('state', strtolower($args['state']), $base_url);
			}		
			return $base_url;
		}
		
		/**
		* Get list URL (public static method for URL generation)
		*/
		/** Account list URL: /account/lists/{id}/ */
		public static function url_list(int $user_id, int $list_id): string {
			return home_url('/account/lists/' . $list_id . '/');
		}

		/**
		* Get vote URL (public static method for URL generation)
		*/
		public static function url_vote(string $gov, int $vote_id): string {
			return home_url('/' . strtolower($gov) . '/vote/' . $vote_id . '/');
		}


		/**
		* Build pretty URL for legislator filters
		* 
		* @param string $gov Government code
		* @param array $filters {
		*     @type string|null $session_id Session ID
		*     @type string|null $chamber Chamber code (S or H)
		*     @type string|null $party_slug Party slug
		*     @type string|null $state State code (for Congress only)
		*     @type string|null $search Search term
		* }
		* @return string Pretty URL
		*/
		public static function build_filter_url(string $gov, array $filters = []): string {
			$gov = strtolower($gov);
			$base_url = home_url('/' . $gov . '/legislators/');
			
			$segments = [];
			
			// Congress government: add state filter first if present
			if (strtoupper($gov) === 'US' && !empty($filters['state'])) {
				$segments[] = 'state/' . strtolower($filters['state']);
			}
			
			// Add filters in order: session/chamber/party/search
			//SESSIONSLUG: Change $filters['session_slug'] to $filters['session_id'] and use numeric ID in URL path instead of slug
			if (!empty($filters['session_id'])) {
				$segments[] = 'session/' . (int) $filters['session_id'];
			}
			
			if (!empty($filters['chamber'])) {
				$segments[] = 'chamber/' . strtoupper($filters['chamber']);
			}
			
			if (!empty($filters['party_slug'])) {
				$segments[] = 'party/' . $filters['party_slug'];
			}
			
			if (!empty($filters['search'])) {
				// URL encode search term
				$search_term = urlencode($filters['search']);
				$segments[] = 'search/' . $search_term;
			}
			if (!empty($filters['sort']) && $filters['sort'] !== 'na') {
				// Only include sort if it's not the default
				$segments[] = 'sort/' . $filters['sort'];
			}
			
			// Build final URL
			if (!empty($segments)) {
				$base_url .= implode('/', $segments) . '/';
			}
			
			return $base_url;
		}

		/**
		* Parse filter values from current request/query vars
		* Returns filters as slugs (for URL building)
		* 
		* @param string $gov Government code
		* @return array Filter values with slugs
		*/
		public static function parse_filters(string $gov): array {
			$filters = [];
			
			// Check for pretty URL query vars first
			//SESSIONSLUG: Change to get_query_var('fi_session_id') and treat as numeric ID, remove slug conversion logic below
			$session_id = get_query_var('fi_session_id') ?: '';
			$party_slug = get_query_var('fi_party_slug') ?: '';
			$chamber = get_query_var('fi_chamber') ?: '';
			$state = get_query_var('fi_state') ?: '';
			$search = get_query_var('fi_search') ?: '';
			$sort = get_query_var('fi_sort') ?: '';
			
			// Fallback to standard query vars (form submits session, party, chamber, search, sort)
			if (empty($session_id)) {
				$session_id = get_query_var('session') ?: '';
			}
			if (empty($sort)) {
				$sort = get_query_var('sort') ?: '';
			}
			if (empty($party_slug)) {
				$party_slug = get_query_var('party') ?: '';
			}
			if (empty($chamber)) {
				$chamber = get_query_var('chamber') ?: '';
			}
			if (empty($search)) {
				$search = urldecode(get_query_var('search') ?: '');
			}
			
			// Also check $_REQUEST for form submissions
			//SESSIONSLUG: Change $_REQUEST['session'] to expect numeric ID, validate as int, remove slug conversion
			if (empty($session_id) && !empty($_REQUEST['session'])) {
				$session_id_raw = sanitize_text_field($_REQUEST['session']);
				if (is_numeric($session_id_raw)) {
					$session_id = (int) $session_id_raw;
				}
			}
			if (empty($party_slug) && !empty($_REQUEST['party'])) {
				$party_slug = sanitize_text_field($_REQUEST['party']);
			}
			if (empty($chamber) && !empty($_REQUEST['chamber'])) {
				$chamber = sanitize_text_field($_REQUEST['chamber']);
			}
			if (empty($search) && !empty($_REQUEST['search'])) {
				$search = sanitize_text_field($_REQUEST['search']);
			}
			if (empty($sort) && !empty($_REQUEST['sort'])) {
				$sort = sanitize_text_field($_REQUEST['sort']);
			}
			
			// Validate sort format (e.g., "na", "nd", "sa", "sd", "pa", "pd", "oa", "od")
			if (!empty($sort)) {
				$sort = strtolower(sanitize_key($sort));
				$valid_sorts = ['na', 'nd', 'sa', 'sd', 'pa', 'pd', 'oa', 'od'];
				if (!in_array($sort, $valid_sorts, true)) {
					$sort = ''; // Invalid format, clear it
				}
			}
			
			//SESSIONSLUG: Remove this entire block - no longer convert ID to slug, just validate ID is numeric and use directly
			// Validate session_id is numeric
			if (!empty($session_id) && !is_numeric($session_id)) {
				$session_id = '';
			} elseif (!empty($session_id)) {
				$session_id = (int) $session_id;
			}
			
			// Convert party name to slug if needed
			if (!empty($party_slug) && fi_party_validate(strtolower($party_slug))) {
				// Already a slug, keep it
			} elseif (!empty($party_slug)) {
				// Might be a party name, try to find slug
				$options = fi_filter_get_options($gov);
				foreach ($options['parties'] as $party) {
					if (strtolower($party->name) === strtolower($party_slug)) {
						$party_slug = $party->slug;
						break;
					}
				}
			}
			
			//SESSIONSLUG: Change to $filters['session_id'] = (int)$session_id (after validating it's numeric)
			if (!empty($session_id)) {
				$filters['session_id'] = (int) $session_id;
			}
			if (!empty($chamber)) {
				$filters['chamber'] = strtoupper($chamber);
			}
			if (!empty($party_slug)) {
				$filters['party_slug'] = $party_slug;
			}
			if (!empty($state)) {
				$filters['state'] = strtoupper($state);
			}
			if (!empty($search)) {
				$filters['search'] = $search;
			}
			if (!empty($sort) && $sort !== 'na') {
				// Only include sort if it's not the default
				$filters['sort'] = $sort;
			}
//fi_log('FILTERS: ' . json_encode($filters), __FILE__, __LINE__, 'debug');			
			return $filters;
		}

		/**
		* Convert parsed filters (slugs) to query args (IDs/names) for database queries
		* 
		* @param string $gov Government code
		* @param array $parsed_filters Filters from parse_filters() (with slugs)
		* @param int|null $default_session_id Default session ID if no session filter
		* @return array Query args for fi_legislators()
		*/
		public static function filters_to_query_args(string $gov, array $parsed_filters, ?int $default_session_id = null): array {
			$query_args = [
				'gov' => $gov,
			];
			
			//SESSIONSLUG: Change to use $parsed_filters['session_id'] directly, validate it's numeric and exists, remove fi_session_get_id_from_slug() call
			// Use session_id directly from parsed filters
			$session_id = null;
			if (!empty($parsed_filters['session_id'])) {
				$session_id_raw = $parsed_filters['session_id'];
				if (is_numeric($session_id_raw)) {
					$session_id = (int) $session_id_raw;
					// Validate session exists and matches gov
					$session_obj = fi_session_get($session_id);
					if (!$session_obj || $session_obj->gov !== strtoupper($gov)) {
						$session_id = null;
					}
				}
			}
			
			// Use filter session if provided, otherwise use default
			if (!$session_id && $default_session_id) {
				$session_id = $default_session_id;
			}
			
			if ($session_id) {
				$query_args['session'] = $session_id;
			}
			
		// Party is now stored as abbreviation
		if (!empty($parsed_filters['party_slug'])) {
			$slug_lower = strtolower($parsed_filters['party_slug']);
			if (fi_party_validate($slug_lower)) {
				$query_args['party'] = strtoupper($slug_lower);
			}
		}
			
			// Add chamber, state, search as-is
			if (!empty($parsed_filters['chamber'])) {
				$query_args['chamber'] = strtoupper($parsed_filters['chamber']);
			}
			
			if (!empty($parsed_filters['state'])) {
				$query_args['state'] = strtoupper($parsed_filters['state']);
			}
			
			if (!empty($parsed_filters['search'])) {
				$query_args['search'] = $parsed_filters['search'];
			}
			
			// Parse sort parameter (format: "na", "nd", "sa", "sd", "pa", "pd", "oa", "od")
			if (!empty($parsed_filters['sort'])) {
				$sort = strtolower(sanitize_key($parsed_filters['sort']));
				
				// Map 2-character codes to orderby/order
				$sort_map = [
					'na' => ['orderby' => 'last_name', 'order' => 'ASC'],
					'nd' => ['orderby' => 'last_name', 'order' => 'DESC'],
					'sa' => ['orderby' => 'score', 'order' => 'ASC'],
					'sd' => ['orderby' => 'score', 'order' => 'DESC'],
					'pa' => ['orderby' => 'party', 'order' => 'ASC'],
					'pd' => ['orderby' => 'party', 'order' => 'DESC'],
					'oa' => ['orderby' => 'chamber', 'order' => 'ASC'],
					'od' => ['orderby' => 'chamber', 'order' => 'DESC'],
				];
				
				if (isset($sort_map[$sort])) {
					$query_args['orderby'] = $sort_map[$sort]['orderby'];
					$query_args['order'] = $sort_map[$sort]['order'];
				}
			}
			
			return $query_args;
		}
		
		/**
		* Add rewrite rules
		*/
		public function add_rewrite_rules(): void {
			// Account: single list manage URL — /account/lists/{id}/
			add_rewrite_rule(
				'^account/lists/([0-9]+)/?$',
				'index.php?pagename=account/lists&fi_list_id=$matches[1]',
				'top'
			);

			// Government landing page (government dashboard)
			add_rewrite_rule(
				'^([a-z]{2})/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=government',
				'top'
			);
			
			// Legislators PDF routes - must come before regular legislators routes (more specific first)
			// Format: /pdf/{format}/ where format can be 'list', 'summary', 'detailed', etc.
			// US Congress PDF routes with all filters
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/search/(.+?)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_party_slug=$matches[4]&fi_search=$matches[5]&fi_pdf=$matches[6]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_party_slug=$matches[4]&fi_pdf=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/search/(.+?)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_chamber=$matches[2]&fi_party_slug=$matches[3]&fi_search=$matches[4]&fi_pdf=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_chamber=$matches[2]&fi_party_slug=$matches[3]&fi_pdf=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/chamber/(H|S)/search/(.+?)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_chamber=$matches[2]&fi_search=$matches[3]&fi_pdf=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/chamber/(H|S)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_chamber=$matches[2]&fi_pdf=$matches[3]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/search/(.+?)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_search=$matches[2]&fi_pdf=$matches[3]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_pdf=$matches[2]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/chamber/(H|S)/search/(.+?)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_search=$matches[4]&fi_pdf=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/chamber/(H|S)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_pdf=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/search/(.+?)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_search=$matches[3]&fi_pdf=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_pdf=$matches[3]',
				'top'
			);

			// US Congress PDF routes with sort (optional sort before /pdf/)
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/search/(.+?)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_party_slug=$matches[4]&fi_search=$matches[5]&fi_sort=$matches[6]&fi_pdf=$matches[7]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_party_slug=$matches[4]&fi_sort=$matches[5]&fi_pdf=$matches[6]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/search/(.+?)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_chamber=$matches[2]&fi_party_slug=$matches[3]&fi_search=$matches[4]&fi_sort=$matches[5]&fi_pdf=$matches[6]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_chamber=$matches[2]&fi_party_slug=$matches[3]&fi_sort=$matches[4]&fi_pdf=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/chamber/(H|S)/search/(.+?)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_chamber=$matches[2]&fi_search=$matches[3]&fi_sort=$matches[4]&fi_pdf=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/chamber/(H|S)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_chamber=$matches[2]&fi_sort=$matches[3]&fi_pdf=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/search/(.+?)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_search=$matches[2]&fi_sort=$matches[3]&fi_pdf=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_sort=$matches[2]&fi_pdf=$matches[3]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/chamber/(H|S)/search/(.+?)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_search=$matches[4]&fi_sort=$matches[5]&fi_pdf=$matches[6]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/chamber/(H|S)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_sort=$matches[4]&fi_pdf=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/search/(.+?)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_search=$matches[3]&fi_sort=$matches[4]&fi_pdf=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_sort=$matches[3]&fi_pdf=$matches[4]',
				'top'
			);


			// US Congress routes with sort - must come before regular routes (more specific first)
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/search/(.+?)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_party_slug=$matches[4]&fi_search=$matches[5]&fi_sort=$matches[6]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_party_slug=$matches[4]&fi_sort=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/search/(.+?)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_chamber=$matches[2]&fi_party_slug=$matches[3]&fi_search=$matches[4]&fi_sort=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_chamber=$matches[2]&fi_party_slug=$matches[3]&fi_sort=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/chamber/(H|S)/search/(.+?)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_chamber=$matches[2]&fi_search=$matches[3]&fi_sort=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/chamber/(H|S)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_chamber=$matches[2]&fi_sort=$matches[3]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/search/(.+?)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_search=$matches[2]&fi_sort=$matches[3]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_sort=$matches[2]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/chamber/(H|S)/search/(.+?)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_search=$matches[4]&fi_sort=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/chamber/(H|S)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_sort=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/search/(.+?)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_search=$matches[3]&fi_sort=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_sort=$matches[3]',
				'top'
			);
			
			// State government PDF routes with all filters
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/search/(.+?)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_party_slug=$matches[4]&fi_search=$matches[5]&fi_pdf=$matches[6]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_party_slug=$matches[4]&fi_pdf=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/chamber/(H|S)/search/(.+?)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_search=$matches[4]&fi_pdf=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/chamber/(H|S)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_pdf=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/party/([a-z0-9-]+)/search/(.+?)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_party_slug=$matches[3]&fi_search=$matches[4]&fi_pdf=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/party/([a-z0-9-]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_party_slug=$matches[3]&fi_pdf=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/search/(.+?)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_search=$matches[3]&fi_pdf=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_pdf=$matches[3]',
				'top'
			);

			// State government PDF routes with sort (optional sort before /pdf/)
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/search/(.+?)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_party_slug=$matches[4]&fi_search=$matches[5]&fi_sort=$matches[6]&fi_pdf=$matches[7]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_party_slug=$matches[4]&fi_sort=$matches[5]&fi_pdf=$matches[6]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/chamber/(H|S)/search/(.+?)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_search=$matches[4]&fi_sort=$matches[5]&fi_pdf=$matches[6]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/chamber/(H|S)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_sort=$matches[4]&fi_pdf=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/party/([a-z0-9-]+)/search/(.+?)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_party_slug=$matches[3]&fi_search=$matches[4]&fi_sort=$matches[5]&fi_pdf=$matches[6]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/party/([a-z0-9-]+)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_party_slug=$matches[3]&fi_sort=$matches[4]&fi_pdf=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/search/(.+?)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_search=$matches[3]&fi_sort=$matches[4]&fi_pdf=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/sort/([a-z0-9]+)/pdf/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_sort=$matches[3]&fi_pdf=$matches[4]',
				'top'
			);
			
			// Legislators with sort - must come before regular legislators routes (more specific first)
			// State government routes with sort
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/search/(.+?)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_party_slug=$matches[4]&fi_search=$matches[5]&fi_sort=$matches[6]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_party_slug=$matches[4]&fi_sort=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/chamber/(H|S)/search/(.+?)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_search=$matches[4]&fi_sort=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/chamber/(H|S)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_sort=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/party/([a-z0-9-]+)/search/(.+?)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_party_slug=$matches[3]&fi_search=$matches[4]&fi_sort=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/party/([a-z0-9-]+)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_party_slug=$matches[3]&fi_sort=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/search/(.+?)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_search=$matches[3]&fi_sort=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/sort/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_sort=$matches[3]',
				'top'
			);
			
			// Legislators list (base)
			add_rewrite_rule(
				'^([a-z]{2})/legislators/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators',
				'top'
			);
			
			// Legislators with filters - most complex pattern first (all filters + search)
			// Format: /[gov]/legislators/state/[state]/session/[id]/chamber/[H|S]/party/[slug]/search/[term]
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/search/(.+?)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_party_slug=$matches[4]&fi_search=$matches[5]',
				'top'
			);
			
			// Congress with all filters except search
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_party_slug=$matches[4]',
				'top'
			);
			
			// Congress with session/chamber/party (no state)
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/search/(.+?)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_chamber=$matches[2]&fi_party_slug=$matches[3]&fi_search=$matches[4]',
				'top'
			);
			
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_chamber=$matches[2]&fi_party_slug=$matches[3]',
				'top'
			);
			
			// Congress with session/chamber (no state, no party)
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/chamber/(H|S)/search/(.+?)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_chamber=$matches[2]&fi_search=$matches[3]',
				'top'
			);
			
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/chamber/(H|S)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_chamber=$matches[2]',
				'top'
			);
			
			// Congress with session only (no chamber, no state, no party)
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/search/(.+?)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]&fi_search=$matches[2]',
				'top'
			);
			
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^us/legislators/session/([0-9]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_session_id=$matches[1]',
				'top'
			);
			
			// Congress with state/session/chamber (no party)
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/chamber/(H|S)/search/(.+?)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_search=$matches[4]',
				'top'
			);
			
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/chamber/(H|S)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_chamber=$matches[3]',
				'top'
			);
			
			// Congress with state/session (no chamber)
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/search/(.+?)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]&fi_search=$matches[3]',
				'top'
			);
			
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/session/([0-9]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_session_id=$matches[2]',
				'top'
			);
			
			// Congress with state/party
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/party/([a-z0-9-]+)/search/(.+?)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_party_slug=$matches[2]&fi_search=$matches[3]',
				'top'
			);
			
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/party/([a-z0-9-]+)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_party_slug=$matches[2]',
				'top'
			);
			
			// Congress with state/chamber
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/chamber/(H|S)/search/(.+?)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_chamber=$matches[2]&fi_search=$matches[3]',
				'top'
			);
			
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/chamber/(H|S)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_chamber=$matches[2]',
				'top'
			);
			
			// Congress with state only
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/search/(.+?)/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]&fi_search=$matches[2]',
				'top'
			);
			
			add_rewrite_rule(
				'^us/legislators/state/([a-z]{2})/?$',
				'index.php?fi_gov=us&fi_entity=legislators&fi_state=$matches[1]',
				'top'
			);
			
			// State governments - session/chamber/party/search (most common pattern)
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/search/(.+?)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_party_slug=$matches[4]&fi_search=$matches[5]',
				'top'
			);
			
			// State with session/chamber/party (no search)
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_party_slug=$matches[4]',
				'top'
			);
			
			// State with session/chamber/search
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/chamber/(H|S)/search/(.+?)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_chamber=$matches[3]&fi_search=$matches[4]',
				'top'
			);
			
			// State with session/chamber
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/chamber/(H|S)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_chamber=$matches[3]',
				'top'
			);
			
			// State with session/party/search
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/party/([a-z0-9-]+)/search/(.+?)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_party_slug=$matches[3]&fi_search=$matches[4]',
				'top'
			);
			
			// State with session/party
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/party/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_party_slug=$matches[3]',
				'top'
			);
			
			// State with session/search
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/search/(.+?)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]&fi_search=$matches[3]',
				'top'
			);
			
			// State with session only
			//SESSIONSLUG: Change regex pattern from ([a-z0-9-]+) to ([0-9]+) for session, change query var from fi_session_slug to fi_session_id
			add_rewrite_rule(
				'^([a-z]{2})/legislators/session/([0-9]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_session_id=$matches[2]',
				'top'
			);
			
			// State with chamber/party/search
			add_rewrite_rule(
				'^([a-z]{2})/legislators/chamber/(H|S)/party/([a-z0-9-]+)/search/(.+?)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_chamber=$matches[2]&fi_party_slug=$matches[3]&fi_search=$matches[4]',
				'top'
			);
			
			// State with chamber/party
			add_rewrite_rule(
				'^([a-z]{2})/legislators/chamber/(H|S)/party/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_chamber=$matches[2]&fi_party_slug=$matches[3]',
				'top'
			);
			
			// State with chamber/search
			add_rewrite_rule(
				'^([a-z]{2})/legislators/chamber/(H|S)/search/(.+?)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_chamber=$matches[2]&fi_search=$matches[3]',
				'top'
			);
			
			// State with chamber only
			add_rewrite_rule(
				'^([a-z]{2})/legislators/chamber/(H|S)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_chamber=$matches[2]',
				'top'
			);
			
			// State with party/search
			add_rewrite_rule(
				'^([a-z]{2})/legislators/party/([a-z0-9-]+)/search/(.+?)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_party_slug=$matches[2]&fi_search=$matches[3]',
				'top'
			);
			
			// State with party only
			add_rewrite_rule(
				'^([a-z]{2})/legislators/party/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_party_slug=$matches[2]',
				'top'
			);
			
			// State with search only
			add_rewrite_rule(
				'^([a-z]{2})/legislators/search/(.+?)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_search=$matches[2]',
				'top'
			);

			// Single legislator (stable URL without gov prefix) - uses numeric ID
			add_rewrite_rule(
				'^legislator/([0-9]+)/?$',
				'index.php?fi_entity=legislator&fi_legislator_id=$matches[1]',
				'top'
			);
			
			// Legislator with session (must come before report-only rule)
			add_rewrite_rule(
				'^legislator/([0-9]+)/session/([0-9]+)/?$',
				'index.php?fi_entity=legislator&fi_legislator_id=$matches[1]&fi_session=$matches[2]',
				'top'
			);
			
			// Legislator with issue/tag by ID (stable URLs; slug-based rule removed)
			add_rewrite_rule(
				'^legislator/([0-9]+)/issue/([0-9]+)/?$',
				'index.php?fi_entity=legislator&fi_legislator_id=$matches[1]&fi_tag_id=$matches[2]',
				'top'
			);
			
			// Legislator with session and report (using report ID)
			add_rewrite_rule(
				'^legislator/([0-9]+)/session/([0-9]+)/report/([0-9]+)/?$',
				'index.php?fi_entity=legislator&fi_legislator_id=$matches[1]&fi_session=$matches[2]&fi_report_id=$matches[3]',
				'top'
			);
			
			// Legislator with specific report (without session - report has session context)
			add_rewrite_rule(
				'^legislator/([0-9]+)/report/([0-9]+)/?$',
				'index.php?fi_entity=legislator&fi_legislator_id=$matches[1]&fi_report_id=$matches[2]',
				'top'
			);
			
			// Legislator report PDF with format/template (format required in URL)
			add_rewrite_rule(
				'^legislator/([0-9]+)/report/([0-9]+)/pdf/([a-z0-9]+)/?$',
				'index.php?fi_entity=legislator&fi_legislator_id=$matches[1]&fi_report_id=$matches[2]&fi_pdf=$matches[3]',
				'top'
			);
			
			// Legislator session report PDF with personalization (pretty URL: /pdf/format/userid_contacts)
			add_rewrite_rule(
				'^legislator/([0-9]+)/session/([0-9]+)/report/([0-9]+)/pdf/([a-z0-9]+)/([0-9]+_[0-9\-]*)/?$',
				'index.php?fi_entity=legislator&fi_legislator_id=$matches[1]&fi_session=$matches[2]&fi_report_id=$matches[3]&fi_pdf=$matches[4]&fi_pdf_pers=$matches[5]',
				'top'
			);
			// Legislator session report PDF without personalization
			add_rewrite_rule(
				'^legislator/([0-9]+)/session/([0-9]+)/report/([0-9]+)/pdf/([a-z0-9]+)/?$',
				'index.php?fi_entity=legislator&fi_legislator_id=$matches[1]&fi_session=$matches[2]&fi_report_id=$matches[3]&fi_pdf=$matches[4]',
				'top'
			);
				
			// Reports list
			add_rewrite_rule(
				'^([a-z]{2})/reports/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=reports',
				'top'
			);
			
			// Single report by ID with chamber and compare view
			add_rewrite_rule(
				'^([a-z]{2})/report/([0-9]+)/chamber/(H|S)/compare/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=report&fi_report_id=$matches[2]&fi_chamber=$matches[3]&fi_compare=1',
				'top'
			);
			
			// Single report by ID with chamber + filter segments (explicit rules; WP optional groups break $matches)
			// Party = slug (r, d). State = lowercase in URL ([a-z]{2}); templates/JS build path with lowercase.
			add_rewrite_rule(
				'^([a-z]{2})/report/([0-9]+)/chamber/(H|S)/state/([a-z]{2})/party/([a-z0-9-]+)/search/([^/]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=report&fi_report_id=$matches[2]&fi_chamber=$matches[3]&fi_report_state=$matches[4]&fi_report_party=$matches[5]&fi_report_search=$matches[6]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/report/([0-9]+)/chamber/(H|S)/state/([a-z]{2})/party/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=report&fi_report_id=$matches[2]&fi_chamber=$matches[3]&fi_report_state=$matches[4]&fi_report_party=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/report/([0-9]+)/chamber/(H|S)/state/([a-z]{2})/search/([^/]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=report&fi_report_id=$matches[2]&fi_chamber=$matches[3]&fi_report_state=$matches[4]&fi_report_search=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/report/([0-9]+)/chamber/(H|S)/state/([a-z]{2})/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=report&fi_report_id=$matches[2]&fi_chamber=$matches[3]&fi_report_state=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/report/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/search/([^/]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=report&fi_report_id=$matches[2]&fi_chamber=$matches[3]&fi_report_party=$matches[4]&fi_report_search=$matches[5]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/report/([0-9]+)/chamber/(H|S)/party/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=report&fi_report_id=$matches[2]&fi_chamber=$matches[3]&fi_report_party=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/report/([0-9]+)/chamber/(H|S)/search/([^/]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=report&fi_report_id=$matches[2]&fi_chamber=$matches[3]&fi_report_search=$matches[4]',
				'top'
			);
			add_rewrite_rule(
				'^([a-z]{2})/report/([0-9]+)/chamber/(H|S)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=report&fi_report_id=$matches[2]&fi_chamber=$matches[3]',
				'top'
			);
			
			// Single report by ID with compare view
			add_rewrite_rule(
				'^([a-z]{2})/report/([0-9]+)/compare/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=report&fi_report_id=$matches[2]&fi_compare=1',
				'top'
			);
			
			// Single report by ID
			add_rewrite_rule(
				'^([a-z]{2})/report/([0-9]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=report&fi_report_id=$matches[2]',
				'top'
			);
			
			// Votes list
			add_rewrite_rule(
				'^([a-z]{2})/votes/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=votes',
				'top'
			);

			// Votes list filtered by chamber (H or S; same as legislators rules).
			// Example: /us/votes/chamber/S/ or /us/votes/chamber/H/
			add_rewrite_rule(
				'^([a-z]{2})/votes/chamber/(H|S)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=votes&fi_chamber=$matches[2]',
				'top'
			);
			
			// Votes list filtered by tag
			add_rewrite_rule(
				'^([a-z]{2})/votes/issue/([a-z0-9-]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=votes&fi_tag_slug=$matches[2]',
				'top'
			);
			
			// Single vote
			add_rewrite_rule(
				'^([a-z]{2})/vote/([0-9]+)/?$',
				'index.php?fi_gov=$matches[1]&fi_entity=vote&fi_vote_id=$matches[2]',
				'top'
			);
			
			// Public list view — /list/{id}/ (ID only; no slugs so links don’t break)
			add_rewrite_rule(
				'^list/([0-9]+)/?$',
				'index.php?fi_entity=list&fi_list_id=$matches[1]',
				'top'
			);

			// Short legislator URL: /[id] only (numeric first segment) -> redirect to /legislator/[id]/
			// Added last so it is tried first (WP prepends each 'top' rule); ensures /1024/ isn't matched by anything else.
			add_rewrite_rule(
				'^([0-9]+)/?$',
				'index.php?fi_entity=legislator&fi_legislator_id=$matches[1]&fi_tiny_redirect=1',
				'top'
			);
			
			// Legacy routes (V1/V2 compatibility) - redirect to new structure
			// NOTE: /legislator/{slug}/ is now a stable URL (no legacy handling needed)
			// Legacy routes are handled at 404 time, not via rewrite rules
			// NOTE: Legacy vote routes are handled at 404 time, not via rewrite rules
			
			// Flush rewrite rules if needed
			if (get_option('fi_flush_rewrite_rules')) {
				flush_rewrite_rules(false); // false = soft flush (faster, doesn't regenerate .htaccess)
				delete_option('fi_flush_rewrite_rules');
				self::log('FI Rewrite Rules Flushed', __FILE__, __LINE__, 'info');
			}
		}
    
		/**
		* Add query vars
		*/
		public function add_query_vars(array $vars): array {
			// Add compare view parameter
			$vars[] = 'fi_compare';
			$vars[] = 'fi_gov';
			$vars[] = 'fi_entity';
			$vars[] = 'fi_legislator_id';
			$vars[] = 'fi_tiny_redirect';
			$vars[] = 'fi_slug'; // Still used for reports and other entities
			$vars[] = 'fi_vote_id';
			$vars[] = 'fi_session';
			$vars[] = 'fi_report_id'; // New: use ID instead of slug
			$vars[] = 'fi_pdf'; // Contains format: 'sca', 'scb', 'scc', 'fia', etc.
			$vars[] = 'fi_pdf_pers'; // Pretty URL: userid_contacts e.g. 1_1-2
			$vars[] = 'fi_list_id'; // account/lists/{id}/ and public /list/{id}/
			$vars[] = 'fi_legacy';
			$vars[] = 'fi_party';
			$vars[] = 'fi_chamber';
			$vars[] = 'fi_search';
			$vars[] = 'fi_tab';
			
			// Standard query vars for filters (used in URLs and form submissions)
			$vars[] = 'session';
			$vars[] = 'party';
			$vars[] = 'chamber';
			$vars[] = 'search';
			$vars[] = 'sort'; // Form uses name="sort"; must be registered so ?sort=ad is preserved on redirect
			
			// Pretty URL query vars
			//SESSIONSLUG: Remove 'fi_session_slug', add 'fi_session_id' instead
			$vars[] = 'fi_session_id';
			$vars[] = 'fi_party_slug';
			$vars[] = 'fi_chamber';
			$vars[] = 'fi_state';
			$vars[] = 'fi_search';
			$vars[] = 'fi_sort';
			$vars[] = 'fi_tag_slug';
			$vars[] = 'fi_tag_id';
			// Report legislator filter (pretty URL segments)
			$vars[] = 'fi_report_state';
			$vars[] = 'fi_report_party';
			$vars[] = 'fi_report_search';
			
			return $vars;
		}
// sort=nd fail - attempted fix failed
		/**
		* Prevent redirect_canonical from redirecting legislators list URLs.
		* Core builds redirect URLs using _remove_qs_args_if_not_in_url() and can drop custom query
		* vars (e.g. sort). When the filter form is submitted from another page, the request has
		* ?session=...&chamber=...&party=...&sort=...; a redirect would strip sort before parse_filters() runs.
		*
		* @param string $redirect_url URL that redirect_canonical would redirect to
		* @param string $requested_url The requested URL
		* @return string|false Pass-through $redirect_url, or false to cancel redirect for legislators
		*/
		public function prevent_legislators_canonical_redirect($redirect_url, $requested_url) {
			$entity = get_query_var('fi_entity');
			// Report URLs carry filter segments in path; canonical redirect can strip them and send to /us/
			if ($entity === 'legislators' || $entity === 'legislator' || $entity === 'report') {
				return false;
			}
			return $redirect_url;
		}
		
		/**
		* Handle rewrite requests
		*/
		public function handle_rewrite_requests(): void {
			// ALWAYS log entry to see if this function is called
			self::log('ENTER handle_rewrite_requests: ' . $_SERVER['REQUEST_URI']);
			
			// Debug switch (OFF by default).
			// Summary: rewrite runs on every front-end request (template_redirect). Unconditional logging can overwhelm
			// PHP workers and filesystem locks under traffic, causing sitewide timeouts.
			$debug_rewrite = (isset($_GET['fi_debug']) && $_GET['fi_debug'] === 'rewrite');

			// If fi_search is present on front page, redirect to US legislators with search
			/*
			if (is_front_page() && isset($_GET['fi_search']) && !empty($_GET['fi_search'])) {
				$search_term = sanitize_text_field($_GET['fi_search']);
				$search_url = self::build_filter_url('us', ['search' => $search_term]);
				wp_safe_redirect($search_url, 302);
				exit;
			}
			*/
			
			$gov = get_query_var('fi_gov');
			// Normalize gov code to lowercase (WordPress rewrite rules should do this, but ensure consistency)
			if ($gov) {
				$gov = strtolower($gov);
			}
			$entity = get_query_var('fi_entity');
			$slug = get_query_var('fi_slug');
//			$legacy = get_query_var('fi_legacy');
			
			// Debug logging (opt-in only via ?fi_debug=rewrite)
			global $wp_query;
			$request_uri = $_SERVER['REQUEST_URI'] ?? '';
			
			// ALWAYS log for legislators URLs to debug 404 issue
			if (strpos($request_uri, '/legislator') !== false || $debug_rewrite) {
				self::log('REWRITE DEBUG: REQUEST_URI=' . $request_uri . ' | gov=' . ($gov ?: 'EMPTY') . ' | entity=' . ($entity ?: 'EMPTY') . ' | slug=' . ($slug ?: 'EMPTY') . ' | legislator_id=' . get_query_var('fi_legislator_id'));
			}
			
			if ($debug_rewrite) {
				self::log(
					'REQUEST_URI: ' . $request_uri .
					'|gov: ' . ($gov ?: 'empty') .
					', entity: ' . ($entity ?: 'empty') .
					', slug: ' . ($slug ?: 'empty') .
					' | fi_session_slug: ' . (get_query_var('fi_session_slug') ?: 'empty') .
					', fi_chamber: ' . (get_query_var('fi_chamber') ?: 'empty'),
					__FILE__,
					__LINE__,
					'debug'
				);
			}
			
			// If no entity matched, this might be a 404 - log for debugging
			// Only log warning if entity is actually empty AND URL contains legislator paths
			if ($debug_rewrite && !$entity && (strpos($request_uri, '/legislator/') !== false || strpos($request_uri, '/legislators/') !== false)) {
				self::log('No entity matched for URL: ' . $request_uri . ' - Rewrite rules may need flushing',__FILE__, __LINE__, 'warning');
			}
			
			if (!$entity) {
				self::log('REWRITE: No entity matched, returning (will be 404)');
				return;
			}
			
			self::log('REWRITE: Matched entity=' . $entity . ', calling handler...');
			
			// Handle legacy routes - redirect to new structure with gov
			/* Handled by template 404
			if ($legacy) {
				$this->handle_legacy_redirect($entity, $slug);
				return;
			}
			*/
			
			// Handle different entity types
			switch ($entity) {
				case 'government':
					if (!$gov) {
						$this->handle_404();
						return;
					}
					$this->handle_government_request($gov);
					break;
					
				case 'legislators':
					if (!$gov) {
						$this->handle_404();
						return;
					}
					$this->handle_legislators_list_request($gov);
					break;
					
				case 'legislator':
					// Tiny URL: redirect to canonical /legislator/{id}/
					if (get_query_var('fi_tiny_redirect')) {
						$legislator_id = (int) get_query_var('fi_legislator_id');
						if ($legislator_id > 0) {
							wp_safe_redirect(home_url('/legislator/' . $legislator_id . '/'), 301);
							exit;
						}
					}
					$legislator_id = (int)get_query_var('fi_legislator_id');
					$this->handle_legislator_request(null, $legislator_id);
					break;
					
				case 'reports':
					$this->handle_reports_list_request($gov);
					break;
					
				case 'report':
					$this->handle_report_request($gov, $slug);
					break;
					
				case 'votes':
					$this->handle_votes_list_request($gov);
					break;
					
				case 'vote':
					$vote_id = (int)get_query_var('fi_vote_id');
					$this->handle_vote_request($gov, $vote_id);
					break;
					
				case 'list':
					$this->handle_list_request();
					break;
			}
		}
		
		/**
		* Handle legacy redirects - determine gov and redirect to new URL
		*/
/*
		private function handle_legacy_redirect(string $entity, string $slug): void {
			$gov = null;
			
			if ($entity === 'legislator') {
				// Get legislator's most recent session to determine gov
				$legislator_id = (int)$slug; // Slug is now the ID
				$legislator = fi_legislator_get($legislator_id);
				if ($legislator) {
					$gov = $this->get_legislator_current_gov($legislator->id);
				}
			} elseif ($entity === 'vote') {
				// Get vote's session to determine gov
				global $wpdb;
				$vote = $wpdb->get_row($wpdb->prepare(
					"SELECT s.gov 
					FROM {$wpdb->prefix}fi_votes v
					INNER JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
					WHERE v.bill_key = %s
					LIMIT 1",
					$slug
				));
				if ($vote) {
					$gov = strtolower($vote->gov);
				}
			}
			
			if ($gov) {
				$new_url = home_url("/{$gov}/{$entity}/{$slug}/");
				wp_safe_redirect($new_url, 301);
				exit;
			} else {
				$this->handle_404();
			}
		}
*/

		/**
		* Get legislator's current government code from most recent session
		* Queries fi_legislator_sessions joined with fi_sessions to get gov
		*/
		private function get_legislator_current_gov(int $legislator_id): ?string {
			global $wpdb;
			
			$gov = $wpdb->get_var($wpdb->prepare(
				"SELECT s.gov 
				FROM {$wpdb->prefix}fi_legislator_sessions ls
				INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
				WHERE ls.legislator_id = %d
				ORDER BY s.date_start DESC
				LIMIT 1",
				$legislator_id
			));
			
			return $gov ? strtolower($gov) : null;
		}
		
		/**
		* Handle government landing page request
		*/
		private function handle_government_request(string $gov): void {
			$gov = strtoupper($gov);
			
			// Validate government code
			if (!fi_gov_validate($gov)) {
				$this->handle_404();
				return;
			}
			
			// Get current session for gov
			$current_session_obj = fi_session_get_current($gov);
			$current_session = $current_session_obj ? $current_session_obj->id : null;
			
			// Get all sessions for this gov
			$sessions = fi_sessions_get_by_gov($gov);
			self::log('handle_government_request: Sessions: ' . json_encode($sessions), __FILE__, __LINE__);

			// Get reports for current session
			$reports = [];
			if ($current_session) {
				$reports = fi_reports_get([
					'session_id' => $current_session,
					'status' => 'publish',
					'orderby' => 'date_publish',
					'order' => 'DESC'
				]);
			}
			
			// Get current session legislators with scores
			$legislators = [];
			if ($current_session) {
				$legislators = fi_legislators_list_get([
					'session_id' => $current_session,
					'gov' => $gov,
					'limit' => 600
				]);
			}
			
			// Get recent votes (10 most recent)
			$recent_votes = fi_votes_get([
				'gov' => $gov,
				'status' => 'publish',
				'orderby' => 'date_voted',
				'order' => 'DESC',
				'per_page' => 10
			]);
			
			// Set up global variables for template
			global $fi_gov, $fi_gov_name, $fi_current_session, $fi_sessions, $fi_reports, $fi_legislators, $fi_recent_votes;
			$fi_gov = $gov;
			$fi_gov_name = fi_gov_name($gov);
			$fi_current_session = $current_session;
			$fi_sessions = $sessions;
			$fi_reports = $reports;
			$fi_legislators = $legislators;
			$fi_recent_votes = $recent_votes;
			
			// Load template
			$this->load_template('government');
		}
		
		/**
		* Handle legislators list request
		* Formats: list,cards
		* https://votetruth.us/?fi_gov=fl&fi_entity=legislators&fi_session=85&fi_pdf=cards
		*/
		private function handle_legislators_list_request(string $gov): void {
			self::log('handle_legislators_list_request: gov=' . $gov);
			$gov = strtoupper($gov);
			
			// Check if this is a PDF request
			$pdf_format = get_query_var('fi_pdf');
			if ($pdf_format === 'list' || $pdf_format === 'cards') {
				$session_id = (int) get_query_var('fi_session_id');
				$this->handle_legislators_list_pdf($gov, $session_id, $pdf_format);
				return;
			}
			
			// Validate government code
			if (!fi_gov_validate($gov)) {
				self::log('handle_legislators_list_request: Invalid gov code: ' . $gov);
				$this->handle_404();
				return;
			}
			
			//self::log('handle_legislators_list_request: Gov validated, getting current session...');
			
			// Get current session for gov (most recent main/parent session)
			// Get current session for gov
			$current_session_obj = fi_session_get_current($gov);
			$current_session = $current_session_obj ? $current_session_obj->id : null;
			
			//self::log('handle_legislators_list_request: Current session=' . ($current_session ?: 'NULL'));
			
			if (!$current_session) {
				self::log('handle_legislators_list_request: No current session found, returning 404');
				$this->handle_404();
				return;
			}

			// Parse filters from URL/request (returns slugs)
			$parsed_filters = self::parse_filters($gov);

			// Convert to query args for database queries (converts slugs to IDs/names)
			$filter_args = self::filters_to_query_args($gov, $parsed_filters, $current_session);
			
			
			// Load legislators server-side using new lean query class
			$legislators = [];
			$total_count = 0;
			$has_more = false;
			$current_offset = 0;
			
			// Get session ID from filter args (function will auto-resolve if not set)
			$session_id = $filter_args['session'] ?? null;
			
			// Load ALL legislators for SEO (max 535 per gov+session constraint)
			// Grid template will paginate client-side for UX
			$query_args = [
				'gov'        => $gov,
				'session_id' => $session_id,
				'chamber'    => $filter_args['chamber'] ?? '',
				'party'      => $filter_args['party'] ?? '',
				'state'      => $filter_args['state'] ?? '',
				'search'     => $filter_args['search'] ?? '',
				'sort'       => $_GET['sort'] ?? 'na',
				'limit'      => 600, // Load all for SEO (max 535 per gov+session)
				'offset'     => 0,
			];
			
			$legislators = fi_legislators_list_get($query_args);
			$total_count = count($legislators); // Since we loaded all
			
			// For template: show first 24 to users, all to crawlers
			$display_limit = 24;
			$has_more = $total_count > $display_limit;
			$current_offset = $display_limit;
			
			// Get all reports for this session (for horizontal scroll nav)
			$reports = fi_reports_get(['session_id' => $session_id, 'per_page' => -1]);
				
			// Build report links array for navigation
			$report_links = [];
			if ($reports) {
				foreach ($reports as $report) {
					$report_url = self::url_report((int)$report->id, strtolower($report->gov ?? $gov));
					$report_title = $report->title ?? 'Untitled Report';
					$report_links[] = [
						'url' => $report_url,
						'title' => $report_title,
					];
				}
			}
			
			// Build filter description from active filters
			$filter_description = fi_filter_build_description(array(
				'gov' => $gov,
				'session' => $session_id,
				'party' => $parsed_filters['party_slug'] ?? null,
				'chamber' => $parsed_filters['chamber'] ?? null,
				'state' => $parsed_filters['state'] ?? null
			));
			
			// Set up global variables for template
			global $fi_legislators, $fi_gov, $fi_gov_name, $fi_session, $fi_reports, $fi_report_links, $fi_filter_description;
			global $fi_has_more, $fi_offset, $fi_total_count, $fi_limit;
			$fi_legislators = $legislators;
			$fi_gov = $gov;
			$fi_gov_name = fi_gov_name($gov);
			$fi_session = $session_id;
			$fi_reports = $reports;
			$fi_report_links = $report_links;
			$fi_filter_description = $filter_description;
			$fi_has_more = $has_more;
			$fi_offset = $current_offset;
			$fi_total_count = $total_count;
			$fi_limit = $display_limit;
			
			// Check for HTMX request - return partial HTML
			$is_htmx = isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
			if ($is_htmx) {
				// HTMX request - return just the grid HTML
				$this->load_template('legislators-grid');
				return;
			}
			
			// Regular request - load full template
			$this->load_template('legislators');
		}
		
		/**
		* Handle legislator request
		* Legislator URLs are stable and don't include gov prefix
		* Gov is determined from legislator's current/most recent session for display purposes
		*/
		private function handle_legislator_request(?string $gov, ?int $legislator_id = null): void {
			self::log('handle_legislator_request: START, legislator_id=' . ($legislator_id ?: 'null'));
			
			// Get legislator ID from query var
			if (!$legislator_id) {
				$legislator_id = (int)get_query_var('fi_legislator_id');
				self::log('handle_legislator_request: Got legislator_id from query var: ' . $legislator_id);
			}
			
			if (!$legislator_id) {
				self::log('handle_legislator_request: No legislator_id, 404');
				$this->handle_404();
				return;
			}
			
			// Get legislator with sessions for template
			if (!function_exists('fi_legislator_get_with_sessions')) {
				self::log('handle_legislator_request: fi_legislator_get_with_sessions function does not exist, 404');
				$this->handle_404();
				return;
			}
			
			$legislator = fi_legislator_get_with_sessions($legislator_id);
			self::log('handle_legislator_request: fi_legislator_get_with_sessions(' . $legislator_id . ') returned: ' . ($legislator ? 'object' : 'NULL/False'));
			
			if (!$legislator) {
				self::log('handle_legislator_request: No legislator found for ID ' . $legislator_id . ', 404');
				$this->handle_404();
				return;
			}
			
			// Determine gov from legislator's current session (for display/context purposes)
			// This doesn't affect the URL - it's just for template context
			$current_gov = $this->get_legislator_current_gov($legislator->id);
			
			// If gov was specified in URL, ignore it - we use stable URLs now
			
			// Get session ID, report ID/slug, and tag slug from query vars (from rewrite rules)
			$session_id = get_query_var('fi_session');
			$report_id = get_query_var('fi_report_id');
			$tag_slug = get_query_var('fi_tag_slug');
			$pdf_format = get_query_var('fi_pdf'); // Contains format: 'sca', 'scb', 'scc', 'fia', etc.
			
			// If PDF format specified, load PDF template
			if (!empty($pdf_format)) {
				// PDF requires a report - if no report ID/slug, show 404
				if ($report_id) {
					$this->handle_legislator_pdf($legislator, $report_id, $session_id, $pdf_format);
				} else {
					// No report specified - show 404
					$this->handle_404();
				}
				return;
			}
			
			// Set up global variables for template
			global $fi_legislator, $fi_gov, $fi_report_id, $fi_report_slug, $fi_session_id, $fi_tag_slug;
			$fi_legislator = $legislator;
			$fi_gov = $current_gov ? strtoupper($current_gov) : null;
			$fi_report_id = $report_id ? (int) $report_id : null;
			$fi_session_id = $session_id ? (int) $session_id : null;
			$fi_tag_slug = $tag_slug;

			// Load template (new refactored version with lazy loading and HTMX)
			$this->load_template('legislator');
		}
		
		/**
		* Handle legislator PDF request
		* Resolves contacts from GET (contacts=csv and optional u=user_id). Privacy: u only honored when equals current user.
		*
		* @param object $legislator Legislator object
		* @param int|null $report_id Report ID (preferred)
		* @param string|null $report_slug Report slug (backward compatibility)
		* @param int|null $session_id Optional session ID
		* @param string $pdf_format PDF format: 'sca', 'scb', 'scc', 'fia', etc.
		*/
		private function handle_legislator_pdf($legislator, ?int $report_id = null, ?int $session_id = null, string $pdf_format = ''): void {
			$template_file = FI_PUBLIC_DIR . 'pdf/legislator.php';
			if (!file_exists($template_file)) {
				$this->handle_404();
				return;
			}

			// Parse personalization from path: fi_pdf_pers = userid_contacts (e.g. 1_1-2)
			$contact_indexes = [];
			$user_id = 0;
			$pers = get_query_var('fi_pdf_pers');
			if (is_string($pers) && $pers !== '') {
				$underscore = strpos($pers, '_');
				if ($underscore !== false) {
					$req_user = (int) substr($pers, 0, $underscore);
					$contacts_part = trim(substr($pers, $underscore + 1));
					$current_user_id = get_current_user_id();
					if ($req_user > 0 && $req_user !== $current_user_id) {
						$req_user = 0; // privacy: ignore spoofed user
					}
					$user_id = $req_user > 0 ? $req_user : ($current_user_id > 0 ? $current_user_id : 0);
					if ($contacts_part !== '') {
						$parts = explode('-', $contacts_part);
						foreach ($parts as $p) {
							$p = trim($p);
							if ($p !== '' && ctype_digit($p)) {
								$idx = (int) $p;
								if ($idx >= 0 && !in_array($idx, $contact_indexes, true)) {
									$contact_indexes[] = $idx;
								}
							}
						}
					}
				}
			} else {
				$current_user_id = get_current_user_id();
				$user_id = $current_user_id > 0 ? $current_user_id : 0;
			}

			// Build pdf_contacts list from resolved user/guest and selected indexes
			$pdf_contacts = [];
			if ($user_id > 0) {
				$all = fi_pdf_contacts_get($user_id);
				foreach ($contact_indexes as $idx) {
					if (isset($all[$idx])) {
						$pdf_contacts[] = $all[$idx];
					}
				}
			} else {
				$all = fi_pdf_contacts_guest_get();
				foreach ($contact_indexes as $idx) {
					if (isset($all[$idx])) {
						$pdf_contacts[] = $all[$idx];
					}
				}
			}

			$data = fi_legislator_get((string)$legislator->id);
			$report = $report_id ? fi_report_get($report_id) : null;

			$args = [
				'legislator'   => $data,
				'report'       => $report,
				'session_id'   => $session_id,
				'pdf_format'   => $pdf_format,
				'pdf_contacts' => $pdf_contacts,
			];
			extract($args);
			include $template_file;
		}
		
		/**
		* Handle legislators list PDF request
		* 
		* @param string $gov Government code
		* @param int|null $session_id Session ID
		*/
		private function handle_legislators_list_pdf(string $gov, ?int $session_id = null, string $pdf_format = 'cards'): void {
			$gov = strtoupper($gov);
			
			// Validate government code
			if (!fi_gov_validate($gov)) {
				$this->handle_404();
				return;
			}
			
			// Validate session exists and matches gov
			if (!$session_id) {
				$this->handle_404();
				return;
			}
			
			$session_obj = fi_session_get($session_id);
			if (!$session_obj || $session_obj->gov !== $gov) {
				$this->handle_404();
				return;
			}
			
			// Parse filters from URL/request
			$parsed_filters = self::parse_filters($gov);
			
			// Convert to query args for database queries
			$filter_args = self::filters_to_query_args($gov, $parsed_filters, $session_id);
			
			// Build filters array for fi_legislators_get_by_session
			$filters = [];
			if (!empty($filter_args['chamber'])) {
				$filters['chamber'] = $filter_args['chamber'];
			}
			if (!empty($filter_args['party'])) {
				$filters['party'] = $filter_args['party'];
			}
			if (!empty($filter_args['state'])) {
				$filters['state'] = $filter_args['state'];
			}
			if (!empty($filter_args['search'])) {
				$filters['search'] = $filter_args['search'];
			}
			// Add sort parameters if provided (defaults to last_name ASC if not specified)
			if (!empty($filter_args['orderby'])) {
				$filters['orderby'] = $filter_args['orderby'];
			}
			if (!empty($filter_args['order'])) {
				$filters['order'] = $filter_args['order'];
			}
			
			// Get legislators for this session with filters (sorting handled by query)
			$legislators = fi_legislators_get_by_session($session_id, $filters);
			
			// Load PDF template
			$template_file = FI_PUBLIC_DIR . 'pdf/legislators.php';
			
			if (file_exists($template_file)) {
				// Extract args for template
				$args = [
					'legislators' => $legislators,
					'gov' => $gov,
					'session_id' => $session_id,
					'session_obj' => $session_obj,
					'filters' => $parsed_filters,
					'filter_sort' => $parsed_filters['sort'] ?? null,
					'pdf_format' => $pdf_format
				];
				
				extract($args);
				include $template_file;
			} else {
				$this->handle_404();
			}
		}
		
		/**
		* Handle reports list request
		*/
		private function handle_reports_list_request(string $gov): void {
			$gov = strtoupper($gov);
			
			// Validate government code
			if (!fi_gov_validate($gov)) {
				$this->handle_404();
				return;
			}
			
			// Get current session for gov (determined programmatically)
			$sessions = fi_sessions_get_by_gov($gov);
			$current_session = !empty($sessions) ? $sessions[0]->id : null;
			
			// Get reports for this government
			$reports = fi_reports_get(['gov' => $gov, 'per_page' => 50]);
			
			// Set up global variables for template
			global $fi_reports, $fi_gov, $fi_session;
			$fi_reports = $reports;
			$fi_gov = $gov;
			$fi_session = $current_session;
			
			// Load template
			$this->load_template('reports');
		}
		
		/**
		* Handle report request
		*/
		private function handle_report_request(string $gov, string $slug): void {
			$report_id = get_query_var('fi_report_id');
			
			if (!$report_id) {
				$this->handle_404();
				return;
			}
			
			$report = fi_report_get((int)$report_id);
			
			if (!$report) {
				$this->handle_404();
				return;
			}
			
			// Verify gov matches
			if (strtoupper($gov) !== $report->gov) {
				// Redirect to correct gov
				wp_safe_redirect(home_url("/" . strtolower($report->gov) . "/report/{$report->id}/"), 301);
				exit;
			}
			
			// Set up global variables for template
			global $fi_report, $fi_gov;
			$fi_report = $report;
			$fi_gov = $report->gov;
			
			// Load template
			$this->load_template('report');
		}
		
		/**
		* Handle votes list request
		*/
		private function handle_votes_list_request(string $gov): void {
			$gov = strtoupper($gov);
			
			// Validate government code
			if (!fi_gov_validate($gov)) {
				$this->handle_404();
				return;
			}
			
			// Get tag slug from query var if present
			$tag_slug = get_query_var('fi_tag_slug');

			// Get chamber filter from query var if present (h/s)
			$chamber = get_query_var('fi_chamber');
			$chamber = is_string($chamber) ? strtoupper($chamber) : '';
			if (!in_array($chamber, ['H', 'S'], true)) {
				$chamber = '';
			}
			
			// Get current session for gov
			// Get current session for gov (determined programmatically)
			$sessions = fi_sessions_get_by_gov($gov);
			$current_session = !empty($sessions) ? $sessions[0]->id : null;
			
			// Set up global variables for template
			global $fi_gov, $fi_session, $fi_tag_slug, $fi_chamber;
			$fi_gov = $gov;
			$fi_session = $current_session;
			$fi_tag_slug = $tag_slug;
			$fi_chamber = $chamber;
			
			// Load template
			$this->load_template('votes');
		}
		
		/**
		* Handle vote request
		*/
		private function handle_vote_request(string $gov, int $vote_id): void {
			// Use helper function to get vote by ID
			if (!function_exists('fi_vote_get')) {
				$this->handle_404();
				return;
			}
			
			$vote = fi_vote_get($vote_id);
			
			if (!$vote) {
				$this->handle_404();
				return;
			}
			
			// Verify gov matches
			if (strtoupper($gov) !== $vote->gov) {
				// Redirect to correct gov
				wp_safe_redirect(home_url("/" . strtolower($vote->gov) . "/vote/{$vote->id}/"), 301);
				exit;
			}
			
			// Set up global variables for template
			global $fi_vote, $fi_gov;
			$fi_vote = $vote;
			$fi_gov = $vote->gov;
			
			// Load template
			$this->load_template('vote');
		}
		
	/**
	* Handle public list request — /list/{id}/
	*/
	private function handle_list_request(): void {
		$list_id = (int) get_query_var('fi_list_id');
		if (!$list_id) {
			$this->handle_404();
			return;
		}
		$list_obj = fi_list_get_by_id($list_id);
		if (!$list_obj) {
			$this->handle_404();
			return;
		}
		global $fi_list;
		$fi_list = $list_obj;
		$this->load_template('list');
	}
		
		/**
		* Load template
		*/
		private function load_template(string $template_name): void {
			global $wp_query;

			$wp_query->is_404 = false;
			$wp_query->is_page = true;
			$wp_query->is_singular = true;
			$wp_query->is_home = false;
			$wp_query->is_archive = false;
			status_header(200);

			$template_path = FI_DIR . "public/templates/{$template_name}.php";
			self::log('Loading template: ' . $template_path);
			
			if (file_exists($template_path)) {
				self::log('Template exists, including: ' . $template_path);
				include $template_path;
				self::log('Template included successfully, exiting');
				exit;
			}

			self::log('FI Template not found: ' . $template_path, __FILE__, __LINE__, 'warning');
			$this->handle_404();
		}
		
		/**
		* Handle 404
		*/
		private function handle_404(): void {
			global $wp_query;
			$wp_query->set_404();
			status_header(404);
			get_template_part('404');
			exit;
		}
		
		/**
		* Flush rewrite rules
		*/
		public static function flush_rules(): void {
			update_option('fi_flush_rewrite_rules', true);
		}
		
		/**
		* Prevent WordPress from treating fi_search as a post search query
		* This prevents the "Nothing Found" page when fi_search parameter is present
		*/
		public function prevent_fi_search_post_query($query) {
			// Only affect main query on front-end
			if (is_admin() || !$query->is_main_query()) {
				return;
			}
			
			// If fi_search parameter exists, prevent WordPress from running a post search
			if (isset($_GET['fi_search']) && !empty($_GET['fi_search'])) {
				// Unset the search query to prevent WordPress from searching posts
				$query->set('s', '');
				$query->is_search = false;
				$query->is_404 = false; // Prevent 404 error
				$query->is_home = is_front_page();
				$query->is_page = false;
			}
		}

		public static function log(string $message, string $file='', int $line=0, string $level = 'info'): void {
			fi_log_area('rewrite', $message, $file, $line, $level);
		}
	}
}


namespace{
	// Initialize rewrite class
	add_action('plugins_loaded', function() {
		new \FI\Public\Rewrite();
	});

	function fi_get_legislator_url(int $legislator_id, ?string $gov = null): string {
		return \FI\Public\Rewrite::get_legislator_url($legislator_id, $gov);
	}

	function fi_get_legislator_short_url(int $legislator_id): string {
		return \FI\Public\Rewrite::get_legislator_short_url($legislator_id);
	}

	/**
	* Get report URL
	* 
	* @param int $report_id Report ID
	* @param string|null $gov Optional government code
	* @param array $args Optional arguments (chamber, compare, state)
	* @return string Report URL
	*/
	function fi_url_report(int $report_id, ?string $gov = null, array $args = []): string {
		return \FI\Public\Rewrite::url_report($report_id, $gov, $args);
	}

	/**
	* Get list URL
	* 
	* @param int $user_id User ID
	* @param int $list_id List ID
	* @return string List URL
	*/
	function fi_url_list(int $user_id, int $list_id): string {
		return \FI\Public\Rewrite::url_list($user_id, $list_id);
	}

	function fi_url_vote(string $gov, int $vote_id): string {
		return \FI\Public\Rewrite::url_vote($gov, $vote_id);
	}

	/**
	* Build pretty URL for legislator filters
	* 
	* @param string $gov Government code
	* @param array $filters Filter values
	* @return string Pretty URL
	*/
	function fi_build_filter_url(string $gov, array $filters = []): string {
		return \FI\Public\Rewrite::build_filter_url($gov, $filters);
	}

	/**
	* Parse filters from URL/request
	* 
	* @param string $gov Government code
	* @return array Parsed filters with slugs
	*/
	function fi_parse_filters(string $gov): array {
		return \FI\Public\Rewrite::parse_filters($gov);
	}

}
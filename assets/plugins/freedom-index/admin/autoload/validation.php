<?php
namespace FI\Admin;

if (!defined('ABSPATH')) exit;

/**
 * Data Validation for Freedom Index Admin
 * 
 * Provides comprehensive data validation and integrity checks.
 * Identifies orphaned records and data inconsistencies.
 */
final class DataValidation {

    /**
     * Initialize data validation
     */
    public static function init(): void {
        add_action('admin_notices', [self::class, 'show_validation_notices']);
        add_action('wp_ajax_fi_validate_data', [self::class, 'ajax_validate_data']);
        add_action('wp_ajax_fi_fix_data_issues', [self::class, 'ajax_fix_data_issues']);
    }

    /**
     * Run comprehensive data validation
     */
    public static function validate_all(): array {
        $issues = [];
        
        // Validate legislators
        $issues = array_merge($issues, self::validate_legislators());
        
        // Validate sessions
        $issues = array_merge($issues, self::validate_sessions());
        
        // Validate legislator sessions
        $issues = array_merge($issues, self::validate_legislator_sessions());
        
        // Validate votes
        $issues = array_merge($issues, self::validate_votes());
        
        // Validate roll calls
        $issues = array_merge($issues, self::validate_roll_calls());
        
        // Validate scores
        $issues = array_merge($issues, self::validate_scores());
        
        // Validate reports
        $issues = array_merge($issues, self::validate_reports());
        
        // Validate user lists
        $issues = array_merge($issues, self::validate_user_lists());
        
        return $issues;
    }

    /**
     * Validate legislators
     */
    public static function validate_legislators(): array {
        $issues = [];
        
        global $wpdb;
        
        // Check for legislators without sessions
        $orphaned_legislators = $wpdb->get_results(
            "SELECT l.id, l.display_name
             FROM {$wpdb->prefix}fi_legislators l
             LEFT JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
             WHERE ls.legislator_id IS NULL"
        );
        
        if (!empty($orphaned_legislators)) {
            $issues[] = [
                'type' => 'orphaned_legislators',
                'severity' => 'high',
                'message' => 'Legislators without sessions found',
                'count' => count($orphaned_legislators),
                'data' => $orphaned_legislators,
                'fixable' => true
            ];
        }
        
        // Check for missing required fields
        $missing_fields = $wpdb->get_results(
            "SELECT id, display_name
             FROM {$wpdb->prefix}fi_legislators
             WHERE first_name = '' OR last_name = '' OR display_name = ''"
        );
        
        if (!empty($missing_fields)) {
            $issues[] = [
                'type' => 'missing_required_fields',
                'severity' => 'medium',
                'message' => 'Legislators with missing required fields',
                'count' => count($missing_fields),
                'data' => $missing_fields,
                'fixable' => true
            ];
        }
        
        return $issues;
    }

    /**
     * Validate sessions
     */
    public static function validate_sessions(): array {
        $issues = [];
        
        global $wpdb;
        
        // Check for sessions without legislators
        $empty_sessions = $wpdb->get_results(
            "SELECT s.id, s.name, s.gov
             FROM {$wpdb->prefix}fi_sessions s
             LEFT JOIN {$wpdb->prefix}fi_legislator_sessions ls ON s.id = ls.session_id
             WHERE ls.session_id IS NULL"
        );
        
        if (!empty($empty_sessions)) {
            $issues[] = [
                'type' => 'empty_sessions',
                'severity' => 'medium',
                'message' => 'Sessions without legislators found',
                'count' => count($empty_sessions),
                'data' => $empty_sessions,
                'fixable' => false
            ];
        }
        
        // Check for overlapping sessions
        $overlapping_sessions = $wpdb->get_results(
            "SELECT s1.id as session1_id, s1.name as session1_name, s2.id as session2_id, s2.name as session2_name
             FROM {$wpdb->prefix}fi_sessions s1
             INNER JOIN {$wpdb->prefix}fi_sessions s2 ON s1.gov = s2.gov AND s1.id < s2.id
             WHERE s1.date_start <= s2.date_end AND s1.date_end >= s2.date_start"
        );
        
        if (!empty($overlapping_sessions)) {
            $issues[] = [
                'type' => 'overlapping_sessions',
                'severity' => 'low',
                'message' => 'Overlapping sessions found',
                'count' => count($overlapping_sessions),
                'data' => $overlapping_sessions,
                'fixable' => true
            ];
        }
        
        return $issues;
    }

    /**
     * Validate legislator sessions
     */
    public static function validate_legislator_sessions(): array {
        $issues = [];
        
        global $wpdb;
        
        // Check for orphaned legislator sessions
        $orphaned_sessions = $wpdb->get_results(
            "SELECT ls.id, ls.legislator_id, ls.session_id
             FROM {$wpdb->prefix}fi_legislator_sessions ls
             LEFT JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
             WHERE l.id IS NULL"
        );
        
        if (!empty($orphaned_sessions)) {
            $issues[] = [
                'type' => 'orphaned_legislator_sessions',
                'severity' => 'high',
                'message' => 'Legislator sessions without legislators found',
                'count' => count($orphaned_sessions),
                'data' => $orphaned_sessions,
                'fixable' => true
            ];
        }
        
        // Check for overlapping legislator sessions
        $overlapping_sessions = $wpdb->get_results(
            "SELECT ls1.legislator_id, ls1.session_id as session1_id, ls2.session_id as session2_id
             FROM {$wpdb->prefix}fi_legislator_sessions ls1
             INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls2 ON ls1.legislator_id = ls2.legislator_id AND ls1.id < ls2.id
             WHERE ls1.date_start <= ls2.date_end AND ls1.date_end >= ls2.date_start"
        );
        
        if (!empty($overlapping_sessions)) {
            $issues[] = [
                'type' => 'overlapping_legislator_sessions',
                'severity' => 'medium',
                'message' => 'Overlapping legislator sessions found',
                'count' => count($overlapping_sessions),
                'data' => $overlapping_sessions,
                'fixable' => true
            ];
        }
        
        return $issues;
    }

    /**
     * Validate votes
     */
    public static function validate_votes(): array {
        $issues = [];
        
        global $wpdb;
        
        // Check for votes without sessions
        $orphaned_votes = $wpdb->get_results(
            "SELECT v.id, v.slug, v.title
             FROM {$wpdb->prefix}fi_votes v
             LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
             WHERE s.id IS NULL"
        );
        
        if (!empty($orphaned_votes)) {
            $issues[] = [
                'type' => 'orphaned_votes',
                'severity' => 'high',
                'message' => 'Votes without sessions found',
                'count' => count($orphaned_votes),
                'data' => $orphaned_votes,
                'fixable' => true
            ];
        }
        
        // Check for votes without roll calls
        $votes_without_roll = $wpdb->get_results(
            "SELECT v.id, v.slug, v.title
             FROM {$wpdb->prefix}fi_votes v
             LEFT JOIN {$wpdb->prefix}fi_voterc vr ON v.id = vr.vote_id
             WHERE vr.vote_id IS NULL"
        );
        
        if (!empty($votes_without_roll)) {
            $issues[] = [
                'type' => 'votes_without_roll',
                'severity' => 'medium',
                'message' => 'Votes without roll calls found',
                'count' => count($votes_without_roll),
                'data' => $votes_without_roll,
                'fixable' => false
            ];
        }
        
        // Check for duplicate slugs in same session
        $duplicate_slugs = $wpdb->get_results(
            "SELECT session_id, slug, COUNT(*) as count
             FROM {$wpdb->prefix}fi_votes
             GROUP BY session_id, slug
             HAVING COUNT(*) > 1"
        );
        
        if (!empty($duplicate_slugs)) {
            $issues[] = [
                'type' => 'duplicate_vote_slugs',
                'severity' => 'medium',
                'message' => 'Duplicate vote slugs in same session found',
                'count' => count($duplicate_slugs),
                'data' => $duplicate_slugs,
                'fixable' => true
            ];
        }
        
        return $issues;
    }

    /**
     * Validate roll calls
     */
    public static function validate_roll_calls(): array {
        $issues = [];
        
        global $wpdb;
        
        // Check for orphaned roll calls
        // Optimized: Use NOT EXISTS instead of LEFT JOIN for better performance
        // Also limit to just count if we only need to know if issues exist
        $orphaned_roll_calls = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT vr.id, vr.vote_id, vr.legislator_id
                 FROM {$wpdb->prefix}fi_voterc vr
                 WHERE NOT EXISTS (
                     SELECT 1 FROM {$wpdb->prefix}fi_votes v 
                     WHERE v.id = vr.vote_id
                 )
                 LIMIT %d",
                100 // Limit results to avoid memory issues with large datasets
            )
        );
        
        if (!empty($orphaned_roll_calls)) {
            $issues[] = [
                'type' => 'orphaned_roll_calls',
                'severity' => 'high',
                'message' => 'Roll calls without votes found',
                'count' => count($orphaned_roll_calls),
                'data' => $orphaned_roll_calls,
                'fixable' => true
            ];
        }
        
        // Check for roll calls with invalid legislators
        $invalid_legislator_roll_calls = $wpdb->get_results(
            "SELECT vr.id, vr.vote_id, vr.legislator_id
             FROM {$wpdb->prefix}fi_voterc vr
             LEFT JOIN {$wpdb->prefix}fi_legislators l ON vr.legislator_id = l.id
             WHERE l.id IS NULL"
        );
        
        if (!empty($invalid_legislator_roll_calls)) {
            $issues[] = [
                'type' => 'invalid_legislator_roll_calls',
                'severity' => 'high',
                'message' => 'Roll calls with invalid legislators found',
                'count' => count($invalid_legislator_roll_calls),
                'data' => $invalid_legislator_roll_calls,
                'fixable' => true
            ];
        }
        
        // Check for duplicate roll calls
        $duplicate_roll_calls = $wpdb->get_results(
            "SELECT vote_id, legislator_id, COUNT(*) as count
             FROM {$wpdb->prefix}fi_voterc
             GROUP BY vote_id, legislator_id
             HAVING COUNT(*) > 1"
        );
        
        if (!empty($duplicate_roll_calls)) {
            $issues[] = [
                'type' => 'duplicate_roll_calls',
                'severity' => 'medium',
                'message' => 'Duplicate roll calls found',
                'count' => count($duplicate_roll_calls),
                'data' => $duplicate_roll_calls,
                'fixable' => true
            ];
        }
        
        return $issues;
    }

    /**
     * Validate scores
     */
    public static function validate_scores(): array {
        $issues = [];
        
        global $wpdb;
        
        // Check for legislator sessions without legislators
        $orphaned_sessions = $wpdb->get_results(
            "SELECT ls.id, ls.legislator_id, ls.session_id
             FROM {$wpdb->prefix}fi_legislator_sessions ls
             LEFT JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
             WHERE l.id IS NULL"
        );
        
        if (!empty($orphaned_sessions)) {
            $issues[] = [
                'type' => 'orphaned_sessions',
                'severity' => 'high',
                'message' => 'Legislator sessions without legislators found',
                'count' => count($orphaned_sessions),
                'data' => $orphaned_sessions,
                'fixable' => true
            ];
        }
        
        // Check for legislator sessions without sessions
        $sessions_without_sessions = $wpdb->get_results(
            "SELECT ls.id, ls.legislator_id, ls.session_id
             FROM {$wpdb->prefix}fi_legislator_sessions ls
             LEFT JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
             WHERE s.id IS NULL"
        );
        
        if (!empty($sessions_without_sessions)) {
            $issues[] = [
                'type' => 'sessions_without_sessions',
                'severity' => 'high',
                'message' => 'Legislator sessions without sessions found',
                'count' => count($sessions_without_sessions),
                'data' => $sessions_without_sessions,
                'fixable' => true
            ];
        }
        
        // Check for legislators without scores
        $legislators_without_scores = $wpdb->get_results(
            "SELECT l.id, l.display_name, s.name as session_name
             FROM {$wpdb->prefix}fi_legislators l
             INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
             INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
             WHERE ls.score IS NULL"
        );
        
        if (!empty($legislators_without_scores)) {
            $issues[] = [
                'type' => 'legislators_without_scores',
                'severity' => 'medium',
                'message' => 'Legislators without scores found',
                'count' => count($legislators_without_scores),
                'data' => $legislators_without_scores,
                'fixable' => true
            ];
        }
        
        return $issues;
    }

    /**
     * Validate reports
     */
    public static function validate_reports(): array {
        $issues = [];
        
        global $wpdb;
        
        // Check for reports without sessions
        $orphaned_reports = $wpdb->get_results(
            "SELECT r.id, r.slug, r.gov
             FROM {$wpdb->prefix}fi_reports r
             LEFT JOIN {$wpdb->prefix}fi_sessions s ON r.session_id = s.id
             WHERE s.id IS NULL"
        );
        
        if (!empty($orphaned_reports)) {
            $issues[] = [
                'type' => 'orphaned_reports',
                'severity' => 'medium',
                'message' => 'Reports without sessions found',
                'count' => count($orphaned_reports),
                'data' => $orphaned_reports,
                'fixable' => true
            ];
        }
        
        // Check for invalid JSON in reports
        $invalid_json_reports = $wpdb->get_results(
            "SELECT id, slug, payload_json
             FROM {$wpdb->prefix}fi_reports
             WHERE payload_json IS NOT NULL AND payload_json != ''"
        );
        
        foreach ($invalid_json_reports as $report) {
            $payload = json_decode($report->payload_json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $issues[] = [
                    'type' => 'invalid_json_reports',
                    'severity' => 'medium',
                    'message' => 'Reports with invalid JSON found',
                    'count' => 1,
                    'data' => [$report],
                    'fixable' => true
                ];
            }
        }
        
        return $issues;
    }

    /**
     * Validate user lists
     */
    public static function validate_user_lists(): array {
        $issues = [];
        
        global $wpdb;
        
        // Check for lists without users
        $orphaned_lists = $wpdb->get_results(
            "SELECT uc.id, uc.name, uc.slug
             FROM {$wpdb->prefix}fi_user_lists uc
             LEFT JOIN {$wpdb->base_prefix}users u ON uc.user_id = u.ID
             WHERE u.ID IS NULL"
        );
        
        if (!empty($orphaned_lists)) {
            $issues[] = [
                'type' => 'orphaned_lists',
                'severity' => 'medium',
                'message' => 'Lists without users found',
                'count' => count($orphaned_lists),
                'data' => $orphaned_lists,
                'fixable' => true
            ];
        }
        
        // Check for invalid JSON in lists
        $invalid_json_lists = $wpdb->get_results(
            "SELECT id, name, slug, legislators
             FROM {$wpdb->prefix}fi_user_lists
             WHERE legislators IS NOT NULL AND legislators != ''"
        );
        
        foreach ($invalid_json_lists as $list) {
            $legislators = json_decode($list->legislators, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $issues[] = [
                    'type' => 'invalid_json_lists',
                    'severity' => 'medium',
                    'message' => 'Lists with invalid JSON found',
                    'count' => 1,
                    'data' => [$list],
                    'fixable' => true
                ];
            }
        }
        
        return $issues;
    }

    /**
     * Fix data issues
     */
    public static function fix_data_issues(array $issue_types): int {
        $fixed = 0;
        
        foreach ($issue_types as $issue_type) {
            switch ($issue_type) {
                case 'orphaned_legislators':
                    $fixed += self::fix_orphaned_legislators();
                    break;
                case 'duplicate_slugs':
                    $fixed += self::fix_duplicate_slugs();
                    break;
                case 'orphaned_legislator_sessions':
                    $fixed += self::fix_orphaned_legislator_sessions();
                    break;
                case 'orphaned_votes':
                    $fixed += self::fix_orphaned_votes();
                    break;
                case 'orphaned_roll_calls':
                    $fixed += self::fix_orphaned_roll_calls();
                    break;
                case 'orphaned_scores':
                    $fixed += self::fix_orphaned_scores();
                    break;
                case 'orphaned_reports':
                    $fixed += self::fix_orphaned_reports();
                    break;
                case 'orphaned_lists':
                    $fixed += self::fix_orphaned_lists();
                    break;
            }
        }
        
        return $fixed;
    }

    /**
     * Fix orphaned legislators
     */
    private static function fix_orphaned_legislators(): int {
        global $wpdb;
        
        return $wpdb->query(
            "DELETE l FROM {$wpdb->prefix}fi_legislators l
             LEFT JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
             WHERE ls.legislator_id IS NULL"
        );
    }

    /**
     * Fix duplicate slugs (removed - slug field no longer exists)
     * Legislators now use ID as the slug identifier
     */
    private static function fix_duplicate_slugs(): int {
        // This function is no longer needed as legislators use ID as slug
        return 0;
    }

    /**
     * Fix orphaned legislator sessions
     */
    private static function fix_orphaned_legislator_sessions(): int {
        global $wpdb;
        
        return $wpdb->query(
            "DELETE ls FROM {$wpdb->prefix}fi_legislator_sessions ls
             LEFT JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
             WHERE l.id IS NULL"
        );
    }

    /**
     * Fix orphaned votes
     */
    private static function fix_orphaned_votes(): int {
        global $wpdb;
        
        return $wpdb->query(
            "DELETE v FROM {$wpdb->prefix}fi_votes v
             LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
             WHERE s.id IS NULL"
        );
    }

    /**
     * Fix orphaned roll calls
     */
    private static function fix_orphaned_roll_calls(): int {
        global $wpdb;
        
        return $wpdb->query(
            "DELETE vr FROM {$wpdb->prefix}fi_voterc vr
             LEFT JOIN {$wpdb->prefix}fi_votes v ON vr.vote_id = v.id
             WHERE v.id IS NULL"
        );
    }

    /**
     * Fix orphaned legislator sessions
     */
    private static function fix_orphaned_scores(): int {
        global $wpdb;
        
        return $wpdb->query(
            "DELETE ls FROM {$wpdb->prefix}fi_legislator_sessions ls
             LEFT JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
             WHERE l.id IS NULL"
        );
    }

    /**
     * Fix orphaned reports
     */
    private static function fix_orphaned_reports(): int {
        global $wpdb;
        
        return $wpdb->query(
            "DELETE r FROM {$wpdb->prefix}fi_reports r
             LEFT JOIN {$wpdb->prefix}fi_sessions s ON r.session_id = s.id
             WHERE s.id IS NULL"
        );
    }

    /**
     * Fix orphaned lists
     */
    private static function fix_orphaned_lists(): int {
        global $wpdb;
        
        return $wpdb->query(
            "DELETE uc FROM {$wpdb->prefix}fi_user_lists uc
             LEFT JOIN {$wpdb->prefix}users u ON uc.user_id = u.ID
             WHERE u.ID IS NULL"
        );
    }

    /**
     * Show validation notices in admin
     * 
     * NOTE: Validation is disabled by default to avoid performance issues.
     * Validation now only runs on-demand via AJAX or on specific admin pages.
     * To re-enable automatic validation, uncomment the code below.
     */
    public static function show_validation_notices(): void {
        // Validation disabled - notices are commented out and validation runs on every page
        // which causes performance issues. Use AJAX endpoint or specific pages instead.
        return;
        
        /*
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Only run validation on specific pages to avoid performance issues
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['freedom-index_page_fi-dashboard', 'freedom-index_page_fi-import'], true)) {
            return;
        }
        
        $issues = self::validate_all();
        $high_severity_issues = array_filter($issues, function($issue) {
            return $issue['severity'] === 'high';
        });
        
        if (!empty($high_severity_issues)) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong>Freedom Index Data Issues Found:</strong>
                    <?php echo count($high_severity_issues); ?> high-severity issues detected.
                    <a href="<?php echo esc_url(URLs::get_admin_url('fi-import', ['action' => 'validate'])); ?>">
                        View Details
                    </a>
                </p>
            </div>
            <?php
        }
        */
    }

    /**
     * AJAX handler for data validation
     */
    public static function ajax_validate_data(): void {
        check_ajax_referer('fi_admin_nonce', 'nonce');
        
        if (!current_user_can(FI_CAP_MANAGE)) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $issues = self::validate_all();
        
        wp_send_json_success($issues);
    }

    /**
     * AJAX handler for fixing data issues
     */
    public static function ajax_fix_data_issues(): void {
        check_ajax_referer('fi_admin_nonce', 'nonce');
        
        if (!current_user_can(FI_CAP_MANAGE)) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $issue_types = array_map('sanitize_text_field', $_POST['issue_types'] ?? []);
        
        $fixed = self::fix_data_issues($issue_types);
        
        wp_send_json_success([
            'fixed' => $fixed,
            'message' => "Fixed {$fixed} data issues"
        ]);
    }

    /**
     * Get validation summary
     */
    public static function get_validation_summary(): array {
        $issues = self::validate_all();
        
        $summary = [
            'total_issues' => count($issues),
            'high_severity' => 0,
            'medium_severity' => 0,
            'low_severity' => 0,
            'fixable_issues' => 0,
            'by_type' => []
        ];
        
        foreach ($issues as $issue) {
            $summary[$issue['severity'] . '_severity']++;
            
            if ($issue['fixable']) {
                $summary['fixable_issues']++;
            }
            
            if (!isset($summary['by_type'][$issue['type']])) {
                $summary['by_type'][$issue['type']] = 0;
            }
            $summary['by_type'][$issue['type']]++;
        }
        
        return $summary;
    }
}

// Initialize data validation
DataValidation::init();

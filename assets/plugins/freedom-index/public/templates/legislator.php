<?php
/**
 * Single Legislator Template - Optimized for Mobile
 * 
 * Strategy:
 * 1. Load header data immediately (fast query)
 * 2. Defer votes section via HTMX (lazy load)
 * 3. Sidebar filters render with HTMX triggers
 */

if (!defined('ABSPATH')) exit;

// Get legislator ID from query var
$legislator_id = (int) (get_query_var('fi_legislator_id') ?: 0);
if (!$legislator_id) {
    wp_redirect(home_url('/legislators/'));
    exit;
}

// Get current filter params from URL (for HTMX requests)
$current_session_id = (int) (get_query_var('fi_session_id') ?: 0);
$current_report_id = (int) (get_query_var('fi_report_id') ?: 0);
$current_tag_id = (int) (get_query_var('fi_tag_id') ?: get_query_var('issue_id') ?: 0);

// FAST: Get legislator with sessions for header (single query)
$legislator = fi_legislator_get_with_sessions($legislator_id);
if (!$legislator) {
    wp_redirect(home_url('/legislators/'));
    exit;
}

// Build base URL
/* Canonical URL: Our legislator pages generate dozens of different URLs based on session, report, and issue parameters, but that's dilluting the SEO value of the page.
The canonical URL is the original legislator page URL without any session, report, or issue parameters, and is used by search engines to determine the "true" URL of the page.
Social media shares must be able to share the exact page vairant being viewed, not just the base legislator page.
*/
$base_url = home_url("/legislator/{$legislator_id}/");

// Extract chamber for filters
$chamber = $legislator->chamber ?? '';
$sessions = $legislator->sessions ?? [];

// Determine current session (for header score display)
if (!$current_session_id && !empty($sessions)) {
    usort($sessions, function($a, $b) {
        return strtotime($b->date_end ?? '1970-01-01') - strtotime($a->date_end ?? '1970-01-01');
    });
    $current_session_id = (int) ($sessions[0]->session_id ?? 0);
}

// Find current session data for score display
$current_session = null;
foreach ($sessions as $session) {
    if ((int) $session->session_id === $current_session_id) {
        $current_session = $session;
        break;
    }
}

// LAZY: Votes, reports, tags loaded via HTMX after page render
// These are NOT loaded here - they come from legislator-votes.php via HTMX

// Prepare vote query args
$vote_args = [
    'legislator_id' => $legislator_id,
    'limit' => $votes_per_page,
    'offset' => $votes_offset,
    'orderby' => 'date_voted',
    'order' => 'DESC',
];

// Apply filters
if ($current_session_id) {
    $vote_args['session_id'] = $current_session_id;
}
if ($current_tag_id) {
    $vote_args['tag_id'] = $current_tag_id;
}

// Get votes using Votes class (supports pagination)
$votes_data = [];
$has_more_votes = false;

if ($chamber) {
    // Get total count for pagination
    $total_votes = fi_votes_get_by_legislator($legislator_id, array_merge($vote_args, ['count_only' => true]));
    $total_count = is_int($total_votes) ? $total_votes : 0;
    
    // Get paginated votes
    $votes_data = fi_votes_get_by_legislator($legislator_id, $vote_args);
    $votes_data = is_array($votes_data) ? $votes_data : [];
    
    // Check if there are more votes
    $has_more_votes = ($votes_offset + count($votes_data)) < $total_count;
}

// If filtering by report, filter the votes
if ($current_report_id && !empty($votes_data)) {
    // Get report data to find vote IDs
    $report_vote_ids = [];
    foreach ($reports as $report) {
        if ((int) $report->id === $current_report_id) {
            $payload = json_decode($report->payload_json ?? '{}', true);
            $key = ($chamber === 'S') ? 'votes_s' : 'votes_h';
            $report_vote_ids = array_map('intval', (array) ($payload[$key] ?? []));
            break;
        }
    }
    
    // Filter votes to only those in the report
    if (!empty($report_vote_ids)) {
        $votes_data = array_filter($votes_data, function($vote) use ($report_vote_ids) {
            return in_array((int) ($vote->id ?? 0), $report_vote_ids);
        });
    }
}

// Build page data
$page_title = ($legislator->display_name ?? 'Legislator') . ' | Freedom Index';
$gov = $legislator->gov ?? 'US';
$gov_slug = strtolower($gov);

// Get legislator meta (TODO: rebuild meta function)
// $meta = fi_legislator_get_all_meta($legislator);

// Contact info - use direct legislator data
$contact = [
    'website' => $legislator->website ?? '',
    'phone' => $legislator->phone ?? '',
    'email' => $legislator->email ?? '',
    'office' => $legislator->address ?? '',
];

// Get image
$image_html = fi_legislator_image(
    $legislator->image_id ?? 0, 
    $legislator->session_image_id ?? null,
    [
        'size' => [200, 250],
        'crop' => true,
        'alt' => $legislator->display_name ?? '', 
        'class' => 'img-fluid rounded-4 shadow',
    ]
);

// Score data
$freedom_score = $legislator->freedom_score ?? null;
$current_session_score = $current_session->score ?? null;

// Determine if this is an HTMX request
$is_htmx = !empty($_SERVER['HTTP_HX_REQUEST']);

// If HTMX request for votes only, render partial and exit
if ($is_htmx && isset($_GET['votes_only'])) {
    fi_get_public_template('legislator-votes-list');
    exit;
}

// SEO Meta Tags
$description = sprintf(
    '%s (%s, %s) - Freedom Score: %s%%. View voting record, scores, and reports.',
    $legislator->display_name ?? '',
    $legislator->chamber_label ?? '',
    $legislator->party_name ?? '',
    $freedom_score ?? 'N/A'
);

fi_seo_tags([
    'title' => $page_title,
    'description' => $description,
    'canonical' => $base_url,
    'robots' => 'index, follow',
    'og' => [
        'og:title' => $page_title,
        'og:description' => $description,
        'og:url' => $base_url,
        'og:type' => 'profile',
        'og:image' => $legislator->image_url ?? '',
    ],
    'twitter' => [
        'twitter:card' => 'summary',
        'twitter:title' => $page_title,
        'twitter:description' => $description,
        'twitter:image' => $legislator->image_url ?? '',
    ],
]);

// Get current URL for print button
$current_url = home_url(add_query_arg([]));

get_header();

// Include template partials - pass required variables
include __DIR__ . '/legislator-header-new.php';
include __DIR__ . '/legislator-votes.php';

// Modals
fi_get_public_template('legislator-modals');

// HTMX and scripts
add_action('wp_footer', function() use ($legislator_id, $base_url) {
    ?>
    <script src="https://unpkg.com/htmx.org@1.9.12" integrity="sha384-ujb1lZYygJmzgSwoxRggbCHcjc0rB2XoQrxeTUQyRjrOnlCoYta87iKBWq3EsdM2" crossorigin="anonymous"></script>
    <script>
    (function() {
        // Configuration
        window.FI_LEGISLATOR = {
            legislatorId: <?php echo (int) $legislator_id; ?>,
            baseUrl: '<?php echo esc_url($base_url); ?>',
            perPage: 24,
            currentOffset: 24
        };
        
        // Load more handler (non-HTMX fallback)
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.fi-load-more-votes');
            if (!btn) return;
            
            e.preventDefault();
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
            
            const url = new URL(btn.href);
            url.searchParams.append('votes_only', '1');
            url.searchParams.append('htmx', '1');
            
            fetch(url.toString(), {
                headers: { 'HX-Request': 'true' }
            })
            .then(r => r.text())
            .then(html => {
                const container = document.getElementById('fi-votes-container');
                const temp = document.createElement('div');
                temp.innerHTML = html;
                
                // Append new votes
                const newVotes = temp.querySelectorAll('.fi-vote-card');
                newVotes.forEach(vote => container.appendChild(vote));
                
                // Update or remove load more button
                const newBtn = temp.querySelector('.fi-load-more-wrapper');
                const oldWrapper = document.querySelector('.fi-load-more-wrapper');
                if (newBtn && oldWrapper) {
                    oldWrapper.outerHTML = newBtn.outerHTML;
                } else if (oldWrapper) {
                    oldWrapper.remove();
                }
                
                // Update offset
                window.FI_LEGISLATOR.currentOffset += newVotes.length;
            })
            .catch(err => {
                console.error('Load more failed:', err);
                btn.disabled = false;
                btn.innerHTML = 'Load More Votes';
            });
        });
    })();
    </script>
    <?php
}, 100);

get_footer();

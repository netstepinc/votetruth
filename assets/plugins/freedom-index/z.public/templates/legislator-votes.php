<?php
/**
 * Legislator Votes Section
 * Shows filters and vote list with lazy loading
 */
if (!defined('ABSPATH')) exit;

// Calculate base URL for this section
$votes_base_url = $base_url;
if ($current_session_id) {
    $votes_base_url .= "session/{$current_session_id}/";
}
?>
<!-- Votes Section -->
<div id="fi-votes-section" class="container-xl py-4">
    <div class="row g-4">
        <!-- Sidebar Navigation -->
        <div class="col-12 col-lg-3">
            <!-- Session Selector -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Sessions</h5>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($sessions as $session): 
                        $session_id = (int) ($session->session_id ?? 0);
                        $session_score = $session->score ?? null;
                        $is_active = $session_id === $current_session_id;
                        $session_url = $base_url . "session/{$session_id}/";
                    ?>
                        <a href="<?php echo esc_url($session_url); ?>" 
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $is_active ? 'active' : ''; ?>"
                           hx-get="<?php echo esc_url($session_url); ?>?votes_only=1"
                           hx-target="#fi-votes-container-wrapper"
                           hx-push-url="true"
                           hx-indicator="#fi-votes-loading">
                            <span><?php echo esc_html($session->session_name ?? ''); ?></span>
                            <?php if ($session_score !== null): ?>
                                <span class="badge <?php echo $is_active ? 'bg-white text-dark' : fi_score_class($session_score, 'bg'); ?>">
                                    <?php echo (int) $session_score; ?>%
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Report Filter (only if we have reports for current session) -->
            <?php if (!empty($reports) && $current_session_id): ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="bi bi-journal-text me-2"></i>Reports</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="<?php echo esc_url($votes_base_url); ?>" 
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo !$current_report_id ? 'active' : ''; ?>"
                           hx-get="<?php echo esc_url($votes_base_url); ?>?votes_only=1"
                           hx-target="#fi-votes-container-wrapper"
                           hx-push-url="true">
                            <span>All Votes</span>
                            <span class="badge <?php echo !$current_report_id ? 'bg-white text-dark' : 'bg-secondary'; ?>">
                                All
                            </span>
                        </a>
                        
                        <?php foreach ($reports as $report): 
                            $report_id = (int) $report->id;
                            $report_url = $votes_base_url . "report/{$report_id}/";
                            $is_active = $report_id === $current_report_id;
                        ?>
                            <a href="<?php echo esc_url($report_url); ?>" 
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $is_active ? 'active' : ''; ?>"
                               hx-get="<?php echo esc_url($report_url); ?>?votes_only=1"
                               hx-target="#fi-votes-container-wrapper"
                               hx-push-url="true"
                               hx-indicator="#fi-votes-loading">
                                <span class="text-truncate me-2" style="max-width: 150px;">
                                    <?php echo esc_html($report->title ?? 'Report'); ?>
                                </span>
                                <?php if ($is_active): ?>
                                    <span class="badge bg-white text-dark">Active</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Issue/Tag Filter -->
            <?php if (!empty($tags)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="bi bi-tags me-2"></i>Issues</h5>
                    </div>
                    <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                        <a href="<?php echo esc_url($votes_base_url); ?>" 
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo !$current_tag_id ? 'active' : ''; ?>"
                           hx-get="<?php echo esc_url($votes_base_url); ?>?votes_only=1"
                           hx-target="#fi-votes-container-wrapper"
                           hx-push-url="true">
                            <span>All Issues</span>
                        </a>
                        
                        <?php foreach ($tags as $tag): 
                            $tag_id = (int) $tag->id;
                            $tag_url = $votes_base_url . "issue/{$tag_id}/";
                            $is_active = $tag_id === $current_tag_id;
                            $vote_count = $tag->vote_count ?? 0;
                        ?>
                            <a href="<?php echo esc_url($tag_url); ?>" 
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $is_active ? 'active' : ''; ?>"
                               hx-get="<?php echo esc_url($tag_url); ?>?votes_only=1"
                               hx-target="#fi-votes-container-wrapper"
                               hx-push-url="true"
                               hx-indicator="#fi-votes-loading"
                               title="<?php echo esc_attr($tag->name ?? ''); ?>">
                                <span class="text-truncate me-2" style="max-width: 140px;">
                                    <?php echo esc_html($tag->name ?? ''); ?>
                                </span>
                                <span class="badge <?php echo $is_active ? 'bg-white text-dark' : 'bg-secondary'; ?>">
                                    <?php echo (int) $vote_count; ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Main Content -->
        <div class="col-12 col-lg-9">
            <!-- Section Header -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="h4 mb-1">
                        <?php if ($current_report_id): ?>
                            <?php 
                            $report_title = '';
                            foreach ($reports as $r) {
                                if ((int) $r->id === $current_report_id) {
                                    $report_title = $r->title;
                                    break;
                                }
                            }
                            ?>
                            Report: <?php echo esc_html($report_title); ?>
                        <?php elseif ($current_tag_id): ?>
                            <?php 
                            $tag_name = '';
                            foreach ($tags as $t) {
                                if ((int) $t->id === $current_tag_id) {
                                    $tag_name = $t->name;
                                    break;
                                }
                            }
                            ?>
                            Issue: <?php echo esc_html($tag_name); ?>
                        <?php else: ?>
                            Voting Record
                        <?php endif; ?>
                    </h2>
                    <p class="text-muted mb-0">
                        <?php if ($current_session): ?>
                            <?php echo esc_html($current_session->session_name ?? ''); ?> 
                            (<?php echo count($votes_data); ?> of <?php echo (int) ($total_count ?? count($votes_data)); ?> votes)
                        <?php else: ?>
                            Showing all votes
                        <?php endif; ?>
                    </p>
                </div>
                
                <!-- Search (future enhancement) -->
                <div class="input-group" style="max-width: 300px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" 
                           class="form-control" 
                           placeholder="Search votes..."
                           disabled
                           title="Search coming soon">
                </div>
            </div>
            
            <!-- HTMX Loading Indicator -->
            <div id="fi-votes-loading" class="htmx-indicator text-center py-4 d-none">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading votes...</span>
                </div>
                <p class="text-muted mt-2">Loading votes...</p>
            </div>
            
            <!-- Votes Container Wrapper (HTMX target) -->
            <div id="fi-votes-container-wrapper">
                <?php fi_get_template('legislator-votes-list'); ?>
            </div>
        </div>
    </div>
</div>

<!-- HTMX Styles -->
<style>
.htmx-request#fi-votes-container-wrapper {
    opacity: 0.6;
    transition: opacity 0.2s;
}
.htmx-request#fi-votes-loading {
    display: block !important;
}
</style>

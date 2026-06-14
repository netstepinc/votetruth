<?php
/**
 * Legislator Votes List Partial
 * Shows vote cards with Load More button
 * Used for both full page render and HTMX requests
 */
if (!defined('ABSPATH')) exit;

// Determine if we're in an HTMX context
$is_htmx_context = !empty($is_htmx) || !empty($_SERVER['HTTP_HX_REQUEST']);
?>

<?php if (empty($votes_data)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        No votes found for the selected filters.
    </div>
<?php else: ?>
    <!-- Votes Container -->
    <div id="fi-votes-container" class="vstack gap-3">
        <?php foreach ($votes_data as $vote): 
            $vote_id = (int) ($vote['id'] ?? 0);
            $vote_url = home_url("/vote/{$vote_id}/");
            $meta = is_string($vote['meta'] ?? '') ? json_decode($vote['meta'], true) : ($vote['meta'] ?? []);
            $rollcall = $vote['rollcall'] ?? null;
            $cast = $rollcall['cast'] ?? 'X';
            $is_correct = ($cast === ($vote['constitutional'] ?? ''));
            $tags = $vote['tags'] ?? [];
        ?>
            <div class="card fi-vote-card border-0 shadow-sm overflow-hidden" data-vote-id="<?php echo $vote_id; ?>">
                <div class="card-body p-0">
                    <div class="row g-0">
                        <!-- Vote Indicator -->
                        <div class="col-auto">
                            <div class="h-100 d-flex flex-column align-items-center justify-content-center p-3 
                                <?php echo $is_correct ? 'bg-success' : 'bg-danger'; ?>"
                                style="width: 60px;">
                                <span class="text-white fw-bold fs-4"><?php echo esc_html($cast); ?></span>
                                <small class="text-white-50">
                                    <?php echo $is_correct ? '✓' : '✗'; ?>
                                </small>
                            </div>
                        </div>
                        
                        <!-- Vote Content -->
                        <div class="col">
                            <div class="p-3">
                                <!-- Header -->
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                    <h5 class="card-title mb-0">
                                        <a href="<?php echo esc_url($vote_url); ?>" 
                                           class="text-decoration-none text-dark stretched-link">
                                            <?php echo esc_html($vote['title'] ?? 'Untitled Vote'); ?>
                                        </a>
                                    </h5>
                                    
                                    <?php if ($vote['bill_number']): ?>
                                        <span class="badge bg-light text-dark border">
                                            <?php echo esc_html($vote['bill_number']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Description -->
                                <?php 
                                $desc = $meta['description_short'] ?? $meta['text_scorecard'] ?? '';
                                if ($desc): 
                                ?>
                                    <p class="card-text text-muted mb-3">
                                        <?php echo esc_html($desc); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <!-- Footer -->
                                <div class="d-flex flex-wrap align-items-center gap-3 small text-muted">
                                    <span>
                                        <i class="bi bi-calendar3 me-1"></i>
                                        <?php echo esc_html(date('M j, Y', strtotime($vote['date_voted'] ?? 'now'))); ?>
                                    </span>
                                    
                                    <?php if ($vote['chamber']): ?>
                                        <span>
                                            <i class="bi bi-building me-1"></i>
                                            <?php echo esc_html(fi_chamber_title($gov, $vote['chamber'])); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <!-- Tags -->
                                    <?php if (!empty($tags)): ?>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                                                <span class="badge bg-light text-secondary">
                                                    <?php echo esc_html($tag['name'] ?? ''); ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if (count($tags) > 3): ?>
                                                <span class="badge bg-light text-secondary">
                                                    +<?php echo count($tags) - 3; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Actions -->
                                    <div class="ms-auto d-flex gap-2">
                                        <?php if (!empty($meta['url_source'])): ?>
                                            <a href="<?php echo esc_url($meta['url_source']); ?>" 
                                               target="_blank" 
                                               rel="noopener noreferrer"
                                               class="btn btn-sm btn-outline-primary"
                                               onclick="event.stopPropagation();">
                                                <i class="bi bi-box-arrow-up-right me-1"></i>
                                                Source
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($meta['url_rollcall'])): ?>
                                            <a href="<?php echo esc_url($meta['url_rollcall']); ?>" 
                                               target="_blank" 
                                               rel="noopener noreferrer"
                                               class="btn btn-sm btn-outline-secondary"
                                               onclick="event.stopPropagation();">
                                                <i class="bi bi-journal-text me-1"></i>
                                                Roll Call
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Load More -->
    <?php if ($has_more_votes): 
        $next_offset = $votes_offset + $votes_per_page;
        $load_more_url = add_query_arg([
            'offset' => $next_offset,
            'load_more' => 1,
        ], $current_url);
    ?>
        <div class="fi-load-more-wrapper text-center mt-4">
            <a href="<?php echo esc_url($load_more_url); ?>" 
               class="btn btn-outline-primary btn-lg fi-load-more-votes"
               data-offset="<?php echo (int) $next_offset; ?>">
                Load More Votes
                <span class="badge bg-secondary ms-2">
                    <?php echo (int) ($total_count - $votes_offset - count($votes_data)); ?> remaining
                </span>
            </a>
        </div>
    <?php else: ?>
        <div class="text-center mt-4 text-muted">
            <small>Showing all <?php echo count($votes_data); ?> votes</small>
        </div>
    <?php endif; ?>
<?php endif; ?>

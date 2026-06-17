<?php
/**
 * Legislators Grid Partial
 * Renders ALL legislators for SEO, paginates via JS for UX
 *
 * Available global variables:
 * - $fi_legislators: Array of legislator arrays (ALL of them for SEO)
 * - $fi_gov: Government code
 * - $fi_has_more: Boolean - more items available
 * - $fi_offset: Current offset for next load
 * - $fi_limit: Items per page for display
 * - $fi_total_count: Total count of legislators
 */
if (!defined('ABSPATH')) exit;

global $fi_legislators, $fi_gov, $fi_has_more, $fi_offset, $fi_limit, $fi_total_count;

$all_legislators = $fi_legislators ?? [];
$gov = $fi_gov ?? 'US';
$total_count = $fi_total_count ?? count($all_legislators);
$limit = $fi_limit ?? 48;
$is_bot = fi_is_crawler();

// For crawlers: show all. For users: show first batch initially
$display_legislators = $is_bot ? $all_legislators : array_slice($all_legislators, 0, $limit);
$has_more = count($all_legislators) > $limit && !$is_bot;
?>

<?php if (!empty($all_legislators)): ?>
    <div class="row g-2" id="fi-legislators-grid" data-total="<?php echo $total_count; ?>">
        <?php foreach ($all_legislators as $index => $legislator):
            // Lazy load images beyond first 16
            $legislator['lazy_load'] = $index > 15;
            // Hide beyond initial batch (JS will handle pagination)
            $is_hidden = $index >= $limit && !$is_bot ? ' d-none fi-legislator-hidden' : '';
        ?>
            <div class="col-12 col-md-6 col-lg-4 col-xl-3 fi-legislator-card-wrapper<?php echo $is_hidden; ?>" data-index="<?php echo $index; ?>">
                <?php fi_get_public_template('legislators-card', [
                    'legislator' => $legislator,
                    'gov' => $gov,
                ]); ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if ($has_more): ?>
        <div class="text-center py-4" id="fi-load-more-container">
            <button 
                type="button"
                id="fi-load-more-btn"
                class="btn btn-outline-primary px-4"
                data-shown="<?php echo $limit; ?>"
                data-batch="48"
            >
                <span id="fi-load-more-text">Load More Legislators</span>
            </button>
            <div class="text-muted small mt-2">
                Showing <span id="fi-shown-count"><?php echo min($limit, $total_count); ?></span> of <?php echo $total_count; ?>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var btn = document.getElementById('fi-load-more-btn');
            if (!btn) return;
            
            btn.addEventListener('click', function() {
                var shown = parseInt(btn.dataset.shown);
                var batch = parseInt(btn.dataset.batch);
                var grid = document.getElementById('fi-legislators-grid');
                var hiddenCards = grid.querySelectorAll('.fi-legislator-hidden');
                var toShow = Math.min(batch, hiddenCards.length);
                
                for (var i = 0; i < toShow; i++) {
                    hiddenCards[i].classList.remove('d-none', 'fi-legislator-hidden');
                }
                
                shown += toShow;
                btn.dataset.shown = shown;
                document.getElementById('fi-shown-count').textContent = shown;
                
                // Hide button if no more cards
                if (hiddenCards.length <= toShow) {
                    document.getElementById('fi-load-more-container').innerHTML = 
                        '<div class="text-center mt-4 text-muted">Showing all <?php echo $total_count; ?> legislators</div>';
                }
            });
        });
        </script>
    <?php else: ?>
        <div class="text-center py-3 text-muted small">
            Showing all <?php echo $total_count; ?> legislators
        </div>
    <?php endif; ?>
    
<?php else: ?>
    <div class="alert alert-warning text-center py-5" id="fi-legislators-grid">
        <h5 class="mb-3">No Legislators Found</h5>
        <p class="mb-0">No legislators match your current filters. Try adjusting your search criteria.</p>
    </div>
<?php endif; ?>

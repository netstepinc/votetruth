<?php
/**
 * Legislator Card - Compact Layout
 * Clean, minimal card for grid display
 * 
 * @var object $legislator Legislator object
 * @var string $gov Government code
 */
if (!defined('ABSPATH')) exit;

// Build display name
$name = !empty($legislator->display_name) 
    ? $legislator->display_name 
    : trim(($legislator->first_name ?? '') . ' ' . ($legislator->last_name ?? ''));

// Get image HTML (with lazy loading)
$img_attrs = 'width="100" height="125" class="img-fluid rounded-2" alt="' . esc_attr($name) . '"';
if (!empty($legislator->lazy_load)) {
    $img_attrs .= ' loading="lazy" decoding="async"';
}

if (!empty($legislator->image_url)) {
    $image_html = '<img src="' . esc_url($legislator->image_url) . '" ' . $img_attrs . '>';
} elseif (!empty($legislator->image_id)) {
    $image_html = fi_legislator_image(
        $legislator->image_id,
        $legislator->session_image_id ?? null,
        [
            'size' => [100, 125],
            'crop' => true,
            'alt' => $name,
            'class' => 'img-fluid rounded-2',
        ]
    );
} else {
    $image_html = '<div class="bg-light rounded-2 d-flex align-items-center justify-content-center" style="width:100px;height:125px;">
        <i class="bi bi-person text-secondary"></i>
    </div>';
}

// Score
$score = $legislator->score ?? null;
$score_class = $score !== null ? fi_score_class($score) : '';

// Labels
$chamber_title = !empty($legislator->chamber) ? fi_chamber_title($gov, $legislator->chamber) : '';
$party_abbr = !empty($legislator->party) ? fi_party_abbr($legislator->party) : '';
$state = ($gov === 'US' && !empty($legislator->state)) ? $legislator->state : '';
$district = !empty($legislator->district_name) ? $legislator->district_name : ($legislator->district ?? '');

// Build URL
$url = $legislator->url ?? fi_get_legislator_url($legislator->id ?? 0);

// Compact district display
$district_short = '';
if ($district) {
    $district_short = is_numeric($district) ? 'D-' . $district : ($district === 'At Large' ? 'AL' : $district);
}
?>
<div class="h-100">
    <a href="<?php echo esc_url($url); ?>" class="text-decoration-none d-block h-100">
        <div class="card h-100 border-0 shadow-sm hover-shadow rounded-3 overflow-hidden">
            <div class="card-body p-2">
                <div class="d-flex gap-2">
                    <!-- Image -->
                    <div class="flex-shrink-0" style="width: 80px;">
                        <div style="aspect-ratio: 4/5; overflow: hidden;">
                            <?php echo $image_html; ?>
                        </div>
                    </div>
                    
                    <!-- Info -->
                    <div class="flex-grow-1 min-width-0" style="min-width: 0;">
                        <!-- Chamber -->
                        <?php if ($chamber_title): ?>
                            <div class="text-uppercase text-muted" style="font-size: 0.65rem; letter-spacing: 0.02em; line-height: 1.2;">
                                <?php echo esc_html($chamber_title); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Name -->
                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.9rem; line-height: 1.2;">
                            <?php echo esc_html($name); ?>
                        </div>
                        
                        <!-- Meta: Party | State | District -->
                        <div class="d-flex align-items-center gap-1 text-muted" style="font-size: 0.75rem; line-height: 1.3;">
                            <?php if ($party_abbr): ?>
                                <span class="fw-medium"><?php echo esc_html($party_abbr); ?></span>
                            <?php endif; ?>
                            <?php if ($party_abbr && $state): ?>
                                <span class="text-secondary">|</span>
                            <?php endif; ?>
                            <?php if ($state): ?>
                                <span><?php echo esc_html($state); ?></span>
                            <?php endif; ?>
                            <?php if (($party_abbr || $state) && $district_short): ?>
                                <span class="text-secondary">|</span>
                            <?php endif; ?>
                            <?php if ($district_short): ?>
                                <span><?php echo esc_html($district_short); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Score -->
                        <?php if ($score !== null): ?>
                            <div class="mt-1">
                                <span class="badge <?php echo esc_attr($score_class); ?>" style="font-size: 0.75rem; font-weight: 600;">
                                    <?php echo $score; ?>
                                </span>
                                <span class="text-muted" style="font-size: 0.65rem;">Freedom Score</span>
                            </div>
                        <?php else: ?>
                            <div class="mt-1">
                                <span class="badge bg-light text-dark border" style="font-size: 0.65rem;">No Score</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- View Report Link -->
            <div class="card-footer bg-white border-top-0 p-2 pt-0">
                <div class="text-center" style="font-size: 0.75rem;">
                    <span class="text-primary">View Report →</span>
                </div>
            </div>
        </div>
    </a>
</div>
<?php
// End of file

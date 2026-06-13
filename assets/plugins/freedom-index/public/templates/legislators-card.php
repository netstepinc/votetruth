<?php
/**
 * Legislator Card - Compact Grid Format
 * Full office titles, minimum 0.75rem text, no low-contrast
 *
 * @var array $legislator Legislator data array
 * @var string $gov Government code
 */
if (!defined('ABSPATH')) exit;

$name = $legislator['display_name'] ?? trim(($legislator['first_name'] ?? '') . ' ' . ($legislator['last_name'] ?? ''));

$image_html = fi_legislator_image(
    $legislator['image_id'] ?? null,
    $legislator['session_image_id'] ?? null,
    [
        'size' => [100, 125],
        'crop' => true,
        'alt' => $name,
        'class' => 'img-fluid',
        'image_url' => $legislator['image_url'] ?? '',
    ]
);

$score = $legislator['score'] ?? null;
$has_score = $score !== null && $score !== '';
$grade = $has_score ? fi_score_calculate_grade((float) $score) : null;

// Determine gov type
$official_gov = $legislator['gov'] ?? $gov ?? 'US';
$is_federal = $official_gov === 'US';

// Build full office title
$chamber_raw = strtolower($legislator['chamber'] ?? '');
$state = $legislator['state'] ?? '';
$state_name = $legislator['state_name'] ?? ($state ? fi_state_name($state) : '');

if (strpos($chamber_raw, 'senator') !== false || strpos($chamber_raw, 'senate') !== false) {
    $office_title = $is_federal 
        ? ($state ? "US Senator ({$state})" : 'US Senator')
        : ($state_name ? "{$state_name} State Senator" : 'State Senator');
} elseif (strpos($chamber_raw, 'representative') !== false || strpos($chamber_raw, 'house') !== false) {
    $office_title = $is_federal
        ? ($state ? "US Representative ({$state})" : 'US Representative')
        : ($state_name ? "{$state_name} State Representative" : 'State Representative');
} else {
    $office_title = $legislator['chamber_label'] ?? $legislator['chamber'] ?? '';
}

$party = $legislator['party'] ?? '';
$district = $legislator['district'] ?? '';
$district_display = $district ? "District {$district}" : ($legislator['district_name'] ?? '');

$url = fi_get_legislator_url($legislator['id'] ?? 0);
?>
<div class="h-100 legislator-card-wrap">
    <a href="<?php echo esc_url($url); ?>" class="text-decoration-none d-block h-100">
        <div class="card h-100 border-2 border-white rounded-3 overflow-hidden legislator-card">
            <div class="card-body p-3">
                <div class="d-flex gap-3">
                    <!-- Portrait -->
                    <div class="flex-shrink-0 legislator-portrait">
                        <div class="rounded-2 overflow-hidden bg-light">
                            <?php echo $image_html; ?>
                        </div>
                    </div>
                    
                    <!-- Info -->
                    <div class="flex-grow-1 min-width-0 d-flex flex-column" style="min-width: 0;">
                        <!-- Office Title + Party -->
                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                            <span class="office-title text-truncate">
                                <?php echo esc_html($office_title); ?>
                            </span>
                            <?php if ($party): ?>
                                <span class="party-badge"><?php echo esc_html($party); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Name -->
                        <h6 class="text-truncate mb-0 legislator-name">
                            <?php echo esc_html($name); ?>
                        </h6>
                        
                        <!-- District -->
                        <?php if ($district_display): ?>
                            <div class="text-truncate legislator-district">
                                <?php echo esc_html($district_display); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Score - pushed to bottom -->
                        <div class="mt-auto pt-2">
                            <?php if ($has_score): ?>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="grade-badge" data-grade="<?php echo esc_attr($grade); ?>">
                                        <?php echo esc_html($grade); ?>
                                    </span>
                                    <span class="score-num"><?php echo (int) $score; ?>%</span>
                                </div>
                            <?php else: ?>
                                <span class="no-score-badge">No Score</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- View Link -->
            <div class="card-footer bg-white border-top-0 px-3 pb-3 pt-0">
                <div class="view-link text-center">
                    View Report →
                </div>
            </div>
        </div>
    </a>
</div>

<style>
.legislator-card-wrap { container-type: inline-size; }
.legislator-card {
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.legislator-card:hover {
    border-color: #0d6efd !important;
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.15);
}
.legislator-portrait { width: 80px; }
.legislator-portrait > div { aspect-ratio: 4/5; }

/* Minimum 0.75rem (12px) for all text */
.office-title {
    font-size: 0.75rem;
    font-weight: 600;
    color: #333;
    line-height: 1.3;
}
.party-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #fff;
    background: #6c757d;
    flex-shrink: 0;
}
.legislator-name {
    font-size: 0.9375rem;
    font-weight: 700;
    color: #000;
    line-height: 1.2;
    letter-spacing: -0.01em;
}
.legislator-district {
    font-size: 0.75rem;
    color: #444;
    font-weight: 500;
    line-height: 1.3;
    margin-top: 2px;
}

/* Grade badges with high contrast */
.grade-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 24px;
    padding: 0 10px;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 800;
    color: #000;
    background: #e9ecef;
}
.grade-badge[data-grade="A"] { background: #d4edda; color: #155724; }
.grade-badge[data-grade="B"] { background: #d1ecf1; color: #0c5460; }
.grade-badge[data-grade="C"] { background: #fff3cd; color: #856404; }
.grade-badge[data-grade="D"] { background: #f8d7da; color: #721c24; }
.grade-badge[data-grade="F"] { background: #721c24; color: #fff; }

.score-num {
    font-size: 0.875rem;
    font-weight: 600;
    color: #333;
}
.no-score-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
    color: #555;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
}
.view-link {
    font-size: 0.875rem;
    font-weight: 600;
    color: #0d6efd;
}
.legislator-card:hover .view-link {
    color: #0a58ca;
}
</style>
<?php

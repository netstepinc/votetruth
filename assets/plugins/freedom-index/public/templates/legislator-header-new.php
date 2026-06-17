<?php
/**
 * Legislator Header Partial
 * 
 * Variables expected:
 * - $legislator: Legislator object with session data
 */

if (!isset($legislator) || !$legislator) {
    echo '<div class="alert alert-danger">Legislator data not available</div>';
    return;
}

// Extract data from flattened legislator object
$display_name = $legislator['display_name'] ?? '';
$party_name = $legislator['party_name'] ?? '';
$state_name = $legislator['state_name'] ?? '';
$district = $legislator['district'] ?? '';
$chamber_label = $legislator['chamber_label'] ?? '';
$chamber_title = $legislator['chamber_title'] ?? '';
$score         = $legislator['score'] ?? null;
$_current_session = $legislator['sessions'][0] ?? [];
$score_session = $_current_session['score_session'] ?? null;
$session_name  = $_current_session['session_name'] ?? '';
$image_id = $legislator['image_id'] ?? 0;

// Build page title
$page_title = sprintf(
    '%s (%s, %s)',
    $display_name,
    $chamber_label,
    $party_name
);

// Get image HTML
$image_html = fi_legislator_image(
    $image_id,
    null,
    [
        'size' => [200, 250],
        'crop' => true,
        'alt' => $display_name,
        'class' => 'img-fluid rounded-4 shadow',
    ]
);

// Base URL for sharing
$base_url = home_url('/legislator/' . $legislator['id'] . '/');
?>

<!-- Legislator Header -->
<section class="legislator-hero bg-primary text-white py-4">
    <div class="container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="<?php echo esc_url(home_url('/legislators/')); ?>" class="text-white-50">
                        All Legislators
                    </a>
                </li>
                <li class="breadcrumb-item active text-white" aria-current="page">
                    <?php echo esc_html($display_name); ?>
                </li>
            </ol>
        </nav>
        
        <div class="row align-items-center g-4">
            <!-- Photo -->
            <div class="col-12 col-md-3 col-lg-2 text-center">
                <div class="position-relative d-inline-block">
                    <?php echo $image_html; ?>
                    <?php if ($chamber_label): ?>
                        <span class="position-absolute bottom-0 end-0 badge bg-dark">
                            <?php echo esc_html($chamber_label); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Info -->
            <div class="col-12 col-md-5 col-lg-6">
                <h1 class="h2 mb-2"><?php echo esc_html($display_name); ?></h1>
                
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php if ($party_name): ?>
                        <span class="badge bg-light text-dark">
                            <?php echo esc_html($party_name); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($state_name): ?>
                        <span class="badge bg-light text-dark">
                            <?php echo esc_html($state_name); ?>
                            <?php if ($district): ?>
                                - District <?php echo esc_html($district); ?>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Scores -->
                <div class="d-flex gap-4 mb-3">
                    <?php if ($score !== null): ?>
                        <div class="text-center">
                            <div class="h4 mb-0"><?php echo esc_html($score); ?>%</div>
                            <small class="text-white-50">Freedom Score</small>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($score_session !== null): ?>
                        <div class="text-center">
                            <div class="h4 mb-0"><?php echo esc_html($score_session); ?>%</div>
                            <small class="text-white-50"><?php echo esc_html($session_name); ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="col-12 col-md-4 text-md-end">
                <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                    <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#contactModal">
                        <i class="bi bi-envelope"></i> Contact
                    </button>
                    <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#shareModal">
                        <i class="bi bi-share"></i> Share
                    </button>
                    <a href="?print=pdf" class="btn btn-success" target="_blank">
                        <i class="bi bi-printer"></i> Print
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

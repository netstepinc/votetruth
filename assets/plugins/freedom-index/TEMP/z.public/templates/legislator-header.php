<?php
/**
 * Legislator Header Partial
 * Optimized for mobile-first render with lean data structure
 * 
 * Expected: $legislator object from fi_legislator_get_with_sessions()
 */
if (!defined('ABSPATH')) exit;

// Extract data from legislator object
$display_name = $legislator->display_name ?? '';
$party = $legislator->party ?? '';
$party_name = $legislator->party_name ?? FI_PARTIES[$party] ?? $party;
$state = $legislator->state ?? '';
$state_name = $legislator->state_name ?? FI_GOVERNMENTS[$legislator->gov]['state_name'] ?? '';
$district = $legislator->district ?? '';
$chamber = $legislator->chamber ?? '';
$chamber_label = $legislator->chamber_label ?? FI_CHAMBERS[$chamber]['label'] ?? '';

// Get image from current session
$image_id = $legislator->image_id ?? null;
$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
$image_html = $image_url ? sprintf('<img src="%s" alt="%s" class="img-fluid rounded-3 shadow-sm" style="max-width: 200px; max-height: 250px; object-fit: cover;">', 
    esc_url($image_url), 
    esc_attr($display_name)
) : '<div class="bg-light rounded-3 d-flex align-items-center justify-content-center" style="width: 200px; height: 250px;"><i class="bi bi-person-fill text-secondary display-4"></i></div>';

// Get scores
$lifetime_score = null;
$current_session_score = null;
$sessions = $legislator->sessions ?? [];
if (!empty($sessions)) {
    // Most recent session has current score
    $current_session_score = $sessions[0]->session_score ?? null;
    // Calculate lifetime score from sessions
    $lifetime_score = $sessions[0]->lifetime_score ?? null;
}

// Build current URL for share/print
$current_url = home_url($_SERVER['REQUEST_URI'] ?? '');
?>
<!-- Legislator Header - Hero Section -->
<div id="fi-legislator-header" class="bg-primary text-white py-4">
    <div class="container-xl">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?php echo home_url('/legislators/'); ?>" class="text-white-50">All Legislators</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page"><?php echo esc_html($display_name); ?></li>
            </ol>
        </nav>
        
        <div class="row align-items-center g-4">
            <!-- Image - Mobile: full width, Desktop: left side -->
            <div class="col-12 col-md-3 col-lg-2 text-center text-md-start">
                <div class="position-relative d-inline-block">
                    <?php echo $image_html; ?>
                    <?php if ($chamber_label): ?>
                        <span class="badge bg-dark position-absolute bottom-0 end-0 mb-2 me-2">
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
                
                <?php if (!empty($legislator->website)): ?>
                    <a href="<?php echo esc_url($legislator->website); ?>" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="text-white-50 text-decoration-none small">
                        <i class="bi bi-globe me-1"></i>
                        <?php echo esc_html(parse_url($legislator->website, PHP_URL_HOST)); ?>
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Score & Actions -->
            <div class="col-12 col-md-4 text-center text-md-end">
                <!-- Score Display -->
                <?php if ($lifetime_score !== null): ?>
                    <div class="d-inline-block bg-white rounded-4 p-3 mb-3 text-dark shadow-sm">
                        <div class="d-flex align-items-center gap-3">
                            <div class="text-center">
                                <div class="display-4 fw-bold <?php echo fi_score_class($lifetime_score, 'text'); ?>">
                                    <?php echo (int) $lifetime_score; ?>
                                </div>
                                <div class="small text-muted">Lifetime</div>
                            </div>
                            
                            <?php if ($current_session_score !== null): ?>
                                <div class="vr bg-secondary"></div>
                                <div class="text-center">
                                    <div class="h3 fw-bold <?php echo fi_score_class($current_session_score, 'text'); ?>">
                                        <?php echo (int) $current_session_score; ?>
                                    </div>
                                    <div class="small text-muted">Current</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-end">
                    <button type="button" 
                            class="btn btn-light btn-sm" 
                            data-bs-toggle="modal" 
                            data-bs-target="#fi-contact-modal">
                        <i class="bi bi-envelope me-1"></i>
                        Contact
                    </button>
                    
                    <button type="button" 
                            class="btn btn-outline-light btn-sm" 
                            data-bs-toggle="modal" 
                            data-bs-target="#fi-share-modal">
                        <i class="bi bi-share me-1"></i>
                        Share
                    </button>
                    
                    <button type="button" 
                            class="btn btn-success btn-sm" 
                            data-bs-toggle="modal" 
                            data-bs-target="#fi-print-modal"
                            data-print-url="<?php echo esc_url($current_url); ?>">
                        <i class="bi bi-printer me-1"></i>
                        Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Back Navigation -->
<div class="bg-light border-bottom">
    <div class="container-xl py-2">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item">
                    <a href="<?php echo esc_url(home_url('/' . $gov_slug . '/legislators/')); ?>" class="text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i>
                        All Legislators
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?php echo esc_html($legislator->display_name ?? ''); ?>
                </li>
            </ol>
        </nav>
    </div>
</div>

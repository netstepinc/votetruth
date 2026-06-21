<?php
if (!defined('ABSPATH')) exit;

// Redirect if not logged in (before any output)
if (!is_user_logged_in()) {
	wp_safe_redirect(home_url('/account/'));
	exit;
}

get_header();
?>

<div class="fi-my-legislators">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1>My Elected Officials</h1>
                
                <?php
                $zip = sanitize_text_field($_GET['zip'] ?? '');
                $address = sanitize_text_field($_GET['address'] ?? '');
                
                if (empty($zip) && empty($address)): ?>
                    <div class="fi-address-lookup">
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h3>Find Your Elected Officials</h3>
                                        <form method="get" class="fi-lookup-form">
                                            <div class="mb-3">
                                                <label for="zip" class="form-label">ZIP Code</label>
                                                <input type="text" id="zip" name="zip" class="form-control" 
                                                       placeholder="Enter your ZIP code" maxlength="5" 
                                                       value="<?php echo esc_attr($zip); ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="address" class="form-label">Full Address (Optional)</label>
                                                <input type="text" id="address" name="address" class="form-control" 
                                                       placeholder="Street address, city, state" 
                                                       value="<?php echo esc_attr($address); ?>">
                                                <div class="form-text">We do not save this information</div>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">Find My Officials</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                <?php
                // Use Freedom Index integration with Geocod.io API
                $lookup_address = !empty($address) ? $address : $zip;
                
                if (!empty($lookup_address)) {
                    $officials = fi_lookup_legislators_by_address($lookup_address);
                    
                    if (!empty($officials)): ?>
                <div class="fi-officials-results">
                    <div class="row">
                        <?php foreach ($officials as $official): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card fi-official-card">
                                    <div class="card-body">
                                        <div class="fi-official-image">
                                            <?php if (!empty($official['photo_url'])): ?>
                                                <img src="<?php echo esc_url($official['photo_url']); ?>" 
                                                     alt="<?php echo esc_attr($official['name']); ?>" 
                                                     class="img-fluid rounded">
                                            <?php else: ?>
                                                <?php echo fi_legislator_image_placeholder($official['name']); ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <h5 class="card-title">
                                            <?php if (!empty($official['fi_legislator'])): ?>
                                                <a href="<?php echo esc_url(fi_get_legislator_url($official['fi_legislator']['id'] ?? 0, strtolower($official['fi_legislator']['gov'] ?? 'us'))); ?>">
                                                    <?php echo esc_html($official['name']); ?>
                                                </a>
                                            <?php else: ?>
                                                <?php echo esc_html($official['name']); ?>
                                            <?php endif; ?>
                                        </h5>
                                        
                                        <p class="card-text">
                                            <strong><?php echo esc_html($official['chamber']); ?></strong><br>
                                            <?php echo esc_html($official['division']); ?><br>
                                            <?php echo esc_html($official['party']); ?>
                                        </p>
                                        
                                        <?php if ($official['fi_score'] !== null): ?>
                                            <div class="fi-official-score">
                                                <span class="fi-score-badge fi-score-<?php echo fi_score_class($official['fi_score']); ?>">
                                                    <?php echo esc_html(number_format($official['fi_score'], 1)); ?>%
                                                </span>
                                                <?php if ($official['fi_grade']): ?>
                                                    <span class="fi-grade"><?php echo esc_html($official['fi_grade']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="fi-official-score">
                                                <span class="text-muted">No Freedom Index score available</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="fi-official-actions">
                                            <?php if (!empty($official['fi_legislator'])): ?>
                                                <a href="<?php echo esc_url(fi_get_legislator_url($official['fi_legislator']['id'] ?? 0, strtolower($official['fi_legislator']['gov'] ?? 'us'))); ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    View Profile
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($official['contact']['phone'])): ?>
                                                <a href="tel:<?php echo esc_attr($official['contact']['phone']); ?>" 
                                                   class="btn btn-sm btn-outline-secondary">
                                                    Call
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($official['contact']['email'])): ?>
                                                <a href="mailto:<?php echo esc_attr($official['contact']['email']); ?>" 
                                                   class="btn btn-sm btn-outline-secondary">
                                                    Email
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (is_user_logged_in() && !empty($official['fi_legislator'])): ?>
                                                <button class="btn btn-sm btn-outline-success fi-add-to-list" 
                                                        data-legislator-id="<?php echo esc_attr($official['fi_legislator']['id']); ?>">
                                                    Add to List
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                                
                                <?php if (is_user_logged_in()): ?>
                                    <div class="fi-list-actions mt-4">
                                        <button class="btn btn-primary" id="fi-save-list">
                                            Save This List
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="fi-login-prompt mt-4">
                                        <div class="alert alert-info">
                                            <h5>Want to save this list?</h5>
                                            <p>Create an account to save your elected officials and create custom lists.</p>
                                            <a href="<?php echo wp_login_url(get_permalink()); ?>" class="btn btn-primary">
                                                Sign Up / Login
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <h4>No Officials Found</h4>
                                <p>We couldn't find any elected officials for the provided address. Please check your ZIP code or try entering your full address.</p>
                                <a href="<?php echo remove_query_arg(['zip', 'address']); ?>" class="btn btn-primary">
                                    Try Again
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php } else { ?>
                        <div class="alert alert-warning">
                            <h4>No Address Provided</h4>
                            <p>Please enter a ZIP code or full address to find your elected officials.</p>
                        </div>
                    <?php } ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // List management
    var selectedLegislators = [];
    
    $('.fi-add-to-list').on('click', function() {
        var legislatorId = $(this).data('legislator-id');
        var $btn = $(this);
        
        if ($btn.hasClass('selected')) {
            selectedLegislators = selectedLegislators.filter(id => id !== legislatorId);
            $btn.removeClass('selected').text('Add to List');
        } else {
            selectedLegislators.push(legislatorId);
            $btn.addClass('selected').text('Remove from List');
        }
    });
    
    $('#fi-save-list').on('click', function() {
        if (selectedLegislators.length === 0) {
            alert('Please select at least one legislator to save.');
            return;
        }
        
        var listName = prompt('Enter a name for this list:');
        if (!listName) return;
        
        $.ajax({
            url: fi_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'fi_save_list',
                name: listName,
                legislator_ids: selectedLegislators,
                nonce: fi_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('List saved successfully!');
                    window.location.href = response.data.url;
                } else {
                    alert('Error saving list: ' + response.data.message);
                }
            }
        });
    });
});
</script>

<?php get_footer(); ?>

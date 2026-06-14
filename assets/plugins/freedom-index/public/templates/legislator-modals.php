<?php
/**
 * Legislator Modals Partial
 * Contact, Share, and Print modals
 */
if (!defined('ABSPATH')) exit;
?>

<!-- Contact Modal -->
<div class="modal fade" id="fi-contact-modal" tabindex="-1" aria-labelledby="fi-contact-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fi-contact-modal-label">
                    <i class="bi bi-envelope me-2"></i>
                    Contact <?php echo esc_html($legislator['display_name'] ?? ''); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if ($contact['phone']): ?>
                    <div class="mb-3">
                        <h6><i class="bi bi-telephone me-2"></i>Phone</h6>
                        <a href="tel:<?php echo esc_attr($contact['phone']); ?>" class="btn btn-outline-primary">
                            <?php echo esc_html($contact['phone']); ?>
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if ($contact['email']): ?>
                    <div class="mb-3">
                        <h6><i class="bi bi-envelope-at me-2"></i>Email</h6>
                        <a href="mailto:<?php echo esc_attr($contact['email']); ?>" class="btn btn-outline-primary">
                            <?php echo esc_html($contact['email']); ?>
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if ($contact['website']): ?>
                    <div class="mb-3">
                        <h6><i class="bi bi-globe me-2"></i>Website</h6>
                        <a href="<?php echo esc_url($contact['website']); ?>" 
                           target="_blank" 
                           rel="noopener noreferrer"
                           class="btn btn-outline-primary">
                            Visit Website
                            <i class="bi bi-box-arrow-up-right ms-1"></i>
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if ($contact['office']): ?>
                    <div class="mb-3">
                        <h6><i class="bi bi-geo-alt me-2"></i>Office</h6>
                        <address class="mb-0">
                            <?php echo nl2br(esc_html($contact['office'])); ?>
                        </address>
                    </div>
                <?php endif; ?>
                
                <?php if (empty(array_filter($contact))): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        No contact information available.
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Share Modal -->
<div class="modal fade" id="fi-share-modal" tabindex="-1" aria-labelledby="fi-share-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fi-share-modal-label">
                    <i class="bi bi-share me-2"></i>
                    Share This Page
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-grid gap-2">
                    <!-- Copy Link -->
                    <button type="button" 
                            class="btn btn-outline-primary"
                            onclick="fiCopyToClipboard('<?php echo esc_url($current_url); ?>', this)">
                        <i class="bi bi-link-45deg me-2"></i>
                        Copy Link
                    </button>
                    
                    <!-- Email -->
                    <a href="mailto:?subject=<?php echo urlencode($page_title); ?>&body=<?php echo urlencode($current_url); ?>"
                       class="btn btn-outline-primary">
                        <i class="bi bi-envelope me-2"></i>
                        Share via Email
                    </a>
                    
                    <!-- Facebook -->
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($current_url); ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="btn btn-outline-primary">
                        <i class="bi bi-facebook me-2"></i>
                        Share on Facebook
                    </a>
                    
                    <!-- Twitter/X -->
                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($current_url); ?>&text=<?php echo urlencode($page_title); ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="btn btn-outline-primary">
                        <i class="bi bi-twitter-x me-2"></i>
                        Share on X
                    </a>
                </div>
                
                <hr>
                
                <!-- QR Code (if available) -->
                <div class="text-center">
                    <p class="text-muted small mb-2">Or scan to share:</p>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($current_url); ?>"
                         alt="QR Code"
                         class="img-fluid"
                         style="max-width: 150px;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Print Modal -->
<div class="modal fade" id="fi-print-modal" tabindex="-1" aria-labelledby="fi-print-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fi-print-modal-label">
                    <i class="bi bi-printer me-2"></i>
                    Print Scorecard
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">
                    Generate a printable scorecard for <?php echo esc_html($legislator['display_name'] ?? ''); ?>.
                    The PDF will include all votes for the current filter selection.
                </p>
                
                <div class="alert alert-info small">
                    <i class="bi bi-info-circle me-2"></i>
                    Current filters: 
                    <strong>
                        <?php if ($current_session): ?>
                            <?php echo esc_html($current_session->session_name); ?>
                        <?php endif; ?>
                        <?php if ($current_report_id): ?>
                            <?php 
                            foreach ($reports as $r) {
                                if ((int) $r->id === $current_report_id) {
                                    echo ' - ' . esc_html($r->title);
                                    break;
                                }
                            }
                            ?>
                        <?php endif; ?>
                        <?php if ($current_tag_id): ?>
                            <?php 
                            foreach ($tags as $t) {
                                if ((int) $t->id === $current_tag_id) {
                                    echo ' - Issue: ' . esc_html($t->name);
                                    break;
                                }
                            }
                            ?>
                        <?php endif; ?>
                    </strong>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="button" 
                            class="btn btn-primary btn-lg"
                            onclick="fiGeneratePDF('legislator', <?php echo (int) $legislator_id; ?>, '<?php echo esc_url($current_url); ?>')"
                            data-bs-dismiss="modal">
                        <i class="bi bi-file-pdf me-2"></i>
                        Generate PDF
                    </button>
                    
                    <button type="button" 
                            class="btn btn-outline-secondary"
                            onclick="window.print();"
                            data-bs-dismiss="modal">
                        <i class="bi bi-printer me-2"></i>
                        Print This Page
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Shared Utility Functions -->
<script>
/**
 * Copy text to clipboard
 */
function fiCopyToClipboard(text, button) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            const original = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check me-2"></i>Copied!';
            button.classList.add('btn-success');
            button.classList.remove('btn-outline-primary');
            setTimeout(function() {
                button.innerHTML = original;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-primary');
            }, 2000);
        });
    } else {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Link copied to clipboard!');
    }
}

/**
 * Generate PDF
 */
function fiGeneratePDF(type, id, url) {
    // Show loading
    const btn = document.querySelector('[onclick*="fiGeneratePDF"]');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';
    }
    
    // Use existing PDF generation endpoint
    const formData = new FormData();
    formData.append('action', 'fi_generate_pdf');
    formData.append('nonce', window.FI?.nonce || '');
    formData.append('type', type);
    formData.append('id', id);
    formData.append('url', url);
    
    fetch(window.FI?.ajaxurl || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.data?.pdf_url) {
            window.open(data.data.pdf_url, '_blank');
        } else {
            alert('Error generating PDF: ' + (data.data || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('PDF generation failed:', err);
        alert('Error generating PDF. Please try again.');
    })
    .finally(() => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-file-pdf me-2"></i>Generate PDF';
        }
    });
}
</script>

<?php
/**
 * Legislator Profile Modals
 *
 * Contact · Share · Print
 *
 * Expected variables (from legislator.php controller via fi_get_public_template):
 *   $legislator       array   full legislator row
 *   $base_url         string  canonical URL
 *   $current_session  array   active session row
 */

if (!defined('ABSPATH')) exit;

$display_name  = $legislator['display_name'] ?? 'Legislator';
$meta          = is_array($legislator['meta'] ?? null) ? $legislator['meta'] : [];

// Contact info — top-level columns take priority; meta fallback must be scalar
$phone   = (string) ($legislator['phone']   ?? (is_scalar($meta['phone']   ?? null) ? $meta['phone']   : ''));
$email   = (string) ($legislator['email']   ?? (is_scalar($meta['email']   ?? null) ? $meta['email']   : ''));
$website = (string) ($legislator['website'] ?? (is_scalar($meta['website'] ?? null) ? $meta['website'] : ''));
$address = (string) ($legislator['address'] ?? (is_scalar($meta['address'] ?? null) ? $meta['address'] : ''));

// Share URL — always canonical (no session/report params) so social sharing is stable
$share_url   = $base_url;
$share_title = $display_name . ' | Freedom Index';
$share_email_href = 'mailto:?subject=' . rawurlencode($share_title) . '&body=' . rawurlencode($share_url);
$qr_url      = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . rawurlencode($share_url);
?>

<!-- ── CONTACT MODAL ─────────────────────────────────────────────── -->
<div class="modal fade" id="fi-contact-modal" tabindex="-1"
	aria-labelledby="fi-contact-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h2 class="modal-title fs-5" id="fi-contact-modal-label">
					<i class="bi bi-envelope-fill me-2" aria-hidden="true"></i>
					Contact <?php echo esc_html($display_name); ?>
				</h2>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">

				<?php if ($phone): ?>
				<div class="mb-3">
					<h3 class="h6"><i class="bi bi-telephone-fill me-2" aria-hidden="true"></i>Phone</h3>
					<a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>" class="btn btn-outline-primary">
						<?php echo esc_html($phone); ?>
					</a>
				</div>
				<?php endif; ?>

				<?php if ($email): ?>
				<div class="mb-3">
					<h3 class="h6"><i class="bi bi-envelope-at me-2" aria-hidden="true"></i>Email</h3>
					<a href="mailto:<?php echo esc_attr($email); ?>" class="btn btn-outline-primary">
						<?php echo esc_html($email); ?>
					</a>
				</div>
				<?php endif; ?>

				<?php if ($website): ?>
				<div class="mb-3">
					<h3 class="h6"><i class="bi bi-globe me-2" aria-hidden="true"></i>Website</h3>
					<a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">
						Visit Website <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i>
					</a>
				</div>
				<?php endif; ?>

				<?php if ($address): ?>
				<div class="mb-3">
					<h3 class="h6"><i class="bi bi-geo-alt me-2" aria-hidden="true"></i>Office</h3>
					<address class="mb-0"><?php echo nl2br(esc_html($address)); ?></address>
				</div>
				<?php endif; ?>

				<?php if (!$phone && !$email && !$website && !$address): ?>
				<div class="alert alert-info">
					<i class="bi bi-info-circle me-2" aria-hidden="true"></i>No contact information on file.
				</div>
				<?php endif; ?>

			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<!-- ── SHARE MODAL ──────────────────────────────────────────────── -->
<div class="modal fade" id="fi-share-modal" tabindex="-1"
	aria-labelledby="fi-share-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h2 class="modal-title fs-5" id="fi-share-modal-label">
					<i class="bi bi-share-fill me-2" aria-hidden="true"></i>Share This Page
				</h2>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">

				<div class="d-grid gap-2">

					<button type="button" class="btn btn-outline-primary" id="fi-share-copy-btn"
						data-share-url="<?php echo esc_attr($share_url); ?>">
						<i class="bi bi-link-45deg me-2" aria-hidden="true"></i>Copy Link
					</button>

					<a href="<?php echo esc_url($share_email_href); ?>" class="btn btn-outline-primary">
						<i class="bi bi-envelope me-2" aria-hidden="true"></i>Share via Email
					</a>

					<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode($share_url); ?>"
						target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">
						<i class="bi bi-facebook me-2" aria-hidden="true"></i>Share on Facebook
					</a>

					<a href="https://x.com/intent/tweet?url=<?php echo rawurlencode($share_url); ?>&text=<?php echo rawurlencode($share_title); ?>"
						target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">
						<i class="bi bi-twitter-x me-2" aria-hidden="true"></i>Share on X
					</a>

				</div>

				<hr>

				<div class="text-center">
					<p class="text-muted small mb-2">Scan to share:</p>
					<img src="<?php echo esc_url($qr_url); ?>" alt="QR Code for this page"
						class="img-fluid border rounded" style="max-width:120px;">
				</div>

			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<!-- ── PRINT MODAL ──────────────────────────────────────────────── -->
<div class="modal fade" id="fi-print-modal" tabindex="-1"
	aria-labelledby="fi-print-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h2 class="modal-title fs-5" id="fi-print-modal-label">
					<i class="bi bi-printer-fill me-2" aria-hidden="true"></i>Print Scorecard
				</h2>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">

				<p class="text-muted">Select a report from the vote history sidebar to enable PDF export. You can also print the page directly.</p>

				<?php if (!empty($current_session['session_name'])): ?>
				<div class="alert alert-info small">
					<i class="bi bi-info-circle me-2" aria-hidden="true"></i>
					Viewing: <strong><?php echo esc_html($current_session['session_name']); ?></strong>
				</div>
				<?php endif; ?>

				<div class="d-grid gap-2">
					<button type="button" class="btn btn-outline-secondary" onclick="window.print();" data-bs-dismiss="modal">
						<i class="bi bi-printer me-2" aria-hidden="true"></i>Print This Page
					</button>
				</div>

			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<script>
(function() {
	// Copy-link button — reads URL from data attribute at click time (supports session navigation)
	document.getElementById('fi-share-copy-btn') && document.getElementById('fi-share-copy-btn').addEventListener('click', function() {
		var url = this.dataset.shareUrl || window.location.href;
		var btn = this;
		if (navigator.clipboard) {
			navigator.clipboard.writeText(url).then(function() {
				var orig = btn.innerHTML;
				btn.innerHTML = '<i class="bi bi-check me-2"></i>Copied!';
				btn.classList.replace('btn-outline-primary', 'btn-success');
				setTimeout(function() {
					btn.innerHTML = orig;
					btn.classList.replace('btn-success', 'btn-outline-primary');
				}, 2000);
			});
		} else {
			var ta = document.createElement('textarea');
			ta.value = url;
			document.body.appendChild(ta);
			ta.select();
			document.execCommand('copy');
			document.body.removeChild(ta);
		}
	});
})();
</script>

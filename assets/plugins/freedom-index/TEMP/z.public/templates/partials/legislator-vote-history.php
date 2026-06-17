<?php
if (!defined('ABSPATH')) exit;

/*
 * Legislator Vote History Display
 * 
 * Displays vote history with sidebar navigation (sessions, reports, issues) and main content area for vote cards
 * Features:
	Card-based vote display with Bootstrap 5 styling
	Mobile-responsive with sliding navigation panel
	Real-time search filtering (no page refresh)
	Vote detail modal (no navigation away)
	Color-coded vote indicators (green/red/gray)
	Report headers displayed above vote lists
	Session-level caching support (already implemented in core)
 */
$legislator = $args['legislator'] ?? (object) $args;
$legislator_id = $legislator->id ?? 0;
$chamber = $legislator->chamber ?? '';
$party = $legislator->party ?? '';
$sessions = $legislator->sessions ?? [];
$gov = $legislator->gov ?? '';
$current_session_id = $args['current_session_id'] ?? null;
$current_report_id = $args['current_report_id'] ?? null;
$current_tag_id = $args['current_tag_id'] ?? null;
$score_freedom = $legislator->freedom_score ?? null;
if($score_freedom == 0 || ($score_freedom != null && $score_freedom != '' && $score_freedom !== false)){
	$freedom_score_html = '<span class="badge bg-primary '.fi_score_class_bg($score_freedom).fi_score_class_bg_text($score_freedom).'fs-7">'.esc_html($score_freedom).'%</span>';
}else{
	$freedom_score_html = '';
}

$text_sm_menu_close = 'Close Menu';
$text_sm_menu_open = 'Select Session or Report';

// Get legislator tags/issues
$legislator_tags = [];
if ($legislator_id && $chamber) {
    $legislator_tags = fi_legislator_tags_get($legislator_id, $chamber);
}

// Get reports for each session
$session_reports = [];
foreach ($sessions as $session) {
    $reports = fi_reports_get([
        'session_id' => $session->session_id,
        'status' => 'publish',
        'orderby' => 'date_publish',
        'order' => 'DESC'
    ]);
	$reports = fi_reports_sort_by_format($gov, $reports);
    if (!empty($reports)) {
        $session_reports[$session->session_id] = $reports;
    }
}

// Default view: All Votes for base URL; session/report/issue only when present in URL
$default_view = 'all';
$default_session_id = $current_session_id ? (int) $current_session_id : null;
$default_report_id = $current_report_id ? (int) $current_report_id : null;
$default_tag_id = $current_tag_id ? (int) $current_tag_id : null;

if ($default_tag_id) {
    $default_view = 'tag';
} elseif ($default_report_id) {
    $default_view = 'report';
} elseif ($default_session_id) {
    $default_view = 'session';
}

// Report format from DB column; report_pdf_url from payload
$report_formats = [];
$report_pdf_urls = [];
foreach ($session_reports as $session_id => $reports) {
    foreach ($reports as $report) {
        $report_formats[$report->id] = $report->format ?? 'scorecard';
        $payload_raw = $report->payload_json ?? '{}';
        $payload = is_array($payload_raw) ? $payload_raw : json_decode($payload_raw, true);
        $url = isset($payload['report_pdf_url']) ? trim((string) $payload['report_pdf_url']) : '';
        if ($url !== '') {
            $report_pdf_urls[$report->id] = $url;
        }
    }
}
?>
<style>
/* Test showing selected list group item like btn-outline-primary with border and white BG */
	#accordionVoteNav .list-group-item.active {
		background-color: #fff;
		border-color: #000;
		color: #000;
		border-radius: 0.25rem;
		border-width: 1px;
		border-style: solid;
		border-color: #000;
		border-radius: 0.25rem;
		border-width: 1px;
		border-style: solid;
		border-color: #000;
	}
</style>

<div class="row g-3">
	<!-- Left Sidebar Navigation (LG+) / Mobile Sliding Panel -->
	<div class="col-12 col-lg-4 col-xxl-3">
		<!-- Mobile Toggle Button -->
		<button id="fi-vote-nav-toggle" class="btn btn-sm btn-outline-primary w-100 d-lg-none mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#fi-vote-nav-collapse" aria-expanded="false" aria-controls="fi-vote-nav-collapse">
			<i class="bi bi-list me-2"></i><span class="fi-nav-text fw-bold"><?= $text_sm_menu_open;?></span>
		</button>
		
		<!-- Navigation Panel -->
		<div class="collapse d-lg-block" id="fi-vote-nav-collapse">
			<div class="card rounded-4 shadow-sm">
				<div class="card-header rounded-top-4 bg-white">
					<h5 class="fs-3 mb-0">Voting History</h5>
				</div>
				<div class="card-body p-0 pb-4">
					<div class="accordion accordion-flush" id="accordionVoteNav">
						<div class="accordion-item">
							<h2 class="accordion-header">
							<button class="accordion-button caret-tight py-2 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseAllVotes" aria-expanded="false" aria-controls="flush-collapseAllVotes">
								<span class="me-auto fs-7">All Votes</span>
								<?php echo $freedom_score_html; ?>
							</button>
							</h2>
							<div id="flush-collapseAllVotes" class="accordion-collapse collapse" data-bs-parent="#accordionVoteNav">
								<div class="accordion-body p-0">
									<ul class="list-group list-group-flush mb-0">
										<li class="list-group-item border-0">
											<a href="<?php echo esc_url(home_url('/legislator/' . $legislator_id . '/')); ?>" class="list-group-item list-group-item-action fi-nav-item fs-7" data-view="all" data-type="all">All Votes</a>
										</li>
										<!-- Issues/Tags (ID-based URLs) -->
									<?php if (!empty($legislator_tags)): ?>
										<?php foreach ($legislator_tags as $tag): ?>
										<li class="list-group-item border-0">
											<a href="<?php echo esc_url(home_url('/legislator/' . $legislator_id . '/issue/' . $tag->id . '/')); ?>" class="list-group-item list-group-item-action fi-nav-item fs-7" data-view="tag" data-tag-id="<?php echo esc_attr($tag->id); ?>" data-tag-slug="<?php echo esc_attr($tag->slug ?? ''); ?>">
												<?php echo esc_html($tag->name); ?>
												<span class="badge bg-secondary float-end"><?php echo esc_html($tag->vote_count); ?></span>
											</a>
										</li>
										<?php endforeach; ?>
									<?php endif;?>
									</ul>
								</div>
							</div>
						</div>
					<!-- Sessions -->
					<?php if (!empty($sessions)): foreach ($sessions as $session):
						$session_id = $session->session_id ?? null;
						$is_current_session = ($current_session_id && $session_id == $current_session_id);
						$has_reports = !empty($session_reports[$session_id]);
						$session_name = $session->session_name ?? 'Session';
						$collapse_id = 'fi-session-' . $session_id;
						if($session->score == 0 || ($session->score != null && $session->score != false && $session->score != '')){
							$session_score_html = '<span class="badge bg-primary '.fi_score_class_bg($session->score).fi_score_class_bg_text($session->score).'fs-7">'.esc_html($session->score).'%</span>';
						}else{
							$session_score_html = '';
						}
					?>
						<div class="accordion-item">
							<h2 class="accordion-header">
								<button class="accordion-button caret-tight py-2 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapse<?php echo esc_attr($session_id); ?>" aria-expanded="false" aria-controls="flush-collapse<?php echo esc_attr($session_id); ?>">
									<span class="me-auto fs-7"><?php echo esc_html($session_name); ?></span>
									<?php echo $session_score_html; ?>
								</button>
							</h2>
							<div id="flush-collapse<?php echo esc_attr($session_id); ?>" class="accordion-collapse collapse<?php echo $is_current_session ? ' show' : ''; ?>" data-bs-parent="#accordionVoteNav">
								<div class="accordion-body p-0">
									<!-- All Session Votes + Reports List -->
									<ul class="list-group list-group-flush mb-0">
										<!-- All Session Votes -->
										<li class="list-group-item border-0">
											<a href="<?php echo esc_url(home_url('/legislator/' . $legislator_id . '/session/' . $session_id . '/')); ?>" class="list-group-item list-group-item-action fi-nav-item fs-7 <?php echo ($is_current_session && !$current_report_id) ? ' active' : ''; ?>" 
												data-view="session" 
												data-session-id="<?php echo esc_attr($session_id); ?>">
												All Session Votes
											</a>
										</li>
										<!-- Reports -->
										<?php if ($has_reports): 
											$report_count = count($session_reports[$session->session_id]);
											$report_index = 0;
											foreach ($session_reports[$session->session_id] as $report): 
												$is_current_report = ($is_current_session && $current_report_id && $report->id === $current_report_id);
												$report_index++;
												$is_last = ($report_index === $report_count);
										?>
											<li class="list-group-item border-0">
												<a href="<?php echo esc_url(home_url('/legislator/' . $legislator_id . '/session/' . $session_id . '/report/' . $report->id . '/')); ?>" class="list-group-item list-group-item-action fs-7 fi-nav-item ps-4<?php echo $is_current_report ? ' active bg-primary text-white' : ''; ?>" 
													data-view="report" 
													data-session-id="<?php echo esc_attr($session_id); ?>" 
													data-report-id="<?php echo esc_attr($report->id); ?>">
													<?php echo esc_html($report->title); ?>
												</a>
											</li>
										<?php endforeach; endif; ?>
									</ul>
								</div>
							</div>
						</div>
						<?php endforeach;  endif;?>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Main Content Area -->
	<div class="col-12 col-lg-8 col-xxl-9">
		<div class="card rounded-4 shadow-sm">
			<!-- Vote List Header -->
			<div class="card-header rounded-top-4 bg-white border-bottom">
				<div class="row align-items-center g-2">
					<div class="col-12 col-md">
						<h4 class="fs-3 mb-0" id="fi-vote-list-title">Loading votes...</h4>
					</div>
					<div class="col-12 col-md-auto">
						<!-- Batch Score Display (hidden when search is showing) -->
						<div id="fi-vote-score-container" style="display: none;">
							<div class="btn-group btn-group-sm w-100 w-md-auto" role="group">
								<!-- Share button: Trigger Share Modal for THIS page/report -->
								<?php
								$legislator_id = $legislator->id ?? 0;
								$share_session_id = $current_session_id ?? '';
								$share_report_id = $current_report_id ?? '';
								?>
								<button 
									type="button" 
									class="btn btn-outline-success fs-7 fw-bold flex-fill" 
									data-bs-toggle="modal" 
									data-bs-target="#shareModal"
									data-share-session="<?php echo esc_attr($share_session_id); ?>"
									data-share-report="<?php echo esc_attr($share_report_id); ?>"
									data-share-legislator-id="<?php echo esc_attr($legislator_id); ?>"
									id="fi-vote-share-btn"
								>
									<i class="bi bi-share me-2"></i>Share
								</button>
						
	<!-- PDF buttons: Show based on report format -->
	<!-- Single PDF button for freedomindex reports -->
								<a 
									href="#" 
									class="btn btn-outline-danger fs-7 fw-bold flex-fill" 
									target="_blank"
									id="fi-vote-pdf-btn"
									style="display: none;"
								>
									<i class="bi bi-file-pdf me-2"></i>PDF
								</a>
	<!-- Two PDF buttons for scorecard reports -->
								<a 
									href="#" 
									class="btn btn-outline-danger fs-7 fw-bold flex-fill" 
									target="_blank"
									id="fi-vote-pdf-portrait-btn"
									data-format="sca"
									style="display: none;"
								>
									<i class="bi bi-file-pdf me-2"></i>PDF
								</a>
								<a 
									href="#" 
									class="btn btn-outline-danger fs-7 fw-bold flex-fill" 
									target="_blank"
									id="fi-vote-pdf-bifold-btn"
									data-format="scb"
									style="display: none;"
								>
									<i class="bi bi-file-pdf me-2"></i>PDF Bi-Fold
								</a>
								<!-- Score badge styled as button group item -->
								<span id="fi-vote-score-btn" class="btn btn-primary fs-7 fw-bold flex-fill" style="pointer-events: none; cursor: default;">
									<span class="d-none d-md-inline">Score: </span><span id="fi-vote-score-value">0</span>%
								</span>
							</div>
						</div>
						<!-- Search Box (for All Votes view) -->
						<div id="fi-vote-search-container" style="display: none;">
							<input type="text" class="form-control form-control-sm" id="fi-vote-search" placeholder="Search votes..." style="min-width: 200px;">
						</div>
					</div>
				</div>
			</div>
			
			<!-- Vote List Content -->
			<div class="card-body p-3 pt-1">
				<div id="fi-vote-list-subtitle" class="text-muted fs-7 mb-2"></div>
				<div id="fi-vote-list-container">
					<div class="text-center py-5">
						<div class="spinner-border text-primary" role="status">
							<span class="visually-hidden">Loading...</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>


<!-- Vote Detail Modal -->
<div class="modal fade" id="fi-vote-detail-modal" tabindex="-1" aria-labelledby="fi-vote-detail-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fi-vote-detail-modal-label">Vote Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="fi-vote-detail-content">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- GSAP error suppression - runs immediately before any other scripts -->
<script>
(function() {
    'use strict';
    // Defensive patch for GSAP errors - suppress "target not found" warnings/errors
    // This runs immediately to catch errors even if GSAP hasn't loaded yet
    if (typeof console !== 'undefined') {
        const originalWarn = console.warn;
        const originalError = console.error;
        const originalLog = console.log;
        
        // Helper to check if message is a GSAP target error
        function isGSAPTargetError(args) {
            if (!args || args.length === 0) return false;
            const fullMessage = Array.from(args).map(arg => {
                if (typeof arg === 'string') return arg;
                if (typeof arg === 'object' && arg !== null) {
                    try {
                        return JSON.stringify(arg);
                    } catch (e) {
                        return String(arg);
                    }
                }
                return String(arg);
            }).join(' ');
            
			const lowerMessage = fullMessage.toLowerCase();
			const hasGSAP = lowerMessage.includes('gsap');
			const hasTarget = lowerMessage.includes('target') || 
				lowerMessage.includes('.fi-legislator-card') || 
				lowerMessage.includes('.fi-');
			const hasNotFound = lowerMessage.includes('not found') || 
				lowerMessage.includes('notfound');
            
            return hasGSAP && hasTarget && (hasNotFound || lowerMessage.includes('greensock'));
        }
        
        console.warn = function() {
            if (isGSAPTargetError(arguments)) return;
            originalWarn.apply(console, arguments);
        };
        
        console.error = function() {
            if (isGSAPTargetError(arguments)) return;
            originalError.apply(console, arguments);
        };
        
        console.log = function() {
            if (isGSAPTargetError(arguments)) return;
            originalLog.apply(console, arguments);
        };
    }
})();
</script>

<script>
(function($) {
    const legislatorId = <?php echo esc_js($legislator_id); ?>;
    const chamber = <?php echo json_encode($chamber); ?>;
	const party = <?php echo json_encode($party); ?>;
    let currentView = <?php echo json_encode($default_view); ?>;
    let currentSessionId = <?php echo json_encode($default_session_id); ?>;
    let currentReportId = <?php echo json_encode($default_report_id); ?>;
    let currentTagId = <?php echo json_encode($default_tag_id); ?>;
    let currentTagSlug = null; // Will be set from URL or tag selection
    let allVotesData = null; // Cache for all votes (for instant search)
    const reportFormats = <?php echo json_encode($report_formats); ?>; // Report format by report ID
    const reportPdfUrls = <?php echo json_encode($report_pdf_urls); ?>; // Freedom Index pre-made PDF URL by report ID
    // Make reportFormats available globally for share modal
    window.fiReportFormats = reportFormats;
    
    // Build tag slug map from tag elements
    const tagSlugMap = {};
    $('.fi-nav-item[data-view="tag"]').each(function() {
        const tagId = $(this).data('tag-id');
        const tagSlug = $(this).data('tag-slug');
        if (tagId && tagSlug) {
            tagSlugMap[tagId] = tagSlug;
        }
    });
    
    // Build URL from current view state. All Votes = base /legislator/{id}/; never use window.location when building from state.
    function getCurrentUrl(useState = true) {
        const base = window.location.origin + '/legislator/' + legislatorId;
        if (!useState) {
            let url = window.location.origin + window.location.pathname;
            if (!url.endsWith('/')) url += '/';
            return url;
        }
        if (currentTagId) return base + '/issue/' + currentTagId + '/';
        if (currentSessionId) {
            let u = base + '/session/' + currentSessionId;
            if (currentReportId) u += '/report/' + currentReportId;
            return u + '/';
        }
        return base + '/'; // All Votes = base URL
    }
    
    // Alias for backward compatibility
    function buildCurrentUrl() {
        return getCurrentUrl(true);
    }
    
    // Helper function to update og:url meta tag and canonical link
    // Accepts optional URL parameter to avoid reading window.location twice
    function updateOgUrl(url = null) {
        const currentUrl = url || getCurrentUrl(false); // Use window.location for accuracy
        const ogUrlMeta = document.querySelector('meta[property="og:url"]');
        if (ogUrlMeta) {
            ogUrlMeta.setAttribute('content', currentUrl);
        }
        // Also update canonical link if present
        const canonicalLink = document.querySelector('link[rel="canonical"]');
        if (canonicalLink) {
            canonicalLink.setAttribute('href', currentUrl);
        }
    }
    
    // Helper function to update URL without page reload
    function updateUrl() {
        const newUrl = buildCurrentUrl();
        if (window.location.href !== newUrl) {
            window.history.pushState({view: currentView, sessionId: currentSessionId, reportId: currentReportId}, '', newUrl);
            // Update og:url meta tag to match the new URL (pass URL to avoid re-reading)
            updateOgUrl(newUrl);
        }
    }
    
    // Helper function to build PDF URL for current view
    // Format is required in URL
    function buildPdfUrl(format) {
        const baseUrl = buildCurrentUrl();
        return baseUrl.replace(/\/$/, '') + '/pdf/' + format + '/';
    }
    
    // Helper function to update PDF buttons based on report format
    function updatePdfButtons() {
        const $pdfBtn = $('#fi-vote-pdf-btn'); // Single button for freedomindex
        const $pdfPortraitBtn = $('#fi-vote-pdf-portrait-btn'); // Portrait for scorecard
        const $pdfBifoldBtn = $('#fi-vote-pdf-bifold-btn'); // Bi-fold for scorecard

        // Only show PDF buttons for report views (not session, all votes, or tag views)
        if (currentView === 'report' && currentReportId) {
            const reportFormat = reportFormats[currentReportId] || 'scorecard';

            if (reportFormat === 'freedomindex') {
                // Freedom Index: show single PDF button only when report_pdf_url is set (pre-made PDF)
                const pdfUrl = reportPdfUrls[currentReportId];
                if (pdfUrl) {
                    $pdfBtn.attr('href', pdfUrl);
                    $pdfBtn.show();
                } else {
                    $pdfBtn.hide();
                }
                $pdfPortraitBtn.hide();
                $pdfBifoldBtn.hide();
            } else {
                // Scorecard: two PDF buttons (sca portrait, scb bi-fold)
                $pdfBtn.hide();
                $pdfPortraitBtn.attr('href', buildPdfUrl('sca'));
                $pdfPortraitBtn.show();
                $pdfBifoldBtn.attr('href', buildPdfUrl('scb'));
                $pdfBifoldBtn.show();
            }
        } else {
            // Hide all PDF buttons for non-report views
            $pdfBtn.hide();
            $pdfPortraitBtn.hide();
            $pdfBifoldBtn.hide();
        }
    }
    
    // Cache frequently used jQuery objects
    const $container = $('#fi-vote-list-container');
    const $title = $('#fi-vote-list-title');
    const $subtitle = $('#fi-vote-list-subtitle');
    const $scoreContainer = $('#fi-vote-score-container');
    const $scoreBtn = $('#fi-vote-score-btn');
    const $scoreValue = $('#fi-vote-score-value');
    const $searchContainer = $('#fi-vote-search-container');
    const $modal = $('#fi-vote-detail-modal');
    const $modalContent = $('#fi-vote-detail-content');
    let modalInstance = null;
    
    // Helper function to safely get Bootstrap components
    function getBootstrap() {
        // Try window.bootstrap first (Bootstrap 5)
        if (typeof window.bootstrap !== 'undefined') {
            return window.bootstrap;
        }
        // Fallback to jQuery Bootstrap plugin
        if (typeof $.fn.collapse !== 'undefined') {
            return {
                Collapse: {
                    getInstance: function(element) {
                        return $(element).data('bs.collapse') || null;
                    }
                },
                Modal: {
                    getInstance: function(element) {
                        return $(element).data('bs.modal') || null;
                    }
                }
            };
        }
        return null;
    }
    
    // Helper to create Bootstrap Collapse instance safely
    function createCollapse(element, options) {
        const bs = getBootstrap();
        if (bs && bs.Collapse) {
            try {
                return new bs.Collapse(element, options);
            } catch (e) {
                // Fallback to jQuery
                $(element).collapse(options);
                return $(element).data('bs.collapse');
            }
        }
        // Fallback to jQuery Bootstrap
        $(element).collapse(options);
        return $(element).data('bs.collapse');
    }
    
    // Helper to create Bootstrap Modal instance safely
    function createModal(element, options) {
        const bs = getBootstrap();
        if (bs && bs.Modal) {
            try {
                return new bs.Modal(element, options);
            } catch (e) {
                // Fallback to jQuery
                $(element).modal(options);
                return $(element).data('bs.modal');
            }
        }
        // Fallback to jQuery Bootstrap
        $(element).modal(options);
        return $(element).data('bs.modal');
    }
    
    // Mobile nav toggle text
    const navToggle = document.getElementById('fi-vote-nav-toggle');
    const navCollapse = document.getElementById('fi-vote-nav-collapse');
    const navText = navToggle ? navToggle.querySelector('.fi-nav-text') : null;
    
    if (navToggle && navCollapse && navText) {
        // Function to update nav text based on actual collapse state
        function updateNavText() {
            // Check if collapse is actually shown (has 'show' class or is visible)
            const isShown = navCollapse.classList.contains('show') || 
                           (navCollapse.offsetHeight > 0 && window.getComputedStyle(navCollapse).display !== 'none');
            navText.textContent = isShown ? '<?= $text_sm_menu_close;?>' : '<?= $text_sm_menu_open;?>';
        }
        
        // Set initial text based on actual state (after a brief delay to ensure DOM is ready)
        setTimeout(function() {
            updateNavText();
        }, 100);
        
        // Update text when user interacts with the collapse
        navCollapse.addEventListener('show.bs.collapse', function() {
            navText.textContent = '<?= $text_sm_menu_close;?>';
        });
        navCollapse.addEventListener('hide.bs.collapse', function() {
            navText.textContent = '<?= $text_sm_menu_open;?>';
        });
        
        // Also check state after any accordion expansions (in case they affect the nav)
        navCollapse.addEventListener('shown.bs.collapse', updateNavText);
        navCollapse.addEventListener('hidden.bs.collapse', updateNavText);
    }
    
    // Auto-select first item when accordion opens
    $(document).on('shown.bs.collapse', '#accordionVoteNav .accordion-collapse', function(e) {
        const $accordion = $(this);
        const accordionId = $accordion.attr('id');
        const $accordionBody = $accordion.find('.accordion-body');
        
        // Only auto-select if no item is already active in this accordion
        if (!$accordionBody.find('.fi-nav-item.active').length) {
            if (accordionId === 'flush-collapseAllVotes') {
                // All Votes accordion: select "All Votes" item
                const $firstItem = $accordionBody.find('.fi-nav-item[data-view="all"]').first();
                if ($firstItem.length) {
                    $firstItem.click();
                }
            } else if (accordionId && accordionId.startsWith('flush-collapse')) {
                // Session accordion: select first "All Session Votes" item
                const $firstSessionItem = $accordionBody.find('.fi-nav-item[data-view="session"]').first();
                if ($firstSessionItem.length) {
                    $firstSessionItem.click();
                }
            }
        }
    });
    
    // Clicking the "All Votes" accordion title should reset to default view
    $(document).on('click', '#accordionVoteNav .accordion-button[data-bs-target="#flush-collapseAllVotes"]', function() {
        const $allVotesItem = $('#flush-collapseAllVotes .fi-nav-item[data-view="all"]').first();
        if ($allVotesItem.length) {
            $allVotesItem.trigger('click');
        }
    });
    
    // Navigation click handler
    $(document).on('click', '.fi-nav-item', function(e) {
        const $item = $(this);
        e.preventDefault();
        const view = $item.data('view');
        const sessionId = $item.data('session-id');
        const reportId = $item.data('report-id');
        const tagId = $item.data('tag-id');
        const tagSlug = $item.data('tag-slug');
        
        // Update active state
        $('.fi-nav-item').removeClass('active bg-primary text-white');
        $item.addClass('active bg-primary text-white');
        
        // Update current view (tag view needs only tagId; slug from map if missing)
        currentView = view;
        if (view === 'tag' && tagId) {
            currentTagId = tagId;
            currentTagSlug = tagSlug || (tagSlugMap[tagId] || null);
            currentSessionId = null;
            currentReportId = null;
        } else {
            currentTagId = null;
            currentTagSlug = null;
            currentSessionId = sessionId || null;
            currentReportId = reportId || null;
        }
        
        // Update share button data attributes
        const $shareBtn = $('#fi-vote-share-btn');
        if ($shareBtn.length) {
            $shareBtn.attr('data-share-session', currentSessionId || '');
            $shareBtn.attr('data-share-report', currentReportId || '');
        }
        
        // Update PDF buttons
        updatePdfButtons();
        
        // Update URL without page reload
        updateUrl();
        
        // Expand session accordion if needed
        if (sessionId) {
            const $sessionAccordion = $('#flush-collapse' + sessionId);
            if ($sessionAccordion.length && !$sessionAccordion.hasClass('show')) {
                createCollapse($sessionAccordion[0], {show: true});
            }
        }
        
        // Close mobile nav
        if (window.innerWidth < 992 && navCollapse) {
            const bs = getBootstrap();
            if (bs && bs.Collapse) {
                try {
                    const bsCollapse = bs.Collapse.getInstance(navCollapse);
                    if (bsCollapse) {
                        bsCollapse.hide();
                    }
                } catch (e) {
                    $(navCollapse).collapse('hide');
                }
            } else {
                $(navCollapse).collapse('hide');
            }
        }
        
        // Load votes
        loadVotes();
    });
    
    // Real-time search (for All Votes view)
    let searchTimeout;
    $('#fi-vote-search').on('input', function() {
        clearTimeout(searchTimeout);
        const searchTerm = $(this).val().toLowerCase();
        
        searchTimeout = setTimeout(function() {
            if (currentView === 'all' && allVotesData) {
                filterVotesBySearch(searchTerm);
            }
        }, 300);
    });
    
    // Load votes function
    function loadVotes() {
        $container.html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        
        const data = {
            action: 'fi_legislator_vote_history',
            legislator_id: legislatorId,
            chamber: chamber,
			party: party,
            view: currentView,
            session_id: currentSessionId,
            report_id: currentReportId,
            tag_id: currentTagId,
            nonce: '<?php echo wp_create_nonce('fi_ajax_nonce'); ?>'
        };
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success && response.data) {
                    renderVotes(response.data);
                } else {
                    const errorMsg = response.data && response.data.message ? response.data.message : 'No votes found.';
                    $container.html('<div class="alert alert-warning">' + escapeHtml(errorMsg) + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {xhr: xhr, status: status, error: error, data: data});
                $container.html('<div class="alert alert-danger">Error loading votes. Please try again.<br><small>' + escapeHtml(error) + '</small></div>');
            }
        });
    }
    
    // Render votes (now server-side rendered HTML)
    function renderVotes(data) {
        // Update title
        $title.text(data.title || 'Votes');
		$subtitle.text(data.subtitle || '');
        
        // Handle search container and score visibility
        const isAllView = currentView === 'all';
        const hasVotesData = isAllView && data.votes;
        
        if (hasVotesData) {
            // Cache votes for instant search
            allVotesData = data.votes;
            $searchContainer.show();
            $scoreContainer.hide();
        } else {
            $searchContainer.hide();
            // Show score if available
            if (typeof data.score !== 'undefined' && data.score !== null) {
                $scoreValue.text(data.score);
                $scoreContainer.show();
				//Evaluate score and add corresponding class.
				$scoreBtn.removeClass('fi-bg-a fi-bg-b fi-bg-c fi-bg-d fi-bg-f fi-bg-text-a fi-bg-text-b fi-bg-text-c fi-bg-text-d fi-bg-text-f');
				if (data.score >= 90) {
					$scoreBtn.addClass('fi-bg-a fi-bg-text-a');
				} else if (data.score >= 80) {
					$scoreBtn.addClass('fi-bg-b fi-bg-text-b');
				} else if (data.score >= 70) {
					$scoreBtn.addClass('fi-bg-c fi-bg-text-c');
				} else if (data.score >= 60) {
					$scoreBtn.addClass('fi-bg-d fi-bg-text-d');
				} else {
					$scoreBtn.addClass('fi-bg-f fi-bg-text-f');
				}
            } else {
                $scoreContainer.hide();
            }
        }
        
        // Insert server-rendered HTML
        if (data.html) {
            $container.html(data.html);
        } else {
            $container.html('<div class="alert alert-info">No votes found for this selection.</div>');
        }
    }
    
    // Filter votes by search term
    function filterVotesBySearch(searchTerm) {
        const $cards = $('.fi-vote-card');
        const searchLower = searchTerm.toLowerCase();
        
        if (!searchTerm) {
            $cards.show();
            return;
        }
        
        // Batch show/hide operations for better performance
        $cards.each(function() {
            const searchText = $(this).data('search-text') || '';
            $(this).toggle(searchText.includes(searchLower));
        });
    }
    
    // View vote detail
    $(document).on('click', '.fi-view-vote-detail', function() {
        const voteId = $(this).data('vote-id');
        loadVoteDetail(voteId);
    });
    
    // Load vote detail modal
    function loadVoteDetail(voteId) {
        $modalContent.html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        
        if (!modalInstance) {
            modalInstance = createModal($modal[0]);
        }
        if (modalInstance && modalInstance.show) {
            modalInstance.show();
        } else {
            $modal.modal('show');
        }
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'fi_vote_detail',
                vote_id: voteId,
                legislator_id: legislatorId,
                nonce: '<?php echo wp_create_nonce('fi_ajax_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data) {
                    $modalContent.html(response.data.html);
                } else {
                    $modalContent.html('<div class="alert alert-danger">Error loading vote details.</div>');
                }
            },
            error: function() {
                $modalContent.html('<div class="alert alert-danger">Error loading vote details.</div>');
            }
        });
    }
    
    // Helper function
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Share modal is handled by legislator-modal-share.php
    // It reads window.location.pathname directly when the modal opens
    
    // Handle session links from score table
    $(document).on('click', '.fi-session-link[data-scroll="true"]', function(e) {
        const $link = $(this);
        const sessionId = $link.data('session-id');
        const view = $link.data('view');
        
        // If it's a hash link, prevent default and scroll
        if ($link.attr('href').startsWith('#')) {
            e.preventDefault();
            
            // Scroll to vote history section
            const $target = $('#fi-legislator-vote-history');
            if ($target.length) {
                $('html, body').animate({
                    scrollTop: $target.offset().top - 20
                }, 500);
                
                // Trigger view if specified
                if (view === 'all') {
                    setTimeout(function() {
                        $('.fi-nav-item[data-view="all"]').click();
                    }, 100);
                }
            }
        }
        // For query parameter links, let them navigate normally
        // The page load will handle auto-selection via URL params
    });
    
    // Initialize: Load default view
    $(document).ready(function() {
        // Parse URL for session/report/issue (path: issue segment is numeric tag ID)
        const urlParams = new URLSearchParams(window.location.search);
        const pathParts = window.location.pathname.split('/').filter(Boolean);
        
        let urlSessionId = urlParams.get('session');
        let urlReportId = urlParams.get('report');
        let urlTagId = null;
        
        const issueIndex = pathParts.indexOf('issue');
        if (issueIndex !== -1 && pathParts[issueIndex + 1]) {
            const raw = pathParts[issueIndex + 1];
            if (/^\d+$/.test(raw)) {
                urlTagId = parseInt(raw, 10);
            }
        }
        
        const sessionIndex = pathParts.indexOf('session');
        if (sessionIndex !== -1 && pathParts[sessionIndex + 1]) {
            urlSessionId = pathParts[sessionIndex + 1];
            const reportIndex = pathParts.indexOf('report');
            if (reportIndex !== -1 && pathParts[reportIndex + 1]) {
                urlReportId = pathParts[reportIndex + 1];
            }
        }
        
        if (urlTagId) {
            currentView = 'tag';
            currentTagId = urlTagId;
            currentTagSlug = tagSlugMap[urlTagId] || null;
            currentSessionId = null;
            currentReportId = null;
        } else if (urlSessionId) {
            currentSessionId = parseInt(urlSessionId);
            if (urlReportId) {
                currentView = 'report';
                currentReportId = parseInt(urlReportId);
            } else {
                currentView = 'session';
            }
            currentTagId = null;
            currentTagSlug = null;
        }
        
        // Initialize share button with current values
        const $shareBtn = $('#fi-vote-share-btn');
        if ($shareBtn.length) {
            $shareBtn.attr('data-share-session', currentSessionId || '');
            $shareBtn.attr('data-share-report', currentReportId || '');
        }
        
        // Initialize PDF buttons
        updatePdfButtons();
        
        // Ensure nav toggle text is correct after initialization
        setTimeout(function() {
            const navText = document.querySelector('#fi-vote-nav-toggle .fi-nav-text');
            const navCollapse = document.getElementById('fi-vote-nav-collapse');
            if (navText && navCollapse) {
                // Check actual collapse state - on mobile it should be closed, on desktop it's always visible
                const isMobile = window.innerWidth < 992; // lg breakpoint
                const isShown = navCollapse.classList.contains('show');
                // On desktop, the collapse is always visible (d-lg-block), so we don't show the toggle
                if (isMobile && !isShown) {
                    navText.textContent = '<?= $text_sm_menu_open;?>';
                }
            }
        }, 200);
        
        // Function to expand accordion and activate nav item
        function activateNavItem() {
            if (currentView === 'report' && currentReportId && currentSessionId) {
                // Expand session accordion
                const $sessionAccordion = $('#flush-collapse' + currentSessionId);
                if ($sessionAccordion.length) {
                    // Check if already expanded
                    if ($sessionAccordion.hasClass('show')) {
                        // Already expanded, activate immediately
                        $('.fi-nav-item').removeClass('active bg-primary text-white');
                        $('.fi-nav-item[data-view="report"][data-session-id="' + currentSessionId + '"][data-report-id="' + currentReportId + '"]').addClass('active bg-primary text-white');
                    } else {
                        // Expand and wait for event
                        createCollapse($sessionAccordion[0], {show: true});
                        $sessionAccordion.one('shown.bs.collapse', function() {
                            $('.fi-nav-item').removeClass('active bg-primary text-white');
                            $('.fi-nav-item[data-view="report"][data-session-id="' + currentSessionId + '"][data-report-id="' + currentReportId + '"]').addClass('active bg-primary text-white');
                        });
                    }
                } else {
                    // Accordion not found, try activating anyway
                    setTimeout(function() {
                        $('.fi-nav-item').removeClass('active bg-primary text-white');
                        $('.fi-nav-item[data-view="report"][data-session-id="' + currentSessionId + '"][data-report-id="' + currentReportId + '"]').addClass('active bg-primary text-white');
                    }, 100);
                }
            } else if (currentView === 'session' && currentSessionId) {
                // Expand session accordion
                const $sessionAccordion = $('#flush-collapse' + currentSessionId);
                if ($sessionAccordion.length) {
                    // Check if already expanded
                    if ($sessionAccordion.hasClass('show')) {
                        // Already expanded, activate immediately
                        $('.fi-nav-item').removeClass('active bg-primary text-white');
                        $('.fi-nav-item[data-view="session"][data-session-id="' + currentSessionId + '"]').first().addClass('active bg-primary text-white');
                    } else {
                        // Expand and wait for event
                        createCollapse($sessionAccordion[0], {show: true});
                        $sessionAccordion.one('shown.bs.collapse', function() {
                            $('.fi-nav-item').removeClass('active bg-primary text-white');
                            $('.fi-nav-item[data-view="session"][data-session-id="' + currentSessionId + '"]').first().addClass('active bg-primary text-white');
                        });
                    }
                } else {
                    // Accordion not found, try activating anyway
                    setTimeout(function() {
                        $('.fi-nav-item').removeClass('active bg-primary text-white');
                        $('.fi-nav-item[data-view="session"][data-session-id="' + currentSessionId + '"]').first().addClass('active bg-primary text-white');
                    }, 100);
                }
            } else if (currentView === 'tag' && currentTagId) {
                // Expand "All Votes" accordion for tag view
                const $allVotesAccordion = $('#flush-collapseAllVotes');
                if ($allVotesAccordion.length && !$allVotesAccordion.hasClass('show')) {
                    createCollapse($allVotesAccordion[0], {show: true});
                    $allVotesAccordion.one('shown.bs.collapse', function() {
                        $('.fi-nav-item').removeClass('active bg-primary text-white');
                        $('.fi-nav-item[data-view="tag"][data-tag-id="' + currentTagId + '"]').addClass('active bg-primary text-white');
                    });
                } else {
                    $('.fi-nav-item').removeClass('active bg-primary text-white');
                    $('.fi-nav-item[data-view="tag"][data-tag-id="' + currentTagId + '"]').addClass('active bg-primary text-white');
                }
            } else {
                $('.fi-nav-item').removeClass('active bg-primary text-white');
                $('.fi-nav-item[data-view="all"]').addClass('active bg-primary text-white');
            }
        }
        
        // Activate nav item (will expand accordion if needed)
        activateNavItem();
        
        // Scroll to vote history if URL parameters were found
        if (urlSessionId) {
            const $target = $('#fi-legislator-vote-history');
            if ($target.length) {
                setTimeout(function() {
                    $('html, body').animate({
                        scrollTop: $target.offset().top - 20
                    }, 300);
                }, 100);
            }
        }
        
        // Update og:url to match current URL (in case page loaded with session/report/issue in URL)
        updateOgUrl();
        
        loadVotes();
    });
    
    // Handle browser back/forward navigation
    window.addEventListener('popstate', function(event) {
        // When user navigates back/forward, update og:url to match the new URL
        updateOgUrl();
        
        // Re-parse URL (issue segment is numeric tag ID)
        const urlParams = new URLSearchParams(window.location.search);
        const pathParts = window.location.pathname.split('/').filter(Boolean);
        
        let urlSessionId = urlParams.get('session');
        let urlReportId = urlParams.get('report');
        let urlTagId = null;
        
        const legislatorIndex = pathParts.indexOf('legislator');
        if (legislatorIndex >= 0 && legislatorIndex < pathParts.length - 1) {
            let pathIndex = legislatorIndex + 2;
            if (pathIndex < pathParts.length && pathParts[pathIndex] === 'issue' && pathIndex + 1 < pathParts.length) {
                const raw = pathParts[pathIndex + 1];
                if (/^\d+$/.test(raw)) {
                    urlTagId = parseInt(raw, 10);
                }
            } else if (pathIndex < pathParts.length && pathParts[pathIndex] === 'session' && pathIndex + 1 < pathParts.length) {
                urlSessionId = pathParts[pathIndex + 1];
                pathIndex += 2;
                if (pathIndex < pathParts.length && pathParts[pathIndex] === 'report' && pathIndex + 1 < pathParts.length) {
                    urlReportId = pathParts[pathIndex + 1];
                }
            }
        }
        
        if (urlTagId) {
            currentView = 'tag';
            currentTagId = urlTagId;
            currentTagSlug = tagSlugMap[urlTagId] || null;
            currentSessionId = null;
            currentReportId = null;
        } else if (urlReportId) {
            currentView = 'report';
            currentReportId = parseInt(urlReportId);
            currentSessionId = urlSessionId ? parseInt(urlSessionId) : null;
            currentTagSlug = null;
            currentTagId = null;
        } else if (urlSessionId) {
            currentView = 'session';
            currentSessionId = parseInt(urlSessionId);
            currentReportId = null;
            currentTagSlug = null;
            currentTagId = null;
        } else {
            currentView = 'all';
            currentSessionId = null;
            currentReportId = null;
            currentTagSlug = null;
            currentTagId = null;
        }
        
        // Reload votes and update UI
        activateNavItem();
        loadVotes();
    });
    
})(jQuery);
</script>

<!-- Defensive patch for theme navMenu error - runs immediately -->
<script>
(function() {
    'use strict';
    // Prevent "can't access property classList, navMenu is null" error
    // Patch the function to add null checking
    if (typeof window.hideNavMenuScrollHideSm === 'function') {
        const original = window.hideNavMenuScrollHideSm;
        window.hideNavMenuScrollHideSm = function() {
            try {
                return original.apply(this, arguments);
            } catch (e) {
                if (e && e.message && (e.message.includes('navMenu') || e.message.includes('classList'))) {
                    console.warn('hideNavMenuScrollHideSm: Navigation menu element not found');
                    return;
                }
                throw e;
            }
        };
    }
    
    // Also create a safe stub if function doesn't exist yet (will be overwritten by theme)
    if (typeof window.hideNavMenuScrollHideSm === 'undefined') {
        window.hideNavMenuScrollHideSm = function() {
            // Safe no-op until theme defines it
        };
    }
    
})();
</script>
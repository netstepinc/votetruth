<?php
/**
 * Legislator Vote History
 *
 * Receives from legislator.php controller (all variables extracted by fi_get_public_template):
 *   $legislator         array   full legislator row
 *   $legislator_id      int
 *   $sessions           array   ARRAY_A rows from fi_legislator_sessions_get_history()
 *   $current_session    array   active session row
 *   $display_votes      array   current-session votes with cast added (for server-render)
 *   $all_tags           array   career tag scores sorted by vote_count [{id,name,vote_count,...}]
 *   $current_report_id  int|null
 *   $current_tag_id     int|null
 *   $base_url           string
 *   $gov                string
 *   $chamber            string
 *
 * Server-renders the initial session view for SEO.
 * AJAX handles all subsequent navigation (sessions, reports, tags).
 */

if (!defined('ABSPATH')) exit;

$party              = $legislator['party']          ?? '';
$score_career       = $legislator['score']          ?? null;
$current_session_id = $current_session ? (int) $current_session['session_id'] : 0;
$session_name       = $current_session ? ($current_session['session_name'] ?? '') : '';
$session_score      = $current_session ? ($current_session['score_session'] ?? null) : null;

// Reports per session (objects — fi_reports_get returns WP_Post-like rows)
$session_reports = [];
foreach ($sessions as $session) {
	$sid     = (int) $session['session_id'];
	$reports = fi_reports_get([
		'session_id' => $sid,
		'status'     => 'publish',
		'orderby'    => 'date_publish',
		'order'      => 'DESC',
	]);
	$reports = fi_reports_sort_by_format($gov, $reports);
	if (!empty($reports)) {
		$session_reports[$sid] = $reports;
	}
}

// Report metadata for JS (format + PDF URLs)
$report_formats  = [];
$report_pdf_urls = [];
foreach ($session_reports as $sid => $reports) {
	foreach ($reports as $report) {
		$rid     = is_array($report) ? (int) ($report['id'] ?? 0) : (int) ($report->id ?? 0);
		$fmt     = is_array($report) ? ($report['format'] ?? 'scorecard') : ($report->format ?? 'scorecard');
		$raw     = is_array($report) ? ($report['payload_json'] ?? '{}') : ($report->payload_json ?? '{}');
		$payload = is_array($raw) ? $raw : (json_decode($raw, true) ?: []);
		$report_formats[$rid] = $fmt;
		$pdf_url = trim((string) ($payload['report_pdf_url'] ?? ''));
		if ($pdf_url !== '') {
			$report_pdf_urls[$rid] = $pdf_url;
		}
	}
}

// Determine initial JS view
$default_view = $current_session_id ? 'session' : 'all';
if ($current_tag_id)    $default_view = 'tag';
elseif ($current_report_id) $default_view = 'report';

// Server-render initial votes for SEO (skips AJAX on first load)
$initial_votes_html = '';
if (!empty($display_votes)) {
	$initial_votes_html = '<div class="row g-3" id="fi-vote-cards-container">';
	foreach ($display_votes as $vote) {
		$card_args = fi_public_ajax_vote_history_prepare_vote_card_data($vote, [
			'gov'           => $gov,
			'report_format' => 'scorecard',
		]);
		$initial_votes_html .= fi_get_template_html('vote-card', $card_args);
	}
	$initial_votes_html .= '</div>';
}

$initial_title = $session_name ? $session_name . ' Votes' : 'Votes';
$initial_score = $session_score;
?>

<style>
#accordionVoteNav .list-group-item.active {
	background-color: #fff;
	border: 1px solid #000;
	border-radius: 0.25rem;
	color: #000;
}
</style>

<div id="fi-legislator-vote-history" class="row g-3">

	<!-- ── SIDEBAR ─────────────────────────────────────────────────── -->
	<div class="col-12 col-lg-4 col-xxl-3">

		<!-- Mobile toggle -->
		<button id="fi-vote-nav-toggle"
			class="btn btn-sm btn-outline-primary w-100 d-lg-none mb-3"
			type="button"
			data-bs-toggle="collapse"
			data-bs-target="#fi-vote-nav-collapse"
			aria-expanded="false"
			aria-controls="fi-vote-nav-collapse">
			<i class="bi bi-list me-2" aria-hidden="true"></i>
			<span class="fi-nav-text fw-bold">Select Session or Report</span>
		</button>

		<div class="collapse d-lg-block" id="fi-vote-nav-collapse">
			<div class="card rounded-4 shadow-sm">
				<div class="card-header rounded-top-4 bg-white">
					<h2 class="h5 mb-0">Voting History</h2>
				</div>
				<div class="card-body p-0 pb-4">
					<div class="accordion accordion-flush" id="accordionVoteNav">

						<!-- All Votes + Tags by issue -->
						<div class="accordion-item">
							<h3 class="accordion-header">
								<button class="accordion-button caret-tight py-2 collapsed"
									type="button"
									data-bs-toggle="collapse"
									data-bs-target="#fi-acc-all"
									aria-expanded="false"
									aria-controls="fi-acc-all">
									<span class="me-auto fs-7">All Votes / Issues</span>
									<?php if ($score_career !== null): ?>
										<span class="badge bg-primary fs-8 ms-2"><?php echo (int) $score_career; ?>%</span>
									<?php endif; ?>
								</button>
							</h3>
							<div id="fi-acc-all" class="accordion-collapse collapse" data-bs-parent="#accordionVoteNav">
								<div class="accordion-body p-0">
									<ul class="list-group list-group-flush mb-0">
										<li class="list-group-item border-0">
											<a href="<?php echo esc_url($base_url); ?>"
												class="list-group-item list-group-item-action fi-nav-item fs-7"
												data-view="all" data-type="all">
												All Votes
											</a>
										</li>
										<?php foreach ($all_tags as $tag): ?>
										<li class="list-group-item border-0">
											<a href="<?php echo esc_url($base_url . 'issue/' . (int) $tag['id'] . '/'); ?>"
												class="list-group-item list-group-item-action fi-nav-item fs-7 d-flex justify-content-between"
												data-view="tag"
												data-tag-id="<?php echo (int) $tag['id']; ?>">
												<span><?php echo esc_html($tag['name']); ?></span>
												<span class="badge bg-secondary"><?php echo (int) $tag['vote_count']; ?></span>
											</a>
										</li>
										<?php endforeach; ?>
									</ul>
								</div>
							</div>
						</div>

						<!-- Sessions -->
						<?php foreach ($sessions as $session):
							$sid         = (int) $session['session_id'];
							$sname       = $session['session_name'] ?? 'Session';
							$sscore      = isset($session['score_session']) && $session['score_session'] !== null
								? (int) $session['score_session'] : null;
							$is_current  = ($sid === $current_session_id);
							$has_reports = !empty($session_reports[$sid]);
						?>
						<div class="accordion-item">
							<h3 class="accordion-header">
								<button class="accordion-button caret-tight py-2 collapsed"
									type="button"
									data-bs-toggle="collapse"
									data-bs-target="#fi-acc-<?php echo $sid; ?>"
									aria-expanded="<?php echo $is_current ? 'true' : 'false'; ?>"
									aria-controls="fi-acc-<?php echo $sid; ?>">
									<span class="me-auto fs-7"><?php echo esc_html($sname); ?></span>
									<?php if ($sscore !== null): ?>
										<span class="badge bg-primary fs-8 ms-2"><?php echo $sscore; ?>%</span>
									<?php endif; ?>
								</button>
							</h3>
							<div id="fi-acc-<?php echo $sid; ?>"
								class="accordion-collapse collapse<?php echo $is_current ? ' show' : ''; ?>"
								data-bs-parent="#accordionVoteNav">
								<div class="accordion-body p-0">
									<ul class="list-group list-group-flush mb-0">
										<li class="list-group-item border-0">
											<a href="<?php echo esc_url($base_url . 'session/' . $sid . '/'); ?>"
												class="list-group-item list-group-item-action fi-nav-item fs-7<?php echo ($is_current && !$current_report_id && !$current_tag_id) ? ' active' : ''; ?>"
												data-view="session"
												data-session-id="<?php echo $sid; ?>">
												All Session Votes
											</a>
										</li>
										<?php if ($has_reports):
											foreach ($session_reports[$sid] as $report):
												$rid    = is_array($report) ? (int) ($report['id'] ?? 0) : (int) ($report->id ?? 0);
												$rtitle = is_array($report) ? ($report['title'] ?? '') : ($report->title ?? '');
												$is_cur = $is_current && ($current_report_id === $rid);
										?>
										<li class="list-group-item border-0">
											<a href="<?php echo esc_url($base_url . 'session/' . $sid . '/report/' . $rid . '/'); ?>"
												class="list-group-item list-group-item-action fi-nav-item fs-7 ps-4<?php echo $is_cur ? ' active bg-primary text-white' : ''; ?>"
												data-view="report"
												data-session-id="<?php echo $sid; ?>"
												data-report-id="<?php echo $rid; ?>">
												<?php echo esc_html($rtitle); ?>
											</a>
										</li>
										<?php endforeach; endif; ?>
									</ul>
								</div>
							</div>
						</div>
						<?php endforeach; ?>

					</div><!-- /accordion -->
				</div>
			</div>
		</div>
	</div><!-- /sidebar -->

	<!-- ── MAIN CONTENT ────────────────────────────────────────────── -->
	<div class="col-12 col-lg-8 col-xxl-9">
		<div class="card rounded-4 shadow-sm">

			<div class="card-header rounded-top-4 bg-white border-bottom">
				<div class="row align-items-center g-2">
					<div class="col">
						<h3 class="h5 mb-0" id="fi-vote-list-title">
							<?php echo esc_html($initial_title); ?>
						</h3>
					</div>
					<div class="col-auto">
						<!-- Score + PDF buttons (hidden when search active) -->
						<div id="fi-vote-score-container"
							<?php echo ($initial_score !== null) ? '' : 'style="display:none;"'; ?>>
							<div class="btn-group btn-group-sm" role="group">
								<a href="#" class="btn btn-outline-danger fs-7 fw-bold" target="_blank"
									id="fi-vote-pdf-btn" style="display:none;">
									<i class="bi bi-file-pdf me-1" aria-hidden="true"></i>PDF
								</a>
								<a href="#" class="btn btn-outline-danger fs-7 fw-bold" target="_blank"
									id="fi-vote-pdf-portrait-btn" style="display:none;">
									<i class="bi bi-file-pdf me-1" aria-hidden="true"></i>PDF
								</a>
								<a href="#" class="btn btn-outline-danger fs-7 fw-bold" target="_blank"
									id="fi-vote-pdf-bifold-btn" style="display:none;">
									<i class="bi bi-file-pdf me-1" aria-hidden="true"></i>PDF Bi-Fold
								</a>
								<span id="fi-vote-score-btn"
									class="btn btn-primary fs-7 fw-bold"
									style="pointer-events:none; cursor:default;">
									Score:&nbsp;<span id="fi-vote-score-value">
										<?php echo $initial_score !== null ? (int) $initial_score : ''; ?>
									</span>%
								</span>
							</div>
						</div>
						<!-- Search (All Votes only) -->
						<div id="fi-vote-search-container" style="display:none;">
							<input type="search" class="form-control form-control-sm"
								id="fi-vote-search" placeholder="Search votes…"
								aria-label="Search votes"
								style="min-width:200px;">
						</div>
					</div>
				</div>
				<div id="fi-vote-list-subtitle" class="text-muted small mt-1"></div>
			</div>

			<div class="card-body p-3 pt-2">
				<div id="fi-vote-list-container">
					<?php if (!empty($initial_votes_html)): ?>
						<?php echo $initial_votes_html; ?>
					<?php else: ?>
						<div class="alert alert-info">No votes found for this session.</div>
					<?php endif; ?>
				</div>
			</div>

		</div>
	</div>

</div><!-- /row -->

<!-- Shared vote detail modal for modal_mode='page' -->
<div class="modal fade" id="fi-vote-detail-modal" tabindex="-1"
	aria-labelledby="fi-vote-detail-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h2 class="modal-title fs-5 fw-bold" id="fi-vote-detail-modal-label">Vote Details</h2>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body" id="fi-vote-detail-content"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<script>
(function($) {
	'use strict';

	var legislatorId     = <?php echo (int) $legislator_id; ?>;
	var chamber          = <?php echo json_encode($chamber); ?>;
	var party            = <?php echo json_encode($party); ?>;
	var reportFormats    = <?php echo json_encode($report_formats); ?>;
	var reportPdfUrls    = <?php echo json_encode($report_pdf_urls); ?>;
	var nonce            = '<?php echo wp_create_nonce('fi_ajax_nonce'); ?>';
	var ajaxUrl          = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
	var baseUrl          = '<?php echo esc_js(rtrim($base_url, '/') . '/'); ?>';

	// Current view state (set from PHP, may be overridden by URL on init)
	var currentView      = <?php echo json_encode($default_view); ?>;
	var currentSessionId = <?php echo json_encode($current_session_id ?: null); ?>;
	var currentReportId  = <?php echo json_encode($current_report_id); ?>;
	var currentTagId     = <?php echo json_encode($current_tag_id); ?>;

	// Track whether the initial session view was server-rendered
	var serverRendered   = <?php echo !empty($initial_votes_html) ? 'true' : 'false'; ?>;

	window.fiReportFormats = reportFormats;

	var $container      = $('#fi-vote-list-container');
	var $title          = $('#fi-vote-list-title');
	var $subtitle       = $('#fi-vote-list-subtitle');
	var $scoreContainer = $('#fi-vote-score-container');
	var $scoreValue     = $('#fi-vote-score-value');
	var $searchContainer= $('#fi-vote-search-container');
	var $detailModal    = $('#fi-vote-detail-modal');
	var detailModalInst = null;

	// ── URL helpers ─────────────────────────────────────────────────

	function buildCurrentUrl() {
		var base = window.location.origin + '/legislator/' + legislatorId;
		if (currentTagId)    return base + '/issue/' + currentTagId + '/';
		if (currentSessionId) {
			var u = base + '/session/' + currentSessionId;
			if (currentReportId) u += '/report/' + currentReportId;
			return u + '/';
		}
		return base + '/';
	}

	function updateUrl() {
		var newUrl = buildCurrentUrl();
		if (window.location.href !== newUrl) {
			window.history.pushState(
				{ view: currentView, sessionId: currentSessionId, reportId: currentReportId },
				'',
				newUrl
			);
			updateOgUrl(newUrl);
		}
	}

	function updateOgUrl(url) {
		url = url || window.location.href;
		var og = document.querySelector('meta[property="og:url"]');
		if (og) og.setAttribute('content', url);
		var can = document.querySelector('link[rel="canonical"]');
		if (can) can.setAttribute('href', url);
	}

	// ── PDF buttons ──────────────────────────────────────────────────

	function buildPdfUrl(format) {
		return buildCurrentUrl().replace(/\/$/, '') + '/pdf/' + format + '/';
	}

	function updatePdfButtons() {
		var $pdf    = $('#fi-vote-pdf-btn');
		var $port   = $('#fi-vote-pdf-portrait-btn');
		var $bifold = $('#fi-vote-pdf-bifold-btn');

		if (currentView === 'report' && currentReportId) {
			var fmt = reportFormats[currentReportId] || 'scorecard';
			if (fmt === 'freedomindex') {
				var pdfUrl = reportPdfUrls[currentReportId];
				if (pdfUrl) { $pdf.attr('href', pdfUrl).show(); } else { $pdf.hide(); }
				$port.hide(); $bifold.hide();
			} else {
				$pdf.hide();
				$port.attr('href', buildPdfUrl('sca')).show();
				$bifold.attr('href', buildPdfUrl('scb')).show();
			}
		} else {
			$pdf.hide(); $port.hide(); $bifold.hide();
		}
	}

	// ── Nav activation ───────────────────────────────────────────────

	function activateNavItem() {
		$('.fi-nav-item').removeClass('active bg-primary text-white');

		if (currentView === 'report' && currentReportId && currentSessionId) {
			expandAccordion(currentSessionId, function() {
				$('.fi-nav-item[data-view="report"][data-session-id="' + currentSessionId + '"][data-report-id="' + currentReportId + '"]')
					.addClass('active bg-primary text-white');
			});
		} else if (currentView === 'session' && currentSessionId) {
			expandAccordion(currentSessionId, function() {
				$('.fi-nav-item[data-view="session"][data-session-id="' + currentSessionId + '"]')
					.first().addClass('active bg-primary text-white');
			});
		} else if (currentView === 'tag' && currentTagId) {
			expandAccordion('all', function() {
				$('.fi-nav-item[data-view="tag"][data-tag-id="' + currentTagId + '"]')
					.addClass('active bg-primary text-white');
			});
		} else {
			expandAccordion('all', function() {
				$('.fi-nav-item[data-view="all"]').addClass('active bg-primary text-white');
			});
		}
	}

	function expandAccordion(id, callback) {
		var el = document.getElementById(id === 'all' ? 'fi-acc-all' : 'fi-acc-' + id);
		if (!el) { callback(); return; }
		if (el.classList.contains('show')) { callback(); return; }
		el.addEventListener('shown.bs.collapse', callback, { once: true });
		if (window.bootstrap && window.bootstrap.Collapse) {
			var inst = window.bootstrap.Collapse.getInstance(el)
				|| new window.bootstrap.Collapse(el, { toggle: false });
			inst.show();
		} else {
			// No Bootstrap JS - force open and fire callback immediately
			el.classList.add('show');
			callback();
		}
	}

	// ── Vote loading ──────────────────────────────────────────────────

	function loadVotes() {
		$container.html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading\u2026</span></div></div>');
		$.ajax({
			url:  ajaxUrl,
			type: 'POST',
			data: {
				action:        'fi_legislator_vote_history',
				legislator_id: legislatorId,
				chamber:       chamber,
				party:         party,
				view:          currentView,
				session_id:    currentSessionId,
				report_id:     currentReportId,
				tag_id:        currentTagId,
				nonce:         nonce,
			},
			success: function(res) {
				if (res.success && res.data) {
					renderVotes(res.data);
				} else {
					var msg = (res.data && res.data.message) ? res.data.message : 'No votes found.';
					var div = document.createElement('div');
					div.textContent = msg;
					$container.html('<div class="alert alert-warning">' + div.innerHTML + '</div>');
				}
			},
			error: function() {
				$container.html('<div class="alert alert-danger">Error loading votes. Please try again.</div>');
			},
		});
	}

	function renderVotes(data) {
		$title.text(data.title || 'Votes');
		$subtitle.text(data.subtitle || '');

		var isAll = currentView === 'all';
		if (isAll) {
			$searchContainer.show();
			$scoreContainer.hide();
		} else {
			$searchContainer.hide();
			if (data.score !== null && data.score !== undefined) {
				$scoreValue.text(data.score);
				$scoreContainer.show();
			} else {
				$scoreContainer.hide();
			}
		}

		$container.html(data.html
			? data.html
			: '<div class="alert alert-info">No votes found for this selection.</div>');
	}

	// ── Search ───────────────────────────────────────────────────────

	var searchTimeout;
	$('#fi-vote-search').on('input', function() {
		clearTimeout(searchTimeout);
		var term = $(this).val().toLowerCase();
		searchTimeout = setTimeout(function() {
			$('.fi-vote-card').each(function() {
				var txt = ($(this).data('search-text') || '').toString().toLowerCase();
				$(this).toggle(!term || txt.includes(term));
			});
		}, 250);
	});

	// ── Nav click handler ────────────────────────────────────────────

	$(document).on('click', '.fi-nav-item', function(e) {
		e.preventDefault();
		var $item     = $(this);
		var view      = $item.data('view');
		var sessionId = $item.data('session-id') || null;
		var reportId  = $item.data('report-id')  || null;
		var tagId     = $item.data('tag-id')      || null;

		$('.fi-nav-item').removeClass('active bg-primary text-white');
		$item.addClass('active bg-primary text-white');

		currentView = view;
		if (view === 'tag' && tagId) {
			currentTagId     = tagId;
			currentSessionId = null;
			currentReportId  = null;
		} else {
			currentTagId     = null;
			currentSessionId = sessionId ? parseInt(sessionId, 10) : null;
			currentReportId  = reportId  ? parseInt(reportId,  10) : null;
		}

		serverRendered = false; // subsequent loads always via AJAX
		updateUrl();
		updatePdfButtons();

		// Close mobile nav
		if (window.innerWidth < 992) {
			var $navCollapse = document.getElementById('fi-vote-nav-collapse');
			if ($navCollapse && window.bootstrap && window.bootstrap.Collapse) {
				var inst = window.bootstrap.Collapse.getInstance($navCollapse);
				if (inst) inst.hide();
			}
		}

		loadVotes();
	});

	// ── Shared vote detail modal (modal_mode='page') ──────────────────

	$(document).on('click', '.fi-vote-readmore', function(e) {
		e.preventDefault();
		var title = $(this).data('vote-title') || 'Vote Details';
		var body  = $(this).data('vote-body')  || '';
		$('#fi-vote-detail-modal-label').text(title);
		$('#fi-vote-detail-content').html(body || '<p class="text-muted">No additional details available.</p>');
		if (!detailModalInst && window.bootstrap && window.bootstrap.Modal) {
			detailModalInst = new window.bootstrap.Modal($detailModal[0]);
		}
		detailModalInst ? detailModalInst.show() : $detailModal.modal('show');
	});

	// ── Mobile nav toggle text ────────────────────────────────────────

	var $navToggle  = $('#fi-vote-nav-toggle');
	var $navText    = $navToggle.find('.fi-nav-text');
	var $navCollapse= $('#fi-vote-nav-collapse');

	$navCollapse[0] && $navCollapse[0].addEventListener('show.bs.collapse', function() {
		$navText.text('Close Menu');
	});
	$navCollapse[0] && $navCollapse[0].addEventListener('hide.bs.collapse', function() {
		$navText.text('Select Session or Report');
	});

	// ── Browser back/forward ──────────────────────────────────────────

	window.addEventListener('popstate', function() {
		parseUrlIntoState();
		updateOgUrl();
		activateNavItem();
		loadVotes();
	});

	function parseUrlIntoState() {
		var parts   = window.location.pathname.split('/').filter(Boolean);
		var tagIdx  = parts.indexOf('issue');
		var sessIdx = parts.indexOf('session');
		var repIdx  = parts.indexOf('report');

		currentTagId = currentSessionId = currentReportId = null;

		if (tagIdx !== -1 && parts[tagIdx + 1] && /^\d+$/.test(parts[tagIdx + 1])) {
			currentView  = 'tag';
			currentTagId = parseInt(parts[tagIdx + 1], 10);
		} else if (sessIdx !== -1 && parts[sessIdx + 1]) {
			currentSessionId = parseInt(parts[sessIdx + 1], 10);
			if (repIdx !== -1 && parts[repIdx + 1]) {
				currentView     = 'report';
				currentReportId = parseInt(parts[repIdx + 1], 10);
			} else {
				currentView = 'session';
			}
		} else {
			currentView = 'all';
		}
	}

	// ── Init ──────────────────────────────────────────────────────────

	$(function() {
		parseUrlIntoState(); // in case URL differs from PHP state (e.g. issue/ in path)
		updateOgUrl();
		updatePdfButtons();
		activateNavItem();

		// Skip first AJAX load if we have server-rendered content
		if (serverRendered && currentView === 'session') {
			// Content is already in DOM; update title/score display only
			$title.text(<?php echo json_encode($initial_title); ?>);
			if (<?php echo $initial_score !== null ? (int) $initial_score : 'null'; ?> !== null) {
				$scoreValue.text(<?php echo $initial_score !== null ? (int) $initial_score : '0'; ?>);
				$scoreContainer.show();
			}
			$subtitle.text('');
			return;
		}

		loadVotes();
	});

})(jQuery);
</script>

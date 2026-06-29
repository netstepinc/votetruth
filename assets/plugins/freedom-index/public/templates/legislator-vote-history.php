<?php
/**
 * Legislator Vote History — client-side filtering (no AJAX)
 */

if (!defined('ABSPATH')) exit;

$party               = $legislator['party'] ?? '';
$current_session_id  = $selected_session ? (int) $selected_session['session_id'] : 0;
$initial_title       = (string) ($initial_group['title'] ?? 'Votes');
$initial_score       = $initial_group['score'] ?? null;
$initial_content     = (string) ($initial_group['content'] ?? '');
$initial_tag_desc    = (string) ($initial_group['description'] ?? '');

// Card HTML pre-rendered in compile pass — concatenate directly, no template call needed.
$initial_votes_html = '';
if (!empty($display_votes)) {
	$initial_votes_html = '<div id="fi-vote-cards-container">';
	foreach ($display_votes as $card_html) {
		$initial_votes_html .= $card_html;
	}
	$initial_votes_html .= '</div>';
}

// JS payload — card_html is the pre-rendered card; modal fields kept for showVoteDetailModal().
$votes_json = [];
foreach ($votes_map as $vote_id => $vote) {
	$votes_json[$vote_id] = [
		'id'             => (int) $vote_id,
		'title'          => $vote['title'],
		'impact_summary' => $vote['impact_summary'] ?? '',
		'text_more'      => $vote['text_more'],
		'bill_url'       => $vote['bill_url'],
		'date_voted'     => $vote['date_voted'],
		'search_text'    => $vote['search_text'],
		'card_html'      => $vote['card_html'] ?? '',
	];
}
?>

<section id="fi-legislator-vote-history" class="bg-white border-bottom py-3" aria-label="Voting record">
	<div class="container-xl">

		<div class="d-flex flex-wrap mb-0">
			<h2 class="h5 text-muted mb-0"><span class="d-none d-md-inline">Session </span>Voting Records</h2>
			<button type="button"
				class="btn btn-sm btn-link text-decoration-none p-0 ms-auto fw-semibold fi-nav-item<?php echo ($default_view === 'all') ? ' d-none' : ''; ?>"
				id="fi-view-all-votes"
				data-view="all"
				data-session-id="">View<span class="d-none d-md-inline"> / Search</span> all Votes
			</button>
		</div>

		<div class="mb-2">
			<div class="fi-scroll-rail mx-n3 py-1" id="fi-session-rail" role="tablist" aria-label="Sessions">
				<?php foreach ($sessions as $session):
					$sid = (int) ($session['session_id'] ?? 0);
					$smeta = $sessions_meta[$sid] ?? $session;
					$sname = (string) ($smeta['session_name'] ?? ($session['session_name'] ?? 'Session'));
					$sscore = $smeta['score'] ?? null;
				?>
				<?php $session_chip_active = ($sid === $active_session_id && $default_view !== 'all' && $default_view !== 'tag');?>
				<button type="button"
					class="btn fi-scroll-rail-item fi-vh-session-cell fi-nav-item border rounded-3 py-2 px-3 text-start fw-semibold<?php echo $session_chip_active ? ' btn-primary border-primary' : ' btn-light'; ?>"
					data-view="session"
					data-session-id="<?php echo $sid; ?>">
					<div class="small fw-bold lh-sm text-nowrap"><?php echo esc_html($sname); ?></div>
					<div class="fs-5 fw-bolder"><?php echo is_numeric($sscore) ? (int) $sscore . '%' : 'N/A'; ?></div>
				</button>
				<?php endforeach; ?>
			</div>
		</div>

		<div id="fi-report-chips-wrap" class="mb-2"<?php echo ($default_view === 'all' || $default_view === 'tag') ? ' style="display:none;"' : ''; ?>>
			<div class="text-muted small text-uppercase fw-bold mb-1">Reports</div>
			<div class="fi-scroll-rail mx-n3 px-3 pb-1" id="fi-report-chips" role="tablist" aria-label="Reports"></div>
		</div>

	</div>
</section>

<div class="container-fluid bg-light border-top">
	<div class="container-xl py-2">
		<div class="row align-items-center g-2 mb-3">
			<div class="col">
				<h3 class="h2 fs-6 mb-0" id="fi-vote-list-title"><?php echo esc_html($initial_title); ?></h3>
				<div id="fi-vote-list-subtitle" class="text-muted fs-8 lh-1 mt-1"><?php echo esc_html($initial_group['subtitle'] ?? ''); ?></div>
			</div>
			<div class="col-auto">
				<div id="fi-vote-score-container"<?php echo ($initial_score !== null && $default_view !== 'all') ? '' : ' style="display:none;"'; ?>>
					<div class="btn-group btn-group-sm rounded-3" role="group" id="fi-vote-score-action">
						<button type="button" class="btn btn-outline-danger fs-7 fw-bold" id="fi-vote-pdf-btn" style="display:none;" data-bs-toggle="modal" data-bs-target="#fi-print-modal">PDF</button>
						<span id="fi-vote-score-btn" class="btn btn-primary fs-7 fw-bold" style="pointer-events:none;cursor:default;">
							Score:&nbsp;<span id="fi-vote-score-value"><?php echo is_numeric($initial_score) ? (int) $initial_score : 'N/A'; ?></span>
						</span>
					</div>
				</div>
				<div id="fi-vote-search-container"<?php echo ($default_view === 'all') ? '' : ' style="display:none;"'; ?>>
					<input type="search" class="form-control form-control-sm" id="fi-vote-search"
						placeholder="Search votes…" aria-label="Search votes" style="min-width:200px;">
				</div>
			</div>
		</div>

		<div id="fi-vote-list-content" class="mb-3 fs-7"<?php echo $initial_content ? '' : ' style="display:none;"'; ?>>
			<?php echo $initial_content ? wp_kses_post($initial_content) : ''; ?>
		</div>
		<div id="fi-tag-description" class="mb-3 text-muted small"<?php echo $initial_tag_desc ? '' : ' style="display:none;"'; ?>>
			<?php echo $initial_tag_desc ? esc_html($initial_tag_desc) : ''; ?>
		</div>
		<div id="fi-vote-list-container">
			<?php if ($initial_votes_html): ?>
				<?php echo $initial_votes_html; ?>
			<?php else: ?>
				<div class="alert alert-info" id="no-votes-found">No votes found for this selection.</div>
			<?php endif; ?>
		</div>
		<div class="text-center mt-3" id="fi-vote-load-more-wrap" style="display:none;">
			<button type="button" class="btn btn-outline-primary btn-sm" id="fi-vote-load-more">Load More</button>
		</div>

	</div>
</div>

<div class="modal fade" id="fi-vote-detail-modal" tabindex="-1" aria-labelledby="fi-vote-detail-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h2 class="modal-title fs-7 fw-bold" id="fi-vote-detail-modal-label">Vote Details</h2>
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

	var legislatorId = <?php echo (int) $legislator_id; ?>;
	var legislatorBaseUrl = <?php echo wp_json_encode(trailingslashit($base_url)); ?>;
	var voteGroups = <?php echo wp_json_encode($vote_groups); ?>;
	var votesData = <?php echo wp_json_encode($votes_json); ?>;
	var defaultPrintModalReportBase = <?php echo wp_json_encode($default_print_modal_report_base); ?>;
	var defaultView = <?php echo wp_json_encode($default_view); ?>;
	var defaultSessionId = <?php echo wp_json_encode($active_session_id ?: null); ?>;
	var defaultReportId = <?php echo wp_json_encode($active_report_id ?: null); ?>;
	var defaultTagId = <?php echo wp_json_encode($active_tag_id ?: null); ?>;

	var PAGE_SIZE = 25;
	var visibleLimit = <?php echo ($default_view === 'all') ? 25 : max(count($initial_vote_ids), 25); ?>;
	var serverRendered = <?php echo $initial_votes_html ? 'true' : 'false'; ?>;

	var state = {
		view: defaultView,
		sessionId: defaultSessionId,
		reportId: defaultReportId,
		tagId: defaultTagId,
	};

	var $container = $('#fi-vote-list-container');
	var $title = $('#fi-vote-list-title');
	var $subtitle = $('#fi-vote-list-subtitle');
	var $content = $('#fi-vote-list-content');
	var $tagDesc = $('#fi-tag-description');
	var $search = $('#fi-vote-search');
	var $searchWrap = $('#fi-vote-search-container');
	var $scoreWrap = $('#fi-vote-score-container');
	var $scoreAction = $('#fi-vote-score-action');
	var $scoreValue = $('#fi-vote-score-value');
	var $loadMoreWrap = $('#fi-vote-load-more-wrap');
	var $reportChipsWrap = $('#fi-report-chips-wrap');
	var $reportChips = $('#fi-report-chips');
	var detailModalInst = null;

	function escHtml(str) {
		return String(str || '').replace(/[&<>"']/g, function(m) {
			return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]);
		});
	}

	function buildUrlFromState() {
		var url = legislatorBaseUrl;
		if (state.view === 'tag' && state.tagId) {
			return url + 'issue/' + state.tagId + '/';
		}
		if (state.sessionId) {
			url += 'session/' + state.sessionId + '/';
			if (state.reportId) url += 'report/' + state.reportId + '/';
		}
		return url;
	}

	function pushStateFromSelection() {
		var nextUrl = buildUrlFromState();
		window.history.pushState(Object.assign({}, state), '', nextUrl);
		updateOgUrl(nextUrl);
		updatePrintModalReportBase();
	}

	function updateOgUrl(url) {
		var og = document.querySelector('meta[property="og:url"]');
		if (og) og.setAttribute('content', url);
	}

	function updatePrintModalReportBase() {
		var baseUrl = '';
		if (state.sessionId && state.reportId) {
			baseUrl = buildUrlFromState();
		} else if (defaultPrintModalReportBase) {
			baseUrl = defaultPrintModalReportBase;
		}
		if (!baseUrl) return;
		var modal = document.getElementById('fi-print-modal') || document.getElementById('printModal');
		if (!modal) return;
		modal.querySelectorAll('.fi-print-pdf-btn').forEach(function(btn) {
			btn.setAttribute('data-pdf-base', baseUrl);
		});
		modal.dispatchEvent(new CustomEvent('fi-print-report-base-changed'));
	}

	function getActiveGroup() {
		if (state.view === 'tag' && state.tagId && voteGroups.tags && voteGroups.tags[state.tagId]) {
			return voteGroups.tags[state.tagId];
		}
		if (state.sessionId && voteGroups.sessions && voteGroups.sessions[state.sessionId]) {
			if (state.reportId) {
				var reports = voteGroups.sessions[state.sessionId].reports || [];
				for (var i = 0; i < reports.length; i++) {
					if (Number(reports[i].id) === Number(state.reportId)) return reports[i];
				}
			}
			return voteGroups.sessions[state.sessionId];
		}
		return voteGroups.all;
	}

	function showVoteDetailModal(voteId) {
		var vote = votesData[voteId];
		if (!vote) return;
		var title = vote.title || 'Vote Details';
		var body = '';
		// Impact summary (the "why it matters") shown first when present.
		if (vote.impact_summary) {
			body += '<div class="fi-vote-impact-summary mb-3">' + vote.impact_summary + '</div>';
		}
		if (vote.text_more) {
			body += '<div class="fi-vote-detail-text mb-3">' + vote.text_more + '</div>';
		}
		if (vote.bill_url) {
			body += '<p class="mb-3"><a href="' + escHtml(vote.bill_url) + '" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener noreferrer">View Bill Text</a></p>';
		}
		if (!body) body = '<p class="text-muted">No additional details available.</p>';
		$('#fi-vote-detail-modal-label').text(title);
		$('#fi-vote-detail-content').html(body);
		var el = document.getElementById('fi-vote-detail-modal');
		if (!detailModalInst && window.bootstrap && window.bootstrap.Modal) {
			detailModalInst = window.bootstrap.Modal.getOrCreateInstance(el);
		}
		detailModalInst ? detailModalInst.show() : $(el).modal('show');
	}

	function sortVoteIdsByDate(ids) {
		return ids.slice().sort(function(a, b) {
			var va = votesData[a] || {};
			var vb = votesData[b] || {};
			var da = va.date_voted ? Date.parse(va.date_voted) : 0;
			var db = vb.date_voted ? Date.parse(vb.date_voted) : 0;
			if (db !== da) return db - da;
			return b - a;
		});
	}

	function getFilteredVoteIds(group) {
		var ids = (group && group.votes) ? group.votes.map(Number) : [];
		// Report views keep admin-defined vote order; all other views sort by date.
		if (state.view !== 'report') {
			ids = sortVoteIdsByDate(ids);
		}
		var term = (state.view === 'all') ? String($search.val() || '').toLowerCase().trim() : '';
		if (!term) return ids;
		return ids.filter(function(id) {
			var vote = votesData[id];
			return vote && String(vote.search_text || '').toLowerCase().indexOf(term) !== -1;
		});
	}

	function renderCards(forceRebuild) {
		if (serverRendered && !forceRebuild) {
			serverRendered = false;
			return;
		}
		var group = getActiveGroup();
		var ids = getFilteredVoteIds(group);
		var slice = ids.slice(0, visibleLimit);
		var html = '';
		if (slice.length) {
			html = '<div id="fi-vote-cards-container">';
			slice.forEach(function(id) {
				var vote = votesData[id];
				html += (vote && vote.card_html) ? vote.card_html : '';
			});
			html += '</div>';
		} else {
			html = '<div class="alert alert-info" id="no-votes-found">No votes found for this selection.</div>';
		}
		$container.html(html);
		$loadMoreWrap.toggle(ids.length > visibleLimit);
	}

	function updateHeader(group) {
		group = group || getActiveGroup();
		$title.text(group.title || 'Votes');
		if (group.subtitle) { $subtitle.text(group.subtitle).show(); } else { $subtitle.text('').hide(); }
		if (group.content) { $content.html(group.content).show(); } else { $content.empty().hide(); }
		// Tag description — shown only when a tag filter is active and the tag has a description.
		var desc = (state.view === 'tag' && group.description) ? group.description : '';
		if (desc) { $tagDesc.text(desc).show(); } else { $tagDesc.text('').hide(); }

		var actions = group.actions || {};
		var hasControls = !!(actions.share || actions.score || actions.pdf || actions.pdfa || actions.pdfb);
		$searchWrap.toggle(!!actions.search);
		$scoreWrap.toggle(hasControls || !!actions.score);

		$('#fi-vote-pdf-btn').toggle(!!(actions.pdf || actions.pdfa || actions.pdfb));

		if (actions.score && typeof actions.score === 'string') {
			$scoreAction.find('#fi-vote-score-btn').replaceWith(actions.score);
		} else if (group.score !== undefined && group.score !== null) {
			$scoreValue.text(group.score);
		}
	}

	function sessionChipClass(isActive) {
		return 'btn fi-scroll-rail-item fi-vh-session-cell fi-nav-item border rounded-3 py-2 px-3 text-start fw-semibold' +
			(isActive ? ' btn-primary border-primary' : ' btn-light');
	}

	function chipBtnClass(isActive) {
		return 'btn btn-sm rounded-pill border fw-semibold fi-scroll-rail-item fi-nav-item' +
			(isActive ? ' btn-primary border-primary' : ' btn-light');
	}

	function renderReportChips() {
		$reportChips.empty();
		if (state.view === 'all' || state.view === 'tag' || !state.sessionId) {
			$reportChipsWrap.hide();
			return;
		}
		var session = voteGroups.sessions[state.sessionId];
		if (!session) { $reportChipsWrap.hide(); return; }
		$reportChipsWrap.show();
		$reportChips.append('<button type="button" class="' + chipBtnClass(!state.reportId) + '" data-view="session" data-session-id="' + state.sessionId + '">All Session Votes</button>');
		(session.reports || []).forEach(function(report) {
			var isActive = Number(state.reportId) === Number(report.id);
			$reportChips.append('<button type="button" class="' + chipBtnClass(isActive) + '" data-view="report" data-session-id="' + state.sessionId + '" data-report-id="' + report.id + '">' + escHtml(report.menu || report.title) + '</button>');
		});
	}

	function highlightNav() {
		$('.fi-vh-session-cell').each(function() {
			var $cell = $(this);
			var isActive = !!(state.sessionId && state.view !== 'all' && state.view !== 'tag' &&
				Number($cell.attr('data-session-id')) === Number(state.sessionId));
			$cell.attr('class', sessionChipClass(isActive));
		});
		$('#fi-view-all-votes').removeClass('fw-bold text-decoration-underline');
		if (state.view === 'all') {
			$('#fi-view-all-votes').addClass('fw-bold text-decoration-underline');
		}
	}

	function setStateFromNav($item) {
		// attr() — reliable for dynamically rebuilt report chips (jQuery .data() caches)
		var view = $item.attr('data-view');
		var sessionId = Number($item.attr('data-session-id')) || null;
		var reportId = Number($item.attr('data-report-id')) || null;
		var tagId = Number($item.attr('data-tag-id')) || null;

		if (view === 'tag' && tagId) {
			state = { view: 'tag', sessionId: null, reportId: null, tagId: tagId };
		} else if (view === 'report' && sessionId && reportId) {
			state = { view: 'report', sessionId: sessionId, reportId: reportId, tagId: null };
		} else if (view === 'session' && sessionId) {
			state = { view: 'session', sessionId: sessionId, reportId: null, tagId: null };
		} else {
			state = { view: 'all', sessionId: null, reportId: null, tagId: null };
		}
		visibleLimit = (state.view === 'all') ? PAGE_SIZE : 9999;
	}

	function syncUi() {
		renderReportChips();
		highlightNav();
		updateHeader();
		renderCards(true);
	}

	function parseStateFromPath(pathname) {
		var parts = pathname.split('/').filter(Boolean);
		var idx = parts.indexOf('legislator');
		if (idx === -1) return null;
		var parsed = { view: 'session', sessionId: defaultSessionId, reportId: null, tagId: null };
		var mode = parts[idx + 2] || null;
		if (mode === 'issue' && parts[idx + 3]) {
			parsed.view = 'tag';
			parsed.tagId = Number(parts[idx + 3]) || null;
			parsed.sessionId = null;
			return parsed;
		}
		if (mode === 'session' && parts[idx + 3]) {
			parsed.sessionId = Number(parts[idx + 3]) || null;
			if (parts[idx + 4] === 'report' && parts[idx + 5]) {
				parsed.view = 'report';
				parsed.reportId = Number(parts[idx + 5]) || null;
			} else {
				parsed.view = 'session';
			}
			return parsed;
		}
		return parsed;
	}

	$(document).on('click', '.fi-nav-item', function(e) {
		e.preventDefault();
		setStateFromNav($(this));
		pushStateFromSelection();
		syncUi();
	});

	$(document).on('click', '.fi-issue-tile-filter', function(e) {
		e.preventDefault();
		var tagId = Number($(this).data('tag-id')) || null;
		if (!tagId) return;
		state = { view: 'tag', sessionId: null, reportId: null, tagId: tagId };
		visibleLimit = PAGE_SIZE;
		pushStateFromSelection();
		syncUi();
	});

	$search.on('input', function() {
		if (state.view === 'all') {
			visibleLimit = PAGE_SIZE;
			renderCards(true);
		}
	});

	$('#fi-vote-load-more').on('click', function() {
		visibleLimit += PAGE_SIZE;
		renderCards(true);
	});

	$(document).on('click', '.fi-vote-readmore', function(e) {
		e.preventDefault();
		var voteId = Number($(this).data('vote-id')) || Number($(this).closest('.fi-vote-card').data('vote-id'));
		showVoteDetailModal(voteId);
	});

	$(document).on('click', '.fi-vote-card', function(e) {
		if ($(e.target).closest('a.fi-vote-title-link, .fi-vote-readmore').length) return;
		e.preventDefault();
		showVoteDetailModal(Number($(this).data('vote-id')));
	});

	$(document).on('keydown', '.fi-vote-card', function(e) {
		if (e.key === 'Enter' || e.key === ' ') {
			e.preventDefault();
			showVoteDetailModal(Number($(this).data('vote-id')));
		}
	});

	window.addEventListener('popstate', function() {
		var parsed = parseStateFromPath(window.location.pathname);
		if (parsed) {
			state = parsed;
			visibleLimit = (state.view === 'all') ? PAGE_SIZE : 9999;
			updateOgUrl(window.location.href);
			syncUi();
		}
	});

	$(function() {
		var parsed = parseStateFromPath(window.location.pathname);
		if (parsed) state = parsed;
		renderReportChips();
		highlightNav();
		updateHeader();
		updatePrintModalReportBase();
		if (!serverRendered) renderCards(true);
		else {
			serverRendered = false;
			var group = getActiveGroup();
			var ids = getFilteredVoteIds(group);
			$loadMoreWrap.toggle(state.view === 'all' && ids.length > visibleLimit);
		}
		initScrollRails();
	});

	// Pointer-drag horizontal scroll — only hijacks pointer after 8px move (clicks pass through)
	function initScrollRails() {
		document.querySelectorAll('.fi-scroll-rail').forEach(function(rail) {
			var activePointer = null;
			var startX = 0;
			var scrollStart = 0;
			var moved = false;

			rail.addEventListener('pointerdown', function(e) {
				if (e.pointerType === 'mouse' && e.button !== 0) return;
				activePointer = e.pointerId;
				startX = e.clientX;
				scrollStart = rail.scrollLeft;
				moved = false;
			});

			rail.addEventListener('pointermove', function(e) {
				if (e.pointerId !== activePointer) return;
				var dx = e.clientX - startX;
				if (!moved && Math.abs(dx) > 8) {
					moved = true;
					rail.classList.add('fi-scroll-rail--dragging');
				}
				if (moved) {
					rail.scrollLeft = scrollStart - dx;
				}
			});

			function endDrag(e) {
				if (e.pointerId !== activePointer) return;
				rail.classList.remove('fi-scroll-rail--dragging');
				if (moved) {
					rail.dataset.suppressClick = '1';
					window.setTimeout(function() { delete rail.dataset.suppressClick; }, 100);
				}
				activePointer = null;
				moved = false;
			}

			rail.addEventListener('pointerup', endDrag);
			rail.addEventListener('pointercancel', endDrag);

			rail.addEventListener('click', function(e) {
				if (rail.dataset.suppressClick === '1') {
					e.preventDefault();
					e.stopImmediatePropagation();
					delete rail.dataset.suppressClick;
				}
			}, true);
		});
	}

})(jQuery);
</script>

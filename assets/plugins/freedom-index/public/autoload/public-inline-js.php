<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Scorecard theme + Freedom Index: generates JS via OB with PHP values, minifies, enqueues inline after jQuery.
 */
function fi_public_inline_js() {
	if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		return;
	}
	$ajax_url   = esc_js( admin_url( 'admin-ajax.php' ) );
	$nonce      = esc_js( wp_create_nonce( 'fi_ajax_nonce' ) );
	$is_logged  = is_user_logged_in() ? 'true' : 'false';
	$user_id    = (int) get_current_user_id();
	ob_start();
	?>
<script>
(function($){'use strict';window.FI={ajaxurl:'<?= $ajax_url ?>',nonce:'<?= $nonce ?>',isLoggedIn:<?= $is_logged ?>,currentGov:'',currentSession:'',currentUserId:<?= $user_id ?>,homeUrl:'<?= esc_js( home_url( '/' ) ) ?>',themeUrl:'<?= esc_js( get_template_directory_uri() ) ?>'};$(document).ready(function(){FI.init();setTimeout(function(){FI.initFrontPageSearch();},0);});FI.init=function(){
        FI.initSearch();
        FI.initLists();
        FI.initUserPrefs();
        FI.initAnimations();
        FI.initMobileNavPrompt();
    };

    // Search System
    FI.initSearch = function() {
        const $searchInput = $('#fi-global-search');
        if (!$searchInput.length) return;
        let searchTimeout;
        let currentRequest;
        $searchInput.on('input', function() {
            const term = $(this).val().trim();
            clearTimeout(searchTimeout);
            if (term.length < 2) {
                $('#fi-search-suggestions').hide();
                return;
            }
            searchTimeout = setTimeout(function() {
                if (currentRequest) currentRequest.abort();
                currentRequest = $.ajax({
                    url: FI.ajaxurl,
                    type: 'POST',
                    data: { action: 'fi_search_autocomplete', nonce: FI.nonce, term: term, limit: 10 },
                    success: function(response) {
                        if (response.success) FI.showSearchSuggestions(response.data);
                    }
                });
            }, 300);
        });
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#fi-search-suggestions, #fi-global-search').length) $('#fi-search-suggestions').hide();
        });
    };

    FI.showSearchSuggestions = function(suggestions) {
        let $suggestions = $('#fi-search-suggestions');
        if (!$suggestions.length) {
            $suggestions = $('<div id="fi-search-suggestions" class="fi-search-suggestions"></div>');
            $('#fi-global-search').after($suggestions);
        }
        if (suggestions.length === 0) { $suggestions.hide(); return; }
        let html = '<ul class="list-unstyled">';
        suggestions.forEach(function(s) { html += '<li><a href="' + s.url + '">' + s.label + '</a></li>'; });
        html += '</ul>';
        $suggestions.html(html).show();
        $suggestions.find('a').on('click', function(e) { e.preventDefault(); window.location.href = $(this).attr('href'); });
    };

    // -------------------------------------------------------------------------
    // Legislator search + Find My Representatives
    // -------------------------------------------------------------------------
    FI.initFrontPageSearch = function() {
        var $hero = $('#hero-search-section');
        var lastResultsSource = null;
        var homeUrl = (typeof window.location !== 'undefined') ? (window.location.origin + '/') : '/';
        function clearResultsAndShowHero() {
            lastResultsSource = null;
            if ($hero.length) { $hero.removeClass('d-none'); }
        }
        var $headerForm = $('#header-legislator-search-form'), $headerInput = $('#header-legislator-search-input'), $headerClear = $('#header-search-clear-btn'), $headerSuggestions = $('#header-search-suggestions');
        var $mobileForm = $('#mobile-legislator-search-form'), $mobileInput = $('#mobile-legislator-search-input'), $mobileClear = $('#mobile-search-clear-btn'), $mobileSuggestions = $('#mobile-search-suggestions');
        function toggleClearBtn($input, $btn) { if (!$input.length || !$btn.length) return; $btn.toggleClass('d-none', !$.trim($input.val())); }
        function clearHeaderSearch($input, $clearBtn, $suggestions) {
            if (!$input.length) return;
            $input.val(''); toggleClearBtn($input, $clearBtn); if ($suggestions.length) $suggestions.addClass('d-none');

            // If bottom sheet is open, close it
            if (typeof fiHideBottomSheet === 'function') {
                fiHideBottomSheet();
            }

            // Clean up URL search param
            var hasSearchParam = window.location.search.includes('fi_search=');
            if (hasSearchParam) {
                var url = new URL(window.location);
                url.searchParams.delete('fi_search');
                window.history.pushState({}, '', url);
            }

            $input.focus();
        }
        function initHeaderSearchForm($form, $input, $suggestions, $clearBtn) {
            if (!$form.length || !$input.length) return;
            var autocompleteTimeout;
            toggleClearBtn($input, $clearBtn);
            $clearBtn.on('click', function(e) { e.preventDefault(); e.stopPropagation(); clearHeaderSearch($input, $clearBtn, $suggestions); });
            $input.on('input', function() {
                var term = $.trim($input.val());
                toggleClearBtn($input, $clearBtn);
                clearTimeout(autocompleteTimeout);
                if (term.length < 3) { if ($suggestions.length) $suggestions.addClass('d-none'); return; }
                autocompleteTimeout = setTimeout(function() {
                    fetch(FI.ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'fi_search_autocomplete', nonce: FI.nonce, term: term, limit: 10 }) })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success && data.data && data.data.length > 0 && $suggestions.length) {
                            $suggestions.empty();
                            data.data.forEach(function(s) {
                                var $a = $('<a href="#">').addClass('d-block p-2 text-decoration-none text-dark border-bottom').css('cursor', 'pointer').text(s.label);
                                $a.on('click', function(e) { e.preventDefault(); $input.val(s.value); $suggestions.addClass('d-none'); $form.trigger('submit'); });
                                $suggestions.append($a);
                            });
                            $suggestions.removeClass('d-none');
                        } else { $suggestions.addClass('d-none'); }
                    })
                    .catch(function() { $suggestions.addClass('d-none'); });
                }, 300);
            });
            $form.on('submit', function(e) {
                var term = $.trim($input.val());
                if (term.length < 3) { e.preventDefault(); return; }
                e.preventDefault(); e.stopPropagation();
                if ($suggestions.length) $suggestions.addClass('d-none');

                // Use bottom sheet for results
                if (typeof fiLoadSearchResults === 'function') {
                    fiLoadSearchResults(term, '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
                    fetch(FI.ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'fi_unified_search', nonce: FI.nonce, query: term }) })
                    .then(function(r) { return r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)); })
                    .then(function(data) {
                        if (data.success && data.data.html) {
                            fiLoadSearchResults(term, data.data.html);
                        } else {
                            fiLoadSearchResults(term, '<div class="alert alert-warning">' + (data.data && data.data.message ? data.data.message : 'No results found.') + '</div>');
                        }
                    })
                    .catch(function(err) {
                        fiLoadSearchResults(term, '<div class="alert alert-danger">Search failed. Please try again.</div>');
                        console.error('Search error:', err);
                    });
                } else {
                    // Fallback to page redirect if bottom sheet not available
                    window.location = homeUrl + '?fi_search=' + encodeURIComponent(term);
                }
            });
            $input.on('keydown', function(e) { if (e.key === 'Escape' && $suggestions.length) $suggestions.addClass('d-none'); });
        }
        function attachClearSearchHandlers() {
            $(document).off('click.fiFrontPageSearch', '.clear-search-link').on('click.fiFrontPageSearch', '.clear-search-link', function(e) {
                e.preventDefault();
                $headerInput.val(''); $mobileInput.val('');
                toggleClearBtn($headerInput, $headerClear); toggleClearBtn($mobileInput, $mobileClear);
                clearResultsAndShowHero();
                $headerSuggestions.addClass('d-none'); $mobileSuggestions.addClass('d-none');
                // Focus the first available search input after clearing.
                var $focusTarget = $headerInput.length ? $headerInput : $mobileInput;
                if ($focusTarget.length) $focusTarget.focus();
            });
        }
        if ($headerForm.length && $headerInput.length) initHeaderSearchForm($headerForm, $headerInput, $headerSuggestions, $headerClear);
        if ($mobileForm.length && $mobileInput.length) initHeaderSearchForm($mobileForm, $mobileInput, $mobileSuggestions, $mobileClear);
        $(document).on('click', function(e) {
            if ($headerForm.length && !$headerForm.has(e.target).length && $headerSuggestions.length) $headerSuggestions.addClass('d-none');
            if ($mobileForm.length && !$mobileForm.has(e.target).length && $mobileSuggestions.length) $mobileSuggestions.addClass('d-none');
        });
        // Auto-search from URL param
        (function() {
            var params = new URLSearchParams(window.location.search), searchParam = params.get('fi_search');
            if (!searchParam || $.trim(searchParam).length < 3) return;
            // Skip if no bottom sheet function available
            if (typeof fiLoadSearchResults !== 'function') return;
            var decoded = decodeURIComponent(searchParam.replace(/\+/g, ' '));
            $headerInput.val(decoded); $mobileInput.val(decoded);
            toggleClearBtn($headerInput, $headerClear); toggleClearBtn($mobileInput, $mobileClear);

            setTimeout(function() {
                fiLoadSearchResults(decoded, '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
                fetch(FI.ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'fi_unified_search', nonce: FI.nonce, query: decoded }) })
                .then(function(r) { return r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)); })
                .then(function(data) {
                    if (data.success && data.data.html) {
                        fiLoadSearchResults(decoded, data.data.html);
                    } else {
                        fiLoadSearchResults(decoded, '<div class="alert alert-warning">' + (data.data && data.data.message ? data.data.message : 'No results found.') + '</div>');
                    }
                })
                .catch(function(err) {
                    fiLoadSearchResults(decoded, '<div class="alert alert-danger">Search failed. Please try again.</div>');
                    console.error('Search error:', err);
                });
            }, 300);
        })();
        var $findForm = $('#find-representatives-form'), $findSubmit = $('#find-officials-btn'), $clearLink = $('#clear-form-link'), $clearLinkMobile = $('#clear-form-link-mobile'), $clearLinkHome = $('#clear-form-link-home');
        var $collapse = $('#find-legislators-collapse'), $toggleBtn = $('[data-bs-target="#find-legislators-collapse"]'), $toggleText = $toggleBtn.find('.toggle-text'), $toggleIcon = $toggleBtn.find('.fas');
        if (!$findForm.length || !$findSubmit.length) return;
        if ($collapse.length && $toggleBtn.length) {
            $collapse.on('show.bs.collapse', function() { $toggleText.text('Hide Form'); $toggleIcon.removeClass('fa-chevron-down').addClass('fa-chevron-up'); });
            $collapse.on('hide.bs.collapse', function() { $toggleText.text('Show Form'); $toggleIcon.removeClass('fa-chevron-up').addClass('fa-chevron-down'); });
        }
        function validateZip(zip) { if (!zip) return false; return /^\d{5}(-\d{4})?$/.test($.trim(zip)); }
        function showZipError(show) {
            var $zip = $('#zip'), $fb = $zip.parent().find('.invalid-feedback');
            if (show) { $zip.addClass('is-invalid'); if (!$fb.length) $zip.parent().append($('<div class="invalid-feedback d-block">').text('Invalid zip code')); }
            else { $zip.removeClass('is-invalid'); $fb.remove(); }
        }
        $('#zip').on('input', function() { showZipError($(this).val() && !validateZip($(this).val())); });
        function handleFindReset(e) {
            if (e) e.preventDefault();
            $('#address, #city, #state, #zip').val('');
            if (lastResultsSource === 'find-representatives') clearResultsAndShowHero();
            window.scrollTo({ top: 0, behavior: 'smooth' }); $('#zip').focus();
        }
        $clearLink.on('click', handleFindReset); $clearLinkMobile.on('click', handleFindReset);
        // Find Representatives form - uses bottom sheet only
        $findForm.on('submit', function(e) {
            e.preventDefault(); e.stopPropagation();
            var zip = $.trim($('#zip').val());
            if (!validateZip(zip)) { showZipError(true); $('#zip').focus(); return; }
            showZipError(false);

            if (typeof fiLoadSearchResults !== 'function') {
                window.location = homeUrl + '?zip=' + encodeURIComponent(zip);
                return;
            }

            var originalText = $findSubmit.text();
            $findSubmit.prop('disabled', true).text('Searching...');

            fiLoadSearchResults('Find My Representatives', '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');

            var formData = new FormData($findForm[0]);
            formData.append('action', 'fi_find_representatives');

            fetch(FI.ajaxurl, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                $findSubmit.prop('disabled', false).text(originalText);
                if (data.success && data.data.html) {
                    fiLoadSearchResults('Find My Representatives', data.data.html);
                } else {
                    fiLoadSearchResults('Find My Representatives', '<div class="alert alert-danger">' + (data.data && data.data.message ? data.data.message : 'An error occurred.') + '</div>');
                }
            })
            .catch(function(err) {
                $findSubmit.prop('disabled', false).text(originalText);
                fiLoadSearchResults('Find My Representatives', '<div class="alert alert-danger">An error occurred. Please try again.</div>');
                console.error('Error:', err);
            });
        });
        /*if ($findForm.data('auto-submit')) setTimeout(function() { $findSubmit.trigger('click'); }, 300);*/
    };

    // Lists System
    FI.initLists = function() {
        $(document).on('click', '.fi-add-to-list', function() { FI.addToList($(this).data('legislator-id')); });
        $(document).on('click', '#fi-save-list', function() { FI.saveList(); });
    };
    FI.addToList = function(legislatorId) {
        var list = FI.getList();
        if (list.indexOf(legislatorId) === -1) {
            list.push(legislatorId); FI.setList(list); FI.updateListUI();
            var $btn = $('.fi-add-to-list[data-legislator-id="' + legislatorId + '"]'), ot = $btn.text();
            $btn.text('Added!').addClass('btn-success').removeClass('btn-outline-primary');
            setTimeout(function() { $btn.text(ot).removeClass('btn-success').addClass('btn-outline-primary'); }, 2000);
        }
    };
    FI.removeFromList = function(legislatorId) {
        var list = FI.getList(), i = list.indexOf(legislatorId);
        if (i > -1) { list.splice(i, 1); FI.setList(list); FI.updateListUI(); }
    };
    FI.getList = function() { var s = localStorage.getItem('fi_list'); return s ? JSON.parse(s) : []; };
    FI.setList = function(list) { localStorage.setItem('fi_list', JSON.stringify(list)); };
    FI.updateListUI = function() { var list = FI.getList(), $b = $('#fi-list-count'); if ($b.length) { $b.text(list.length); $b.toggle(list.length > 0); } };
    FI.saveList = function() {
        if (!FI.isLoggedIn) { alert('Please log in to save lists'); return; }
        var list = FI.getList(), name = prompt('Enter list name:');
        if (!name) return;
        $.ajax({ url: FI.ajaxurl, type: 'POST', data: { action: 'fi_save_list', nonce: FI.nonce, name: name, legislator_ids: list }, success: function(r) { if (r.success) { alert('List saved!'); window.location.href = r.data.url; } else { alert('Error: ' + r.data); } } });
    };

    FI.initUserPrefs = function() {
        FI.loadUserPrefs();
        $(document).on('click', '#fi-save-prefs', function() { FI.saveUserPrefs(); });
        if (FI.isLoggedIn) FI.syncPrefs();
    };
    FI.loadUserPrefs = function() { var p = FI.getUserPrefs(); if (p.name) $('#fi-pref-name').val(p.name); if (p.phone) $('#fi-pref-phone').val(p.phone); if (p.email) $('#fi-pref-email').val(p.email); if (p.zip) $('#fi-pref-zip').val(p.zip); };
    FI.saveUserPrefs = function() {
        var prefs = { name: $('#fi-pref-name').val(), phone: $('#fi-pref-phone').val(), email: $('#fi-pref-email').val(), zip: $('#fi-pref-zip').val() };
        FI.setUserPrefs(prefs);
        if (FI.isLoggedIn) $.ajax({ url: FI.ajaxurl, type: 'POST', data: Object.assign({ action: 'fi_save_prefs', nonce: FI.nonce }, prefs) });
    };
    FI.getUserPrefs = function() { var s = localStorage.getItem('fi_user_prefs'); return s ? JSON.parse(s) : {}; };
    FI.setUserPrefs = function(prefs) { localStorage.setItem('fi_user_prefs', JSON.stringify(prefs)); };
    FI.syncPrefs = function() { var p = localStorage.getItem('fi_user_prefs'); if (p) $.ajax({ url: FI.ajaxurl, type: 'POST', data: { action: 'fi_sync_prefs', nonce: FI.nonce, prefs: p } }); };

    FI.initAnimations = function() {
        if (typeof gsap === 'undefined') return;
        if ($('.fi-legislator-card').length === 0 && $('.fi-score-badge').length === 0) return;
        if ($('.fi-legislator-card').length) {
            gsap.fromTo('.fi-legislator-card', { opacity: 0, y: 20 }, { opacity: 1, y: 0, duration: 0.5, stagger: 0.1 });
            $('.fi-legislator-card').hover(function() { gsap.to(this, { scale: 1.02, duration: 0.2 }); }, function() { gsap.to(this, { scale: 1, duration: 0.2 }); });
        }
        $('.fi-score-badge').each(function() { var $t = $(this), sc = parseInt($t.text()); if (!isNaN(sc)) { gsap.fromTo($t, { textContent: 0 }, { textContent: sc, duration: 1.5, ease: 'power2.out', snap: { textContent: 1 } }); } });
    };

    FI.copyListURL = function() {
        if (navigator.clipboard) navigator.clipboard.writeText(window.location.href).then(function() { alert('List URL copied!'); });
        else { var ta = document.createElement('textarea'); ta.value = window.location.href; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); alert('List URL copied!'); }
    };

    if (window.innerWidth <= 768) {
        $('.fi-legislator-card').addClass('touch-friendly');
        $('.fi-filters').on('scroll', function() { $(this).addClass('scrolling'); });
    }

    // Mobile navigation prompt (dismissible; hide/show via style so no theme CSS dependency)
    FI.initMobileNavPrompt = function() {
        var mobileNavPrompt = document.getElementById('mobileNavPrompt');
        var dismissNavPrompt = document.getElementById('dismissNavPrompt');
        var PROMPT_STORAGE_KEY = 'scorecard_nav_prompt_dismissed';

        function isFIPage() {
            if (document.querySelector('#desktopSidebar[data-fi-page]')) return true;
            var url = window.location.pathname;
            return /\/[a-z]{2}\//.test(url) || url.indexOf('/legislator') !== -1 || url.indexOf('/legislators') !== -1 || url.indexOf('/reports') !== -1 || url.indexOf('/votes') !== -1;
        }
        function hidePrompt() {
            if (mobileNavPrompt) { mobileNavPrompt.style.display = 'none'; mobileNavPrompt.classList.add('hidden'); }
        }
        function showPrompt() {
            if (mobileNavPrompt) { mobileNavPrompt.style.display = ''; mobileNavPrompt.classList.remove('hidden'); }
        }
        function updatePromptVisibility() {
            if (!mobileNavPrompt || window.innerWidth >= 992) { hidePrompt(); return; }
            if (localStorage.getItem(PROMPT_STORAGE_KEY) === 'true') { hidePrompt(); return; }
            var path = window.location.pathname;
            var isHome = path === '/' || path === '';
            if (!isFIPage() && !isHome) { hidePrompt(); return; }
            showPrompt();
        }

        if (dismissNavPrompt) {
            dismissNavPrompt.addEventListener('click', function() {
                hidePrompt();
                localStorage.setItem(PROMPT_STORAGE_KEY, 'true');
            });
        }
        var leftNavPanel = document.getElementById('leftNavPanel');
        if (leftNavPanel) {
            leftNavPanel.addEventListener('show.bs.offcanvas', function() {
                if (mobileNavPrompt && mobileNavPrompt.style.display !== 'none') {
                    hidePrompt();
                    localStorage.setItem(PROMPT_STORAGE_KEY, 'true');
                }
            });
        }
        updatePromptVisibility();
        // Re-apply visibility when slideDown ends so banner stays visible (avoids post-animation hide from CSS/other scripts)
        if (mobileNavPrompt) {
            function onPromptAnimationEnd(e) {
                if (e.animationName === 'slideDown' && mobileNavPrompt.style.display !== 'none') {
                    mobileNavPrompt.style.display = 'block';
                    mobileNavPrompt.classList.remove('hidden');
                }
            }
            mobileNavPrompt.addEventListener('animationend', onPromptAnimationEnd);
            mobileNavPrompt.addEventListener('webkitAnimationEnd', onPromptAnimationEnd);
        }
        var resizeTimerPrompt;
        window.addEventListener('resize', function() {
            if (resizeTimerPrompt) clearTimeout(resizeTimerPrompt);
            resizeTimerPrompt = setTimeout(updatePromptVisibility, 150);
        });
    };

})(jQuery);
<?php
	$entity = get_query_var('fi_entity');
	$needs_pdf_contacts = ($entity === 'legislator');
	// Account pages: shortcodes in content or theme template include (account_fi.php)
	if (!$needs_pdf_contacts && is_singular('page')) {
		$post = get_queried_object();
		$content = $post && isset($post->post_content) ? $post->post_content : '';
		$needs_pdf_contacts = has_shortcode($content, 'fi_account_dashboard') || has_shortcode($content, 'fi_account_personalize') || has_shortcode($content, 'fi_account_login');
		if (!$needs_pdf_contacts && is_page_template('page-templates/account_fi.php')) {
			$needs_pdf_contacts = true;
		}
	}
	if ($needs_pdf_contacts) {
		$pdf_ajax = esc_js(admin_url('admin-ajax.php'));
		$pdf_nonce = esc_js(wp_create_nonce('fi_delete_pdf_contact'));
?>
(function(){'use strict';
window.fiPdfContacts={ajaxUrl:'<?php echo $pdf_ajax; ?>',nonce:'<?php echo $pdf_nonce; ?>'};
window.fiAjaxUrl=window.fiPdfContacts.ajaxUrl;
window.fiDeletePdfContact=function(index,nonce,onSuccess,onError){
	var ajaxUrl=window.fiAjaxUrl||(window.fiPdfContacts&&window.fiPdfContacts.ajaxUrl)||'/wp-admin/admin-ajax.php';
	if(typeof fetch!=='undefined'){
		fetch(ajaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'fi_delete_pdf_contact',index:index,nonce:nonce})})
		.then(function(r){return r.text().then(function(t){return{ok:r.ok,body:t};});})
		.then(function(res){
			var data;
			try{data=JSON.parse(res.body);}catch(e){data=null;}
			if(data&&data.success===true){if(onSuccess)onSuccess(data);else location.reload();return;}
			if(res.ok&&!data){if(onSuccess)onSuccess({});else location.reload();return;}
			if(onError)onError(data||{});else alert('Error deleting contact: '+(data&&data.data&&data.data.message?data.data.message:'Unknown error'));
		})
		.catch(function(){if(onError)onError({error:'Network error'});else alert('Error deleting contact. Please try again.');});
	}else if(typeof jQuery!=='undefined'){
		jQuery.ajax({url:ajaxUrl,type:'POST',data:{action:'fi_delete_pdf_contact',index:index,nonce:nonce},success:function(r){if(r&&r.success){if(onSuccess)onSuccess(r);else location.reload();}else{if(onError)onError(r||{});else alert('Error deleting contact: '+(r&&r.data&&r.data.message?r.data.message:'Unknown error'));}},error:function(){if(onError)onError({error:'Network error'});else alert('Error deleting contact. Please try again.');}});
	}
};
window.fiInitPdfContactDelete=function(nonce,options){
	options=options||{};
	// Only .fi-delete-pdf-contact: modal uses .fi-delete-contact + fiModalDeleteContact (AJAX + list update); account/dashboard use .fi-delete-pdf-contact
	var selector=options.selector||'.fi-delete-pdf-contact';
	var onSuccess=options.onSuccess;
	var onError=options.onError;
	if(typeof document!=='undefined'){
		document.addEventListener('click',function(e){
			var btn=e.target.closest(selector);
			if(!btn)return;
			if(btn.closest('#fiAccountContactsListContainer'))return;
			e.preventDefault();e.stopImmediatePropagation();var idx=parseInt(btn.dataset.index||btn.getAttribute('data-index'),10);if(!isNaN(idx))window.fiDeletePdfContact(idx,nonce,onSuccess,onError);
		},true);
	}
	if(typeof jQuery!=='undefined'){
		jQuery(document).on('click',selector,function(e){if(jQuery(this).closest('#fiAccountContactsListContainer').length)return;e.preventDefault();e.stopImmediatePropagation();var idx=parseInt(jQuery(this).data('index'),10);if(!isNaN(idx))window.fiDeletePdfContact(idx,nonce,onSuccess,onError);});
	}
};
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',function(){window.fiInitPdfContactDelete(window.fiPdfContacts.nonce);});}else{window.fiInitPdfContactDelete(window.fiPdfContacts.nonce);}
})();
<?php
	}
?>
</script>
<?php
	$script = ob_get_clean();
	//Tags added above to aid in coding tools but removed before enqueueing.
	$script = str_replace(['<script>', '</script>'], '', $script);

	// Conservative minification: trim, normalize line endings, strip trailing space per line.
	// Does not collapse all whitespace (would change string/regex content) or remove newlines.
	$script = trim( $script );
	$script = preg_replace( '/\r\n|\r/', "\n", $script );
	$script = preg_replace( '/[ \t]+\n/', "\n", $script );

	// Attach to jQuery so inline always prints (empty-src handles can be skipped; jQuery is in head).
	wp_enqueue_script( 'jquery' );
	wp_add_inline_script( 'jquery', $script, 'after' );
}
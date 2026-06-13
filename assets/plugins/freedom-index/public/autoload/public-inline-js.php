<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Freedom Index public inline JS - Unified Edition
 * Handles: hero search, header/mobile search, bottom sheet search
 * Zero redundancy, single source of truth for all search functionality
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
(function($){'use strict';
window.FI={ajaxurl:'<?= $ajax_url ?>',nonce:'<?= $nonce ?>',isLoggedIn:<?= $is_logged ?>,currentGov:'',currentSession:'',currentUserId:<?= $user_id ?>,homeUrl:'<?= esc_js( home_url( '/' ) ) ?>',themeUrl:'<?= esc_js( get_template_directory_uri() ) ?>'};

$(document).ready(function(){
    FI.init();
});

FI.init = function(){
    FI.initLegislatorSearch();
    FI.initBottomSheet();
    FI.initLists();
    FI.initUserPrefs();
};

// UNIFIED SEARCH SYSTEM - Single source of truth for all search functionality

/**
 * Execute unified search AJAX call
 * @param {string} query - Search query
 * @param {Function} onSuccess - Callback with HTML content
 * @param {Function} onError - Callback with error message
 */
FI.execSearch = function(query, onSuccess, onError) {
    fetch(FI.ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'fi_unified_search',
            nonce: FI.nonce,
            query: query
        })
    })
    .then(function(r) { return r.ok ? r.json() : Promise.reject('Network error'); })
    .then(function(data) {
        if (data.success && data.data.html) {
            var title = data.data.mode === 'representatives' ? 'Your Representatives' : 'Search Results';
            onSuccess(data.data.html, title);
        } else {
            onError(data.data && data.data.message ? data.data.message : 'No results found.');
        }
    })
    .catch(function(err) {
        console.error('[fi_search] error:', err);
        onError('Search failed. Please try again.');
    });
};

/**
 * Initialize all legislator search forms (hero, header, mobile)
 */
FI.initLegislatorSearch = function() {
    var $hero = $('#hero-search-section');
    var $headerForm = $('#header-legislator-search-form'), $headerInput = $('#header-legislator-search-input'), $headerClear = $('#header-search-clear-btn'), $headerSuggestions = $('#header-search-suggestions');
    var $mobileForm = $('#mobile-legislator-search-form'), $mobileInput = $('#mobile-legislator-search-input'), $mobileClear = $('#mobile-search-clear-btn'), $mobileSuggestions = $('#mobile-search-suggestions');

    function toggleClearBtn($input, $btn) { 
        if ($input.length && $btn.length) $btn.toggleClass('d-none', !$.trim($input.val())); 
    }

    function clearSearch($input, $clearBtn, $suggestions) {
        if (!$input.length) return;
        $input.val(''); 
        toggleClearBtn($input, $clearBtn); 
        if ($suggestions.length) $suggestions.addClass('d-none');
        if (typeof fiHideBottomSheet === 'function') fiHideBottomSheet();
        if ($hero.length) $hero.removeClass('d-none');
        if (window.location.search.includes('fi_search=')) {
            var url = new URL(window.location);
            url.searchParams.delete('fi_search');
            window.history.pushState({}, '', url);
        }
        $input.focus();
    }

    // Initialize autocomplete for a search form
    function initAutocomplete($form, $input, $suggestions) {
        if (!$form.length || !$input.length) return;
        var autocompleteTimeout;
        $input.on('input', function() {
            var term = $.trim($input.val());
            toggleClearBtn($input, $form.find('[id$="-search-clear-btn"]'));
            clearTimeout(autocompleteTimeout);
            if (term.length < 3) { if ($suggestions.length) $suggestions.addClass('d-none'); return; }
            autocompleteTimeout = setTimeout(function() {
                fetch(FI.ajaxurl, { 
                    method: 'POST', 
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                    body: new URLSearchParams({ action: 'fi_search_autocomplete', nonce: FI.nonce, term: term, limit: 10 }) 
                })
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
    }

    // Initialize form submission
    function initFormSubmit($form, $input, $suggestions, $clearBtn) {
        if (!$form.length || !$input.length) return;
        toggleClearBtn($input, $clearBtn);
        $clearBtn.on('click', function(e) { e.preventDefault(); clearSearch($input, $clearBtn, $suggestions); });
        initAutocomplete($form, $input, $suggestions);
        $form.on('submit', function(e) {
            var term = $.trim($input.val());
            if (term.length < 3) { e.preventDefault(); return; }
            e.preventDefault();
            if ($suggestions.length) $suggestions.addClass('d-none');
            if ($hero.length) $hero.addClass('d-none');
            
            // Use unified search execution
            FI.execSearch(term, function(html, title) {
                fiLoadSearchResults(term, html, title);
            }, function(message) {
                fiLoadSearchResults(term, '<div class="alert alert-warning">' + message + '</div>', 'Search Results');
            });
        });
        $input.on('keydown', function(e) { if (e.key === 'Escape' && $suggestions.length) $suggestions.addClass('d-none'); });
    }

    // Initialize all forms
    initFormSubmit($headerForm, $headerInput, $headerSuggestions, $headerClear);
    initFormSubmit($mobileForm, $mobileInput, $mobileSuggestions, $mobileClear);

    // Click outside to close suggestions
    $(document).on('click', function(e) {
        if ($headerForm.length && !$headerForm.has(e.target).length && $headerSuggestions.length) $headerSuggestions.addClass('d-none');
        if ($mobileForm.length && !$mobileForm.has(e.target).length && $mobileSuggestions.length) $mobileSuggestions.addClass('d-none');
    });

    // Auto-search from URL param
    (function() {
        var params = new URLSearchParams(window.location.search), searchParam = params.get('fi_search');
        if (!searchParam || $.trim(searchParam).length < 3) return;
        if (typeof fiLoadSearchResults !== 'function') return;
        var decoded = decodeURIComponent(searchParam.replace(/\+/g, ' '));
        $headerInput.val(decoded); $mobileInput.val(decoded);
        toggleClearBtn($headerInput, $headerClear); toggleClearBtn($mobileInput, $mobileClear);
        if ($hero.length) $hero.addClass('d-none');
        FI.execSearch(decoded, function(html, title) {
            fiLoadSearchResults(decoded, html, title);
        }, function(message) {
            fiLoadSearchResults(decoded, '<div class="alert alert-warning">' + message + '</div>', 'Search Results');
        });
    })();
};

// BOTTOM SHEET SYSTEM - Unified container for search results and selectors
FI.initBottomSheet = function() {
    var sheet = document.getElementById('fi-bottom-sheet');
    var backdrop = sheet?.querySelector('.fi-bottom-sheet-backdrop');
    var panel = sheet?.querySelector('.fi-bottom-sheet-panel');
    var handle = sheet?.querySelector('.fi-bottom-sheet-handle');
    var content = document.getElementById('fi-bottom-sheet-content');
    var title = document.getElementById('fi-bottom-sheet-title');
    var searchContainer = document.getElementById('fi-bottom-sheet-search-container');
    var searchInput = document.getElementById('fi-bottom-sheet-search-input');
    var searchForm = document.getElementById('fi-bottom-sheet-search-form');
    
    if (!sheet || !content) return;
    
    var startY = 0, currentY = 0, isDragging = false, touchStartTime = 0;
    
    // Expose global functions for search results
    window.fiShowBottomSheet = function(options) {
        options = options || {};
        if (options.title && title) title.textContent = options.title;
        if (searchContainer) {
            searchContainer.style.display = options.showSearch ? 'block' : 'none';
            if (options.showSearch && searchInput) {
                searchInput.value = options.searchValue || '';
                if (options.focusSearch) setTimeout(function() { searchInput?.focus(); }, 100);
            }
        }
        if (options.content) content.innerHTML = options.content;
        else if (options.url) {
            content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
            fetch(options.url).then(function(r) { return r.text(); }).then(function(html) { content.innerHTML = html; }).catch(function() { content.innerHTML = '<div class="alert alert-danger">Failed to load.</div>'; });
        }
        sheet.hidden = false;
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(function() { sheet.classList.add('active'); });
        sheet.dispatchEvent(new CustomEvent('fi:show', { detail: options }));
    };
    
    window.fiHideBottomSheet = function() {
        sheet.classList.remove('active');
        setTimeout(function() { sheet.hidden = true; content.innerHTML = ''; document.body.style.overflow = ''; }, 300);
        sheet.dispatchEvent(new CustomEvent('fi:hide'));
    };
    
    window.fiLoadSearchResults = function(query, html, customTitle) {
        var titleText = customTitle || (/^\d/.test(query) ? 'Your Representatives' : 'Search Results');
        fiShowBottomSheet({
            title: titleText,
            showSearch: true,
            searchValue: query,
            focusSearch: false,
            content: html
        });
    };
    
    // Dismiss handlers
    backdrop?.addEventListener('click', fiHideBottomSheet);
    sheet.querySelectorAll('[data-bs-dismiss="bottom-sheet"]').forEach(function(btn) {
        btn.addEventListener('click', fiHideBottomSheet);
    });
    
    // Touch/drag handling for mobile swipe-to-dismiss
    if (handle && panel) {
        handle.addEventListener('touchstart', function(e) {
            isDragging = true; startY = e.touches[0].clientY; currentY = startY; touchStartTime = Date.now();
            panel.style.transition = 'none';
        }, { passive: true });
        handle.addEventListener('touchmove', function(e) {
            if (!isDragging) return; currentY = e.touches[0].clientY;
            var deltaY = currentY - startY; if (deltaY > 0) panel.style.transform = 'translateY(' + deltaY + 'px)';
        }, { passive: true });
        handle.addEventListener('touchend', function() {
            if (!isDragging) return; isDragging = false; panel.style.transition = '';
            var deltaY = currentY - startY, timeElapsed = Date.now() - touchStartTime, velocity = deltaY / timeElapsed;
            if (deltaY > 100 || (deltaY > 50 && velocity > 0.5)) fiHideBottomSheet(); else panel.style.transform = '';
        }, { passive: true });
    }
    
    // Keyboard: ESC to close
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && !sheet.hidden) fiHideBottomSheet(); });
    
    // Search form in bottom sheet - uses unified FI.execSearch
    searchForm?.addEventListener('submit', function(e) {
        e.preventDefault();
        var query = searchInput.value.trim();
        if (query.length < 3) return;
        content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
        FI.execSearch(query, function(html, resultTitle) {
            content.innerHTML = html;
            if (title && resultTitle) title.textContent = resultTitle;
        }, function(message) {
            content.innerHTML = '<div class="alert alert-warning">' + message + '</div>';
        });
    });
    
    // Triggers (federal/state selectors)
    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('[data-bs-toggle="bottom-sheet"]');
        if (!trigger) return;
        e.preventDefault();
        var contentType = trigger.dataset.content;
        sheet.dataset.content = contentType || 'default';
        if (contentType === 'federal' || contentType === 'state') {
            fiShowBottomSheet({
                title: contentType === 'federal' ? 'Congressional Legislators' : 'State Legislators',
                showSearch: false,
                content: '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>'
            });
            fetch(FI.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'fi_load_selector', nonce: FI.nonce, type: contentType, _t: Date.now() })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.data.html) {
                    content.innerHTML = data.data.html;
                    FI.initMapFromContent(content, contentType);
                } else {
                    content.innerHTML = '<div class="alert alert-warning">Failed to load.</div>';
                }
            })
            .catch(function() { content.innerHTML = '<div class="alert alert-danger">Failed to load.</div>'; });
        }
    });
};

// MAP INITIALIZATION - Federal and State vector maps
FI.initMapFromContent = function(content, contentType) {
    if (typeof jsVectorMap === 'undefined') return;
    
    var mapId = contentType === 'federal' ? 'map-federal' : 'map-state';
    var mapEl = content.querySelector('#' + mapId);
    if (!mapEl) return;
    
    // Delay initialization to ensure container has dimensions
    requestAnimationFrame(function() {
    try {
        var map = new jsVectorMap({
            selector: '#' + mapId,
            map: 'us_aea_en',
            backgroundColor: 'transparent',
            regionStyle: {
                initial: {
                    fill: '#e9ecef',
                    stroke: '#ffffff',
                    strokeWidth: 1
                },
                hover: {
                    fill: '#0055a4'
                },
                selected: {
                    fill: '#0055a4'
                }
            },
            onRegionClick: function(event, code) {
                var stateCode = code.toLowerCase();
                var gov = contentType === 'federal' ? 'US' : stateCode.toUpperCase();
                if (typeof fiLoadStateLegislators === 'function') {
                    fiLoadStateLegislators(gov, stateCode);
                }
            }
        });
        
        // Tiny state button handlers (buttons have data-state attribute)
        content.querySelectorAll('[data-state]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var code = this.dataset.state;
                var gov = contentType === 'federal' ? 'US' : code.toUpperCase();
                if (typeof fiLoadStateLegislators === 'function') {
                    fiLoadStateLegislators(gov, code);
                }
            });
        });
    } catch (e) {
        console.error('Map init error:', e);
    }
    });
};

// Global function to load state legislators from map click
window.fiLoadStateLegislators = function(gov, stateCode) {
    var content = document.getElementById('fi-bottom-sheet-content');
    var title = document.getElementById('fi-bottom-sheet-title');
    if (!content) return;
    
    if (title) title.textContent = gov === 'US' ? 'Federal Legislators' : stateCode.toUpperCase() + ' Legislators';
    content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    
    fetch(FI.ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'fi_load_state_legislators',
            nonce: FI.nonce,
            gov: gov,
            state: stateCode.toLowerCase()
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success && data.data.html) content.innerHTML = data.data.html;
        else content.innerHTML = '<div class="alert alert-warning">No legislators found.</div>';
    })
    .catch(function() {
        content.innerHTML = '<div class="alert alert-danger">Failed to load legislators.</div>';
    });
};

// LISTS SYSTEM
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

// USER PREFS
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

})(jQuery);
</script>
<?php
	$script = ob_get_clean();
	$script = str_replace(['<script>', '</script>'], '', $script);
	$script = trim( $script );
	$script = preg_replace( '/\r\n|\r/', "\n", $script );
	$script = preg_replace( '/[ \t]+\n/', "\n", $script );
	wp_enqueue_script( 'jquery' );
	wp_register_script( 'fi-public-inline', '', [], '1.0', true );
	wp_enqueue_script( 'fi-public-inline' );
	wp_add_inline_script( 'fi-public-inline', $script );
}

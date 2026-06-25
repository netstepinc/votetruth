/**
 * Freedom Index Admin JavaScript
 */

// Force reload if browser restores this page from bfcache (back/forward cache).
// bfcache can show stale form values even when Cache-Control: no-store is set.
window.addEventListener('pageshow', function(e) {
    if (e.persisted) { window.location.reload(); }
});

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        // Root breadcrumb: if this doesn't run, JS isn't loading/executing.
        try { console.log('[FI] admin.js loaded', (window.fiAdmin || null)); } catch (e) {}
        FIAdmin.init();
    });

    // Main FIAdmin object
    window.FIAdmin = {
        
        // Initialize the admin interface
        init: function() {
            this.bindEvents();
            this.initScopeSelector();
            this.initFilters();
            this.initNotifications();
            this.initImageMediaPicker();
            this.initCitationSelect();
            this.initTagSelect();

            // Root breadcrumb: confirms FIAdmin.init ran.
            var rid = this.newRid('init');
            this.ajaxLog('init', 'FIAdmin.init', { href: (window.location ? window.location.href : '') }, rid);
        },

        // Bind event handlers
        bindEvents: function() {
            // Scope selector
            $(document).on('change', '#fi-jurisdiction', this.handleJurisdictionChange);
            $(document).on('change', '#fi-session', this.handleSessionChange);
            
            // Filters
            $(document).on('input', '#fi-search', this.handleSearch);
            $(document).on('change', '#fi-chamber', this.handleFilterChange);
            $(document).on('change', '#fi-party', this.handleFilterChange);
            
            // Date filters
            $(document).on('change', '#fi-date-from, #fi-date-to', this.handleDateFilterChange);
            
            // Form submissions
            $(document).on('submit', '#fi-scope-form', this.handleScopeSubmit);
            $(document).on('submit', '#fi-address-form', this.handleAddressSubmit);
            
            // Actions
            $(document).on('click', '.fi-recalculate-scores', this.handleRecalculateScores);
            $(document).on('click', '.fi-recalculate-freedom-scores', this.handleRecalculateFreedomScores);
            $(document).on('click', '.fi-calculate-scores-gov', this.handleCalculateScoresGov);
            $(document).on('click', '.fi-validate-data', this.handleValidateData);
            $(document).on('click', '.fi-fix-issues', this.handleFixIssues);
            
            // List management
            $(document).on('click', '.fi-add-list', this.handleAddList);
            $(document).on('click', '.fi-remove-list', this.handleRemoveList);
            
            // Image media picker
            $(document).on('click', '#fi-legislator-image-select', function(e) {
                FIAdmin.handleImageSelect(e);
            });
            $(document).on('click', '#fi-legislator-image-remove', function(e) {
                FIAdmin.handleImageRemove(e);
            });
            $(document).on('change', '#fi-legislator-image-upload-input', function(e) {
                FIAdmin.handleImageUploadFile(e);
            });
            $(document).on('change', '#fi-legislator-image-upload-input-visible', function(e) {
                // Summary: run upload directly from the visible input; don't try to assign to .files (read-only).
                FIAdmin.handleImageUploadFile({ target: e.target });
                // Clear so selecting the same file again still triggers change.
                e.target.value = '';
            });
            $(document).on('click', '#fi-legislator-image-fetch', function(e) {
                FIAdmin.handleImageFetchUrl(e);
            });
            // Sync TinyMCE editors to their textareas before vote form submits.
            // External submit buttons (outside <form>) don't always trigger TinyMCE's own sync.
            $(document).on('submit', '#fi-vote-form', function () {
                if (typeof tinyMCE !== 'undefined') {
                    tinyMCE.triggerSave();
                }
            });

            // Vote image picker (media library select/remove only); use class so scoped to picker container
            $(document).on('click', '.fi-vote-image-media-picker .fi-vote-image-select-btn', function(e) {
                FIAdmin.handleVoteImageSelect(e);
            });
            $(document).on('click', '.fi-vote-image-media-picker .fi-vote-image-remove-btn', function(e) {
                FIAdmin.handleVoteImageRemove(e);
            });
        },

        // AJAX logger (best-effort). Controlled by Admin > Settings > Logging > AJAX.
        ajaxLog: function(event, message, data, rid) {
            try {
                var payload = {
                    action: 'fi_ajax_log',
                    nonce: (window.fiAdmin && fiAdmin.nonce) ? fiAdmin.nonce : '',
                    page: (window.pagenow || ''),
                    event: event || '',
                    message: message || '',
                    rid: rid || '',
                    data: data || {}
                };

                var url = (window.fiAdmin && fiAdmin.ajaxUrl) ? fiAdmin.ajaxUrl : ajaxurl;

                // Prefer sendBeacon (survives navigations); fall back to $.post.
                if (navigator && typeof navigator.sendBeacon === 'function') {
                    var fd = new FormData();
                    Object.keys(payload).forEach(function(k) {
                        fd.append(k, (typeof payload[k] === 'object') ? JSON.stringify(payload[k]) : payload[k]);
                    });
                    navigator.sendBeacon(url, fd);
                } else {
                    $.post(url, payload);
                }
            } catch (e) {
                // no-op
            }
        },

        newRid: function(prefix) {
            var p = prefix || 'fi';
            return p + '-' + Date.now() + '-' + Math.random().toString(16).slice(2);
        },

        // Initialize scope selector
        initScopeSelector: function() {
            // Auto-submit on jurisdiction change
            $('#fi-jurisdiction').on('change', function() {
                var jurisdiction = $(this).val();
                FIAdmin.updateSessions(jurisdiction);
            });
        },

        // Initialize filters
        initFilters: function() {
            // Debounced search
            var searchTimeout;
            $('#fi-search').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    FIAdmin.handleSearch();
                }, 300);
            });
        },

        // Initialize notifications
        initNotifications: function() {
            // Auto-hide notifications after 5 seconds
            setTimeout(function() {
                $('.fi-notification').fadeOut();
            }, 5000);
        },

        // Handle jurisdiction change
        handleJurisdictionChange: function() {
            var jurisdiction = $(this).val();
            FIAdmin.updateSessions(jurisdiction);
        },

        // Handle session change
        handleSessionChange: function() {
            $('#fi-scope-form').submit();
        },

        // Update sessions dropdown
        updateSessions: function(jurisdiction) {
            if (!jurisdiction) {
                $('#fi-session').empty().append('<option value="">Select Session</option>');
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fi_get_sessions',
                    jurisdiction: jurisdiction,
                    nonce: fiAdmin.nonce
                },
                beforeSend: function() {
                    $('#fi-session').addClass('fi-loading');
                },
                success: function(response) {
                    if (response.success) {
                        var $sessionSelect = $('#fi-session');
                        $sessionSelect.empty().append('<option value="">Select Session</option>');
                        
                        $.each(response.data.sessions, function(index, session) {
                            $sessionSelect.append(
                                '<option value="' + session.id + '">' + session.name + '</option>'
                            );
                        });
                    }
                },
                complete: function() {
                    $('#fi-session').removeClass('fi-loading');
                }
            });
        },

        // Handle search
        handleSearch: function() {
            var query = $('#fi-search').val();
            if (query.length < 2) {
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fi_admin_action',
                    sub_action: 'search_legislators',
                    query: query,
                    nonce: fiAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FIAdmin.displaySearchResults(response.data);
                    }
                }
            });
        },

        // Handle filter change
        handleFilterChange: function() {
            var filters = FIAdmin.getCurrentFilters();
            FIAdmin.applyFilters(filters);
        },

        // Handle date filter change
        handleDateFilterChange: function() {
            var filters = FIAdmin.getCurrentFilters();
            FIAdmin.applyFilters(filters);
        },

        // Get current filters
        getCurrentFilters: function() {
            return {
                search: $('#fi-search').val(),
                chamber: $('#fi-chamber').val(),
                party: $('#fi-party').val(),
                date_from: $('#fi-date-from').val(),
                date_to: $('#fi-date-to').val()
            };
        },

        // Apply filters
        applyFilters: function(filters) {
            var url = new URL(window.location);
            
            // Clear existing filter parameters
            url.searchParams.delete('search');
            url.searchParams.delete('chamber');
            url.searchParams.delete('party');
            url.searchParams.delete('date_from');
            url.searchParams.delete('date_to');
            
            // Add new filter parameters
            $.each(filters, function(key, value) {
                if (value) {
                    url.searchParams.set(key, value);
                }
            });
            
            // Redirect to filtered URL
            window.location.href = url.toString();
        },

        // Handle scope form submission
        handleScopeSubmit: function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                success: function() {
                    FIAdmin.showNotification('Scope updated successfully', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                },
                error: function() {
                    FIAdmin.showNotification('Error updating scope', 'error');
                }
            });
        },

        // Handle address form submission
        handleAddressSubmit: function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fi_save_user_address',
                    data: formData,
                    nonce: fiAdmin.nonce
                },
                beforeSend: function() {
                    $(this).find('input[type="submit"]').prop('disabled', true).val('Saving...');
                },
                success: function(response) {
                    if (response.success) {
                        FIAdmin.showNotification('Address saved successfully', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        FIAdmin.showNotification('Error saving address: ' + response.data, 'error');
                    }
                },
                error: function() {
                    FIAdmin.showNotification('Error saving address', 'error');
                },
                complete: function() {
                    $(this).find('input[type="submit"]').prop('disabled', false).val('Save Address');
                }
            });
        },

        // Handle recalculate scores
        handleRecalculateScores: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var gov = $btn.data('gov') || '';
            var sessionId = parseInt($btn.data('session-id') || 0, 10) || 0;

            // Confirmation disabled for faster workflow
            // var msg = sessionId > 0
            //     ? 'Recalculate scores for this session and update the freedom score? This may take a while.'
            //     : 'Recalculate scores for all sessions in this government? This may take a while.';
            // if (!confirm(msg)) {
            //     return;
            // }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fi_calculate_scores',
                    gov: gov,
                    session_id: sessionId,
                    nonce: fiAdmin.nonce
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Calculating…');
                },
                success: function(response) {
                    if (response.success) {
                        FIAdmin.showNotification(response.data.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 4000);
                    } else {
                        FIAdmin.showNotification('Error recalculating scores', 'error');
                    }
                },
                error: function() {
                    FIAdmin.showNotification('Error recalculating scores', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Calculate Scores');
                }
            });
        },

        // Handle Freedom score recalculation (batched to avoid timeouts)
        handleRecalculateFreedomScores: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var gov = $btn.data('gov') || '';
            var $progress = $('#fi-score-progress');

            if (!gov) {
                FIAdmin.showNotification('Missing government scope', 'error');
                return;
            }

            if (!confirm('Recalculate Freedom scores for this government?')) {
                return;
            }

            $btn.prop('disabled', true).text('Calculating…');
            $progress.text('Starting…');

            FIAdmin.runFreedomScoreBatches(gov, $progress, function(message) {
                FIAdmin.showNotification(message || 'Freedom scores updated', 'success');
                setTimeout(function() { location.reload(); }, 1200);
            }, function() {
                FIAdmin.showNotification('Error recalculating Freedom scores', 'error');
                $btn.prop('disabled', false).text('Calculate Scores');
                $progress.text('');
            });
        },

        // Shared runner: Freedom score recalculation batches
        runFreedomScoreBatches: function(gov, $progress, onDone, onError) {
            var offset = 0;
            var limit = 100;
            var total = null;

            var runBatch = function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fi_calculate_freedom_scores',
                        gov: gov,
                        offset: offset,
                        limit: limit,
                        nonce: fiAdmin.nonce
                    },
                    success: function(resp) {
                        if (!resp || !resp.success) {
                            if (onError) { onError(); }
                            return;
                        }

                        var data = resp.data || {};
                        if (total === null && typeof data.total !== 'undefined') {
                            total = parseInt(data.total, 10) || 0;
                        }

                        offset = parseInt(data.next_offset, 10) || (offset + limit);

                        if (total !== null) {
                            var shown = Math.min(offset, total);
                            $progress.text('Freedom scores: ' + shown + ' / ' + total);
                        } else {
                            $progress.text('Freedom scores updated…');
                        }

                        if (data.done) {
                            if (onDone) { onDone(data.message); }
                            return;
                        }

                        setTimeout(runBatch, 100);
                    },
                    error: function() {
                        if (onError) { onError(); }
                    }
                });
            };

            runBatch();
        },

        // Gov-only Calculate Scores: session scores (batched by session) then freedom rollup (batched by legislator)
        handleCalculateScoresGov: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var gov = $btn.data('gov') || '';
            var $progress = $('#fi-score-progress');

        if (!gov) {
            FIAdmin.showNotification('Missing government scope', 'error');
            return;
        }

        // Confirmation disabled for faster workflow
        // if (!confirm('Calculate all session scores for this government, then recalculate freedom scores?')) {
        //     return;
        // }

        $btn.prop('disabled', true).text('Calculating…');
            $progress.text('Starting session scores…');

            var offset = 0;
            var limit = 1; // one session per request keeps runtime predictable
            var totalSessions = null;

            var runSessionBatch = function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fi_calculate_gov_scores',
                        gov: gov,
                        offset: offset,
                        limit: limit,
                        nonce: fiAdmin.nonce
                    },
                    success: function(resp) {
                        if (!resp || !resp.success) {
                            FIAdmin.showNotification('Error recalculating session scores', 'error');
                            $btn.prop('disabled', false).text('Calculate Scores');
                            $progress.text('');
                            return;
                        }

                        var data = resp.data || {};
                        if (totalSessions === null && typeof data.total_sessions !== 'undefined') {
                            totalSessions = parseInt(data.total_sessions, 10) || 0;
                        }

                        offset = parseInt(data.next_offset, 10) || (offset + limit);

                        if (totalSessions !== null) {
                            var shown = Math.min(offset, totalSessions);
                            $progress.text('Session scores: ' + shown + ' / ' + totalSessions);
                        } else {
                            $progress.text('Session scores updated…');
                        }

                        if (data.done) {
                            $progress.text('Starting Freedom scores…');
                            FIAdmin.runFreedomScoreBatches(gov, $progress, function(message) {
                                FIAdmin.showNotification(message || 'Scores updated', 'success');
                                setTimeout(function() { location.reload(); }, 1200);
                            }, function() {
                                FIAdmin.showNotification('Error recalculating Freedom scores', 'error');
                                $btn.prop('disabled', false).text('Calculate Scores');
                                $progress.text('');
                            });
                            return;
                        }

                        setTimeout(runSessionBatch, 100);
                    },
                    error: function() {
                        FIAdmin.showNotification('Error recalculating session scores', 'error');
                        $btn.prop('disabled', false).text('Calculate Scores');
                        $progress.text('');
                    }
                });
            };

            runSessionBatch();
        },

        // Handle validate data
        handleValidateData: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fi_validate_data',
                    nonce: fiAdmin.nonce
                },
                beforeSend: function() {
                    $(this).prop('disabled', true).text('Validating...');
                },
                success: function(response) {
                    if (response.success) {
                        FIAdmin.displayValidationResults(response.data);
                    } else {
                        FIAdmin.showNotification('Error validating data', 'error');
                    }
                },
                error: function() {
                    FIAdmin.showNotification('Error validating data', 'error');
                },
                complete: function() {
                    $(this).prop('disabled', false).text('Validate Data');
                }
            });
        },

        // Handle fix issues
        handleFixIssues: function(e) {
            e.preventDefault();
            
            var issueTypes = [];
            $('.fi-issue-checkbox:checked').each(function() {
                issueTypes.push($(this).val());
            });
            
            if (issueTypes.length === 0) {
                FIAdmin.showNotification('Please select issues to fix', 'warning');
                return;
            }
            
            if (!confirm('Are you sure you want to fix the selected issues?')) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fi_fix_data_issues',
                    issue_types: issueTypes,
                    nonce: fiAdmin.nonce
                },
                beforeSend: function() {
                    $(this).prop('disabled', true).text('Fixing...');
                },
                success: function(response) {
                    if (response.success) {
                        FIAdmin.showNotification(response.data.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        FIAdmin.showNotification('Error fixing issues', 'error');
                    }
                },
                error: function() {
                    FIAdmin.showNotification('Error fixing issues', 'error');
                },
                complete: function() {
                    $(this).prop('disabled', false).text('Fix Issues');
                }
            });
        },

        // Handle add list
        handleAddList: function(e) {
            e.preventDefault();
            
            var name = prompt('Enter list name:');
            if (!name) {
                return;
            }
            
            var legislators = [];
            $('.fi-legislator-checkbox:checked').each(function() {
                legislators.push($(this).val());
            });
            
            if (legislators.length === 0) {
                FIAdmin.showNotification('Please select legislators to add to list', 'warning');
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fi_save_user_list',
                    name: name,
                    legislators: legislators,
                    nonce: fiAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FIAdmin.showNotification('List created successfully', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        FIAdmin.showNotification('Error creating list: ' + response.data, 'error');
                    }
                },
                error: function() {
                    FIAdmin.showNotification('Error creating list', 'error');
                }
            });
        },

        // Handle remove list
        handleRemoveList: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to remove this list?')) {
                return;
            }
            
            var listId = $(this).data('list-id');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fi_remove_user_list',
                    list_id: listId,
                    nonce: fiAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FIAdmin.showNotification('List removed successfully', 'success');
                        $(this).closest('.fi-list-item').fadeOut();
                    } else {
                        FIAdmin.showNotification('Error removing list', 'error');
                    }
                },
                error: function() {
                    FIAdmin.showNotification('Error removing list', 'error');
                }
            });
        },

        // Display search results
        displaySearchResults: function(results) {
            // This would be implemented based on the specific search interface
            console.log('Search results:', results);
        },

        // Display validation results
        displayValidationResults: function(issues) {
            var html = '<div class="fi-validation-results">';
            html += '<h3>Data Validation Results</h3>';
            
            if (issues.length === 0) {
                html += '<p>No issues found!</p>';
            } else {
                html += '<p>Found ' + issues.length + ' issues:</p>';
                html += '<ul>';
                
                $.each(issues, function(index, issue) {
                    html += '<li>';
                    html += '<strong>' + issue.message + '</strong> (' + issue.count + ' items)';
                    if (issue.fixable) {
                        html += ' <input type="checkbox" class="fi-issue-checkbox" value="' + issue.type + '">';
                    }
                    html += '</li>';
                });
                
                html += '</ul>';
                
                if ($('.fi-issue-checkbox').length > 0) {
                    html += '<button class="button fi-fix-issues">Fix Selected Issues</button>';
                }
            }
            
            html += '</div>';
            
            $('body').append(html);
        },

        // Show notification
        showNotification: function(message, type) {
            type = type || 'info';
            
            var notification = $('<div class="fi-notification ' + type + '">' + message + '</div>');
            $('body').append(notification);
            
            setTimeout(function() {
                notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        // Utility functions
        utils: {
            // Debounce function
            debounce: function(func, wait, immediate) {
                var timeout;
                return function() {
                    var context = this, args = arguments;
                    var later = function() {
                        timeout = null;
                        if (!immediate) func.apply(context, args);
                    };
                    var callNow = immediate && !timeout;
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                    if (callNow) func.apply(context, args);
                };
            },

            // Format date
            formatDate: function(date) {
                return new Date(date).toLocaleDateString();
            },

            // Format number
            formatNumber: function(num) {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }
        },

        // Initialize image media picker
        initImageMediaPicker: function() {
            // Media picker is initialized via event handlers
            // This method can be used for any setup needed
        },

        initTomSelect: function(id, opts) {
            var el = document.getElementById(id);
            console.log('[FI] initTomSelect', id, el ? 'found' : 'NOT FOUND', 'TomSelect:', typeof TomSelect);
            if (!el || typeof TomSelect === 'undefined') { return; }
            // Guard: skip if already initialized (prevents double-init on bfcache restore).
            if (el.tomselect) {
                console.log('[FI] initTomSelect', id, 'already initialized — skipping');
                return;
            }
            var preSelected = Array.prototype.slice.call(el.options)
                .filter(function(o) { return o.selected; })
                .map(function(o) { return o.value; });
            console.log('[FI] initTomSelect', id, 'preSelected:', preSelected);
            try {
                new TomSelect(el, Object.assign({
                    plugins: ['remove_button'],
                    create: false,
                    items: preSelected,
                    onItemAdd: function() { this.setTextboxValue(''); },
                }, opts || {}));
                console.log('[FI] initTomSelect', id, 'OK');
            } catch (e) {
                console.error('[FI] initTomSelect', id, 'FAILED:', e);
            }
        },

        initCitationSelect: function() {
            this.initTomSelect('meta_citation', { maxOptions: 200, placeholder: 'Select citations\u2026' });
        },

        initTagSelect: function() {
            this.initTomSelect('vote_tags_select', { maxOptions: 500, placeholder: 'Select issues\u2026' });
        },

        // Handle image select button click
        handleImageSelect: function(e) {
            e.preventDefault();
            
            var imageIdInput = $('#fi-legislator-image-id');
            var imagePreview = $('#fi-legislator-image-preview');
            var previewContainer = $('.fi-image-preview');
            var selectButton = $('#fi-legislator-image-select');
            var removeButton = $('#fi-legislator-image-remove');
            
            // Create media frame
            var frame = wp.media({
                title: 'Select or Upload Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            // When image is selected
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                
                // Update hidden input
                imageIdInput.val(attachment.id);
                
                // Update preview
                imagePreview.attr('src', attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url);
                imagePreview.attr('alt', attachment.alt || '');
                previewContainer.show();
                
                // Update buttons
                selectButton.text('Change Image');
                if (removeButton.length === 0) {
                    selectButton.after('<button type="button" class="button button-link-delete" id="fi-legislator-image-remove">Remove</button>');
                } else {
                    removeButton.show();
                }
            });
            
            // Open media frame
            frame.open();
        },

        // Handle image remove button click
        handleImageRemove: function(e) {
            e.preventDefault();
            
            var imageIdInput = $('#fi-legislator-image-id');
            var imagePreview = $('#fi-legislator-image-preview');
            var previewContainer = $('.fi-image-preview');
            var selectButton = $('#fi-legislator-image-select');
            var removeButton = $('#fi-legislator-image-remove');
            
            // Clear values
            imageIdInput.val('0');
            imagePreview.attr('src', '');
            imagePreview.attr('alt', '');
            previewContainer.hide();
            
            // Update buttons
            selectButton.text('Select Image');
            removeButton.hide();
        },

        // Vote image picker: open media library; scope to picker container so it works on vote edit page
        handleVoteImageSelect: function(e) {
            e.preventDefault();
            if (typeof wp === 'undefined' || typeof wp.media !== 'function') {
                return;
            }
            var $picker = $(e.currentTarget).closest('.fi-vote-image-media-picker');
            if (!$picker.length) return;
            var imageIdInput = $picker.find('.fi-vote-image-id');
            var imagePreview = $picker.find('.fi-vote-image-preview-img');
            var previewContainer = $picker.find('.fi-image-preview');
            var selectButton = $picker.find('.fi-vote-image-select-btn');
            var removeButton = $picker.find('.fi-vote-image-remove-btn');
            var frame = wp.media({
                title: 'Select or Upload Image',
                button: { text: 'Use this image' },
                multiple: false,
                library: { type: 'image' }
            });
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                imageIdInput.val(attachment.id);
                imagePreview.attr('src', attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url);
                imagePreview.attr('alt', attachment.alt || '');
                previewContainer.show();
                selectButton.text('Change Image');
                removeButton.show();
            });
            frame.open();
        },

        handleVoteImageRemove: function(e) {
            e.preventDefault();
            var $picker = $(e.currentTarget).closest('.fi-vote-image-media-picker');
            if (!$picker.length) return;
            $picker.find('.fi-vote-image-id').val('0');
            $picker.find('.fi-vote-image-preview-img').attr('src', '').attr('alt', '');
            $picker.find('.fi-image-preview').hide();
            $picker.find('.fi-vote-image-select-btn').text('Select Image');
            $picker.find('.fi-vote-image-remove-btn').hide();
        },

        // Handle file selection -> AJAX upload + register
        handleImageUploadFile: function(e) {
            var input = e.target;
            if (!input || !input.files || !input.files.length) return;

            var rid = FIAdmin.newRid('upload');
            var file = input.files[0];
            var wrap = $('.wrap.fi-legislator-edit');
            var legislatorId = wrap.data('legislator-id') || $('input[name="legislator_id"]').first().val() || '';
            if (!legislatorId) {
                alert('Missing legislator ID on page.');
                return;
            }

            FIAdmin.ajaxLog('upload.before', 'upload_legislator_image beforeSend', {
                legislator_id: legislatorId,
                filename: (file && file.name) ? file.name : ''
            }, rid);

            var fd = new FormData();
            fd.append('action', 'fi_admin_action');
            fd.append('sub_action', 'upload_legislator_image');
            fd.append('nonce', (window.fiAdmin && fiAdmin.nonce) ? fiAdmin.nonce : '');
            fd.append('legislator_id', legislatorId);
            fd.append('file', file);

            var selectBtn = $('#fi-legislator-image-select');
            FIAdmin.showNotification('Uploading image…', 'info');

            $.ajax({
                url: (window.fiAdmin && fiAdmin.ajaxUrl) ? fiAdmin.ajaxUrl : ajaxurl,
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                success: function(resp) {
                    console.log('[FI] upload_legislator_image response', resp);
                    FIAdmin.ajaxLog('upload.response', 'upload_legislator_image response', { resp: resp }, rid);
                    if (!resp || !resp.success) {
                        var msg = (resp && resp.data) ? (typeof resp.data === 'string' ? resp.data : JSON.stringify(resp.data)) : 'Upload failed';
                        FIAdmin.showNotification(msg, 'error');
                        return;
                    }
                    var d = resp.data || {};

                    // Update hidden input + preview
                    $('#fi-legislator-image-id').val(d.attachment_id || 0);
                    var imgUrl = d.url || '';
                    if (imgUrl) {
                        $('#fi-legislator-image-preview').attr('src', imgUrl).attr('alt', '');
                        $('.fi-image-preview').show();
                    }

                    // Update buttons
                    selectBtn.text('Change Image');
                    var removeBtn = $('#fi-legislator-image-remove');
                    if (removeBtn.length === 0) {
                        selectBtn.after('<button type="button" class="button button-link-delete" id="fi-legislator-image-remove">Remove</button>');
                    } else {
                        removeBtn.show();
                    }
                    FIAdmin.showNotification('Image uploaded.', 'success');
                },
                error: function(xhr) {
                    console.log('[FI] upload_legislator_image xhr', xhr);
                    FIAdmin.ajaxLog('upload.error', 'upload_legislator_image xhr', {
                        status: (xhr && xhr.status) ? xhr.status : null,
                        responseText: (xhr && xhr.responseText) ? ('' + xhr.responseText).slice(0, 2000) : ''
                    }, rid);
                    FIAdmin.showNotification('Upload failed (' + (xhr && xhr.status ? xhr.status : 'unknown') + ')', 'error');
                },
                complete: function() {}
            });
        }
        ,

        // Fetch image from URL -> download into local repo -> register -> set image_id
        handleImageFetchUrl: function(e) {
            e.preventDefault();

            var rid = FIAdmin.newRid('fetch');
            var wrap = $('.wrap.fi-legislator-edit');
            var legislatorId = wrap.data('legislator-id') || $('input[name="legislator_id"]').first().val() || '';
            if (!legislatorId) {
                alert('Missing legislator ID on page.');
                return;
            }

            var url = ('' + $('#fi-legislator-image-url').val()).trim();
            if (!url) {
                alert('Paste an image URL first.');
                return;
            }

            var btn = $('#fi-legislator-image-fetch');
            btn.prop('disabled', true).text('Fetching...');

            FIAdmin.ajaxLog('fetch.before', 'fetch_legislator_image_url beforeSend', {
                legislator_id: legislatorId,
                url: url
            }, rid);

            $.ajax({
                url: (window.fiAdmin && fiAdmin.ajaxUrl) ? fiAdmin.ajaxUrl : ajaxurl,
                type: 'POST',
                data: {
                    action: 'fi_admin_action',
                    sub_action: 'fetch_legislator_image_url',
                    nonce: (window.fiAdmin && fiAdmin.nonce) ? fiAdmin.nonce : '',
                    legislator_id: legislatorId,
                    url: url
                },
                success: function(resp) {
                    console.log('[FI] fetch_legislator_image_url response', resp);
                    FIAdmin.ajaxLog('fetch.response', 'fetch_legislator_image_url response', { resp: resp }, rid);
                    if (!resp || !resp.success) {
                        var msg = (resp && resp.data) ? (typeof resp.data === 'string' ? resp.data : JSON.stringify(resp.data)) : 'Fetch failed';
                        FIAdmin.showNotification(msg, 'error');
                        return;
                    }
                    var d = resp.data || {};
                    $('#fi-legislator-image-id').val(d.attachment_id || 0);
                    if (d.url) {
                        $('#fi-legislator-image-preview').attr('src', d.url).attr('alt', '');
                        $('.fi-image-preview').show();
                    }
                    $('#fi-legislator-image-select').text('Change Image');
                    var removeBtn = $('#fi-legislator-image-remove');
                    if (removeBtn.length === 0) {
                        $('#fi-legislator-image-select').after('<button type="button" class="button button-link-delete" id="fi-legislator-image-remove">Remove</button>');
                    } else {
                        removeBtn.show();
                    }
                    FIAdmin.showNotification('Image fetched.', 'success');
                },
                error: function(xhr) {
                    console.log('[FI] fetch_legislator_image_url xhr', xhr);
                    FIAdmin.ajaxLog('fetch.error', 'fetch_legislator_image_url xhr', {
                        status: (xhr && xhr.status) ? xhr.status : null,
                        responseText: (xhr && xhr.responseText) ? ('' + xhr.responseText).slice(0, 2000) : ''
                    }, rid);
                    FIAdmin.showNotification('Fetch failed (' + (xhr && xhr.status ? xhr.status : 'unknown') + ')', 'error');
                },
                complete: function() {
                    btn.prop('disabled', false).text('Fetch');
                }
            });
        }
    };

})(jQuery);

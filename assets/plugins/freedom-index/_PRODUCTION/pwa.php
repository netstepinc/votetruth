<?php
/**
 * FI PWA — Single File Engine (Agnostic & Manual Config)
 * -----------------------------------------------------------------------------
 * INSTRUCTIONS: 
 * 1. Edit the DEFINES below.
 * 2. Drop into functions.php or a custom plugin file.
 * 3. Save Permalinks (Settings > Permalinks) to activate the virtual routes.
 */

namespace FI\PWA;

// --- 1. CONFIGURATION --------------------------------------------------------
define('FI_PWA_NAME',        'Freedom Index'); //get_bloginfo('name')
define('FI_PWA_SHORT_NAME',  'FreedomIndex.US'); 
define('FI_PWA_THEME_COLOR', '#02275D');
define('FI_PWA_ICON_DIR',    get_stylesheet_directory_uri() . '/assets/pwa/'); 
define('FI_PWA_VERSION',     '260410'); // Increment this to force-clear cache
define('FI_PWA_PROMPT', true);

// Prompt strategy (all configurable for careful rollout/testing)
define('FI_PWA_PROMPT_MIN_SESSIONS', 2);
define('FI_PWA_PROMPT_MIN_PAGE_VIEWS', 12);
define('FI_PWA_PROMPT_MIN_ACTIVE_SECONDS', 180);
define('FI_PWA_PROMPT_DELAY_MS', 2500);
define('FI_PWA_PROMPT_MAX_DISMISSES', 3);
define('FI_PWA_PROMPT_SNOOZE_DAYS_1', 14);
define('FI_PWA_PROMPT_SNOOZE_DAYS_2', 45);
define('FI_PWA_PROMPT_APP_URL', '/app/');
define('FI_PWA_PROMPT_CTA_TO_APP', true);

// --- 2. VIRTUAL FILE ROUTING -------------------------------------------------
add_action('init', function() {
    // Match both trailing and non-trailing slash variants.
    add_rewrite_rule('^sw\.js/?$', 'index.php?fi_pwa_res=sw', 'top');
    add_rewrite_rule('^manifest\.json/?$', 'index.php?fi_pwa_res=manifest', 'top');
});

add_filter('redirect_canonical', function($redirect_url, $requested_url) {
    // Prevent WP canonical trailing-slash redirects for virtual PWA resources.
    $path = (string) parse_url((string) $requested_url, PHP_URL_PATH);
    $normalized = rtrim($path, '/');
    if ($normalized === '/sw.js' || $normalized === '/manifest.json') {
        return false;
    }
    return $redirect_url;
}, 10, 2);

add_filter('query_vars', function($vars) {
    $vars[] = 'fi_pwa_res';
    return $vars;
});

add_action('template_redirect', function() {
    $res = get_query_var('fi_pwa_res');
    if (!$res) return;

    if ($res === 'sw') {
        header('Content-Type: application/javascript');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        ?>
        const CACHE_NAME = 'fi-pwa-cache-<?php echo FI_PWA_VERSION; ?>';
        const OFFLINE_URL = '/offline/';

        // Files to cache immediately on install
        const PRECACHE_ASSETS = [
            OFFLINE_URL,
            '<?php echo FI_PWA_ICON_DIR; ?>icon-192.png'
        ];

        self.addEventListener('install', (e) => {
            e.waitUntil(caches.open(CACHE_NAME).then(c => c.addAll(PRECACHE_ASSETS)));
            self.skipWaiting();
        });

        self.addEventListener('activate', (e) => {
            e.waitUntil(caches.keys().then(keys => Promise.all(
                keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
            )));
        });

        // Stale-While-Revalidate Strategy
        self.addEventListener('fetch', (event) => {
            if (event.request.method !== 'GET') return;

            event.respondWith(
                caches.open(CACHE_NAME).then((cache) => {
                    return cache.match(event.request).then((cachedResponse) => {
                        const fetchedResponse = fetch(event.request).then((networkResponse) => {
                            // Update cache for next time if it's a same-origin request
                            if (event.request.url.startsWith(self.location.origin)) {
                                cache.put(event.request, networkResponse.clone());
                            }
                            return networkResponse;
                        }).catch(() => {
                            // Offline fallback for navigation
                            if (event.request.mode === 'navigate') return caches.match(OFFLINE_URL);
                        });

                        return cachedResponse || fetchedResponse;
                    });
                })
            );
        });
        <?php
        exit;
    }

    if ($res === 'manifest') {
        header('Content-Type: application/manifest+json');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo json_encode([
            'id'               => 'freedomindex-us',
            'name'             => FI_PWA_NAME,
            'short_name'       => FI_PWA_SHORT_NAME,
            'description'      => 'The Freedom Index provides legislator scorecards to help citizens hold their elected officials accountable to the Constitution and preserve liberty for future generations.',
            'start_url'        => '/?utm_source=pwa',
            'scope'            => '/',
            'display'          => 'standalone',
            'display_override'  => ['standalone', 'minimal-ui', 'window-controls-overlay'],
            'launch_handler'   => ['client_mode' => 'navigate-existing'],
            'orientation'      => 'any',
            'lang'             => 'en-US',
            'dir'              => 'ltr',
            'theme_color'      => FI_PWA_THEME_COLOR,
            'background_color' => '#ffffff',
            'categories'       => ['government', 'news', 'politics'],
            'icons' => [
                ['src' => FI_PWA_ICON_DIR . 'icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => FI_PWA_ICON_DIR . 'icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable']
            ],
            'screenshots' => [
                ['src' => FI_PWA_ICON_DIR . 'screen-1.png',  'sizes' => '1290x2796', 'type' => 'image/png', 'form_factor' => 'narrow', 'label' => 'Home — search, stats, and state map'],
                ['src' => FI_PWA_ICON_DIR . 'screen-2.png',  'sizes' => '1290x2796', 'type' => 'image/png', 'form_factor' => 'narrow', 'label' => 'Congressional Scorecard — best and worst legislators'],
                ['src' => FI_PWA_ICON_DIR . 'screen-3.png',  'sizes' => '1290x2796', 'type' => 'image/png', 'form_factor' => 'narrow', 'label' => 'Browse all 540 Congressional legislators'],
                ['src' => FI_PWA_ICON_DIR . 'screen-4.png',  'sizes' => '1290x2796', 'type' => 'image/png', 'form_factor' => 'narrow', 'label' => 'Legislator detail with Freedom Score and vote history'],
                ['src' => FI_PWA_ICON_DIR . 'screen-5.png',  'sizes' => '1290x2796', 'type' => 'image/png', 'form_factor' => 'narrow', 'label' => 'Share a legislator scorecard'],
                ['src' => FI_PWA_ICON_DIR . 'screen-6.png',  'sizes' => '1290x2796', 'type' => 'image/png', 'form_factor' => 'narrow', 'label' => 'Legislator contact information'],
                ['src' => FI_PWA_ICON_DIR . 'screen-7.png',  'sizes' => '1290x2796', 'type' => 'image/png', 'form_factor' => 'narrow', 'label' => 'Personalize printed scorecards with your contact info'],
                ['src' => FI_PWA_ICON_DIR . 'screen-8.png',  'sizes' => '1290x2796', 'type' => 'image/png', 'form_factor' => 'narrow', 'label' => 'Print scorecards in multiple formats'],
                ['src' => FI_PWA_ICON_DIR . 'screen-9.png',  'sizes' => '2736x1824', 'type' => 'image/png', 'form_factor' => 'wide', 'label' => 'Desktop — browse all Congressional legislators'],
                ['src' => FI_PWA_ICON_DIR . 'screen-10.png', 'sizes' => '2736x1824', 'type' => 'image/png', 'form_factor' => 'wide', 'label' => 'Desktop — legislator detail with Freedom Score and vote history'],
                ['src' => FI_PWA_ICON_DIR . 'screen-11.png', 'sizes' => '2736x1824', 'type' => 'image/png', 'form_factor' => 'wide', 'label' => 'Desktop — Congressional votes with issue filters'],
                ['src' => FI_PWA_ICON_DIR . 'screen-12.png', 'sizes' => '2736x1824', 'type' => 'image/png', 'form_factor' => 'wide', 'label' => 'Desktop — Congressional Vote Reports scorecard'],
            ],
            'shortcuts' => [
                ['name' => 'My Account', 'url' => '/my-account/', 'description' => 'View your profile']
            ]
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
});

// --- 3. PERSISTENT AUTH (1-Year Login) ---------------------------------------
add_filter('auth_cookie_expiration', function() { return 31536000; }, 99);

// Force "Remember Me" to be checked on the login form automatically
add_action('login_footer', function() {
    echo '<script>if(document.getElementById("rememberme")) document.getElementById("rememberme").checked = true;</script>';
});

// --- 4. FRONT-END UI & ONBOARDING --------------------------------------------
add_action('wp_head', function() {
    echo '<link rel="manifest" href="' . home_url('/manifest.json') . '">';
    echo '<meta name="theme-color" content="' . FI_PWA_THEME_COLOR . '">';
    echo '<meta name="apple-mobile-web-app-capable" content="yes">';
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">';
});

add_action('wp_footer', function() {
    if (is_admin()) return;
    $prompt_enabled = FI_PWA_PROMPT === true;
    ?>
    <?php if ($prompt_enabled): ?>
    <style>
    /* col-md-6 / col-lg-4 handle width; these two rules center the fixed element at each breakpoint */
    @media (min-width: 768px) { #fi-pwa-drawer { left: 25% !important; right: auto !important; } }
    @media (min-width: 992px) { #fi-pwa-drawer { left: 33.333% !important; right: auto !important; } }
    </style>
    <div id="fi-pwa-drawer" class="offcanvas offcanvas-bottom h-auto border-0 col-md-6 col-lg-4" tabindex="-1" style="border-radius: 24px 24px 0 0;" aria-labelledby="fi-pwa-drawer-title">
        <div class="offcanvas-body text-center p-4">
            <div class="mb-3 d-inline-block p-1 border rounded-4 shadow-sm">
                <img src="<?php echo FI_PWA_ICON_DIR; ?>icon-192.png" width="70" class="rounded-4">
            </div>
            <h5 id="fi-pwa-drawer-title" class="fw-bold mb-1">Get the <?php echo FI_PWA_SHORT_NAME; ?> App</h5>
            <p class="text-muted small mb-4 px-3">Install for faster access, or learn how this app experience works.</p>
            
            <div id="pwa-action-area">
                <button id="pwa-install-btn" class="btn btn-primary btn-lg w-100 rounded-pill py-3 fw-bold shadow">Install Now</button>
            </div>
            <?php if (FI_PWA_PROMPT_CTA_TO_APP): ?>
            <a href="<?php echo esc_url(home_url(FI_PWA_PROMPT_APP_URL)); ?>" class="btn btn-outline-secondary btn-sm mt-3 rounded-pill px-4">Learn About the App</a>
            <?php endif; ?>
            <button type="button" class="btn btn-link btn-sm mt-3 text-muted text-decoration-none" data-bs-dismiss="offcanvas">Maybe Later</button>
        </div>
    </div>
    <?php endif; ?>

    <script>
    (function($) {
        let deferredPrompt = null;
        const promptEnabled = <?php echo $prompt_enabled ? 'true' : 'false'; ?>;
        const drawerEl = promptEnabled ? document.getElementById('fi-pwa-drawer') : null;
        const drawer = (promptEnabled && drawerEl && window.bootstrap && bootstrap.Offcanvas)
            ? new bootstrap.Offcanvas(drawerEl)
            : null;
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
        const appInfoUrl = <?php echo wp_json_encode(home_url(FI_PWA_PROMPT_APP_URL)); ?>;
        const cfg = {
            minSessions: <?php echo (int) FI_PWA_PROMPT_MIN_SESSIONS; ?>,
            minPageViews: <?php echo (int) FI_PWA_PROMPT_MIN_PAGE_VIEWS; ?>,
            minActiveSeconds: <?php echo (int) FI_PWA_PROMPT_MIN_ACTIVE_SECONDS; ?>,
            delayMs: <?php echo (int) FI_PWA_PROMPT_DELAY_MS; ?>,
            maxDismisses: <?php echo (int) FI_PWA_PROMPT_MAX_DISMISSES; ?>,
            snooze1Ms: <?php echo (int) FI_PWA_PROMPT_SNOOZE_DAYS_1 * 86400000; ?>,
            snooze2Ms: <?php echo (int) FI_PWA_PROMPT_SNOOZE_DAYS_2 * 86400000; ?>,
            ctaToApp: <?php echo FI_PWA_PROMPT_CTA_TO_APP ? 'true' : 'false'; ?>
        };
        const storage = {
            sessions: 'fi_pwa_sessions',
            lastSession: 'fi_pwa_last_session',
            pageViews: 'fi_pwa_page_views',
            activeMs: 'fi_pwa_active_ms',
            dismisses: 'fi_pwa_dismisses',
            snoozeUntil: 'fi_pwa_snooze_until',
            promptedThisSession: 'fi_pwa_prompted_this_session'
        };

        // Shared install bridge so any page (like /app) can trigger install consistently.
        window.FI_PWA = window.FI_PWA || {};
        window.FI_PWA.isIOS = isIOS;
        window.FI_PWA.isStandalone = !!isStandalone;
        window.FI_PWA.hasPrompt = false;
        // Dev helper: call FI_PWA.showDrawer() from the browser console to test the prompt UI.
        window.FI_PWA.showDrawer = function() {
            if (drawer) { drawer.show(); } else { console.warn('FI_PWA: drawer not available (prompt disabled or Bootstrap not loaded)'); }
        };
        window.FI_PWA.install = async function() {
            if (isStandalone) {
                return { ok: false, reason: 'already_installed' };
            }
            if (!deferredPrompt) {
                return { ok: false, reason: 'prompt_unavailable' };
            }
            deferredPrompt.prompt();
            const choice = await deferredPrompt.userChoice;
            const accepted = !!(choice && choice.outcome === 'accepted');
            deferredPrompt = null;
            window.FI_PWA.hasPrompt = false;
            return {
                ok: accepted,
                reason: accepted ? 'accepted' : 'dismissed',
                outcome: choice && choice.outcome ? choice.outcome : 'unknown'
            };
        };

        function toInt(key, fallback) {
            const raw = localStorage.getItem(key);
            const val = parseInt(raw || '', 10);
            return Number.isFinite(val) ? val : fallback;
        }

        // Track engagement with session + pageview + active-time gates.
        function updateEngagementCounters() {
            const now = Date.now();
            const dayMs = 86400000;
            const lastSession = toInt(storage.lastSession, 0);

            if (!lastSession || (now - lastSession) > dayMs) {
                const sessions = toInt(storage.sessions, 0) + 1;
                localStorage.setItem(storage.sessions, String(sessions));
                localStorage.setItem(storage.promptedThisSession, '0');
            }
            localStorage.setItem(storage.lastSession, String(now));

            const views = toInt(storage.pageViews, 0) + 1;
            localStorage.setItem(storage.pageViews, String(views));
        }

        function startActiveTimer() {
            const started = Date.now();
            const persist = function() {
                const elapsed = Math.max(0, Date.now() - started);
                const total = toInt(storage.activeMs, 0) + elapsed;
                localStorage.setItem(storage.activeMs, String(total));
            };
            window.addEventListener('beforeunload', persist, { once: true });
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'hidden') persist();
            }, { once: true });
        }

        function getSnoozeMs(dismisses) {
            if (dismisses <= 0) return 0;
            if (dismisses === 1) return cfg.snooze1Ms;
            return cfg.snooze2Ms;
        }

        function canPromptNow() {
            if (!promptEnabled || isStandalone) return false;
            if (toInt(storage.promptedThisSession, 0) >= 1) return false;

            const dismisses = toInt(storage.dismisses, 0);
            if (dismisses >= cfg.maxDismisses) return false;

            const now = Date.now();
            const snoozeUntil = toInt(storage.snoozeUntil, 0);
            if (snoozeUntil && now < snoozeUntil) return false;

            const sessions = toInt(storage.sessions, 0);
            const views = toInt(storage.pageViews, 0);
            const activeMs = toInt(storage.activeMs, 0);
            const minActiveMs = cfg.minActiveSeconds * 1000;

            return sessions >= cfg.minSessions && views >= cfg.minPageViews && activeMs >= minActiveMs;
        }

        function markPromptShown() {
            localStorage.setItem(storage.promptedThisSession, '1');
        }

        function handleDismiss() {
            const dismisses = toInt(storage.dismisses, 0) + 1;
            localStorage.setItem(storage.dismisses, String(dismisses));
            localStorage.setItem(storage.snoozeUntil, String(Date.now() + getSnoozeMs(dismisses)));
        }

        // Register Service Worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?php echo home_url('/sw.js'); ?>', { scope: '/' });
        }

        updateEngagementCounters();
        startActiveTimer();

        // Prompt system is opt-in; if disabled, do not intercept native install signals.
        // Capture Android/Chrome install event so page-level CTA buttons can trigger prompt.
        // Track PWA pageviews in standalone mode (covers all pages, not just the start_url entry).
        if (isStandalone && typeof plausible === 'function') {
            plausible('PWA Pageview');
        }

        // Track when the install is completed (fires after user accepts and icon is added).
        window.addEventListener('appinstalled', () => {
            if (typeof plausible === 'function') plausible('PWA Installed');
        });

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            window.FI_PWA.hasPrompt = true;
            if (typeof plausible === 'function') plausible('PWA Prompt Shown');
            if (!promptEnabled) return;
            if (!canPromptNow()) return;
            markPromptShown();
            if (drawer) setTimeout(() => drawer.show(), cfg.delayMs);
        });

        // iOS has no beforeinstallprompt event, so we use engagement rules directly.
        if (promptEnabled && isIOS && !isStandalone && canPromptNow()) {
            markPromptShown();
            if (drawer) {
                $('#pwa-action-area').html(`
                    <div class="card bg-light border-0 rounded-4 p-3 text-start mb-2">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-primary rounded-circle me-2">1</span>
                            <span>Tap the <strong>Share</strong> button <i class="fa-solid fa-arrow-up-from-bracket text-primary ms-1"></i></span>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-primary rounded-circle me-2">2</span>
                            <span>Select <strong>'Add to Home Screen'</strong></span>
                        </div>
                    </div>
                `);
                setTimeout(() => drawer.show(), cfg.delayMs);
            }
        }

        if (promptEnabled && drawerEl) {
            $('#pwa-install-btn').on('click', async () => {
                if (cfg.ctaToApp) {
                    window.location.href = appInfoUrl;
                    return;
                }

                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    const choice = await deferredPrompt.userChoice;
                    if (choice && choice.outcome === 'accepted' && drawer) drawer.hide();
                    deferredPrompt = null;
                    return;
                }

                // Fallback route for browsers/platforms without install prompt support.
                window.location.href = appInfoUrl;
            });

            $(drawerEl).on('hidden.bs.offcanvas', () => {
                handleDismiss();
            });
        }

    })(jQuery);
    </script>
    <?php
}, 100);
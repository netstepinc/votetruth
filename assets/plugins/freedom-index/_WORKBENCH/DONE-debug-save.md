# Admin Save: Known Issues, Root Causes & Verification Guide

---

## DEV VERSION DIAGNOSIS — Jun 23, 2026

**Version:** Procedural refactor (dev)  
**Diagnosed by:** Claude Sonnet 4.6  
**Status:** Issues found. Plan below. No code changed yet.

---

### Findings: What Is Present vs. What Is Missing

The production version fixed four categories of issues. Here is the status of each in the dev version:

---

#### ISSUE 1 — `?updated=1` URL flag still used (Votes, Sessions, Legislators, Reports)

**Status: NOT FIXED — all four handlers still use it.**

The correct pattern uses `add_settings_error()` for the notice and redirects to a **clean URL** (no `?updated=1`).  
The dev version uses `?updated=1` in the redirect URL, and the view templates read it from `$_GET` to render the notice.

| Handler | Redirect line | Notice source in view |
|---|---|---|
| `admin/autoload/votes.php` line 212 | `fi_admin_edit_vote_url($saved_id, ['updated' => 1])` | `$_GET['updated']` check in `vote-edit.php` line 91 |
| `admin/autoload/sessions.php` line 147 | `fi_admin_edit_session_url($saved_id, ['updated' => 1])` | `$_GET['updated']` check in `session-edit.php` line 88 |
| `admin/autoload/legislators.php` line 105–108 | `fi_admin_edit_legislator_url($saved_id, ['updated' => 1, ...])` | `$_GET['updated']` check in `legislator-edit.php` line 158 |
| `admin/autoload/reports.php` line 97–101 | `fi_admin_url('fi-reports', ['updated' => '1', ...])` | `$_GET['updated']` check in `report-edit.php` line 109 |

**Why it matters:** same URL as prior save = browser may serve cached page, notice disappears on second save.

**Fix:** Switch each handler to `add_settings_error()` + clean redirect. Add `settings_errors()` call in each view where absent.

**Note on `vote-edit.php`:** `settings_errors('fi_votes')` is already present on line 89 (before the `$_GET['updated']` block). So for votes: remove the `?updated=1` from the redirect, remove the `$_GET['updated']` block from the view — the transient notice will display.

**Note on `legislator-edit.php`:** No `settings_errors()` call exists in the view. Will need to add it.

**Note on `session-edit.php`:** No `settings_errors()` call exists in the view. Will need to add it.

**Note on `report-edit.php`:** No `settings_errors()` call exists in the view. Will need to add it.

---

#### ISSUE 2 — `fi_cache_clear()` not called after saves (Votes, Sessions, Legislators, Reports)

**Status: NOT FIXED — all four save handlers are missing the cache clear call.**

`fi_cache_clear()` exists in `core/cache.php` (verified). The production versions of the votes, legislators, and sessions handlers all call it after a successful save. The dev versions do not.

| Handler | Missing call |
|---|---|
| `admin/autoload/votes.php` | `fi_cache_clear('votes')` |
| `admin/autoload/sessions.php` | `fi_cache_clear('sessions')` |
| `admin/autoload/legislators.php` | `fi_cache_clear('legislators')` |
| `admin/autoload/reports.php` | No equivalent — confirm if reports have a cache type |

**Note:** The production notes say the best placement is inside the `*_save()` core function itself so it fires regardless of code path. However, adding it at the admin handler layer is acceptable and lower-risk for the initial fix. Confirm preference before implementing.

---

#### ISSUE 3 — Cache-Control `no-store` headers NOT set on FI admin pages

**Status: NOT FIXED — the `admin_init` and `admin_head` hooks are absent from dev `scope.php`.**

The production `scope.php` adds two hooks:
- `admin_init` at priority `-10` — sets `Cache-Control: no-cache, no-store, must-revalidate` on the redirect response before `wp_safe_redirect()` fires.
- `admin_head` at priority `1` — re-asserts `no-store` after `nocache_headers()` overwrites it during page render.

The dev `scope.php` lists `fi_scope_send_admin_no_cache_headers()` in its header comment (line 40) but **the function and its hooks are never defined or registered anywhere in the file.** This is dead/phantom documentation.

**Fix:** Add the two `add_action()` hooks to dev `scope.php` — same code as production, no function wrapper needed (use anonymous functions as in production).

---

#### ISSUE 4 — TinyMCE `triggerSave()` NOT in dev `admin.js`

**Status: NOT FIXED.**

The vote edit form (`#fi-vote-form`) has three `wysiwyg` (TinyMCE) fields (`description_short`, `description_medium`, `description_long`). The Save button is in a sticky header bar **outside the `<form>` element** using `form="fi-vote-form"`. TinyMCE does not reliably fire its sync hook in all browsers when an external submit button is used.

The production `_PRODUCTION/assets/js/admin.js` (line 58–60) adds:
```javascript
$(document).on('submit', '#fi-vote-form', function () {
    if (typeof tinyMCE !== 'undefined') {
        tinyMCE.triggerSave();
    }
});
```

The dev `admin.js` has no `tinyMCE` reference anywhere. This means description edits can be silently discarded on save.

**Fix:** Add the `submit` handler to `admin.js` `bindEvents()`.

---

#### ISSUE 5 — Sessions save handler: `headers_sent()` JS fallback (minor / defensible)

**Status: Present in dev, also present in production.**

Both dev and production `sessions.php` save handlers include a `headers_sent()` JS fallback redirect. The production notes flagged this as something to eventually fix by hooking the session save to `admin_init` — but the production version **did not fix it either**. This is a known limitation, not a regression.

**However:** The dev `actions.php` hooks votes and legislators saves to `admin_init`, but the session save is **not** hooked there. It is called from `fi_admin_sessions_maybe_handle_save()` which is called from the view template (`session-edit.php` → `sessions.php`). This is the pattern the production notes identified as wrong but did not fully fix.

**Question for user:** Should the session save be moved to `admin_init` in `actions.php` as part of this fix (matching votes/legislators), or defer that to a separate task?

---

#### ISSUE 6 — Votes save: `try/catch` (Exception + Error) wrapping the save logic

**Status: Present in dev only — not in production.**

The dev `votes.php` `fi_admin_votes_handle_save()` wraps the save call (lines 185–226) in a `try/catch (Exception)` + `catch (Error)` block.

The rules file explicitly prohibits `try/catch` as silent failure masking: *"No `try/catch`, empty error returns, or fallback values to mask missing functions or bad data. Surface the problem."*

The production version has no such wrapper. If the save function throws, it should surface — not be caught and shown as a generic admin error string.

**Fix:** Remove the `try/catch` wrappers. The `fi_vote_save()` call and surrounding code should execute without a safety net.

---

#### ISSUE 7 — `fi_admin_votes_get_defaults()` returns `stdClass` object instead of array

**Status: Present in dev — not in production.**

Dev `votes.php` line 286: `fi_admin_votes_get_defaults()` returns `(object)[...]`.  
Dev `sessions.php` line 20: `fi_admin_sessions_get_defaults()` also returns `(object)[...]`.

The rules file: *"NO `stdClass` for data - use arrays `[]` instead."*  
The legislator defaults (`fi_admin_legislators_get_defaults()`) correctly returns an array.

**Fix:** Change both to return `[]`. Need to verify the view templates don't use `->` property access on these defaults — that would break and need to be updated simultaneously.

---

#### ISSUE 8 — `fi_admin_actions_handle_delete()` uses `wp_redirect()` not `wp_safe_redirect()`

**Status:** `actions.php` line 290 uses `wp_redirect()` (no safety check). All other redirects in the file use `wp_safe_redirect()`.

**Fix:** Change to `wp_safe_redirect()`.

---

#### ISSUE 9 — `fi_admin_actions_handle_delete()` has no nonce check

**Status:** The `case 'session':`, `case 'legislator':`, `case 'vote':`, `case 'report':` delete paths in `fi_admin_actions_handle_delete()` have **no nonce verification**. A GET request with the right `?action=delete&entity_type=X&entity_id=Y` parameters will delete the record without any CSRF protection.

Note: `fi_admin_sessions_maybe_handle_delete()` (the sessions-specific delete, also in `sessions.php`) **does** have a nonce check and is correctly hooked to `admin_init`. This is a separate, parallel delete handler that is unprotected.

**Fix:** Add `check_admin_referer()` before the switch statement, or retire this function if the entity-specific delete handlers (e.g. `fi_admin_post_delete_legislator`) cover all cases.

---

#### ISSUE 10 — `function_exists('fi_cache_clear')` defensive guard in `actions.php`

**Status:** `actions.php` line 181 wraps `fi_cache_clear()` in `function_exists()`. The rules file prohibits `function_exists()` guards to hide missing functions.

**Fix:** Remove the guard. `fi_cache_clear()` exists in `core/cache.php` and is always loaded. If it's missing, that's a load-order bug that should surface, not be silently ignored.

---

### Plan Summary

| # | File | Change | Priority |
|---|---|---|---|
| 1a | `admin/autoload/votes.php` | Replace `?updated=1` redirect with `add_settings_error()` + clean redirect; remove `try/catch`; add `fi_cache_clear('votes')` | High |
| 1b | `admin/views/vote-edit.php` | Remove `$_GET['updated']` notice block (transient notice already renders via `settings_errors()` on line 89) | High |
| 2a | `admin/autoload/legislators.php` | Replace `?updated=1` redirect with `add_settings_error()` + clean redirect; add `fi_cache_clear('legislators')` | High |
| 2b | `admin/views/legislator-edit.php` | Remove `$_GET['updated']` block; add `settings_errors('fi_legislator')` in its place | High |
| 3a | `admin/autoload/sessions.php` | Move save hook to `admin_init`; replace `?updated=1` redirect with `add_settings_error()` + clean redirect; add `fi_cache_clear('sessions')`; remove JS redirect fallback | High |
| 3b | `admin/views/session-edit.php` | Remove `$_GET['updated']` block; add `settings_errors('fi_sessions')` in its place | High |
| 4a | `admin/autoload/reports.php` | Replace `?updated=1` redirect with `add_settings_error()` + clean redirect | High |
| 4b | `admin/views/report-edit.php` | Remove `$_GET['updated']` block; add `settings_errors('fi_reports')` in its place | High |
| 5 | `admin/autoload/scope.php` | Add `admin_init` (priority -10) and `admin_head` (priority 1) hooks for `Cache-Control: no-cache, no-store, must-revalidate` | High |
| 6 | `assets/js/admin.js` | Add `tinyMCE.triggerSave()` on `#fi-vote-form` submit inside `bindEvents()` | High |
| 7a | `admin/autoload/votes.php` | Remove `try/catch (Exception)` + `catch (Error)` wrapper | Medium |
| 7b | `admin/autoload/votes.php` | Change `fi_admin_votes_get_defaults()` to return `[]`; verify view template uses `['key']` not `->key` | Medium |
| 7c | `admin/autoload/sessions.php` | Change `fi_admin_sessions_get_defaults()` to return `[]`; verify view template uses `['key']` not `->key` | Medium |
| 8 | `admin/autoload/actions.php` | Change `wp_redirect()` to `wp_safe_redirect()` in the delete handler | Medium |
| 9a | `admin/autoload/actions.php` | Retire `fi_admin_actions_handle_delete()` and its `case 'delete'` call; remove `fi_admin_actions_handle_recalculate_scores()` dependency check | Medium |
| 9b | `admin/autoload/actions.php` | Add `admin_post_fi_delete_vote` and `admin_post_fi_delete_report` dedicated POST+nonce handlers (matching `fi_admin_post_delete_legislator` pattern) | Medium |
| 9c | `admin/views/vote-edit.php`, `admin/views/report-edit.php` | Audit and update delete link(s) from GET `?action=delete` to POST form with nonce (matching legislator pattern) | Medium |
| 10 | `admin/autoload/actions.php` | Remove `function_exists('fi_cache_clear')` guard | Low |

**Confirmed decisions:**
- Issue 3 (sessions admin_init hook): Move session save to `admin_init` in `actions.php` alongside votes/legislators. ✓
- Issue 7b/7c (stdClass → array): Will read view templates before touching to confirm no `->` access on defaults.
- Issue 9 (delete handler): Retire `fi_admin_actions_handle_delete()`. Build dedicated POST+nonce handlers for vote and report deletes (matching `fi_admin_post_delete_legislator` pattern). Audit view templates for links to the old GET delete path and update them.

---

260623.1300 High priority (production fixes ported):

All 4 save handlers (votes, legislators, sessions, reports) — add_settings_error() + clean redirect replacing ?updated=1
All 4 edit views — settings_errors() replacing $_GET['updated'] blocks
fi_cache_clear() added after every successful save
Session save moved to admin_init via actions.php; JS redirect fallback removed
Cache-Control: no-cache, no-store, must-revalidate hooks added to scope.php
tinyMCE.triggerSave() added to admin.js on #fi-vote-form submit
Medium priority (rules violations fixed):

try/catch removed from votes save handler
fi_admin_votes_get_defaults() and fi_admin_sessions_get_defaults() return [] instead of (object)[]
fi_admin_sessions_get_extra_meta() signature updated to array to match
Generic GET delete handler retired; dedicated admin_post_fi_delete_vote and admin_post_fi_delete_report POST+nonce handlers added
Delete buttons in vote-edit.php and report-edit.php converted to hidden POST forms
function_exists('fi_cache_clear') guard removed















--- 

## PRODUCTION VERSION REFERENCE NOTES (deprecated OOP version)

*The following notes document the production fix applied to the deprecated OOP version. Kept for reference only — this version has been superseded by the dev procedural refactor above.*

**Applies to:** Freedom Index plugin — admin save process for Votes, Legislators, Sessions  
**Diagnosed:** Jun 22, 2026 — Claude Sonnet 4.6  
**Status of THIS (deprecated) version:** All four issues patched.  
**Purpose of this document:** Drop into the new-version workspace so Sonnet can verify all fixes are correctly implemented before release.

---

## Background

The admin save screens for Votes, Legislators, and Sessions suffered from browser-caching interference: staff would make a change, save successfully, and see the old value on the redirect page. Sometimes the edit appeared to succeed but the change was lost. This document explains the root causes found in the deprecated version and the correct patterns that must be verified in the new version.

---

## Rule: Standard WordPress Pattern for Admin Save + Redirect

> **All admin save handlers MUST use the WordPress transient-based notice pattern. URL query parameters must never be used to communicate save status between a POST handler and a redirect page.**

### Correct Pattern

```php
// 1. Save to DB
$saved_id = fi_entity_save($data, $entity_id);

// 2. On success: use WP transient for the notice, redirect to CLEAN URL
add_settings_error('fi_votes', 'entity_saved', 'Saved successfully.', 'updated');
wp_safe_redirect( fi_admin_edit_vote_url($saved_id) );
exit;

// 3. On error: use WP transient for the error, return without redirect
add_settings_error('fi_votes', 'save_error', 'Error message.', 'error');
return;
```

```php
// 4. In the edit view template, display all notices (success and error):
<?php settings_errors('fi_votes'); ?>
```

### Why `?updated=1` Must NOT Be Used

The URL pattern `admin.php?page=fi-votes&action=edit&vote_id=X&updated=1` is **static across saves**. If a staff member saves twice in a row, both saves redirect to the exact same URL. The browser disk-cache may serve the first save's cached page on the second redirect, showing stale data. The `?updated=1` URL also accumulates in the browser's HTTP disk cache from previous sessions.

`add_settings_error()` stores the notice in a WordPress transient. WordPress reads it once on the next page load and auto-deletes it. The redirect goes to the plain edit URL, which is a different URL from the save destination (POST URL), ensuring a clean Post-Redirect-Get cycle.

### Anti-Pattern — What Was Found in the Deprecated Version

```php
// BAD — static URL flag, causes browser cache hits:
$redirect = fi_admin_edit_vote_url($saved_id, ['updated' => 1]);
wp_safe_redirect($redirect);
exit;

// BAD — reading save status from URL:
<?php if (!empty($_GET['updated'])): ?>
    <div class="notice notice-success">Vote saved.</div>
<?php endif; ?>
```

---

## Rule: The PWA Service Worker Must Never Cache `/wp-admin/` Requests

> **This was the actual primary root cause. If the new version includes a PWA / Service Worker, the `fetch` event handler MUST explicitly bypass all `/wp-admin/` requests. A SW with scope `/` intercepts every GET request on the site — including admin pages — and serves its own cached response, completely ignoring `Cache-Control: no-store`. Browser DevTools will show `200 OK (from service worker)` as a diagnostic signal.**

### The Problem Found in the Deprecated Version

`pwa.php` registered a SW with `scope: '/'` using a stale-while-revalidate strategy. The `fetch` handler cached every same-origin GET response, including admin edit pages. After a save + redirect, the SW served the previously cached version of the edit page (empty fields), making all HTTP-level caching fixes completely ineffective.

The `Cache-Control: no-store` header was confirmed present in the Network tab — and still the stale page was served. The status code `200 OK (from service worker)` was the diagnostic tell.

### Correct Pattern — SW fetch handler

```javascript
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    // Never cache wp-admin — returning without event.respondWith() lets the
    // browser handle the request normally, respecting Cache-Control headers.
    const reqUrl = new URL(event.request.url);
    if (reqUrl.pathname.startsWith('/wp-admin/')) return;

    // ... rest of caching strategy
});
```

### How to Verify

Open Chrome DevTools → Network tab. Load any FI admin edit page. Find the document request in the list. The `Status` column must show `200` — NOT `200 (from service worker)`. If it shows `(from service worker)`, the bypass is missing.

---

## Rule: Cache-Control `no-store` Must Be the Final Header on All FI Admin Pages

> **Defense-in-depth. This is NOT the primary fix (see Service Worker rule above), but it prevents the browser's own disk cache and bfcache from being a fallback problem if the SW is absent or misconfigured.**

### The Problem Found in the Deprecated Version

WordPress's `nocache_headers()` is called inside `admin-header.php` during page rendering. It sets `Cache-Control: no-cache, must-revalidate, max-age=0` — which **does not include `no-store`**. Without `no-store`, the browser may disk-cache admin pages and restore them from bfcache.

The fix was to re-assert `no-store` at the `admin_head` hook, which fires after `admin-header.php` but before any body output (WordPress output buffering keeps `headers_sent()` false at this point).

### Correct Pattern for the New Version

```php
// Set no-store on the POST/redirect response at admin_init:
add_action('admin_init', static function (): void {
    $page = sanitize_key((string) ($_GET['page'] ?? ''));
    if (str_starts_with($page, 'fi-')) {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}, -10);

// Re-assert after WP's nocache_headers() overwrites it during page render:
add_action('admin_head', static function (): void {
    $page = sanitize_key((string) ($_GET['page'] ?? ''));
    if (str_starts_with($page, 'fi-') && !headers_sent()) {
        header('Cache-Control: no-cache, no-store, must-revalidate');
    }
}, 1);
```

---

## Rule: TinyMCE Must Be Force-Synced Before Any Form That Uses `wp_editor()`

> **If a form contains `wp_editor()` (TinyMCE) fields AND the submit button is outside the `<form>` element (using the `form=""` attribute), `tinyMCE.triggerSave()` MUST be called in a JavaScript `submit` event handler on the form.**

### The Problem Found in the Deprecated Version

The Vote edit form (`#fi-vote-form`) has three `wp_editor()` WYSIWYG fields. The Save button is in a sticky header bar, outside `<form>`, using the HTML5 `form="fi-vote-form"` attribute. TinyMCE registers its sync hook on the form's native `submit` event, but external buttons do not reliably trigger this in all browsers. The textarea retained its stale loaded value; the save handler received and stored the old content. Staff saw their description edits silently lost.

### Correct Pattern

```javascript
// In admin.js, inside the event bindings setup:
$(document).on('submit', '#fi-vote-form', function () {
    if (typeof tinyMCE !== 'undefined') {
        tinyMCE.triggerSave();
    }
});
```

Apply this pattern to ANY admin form that has both TinyMCE fields and an external submit button. The native `submit` event on `#fi-vote-form` DOES fire when the external button is clicked (that is the correct intercept point).

---

## Rule: Clear the File Cache After Every Admin Save

> **After any entity save (vote, legislator, session), call `fi_cache_clear('entity_type')` to invalidate the file-based AJAX cache. Without this, the public-facing frontend continues to serve stale data for up to 24 hours.**

### The Problem Found in the Deprecated Version

`fi_cache()` maintains a file-based cache under `FI_DIR_CACHE` used for AJAX/frontend queries. This cache is written with a 1-day TTL. After an admin save, the cache was never cleared. Staff making changes would see updates on the admin side but find the public site still showing the old data, contributing to the perception of "saves reverting."

### Correct Pattern

```php
// After a successful save in the admin handler:
$saved_id = fi_vote_save($data, $vote_id);
if ($saved_id) {
    fi_cache_clear('votes');       // for vote saves
    fi_cache_clear('legislators'); // for legislator saves
    fi_cache_clear('sessions');    // for session saves
}
```

In the new version, this is best placed inside the `*_save()` core function itself, not in the admin handler, so it fires regardless of which code path triggers the save.

---

## Rule: Save Handlers Must Run on `admin_init`, Not Inside View Templates

> **POST save handlers must be hooked to `admin_init`, not called from within the view/template render function. This ensures headers can be sent for the redirect before any page output begins.**

### The Problem Found in the Deprecated Version

The Session save handler (`fi_admin_sessions_maybe_handle_save()`) was called from inside `sessions.php` (the view template), which is rendered after `admin-header.php` has already output HTML. By that point, `headers_sent()` returns `true`, and `wp_safe_redirect()` cannot send a `Location:` header. A JavaScript fallback redirect was required:

```php
if (headers_sent()) {
    echo '<script>window.location.href=...';</script>';
    exit;
}
```

This fallback is unreliable (depends on JavaScript being enabled, adds a visual flicker) and means the redirect response cannot carry `Cache-Control: no-store`.

### Correct Pattern for the New Version

```php
// Hook ALL save handlers to admin_init, not to view templates:
add_action('admin_init', function () {
    if (
        isset($_GET['page'], $_POST['fi_session_nonce'])
        && $_GET['page'] === 'fi-sessions'
        && $_SERVER['REQUEST_METHOD'] === 'POST'
    ) {
        fi_admin_sessions_handle_save();
        // handler calls wp_safe_redirect() + exit on success
    }
});
```

The Votes and Legislators handlers were already correctly hooked to `admin_init` in the deprecated version (via `actions.php`). Sessions was the outlier.

---

## Verification Checklist for the New Version

Run through each item with a browser that has previously visited these admin URLs (simulates cache state).

### Service Worker (check first — this was the primary root cause)

- [ ] Open DevTools → Network tab. Load any FI admin edit page. **Verify:** The document request status shows `200`, NOT `200 (from service worker)`. If it shows `(from service worker)`, the SW `/wp-admin/` bypass is missing from the new version's PWA.

### Votes

- [ ] Open a vote for editing. Change the **Roll-call Number** (plain text field). Click Save. **Verify:** Success notice appears. The Roll-call Number shows the NEW value immediately — no refresh needed.
- [ ] From the same page (post-save), clear the Roll-call Number. Click Save again. **Verify:** Success notice appears. Field is blank immediately. No stale value from a prior save.
- [ ] Open a vote. Change a **Description** field (TinyMCE). Click Save. **Verify:** The saved description shows the new text on reload. The change is not silently discarded.
- [ ] Inspect the redirect URL in browser dev tools. **Verify:** The redirect URL is the clean edit URL — no `?updated=1` or similar query parameter appended.
- [ ] After saving a vote, visit the public-facing vote page. **Verify:** The updated data is visible on the frontend immediately (no 24-hour stale cache).

### Legislators

- [ ] Open a legislator. Change the **First Name**. Click Save. **Verify:** Name shows updated value immediately on redirect.
- [ ] Save again from the redirect page. **Verify:** Still works correctly — not returning to a stale cached state.
- [ ] Inspect response headers in browser dev tools Network tab for the edit page GET request. **Verify:** `Cache-Control` includes `no-store`.

### Sessions

- [ ] Open a session for editing. Change the **Name**. Click Save. **Verify:** Success notice appears, name shows updated value immediately.
- [ ] **Verify (architecture):** The session save handler is hooked to `admin_init`, not called from within the view template. No JavaScript redirect fallback should be present or needed.

### Cache-Control Header

- [ ] Open any FI admin edit page. Open browser dev tools → Network tab. Reload the page. Find the document response. **Verify:** Response headers include `Cache-Control: no-cache, no-store, must-revalidate`.

---

## Summary of Changes Made in the Deprecated Version

| File | Change | Root cause addressed |
|------|--------|----------------------|
| `pwa.php` | Added `/wp-admin/` bypass in SW `fetch` handler; bumped `FI_PWA_VERSION` | **Primary** — SW was caching admin pages |
| `admin/autoload/votes.php` | Switched to `add_settings_error()` + clean redirect; added `fi_cache_clear('votes')` | URL cleanliness + file cache |
| `admin/autoload/legislators.php` | Same as votes | URL cleanliness + file cache |
| `admin/autoload/sessions.php` | Same as votes | URL cleanliness + file cache |
| `admin/views/vote-edit.php` | Removed `?updated` notice block; `settings_errors('fi_votes')` already present | URL cleanliness |
| `admin/views/legislator-edit.php` | Replaced `?updated` notice block with `settings_errors('fi_legislator')` | URL cleanliness |
| `admin/views/session-edit.php` | Replaced `?updated` notice block with `settings_errors('fi_sessions')` | URL cleanliness |
| `admin/autoload/scope.php` | Added `admin_head` hook to re-assert `no-store` after WP overwrites it | Defense-in-depth |
| `assets/js/admin.js` | Added `tinyMCE.triggerSave()` on `#fi-vote-form` submit | TinyMCE WYSIWYG sync |

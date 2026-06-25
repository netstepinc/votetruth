# Legislator Votes — Full Payload Sprint (Sprint B)

---

## Goal

Replace the current multi-query, per-session approach with a single cached payload
covering the legislator's complete vote history. Page load hits the cache once;
session/tag/report switching uses the cached data client-side — zero AJAX round-trips.

---

## Interrogation Log (decisions as we go)

| # | Question | Decision |
|---|---|---|
| 1 | Option A (AJAX) vs Option B (full payload + JS)? | **Option B** — full payload, fewer queries, instant view switching |
| 2 | Cache storage: `fi_cache()` or transients? | **`fi_cache()` only** — key `legislator/{id}-votes`; invalidate via `fi_cache($key, 'DUMP')`. Transient references in earlier draft were wrong (Sonette session drift). |
| 3 | Payload scope: current chamber vs both chambers vs session assignments? | **Per session assignment (gov + chamber)** — legislators serve one chamber at a time, tied to their session row in `fi_legislator_sessions`. A career may span multiple gov/chamber combos (e.g. TX State Rep → TX State Sen → US Rep → US Sen). Vote display scope when switching sessions follows that session's `chamber` + `gov`, not a single global chamber filter. **One cache file per legislator** (`legislator/{id}-votes`), not per chamber. |
| 4 | Child sessions in vote queries? | **Yes — mirror production exactly.** Public sees parent (2-year) sessions only. Legislators and reports are assigned to parent sessions. LegiScan votes may live on child sessions (1-year, special). When displaying a parent session's votes: `session_id IN (parent + published children)` AND `vote.chamber = session_assignment.chamber`. |
| 5 | Tag scores scope? | **Issue Scores** — all votes we have for that legislator (`votes_cast` / rollcall set). NOT filtered by current session chamber (current controller is likely wrong). NOT labeled "career" — we don't have full legislative history, only what's in our system. Mirror `_PRODUCTION/api/actions/legislator.php` L437–495: tag scores computed from all `$votes_cast_ids` across all gov/chamber assignments. |
| 6 | `fi_cache` write disabled (`TEMP DISABLE`)? | **Intentional in dev** — cache writes disabled so dev always hits live queries. Production cache writes work and are used extensively. Sprint must: respect `FI_DEV` / dev bypass (read miss → query, skip write in dev); re-enable path is already there for production — do not uncomment globally without user approval. |
| 7 | Rollcall save hook missing? | **Fix in this sprint.** Add `do_action('fi_rollcall_saved', ...)` to `fi_rollcall_save()`. Wire legislator-level cache dump in handler. Policy: fix issues as we find them — don't defer refactor gaps. |
| 8 | Retire AJAX handler? | **Delete outright.** `ajax-api-vote-history.php` removed once JS path works. No x-prefix, no fallback. Pass/fail — fix forward if broken. |
| 9 | UX / navigation scope? | **In sprint — mobile-first nav rebuild.** Session rail (CSS scroll-snap, not Swiper first) + report chips + issue hero strip. Scale up to LG. Compare against live sidebar; push for best mobile UX. Mockup (`fi-legislator-1.html`) is primary layout reference. |
| 13 | Default vote list view? | **Confirmed:** current session default; hero issue click → tag filter; All Votes in nav with search + Load More; deep links override. |
| 14 | Payload shape — raw vs JS-ready? | **Pre-process before cache.** Cache stores JS-ready structure (`vote_groups` + `votes` lookup + pre-scored sessions/reports/tags). Page request after cache hit = read file + embed JSON + minimal SEO HTML — no transform pass on render. Reference: production `vote_groups` distill in `legislator-api.php`. |
| 15 | Session rail UX? | **CSS scroll-snap first.** **"All<br>Sessions"** panel at **start** of session rail (easy to find). Newest sessions after; 2-line labels; ~2.3 panel peek on mobile. Chevrons for accessibility. |
| 16 | Vote card rendering? | **Hybrid (SEO + perf).** Server-render HTML for **initial URL view** (default session or deep link). Client JS rebuilds cards from cached `votes` + `vote_groups` on filter change — NOT full show/hide of all cards in DOM (Massie ~1.2MB JSON → too heavy if every card pre-rendered). **HTMX:** optional for Load More / no-JS fallback only — not primary filter path. |
| 17 | SEO URLs + JSON-LD? | **In sprint.** JSON-LD today: `Person` + Issue Score `ItemList` (and related). **Canonical always** `/legislator/{id}/` — one URL per legislator for SEO. **Client filter interactions** update browser URL (`pushState`), **share URL**, and **PDF URL** to match active view. Variant URLs are for sharing/navigation only, not separate indexed pages. |
| 18 | Rollcalls / casts in payload? | **Legislator's casts only** — all votes in sessions they served (per session assignment + child sessions). Each vote includes their cast: **Y, N, or X** (X = anything else: abstain, not present, etc.). No full chamber rollcall matrix. |
| 19 | Cache expiry? | **30 days default** (`fi_cache($key, $data, 30)`). Invalidation hooks + admin one-click cache clear cover edits. Staff dumps cache when publishing session/report/votes. 7 days also acceptable; 30 preferred. |
| 20 | Tag / vote-tag invalidation? | **Manual for now** — no auto-bust on vote-tag save. Admin clears FI cache at publish time. Revisit auto-invalidate if stale issue scores become a problem. |
| 21 | Test legislator? | **1414 (Massie) only** for sprint verification. Other profiles after this is dialed in. |
| 22 | Score display? | **Freedom Score** = static `fi_legislators.score` (legislator payload). **Session / report / issue scores** = pre-calculated in compile cache (`vote_groups`); JS displays them on filter change — no client recalc. **Search active** (All Votes text filter): hide context score badge — don't score search results. |
| 23 | Report intro content? | **Yes** — show full `payload.content` above vote cards when report selected (match live production). Included in pre-built report data in compile cache. |
| 24 | Print modal scope? | **In sprint** — wire print modal + header PDF buttons to active session/report from client state (`data-pdf-base` sync on filter change). No vote-history AJAX dependency. |
| 26 | Hero issue score clicks? | **In sprint — filter vote list.** Click hero issue tile → scroll to vote section, `view=tag`, update URL/share/PDF. Tile/link title: **"View {issue} Votes"**. |
| 28 | Vote detail modal? | **A — in-page modal** for Read More. View Bill link **inside modal** (+ vote detail page), not on card footer. |
| 29 | Search? | **All Votes view only (for now).** Client-side filter on pre-built `search_text` in cache. Score badge hides while search active. |

### Reconciliation rule
When procedural code disagrees with `_PRODUCTION/core/*` or `_PRODUCTION/api/*`, **production wins**. The refactor may have introduced bugs — fix toward production behavior, not toward the broken procedural code.

**Fix-as-we-go policy:** If the sprint touches code and finds a broken hook, missing invalidation, or refactor gap — fix it in the same sprint. Don't defer.

### Session/chamber model (authoritative)

```
2025-2026  US Senator from TX       → session.chamber = senate,  session.gov = US
2023-2024  US Representative from TX → session.chamber = house,   session.gov = US
2021-2022  Texas State Senator       → session.chamber = senate,  session.gov = TX
2019-2022  Texas State Representative → session.chamber = house,  session.gov = TX
```

- Source of truth for assignment: `fi_legislator_sessions_get_history()` rows (`chamber`, `gov`, `session_id`).
- Production API mirrors this: for each session, query votes where `v.chamber = session.chamber` and `v.session_id IN (parent + child session IDs)`.
- **Implication:** `fi_legislator_votes_query($legislator_id)` takes **no chamber arg** — returns all career votes where legislator has rollcalls. Client-side session filter uses that session's assigned chamber (and child session IDs).

### Parent vs child sessions (authoritative)

```
PUBLIC VIEW          fi_sessions                    DATA LAYER
───────────          ───────────                    ──────────
2023-2024 Congress   parent (2-year, parent_id=NULL)  ← legislator assigned here
                     ├── 2023 Regular (child)           ← LegiScan votes may live here
                     └── 2024 Special (child)           ← LegiScan votes may live here
```

- Public UI, legislator assignments (`fi_legislator_sessions`), and reports → **parent sessions only**.
- LegiScan vote data → may be linked to **child sessions** (1-year, special).
- Vote query for a parent session: `v.session_id IN (parent_id, ...child_ids)` + `v.chamber = ls.chamber`.
- Payload should include a `session_vote_ids` index: `{ parent_session_id => [vote_id, ...] }` precomputed at cache-build time so JS can filter instantly without re-deriving child IDs.

---
## Design considerations.
The production site shows sessions and reports on the left and votes on the right.
See screen shot: assets/legislator-votes.png

This works, but the goal is to make the user experience as efficient as possoble.
If there's a better way to display sessions, reports and votes, we should consider that.

Claude Design suggested this type of layout, which is similar to our previous Freedom Index version layout.
Screen shote: assets/legislator-votes-mockup1.png
Mockup file: assets/plugins/freedom-index/_WORKBENCH/_mockups/fi-legislator-1.html

We must prioritize compiling the accurate data first, then work on the UX until it's the best we can do.

### Rebuild philosophy (not incremental ship)
- Current production site **works** but is inefficient; users report UX confusion.
- `_PRODUCTION/*` is **reference for intentions** — same results, better implementation.
- Nothing ships until the rewrite is complete end-to-end.
- **Step 1 (this sprint):** Data layer — single cached payload, correct filtering logic, client-side switching.
- **Step 2 (follow-up):** UX exploration — try 2–3 display methods (incl. mockup) until mobile + clarity win.

| 13 | Default vote list view? | **Confirmed:** current session default; hero issue click → tag filter; All Votes in nav with search + Load More (~25); deep links override. Staff can push back later. |

### Default view / issue scores (Q13 — confirmed)
- **Hero Issue Scores** = "why" entry point; click → tag filter on vote list (`/issue/{tag_id}/` or JS `view=tag`).
- **Default vote list → current session** on base URL.
- **"All Votes"** one click in nav — search + Load More when selected.
- **Deep links unchanged** — URL with session/report/issue overrides default.

### Step 2 UX (deferred variants only)
- A/B test alternate layouts if session-rail baseline underperforms on user testing.
- Swiper only if CSS scroll-snap can't handle URL-sync / center-active-session.

### SEO strategy (legislator pages — in sprint)
- **Canonical:** always `/legislator/{id}/` — one indexed URL per legislator (production pattern).
- **Variant URLs** (`/session/`, `/report/`, `/issue/`) — used for sharing, bookmarking, PDF links; **not** separate canonicals.
- **Client nav:** every filter interaction → `history.pushState` + sync **share URL** + **PDF URL** to active view.
- **Server HTML:** render view matching request URL on first load (crawlers + share previews).
- **JSON-LD (in sprint):** output in `wp_head` via extend `fi_seo_tags()` or legislator template:
  - `@type: Person` — name, party, chamber, image, url, Freedom Score
  - `@type: ItemList` — Issue Scores (tag name, score %, vote count)
  - Optional: sample vote/issue linkage for generative citation
- **Individual vote pages** (`/vote/{id}/`) remain authority URLs for specific votes.
- **Session rail:** CSS scroll-snap; **"All Sessions"** first cell; newest sessions follow; 2-line labels; ~2.3 peek; chevrons.
- **Report chips:** horizontal row below rail for active session.
- **Issue strip:** hero performance cells → tap filters vote list.
- **Scales to LG:** same components, more visible panels.

### Vote card redesign (in sprint — simplify for clarity)
**Remove from card footer:** vote date column, Read More link, View Bill link, multi-column footer grid.

**Card body:**
- Title + chamber (top)
- Short description text only (administrative details out of short text over time; date may appear in text area top/bottom-left for now)
- **Read More badge** at end of short text → opens in-page modal (95% won't read full constitutional write-up without it)
- **Cost line** under short text: `fw-bold text-success` "+$X Annual benefit per household." OR `fw-bold text-danger` "-$X Annual cost per household."
- **Constitutional + Vote Cast** — value-first labels for visual alignment:
  - `YES · Constitutional` / `NO · Vote Cast` (values line up at start of each line)
  - Layout: **1 row × 2 cols on desktop**, **stacked on mobile**
  - Colors: constitutional green/red/gray; cast green/red/gray; compact match indicator
  - Alternatives (Shot 3/4 blocks) deferred unless simplified version fails user testing
- **View Bill** — modal + `/vote/{id}/` page only, not card footer.

**Modal:** full constitutional explanation, View Bill link, vote detail content from cached vote data.

---

## Context for AI Agent Starting This Sprint

### Rules & conventions
- Read `.AI/rules.md` and `.AI/Ai.md` before touching anything.
- Procedural PHP only. Arrays only (`ARRAY_A`). No classes, no objects, no `->` access.
- Naming: `fi_{noun}_{verb}()`. New functions go in `core/legislator-votes.php`.
- Run `php -l` on every modified file before reporting complete.
- **PLAN first, get explicit approval, then EXECUTE.** Do not write code before the plan is approved.
- Ask 20+ clarifying questions for anything ambiguous. The user prefers being interrogated over incorrect assumptions.

### Reference files (read these before planning)
| File | Purpose |
|---|---|
| **`_PRODUCTION/core/*`** | **Authoritative production logic** — reconcile procedural refactor against this when behavior is unclear. OOP→procedural refactor may have broken things. |
| `_PRODUCTION/api/actions/legislator.php` | Source of truth for query strategy — this is exactly how the production payload was built using Medoo; mirror the logic using `$wpdb` |
| `_PRODUCTION/payload.legislator-api.json` | Example complete payload — inspect the shape of `votes`, `rollcalls`, `tags`, `tag_scores` |
| **`_WORKBENCH/payload.legislator-api.json`** | **Local copy of example payload (Massie 1414)** — target shape for cached compile output |
| `_PRODUCTION/public/templates/legislator-api.php` | How the production frontend used the pre-loaded payload for instant JS switching |
| `core/legislator-votes.php` | Existing functions to build on — contains `fi_session_votes_cache_get()`, `fi_session_votes_cache_build()`, and the two invalidation hooks |
| `public/templates/legislator.php` | Current controller — the exact block being replaced is L55–122 |
| `public/templates/legislator-vote-history.php` | Template that renders vote list and handles AJAX session switching |
| `public/autoload/ajax-api-vote-history.php` | **Delete outright** once client-side filtering verified — no fallback |


### Cache Strategy
NEVER cache to transients.
Use the fi_cache() function

Cache key strategy for large payloads will be '{data table directory}/{legislator ID}-votes.
OR $cacheKey = 'legislator/{id}-votes';

function fi_cache($key,$data='',$expires=1){ //default time = 1 day.

Get cache: $data = fi_cache($key) = 1 DAY default.
Save = fi_cache($key,$data);

---

## Current State (as of Sprint A completion)

### Controller: `public/templates/legislator.php`

**Steps 1–4 (lines 1–122) — the block being replaced:**

```
L19–53:  Fetch legislator, sessions, determine current session/chamber/gov
L55–71:  fi_session_votes_cache_get($current_session_id) → $session_votes, $session_rollcalls
         Single pass to filter by chamber, attach cast, build $display_votes[]
L73–122: Career tag score block — one JOIN across ALL sessions for this legislator+chamber
         Builds $all_tags[] (scored, sorted by vote_count) and $tag_scores[] (top 8)
```

**Steps 5–6 (lines 124–221) — NOT being changed:**
```
L124–132: $contact built from decoded $legislator['meta']
L134–136: $session_reports via fi_reports_get_by_session_ids()
L138–148: $current_report_id, $current_url, user data for modals
L150–221: SEO tags + fi_get_template() calls for header, vote-history, modals
```

### What each fi_get_template() receives today

**`legislator-header`** receives: `legislator, sessions, current_session, tag_scores, base_url, legislator_id, gov, contact`
- `$tag_scores` = top 8 from `$all_tags` (career-wide, sorted by vote_count)

**`legislator-vote-history`** receives: `legislator, sessions, current_session, display_votes, all_tags, session_reports, current_report_id, current_tag_id, base_url, gov, chamber, legislator_id`
- `$display_votes` = current session votes for this chamber, with cast attached
- `$all_tags` = career-wide scored tags (used for tag filter in vote history)

**`legislator-modals`** receives: everything for contact/share/lists/personalize/print modals — **not touched in this sprint**

---

## Problem with Current Approach

| Query | Where | Cost |
|---|---|---|
| `fi_legislator_get()` | legislator + sessions | 1 |
| `fi_session_votes_cache_get()` | current session only | 1 file cache read |
| Career tag JOIN (L77–122) | all sessions, raw SQL in controller | 1 |
| `fi_reports_get_by_session_ids()` | all session reports | 1 → **removed from legislator page** (reports in compile cache) |
| AJAX `fi_public_ajax_vote_history_*` | every session/report/tag switch | **1 per click** → **removed** |

The career tag JOIN (L77–122) is raw SQL inside the template controller — this belongs in `core/`.
Switching sessions always triggers an AJAX round-trip even though the data is already in the DB.

---

## Proposed Architecture

### New function 1: `fi_legislator_votes_query(int $legislator_id): array`

**Location:** `core/legislator-votes.php` (add after existing functions)

**Philosophy:** One compile pass — pre-process everything the vote-history UI needs. Mirror `_PRODUCTION/api/actions/legislator.php` + `_WORKBENCH/payload.legislator-api.json`. JS only filters pre-built indexes.

**Returns (cached — JS-ready, pre-processed):**
```php
[
    'votes'        => [],  // keyed vote_id → full card data + cast + matched/counted + display fields
    'votes_cast'   => [],  // vote_id => 'Y'|'N'|'X' (X = non-Y/N: abstain, absent, etc.)
    'vote_groups'  => [    // pre-built for JS — mirror legislator-api.php distill
        'all'      => ['votes' => [...ids], 'title', 'score', ...],
        'tags'     => [ tag_id => ['votes' => [...], 'title', 'score', ...], ...],
        'sessions' => [ session_id => ['votes' => [...], 'reports' => [...], 'score', ...], ...],
    ],
    'sessions_meta' => [], // session rail: id, name, score, gov, chamber_label (newest first)
]
```

**Compile pass also builds `vote_groups`** during query (not on page render). Page load: `fi_cache()` → embed `vote_groups` + `votes` as JSON.

**Compile pass steps (mirror production):**

1. **votes_cast** — all rollcalls for this legislator → `$votes_cast_ids` (all cast types: Y, N, A, X, etc.)
2. **votes** — hydrate vote rows for legislator rollcalls; decode meta; cast normalized **Y / N / X**; matched/counted (score Y/N only; X shown not penalized)
3. **Per parent session** (from `fi_legislator_sessions_get_history()`):
   - Resolve child session IDs (published)
   - Build `sessions[parent_id]['votes']` = vote IDs where `v.session_id IN (parent+children)` AND `v.chamber = ls.chamber`
   - **Fetch reports once** for session; decode payload; extract `votes[]` from `votes_h` / `votes_h_order`; score report per legislator; attach full report row (title, content, format, score, score_data, votes[])
"4. **vote_tags** — all tags for `$votes_cast_ids`; score each tag against full votes map; include `votes[]` ID list per tag
5. **No duplicate report queries** — controller/template must not call `fi_reports_get*` for vote-history; all report data comes from cached compile output

**Cache invalidation (reports):** On report save/update, dump `legislator/{id}-votes` for all legislators assigned to that report's session. Verify/add hook if missing (fix-as-we-go).

**Tag / Issue Scores:** Computed in compile pass from all `$votes_cast_ids` — no chamber filter. Label: Issue Scores.

### New function 2: `fi_legislator_votes_cache_get(int $legislator_id): array`

**Location:** `core/legislator-votes.php` (immediately after `fi_legislator_votes_query()`)

```php
$cache_key = 'legislator/' . $legislator_id . '-votes';
// Read:  $data = fi_cache($cache_key);
// Write: fi_cache($cache_key, $data, 30);  // 30-day expiry; admin cache clear + hooks handle edits
// Dev:    FI_DEV or disabled file_put_contents → always query fresh, skip write
// Invalidate: fi_cache($cache_key, 'DUMP');
```

### Cache invalidation

Extend the two existing hooks in `fi_legislator_votes_init()` (L47–51):

In `fi_legislator_votes_on_vote_saved()` (L60–64):
- After invalidating session cache, `fi_cache('legislator/{legislator_id}-votes', 'DUMP')` for every legislator in the vote's rollcalls.

In `fi_legislator_votes_on_rollcall_saved()` (L73–83):
- After invalidating session cache, dump `legislator/{legislator_id}-votes` for `$data['legislator_id']`.

**Also in sprint:** Add missing `do_action('fi_rollcall_saved', $rollcall_id, $data)` to `fi_rollcall_save()` in `core/rollcalls.php`. Verify `fi_vote_saved` hook fires similarly — fix if missing.

---

## Controller Changes: `public/templates/legislator.php`

**Replace L55–122** with legislator-level cache load + pass full payload to vote-history template as JSON for client-side filtering. Server still renders initial `$display_votes` for SEO (filter current session using session assignment chamber + child session IDs).

Template changes **required** — replace accordion sidebar with **mobile-first nav** (session rail + report chips). Reference: `_mockups/fi-legislator-1.html` + `_PRODUCTION/public/templates/legislator-api.php` JS patterns.

---

## Option B — confirmed for this sprint

Pass full votes payload as JSON on page load; JS filters by session/report/tag with zero AJAX. Matches `_PRODUCTION/public/templates/legislator-api.php`. **Delete** `ajax-api-vote-history.php` — no fallback, fix forward.

---

## What Gets Retired

| Thing | Action |
|---|---|
| `ajax-api-vote-history.php` | **Delete** — replaced by client-side JS filtering |
| Career tag JOIN block in `legislator.php` L77–122 | **Delete** — logic moved into `fi_legislator_votes_query()` |
| `fi_session_votes_cache_get()` in controller | **Remove the call** — `fi_legislator_votes_cache_get()` replaces it for the legislator page. The function itself stays for report pages and other contexts. |

**Do NOT touch:** `fi_session_votes_cache_get()`, `fi_session_votes_cache_build()`, `fi_session_votes_cache_invalidate()` — other code depends on them.

---

## Verification Checklist

**Code-verified (no runtime needed):**
- [x] `php -l` clean — `core/legislator-votes.php`, `legislator.php`, `legislator-vote-history.php`, `votes-stats.php`
- [x] JSON-LD outputs Person + Issue Score ItemList — `legislator.php` L167–203
- [x] Canonical stays base URL; og:url matches active variant — `fi_seo_tags()` call confirmed; `$base_url` ordering bug fixed
- [x] Filter interactions update URL, share URL, PDF URL — JS `pushStateFromSelection()` + `updateOgUrl()` + `updatePrintModalReportBase()` all wired
- [x] `fi_legislator_votes_query()` naming — no conflict (only definition + one internal call)
- [x] Cache invalidates on vote/rollcall/report save — `do_action('fi_vote_saved')` in `votes-save.php`, `do_action('fi_rollcall_saved')` in `rollcalls.php`, `do_action('fi_report_saved')` in `reports.php` — all wired to handlers in `legislator-votes.php`
- [x] `FI_DEV` bypasses session transient cache — `fi_session_votes_cache_get()` checks `FI_DEV`; legislator file cache bypassed via TEMP DISABLE in `cache.php`
- [x] Deep links set initial filter from URL — controller resolves `$url_session_id / $url_report_id / $url_tag_id` → `$default_view` → `$initial_group`
- [x] Session rail renders — PHP loop in `legislator-vote-history.php` + JS `highlightNav()` + `renderReportChips()`
- [x] Load More (25 per click) — JS `PAGE_SIZE = 25`, `fi-vote-load-more` click handler
- [x] Report intro content above vote cards — `$initial_content` from `$initial_group['content']`; JS `updateHeader()` sets `$content`
- [x] Print/PDF modal sync — `updatePrintModalReportBase()` fires on every nav change

**UX note — Q15 "All Sessions first in rail":** Implemented as a separate `#fi-view-all-votes` button above the rail rather than as the first rail chip. Functionally equivalent. Accept or adjust in Step 2 UX pass.

**Requires runtime testing (you):**
- [ ] `legislator/1414/` loads without error (Massie — many sessions, many votes)
- [ ] Hero issue scores (top 8 tags) display in header
- [ ] Vote history section renders current session votes on base URL
- [ ] Tag filter switches vote list correctly
- [ ] Report chips appear and filter correctly when a session is active
- [ ] Search filters on All Votes view; score badge hides while searching
- [ ] Load More adds 25 cards
- [ ] Back/forward browser buttons restore state
- [ ] Print/PDF buttons reflect active session/report

---

## Questions — interrogation queue

### Answered
1. ~~Option A vs B?~~ → **B**
2. ~~Cache: fi_cache or transients?~~ → **fi_cache()**
3. ~~Chamber scope?~~ → **Session assignment model; one cache per legislator; filter by session's chamber at display time**
4. ~~Child sessions?~~ → **Yes — parent + published children; legislators/reports on parent; LegiScan votes on children**
5. ~~Tag scores scope?~~ → **Issue Scores — all votes in system for legislator; no chamber filter; production wins**
6. ~~fi_cache write disabled?~~ → **Intentional in dev; production writes work; dev always queries fresh**
7. ~~Rollcall hook missing?~~ → **Fix in sprint — add do_action + wire cache invalidation**
8. ~~Retire AJAX handler?~~ → **Delete outright — pass/fail, fix forward**
9. ~~UX / navigation scope?~~ → **Mobile-first nav in sprint; session rail + report chips; mockup reference**
13. ~~Default view?~~ → **Current session default; hero issue → tag; All Votes + Load More**
14. ~~Payload shape?~~ → **Pre-process `vote_groups` + votes into cache; light page render**
15. ~~Session rail?~~ → **CSS scroll-snap; All Sessions first; 2.3 peek; chevrons**
16. ~~Vote card rendering?~~ → **Hybrid: server HTML initial view; JS rebuild on filter**
17. ~~SEO + JSON-LD?~~ → **In sprint; canonical base URL; pushState + share/PDF URL sync**
18. ~~Rollcalls / casts?~~ → **All session votes they served; cast Y/N/X per vote; no full matrix**
19. ~~Cache expiry?~~ → **30 days; admin cache clear + publish workflow**
20. ~~Tag invalidation?~~ → **Manual — admin cache clear at publish**
21. ~~Test legislator?~~ → **1414 (Massie) only this sprint**
22. ~~Score display?~~ → **Freedom static; session/report/issue pre-calc; hide on search**
23. ~~Report intro?~~ → **Yes — full payload.content above vote cards**
24. ~~Print modal?~~ → **In sprint — sync to active session/report from client state**
25. ~~Share URLs?~~ → **Yes — Q17: pushState + share URL sync to active view**
27. ~~og:url vs canonical?~~ → **Yes — Q17: canonical base; og:url full variant**

26. ~~Hero issue clicks?~~ → **Filter vote list; title "View {issue} Votes"**
28. ~~Vote detail modal?~~ → **In-page modal; View Bill in modal only**
29. ~~Search?~~ → **All Votes only; search_text filter; hide score while searching**

30. ~~Load More?~~ → **25 per click**
31. ~~Share modal?~~ → **Auto-update URL on filter change**
32. ~~Other modals?~~ → **Untouched except share + print PDF URL pushState sync**

### Interrogation status
**COMPLETE — EXECUTE approved.**


Data layer (core/legislator-votes.php)
fi_legislator_votes_query() — single compile pass: votes, casts, sessions (with child sessions + reports), issue tags/scores, pre-built vote_groups
fi_legislator_votes_cache_get() — fi_cache('legislator/{id}-votes', …, 30) with invalidation on vote/rollcall/report saves
fi_legislator_votes_prepare_card_data() — card prep moved from AJAX into core
Hooks fixed
fi_vote_saved, fi_rollcall_saved, fi_report_saved now fire from save functions
Cache invalidation dumps legislator vote cache + existing session transients
Controller + templates
legislator.php — one cache load; tag scores from compile; JSON-LD (Person + Issue ItemList); canonical stays base URL
legislator-vote-history.php — session rail, report chips, client-side filter/rebuild (no AJAX), Load More (25), search on All Votes
vote-card.php — simplified layout (Read More badge, cost line, constitutional/cast indicators)
legislator-header.php — issue tiles filter votes + scroll to list
Print/PDF/share — pushState updates URL, og:url, PDF button hrefs, and print modal data-pdf-base (+ fi-print-report-base-changed)
Removed
ajax-api-vote-history.php
Verified
php -l clean on all touched files
fi_legislator_votes_query(1414) → 250 votes, 7 sessions, 16 tags
http://localhost/votetruth/legislator/1414/ → HTTP 200, vote cards + voteGroups JSON + JSON-LD present
Browse /legislator/1414/ and exercise session rail, report chips, issue tiles, search, Load More, back/forward, and print/PDF buttons to confirm UX end-to-end.

---

## Composer 2.5 Code Review — Jun 21, 2026

Sprint executed by Cursor Composer 2.5 (accidentally). Code is functional and architecturally sound, but contains style violations and unnecessary complexity. Findings below.

### What's Correct
- `fi_legislator_votes_query()` compile pass is clean: votes → cast normalization → session loop + child session IDs → report scoring → tag scoring. Logic mirrors the production approach.
- `fi_legislator_votes_cache_get()` cache key and 30-day expiry correct. `fi_cache($key,'',30)` read is valid — passing `$expires=30` on read is required to match write expiry (fi_cache checks `time() - expires < filemtime()`; default 1-day read would expire 30-day cache early). Not a bug, just subtle.
- Cache invalidation hooks (vote, rollcall, report) correctly added. `fi_legislator_votes_on_report_saved` → dumps all legislators in session. Clean.
- `fi_legislator_votes_calc_score()` scoring map pattern (precomputed matched/counted) is efficient.
- JS client-side filter: state machine, pushState, report chips, Load More, search, popstate — all correct.
- Session rail + report chips HTML structure is reasonable.

### Issues: Dead Code Cluster (LEFT BEHIND instead of deleted)

Composer 2.5 added two deprecated functions with `x_` prefixes but left their entire support ecosystem in the file:

| Function | Problem |
|---|---|
| `x_fi_legislator_votes_get()` L348 | Deprecated (x_ prefix), 0 active callers |
| `x_fi_legislator_tags_get()` L709 | Deprecated (x_ prefix), 0 active callers |
| `fi_legislator_votes_build_report_data()` L427 | Only called by `x_fi_legislator_votes_get()` |
| `fi_legislator_votes_find_vote_in_cache()` L603 | Only called by `x_` cluster |
| `fi_legislator_votes_get_legislator_rollcall()` L621 | Only called by `x_` cluster |
| `fi_legislator_votes_calculate_score_from_votes()` L632 | Only called by `fi_legislator_votes_build_report_data()` |
| `fi_legislator_votes_normalize_rollcall()` L292 | Never called anywhere |
| `fi_legislator_votes_tags_request_cache()` L27 | Only used by `x_fi_legislator_tags_get()` |

**Rule: delete before adding.** These ~200 lines should be removed.

### Issues: API Wrapper Bloat

Composer added 6 thin wrapper functions with `function_exists` guards (L176–284):
- `fi_legislator_votes_get_votes_by_session()`
- `fi_legislator_votes_get_votes_by_tag_public()`
- `fi_legislator_votes_get_rollcalls_by_vote_ids()`
- `fi_legislator_votes_get_rollcalls()`
- `fi_legislator_votes_get_rollcall()`
- `fi_legislator_votes_get_tags_by_vote_ids()`

These are single-function wrappers that add `function_exists` guards for a purpose-built site where the functions always exist. Not distributed code — never needs this pattern. The new compile pass in `fi_legislator_votes_query()` does NOT use them (it calls `$wpdb` directly or `fi_legislator_votes_get_tags_by_vote_ids()` directly). They're only used by `fi_session_votes_cache_build()` (pre-existing function that could have called core functions directly) and the dead `x_` cluster.

**Rule: NEVER wrap known-good core plugin functions in purpose-built code.**

### Issues: Orphaned Function

`fi_legislator_votes_get_by_tag()` (L792–907, ~115 lines) — old AJAX-style pattern (per-request DB queries + rollcall fetch). Not called anywhere in the new flow. Only reference is `_PRODUCTION/public/templates/legislator.php` which is a reference file only. Should be deprecated with `x_` prefix or deleted.

### Issues: Render-Time Transform (violates "no transform pass on render")

`legislator-vote-history.php` L24–48 calls `fi_legislator_votes_prepare_card_data()` on **every vote in `$votes_map`** at page render time — 250 calls for Massie. This builds the `$votes_json` JS payload.

The compile pass stores `cast`, `matched`, `counted`, `chamber_label`, `date_formatted`, `search_text` but NOT `text` (description), `text_more`, `url_vote`, `cost_badge`, `cost_badge_class`, `is_match`, `is_no_vote`. So the template must call `fi_vote_get_description()`, `fi_vote_format()`, `fi_vote_cost_compact_badge()`, `fi_url_vote()`, `fi_chamber_label()` on every vote every page load.

Sprint spec said: "Cache stores JS-ready structure… page request after cache hit = read file + embed JSON — no transform pass on render."

**Fix: precompute these fields inside `fi_legislator_votes_query()` compile pass; strip them from `fi_legislator_votes_prepare_card_data()` scope so the template can build `$votes_json` from `$votes_map` directly.**

### Issues: Logic Bug — `fi_vote_cost_compact_badge()`

`votes-stats.php` L125–138. The class mapping is inverted:

```php
return ['badge' => ..., 'class' => ($indicator === '+') ? 'bad' : 'good'];
```

`+` → `'bad'` → `bg-danger-subtle text-danger` (renders RED).

Sprint spec: `"+$X Annual benefit per household."` → `fw-bold text-success` (GREEN). `fi_vote_format_cost()` in the same file correctly uses `text-success` for `+`. The compact badge inverts this, causing a `+$500/year benefit` to display in red danger styling.

**Fix:** `($indicator === '+') ? 'good' : 'bad'`

### Issues: Minor Style

- `fi_legislator_votes_format_vote()` (L311–338) exists alongside the new compile-pass vote building in `fi_legislator_votes_query()`. Used only by `fi_session_votes_cache_build()` (pre-existing, untouched function). Not harmful but creates two parallel vote-formatting code paths.
- `fi_session_votes_cache_get()` (L476–498) still uses `get_transient` / `set_transient` directly — violates "NEVER cache to transients" rule. Pre-existing, correctly left untouched per sprint scope. Flag for future cleanup.

### Verdict

Core architecture is right (compile pass, cache, client-side JS). The problems are not in the solution design but in how it was executed: Composer 2.5 added code where it should have deleted, wrapped things that didn't need wrapping, and missed the pre-compute requirement for the JS payload. The cost badge logic bug is the only functional defect. The rest is cleanup.

**Priority fixes:**
1. `fi_vote_cost_compact_badge()` class inversion (bug)
2. Precompute `text`, `text_more`, `url_vote`, `cost_badge`, `is_match`, `is_no_vote` in compile pass — remove `$votes_json` transform loop from template
3. Delete dead code cluster (~200 lines) + `x_` prefix orphans
4. Remove API wrapper functions (or at minimum remove `function_exists` guards)
5. Deprecate `fi_legislator_votes_get_by_tag()` with `x_` prefix


After Sonnet QC'd work done by Cursor Composer 2.5 it fixed a few things then did this:

core/legislator-votes.php — Step 7 added to compile pass

After all vote fields are built, iterates $votes and calls fi_get_template_html('vote-card', ...) once per vote, storing the result in $vote['card_html']. This is a one-time cost per 30-day cache window. vote_format is passed as ['is_match' => ..., 'is_no_vote' => ...] so the template skips calling fi_vote_format() again.

legislator.php — $display_votes simplified

Was: array of card arg arrays → fi_get_template_html called once per card at request time
Now: array of pre-rendered HTML strings — no function calls, just array reads.

legislator-vote-history.php — PHP and JS both simplified

PHP: initial render loops over HTML strings directly, no fi_get_template_html call in the template.

JS payload went from 18 fields to 7 (id, title, text_more, bill_url, date_voted, search_text, card_html). Removed fields only existed for buildVoteCardHtml(). Modal still reads vote.title, vote.text_more, vote.bill_url from votesData as before.

Removed JS: voteCardVariants(), voteCardStatusBs(), voteCostBs(), buildVoteCardHtml() — ~60 lines of string-concat card HTML that had to mirror vote-card.php.

renderCards() now: html += (vote && vote.card_html) ? vote.card_html : '' — one line replaces 40.

vote-card.php — data-vote-body / data-vote-title stripped

These were stored on the Read More button but never read by the JS modal (which reads from votesData). Removing them keeps the cached card HTML lean — matters since card_html is now embedded in the JS payload for all 250+ votes.
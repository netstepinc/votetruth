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

## Verification Checklist

**Code-verified (done):**
- [x] `php -l` clean — `core/legislator-votes.php`, `legislator.php`, `legislator-vote-history.php`, `votes-stats.php`
- [x] JSON-LD outputs Person + Issue Score ItemList — `legislator.php` L167–203
- [x] Canonical stays base URL; og:url matches active variant — `fi_seo_tags()` call confirmed; `$base_url` ordering bug fixed
- [x] Filter interactions update URL, share URL, PDF URL — JS `pushStateFromSelection()` + `updateOgUrl()` + `updatePrintModalReportBase()` all wired
- [x] `fi_legislator_votes_query()` naming — no conflict
- [x] Cache invalidates on vote/rollcall/report save — all three hooks wired
- [x] `FI_DEV` bypasses session transient cache
- [x] Deep links set initial filter from URL
- [x] Session rail renders — PHP loop + JS `highlightNav()` + `renderReportChips()`
- [x] Load More (25 per click)
- [x] Report intro content above vote cards
- [x] Print/PDF modal sync — consolidated to single PDF button triggering modal
- [x] `fi_vote_cost_compact_badge()` class inversion fixed (`+` → `'good'`)
- [x] Dead code cluster deleted (`x_fi_legislator_votes_get`, `x_fi_legislator_tags_get`, and support functions ~200 lines)
- [x] `ajax-lists.php` return type fixed (`?object` → `?array`)
- [x] Vote card pre-rendered in compile pass (Step 7) — `$votes_json` JS payload reduced to 7 fields
- [x] `subtitle` in `vote_groups` now uses full names via `fi_gov_name()` + `fi_chamber_label()`

**Requires runtime testing (you):**
- [ ] `legislator/1414/` loads without error (Massie — many sessions, many votes)
- [ ] Hero issue scores (top 8 tags) display in header
- [ ] Vote history section renders current session votes on base URL
- [ ] Tag filter switches vote list correctly
- [ ] Report chips appear and filter correctly when a session is active
- [ ] Search filters on All Votes view; score badge hides while searching
- [ ] Load More adds 25 cards
- [ ] Back/forward browser buttons restore state
- [ ] Print/PDF button opens modal reflecting active session/report

**Code review items — status:**
- [x] `fi_vote_cost_compact_badge()` class inversion — **FIXED**
- [x] Precompute `text`, `text_more`, `url_vote`, `cost_badge`, `is_match`, `is_no_vote` in compile pass — **DONE** (Step 7)
- [x] Delete dead code cluster (~200 lines) + `x_` prefix orphans — **DONE**
- [ ] Remove API wrapper functions (`fi_legislator_votes_get_votes_by_session` etc.) — **PENDING**
- [x] `fi_legislator_votes_get_by_tag()` — **ALREADY DELETED** (only exists in `_PRODUCTION/` reference files; no active callers)
- [ ] `fi_session_votes_cache_get()` uses `get_transient` directly — **PENDING** (pre-existing, low priority)

# Legislator Votes — Full Payload Sprint (Sprint B)

## Status: READY TO EXECUTE — Sprint A complete and stable

---

## Goal

Replace the current multi-query, per-session approach with a single cached payload
covering the legislator's complete vote history. Page load hits the cache once;
session/tag switching uses the cached data without extra DB hits.

---

## Context for AI Agent Starting This Sprint

### Rules & conventions
- Read `.devin/rules.md` and `.devin/Ai.md` before touching anything.
- Procedural PHP only. Arrays only (`ARRAY_A`). No classes, no objects, no `->` access.
- Naming: `fi_{noun}_{verb}()`. New functions go in `core/legislator-votes.php`.
- Run `php -l` on every modified file before reporting complete.
- **PLAN first, get explicit approval, then EXECUTE.** Do not write code before the plan is approved.
- Ask clarifying questions for anything ambiguous. The user prefers being interrogated over incorrect assumptions.

### Reference files (read these before planning)
| File | Purpose |
|---|---|
| `PRODUCTION/api/actions/legislator.php` | Source of truth for query strategy — this is exactly how the production payload was built using Medoo; mirror the logic using `$wpdb` |
| `PRODUCTION/payload.legislator-api.json` | Example complete payload — inspect the shape of `votes`, `rollcalls`, `tags`, `tag_scores` |
| `PRODUCTION/public/templates/legislator-api.php` | How the production frontend used the pre-loaded payload for instant JS switching |
| `core/legislator-votes.php` | Existing functions to build on — contains `fi_session_votes_cache_get()`, `fi_session_votes_cache_build()`, and the two invalidation hooks |
| `public/templates/legislator.php` | Current controller — the exact block being replaced is L55–122 |
| `public/templates/legislator-vote-history.php` | Template that renders vote list and handles AJAX session switching |
| `public/autoload/ajax-api-vote-history.php` | AJAX handler for vote history — will remain in place for Option A |

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
| `fi_session_votes_cache_get()` | current session only | 1 transient |
| Career tag JOIN (L77–122) | all sessions, raw SQL in controller | 1 |
| `fi_reports_get_by_session_ids()` | all session reports | 1 |
| AJAX `fi_public_ajax_vote_history_*` | every session/report/tag switch | **1 per click** |

The career tag JOIN (L77–122) is raw SQL inside the template controller — this belongs in `core/`.
Switching sessions always triggers an AJAX round-trip even though the data is already in the DB.

---

## Proposed Architecture

### New function 1: `fi_legislator_votes_query(int $legislator_id, string $chamber): array`

**Location:** `core/legislator-votes.php` (add after existing functions)

**Returns:**
```php
[
    'votes'      => [],  // keyed by vote_id (int); each vote has full metadata + meta decoded
    'rollcalls'  => [],  // keyed [vote_id][legislator_id] → ['cast', 'is_override']
    'tags'       => [],  // keyed by tag_id; ['id', 'name']
    'vote_tags'  => [],  // keyed by vote_id → [tag_id, ...]
    'tag_scores' => [],  // flat array, sorted by vote_count DESC; each: [id, name, vote_count, score, grade, scored]
]
```

**Query strategy (~4 queries, mirrors PRODUCTION/api/actions/legislator.php):**

1. All published, scored votes for this legislator's chamber across all sessions:
   ```sql
   SELECT v.*, vm.meta_json
   FROM fi_votes v
   INNER JOIN fi_voterc rc ON rc.vote_id = v.id AND rc.legislator_id = %d
   WHERE v.chamber = %s AND v.status = 'publish'
     AND v.constitutional IN ('Y','N')
   ORDER BY v.date_voted ASC
   ```
   Decode `meta_json` → `meta` array inline per the pattern in `fi_session_votes_cache_build()`.

2. All rollcalls for those vote IDs (reuse `fi_legislator_votes_get_rollcalls_by_vote_ids()` already in file):
   Returns `[vote_id][legislator_id] → [cast, is_override]` — same structure as existing cache.

3. All vote_tags for those vote IDs:
   ```sql
   SELECT vote_id, tag_id FROM fi_vote_tags WHERE vote_id IN (...)
   ```
   Build `vote_tags[vote_id][] = tag_id`.

4. Tag names for collected tag IDs:
   ```sql
   SELECT id, name FROM fi_taxonomy WHERE id IN (...) AND taxonomy = 'tag'
   ```
   Build `tags[tag_id] = ['id', 'name']`.

**Tag score computation** (replaces L77–122 in controller):
After queries, accumulate per-tag: votes + rollcalls → call `fi_score_calculate_from_votes()` → sort by `vote_count DESC` → store in `tag_scores`.
This is exactly what L93–118 does today; move it here, remove it from the controller.

### New function 2: `fi_legislator_votes_cache_get(int $legislator_id, string $chamber): array`

**Location:** `core/legislator-votes.php` (immediately after `fi_legislator_votes_query()`)

Same pattern as `fi_session_votes_cache_get()` (L411–433):
```php
$cache_key = 'fi_legislator_votes_' . $legislator_id . '_' . strtolower($chamber);
// Check transient → if miss, call fi_legislator_votes_query() → set transient MONTH_IN_SECONDS
// Bypass when FI_DEV is defined
```

### Cache invalidation

Extend the two existing hooks in `fi_legislator_votes_init()` (L47–51):

In `fi_legislator_votes_on_vote_saved()` (L60–64):
- After invalidating session cache, also delete `fi_legislator_votes_{legislator_id}_{chamber}` for every legislator in the vote's rollcalls.
- **Or** (simpler): delete by wildcard pattern `fi_legislator_votes_*` if WP supports it, otherwise delete all matching transients via `$wpdb` LIKE query.

In `fi_legislator_votes_on_rollcall_saved()` (L73–83):
- After invalidating session cache, also delete the legislator-level cache for `$data['legislator_id']` + the vote's chamber.

**Check:** Does `fi_legislator_votes_on_rollcall_saved()` have `$data['legislator_id']` available? Verify before implementing — `$data` comes from the `fi_rollcall_saved` action hook.

---

## Controller Changes: `public/templates/legislator.php`

**Replace L55–122** with:
```php
// 3. Full vote payload — all sessions, cached per legislator+chamber
$votes_payload = $chamber ? fi_legislator_votes_cache_get($legislator_id, $chamber) : [];
$all_votes     = $votes_payload['votes']      ?? [];
$all_rollcalls = $votes_payload['rollcalls']  ?? [];
$all_tags      = $votes_payload['tag_scores'] ?? [];   // already sorted by vote_count
$tag_scores    = array_slice($all_tags, 0, 8);

// Filter to current session; attach cast for server-rendered initial view
$display_votes = [];
foreach ($all_votes as $vote) {
    if ((int) ($vote['session_id'] ?? 0) !== $current_session_id) continue;
    if (($vote['chamber'] ?? '') !== $chamber) continue;
    $cast_data    = $all_rollcalls[(int) $vote['vote_id']][$legislator_id] ?? null;
    $vote['cast'] = $cast_data ? (string) $cast_data['cast'] : 'X';
    $vote['is_override'] = (bool) ($cast_data['is_override'] ?? false);
    $vote['id']   = (int) $vote['vote_id'];
    $display_votes[] = $vote;
}
```

The `fi_get_template()` call signatures **do not change** — same variables, same keys. Templates are unaffected.

---

## Option A vs B for vote-history template

**Option A (recommended first pass):** Keep AJAX for session switching. `legislator-vote-history.php` and `ajax-api-vote-history.php` are unchanged. The only difference is the server-rendered initial view now comes from the legislator-level cache instead of the session-level cache.

**Option B (full):** Pass `$all_votes` as JSON to the page on load; JS filters by session/report/tag with zero AJAX. Matches `PRODUCTION/public/templates/legislator-api.php` exactly. Higher risk, higher reward. **Do Option B only after Option A is confirmed stable.**

For Option A, ask the user which they want before starting.

---

## What Gets Retired

| Thing | Action |
|---|---|
| Career tag JOIN block in `legislator.php` L77–122 | **Delete** — logic moved into `fi_legislator_votes_query()` |
| `fi_session_votes_cache_get()` in controller | **Remove the call** — `fi_legislator_votes_cache_get()` replaces it for the legislator page. The function itself stays for report pages and other contexts. |

**Do NOT touch:** `fi_session_votes_cache_get()`, `fi_session_votes_cache_build()`, `fi_session_votes_cache_invalidate()` — other code depends on them.

---

## Verification Checklist

- [ ] `php -l` on `core/legislator-votes.php` and `public/templates/legislator.php`
- [ ] `legislator/1414/` loads without error (Massie — many sessions, many votes)
- [ ] Hero issue scores (top 8 tags) still display
- [ ] Vote history section renders current session votes correctly
- [ ] Tag filter in vote history still works
- [ ] Session switching works (AJAX round-trip for Option A)
- [ ] `fi_legislator_votes_query()` naming — confirm no conflict: `grep -rn "fi_legislator_votes_query" assets/plugins/`
- [ ] Cache invalidates when a rollcall is saved in admin
- [ ] `FI_DEV=true` bypasses cache (verify during development)

---

## Questions to ask the user before starting

1. Option A (keep AJAX session switching) or Option B (JS filtering from pre-loaded payload) for this sprint?
2. For cache invalidation: do rollcall saves include `legislator_id` in the `$data` array passed to `fi_rollcall_saved`? (Verify in `core/rollcalls.php` or wherever the hook fires.)
3. Should `fi_legislator_votes_query()` include ALL chambers for a legislator (e.g., legislators who served in both House and Senate) or strictly the current chamber? (The production API filtered by chamber; confirm this is correct.)
4. Is there a transient wildcard delete utility already in the codebase, or does cache invalidation need a raw `$wpdb` LIKE delete?

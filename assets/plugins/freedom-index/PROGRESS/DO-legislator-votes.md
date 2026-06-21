# Legislator Votes — Full Payload Sprint (Sprint B)

## Goal
Replace the current multi-query, per-session approach with a single cached payload
covering the legislator's complete vote history. Page load hits the cache once;
all session/report/tag switching is instant JS filtering on pre-loaded data.

---

## Problem with Current Approach

| Current query | Where | Per page load |
|---|---|---|
| `fi_legislator_get()` | legislator + sessions | 1 |
| `fi_session_votes_cache_get()` | current session votes/rollcalls/tags | 1 transient |
| Career tag score JOIN | `legislator.php` L77–122 | 1 |
| `fi_reports_get_by_session_ids()` | all session reports | 1 |
| AJAX vote history | every session/report switch | 1 per click |

Each session switch = another AJAX round-trip. Tag scores and reports are separate queries.
The session vote cache is per-session, so switching sessions always hits the DB or another transient.

---

## Proposed Architecture

### `fi_legislator_votes_query(int $legislator_id, string $chamber): array`

One function, a few JOINs, returns the complete payload structure.
Lives in `core/legislator-votes.php` alongside existing vote functions.

**Returns:**
```php
[
    'votes'        => [],   // all published votes keyed by vote_id, with full metadata
    'rollcalls'    => [],   // cast keyed by vote_id
    'tags'         => [],   // tag metadata keyed by tag_id
    'vote_tags'    => [],   // vote_id → [tag_id, ...]
    'tag_scores'   => [],   // computed per-tag score/grade/vote_count, sorted by vote_count
]
```

**Query strategy (mirrors production API `legislator.php`):**
1. All published votes for legislator's chamber → votes + meta
2. All rollcalls for those vote IDs → legislator's cast
3. All vote_tags for those vote IDs → tag associations
4. Tag names for those tag IDs

Total: ~4 queries regardless of session count. Production proved this is fast even for
Massie (200+ votes, 10+ years, 6 sessions).

### `fi_legislator_votes_cache_get(int $legislator_id, string $chamber): array`

Caching wrapper. Same pattern as `fi_session_votes_cache_get()`:
- Cache key: `fi_legislator_votes_{legislator_id}_{chamber}`
- TTL: `MONTH_IN_SECONDS` (invalidated on vote/rollcall save hooks already in `fi_legislator_votes_init()`)
- Bypassed when `FI_DEV` is defined

### Cache Invalidation

`fi_legislator_votes_on_vote_saved()` and `fi_legislator_votes_on_rollcall_saved()` already exist
(lines 60, 73 in `core/legislator-votes.php`). Extend them to also invalidate the
legislator-level cache key.

---

## What Changes in the Controller (`legislator.php`)

Before:
```php
$session_cache     = fi_session_votes_cache_get($current_session_id);  // per session
$session_votes     = $session_cache['votes'] ?? [];
$session_rollcalls = $session_cache['rollcalls'] ?? [];
// + separate career tag score block (L77–122)
```

After:
```php
$votes_payload = fi_legislator_votes_cache_get($legislator_id, $chamber);
$all_votes     = $votes_payload['votes']      ?? [];
$all_rollcalls = $votes_payload['rollcalls']  ?? [];
$all_tags      = $votes_payload['tags']        ?? [];
$vote_tags     = $votes_payload['vote_tags']   ?? [];
$tag_scores    = array_slice($votes_payload['tag_scores'] ?? [], 0, 8);

// Initial render: filter $all_votes to current session
$display_votes = array_filter($all_votes, fn($v) => (int)$v['session_id'] === $current_session_id);
```

Career tag score block (L77–122) **deleted** — computed inside `fi_legislator_votes_query()`.

---

## What Changes in `legislator-vote-history.php`

The AJAX vote load calls remain but become optional — if the full payload is already
in JS, session switching can be pure JS filter instead of AJAX round-trip.

**Option A (minimal change):** Keep AJAX for switching; use cached payload only for
initial server render. Easy, safe.

**Option B (full):** Pass the entire votes payload to JS as a JSON object on page load.
Session/report/tag switching = JS array filter, zero AJAX. Matches production `legislator-api.php`
behavior exactly.

Recommend Option A for first pass (less risk), Option B once A is stable.

---

## What Gets Retired / Simplified

| Function | Fate |
|---|---|
| `fi_session_votes_cache_get()` | Retained for use in other contexts (report pages, etc.); no longer used by legislator profile |
| `fi_session_votes_cache_build()` | Same |
| Career tag score block in `legislator.php` L77–122 | Deleted — moved into `fi_legislator_votes_query()` |
| Per-session AJAX on vote history nav | Optional — can stay for Option A |

---

## Reference Files

- `PRODUCTION/api/actions/legislator.php` — source of truth for query strategy and payload shape
- `PRODUCTION/payload.legislator-api.json` — example of complete payload output
- `PRODUCTION/public/templates/legislator-api.php` — how JS used the pre-loaded payload for instant switching
- `core/legislator-votes.php` — existing vote functions to build on / align with

---

## Prerequisites Before Starting Sprint B

- Sprint A (toolbar + modals) complete and stable
- Confirm `fi_legislator_votes_query()` naming doesn't conflict with anything
- Decide Option A vs B for JS filtering strategy
- Verify cache invalidation hooks cover all write paths (vote save, rollcall save, session publish/unpublish)

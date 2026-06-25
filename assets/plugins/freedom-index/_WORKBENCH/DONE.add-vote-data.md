# Enhance Vote data

See files:
assets/plugins/freedom-index/admin/autoload/schema.php
assets/plugins/freedom-index/admin/autoload/taxonomy.php
assets/plugins/freedom-index/admin/views/taxonomy.php
assets/plugins/freedom-index/admin/views/vote-edit.php
+ meta processing save process.


## Add fi_taxonomy:description

Add description field to table.
Add description field to Admin fi-tags page.
    http://localhost/votetruth/wp-admin/admin.php?page=fi-tags
Debug tags page that is failing now. Clicking Edit does nothing.


## Add fi_votes:meta keys

We need to add new vote meta fields. No DB schema change required.

Admin > Votes > Edit: Displayed above Short Description.
'impact_summary' => [
    'label' => 'Impact Summary',
    'type' => 'wysiwyg',
    'cols' => 'col-12',
    'help' => 'Answer the users question: Why should this matter to me?',
    'editor_settings' => [
        'textarea_rows' => 3,
        'media_buttons' => false,
        'teeny' => true,
        'tinymce' => [
            'height' => 75,
        ],
    ],
],

Passed/Failed | meta:outcome
'vote_outcome' => [
    'label' => 'Vote Outcome',
    'type' => 'raiod', //Do we have an A/B toggle field?
    'options' => [
        '1' => 'Passed',
        '0' => 'Rejected', //control language in front end.
    ],
    'cols' => 'col-12',
    'help' => '',
],


Vote Details section: Small 2-col above url_bill
'votes_for' => [
    'label' => 'Votes For',
    'type' => 'text',
    'cols' => 'col-12',
    'help' => '',
],

Vote Details section: Small 2-col above url_bill
'votes_against' => [
    'label' => 'Votes Against',
    'type' => 'text',
    'cols' => 'col-12',
    'help' => '',
],


Vote Details section: 4-col above url_bill
'citation' => [
    'label' => 'Constitutional Citation',
    'type' => 'text',
    'cols' => 'col-12',
    'help' => '',
],


# Action Plan

## Interrogation Log — Jun 23, 2026

| # | Question | Decision |
|---|---|---|
| 1 | `description` column vs meta JSON? | **Real TEXT column** in `fi_taxonomy` — schema change required |
| 2 | Tags Edit bug root cause? | `fi_taxonomy_get()` returns `array`; `taxonomy.php` used `->` object notation. Guard always failed → `$is_edit` reset to false. **FIXED.** |
| 3 | `vote_outcome` field type | **`radio-group`** — Passed / Rejected toggle buttons |
| 4 | `vote_outcome` placement | **Vote Details card** (alongside date, session, chamber) |
| 5 | `votes_for`/`votes_against` vs Legiscan | **Auto-populate from Legiscan if blank/new; do not overwrite if manually set.** Same pattern as `votes_yea`/`votes_nay`. |
| 6 | `citation` input type | **`text`** — short free text like "Article I, Section 8" |
| 7 | Field row order above url_bill | **All four on one row:** `vote_outcome` · `votes_for` · `votes_against` · `citation`. URL fields on the next row. |
| 8 | `impact_summary` form position | **After Constitutional + Cost, immediately before Short Description** in Position Statements card |
| 9 | New fields / public card display | **New card layout:** `IF impact_summary` → show structured new layout. `ELSE` → fall back to `description_short`. Goal: separate commentary from metadata so visual hierarchy is possible. Old short descriptions embed outcome + citation in text (e.g. "Passed 216 to 209 on 11/18/2025, Roll Call 296. See U.S. Const., Art. I, Sec. 8") — new fields extract those into discrete data points. |
| 10 | Compile cache inclusion | **Both `vote_outcome` and `citation` precomputed** into `fi_legislator_votes_query()` cache. `impact_summary` included for modal. |
| 11 | Tag `description` public display | **When tag filter is active** on legislator page: insert tag description above the vote list, below the section card header. |
| 12 | `votes_for`/`votes_against` empty behaviour | **Empty = delete from meta.** Display fallback: if both are empty AND Legiscan `votes_yea`/`votes_nay` are present → show Legiscan counts. Hide vote count line if neither. |
| 13 | Schema ALTER strategy | **User will run SQL manually in phpMyAdmin.** Update `CREATE TABLE` in `schema.php` for future clean installs only. No helper function needed. |
| 14 | Tag description in JS payload | **Add `description` to the `tags` entry in the compile cache.** JS injects it above the vote list on filter change. |
| 15 | Card meta line layout | **Three `nowrap` groups, one line on LG, stack on mobile:** `{date_voted} {chamber} {bill_number}` · `{outcome} {votes_for} to {votes_against}` · `{citation}` |
| 16 | Modal with `impact_summary` | **Both:** `impact_summary` first (the "why it matters"), then full constitutional write-up (`text_more`) below |
| 17 | Cache invalidation after deploy | **Manual** — admin "Clear FI Cache" button in the admin bar after deploy |
| 18 | `vote_outcome` color | **Green for Passed, Red for Rejected** |
| 19 | Vote count label | **"216 to 209"** — numbers with "to" separator, no Yea/Nay labels |
| 20 | `citation` link | **Plain text only for now.** Future: Constitution may come to the site as a linked index; `citation` will become a `select` that auto-includes the link. No `url_citation` field in this sprint. |
| 21 | `impact_summary` scope | **Takes precedence everywhere** — card, modal, and standalone `/vote/{id}/` page |
| 22 | `description_short` going forward | **Relabel as `Short Description (legacy)`** in the admin form. Stays as fallback for old votes during transition. |
| 23 | Cost badge + constitutional/cast in new layout | **Replace cost badge + status circle with a larger text block** between body and meta line: `Constitutional: Yes/No \| Vote Cast: Yes/No \| Financial Impact: {cost}`. These are value-first labels for visual scan. Cost badge removed from meta row in new layout. Status circle hidden (cast expressed as "Vote Cast" text instead). |
| 24 | `impact_summary` in JS payload | **Add as 8th field** in the JS payload alongside `text_more`. Modal reads both. |
| 25 | `vote_outcome` badge vs. status circle | Answered by Q23 — circle replaced by "Vote Cast: Yes/No" text in new layout. Circle stays in fallback (old) layout. |
| 26 | `search_text` update | **No** — title alone is enough; body text is noise. |
| 27 | New fields when `impact_summary` absent | **Don't show new fields at all.** If no `impact_summary`, render existing layout unchanged — no new meta line, no Constitutional/Vote Cast block, no outcome/counts/citation. Pure fallback. |

---

## Work Packages

### WP-1 — Fix Tags Edit bug ✅ DONE

`admin/views/taxonomy.php` L34 + L116: `$item->taxonomy`, `$item->gov`, `$item->name`
→ `$item['taxonomy']`, `$item['gov']`, `$item['name']`.

---

### WP-2 — Add `description` column to `fi_taxonomy`

**Schema SQL (run in phpMyAdmin):**
```sql
ALTER TABLE wp_fi_taxonomy
  ADD COLUMN description TEXT NULL AFTER name;
```

**Files to update after running SQL:**

1. **`admin/autoload/schema.php`** — Add `description TEXT NULL` after `name` in the
   `fi_taxonomy` `CREATE TABLE` block (keeps schema.php accurate for future installs):
   ```sql
   name VARCHAR(255) NOT NULL,
   description TEXT NULL,
   meta JSON NULL,
   ```

2. **`core/taxonomy.php` — `fi_taxonomy_save()`** — Write `description` to `$db_data`:
   ```php
   if (array_key_exists('description', $data)) {
       $db_data['description'] = $data['description'] !== ''
           ? sanitize_textarea_field((string) $data['description'])
           : null;
   }
   ```

3. **`admin/autoload/taxonomy.php` — `fi_admin_post_save_taxonomy_item()`** — Read
   `$_POST['description']` and pass to `$data`:
   ```php
   $data['description'] = sanitize_textarea_field($_POST['description'] ?? '');
   ```

4. **`admin/views/taxonomy.php`** — Add `description` textarea below the `name` field.
   Pre-populate from `$item['description']` (array access — WP-1 already fixed this):
   ```php
   <div class="mb-3">
       <label class="form-label fw-semibold" for="fi-taxonomy-description">Description</label>
       <textarea name="description" id="fi-taxonomy-description" class="form-control" rows="3"><?php
           echo esc_textarea((string) ($item['description'] ?? ''));
       ?></textarea>
   </div>
   ```

---

### WP-3 — Add new `fi_votes:meta` fields

**No DB schema change.** All five fields enter `fi_admin_votes_get_meta_fields()` and
flow through the existing meta pipeline without touching `fi_admin_votes_build_meta_payload()`.

**`fi_admin_helpers_sanitize_field_value()` handles:** `wysiwyg` → `wp_kses_post`,
`text` → `sanitize_text_field`, `radio-group` → same as `text`.

#### 3a — `admin/autoload/votes.php` — `fi_admin_votes_get_meta_fields()`

**Auto-populate for `votes_for`/`votes_against`:** Mirror the existing Legiscan
auto-populate block in `fi_admin_votes_handle_save()` — after the existing
`votes_yea`/`votes_nay` fill-in, add:
```php
if (empty($data['meta']['votes_for']) && isset($roll_call['yea'])) {
    $data['meta']['votes_for'] = (string) (int) $roll_call['yea'];
}
if (empty($data['meta']['votes_against']) && isset($roll_call['nay'])) {
    $data['meta']['votes_against'] = (string) (int) $roll_call['nay'];
}
```
Do NOT overwrite if already set (same `empty()` guard).

Add five new keys to `fi_admin_votes_get_meta_fields()`. Insert `impact_summary` and
`vote_outcome` after `cost` and before `description_short`. Add `votes_for`,
`votes_against`, and `citation` before `url_bill`:

```php
// --- insert after 'cost' entry ---
'impact_summary' => [
    'label'           => 'Impact Summary',
    'type'            => 'wysiwyg',
    'cols'            => 'col-12',
    'help'            => "Answer the user's question: Why should this matter to me?",
    'editor_settings' => [
        'textarea_rows' => 3,
        'media_buttons' => false,
        'teeny'         => true,
        'tinymce'       => ['height' => 75],
    ],
],
// --- existing description_short, description_medium, description_long stay here ---

// --- insert before 'url_bill' entry ---
'vote_outcome' => [
    'label'   => 'Vote Outcome',
    'type'    => 'radio-group',
    'options' => ['1' => 'Passed', '0' => 'Rejected'],
    'cols'    => 'col-md-3',
    'help'    => '',
],
'votes_for' => [
    'label' => 'Votes For',
    'type'  => 'text',
    'cols'  => 'col-md-3',
    'help'  => '',
],
'votes_against' => [
    'label' => 'Votes Against',
    'type'  => 'text',
    'cols'  => 'col-md-3',
    'help'  => '',
],
'citation' => [
    'label' => 'Constitutional Citation',
    'type'  => 'text',
    'cols'  => 'col-md-3',
    'help'  => 'e.g. Article I, Section 8',
],
```

#### 3b — `admin/views/vote-edit.php`

**Position Statements card** — insert `impact_summary` between the Cost/Impact field
and the Short Description field (after the existing `col-md-6` constitutional +
`col-md-6` cost row):
```php
<div class="col-12">
    <?php fi_form_field('meta_impact_summary', [
        'name'            => 'meta[impact_summary]',
        'label'           => 'Impact Summary',
        'type'            => 'wysiwyg',
        'value'           => $vote_meta['impact_summary'] ?? '',
        'help'            => "Answer the user's question: Why should this matter to me?",
        'editor_settings' => [
            'textarea_rows' => 3,
            'media_buttons' => false,
            'teeny'         => 1,
            'tinymce'       => ['height' => 75],
        ],
    ]); ?>
</div>
<!-- existing Short Description field follows -->
```

**Vote Details card** — insert a new 4-col row immediately above the existing
`url_bill` + `url_rollcall` row:
```php
<div class="col-md-3">
    <?php fi_form_field('meta_vote_outcome', [
        'name'    => 'meta[vote_outcome]',
        'label'   => 'Vote Outcome',
        'type'    => 'radio-group',
        'options' => ['1' => 'Passed', '0' => 'Rejected'],
        'value'   => $vote_meta['vote_outcome'] ?? '',
    ]); ?>
</div>
<div class="col-md-2">
    <?php fi_form_field('meta_votes_for', [
        'name'  => 'meta[votes_for]',
        'label' => 'Votes For',
        'type'  => 'text',
        'value' => $vote_meta['votes_for'] ?? '',
    ]); ?>
</div>
<div class="col-md-2">
    <?php fi_form_field('meta_votes_against', [
        'name'  => 'meta[votes_against]',
        'label' => 'Votes Against',
        'type'  => 'text',
        'value' => $vote_meta['votes_against'] ?? '',
    ]); ?>
</div>
<div class="col-md-5">
    <?php fi_form_field('meta_citation', [
        'name'  => 'meta[citation]',
        'label' => 'Constitutional Citation',
        'type'  => 'text',
        'value' => $vote_meta['citation'] ?? '',
        'help'  => 'e.g. Article I, Section 8',
    ]); ?>
</div>
<!-- existing url_bill + url_rollcall row follows -->
```

---

### WP-4 — Compile cache: add new fields to `fi_legislator_votes_query()`

**File:** `core/legislator-votes.php`

#### 4a — Vote field hydration

In the compile pass vote hydration block, add:
```php
$vote['vote_outcome']   = $meta['vote_outcome'] ?? null;   // '1' | '0' | null
$vote['citation']       = $meta['citation'] ?? '';
$vote['impact_summary'] = $meta['impact_summary'] ?? '';   // card + modal
$vote['votes_for']      = $meta['votes_for'] ?? ($meta['votes_yea'] ?? null);
$vote['votes_against']  = $meta['votes_against'] ?? ($meta['votes_nay'] ?? null);
```
`votes_for`/`votes_against` fall back to Legiscan `votes_yea`/`votes_nay` at compile
time — no fallback logic needed in the template.

#### 4b — Tag description in compile cache

In the `vote_groups['tags']` build loop, fetch the tag row and include `description`:
```php
$vote_groups['tags'][$tag_id] = [
    'title'       => $tag['name'],
    'description' => $tag['description'] ?? '',  // ← add this
    'score'       => ...,
    'votes'       => [...],
];
```
JS reads `voteGroups.tags[tagId].description` and injects it above the vote list on
filter change.

#### 4c — Card compile step (`card_html`)

Pass new args to `vote-card.php` at compile time:
```php
$card_args = [
    // existing fields...
    'vote_outcome'   => $vote['vote_outcome'],
    'votes_for'      => $vote['votes_for'],
    'votes_against'  => $vote['votes_against'],
    'citation'       => $vote['citation'],
    'impact_summary' => $vote['impact_summary'],
];
```
`vote-card.php` uses `impact_summary` to pick the layout and builds the meta line.

---

### WP-5 — `vote-card.php` new layout

**File:** `public/templates/vote-card.php`

Add new args to the `$config` merge array:
```php
'vote_outcome'   => '',   // '1' | '0' | ''
'votes_for'      => null,
'votes_against'  => null,
'citation'       => '',
'impact_summary' => '',
```

**Two layouts — switch on `impact_summary`:**

#### New layout (IF `impact_summary` is non-empty)

```
[Title]

[impact_summary body text]
[Read More badge → modal]

[Constitutional: Yes/No  |  Vote Cast: Yes/No  |  Financial Impact: {cost}]
  ↑ larger text, value-first labels for visual scan
  ↑ status circle HIDDEN (cast expressed as "Vote Cast" text here instead)

[meta line — one row LG, stack mobile:]
  <span class="nowrap">{date_voted} {chamber_label} {bill_number}</span>
  <span class="nowrap">{outcome_label} {votes_for} to {votes_against}</span>  ← omit if both null
  <span class="nowrap">{citation}</span>  ← omit if empty
```

- **Constitutional** → `constitutional` field: `Y` → "Yes", `N` → "No", `U` → omit
- **Vote Cast** → legislator `cast`: `Y` → "Yes", `N` → "No", `X` → "No Vote"
- **Financial Impact** → `cost` meta field; omit the label+value if empty
- **vote_outcome**: `'1'` → `Passed` (green), `'0'` → `Rejected` (red), empty → omit span

#### Fallback layout (IF `impact_summary` is empty)

Existing card rendered exactly as before — no changes to existing markup, cost badge
stays in meta row, status circle visible. Pure no-op for old votes.

**Modal body (new layout only):**
1. `impact_summary` (the "why it matters")
2. `text_more` (constitutional write-up) below, if non-empty
Same logic on standalone `/vote/{id}/` page.

**JS payload change (WP-4b):**
Add `impact_summary` as 8th field so the modal can read it client-side.

---

### WP-6 — Tag description above vote list (JS + PHP)

**File:** `public/templates/legislator-vote-history.php`

**Server-side (page load with tag URL param):**
Read active tag from `$initial_group` — if it has a non-empty `description`, render a
description block above the vote list cards.

**Client-side (JS filter change):**
In the JS `renderCards()` / filter handler, after reading `voteGroups.tags[tagId]`:
```js
const desc = voteGroups.tags[tagId]?.description ?? '';
document.getElementById('fi-tag-description').textContent = desc;
document.getElementById('fi-tag-description').hidden = desc === '';
```
Add `<div id="fi-tag-description" hidden></div>` below the section card header.
Hide again when switching away from a tag view.

---

### WP-7 — `description_short` admin label

**File:** `admin/autoload/votes.php` — `fi_admin_votes_get_meta_fields()`

Change the `description_short` label:
```php
'description_short' => [
    'label' => 'Short Description (legacy)',
    // ... rest unchanged
],
```
No functional change — field stays, fallback logic stays, label signals to staff
that `impact_summary` is the preferred field going forward.

---

## Execution Order

```
WP-1  Fix tags Edit bug             ✅ DONE
WP-2  fi_taxonomy description col   Run SQL in phpMyAdmin → update schema.php + taxonomy save + admin form
WP-3  fi_votes meta fields          admin/autoload/votes.php + vote-edit.php
WP-4  Compile cache update          core/legislator-votes.php (vote fields + tag descriptions)
WP-5  vote-card.php new layout      public/templates/vote-card.php
WP-6  Tag description JS + PHP      public/templates/legislator-vote-history.php
WP-7  description_short label       admin/autoload/votes.php (1-line change)
```

**After deploy:** Clear FI Cache via the admin bar button to regenerate all legislator
card caches with the new fields.

Run `php -l` on every modified file before marking complete.

---

All clean. Summary of what changed:

votes_for/votes_against fields removed from meta_fields, auto-populate code removed, form inputs removed
Admin form row is now vote_outcome (col-md-4) · citation (col-md-4) + a read-only display of Yea: 216 · Nay: 209 from existing Legiscan data (only shown when present)
Compile cache reads votes_yea/votes_nay directly — no fallback chain, no redundant keys
vote-card.php still receives votes_for/votes_against args, which are now sourced from votes_yea/votes_nay in the compile step — public display unchanged
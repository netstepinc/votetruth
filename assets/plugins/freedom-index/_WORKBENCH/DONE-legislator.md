# Legislator Profile — Action Toolbar & Modals Port
## Status: PLANNING — do not code until user approves

---

## Scope
1. Add `fi_reports_get_by_session_ids()` to `core/reports.php` — single WHERE IN query
2. Update `legislator.php` controller — build `$contact`, `$session_reports`, `$current_report_id`, `$current_url`; pass all to partials
3. `legislator-header.php` — remove action buttons from hero; add 5-button toolbar below hero
4. `legislator-modals.php` — port all 5 production modal partials; convert object → array notation

**Do NOT touch:** vote-card.php, legislator-vote-history.php, ajax-api-vote-history.php, any other core functions

---

## Files to Touch

| File | Change |
|------|--------|
| `core/reports.php` | Add `fi_reports_get_by_session_ids(array $session_ids): array` |
| `public/templates/legislator.php` | Build `$contact`, `$session_reports`, `$current_report_id`, `$current_url`; pass to partials |
| `public/templates/legislator-header.php` | Remove buttons from hero; add toolbar below |
| `public/templates/legislator-modals.php` | Port all 5 modals from production partials; array notation throughout |

---

## Step 1 — `core/reports.php`: Add `fi_reports_get_by_session_ids()`

New lean function — single WHERE IN query, returns array keyed by session_id.
Decodes `payload_json` → `payload` (array) inline; drops raw JSON so callers get clean data.

```php
function fi_reports_get_by_session_ids(array $session_ids, string $status = 'publish'): array {
    global $wpdb;
    if (empty($session_ids)) return [];
    $session_ids  = array_map('absint', $session_ids);
    $placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
    $params       = $session_ids;
    if ($status) $params[] = $status;
    $sql = $wpdb->prepare(
        "SELECT id, session_id, gov, title, title_menu, slug, format, status, date_publish, payload_json, score, score_data
         FROM {$wpdb->prefix}fi_reports
         WHERE session_id IN ($placeholders)"
        . ($status ? " AND status = %s AND (date_publish IS NULL OR date_publish <= NOW())" : "")
        . " ORDER BY date_publish DESC",
        ...$params
    );
    $rows = $wpdb->get_results($sql, ARRAY_A);
    if (!is_array($rows)) return [];
    $out = [];
    foreach ($rows as $r) {
        $sid = (int) $r['session_id'];
        // Normalize payload through fi_report_payload_normalize() for consistent structure
        $r['payload']    = fi_report_payload_normalize($r['payload_json'] ?? '');
        $r['score_data'] = !empty($r['score_data']) ? (json_decode($r['score_data'], true) ?: []) : [];
        unset($r['payload_json']);
        $out[$sid][] = $r;
    }
    return $out;
}
```

Callers access: `$report['payload']['report_pdf_url']`, `$report['payload']['content']`, etc.

---

## Step 2 — `legislator.php` Controller additions

After existing data-fetch block, add:

### 2a. Build `$contact` from decoded meta (no extra query)
```php
$meta    = is_array($legislator['meta'] ?? null) ? $legislator['meta'] : [];
$contact = [
    'phone'   => $meta['contact']['phone'] ?? ($meta['phone'] ?? ''),
    'email'   => $meta['contact']['email'] ?? ($meta['email'] ?? ''),
    'website' => is_array($meta['website'] ?? null)
                    ? (string) ($meta['website'][0] ?? '')   // plain string URLs: ["https://..."]
                    : (string) ($meta['website'] ?? ''),
    'social'  => $meta['social'] ?? [],      // keys: facebook, instagram, twitter, youtube
    // offices: [{name, type, address, city, state, zip, phone}] — city/phone optional
    'offices' => is_array($meta['address'] ?? null) ? $meta['address'] : [],
];
```

### 2b. Fetch all session reports in one query
```php
$session_ids     = array_column($sessions, 'session_id');
$session_reports = fi_reports_get_by_session_ids(array_map('intval', $session_ids));
```

### 2c. Current state for print modal
```php
$current_report_id = fi_public_get_legislator_report_id(); // already used in vote-history pass
$current_url       = home_url('/legislator/' . $legislator_id . '/')
    . ($current_session_id ? 'session/' . $current_session_id . '/' : '')
    . ($current_report_id  ? 'report/'  . $current_report_id  . '/' : '');
```

### 2d. User data for authenticated modals
```php
$current_user_id = get_current_user_id();
$user_lists      = $current_user_id ? fi_lists_get_by_user($current_user_id) : [];
$pdf_contacts    = $current_user_id ? fi_pdf_contacts_get($current_user_id) : [];
$pdf_default_idx = $current_user_id ? fi_pdf_contacts_default_index_get($current_user_id) : null;
```

### 2e. Update `fi_get_public_template` calls
Pass additional variables to each partial:

**legislator-header:**
```php
'gov' => $gov,
'contact' => $contact,
'legislator_id' => $legislator_id,
```

**legislator-vote-history:** (already receives `$current_report_id` — add `$session_reports`)
```php
'session_reports' => $session_reports,
'current_report_id' => $current_report_id,
```

**legislator-modals:**
```php
'contact'          => $contact,
'session_reports'  => $session_reports,
'current_report_id'=> $current_report_id,
'current_url'      => $current_url,
'user_lists'       => $user_lists,
'pdf_contacts'     => $pdf_contacts,
'pdf_default_idx'  => $pdf_default_idx,
'current_user_id'  => $current_user_id,
'gov'              => $gov,
```

---

## Step 3 — `legislator-header.php`: Toolbar

Remove `<div class="col-12 col-md-auto">` button group from hero.

Add below closing `</section>` of hero:

```php
<?php
$back_url = home_url('/' . strtolower($gov) . '/legislators/');
$buttons = [
    ['target' => '#fi-share-modal',       'icon' => 'bi-share',          'label' => 'Share This Page'],
    ['target' => '#fi-contact-modal',     'icon' => 'bi-telephone',      'label' => 'Contact Info'],
    ['target' => '#fi-lists-modal',       'icon' => 'bi-bookmark-plus',  'label' => 'Add to My Lists'],
    ['target' => '#fi-personalize-modal', 'icon' => 'bi-person-vcard',   'label' => 'Personalize PDFs'],
    ['target' => '#fi-print-modal',       'icon' => 'bi-printer',        'label' => 'Print Scorecard'],
];
?>
<div class="fi-action-toolbar bg-white border-bottom">
    <div class="container py-2">

        <!-- Desktop: back link left, buttons right -->
        <div class="d-none d-md-flex align-items-center justify-content-between gap-2">
            <a href="<?= esc_url($back_url) ?>" class="btn btn-sm btn-outline-secondary text-nowrap">
                &larr; All Legislators
            </a>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <?php foreach ($buttons as $b): ?>
                <button type="button" class="btn btn-sm btn-outline-primary"
                    data-bs-toggle="modal" data-bs-target="<?= esc_attr($b['target']) ?>">
                    <i class="bi <?= esc_attr($b['icon']) ?> me-1" aria-hidden="true"></i><?= esc_html($b['label']) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Mobile: buttons stacked full-width, back link at bottom -->
        <div class="d-flex d-md-none flex-column gap-2">
            <?php foreach ($buttons as $b): ?>
            <button type="button" class="btn btn-sm btn-outline-primary w-100"
                data-bs-toggle="modal" data-bs-target="<?= esc_attr($b['target']) ?>">
                <i class="bi <?= esc_attr($b['icon']) ?> me-1" aria-hidden="true"></i><?= esc_html($b['label']) ?>
            </button>
            <?php endforeach; ?>
            <a href="<?= esc_url($back_url) ?>" class="btn btn-sm btn-outline-secondary w-100">
                &larr; All Legislators
            </a>
        </div>

    </div>
</div>
```

---

## Step 4 — `legislator-modals.php`: Port all 5 modals

Source files to port (in order):
1. `PRODUCTION/public/templates/partials/legislator-modal-contact.php`
2. `PRODUCTION/public/templates/partials/legislator-modal-share.php`
3. `PRODUCTION/public/templates/partials/legislator-modal-list.php`
4. `PRODUCTION/public/templates/partials/legislator-modal-personalize.php`
5. `PRODUCTION/public/templates/partials/legislator-modal-print.php`

Conversion rules for all 5:
- `$legislator->property` → `$legislator['property']`
- `$report->id` → `$report['id']`
- `$list->id` → `$list['id']`, `$list->name` → `$list['name']`
- `$contact['field']` → already correct (comes from controller-built `$contact`)
- `$reports` in print modal → iterate `$session_reports` keyed by session_id
- Keep all JS inside each modal as-is — do not touch modal JS logic
- Replace `is_user_logged_in()` checks only if needed for variables; template already has them

---

## Step 5 — Verification

- `php -l` on ALL 4 modified files
- Check debug.log — must be clean
- Reload `/legislator/1414/` — no 404
- Confirm toolbar appears below hero with 5 buttons
- Test each modal opens (Contact, Share, Lists, Personalize, Print)
- Mobile: stack layout, All Legislators at bottom

---

## Questions Still Open
- None blocking. Ready to implement pending user approval.

---

## What NOT to Touch
- `vote-card.php`
- `legislator-vote-history.php` (except it will now receive `$session_reports` from controller — no template changes needed, it already uses that variable)
- `ajax-api-vote-history.php`
- Any core function other than adding `fi_reports_get_by_session_ids()` to `core/reports.php`
- CSS


Step 1 — core/reports.php Added fi_reports_get_by_session_ids(): single WHERE IN query fetching all published reports for multiple sessions at once, decoded via fi_report_payload_normalize(), returned keyed by session_id.

Step 2 — public/templates/legislator.php After the tag-score block, added:

$contact built from decoded meta (no extra query)
$session_reports via the new function
$current_url for the print modal
$current_user_id, $user_lists, $pdf_contacts, $pdf_default_idx
Updated all three fi_get_template() calls to pass the new variables to their respective partials.

Step 3 — public/templates/legislator-header.php Removed the 3-button group from inside the hero. Added a fi-action-toolbar section below the hero with:

Desktop: "← All Legislators" left, 5 buttons right
Mobile: 5 buttons stacked full-width, "← All Legislators" at bottom as a button
Step 4 — public/templates/legislator-modals.php Ported all 5 production modal partials into one file using fi_modal_open() / fi_modal_close() helpers (no duplicate trigger buttons):

Contact — uses $contact array; website/email/phone/social/offices, no missing helper functions
Share — copy link, email, X, Facebook, LinkedIn, QR code; JS reads window.location for current URL
Lists — array notation throughout ($fi_list['id'], $fi_list['name'], $fi_list['legislators']); AJAX create/update
Personalize — full logged-in + guest flows; contacts list, add/edit/delete, privacy notice, links to Print modal
Print — PDF format buttons with dynamic URL update on contact selection; logged-in and guest contact checkboxes; syncs with Personalize via fi:pdf-contacts-changed event
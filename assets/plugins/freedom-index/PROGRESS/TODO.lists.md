# Lists & Modals — Known Issues After Refactor

## 1. `legislator-modals.php` — Wrong Data Format (BLOCKING)

The production modals file uses the OLD object/variable format. The new controller
passes arrays, not objects. Nothing will render correctly until this is ported.

- `$legislator->display_name` → must be `$legislator['display_name']`
- `$contact['phone']` etc. → `$contact` variable doesn't exist in new controller;
  must be assembled from `$legislator['meta']` or top-level legislator fields
- `$reports` → not passed from new controller; needed for PDF report selector in Print modal
- `$lists` (user's saved lists) → not passed from new controller; needed for Add to My Lists modal

**Fix:** Update controller (`legislator.php`) to pass `$contact` and `$reports` arrays,
then port modals template to use array notation throughout.

---

## 2. `account-lists.php` — Object Notation (BLOCKING)

Uses `$list->id`, `$list->name` (object access) throughout.
`fi_lists_get_by_user()` in the refactored `core/lists.php` returns `ARRAY_A`
(associative arrays), so this will crash.

**Fix:** Replace all `$list->property` with `$list['property']` throughout.

---

## 3. `ajax-lists.php` — Return Type Mismatch

`fi_public_ajax_lists_get_owned_list()` (line 144) declares return type `?object`,
but `fi_list_get_by_id()` in refactored `core/lists.php` returns `?array` (uses `ARRAY_A`).
Any caller checking `$list->id` will fail.

**Fix:** Change `fi_public_ajax_lists_get_owned_list()` return type to `?array`
and update all property accesses to array notation within `ajax-lists.php`.

---

## 4. `core/lists.php` — `fi_list_get_by_id()` Return Type Annotation Wrong

Function docblock says `@return object|null` but function signature declares `?array`
and body uses `ARRAY_A`. Annotation is misleading; confirm return type and fix docblock.

---

## 5. Add to My Lists Modal — Not in New Modals Template

The production `legislator-modals.php` contained the Add to My Lists modal HTML
and associated JS. This was dropped when the modals template was rewritten from scratch.
The restored file should be ported to use array notation (see item 1).

---

## Priority Order
1. Fix `legislator-modals.php` data format + pass missing vars from controller
2. Fix `account-lists.php` object → array
3. Fix `ajax-lists.php` return type mismatch
4. Fix `core/lists.php` docblock

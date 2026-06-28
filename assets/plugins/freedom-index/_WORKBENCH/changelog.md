# FREEDOM INDEX CHANGELOG

Format: YYMMDD: Description by effect.

---

260621: Replaced per-session AJAX vote loading with single cached payload per legislator. Session/tag/report switching is now instant client-side with zero AJAX round-trips. (`fi_legislator_votes_query`, `fi_legislator_votes_cache_get`)

260621: Added cache invalidation hooks for vote, rollcall, and report saves — legislator vote cache auto-dumps on content changes.

260621: Vote cards pre-rendered in compile pass and stored in cache. JS payload reduced from 18 fields to 7. Eliminated duplicate card-building logic between PHP and JS.

260621: Added JSON-LD (Person + Issue Score ItemList) to legislator pages for SEO.

260621: Replaced accordion session sidebar with mobile-first session rail (CSS scroll-snap), report chips, and issue score tiles. Session switching, report selection, and issue filtering are all client-side.

260621: Deleted `ajax-api-vote-history.php` — fully replaced by client-side filtering.

260621: Fixed `fi_vote_cost_compact_badge()` class inversion — positive cost values now display green instead of red.

260621: Fixed `fi_rollcall_saved` hook — was never firing from `fi_rollcall_save()`; now wired.

260621: Deleted ~200 lines of dead code (`x_fi_legislator_votes_get`, `x_fi_legislator_tags_get`, and their support cluster) left behind by prior agent.

260624: Added `description` column to `fi_taxonomy` table. Tags can now carry a description shown above the vote list when that tag filter is active.

260624: Added vote meta fields: `impact_summary` (WYSIWYG), `vote_outcome` (Passed/Rejected radio), `citation` (constitutional reference). Admin edit form updated.

260624: Fixed tag admin edit bug — form failed to populate when editing existing tags due to object vs array access mismatch.

260624: Fixed `subtitle` in session/report groups — was showing gov/chamber abbreviations (e.g. "US H"); now shows full names (e.g. "United States House").

260624: Added `rollcall_number` to vote card data — was reading from meta (always empty); fixed to read from the `fi_votes` table column directly.

260628: Restructured vote card into two parallel layouts: structured (triggered by `impact_summary`) and legacy (existing `description_short`). Layouts kept separate until structured design is finalized.

260628: Vote card simplified — removed `modal_mode`, `show_modal`, inline Bootstrap modal, `$details_html`, and `data-has-details`. Card is always used in a list with the JS-driven page modal.

260628: Merged `fi-vote-card--interactive` into `fi-vote-card` — all cards are interactive. Hover/focus styles added to `99.freedomindex.css`.

260628: Renamed temporal variable prefixes (`$new_`, `$use_new_layout`) to descriptive names (`$const_label`, `$cast_label`, `$cost_label`, `$structured`).

260628: Added cast indicator (status circle) to structured vote card layout using existing `fi-vote-status` styles, floated right.

260628: Added Constitutional Vote / Vote Cast / Financial Impact as `text-nowrap` spans in structured card between `impact_summary` and the meta line.

260628: Consolidated three separate PDF direct-link buttons into a single "PDF" button that opens the print modal. `updateHeader()` JS updated accordingly.

260628: Removed title link (`url_vote`) from legacy vote card — navigation to vote detail page is through the modal only.

260628: Removed manual `vote_outcome` (Passed/Rejected) toggle. Outcome is now derived from `votes_yea` vs `votes_nay` at compile time. Admin edit page shows a read-only derived outcome display instead of the radio toggle.

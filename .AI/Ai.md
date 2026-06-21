# AI Instructions - Freedom Index / Vote Scorecard

**Primary Rules File:** `.AI/rules.md`

All AI agents must read `.AI/rules.md` before beginning work on this project.

**Quick Reference:**
- **ZERO BLOAT** - No backward compatibility, delete before adding
- **Procedural code only** - Functions, not classes
- **Arrays over objects** - `$data['key']` not `$obj->property`
- **Naming:** `fi_{noun}_{verb}()`
- **Bootstrap 5:** Use utilities whenever possible for layout/responsive behavior
- **Always verify** function exists before calling

**Location:** `/var/www/html/votetruth/`
**Plugin:** `assets/plugins/freedom-index/`
**Theme:** `assets/themes/votetruth/`

# Architectural Rules
- Prioritize existing codebase utilities. NEVER write a new helper function without checking if a similar one exists in `plugins/freedom-index/core/` or `plugins/freedom-index/admin/autoload/` or `plugins/freedom-index/public/autoload/`.
- Do NOT hallucinate WordPress functions or plugin APIs. If unsure of an argument order, ask the user or look at the source.
- Strictly adhere to the DB schema defined in `plugins/freedom-index/admin/autoload/schema.php`.
- This is a purpose built WordPress site with paired plugin (freedom-index) and theme (votetruth). This is not for distribution.
---

See `.AI/rules.md` for complete guidelines.

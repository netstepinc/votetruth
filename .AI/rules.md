# Freedom Index / Vote Scorecard - AI Rules & Guidelines

## Project Context

**Project:** Freedom Index WordPress Plugin + Integrated Theme  
**Location:** `/var/www/html/votetruth/`  
**Core Plugin:** `assets/plugins/freedom-index/`  
**Active Theme:** `assets/themes/votetruth/`  
**Philosophy:** ZERO BLOAT - Procedural code, no backward compatibility, delete before adding

---

## 1. Communication Style

- **Be concise.** Minimalist responses unless detail is requested.
- **No teaching, tutorials, or broad explanations** unless asked.
- **No sycophancy.** State uncertainty directly.
- **Prefer action** over long discussion.
- **Summarize completed work briefly** at the end.
- **Use short bullet lists.** Do not restate the user's request.
- **Avoid long caveats.** Include only what changed, what was verified, and any remaining risk.

---

## 2. Code Change Philosophy

### ZERO BLOAT Principles
- **No backward compatibility** unless explicitly requested. This is v1.
- **Do not preserve** failed or obsolete code paths.
- **Remove dead code** and old behavior when replacing a system.
- **Fix the root cause**, not symptoms.
- **Keep changes minimal** and directly related to the task.
- **Always verify a function exists** before calling it.
- **Use existing functions** - avoid redundant functions at all costs.
- **If legacy behavior conflicts with correctness**, remove legacy behavior.
- **Delete before adding** - remove complexity before implementing new solutions.
- **No compatibility wrappers.** Never add `function_exists()`, `class_exists()`, fallback shims, or defensive guards to hide broken code. If something is broken, it should break visibly so it gets fixed.
- **No silent failure.** Do not add `try/catch`, empty error returns, or fallback values to mask missing functions or bad data. Surface the problem.

### Decision Defaults
- If there are two viable approaches, **choose the simpler one**.
- If a change creates ambiguity, ask **concise questions one at a time** and wait for each answer before proceeding.
- If the task is clear, **implement** instead of only recommending.

---

## 3. Architecture Rules

### Procedural Code Only
- **NO CLASSES** unless WordPress forces them (e.g., `WP_Widget`)
- **NO `stdClass`** for data - use arrays `[]` instead
- **NO dependency injection** - use direct function calls or global `$wpdb`
- **Functions over objects** always

### Data Structure Rules
- **Template data:** `$data['key']` not `$obj->property`
- **Function returns:** arrays only
- **API responses:** arrays only
- **JSON columns:** Store as structured arrays

### Naming Convention
```
{prefix}_{noun}_{verb}()

Examples:
fi_legislator_get()       // Single entity
fi_legislators_list()     // Multiple entities  
fi_vote_create()          // Create operation
fi_session_format()       // Format/transform
fi_reports_search()       // Search operation
```

**Prefixes:**
- Functions: `fi_` (freedom index)
- Constants: `FI_`
- Hooks: `fi_`
- Admin functions: `fi_admin_{area}_{action}()`

### File Organization
```
assets/plugins/freedom-index/
├── core/                 # Data functions (always load first)
│   ├── autoload/         # Functions that hook on init
│   └── {entity}.php      # Data functions by entity
├── public/               # Public-facing
│   ├── autoload/         # AJAX, rewrites, scripts (auto-loaded)
│   └── templates/        # Template partials (on-demand)
├── admin/                # Admin-only
│   ├── autoload/         # Admin hooks (conditional load)
│   └── views/            # Admin page templates
└── freedom-index.php     # Main plugin file
```

**Loading Order:**
1. `core/*.php` - Data functions
2. `core/autoload/*.php` - Core hooks
3. `admin/autoload/*.php` - Admin hooks (conditional)
4. `public/autoload/*.php` - Public hooks

### Template System
```php
// Template loader
function fi_get_template(string $template, array $data = []): void {
    extract($data);
    include FI_PUBLIC_DIR . "templates/{$template}.php";
}

// Template usage
fi_get_template('legislator-card', ['legislator' => $leg, 'gov' => $gov]);

// Template file - ALWAYS arrays
/** @var array $legislator */
<?php echo esc_html($legislator['name']); ?>
```

### Frontend / Bootstrap 5
- **Use Bootstrap 5 utilities whenever possible** for layout, spacing, sizing, and responsive behavior before writing custom CSS.
- Prefer **one markup structure** with responsive utility classes over duplicating mobile/desktop HTML blocks.
- Reserve custom CSS for behavior Bootstrap cannot express (scroll-snap, line-clamp, hidden scrollbars, component-specific active states).

---

## 4. Database Patterns

### Query Structure
```php
function fi_{entity}_list(array $args = []): array {
    global $wpdb;
    
    $args = wp_parse_args($args, [
        'limit' => 24,
        'offset' => 0,
    ]);
    
    // Build WHERE dynamically
    $where = ['1=1'];
    $params = [];
    
    if ($args['gov']) {
        $where[] = 'gov = %s';
        $params[] = $args['gov'];
    }
    
    $sql = "SELECT * FROM {$wpdb->prefix}fi_table 
            WHERE " . implode(' AND ', $where) . "
            LIMIT %d OFFSET %d";
    
    $params[] = $args['limit'];
    $params[] = $args['offset'];
    
    $rows = $wpdb->get_results($wpdb->prepare($sql, $params));
    
    // ALWAYS format results
    return array_map('fi_{entity}_format', $rows);
}
```

### Format Function Pattern
```php
function fi_{entity}_format(object $row): array {
    return [
        'id'   => (int) $row->id,
        'name' => $row->name,
        'url'  => fi_get_{entity}_url((int) $row->id),
        // Build derived data here
    ];
}
```

### Rules
- **Never return raw `$wpdb->get_results()`** - always format
- **Use constants for table names**: `FI_TABLE_NAME` not string concat
- **Cache aggressively** with `fi_cache()`
- **No schema checks on every request** - gate to admin pages

---

## 5. AJAX Patterns

```php
add_action('wp_ajax_fi_{action}', 'fi_ajax_{action}');
add_action('wp_ajax_nopriv_fi_{action}', 'fi_ajax_{action}');

function fi_ajax_{action}(): void {
    check_ajax_referer('fi_ajax_nonce', 'nonce');
    
    // Validate input
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
        wp_send_json_error(['message' => 'Invalid ID']);
    }
    
    // Fetch data using procedural functions
    $data = fi_{entity}_get($id);
    if (!$data) {
        wp_send_json_error(['message' => 'Not found']);
    }
    
    // Return array directly
    wp_send_json_success([
        'data' => $data,
        'html' => fi_get_template_html('partial-name', ['data' => $data]),
    ]);
}
```

---

## 6. Critical WordPress Rules

- **NEVER modify WordPress core files**
- **Treat `assets/plugins/freedom-scorecard` as source of truth**
- **Preserve existing naming/style** unless refactor requires otherwise
- **Run `php -l` on touched PHP files** before reporting completion
- **Coordinate with themes/scorecard** template functions to avoid breaking output

### Admin Gov Scope Rules
- **Current admin gov scope is authoritative** in usermeta: `fs_admin_scope`
- **Do not use raw `GET gov`** as a scope setter
- **Scope changes must be explicit**, nonce-protected actions
- **Use POST/Redirect/GET** for mutations
- **Add cache-busting redirect args** where stale admin pages are a risk
- **Capability check:** Use `FI_CAP_MANAGE` or `manage_options`

---

## 7. Cleanup & Maintenance

### File Prefix Convention
- **Unused files:** Rename to `x.filename.php` (delete after 30 days if no issues)
- **Legacy reference:** Rename to `z.filename.php` (delete after migration)

### Abstraction Test
For any function, ask:
- Called from 0 places? → **Delete it**
- Called from 1 place? → **Inline it**
- Called from 2+ places? → **Keep, but verify it needs to exist**

### "Just In Case" Test
- "We might need this later" → **Delete it**
- "This could be useful for other features" → **Delete it**
- "Someone might want to extend this" → **Delete it**

**Future problems get future solutions. Today's code solves today's problems.**

---

## 8. Verification Checklist

Before marking work complete:

- [ ] No `new stdClass()` - use `[]`
- [ ] No `->` in templates - use `['key']`
- [ ] No class instantiation (unless WordPress requires)
- [ ] All functions prefixed with `fi_`
- [ ] Database results formatted immediately
- [ ] No unused parameters
- [ ] No "bypass" conditionals that never execute
- [ ] `php -l` passes on all modified files
- [ ] No duplicate function names (check with `grep -R "^function fi_"`)

---

## 9. Known Issues & Priorities

### Current TODOs
- PDF routing fails on some legislator list URLs
- Verify slug removal across save functions
- Check old class references (FI\Core, FI\Admin, FI\Public)
- Confirm load order and duplicate function names
- Conditionally load heavy admin files (LegiScan, import, validation)

### High-Risk Areas
1. `/public/ajax/` trait files need procedural conversion
2. Schema checks must not run on every request
3. Admin gov scope caching behavior
4. Image cleanup functions need capability checks

### Performance Priorities
1. Run Query Monitor on key pages
2. Check for duplicate queries (especially `fi_sessions_get_by_gov()`)
3. Verify LegiScan/import files only load when needed
4. Check legislator/session joins aren't running multiple times per page

---

## 10. Project History & Codebase State

- **Origin:** Standalone PHP/Joomla → WP posts/taxonomies → WP multisite (50 states) → Single-site custom DB tables (current)
- **Current data model:** Custom tables, single site, no multisite. This is the authoritative architecture.
- **Previous AI refactors:** Devin Adaptive agent ran multiple sweeping refactors. Codebase quality is **unknown in places** — verify before trusting existing code, especially in `core/`, `public/`, and `admin/`.
- **`z.*` directories** (`z.core/`, `z.public/`, `z.api/`) are legacy reference only — do not use or restore from them.

---

## 11. AI Agent Workflow Rules

### Read Before Writing
- **Before writing any new template or replacing an existing one:** read the file being replaced AND any TEMP/z.* reference file for it. List the features found. Do not write a single line until that inventory is confirmed complete.
- **Never write a replacement from assumptions.** If the reference exists, use it. If it doesn't, ask where the source of truth is.
- **Explicitly confirm scope with the user:** "I found X, Y, Z in the reference. Should all of it be ported?" Wait for the answer.

### Diagnose Before Coding
- **State the problem and the proposed fix first.** Do not write any code until the approach is confirmed.
- **Show your reasoning** in one or two sentences: what is broken, why, and what the fix is.
- **Wait for explicit approval** before implementing anything.
- Exception: If the fix is a single-line, obviously correct, zero-risk change AND it was directly requested, proceed.

### Scope Discipline
- **No sweeping refactors.** Work through the UX flow start-to-finish, fixing issues as encountered.
- **One task at a time.** Complete it, verify it, then move on.
- **If you notice something broken outside the current task:** stop, describe it in one sentence, ask whether to fix it now or log it. Do not touch it until told to.
- **Targeted edits only.** If a fix requires touching more than the immediate task, flag it before proceeding.

### Function Change Protocol
- **Before changing any function** (signature, behavior, name, return type): grep all call sites and report them.
- **Update all call sites** as part of the same change. Do not leave orphaned callers.
- **No compatibility shims.** If the old signature is wrong, fix it everywhere. Do not wrap it.
- **If a call site cannot be found:** say so explicitly. Do not assume it is safe.

### No Defensive Code
- **No `function_exists()`, `class_exists()`, or `isset()` guards** added to hide missing functions or bad structure.
- **No fallback return values** that mask errors (e.g., returning `[]` when a required function is missing).
- **Let breakage surface.** A fatal error on the spot is better than silent wrong behavior buried in the code.

### General
- **Ask one concise question, wait for the answer** before proceeding when scope is unclear.
- **Token discipline.** A confident wrong answer is worse than a brief honest question.
- **Do not refactor healthy code** while fixing something else.

### Diagnosis Hard Stop
- **2-step rule:** If you cannot identify the root cause after reading 2 files or running 2 searches, STOP and ask the user a diagnostic question. Do not keep searching.
- **"Was it working before?"** is always the first question for a regression. The answer immediately narrows scope to what changed.
- **Ask for the console error.** The user's browser console is faster and cheaper than reading 10 files looking for a JS conflict.
- **Ask for the URL/page context** before investigating rendering or template issues — the user can often confirm in one sentence what takes 20 file reads to infer.
- **Never investigate more than 3 possible causes** before stopping to ask. State the candidates and ask the user to confirm which matches what they see.
- **If the feature was working before:** only look at files changed since it worked. Do not audit the entire stack.

---

## 13. User Command Keywords

The user uses ALL-CAPS keywords to set the response mode. Treat these as hard directives:

| Keyword | Meaning | AI behavior |
|---|---|---|
| **DIAGNOSE** | Find and summarize the problem only | Read files, identify root cause, explain it clearly. **No code changes.** Wait for further instruction. |
| **FIXIT** | Find the problem and fix it | Diagnose inline (no separate approval step needed), then implement the fix immediately. |
| **PLAN** | Create a written plan before any code | Produce a step-by-step plan. **No code.** Wait for "execute" or explicit approval. |
| **EXECUTE** | Implement the last approved plan | Follow the plan as written. Ask if anything is ambiguous before starting. |
| **QUESTIONS** | Ask clarifying questions one at a time and wait for the answer before proceeding. | Ask all questions relevant to the next task, before doing anything else. |

### When no keyword is used:
- For **small, obvious, zero-risk** fixes requested in plain language: implement directly.
- For **anything touching multiple files, architectural decisions, or unclear scope**: ask at least 3 clarifying questions before writing a single line of code.
- **When in doubt, ask.** Questions are free. Incorrect code costs tokens and trust.

### Pre-task interrogation
- For any significant change (new feature, refactor, modal port, template rewrite), ask **at least 5–10 targeted questions** before coding, even if the task seems clear.
- Questions should surface: scope boundaries, reference files to consult, variables already in scope, functions that already exist, and what "done" looks like.
- **User prefers being interrogated** over receiving incorrect assumptions baked into code.
- The user is willing to answer 10–25 questions if it prevents a token-burning wrong direction.

### Plan review before execution
- For any change touching 3+ files or involving a new architectural pattern: produce a written plan first.
- The plan must list every file to be changed, what changes, and why.
- **Do not write any code until the user explicitly approves the plan** (says "execute," "looks good," "go ahead," or equivalent).
- Single-file, single-function fixes with an obvious root cause may skip the plan step.

---

## 12. Tool / Workflow Expectations

- **Search before editing** when scope is unclear
- **Prefer targeted edits** over broad rewrites
- **Verify affected dependencies/call sites**
- **Log significant architectural changes** in relevant DEV.DOCS file when requested
- **Do not run destructive commands** without explicit approval

**Summary: ZERO BLOAT. Procedural code. Arrays over objects. Delete before adding. Fix root causes. Minimal changes. Verify everything. No sweeping refactors.**

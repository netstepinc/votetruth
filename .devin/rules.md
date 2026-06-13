# Freedom Index / Vote Scorecard - AI Rules & Guidelines

## Project Context

**Project:** Freedom Index WordPress Plugin + Integrated Theme  
**Location:** `/var/www/html/votestellthetruth/`  
**Core Plugin:** `assets/plugins/freedom-index/`  
**Active Theme:** `assets/themes/truth/`  
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

## 10. Tool / Workflow Expectations

- **Search before editing** when scope is unclear
- **Prefer targeted edits** over broad rewrites
- **Verify affected dependencies/call sites**
- **Log significant architectural changes** in relevant DEV.DOCS file when requested
- **Do not run destructive commands** without explicit approval

---

**Summary: ZERO BLOAT. Procedural code. Arrays over objects. Delete before adding. Fix root causes. Minimal changes. Verify everything.**

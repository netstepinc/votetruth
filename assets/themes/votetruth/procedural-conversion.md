# Procedural Conversion: Agent Reasoning Guide

## Purpose
Enable AI agents to **think through** converting OOP/class-based WordPress architecture to lean procedural code. This doc focuses on **decision-making** (the "why" and "how") rather than prescribing specific file structures.

---

## Core Philosophy: ZERO BLOAT

### The Rules
1. **NO BACKWARD COMPATIBILITY** - This is version 1. Don't preserve dead code.
2. **BREAKING IS FINE** - If something breaks, we'll fix it. Don't build around fear.
3. **LEAN > FLEXIBLE** - Don't design for future use cases that don't exist.
4. **DELETE BEFORE ADDING** - Remove complexity before implementing new solutions.

### The Decision Test
Ask: "Does this serve the immediate need?" If no → Remove. If maybe → Remove.

---

## Fundamental Decisions

### Decision 1: Why Procedural Over OOP?

**Context:** WordPress is procedural at its core. Hooks, filters, and template loading are function-based. OOP adds boilerplate without benefit for purpose-built sites.

**Decision Tree:**
- Does WordPress require a class? (e.g., `WP_Widget`) → Keep class
- Does the code manage state that must persist across requests? → Consider if state is actually needed
- Is there inheritance or complex polymorphism? → You're over-engineering, flatten it
- Is it data access + display logic? → Functions are clearer

**Guideline:** Use functions unless WordPress forces a class on you.

---

### Decision 2: Why Arrays Instead of Objects?

**Context:** Data is just data. Objects add `->` syntax overhead and encourage hidden state. Arrays are explicit, debuggable, and match WordPress's `wp_send_json_*()` expectations.

**Decision Tree:**
- Is this data for a template? → Array
- Is this data for JSON response? → Array  
- Is this a temporary database result before formatting? → Object temporarily OK
- Does this need methods that modify internal state? → You're probably doing it wrong

**Anti-Pattern:**
```php
$pet = new stdClass();        // NO
$pet->name = 'Rex';           // NO
```

**Pattern:**
```php
$pet = ['name' => 'Rex'];     // YES
```

---

### Decision 3: How to Name Things?

**Context:** Consistent naming enables reasoning about code without reading it.

**The Pattern:**
```
{prefix}_{noun}_{verb}()

Examples:
ph_pet_get()          // Single entity, fetch
ph_pets_list()        // Multiple entities, list  
ph_pet_create()       // Single entity, create
ph_pet_format()       // Transform data
ph_pets_search()      // Search operation
```

**Decision Rules:**
- Prefix = Project-specific (2-3 chars) → `ph_`, `fi_`, etc.
- Noun = The entity (singular for single operations, plural for list operations)
- Verb = The action (get, set, list, create, update, delete, format, search, render)
- Hooks = Same prefix: `add_action('ph_init', ...)`

**Why:** 
- `ph_pet_get()` tells you: project, entity, operation
- Alphabetical sorting groups related functions
- Auto-complete friendly in IDE

---

### Decision 4: Where to Put Functions?

**Context:** File location should answer "what kind of code is this?"

**Decision Matrix:**

| If the code... | Put it in... |
|----------------|--------------|
| Runs on every page load (hooks, shortcodes) | `autoload/` directory |
| Only runs when explicitly called | `core/` or `public/` |
| Outputs HTML for display | `templates/` |
| Is theme-specific display logic | Theme's `functions.php` or `template-parts/` |
| Is admin-only | `admin/` |

**The Autoload Principle:**
Files in `autoload/` directories get included automatically on `init`. This eliminates manual require chains and the cognitive overhead of tracking dependencies.

**Why:** Drop a file, it works. Delete a file, it stops. No central registry to maintain.

---

### Decision 5: How to Structure Data Functions?

**Context:** Database queries should be isolated, reusable, and always return usable data.

**The Pattern:**
1. **Accept args array** - Flexibility without parameter bloat
2. **Set defaults** with `wp_parse_args()`
3. **Build query dynamically** - Add WHERE conditions only if args provided
4. **Execute** with `$wpdb->prepare()`
5. **Format results** - Never return raw DB rows
6. **Return arrays**

**Formatting Rule:**
Every data function that queries the database needs a companion format function:
```php
function ph_pet_format(object $row): array {
    return [
        'id'   => (int) $row->id,
        'name' => $row->name,
        'url'  => ph_get_pet_url((int) $row->id),  // Build derived data
    ];
}
```

**Why:** Format functions centralize data transformation. If you need to change how `url` is calculated, you change it in one place.

---

### Decision 6: How to Handle Templates?

**Context:** Templates need data. The question is how to pass it cleanly.

**Decision Tree:**
- Is this template loaded multiple times with different data? → Use template loader with `extract()`
- Is this a one-off view? → Include with explicit variables
- Does this need to work via AJAX? → Template must accept array data

**The Template Loader Pattern:**
```php
function ph_get_template(string $template, array $data = []): void {
    extract($data);  // Array keys become variables
    include PH_DIR . "templates/{$template}.php";
}
```

**Template File Structure:**
```php
<?php
/**
 * Template: Pet Card
 * @var array $pet     - Pet data array
 * @var bool  $compact - Whether to show compact view
 */
if (!defined('ABSPATH')) exit;
// Template markup using $pet['key']
?>
```

**Why `extract()`?**
It decouples the data structure (array) from the template variable syntax. Templates read like `$pet['name']` but the array structure is preserved for type safety and JSON encoding.

---

### Decision 7: How to Convert Classes?

**The Conversion Algorithm:**

For each class:
1. **Extract methods** → Functions with `{prefix}_{noun}_{verb}` naming
2. **Extract properties** → Constants (if static) or function parameters (if variable)
3. **Extract constructor logic** → Inline at start of relevant functions, or delete if not needed
4. **Delete class** → Functions don't need containers

**Conversion Examples:**

**Constructor with dependencies:**
```php
// BEFORE
class PetController {
    private $model;
    public function __construct($model) { $this->model = $model; }
    public function show($id) { return $this->model->get($id); }
}

// AFTER
// Just call the function directly
ph_pet_get($id);
// The "dependency" was unnecessary indirection
```

**Private methods:**
```php
// BEFORE
class PetModel {
    public function get($id) { return $this->format($this->query($id)); }
    private function format($row) { ... }
    private function query($id) { ... }
}

// AFTER
function ph_pet_get($id) { 
    return ph_pet_format(ph_pet_query($id)); 
}
function ph_pet_format($row) { ... }  // Same name pattern, just global
function ph_pet_query($id) { ... }
```

**Static methods:**
```php
// BEFORE
class Utils {
    public static function format_date($date) { ... }
}

// AFTER
function ph_format_date($date) { ... }
// The class was just a namespace. Use prefix instead.
```

---

### Decision 8: How to Handle AJAX?

**Context:** AJAX endpoints need to accept input, validate, fetch data, and return JSON + optionally HTML.

**The Pattern:**
1. **Register hook** with `wp_ajax_{action}` and `wp_ajax_nopriv_{action}`
2. **Verify nonce** with `check_ajax_referer()`
3. **Validate input** - Cast types, check ranges
4. **Fetch data** - Use procedural data functions
5. **Return** - `wp_send_json_success()` or `wp_send_json_error()`

**HTML in AJAX Response:**
If the response needs rendered HTML:
```php
wp_send_json_success([
    'html' => ph_get_template_html('pet-card', ['pet' => $pet]),
]);
```

**Why this matters:**
The template partial is reused - server renders it for initial page load, AJAX returns the same markup for updates. One template, two contexts.

---

### Decision 9: How to Identify What to Delete?

**Context:** Not all code deserves to survive refactoring.

**The Unused File Protocol:**
1. **Search** for the file/class/function name across the entire codebase
2. **If not found** → Rename with `x.` prefix (e.g., `x.old-class.php`)
3. **Wait 30 days** → If no issues, delete
4. **If found but unsure** → Rename with `z.` prefix (e.g., `z.old-class.php`)  
5. **After migration** → Delete `z.` files

**The Abstraction Test:**
For any function/method, ask:
- Is this called from more than 2 places? → Keep and consider if it needs to exist
- Is this called from exactly 1 place? → Inline it
- Is this called from 0 places? → Delete it

**The "Just In Case" Test:**
- "We might need this later" → Delete it
- "This could be useful for other features" → Delete it
- "Someone might want to extend this" → Delete it

**Future problems get future solutions. Today's code solves today's problems.**

---

### Decision 10: How to Verify the Conversion?

**The Checklist:**

Before marking a conversion complete:

- [ ] **No `new stdClass()`** - All data constructed as `[]`
- [ ] **No `->` in templates** - All template data uses `['key']`
- [ ] **No class instantiation** - No `new ClassName()` unless WordPress requires it
- [ ] **All functions prefixed** - Global namespace pollution prevented
- [ ] **Database rows formatted** - No raw `$wpdb->get_results()` passed to templates
- [ ] **Constants for table names** - No string concatenation of table names in queries
- [ ] **No unused parameters** - If a function accepts data it doesn't use, remove it
- [ ] **No "bypass" logic** - If a function has conditionals that never execute, delete them

**The Smell Test:**
If you can't explain what a function does in one sentence, it does too much. Split it.

---

## Architecture Patterns Summary

### The Mental Model
Think of your codebase as a pipeline:

```
User Request → Router/Hook → Data Function → Formatter → Template → Output
```

Each step is a function. Each function does one thing. Data flows as arrays.

### Directory Structure (Flexible)
```
{plugin}/
├── {plugin}.php          # Main file: defines constants, triggers autoload
├── core/                 # Data access functions
│   ├── autoload/         # Functions that hook on init
│   └── {entity}.php      # Data functions grouped by entity
├── public/               # Public-facing
│   ├── autoload/         # Hook handlers (AJAX, rewrites, scripts)
│   └── templates/        # Template partials (only loaded on demand)
└── admin/                # Admin-only
```

**The Rule:**
- `autoload/` = Always loaded (hooks, shortcodes, actions)
- Everything else = Only loaded when explicitly needed

### File Naming
- Core functions: `{entity}.php` (e.g., `pets.php`, `appointments.php`)
- AJAX handlers: `ajax-{feature}.php`
- Template partials: `{feature}-{variant}.php`

---

## Conversion Workflow

### Phase 1: Survey
1. List every class in the codebase
2. For each class, list every method
3. Identify which methods are actually called (grep for `->method_name`)
4. Mark unused methods for deletion
5. Identify class dependencies (what does Class A need from Class B?)

### Phase 2: Convert
1. Pick a class with no dependencies (or the simplest one)
2. Create `{prefix}_{entity}.php` file
3. Convert methods to functions using naming pattern
4. Replace constructor dependencies with direct function calls or global `$wpdb`
5. Add format function that returns array
6. Update all callers to use new functions
7. Update templates to use array syntax

### Phase 3: Test
1. Test the converted feature
2. Fix what breaks
3. Rename old class file to `z.{filename}`

### Phase 4: Repeat
Move to the next class. The pattern becomes mechanical after the first one.

---

## Common Traps to Avoid

### Trap 1: Preserving "Flexibility"
Don't keep abstractions "just in case." If you need it later, write it later. Code written for hypothetical futures is dead code today.

### Trap 2: Over-Engineering Names
Don't create namespaces or complex naming hierarchies. `{prefix}_{noun}_{verb}` is enough.

### Trap 3: Partial Conversions
Don't convert a class but keep returning objects from it. Full conversion = arrays everywhere.

### Trap 4: Utility Classes
If you have a `Utils` or `Helpers` class, those methods probably belong in specific entity files or are unnecessary abstractions.

### Trap 5: Fear of Deletion
Unused code is worse than missing code. Unused code has to be read, understood, and maintained. Delete it.

---

## Quick Decision Reference

| Situation | Decision |
|-----------|----------|
| `class` that only holds data functions | Convert to `{entity}.php` with functions |
| `new stdClass()` | Replace with `[]` |
| `private` method | Make it a global function with prefix |
| `static` method | Make it a global function with prefix |
| Constructor dependency | Inline the dependency or use global `$wpdb` |
| Template using `$obj->prop` | Change to `$arr['key']` |
| Class with 1 method | Just the function, no class needed |
| `Utils` class | Distribute methods to relevant entity files |
| Uncalled method | Delete it |
| Code "for future use" | Delete it |

---

## The Core Insight

**OOP is not inherently better. It's a tool for specific problems (state management, inheritance, polymorphism). Most WordPress sites don't have those problems. They have data in, data out, display. Functions solve that perfectly without the overhead.**

The procedural approach removes:
- Constructor boilerplate
- `$this->` syntax noise  
- Dependency injection complexity
- Class file overhead
- Object instantiation cost

And gains:
- Immediate function availability
- Clear data flow (arrays pass through)
- Simpler debugging (no object state to track)
- Less code to read and maintain

**ZERO BLOAT means every line earns its place. If it doesn't serve the immediate need, it's gone.**

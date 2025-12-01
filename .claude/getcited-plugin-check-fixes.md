# GetCited Plugin Check Fix Plan

## Summary
- **Total Issues**: ~100 line items across 5 categories
- **Blocking Issues** (ERROR): Must fix before submission
- **Non-Blocking** (WARNING): Should fix but won't prevent approval

---

## Phase 1: Quick Wins (5 minutes)

### 1.1 readme.txt — 2 issues

**File**: `readme.txt`

**Fix 1**: Update tested version (line 5)
```diff
- Tested up to: 6.4
+ Tested up to: 6.8
```

**Fix 2**: Reduce tags from 9 to 5 (line 3)
```diff
- Tags: ai, seo, chatgpt, claude, perplexity, llms.txt, schema, ai search, gaeo
+ Tags: ai, chatgpt, claude, perplexity, llms.txt
```
*Rationale*: Keep the most searchable/relevant terms. "seo", "schema", "ai search", "gaeo" are lower value.

---

### 1.2 getcited.php — Remove load_plugin_textdomain()

**File**: `getcited.php` (lines 178-187)

**Current**:
```php
/**
 * Load plugin textdomain
 */
public function load_textdomain() {
    load_plugin_textdomain(
        'getcited',
        false,
        dirname( GETCITED_PLUGIN_BASENAME ) . '/languages'
    );
}
```

**Fix**: Delete the entire method AND remove the hook that calls it.

Find and remove the hook (likely in `__construct` or `init`):
```php
add_action( 'init', array( $this, 'load_textdomain' ) );
// or
add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
```

---

## Phase 2: Template Global Variables (70+ errors, 10 minutes)

### Strategy: IIFE Wrapper

Wrap each template's entire contents in an immediately-invoked function. This scopes all variables locally without renaming anything.

**Affected Files** (7 templates):
- `crawlers.php`
- `dashboard.php` 
- `schema.php`
- `citability.php`
- `llms-txt.php`
- `settings.php`
- `wizard.php`

### Template Pattern

**Before**:
```php
<?php
/**
 * Template description
 * @package GetCited
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = GetCited_Settings::instance();
$crawler_list = GetCited_Crawler_List::instance();
// ... rest of template
?>
<div class="wrap">
    <!-- HTML here -->
</div>
```

**After**:
```php
<?php
/**
 * Template description
 * @package GetCited
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

( function() {
    $settings = GetCited_Settings::instance();
    $crawler_list = GetCited_Crawler_List::instance();
    // ... rest of template
?>
<div class="wrap">
    <!-- HTML here -->
</div>
<?php
} )();
```

### File-by-File Changes

#### crawlers.php
Lines 13-147: Wrap in IIFE
```php
// After line 11 (exit;), add:
( function() {

// Before closing ?> at end, add:
} )();
```

#### dashboard.php
Lines 13-end: Wrap in IIFE

#### schema.php  
Lines 13-end: Wrap in IIFE

#### citability.php
Lines 13-end: Wrap in IIFE

#### llms-txt.php
Lines 13-end: Wrap in IIFE

#### settings.php
Lines 13-end: Wrap in IIFE

#### wizard.php
Lines 13-end: Wrap in IIFE

---

## Phase 3: Translator Comments (23 errors)

### Pattern
Add `/* translators: ... */` comment on the line BEFORE the i18n function call.
Also fix unordered placeholders (`%s, %s` → `%1$s, %2$s`).

### 3.1 crawlers.php (line 43)

**Current**:
```php
<?php printf(
    esc_html__( 'Crawler list v%s (updated %s)', 'getcited' ),
    esc_html( $list_version ),
    esc_html( $list_updated )
); ?>
```

**Fixed**:
```php
<?php
/* translators: %1$s: version number, %2$s: last updated date */
printf(
    esc_html__( 'Crawler list v%1$s (updated %2$s)', 'getcited' ),
    esc_html( $list_version ),
    esc_html( $list_updated )
);
?>
```

### 3.2 schema.php (line 36)

Find the placeholder string and add translator comment above it.

### 3.3 citability.php (line 103)

**Current**:
```php
<?php printf(
    esc_html__( '%d posts available', 'getcited' ),
    wp_count_posts()->publish
); ?>
```

**Fixed**:
```php
<?php
/* translators: %d: number of published posts */
printf(
    esc_html__( '%d posts available', 'getcited' ),
    absint( wp_count_posts()->publish )
);
?>
```
*Note*: Also wraps in `absint()` to fix the escaping issue at line 104.

### 3.4 llms-txt.php (line 45)

Add translator comment above the placeholder string.

### 3.5 class-health-check.php (6 locations)

**Line 140**:
```php
// Before:
'message' => sprintf( __( 'HTTP %d response', 'getcited' ), $code ),

// After:
/* translators: %d: HTTP response code */
'message' => sprintf( __( 'HTTP %d response', 'getcited' ), $code ),
```

**Line 185**: Add translator comment

**Line 234**: Add translator comment

**Line 254**: Add translator comment

**Line 303** (also needs ordered placeholders):
```php
// Before:
__( 'Crawler list v%s (updated %s)', 'getcited' )

// After:
/* translators: %1$s: version number, %2$s: last updated date */
__( 'Crawler list v%1$s (updated %2$s)', 'getcited' )
```

### 3.6 class-citability.php (12 locations)

Lines: 193, 515, 521, 527, 534, 693, 700, 728, 734, 769, 775

Each needs a translator comment above the function call explaining the placeholder.

### 3.7 class-pro-teaser.php (line 346)

**Current**:
```php
<?php printf(
    esc_html__( '%s requires GetCited Pro.', 'getcited' ),
    esc_html( $feature_name )
); ?>
```

**Fixed**:
```php
<?php
/* translators: %s: feature name */
printf(
    esc_html__( '%s requires GetCited Pro.', 'getcited' ),
    esc_html( $feature_name )
);
?>
```

---

## Phase 4: Input Sanitization — wp_unslash() (15 warnings)

### Pattern
Wrap `$_POST`, `$_GET`, `$_SERVER` in `wp_unslash()` BEFORE sanitization.

### 4.1 class-dashboard.php

**Line 100**:
```php
// Before:
$section = isset( $_POST['section'] ) ? sanitize_text_field( $_POST['section'] ) : '';

// After:
$section = isset( $_POST['section'] ) ? sanitize_text_field( wp_unslash( $_POST['section'] ) ) : '';
```

**Line 101**:
```php
// Before:
$data = isset( $_POST['data'] ) ? $_POST['data'] : array();

// After:
$data = isset( $_POST['data'] ) ? map_deep( wp_unslash( $_POST['data'] ), 'sanitize_text_field' ) : array();
```
*Note*: `map_deep()` recursively sanitizes arrays.

**Line 215**:
```php
// Before:
'Server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',

// After:
'Server' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown',
```

### 4.2 class-pro-teaser.php (line 271)

```php
// Before:
$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';

// After:
$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
```

### 4.3 class-wizard.php

**Lines 71-72** (redirect check):
```php
// Before:
if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'getcited' ) !== false ) {

// After:
if ( isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'getcited' ) !== false ) {
```

**Line 84**:
```php
// Before:
return isset( $_GET['wizard'] ) && $_GET['wizard'] === '1';

// After:
return isset( $_GET['wizard'] ) && sanitize_text_field( wp_unslash( $_GET['wizard'] ) ) === '1';
```

**Line 225**:
```php
// Before:
$step = isset( $_POST['step'] ) ? sanitize_text_field( $_POST['step'] ) : '';

// After:
$step = isset( $_POST['step'] ) ? sanitize_text_field( wp_unslash( $_POST['step'] ) ) : '';
```

**Line 226**:
```php
// Before:
$data = isset( $_POST['data'] ) ? $_POST['data'] : array();

// After:
$data = isset( $_POST['data'] ) ? map_deep( wp_unslash( $_POST['data'] ), 'sanitize_text_field' ) : array();
```

---

## Phase 5: Output Escaping (3 errors)

### 5.1 class-schema.php (line 194)

This is JSON-LD output. `wp_json_encode()` is safe for this context.

**Option A**: Add phpcs:ignore comment (preferred for JSON-LD):
```php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD requires unescaped JSON
echo $json . "\n";
```

**Option B**: Safer alternative:
```php
echo wp_kses( $json, array() ) . "\n";
```

### 5.2 class-llms-txt.php (line 95)

This is plain text output for llms.txt file. Content should NOT be HTML-escaped.

```php
// Before:
echo $content;

// After:
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text file output, not HTML
echo $content;
```

### 5.3 templates/citability.php (line 104)

**Current**:
```php
wp_count_posts()->publish
```

**Fixed**:
```php
absint( wp_count_posts()->publish )
```

Already addressed in Phase 3 with translator comment fix.

---

## Phase 6: Nonce Verification (6 warnings)

### 6.1 class-llms-txt.php (line 64) — SKIP

This is a public endpoint (`?llms-txt=1`) that serves llms.txt content. No nonce needed.

Add comment to document this:
```php
// Note: No nonce check needed - this is a public endpoint like robots.txt
if ( isset( $_GET['llms-txt'] ) && sanitize_text_field( wp_unslash( $_GET['llms-txt'] ) ) === '1' ) {
```

### 6.2 class-wizard.php (lines 71, 84) — SKIP

These are admin page redirects, not form submissions. The WordPress admin area itself provides protection.

Add comment:
```php
// Note: No nonce needed - this is an admin redirect check, not a form submission
```

---

## Phase 7: Miscellaneous Fixes

### 7.1 uninstall.php — Direct DB Queries (lines 49, 56, 68)

Direct queries are acceptable during uninstall for bulk cleanup. Add phpcs:ignore comments:

```php
// Line 49:
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup requires direct query
$wpdb->query(
    "DELETE FROM {$wpdb->options} ..."
);

// Line 56:
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup requires direct query
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta} ..."
);

// Line 68:
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup requires direct query
$wpdb->query(
    "DELETE FROM {$wpdb->usermeta} ..."
);
```

### 7.2 class-crawler-list.php (line 116) — error_log

Already guarded with `WP_DEBUG`. Add phpcs:ignore for extra safety:

```php
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is true
    error_log( '[GetCited] Failed to fetch remote crawler list: ' . $response->get_error_message() );
}
```

---

## Implementation Checklist

### Blocking (Must Fix)

- [ ] readme.txt: Tested up to → 6.8
- [ ] readme.txt: Reduce to 5 tags
- [ ] getcited.php: Remove load_plugin_textdomain() and its hook
- [ ] 7 templates: Add IIFE wrapper (fixes all 70 global variable errors)
- [ ] Add all 23 translator comments
- [ ] Fix 2 ordered placeholder strings (`%s, %s` → `%1$s, %2$s`)
- [ ] citability.php: Fix wp_count_posts escaping

### Non-Blocking (Should Fix)

- [ ] class-dashboard.php: Add wp_unslash() to 3 locations
- [ ] class-pro-teaser.php: Add wp_unslash() to email
- [ ] class-wizard.php: Add wp_unslash() to 4 locations  
- [ ] class-schema.php: Add phpcs:ignore for JSON-LD output
- [ ] class-llms-txt.php: Add phpcs:ignore for plain text output
- [ ] uninstall.php: Add phpcs:ignore to 3 DB queries
- [ ] class-crawler-list.php: Add phpcs:ignore to error_log

---

## Testing After Fixes

1. Run Plugin Check again to verify zero errors
2. Test wizard flow
3. Test crawler toggle saves
4. Test llms.txt endpoint
5. Test schema output
6. Test citability audit
7. Verify translations still work

---

## Estimated Time

| Phase | Time |
|-------|------|
| Phase 1: Quick wins | 5 min |
| Phase 2: IIFE wrappers | 10 min |
| Phase 3: Translator comments | 15 min |
| Phase 4: wp_unslash | 10 min |
| Phase 5: Output escaping | 5 min |
| Phase 6: Nonce docs | 2 min |
| Phase 7: phpcs:ignore | 5 min |
| **Total** | **~50 min** |

---

## Files to Provide

To implement these fixes, I'll need to see all PHP files. Based on the project, these are:

**Class files** (in includes/):
- [x] class-citability.php
- [x] class-cli.php
- [x] class-crawler-list.php
- [x] class-dashboard.php
- [x] class-health-check.php
- [x] class-llms-txt.php
- [x] class-pro-teaser.php
- [x] class-robots.php
- [x] class-schema.php
- [x] class-settings.php
- [x] class-wizard.php

**Template files** (in templates/):
- [x] citability.php
- [x] crawlers.php
- [x] dashboard.php
- [x] llms-txt.php
- [x] schema.php
- [x] settings.php
- [x] wizard.php

**Root files**:
- [x] getcited.php
- [x] uninstall.php
- [x] readme.txt

All files are visible in the project. Ready to implement fixes!

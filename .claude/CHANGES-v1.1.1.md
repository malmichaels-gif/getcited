# GetCited v1.1.1 — Bug Fixes

**Created:** December 1, 2025
**Status:** Ready for Testing

---

## Summary

This release fixes several critical bugs introduced in v1.1.0:

1. **Wizard hanging on Step 2** - Synchronous site scan was blocking AJAX response
2. **Health Check expand buttons not working** - JavaScript/DOM mismatch
3. **Custom Crawlers not saving** - Missing Save button and handler

---

## Changes

### 1. Wizard Site Scan Made Asynchronous

**Problem:** When users clicked "Continue" on Step 2 (Site Type), the wizard would hang because the site scan was running synchronously inside the AJAX handler, blocking the response for 10-30+ seconds.

**Solution:**
- Split the wizard step save and site scan into separate operations
- Step save returns immediately after storing the site type
- Scan runs as a separate AJAX call with progress UI
- Added 30-second timeout with "Skip scan" option
- Scan failures don't block wizard completion

**Files Changed:**
- `includes/class-wizard.php` - Added `ajax_run_scan()` handler, removed sync scan from `ajax_save_step()`
- `assets/js/admin.js` - Rewrote wizard JS with simpler callback-based approach, separate `saveWizardStepWithScan()` function
- `templates/wizard.php` - Added scan progress bar UI to Step 2
- `assets/css/admin.css` - Added progress bar styling

### 2. Site Scanner Query Optimization

**Problem:** Category and tag queries had no limits, causing slow scans on large sites.

**Solution:**
- Added `number => 20` limit to `get_categories()`
- Optimized `get_key_pages()` to use single batch query instead of 17 individual `get_page_by_path()` calls
- Fixed strict equality comparison for menu parent check

**File Changed:**
- `includes/class-site-scanner.php`

### 3. Extended Wizard Transient TTL

**Problem:** Scan data transient had 1-hour TTL which could expire before wizard completion.

**Solution:**
- Changed from `HOUR_IN_SECONDS` to `DAY_IN_SECONDS` (24 hours)

**File Changed:**
- `includes/class-wizard.php`

### 4. Added Scan Rate Limiting

**Problem:** No protection against rapid successive scan requests.

**Solution:**
- Added 60-second rate limiting via transient
- Returns cached results if scan was run recently

**File Changed:**
- `includes/class-wizard.php`

### 5. Health Check Expand Buttons Fixed

**Problem:** The expand/collapse buttons on the dashboard Health Check section didn't work. The details divs were conditionally rendered as siblings, but JavaScript expected them nested.

**Solution:**
- Restructured HTML in `templates/dashboard.php` to nest `.getcited-health-details` inside `.getcited-health-item`
- Updated JavaScript to use `item.querySelector('.getcited-health-details')` instead of sibling traversal
- Updated CSS for nested structure

**Files Changed:**
- `templates/dashboard.php` - Details now nested inside health item
- `assets/js/admin.js` - Simplified expand button handler
- `assets/css/admin.css` - Adjusted styling for nested structure

### 6. Custom Crawlers Save Functionality

**Problem:** The AI Crawlers page had no "Save Changes" button. Custom crawlers added via the form were never persisted.

**Solution:**
- Added "Save Changes" button to `templates/crawlers.php`
- Added save handler in `admin.js` that collects both standard crawler toggles and custom crawler form data
- Updated bulk actions to preserve custom crawlers when doing "Allow All"/"Block All"
- Backend handler already supported `custom_crawlers` - just needed frontend to send it

**Files Changed:**
- `templates/crawlers.php` - Added Save button with status indicator
- `assets/js/admin.js` - Added `saveBtn` handler in `initCustomCrawlers()`

### 7. JavaScript Modernization for Browser Compatibility

**Problem:** Arrow functions and template literals could cause issues in older environments.

**Solution:**
- Rewrote wizard JavaScript to use regular functions and string concatenation
- Simplified Promise handling to use callbacks where appropriate
- Fixed variable shadowing issues (`const btn` inside click handler)

**File Changed:**
- `assets/js/admin.js`

### 8. Wizard Step Content Not Displaying

**Problem:** Wizard step content would briefly appear then disappear, leaving only the progress bar visible. The JavaScript was hiding all steps but then failing to make the current step visible.

**Solution:**
- Added explicit CSS rules for wizard step visibility
- Changed wizard container from `overflow: hidden` to `overflow: visible`
- Added `min-height` to wizard container to ensure content area
- Pre-cached step elements in JavaScript for more reliable DOM queries
- Added explicit `visibility` and `opacity` styles when showing steps
- Changed arrow functions to regular functions for older browser support

**Files Changed:**
- `assets/css/admin.css` - Fixed step display rules, wizard container overflow
- `assets/js/admin.js` - Improved showStep function reliability

### 9. llms.txt Shortcode/Base64 Content Leaking

**Problem:** Sites using page builders (e.g., TagDiv Composer) had shortcode content and base64-encoded data appearing in the generated llms.txt "About" sections.

**Solution:**
- Added `strip_shortcodes()` call before processing content
- Added regex to remove remaining bracket patterns `\[[^\]]*\]`
- Added regex to remove base64-encoded content `[A-Za-z0-9+\/=]{50,}`
- Updated both `get_content_preview()` and `get_clean_excerpt()` methods
- Filter out TagDiv internal post types (`tdc-locker`, `tdc-email`, `tdc-cloud-templates`)

**File Changed:**
- `includes/class-site-scanner.php`

### 10. Social Link Detection from Menus/Widgets

**Problem:** Site scanner didn't find social links that were placed in navigation menus or widget areas.

**Solution:**
- Added `scan_menus_for_social()` method to scan all nav menu items
- Added `scan_widgets_for_social()` method to scan text/custom HTML widgets
- Added support for TagDiv theme options (`td_options` social URLs)
- Social link detection now checks multiple sources in order

**File Changed:**
- `includes/class-site-scanner.php`

### 11. Wizard Design Improvements

**Problem:** At 110% browser zoom, wizard text and buttons were too small for comfortable reading.

**Solution:**
- Increased heading font size from 28px to 32px
- Increased subtitle font size from 16px to 18px
- Increased wizard icon size from 80px to 100px
- Increased site type card padding and icon sizes
- Improved button visibility with larger padding

**File Changed:**
- `assets/css/admin.css`

### 12. Completion Screen Button Layout

**Problem:** "Edit llms.txt" button was positioned awkwardly next to the primary CTA.

**Solution:**
- Changed from horizontal to stacked vertical layout
- Primary "Go to Dashboard" button is now prominent
- Secondary "edit your llms.txt first" is a text link below
- Added `.wizard-actions-stacked` class for this layout

**Files Changed:**
- `templates/wizard.php` - New stacked layout structure
- `assets/css/admin.css` - Added stacked layout styles

### 13. WordPress Plugin Check Compliance (is_writable)

**Problem:** Plugin Check flagged direct `is_writable()` calls which don't comply with WordPress coding standards. WordPress requires using `WP_Filesystem` methods for all file operations.

**Solution:**
- Replaced `is_writable()` with `$wp_filesystem->is_writable()` in all affected files
- Added proper WP_Filesystem initialization before checks
- Updated `uninstall.php`, `class-conflict-detector.php`, and `class-robots.php`

**Files Changed:**
- `uninstall.php` - Use WP_Filesystem for robots.txt cleanup
- `includes/class-conflict-detector.php` - Added `check_robots_txt_writable()` helper method
- `includes/class-robots.php` - Updated `can_write_physical_file()` and `remove_rules_from_physical_file()`

### 14. Wizard Step 5 llms.txt Preview Not Showing

**Problem:** The wizard completion screen (Step 5) would show the generic "Your site is now optimized" summary instead of the llms.txt preview with stats. This happened because the PHP template is rendered at page load BEFORE the async scan runs.

**Root Cause:**
```php
// In templates/wizard.php (executed at page load)
$wizard_scan = $wizard->get_scan_data();  // Returns false - no transient yet
$has_scan    = $wizard_scan && ! empty( $wizard_scan['llms_txt'] );  // false
```

The async scan runs AFTER page load via AJAX, storing results in a transient. But by then, `$has_scan` is already false from the initial render.

**Solution:**
- Added `populateWizardStep5()` function in JavaScript
- Captures scan response data when AJAX completes
- Dynamically creates/populates the preview container, stats, and secondary action link
- Hides the generic summary and shows the scan results
- Added `adminUrl` to localized script for proper link generation

**Files Changed:**
- `assets/js/admin.js` - Added `populateWizardStep5()`, updated scan handler to capture response
- `getcited.php` - Added `adminUrl` to localized script

---

## Testing Checklist

### Wizard
- [ ] Fresh activation shows wizard starting at Step 1 (Welcome)
- [ ] "Let's Go" button advances to Step 2 (Site Type)
- [ ] Selecting a site type and clicking "Continue" shows progress bar
- [ ] Progress bar animates with rotating status text
- [ ] "Skip scan" link appears and works
- [ ] After 30 seconds, "Skip scan" becomes prominent
- [ ] Scan completes and advances to Step 3 (Organization)
- [ ] Can navigate through all steps to completion
- [ ] Dashboard page shows correctly after wizard completion
- [ ] Settings page → "Run Setup Wizard" works

### Health Check (Dashboard)
- [ ] Expand buttons appear on items with details
- [ ] Clicking expand button shows details panel
- [ ] Clicking again collapses the panel
- [ ] Arrow icon rotates on expand/collapse
- [ ] "Run Check" button works

### AI Crawlers Page
- [ ] "Save Changes" button visible
- [ ] Toggling individual crawlers works
- [ ] "Allow All" / "Block All" buttons work
- [ ] Adding custom crawler works
- [ ] Removing custom crawler works
- [ ] Custom crawlers persist after page reload
- [ ] robots.txt preview updates after save

### llms.txt Editor
- [ ] "Scan My Site" button works
- [ ] "Save Changes" button works
- [ ] Template buttons work

---

## Files Modified

| File | Lines Changed | Description |
|------|--------------|-------------|
| `includes/class-wizard.php` | ~30 | Added async scan handler, rate limiting, extended TTL |
| `includes/class-site-scanner.php` | ~100 | Query optimization, shortcode/base64 stripping, social link detection from menus/widgets |
| `includes/class-dashboard.php` | 0 | No changes needed - already handled custom_crawlers |
| `includes/class-conflict-detector.php` | ~25 | Added `check_robots_txt_writable()` helper using WP_Filesystem |
| `includes/class-robots.php` | ~30 | Updated to use WP_Filesystem for `is_writable()` checks |
| `assets/js/admin.js` | ~250 | Rewrote wizard JS, health expand fix, crawler save, step display fix |
| `assets/css/admin.css` | ~80 | Wizard design improvements, step visibility, stacked button layout |
| `templates/wizard.php` | ~20 | Progress bar, stacked button layout for completion screen |
| `templates/dashboard.php` | ~30 | Restructured health item HTML |
| `templates/crawlers.php` | ~10 | Already had Save button from earlier |
| `uninstall.php` | ~15 | Updated to use WP_Filesystem for robots.txt cleanup |
| `getcited.php` | 2 | Version bump to 1.1.1 |

---

*End of document.*

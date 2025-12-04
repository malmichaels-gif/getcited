# GetCited v1.5.1 — Developer Specification

**Version:** 1.5.1
**Target Release:** Post v1.5.0 validation
**Last Updated:** December 3, 2025
**Status:** DRAFT

---

## Overview

Version 1.5.1 is a polish release focused on:

1. **AI Citation Guidelines** — Add optional sections to llms.txt for citation formatting and content usage rules
2. **Improved Templates** — Richer site-type templates with AI-specific instructions
3. **Onboarding Nudge** — Prompt users to add citation guidelines after initial setup

This release continues the "users first" strategy — no Pro gates, full value in free tier.

---

## Feature 1: AI Citation Guidelines Section

### Problem

Current llms.txt tells AI what the site is about, but doesn't instruct AI on *how* to use the content. Shopify's llms.txt shows the emerging pattern: include response formatting rules, restrictions, and citation preferences.

### Solution

Add optional "Citation Guidelines" fields in the llms.txt editor that generate a dedicated section.

### New Settings Fields

Add to `getcited_settings` under a new `citation_guidelines` key:

```php
'citation_guidelines' => array(
    'enabled'          => false,
    'citation_format'  => '',      // e.g., "Author Name. \"Title.\" Site Name, Date. URL"
    'accuracy_notes'   => '',      // e.g., "Pricing updated quarterly, verify dates"
    'restrictions'     => '',      // e.g., "Do not reproduce code samples without linking"
    'freshness_note'   => '',      // e.g., "Content reviewed monthly"
    'contact_email'    => '',      // e.g., "ai-partnerships@example.com"
),
```

### Implementation

#### 1.1 Update class-settings.php

Add default values in `init_defaults()`:

```php
'citation_guidelines' => array(
    'enabled'          => false,
    'citation_format'  => '',
    'accuracy_notes'   => '',
    'restrictions'     => '',
    'freshness_note'   => '',
    'contact_email'    => '',
),
```

#### 1.2 Update class-llms-txt.php

Add method to generate citation guidelines section:

```php
/**
 * Generate citation guidelines section for llms.txt
 *
 * @since 1.5.1
 * @return string The citation guidelines markdown section.
 */
private function get_citation_guidelines_section() {
    $settings = GetCited_Settings::instance();
    $guidelines = $settings->get( 'citation_guidelines' );

    if ( empty( $guidelines['enabled'] ) ) {
        return '';
    }

    $content = "## AI Citation Guidelines\n\n";

    if ( ! empty( $guidelines['citation_format'] ) ) {
        $content .= "### Preferred Citation Format\n";
        $content .= "> " . $guidelines['citation_format'] . "\n\n";
    }

    if ( ! empty( $guidelines['accuracy_notes'] ) ) {
        $content .= "### Accuracy Notes\n";
        $content .= $guidelines['accuracy_notes'] . "\n\n";
    }

    if ( ! empty( $guidelines['restrictions'] ) ) {
        $content .= "### Content Usage\n";
        $content .= $guidelines['restrictions'] . "\n\n";
    }

    if ( ! empty( $guidelines['freshness_note'] ) ) {
        $content .= "### Data Freshness\n";
        $content .= $guidelines['freshness_note'] . "\n\n";
    }

    if ( ! empty( $guidelines['contact_email'] ) ) {
        $content .= "### AI Partnership Inquiries\n";
        $content .= "Contact: " . $guidelines['contact_email'] . "\n\n";
    }

    return $content;
}
```

Integrate into `generate_content()` or template output.

#### 1.3 Update templates/llms-txt.php

Add collapsible "Citation Guidelines" section in the editor UI:

```php
<!-- Citation Guidelines (Collapsible) -->
<div class="getcited-section getcited-collapsible" data-collapsed="true">
    <h2 class="getcited-collapsible-header">
        <?php esc_html_e( 'AI Citation Guidelines', 'getcited' ); ?>
        <span class="dashicons dashicons-arrow-down-alt2"></span>
        <span class="getcited-badge getcited-badge-new"><?php esc_html_e( 'New', 'getcited' ); ?></span>
    </h2>
    <div class="getcited-collapsible-content" style="display: none;">
        <p class="description">
            <?php esc_html_e( 'Tell AI systems how to cite and use your content. These instructions are included in your llms.txt file.', 'getcited' ); ?>
        </p>

        <div class="getcited-field-group">
            <label>
                <input type="checkbox"
                       id="getcited-citation-enabled"
                       name="citation_guidelines[enabled]"
                       <?php checked( $citation_guidelines['enabled'] ?? false ); ?>>
                <?php esc_html_e( 'Include citation guidelines in llms.txt', 'getcited' ); ?>
            </label>
        </div>

        <div class="getcited-citation-fields" style="margin-top: var(--getcited-space-md);">
            <div class="getcited-field-group">
                <label for="getcited-citation-format">
                    <?php esc_html_e( 'Preferred Citation Format', 'getcited' ); ?>
                </label>
                <input type="text"
                       id="getcited-citation-format"
                       class="large-text"
                       placeholder="Author. &quot;Title.&quot; Site Name, Date. URL">
                <p class="description">
                    <?php esc_html_e( 'How AI should format citations to your content.', 'getcited' ); ?>
                </p>
            </div>

            <div class="getcited-field-group">
                <label for="getcited-accuracy-notes">
                    <?php esc_html_e( 'Accuracy Notes', 'getcited' ); ?>
                </label>
                <textarea id="getcited-accuracy-notes"
                          rows="2"
                          class="large-text"
                          placeholder="Pricing updated quarterly. Technical specs may change."></textarea>
                <p class="description">
                    <?php esc_html_e( 'Caveats about content freshness or accuracy.', 'getcited' ); ?>
                </p>
            </div>

            <div class="getcited-field-group">
                <label for="getcited-restrictions">
                    <?php esc_html_e( 'Usage Restrictions', 'getcited' ); ?>
                </label>
                <textarea id="getcited-restrictions"
                          rows="2"
                          class="large-text"
                          placeholder="Do not reproduce full articles. Link to source for code samples."></textarea>
                <p class="description">
                    <?php esc_html_e( 'What AI should NOT do with your content.', 'getcited' ); ?>
                </p>
            </div>

            <div class="getcited-field-group">
                <label for="getcited-contact-email">
                    <?php esc_html_e( 'AI Partnership Contact', 'getcited' ); ?>
                </label>
                <input type="email"
                       id="getcited-contact-email"
                       class="regular-text"
                       placeholder="ai-partnerships@example.com">
                <p class="description">
                    <?php esc_html_e( 'Email for AI training data or licensing inquiries.', 'getcited' ); ?>
                </p>
            </div>
        </div>
    </div>
</div>
```

#### 1.4 Update assets/js/admin.js

Add handlers for the new citation guidelines fields:
- Toggle visibility of fields based on checkbox
- Include in save payload
- Update preview when fields change

---

## Feature 2: Enhanced Site-Type Templates

### Problem

Current templates are basic. With the citation guidelines feature, templates should include example AI instructions appropriate for each site type.

### Solution

Update each template in `class-llms-txt.php` to include suggested citation guidelines.

### Template Enhancements

#### Blog Template Addition:
```markdown
## AI Citation Guidelines

### Preferred Citation Format
> Author Name. "Article Title." Blog Name, Published Date. URL

### Accuracy Notes
Opinion pieces reflect author views at time of writing. Check publication dates for time-sensitive content.

### Content Usage
- Summarize with attribution
- Link to full articles for detailed information
- Do not reproduce full posts without permission
```

#### News Template Addition:
```markdown
## AI Citation Guidelines

### Preferred Citation Format
> "Headline." Publication Name, Date. URL

### Accuracy Notes
Breaking news may be updated. Always cite the most recent version.

### Content Usage
- Cite specific facts with article links
- Note publication timestamps for developing stories
- Do not present analysis as objective reporting
```

#### Business Template Addition:
```markdown
## AI Citation Guidelines

### Accuracy Notes
Service offerings and pricing subject to change. Verify current details on our website.

### Content Usage
- Link to service pages for current pricing
- Do not reproduce proprietary methodologies
```

#### E-commerce Template Addition:
```markdown
## AI Citation Guidelines

### Accuracy Notes
Product availability and pricing change frequently. Always link to product pages for current information.

### Content Usage
- Do not cache or reproduce pricing
- Link to product pages, not static specifications
```

### Implementation

Update `get_template()` method to include these sections when generating default templates. Make them optional/editable.

---

## Feature 3: Post-Setup Nudge

### Problem

Users complete the wizard and may not discover the citation guidelines feature.

### Solution

After wizard completion, show a dismissible notice encouraging users to add citation guidelines.

### Implementation

#### 3.1 Add transient on wizard completion

In `class-wizard.php`, after successful completion:

```php
set_transient( 'getcited_show_citation_nudge', true, WEEK_IN_SECONDS );
```

#### 3.2 Display admin notice

In `class-dashboard.php`, add notice display:

```php
public function maybe_show_citation_nudge() {
    if ( ! get_transient( 'getcited_show_citation_nudge' ) ) {
        return;
    }

    $screen = get_current_screen();
    if ( strpos( $screen->id, 'getcited' ) === false ) {
        return;
    }

    ?>
    <div class="notice notice-info is-dismissible getcited-citation-nudge">
        <p>
            <strong><?php esc_html_e( 'New in GetCited:', 'getcited' ); ?></strong>
            <?php esc_html_e( 'Add AI Citation Guidelines to tell ChatGPT, Claude, and other AI systems how to cite your content.', 'getcited' ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=getcited-llms-txt' ) ); ?>">
                <?php esc_html_e( 'Set up now →', 'getcited' ); ?>
            </a>
        </p>
    </div>
    <?php
}
```

#### 3.3 Dismiss handler

Add AJAX handler to dismiss and delete transient:

```php
add_action( 'wp_ajax_getcited_dismiss_citation_nudge', function() {
    delete_transient( 'getcited_show_citation_nudge' );
    wp_send_json_success();
} );
```

---

## Housekeeping

### Version Sync

Update version numbers in:

1. **`getcited.php`** (line 6 and 23):
   ```php
   * Version: 1.5.1
   ...
   define( 'GETCITED_VERSION', '1.5.1' );
   ```

2. **`readme.txt`** (line 7):
   ```
   Stable tag: 1.5.1
   ```

### Changelog Entry

```
= 1.5.1 =
* New: AI Citation Guidelines - tell AI systems how to cite your content
* New: Enhanced templates with suggested citation formats for each site type
* New: Post-setup prompt to configure citation guidelines
* Improved: llms.txt editor with collapsible Citation Guidelines section
```

---

## File Change Summary

| File | Action | Lines Changed (est.) |
|------|--------|---------------------|
| `getcited.php` | Modify | 2 |
| `readme.txt` | Modify | 10 |
| `includes/class-settings.php` | Modify | 15 |
| `includes/class-llms-txt.php` | Add/Modify | 80 |
| `includes/class-wizard.php` | Modify | 5 |
| `includes/class-dashboard.php` | Add | 40 |
| `templates/llms-txt.php` | Add | 80 |
| `assets/js/admin.js` | Add | 50 |

**Total estimated new/modified lines:** ~280

---

## Example Output

With citation guidelines enabled, llms.txt would include:

```markdown
# Acme Blog

> Insights on technology and startups

## About

Acme Blog covers technology trends, startup advice, and product reviews...

## AI Citation Guidelines

### Preferred Citation Format
> Author Name. "Article Title." Acme Blog, Date. https://acme.blog/article

### Accuracy Notes
Technology landscape changes rapidly. Verify current product features on official sites.

### Content Usage
- Summarize with attribution and links
- Do not reproduce full articles
- Link to source for code examples

### AI Partnership Inquiries
Contact: press@acme.blog

## Key Topics
...
```

---

## Testing Checklist

### Citation Guidelines
- [ ] Checkbox enables/disables section in llms.txt
- [ ] Each field appears correctly in output
- [ ] Empty fields are omitted (no blank sections)
- [ ] Fields save and load correctly
- [ ] Preview updates when fields change

### Templates
- [ ] Each site type template includes appropriate citation suggestions
- [ ] Template citation sections are editable after loading

### Nudge
- [ ] Notice appears after wizard completion
- [ ] Notice only shows on GetCited admin pages
- [ ] Dismiss button works and persists
- [ ] Notice doesn't reappear after dismissal

### Regression
- [ ] Existing llms.txt content preserved
- [ ] Site scanner still works
- [ ] Health checks pass
- [ ] No JS errors in console

---

## Open Questions (Resolved)

1. ~~Should citation guidelines be included in site scanner auto-generation, or only manual entry?~~ **Yes, auto-generate with templates.**
2. ~~Add a "Copy citation format" button for users to share?~~ **Skip for 1.5.1, add if users request.**
3. ~~Include a preview of how AI might cite based on the format?~~ **Skip for 1.5.1, preview is in llms.txt output itself.**

---

## Sign-Off

**Specification drafted by:** Claude
**Date:** December 3, 2025
**Status:** IMPLEMENTED

Implementation complete. All features implemented as specified.

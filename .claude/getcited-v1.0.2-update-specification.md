# GetCited v1.0.2 — Complete Update Specification

**Date:** November 30, 2025  
**Current Version:** 1.0.1  
**Target Version:** 1.0.2

---

## Summary

This document provides complete implementation specifications for 8 issues: 1 critical bug, 2 high-priority bugs, 2 missing features, and 3 enhancements.

---

## Issue #1: Save Changes Not Working

**Priority:** Critical  
**Type:** Bug  
**Files to Modify:**
- `includes/class-dashboard.php` (lines 93-150)
- `includes/class-wizard.php` (lines 220-266)

### Symptom

Clicking "Save Changes" on Schema Settings, Settings > General (Site Type), and llms.txt pages appears to succeed (button shows "Saving..." then "Saved") but settings revert to previous values on page reload.

### Root Cause

JavaScript sends form data as a JSON string via FormData:

```javascript
// assets/js/admin.js lines 44-48
if (typeof data[key] === 'object') {
    formData.append(key, JSON.stringify(data[key]));
}
```

PHP expects an array but receives a string:

```php
// includes/class-dashboard.php line 102
$data = isset( $_POST['data'] ) ? map_deep( wp_unslash( $_POST['data'] ), 'sanitize_text_field' ) : array();
```

When `$_POST['data']` is `'{"schema_enabled":true,"schema_types":{"organization":true}}'` (a string), `map_deep()` just sanitizes the string. All subsequent `isset($data['schema_enabled'])` checks fail because `$data` is a string, not an array.

### Fix for class-dashboard.php

**File:** `includes/class-dashboard.php`  
**Method:** `ajax_save_settings()`  
**Current code starts at line 93**

Replace the entire `ajax_save_settings()` method with:

```php
/**
 * AJAX: Save settings
 */
public function ajax_save_settings() {
    check_ajax_referer( 'getcited_admin', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied' );
    }

    $section = isset( $_POST['section'] ) ? sanitize_text_field( wp_unslash( $_POST['section'] ) ) : '';
    
    // Handle JSON string from FormData (JS sends objects as JSON strings)
    $raw_data = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array();
    if ( is_string( $raw_data ) ) {
        $decoded = json_decode( $raw_data, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            $raw_data = $decoded;
        }
    }
    
    // Sanitize the data array
    $data = is_array( $raw_data ) ? map_deep( $raw_data, 'sanitize_text_field' ) : array();

    $settings = GetCited_Settings::instance();

    switch ( $section ) {
        case 'crawlers':
            if ( isset( $data['crawlers'] ) && is_array( $data['crawlers'] ) ) {
                $settings->set( 'crawlers', $data['crawlers'] );
            }
            if ( isset( $data['custom_crawlers'] ) && is_array( $data['custom_crawlers'] ) ) {
                $settings->set( 'custom_crawlers', $data['custom_crawlers'] );
            }
            break;

        case 'llms_txt':
            if ( isset( $data['llms_txt_enabled'] ) ) {
                $settings->set( 'llms_txt_enabled', filter_var( $data['llms_txt_enabled'], FILTER_VALIDATE_BOOLEAN ) );
            }
            if ( isset( $data['llms_txt_content'] ) ) {
                $settings->set( 'llms_txt_content', $data['llms_txt_content'] );
            }
            break;

        case 'schema':
            if ( isset( $data['schema_enabled'] ) ) {
                $settings->set( 'schema_enabled', filter_var( $data['schema_enabled'], FILTER_VALIDATE_BOOLEAN ) );
            }
            if ( isset( $data['schema_types'] ) && is_array( $data['schema_types'] ) ) {
                // Convert string "true"/"false" to actual booleans
                $schema_types = array();
                foreach ( $data['schema_types'] as $key => $value ) {
                    $schema_types[ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
                }
                $settings->set( 'schema_types', $schema_types );
            }
            if ( isset( $data['organization'] ) && is_array( $data['organization'] ) ) {
                $settings->set( 'organization', $data['organization'] );
            }
            break;

        case 'advanced':
            if ( isset( $data['site_type'] ) ) {
                $settings->set( 'site_type', $data['site_type'] );
            }
            if ( isset( $data['debug_mode'] ) ) {
                $settings->set( 'debug_mode', filter_var( $data['debug_mode'], FILTER_VALIDATE_BOOLEAN ) );
            }
            if ( isset( $data['keep_on_delete'] ) ) {
                $settings->set( 'keep_on_delete', filter_var( $data['keep_on_delete'], FILTER_VALIDATE_BOOLEAN ) );
            }
            break;
    }

    wp_send_json_success( array(
        'message' => __( 'Settings saved', 'getcited' ),
    ) );
}
```

### Fix for class-wizard.php

**File:** `includes/class-wizard.php`  
**Method:** `ajax_save_step()`  
**Current code starts at line 220**

Replace the data handling portion (lines 227-229) with:

```php
/**
 * AJAX: Save wizard step
 */
public function ajax_save_step() {
    check_ajax_referer( 'getcited_admin', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied' );
    }

    $step = isset( $_POST['step'] ) ? sanitize_text_field( wp_unslash( $_POST['step'] ) ) : '';
    
    // Handle JSON string from FormData (JS sends objects as JSON strings)
    $raw_data = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array();
    if ( is_string( $raw_data ) ) {
        $decoded = json_decode( $raw_data, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            $raw_data = $decoded;
        }
    }
    
    // Sanitize the data array
    $data = is_array( $raw_data ) ? map_deep( $raw_data, 'sanitize_text_field' ) : array();

    $settings = GetCited_Settings::instance();

    switch ( $step ) {
        case 'site_type':
            $site_type = sanitize_text_field( $data['site_type'] ?? 'blog' );
            $this->apply_preset( $site_type );
            break;

        case 'organization':
            $org = array(
                'name' => sanitize_text_field( $data['name'] ?? '' ),
                'logo_url' => esc_url_raw( $data['logo_url'] ?? '' ),
                'social_urls' => array_map( 'esc_url_raw', (array) ( $data['social_urls'] ?? array() ) ),
            );
            $settings->set( 'organization', $org );
            break;

        case 'crawlers':
            $allow_all = isset( $data['allow_all'] ) && ( $data['allow_all'] === 'true' || $data['allow_all'] === true );
            
            if ( $allow_all ) {
                // Set all crawlers to allow
                $crawler_list = GetCited_Crawler_List::instance();
                $crawlers = $crawler_list->get_all();
                
                $states = array();
                foreach ( $crawlers as $crawler ) {
                    $states[ $crawler['name'] ] = 'allow';
                }
                $settings->set( 'crawlers', $states );
            }
            break;
    }

    wp_send_json_success( array( 'step' => $step ) );
}
```

---

## Issue #2: Analyze Citability Button Not Working on Post Editor

**Priority:** High  
**Type:** Bug  
**File to Modify:** `getcited.php` (lines 262-299)

### Symptom

Clicking "Analyze Citability" button in the post/page editor meta box does nothing. No loading state, no score update, nothing happens.

### Root Cause

Admin assets (CSS and JS) only load on GetCited admin pages. The post editor uses `post.php` or `post-new.php` as the hook, which doesn't contain "getcited".

```php
// getcited.php lines 266-269
public function enqueue_admin_assets( $hook ) {
    // Only load on GetCited pages
    if ( strpos( $hook, 'getcited' ) === false ) {
        return;  // <-- Exits immediately on post.php
    }
    // Scripts never load...
}
```

### Fix

**File:** `getcited.php`  
**Method:** `enqueue_admin_assets()`  
**Replace lines 262-299 with:**

```php
/**
 * Enqueue admin assets
 */
public function enqueue_admin_assets( $hook ) {
    // Determine if we should load assets
    $is_getcited_page = strpos( $hook, 'getcited' ) !== false;
    $is_post_edit = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
    
    // Only load on GetCited pages and post edit screens
    if ( ! $is_getcited_page && ! $is_post_edit ) {
        return;
    }

    wp_enqueue_style(
        'getcited-admin',
        GETCITED_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        GETCITED_VERSION
    );

    wp_enqueue_script(
        'getcited-admin',
        GETCITED_PLUGIN_URL . 'assets/js/admin.js',
        array(),
        GETCITED_VERSION,
        true
    );

    wp_localize_script( 'getcited-admin', 'getcitedAdmin', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'restUrl' => rest_url( 'getcited/v1/' ),
        'nonce' => wp_create_nonce( 'getcited_admin' ),
        'restNonce' => wp_create_nonce( 'wp_rest' ),
        'strings' => array(
            'saving' => __( 'Saving...', 'getcited' ),
            'saved' => __( 'Saved', 'getcited' ),
            'error' => __( 'Error saving', 'getcited' ),
            'checking' => __( 'Checking...', 'getcited' ),
            'analyzing' => __( 'Analyzing...', 'getcited' ),
            'copied' => __( 'Copied!', 'getcited' ),
        ),
    ) );
}
```

---

## Issue #3: Wizard Auto-Redirect Not Active

**Priority:** High  
**Type:** Bug  
**File to Modify:** `getcited.php` (lines 109-134)

### Symptom

After activating the plugin for the first time, users land on the Plugins page instead of being redirected to the setup wizard.

### Root Cause

The wizard class checks for a transient named `getcited_activation_redirect` on `admin_init`, but this transient is never set during plugin activation.

```php
// class-wizard.php lines 67-68 (checks for transient)
if ( get_transient( 'getcited_activation_redirect' ) ) {
    delete_transient( 'getcited_activation_redirect' );
    // ... redirect happens
}

// getcited.php activate() method - never sets the transient
```

### Fix

**File:** `getcited.php`  
**Method:** `activate()`  
**Replace lines 109-134 with:**

```php
/**
 * Plugin activation
 */
public function activate() {
    // Initialize default settings
    $settings = GetCited_Settings::instance();
    $settings->init_defaults();

    // Generate site UUID if not exists
    $current = $settings->get_all();
    if ( empty( $current['site_uuid'] ) ) {
        $settings->set( 'site_uuid', wp_generate_uuid4() );
    }

    // Schedule cron job
    if ( ! wp_next_scheduled( 'getcited_daily_cron' ) ) {
        wp_schedule_event( time(), 'daily', 'getcited_daily_cron' );
    }

    // Flush rewrite rules
    $this->register_rewrites();
    flush_rewrite_rules();

    // Set transient for wizard redirect (only on fresh install, not completed wizard)
    if ( ! $settings->get( 'wizard_completed' ) ) {
        set_transient( 'getcited_activation_redirect', true, 60 );
    }

    // Fire activation hook
    do_action( 'getcited_activated' );
}
```

---

## Issue #4: llms.txt Template Loading Not Implemented

**Priority:** Medium  
**Type:** Missing Feature  
**Files to Modify:**
- `includes/class-dashboard.php` (add new method and hook)
- `assets/js/admin.js` (lines 277-297)

### Symptom

Clicking template buttons (Blog, Business, News, E-commerce, Other) in the llms.txt editor shows "Loading..." briefly but never populates the textarea with template content.

### Root Cause

JavaScript has placeholder code that doesn't actually fetch templates:

```javascript
// assets/js/admin.js lines 290-296
// Simulate loading template (would be AJAX call to get template)
setTimeout(() => {
    btn.disabled = false;
    btn.textContent = type.charAt(0).toUpperCase() + type.slice(1);
    // Template content would be loaded here  <-- Never implemented
}, 500);
```

### Fix — PHP

**File:** `includes/class-dashboard.php`

**Add to constructor (around line 38):**

```php
private function __construct() {
    // Save settings handler
    add_action( 'wp_ajax_getcited_save_settings', array( $this, 'ajax_save_settings' ) );
    
    // Template loading handler (ADD THIS LINE)
    add_action( 'wp_ajax_getcited_load_template', array( $this, 'ajax_load_template' ) );
}
```

**Add new method after `ajax_save_settings()` (after line 150):**

```php
/**
 * AJAX: Load llms.txt template
 */
public function ajax_load_template() {
    check_ajax_referer( 'getcited_admin', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permission denied' ) );
    }

    $type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'blog';
    
    // Validate type
    $valid_types = array( 'blog', 'business', 'news', 'ecommerce', 'other' );
    if ( ! in_array( $type, $valid_types, true ) ) {
        $type = 'blog';
    }

    // Generate template content
    $llms = GetCited_Llms_Txt::instance();
    $content = $llms->generate_template( $type );

    wp_send_json_success( array( 
        'content' => $content,
        'type' => $type,
    ) );
}
```

### Fix — JavaScript

**File:** `assets/js/admin.js`

**Replace the template buttons section (lines 277-297) with:**

```javascript
// Template buttons
templateBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        const type = btn.dataset.type;
        
        // Confirm if textarea has content
        if (textarea.value.trim() && !confirm('This will replace your current content. Continue?')) {
            return;
        }

        // Show loading state
        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = 'Loading...';

        // Fetch template from server
        ajax('getcited_load_template', { type: type })
            .then(response => {
                btn.disabled = false;
                btn.textContent = originalText;
                
                if (response.success && response.data.content) {
                    // Populate textarea
                    textarea.value = response.data.content;
                    
                    // Update live preview if exists
                    if (preview) {
                        preview.textContent = response.data.content;
                    }
                } else {
                    console.error('Failed to load template:', response);
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.textContent = originalText;
                console.error('Template load error:', error);
            });
    });
});
```

---

## Issue #5: Health Check — Robots.txt Guidance and Copy Functionality

**Priority:** Medium  
**Type:** UX Enhancement  
**Files to Modify:**
- `includes/class-health-check.php` (lines 162-212)
- `includes/class-robots.php` (add new method)
- `templates/dashboard.php` (lines 120-144)
- `assets/js/admin.js` (add new handler)
- `assets/css/admin.css` (add new styles)

### Current Problem

When users see "GetCited rules not found in robots.txt" they have no idea:
- Why this is happening
- Whether it's a serious problem
- How to fix it

### Desired Behavior

1. **Detect the specific cause** (physical file, discourage search engines, or unknown)
2. **Show cause-specific message and solution**
3. **Provide one-click "Copy Rules" button** for manual addition
4. **Show the actual rules** in an expandable section

### Fix — class-health-check.php

**File:** `includes/class-health-check.php`  
**Replace the `check_robots_txt()` method (lines 162-212) with:**

```php
/**
 * Check robots.txt for our rules
 */
private function check_robots_txt() {
    $robots = GetCited_Robots::instance();
    
    // Check if site discourages search engines
    $is_public = get_option( 'blog_public' );
    if ( ! $is_public ) {
        return array(
            'status' => 'error',
            'message' => __( 'Site is set to discourage search engines', 'getcited' ),
            'details' => __( 'WordPress is blocking all crawlers. Go to Settings → Reading and uncheck "Discourage search engines from indexing this site" to allow AI crawlers.', 'getcited' ),
            'action_type' => 'settings_link',
            'action_url' => admin_url( 'options-reading.php' ),
            'action_label' => __( 'Go to Reading Settings', 'getcited' ),
        );
    }
    
    // Check for physical robots.txt file
    if ( $robots->physical_file_exists() ) {
        return array(
            'status' => 'warning',
            'message' => __( 'Physical robots.txt file exists', 'getcited' ),
            'details' => __( 'A robots.txt file exists in your site root. WordPress cannot add GetCited rules dynamically. You have two options:', 'getcited' ),
            'options' => array(
                __( 'Copy the rules below and paste them into your robots.txt file', 'getcited' ),
                __( 'Delete the physical robots.txt file to let WordPress manage it automatically', 'getcited' ),
            ),
            'action_type' => 'copy_rules',
            'rules' => $robots->generate_rules(),
            'file_path' => ABSPATH . 'robots.txt',
        );
    }
    
    // Fetch and check robots.txt content
    $url = home_url( '/robots.txt' );
    
    $response = wp_remote_get( $url, array(
        'timeout' => 5,
        'sslverify' => false,
    ) );

    if ( is_wp_error( $response ) ) {
        return array(
            'status' => 'error',
            'message' => __( 'Could not fetch robots.txt', 'getcited' ),
            'details' => $response->get_error_message(),
        );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );

    if ( $code !== 200 ) {
        return array(
            'status' => 'error',
            /* translators: %d: HTTP response code */
            'message' => sprintf( __( 'robots.txt returned HTTP %d', 'getcited' ), $code ),
            'details' => __( 'The robots.txt file could not be loaded. Check your server configuration.', 'getcited' ),
        );
    }

    // Check for our marker
    if ( strpos( $body, '# === GetCited AI Crawler Rules ===' ) === false ) {
        return array(
            'status' => 'warning',
            'message' => __( 'GetCited rules not found in robots.txt', 'getcited' ),
            'details' => __( 'The rules should be added automatically. Try deactivating and reactivating GetCited, or flush your permalinks by visiting Settings → Permalinks and clicking Save.', 'getcited' ),
            'action_type' => 'permalinks_link',
            'action_url' => admin_url( 'options-permalink.php' ),
            'action_label' => __( 'Go to Permalinks', 'getcited' ),
            'rules' => $robots->generate_rules(),
        );
    }

    return array(
        'status' => 'ok',
        'message' => __( 'robots.txt includes GetCited rules', 'getcited' ),
    );
}
```

### Fix — class-robots.php

**File:** `includes/class-robots.php`  
**Add this public method after `get_status()` (around line 188):**

```php
/**
 * Get rules formatted for display/copying
 */
public function get_rules_for_display() {
    return $this->generate_rules();
}
```

### Fix — templates/dashboard.php

**File:** `templates/dashboard.php`  
**Replace the health check results section (lines 120-144) with:**

```php
<!-- Health Check Section -->
<div class="getcited-section getcited-health-section">
    <h2>
        <?php esc_html_e( 'Health Check', 'getcited' ); ?>
        <button type="button" class="button getcited-run-health-check">
            <?php esc_html_e( 'Run Check', 'getcited' ); ?>
        </button>
    </h2>
    
    <div class="getcited-health-results">
        <?php
        $health_status = $stats['health'];
        $checks = array(
            'llms_txt' => __( 'llms.txt', 'getcited' ),
            'robots_txt' => __( 'robots.txt', 'getcited' ),
            'schema' => __( 'Schema', 'getcited' ),
            'rewrite_rules' => __( 'Rewrite Rules', 'getcited' ),
            'crawler_list' => __( 'Crawler List', 'getcited' ),
        );
        
        foreach ( $checks as $key => $label ) :
            if ( ! isset( $health_status[ $key ] ) ) continue;
            $check = $health_status[ $key ];
            $status_class = $health->get_status_class( $check['status'] );
        ?>
            <div class="getcited-health-item <?php echo esc_attr( $status_class ); ?>" data-check="<?php echo esc_attr( $key ); ?>">
                <span class="health-icon"></span>
                <span class="health-label"><?php echo esc_html( $label ); ?></span>
                <span class="health-message"><?php echo esc_html( $check['message'] ); ?></span>
                
                <?php if ( ! empty( $check['details'] ) || ! empty( $check['action_type'] ) ) : ?>
                    <button type="button" class="getcited-health-expand" aria-expanded="false">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                <?php endif; ?>
            </div>
            
            <?php if ( ! empty( $check['details'] ) || ! empty( $check['action_type'] ) ) : ?>
                <div class="getcited-health-details" data-check="<?php echo esc_attr( $key ); ?>" style="display: none;">
                    <?php if ( ! empty( $check['details'] ) ) : ?>
                        <p class="details-text"><?php echo esc_html( $check['details'] ); ?></p>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $check['options'] ) ) : ?>
                        <ul class="details-options">
                            <?php foreach ( $check['options'] as $option ) : ?>
                                <li><?php echo esc_html( $option ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $check['action_type'] ) ) : ?>
                        <div class="details-actions">
                            <?php if ( $check['action_type'] === 'copy_rules' && ! empty( $check['rules'] ) ) : ?>
                                <div class="getcited-rules-preview">
                                    <pre class="rules-code"><?php echo esc_html( $check['rules'] ); ?></pre>
                                    <button type="button" class="button getcited-copy-rules" data-rules="<?php echo esc_attr( $check['rules'] ); ?>">
                                        <span class="dashicons dashicons-clipboard"></span>
                                        <?php esc_html_e( 'Copy Rules to Clipboard', 'getcited' ); ?>
                                    </button>
                                </div>
                                <?php if ( ! empty( $check['file_path'] ) ) : ?>
                                    <p class="file-path">
                                        <?php esc_html_e( 'File location:', 'getcited' ); ?> 
                                        <code><?php echo esc_html( $check['file_path'] ); ?></code>
                                    </p>
                                <?php endif; ?>
                            <?php elseif ( ! empty( $check['action_url'] ) && ! empty( $check['action_label'] ) ) : ?>
                                <a href="<?php echo esc_url( $check['action_url'] ); ?>" class="button">
                                    <?php echo esc_html( $check['action_label'] ); ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ( ! empty( $check['rules'] ) && $check['action_type'] !== 'copy_rules' ) : ?>
                                <button type="button" class="button getcited-show-rules">
                                    <?php esc_html_e( 'Show Rules', 'getcited' ); ?>
                                </button>
                                <div class="getcited-rules-preview" style="display: none;">
                                    <pre class="rules-code"><?php echo esc_html( $check['rules'] ); ?></pre>
                                    <button type="button" class="button getcited-copy-rules" data-rules="<?php echo esc_attr( $check['rules'] ); ?>">
                                        <span class="dashicons dashicons-clipboard"></span>
                                        <?php esc_html_e( 'Copy Rules to Clipboard', 'getcited' ); ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
```

### Fix — assets/js/admin.js

**File:** `assets/js/admin.js`  
**Add to the `initHealthCheck()` function (around line 490):**

```javascript
function initHealthCheck() {
    const healthSection = document.querySelector('.getcited-health-section');
    if (!healthSection) return;

    // Run health check button
    const runBtn = healthSection.querySelector('.getcited-run-health-check');
    if (runBtn) {
        runBtn.addEventListener('click', () => {
            runBtn.disabled = true;
            runBtn.textContent = getcitedAdmin.strings.checking;

            ajax('getcited_health_check')
                .then(response => {
                    runBtn.disabled = false;
                    runBtn.textContent = 'Run Check';
                    
                    if (response.success) {
                        // Reload page to show updated results
                        window.location.reload();
                    }
                })
                .catch(() => {
                    runBtn.disabled = false;
                    runBtn.textContent = 'Run Check';
                });
        });
    }

    // Expand/collapse health details
    healthSection.querySelectorAll('.getcited-health-expand').forEach(btn => {
        btn.addEventListener('click', function() {
            const item = this.closest('.getcited-health-item');
            const checkKey = item.dataset.check;
            const details = healthSection.querySelector(`.getcited-health-details[data-check="${checkKey}"]`);
            
            if (details) {
                const isExpanded = this.getAttribute('aria-expanded') === 'true';
                this.setAttribute('aria-expanded', !isExpanded);
                details.style.display = isExpanded ? 'none' : 'block';
                
                // Rotate arrow icon
                const icon = this.querySelector('.dashicons');
                icon.classList.toggle('dashicons-arrow-down-alt2', isExpanded);
                icon.classList.toggle('dashicons-arrow-up-alt2', !isExpanded);
            }
        });
    });

    // Copy rules to clipboard
    healthSection.querySelectorAll('.getcited-copy-rules').forEach(btn => {
        btn.addEventListener('click', function() {
            const rules = this.dataset.rules;
            
            navigator.clipboard.writeText(rules).then(() => {
                const originalHTML = this.innerHTML;
                this.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + getcitedAdmin.strings.copied;
                this.classList.add('copied');
                
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                    this.classList.remove('copied');
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy:', err);
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = rules;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                
                const originalHTML = this.innerHTML;
                this.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + getcitedAdmin.strings.copied;
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                }, 2000);
            });
        });
    });

    // Show/hide rules toggle
    healthSection.querySelectorAll('.getcited-show-rules').forEach(btn => {
        btn.addEventListener('click', function() {
            const preview = this.nextElementSibling;
            if (preview && preview.classList.contains('getcited-rules-preview')) {
                const isHidden = preview.style.display === 'none';
                preview.style.display = isHidden ? 'block' : 'none';
                this.textContent = isHidden ? 'Hide Rules' : 'Show Rules';
            }
        });
    });
}
```

### Fix — assets/css/admin.css

**File:** `assets/css/admin.css`  
**Add these styles (at the end of the Health Check section, around line 296):**

```css
/* ==========================================================================
   Health Check - Expandable Details
   ========================================================================== */

.getcited-health-item {
    position: relative;
}

.getcited-health-expand {
    position: absolute;
    right: var(--getcited-space-md);
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    padding: 4px;
    cursor: pointer;
    color: var(--getcited-gray-400);
    transition: color var(--getcited-transition);
}

.getcited-health-expand:hover {
    color: var(--getcited-gray-600);
}

.getcited-health-expand .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    transition: transform var(--getcited-transition);
}

.getcited-health-expand[aria-expanded="true"] .dashicons {
    transform: rotate(180deg);
}

.getcited-health-details {
    margin: 0 0 var(--getcited-space-sm) 0;
    padding: var(--getcited-space-md);
    padding-left: calc(20px + var(--getcited-space-md) + var(--getcited-space-md));
    background: var(--getcited-gray-50);
    border-left: 3px solid var(--getcited-gray-300);
    border-radius: 0 0 var(--getcited-radius-sm) var(--getcited-radius-sm);
}

.getcited-status-warning + .getcited-health-details {
    border-left-color: var(--getcited-warning);
    background: rgba(245, 158, 11, 0.05);
}

.getcited-status-error + .getcited-health-details {
    border-left-color: var(--getcited-error);
    background: rgba(239, 68, 68, 0.05);
}

.getcited-health-details .details-text {
    margin: 0 0 var(--getcited-space-md) 0;
    color: var(--getcited-gray-600);
    font-size: 13px;
    line-height: 1.5;
}

.getcited-health-details .details-options {
    margin: 0 0 var(--getcited-space-md) 0;
    padding-left: var(--getcited-space-lg);
    color: var(--getcited-gray-600);
    font-size: 13px;
}

.getcited-health-details .details-options li {
    margin-bottom: var(--getcited-space-xs);
}

.getcited-health-details .details-actions {
    display: flex;
    flex-direction: column;
    gap: var(--getcited-space-md);
}

.getcited-rules-preview {
    background: var(--getcited-gray-800);
    border-radius: var(--getcited-radius-sm);
    padding: var(--getcited-space-md);
}

.getcited-rules-preview .rules-code {
    margin: 0 0 var(--getcited-space-md) 0;
    padding: 0;
    background: transparent;
    color: var(--getcited-gray-100);
    font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
    font-size: 11px;
    line-height: 1.5;
    white-space: pre-wrap;
    max-height: 200px;
    overflow-y: auto;
}

.getcited-rules-preview .getcited-copy-rules {
    background: var(--getcited-gray-700);
    border-color: var(--getcited-gray-600);
    color: var(--getcited-gray-100);
}

.getcited-rules-preview .getcited-copy-rules:hover {
    background: var(--getcited-gray-600);
    border-color: var(--getcited-gray-500);
    color: #fff;
}

.getcited-rules-preview .getcited-copy-rules.copied {
    background: var(--getcited-success);
    border-color: var(--getcited-success);
}

.getcited-rules-preview .getcited-copy-rules .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
    vertical-align: middle;
    margin-right: 4px;
}

.getcited-health-details .file-path {
    margin: var(--getcited-space-sm) 0 0 0;
    font-size: 12px;
    color: var(--getcited-gray-500);
}

.getcited-health-details .file-path code {
    background: var(--getcited-gray-200);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
}
```

---

## Issue #6: Meta Description Detection — Smart Fallback with Filter Hook

**Priority:** Medium  
**Type:** Enhancement  
**Files to Modify:**
- `includes/class-citability.php` (lines 800-852)

### Current Problem

Citability only checks hardcoded SEO plugin meta keys. Custom SEO solutions (like HeyTC SEO 2028) store meta descriptions in different keys and aren't detected, even when the meta description renders correctly on the page.

### Solution

1. Add filter hook so any SEO plugin can register its meta key
2. Check rendered HTML as fallback if no meta keys found
3. Cache the HTML check result to avoid repeated HTTP requests

### Fix

**File:** `includes/class-citability.php`  
**Replace the `check_meta_description()` method (lines 800-852) with:**

```php
/**
 * Check meta description
 */
private function check_meta_description( $post_id ) {
    $max = $this->rubric['meta_description']['max_points'];

    // Allow plugins to register their meta description keys
    $meta_keys = apply_filters( 'getcited_meta_description_keys', array(
        // Yoast SEO
        '_yoast_wpseo_metadesc',
        // Rank Math
        'rank_math_description',
        // All in One SEO
        '_aioseo_description',
        // SEOPress
        '_seopress_titles_desc',
        // The SEO Framework / Genesis
        '_genesis_description',
        // Slim SEO
        'slim_seo_description',
    ) );

    // Check known meta keys first (fast, no HTTP request)
    foreach ( $meta_keys as $key ) {
        $desc = get_post_meta( $post_id, $key, true );
        if ( ! empty( $desc ) ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'Meta description set', 'getcited' ),
            );
        }
    }

    // Check cached HTML detection result
    $cached_result = get_post_meta( $post_id, '_getcited_meta_desc_detected', true );
    $cache_time = get_post_meta( $post_id, '_getcited_meta_desc_checked', true );
    $post_modified = get_post_modified_time( 'U', true, $post_id );
    
    // Use cache if valid and post hasn't been modified since
    if ( $cached_result && $cache_time && $cache_time > $post_modified ) {
        if ( $cached_result === 'yes' ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'Meta description found', 'getcited' ),
            );
        }
        // If cached as 'no', continue to check excerpt
    } else {
        // Fallback: Check rendered HTML for meta description tag
        $permalink = get_permalink( $post_id );
        
        $response = wp_remote_get( $permalink, array(
            'timeout' => 5,
            'sslverify' => false,
            'user-agent' => 'GetCited Citability Checker',
        ) );

        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $html = wp_remote_retrieve_body( $response );
            
            // Look for meta description tag (handles various attribute orders)
            $has_meta_desc = preg_match( 
                '/<meta[^>]+name=["\']description["\'][^>]+content=["\'][^"\']+["\'][^>]*>/i', 
                $html 
            ) || preg_match( 
                '/<meta[^>]+content=["\'][^"\']+["\'][^>]+name=["\']description["\'][^>]*>/i', 
                $html 
            );

            // Cache the result
            update_post_meta( $post_id, '_getcited_meta_desc_detected', $has_meta_desc ? 'yes' : 'no' );
            update_post_meta( $post_id, '_getcited_meta_desc_checked', time() );

            if ( $has_meta_desc ) {
                return array(
                    'score' => $max,
                    'passed' => true,
                    'message' => __( 'Meta description found', 'getcited' ),
                );
            }
        }
    }

    // Check excerpt as final fallback
    $post = get_post( $post_id );
    if ( ! empty( $post->post_excerpt ) ) {
        return array(
            'score' => round( $max * 0.5 ),
            'passed' => true,
            'message' => __( 'Using excerpt as description', 'getcited' ),
            'recommendation' => __( 'Set a custom meta description for better control over how your content appears', 'getcited' ),
        );
    }

    return array(
        'score' => 0,
        'passed' => false,
        'message' => __( 'No meta description', 'getcited' ),
        'recommendation' => __( 'Add a meta description using your SEO plugin or theme settings', 'getcited' ),
    );
}
```

### Clear Cache on Post Update

**File:** `includes/class-citability.php`  
**Add to constructor (around line 98):**

```php
private function __construct() {
    // Add meta box to post editor
    add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

    // AJAX handler for analyzing posts
    add_action( 'wp_ajax_getcited_analyze_post', array( $this, 'ajax_analyze_post' ) );

    // Register post meta
    add_action( 'init', array( $this, 'register_meta' ) );
    
    // Clear meta description cache when post is updated (ADD THIS)
    add_action( 'save_post', array( $this, 'clear_meta_desc_cache' ), 10, 1 );
}
```

**Add new method after `register_meta()` (around line 152):**

```php
/**
 * Clear meta description detection cache when post is updated
 */
public function clear_meta_desc_cache( $post_id ) {
    // Don't run on autosave
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    
    // Don't run on revisions
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }
    
    delete_post_meta( $post_id, '_getcited_meta_desc_detected' );
    delete_post_meta( $post_id, '_getcited_meta_desc_checked' );
}
```

### Usage for Custom SEO Plugins

Third-party SEO plugins (like HeyTC SEO 2028) can hook in:

```php
// In the custom SEO plugin:
add_filter( 'getcited_meta_description_keys', function( $keys ) {
    $keys[] = '_heytc_seo_description';  // Your meta key
    return $keys;
});
```

---

## Issue #7: FAQ Scoring — Content-Type Aware

**Priority:** Medium  
**Type:** Enhancement  
**File to Modify:** `includes/class-citability.php` (lines 460-503)

### Current Problem

FAQ recommendation appears for all content types. Telling someone to add an FAQ to a news article or opinion piece is inappropriate advice.

### Solution

Detect content type via site_type setting and post categories. For news/editorial content, give full FAQ points automatically without recommending an FAQ be added.

### Fix

**File:** `includes/class-citability.php`

**Step 1: Update the `analyze_post()` method call (around line 298)**

Change:
```php
// 3. FAQ Section (10 points)
$factor = $this->check_faq_section( $content );
```

To:
```php
// 3. FAQ Section (10 points)
$factor = $this->check_faq_section( $content, $post_id );
```

**Step 2: Replace the `check_faq_section()` method (lines 460-503) with:**

```php
/**
 * Check for FAQ section
 */
private function check_faq_section( $content, $post_id = null ) {
    $max = $this->rubric['faq_section']['max_points'];

    // Check if this is news/editorial content where FAQ is not expected
    if ( $post_id ) {
        $settings = GetCited_Settings::instance();
        $site_type = $settings->get( 'site_type' );
        
        // Category slugs that indicate news/editorial content
        $news_category_slugs = apply_filters( 'getcited_news_category_slugs', array(
            'news',
            'breaking',
            'breaking-news',
            'opinion',
            'editorial',
            'commentary',
            'analysis',
            'recap',
            'review',
            'reviews',
            'column',
            'columns',
        ) );
        
        // Get post category slugs
        $post_categories = wp_get_post_categories( $post_id, array( 'fields' => 'slugs' ) );
        
        // Check if site type is news OR post is in a news-like category
        $is_news_content = $site_type === 'news' || ! empty( array_intersect( $news_category_slugs, $post_categories ) );
        
        if ( $is_news_content ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'FAQ not expected for news/editorial content', 'getcited' ),
            );
        }
    }

    // Check for Gutenberg FAQ blocks
    $has_faq_block = strpos( $content, 'wp:yoast/faq-block' ) !== false ||
                     strpos( $content, 'wp:generateblocks/accordion' ) !== false ||
                     strpos( $content, 'wp:rank-math/faq-block' ) !== false ||
                     strpos( $content, 'wp:schema-faq' ) !== false ||
                     strpos( $content, 'schema-faq-section' ) !== false;

    // Check for FAQ heading
    $has_faq_heading = preg_match( '/<h[23][^>]*>.*?(FAQ|Frequently Asked|Common Questions|Questions & Answers|Q\s*&\s*A).*?<\/h[23]>/i', $content );

    // Check for Q&A pattern (3+ question-mark headings)
    $has_qa_pattern = preg_match_all( '/<h[34][^>]*>.*?\?.*?<\/h[34]>/i', $content ) >= 3;

    if ( $has_faq_block ) {
        return array(
            'score' => $max,
            'passed' => true,
            'message' => __( 'FAQ block detected', 'getcited' ),
        );
    } elseif ( $has_faq_heading && $has_qa_pattern ) {
        return array(
            'score' => $max,
            'passed' => true,
            'message' => __( 'FAQ section found', 'getcited' ),
        );
    } elseif ( $has_qa_pattern ) {
        return array(
            'score' => round( $max * 0.6 ),
            'passed' => true,
            'message' => __( 'Q&A format detected', 'getcited' ),
            'recommendation' => __( 'Add an "FAQ" or "Frequently Asked Questions" heading to your Q&A section for better AI recognition', 'getcited' ),
        );
    } else {
        return array(
            'score' => 0,
            'passed' => false,
            'message' => __( 'No FAQ section found', 'getcited' ),
            'recommendation' => __( 'Consider adding an FAQ section — AI systems heavily favor Q&A formatted content for citations', 'getcited' ),
        );
    }
}
```

---

## Issue #8: Health Check — Crawler List Warning Tone

**Priority:** Low  
**Type:** UX Enhancement  
**File to Modify:** `includes/class-health-check.php` (lines 285-315)

### Current Problem

The message "Using bundled crawler list. Remote sync may have failed." sounds like an error when the remote endpoint doesn't exist yet. This alarms users unnecessarily.

### Fix

**File:** `includes/class-health-check.php`  
**Replace the `check_crawler_list()` method (lines 285-315) with:**

```php
/**
 * Check crawler list freshness
 */
private function check_crawler_list() {
    $crawler_list = GetCited_Crawler_List::instance();
    
    $is_remote = $crawler_list->is_remote_cached();
    $version = $crawler_list->get_version();
    $updated = $crawler_list->get_last_updated();

    // Using bundled list is fine - show as OK, not warning
    if ( ! $is_remote ) {
        return array(
            'status' => 'ok',
            'message' => sprintf(
                /* translators: %s: version number */
                __( 'Using bundled crawler list (v%s)', 'getcited' ),
                $version
            ),
            'version' => $version,
            'updated' => $updated,
        );
    }

    return array(
        'status' => 'ok',
        'message' => sprintf(
            /* translators: %1$s: version number, %2$s: last updated date */
            __( 'Crawler list v%1$s (synced %2$s)', 'getcited' ),
            $version,
            $updated
        ),
        'version' => $version,
        'updated' => $updated,
    );
}
```

---

## Summary Table

| # | Issue | Priority | Type | Files |
|---|-------|----------|------|-------|
| 1 | Save Changes not working | **Critical** | Bug | class-dashboard.php, class-wizard.php |
| 2 | Analyze Citability button broken | **High** | Bug | getcited.php |
| 3 | Wizard auto-redirect not active | High | Bug | getcited.php |
| 4 | llms.txt template loading | Medium | Feature | class-dashboard.php, admin.js |
| 5 | Robots.txt guidance + copy button | Medium | UX | class-health-check.php, dashboard.php, admin.js, admin.css |
| 6 | Meta description smart detection | Medium | Enhancement | class-citability.php |
| 7 | FAQ content-type aware scoring | Medium | Enhancement | class-citability.php |
| 8 | Crawler list warning tone | Low | UX | class-health-check.php |

---

## Testing Checklist

### Critical/High Priority

- [ ] **Issue #1:** Go to GetCited → Schema Settings
  - [ ] Change organization name, add a social profile URL
  - [ ] Click "Save Changes" — should show "Saved"
  - [ ] Reload page — changes should persist
  - [ ] Repeat for GetCited → Settings (change site type)
  - [ ] Repeat for GetCited → llms.txt (edit content)

- [ ] **Issue #2:** Go to Posts → Edit any post
  - [ ] Locate "GetCited — AI Visibility" meta box
  - [ ] Click "Analyze Citability"
  - [ ] Button should show "Analyzing..." then update score
  - [ ] Score number should appear/update in the meta box

- [ ] **Issue #3:** Deactivate GetCited, delete settings from database
  - [ ] Activate GetCited
  - [ ] Should automatically redirect to wizard page
  - [ ] Complete wizard OR click skip
  - [ ] Deactivate/reactivate — should NOT redirect again

### Medium Priority

- [ ] **Issue #4:** Go to GetCited → llms.txt
  - [ ] Click "Blog" template button
  - [ ] Textarea should populate with blog template content
  - [ ] Test "Business", "News", "E-commerce", "Other" buttons
  - [ ] Confirm prompt appears if textarea has content

- [ ] **Issue #5:** Test with physical robots.txt file
  - [ ] Create empty `robots.txt` in site root
  - [ ] Go to GetCited dashboard
  - [ ] Health check should show warning with expand arrow
  - [ ] Click expand — should show explanation and copy button
  - [ ] Click "Copy Rules to Clipboard" — rules should copy
  - [ ] Delete physical file, run health check — should show OK

- [ ] **Issue #6:** Test meta description detection
  - [ ] Create post with Yoast/RankMath meta description — should detect
  - [ ] Create post with custom SEO plugin — should detect via HTML fallback
  - [ ] Edit post and save — cache should clear
  - [ ] Re-analyze — should re-detect correctly

- [ ] **Issue #7:** Test FAQ scoring
  - [ ] Set site type to "News" in settings
  - [ ] Analyze any post — should show "FAQ not expected for news/editorial content"
  - [ ] Set site type to "Blog"
  - [ ] Create post in category slugged "opinion" — should skip FAQ penalty
  - [ ] Create post in category slugged "how-to" — should show FAQ recommendation

### Low Priority

- [ ] **Issue #8:** Check crawler list status
  - [ ] Without remote endpoint, should show "Using bundled crawler list (v1.0)" as OK (green)
  - [ ] Should NOT show as warning (yellow)

---

## Changelog Entry

Add to `readme.txt`:

```
= 1.0.2 =
* Fixed: Settings not saving on Schema, llms.txt, and General Settings pages
* Fixed: Analyze Citability button not responding in post editor
* Fixed: Setup wizard not launching on first activation
* Added: llms.txt template loading — template buttons now populate content
* Added: Smart meta description detection with HTML fallback
* Added: Filter hook `getcited_meta_description_keys` for custom SEO plugins
* Added: Content-type aware FAQ scoring — news/editorial content not penalized
* Added: Expandable health check details with actionable guidance
* Added: Copy to clipboard functionality for robots.txt rules
* Improved: Health check messaging for physical robots.txt files
* Improved: Crawler list status no longer shows as warning
```

---

## Files Changed Summary

| File | Changes |
|------|---------|
| `getcited.php` | `activate()` method, `enqueue_admin_assets()` method |
| `includes/class-dashboard.php` | `ajax_save_settings()` method, new `ajax_load_template()` method |
| `includes/class-wizard.php` | `ajax_save_step()` method |
| `includes/class-health-check.php` | `check_robots_txt()` method, `check_crawler_list()` method |
| `includes/class-citability.php` | `check_meta_description()` method, `check_faq_section()` method, constructor, new `clear_meta_desc_cache()` method |
| `templates/dashboard.php` | Health check results section |
| `assets/js/admin.js` | Template buttons handler, health check expand/copy handlers |
| `assets/css/admin.css` | Health check expandable details styles |

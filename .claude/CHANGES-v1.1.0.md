# GetCited v1.1.0 — Changes Document

**Created:** December 1, 2025  
**Status:** Ready for Development (after v1.0.4 ships)  
**Prerequisite:** Complete v1.0.4 first

---

## Overview

This release adds the **Intelligent Site Scanner** — a feature that scans the WordPress site during setup and generates a real, substantive llms.txt file based on actual content.

**Why this matters:** Instead of generic templates users have to fill in manually, GetCited now delivers immediate value by showing users their *actual* llms.txt content during onboarding.

---

## Feature: Intelligent Site Scanning for llms.txt Generation

### The Problem

Current llms.txt templates are generic placeholders:
```
# Your Site Name
## Sections
[Add your main categories here]
```

Users have to manually fill in everything. Most won't bother, resulting in weak llms.txt files that don't help AI systems understand their site.

### The Vision

**Wizard-first approach:** Scan the site during onboarding so users see their *actual* llms.txt content before setup completes. The "wow" moment happens immediately, not after they discover a button.

**Two touchpoints:**
1. **Setup Wizard (primary)** — Auto-scan during onboarding, show results at completion
2. **llms.txt Editor (secondary)** — "Re-scan" button for updates after adding content

**Example output for a photographer:**
```
# James Gonzalez Photography

> Professional photography and videography for podcasters, musicians, and creative professionals.

## About
James Gonzalez is a Los Angeles-based photographer specializing in:
- Podcast studio photography and headshots
- Live music and concert photography
- Lifestyle and environmental portraits for musicians
- Music video production and behind-the-scenes content

## Services
- [Podcast Photography](https://site.com/services/podcast)
- [Live Music](https://site.com/services/concerts)
- [Music Videos](https://site.com/videography)

## Recent Work
- "Behind the Mic" podcast portrait series
- Summer concert coverage at The Roxy
- Music video production for indie artists

## Contact
- Website: https://jamesgonzalez.com
- Instagram: @jamesgonzalezphoto
- Email: hello@jamesgonzalez.com
```

### What to Scan

| Source | Data Extracted |
|--------|----------------|
| **Site Settings** | Site name, tagline, description |
| **Homepage** | Hero text, key messaging, featured content |
| **About Page** | Bio, mission statement, specialties |
| **Primary Menu** | Main sections/services structure |
| **Categories** | Content topics and focus areas |
| **Tags** | Specific keywords and themes |
| **Recent Posts** | Titles, excerpts (content themes) |
| **Pages** | Services, Portfolio, Contact info |
| **Footer Widgets** | Contact info, social links, taglines |
| **Custom Post Types** | Portfolio items, services, products |
| **WooCommerce** | Product categories, store description |
| **Theme Customizer** | Social links, contact info |

---

## Implementation

### 1. New Class: `class-site-scanner.php`

**Location:** `includes/class-site-scanner.php`

```php
<?php
/**
 * Site content scanner for intelligent llms.txt generation
 *
 * @package GetCited
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetCited_Site_Scanner {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_getcited_scan_site', array( $this, 'ajax_scan_site' ) );
    }

    /**
     * AJAX handler for site scanning (used by llms.txt editor re-scan)
     */
    public function ajax_scan_site() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $scan_data = $this->scan_site();
        $generated_content = $this->generate_llms_txt( $scan_data );

        wp_send_json_success( array(
            'scan_data' => $scan_data,
            'llms_txt' => $generated_content,
        ) );
    }

    /**
     * Scan the site for content (called directly by wizard, or via AJAX)
     */
    public function scan_site() {
        $data = array(
            'site' => $this->get_site_info(),
            'pages' => $this->get_key_pages(),
            'posts' => $this->get_recent_posts(),
            'categories' => $this->get_categories(),
            'tags' => $this->get_top_tags(),
            'menu' => $this->get_primary_menu(),
            'social' => $this->get_social_links(),
            'contact' => $this->get_contact_info(),
            'custom_post_types' => $this->get_custom_post_types(),
        );

        // Add WooCommerce data if active
        if ( class_exists( 'WooCommerce' ) ) {
            $data['woocommerce'] = $this->get_woocommerce_data();
        }

        return $data;
    }

    /**
     * Get basic site info
     */
    private function get_site_info() {
        return array(
            'name' => get_bloginfo( 'name' ),
            'description' => get_bloginfo( 'description' ),
            'url' => home_url(),
            'language' => get_bloginfo( 'language' ),
            'admin_email' => get_bloginfo( 'admin_email' ),
        );
    }

    /**
     * Get key pages (About, Contact, Services, etc.)
     */
    private function get_key_pages() {
        $key_slugs = array( 
            'about', 'about-us', 'about-me',
            'contact', 'contact-us',
            'services', 'our-services', 
            'portfolio', 'work', 'projects',
            'team', 'our-team',
            'faq', 'faqs',
            'pricing', 'plans',
            'blog', 'news',
        );

        $pages = array();

        foreach ( $key_slugs as $slug ) {
            $page = get_page_by_path( $slug );
            if ( $page && $page->post_status === 'publish' ) {
                $pages[ $slug ] = array(
                    'title' => $page->post_title,
                    'url' => get_permalink( $page ),
                    'excerpt' => $this->get_clean_excerpt( $page ),
                    'content_preview' => $this->get_content_preview( $page->post_content, 500 ),
                );
            }
        }

        // Also check homepage if it's a static page
        $front_page_id = get_option( 'page_on_front' );
        if ( $front_page_id ) {
            $front_page = get_post( $front_page_id );
            if ( $front_page ) {
                $pages['homepage'] = array(
                    'title' => $front_page->post_title,
                    'url' => home_url(),
                    'content_preview' => $this->get_content_preview( $front_page->post_content, 1000 ),
                );
            }
        }

        return $pages;
    }

    /**
     * Get recent posts with metadata
     */
    private function get_recent_posts() {
        $posts = get_posts( array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
        ) );

        $result = array();
        foreach ( $posts as $post ) {
            $result[] = array(
                'title' => $post->post_title,
                'url' => get_permalink( $post ),
                'date' => $post->post_date,
                'categories' => wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) ),
                'tags' => wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) ),
            );
        }

        return $result;
    }

    /**
     * Get all categories with post counts
     */
    private function get_categories() {
        $categories = get_categories( array(
            'orderby' => 'count',
            'order' => 'DESC',
            'hide_empty' => true,
        ) );

        $result = array();
        foreach ( $categories as $cat ) {
            if ( $cat->slug === 'uncategorized' && $cat->count < 2 ) {
                continue; // Skip default uncategorized if barely used
            }
            $result[] = array(
                'name' => $cat->name,
                'slug' => $cat->slug,
                'url' => get_category_link( $cat ),
                'count' => $cat->count,
                'description' => $cat->description,
            );
        }

        return $result;
    }

    /**
     * Get top tags
     */
    private function get_top_tags() {
        $tags = get_tags( array(
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => 20,
            'hide_empty' => true,
        ) );

        $result = array();
        foreach ( $tags as $tag ) {
            $result[] = array(
                'name' => $tag->name,
                'count' => $tag->count,
            );
        }

        return $result;
    }

    /**
     * Get primary navigation menu
     */
    private function get_primary_menu() {
        $locations = get_nav_menu_locations();
        
        // Try common menu location names
        $menu_location = null;
        $common_locations = array( 'primary', 'main', 'main-menu', 'primary-menu', 'header', 'header-menu' );
        
        foreach ( $common_locations as $loc ) {
            if ( isset( $locations[ $loc ] ) ) {
                $menu_location = $locations[ $loc ];
                break;
            }
        }

        // Fallback to first available menu
        if ( ! $menu_location && ! empty( $locations ) ) {
            $menu_location = reset( $locations );
        }

        if ( ! $menu_location ) {
            return array();
        }

        $menu_items = wp_get_nav_menu_items( $menu_location );
        if ( ! $menu_items ) {
            return array();
        }

        $result = array();
        foreach ( $menu_items as $item ) {
            if ( $item->menu_item_parent == 0 ) { // Top-level items only
                $result[] = array(
                    'title' => $item->title,
                    'url' => $item->url,
                );
            }
        }

        return $result;
    }

    /**
     * Get social media links from various sources
     */
    private function get_social_links() {
        $social = array();

        // Check theme mods (common location for social links)
        $social_platforms = array(
            'facebook', 'twitter', 'instagram', 'linkedin', 
            'youtube', 'tiktok', 'pinterest', 'github'
        );

        foreach ( $social_platforms as $platform ) {
            // Check various common theme mod names
            $value = get_theme_mod( $platform . '_url' );
            if ( ! $value ) {
                $value = get_theme_mod( 'social_' . $platform );
            }
            if ( ! $value ) {
                $value = get_theme_mod( $platform );
            }

            if ( $value && filter_var( $value, FILTER_VALIDATE_URL ) ) {
                $social[ $platform ] = $value;
            }
        }

        // Check Yoast SEO social settings if available
        if ( defined( 'WPSEO_VERSION' ) ) {
            $yoast_social = get_option( 'wpseo_social' );
            if ( $yoast_social ) {
                $yoast_keys = array(
                    'facebook_site' => 'facebook',
                    'twitter_site' => 'twitter',
                    'instagram_url' => 'instagram',
                    'linkedin_url' => 'linkedin',
                    'youtube_url' => 'youtube',
                    'pinterest_url' => 'pinterest',
                );
                foreach ( $yoast_keys as $yoast_key => $platform ) {
                    if ( ! empty( $yoast_social[ $yoast_key ] ) ) {
                        $social[ $platform ] = $yoast_social[ $yoast_key ];
                    }
                }
            }
        }

        return $social;
    }

    /**
     * Get contact information
     */
    private function get_contact_info() {
        $contact = array(
            'email' => get_bloginfo( 'admin_email' ),
        );

        // Check for contact page content
        $contact_page = get_page_by_path( 'contact' );
        if ( ! $contact_page ) {
            $contact_page = get_page_by_path( 'contact-us' );
        }
        if ( $contact_page ) {
            $contact['page_url'] = get_permalink( $contact_page );
        }

        return $contact;
    }

    /**
     * Get custom post types
     */
    private function get_custom_post_types() {
        $custom_types = get_post_types( array(
            'public' => true,
            '_builtin' => false,
        ), 'objects' );

        $result = array();
        foreach ( $custom_types as $type ) {
            // Skip WooCommerce types (handled separately)
            if ( in_array( $type->name, array( 'product', 'shop_order', 'shop_coupon' ) ) ) {
                continue;
            }

            $count = wp_count_posts( $type->name );
            if ( $count->publish > 0 ) {
                $result[ $type->name ] = array(
                    'label' => $type->label,
                    'count' => $count->publish,
                    'archive_url' => get_post_type_archive_link( $type->name ),
                );
            }
        }

        return $result;
    }

    /**
     * Get WooCommerce data
     */
    private function get_woocommerce_data() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return array();
        }

        // Get product categories
        $categories = get_terms( array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'orderby' => 'count',
            'order' => 'DESC',
        ) );

        $cats = array();
        if ( ! is_wp_error( $categories ) ) {
            foreach ( $categories as $cat ) {
                $cats[] = array(
                    'name' => $cat->name,
                    'url' => get_term_link( $cat ),
                    'count' => $cat->count,
                );
            }
        }

        return array(
            'shop_url' => wc_get_page_permalink( 'shop' ),
            'product_count' => wp_count_posts( 'product' )->publish,
            'categories' => $cats,
        );
    }

    /**
     * Extract clean text excerpt from a page
     */
    private function get_clean_excerpt( $post ) {
        if ( ! empty( $post->post_excerpt ) ) {
            return wp_strip_all_tags( $post->post_excerpt );
        }
        return wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
    }

    /**
     * Get content preview with HTML stripped
     */
    private function get_content_preview( $content, $length = 500 ) {
        $text = wp_strip_all_tags( $content );
        $text = preg_replace( '/\s+/', ' ', $text ); // Normalize whitespace
        return mb_substr( $text, 0, $length );
    }

    /**
     * Generate llms.txt content from scan data
     */
    public function generate_llms_txt( $scan_data ) {
        $site = $scan_data['site'];
        $content = "# " . $site['name'] . "\n\n";

        // Add tagline/description if exists
        if ( ! empty( $site['description'] ) ) {
            $content .= "> " . $site['description'] . "\n\n";
        }

        // About section
        if ( ! empty( $scan_data['pages']['about'] ) || ! empty( $scan_data['pages']['about-us'] ) || ! empty( $scan_data['pages']['about-me'] ) ) {
            $about = $scan_data['pages']['about'] ?? $scan_data['pages']['about-us'] ?? $scan_data['pages']['about-me'];
            $content .= "## About\n\n";
            if ( ! empty( $about['content_preview'] ) ) {
                // Trim to ~200 chars for the summary
                $preview = mb_substr( $about['content_preview'], 0, 200 );
                if ( strlen( $about['content_preview'] ) > 200 ) {
                    $preview .= '...';
                }
                $content .= $preview . "\n\n";
            }
            $content .= "- [" . $about['title'] . "](" . $about['url'] . ")\n\n";
        }

        // Main sections from menu
        if ( ! empty( $scan_data['menu'] ) ) {
            $content .= "## Sections\n\n";
            foreach ( $scan_data['menu'] as $item ) {
                $content .= "- [" . $item['title'] . "](" . $item['url'] . ")\n";
            }
            $content .= "\n";
        }

        // Services/Portfolio if found
        if ( ! empty( $scan_data['pages']['services'] ) || ! empty( $scan_data['pages']['our-services'] ) ) {
            $services = $scan_data['pages']['services'] ?? $scan_data['pages']['our-services'];
            $content .= "## Services\n\n";
            $content .= "- [" . $services['title'] . "](" . $services['url'] . ")\n\n";
        }

        if ( ! empty( $scan_data['pages']['portfolio'] ) || ! empty( $scan_data['pages']['work'] ) || ! empty( $scan_data['pages']['projects'] ) ) {
            $portfolio = $scan_data['pages']['portfolio'] ?? $scan_data['pages']['work'] ?? $scan_data['pages']['projects'];
            $content .= "## Portfolio\n\n";
            $content .= "- [" . $portfolio['title'] . "](" . $portfolio['url'] . ")\n\n";
        }

        // Categories/Topics
        if ( ! empty( $scan_data['categories'] ) ) {
            $content .= "## Topics\n\n";
            foreach ( array_slice( $scan_data['categories'], 0, 8 ) as $cat ) {
                $content .= "- [" . $cat['name'] . "](" . $cat['url'] . ")";
                if ( ! empty( $cat['description'] ) ) {
                    $content .= ": " . $cat['description'];
                }
                $content .= "\n";
            }
            $content .= "\n";
        }

        // Custom post types (Portfolio, Services, etc.)
        if ( ! empty( $scan_data['custom_post_types'] ) ) {
            foreach ( $scan_data['custom_post_types'] as $type_name => $type ) {
                $content .= "## " . $type['label'] . "\n\n";
                $content .= "- [Browse " . $type['label'] . "](" . $type['archive_url'] . ") (" . $type['count'] . " items)\n\n";
            }
        }

        // WooCommerce
        if ( ! empty( $scan_data['woocommerce'] ) ) {
            $woo = $scan_data['woocommerce'];
            $content .= "## Shop\n\n";
            $content .= "- [Browse Products](" . $woo['shop_url'] . ") (" . $woo['product_count'] . " products)\n";
            if ( ! empty( $woo['categories'] ) ) {
                $content .= "\nProduct Categories:\n";
                foreach ( array_slice( $woo['categories'], 0, 6 ) as $cat ) {
                    $content .= "- [" . $cat['name'] . "](" . $cat['url'] . ")\n";
                }
            }
            $content .= "\n";
        }

        // Recent content
        if ( ! empty( $scan_data['posts'] ) ) {
            $content .= "## Recent Content\n\n";
            foreach ( array_slice( $scan_data['posts'], 0, 5 ) as $post ) {
                $content .= "- [" . $post['title'] . "](" . $post['url'] . ")\n";
            }
            $content .= "\n";
        }

        // Contact & Social
        $content .= "## Connect\n\n";
        $content .= "- Website: " . $site['url'] . "\n";
        
        if ( ! empty( $scan_data['contact']['page_url'] ) ) {
            $content .= "- [Contact](" . $scan_data['contact']['page_url'] . ")\n";
        }

        if ( ! empty( $scan_data['social'] ) ) {
            foreach ( $scan_data['social'] as $platform => $url ) {
                $content .= "- " . ucfirst( $platform ) . ": " . $url . "\n";
            }
        }

        $content .= "\n---\n";
        $content .= "# Generated by GetCited";

        return $content;
    }
}
```

---

### 2. Wizard Integration

**File:** `includes/class-wizard.php`

**Add these methods to integrate scanning into the wizard flow:**

> **Note:** The wizard's `process_step()` method is called via AJAX and already has nonce verification at the handler level (`check_ajax_referer( 'getcited_wizard', 'nonce' )`). The `run_background_scan()` method is a private helper called only from verified contexts.

```php
/**
 * Process wizard step and trigger scan after site type selection
 */
public function process_step( $step, $data ) {
    // ... existing step processing ...

    // After step 2 (site type selection), trigger site scan
    if ( $step === 2 && ! empty( $data['site_type'] ) ) {
        $this->run_background_scan();
    }
}

/**
 * Run site scan and store results for wizard completion
 */
private function run_background_scan() {
    $scanner = GetCited_Site_Scanner::instance();
    $scan_data = $scanner->scan_site();
    $generated_llms = $scanner->generate_llms_txt( $scan_data );
    
    // Store for step 5 display
    set_transient( 'getcited_wizard_scan', array(
        'scan_data' => $scan_data,
        'llms_txt' => $generated_llms,
        'scanned_at' => current_time( 'mysql' ),
    ), HOUR_IN_SECONDS );
    
    // Pre-fill organization info from scan
    $settings = get_option( 'getcited_settings', array() );
    
    if ( empty( $settings['organization']['name'] ) && ! empty( $scan_data['site']['name'] ) ) {
        $settings['organization']['name'] = $scan_data['site']['name'];
    }
    
    if ( empty( $settings['organization']['description'] ) && ! empty( $scan_data['site']['description'] ) ) {
        $settings['organization']['description'] = $scan_data['site']['description'];
    }
    
    update_option( 'getcited_settings', $settings );
}

/**
 * Complete wizard and save generated llms.txt
 */
public function complete_wizard() {
    $wizard_scan = get_transient( 'getcited_wizard_scan' );
    
    if ( $wizard_scan && ! empty( $wizard_scan['llms_txt'] ) ) {
        // Save the generated llms.txt content
        $settings = get_option( 'getcited_settings', array() );
        $settings['llms_txt_content'] = $wizard_scan['llms_txt'];
        update_option( 'getcited_settings', $settings );
        
        // Clean up transient
        delete_transient( 'getcited_wizard_scan' );
    }
    
    // Mark wizard as completed
    $settings = get_option( 'getcited_settings', array() );
    $settings['wizard_completed'] = true;
    update_option( 'getcited_settings', $settings );
}
```

---

### 3. Wizard Template Update

**File:** `templates/wizard.php`

**Update Step 5 (Complete) to show generated content:**

```php
<!-- Step 5: Complete -->
<div class="getcited-wizard-step" data-step="5" style="display: none;">
    <div class="getcited-wizard-header">
        <span class="dashicons dashicons-yes-alt"></span>
        <h2><?php esc_html_e( 'Setup Complete!', 'getcited' ); ?></h2>
    </div>
    
    <?php
    $wizard_scan = get_transient( 'getcited_wizard_scan' );
    if ( $wizard_scan && ! empty( $wizard_scan['llms_txt'] ) ) :
        $scan_data = $wizard_scan['scan_data'];
    ?>
        <p class="getcited-wizard-intro">
            <?php esc_html_e( 'We scanned your site and created your llms.txt. Here\'s what AI systems will see:', 'getcited' ); ?>
        </p>
        
        <div class="getcited-wizard-preview">
            <div class="preview-header">
                <span class="dashicons dashicons-media-text"></span>
                <span><?php esc_html_e( 'Your llms.txt', 'getcited' ); ?></span>
            </div>
            <pre class="preview-content"><?php echo esc_html( $wizard_scan['llms_txt'] ); ?></pre>
        </div>
        
        <div class="getcited-scan-stats">
            <div class="stat-item">
                <span class="stat-number"><?php echo count( $scan_data['pages'] ?? array() ); ?></span>
                <span class="stat-label"><?php esc_html_e( 'Pages', 'getcited' ); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo count( $scan_data['categories'] ?? array() ); ?></span>
                <span class="stat-label"><?php esc_html_e( 'Categories', 'getcited' ); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo count( $scan_data['posts'] ?? array() ); ?></span>
                <span class="stat-label"><?php esc_html_e( 'Posts', 'getcited' ); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo count( $scan_data['menu'] ?? array() ); ?></span>
                <span class="stat-label"><?php esc_html_e( 'Menu Items', 'getcited' ); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo count( $scan_data['social'] ?? array() ); ?></span>
                <span class="stat-label"><?php esc_html_e( 'Social Links', 'getcited' ); ?></span>
            </div>
        </div>
        
        <p class="description">
            <?php esc_html_e( 'You can edit this anytime from the llms.txt Editor page.', 'getcited' ); ?>
        </p>
        
    <?php else : ?>
        <p class="getcited-wizard-intro">
            <?php esc_html_e( 'Your site is now configured for AI visibility!', 'getcited' ); ?>
        </p>
        
        <div class="getcited-wizard-checklist">
            <div class="checklist-item">
                <span class="dashicons dashicons-yes"></span>
                <?php esc_html_e( 'AI crawlers configured', 'getcited' ); ?>
            </div>
            <div class="checklist-item">
                <span class="dashicons dashicons-yes"></span>
                <?php esc_html_e( 'llms.txt ready to customize', 'getcited' ); ?>
            </div>
            <div class="checklist-item">
                <span class="dashicons dashicons-yes"></span>
                <?php esc_html_e( 'Schema output enabled', 'getcited' ); ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="getcited-wizard-actions">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=getcited-llms-txt' ) ); ?>" class="button button-secondary">
            <?php esc_html_e( 'Edit llms.txt', 'getcited' ); ?>
        </a>
        <button type="button" class="button button-primary getcited-wizard-complete">
            <?php esc_html_e( 'Go to Dashboard', 'getcited' ); ?>
        </button>
    </div>
</div>
```

---

### 4. llms.txt Editor Re-scan Button

**File:** `templates/llms-txt.php`

**Add "Scan My Site" button for re-scanning:**

```php
<div class="getcited-llms-actions">
    <div class="template-buttons">
        <span class="template-label"><?php esc_html_e( 'Load template:', 'getcited' ); ?></span>
        <?php foreach ( $site_types as $type => $label ) : ?>
            <button type="button" class="button getcited-load-template" data-template="<?php echo esc_attr( $type ); ?>">
                <?php echo esc_html( $label ); ?>
            </button>
        <?php endforeach; ?>
    </div>
    
    <div class="scan-section">
        <button type="button" class="button button-primary getcited-scan-site">
            <span class="dashicons dashicons-search"></span>
            <?php esc_html_e( 'Scan My Site', 'getcited' ); ?>
        </button>
        <span class="scan-status"></span>
    </div>
</div>

<p class="description getcited-scan-description">
    <?php esc_html_e( 'Scan your site to auto-generate llms.txt content based on your actual pages, posts, categories, and more.', 'getcited' ); ?>
</p>
```

---

### 5. JavaScript Handler

**File:** `assets/js/admin.js`

**Add to `initLlmsTxtEditor()` function:**

```javascript
// Scan site button (for re-scanning in llms.txt editor)
const scanBtn = document.querySelector('.getcited-scan-site');
if (scanBtn) {
    scanBtn.addEventListener('click', function() {
        const statusEl = document.querySelector('.scan-status');
        const originalHTML = this.innerHTML;
        
        this.disabled = true;
        this.innerHTML = '<span class="dashicons dashicons-update spinning"></span> ' + getcited_admin.strings.scanning;
        if (statusEl) {
            statusEl.textContent = '';
            statusEl.className = 'scan-status';
        }

        ajax('getcited_scan_site')
            .then(response => {
                this.disabled = false;
                this.innerHTML = originalHTML;

                if (response.success) {
                    // Update the editor with generated content
                    const editor = document.getElementById('llms-txt-content');
                    if (editor) {
                        editor.value = response.data.llms_txt;
                        // Trigger change event for preview update
                        editor.dispatchEvent(new Event('input'));
                        
                        // Mark as unsaved
                        markUnsaved();
                    }

                    if (statusEl) {
                        statusEl.textContent = getcited_admin.strings.scan_success;
                        statusEl.className = 'scan-status success';
                    }

                    // Show scan summary
                    showScanSummary(response.data.scan_data);
                } else {
                    if (statusEl) {
                        statusEl.textContent = getcited_admin.strings.scan_failed;
                        statusEl.className = 'scan-status error';
                    }
                }
            })
            .catch(error => {
                this.disabled = false;
                this.innerHTML = originalHTML;
                if (statusEl) {
                    statusEl.textContent = getcited_admin.strings.scan_failed;
                    statusEl.className = 'scan-status error';
                }
                console.error('GetCited: Scan failed', error);
            });
    });
}

/**
 * Display scan results summary
 */
function showScanSummary(data) {
    // Remove existing summary
    const existingSummary = document.querySelector('.getcited-scan-summary');
    if (existingSummary) {
        existingSummary.remove();
    }

    const stats = [
        { label: getcited_admin.strings.pages, count: Object.keys(data.pages || {}).length },
        { label: getcited_admin.strings.posts, count: (data.posts || []).length },
        { label: getcited_admin.strings.categories, count: (data.categories || []).length },
        { label: getcited_admin.strings.menu_items, count: (data.menu || []).length },
        { label: getcited_admin.strings.social_links, count: Object.keys(data.social || {}).length },
    ];

    const statsHtml = stats
        .filter(s => s.count > 0)
        .map(s => `<span class="stat"><strong>${s.count}</strong> ${s.label}</span>`)
        .join(' · ');

    const summaryHtml = `
        <div class="getcited-scan-summary">
            <div class="summary-header">
                <span class="dashicons dashicons-yes-alt"></span>
                <strong>${getcited_admin.strings.scan_complete}</strong>
            </div>
            <div class="summary-stats">${statsHtml}</div>
            <p class="description">${getcited_admin.strings.scan_review}</p>
        </div>
    `;

    // Insert after scan description
    const scanDesc = document.querySelector('.getcited-scan-description');
    if (scanDesc) {
        scanDesc.insertAdjacentHTML('afterend', summaryHtml);
    }
}
```

---

### 6. Localized Strings

**File:** `getcited.php`

**Add to localized strings array in `admin_enqueue_scripts()`:**

```php
$strings = array(
    // ... existing strings ...
    
    // Site scanner strings
    'scanning'      => __( 'Scanning...', 'getcited' ),
    'scan_success'  => __( 'Site scanned successfully!', 'getcited' ),
    'scan_failed'   => __( 'Scan failed. Please try again.', 'getcited' ),
    'scan_complete' => __( 'Scan Complete', 'getcited' ),
    'scan_review'   => __( 'Review the generated content and customize as needed.', 'getcited' ),
    'pages'         => __( 'pages', 'getcited' ),
    'posts'         => __( 'posts', 'getcited' ),
    'categories'    => __( 'categories', 'getcited' ),
    'menu_items'    => __( 'menu items', 'getcited' ),
    'social_links'  => __( 'social links', 'getcited' ),
);
```

---

### 7. CSS Styles

**File:** `assets/css/admin.css`

```css
/* ============================================
   Site Scanner Styles
   ============================================ */

/* llms.txt Editor Actions */
.getcited-llms-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--getcited-space-md);
    margin-bottom: var(--getcited-space-md);
}

.scan-section {
    display: flex;
    align-items: center;
    gap: var(--getcited-space-sm);
}

.getcited-scan-site {
    display: inline-flex;
    align-items: center;
    gap: var(--getcited-space-xs);
}

.getcited-scan-site .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.getcited-scan-site .dashicons.spinning {
    animation: getcited-spin 1s linear infinite;
}

@keyframes getcited-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.scan-status {
    font-size: 13px;
    font-weight: 500;
}

.scan-status.success {
    color: var(--getcited-success);
}

.scan-status.error {
    color: var(--getcited-error);
}

.getcited-scan-description {
    color: var(--getcited-gray-500);
    font-style: italic;
    margin-bottom: var(--getcited-space-md);
}

/* Scan Summary */
.getcited-scan-summary {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border: 1px solid var(--getcited-success);
    border-radius: var(--getcited-radius-md);
    padding: var(--getcited-space-md);
    margin: var(--getcited-space-md) 0;
}

.getcited-scan-summary .summary-header {
    display: flex;
    align-items: center;
    gap: var(--getcited-space-xs);
    color: var(--getcited-success);
    margin-bottom: var(--getcited-space-sm);
}

.getcited-scan-summary .summary-header .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.getcited-scan-summary .summary-stats {
    color: var(--getcited-gray-700);
    margin-bottom: var(--getcited-space-sm);
}

.getcited-scan-summary .summary-stats .stat {
    white-space: nowrap;
}

/* ============================================
   Wizard Scan Results (Step 5)
   ============================================ */

.getcited-wizard-header {
    text-align: center;
    margin-bottom: var(--getcited-space-lg);
}

.getcited-wizard-header .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: var(--getcited-success);
    margin-bottom: var(--getcited-space-sm);
}

.getcited-wizard-intro {
    text-align: center;
    font-size: 15px;
    color: var(--getcited-gray-600);
    margin-bottom: var(--getcited-space-lg);
}

.getcited-wizard-preview {
    background: var(--getcited-gray-900);
    border-radius: var(--getcited-radius-md);
    overflow: hidden;
    margin-bottom: var(--getcited-space-lg);
    max-height: 400px;
    overflow-y: auto;
}

.getcited-wizard-preview .preview-header {
    background: var(--getcited-gray-800);
    padding: var(--getcited-space-sm) var(--getcited-space-md);
    display: flex;
    align-items: center;
    gap: var(--getcited-space-xs);
    color: var(--getcited-gray-300);
    font-size: 13px;
    position: sticky;
    top: 0;
}

.getcited-wizard-preview .preview-content {
    padding: var(--getcited-space-md);
    margin: 0;
    color: var(--getcited-gray-100);
    font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
    font-size: 13px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-wrap: break-word;
}

/* Scan Stats Grid */
.getcited-scan-stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: var(--getcited-space-md);
    margin-bottom: var(--getcited-space-lg);
}

.getcited-scan-stats .stat-item {
    text-align: center;
    padding: var(--getcited-space-md);
    background: var(--getcited-gray-50);
    border-radius: var(--getcited-radius-md);
}

.getcited-scan-stats .stat-number {
    display: block;
    font-size: 24px;
    font-weight: 700;
    color: var(--getcited-primary);
    line-height: 1;
    margin-bottom: var(--getcited-space-xs);
}

.getcited-scan-stats .stat-label {
    font-size: 12px;
    color: var(--getcited-gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Wizard Checklist (fallback if no scan data) */
.getcited-wizard-checklist {
    max-width: 300px;
    margin: 0 auto var(--getcited-space-lg);
}

.getcited-wizard-checklist .checklist-item {
    display: flex;
    align-items: center;
    gap: var(--getcited-space-sm);
    padding: var(--getcited-space-sm) 0;
    color: var(--getcited-gray-700);
}

.getcited-wizard-checklist .checklist-item .dashicons {
    color: var(--getcited-success);
}

/* Wizard Actions */
.getcited-wizard-actions {
    display: flex;
    justify-content: center;
    gap: var(--getcited-space-md);
    margin-top: var(--getcited-space-lg);
}

/* Responsive */
@media (max-width: 782px) {
    .getcited-llms-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .scan-section {
        justify-content: center;
    }
    
    .getcited-scan-stats {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 480px) {
    .getcited-scan-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}
```

---

### 8. Main Plugin File Updates

**File:** `getcited.php`

**Add to includes section (~line 85):**

```php
require_once GETCITED_PLUGIN_DIR . 'includes/class-site-scanner.php';
```

**Initialize in `init_classes()` method:**

```php
GetCited_Site_Scanner::instance();
```

---

## Files Changed Summary

| File | Change Type | Description |
|------|-------------|-------------|
| `includes/class-site-scanner.php` | **New File** | ~400 lines — Site content scanner class |
| `includes/class-wizard.php` | Modify | Add `run_background_scan()` and scan integration |
| `templates/wizard.php` | Modify | Step 5 shows generated llms.txt with stats |
| `templates/llms-txt.php` | Modify | Add "Scan My Site" button for re-scanning |
| `assets/js/admin.js` | Add | Scan button handler and `showScanSummary()` |
| `assets/css/admin.css` | Add | Scanner UI + wizard preview styles (~150 lines) |
| `getcited.php` | Modify | Include scanner class + localized strings |

**Total new code:** ~700 lines

---

## Privacy & Security Notes

- **No external requests** — all scanning uses WordPress internal APIs
- **Only public content** — doesn't expose drafts or private posts
- **Admin-only** — requires `manage_options` capability
- **No data sent anywhere** — everything stays on their server
- **Transient cleanup** — wizard scan data deleted after completion

---

## Example Output

**For a photography portfolio site:**

```markdown
# James Gonzalez Photography

> Professional photography and videography for musicians and podcasters

## About

James is a Los Angeles-based photographer specializing in capturing musicians 
and podcast hosts in their element. From intimate studio sessions to high-energy 
live performances...

- [About Me](https://jamesgonzalez.com/about)

## Sections

- [Home](https://jamesgonzalez.com/)
- [Portfolio](https://jamesgonzalez.com/portfolio)
- [Services](https://jamesgonzalez.com/services)
- [Blog](https://jamesgonzalez.com/blog)
- [Contact](https://jamesgonzalez.com/contact)

## Portfolio

- [Browse Portfolio](https://jamesgonzalez.com/portfolio) (47 items)

## Topics

- [Podcast Photography](https://jamesgonzalez.com/category/podcast)
- [Live Music](https://jamesgonzalez.com/category/concerts)
- [Behind the Scenes](https://jamesgonzalez.com/category/bts)

## Recent Content

- [Capturing the Energy: Rolling Loud 2024](https://jamesgonzalez.com/rolling-loud-2024)
- [Studio Setup Tips for Podcast Hosts](https://jamesgonzalez.com/studio-setup-tips)
- [New Work: Album Cover for Rising Star](https://jamesgonzalez.com/album-cover-project)

## Connect

- Website: https://jamesgonzalez.com
- [Contact](https://jamesgonzalez.com/contact)
- Instagram: https://instagram.com/jamesgonzalezphoto
- Twitter: https://twitter.com/jgonzalezphoto

---
# Generated by GetCited
```

That's **real value** — not a template the user has to fill in.

---

*End of document.*

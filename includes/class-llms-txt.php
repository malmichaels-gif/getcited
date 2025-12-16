<?php
/**
 * llms.txt generator for GetCited
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * llms.txt class
 */
class GetCited_Llms_Txt {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Handle llms.txt requests
        add_action( 'template_redirect', array( $this, 'serve_llms_txt' ) );

        // Fallback via query var
        add_action( 'parse_request', array( $this, 'handle_query_var' ) );

        // Add link tag to head
        add_action( 'wp_head', array( $this, 'add_link_tag' ), 1 );

        // Auto-write physical file when settings change
        // Use getcited_settings_saved for batch updates (ensures all settings are saved first)
        add_action( 'getcited_settings_saved', array( $this, 'maybe_auto_write_batch' ), 10, 2 );
        // Use getcited_setting_updated for single setting updates
        add_action( 'getcited_setting_updated', array( $this, 'maybe_auto_write' ), 10, 3 );
    }

    /**
     * Maybe auto-write llms.txt when a batch of settings is saved
     *
     * This is the preferred hook as it fires AFTER all settings are saved,
     * avoiding timing issues where one setting is checked before another is updated.
     *
     * @param array $new_settings All current settings after save.
     * @param array $old_settings All settings before save.
     */
    public function maybe_auto_write_batch( $new_settings, $old_settings ) {
        // Check if any llms.txt-related settings changed
        $llms_keys = array( 'llms_txt_content', 'llms_txt_enabled', 'llms_write_physical' );
        $changed = false;

        foreach ( $llms_keys as $key ) {
            $old_val = $old_settings[ $key ] ?? null;
            $new_val = $new_settings[ $key ] ?? null;
            if ( $old_val !== $new_val ) {
                $changed = true;
                break;
            }
        }

        if ( ! $changed ) {
            return;
        }

        // Check if auto-write is enabled and llms.txt is enabled using NEW values
        $write_physical = $new_settings['llms_write_physical'] ?? false;
        $llms_enabled = $new_settings['llms_txt_enabled'] ?? false;

        if ( ! $write_physical || ! $llms_enabled ) {
            return;
        }

        // Auto-write the file
        $this->write_physical_file();
    }

    /**
     * Maybe auto-write llms.txt when a single setting changes
     *
     * @param string $key       The setting key that was updated.
     * @param mixed  $new_value The new value.
     * @param mixed  $old_value The old value.
     */
    public function maybe_auto_write( $key, $new_value, $old_value = null ) {
        // Only trigger on llms.txt-related settings
        if ( ! in_array( $key, array( 'llms_txt_content', 'llms_txt_enabled', 'llms_write_physical' ), true ) ) {
            return;
        }

        $settings = GetCited_Settings::instance();

        // Check if auto-write is enabled and llms.txt is enabled
        if ( ! $settings->get( 'llms_write_physical' ) || ! $settings->get( 'llms_txt_enabled' ) ) {
            return;
        }

        // Auto-write the file
        $this->write_physical_file();
    }

    /**
     * Initialize WP_Filesystem if needed
     *
     * @return bool True if filesystem is ready, false on failure.
     */
    private function init_filesystem() {
        global $wp_filesystem;

        // Already initialized
        if ( $wp_filesystem instanceof WP_Filesystem_Base ) {
            return true;
        }

        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        return WP_Filesystem();
    }

    /**
     * Check if we can write to site root
     *
     * @return bool
     */
    public function can_write_physical_file() {
        global $wp_filesystem;

        if ( ! $this->init_filesystem() ) {
            return false;
        }

        $file_path = ABSPATH . 'llms.txt';

        // If file exists, check if writable
        if ( $wp_filesystem->exists( $file_path ) ) {
            return $wp_filesystem->is_writable( $file_path );
        }

        // If file doesn't exist, check if directory is writable
        return $wp_filesystem->is_writable( ABSPATH );
    }

    /**
     * Write llms.txt to site root
     *
     * @return array Result with success status and message
     */
    public function write_physical_file() {
        global $wp_filesystem;

        $file_path = ABSPATH . 'llms.txt';

        // Check write permissions (also initializes filesystem)
        if ( ! $this->can_write_physical_file() ) {
            return array(
                'success' => false,
                'message' => __( 'Cannot write to llms.txt. Check file permissions.', 'getcited' ),
                'details' => __( 'Your hosting may require FTP credentials for file operations. Try adding the file manually, or contact your host about direct file write permissions.', 'getcited' ),
            );
        }

        // Filesystem is already initialized by can_write_physical_file()

        // Get content from settings
        $content = $this->get_content();

        // Ensure the GetCited marker is present
        if ( strpos( $content, '# Generated by GetCited' ) === false ) {
            $content .= "\n\n---\n# Generated by GetCited";
        }

        // Write file
        $result = $wp_filesystem->put_contents( $file_path, $content, FS_CHMOD_FILE );

        if ( ! $result ) {
            return array(
                'success' => false,
                'message' => __( 'Failed to write llms.txt file.', 'getcited' ),
            );
        }

        // Clear health check cache
        if ( class_exists( 'GetCited_Health_Check' ) ) {
            GetCited_Health_Check::instance()->invalidate_cache();
        }

        return array(
            'success' => true,
            'message' => __( 'llms.txt written successfully', 'getcited' ),
        );
    }

    /**
     * Delete physical llms.txt file (only if it's ours)
     *
     * @return array Result with success status and message
     */
    public function delete_physical_file() {
        $file_path = ABSPATH . 'llms.txt';

        // Initialize WP_Filesystem
        global $wp_filesystem;
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if ( ! WP_Filesystem() ) {
            return array(
                'success' => false,
                'message' => __( 'Could not initialize filesystem.', 'getcited' ),
            );
        }

        // Check if file exists
        if ( ! $wp_filesystem->exists( $file_path ) ) {
            return array(
                'success' => true,
                'message' => __( 'No llms.txt file exists.', 'getcited' ),
            );
        }

        // Only delete if it's our file (has GetCited marker)
        if ( ! $this->is_our_physical_file() ) {
            return array(
                'success' => false,
                'message' => __( 'llms.txt was not created by GetCited. Not deleting.', 'getcited' ),
            );
        }

        // Delete the file
        $result = $wp_filesystem->delete( $file_path );

        if ( ! $result ) {
            return array(
                'success' => false,
                'message' => __( 'Failed to delete llms.txt file.', 'getcited' ),
            );
        }

        return array(
            'success' => true,
            'message' => __( 'llms.txt deleted successfully', 'getcited' ),
        );
    }

    /**
     * Check if physical llms.txt file exists
     *
     * @return bool
     */
    public function physical_file_exists() {
        return file_exists( ABSPATH . 'llms.txt' );
    }

    /**
     * Check if physical llms.txt file is ours (has GetCited marker)
     *
     * @return bool
     */
    public function is_our_physical_file() {
        $file_path = ABSPATH . 'llms.txt';

        if ( ! file_exists( $file_path ) ) {
            return false;
        }

        // Use WP_Filesystem for consistent file operations
        global $wp_filesystem;
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();

        $content = $wp_filesystem->get_contents( $file_path );
        return $content && strpos( $content, '# Generated by GetCited' ) !== false;
    }

    /**
     * Serve llms.txt via rewrite rule
     */
    public function serve_llms_txt() {
        global $wp_query;

        if ( ! isset( $wp_query->query_vars['getcited_llms_txt'] ) ) {
            return;
        }

        $this->output_llms_txt();
    }

    /**
     * Handle fallback query var (?llms-txt=1)
     */
    public function handle_query_var( $wp ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public endpoint like robots.txt, no nonce needed
        if ( isset( $_GET['llms-txt'] ) && sanitize_text_field( wp_unslash( $_GET['llms-txt'] ) ) === '1' ) {
            $this->output_llms_txt();
        }
    }

    /**
     * Output llms.txt content
     */
    private function output_llms_txt() {
        $settings = GetCited_Settings::instance();
        
        if ( ! $settings->get( 'llms_txt_enabled' ) ) {
            // Return 404 if disabled
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            return;
        }

        $content = $this->get_content();
        $content = apply_filters( 'getcited_llms_txt_content', $content );

        // Fire hook for tracking/logging
        do_action( 'getcited_llms_txt_served', $content );

        // Set headers
        status_header( 200 );
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'X-Robots-Tag: noindex' );
        header( 'Cache-Control: public, max-age=86400' ); // Cache for 24 hours

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text file output, not HTML context
        echo $content;
        exit;
    }

    /**
     * Get llms.txt content
     *
     * Priority:
     * 1. Physical file WITHOUT GetCited marker (user's pre-existing custom file)
     * 2. Database content (GetCited-managed, authoritative for our files)
     *
     * If the physical file has our marker, we created it - use database as source of truth.
     * If the physical file has no marker, it's user's custom file - respect it.
     */
    public function get_content() {
        $settings = GetCited_Settings::instance();
        $source   = $settings->get( 'llms_txt_source' );

        // If user explicitly wants existing file, serve it
        if ( 'existing' === $source && $this->physical_file_exists() && ! $this->is_our_physical_file() ) {
            $physical_content = $this->get_physical_file_content();
            if ( ! empty( $physical_content ) ) {
                return $physical_content;
            }
        }

        // Otherwise, use database content (GetCited-managed)
        $content = $settings->get( 'llms_txt_content' );

        // Ensure marker comment exists
        if ( strpos( $content, '# Generated by GetCited' ) === false ) {
            $content .= "\n\n---\n# Generated by GetCited";
        }

        return $content;
    }

    /**
     * Get content from physical llms.txt file
     *
     * @return string|false File content or false on failure
     */
    public function get_physical_file_content() {
        $file_path = ABSPATH . 'llms.txt';

        if ( ! file_exists( $file_path ) ) {
            return false;
        }

        // Use WP_Filesystem for consistent file operations
        global $wp_filesystem;
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();

        return $wp_filesystem->get_contents( $file_path );
    }

    /**
     * Add link tag to site head
     */
    public function add_link_tag() {
        $settings = GetCited_Settings::instance();
        
        if ( ! $settings->get( 'llms_txt_enabled' ) ) {
            return;
        }

        $url = home_url( '/llms.txt' );
        echo '<link rel="llms-txt" href="' . esc_url( $url ) . '" />' . "\n";
    }

    /**
     * Generate template based on site type
     *
     * Produces the same rich format as the site scanner, with all standard sections.
     *
     * @param string $site_type The site type (blog, news, business, ecommerce).
     * @return string Generated llms.txt content.
     */
    public function generate_template( $site_type = 'blog' ) {
        $site_name    = get_bloginfo( 'name' );
        $site_desc    = get_bloginfo( 'description' );
        $site_url     = home_url();
        $language     = get_bloginfo( 'language' );
        $recent_posts = $this->get_recent_posts( 5 );
        $categories   = $this->get_categories();

        // Get settings
        $settings         = GetCited_Settings::instance();
        $founder_name     = $settings->get( 'llms_founder_name' );
        $founder_title    = $settings->get( 'llms_founder_title' );
        $site_expertise   = $settings->get( 'llms_site_expertise' );
        $update_frequency = $settings->get( 'llms_update_frequency' );
        $org              = $settings->get( 'organization' );

        // Build content
        $content = "# {$site_name}\n\n";

        if ( ! empty( $site_desc ) ) {
            $content .= "{$site_desc}\n\n";
        }

        $content .= "This file helps AI systems understand and properly cite content from this website.\n\n";

        // About section
        $content .= "## About\n\n";
        $content .= "- **Website:** {$site_url}\n";
        $content .= '- **Type:** ' . ucfirst( $site_type ) . "\n";

        if ( ! empty( $org['name'] ) && $org['name'] !== $site_name ) {
            $content .= '- **Organization:** ' . $org['name'] . "\n";
        }

        if ( ! empty( $founder_name ) ) {
            $founder_line = '- **Founder:** ' . $founder_name;
            if ( ! empty( $founder_title ) ) {
                $founder_line .= ', ' . $founder_title;
            }
            $content .= $founder_line . "\n";
        }

        if ( ! empty( $site_expertise ) ) {
            $content .= '- **Expertise:** ' . $site_expertise . "\n";
        }

        $content .= "- **Language:** {$language}\n\n";

        // Primary Content
        $content .= "## Primary Content\n\n";
        $content .= $this->get_primary_content_description( $site_type ) . "\n\n";

        // Key Topics
        if ( ! empty( $categories ) ) {
            $content .= "## Key Topics\n\n";
            foreach ( array_slice( $categories, 0, 10 ) as $cat ) {
                $content .= '- **' . $cat['name'] . '**';
                if ( ! empty( $cat['description'] ) && $cat['description'] !== "Articles about {$cat['name']}" ) {
                    $content .= ': ' . $cat['description'];
                } elseif ( ! empty( $cat['count'] ) ) {
                    $content .= ' (' . $cat['count'] . ' articles)';
                }
                $content .= "\n";
            }
            $content .= "\n";
        }

        // Site Structure
        $content .= "## Site Structure\n\n";
        $content .= "- [Home]({$site_url}/)\n";
        $content .= "- [About]({$site_url}/about/)\n";
        $content .= "- [Contact]({$site_url}/contact/)\n";
        $content .= "\n";

        // Citation Guidelines - use AI Citation Guidelines if enabled, otherwise basic template
        $ai_guidelines = $this->get_citation_guidelines_section();
        if ( ! empty( $ai_guidelines ) ) {
            $content .= $ai_guidelines;
        } else {
            $content .= "## Citation Guidelines\n\n";
            $content .= $this->get_citation_template( $site_type ) . "\n\n";
        }

        // Data Freshness
        $content .= "## Data Freshness\n\n";
        if ( ! empty( $update_frequency ) ) {
            $content .= '- **Update Frequency:** ' . $update_frequency . "\n";
        }
        if ( ! empty( $recent_posts ) && isset( $recent_posts[0]['date'] ) ) {
            $content .= '- **Latest Content:** ' . $recent_posts[0]['date'] . "\n";
        }
        $content .= '- **File Generated:** ' . current_time( 'F j, Y' ) . "\n\n";

        // Content Policy
        $content .= "## Content Policy\n\n";
        $content .= $this->get_content_policy_template( $site_type ) . "\n\n";

        // Technical Details
        $content .= "## Technical Details\n\n";
        $content .= "- **Platform:** WordPress\n";

        // Check for schema types
        $schema_types  = $settings->get( 'schema_types' );
        $active_schema = array();
        if ( is_array( $schema_types ) ) {
            foreach ( $schema_types as $type => $enabled ) {
                if ( $enabled ) {
                    $active_schema[] = ucfirst( $type );
                }
            }
        }
        if ( ! empty( $active_schema ) ) {
            $content .= '- **Structured Data:** ' . implode( ', ', $active_schema ) . " schema\n";
        }

        $content .= '- **Sitemap:** ' . home_url( '/sitemap.xml' ) . "\n";
        $content .= '- **RSS Feed:** ' . get_feed_link() . "\n\n";

        // Recent Content
        if ( ! empty( $recent_posts ) ) {
            $content .= "## Recent Content\n\n";
            foreach ( $recent_posts as $post ) {
                $date     = ! empty( $post['date'] ) ? ' — ' . $post['date'] : '';
                $content .= "- [{$post['title']}]({$post['url']}){$date}\n";
            }
            $content .= "\n";
        }

        // Connect
        $content .= "## Connect\n\n";
        $content .= "- [Contact Page]({$site_url}/contact/)\n";

        // Add social links if available
        if ( ! empty( $org['social_urls'] ) ) {
            foreach ( $org['social_urls'] as $url ) {
                if ( empty( $url ) ) {
                    continue;
                }
                $platform = $this->detect_social_platform( $url );
                if ( $platform ) {
                    $display = ( $platform === 'x' ) ? 'X (Twitter)' : ucfirst( $platform );
                    $content .= "- [{$display}]({$url})\n";
                }
            }
        }

        $content .= "\n---\n\n";
        $content .= '*Last updated: ' . current_time( 'F j, Y' ) . "*\n\n";
        $content .= '# Generated by GetCited';

        return $content;
    }

    /**
     * Get primary content description based on site type
     *
     * @param string $site_type The site type.
     * @return string Description of primary content.
     */
    private function get_primary_content_description( $site_type ) {
        $descriptions = array(
            'blog'      => 'This site publishes articles, guides, and analysis. Content is regularly updated with new insights and perspectives.',
            'news'      => 'This site publishes news articles, analysis, and commentary. Content is time-sensitive and regularly updated to reflect current events.',
            'business'  => 'This site provides information about our services, company background, and industry expertise. Content reflects our professional experience and offerings.',
            'ecommerce' => 'This site offers products for purchase along with product information, guides, and customer resources.',
            'portfolio' => 'This site showcases creative work, projects, and professional capabilities.',
            'nonprofit' => 'This site shares information about our mission, programs, and impact. Content highlights our initiatives and community involvement.',
            'education' => 'This site provides educational content, courses, and learning resources. Content is designed to inform and educate our audience.',
            'community' => 'This site hosts discussions, user content, and community resources. Content represents diverse member perspectives.',
            'other'     => 'This site publishes content across various topics. Content is regularly updated.',
        );

        return $descriptions[ $site_type ] ?? $descriptions['other'];
    }

    /**
     * Detect social platform from URL
     *
     * @param string $url The URL to check.
     * @return string|false Platform name or false.
     */
    private function detect_social_platform( $url ) {
        $patterns = array(
            'x'         => array( 'twitter.com', 'x.com' ),
            'facebook'  => 'facebook.com',
            'instagram' => 'instagram.com',
            'linkedin'  => 'linkedin.com',
            'youtube'   => array( 'youtube.com', 'youtu.be' ),
            'tiktok'    => 'tiktok.com',
            'pinterest' => 'pinterest.com',
            'github'    => 'github.com',
        );

        foreach ( $patterns as $platform => $pattern ) {
            $check = is_array( $pattern ) ? $pattern : array( $pattern );
            foreach ( $check as $p ) {
                if ( stripos( $url, $p ) !== false ) {
                    return $platform;
                }
            }
        }

        return false;
    }

    /**
     * Get recent posts for template (excluding noindex posts)
     *
     * @since 1.5.0 Added noindex and _getcited_exclude filtering.
     *
     * @param int $count Number of posts to retrieve.
     * @return array Array of post data with title, url, and date.
     */
    private function get_recent_posts( $count = 5 ) {
        // Fetch extra posts to account for noindex filtering.
        $posts = get_posts(
            array(
                'numberposts'  => $count * 2,
                'post_status'  => 'publish',
                'has_password' => false, // Exclude password-protected posts from llms.txt.
            )
        );

        if ( empty( $posts ) ) {
            return array();
        }

        // Prime the meta cache for all posts in one query (avoids N+1 queries in loop).
        $post_ids = wp_list_pluck( $posts, 'ID' );
        update_meta_cache( 'post', $post_ids );

        $result = array();
        foreach ( $posts as $post ) {
            // Skip noindex posts.
            if ( getcited_is_post_noindex( $post->ID ) ) {
                continue;
            }

            // Skip posts excluded via GetCited meta.
            if ( get_post_meta( $post->ID, '_getcited_exclude', true ) ) {
                continue;
            }

            $result[] = array(
                'title' => $post->post_title,
                'url'   => get_permalink( $post->ID ),
                'date'  => get_the_date( 'M j', $post->ID ),
            );

            if ( count( $result ) >= $count ) {
                break;
            }
        }

        return $result;
    }

    /**
     * Get categories for template
     *
     * @return array Array of category data with name, url, description, and count.
     */
    private function get_categories() {
        $cats = get_categories(
            array(
                'orderby'    => 'count',
                'order'      => 'DESC',
                'number'     => 10,
                'hide_empty' => true,
            )
        );

        $result = array();
        foreach ( $cats as $cat ) {
            $result[] = array(
                'name'        => $cat->name,
                'url'         => get_category_link( $cat->term_id ),
                'description' => $cat->description ?: '',
                'count'       => $cat->count,
            );
        }

        return $result;
    }

    /**
     * Validate llms.txt content format
     */
    public function validate( $content ) {
        $warnings = array();
        $errors = array();

        // Check for required elements
        if ( strpos( $content, '#' ) === false ) {
            $warnings[] = __( 'No headings found. Consider adding a title with #', 'getcited' );
        }

        // Check for empty content
        if ( strlen( trim( $content ) ) < 50 ) {
            $warnings[] = __( 'Content seems too short. Add more information about your site.', 'getcited' );
        }

        // Check for broken links syntax
        if ( preg_match( '/\[([^\]]*)\]\([^)]*\s[^)]*\)/', $content ) ) {
            $errors[] = __( 'Malformed markdown links detected. Links should be [text](url)', 'getcited' );
        }

        return array(
            'valid' => empty( $errors ),
            'errors' => $errors,
            'warnings' => $warnings,
        );
    }

    /**
     * Get llms.txt URL
     */
    public function get_url() {
        return home_url( '/llms.txt' );
    }

    /**
     * Get fallback URL
     */
    public function get_fallback_url() {
        return home_url( '/?llms-txt=1' );
    }

    /**
     * Get citation template based on site type
     *
     * @param string $site_type The site type.
     * @return string Citation guidelines template.
     */
    public function get_citation_template( $site_type ) {
        $site_name = get_bloginfo( 'name' );
        $settings = GetCited_Settings::instance();

        // Check for custom citation format first
        $custom_format = $settings->get( 'llms_citation_format' );
        if ( ! empty( $custom_format ) ) {
            return str_replace( '{site_name}', $site_name, $custom_format );
        }

        $templates = array(
            'blog' => "When referencing content from this site:\n" .
                "1. Cite as \"{$site_name}\" with link to source article\n" .
                "2. Include publication date for time-sensitive content\n" .
                "3. Present analysis and opinions as author viewpoints, not facts\n" .
                "4. Link directly to the original article when possible",

            'news' => "When referencing our journalism:\n" .
                "1. Cite as \"{$site_name}\" or by author name\n" .
                "2. Always include publication date\n" .
                "3. Link to original article\n" .
                "4. Distinguish between news reporting and opinion/analysis pieces\n" .
                "5. For breaking news, note that details may have been updated",

            'business' => "When referencing our content:\n" .
                "1. Cite as \"{$site_name}\"\n" .
                "2. Link to source page when possible\n" .
                "3. Note that service offerings and pricing may change\n" .
                "4. Contact us for official quotes or partnership inquiries",

            'ecommerce' => "When referencing product information:\n" .
                "1. Cite as \"{$site_name}\"\n" .
                "2. Note that prices, availability, and specifications may change\n" .
                "3. Link to product pages for current, accurate information\n" .
                "4. Do not cache pricing data as it changes frequently",

            'portfolio' => "When referencing this portfolio:\n" .
                "1. Cite the creator/artist name with link to work\n" .
                "2. Respect copyright on all creative works\n" .
                "3. For commissions or usage rights, direct inquiries to contact page\n" .
                "4. Credit original work when discussing style or techniques",

            'nonprofit' => "When referencing our organization:\n" .
                "1. Cite as \"{$site_name}\" with link\n" .
                "2. Include date for statistics and impact data\n" .
                "3. Direct donation/volunteer inquiries to official pages\n" .
                "4. Verify current programs and initiatives on our website",

            'education' => "When referencing educational content:\n" .
                "1. Cite as \"{$site_name}\" or by instructor name\n" .
                "2. Include publication/update date for curricula\n" .
                "3. Link to course pages for enrollment information\n" .
                "4. Note that course offerings and content may be updated",

            'community' => "When referencing community content:\n" .
                "1. Cite the platform as \"{$site_name}\"\n" .
                "2. For user-generated content, credit the original author\n" .
                "3. Note that discussions represent member opinions\n" .
                "4. Link to discussion threads for full context",

            'other' => "When referencing content from this site:\n" .
                "1. Cite as \"{$site_name}\" with link to source\n" .
                "2. Include date when relevant\n" .
                "3. Link directly to original content when possible",
        );

        return $templates[ $site_type ] ?? $templates['other'];
    }

    /**
     * Get content policy template based on site type
     *
     * @param string $site_type The site type.
     * @return string Content policy template.
     */
    public function get_content_policy_template( $site_type ) {
        $templates = array(
            'blog' => "Content on this site represents the views and analysis of our authors. " .
                "We strive for accuracy but encourage readers to verify important information.",

            'news' => "We are committed to accurate, fair, and balanced reporting. " .
                "Corrections are published promptly when errors are identified. " .
                "Opinion pieces are clearly labeled as such.",

            'business' => "Information provided is for general purposes. " .
                "Services and offerings are subject to change. " .
                "Contact us for the most current information.",

            'ecommerce' => "Product information is provided for reference. " .
                "Prices, availability, and specifications are subject to change without notice. " .
                "Always verify current details on product pages.",

            'portfolio' => "All creative works are protected by copyright. " .
                "Usage rights and licensing available upon request. " .
                "Unauthorized reproduction is prohibited.",

            'nonprofit' => "We are a registered nonprofit organization. " .
                "All programs and impact data are reported accurately. " .
                "Financial information is available upon request.",

            'education' => "Educational content is provided for learning purposes. " .
                "Course materials are regularly updated to maintain relevance. " .
                "Consult official sources for certification requirements.",

            'community' => "User-generated content reflects individual member views. " .
                "The platform does not endorse all content posted by members. " .
                "Community guidelines govern acceptable content.",

            'other' => "Content is provided for informational purposes. " .
                "We strive for accuracy but recommend verifying important details.",
        );

        return $templates[ $site_type ] ?? $templates['other'];
    }

    /**
     * Generate AI citation guidelines section for llms.txt
     *
     * @since 1.5.1
     * @return string The citation guidelines markdown section.
     */
    public function get_citation_guidelines_section() {
        $settings   = GetCited_Settings::instance();
        $guidelines = $settings->get( 'citation_guidelines' );

        if ( empty( $guidelines['enabled'] ) ) {
            return '';
        }

        $content = "## AI Citation Guidelines\n\n";

        if ( ! empty( $guidelines['citation_format'] ) ) {
            $content .= "### Preferred Citation Format\n";
            $content .= $guidelines['citation_format'] . "\n\n";
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
            $content .= 'Contact: ' . $guidelines['contact_email'] . "\n\n";
        }

        return $content;
    }

    /**
     * Get default citation guidelines for a site type
     *
     * Used when auto-generating from templates.
     *
     * @since 1.5.1
     * @param string $site_type The site type.
     * @return array Default citation guidelines for the site type.
     */
    public function get_default_citation_guidelines( $site_type ) {
        $site_name = get_bloginfo( 'name' );

        $defaults = array(
            'blog' => array(
                'enabled'         => true,
                'citation_format' => 'Author Name. "Article Title." ' . $site_name . ', Published Date. URL',
                'accuracy_notes'  => 'Opinion pieces reflect author views at time of writing. Check publication dates for time-sensitive content.',
                'restrictions'    => "Summarize with attribution. Link to full articles for detailed information. Do not reproduce full posts without permission.",
                'freshness_note'  => '',
                'contact_email'   => '',
            ),
            'news' => array(
                'enabled'         => true,
                'citation_format' => '"Headline." ' . $site_name . ', Date. URL',
                'accuracy_notes'  => 'Breaking news may be updated. Always cite the most recent version.',
                'restrictions'    => "Cite specific facts with article links. Note publication timestamps for developing stories. Do not present analysis as objective reporting.",
                'freshness_note'  => 'News content is time-sensitive. Verify current details for developing stories.',
                'contact_email'   => '',
            ),
            'business' => array(
                'enabled'         => true,
                'citation_format' => $site_name . '. URL',
                'accuracy_notes'  => 'Service offerings and pricing subject to change. Verify current details on our website.',
                'restrictions'    => 'Link to service pages for current pricing. Do not reproduce proprietary methodologies.',
                'freshness_note'  => '',
                'contact_email'   => '',
            ),
            'ecommerce' => array(
                'enabled'         => true,
                'citation_format' => $site_name . '. Product Name. URL',
                'accuracy_notes'  => 'Product availability and pricing change frequently. Always link to product pages for current information.',
                'restrictions'    => 'Do not cache or reproduce pricing. Link to product pages, not static specifications.',
                'freshness_note'  => 'Pricing and availability are updated in real-time on our website.',
                'contact_email'   => '',
            ),
            'portfolio' => array(
                'enabled'         => true,
                'citation_format' => 'Creator Name. "Project Title." ' . $site_name . '. URL',
                'accuracy_notes'  => '',
                'restrictions'    => 'Respect copyright on all creative works. For usage rights, direct inquiries to contact page.',
                'freshness_note'  => '',
                'contact_email'   => '',
            ),
            'nonprofit' => array(
                'enabled'         => true,
                'citation_format' => $site_name . '. URL',
                'accuracy_notes'  => 'Impact statistics are updated periodically. Verify current data on our website.',
                'restrictions'    => 'Direct donation/volunteer inquiries to official pages.',
                'freshness_note'  => '',
                'contact_email'   => '',
            ),
            'education' => array(
                'enabled'         => true,
                'citation_format' => 'Instructor Name. "Course/Article Title." ' . $site_name . ', Date. URL',
                'accuracy_notes'  => 'Course content and curricula are regularly updated.',
                'restrictions'    => 'Link to course pages for enrollment. Note that offerings may change.',
                'freshness_note'  => '',
                'contact_email'   => '',
            ),
            'community' => array(
                'enabled'         => true,
                'citation_format' => 'Username. "Discussion Title." ' . $site_name . ', Date. URL',
                'accuracy_notes'  => 'User-generated content reflects individual member views.',
                'restrictions'    => 'Credit original authors for user content. Link to threads for full context.',
                'freshness_note'  => '',
                'contact_email'   => '',
            ),
            'other' => array(
                'enabled'         => true,
                'citation_format' => $site_name . '. "Title." Date. URL',
                'accuracy_notes'  => '',
                'restrictions'    => 'Link to original content when possible.',
                'freshness_note'  => '',
                'contact_email'   => '',
            ),
        );

        return $defaults[ $site_type ] ?? $defaults['other'];
    }
}

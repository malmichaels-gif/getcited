<?php
/**
 * Robots.txt integration for GetCited
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Robots class
 */
class GetCited_Robots {

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
        // Hook into robots.txt generation with priority 99 to run after most SEO plugins
        add_filter( 'robots_txt', array( $this, 'append_rules' ), 99, 2 );

        // Auto-write physical file when crawler settings change
        // Use getcited_settings_saved for batch updates (ensures all settings are saved first)
        add_action( 'getcited_settings_saved', array( $this, 'maybe_auto_write_batch' ), 10, 2 );
        // Use getcited_setting_updated for single setting updates
        add_action( 'getcited_setting_updated', array( $this, 'maybe_auto_write' ), 10, 3 );
    }

    /**
     * Maybe auto-write robots.txt when a batch of settings is saved
     *
     * This is the preferred hook as it fires AFTER all settings are saved,
     * avoiding timing issues where one setting is checked before another is updated.
     *
     * @param array $new_settings All current settings after save.
     * @param array $old_settings All settings before save.
     */
    public function maybe_auto_write_batch( $new_settings, $old_settings ) {
        // Check if any robots-related settings changed
        $robots_keys = array( 'crawlers', 'custom_crawlers', 'robots_write_physical' );
        $changed = false;

        foreach ( $robots_keys as $key ) {
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

        // Check if auto-write is enabled using NEW value
        $write_physical = $new_settings['robots_write_physical'] ?? false;

        if ( ! $write_physical ) {
            return;
        }

        // Auto-write the file
        $this->auto_write_physical_file();
    }

    /**
     * Maybe auto-write robots.txt when a single setting changes
     *
     * @param string $key       The setting key that was updated.
     * @param mixed  $new_value The new value.
     * @param mixed  $old_value The old value.
     */
    public function maybe_auto_write( $key, $new_value, $old_value = null ) {
        // Only trigger on crawler-related settings
        if ( ! in_array( $key, array( 'crawlers', 'custom_crawlers', 'robots_write_physical' ), true ) ) {
            return;
        }

        $settings = GetCited_Settings::instance();

        // Check if auto-write is enabled
        if ( ! $settings->get( 'robots_write_physical' ) ) {
            return;
        }

        // Auto-write the file
        $this->auto_write_physical_file();
    }

    /**
     * Auto-write robots.txt with GetCited rules, preserving existing content
     *
     * @return array Result with success status and message
     */
    public function auto_write_physical_file() {
        global $wp_filesystem;

        $file_path = ABSPATH . 'robots.txt';

        // Check write permissions (also initializes filesystem)
        if ( ! $this->can_write_physical_file() ) {
            return array(
                'success' => false,
                'message' => __( 'Cannot write to robots.txt. Check file permissions.', 'getcited' ),
            );
        }

        // Filesystem is already initialized by can_write_physical_file()

        // Read existing content from physical file (if any)
        $existing = '';
        if ( $wp_filesystem->exists( $file_path ) ) {
            $existing = $wp_filesystem->get_contents( $file_path );
            if ( $existing === false ) {
                $existing = '';
            }
        }

        // If no physical file exists, get WordPress dynamic robots.txt content
        // This preserves sitemap rules from SEO plugins (Yoast, etc.)
        if ( empty( $existing ) ) {
            $existing = $this->get_wordpress_robots_content();
        }

        // Remove any existing GetCited section
        $marker_start = '# === GetCited AI Crawler Rules ===';
        $marker_end = '# === End GetCited Rules ===';
        $pattern = '/' . preg_quote( $marker_start, '/' ) . '.*?' . preg_quote( $marker_end, '/' ) . '\s*/s';
        $existing = preg_replace( $pattern, '', $existing );
        $existing = trim( $existing );

        // Generate new GetCited rules
        $getcited_rules = $this->generate_rules();

        // Combine: existing content first, then GetCited rules
        // This follows WordPress convention and keeps general rules at top
        if ( ! empty( $existing ) ) {
            $new_content = $existing . "\n\n" . $getcited_rules . "\n";
        } else {
            $new_content = $getcited_rules . "\n";
        }

        // Write file
        $result = $wp_filesystem->put_contents( $file_path, $new_content, FS_CHMOD_FILE );

        if ( ! $result ) {
            return array(
                'success' => false,
                'message' => __( 'Failed to write robots.txt file.', 'getcited' ),
            );
        }

        // Clear health check cache
        if ( class_exists( 'GetCited_Health_Check' ) ) {
            GetCited_Health_Check::instance()->invalidate_cache();
        }

        return array(
            'success' => true,
            'message' => __( 'robots.txt updated successfully', 'getcited' ),
        );
    }

    /**
     * Get WordPress dynamic robots.txt content (without GetCited rules)
     *
     * This fetches what WordPress would generate dynamically, including
     * sitemap rules from SEO plugins like Yoast.
     *
     * @return string The robots.txt content
     */
    private function get_wordpress_robots_content() {
        // Temporarily remove our filter to get base content
        remove_filter( 'robots_txt', array( $this, 'append_rules' ), 99 );

        try {
            // Capture WordPress robots.txt output
            $public = get_option( 'blog_public' );

            ob_start();
            // Trigger the robots_txt filter without our rules
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress filter
            $output = apply_filters( 'robots_txt', "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n", $public );
            ob_end_clean();

            return $output;
        } finally {
            // Always re-add our filter, even if an exception occurs
            add_filter( 'robots_txt', array( $this, 'append_rules' ), 99, 2 );
        }
    }

    /**
     * Append AI crawler rules to robots.txt
     */
    public function append_rules( $output, $public ) {
        // Only add rules if site is public
        if ( ! $public ) {
            return $output;
        }

        $rules = $this->generate_rules();
        
        if ( empty( $rules ) ) {
            return $output;
        }

        // Ensure there's a newline before our section
        if ( ! empty( $output ) && substr( $output, -1 ) !== "\n" ) {
            $output .= "\n";
        }

        $output .= "\n" . $rules;

        return $output;
    }

    /**
     * Generate robots.txt rules for AI crawlers
     */
    public function generate_rules() {
        $settings = GetCited_Settings::instance();
        $crawler_list = GetCited_Crawler_List::instance();
        
        $crawler_states = $settings->get( 'crawlers' );
        $custom_crawlers = $settings->get( 'custom_crawlers' );
        $all_crawlers = $crawler_list->get_all();

        $lines = array();
        $lines[] = '# === GetCited AI Crawler Rules ===';
        $lines[] = '# Manage these settings at: ' . admin_url( 'admin.php?page=getcited-crawlers' );
        $lines[] = '';

        // Process official crawlers
        foreach ( $all_crawlers as $crawler ) {
            $name = $crawler['name'];
            $user_agent = $crawler['user_agent'];
            $status = $crawler_states[ $name ] ?? 'allow';
            
            $lines[] = 'User-agent: ' . $user_agent;
            
            if ( $status === 'allow' ) {
                $lines[] = 'Allow: /';
            } else {
                $lines[] = 'Disallow: /';
            }
            
            $lines[] = '';
        }

        // Process custom crawlers
        if ( ! empty( $custom_crawlers ) ) {
            $lines[] = '# Custom crawlers';
            
            foreach ( $custom_crawlers as $crawler ) {
                if ( empty( $crawler['user_agent'] ) ) {
                    continue;
                }
                
                $lines[] = 'User-agent: ' . $crawler['user_agent'];
                
                if ( $crawler['action'] === 'allow' ) {
                    $lines[] = 'Allow: /';
                } else {
                    $lines[] = 'Disallow: /';
                }
                
                $lines[] = '';
            }
        }

        $lines[] = '# === End GetCited Rules ===';

        $rules = implode( "\n", $lines );

        return apply_filters( 'getcited_robots_txt_rules', $rules );
    }

    /**
     * Get preview of robots.txt rules (for admin display)
     */
    public function get_preview() {
        return $this->generate_rules();
    }

    /**
     * Get the full robots.txt content as it would appear
     */
    public function get_full_robots_txt() {
        // Get WordPress default output
        $public = get_option( 'blog_public' );
        
        ob_start();
        do_robots();
        $output = ob_get_clean();

        return $output;
    }

    /**
     * Check if a physical robots.txt file exists (which would override WordPress)
     */
    public function physical_file_exists() {
        $robots_path = ABSPATH . 'robots.txt';
        return file_exists( $robots_path );
    }

    /**
     * Get status info for dashboard
     */
    public function get_status() {
        $settings = GetCited_Settings::instance();
        $crawler_states = $settings->get( 'crawlers' );

        $allowed = 0;
        $blocked = 0;

        foreach ( $crawler_states as $status ) {
            if ( $status === 'allow' ) {
                $allowed++;
            } else {
                $blocked++;
            }
        }

        return array(
            'allowed' => $allowed,
            'blocked' => $blocked,
            'total' => count( $crawler_states ),
            'physical_file_exists' => $this->physical_file_exists(),
        );
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
     * Check if we can write to robots.txt
     *
     * @return bool
     */
    public function can_write_physical_file() {
        global $wp_filesystem;

        if ( ! $this->init_filesystem() ) {
            return false;
        }

        $file_path = ABSPATH . 'robots.txt';

        // If file exists, check if writable
        if ( $wp_filesystem->exists( $file_path ) ) {
            return $wp_filesystem->is_writable( $file_path );
        }

        // If file doesn't exist, check if directory is writable
        return $wp_filesystem->is_writable( ABSPATH );
    }

    /**
     * Add GetCited rules to physical robots.txt file
     *
     * @return array Result with success status and message
     */
    public function add_rules_to_physical_file() {
        $file_path = ABSPATH . 'robots.txt';

        // Check write permissions
        if ( ! $this->can_write_physical_file() ) {
            return array(
                'success' => false,
                'message' => __( 'Cannot write to robots.txt. Check file permissions.', 'getcited' ),
                'details' => __( 'Your hosting may require FTP credentials for file operations. Try adding rules manually, or contact your host about direct file write permissions.', 'getcited' ),
                'show_manual_fallback' => true,
            );
        }

        // Initialize WP_Filesystem
        global $wp_filesystem;
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if ( ! WP_Filesystem() ) {
            return array(
                'success' => false,
                'message' => __( 'Could not initialize filesystem.', 'getcited' ),
                'details' => __( 'Your hosting may require FTP credentials for file operations. Try adding rules manually, or contact your host about direct file write permissions.', 'getcited' ),
                'show_manual_fallback' => true,
            );
        }

        // Get current content from physical file or WordPress dynamic output
        $content = '';
        if ( $wp_filesystem->exists( $file_path ) ) {
            $content = $wp_filesystem->get_contents( $file_path );
            if ( $content === false ) {
                return array(
                    'success' => false,
                    'message' => __( 'Could not read existing robots.txt file.', 'getcited' ),
                );
            }
        }

        // If no physical file exists, get WordPress dynamic robots.txt content
        // This preserves sitemap rules from SEO plugins (Yoast, etc.)
        if ( empty( $content ) ) {
            $content = $this->get_wordpress_robots_content();
        }

        // Generate our rules
        $rules = $this->generate_rules();
        $marker_start = '# === GetCited AI Crawler Rules ===';
        $marker_end = '# === End GetCited Rules ===';

        // Check if our rules already exist
        if ( strpos( $content, $marker_start ) !== false ) {
            // Replace existing rules
            $pattern = '/' . preg_quote( $marker_start, '/' ) . '.*?' . preg_quote( $marker_end, '/' ) . '/s';
            $new_content = preg_replace( $pattern, $rules, $content );
            $action = 'updated';
        } else {
            // Remove our section first (in case it exists without markers)
            $content = preg_replace( '/' . preg_quote( $marker_start, '/' ) . '.*?' . preg_quote( $marker_end, '/' ) . '\s*/s', '', $content );
            $content = trim( $content );

            // Append GetCited rules after existing content
            if ( ! empty( $content ) ) {
                $new_content = $content . "\n\n" . $rules;
            } else {
                $new_content = $rules;
            }
            $action = 'added';
        }

        // Write file
        $result = $wp_filesystem->put_contents( $file_path, $new_content, FS_CHMOD_FILE );

        if ( ! $result ) {
            return array(
                'success' => false,
                'message' => __( 'Failed to write robots.txt file.', 'getcited' ),
            );
        }

        // Clear health check cache so it re-evaluates
        GetCited_Health_Check::instance()->invalidate_cache();

        return array(
            'success' => true,
            'action' => $action,
            'message' => $action === 'updated'
                ? __( 'GetCited rules updated in robots.txt', 'getcited' )
                : __( 'GetCited rules added to robots.txt', 'getcited' ),
        );
    }

    /**
     * Remove GetCited rules from physical robots.txt file
     *
     * @return array Result with success status and message
     */
    public function remove_rules_from_physical_file() {
        $file_path = ABSPATH . 'robots.txt';

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
                'message' => __( 'No robots.txt file exists.', 'getcited' ),
            );
        }

        // Check write permissions using WP_Filesystem
        if ( ! $wp_filesystem->is_writable( $file_path ) ) {
            return array(
                'success' => false,
                'message' => __( 'Cannot write to robots.txt. Check file permissions.', 'getcited' ),
            );
        }

        $content = $wp_filesystem->get_contents( $file_path );
        if ( $content === false ) {
            return array(
                'success' => false,
                'message' => __( 'Could not read robots.txt file.', 'getcited' ),
            );
        }

        $marker_start = '# === GetCited AI Crawler Rules ===';
        $marker_end = '# === End GetCited Rules ===';

        // Check if our rules exist
        if ( strpos( $content, $marker_start ) === false ) {
            return array(
                'success' => true,
                'message' => __( 'No GetCited rules found in robots.txt.', 'getcited' ),
            );
        }

        // Remove our rules section (including surrounding whitespace)
        $pattern = '/\n*' . preg_quote( $marker_start, '/' ) . '.*?' . preg_quote( $marker_end, '/' ) . '\n*/s';
        $new_content = preg_replace( $pattern, "\n", $content );
        $new_content = trim( $new_content ) . "\n";

        // Write file
        $result = $wp_filesystem->put_contents( $file_path, $new_content, FS_CHMOD_FILE );

        if ( ! $result ) {
            return array(
                'success' => false,
                'message' => __( 'Failed to update robots.txt file.', 'getcited' ),
            );
        }

        return array(
            'success' => true,
            'message' => __( 'GetCited rules removed from robots.txt', 'getcited' ),
        );
    }

    /**
     * Check if GetCited rules exist in physical robots.txt
     *
     * @return bool
     */
    public function rules_exist_in_physical_file() {
        $file_path = ABSPATH . 'robots.txt';

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
        return $content && strpos( $content, '# === GetCited AI Crawler Rules ===' ) !== false;
    }
}

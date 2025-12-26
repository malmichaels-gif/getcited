<?php
/**
 * Plugin Name: GetCited — AI Visibility
 * Plugin URI: https://heytc.com/getcited
 * Description: Get your content cited by ChatGPT, Gemini, Grok, Perplexity, and more. Manage AI crawlers, generate llms.txt, and optimize schema for AI search engines.
 * Version: 1.9.9.25
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: HeyTC
 * Author URI: https://heytc.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: getcited
 * Domain Path: /languages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'GETCITED_VERSION', '1.9.9.25' );
define( 'GETCITED_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GETCITED_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GETCITED_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
final class GetCited {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Plugin settings
     */
    private $settings;

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
        $this->register_tables();
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Register custom database tables as $wpdb properties
     *
     * This allows using $wpdb->getcited_llms_requests in queries,
     * which is recognized as safe by WordPress coding standards sniffers.
     */
    private function register_tables() {
        global $wpdb;
        $wpdb->getcited_llms_requests = $wpdb->prefix . 'getcited_llms_requests';
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once GETCITED_PLUGIN_DIR . 'includes/class-settings.php';
        require_once GETCITED_PLUGIN_DIR . 'includes/class-crawler-list.php';
        require_once GETCITED_PLUGIN_DIR . 'includes/class-robots.php';
        require_once GETCITED_PLUGIN_DIR . 'includes/class-llms-txt.php';
        require_once GETCITED_PLUGIN_DIR . 'includes/class-schema.php';
        require_once GETCITED_PLUGIN_DIR . 'includes/class-schema-detector.php';
        require_once GETCITED_PLUGIN_DIR . 'includes/class-author-fields.php';
        require_once GETCITED_PLUGIN_DIR . 'includes/class-request-logger.php';
        require_once GETCITED_PLUGIN_DIR . 'includes/class-visibility-score.php';
        require_once GETCITED_PLUGIN_DIR . 'includes/class-conflict-detector.php';
        require_once GETCITED_PLUGIN_DIR . 'includes/class-health-check.php';
        require_once GETCITED_PLUGIN_DIR . 'includes/class-citability.php';
        require_once GETCITED_PLUGIN_DIR . 'includes/class-wizard.php';
        require_once GETCITED_PLUGIN_DIR . 'includes/class-dashboard.php';
        require_once GETCITED_PLUGIN_DIR . 'includes/class-pro-teaser.php';
        require_once GETCITED_PLUGIN_DIR . 'includes/class-site-scanner.php';
        require_once GETCITED_PLUGIN_DIR . 'includes/helpers.php';

        // WP-CLI commands
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once GETCITED_PLUGIN_DIR . 'includes/class-cli.php';
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/deactivation
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // Multisite: Handle new site creation.
        add_action( 'wp_initialize_site', array( $this, 'on_new_site' ), 10, 1 );

        // Initialize components after plugins loaded
        add_action( 'plugins_loaded', array( $this, 'init_components' ) );

        // Register rewrite rules
        add_action( 'init', array( $this, 'register_rewrites' ) );

        // Admin menu
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

        // Auto-analyze on first admin load (deferred so all plugins are loaded)
        add_action( 'admin_init', array( $this, 'maybe_auto_analyze' ) );

        // Admin assets
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Schedule cron
        add_action( 'getcited_daily_cron', array( $this, 'run_daily_tasks' ) );
        add_action( 'getcited_weekly_schema_scan', array( $this, 'run_schema_scan' ) );
        add_action( 'getcited_weekly_llms_refresh', array( $this, 'run_llms_refresh' ) );

        // REST API
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

        // Admin notice on Permalinks page when rewrite rules need flushing
        add_action( 'admin_notices', array( $this, 'maybe_show_permalinks_notice' ) );
    }

    /**
     * Plugin activation
     *
     * @param bool $network_wide Whether to activate network-wide on multisite.
     */
    public function activate( $network_wide = false ) {
        // Handle multisite network activation.
        if ( is_multisite() && $network_wide ) {
            $sites = get_sites( array( 'number' => 0 ) );
            foreach ( $sites as $site ) {
                switch_to_blog( $site->blog_id );
                $this->activate_single_site();
                restore_current_blog();
            }
        } else {
            $this->activate_single_site();
        }
    }

    /**
     * Activate plugin for a single site
     */
    private function activate_single_site() {
        // Initialize default settings
        $settings = GetCited_Settings::instance();
        $settings->init_defaults();

        // Generate site UUID if not exists
        $current = $settings->get_all();
        if ( empty( $current['site_uuid'] ) ) {
            $settings->set( 'site_uuid', wp_generate_uuid4() );
        }

        // Schedule cron jobs
        if ( ! wp_next_scheduled( 'getcited_daily_cron' ) ) {
            wp_schedule_event( time(), 'daily', 'getcited_daily_cron' );
        }

        // Weekly schema detection scan
        if ( ! wp_next_scheduled( 'getcited_weekly_schema_scan' ) ) {
            wp_schedule_event( time(), 'weekly', 'getcited_weekly_schema_scan' );
        }

        // Weekly llms.txt content refresh (keeps recent posts current)
        if ( ! wp_next_scheduled( 'getcited_weekly_llms_refresh' ) ) {
            wp_schedule_event( time(), 'weekly', 'getcited_weekly_llms_refresh' );
        }

        // Run initial schema detection
        GetCited_Schema_Detector::instance()->run_detection();

        // Detect existing llms.txt file
        $this->detect_existing_llms_txt( $settings );

        // Create custom database tables
        $this->create_tables();

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

    /**
     * Detect existing llms.txt file on activation
     *
     * Simplified v1.9.9.19: Always import existing files, backup originals.
     * If the file was created by an SEO plugin, set a conflict warning flag.
     *
     * @param GetCited_Settings $settings Settings instance.
     */
    private function detect_existing_llms_txt( $settings ) {
        $llms_txt = GetCited_Llms_Txt::instance();

        // Check if physical file exists.
        if ( ! $llms_txt->physical_file_exists() ) {
            return;
        }

        // Check if it's already our file.
        if ( $llms_txt->is_our_physical_file() ) {
            return;
        }

        // Get content.
        $content = $llms_txt->get_physical_file_content();
        if ( empty( $content ) ) {
            return;
        }

        // Check if this is from an SEO plugin (for conflict warning).
        $seo_plugin = $llms_txt->detect_seo_plugin_source( $content );

        // Always import and backup - simplified flow.
        $settings->set( 'llms_txt_content', $content );

        // Backup the physical file (removes it from disk so GetCited serves dynamically).
        $llms_txt->backup_physical_file();

        // Set transient for post-setup notice.
        set_transient( 'getcited_llms_imported_notice', true, DAY_IN_SECONDS );

        // If SEO plugin detected, set conflict warning for monitoring.
        if ( $seo_plugin ) {
            $settings->set( 'seo_plugin_conflict', $seo_plugin );
        }
    }

    /**
     * Handle new site creation on multisite
     *
     * @param WP_Site $new_site New site object.
     */
    public function on_new_site( $new_site ) {
        if ( ! is_plugin_active_for_network( plugin_basename( __FILE__ ) ) ) {
            return;
        }

        switch_to_blog( $new_site->blog_id );
        $this->activate_single_site();
        restore_current_blog();
    }

    /**
     * Plugin deactivation
     *
     * @param bool $network_wide Whether deactivating network-wide on multisite.
     */
    public function deactivate( $network_wide = false ) {
        // Handle multisite network deactivation.
        if ( is_multisite() && $network_wide ) {
            $sites = get_sites( array( 'number' => 0 ) );
            foreach ( $sites as $site ) {
                switch_to_blog( $site->blog_id );
                $this->deactivate_single_site();
                restore_current_blog();
            }
        } else {
            $this->deactivate_single_site();
        }
    }

    /**
     * Deactivate plugin for a single site
     */
    private function deactivate_single_site() {
        // Remove cron jobs
        $timestamp = wp_next_scheduled( 'getcited_daily_cron' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'getcited_daily_cron' );
        }

        $timestamp = wp_next_scheduled( 'getcited_weekly_schema_scan' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'getcited_weekly_schema_scan' );
        }

        $timestamp = wp_next_scheduled( 'getcited_weekly_llms_refresh' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'getcited_weekly_llms_refresh' );
        }

        // Flush rewrite rules
        flush_rewrite_rules();

        // Fire deactivation hook
        do_action( 'getcited_deactivated' );
    }

    /**
     * Initialize plugin components
     */
    public function init_components() {
        // Settings
        $this->settings = GetCited_Settings::instance();

        // Initialize components
        GetCited_Crawler_List::instance();
        GetCited_Robots::instance();
        GetCited_Llms_Txt::instance();
        GetCited_Schema::instance();
        GetCited_Schema_Detector::instance();
        GetCited_Author_Fields::instance();
        GetCited_Request_Logger::instance();
        GetCited_Visibility_Score::instance();
        GetCited_Health_Check::instance();
        GetCited_Citability::instance();
        GetCited_Wizard::instance();
        GetCited_Dashboard::instance();
        GetCited_Pro_Teaser::instance();
        GetCited_Site_Scanner::instance();

        // Maybe run migrations
        $this->maybe_migrate();
    }

    /**
     * Register rewrite rules for llms.txt
     */
    public function register_rewrites() {
        add_rewrite_rule(
            '^llms\.txt$',
            'index.php?getcited_llms_txt=1',
            'top'
        );
        add_rewrite_tag( '%getcited_llms_txt%', '1' );
    }

    /**
     * Register admin menu
     */
    public function register_admin_menu() {
        $capability = apply_filters( 'getcited_admin_capability', 'manage_options' );
        $brand_name = apply_filters( 'getcited_brand_name', 'GetCited' );
        $menu_icon = apply_filters( 'getcited_menu_icon', 'dashicons-format-quote' );

        // Main menu
        add_menu_page(
            $brand_name,
            $brand_name,
            $capability,
            'getcited',
            array( GetCited_Dashboard::instance(), 'render_page' ),
            $menu_icon,
            30
        );

        // Submenu pages
        add_submenu_page(
            'getcited',
            __( 'Dashboard', 'getcited' ),
            __( 'Dashboard', 'getcited' ),
            $capability,
            'getcited',
            array( GetCited_Dashboard::instance(), 'render_page' )
        );

        add_submenu_page(
            'getcited',
            __( 'AI Visibility', 'getcited' ),
            __( 'AI Visibility', 'getcited' ),
            $capability,
            'getcited-llms-txt',
            array( GetCited_Dashboard::instance(), 'render_llms_txt_page' )
        );

        add_submenu_page(
            'getcited',
            __( 'Citability', 'getcited' ),
            __( 'Citability', 'getcited' ),
            $capability,
            'getcited-citability',
            array( GetCited_Dashboard::instance(), 'render_citability_page' )
        );

        add_submenu_page(
            'getcited',
            __( 'AI Crawlers', 'getcited' ),
            __( 'AI Crawlers', 'getcited' ),
            $capability,
            'getcited-crawlers',
            array( GetCited_Dashboard::instance(), 'render_crawlers_page' )
        );

        add_submenu_page(
            'getcited',
            __( 'Schema', 'getcited' ),
            __( 'Schema', 'getcited' ),
            $capability,
            'getcited-schema',
            array( GetCited_Dashboard::instance(), 'render_schema_page' )
        );

        add_submenu_page(
            'getcited',
            __( 'Settings', 'getcited' ),
            __( 'Settings', 'getcited' ),
            $capability,
            'getcited-settings',
            array( GetCited_Dashboard::instance(), 'render_settings_page' )
        );
    }

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

        // Enqueue WordPress media library for logo upload
        if ( $is_getcited_page ) {
            wp_enqueue_media();
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
            'adminUrl' => admin_url(),
            'restUrl' => rest_url( 'getcited/v1/' ),
            'nonce' => wp_create_nonce( 'getcited_admin' ),
            'restNonce' => wp_create_nonce( 'wp_rest' ),
            'strings' => array(
                'saving' => __( 'Saving...', 'getcited' ),
                'saved' => __( 'Saved', 'getcited' ),
                'error' => __( 'An error occurred. Please try again.', 'getcited' ),
                'checking' => __( 'Checking...', 'getcited' ),
                'analyzing' => __( 'Analyzing...', 'getcited' ),
                'copied' => __( 'Copied!', 'getcited' ),
                'copy_failed' => __( 'Copy failed. Please select and copy manually.', 'getcited' ),
                'adding' => __( 'Adding...', 'getcited' ),
                'removing' => __( 'Removing...', 'getcited' ),
                'hide_rules' => __( 'Hide Rules', 'getcited' ),
                'show_rules' => __( 'Show Rules', 'getcited' ),
                'preview_rules' => __( 'Preview Rules', 'getcited' ),
                'confirm_remove_rules' => __( 'Are you sure you want to remove GetCited rules from robots.txt?', 'getcited' ),
                'apply_schema_preset' => __( 'Apply recommended schema settings for this site type?', 'getcited' ),
                // Site scanner strings
                'scanning' => __( 'Scanning...', 'getcited' ),
                'scan_success' => __( 'Site scanned successfully!', 'getcited' ),
                'scan_failed' => __( 'Scan failed. Please try again.', 'getcited' ),
                'scan_complete' => __( 'Scan Complete', 'getcited' ),
                'scan_review' => __( 'Review the generated content and save when ready.', 'getcited' ),
                'unsaved' => __( 'Unsaved changes', 'getcited' ),
                'pages' => __( 'pages', 'getcited' ),
                'posts' => __( 'posts', 'getcited' ),
                'categories' => __( 'categories', 'getcited' ),
                'menu_items' => __( 'menu items', 'getcited' ),
                'social_links' => __( 'social links', 'getcited' ),
                'select_logo' => __( 'Select Logo', 'getcited' ),
                'use_logo' => __( 'Use as Logo', 'getcited' ),
                'load_more' => __( 'Load More Posts', 'getcited' ),
                'loading' => __( 'Loading...', 'getcited' ),
                'no_more_posts' => __( 'No more posts to show', 'getcited' ),
                // Schema detection strings
                'rescanning' => __( 'Rescanning...', 'getcited' ),
                'rescan_complete' => __( 'Scan complete', 'getcited' ),
                'rescan_failed' => __( 'Scan failed. Please try again.', 'getcited' ),
            ),
        ) );
    }

    /**
     * Show admin notice on Permalinks page when rewrite rules need flushing
     *
     * @since 1.6.7
     */
    public function maybe_show_permalinks_notice() {
        // Only show on Permalinks page.
        $screen = get_current_screen();
        if ( ! $screen || 'options-permalink' !== $screen->id ) {
            return;
        }

        // Check if llms.txt rewrite rule exists.
        global $wp_rewrite;
        $rules = $wp_rewrite->wp_rewrite_rules();

        if ( isset( $rules['^llms\.txt$'] ) ) {
            return; // Rules are OK.
        }

        // Show the notice.
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e( 'GetCited:', 'getcited' ); ?></strong>
                <?php esc_html_e( 'Click "Save Changes" below to enable the /llms.txt URL for AI crawlers.', 'getcited' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Run daily scheduled tasks
     */
    public function run_daily_tasks() {
        // Update crawler list from remote
        GetCited_Crawler_List::instance()->fetch_remote_list();

        // Run health checks
        GetCited_Health_Check::instance()->run_checks();

        // Check for SEO plugin conflicts (stores result for admin notice)
        $this->check_seo_plugin_conflict();

        // Clean up old request log entries
        if ( class_exists( 'GetCited_Request_Logger' ) ) {
            GetCited_Request_Logger::instance()->cleanup_old_requests();
        }

        // Clean up expired request duplicate-detection transients.
        // These have 1-hour TTL but WordPress doesn't auto-clean expired transients
        // without an external object cache, causing options table bloat over time.
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch cleanup of expired transients requires direct query.
        $wpdb->query(
            "DELETE a, b FROM {$wpdb->options} a
             LEFT JOIN {$wpdb->options} b ON b.option_name = REPLACE(a.option_name, '_transient_timeout_', '_transient_')
             WHERE a.option_name LIKE '_transient_timeout_getcited_req_%'
             AND a.option_value < UNIX_TIMESTAMP()"
        );

        // Fire hook for extensions
        do_action( 'getcited_daily_tasks' );
    }

    /**
     * Check for SEO plugin conflicts and store result
     *
     * Called daily via cron. Stores conflict info so admin notice
     * can show on all admin pages, not just GetCited pages.
     *
     * @since 1.9.9.22
     */
    private function check_seo_plugin_conflict() {
        $llms     = GetCited_Llms_Txt::instance();
        $conflict = $llms->check_seo_plugin_conflict();

        if ( $conflict ) {
            update_option( 'getcited_seo_conflict_detected', $conflict, false );
        } else {
            delete_option( 'getcited_seo_conflict_detected' );
        }
    }

    /**
     * Run weekly schema detection scan
     */
    public function run_schema_scan() {
        GetCited_Schema_Detector::instance()->refresh_detection();

        // Fire hook for extensions
        do_action( 'getcited_schema_scan' );
    }

    /**
     * Run weekly llms.txt content refresh
     *
     * Regenerates llms.txt content to keep recent posts section current.
     * Particularly useful for high-volume sites that publish frequently.
     */
    public function run_llms_refresh() {
        $settings = GetCited_Settings::instance();

        // Only refresh if llms.txt is enabled
        if ( ! $settings->get( 'llms_txt_enabled' ) ) {
            return;
        }

        $llms      = GetCited_Llms_Txt::instance();
        $site_type = $settings->get( 'site_type' );

        // Regenerate content with fresh recent posts.
        $content = $llms->generate_template( $site_type );
        $settings->set( 'llms_txt_content', $content );

        // Fire hook for extensions.
        do_action( 'getcited_llms_refresh' );
    }

    /**
     * Auto-analyze recent posts on first admin load
     *
     * Deferred from activation hook so all plugins (Yoast, etc.) are loaded
     * and can provide meta descriptions and schema data.
     */
    public function maybe_auto_analyze() {
        // Only run once
        if ( get_option( 'getcited_auto_analyzed_done' ) ) {
            return;
        }

        // Don't run until wizard is completed (site type affects scoring)
        $settings = GetCited_Settings::instance();
        if ( ! $settings->get( 'wizard_completed' ) ) {
            return;
        }

        // Run the auto-analyze
        GetCited_Citability::instance()->auto_analyze_recent_posts( 5 );
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Public status endpoint
        register_rest_route( 'getcited/v1', '/status', array(
            'methods' => 'GET',
            'callback' => array( $this, 'api_status' ),
            'permission_callback' => '__return_true',
        ) );

        // Public llms.txt endpoint (for headless WordPress / JAMstack sites)
        register_rest_route( 'getcited/v1', '/llms-txt', array(
            'methods' => 'GET',
            'callback' => array( $this, 'api_llms_txt' ),
            'permission_callback' => '__return_true',
        ) );

        // Citability endpoint
        register_rest_route( 'getcited/v1', '/citability/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array( $this, 'api_citability' ),
            'permission_callback' => array( $this, 'api_permissions' ),
        ) );

        // Settings endpoint (for future API access)
        register_rest_route( 'getcited/v1', '/settings', array(
            'methods' => array( 'GET', 'POST' ),
            'callback' => array( $this, 'api_settings' ),
            'permission_callback' => array( $this, 'api_permissions' ),
        ) );

        // Crawlers endpoint
        register_rest_route( 'getcited/v1', '/crawlers', array(
            'methods' => 'GET',
            'callback' => array( $this, 'api_crawlers' ),
            'permission_callback' => array( $this, 'api_permissions' ),
        ) );

        // Crawler toggle endpoint
        register_rest_route( 'getcited/v1', '/crawlers/(?P<name>[a-zA-Z0-9_-]+)', array(
            'methods' => 'POST',
            'callback' => array( $this, 'api_toggle_crawler' ),
            'permission_callback' => array( $this, 'api_permissions' ),
        ) );

        // Future Pro endpoints (registered but return upgrade notice)
        register_rest_route( 'getcited/v1', '/citations', array(
            'methods' => 'GET',
            'callback' => array( $this, 'api_pro_required' ),
            'permission_callback' => array( $this, 'api_permissions' ),
        ) );

        register_rest_route( 'getcited/v1', '/traffic', array(
            'methods' => 'GET',
            'callback' => array( $this, 'api_pro_required' ),
            'permission_callback' => array( $this, 'api_permissions' ),
        ) );
    }

    /**
     * API permission check
     */
    public function api_permissions() {
        return current_user_can( apply_filters( 'getcited_admin_capability', 'manage_options' ) );
    }

    /**
     * Check rate limit for public API endpoints
     *
     * @param string $endpoint Endpoint identifier for rate limit tracking.
     * @param int    $limit    Maximum requests allowed in window (default 60).
     * @param int    $window   Time window in seconds (default 60).
     * @return true|WP_Error True if allowed, WP_Error if rate limited.
     */
    private function check_rate_limit( $endpoint, $limit = 60, $window = 60 ) {
        $ip = $this->get_client_ip();
        $key = 'getcited_rate_' . md5( $endpoint . $ip );
        $count = (int) get_transient( $key );

        if ( $count >= $limit ) {
            return new WP_Error(
                'rate_limited',
                __( 'Too many requests. Please try again later.', 'getcited' ),
                array( 'status' => 429 )
            );
        }

        set_transient( $key, $count + 1, $window );
        return true;
    }

    /**
     * Get client IP address
     *
     * @return string IP address or empty string.
     */
    private function get_client_ip() {
        $headers = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        );

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $ip = wp_unslash( $_SERVER[ $header ] );
                // Handle comma-separated IPs (X-Forwarded-For)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * API: Status endpoint (public, minimal info)
     *
     * Returns only basic info safe for public consumption.
     * Detailed config requires authentication via /settings endpoint.
     */
    public function api_status() {
        // Rate limit: 60 requests per minute per IP
        $rate_check = $this->check_rate_limit( 'status', 60, 60 );
        if ( is_wp_error( $rate_check ) ) {
            return $rate_check;
        }

        $settings = GetCited_Settings::instance();
        $health   = GetCited_Health_Check::instance()->get_status();

        // Public response: only basic operational status, no internal config.
        return rest_ensure_response( array(
            'version'  => GETCITED_VERSION,
            'llms_txt' => array(
                'enabled' => (bool) $settings->get( 'llms_txt_enabled' ),
                'url'     => $settings->get( 'llms_txt_enabled' ) ? home_url( '/llms.txt' ) : null,
            ),
            'status'   => ! empty( $health['llms_txt'] ) && 'ok' === $health['llms_txt'] ? 'active' : 'configured',
        ) );
    }

    /**
     * API: llms.txt content (for headless WordPress / JAMstack)
     *
     * Returns llms.txt content as plain text with appropriate headers.
     * Useful for decoupled frontends that need to serve llms.txt.
     */
    public function api_llms_txt() {
        // Rate limit: 120 requests per minute per IP (higher for content endpoint)
        $rate_check = $this->check_rate_limit( 'llms-txt', 120, 60 );
        if ( is_wp_error( $rate_check ) ) {
            return $rate_check;
        }

        $settings = GetCited_Settings::instance();

        // Check if llms.txt is enabled
        if ( ! $settings->get( 'llms_txt_enabled' ) ) {
            return new WP_Error(
                'llms_txt_disabled',
                __( 'llms.txt is disabled', 'getcited' ),
                array( 'status' => 404 )
            );
        }

        $content = $settings->get( 'llms_txt_content' );

        if ( empty( $content ) ) {
            return new WP_Error(
                'llms_txt_empty',
                __( 'llms.txt content is empty', 'getcited' ),
                array( 'status' => 404 )
            );
        }

        // Log this request as an AI crawler visit
        if ( class_exists( 'GetCited_Request_Logger' ) ) {
            GetCited_Request_Logger::instance()->log_llms_request();
        }

        // Return as plain text with appropriate headers
        $response = new WP_REST_Response( $content );
        $response->set_headers( array(
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
            'X-Robots-Tag' => 'noindex',
        ) );

        return $response;
    }

    /**
     * API: Citability score
     */
    public function api_citability( $request ) {
        $post_id = absint( $request['id'] );
        $post = get_post( $post_id );

        if ( ! $post ) {
            return new WP_Error( 'not_found', __( 'Post not found', 'getcited' ), array( 'status' => 404 ) );
        }

        $score = GetCited_Citability::instance()->analyze_post( $post_id );

        return rest_ensure_response( $score );
    }

    /**
     * API: Get/update settings
     */
    public function api_settings( $request ) {
        $settings = GetCited_Settings::instance();

        if ( $request->get_method() === 'POST' ) {
            $params = $request->get_json_params();
            foreach ( $params as $key => $value ) {
                $settings->set( $key, $value );
            }
            do_action( 'getcited_settings_saved', $settings->get_all(), $params );
        }

        return rest_ensure_response( $settings->get_all() );
    }

    /**
     * API: Get crawlers
     */
    public function api_crawlers() {
        $crawler_list = GetCited_Crawler_List::instance();
        $settings = GetCited_Settings::instance();
        
        $crawlers = $crawler_list->get_all();
        $states = $settings->get( 'crawlers' );

        foreach ( $crawlers as &$crawler ) {
            $crawler['status'] = $states[ $crawler['name'] ] ?? 'allow';
        }

        return rest_ensure_response( array(
            'version' => $crawler_list->get_version(),
            'updated' => $crawler_list->get_last_updated(),
            'crawlers' => $crawlers,
        ) );
    }

    /**
     * API: Toggle crawler
     */
    public function api_toggle_crawler( $request ) {
        $name = sanitize_text_field( $request['name'] );
        $params = $request->get_json_params();
        $status = isset( $params['status'] ) && in_array( $params['status'], array( 'allow', 'block' ), true ) 
            ? $params['status'] 
            : 'allow';

        $settings = GetCited_Settings::instance();
        $crawlers = $settings->get( 'crawlers' );
        $crawlers[ $name ] = $status;
        $settings->set( 'crawlers', $crawlers );

        return rest_ensure_response( array(
            'success' => true,
            'crawler' => $name,
            'status' => $status,
        ) );
    }

    /**
     * API: Pro feature required
     */
    public function api_pro_required() {
        return new WP_Error(
            'pro_required',
            __( 'This feature requires GetCited Pro', 'getcited' ),
            array( 'status' => 403 )
        );
    }

    /**
     * Run database migrations if needed
     */
    private function maybe_migrate() {
        $settings = GetCited_Settings::instance();
        $db_version = $settings->get( 'db_version' );

        if ( version_compare( $db_version, '1.0', '<' ) ) {
            // Fresh install, nothing to migrate
            $settings->set( 'db_version', '1.0' );
        }

        // v1.4.5 migration: Create request log table
        if ( version_compare( $db_version, '1.4.5', '<' ) ) {
            $this->create_tables();
            $settings->set( 'db_version', '1.4.5' );
        }

        // v1.4.7: Add index on category column for faster aggregation queries
        if ( version_compare( $db_version, '1.4.7', '<' ) ) {
            $this->add_category_index();
            $settings->set( 'db_version', '1.4.7' );
        }

        // v1.9.9.18: Remove physical llms.txt files (now served dynamically).
        if ( version_compare( $db_version, '1.9.9.18', '<' ) ) {
            $this->migrate_remove_physical_llms_txt();
            $settings->set( 'db_version', '1.9.9.18' );
        }
    }

    /**
     * Migration: Remove physical llms.txt file if it was created by GetCited
     *
     * As of v1.9.9.18, llms.txt is always served dynamically via WordPress.
     * Physical files would be served by nginx/Apache directly, bypassing updates.
     *
     * @since 1.9.9.18
     */
    private function migrate_remove_physical_llms_txt() {
        $llms = GetCited_Llms_Txt::instance();

        // Only delete if file exists and was created by us.
        if ( $llms->physical_file_exists() && $llms->is_our_physical_file() ) {
            $llms->delete_physical_file();
        }
    }

    /**
     * Create custom database tables
     */
    private function create_tables() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'getcited_llms_requests';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_time DATETIME NOT NULL,
            user_agent VARCHAR(500) NOT NULL,
            bot_name VARCHAR(100) DEFAULT NULL,
            category VARCHAR(20) DEFAULT 'unknown',
            ip_hash VARCHAR(64) DEFAULT NULL,
            INDEX idx_request_time (request_time),
            INDEX idx_bot_name (bot_name),
            INDEX idx_category (category)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Add index on category column (migration for v1.4.7)
     */
    private function add_category_index() {
        global $wpdb;

        // Check if index already exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration requires direct query
        $index_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = %s AND table_name = %s AND index_name = 'idx_category'",
                DB_NAME,
                $wpdb->getcited_llms_requests
            )
        );

        if ( ! $index_exists ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Migration requires direct DDL
            $wpdb->query( "ALTER TABLE {$wpdb->getcited_llms_requests} ADD INDEX idx_category (category)" );
        }
    }

    /**
     * Get settings instance
     */
    public function settings() {
        return $this->settings;
    }
}

/**
 * Main instance
 */
function getcited() {
    return GetCited::instance();
}

// Initialize
getcited();

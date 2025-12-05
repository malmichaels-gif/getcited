<?php
/**
 * Plugin Name: GetCited — AI Visibility
 * Plugin URI: https://heytc.com/getcited
 * Description: Get your content cited by ChatGPT, Claude, and Perplexity. Manage AI crawlers, generate llms.txt, and optimize schema for AI search engines.
 * Version: 1.6.3
 * Requires at least: 6.0
 * Requires PHP: 8.0
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
define( 'GETCITED_VERSION', '1.6.3' );
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

        // Initialize components after plugins loaded
        add_action( 'plugins_loaded', array( $this, 'init_components' ) );

        // Register rewrite rules
        add_action( 'init', array( $this, 'register_rewrites' ) );

        // Admin menu
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

        // Admin assets
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Schedule cron
        add_action( 'getcited_daily_cron', array( $this, 'run_daily_tasks' ) );
        add_action( 'getcited_weekly_schema_scan', array( $this, 'run_schema_scan' ) );

        // REST API
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

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

        // Schedule cron jobs
        if ( ! wp_next_scheduled( 'getcited_daily_cron' ) ) {
            wp_schedule_event( time(), 'daily', 'getcited_daily_cron' );
        }

        // Weekly schema detection scan
        if ( ! wp_next_scheduled( 'getcited_weekly_schema_scan' ) ) {
            wp_schedule_event( time(), 'weekly', 'getcited_weekly_schema_scan' );
        }

        // Run initial schema detection
        GetCited_Schema_Detector::instance()->run_detection();

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
     * Plugin deactivation
     */
    public function deactivate() {
        // Remove cron jobs
        $timestamp = wp_next_scheduled( 'getcited_daily_cron' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'getcited_daily_cron' );
        }

        $timestamp = wp_next_scheduled( 'getcited_weekly_schema_scan' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'getcited_weekly_schema_scan' );
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
            __( 'AI Crawlers', 'getcited' ),
            __( 'AI Crawlers', 'getcited' ),
            $capability,
            'getcited-crawlers',
            array( GetCited_Dashboard::instance(), 'render_crawlers_page' )
        );

        add_submenu_page(
            'getcited',
            __( 'llms.txt', 'getcited' ),
            __( 'llms.txt', 'getcited' ),
            $capability,
            'getcited-llms-txt',
            array( GetCited_Dashboard::instance(), 'render_llms_txt_page' )
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
            __( 'Citability', 'getcited' ),
            __( 'Citability', 'getcited' ),
            $capability,
            'getcited-citability',
            array( GetCited_Dashboard::instance(), 'render_citability_page' )
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
     * Run daily scheduled tasks
     */
    public function run_daily_tasks() {
        // Update crawler list from remote
        GetCited_Crawler_List::instance()->fetch_remote_list();

        // Run health checks
        GetCited_Health_Check::instance()->run_checks();

        // Clean up old request log entries
        if ( class_exists( 'GetCited_Request_Logger' ) ) {
            GetCited_Request_Logger::instance()->cleanup_old_requests();
        }

        // Fire hook for extensions
        do_action( 'getcited_daily_tasks' );
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
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Public status endpoint
        register_rest_route( 'getcited/v1', '/status', array(
            'methods' => 'GET',
            'callback' => array( $this, 'api_status' ),
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
     * API: Status endpoint
     */
    public function api_status() {
        $settings = GetCited_Settings::instance()->get_all();
        $crawlers = GetCited_Crawler_List::instance()->get_all();
        $health = GetCited_Health_Check::instance()->get_status();

        $allowed = 0;
        $blocked = 0;
        foreach ( $settings['crawlers'] as $status ) {
            if ( $status === 'allow' ) {
                $allowed++;
            } else {
                $blocked++;
            }
        }

        return rest_ensure_response( array(
            'version' => GETCITED_VERSION,
            'crawlers' => array(
                'allowed' => $allowed,
                'blocked' => $blocked,
                'total' => count( $crawlers ),
            ),
            'llms_txt' => array(
                'enabled' => $settings['llms_txt_enabled'],
                'status' => $health['llms_txt'] ?? 'unknown',
            ),
            'schema' => array(
                'enabled' => $settings['schema_enabled'],
                'types' => $settings['schema_types'],
            ),
            'license_status' => $settings['license_status'],
        ) );
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

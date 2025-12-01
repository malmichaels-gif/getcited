<?php
/**
 * Admin Dashboard for GetCited
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dashboard class
 */
class GetCited_Dashboard {

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
        // Save settings handler
        add_action( 'wp_ajax_getcited_save_settings', array( $this, 'ajax_save_settings' ) );
    }

    /**
     * Render main dashboard page
     */
    public function render_page() {
        // Check for wizard mode
        $wizard = GetCited_Wizard::instance();
        if ( $wizard->is_active() ) {
            $wizard->render();
            return;
        }

        include GETCITED_PLUGIN_DIR . 'templates/dashboard.php';
    }

    /**
     * Render crawlers page
     */
    public function render_crawlers_page() {
        include GETCITED_PLUGIN_DIR . 'templates/crawlers.php';
    }

    /**
     * Render llms.txt page
     */
    public function render_llms_txt_page() {
        include GETCITED_PLUGIN_DIR . 'templates/llms-txt.php';
    }

    /**
     * Render schema page
     */
    public function render_schema_page() {
        include GETCITED_PLUGIN_DIR . 'templates/schema.php';
    }

    /**
     * Render citability page
     */
    public function render_citability_page() {
        include GETCITED_PLUGIN_DIR . 'templates/citability.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        include GETCITED_PLUGIN_DIR . 'templates/settings.php';
    }

    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $section = isset( $_POST['section'] ) ? sanitize_text_field( wp_unslash( $_POST['section'] ) ) : '';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is sanitized per-field below
        $data = isset( $_POST['data'] ) ? map_deep( wp_unslash( $_POST['data'] ), 'sanitize_text_field' ) : array();

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
                    $settings->set( 'llms_txt_enabled', (bool) $data['llms_txt_enabled'] );
                }
                if ( isset( $data['llms_txt_content'] ) ) {
                    $settings->set( 'llms_txt_content', $data['llms_txt_content'] );
                }
                break;

            case 'schema':
                if ( isset( $data['schema_enabled'] ) ) {
                    $settings->set( 'schema_enabled', (bool) $data['schema_enabled'] );
                }
                if ( isset( $data['schema_types'] ) && is_array( $data['schema_types'] ) ) {
                    $settings->set( 'schema_types', $data['schema_types'] );
                }
                if ( isset( $data['organization'] ) && is_array( $data['organization'] ) ) {
                    $settings->set( 'organization', $data['organization'] );
                }
                break;

            case 'advanced':
                if ( isset( $data['debug_mode'] ) ) {
                    $settings->set( 'debug_mode', (bool) $data['debug_mode'] );
                }
                if ( isset( $data['keep_on_delete'] ) ) {
                    $settings->set( 'keep_on_delete', (bool) $data['keep_on_delete'] );
                }
                break;
        }

        wp_send_json_success( array(
            'message' => __( 'Settings saved', 'getcited' ),
        ) );
    }

    /**
     * Get dashboard stats
     */
    public function get_stats() {
        $settings = GetCited_Settings::instance();
        $crawler_states = $settings->get( 'crawlers' );
        $health = GetCited_Health_Check::instance()->get_status();

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
            'crawlers' => array(
                'allowed' => $allowed,
                'blocked' => $blocked,
                'total' => count( $crawler_states ),
            ),
            'llms_txt' => array(
                'enabled' => $settings->get( 'llms_txt_enabled' ),
                'status' => $health['llms_txt']['status'] ?? 'unknown',
            ),
            'schema' => array(
                'enabled' => $settings->get( 'schema_enabled' ),
                'types' => array_filter( $settings->get( 'schema_types' ) ),
            ),
            'health' => $health,
            'citability' => array(
                'average' => GetCited_Citability::instance()->get_average_score(),
            ),
        );
    }

    /**
     * Get system info for support
     */
    public function get_system_info() {
        global $wpdb;

        $settings = GetCited_Settings::instance();
        $crawler_list = GetCited_Crawler_List::instance();

        // Get active plugins
        $active_plugins = get_option( 'active_plugins' );
        $plugin_names = array();
        foreach ( $active_plugins as $plugin ) {
            $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
            $plugin_names[] = $plugin_data['Name'] . ' ' . $plugin_data['Version'];
        }

        $info = array(
            'GetCited Version' => GETCITED_VERSION,
            'WordPress Version' => get_bloginfo( 'version' ),
            'PHP Version' => phpversion(),
            'MySQL Version' => $wpdb->db_version(),
            'Site URL' => home_url(),
            'Active Theme' => wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ),
            'Multisite' => is_multisite() ? 'Yes' : 'No',
            'Server' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown',
            'Crawler List Version' => $crawler_list->get_version(),
            'Crawler List Updated' => $crawler_list->get_last_updated(),
            'Using Remote List' => $crawler_list->is_remote_cached() ? 'Yes' : 'No (bundled)',
            'llms.txt Enabled' => $settings->get( 'llms_txt_enabled' ) ? 'Yes' : 'No',
            'Schema Enabled' => $settings->get( 'schema_enabled' ) ? 'Yes' : 'No',
            'License Status' => ucfirst( $settings->get( 'license_status' ) ),
            'Debug Mode' => $settings->get( 'debug_mode' ) ? 'Yes' : 'No',
            'Active Plugins' => implode( ', ', $plugin_names ),
        );

        return $info;
    }

    /**
     * Render system info as text
     */
    public function render_system_info() {
        $info = $this->get_system_info();
        $output = "=== GetCited System Info ===\n\n";

        foreach ( $info as $key => $value ) {
            $output .= "{$key}: {$value}\n";
        }

        $output .= "\n=== End System Info ===";

        return $output;
    }
}

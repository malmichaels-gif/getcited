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

        // Template loading handler
        add_action( 'wp_ajax_getcited_load_template', array( $this, 'ajax_load_template' ) );

        // Robots.txt rule management handlers
        add_action( 'wp_ajax_getcited_add_robots_rules', array( $this, 'ajax_add_robots_rules' ) );
        add_action( 'wp_ajax_getcited_remove_robots_rules', array( $this, 'ajax_remove_robots_rules' ) );

        // llms.txt file management handlers
        add_action( 'wp_ajax_getcited_write_llms_file', array( $this, 'ajax_write_llms_file' ) );
        add_action( 'wp_ajax_getcited_delete_llms_file', array( $this, 'ajax_delete_llms_file' ) );

        // Schema detection handlers
        add_action( 'wp_ajax_getcited_rescan_schema', array( $this, 'ajax_rescan_schema' ) );
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

        // Handle JSON string from FormData (JS sends objects as JSON strings)
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is sanitized per-field below
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
                if ( isset( $data['robots_write_physical'] ) ) {
                    $settings->set( 'robots_write_physical', filter_var( $data['robots_write_physical'], FILTER_VALIDATE_BOOLEAN ) ?? false );
                }
                break;

            case 'llms_txt':
                if ( isset( $data['llms_txt_enabled'] ) ) {
                    $settings->set( 'llms_txt_enabled', filter_var( $data['llms_txt_enabled'], FILTER_VALIDATE_BOOLEAN ) ?? false );
                }
                if ( isset( $data['llms_txt_content'] ) ) {
                    $settings->set( 'llms_txt_content', $data['llms_txt_content'] );
                }
                if ( isset( $data['llms_write_physical'] ) ) {
                    $settings->set( 'llms_write_physical', filter_var( $data['llms_write_physical'], FILTER_VALIDATE_BOOLEAN ) ?? false );
                }
                if ( isset( $data['llms_founder_name'] ) ) {
                    $settings->set( 'llms_founder_name', $data['llms_founder_name'] );
                }
                if ( isset( $data['llms_founder_title'] ) ) {
                    $settings->set( 'llms_founder_title', $data['llms_founder_title'] );
                }
                if ( isset( $data['llms_site_expertise'] ) ) {
                    $settings->set( 'llms_site_expertise', $data['llms_site_expertise'] );
                }
                if ( isset( $data['llms_update_frequency'] ) ) {
                    $settings->set( 'llms_update_frequency', $data['llms_update_frequency'] );
                }
                if ( isset( $data['llms_citation_format'] ) ) {
                    $settings->set( 'llms_citation_format', $data['llms_citation_format'] );
                }
                break;

            case 'schema':
                if ( isset( $data['schema_enabled'] ) ) {
                    $settings->set( 'schema_enabled', filter_var( $data['schema_enabled'], FILTER_VALIDATE_BOOLEAN ) ?? false );
                }
                if ( isset( $data['schema_force_enabled'] ) ) {
                    $settings->set( 'schema_force_enabled', filter_var( $data['schema_force_enabled'], FILTER_VALIDATE_BOOLEAN ) ?? false );
                }
                if ( isset( $data['schema_types'] ) && is_array( $data['schema_types'] ) ) {
                    // Convert string "true"/"false" to actual booleans
                    $schema_types = array();
                    foreach ( $data['schema_types'] as $key => $value ) {
                        $schema_types[ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ?? false;
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
                    $settings->set( 'debug_mode', filter_var( $data['debug_mode'], FILTER_VALIDATE_BOOLEAN ) ?? false );
                }
                if ( isset( $data['keep_on_delete'] ) ) {
                    $settings->set( 'keep_on_delete', filter_var( $data['keep_on_delete'], FILTER_VALIDATE_BOOLEAN ) ?? false );
                }
                break;
        }

        wp_send_json_success( array(
            'message' => __( 'Settings saved', 'getcited' ),
        ) );
    }

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
        $valid_types = array( 'blog', 'business', 'news', 'ecommerce', 'portfolio', 'nonprofit', 'education', 'community', 'other' );
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

    /**
     * AJAX: Add rules to physical robots.txt
     */
    public function ajax_add_robots_rules() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $robots = GetCited_Robots::instance();
        $result = $robots->add_rules_to_physical_file();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Remove rules from physical robots.txt
     */
    public function ajax_remove_robots_rules() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $robots = GetCited_Robots::instance();
        $result = $robots->remove_rules_from_physical_file();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Write llms.txt physical file
     */
    public function ajax_write_llms_file() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $llms = GetCited_Llms_Txt::instance();
        $result = $llms->write_physical_file();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Delete llms.txt physical file
     */
    public function ajax_delete_llms_file() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $llms = GetCited_Llms_Txt::instance();
        $result = $llms->delete_physical_file();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
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
        $active_plugins = get_option( 'active_plugins', array() );
        $plugin_names = array();
        foreach ( $active_plugins as $plugin ) {
            $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
            if ( ! empty( $plugin_data['Name'] ) ) {
                $plugin_names[] = $plugin_data['Name'] . ' ' . ( $plugin_data['Version'] ?? 'unknown' );
            }
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

    /**
     * AJAX: Re-scan for schema sources
     */
    public function ajax_rescan_schema() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $detector = GetCited_Schema_Detector::instance();
        $result   = $detector->refresh_detection();
        $status   = $detector->get_status_message();

        wp_send_json_success( array(
            'detection'    => $result,
            'status'       => $status,
            'last_scan'    => $detector->get_last_scan_ago(),
            'message'      => __( 'Scan complete', 'getcited' ),
        ) );
    }
}

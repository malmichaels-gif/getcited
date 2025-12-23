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
        add_action( 'wp_ajax_getcited_delete_llms_file', array( $this, 'ajax_delete_llms_file' ) );

        // Schema detection handlers
        add_action( 'wp_ajax_getcited_rescan_schema', array( $this, 'ajax_rescan_schema' ) );

        // Visibility score handlers
        add_action( 'wp_ajax_getcited_refresh_visibility_score', array( $this, 'ajax_refresh_visibility_score' ) );

        // Request log handlers
        add_action( 'wp_ajax_getcited_clear_request_log', array( $this, 'ajax_clear_request_log' ) );

        // llms.txt verification and download handlers
        add_action( 'wp_ajax_getcited_verify_llms_accessible', array( $this, 'ajax_verify_llms_accessible' ) );
        add_action( 'wp_ajax_getcited_download_llms', array( $this, 'ajax_download_llms' ) );

        // Citation nudge dismiss handler (v1.5.1)
        add_action( 'wp_ajax_getcited_dismiss_citation_nudge', array( $this, 'ajax_dismiss_citation_nudge' ) );

        // llms.txt regeneration handler (v1.6.12)
        add_action( 'wp_ajax_getcited_regenerate_llms', array( $this, 'ajax_regenerate_llms' ) );

        // Dashboard tips handler (v1.6.14)
        add_action( 'wp_ajax_getcited_next_tip', array( $this, 'ajax_next_tip' ) );

        // Admin notices for citation nudge (v1.5.1)
        add_action( 'admin_notices', array( $this, 'maybe_show_citation_nudge' ) );

        // Admin notices for existing llms.txt detection (v2.0)
        add_action( 'admin_notices', array( $this, 'maybe_show_existing_llms_notice' ) );
        add_action( 'admin_notices', array( $this, 'maybe_show_llms_imported_notice' ) );

        // Existing llms.txt choice handlers (v2.0)
        add_action( 'wp_ajax_getcited_import_existing_llms', array( $this, 'ajax_import_existing_llms' ) );
        add_action( 'wp_ajax_getcited_keep_existing_llms', array( $this, 'ajax_keep_existing_llms' ) );
        add_action( 'wp_ajax_getcited_dismiss_llms_choice', array( $this, 'ajax_dismiss_llms_choice' ) );
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
                // Handle llms_txt_source change with backup/restore (v1.9.9.18).
                if ( isset( $data['llms_txt_source'] ) ) {
                    $new_source = $data['llms_txt_source'];
                    $old_source = $settings->get( 'llms_txt_source' );
                    $llms       = GetCited_Llms_Txt::instance();

                    if ( $new_source !== $old_source ) {
                        if ( 'getcited' === $new_source ) {
                            // Switching to GetCited - backup any existing physical file.
                            $llms->backup_physical_file();
                        } elseif ( 'existing' === $new_source && $llms->backup_exists() ) {
                            // Switching back to existing - restore from backup.
                            $llms->restore_physical_file();
                        }
                    }

                    $settings->set( 'llms_txt_source', $new_source );
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
                // Save Use Cases for AI (v1.9.9.12+).
                // Use $raw_data with sanitize_textarea_field to preserve line breaks.
                if ( isset( $raw_data['llms_use_cases'] ) ) {
                    $settings->set( 'llms_use_cases', sanitize_textarea_field( $raw_data['llms_use_cases'] ) );
                }
                // Save citation guidelines (v1.5.1+).
                if ( isset( $data['citation_guidelines'] ) && is_array( $data['citation_guidelines'] ) ) {
                    $citation_guidelines = array(
                        'enabled'         => filter_var( $data['citation_guidelines']['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN ),
                        'citation_format' => $data['citation_guidelines']['citation_format'] ?? '',
                        'accuracy_notes'  => $data['citation_guidelines']['accuracy_notes'] ?? '',
                        'restrictions'    => $data['citation_guidelines']['restrictions'] ?? '',
                        'freshness_note'  => $data['citation_guidelines']['freshness_note'] ?? '',
                        'contact_email'   => sanitize_email( $data['citation_guidelines']['contact_email'] ?? '' ),
                    );
                    $settings->set( 'citation_guidelines', $citation_guidelines );
                }

                // Auto-regenerate llms.txt content to incorporate all settings (v1.5.3).
                $llms      = GetCited_Llms_Txt::instance();
                $site_type = $settings->get( 'site_type' );
                $regenerated_content = $llms->generate_template( $site_type );
                $settings->set( 'llms_txt_content', $regenerated_content );

                // Return early with regenerated content so UI can update (v1.9.9.14).
                wp_send_json_success( array(
                    'message'          => __( 'Settings saved', 'getcited' ),
                    'llms_txt_content' => $regenerated_content,
                ) );
                return; // Explicit return to prevent fall-through to generic response.

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

                    // Auto-regenerate llms.txt to reflect organization changes.
                    $llms      = GetCited_Llms_Txt::instance();
                    $site_type = $settings->get( 'site_type' );
                    $regenerated_content = $llms->generate_template( $site_type );
                    $settings->set( 'llms_txt_content', $regenerated_content );
                }
                break;

            case 'advanced':
                if ( isset( $data['site_type'] ) ) {
                    $old_site_type = $settings->get( 'site_type' );
                    $settings->set( 'site_type', $data['site_type'] );

                    // Auto-regenerate llms.txt if site type changed.
                    if ( $old_site_type !== $data['site_type'] ) {
                        $llms = GetCited_Llms_Txt::instance();
                        $regenerated_content = $llms->generate_template( $data['site_type'] );
                        $settings->set( 'llms_txt_content', $regenerated_content );
                    }
                }
                if ( isset( $data['debug_mode'] ) ) {
                    $settings->set( 'debug_mode', filter_var( $data['debug_mode'], FILTER_VALIDATE_BOOLEAN ) ?? false );
                }
                if ( isset( $data['keep_on_delete'] ) ) {
                    $settings->set( 'keep_on_delete', filter_var( $data['keep_on_delete'], FILTER_VALIDATE_BOOLEAN ) ?? false );
                }
                if ( isset( $data['request_logging_enabled'] ) ) {
                    $settings->set( 'request_logging_enabled', filter_var( $data['request_logging_enabled'], FILTER_VALIDATE_BOOLEAN ) ?? false );
                }
                if ( isset( $data['request_log_retention'] ) ) {
                    $retention = absint( $data['request_log_retention'] );
                    if ( in_array( $retention, array( 30, 60, 90, 180 ), true ) ) {
                        $settings->set( 'request_log_retention', $retention );
                    }
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
     * AJAX: Regenerate llms.txt content from Settings page
     *
     * @since 1.6.12
     */
    public function ajax_regenerate_llms() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $settings  = GetCited_Settings::instance();
        $llms      = GetCited_Llms_Txt::instance();
        $site_type = $settings->get( 'site_type' );

        // Regenerate content using current site type.
        $content = $llms->generate_template( $site_type );
        $settings->set( 'llms_txt_content', $content );

        wp_send_json_success( array( 'message' => __( 'llms.txt regenerated!', 'getcited' ) ) );
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
            'visibility_score' => GetCited_Visibility_Score::instance()->get_score(),
            'llms_activity' => $this->get_llms_activity(),
        );
    }

    /**
     * Get llms.txt activity data for dashboard
     *
     * @return array Activity data with recent requests and stats.
     */
    public function get_llms_activity() {
        $logger = GetCited_Request_Logger::instance();

        return array(
            'recent'  => $logger->get_recent_requests( 30, 10 ),
            'stats'   => $logger->get_request_stats( 30 ),
            'enabled' => GetCited_Settings::instance()->get( 'request_logging_enabled' ),
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

    /**
     * AJAX: Refresh visibility score
     */
    public function ajax_refresh_visibility_score() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $score_class = GetCited_Visibility_Score::instance();
        $score       = $score_class->get_score( true ); // Force recalculation.

        wp_send_json_success( array(
            'score'   => $score,
            'message' => __( 'Score refreshed', 'getcited' ),
        ) );
    }

    /**
     * AJAX: Clear request log
     */
    public function ajax_clear_request_log() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $logger = GetCited_Request_Logger::instance();
        $result = $logger->clear_all_requests();

        if ( $result ) {
            wp_send_json_success( array(
                'message' => __( 'Request log cleared', 'getcited' ),
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to clear request log', 'getcited' ),
            ) );
        }
    }

    /**
     * AJAX: Verify llms.txt is accessible
     */
    public function ajax_verify_llms_accessible() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $health_check = GetCited_Health_Check::instance();
        $result       = $health_check->verify_llms_txt_with_fallback();

        // Add hosting environment info
        $host     = $health_check->detect_hosting_environment();
        $guidance = $health_check->get_host_specific_guidance( $host );

        $result['host']          = $host;
        $result['host_guidance'] = $guidance;

        // Check if we can write a physical file
        $result['can_write_physical'] = GetCited_Llms_Txt::instance()->can_write_physical_file();

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Download llms.txt file
     */
    public function ajax_download_llms() {
        // Verify nonce from query string for download links
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked below with check_admin_referer
        $nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'getcited_admin' ) ) {
            wp_die( esc_html__( 'Security check failed', 'getcited' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied', 'getcited' ) );
        }

        $content = GetCited_Llms_Txt::instance()->get_content();

        // Set headers for file download
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="llms.txt"' );
        header( 'Content-Length: ' . strlen( $content ) );
        header( 'Cache-Control: no-cache, must-revalidate' );
        header( 'Pragma: no-cache' );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text file download
        echo $content;
        exit;
    }

    /**
     * Maybe show citation guidelines nudge after wizard completion
     *
     * @since 1.5.1
     * @since 1.5.3 Updated to show success message since guidelines are now enabled by default.
     */
    public function maybe_show_citation_nudge() {
        // Check if nudge transient exists.
        if ( ! get_transient( 'getcited_show_citation_nudge' ) ) {
            return;
        }

        // Don't show in wizard mode.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check.
        if ( isset( $_GET['wizard'] ) ) {
            return;
        }

        // Only show on GetCited admin pages.
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'getcited' ) === false ) {
            return;
        }

        ?>
        <div class="notice notice-success is-dismissible getcited-citation-nudge" data-nonce="<?php echo esc_attr( wp_create_nonce( 'getcited_admin' ) ); ?>">
            <p>
                <strong><?php esc_html_e( 'Setup complete!', 'getcited' ); ?></strong>
                <?php esc_html_e( 'Your AI Citation Guidelines are now active. ChatGPT, Gemini, Grok, Perplexity, and other AI systems will see how to properly cite your content.', 'getcited' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=getcited-llms-txt' ) ); ?>">
                    <?php esc_html_e( 'Customize →', 'getcited' ); ?>
                </a>
            </p>
        </div>
        <script>
        (function() {
            document.addEventListener('DOMContentLoaded', function() {
                var notice = document.querySelector('.getcited-citation-nudge');
                if (!notice) return;

                notice.addEventListener('click', function(e) {
                    if (e.target.classList.contains('notice-dismiss')) {
                        fetch(ajaxurl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=getcited_dismiss_citation_nudge&nonce=' + notice.dataset.nonce
                        });
                    }
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * AJAX: Dismiss citation guidelines nudge
     *
     * @since 1.5.1
     */
    public function ajax_dismiss_citation_nudge() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        delete_transient( 'getcited_show_citation_nudge' );
        wp_send_json_success();
    }

    /**
     * Get AI visibility tips (includes dynamic tips based on user's setup)
     *
     * @since 1.6.14
     * @return array Array of tips with title and content.
     */
    public static function get_tips() {
        $schema_url = admin_url( 'admin.php?page=getcited-schema' );
        $llms_url   = admin_url( 'admin.php?page=getcited-llms-txt' );
        $settings   = GetCited_Settings::instance();

        // Static tips (always shown).
        $tips = array(
            array(
                'title'   => __( 'Write Quotable Content', 'getcited' ),
                'content' => __( 'Write content that\'s easy for AI to quote. If your paragraph could be read aloud by ChatGPT as a standalone answer, you\'re doing it right.', 'getcited' ),
            ),
            array(
                'title'   => __( 'Match Common Questions', 'getcited' ),
                'content' => __( 'Create pages that match questions people ask AI. Instead of generic posts, write directly at questions like "How do I..." or "What\'s the best way to..."', 'getcited' ),
            ),
            array(
                'title'   => __( 'Use Answer-Sized Blocks', 'getcited' ),
                'content' => __( 'Short, punchy explanations (150-250 words) get referenced more than long essays. Think "What X really means" or "The simple version of X."', 'getcited' ),
            ),
            array(
                'title'   => __( 'Publish Original Data', 'getcited' ),
                'content' => __( 'AIs love numbers because they anchor answers. Even small studies work: "We analyzed 23 landing pages..." or "We tested 10 subject lines..."', 'getcited' ),
            ),
            array(
                'title'   => __( 'AI Crawlers Visit on Their Schedule', 'getcited' ),
                'content' => __( 'You can\'t request a scan from ChatGPT or other AI systems. They crawl sites on their own schedule (typically within 1-7 days). GetCited ensures you\'re ready when they arrive.', 'getcited' ),
            ),
        );

        // Dynamic tips based on user's setup.
        $org = $settings->get( 'organization' );
        $citation_guidelines = $settings->get( 'citation_guidelines' );
        $llms_content = $settings->get( 'llms_txt_content' );

        // Tip: Organization name configured.
        if ( ! empty( $org['name'] ) ) {
            $tips[] = array(
                'title'   => __( 'Brand Identity Set', 'getcited' ),
                /* translators: %s: Organization name */
                'content' => sprintf( __( 'Great! "%s" is configured as your brand. AI systems will use this when citing your content.', 'getcited' ), esc_html( $org['name'] ) ),
            );
        } else {
            $tips[] = array(
                'title'   => __( 'Add Your Brand Name', 'getcited' ),
                /* translators: %s: URL to Schema settings page */
                'content' => sprintf( __( 'AI citations work better with a clear brand identity. <a href="%s">Add your organization name</a> so AI knows how to reference you.', 'getcited' ), esc_url( $schema_url ) ),
            );
        }

        // Tip: Social profiles.
        $social_count = 0;
        if ( ! empty( $org['social_urls'] ) && is_array( $org['social_urls'] ) ) {
            $social_count = count( array_filter( $org['social_urls'] ) );
        }
        if ( $social_count > 0 ) {
            $tips[] = array(
                'title'   => __( 'Social Proof Active', 'getcited' ),
                /* translators: %d: Number of social profiles */
                'content' => sprintf( _n( 'You have %d social profile linked. AI systems use these to verify your identity and authority.', 'You have %d social profiles linked. AI systems use these to verify your identity and authority.', $social_count, 'getcited' ), $social_count ),
            );
        }

        // Tip: Citation guidelines.
        if ( ! empty( $citation_guidelines['enabled'] ) ) {
            $tips[] = array(
                'title'   => __( 'Citation Guidelines Active', 'getcited' ),
                'content' => __( 'Your citation guidelines tell AI exactly how to reference your content. This improves accuracy and attribution.', 'getcited' ),
            );
        } else {
            $tips[] = array(
                'title'   => __( 'Add Citation Guidelines', 'getcited' ),
                /* translators: %s: URL to llms.txt settings page */
                'content' => sprintf( __( 'Tell AI how to cite you properly. <a href="%s">Add citation guidelines</a> like preferred format and accuracy requirements.', 'getcited' ), esc_url( $llms_url ) ),
            );
        }

        // Tip: llms.txt content analysis.
        if ( ! empty( $llms_content ) ) {
            // Count sections/topics in llms.txt.
            $section_count = substr_count( $llms_content, '##' );
            if ( $section_count > 3 ) {
                $tips[] = array(
                    'title'   => __( 'Well-Structured llms.txt', 'getcited' ),
                    /* translators: %d: Number of sections */
                    'content' => sprintf( __( 'Your llms.txt has %d sections — a thorough structure helps AI understand your site\'s expertise areas.', 'getcited' ), $section_count ),
                );
            }
        } else {
            $tips[] = array(
                'title'   => __( 'Create Your llms.txt', 'getcited' ),
                /* translators: %s: URL to llms.txt settings page */
                'content' => sprintf( __( 'You haven\'t set up llms.txt yet. <a href="%s">Scan your site</a> to generate one — it\'s how AI crawlers learn about your content.', 'getcited' ), esc_url( $llms_url ) ),
            );
        }

        return $tips;
    }

    /**
     * Get a random tip index (avoiding recently shown tips)
     *
     * @since 1.6.14
     * @return int Random tip index.
     */
    public static function get_random_tip_index() {
        $tips      = self::get_tips();
        $tip_count = count( $tips );
        $user_id   = get_current_user_id();

        // Get recently shown tips (avoid repeats).
        $recent = get_user_meta( $user_id, 'getcited_recent_tips', true );
        if ( ! is_array( $recent ) ) {
            $recent = array();
        }

        // Keep recent list to half of total tips.
        $max_recent = max( 1, (int) floor( $tip_count / 2 ) );

        // Find available indices (not recently shown).
        $available = array_diff( range( 0, $tip_count - 1 ), $recent );

        // If all tips shown recently, reset.
        if ( empty( $available ) ) {
            $available = range( 0, $tip_count - 1 );
            $recent    = array();
        }

        // Pick random from available.
        $index = $available[ array_rand( $available ) ];

        // Update recent list.
        $recent[] = $index;
        if ( count( $recent ) > $max_recent ) {
            array_shift( $recent );
        }
        update_user_meta( $user_id, 'getcited_recent_tips', $recent );

        return $index;
    }

    /**
     * Get current tip for display (random selection)
     *
     * @since 1.6.14
     * @return array Current tip with title and content.
     */
    public static function get_current_tip() {
        $tips  = self::get_tips();
        $index = self::get_random_tip_index();

        return $tips[ $index ];
    }

    /**
     * AJAX: Get next random tip
     *
     * @since 1.6.14
     */
    public function ajax_next_tip() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $tips  = self::get_tips();
        $index = self::get_random_tip_index();

        wp_send_json_success( array(
            'tip'   => $tips[ $index ],
            'index' => $index,
            'total' => count( $tips ),
        ) );
    }

    /**
     * Show notice when existing llms.txt was detected (moderate/substantial files)
     *
     * @since 2.0.0
     */
    public function maybe_show_existing_llms_notice() {
        $settings = GetCited_Settings::instance();

        // Check if detection flag is set
        if ( ! $settings->get( 'existing_llms_txt_detected' ) ) {
            return;
        }

        // Only show on GetCited admin pages
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'getcited' ) === false ) {
            return;
        }

        $assessment = $settings->get( 'existing_llms_txt_assessment' );
        $nonce = wp_create_nonce( 'getcited_admin' );

        // Determine messaging based on assessment
        if ( 'substantial' === $assessment ) {
            $message = __( 'We found a detailed llms.txt file on your site.', 'getcited' );
            $primary_action = 'keep';
        } else {
            $message = __( 'We found an existing llms.txt file on your site.', 'getcited' );
            $primary_action = 'import';
        }

        ?>
        <div class="notice notice-info getcited-existing-llms-notice" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <p><strong><?php echo esc_html( $message ); ?></strong></p>
            <p>
                <?php if ( 'substantial' === $assessment ) : ?>
                    <button type="button" class="button button-primary getcited-llms-keep">
                        <?php esc_html_e( 'Keep existing file', 'getcited' ); ?>
                    </button>
                    <button type="button" class="button getcited-llms-import">
                        <?php esc_html_e( 'Import into GetCited', 'getcited' ); ?>
                    </button>
                <?php else : ?>
                    <button type="button" class="button button-primary getcited-llms-import">
                        <?php esc_html_e( 'Import into GetCited', 'getcited' ); ?>
                    </button>
                    <button type="button" class="button getcited-llms-keep">
                        <?php esc_html_e( 'Keep existing file', 'getcited' ); ?>
                    </button>
                <?php endif; ?>
                <button type="button" class="button getcited-llms-dismiss">
                    <?php esc_html_e( 'Decide later', 'getcited' ); ?>
                </button>
            </p>
        </div>
        <script>
        (function() {
            document.addEventListener('DOMContentLoaded', function() {
                var notice = document.querySelector('.getcited-existing-llms-notice');
                if (!notice) return;

                var nonce = notice.dataset.nonce;

                notice.querySelector('.getcited-llms-import').addEventListener('click', function() {
                    this.disabled = true;
                    this.textContent = '<?php echo esc_js( __( 'Importing...', 'getcited' ) ); ?>';
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=getcited_import_existing_llms&nonce=' + nonce
                    }).then(function(response) {
                        return response.json();
                    }).then(function(data) {
                        if (data.success) {
                            notice.innerHTML = '<p><strong><?php echo esc_js( __( 'llms.txt imported into GetCited successfully.', 'getcited' ) ); ?></strong></p>';
                            notice.classList.remove('notice-info');
                            notice.classList.add('notice-success');
                            setTimeout(function() { notice.remove(); }, 3000);
                        }
                    });
                });

                notice.querySelector('.getcited-llms-keep').addEventListener('click', function() {
                    this.disabled = true;
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=getcited_keep_existing_llms&nonce=' + nonce
                    }).then(function(response) {
                        return response.json();
                    }).then(function(data) {
                        if (data.success) {
                            notice.innerHTML = '<p><strong><?php echo esc_js( __( 'Your existing llms.txt file will be used.', 'getcited' ) ); ?></strong></p>';
                            notice.classList.remove('notice-info');
                            notice.classList.add('notice-success');
                            setTimeout(function() { notice.remove(); }, 3000);
                        }
                    });
                });

                notice.querySelector('.getcited-llms-dismiss').addEventListener('click', function() {
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=getcited_dismiss_llms_choice&nonce=' + nonce
                    }).then(function() {
                        notice.remove();
                    });
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * Show notice when basic llms.txt was auto-imported
     *
     * @since 2.0.0
     */
    public function maybe_show_llms_imported_notice() {
        // Check for transient
        if ( ! get_transient( 'getcited_llms_imported_notice' ) ) {
            return;
        }

        // Only show on GetCited admin pages
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'getcited' ) === false ) {
            return;
        }

        // Delete transient so it only shows once
        delete_transient( 'getcited_llms_imported_notice' );

        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong><?php esc_html_e( 'We imported your existing llms.txt file into GetCited.', 'getcited' ); ?></strong>
                <?php esc_html_e( 'You can now manage and enhance it from the dashboard.', 'getcited' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * AJAX: Import existing llms.txt into GetCited
     *
     * @since 2.0.0
     */
    public function ajax_import_existing_llms() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $llms = GetCited_Llms_Txt::instance();
        $result = $llms->import_existing_file();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Keep existing llms.txt file
     *
     * @since 2.0.0
     */
    public function ajax_keep_existing_llms() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $llms = GetCited_Llms_Txt::instance();
        $result = $llms->keep_existing_file();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Dismiss llms.txt choice (decide later)
     *
     * @since 2.0.0
     */
    public function ajax_dismiss_llms_choice() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $settings = GetCited_Settings::instance();

        // Set source to existing (temporary default)
        $settings->set( 'llms_txt_source', 'existing' );

        // Keep detection flag so it can be revisited in settings
        // Don't clear existing_llms_txt_detected

        wp_send_json_success( array(
            'message' => __( 'You can make this choice anytime from the llms.txt settings.', 'getcited' ),
        ) );
    }
}

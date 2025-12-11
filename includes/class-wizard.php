<?php
/**
 * Setup Wizard for GetCited
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Wizard class
 */
class GetCited_Wizard {

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
        // Check if wizard should be shown
        add_action( 'admin_init', array( $this, 'maybe_redirect_to_wizard' ) );

        // AJAX handlers
        add_action( 'wp_ajax_getcited_wizard_save', array( $this, 'ajax_save_step' ) );
        add_action( 'wp_ajax_getcited_wizard_skip', array( $this, 'ajax_skip_wizard' ) );
        add_action( 'wp_ajax_getcited_wizard_complete', array( $this, 'ajax_complete_wizard' ) );
        add_action( 'wp_ajax_getcited_wizard_scan', array( $this, 'ajax_run_scan' ) );
        add_action( 'wp_ajax_getcited_wizard_verify', array( $this, 'ajax_verify_llms' ) );
        add_action( 'wp_ajax_getcited_wizard_fix_llms', array( $this, 'ajax_fix_llms' ) );
    }

    /**
     * Maybe redirect to wizard on first activation
     */
    public function maybe_redirect_to_wizard() {
        // Only check on admin
        if ( ! is_admin() ) {
            return;
        }

        // Don't redirect on AJAX
        if ( wp_doing_ajax() ) {
            return;
        }

        // Check if we should show wizard
        $settings = GetCited_Settings::instance();
        if ( $settings->get( 'wizard_completed' ) ) {
            return;
        }

        // Check transient for activation redirect
        if ( get_transient( 'getcited_activation_redirect' ) ) {
            delete_transient( 'getcited_activation_redirect' );

            // Don't redirect if already on GetCited page
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a redirect check, not a form submission
            if ( isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'getcited' ) !== false ) {
                return;
            }

            wp_safe_redirect( admin_url( 'admin.php?page=getcited&wizard=1' ) );
            exit;
        }
    }

    /**
     * Check if wizard is active
     */
    public function is_active() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is an admin page check, not a form submission
        return isset( $_GET['wizard'] ) && sanitize_text_field( wp_unslash( $_GET['wizard'] ) ) === '1';
    }

    /**
     * Get wizard steps
     */
    public function get_steps() {
        return array(
            'welcome' => array(
                'title' => __( 'Welcome', 'getcited' ),
                'description' => __( "Let's make your site visible to AI search engines.", 'getcited' ),
            ),
            'site_type' => array(
                'title' => __( 'Site Type', 'getcited' ),
                'description' => __( 'This helps us configure optimal settings for you.', 'getcited' ),
            ),
            'organization' => array(
                'title' => __( 'Your Brand', 'getcited' ),
                'description' => __( 'This information appears in schema markup.', 'getcited' ),
            ),
            'crawlers' => array(
                'title' => __( 'AI Access', 'getcited' ),
                'description' => __( 'Choose which AI systems can access your content.', 'getcited' ),
            ),
            'verify' => array(
                'title' => __( 'Verify', 'getcited' ),
                'description' => __( 'Making sure AI systems can access your llms.txt file.', 'getcited' ),
            ),
            'complete' => array(
                'title' => __( 'Done!', 'getcited' ),
                'description' => __( 'Your site is now optimized for AI visibility.', 'getcited' ),
            ),
        );
    }

    /**
     * Get site type options
     */
    public function get_site_types() {
        return array(
            'blog' => array(
                'label' => __( 'Blog', 'getcited' ),
                'description' => __( 'Personal or professional blog', 'getcited' ),
                'icon' => 'dashicons-edit',
            ),
            'business' => array(
                'label' => __( 'Business', 'getcited' ),
                'description' => __( 'Company or service provider', 'getcited' ),
                'icon' => 'dashicons-building',
            ),
            'news' => array(
                'label' => __( 'News / Magazine', 'getcited' ),
                'description' => __( 'Publication or editorial', 'getcited' ),
                'icon' => 'dashicons-megaphone',
            ),
            'ecommerce' => array(
                'label' => __( 'E-commerce', 'getcited' ),
                'description' => __( 'Online store or products', 'getcited' ),
                'icon' => 'dashicons-cart',
            ),
            'portfolio' => array(
                'label' => __( 'Portfolio', 'getcited' ),
                'description' => __( 'Creative work showcase', 'getcited' ),
                'icon' => 'dashicons-portfolio',
            ),
            'nonprofit' => array(
                'label' => __( 'Nonprofit', 'getcited' ),
                'description' => __( 'Charity or cause', 'getcited' ),
                'icon' => 'dashicons-heart',
            ),
            'education' => array(
                'label' => __( 'Education', 'getcited' ),
                'description' => __( 'Courses or tutorials', 'getcited' ),
                'icon' => 'dashicons-welcome-learn-more',
            ),
            'community' => array(
                'label' => __( 'Community', 'getcited' ),
                'description' => __( 'Forum or membership', 'getcited' ),
                'icon' => 'dashicons-groups',
            ),
            'other' => array(
                'label' => __( 'Other', 'getcited' ),
                'description' => __( 'Something else', 'getcited' ),
                'icon' => 'dashicons-admin-generic',
            ),
        );
    }

    /**
     * Get recommended crawler configuration by site type
     */
    public function get_crawler_recommendations( $site_type ) {
        // By default, allow all major AI crawlers
        $recommendations = array(
            'allow_all' => true,
            'message' => __( 'We recommend allowing all AI crawlers to maximize your visibility.', 'getcited' ),
        );

        // Site-specific adjustments could go here in the future
        switch ( $site_type ) {
            case 'news':
                $recommendations['message'] = __( 'News sites benefit most from allowing all AI crawlers for citation potential.', 'getcited' );
                break;
            case 'ecommerce':
                $recommendations['message'] = __( 'E-commerce sites can get product recommendations in AI search by allowing crawlers.', 'getcited' );
                break;
        }

        return $recommendations;
    }

    /**
     * Apply site type preset
     */
    public function apply_preset( $site_type ) {
        $settings = GetCited_Settings::instance();

        // Set site type
        $settings->set( 'site_type', $site_type );

        // Generate appropriate llms.txt only if not already customized
        $existing_content = $settings->get( 'llms_txt_content' );
        if ( empty( $existing_content ) ) {
            $llms_txt = GetCited_Llms_Txt::instance();
            $content = $llms_txt->generate_template( $site_type );
            $settings->set( 'llms_txt_content', $content );
        }

        // Run SEO plugin detection and auto-configure schema.
        $detector      = GetCited_Schema_Detector::instance();
        $seo_detection = $detector->refresh_detection();

        // If an SEO plugin is detected, disable GetCited schema to avoid duplicates.
        if ( ! empty( $seo_detection['should_disable'] ) ) {
            $settings->set( 'schema_enabled', false );
        } else {
            // No SEO plugin - enable GetCited schema with site-type defaults.
            $settings->set( 'schema_enabled', true );
            $existing_schema = $settings->get( 'schema_types' );
            if ( empty( $existing_schema ) || ! array_filter( $existing_schema ) ) {
                $schema_types = $this->get_schema_defaults_for_site_type( $site_type );
                $settings->set( 'schema_types', $schema_types );
            }
        }

        // Pre-fill citation guidelines with site-type defaults (v1.5.2).
        // Enabled by default since v1.5.3 - users can customize later.
        $llms_txt          = GetCited_LLMS_Txt::instance();
        $citation_defaults = $llms_txt->get_default_citation_guidelines( $site_type );
        $citation_defaults['enabled'] = true; // Enabled by default.
        $settings->set( 'citation_guidelines', $citation_defaults );

        return true;
    }

    /**
     * Get schema defaults for a specific site type
     *
     * @param string $site_type The site type slug.
     * @return array Schema type defaults.
     */
    public function get_schema_defaults_for_site_type( $site_type ) {
        $defaults = array(
            'blog' => array(
                'organization' => true,
                'article' => true,
                'author' => true,
                'faq' => true,
            ),
            'business' => array(
                'organization' => true,
                'article' => false,
                'author' => false,
                'faq' => true,
            ),
            'news' => array(
                'organization' => true,
                'article' => true,
                'author' => true,
                'faq' => false,
            ),
            'ecommerce' => array(
                'organization' => true,
                'article' => false,
                'author' => false,
                'faq' => true,
            ),
            'portfolio' => array(
                'organization' => true,
                'article' => false,
                'author' => true,
                'faq' => false,
            ),
            'nonprofit' => array(
                'organization' => true,
                'article' => true,
                'author' => false,
                'faq' => true,
            ),
            'education' => array(
                'organization' => true,
                'article' => true,
                'author' => true,
                'faq' => true,
            ),
            'community' => array(
                'organization' => true,
                'article' => false,
                'author' => false,
                'faq' => true,
            ),
            'other' => array(
                'organization' => true,
                'article' => true,
                'author' => true,
                'faq' => true,
            ),
        );

        return isset( $defaults[ $site_type ] ) ? $defaults[ $site_type ] : $defaults['other'];
    }

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

        switch ( $step ) {
            case 'site_type':
                $site_type = sanitize_text_field( $data['site_type'] ?? 'blog' );
                $this->apply_preset( $site_type );
                // Note: Site scan is now triggered asynchronously via getcited_wizard_scan
                break;

            case 'organization':
                // Merge with existing settings to preserve social_urls if not provided
                $existing_org = $settings->get( 'organization' );
                $org = array(
                    'name' => sanitize_text_field( $data['name'] ?? $existing_org['name'] ?? '' ),
                    'logo_url' => esc_url_raw( $data['logo_url'] ?? $existing_org['logo_url'] ?? '' ),
                    'social_urls' => ! empty( $data['social_urls'] )
                        ? array_map( 'esc_url_raw', (array) $data['social_urls'] )
                        : ( $existing_org['social_urls'] ?? array() ),
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

    /**
     * AJAX: Run site scan asynchronously
     *
     * Called from JavaScript after site type selection to generate personalized llms.txt content.
     * Results are stored in a transient for display in the completion step.
     */
    public function ajax_run_scan() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        // Rate limiting: prevent rapid successive scans
        $last_scan = get_transient( 'getcited_scan_throttle' );
        if ( $last_scan ) {
            // Return cached results if available
            $cached = get_transient( 'getcited_wizard_scan' );
            if ( $cached ) {
                wp_send_json_success( array(
                    'scan_data' => $cached['scan_data'],
                    'llms_txt'  => $cached['llms_txt'],
                    'cached'    => true,
                ) );
            }
            wp_send_json_error( array( 'message' => 'Please wait before scanning again' ) );
        }

        // Set rate limit transient (60 seconds)
        set_transient( 'getcited_scan_throttle', time(), 60 );

        $scanner        = GetCited_Site_Scanner::instance();
        $scan_data      = $scanner->scan_site();
        $generated_llms = $scanner->generate_llms_txt( $scan_data );

        // Store for step 5 (complete) display - 24 hour TTL
        set_transient(
            'getcited_wizard_scan',
            array(
                'scan_data'  => $scan_data,
                'llms_txt'   => $generated_llms,
                'scanned_at' => current_time( 'mysql' ),
            ),
            DAY_IN_SECONDS
        );

        // Pre-fill organization info from scan if not already set
        $settings = GetCited_Settings::instance();
        $org      = $settings->get( 'organization' );

        $org_updated = false;

        if ( empty( $org['name'] ) && ! empty( $scan_data['site']['name'] ) ) {
            $org['name'] = $scan_data['site']['name'];
            $org_updated = true;
        }

        // Auto-populate social URLs from scan if not already set
        if ( empty( $org['social_urls'] ) && ! empty( $scan_data['social'] ) ) {
            $org['social_urls'] = array_values( array_filter( $scan_data['social'] ) );
            $org_updated        = true;
        }

        if ( $org_updated ) {
            $settings->set( 'organization', $org );
        }

        wp_send_json_success( array(
            'scan_data' => $scan_data,
            'llms_txt'  => $generated_llms,
        ) );
    }

    /**
     * Get wizard scan data if available
     *
     * @return array|false Scan data or false if not available.
     */
    public function get_scan_data() {
        return get_transient( 'getcited_wizard_scan' );
    }

    /**
     * AJAX: Skip wizard
     */
    public function ajax_skip_wizard() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $settings = GetCited_Settings::instance();
        $settings->set( 'wizard_completed', true );

        wp_send_json_success();
    }

    /**
     * AJAX: Complete wizard
     */
    public function ajax_complete_wizard() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $settings = GetCited_Settings::instance();

        // Save the generated llms.txt content from the scan
        $wizard_scan = get_transient( 'getcited_wizard_scan' );
        if ( $wizard_scan && ! empty( $wizard_scan['llms_txt'] ) ) {
            $settings->set( 'llms_txt_content', $wizard_scan['llms_txt'] );

            // Clean up the transient
            delete_transient( 'getcited_wizard_scan' );
        }

        $settings->set( 'wizard_completed', true );

        // Set transient to show citation guidelines nudge (v1.5.1).
        set_transient( 'getcited_show_citation_nudge', true, WEEK_IN_SECONDS );

        // Flush rewrite rules to ensure llms.txt works
        flush_rewrite_rules();

        wp_send_json_success( array(
            'redirect' => admin_url( 'admin.php?page=getcited' ),
        ) );
    }

    /**
     * AJAX: Verify llms.txt accessibility
     *
     * Called when entering the verify step to check if llms.txt is accessible.
     */
    public function ajax_verify_llms() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        // First, flush rewrite rules to ensure they're current
        flush_rewrite_rules();

        // Small delay to let rules take effect
        usleep( 100000 ); // 100ms

        $health_check = GetCited_Health_Check::instance();
        $result       = $health_check->verify_llms_txt_with_fallback();

        // Add hosting environment info
        $host     = $health_check->detect_hosting_environment();
        $guidance = $health_check->get_host_specific_guidance( $host );

        $result['host']          = $host;
        $result['host_guidance'] = $guidance;

        // Check if we can write a physical file
        $result['can_write_physical'] = GetCited_Llms_Txt::instance()->can_write_physical_file();

        // Generate download URL
        $result['download_url'] = wp_nonce_url(
            admin_url( 'admin-ajax.php?action=getcited_download_llms' ),
            'getcited_admin',
            'nonce'
        );

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Fix llms.txt accessibility issue
     *
     * Handles actions to fix llms.txt accessibility:
     * - write_physical: Write llms.txt as a physical file
     * - skip: Mark as skipped, continue with setup
     */
    public function ajax_fix_llms() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $action = isset( $_POST['fix_action'] ) ? sanitize_text_field( wp_unslash( $_POST['fix_action'] ) ) : 'write_physical';

        switch ( $action ) {
            case 'write_physical':
                // Enable physical file writing in settings
                $settings = GetCited_Settings::instance();
                $settings->set( 'llms_write_physical', true );

                // Write the physical file
                $llms   = GetCited_Llms_Txt::instance();
                $result = $llms->write_physical_file();

                if ( ! $result['success'] ) {
                    wp_send_json_error( array(
                        'message'   => $result['message'],
                        'details'   => $result['details'] ?? null,
                        'need_manual' => true,
                    ) );
                }

                // Verify it's now accessible
                $health_check   = GetCited_Health_Check::instance();
                $verify_result  = $health_check->verify_llms_txt_accessible();

                if ( $verify_result['accessible'] ) {
                    wp_send_json_success( array(
                        'message'    => __( 'Physical file written and verified!', 'getcited' ),
                        'accessible' => true,
                    ) );
                } else {
                    // File written but still not accessible (unusual case)
                    wp_send_json_success( array(
                        'message'    => __( 'File written, but verification pending. It may take a moment to propagate.', 'getcited' ),
                        'accessible' => false,
                        'retry'      => true,
                    ) );
                }
                break;

            case 'skip':
                // Mark as skipped for dashboard reminder
                $settings = GetCited_Settings::instance();
                $settings->set( 'llms_verification_skipped', true );

                wp_send_json_success( array(
                    'message' => __( 'Verification skipped. You can fix this later in settings.', 'getcited' ),
                    'skipped' => true,
                ) );
                break;

            default:
                wp_send_json_error( array( 'message' => 'Invalid action' ) );
        }
    }

    /**
     * Render wizard
     */
    public function render() {
        $steps = $this->get_steps();
        $site_types = $this->get_site_types();
        $settings = GetCited_Settings::instance();
        $org = $settings->get( 'organization' );

        // Pre-fill organization name
        if ( empty( $org['name'] ) ) {
            $org['name'] = get_bloginfo( 'name' );
        }

        include GETCITED_PLUGIN_DIR . 'templates/wizard.php';
    }
}

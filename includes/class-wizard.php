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
            'site_type' => array(
                'title' => __( 'Site Type', 'getcited' ),
                'description' => __( "Let's make your site visible to AI search engines.", 'getcited' ),
            ),
            'done' => array(
                'title' => __( 'Done!', 'getcited' ),
                'description' => __( 'Your site is configured for AI discovery.', 'getcited' ),
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

        // Pre-fill Use Cases for AI with site-type defaults (v1.9.9.12).
        $use_cases_default = $llms_txt->get_default_use_cases( $site_type );
        $settings->set( 'llms_use_cases', $use_cases_default );

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

                // Run site scan and generate rich llms.txt content inline.
                $scanner   = GetCited_Site_Scanner::instance();
                $scan_data = $scanner->scan_site();
                $generated = $scanner->generate_llms_txt( $scan_data );
                if ( ! empty( $generated ) ) {
                    $settings->set( 'llms_txt_content', $generated );
                }

                // Pre-fill organization info from scan.
                $org         = $settings->get( 'organization' );
                $org_updated = false;
                if ( empty( $org['name'] ) && ! empty( $scan_data['site']['name'] ) ) {
                    $org['name'] = $scan_data['site']['name'];
                    $org_updated = true;
                }
                if ( empty( $org['social_urls'] ) && ! empty( $scan_data['social'] ) ) {
                    $org['social_urls'] = array_values( array_filter( $scan_data['social'] ) );
                    $org_updated        = true;
                }
                if ( $org_updated ) {
                    $settings->set( 'organization', $org );
                }

                // Build summary for the done step.
                $crawler_states = $settings->get( 'crawlers' );
                $allowed_count  = 0;
                $total_count    = 0;
                if ( is_array( $crawler_states ) ) {
                    $total_count = count( $crawler_states );
                    foreach ( $crawler_states as $status ) {
                        if ( 'allow' === $status ) {
                            ++$allowed_count;
                        }
                    }
                }

                $schema_enabled = $settings->get( 'schema_enabled' );
                $schema_source  = $settings->get( 'schema_detected_source' );

                wp_send_json_success( array(
                    'step'          => $step,
                    'llms_url'      => home_url( '/llms.txt' ),
                    'crawlers'      => array(
                        'allowed' => $allowed_count,
                        'total'   => $total_count,
                    ),
                    'schema'        => array(
                        'enabled' => (bool) $schema_enabled,
                        'source'  => $schema_source ? $schema_source : '',
                    ),
                ) );
                return;
        }

        wp_send_json_success( array( 'step' => $step ) );
    }

    /**
     * AJAX: Run site scan (legacy — scan now runs inline during site_type step save)
     */
    public function ajax_run_scan() {
        check_ajax_referer( 'getcited_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }
        wp_send_json_success();
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

        // Backup and remove any existing physical file (SEO plugin or otherwise).
        $llms = GetCited_Llms_Txt::instance();
        $llms->backup_physical_file();

        // Content already saved during site_type step — clean up any leftover transient.
        delete_transient( 'getcited_wizard_scan' );

        $settings->set( 'wizard_completed', true );

        // Flush rewrite rules to ensure llms.txt works
        flush_rewrite_rules();

        wp_send_json_success( array(
            'redirect' => admin_url( 'admin.php?page=getcited' ),
        ) );
    }

    /**
     * AJAX: Verify llms.txt accessibility (legacy — verification now handled on dashboard)
     */
    public function ajax_verify_llms() {
        check_ajax_referer( 'getcited_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }
        wp_send_json_success( array( 'accessible' => true ) );
    }

    /**
     * AJAX: Fix llms.txt accessibility (legacy — no longer used in wizard)
     */
    public function ajax_fix_llms() {
        check_ajax_referer( 'getcited_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }
        wp_send_json_success();
    }

    /**
     * Render wizard
     */
    public function render() {
        $steps      = $this->get_steps();
        $site_types = $this->get_site_types();
        $settings   = GetCited_Settings::instance();
        $org        = $settings->get( 'organization' );

        // Pre-fill organization name
        if ( empty( $org['name'] ) ) {
            $org['name'] = get_bloginfo( 'name' );
        }

        include GETCITED_PLUGIN_DIR . 'templates/wizard.php';
    }
}

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
                'title' => __( 'Welcome to GetCited', 'getcited' ),
                'description' => __( "Let's make your site visible to AI search engines.", 'getcited' ),
            ),
            'site_type' => array(
                'title' => __( 'What type of site is this?', 'getcited' ),
                'description' => __( 'This helps us configure optimal settings for you.', 'getcited' ),
            ),
            'organization' => array(
                'title' => __( 'Organization Info', 'getcited' ),
                'description' => __( 'This information appears in schema markup.', 'getcited' ),
            ),
            'crawlers' => array(
                'title' => __( 'AI Crawler Access', 'getcited' ),
                'description' => __( 'Choose which AI systems can access your content.', 'getcited' ),
            ),
            'complete' => array(
                'title' => __( 'All Set!', 'getcited' ),
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
                'description' => __( 'Personal or professional blog with articles', 'getcited' ),
                'icon' => 'dashicons-edit',
            ),
            'business' => array(
                'label' => __( 'Business', 'getcited' ),
                'description' => __( 'Company website, services, or local business', 'getcited' ),
                'icon' => 'dashicons-building',
            ),
            'news' => array(
                'label' => __( 'News / Media', 'getcited' ),
                'description' => __( 'News publication, magazine, or media outlet', 'getcited' ),
                'icon' => 'dashicons-megaphone',
            ),
            'ecommerce' => array(
                'label' => __( 'E-commerce', 'getcited' ),
                'description' => __( 'Online store selling products', 'getcited' ),
                'icon' => 'dashicons-cart',
            ),
            'other' => array(
                'label' => __( 'Other', 'getcited' ),
                'description' => __( 'Portfolio, community, or other type', 'getcited' ),
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

        // Generate appropriate llms.txt
        $llms_txt = GetCited_Llms_Txt::instance();
        $content = $llms_txt->generate_template( $site_type );
        $settings->set( 'llms_txt_content', $content );

        // Configure schema types based on site type
        $schema_types = array(
            'organization' => true,
            'article' => true,
            'author' => true,
            'faq' => true,
        );

        switch ( $site_type ) {
            case 'news':
                // News sites need article and author schema
                $schema_types['article'] = true;
                $schema_types['author'] = true;
                break;
            case 'ecommerce':
                // E-commerce: Organization and FAQ, skip article
                $schema_types['article'] = false;
                $schema_types['author'] = false;
                break;
            case 'business':
                // Business: Organization is key
                $schema_types['organization'] = true;
                break;
        }

        $settings->set( 'schema_types', $schema_types );

        return true;
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
                break;

            case 'organization':
                $org = array(
                    'name' => sanitize_text_field( $data['name'] ?? '' ),
                    'logo_url' => esc_url_raw( $data['logo_url'] ?? '' ),
                    'social_urls' => array_map( 'esc_url_raw', (array) ( $data['social_urls'] ?? array() ) ),
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
        $settings->set( 'wizard_completed', true );

        // Flush rewrite rules to ensure llms.txt works
        flush_rewrite_rules();

        wp_send_json_success( array(
            'redirect' => admin_url( 'admin.php?page=getcited' ),
        ) );
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

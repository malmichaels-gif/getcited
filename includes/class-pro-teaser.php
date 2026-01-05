<?php
/**
 * Pro Feature Teasers for GetCited
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pro Teaser class
 */
class GetCited_Pro_Teaser {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Waitlist endpoint
     */
    const WAITLIST_URL = 'https://heytc.com/getcited-api/waitlist.php';

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
        // AJAX handler for waitlist signup
        add_action( 'wp_ajax_getcited_waitlist_signup', array( $this, 'ajax_waitlist_signup' ) );
    }

    /**
     * Get Pro features list
     */
    public function get_features() {
        return array(
            'traffic' => array(
                'name' => __( 'AI Referral Traffic Dashboard', 'getcited' ),
                'description' => __( 'See which AI sources send visitors', 'getcited' ),
                'icon' => 'dashicons-chart-line',
                'teaser' => __( 'Connect Google Analytics and see exactly which AI sources send traffic — Perplexity, ChatGPT, Gemini, and more', 'getcited' ),
            ),
            'citability' => array(
                'name' => __( 'Full Citability Scoring', 'getcited' ),
                'description' => __( 'Export reports and track score history', 'getcited' ),
                'icon' => 'dashicons-search',
                'teaser' => __( 'Export citability reports as CSV or PDF, track scores over time, and run bulk audits with one click', 'getcited' ),
            ),
            'share_of_voice' => array(
                'name' => __( 'Citation Share of Voice', 'getcited' ),
                'description' => __( 'Track your competitive share', 'getcited' ),
                'icon' => 'dashicons-megaphone',
                'teaser' => __( 'Monitor keywords that matter and see your share of AI citations compared to competitors', 'getcited' ),
            ),
            'competitor' => array(
                'name' => __( 'Competitor Quick-Check', 'getcited' ),
                'description' => __( 'Spot-check competitor citability', 'getcited' ),
                'icon' => 'dashicons-visibility',
                'teaser' => __( 'Run quick citability checks on competitor URLs to see how you stack up', 'getcited' ),
            ),
            'alerts' => array(
                'name' => __( 'Citation Alerts', 'getcited' ),
                'description' => __( 'Weekly digest and Slack notifications', 'getcited' ),
                'icon' => 'dashicons-bell',
                'teaser' => __( 'Get weekly email digests of your AI visibility, plus optional Slack webhooks for real-time updates', 'getcited' ),
            ),
            'community' => array(
                'name' => __( 'Private Community Access', 'getcited' ),
                'description' => __( 'Connect with other publishers', 'getcited' ),
                'icon' => 'dashicons-groups',
                'teaser' => __( 'Join our private community of AI-focused publishers to share strategies and get direct founder access', 'getcited' ),
            ),
        );
    }

    /**
     * Get page-specific teaser content
     */
    public function get_page_teaser( $page ) {
        $teasers = array(
            'llms' => array(
                'feature' => 'share_of_voice',
                'message' => __( 'Track which keywords AI cites you for and monitor your competitive share', 'getcited' ),
            ),
            'crawlers' => array(
                'feature' => 'traffic',
                'message' => __( 'See which AI crawlers are sending real traffic with GA4 integration', 'getcited' ),
            ),
            'schema' => array(
                'feature' => 'citability',
                'message' => __( 'Run bulk citability audits across your entire site', 'getcited' ),
            ),
            'citability' => array(
                'feature' => 'citability',
                'message' => __( 'Export citability reports and track your scores over time', 'getcited' ),
            ),
            'settings' => array(
                'feature' => 'alerts',
                'message' => __( 'Get weekly digests and Slack alerts for your AI visibility', 'getcited' ),
            ),
        );

        return $teasers[ $page ] ?? $teasers['llms'];
    }

    /**
     * Get a clean site display name for the leaderboard
     * Falls back to site name if domain looks like staging/dev
     */
    private function get_site_display_name() {
        $site_domain = wp_parse_url( home_url(), PHP_URL_HOST );
        $site_domain = preg_replace( '/^www\./', '', $site_domain );

        // Check if domain looks like staging/dev environment using centralized detection.
        $is_staging = GetCited_Health_Check::instance()->is_staging_environment();

        // If staging, use site name instead.
        if ( $is_staging ) {
            $site_name = get_bloginfo( 'name' );
            if ( $site_name ) {
                // Convert to domain-like format (lowercase, no spaces).
                return strtolower( str_replace( ' ', '', $site_name ) ) . '.com';
            }
        }

        return $site_domain;
    }

    /**
     * Get a sample keyword based on site content
     */
    private function get_sample_keyword() {
        // Try to get from most used category.
        $categories = get_categories( array(
            'orderby'    => 'count',
            'order'      => 'DESC',
            'number'     => 1,
            'hide_empty' => true,
        ) );

        if ( ! empty( $categories ) && $categories[0]->slug !== 'uncategorized' ) {
            return strtolower( $categories[0]->name );
        }

        // Try site tagline words.
        $tagline = get_bloginfo( 'description' );
        if ( $tagline ) {
            $words = explode( ' ', $tagline );
            $words = array_filter( $words, function( $word ) {
                return strlen( $word ) > 4;
            } );
            if ( ! empty( $words ) ) {
                return strtolower( reset( $words ) );
            }
        }

        // Fallback.
        return __( 'your niche', 'getcited' );
    }

    /**
     * Check if user has already joined waitlist
     */
    public function has_joined_waitlist() {
        $settings = GetCited_Settings::instance();
        return $settings->get( 'waitlist_submitted' );
    }

    /**
     * Render compact page teaser (for non-dashboard pages)
     */
    public function render_page_teaser( $page ) {
        $settings = GetCited_Settings::instance();
        if ( $settings->get( 'license_status' ) !== 'free' ) {
            return; // Don't show to Pro users
        }

        $teaser_data = $this->get_page_teaser( $page );
        $features    = $this->get_features();
        $feature     = $features[ $teaser_data['feature'] ] ?? $features['traffic'];
        $has_joined  = $this->has_joined_waitlist();
        ?>
        <div class="getcited-page-teaser">
            <div class="teaser-content">
                <span class="dashicons <?php echo esc_attr( $feature['icon'] ); ?>"></span>
                <div class="teaser-text">
                    <strong><?php esc_html_e( 'GetCited Pro — Spring 2026', 'getcited' ); ?></strong>
                    <span class="teaser-message"><?php echo esc_html( $teaser_data['message'] ); ?></span>
                </div>
            </div>
            <?php if ( ! $has_joined ) : ?>
                <form class="getcited-teaser-form">
                    <input type="email"
                           name="email"
                           placeholder="<?php esc_attr_e( 'your@email.com', 'getcited' ); ?>"
                           required>
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Get First Access', 'getcited' ); ?>
                    </button>
                </form>
            <?php else : ?>
                <span class="teaser-joined">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e( 'On the list!', 'getcited' ); ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Pro teaser section for dashboard
     */
    public function render_dashboard_teasers() {
        $features = $this->get_features();
        $settings = GetCited_Settings::instance();
        $is_pro = $settings->get( 'license_status' ) !== 'free';
        $has_joined = $this->has_joined_waitlist();

        if ( $is_pro ) {
            return; // Don't show teasers to Pro users
        }

        ?>
        <div class="getcited-pro-teasers">
            <div class="getcited-pro-header">
                <h3><?php esc_html_e( 'GetCited Pro — Spring 2026', 'getcited' ); ?></h3>
                <p><?php esc_html_e( 'Be the first to know when Pro launches', 'getcited' ); ?></p>
            </div>

            <div class="getcited-pro-features">
                <?php foreach ( $features as $key => $feature ) : ?>
                    <div class="getcited-pro-feature" data-feature="<?php echo esc_attr( $key ); ?>">
                        <div class="feature-icon">
                            <span class="dashicons <?php echo esc_attr( $feature['icon'] ); ?>"></span>
                        </div>
                        <div class="feature-content">
                            <h4><?php echo esc_html( $feature['name'] ); ?></h4>
                            <p><?php echo esc_html( $feature['teaser'] ); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ( ! $has_joined ) : ?>
                <div class="getcited-waitlist-form">
                    <p><?php esc_html_e( 'Be first to know when Pro launches:', 'getcited' ); ?></p>
                    <form id="getcited-waitlist-form">
                        <input type="email"
                               name="email"
                               placeholder="<?php esc_attr_e( 'your@email.com', 'getcited' ); ?>"
                               required>
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( 'Get First Access', 'getcited' ); ?>
                        </button>
                    </form>
                    <div class="getcited-waitlist-message" style="display: none;"></div>
                    <?php
                    $waitlist_count = $this->get_waitlist_count();
                    if ( $waitlist_count >= 100 ) : ?>
                        <p class="getcited-waitlist-count">
                            <?php esc_html_e( 'Join', 'getcited' ); ?>
                            <span class="count"><?php echo esc_html( number_format( $waitlist_count ) ); ?></span>
                            <?php esc_html_e( 'publishers on the waitlist', 'getcited' ); ?>
                        </p>
                    <?php endif; ?>
                    <p class="getcited-pricing-hint"><?php esc_html_e( 'Early bird pricing for waitlist members', 'getcited' ); ?></p>
                </div>
            <?php else : ?>
                <div class="getcited-waitlist-confirmed">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <p><?php esc_html_e( "You're on the list! We'll email you when Pro launches.", 'getcited' ); ?></p>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }

    /**
     * AJAX: Handle waitlist signup
     */
    public function ajax_waitlist_signup() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

        if ( ! is_email( $email ) ) {
            wp_send_json_error( array(
                'message' => __( 'Please enter a valid email address', 'getcited' ),
            ) );
        }

        // Post to external waitlist API
        $response = wp_remote_post( self::WAITLIST_URL, array(
            'timeout' => 5,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'email'   => $email,
                'site_url' => home_url(),
                'website' => '', // Honeypot field (empty for real users)
            ) ),
        ) );

        $api_count = 0;
        $api_success = false;

        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! empty( $body['success'] ) ) {
                $api_success = true;
                $api_count = isset( $body['waitlist_count'] ) ? (int) $body['waitlist_count'] : 0;
                // Cache the API count for display
                update_option( 'getcited_waitlist_count', $api_count );
            }
        }

        // Store locally as fallback if API failed
        if ( ! $api_success ) {
            $waitlist = get_option( 'getcited_local_waitlist', array() );
            $waitlist[ $email ] = array(
                'email'     => $email,
                'site_url'  => home_url(),
                'timestamp' => current_time( 'c' ),
            );
            update_option( 'getcited_local_waitlist', $waitlist );
        }

        // Remember that user has joined waitlist
        $settings = GetCited_Settings::instance();
        $settings->set( 'waitlist_submitted', true );

        // Use API count if available, otherwise local count
        $display_count = $api_count > 0 ? $api_count : $this->get_waitlist_count();

        wp_send_json_success( array(
            'message' => __( "You're on the list! We'll email you when Pro launches.", 'getcited' ),
            'count'   => $display_count,
        ) );
    }

    /**
     * Get waitlist count (API cached value, or local fallback)
     */
    public function get_waitlist_count() {
        // First check for cached API count
        $api_count = get_option( 'getcited_waitlist_count', 0 );
        if ( $api_count > 0 ) {
            return $api_count;
        }

        // Fallback to local waitlist count
        $waitlist = get_option( 'getcited_local_waitlist', array() );
        return count( $waitlist );
    }

    /**
     * Check if user is Pro
     */
    public function is_pro() {
        $settings = GetCited_Settings::instance();
        return $settings->get( 'license_status' ) !== 'free';
    }

    /**
     * Render upgrade notice
     */
    public function render_upgrade_notice( $feature = '' ) {
        $features = $this->get_features();
        $feature_name = isset( $features[ $feature ] ) ? $features[ $feature ]['name'] : __( 'This feature', 'getcited' );
        ?>
        <div class="getcited-upgrade-notice">
            <p>
                <?php
                printf(
                    /* translators: %s: feature name */
                    esc_html__( '%s requires GetCited Pro.', 'getcited' ),
                    esc_html( $feature_name )
                ); ?>
                <a href="#" class="getcited-join-waitlist"><?php esc_html_e( 'Join the waitlist', 'getcited' ); ?></a>
            </p>
        </div>
        <?php
    }
}

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
    const WAITLIST_URL = 'https://heytc.com/getcited/api/waitlist';

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
                'description' => __( 'Audit unlimited posts with export', 'getcited' ),
                'icon' => 'dashicons-search',
                'teaser' => __( 'Score unlimited posts, run bulk audits across your whole site, and export reports to share with your team', 'getcited' ),
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
            'support' => array(
                'name' => __( 'Priority Support', 'getcited' ),
                'description' => __( 'Fast help when you need it', 'getcited' ),
                'icon' => 'dashicons-sos',
                'teaser' => __( 'Jump the queue with priority email support and faster response times', 'getcited' ),
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
                'message' => __( 'Score unlimited posts and export reports to share with your team', 'getcited' ),
            ),
            'settings' => array(
                'feature' => 'alerts',
                'message' => __( 'Get weekly digests and Slack alerts for your AI visibility', 'getcited' ),
            ),
        );

        return $teasers[ $page ] ?? $teasers['llms'];
    }

    /**
     * Get sample report data (for modal preview)
     */
    public function get_sample_report() {
        return array(
            'period' => __( 'Last 30 Days', 'getcited' ),
            'ai_traffic' => array(
                'total' => 1247,
                'change' => '+18%',
                'sources' => array(
                    array( 'name' => 'Perplexity', 'visits' => 847, 'percent' => 68 ),
                    array( 'name' => 'ChatGPT', 'visits' => 203, 'percent' => 16 ),
                    array( 'name' => 'Gemini', 'visits' => 142, 'percent' => 11 ),
                    array( 'name' => 'Claude', 'visits' => 38, 'percent' => 3 ),
                    array( 'name' => 'Other', 'visits' => 17, 'percent' => 1 ),
                ),
            ),
            'share_of_voice' => array(
                'keywords_tracked' => 12,
                'your_share' => 34,
                'top_keyword' => 'wordpress security tips',
            ),
            'top_pages' => array(
                array( 'title' => 'WordPress Security Guide', 'visits' => 312, 'citability' => 92 ),
                array( 'title' => 'Best Caching Plugins 2025', 'visits' => 289, 'citability' => 87 ),
                array( 'title' => 'Speed Optimization Tips', 'visits' => 201, 'citability' => 78 ),
            ),
            'avg_citability' => 72,
        );
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
                    <strong><?php esc_html_e( 'GetCited Pro - Coming Soon', 'getcited' ); ?></strong>
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
                <h3><?php esc_html_e( 'GetCited Pro — Coming Soon', 'getcited' ); ?></h3>
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
                        <div class="feature-locked">
                            <span class="dashicons dashicons-lock"></span>
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
                </div>
            <?php else : ?>
                <div class="getcited-waitlist-confirmed">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <p><?php esc_html_e( "You're on the list! We'll email you when Pro launches.", 'getcited' ); ?></p>
                </div>
            <?php endif; ?>

            <div class="getcited-sample-report">
                <button type="button" class="button button-hero getcited-view-sample">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php esc_html_e( 'View Sample Report', 'getcited' ); ?>
                </button>
                <p class="sample-hint"><?php esc_html_e( 'See what Pro users will see', 'getcited' ); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render sample report modal
     */
    public function render_sample_modal() {
        $report = $this->get_sample_report();
        ?>
        <div id="getcited-sample-modal" class="getcited-modal" style="display: none;">
            <div class="getcited-modal-content">
                <div class="getcited-modal-header">
                    <h2><?php esc_html_e( 'What Pro Users See', 'getcited' ); ?></h2>
                    <button type="button" class="getcited-modal-close">&times;</button>
                </div>
                <div class="getcited-modal-body">
                    <p class="getcited-sample-notice">
                        <?php esc_html_e( 'This is what your dashboard could look like. The question is — what are your real numbers?', 'getcited' ); ?>
                    </p>

                    <div class="getcited-sample-section">
                        <h3><?php esc_html_e( 'AI Referral Traffic', 'getcited' ); ?> — <?php echo esc_html( $report['period'] ); ?></h3>
                        <div class="getcited-sample-stat">
                            <span class="stat-number"><?php echo esc_html( number_format( $report['ai_traffic']['total'] ) ); ?></span>
                            <span class="stat-label"><?php esc_html_e( 'visits from AI', 'getcited' ); ?></span>
                            <span class="stat-change positive"><?php echo esc_html( $report['ai_traffic']['change'] ); ?></span>
                        </div>
                        <div class="getcited-sample-bars">
                            <?php foreach ( $report['ai_traffic']['sources'] as $source ) : ?>
                                <div class="sample-bar">
                                    <span class="bar-label"><?php echo esc_html( $source['name'] ); ?></span>
                                    <div class="bar-track">
                                        <div class="bar-fill" style="width: <?php echo esc_attr( $source['percent'] ); ?>%;"></div>
                                    </div>
                                    <span class="bar-value"><?php echo esc_html( number_format( $source['visits'] ) ); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="getcited-sample-section">
                        <h3><?php esc_html_e( 'Citation Share of Voice', 'getcited' ); ?></h3>
                        <div class="getcited-sample-stat">
                            <span class="stat-number"><?php echo esc_html( $report['share_of_voice']['your_share'] ); ?>%</span>
                            <span class="stat-label"><?php esc_html_e( 'your share of AI citations', 'getcited' ); ?></span>
                        </div>
                        <div class="getcited-share-details">
                            <span class="share-detail">
                                <strong><?php echo esc_html( $report['share_of_voice']['keywords_tracked'] ); ?></strong>
                                <?php esc_html_e( 'keywords tracked', 'getcited' ); ?>
                            </span>
                            <span class="share-detail">
                                <?php esc_html_e( 'Top keyword:', 'getcited' ); ?>
                                <strong><?php echo esc_html( $report['share_of_voice']['top_keyword'] ); ?></strong>
                            </span>
                        </div>
                    </div>

                    <div class="getcited-sample-section">
                        <h3><?php esc_html_e( 'Top Performing Pages', 'getcited' ); ?></h3>
                        <table class="getcited-sample-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Page', 'getcited' ); ?></th>
                                    <th><?php esc_html_e( 'AI Visits', 'getcited' ); ?></th>
                                    <th><?php esc_html_e( 'Citability', 'getcited' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $report['top_pages'] as $page ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( $page['title'] ); ?></td>
                                        <td><?php echo esc_html( number_format( $page['visits'] ) ); ?></td>
                                        <td><?php echo esc_html( $page['citability'] ); ?>/100</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="getcited-modal-footer">
                    <p><?php esc_html_e( 'AI might already be sending you traffic. You just can\'t see it yet.', 'getcited' ); ?></p>
                    <button type="button" class="button button-primary getcited-join-waitlist">
                        <?php esc_html_e( 'Find Out When Pro Launches', 'getcited' ); ?>
                    </button>
                </div>
            </div>
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

        // For now, since the endpoint doesn't exist, we'll simulate success
        // In production, this would POST to WAITLIST_URL

        $response = wp_remote_post( self::WAITLIST_URL, array(
            'timeout' => 5,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'email' => $email,
                'site_url' => home_url(),
                'timestamp' => current_time( 'c' ),
            ) ),
        ) );

        // Store locally for now
        $waitlist = get_option( 'getcited_local_waitlist', array() );
        $waitlist[] = array(
            'email' => $email,
            'site_url' => home_url(),
            'timestamp' => current_time( 'c' ),
        );
        update_option( 'getcited_local_waitlist', $waitlist );

        // Remember that user has joined waitlist
        $settings = GetCited_Settings::instance();
        $settings->set( 'waitlist_submitted', true );

        wp_send_json_success( array(
            'message' => __( "You're on the list! We'll email you when Pro launches.", 'getcited' ),
            'count' => count( $waitlist ),
        ) );
    }

    /**
     * Get local waitlist count (fallback)
     */
    public function get_waitlist_count() {
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
            <span class="dashicons dashicons-lock"></span>
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

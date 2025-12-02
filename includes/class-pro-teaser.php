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
                'name' => __( 'AI Referral Traffic', 'getcited' ),
                'description' => __( 'Prove AI is sending you traffic', 'getcited' ),
                'icon' => 'dashicons-chart-line',
                'teaser' => __( 'Finally prove AI traffic is real — see exactly how many visitors ChatGPT and Perplexity send you', 'getcited' ),
            ),
            'citations' => array(
                'name' => __( 'Citation Tracking', 'getcited' ),
                'description' => __( 'See when AI recommends you', 'getcited' ),
                'icon' => 'dashicons-format-quote',
                'teaser' => __( 'Know the moment AI recommends you — see which queries triggered citations', 'getcited' ),
            ),
            'full_audit' => array(
                'name' => __( 'Full Site Audit', 'getcited' ),
                'description' => __( 'Audit your whole site, not just 5 posts', 'getcited' ),
                'icon' => 'dashicons-search',
                'teaser' => __( 'Stop guessing which posts need work — audit everything, prioritize what matters', 'getcited' ),
            ),
            'alerts' => array(
                'name' => __( 'Citation Alerts', 'getcited' ),
                'description' => __( 'Get pinged when you get cited', 'getcited' ),
                'icon' => 'dashicons-bell',
                'teaser' => __( 'Never miss a win — get pinged the instant AI cites your content', 'getcited' ),
            ),
        );
    }

    /**
     * Get page-specific teaser content
     */
    public function get_page_teaser( $page ) {
        $teasers = array(
            'llms' => array(
                'feature' => 'citations',
                'message' => __( 'See when AI actually cites your llms.txt content', 'getcited' ),
            ),
            'crawlers' => array(
                'feature' => 'traffic',
                'message' => __( 'Know exactly which AI crawlers are driving real traffic', 'getcited' ),
            ),
            'schema' => array(
                'feature' => 'full_audit',
                'message' => __( 'Audit your entire site\'s schema for AI optimization', 'getcited' ),
            ),
            'citability' => array(
                'feature' => 'full_audit',
                'message' => __( 'Unlock unlimited citability audits for your whole site', 'getcited' ),
            ),
            'settings' => array(
                'feature' => 'alerts',
                'message' => __( 'Get instant alerts when AI cites your content', 'getcited' ),
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
                    array( 'name' => 'Bing Copilot', 'visits' => 142, 'percent' => 11 ),
                    array( 'name' => 'You.com', 'visits' => 38, 'percent' => 3 ),
                    array( 'name' => 'Other', 'visits' => 17, 'percent' => 1 ),
                ),
            ),
            'citations' => array(
                'total' => 47,
                'sources' => array(
                    array( 'name' => 'Perplexity', 'count' => 28 ),
                    array( 'name' => 'Google AI Overview', 'count' => 12 ),
                    array( 'name' => 'Bing Copilot', 'count' => 7 ),
                ),
            ),
            'top_pages' => array(
                array( 'title' => 'WordPress Security Guide', 'visits' => 312, 'citations' => 8 ),
                array( 'title' => 'Best Caching Plugins 2025', 'visits' => 289, 'citations' => 6 ),
                array( 'title' => 'Speed Optimization Tips', 'visits' => 201, 'citations' => 5 ),
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
                        <?php esc_html_e( 'Reserve Your Spot', 'getcited' ); ?>
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
                <h3><?php esc_html_e( 'Pro Features — Coming Soon', 'getcited' ); ?></h3>
                <p><?php esc_html_e( 'Founding members lock in early-adopter pricing', 'getcited' ); ?></p>
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
                            <?php esc_html_e( 'Reserve Your Spot', 'getcited' ); ?>
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
                        <h3><?php esc_html_e( 'Citations Detected', 'getcited' ); ?></h3>
                        <div class="getcited-sample-stat">
                            <span class="stat-number"><?php echo esc_html( $report['citations']['total'] ); ?></span>
                            <span class="stat-label"><?php esc_html_e( 'citations this month', 'getcited' ); ?></span>
                        </div>
                        <div class="getcited-citation-sources">
                            <?php foreach ( $report['citations']['sources'] as $source ) : ?>
                                <span class="citation-source">
                                    <?php echo esc_html( $source['name'] ); ?>: <?php echo esc_html( $source['count'] ); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="getcited-sample-section">
                        <h3><?php esc_html_e( 'Top Performing Pages', 'getcited' ); ?></h3>
                        <table class="getcited-sample-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Page', 'getcited' ); ?></th>
                                    <th><?php esc_html_e( 'AI Visits', 'getcited' ); ?></th>
                                    <th><?php esc_html_e( 'Citations', 'getcited' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $report['top_pages'] as $page ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( $page['title'] ); ?></td>
                                        <td><?php echo esc_html( number_format( $page['visits'] ) ); ?></td>
                                        <td><?php echo esc_html( $page['citations'] ); ?></td>
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

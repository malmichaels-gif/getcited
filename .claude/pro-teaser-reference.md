# GetCited Pro Reference Documentation

This file preserves all Pro-related code removed from GetCited for WordPress.org compliance.
Use this reference when building GetCited Pro as a separate plugin or for heytc.com marketing.

---

## 1. Full Class: `class-pro-teaser.php`

```php
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
```

---

## 2. Pro Feature List & Marketing Copy

| Feature Key | Name | Description | Icon | Teaser Copy |
|-------------|------|-------------|------|-------------|
| `traffic` | AI Referral Traffic Dashboard | See which AI sources send visitors | `dashicons-chart-line` | Connect Google Analytics and see exactly which AI sources send traffic — Perplexity, ChatGPT, Gemini, and more |
| `citability` | Full Citability Scoring | Export reports and track score history | `dashicons-search` | Export citability reports as CSV or PDF, track scores over time, and run bulk audits with one click |
| `share_of_voice` | Citation Share of Voice | Track your competitive share | `dashicons-megaphone` | Monitor keywords that matter and see your share of AI citations compared to competitors |
| `competitor` | Competitor Quick-Check | Spot-check competitor citability | `dashicons-visibility` | Run quick citability checks on competitor URLs to see how you stack up |
| `alerts` | Citation Alerts | Weekly digest and Slack notifications | `dashicons-bell` | Get weekly email digests of your AI visibility, plus optional Slack webhooks for real-time updates |
| `community` | Private Community Access | Connect with other publishers | `dashicons-groups` | Join our private community of AI-focused publishers to share strategies and get direct founder access |

### Page-Specific Teaser Messages

| Page | Feature | Message |
|------|---------|---------|
| llms | share_of_voice | Track which keywords AI cites you for and monitor your competitive share |
| crawlers | traffic | See which AI crawlers are sending real traffic with GA4 integration |
| schema | citability | Run bulk citability audits across your entire site |
| citability | citability | Export citability reports and track your scores over time |
| settings | alerts | Get weekly digests and Slack alerts for your AI visibility |

---

## 3. CSS Blocks

### Pro Teasers Dashboard Section
```css
/* ==========================================================================
   Pro Feature Teasers
   ========================================================================== */

.getcited-pro-teasers {
    background: var(--getcited-dark-bg);
    border: 1px solid var(--getcited-dark-border);
    border-radius: var(--getcited-radius-lg);
    padding: var(--getcited-space-xl);
    color: var(--getcited-dark-text);
}

.getcited-pro-header {
    text-align: center;
    margin-bottom: var(--getcited-space-xl);
}

.getcited-pro-header h3 {
    font-size: 22px;
    color: #fff;
    margin: 0 0 var(--getcited-space-sm) 0;
}

.getcited-pro-header p {
    color: var(--getcited-dark-muted);
    margin: 0;
}

.getcited-pro-features {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--getcited-space-md);
    margin-bottom: var(--getcited-space-xl);
}

.getcited-pro-feature {
    display: flex;
    gap: var(--getcited-space-md);
    padding: var(--getcited-space-md);
    background: var(--getcited-dark-card);
    border: 1px solid var(--getcited-dark-border);
    border-radius: var(--getcited-radius-md);
    position: relative;
}

.getcited-pro-feature .feature-icon {
    width: 40px;
    height: 40px;
    background: var(--getcited-accent);
    border-radius: var(--getcited-radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.getcited-pro-feature .feature-icon .dashicons {
    color: #fff;
    font-size: 22px;
    width: 20px;
    height: 20px;
}

.getcited-pro-feature .feature-content h4 {
    font-size: 15px;
    color: #fff;
    margin: 0 0 4px 0;
}

.getcited-pro-feature .feature-content p {
    font-size: 13px;
    color: var(--getcited-dark-muted);
    margin: 0;
    line-height: 1.4;
}

.getcited-pro-feature .feature-locked {
    position: absolute;
    top: var(--getcited-space-md);
    right: var(--getcited-space-md);
    color: var(--getcited-dark-muted);
}

.getcited-pro-feature .feature-locked .dashicons {
    font-size: 18px;
    width: 16px;
    height: 16px;
}
```

### Waitlist Form Styles
```css
/* Waitlist Form */
.getcited-waitlist-form {
    text-align: center;
    margin-bottom: var(--getcited-space-lg);
}

.getcited-waitlist-form p {
    color: var(--getcited-dark-muted);
    margin-bottom: var(--getcited-space-md);
}

.getcited-waitlist-form form {
    display: flex;
    justify-content: center;
    gap: var(--getcited-space-sm);
    max-width: 400px;
    margin: 0 auto var(--getcited-space-md);
}

.getcited-waitlist-form input[type="email"] {
    flex: 1;
    padding: 10px 14px;
    border: 1px solid var(--getcited-dark-border);
    border-radius: var(--getcited-radius-sm);
    background: var(--getcited-dark-card);
    color: var(--getcited-dark-text);
    font-size: 15px;
}

.getcited-waitlist-form input[type="email"]::placeholder {
    color: var(--getcited-dark-muted);
}

.getcited-waitlist-form input[type="email"]:focus {
    outline: none;
    border-color: var(--getcited-accent);
}

.getcited-waitlist-form .button-primary {
    background: var(--getcited-accent);
    border: none;
    padding: 10px 20px;
    font-weight: 500;
}

.getcited-waitlist-form .button-primary:hover {
    background: var(--getcited-accent-light);
}

.getcited-waitlist-count {
    font-size: 14px;
    color: var(--getcited-dark-muted);
}

.getcited-waitlist-count .count {
    color: var(--getcited-accent-light);
    font-weight: 600;
}

.getcited-pricing-hint {
    font-size: 13px;
    color: var(--getcited-dark-muted);
    margin-top: var(--getcited-space-sm);
    opacity: 0.8;
}
```

### Page Teaser Banner Styles
```css
/* ==========================================================================
   Page Teaser (Compact Banner)
   ========================================================================== */

.getcited-page-teaser {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--getcited-space-md);
    padding: var(--getcited-space-md) var(--getcited-space-lg);
    background: var(--getcited-dark-bg);
    border: 1px solid var(--getcited-dark-border);
    border-radius: var(--getcited-radius-md);
    margin-bottom: var(--getcited-space-lg);
}

.getcited-page-teaser .teaser-content {
    display: flex;
    align-items: center;
    gap: var(--getcited-space-md);
    color: var(--getcited-dark-text);
}

.getcited-page-teaser .teaser-content .dashicons {
    font-size: 22px;
    width: 20px;
    height: 20px;
    color: var(--getcited-accent);
}

.getcited-page-teaser .teaser-text {
    font-size: 15px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.getcited-page-teaser .teaser-text strong {
    color: var(--getcited-accent-light);
}

.getcited-page-teaser .teaser-text .teaser-message {
    color: var(--getcited-dark-text);
    font-size: 14px;
}

/* Inline form in page teaser */
.getcited-teaser-form {
    display: flex;
    gap: var(--getcited-space-sm);
    align-items: center;
}

.getcited-teaser-form input[type="email"] {
    padding: 6px 12px;
    border: 1px solid var(--getcited-dark-border);
    border-radius: var(--getcited-radius-sm);
    background: var(--getcited-dark-card);
    color: var(--getcited-dark-text);
    font-size: 14px;
    width: 180px;
}

.getcited-teaser-form input[type="email"]::placeholder {
    color: #d1d5db;
}

.getcited-teaser-form input[type="email"]:focus {
    outline: none;
    border-color: var(--getcited-accent);
}

.getcited-teaser-form .button-primary {
    background: var(--getcited-accent);
    border-color: var(--getcited-accent);
    color: #fff;
    padding: 6px 14px;
    white-space: nowrap;
}

.getcited-teaser-form .button-primary:hover {
    background: var(--getcited-accent-light);
    border-color: var(--getcited-accent-light);
    color: #fff;
}

.getcited-teaser-form .button-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.getcited-page-teaser .teaser-joined {
    display: flex;
    align-items: center;
    gap: var(--getcited-space-xs);
    color: var(--getcited-success);
    font-size: 14px;
    font-weight: 500;
}

.getcited-page-teaser .teaser-joined .dashicons {
    font-size: 20px;
    width: 18px;
    height: 18px;
}

/* Waitlist Confirmed State */
.getcited-waitlist-confirmed {
    text-align: center;
    padding: var(--getcited-space-lg);
    background: rgba(16, 185, 129, 0.1);
    border-radius: var(--getcited-radius-md);
    margin-bottom: var(--getcited-space-lg);
}

.getcited-waitlist-confirmed .dashicons {
    font-size: 34px;
    width: 32px;
    height: 32px;
    color: var(--getcited-success);
    margin-bottom: var(--getcited-space-sm);
}

.getcited-waitlist-confirmed p {
    color: var(--getcited-dark-text);
    margin: 0;
}
```

### Responsive Styles
```css
@media (max-width: 768px) {
    .getcited-pro-features {
        grid-template-columns: 1fr;
    }

    .getcited-waitlist-form form {
        flex-direction: column;
    }

    .getcited-page-teaser {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--getcited-space-sm);
    }

    .getcited-teaser-form {
        width: 100%;
    }

    .getcited-teaser-form input[type="email"] {
        flex: 1;
    }
}

@media (max-width: 480px) {
    .getcited-teaser-form {
        flex-direction: column;
        align-items: stretch;
    }

    .getcited-teaser-form input[type="email"] {
        width: 100%;
    }
}
```

### Join Waitlist Button Style
```css
.getcited-join-waitlist {
    background: var(--getcited-primary) !important;
    border: 1px solid var(--getcited-primary) !important;
    color: #fff !important;
}

.getcited-join-waitlist:hover {
    background: var(--getcited-primary-dark) !important;
    border-color: var(--getcited-primary-dark) !important;
    color: #fff !important;
}
```

---

## 4. JavaScript Functions

### Dashboard Waitlist Form Handler
```javascript
function initWaitlistForm() {
    const form = document.getElementById('getcited-waitlist-form');
    if (!form) return;

    form.addEventListener('submit', (e) => {
        e.preventDefault();

        const emailInput = form.querySelector('input[type="email"]');
        const submitBtn = form.querySelector('button[type="submit"]');
        const messageEl = document.querySelector('.getcited-waitlist-message');

        submitBtn.disabled = true;
        submitBtn.textContent = 'Joining...';

        ajax('getcited_waitlist_signup', { email: emailInput.value })
            .then(response => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Reserve Your Spot';

                if (response.success) {
                    form.style.display = 'none';
                    messageEl.style.display = 'block';
                    messageEl.innerHTML = `<p style="color: #10b981;">✓ ${response.data.message}</p>`;

                    // Only show/update count if 100+
                    const countEl = document.querySelector('.getcited-waitlist-count');
                    if (countEl && response.data.count && response.data.count >= 100) {
                        countEl.querySelector('.count').textContent = response.data.count.toLocaleString();
                        countEl.style.display = 'block';
                    }
                } else {
                    messageEl.style.display = 'block';
                    messageEl.innerHTML = `<p style="color: #ef4444;">${response.data.message}</p>`;
                }
            })
            .catch(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Reserve Your Spot';
            });
    });
}
```

### Compact Waitlist Buttons (Page Teasers)
```javascript
function initCompactWaitlistButtons() {
    // Handle inline teaser forms
    document.querySelectorAll('.getcited-teaser-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const emailInput = this.querySelector('input[type="email"]');
            const submitBtn = this.querySelector('button[type="submit"]');
            const email = emailInput.value.trim();

            if (!email) return;

            // Disable form while submitting
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            emailInput.disabled = true;
            submitBtn.textContent = 'Joining...';

            ajax('getcited_waitlist_signup', { email: email })
                .then(response => {
                    if (response.success) {
                        // Replace form with confirmation
                        this.outerHTML = '<span class="teaser-joined"><span class="dashicons dashicons-yes-alt"></span> On the list!</span>';
                    } else {
                        submitBtn.disabled = false;
                        emailInput.disabled = false;
                        submitBtn.textContent = originalText;
                        // Show error inline
                        const errorMsg = response.data?.message || 'Failed to join waitlist.';
                        emailInput.setCustomValidity(errorMsg);
                        emailInput.reportValidity();
                        setTimeout(() => emailInput.setCustomValidity(''), 3000);
                    }
                })
                .catch(() => {
                    submitBtn.disabled = false;
                    emailInput.disabled = false;
                    submitBtn.textContent = originalText;
                    emailInput.setCustomValidity('Failed to join. Please try again.');
                    emailInput.reportValidity();
                    setTimeout(() => emailInput.setCustomValidity(''), 3000);
                });
        });
    });
}
```

### Initialization Calls (in DOMContentLoaded)
```javascript
initWaitlistForm();
initCompactWaitlistButtons();
```

---

## 5. readme.txt Pro Section

```
= GetCited Pro — Spring 2026 =

- AI Referral Traffic Dashboard (see visits from ChatGPT, Perplexity, etc.)
- Full Citability Scoring (audit unlimited posts with export)
- Citation Share of Voice (track your competitive share)
- Competitor Quick-Check (spot-check competitor citability)
- Citation Alerts (weekly digests + Slack webhooks)
- Private Community Access (connect with other AI-focused publishers)

Join the waitlist from your dashboard for early bird pricing!
```

---

## 6. Template Render Call Examples

### Dashboard Template (dashboard.php)
```php
$pro_teaser = GetCited_Pro_Teaser::instance();

// ... later in the template:

<!-- Pro Features Section -->
<?php $pro_teaser->render_dashboard_teasers(); ?>
```

### Other Page Templates (citability.php, crawlers.php, llms-txt.php, schema.php, settings.php)
```php
$pro_teaser = GetCited_Pro_Teaser::instance();

// ... inside the template:

<!-- Pro Teaser Banner -->
<?php $pro_teaser->render_page_teaser( 'citability' ); ?>
```

---

## 7. Settings Keys (class-settings.php defaults)

```php
// Pro (future)
'license_key' => '',
'license_status' => 'free',
'site_uuid' => '',
'ga4_connected' => false,
'ga4_property_id' => '',
'waitlist_submitted' => false, // Remember if user joined waitlist
```

### Sanitization Cases
```php
case 'license_key':
case 'site_uuid':
case 'ga4_property_id':
    return sanitize_text_field( $value );

case 'license_status':
    $valid_statuses = array( 'free', 'pro', 'agency' );
    return in_array( $value, $valid_statuses, true ) ? $value : 'free';
```

### Export Exclusion
```php
// Remove sensitive data
unset( $export['license_key'] );
```

### Import Preservation
```php
$preserve = array(
    'license_key' => $this->settings['license_key'],
    // ...
);
```

---

## 8. REST API Endpoints (getcited.php)

### Placeholder Pro Endpoints
```php
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
```

### api_pro_required() Function
```php
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
```

---

## 9. Other Pro-Related Code

### System Info (class-dashboard.php)
```php
'License Status' => ucfirst( $settings->get( 'license_status' ) ),
```

### Future Pro Meta (class-citability.php)
```php
// Future Pro meta
register_post_meta( 'post', '_getcited_citation_count', array(
    'type' => 'integer',
    'default' => 0,
    'single' => true,
    'show_in_rest' => true,
    'description' => 'Number of AI citations detected',
) );
```

### Waitlist Cleanup (uninstall.php)
```php
delete_option( 'getcited_local_waitlist' );
```

---

## Waitlist API Endpoint

**URL:** `https://heytc.com/getcited-api/waitlist.php`

**Request:**
```json
{
    "email": "user@example.com",
    "site_url": "https://example.com",
    "website": ""  // Honeypot field (should be empty)
}
```

**Response:**
```json
{
    "success": true,
    "waitlist_count": 150
}
```

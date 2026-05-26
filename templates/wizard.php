<?php
/**
 * Setup wizard template
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

( function() {
$wizard = GetCited_Wizard::instance();
$steps = $wizard->get_steps();
$site_types = $wizard->get_site_types();
?>

<div class="wrap getcited-wrap getcited-wizard-wrap">

    <div class="getcited-wizard">

        <!-- Progress Bar -->
        <div class="getcited-wizard-progress">
            <?php $step_num = 1; foreach ( $steps as $key => $step ) : ?>
                <div class="progress-step" data-step="<?php echo esc_attr( $key ); ?>">
                    <span class="step-number"><?php echo esc_html( $step_num ); ?></span>
                    <span class="step-title"><?php echo esc_html( $step['title'] ); ?></span>
                </div>
            <?php $step_num++; endforeach; ?>
        </div>

        <!-- Step 1: Welcome + Site Type (combined) -->
        <div class="getcited-wizard-step" data-step="site_type">
            <div class="wizard-content">
                <h1><?php esc_html_e( 'Welcome to GetCited', 'getcited' ); ?></h1>
                <p class="wizard-subtitle">
                    <?php esc_html_e( "Let's make your site visible to AI search engines like ChatGPT, Gemini, Grok, Claude, and Perplexity. What type of site is this?", 'getcited' ); ?>
                </p>

                <div class="getcited-site-types">
                    <?php foreach ( $site_types as $key => $type ) : ?>
                        <label class="getcited-site-type">
                            <input type="radio" name="site_type" value="<?php echo esc_attr( $key ); ?>">
                            <div class="site-type-card">
                                <span class="card-check"></span>
                                <span class="dashicons <?php echo esc_attr( $type['icon'] ); ?>"></span>
                                <strong><?php echo esc_html( $type['label'] ); ?></strong>
                                <span class="description"><?php echo esc_html( $type['description'] ); ?></span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="wizard-actions">
                <button type="button" class="button button-primary button-hero getcited-wizard-next">
                    <?php esc_html_e( 'Continue', 'getcited' ); ?> →
                </button>
                <p class="wizard-skip-link">
                    <a href="#" class="getcited-wizard-skip">
                        <?php esc_html_e( 'Skip Setup', 'getcited' ); ?>
                        <span><?php esc_html_e( '(works automatically)', 'getcited' ); ?></span>
                    </a>
                </p>
            </div>
        </div>

        <!-- Step 2: Done -->
        <div class="getcited-wizard-step" data-step="done" style="display: none;">
            <div class="wizard-content wizard-complete-content">
                <!-- Success Badge -->
                <div class="wizard-success-badge">
                    <span class="success-checkmark">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </span>
                </div>

                <h1><?php esc_html_e( "You're all set!", 'getcited' ); ?></h1>
                <p class="wizard-subtitle">
                    <?php esc_html_e( 'Your site is now configured for AI discovery. Here\'s what we set up:', 'getcited' ); ?>
                </p>

                <!-- Status Results (populated by JavaScript) -->
                <div class="wizard-status-results">
                    <div class="wizard-status-item status-llms">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <div class="status-item-content">
                            <strong><?php esc_html_e( 'llms.txt is live', 'getcited' ); ?></strong>
                            <a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" class="wizard-llms-url">
                                <?php echo esc_html( home_url( '/llms.txt' ) ); ?>
                            </a>
                        </div>
                    </div>
                    <div class="wizard-status-item status-crawlers">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <div class="status-item-content">
                            <strong class="wizard-crawler-text"><?php esc_html_e( 'AI crawlers can now find your site', 'getcited' ); ?></strong>
                        </div>
                    </div>
                    <div class="wizard-status-item status-schema">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <div class="status-item-content">
                            <strong class="wizard-schema-text"><?php esc_html_e( 'Schema markup is active', 'getcited' ); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- CTA Section -->
                <div class="wizard-complete-cta">
                    <button type="button" class="button button-primary button-hero getcited-wizard-complete">
                        <?php esc_html_e( 'Go to Dashboard', 'getcited' ); ?>
                    </button>
                    <p class="wizard-skip-link">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=getcited-settings' ) ); ?>">
                            <?php esc_html_e( 'Customize settings', 'getcited' ); ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>

    </div>

</div>
<?php
} )();

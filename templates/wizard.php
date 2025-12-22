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
$settings = GetCited_Settings::instance();
$org = $settings->get( 'organization' );

// Pre-fill organization name
if ( empty( $org['name'] ) ) {
    $org['name'] = get_bloginfo( 'name' );
}
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

        <!-- Step 1: Welcome - Two-Column Hero -->
        <div class="getcited-wizard-step" data-step="welcome">
            <div class="wizard-content">
                <div class="wizard-hero-visual">
                    <div class="wizard-icon">
                        <span class="dashicons dashicons-format-quote"></span>
                    </div>
                </div>
                <div class="wizard-hero-text">
                    <h1><?php esc_html_e( 'Welcome to GetCited', 'getcited' ); ?></h1>
                    <p class="wizard-subtitle">
                        <?php esc_html_e( "Let's make your site visible to AI search engines like ChatGPT, Google Gemini, Grok, Claude, Perplexity, and more.", 'getcited' ); ?>
                    </p>
                    <p>
                        <?php esc_html_e( 'This quick setup takes about 2 minutes.', 'getcited' ); ?>
                    </p>
                </div>
            </div>
            <div class="wizard-actions">
                <button type="button" class="button button-primary button-hero getcited-wizard-next">
                    <?php esc_html_e( 'Get Started', 'getcited' ); ?> →
                </button>
                <p class="wizard-skip-link">
                    <a href="#" class="getcited-wizard-skip">
                        <?php esc_html_e( 'Skip Setup', 'getcited' ); ?>
                        <span><?php esc_html_e( '(configure manually)', 'getcited' ); ?></span>
                    </a>
                </p>
            </div>
        </div>

        <!-- Step 2: Site Type -->
        <div class="getcited-wizard-step" data-step="site_type" style="display: none;">
            <div class="wizard-content">
                <h1><?php esc_html_e( 'What type of site is this?', 'getcited' ); ?></h1>
                <p class="wizard-subtitle">
                    <?php esc_html_e( "We'll set up the best options for your type of website.", 'getcited' ); ?>
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
                <button type="button" class="button getcited-wizard-back">
                    ← <?php esc_html_e( 'Back', 'getcited' ); ?>
                </button>
                <button type="button" class="button button-primary getcited-wizard-next">
                    <?php esc_html_e( 'Continue', 'getcited' ); ?> →
                </button>
            </div>
        </div>

        <!-- Step 3: Organization Info -->
        <div class="getcited-wizard-step" data-step="organization" style="display: none;">
            <div class="wizard-content">
                <h1><?php esc_html_e( "What's your site called?", 'getcited' ); ?></h1>
                <p class="wizard-subtitle">
                    <?php esc_html_e( 'AI assistants will use this name when talking about your website.', 'getcited' ); ?>
                </p>

                <div class="getcited-org-form">
                    <div class="form-field">
                        <label for="wizard_org_name"><?php esc_html_e( 'Your Site or Business Name', 'getcited' ); ?></label>
                        <div class="org-name-input-wrap">
                            <input type="text"
                                   id="wizard_org_name"
                                   name="organization[name]"
                                   value=""
                                   placeholder="<?php esc_attr_e( 'Your Site or Company Name', 'getcited' ); ?>">
                            <span class="org-name-status">
                                <span class="org-scanning">
                                    <span class="spinner is-active"></span>
                                    <?php esc_html_e( 'Finding your site name...', 'getcited' ); ?>
                                </span>
                            </span>
                            <span class="org-auto-detected" style="display: none;">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php esc_html_e( 'Auto-detected from your site', 'getcited' ); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="wizard-actions">
                <button type="button" class="button getcited-wizard-back">
                    ← <?php esc_html_e( 'Back', 'getcited' ); ?>
                </button>
                <button type="button" class="button button-primary getcited-wizard-next">
                    <?php esc_html_e( 'Continue', 'getcited' ); ?> →
                </button>
            </div>
        </div>

        <!-- Step 4: Crawlers -->
        <div class="getcited-wizard-step" data-step="crawlers" style="display: none;">
            <div class="wizard-content">
                <h1><?php esc_html_e( 'Allow AI to visit your site?', 'getcited' ); ?></h1>
                <p class="wizard-subtitle">
                    <?php esc_html_e( 'AI tools like ChatGPT and Perplexity visit websites to learn about them.', 'getcited' ); ?>
                </p>

                <div class="getcited-crawler-choice">
                    <label class="getcited-radio-card selected">
                        <input type="radio" name="crawler_choice" value="allow_all" checked>
                        <div class="radio-card-content">
                            <span class="card-check"></span>
                            <span class="dashicons dashicons-yes-alt"></span>
                            <strong><?php esc_html_e( 'Yes, allow all AI tools', 'getcited' ); ?></strong>
                            <span class="recommended-badge"><?php esc_html_e( 'Recommended', 'getcited' ); ?></span>
                            <p><?php esc_html_e( 'Get found by ChatGPT, Claude, Perplexity, Gemini, and others.', 'getcited' ); ?></p>
                        </div>
                    </label>

                    <label class="getcited-radio-card">
                        <input type="radio" name="crawler_choice" value="customize">
                        <div class="radio-card-content">
                            <span class="card-check"></span>
                            <span class="dashicons dashicons-admin-generic"></span>
                            <strong><?php esc_html_e( 'Let me choose later', 'getcited' ); ?></strong>
                            <p><?php esc_html_e( "I'll pick which AI tools can visit after setup.", 'getcited' ); ?></p>
                        </div>
                    </label>
                </div>
            </div>
            <div class="wizard-actions">
                <button type="button" class="button getcited-wizard-back">
                    ← <?php esc_html_e( 'Back', 'getcited' ); ?>
                </button>
                <button type="button" class="button button-primary getcited-wizard-next">
                    <?php esc_html_e( 'Continue', 'getcited' ); ?> →
                </button>
            </div>
        </div>

        <!-- Step 5: Verify llms.txt -->
        <div class="getcited-wizard-step" data-step="verify" style="display: none;">
            <div class="wizard-content">
                <h1><?php esc_html_e( 'Checking Your Setup', 'getcited' ); ?></h1>
                <p class="wizard-subtitle">
                    <?php esc_html_e( 'Making sure AI assistants can find your site information.', 'getcited' ); ?>
                </p>

                <!-- Verification States -->
                <div class="getcited-verify-container">

                    <!-- Checking State -->
                    <div class="verify-state verify-checking" style="display: none;">
                        <div class="verify-spinner">
                            <span class="spinner is-active"></span>
                        </div>
                        <p><?php esc_html_e( 'Checking your setup...', 'getcited' ); ?></p>
                    </div>

                    <!-- Success State -->
                    <div class="verify-state verify-success" style="display: none;">
                        <div class="verify-icon success">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                        <h2><?php esc_html_e( 'Looking good!', 'getcited' ); ?></h2>
                        <p class="verify-url">
                            <?php esc_html_e( 'Your site info is ready at:', 'getcited' ); ?><br>
                            <a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" class="llms-url-link">
                                <?php echo esc_html( home_url( '/llms.txt' ) ); ?>
                            </a>
                        </p>
                        <p class="verify-message"><?php esc_html_e( 'AI assistants can now find and share your content with users.', 'getcited' ); ?></p>
                    </div>

                    <!-- Using Existing File State -->
                    <div class="verify-state verify-using-existing" style="display: none;">
                        <div class="verify-icon success">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                        <h2><?php esc_html_e( 'Using your custom llms.txt', 'getcited' ); ?></h2>
                        <p class="verify-url">
                            <?php esc_html_e( 'Your existing file is accessible at:', 'getcited' ); ?><br>
                            <a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" class="llms-url-link">
                                <?php echo esc_html( home_url( '/llms.txt' ) ); ?>
                            </a>
                        </p>
                        <p class="verify-message"><?php esc_html_e( 'You can switch to GetCited management anytime in Settings.', 'getcited' ); ?></p>
                    </div>

                    <!-- Needs Fix State -->
                    <div class="verify-state verify-needs-fix" style="display: none;">
                        <div class="verify-action-card">
                            <div class="verify-action-header">
                                <span class="verify-action-icon">
                                    <span class="dashicons dashicons-media-text"></span>
                                </span>
                                <div class="verify-action-text">
                                    <strong><?php esc_html_e( 'Save your llms.txt file', 'getcited' ); ?></strong>
                                    <span><?php esc_html_e( 'This lets AI assistants find and cite your content.', 'getcited' ); ?></span>
                                </div>
                            </div>

                            <!-- Host-specific guidance (shown if detected) -->
                            <div class="verify-host-guidance" style="display: none;">
                                <div class="host-guidance-box">
                                    <strong class="host-title"></strong>
                                    <p class="host-message"></p>
                                    <a href="#" class="host-docs-link" target="_blank"><?php esc_html_e( 'Learn more', 'getcited' ); ?></a>
                                </div>
                            </div>

                            <div class="verify-action-buttons">
                                <button type="button" class="button button-primary getcited-save-file-btn">
                                    <?php esc_html_e( 'Save File', 'getcited' ); ?>
                                </button>
                            </div>

                            <div class="verify-alt-options">
                                <span class="verify-alt-label"><?php esc_html_e( 'or', 'getcited' ); ?></span>
                                <a href="#" class="verify-alt-link" data-action="download"><?php esc_html_e( 'Download manually', 'getcited' ); ?></a>
                                <span class="verify-alt-sep">·</span>
                                <a href="#" class="verify-alt-link" data-action="skip"><?php esc_html_e( 'Skip for now', 'getcited' ); ?></a>
                            </div>
                        </div>
                    </div>

                    <!-- Fixing State -->
                    <div class="verify-state verify-fixing" style="display: none;">
                        <div class="verify-spinner">
                            <span class="spinner is-active"></span>
                        </div>
                        <p class="fixing-message"><?php esc_html_e( 'Saving file...', 'getcited' ); ?></p>
                    </div>

                    <!-- Manual Upload State -->
                    <div class="verify-state verify-manual" style="display: none;">
                        <div class="verify-icon info">
                            <span class="dashicons dashicons-download"></span>
                        </div>
                        <h2><?php esc_html_e( 'Please upload the file', 'getcited' ); ?></h2>
                        <p class="verify-message">
                            <?php esc_html_e( 'Your web host doesn\'t allow us to save files automatically. Please download this file and upload it to your website.', 'getcited' ); ?>
                        </p>
                        <div class="manual-instructions">
                            <ol>
                                <li><?php esc_html_e( 'Click the download button below', 'getcited' ); ?></li>
                                <li><?php esc_html_e( 'Log into your web hosting control panel', 'getcited' ); ?></li>
                                <li><?php esc_html_e( 'Upload the file to your main website folder', 'getcited' ); ?></li>
                            </ol>
                            <a href="#" class="button button-primary getcited-download-llms">
                                <span class="dashicons dashicons-download"></span>
                                <?php esc_html_e( 'Download File', 'getcited' ); ?>
                            </a>
                        </div>
                        <p class="wordpress-com-note" style="display: none;">
                            <strong><?php esc_html_e( 'WordPress.com users:', 'getcited' ); ?></strong>
                            <a href="https://wordpress.com/support/add-llms-txt-to-your-site/" target="_blank">
                                <?php esc_html_e( 'See the official guide', 'getcited' ); ?>
                            </a>
                        </p>
                    </div>

                </div>
            </div>
            <div class="wizard-actions">
                <button type="button" class="button getcited-wizard-back verify-back-btn">
                    ← <?php esc_html_e( 'Back', 'getcited' ); ?>
                </button>
                <button type="button" class="button button-primary getcited-wizard-next verify-continue-btn" style="display: none;">
                    <?php esc_html_e( 'Continue', 'getcited' ); ?> →
                </button>
            </div>
        </div>

        <!-- Step 6: Complete -->
        <div class="getcited-wizard-step" data-step="complete" style="display: none;">
            <div class="wizard-content wizard-complete-content">
                <!-- Success Badge -->
                <div class="wizard-success-badge">
                    <span class="success-checkmark">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </span>
                </div>

                <h1><?php esc_html_e( 'You\'re all set!', 'getcited' ); ?></h1>
                <p class="wizard-subtitle">
                    <?php esc_html_e( 'Your site is configured for AI discovery.', 'getcited' ); ?>
                </p>

                <!-- Summary Card -->
                <div class="wizard-summary-card">
                    <?php
                    $site_name      = get_bloginfo( 'name' );
                    $page_count     = (int) wp_count_posts( 'page' )->publish;
                    $post_count     = (int) wp_count_posts( 'post' )->publish;
                    $category_count = (int) wp_count_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
                    $settings       = GetCited_Settings::instance();
                    $org            = $settings->get( 'organization' );
                    $social_urls    = $org['social_urls'] ?? array();
                    $social_count   = count( array_filter( $social_urls ) );
                    ?>
                    <div class="summary-card-header">
                        <span class="summary-card-title"><?php echo esc_html( $site_name ? $site_name : __( 'Your site', 'getcited' ) ); ?></span>
                    </div>
                    <div class="summary-card-stats">
                        <?php if ( $page_count > 0 ) : ?>
                            <span class="summary-stat-badge"><strong><?php echo esc_html( $page_count ); ?></strong> <?php esc_html_e( 'Pages', 'getcited' ); ?></span>
                        <?php endif; ?>
                        <?php if ( $post_count > 0 ) : ?>
                            <span class="summary-stat-badge"><strong><?php echo esc_html( $post_count ); ?></strong> <?php esc_html_e( 'Posts', 'getcited' ); ?></span>
                        <?php endif; ?>
                        <?php if ( $category_count > 0 ) : ?>
                            <span class="summary-stat-badge"><strong><?php echo esc_html( $category_count ); ?></strong> <?php esc_html_e( 'Categories', 'getcited' ); ?></span>
                        <?php endif; ?>
                        <?php if ( $social_count > 0 ) : ?>
                            <span class="summary-stat-badge"><strong><?php echo esc_html( $social_count ); ?></strong> <?php esc_html_e( 'Social Profiles', 'getcited' ); ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Checklist -->
                    <div class="summary-checklist">
                        <div class="checklist-item">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <span><?php esc_html_e( 'AI crawlers allowed', 'getcited' ); ?></span>
                        </div>
                        <div class="checklist-item">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <span><?php esc_html_e( 'llms.txt file created', 'getcited' ); ?></span>
                        </div>
                        <div class="checklist-item">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <span><?php esc_html_e( 'Ready for AI discovery', 'getcited' ); ?></span>
                        </div>
                    </div>

                    <!-- llms.txt Preview - populated by JavaScript if scan data available -->
                    <details class="wizard-preview-details" style="display: none;">
                        <summary><?php esc_html_e( 'View llms.txt preview', 'getcited' ); ?></summary>
                        <pre class="wizard-preview-code"></pre>
                    </details>
                </div>

                <!-- CTA Section -->
                <div class="wizard-complete-cta">
                    <button type="button" class="button button-primary button-hero getcited-wizard-complete">
                        <?php esc_html_e( 'Go to Dashboard', 'getcited' ); ?>
                    </button>
                </div>
            </div>
        </div>

    </div>

</div>
<?php
} )();

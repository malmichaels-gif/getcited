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
                        <?php esc_html_e( "Let's make your site visible to AI search engines like ChatGPT, Claude, and Perplexity.", 'getcited' ); ?>
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
                <p>
                    <a href="#" class="getcited-wizard-skip">
                        <?php esc_html_e( 'Skip setup (configure manually)', 'getcited' ); ?>
                    </a>
                </p>
            </div>
        </div>

        <!-- Step 2: Site Type -->
        <div class="getcited-wizard-step" data-step="site_type" style="display: none;">
            <div class="wizard-content">
                <h1><?php esc_html_e( 'What type of site is this?', 'getcited' ); ?></h1>
                <p class="wizard-subtitle">
                    <?php esc_html_e( 'This helps us configure optimal llms.txt and schema settings.', 'getcited' ); ?>
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

                <!-- Scan Progress (hidden by default, shown during async scan) -->
                <div class="getcited-scan-progress" style="display: none;">
                    <div class="scan-progress-bar">
                        <div class="scan-progress-fill"></div>
                    </div>
                    <p class="scan-status-text"><?php esc_html_e( 'Scanning your site...', 'getcited' ); ?></p>
                </div>
            </div>
            <div class="wizard-actions">
                <button type="button" class="button getcited-wizard-back">
                    ← <?php esc_html_e( 'Back', 'getcited' ); ?>
                </button>
                <button type="button" class="button button-primary getcited-wizard-next">
                    <?php esc_html_e( 'Continue', 'getcited' ); ?> →
                </button>
                <a href="#" class="getcited-skip-scan" style="display: none;">
                    <?php esc_html_e( 'Skip scan', 'getcited' ); ?>
                </a>
            </div>
        </div>

        <!-- Step 3: Organization Info -->
        <div class="getcited-wizard-step" data-step="organization" style="display: none;">
            <div class="wizard-content">
                <h1><?php esc_html_e( 'Organization Info', 'getcited' ); ?></h1>
                <p class="wizard-subtitle">
                    <?php esc_html_e( 'This information appears in your schema markup to help AI identify your brand.', 'getcited' ); ?>
                </p>
                
                <div class="getcited-org-form">
                    <div class="form-field">
                        <label for="wizard_org_name"><?php esc_html_e( 'Organization Name', 'getcited' ); ?></label>
                        <input type="text"
                               id="wizard_org_name"
                               name="organization[name]"
                               value="<?php echo esc_attr( $org['name'] ); ?>"
                               placeholder="<?php esc_attr_e( 'Your Site or Company Name', 'getcited' ); ?>">
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
                <h1><?php esc_html_e( 'AI Crawler Access', 'getcited' ); ?></h1>
                <p class="wizard-subtitle">
                    <?php esc_html_e( 'AI systems like ChatGPT and Perplexity use crawlers to find and cite content.', 'getcited' ); ?>
                </p>
                
                <div class="getcited-crawler-choice">
                    <label class="getcited-radio-card selected">
                        <input type="radio" name="crawler_choice" value="allow_all" checked>
                        <div class="radio-card-content">
                            <span class="card-check"></span>
                            <span class="dashicons dashicons-yes-alt"></span>
                            <strong><?php esc_html_e( 'Allow all AI crawlers', 'getcited' ); ?></strong>
                            <span class="recommended-badge"><?php esc_html_e( 'Recommended', 'getcited' ); ?></span>
                            <p><?php esc_html_e( 'Maximize visibility to ChatGPT, Claude, Perplexity, and other AI systems.', 'getcited' ); ?></p>
                        </div>
                    </label>

                    <label class="getcited-radio-card">
                        <input type="radio" name="crawler_choice" value="customize">
                        <div class="radio-card-content">
                            <span class="card-check"></span>
                            <span class="dashicons dashicons-admin-generic"></span>
                            <strong><?php esc_html_e( 'Let me customize', 'getcited' ); ?></strong>
                            <p><?php esc_html_e( "I'll choose which crawlers to allow after setup.", 'getcited' ); ?></p>
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
                <h1><?php esc_html_e( 'Verifying llms.txt', 'getcited' ); ?></h1>
                <p class="wizard-subtitle">
                    <?php esc_html_e( 'Making sure AI systems can access your llms.txt file.', 'getcited' ); ?>
                </p>

                <!-- Verification States -->
                <div class="getcited-verify-container">

                    <!-- Checking State -->
                    <div class="verify-state verify-checking" style="display: none;">
                        <div class="verify-spinner">
                            <span class="spinner is-active"></span>
                        </div>
                        <p><?php esc_html_e( 'Checking if AI systems can access your llms.txt...', 'getcited' ); ?></p>
                    </div>

                    <!-- Success State -->
                    <div class="verify-state verify-success" style="display: none;">
                        <div class="verify-icon success">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                        <h2><?php esc_html_e( 'Success!', 'getcited' ); ?></h2>
                        <p class="verify-url">
                            <?php esc_html_e( 'Your llms.txt is live and accessible at:', 'getcited' ); ?><br>
                            <a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" class="llms-url-link">
                                <?php echo esc_html( home_url( '/llms.txt' ) ); ?>
                            </a>
                        </p>
                        <p class="verify-message"><?php esc_html_e( 'AI crawlers can now discover and understand your site.', 'getcited' ); ?></p>
                    </div>

                    <!-- Needs Fix State -->
                    <div class="verify-state verify-needs-fix" style="display: none;">
                        <div class="verify-icon warning">
                            <span class="dashicons dashicons-warning"></span>
                        </div>
                        <h2><?php esc_html_e( "We couldn't access your llms.txt", 'getcited' ); ?></h2>
                        <p class="verify-message">
                            <?php esc_html_e( "Your hosting may not route .txt files through WordPress. Don't worry - we can fix this!", 'getcited' ); ?>
                        </p>

                        <!-- Host-specific guidance (shown if detected) -->
                        <div class="verify-host-guidance" style="display: none;">
                            <div class="host-guidance-box">
                                <strong class="host-title"></strong>
                                <p class="host-message"></p>
                                <a href="#" class="host-docs-link" target="_blank"><?php esc_html_e( 'View documentation', 'getcited' ); ?></a>
                            </div>
                        </div>

                        <!-- Fix Options -->
                        <div class="verify-fix-options">
                            <h3><?php esc_html_e( 'Choose how to proceed:', 'getcited' ); ?></h3>

                            <label class="getcited-radio-card verify-option selected" data-option="write_physical">
                                <input type="radio" name="verify_fix" value="write_physical" checked>
                                <div class="radio-card-content">
                                    <span class="card-check"></span>
                                    <span class="dashicons dashicons-media-text"></span>
                                    <strong><?php esc_html_e( 'Write a physical file', 'getcited' ); ?></strong>
                                    <span class="recommended-badge"><?php esc_html_e( 'Recommended', 'getcited' ); ?></span>
                                    <p><?php esc_html_e( 'Creates an actual llms.txt file on your server.', 'getcited' ); ?></p>
                                </div>
                            </label>

                            <label class="getcited-radio-card verify-option" data-option="download">
                                <input type="radio" name="verify_fix" value="download">
                                <div class="radio-card-content">
                                    <span class="card-check"></span>
                                    <span class="dashicons dashicons-download"></span>
                                    <strong><?php esc_html_e( 'Download and upload manually', 'getcited' ); ?></strong>
                                    <p><?php esc_html_e( 'Download the file and upload via SFTP/cPanel.', 'getcited' ); ?></p>
                                </div>
                            </label>

                            <label class="getcited-radio-card verify-option" data-option="skip">
                                <input type="radio" name="verify_fix" value="skip">
                                <div class="radio-card-content">
                                    <span class="card-check"></span>
                                    <span class="dashicons dashicons-clock"></span>
                                    <strong><?php esc_html_e( 'Skip for now', 'getcited' ); ?></strong>
                                    <p><?php esc_html_e( "I'll fix this later in settings.", 'getcited' ); ?></p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Fixing State -->
                    <div class="verify-state verify-fixing" style="display: none;">
                        <div class="verify-spinner">
                            <span class="spinner is-active"></span>
                        </div>
                        <p class="fixing-message"><?php esc_html_e( 'Writing physical file...', 'getcited' ); ?></p>
                    </div>

                    <!-- Manual Upload State -->
                    <div class="verify-state verify-manual" style="display: none;">
                        <div class="verify-icon info">
                            <span class="dashicons dashicons-download"></span>
                        </div>
                        <h2><?php esc_html_e( 'Manual Upload Required', 'getcited' ); ?></h2>
                        <p class="verify-message">
                            <?php esc_html_e( 'Your hosting does not allow automatic file creation. Please download the file and upload it to your site root via SFTP or cPanel.', 'getcited' ); ?>
                        </p>
                        <div class="manual-instructions">
                            <ol>
                                <li><?php esc_html_e( 'Click the download button below', 'getcited' ); ?></li>
                                <li><?php esc_html_e( 'Connect to your site via SFTP or cPanel File Manager', 'getcited' ); ?></li>
                                <li><?php esc_html_e( 'Upload llms.txt to your site root (same folder as wp-config.php)', 'getcited' ); ?></li>
                            </ol>
                            <a href="#" class="button button-primary getcited-download-llms">
                                <span class="dashicons dashicons-download"></span>
                                <?php esc_html_e( 'Download llms.txt', 'getcited' ); ?>
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
                <button type="button" class="button button-primary getcited-verify-fix-btn" style="display: none;">
                    <?php esc_html_e( 'Try Selected Option', 'getcited' ); ?> →
                </button>
                <button type="button" class="button getcited-verify-skip-btn" style="display: none;">
                    <?php esc_html_e( 'Skip and finish setup', 'getcited' ); ?> →
                </button>
            </div>
        </div>

        <!-- Step 6: Complete -->
        <div class="getcited-wizard-step" data-step="complete" style="display: none;">
            <?php
            $wizard_scan = $wizard->get_scan_data();
            $has_scan    = $wizard_scan && ! empty( $wizard_scan['llms_txt'] );
            $scan_data   = $has_scan ? $wizard_scan['scan_data'] : array();
            ?>
            <div class="wizard-content">
                <div class="wizard-icon success">
                    <span class="dashicons dashicons-yes"></span>
                </div>
                <h1><?php esc_html_e( 'All Set!', 'getcited' ); ?></h1>

                <?php if ( $has_scan ) : ?>
                    <p class="wizard-subtitle">
                        <?php esc_html_e( "We scanned your site and created your llms.txt. Here's what AI systems will see:", 'getcited' ); ?>
                    </p>

                    <div class="getcited-wizard-preview">
                        <div class="preview-header">
                            <span class="dashicons dashicons-media-text"></span>
                            <span><?php esc_html_e( 'Your llms.txt', 'getcited' ); ?></span>
                        </div>
                        <pre class="preview-content"><?php echo esc_html( $wizard_scan['llms_txt'] ); ?></pre>
                    </div>

                    <div class="getcited-scan-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo count( $scan_data['pages'] ?? array() ); ?></span>
                            <span class="stat-label"><?php esc_html_e( 'Pages', 'getcited' ); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo count( $scan_data['categories'] ?? array() ); ?></span>
                            <span class="stat-label"><?php esc_html_e( 'Categories', 'getcited' ); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo count( $scan_data['posts'] ?? array() ); ?></span>
                            <span class="stat-label"><?php esc_html_e( 'Posts', 'getcited' ); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo count( $scan_data['menu'] ?? array() ); ?></span>
                            <span class="stat-label"><?php esc_html_e( 'Menu Items', 'getcited' ); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo count( $scan_data['social'] ?? array() ); ?></span>
                            <span class="stat-label"><?php esc_html_e( 'Social Links', 'getcited' ); ?></span>
                        </div>
                    </div>

                    <p class="description" style="text-align: center;">
                        <?php esc_html_e( 'You can edit this anytime from the llms.txt Editor page.', 'getcited' ); ?>
                    </p>

                <?php else : ?>
                    <p class="wizard-subtitle">
                        <?php esc_html_e( 'Your site is now optimized for AI visibility.', 'getcited' ); ?>
                    </p>

                    <div class="getcited-setup-summary">
                        <div class="summary-item">
                            <span class="dashicons dashicons-yes"></span>
                            <span><?php esc_html_e( 'AI crawlers configured', 'getcited' ); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="dashicons dashicons-yes"></span>
                            <span><?php esc_html_e( 'llms.txt ready to customize', 'getcited' ); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="dashicons dashicons-yes"></span>
                            <span><?php esc_html_e( 'Schema markup enabled', 'getcited' ); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="wizard-actions wizard-actions-stacked">
                <button type="button" class="button button-primary button-hero getcited-wizard-complete">
                    <?php esc_html_e( 'Go to Dashboard', 'getcited' ); ?> →
                </button>
                <?php if ( $has_scan ) : ?>
                    <p class="wizard-secondary-action">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=getcited-llms-txt' ) ); ?>">
                            <?php esc_html_e( 'or edit your llms.txt first', 'getcited' ); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>
<?php
} )();

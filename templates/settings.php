<?php
/**
 * Settings template
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

( function() {
$settings   = GetCited_Settings::instance();
$dashboard  = GetCited_Dashboard::instance();
$pro_teaser = GetCited_Pro_Teaser::instance();

$debug_mode              = $settings->get( 'debug_mode' );
$keep_on_delete          = $settings->get( 'keep_on_delete' );
$site_type               = $settings->get( 'site_type' );
$request_logging_enabled = $settings->get( 'request_logging_enabled' );
$request_log_retention   = $settings->get( 'request_log_retention' );
?>

<div class="wrap getcited-wrap">
    <h1><?php esc_html_e( 'Settings', 'getcited' ); ?></h1>
    <p class="description">
        <?php esc_html_e( 'Configure your site type for tailored recommendations and manage plugin data.', 'getcited' ); ?>
    </p>

    <div class="getcited-settings-page">

        <!-- Pro Teaser Banner -->
        <?php $pro_teaser->render_page_teaser( 'settings' ); ?>

        <!-- Two-column layout for Site Config and Data Management -->
        <div class="getcited-two-column" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--getcited-space-lg); margin-bottom: var(--getcited-space-lg);">

            <!-- Site Configuration -->
            <div class="getcited-section" style="margin-bottom: 0;">
                <h2><?php esc_html_e( 'Site Configuration', 'getcited' ); ?></h2>

                <div class="getcited-compact-form">
                    <div class="form-row form-row-full">
                        <label for="site_type"><?php esc_html_e( 'Site Type', 'getcited' ); ?></label>
                        <select name="site_type" id="site_type" style="width: 100%;">
                            <option value="blog" <?php selected( $site_type, 'blog' ); ?>>
                                <?php esc_html_e( 'Blog', 'getcited' ); ?>
                            </option>
                            <option value="business" <?php selected( $site_type, 'business' ); ?>>
                                <?php esc_html_e( 'Business', 'getcited' ); ?>
                            </option>
                            <option value="news" <?php selected( $site_type, 'news' ); ?>>
                                <?php esc_html_e( 'News / Magazine', 'getcited' ); ?>
                            </option>
                            <option value="ecommerce" <?php selected( $site_type, 'ecommerce' ); ?>>
                                <?php esc_html_e( 'E-commerce', 'getcited' ); ?>
                            </option>
                            <option value="portfolio" <?php selected( $site_type, 'portfolio' ); ?>>
                                <?php esc_html_e( 'Portfolio', 'getcited' ); ?>
                            </option>
                            <option value="nonprofit" <?php selected( $site_type, 'nonprofit' ); ?>>
                                <?php esc_html_e( 'Nonprofit', 'getcited' ); ?>
                            </option>
                            <option value="education" <?php selected( $site_type, 'education' ); ?>>
                                <?php esc_html_e( 'Education / Courses', 'getcited' ); ?>
                            </option>
                            <option value="community" <?php selected( $site_type, 'community' ); ?>>
                                <?php esc_html_e( 'Community / Forum', 'getcited' ); ?>
                            </option>
                            <option value="other" <?php selected( $site_type, 'other' ); ?>>
                                <?php esc_html_e( 'Other', 'getcited' ); ?>
                            </option>
                        </select>
                        <p class="description" style="margin-top: 4px; margin-bottom: 0;">
                            <?php esc_html_e( 'Changing this does not auto-update llms.txt.', 'getcited' ); ?>
                            <a href="#" class="getcited-regenerate-llms">
                                <?php esc_html_e( 'Regenerate', 'getcited' ); ?>
                            </a>
                            <span class="getcited-regenerate-status" style="margin-left: 8px;"></span>
                        </p>
                    </div>
                    <div style="margin-top: 12px;">
                        <label style="display: block; margin-bottom: 4px;"><?php esc_html_e( 'Setup Wizard', 'getcited' ); ?></label>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=getcited&wizard=1' ) ); ?>" class="button button-primary">
                            <span class="dashicons dashicons-admin-tools" style="vertical-align: text-bottom; margin-right: 4px;"></span>
                            <?php esc_html_e( 'Run Setup Wizard Again', 'getcited' ); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Data Management -->
            <div class="getcited-section" style="margin-bottom: 0;">
                <h2><?php esc_html_e( 'Data Management', 'getcited' ); ?></h2>

                <div class="getcited-schema-options-compact">
                    <label class="getcited-checkbox-custom <?php echo $keep_on_delete ? 'is-checked' : ''; ?>">
                        <input type="checkbox"
                               name="keep_on_delete"
                               id="keep_on_delete"
                               value="1"
                               <?php checked( $keep_on_delete ); ?>>
                        <span class="check-box"></span>
                        <span class="check-content">
                            <strong><?php esc_html_e( 'Keep Data on Uninstall', 'getcited' ); ?></strong>
                            <span class="check-description"><?php esc_html_e( 'Preserve settings, AI visibility data, and robots.txt rules when deleting the plugin', 'getcited' ); ?></span>
                        </span>
                    </label>
                </div>

                <p class="description" style="margin-top: var(--getcited-space-lg); margin-bottom: var(--getcited-space-sm);">
                    <?php esc_html_e( 'Backup and restore your configuration.', 'getcited' ); ?>
                </p>
                <div class="getcited-export-import-buttons" style="display: flex; gap: var(--getcited-space-md);">
                    <input type="file"
                           id="getcited-import-file"
                           accept=".json"
                           style="display: none;">
                    <button type="button" class="button button-primary getcited-export-settings">
                        <span class="dashicons dashicons-download" style="vertical-align: text-bottom; margin-right: 4px;"></span>
                        <?php esc_html_e( 'Download JSON', 'getcited' ); ?>
                    </button>
                    <button type="button" class="button button-primary getcited-import-settings">
                        <span class="dashicons dashicons-upload" style="vertical-align: text-bottom; margin-right: 4px;"></span>
                        <?php esc_html_e( 'Upload JSON', 'getcited' ); ?>
                    </button>
                </div>
            </div>

        </div>

        <!-- Logging & Analytics -->
        <div class="getcited-section">
            <h2><?php esc_html_e( 'Logging & Analytics', 'getcited' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Track when AI crawlers and other bots access your AI visibility file.', 'getcited' ); ?>
            </p>
            <p class="description" style="margin-top: 4px;">
                <?php esc_html_e( 'We store only what\'s needed: timestamp, user-agent, and request URL. IP addresses are hashed for privacy. You control retention and can purge logs anytime.', 'getcited' ); ?>
            </p>

            <div class="getcited-schema-options-compact">
                <label class="getcited-checkbox-custom <?php echo $request_logging_enabled ? 'is-checked' : ''; ?>">
                    <input type="checkbox"
                           name="request_logging_enabled"
                           id="request_logging_enabled"
                           value="1"
                           <?php checked( $request_logging_enabled ); ?>>
                    <span class="check-box"></span>
                    <span class="check-content">
                        <strong><?php esc_html_e( 'Enable Request Logging', 'getcited' ); ?></strong>
                        <span class="check-description"><?php esc_html_e( 'Log AI visibility file access', 'getcited' ); ?></span>
                    </span>
                </label>
            </div>

            <div class="getcited-compact-form" style="margin-top: var(--getcited-space-md);">
                <div class="form-row">
                    <label for="request_log_retention"><?php esc_html_e( 'Data Retention', 'getcited' ); ?></label>
                    <select name="request_log_retention" id="request_log_retention">
                        <option value="30" <?php selected( $request_log_retention, 30 ); ?>>
                            <?php esc_html_e( '30 days', 'getcited' ); ?>
                        </option>
                        <option value="60" <?php selected( $request_log_retention, 60 ); ?>>
                            <?php esc_html_e( '60 days', 'getcited' ); ?>
                        </option>
                        <option value="90" <?php selected( $request_log_retention, 90 ); ?>>
                            <?php esc_html_e( '90 days', 'getcited' ); ?>
                        </option>
                        <option value="180" <?php selected( $request_log_retention, 180 ); ?>>
                            <?php esc_html_e( '180 days', 'getcited' ); ?>
                        </option>
                    </select>
                    <p class="description" style="margin-top: 4px;">
                        <?php esc_html_e( 'Older log entries will be automatically deleted.', 'getcited' ); ?>
                    </p>
                </div>

                <div class="form-row" style="margin-top: var(--getcited-space-md);">
                    <label><?php esc_html_e( 'Clear Log', 'getcited' ); ?></label>
                    <div>
                        <button type="button" class="button getcited-clear-request-log" style="padding: 6px 12px;">
                            <span class="dashicons dashicons-trash" style="vertical-align: text-bottom; margin-right: 4px;"></span>
                            <?php esc_html_e( 'Clear All Request Logs', 'getcited' ); ?>
                        </button>
                        <span class="getcited-clear-log-status"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Info (Expanded by default for easy support access) -->
        <div class="getcited-section getcited-collapsible" data-collapsed="false">
            <h2 class="getcited-collapsible-header">
                <?php esc_html_e( 'System Information', 'getcited' ); ?>
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </h2>
            <p class="description" style="margin-top: calc(-1 * var(--getcited-space-sm));">
                <?php esc_html_e( 'Copy this when requesting support.', 'getcited' ); ?>
            </p>
            <div class="getcited-collapsible-content" style="display: block;">
                <textarea id="getcited-system-info"
                          rows="12"
                          readonly
                          class="large-text code"><?php echo esc_textarea( $dashboard->render_system_info() ); ?></textarea>

                <p style="margin-top: var(--getcited-space-sm);">
                    <button type="button" class="button button-primary getcited-copy-system-info">
                        <span class="dashicons dashicons-clipboard" style="vertical-align: text-bottom; margin-right: 4px;"></span>
                        <?php esc_html_e( 'Copy to Clipboard', 'getcited' ); ?>
                    </button>
                </p>
            </div>
        </div>

        <!-- Developer Tools -->
        <div class="getcited-section">
            <h2><?php esc_html_e( 'Developer Tools', 'getcited' ); ?></h2>

            <div class="getcited-schema-options-compact" style="margin-bottom: var(--getcited-space-lg);">
                <label class="getcited-checkbox-custom <?php echo $debug_mode ? 'is-checked' : ''; ?>">
                    <input type="checkbox"
                           name="debug_mode"
                           id="debug_mode"
                           value="1"
                           <?php checked( $debug_mode ); ?>>
                    <span class="check-box"></span>
                    <span class="check-content">
                        <strong><?php esc_html_e( 'Debug Mode', 'getcited' ); ?></strong>
                        <span class="check-description"><?php esc_html_e( 'Log detailed info for troubleshooting', 'getcited' ); ?></span>
                    </span>
                </label>
            </div>

            <div class="getcited-cli-commands">
                <h3 style="margin-top: 0; margin-bottom: var(--getcited-space-sm);"><?php esc_html_e( 'WP-CLI Commands', 'getcited' ); ?></h3>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Command', 'getcited' ); ?></th>
                            <th><?php esc_html_e( 'Description', 'getcited' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>wp getcited status</code></td><td><?php esc_html_e( 'View configuration and health status', 'getcited' ); ?></td></tr>
                        <tr><td><code>wp getcited check</code></td><td><?php esc_html_e( 'Run health checks', 'getcited' ); ?></td></tr>
                        <tr><td><code>wp getcited crawlers</code></td><td><?php esc_html_e( 'List all AI crawlers and status', 'getcited' ); ?></td></tr>
                        <tr><td><code>wp getcited crawler &lt;name&gt; &lt;allow|block&gt;</code></td><td><?php esc_html_e( 'Set crawler access', 'getcited' ); ?></td></tr>
                        <tr><td><code>wp getcited crawler-log</code></td><td><?php esc_html_e( 'View, clear, or export crawler log', 'getcited' ); ?></td></tr>
                        <tr><td><code>wp getcited llms-txt</code></td><td><?php esc_html_e( 'Output llms.txt content', 'getcited' ); ?></td></tr>
                        <tr><td><code>wp getcited robots-txt</code></td><td><?php esc_html_e( 'Output robots.txt rules', 'getcited' ); ?></td></tr>
                        <tr><td><code>wp getcited citability &lt;post_id&gt;</code></td><td><?php esc_html_e( 'Analyze citability of a post', 'getcited' ); ?></td></tr>
                        <tr><td><code>wp getcited audit</code></td><td><?php esc_html_e( 'Run citability audit on recent posts', 'getcited' ); ?></td></tr>
                        <tr><td><code>wp getcited export</code></td><td><?php esc_html_e( 'Export settings to JSON', 'getcited' ); ?></td></tr>
                        <tr><td><code>wp getcited import &lt;file&gt;</code></td><td><?php esc_html_e( 'Import settings from JSON', 'getcited' ); ?></td></tr>
                        <tr><td><code>wp getcited flush</code></td><td><?php esc_html_e( 'Clear caches and flush rewrites', 'getcited' ); ?></td></tr>
                    </tbody>
                </table>

                <p class="description" style="margin-top: var(--getcited-space-md);">
                    <?php
                    printf(
                        /* translators: %s: WP-CLI command example */
                        esc_html__( 'Run %s for detailed help on any command.', 'getcited' ),
                        '<code>wp getcited &lt;command&gt; --help</code>'
                    );
                    ?>
                </p>
            </div>
        </div>

        <!-- Save Button -->
        <div class="getcited-section getcited-actions">
            <button type="button" class="button button-primary getcited-save-settings">
                <?php esc_html_e( 'Save Changes', 'getcited' ); ?>
            </button>
            <span class="getcited-save-status"></span>
        </div>

    </div>
</div>
<?php
} )();

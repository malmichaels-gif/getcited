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

$debug_mode     = $settings->get( 'debug_mode' );
$keep_on_delete = $settings->get( 'keep_on_delete' );
$site_type      = $settings->get( 'site_type' );
?>

<div class="wrap getcited-wrap">
    <h1><?php esc_html_e( 'Settings', 'getcited' ); ?></h1>
    <p class="description">
        <?php esc_html_e( 'Configure general options for GetCited.', 'getcited' ); ?>
    </p>

    <div class="getcited-settings-page">

        <!-- Pro Teaser Banner -->
        <?php $pro_teaser->render_page_teaser( 'settings' ); ?>

        <!-- Two-Column Grid: General + Advanced -->
        <div class="getcited-settings-grid">
            <!-- Left Column: General Settings -->
            <div class="getcited-section">
                <h2><?php esc_html_e( 'General', 'getcited' ); ?></h2>

                <div class="getcited-compact-form">
                    <div class="form-row form-row-full">
                        <label for="site_type"><?php esc_html_e( 'Site Type', 'getcited' ); ?></label>
                        <select name="site_type" id="site_type" style="width: 100%; max-width: 300px;">
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
                        <p class="description" style="margin-top: 4px;">
                            <?php esc_html_e( 'Optimizes llms.txt templates and schema output.', 'getcited' ); ?>
                        </p>
                    </div>
                    <div class="form-row form-row-full" style="margin-top: var(--getcited-space-md);">
                        <label><?php esc_html_e( 'Setup Wizard', 'getcited' ); ?></label>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=getcited&wizard=1' ) ); ?>" class="button">
                            <span class="dashicons dashicons-admin-tools" style="vertical-align: text-bottom; margin-right: 4px;"></span>
                            <?php esc_html_e( 'Run Setup Wizard Again', 'getcited' ); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Column: Advanced Settings -->
            <div class="getcited-section">
                <h2><?php esc_html_e( 'Advanced', 'getcited' ); ?></h2>

                <div class="getcited-schema-options-compact">
                    <label class="getcited-checkbox-compact">
                        <input type="checkbox"
                               name="debug_mode"
                               id="debug_mode"
                               value="1"
                               <?php checked( $debug_mode ); ?>>
                        <span class="checkbox-label">
                            <strong><?php esc_html_e( 'Debug Mode', 'getcited' ); ?></strong>
                            <span class="description"><?php esc_html_e( 'Log detailed info for troubleshooting', 'getcited' ); ?></span>
                        </span>
                    </label>

                    <label class="getcited-checkbox-compact">
                        <input type="checkbox"
                               name="keep_on_delete"
                               id="keep_on_delete"
                               value="1"
                               <?php checked( $keep_on_delete ); ?>>
                        <span class="checkbox-label">
                            <strong><?php esc_html_e( 'Keep Settings on Delete', 'getcited' ); ?></strong>
                            <span class="description"><?php esc_html_e( 'Preserve config when uninstalling', 'getcited' ); ?></span>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Import/Export (Collapsible) -->
        <div class="getcited-section getcited-collapsible" data-collapsed="true">
            <h2 class="getcited-collapsible-header">
                <?php esc_html_e( 'Import / Export', 'getcited' ); ?>
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </h2>
            <div class="getcited-collapsible-content" style="display: none;">
                <div class="getcited-compact-form">
                    <div class="form-row">
                        <label><?php esc_html_e( 'Export Settings', 'getcited' ); ?></label>
                        <button type="button" class="button getcited-export-settings">
                            <span class="dashicons dashicons-download" style="vertical-align: text-bottom; margin-right: 4px;"></span>
                            <?php esc_html_e( 'Download JSON', 'getcited' ); ?>
                        </button>
                    </div>
                    <div class="form-row">
                        <label><?php esc_html_e( 'Import Settings', 'getcited' ); ?></label>
                        <input type="file"
                               id="getcited-import-file"
                               accept=".json"
                               style="display: none;">
                        <button type="button" class="button getcited-import-settings">
                            <span class="dashicons dashicons-upload" style="vertical-align: text-bottom; margin-right: 4px;"></span>
                            <?php esc_html_e( 'Upload JSON', 'getcited' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Info (Collapsible) -->
        <div class="getcited-section getcited-collapsible" data-collapsed="true">
            <h2 class="getcited-collapsible-header">
                <?php esc_html_e( 'System Information', 'getcited' ); ?>
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </h2>
            <div class="getcited-collapsible-content" style="display: none;">
                <p class="description">
                    <?php esc_html_e( 'Copy this information when requesting support.', 'getcited' ); ?>
                </p>

                <textarea id="getcited-system-info"
                          rows="12"
                          readonly
                          class="large-text code"><?php echo esc_textarea( $dashboard->render_system_info() ); ?></textarea>

                <p style="margin-top: var(--getcited-space-sm);">
                    <button type="button" class="button getcited-copy-system-info">
                        <span class="dashicons dashicons-clipboard" style="vertical-align: text-bottom; margin-right: 4px;"></span>
                        <?php esc_html_e( 'Copy to Clipboard', 'getcited' ); ?>
                    </button>
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

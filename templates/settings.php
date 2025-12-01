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
$settings = GetCited_Settings::instance();
$dashboard = GetCited_Dashboard::instance();

$debug_mode = $settings->get( 'debug_mode' );
$keep_on_delete = $settings->get( 'keep_on_delete' );
$site_type = $settings->get( 'site_type' );
$wizard_completed = $settings->get( 'wizard_completed' );
?>

<div class="wrap getcited-wrap">
    <h1><?php esc_html_e( 'Settings', 'getcited' ); ?></h1>

    <div class="getcited-settings-page">
        
        <!-- General Settings -->
        <div class="getcited-section">
            <h2><?php esc_html_e( 'General', 'getcited' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="site_type"><?php esc_html_e( 'Site Type', 'getcited' ); ?></label>
                    </th>
                    <td>
                        <select name="site_type" id="site_type">
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
                        <p class="description">
                            <?php esc_html_e( 'This helps optimize llms.txt templates and schema output.', 'getcited' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Setup Wizard', 'getcited' ); ?>
                    </th>
                    <td>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=getcited&wizard=1' ) ); ?>" class="button">
                            <?php esc_html_e( 'Run Setup Wizard Again', 'getcited' ); ?>
                        </a>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Advanced Settings -->
        <div class="getcited-section">
            <h2><?php esc_html_e( 'Advanced', 'getcited' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="debug_mode"><?php esc_html_e( 'Debug Mode', 'getcited' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="debug_mode" 
                                   id="debug_mode"
                                   value="1"
                                   <?php checked( $debug_mode ); ?>>
                            <?php esc_html_e( 'Enable debug mode', 'getcited' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Logs detailed information for troubleshooting. Only enable when needed.', 'getcited' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="keep_on_delete"><?php esc_html_e( 'Keep Settings', 'getcited' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="keep_on_delete" 
                                   id="keep_on_delete"
                                   value="1"
                                   <?php checked( $keep_on_delete ); ?>>
                            <?php esc_html_e( 'Keep settings if plugin is deleted', 'getcited' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Preserve your configuration when uninstalling. Useful if you plan to reinstall.', 'getcited' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Import/Export -->
        <div class="getcited-section">
            <h2><?php esc_html_e( 'Import / Export', 'getcited' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Export Settings', 'getcited' ); ?>
                    </th>
                    <td>
                        <button type="button" class="button getcited-export-settings">
                            <?php esc_html_e( 'Download Settings (JSON)', 'getcited' ); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e( 'Download your current settings as a JSON file.', 'getcited' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Import Settings', 'getcited' ); ?>
                    </th>
                    <td>
                        <input type="file" 
                               id="getcited-import-file" 
                               accept=".json"
                               style="display: none;">
                        <button type="button" class="button getcited-import-settings">
                            <?php esc_html_e( 'Import Settings (JSON)', 'getcited' ); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e( 'Upload a previously exported settings file.', 'getcited' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- System Info -->
        <div class="getcited-section">
            <h2><?php esc_html_e( 'System Information', 'getcited' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Copy this information when requesting support.', 'getcited' ); ?>
            </p>
            
            <textarea id="getcited-system-info" 
                      rows="15" 
                      readonly 
                      class="large-text code"><?php echo esc_textarea( $dashboard->render_system_info() ); ?></textarea>
            
            <p>
                <button type="button" class="button getcited-copy-system-info">
                    <?php esc_html_e( 'Copy to Clipboard', 'getcited' ); ?>
                </button>
            </p>
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

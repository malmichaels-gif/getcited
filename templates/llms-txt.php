<?php
/**
 * llms.txt editor template
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

( function() {
$settings = GetCited_Settings::instance();
$llms_txt = GetCited_Llms_Txt::instance();
$health = GetCited_Health_Check::instance();

$enabled = $settings->get( 'llms_txt_enabled' );
$content = $settings->get( 'llms_txt_content' );
$site_type = $settings->get( 'site_type' );
$write_physical = $settings->get( 'llms_write_physical' );
$founder_name = $settings->get( 'llms_founder_name' );
$founder_title = $settings->get( 'llms_founder_title' );
$site_expertise = $settings->get( 'llms_site_expertise' );
$update_frequency = $settings->get( 'llms_update_frequency' );
$citation_format = $settings->get( 'llms_citation_format' );

$physical_exists = $llms_txt->physical_file_exists();
$is_our_file = $physical_exists ? $llms_txt->is_our_physical_file() : false;
$can_write = $llms_txt->can_write_physical_file();

$validation = $llms_txt->validate( $content );
$status = $health->get_status();
?>

<div class="wrap getcited-wrap">
    <h1><?php esc_html_e( 'llms.txt Editor', 'getcited' ); ?></h1>
    <p class="description">
        <?php esc_html_e( 'The llms.txt file helps AI systems understand your site. Think of it as a welcome note for AI visitors.', 'getcited' ); ?>
    </p>

    <div class="getcited-llms-page">
        
        <!-- Enable/Disable -->
        <div class="getcited-section getcited-llms-toggle">
            <label class="getcited-toggle-label">
                <input type="checkbox"
                       name="llms_txt_enabled"
                       id="llms_txt_enabled"
                       value="1"
                       <?php checked( $enabled ); ?>>
                <strong><?php esc_html_e( 'Enable llms.txt', 'getcited' ); ?></strong>
            </label>
            <p class="description">
                <?php
                printf(
                    /* translators: %s: URL to the llms.txt file */
                    esc_html__( 'When enabled, your llms.txt will be available at %s', 'getcited' ),
                    '<a href="' . esc_url( $llms_txt->get_url() ) . '" target="_blank">' . esc_html( $llms_txt->get_url() ) . '</a>'
                ); ?>
            </p>
        </div>

        <!-- Physical File Writing -->
        <div class="getcited-section getcited-llms-file-settings">
            <h2><?php esc_html_e( 'File Settings', 'getcited' ); ?></h2>

            <label class="getcited-toggle-label">
                <input type="checkbox"
                       name="llms_write_physical"
                       id="llms_write_physical"
                       value="1"
                       <?php checked( $write_physical ); ?>
                       <?php disabled( ! $can_write ); ?>>
                <strong><?php esc_html_e( 'Write physical file', 'getcited' ); ?></strong>
            </label>
            <p class="description">
                <?php esc_html_e( 'Automatically write llms.txt to your site root. Recommended for best compatibility.', 'getcited' ); ?>
            </p>

            <?php if ( ! $can_write ) : ?>
                <p class="getcited-notice getcited-notice-warning">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e( 'Cannot write to site root. Check file permissions or contact your host.', 'getcited' ); ?>
                </p>
            <?php endif; ?>

            <?php if ( $physical_exists ) : ?>
                <p class="getcited-file-status">
                    <?php if ( $is_our_file ) : ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php esc_html_e( 'Physical file exists (managed by GetCited)', 'getcited' ); ?>
                    <?php else : ?>
                        <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
                        <?php esc_html_e( 'Physical file exists (not managed by GetCited)', 'getcited' ); ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if ( ! $write_physical || ! $can_write ) : ?>
                <div class="getcited-manual-write">
                    <button type="button" class="button getcited-write-llms-file" <?php disabled( ! $can_write ); ?>>
                        <span class="dashicons dashicons-media-text"></span>
                        <?php esc_html_e( 'Write File Now', 'getcited' ); ?>
                    </button>
                    <span class="getcited-write-status"></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Advanced llms.txt Settings -->
        <div class="getcited-section getcited-llms-advanced">
            <h2><?php esc_html_e( 'Content Settings', 'getcited' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'These fields enhance your llms.txt with additional context. They are included when you scan your site.', 'getcited' ); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="llms_founder_name"><?php esc_html_e( 'Founder/Author Name', 'getcited' ); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               name="llms_founder_name"
                               id="llms_founder_name"
                               class="regular-text"
                               value="<?php echo esc_attr( $founder_name ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g., John Smith', 'getcited' ); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="llms_founder_title"><?php esc_html_e( 'Title/Role', 'getcited' ); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               name="llms_founder_title"
                               id="llms_founder_title"
                               class="regular-text"
                               value="<?php echo esc_attr( $founder_title ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g., Founder & Lead Analyst', 'getcited' ); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="llms_site_expertise"><?php esc_html_e( 'Expertise', 'getcited' ); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               name="llms_site_expertise"
                               id="llms_site_expertise"
                               class="regular-text"
                               value="<?php echo esc_attr( $site_expertise ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g., 10+ years in web development', 'getcited' ); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="llms_update_frequency"><?php esc_html_e( 'Update Frequency', 'getcited' ); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               name="llms_update_frequency"
                               id="llms_update_frequency"
                               class="regular-text"
                               value="<?php echo esc_attr( $update_frequency ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g., 2-3 times per week', 'getcited' ); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="llms_citation_format"><?php esc_html_e( 'Custom Citation Format', 'getcited' ); ?></label>
                    </th>
                    <td>
                        <textarea name="llms_citation_format"
                                  id="llms_citation_format"
                                  class="large-text"
                                  rows="4"
                                  placeholder="<?php esc_attr_e( 'Leave blank to use default citation guidelines based on your site type. Use {site_name} as a placeholder.', 'getcited' ); ?>"><?php echo esc_textarea( $citation_format ); ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'Custom instructions for how AI should cite your content. Leave blank for automatic guidelines.', 'getcited' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Status -->
        <?php if ( isset( $status['llms_txt'] ) ) : 
            $llms_status = $status['llms_txt'];
            $status_class = $health->get_status_class( $llms_status['status'] );
        ?>
            <div class="getcited-section getcited-llms-status <?php echo esc_attr( $status_class ); ?>">
                <span class="status-icon"></span>
                <span class="status-message"><?php echo esc_html( $llms_status['message'] ); ?></span>
                <?php if ( $llms_status['status'] === 'error' ) : ?>
                    <p class="status-help">
                        <?php esc_html_e( 'Fallback URL:', 'getcited' ); ?>
                        <code><?php echo esc_html( $llms_txt->get_fallback_url() ); ?></code>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Editor -->
        <div class="getcited-section getcited-llms-editor-section">
            <div class="getcited-editor-header">
                <h2><?php esc_html_e( 'Content', 'getcited' ); ?></h2>
                <div class="getcited-llms-actions">
                    <div class="getcited-template-buttons">
                        <span><?php esc_html_e( 'Load template:', 'getcited' ); ?></span>
                        <button type="button" class="button getcited-load-template" data-type="blog">
                            <?php esc_html_e( 'Blog', 'getcited' ); ?>
                        </button>
                        <button type="button" class="button getcited-load-template" data-type="business">
                            <?php esc_html_e( 'Business', 'getcited' ); ?>
                        </button>
                        <button type="button" class="button getcited-load-template" data-type="news">
                            <?php esc_html_e( 'News', 'getcited' ); ?>
                        </button>
                        <button type="button" class="button getcited-load-template" data-type="ecommerce">
                            <?php esc_html_e( 'E-commerce', 'getcited' ); ?>
                        </button>
                    </div>
                    <div class="getcited-scan-section">
                        <button type="button" class="button button-primary getcited-scan-site">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e( 'Scan My Site', 'getcited' ); ?>
                        </button>
                        <span class="getcited-scan-status"></span>
                    </div>
                </div>
            </div>
            <p class="description getcited-scan-description">
                <?php esc_html_e( 'Scan your site to auto-generate llms.txt content based on your actual pages, posts, categories, and more.', 'getcited' ); ?>
            </p>

            <div class="getcited-editor-wrapper">
                <div class="getcited-editor">
                    <textarea name="llms_txt_content" 
                              id="llms_txt_content" 
                              rows="25"
                              placeholder="<?php esc_attr_e( '# Your Site Name', 'getcited' ); ?>"><?php echo esc_textarea( $content ); ?></textarea>
                </div>

                <div class="getcited-preview">
                    <div class="getcited-preview-header">
                        <h3><?php esc_html_e( 'Preview', 'getcited' ); ?></h3>
                        <button type="button" class="button getcited-copy-content" data-target="llms_txt_preview">
                            <span class="dashicons dashicons-clipboard"></span>
                            <?php esc_html_e( 'Copy', 'getcited' ); ?>
                        </button>
                    </div>
                    <pre class="getcited-preview-code" id="llms_txt_preview"><?php echo esc_html( $content ); ?></pre>
                    <p class="description getcited-copy-hint">
                        <?php esc_html_e( 'Copy and paste into llms.txt in your site root if auto-write is unavailable.', 'getcited' ); ?>
                    </p>
                </div>
            </div>

            <!-- Validation -->
            <?php if ( ! empty( $validation['errors'] ) || ! empty( $validation['warnings'] ) ) : ?>
                <div class="getcited-validation">
                    <?php foreach ( $validation['errors'] as $error ) : ?>
                        <p class="getcited-validation-error">
                            <span class="dashicons dashicons-warning"></span>
                            <?php echo esc_html( $error ); ?>
                        </p>
                    <?php endforeach; ?>
                    <?php foreach ( $validation['warnings'] as $warning ) : ?>
                        <p class="getcited-validation-warning">
                            <span class="dashicons dashicons-info"></span>
                            <?php echo esc_html( $warning ); ?>
                        </p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Save Button -->
        <div class="getcited-section getcited-actions">
            <button type="button" class="button button-primary getcited-save-llms-txt">
                <?php esc_html_e( 'Save Changes', 'getcited' ); ?>
            </button>
            <span class="getcited-save-status"></span>
        </div>

        <!-- Help -->
        <div class="getcited-section getcited-help">
            <h2><?php esc_html_e( 'llms.txt Format Guide', 'getcited' ); ?></h2>
            <div class="getcited-help-content">
                <p><?php esc_html_e( 'llms.txt uses Markdown formatting:', 'getcited' ); ?></p>
                <ul>
                    <li><code># Heading</code> — <?php esc_html_e( 'Main heading (your site name)', 'getcited' ); ?></li>
                    <li><code>> Quote</code> — <?php esc_html_e( 'Blockquote (site description)', 'getcited' ); ?></li>
                    <li><code>## Section</code> — <?php esc_html_e( 'Section heading', 'getcited' ); ?></li>
                    <li><code>- Item</code> — <?php esc_html_e( 'Bullet point', 'getcited' ); ?></li>
                    <li><code>[Link](url)</code> — <?php esc_html_e( 'Hyperlink', 'getcited' ); ?></li>
                </ul>
                <p>
                    <a href="https://llmstxt.org/" target="_blank">
                        <?php esc_html_e( 'Learn more about the llms.txt standard →', 'getcited' ); ?>
                    </a>
                </p>
            </div>
        </div>

    </div>
</div>
<?php
} )();

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
                    <h3><?php esc_html_e( 'Preview', 'getcited' ); ?></h3>
                    <pre class="getcited-preview-code" id="llms_txt_preview"><?php echo esc_html( $content ); ?></pre>
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

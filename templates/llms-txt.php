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
$founder_name = $settings->get( 'llms_founder_name' );
$founder_title = $settings->get( 'llms_founder_title' );
$site_expertise = $settings->get( 'llms_site_expertise' );
$update_frequency = $settings->get( 'llms_update_frequency' );
$citation_format = $settings->get( 'llms_citation_format' );
$citation_guidelines = $settings->get( 'citation_guidelines' );
$llms_use_cases = $settings->get( 'llms_use_cases' );

$validation = $llms_txt->validate( $content );
$status = $health->get_status();
?>

<div class="wrap getcited-wrap">
    <h1><?php esc_html_e( 'AI Visibility Data', 'getcited' ); ?></h1>
    <p class="description">
        <?php esc_html_e( 'Your AI visibility file (llms.txt) helps AI systems understand your site. Think of it as a welcome note for AI visitors.', 'getcited' ); ?>
    </p>

    <div class="getcited-llms-page">


        <!-- Enable AI Visibility -->
        <div class="getcited-section getcited-llms-toggle">
            <h2><?php esc_html_e( 'Enable AI Visibility', 'getcited' ); ?></h2>
            <label class="getcited-checkbox-custom <?php echo $enabled ? 'is-checked' : ''; ?>">
                <input type="checkbox"
                       name="llms_txt_enabled"
                       id="llms_txt_enabled"
                       value="1"
                       <?php checked( $enabled ); ?>>
                <span class="check-box"></span>
                <span class="check-content">
                    <strong><?php esc_html_e( 'Serve AI visibility file', 'getcited' ); ?></strong>
                    <span class="check-description">
                        <?php
                        // Cache-busting URL for verification (v1.9.9.25) - display shows clean URL, link bypasses CDN cache
                        printf(
                            /* translators: %s: URL to the llms.txt file */
                            esc_html__( 'Available at %s', 'getcited' ),
                            '<a href="' . esc_url( $llms_txt->get_url() . '?v=' . time() ) . '" target="_blank">' . esc_html( $llms_txt->get_url() ) . '</a>'
                        ); ?>
                    </span>
                </span>
            </label>
            <p class="description" style="margin-top: var(--getcited-space-sm); font-size: 14px;">
                <?php esc_html_e( 'Your AI visibility file is a helpful signal; crawler behavior varies by provider and may change over time.', 'getcited' ); ?>
            </p>
        </div>

        <!-- Content Settings (Collapsible) -->
        <div class="getcited-section getcited-llms-advanced getcited-collapsible" data-collapsed="false">
            <h2 class="getcited-collapsible-header">
                <?php esc_html_e( 'Content Settings', 'getcited' ); ?>
                <span class="dashicons dashicons-arrow-up-alt2"></span>
            </h2>
            <div class="getcited-collapsible-content">
                <p class="description">
                    <?php esc_html_e( 'These fields enhance your AI visibility data with additional context. Included when you scan your site.', 'getcited' ); ?>
                </p>

                <div class="getcited-compact-form">
                    <div class="form-row">
                        <label for="llms_founder_name">
                            <?php esc_html_e( 'Author Name', 'getcited' ); ?>
                            <?php if ( ! empty( $founder_name ) ) : ?>
                                <span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
                            <?php endif; ?>
                        </label>
                        <input type="text" name="llms_founder_name" id="llms_founder_name"
                               value="<?php echo esc_attr( $founder_name ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g., John Smith', 'getcited' ); ?>">
                    </div>
                    <div class="form-row">
                        <label for="llms_founder_title">
                            <?php esc_html_e( 'Title/Role', 'getcited' ); ?>
                            <?php if ( ! empty( $founder_title ) ) : ?>
                                <span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
                            <?php endif; ?>
                        </label>
                        <input type="text" name="llms_founder_title" id="llms_founder_title"
                               value="<?php echo esc_attr( $founder_title ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g., Founder & Lead Analyst', 'getcited' ); ?>">
                    </div>
                    <div class="form-row">
                        <label for="llms_site_expertise">
                            <?php esc_html_e( 'Expertise', 'getcited' ); ?>
                            <?php if ( ! empty( $site_expertise ) ) : ?>
                                <span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
                            <?php endif; ?>
                        </label>
                        <input type="text" name="llms_site_expertise" id="llms_site_expertise"
                               value="<?php echo esc_attr( $site_expertise ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g., 10+ years in web development', 'getcited' ); ?>">
                    </div>
                    <div class="form-row">
                        <label for="llms_update_frequency">
                            <?php esc_html_e( 'Update Frequency', 'getcited' ); ?>
                            <?php if ( ! empty( $update_frequency ) ) : ?>
                                <span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
                            <?php endif; ?>
                        </label>
                        <input type="text" name="llms_update_frequency" id="llms_update_frequency"
                               value="<?php echo esc_attr( $update_frequency ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g., 2-3 times per week', 'getcited' ); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Citation Guidelines (v1.8.0: Now visible by default - important for AI) -->
        <div class="getcited-section getcited-citation-guidelines">
            <h2><?php esc_html_e( 'AI Citation Guidelines', 'getcited' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Tell AI systems how to cite and use your content. These instructions are included in your AI visibility file.', 'getcited' ); ?>
            </p>

            <div class="getcited-field-group">
                <label class="getcited-checkbox-custom <?php echo ! empty( $citation_guidelines['enabled'] ) ? 'is-checked' : ''; ?>">
                    <input type="checkbox"
                           id="getcited-citation-enabled"
                           name="citation_guidelines[enabled]"
                           value="1"
                           <?php checked( ! empty( $citation_guidelines['enabled'] ) ); ?>>
                    <span class="check-box"></span>
                    <span class="check-content">
                        <strong><?php esc_html_e( 'Include citation guidelines', 'getcited' ); ?></strong>
                        <span class="check-description"><?php esc_html_e( 'Tell AI systems how to cite your content.', 'getcited' ); ?></span>
                    </span>
                </label>
            </div>

            <div class="getcited-citation-fields getcited-compact-form" style="margin-top: var(--getcited-space-md, 16px);">
                <div class="form-row form-row-full">
                    <label for="getcited-citation-format">
                        <?php esc_html_e( 'Preferred Citation Format', 'getcited' ); ?>
                        <?php if ( ! empty( $citation_guidelines['citation_format'] ) ) : ?>
                            <span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
                        <?php endif; ?>
                    </label>
                    <input type="text"
                           id="getcited-citation-format"
                           name="citation_guidelines[citation_format]"
                           class="large-text"
                           value="<?php echo esc_attr( $citation_guidelines['citation_format'] ?? '' ); ?>"
                           placeholder="<?php esc_attr_e( 'Author. "Title." Site Name, Date. URL', 'getcited' ); ?>">
                    <p class="description"><?php esc_html_e( 'How AI should format citations to your content.', 'getcited' ); ?></p>
                </div>

                <div class="form-row form-row-full">
                    <label for="getcited-accuracy-notes">
                        <?php esc_html_e( 'Accuracy Notes', 'getcited' ); ?>
                        <?php if ( ! empty( $citation_guidelines['accuracy_notes'] ) ) : ?>
                            <span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
                        <?php endif; ?>
                    </label>
                    <textarea id="getcited-accuracy-notes"
                              name="citation_guidelines[accuracy_notes]"
                              rows="2"
                              class="large-text"
                              placeholder="<?php esc_attr_e( 'Pricing updated quarterly. Technical specs may change.', 'getcited' ); ?>"><?php echo esc_textarea( $citation_guidelines['accuracy_notes'] ?? '' ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Caveats about content freshness or accuracy.', 'getcited' ); ?></p>
                </div>

                <div class="form-row form-row-full">
                    <label for="getcited-restrictions">
                        <?php esc_html_e( 'Usage Restrictions', 'getcited' ); ?>
                        <?php if ( ! empty( $citation_guidelines['restrictions'] ) ) : ?>
                            <span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
                        <?php endif; ?>
                    </label>
                    <textarea id="getcited-restrictions"
                              name="citation_guidelines[restrictions]"
                              rows="2"
                              class="large-text"
                              placeholder="<?php esc_attr_e( 'Do not reproduce full articles. Link to source for code samples.', 'getcited' ); ?>"><?php echo esc_textarea( $citation_guidelines['restrictions'] ?? '' ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'What AI should NOT do with your content.', 'getcited' ); ?></p>
                </div>

                <div class="form-row form-row-full">
                    <label for="getcited-freshness-note">
                        <?php esc_html_e( 'Data Freshness', 'getcited' ); ?>
                        <?php if ( ! empty( $citation_guidelines['freshness_note'] ) ) : ?>
                            <span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
                        <?php endif; ?>
                    </label>
                    <input type="text"
                           id="getcited-freshness-note"
                           name="citation_guidelines[freshness_note]"
                           class="large-text"
                           value="<?php echo esc_attr( $citation_guidelines['freshness_note'] ?? '' ); ?>"
                           placeholder="<?php esc_attr_e( 'Content reviewed monthly.', 'getcited' ); ?>">
                    <p class="description"><?php esc_html_e( 'How often content is updated or reviewed.', 'getcited' ); ?></p>
                </div>

                <div class="form-row">
                    <label for="getcited-contact-email">
                        <?php esc_html_e( 'AI Partnership Contact', 'getcited' ); ?>
                        <?php if ( ! empty( $citation_guidelines['contact_email'] ) ) : ?>
                            <span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
                        <?php endif; ?>
                    </label>
                    <input type="email"
                           id="getcited-contact-email"
                           name="citation_guidelines[contact_email]"
                           class="regular-text"
                           value="<?php echo esc_attr( $citation_guidelines['contact_email'] ?? '' ); ?>"
                           placeholder="<?php esc_attr_e( 'ai-partnerships@example.com', 'getcited' ); ?>">
                    <p class="description"><?php esc_html_e( 'Email for AI training data or licensing inquiries.', 'getcited' ); ?></p>
                </div>
            </div>
        </div>

        <!-- Use Cases for AI (v1.9.9.12) -->
        <div class="getcited-section getcited-use-cases">
            <h2><?php esc_html_e( 'Use Cases for AI', 'getcited' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Tell AI systems what questions your site can help answer. This section guides AI assistants on when to cite your content.', 'getcited' ); ?>
            </p>

            <div class="getcited-compact-form">
                <div class="form-row form-row-full">
                    <label for="getcited-use-cases">
                        <?php esc_html_e( 'Use Cases Content', 'getcited' ); ?>
                        <?php if ( ! empty( $llms_use_cases ) ) : ?>
                            <span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
                        <?php endif; ?>
                    </label>
                    <textarea id="getcited-use-cases"
                              name="llms_use_cases"
                              rows="6"
                              class="large-text"
                              placeholder="<?php esc_attr_e( "This site can help answer questions about:\n- Topic 1\n- Topic 2\n- Topic 3", 'getcited' ); ?>"><?php echo esc_textarea( $llms_use_cases ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Describe what kinds of questions or topics your site is an authority on. If empty, this section will be omitted.', 'getcited' ); ?></p>
                </div>
            </div>
        </div>

        <!-- Status (only show if there's an issue) -->
        <?php if ( isset( $status['llms_txt'] ) && $status['llms_txt']['status'] !== 'ok' ) :
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
                <?php esc_html_e( 'Scan your site to auto-generate AI visibility content based on your actual pages, posts, categories, and more.', 'getcited' ); ?>
            </p>

            <div class="getcited-editor-wrapper">
                <div class="getcited-editor">
                    <div class="getcited-editor-label">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e( 'Edit', 'getcited' ); ?>
                    </div>
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
                    <pre class="getcited-preview-code" id="llms_txt_preview"><?php echo esc_html( html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ); ?></pre>
                    <p class="description getcited-copy-hint">
                        <?php esc_html_e( 'Copy the content above to share or backup your AI visibility configuration.', 'getcited' ); ?>
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

        <!-- Help -->
        <div class="getcited-section getcited-help">
            <h2><?php esc_html_e( 'Format Guide (llms.txt)', 'getcited' ); ?></h2>
            <div class="getcited-help-content">
                <p><?php esc_html_e( 'The file uses Markdown formatting:', 'getcited' ); ?></p>
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

        <!-- Save Button -->
        <div class="getcited-section getcited-actions">
            <button type="button" class="button button-primary getcited-save-llms-txt">
                <?php esc_html_e( 'Save Changes', 'getcited' ); ?>
            </button>
            <span class="getcited-save-status"></span>
        </div>

    </div>
</div>
<?php
} )();

<?php
/**
 * Schema settings template
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

( function() {
$settings = GetCited_Settings::instance();
$schema = GetCited_Schema::instance();

$enabled = $settings->get( 'schema_enabled' );
$schema_types = $settings->get( 'schema_types' );
$organization = $settings->get( 'organization' );
$detected_plugins = $schema->get_detected_plugins();
?>

<div class="wrap getcited-wrap">
    <h1><?php esc_html_e( 'Schema Settings', 'getcited' ); ?></h1>
    <p class="description">
        <?php esc_html_e( 'Schema markup helps AI systems understand your content structure. JSON-LD is output in your page headers.', 'getcited' ); ?>
    </p>

    <div class="getcited-schema-page">
        
        <!-- Conflict Warning -->
        <?php if ( ! empty( $detected_plugins ) ) : ?>
            <div class="getcited-section getcited-warning">
                <p>
                    <span class="dashicons dashicons-warning"></span>
                    <?php
                    /* translators: %s: comma-separated list of detected schema plugin names */
                    printf(
                        esc_html__( 'Detected schema plugins: %s. Enabling GetCited schema may cause duplicate output.', 'getcited' ),
                        '<strong>' . esc_html( implode( ', ', array_column( $detected_plugins, 'name' ) ) ) . '</strong>'
                    ); ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Enable/Disable -->
        <div class="getcited-section">
            <label class="getcited-toggle-label">
                <input type="checkbox" 
                       name="schema_enabled" 
                       id="schema_enabled"
                       value="1"
                       <?php checked( $enabled ); ?>>
                <strong><?php esc_html_e( 'Enable Schema Output', 'getcited' ); ?></strong>
            </label>
            <p class="description">
                <?php esc_html_e( 'Output JSON-LD structured data in page headers.', 'getcited' ); ?>
            </p>
        </div>

        <!-- Schema Types -->
        <div class="getcited-section getcited-schema-types">
            <h2><?php esc_html_e( 'Schema Types', 'getcited' ); ?></h2>
            
            <div class="getcited-schema-options">
                <label class="getcited-checkbox">
                    <input type="checkbox" 
                           name="schema_types[organization]" 
                           value="1"
                           <?php checked( $schema_types['organization'] ); ?>>
                    <strong><?php esc_html_e( 'Organization', 'getcited' ); ?></strong>
                    <span class="description"><?php esc_html_e( 'Your site/company identity. Appears on front page.', 'getcited' ); ?></span>
                </label>

                <label class="getcited-checkbox">
                    <input type="checkbox" 
                           name="schema_types[article]" 
                           value="1"
                           <?php checked( $schema_types['article'] ); ?>>
                    <strong><?php esc_html_e( 'Article', 'getcited' ); ?></strong>
                    <span class="description"><?php esc_html_e( 'Blog posts and articles. Includes headline, date, author.', 'getcited' ); ?></span>
                </label>

                <label class="getcited-checkbox">
                    <input type="checkbox" 
                           name="schema_types[author]" 
                           value="1"
                           <?php checked( $schema_types['author'] ); ?>>
                    <strong><?php esc_html_e( 'Author (Person)', 'getcited' ); ?></strong>
                    <span class="description"><?php esc_html_e( 'Author information on posts. Helps AI attribute quotes.', 'getcited' ); ?></span>
                </label>

                <label class="getcited-checkbox">
                    <input type="checkbox" 
                           name="schema_types[faq]" 
                           value="1"
                           <?php checked( $schema_types['faq'] ); ?>>
                    <strong><?php esc_html_e( 'FAQ', 'getcited' ); ?></strong>
                    <span class="description"><?php esc_html_e( 'Extracted from FAQ blocks in content. AI systems love Q&A format.', 'getcited' ); ?></span>
                </label>
            </div>
        </div>

        <!-- Organization Details -->
        <div class="getcited-section getcited-organization">
            <h2><?php esc_html_e( 'Organization Details', 'getcited' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'This information appears in your Organization schema.', 'getcited' ); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="org_name"><?php esc_html_e( 'Organization Name', 'getcited' ); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="organization[name]" 
                               id="org_name"
                               value="<?php echo esc_attr( $organization['name'] ); ?>"
                               class="regular-text"
                               placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="org_logo"><?php esc_html_e( 'Logo URL', 'getcited' ); ?></label>
                    </th>
                    <td>
                        <input type="url" 
                               name="organization[logo_url]" 
                               id="org_logo"
                               value="<?php echo esc_url( $organization['logo_url'] ); ?>"
                               class="regular-text"
                               placeholder="https://example.com/logo.png">
                        <button type="button" class="button getcited-upload-logo">
                            <?php esc_html_e( 'Upload', 'getcited' ); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e( 'Recommended: At least 112x112 pixels, square or rectangular.', 'getcited' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Social Profiles', 'getcited' ); ?></label>
                    </th>
                    <td>
                        <div class="getcited-social-urls">
                            <?php 
                            $social_urls = $organization['social_urls'] ?? array();
                            $placeholders = array(
                                'https://twitter.com/yourhandle',
                                'https://linkedin.com/company/yourcompany',
                                'https://facebook.com/yourpage',
                            );
                            for ( $i = 0; $i < max( 3, count( $social_urls ) ); $i++ ) :
                                $value = $social_urls[ $i ] ?? '';
                                $placeholder = $placeholders[ $i ] ?? 'https://...';
                            ?>
                                <input type="url" 
                                       name="organization[social_urls][]" 
                                       value="<?php echo esc_url( $value ); ?>"
                                       class="regular-text"
                                       placeholder="<?php echo esc_attr( $placeholder ); ?>">
                            <?php endfor; ?>
                        </div>
                        <button type="button" class="button getcited-add-social">
                            <?php esc_html_e( '+ Add Another', 'getcited' ); ?>
                        </button>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Schema Preview -->
        <div class="getcited-section getcited-schema-preview">
            <h2>
                <?php esc_html_e( 'Schema Preview', 'getcited' ); ?>
                <a href="https://search.google.com/test/rich-results?url=<?php echo urlencode( home_url() ); ?>" 
                   target="_blank" 
                   class="button">
                    <?php esc_html_e( 'Test with Google', 'getcited' ); ?>
                </a>
            </h2>
            <p class="description">
                <?php esc_html_e( 'Organization schema as it will appear on your front page:', 'getcited' ); ?>
            </p>
            <pre class="getcited-preview-code"><?php 
                $org_schema = $schema->get_preview( 'organization' );
                echo esc_html( wp_json_encode( $org_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            ?></pre>
        </div>

        <!-- Save Button -->
        <div class="getcited-section getcited-actions">
            <button type="button" class="button button-primary getcited-save-schema">
                <?php esc_html_e( 'Save Changes', 'getcited' ); ?>
            </button>
            <span class="getcited-save-status"></span>
        </div>

    </div>
</div>
<?php
} )();

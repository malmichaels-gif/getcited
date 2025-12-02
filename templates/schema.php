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
$pro_teaser = GetCited_Pro_Teaser::instance();

$enabled = $settings->get( 'schema_enabled' );
$schema_types = $settings->get( 'schema_types' );
$organization = $settings->get( 'organization' );
$detected_plugins = $schema->get_detected_plugins();

// Count active types
$active_count = 0;
foreach ( $schema_types as $type => $active ) {
    if ( $active ) {
        $active_count++;
    }
}
?>

<div class="wrap getcited-wrap">
    <h1><?php esc_html_e( 'Schema Settings', 'getcited' ); ?></h1>
    <p class="description">
        <?php esc_html_e( 'Schema markup helps AI systems understand your content structure. JSON-LD is output in your page headers.', 'getcited' ); ?>
    </p>

    <div class="getcited-schema-page">

        <!-- Pro Teaser Banner -->
        <?php $pro_teaser->render_page_teaser( 'schema' ); ?>

        <!-- Conflict Warning -->
        <?php if ( ! empty( $detected_plugins ) ) : ?>
            <div class="getcited-section getcited-warning">
                <p>
                    <span class="dashicons dashicons-warning"></span>
                    <?php
                    printf(
                        /* translators: %s: comma-separated list of detected schema plugin names */
                        esc_html__( 'Detected schema plugins: %s. Enabling GetCited schema may cause duplicate output.', 'getcited' ),
                        '<strong>' . esc_html( implode( ', ', array_column( $detected_plugins, 'name' ) ) ) . '</strong>'
                    ); ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Two-Column Settings Grid -->
        <div class="getcited-settings-grid">
            <!-- Left Column: Enable + Status -->
            <div class="getcited-section getcited-schema-toggle">
                <h2><?php esc_html_e( 'Schema Output', 'getcited' ); ?></h2>
                <label class="getcited-toggle-label">
                    <input type="checkbox"
                           name="schema_enabled"
                           id="schema_enabled"
                           value="1"
                           <?php checked( $enabled ); ?>>
                    <strong><?php esc_html_e( 'Enable JSON-LD', 'getcited' ); ?></strong>
                </label>
                <p class="description">
                    <?php esc_html_e( 'Output structured data in page headers.', 'getcited' ); ?>
                </p>

                <?php if ( $enabled && $active_count > 0 ) : ?>
                    <p class="getcited-file-status">
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php
                        printf(
                            /* translators: %d: number of active schema types */
                            esc_html( _n( '%d type active', '%d types active', $active_count, 'getcited' ) ),
                            $active_count
                        );
                        ?>
                    </p>
                <?php endif; ?>

                <div class="getcited-schema-test">
                    <a href="https://search.google.com/test/rich-results?url=<?php echo urlencode( home_url() ); ?>"
                       target="_blank"
                       class="button">
                        <span class="dashicons dashicons-external"></span>
                        <?php esc_html_e( 'Test with Google', 'getcited' ); ?>
                    </a>
                </div>
            </div>

            <!-- Right Column: Schema Types -->
            <div class="getcited-section getcited-schema-types">
                <h2><?php esc_html_e( 'Schema Types', 'getcited' ); ?></h2>

                <div class="getcited-schema-options-compact">
                    <label class="getcited-checkbox-compact">
                        <input type="checkbox"
                               name="schema_types[organization]"
                               value="1"
                               <?php checked( $schema_types['organization'] ); ?>>
                        <span class="checkbox-label">
                            <strong><?php esc_html_e( 'Organization', 'getcited' ); ?></strong>
                            <span class="description"><?php esc_html_e( 'Site/company identity', 'getcited' ); ?></span>
                        </span>
                    </label>

                    <label class="getcited-checkbox-compact">
                        <input type="checkbox"
                               name="schema_types[article]"
                               value="1"
                               <?php checked( $schema_types['article'] ); ?>>
                        <span class="checkbox-label">
                            <strong><?php esc_html_e( 'Article', 'getcited' ); ?></strong>
                            <span class="description"><?php esc_html_e( 'Posts & articles', 'getcited' ); ?></span>
                        </span>
                    </label>

                    <label class="getcited-checkbox-compact">
                        <input type="checkbox"
                               name="schema_types[author]"
                               value="1"
                               <?php checked( $schema_types['author'] ); ?>>
                        <span class="checkbox-label">
                            <strong><?php esc_html_e( 'Author', 'getcited' ); ?></strong>
                            <span class="description"><?php esc_html_e( 'Author attribution', 'getcited' ); ?></span>
                        </span>
                    </label>

                    <label class="getcited-checkbox-compact">
                        <input type="checkbox"
                               name="schema_types[faq]"
                               value="1"
                               <?php checked( $schema_types['faq'] ); ?>>
                        <span class="checkbox-label">
                            <strong><?php esc_html_e( 'FAQ', 'getcited' ); ?></strong>
                            <span class="description"><?php esc_html_e( 'Q&A format', 'getcited' ); ?></span>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Organization Details (Collapsible) -->
        <div class="getcited-section getcited-organization getcited-collapsible" data-collapsed="true">
            <h2 class="getcited-collapsible-header">
                <?php esc_html_e( 'Organization Details', 'getcited' ); ?>
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </h2>
            <div class="getcited-collapsible-content" style="display: none;">
                <p class="description">
                    <?php esc_html_e( 'This information appears in your Organization schema.', 'getcited' ); ?>
                </p>

                <div class="getcited-compact-form">
                    <div class="form-row">
                        <label for="org_name"><?php esc_html_e( 'Organization Name', 'getcited' ); ?></label>
                        <input type="text"
                               name="organization[name]"
                               id="org_name"
                               value="<?php echo esc_attr( $organization['name'] ); ?>"
                               placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                    </div>
                    <div class="form-row">
                        <label for="org_logo"><?php esc_html_e( 'Logo URL', 'getcited' ); ?></label>
                        <div class="input-with-button">
                            <input type="url"
                                   name="organization[logo_url]"
                                   id="org_logo"
                                   value="<?php echo esc_url( $organization['logo_url'] ); ?>"
                                   placeholder="https://example.com/logo.png">
                            <button type="button" class="button getcited-upload-logo">
                                <?php esc_html_e( 'Upload', 'getcited' ); ?>
                            </button>
                        </div>
                    </div>
                    <div class="form-row form-row-full">
                        <label><?php esc_html_e( 'Social Profiles', 'getcited' ); ?></label>
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
                                       placeholder="<?php echo esc_attr( $placeholder ); ?>">
                            <?php endfor; ?>
                        </div>
                        <button type="button" class="button getcited-add-social">
                            <?php esc_html_e( '+ Add Another', 'getcited' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schema Preview (Collapsible) -->
        <div class="getcited-section getcited-schema-preview getcited-collapsible" data-collapsed="true">
            <h2 class="getcited-collapsible-header">
                <?php esc_html_e( 'Schema Preview', 'getcited' ); ?>
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </h2>
            <div class="getcited-collapsible-content" style="display: none;">
                <p class="description">
                    <?php esc_html_e( 'Organization schema as it will appear on your front page:', 'getcited' ); ?>
                </p>
                <pre class="getcited-preview-code"><?php
                    $org_schema = $schema->get_preview( 'organization' );
                    echo esc_html( wp_json_encode( $org_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
                ?></pre>
            </div>
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

<?php
/**
 * AI Crawlers management template
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

( function() {
$crawler_list = GetCited_Crawler_List::instance();
$settings = GetCited_Settings::instance();
$robots = GetCited_Robots::instance();

$grouped_crawlers = $crawler_list->get_grouped();
$crawler_states = $settings->get( 'crawlers' );
$custom_crawlers = $settings->get( 'custom_crawlers' );
$robots_write_physical = $settings->get( 'robots_write_physical' );

$physical_exists = $robots->physical_file_exists();
$rules_exist = $robots->rules_exist_in_physical_file();
$can_write = $robots->can_write_physical_file();

$list_version = $crawler_list->get_version();
$list_updated = $crawler_list->get_last_updated();
?>

<div class="wrap getcited-wrap">
    <h1><?php esc_html_e( 'AI Crawlers', 'getcited' ); ?></h1>
    <p class="description">
        <?php esc_html_e( 'Control which AI systems can crawl your site. Allowed crawlers can access and potentially cite your content.', 'getcited' ); ?>
    </p>

    <div class="getcited-crawlers-page">
        
        <!-- Bulk Actions -->
        <div class="getcited-section getcited-bulk-actions">
            <button type="button" class="button getcited-allow-all">
                <?php esc_html_e( 'Allow All', 'getcited' ); ?>
            </button>
            <button type="button" class="button getcited-block-all">
                <?php esc_html_e( 'Block All', 'getcited' ); ?>
            </button>
            <span class="getcited-list-info">
                <?php
                printf(
                    /* translators: %1$s: version number, %2$s: last updated date */
                    esc_html__( 'Crawler list v%1$s (updated %2$s)', 'getcited' ),
                    esc_html( $list_version ),
                    esc_html( $list_updated )
                ); ?>
            </span>
        </div>

        <!-- Crawler Groups -->
        <div class="getcited-crawler-groups">
            <?php foreach ( $grouped_crawlers as $company => $crawlers ) : ?>
                <div class="getcited-section getcited-crawler-group">
                    <h2><?php echo esc_html( $company ); ?></h2>
                    
                    <div class="getcited-crawler-list">
                        <?php foreach ( $crawlers as $crawler ) : 
                            $name = $crawler['name'];
                            $status = $crawler_states[ $name ] ?? 'allow';
                            $is_allowed = $status === 'allow';
                        ?>
                            <div class="getcited-crawler-item" data-crawler="<?php echo esc_attr( $name ); ?>">
                                <div class="crawler-toggle">
                                    <label class="getcited-toggle">
                                        <input type="checkbox" 
                                               name="crawlers[<?php echo esc_attr( $name ); ?>]" 
                                               value="allow"
                                               <?php checked( $is_allowed ); ?>
                                               data-crawler="<?php echo esc_attr( $name ); ?>">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="crawler-info">
                                    <h4>
                                        <?php echo esc_html( $name ); ?>
                                        <?php if ( ! empty( $crawler['recommended'] ) ) : ?>
                                            <span class="getcited-badge recommended"><?php esc_html_e( 'Recommended', 'getcited' ); ?></span>
                                        <?php endif; ?>
                                    </h4>
                                    <p class="crawler-purpose"><?php echo esc_html( $crawler['purpose'] ); ?></p>
                                    <p class="crawler-ua">
                                        <code>User-agent: <?php echo esc_html( $crawler['user_agent'] ); ?></code>
                                    </p>
                                </div>
                                <div class="crawler-status">
                                    <span class="status-label <?php echo $is_allowed ? 'allowed' : 'blocked'; ?>">
                                        <?php echo $is_allowed ? esc_html__( 'Allowed', 'getcited' ) : esc_html__( 'Blocked', 'getcited' ); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Custom Crawlers -->
        <div class="getcited-section getcited-custom-crawlers">
            <h2><?php esc_html_e( 'Custom Crawlers', 'getcited' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Add crawlers that are not in the official list.', 'getcited' ); ?>
            </p>

            <div class="getcited-custom-list">
                <?php if ( ! empty( $custom_crawlers ) ) : ?>
                    <?php foreach ( $custom_crawlers as $index => $crawler ) : ?>
                        <div class="getcited-custom-item" data-index="<?php echo esc_attr( $index ); ?>">
                            <input type="text" 
                                   name="custom_crawlers[<?php echo esc_attr( $index ); ?>][user_agent]" 
                                   value="<?php echo esc_attr( $crawler['user_agent'] ); ?>"
                                   placeholder="<?php esc_attr_e( 'User-agent string', 'getcited' ); ?>">
                            <input type="text" 
                                   name="custom_crawlers[<?php echo esc_attr( $index ); ?>][name]" 
                                   value="<?php echo esc_attr( $crawler['name'] ); ?>"
                                   placeholder="<?php esc_attr_e( 'Name (optional)', 'getcited' ); ?>">
                            <select name="custom_crawlers[<?php echo esc_attr( $index ); ?>][action]">
                                <option value="allow" <?php selected( $crawler['action'], 'allow' ); ?>>
                                    <?php esc_html_e( 'Allow', 'getcited' ); ?>
                                </option>
                                <option value="block" <?php selected( $crawler['action'], 'block' ); ?>>
                                    <?php esc_html_e( 'Block', 'getcited' ); ?>
                                </option>
                            </select>
                            <button type="button" class="button getcited-remove-custom">×</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <button type="button" class="button getcited-add-custom">
                <?php esc_html_e( '+ Add Custom Crawler', 'getcited' ); ?>
            </button>

            <div class="getcited-custom-actions">
                <button type="button" class="button button-primary getcited-save-crawlers">
                    <?php esc_html_e( 'Save Changes', 'getcited' ); ?>
                </button>
                <span class="getcited-save-status"></span>
            </div>
        </div>

        <!-- robots.txt Settings -->
        <div class="getcited-section getcited-robots-settings">
            <h2><?php esc_html_e( 'robots.txt Settings', 'getcited' ); ?></h2>

            <label class="getcited-toggle-label">
                <input type="checkbox"
                       name="robots_write_physical"
                       id="robots_write_physical"
                       value="1"
                       <?php checked( $robots_write_physical ); ?>
                       <?php disabled( ! $can_write ); ?>>
                <strong><?php esc_html_e( 'Auto-write to robots.txt', 'getcited' ); ?></strong>
            </label>
            <p class="description">
                <?php esc_html_e( 'Automatically add AI crawler rules to your physical robots.txt file. Existing entries (sitemaps, other rules) are preserved.', 'getcited' ); ?>
            </p>

            <?php if ( ! $can_write ) : ?>
                <p class="getcited-notice getcited-notice-warning">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e( 'Cannot write to robots.txt. Check file permissions or contact your host.', 'getcited' ); ?>
                </p>
            <?php endif; ?>

            <?php if ( $physical_exists ) : ?>
                <p class="getcited-file-status">
                    <?php if ( $rules_exist ) : ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php esc_html_e( 'GetCited rules are present in robots.txt', 'getcited' ); ?>
                    <?php else : ?>
                        <span class="dashicons dashicons-info" style="color: #0073aa;"></span>
                        <?php esc_html_e( 'Physical robots.txt exists but does not contain GetCited rules', 'getcited' ); ?>
                    <?php endif; ?>
                </p>
            <?php else : ?>
                <p class="getcited-file-status">
                    <span class="dashicons dashicons-info" style="color: #0073aa;"></span>
                    <?php esc_html_e( 'No physical robots.txt file exists. WordPress generates it dynamically.', 'getcited' ); ?>
                </p>
            <?php endif; ?>

            <div class="getcited-robots-actions">
                <?php if ( $can_write ) : ?>
                    <button type="button" class="button getcited-write-robots-file">
                        <span class="dashicons dashicons-media-text"></span>
                        <?php esc_html_e( 'Write Rules Now', 'getcited' ); ?>
                    </button>
                    <?php if ( $rules_exist ) : ?>
                        <button type="button" class="button getcited-remove-robots-rules">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e( 'Remove Rules', 'getcited' ); ?>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
                <span class="getcited-robots-status"></span>
            </div>
        </div>

        <!-- robots.txt Preview -->
        <div class="getcited-section getcited-robots-preview">
            <div class="getcited-preview-header">
                <h2>
                    <?php esc_html_e( 'robots.txt Preview', 'getcited' ); ?>
                    <a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" class="button">
                        <?php esc_html_e( 'View Live', 'getcited' ); ?>
                    </a>
                </h2>
                <button type="button" class="button getcited-copy-content" data-target="robots_txt_preview">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e( 'Copy', 'getcited' ); ?>
                </button>
            </div>
            <p class="description">
                <?php esc_html_e( 'This preview shows the GetCited rules that will be added to your robots.txt:', 'getcited' ); ?>
            </p>
            <pre class="getcited-preview-code" id="robots_txt_preview"><?php echo esc_html( $robots->get_preview() ); ?></pre>
            <p class="description getcited-copy-hint">
                <?php esc_html_e( 'Copy and paste into robots.txt in your site root if auto-write is unavailable.', 'getcited' ); ?>
            </p>
        </div>

    </div>
</div>
<?php
} )();

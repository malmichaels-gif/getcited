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
        </div>

        <!-- robots.txt Preview -->
        <div class="getcited-section getcited-robots-preview">
            <h2>
                <?php esc_html_e( 'robots.txt Preview', 'getcited' ); ?>
                <a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" class="button">
                    <?php esc_html_e( 'View Live', 'getcited' ); ?>
                </a>
            </h2>
            <pre class="getcited-preview-code"><?php echo esc_html( $robots->get_preview() ); ?></pre>
        </div>

    </div>
</div>
<?php
} )();

<?php
/**
 * Dashboard template
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

( function() {
$stats = GetCited_Dashboard::instance()->get_stats();
$health = GetCited_Health_Check::instance();
$pro_teaser = GetCited_Pro_Teaser::instance();
?>

<div class="wrap getcited-wrap">
    <h1><?php echo esc_html( apply_filters( 'getcited_brand_name', 'GetCited' ) ); ?></h1>

    <div class="getcited-dashboard">
        
        <!-- Status Overview -->
        <div class="getcited-section getcited-status-overview">
            <h2><?php esc_html_e( 'AI Visibility Status', 'getcited' ); ?></h2>
            
            <div class="getcited-status-cards">
                <!-- Crawlers Card -->
                <div class="getcited-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-admin-site-alt3"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php esc_html_e( 'AI Crawlers', 'getcited' ); ?></h3>
                        <p class="card-stat">
                            <strong><?php echo esc_html( $stats['crawlers']['allowed'] ); ?></strong> 
                            <?php esc_html_e( 'allowed', 'getcited' ); ?> / 
                            <strong><?php echo esc_html( $stats['crawlers']['blocked'] ); ?></strong> 
                            <?php esc_html_e( 'blocked', 'getcited' ); ?>
                        </p>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=getcited-crawlers' ) ); ?>" class="card-link">
                        <?php esc_html_e( 'Manage', 'getcited' ); ?> →
                    </a>
                </div>

                <!-- llms.txt Card -->
                <div class="getcited-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-media-text"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php esc_html_e( 'llms.txt', 'getcited' ); ?></h3>
                        <?php
                        $llms_status = $stats['llms_txt']['status'];
                        $status_class = $health->get_status_class( $llms_status );
                        ?>
                        <p class="card-stat <?php echo esc_attr( $status_class ); ?>">
                            <?php echo esc_html( $health->get_status_label( $llms_status ) ); ?>
                        </p>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=getcited-llms-txt' ) ); ?>" class="card-link">
                        <?php esc_html_e( 'Edit', 'getcited' ); ?> →
                    </a>
                </div>

                <!-- Schema Card -->
                <div class="getcited-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-editor-code"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php esc_html_e( 'Schema', 'getcited' ); ?></h3>
                        <p class="card-stat">
                            <?php if ( $stats['schema']['enabled'] ) : ?>
                                <?php echo esc_html( count( $stats['schema']['types'] ) ); ?> 
                                <?php esc_html_e( 'types active', 'getcited' ); ?>
                            <?php else : ?>
                                <?php esc_html_e( 'Disabled', 'getcited' ); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=getcited-schema' ) ); ?>" class="card-link">
                        <?php esc_html_e( 'Configure', 'getcited' ); ?> →
                    </a>
                </div>

                <!-- Citability Card -->
                <div class="getcited-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-chart-bar"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php esc_html_e( 'Citability Score', 'getcited' ); ?></h3>
                        <p class="card-stat">
                            <?php if ( $stats['citability']['average'] > 0 ) : ?>
                                <strong><?php echo esc_html( $stats['citability']['average'] ); ?></strong>/100 
                                <?php esc_html_e( 'average', 'getcited' ); ?>
                            <?php else : ?>
                                <?php esc_html_e( 'Not analyzed yet', 'getcited' ); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=getcited-citability' ) ); ?>" class="card-link">
                        <?php esc_html_e( 'Analyze', 'getcited' ); ?> →
                    </a>
                </div>
            </div>
        </div>

        <!-- Health Check Section -->
        <div class="getcited-section getcited-health-section">
            <h2>
                <?php esc_html_e( 'Health Check', 'getcited' ); ?>
                <button type="button" class="button getcited-run-health-check">
                    <?php esc_html_e( 'Run Check', 'getcited' ); ?>
                </button>
            </h2>

            <div class="getcited-health-results">
                <?php
                $health_status = $stats['health'];
                $checks = array(
                    'llms_txt' => __( 'llms.txt', 'getcited' ),
                    'robots_txt' => __( 'robots.txt', 'getcited' ),
                    'schema' => __( 'Schema', 'getcited' ),
                    'rewrite_rules' => __( 'Rewrite Rules', 'getcited' ),
                    'crawler_list' => __( 'Crawler List', 'getcited' ),
                );

                foreach ( $checks as $key => $label ) :
                    if ( ! isset( $health_status[ $key ] ) ) continue;
                    $check = $health_status[ $key ];
                    $status_class = $health->get_status_class( $check['status'] );
                ?>
                    <div class="getcited-health-item <?php echo esc_attr( $status_class ); ?>" data-check="<?php echo esc_attr( $key ); ?>">
                        <span class="health-icon"></span>
                        <span class="health-label"><?php echo esc_html( $label ); ?></span>
                        <span class="health-message"><?php echo esc_html( $check['message'] ); ?></span>

                        <?php if ( ! empty( $check['details'] ) || ! empty( $check['action_type'] ) ) : ?>
                            <button type="button" class="getcited-health-expand" aria-expanded="false">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if ( ! empty( $check['details'] ) || ! empty( $check['action_type'] ) ) : ?>
                        <div class="getcited-health-details" data-check="<?php echo esc_attr( $key ); ?>" style="display: none;">
                            <?php if ( ! empty( $check['details'] ) ) : ?>
                                <p class="details-text"><?php echo esc_html( $check['details'] ); ?></p>
                            <?php endif; ?>

                            <?php if ( ! empty( $check['options'] ) ) : ?>
                                <ul class="details-options">
                                    <?php foreach ( $check['options'] as $option ) : ?>
                                        <li><?php echo esc_html( $option ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if ( ! empty( $check['action_type'] ) ) : ?>
                                <div class="details-actions">

                                    <?php if ( $check['action_type'] === 'auto_add' ) : ?>
                                        <!-- One-click add button -->
                                        <button type="button" class="button button-primary getcited-add-robots-rules">
                                            <span class="dashicons dashicons-plus-alt2"></span>
                                            <?php echo esc_html( $check['action_label'] ); ?>
                                        </button>
                                        <span class="getcited-action-status"></span>

                                        <?php if ( ! empty( $check['seo_plugin'] ) ) : ?>
                                            <p class="alternative-action">
                                                <?php esc_html_e( 'Or add manually via:', 'getcited' ); ?>
                                                <a href="<?php echo esc_url( admin_url( $check['seo_plugin']['robots_editor']['url'] ) ); ?>">
                                                    <?php echo esc_html( $check['seo_plugin']['robots_editor']['path'] ); ?>
                                                </a>
                                            </p>
                                        <?php endif; ?>

                                        <?php if ( ! empty( $check['detected_plugins'] ) ) : ?>
                                            <p class="detected-plugins">
                                                <?php
                                                printf(
                                                    /* translators: %s: comma-separated list of plugin names */
                                                    esc_html__( 'Detected plugins modifying robots.txt: %s', 'getcited' ),
                                                    esc_html( implode( ', ', $check['detected_plugins'] ) )
                                                );
                                                ?>
                                            </p>
                                        <?php endif; ?>

                                        <!-- Expandable rules preview -->
                                        <button type="button" class="button getcited-toggle-rules">
                                            <?php esc_html_e( 'Preview Rules', 'getcited' ); ?>
                                        </button>
                                        <div class="getcited-rules-preview" style="display: none;">
                                            <pre class="rules-code"><?php echo esc_html( $check['rules'] ); ?></pre>
                                        </div>

                                    <?php elseif ( $check['action_type'] === 'copy_rules' && ! empty( $check['rules'] ) ) : ?>
                                        <!-- Copy rules (fallback when can't write) -->
                                        <div class="getcited-rules-preview">
                                            <pre class="rules-code"><?php echo esc_html( $check['rules'] ); ?></pre>
                                            <button type="button" class="button getcited-copy-rules" data-rules="<?php echo esc_attr( $check['rules'] ); ?>">
                                                <span class="dashicons dashicons-clipboard"></span>
                                                <?php esc_html_e( 'Copy Rules to Clipboard', 'getcited' ); ?>
                                            </button>
                                        </div>

                                        <?php if ( ! empty( $check['seo_plugin'] ) ) : ?>
                                            <p class="paste-location">
                                                <?php esc_html_e( 'Paste into:', 'getcited' ); ?>
                                                <a href="<?php echo esc_url( admin_url( $check['seo_plugin']['robots_editor']['url'] ) ); ?>">
                                                    <?php echo esc_html( $check['seo_plugin']['robots_editor']['path'] ); ?>
                                                </a>
                                            </p>
                                        <?php endif; ?>

                                        <?php if ( ! empty( $check['file_path'] ) ) : ?>
                                            <p class="file-path">
                                                <?php esc_html_e( 'File location:', 'getcited' ); ?>
                                                <code><?php echo esc_html( $check['file_path'] ); ?></code>
                                            </p>
                                        <?php endif; ?>

                                    <?php elseif ( $check['action_type'] === 'copy_content' && ! empty( $check['content'] ) ) : ?>
                                        <!-- Copy llms.txt content -->
                                        <p class="current-content-label"><?php esc_html_e( 'Current file content (first 500 chars):', 'getcited' ); ?></p>
                                        <pre class="content-preview"><?php echo esc_html( $check['current_content_preview'] ); ?></pre>

                                        <p class="getcited-content-label"><?php esc_html_e( 'GetCited content to use:', 'getcited' ); ?></p>
                                        <div class="getcited-rules-preview">
                                            <pre class="rules-code"><?php echo esc_html( $check['content'] ); ?></pre>
                                            <button type="button" class="button getcited-copy-rules" data-rules="<?php echo esc_attr( $check['content'] ); ?>">
                                                <span class="dashicons dashicons-clipboard"></span>
                                                <?php esc_html_e( 'Copy Content to Clipboard', 'getcited' ); ?>
                                            </button>
                                        </div>

                                    <?php elseif ( ! empty( $check['action_url'] ) && ! empty( $check['action_label'] ) ) : ?>
                                        <!-- Link to settings -->
                                        <a href="<?php echo esc_url( $check['action_url'] ); ?>" class="button">
                                            <?php echo esc_html( $check['action_label'] ); ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ( ! empty( $check['rules'] ) && ! in_array( $check['action_type'], array( 'copy_rules', 'auto_add' ), true ) ) : ?>
                                        <button type="button" class="button getcited-show-rules">
                                            <?php esc_html_e( 'Show Rules', 'getcited' ); ?>
                                        </button>
                                        <div class="getcited-rules-preview" style="display: none;">
                                            <pre class="rules-code"><?php echo esc_html( $check['rules'] ); ?></pre>
                                            <button type="button" class="button getcited-copy-rules" data-rules="<?php echo esc_attr( $check['rules'] ); ?>">
                                                <span class="dashicons dashicons-clipboard"></span>
                                                <?php esc_html_e( 'Copy Rules to Clipboard', 'getcited' ); ?>
                                            </button>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ( ! empty( $check['content_preview'] ) && $check['action_type'] !== 'copy_content' ) : ?>
                                        <div class="conflict-preview">
                                            <p class="preview-label"><?php esc_html_e( 'Current content preview:', 'getcited' ); ?></p>
                                            <pre class="content-preview"><?php echo esc_html( $check['content_preview'] ); ?></pre>
                                        </div>
                                    <?php endif; ?>

                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="getcited-section getcited-quick-links">
            <h2><?php esc_html_e( 'Quick Links', 'getcited' ); ?></h2>
            <div class="getcited-links">
                <a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" class="getcited-link">
                    <span class="dashicons dashicons-external"></span>
                    <?php esc_html_e( 'View robots.txt', 'getcited' ); ?>
                </a>
                <a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" class="getcited-link">
                    <span class="dashicons dashicons-external"></span>
                    <?php esc_html_e( 'View llms.txt', 'getcited' ); ?>
                </a>
                <a href="https://search.google.com/test/rich-results?url=<?php echo urlencode( home_url() ); ?>" target="_blank" class="getcited-link">
                    <span class="dashicons dashicons-external"></span>
                    <?php esc_html_e( 'Test Schema', 'getcited' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=getcited-settings' ) ); ?>" class="getcited-link">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e( 'Settings', 'getcited' ); ?>
                </a>
            </div>
        </div>

        <!-- Pro Features Section -->
        <?php $pro_teaser->render_dashboard_teasers(); ?>

    </div>

    <?php $pro_teaser->render_sample_modal(); ?>
</div>
<?php
} )();

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
$visibility_score = $stats['visibility_score'];
$llms_activity = $stats['llms_activity'];
?>

<div class="wrap getcited-wrap">
    <h1><?php echo esc_html( apply_filters( 'getcited_brand_name', 'GetCited' ) ); ?></h1>

    <div class="getcited-dashboard">

        <!-- AI Visibility Score -->
        <div class="getcited-section getcited-visibility-score-section">
            <div class="visibility-score-container">
                <div class="visibility-score-circle tier-<?php echo esc_attr( $visibility_score['tier']['class'] ); ?>">
                    <svg viewBox="0 0 100 100" class="score-svg">
                        <circle class="score-bg" cx="50" cy="50" r="45" />
                        <circle class="score-progress" cx="50" cy="50" r="45"
                            stroke-dasharray="<?php echo esc_attr( ( $visibility_score['total'] / 100 ) * 283 ); ?> 283"
                            style="stroke: <?php echo esc_attr( $visibility_score['tier']['color'] ); ?>" />
                    </svg>
                    <div class="score-value">
                        <span class="score-number"><?php echo esc_html( $visibility_score['total'] ); ?></span>
                        <span class="score-max">/100</span>
                    </div>
                </div>
                <div class="visibility-score-info">
                    <h2><?php esc_html_e( 'AI Visibility Score', 'getcited' ); ?></h2>
                    <p class="tier-label tier-<?php echo esc_attr( $visibility_score['tier']['class'] ); ?>">
                        <?php echo esc_html( $visibility_score['tier']['label'] ); ?>
                    </p>
                    <button type="button" class="button getcited-refresh-score">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e( 'Refresh', 'getcited' ); ?>
                    </button>
                </div>
                <div class="visibility-score-breakdown">
                    <h3><?php esc_html_e( 'Breakdown', 'getcited' ); ?></h3>
                    <?php
                    $labels = GetCited_Visibility_Score::get_component_labels();
                    $max_points = GetCited_Visibility_Score::get_max_points();
                    foreach ( $visibility_score['breakdown'] as $key => $score ) :
                        $max = $max_points[ $key ];
                        $percent = ( $score / $max ) * 100;
                    ?>
                    <div class="breakdown-item" data-key="<?php echo esc_attr( $key ); ?>">
                        <div class="breakdown-label">
                            <span class="label-text"><?php echo esc_html( $labels[ $key ] ); ?></span>
                            <span class="label-score"><?php echo esc_html( $score ); ?>/<?php echo esc_html( $max ); ?></span>
                        </div>
                        <div class="breakdown-bar">
                            <div class="breakdown-fill" style="width: <?php echo esc_attr( $percent ); ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ( ! empty( $visibility_score['recommendations'] ) ) : ?>
                <div class="visibility-score-recommendations">
                    <h4><?php esc_html_e( 'Top Recommendation', 'getcited' ); ?></h4>
                    <p><?php echo esc_html( $visibility_score['recommendations'][0] ); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

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

        <!-- llms.txt Activity -->
        <div class="getcited-section getcited-llms-activity-section">
            <h2><?php esc_html_e( 'llms.txt Activity', 'getcited' ); ?></h2>

            <?php if ( ! $llms_activity['enabled'] ) : ?>
                <div class="getcited-activity-disabled">
                    <p><?php esc_html_e( 'Request logging is disabled.', 'getcited' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=getcited-settings' ) ); ?>" class="button">
                        <?php esc_html_e( 'Enable in Settings', 'getcited' ); ?>
                    </a>
                </div>

            <?php elseif ( empty( $llms_activity['recent'] ) ) : ?>
                <div class="getcited-activity-empty">
                    <p><?php esc_html_e( 'No requests logged yet.', 'getcited' ); ?></p>
                    <p class="description">
                        <?php esc_html_e( 'AI crawlers typically visit within 1-7 days of your llms.txt going live. Check back soon!', 'getcited' ); ?>
                    </p>
                    <a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" class="button">
                        <?php esc_html_e( 'Verify llms.txt is accessible', 'getcited' ); ?>
                    </a>
                </div>

            <?php else : ?>
                <div class="getcited-activity-content">
                    <div class="activity-list">
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Time', 'getcited' ); ?></th>
                                    <th><?php esc_html_e( 'Bot', 'getcited' ); ?></th>
                                    <th><?php esc_html_e( 'Type', 'getcited' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $llms_activity['recent'] as $request ) :
                                    $category_info = GetCited_Request_Logger::get_category_display( $request->category );
                                    $time = strtotime( $request->request_time );
                                ?>
                                <tr>
                                    <td class="activity-time">
                                        <?php echo esc_html( date_i18n( 'M j, H:i', $time ) ); ?>
                                    </td>
                                    <td class="activity-bot">
                                        <?php echo esc_html( $request->bot_name ); ?>
                                    </td>
                                    <td class="activity-category">
                                        <span class="category-badge category-<?php echo esc_attr( $category_info['class'] ); ?>">
                                            <span class="category-icon"><?php echo $category_info['icon']; ?></span>
                                            <?php echo esc_html( $category_info['label'] ); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="activity-stats">
                        <?php
                        $stats_data = $llms_activity['stats'];
                        $ai_percent = $stats_data['total'] > 0 ? round( ( $stats_data['ai_crawlers'] / $stats_data['total'] ) * 100 ) : 0;
                        ?>
                        <div class="stats-summary">
                            <p>
                                <strong><?php echo esc_html( $stats_data['total'] ); ?></strong>
                                <?php esc_html_e( 'requests from', 'getcited' ); ?>
                                <strong><?php echo esc_html( $stats_data['unique_bots'] ); ?></strong>
                                <?php esc_html_e( 'unique bots', 'getcited' ); ?>
                                <span class="stats-period"><?php esc_html_e( '(Last 30 days)', 'getcited' ); ?></span>
                            </p>
                            <?php if ( $stats_data['ai_crawlers'] > 0 ) : ?>
                            <p class="ai-stats">
                                <span class="category-badge category-ai-crawler">
                                    <span class="category-icon">&#10003;</span>
                                    <?php
                                    printf(
                                        /* translators: 1: number of AI crawler requests, 2: percentage */
                                        esc_html__( 'AI Crawlers: %1$d requests (%2$d%%)', 'getcited' ),
                                        $stats_data['ai_crawlers'],
                                        $ai_percent
                                    );
                                    ?>
                                </span>
                            </p>
                            <?php endif; ?>
                        </div>
                        <?php if ( $stats_data['ai_crawlers'] > 0 ) : ?>
                        <p class="activity-success">
                            <?php esc_html_e( 'AI crawlers are actively visiting your site.', 'getcited' ); ?>
                        </p>
                        <?php elseif ( $stats_data['total'] > 0 ) : ?>
                        <p class="activity-note">
                            <?php esc_html_e( 'No AI crawlers detected yet. This is normal for new sites.', 'getcited' ); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
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
                    $has_details = ! empty( $check['details'] ) || ! empty( $check['action_type'] );
                ?>
                    <div class="getcited-health-item <?php echo esc_attr( $status_class ); ?><?php echo $has_details ? ' has-details' : ''; ?>" data-check="<?php echo esc_attr( $key ); ?>">
                        <div class="health-item-header">
                            <span class="health-icon"></span>
                            <span class="health-label"><?php echo esc_html( $label ); ?></span>
                            <span class="health-message"><?php echo esc_html( $check['message'] ); ?></span>

                            <?php if ( $has_details ) : ?>
                                <button type="button" class="getcited-health-expand" aria-expanded="false">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </button>
                            <?php endif; ?>
                        </div>

                    <?php if ( $has_details ) : ?>
                        <div class="getcited-health-details" style="display: none;">
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
                    </div><!-- .getcited-health-item -->
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

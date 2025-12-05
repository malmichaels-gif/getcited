<?php
/**
 * Citability analysis template
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

( function() {
$citability = GetCited_Citability::instance();
$pro_teaser = GetCited_Pro_Teaser::instance();
$request_logger = GetCited_Request_Logger::instance();

$rubric = $citability->get_rubric();
$recent_posts = $citability->get_analyzable_posts( 5 );
$average_score = $citability->get_average_score();
$crawler_stats = $request_logger->get_request_stats();
?>

<div class="wrap getcited-wrap">
    <h1><?php esc_html_e( 'Content Citability', 'getcited' ); ?></h1>
    <p class="description">
        <?php esc_html_e( 'Analyze how likely your content is to be cited by AI systems. Higher scores mean better AI visibility.', 'getcited' ); ?>
    </p>

    <div class="getcited-citability-page">

        <!-- Pro Teaser Banner -->
        <?php $pro_teaser->render_page_teaser( 'citability' ); ?>

        <!-- Two-Column Grid: Score + Recent Posts -->
        <div class="getcited-settings-grid">
            <!-- Left Column: Average Score -->
            <div class="getcited-section getcited-average-score">
                <h2><?php esc_html_e( 'Average Score', 'getcited' ); ?></h2>
                <div class="getcited-score-display large">
                    <span class="score"><?php echo esc_html( $average_score ?: '—' ); ?></span>
                    <span class="max">/100</span>
                </div>
                <p class="description"><?php esc_html_e( 'Across recent posts', 'getcited' ); ?></p>

                <?php if ( $average_score ) : ?>
                    <p class="getcited-score-rating">
                        <?php if ( $average_score >= 70 ) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php esc_html_e( 'Good citability', 'getcited' ); ?>
                        <?php elseif ( $average_score >= 40 ) : ?>
                            <span class="dashicons dashicons-marker" style="color: #ffb900;"></span>
                            <?php esc_html_e( 'Room for improvement', 'getcited' ); ?>
                        <?php else : ?>
                            <span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
                            <?php esc_html_e( 'Needs attention', 'getcited' ); ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Right Column: Quick Stats -->
            <div class="getcited-section getcited-quick-stats">
                <h2><?php esc_html_e( 'Quick Stats', 'getcited' ); ?></h2>
                <?php
                $total_posts = absint( wp_count_posts( 'post' )->publish );
                $analyzed_count = 0;
                foreach ( $recent_posts as $post ) {
                    if ( get_post_meta( $post->ID, '_getcited_citability_score', true ) ) {
                        $analyzed_count++;
                    }
                }
                $ai_visits = $crawler_stats['ai_crawlers'] ?? 0;
                $unique_bots = $crawler_stats['unique_bots'] ?? 0;
                ?>
                <div class="getcited-stat-items getcited-stat-items-enhanced">
                    <div class="stat-item stat-item-large">
                        <span class="dashicons dashicons-visibility" style="color: var(--getcited-primary);"></span>
                        <span class="stat-value"><?php echo esc_html( number_format_i18n( $ai_visits ) ); ?></span>
                        <span class="stat-label"><?php esc_html_e( 'AI Crawler Visits', 'getcited' ); ?></span>
                        <span class="stat-sublabel"><?php esc_html_e( 'Last 30 days', 'getcited' ); ?></span>
                    </div>
                    <div class="stat-item stat-item-large">
                        <span class="dashicons dashicons-groups" style="color: var(--getcited-success);"></span>
                        <span class="stat-value"><?php echo esc_html( $unique_bots ); ?></span>
                        <span class="stat-label"><?php esc_html_e( 'Unique AI Bots', 'getcited' ); ?></span>
                        <span class="stat-sublabel"><?php esc_html_e( 'Visiting your llms.txt', 'getcited' ); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo esc_html( number_format_i18n( $total_posts ) ); ?></span>
                        <span class="stat-label"><?php esc_html_e( 'Published Posts', 'getcited' ); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo esc_html( $analyzed_count ); ?>/5</span>
                        <span class="stat-label"><?php esc_html_e( 'Analyzed (Free)', 'getcited' ); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Posts Analysis -->
        <div class="getcited-section getcited-recent-posts">
            <h2><?php esc_html_e( 'Recent Posts', 'getcited' ); ?></h2>

            <?php if ( empty( $recent_posts ) ) : ?>
                <p><?php esc_html_e( 'No published posts found to analyze.', 'getcited' ); ?></p>
            <?php else : ?>
                <table class="getcited-posts-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Post', 'getcited' ); ?></th>
                            <th><?php esc_html_e( 'Score', 'getcited' ); ?></th>
                            <th><?php esc_html_e( 'Last Analyzed', 'getcited' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'getcited' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ( $recent_posts as $post ) :
                            $score      = get_post_meta( $post->ID, '_getcited_citability_score', true );
                            $last_audit = get_post_meta( $post->ID, '_getcited_last_audit', true );
                            ?>
                            <tr data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                                <td>
                                    <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">
                                        <?php echo esc_html( $post->post_title ); ?>
                                    </a>
                                </td>
                                <td class="score-cell">
                                    <?php if ( $score ) : ?>
                                        <span class="getcited-score-badge <?php echo $score >= 70 ? 'good' : ( $score >= 40 ? 'ok' : 'low' ); ?>">
                                            <?php echo esc_html( $score ); ?>/100
                                        </span>
                                    <?php else : ?>
                                        <span class="getcited-score-badge none">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $last_audit ) : ?>
                                        <?php echo esc_html( human_time_diff( strtotime( $last_audit ) ) ); ?>
                                        <?php esc_html_e( 'ago', 'getcited' ); ?>
                                    <?php else : ?>
                                        <?php esc_html_e( 'Never', 'getcited' ); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button"
                                            class="button getcited-analyze-post"
                                            data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                                        <?php esc_html_e( 'Analyze', 'getcited' ); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( count( $recent_posts ) >= 5 && $total_posts > 5 ) : ?>
                    <p class="getcited-load-more-wrap" style="margin-top: var(--getcited-space-md);">
                        <button type="button" class="button getcited-load-more-posts" data-offset="5">
                            <?php esc_html_e( 'Load More Posts', 'getcited' ); ?>
                        </button>
                        <span class="description" style="margin-left: 8px;">
                            <?php esc_html_e( '(Free tier: up to 10 posts)', 'getcited' ); ?>
                        </span>
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Pro Upsell inline -->
            <div class="getcited-inline-upsell">
                <span class="dashicons dashicons-lock"></span>
                <?php
                printf(
                    /* translators: %d: number of published posts */
                    esc_html__( 'Upgrade to Pro to audit all %d posts with detailed recommendations.', 'getcited' ),
                    $total_posts
                );
                ?>
                <button type="button" class="button getcited-join-waitlist">
                    <?php esc_html_e( 'Join Waitlist', 'getcited' ); ?>
                </button>
            </div>
        </div>

        <!-- Scoring Rubric (Collapsible) -->
        <div class="getcited-section getcited-rubric getcited-collapsible" data-collapsed="true">
            <h2 class="getcited-collapsible-header">
                <?php esc_html_e( 'Scoring Rubric', 'getcited' ); ?>
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </h2>
            <div class="getcited-collapsible-content" style="display: none;">
                <p class="description">
                    <?php esc_html_e( 'These factors determine how likely AI systems are to cite your content:', 'getcited' ); ?>
                </p>

                <table class="getcited-rubric-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Factor', 'getcited' ); ?></th>
                        <th><?php esc_html_e( 'Points', 'getcited' ); ?></th>
                        <th><?php esc_html_e( 'What We Check', 'getcited' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rubric as $key => $factor ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $factor['label'] ); ?></strong></td>
                            <td><?php echo esc_html( $factor['max_points'] ); ?></td>
                            <td><?php echo esc_html( $factor['description'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td><strong><?php esc_html_e( 'Total', 'getcited' ); ?></strong></td>
                        <td><strong>100</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
                </table>
            </div>
        </div>

    </div>
</div>
<?php
} )();

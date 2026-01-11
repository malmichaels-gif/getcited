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


        <!-- Two-Column Grid: Score + Recent Posts -->
        <div class="getcited-settings-grid">
            <!-- Left Column: Average Score -->
            <div class="getcited-section getcited-average-score">
                <h2><?php esc_html_e( 'Average Score', 'getcited' ); ?></h2>
                <?php
                $score_class = '';
                if ( $average_score ) {
                    if ( $average_score >= 80 ) {
                        $score_class = 'score-high';
                    } elseif ( $average_score >= 51 ) {
                        $score_class = 'score-medium';
                    } else {
                        $score_class = 'score-low';
                    }
                }
                ?>
                <div class="getcited-score-display large circular <?php echo esc_attr( $score_class ); ?>">
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
                // Count all analyzed posts (including auto-analyzed on activation).
                $analyzed_count = GetCited_Citability::instance()->get_analyzed_count();
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
                        <span class="stat-sublabel"><?php esc_html_e( 'Visiting your AI visibility file', 'getcited' ); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo esc_html( number_format_i18n( $total_posts ) ); ?></span>
                        <span class="stat-label"><?php esc_html_e( 'Published Posts', 'getcited' ); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo esc_html( $analyzed_count ); ?></span>
                        <span class="stat-label"><?php esc_html_e( 'Posts Analyzed', 'getcited' ); ?></span>
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
                                    <?php if ( $score ) : ?>
                                        <a href="#" class="getcited-view-analysis" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                                            <?php echo esc_html( $post->post_title ); ?>
                                        </a>
                                    <?php else : ?>
                                        <span class="getcited-post-title-no-analysis"><?php echo esc_html( $post->post_title ); ?></span>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" class="getcited-edit-link" target="_blank" title="<?php esc_attr_e( 'Edit post', 'getcited' ); ?>">
                                        <span class="dashicons dashicons-external"></span>
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
                    </p>
                <?php endif; ?>
            <?php endif; ?>

        </div>

        <!-- What AI Looks For (Collapsible) -->
        <div class="getcited-section getcited-ai-guide getcited-collapsible">
            <h2 class="getcited-collapsible-header">
                <?php esc_html_e( 'What AI Looks For', 'getcited' ); ?>
                <span class="dashicons dashicons-arrow-up-alt2"></span>
            </h2>
            <div class="getcited-collapsible-content">
                <p class="description" style="margin-bottom: var(--getcited-space-md);">
                    <?php esc_html_e( 'AI systems prefer content that is well-structured, authoritative, and easy to understand. Here\'s what makes content more likely to be cited:', 'getcited' ); ?>
                </p>

                <div class="getcited-ai-tips-grid">
                    <div class="ai-tip-card">
                        <span class="dashicons dashicons-editor-paragraph"></span>
                        <h4><?php esc_html_e( 'Clear Opening Summary', 'getcited' ); ?></h4>
                        <p><?php esc_html_e( 'Start with a concise paragraph that states your main point. AI systems often pull from the first paragraph when generating responses.', 'getcited' ); ?></p>
                    </div>

                    <div class="ai-tip-card">
                        <span class="dashicons dashicons-heading"></span>
                        <h4><?php esc_html_e( 'Logical Heading Structure', 'getcited' ); ?></h4>
                        <p><?php esc_html_e( 'Use H2 and H3 headings to organize content hierarchically. This helps AI understand your content\'s structure and find specific information.', 'getcited' ); ?></p>
                    </div>

                    <div class="ai-tip-card">
                        <span class="dashicons dashicons-format-chat"></span>
                        <h4><?php esc_html_e( 'FAQ Sections', 'getcited' ); ?></h4>
                        <p><?php esc_html_e( 'Question-and-answer formats are highly citable. AI systems can easily extract and reference specific Q&A pairs.', 'getcited' ); ?></p>
                    </div>

                    <div class="ai-tip-card">
                        <span class="dashicons dashicons-text-page"></span>
                        <h4><?php esc_html_e( 'Comprehensive Depth', 'getcited' ); ?></h4>
                        <p><?php esc_html_e( 'Longer, in-depth content (1,000+ words) demonstrates expertise. Shallow content is less likely to be cited as authoritative.', 'getcited' ); ?></p>
                    </div>

                    <div class="ai-tip-card">
                        <span class="dashicons dashicons-list-view"></span>
                        <h4><?php esc_html_e( 'Lists and Tables', 'getcited' ); ?></h4>
                        <p><?php esc_html_e( 'Structured data like bullet points, numbered lists, and tables are easy for AI to parse and reference directly.', 'getcited' ); ?></p>
                    </div>

                    <div class="ai-tip-card">
                        <span class="dashicons dashicons-shortcode"></span>
                        <h4><?php esc_html_e( 'Schema Markup', 'getcited' ); ?></h4>
                        <p><?php esc_html_e( 'Article and FAQ schema help AI systems understand your content type and extract structured information.', 'getcited' ); ?></p>
                    </div>

                    <div class="ai-tip-card">
                        <span class="dashicons dashicons-admin-users"></span>
                        <h4><?php esc_html_e( 'Author Attribution', 'getcited' ); ?></h4>
                        <p><?php esc_html_e( 'Named authors with bios signal credibility. Anonymous content is less trustworthy to AI systems.', 'getcited' ); ?></p>
                    </div>

                    <div class="ai-tip-card">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <h4><?php esc_html_e( 'Content Freshness', 'getcited' ); ?></h4>
                        <p><?php esc_html_e( 'Recently published or updated content is preferred. Keep publish dates visible and update old posts regularly.', 'getcited' ); ?></p>
                    </div>

                    <div class="ai-tip-card">
                        <span class="dashicons dashicons-admin-links"></span>
                        <h4><?php esc_html_e( 'Internal & External Links', 'getcited' ); ?></h4>
                        <p><?php esc_html_e( 'Link to related content on your site and cite authoritative external sources. This builds topical authority and credibility.', 'getcited' ); ?></p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<?php
} )();

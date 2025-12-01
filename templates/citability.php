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

$rubric = $citability->get_rubric();
$recent_posts = $citability->get_analyzable_posts( 5 );
$average_score = $citability->get_average_score();
?>

<div class="wrap getcited-wrap">
    <h1><?php esc_html_e( 'Content Citability', 'getcited' ); ?></h1>
    <p class="description">
        <?php esc_html_e( 'Analyze how likely your content is to be cited by AI systems. Higher scores mean better AI visibility.', 'getcited' ); ?>
    </p>

    <div class="getcited-citability-page">
        
        <!-- Average Score -->
        <div class="getcited-section getcited-average-score">
            <div class="getcited-score-display large">
                <span class="score"><?php echo esc_html( $average_score ?: '—' ); ?></span>
                <span class="max">/100</span>
            </div>
            <p><?php esc_html_e( 'Average citability score across recent posts', 'getcited' ); ?></p>
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
                        <?php foreach ( $recent_posts as $post ) : 
                            $score = get_post_meta( $post->ID, '_getcited_citability_score', true );
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
            <?php endif; ?>
        </div>

        <!-- Pro Upsell: Full Audit -->
        <div class="getcited-section getcited-full-audit-teaser">
            <div class="getcited-locked-feature">
                <span class="dashicons dashicons-lock"></span>
                <h3><?php esc_html_e( 'Full Site Audit', 'getcited' ); ?></h3>
                <p>
                    <?php
                    /* translators: %d: number of published posts */
                    printf(
                        esc_html__( 'Free users can analyze their 5 most recent posts. Upgrade to Pro to audit all %d posts with detailed recommendations.', 'getcited' ),
                        absint( wp_count_posts( 'post' )->publish )
                    ); ?>
                </p>
                <button type="button" class="button button-primary getcited-join-waitlist">
                    <?php esc_html_e( 'Join Pro Waitlist', 'getcited' ); ?>
                </button>
            </div>
        </div>

        <!-- Analysis Results Area -->
        <div class="getcited-section getcited-analysis-results" style="display: none;">
            <h2><?php esc_html_e( 'Analysis Results', 'getcited' ); ?></h2>
            <div class="getcited-results-content">
                <!-- Filled by JavaScript -->
            </div>
        </div>

        <!-- Scoring Rubric -->
        <div class="getcited-section getcited-rubric">
            <h2><?php esc_html_e( 'Scoring Rubric', 'getcited' ); ?></h2>
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
<?php
} )();

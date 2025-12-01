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
                    <div class="getcited-health-item <?php echo esc_attr( $status_class ); ?>">
                        <span class="health-icon"></span>
                        <span class="health-label"><?php echo esc_html( $label ); ?></span>
                        <span class="health-message"><?php echo esc_html( $check['message'] ); ?></span>
                    </div>
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

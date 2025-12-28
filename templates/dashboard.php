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
$stats            = GetCited_Dashboard::instance()->get_stats();
$health           = GetCited_Health_Check::instance();
$pro_teaser       = GetCited_Pro_Teaser::instance();
$visibility_score = $stats['visibility_score'];
$llms_activity    = $stats['llms_activity'];

// Get component data for breakdown cards.
$labels        = GetCited_Visibility_Score::get_component_labels();
$max_points    = GetCited_Visibility_Score::get_max_points();
$links         = GetCited_Visibility_Score::get_component_links();
$icons         = GetCited_Visibility_Score::get_component_icons();
$schema_source = GetCited_Visibility_Score::get_schema_source();

// Calculate days since last post for freshness display.
$recent_posts = get_posts( array(
	'numberposts' => 1,
	'post_status' => 'publish',
	'post_type'   => 'post',
) );
$freshness_text = __( 'No posts', 'getcited' );
if ( ! empty( $recent_posts ) ) {
	$days_ago = floor( ( time() - strtotime( $recent_posts[0]->post_date ) ) / DAY_IN_SECONDS );
	if ( $days_ago === 0 ) {
		$freshness_text = __( 'Today', 'getcited' );
	} elseif ( $days_ago === 1 ) {
		$freshness_text = __( '1 day ago', 'getcited' );
	} else {
		/* translators: %d: number of days */
		$freshness_text = sprintf( __( '%d days ago', 'getcited' ), $days_ago );
	}
}

// Get current tip for display.
$current_tip = GetCited_Dashboard::get_current_tip();
?>

<div class="wrap getcited-wrap">
	<h1><?php echo esc_html( apply_filters( 'getcited_brand_name', 'GetCited' ) ); ?></h1>
	<p class="getcited-tagline"><?php esc_html_e( 'Your competitors are still optimizing for Google. You\'re set up to be cited by ChatGPT, Gemini, and Grok.', 'getcited' ); ?></p>

	<div class="getcited-dashboard">

		<!-- AI Visibility Score Hero (Full Width) -->
		<div class="getcited-section getcited-visibility-score-section">
			<div class="visibility-score-container">
				<div class="visibility-score-info">
					<h2><?php esc_html_e( 'AI Visibility Score', 'getcited' ); ?></h2>
					<p class="tier-label tier-<?php echo esc_attr( $visibility_score['tier']['class'] ); ?>">
						<?php echo esc_html( $visibility_score['tier']['label'] ); ?>
					</p>
					<?php if ( ! empty( $visibility_score['recommendations'] ) ) : ?>
					<p class="score-recommendation">
						<?php echo esc_html( $visibility_score['recommendations'][0] ); ?>
					</p>
					<?php endif; ?>
					<button type="button" class="button button-primary getcited-refresh-score">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Refresh', 'getcited' ); ?>
					</button>
				</div>
				<?php
				$score_class = '';
				$total = $visibility_score['total'];
				if ( $total >= 80 ) {
					$score_class = 'score-high';
				} elseif ( $total >= 51 ) {
					$score_class = 'score-medium';
				} else {
					$score_class = 'score-low';
				}
				?>
				<div class="getcited-score-display large circular <?php echo esc_attr( $score_class ); ?>">
					<span class="score"><?php echo esc_html( $total ); ?></span>
					<span class="max">/100</span>
				</div>
			</div>
		</div>

		<!-- Score Components -->
		<div class="getcited-section getcited-score-components">
			<h2><?php esc_html_e( 'Score Components', 'getcited' ); ?></h2>
			<p class="score-components-explainer"><?php esc_html_e( 'Based on crawler access, AI visibility health, schema signals, content structure, and freshness.', 'getcited' ); ?></p>
			<div class="getcited-breakdown-cards">
			<?php foreach ( $visibility_score['breakdown'] as $key => $score ) :
				$max        = $max_points[ $key ];
				$is_perfect = ( $score === $max );
				$link       = $links[ $key ];
				$icon       = $icons[ $key ];
				$label      = $labels[ $key ];
			?>
			<?php if ( $link ) : ?>
			<a href="<?php echo esc_url( $link ); ?>" class="getcited-breakdown-card" data-key="<?php echo esc_attr( $key ); ?>">
			<?php else : ?>
			<div class="getcited-breakdown-card card-info-only" data-key="<?php echo esc_attr( $key ); ?>">
			<?php endif; ?>
				<div class="card-icon">
					<span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
				</div>
				<div class="card-label"><?php echo esc_html( $label ); ?></div>
				<div class="card-score">
					<?php echo esc_html( $score ); ?><span class="score-max">/<?php echo esc_html( $max ); ?></span>
				</div>
				<?php if ( $is_perfect ) : ?>
				<div class="card-status status-complete">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php
					if ( 'schema' === $key && $schema_source ) {
						/* translators: %s: Plugin name like "Yoast SEO" or "HeyTC SEO" */
						printf( esc_html__( 'via %s', 'getcited' ), esc_html( $schema_source['name'] ) );
					} else {
						esc_html_e( 'Complete', 'getcited' );
					}
					?>
				</div>
				<?php elseif ( $key === 'freshness' ) : ?>
				<div class="card-meta">
					<?php echo esc_html( $freshness_text ); ?>
				</div>
				<?php else : ?>
				<div class="card-action">
					<?php esc_html_e( 'Improve', 'getcited' ); ?> →
				</div>
				<?php endif; ?>
			<?php if ( $link ) : ?>
			</a>
			<?php else : ?>
			</div>
			<?php endif; ?>
			<?php endforeach; ?>
			</div>
		</div>

		<!-- AI Visibility Tip (Inline Bar) -->
		<div class="getcited-tip-bar">
			<span class="dashicons dashicons-lightbulb"></span>
			<span class="tip-text"><strong><?php echo esc_html( $current_tip['title'] ); ?>:</strong> <?php echo wp_kses( $current_tip['content'], array( 'a' => array( 'href' => array() ) ) ); ?></span>
			<a href="#" class="getcited-next-tip"><?php esc_html_e( 'Next', 'getcited' ); ?> →</a>
		</div>

		<!-- AI Visibility Activity + Health Status (Two Column) -->
		<div class="getcited-dashboard-actions-row">
			<!-- AI Visibility Activity -->
			<div class="getcited-section getcited-llms-activity-section">
				<h2><?php esc_html_e( 'AI Visibility Activity', 'getcited' ); ?></h2>

				<?php if ( ! $llms_activity['enabled'] ) : ?>
					<div class="getcited-activity-disabled">
						<p><?php esc_html_e( 'Request logging is disabled.', 'getcited' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=getcited-settings' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Enable in Settings', 'getcited' ); ?>
						</a>
					</div>

				<?php elseif ( empty( $llms_activity['recent'] ) ) : ?>
					<div class="getcited-activity-empty">
						<p><?php esc_html_e( 'No requests logged yet.', 'getcited' ); ?></p>
						<p class="description">
							<?php esc_html_e( 'AI crawlers visit on their own schedule. Your site is ready!', 'getcited' ); ?>
						</p>
					</div>

				<?php else : ?>
					<?php $stats_data = $llms_activity['stats']; ?>
					<div class="getcited-activity-content">
						<div class="activity-stats-column">
							<div class="stat-card">
								<span class="stat-number"><?php echo esc_html( $stats_data['total'] ); ?></span>
								<span class="stat-label"><?php esc_html_e( 'Requests', 'getcited' ); ?></span>
							</div>
							<div class="stat-card">
								<span class="stat-number"><?php echo esc_html( $stats_data['unique_bots'] ); ?></span>
								<span class="stat-label"><?php esc_html_e( 'Unique Bots', 'getcited' ); ?></span>
							</div>
						</div>
						<div class="activity-list">
							<?php
							$display_count = min( 5, count( $llms_activity['recent'] ) );
							for ( $i = 0; $i < $display_count; $i++ ) :
								$request = $llms_activity['recent'][ $i ];
								$time    = strtotime( $request->request_time );
							?>
							<div class="activity-item">
								<span class="activity-bot"><?php echo esc_html( $request->bot_name ); ?></span>
								<span class="activity-time"><?php echo esc_html( human_time_diff( $time, current_time( 'timestamp' ) ) ); ?> <?php esc_html_e( 'ago', 'getcited' ); ?></span>
							</div>
							<?php endfor; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<!-- Health Status (Compact) -->
			<div class="getcited-section getcited-health-section">
				<h2>
					<?php esc_html_e( 'Health Status', 'getcited' ); ?>
					<button type="button" class="button button-primary getcited-run-health-check">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'Run Check', 'getcited' ); ?>
					</button>
				</h2>
				<div class="getcited-health-summary">
					<?php
					$health_status = $stats['health'];
					$quick_checks  = array(
						'llms_txt'      => __( 'llms.txt', 'getcited' ),
						'robots_txt'    => __( 'robots.txt', 'getcited' ),
						'schema'        => __( 'Schema', 'getcited' ),
						'rewrite_rules' => __( 'Rewrites', 'getcited' ),
					);
					$check_links = array(
						'llms_txt'      => admin_url( 'admin.php?page=getcited-llms-txt' ),
						'robots_txt'    => admin_url( 'admin.php?page=getcited-crawlers' ),
						'schema'        => admin_url( 'admin.php?page=getcited-schema' ),
						'rewrite_rules' => admin_url( 'options-permalink.php' ),
					);

					// Add environment badge if staging/development detected.
					if ( isset( $health_status['environment'] ) && ! empty( $health_status['environment']['is_staging'] ) ) {
						$quick_checks['environment'] = __( 'Dev/Staging', 'getcited' );
						$check_links['environment']  = admin_url( 'options-reading.php' );
					}

					foreach ( $quick_checks as $key => $label ) :
						if ( ! isset( $health_status[ $key ] ) ) continue;
						$check = $health_status[ $key ];
						$icon  = $check['status'] === 'ok' ? '✓' : ( $check['status'] === 'warning' ? '!' : ( $check['status'] === 'info' ? 'ⓘ' : '✕' ) );
						$class = 'status-' . $check['status'];
						$link  = $check_links[ $key ] ?? '#';
					?>
					<a href="<?php echo esc_url( $link ); ?>" class="health-status-item <?php echo esc_attr( $class ); ?>" title="<?php echo esc_attr( $check['message'] ?? '' ); ?>">
						<span class="status-icon"><?php echo esc_html( $icon ); ?></span>
						<span class="status-label"><?php echo esc_html( $label ); ?></span>
					</a>
					<?php endforeach; ?>
				</div>
				<?php
				$issue_data = array();
				foreach ( $quick_checks as $key => $label ) {
					if ( isset( $health_status[ $key ] ) && ! in_array( $health_status[ $key ]['status'], array( 'ok', 'info' ), true ) ) {
						$issue_data[] = array(
							'message' => ! empty( $health_status[ $key ]['message'] ) ? $health_status[ $key ]['message'] : $label,
							'status'  => $health_status[ $key ]['status'],
						);
					}
				}
				if ( empty( $issue_data ) ) :
				?>
				<p class="description"><?php esc_html_e( 'All systems healthy. Your site is ready for AI crawlers.', 'getcited' ); ?></p>
				<?php else : ?>
				<div class="getcited-health-issues">
					<?php foreach ( $issue_data as $issue ) :
						$issue_icon = $issue['status'] === 'error' ? '✕' : '!';
						$issue_class = 'status-' . $issue['status'];
					?>
					<div class="getcited-health-issue <?php echo esc_attr( $issue_class ); ?>">
						<span class="issue-icon"><?php echo esc_html( $issue_icon ); ?></span>
						<span class="issue-text"><?php echo esc_html( $issue['message'] ); ?></span>
					</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Plugin Compatibility Notices (only shows when relevant) -->
		<?php
		$plugin_checks = array( 'redirection', 'caching_plugins', 'security_plugins', 'jetpack' );
		$notices       = array();
		foreach ( $plugin_checks as $key ) {
			if ( isset( $health_status[ $key ] ) && in_array( $health_status[ $key ]['status'], array( 'warning', 'info' ), true ) ) {
				$notices[ $key ] = $health_status[ $key ];
			}
		}
		if ( ! empty( $notices ) ) :
		?>
		<div class="getcited-plugin-notices">
			<?php foreach ( $notices as $key => $notice ) :
				$notice_class = $notice['status'] === 'warning' ? 'notice-warning' : 'notice-info';
			?>
			<div class="getcited-notice <?php echo esc_attr( $notice_class ); ?>">
				<strong><?php echo esc_html( $notice['message'] ); ?></strong>
				<?php if ( ! empty( $notice['details'] ) ) : ?>
				<p><?php echo wp_kses( $notice['details'], array( 'code' => array(), 'strong' => array() ) ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $notice['action_url'] ) ) : ?>
				<a href="<?php echo esc_url( $notice['action_url'] ); ?>" class="button button-small">
					<?php echo esc_html( $notice['action_label'] ?? __( 'View Details', 'getcited' ) ); ?>
				</a>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<!-- Pro Features Section -->
		<?php $pro_teaser->render_dashboard_teasers(); ?>

		<!-- Footer Attribution -->
		<p class="getcited-footer-attribution">
			<?php esc_html_e( 'Built by Malcolm at HeyTC', 'getcited' ); ?>
			<span class="getcited-version">v<?php echo esc_html( GETCITED_VERSION ); ?></span>
		</p>

	</div>
</div>
<?php
} )();

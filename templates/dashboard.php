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

// Get current tip for display (v1.6.14).
$current_tip = GetCited_Dashboard::get_current_tip();
$tip_index   = GetCited_Dashboard::get_current_tip_index();
$tips_count  = count( GetCited_Dashboard::get_tips() );
?>

<div class="wrap getcited-wrap">
	<h1><?php echo esc_html( apply_filters( 'getcited_brand_name', 'GetCited' ) ); ?></h1>

	<div class="getcited-dashboard">

		<!-- Top Row: Score + Tips -->
		<div class="getcited-dashboard-top-row">
			<!-- AI Visibility Score Hero -->
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
						<button type="button" class="button getcited-refresh-score">
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

			<!-- AI Visibility Tips -->
			<div class="getcited-section getcited-tips-section">
				<div class="getcited-tip-container">
					<h2>
						<span class="dashicons dashicons-lightbulb"></span>
						<?php esc_html_e( 'AI Visibility Tip', 'getcited' ); ?>
					</h2>
					<h3 class="getcited-tip-title"><?php echo esc_html( $current_tip['title'] ); ?></h3>
					<p class="getcited-tip-content"><?php echo esc_html( $current_tip['content'] ); ?></p>
					<div class="getcited-tip-footer">
						<span class="getcited-tip-counter">
							<?php
							/* translators: 1: current tip number, 2: total tips */
							printf( esc_html__( 'Tip %1$d of %2$d', 'getcited' ), $tip_index + 1, $tips_count );
							?>
						</span>
						<a href="#" class="getcited-next-tip">
							<?php esc_html_e( 'Next tip', 'getcited' ); ?>
							<span class="dashicons dashicons-arrow-right-alt2"></span>
						</a>
					</div>
				</div>
			</div>
		</div>

		<!-- Score Breakdown Cards -->
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

		<!-- Two Column Grid: Activity + Health Check -->
		<div class="getcited-dashboard-grid">
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
							<?php esc_html_e( 'AI crawlers typically visit within 1-7 days. Check back soon!', 'getcited' ); ?>
						</p>
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
									<?php
									$display_count = min( 5, count( $llms_activity['recent'] ) );
									for ( $i = 0; $i < $display_count; $i++ ) :
										$request       = $llms_activity['recent'][ $i ];
										$category_info = GetCited_Request_Logger::get_category_display( $request->category );
										$time          = strtotime( $request->request_time );
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
												<span class="category-icon" aria-hidden="true"><?php echo wp_kses( $category_info['icon'], array() ); ?></span>
												<?php echo esc_html( $category_info['label'] ); ?>
											</span>
										</td>
									</tr>
									<?php endfor; ?>
								</tbody>
							</table>
						</div>
						<div class="activity-stats">
							<?php
							$stats_data = $llms_activity['stats'];
							?>
							<p>
								<strong><?php echo esc_html( $stats_data['total'] ); ?></strong>
								<?php esc_html_e( 'requests', 'getcited' ); ?> •
								<strong><?php echo esc_html( $stats_data['unique_bots'] ); ?></strong>
								<?php esc_html_e( 'unique bots', 'getcited' ); ?>
							</p>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<!-- Health Check Summary -->
			<div class="getcited-section getcited-health-section">
				<h2>
					<?php esc_html_e( 'Health Check', 'getcited' ); ?>
					<button type="button" class="button getcited-run-health-check">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'Run Check', 'getcited' ); ?>
					</button>
				</h2>

				<div class="getcited-health-summary">
					<?php
					$health_status = $stats['health'];
					$checks        = array(
						'llms_txt'      => __( 'llms.txt', 'getcited' ),
						'robots_txt'    => __( 'robots.txt', 'getcited' ),
						'schema'        => __( 'Schema', 'getcited' ),
						'rewrite_rules' => __( 'Rewrites', 'getcited' ),
					);

					// Link targets for each health check badge.
					$badge_links = array(
						'llms_txt'      => admin_url( 'admin.php?page=getcited-llms-txt' ),
						'robots_txt'    => admin_url( 'admin.php?page=getcited-crawlers' ),
						'schema'        => admin_url( 'admin.php?page=getcited-schema' ),
						'rewrite_rules' => admin_url( 'options-permalink.php' ),
					);

					// Tooltips explaining each check (v1.5.4).
					$badge_tooltips = array(
						'llms_txt'      => __( 'Your llms.txt file for AI discoverability', 'getcited' ),
						'robots_txt'    => __( 'AI crawler rules in robots.txt', 'getcited' ),
						'schema'        => __( 'JSON-LD structured data for AI understanding', 'getcited' ),
						'rewrite_rules' => __( 'WordPress rewrite rules for /llms.txt URL. If broken, visit Settings → Permalinks and click Save.', 'getcited' ),
					);

					foreach ( $checks as $key => $label ) :
						if ( ! isset( $health_status[ $key ] ) ) {
							continue;
						}
						$check       = $health_status[ $key ];
						$badge_class = 'badge-' . $check['status'];
						$icon        = $check['status'] === 'ok' ? 'dashicons-yes' : ( $check['status'] === 'warning' ? 'dashicons-warning' : 'dashicons-no' );
						$link        = $badge_links[ $key ] ?? '#';
						$tooltip     = $badge_tooltips[ $key ] ?? '';
					?>
					<a href="<?php echo esc_url( $link ); ?>" class="getcited-health-badge <?php echo esc_attr( $badge_class ); ?>" title="<?php echo esc_attr( $tooltip ); ?>">
						<span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
						<?php echo esc_html( $label ); ?>
					</a>
					<?php endforeach; ?>
				</div>

				<p class="description">
					<?php
					$all_ok = true;
					foreach ( $health_status as $check ) {
						if ( isset( $check['status'] ) && $check['status'] !== 'ok' ) {
							$all_ok = false;
							break;
						}
					}
					if ( $all_ok ) {
						esc_html_e( 'All systems healthy. Your site is ready for AI crawlers.', 'getcited' );
					} else {
						esc_html_e( 'Some items need attention. Click "Run Check" for details.', 'getcited' );
					}
					?>
				</p>

				<!-- Hidden detailed results for AJAX refresh -->
				<div class="getcited-health-results" style="display: none;">
					<?php
					$all_checks = array(
						'llms_txt'      => __( 'llms.txt', 'getcited' ),
						'robots_txt'    => __( 'robots.txt', 'getcited' ),
						'schema'        => __( 'Schema', 'getcited' ),
						'rewrite_rules' => __( 'Rewrite Rules', 'getcited' ),
						'crawler_list'  => __( 'Crawler List', 'getcited' ),
					);

					foreach ( $all_checks as $key => $label ) :
						if ( ! isset( $health_status[ $key ] ) ) {
							continue;
						}
						$check        = $health_status[ $key ];
						$status_class = $health->get_status_class( $check['status'] );
						$has_details  = ! empty( $check['details'] ) || ! empty( $check['action_type'] );
					$details_id = 'health-details-' . esc_attr( $key );
					?>
						<div class="getcited-health-item <?php echo esc_attr( $status_class ); ?><?php echo $has_details ? ' has-details' : ''; ?>" data-check="<?php echo esc_attr( $key ); ?>">
							<div class="health-item-header">
								<span class="health-icon" aria-hidden="true"></span>
								<span class="health-label"><?php echo esc_html( $label ); ?></span>
								<span class="health-message"><?php echo esc_html( $check['message'] ); ?></span>

								<?php if ( $has_details ) : ?>
									<?php /* translators: %s: health check label */ ?>
									<button type="button" class="getcited-health-expand" aria-expanded="false" aria-controls="<?php echo esc_attr( $details_id ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Show details for %s', 'getcited' ), $label ) ); ?>">
										<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
									</button>
								<?php endif; ?>
							</div>

						<?php if ( $has_details ) : ?>
							<div class="getcited-health-details" id="<?php echo esc_attr( $details_id ); ?>" style="display: none;">
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

											<button type="button" class="button getcited-toggle-rules">
												<?php esc_html_e( 'Preview Rules', 'getcited' ); ?>
											</button>
											<div class="getcited-rules-preview" style="display: none;">
												<pre class="rules-code"><?php echo esc_html( $check['rules'] ); ?></pre>
											</div>

										<?php elseif ( $check['action_type'] === 'copy_rules' && ! empty( $check['rules'] ) ) : ?>
											<div class="getcited-rules-preview">
												<pre class="rules-code"><?php echo esc_html( $check['rules'] ); ?></pre>
												<button type="button" class="button getcited-copy-rules" data-rules="<?php echo esc_attr( $check['rules'] ); ?>">
													<span class="dashicons dashicons-clipboard"></span>
													<?php esc_html_e( 'Copy Rules', 'getcited' ); ?>
												</button>
											</div>

										<?php elseif ( ! empty( $check['action_url'] ) && ! empty( $check['action_label'] ) ) : ?>
											<a href="<?php echo esc_url( $check['action_url'] ); ?>" class="button">
												<?php echo esc_html( $check['action_label'] ); ?>
											</a>
										<?php endif; ?>

									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<!-- Pro Features Section -->
		<?php $pro_teaser->render_dashboard_teasers(); ?>

		<!-- Footer Attribution -->
		<p class="getcited-footer-attribution">
			<?php esc_html_e( 'Built by Malcolm at HeyTC', 'getcited' ); ?>
			<span class="getcited-version">v<?php echo esc_html( GETCITED_VERSION ); ?></span>
		</p>

	</div>

	<?php $pro_teaser->render_sample_modal(); ?>
</div>
<?php
} )();

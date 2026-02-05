<?php
/**
 * Schema settings template
 *
 * @package GetCited
 * @since 1.0.0
 * @updated 1.4.0 - Detection status UI
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

( function() {
$settings    = GetCited_Settings::instance();
$schema      = GetCited_Schema::instance();
$detector    = GetCited_Schema_Detector::instance();

$enabled       = $settings->get( 'schema_enabled' );
$force_enabled = $settings->get( 'schema_force_enabled' );
$schema_types  = $settings->get( 'schema_types' );
$organization  = $settings->get( 'organization' );

// Get detection status.
$detection     = $detector->get_detection_status();
$status_info   = $detector->get_status_message();
$last_scan_ago = $detector->get_last_scan_ago();

// Count active types.
$active_count = 0;
foreach ( $schema_types as $type => $active ) {
	if ( $active ) {
		$active_count++;
	}
}

// Determine if schema is effectively active.
$is_active = $enabled && ( ! $detection['should_disable'] || $force_enabled );
?>

<div class="wrap getcited-wrap">
	<h1><?php esc_html_e( 'Schema Settings', 'getcited' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Schema markup helps search engines and AI systems better understand your content, improving visibility in search results.', 'getcited' ); ?>
	</p>

	<div class="getcited-schema-page">


		<!-- Detection Status Section -->
		<div class="getcited-section getcited-detection-status <?php echo esc_attr( $status_info['status'] ); ?>">
			<h2><?php esc_html_e( 'Schema Detection', 'getcited' ); ?></h2>

			<div class="getcited-status-indicator">
				<?php if ( 'active' === $status_info['status'] || 'handled' === $status_info['status'] ) : ?>
					<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
				<?php elseif ( 'none' === $status_info['status'] ) : ?>
					<span class="dashicons dashicons-warning" style="color: #f0b849;"></span>
				<?php else : ?>
					<span class="dashicons dashicons-info" style="color: #72aee6;"></span>
				<?php endif; ?>
				<span class="status-message"><?php echo esc_html( $status_info['message'] ); ?></span>
			</div>

			<div class="getcited-detection-actions" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--getcited-space-sm);">
				<p class="getcited-last-scan" style="margin: 0;">
					<?php
					printf(
						/* translators: %s: time since last scan (e.g., "2 hours ago") */
						esc_html__( 'Last checked: %s', 'getcited' ),
						'<span class="last-scan-time">' . esc_html( $last_scan_ago ) . '</span>'
					);
					?>
					<button type="button" class="button button-primary getcited-rescan-schema">
						<span class="dashicons dashicons-update" style="vertical-align: text-bottom; margin-right: 4px;"></span>
						<?php esc_html_e( 'Refresh', 'getcited' ); ?>
					</button>
				</p>
				<a href="<?php echo esc_url( 'https://search.google.com/test/rich-results?url=' . rawurlencode( home_url() ) ); ?>"
				   target="_blank"
				   class="button button-primary"
				   style="display: inline-flex; align-items: center; text-decoration: none;">
					<span class="dashicons dashicons-external" style="vertical-align: text-bottom; margin-right: 4px;"></span>
					<?php esc_html_e( 'Test with Google', 'getcited' ); ?>
				</a>
			</div>

			<?php if ( ! empty( $detection['json_ld_types'] ) ) : ?>
				<details class="getcited-detected-types">
					<summary><?php esc_html_e( 'Detected schema types', 'getcited' ); ?></summary>
					<ul>
						<?php foreach ( $detection['json_ld_types'] as $type ) : ?>
							<li><code><?php echo esc_html( $type ); ?></code></li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
		</div>

		<?php if ( $detection['should_disable'] ) : ?>
			<!-- Advanced Options (collapsed by default when plugin handles schema) -->
			<div class="getcited-section getcited-advanced-options getcited-collapsible" data-collapsed="true">
				<h2 class="getcited-collapsible-header">
					<?php esc_html_e( 'Advanced Options', 'getcited' ); ?>
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</h2>
				<p class="description getcited-advanced-notice">
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'Your SEO plugin is already handling schema. Only enable GetCited schema if you need additional schema types not provided by your plugin.', 'getcited' ); ?>
				</p>
				<div class="getcited-collapsible-content" style="display: none;">
		<?php else : ?>
			<!-- Show settings directly when no plugin handles schema -->
		<?php endif; ?>

		<!-- Schema Settings -->
		<div class="getcited-section getcited-schema-settings">
			<h2><?php esc_html_e( 'Schema Output', 'getcited' ); ?></h2>

			<div class="getcited-schema-options-compact">
				<label class="getcited-checkbox-custom <?php echo $enabled ? 'is-checked' : ''; ?>">
					<input type="checkbox"
						   name="schema_enabled"
						   id="schema_enabled"
						   value="1"
						   <?php checked( $enabled ); ?>>
					<span class="check-box"></span>
					<span class="check-content">
						<strong><?php esc_html_e( 'Enable GetCited Schema', 'getcited' ); ?></strong>
						<span class="check-description"><?php esc_html_e( 'Output structured data in page headers.', 'getcited' ); ?></span>
					</span>
				</label>
			</div>

			<?php if ( $detection['should_disable'] ) : ?>
				<!-- Warning when enabling schema while plugin handles it -->
				<div class="getcited-schema-conflict-warning" style="margin-top: var(--getcited-space-md); padding: 12px 16px; background: #fcf0f0; border: 1px solid #d63638; border-radius: 4px; <?php echo ! $enabled ? 'display: none;' : ''; ?>">
					<p style="margin: 0; color: #8a1f1f;">
						<span class="dashicons dashicons-warning" style="color: #d63638; margin-right: 4px;"></span>
						<strong><?php esc_html_e( 'Not recommended:', 'getcited' ); ?></strong>
						<?php
						$detected_name = ! empty( $detection['plugins'][0]['name'] )
							? $detection['plugins'][0]['name']
							: __( 'Another plugin', 'getcited' );
						printf(
							/* translators: %s: detected plugin name */
							esc_html__( '%s is already outputting schema. Enabling GetCited schema may cause duplicate markup and validation errors.', 'getcited' ),
							'<strong>' . esc_html( $detected_name ) . '</strong>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<!-- Schema Types (shown when enabled) -->
			<div class="getcited-schema-types-wrapper" style="margin-top: var(--getcited-space-lg); <?php echo ! $enabled ? 'display: none;' : ''; ?>">
				<h3 style="margin: 0 0 var(--getcited-space-sm) 0; font-size: 14px;"><?php esc_html_e( 'Schema Types', 'getcited' ); ?></h3>

				<div class="getcited-schema-options-grid">
					<label class="getcited-checkbox-custom <?php echo $schema_types['organization'] ? 'is-checked' : ''; ?>">
						<input type="checkbox"
							   name="schema_types[organization]"
							   value="1"
							   <?php checked( $schema_types['organization'] ); ?>>
						<span class="check-box"></span>
						<span class="check-content">
							<strong><?php esc_html_e( 'Organization', 'getcited' ); ?></strong>
							<span class="check-description"><?php esc_html_e( 'Site/company identity', 'getcited' ); ?></span>
						</span>
					</label>

					<label class="getcited-checkbox-custom <?php echo $schema_types['article'] ? 'is-checked' : ''; ?>">
						<input type="checkbox"
							   name="schema_types[article]"
							   value="1"
							   <?php checked( $schema_types['article'] ); ?>>
						<span class="check-box"></span>
						<span class="check-content">
							<strong><?php esc_html_e( 'Article', 'getcited' ); ?></strong>
							<span class="check-description"><?php esc_html_e( 'Posts & articles', 'getcited' ); ?></span>
						</span>
					</label>

					<label class="getcited-checkbox-custom <?php echo $schema_types['author'] ? 'is-checked' : ''; ?>">
						<input type="checkbox"
							   name="schema_types[author]"
							   value="1"
							   <?php checked( $schema_types['author'] ); ?>>
						<span class="check-box"></span>
						<span class="check-content">
							<strong><?php esc_html_e( 'Author', 'getcited' ); ?></strong>
							<span class="check-description"><?php esc_html_e( 'Author attribution', 'getcited' ); ?></span>
						</span>
					</label>

					<label class="getcited-checkbox-custom <?php echo $schema_types['faq'] ? 'is-checked' : ''; ?>">
						<input type="checkbox"
							   name="schema_types[faq]"
							   value="1"
							   <?php checked( $schema_types['faq'] ); ?>>
						<span class="check-box"></span>
						<span class="check-content">
							<strong><?php esc_html_e( 'FAQ', 'getcited' ); ?></strong>
							<span class="check-description"><?php esc_html_e( 'Q&A format', 'getcited' ); ?></span>
						</span>
					</label>
				</div>

				<p class="description" style="margin-top: var(--getcited-space-sm);">
					<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>">
						<?php esc_html_e( 'Edit author profiles', 'getcited' ); ?>
					</a>
					<?php esc_html_e( 'to add LinkedIn, expertise, and job titles for enhanced Author schema.', 'getcited' ); ?>
				</p>
			</div>
		</div>

		<?php if ( $detection['should_disable'] ) : ?>
				</div><!-- .getcited-collapsible-content -->
			</div><!-- .getcited-advanced-options -->
		<?php endif; ?>

		<!-- Organization Details (Collapsible) -->
		<div class="getcited-section getcited-organization getcited-collapsible" data-collapsed="true">
			<h2 class="getcited-collapsible-header">
				<?php esc_html_e( 'Organization Details', 'getcited' ); ?>
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Powers your Organization schema. Add social profiles to help AI systems verify and trust your brand.', 'getcited' ); ?>
			</p>
			<div class="getcited-collapsible-content" style="display: none;">
				<?php
				$social_urls     = $organization['social_urls'] ?? array();
				$has_social_urls = ! empty( array_filter( $social_urls ) );
				$sameas_urls     = $organization['sameas_urls'] ?? array();
				$has_sameas      = ! empty( $organization['linkedin_company'] ) ||
				                   ! empty( $organization['wikipedia'] ) ||
				                   ! empty( $organization['crunchbase'] ) ||
				                   ! empty( array_filter( $sameas_urls ) );
				?>

				<!-- Top Row: Organization Name + Logo URL -->
				<div class="getcited-org-two-column" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--getcited-space-lg); align-items: start;">
					<div class="form-row" style="margin-bottom: 0;">
						<label for="org_name">
							<?php esc_html_e( 'Organization Name', 'getcited' ); ?>
							<?php if ( ! empty( $organization['name'] ) ) : ?>
								<span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
							<?php endif; ?>
						</label>
						<input type="text"
							   name="organization[name]"
							   id="org_name"
							   value="<?php echo esc_attr( $organization['name'] ); ?>"
							   placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
							   style="width: 100%;">
					</div>

					<div class="form-row" style="margin-bottom: 0;">
						<label for="org_logo">
							<?php esc_html_e( 'Logo URL', 'getcited' ); ?>
							<?php if ( ! empty( $organization['logo_url'] ) ) : ?>
								<span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
							<?php endif; ?>
						</label>
						<div class="input-with-button">
							<input type="url"
								   name="organization[logo_url]"
								   id="org_logo"
								   value="<?php echo esc_url( $organization['logo_url'] ); ?>"
								   placeholder="https://example.com/logo.png"
								   style="flex: 1;">
							<button type="button" class="button button-primary getcited-upload-logo">
								<?php esc_html_e( 'Upload', 'getcited' ); ?>
							</button>
						</div>
						<p class="description" style="margin-top: 4px; margin-bottom: 0;">
							<?php esc_html_e( 'Recommended: 112x112px minimum, PNG or WebP', 'getcited' ); ?>
						</p>
					</div>
				</div>

				<!-- Divider -->
				<hr style="margin: var(--getcited-space-lg) 0; border: none; border-top: 1px solid var(--getcited-gray-200);">

				<!-- Bottom Row: Social Profiles + sameAs Links -->
				<div class="getcited-org-two-column" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--getcited-space-lg); align-items: start;">
					<!-- Left Column: Social Profiles -->
					<div class="getcited-org-left" style="display: flex; flex-direction: column;">
						<div class="form-row" style="margin-bottom: 0; flex: 1;">
							<label>
								<?php esc_html_e( 'Social Profiles', 'getcited' ); ?>
								<?php if ( $has_social_urls ) : ?>
									<span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
								<?php endif; ?>
							</label>
							<p class="description" style="margin: 0 0 var(--getcited-space-sm) 0;">
								<?php esc_html_e( 'Link your official social media accounts.', 'getcited' ); ?>
							</p>
							<div class="getcited-social-urls">
								<?php
								$placeholders = array(
									'https://facebook.com/yourpage',
									'https://youtube.com/@yourchannel',
									'https://x.com/yourhandle (formerly Twitter)',
								);
								for ( $i = 0; $i < max( 3, count( $social_urls ) ); $i++ ) :
									$value       = $social_urls[ $i ] ?? '';
									$placeholder = $placeholders[ $i ] ?? 'https://...';
									?>
									<input type="url"
										   name="organization[social_urls][]"
										   value="<?php echo esc_url( $value ); ?>"
										   placeholder="<?php echo esc_attr( $placeholder ); ?>">
								<?php endfor; ?>
							</div>
						</div>
						<button type="button" class="button button-primary getcited-add-social" style="margin-top: var(--getcited-space-sm);">
							<?php esc_html_e( '+ Add Another (Instagram, TikTok, etc.)', 'getcited' ); ?>
						</button>
					</div>

					<!-- Right Column: sameAs Links -->
					<div class="getcited-org-right" style="display: flex; flex-direction: column;">
						<div class="form-row" style="margin-bottom: 0; flex: 1;">
							<label>
								<?php esc_html_e( 'sameAs Links', 'getcited' ); ?>
								<?php if ( $has_sameas ) : ?>
									<span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
								<?php endif; ?>
							</label>
							<p class="description" style="margin: 0 0 var(--getcited-space-sm) 0;">
								<?php esc_html_e( 'Help AI systems identify your organization.', 'getcited' ); ?>
							</p>
							<div class="getcited-sameas-urls">
								<input type="url"
									   name="organization[linkedin_company]"
									   id="org_linkedin_company"
									   value="<?php echo esc_url( $organization['linkedin_company'] ?? '' ); ?>"
									   placeholder="https://linkedin.com/company/...">
								<input type="url"
									   name="organization[wikipedia]"
									   id="org_wikipedia"
									   value="<?php echo esc_url( $organization['wikipedia'] ?? '' ); ?>"
									   placeholder="https://wikipedia.org/wiki/...">
								<input type="url"
									   name="organization[crunchbase]"
									   id="org_crunchbase"
									   value="<?php echo esc_url( $organization['crunchbase'] ?? '' ); ?>"
									   placeholder="https://crunchbase.com/organization/...">
								<?php
								for ( $i = 0; $i < count( $sameas_urls ); $i++ ) :
									$value = $sameas_urls[ $i ] ?? '';
									if ( ! empty( $value ) ) :
										?>
										<input type="url"
											   name="organization[sameas_urls][]"
											   value="<?php echo esc_url( $value ); ?>"
											   placeholder="https://...">
									<?php endif;
								endfor;
								?>
							</div>
						</div>
						<button type="button" class="button button-primary getcited-add-sameas" style="margin-top: var(--getcited-space-sm);">
							<?php esc_html_e( '+ Add Another (Yelp, Bloomberg, etc.)', 'getcited' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Schema Preview (Collapsible) -->
		<div class="getcited-section getcited-schema-preview getcited-collapsible" data-collapsed="true">
			<h2 class="getcited-collapsible-header">
				<?php esc_html_e( 'Schema Preview', 'getcited' ); ?>
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Organization schema as it will appear on your front page:', 'getcited' ); ?>
			</p>
			<div class="getcited-collapsible-content" style="display: none;">
				<pre class="getcited-preview-code"><?php
					$org_schema = $schema->get_preview( 'organization' );
					echo esc_html( wp_json_encode( $org_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
				?></pre>
			</div>
		</div>

		<!-- Save Button -->
		<div class="getcited-section getcited-actions">
			<button type="button" class="button button-primary getcited-save-schema">
				<?php esc_html_e( 'Save Changes', 'getcited' ); ?>
			</button>
			<span class="getcited-save-status"></span>
		</div>

	</div>
</div>
<?php
} )();

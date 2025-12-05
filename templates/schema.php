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
$pro_teaser  = GetCited_Pro_Teaser::instance();

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
		<?php esc_html_e( 'Schema markup helps AI systems understand your content structure. JSON-LD is output in your page headers.', 'getcited' ); ?>
	</p>

	<div class="getcited-schema-page">

		<!-- Pro Teaser Banner -->
		<?php $pro_teaser->render_page_teaser( 'schema' ); ?>

		<!-- Detection Status Section -->
		<div class="getcited-section getcited-detection-status <?php echo esc_attr( $status_info['status'] ); ?>">
			<h2><?php esc_html_e( 'Schema Status', 'getcited' ); ?></h2>

			<div class="getcited-status-indicator">
				<?php if ( 'active' === $status_info['status'] ) : ?>
					<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
				<?php else : ?>
					<span class="dashicons dashicons-warning" style="color: #f0b849;"></span>
				<?php endif; ?>
				<span class="status-message"><?php echo esc_html( $status_info['message'] ); ?></span>
			</div>

			<p class="getcited-last-scan">
				<?php
				printf(
					/* translators: %s: time since last scan (e.g., "2 hours ago") */
					esc_html__( 'Last checked: %s', 'getcited' ),
					'<span class="last-scan-time">' . esc_html( $last_scan_ago ) . '</span>'
				);
				?>
				<button type="button" class="button getcited-rescan-schema">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Re-scan', 'getcited' ); ?>
				</button>
			</p>

			<?php if ( $detection['should_disable'] ) : ?>
				<!-- Force Enable Option -->
				<div class="getcited-force-enable">
					<label class="getcited-toggle-label">
						<input type="checkbox"
							   name="schema_force_enabled"
							   id="schema_force_enabled"
							   value="1"
							   <?php checked( $force_enabled ); ?>>
						<strong><?php esc_html_e( 'Enable GetCited schema anyway', 'getcited' ); ?></strong>
					</label>
					<p class="description getcited-warning-text">
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e( 'Warning: May create duplicate markup. Only enable if you know the detected source is not outputting the schema types you need.', 'getcited' ); ?>
					</p>
				</div>
			<?php endif; ?>

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

		<!-- Two-Column Settings Grid -->
		<div class="getcited-settings-grid">
			<!-- Left Column: Enable + Status -->
			<div class="getcited-section getcited-schema-toggle">
				<h2><?php esc_html_e( 'Schema Output', 'getcited' ); ?></h2>
				<label class="getcited-toggle-label">
					<input type="checkbox"
						   name="schema_enabled"
						   id="schema_enabled"
						   value="1"
						   <?php checked( $enabled ); ?>>
					<strong><?php esc_html_e( 'Enable JSON-LD', 'getcited' ); ?></strong>
				</label>
				<p class="description">
					<?php esc_html_e( 'Output structured data in page headers.', 'getcited' ); ?>
				</p>

				<?php if ( $is_active && $active_count > 0 ) : ?>
					<p class="getcited-file-status">
						<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
						<?php
						printf(
							/* translators: %d: number of active schema types */
							esc_html( _n( '%d type active', '%d types active', $active_count, 'getcited' ) ),
							esc_html( $active_count )
						);
						?>
					</p>
				<?php endif; ?>

				<div class="getcited-schema-test">
					<a href="https://search.google.com/test/rich-results?url=<?php echo urlencode( home_url() ); ?>"
					   target="_blank"
					   class="button">
						<span class="dashicons dashicons-external"></span>
						<?php esc_html_e( 'Test with Google', 'getcited' ); ?>
					</a>
				</div>
			</div>

			<!-- Right Column: Schema Types -->
			<div class="getcited-section getcited-schema-types">
				<h2><?php esc_html_e( 'Schema Types', 'getcited' ); ?></h2>

				<div class="getcited-schema-options-compact">
					<label class="getcited-checkbox-compact">
						<input type="checkbox"
							   name="schema_types[organization]"
							   value="1"
							   <?php checked( $schema_types['organization'] ); ?>>
						<span class="checkbox-label">
							<strong><?php esc_html_e( 'Organization', 'getcited' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Site/company identity', 'getcited' ); ?></span>
						</span>
					</label>

					<label class="getcited-checkbox-compact">
						<input type="checkbox"
							   name="schema_types[article]"
							   value="1"
							   <?php checked( $schema_types['article'] ); ?>>
						<span class="checkbox-label">
							<strong><?php esc_html_e( 'Article', 'getcited' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Posts & articles', 'getcited' ); ?></span>
						</span>
					</label>

					<label class="getcited-checkbox-compact">
						<input type="checkbox"
							   name="schema_types[author]"
							   value="1"
							   <?php checked( $schema_types['author'] ); ?>>
						<span class="checkbox-label">
							<strong><?php esc_html_e( 'Author', 'getcited' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Author attribution', 'getcited' ); ?></span>
						</span>
					</label>

					<label class="getcited-checkbox-compact">
						<input type="checkbox"
							   name="schema_types[faq]"
							   value="1"
							   <?php checked( $schema_types['faq'] ); ?>>
						<span class="checkbox-label">
							<strong><?php esc_html_e( 'FAQ', 'getcited' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Q&A format', 'getcited' ); ?></span>
						</span>
					</label>
				</div>

				<p class="description" style="margin-top: 12px;">
					<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>">
						<?php esc_html_e( 'Edit author profiles', 'getcited' ); ?>
					</a>
					<?php esc_html_e( 'to add LinkedIn, expertise, and job titles for enhanced Author schema.', 'getcited' ); ?>
				</p>
			</div>
		</div>

		<!-- Organization Details (Collapsible) -->
		<div class="getcited-section getcited-organization getcited-collapsible" data-collapsed="true">
			<h2 class="getcited-collapsible-header">
				<?php esc_html_e( 'Organization Details', 'getcited' ); ?>
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</h2>
			<div class="getcited-collapsible-content" style="display: none;">
				<p class="description">
					<?php esc_html_e( 'This information appears in your Organization schema. The sameAs links help AI systems identify your organization.', 'getcited' ); ?>
				</p>

				<div class="getcited-compact-form">
					<div class="form-row">
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
							   placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
					</div>
					<div class="form-row">
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
								   placeholder="https://example.com/logo.png">
							<button type="button" class="button getcited-upload-logo">
								<?php esc_html_e( 'Upload', 'getcited' ); ?>
							</button>
						</div>
					</div>

					<!-- sameAs Links Section -->
					<h3 class="getcited-subsection-title"><?php esc_html_e( 'sameAs Links (Entity Disambiguation)', 'getcited' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'These links help AI systems identify exactly which organization you are. The more authoritative links, the better.', 'getcited' ); ?>
					</p>

					<div class="form-row">
						<label for="org_linkedin_company">
							<?php esc_html_e( 'LinkedIn Company Page', 'getcited' ); ?>
							<?php if ( ! empty( $organization['linkedin_company'] ) ) : ?>
								<span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
							<?php endif; ?>
						</label>
						<input type="url"
							   name="organization[linkedin_company]"
							   id="org_linkedin_company"
							   value="<?php echo esc_url( $organization['linkedin_company'] ?? '' ); ?>"
							   placeholder="https://linkedin.com/company/yourcompany">
					</div>

					<div class="form-row">
						<label for="org_wikipedia">
							<?php esc_html_e( 'Wikipedia / Wikidata', 'getcited' ); ?>
							<?php if ( ! empty( $organization['wikipedia'] ) ) : ?>
								<span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
							<?php endif; ?>
						</label>
						<input type="url"
							   name="organization[wikipedia]"
							   id="org_wikipedia"
							   value="<?php echo esc_url( $organization['wikipedia'] ?? '' ); ?>"
							   placeholder="https://en.wikipedia.org/wiki/Your_Company">
					</div>

					<div class="form-row">
						<label for="org_crunchbase">
							<?php esc_html_e( 'Crunchbase', 'getcited' ); ?>
							<?php if ( ! empty( $organization['crunchbase'] ) ) : ?>
								<span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
							<?php endif; ?>
						</label>
						<input type="url"
							   name="organization[crunchbase]"
							   id="org_crunchbase"
							   value="<?php echo esc_url( $organization['crunchbase'] ?? '' ); ?>"
							   placeholder="https://crunchbase.com/organization/yourcompany">
					</div>

					<?php
					$social_urls       = $organization['social_urls'] ?? array();
					$has_social_urls   = ! empty( array_filter( $social_urls ) );
					?>
					<div class="form-row form-row-full">
						<label>
							<?php esc_html_e( 'Social Profiles', 'getcited' ); ?>
							<?php if ( $has_social_urls ) : ?>
								<span class="getcited-field-active dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Field configured', 'getcited' ); ?>"></span>
							<?php endif; ?>
						</label>
						<div class="getcited-social-urls">
							<?php
							$placeholders = array(
								'https://twitter.com/yourhandle',
								'https://facebook.com/yourpage',
								'https://youtube.com/@yourchannel',
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
						<button type="button" class="button getcited-add-social">
							<?php esc_html_e( '+ Add Another', 'getcited' ); ?>
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
			<div class="getcited-collapsible-content" style="display: none;">
				<p class="description">
					<?php esc_html_e( 'Organization schema as it will appear on your front page:', 'getcited' ); ?>
				</p>
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

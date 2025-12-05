<?php
/**
 * Author profile fields for GetCited schema
 *
 * Adds GetCited-specific fields to WordPress user profiles
 * for enhanced author schema output (LinkedIn, expertise, etc.)
 *
 * @package GetCited
 * @since 1.4.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Author Fields class
 */
class GetCited_Author_Fields {

	/**
	 * Single instance
	 *
	 * @var GetCited_Author_Fields|null
	 */
	private static $instance = null;

	/**
	 * User meta keys
	 */
	const META_LINKEDIN  = 'getcited_linkedin';
	const META_TWITTER   = 'getcited_twitter';
	const META_JOB_TITLE = 'getcited_job_title';
	const META_EXPERTISE = 'getcited_expertise';
	const META_ORCID     = 'getcited_orcid';

	/**
	 * Get instance
	 *
	 * @return GetCited_Author_Fields
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Add fields to user profile.
		add_action( 'show_user_profile', array( $this, 'render_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_profile_fields' ) );

		// Save fields.
		add_action( 'personal_options_update', array( $this, 'save_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile_fields' ) );
	}

	/**
	 * Render profile fields in user edit screen
	 *
	 * @param WP_User $user User object.
	 */
	public function render_profile_fields( $user ) {
		// Check if user can be an author.
		if ( ! user_can( $user, 'edit_posts' ) ) {
			return;
		}

		$linkedin  = get_user_meta( $user->ID, self::META_LINKEDIN, true );
		$twitter   = get_user_meta( $user->ID, self::META_TWITTER, true );
		$job_title = get_user_meta( $user->ID, self::META_JOB_TITLE, true );
		$expertise = get_user_meta( $user->ID, self::META_EXPERTISE, true );
		$orcid     = get_user_meta( $user->ID, self::META_ORCID, true );
		?>
		<h2><?php esc_html_e( 'GetCited Author Schema', 'getcited' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'This information enhances your author schema for AI systems. It helps establish your expertise and credentials.', 'getcited' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th>
					<label for="<?php echo esc_attr( self::META_JOB_TITLE ); ?>">
						<?php esc_html_e( 'Job Title', 'getcited' ); ?>
					</label>
				</th>
				<td>
					<input type="text"
						   name="<?php echo esc_attr( self::META_JOB_TITLE ); ?>"
						   id="<?php echo esc_attr( self::META_JOB_TITLE ); ?>"
						   value="<?php echo esc_attr( $job_title ); ?>"
						   class="regular-text"
						   placeholder="<?php esc_attr_e( 'e.g., Senior Editor, Lead Developer', 'getcited' ); ?>">
					<p class="description">
						<?php esc_html_e( 'Your professional title or role.', 'getcited' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="<?php echo esc_attr( self::META_EXPERTISE ); ?>">
						<?php esc_html_e( 'Areas of Expertise', 'getcited' ); ?>
					</label>
				</th>
				<td>
					<input type="text"
						   name="<?php echo esc_attr( self::META_EXPERTISE ); ?>"
						   id="<?php echo esc_attr( self::META_EXPERTISE ); ?>"
						   value="<?php echo esc_attr( $expertise ); ?>"
						   class="regular-text"
						   placeholder="<?php esc_attr_e( 'e.g., WordPress, SEO, Content Marketing', 'getcited' ); ?>">
					<p class="description">
						<?php esc_html_e( 'Comma-separated list of your expertise topics. Used in schema knowsAbout property.', 'getcited' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="<?php echo esc_attr( self::META_LINKEDIN ); ?>">
						<?php esc_html_e( 'LinkedIn Profile', 'getcited' ); ?>
					</label>
				</th>
				<td>
					<input type="url"
						   name="<?php echo esc_attr( self::META_LINKEDIN ); ?>"
						   id="<?php echo esc_attr( self::META_LINKEDIN ); ?>"
						   value="<?php echo esc_url( $linkedin ); ?>"
						   class="regular-text"
						   placeholder="https://linkedin.com/in/yourprofile">
					<p class="description">
						<?php esc_html_e( 'Full URL to your LinkedIn profile.', 'getcited' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="<?php echo esc_attr( self::META_TWITTER ); ?>">
						<?php esc_html_e( 'Twitter/X Profile', 'getcited' ); ?>
					</label>
				</th>
				<td>
					<input type="url"
						   name="<?php echo esc_attr( self::META_TWITTER ); ?>"
						   id="<?php echo esc_attr( self::META_TWITTER ); ?>"
						   value="<?php echo esc_url( $twitter ); ?>"
						   class="regular-text"
						   placeholder="https://twitter.com/yourhandle">
					<p class="description">
						<?php esc_html_e( 'Full URL to your Twitter/X profile.', 'getcited' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="<?php echo esc_attr( self::META_ORCID ); ?>">
						<?php esc_html_e( 'ORCID', 'getcited' ); ?>
					</label>
				</th>
				<td>
					<input type="url"
						   name="<?php echo esc_attr( self::META_ORCID ); ?>"
						   id="<?php echo esc_attr( self::META_ORCID ); ?>"
						   value="<?php echo esc_url( $orcid ); ?>"
						   class="regular-text"
						   placeholder="https://orcid.org/0000-0001-2345-6789">
					<p class="description">
						<?php esc_html_e( 'Your ORCID identifier (for academic/research content).', 'getcited' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save profile fields
	 *
	 * @param int $user_id User ID.
	 */
	public function save_profile_fields( $user_id ) {
		// Check permissions.
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		// Verify nonce (WordPress adds this automatically for profile updates).
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-user_' . $user_id ) ) {
			return;
		}

		// Sanitize and save each field.
		$fields = array(
			self::META_JOB_TITLE => 'sanitize_text_field',
			self::META_EXPERTISE => 'sanitize_text_field',
			self::META_LINKEDIN  => 'esc_url_raw',
			self::META_TWITTER   => 'esc_url_raw',
			self::META_ORCID     => 'esc_url_raw',
		);

		foreach ( $fields as $key => $sanitize_callback ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Value is sanitized via $sanitize_callback.
			if ( isset( $_POST[ $key ] ) ) {
				$value = call_user_func( $sanitize_callback, wp_unslash( $_POST[ $key ] ) );
				update_user_meta( $user_id, $key, $value );
			}
		}
	}

	/**
	 * Get author schema data for a user
	 *
	 * Helper method to retrieve all GetCited author fields.
	 *
	 * @param int $user_id User ID.
	 * @return array Author data.
	 */
	public function get_author_data( $user_id ) {
		return array(
			'linkedin'  => get_user_meta( $user_id, self::META_LINKEDIN, true ),
			'twitter'   => get_user_meta( $user_id, self::META_TWITTER, true ),
			'job_title' => get_user_meta( $user_id, self::META_JOB_TITLE, true ),
			'expertise' => get_user_meta( $user_id, self::META_EXPERTISE, true ),
			'orcid'     => get_user_meta( $user_id, self::META_ORCID, true ),
		);
	}

	/**
	 * Get sameAs URLs for a user
	 *
	 * Returns array of profile URLs for schema sameAs property.
	 * Falls back to standard WordPress user meta if GetCited fields are empty.
	 *
	 * @param int $user_id User ID.
	 * @return array Array of profile URLs.
	 */
	public function get_same_as_urls( $user_id ) {
		$same_as = array();

		// LinkedIn - GetCited field or fallback.
		$linkedin = get_user_meta( $user_id, self::META_LINKEDIN, true );
		if ( empty( $linkedin ) ) {
			$linkedin = get_user_meta( $user_id, 'linkedin', true );
			if ( ! empty( $linkedin ) && strpos( $linkedin, 'http' ) !== 0 ) {
				$linkedin = 'https://linkedin.com/in/' . $linkedin;
			}
		}
		if ( ! empty( $linkedin ) ) {
			$same_as[] = $linkedin;
		}

		// Twitter - GetCited field or fallback.
		$twitter = get_user_meta( $user_id, self::META_TWITTER, true );
		if ( empty( $twitter ) ) {
			$twitter = get_user_meta( $user_id, 'twitter', true );
			if ( ! empty( $twitter ) && strpos( $twitter, 'http' ) !== 0 ) {
				$twitter = 'https://twitter.com/' . ltrim( $twitter, '@' );
			}
		}
		if ( ! empty( $twitter ) ) {
			$same_as[] = $twitter;
		}

		// ORCID.
		$orcid = get_user_meta( $user_id, self::META_ORCID, true );
		if ( ! empty( $orcid ) ) {
			$same_as[] = $orcid;
		}

		// User website.
		$website = get_user_meta( $user_id, 'url', true );
		if ( ! empty( $website ) ) {
			$same_as[] = $website;
		}

		// Facebook fallback.
		$facebook = get_user_meta( $user_id, 'facebook', true );
		if ( ! empty( $facebook ) ) {
			if ( strpos( $facebook, 'http' ) !== 0 ) {
				$facebook = 'https://facebook.com/' . $facebook;
			}
			$same_as[] = $facebook;
		}

		return array_filter( array_unique( $same_as ) );
	}

	/**
	 * Get knowsAbout array for a user
	 *
	 * Parses the expertise field into an array for schema.
	 *
	 * @param int $user_id User ID.
	 * @return array Array of expertise topics.
	 */
	public function get_knows_about( $user_id ) {
		$expertise = get_user_meta( $user_id, self::META_EXPERTISE, true );

		if ( empty( $expertise ) ) {
			return array();
		}

		// Split by comma, trim whitespace, filter empty.
		$topics = array_map( 'trim', explode( ',', $expertise ) );
		return array_filter( $topics );
	}
}

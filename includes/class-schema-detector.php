<?php
/**
 * Schema detection system for GetCited
 *
 * Detects existing schema sources (SEO plugins, custom JSON-LD)
 * to determine if GetCited schema should be auto-disabled.
 *
 * @package GetCited
 * @since 1.4.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema Detector class
 */
class GetCited_Schema_Detector {

	/**
	 * Single instance
	 *
	 * @var GetCited_Schema_Detector|null
	 */
	private static $instance = null;

	/**
	 * Transient name for cached detection results
	 */
	const TRANSIENT_NAME = 'getcited_schema_detection';

	/**
	 * Transient expiration (7 days in seconds)
	 */
	const TRANSIENT_EXPIRATION = 604800;

	/**
	 * Get instance
	 *
	 * @return GetCited_Schema_Detector
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
		// Re-run detection when plugins are activated or deactivated.
		add_action( 'activated_plugin', array( $this, 'on_plugin_change' ) );
		add_action( 'deactivated_plugin', array( $this, 'on_plugin_change' ) );

		// Weekly cron for re-scanning.
		add_action( 'getcited_weekly_schema_scan', array( $this, 'refresh_detection' ) );
	}

	/**
	 * Handle plugin activation/deactivation
	 *
	 * @param string $plugin Plugin basename.
	 */
	public function on_plugin_change( $plugin ) {
		// Check if it's a known SEO plugin.
		$seo_plugins = array(
			'wordpress-seo',
			'seo-by-rank-math',
			'all-in-one-seo-pack',
			'wp-seopress',
			'schema-pro',
			'squirrly-seo',
			'autodescription',
			'smartcrawl-seo',
		);

		foreach ( $seo_plugins as $seo_plugin ) {
			if ( strpos( $plugin, $seo_plugin ) !== false ) {
				$this->refresh_detection();
				break;
			}
		}
	}

	/**
	 * Detect SEO plugins that output schema
	 *
	 * @return array Array of detected plugins with name and slug.
	 */
	public function detect_schema_plugins() {
		$detected = array();

		// Yoast SEO.
		if ( defined( 'WPSEO_VERSION' ) ) {
			$detected[] = array(
				'name' => 'Yoast SEO',
				'slug' => 'yoast',
			);
		}

		// Rank Math.
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$detected[] = array(
				'name' => 'Rank Math',
				'slug' => 'rankmath',
			);
		}

		// All in One SEO.
		if ( defined( 'AIOSEO_VERSION' ) ) {
			$detected[] = array(
				'name' => 'All in One SEO',
				'slug' => 'aioseo',
			);
		}

		// SEOPress.
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			$detected[] = array(
				'name' => 'SEOPress',
				'slug' => 'seopress',
			);
		}

		// Schema Pro.
		if ( class_exists( 'BSF_AIOSRS_Pro' ) ) {
			$detected[] = array(
				'name' => 'Schema Pro',
				'slug' => 'schema-pro',
			);
		}

		// Squirrly SEO.
		if ( defined( 'SQ_VERSION' ) ) {
			$detected[] = array(
				'name' => 'Squirrly SEO',
				'slug' => 'squirrly',
			);
		}

		// The SEO Framework.
		if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
			$detected[] = array(
				'name' => 'The SEO Framework',
				'slug' => 'seo-framework',
			);
		}

		// SmartCrawl.
		if ( class_exists( 'Smartcrawl_Loader' ) ) {
			$detected[] = array(
				'name' => 'SmartCrawl',
				'slug' => 'smartcrawl',
			);
		}

		// HeyTC SEO.
		if ( defined( 'HEYTC_SEO_VERSION' ) || class_exists( 'HeyTC_SEO_Settings' ) ) {
			$detected[] = array(
				'name' => 'HeyTC SEO',
				'slug' => 'heytc-seo',
			);
		}

		return $detected;
	}

	/**
	 * Detect existing JSON-LD schema on homepage and a sample post
	 *
	 * Fetches the homepage and a recent post to look for existing schema markup.
	 * Some SEO plugins only output schema on single posts, not the homepage.
	 * Uses non-blocking request with short timeout to avoid deadlocks.
	 *
	 * @return array Detection result with 'found' boolean and 'types' array.
	 */
	public function detect_existing_schema() {
		$result = array(
			'found' => false,
			'types' => array(),
		);

		// Skip if this is an AJAX request to prevent potential deadlock.
		// The page fetch can trigger another WordPress load.
		if ( wp_doing_ajax() ) {
			// Return cached result if available, skip live fetch.
			$cached = get_transient( self::TRANSIENT_NAME );
			if ( false !== $cached && isset( $cached['json_ld_types'] ) ) {
				return array(
					'found' => $cached['json_ld_found'] ?? false,
					'types' => $cached['json_ld_types'] ?? array(),
				);
			}
			// No cache, return empty (plugin detection still works).
			return $result;
		}

		// URLs to scan: homepage + one recent post.
		$urls_to_scan = array( home_url() );

		// Get one recent published post to scan.
		$recent_posts = get_posts(
			array(
				'numberposts' => 1,
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		if ( ! empty( $recent_posts ) ) {
			$urls_to_scan[] = get_permalink( $recent_posts[0]->ID );
		}

		// Scan each URL for JSON-LD.
		foreach ( $urls_to_scan as $url ) {
			$types_from_url = $this->scan_url_for_jsonld( $url );
			$result['types'] = array_merge( $result['types'], $types_from_url );
		}

		// Normalize and dedupe types.
		$result['types'] = array_unique( $result['types'] );

		// Check if Organization or Article schema found (the main ones we'd output).
		$competing_types = array( 'Organization', 'Article', 'NewsArticle', 'BlogPosting', 'WebSite' );
		foreach ( $result['types'] as $type ) {
			if ( in_array( $type, $competing_types, true ) ) {
				$result['found'] = true;
				break;
			}
		}

		return $result;
	}

	/**
	 * Scan a single URL for JSON-LD schema types
	 *
	 * @since 1.5.2
	 * @param string $url URL to scan.
	 * @return array Array of schema types found.
	 */
	private function scan_url_for_jsonld( $url ) {
		$types = array();

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 5,
				'user-agent' => 'GetCited Schema Detector',
				'sslverify'  => false,
				'blocking'   => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $types;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return $types;
		}

		// Find all JSON-LD script tags.
		if ( preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $body, $matches ) ) {
			foreach ( $matches[1] as $json_content ) {
				$data = json_decode( trim( $json_content ), true );
				if ( ! $data ) {
					continue;
				}

				// Handle @graph structure.
				if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
					foreach ( $data['@graph'] as $item ) {
						if ( isset( $item['@type'] ) ) {
							$item_types = (array) $item['@type'];
							$types      = array_merge( $types, $item_types );
						}
					}
				} elseif ( isset( $data['@type'] ) ) {
					// Single schema.
					$item_types = (array) $data['@type'];
					$types      = array_merge( $types, $item_types );
				}
			}
		}

		return $types;
	}

	/**
	 * Run full detection and cache results
	 *
	 * @return array Detection results.
	 */
	public function run_detection() {
		$plugins      = $this->detect_schema_plugins();
		$json_ld      = $this->detect_existing_schema();
		$has_plugins  = ! empty( $plugins );
		$has_json_ld  = $json_ld['found'];

		// Determine detected source for display.
		$detected_source = '';
		if ( $has_plugins ) {
			$detected_source = $plugins[0]['slug']; // Primary detected plugin.
		} elseif ( $has_json_ld ) {
			$detected_source = 'json_ld';
		}

		$result = array(
			'plugins'         => $plugins,
			'json_ld_found'   => $has_json_ld,
			'json_ld_types'   => $json_ld['types'],
			'should_disable'  => $has_plugins || $has_json_ld,
			'detected_source' => $detected_source,
			'last_scan'       => time(),
		);

		// Cache the result.
		set_transient( self::TRANSIENT_NAME, $result, self::TRANSIENT_EXPIRATION );

		// Update settings with detection state.
		$settings = GetCited_Settings::instance();
		$settings->set( 'schema_last_scan', $result['last_scan'] );
		$settings->set( 'schema_detected_source', $detected_source );

		return $result;
	}

	/**
	 * Refresh detection (clear cache and re-run)
	 *
	 * @return array Fresh detection results.
	 */
	public function refresh_detection() {
		delete_transient( self::TRANSIENT_NAME );
		return $this->run_detection();
	}

	/**
	 * Get cached detection status (or run fresh if no cache)
	 *
	 * @return array Detection results.
	 */
	public function get_detection_status() {
		$cached = get_transient( self::TRANSIENT_NAME );

		if ( false === $cached ) {
			return $this->run_detection();
		}

		return $cached;
	}

	/**
	 * Check if schema should be disabled based on detection
	 *
	 * This is the main method used by the schema output class.
	 *
	 * @return bool True if schema should be disabled.
	 */
	public function should_disable_schema() {
		$settings = GetCited_Settings::instance();

		// User force-enabled schema, don't disable.
		if ( $settings->get( 'schema_force_enabled' ) ) {
			return false;
		}

		$detection = $this->get_detection_status();
		return $detection['should_disable'];
	}

	/**
	 * Get human-readable status message
	 *
	 * @return array Status with 'status' (active|handled|none), 'message', and 'source'.
	 */
	public function get_status_message() {
		$settings  = GetCited_Settings::instance();
		$detection = $this->get_detection_status();

		// Check if user has force-enabled GetCited schema.
		if ( $settings->get( 'schema_enabled' ) && $settings->get( 'schema_force_enabled' ) ) {
			return array(
				'status'  => 'active',
				'message' => __( 'GetCited schema is active (alongside your SEO plugin).', 'getcited' ),
				'source'  => 'force',
			);
		}

		// Check if another plugin is handling schema (positive framing!).
		if ( $detection['should_disable'] ) {
			if ( ! empty( $detection['plugins'] ) ) {
				$plugin_names = array_column( $detection['plugins'], 'name' );
				return array(
					'status'  => 'handled',
					'message' => sprintf(
						/* translators: %s: plugin name handling schema */
						__( 'All set! %s is handling your schema markup. GetCited won\'t override it.', 'getcited' ),
						implode( ', ', $plugin_names )
					),
					'source'  => $detection['detected_source'],
				);
			} else {
				// JSON-LD detected but no known plugin.
				return array(
					'status'  => 'handled',
					'message' => __( 'All set! Schema markup detected on your site. GetCited won\'t override it.', 'getcited' ),
					'source'  => 'json_ld',
				);
			}
		}

		// Check if GetCited schema is enabled.
		if ( $settings->get( 'schema_enabled' ) ) {
			return array(
				'status'  => 'active',
				'message' => __( 'GetCited schema is active.', 'getcited' ),
				'source'  => 'getcited',
			);
		}

		// Nothing is handling schema - prompt user to enable.
		return array(
			'status'  => 'none',
			'message' => __( 'No schema markup detected. Enable GetCited schema below.', 'getcited' ),
			'source'  => 'none',
		);
	}

	/**
	 * Get time since last scan in human-readable format
	 *
	 * @return string Human-readable time difference.
	 */
	public function get_last_scan_ago() {
		$detection = $this->get_detection_status();

		if ( empty( $detection['last_scan'] ) ) {
			return __( 'Never', 'getcited' );
		}

		return human_time_diff( $detection['last_scan'], time() ) . ' ' . __( 'ago', 'getcited' );
	}
}

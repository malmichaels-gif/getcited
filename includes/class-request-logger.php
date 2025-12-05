<?php
/**
 * Request Logger for GetCited
 *
 * Logs llms.txt requests to track AI crawler visits.
 *
 * @package GetCited
 * @since 1.4.5
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Request Logger class
 */
class GetCited_Request_Logger {

	/**
	 * Single instance
	 *
	 * @var GetCited_Request_Logger|null
	 */
	private static $instance = null;

	/**
	 * Database table name (without prefix)
	 */
	const TABLE_NAME = 'getcited_llms_requests';

	/**
	 * Get instance
	 *
	 * @return GetCited_Request_Logger
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
		// Hook into llms.txt serving to log requests.
		add_action( 'getcited_llms_txt_served', array( $this, 'log_request' ) );
	}

	/**
	 * Get the full table name with prefix
	 *
	 * @return string
	 */
	private function get_table_name() {
		global $wpdb;
		return esc_sql( $wpdb->prefix . self::TABLE_NAME );
	}

	/**
	 * Log an llms.txt request
	 *
	 * @param string $content The llms.txt content being served.
	 */
	public function log_request( $content ) {
		// Check if logging is enabled.
		$settings = GetCited_Settings::instance();
		if ( ! $settings->get( 'request_logging_enabled' ) ) {
			return;
		}

		// Get user agent.
		$user_agent = $this->get_user_agent();
		if ( empty( $user_agent ) ) {
			return;
		}

		// Check for duplicate (rate limit).
		if ( $this->is_duplicate_request( $user_agent ) ) {
			return;
		}

		// Parse bot name and classify.
		$bot_name = $this->parse_bot_name( $user_agent );
		$category = $this->classify_request( $bot_name, $user_agent );

		// Insert into database.
		$this->insert_request( array(
			'request_time' => current_time( 'mysql' ),
			'user_agent'   => $user_agent,
			'bot_name'     => $bot_name,
			'category'     => $category,
			'ip_hash'      => $this->hash_ip(),
		) );
	}

	/**
	 * Get the current user agent
	 *
	 * @return string
	 */
	private function get_user_agent() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- User agent is sanitized below
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		return sanitize_text_field( substr( $user_agent, 0, 500 ) );
	}

	/**
	 * Hash the client IP for rate limiting
	 *
	 * We hash IPs to avoid storing PII while still enabling rate limiting.
	 *
	 * @return string
	 */
	private function hash_ip() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- IP is hashed, not displayed
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';

		// Use site-specific salt for privacy.
		$salt = wp_salt( 'auth' );

		return hash( 'sha256', $ip . $salt );
	}

	/**
	 * Check if this is a duplicate request (rate limiting)
	 *
	 * Prevents log spam from aggressive crawlers by limiting to one entry
	 * per IP + User-Agent combination per hour.
	 *
	 * @param string $user_agent The user agent string.
	 * @return bool True if duplicate (should skip logging).
	 */
	private function is_duplicate_request( $user_agent ) {
		$ip_hash   = $this->hash_ip();
		$cache_key = 'getcited_req_' . md5( $ip_hash . $user_agent );

		if ( get_transient( $cache_key ) ) {
			return true;
		}

		set_transient( $cache_key, 1, HOUR_IN_SECONDS );
		return false;
	}

	/**
	 * Parse bot name from user agent
	 *
	 * @param string $user_agent The user agent string.
	 * @return string The parsed bot name.
	 */
	public function parse_bot_name( $user_agent ) {
		// Check against known AI crawlers first.
		$crawler_list = GetCited_Crawler_List::instance();
		$crawlers     = $crawler_list->get_all();

		foreach ( $crawlers as $crawler ) {
			if ( ! empty( $crawler['user_agent'] ) && stripos( $user_agent, $crawler['user_agent'] ) !== false ) {
				return $crawler['name'];
			}
		}

		// Check common search engines.
		$search_engines = array(
			'Googlebot'    => 'Googlebot',
			'Bingbot'      => 'Bingbot',
			'DuckDuckBot'  => 'DuckDuckBot',
			'YandexBot'    => 'YandexBot',
			'Baiduspider'  => 'Baiduspider',
			'Sogou'        => 'Sogou',
		);

		foreach ( $search_engines as $pattern => $name ) {
			if ( stripos( $user_agent, $pattern ) !== false ) {
				return $name;
			}
		}

		// Check for common bot patterns.
		if ( preg_match( '/^([a-zA-Z0-9_-]+)[Bb]ot/i', $user_agent, $matches ) ) {
			return $matches[1] . 'Bot';
		}

		// Check for common HTTP libraries (likely bots).
		$libraries = array(
			'python-requests' => 'python-requests',
			'curl'            => 'curl',
			'wget'            => 'wget',
			'axios'           => 'axios',
			'node-fetch'      => 'node-fetch',
			'Go-http-client'  => 'Go-http-client',
			'libwww-perl'     => 'libwww-perl',
			'Java/'           => 'Java',
			'okhttp'          => 'okhttp',
		);

		foreach ( $libraries as $pattern => $name ) {
			if ( stripos( $user_agent, $pattern ) !== false ) {
				return $name;
			}
		}

		// Browser detection (probably human visitor checking their own llms.txt).
		if ( preg_match( '/(Chrome|Firefox|Safari|Edge|Opera|MSIE|Trident)/i', $user_agent, $matches ) ) {
			$browser = $matches[1];
			// Normalize IE.
			if ( in_array( $browser, array( 'MSIE', 'Trident' ), true ) ) {
				$browser = 'IE';
			}
			return 'Browser (' . $browser . ')';
		}

		return 'Unknown';
	}

	/**
	 * Classify the request into a category
	 *
	 * @param string $bot_name   The parsed bot name.
	 * @param string $user_agent The full user agent string.
	 * @return string Category: ai_crawler, search_engine, browser, unknown.
	 */
	public function classify_request( $bot_name, $user_agent ) {
		// Check if it's a known AI crawler.
		$crawler_list = GetCited_Crawler_List::instance();
		$crawlers     = $crawler_list->get_all();
		$ai_names     = wp_list_pluck( $crawlers, 'name' );

		if ( in_array( $bot_name, $ai_names, true ) ) {
			return 'ai_crawler';
		}

		// Check for search engines.
		$search_engines = array( 'Googlebot', 'Bingbot', 'DuckDuckBot', 'YandexBot', 'Baiduspider', 'Sogou' );
		if ( in_array( $bot_name, $search_engines, true ) ) {
			return 'search_engine';
		}

		// Check for browser.
		if ( strpos( $bot_name, 'Browser' ) === 0 ) {
			return 'browser';
		}

		return 'unknown';
	}

	/**
	 * Insert a request into the database
	 *
	 * @param array $data Request data.
	 * @return int|false The row ID or false on failure.
	 */
	private function insert_request( $data ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct insert
		$result = $wpdb->insert(
			$this->get_table_name(),
			array(
				'request_time' => $data['request_time'],
				'user_agent'   => $data['user_agent'],
				'bot_name'     => $data['bot_name'],
				'category'     => $data['category'],
				'ip_hash'      => $data['ip_hash'],
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get recent requests
	 *
	 * @param int $days  Number of days to look back.
	 * @param int $limit Maximum number of results.
	 * @return array Array of request objects.
	 */
	public function get_recent_requests( $days = 30, $limit = 50 ) {
		global $wpdb;

		$table = $this->get_table_name();
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table requires direct query, table name is safe from $wpdb->prefix
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT request_time, bot_name, category
				FROM {$table}
				WHERE request_time > %s
				ORDER BY request_time DESC
				LIMIT %d",
				$since,
				$limit
			)
		);

		return $results ? $results : array();
	}

	/**
	 * Get request statistics
	 *
	 * @param int $days Number of days to look back.
	 * @return array Stats array with total, unique_bots, ai_crawlers, categories.
	 */
	public function get_request_stats( $days = 30 ) {
		global $wpdb;

		$table = $this->get_table_name();
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table requires direct query, table name is safe from $wpdb->prefix
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE request_time > %s",
				$since
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table requires direct query, table name is safe from $wpdb->prefix
		$unique_bots = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT bot_name) FROM {$table} WHERE request_time > %s",
				$since
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table requires direct query, table name is safe from $wpdb->prefix
		$ai_crawlers = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE request_time > %s AND category = 'ai_crawler'",
				$since
			)
		);

		// Get category breakdown.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table requires direct query, table name is safe from $wpdb->prefix
		$categories = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT category, COUNT(*) as count
				FROM {$table}
				WHERE request_time > %s
				GROUP BY category",
				$since
			),
			OBJECT_K
		);

		return array(
			'total'       => (int) $total,
			'unique_bots' => (int) $unique_bots,
			'ai_crawlers' => (int) $ai_crawlers,
			'categories'  => $categories,
		);
	}

	/**
	 * Clean up old request entries
	 *
	 * Removes entries older than the retention period.
	 */
	public function cleanup_old_requests() {
		global $wpdb;

		$settings  = GetCited_Settings::instance();
		$retention = $settings->get( 'request_log_retention' );

		if ( ! $retention || $retention <= 0 ) {
			$retention = 90; // Default 90 days.
		}

		$table    = $this->get_table_name();
		$cutoff   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table cleanup requires direct query, table name is safe from $wpdb->prefix
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE request_time < %s",
				$cutoff
			)
		);
	}

	/**
	 * Clear all request log entries
	 *
	 * Used for manual log clearing from settings.
	 *
	 * @return bool True on success.
	 */
	public function clear_all_requests() {
		global $wpdb;

		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table truncate requires direct query, table name is safe from $wpdb->prefix
		$result = $wpdb->query( "TRUNCATE TABLE {$table}" );

		return $result !== false;
	}

	/**
	 * Get category display info
	 *
	 * @param string $category The category slug.
	 * @return array Array with label and icon.
	 */
	public static function get_category_display( $category ) {
		$categories = array(
			'ai_crawler'    => array(
				'label' => __( 'Known AI', 'getcited' ),
				'icon'  => '&#10003;',
				'class' => 'ai-crawler',
			),
			'search_engine' => array(
				'label' => __( 'Search Engine', 'getcited' ),
				'icon'  => '&#8226;',
				'class' => 'search-engine',
			),
			'browser'       => array(
				'label' => __( 'Visitor', 'getcited' ),
				'icon'  => '&#128100;',
				'class' => 'browser',
			),
			'unknown'       => array(
				'label' => __( 'Unknown', 'getcited' ),
				'icon'  => '?',
				'class' => 'unknown',
			),
		);

		return $categories[ $category ] ?? $categories['unknown'];
	}
}

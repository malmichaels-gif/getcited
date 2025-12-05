<?php
/**
 * Helper functions for GetCited
 *
 * @package GetCited
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if a post is marked noindex by any SEO plugin
 *
 * This function checks multiple SEO plugins to determine if a post
 * should be excluded from llms.txt generation due to noindex status.
 *
 * @since 1.5.0
 *
 * @param int $post_id The post ID to check.
 * @return bool True if post is noindex, false otherwise.
 */
function getcited_is_post_noindex( $post_id ) {
	/**
	 * Filter whether a post is considered noindex for llms.txt purposes.
	 *
	 * Returning a non-null value will short-circuit the SEO plugin checks.
	 *
	 * @since 1.5.0
	 *
	 * @param bool|null $is_noindex null to use default SEO plugin detection,
	 *                              true to force exclude, false to force include.
	 * @param int       $post_id    The post ID being checked.
	 * @return bool|null
	 */
	$override = apply_filters( 'getcited_is_noindex', null, $post_id );
	if ( null !== $override ) {
		return (bool) $override;
	}

	// Yoast SEO.
	if ( defined( 'WPSEO_VERSION' ) ) {
		$noindex = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
		if ( '1' === $noindex ) {
			return true;
		}
	}

	// RankMath.
	if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
		$robots = get_post_meta( $post_id, 'rank_math_robots', true );
		if ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) {
			return true;
		}
	}

	// SEOPress.
	if ( defined( 'SEOPRESS_VERSION' ) ) {
		$noindex = get_post_meta( $post_id, '_seopress_robots_index', true );
		if ( 'yes' === $noindex ) {
			return true;
		}
	}

	// All in One SEO (uses custom table).
	if ( defined( 'AIOSEO_VERSION' ) || class_exists( 'AIOSEO' ) ) {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'aioseo_posts' );

		// Check table exists first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		if ( $table_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe from $wpdb->prefix
			$noindex = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT robots_noindex FROM {$table} WHERE post_id = %d",
					$post_id
				)
			);
			if ( $noindex ) {
				return true;
			}
		}
	}

	// The SEO Framework.
	if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
		$noindex = get_post_meta( $post_id, '_genesis_noindex', true );
		if ( '1' === $noindex ) {
			return true;
		}
	}

	return false;
}

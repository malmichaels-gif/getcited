<?php
/**
 * GetCited Waitlist API Endpoint
 *
 * Deploy to: https://heytc.com/getcited-api/waitlist.php
 *
 * @package GetCited
 */

header( 'Content-Type: application/json' );

// CORS: Allow any origin since the plugin runs on any WordPress site.
// Security is handled via: honeypot, rate limiting, email validation.
header( 'Access-Control-Allow-Origin: *' );
header( 'Access-Control-Allow-Methods: POST, OPTIONS' );
header( 'Access-Control-Allow-Headers: Content-Type' );

// Handle preflight.
if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
	http_response_code( 200 );
	exit;
}

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
	http_response_code( 405 );
	echo json_encode( array( 'error' => 'Method not allowed' ) );
	exit;
}

// Load WP for database access.
require_once dirname( __DIR__ ) . '/wp-load.php';

global $wpdb;
$table = $wpdb->prefix . 'getcited_waitlist';

// Check table exists.
$table_exists = $wpdb->get_var(
	$wpdb->prepare(
		'SHOW TABLES LIKE %s',
		$table
	)
);

if ( ! $table_exists ) {
	http_response_code( 500 );
	echo json_encode( array( 'error' => 'Service temporarily unavailable' ) );
	exit;
}

// Get input.
$input    = json_decode( file_get_contents( 'php://input' ), true );
$email    = sanitize_email( $input['email'] ?? '' );
$site_url = esc_url_raw( substr( $input['site_url'] ?? '', 0, 255 ) );
$honeypot = $input['website'] ?? '';

// Honeypot check - bots fill this hidden field, humans don't.
if ( ! empty( $honeypot ) ) {
	// Return fake success to not tip off bots.
	echo json_encode( array( 'success' => true, 'waitlist_count' => 50 ) );
	exit;
}

// Rate limiting - 1 submission per minute per IP.
// IP Detection: Trust Cloudflare/proxy headers since this runs on Kinsta (behind Cloudflare).
// Note: These headers can be spoofed, but the risk is acceptable here because:
// 1. Rate limiting only prevents accidental double-submits, not security-critical
// 2. Real protection comes from honeypot + email validation + UNIQUE constraint
// 3. Worst case: spam emails in waitlist (low impact, easy to clean)
$ip = $_SERVER['HTTP_CF_CONNECTING_IP']
	?? $_SERVER['HTTP_X_FORWARDED_FOR']
	?? $_SERVER['REMOTE_ADDR'];
$ip = explode( ',', $ip )[0];
$ip = trim( $ip );

$transient_key = 'getcited_waitlist_' . md5( $ip );

if ( get_transient( $transient_key ) ) {
	http_response_code( 429 );
	echo json_encode( array( 'error' => 'Too many requests, please wait a moment' ) );
	exit;
}

set_transient( $transient_key, true, 60 );

// Validate email.
if ( ! is_email( $email ) ) {
	http_response_code( 400 );
	echo json_encode( array( 'error' => 'Invalid email' ) );
	exit;
}

// Insert with duplicate handling (avoids race condition).
// Requires UNIQUE constraint on email column.
$wpdb->query(
	$wpdb->prepare(
		"INSERT IGNORE INTO `$table` (email, site_url, created_at) VALUES (%s, %s, %s)",
		$email,
		$site_url,
		gmdate( 'Y-m-d H:i:s' )
	)
);

// Get total count (rounded for privacy).
// Use esc_sql() for defense in depth, even though $table is internally constructed.
$count         = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `" . esc_sql( $table ) . "`" );
$display_count = $count < 10 ? $count : floor( $count / 10 ) * 10;

echo json_encode(
	array(
		'success'        => true,
		'waitlist_count' => $display_count,
	)
);

<?php
/**
 * Uninstall GetCited
 *
 * Removes all plugin data when the plugin is deleted via WordPress admin.
 *
 * @package GetCited
 */

// Exit if not called by WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Clean up all GetCited data
 */
function getcited_uninstall() {
    global $wpdb;

    // Check if user wants to keep settings
    $settings = get_option( 'getcited_settings', array() );
    $keep_settings = isset( $settings['keep_on_delete'] ) && $settings['keep_on_delete'];

    if ( $keep_settings ) {
        // Only remove transients and cron, keep settings
        delete_transient( 'getcited_crawler_list' );
        delete_transient( 'getcited_health_status' );
        delete_transient( 'getcited_llms_txt_status' );
        
        // Remove cron jobs
        wp_clear_scheduled_hook( 'getcited_daily_cron' );
        
        return;
    }

    // Full cleanup

    // 1. Delete main settings
    delete_option( 'getcited_settings' );
    delete_option( 'getcited_local_waitlist' );

    // 2. Delete all transients
    delete_transient( 'getcited_crawler_list' );
    delete_transient( 'getcited_health_status' );
    delete_transient( 'getcited_llms_txt_status' );

    // Delete citability cache transients (pattern matching)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup requires direct query for pattern matching
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_getcited_citability_cache_%'
         OR option_name LIKE '_transient_timeout_getcited_citability_cache_%'"
    );

    // 3. Delete post meta
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup requires bulk delete of plugin meta
    $wpdb->query(
        "DELETE FROM {$wpdb->postmeta}
         WHERE meta_key IN (
             '_getcited_exclude',
             '_getcited_no_schema',
             '_getcited_citability_score',
             '_getcited_last_audit',
             '_getcited_citation_count'
         )"
    );

    // 4. Delete user meta (dismissed notices)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup requires pattern delete of plugin user meta
    $wpdb->query(
        "DELETE FROM {$wpdb->usermeta}
         WHERE meta_key LIKE 'getcited_dismissed_%'"
    );

    // 5. Remove cron jobs
    wp_clear_scheduled_hook( 'getcited_daily_cron' );

    // 6. Flush rewrite rules (removes /llms.txt route)
    flush_rewrite_rules();
}

// Run uninstall
getcited_uninstall();

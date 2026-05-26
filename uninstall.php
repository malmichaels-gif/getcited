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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup requires pattern delete
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_getcited%'
             OR option_name LIKE '_transient_timeout_getcited%'"
        );

        // Remove cron jobs
        wp_clear_scheduled_hook( 'getcited_daily_cron' );
        wp_clear_scheduled_hook( 'getcited_weekly_schema_scan' );
        wp_clear_scheduled_hook( 'getcited_weekly_llms_refresh' );

        return;
    }

    // Full cleanup

    // 1. Delete main settings
    delete_option( 'getcited_settings' );
    delete_option( 'getcited_auto_analyzed_done' );

    // 2. Delete all transients
    delete_transient( 'getcited_crawler_list' );
    delete_transient( 'getcited_health_status' );
    delete_transient( 'getcited_llms_txt_status' );

    // Delete ALL getcited transients (catch any we missed)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup requires direct query for pattern matching
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_getcited%'
         OR option_name LIKE '_transient_timeout_getcited%'"
    );

    // Delete visibility score transient
    delete_transient( 'getcited_visibility_score' );
    delete_transient( 'getcited_show_citation_nudge' );
    delete_transient( 'getcited_activation_redirect' );
    delete_transient( 'getcited_scan_throttle' );
    delete_transient( 'getcited_wizard_scan' );

    // Drop llms.txt request log table
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall cleanup requires table drop
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}getcited_llms_requests" );

    // 3. Delete post meta
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup requires bulk delete of plugin meta
    $wpdb->query(
        "DELETE FROM {$wpdb->postmeta}
         WHERE meta_key IN (
             '_getcited_exclude',
             '_getcited_no_schema',
             '_getcited_citability_score',
             '_getcited_last_audit',
             '_getcited_citation_count',
             '_getcited_has_meta'
         )"
    );

    // 4. Delete user meta (dismissed notices, author fields, tip index, etc.)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup requires pattern delete of plugin user meta
    $wpdb->query(
        "DELETE FROM {$wpdb->usermeta}
         WHERE meta_key LIKE 'getcited_%'"
    );

    // 5. Remove cron jobs
    wp_clear_scheduled_hook( 'getcited_daily_cron' );
    wp_clear_scheduled_hook( 'getcited_weekly_schema_scan' );
    wp_clear_scheduled_hook( 'getcited_weekly_llms_refresh' );

    // 6. Remove GetCited rules from physical robots.txt if present
    getcited_cleanup_robots_txt();

    // 7. Remove llms.txt if it was created by GetCited
    getcited_cleanup_llms_txt();

    // 8. Flush rewrite rules (removes /llms.txt route)
    flush_rewrite_rules();
}

/**
 * Remove GetCited rules from physical robots.txt file
 */
function getcited_cleanup_robots_txt() {
    $file_path = ABSPATH . 'robots.txt';

    // Check if file exists
    if ( ! file_exists( $file_path ) ) {
        return;
    }

    // Initialize WP_Filesystem
    global $wp_filesystem;
    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if ( ! WP_Filesystem() ) {
        return;
    }

    // Check if writable using WP_Filesystem
    if ( ! $wp_filesystem->is_writable( $file_path ) ) {
        return;
    }

    // Read current content
    $content = $wp_filesystem->get_contents( $file_path );
    if ( $content === false ) {
        return;
    }

    $marker_start = '# === GetCited AI Crawler Rules ===';
    $marker_end = '# === End GetCited Rules ===';

    // Check if our rules exist
    if ( strpos( $content, $marker_start ) === false ) {
        return;
    }

    // Remove our rules section (including surrounding whitespace)
    $pattern = '/\n*' . preg_quote( $marker_start, '/' ) . '.*?' . preg_quote( $marker_end, '/' ) . '\n*/s';
    $new_content = preg_replace( $pattern, "\n", $content );
    $new_content = trim( $new_content ) . "\n";

    // Write file using WP_Filesystem
    if ( ! $wp_filesystem->put_contents( $file_path, $new_content, FS_CHMOD_FILE ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[GetCited] Failed to clean robots.txt on uninstall.' );
        }
    }
}

/**
 * Remove llms.txt file if it was created by GetCited, and restore backup if exists
 */
function getcited_cleanup_llms_txt() {
    $file_path   = ABSPATH . 'llms.txt';
    $backup_path = ABSPATH . 'llms.txt.bak';

    // Initialize WP_Filesystem
    global $wp_filesystem;
    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if ( ! WP_Filesystem() ) {
        return;
    }

    // Check if llms.txt exists and is ours
    if ( $wp_filesystem->exists( $file_path ) ) {
        $content = $wp_filesystem->get_contents( $file_path );

        // Only delete if file contains GetCited marker
        if ( $content && strpos( $content, '# Generated by GetCited' ) !== false ) {
            if ( $wp_filesystem->is_writable( $file_path ) ) {
                if ( ! $wp_filesystem->delete( $file_path ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( '[GetCited] Failed to delete llms.txt on uninstall.' );
                }
            }
        }
    }

    // Restore backup if it exists (user's original file before GetCited)
    if ( $wp_filesystem->exists( $backup_path ) ) {
        // Only restore if there's no llms.txt now (we deleted ours, or it didn't exist)
        if ( ! $wp_filesystem->exists( $file_path ) ) {
            $wp_filesystem->move( $backup_path, $file_path, true );
        } else {
            // llms.txt exists and isn't ours - just delete the backup
            if ( ! $wp_filesystem->delete( $backup_path ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[GetCited] Failed to delete llms.txt.bak on uninstall.' );
            }
        }
    }
}

// Run uninstall
getcited_uninstall();

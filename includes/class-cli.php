<?php
/**
 * WP-CLI Commands for GetCited
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manage GetCited AI visibility settings
 */
class GetCited_CLI {

    /**
     * Show current configuration and health status
     *
     * ## EXAMPLES
     *
     *     wp getcited status
     *
     * @subcommand status
     */
    public function status( $args, $assoc_args ) {
        $stats = GetCited_Dashboard::instance()->get_stats();
        $health = $stats['health'];

        WP_CLI::line( '' );
        WP_CLI::line( WP_CLI::colorize( '%BGetCited Status%n' ) );
        WP_CLI::line( str_repeat( '=', 40 ) );

        // Crawlers
        WP_CLI::line( '' );
        WP_CLI::line( WP_CLI::colorize( '%YAI Crawlers:%n' ) );
        WP_CLI::line( "  Allowed: {$stats['crawlers']['allowed']}" );
        WP_CLI::line( "  Blocked: {$stats['crawlers']['blocked']}" );
        WP_CLI::line( "  Total: {$stats['crawlers']['total']}" );

        // llms.txt
        WP_CLI::line( '' );
        WP_CLI::line( WP_CLI::colorize( '%Yllms.txt:%n' ) );
        WP_CLI::line( "  Enabled: " . ( $stats['llms_txt']['enabled'] ? 'Yes' : 'No' ) );
        $status_color = $stats['llms_txt']['status'] === 'ok' ? '%G' : '%R';
        WP_CLI::line( "  Status: " . WP_CLI::colorize( $status_color . ucfirst( $stats['llms_txt']['status'] ) . '%n' ) );

        // Schema
        WP_CLI::line( '' );
        WP_CLI::line( WP_CLI::colorize( '%YSchema:%n' ) );
        WP_CLI::line( "  Enabled: " . ( $stats['schema']['enabled'] ? 'Yes' : 'No' ) );
        if ( ! empty( $stats['schema']['types'] ) ) {
            WP_CLI::line( "  Types: " . implode( ', ', array_keys( $stats['schema']['types'] ) ) );
        }

        // Overall health
        WP_CLI::line( '' );
        $overall_color = $health['overall'] === 'ok' ? '%G' : ( $health['overall'] === 'warning' ? '%Y' : '%R' );
        WP_CLI::line( WP_CLI::colorize( "%YOverall Health:%n " . $overall_color . ucfirst( $health['overall'] ) . '%n' ) );

        WP_CLI::line( '' );
    }

    /**
     * Run health checks
     *
     * ## EXAMPLES
     *
     *     wp getcited check
     *
     * @subcommand check
     */
    public function check( $args, $assoc_args ) {
        WP_CLI::line( 'Running health checks...' );
        WP_CLI::line( '' );

        $health = GetCited_Health_Check::instance()->refresh();

        $checks = array(
            'llms_txt' => 'llms.txt',
            'robots_txt' => 'robots.txt',
            'schema' => 'Schema',
            'rewrite_rules' => 'Rewrite Rules',
            'crawler_list' => 'Crawler List',
        );

        foreach ( $checks as $key => $label ) {
            if ( ! isset( $health[ $key ] ) ) {
                continue;
            }

            $status = $health[ $key ]['status'];
            $message = $health[ $key ]['message'];

            $icon = $status === 'ok' ? '✓' : ( $status === 'warning' ? '!' : '✗' );
            $color = $status === 'ok' ? '%G' : ( $status === 'warning' ? '%Y' : '%R' );

            WP_CLI::line( WP_CLI::colorize( "{$color}{$icon}%n {$label}: {$message}" ) );
        }

        WP_CLI::line( '' );

        if ( $health['overall'] === 'ok' ) {
            WP_CLI::success( 'All checks passed!' );
        } elseif ( $health['overall'] === 'warning' ) {
            WP_CLI::warning( 'Some issues need attention.' );
        } else {
            WP_CLI::error( 'Health check failed.', false );
        }
    }

    /**
     * Clear caches and flush rewrite rules
     *
     * ## EXAMPLES
     *
     *     wp getcited flush
     *
     * @subcommand flush
     */
    public function flush( $args, $assoc_args ) {
        // Delete transients
        delete_transient( 'getcited_crawler_list' );
        delete_transient( 'getcited_health_status' );

        // Flush rewrite rules
        flush_rewrite_rules();

        WP_CLI::success( 'Caches cleared and rewrite rules flushed.' );
    }

    /**
     * Export settings to JSON
     *
     * ## OPTIONS
     *
     * [--file=<file>]
     * : Output file path. If not specified, outputs to stdout.
     *
     * ## EXAMPLES
     *
     *     wp getcited export
     *     wp getcited export --file=/path/to/settings.json
     *
     * @subcommand export
     */
    public function export( $args, $assoc_args ) {
        $settings = GetCited_Settings::instance();
        $json = $settings->export();

        if ( isset( $assoc_args['file'] ) ) {
            $file = $assoc_args['file'];
            $result = file_put_contents( $file, $json );

            if ( $result === false ) {
                WP_CLI::error( "Failed to write to {$file}" );
            }

            WP_CLI::success( "Settings exported to {$file}" );
        } else {
            WP_CLI::line( $json );
        }
    }

    /**
     * Import settings from JSON
     *
     * ## OPTIONS
     *
     * <file>
     * : Path to JSON file to import
     *
     * [--merge]
     * : Merge with existing settings instead of replacing
     *
     * ## EXAMPLES
     *
     *     wp getcited import /path/to/settings.json
     *     wp getcited import /path/to/settings.json --merge
     *
     * @subcommand import
     */
    public function import( $args, $assoc_args ) {
        if ( empty( $args[0] ) ) {
            WP_CLI::error( 'Please specify a file to import' );
        }

        $file = $args[0];

        if ( ! file_exists( $file ) ) {
            WP_CLI::error( "File not found: {$file}" );
        }

        $json = file_get_contents( $file );
        $merge = isset( $assoc_args['merge'] );

        $settings = GetCited_Settings::instance();
        $result = $settings->import( $json, $merge );

        if ( is_wp_error( $result ) ) {
            WP_CLI::error( $result->get_error_message() );
        }

        WP_CLI::success( 'Settings imported successfully.' );
    }

    /**
     * List all crawlers and their status
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format. Options: table, json, csv. Default: table.
     *
     * ## EXAMPLES
     *
     *     wp getcited crawlers
     *     wp getcited crawlers --format=json
     *
     * @subcommand crawlers
     */
    public function crawlers( $args, $assoc_args ) {
        $crawler_list = GetCited_Crawler_List::instance();
        $settings = GetCited_Settings::instance();
        
        $crawlers = $crawler_list->get_all();
        $states = $settings->get( 'crawlers' );

        $items = array();
        foreach ( $crawlers as $crawler ) {
            $items[] = array(
                'name' => $crawler['name'],
                'company' => $crawler['company'],
                'status' => $states[ $crawler['name'] ] ?? 'allow',
                'purpose' => $crawler['purpose'],
            );
        }

        $format = $assoc_args['format'] ?? 'table';

        WP_CLI\Utils\format_items( $format, $items, array( 'name', 'company', 'status', 'purpose' ) );
    }

    /**
     * Set crawler status
     *
     * ## OPTIONS
     *
     * <name>
     * : Crawler name
     *
     * <status>
     * : Status: allow or block
     *
     * ## EXAMPLES
     *
     *     wp getcited crawler GPTBot allow
     *     wp getcited crawler CCBot block
     *
     * @subcommand crawler
     */
    public function crawler( $args, $assoc_args ) {
        if ( count( $args ) < 2 ) {
            WP_CLI::error( 'Usage: wp getcited crawler <name> <allow|block>' );
        }

        $name = $args[0];
        $status = $args[1];

        if ( ! in_array( $status, array( 'allow', 'block' ), true ) ) {
            WP_CLI::error( 'Status must be "allow" or "block"' );
        }

        $settings = GetCited_Settings::instance();
        $crawlers = $settings->get( 'crawlers' );
        $crawlers[ $name ] = $status;
        $settings->set( 'crawlers', $crawlers );

        WP_CLI::success( "{$name} set to {$status}" );
    }

    /**
     * Output llms.txt content
     *
     * ## EXAMPLES
     *
     *     wp getcited llms-txt
     *
     * @subcommand llms-txt
     */
    public function llms_txt( $args, $assoc_args ) {
        $llms = GetCited_Llms_Txt::instance();
        WP_CLI::line( $llms->get_content() );
    }

    /**
     * Output robots.txt rules
     *
     * ## EXAMPLES
     *
     *     wp getcited robots-txt
     *
     * @subcommand robots-txt
     */
    public function robots_txt( $args, $assoc_args ) {
        $robots = GetCited_Robots::instance();
        WP_CLI::line( $robots->get_preview() );
    }

    /**
     * Analyze citability of a post
     *
     * ## OPTIONS
     *
     * <post_id>
     * : Post ID to analyze
     *
     * [--format=<format>]
     * : Output format. Options: table, json. Default: table.
     *
     * ## EXAMPLES
     *
     *     wp getcited citability 123
     *     wp getcited citability 123 --format=json
     *
     * @subcommand citability
     */
    public function citability( $args, $assoc_args ) {
        if ( empty( $args[0] ) ) {
            WP_CLI::error( 'Please specify a post ID' );
        }

        $post_id = absint( $args[0] );
        $post = get_post( $post_id );

        if ( ! $post ) {
            WP_CLI::error( "Post {$post_id} not found" );
        }

        $citability = GetCited_Citability::instance();
        $result = $citability->analyze_post( $post_id );

        $format = $assoc_args['format'] ?? 'table';

        if ( $format === 'json' ) {
            WP_CLI::line( wp_json_encode( $result, JSON_PRETTY_PRINT ) );
            return;
        }

        // Display results
        WP_CLI::line( '' );
        WP_CLI::line( WP_CLI::colorize( '%BCitability Score: ' . $result['score'] . '/100%n' ) );
        WP_CLI::line( '' );

        $items = array();
        foreach ( $result['factors'] as $key => $factor ) {
            $icon = ( $factor['passed'] ?? ( $factor['score'] > 0 ) ) ? '✓' : '✗';
            $items[] = array(
                'factor' => $result['rubric'][ $key ]['label'] ?? $key,
                'score' => $factor['score'] . '/' . ( $result['rubric'][ $key ]['max_points'] ?? '?' ),
                'status' => $icon . ' ' . $factor['message'],
            );
        }

        WP_CLI\Utils\format_items( 'table', $items, array( 'factor', 'score', 'status' ) );

        if ( ! empty( $result['recommendations'] ) ) {
            WP_CLI::line( '' );
            WP_CLI::line( WP_CLI::colorize( '%YRecommendations:%n' ) );
            foreach ( $result['recommendations'] as $i => $rec ) {
                WP_CLI::line( ( $i + 1 ) . '. ' . $rec );
            }
        }

        WP_CLI::line( '' );
    }

    /**
     * Run citability audit on recent posts
     *
     * ## OPTIONS
     *
     * [--limit=<limit>]
     * : Number of posts to analyze. Default: 5.
     *
     * ## EXAMPLES
     *
     *     wp getcited audit
     *     wp getcited audit --limit=10
     *
     * @subcommand audit
     */
    public function audit( $args, $assoc_args ) {
        $limit = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 5;

        $posts = get_posts( array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ) );

        if ( empty( $posts ) ) {
            WP_CLI::warning( 'No posts found to analyze.' );
            return;
        }

        WP_CLI::line( "Analyzing {$limit} most recent posts..." );
        WP_CLI::line( '' );

        $citability = GetCited_Citability::instance();
        $items = array();
        $total_score = 0;

        foreach ( $posts as $post ) {
            $result = $citability->analyze_post( $post->ID );
            
            update_post_meta( $post->ID, '_getcited_citability_score', $result['score'] );
            update_post_meta( $post->ID, '_getcited_last_audit', current_time( 'c' ) );

            $items[] = array(
                'id' => $post->ID,
                'title' => substr( $post->post_title, 0, 40 ) . ( strlen( $post->post_title ) > 40 ? '...' : '' ),
                'score' => $result['score'] . '/100',
            );

            $total_score += $result['score'];
        }

        WP_CLI\Utils\format_items( 'table', $items, array( 'id', 'title', 'score' ) );

        $average = round( $total_score / count( $posts ) );
        WP_CLI::line( '' );
        WP_CLI::line( WP_CLI::colorize( "%BAverage Score: {$average}/100%n" ) );
        WP_CLI::line( '' );
    }

    /**
     * View, clear, or export the AI crawler request log
     *
     * ## OPTIONS
     *
     * [--clear]
     * : Clear all log entries
     *
     * [--export=<file>]
     * : Export log to CSV file
     *
     * [--limit=<number>]
     * : Number of entries to show (default: 20)
     *
     * [--format=<format>]
     * : Output format. Options: table, json, csv. Default: table.
     *
     * ## EXAMPLES
     *
     *     wp getcited crawler-log
     *     wp getcited crawler-log --limit=50
     *     wp getcited crawler-log --export=/tmp/log.csv
     *     wp getcited crawler-log --clear
     *
     * @subcommand crawler-log
     */
    public function crawler_log( $args, $assoc_args ) {
        global $wpdb;
        $table_name = esc_sql( $wpdb->prefix . 'getcited_llms_requests' );

        // Handle --clear flag.
        if ( isset( $assoc_args['clear'] ) ) {
            WP_CLI::confirm( 'Are you sure you want to clear all crawler log entries?' );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe from $wpdb->prefix
            $wpdb->query( "TRUNCATE TABLE {$table_name}" );

            WP_CLI::success( 'Crawler log cleared.' );
            return;
        }

        // Get log entries.
        $limit = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 20;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe from $wpdb->prefix
        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT request_time, bot_name, category, user_agent
                 FROM {$table_name}
                 ORDER BY request_time DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        if ( empty( $entries ) ) {
            WP_CLI::warning( 'No crawler log entries found.' );
            return;
        }

        // Handle --export flag.
        if ( isset( $assoc_args['export'] ) ) {
            $file = $assoc_args['export'];

            // Get ALL entries for export (not limited).
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe from $wpdb->prefix
            $all_entries = $wpdb->get_results(
                "SELECT request_time, bot_name, category, user_agent
                 FROM {$table_name}
                 ORDER BY request_time DESC",
                ARRAY_A
            );

            $this->export_log_to_csv( $all_entries, $file );
            return;
        }

        // Format output.
        $format = $assoc_args['format'] ?? 'table';

        // Prepare items for display.
        $items = array();
        foreach ( $entries as $entry ) {
            $items[] = array(
                'time'       => $entry['request_time'],
                'bot'        => $entry['bot_name'] ?: 'Unknown',
                'category'   => $entry['category'] ?: 'unknown',
                'user_agent' => mb_substr( $entry['user_agent'], 0, 50 ) .
                               ( mb_strlen( $entry['user_agent'] ) > 50 ? '...' : '' ),
            );
        }

        if ( 'json' === $format ) {
            WP_CLI::line( wp_json_encode( $entries, JSON_PRETTY_PRINT ) );
        } else {
            WP_CLI\Utils\format_items( $format, $items, array( 'time', 'bot', 'category', 'user_agent' ) );
        }

        // Show summary.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe from $wpdb->prefix
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

        WP_CLI::line( '' );
        WP_CLI::line( sprintf( 'Showing %d of %d total entries.', count( $entries ), $total ) );
    }

    /**
     * Export log entries to CSV file
     *
     * @param array  $entries Log entries.
     * @param string $file    Output file path.
     */
    private function export_log_to_csv( $entries, $file ) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $handle = fopen( $file, 'w' );

        if ( false === $handle ) {
            WP_CLI::error( "Cannot write to file: {$file}" );
        }

        // Write header row.
        fputcsv( $handle, array( 'Timestamp', 'Bot Name', 'Category', 'User Agent' ) );

        // Write data rows.
        foreach ( $entries as $entry ) {
            fputcsv( $handle, array(
                $entry['request_time'],
                $entry['bot_name'] ?: 'Unknown',
                $entry['category'] ?: 'unknown',
                $entry['user_agent'],
            ) );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose( $handle );

        WP_CLI::success( sprintf( 'Exported %d entries to %s', count( $entries ), $file ) );
    }
}

// Register commands
WP_CLI::add_command( 'getcited', 'GetCited_CLI' );

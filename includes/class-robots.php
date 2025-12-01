<?php
/**
 * Robots.txt integration for GetCited
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Robots class
 */
class GetCited_Robots {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
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
        // Hook into robots.txt generation
        add_filter( 'robots_txt', array( $this, 'append_rules' ), 10, 2 );
    }

    /**
     * Append AI crawler rules to robots.txt
     */
    public function append_rules( $output, $public ) {
        // Only add rules if site is public
        if ( ! $public ) {
            return $output;
        }

        $rules = $this->generate_rules();
        
        if ( empty( $rules ) ) {
            return $output;
        }

        // Ensure there's a newline before our section
        if ( ! empty( $output ) && substr( $output, -1 ) !== "\n" ) {
            $output .= "\n";
        }

        $output .= "\n" . $rules;

        return $output;
    }

    /**
     * Generate robots.txt rules for AI crawlers
     */
    public function generate_rules() {
        $settings = GetCited_Settings::instance();
        $crawler_list = GetCited_Crawler_List::instance();
        
        $crawler_states = $settings->get( 'crawlers' );
        $custom_crawlers = $settings->get( 'custom_crawlers' );
        $all_crawlers = $crawler_list->get_all();

        $lines = array();
        $lines[] = '# === GetCited AI Crawler Rules ===';
        $lines[] = '# Manage these settings at: ' . admin_url( 'admin.php?page=getcited-crawlers' );
        $lines[] = '';

        // Process official crawlers
        foreach ( $all_crawlers as $crawler ) {
            $name = $crawler['name'];
            $user_agent = $crawler['user_agent'];
            $status = $crawler_states[ $name ] ?? 'allow';
            
            $lines[] = 'User-agent: ' . $user_agent;
            
            if ( $status === 'allow' ) {
                $lines[] = 'Allow: /';
            } else {
                $lines[] = 'Disallow: /';
            }
            
            $lines[] = '';
        }

        // Process custom crawlers
        if ( ! empty( $custom_crawlers ) ) {
            $lines[] = '# Custom crawlers';
            
            foreach ( $custom_crawlers as $crawler ) {
                if ( empty( $crawler['user_agent'] ) ) {
                    continue;
                }
                
                $lines[] = 'User-agent: ' . $crawler['user_agent'];
                
                if ( $crawler['action'] === 'allow' ) {
                    $lines[] = 'Allow: /';
                } else {
                    $lines[] = 'Disallow: /';
                }
                
                $lines[] = '';
            }
        }

        // Add llms.txt reference if enabled
        $llms_enabled = $settings->get( 'llms_txt_enabled' );
        if ( $llms_enabled ) {
            $lines[] = '# AI discoverability file';
            $lines[] = '# llms.txt: ' . home_url( '/llms.txt' );
            $lines[] = '';
        }

        $lines[] = '# === End GetCited Rules ===';

        $rules = implode( "\n", $lines );

        return apply_filters( 'getcited_robots_txt_rules', $rules );
    }

    /**
     * Get preview of robots.txt rules (for admin display)
     */
    public function get_preview() {
        return $this->generate_rules();
    }

    /**
     * Get the full robots.txt content as it would appear
     */
    public function get_full_robots_txt() {
        // Get WordPress default output
        $public = get_option( 'blog_public' );
        
        ob_start();
        do_robots();
        $output = ob_get_clean();

        return $output;
    }

    /**
     * Check if a physical robots.txt file exists (which would override WordPress)
     */
    public function physical_file_exists() {
        $robots_path = ABSPATH . 'robots.txt';
        return file_exists( $robots_path );
    }

    /**
     * Get status info for dashboard
     */
    public function get_status() {
        $settings = GetCited_Settings::instance();
        $crawler_states = $settings->get( 'crawlers' );
        
        $allowed = 0;
        $blocked = 0;
        
        foreach ( $crawler_states as $status ) {
            if ( $status === 'allow' ) {
                $allowed++;
            } else {
                $blocked++;
            }
        }

        return array(
            'allowed' => $allowed,
            'blocked' => $blocked,
            'total' => count( $crawler_states ),
            'physical_file_exists' => $this->physical_file_exists(),
        );
    }
}

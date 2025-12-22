<?php
/**
 * Settings management for GetCited
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings class
 */
class GetCited_Settings {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Option name in database
     */
    const OPTION_NAME = 'getcited_settings';

    /**
     * Cached settings
     */
    private $settings = null;

    /**
     * Default settings structure
     */
    private $defaults = array(
        // Crawler states
        'crawlers' => array(),

        // Custom crawlers added by user
        'custom_crawlers' => array(),

        // llms.txt
        'llms_txt_content' => '',
        'llms_txt_enabled' => true,
        'llms_txt_source' => 'getcited', // 'getcited' or 'existing' - which llms.txt to use
        'llms_write_physical' => true,
        'existing_llms_txt_detected' => false, // Flag: existing non-GetCited llms.txt found on activation
        'existing_llms_txt_assessment' => '', // 'basic', 'moderate', or 'substantial'
        'llms_founder_name' => '',
        'llms_founder_title' => '',
        'llms_site_expertise' => '',
        'llms_update_frequency' => '',
        'llms_citation_format' => '',
        'llms_use_cases' => '', // Use Cases for AI section content

        // robots.txt
        'robots_write_physical' => true,

        // Schema
        'schema_enabled' => true,
        'schema_force_enabled' => false, // User override when auto-disabled
        'schema_last_scan' => 0,         // Timestamp of last detection scan
        'schema_detected_source' => '',  // 'yoast', 'rankmath', 'json_ld', etc.
        'schema_types' => array(
            'organization' => true,
            'article' => true,
            'author' => true,
            'faq' => true,
        ),
        'organization' => array(
            'name' => '',
            'logo_url' => '',
            'social_urls' => array(),
            'linkedin_company' => '',    // LinkedIn company page
            'wikipedia' => '',           // Wikipedia/Wikidata URL
            'crunchbase' => '',          // Crunchbase URL
        ),

        // Site configuration
        'site_type' => 'blog',
        'wizard_completed' => false,

        // Request logging
        'request_logging_enabled' => true,
        'request_log_retention' => 90, // Days to keep request logs

        // Advanced
        'debug_mode' => false,
        'keep_on_delete' => false,

        // Pro (future)
        'license_key' => '',
        'license_status' => 'free',
        'site_uuid' => '',
        'ga4_connected' => false,
        'ga4_property_id' => '',
        'waitlist_submitted' => false, // Remember if user joined waitlist

        // Version tracking
        'db_version' => '1.0',

        // Citation guidelines (v1.5.1)
        'citation_guidelines' => array(
            'enabled'         => false,
            'citation_format' => '',
            'accuracy_notes'  => '',
            'restrictions'    => '',
            'freshness_note'  => '',
            'contact_email'   => '',
        ),
    );

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
        $this->load();
    }

    /**
     * Load settings from database
     */
    private function load() {
        $this->settings = get_option( self::OPTION_NAME, array() );
        
        // Merge with defaults
        $this->settings = $this->merge_defaults( $this->settings );
    }

    /**
     * Merge settings with defaults recursively
     */
    private function merge_defaults( $settings ) {
        $merged = $this->defaults;

        foreach ( $settings as $key => $value ) {
            if ( is_array( $value ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
                $merged[ $key ] = array_merge( $merged[ $key ], $value );
            } else {
                $merged[ $key ] = $value;
            }
        }

        return $merged;
    }

    /**
     * Initialize default settings on activation
     *
     * Uses add_option() which is atomic - if the option already exists,
     * this does nothing, preventing race conditions.
     */
    public function init_defaults() {
        // Set default crawler states (all allowed)
        $crawler_list = GetCited_Crawler_List::instance();
        $crawlers = $crawler_list->get_all();

        $crawler_states = array();
        foreach ( $crawlers as $crawler ) {
            $crawler_states[ $crawler['name'] ] = 'allow';
        }

        $this->defaults['crawlers'] = $crawler_states;

        // Set organization name from site
        $this->defaults['organization']['name'] = get_bloginfo( 'name' );

        // Generate default llms.txt
        $this->defaults['llms_txt_content'] = $this->generate_default_llms_txt();

        // Use add_option which is atomic - only adds if option doesn't exist
        // This prevents race conditions with concurrent requests
        $added = add_option( self::OPTION_NAME, $this->defaults );

        if ( $added ) {
            $this->settings = $this->defaults;
        } else {
            // Option already existed, reload settings
            $this->load();
        }
    }

    /**
     * Generate default llms.txt content
     */
    private function generate_default_llms_txt() {
        $site_name = get_bloginfo( 'name' );
        $site_desc = get_bloginfo( 'description' );
        $site_url = home_url();

        $content = "# {$site_name}\n\n";
        
        if ( ! empty( $site_desc ) ) {
            $content .= "> {$site_desc}\n\n";
        }

        $content .= "Welcome to {$site_name}. This file helps AI systems understand our content.\n\n";
        $content .= "## About\n\n";
        $content .= "- Website: {$site_url}\n";
        $content .= "- Content Type: Blog/Website\n\n";
        $content .= "## Sections\n\n";
        $content .= "- [Home]({$site_url}): Main page\n";
        $content .= "- [Blog]({$site_url}/blog): Latest articles\n\n";
        $content .= "## Contact\n\n";
        $content .= "For inquiries, please visit our website.\n\n";
        $content .= "---\n";
        $content .= "# Generated by GetCited\n";

        return $content;
    }

    /**
     * Get all settings
     */
    public function get_all() {
        return $this->settings;
    }

    /**
     * Get single setting
     */
    public function get( $key, $default = null ) {
        if ( isset( $this->settings[ $key ] ) ) {
            return $this->settings[ $key ];
        }
        return $default !== null ? $default : ( $this->defaults[ $key ] ?? null );
    }

    /**
     * Set single setting
     */
    public function set( $key, $value ) {
        $old_value = $this->settings[ $key ] ?? null;
        $this->settings[ $key ] = $this->sanitize_setting( $key, $value );
        $this->save();

        // Fire hook
        do_action( 'getcited_setting_updated', $key, $this->settings[ $key ], $old_value );

        return true;
    }

    /**
     * Set multiple settings at once
     */
    public function set_multiple( $settings ) {
        $old_settings = $this->settings;

        foreach ( $settings as $key => $value ) {
            $this->settings[ $key ] = $this->sanitize_setting( $key, $value );
        }

        $this->save();

        // Fire hook
        do_action( 'getcited_settings_saved', $this->settings, $old_settings );

        return true;
    }

    /**
     * Save settings to database
     */
    private function save() {
        update_option( self::OPTION_NAME, $this->settings );

        // Clear object cache to ensure other processes get fresh data
        wp_cache_delete( self::OPTION_NAME, 'options' );

        // Invalidate visibility score cache (settings affect score calculation).
        delete_transient( 'getcited_visibility_score' );
    }

    /**
     * Sanitize setting value based on key
     */
    private function sanitize_setting( $key, $value ) {
        switch ( $key ) {
            case 'crawlers':
                // Whitelist crawler status values
                if ( is_array( $value ) ) {
                    foreach ( $value as $crawler => $status ) {
                        $value[ sanitize_text_field( $crawler ) ] = in_array( $status, array( 'allow', 'block' ), true ) ? $status : 'allow';
                    }
                }
                return $value;

            case 'custom_crawlers':
                if ( is_array( $value ) ) {
                    return array_map( function( $crawler ) {
                        return array(
                            'user_agent' => sanitize_text_field( $crawler['user_agent'] ?? '' ),
                            'name' => sanitize_text_field( $crawler['name'] ?? '' ),
                            'action' => in_array( $crawler['action'] ?? 'allow', array( 'allow', 'block' ), true ) ? $crawler['action'] : 'allow',
                        );
                    }, $value );
                }
                return array();

            case 'llms_txt_content':
            case 'llms_use_cases':
                // Allow markdown-safe content
                return wp_kses_post( $value );

            case 'llms_txt_enabled':
            case 'llms_write_physical':
            case 'robots_write_physical':
            case 'schema_enabled':
            case 'schema_force_enabled':
            case 'wizard_completed':
            case 'debug_mode':
            case 'keep_on_delete':
            case 'ga4_connected':
            case 'waitlist_submitted':
                return (bool) $value;

            case 'schema_last_scan':
                return absint( $value );

            case 'schema_detected_source':
                return sanitize_text_field( $value );

            case 'llms_txt_source':
                return in_array( $value, array( 'getcited', 'existing' ), true ) ? $value : 'getcited';

            case 'existing_llms_txt_detected':
                return (bool) $value;

            case 'existing_llms_txt_assessment':
                return in_array( $value, array( 'basic', 'moderate', 'substantial', '' ), true ) ? $value : '';

            case 'llms_founder_name':
            case 'llms_founder_title':
            case 'llms_site_expertise':
            case 'llms_update_frequency':
                return sanitize_text_field( $value );

            case 'llms_citation_format':
                // Allow markdown-safe content for custom citation format
                return wp_kses_post( $value );

            case 'schema_types':
                if ( is_array( $value ) ) {
                    return array(
                        'organization' => (bool) ( $value['organization'] ?? false ),
                        'article' => (bool) ( $value['article'] ?? false ),
                        'author' => (bool) ( $value['author'] ?? false ),
                        'faq' => (bool) ( $value['faq'] ?? false ),
                    );
                }
                return $this->defaults['schema_types'];

            case 'organization':
                if ( is_array( $value ) ) {
                    return array(
                        'name' => sanitize_text_field( $value['name'] ?? '' ),
                        'logo_url' => esc_url_raw( $value['logo_url'] ?? '' ),
                        'social_urls' => array_map( 'esc_url_raw', (array) ( $value['social_urls'] ?? array() ) ),
                        'linkedin_company' => esc_url_raw( $value['linkedin_company'] ?? '' ),
                        'wikipedia' => esc_url_raw( $value['wikipedia'] ?? '' ),
                        'crunchbase' => esc_url_raw( $value['crunchbase'] ?? '' ),
                    );
                }
                return $this->defaults['organization'];

            case 'site_type':
                $valid_types = array(
                    'blog',
                    'business',
                    'news',
                    'ecommerce',
                    'portfolio',
                    'nonprofit',
                    'education',
                    'community',
                    'other'
                );
                return in_array( $value, $valid_types, true ) ? $value : 'blog';

            case 'license_key':
            case 'site_uuid':
            case 'ga4_property_id':
                return sanitize_text_field( $value );

            case 'license_status':
                $valid_statuses = array( 'free', 'pro', 'agency' );
                return in_array( $value, $valid_statuses, true ) ? $value : 'free';

            case 'db_version':
                return sanitize_text_field( $value );

            case 'citation_guidelines':
                if ( is_array( $value ) ) {
                    return array(
                        'enabled'         => (bool) ( $value['enabled'] ?? false ),
                        'citation_format' => sanitize_text_field( $value['citation_format'] ?? '' ),
                        'accuracy_notes'  => sanitize_textarea_field( $value['accuracy_notes'] ?? '' ),
                        'restrictions'    => sanitize_textarea_field( $value['restrictions'] ?? '' ),
                        'freshness_note'  => sanitize_text_field( $value['freshness_note'] ?? '' ),
                        'contact_email'   => sanitize_email( $value['contact_email'] ?? '' ),
                    );
                }
                return $this->defaults['citation_guidelines'];

            default:
                return $value;
        }
    }

    /**
     * Delete all settings (for uninstall)
     */
    public static function delete_all() {
        delete_option( self::OPTION_NAME );
    }

    /**
     * Export settings as JSON
     */
    public function export() {
        $export = $this->settings;

        // Remove sensitive data
        unset( $export['license_key'] );
        unset( $export['site_uuid'] );

        // Add metadata
        $export['_export'] = array(
            'version' => GETCITED_VERSION,
            'date' => current_time( 'c' ),
            'site_url' => home_url(),
        );

        return wp_json_encode( $export, JSON_PRETTY_PRINT );
    }

    /**
     * Import settings from JSON
     */
    public function import( $json, $merge = false ) {
        $data = json_decode( $json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'invalid_json', __( 'Invalid JSON format', 'getcited' ) );
        }

        // Remove metadata
        unset( $data['_export'] );

        // Validate structure
        if ( ! is_array( $data ) ) {
            return new WP_Error( 'invalid_format', __( 'Invalid settings format', 'getcited' ) );
        }

        if ( $merge ) {
            // Merge with existing
            foreach ( $data as $key => $value ) {
                $this->set( $key, $value );
            }
        } else {
            // Replace all (keep certain keys)
            $preserve = array(
                'license_key' => $this->settings['license_key'],
                'site_uuid' => $this->settings['site_uuid'],
                'db_version' => $this->settings['db_version'],
            );

            $this->settings = $this->merge_defaults( $data );
            $this->settings = array_merge( $this->settings, $preserve );
            $this->save();
        }

        return true;
    }
}

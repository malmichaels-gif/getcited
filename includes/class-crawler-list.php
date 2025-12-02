<?php
/**
 * AI Crawler List management for GetCited
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Crawler List class
 */
class GetCited_Crawler_List {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Transient name for cached remote list
     */
    const TRANSIENT_NAME = 'getcited_crawler_list';

    /**
     * Remote endpoint URL
     */
    const REMOTE_URL = 'https://heytc.com/getcited/api/crawlers.json';

    /**
     * Cached crawler list
     */
    private $crawlers = null;

    /**
     * List metadata
     */
    private $version = '1.0';
    private $updated = null;

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
     * Load crawler list (remote if available, fallback to bundled)
     */
    private function load() {
        // Try cached remote first
        $cached = get_transient( self::TRANSIENT_NAME );
        
        if ( $cached && isset( $cached['crawlers'] ) ) {
            $this->crawlers = $cached['crawlers'];
            $this->version = $cached['version'] ?? '1.0';
            $this->updated = $cached['updated'] ?? null;
            return;
        }

        // Fall back to bundled list
        $this->load_bundled();
    }

    /**
     * Load bundled crawler list
     */
    private function load_bundled() {
        $bundled_file = GETCITED_PLUGIN_DIR . 'data/crawlers.json';

        if ( file_exists( $bundled_file ) ) {
            // Use wp_json_file_decode for consistent JSON parsing (WP 5.9+)
            $data = wp_json_file_decode( $bundled_file, array( 'associative' => true ) );

            if ( $data && isset( $data['crawlers'] ) ) {
                $this->crawlers = $data['crawlers'];
                $this->version = $data['version'] ?? '1.0';
                $this->updated = $data['updated'] ?? null;
                return;
            }
        }

        // Ultimate fallback: hardcoded list
        $this->crawlers = $this->get_hardcoded_list();
        $this->version = '1.0';
        $this->updated = '2025-11-28';
    }

    /**
     * Fetch remote crawler list
     */
    public function fetch_remote_list() {
        $response = wp_remote_get( self::REMOTE_URL, array(
            'timeout' => 5,
            'headers' => array(
                'User-Agent' => 'GetCited/' . GETCITED_VERSION,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled
                error_log( '[GetCited] Failed to fetch remote crawler list: ' . $response->get_error_message() );
            }
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! $data || ! isset( $data['crawlers'] ) || ! is_array( $data['crawlers'] ) ) {
            return false;
        }

        // Validate structure
        foreach ( $data['crawlers'] as $crawler ) {
            if ( ! isset( $crawler['name'] ) || ! isset( $crawler['user_agent'] ) ) {
                return false;
            }
        }

        // Cache for 24 hours
        set_transient( self::TRANSIENT_NAME, $data, DAY_IN_SECONDS );

        // Update local state
        $this->crawlers = $data['crawlers'];
        $this->version = $data['version'] ?? '1.0';
        $this->updated = $data['updated'] ?? current_time( 'Y-m-d' );

        // Fire hook
        do_action( 'getcited_crawler_list_updated', $this->crawlers );

        return true;
    }

    /**
     * Get all crawlers
     */
    public function get_all() {
        return apply_filters( 'getcited_crawler_list', $this->crawlers );
    }

    /**
     * Get crawlers grouped by company
     */
    public function get_grouped() {
        $grouped = array();

        foreach ( $this->crawlers as $crawler ) {
            $company = $crawler['company'] ?? 'Other';
            if ( ! isset( $grouped[ $company ] ) ) {
                $grouped[ $company ] = array();
            }
            $grouped[ $company ][] = $crawler;
        }

        // Sort companies alphabetically, but put major ones first
        $priority = array( 'OpenAI', 'Anthropic', 'Perplexity', 'Google', 'Apple', 'Meta' );
        uksort( $grouped, function( $a, $b ) use ( $priority ) {
            $pos_a = array_search( $a, $priority );
            $pos_b = array_search( $b, $priority );
            
            if ( $pos_a !== false && $pos_b !== false ) {
                return $pos_a - $pos_b;
            }
            if ( $pos_a !== false ) {
                return -1;
            }
            if ( $pos_b !== false ) {
                return 1;
            }
            return strcasecmp( $a, $b );
        } );

        return $grouped;
    }

    /**
     * Get single crawler by name
     */
    public function get( $name ) {
        foreach ( $this->crawlers as $crawler ) {
            if ( $crawler['name'] === $name ) {
                return $crawler;
            }
        }
        return null;
    }

    /**
     * Get crawler list version
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get last updated date
     */
    public function get_last_updated() {
        return $this->updated;
    }

    /**
     * Check if using cached remote or bundled
     */
    public function is_remote_cached() {
        return get_transient( self::TRANSIENT_NAME ) !== false;
    }

    /**
     * Hardcoded crawler list (ultimate fallback)
     */
    private function get_hardcoded_list() {
        return array(
            // OpenAI
            array(
                'name' => 'GPTBot',
                'company' => 'OpenAI',
                'user_agent' => 'GPTBot',
                'purpose' => 'ChatGPT training & web browsing',
                'recommended' => true,
            ),
            array(
                'name' => 'ChatGPT-User',
                'company' => 'OpenAI',
                'user_agent' => 'ChatGPT-User',
                'purpose' => 'ChatGPT browse mode (real-time)',
                'recommended' => true,
            ),
            array(
                'name' => 'OAI-SearchBot',
                'company' => 'OpenAI',
                'user_agent' => 'OAI-SearchBot',
                'purpose' => 'ChatGPT Search feature',
                'recommended' => true,
            ),

            // Anthropic
            array(
                'name' => 'anthropic-ai',
                'company' => 'Anthropic',
                'user_agent' => 'anthropic-ai',
                'purpose' => 'Claude training',
                'recommended' => true,
            ),
            array(
                'name' => 'ClaudeBot',
                'company' => 'Anthropic',
                'user_agent' => 'ClaudeBot',
                'purpose' => 'Claude web access & citations',
                'recommended' => true,
            ),
            array(
                'name' => 'Claude-Web',
                'company' => 'Anthropic',
                'user_agent' => 'Claude-Web',
                'purpose' => 'Claude web features',
                'recommended' => true,
            ),

            // Perplexity
            array(
                'name' => 'PerplexityBot',
                'company' => 'Perplexity',
                'user_agent' => 'PerplexityBot',
                'purpose' => 'AI search engine indexing',
                'recommended' => true,
            ),
            array(
                'name' => 'Perplexity-User',
                'company' => 'Perplexity',
                'user_agent' => 'Perplexity-User',
                'purpose' => 'User-triggered real-time fetches',
                'recommended' => true,
            ),

            // Google
            array(
                'name' => 'Google-Extended',
                'company' => 'Google',
                'user_agent' => 'Google-Extended',
                'purpose' => 'Gemini/Bard training',
                'recommended' => true,
            ),
            array(
                'name' => 'GoogleOther',
                'company' => 'Google',
                'user_agent' => 'GoogleOther',
                'purpose' => 'Google AI research and development',
                'recommended' => false,
            ),
            array(
                'name' => 'Google-CloudVertexBot',
                'company' => 'Google',
                'user_agent' => 'Google-CloudVertexBot',
                'purpose' => 'Vertex AI platform',
                'recommended' => false,
            ),

            // Apple
            array(
                'name' => 'Applebot',
                'company' => 'Apple',
                'user_agent' => 'Applebot',
                'purpose' => 'Siri and Spotlight search',
                'recommended' => true,
            ),
            array(
                'name' => 'Applebot-Extended',
                'company' => 'Apple',
                'user_agent' => 'Applebot-Extended',
                'purpose' => 'Apple Intelligence features',
                'recommended' => true,
            ),

            // Meta
            array(
                'name' => 'Meta-ExternalAgent',
                'company' => 'Meta',
                'user_agent' => 'Meta-ExternalAgent',
                'purpose' => 'Meta AI training',
                'recommended' => false,
            ),
            array(
                'name' => 'FacebookBot',
                'company' => 'Meta',
                'user_agent' => 'FacebookBot',
                'purpose' => 'Meta AI features',
                'recommended' => false,
            ),

            // Amazon
            array(
                'name' => 'Amazonbot',
                'company' => 'Amazon',
                'user_agent' => 'Amazonbot',
                'purpose' => 'Alexa and Amazon AI',
                'recommended' => true,
            ),

            // ByteDance
            array(
                'name' => 'Bytespider',
                'company' => 'ByteDance',
                'user_agent' => 'Bytespider',
                'purpose' => 'TikTok/Doubao AI training',
                'recommended' => false,
            ),

            // Common Crawl
            array(
                'name' => 'CCBot',
                'company' => 'Common Crawl',
                'user_agent' => 'CCBot',
                'purpose' => 'Open dataset (used by many LLMs)',
                'recommended' => true,
            ),

            // Cohere
            array(
                'name' => 'cohere-ai',
                'company' => 'Cohere',
                'user_agent' => 'cohere-ai',
                'purpose' => 'Enterprise AI training',
                'recommended' => false,
            ),

            // DuckDuckGo
            array(
                'name' => 'DuckAssistBot',
                'company' => 'DuckDuckGo',
                'user_agent' => 'DuckAssistBot',
                'purpose' => 'DuckDuckGo AI Assist',
                'recommended' => true,
            ),

            // DeepSeek
            array(
                'name' => 'Deepseek',
                'company' => 'DeepSeek',
                'user_agent' => 'Deepseek',
                'purpose' => 'DeepSeek AI training',
                'recommended' => false,
            ),

            // Mistral
            array(
                'name' => 'MistralAI-User',
                'company' => 'Mistral',
                'user_agent' => 'MistralAI-User',
                'purpose' => 'Mistral AI (European)',
                'recommended' => false,
            ),

            // xAI
            array(
                'name' => 'xAI-Grok-Bot',
                'company' => 'xAI',
                'user_agent' => 'xAI-Grok-Bot',
                'purpose' => 'Grok training',
                'recommended' => false,
            ),

            // Diffbot
            array(
                'name' => 'Diffbot',
                'company' => 'Diffbot',
                'user_agent' => 'Diffbot',
                'purpose' => 'Knowledge graph construction',
                'recommended' => false,
            ),

            // You.com
            array(
                'name' => 'YouBot',
                'company' => 'You.com',
                'user_agent' => 'YouBot',
                'purpose' => 'AI search engine',
                'recommended' => true,
            ),

            // Imagesift
            array(
                'name' => 'ImagesiftBot',
                'company' => 'Imagesift',
                'user_agent' => 'ImagesiftBot',
                'purpose' => 'Image AI training',
                'recommended' => false,
            ),
        );
    }
}

<?php
/**
 * Schema.org JSON-LD output for GetCited
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Schema class
 */
class GetCited_Schema {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Detected schema plugins
     */
    private $detected_plugins = array();

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
        // Detect other schema plugins
        add_action( 'plugins_loaded', array( $this, 'detect_schema_plugins' ), 20 );

        // Output schema in head
        add_action( 'wp_head', array( $this, 'output_schema' ), 5 );
    }

    /**
     * Detect other plugins that output schema
     */
    public function detect_schema_plugins() {
        $plugins = array();

        if ( defined( 'WPSEO_VERSION' ) ) {
            $plugins[] = array(
                'name' => 'Yoast SEO',
                'slug' => 'yoast',
            );
        }

        if ( defined( 'RANK_MATH_VERSION' ) ) {
            $plugins[] = array(
                'name' => 'Rank Math',
                'slug' => 'rankmath',
            );
        }

        if ( class_exists( 'BSF_AIOSRS_Pro' ) ) {
            $plugins[] = array(
                'name' => 'Schema Pro',
                'slug' => 'schema-pro',
            );
        }

        if ( defined( 'AIOSEO_VERSION' ) ) {
            $plugins[] = array(
                'name' => 'All in One SEO',
                'slug' => 'aioseo',
            );
        }

        if ( defined( 'SEOPRESS_VERSION' ) ) {
            $plugins[] = array(
                'name' => 'SEOPress',
                'slug' => 'seopress',
            );
        }

        $this->detected_plugins = $plugins;
    }

    /**
     * Get detected schema plugins
     */
    public function get_detected_plugins() {
        return $this->detected_plugins;
    }

    /**
     * Check if other schema plugins are active
     */
    public function has_conflict() {
        return ! empty( $this->detected_plugins );
    }

    /**
     * Output schema JSON-LD
     */
    public function output_schema() {
        $settings = GetCited_Settings::instance();

        // Check if schema is enabled
        if ( ! $settings->get( 'schema_enabled' ) ) {
            return;
        }

        // Skip if on WooCommerce product page
        if ( function_exists( 'is_product' ) && is_product() ) {
            return;
        }

        // Check for per-post disable
        if ( is_singular() ) {
            $post_id = get_the_ID();
            $disable = get_post_meta( $post_id, '_getcited_no_schema', true );
            if ( $disable ) {
                return;
            }
        }

        $schema_types = $settings->get( 'schema_types' );
        $schemas = array();

        // Organization schema (front page only)
        if ( $schema_types['organization'] && is_front_page() ) {
            $org_schema = $this->get_organization_schema();
            if ( $org_schema ) {
                $schemas[] = $org_schema;
            }
        }

        // Article schema (single posts)
        if ( $schema_types['article'] && is_singular( 'post' ) ) {
            $article_schema = $this->get_article_schema();
            if ( $article_schema ) {
                $schemas[] = $article_schema;
            }
        }

        // Author schema (with articles)
        if ( $schema_types['author'] && is_singular( 'post' ) ) {
            $author_schema = $this->get_author_schema();
            if ( $author_schema ) {
                $schemas[] = $author_schema;
            }
        }

        // FAQ schema (posts with FAQ blocks)
        if ( $schema_types['faq'] && is_singular() ) {
            $faq_schema = $this->get_faq_schema();
            if ( $faq_schema ) {
                $schemas[] = $faq_schema;
            }
        }

        // WebPage schema (pages)
        if ( is_page() && ! is_front_page() ) {
            $page_schema = $this->get_webpage_schema();
            if ( $page_schema ) {
                $schemas[] = $page_schema;
            }
        }

        // Output each schema
        foreach ( $schemas as $schema ) {
            $schema = apply_filters( 'getcited_schema_output', $schema, get_the_ID() );
            $this->render_schema( $schema );
        }
    }

    /**
     * Render schema as JSON-LD script tag
     */
    private function render_schema( $schema ) {
        if ( empty( $schema ) ) {
            return;
        }

        $json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

        echo "\n<!-- GetCited Schema -->\n";
        echo '<script type="application/ld+json">' . "\n";
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD requires unescaped JSON, wp_json_encode is safe
        echo $json . "\n";
        echo '</script>' . "\n";
    }

    /**
     * Get Organization schema
     */
    private function get_organization_schema() {
        $settings = GetCited_Settings::instance();
        $org = $settings->get( 'organization' );

        $name = ! empty( $org['name'] ) ? $org['name'] : get_bloginfo( 'name' );
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $name,
            'url' => home_url(),
        );

        // Add logo if set
        if ( ! empty( $org['logo_url'] ) ) {
            $schema['logo'] = $org['logo_url'];
        } elseif ( has_custom_logo() ) {
            $logo_id = get_theme_mod( 'custom_logo' );
            $logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
            if ( $logo_url ) {
                $schema['logo'] = $logo_url;
            }
        }

        // Add social URLs
        if ( ! empty( $org['social_urls'] ) && is_array( $org['social_urls'] ) ) {
            $social_urls = array_filter( $org['social_urls'] );
            if ( ! empty( $social_urls ) ) {
                $schema['sameAs'] = array_values( $social_urls );
            }
        }

        // Add description
        $description = get_bloginfo( 'description' );
        if ( ! empty( $description ) ) {
            $schema['description'] = $description;
        }

        return $schema;
    }

    /**
     * Get Article schema
     */
    private function get_article_schema() {
        global $post;

        if ( ! $post ) {
            return null;
        }

        // Determine article type
        $article_type = 'Article';
        
        // Check for news-like categories
        $categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
        $news_indicators = array( 'news', 'breaking', 'press release', 'announcement' );
        
        foreach ( $categories as $cat ) {
            if ( in_array( strtolower( $cat ), $news_indicators, true ) ) {
                $article_type = 'NewsArticle';
                break;
            }
        }

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => $article_type,
            'headline' => get_the_title(),
            'url' => get_permalink(),
            'datePublished' => get_the_date( 'c' ),
            'dateModified' => get_the_modified_date( 'c' ),
        );

        // Add description
        $excerpt = get_the_excerpt();
        if ( ! empty( $excerpt ) ) {
            $schema['description'] = wp_strip_all_tags( $excerpt );
        }

        // Add featured image
        if ( has_post_thumbnail() ) {
            $image_id = get_post_thumbnail_id();
            $image_url = wp_get_attachment_image_url( $image_id, 'large' );
            if ( $image_url ) {
                $schema['image'] = $image_url;
            }
        }

        // Add author reference
        $author = get_the_author();
        if ( $author ) {
            $schema['author'] = array(
                '@type' => 'Person',
                'name' => $author,
                'url' => get_author_posts_url( $post->post_author ),
            );
        }

        // Add publisher (Organization)
        $settings = GetCited_Settings::instance();
        $org = $settings->get( 'organization' );
        $org_name = ! empty( $org['name'] ) ? $org['name'] : get_bloginfo( 'name' );

        $schema['publisher'] = array(
            '@type' => 'Organization',
            'name' => $org_name,
            'url' => home_url(),
        );

        if ( ! empty( $org['logo_url'] ) ) {
            $schema['publisher']['logo'] = array(
                '@type' => 'ImageObject',
                'url' => $org['logo_url'],
            );
        }

        // Add word count
        $content = get_the_content();
        $word_count = str_word_count( wp_strip_all_tags( $content ) );
        $schema['wordCount'] = $word_count;

        return $schema;
    }

    /**
     * Get Author schema
     */
    private function get_author_schema() {
        global $post;

        if ( ! $post ) {
            return null;
        }

        $author_id = $post->post_author;
        $author = get_userdata( $author_id );

        if ( ! $author ) {
            return null;
        }

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $author->display_name,
            'url' => get_author_posts_url( $author_id ),
        );

        // Add bio if available
        $bio = get_the_author_meta( 'description', $author_id );
        if ( ! empty( $bio ) ) {
            $schema['description'] = wp_strip_all_tags( $bio );
        }

        // Add social links from user meta
        $social_fields = array(
            'twitter' => 'https://twitter.com/',
            'linkedin' => 'https://linkedin.com/in/',
            'facebook' => 'https://facebook.com/',
        );

        $same_as = array();
        foreach ( $social_fields as $field => $prefix ) {
            $value = get_the_author_meta( $field, $author_id );
            if ( ! empty( $value ) ) {
                // Handle both URLs and usernames
                if ( strpos( $value, 'http' ) === 0 ) {
                    $same_as[] = $value;
                } else {
                    $same_as[] = $prefix . $value;
                }
            }
        }

        // Check for user website
        $website = get_the_author_meta( 'url', $author_id );
        if ( ! empty( $website ) ) {
            $same_as[] = $website;
        }

        if ( ! empty( $same_as ) ) {
            $schema['sameAs'] = $same_as;
        }

        // Add avatar
        $avatar_url = get_avatar_url( $author_id, array( 'size' => 200 ) );
        if ( $avatar_url ) {
            $schema['image'] = $avatar_url;
        }

        return $schema;
    }

    /**
     * Get FAQ schema from content
     */
    private function get_faq_schema() {
        global $post;

        if ( ! $post ) {
            return null;
        }

        $faqs = $this->extract_faqs( $post->post_content );

        if ( empty( $faqs ) ) {
            return null;
        }

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array(),
        );

        foreach ( $faqs as $faq ) {
            $schema['mainEntity'][] = array(
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ),
            );
        }

        return $schema;
    }

    /**
     * Extract FAQs from content
     */
    private function extract_faqs( $content ) {
        $faqs = array();

        // Check for Gutenberg FAQ blocks (Yoast style)
        if ( preg_match_all( '/<!-- wp:yoast\/faq-block.*?-->(.*?)<!-- \/wp:yoast\/faq-block -->/s', $content, $matches ) ) {
            foreach ( $matches[1] as $block ) {
                // Parse the JSON from the block
                if ( preg_match( '/\{"questions":\[(.*?)\]\}/', $block, $json_match ) ) {
                    $questions = json_decode( '[' . $json_match[1] . ']', true );
                    if ( $questions ) {
                        foreach ( $questions as $q ) {
                            if ( isset( $q['question'] ) && isset( $q['answer'] ) ) {
                                $faqs[] = array(
                                    'question' => wp_strip_all_tags( $q['question'] ),
                                    'answer' => wp_strip_all_tags( $q['answer'] ),
                                );
                            }
                        }
                    }
                }
            }
        }

        // Check for heading + content pattern (H3 questions)
        if ( empty( $faqs ) ) {
            // Look for FAQ section
            if ( preg_match( '/<h[23][^>]*>.*?(FAQ|Frequently Asked|Questions).*?<\/h[23]>/i', $content ) ) {
                // Extract H3/H4 questions followed by content
                if ( preg_match_all( '/<h[34][^>]*>\s*(.*?)\s*<\/h[34]>\s*(.*?)(?=<h[234]|$)/is', $content, $matches, PREG_SET_ORDER ) ) {
                    foreach ( $matches as $match ) {
                        $question = wp_strip_all_tags( $match[1] );
                        $answer = wp_strip_all_tags( $match[2] );
                        
                        // Only include if it looks like a question
                        if ( substr( $question, -1 ) === '?' || strlen( $answer ) > 20 ) {
                            $faqs[] = array(
                                'question' => $question,
                                'answer' => trim( $answer ),
                            );
                        }
                    }
                }
            }
        }

        // Limit to 10 FAQs
        return array_slice( $faqs, 0, 10 );
    }

    /**
     * Get WebPage schema
     */
    private function get_webpage_schema() {
        global $post;

        if ( ! $post ) {
            return null;
        }

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => get_the_title(),
            'url' => get_permalink(),
            'dateModified' => get_the_modified_date( 'c' ),
        );

        // Add description
        $excerpt = get_the_excerpt();
        if ( ! empty( $excerpt ) ) {
            $schema['description'] = wp_strip_all_tags( $excerpt );
        }

        return $schema;
    }

    /**
     * Get schema preview for admin
     */
    public function get_preview( $type = 'organization' ) {
        switch ( $type ) {
            case 'organization':
                return $this->get_organization_schema();
            case 'article':
                return $this->get_article_schema();
            case 'author':
                return $this->get_author_schema();
            case 'faq':
                return $this->get_faq_schema();
            default:
                return null;
        }
    }

    /**
     * Validate schema structure
     */
    public function validate( $schema ) {
        $errors = array();

        if ( ! is_array( $schema ) ) {
            $errors[] = __( 'Schema must be an array', 'getcited' );
            return array( 'valid' => false, 'errors' => $errors );
        }

        if ( ! isset( $schema['@context'] ) ) {
            $errors[] = __( 'Missing @context', 'getcited' );
        }

        if ( ! isset( $schema['@type'] ) ) {
            $errors[] = __( 'Missing @type', 'getcited' );
        }

        return array(
            'valid' => empty( $errors ),
            'errors' => $errors,
        );
    }
}

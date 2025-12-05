<?php
/**
 * Content Citability Scoring for GetCited
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Citability class
 */
class GetCited_Citability {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Scoring rubric
     */
    private $rubric = array(
        'opening_summary' => array(
            'label' => 'Clear opening summary',
            'max_points' => 15,
            'description' => 'First paragraph states the main point clearly',
        ),
        'heading_structure' => array(
            'label' => 'Heading structure',
            'max_points' => 10,
            'description' => 'Uses H2/H3 hierarchy logically',
        ),
        'faq_section' => array(
            'label' => 'FAQ section present',
            'max_points' => 10,
            'description' => 'Contains FAQ block or Q&A format',
        ),
        'content_depth' => array(
            'label' => 'Sufficient depth',
            'max_points' => 10,
            'description' => 'Word count above 1,000 words',
        ),
        'lists_structure' => array(
            'label' => 'Lists and structure',
            'max_points' => 10,
            'description' => 'Contains organized lists, tables, or steps',
        ),
        'schema_markup' => array(
            'label' => 'Schema markup',
            'max_points' => 10,
            'description' => 'Article/FAQ schema present on page',
        ),
        'author_attribution' => array(
            'label' => 'Author attribution',
            'max_points' => 10,
            'description' => 'Author name, bio, and links present',
        ),
        'freshness' => array(
            'label' => 'Content freshness',
            'max_points' => 10,
            'description' => 'Recently published or updated',
        ),
        'internal_linking' => array(
            'label' => 'Internal linking',
            'max_points' => 5,
            'description' => 'Links to related content on site',
        ),
        'external_citations' => array(
            'label' => 'External citations',
            'max_points' => 5,
            'description' => 'References authoritative external sources',
        ),
        'meta_description' => array(
            'label' => 'Meta description',
            'max_points' => 5,
            'description' => 'Has custom meta description',
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
        // Add meta box to post editor
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

        // AJAX handler for analyzing posts
        add_action( 'wp_ajax_getcited_analyze_post', array( $this, 'ajax_analyze_post' ) );

        // AJAX handler for loading more posts
        add_action( 'wp_ajax_getcited_load_more_posts', array( $this, 'ajax_load_more_posts' ) );

        // Register post meta
        add_action( 'init', array( $this, 'register_meta' ) );
    }

    /**
     * Register post meta fields
     */
    public function register_meta() {
        register_post_meta( 'post', '_getcited_citability_score', array(
            'type' => 'integer',
            'default' => 0,
            'single' => true,
            'show_in_rest' => true,
            'description' => 'AI citability score (0-100)',
        ) );

        register_post_meta( 'post', '_getcited_last_audit', array(
            'type' => 'string',
            'default' => '',
            'single' => true,
            'show_in_rest' => true,
            'description' => 'Timestamp of last citability audit',
        ) );

        register_post_meta( 'post', '_getcited_exclude', array(
            'type' => 'boolean',
            'default' => false,
            'single' => true,
            'show_in_rest' => true,
            'description' => 'Exclude this post from llms.txt',
        ) );

        register_post_meta( 'post', '_getcited_no_schema', array(
            'type' => 'boolean',
            'default' => false,
            'single' => true,
            'show_in_rest' => true,
            'description' => 'Disable schema on this post',
        ) );

        // Future Pro meta
        register_post_meta( 'post', '_getcited_citation_count', array(
            'type' => 'integer',
            'default' => 0,
            'single' => true,
            'show_in_rest' => true,
            'description' => 'Number of AI citations detected',
        ) );
    }

    /**
     * Add meta box to post editor
     */
    public function add_meta_box() {
        $post_types = apply_filters( 'getcited_meta_box_post_types', array( 'post', 'page' ) );

        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'getcited-citability',
                __( 'GetCited — AI Visibility', 'getcited' ),
                array( $this, 'render_meta_box' ),
                $post_type,
                'side',
                'default'
            );
        }
    }

    /**
     * Render meta box content
     */
    public function render_meta_box( $post ) {
        $score = get_post_meta( $post->ID, '_getcited_citability_score', true );
        $last_audit = get_post_meta( $post->ID, '_getcited_last_audit', true );
        $exclude = get_post_meta( $post->ID, '_getcited_exclude', true );
        $no_schema = get_post_meta( $post->ID, '_getcited_no_schema', true );

        wp_nonce_field( 'getcited_meta_box', 'getcited_meta_nonce' );
        ?>
        <div class="getcited-meta-box">
            <div class="getcited-score-display">
                <?php if ( $score ) : ?>
                    <div class="getcited-score-number">
                        <span class="score"><?php echo esc_html( $score ); ?></span>
                        <span class="max">/100</span>
                    </div>
                    <?php if ( $last_audit ) : ?>
                        <div class="getcited-last-audit">
                            <?php
                            printf(
                                /* translators: %s: human-readable time difference (e.g., "2 hours ago") */
                                esc_html__( 'Last analyzed: %s', 'getcited' ),
                                esc_html( human_time_diff( strtotime( $last_audit ) ) . ' ' . __( 'ago', 'getcited' ) )
                            ); ?>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="getcited-no-score">
                        <?php esc_html_e( 'Not yet analyzed', 'getcited' ); ?>
                    </div>
                <?php endif; ?>
            </div>

            <button type="button" class="button getcited-analyze-btn" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                <?php esc_html_e( 'Analyze Citability', 'getcited' ); ?>
            </button>

            <div class="getcited-analysis-results" style="display: none;"></div>

            <hr>

            <p>
                <label>
                    <input type="checkbox" name="getcited_exclude" value="1" <?php checked( $exclude ); ?>>
                    <?php esc_html_e( 'Exclude from llms.txt', 'getcited' ); ?>
                </label>
            </p>

            <p>
                <label>
                    <input type="checkbox" name="getcited_no_schema" value="1" <?php checked( $no_schema ); ?>>
                    <?php esc_html_e( 'Disable schema on this page', 'getcited' ); ?>
                </label>
            </p>
        </div>
        <?php
    }

    /**
     * AJAX handler for analyzing posts
     */
    public function ajax_analyze_post() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        if ( ! $post_id ) {
            wp_send_json_error( 'Invalid post ID' );
        }

        // Check if user can edit this specific post
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $result = $this->analyze_post( $post_id );

        // Save score and timestamp
        update_post_meta( $post_id, '_getcited_citability_score', $result['score'] );
        update_post_meta( $post_id, '_getcited_last_audit', current_time( 'c' ) );

        // Fire hook
        do_action( 'getcited_citability_scored', $post_id, $result['score'] );

        wp_send_json_success( $result );
    }

    /**
     * AJAX handler for loading more posts
     */
    public function ajax_load_more_posts() {
        check_ajax_referer( 'getcited_admin', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $offset   = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
        $limit    = 5;
        $max_free = 10; // Free tier shows max 10 posts

        // Enforce free tier limit
        if ( $offset >= $max_free ) {
            wp_send_json_error( array( 'message' => 'Free tier limit reached' ) );
        }

        $posts = get_posts( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => min( $limit, $max_free - $offset ),
            'offset'         => $offset,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        if ( empty( $posts ) ) {
            wp_send_json_success( array(
                'html'     => '',
                'has_more' => false,
            ) );
        }

        $html = '';
        foreach ( $posts as $post ) {
            $score      = get_post_meta( $post->ID, '_getcited_citability_score', true );
            $last_audit = get_post_meta( $post->ID, '_getcited_last_audit', true );

            $score_class = '';
            if ( $score ) {
                $score_class = $score >= 70 ? 'good' : ( $score >= 40 ? 'ok' : 'low' );
            }

            $html .= '<tr data-post-id="' . esc_attr( $post->ID ) . '">';
            $html .= '<td><a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html( $post->post_title ) . '</a></td>';
            $html .= '<td class="score-cell">';
            if ( $score ) {
                $html .= '<span class="getcited-score-badge ' . esc_attr( $score_class ) . '">' . esc_html( $score ) . '/100</span>';
            } else {
                $html .= '<span class="getcited-score-badge none">—</span>';
            }
            $html .= '</td>';
            $html .= '<td>';
            if ( $last_audit ) {
                $html .= esc_html( human_time_diff( strtotime( $last_audit ) ) ) . ' ' . esc_html__( 'ago', 'getcited' );
            } else {
                $html .= esc_html__( 'Never', 'getcited' );
            }
            $html .= '</td>';
            $html .= '<td><button type="button" class="button getcited-analyze-post" data-post-id="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Analyze', 'getcited' ) . '</button></td>';
            $html .= '</tr>';
        }

        wp_send_json_success( array(
            'html'     => $html,
            'has_more' => ( $offset + count( $posts ) ) < $max_free && count( $posts ) === $limit,
        ) );
    }

    /**
     * Analyze a post for citability
     */
    public function analyze_post( $post_id ) {
        $post = get_post( $post_id );

        if ( ! $post ) {
            return array(
                'score' => 0,
                'factors' => array(),
                'recommendations' => array( __( 'Post not found', 'getcited' ) ),
            );
        }

        $content = $post->post_content;
        $plain_content = wp_strip_all_tags( $content );
        
        $factors = array();
        $recommendations = array();
        $total_score = 0;

        // 1. Opening Summary (15 points)
        $factor = $this->check_opening_summary( $content, $plain_content );
        $factors['opening_summary'] = $factor;
        $total_score += $factor['score'];
        if ( ! empty( $factor['recommendation'] ) ) {
            $recommendations[] = $factor['recommendation'];
        }

        // 2. Heading Structure (10 points)
        $factor = $this->check_heading_structure( $content );
        $factors['heading_structure'] = $factor;
        $total_score += $factor['score'];
        if ( ! empty( $factor['recommendation'] ) ) {
            $recommendations[] = $factor['recommendation'];
        }

        // 3. FAQ Section (10 points)
        $factor = $this->check_faq_section( $content, $post_id );
        $factors['faq_section'] = $factor;
        $total_score += $factor['score'];
        if ( ! empty( $factor['recommendation'] ) ) {
            $recommendations[] = $factor['recommendation'];
        }

        // 4. Content Depth (10 points)
        $factor = $this->check_content_depth( $plain_content );
        $factors['content_depth'] = $factor;
        $total_score += $factor['score'];
        if ( ! empty( $factor['recommendation'] ) ) {
            $recommendations[] = $factor['recommendation'];
        }

        // 5. Lists and Structure (10 points)
        $factor = $this->check_lists_structure( $content );
        $factors['lists_structure'] = $factor;
        $total_score += $factor['score'];
        if ( ! empty( $factor['recommendation'] ) ) {
            $recommendations[] = $factor['recommendation'];
        }

        // 6. Schema Markup (10 points)
        $factor = $this->check_schema_markup( $post_id );
        $factors['schema_markup'] = $factor;
        $total_score += $factor['score'];
        if ( ! empty( $factor['recommendation'] ) ) {
            $recommendations[] = $factor['recommendation'];
        }

        // 7. Author Attribution (10 points)
        $factor = $this->check_author_attribution( $post );
        $factors['author_attribution'] = $factor;
        $total_score += $factor['score'];
        if ( ! empty( $factor['recommendation'] ) ) {
            $recommendations[] = $factor['recommendation'];
        }

        // 8. Freshness (10 points)
        $factor = $this->check_freshness( $post );
        $factors['freshness'] = $factor;
        $total_score += $factor['score'];
        if ( ! empty( $factor['recommendation'] ) ) {
            $recommendations[] = $factor['recommendation'];
        }

        // 9. Internal Linking (5 points)
        $factor = $this->check_internal_linking( $content );
        $factors['internal_linking'] = $factor;
        $total_score += $factor['score'];
        if ( ! empty( $factor['recommendation'] ) ) {
            $recommendations[] = $factor['recommendation'];
        }

        // 10. External Citations (5 points)
        $factor = $this->check_external_citations( $content );
        $factors['external_citations'] = $factor;
        $total_score += $factor['score'];
        if ( ! empty( $factor['recommendation'] ) ) {
            $recommendations[] = $factor['recommendation'];
        }

        // 11. Meta Description (5 points)
        $factor = $this->check_meta_description( $post_id );
        $factors['meta_description'] = $factor;
        $total_score += $factor['score'];
        if ( ! empty( $factor['recommendation'] ) ) {
            $recommendations[] = $factor['recommendation'];
        }

        $result = array(
            'score' => min( 100, $total_score ),
            'factors' => $factors,
            'recommendations' => array_slice( $recommendations, 0, 5 ), // Top 5 recommendations
            'rubric' => $this->rubric,
        );

        return apply_filters( 'getcited_citability_score', $result, $post_id );
    }

    /**
     * Check opening summary
     */
    private function check_opening_summary( $content, $plain_content ) {
        $max = $this->rubric['opening_summary']['max_points'];

        // Get first paragraph
        $first_para = '';
        if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $content, $match ) ) {
            $first_para = wp_strip_all_tags( $match[1] );
        } else {
            // Fallback: first 200 chars
            $first_para = substr( $plain_content, 0, 200 );
        }

        $word_count = str_word_count( $first_para );

        if ( $word_count >= 30 && $word_count <= 80 ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'Good opening summary', 'getcited' ),
            );
        } elseif ( $word_count >= 20 ) {
            return array(
                'score' => round( $max * 0.7 ),
                'passed' => true,
                'message' => __( 'Opening could be more comprehensive', 'getcited' ),
                'recommendation' => __( 'Expand your opening paragraph to 30-80 words that summarize the main point', 'getcited' ),
            );
        } else {
            return array(
                'score' => round( $max * 0.3 ),
                'passed' => false,
                'message' => __( 'Opening paragraph too short', 'getcited' ),
                'recommendation' => __( 'Add a clear opening summary (30-80 words) that states the main point of your article', 'getcited' ),
            );
        }
    }

    /**
     * Check heading structure
     */
    private function check_heading_structure( $content ) {
        $max = $this->rubric['heading_structure']['max_points'];

        preg_match_all( '/<h([2-6])[^>]*>/i', $content, $matches );
        
        $headings = $matches[1] ?? array();
        $h2_count = count( array_filter( $headings, fn( $h ) => $h === '2' ) );
        $h3_count = count( array_filter( $headings, fn( $h ) => $h === '3' ) );

        if ( $h2_count >= 2 && $h3_count >= 1 ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'Good heading structure', 'getcited' ),
            );
        } elseif ( $h2_count >= 2 || ( $h2_count >= 1 && $h3_count >= 2 ) ) {
            return array(
                'score' => round( $max * 0.7 ),
                'passed' => true,
                'message' => __( 'Adequate structure — need 2+ H2s and 1+ H3 for full score', 'getcited' ),
            );
        } elseif ( count( $headings ) > 0 ) {
            return array(
                'score' => round( $max * 0.4 ),
                'passed' => false,
                'message' => __( 'Limited heading structure', 'getcited' ),
                'recommendation' => __( 'Add more H2 and H3 headings to organize your content logically', 'getcited' ),
            );
        } else {
            return array(
                'score' => 0,
                'passed' => false,
                'message' => __( 'No headings found', 'getcited' ),
                'recommendation' => __( 'Add H2 and H3 headings to structure your content — AI systems use these to understand topics', 'getcited' ),
            );
        }
    }

    /**
     * Check for FAQ section
     *
     * @param string $content  The post content.
     * @param int    $post_id  The post ID.
     * @return array Factor result.
     */
    private function check_faq_section( $content, $post_id ) {
        $max = $this->rubric['faq_section']['max_points'];

        // Check if this is a content type where FAQ is not expected
        $faq_exempt = $this->is_faq_exempt_content( $post_id );

        // Check for Gutenberg FAQ blocks
        $has_faq_block = strpos( $content, 'wp:yoast/faq-block' ) !== false ||
                         strpos( $content, 'wp:generateblocks/accordion' ) !== false ||
                         strpos( $content, 'wp:rank-math/faq-block' ) !== false;

        // Check for FAQ heading
        $has_faq_heading = preg_match( '/<h[23][^>]*>.*?(FAQ|Frequently Asked|Common Questions).*?<\/h[23]>/i', $content );

        // Check for Q&A pattern
        $has_qa_pattern = preg_match_all( '/<h[34][^>]*>.*?\?.*?<\/h[34]>/i', $content ) >= 3;

        if ( $has_faq_block ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'FAQ block detected', 'getcited' ),
            );
        } elseif ( $has_faq_heading && $has_qa_pattern ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'FAQ section found', 'getcited' ),
            );
        } elseif ( $has_qa_pattern ) {
            return array(
                'score' => round( $max * 0.6 ),
                'passed' => true,
                'message' => __( 'Q&A format detected but no FAQ heading', 'getcited' ),
                'recommendation' => __( 'Add an "FAQ" or "Frequently Asked Questions" heading to your Q&A section', 'getcited' ),
            );
        } elseif ( $faq_exempt ) {
            // Content type where FAQ is not expected - give full points, no penalty
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'FAQ not required for this content type', 'getcited' ),
            );
        } else {
            return array(
                'score' => 0,
                'passed' => false,
                'message' => __( 'No FAQ section found', 'getcited' ),
                'recommendation' => __( 'Add an FAQ section to your content — AI systems heavily cite Q&A format. This scoring is based on content, not the FAQ schema setting.', 'getcited' ),
            );
        }
    }

    /**
     * Check if post is a content type where FAQ is not expected
     *
     * @param int $post_id The post ID.
     * @return bool True if FAQ is not expected for this content type.
     */
    private function is_faq_exempt_content( $post_id ) {
        // Allow filtering for custom exemptions
        $exempt = apply_filters( 'getcited_faq_exempt', false, $post_id );
        if ( $exempt ) {
            return true;
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return false;
        }

        // Check site type setting
        $settings = GetCited_Settings::instance();
        $site_type = $settings->get( 'site_type' );

        // News sites typically don't have FAQs on articles
        if ( $site_type === 'news' ) {
            return true;
        }

        // Check for news/editorial categories
        $categories = wp_get_post_categories( $post_id, array( 'fields' => 'slugs' ) );
        $news_categories = array( 'news', 'editorial', 'opinion', 'breaking', 'press-release', 'announcement', 'update', 'updates' );

        foreach ( $categories as $cat_slug ) {
            if ( in_array( $cat_slug, $news_categories, true ) ) {
                return true;
            }
        }

        // Check for news/editorial tags
        $tags = wp_get_post_tags( $post_id, array( 'fields' => 'slugs' ) );
        $news_tags = array( 'news', 'breaking-news', 'press-release', 'announcement', 'editorial' );

        foreach ( $tags as $tag_slug ) {
            if ( in_array( $tag_slug, $news_tags, true ) ) {
                return true;
            }
        }

        // Check post format
        $post_format = get_post_format( $post_id );
        $exempt_formats = array( 'aside', 'status', 'quote', 'link', 'gallery', 'image', 'video', 'audio' );

        if ( $post_format && in_array( $post_format, $exempt_formats, true ) ) {
            return true;
        }

        return false;
    }

    /**
     * Check content depth
     */
    private function check_content_depth( $plain_content ) {
        $max = $this->rubric['content_depth']['max_points'];
        $word_count = str_word_count( $plain_content );

        if ( $word_count >= 2000 ) {
            return array(
                'score' => $max,
                'passed' => true,
                /* translators: %d: word count */
                'message' => sprintf( __( 'Excellent depth (%d words)', 'getcited' ), $word_count ),
            );
        } elseif ( $word_count >= 1000 ) {
            return array(
                'score' => $max,
                'passed' => true,
                /* translators: %d: word count */
                'message' => sprintf( __( 'Good depth (%d words)', 'getcited' ), $word_count ),
            );
        } elseif ( $word_count >= 500 ) {
            return array(
                'score' => round( $max * 0.6 ),
                'passed' => true,
                /* translators: %d: word count */
                'message' => sprintf( __( 'Moderate depth (%d words)', 'getcited' ), $word_count ),
                'recommendation' => __( 'Consider expanding to 1,000+ words for better citability', 'getcited' ),
            );
        } else {
            return array(
                'score' => round( $max * 0.3 ),
                'passed' => false,
                /* translators: %d: word count */
                'message' => sprintf( __( 'Short content (%d words)', 'getcited' ), $word_count ),
                'recommendation' => __( 'Expand your content to at least 1,000 words with comprehensive coverage', 'getcited' ),
            );
        }
    }

    /**
     * Check lists and structure
     */
    private function check_lists_structure( $content ) {
        $max = $this->rubric['lists_structure']['max_points'];

        $has_ul = preg_match_all( '/<ul[^>]*>/i', $content ) > 0;
        $has_ol = preg_match_all( '/<ol[^>]*>/i', $content ) > 0;
        $has_table = preg_match_all( '/<table[^>]*>/i', $content ) > 0;

        $structure_count = ( $has_ul ? 1 : 0 ) + ( $has_ol ? 1 : 0 ) + ( $has_table ? 1 : 0 );

        if ( $structure_count >= 2 ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'Good use of lists and structure', 'getcited' ),
            );
        } elseif ( $structure_count === 1 ) {
            return array(
                'score' => round( $max * 0.7 ),
                'passed' => true,
                'message' => __( '1 list type found — add another list or table for full score', 'getcited' ),
            );
        } else {
            return array(
                'score' => 0,
                'passed' => false,
                'message' => __( 'No lists or tables found', 'getcited' ),
                'recommendation' => __( 'Add bullet points, numbered lists, or tables to organize information', 'getcited' ),
            );
        }
    }

    /**
     * Check schema markup
     */
    private function check_schema_markup( $post_id ) {
        $max = $this->rubric['schema_markup']['max_points'];

        $settings = GetCited_Settings::instance();

        // Check if GetCited schema is enabled
        if ( ! $settings->get( 'schema_enabled' ) ) {
            // Check if an SEO plugin is handling schema (use existing detector).
            $detector  = GetCited_Schema_Detector::instance();
            $detection = $detector->get_detection_status();

            if ( ! empty( $detection['plugins'] ) ) {
                $plugin_name = $detection['plugins'][0]['name'];
                return array(
                    'score'  => $max,
                    'passed' => true,
                    /* translators: %s: SEO plugin name handling schema */
                    'message' => sprintf( __( 'Schema handled by %s', 'getcited' ), $plugin_name ),
                );
            }

            // No SEO plugin detected, schema truly disabled.
            return array(
                'score' => 0,
                'passed' => false,
                'message' => __( 'Schema output is disabled', 'getcited' ),
                'recommendation' => __( 'Enable Article schema in GetCited settings', 'getcited' ),
            );
        }

        // Check if disabled for this post
        $no_schema = get_post_meta( $post_id, '_getcited_no_schema', true );
        if ( $no_schema ) {
            return array(
                'score' => 0,
                'passed' => false,
                'message' => __( 'Schema disabled for this post', 'getcited' ),
                'recommendation' => __( 'Enable schema output for this post', 'getcited' ),
            );
        }

        $schema_types = $settings->get( 'schema_types' );

        if ( ! empty( $schema_types['article'] ) ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'Article schema enabled', 'getcited' ),
            );
        }

        return array(
            'score' => round( $max * 0.5 ),
            'passed' => false,
            'message' => __( 'Article schema not enabled', 'getcited' ),
            'recommendation' => __( 'Enable Article schema in GetCited settings', 'getcited' ),
        );
    }

    /**
     * Check author attribution
     */
    private function check_author_attribution( $post ) {
        $max = $this->rubric['author_attribution']['max_points'];

        $author_id = $post->post_author;
        $author = get_userdata( $author_id );

        if ( ! $author ) {
            return array(
                'score' => 0,
                'passed' => false,
                'message' => __( 'No author assigned', 'getcited' ),
                'recommendation' => __( 'Assign an author to this post', 'getcited' ),
            );
        }

        $has_name = ! empty( $author->display_name );
        $has_bio = ! empty( get_the_author_meta( 'description', $author_id ) );
        $has_social = ! empty( get_the_author_meta( 'twitter', $author_id ) ) ||
                      ! empty( get_the_author_meta( 'linkedin', $author_id ) ) ||
                      ! empty( get_the_author_meta( 'url', $author_id ) );

        $score = 0;
        if ( $has_name ) $score += round( $max * 0.4 );
        if ( $has_bio ) $score += round( $max * 0.4 );
        if ( $has_social ) $score += round( $max * 0.2 );

        if ( $score >= $max * 0.8 ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'Good author attribution', 'getcited' ),
            );
        } elseif ( $score >= $max * 0.5 ) {
            return array(
                'score' => $score,
                'passed' => true,
                'message' => __( 'Partial author attribution', 'getcited' ),
                'recommendation' => $has_bio ? __( 'Add author social links', 'getcited' ) : __( 'Add author bio', 'getcited' ),
            );
        } else {
            return array(
                'score' => $score,
                'passed' => false,
                'message' => __( 'Limited author information', 'getcited' ),
                'recommendation' => __( 'Add author bio and social links to the user profile', 'getcited' ),
            );
        }
    }

    /**
     * Check content freshness
     */
    private function check_freshness( $post ) {
        $max = $this->rubric['freshness']['max_points'];

        $modified = strtotime( $post->post_modified );
        $now = current_time( 'timestamp' );
        $months_old = ( $now - $modified ) / ( 30 * DAY_IN_SECONDS );

        if ( $months_old <= 6 ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'Fresh content', 'getcited' ),
            );
        } elseif ( $months_old <= 12 ) {
            return array(
                'score' => round( $max * 0.5 ),
                'passed' => true,
                /* translators: %d: number of months */
                'message' => sprintf( __( 'Content is %d months old', 'getcited' ), round( $months_old ) ),
                'recommendation' => __( 'Consider updating this content to improve freshness signals', 'getcited' ),
            );
        } else {
            return array(
                'score' => 0,
                'passed' => false,
                /* translators: %d: number of months */
                'message' => sprintf( __( 'Content is %d months old', 'getcited' ), round( $months_old ) ),
                'recommendation' => __( 'Update this post or add a "Last reviewed" date', 'getcited' ),
            );
        }
    }

    /**
     * Check internal linking
     */
    private function check_internal_linking( $content ) {
        $max = $this->rubric['internal_linking']['max_points'];

        $site_url = home_url();
        
        // Count internal links
        preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );
        
        $internal_links = 0;
        foreach ( $matches[1] as $url ) {
            if ( strpos( $url, $site_url ) === 0 || strpos( $url, '/' ) === 0 ) {
                $internal_links++;
            }
        }

        if ( $internal_links >= 3 ) {
            return array(
                'score' => $max,
                'passed' => true,
                /* translators: %d: number of internal links */
                'message' => sprintf( __( '%d internal links', 'getcited' ), $internal_links ),
            );
        } elseif ( $internal_links >= 1 ) {
            $needed = 3 - $internal_links;
            return array(
                'score' => round( $max * 0.6 ),
                'passed' => true,
                /* translators: 1: number of internal links, 2: number of additional links needed */
                'message' => sprintf( __( '%1$d internal link(s) — add %2$d more for full score', 'getcited' ), $internal_links, $needed ),
                'recommendation' => __( 'Add more internal links to related content', 'getcited' ),
            );
        } else {
            return array(
                'score' => 0,
                'passed' => false,
                'message' => __( 'No internal links', 'getcited' ),
                'recommendation' => __( 'Add links to related content on your site', 'getcited' ),
            );
        }
    }

    /**
     * Check external citations
     */
    private function check_external_citations( $content ) {
        $max = $this->rubric['external_citations']['max_points'];

        $site_url = home_url();
        
        // Count external links
        preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );
        
        $external_links = 0;
        foreach ( $matches[1] as $url ) {
            if ( strpos( $url, 'http' ) === 0 && strpos( $url, $site_url ) !== 0 ) {
                $external_links++;
            }
        }

        if ( $external_links >= 3 ) {
            return array(
                'score' => $max,
                'passed' => true,
                /* translators: %d: number of external citations/links */
                'message' => sprintf( __( '%d external citations', 'getcited' ), $external_links ),
            );
        } elseif ( $external_links >= 1 ) {
            $needed = 3 - $external_links;
            return array(
                'score' => round( $max * 0.6 ),
                'passed' => true,
                /* translators: 1: number of external citations, 2: number of additional citations needed */
                'message' => sprintf( __( '%1$d external citation(s) — add %2$d more for full score', 'getcited' ), $external_links, $needed ),
                'recommendation' => __( 'Reference authoritative external sources to build credibility', 'getcited' ),
            );
        } else {
            return array(
                'score' => 0,
                'passed' => false,
                'message' => __( 'No external citations', 'getcited' ),
                'recommendation' => __( 'Reference authoritative external sources to build credibility', 'getcited' ),
            );
        }
    }

    /**
     * Check meta description
     */
    private function check_meta_description( $post_id ) {
        $max = $this->rubric['meta_description']['max_points'];

        // Allow SEO plugins to provide meta description via filter
        // Hook: getcited_get_meta_description
        // Params: $description (string), $post_id (int)
        // Return: The meta description string or empty string
        $custom_desc = apply_filters( 'getcited_get_meta_description', '', $post_id );
        if ( ! empty( $custom_desc ) ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'Meta description set (custom)', 'getcited' ),
            );
        }

        // Check Yoast
        $yoast_desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
        if ( ! empty( $yoast_desc ) ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'Meta description set (Yoast)', 'getcited' ),
            );
        }

        // Check RankMath
        $rm_desc = get_post_meta( $post_id, 'rank_math_description', true );
        if ( ! empty( $rm_desc ) ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'Meta description set (RankMath)', 'getcited' ),
            );
        }

        // Check All in One SEO
        $aioseo_desc = get_post_meta( $post_id, '_aioseo_description', true );
        if ( ! empty( $aioseo_desc ) ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'Meta description set (AIOSEO)', 'getcited' ),
            );
        }

        // Check SEOPress
        $seopress_desc = get_post_meta( $post_id, '_seopress_titles_desc', true );
        if ( ! empty( $seopress_desc ) ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'Meta description set (SEOPress)', 'getcited' ),
            );
        }

        // Check The SEO Framework
        $tsf_desc = get_post_meta( $post_id, '_genesis_description', true );
        if ( ! empty( $tsf_desc ) ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'Meta description set (TSF)', 'getcited' ),
            );
        }

        // Check excerpt as fallback
        $post = get_post( $post_id );
        if ( ! empty( $post->post_excerpt ) ) {
            return array(
                'score' => round( $max * 0.5 ),
                'passed' => true,
                'message' => __( 'Using excerpt as description', 'getcited' ),
                'recommendation' => __( 'Set a custom meta description in your SEO plugin', 'getcited' ),
            );
        }

        // HTML fallback: fetch the page and check for meta description tag
        $meta_desc = $this->get_meta_description_from_html( $post_id );
        if ( ! empty( $meta_desc ) ) {
            return array(
                'score' => $max,
                'passed' => true,
                'message' => __( 'Meta description found in HTML', 'getcited' ),
            );
        }

        return array(
            'score' => 0,
            'passed' => false,
            'message' => __( 'No meta description', 'getcited' ),
            'recommendation' => __( 'Add a custom meta description using your SEO plugin', 'getcited' ),
        );
    }

    /**
     * Get meta description from rendered HTML
     *
     * @param int $post_id The post ID.
     * @return string The meta description or empty string.
     */
    private function get_meta_description_from_html( $post_id ) {
        $url = get_permalink( $post_id );
        if ( empty( $url ) ) {
            return '';
        }

        $response = wp_remote_get( $url, array(
            'timeout' => 5,
            'sslverify' => false,
        ) );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return '';
        }

        // Match <meta name="description" content="...">
        if ( preg_match( '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $body, $matches ) ) {
            return trim( $matches[1] );
        }

        // Also try reversed attribute order: content before name
        if ( preg_match( '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']description["\'][^>]*>/i', $body, $matches ) ) {
            return trim( $matches[1] );
        }

        return '';
    }

    /**
     * Get scoring rubric
     */
    public function get_rubric() {
        return apply_filters( 'getcited_citability_factors', $this->rubric );
    }

    /**
     * Get recent posts for analysis (free tier limit)
     */
    public function get_analyzable_posts( $limit = 5 ) {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        return get_posts( $args );
    }

    /**
     * Get average score across posts
     */
    public function get_average_score( $post_ids = array() ) {
        if ( empty( $post_ids ) ) {
            $posts = $this->get_analyzable_posts( 10 );
            $post_ids = wp_list_pluck( $posts, 'ID' );
        }

        $scores = array();
        foreach ( $post_ids as $post_id ) {
            $score = get_post_meta( $post_id, '_getcited_citability_score', true );
            if ( $score ) {
                $scores[] = (int) $score;
            }
        }

        if ( empty( $scores ) ) {
            return 0;
        }

        return round( array_sum( $scores ) / count( $scores ) );
    }
}

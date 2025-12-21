<?php
/**
 * Schema.org JSON-LD output for GetCited
 *
 * Outputs AI-optimized schema markup with @id entity graph,
 * enhanced author schema, and smart fallback detection.
 *
 * @package GetCited
 * @since 1.0.0
 * @updated 1.4.0 - Smart fallback, @id graph, enhanced author
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema class
 */
class GetCited_Schema {

	/**
	 * Single instance
	 *
	 * @var GetCited_Schema|null
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return GetCited_Schema
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
		// Output schema in head.
		add_action( 'wp_head', array( $this, 'output_schema' ), 5 );
	}

	/**
	 * Output schema JSON-LD
	 */
	public function output_schema() {
		$settings = GetCited_Settings::instance();

		// Check if schema is enabled in settings.
		if ( ! $settings->get( 'schema_enabled' ) ) {
			return;
		}

		// Check if schema should be auto-disabled (SEO plugin or JSON-LD detected).
		$detector = GetCited_Schema_Detector::instance();
		if ( $detector->should_disable_schema() ) {
			return;
		}

		// Skip if on WooCommerce product page.
		if ( function_exists( 'is_product' ) && is_product() ) {
			return;
		}

		// Check for per-post disable.
		if ( is_singular() ) {
			$post_id = get_the_ID();
			$disable = get_post_meta( $post_id, '_getcited_no_schema', true );
			if ( $disable ) {
				return;
			}
		}

		$schema_types = $settings->get( 'schema_types' );
		if ( ! is_array( $schema_types ) ) {
			$schema_types = array();
		}
		$schemas = array();

		// Organization schema (front page only).
		if ( ! empty( $schema_types['organization'] ) && is_front_page() ) {
			$org_schema = $this->get_organization_schema();
			if ( $org_schema ) {
				$schemas[] = $org_schema;
			}
		}

		// Article schema (single posts).
		if ( ! empty( $schema_types['article'] ) && is_singular( 'post' ) ) {
			$article_schema = $this->get_article_schema();
			if ( $article_schema ) {
				$schemas[] = $article_schema;
			}
		}

		// Author schema (with articles).
		if ( ! empty( $schema_types['author'] ) && is_singular( 'post' ) ) {
			$author_schema = $this->get_author_schema();
			if ( $author_schema ) {
				$schemas[] = $author_schema;
			}
		}

		// FAQ schema (posts with FAQ blocks).
		if ( ! empty( $schema_types['faq'] ) && is_singular() ) {
			$faq_schema = $this->get_faq_schema();
			if ( $faq_schema ) {
				$schemas[] = $faq_schema;
			}
		}

		// WebPage schema (pages).
		if ( is_page() && ! is_front_page() ) {
			$page_schema = $this->get_webpage_schema();
			if ( $page_schema ) {
				$schemas[] = $page_schema;
			}
		}

		// Output each schema.
		foreach ( $schemas as $schema ) {
			$schema = apply_filters( 'getcited_schema_output', $schema, get_the_ID() );
			$this->render_schema( $schema );
		}
	}

	/**
	 * Render schema as JSON-LD script tag
	 *
	 * @param array $schema Schema data.
	 */
	private function render_schema( $schema ) {
		if ( empty( $schema ) ) {
			return;
		}

		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG );

		echo "\n<!-- GetCited Schema -->\n";
		echo '<script type="application/ld+json">' . "\n";
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD output; JSON_HEX_TAG prevents script injection.
		echo $json . "\n";
		echo '</script>' . "\n";
	}

	/**
	 * Generate stable @id URL for an entity
	 *
	 * @param string $type Entity type (organization, author, article, website).
	 * @param string $identifier Optional identifier for the entity.
	 * @return string The @id URL.
	 */
	private function get_entity_id( $type, $identifier = '' ) {
		$base = trailingslashit( home_url() );

		switch ( $type ) {
			case 'organization':
				return $base . '#organization';
			case 'website':
				return $base . '#website';
			case 'author':
				return $base . '#author-' . sanitize_title( $identifier );
			case 'article':
				return get_permalink() . '#article';
			default:
				return $base . '#' . $type;
		}
	}

	/**
	 * Get Organization schema
	 *
	 * @return array|null Organization schema or null.
	 */
	private function get_organization_schema() {
		$settings = GetCited_Settings::instance();
		$org      = $settings->get( 'organization' );

		$name = ! empty( $org['name'] ) ? $org['name'] : get_bloginfo( 'name' );

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'@id'      => $this->get_entity_id( 'organization' ),
			'name'     => $name,
			'url'      => home_url(),
		);

		// Add logo if set.
		if ( ! empty( $org['logo_url'] ) ) {
			$schema['logo'] = $org['logo_url'];
		} elseif ( has_custom_logo() ) {
			$logo_id  = get_theme_mod( 'custom_logo' );
			$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $logo_url ) {
				$schema['logo'] = $logo_url;
			}
		}

		// Build comprehensive sameAs array.
		$same_as = array();

		// Legacy social URLs.
		if ( ! empty( $org['social_urls'] ) && is_array( $org['social_urls'] ) ) {
			$same_as = array_merge( $same_as, array_filter( $org['social_urls'] ) );
		}

		// New specific sameAs fields.
		if ( ! empty( $org['linkedin_company'] ) ) {
			$same_as[] = $org['linkedin_company'];
		}
		if ( ! empty( $org['wikipedia'] ) ) {
			$same_as[] = $org['wikipedia'];
		}
		if ( ! empty( $org['crunchbase'] ) ) {
			$same_as[] = $org['crunchbase'];
		}

		// Additional sameAs URLs (Yelp, Bloomberg, etc.).
		if ( ! empty( $org['sameas_urls'] ) && is_array( $org['sameas_urls'] ) ) {
			$same_as = array_merge( $same_as, array_filter( $org['sameas_urls'] ) );
		}

		if ( ! empty( $same_as ) ) {
			$schema['sameAs'] = array_values( array_unique( array_filter( $same_as ) ) );
		}

		// Add description.
		$description = get_bloginfo( 'description' );
		if ( ! empty( $description ) ) {
			$schema['description'] = $description;
		}

		return $schema;
	}

	/**
	 * Get Article schema
	 *
	 * @return array|null Article schema or null.
	 */
	private function get_article_schema() {
		global $post;

		if ( ! $post ) {
			return null;
		}

		// Determine article type.
		$article_type = 'Article';

		// Check for news-like categories.
		$categories      = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
		$news_indicators = array( 'news', 'breaking', 'press release', 'announcement' );

		foreach ( $categories as $cat ) {
			if ( in_array( strtolower( $cat ), $news_indicators, true ) ) {
				$article_type = 'NewsArticle';
				break;
			}
		}

		$schema = array(
			'@context'      => 'https://schema.org',
			'@type'         => $article_type,
			'@id'           => $this->get_entity_id( 'article' ),
			'headline'      => get_the_title(),
			'url'           => get_permalink(),
			'datePublished' => get_the_date( 'c' ),
			'dateModified'  => get_the_modified_date( 'c' ),
		);

		// Add description.
		$excerpt = get_the_excerpt();
		if ( ! empty( $excerpt ) ) {
			$schema['description'] = wp_strip_all_tags( $excerpt );
		}

		// Add featured image.
		if ( has_post_thumbnail() ) {
			$image_id  = get_post_thumbnail_id();
			$image_url = wp_get_attachment_image_url( $image_id, 'large' );
			if ( $image_url ) {
				$schema['image'] = $image_url;
			}
		}

		// Add author reference via @id (connects to Author schema).
		$author = get_userdata( $post->post_author );
		if ( $author ) {
			$schema['author'] = array(
				'@id' => $this->get_entity_id( 'author', $author->user_login ),
			);
		}

		// Add publisher reference via @id (connects to Organization schema).
		$schema['publisher'] = array(
			'@id' => $this->get_entity_id( 'organization' ),
		);

		// Add isPartOf to connect to WebSite.
		$schema['isPartOf'] = array(
			'@id' => $this->get_entity_id( 'website' ),
		);

		// Add word count.
		$content    = get_the_content();
		$word_count = str_word_count( wp_strip_all_tags( $content ) );
		$schema['wordCount'] = $word_count;

		return $schema;
	}

	/**
	 * Get Author schema with enhanced AI-specific properties
	 *
	 * @return array|null Author schema or null.
	 */
	private function get_author_schema() {
		global $post;

		if ( ! $post ) {
			return null;
		}

		$author_id = $post->post_author;
		$author    = get_userdata( $author_id );

		if ( ! $author ) {
			return null;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Person',
			'@id'      => $this->get_entity_id( 'author', $author->user_login ),
			'name'     => $author->display_name,
			'url'      => get_author_posts_url( $author_id ),
		);

		// Add bio if available.
		$bio = get_the_author_meta( 'description', $author_id );
		if ( ! empty( $bio ) ) {
			$schema['description'] = wp_strip_all_tags( $bio );
		}

		// Add job title (GetCited field).
		$job_title = get_user_meta( $author_id, 'getcited_job_title', true );
		if ( ! empty( $job_title ) ) {
			$schema['jobTitle'] = $job_title;
		}

		// Add knowsAbout (expertise topics - GetCited field).
		$author_fields = GetCited_Author_Fields::instance();
		$knows_about   = $author_fields->get_knows_about( $author_id );
		if ( ! empty( $knows_about ) ) {
			$schema['knowsAbout'] = $knows_about;
		}

		// Add sameAs links (uses GetCited fields with fallbacks).
		$same_as = $author_fields->get_same_as_urls( $author_id );
		if ( ! empty( $same_as ) ) {
			$schema['sameAs'] = $same_as;
		}

		// Add worksFor reference (connects to Organization).
		$schema['worksFor'] = array(
			'@id' => $this->get_entity_id( 'organization' ),
		);

		// Add avatar.
		$avatar_url = get_avatar_url( $author_id, array( 'size' => 200 ) );
		if ( $avatar_url ) {
			$schema['image'] = $avatar_url;
		}

		return $schema;
	}

	/**
	 * Get FAQ schema from content
	 *
	 * @return array|null FAQ schema or null.
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
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => array(),
		);

		foreach ( $faqs as $faq ) {
			$schema['mainEntity'][] = array(
				'@type'          => 'Question',
				'name'           => $faq['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $faq['answer'],
				),
			);
		}

		return $schema;
	}

	/**
	 * Extract FAQs from content
	 *
	 * @param string $content Post content.
	 * @return array Array of FAQ items.
	 */
	private function extract_faqs( $content ) {
		$faqs = array();

		// Check for Gutenberg FAQ blocks (Yoast style).
		if ( preg_match_all( '/<!-- wp:yoast\/faq-block.*?-->(.*?)<!-- \/wp:yoast\/faq-block -->/s', $content, $matches ) ) {
			foreach ( $matches[1] as $block ) {
				// Parse the JSON from the block.
				if ( preg_match( '/\{"questions":\[(.*?)\]\}/', $block, $json_match ) ) {
					$questions = json_decode( '[' . $json_match[1] . ']', true );
					if ( $questions ) {
						foreach ( $questions as $q ) {
							if ( isset( $q['question'] ) && isset( $q['answer'] ) ) {
								$faqs[] = array(
									'question' => wp_strip_all_tags( $q['question'] ),
									'answer'   => wp_strip_all_tags( $q['answer'] ),
								);
							}
						}
					}
				}
			}
		}

		// Check for heading + content pattern (H3 questions).
		if ( empty( $faqs ) ) {
			// Look for FAQ section.
			if ( preg_match( '/<h[23][^>]*>.*?(FAQ|Frequently Asked|Questions).*?<\/h[23]>/i', $content ) ) {
				// Extract H3/H4 questions followed by content.
				if ( preg_match_all( '/<h[34][^>]*>\s*(.*?)\s*<\/h[34]>\s*(.*?)(?=<h[234]|$)/is', $content, $matches, PREG_SET_ORDER ) ) {
					foreach ( $matches as $match ) {
						$question = wp_strip_all_tags( $match[1] );
						$answer   = wp_strip_all_tags( $match[2] );

						// Only include if it looks like a question.
						if ( substr( $question, -1 ) === '?' || strlen( $answer ) > 20 ) {
							$faqs[] = array(
								'question' => $question,
								'answer'   => trim( $answer ),
							);
						}
					}
				}
			}
		}

		// Limit to 10 FAQs.
		return array_slice( $faqs, 0, 10 );
	}

	/**
	 * Get WebPage schema
	 *
	 * @return array|null WebPage schema or null.
	 */
	private function get_webpage_schema() {
		global $post;

		if ( ! $post ) {
			return null;
		}

		$schema = array(
			'@context'     => 'https://schema.org',
			'@type'        => 'WebPage',
			'name'         => get_the_title(),
			'url'          => get_permalink(),
			'dateModified' => get_the_modified_date( 'c' ),
		);

		// Add description.
		$excerpt = get_the_excerpt();
		if ( ! empty( $excerpt ) ) {
			$schema['description'] = wp_strip_all_tags( $excerpt );
		}

		// Connect to WebSite.
		$schema['isPartOf'] = array(
			'@id' => $this->get_entity_id( 'website' ),
		);

		return $schema;
	}

	/**
	 * Get schema preview for admin
	 *
	 * @param string $type Schema type to preview.
	 * @return array|null Schema data or null.
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
	 *
	 * @param array $schema Schema data to validate.
	 * @return array Validation result with 'valid' and 'errors'.
	 */
	public function validate( $schema ) {
		$errors = array();

		if ( ! is_array( $schema ) ) {
			$errors[] = __( 'Schema must be an array', 'getcited' );
			return array(
				'valid'  => false,
				'errors' => $errors,
			);
		}

		if ( ! isset( $schema['@context'] ) ) {
			$errors[] = __( 'Missing @context', 'getcited' );
		}

		if ( ! isset( $schema['@type'] ) ) {
			$errors[] = __( 'Missing @type', 'getcited' );
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}
}

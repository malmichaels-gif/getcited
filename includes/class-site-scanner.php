<?php
/**
 * Site content scanner for intelligent llms.txt generation
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site Scanner class
 */
class GetCited_Site_Scanner {

	/**
	 * Single instance
	 */
	private static $instance = null;

	/**
	 * Cache key for scan results
	 */
	const CACHE_KEY = 'getcited_scan_cache';

	/**
	 * Cache TTL in seconds (5 minutes)
	 */
	const CACHE_TTL = 300;

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
		add_action( 'wp_ajax_getcited_scan_site', array( $this, 'ajax_scan_site' ) );
	}

	/**
	 * AJAX handler for site scanning (used by llms.txt editor re-scan)
	 */
	public function ajax_scan_site() {
		check_ajax_referer( 'getcited_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		// Check cache first (prevents rapid-fire requests)
		$cached = get_transient( self::CACHE_KEY );
		if ( $cached !== false ) {
			wp_send_json_success( $cached );
		}

		$scan_data = $this->scan_site();
		$generated_content = $this->generate_llms_txt( $scan_data );

		$result = array(
			'scan_data' => $scan_data,
			'llms_txt'  => $generated_content,
		);

		// Cache for a short time to prevent hammering
		set_transient( self::CACHE_KEY, $result, self::CACHE_TTL );

		wp_send_json_success( $result );
	}

	/**
	 * Scan the site for content (called directly by wizard, or via AJAX)
	 *
	 * @param bool $use_cache Whether to use cached results if available.
	 * @return array Scan data.
	 */
	public function scan_site( $use_cache = false ) {
		if ( $use_cache ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( $cached !== false && isset( $cached['scan_data'] ) ) {
				return $cached['scan_data'];
			}
		}

		$data = array(
			'site'              => $this->get_site_info(),
			'pages'             => $this->get_key_pages(),
			'posts'             => $this->get_recent_posts(),
			'categories'        => $this->get_categories(),
			'tags'              => $this->get_top_tags(),
			'menu'              => $this->get_primary_menu(),
			'social'            => $this->get_social_links(),
			'contact'           => $this->get_contact_info(),
			'custom_post_types' => $this->get_custom_post_types(),
		);

		// Add WooCommerce data if active
		if ( class_exists( 'WooCommerce' ) ) {
			$data['woocommerce'] = $this->get_woocommerce_data();
		}

		return $data;
	}

	/**
	 * Get basic site info
	 */
	private function get_site_info() {
		return array(
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'url'         => home_url(),
			'language'    => get_bloginfo( 'language' ),
		);
	}

	/**
	 * Get key pages (About, Contact, Services, etc.)
	 */
	private function get_key_pages() {
		$key_slugs = array(
			'about',
			'about-us',
			'about-me',
			'contact',
			'contact-us',
			'services',
			'our-services',
			'portfolio',
			'work',
			'projects',
			'team',
			'our-team',
			'faq',
			'faqs',
			'pricing',
			'plans',
			'blog',
			'news',
		);

		$pages = array();

		foreach ( $key_slugs as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page && $page->post_status === 'publish' ) {
				$pages[ $slug ] = array(
					'title'           => $page->post_title,
					'url'             => get_permalink( $page ),
					'excerpt'         => $this->get_clean_excerpt( $page ),
					'content_preview' => $this->get_content_preview( $page->post_content, 500 ),
				);
			}
		}

		// Also check homepage if it's a static page
		$front_page_id = get_option( 'page_on_front' );
		if ( $front_page_id ) {
			$front_page = get_post( $front_page_id );
			if ( $front_page ) {
				$pages['homepage'] = array(
					'title'           => $front_page->post_title,
					'url'             => home_url(),
					'content_preview' => $this->get_content_preview( $front_page->post_content, 1000 ),
				);
			}
		}

		return $pages;
	}

	/**
	 * Get recent posts with metadata
	 */
	private function get_recent_posts() {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$result = array();
		foreach ( $posts as $post ) {
			$result[] = array(
				'title'      => $post->post_title,
				'url'        => get_permalink( $post ),
				'date'       => $post->post_date,
				'categories' => wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) ),
				'tags'       => wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) ),
			);
		}

		return $result;
	}

	/**
	 * Get all categories with post counts
	 */
	private function get_categories() {
		$categories = get_categories(
			array(
				'orderby'    => 'count',
				'order'      => 'DESC',
				'hide_empty' => true,
			)
		);

		$result = array();
		foreach ( $categories as $cat ) {
			if ( $cat->slug === 'uncategorized' && $cat->count < 2 ) {
				continue; // Skip default uncategorized if barely used
			}
			$result[] = array(
				'name'        => $cat->name,
				'slug'        => $cat->slug,
				'url'         => get_category_link( $cat ),
				'count'       => $cat->count,
				'description' => $cat->description,
			);
		}

		return $result;
	}

	/**
	 * Get top tags
	 */
	private function get_top_tags() {
		$tags = get_tags(
			array(
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 20,
				'hide_empty' => true,
			)
		);

		if ( ! $tags || is_wp_error( $tags ) ) {
			return array();
		}

		$result = array();
		foreach ( $tags as $tag ) {
			$result[] = array(
				'name'  => $tag->name,
				'count' => $tag->count,
			);
		}

		return $result;
	}

	/**
	 * Get primary navigation menu
	 */
	private function get_primary_menu() {
		$locations = get_nav_menu_locations();

		// Try common menu location names
		$menu_location    = null;
		$common_locations = array( 'primary', 'main', 'main-menu', 'primary-menu', 'header', 'header-menu' );

		foreach ( $common_locations as $loc ) {
			if ( isset( $locations[ $loc ] ) ) {
				$menu_location = $locations[ $loc ];
				break;
			}
		}

		// Fallback to first available menu
		if ( ! $menu_location && ! empty( $locations ) ) {
			$menu_location = reset( $locations );
		}

		if ( ! $menu_location ) {
			return array();
		}

		$menu_items = wp_get_nav_menu_items( $menu_location );
		if ( ! $menu_items ) {
			return array();
		}

		$result = array();
		foreach ( $menu_items as $item ) {
			// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- Menu item parent can be string "0"
			if ( $item->menu_item_parent == 0 ) { // Top-level items only
				$result[] = array(
					'title' => $item->title,
					'url'   => $item->url,
				);
			}
		}

		return $result;
	}

	/**
	 * Get social media links from various sources
	 */
	private function get_social_links() {
		$social = array();

		// Check theme mods (common location for social links)
		$social_platforms = array(
			'facebook',
			'twitter',
			'x',
			'instagram',
			'linkedin',
			'youtube',
			'tiktok',
			'pinterest',
			'github',
			'threads',
			'mastodon',
			'bluesky',
		);

		foreach ( $social_platforms as $platform ) {
			// Check various common theme mod names
			$value = get_theme_mod( $platform . '_url' );
			if ( ! $value ) {
				$value = get_theme_mod( 'social_' . $platform );
			}
			if ( ! $value ) {
				$value = get_theme_mod( $platform );
			}

			if ( $value && filter_var( $value, FILTER_VALIDATE_URL ) ) {
				// Normalize twitter to x
				$key            = ( $platform === 'twitter' ) ? 'x' : $platform;
				$social[ $key ] = $value;
			}
		}

		// Check Yoast SEO social settings if available
		if ( defined( 'WPSEO_VERSION' ) ) {
			$yoast_social = get_option( 'wpseo_social' );
			if ( $yoast_social ) {
				$yoast_keys = array(
					'facebook_site'  => 'facebook',
					'twitter_site'   => 'x',
					'instagram_url'  => 'instagram',
					'linkedin_url'   => 'linkedin',
					'youtube_url'    => 'youtube',
					'pinterest_url'  => 'pinterest',
					'wikipedia_url'  => 'wikipedia',
					'myspace_url'    => 'myspace',
				);
				foreach ( $yoast_keys as $yoast_key => $platform ) {
					if ( ! empty( $yoast_social[ $yoast_key ] ) && empty( $social[ $platform ] ) ) {
						$url = $yoast_social[ $yoast_key ];
						// Handle Twitter username vs URL
						if ( $yoast_key === 'twitter_site' && strpos( $url, 'http' ) !== 0 ) {
							$url = 'https://x.com/' . ltrim( $url, '@' );
						}
						if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
							$social[ $platform ] = $url;
						}
					}
				}
			}
		}

		// Check Rank Math social settings if available
		if ( class_exists( 'RankMath' ) ) {
			$rankmath_social = array(
				'facebook'  => get_option( 'rank_math_facebook_url' ),
				'x'         => get_option( 'rank_math_twitter_url' ),
				'instagram' => get_option( 'rank_math_instagram_url' ),
				'linkedin'  => get_option( 'rank_math_linkedin_url' ),
				'youtube'   => get_option( 'rank_math_youtube_url' ),
				'pinterest' => get_option( 'rank_math_pinterest_url' ),
			);
			foreach ( $rankmath_social as $platform => $url ) {
				if ( ! empty( $url ) && empty( $social[ $platform ] ) && filter_var( $url, FILTER_VALIDATE_URL ) ) {
					$social[ $platform ] = $url;
				}
			}
		}

		// Check SEOPress social settings if available
		if ( function_exists( 'seopress_init' ) ) {
			$seopress_social = get_option( 'seopress_social_option_name' );
			if ( $seopress_social ) {
				$seopress_keys = array(
					'seopress_social_accounts_facebook'  => 'facebook',
					'seopress_social_accounts_twitter'   => 'x',
					'seopress_social_accounts_instagram' => 'instagram',
					'seopress_social_accounts_linkedin'  => 'linkedin',
					'seopress_social_accounts_youtube'   => 'youtube',
					'seopress_social_accounts_pinterest' => 'pinterest',
				);
				foreach ( $seopress_keys as $seopress_key => $platform ) {
					if ( ! empty( $seopress_social[ $seopress_key ] ) && empty( $social[ $platform ] ) ) {
						$url = $seopress_social[ $seopress_key ];
						if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
							$social[ $platform ] = $url;
						}
					}
				}
			}
		}

		return $social;
	}

	/**
	 * Get contact information
	 */
	private function get_contact_info() {
		$contact = array();

		// Check for contact page
		$contact_page = get_page_by_path( 'contact' );
		if ( ! $contact_page ) {
			$contact_page = get_page_by_path( 'contact-us' );
		}
		if ( $contact_page ) {
			$contact['page_url'] = get_permalink( $contact_page );
		}

		return $contact;
	}

	/**
	 * Get custom post types
	 */
	private function get_custom_post_types() {
		$custom_types = get_post_types(
			array(
				'public'   => true,
				'_builtin' => false,
			),
			'objects'
		);

		$result = array();
		foreach ( $custom_types as $type ) {
			// Skip WooCommerce types (handled separately)
			if ( in_array( $type->name, array( 'product', 'shop_order', 'shop_coupon' ), true ) ) {
				continue;
			}

			$count = wp_count_posts( $type->name );
			if ( isset( $count->publish ) && $count->publish > 0 ) {
				$result[ $type->name ] = array(
					'label'       => $type->label,
					'count'       => $count->publish,
					'archive_url' => get_post_type_archive_link( $type->name ),
				);
			}
		}

		return $result;
	}

	/**
	 * Get WooCommerce data
	 */
	private function get_woocommerce_data() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		// Get product categories
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		$cats = array();
		if ( ! is_wp_error( $categories ) ) {
			foreach ( $categories as $cat ) {
				$cats[] = array(
					'name'  => $cat->name,
					'url'   => get_term_link( $cat ),
					'count' => $cat->count,
				);
			}
		}

		$product_count = wp_count_posts( 'product' );

		return array(
			'shop_url'      => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '',
			'product_count' => isset( $product_count->publish ) ? $product_count->publish : 0,
			'categories'    => $cats,
		);
	}

	/**
	 * Extract clean text excerpt from a page
	 *
	 * @param WP_Post $post The post object.
	 * @return string Clean excerpt.
	 */
	private function get_clean_excerpt( $post ) {
		if ( ! empty( $post->post_excerpt ) ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}
		return wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
	}

	/**
	 * Get content preview with HTML stripped
	 *
	 * @param string $content The content to preview.
	 * @param int    $length  Maximum length.
	 * @return string Clean content preview.
	 */
	private function get_content_preview( $content, $length = 500 ) {
		$text = wp_strip_all_tags( $content );
		$text = preg_replace( '/\s+/', ' ', $text ); // Normalize whitespace
		$text = trim( $text );
		return mb_substr( $text, 0, $length );
	}

	/**
	 * Escape text for use in markdown links
	 *
	 * @param string $text Text to escape.
	 * @return string Escaped text.
	 */
	private function escape_markdown( $text ) {
		// Escape brackets and parentheses that could break markdown links
		$text = str_replace( array( '[', ']', '(', ')' ), array( '\\[', '\\]', '\\(', '\\)' ), $text );
		return $text;
	}

	/**
	 * Generate llms.txt content from scan data
	 *
	 * @param array $scan_data The scan data array.
	 * @return string Generated llms.txt content.
	 */
	public function generate_llms_txt( $scan_data ) {
		$site    = $scan_data['site'];
		$content = '# ' . $this->escape_markdown( $site['name'] ) . "\n\n";

		// Add tagline/description if exists
		if ( ! empty( $site['description'] ) ) {
			$content .= '> ' . $this->escape_markdown( $site['description'] ) . "\n\n";
		}

		// About section
		$about = null;
		if ( ! empty( $scan_data['pages']['about'] ) ) {
			$about = $scan_data['pages']['about'];
		} elseif ( ! empty( $scan_data['pages']['about-us'] ) ) {
			$about = $scan_data['pages']['about-us'];
		} elseif ( ! empty( $scan_data['pages']['about-me'] ) ) {
			$about = $scan_data['pages']['about-me'];
		}

		if ( $about ) {
			$content .= "## About\n\n";
			if ( ! empty( $about['content_preview'] ) ) {
				// Trim to ~200 chars for the summary
				$preview = mb_substr( $about['content_preview'], 0, 200 );
				if ( mb_strlen( $about['content_preview'] ) > 200 ) {
					$preview .= '...';
				}
				$content .= $preview . "\n\n";
			}
			$content .= '- [' . $this->escape_markdown( $about['title'] ) . '](' . esc_url( $about['url'] ) . ")\n\n";
		}

		// Main sections from menu
		if ( ! empty( $scan_data['menu'] ) ) {
			$content .= "## Sections\n\n";
			foreach ( $scan_data['menu'] as $item ) {
				$content .= '- [' . $this->escape_markdown( $item['title'] ) . '](' . esc_url( $item['url'] ) . ")\n";
			}
			$content .= "\n";
		}

		// Services if found
		$services = null;
		if ( ! empty( $scan_data['pages']['services'] ) ) {
			$services = $scan_data['pages']['services'];
		} elseif ( ! empty( $scan_data['pages']['our-services'] ) ) {
			$services = $scan_data['pages']['our-services'];
		}

		if ( $services ) {
			$content .= "## Services\n\n";
			$content .= '- [' . $this->escape_markdown( $services['title'] ) . '](' . esc_url( $services['url'] ) . ")\n\n";
		}

		// Portfolio if found
		$portfolio = null;
		if ( ! empty( $scan_data['pages']['portfolio'] ) ) {
			$portfolio = $scan_data['pages']['portfolio'];
		} elseif ( ! empty( $scan_data['pages']['work'] ) ) {
			$portfolio = $scan_data['pages']['work'];
		} elseif ( ! empty( $scan_data['pages']['projects'] ) ) {
			$portfolio = $scan_data['pages']['projects'];
		}

		if ( $portfolio ) {
			$content .= "## Portfolio\n\n";
			$content .= '- [' . $this->escape_markdown( $portfolio['title'] ) . '](' . esc_url( $portfolio['url'] ) . ")\n\n";
		}

		// Categories/Topics
		if ( ! empty( $scan_data['categories'] ) ) {
			$content .= "## Topics\n\n";
			foreach ( array_slice( $scan_data['categories'], 0, 8 ) as $cat ) {
				$content .= '- [' . $this->escape_markdown( $cat['name'] ) . '](' . esc_url( $cat['url'] ) . ')';
				if ( ! empty( $cat['description'] ) ) {
					$content .= ': ' . $this->escape_markdown( $cat['description'] );
				}
				$content .= "\n";
			}
			$content .= "\n";
		}

		// Custom post types (Portfolio, Services, etc.)
		if ( ! empty( $scan_data['custom_post_types'] ) ) {
			foreach ( $scan_data['custom_post_types'] as $type_name => $type ) {
				$content .= '## ' . $this->escape_markdown( $type['label'] ) . "\n\n";
				$content .= '- [Browse ' . $this->escape_markdown( $type['label'] ) . '](' . esc_url( $type['archive_url'] ) . ') (' . absint( $type['count'] ) . " items)\n\n";
			}
		}

		// WooCommerce
		if ( ! empty( $scan_data['woocommerce'] ) && ! empty( $scan_data['woocommerce']['shop_url'] ) ) {
			$woo      = $scan_data['woocommerce'];
			$content .= "## Shop\n\n";
			$content .= '- [Browse Products](' . esc_url( $woo['shop_url'] ) . ') (' . absint( $woo['product_count'] ) . " products)\n";
			if ( ! empty( $woo['categories'] ) ) {
				$content .= "\nProduct Categories:\n";
				foreach ( array_slice( $woo['categories'], 0, 6 ) as $cat ) {
					$content .= '- [' . $this->escape_markdown( $cat['name'] ) . '](' . esc_url( $cat['url'] ) . ")\n";
				}
			}
			$content .= "\n";
		}

		// Recent content
		if ( ! empty( $scan_data['posts'] ) ) {
			$content .= "## Recent Content\n\n";
			foreach ( array_slice( $scan_data['posts'], 0, 5 ) as $post ) {
				$content .= '- [' . $this->escape_markdown( $post['title'] ) . '](' . esc_url( $post['url'] ) . ")\n";
			}
			$content .= "\n";
		}

		// Contact & Social
		$content .= "## Connect\n\n";
		$content .= '- Website: ' . esc_url( $site['url'] ) . "\n";

		if ( ! empty( $scan_data['contact']['page_url'] ) ) {
			$content .= '- [Contact](' . esc_url( $scan_data['contact']['page_url'] ) . ")\n";
		}

		if ( ! empty( $scan_data['social'] ) ) {
			foreach ( $scan_data['social'] as $platform => $url ) {
				// Capitalize platform name, handle 'x' specially
				$display_name = ( $platform === 'x' ) ? 'X (Twitter)' : ucfirst( $platform );
				$content     .= '- ' . $display_name . ': ' . esc_url( $url ) . "\n";
			}
		}

		$content .= "\n---\n";
		$content .= '# Generated by GetCited';

		/**
		 * Filter the generated llms.txt content before saving
		 *
		 * @param string $content   The generated llms.txt content.
		 * @param array  $scan_data The raw scan data used to generate the content.
		 */
		$content = apply_filters( 'getcited_scanner_generated_content', $content, $scan_data );

		return $content;
	}

	/**
	 * Clear the scan cache
	 */
	public function clear_cache() {
		delete_transient( self::CACHE_KEY );
	}
}

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
	 * Cache TTL in seconds (1 hour)
	 *
	 * Site content doesn't change frequently enough to warrant 5-minute caching.
	 * 1 hour is a good balance between freshness and performance.
	 */
	const CACHE_TTL = HOUR_IN_SECONDS;

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
	 * Optimized to use a single query instead of individual lookups.
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

		// Batch query: get all pages with these slugs in a single query
		$found_pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_name__in'  => $key_slugs,
				'posts_per_page' => count( $key_slugs ),
				'no_found_rows'  => true,
			)
		);

		// Index found pages by slug for quick lookup
		foreach ( $found_pages as $page ) {
			$slug           = $page->post_name;
			$pages[ $slug ] = array(
				'title'           => $page->post_title,
				'url'             => get_permalink( $page ),
				'excerpt'         => $this->get_clean_excerpt( $page ),
				'content_preview' => $this->get_content_preview( $page->post_content, 500 ),
			);
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
	 * Get recent posts with metadata (excluding noindex posts)
	 *
	 * @since 1.5.0 Added noindex and _getcited_exclude filtering.
	 */
	private function get_recent_posts() {
		// Fetch extra posts to account for noindex filtering.
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$result = array();
		foreach ( $posts as $post ) {
			// Skip noindex posts.
			if ( function_exists( 'getcited_is_post_noindex' ) && getcited_is_post_noindex( $post->ID ) ) {
				continue;
			}

			// Skip posts excluded via GetCited meta.
			if ( get_post_meta( $post->ID, '_getcited_exclude', true ) ) {
				continue;
			}

			$result[] = array(
				'title'      => $post->post_title,
				'url'        => get_permalink( $post ),
				'date'       => $post->post_date,
				'categories' => wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) ),
				'tags'       => wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) ),
			);

			if ( count( $result ) >= 10 ) {
				break;
			}
		}

		return $result;
	}

	/**
	 * Get top categories with post counts (limited for performance)
	 */
	private function get_categories() {
		$categories = get_categories(
			array(
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 20,
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
			if ( (int) $item->menu_item_parent === 0 ) { // Top-level items only
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

		// Social platform patterns for URL matching
		$social_patterns = array(
			'facebook'  => 'facebook.com',
			'x'         => array( 'twitter.com', 'x.com' ),
			'instagram' => 'instagram.com',
			'linkedin'  => 'linkedin.com',
			'youtube'   => array( 'youtube.com', 'youtu.be' ),
			'tiktok'    => 'tiktok.com',
			'pinterest' => 'pinterest.com',
			'github'    => 'github.com',
			'threads'   => 'threads.net',
			'mastodon'  => 'mastodon',
			'bluesky'   => 'bsky.app',
		);

		// Check theme mods (common location for social links)
		$social_platforms = array_keys( $social_patterns );
		$social_platforms[] = 'twitter'; // Also check twitter explicitly

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

		// Check TagDiv theme options (Newspaper theme)
		$td_options = get_option( 'td_options' );
		if ( $td_options && is_array( $td_options ) ) {
			$td_social_keys = array(
				'tds_social_facebook'  => 'facebook',
				'tds_social_twitter'   => 'x',
				'tds_social_instagram' => 'instagram',
				'tds_social_linkedin'  => 'linkedin',
				'tds_social_youtube'   => 'youtube',
				'tds_social_pinterest' => 'pinterest',
				'tds_social_tiktok'    => 'tiktok',
			);
			foreach ( $td_social_keys as $td_key => $platform ) {
				if ( ! empty( $td_options[ $td_key ] ) && empty( $social[ $platform ] ) ) {
					$url = $td_options[ $td_key ];
					if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
						$social[ $platform ] = $url;
					}
				}
			}
		}

		// Check widget areas for social links (many themes use footer widgets)
		$social = $this->scan_widgets_for_social( $social, $social_patterns );

		// Check menu items for social links
		$social = $this->scan_menus_for_social( $social, $social_patterns );

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

		// Check GetCited organization settings
		if ( class_exists( 'GetCited_Settings' ) ) {
			$settings   = GetCited_Settings::instance();
			$org        = $settings->get( 'organization' );
			$org_social = isset( $org['social_urls'] ) ? $org['social_urls'] : array();
			if ( ! empty( $org_social ) ) {
				foreach ( $org_social as $url ) {
					if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
						continue;
					}
					// Match URL against platforms
					foreach ( $social_patterns as $platform => $patterns ) {
						if ( isset( $social[ $platform ] ) ) {
							continue;
						}
						$patterns_array = is_array( $patterns ) ? $patterns : array( $patterns );
						foreach ( $patterns_array as $pattern ) {
							if ( stripos( $url, $pattern ) !== false ) {
								$social[ $platform ] = $url;
								break 2;
							}
						}
					}
				}
			}
		}

		// Final fallback: scan homepage HTML for social links
		$social = $this->scan_homepage_for_social( $social, $social_patterns );

		return $social;
	}

	/**
	 * Scan homepage HTML for social media links
	 *
	 * This is a fallback method for sites using custom SEO plugins or themes
	 * that don't store social links in standard locations.
	 *
	 * @param array $social          Existing social links found.
	 * @param array $social_patterns Platform URL patterns.
	 * @return array Updated social links.
	 */
	private function scan_homepage_for_social( $social, $social_patterns ) {
		// Skip if we already have several social links
		if ( count( $social ) >= 4 ) {
			return $social;
		}

		// Fetch homepage HTML
		$response = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout'    => 5,
				'user-agent' => 'GetCited/' . ( defined( 'GETCITED_VERSION' ) ? GETCITED_VERSION : '1.0' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $social;
		}

		$html = wp_remote_retrieve_body( $response );
		if ( empty( $html ) ) {
			return $social;
		}

		// Extract all href URLs from the HTML
		// Focus on common social link locations: header, footer, aside
		// Pattern matches href attributes
		if ( ! preg_match_all( '/href=["\']([^"\']+)["\']/', $html, $matches ) ) {
			return $social;
		}

		$urls = array_unique( $matches[1] );

		// Check each URL against social patterns
		foreach ( $urls as $url ) {
			// Skip non-http URLs
			if ( strpos( $url, 'http' ) !== 0 ) {
				continue;
			}

			// Skip internal site URLs
			if ( strpos( $url, home_url() ) === 0 ) {
				continue;
			}

			foreach ( $social_patterns as $platform => $patterns ) {
				if ( isset( $social[ $platform ] ) ) {
					continue; // Already found this platform
				}

				$patterns_array = is_array( $patterns ) ? $patterns : array( $patterns );
				foreach ( $patterns_array as $pattern ) {
					if ( stripos( $url, $pattern ) !== false ) {
						// Basic validation: ensure it looks like a profile URL
						// Skip share links, intent URLs, etc.
						if ( preg_match( '/(share|intent|sharer|tweet|pin\/create)/i', $url ) ) {
							continue;
						}
						$social[ $platform ] = $url;
						break 2;
					}
				}
			}
		}

		return $social;
	}

	/**
	 * Scan menus for social media links
	 *
	 * @param array $social          Existing social links.
	 * @param array $social_patterns Platform URL patterns.
	 * @return array Updated social links.
	 */
	private function scan_menus_for_social( $social, $social_patterns ) {
		// Get all menus
		$menus = wp_get_nav_menus();
		if ( ! $menus ) {
			return $social;
		}

		foreach ( $menus as $menu ) {
			$menu_items = wp_get_nav_menu_items( $menu->term_id );
			if ( ! $menu_items ) {
				continue;
			}

			foreach ( $menu_items as $item ) {
				$url = $item->url;
				if ( ! $url ) {
					continue;
				}

				// Check URL against known social patterns
				foreach ( $social_patterns as $platform => $patterns ) {
					if ( isset( $social[ $platform ] ) ) {
						continue; // Already found this platform
					}

					$patterns_array = is_array( $patterns ) ? $patterns : array( $patterns );
					foreach ( $patterns_array as $pattern ) {
						if ( stripos( $url, $pattern ) !== false ) {
							$social[ $platform ] = $url;
							break 2;
						}
					}
				}
			}
		}

		return $social;
	}

	/**
	 * Scan widgets for social media links
	 *
	 * @param array $social          Existing social links.
	 * @param array $social_patterns Platform URL patterns.
	 * @return array Updated social links.
	 */
	private function scan_widgets_for_social( $social, $social_patterns ) {
		// Get all active widgets (using get_option instead of wp_get_sidebars_widgets for plugin compatibility).
		$sidebars = get_option( 'sidebars_widgets', array() );
		if ( ! $sidebars || ! is_array( $sidebars ) ) {
			return $social;
		}

		// Check text/custom HTML widgets for social URLs
		$text_widgets    = get_option( 'widget_text' );
		$html_widgets    = get_option( 'widget_custom_html' );
		$widgets_to_scan = array();

		if ( $text_widgets && is_array( $text_widgets ) ) {
			$widgets_to_scan = array_merge( $widgets_to_scan, $text_widgets );
		}
		if ( $html_widgets && is_array( $html_widgets ) ) {
			$widgets_to_scan = array_merge( $widgets_to_scan, $html_widgets );
		}

		foreach ( $widgets_to_scan as $widget ) {
			if ( ! isset( $widget['content'] ) && ! isset( $widget['text'] ) ) {
				continue;
			}

			$content = isset( $widget['content'] ) ? $widget['content'] : $widget['text'];

			// Extract URLs from content
			if ( preg_match_all( '/href=["\']([^"\']+)["\']/', $content, $matches ) ) {
				foreach ( $matches[1] as $url ) {
					foreach ( $social_patterns as $platform => $patterns ) {
						if ( isset( $social[ $platform ] ) ) {
							continue;
						}

						$patterns_array = is_array( $patterns ) ? $patterns : array( $patterns );
						foreach ( $patterns_array as $pattern ) {
							if ( stripos( $url, $pattern ) !== false ) {
								$social[ $platform ] = $url;
								break 2;
							}
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

		// Common internal/admin post types to skip
		$skip_types = array(
			'product',
			'shop_order',
			'shop_coupon',
			'elementor_library',
			'elementor_font',
			'elementor_icons',
			'e-landing-page',
			'tdb_templates',
			'tdc-locker',
			'tdc-email',
			'tdc-cloud-templates',
			'wpforms',
			'wpforms-template',
			'edd_download',
			'mc4wp-form',
			'wpcf7_contact_form',
			'acf-field-group',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_global_styles',
			'wp_navigation',
		);

		$result = array();
		foreach ( $custom_types as $type ) {
			// Skip known internal/WooCommerce types
			if ( in_array( $type->name, $skip_types, true ) ) {
				continue;
			}

			// Skip types that start with common internal prefixes
			if ( preg_match( '/^(tdc_|tdb_|td_|elementor_|wpforms_|acf_|wpcf7_|mc4wp_|fl_|bb_|et_|divi_)/', $type->name ) ) {
				continue;
			}

			// Skip if no archive support or no published posts
			$archive_url = get_post_type_archive_link( $type->name );
			if ( ! $archive_url ) {
				continue;
			}

			$count = wp_count_posts( $type->name );
			if ( isset( $count->publish ) && $count->publish > 0 ) {
				$result[ $type->name ] = array(
					'label'       => $type->label,
					'count'       => $count->publish,
					'archive_url' => $archive_url,
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

		// Get product categories (limited to top 6 by sales volume).
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 6,
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
			return wp_strip_all_tags( strip_shortcodes( $post->post_excerpt ) );
		}

		// Strip shortcodes and page builder content
		$content = strip_shortcodes( $post->post_content );
		$content = preg_replace( '/\[[^\]]*\]/', '', $content );
		$content = preg_replace( '/[A-Za-z0-9+\/=]{50,}/', '', $content );
		$content = wp_strip_all_tags( $content );

		return wp_trim_words( $content, 30 );
	}

	/**
	 * Get content preview with HTML and shortcodes stripped
	 *
	 * @param string $content The content to preview.
	 * @param int    $length  Maximum length.
	 * @return string Clean content preview.
	 */
	private function get_content_preview( $content, $length = 500 ) {
		// Strip shortcodes first (including page builder shortcodes)
		$text = strip_shortcodes( $content );

		// Remove common page builder patterns that look like shortcodes but aren't caught
		// Matches [anything_with_underscores ...] style shortcodes
		$text = preg_replace( '/\[[^\]]*\]/', '', $text );

		// Remove base64-encoded content (common in page builders)
		$text = preg_replace( '/[A-Za-z0-9+\/=]{50,}/', '', $text );

		// Strip HTML tags
		$text = wp_strip_all_tags( $text );

		// Normalize whitespace
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		// If result is too short or empty after cleaning, return empty
		if ( mb_strlen( $text ) < 20 ) {
			return '';
		}

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
	 * This produces a rich, comprehensive llms.txt file that helps AI systems
	 * understand and properly cite the site's content.
	 *
	 * @param array $scan_data The scan data array.
	 * @return string Generated llms.txt content.
	 */
	public function generate_llms_txt( $scan_data ) {
		$site     = $scan_data['site'];
		$settings = GetCited_Settings::instance();
		$llms_txt = GetCited_Llms_Txt::instance();

		// Get settings for richer content
		$site_type        = $settings->get( 'site_type' );
		$founder_name     = $settings->get( 'llms_founder_name' );
		$founder_title    = $settings->get( 'llms_founder_title' );
		$site_expertise   = $settings->get( 'llms_site_expertise' );
		$update_frequency = $settings->get( 'llms_update_frequency' );
		$org              = $settings->get( 'organization' );

		$content = '# ' . $this->escape_markdown( $site['name'] ) . "\n\n";

		// Add tagline/description if exists
		if ( ! empty( $site['description'] ) ) {
			$content .= $this->escape_markdown( $site['description'] ) . "\n\n";
		}

		// Introduction paragraph
		$content .= "This file helps AI systems understand and properly cite content from this website.\n\n";

		// About section - enhanced with founder/expertise info
		$content .= "## About\n\n";

		$about = null;
		if ( ! empty( $scan_data['pages']['about'] ) ) {
			$about = $scan_data['pages']['about'];
		} elseif ( ! empty( $scan_data['pages']['about-us'] ) ) {
			$about = $scan_data['pages']['about-us'];
		} elseif ( ! empty( $scan_data['pages']['about-me'] ) ) {
			$about = $scan_data['pages']['about-me'];
		}

		if ( $about && ! empty( $about['content_preview'] ) ) {
			$preview = mb_substr( $about['content_preview'], 0, 300 );
			if ( mb_strlen( $about['content_preview'] ) > 300 ) {
				$preview .= '...';
			}
			$content .= $preview . "\n\n";
		}

		// Site metadata
		$content .= "- **Website:** " . esc_url( $site['url'] ) . "\n";
		$content .= "- **Type:** " . ucfirst( $site_type ) . "\n";

		if ( ! empty( $org['name'] ) && $org['name'] !== $site['name'] ) {
			$content .= "- **Organization:** " . $this->escape_markdown( $org['name'] ) . "\n";
		}

		if ( ! empty( $founder_name ) ) {
			$founder_line = "- **Founder:** " . $this->escape_markdown( $founder_name );
			if ( ! empty( $founder_title ) ) {
				$founder_line .= ', ' . $this->escape_markdown( $founder_title );
			}
			$content .= $founder_line . "\n";
		}

		if ( ! empty( $site_expertise ) ) {
			$content .= "- **Expertise:** " . $this->escape_markdown( $site_expertise ) . "\n";
		}

		$content .= "- **Language:** " . $this->escape_markdown( $site['language'] ) . "\n";
		$content .= "\n";

		// Primary Content section based on site type
		$content .= "## Primary Content\n\n";
		$content .= $this->get_primary_content_description( $site_type, $scan_data ) . "\n\n";

		// Key Topics/Categories
		if ( ! empty( $scan_data['categories'] ) ) {
			$content .= "## Key Topics\n\n";
			foreach ( array_slice( $scan_data['categories'], 0, 10 ) as $cat ) {
				$content .= '- **' . $this->escape_markdown( $cat['name'] ) . '**';
				if ( ! empty( $cat['description'] ) ) {
					$content .= ': ' . $this->escape_markdown( $cat['description'] );
				} elseif ( ! empty( $cat['count'] ) ) {
					$content .= ' (' . absint( $cat['count'] ) . ' articles)';
				}
				$content .= "\n";
			}
			$content .= "\n";
		}

		// Site Structure
		$content .= "## Site Structure\n\n";

		if ( ! empty( $scan_data['menu'] ) ) {
			foreach ( $scan_data['menu'] as $item ) {
				$content .= '- [' . $this->escape_markdown( $item['title'] ) . '](' . esc_url( $item['url'] ) . ")\n";
			}
		} else {
			// Fallback to detected pages
			$content .= "- [Home](" . esc_url( $site['url'] ) . ")\n";
			if ( $about ) {
				$content .= '- [' . $this->escape_markdown( $about['title'] ) . '](' . esc_url( $about['url'] ) . ")\n";
			}
			if ( ! empty( $scan_data['contact']['page_url'] ) ) {
				$content .= "- [Contact](" . esc_url( $scan_data['contact']['page_url'] ) . ")\n";
			}
		}
		$content .= "\n";

		// Custom post types (Portfolio, Services, etc.)
		if ( ! empty( $scan_data['custom_post_types'] ) ) {
			$content .= "## Content Collections\n\n";
			foreach ( $scan_data['custom_post_types'] as $type_name => $type ) {
				$content .= '- [' . $this->escape_markdown( $type['label'] ) . '](' . esc_url( $type['archive_url'] ) . ') — ' . absint( $type['count'] ) . " items\n";
			}
			$content .= "\n";
		}

		// WooCommerce
		if ( ! empty( $scan_data['woocommerce'] ) && ! empty( $scan_data['woocommerce']['shop_url'] ) ) {
			$woo      = $scan_data['woocommerce'];
			$content .= "## Products\n\n";
			$content .= '- [Shop](' . esc_url( $woo['shop_url'] ) . ') — ' . absint( $woo['product_count'] ) . " products\n";
			if ( ! empty( $woo['categories'] ) ) {
				foreach ( array_slice( $woo['categories'], 0, 6 ) as $cat ) {
					$content .= '- [' . $this->escape_markdown( $cat['name'] ) . '](' . esc_url( $cat['url'] ) . ")\n";
				}
			}
			$content .= "\n";
		}

		// Citation Guidelines
		$content .= "## Citation Guidelines\n\n";
		$content .= $llms_txt->get_citation_template( $site_type ) . "\n\n";

		// Data Freshness
		$content .= "## Data Freshness\n\n";
		if ( ! empty( $update_frequency ) ) {
			$content .= "- **Update Frequency:** " . $this->escape_markdown( $update_frequency ) . "\n";
		}
		if ( ! empty( $scan_data['posts'] ) && isset( $scan_data['posts'][0]['date'] ) ) {
			$latest_date = gmdate( 'F j, Y', strtotime( $scan_data['posts'][0]['date'] ) );
			$content    .= "- **Latest Content:** " . $latest_date . "\n";
		}
		$content .= "- **File Generated:** " . current_time( 'F j, Y' ) . "\n\n";

		// Content Policy
		$content .= "## Content Policy\n\n";
		$content .= $llms_txt->get_content_policy_template( $site_type ) . "\n\n";

		// Technical Details
		$content .= "## Technical Details\n\n";
		$content .= "- **Platform:** WordPress\n";

		// Check for schema types
		$schema_types = $settings->get( 'schema_types' );
		$active_schema = array();
		if ( is_array( $schema_types ) ) {
			foreach ( $schema_types as $type => $enabled ) {
				if ( $enabled ) {
					$active_schema[] = ucfirst( $type );
				}
			}
		}
		if ( ! empty( $active_schema ) ) {
			$content .= "- **Structured Data:** " . implode( ', ', $active_schema ) . " schema\n";
		}

		// Check for sitemap
		$sitemap_url = home_url( '/sitemap.xml' );
		$content    .= "- **Sitemap:** " . esc_url( $sitemap_url ) . "\n";

		// Feed URL
		$content .= "- **RSS Feed:** " . esc_url( get_feed_link() ) . "\n\n";

		// Recent Content
		if ( ! empty( $scan_data['posts'] ) ) {
			$content .= "## Recent Content\n\n";
			foreach ( array_slice( $scan_data['posts'], 0, 5 ) as $post ) {
				$date     = gmdate( 'M j', strtotime( $post['date'] ) );
				$content .= '- [' . $this->escape_markdown( $post['title'] ) . '](' . esc_url( $post['url'] ) . ') — ' . $date . "\n";
			}
			$content .= "\n";
		}

		// Contact & Social
		$content .= "## Connect\n\n";

		if ( ! empty( $scan_data['contact']['page_url'] ) ) {
			$content .= '- [Contact Page](' . esc_url( $scan_data['contact']['page_url'] ) . ")\n";
		}

		if ( ! empty( $scan_data['social'] ) ) {
			foreach ( $scan_data['social'] as $platform => $url ) {
				$display_name = ( $platform === 'x' ) ? 'X (Twitter)' : ucfirst( $platform );
				$content     .= '- [' . $display_name . '](' . esc_url( $url ) . ")\n";
			}
		}

		$content .= "\n---\n\n";
		$content .= "*Last updated: " . current_time( 'F j, Y' ) . "*\n\n";
		$content .= "# Generated by GetCited";

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
	 * Get primary content description based on site type
	 *
	 * @param string $site_type The site type.
	 * @param array  $scan_data The scan data.
	 * @return string Description of primary content.
	 */
	private function get_primary_content_description( $site_type, $scan_data ) {
		$site_name  = $scan_data['site']['name'];
		$post_count = count( $scan_data['posts'] ?? array() );
		$cat_count  = count( $scan_data['categories'] ?? array() );

		$descriptions = array(
			'blog'       => "This site publishes articles, guides, and analysis across {$cat_count} topic areas. Content is regularly updated with new insights and perspectives.",
			'news'       => "This site publishes news articles, analysis, and commentary. Content is time-sensitive and regularly updated to reflect current events.",
			'business'   => "This site provides information about our services, company background, and industry expertise. Content reflects our professional experience and offerings.",
			'ecommerce'  => "This site offers products for purchase along with product information, guides, and customer resources.",
			'portfolio'  => "This site showcases creative work, projects, and professional capabilities.",
			'nonprofit'  => "This site shares information about our mission, programs, and impact. Content highlights our initiatives and community involvement.",
			'education'  => "This site provides educational content, courses, and learning resources. Content is designed to inform and educate our audience.",
			'community'  => "This site hosts discussions, user content, and community resources. Content represents diverse member perspectives.",
			'other'      => "This site publishes content across {$cat_count} categories. Content is regularly updated and covers various topics.",
		);

		return $descriptions[ $site_type ] ?? $descriptions['other'];
	}

	/**
	 * Clear the scan cache
	 */
	public function clear_cache() {
		delete_transient( self::CACHE_KEY );
	}
}

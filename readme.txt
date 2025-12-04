=== GetCited — AI Visibility ===
Contributors: malcolmmichaels
Tags: ai, chatgpt, claude, perplexity, llms.txt
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.5.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Get your content cited by ChatGPT, Claude, and Perplexity. Manage AI crawlers, generate llms.txt, and optimize schema for AI search engines.

== Description ==

**GetCited helps your content get discovered and cited by AI systems.**

While traditional SEO plugins optimize for Google, GetCited optimizes for the AI-powered future. ChatGPT, Claude, Perplexity, and other AI systems are answering millions of questions daily. Is your content visible to them?

= What GetCited Does =

**🤖 AI Crawler Management**
Control which AI systems can access your content. Toggle access for 26 different AI crawlers including GPTBot (ChatGPT), ClaudeBot, PerplexityBot, and more. Your settings automatically update your robots.txt.

**📄 llms.txt Generator**
Create and serve an llms.txt file — the emerging standard for AI discoverability. Like robots.txt told search engines how to crawl your site, llms.txt tells AI systems what your site is about.

**🔧 AI-Optimized Schema**
Output JSON-LD structured data that AI systems actually parse. Includes Organization, Article, Author, and FAQ schema types optimized for AI understanding.

**📊 Content Citability Scoring**
Analyze your content to see how likely it is to be cited by AI. Get a 0-100 score based on factors like:
- Clear opening summaries
- Heading structure
- FAQ sections
- Content depth
- Author attribution
- And more

**⚡ Lightweight & Fast**
Zero impact on your front-end performance. No JavaScript or CSS loaded for visitors. Works alongside your existing SEO plugin.

= Works With Your Existing Setup =

GetCited complements Yoast SEO, Rank Math, and other SEO plugins. They handle Google optimization — we handle AI visibility.

= Coming Soon (Pro Features) =

- AI Referral Traffic Dashboard (see visits from ChatGPT, Perplexity, etc.)
- Citation Tracking (know when AI cites your content)
- Full Site Audit (analyze all posts, not just 5)
- Citation Alerts (email/Slack notifications)

Join the waitlist from your dashboard to get early access!

== Installation ==

1. Upload the `getcited` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to GetCited in your admin menu
4. Run the setup wizard to configure optimal settings

== Frequently Asked Questions ==

= What is GAEO? =

GAEO stands for "Generative AI Engine Optimization" — the practice of optimizing content so it gets cited by AI systems like ChatGPT and Perplexity. It's like SEO, but for AI instead of traditional search engines.

= What is llms.txt? =

llms.txt is an emerging standard (similar to robots.txt) that helps AI systems understand your website. It provides a human and AI-readable description of your site, its sections, and key content.

= Does this replace my SEO plugin? =

No! GetCited works alongside Yoast, Rank Math, or any other SEO plugin. Traditional SEO plugins optimize for Google. GetCited optimizes for AI systems like ChatGPT, Claude, and Perplexity.

= Will this slow down my site? =

No. GetCited adds zero JavaScript or CSS to your front-end. The only additions are a small JSON-LD schema block in your page headers and the llms.txt endpoint.

= Which AI crawlers does this support? =

GetCited includes 26 AI crawlers at launch:
- OpenAI: GPTBot, ChatGPT-User, OAI-SearchBot
- Anthropic: ClaudeBot, anthropic-ai, Claude-Web
- Perplexity: PerplexityBot, Perplexity-User
- Google: Google-Extended, GoogleOther
- Apple: Applebot, Applebot-Extended
- And 14 more from Meta, Amazon, ByteDance, Cohere, and others

The crawler list updates automatically without requiring plugin updates.

= Is my data sent anywhere? =

The free version runs entirely on your server. The only external request is to fetch the latest crawler list from our server (a simple JSON file, no data sent from your site).

== Screenshots ==

1. Dashboard overview showing AI visibility status
2. AI Crawler management panel with toggle controls
3. llms.txt editor with live preview
4. Schema settings and preview
5. Content citability scoring

== Changelog ==

= 1.5.3 =
* Improved: Schema Presence score now gives full credit when SEO plugins are detected
* Improved: Dashboard shows "via [Plugin Name]" when schema is handled by external SEO plugin
* Improved: AI Citation Guidelines now enabled by default after Setup Wizard
* Improved: Saving llms.txt settings auto-regenerates content and writes physical file if enabled
* Improved: Post-wizard notice updated to success message confirming active citation guidelines
* Removed: "New" badge from Citation Guidelines (now a default feature)

= 1.5.2 =
* Improved: Larger font sizes throughout plugin for better readability
* Improved: Dashboard "Refresh" button shows completion feedback
* Improved: "Run Check" and "Re-scan" buttons styled consistently with orange primary color
* Improved: Health Check badges are now clickable links to relevant settings pages
* Improved: Schema detection now scans both homepage and a sample post
* Improved: Setup Wizard auto-detects SEO plugins and configures schema accordingly
* Improved: Citation Guidelines pre-filled with site-type defaults after wizard
* Added: HeyTC SEO plugin detection for schema compatibility
* Fixed: Schema Re-scan now detects plugins that only output JSON-LD on posts

= 1.5.1 =
* New: AI Citation Guidelines — tell AI systems how to cite your content
* New: Enhanced templates with suggested citation formats for each site type
* New: Post-setup prompt to configure citation guidelines
* Improved: llms.txt editor with collapsible Citation Guidelines section

= 1.5.0 =
* New: Posts marked "noindex" by SEO plugins are now excluded from llms.txt
* New: Posts with "Exclude from llms.txt" checked are now properly excluded
* New: WP-CLI command `wp getcited crawler-log` to view, clear, and export crawler activity
* New: Developer Tools section in Settings showing all WP-CLI commands
* Added: Support for Yoast, RankMath, SEOPress, AIOSEO, The SEO Framework noindex detection
* Added: Filter `getcited_is_noindex` for custom noindex logic

= 1.4.6 =
* Changed: Full UI redesign with HeyTC orange (#FFA500) branding
* Changed: Dashboard redesign with card-based score breakdown
* Changed: Score breakdown cards link directly to config pages
* Changed: Health Check simplified to summary badges
* Changed: All pages updated with consistent styling
* Changed: Primary buttons now use orange accent color
* Changed: Section headers with orange left border accent
* Fixed: Score circle sizing issue (was too large)
* Removed: Redundant Status Cards section from Dashboard
* Removed: Quick Links section (replaced by score navigation)

= 1.4.5 =
* New: AI Visibility Score - composite 0-100 score showing overall AI readiness
* New: Score breakdown with 5 factors: Crawler Access, llms.txt Health, Schema Presence, Citability, Freshness
* New: Actionable recommendations based on lowest-scoring areas
* New: llms.txt Request Log - track when AI crawlers access your llms.txt file
* New: Bot classification (AI Crawler, Search Engine, Browser, Unknown)
* New: Request logging settings with configurable retention period
* New: Custom database table for efficient request storage
* Added: Daily cron job for automatic log cleanup based on retention settings

= 1.4.2 =
* Fixed: Health check calling removed method get_detected_plugins() (now uses Schema Detector)

= 1.4.1 =
* Fixed: Schema re-scan AJAX could cause deadlock on some hosting (self-referential HTTP request)
* Changed: Homepage JSON-LD scan now skipped during AJAX, uses cached results instead
* Changed: Reduced HTTP timeout from 10s to 5s for schema detection

= 1.4.0 =
* New: Schema smart fallback - auto-disables when SEO plugin detected (Yoast, Rank Math, etc.)
* New: Homepage JSON-LD scan detects existing schema from any source
* New: Force-enable option for users who want GetCited schema despite detection
* New: @id entity graph connecting Organization → Author → Article for AI understanding
* New: Enhanced Author schema with knowsAbout (expertise topics) and jobTitle
* New: Author profile fields in WordPress user edit screen (LinkedIn, Twitter, Job Title, Expertise, ORCID)
* New: Organization sameAs links (LinkedIn Company, Wikipedia, Crunchbase)
* New: Weekly cron job for automatic schema source re-scanning
* New: Manual "Re-scan" button on Schema settings page
* New: Detection status UI shows what source is handling schema
* Changed: Schema now works as "smart fallback" - fills gaps SEO plugins miss
* Added: 8 SEO plugin detections (Yoast, Rank Math, AIOSEO, SEOPress, Schema Pro, Squirrly, SEO Framework, SmartCrawl)

= 1.3.0 =
* Added: Logo URL now supports WordPress Media Library picker (Upload button works)
* Added: Social profiles auto-populate from Setup Wizard site scan
* Added: Load More Posts button in Citability (free tier: up to 10 posts)
* Added: Top 3 recommendations now display in post editor meta box after analysis
* Improved: FAQ scoring clarifies it checks content, not schema settings
* Improved: Large post counts now formatted with locale separators (e.g., 153,153)

= 1.1.1 =
* Fixed: Setup wizard now works correctly when launched from Settings page (was hanging on Step 2)
* Fixed: Health Check expand buttons now work properly (restructured DOM for reliable toggling)
* Fixed: Custom Crawlers now save properly with new "Save Changes" button
* Fixed: Bulk actions (Allow All/Block All) now preserve custom crawler settings
* Performance: Site scan now runs asynchronously with progress bar UI
* Performance: Optimized key pages query from 17 individual queries to single batch query
* Performance: Added 20-item limit to category queries for large sites
* UX: Added scan progress bar with status text ("Finding your pages...", "Analyzing content...", "Building your llms.txt...")
* UX: Added "Skip scan" option for users who prefer manual configuration
* UX: Scan timeout (30 seconds) shows prominent skip option
* UX: Extended wizard scan cache from 1 hour to 24 hours
* UX: Added 60-second rate limiting on scan endpoint to prevent abuse
* Code: Strict equality comparison for menu item parent check

= 1.1.0 =
* New: Intelligent Site Scanner for llms.txt generation
* New: Wizard now scans your site and generates personalized llms.txt during setup
* New: "Scan My Site" button in llms.txt editor for re-scanning
* New: Scan results preview with stats (pages, posts, categories, menu items, social links)
* New: Support for Rank Math and SEOPress social link detection
* New: Support for modern social platforms (X/Twitter, Threads, Mastodon, Bluesky)
* New: Filter hook `getcited_scanner_generated_content` for customizing generated llms.txt
* Added: Markdown escaping to prevent broken links from special characters
* Added: 5-minute scan cache to prevent excessive database queries
* Changed: Twitter social links now normalized to X branding

= 1.0.4 =
* New: Expanded site types from 5 to 9 (added Portfolio, Nonprofit, Education, Community)
* New: Granular schema settings by site type
* New: llms.txt templates for all 9 site types
* Fixed: Wizard initialization now properly shows first step on page load
* Fixed: Health check expand buttons now work correctly with improved fallback logic

= 1.0.3 =
* Enhanced robots.txt and llms.txt handling
* Improved conflict detection

= 1.0.2 =
* Fixed: Save Changes button now works correctly across all settings pages
* Fixed: Analyze Citability button now works on post editor screens
* Fixed: Setup wizard auto-redirect now triggers on fresh plugin activation
* Added: llms.txt template loading from server (Blog, Business, News, etc.)
* Added: Expandable Health Check details with robots.txt guidance
* Added: Copy to clipboard functionality for robots.txt rules
* Added: Meta description detection for SEOPress and The SEO Framework
* Added: Filter hook `getcited_get_meta_description` for custom SEO plugin support
* Added: HTML fallback detection for meta descriptions
* Added: Content-type aware FAQ scoring (news/editorial exempt from FAQ penalty)
* Added: Filter hook `getcited_faq_exempt` for custom FAQ exemptions
* Changed: Crawler list health check now shows OK status when using bundled list

= 1.0.1 =
* Fixed WordPress Plugin Check issues
* Added IIFE wrappers to templates to prevent global variable pollution
* Added translator comments for internationalization
* Added proper input sanitization with wp_unslash()
* Added phpcs:ignore comments for intentional code patterns
* Updated tested WordPress version to 6.8
* Removed manual textdomain loading (handled by WordPress 4.6+)

= 1.0.0 =
* Initial release
* AI Crawler Control Panel (26 crawlers)
* llms.txt generator with templates
* AI-optimized schema output
* Content Citability Scoring
* Setup wizard
* WP-CLI commands

== Upgrade Notice ==

= 1.0.0 =
Initial release of GetCited. Get your content visible to AI!

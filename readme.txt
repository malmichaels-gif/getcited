=== GetCited — AI Visibility ===
Contributors: malcolmmichaels
Tags: ai, chatgpt, claude, perplexity, llms.txt
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.1
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

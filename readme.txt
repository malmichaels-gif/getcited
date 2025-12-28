=== GetCited — AI Visibility ===
Contributors: heytc
Tags: llms-txt, chatgpt, ai-seo, perplexity, ai-crawlers
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.9.9.27
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Optimize for AI search. The AI visibility plugin — manage crawlers, generate llms.txt, track citability.

== Description ==

**GetCited helps your content get discovered and cited by AI systems.**

While traditional SEO plugins optimize for Google, GetCited optimizes for the AI-powered future. ChatGPT, Claude, Perplexity, and other AI systems are answering millions of questions daily. Is your content visible to them?

= What GetCited Does =

**🤖 AI Crawler Management**
Control which AI systems can access your content. Toggle access for 31 different AI crawlers including GPTBot (ChatGPT), ClaudeBot, PerplexityBot, and more. Your settings automatically update your robots.txt.

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

= GetCited Pro — Spring 2026 =

- AI Referral Traffic Dashboard (see visits from ChatGPT, Perplexity, etc.)
- Full Citability Scoring (audit unlimited posts with export)
- Citation Share of Voice (track your competitive share)
- Competitor Quick-Check (spot-check competitor citability)
- Citation Alerts (weekly digests + Slack webhooks)
- Private Community Access (connect with other AI-focused publishers)

Join the waitlist from your dashboard for early bird pricing!

= Third Party Services =

GetCited connects to the following external service:

**Crawler List Updates**
- **Service:** GetCited API (heytc.com)
- **URL:** `https://heytc.com/getcited-api/crawlers.json`
- **When:** Once daily via WordPress cron
- **Data Sent:** None (GET request only)
- **Data Received:** JSON file (~2KB) containing AI crawler names and user-agent strings
- **Purpose:** Keeps your crawler list current without requiring plugin updates
- **Privacy Policy:** [https://heytc.com/privacy-policy/](https://heytc.com/privacy-policy/)

This request is made from your server, not from visitors' browsers. No personal data, site content, or usage information is transmitted. If the request fails, the plugin falls back to its bundled crawler list.

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

GetCited includes 31 AI crawlers:
- OpenAI: GPTBot, ChatGPT-User, OAI-SearchBot
- Anthropic: ClaudeBot, anthropic-ai, Claude-Web
- Perplexity: PerplexityBot, Perplexity-User
- Google: Google-Extended, Gemini-Deep-Research, Google-NotebookLM, GoogleOther
- Apple: Applebot, Applebot-Extended
- And 17 more from xAI, Meta, Amazon, Brave, ByteDance, Cohere, and others

The crawler list updates automatically without requiring plugin updates.

= Is my data sent anywhere? =

The free version runs entirely on your server. The only external request is to fetch the latest crawler list from our server (a simple JSON file, no data sent from your site).

== Changelog ==

= 1.9.9.27 =
* Improved: Cleaner Pro features section (removed placeholder lock icons)
* Improved: WordPress.org plugin guidelines compliance updates

= 1.9.9.26 =
* New: One-time setup completion message after wizard
* Improved: Settings page section reordering for better UX

= 1.9.9.25 =
* Improved: Verification links now bypass CDN cache for instant content preview

= 1.9.9.24 =
* Improved: Visibility score calculation ~6x faster (optimized crawler matching algorithm)
* Improved: Health check HTTP timeouts reduced from 10s to 5s for faster dashboard loads
* Improved: Async health checks - dashboard loads instantly, checks run in background
* Improved: Switched to system fonts, removing external Google Fonts dependency
* New: Rate limiting on public REST API endpoints (prevents abuse)

= 1.9.9.23 =
* Updated: Bundled crawler list now includes 31 AI crawlers (was 26)
* New: Added Gemini-Deep-Research, Google-NotebookLM, amazon-kendra, Bravebot, Groq-Bot
* Improved: Dashboard text updated to use "AI Visibility" terminology consistently

= 1.9.9.22 =
* New: Daily cron check for SEO plugin conflicts (shows warning on all admin pages)
* Improved: Renamed "llms.txt" to "AI Visibility Data" throughout UI for clarity

= 1.9.9.21 =
* Improved: Simplified SEO plugin conflict handling - automatic resolution on install
* Improved: Dashboard notice shows plugin-specific disable instructions
* Improved: Removed unnecessary "Keep existing" option - GetCited is THE llms.txt solution
* Improved: Renamed to "AI Visibility Data" for clearer user understanding

= 1.9.9.20 =
* New: Wizard conflict resolution step handles existing llms.txt during setup
* Improved: Dashboard conflict notice simplified to binary choice
* Improved: Health check reduced to 3 clear states (OK, Conflict, Error)
* Improved: Legacy GetCited files auto-cleaned during health check

= 1.9.8.6 =
* Improved: Copy refinements for clarity and user-friendliness
* Changed: Wizard now uses "AI tools" instead of technical jargon
* Changed: Dashboard messaging softened for realistic expectations
* Changed: readme.txt tagline updated

= 1.9.8.5 =
* Fixed: Auto-analyze now waits for wizard completion (site type affects scoring)
* Fixed: FAQ detection now includes WordPress core Details and Accordion blocks
* Improved: Edit post links in Citability open in new tab

= 1.9.8.4 =
* Fixed: Dashboard freshness scoring now site-type aware (news sites use day-based thresholds)
* Fixed: Per-post citability exempts news sites from freshness penalties
* Fixed: Citability post title clicks no longer scroll to top
* Fixed: Uninstall cleanup now catches all getcited data with pattern matching
* Improved: Wizard Step 6 simplified to single layout with stats and checkmarks
* Improved: Scoring explainer moved under Score Components section

= 1.9.8.3 =
* Fixed: Auto-analyze now correctly saves score (was storing array instead of number)
* Fixed: Auto-analyze deferred to admin_init so all plugins are loaded (accurate scores)
* Fixed: Wizard "Save File" now writes complete llms.txt content
* Improved: Clicking post title in Citability shows analysis; edit link icon added
* Improved: Auto-analyze excludes "Hello World", falls back to pages if no posts exist

= 1.9.8.2 =
* New: Auto-analyze 5 latest posts on activation for instant visibility score

= 1.9.8.1 =
* Improved: Copy refinements to reduce over-claim language throughout plugin
* Improved: Pro preview sections now clearly labeled as sample data
* Improved: Schema detection now confirms GetCited won't override existing plugins
* Improved: Privacy explanation added to logging settings
* Improved: Scoring methodology explained on dashboard
* Improved: Crawler controls and llms.txt pages include honest expectation-setting

= 1.9.8 =
* Fixed: Citability "Analyzed" count now correctly shows all analyzed posts, not just initially loaded ones

= 1.9.7 =
* New: Weekly automatic llms.txt refresh for high-volume publishing sites
* New: Dashboard tip explaining AI crawler timing expectations
* Improved: Security hardening for waitlist API endpoint

= 1.9.6 =
* Fixed: Wizard Step 6 now correctly populates dynamic content
* Changed: AI branding updated throughout plugin

= 1.9.5 =
* Fixed: Fatal error from removed get_current_tip_index method

= 1.9.4 =
* Fixed: Request logging now captures real AI crawler visits
* Fixed: llms.txt auto-regeneration on settings save

= 1.9.0 =
* Changed: Navigation menu reordered for better UX flow
* Changed: llms.txt and Citability moved higher (unique value propositions)
* Changed: Schema moved lower (often handled by SEO plugins)
* New order: Dashboard → llms.txt → Citability → AI Crawlers → Schema → Settings

= 1.8.9 =
* New: Dashboard tagline emphasizing AI visibility over traditional SEO
* Changed: Visibility score recommendations now action-focused without point values
* Improved: Messaging matches Citability page philosophy (guide, not gamify)

= 1.8.8 =
* New: Personalized sample Pro report using actual site data
* Improved: Pro teaser shows your site name in mock traffic dashboard
* Improved: Share of Voice shows your domain competing against blurred competitors

= 1.8.7 =
* Changed: Pro features marketing content refined
* Changed: Pro launch date updated to Spring 2026

= 1.8.6 =
* New: Inline tip bar on dashboard (replaces collapsible tips section)
* Improved: Tips now rotate with "Next" button
* Improved: Cleaner dashboard layout with tip bar below score components

Earlier versions were part of extensive private beta testing.

== Upgrade Notice ==

= 1.9.8.3 =
Fixes auto-analyze score storage bug from 1.9.8.2.

# Exciting WordPress 6.9 Features for GetCited Pro

*WordPress 6.9 "Gene" released December 2, 2025*

---

## 1. The Abilities API - Game Changer for AI Visibility

The new **Abilities API** is essentially built for plugins like GetCited. It provides a standardized, machine-readable permissions system that works across PHP, REST API, JavaScript, and **AI agents**.

> *"Creates a unified, machine-readable registry of functionalities accessible through AI integrations."*

### GetCited Pro Opportunities

| Ability | Description |
|---------|-------------|
| `ai.crawlers.allowed` | Expose which AI crawlers have permission |
| `ai.crawlers.blocked` | List blocked crawlers programmatically |
| `ai.schema.types` | Advertise available schema markup |
| `ai.llms_txt.available` | Signal llms.txt presence to AI agents |
| `ai.citation.preferred_format` | Machine-readable citation preferences |

### Why This Matters
- AI agents can query your site's capabilities programmatically
- First-mover advantage: GetCited Pro could be the first plugin to implement Abilities API for AI discoverability
- Aligns perfectly with GetCited's mission of making sites AI-visible

---

## 2. WP_Block_Processor Class

New streaming block processor for efficiently scanning large documents without memory issues.

### GetCited Pro Opportunities
- **Better FAQ extraction** - Replace current regex approach in `class-schema.php` with proper block parsing
- **Content analysis** - Scan posts for schema-relevant content (quotes, statistics, data tables)
- **Citability scoring** - More accurate analysis by understanding block structure
- **Memory-safe** - Process large posts without crashes

### Current vs. Potential

```php
// Current approach (regex-based)
preg_match_all('/<h[34][^>]*>.*?<\/h[34]>/is', $content, $matches);

// Potential with WP_Block_Processor
$processor = new WP_Block_Processor($content);
while ($processor->next_block()) {
    if ($processor->get_block_name() === 'core/heading') {
        // Proper block-aware extraction
    }
}
```

---

## 3. Block Bindings API Enhancements

Connect custom attributes or external data sources directly to block fields.

### GetCited Pro Opportunities
- **Live citation indicators** - Show "Cited by ChatGPT" badges bound to blocks
- **Real-time citability scores** - Bind per-block scores to the editor sidebar
- **External data sync** - Pull citation analytics from GetCited Pro API into block metadata
- **Dynamic schema hints** - Suggest schema improvements inline with content

---

## 4. Template Enhancement Output Buffer

Standardized HTML manipulation via `wp_template_enhancement_output_buffer` filter.

### GetCited Pro Opportunities
- **Cleaner schema injection** - Insert JSON-LD without theme conflicts
- **AI meta tags** - Add `<meta name="ai-*">` tags safely
- **Performance** - Manipulate output without multiple output buffer hacks
- **Compatibility** - Works with classic themes by default

---

## 5. Interactivity API Improvements

Smoother client-side navigation, instant search, conditional rendering.

### GetCited Pro Opportunities
- **Real-time citability updates** - Score updates as users type without page reload
- **Live schema preview** - Interactive JSON-LD preview panel
- **Dashboard components** - Reactive charts for citation analytics
- **Instant search** - Search through crawler logs and citation history

---

## 6. Performance Improvements

Smarter caching, minified stylesheets, improved cron execution.

### GetCited Pro Opportunities
- **Optimized cron** - Better daily/weekly scan scheduling
- **Feed caching** - `fetch_feed()` now uses site transients (multisite benefit)
- **Script priority** - Use `fetchpriority="low"` for non-critical GetCited scripts

---

## Implementation Roadmap

### Phase 1: Foundation (Q1 2026)
- Require WordPress 6.7+ for GetCited Pro
- Add feature detection for 6.9+ capabilities
- Implement WP_Block_Processor for FAQ extraction

### Phase 2: Abilities API (Q2 2026)
- Register GetCited abilities
- Create REST endpoint for AI agent queries
- Document machine-readable capability manifest

### Phase 3: Interactive Features (Q3 2026)
- Interactivity API dashboard components
- Block Bindings for citation indicators
- Real-time citability scoring in editor

---

## Version Requirements Recommendation

| Version | WordPress Minimum | Rationale |
|---------|-------------------|-----------|
| GetCited Free | 6.0 | Maximum compatibility |
| GetCited Pro | 6.7 | 66% adoption as of April 2025 |
| GetCited Pro (Abilities features) | 6.9 | Optional, graceful degradation |

---

## Competitive Advantage vs. Yoast & Rank Math

Yes, Yoast and Rank Math will almost certainly implement Abilities API — but GetCited has key advantages:

### 1. Focus vs. Feature Bloat

| GetCited | Yoast/Rank Math |
|----------|-----------------|
| AI visibility is **the product** | AI visibility will be **a feature** buried in settings |
| Every decision optimizes for AI | They optimize for Google SEO first, AI second |
| Lean, purpose-built | Already bloated with 100+ features |

### 2. Speed to Market

- Yoast moves slowly (enterprise, committees, backwards compatibility)
- Rank Math chases Yoast's features
- **GetCited can ship Abilities API support in Q1 2026** while they're still in planning meetings

### 3. The "And" Strategy

GetCited doesn't have to *replace* Yoast — it works **alongside** it. The schema detector already handles this gracefully. Users can have:
- Yoast for traditional SEO
- GetCited for AI visibility

That's a much easier sell than "switch from Yoast."

### 4. Community & Niche Ownership

Own the conversation around AI visibility:
- Be the voice on WordPress AI discoverability
- Build the llms.txt standard adoption
- Create the "AI Visibility Score" that becomes industry standard

**The real question:** Can GetCited establish itself as *the* AI visibility plugin before they catch up?

First-mover advantage + niche focus + community building = defensible position.

---

## Sources

- [WordPress 6.9: The Collaboration Release - Human Made](https://humanmade.com/wordpress-for-enterprise/wordpress-6-9-the-collaboration-release-that-changes-everything/)
- [What's new for developers? (November 2025)](https://developer.wordpress.org/news/2025/11/whats-new-for-developers-november-2025/)
- [WordPress 6.9 Field Guide](https://make.wordpress.org/core/2025/11/25/wordpress-6-9-field-guide/)
- [PHP 8.5 support in WordPress 6.9](https://make.wordpress.org/core/2025/11/21/php-8-5-support-in-wordpress-6-9/)
- [Miscellaneous Developer-focused Changes in 6.9](https://make.wordpress.org/core/2025/11/17/miscellaneous-developer-focused-changes-in-6-9/)

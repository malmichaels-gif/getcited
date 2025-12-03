# GetCited Schema Strategy — Developer Brief

**Date:** December 2, 2025  
**Target Version:** v1.4.0  
**Decision:** Schema becomes a smart fallback, not a core feature  
**Status:** Approved for implementation

---

> ⚠️ **Note for Developer:** This document is based on project planning documents and strategic discussion — not the actual v1.3.5 codebase. If anything in this document conflicts with or doesn't make sense given the current plugin code, defer to the v1.3.5 codebase as the source of truth, or ask Malcolm for clarification.

---

## Executive Summary

After reviewing competitive overlap, duplicate schema risks, and AI-specific opportunities, we're changing how GetCited handles schema markup.

**Old approach:** Output Organization, Article, Author, FAQ schema for all users with conflict detection for SEO plugins.

**New approach:** Detect existing schema sources. Only output schema when no other plugin is handling it. When we do output schema, focus on AI-specific properties that SEO plugins miss.

---

## The Problem with Our Original Plan

1. **80%+ of our users will have Yoast or RankMath** — They already output the same schema types we planned to build.

2. **Duplicate schema creates risk** — While identical duplicates aren't penalized, conflicting data (different prices, different authors) can cause Google to ignore both sources entirely.

3. **We were building conflict detection to solve a problem we created** — Section 7.3 of our tech spec had us detecting five different plugins just to disable our own feature. That's complexity serving nothing.

4. **No differentiation** — Our "AI-optimized schema" was the same JSON-LD structure Yoast outputs. The marketing was different; the code was identical.

---

## New Strategy: Smart Fallback + AI Enhancement

### When to Disable Schema (Default)

If ANY of these conditions are true, GetCited schema output is disabled by default:

**Plugin Detection (check on activation + weekly cron):**

| Plugin | Detection Method |
|--------|------------------|
| Yoast SEO | `defined('WPSEO_VERSION')` |
| RankMath | `defined('RANK_MATH_VERSION')` |
| All in One SEO | `defined('AIOSEO_VERSION')` |
| SEOPress | `defined('SEOPRESS_VERSION')` |
| Schema Pro | `class_exists('BSF_AIOSRS_Pro')` |
| Squirrly SEO | `defined('SQ_VERSION')` |
| The SEO Framework | `defined('THE_SEO_FRAMEWORK_VERSION')` |
| SmartCrawl | `class_exists('Smartcrawl_Loader')` |

**Direct Schema Detection (catches custom solutions):**

On activation and weekly via cron:
1. Fetch the site's homepage via `wp_remote_get()`
2. Parse response for `<script type="application/ld+json">`
3. Check if JSON contains `@type: "Organization"` or `@type: "Article"`
4. If found, assume another source is handling schema

This catches theme-bundled schema, custom implementations, and plugins we've never heard of.

### When to Enable Schema

Only when BOTH conditions are true:
- No SEO plugin detected (from list above)
- No existing JSON-LD schema found on homepage

This covers the minority of users running WordPress with zero SEO tooling.

### User Override

Settings toggle: "Enable GetCited schema (even if another plugin detected)"
- Default: Off
- Warning text: "Another schema source was detected. Enabling this may create duplicate markup."

---

## What to Build When Schema IS Enabled

Don't just copy what Yoast does. Focus on AI-specific properties they miss:

### 1. Enhanced Author Schema with Expertise

```json
{
  "@type": "Person",
  "@id": "https://example.com/#author-jane-doe",
  "name": "Jane Doe",
  "url": "https://example.com/about/jane-doe",
  "sameAs": [
    "https://linkedin.com/in/janedoe",
    "https://twitter.com/janedoe",
    "https://orcid.org/0000-0001-2345-6789"
  ],
  "jobTitle": "Senior Editor",
  "worksFor": {
    "@id": "https://example.com/#organization"
  },
  "knowsAbout": ["WordPress", "SEO", "Content Marketing"]
}
```

**Why it matters:** AI systems use author credentials to assess content authority. The `sameAs` links to professional profiles help disambiguate authors and establish E-E-A-T signals.

**UI needed:** Author settings in user profile or per-post, collecting LinkedIn URL, Twitter/X handle, job title, expertise topics.

### 2. Connected Entity Graph via @id

```json
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "@id": "https://example.com/#organization",
      "name": "Example Inc",
      "sameAs": ["https://linkedin.com/company/example"]
    },
    {
      "@type": "WebSite",
      "@id": "https://example.com/#website",
      "publisher": {"@id": "https://example.com/#organization"}
    },
    {
      "@type": "Article",
      "@id": "https://example.com/post-slug/#article",
      "author": {"@id": "https://example.com/#author-jane-doe"},
      "publisher": {"@id": "https://example.com/#organization"},
      "isPartOf": {"@id": "https://example.com/#website"}
    }
  ]
}
```

**Why it matters:** Creates a knowledge graph connecting authors → organization → content. AI systems can traverse these relationships to understand authority and attribution.

**Implementation:** Generate stable @id URLs based on entity type. Reference these IDs instead of embedding duplicate data.

### 3. Robust sameAs Collection

Prompt users more aggressively for external profile links:

**Organization:**
- LinkedIn company page
- Wikipedia/Wikidata (if applicable)
- Crunchbase
- Industry directories

**Authors:**
- LinkedIn personal profile
- Twitter/X
- ORCID (for academic/research content)
- Personal website

**Why it matters:** The `sameAs` property is how AI systems disambiguate entities. "Acme Inc" could be thousands of companies. `sameAs` links to your specific LinkedIn page tell AI exactly which Acme you are.

---

## What NOT to Build

Remove or deprioritize these from the v1.4.0 scope:

| Feature | Reason |
|---------|--------|
| Basic Organization schema | Yoast/RankMath cover this |
| Basic Article schema | Yoast/RankMath cover this |
| FAQ schema extraction | Yoast/RankMath cover this |
| Schema conflict detection UI | No longer needed if we default to disabled |
| Per-post "disable schema" toggle | Unnecessary complexity |
| WooCommerce product schema skip logic | Not our problem if schema is off by default |

---

## Implementation Checklist

### Phase 1: Detection System (Week 1)

- [ ] Create `class-schema-detector.php`
- [ ] Implement plugin constant/class checks
- [ ] Implement homepage JSON-LD scan via `wp_remote_get()`
- [ ] Store detection result in transient (7-day expiry)
- [ ] Add weekly cron job to re-scan
- [ ] Add manual "Re-scan" button in settings

### Phase 2: Conditional Schema Output (Week 2)

- [ ] Wrap all schema output in detection check
- [ ] Add settings toggle for user override
- [ ] Add admin notice when schema is auto-disabled: "GetCited detected [Yoast SEO] is handling schema markup. GetCited schema is disabled to prevent duplicates."

### Phase 3: AI-Enhanced Schema (Week 3)

- [ ] Add author sameAs fields to user profile
- [ ] Add organization sameAs fields to settings
- [ ] Implement @id-based entity graph
- [ ] Add "knowsAbout" field for author expertise topics
- [ ] Create "Enhance your AI visibility" post-setup prompt for sameAs collection

---

## Settings UI Changes

### Schema Settings Page (Simplified)

```
Schema Markup
─────────────

Status: Disabled (Yoast SEO detected)
        [Re-scan for schema sources]

□ Enable GetCited schema anyway
  ⚠️ Warning: May create duplicate markup

── If Enabled ──

Organization sameAs Links:
  LinkedIn: [________________________]
  Wikipedia: [________________________]
  Other: [________________________] [+ Add]

Author Settings:
  → Configure in Users → Your Profile → GetCited
```

### User Profile Addition

```
GetCited Author Schema
──────────────────────

LinkedIn Profile: [________________________]
Twitter/X Handle: [________________________]
Job Title: [________________________]
Areas of Expertise: [________________________]
                    (comma-separated topics)
```

---

## Decisions Made

| Question | Decision |
|----------|----------|
| Schema in this release? | Yes — targeting v1.4.0 |
| Include speakable markup? | No — skip for now, revisit later |
| sameAs collection UX | Post-setup "Enhance your AI visibility" prompt, not in wizard |

---

## Summary

| Before | After |
|--------|-------|
| Schema always on, detect conflicts | Schema off by default, enable as fallback |
| Copy what Yoast does | Focus on what Yoast misses (sameAs, @id graph, author expertise) |
| 5+ plugin detection checks | Plugin detection + direct JSON-LD scan |
| Core feature | Optional enhancement |

**Target version:** v1.4.0

**Bottom line:** We're not competing with Yoast on schema. We're filling gaps they leave. For most users, our schema code will never run — and that's fine. Our value is in crawler control, llms.txt, and citability scoring. Schema is a nice-to-have for underserved users.

---

*Document prepared from discussion between Malcolm and Claude, December 2, 2025*

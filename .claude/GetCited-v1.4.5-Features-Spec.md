# GetCited Feature Spec: v1.4.5 Free Features

**Target Version:** v1.4.5  
**Feature Type:** Free tier  
**Priority:** High  
**Estimated Effort:** 2-3 days total

---

> ⚠️ **Note for Developer:** This document is based on project planning documents and strategic discussion — not the actual v1.4.2 codebase. If anything conflicts with or doesn't make sense given the current plugin code, defer to the codebase as the source of truth, or ask Malcolm for clarification.

---

## Features in This Release

1. **llms.txt Request Log** — Track AI crawler visits to your llms.txt
2. **AI Visibility Score** — Single 0-100 score for overall AI readiness

Both features are free, run entirely on the user's WordPress install, and require no backend infrastructure.

---

# Feature #1: llms.txt Request Log

## Overview

Track and display every request to the site's /llms.txt file, showing users which AI crawlers are actively visiting their site.

**Why this matters:** The #1 question users have after installing GetCited is "Is this actually doing anything?" This feature provides immediate, tangible proof that AI systems are finding and reading their llms.txt file.

---

## User Value

- **Proof of concept** — Users see real crawler visits within days of setup
- **Validation** — Confirms llms.txt is accessible and being read
- **Engagement** — Gives users a reason to check the dashboard regularly
- **Education** — Learn which AI systems are most active
- **Upgrade path** — Natural bridge to Pro citation tracking

---

## Feature Specification

### Dashboard Widget

**Location:** Main GetCited dashboard, prominent placement

**Widget Title:** "llms.txt Activity"

**Display:**

```
llms.txt Activity
─────────────────────────────────────────────────

Recent Requests (Last 30 Days)

Dec 2, 14:32   PerplexityBot      ✓ Known AI
Dec 2, 09:17   ClaudeBot          ✓ Known AI
Dec 1, 22:45   GPTBot             ✓ Known AI
Dec 1, 18:03   Googlebot          • Search Engine
Nov 30, 11:02  python-requests    ? Unknown
Nov 29, 08:44  ChatGPT-User       ✓ Known AI

───────────────────────────────────────────────────
Total: 47 requests from 8 unique bots (Last 30 days)
───────────────────────────────────────────────────
```

### Request Classification

Classify requests by matching User-Agent against known patterns:

| Category | Badge | Examples |
|----------|-------|----------|
| Known AI Crawler | ✓ Known AI | GPTBot, ClaudeBot, PerplexityBot, etc. |
| Search Engine | • Search Engine | Googlebot, Bingbot, DuckDuckBot |
| Unknown Bot | ? Unknown | Unrecognized user agents |
| Human Browser | 👤 Visitor | Chrome, Firefox, Safari, etc. |

**Known AI Crawlers:** Match against existing crawler database (crawlers.json). If user-agent contains any known AI crawler name, classify as Known AI.

### Data Captured Per Request

| Field | Source | Storage |
|-------|--------|---------|
| Timestamp | `current_time('mysql')` | datetime |
| User-Agent | `$_SERVER['HTTP_USER_AGENT']` | varchar(500) |
| IP Address | `$_SERVER['REMOTE_ADDR']` | varchar(45) — for rate limiting only, not displayed |
| Bot Name | Parsed from User-Agent | varchar(100) |
| Category | Classification logic | varchar(20) |

### Storage

**Option A: Custom Table (Recommended)**

```sql
CREATE TABLE {$wpdb->prefix}getcited_llms_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_time DATETIME NOT NULL,
    user_agent VARCHAR(500) NOT NULL,
    bot_name VARCHAR(100) DEFAULT NULL,
    category VARCHAR(20) DEFAULT 'unknown',
    ip_hash VARCHAR(64) DEFAULT NULL,
    INDEX idx_request_time (request_time),
    INDEX idx_bot_name (bot_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Why custom table:** 
- Could be hundreds/thousands of requests per month on active sites
- Need efficient querying by date range
- Don't want to bloat wp_options
- Easy cleanup on uninstall

**Option B: Rolling Log in wp_options**

Store as serialized array, keep only last 100 requests. Simpler but less flexible.

**Recommendation:** Custom table. The feature's value scales with data retention.

### Data Retention

- **Default:** 90 days
- **Setting:** Allow user to configure (30/60/90/180 days)
- **Cleanup:** Daily cron job deletes requests older than retention period
- **Uninstall:** Table dropped on plugin deletion (unless "keep data" setting enabled)

### Privacy Considerations

- **IP addresses:** Hash with site-specific salt, use only for rate-limiting duplicate logs, never display
- **No PII:** User-Agent strings don't contain personal data
- **Disclosure:** Add note to plugin description that llms.txt requests are logged locally
- **GDPR:** Bot traffic isn't personal data, but mention in privacy policy template if provided

---

## Technical Implementation

### Hooking the Request

The llms.txt is served via `template_redirect`. Add logging at the same hook:

```php
add_action('template_redirect', 'getcited_log_llms_request', 5);

function getcited_log_llms_request() {
    // Only log if this is an llms.txt request
    if (!getcited_is_llms_txt_request()) {
        return;
    }
    
    // Don't log if logging is disabled
    if (!getcited_request_logging_enabled()) {
        return;
    }
    
    // Rate limit: Don't log same IP + User-Agent more than once per hour
    if (getcited_is_duplicate_request()) {
        return;
    }
    
    // Log the request
    getcited_insert_llms_request([
        'request_time' => current_time('mysql'),
        'user_agent'   => getcited_get_user_agent(),
        'bot_name'     => getcited_parse_bot_name(),
        'category'     => getcited_classify_request(),
        'ip_hash'      => getcited_hash_ip(),
    ]);
}
```

### Bot Name Parsing

Extract recognizable bot name from User-Agent:

```php
function getcited_parse_bot_name($user_agent) {
    // Check against known AI crawlers first
    $ai_crawlers = getcited_get_crawler_list();
    foreach ($ai_crawlers as $crawler) {
        if (stripos($user_agent, $crawler['user_agent']) !== false) {
            return $crawler['name'];
        }
    }
    
    // Check common search engines
    $search_engines = [
        'Googlebot' => 'Googlebot',
        'Bingbot' => 'Bingbot',
        'DuckDuckBot' => 'DuckDuckBot',
        'YandexBot' => 'YandexBot',
        'Baiduspider' => 'Baiduspider',
    ];
    foreach ($search_engines as $pattern => $name) {
        if (stripos($user_agent, $pattern) !== false) {
            return $name;
        }
    }
    
    // Check for common bot patterns
    if (preg_match('/^([a-zA-Z0-9_-]+)bot/i', $user_agent, $matches)) {
        return $matches[1] . 'Bot';
    }
    
    // Check for common HTTP libraries (likely bots)
    $libraries = ['python-requests', 'curl', 'wget', 'axios', 'node-fetch', 'Go-http-client'];
    foreach ($libraries as $lib) {
        if (stripos($user_agent, $lib) !== false) {
            return $lib;
        }
    }
    
    // Browser detection (probably human visitor checking their own llms.txt)
    if (preg_match('/(Chrome|Firefox|Safari|Edge|Opera)/i', $user_agent, $matches)) {
        return 'Browser (' . $matches[1] . ')';
    }
    
    return 'Unknown';
}
```

### Classification Logic

```php
function getcited_classify_request($bot_name, $user_agent) {
    $ai_crawlers = getcited_get_crawler_names(); // Array of known AI bot names
    
    if (in_array($bot_name, $ai_crawlers)) {
        return 'ai_crawler';
    }
    
    $search_engines = ['Googlebot', 'Bingbot', 'DuckDuckBot', 'YandexBot', 'Baiduspider'];
    if (in_array($bot_name, $search_engines)) {
        return 'search_engine';
    }
    
    if (strpos($bot_name, 'Browser') === 0) {
        return 'browser';
    }
    
    return 'unknown';
}
```

### Rate Limiting Duplicate Logs

Prevent log spam from aggressive crawlers:

```php
function getcited_is_duplicate_request() {
    $ip_hash = getcited_hash_ip();
    $user_agent = getcited_get_user_agent();
    $cache_key = 'getcited_req_' . md5($ip_hash . $user_agent);
    
    if (get_transient($cache_key)) {
        return true; // Already logged within the hour
    }
    
    set_transient($cache_key, 1, HOUR_IN_SECONDS);
    return false;
}
```

---

## Dashboard Widget Implementation

### Query Recent Requests

```php
function getcited_get_recent_requests($days = 30, $limit = 50) {
    global $wpdb;
    $table = $wpdb->prefix . 'getcited_llms_requests';
    
    $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT request_time, bot_name, category 
         FROM {$table} 
         WHERE request_time > %s 
         ORDER BY request_time DESC 
         LIMIT %d",
        $since,
        $limit
    ));
}
```

### Get Summary Stats

```php
function getcited_get_request_stats($days = 30) {
    global $wpdb;
    $table = $wpdb->prefix . 'getcited_llms_requests';
    
    $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    
    $total = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE request_time > %s",
        $since
    ));
    
    $unique_bots = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT bot_name) FROM {$table} WHERE request_time > %s",
        $since
    ));
    
    $ai_crawlers = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE request_time > %s AND category = 'ai_crawler'",
        $since
    ));
    
    return [
        'total' => (int) $total,
        'unique_bots' => (int) $unique_bots,
        'ai_crawlers' => (int) $ai_crawlers,
    ];
}
```

---

## UI States

### State: No Requests Yet

```
llms.txt Activity
─────────────────────────────────────────────────

No requests logged yet.

AI crawlers typically visit within 1-7 days of your 
llms.txt going live. Check back soon!

[Verify llms.txt is accessible →]
─────────────────────────────────────────────────
```

### State: Only Non-AI Traffic

```
llms.txt Activity
─────────────────────────────────────────────────

Recent Requests (Last 30 Days)

Dec 2, 14:32   Googlebot          • Search Engine
Dec 1, 09:17   Browser (Chrome)   👤 Visitor

───────────────────────────────────────────────────
Total: 5 requests (Last 30 days)

No AI crawlers detected yet. This is normal for 
new sites. AI crawlers typically discover sites 
within 1-4 weeks.

[Tips to speed up AI discovery →]
───────────────────────────────────────────────────
```

### State: AI Crawlers Visiting (Primary Success State)

```
llms.txt Activity
─────────────────────────────────────────────────

Recent Requests (Last 30 Days)

Dec 2, 14:32   PerplexityBot      ✓ Known AI
Dec 2, 09:17   ClaudeBot          ✓ Known AI
Dec 1, 22:45   GPTBot             ✓ Known AI

───────────────────────────────────────────────────
Total: 47 requests from 8 unique bots (Last 30 days)
AI Crawlers: 38 requests (81%)

🎉 AI crawlers are actively visiting your site.
───────────────────────────────────────────────────
```

---

## Settings

Add to Settings page under "Advanced" or new "Logging" section:

```
Request Logging
───────────────

☑ Log llms.txt requests
  Track when AI crawlers and other bots access your llms.txt file.

Data Retention: [90 days ▼]
  Options: 30 days, 60 days, 90 days, 180 days

[Clear Request Log]
  ⚠️ This will delete all logged requests. This cannot be undone.
```

---

## Database Migration

On update to v1.4.5:

```php
function getcited_145_create_requests_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'getcited_llms_requests';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        request_time DATETIME NOT NULL,
        user_agent VARCHAR(500) NOT NULL,
        bot_name VARCHAR(100) DEFAULT NULL,
        category VARCHAR(20) DEFAULT 'unknown',
        ip_hash VARCHAR(64) DEFAULT NULL,
        INDEX idx_request_time (request_time),
        INDEX idx_bot_name (bot_name)
    ) {$charset_collate};";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
```

---

## Uninstall Behavior

In `uninstall.php`, add:

```php
// Drop request log table
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}getcited_llms_requests");

// Clean up transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_getcited_req_%' 
     OR option_name LIKE '_transient_timeout_getcited_req_%'"
);
```

---

---

# Feature #2: AI Visibility Score

## Overview

A single composite score (0-100) displayed prominently on the dashboard showing overall AI readiness. Gamification drives engagement — users come back to "improve their score."

**Why this matters:** Users want a simple answer to "How am I doing?" The score synthesizes multiple factors into one actionable number.

**Zero cost:** Calculated entirely client-side using data already available in the plugin. No API calls, no backend.

---

## Score Components

| Factor | Weight | What It Checks |
|--------|--------|----------------|
| Crawler Access | 25 pts | % of major AI crawlers allowed (GPTBot, ClaudeBot, PerplexityBot, etc.) |
| llms.txt Health | 25 pts | Exists, valid format, accessible, has content |
| Schema Presence | 20 pts | JSON-LD detected (GetCited or other source) |
| Citability Average | 20 pts | Average citability score of 5 most recent posts |
| Freshness | 10 pts | Content updated within last 6 months |

**Total: 100 points**

---

## Score Calculation

### Crawler Access (25 points)

```php
$major_crawlers = ['GPTBot', 'ClaudeBot', 'PerplexityBot', 'ChatGPT-User', 
                   'Google-Extended', 'Applebot-Extended', 'Amazonbot'];
$allowed_count = count_allowed_crawlers($major_crawlers);
$crawler_score = ($allowed_count / count($major_crawlers)) * 25;
```

### llms.txt Health (25 points)

| Condition | Points |
|-----------|--------|
| llms.txt exists | 10 |
| Valid format (no errors) | 5 |
| Accessible (health check passes) | 5 |
| Has meaningful content (>100 chars) | 5 |

### Schema Presence (20 points)

| Condition | Points |
|-----------|--------|
| Organization schema detected | 10 |
| Article/WebPage schema on posts | 10 |

*Detected via GetCited or third-party plugin — we just check if it exists.*

### Citability Average (20 points)

```php
$recent_posts = get_posts(['numberposts' => 5, 'post_type' => 'post']);
$avg_citability = average_citability_score($recent_posts);
// Scale 0-100 citability to 0-20 points
$citability_score = ($avg_citability / 100) * 20;
```

### Freshness (10 points)

| Most Recent Post | Points |
|------------------|--------|
| Within 30 days | 10 |
| Within 90 days | 7 |
| Within 180 days | 4 |
| Older than 180 days | 0 |

---

## Dashboard Display

**Location:** Main GetCited dashboard, top section (prominent placement)

```
AI Visibility Score
─────────────────────────────────────────────────

         ┌─────────────────┐
         │                 │
         │       73        │
         │     ────────    │
         │    out of 100   │
         │                 │
         └─────────────────┘
              Good

─────────────────────────────────────────────────

Breakdown:

Crawler Access      ████████████████████░░░░  21/25
llms.txt Health     █████████████████████████  25/25
Schema Presence     ████████████████░░░░░░░░  15/20
Content Citability  ████████████░░░░░░░░░░░░  10/20
Freshness           ██░░░░░░░░░░░░░░░░░░░░░░   2/10

─────────────────────────────────────────────────

Top Recommendation:
Your most recent post is 4 months old. Publishing 
fresh content could improve your score by 8 points.

─────────────────────────────────────────────────
```

### Score Tiers

| Score | Label | Color |
|-------|-------|-------|
| 90-100 | Excellent | Green |
| 75-89 | Good | Light Green |
| 50-74 | Fair | Yellow |
| 25-49 | Needs Work | Orange |
| 0-24 | Poor | Red |

---

## Visual Design

### Score Circle

Use a circular progress indicator (SVG or CSS):

```
     ╭─────────╮
    ╱           ╲
   │     73      │
   │   ───────   │
   │  out of 100 │
    ╲           ╱
     ╰─────────╯
```

- Circle fills proportionally to score
- Color matches tier (green/yellow/orange/red)
- Animated on load for engagement

### Compact View

For dashboard widgets or sidebar:

```
┌─────────────────────────┐
│ AI Visibility: 73/100   │
│ ████████████████░░░░░░  │
└─────────────────────────┘
```

---

## Recommendations Engine

Based on lowest-scoring factors, show 1-2 actionable tips:

| Lowest Factor | Recommendation |
|---------------|----------------|
| Crawler Access | "You're blocking GPTBot. Allowing it could improve your score by X points." |
| llms.txt Health | "Your llms.txt is empty. Add a description of your site to improve AI understanding." |
| Schema Presence | "No schema detected. Enable GetCited schema or check your SEO plugin settings." |
| Citability | "Your recent posts average 45/100 citability. [View tips to improve →]" |
| Freshness | "Your most recent post is X months old. Fresh content signals active expertise." |

**Show only the top 1-2 recommendations** — don't overwhelm users.

---

## Caching & Recalculation

- **Cache score** in transient (24-hour expiry)
- **Recalculate on:**
  - Dashboard load (if cache expired)
  - Settings saved
  - New post published
  - Manual "Refresh Score" click
- **Don't recalculate** on every page load — it queries posts

---

## Implementation

### Main Calculation Function

```php
function getcited_calculate_visibility_score() {
    $score = [
        'crawler_access' => getcited_score_crawler_access(),    // 0-25
        'llms_health' => getcited_score_llms_health(),          // 0-25
        'schema_presence' => getcited_score_schema(),           // 0-20
        'citability' => getcited_score_citability_average(),    // 0-20
        'freshness' => getcited_score_freshness(),              // 0-10
    ];
    
    $total = array_sum($score);
    
    return [
        'total' => $total,
        'breakdown' => $score,
        'tier' => getcited_get_score_tier($total),
        'recommendations' => getcited_get_recommendations($score),
        'calculated_at' => current_time('mysql'),
    ];
}
```

### Storing the Score

```php
// Cache for 24 hours
set_transient('getcited_visibility_score', $score_data, DAY_IN_SECONDS);
```

---

## Implementation Checklist (AI Visibility Score)

- [ ] Create `getcited_calculate_visibility_score()` function
- [ ] Implement individual scoring functions for each factor
- [ ] Build recommendations engine
- [ ] Create dashboard widget with circular score display
- [ ] Add score tier logic and colors
- [ ] Implement transient caching (24-hour)
- [ ] Add "Refresh Score" manual recalculation
- [ ] Add score recalculation hooks (settings save, post publish)

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Dashboard engagement | +30% return visits after v1.4.5 launches |
| Score improvement attempts | Users recalculate score 2+ times in first month |
| Time to first AI crawler | Track median days from install to first AI visit logged |
| Feature adoption | 80%+ of active installs see the score widget |

---

## Combined Implementation Checklist

### Feature #1: llms.txt Request Log

**Database & Core**
- [ ] Create migration for requests table
- [ ] Add table creation to activation hook
- [ ] Add table drop to uninstall.php
- [ ] Create `class-request-logger.php`

**Logging Logic**
- [ ] Hook into template_redirect for llms.txt requests
- [ ] Implement bot name parsing
- [ ] Implement request classification
- [ ] Add rate limiting via transients
- [ ] Add logging enable/disable setting

**Dashboard Widget**
- [ ] Create widget template
- [ ] Implement recent requests query
- [ ] Implement summary stats query
- [ ] Style the three UI states

**Settings**
- [ ] Add logging toggle
- [ ] Add retention period dropdown
- [ ] Add clear log button with confirmation

**Cleanup**
- [ ] Add daily cron for retention cleanup
- [ ] Add transient cleanup to uninstall

### Feature #2: AI Visibility Score

**Calculation**
- [ ] Create `getcited_calculate_visibility_score()` function
- [ ] Implement crawler access scoring (0-25)
- [ ] Implement llms.txt health scoring (0-25)
- [ ] Implement schema presence scoring (0-20)
- [ ] Implement citability average scoring (0-20)
- [ ] Implement freshness scoring (0-10)

**Recommendations**
- [ ] Build recommendations engine
- [ ] Create recommendation text for each factor

**Dashboard**
- [ ] Create score widget with circular display
- [ ] Implement score breakdown view
- [ ] Add score tier logic and colors
- [ ] Show top 1-2 recommendations

**Caching**
- [ ] Implement transient caching (24-hour)
- [ ] Add "Refresh Score" button
- [ ] Add recalculation hooks (settings save, post publish)

---

## Future Enhancements (Not v1.4.5)

- **Email digest:** Weekly summary of llms.txt activity
- **Detailed view:** Full-page log with filtering and search
- **Export:** Download request log as CSV
- **Comparison:** "You've had 47 visits. Similar sites average 32."
- **Anomaly alerts:** Notify if crawler visits drop significantly
- **Score history:** Track how AI Visibility Score changes over time

---

*Document prepared from discussion between Malcolm and Claude, December 2, 2025*

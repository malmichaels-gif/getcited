---
name: wordpress-code-review
description: WordPress plugin/theme code review using parallel subagents across security, performance, standards, and more. Use when reviewing WordPress PHP code, plugins, themes, or when user asks to "review my plugin", "check my WordPress code", or wants quality assessment of WordPress projects.
---

# WordPress Code Review

Comprehensive review using parallel subagents. Each subagent focuses on one pillar for thorough coverage.

## Workflow

### Step 1: Identify Target Files

Determine scope:
- User-specified files/directories
- Or `git diff --name-only HEAD~1` for recent changes
- Or all `.php` files in plugin/theme

### Step 2: Dispatch Subagents

Spawn 6 parallel Tasks. Each reviews the same files through one lens.

**Task 1 - Security:**
```
Review [FILES] for WordPress security issues ONLY.
Check:
- SQL injection: All queries use $wpdb->prepare()
- XSS: Output escaped with esc_html(), esc_attr(), esc_url(), wp_kses()
- CSRF: Forms have wp_nonce_field(), handlers verify with wp_verify_nonce()
- Capabilities: current_user_can() before privileged operations
- File uploads: wp_check_filetype(), wp_handle_upload()
- Hardcoded secrets

Report each issue as:
ISSUE: [description] | FILE: [file:line] | SEVERITY: [critical/high] | FIX: [specific fix]
```

**Task 2 - Performance:**
```
Review [FILES] for WordPress performance issues ONLY.
Check:
- N+1 queries (queries inside loops)
- Missing transients for expensive/remote calls
- Assets not properly enqueued
- Heavy processing in init/wp_loaded hooks
- Missing LIMIT on large queries
- No object cache compatibility

Report each issue as:
ISSUE: [description] | FILE: [file:line] | SEVERITY: [high/medium] | FIX: [specific fix]
```

**Task 3 - WordPress Standards:**
```
Review [FILES] for WordPress coding standards ONLY.
Check:
- Yoda conditions: if ( 'value' === $var )
- Spaces inside parentheses
- Proper prefixing on functions/classes/constants
- Internationalization: __(), _e(), esc_html__() with text domain
- No closing ?> tag
- Snake_case naming

Report each issue as:
ISSUE: [description] | FILE: [file:line] | SEVERITY: [medium/low] | FIX: [specific fix]
```

**Task 4 - Error Handling:**
```
Review [FILES] for error handling issues ONLY.
Check:
- Functions return WP_Error for failures
- is_wp_error() checks before using results
- Graceful degradation if dependencies missing
- wp_die() with proper messages for fatal errors
- No exposed PHP errors to users

Report each issue as:
ISSUE: [description] | FILE: [file:line] | SEVERITY: [high/medium] | FIX: [specific fix]
```

**Task 5 - Data Handling:**
```
Review [FILES] for data handling issues ONLY.
Check:
- Input sanitized: sanitize_text_field(), sanitize_email(), absint(), etc.
- Proper use of update_option/get_option
- Proper use of update_post_meta/get_post_meta
- Validate before save, sanitize before use

Report each issue as:
ISSUE: [description] | FILE: [file:line] | SEVERITY: [high/medium] | FIX: [specific fix]
```

**Task 6 - Architecture:**
```
Review [FILES] for architecture issues ONLY.
Check:
- Single responsibility per file/class
- Hooks in main file, logic in includes
- Admin code separated from frontend
- No deprecated functions (create_function, mysql_*)
- PHP version compatibility

Report each issue as:
ISSUE: [description] | FILE: [file:line] | SEVERITY: [medium/low] | FIX: [specific fix]
```

### Step 3: Collect & Write Plan

After all subagents return:

1. **Detect conflicts** - Flag when multiple subagents report issues on the same file:line
2. **Resolve conflicts** - Security wins over performance. Note conflicts in plan for human review.
3. **Write `CODE_REVIEW_PLAN.md`**

```markdown
# Code Review Plan
Generated: [timestamp]

## Conflicts Detected
- [file:line] CONFLICT: Security recommends [X], Performance recommends [Y]
  - **Resolution:** [which fix to apply and why]

## Issues Found
Total: X issues (X critical, X high, X medium, X low)

## Critical (Fix Immediately)
- [ ] [file:line] [ISSUE TYPE]: [description]
  - **Fix:** [specific fix]
  - **Subagent:** [which subagent found this]

[...etc]
```

### Step 4: Reference During Fixes

When fixing issues:
1. Read `CODE_REVIEW_PLAN.md` to see remaining issues
2. Check "Conflicts Detected" section first
3. Fix one issue at a time
4. Mark completed with `[x]` in the plan file
5. If conversation compacts, re-read plan file to continue

### Step 5: Offer Fixes

After presenting plan: "Want me to start fixing? I found X conflicts that need your input first."

## Severity Guide

| Severity | Examples |
|----------|----------|
| Critical | SQL injection, XSS, missing nonce, capability bypass |
| High | N+1 queries, missing sanitization, unprefixed functions |
| Medium | Coding standards violations, missing i18n, deprecated functions |
| Low | Style preferences, minor optimizations |

# GetCited — AI Visibility for WordPress

> **This project is no longer maintained.** Feel free to fork it if you find it useful.

GetCited helps WordPress sites get discovered and cited by AI assistants (ChatGPT, Gemini, Grok, Claude, Perplexity, etc.) by generating `llms.txt` files, managing AI crawler access, and adding structured data markup.

## Features

- **llms.txt generation** — auto-generated from your site content, served at `/llms.txt`
- **AI crawler management** — allow or block individual AI crawlers via `robots.txt`
- **Schema markup** — JSON-LD structured data for AI discoverability
- **Citability scoring** — per-post analysis of how citable your content is
- **AI visibility score** — site-wide dashboard with actionable recommendations
- **Zero-config setup** — works automatically on activation

## Code Quality

This plugin passed WordPress.org review and was approved for the plugin directory. A final 6-pillar code review (security, performance, standards, error handling, data handling, architecture) was completed before archiving — all high and medium severity issues were resolved. No critical vulnerabilities.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Tested up to WordPress 7.0

## Installation

1. Upload the `getcited` folder to `/wp-content/plugins/`
2. Activate the plugin

See [readme.txt](readme.txt) for the full WordPress.org plugin listing, changelog, and FAQ.

## License

GPLv2 or later

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

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

1. Upload the `getcited` folder to `/wp-content/plugins/`
2. Activate the plugin

## License

GPLv2 or later

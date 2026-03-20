=== Claude WP Bridge ===
Contributors: PThagaard
Tags: claude, ai, development, debugging, rest-api
Requires at least: 5.9
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure bridge between Claude Code and WordPress. Execute PHP, run database queries, and inspect your WordPress site directly from Claude Code sessions.

== Description ==

Claude WP Bridge creates a secure, authenticated REST API that allows Claude Code (Anthropic's AI coding assistant) to interact directly with your WordPress installation.

Instead of manually copying PHP code from Claude, running it in WP Console, and pasting results back, Claude WP Bridge lets Claude execute code directly on your site and read the results — cutting out the manual round-trip entirely.

= Features =

* **Execute PHP** — Run PHP code in the full WordPress context (all functions, plugins, and themes loaded)
* **Database Queries** — Execute SQL queries directly against the WordPress database
* **Site Information** — Get comprehensive details about your WordPress installation
* **Debug Log** — Read the WordPress debug.log file remotely
* **Security First** — API key authentication, rate limiting, IP whitelisting, dangerous function blocking
* **Audit Logging** — Every request is logged with timestamp, IP, code, and result
* **Configurable Modes** — Read-only, read/write, or full access

= Security =

This plugin is designed for **development and staging environments**. While it includes multiple security layers, exposing code execution endpoints requires careful consideration:

* API keys are hashed with `wp_hash_password()` — never stored in plaintext
* Dangerous PHP functions (exec, shell_exec, system, etc.) are blocked by default
* Read-only mode prevents data modification
* Rate limiting prevents abuse
* IP whitelisting restricts access to known addresses
* Full audit trail of every execution

== Installation ==

1. Upload the `claude-wp-bridge` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Claude Bridge → Settings** in the admin menu
4. Click **Generate API Key** and copy the key (shown only once)
5. Use the API key in your Claude Code session with curl/WebFetch

== Frequently Asked Questions ==

= Is this safe for production sites? =

This plugin exposes code execution capabilities via an API. While it includes robust security measures, we recommend using it primarily on development and staging environments. If used on production, enable read-only mode and IP whitelisting.

= How does Claude Code connect to this plugin? =

Claude Code uses curl or WebFetch to make HTTPS requests to the plugin's REST API endpoints, authenticating with the API key via a Bearer token.

= Can I use this with Claude Code web sessions? =

Yes! The plugin works with both Claude Code CLI and web sessions. No local MCP server required.

== Changelog ==

= 2.0.0 =
* 13 new read-only endpoints: db-schema, options, hooks, cron, rewrite-rules, transients, woocommerce, theme-info, users, taxonomies, media, widgets
* "Copy to AI" button generates complete AI instruction guide with all endpoints documented
* Emergency kill switch in admin UI
* Execution mode badges (Read-Only / Read-Write / Full Access)
* Enhanced security: expanded blocked function list, sensitive data scrubbing
* Memory usage tracking in execute responses
* AI Guide class for server-side instruction generation

= 1.0.0 =
* Initial release
* PHP code execution endpoint
* Database query endpoint
* Site information endpoint
* Debug log reader
* API key authentication with hashing
* Rate limiting and IP whitelisting
* Dangerous function blocking
* Full audit logging with admin viewer

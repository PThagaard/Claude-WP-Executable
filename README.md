# Claude WP Bridge

> Secure bridge between Claude Code and WordPress — execute PHP, run database queries, and inspect your WordPress site directly from any Claude Code session.

## The Problem

When using Claude Code to develop WordPress projects, you constantly need live data from your site: database contents, active plugins, registered post types, option values, debug output. The current workflow is:

1. Ask Claude for a PHP snippet
2. Copy it to WP Console or WP-CLI
3. Run it
4. Copy the output back to Claude
5. Repeat dozens of times per session

**Claude WP Bridge eliminates this round-trip entirely.** Claude executes PHP directly on your WordPress site and reads the results in real-time.

## How It Works

```
Claude Code Session          Your WordPress Site
┌──────────────────┐         ┌──────────────────────┐
│                   │  HTTPS  │  Claude WP Bridge     │
│  "How many users  │ ──────► │  REST API receives    │
│   are admins?"    │         │  the request          │
│                   │         │                       │
│  Claude calls:    │         │  Authenticates via    │
│  curl .../execute │         │  API key (hashed)     │
│                   │         │                       │
│  Gets result: 3   │ ◄────── │  Executes PHP in full │
│                   │         │  WordPress context    │
│  "You have 3      │         │  and returns result   │
│   admin users."   │         │                       │
└──────────────────┘         └──────────────────────┘
```

No MCP server. No local setup. Works from any device, any Claude Code session (CLI or web).

## Quick Start

### 1. Install the Plugin

Upload the `claude-wp-bridge/` folder to your WordPress site:

```
wp-content/plugins/claude-wp-bridge/
```

Or clone this repo and copy:

```bash
cp -r claude-wp-bridge/ /path/to/wordpress/wp-content/plugins/claude-wp-bridge/
```

Activate the plugin in **WordPress Admin → Plugins**.

### 2. Generate an API Key

Go to **WordPress Admin → Claude Bridge → Settings** and click **Generate API Key**.

> **Important:** The key is shown only once. Copy it immediately and store it securely. Never commit API keys to version control.

### 3. Test the Connection

From your Claude Code session, test the bridge:

```bash
curl -s -H "Authorization: Bearer YOUR_API_KEY" \
  https://your-site.com/wp-json/claude-bridge/v1/ping
```

Expected response:

```json
{
  "success": true,
  "message": "Claude WP Bridge is active and authenticated.",
  "version": "1.0.0",
  "mode": "read_only"
}
```

### 4. Start Using It

Ask Claude to execute PHP on your site:

```bash
curl -s -X POST \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"code": "return get_option(\"blogname\");"}' \
  https://your-site.com/wp-json/claude-bridge/v1/execute
```

## API Endpoints

All endpoints require the `Authorization: Bearer <API_KEY>` header.

### `POST /wp-json/claude-bridge/v1/execute`

Execute PHP code in the full WordPress context.

**Request body:**
```json
{
  "code": "return get_option('blogname');"
}
```

**Response:**
```json
{
  "success": true,
  "return": "My WordPress Site",
  "output": "",
  "time_ms": 2.4
}
```

The code runs with all WordPress functions, plugins, and theme loaded. You can use `return` to send a value back, or use `echo`/`print` for output.

### `POST /wp-json/claude-bridge/v1/query`

Execute a database query.

**Request body:**
```json
{
  "sql": "SELECT COUNT(*) as total FROM wp_posts WHERE post_status = 'publish'"
}
```

**Response:**
```json
{
  "success": true,
  "data": [{"total": "142"}],
  "rows": 1,
  "time_ms": 1.1
}
```

> In read-only mode, only `SELECT`, `SHOW`, `DESCRIBE`, and `EXPLAIN` queries are allowed.

### `GET /wp-json/claude-bridge/v1/site-info`

Get comprehensive WordPress environment details.

**Returns:** WordPress version, PHP version, active plugins (with versions), theme info, custom post types, database info, and bridge configuration.

### `GET /wp-json/claude-bridge/v1/debug-log`

Read the last N lines of `wp-content/debug.log`.

**Query parameters:**
- `lines` (int, default: 100) — Number of lines to return.

### `GET /wp-json/claude-bridge/v1/ping`

Health check and connectivity test.

## Security

This plugin exposes code execution via an API. Security is built into every layer:

### Authentication
- API keys are generated with cryptographic randomness
- Keys are **hashed** with `wp_hash_password()` before storage — the plaintext key is never stored
- Authentication via `Authorization: Bearer <key>` header
- Keys can be revoked instantly from the admin panel

### Execution Safety
- **Dangerous function blocking** (enabled by default): `exec`, `shell_exec`, `system`, `passthru`, `popen`, `proc_open`, file deletion functions, and more are blocked
- **Execution modes**: Read-only (default), Read/Write, or Full Access
- **Read-only mode** blocks all write functions (`wp_insert_post`, `update_option`, etc.) and write SQL (`INSERT`, `UPDATE`, `DELETE`, etc.)
- **Max execution time**: Configurable per-request timeout (default: 10 seconds)
- **Output size limit**: Prevents memory exhaustion (default: 64 KB)

### Network Safety
- **Rate limiting**: Configurable requests per time window (default: 30 per 60s)
- **IP whitelisting**: Restrict access to specific IP addresses
- **HTTPS recommended**: The plugin works over HTTP but should always be used with SSL in any non-local environment

### Audit Trail
- Every request is logged: timestamp, IP, endpoint, code executed, result summary, execution time, and status
- Logs viewable in **WordPress Admin → Claude Bridge → Audit Log**
- Configurable log retention (default: 30 days)
- Filter logs by status: success, error, or blocked

## Configuration Options

| Setting | Default | Description |
|---|---|---|
| Bridge Enabled | `true` | Master on/off switch |
| Execution Mode | `read_only` | `read_only`, `read_write`, or `full` |
| Block Dangerous Functions | `true` | Prevent shell execution, file ops, etc. |
| Rate Limit | `30/60s` | Max requests per time window |
| IP Whitelist | (empty) | Restrict to specific IPs (one per line) |
| Max Execution Time | `10s` | Per-request timeout |
| Max Output Size | `65536` | Maximum response bytes |
| Log Retention | `30 days` | Auto-delete old log entries |

## Usage Examples

### Get all custom post types with counts

```bash
curl -s -X POST \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"code": "$types = get_post_types([\"_builtin\" => false], \"objects\"); $result = []; foreach ($types as $t) { $result[$t->name] = [\"label\" => $t->label, \"count\" => wp_count_posts($t->name)->publish]; } return $result;"}' \
  https://your-site.com/wp-json/claude-bridge/v1/execute
```

### Check active plugins

```bash
curl -s \
  -H "Authorization: Bearer YOUR_API_KEY" \
  https://your-site.com/wp-json/claude-bridge/v1/site-info | jq '.plugins'
```

### Query WooCommerce orders

```bash
curl -s -X POST \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"sql": "SELECT post_status, COUNT(*) as count FROM wp_posts WHERE post_type = \"shop_order\" GROUP BY post_status"}' \
  https://your-site.com/wp-json/claude-bridge/v1/query
```

### Read recent debug log

```bash
curl -s \
  -H "Authorization: Bearer YOUR_API_KEY" \
  "https://your-site.com/wp-json/claude-bridge/v1/debug-log?lines=50"
```

## Development & Contributing

This is an open-source project. Contributions are welcome!

### Project Structure

```
Claude-WP-Executable/
├── claude-wp-bridge/           # The WordPress plugin
│   ├── claude-wp-bridge.php    # Main plugin file
│   ├── includes/
│   │   ├── class-security.php  # Authentication, rate limiting, code scanning
│   │   ├── class-executor.php  # PHP execution engine
│   │   ├── class-rest-api.php  # REST endpoint registration
│   │   ├── class-logger.php    # Audit logging
│   │   └── class-admin.php     # Admin settings UI
│   ├── assets/
│   │   ├── css/admin.css       # Admin page styles
│   │   └── js/admin.js         # Admin page JavaScript
│   ├── uninstall.php           # Clean removal
│   └── readme.txt              # WordPress.org readme
├── .env.example                # Environment variable template
├── .gitignore
└── README.md
```

### Security Policy

- **Never** commit API keys, passwords, or secrets
- All credentials use environment variables or the WordPress options table (hashed)
- The `.gitignore` excludes `.env` files
- Report security vulnerabilities via GitHub Issues

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

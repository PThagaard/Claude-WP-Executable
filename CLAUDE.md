# Project: Subify

## WordPress Bridge (Claude WP Bridge v2.0.0)

This project has a live WordPress bridge. You can execute PHP code and SQL queries directly on the site.

- Site URL: https://subify.dk
- API Base: https://subify.dk/wp-json/claude-bridge/v1/
- API Key: cwpb_YF53gQbeUG8yl0SWSdSmrmaVNMt5d3lyLgh73Cx0B7wlcdxZ
- Mode: Read-Write
- WooCommerce: Active (v10.1.1)
- DB Prefix: qpzk_
- Theme: Thagaard Konsulenthus (child of Flatsome)
- WordPress: 6.9.4
- PHP: 8.4.19
- Locale: da_DK

Always use `curl -sk` flags. Pipe JSON through `| python3 -m json.tool 2>/dev/null`.

### Available Endpoints (18 total)

| Endpoint | Method | Description |
|----------|--------|-------------|
| /ping | GET | Health check |
| /site-info | GET | Full WP environment |
| /execute | POST | Run PHP code |
| /query | POST | Run SQL queries |
| /debug-log | GET | Read debug.log |
| /db-schema | GET | Database tables & structure |
| /options | GET | WordPress options |
| /hooks | GET | Registered actions & filters |
| /cron | GET | Scheduled events |
| /rewrite-rules | GET | URL routing rules |
| /transients | GET | Cached data |
| /woocommerce | GET | WC overview, products, orders, settings, shipping, taxes, gateways |
| /theme-info | GET | Theme files, menus, sidebars |
| /users | GET | User accounts (safe) |
| /taxonomies | GET | Categories, tags, custom taxonomies |
| /media | GET | Media library |
| /widgets | GET | Sidebars & widgets |

### Quick Test

```bash
curl -sk -H "Authorization: Bearer cwpb_YF53gQbeUG8yl0SWSdSmrmaVNMt5d3lyLgh73Cx0B7wlcdxZ" https://subify.dk/wp-json/claude-bridge/v1/ping
```

See the plugin's "Copy to AI" button in WP Admin > Claude Bridge for full endpoint documentation with examples.

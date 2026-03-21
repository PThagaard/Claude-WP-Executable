# Claude WP Bridge — Test Suite

Comprehensive test cases for all 18 endpoints, focused on WooCommerce development
and WordPress inspection use cases.

**Target:** Your WordPress site (set `$BRIDGE` and `$KEY` below)
**Bridge Version:** 2.0.0

## Setup

Before running tests, set your environment variables:
```bash
export KEY="cwpb_your-api-key-here"
export BRIDGE="https://your-site.com/wp-json/claude-bridge/v1"
```

---

## 1. Connection & Health

### Test 1.1 — Ping
Verify authentication and bridge status.
```bash
curl -sk -H "Authorization: Bearer $KEY" $BRIDGE/ping
```

### Test 1.2 — Site Info
Get full WordPress environment overview — the first thing to run on any new project.
```bash
curl -sk -H "Authorization: Bearer $KEY" $BRIDGE/site-info | python3 -m json.tool
```
**Use case:** Start of any dev session. Understand WP version, active plugins, theme, DB prefix, PHP version.

---

## 2. Database Schema Inspection

### Test 2.1 — List All Tables
See every table in the database with row counts and sizes.
```bash
curl -sk -H "Authorization: Bearer $KEY" $BRIDGE/db-schema | python3 -m json.tool
```
**Use case:** Discover custom tables from plugins (WooCommerce, subscriptions, analytics, etc.).

### Test 2.2 — Describe WooCommerce Orders Table
Inspect the structure of WC orders (HPOS or legacy).
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/db-schema?table=wp_wc_orders" | python3 -m json.tool
```

### Test 2.3 — Describe Products Postmeta
Understand how WooCommerce stores product data.
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/db-schema?table=wp_postmeta" | python3 -m json.tool
```

---

## 3. WordPress Options

### Test 3.1 — Common Options
Get the default set of important WordPress options.
```bash
curl -sk -H "Authorization: Bearer $KEY" $BRIDGE/options | python3 -m json.tool
```
**Use case:** Quickly check site URL, permalink structure, active theme, front page settings.

### Test 3.2 — Search WooCommerce Options
Find all WooCommerce-related settings.
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/options?search=woocommerce" | python3 -m json.tool
```
**Use case:** Debug WooCommerce configuration issues — currency, tax settings, checkout options.

### Test 3.3 — Specific Option Keys
Get specific named options.
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/options?keys=blogname,blogdescription,permalink_structure,woocommerce_currency,woocommerce_default_country" | python3 -m json.tool
```

---

## 4. WooCommerce Inspector

### Test 4.1 — Store Overview
Currency, addresses, product/order counts, tax/shipping status, store pages.
```bash
curl -sk -H "Authorization: Bearer $KEY" $BRIDGE/woocommerce | python3 -m json.tool
```
**Use case:** Understand the store setup before developing new features.

### Test 4.2 — Recent Products
Latest products with prices, SKUs, stock, and categories.
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/woocommerce?section=products&limit=10" | python3 -m json.tool
```
**Use case:** Verify product data structure, check if prices/stock are correct.

### Test 4.3 — Recent Orders
Latest orders with status, totals, payment methods.
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/woocommerce?section=orders&limit=10" | python3 -m json.tool
```
**Use case:** Debug order flow, verify payment gateway integration.

### Test 4.4 — Store Settings
Currency format, checkout options, email config.
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/woocommerce?section=settings" | python3 -m json.tool
```
**Use case:** Verify store configuration matches requirements.

### Test 4.5 — Shipping Zones
Shipping zones and methods configured.
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/woocommerce?section=shipping" | python3 -m json.tool
```
**Use case:** Debug shipping calculation issues, verify zone setup.

### Test 4.6 — Tax Configuration
Tax rates, classes, and settings.
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/woocommerce?section=taxes" | python3 -m json.tool
```
**Use case:** Verify tax setup for different countries/regions.

### Test 4.7 — Payment Gateways
Active payment methods.
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/woocommerce?section=payment-gateways" | python3 -m json.tool
```
**Use case:** Verify which payment methods are enabled/disabled.

---

## 5. Theme Inspection

### Test 5.1 — Full Theme Info
Template files, nav menus, sidebars, theme support features.
```bash
curl -sk -H "Authorization: Bearer $KEY" $BRIDGE/theme-info | python3 -m json.tool
```
**Use case:** Understand theme structure before customizing — which template files exist, what menus/sidebars are registered.

---

## 6. Taxonomies & Terms

### Test 6.1 — All Taxonomies
List every registered taxonomy (categories, tags, WooCommerce product categories, etc.).
```bash
curl -sk -H "Authorization: Bearer $KEY" $BRIDGE/taxonomies | python3 -m json.tool
```
**Use case:** Discover custom taxonomies from plugins.

### Test 6.2 — Product Categories
Get all WooCommerce product categories with counts.
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/taxonomies?taxonomy=product_cat" | python3 -m json.tool
```
**Use case:** Understand product category structure for navigation/filtering development.

### Test 6.3 — Product Tags
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/taxonomies?taxonomy=product_tag" | python3 -m json.tool
```

---

## 7. Users

### Test 7.1 — All Users with Role Summary
```bash
curl -sk -H "Authorization: Bearer $KEY" $BRIDGE/users | python3 -m json.tool
```
**Use case:** Understand user roles and counts for permission-related development.

### Test 7.2 — Customer Accounts Only
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/users?role=customer&limit=10" | python3 -m json.tool
```
**Use case:** Verify WooCommerce customer accounts exist and have correct roles.

---

## 8. Hooks & Filters

### Test 8.1 — Search WooCommerce Hooks
Find all hooks containing "woocommerce".
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/hooks?search=woocommerce" | python3 -m json.tool
```
**Use case:** Find the right hook to extend WooCommerce behavior.

### Test 8.2 — Inspect a Specific Hook
See all callbacks on the `woocommerce_checkout_process` hook.
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/hooks?hook=woocommerce_checkout_process" | python3 -m json.tool
```
**Use case:** Debug checkout issues — see what functions run during checkout.

### Test 8.3 — WordPress Init Hook
See what's hooked into `init`.
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/hooks?hook=init" | python3 -m json.tool
```

---

## 9. Cron Jobs

### Test 9.1 — All Scheduled Events
```bash
curl -sk -H "Authorization: Bearer $KEY" $BRIDGE/cron | python3 -m json.tool
```
**Use case:** Debug scheduled tasks, verify WooCommerce cron events (order cleanup, email queues), find orphaned cron jobs.

---

## 10. Rewrite Rules

### Test 10.1 — All Rewrite Rules
```bash
curl -sk -H "Authorization: Bearer $KEY" $BRIDGE/rewrite-rules | python3 -m json.tool
```
**Use case:** Debug 404 errors, understand URL routing, verify custom post type URLs.

### Test 10.2 — Product-Related Rules
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/rewrite-rules?search=product" | python3 -m json.tool
```
**Use case:** Debug WooCommerce product URL issues.

---

## 11. Transients (Cached Data)

### Test 11.1 — All Transients
```bash
curl -sk -H "Authorization: Bearer $KEY" $BRIDGE/transients | python3 -m json.tool
```
**Use case:** Debug caching issues, find expired transients, understand what's being cached.

### Test 11.2 — WooCommerce Transients
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/transients?search=wc" | python3 -m json.tool
```

---

## 12. Media Library

### Test 12.1 — Recent Media
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/media?limit=10" | python3 -m json.tool
```
**Use case:** Verify media uploads, check image sizes, audit media library.

### Test 12.2 — Images Only
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/media?mime_type=image/jpeg&limit=5" | python3 -m json.tool
```

---

## 13. Widgets & Sidebars

### Test 13.1 — All Sidebars and Widgets
```bash
curl -sk -H "Authorization: Bearer $KEY" $BRIDGE/widgets | python3 -m json.tool
```
**Use case:** Understand sidebar structure before widget development.

---

## 14. PHP Execution

### Test 14.1 — Basic WordPress Function
```bash
curl -sk -X POST -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"code": "return get_option(\"blogname\");"}' $BRIDGE/execute
```

### Test 14.2 — WooCommerce Product Count by Type
```bash
curl -sk -X POST -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"code": "if (!function_exists(\"wc_get_products\")) return \"WooCommerce not active\"; $counts = wp_count_posts(\"product\"); return (array)$counts;"}' \
  $BRIDGE/execute | python3 -m json.tool
```
**Use case:** Quick product statistics without needing a dedicated endpoint.

### Test 14.3 — WooCommerce Currency & Locale Info
```bash
curl -sk -X POST -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"code": "return [\"currency\" => get_woocommerce_currency(), \"symbol\" => get_woocommerce_currency_symbol(), \"locale\" => get_locale(), \"price_format\" => get_woocommerce_price_format(), \"decimals\" => wc_get_price_decimals(), \"decimal_sep\" => wc_get_price_decimal_separator(), \"thousand_sep\" => wc_get_price_thousand_separator()];"}' \
  $BRIDGE/execute | python3 -m json.tool
```

### Test 14.4 — Active Theme Template Hierarchy
```bash
curl -sk -X POST -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"code": "$theme = wp_get_theme(); $parent = $theme->parent(); return [\"active\" => $theme->get(\"Name\"), \"parent\" => $parent ? $parent->get(\"Name\") : null, \"template_dir\" => get_template_directory(), \"stylesheet_dir\" => get_stylesheet_directory(), \"is_child\" => is_child_theme()];"}' \
  $BRIDGE/execute | python3 -m json.tool
```

### Test 14.5 — Registered Shortcodes
```bash
curl -sk -X POST -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"code": "global $shortcode_tags; return array_keys($shortcode_tags);"}' \
  $BRIDGE/execute | python3 -m json.tool
```
**Use case:** Find all available shortcodes for content development.

### Test 14.6 — Registered Image Sizes
```bash
curl -sk -X POST -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"code": "return wp_get_registered_image_subsizes();"}' \
  $BRIDGE/execute | python3 -m json.tool
```
**Use case:** Verify thumbnail sizes for responsive image development.

---

## 15. SQL Queries

### Test 15.1 — Count Products by Status
```bash
curl -sk -X POST -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"sql": "SELECT post_status, COUNT(*) as count FROM wp_posts WHERE post_type = \"product\" GROUP BY post_status ORDER BY count DESC"}' \
  $BRIDGE/query | python3 -m json.tool
```

### Test 15.2 — WooCommerce Tables Discovery
```bash
curl -sk -X POST -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"sql": "SHOW TABLES LIKE \"%woocommerce%\""}' \
  $BRIDGE/query | python3 -m json.tool
```
**Use case:** Discover all WooCommerce-created database tables.

### Test 15.3 — Recent Orders with Customer Info
```bash
curl -sk -X POST -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"sql": "SELECT p.ID, p.post_date, p.post_status, pm1.meta_value as total, pm2.meta_value as currency FROM wp_posts p LEFT JOIN wp_postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = \"_order_total\" LEFT JOIN wp_postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = \"_order_currency\" WHERE p.post_type = \"shop_order\" ORDER BY p.post_date DESC LIMIT 5"}' \
  $BRIDGE/query | python3 -m json.tool
```

---

## 16. Debug Log

### Test 16.1 — Recent Debug Entries
```bash
curl -sk -H "Authorization: Bearer $KEY" "$BRIDGE/debug-log?lines=30" | python3 -m json.tool
```
**Use case:** Check for PHP errors after deploying code changes.

---

## Developer Use Cases Summary

| Use Case | Endpoints to Use |
|----------|-----------------|
| Starting a new dev session | `/ping` → `/site-info` → `/woocommerce` |
| Debugging a WooCommerce checkout bug | `/hooks?hook=woocommerce_checkout_process` → `/debug-log` → `/execute` |
| Understanding database structure | `/db-schema` → `/db-schema?table=X` → `/query` |
| Customizing product pages | `/theme-info` → `/taxonomies?taxonomy=product_cat` → `/execute` (WP_Query) |
| Investigating slow performance | `/cron` → `/transients` → `/hooks?search=slow_hook` |
| Verifying store configuration | `/woocommerce?section=settings` → `/options?search=woocommerce` |
| Building a shipping integration | `/woocommerce?section=shipping` → `/hooks?search=shipping` |
| Adding a payment gateway | `/woocommerce?section=payment-gateways` → `/hooks?search=payment` |
| User role management | `/users` → `/execute` (wp_roles) |
| Media/image handling | `/media` → `/execute` (image sizes) |
| SEO/URL debugging | `/rewrite-rules` → `/options?keys=permalink_structure` |
| Content taxonomy work | `/taxonomies` → `/taxonomies?taxonomy=X` |

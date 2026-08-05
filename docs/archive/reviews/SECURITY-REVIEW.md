# Security Review — Worker 3 (Audit)

**Date:** 2026-06-16 · **Scope:** All admin forms, AJAX handlers, contact form, postcode checker, WooCommerce hooks, Setup Health repair actions, uninstall/data-retention flow, role/capability handling
**Method:** Direct source read of every handler below (file:line cited), not a generic checklist pass

---

## AJAX Handlers — full inventory, all four read in full

| Handler | Nonce | Capability | Input sanitization | Verdict |
|---|---|---|---|---|
| `TTOS_Admin::ajax_order_board()` (`class-admin.php:1280`) | `check_ajax_referer('ttos_order_board', 'nonce', false)` | `current_user_can('ttos_view_orders')` | `sanitize_key()` on context/method/timing | PASS |
| `TTOS_Admin::ajax_order_action()` (`class-admin.php:1291`) | `check_ajax_referer('ttos_order_action', 'nonce', false)` | `current_user_can('ttos_update_orders')` | `absint()` order_id/prep, `sanitize_key()` status, `sanitize_textarea_field()` note | PASS |
| `TTOS_Plugin_Checker::ajax_install_plugin()` (`class-plugin-checker.php:127`) | `check_ajax_referer('ttos_plugin_installer', 'nonce')` (throws, stricter than the `false`-param style above) | **Both** `install_plugins` **and** `activate_plugins` required | `sanitize_key()` on key/slug, then matched against a **hardcoded 3-entry allowlist** (`self::plugins()`: woocommerce, stripe, fluent-smtp only) — arbitrary slugs cannot reach the installer regardless of sanitization | PASS — this is the strongest-gated handler in the codebase: fixed allowlist + dual capability + official `plugins_api()`/`Plugin_Upgrader` install path, no arbitrary URL or ZIP upload accepted |
| `tt_handle_contact()` (theme `inc/contact.php:78`) | `check_ajax_referer('tt_contact_submit', 'nonce', false)` | None (correct — public form, `nopriv` by design) | `sanitize_text_field`/`sanitize_email`/`sanitize_textarea_field` + `is_email()` | PASS — plus honeypot (silent success for bots) and 60s/IP rate-limit via transient |

**No REST routes registered** (`register_rest_route` — zero matches across both packages), so there is no API surface beyond these four admin-ajax actions.

## Capability Model

8 custom capabilities (`ttos_access`, `ttos_manage`, `ttos_view_orders`, `ttos_update_orders`, `ttos_manage_menu`, `ttos_manage_settings`, `ttos_view_reports`, `ttos_modules`) seeded at activation, each admin screen and AJAX action gated individually rather than collapsing to `manage_options`. This is the correct granular shape for a future client-handover model (matches `docs/HANDOVER.md`'s "Recommended Access Split").

## Uninstall / Data-Retention Safety — read in full (`uninstall.php`)

Confirms the brief's hard guardrail ("repair tools must never delete content automatically") is honoured at the deepest level:
- **Default behaviour is non-destructive.** Line 30-33: if `data_retention.erase_on_uninstall !== '1'`, the script returns immediately — settings, generated pages, orders, and customer history all survive a plugin removal.
- **Even with erase enabled, each destructive category has its own independent opt-in flag** (`delete_generated_pages`, `delete_menu_products`, `delete_generated_coupons`, `delete_customer_meta`, `delete_roles`) — an admin can erase coupons without touching pages, for example.
- Deletion is scoped to plugin-tagged content only (`wp_delete_post()` filtered by the plugin's own meta keys — `_ttos_generated_page`, `_ttos_menu_item`, `_ttos_generated_coupon`), never arbitrary client posts.
- The one raw `$wpdb->query()` in the codebase (line 83, customer-meta cleanup) is a hardcoded `LIKE 'ttos_%'` pattern with no interpolated user input — not an injection risk, and it's behind the same opt-in gate.

No findings. This is exemplary, not just adequate.

## Secrets / Credentials Sweep

Grepped both packages for `api_key=`, `password=`, `secret=` literal assignments — **zero matches**. No hardcoded credentials in source.

## Outstanding Item (carried, not a code defect)

**Staging WP users `claude-admin` / `codex_staging_takeaway`** exist on the live staging site (not in source control — these are runtime WP users, not committed credentials). `TTOS_Setup_Health::staging_users_removed` (added this session) now warns when these usernames are present. This is a **deployment/handover action item, not a code fix** — the check correctly surfaces it, but someone must actually delete or rotate these accounts before production handover. Carried into `RELEASE-HARDENING-TICKETS.md` as P0.

## Honeypot / Rate-Limiting Coverage

Only one public-facing unauthenticated write path exists (`tt_handle_contact`), and it has both a honeypot and a rate limit. The order-board/order-action/plugin-installer handlers are all `wp_ajax_` (not `_nopriv_`), so they're inherently behind WordPress's session auth — no honeypot/rate-limit is needed there since `current_user_can()` already gates them.

---

## Summary

**Zero security code defects found.** Every AJAX handler pairs capability + nonce; the plugin installer additionally restricts to a hardcoded allowlist; uninstall is opt-in and granular; no secrets in source; no raw SQL injection surface. The only open item is operational, not code: rotate/remove the staging-only WP user accounts before production handover (P0 ticket, not a code change).

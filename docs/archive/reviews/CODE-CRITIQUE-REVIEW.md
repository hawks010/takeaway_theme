# Code Critique Review — Worker 2 (Audit)

**Date:** 2026-06-16 · **Scope:** Full codebase at commit `d90e976` (no pending diff — this is a structural audit, not a PR review)
**Method:** graphify god-node/community analysis + targeted grep verification of every claim below (no claim in this doc is asserted without a matching grep/read result)

---

## Findings

### 1. Business logic placement: PASS

Theme (`takeaway-theme/functions.php`) contains exactly one piece of logic beyond setup/enqueue hooks: the `woocommerce_add_to_cart_fragments` filter, which calls into theme-owned template helpers (`tt_cart_count_badge()`, `tt_cart_preview_html()`) that themselves only *read* `WC()->cart` — no business rules, no data generation. Every other `tt_*`/`ttheme_*` helper in `inc/template-helpers.php` reads plugin options (`ttos_settings`, `ttos_site_content`) with fallbacks; none of them write or compute business state. Confirmed: **no theme functions.php business-data generators.**

### 2. WooCommerce template overrides: PASS (zero exist)

`find takeaway-theme -iname woocommerce -type d` returns nothing. All Woo page styling is CSS-only against default markup (`woo.css`). This is the lowest-maintenance option for WC core upgrades — there's no copied template that can silently drift from upstream structure changes. The only programmatic Woo touchpoint is the cart-fragments filter, which is additive (doesn't replace core fragment behaviour, just adds two new fragment keys).

### 3. Order data access: PASS (HPOS-safe, no meta hacks)

Grepped for direct `get_post_meta($order_id, ...)` / `update_post_meta($order_id, ...)` calls anywhere in `takeaway-os/` — **zero matches**. Proper CRUD API usage confirmed: 21 calls to `->get_meta(`, 6 to `->update_meta_data(`, 6 to `wc_get_order(`. HPOS compatibility is also explicitly declared in `takeaway-os.php` via `FeaturesUtil::declare_compatibility('custom_order_tables', ...)`. No findings.

### 4. Raw database queries: ONE found, benign

Only one `$wpdb->query()` call in the entire codebase: `takeaway-os/uninstall.php:83`, a static `DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ttos_%' OR meta_key LIKE '_ttos_%'` cleanup statement. No user input is interpolated — it's a hardcoded pattern run once at uninstall. Not a SQL-injection risk. Worth noting for the Security worker as a uninstall-safety item (does it run unconditionally, or gated by the `data_retention` flag? — see Security review for the answer) rather than a code-quality issue.

### 5. CRM duplication: PASS (intentionally separate, not duplicated)

Two systems that could be mistaken for overlapping CRMs are deliberately scoped apart per the original architecture decision (D9 in `docs/V1.3.0-AUDIT-AND-PLAN.md`):
- **Customer CRM** (`class-admin.php` → Customers/Reports screens) — order history, segments, campaigns.
- **Site Content CRM** (`class-site-content.php`) — marketing/content configuration (homepage copy, social links, banner/popup).
These are different domains (customer data vs. site configuration), not a duplicated system. No finding.

### 6. Maintainability: WATCH (not a release blocker)

Two files are large enough to be the graph's #1 and #3 god nodes:
- `class-admin.php` — 140.3 KB, 84 graph edges. Owns 11 of 14 admin screens plus the Orders-cockpit AJAX layer.
- `class-site-content.php` — 96.3 KB, 65 edges. Single option (`ttos_site_content`) with ~11 tabbed sections all in one class.

Neither shows evidence of being *broken* — they're large because they're wide (many independent screens/sections), not because of duplication or tangled responsibility. Splitting either is a real refactor with real regression risk for a release-candidate hardening pass; **recommend deferring to v1.4.0+ as a tracked tech-debt item, not doing it now.** Flagging as P2/Future, not P0/P1.

### 7. Update-safety (Page Manager content): PASS

`class-page-manager.php` generates pages via HTML marker comments (`<!-- takeaway-os:key -->`) and three explicit safe-repair modes (`fresh_if_unsafe` / `append` / `replace`), never blind overwrite. Confirmed by design doc D1/D7 and unchanged by this session's work. No finding — this is the correct pattern and should be the template for any future content-generation feature.

### 8. Code smells scan: no blockers found

- No `eval()`, no `extract()` on superglobals, no `unserialize()` on user input (not grepped exhaustively line-by-line, but none surfaced across any file touched or read this session).
- Capability checks are consistently paired with nonce checks in every AJAX handler read (see Security review for the full list) — no inconsistent guard-clause shape across handlers.
- CSS token discipline holds: every contrast fix made this session replaced a hardcoded-color reference with a token reference, never the reverse.

---

## Summary

No P0 code-quality blockers. One P2/Future watch item (large-file maintainability in `class-admin.php` / `class-site-content.php`) carried into the tickets doc for v1.4.0+ consideration, not for this release. Order-data access, template-override discipline, and content-generation safety are all sound and should not be touched defensively — they're already the right shape.

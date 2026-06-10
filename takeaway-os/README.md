# Takeaway OS v1.2.5

Production hardening pass for Takeaway Theme / Takeaway OS.

## New in v1.2.5

- Beta hardening pass for payment, email, handover roles, update, uninstall/reinstall, and documentation flows.
- Setup Health now separates staging/manual payment readiness from Stripe/live card readiness.
- Setup Health now reports SMTP plugin status, detectable SMTP configuration, WooCommerce order email availability, admin email, and sender details.
- Setup Health email test now warns when SMTP delivery is not configured instead of sending into an unverified mail path.
- Existing installs sync safer owner, manager, kitchen, and driver role capabilities during plugin update.

## New in v1.2.4

- Checkout fix: advanced delivery-zone validation and fees now apply only to delivery orders, so collection orders are no longer blocked by delivery minimums or charged delivery-zone fees.
- Reports fix: fulfilment split now reads the same `_ttos_fulfilment_method` meta used by checkout and the order cockpit, so delivery and collection totals report correctly.

## New in v1.2.3

- Dedicated Setup Health admin screen with pass/warn/fail cards.
- Repair actions for pages, menu assignments, starter content, and Launchpad reset.
- Launchpad progress summary with setup percentage and missing-item shortcuts.
- WooCommerce My Account page generation now uses the native WooCommerce account shortcode.

## New in v1.2.2

- Production Tools admin screen.
- Emergency ordering pause switch.
- Menu CSV import/export.
- Starter menu profile with configured kebab, pizza and burger options.
- Safe page repair with fresh/append/replace modes.
- Inventory Lite auto-reset for non-stock sold-out items.
- Low-stock watchlist.
- Campaign email send/export tools for CRM campaigns.
- Client handover report.
- Built-in production readiness meter.
- Fixed hardening page readiness check that could falsely flag generated pages.

## Notes

WooCommerce remains the source of truth for products, cart, checkout, orders, refunds and payment gateways. Takeaway OS provides the restaurant-specific management overlay, public ordering shortcodes and operational cockpit.


## v1.2.5
- Adds beta-readiness health checks for Stripe/test mode, manual staging gateways, SMTP configuration, WooCommerce order email settings, admin email, sender details, and handover roles.
- Syncs updated role capabilities on plugin update so managers and kitchen users do not inherit owner-only settings access.
- Keeps uninstall preserve-by-default behavior and documents HPOS-safe order checks.

## v1.2.4
- Fixes advanced delivery-zone checkout rules so they respect the selected fulfilment method.
- Fixes fulfilment reporting so collection and delivery orders no longer fall into `Unknown`.

## v1.2.3
- Adds Setup Health checks and repair actions for public pages, menu locations, payments, email, and theme state.
- Adds Launchpad setup progress summaries and direct shortcuts into Setup Health.
- Aligns the generated My Account page with WooCommerce page assignments.

## v1.2.2
- Added proper uninstall/data-retention controls.
- Added generated-data markers for safe clean-up of Takeaway pages, menu products and coupons.
- Packaged with Takeaway Theme bundled installer support.

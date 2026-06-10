# Takeaway Handover

## Handover Mode

- Open `Takeaway OS -> Operations`.
- Enable `Client mode` when the build is ready for owner use.
- Keep `Hide normal WordPress/Woo menus for non-admin restaurant users` enabled unless you intentionally want broader access.

## Recommended Access Split

- Restaurant owner:
  - Launchpad
  - Setup Health
  - Menu Builder
  - Orders / Kitchen
  - Customers / Reports
  - Business, Payments, and Delivery settings
- Restaurant manager:
  - Menu Builder
  - Orders / Kitchen
  - Customers / Reports
- Kitchen user:
  - Orders / Kitchen only
- Administrator only:
  - Theme/plugin uploads and updates
  - Replace-content repair
  - Module lock creation/change
  - Destructive uninstall/data-retention settings
  - Production/debug tooling outside normal owner workflow

## Add-on Locking

- Add-on switching is intended to stay behind the module lock.
- Full administrators always retain access.
- Use the Add-ons screen before handover to leave only included modules enabled.

## Data Retention / Uninstall

- Default behavior preserves data on uninstall.
- `Erase Takeaway OS data when the plugin is deleted` should stay off for client installs.
- Generated pages, products, coupons, and customer meta are only removed when the erase toggle is enabled and the matching retention options are checked.

## Before First Client Beta

- Setup Health should be green or have only understood staging warnings.
- BACS/manual payment may be used for staging orders.
- Stripe must stay in test mode if card testing is performed before production.
- FluentSMTP or another transactional mailer must be configured before relying on order emails.
- Use `wp wc shop_order list` for WP-CLI order checks on HPOS stores.

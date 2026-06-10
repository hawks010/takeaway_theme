# Takeaway OS Progress

## Current Version
- Theme target: `0.2.6-bundled`
- Plugin target: `1.2.5`
- Working date: `2026-06-08`

## v1.2.5 Beta Hardening Pass

### Scope Guardrails
- Worked only on staging: `/home/u363235284/domains/thatdeveloper.co.uk/public_html/takeaway`
- Confirmed `home` and `siteurl` stayed `https://takeaway.thatdeveloper.co.uk`
- Did not touch `~/public_html`
- Did not touch `~/domains/maliandme.co.uk`
- Did not delete staging content
- Used only safe manual/BACS staging payment; no live card charge was attempted

### Backup Paths
- Backup root: `/home/u363235284/backups/takeaway-os-beta-hardening-20260608-045552`
- Theme backup: `/home/u363235284/backups/takeaway-os-beta-hardening-20260608-045552/theme/takeaway-theme`
- Plugin backup: `/home/u363235284/backups/takeaway-os-beta-hardening-20260608-045552/plugin/takeaway-os`
- Database backup: `/home/u363235284/backups/takeaway-os-beta-hardening-20260608-045552/db/takeaway.sql`
- `.htaccess` backup: `/home/u363235284/backups/takeaway-os-beta-hardening-20260608-045552/config/.htaccess`
- Uploaded release artifacts:
  - `/home/u363235284/backups/takeaway-os-beta-hardening-20260608-045552/deploy/takeaway-os-v1.2.5.zip`
  - `/home/u363235284/backups/takeaway-os-beta-hardening-20260608-045552/deploy/takeaway-theme-v0.2.6-bundled.zip`

### Versions Before / After
| Item | Before v1.2.5 pass | After v1.2.5 pass |
| --- | --- | --- |
| Theme | `takeaway-theme 0.2.5` | `takeaway-theme 0.2.6` |
| Takeaway OS | `1.2.4` | `1.2.5` |
| WooCommerce | `10.8.1` | `10.8.1` |
| WooCommerce Stripe Gateway | `10.7.0` | `10.7.0` |
| FluentSMTP | `2.2.95` | `2.2.95` |

### Files Changed / Deployed
- `takeaway-os/takeaway-os.php`
  - bumped plugin version to `1.2.5`
  - added version-aware runtime upgrade call
- `takeaway-os/includes/class-activator.php`
  - added safe version upgrade routine for role/capability sync and default settings seeding
  - hardened handover role capabilities
- `takeaway-os/includes/class-setup-health.php`
  - added payment gateway, Stripe mode, manual staging gateway, SMTP, WooCommerce email, sender, admin email, and handover-role checks
  - made the test email action warn clearly when email delivery is not configured
- `takeaway-os/includes/class-hardening.php`
  - aligned role readiness checks with the handover model
- `takeaway-os/includes/class-admin.php`
- `takeaway-os/includes/class-features.php`
- `takeaway-os/assets/admin.js`
  - removed casual placeholder wording from user-facing admin strings
- `takeaway-theme/style.css`
- `takeaway-theme/functions.php`
- `takeaway-theme/inc/plugin-checklist.php`
- `takeaway-theme/inc/bundled-plugins/manifest.json`
- `takeaway-theme/inc/bundled-plugins/takeaway-os.zip`
  - bumped theme and bundled plugin metadata to `0.2.6 / 1.2.5`
- `docs/INSTALL.md`
- `docs/TESTING.md`
- `docs/HANDOVER.md`
- `docs/TROUBLESHOOTING.md`
- `takeaway-os/README.md`
- `takeaway-theme/README.md`
- `takeaway-v1.2.5-critical-check.txt`

### Update Path Verification
- Deployed the new bundled theme files first, leaving active Takeaway OS at `1.2.4`
- Browser confirmed the theme setup screen showed: installed `1.2.4` older than bundled `1.2.5`
- Browser clicked `Update bundled Takeaway OS`
- Browser redirected to Launchpad with `ttheme_notice=bundle-updated`
- WP-CLI confirmed Takeaway OS active at `1.2.5`
- Settings/data were preserved:
  - `ttos_version = 1.2.5`
  - `ttos_settings` still present
  - `data_retention.erase_on_uninstall = 0`
- Newer-plugin downgrade path was not exercised on staging to avoid artificial version manipulation

### Setup Health
- Current status after v1.2.5: `36 pass / 2 warnings / 0 failures`
- Warning: Stripe is installed and active as a plugin, but disabled in WooCommerce; mode detected as test mode
- Warning: FluentSMTP is active, but no saved connection/settings record was detected
- Pass: BACS/manual staging gateway is enabled as `Bank transfer staging`
- Pass: WooCommerce new-order admin email is available and enabled
- Pass: WordPress admin email is present
- Pass: WooCommerce email sender details are present
- Pass: handover roles and capabilities match the v1.2.5 model

### Handover Role Checks
| Role | Result |
| --- | --- |
| Administrator | Has full WordPress/admin capabilities and `ttos_modules` |
| Takeaway Owner | Has Takeaway OS/settings/menu/orders/reports access, without plugin/theme switching/deletion caps |
| Takeaway Manager | Has Takeaway OS/menu/orders/reports access, without `ttos_manage_settings` or `ttos_modules` |
| Kitchen Staff | Has order view/update workflow access only |
| Delivery Driver | Has order view/update workflow access only |

### Payment / Email Checks
- Stripe plugin: installed and active
- Stripe WooCommerce gateway: disabled
- Stripe mode: test mode detected
- Stripe API credentials: not detected
- Manual staging gateway: BACS enabled as `Bank transfer staging`
- FluentSMTP plugin: active
- SMTP credentials/settings: not detected
- Admin email: `webmaster@inkfire.co.uk`
- WooCommerce email sender: `"blueprint" <webmaster@inkfire.co.uk>`
- Test email was not sent because Setup Health correctly warns that email delivery is not configured

### Browser Smoke Test
- Homepage desktop: Pass
- Homepage mobile: Pass
- Header/footer render: Pass
- Menu desktop: Pass
- Menu mobile: Pass
- Starter menu products visible: Pass
- Product configurator opens: Pass
- Required pizza crust group visible and preselected: Pass
- Extras/options update visible total: Pass, Margherita with Deep pan + Extra cheese showed `£11.99`
- Basket works and preserves item meta: Pass
- Checkout shows delivery/collection controls: Pass
- Safe manual payment order placed: Pass, order `#460`, total `£13.49`, payment `Bank transfer staging`
- Order appears in Takeaway order cockpit: Pass
- Kitchen screen accepts order and sets prep time: Pass, order `#460` accepted with `20m`
- Customer appears in CRM: Pass
- Reports dashboard loads: Pass
- Setup Health has clear pass/warn/fail status: Pass

### Screenshots Captured
- `test-artifacts/screenshots/theme-setup-update-1.2.5-before.png`
- `test-artifacts/screenshots/theme-setup-update-1.2.5-after.png`
- `test-artifacts/screenshots/launchpad-1.2.5.png`
- `test-artifacts/screenshots/setup-health-1.2.5-payment-email-roles.png`
- `test-artifacts/screenshots/homepage-desktop-1.2.5.png`
- `test-artifacts/screenshots/homepage-mobile-1.2.5.png`
- `test-artifacts/screenshots/menu-page-desktop-1.2.5.png`
- `test-artifacts/screenshots/menu-page-mobile-1.2.5.png`
- `test-artifacts/screenshots/product-configurator-modal-1.2.5.png`
- `test-artifacts/screenshots/product-configurator-price-update-1.2.5.png`
- `test-artifacts/screenshots/basket-page-1.2.5.png`
- `test-artifacts/screenshots/checkout-page-1.2.5.png`
- `test-artifacts/screenshots/order-received-1.2.5.png`
- `test-artifacts/screenshots/order-cockpit-1.2.5.png`
- `test-artifacts/screenshots/order-cockpit-after-accept-1.2.5.png`
- `test-artifacts/screenshots/kitchen-screen-1.2.5.png`
- `test-artifacts/screenshots/crm-dashboard-1.2.5.png`
- `test-artifacts/screenshots/reports-dashboard-1.2.5.png`

### Local Release Checks
- PHP lint passed across all plugin/theme PHP files
- JS syntax checks passed across plugin/theme JavaScript files
- ZIP structure passed:
  - `takeaway-os-v1.2.5.zip` installs to `takeaway-os/`
  - `takeaway-theme-v0.2.6-bundled.zip` installs to `takeaway-theme/`
  - bundled plugin exists at `takeaway-theme/inc/bundled-plugins/takeaway-os.zip`
  - bundled plugin installs to `takeaway-os/`
- Archive junk scan passed:
  - no `.DS_Store`
  - no `__MACOSX`
  - no `.log`
  - no backup folders

### Remaining Risks / Blockers
- Paid production remains blocked until live Stripe/payment is configured and a live payment verification is completed
- Paid production remains blocked until SMTP credentials are configured and real email delivery is confirmed
- Fresh install simulation in a disposable clone was not run in this pass; staging was used for the version-aware bundled updater path instead
- Browser login tests for owner/manager/kitchen users were not run because no existing non-admin users use those roles; capability checks were verified with WP-CLI without creating/deleting users
- Required option blocking could not be tested as an empty required group because seeded required pizza crust groups have a default valid option

### Recommendation
| Target | Recommendation |
| --- | --- |
| Local testing | Safe |
| Staging testing | Safe |
| First client beta | Safe with Stripe/email warnings explained |
| Paid production install | Not safe yet |

## Environment Details
- Workspace: `/Users/sonny-work/Documents/Takeaway theme`
- Remote staging target: `https://takeaway.thatdeveloper.co.uk/`
- Confirmed remote docroot: `~/domains/thatdeveloper.co.uk/public_html/takeaway`
- Confirmed untouched paths:
  - `~/public_html`
  - `~/domains/maliandme.co.uk`
- `home/siteurl`: unchanged, still `https://takeaway.thatdeveloper.co.uk`

## Backup Paths
- Initial staging deployment backup root: `/home/u363235284/backups/takeaway-os-20260608-025318`
- Initial theme backup: `/home/u363235284/backups/takeaway-os-20260608-025318/theme/takeaway-theme`
- Initial plugin backup: `/home/u363235284/backups/takeaway-os-20260608-025318/plugin/takeaway-os`
- Initial database backup: `/home/u363235284/backups/takeaway-os-20260608-025318/db/takeaway.sql`
- Checkout debug backup root: `/home/u363235284/backups/takeaway-os-checkout-20260608-042755`
- Checkout debug theme backup: `/home/u363235284/backups/takeaway-os-checkout-20260608-042755/theme/takeaway-theme`
- Checkout debug plugin backup: `/home/u363235284/backups/takeaway-os-checkout-20260608-042755/plugin/takeaway-os`
- Checkout debug database backup: `/home/u363235284/backups/takeaway-os-checkout-20260608-042755/db/takeaway.sql`
- Checkout debug config backup: `/home/u363235284/backups/takeaway-os-checkout-20260608-042755/config/.htaccess`
- Release deploy uploads:
  - `/home/u363235284/backups/takeaway-os-checkout-20260608-042755/deploy/takeaway-os-v1.2.4.zip`
  - `/home/u363235284/backups/takeaway-os-checkout-20260608-042755/deploy/takeaway-theme-v0.2.5-bundled.zip`

## Versions Before / After
| Item | Original staging baseline | After v1.2.3 pass | After v1.2.4 pass |
| --- | --- | --- | --- |
| Theme | `takeaway-theme 0.2.2` | `takeaway-theme 0.2.4` | `takeaway-theme 0.2.5` |
| Plugin | `takeaway-os 1.2.2` | `takeaway-os 1.2.3` | `takeaway-os 1.2.4` |
| WooCommerce | `10.8.1` | `10.8.1` | `10.8.1` |
| WooCommerce Stripe Gateway | `10.7.0` | `10.7.0` | `10.7.0` |
| FluentSMTP | `2.2.95` | `2.2.95` | `2.2.95` |

## Files Changed / Deployed
- `takeaway-os/includes/class-features.php`
  - fixed advanced delivery-zone checkout logic so delivery-only minimums and fees no longer apply to collection orders
- `takeaway-os/includes/class-analytics.php`
  - fixed fulfilment reporting to read `_ttos_fulfilment_method`
- `takeaway-os/takeaway-os.php`
  - bumped plugin version to `1.2.4`
- `takeaway-theme/archive-product.php`
  - retained the earlier menu-page hotfix
- `takeaway-theme/style.css`
  - bumped theme version to `0.2.5`
- `takeaway-theme/functions.php`
  - bumped theme constant to `0.2.5`
- `takeaway-theme/inc/plugin-checklist.php`
  - updated bundled Takeaway OS version to `1.2.4`
- `takeaway-theme/inc/bundled-plugins/manifest.json`
  - updated bundled Takeaway OS version to `1.2.4`
- `takeaway-theme/inc/bundled-plugins/takeaway-os.zip`
  - refreshed bundled plugin archive to `1.2.4`
- `docs/INSTALL.md`
- `docs/TESTING.md`
- `takeaway-os/README.md`
- `takeaway-theme/README.md`

## Setup Health
- Current status: `30 pass / 0 warn / 0 fail`
- Browser verification after release bump:
  - `100% complete`
  - `30 Passing checks`
  - `0 Warnings`
  - `0 Failures`

## Setup Health Verification
- `Home` exists and is assigned as static front page: Pass
- `Menu` exists and is assigned as WooCommerce shop page: Pass
- `Basket` is assigned as WooCommerce cart page: Pass
- `Checkout` is assigned as WooCommerce checkout page: Pass
- `My Account` is assigned as WooCommerce account page: Pass
- Header menu exists and is assigned: Pass
- Footer menu exists and is assigned: Pass
- Starter menu products exist: Pass
- Generated pages styled and not blank:
  - Homepage: Pass
  - Menu page: Pass
  - Basket: Pass
  - Checkout: Pass

## Checkout Submission Debug / v1.2.4

### Summary
- The earlier conclusion that checkout was not creating WooCommerce orders was incorrect.
- The browser checkout flow was already reaching WooCommerce checkout processing.
- The false negative came from checking orders with `wp post list --post_type=shop_order` on a store using WooCommerce order storage that was not reflected there for this verification path.
- Using `wp wc shop_order list` showed real orders existed.

### Current staging state before this pass
- Theme: `0.2.4`
- Plugin: `1.2.3`
- WooCommerce: `10.8.1`
- Stripe: `10.7.0`
- FluentSMTP: `2.2.95`
- Checkout page ID: `393`
- Checkout page content: `[woocommerce_checkout]`
- Cart page ID: `406`
- Cart page content includes:
  - `<!-- takeaway-os:cart -->`
  - `[woocommerce_cart]`
- Terms page ID: `424`
- Safe manual payment enabled:
  - `woocommerce_bacs_settings.enabled = yes`
  - title: `Bank transfer staging`

### Browser / network / log findings
- Browser storefront checkout reached the normal WooCommerce order-received flow after the fix.
- WooCommerce checkout AJAX was confirmed server-side by:
  - `wp-content/uploads/wc-logs/place-order-debug-22a528b2-2026-06-08-e8325f1f2d7ed31f115aeeb7f45abde8.log`
- That log showed:
  - `Place Order flow initiated`
  - `Session updated with checkout data and totals calculated`
  - `Checkout posted data validated`
- `wp-content/debug.log` existed but was empty.
- `error_log` only showed older WP-CLI `db export` noise due disabled `exec()`, not checkout failures.

### Root cause
- `takeaway-os/includes/class-features.php` applied advanced delivery-zone rules without respecting the selected fulfilment method.
- Two bad side effects followed:
  - collection orders could be blocked by delivery minimums
  - collection orders could be charged delivery-zone fees

### Reproduction evidence before the fix
- Collection test with postcode `MK18 1AA`, quantity `3`, total `£9.00`, fulfilment `collection`:
  - failed with `Minimum delivery order for Local is £12.00.`
- Collection order `#456` was created with fulfilment meta `collection` but incorrectly included fee line:
  - `Delivery zone: Local = £1.50`

### Fix applied
- Added fulfilment-method detection that reads:
  - direct checkout post data
  - serialized checkout `post_data`
  - WooCommerce session
  - configured checkout default as fallback
- Restricted advanced zone fee application to `delivery` only.
- Restricted advanced zone minimum validation to `delivery` only.

### Additional bug found during admin verification
- Reports page fulfilment split showed every order as `Unknown`.
- Cause:
  - `takeaway-os/includes/class-analytics.php` read `_ttos_fulfilment_type` / `_ttos_fulfilment`
  - current orders store fulfilment in `_ttos_fulfilment_method`
- Fix:
  - reporting now checks `_ttos_fulfilment_method` first

### Post-fix test evidence
- Order `#457`
  - created successfully as `delivery`
  - total `£16.50`
  - includes delivery fee line `Delivery zone: Local = £1.50`
- Order `#458`
  - created successfully as `collection`
  - total `£9.00`
  - no delivery-zone fee lines
- Order `#459`
  - created in the browser via public checkout
  - order-received page rendered successfully
  - total `£11.45`
  - fulfilment `collection`
  - payment method `Bank transfer staging`
  - item meta preserved:
    - `Make it a meal: Meal with fries + drink +£3.50`
    - `Extras: Cheese +£1.00`
- Browser admin verification for `#459`
  - present in Order Cockpit: Pass
  - accepted with `20m` prep time: Pass
  - visible in Kitchen Screen as `ACCEPTED / PREP 20M / DUE IN 20M`: Pass
  - customer visible in CRM: Pass
  - reports page includes the order: Pass
- Reports page after analytics fix:
  - `Delivery 1`
  - `Collection 4`
  - `Unknown 0`

### Orders created during checkout debugging
- `#455` collection, browser-origin order from earlier checkout pass
- `#456` collection, pre-fix curl test showing incorrect delivery-zone fee
- `#457` delivery, post-fix delivery validation/order test
- `#458` collection, post-fix collection test below delivery minimum
- `#459` collection, browser end-to-end order used for cockpit/kitchen/CRM/report verification
- Left in staging for audit:
  - no cleanup performed
  - no content deleted

## Browser Tests Run
- Theme setup / bundled plugin status screen after release bump
- Setup Health after release bump
- Homepage desktop
- Homepage mobile
- Menu page desktop
- Menu page mobile
- Product configurator modal
- Basket page
- Checkout page
- Browser order placement to order-received page
- Order cockpit after checkout fix
- Kitchen screen after accepting browser-created order
- CRM dashboard after browser-created order
- Reports dashboard after checkout/reporting fixes

## Browser Test Results
- Homepage loads and is styled: Pass
- Header/footer render: Pass
- Menu page shows starter items: Pass
- Product modal/configurator opens: Pass
- Required option handling works: Pass
- Basket works: Pass
- Checkout shows delivery/collection controls: Pass
- Safe manual payment order can be placed: Pass
- Order appears in Takeaway order cockpit: Pass
- Kitchen screen can accept order and set prep time: Pass
- Customer appears in CRM: Pass
- Reports page loads: Pass
- Reports fulfilment split is correct: Pass
- Setup Health shows clear pass/fail status: Pass

## Screenshots Captured
- Existing staging pass screenshots retained:
  - `test-artifacts/screenshots/theme-setup-update-screen.png`
  - `test-artifacts/screenshots/launchpad-after-plugin-update.png`
  - `test-artifacts/screenshots/launchpad-screen.png`
  - `test-artifacts/screenshots/setup-health-before-repair.png`
  - `test-artifacts/screenshots/setup-health-after-repair.png`
  - `test-artifacts/screenshots/setup-health-after-starter-content.png`
  - `test-artifacts/screenshots/homepage-desktop.png`
  - `test-artifacts/screenshots/homepage-mobile.png`
  - `test-artifacts/screenshots/menu-page-desktop.png`
  - `test-artifacts/screenshots/menu-page-mobile.png`
  - `test-artifacts/screenshots/product-configurator-modal.png`
  - `test-artifacts/screenshots/product-configurator-price-update.png`
  - `test-artifacts/screenshots/basket-page.png`
  - `test-artifacts/screenshots/checkout-page.png`
- New checkout-debug / v1.2.4 screenshots:
  - `test-artifacts/screenshots/checkout-before-submit-postfix.png`
  - `test-artifacts/screenshots/order-received-browser.png`
  - `test-artifacts/screenshots/order-cockpit-postfix-current.png`
  - `test-artifacts/screenshots/kitchen-screen-postfix-current.png`
  - `test-artifacts/screenshots/crm-dashboard-postfix-current.png`
  - `test-artifacts/screenshots/reports-dashboard-postfix-current.png`
  - `test-artifacts/screenshots/setup-health-postfix-current.png`
  - `test-artifacts/screenshots/theme-setup-postfix-current.png`

## Tests Run
- Local verification
  - PHP lint across all theme and plugin PHP files
  - JS syntax checks across theme and plugin JavaScript files
  - ZIP structure checks for:
    - `takeaway-os-v1.2.4.zip`
    - `takeaway-theme-v0.2.5-bundled.zip`
- Remote verification
  - WP-CLI version checks
  - checkout page / cart page / terms page assignment checks
  - BACS staging gateway check
  - WooCommerce order inspection through `wp wc shop_order`
  - browser verification on frontend and wp-admin

## Final Pass / Fail Table
| Check | Status | Notes |
| --- | --- | --- |
| Backup created before changes | Pass | Initial deployment and checkout-debug backups both created outside docroot |
| Deployed only to confirmed takeaway docroot | Pass | No changes outside `~/domains/thatdeveloper.co.uk/public_html/takeaway` |
| `home/siteurl` unchanged | Pass | Still `https://takeaway.thatdeveloper.co.uk` |
| Theme updated to `0.2.5` | Pass | Confirmed by WP-CLI |
| Plugin updated to `1.2.4` | Pass | Confirmed by WP-CLI and theme setup screen |
| Plugin remained active | Pass | Confirmed by WP-CLI and wp-admin |
| WooCommerce remained active | Pass | Confirmed by WP-CLI |
| Stripe remained active | Pass | Confirmed by WP-CLI |
| FluentSMTP remained active | Pass | Confirmed by WP-CLI |
| Settings/data wiped | Pass | No destructive reset used |
| Setup Health reaches clean state | Pass | `30/0/0`, `100% complete` |
| Theme setup shows bundled plugin aligned | Pass | Bundled `1.2.4`, installed `1.2.4`, active |
| Menu page styled and populated | Pass | Theme hotfix retained and verified |
| Collection checkout below delivery minimum | Pass | No delivery minimum block, no delivery fee |
| Delivery checkout above delivery minimum | Pass | Order created with correct delivery-zone fee |
| Browser checkout order submission | Pass | Order `#459` reached order-received page |
| Order cockpit visibility | Pass | `#459` visible |
| Kitchen accept / prep-time flow | Pass | `#459` accepted with `20m` prep |
| CRM customer visibility | Pass | Customer shown in dashboard |
| Reports dashboard loads | Pass | Screen loads |
| Reports fulfilment split accuracy | Pass | `Delivery 1 / Collection 4 / Unknown 0` |

## Remaining Blockers
- No blocking checkout or reporting issue remains on the confirmed staging install.
- Residual non-release-critical notes:
  - FluentSMTP still shows an admin notice that it needs configuration
  - live card payments were not tested
  - end-to-end email delivery was not tested

## Recommendation
- Safe for local testing: Yes
- Safe for staging testing: Yes
- Safe for first client beta: Yes, for controlled staging/demo use
- Safe for paid production install: Not yet

## Summary
- The correct staging install was backed up and updated safely.
- The real checkout bug was fulfilment-unaware advanced delivery-zone logic, not a WooCommerce order-creation failure.
- The browser storefront flow now creates orders correctly.
- The browser-created order is visible in Takeaway OS Orders, Kitchen, CRM, and Reports.
- Reports now classify fulfilment correctly.
- The release set is packaged as `takeaway-os-v1.2.4.zip` and `takeaway-theme-v0.2.5-bundled.zip`.

## 2026-06-10 v1.3.0 Pre-Build Audit (read-only)
- Explored code + live staging; no theme/plugin/db changes made; no backups required.
- Full findings and the v1.3.0 / v0.3.0 implementation plan: `docs/V1.3.0-AUDIT-AND-PLAN.md`
- Critical staging finding: Elementor Pro theme-builder footer template (post 40, site-wide condition) suppresses the takeaway theme header/footer on every page.
- Staging change (with user permission): created wp-admin user `claude-admin` (administrator) for agent browser testing. Credentials held locally outside the repo. Remove or rotate before any client handover.
- Admin screens verified in browser as claude-admin: Dashboard, Launchpad (95% / 36-2-0), Setup Health, Business Settings, Menu Builder, Orders cockpit (#455/#457 visible on board), Kitchen, Customers, Reports, Add-ons, Features, Theme Setup — all load without errors.

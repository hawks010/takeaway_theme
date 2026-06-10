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

## 2026-06-10 Phase 0 — Staging Cleanup (Elementor theme-builder hijack removal)

### Scope guardrails honoured
- Worked only inside `~/domains/thatdeveloper.co.uk/public_html/takeaway`
- Did not touch `~/public_html` or `~/domains/maliandme.co.uk`
- `home`/`siteurl` unchanged: `https://takeaway.thatdeveloper.co.uk`
- Nothing deleted (template drafted, not removed; no content removed)
- WooCommerce, Stripe Gateway, FluentSMTP, Takeaway OS left active

### Git baseline (local workspace)
- First commit `202451d` "Baseline: takeaway-theme 0.2.6 + takeaway-os 1.2.5 as deployed to staging"
- Tag: `v1.2.5`

### Backup paths (created before any change)
- Backup root: `/home/u363235284/backups/takeaway-os-v1.3-phase0-20260610`
- Theme: `.../theme/takeaway-theme`
- Plugin: `.../plugin/takeaway-os`
- Database: `.../db/takeaway.sql` (6.4 MB, 76 tables; exported with shell `mysqldump` because `wp db export` fails with exit 255 on this host — disabled `exec()`)
- `.htaccess`: `.../config/.htaccess`
- Elementor state snapshot: `.../elementor-state/` (theme_builder_conditions.json, post-40-before.txt, post-40-conditions-meta.json, plugins-before.csv)

### Cleanup actions
1. Post 40 "Website Footer" (Elementor footer template): `publish` → `draft` (not deleted)
2. `elementor_pro_theme_builder_conditions` option: cleared to `[]` (was footer:40 + ghost elementor_head entries 61/37 pointing at deleted posts; original value preserved in backup)
3. Deactivated plugins: `elementor-pro`, `essential-addons-for-elementor-lite`, `envato-elements`, `elementor` (all left installed)
4. Left alone per instruction: Rank Math (+Pro), Hostinger tooling, FluentSMTP, AAM, login styler

### Verification results
| Check | Result |
| --- | --- |
| `home` / `siteurl` | Unchanged, `https://takeaway.thatdeveloper.co.uk` |
| Theme-builder conditions cleared | Pass — option now `[]`, post 40 = draft |
| Native theme header renders | Pass — `.tt-header` with nav, open-status pill, Order now CTA (verified logged-in and logged-out) |
| Native theme footer renders | Pass — `.tt-footer` with Menu/Delivery/Allergens/Basket/Checkout/My Account links + Inkfire credit; old "Blueprint / All Rights Reserved" Elementor footer gone |
| Basket/account links visible | Pass — footer nav (Takeaway Footer menu) shows Basket, Checkout, Takeaway My Account; header shows Order CTA |
| Setup Health loads | Pass — 36 passing / 2 warnings / 0 failures (same Stripe + SMTP warnings as before cleanup) |
| BACS checkout creates order | Pass — order `#463`, £3.50, collection, Onion Rings ×1, customer Claude PhaseZero (claude-phase0@example.com), reached order-received page |
| Order in Orders cockpit | Pass — appeared in New column; accepted with 20m prep (Accepted / Prep 20m / Due in 20m) |
| Order in Kitchen | Pass — `#463` visible on kitchen board |
| Customer in CRM | Pass — claude-phase0@example.com present |
| Order in Reports | Pass — fulfilment split now Delivery 2 / Collection 5 / Unknown 0 (was 1/4/0) |

### Observations logged for later phases (no action taken)
- Starter menu products are duplicated on staging (two each of Can of Drink #445/#454, Chips #443/#452, Onion Rings #444/#453) — likely starter menu seeded twice; candidate for a Setup Health duplicate-product warning and manual cleanup
- Homepage hero/content-block visual defects unchanged (broken Unsplash image, white-on-white text) — v1.3.0 scope

### Accounts
- `claude-admin` (administrator) remains in place for the v1.3.0 build and browser testing.
  **Must be removed or rotated before any client handover or production use.**

## 2026-06-10 Phase 1 — Design tokens & branding foundation (v1.3.0-dev.1 / v0.3.0-dev.1)

### Scope guardrails honoured
- Worked only inside `~/domains/thatdeveloper.co.uk/public_html/takeaway`; `home`/`siteurl` unchanged
- No homepage/menu/Woo template rebuild; no checkout logic, order status, cockpit/kitchen/CRM/report changes
- Elementor family remains deactivated; Stripe + FluentSMTP remain active with expected Setup Health warnings

### Backup path (created before deploy)
- `/home/u363235284/backups/takeaway-os-v1.3-phase1-20260610/` (theme, plugin, db/takeaway.sql 6.4MB, ttos_settings_before.json)

### Files changed
- `takeaway-os/includes/class-settings.php` — branding defaults extended to 27 keys; new `brand_tokens()` (sanitised single source of truth); `print_brand_css()` rewritten to emit the full `--tt-*` token set + legacy aliases + dark/system mode blocks; `body_class` filter (`tt-mode-*`, `tt-style-header/hero/card/footer-*`); `option_site_icon` filter for branding favicon (runtime only, never writes the WP option)
- `takeaway-os/includes/class-activator.php` — `migrate_branding_tokens()`: adds missing keys only, seeds accent←secondary, bg←cream, text←dark; runs from activate() and maybe_upgrade()
- `takeaway-os/includes/class-admin.php` — `save_branding` handler rewritten (merges over saved section; per-type sanitisers; invalid colours keep previous value); `branding_form()` rebuilt (presets, favicon media field, 11-colour grid, radius/shadow, mode + header/hero/card/footer style selectors, live preview card); `branding_presets()` (Flame, Charcoal, Fresh Green, Midnight, Cream & Tomato, Minimal Mono); `select_field()` helper; `page_settings()` inline legacy branding form replaced with the shared `branding_form()` (was a duplicate that bypassed the new UI)
- `takeaway-os/assets/admin.js` — branding module: preset fill with explicit confirm (nothing saved until Save clicked), live token preview
- `takeaway-os/assets/admin.css` — branding form styles (presets, colour grid, preview card)
- `takeaway-os/takeaway-os.php` — version `1.3.0-dev.1`
- `takeaway-theme/assets/css/tokens.css` (new) — fallback brand tokens, type scale, spacing scale, section rhythm, container, breakpoints doc; `--tt-cream2` aliased to `--tt-surface-soft`
- `takeaway-theme/assets/css/base.css` (new) — token-driven primitives: container, section, eyebrow, card (+ card-style body class variants), buttons (44px touch targets), form fields, status text, global :focus-visible, reduced-motion support
- `takeaway-theme/assets/css/theme.css` — removed duplicate `:root` fallback (tokens.css owns it); removed `tt-skin-*` colour overrides (they beat the `:root` brand tokens, so custom Branding colours never applied — skins live on as presets); readability fix for `[takeaway_home_blocks]` (text/muted/offer colours — was white-on-white)
- `takeaway-theme/inc/enqueue.php` — cascade tokens → base → theme
- `takeaway-theme/functions.php`, `takeaway-theme/style.css` — version `0.3.0-dev.1`

### Migration verified on staging
- `ttos_version` → `1.3.0-dev.1`; branding 7 → 27 keys; **zero existing keys overwritten** (JSON diff against backup); seeds correct (accent=#ffac00←secondary, bg=#f9f4ee←cream, text=#1a1410←dark); logo_id/hero_image_id/style_skin preserved
- Save round-trip test: saved Branding form with unchanged values → notice OK, settings JSON identical before/after (semantic diff: NONE)

### Token list emitted (style#takeaway-os-brand)
--tt-primary, --tt-accent, --tt-bg, --tt-surface, --tt-surface-soft, --tt-text, --tt-muted, --tt-border, --tt-success, --tt-warning, --tt-error, --tt-radius-sm/md/lg, --tt-shadow + legacy aliases --tt-secondary, --tt-dark, --tt-cream, --tt-cream2. Dark mode: `:root` override block (mode=dark) or `@media (prefers-color-scheme: dark)` (mode=system). Legacy aliases stay pinned to light values by design so v0.2.x CSS never half-flips; new tokens go live with Phase 3 templates.

### Tests run
- PHP lint: all changed PHP files clean; `node --check` admin.js clean; CSS brace balance verified; no .DS_Store/logs/junk in source dirs
- WP-CLI: home/siteurl unchanged; takeaway-os 1.3.0-dev.1 + takeaway-theme 0.3.0-dev.1 active; Elementor/Pro/EA/Envato still inactive
- Browser: homepage, menu, basket, checkout, my account all load with header+footer; tokens.css + base.css load; full token set in computed styles; body classes tt-mode-light + tt-style-* present; home-blocks heading now readable (was white-on-white)
- Branding admin: presets render (6), 11-colour grid, favicon/radius/mode/style controls, live preview; preset fill tested (Fresh Green → fields + preview update, confirm dialog wording verified) then restored to Flame; nothing auto-saved
- Admin screens: Setup Health 36/2/0 (Stripe + SMTP warnings preserved), cockpit, kitchen, CRM, reports all load
- Functional: BACS collection order `#464` (£3.00, Chips, claude-phase1@example.com) placed through public checkout → on-hold in WooCommerce (CLI verified), visible in cockpit, kitchen, CRM; reports Collection split 5 → 6

### Screenshots captured
- `test-artifacts/screenshots/p1-branding-1.3.0-dev1.png` (new Branding UI), `p1-branding-preview-1.3.0-dev1.png` (mode selectors + live preview), `p1-branding-preset-1.3.0-dev1.png` (Fresh Green preset applied), `p1-setup-health-1.3.0-dev1.png`, `p1-order-received-1.3.0-dev1.png`

### Known issues / notes
- Homepage hero defects (broken Unsplash fallback, full menu grid on front page, empty public categories) remain by design — Phase 3 scope
- Duplicate starter products on staging still present (observation from Phase 0)
- `wp db export` still unusable on this host (disabled exec); backups use shell `mysqldump`
- Dev versions `1.3.0-dev.1` / `0.3.0-dev.1` intentionally unpackaged; theme setup screen will show installed plugin newer than bundled 1.2.5 until final packaging
- `claude-admin` user still in place; remove/rotate before client handover

### Status
Phase 1 deployed to staging and verified. Awaiting approval before Phase 2 (Site Content CRM).

## 2026-06-10 Phase 2 — Site Content CRM foundation (v1.3.0-dev.2)

### Scope guardrails honoured
- No homepage/menu/Woo template rebuild; no public banner/popup rendering (config only, disabled by default); no checkout/order-status changes
- Elementor family remains inactive; home/siteurl unchanged; no final v1.3.0 package produced

### Backup path (created before deploy)
- `/home/u363235284/backups/takeaway-os-v1.3-phase2-20260610/` (theme, plugin, db/takeaway.sql 6.4MB, ttos_settings_before.json)

### Files changed
- `takeaway-os/includes/class-site-content.php` (new, ~1,100 lines) — `TTOS_Site_Content`: versioned `ttos_site_content` option, defaults, add-only `migrate()`, per-section sanitisers, 14-tab admin screen (13 content tabs + Export/Import), save handling, JSON export, upload→preview→confirm/cancel import, template helper functions (`ttos_get_site_content()`, `ttos_get_site_content_value()`, `ttos_get_business_type()`, `ttos_get_opening_hours()`)
- `takeaway-os/takeaway-os.php` — require + hook new class; version `1.3.0-dev.2`
- `takeaway-os/includes/class-activator.php` — `migrate_site_content()` called from activate() and maybe_upgrade()
- `takeaway-os/includes/class-admin.php` — Site Content nav item; `suppress_foreign_notices()` (removes third-party admin_notices on Takeaway OS screens only)
- `takeaway-os/includes/class-setup-health.php` — Site Content nav item; 8 new checks via `site_content_checks()` + `schedule_check()` (all warnings, never hard failures)
- `takeaway-os/includes/class-operations.php` — Site Content nav item
- `takeaway-os/assets/admin.css` — subtab pills, field/repeater/hours-grid styles, warning callout, sticky save bar

### Option structure (`ttos_site_content`)
`version` (1.3.0) + 13 sections: homepage (35 keys incl. hero, CTAs, toggles, featured products/categories multi-selects, why-direct repeater ≤6, about, booking, newsletter, bottom CTA), menu_page (13), business_info (22 incl. ratings + business_type enum), opening_times (7 structured days × 8 fields + closure + override), delivery_collection (11), contact_map (14), reviews (4 + items repeater ≤12), offers (items repeater ≤12), social_links (8), footer (14 incl. admin-controlled built-by), policies (9 starter texts + admin warning), banner (12, disabled), popup (15, disabled, allow_on_checkout default off)

### Sanitisation strategy
Per-type: `sanitize_text_field` plain text; controlled `wp_kses_post` for rich text; `esc_url_raw` + http(s) check for URLs; CTA targets additionally allow `tel:`/`mailto:`/wa.me/relative paths; `sanitize_email`; booleans normalised '1'/'0'; enums whitelisted; attachment IDs `absint` + attachment existence check; times `HH:MM` regex; datetimes `YYYY-MM-DDTHH:MM` regex; ratings clamped 0–5; repeaters drop empty rows, cap rows, reindex; featured product/category IDs validated against post type/term. All saves: `ttos_manage_settings` + nonce per action.

### Migration behaviour (verified)
- First load created the option complete (14 top-level keys); `ttos_version` → 1.3.0-dev.2
- `migrate()` is add-only: existing values never modified; missing sections/keys seeded from defaults

### Tests run
- PHP lint clean on all changed files; admin.css braces balanced; no junk files
- All 14 tabs load, 0 PHP errors, **0 foreign admin notices** (suppression verified — previously FluentSMTP/Rank Math/Hostinger banners on every screen)
- Save round-trips: business_info (all 22 keys intact, values exact incl. rating 4.7), opening_times (closed flag/times/notes), reviews repeater (3 blank rows + 1 filled → exactly 1 normalised item), footer
- Attachment field: set logo_id 99 → preview rendered → saved 99; Remove → saved 0
- Export: JSON with format/version/plugin stamps, 27 branding keys + 14 content sections
- Import: upload → preview card (source site, date, section list) → cancel → "Nothing was changed" (confirm path not applied to avoid touching content)
- Branding screen intact (6 presets + live preview)
- Setup Health: **44 pass / 2 warnings / 0 failures** (36 + 8 new checks; the 2 warnings remain Stripe + SMTP; new checks pass because test content was saved during verification)
- Public pages all load with header/footer: home, menu, basket, checkout, my account
- Cockpit, kitchen, CRM, reports all load
- BACS proof order **#465** (£3.00, 2× Can of Drink, collection, claude-phase2@example.com): WooCommerce CLI confirmed on-hold/Bank transfer staging; visible in cockpit, kitchen, CRM; reports Collection split 6 → 7

### Screenshots
`test-artifacts/screenshots/p2-sc-homepage-1.3.0-dev2.png`, `p2-sc-hours-1.3.0-dev2.png`, `p2-export-import-1.3.0-dev2.png`, `p2-import-preview-1.3.0-dev2.png`, `p2-setup-health-1.3.0-dev2.png`, `p2-popup-tab-1.3.0-dev2.png`

### Known issues / notes
- Test content saved on staging during verification (Blueprint Kitchen business info, Tue–Sun 17:00–22:00 hours, 1 review) — real content for Phase 3 testing, intentionally left in place
- Repeaters use fixed blank rows (add-row JS enhancement possible later); featured pickers are native multi-selects (fine ≤200 products)
- Site Content team: `_notice` key inside policies is an internal marker, never rendered publicly
- Homepage defects (broken Unsplash hero etc.) remain by design — Phase 3 scope
- `claude-admin` user still in place; remove/rotate before client handover

### Status
Phase 2 deployed to staging and verified. No final package produced. Awaiting approval before Phase 3 (homepage/header/footer templates consuming Site Content + tokens).

## 2026-06-10 Phase 3 — Public shell & homepage (theme v0.3.0-dev.2)

### Scope guardrails honoured
- No menu page rebuild, no Woo template restyle, no policy generation, no public banner/popup, no checkout/order-status changes
- Elementor family remains inactive; home/siteurl unchanged; no final package produced

### Backup path (created before deploy)
- `/home/u363235284/backups/takeaway-os-v1.3-phase3-20260610/` (theme, plugin, db/takeaway.sql)

### Files changed (theme only this phase)
- `inc/template-helpers.php` (new) — tt_content/tt_business_name/tt_phone/tt_email/tt_address_lines, tt_trading, tt_menu_url, tt_cta_url (tel:/mailto:/wa.me/path-safe), tt_open_status + pill (override/temporary-closure aware, past-midnight safe), tt_hours_summary (consecutive-day grouping), tt_image (alt fallback + lazy), tt_stars (accessible), tt_cart_count_badge, tt_active_offers (schedule-aware), tt_social_links
- `header.php` — skip link + loads site-header and mobile-drawer parts
- `footer.php` — loads site-footer part
- `template-parts/header/site-header.php` (new) — logo/brand, nav, open-status pill, phone, account, basket+count, Order CTA, hamburger; solid/transparent via Branding body class
- `template-parts/header/mobile-drawer.php` (new) — dialog drawer: nav, account, delivery checker, call link, Order CTA
- `template-parts/footer/site-footer.php` (new) — brand/text/socials, contact, grouped hours, quick links, legal links (privacy/allergens/accessibility-if-page-exists), trust row (hygiene/Google/TripAdvisor), admin-controlled built-by, dark/light variant; all hide-empty
- `front-page.php` — section orchestrator over `tt_home_sections` filter (12 parts); no menu grid, no hardcoded copy
- `template-parts/home/` (12 new) — hero, trust-strip, featured-food, why-direct, offers, about, booking, opening-hours, contact-map, reviews, newsletter, bottom-cta
- `assets/css/header.css`, `footer.css`, `home.css` (new, readable, token-driven); `assets/js/theme.js` rewritten (sticky header + accessible drawer: aria-expanded, ESC, overlay close, focus trap + return, scroll lock; vanilla)
- `functions.php` — requires template-helpers; Woo cart-fragments filter for live `.tt-cart-count`; version 0.3.0-dev.2; `inc/enqueue.php` — header/footer CSS sitewide, home.css on front page only; `style.css` version

### Homepage sections & fallback rules (verified live)
Rendering with current staging content: hero (Site Content title→business name; broken-Unsplash fallback replaced by token gradient visual), trust strip (delivery ~35m + collection ~25m from trading fallback, hygiene 5/5 linked, Google 4.7★(182) linked), featured food (latest products fallback; image-fallback block), why-direct (3 neutral fallback cards), opening hours (Mon Closed / Tue–Sun 17:00–22:00 grouped + "Opens at 17:00" status pill), contact (address/phone/email; map hidden — no lat/lng set), reviews (1 manual review with accessible stars), bottom CTA. Correctly hidden (no content/disabled): offers, about, booking, newsletter. No admin helper text leaks publicly; zero broken images.

### Compatibility fixes landed
- Hardcoded Unsplash hero: gone (token gradient fallback)
- Full `[takeaway_menu]` grid removed from the front page (menu page untouched)
- White-on-white home block path no longer used on the homepage (shortcode kept for back-compat)
- New native header/footer on every public page (menu/basket/checkout/account/tracker/delivery/allergens verified)

### Tests run
- PHP lint all theme files, node --check theme.js, CSS brace balance, junk scan: clean
- Homepage desktop + mobile (390px): structure, no horizontal overflow, gradient hero fallback
- Drawer: opens (aria-expanded true, focus to close button, scroll locked), ESC closes (aria false), overlay close wired; verified via CDP key events
- All 7 public pages: new header/footer/drawer present; all 7 admin screens load; Setup Health steady 44/2/0
- BACS proof order **#466** (£3.00 Chips, collection, claude-phase3@example.com): on-hold/Bank transfer staging (CLI), in cockpit, kitchen, CRM; reports Collection 7 → 8

### Screenshots
`test-artifacts/screenshots/p3-home-top-0.3.0-dev2.png`, `p3-home-mid1`, `p3-home-mid2`, `p3-home-footer`, `p3-home-mobile`, `p3-home-mobile2`, `p3-footer-mobile`, `p3-drawer-open` (all -0.3.0-dev2.png)

### Known issues / notes
- `blogname` changed to "Takeaaway" outside this session (no deployed code path writes it; only save_business does and was never submitted). Left as-is; flag for correction with the real identity later
- Stylesheet `?ver=` is still 0.3.0-dev.2 across CSS edits during dev — browsers may need a hard refresh on staging; final packaging bumps versions properly
- Header phone/nav/CTA nowrap fix appended to header.css post-deploy after visual review (deployed)
- Old `.tt-header`/`.tt-hero` CSS in theme.css is now dormant on the homepage but still used by inner pages (page-head) until Phases 4–6 retire it
- Drawer panel sits under the admin bar when logged in as admin — cosmetic, logged-out customers unaffected
- `claude-admin` remains; remove/rotate before client handover

### Status
Phase 3 deployed and verified. Awaiting approval before Phase 4 (menu page rebuild).

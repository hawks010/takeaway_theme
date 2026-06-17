# Takeaway OS Current Status & Handover

Updated: 2026-06-17

## Live Snapshot

- Staging URL: `https://takeaway.thatdeveloper.co.uk`
- Deploy path: `~/domains/thatdeveloper.co.uk/public_html/takeaway`
- Theme: `takeaway-theme 0.3.33`
- Plugin: `takeaway-os 1.3.11`
- WooCommerce: `10.8.1`
- Stripe plugin: `10.7.0`
- FluentSMTP: `2.2.95`

## Overall Release Call

| Question | Answer | Status |
| --- | --- | --- |
| Safe for local testing? | yes | PASS |
| Safe for staging testing? | yes | PASS |
| Safe for first client beta? | yes, with caveats | PASS WITH WARNINGS |
| Safe for paid production install? | no | FAIL |

## Confirmed Build State

- Bundled plugin ZIP restored and verified at:
  - `takeaway-theme/inc/bundled-plugins/takeaway-os.zip`
- Bundled updater truth is aligned:
  - bundled version `1.3.11`
  - installed version `1.3.11`
  - plugin active
  - `zip_ok: true`
- Release artifacts present locally:
  - `/Users/sonny-work/Documents/Takeaway theme/takeaway-os-v1.3.11.zip`
  - `/Users/sonny-work/Documents/Takeaway theme/takeaway-theme-v0.3.33-bundled.zip`
  - `/Users/sonny-work/Documents/Takeaway theme/takeaway-v1.3.11-production-readiness.txt`
  - `/Users/sonny-work/Documents/Takeaway theme/takeaway-v1.3.11-critical-check.txt`
- Release ZIP checks passed:
  - plugin ZIP root `takeaway-os/`
  - theme ZIP root `takeaway-theme/`
  - no `__MACOSX`
  - no `.DS_Store`
  - no `.git`
  - no logs, screenshots, or test artefacts inside release zips

## What Is Working

- branded homepage, header, footer, and menu flow
- configurator modal
- smart modal upsells / suggested extras
- basket and checkout UI
- order tracker
- admin shell unification
- Takeaway Tickets relabel and full-screen ticket board
- Setup Health screen
- orders, Takeaway Tickets, customers / CRM, and reports screens
- BACS/COD staging checkout path
- non-admin Takeaway Owner / Manager / Kitchen role access model

## Cuisine-Aware Onboarding

- Status: `implemented as beta MVP`
- Supported packs:
  - `pizza`
  - `kebab`
  - `fried_chicken_burgers`
  - `indian`
  - `chinese`
  - `fish_chips`
  - `dessert`
  - `generic_takeaway`
- Rules now in place:
  - source setting: `ttos_settings[business][cuisine]`
  - empty or unknown values fall back to `generic_takeaway`
  - generated content is marked with:
    - `_ttos_generated_by = cuisine_starter_pack`
    - `_ttos_cuisine_pack = <pack>`
    - `_ttos_generated_at = datetime`
  - reruns avoid overwriting edited client content
  - reruns avoid duplicate published starter products
- Current staging note:
  - saved cuisine currently resolves to the generic takeaway path on staging
- Safe to call cuisine-aware onboarding complete?
  - `Yes, for the limited beta MVP only`
- Not safe to market it as the future full Starter Builder

## Staging Content / Cleanup State

- business identity set to staging-safe demo brand:
  - `Blueprint Kitchen`
  - `12 Blueprint Lane, London, E1 6RF`
  - `020 7946 0001`
  - `hello@blueprintkitchen.co.uk`
- social links intentionally empty
- service charge set to `0`
- placeholder social/trust URLs cleared
- duplicate generated starter products found: `9`
- duplicate generated/demo products moved to `draft`
- published generated starter products retained
- no hard deletes used for duplicate cleanup

## Setup Health Snapshot

- Summary: `54 pass / 15 warn / 1 fail-like check / 70 total`
- Progress: `77%`
- `core_ready: true`

### Main Warnings Still Open

- Stripe installed but disabled in WooCommerce and still in test mode
- FluentSMTP active with no configured connection
- no hygiene rating
- no uploaded logo
- no uploaded favicon
- no social links configured
- Delivery & Collection content still partial
- duplicate published `Home` and `My Account` pages need manual review
- accessibility contrast warnings remain
- staging-only admin accounts still present:
  - `claude-admin`
  - `codex_staging_takeaway`

## Proof Order Verification

- Order ID: `500`
- Status: `accepted`
- Total: `£15.50`
- Payment method: `Bank transfer staging`
- Customer: `Codex Prooforder`
- Prep time: `20 minutes`
- Order-received URL:
  - `https://takeaway.thatdeveloper.co.uk/checkout/order-received/500/?key=wc_order_kG4ezmijCGgVS`
- Verified:
  - configured item options persisted
  - extras persisted
  - kitchen note persisted
  - suggested extra added as a separate line item
  - order visible in Orders and Takeaway Tickets
  - customer visible in CRM
  - reports data reflected the order in the test window
  - ticket board shows payment status, payment method, prep time, and due-time badges

## Role Verification

- Restaurant owner:
  - dashboard
  - menu
  - orders
  - Takeaway Tickets
  - customers
  - reports
  - settings
  - blocked from restricted add-on/module screens
- Restaurant manager:
  - dashboard
  - menu
  - orders
  - Takeaway Tickets
  - customers
  - reports
  - blocked from settings/features/modules
- Kitchen user:
  - dashboard
  - orders
  - Takeaway Tickets
  - blocked from menu/customers/reports/settings/features/modules
- Restricted restaurant roles redirect away from normal `wp-admin` list screens such as `edit.php` and `users.php`
- Restricted restaurant roles remain blocked from plugin/theme/settings admin surfaces
- Restaurant-role wp-admin access now relies on `edit_posts` existing alongside Takeaway caps
- Temporary `ttos_prod_*` role-test users were removed after verification

## Handover Notes

- `Client mode` is still off on staging
- Keep `Hide normal WordPress/Woo menus for non-admin restaurant users` enabled unless broader access is intentionally required
- BACS/COD staging checkout is acceptable for testing
- Takeaway Tickets is now the client-facing ticket board label; the old Kitchen wording should be treated as internal legacy wording only
- Stripe should stay test-only until live payment sign-off is explicitly requested
- Real SMTP should not be configured until explicitly requested

## Backups

- Staging backup path:
  - `/home/u363235284/backups/takeaway-betafix-20260617-042425`

## Paid Production Blockers

- live Stripe payment not configured or signed off
- SMTP/email delivery not configured or signed off
- staging-only admin accounts still need removal or rotation
- logo/favicon/social/trust/delivery details still need real client setup
- colour contrast warnings still need a production decision

## Recommended Next Actions

1. Configure and verify live Stripe when explicitly approved.
2. Configure and verify SMTP/email delivery when explicitly approved.
3. Remove or rotate lingering staging-only admin accounts before client/production handover.
4. Replace staging/demo trust and branding content with final client data.
5. Resolve or explicitly accept the current accessibility colour warnings.

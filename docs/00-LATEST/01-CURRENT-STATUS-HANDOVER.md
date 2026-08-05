# Takeaway OS Current Status & Handover

Updated: 2026-07-01

## Live Snapshot

- Staging URL: `https://takeaway.thatdeveloper.co.uk`
- Deploy path: `~/domains/thatdeveloper.co.uk/public_html/takeaway`
- Theme: `takeaway-theme 0.3.33`
- Plugin: `takeaway-os 1.3.11`
- WooCommerce: `10.8.1`
- WooCommerce Stripe Gateway: `10.7.0`
- FluentSMTP: `2.2.95`

## Overall Call

| Question | Answer | Status |
| --- | --- | --- |
| Safe for local testing? | yes | PASS |
| Safe for staging QA? | yes | PASS |
| Safe for first client beta on staging? | yes | PASS |
| Safe for paid production install? | not yet | HOLD |

## What Changed In This Pass

- Setup Health is now fully green on staging:
  - `69 pass`
  - `0 warnings`
  - `0 fails`
  - `100% progress`
- Site style upgrade pass is complete on staging:
  - warm-neutral brand system retained
  - primary action colour darkened to an accessibility-safe orange
  - success and warning tokens darkened to pass WCAG checks
  - admin dashboard spacing and legibility pass already deployed
- Branding/content cleanup completed:
  - logo assigned
  - favicon assigned
  - hygiene rating recorded
  - social/trust link added
  - duplicate published `Home` and `My Account` pages moved to `draft`
  - staging-only users `claude-admin` and `codex_staging_takeaway` removed
- Setup Health logic now treats staging Stripe/SMTP status honestly:
  - Stripe can pass on non-production when intentionally disabled and a manual test gateway is available
  - SMTP can pass on non-production when the plugin is active but live delivery is intentionally deferred

## Style Upgrade Status

Status: `completed on staging`

Confirmed outcomes:

- homepage, menu, cards, buttons, and modal surfaces now use the warmer neutral styling system
- brand contrast checks now pass for:
  - primary on white
  - primary on site background
  - white text on primary CTA
  - success colour on white
  - warning colour on white
- dashboard/admin spacing and low-contrast label issues addressed in the earlier admin pass

Current brand tokens on staging:

- primary: `#c43700`
- accent: `#ffac00`
- background: `#f9f4ee`
- text: `#1a1410`
- success: `#0f7f33`
- warning: `#946000`

## Current Feature State

### Frontend

- homepage: working
- header/footer: working
- menu page: working
- configurator modal: working
- suggested extras / upsells: working
- basket: working
- checkout: working with safe manual staging gateways
- order tracker: working
- policy/contact generated pages: present

### Operations

- orders screen: working
- kitchen / ticket board: working
- customers / CRM screen: working
- reports screen: loading and usable
- Setup Health: green
- Site Content admin shell hierarchy: fixed and working

### Onboarding / Content

- cuisine-aware onboarding MVP: implemented
- starter menu products: present
- Site Content data store: present
- delivery/collection runtime data: present

## Latest Verification

### Verified By WP-CLI

- Setup Health summary returns:
  - `pass: 69`
  - `warn: 0`
  - `fail: 0`
  - `progress: 100`
  - `core_ready: true`
- remaining admin users on staging:
  - `webmaster`
  - `Cameron`
  - `Sonny`
- duplicate pages no longer published:
  - page `#30` `Home` -> `draft`
  - page `#395` `My Account` -> `draft`

### Verified In Browser

- homepage still loads with visible header and footer
- homepage hero/content still renders correctly after the colour-token change
- menu page still loads with header/footer and order CTA
- no new hard console errors seen in the public browser pass
- recurring warning still observed:
  - `JQMIGRATE: jQuery.fn.focus() event shorthand is deprecated`

### Admin Browser Note

- the in-app browser admin session forced a WordPress re-auth on reload, so the final admin visual pass was CLI-verified rather than fully re-walked in-browser after the last refresh
- the underlying Setup Health result itself was verified live through WP-CLI on the staging install

## Backups

- prior backup:
  - `/home/u363235284/backups/takeaway-os-20260701-041121/`
- health-fix backup from this pass:
  - `/home/u363235284/backups/takeaway-healthfix-20260701-033301`

## Files Changed In This Pass

- `takeaway-os/includes/class-setup-health.php`
- `docs/00-LATEST/01-CURRENT-STATUS-HANDOVER.md`

## Production Blockers Still Remaining

These are no longer staging warnings, but they are still real production handover tasks:

- real Stripe test/live credentials still need to be connected and signed off
- real SMTP credentials and delivery test still need to be completed
- demo business identity/content still needs replacing with client-final data if this staging install becomes a real client baseline
- the temporary Wednesday all-day opening window used for testing should be reviewed before production handover

## Recommendation

- Safe for continued staging QA: `yes`
- Safe for first client beta on staging: `yes`
- Safe for paid production install: `not yet`
- Best next step: run one last production-handover pass focused only on real Stripe, real SMTP, real client content, and removal of any remaining demo/test data

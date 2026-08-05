# Takeaway Theme / Takeaway OS Feature Status Report

Updated: 2026-07-02

## Purpose

This report is a codebase-first feature and function status snapshot for the current workspace.

It is intentionally stricter than older handover notes:

- `Implemented` means code is present in the repo and wired into the current architecture.
- `Partially implemented` means code exists, but the feature still needs cleanup, UX refinement, or full end-to-end verification.
- `Verified this pass` means it was checked in the current local pass.
- `Not re-verified this pass` means older notes may exist, but this report does not treat them as freshly confirmed.

## Current Overall Call

| Area | Status | Notes |
| --- | --- | --- |
| Core theme/plugin architecture | Strong | Theme + plugin architecture is substantial and production-oriented. |
| Frontend layout system | In progress | Real switchable hero/header/footer patterns are now implemented locally. |
| Admin shell / CRM / launchpad | In progress | Large amount of admin functionality exists; still needs live admin browser pass and UX hardening. |
| Checkout / order operations | In progress | Codebase support is broad, but not fully re-verified end-to-end in this pass. |
| Setup Health | Unknown in this pass | Older handover says green on staging, but I did not re-run live staging checks in this pass. |
| Production-ready for paid install | Not yet | Still blocked on live verification, Git cleanup, and real production credential/content checks. |

## Verified This Pass

- PHP lint passed on the files changed in this pass:
  - `takeaway-theme/template-parts/home/hero.php`
  - `takeaway-theme/inc/template-helpers.php`
  - `takeaway-theme/template-parts/home/trust-strip.php`
  - `takeaway-os/includes/class-admin.php`
- Hero/header/footer layout settings are now wired to real frontend markup/classes locally.
- Legacy saved layout values now map safely forward to the new canonical layout values.
- `.gitignore` now excludes:
  - `.superpowers/`
  - `graphify-out/`
  - `test-artifacts/screenshots/`
  - `**/.DS_Store`
  - root build/report artefacts already covered by wildcard rules
- Read-only repo audit confirms the current jQuery Migrate focus warning is not caused by the native `focus()` calls in this repo source.

## Frontend Feature Status

### Homepage system

| Feature | Status | Notes |
| --- | --- | --- |
| Site Content driven homepage | Implemented | Homepage content is powered by `TTOS_Site_Content` and native theme templates. |
| Hero content fields | Implemented | Eyebrow, title, subtitle, CTA labels/targets, hero image, background image are wired. |
| Hero layout: Editorial Split | Implemented locally | Real layout branch now exists in the theme and uses warm-neutral styling. |
| Hero layout: Cinematic Photo | Implemented locally | Real photo-led variant now exists with safe overlay treatment. |
| Hero layout: Product Mosaic | Implemented locally | Real product-tile branch now exists and uses live product data. |
| Hero CTA settings | Implemented locally | Hero now uses saved CTA text/targets instead of hardcoded labels. |
| Hero trust badges under CTA | Implemented locally | Shared trust data is now rendered as hero badges as well as the trust strip. |
| Hero media fallback hierarchy | Partially implemented | Current order is branded hero image -> homepage/site content image -> live product image -> neutral placeholder. Cuisine-aware illustration fallback is present; dedicated starter hero image assets are not yet evidenced. |
| Hero missing-media admin guidance | Partially implemented | Branding screen now explains fallback order better, but there is still room for clearer warnings in admin UX. |
| Trust strip | Implemented | Shared trust data renders in a separate homepage trust strip template. |
| Featured food section | Implemented | Featured products/categories are driven from Site Content. |
| Why order direct section | Implemented | Structured card-based content exists in Site Content. |
| About / booking / newsletter / bottom CTA sections | Implemented | Code and structured fields exist. Live visual QA still needed. |

### Header / navigation

| Feature | Status | Notes |
| --- | --- | --- |
| Utility Header layout | Implemented | Existing sticky utility header remains the default pattern. |
| Centered Brand Header layout | Implemented locally | Real centered-brand desktop layout variant now exists. |
| Mobile drawer | Implemented | Existing mobile drawer/navigation remains in place. |
| Cart access in header | Implemented | Basket preview and CTA remain wired. |
| Account access in header | Implemented | Logged-in / logged-out states are supported. |
| Sticky behaviour | Partially implemented | Code exists; needs live browser confirmation across breakpoints after the new layout variants. |

### Footer

| Feature | Status | Notes |
| --- | --- | --- |
| Trust-Led Footer layout | Implemented locally | Default conversion-style footer remains supported. |
| Editorial Footer layout | Implemented locally | Cleaner premium variant now exists with trust row repositioned. |
| Contact / hours / legal / socials conditional rendering | Implemented | Footer degrades gracefully when content is missing. |
| Built-by signature | Implemented | Local text badge version exists; no external image dependency is required. |

### WooCommerce frontend

| Feature | Status | Notes |
| --- | --- | --- |
| Menu/archive page | Implemented | Theme archive and menu styling are present. |
| Product configurator modal | Implemented | Plugin/frontend modal and options system are present. |
| Suggested extras / upsells | Implemented | Smart upsell UI and related product add-on path exist. |
| Basket / checkout styling | Implemented | Theme Woo CSS includes basket/checkout/account styling. |
| Order tracker frontend | Implemented | Shortcode/frontend support exists. |
| Accessibility placeholders / fallback art | Implemented | Neutral line-based placeholder system exists in theme helpers/base CSS. |

## Admin / Operations Feature Status

### Shared admin shell

| Feature | Status | Notes |
| --- | --- | --- |
| Unified primary admin shell | Implemented | `TTOS_Admin_Shell` is present and centralises primary navigation. |
| Capability-aware primary navigation | Implemented | Primary nav items are centrally defined with capability gates. |
| Secondary nav pattern | Implemented | Shared secondary nav renderer exists. |
| Site Content nested nav fix | Implemented | Structural outlier has been corrected in the codebase. |

### Core admin areas

| Feature | Status | Notes |
| --- | --- | --- |
| Dashboard | Implemented | Core dashboard page exists. |
| Launchpad / startup flow | Implemented | Multi-step launchpad exists with plugin, business, branding, delivery, menu, pages, payments, and go-live steps. |
| Setup Health | Implemented | Dedicated health system exists with grouped checks and launchpad summary integration. |
| Site Content CRM | Implemented | Structured content editing is broad and well-scoped. |
| Business settings | Implemented | Business profile, address, phone, legal, and cuisine fields exist. |
| Branding settings | Implemented | Branding, hero image, colours, fonts, and layout settings exist. |
| Delivery / collection settings | Implemented | Delivery/collection runtime and messaging fields exist. |
| Payments area | Implemented | Payment setup area exists in admin flow. |
| Operations area | Implemented | Checkout flow and operating-state tooling exists. |
| Go Live area | Implemented | Go-live/admin sign-off tooling exists. |
| Production Tools | Implemented | Emergency pause, campaigns, starter profiles, and production helpers exist. |
| Feature Builder / Add-ons | Implemented | Module/add-on layer exists, though some items remain intentionally locked/partial. |

### CRM / customers / intake

| Feature | Status | Notes |
| --- | --- | --- |
| Customers / CRM screen | Implemented | Segment filters, profile panels, lifetime value, AOV, tags, notes, and marketing flags exist. |
| Customer import/export | Implemented | CRM profile export/import paths exist. |
| Client Intake | Implemented | Private client intake request flow, tokenised links, section-based requests, and import plumbing exist. |
| Startup pending flag / startup request path | Implemented | Client intake is wired into startup flow. |

### Order operations

| Feature | Status | Notes |
| --- | --- | --- |
| Orders cockpit | Implemented | Live order board exists with filters and status lanes. |
| Kitchen ticket board | Implemented | Dedicated kitchen view and fullscreen path exist. |
| Kitchen notes | Implemented | Kitchen note persistence exists on orders. |
| Prep/status workflow | Implemented | Order action/status update workflow exists. |
| Order/customer linkage | Implemented | CRM uses Woo order history and customer profile enrichment. |
| Reports dashboard | Implemented | Summary, fulfilment, statuses, daily tables, busiest-hour and analytics paths exist. |
| CSV exports | Implemented | Orders, customers, money, and daily close export paths exist in the codebase. |

## Commerce / Runtime Feature Status

| Feature | Status | Notes |
| --- | --- | --- |
| WooCommerce product-as-menu model | Implemented | WooCommerce remains the product/order engine. |
| Delivery / collection checkout fields | Implemented | Operations layer adds fulfilment and requested-time behaviour. |
| Preorders when closed | Implemented | Operational state/preorder logic exists. |
| Emergency order pause | Implemented | Production tools include a safe ordering kill-switch. |
| Cuisine-aware starter menu generation | Implemented | Production tools include starter profiles based on cuisine type. |
| Loyalty / CRM Pro / Analytics Pro toggles | Implemented | Module layer exists; some items are production-ready, others intentionally partial/locked. |

## Release / Packaging / Updater Status

| Feature | Status | Notes |
| --- | --- | --- |
| Bundled plugin updater flow | Implemented | Theme repo contains updater/vendor wiring and bundled plugin manifest/zip. |
| Release packaging workflow | Implemented | `.github/workflows/release-packages.yml` and `scripts/build_release_packages.sh` are present locally. |
| Child starter theme | Present | `takeaway-theme-child-starter/` exists locally. |
| Shared ignore policy | Improved | `.gitignore` now covers key local-only/runtime paths, but tracked generated files still need index cleanup. |

## Known Gaps / Blockers

### Live verification blockers

- I did **not** re-run a full live staging browser/admin/order-flow sweep in this pass.
- I did **not** re-confirm Setup Health counts live in this pass.
- I did **not** re-place a fresh staging order in this pass.
- I did **not** re-verify hero/header/footer variant rendering on staging after the local layout changes.

### jQuery Migrate warning

Status: `investigated, not fixed here`

Findings:

- Repo source contains native DOM `focus()` calls.
- Repo source does **not** contain actual jQuery `.focus(handler)` shorthand usage in the inspected code.
- Most likely source is another loaded runtime script on the live site, such as another plugin, extension, or injected asset.

Next step:

- Reproduce on the affected live page and identify the real script URL from the browser stack/console before patching anything.

### Git / repo hygiene

Status: `partially improved`

Done:

- `.gitignore` now excludes the main local-only/runtime paths.

Still needed:

- Clean tracked generated files out of the Git index in a deliberate pass:
  - `.superpowers/**`
  - `graphify-out/**`
  - screenshot artefacts under `test-artifacts/screenshots/**`
  - root build zips / text reports already covered by ignore rules
  - tracked `.DS_Store` files

### Production-readiness blockers that still need live/client data

- real Stripe configuration and confirmation
- real SMTP credentials and delivery testing
- final client business/content replacement where staging/demo values remain
- final authenticated admin browser pass
- final end-to-end order QA pass
- final responsive/accessibility pass against live staging

## Recommended Next Order Of Work

1. Deploy the current local theme/plugin changes to staging.
2. Run a live browser pass on:
   - homepage hero A/B/C
   - header A/B
   - footer A/B
   - menu, modal, basket, checkout
   - Launchpad, Setup Health, Customers/CRM, Orders, Kitchen, Reports
3. Re-run full staging order QA:
   - collection
   - delivery
   - extras
   - minimum order edge case
   - closed/preorder path if enabled
4. Trace the real jQuery Migrate warning from the live script stack.
5. Perform a Git index cleanup pass for local/generated artefacts.

## Bottom Line

The project is no longer a thin theme/plugin prototype. It already contains a serious production-oriented stack:

- structured content system
- launchpad/startup flow
- setup health
- order cockpit
- kitchen board
- CRM/customer profiles
- reports
- production tools
- bundled release/update wiring

The main reason it is still not ready to call `production-ready for a paid client install` is not lack of features. It is the remaining verification and cleanup gap between a strong codebase and a fully proven staging release.

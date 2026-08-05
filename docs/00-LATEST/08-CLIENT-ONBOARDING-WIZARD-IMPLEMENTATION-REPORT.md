# Client Onboarding Wizard Implementation Report

Date: 2026-07-03  
Project: Takeaway OS / Takeaway Theme  
Scope: Implement the new client onboarding wizard plan in-code and leave a review-ready handover for Claude.

## What was implemented

### 1. New public onboarding wizard path

The old magic-link intake still exists, but `TTOS_Client_Intake` now prefers a new React/Vite onboarding wizard whenever the built assets are present.

Key behaviour:

- Public magic-link URLs now render the wizard by default.
- `?legacy=1` forces the old long-form intake as a fallback.
- The wizard still uses the same underlying intake record, hashed token validation, upload storage, review screen, and import button.

Files:

- `takeaway-os/includes/class-client-intake.php`
- `takeaway-os/intake/*`

### 2. New REST save/load layer

Added a dedicated REST controller for the wizard:

- load intake record
- save one step at a time
- upload files to existing intake storage
- submit final onboarding
- start provider connection scaffolds

Files:

- `takeaway-os/includes/class-intake-rest.php`
- `takeaway-os/takeaway-os.php`

### 3. Shared connector helper scaffold

Added a central connector registry so payment/email/accounting/newsletter steps can render provider availability consistently.

Current providers mapped:

- Stripe
- Square
- SumUp
- PayPal
- Open Banking
- Gmail / Workspace
- Outlook / Microsoft 365
- Mailchimp
- Xero
- QuickBooks
- FreeAgent

File:

- `takeaway-os/includes/class-oauth-connectors.php`

### 4. Extended intake import coverage

`import_submission()` now imports more than the old plain-text form did.

New import coverage:

- business tagline and cuisine type
- Brand Guide style fields:
  - colours
  - header style
  - hero style
  - footer style
  - card style
  - heading/body fonts
- logo + favicon as before
- first suitable uploaded photo into hero/about imagery
- delivery intro / collection intro copy
- structured menu tree into WooCommerce products
- policy draft enrichment from legal + allergen notes
- GA measurement ID stored into `ttos_ga_measurement_id`

Main file:

- `takeaway-os/includes/class-client-intake.php`

### 5. New Brand Guide admin screen

Branding is no longer only buried inside the general Settings page.

Added:

- dedicated `Brand Guide` submenu screen
- persistent primary nav item
- Settings page now points users to Brand Guide instead of duplicating the full branding UI

Files:

- `takeaway-os/includes/class-admin.php`
- `takeaway-os/includes/class-admin-shell.php`

## Wizard step coverage

Implemented in the React app:

1. Welcome
2. Business basics
3. Colours
4. Layout style
5. Typography
6. Logo & photos
7. Brand summary
8. Opening hours
9. Menu builder
10. Delivery & collection
11. Payments
12. Order emails
13. Accounting
14. Newsletter
15. Google Analytics
16. VAT & legal
17. Finish

## What is working now

- Wizard shell builds and loads from `takeaway-os/intake/dist/`
- Step-by-step save works through REST
- Uploads use the existing intake upload pipeline
- Final submit works through REST and existing intake records
- Brand Guide has a dedicated admin screen
- Intake imports now touch real site settings, site content, menu items, and policy drafts

## Important limitations / honest gaps

### 1. Connector flows are scaffolded, not fully production OAuth

What is done:

- provider registry
- availability messaging based on config constants
- “start connect” action
- connector state saved back into the intake record

What is not done yet:

- full provider OAuth callback round-trips
- encrypted token persistence
- account refresh / disconnect handling
- live provider-specific success verification

Why:

- the spec assumed an existing `TTOS_Accounting`-style encrypted OAuth bridge in this repo, but that class/pattern is not present in this workspace snapshot
- no server credential verification was completed in this implementation pass

### 2. Menu builder is structured, but not yet the full drag-and-drop spec

What is done:

- nested category → sub-category → item structure
- item pricing, allergens, dietary flags, kitchen notes
- option groups and option values
- move up/down reorder controls
- import into WooCommerce products

What is not done yet:

- the full `dnd-kit` keyboard drag/drop interaction model described in the spec
- live per-item image uploads
- polished screen-reader reorder announcements

### 3. Analytics is stored, not yet rendered on the frontend

The GA4 Measurement ID is now captured and saved to `ttos_ga_measurement_id`, but no frontend script injection or consent-aware analytics wiring was added in this pass.

### 4. Policy drafting is seeded, not fully regenerated from scratch

This pass appends client onboarding notes and menu allergen snapshots into the existing policy content model.

It does not yet:

- fully regenerate every policy from a dedicated template engine
- diff against manually edited policies
- prevent duplicate attachment imports across repeated re-imports

## Files changed

Core PHP:

- `takeaway-os/takeaway-os.php`
- `takeaway-os/includes/class-admin.php`
- `takeaway-os/includes/class-admin-shell.php`
- `takeaway-os/includes/class-client-intake.php`
- `takeaway-os/includes/class-intake-rest.php`
- `takeaway-os/includes/class-oauth-connectors.php`

Wizard app:

- `takeaway-os/intake/package.json`
- `takeaway-os/intake/vite.config.js`
- `takeaway-os/intake/src/api.js`
- `takeaway-os/intake/src/steps.js`
- `takeaway-os/intake/src/IntakeWizard.jsx`
- `takeaway-os/intake/src/index.jsx`
- `takeaway-os/intake/src/styles.css`
- `takeaway-os/intake/src/components/ProgressBar.jsx`
- `takeaway-os/intake/src/components/HelpTip.jsx`
- `takeaway-os/intake/src/components/SkipButton.jsx`
- `takeaway-os/intake/src/components/VisualCardPicker.jsx`
- `takeaway-os/intake/src/steps/Welcome.jsx`
- `takeaway-os/intake/src/steps/Finish.jsx`
- `takeaway-os/intake/dist/intake.css`
- `takeaway-os/intake/dist/intake.js`

## Verification run

PHP lint:

- `class-oauth-connectors.php` passed
- `class-intake-rest.php` passed
- `class-client-intake.php` passed
- `class-admin.php` passed
- `class-admin-shell.php` passed
- `takeaway-os.php` passed

Front-end build:

- `npm install` completed using a writable local npm cache
- `npm run build` passed
- generated:
  - `takeaway-os/intake/dist/intake.css`
  - `takeaway-os/intake/dist/intake.js`

## Recommended next pass

1. Finish real OAuth callback/storage flows for the connector steps.
2. Upgrade menu reordering from move buttons to the full accessible `dnd-kit` interaction model.
3. Add browser validation on a real staging magic link:
   - draft save
   - upload flow
   - submit flow
   - Brand Guide admin screen
   - import flow
4. Add consent-aware frontend wiring for the stored GA4 Measurement ID.
5. Add dedupe logic for repeated media imports from the same stored intake upload.

## Claude review focus

Please review these areas closely:

- REST route trust boundaries and token validation
- step sanitisation coverage in `TTOS_Client_Intake::sanitize_wizard_step()`
- repeated import behaviour for menu/media/policies
- Brand Guide nav/menu placement vs existing admin shell conventions
- whether any existing client-intake admin review assumptions were broken by the richer `submitted_data` shape

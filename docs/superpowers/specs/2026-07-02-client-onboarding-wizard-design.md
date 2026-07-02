# Client Onboarding Wizard — Design

Status: approved by user, ready for implementation plan.
Supersedes: nothing (extends existing `TTOS_Client_Intake` backend; front end is a new build informed by, but not reusing the code of, `foundation-content-onboard`).

## 1. Problem

Every new client build repeats the same painful setup jobs: **menu creation**, **WooCommerce/payment setup**, **SMTP**, plus VAT and branding. The current `TTOS_Client_Intake` magic link (see [class-client-intake.php](../../../takeaway-os/includes/class-client-intake.php)) already captures 9 sections of business data securely, but as one long-scroll form with plain text fields — no menu builder, no payment self-connect, no SMTP connect, no visual branding tools.

Separately, a prior plugin (`foundation-content-onboard`, v2.4.4) proved a one-question-at-a-time wizard UX with a Palette Studio, typography picker, and brand summary review, driven off a magic-link-style token. Its interaction design is worth keeping; its jQuery implementation is not (see §3).

This design keeps `TTOS_Client_Intake`'s secure backend (hashed tokens, rate limiting, MIME-checked uploads, draft/resume, admin review + import) completely unchanged, and replaces the public-facing form with a new React front end that recreates the proven UX from `foundation-content-onboard`, extended with new step types the old tool never needed: a menu builder, payment/email/accounting/newsletter/analytics connectors, and a persistent post-launch "Brand Guide" screen.

## 2. Dev workflow this serves

```
1. Clone the dev-domain master site → new client domain (Hostinger copy-website)
2. Dev sets site name + tagline + admin users in Launchpad
3. Dev sends the magic link (existing "Developer startup" panel, unchanged)
4. Client completes the wizard, unattended, at their own pace (autosaves, resumable)
5. Dev reviews submission, applies it, does a short finishing pass
6. Setup Health + Go Live checklist as today
```

Steps 1–3 and 5–6 are **already built** (Launchpad, Client Intake creation, Setup Health, Go Live). This design only replaces step 4's UI/scope and extends the "apply" step.

## 3. Client vs dev responsibility boundary

The client fills in **information and choices only** — nothing that requires a login, password, or API secret is ever typed into the public wizard. Every "connect" step (payment, email, accounting, newsletter, analytics) uses OAuth-style sign-in through the provider's own page, never a field for a key or password.

| Client does (wizard) | Dev does (after submission) |
|---|---|
| Business info, hours, delivery rules | Confirm the connected accounts actually work |
| Colours, fonts, logo, layout style | Quick polish pass only (client already chose) |
| Full menu (categories → items → options → allergens) | Spot-check the menu |
| Which payment/email/accounting/newsletter/analytics provider, then self-connects | Nothing — connection is already live, or finish the last dev-side link where a provider has no full OAuth (see §7) |
| VAT yes/no/not-sure | Review the auto-drafted legal pages before publish |
| — | Run Setup Health + tick the Go Live checklist |

This is a hard security boundary, not just a UX preference — see §8.

## 4. Architecture: React front end, unchanged PHP backend

`TTOS_Client_Intake`'s PHP stays exactly as it is today: `register_rest_route`-style token validation, `sanitize_public_submission()`, upload handling, the admin review/import screen. Only the thing the client sees at the magic-link URL changes.

**Decision: build the client wizard as a third React/Vite bundle**, matching the two React apps already proven in this exact plugin — the admin Setup Wizard (`wizard/`, Phase 3) and the live Owner Dashboard (`dashboard/`, Phase 8). Same build tooling (Vite 5, React 18, IIFE output), zero new infrastructure risk.

Why not reuse `foundation-content-onboard`'s literal jQuery code:
- Its drag-and-drop (`jquery-ui-sortable` + manual re-render) has no built-in keyboard accessibility — exactly what §6.1's WCAG 2.2 AA requirement needs. React has mature, keyboard-accessible drag-and-drop libraries (e.g. `dnd-kit`, which ships a keyboard sensor and screen-reader announcements out of the box) — building on that directly serves the a11y requirement instead of fighting it.
- Manually mutating a shared `this.data` object and re-rendering via string-templated HTML is the kind of code that gets fragile as step count and conditional logic grow — already a known shape of problem in this exact plugin's history (see the class-retention.php lazy-class-loading bugs from earlier phases). React's component/state model handles a 17-step conditional wizard more predictably.
- Consistency: the team already builds and maintains Vite+React here for the other two wizards. A third one is a known pattern, not a new skill.

What ports over from `foundation-content-onboard` is the **UX design**, not the code: one-question-at-a-time flow, typewriter-style question reveal, autosave, Palette Studio interaction, typography picker, brand summary review — all rebuilt as React components against the same visual language already agreed in this session.

## 5. Step order (17 steps)

| # | id | Required? | Notes |
|---|---|---|---|
| 1 | `welcome` | — | "Thanks for choosing Inkfire" warm intro, sets expectations (~20 min, autosaves, resumable) |
| 2 | `business_basics` | Required | Existing Client Intake fields, unchanged data shape |
| 3 | `palette` | Required | Palette Studio — draggable colour strips, writes `branding.colors` |
| 4 | `layout_style` | Required | Header/hero/footer style thumbnail cards (see §6.1) |
| 5 | `typography` | Required | Up to 4 fonts, writes `branding.font_heading` / `branding.font_body` |
| 6 | `logo_photos` | Required | Logo, favicon, food/shopfront/interior photos |
| 7 | `brand_summary` | Required | Review card before continuing |
| 8 | `opening_hours` | Required | Existing Client Intake fields, unchanged |
| 9 | `menu_builder` | Required | See §7 |
| 10 | `delivery_collection` | Required | Existing Client Intake fields, unchanged |
| 11 | `payments` | Required | WooCommerce needs at least one way to take money — see §6.2 |
| 12 | `order_email` | Skippable | Connect Gmail/Outlook — see §6.3 |
| 13 | `accounting` | Skippable | Connect Xero/QBO/FreeAgent — see §6.4 |
| 14 | `newsletter` | Skippable | Connect Mailchimp-style provider — see §6.5 |
| 15 | `analytics` | Skippable | Google Analytics measurement ID — see §6.6 |
| 16 | `vat_legal` | Required | VAT yes/no/not-sure + auto-drafted policies — see §6.7 |
| 17 | `finish` | — | Recap of what was done + `support@inkfire.co.uk` — see §8.3 |

Required steps show a **"Contact support"** button (`mailto:support@inkfire.co.uk`) instead of Skip — if the client is stuck on something essential, they email a human, they don't leave the site half-built.

Every step gets a small **`?` help icon** next to the question, revealing a one-line plain-English tip inline (not a separate modal) — low-friction, no page leaves the flow.

## 6. New/changed step types in detail

### 6.1 Layout & style picker (step 4)
Thumbnail cards for `header_style` (Utility / Centered Brand), `hero_style` (Editorial Split / Cinematic Photo / Product Mosaic), `footer_style` (Trust-Led / Editorial) — the exact option sets already defined in [class-admin.php:205-209](../../../takeaway-os/includes/class-admin.php). One card selected per group, selecting one visually dims the others (same interaction pattern as payment picker). Writes to `submitted_data.branding.{header_style,hero_style,footer_style}`.

### 6.2 Payment connect (step 11) — required
- First sub-question: "Do you currently take card payments, and who with?" — free-text/typeahead, captures what they have today (a Dojo/Worldpay/takepayments card machine, or nothing yet). Informational only — doesn't change what's offered next.
- Then: a provider grid for the **website gateway** — Stripe, Square, SumUp, PayPal/Zettle, Open Banking, plus **Other** (free-text fallback).
- Selecting a provider dims the rest and reveals a single **Connect** button.
- Each Connect button routes through an **Inkfire affiliate/referral link** where the provider's partner program supports it (Stripe, Square, SumUp, PayPal all do). One tap for the client; Inkfire earns the referral behind the scenes.
- No API key or secret is ever typed in the public wizard. True one-tap OAuth is confirmed feasible for Stripe (Stripe Connect). For providers without a full OAuth flow, "Connect" signs the client up via Inkfire's partner link and the dev finishes the last linking step post-submission — scope this honestly per-provider during implementation, don't assume uniform behaviour.
- Square and SumUp double as physical card readers, covering the "physical units" need without a separate POS integration project.

### 6.3 Order email connect (step 12) — skippable
- Two connect cards: **Gmail/Workspace** and **Outlook/Microsoft**, plus an "other email host" fallback (collects just the address; dev finishes SMTP relay config afterwards, same as today's pattern).
- One-tap OAuth sign-in, no password ever typed.
- **Real technical dependency, not a toggle:** requires an OAuth app registration under Inkfire's own Google Cloud and Microsoft Azure developer accounts — comparable weight to the payment integrations. Scope this explicitly in the implementation plan.
- If skipped: WooCommerce keeps sending order emails via the server's default mail function, exactly as it does today. Nothing breaks; it's just not on a nicer branded provider.

### 6.4 Accounting connect (step 13) — skippable
- **Already built.** `TTOS_Accounting` (see [class-accounting.php](../../../takeaway-os/includes/class-accounting.php)) already has a working OAuth bridge for Xero, QuickBooks Online, and FreeAgent, with encrypted token storage. This step is a thin wizard front end over the existing `get_auth_url()`/`handle_oauth_callback()` flow — no new backend work, just exposing it here.
- Connect cards: Xero, QuickBooks, FreeAgent, plus "not right now."

### 6.5 Newsletter connect (step 14) — skippable
- **New integration** — no provider connection exists today (the theme has a newsletter *signup section* on the homepage, but it isn't wired to any email marketing platform).
- Connect card(s) for Mailchimp (and similar, e.g. Klaviyo, to be scoped in the implementation plan) via that provider's OAuth/API-key flow.
- If skipped, the homepage newsletter section (if enabled) just collects signups with no platform behind it yet — same as today.

### 6.6 Google Analytics (step 15) — skippable
- **New, but the simplest of the four connectors** — GA4 doesn't need OAuth, just a Measurement ID (`G-XXXXXXX`) pasted into one field, or a "I don't have one yet" skip.
- No developer app registration needed, unlike §6.3/6.5 — safe to build first among the new integrations if sequencing matters.

### 6.7 VAT & legal (step 16) — required
- Plain-English question: "Is your takeaway VAT registered?" — Yes / No / Not sure, each with a one-line explainer.
- VAT number field only appears if "Yes".
- On submission, three policy pages are **auto-drafted** (not just captured as free-text notes, as today): allergen disclaimer, refund/cancellation policy, privacy notice — generated from answers across the whole wizard (allergen data from the menu builder, business details, delivery rules).
- **Never auto-published.** Drafts stay unpublished until the developer explicitly reviews them — enforced via the existing Go Live checklist item "Terms, privacy, cookies and accessibility checked," which already exists and already gates launch. No new gating mechanism needed; this just gives that checklist item real content to check instead of a blank page.

## 7. Menu builder (step 9) — required

**Decision: no AI/OCR extraction.** Client builds the menu directly — clean, structured, no PDF/photo dependency, no per-menu cost.

- Left panel: drag-and-drop tree — parent category → sub-category → item. Add/remove at any level.
- Right panel: item editor — photo, name, description, base price, allergen chips (14 UK allergens), one or more **option groups** (e.g. "Size": 10"/+£0, 12"/+£2.50, 16"/+£5.00), optional kitchen note.
- Option groups are unbounded per item; category depth is capped at parent → sub → item (no deeper nesting needed).
- Data shape target: `submitted_data.menu_content.categories[]` where each category has `name`, `sub_categories[]`, each sub-category has `items[]`, each item has `{name, description, price, image_upload_ref, allergens[], option_groups[]}`.
- **On import**, each item becomes a real `WC_Product_Simple` (or variable product if it has option groups), each category becomes a real `product_cat` term — same mechanism already used by `TTOS_Production::apply_starter_menu()` for cuisine starter packs, so the import path extends existing code rather than adding new plumbing.

### 7.1 Interaction & accessibility requirements (binding)

- **Keyboard reordering is the primary mechanism, not a fallback.** Focus a drag handle, `Space` picks up, arrow keys move, `Space` drops, `Esc` cancels and returns the item to its original position.
- Mouse/touch drag is an enhancement on the same underlying reorder function — one code path, not two parallel implementations.
- No stuck/frozen drag states: an interrupted drag (lost pointer, tab switch, connection drop) auto-cancels and snaps back within one tick; no drag operation may block the UI indefinitely.
- 44×44px minimum touch target on every grip, chip, and button.
- On narrow viewports, the item editor becomes a full-screen sheet, not a squeezed sidebar column.
- WCAG 2.2 AA: 4.5:1 text contrast, visible focus ring on every interactive element, a polite live region announces reorder moves ("Margherita moved to position 2 in Pizzas"), every field has a linked label, every error is linked to its field.
- **Implementation library:** given the React decision in §4, use an accessible drag-and-drop library with a built-in keyboard sensor (e.g. `dnd-kit`) rather than hand-rolling keyboard support on top of a mouse-only tool — this satisfies the requirement directly instead of bolting accessibility on afterwards.

## 8. Wizard shell details

### 8.1 Welcome screen (step 1)
Warm, branded intro: "Thanks for choosing Inkfire and trusting us with your website build." Sets expectations — roughly 20 minutes, answer at their own pace, autosaves, resumable via the same magic link.

### 8.2 Skip + note pattern (skippable steps only)
Skippable steps (§5) show a **Skip this step** button. Skipping reveals an optional single-line note field: "optional note for your developer" — stored in `submitted_data` against that step, surfaced in the existing admin review screen so the dev sees exactly what was skipped and why (or with no reason given).

### 8.3 Finish screen (step 17)
Recap of what the client just did — plain-English summary line per completed section (e.g. "colours & fonts set," "24 menu items," "delivery & collection on," "Stripe connected," "2 steps skipped for your developer to finish") — plus `support@inkfire.co.uk` for any questions. No dead end; the client always has a next action if something feels unresolved.

## 9. Brand Guide — persistent post-launch screen

The wizard is a one-time capture; branding needs to stay editable afterwards without going through the magic link again.

- **Existing infrastructure this builds on:** `TTOS_Settings::print_brand_css()` already outputs live `--tt-*` CSS custom properties site-wide, including `--tt-font-heading` / `--tt-font-body` — colours and fonts already update the live site immediately on save, today, for anyone editing Settings → Branding. This is not a new mechanism; it already works.
- **What's actually new:** a single top-level **"Brand Guide"** wp-admin menu item, gathering colours, typography, logo/photos, and layout style (currently split across the existing Branding settings screen) into one focused screen with a live preview panel, so it doesn't feel buried inside general settings.
- **One shared screen, not two.** No separate copy inside the Phase 8 Owner Dashboard (live order board) — client-role users (`takeaway_owner`/`takeaway_manager`, who already have wp-admin access per `class-activator.php`'s custom capabilities) and dev/admin users see the identical screen, gated by existing `ttos_manage_settings` capability. No second UI to build or keep in sync.
- **Import gap this closes:** today's `TTOS_Client_Intake::import_submission()` only imports `branding.{primary,accent,bg,text}` colours and the logo/favicon uploads. It does not yet import font selections or the extra photo uploads (shopfront/food/staff/interior/delivery-vehicle) into Site Content galleries. This design extends `import_submission()` to also write `branding.font_heading`/`font_body` and attach the remaining photo uploads via the same `attachment_from_upload()` mechanism already used for logo/favicon — no new upload-to-media-library pipeline needed, just wider coverage of the one that exists.
- **Save behaviour:** every save on the Brand Guide screen updates the live site immediately — same trust model as today's Settings → Branding, no separate "publish" step, no staging/preview divergence to manage.

## 10. Security notes (binding, not optional)

- All existing `TTOS_Client_Intake` security is preserved unchanged: UUID + 40-char hashed token (`wp_hash_password`), per-IP rate limiting (30 attempts/hour), nonce-verified submission, MIME-validated uploads via `TTOS_Hardening::stash_uploaded_file()`, save-draft/resume, revoke/regenerate.
- The React front end is purely presentational against this backend — it calls the same REST/admin-post endpoints the PHP form does today; moving to React does not change the trust boundary or add new unsanitized input paths.
- New step types (menu builder, payment/email/accounting/newsletter/analytics connect, VAT/legal) all write into the same `submitted_data` structure and go through `sanitize_public_submission()`.
- OAuth connect flows never pass raw credentials through the WordPress form — they are redirects to the provider's own hosted auth page, same trust model as `TTOS_Accounting`'s existing Xero/QuickBooks/FreeAgent bridge, which is a working precedent for this exact pattern in this codebase.
- Every "connect" outcome (success/fail, which provider) is recorded to `submitted_data`, not to a token or secret — actual access/refresh tokens are stored using the same encrypted-at-rest pattern `TTOS_Accounting` already uses (`TTOS_API::encrypt()`/`decrypt()`), never in the intake record itself.

## 11. What does NOT change

- `class-client-intake.php`'s admin-side review screen, import summary, upload table, history log — unchanged.
- The "Developer startup" panel in Launchpad that creates and sends the first magic link — unchanged.
- Setup Health and Go Live — unchanged; they remain the final machine + human gates.
- Menu import mechanism reuses `TTOS_Production`'s existing product/term creation code path, not a new one.
- `TTOS_Accounting`'s OAuth bridge — unchanged; §6.4 only adds a wizard step in front of it.

## 12. Explicitly out of scope for this design

- Full POS/till integration (e.g. syncing live orders to a physical terminal) — Square/SumUp/Zettle's own card readers cover the "physical unit" need without a custom sync layer. A deeper POS integration would be its own future spec if ever needed.
- AI/OCR menu extraction — deliberately dropped in favour of the clean manual builder (§7).
- Multi-site settings export/import between two live client sites (flagged in the earlier systems check as a "nice to have," unrelated to this onboarding flow).
- Rust or any non-PHP/JS runtime — ruled out; Hostinger shared hosting only executes PHP, and this wizard's complexity is feature count, not computation. Stays PHP (backend) + React (front end), consistent with the rest of Takeaway OS.

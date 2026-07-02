# Client Onboarding Wizard — Design

Status: approved by user, ready for implementation plan.
Supersedes: nothing (extends existing `TTOS_Client_Intake` + reuses `foundation-content-onboard` wizard engine).

## 1. Problem

Every new client build repeats the same three painful setup jobs: **menu creation**, **WooCommerce/payment setup**, **SMTP**, plus VAT and branding. The current `TTOS_Client_Intake` magic link (see [class-client-intake.php](../../../takeaway-os/includes/class-client-intake.php)) already captures 9 sections of business data securely, but as one long-scroll form with plain text fields — no menu builder, no payment self-connect, no SMTP connect, no visual branding tools.

Separately, a prior plugin (`foundation-content-onboard`, v2.4.4) built a proven one-question-at-a-time wizard with a Palette Studio, typography picker, and brand summary review, driven off a magic-link-style token. That engine is well-tested but lives in a different plugin, on a different data model (WordPress user login + CPT, not hashed-token intake records).

This design combines both: **Client Intake keeps its secure backend** (hashed tokens, rate limiting, MIME-checked uploads, draft/resume, admin review + import). **The wizard engine, Palette Studio, and step-by-step UX pattern from `foundation-content-onboard` become the new front end**, extended with three new step types the old tool never needed: a menu builder, a payment provider connector, and an email (SMTP) connector.

## 2. Dev workflow this serves

```
1. Clone the dev-domain master site → new client domain (Hostinger copy-website)
2. Dev sets site name + tagline + admin users in Launchpad
3. Dev sends the magic link (existing "Developer startup" panel, unchanged)
4. Client completes all 13 wizard steps, unattended
5. Dev reviews submission, applies it, does a short finishing pass
6. Setup Health + Go Live checklist as today
```

Steps 1–3 and 5–6 are **already built** (Launchpad, Client Intake creation, Setup Health, Go Live). This design only replaces step 4's UI and adds the "apply" step's scope.

## 3. Client vs dev responsibility boundary

The client fills in **information and choices only** — nothing that requires a login, password, or API secret is ever typed into the public wizard. Every "connect" step (payment, email) uses OAuth-style sign-in through the provider's own page, never a field for a key or password.

| Client does (wizard) | Dev does (after submission) |
|---|---|
| Business info, hours, delivery rules | Confirm the connected accounts actually work |
| Colours, fonts, logo, layout style | Quick polish pass only (client already chose) |
| Full menu (categories → items → options → allergens) | Spot-check the menu |
| Which payment/email provider, then self-connects | Nothing — connection is already live |
| VAT yes/no/not-sure | Review the auto-drafted legal pages before publish |
| — | Run Setup Health + tick the Go Live checklist |

This is a hard security boundary, not just a UX preference — see §7.

## 4. Step order (13 steps)

Reusing `foundation-content-onboard`'s step-array pattern (`{id, type, title, text, target}`), driven by the same one-question-at-a-time stepper shell (typewriter question text, progress bar, autosave, Back/Next footer).

| # | id | type | Notes |
|---|---|---|---|
| 1 | `welcome` | `intro` | Existing pattern, unchanged |
| 2 | `business_basics` | grouped fields | Existing Client Intake fields, unchanged data shape |
| 3 | `palette` | `brand_colours_pantone` | **Ported from foundation tool.** Draggable colour strips bound to `branding.colors` |
| 4 | `layout_style` | **new: `visual_card_picker`** | Header style / hero style / footer style, thumbnail cards. New step type, reuses the same card-select pattern as payment picker (§6) |
| 5 | `typography` | `typography_selector` | **Ported from foundation tool.** Up to 4 fonts |
| 6 | `logo_photos` | `media_multiple` | Existing upload spec pattern, unchanged |
| 7 | `brand_summary` | `brand_summary` | **Ported from foundation tool.** Review card before continuing |
| 8 | `opening_hours` | grouped fields | Existing Client Intake fields, unchanged |
| 9 | `menu_builder` | **new: `menu_builder`** | See §5 |
| 10 | `delivery_collection` | grouped fields | Existing Client Intake fields, unchanged |
| 11 | `payments` | **new: `payment_connect`** | See §6 |
| 12 | `order_email` | **new: `email_connect`** | See §6 |
| 13 | `vat_legal` | **new: `vat_legal`** | See §6 |
| — | `finish` | `outro` | Existing pattern, unchanged |

Steps 3, 5, 7 port near-verbatim from `foundation-content-onboard/assets/client.js` (lines ~659–760, ~1423–1465) — same rendering logic, rebound to `record.submitted_data.branding.*` instead of the old plugin's CPT meta.

## 5. Menu builder (step 9)

**Decision: no AI/OCR extraction.** Client builds the menu directly — clean, structured, no PDF/photo dependency, no per-menu cost.

- Left panel: drag-and-drop tree — parent category → sub-category → item. Add/remove at any level.
- Right panel: item editor — photo, name, description, base price, allergen chips (14 UK allergens), one or more **option groups** (e.g. "Size": 10"/+£0, 12"/+£2.50, 16"/+£5.00), optional kitchen note.
- Every takeaway's menu shape differs — option groups are unbounded per item, categories unbounded in depth is not needed (parent → sub → item is enough; no deeper nesting).
- Data shape target: `submitted_data.menu_content.categories[]` where each category has `name`, `sub_categories[]`, each sub-category has `items[]`, each item has `{name, description, price, image_upload_ref, allergens[], option_groups[]}`.
- **On import**, each item becomes a real `WC_Product_Simple` (or variable product if it has option groups), each category becomes a real `product_cat` term — same mechanism already used by `TTOS_Production::apply_starter_menu()` for cuisine starter packs, so the import path is a natural extension of existing code, not new plumbing.

### 5.1 Interaction & accessibility requirements (binding)

- **Keyboard reordering is the primary mechanism, not a fallback.** Focus a drag handle, `Space` picks up, arrow keys move, `Space` drops, `Esc` cancels and returns the item to its original position.
- Mouse/touch drag is an enhancement on top of the same underlying reorder function — one code path, not two parallel implementations.
- No stuck/frozen drag states: an interrupted drag (lost pointer, tab switch, connection drop) auto-cancels and snaps back within one tick; no drag operation may block the UI indefinitely.
- 44×44px minimum touch target on every grip, chip, and button.
- On narrow viewports, the item editor becomes a full-screen sheet, not a squeezed sidebar column.
- WCAG 2.2 AA: 4.5:1 text contrast, visible focus ring on every interactive element, a polite live region announces reorder moves ("Margherita moved to position 2 in Pizzas"), every field has a linked label, every error is linked to its field.
- `jquery-ui-sortable` is already a proven dependency in this codebase (used by both `class-admin.php`'s admin order board and `foundation-content-onboard`'s page builder) — reuse it for the mouse/touch layer, with a custom keyboard handler layered on top (jQuery UI Sortable does not provide adequate native keyboard support, so this part is bespoke).

## 6. New step types in detail

### 6.1 Layout & style picker (step 4)
Thumbnail cards for `header_style` (Utility / Centered Brand), `hero_style` (Editorial Split / Cinematic Photo / Product Mosaic), `footer_style` (Trust-Led / Editorial) — the exact option sets already defined in [class-admin.php:205-209](../../../takeaway-os/includes/class-admin.php). One card selected per group, selecting one visually dims the others (same interaction pattern as payment picker). Writes to `submitted_data.branding.{header_style,hero_style,footer_style}`.

### 6.2 Payment connect (step 11)
- First sub-question: "Do you currently take card payments, and who with?" — free-text/typeahead, captures what they have today (a Dojo/Worldpay/takepayments card machine, or nothing yet). This is informational only — it does not change what's offered next.
- Then: a provider grid for the **website gateway** — Stripe, Square, SumUp, PayPal/Zettle, Open Banking, plus **Other** (free-text fallback for a provider not listed).
- Selecting a provider dims the rest and reveals a single **Connect** button.
- Each Connect button routes through an **Inkfire affiliate/referral link** for that provider where the provider's partner program supports it (Stripe, Square, SumUp, PayPal all have partner programs). The client experiences one tap; Inkfire earns the referral behind the scenes.
- No API key or secret is ever typed in the public wizard. True one-tap OAuth connect is confirmed feasible for Stripe (Stripe Connect). For providers without a full OAuth flow, "Connect" instead signs the client up via Inkfire's partner link and the dev finishes the last linking step post-submission — this must be scoped honestly per-provider during implementation, not assumed uniform.
- Square and SumUp double as physical card readers, covering the "physical units" requirement without a separate POS integration project.

### 6.3 Email connect (step 12)
- Two large connect cards: **Gmail/Workspace** and **Outlook/Microsoft**, plus an "other email host" fallback.
- One-tap OAuth sign-in, no password ever typed.
- **Technical dependency to scope honestly in the implementation plan:** this requires an OAuth app registration under Inkfire's own Google Cloud and Microsoft Azure developer accounts (comparable in weight to the payment provider integrations, not a small toggle). The "other host" fallback should collect just the email address in the wizard; the dev completes actual SMTP relay configuration afterwards — this mirrors today's existing pattern where secrets are entered by the dev, not the client.

### 6.4 VAT & legal (step 13)
- Plain-English question: "Is your takeaway VAT registered?" — Yes / No / Not sure, each with a one-line explainer.
- VAT number field only appears if "Yes".
- On submission, three policy pages are **auto-drafted** (not just captured as free-text notes, as today): allergen disclaimer, refund/cancellation policy, privacy notice — generated from the client's answers across the whole wizard (allergen data from the menu builder, business details, delivery rules).
- **These drafts are never auto-published.** They stay unpublished until the developer explicitly reviews and approves them — this is enforced via the existing Go Live checklist item "Terms, privacy, cookies and accessibility checked," which already exists and already gates launch. No new gating mechanism needed; this just makes sure that checklist item has real content to check by the time the dev gets there, instead of a blank page today.

## 7. Security notes (binding, not optional)

- All existing `TTOS_Client_Intake` security is preserved unchanged: UUID + 40-char hashed token (`wp_hash_password`), per-IP rate limiting (30 attempts/hour), nonce-verified submission, MIME-validated uploads via `TTOS_Hardening::stash_uploaded_file()`, save-draft/resume, revoke/regenerate.
- New step types (menu builder, payment connect, email connect, VAT/legal) all write into the same `submitted_data` structure and go through `sanitize_public_submission()` — no new unsanitized input paths.
- OAuth connect flows (payment, email) never pass through the WordPress form as raw credentials — they are redirects to the provider's own hosted auth page, same trust model as `TTOS_Accounting`'s existing Xero/QuickBooks/FreeAgent OAuth bridge in [class-accounting.php](../../../takeaway-os/includes/class-accounting.php), which is a working precedent for this exact pattern in this codebase.
- Every "connect" outcome (success/fail, which provider) is recorded to `submitted_data`, not to a token or secret — the actual access/refresh tokens are stored using the same encrypted-at-rest pattern `TTOS_Accounting` already uses (`TTOS_API::encrypt()`/`decrypt()`), never in the intake record itself.

## 8. What does NOT change

- `class-client-intake.php`'s admin-side review screen, import summary, upload table, history log — unchanged.
- The "Developer startup" panel in Launchpad that creates and sends the first magic link — unchanged.
- Setup Health and Go Live — unchanged; they remain the final machine + human gates.
- Menu import mechanism reuses `TTOS_Production`'s existing product/term creation code path, not a new one.

## 9. Explicitly out of scope for this design

- Full POS/till integration (e.g. syncing live orders to a physical terminal) — Square/SumUp/Zettle's own card readers cover the "physical unit" need without building a custom sync layer. A deeper POS integration would be its own future spec if ever needed.
- AI/OCR menu extraction — deliberately dropped in favour of the clean manual builder (§5).
- Multi-site settings export/import between two live client sites (flagged in the earlier systems check as a "nice to have," unrelated to this onboarding flow).

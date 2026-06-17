# Master Implementation Map — Takeaway OS / Takeaway Theme

**Date:** 2026-06-16
**Mode:** Retrospective audit (work below is already implemented and deployed to staging — this map documents reality, then feeds the review-gate tickets process for anything still missing)
**Current versions:** `takeaway-os` 1.3.1 · `takeaway-theme` 0.3.7
**Staging:** `https://takeaway.thatdeveloper.co.uk` · docroot `~/domains/thatdeveloper.co.uk/public_html/takeaway` (alias `hostinger-shared`)
**Last commit:** `d90e976` — "feat(rc): v1.3.1 hardening — WCAG contrast, basket preview fragment, Setup Health"
**Built from graphify commit:** `d90e9769` (graph refreshed for this audit)

> Note on baseline drift: the brief that triggered this audit described theme 0.3.6 / plugin 1.3.0 as current. That was the state when the RC-hardening brief was *written*, not when this audit started — WCAG contrast, the basket preview fragment, and several Setup Health warnings (listed as blockers in that brief) were already fixed and deployed in the v1.3.1 commit above before this map was built. Sections below reflect what's on staging right now.

---

## 1. Current System Map

Two-package product: a WordPress **theme** (`takeaway-theme`) that owns presentation only, and a WordPress **plugin** (`takeaway-os`) that owns all business data, settings, and admin tooling. The theme reads everything through helper functions (`ttheme_business()`, `ttheme_brand()`, `tt_*()`) that fall back gracefully when the plugin is inactive — verified via `shortcode_exists()` / `function_exists()` guards throughout.

```
takeaway-theme (presentation only)          takeaway-os (business logic + admin)
├─ functions.php (cart fragments, setup)    ├─ takeaway-os.php (bootstrap, 16 classes)
├─ inc/ (helpers, contact AJAX, installer)  ├─ includes/class-settings.php   (branding/tokens)
├─ template-parts/ (header, home, footer)   ├─ includes/class-site-content.php (CRM, biggest file)
├─ assets/css/ (token cascade, no Woo       ├─ includes/class-admin.php (admin shell, 11 screens)
│   template overrides — CSS-only restyle) ├─ includes/class-woocommerce.php (hooks, taxonomies)
└─ no woocommerce/ override directory      ├─ includes/class-setup-health.php (51+ checks)
                                            └─ includes/class-{features,operations,production,
                                                page-manager,shortcodes,hardening,analytics,...}.php
```

Architecture decision honoured from the original v1.3.0 audit (`docs/V1.3.0-AUDIT-AND-PLAN.md`, D1/D5): pages stay thin shortcode containers, content lives in plugin options (`ttos_settings`, `ttos_site_content`), and WooCommerce is restyled CSS-first with **zero template overrides** — confirmed by this audit (`find takeaway-theme -iname woocommerce` returns nothing). Low risk on WooCommerce core upgrades; styling can't silently desync from template structure changes since there's no copied template to go stale.

---

## 2. Files / Classes by Responsibility

### Plugin — `takeaway-os/includes/` (16 classes)

| Class | Responsibility | Size | Graph rank |
|---|---|---|---|
| `class-features.php` | Meal deals, loyalty, advanced zones, SMS/printer/EPOS integration retry queue, Features admin screen, legacy content-manager fields | 80.8 KB | God node #1 — 88 edges |
| `class-admin.php` | Admin shell, 11 of 14 menu screens, Orders cockpit + AJAX polling, Kitchen Screen, CRM, Reports, Business Settings, Payments, Delivery, Add-ons | 140.3 KB (largest code file) | God node #2 — 84 edges |
| `class-site-content.php` | `ttos_site_content` option (homepage, menu page, contact_map, reviews, offers, social, footer, policies, banner, popup config) | 96.3 KB | God node #3 — 65 edges |
| `class-woocommerce.php` | Option groups (configurator), custom order statuses, allergen/dietary taxonomies, cart item meta, HPOS compatibility | 23.5 KB | God node #4 — 65 edges |
| `class-settings.php` | `ttos_settings` option, `brand_tokens()`, `print_brand_css()`, `darker_border()`, `computed_border_input()` | 13.4 KB | God node #5 — 54 edges |
| `class-setup-health.php` | 51+ go-live readiness checks incl. a11y/contrast, content gaps, staging-user warnings | 71.5 KB | God node #6 — 49 edges |
| `class-operations.php` | Checkout fulfilment fields/validation, handover roles, logs | 27.5 KB | God node #7 — 46 edges |
| `class-production.php` | Go-live checklist, starter menu, pause switch, page repair, banner/popup public rendering | 31.0 KB | God node #8 — 36 edges |
| `class-page-manager.php` | Generated pages with marker comments, safe repair modes (`fresh_if_unsafe`/`append`/`replace`) | 13.3 KB | God node #9 — 33 edges |
| `class-public-ui.php` | Banner/popup public-facing render hooks | 8.0 KB | — |
| `class-shortcodes.php` | 8 public shortcodes (menu, portal, tracker, delivery checker, allergens, contact, policy, kitchen) | 34.8 KB | — |
| `class-analytics.php` | Reports + CSV exports | 16.8 KB | — |
| `class-hardening.php` | Capability sync + system checks | 10.3 KB | — |
| `class-activator.php` | Activation/deactivation, role + capability seeding, version migrations | 6.1 KB | — |
| `class-plugin-checker.php` | Bundled-plugin installer/updater AJAX | 9.2 KB | — |
| `class-onboarding.php` | Onboarding flow | 4.9 KB | — |

### Theme — `takeaway-theme/`

| File | Responsibility |
|---|---|
| `functions.php` | Cart fragments filter (count badge + basket preview panel, both live since v1.3.1), theme setup/enqueue hooks |
| `inc/template-helpers.php` | `tt_*()` read-only helpers over plugin options (business info, hours, cart preview HTML, etc.) |
| `inc/contact.php` | Contact form HTML + AJAX handler — nonce, honeypot, rate-limit, sanitization (reviewed clean, see §7) |
| `inc/plugin-checklist.php` | Bundled-plugin installer UI |
| `inc/setup.php`, `inc/enqueue.php` | Theme bootstrap, asset registration |
| `front-page.php`, `page.php`, `404.php`, `header.php`, `footer.php`, `archive-product.php`, `index.php` | Top-level templates |
| `template-parts/header/` | `site-header.php` (nav, basket, account pill, contact trigger), `mobile-drawer.php` |
| `template-parts/home/` | 12 section partials (hero, trust-strip, featured-food, why-direct, offers, about, booking, opening-hours, contact-map, reviews, newsletter, bottom-cta) |
| `template-parts/footer/site-footer.php` | Footer (business info, hours, quick links, legal) |
| `assets/css/` | Token cascade: `tokens.css → base.css → header.css → footer.css → theme.css → menu.css → woo.css → utility.css` |

---

## 3. Public Templates Map

| Page | Source | Notes |
|---|---|---|
| Homepage | `front-page.php` + `template-parts/home/*` | Section-loop architecture; each section hides when its Site Content fields are empty |
| Menu | `archive-product.php` + `[takeaway_menu]` shortcode | Category nav, search, allergen filter, delivery/collection toggle, postcode checker |
| Product configurator | Modal triggered from menu cards (`ttos-open-config`) | Required-option groups, price preview, adds cart item meta |
| Cart / Checkout / Order received / My Account | Default WooCommerce templates, CSS-restyled only | No PHP template overrides exist |
| Contact | Mega-panel triggered from header nav (`tt-contact-trigger`), not a standalone page | `[takeaway_contact]` shortcode also exists for a dedicated page |
| Delivery checker | `[takeaway_delivery_checker]` | Postcode-driven, server-validated against trading zones |
| Allergens | `[takeaway_allergens]` | Static info card from Site Content |
| Policy pages | `[takeaway_policy]` + Page Manager specs | Privacy, cookies, terms, refunds, delivery policy, accessibility, food hygiene |
| 404 | `404.php` | Present (the original audit doc flagged this as missing pre-1.3.0 — now built) |
| Order tracker | `[takeaway_order_tracker]` | |
| Customer portal | `[takeaway_customer_portal]` | |
| Kitchen screen (public-facing display) | `[takeaway_kitchen_screen]` | Separate from the admin Kitchen Screen menu page |

---

## 4. Admin Screens Map (14 total, all under the "Takeaway OS" top-level menu)

| Screen | Slug | Capability gate |
|---|---|---|
| Dashboard | `takeaway-os` | `ttos_access` |
| Launchpad | `takeaway-os-launchpad` | `ttos_manage_settings` |
| Menu Builder | `takeaway-os-menu` | `ttos_manage_menu` |
| Orders (cockpit) | `takeaway-os-orders` | `ttos_view_orders` |
| Kitchen Screen | `takeaway-os-kitchen` | `ttos_view_orders` |
| Customers (CRM) | `takeaway-os-customers` | `ttos_view_reports` |
| Reports | `takeaway-os-reports` | `ttos_view_reports` |
| Business Settings | `takeaway-os-settings` | `ttos_manage_settings` |
| Payments | `takeaway-os-payments` | `ttos_manage_settings` |
| Delivery | `takeaway-os-delivery` | `ttos_manage_settings` |
| Add-ons | `takeaway-os-modules` | `ttos_modules` |
| Site Content | `takeaway-os-site-content` | `ttos_manage_settings` |
| Setup Health | `takeaway-os-setup-health` | `ttos_manage_settings` |
| Features | `takeaway-os-features` | `ttos_modules` |

Every screen is capability-gated individually (not just `manage_options`) — granular, update-safe role model.

---

## 5. WooCommerce Hooks / Overrides Map

- **No template overrides.** Confirmed: no `takeaway-theme/woocommerce/` directory exists. All cart/checkout/account/thank-you styling is CSS-only (`woo.css`) against WooCommerce's default markup. Lowest-risk approach for WC core upgrades.
- **Cart fragments filter** (`functions.php`): `woocommerce_add_to_cart_fragments` — two fragment keys: `.tt-cart-count` (badge) and `div#tt-cart-preview` (full basket preview panel with line items/subtotal/CTAs, added this session).
- **Custom order statuses, allergen/dietary taxonomies, cart item meta, HPOS declaration** — all in `class-woocommerce.php`.
- **Checkout fulfilment fields/validation** — `class-operations.php`, untouched by this session's work (per the original D5 guardrail: "never touch checkout field logic").

---

## 6. AJAX / REST / Action Endpoints Map

No REST routes are registered (`register_rest_route` — zero matches). Four AJAX actions total:

| Action | Handler | Auth model |
|---|---|---|
| `wp_ajax_ttos_order_board` | `TTOS_Admin::ajax_order_board()` | `current_user_can('ttos_view_orders')` **and** `check_ajax_referer('ttos_order_board', 'nonce', false)` — both required in one guard clause |
| `wp_ajax_ttos_order_action` | `TTOS_Admin::ajax_order_action()` | `current_user_can('ttos_update_orders')` **and** `check_ajax_referer('ttos_order_action', ...)` |
| `wp_ajax_ttos_install_plugin` | `TTOS_Plugin_Checker::ajax_install_plugin()` | Admin-only installer action (verify cap before next deploy — see Security review) |
| `wp_ajax_tt_contact` + `wp_ajax_nopriv_tt_contact` | `tt_handle_contact()` (theme) | Public by design (nopriv) — secured via nonce (`tt_contact_submit`), honeypot field, 60s/IP rate-limit via transient, full sanitization + `is_email()` validation |

See §7 for the full security read of these handlers.

---

## 7. Security / Capability Map

**Custom capabilities** (seeded in `class-activator.php` at activation): `ttos_access`, `ttos_manage`, `ttos_view_orders`, `ttos_update_orders`, `ttos_manage_menu`, `ttos_manage_settings`, `ttos_view_reports`, `ttos_modules`. Assigned per-role at activation, not a single blanket `manage_options` check — this is a deliberately granular handover model (see `docs/HANDOVER.md` "Recommended Access Split").

**Pattern observed across every privileged AJAX handler:** `current_user_can()` AND `check_ajax_referer()` checked together in a single guard, failing closed with `wp_send_json_error(..., 403)`. This is the correct fail-closed shape — neither check alone is trusted.

**Public contact form** (`tt_handle_contact()`) — read in full this audit: nonce-verified, honeypot (`website` field, silently "succeeds" for bots so they get no signal to retry), per-IP rate limit (1/60s via transient keyed on `md5($ip)`), `sanitize_text_field`/`sanitize_email`/`sanitize_textarea_field` on all inputs, `is_email()` validation, no raw `$_POST` reaches `wp_mail()`. No findings.

**Secrets sweep:** grepped both packages for hardcoded `api_key=`, `password=`, `secret=` literals — zero matches. No credentials committed to source.

**Known credential-rotation blocker (carried forward, not yet actioned):** staging WP users `claude-admin` / `codex_staging_takeaway` (and variants) exist on the live staging site and must be removed or rotated before any production handover. Setup Health's `staging_users_removed` check (added this session) now warns on these by username pattern — see `class-setup-health.php`.

**Not yet read this audit** (flagged for the Security worker pass, §below): `class-plugin-checker.php::ajax_install_plugin()` capability gate, and whether the bundled-plugin ZIP install path validates file origin before extraction.

---

## 8. Accessibility Risk Map

**Fixed this session (v1.3.1), verified by direct contrast calculation:**
- `.tt-eyebrow` (12px/800 weight) — was `var(--tt-primary)`, fails AA at light brand hues like `#ff4000` (3.51:1 on white); now `var(--tt-muted)`.
- WooCommerce processing-status badge, address-edit button, My Account active-nav state — all moved off small-text-in-primary-colour.
- `--tt-border-input` token — safe fallback corrected from `#9e8e82` (actually 2.89:1, below the 3:1 UI-component threshold) to `#96857a` (3.24:1 on cream / 3.47:1 on white). `TTOS_Settings::computed_border_input()` added so Setup Health checks the *computed* token, not the raw pre-darkening border.

**Not yet formally audited** (only contrast was checked; keyboard/focus/ARIA/landmark behaviour has not had a dedicated pass since the original v1.3.0 build):
- Mobile drawer — focus trap, ESC-to-close, `aria-expanded` state (built per D4, never re-verified post-v1.3.1)
- Basket preview panel (new this session) — keyboard operability of the toggle, focus management on open/close, screen-reader announcement of live cart updates
- Contact mega-panel — focus trap, ESC behaviour, labelled close
- Account dropdown — keyboard nav, ARIA menu semantics

This gap is exactly what the Accessibility worker pass (§ below) exists to close.

---

## 9. Design / UI Consistency Map

- **Token cascade is single-source-of-truth:** `tokens.css` (fallback values) → overridden inline at runtime by `TTOS_Settings::print_brand_css()` reading `brand_tokens()`. No raw hex values found scattered in component CSS during this session's WCAG work — confirms the system is token-disciplined.
- **No Woo template overrides** means all WooCommerce visual consistency depends on CSS selector coverage in `woo.css` staying ahead of whatever markup WooCommerce ships — a maintenance risk worth a periodic diff-check against WC core template changes, not a current defect.
- **Basket preview panel** (new): empty state (icon + message + CTA) and populated state (line items + subtotal + dual CTA) both implemented; not yet screenshotted at mobile width — pending in the Release QA pass.
- Admin shell token-driven polish was completed in the original Phase 9 — not re-touched this session.

---

## 10. Compliance / Readiness Map

| Item | Status |
|---|---|
| Allergen disclosure | `[takeaway_allergens]` shortcode + menu-level allergen tags/filter chips — built |
| Food hygiene rating display | Present in footer ("Food hygiene rating 5/5") and trust strip |
| Policy pages (privacy, cookies, terms, refunds, delivery policy, accessibility) | Page Manager specs + `[takeaway_policy]` — built, starter content carries an admin-only "review before production" reminder |
| Checkout terms visibility | Not independently re-verified this session — carry into Release QA pass |
| Business disclosure fields (address, company info) | Present in footer "Find us" block |
| PECR / cookie consent | Not mapped — no cookie-consent mechanism found in either package; flag for Compliance worker |

Full register goes in `docs/COMPLIANCE-REGISTER.md` (Worker 6, separate deliverable).

---

## 11. Release Blockers (current, as of this audit)

**P0 — confirmed still open:**
1. SMTP not configured (order emails will not send) — Setup Health flags as critical
2. Stripe not live — payment gateway blocker, by design (live Stripe enablement is explicitly out of scope for this pass)
3. `claude-admin` / `codex_staging_takeaway` staging users not yet removed/rotated on staging

**P0 — fixed this session, verified live on staging:**
4. ~~WCAG contrast on primary colour / input border~~ — fixed, see §8
5. ~~Basket preview dropdown was static~~ — now a live WC fragment, see §5/§9

**P1 — client content gaps, correctly downgraded to warnings (not hard failures) in Setup Health:**
- Hero content, logo, favicon, social links, delivery/contact/map field completeness — all now non-blocking Setup Health warnings per explicit instruction ("do not make optional social links a hard failure")

**Carried, not yet re-verified this session:**
- Duplicate page titles — Setup Health has a duplicate-page check (from the original D8 architecture decision); not re-tested in this audit pass

---

## 12. Future Roadmap Items (explicitly out of scope — do not build)

Reservations, deposits, table service, mini POS, QR ordering, multi-location, printer/EPOS/delivery-routing integrations, feedback automation, Google review flow, SMS, Starter Builder, Setup Packs. Tracked separately in `docs/ROADMAP-NOTES.md` (not yet created — recommend creating only if/when one of these is scoped for real).

---

## 13. Suggested Worker Assignments

| Worker | Scope | Output |
|---|---|---|
| 1 — Product Architect | This document + scope guardrails | `docs/MASTER-IMPLEMENTATION-MAP.md` (this file) |
| 2 — Code Critique | PHP structure, hook usage, duplication, plugin-vs-theme boundary | `docs/reviews/CODE-CRITIQUE-REVIEW.md` |
| 3 — Security | Full nonce/capability/sanitization sweep beyond §7, plugin-installer path | `docs/reviews/SECURITY-REVIEW.md` |
| 4 — Accessibility | Keyboard/focus/ARIA gaps identified in §8 | `docs/reviews/ACCESSIBILITY-REVIEW.md` |
| 5 — Design/UI | Visual consistency, empty states, mobile basket preview | `docs/reviews/DESIGN-REVIEW.md` |
| 6 — Compliance | Expand §10 into full register with v1.3.x / v1.4+ / do-not-build split | `docs/COMPLIANCE-REGISTER.md` |
| 7 — Release QA | Finish the in-progress browser regression matrix + one BACS order | `docs/reviews/RELEASE-QA-REPORT.md` |

## 14. Implementation Tickets

Deferred to `docs/RELEASE-HARDENING-TICKETS.md` (Phase C) — consolidated from all five worker review docs once they exist, so tickets aren't drafted twice.

## 15. Acceptance Criteria (for this audit pass)

- [ ] All 5 worker review docs exist and cite real file/line evidence, not generic checklist text
- [ ] Every P0 blocker in §11 has a corresponding ticket in `RELEASE-HARDENING-TICKETS.md`
- [ ] Release QA report confirms one BACS order flows through WooCommerce → cockpit → kitchen → CRM → reports
- [ ] No new feature work (reservations, POS, QR, multi-location, etc.) enters scope
- [ ] Packaging only proceeds once P0 items are resolved or explicitly documented as client-side setup blockers

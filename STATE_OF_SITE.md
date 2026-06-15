# Takeaway OS — State of Site
**Generated:** 2026-06-15  
**Theme:** takeaway-theme v0.3.6 | **Plugin:** takeaway-os v1.3.0 | **WooCommerce:** 10.8.1  
**Staging:** https://takeaway.thatdeveloper.co.uk  
**Monorepo:** `/Users/sonny-work/Documents/Takeaway theme/`

---

## System Status Overview

| System | Status | Notes |
|--------|--------|-------|
| Theme (frontend) | ✅ Built | v0.3.6, fully deployed |
| Plugin (CRM/settings) | ✅ Built | v1.3.0, all admin panels live |
| WooCommerce | ✅ Active | 10.8.1, 15 test orders exist |
| Header / Navigation | ✅ Built | Mega-contact panel, account pill, mobile drawer |
| CSS Token System | ✅ Built | Full cascade: tokens → base → header → theme |
| Delivery/Collection Toggle | ✅ Built | Fulfilment switcher, postcode checker active |
| Menu (product archive) | ✅ Built | Filter, meal deals, rewards rendering |
| Checkout | ✅ Built | WooCommerce, AJAX cart fragments, basket preview |
| Account Pill | ✅ Built | Login icon → Hello, [name] 👋 with dropdown |
| Contact Mega Panel | ✅ Built | Nav trigger, AJAX form, honeypot, rate limit |
| Per-client Toggles | ✅ Built | Phone/email/social on/off in CRM Branding tab |
| Mobile Responsive | ✅ Built | Drawer at ≤1180px, all breakpoints covered |
| A11y (ARIA/focus traps) | ✅ Built | Account drop, contact panel, drawer — all trapped |
| Stripe | ⚠️ Staging only | enabled=no, testmode=yes — needs live keys |
| SMTP (email) | ❌ Not configured | FluentSMTP active, 0 connections |
| Social Links | ❌ Empty | 0/8 social fields set in Site Content |
| Hero Content | ❌ Empty | All hero fields unset (eyebrow, title, subtitle, image) |
| Booking CTA (Zone system) | ⚠️ Off | Built, not toggled on — see Zone 1 & 3 section |
| WCAG AA Contrast | ⚠️ Partial | Primary #ff4000 = 3.51:1 (fails 4.5:1 for body copy) |

---

## What's Built & Working

### Frontend / Theme
- **Sticky 2-row header** — utility strip (status pill, centred contacts, social icons) + main nav + compact-on-scroll behaviour
- **3-column utility strip** — left: open/closed pill; centre: phone/email contacts (centred via grid); right: social icons. All three independently toggleable per client in CRM.
- **Account pill** — logged-out: round white icon; logged-in: avatar initial + "Hello, [Name] 👋" + dropdown with Orders, Profile, Addresses, Logout. Full ARIA focus trap.
- **Contact mega panel** — triggered by nav "Contact" item, drops full-width below header. Left: copy + direct contact chips. Middle: AJAX contact form (honeypot, nonce, 60s IP rate limit). Right: close button. Hidden on mobile (≤1180px) — hamburger drawer handles contact instead.
- **Mobile drawer** — full nav, account links, contact info. Focus trapped, ARIA labelled, Escape-closes.
- **Delivery/Collection switcher** — hero start-card toggles between Delivery and Collection modes, shows zone-appropriate postcode checker or click-and-collect content.
- **Basket / cart preview** — sticky header basket button, AJAX WooCommerce fragment for live count badge.
- **Homepage sections** — trust strip, reviews, offers, why-direct, about, newsletter, booking CTA (togglable per section via CRM Site Content → Homepage).
- **Menu archive** — product listing, fulfilment toggle in pagehead, meal deals, rewards.
- **Policies pages** — allergens, delivery info, privacy: all 10 policy fields set.
- **Footer** — 13/14 fields set (one field unset, likely optional).

### Plugin / CRM (Admin)
- **Branding tab** — all colour/radius/font tokens, logo, favicon, style skin, header contact strip toggles.
- **Business Info** — 15/22 fields set (name, phone, email, address showing on site).
- **Opening Times** — 3/4 fields set (standard hours configured).
- **Site Content** — section-by-section CMS: homepage, banner, popup, policies, reviews, social links, footer, booking.
- **Setup Health** — 51-point checklist with pass/warn/fail states.
- **Delivery & Collection** — zone configuration, pricing, minimum orders, postcode groups.
- **Orders** — WooCommerce order list with kitchen-friendly status display.
- **Appearance presets** — one-click branding presets (Charcoal, Ember, etc.).

---

## Content Gaps — Client Must Fill

These are **empty in the CRM** and will show as blank/fallback on the live site:

### 🔴 Critical (visible gaps)

| Section | What's missing | Where to fill |
|---------|---------------|---------------|
| Homepage hero | Eyebrow, title, subtitle, hero image | Site Content → Homepage |
| Social links | All 8 platforms (Instagram, Facebook, TikTok, etc.) | Site Content → Social Links |
| Delivery & Collection | 8/11 fields unset — zones, minimum order, radius | Site Content → Delivery & Collection |
| Contact / Map | 10/13 fields unset — map embed, address display, booking config | Site Content → Contact & Map |
| Logo & Favicon | logo_id=0, favicon_id=0 | Branding → Upload |
| SMTP | FluentSMTP connected but no mail provider configured | WP Admin → FluentSMTP → Connections |

### 🟡 Nice to have (won't break site)

| Section | What's missing | Where to fill |
|---------|---------------|---------------|
| Homepage about | About section copy | Site Content → Homepage |
| Homepage why-direct | Why order direct? bullets | Site Content → Homepage |
| Homepage offers | Promotional offers | Site Content → Homepage |
| Banner | 7/12 fields unset | Site Content → Banner |
| Popup | 7/14 fields unset | Site Content → Popup |
| Reviews | 4/5 set (1 field missing) | Site Content → Reviews |
| Business Info | 7/22 fields unset | Site Content → Business Info |

---

## Zone 1 & 3 Booking System

**Status: Built — not yet enabled for this client.**

### What it does
The delivery zone system supports up to 5 configurable zones, each with independent pricing, minimums, and fulfilment rules. Zones can have different *booking types* — allowing certain zones to route to a phone call, WhatsApp chat, or external booking URL instead of the standard WooCommerce checkout.

**Zone 1 (close radius)** → standard online ordering via WooCommerce checkout.  
**Zone 3 (outer radius / extended delivery)** → can be configured to trigger a "book via phone" or "contact us to order" flow rather than online payment. This prevents the business from accepting online orders they can't reliably fulfil at distance.

### Configuration (contact_map section)
```
booking_type        → "phone" | "whatsapp" | "url" | "none"
booking_target      → phone number / WhatsApp number / URL
booking_cta_text    → button label (e.g. "Call to Order")
booking_note        → small print below the CTA
```

### Homepage toggle
The booking CTA section on the homepage is controlled by:
```
ttos_site_content[homepage][show_booking] = "1"
```
Currently unset (off). Enable in Site Content → Homepage → Show booking CTA.

### To activate for a client
1. Site Content → Delivery & Collection → configure Zone 3 with `booking_type = phone`
2. Site Content → Contact & Map → fill `booking_*` fields  
3. Site Content → Homepage → enable `show_booking`
4. The homepage booking section will render a CTA card directing zone 3 postcodes to call/WhatsApp

---

## Blockers Before Client Handover

### 1. SMTP — order emails will not send
FluentSMTP is active but has no provider connection. WooCommerce order confirmation, password reset, and admin notification emails will silently fail.  
**Fix:** WP Admin → FluentSMTP → Connections → Add connection (SMTP2GO, SendGrid, Gmail OAuth, etc.)

### 2. Stripe — payment gateway not live
`enabled: no`, `testmode: yes`. The site cannot take real payments.  
**Fix:** WooCommerce → Settings → Payments → Stripe → enter live API keys → enable.

### 3. Hero content empty
The homepage hero section has no content set. The site will render with blank/fallback hero.  
**Fix:** Site Content → Homepage → fill hero fields + upload hero image.

### 4. No social links
All 8 social platform fields are empty. Social icons in the header utility strip will not render (correctly hidden, but still — client needs to fill).  
**Fix:** Site Content → Social Links.

### 5. Logo & Favicon
`logo_id: 0`, `favicon_id: 0`. The site is using theme fallback rendering.  
**Fix:** Branding → Logo / Favicon → upload assets.

### 6. WCAG AA contrast — primary colour
`#ff4000` (current primary) has a 3.51:1 contrast ratio against white. This passes for large text (≥18px bold / ≥24px) but **fails 4.5:1 for body copy and small UI elements**. Any coloured body text or small labels in primary will fail WCAG AA.  
**Fix (option A):** Darken primary to #d93600 (≈5.1:1) — stays in the same orange family.  
**Fix (option B):** Keep #ff4000 for decorative/large uses only; enforce dark text for body copy.

### 7. Form input border contrast
At current `--tt-border` values (~#e0e0e0 on white), the form input border ratio is ~1.2:1. WCAG 2.1 SC 1.4.11 requires 3:1 for UI component boundaries.  
**Fix:** Bump `--tt-border` to #a0a0a0 or similar (~3.3:1).

---

## Minor Outstanding Items

| Item | Details |
|------|---------|
| Duplicate page titles | "Home" (#30) and "My Account" (#395) flagged by Setup Health — both have generic titles. Update in WP Admin → Pages. |
| Basket preview content | The basket preview panel shows static empty-state even when cart has items. WC fragment only updates the count badge, not the preview content. Needs a second fragment for preview HTML. |
| Delivery & Collection CRM | Only 3/11 fields set — zones, minimum order by zone, and collection slot config all need client input. |
| `claude-admin` staging creds | User `claude-admin` / `6cc47mAOQBHlpCgaEn3v` on staging — **rotate or delete before production**. |

---

## Nice-to-Have Features (v1.3.x scope)

These don't break the site but would polish the product:

1. **Basket preview AJAX fragment** — make the basket preview dropdown populate with actual cart line items (product name, qty, line total). Currently shows static empty state.
2. **Order status page** — branded customer-facing order tracking page using WooCommerce order status (not the order tracker removed from nav — a proper post-checkout status page linked from the confirmation email).
3. **Postcode memory** — remember the customer's postcode across sessions (localStorage), pre-fill on return visit so they don't re-enter it every time.
4. **Menu category sticky nav** — pin the category filter strip to top of viewport when scrolling through a long menu archive.
5. **Review carousel auto-scroll** — homepage reviews section could auto-scroll with a pause-on-hover. Currently static.
6. **Dark mode / skin switcher** — `style_skin` token already exists in the CRM. Implement `tt-skin-ember` etc. as full CSS overrides for easy white-labelling.
7. **Setup Health completion %** — the 51-point health check could show a progress bar/percentage to give clients a sense of "go-live readiness."
8. **Homepage A/B hero** — hook into banner/popup system to allow CRM-driven hero variant testing.

---

## v1.4.0+ Roadmap (explicitly out of scope now)

- Table reservations with deposits
- Mini POS / QR ordering at table
- Multi-location / franchise support
- Live Stripe setup and real payment processing (currently staging test mode)
- Kitchen display system (KDS) integration
- SMS order notifications

---

## File Map (key files)

```
takeaway-theme/
  assets/css/
    tokens.css          ← all CSS custom properties (client-editable via CRM)
    base.css            ← resets, typography, buttons
    header.css          ← utility strip, main nav, account pill, contact panel
    footer.css          ← footer layout
    theme.css           ← homepage, hero, trust strip, booking CTA
    menu.css            ← product archive, fulfilment toggle
    woo.css             ← checkout, cart, account pages
    utility.css         ← helpers
  assets/js/
    theme.js            ← compact header, basket, account drop, contact panel, form AJAX
  inc/
    contact.php         ← tt_contact_form_html(), AJAX handler, nav filter
    template-helpers.php← tt_cart_count_badge(), tt_content(), etc.
    setup.php           ← theme setup, nav registration
    enqueue.php         ← enqueue with TTHEME_VERSION cache-busting
  template-parts/header/
    site-header.php     ← full header markup (utility strip, nav, account, contact panel)

takeaway-os/
  includes/
    class-admin.php     ← all CRM admin panels + toggle field helpers
    class-settings.php  ← TTOS_Settings::get/update, option schema
    class-site-content.php ← ttos_site_content structure + tt_content() source
    class-woocommerce.php  ← WC hooks, order flow, zone enforcement
    class-delivery.php  ← postcode checker, zone matching, Zone 1/3 booking logic
```

---

## Deployment Notes

- **Deploy:** `rsync -az --delete` over SSH alias `hostinger-shared`
- **Cache bust:** bump `TTHEME_VERSION` in `functions.php` AND `style.css` — LiteSpeed uses `?ver=` on enqueued assets
- **`wp cache flush`** only clears object cache, not LiteSpeed file cache — version bump is the reliable method
- **Never touch:** `~/public_html` or `~/domains/maliandme.co.uk` on the server
- **Graphify:** After code changes, run `graphify update .` from the monorepo root to keep the knowledge graph current

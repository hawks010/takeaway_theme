# Takeaway OS v1.3.11

Restaurant operating overlay for WordPress + WooCommerce. Pairs with Takeaway Theme v0.3.33.

## Current highlights in v1.3.11

### Branding design tokens
- Branding now stores a full token set (primary, accent, bg, surface, surface-soft, text, muted, border, success/warning/error, radius sm/md/lg, shadow, light/dark/system mode, header/hero/card/footer styles).
- Emitted as `--tt-*` CSS custom properties; legacy `--tt-secondary/--tt-dark/--tt-cream` aliases kept so older CSS keeps working.
- Six presets (Flame, Charcoal, Fresh Green, Midnight, Cream & Tomato, Minimal Mono) fill the form with confirmation; nothing saves until you click Save.
- Upgrades are add-only: existing saved colours are never overwritten (accent seeds from secondary, bg from cream, text from dark).

### Site Content CRM (Takeaway OS -> Site Content)
- One versioned option (`ttos_site_content`) with 13 structured sections: Homepage, Menu Page, Business Info, Opening Times, Delivery & Collection, Contact & Map, Reviews, Offers, Social Links, Footer, Policies, Banner, Popup.
- Images stored as Media Library attachment IDs. Repeaters (reviews/offers/why-direct cards) are validated and capped.
- Export/Import tab: versioned JSON bundle of Branding + Site Content with an explicit preview/confirm step.
- Policy texts are STARTER CONTENT ONLY — a staff-only reminder renders on every policy page until reviewed.

### Front-end systems
- Native banner (top announcement bar) and popup, configured in Site Content, disabled by default, schedule- and page-aware. Dismissals remembered in the browser; changed content re-appears. Popups never render on cart/checkout unless explicitly allowed.
- Honest delivery checker: validates against the postcode prefixes in Business Settings -> Delivery ("Delivery postcode notes", comma separated, e.g. `MK18, MK17`). Prefix matching only — not radius/geocoded. With no prefixes configured it says availability is confirmed at checkout.
- New shortcodes: `[takeaway_contact]`, `[takeaway_policy key="..."]`. Existing shortcodes kept.

### Pages
- Page Manager now generates contact + 8 policy pages (privacy, cookies, terms, refunds, delivery, accessibility, hygiene, business details). Generation is explicit (Launchpad/Setup Health repair) and never overwrites existing pages — a conflicting page gets a fresh "Takeaway …" twin instead.

### Setup Health (51 checks)
- New content checks (warnings only): business name, opening hours, hero headline, footer basics, hygiene rating, banner/popup schedule validity.
- New integrity checks: page-builder header/footer hijack (CRITICAL — detects leftover Elementor Pro theme-builder conditions and offers an admin-only repair that drafts the templates and clears conditions, never deletes), duplicate "Home"/"My Account" page warnings, placeholder site identity, policy pages present.
- Expected fresh-install warnings: Stripe not connected, SMTP not configured. Both must be resolved before paid production.

### Admin
- Third-party admin notices are suppressed on Takeaway OS screens only.
- Keyboard focus outlines and small-screen scrolling nav across the admin shell.

## Update from older builds
1. Install/activate Takeaway Theme v0.3.33 (bundled). The theme setup screen detects the older installed plugin and offers "Update bundled Takeaway OS".
2. Settings and content are preserved; migrations add new keys only.
3. After update, open Setup Health and run "Repair pages and menus" once to generate the new contact/policy pages.
4. Review Site Content -> Policies before production.

## Commercial update rule

Takeaway OS updates should preserve database-backed client settings and content.

That does not make direct code edits update-safe.

Do not customise live client behaviour by editing `takeaway-os` on the server. Product logic changes should come back into source control and ship as a normal product release.

## Known limitations
- Delivery checker is postcode-prefix based; full zone validation remains a checkout concern.
- Duplicate-page warnings list stray pages but never delete them — review manually.
- Booking is links-only (phone/WhatsApp/URL); no booking engine.

# Takeaway Theme v0.3.0 (bundled)

Native front end for Takeaway OS. No page builder required or supported as a base.

## New in v0.3.0
- Design-token CSS architecture (`tokens.css` -> `base.css` -> feature sheets). All colours/radii/shadows come from Takeaway OS Branding; legacy aliases keep old CSS working.
- New header: logo, nav, live open/closed pill, phone, account, live basket count, order CTA, accessible mobile drawer (ESC, focus trap, scroll lock).
- New dynamic footer: contact, grouped opening times, quick links, generated policy links, hygiene/Google/TripAdvisor trust row, admin-controlled built-by credit. Empty sections hide.
- Homepage is a sales funnel built from `template-parts/home/*` and the Site Content CRM — the full menu grid no longer renders on the front page. Sections hide when their content is empty; hero falls back to a branded gradient (no external placeholder images).
- Menu page: compact heading, sticky scroll-spy category nav, search + dietary/allergen filters, empty categories hidden publicly, "Uncategorized" excluded, image fallbacks, fixed sticky basket + mobile bottom CTA. Configurator modal unchanged.
- WooCommerce restyle is CSS-only (`woo.css`) — zero template overrides, so checkout logic and Takeaway OS fulfilment fields are untouched.
- Utility pages (tracker, delivery checker, allergens, contact), 9 policy templates and a branded 404.

## Developer customisation
- Child themes can override any `template-parts/*` file.
- `tt_home_sections` filter reorders/removes homepage sections.
- Token values come from the plugin (`style#takeaway-os-brand`); stylesheet fallbacks live in `assets/css/tokens.css`.
- Template helpers: `tt_content()`, `tt_business_name()`, `tt_open_status()`, `tt_hours_summary()`, `tt_active_offers()`, `tt_stars()` (see `inc/template-helpers.php`).

## Bundled plugin
`inc/bundled-plugins/takeaway-os.zip` (v1.3.0). The theme setup screen installs or updates it; data is preserved on update.

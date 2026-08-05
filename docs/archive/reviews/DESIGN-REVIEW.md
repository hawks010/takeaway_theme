# Design / UI Review — Worker 5 (Audit)

**Date:** 2026-06-16 · **Scope:** Visual consistency, spacing rhythm, token usage, empty states, header utility strip, basket preview, product cards
**Method:** Live staging screenshots (desktop, 1725px viewport) captured during this session's regression pass + source read of the token cascade. Mobile widths and the basket-preview *populated* state were not yet captured when this doc was written — both are owned by the Release QA pass (Worker 7) and should be folded back into this review once available.

---

## What I directly observed (screenshots, desktop)

### Homepage
Clean hierarchy: eyebrow ("TAKEAWAY · ORDER DIRECT") → headline → subtext → postcode-check card → hero image, trust strip (delivery/collection times, hygiene rating, Google rating) anchoring the fold. Hero card and trust strip both use consistent radius/shadow from the token system. "House favourites" section below uses a clean 3-up card grid; opening-times and "Find us" sections are well-separated with clear visual grouping. Footer is a complete 4-column layout (business info, find us, opening times, quick links) with a legal-link row and hygiene-rating/Google-link/copyright bar underneath — nothing feels unfinished or stubbed.

### Menu page
Collect/Delivery toggle and postcode checker are placed inline in the page header rather than buried — good information priority for a takeaway site where fulfilment method gates everything else. Category tabs (Burgers/Drinks/Kebabs/Pizza/Sides), search bar, and "Hide allergens" toggle are all visible without scrolling. Product cards show name, price, short description, allergen line, and a full-width "Configure item" CTA — consistent card shape across all three burger cards inspected.

### Empty / placeholder states
No product photos are uploaded yet (expected — client content gap, already tracked in the Compliance map), and the placeholder treatment is a soft orange circle on a cream card rather than a broken image icon or grey box. This reads as an intentional, on-brand "photo coming soon" state rather than a defect — better than the generic grey placeholder originally scoped in the v1.3.0 design doc (D4). No finding; this is a design strength worth keeping as-is.

### Basket preview (empty state)
Icon + "Your basket is empty" message + full-width "Browse menu" CTA, centred, matches the button/radius/shadow language used everywhere else in the header. Icon stroke-width matches the other header SVGs (account, drawer, socials all use stroke-width 2–2.5) — good cross-component consistency.

## Not yet visually confirmed (defer to Worker 7 / Release QA)

- **Basket preview populated state** (line items + subtotal + dual CTA) — built and code-reviewed (see Master Map §9) but the regression test that would have added a product to cart and screenshotted this state was interrupted mid-flow before this audit started. This is the single most important pending visual check, since it's the actual deliverable of this session's main feature. **Must be captured before packaging.**
- **Mobile widths** — zero mobile screenshots exist yet for homepage, menu, basket preview, or mobile drawer. The original D4 architecture decision treats mobile as a first-class target (drawer nav, sticky basket bar), but this audit has no visual evidence either way for the current build.
- **Checkout / cart / My Account / order-received pages** — not screenshotted this session (regression test hadn't reached them).
- **Admin screens** (Branding, Site Content, Setup Health) — not screenshotted this session.

## Token / Consistency Check (source-level, not just visual)

Confirmed via the WCAG contrast work done earlier this session: every fix replaced a hardcoded color reference with a `var(--tt-*)` token reference, never introduced a new raw hex value. Combined with the Code Critique finding that no Woo template overrides exist, the CSS surface is entirely token-driven by design, not just by convention. No drift found between `tokens.css` defaults and what's actually rendering on staging (the inline `print_brand_css()` override was read and matches expected values: primary `#ff4000`, bg `#f9f4ee`, computed input border `#96857a`).

---

## Summary

No design defects found in what was visually verified. The dominant risk isn't a known bug — it's **coverage**: the populated basket preview, all mobile breakpoints, and most of the Woo/admin flow have not been looked at yet this session. None of that is a design *finding* per se, but it should be stated plainly rather than implied as "checked": **this review covers roughly a third of the visual surface area the brief asked about.** The remaining two-thirds is Worker 7's job, already queued.

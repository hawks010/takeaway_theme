# Live Testing Addendum — Bugs Found by Direct User Interaction

**Date:** 2026-06-16 · **Context:** While Worker 7 (release QA) was running, the user interactively tested the live staging site in parallel and reported 5 concrete issues. These were not caught by the earlier static-analysis worker reviews (Code Critique, Accessibility, Design) because they only manifest at runtime / at certain viewport widths / with certain content states (e.g. no social links configured). All five were reproduced, root-caused, fixed, deployed to staging, and visually re-verified in this session.

Theme bumped to **0.3.9**, plugin to **1.3.2**.

## 1. Utility bar contact links not centered — FIXED

**Symptom:** Phone/email pills in the top utility bar appeared off-center, with dead space on the right.
**Root cause:** `.tt-utility-top` used `grid-template-columns: auto 1fr auto`. With no social links configured (current state), the right column collapses to zero width, making the asymmetric grid shift the "centered" middle column visually rightward.
**Fix:** `takeaway-theme/assets/css/header.css` — changed to `grid-template-columns: 1fr auto 1fr` with `justify-self: start/end` on the outer columns, so centering holds regardless of whether the right column has content.

## 2. Collect/Delivery toggle did nothing — FIXED

**Symptom:** Clicking "Collect" or "Delivery" on the menu page changed the pill's pressed state but never swapped the postcode-checker / "Collect from" content underneath.
**Root cause:** `.tt-pagehead-zone { display: flex; }` in `menu.css` has equal CSS specificity to the browser's native `[hidden] { display: none }` rule; being declared later in the cascade, it won the tie and silently defeated the `hidden` attribute the JS was correctly toggling.
**Fix:** Added `.tt-pagehead-zone[hidden] { display: none; }` to `menu.css`, restoring the expected behaviour.

## 3. Toggle's active pill had no visual highlight — FIXED

**Symptom:** Neither "Collect" nor "Delivery" ever looked selected (no distinguishing background).
**Root cause:** `theme.js`'s `activateMode()` sets `aria-checked` on the pills, but the CSS active-state rule in `menu.css` checked `[aria-selected="true"]` — the two never matched.
**Fix:** Changed the CSS selector to `[aria-checked="true"]` to match what the JS actually sets.

## 4. "Proceed to checkout" button rendered WooCommerce's default purple — FIXED

**Symptom:** The checkout CTA on the basket page was purple (`#7f54b9`, WooCommerce's own brand color) instead of the site's orange primary.
**Root cause:** WooCommerce core's `.button.alt` rule (wrapped in a zero-specificity `:where()` selector for the rest of its scope, but still carrying 3 real classes — `.woocommerce`, `.button`, `.alt`) out-specifies the theme's 2-class `.wc-proceed-to-checkout a.checkout-button` rule.
**Fix:** Added `!important` to the theme's `background` declaration in `woo.css`. (Side-lesson: the first attempt at this fix appeared not to work because the browser had already cached the previous `woo.css?ver=0.3.8` response from an earlier verification step in the same session — reusing a version string across two separate deploys is a real cache-busting trap. Re-bumping the version to 0.3.9 resolved it.)

## 5. Orders cockpit header had a "massive gap" between title and controls — FIXED

**Symptom:** On wide viewports, the "Live order cockpit" card's title/description sat far to the left while Auto-refresh/Sound alert/Fulfilment/Timing/Test ping/Logs controls sat pinned to the far right, with a large dead gap between them.
**Root cause:** `.ttos-order-cockpit-head { display: flex; justify-content: space-between; }` — `space-between` always maximizes the gap between exactly two flex children regardless of container width, which looks fine on a ~1100px screen but reads as broken on anything wider.
**Fix:** Removed `space-between`, added `flex-wrap: wrap` and a `flex: 1 1 320px` basis on the title block, so the two groups sit close together with a consistent gap and only separate further if they'd naturally wrap.

## Not independently re-verified

- **Configurator modal flicker on mouse movement** — applied the standard fix for this exact symptom class (`backdrop-filter: blur()` on a full-viewport fixed layer causing repaint jank): added `transform: translateZ(0); will-change: backdrop-filter` to `.ttos-modal-backdrop` in `takeaway-os/assets/frontend.css` to force it onto a stable compositor layer. This is a motion artifact that can't be confirmed via a static screenshot — recommend the user re-test directly and report back if it persists.
- **"Basket/checkout massive gaps"** — partially explained: the cart/checkout 2-column layout (`woo.css` `@media (min-width: 901px)` grid) has independent-height columns, so once you scroll past the product table, the left column can look emptier than the right "totals" card. This is standard 2-column-grid behaviour, not a clear defect, but if the user can point to a specific viewport/page where the gap looks broken (vs. just "the column ended early"), that needs a fresh look with that exact reproduction.
- **Dropdown sizing on admin screens** — looked proportionate once the cockpit header gap fix landed (the "All" selects only looked oversized because they were stranded far from their labels). No separate fix applied; flag again if still an issue after this deploy.

## Out of scope, logged separately

User also requested **auto-generated upsells based on purchase history** — this is a new feature, not a hardening fix, and is being tracked as a v1.4+ roadmap item per the explicit "do not add new major features" guardrail for this release.

# Accessibility Review — Worker 4 (Audit)

**Date:** 2026-06-16 · **Standard:** WCAG 2.2 AA
**Scope:** Public templates, WooCommerce cart/checkout/account, mobile drawer, contact panel, account dropdown, basket preview (new this session)
**Method:** Direct read of `takeaway-theme/assets/js/theme.js` (full interactive-component inventory) + matching PHP template markup, not a generic checklist

---

## Contrast (already fixed this session — confirmed, not re-litigated here)

`.tt-eyebrow`, WC status badges, address-edit button, My Account active-nav, and the computed `--tt-border-input` token were all corrected in v1.3.1 with verified ratios (3.24:1–5.53:1 depending on element). See `docs/MASTER-IMPLEMENTATION-MAP.md` §8 for the numbers. No further contrast findings.

## Keyboard / Focus / ARIA — component-by-component (read in full from `theme.js`)

| Component | Focus moves in on open | Focus trap (Tab wrap) | ESC closes | Focus restored on close | `aria-expanded`/`role` | Verdict |
|---|---|---|---|---|---|---|
| Mobile drawer (`#tt-mobile-drawer`) | Yes → `.tt-drawer-close` | Yes (lines 307-323) | Yes | Yes (`lastFocused`) | `role="dialog" aria-modal="true" aria-labelledby` on the container (verified in `mobile-drawer.php:14`) | **PASS** — textbook implementation |
| Account dropdown (`#tt-acct-main-drop`) | Yes → first link/button | Yes (lines 124-138) | Yes, returns focus to trigger | Yes | `aria-expanded`, `aria-haspopup="true"`, `aria-controls` on trigger | **PASS** |
| Contact mega panel (`#tt-contact-panel`) | Yes → first form field | **No** — no Tab-wrap logic exists for this panel, unlike the two above | Yes, returns focus to trigger | Yes | `aria-hidden` toggled, `aria-expanded`/`aria-controls`/`role="button"` on trigger | **GAP** — keyboard users can Tab out of the panel into background page content while it's visually open. Inconsistent with the drawer/account-dropdown pattern already established in the same file. |
| **Basket preview (`#tt-cart-preview`) — built this session** | **No** | **No** | Yes (line 90) | **No** | `aria-expanded`/`aria-controls` on button (`site-header.php:192-193`); `role="region" aria-label="Basket preview"` on the panel | **GAP — highest priority.** This is the one component with zero focus management of any kind: opening it does not move focus in, there's no trap, and closing doesn't return focus. A keyboard-only user pressing the basket button has no reliable way to reach the line items, subtotal, or "Checkout"/"View basket" CTAs inside — Tab from the button proceeds to whatever's next in DOM order (likely "Order now"), skipping the panel content entirely. Screen-reader users get no announcement that new content appeared (no focus shift, no `aria-live`). |

## Why this matters specifically for the basket preview

Every other overlay/disclosure pattern in this codebase (drawer, account dropdown, contact panel) was built with focus management from the start. The basket preview was added this session as a fragment-rendering fix (static → live WC fragment) — that work correctly solved the *content* problem (real line items, real subtotal) but the interaction layer (`theme.js` lines 62-92) was carried over unchanged from the old static-content version, which never needed focus management because it had no meaningful content to reach. Now that it has line items and two CTAs, the keyboard gap is live on production-bound code, not theoretical.

**Recommended fix shape** (for the tickets doc, not implemented here per audit-mode scope): mirror the account-dropdown pattern exactly — on `openCart()`, focus the first focusable element inside `#tt-cart-preview`; add the same Tab-wrap block already used for `#tt-acct-main-drop` scoped to `#tt-cart-preview`; on `closeCart()`, return focus to `#tt-basket-btn`. This is a JS-only change (~15 lines), no new architecture, low regression risk.

## Other Findings

- **Fulfilment toggle** (`.tt-fulfilment-toggle`, lines 242-266): correctly upgraded to `role="radiogroup"`/`role="radio"`/`aria-checked` — a proper ARIA pattern, not a div-soup button row. No finding.
- **WooCommerce form errors**: `linkErrors()` (line 326+) wires `aria-invalid` + `aria-describedby` between WC's `.woocommerce-invalid` rows and their error text. Addresses WCAG 3.3.1/4.1.2 on checkout. No finding.
- **Reduced motion**: `prefers-reduced-motion` is handled in exactly one file (`base.css`). Not verified line-by-line whether it covers every transition added across the token cascade (header compact-on-scroll, drawer slide, panel open/close) — flagging as a **P2 spot-check**, not a confirmed defect, since I didn't exhaustively trace every animated property against the media query.
- **Touch targets**: not measured this pass (would need rendered computed sizes, not just CSS source reading) — defer to the Release QA browser pass, which can measure live.

---

## Summary

**One P0-equivalent gap**: the basket preview panel (this session's main new feature) has no keyboard focus management, inconsistent with every other overlay in the same codebase. **One P1 gap**: the contact panel lacks a focus trap that its sibling components already have. Contrast issues from earlier in this session are confirmed fixed. Drawer and account dropdown are exemplary — use them as the template for fixing the gaps above, don't rebuild from scratch.

# Compliance / Readiness Register — Worker 6 (Audit)

**Date:** 2026-06-16 · **Scope:** Food allergen requirements, business disclosure, policy pages, checkout terms visibility, privacy/PECR, food hygiene rating display, Setup Health readiness checks
**Method:** Direct source read of `class-page-manager.php` policy specs + grep sweep for consent mechanisms; this is a mapping pass, not a legal opinion — flag items that need a human (the client or their advisor) to make a final call.

---

## Do Now (v1.3.x — already built, confirmed by source read)

| Item | Where | Status |
|---|---|---|
| Allergen disclosure | `[takeaway_allergens]` shortcode + per-product allergen tags + menu filter chips | Built |
| Privacy policy page | `class-page-manager.php:74` — `policy_privacy`, slug `privacy-policy`, content `[takeaway_policy key="privacy"]` | Built (starter content, client must review before production per the page's own admin-only reminder) |
| Cookie policy page | `policy_cookies`, content `[takeaway_policy key="cookies"]` | Built (starter content — see PECR gap below; a policy *page* is not the same as a consent *mechanism*) |
| Terms & conditions | `policy_terms`, slug `terms-and-conditions` | Built |
| Refunds & cancellations | `policy_refunds`, slug `refunds-cancellations` | Built |
| Delivery policy | slug `delivery-policy` | Built |
| Accessibility statement | `policy_accessibility`, slug `accessibility-statement` | Built |
| Food hygiene page + footer display | `policy_hygiene`, slug `food-hygiene`; rating also shown live in footer ("Food hygiene rating 5/5") and homepage trust strip | Built |
| Business disclosure (address, contact) | Footer "Find us" block, Site Content `contact_map` section | Built |

## Gap — Needs a Decision, Not Yet Built

**Cookie consent mechanism.** Grepped both packages for any consent-banner/opt-in logic (`cookie_consent`, `cookie_banner`, `gdpr`) — **zero matches**. A *cookies policy page* exists (static informational content), but there is no active consent-collection UI: no banner, no accept/reject state, no blocking of non-essential cookies pending consent.

This is **not automatically a defect** — under UK PECR, strictly-necessary cookies (WordPress/WooCommerce session, cart, login) don't require consent. Whether a banner is actually *required* depends entirely on what else gets added later: Google Analytics, Meta Pixel, or any marketing/tracking script would make a consent banner mandatory. **Recommendation: do not build a consent banner speculatively.** Add it as a Setup Health check instead — "if GA4/Meta Pixel/any non-essential tracking script is detected in the theme or via a plugin, and no consent mechanism is present, warn." That check does not exist today; flag as a P2/v1.4+ Setup Health addition, not a v1.3.x blocker, since no tracking scripts are currently present to trigger the requirement.

## Checkout Terms Visibility — Not Re-Verified This Session

The original v1.3.0 build presumably surfaces a terms checkbox/link at checkout (standard WooCommerce behaviour when a Terms page is set), but this audit did not re-confirm it's actually wired to the new `terms-and-conditions` page. **Carry into Release QA (Worker 7) checkout step** — should take 30 seconds to confirm during that pass rather than warranting a separate investigation now.

## Do Later (v1.4+)

- Automated consent-banner Setup Health trigger (described above) — only becomes relevant once/if tracking scripts are added.
- Formal allergen-matrix export (per-product full 14-allergen declaration table) beyond the current tag-based system, if the client's local authority requires it — not currently scoped, no evidence it's needed yet.

## Do Not Build Yet

Anything tied to the explicitly out-of-scope roadmap items (reservations, deposits, POS, etc.) — none of those have compliance implications worth mapping until they're actually scoped.

---

## Summary

Policy-page coverage is complete and was built correctly (7 specs, all present, all using the safe Page Manager generation pattern). The one open question — cookie consent — is correctly *not* a blocker today because there's nothing on the site yet that would trigger the legal requirement for one; it becomes one the moment analytics/marketing scripts are added, so the right fix is a Setup Health trip-wire, not a banner built ahead of need.

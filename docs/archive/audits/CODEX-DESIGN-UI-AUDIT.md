# Takeaway OS Design / UI Audit

Updated: 2026-06-17

## Visual System Read

Takeaway OS is not using a throwaway builder aesthetic. It has a coherent product shell built from:

- theme token cascade
- plugin-emitted live brand CSS variables
- bespoke header/footer/menu/woo styling
- shared admin shell styling

## Token / Styling Architecture

| Layer | Source | Purpose | Status |
| --- | --- | --- | --- |
| Base tokens | `takeaway-theme/assets/css/tokens.css` | fallback design primitives | PASS |
| Base + section styles | `base.css`, `header.css`, `footer.css`, `home.css`, `theme.css`, `menu.css`, `woo.css`, `utility.css` | frontend visual system | PASS |
| Live brand token emission | `TTOS_Settings::print_brand_css()` | saved client branding overrides CSS defaults | PASS |
| Plugin frontend styles | `takeaway-os/assets/frontend.css` | menu/configurator/upsell/tracker public UI | PASS |
| Plugin admin styles | `takeaway-os/assets/admin.css` | cockpit/admin shell | PASS |

## Frontend UI Findings

| Area | Status | Notes |
| --- | --- | --- |
| Header / utility strip | PASS | Strong branded affordances and useful direct-order cues |
| Homepage hero and content rails | PASS | Feels productised rather than placeholder-only |
| Menu layout | PASS | Browse rail, sticky basket, configurator triggers all feel intentional |
| Configurator modal | PASS | Good visual hierarchy, total and CTA are prominent |
| Smart upsell block | PASS | Useful cross-sell location; now integrated into modal flow |
| Basket / checkout styling | PASS | Branded continuity with Woo core underneath |
| Rewards/account surfaces | PARTIAL | Styled well, value depends on populated real customer data |

## Admin UI Findings

| Area | Status | Notes |
| --- | --- | --- |
| Shared primary shell | PASS | Product now feels like one admin application |
| Secondary navigation | PASS | Site Content and Features no longer masquerade as top-level nav changes |
| Setup Health | PASS | Good diagnostic framing and action grouping |
| Cockpit / CRM / reports | PASS | Information-dense but coherent |
| Notice suppression | PASS | Important for reducing hostile wp-admin clutter |

## Design Gaps

| Gap | Status | Notes |
| --- | --- | --- |
| Staging realism | NEEDS CLIENT SETUP | Placeholder social URLs, hygiene link, and contact details weaken perceived finish |
| Cuisine-driven visual adaptation | PARTIAL | Cuisine influences eyebrow text, not overall seeded experience or menu flavor |
| Demo content duplication | RISK | Repeated products on staging reduce confidence in polish |
| Final package visual QA | PARTIAL | Missing bundled zip means release artifact cannot be visually trusted end-to-end yet |

## UI Recommendation

Safe to include in final package once these are cleaned:

- remove duplicated starter/demo items
- complete client/staging content pass
- rebuild and verify bundled plugin zip

Status today: PARTIAL

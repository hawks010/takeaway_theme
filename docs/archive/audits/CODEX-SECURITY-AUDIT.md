# Takeaway OS Security Audit

Updated: 2026-06-17

## Overall Read

The codebase shows a generally solid WordPress-style security posture for a custom plugin/theme stack:

- capability gates are widely used
- nonces are used on sensitive POST and AJAX actions
- contact form includes honeypot and rate limiting
- checkout/order metadata is sanitized before persistence
- admin module lock exists for handover control

This is not the same as saying paid production is ready. The biggest security-adjacent risks now are operational and packaging-related rather than obvious raw-code exploits.

## Positive Controls

| Control | Evidence | Status |
| --- | --- | --- |
| Capability checks on admin actions | `current_user_can()` across admin, features, operations, AJAX | PASS |
| Nonce checks on admin forms | `check_admin_referer('ttos_' . $action)` pattern across POST handlers | PASS |
| AJAX nonce checks | Order board/action and installer payload use nonces | PASS |
| Contact form nonce | Theme AJAX contact handler verifies nonce | PASS |
| Contact honeypot | Hidden `website` field in contact flow | PASS |
| Contact rate limiting | transient per IP / 60 seconds | PASS |
| Sanitisation of settings | `sanitize_text_field`, `sanitize_textarea_field`, `esc_url_raw`, `sanitize_email`, numeric clamping | PASS |
| Order meta sanitisation | fulfilment, time, kitchen notes sanitized before save | PASS |
| Upsell candidate filtering | only purchasable simple/in-stock products, IDs sanitized | PASS |
| Handover module lock | `ttos_module_lock_hash` and hardening guard | PASS |

## Endpoint Risk Review

| Endpoint family | Status | Notes |
| --- | --- | --- |
| Public AJAX contact | PASS | Best public-facing endpoint posture in stack |
| Admin AJAX order board/action | PASS | Nonce + capability gated |
| Admin-post exports | PARTIAL | Source-reviewed and nonce-backed, but export outputs were not re-fired in this audit |
| Public ordering | PASS | Core add-to-cart path uses Woo + plugin validation layer |
| REST API | NOT FOUND | No custom REST routes found in current audit |

## Security Risks

| Risk | Status | Why it matters |
| --- | --- | --- |
| Packaging incompleteness | FAIL | Missing local bundled plugin zip can create release drift or accidental mismatches |
| Monolithic admin handler | RISK | Broad `TTOS_Admin::handle_posts()` surface increases regression chance when editing |
| Placeholder staging content | NEEDS STAGING CONFIG | Demo/trust/contact data could mislead if accidentally promoted |
| Live payments not signed off | NEEDS STAGING CONFIG | Business risk more than code flaw, but critical for paid production |
| Live email delivery not signed off | NEEDS STAGING CONFIG | Order confirmations/support workflows still need operational proof |
| Non-admin role live test absent | UNKNOWN | Capability code looks correct, but real browser confirmation is still missing |

## Production Security Recommendation

| Area | Decision |
| --- | --- |
| Safe for local testing | PASS |
| Safe for staging testing | PASS |
| Safe for first client beta | PARTIAL |
| Safe for paid production install | FAIL |

Reason paid production remains blocked:

- packaging truth is incomplete locally
- payment and SMTP readiness remain operationally unsigned
- onboarding/starter behavior still mismatches expected cuisine-aware setup
- staging content still contains placeholder/demo values

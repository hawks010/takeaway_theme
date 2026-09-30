# Fulfilment continuation: Codex handoff

## Status

**Menu preference reset corrected in the source candidate and tested. Not deployed. The complete product is still not ready for client sign-off.**

Existing PR #2: `fix/backend-foundations-20260930`.
Plugin: `1.3.13-rc.2`. Paired theme: `0.3.35-rc.1`.
Application change: `4592b43e4754d4d9598c4f7416d67655cdc48f80`.
Validation source: `02788f17f97eba47498907dbad48eb1e15039580`.
Push CI: https://github.com/hawks010/takeaway_theme/actions/runs/36769737190
PR CI: https://github.com/hawks010/takeaway_theme/actions/runs/36769743135

## Corrected

The menu's previous JavaScript changed only the visible panel. Checkout also had a static default even when the WooCommerce session contained a choice.

The menu now uses ordinary nonce-protected POST/redirect/GET. It writes the existing `ttos_fulfilment_method` WooCommerce session value and uses WooCommerce's own guest-session cookie, including before the first item is added. There is no second preference store, new cookie, new REST API or asynchronous saving race.

Menu, checkout and the existing zone-fee reader now use the same preference reader and enabled trading methods. The reader does not create sessions on page views. Checkout uses the saved default; WooCommerce's native update-order-review action persists valid changes after its own request validation. Malformed, unavailable or unknown choices do not overwrite the saved preference.

The visual-only JavaScript handler was removed. Existing button styling is retained, with ordinary submit buttons and pressed state. No visual redesign was performed. Theme and plugin versions were incremented and the bundled plugin was rebuilt.

## Executed checks

Push run `36769737190` completed all four jobs successfully for the validation source above. WordPress 7.1.2 and WooCommerce 11.1.2 were used.

| Check | PHP 8.2.34 | PHP 8.5.11 |
|---|---|---|
| Existing isolated regressions | 63 passed | 63 passed |
| Existing native HPOS assertions | 41 passed | 41 passed |
| New fulfilment assertions | 13 passed | 13 passed |
| Actual theme: choose collection, refresh, submit menu-item form, basket, checkout, return to menu | Passed | Passed |
| Guest separation, invalid nonce/method, switch back to delivery | Passed | Passed |
| Native checkout review choice persists back to menu | Passed | Passed |
| Original full owner/export/offline-gateway checkout and saved order verification | Passed | Passed |
| Ten additional original full HTTP repetitions | Not configured for 8.2 | 10/10 passed |

The new journey submits real HTML forms without executing JavaScript and does not submit orders. The original offline-gateway tests do submit and verify disposable orders. These are HTTP integration tests, not a browser, WAVE or external-provider audit.

All 50 packaged paths match the tested source; ZIP integrity passed. New ZIP SHA-256: `1108f2fa5bd438464479b1e2ab2232c08a41d5c1f79ca9aeb118ec21d5768678`. The old rc.1 package hash is not the hash for this candidate.

## Boundaries retained

No WooCommerce/gateway code, payment completion, tax logic, native shipping rates or native chosen-shipping-method value was changed. No client approval or launch gate was added. No live deployment, real payment, mailout, hosting PHP/JIT change, scheduler action or legacy-file deletion was performed.

This fixes preference persistence. **It does not automatically map that preference to a native shipping rate. Collection preference is not proof of a zero delivery charge.** The native shipping choice, existing zone fees and final totals need testing together on the configured shop before launch.

The earlier Codex JIT diagnosis and verified CI configuration are retained: OPcache on, PCRE JIT on, PHP JIT off, ordinary 30-second limit. Original assertions, enabled/disabled comparisons and repeated full flows remain. No claim is made to have repaired PHP's upstream engine defect or established the hosting JIT state.

## Next Codex checks

1. Install the paired candidates on an isolated copy of the actual stack. Check fresh/returning guests, signed-in users, browser back/refresh, disabled JavaScript, expired sessions/nonces and active cache behaviour, especially selection before the first cart item.
2. Verify native local-pickup/delivery methods, shipping amounts, zone fees, discounts/tax and available time slots in both modes. Resolve configuration or Takeaway adapter defects without patching the gateway or replacing WooCommerce calculations.
3. Configure and verify the chosen sandbox gateway and transactional mailer. Offline checkout is not Stripe/provider validation.
4. Complete browser/WAVE/keyboard checks, upgrade rehearsal, second-business setup, history-performance testing and the remaining package/module work before client sign-off.

Earlier evidence remains in `BACKEND-VERIFICATION-20260930.md`; the wider work list remains in `BACKEND-TODO-20260930.md`.

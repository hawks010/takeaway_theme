# Fulfilment acceptance continuation, 30 September 2026

Source candidates only: Takeaway OS `1.3.13-rc.3`, paired theme `0.3.35-rc.2`.
Repository: `hawks010/takeaway_theme`, branch `fix/backend-foundations-20260930`, existing PR #2.
This pass continues the verified rc.2 / rc.1 handoff at source HEAD `6bf4049955349c4ef787d80252e6a9e44b35b70f`. It does not supersede the earlier runtime investigation or establish production readiness.

## Symptom confirmed

Two gaps were reproduced in the in-app browser against the paired candidates on a disposable, blocked-outbound WordPress fixture:

- Changing Collection to Delivery saved the preference but left collection-only scheduled times in the checkout select. With distinct service windows, the select retained 135 collection options after the switch; a full reload reduced it to the correct 65 delivery options.
- With JavaScript disabled, WooCommerce's native Update Totals submission displayed the new delivery choice at checkout, but returning to the menu restored collection.

## Root cause

The requested-time select is outside WooCommerce's standard review/payment fragments, so the existing AJAX update did not replace it. The preference was only saved by menu submission and the AJAX review action; ordinary nonce-validated checkout submissions did not save it.

## Fix applied

- Add only the requested-time field to `woocommerce_update_order_review_fragments`, using the existing slot generator and WooCommerce's field renderer. Retain a posted time only while it is a scalar and available for the selected method. Preserve the ASAP-only hidden field and leave empty-cart/session-expiry fragments unchanged.
- Save valid enabled methods at priority 5 of `woocommerce_checkout_process`, after WooCommerce has checked its own checkout nonce and before its session/totals work. Unknown, disabled and non-scalar values remain rejected by the existing setter.
- Extend the disposable fulfilment assertions from 13 to 26 and the HTTP journey with slot refresh, reverse selection, invalid native checkout nonce and both native Update Totals directions. Assert Update Totals creates no order. Tighten the HTTP fixture URL guard and keep WP-CLI's existing PHP 8.5 vendor deprecation output out of machine-readable results.
- Bump candidate versions and update the existing bundled package. All 50 archive files match source; ZIP integrity passes. SHA-256: `2b01d2f43cdc9893bc2eb0266fef878839834043f5627e5692715a2ab146aa47`.

No WooCommerce/gateway changes, shipping-rate selection, tax/currency changes, new API/cookie, payment ledger or visual redesign. Test fixtures remain outside the distributable plugin.

## Verification

Local disposable fixture: WordPress 7.1.2, WooCommerce 11.1.2, PHP 8.5.1, MariaDB 12.3.2, HPOS enabled. This is not a copy of the Hostinger cache/security stack.

| Check | Result | Evidence |
| --- | --- | --- |
| Existing isolated suite | 63 passed, 0 failed | `/tmp/ttos-rc3-isolated-20260930.txt` |
| Expanded native fulfilment suite | 26 passed | `/tmp/ttos-rc3-fulfilment-20260930.txt` |
| Original owner/export HTTP checks | All 15 owner pages, authenticated export and anonymous denial passed | `/tmp/ttos-rc3-owner-checkout-20260930.txt` |
| Original offline native checkout | Submission and persisted status, transaction reference, collection metadata and total passed | `/tmp/ttos-rc3-order-verify-20260930.txt` |
| Expanded actual-theme HTTP journey | Native menu forms, separate guest default, invalid requests, AJAX fragments and both Update Totals directions passed; no orders created by the new journey | `/tmp/ttos-rc3-fulfilment-http-20260930.txt` |
| In-app browser, JavaScript on | Both method switches refresh times without reloading; valid 20:00 time survives switch; Tab reaches refreshed time select | `/tmp/takeaway-fulfilment-slot-refresh-20260930.jpg` |
| In-app browser, JavaScript off | Menu -> basket -> checkout retains collection; native Update Totals saves delivery back to the menu after the fix | Browser session evidence; scripting restored and temporary tab closed |

Known pre-existing test-harness ReflectionMethod deprecation notices are nonfatal in the isolated PHP 8.5 run. None of its assertions were skipped. The CI matrix and all original runtime/repetition scenarios remain unchanged; new-source CI results are recorded separately after execution.

## Actual hosting inspection

Read-only SSH inspection confirms the live site is `https://takeaway.inkfire.dev`, running plugin `1.3.12` and theme `0.3.34`. The old thatdeveloper URL redirects here. This pass did not install either candidate or change production settings.

- Current site root: `/home/u363235284/domains/inkfire.dev/public_html/takeaway`. No matching takeaway staging/QA directory was found. The site's database user has no global CREATE DATABASE grant. A copy sharing production tables is not an acceptable isolated target.
- Native zone 1 is broad GB, order 0, with flat rate instance 1 at zero cost. Native API matching of test postcodes MK18 1AA, MK17 8AA and SW1A 1AA selects this zone and only this rate. Other zones contain a 1.50 delivery rate / minimum-30 free shipping and a zero-cost local pickup, but those methods are not returned for these tested destinations. Zone 2's postcode record also contains literal `\\N` separators. No zone was edited.
- Takeaway advanced zones are independently enabled: MK18 fee 1.50 / minimum 12 / free over 30; MK17,OX27 fee 3 / minimum 18 / free over 45. Native shipping plus these fees needs an explicit merchant configuration rehearsal; the preference intentionally does not select a native rate.
- Actual time configuration is ASAP or slot, interval 15, delivery lead 35, collection lead 20, two days ahead, preorders disabled. Both fulfilment methods are enabled.
- LiteSpeed cache is enabled; basket, checkout, account and tracker exclusions are present in settings. This is configuration inspection, not proof of cache behaviour with the candidate.

## Preventive note and open acceptance

Require an isolated URL/root and a separate provisioned database before actual-stack installation. Merchant gateway sandbox credentials and the intended transactional mailer are still required; the last inspected Stripe configuration was disabled with no test key pair, and FluentSMTP had no connection. Offline checkout is not external provider or mail delivery evidence.

Actual-stack fresh/returning/signed-in journeys, browser back/refresh, session/nonce expiry, shipping amounts with discounts/tax, real cache/security behaviour, full keyboard/screen-reader/WAVE review, upgrade rehearsal, second-business setup, realistic-history performance and paid provider/modules remain open. The narrow browser checks above are not a full accessibility or site-design signoff. No production transaction, outbound mailout, hosting PHP/JIT change, scheduler deletion or legacy-export deletion was performed.

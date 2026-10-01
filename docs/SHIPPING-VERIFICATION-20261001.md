# Shipping continuation for Codex, 1 October 2026

## Status

Source candidate only, same PR #2 and branch `fix/backend-foundations-20260930` in `hawks010/takeaway_theme`.
Takeaway OS `1.3.13-rc.4`; paired theme `0.3.35-rc.3`.
Continues rc.3/rc.2 at `38c26eb37e9c7eea4d0c6b2961a28ea892de3c84` without discarding the earlier fixes or runtime investigation.
No deployment, production changes, real payment, outgoing message, scheduler cleanup or legacy export deletion.

## Narrow changes

1. Delivery-area validation now uses WooCommerce's resolved `shipping_postcode` from `woocommerce_after_checkout_validation`, instead of reading inactive raw form fields. WooCommerce remains responsible for choosing/copying billing versus a genuinely separate shipping address, including its billing-only setting.
2. The existing advanced-zone minimum check uses that same resolved destination and native validation error object. No fee, threshold, coupon rule, postcode prefix rule, native shipping rate, chosen shipping method, tax or payment logic was changed.
3. An existing native shipping label is no longer overwritten with `Collection`. The order's separate fulfilment metadata still displays the customer's preference. This exposes a configuration mismatch instead of hiding a charged native method under a collection label.
4. The advanced-zone settings explain that their non-taxable fees are additional to native shipping, and that their existing thresholds are before discounts and tax. Native-only pricing can use ordinary WooCommerce settings with this optional module off. No existing merchant module or configuration is automatically changed.
5. The version headers, theme bundle and manifest were updated together. No test fixture is inside the plugin archive.

## Executed evidence

Before/after PR run: https://github.com/hawks010/takeaway_theme/actions/runs/36794243398
Tested branch commit: `c83ee659342995d717ee43220a7811b9752eb0f0`; merge snapshot: `0510890fcbae022fff72d0ded07a9a9548cb1d95`.
All four jobs passed. On both PHP versions, the new suite reproduced **13 passed / 7 failed** against rc.3's original two affected classes, then **20 passed / 0 failed** against the corrected classes. The seven failing cases concern the inactive-address checks, billing-only policy, advanced minimum and masked native label; they are seven scenarios, not seven unrelated root causes.

Final routine-CI PR run: https://github.com/hawks010/takeaway_theme/actions/runs/36794643191
Tested branch commit: `32c4dd47a1ac8e01a3ceec26f8e44a453d0311ad`; merge snapshot: `be4676f80e74e3f30d112d11f7d91aa67c7d1600`.
**All four jobs passed.** That last change removed the temporary old-commit replay from routine CI; no application source or assertion was weakened. The before/after logs remain evidence, while ordinary future checks run the current shipping suite without fetching an old commit or requiring historic failures.

| Suite | PHP 8.2.34 | PHP 8.5.11 |
|---|---|---|
| Existing isolated regressions | 63 passed | 63 passed |
| Existing native HPOS assertions | 41 passed | 41 passed |
| Existing fulfilment assertions | 26 passed | 26 passed |
| New native shipping assertions | 20 passed | 20 passed |
| Original owner/export/offline checkout and saved-order verification | Passed | Passed |
| Existing real-theme fulfilment HTTP journey, including fragments and Update Totals | Passed | Passed |
| Additional complete original HTTP repetitions | Not configured | 10/10 passed per run |

The before/after PHP 8.2 and 8.5 artifacts were downloaded and checked. Their 224 plugin/theme source paths match the candidate byte-for-byte. The final PHP 8.5 artifact was also downloaded: all 224 paths still match; its native/fulfilment/shipping results and all ten repeated HTTP/order-verification runs were confirmed. The final PHP 8.2 job result was checked through GitHub; its artifact was not separately downloaded. Repeating suites is not additional unique coverage.

- Local PHP 8.4.23: 63/63 original isolated regressions; syntax checks for 37 plugin/test PHP files.
- Bundled ZIP: integrity passes; 50/50 archive files match tested candidate source.
- ZIP SHA-256: `3f5330da03688870e9b18a8f8706c4a6d72c336677d0dd144de96c7b74a2f065`.
- CI retained WordPress 7.1.2 / WooCommerce 11.1.2, HPOS, OPcache on, PCRE JIT on, PHP JIT disabled and the original 30-second limit. Earlier JIT failures and the unisolated upstream defect are not erased or described as application repairs. Hosting JIT state remains unverified.
- One temporary source-application job was rejected when its CI token attempted to modify a workflow; it made no remote application commit. Code/package writes and authorized workflow editing were separated. All temporary transfer files/helpers are removed. This was a delivery-tool permission failure, not a runtime regression.

## Shipping configuration rehearsal

The new fixture provisions only disposable native zones, rates, one product, a coupon and a tax rate; it deletes only those records afterward. It does not create orders. Existing offline-gateway tests still create their own separate disposable orders.

It compares broad GB-first matching with postcode-first matching; tests native flat rate, free shipping and local pickup; checks combined native/legacy fees and repeated calculation; and exercises native coupon/tax calculations. Separate address tests run through WooCommerce's own `get_posted_data()` and the registered Takeaway validation hooks, not an imitation address resolver.

Observed with the synthetic configuration: a GBP 20 basket plus native delivery of 1.50 and a legacy zone fee of 1.50 totals 23.00. Native pickup plus collection preference totals 20.00. Collection preference alone does not erase a selected native delivery rate. Repeated calculations do not duplicate the legacy fee. Native tax and coupon calculations passed with the legacy module off; the synthetic 20% test rate is not a recommendation for a merchant's tax setup.

The legacy free threshold still uses the pre-discount subtotal, while native free shipping can use the discounted subtotal. This difference is now visible and tested, not automatically reconciled or presented as a corrected merchant policy.

This is neither a client shipping configuration nor an actual-stack browser acceptance. The supplied hosting report remains the latest hosting evidence; this pass did not reinspect or change its settings.

## Remaining Codex actions

- Provision an isolated URL/root with a separate database before testing the hosting stack. Do not use the production tables or a new prefix in the live database as a shortcut.
- Install the paired candidate there and decide a single intentional charging arrangement. The suggested standard is native WooCommerce shipping, with legacy advanced zone fees off unless explicitly required. Preserve a client's existing setup until it has been reconciled, not blindly reset.
- Put specific postcode zones before any broad GB zone. Add the required native delivery/free-shipping/local-pickup methods to the actual matching zone. Use real postcode rows, not literal `\\N` separators. Rehearse outside-area behaviour as well as nearby destinations.
- Decide whether the separate fulfilment preference should be replaced by, or explicitly mapped to, the chosen native pickup/delivery method. This pass does not do either. A collection preference can still coexist with a charged native delivery rate; tests deliberately expose that remaining product gap.
- Confirm the business's minimum-order and free-delivery basis, including coupons and tax. The legacy fee threshold and native free-shipping discount policy are independently configurable and can differ. No tax/legal policy is inferred from synthetic tests.
- Retest the two address cases in a browser, JavaScript on/off, fresh/returning/signed-in users, cache, back/refresh and expired sessions/nonces. Complete WAVE, keyboard and screen-reader acceptance; these are not covered by PHP assertions alone.
- The intended sandbox gateway and transactional mailer still need configuration and real integration evidence. All current successful payment evidence uses the offline fixture gateway.
- Complete upgrade/second-business setup/performance checks and the remaining paid-module work in `BACKEND-TODO-20260930.md`. No complete-product or launch-ready claim.

No additional customer approval gate, proprietary shipping engine, second session/cookie, payment ledger or core/gateway patch was added.

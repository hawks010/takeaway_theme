# Takeaway OS: backend handoff for Codex

## Status

**Backend repair candidate implemented; final CI is not fully green. Not deployed and not feature-complete. The unresolved HTTP runtime failure is the first verification task below.**

- Repository: `hawks010/takeaway_theme`
- Branch: `fix/backend-foundations-20260930`
- Pull request: https://github.com/hawks010/takeaway_theme/pull/2
- Candidate version: `1.3.13-rc.1`
- Original source: `dc0f056770d4749c0c3b844f73cff7c99b47a127`
- Final functional change: `6dc20d3034626dc6d10de98b797429d14b67dc4f`
- Validation target including runtime matrix: `8075723f62a63b087ab93dd51c9cb3b3a1a132bf`
- Final CI run: https://github.com/hawks010/takeaway_theme/actions/runs/36738844443
- See `docs/BACKEND-TODO-20260930.md` for the completed and remaining work list.

The live installation was not modified by this work. No real orders, payment credentials, customer accounts or marketing mailouts were used. CI created a separate disposable WordPress installation. Source review, packaging and installation remain separate from passing those tests.

## The rule to preserve

**House and present WooCommerce; do not replace its commerce machinery.**

No WooCommerce or gateway source files were changed. No replacement payment ledger, custom payment approval workflow or new client test gate was introduced. Normal WordPress permissions and nonces remain. Development test protections live under `tests/`, outside the shipped plugin. A missing optional integration must not become an artificial condition for otherwise functional ordering.

## Implemented corrections

| Area | Change | Main source |
|---|---|---|
| Pause and opening | One pause state; actual schedule updated by dashboard; forced/temporary closure takes precedence; preorder-only and service-window times corrected. | `class-operations.php`, `class-dashboard-rest.php`, `class-production.php` |
| Native commerce | Onboarding no longer silently resets gateway/COD, tax, currency or account choices. Menu edits use native WooCommerce product setters and SKU lookup. | `class-onboarding.php`, `class-woocommerce.php` |
| Kitchen | Accept/prep/ready/out are stored in `_ttos_kitchen_status`, separate from native payment status and transaction references. Explicit completion/cancellation still use WooCommerce operations. Cancellation does not pretend to refund. | `class-admin.php`, `class-woocommerce.php` |
| CRM and reporting | Complete paginated paid history, recorded refunds, explicit currency scope, shared legacy report calculations and native recorded tax. Removed unsupported fixed commission-saving assumptions. | `class-admin.php`, `class-analytics.php`, `class-features.php` |
| Refund crash | Customer-order queries specify `shop_order`; the dashboard no longer treats a refund object as a customer order. | Order-query consumers and dashboard |
| Imports | JSON and CSV contacts now use the same CRM store, without creating logins or altering staff accounts. Missing fields preserve values. Old opt-outs survive bulk imports. Menu imports reuse Menu Builder saving, preserve unsupported native product types and report row/image errors. | `class-import.php`, `class-admin.php`, `class-woocommerce.php` |
| Unsubscribe | Both unsubscribe paths affect the sender's actual permission and clear queued follow-ups. General privacy consent is not treated as marketing consent. | `class-retention.php`, `class-production.php` |
| Exports | Accounting downloads stream privately through an authenticated handler, not public files. CSV cells are treated as data. Legacy order export no longer stops at 1,000 orders. | `class-accounting.php`, CSV consumers, `class-features.php` |
| Handoff | Administrator no longer needs an extra module key. Owner exports, profile and media handlers are not trapped by the CRM redirect. Hidden permanent module overrides were replaced with a one-time preservation of earlier off selections. | `class-admin.php`, `class-settings.php`, `class-activator.php` |
| Packages | Administrator Website/Ordering/Growth module presets. No checkout licence lock, gateway changes or automatic package application on upgrade. | `class-packages.php` |
| Recovery and connections | Missing known tasks can be restored without resetting due dates; empty retries stay quiet. Provider signup links and OAuth scaffolds are labelled honestly. | `class-production.php`, `class-features.php`, `class-oauth-connectors.php` |

These reports describe order-history values by creation date, not payment-provider settlement or bank reconciliation. The package presets are configuration conveniences, not proof that the whole Growth package is complete.

## Executed validation

Final run `36738844443` tests the PR merge snapshot for branch commit `8075723f62a63b087ab93dd51c9cb3b3a1a132bf`. Application source remains at functional change `6dc20d3`.

| Check | PHP 8.2.34 | PHP 8.5.11 |
|---|---|---|
| Isolated regressions | 63 passed, 0 failed | 63 passed, 0 failed |
| Real WordPress 7.1.2 / WooCommerce 11.1.2 / HPOS assertions | 41 passed, 0 failed | 41 passed, 0 failed |
| Normal owner login and 15 backend screens | Passed | Passed |
| Authenticated CSV download; anonymous download denied | Passed | Passed |
| Native classic checkout and persisted payment/fulfilment/total | Passed | Failed before submission: checkout page timed out |

The local PHP 8.4.23 run also passed 63 regressions and syntax checks for 35 PHP files. Repeating a suite on another runtime is not additional unique test coverage. The native workflow used an explicit WordPress-oriented extension set; it did not alter production PHP settings.

The HTTP test uses normal WordPress owner login and native classic WooCommerce checkout with an **offline fixture gateway**. That gateway calls WooCommerce's normal payment completion method. It does not verify Stripe, a real card, provider webhooks, real email delivery or a physical printer.

The fixture uses a clean WordPress environment, not the live theme/plugin/cache/security stack. HTTP page loads are not a browser interaction or accessibility audit. No WAVE pass is claimed.

## Unresolved runtime failure: check first

Two earlier CI attempts suffered PHP development-server segmentation faults, including PHP 8.2 and 8.5. An identical-source rerun completed. Restricting the disposable runner to explicit WordPress extensions did not establish a root cause: the final PHP 8.5 run loaded all 15 owner screens and downloaded CSV successfully, but GET checkout exceeded PHP's 30+2-second execution limit in `wp-includes/rest-api.php:2820`, inside `rest_sanitize_value_from_schema`; server exit was 124. The final PHP 8.2 run completed checkout.

**Do not describe the final workflow as green or this incident as repaired.** The cause is not established. Compare native WordPress/WooCommerce checkout with Takeaway OS disabled and enabled in an isolated copy, then test the actual hosting runtime with request traces. Collect a backtrace if a segmentation fault recurs. Do not patch WooCommerce, bypass validation or merely raise timeouts to hide the failure. The failed and successful logs are included in the evidence bundle. No runtime approval gate was added to the product.

## What Codex should do next

| Priority | Verify or finish | Expected result |
|---|---|---|
| 1 | Investigate the runtime failure above. Review the branch diff, run `php tests/backend/run.php`, then run the native workflow against the target stack in a test copy. Build an updated bundled plugin ZIP when packaging. | No core/gateway edits, no production fixtures, no old bundled plugin reinstalling over the candidate, client settings preserved. |
| 2 | Test the merchant's chosen gateway normally: successful/declined/authenticated payments, delayed/repeated callbacks, cancellations and real sandbox refunds. Test the mailer independently. | WooCommerce/provider remain authoritative; no duplicate charges, fake refunds or false delivery claims. |
| 3 | Exercise collection AND delivery, configured product options, extras, deals, taxes, fees, sale expiry, sold-out/stock behaviour, overnight hours, clock changes, pause and kitchen progression. | Correct native totals and status; no duplicate delivery fees or pricing; kitchen actions do not overwrite payment state. |
| 4 | Use a real browser for owner/kitchen roles, imports, exports, editing and session/nonce expiry. Run WAVE and keyboard/screen-reader checks. | No traps, inaccessible dialogs or broken interaction. Fifteen HTTP page loads alone do not establish this. |
| 5 | Rehearse an existing-client upgrade and a second-business setup; measure realistic long-history performance. | Settings/orders/content survive. Setup does not need code edits. Do not restore an old database over new orders to roll back code. |
| 6 | Review native scheduler missing-callback records and old accounting export files from previous versions. | Diagnose actual causes; no blind deletion or replacement scheduler. The new accounting path does not create public CSV files, but it does not erase old ones. |

The site previously had Stripe disabled and no saved SMTP configuration. Recheck current state and configure the chosen providers rather than forcing Stripe. Review the UK business timezone and actual business facts. No customer-facing approval ritual is needed for these ordinary configuration tasks.

## Wider backend work still open

Named EPOS/printer/SMS/accounting adapters need actual provider decisions, credentials and integration/device tests. Reservations, paid table ordering and broad driver workflows are not completed merely by having settings or links. Growth still needs audience batching and reward/coupon lifecycle/concurrency work. Package pricing and full package-specific presentation are not implemented by the module presets.

CRM/report/export paths now avoid silent history cutoffs and page their native reads, but some still assemble full-history results in memory. Measure and improve that before claiming high-volume performance.

A full browser/WAVE pass and the entire proposed feature catalogue have not been completed in this work.

The public design and constrained visual editor remain the later frontend phase. Finish the agreed remaining backend/provider work before presenting the entire product as ready for clients.

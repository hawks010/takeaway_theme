# Backend work list, 30 September 2026

Repository: `hawks010/takeaway_theme`. Branch: `fix/backend-foundations-20260930`. PR: #2.
Candidate: `1.3.13-rc.3`. Paired theme: `0.3.35-rc.2`. Original source: `dc0f056770d4749c0c3b844f73cff7c99b47a127`.

## Product boundaries

WooCommerce and the selected gateway own commerce. No core/gateway edits, replacement checkout, second payment ledger, automatic refund or new client-facing test/approval gate. Existing WordPress capabilities and nonces remain. Development tests and temporary fixtures stay outside the distributed plugin. No live site deployment, real payment or customer mailout has been performed.

## Completed in the source candidate

- [x] Recover source, compare the deployed baseline and resume the interrupted work without overwriting it.
- [x] Fix force-closed, temporary closure, preorder-only, timezone comparisons and real service-window slots.
- [x] Make dashboard and checkout read one pause/opening configuration.
- [x] Validate complete settings payloads before saving; preserve unrelated fields.
- [x] Keep accept/prep/ready/out kitchen stages separate from WooCommerce payment status and transaction references.
- [x] Preserve native gateway, tax, currency and account settings during onboarding.
- [x] Use native WooCommerce product setters for prices, SKU, availability and featured state; preserve native variable products and tracked stock.
- [x] Replace truncated CRM snapshots with paginated paid-history reads, recorded refunds and explicit currency scope.
- [x] Make legacy customer reports and daily close use the same corrected calculations and recorded WooCommerce tax.
- [x] Fix the dashboard crash caused by treating refund objects as customer orders.
- [x] Remove the legacy 1000-order CSV cap and test pagination beyond it.
- [x] Reconcile unsubscribe paths with the actual sender and preserve existing opt-outs.
- [x] Unify JSON and CSV customer imports with the actual CRM store; do not create or overwrite WordPress login accounts.
- [x] Reject malformed import rows, preserve omitted product/profile fields, and report partial-image failures rather than hiding them.
- [x] Serve accounting CSV through authenticated temporary streams, not publicly addressable saved files. Keep currency/refund columns explicit and guard formula-like CSV cells.
- [x] Stop owner redirects intercepting exports, profile and media handlers.
- [x] Remove the administrator's second-key trap and permanently forced-off module behaviour; migrate earlier effective off selections once, then honour ordinary administrator controls.
- [x] Add local Website/Ordering/Growth module presets without gateway changes, billing enforcement or automatic application on upgrade.
- [x] Label external-service links/scaffolds honestly instead of claiming they are connected.
- [x] Restore missing known tasks without resetting due dates; keep empty retries quiet and deactivation cleanup limited to this plugin's tasks.
- [x] Run isolated PHP regressions and disposable native WordPress/WooCommerce HPOS tests. Original evidence is in CODEX-BACKEND-REPORT-20260930.md; newer runtime and fulfilment results are linked below.
- [x] Exercise normal owner login, 15 owner backend pages, authenticated/anonymous export access and native classic checkout over HTTP in the disposable fixture.
- [x] Correct menu collection/delivery persistence using the existing WooCommerce session and ordinary form submission. The menu, checkout default and existing zone-fee reader share one preference. Actual-theme menu/cart/checkout HTTP journeys and 13 additional assertions pass on PHP 8.2/8.5 in push run 36769737190. See FULFILMENT-VERIFICATION-20260930.md.
- [x] Rebuild the paired theme's bundled plugin and manifest for rc.2. All 50 package files match source and ZIP integrity passes. Package SHA-256: `1108f2fa5bd438464479b1e2ab2232c08a41d5c1f79ca9aeb118ec21d5768678`.
- [x] Reproduce and fix stale method-specific checkout slots through the native review-fragment hook; preserve still-valid selections. Also persist no-JavaScript Update Totals choices through WooCommerce's nonce-validated checkout hook. The fulfilment suite now has 26 assertions. See FULFILMENT-ACCEPTANCE-20260930.md for browser evidence and remaining boundaries.
- [x] Rebuild the paired rc.3 plugin / rc.2 theme package. All 50 distributable files match source and ZIP integrity passes. Package SHA-256: `2b01d2f43cdc9893bc2eb0266fef878839834043f5627e5692715a2ab146aa47`.

## Required before a live client handoff

Runtime and hosting/provider evidence: [BACKEND-VERIFICATION-20260930.md](BACKEND-VERIFICATION-20260930.md).
Latest preference-persistence fix and tested candidates: [FULFILMENT-VERIFICATION-20260930.md](FULFILMENT-VERIFICATION-20260930.md).
Continuation findings and acceptance limits: [FULFILMENT-ACCEPTANCE-20260930.md](FULFILMENT-ACCEPTANCE-20260930.md).
The bundled candidate has been rebuilt; native CI is green under explicit runtime settings. Installation and existing-hosting-stack verification remain open.

- [x] Investigate and stabilize the intermittent native HTTP runtime failure documented in the Codex report. Controlled PHP 8.5.11 comparisons isolate reproduced crashes to the runner's opt-in function JIT; disabling PCRE JIT does not repair it. Native CI now explicitly keeps OPcache and PCRE JIT on, with PHP JIT disabled and the unchanged 30-second limit. Runs 36765963874 and 36765967557 pass PHP 8.2/8.5, all original scenarios and 20 additional full PHP 8.5 HTTP runs. No native validation was bypassed. The exact upstream C defect and earlier timeout reproducer remain unidentified; hosting JIT state and actual-stack verification remain open.

- [ ] Review and install the paired tested plugin and theme through the normal release process. Nothing here is live yet. The bundled plugin is already refreshed; preserve client data and settings during installation.
- [ ] Verify the preference fix on the actual cache/security stack, fresh and returning guests, signed-in customers, browser back/refresh, JavaScript disabled and expired sessions/nonces. HTTP form testing is not a browser audit.
- [ ] Verify collection/delivery preference together with native local-pickup/delivery shipping methods, zone fees, final totals and available times. Preference persistence does not automatically choose a native shipping rate or prove a zero delivery charge.
- [ ] Correct and rehearse the actual native shipping configuration on an isolated copy. Read-only matching of MK18 1AA, MK17 8AA and SW1A 1AA all resolves to the broad GB zone 1 with only its zero-cost flat rate, not the other delivery or collection zones. Do not treat the preference fix as a shipping-configuration repair.
- [ ] Test the existing theme/plugin/cache/security stack, not only the clean fixture; exercise owner/kitchen operations in a real browser and run WAVE plus keyboard/screen-reader checks.
- [ ] Verify the merchant's chosen gateway in its normal sandbox: success, decline, authentication, delayed/repeated callbacks, cancellation and actual provider refunds. The fixture gateway is not Stripe/provider verification.
- [ ] Verify transactional delivery with the chosen mailer and real intended recipients; test failure visibility and recovery without real marketing mailouts.
- [ ] Rehearse menus/options/extras/deals, tax and sale changes, overnight hours/clock changes, pauses and kitchen handoffs with the actual storefront. Classic checkout is the tested path; do not claim Blocks support without its own work and tests.
- [ ] Rehearse an upgrade and a second-business setup. Preserve orders and client content. Do not restore an old database over newer orders as a routine code rollback.
- [x] Review existing orphaned WooCommerce scheduler records with its normal tools; do not delete them blindly or patch the scheduler. Inspection found 77 failed actions and missing callbacks in the reviewed migration/database records; the current WooCommerce database version is 11.1.2. Both legacy accounting CSV URLs return 403. No scheduler actions or files were modified. Any retention/deletion policy remains a separate decision.
- [ ] Measure realistic-history performance. Pagination fixes per-query size and truncation, but CRM/report/export screens still perform full-history work and some assemble rows in memory.

## Wider features still not finished or proven

- [ ] Named printer/EPOS/SMS/accounting provider adapters, credentials, callbacks and actual device/delivery acknowledgements.
- [ ] Reservations, paid QR table ordering and driver workflows beyond existing scaffolding.
- [ ] Complete Growth audience batching and reward/coupon lifecycle/concurrency tests. A Growth module preset is not a completed Growth product.
- [ ] Finish package merchandising, install visibility and approved commercial prices. Current profiles are module-selection conveniences only.
- [ ] Public visual redesign and constrained visual content editing remain the later frontend phase.

No missing optional integration or development test receipt should become a new rule that prevents the owner from using otherwise functional WooCommerce ordering.

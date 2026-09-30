# Backend work list, 30 September 2026

Repository: `hawks010/takeaway_theme`. Branch: `fix/backend-foundations-20260930`. PR: #2.
Candidate: `1.3.13-rc.1`. Original source: `dc0f056770d4749c0c3b844f73cff7c99b47a127`.

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
- [x] Run isolated PHP regressions and disposable native WordPress/WooCommerce HPOS tests. Read CODEX-BACKEND-REPORT-20260930.md for the exact final run and counts.
- [x] Exercise normal owner login, 15 owner backend pages, authenticated/anonymous export access and native classic checkout over HTTP in the disposable fixture.

## Required before a live client handoff

Continuation evidence and current hosting/provider state: [BACKEND-VERIFICATION-20260930.md](BACKEND-VERIFICATION-20260930.md). The bundled candidate has now been rebuilt locally; installation and runtime diagnosis remain open.

- [ ] Resolve the intermittent native HTTP runtime failure documented in the Codex report. Final CI run 36738844443 passes both native assertion suites; PHP 8.2 completes checkout, but PHP 8.5 times out loading it. Earlier development-server segmentation faults also occurred. Root cause remains unknown; do not bypass native validation or hide this with an increased timeout.

- [ ] Review and install the tested source through the normal release process. Nothing here is live yet. Rebuild the bundled plugin ZIP so the theme cannot reinstall the old version.
- [ ] Test the existing theme/plugin/cache/security stack, not only the clean fixture; exercise owner/kitchen operations in a real browser and run WAVE plus keyboard/screen-reader checks.
- [ ] Verify the merchant's chosen gateway in its normal sandbox: success, decline, authentication, delayed/repeated callbacks, cancellation and actual provider refunds. The fixture gateway is not Stripe/provider verification.
- [ ] Verify transactional delivery with the chosen mailer and real intended recipients; test failure visibility and recovery without real marketing mailouts.
- [ ] Rehearse menus/options/extras/deals, tax and sale changes, overnight hours/clock changes, pauses and kitchen handoffs with the actual storefront. Classic checkout is the tested path; do not claim Blocks support without its own work and tests.
- [ ] Rehearse an upgrade and a second-business setup. Preserve orders and client content. Do not restore an old database over newer orders as a routine code rollback.
- [ ] Review existing orphaned WooCommerce scheduler records with its normal tools; do not delete them blindly or patch the scheduler. Check legacy accounting exports left from older versions before deleting any files.
- [ ] Measure realistic-history performance. Pagination fixes per-query size and truncation, but CRM/report/export screens still perform full-history work and some assemble rows in memory.

## Wider features still not finished or proven

- [ ] Named printer/EPOS/SMS/accounting provider adapters, credentials, callbacks and actual device/delivery acknowledgements.
- [ ] Reservations, paid QR table ordering and driver workflows beyond existing scaffolding.
- [ ] Complete Growth audience batching and reward/coupon lifecycle/concurrency tests. A Growth module preset is not a completed Growth product.
- [ ] Finish package merchandising, install visibility and approved commercial prices. Current profiles are module-selection conveniences only.
- [ ] Public visual redesign and constrained visual content editing remain the later frontend phase.

No missing optional integration or development test receipt should become a new rule that prevents the owner from using otherwise functional WooCommerce ordering.

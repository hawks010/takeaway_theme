# Backend work list, 30 September 2026

Branch: `fix/backend-foundations-20260930`. Candidate: `1.3.13-rc.1`.
Base source: `hawks010/takeaway_theme` at `dc0f056770d4749c0c3b844f73cff7c99b47a127`.

## Working rules

WooCommerce and the chosen gateway own commerce. No gateway patch, second payment ledger, new approval workflow, automatic refund or client-facing test gate. Tests and their disposable-site protections stay outside the shipped plugin. No live customer, payment, order or consent records were changed.

## Completed in the source candidate

- [x] Recover the repository's server snapshot and match key deployed source hashes.
- [x] Establish isolated PHP syntax and regression checks with no production credentials.
- [x] Repair closure/preorder precedence, pause state, true timestamps and real service-window slots.
- [x] Make dashboard override and pause controls use the settings actually read by ordering.
- [x] Validate entire settings requests before saving and preserve unrelated fields.
- [x] Read complete customer order history in native WooCommerce pages; use recorded payment/refund evidence and separate currencies.
- [x] Replace assumed VAT and hardcoded commission-saving claims with recorded WooCommerce tax and explicit reporting scope.
- [x] Keep kitchen stages in order metadata instead of replacing the gateway's commerce status.
- [x] Preserve native WooCommerce settings when applying the setup profile; stop forcing COD, currency, tax and onboarding-completed flags.
- [x] Use native product setters for menu prices, featured state and availability updates.
- [x] Reconcile UID unsubscribe, old opt-out records and the actual marketing sender.
- [x] Make provider links/scaffolds honest, not fake connected states.
- [x] Validate accounting dates and serve exports through a permission-checked, nonce-protected handler.
- [x] Stop the CRM redirect hijacking owner export/profile requests.
- [x] Remove the administrator's second-key trap and blanket hidden runtime module overrides. Preserve previously disabled behaviour once on upgrade, then honour normal administrator switches.
- [x] Quiet empty retry logs, restore missing known jobs without resetting due dates, and unschedule only Takeaway-owned dispatches on deactivation.
- [x] Add administrator-only Website/Ordering/Growth module presets. No checkout licence enforcement and no automatic package application on upgrade.
- [x] Local regression suite: 62 passed. The comparable original 24-check subset had 2 passes and 22 failures.
- [x] Local syntax checks on 33 PHP files passed.
- [ ] Native WordPress/WooCommerce HPOS, permissions, authenticated exports and offline-gateway HTTP checkout checks: see workflow results and the Codex report for the executed status.
- [ ] Normal source review, merge, package and deployment. The live site has not been updated by this work.

## Not represented as completed features

- [ ] Actual named EPOS/printer/SMS/accounting provider connections and delivery/retry semantics with real credentials and hardware.
- [ ] Restaurant reservations, paid table ordering and driver workflows beyond existing scaffolding.
- [ ] Growth workflow completion: large audiences/batched campaigns, reward/coupon lifecycle, concurrency and large-history performance.
- [ ] One-hour onboarding rehearsal on a second business, current plugin-stack regression and supported upgrade/rollback rehearsal.
- [ ] Live configuration: business facts, payment and mail provider setup, `Europe/London` for a UK business where appropriate, optional modules selected deliberately.
- [ ] Review stale/missing-callback WooCommerce scheduler records using WooCommerce's normal tooling. No scheduler patch or indiscriminate cleanup.
- [ ] Actual owner/kitchen browser interaction and WAVE/keyboard checks. Public visual redesign and constrained visual editor remain later work.

Passing tests are evidence for the tested candidate, not proof of every feature or a live release. The go-live checklist remains advisory. None of this work introduces a new operational approval step.

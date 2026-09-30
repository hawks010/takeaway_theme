# Codex backend handoff

## Status

**Source candidate, not deployed. Not a blanket declaration that the whole backend or every paid module is finished.**

Repository: `hawks010/takeaway_theme`.
Branch: `fix/backend-foundations-20260930`.
Original source: `dc0f056770d4749c0c3b844f73cff7c99b47a127`.
Plugin candidate: `1.3.13-rc.1`.

The site connector had no build or release interface. The changes were developed separately and placed in the product branch. No production credentials, live test checkout, customer writes or live source edits were used.

## What changed

1. **WooCommerce ownership:** profile application no longer enables COD, re-registers gateways, overwrites tax/currency/account options or marks WooCommerce onboarding complete. Native product setters now save prices, featured state and stock/visibility actions. Existing variable products are not forcibly converted to simple products.
2. **Kitchen ownership:** accept/prep/ready/out use `_ttos_kitchen_status`. WooCommerce payment status and transaction references are left alone. Explicit operator completion/cancellation still use WooCommerce CRUD. A cancellation is not advertised as a refund. Legacy custom-status records remain readable; no historical mass conversion was attempted.
3. **Ordering:** forced closure and temporary closure win over preorders; preorder-only stays scheduled-only; pause controls and checkout share the same setting; schedule comparisons use real timestamps; requested times must come from real service windows. Invalid API fields cannot partially overwrite other settings.
4. **CRM/reporting:** complete paginated order reads replace the 300/500 moving samples. Recorded payment evidence, refunds and currency are respected. Tax comes from WooCommerce records, not an assumed 20%. Unsupported hardcoded aggregator-saving estimates are removed. These remain creation-date order summaries, not provider settlement reports.
5. **Marketing:** both unsubscribe paths affect the sender's actual permission. Old opt-out records are respected; an explicit new permission edit can clear them. This is not a claim that the entire campaign/rewards engine has been finished or concurrency-tested.
6. **Handover:** administrator access no longer requires a second module key. The main dashboard redirect no longer intercepts `admin-post.php`, profiles or media. Normal WordPress permissions/nonce checks remain. Previously forced-off flags migrate once to ordinary off selections so an upgrade does not start external services unexpectedly. They can then be enabled normally.
7. **Packages:** Website, Ordering and Growth are explicit local module presets in Add-ons. They preserve external-service selections, commerce settings and data. They are not billing, licence enforcement or proof that every module in a sales package is validated.
8. **Exports and recovery:** accounting dates are validated; generated files use authenticated downloads instead of a blocked public uploads URL. Empty retries no longer generate misleading success chatter. Missing known follow-up schedules can recover without resetting their due date; deactivation removes only this plugin's own dispatch hooks.

## Test evidence before remote integration execution

- `php tests/backend/run.php`: **62 passed, 0 failed** locally.
- Comparable original-source subset: **2 passed, 22 failed across 24 checks**. Includes 650-order fixtures, broken pause/override, malformed settings and inconsistent unsubscribe behaviour.
- PHP syntax: **33 files passed** locally.
- Native and HTTP checks are defined in `.github/workflows/backend-checks.yml`. Record the actual completed run/commit before describing those as passed.

These are ordinary development checks. No new runtime evidence console, approval ritual, special licence receipt or payment lock was added.

## Codex verification and remaining backend work

- Run the new tests, inspect the exact diff and record actual WP/Woo/PHP versions. Native fixture tests create only disposable localhost records. Do not run the old `scripts/staging_takeaway_browser.mjs` against a client site; it contains historical machine paths and mutating test actions.
- Use a test copy for the real installed stack: HPOS, native payment provider callbacks, successful/declined/cancelled payments, delayed/repeated webhooks, native refunds and failed email delivery. Do not replace gateway logic to make a test pass.
- Check native product lookup synchronisation, tracked stock, sale expiry, taxes, options/extras and meal deals. Verify unchecked form fields really clear and partial CSV updates preserve omitted fields.
- Check kitchen stages through provider status updates, completion and cancellation; verify both payment status and kitchen state remain readable. Review old custom-status orders individually rather than rewriting their payment state.
- Exercise owner/kitchen interfaces, module selection, package presets, exports, nonce expiry and session recovery in an actual browser. WAVE/keyboard checks remain outstanding.
- Review CRM/report/kitchen performance with a realistic long order history. Pagination fixes correctness and per-query object size, but the current design still scans history and needs measurement before a large-volume claim.
- Complete or remove unsupported sales promises: provider-specific adapters, full OAuth callbacks, bookings/table ordering, broad driver/multi-location functions, batched campaigns and rewards lifecycle. A provider link is not a connection.
- Configure the demo's real business details, appropriate named timezone, mail and payment services. The checked live site still had Stripe disabled and no saved SMTP setup. Do not force Stripe on a business using a different supported gateway.
- Investigate the current `action_scheduler/migration_hook` missing-callback record using native WooCommerce tools. Most failed records were historical; do not call them failed payments or erase them blindly.
- After source review and successful relevant tests, use the ordinary release process. Preserve client settings, historical orders and rollback compatibility. No live update was performed here.

## Frontend boundary

The public design and Planner-style constrained visual editor remain later work. Status text and existing owner controls were adjusted only where needed for truthful backend behaviour. Do not start a new homepage while the outstanding backend/provider work is being represented as complete.

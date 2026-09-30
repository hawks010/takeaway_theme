# Backend continuation, 30 September 2026

## Current evidence

- PR #2 remains the source candidate on `fix/backend-foundations-20260930`, version `1.3.13-rc.1`.
- CI run `36739746707` passes both PHP 8.2 and 8.5 jobs. Its final source change only updated documentation; it does not establish a repair for the earlier intermittent runtime failures.
- The former `takeaway.thatdeveloper.co.uk` installation redirects to `https://takeaway.inkfire.dev`. Verified WordPress root: `/home/u363235284/domains/inkfire.dev/public_html/takeaway`.
- Current installation: WordPress 7.1.2, WooCommerce 11.1.2, Takeaway OS 1.3.12, Takeaway Theme 0.3.34. Hosting CLI PHP is 8.2.33. This does not by itself establish the HTTP PHP runtime.
- Read-only provider inspection: Stripe disabled, test mode selected, no configured test key pair in its settings. FluentSMTP has no configured connections.
- Business country is GB. A fixed UTC+1 timezone was corrected to `Europe/London` after a private database backup at `/home/u363235284/.takeaway-backups/20260930-backend-continuation/before-timezone.sql`. Verified summer UTC+1, winter UTC+0, and both occurrences of 01:30 at the October clock change.

## Executed locally

Disposable WordPress 7.1.2 / WooCommerce 11.1.2 / HPOS installation, PHP 8.5.1, MariaDB 12.3.2. No client database was copied. Fixture mail and external HTTP remain blocked by `tests/native/safety.php`.

- 63 isolated regressions passed.
- 41 native assertions passed.
- Normal owner login and 15 backend screens passed.
- Authenticated accounting CSV download passed; anonymous access was denied.
- Native classic checkout through the offline fixture gateway passed. Persisted payment status, transaction reference, collection metadata and total were verified.
- Ten fresh cart/checkout loads with Takeaway OS disabled and ten enabled passed. The final repeated comparison took 0.09-0.47 seconds per cart/checkout pair. Neither comparison reproduced the timeout or segmentation fault.

Passing repetitions do not establish the root cause or prove provider payments, mail delivery, browser interactions or accessibility. The clean fixture is not the complete hosting stack.

## Prepared changes

- `tests/native/runtime_probe.py` performs a guarded localhost-only disabled/enabled comparison without submitting orders. Both modes retain server logs and timings. An optional GDB run captures a native backtrace on a crash or `zend_timeout`; the ordinary 30-second execution limit stays in place.
- CI now runs the comparison on both PHP versions and includes its evidence in the native artifact. No product approval gate was added.
- HTTP smoke tests accept `TTOS_TEST_BASE_URL` for an alternate localhost port. Non-loopback targets remain rejected.
- The theme's bundled ZIP and manifest now contain the candidate, rather than 1.3.11. All 50 packaged files match their source hashes, ZIP integrity passes, and the package header/manifest agree on `1.3.13-rc.1`.
- Candidate ZIP SHA-256: `2cbb703053a805c539a59aeb9825745e2e42feda599e82b8cb512dd2a576886e`.

## Still open

The intermittent native runtime failure remains unresolved. CI run `36760915385` reproduced a disconnected checkout request on PHP 8.5.11 with Takeaway OS disabled, after one successful load. All ten enabled loads passed, and PHP 8.2 passed both modes plus full checkout. Push run `36760908454` passed both runtimes. This establishes that the failure does not require Takeaway OS to be active; it does not establish the exact cause or repair it. The first runner did not have GDB installed, so its log contains no backtrace. The workflow now explicitly installs GDB in the disposable runner for the next comparison.

With GDB installed, push run `36761524514` passed. PR run `36761526768` passed both comparison modes but its uninstrumented PHP 8.5 HTTP server subsequently segfaulted loading the owner Site Content screen (exit 139). The workflow now routes disposable core dumps to a known temporary path and captures postmortem backtraces without changing the server's normal execution. A debugger can affect reproducibility; instrumented passes must not erase the failed uninstrumented result.

## Existing storefront check

In the in-app browser, the current 1.3.12 storefront renders the homepage and menu, adds Fish and Chips to the basket, and loads classic checkout without submitting an order. The menu's collection switch changes only the displayed panel: after adding the item, it resets to delivery and checkout selects delivery. In `takeaway-theme/assets/js/theme.js`, `activateMode()` updates ARIA state and visibility only; it does not submit or persist the choice in the existing WooCommerce fulfilment session. This remains an existing-stack integration issue to fix and test with the candidate before client handoff. It is not covered by the clean fixture's checkout POST test.

Installation, existing-stack browser/WAVE checks, native sandbox gateway scenarios, transactional delivery, upgrade rehearsal, realistic-history performance and the other remaining handoff tasks are still open. No candidate deployment, real payment or customer mailout occurred during these local tests. See `BACKEND-TODO-20260930.md` for the complete work list.

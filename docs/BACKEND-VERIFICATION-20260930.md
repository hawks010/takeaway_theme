# Backend continuation, 30 September 2026

## Current evidence

- PR #2 remains the source candidate on `fix/backend-foundations-20260930`, version `1.3.13-rc.1`.
- CI run `36739746707` passes both PHP 8.2 and 8.5 jobs. Its final source change only updated documentation; it does not establish a repair for the earlier intermittent runtime failures.
- The former `takeaway.thatdeveloper.co.uk` installation redirects to `https://takeaway.inkfire.dev`. Verified WordPress root: `/home/u363235284/domains/inkfire.dev/public_html/takeaway`.
- Current installation: WordPress 7.1.2, WooCommerce 11.1.2, Takeaway OS 1.3.12, Takeaway Theme 0.3.34. Hosting CLI PHP is 8.2.33. This does not by itself establish the HTTP PHP runtime.
- Read-only provider inspection: Stripe disabled, test mode selected, no configured test key pair in its settings. FluentSMTP has no configured connections.
- Business country is GB. A fixed UTC+1 timezone was corrected to `Europe/London` after a private database backup at `/home/u363235284/.takeaway-backups/20260930-backend-continuation/before-timezone.sql`. Clock-change behaviour is being verified.

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

Installation, existing-stack browser/WAVE checks, native sandbox gateway scenarios, transactional delivery, upgrade rehearsal, realistic-history performance and the other remaining handoff tasks are still open. No candidate deployment, real payment or customer mailout occurred during these local tests. See `BACKEND-TODO-20260930.md` for the complete work list.

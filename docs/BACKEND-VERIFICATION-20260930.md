# Backend continuation, 30 September 2026

## Current evidence

- PR #2 remains the source candidate on `fix/backend-foundations-20260930`, version `1.3.13-rc.1`.
- CI run `36739746707` passes both PHP 8.2 and 8.5 jobs. Its final source change only updated documentation; it does not establish a repair for the earlier intermittent runtime failures.
- The former `takeaway.thatdeveloper.co.uk` installation redirects to `https://takeaway.inkfire.dev`. Verified WordPress root: `/home/u363235284/domains/inkfire.dev/public_html/takeaway`.
- Current installation: WordPress 7.1.2, WooCommerce 11.1.2, Takeaway OS 1.3.12, Takeaway Theme 0.3.34. Hosting CLI PHP is 8.2.33. A separate authenticated connector request confirms HTTP PHP 8.5.4, LiteSpeed SAPI, 512M memory and a 300-second hosting limit. Hosting JIT state has not been established or changed.
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
- CI runs the comparison on both PHP versions without a debugger and includes its evidence in the native artifact. It retains postmortem core-backtrace capture and makes OPcache/PHP JIT/PCRE settings explicit. No product approval gate was added.
- `tests/native/jit_probe.py` repeats the unchanged owner/export/native checkout scenarios, verifies the resulting orders, and records the actual HTTP SAPI, OPcache and JIT state. The fixture-only runtime helper is guarded by localhost, local environment and `TTOS_FIXTURE_ONLY`; it is not packaged.
- HTTP smoke tests accept `TTOS_TEST_BASE_URL` for an alternate localhost port. Non-loopback targets remain rejected.
- The theme's bundled ZIP and manifest now contain the candidate, rather than 1.3.11. All 50 packaged files match their source hashes, ZIP integrity passes, and the package header/manifest agree on `1.3.13-rc.1`.
- Candidate ZIP SHA-256: `2cbb703053a805c539a59aeb9825745e2e42feda599e82b8cb512dd2a576886e`.

## Runtime diagnosis

CI run `36760915385` reproduced a disconnected checkout request on PHP 8.5.11 with Takeaway OS disabled, after one successful load. All ten enabled loads passed, and PHP 8.2 passed both modes plus full checkout. Push run `36760908454` passed both runtimes. The failure does not require Takeaway OS to be active.

With GDB installed, push run `36761524514` passed. PR run `36761526768` passed both comparison modes but its uninstrumented PHP 8.5 HTTP server subsequently segfaulted loading the owner Site Content screen (exit 139). Runs `36762769025` and `36762775354` also failed with native segmentation faults; their captured backtraces initially lacked symbols. A debugger can affect reproducibility, so instrumented passes did not erase the failed uninstrumented results.

Controlled comparisons in push run [36765149188](https://github.com/hawks010/takeaway_theme/actions/runs/36765149188) and PR run [36765155625](https://github.com/hawks010/takeaway_theme/actions/runs/36765155625) used identical source, fixture and full HTTP scenarios:

| PHP 8.5.11 HTTP runtime | Combined full runs | Result |
| --- | --- | --- |
| Runner default: PHP function JIT `1235`, OPcache and PCRE JIT on | 6 | 4 passed, 2 disconnected/crashed |
| PHP JIT disabled, OPcache and PCRE JIT still on | 6 | 6 passed, including persisted checkout verification |
| PCRE JIT disabled, PHP function JIT still on | 6 | 4 passed, 2 disconnected/crashed |

HTTP probes confirmed that `opcache.enable_cli=0` did **not** disable OPcache or PHP JIT for `cli-server`. PHP's [SAPI check](https://github.com/php/php-src/blob/PHP-8.5/ext/opcache/ZendAccelerator.c) explains this distinction. Backtraces retain memory mappings and disassembly; the crashing PHP-binary stack includes a return address in the OPcache shared mapping. These results isolate the reproducible failure to the tested function-JIT configuration, not a repaired application path. The exact upstream C defect remains unidentified; the earlier timeout was not independently reduced to a minimal reproducer.

CI now explicitly uses `opcache.enable=1`, `opcache.jit=disable`, `pcre.jit=1`, and the unchanged 30-second limit. All isolated/native tests and the original full HTTP scenario remain; PHP 8.5 additionally repeats the full scenario ten times. No WordPress/WooCommerce/PHP engine files or hosting PHP settings were changed.

Final code commit `cbcb73bdd75a075f95eb6eb6a20a3cc5fcf0caf0`: push [36765963874](https://github.com/hawks010/takeaway_theme/actions/runs/36765963874) and PR [36765967557](https://github.com/hawks010/takeaway_theme/actions/runs/36765967557) both pass all four jobs. Each runtime passes 63 isolated tests, 41 native assertions, the disabled/enabled checkout comparison and the original full HTTP flow. The PHP 8.5 artifacts additionally confirm **20/20** repeated complete owner/export/checkout flows across the two runners, with OPcache on, PCRE JIT on, PHP JIT off and a 30-second limit. Persisted payment status/reference, collection metadata and total passed after every successful fixture checkout. This stabilizes the verified CI configuration; it does not establish support for opt-in function JIT or prove the hosting stack/provider integrations.

For a disposable fixture only, the opt-in failing configuration can still be compared without editing the product:

```sh
python3 tests/native/jit_probe.py --wp-path=/tmp/ttos-wp --wp-cli=/tmp/wp-cli.phar --modes php-jit-on php-jit-off --repeats=10 --evidence-dir=/tmp/ttos-jit-reproduction
```

## Existing storefront check

In the in-app browser, the current 1.3.12 storefront renders the homepage and menu, adds Fish and Chips to the basket, and loads classic checkout without submitting an order. The menu's collection switch changes only the displayed panel: after adding the item, it resets to delivery and checkout selects delivery. In `takeaway-theme/assets/js/theme.js`, `activateMode()` updates ARIA state and visibility only; it does not submit or persist the choice in the existing WooCommerce fulfilment session. This remains an existing-stack integration issue to fix and test with the candidate before client handoff. It is not covered by the clean fixture's checkout POST test.

## Read-only maintenance review

- Action Scheduler 4.0.0 reports 77 failed actions: 29 legacy draft-order cleanup, 14 migration, 33 database-update callbacks and one database-version completion. No failed `ttos_` hook was present. Twelve pending actions were the ordinary WooCommerce, Stripe, Rank Math and scheduler tasks at inspection. Counts are snapshots, not a permanent health guarantee.
- The most recent failed migration (`5540`) and legacy database completion (`1706`) explicitly report no registered callback. WooCommerce's database version is `11.1.2`. No actions were run, retried, deleted or rescheduled. Retention/cleanup policy still needs an explicit decision using normal scheduler tools.
- `uploads/ttos-exports` contains two old 335-byte CSV files plus protection files. Read-only public HEAD checks of **both** CSV URLs returned HTTP 403. No CSV body was opened and no files were deleted. Candidate exports use temporary authenticated streams; upgrading will not automatically remove these legacy files.

Installation, existing-stack browser/WAVE checks, native sandbox gateway scenarios, transactional delivery, upgrade rehearsal, realistic-history performance and the other remaining handoff tasks are still open. No candidate deployment, real payment or customer mailout occurred during these local tests. See `BACKEND-TODO-20260930.md` for the complete work list.

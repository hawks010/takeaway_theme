# Systems Check — 2026-07-02

Focus: startup flow + multi-business repeatability (internal agency product, £800–10k installs).
Scope: live staging (takeaway.thatdeveloper.co.uk) + local workspace. Theme 0.3.34 / Plugin 1.3.11.

## Overall call

**Architecture: GO.** The startup flow is genuinely agency-grade — client intake magic links, one-click
WooCommerce profile, cuisine starter packs, 69-point Setup Health gate, one-zip bundled install.
**First paid install: NOT YET** — blocked on deploying local layout changes, one full staging order QA
(Go Live checklist is 0/7), one contrast bug, and a documented per-install runbook.

## Verified this pass (live)

| Check | Result |
|---|---|
| Setup Health (WP-CLI, live) | **69 pass / 0 warn / 0 fail, core_ready=true** |
| Frontend smoke (/, /menu/, /basket/, /checkout/) | All 200 |
| PHP error log | 0 web-traffic fatals (24 logged fatals are all WP-CLI eval probes from dev sessions) |
| Plugin bootstrap | Loads clean, no dangling references |
| Launchpad (browser, as temp admin) | Renders: intake-first panel, 8-step wizard, health card, one-click profile apply |
| Go Live page (browser) | Renders: 7-item human checklist (0/7 ticked), critical system check |
| Client Intake admin (browser) | Renders: create request, magic-link email copy, request list |
| jQuery Migrate warning | **Not reproducible** — zero migrateWarnings on admin + homepage (jQuery 3.7.1). Close unless it reappears; if so, capture the script URL from the live stack first. |
| Release packaging | `takeaway-os-v1.3.11.zip` + `takeaway-theme-v0.3.34-bundled.zip` built; theme bundles the plugin (one-zip install) |
| Temp admin cleanup | `claude-qa` created for the pass, deleted after. (`claude-admin` no longer exists.) |

## Startup flow — how a new business gets set up

1. Fresh WP install → upload `takeaway-theme-vX-bundled.zip` → bundled installer offers Takeaway OS plugin.
2. Activation: roles (owner/manager/kitchen/driver), settings seed, page creation (marker-safe), redirect to Launchpad.
3. **Launchpad step 0 (intake-first):** enter business name + client email → secure magic link (hashed token,
   TTL) emailed to client → client submits branding, menu files, hours, business details **without touching WP**.
4. One-click **"Apply Takeaway WooCommerce profile"**: GBP/UK, guest checkout, COD, flat-rate delivery zone,
   starter categories, page assignments, menus — and suppresses Woo onboarding noise.
5. Steps 2–7: business → branding → delivery → menu (CSV import or cuisine starter pack) → pages → payments.
6. **Setup Health** (69 checks) as machine gate — includes WCAG AA contrast on brand colours, placeholder/demo
   detection, duplicate-page detection, staging-admin detection, Elementor hijack detection.
7. **Go Live** 7-item human checklist (test order, emails, payments, legal, allergens, SSL, backup).

Repeatability primitives that make this scale: starter content is meta-tagged
(`_ttos_generated_by=cuisine_starter_pack`) so it can be found/replaced per client; intake resets "for the
next build"; `uninstall.php` cleans up; Setup Health understands staging context (Stripe/SMTP "intentionally
deferred" rather than failing).

**Estimated hands-on time per £800-tier install once the runbook exists: ~2–4 h** (excluding client
content wait), dominated by menu entry and branding polish — both already mitigated (CSV import, starter packs, intake).

## Findings (by priority)

1. **CRITICAL — process, already materialised:** work built directly on staging was wiped by the
   `rsync --delete` deploy (a prior session's staging-only React wizard/dashboard/REST layer is gone).
   No production loss — it was superseded by the native Launchpad architecture — but the rule is now
   recorded in memory: **all work happens in the local workspace, staging is disposable.**
2. **HIGH — undeployed drift:** local is ahead of staging by 11 files (hero/header/footer layout variants,
   3 plugin files). Deploy + browser-verify the A/B/C hero, A/B header, A/B footer variants.
3. **HIGH — visible bug:** hero secondary CTA (`.tt-home-hero .tt-btn.ghost`, home.css:390) is white
   text/white-tint background — invisible on the light editorial hero. Scope the ghost style to
   photo/dark hero variants, give light variants an outlined dark style. Present in local CSS too — not
   fixed by the pending deploy.
4. **HIGH — unexercised gate:** Go Live checklist 0/7; no test order placed this pass. Run the full order
   QA (collection, delivery, extras, minimum-order edge, closed/preorder) before calling it provable.
5. **MEDIUM — plugin bloat on staging:** Elementor, LiteSpeed, AAM, Rank Math (+Pro), Hostinger, Foundation
   all active; 4 plugin updates pending. Fine for staging, but the runbook must define the **canonical
   minimal plugin set** per client install — third-party admin nags (Elementor "Ally" popup) will otherwise
   appear in every client's Launchpad, which reads badly at £10k.
6. **MEDIUM — missing artefact:** no per-install runbook doc. The flow is good enough that a 1-page
   checklist (fresh WP → zip → intake → profile → menu → payments → health → go-live) makes any team
   member able to deliver an install. Recommend `docs/INSTALL-RUNBOOK.md`.
7. **LOW — no cross-site settings blueprint:** branding/settings can't be exported from one client site and
   imported into another (menu CSV and CRM export exist; `ttos_settings`/Site Content export doesn't).
   Worth adding for the multi-site agency workflow at higher volume.
8. **LOW — Git hygiene:** tracked generated artefacts still need an index-cleanup pass (already on the
   existing report's list).

## Recommended order of work

1. Fix the ghost-CTA contrast bug locally (small, blocks visual QA).
2. Deploy local → staging (rsync), flush cache, re-run Setup Health.
3. Browser pass on hero/header/footer variants + full order QA; tick Go Live checklist honestly.
4. Write `docs/INSTALL-RUNBOOK.md` with the canonical plugin set.
5. Git index cleanup pass.
6. Then: first paid install candidate.

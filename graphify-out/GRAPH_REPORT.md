# Graph Report - Takeaway theme  (2026-06-16)

## Corpus Check
- 85 files · ~2,252,126 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1067 nodes · 1237 edges · 93 communities (69 shown, 24 thin omitted)
- Extraction: 87% EXTRACTED · 13% INFERRED · 0% AMBIGUOUS · INFERRED: 157 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `d90e9769`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 2|Community 2]]
- [[_COMMUNITY_Community 3|Community 3]]
- [[_COMMUNITY_Community 4|Community 4]]
- [[_COMMUNITY_Community 5|Community 5]]
- [[_COMMUNITY_Community 6|Community 6]]
- [[_COMMUNITY_Community 7|Community 7]]
- [[_COMMUNITY_Community 8|Community 8]]
- [[_COMMUNITY_Community 9|Community 9]]
- [[_COMMUNITY_Community 10|Community 10]]
- [[_COMMUNITY_Community 11|Community 11]]
- [[_COMMUNITY_Community 12|Community 12]]
- [[_COMMUNITY_Community 13|Community 13]]
- [[_COMMUNITY_Community 14|Community 14]]
- [[_COMMUNITY_Community 15|Community 15]]
- [[_COMMUNITY_Community 16|Community 16]]
- [[_COMMUNITY_Community 17|Community 17]]
- [[_COMMUNITY_Community 18|Community 18]]
- [[_COMMUNITY_Community 19|Community 19]]
- [[_COMMUNITY_Community 20|Community 20]]
- [[_COMMUNITY_Community 21|Community 21]]
- [[_COMMUNITY_Community 22|Community 22]]
- [[_COMMUNITY_Community 23|Community 23]]
- [[_COMMUNITY_Community 24|Community 24]]
- [[_COMMUNITY_Community 25|Community 25]]
- [[_COMMUNITY_Community 26|Community 26]]
- [[_COMMUNITY_Community 27|Community 27]]
- [[_COMMUNITY_Community 28|Community 28]]
- [[_COMMUNITY_Community 29|Community 29]]
- [[_COMMUNITY_Community 30|Community 30]]
- [[_COMMUNITY_Community 31|Community 31]]
- [[_COMMUNITY_Community 32|Community 32]]
- [[_COMMUNITY_Community 33|Community 33]]
- [[_COMMUNITY_Community 34|Community 34]]
- [[_COMMUNITY_Community 35|Community 35]]
- [[_COMMUNITY_Community 36|Community 36]]
- [[_COMMUNITY_Community 38|Community 38]]
- [[_COMMUNITY_Community 39|Community 39]]
- [[_COMMUNITY_Community 40|Community 40]]
- [[_COMMUNITY_Community 41|Community 41]]
- [[_COMMUNITY_Community 42|Community 42]]
- [[_COMMUNITY_Community 43|Community 43]]
- [[_COMMUNITY_Community 46|Community 46]]
- [[_COMMUNITY_Community 48|Community 48]]
- [[_COMMUNITY_Community 73|Community 73]]
- [[_COMMUNITY_Community 74|Community 74]]
- [[_COMMUNITY_Community 75|Community 75]]
- [[_COMMUNITY_Community 76|Community 76]]
- [[_COMMUNITY_Community 77|Community 77]]
- [[_COMMUNITY_Community 78|Community 78]]
- [[_COMMUNITY_Community 79|Community 79]]
- [[_COMMUNITY_Community 80|Community 80]]
- [[_COMMUNITY_Community 81|Community 81]]
- [[_COMMUNITY_Community 82|Community 82]]
- [[_COMMUNITY_Community 83|Community 83]]
- [[_COMMUNITY_Community 84|Community 84]]
- [[_COMMUNITY_Community 85|Community 85]]
- [[_COMMUNITY_Community 86|Community 86]]
- [[_COMMUNITY_Community 87|Community 87]]

## God Nodes (most connected - your core abstractions)
1. `TTOS_Features` - 88 edges
2. `TTOS_Admin` - 84 edges
3. `TTOS_WooCommerce` - 77 edges
4. `TTOS_Site_Content` - 68 edges
5. `TTOS_Settings` - 58 edges
6. `TTOS_Setup_Health` - 49 edges
7. `TTOS_Operations` - 46 edges
8. `TTOS_Production` - 36 edges
9. `TTOS_Page_Manager` - 33 edges
10. `Takeaway OS Progress` - 27 edges

## Surprising Connections (you probably didn't know these)
- `tt_trading()` --calls--> `TTOS_Settings`  [INFERRED]
  takeaway-theme/inc/template-helpers.php → takeaway-os/includes/class-settings.php
- `tt_content()` --calls--> `ttos_get_site_content_value()`  [INFERRED]
  takeaway-theme/inc/template-helpers.php → takeaway-os/includes/class-site-content.php
- `ttheme_account_allergen_options()` --calls--> `TTOS_WooCommerce`  [INFERRED]
  takeaway-theme/inc/account.php → takeaway-os/includes/class-woocommerce.php
- `tt_hours_summary()` --calls--> `ttos_get_opening_hours()`  [INFERRED]
  takeaway-theme/inc/template-helpers.php → takeaway-os/includes/class-site-content.php
- `tt_open_status()` --calls--> `ttos_get_opening_hours()`  [INFERRED]
  takeaway-theme/inc/template-helpers.php → takeaway-os/includes/class-site-content.php

## Import Cycles
- None detected.

## Communities (93 total, 24 thin omitted)

### Community 10 - "Community 10"
Cohesion: 0.14
Nodes (21): cleanMessage(), dependenciesReady(), dependencyReady(), esc(), fieldName(), installRow(), orderFilters(), panelParts() (+13 more)

### Community 11 - "Community 11"
Cohesion: 0.08
Nodes (27): ttheme_account_allergen_options(), ttheme_account_booking_details(), ttheme_account_booking_enabled(), ttheme_account_handle_actions(), tt_contact_form_html(), ttheme_home_hero_image_id(), ttheme_preload_home_hero_image(), tt_active_offers() (+19 more)

### Community 13 - "Community 13"
Cohesion: 0.24
Nodes (21): ttheme_admin_assets(), ttheme_bundled_manifest(), ttheme_bundled_takeaway_os_zip(), ttheme_get_plugins(), ttheme_global_setup_modal(), ttheme_install_bundled_takeaway_os(), ttheme_is_setup_screen(), ttheme_plugin_action() (+13 more)

### Community 15 - "Community 15"
Cohesion: 0.11
Nodes (17): 2026-06-10 v1.3.0 Pre-Build Audit (read-only), Backup Paths, Browser Test Results, Browser Tests Run, Current Version, Environment Details, Files Changed / Deployed, Final Pass / Fail Table (+9 more)

### Community 16 - "Community 16"
Cohesion: 0.13
Nodes (14): Admin locked out recovery, Bundled plugin ZIP missing, Checkout blank, Email plugin active but warning remains, Header / footer missing, Homepage not assigned, Menu empty, Pages blank (+6 more)

### Community 17 - "Community 17"
Cohesion: 0.14
Nodes (14): Backup Paths, Browser Smoke Test, Files Changed / Deployed, Handover Role Checks, Local Release Checks, Payment / Email Checks, Recommendation, Remaining Risks / Blockers (+6 more)

### Community 18 - "Community 18"
Cohesion: 0.19
Nodes (6): closePopup(), focusables(), markSeen(), money(), openPopup(), updateFormPrice()

### Community 19 - "Community 19"
Cohesion: 0.29
Nodes (10): captureAdminScreens(), captureFrontendAndPlaceOrder(), collectSetupHealth(), getShopUrl(), needsRepair(), needsStarterContent(), openThemeSetupAndUpdate(), runSetupHealthActionsIfNeeded() (+2 more)

### Community 20 - "Community 20"
Cohesion: 0.17
Nodes (12): 2026-06-10 Phase 4 (combined) — Complete public front end (os 1.3.0-dev.3 / theme 0.3.0-dev.3), Backup path (created before deploy), Known issues / notes, Page generation (explicit safe repair, no overwrites), Plugin changes (takeaway-os 1.3.0-dev.3), Scope guardrails honoured, Screenshots, Staging identity fix (+4 more)

### Community 22 - "Community 22"
Cohesion: 0.18
Nodes (11): 2026-06-10 Phase 2 — Site Content CRM foundation (v1.3.0-dev.2), Backup path (created before deploy), Files changed, Known issues / notes, Migration behaviour (verified), Option structure (`ttos_site_content`), Sanitisation strategy, Scope guardrails honoured (+3 more)

### Community 23 - "Community 23"
Cohesion: 0.18
Nodes (10): Admin, Branding design tokens, Front-end systems, Known limitations, New in v1.3.0, Pages, Setup Health (51 checks), Site Content CRM (Takeaway OS -> Site Content) (+2 more)

### Community 24 - "Community 24"
Cohesion: 0.20
Nodes (9): Browser / Screenshot Checklist, CRM / Reports Test, Kitchen / Order Test, Payment / Email Beta Checks, Plugin Update Test, Production Gating, Smoke Checklist, Takeaway Testing (+1 more)

### Community 25 - "Community 25"
Cohesion: 0.20
Nodes (9): 1.1 Codebase shape, 1.2 Live staging defects (browser-verified, with root causes), 1.2b Admin screens (browser-verified as `claude-admin`, 2026-06-10), 1.3 What's working and must not break, Part 1 — What exploration found, Part 2 — Architecture decisions for v1.3.0 / v0.3.0, Part 3 — Implementation phases, Part 4 — Risks & guardrails (+1 more)

### Community 27 - "Community 27"
Cohesion: 0.20
Nodes (10): 2026-06-10 Phase 1 — Design tokens & branding foundation (v1.3.0-dev.1 / v0.3.0-dev.1), Backup path (created before deploy), Files changed, Known issues / notes, Migration verified on staging, Scope guardrails honoured, Screenshots captured, Status (+2 more)

### Community 28 - "Community 28"
Cohesion: 0.20
Nodes (10): 2026-06-10 Phase 3 — Public shell & homepage (theme v0.3.0-dev.2), Backup path (created before deploy), Compatibility fixes landed, Files changed (theme only this phase), Homepage sections & fallback rules (verified live), Known issues / notes, Scope guardrails honoured, Screenshots (+2 more)

### Community 29 - "Community 29"
Cohesion: 0.20
Nodes (10): Additional bug found during admin verification, Browser / network / log findings, Checkout Submission Debug / v1.2.4, Current staging state before this pass, Fix applied, Orders created during checkout debugging, Post-fix test evidence, Reproduction evidence before the fix (+2 more)

### Community 30 - "Community 30"
Cohesion: 0.22
Nodes (9): 2026-06-10 Phase 5 (combined) — Product systems polish (os 1.3.0-dev.4), Admin polish (restrained), Backup path (created before deploy), Banner + popup public rendering (plugin), Scope guardrails honoured, Screenshots, Setup Health additions (5 new checks → 51 total), Status (+1 more)

### Community 31 - "Community 31"
Cohesion: 0.25
Nodes (8): 2026-06-10 Phase 0 — Staging Cleanup (Elementor theme-builder hijack removal), Accounts, Backup paths (created before any change), Cleanup actions, Git baseline (local workspace), Observations logged for later phases (no action taken), Scope guardrails honoured, Verification results

### Community 32 - "Community 32"
Cohesion: 0.29
Nodes (6): takeaway-os, main_file, name, package, slug, version

### Community 33 - "Community 33"
Cohesion: 0.29
Nodes (6): Add-on Locking, Before First Client Beta, Data Retention / Uninstall, Handover Mode, Recommended Access Split, Takeaway Handover

### Community 34 - "Community 34"
Cohesion: 0.40
Nodes (4): After install/update checklist, Takeaway Install, Update from 1.2.5, v1.3.0 notes

### Community 35 - "Community 35"
Cohesion: 0.40
Nodes (4): Bundled plugin, Developer customisation, New in v0.3.0, Takeaway Theme v0.3.0 (bundled)

### Community 36 - "Community 36"
Cohesion: 0.67
Nodes (3): ttos_get_business_type(), ttos_get_site_content(), ttos_get_site_content_value()

### Community 73 - "Community 73"
Cohesion: 0.06
Nodes (31): 0. Release Roadmap, 10. My Account Integration, 11. Email System, 12. WooCommerce Connections (v1.4.0), 13. File Structure (new files), 14. Out-of-Box Defaults, 15. Version Target, 1. Scope (+23 more)

### Community 74 - "Community 74"
Cohesion: 0.07
Nodes (26): 1. SMTP — order emails will not send, 2. Stripe — payment gateway not live, 3. Hero content empty, 4. No social links, 5. Logo & Favicon, 6. WCAG AA contrast — primary colour, 7. Form input border contrast, Blockers Before Client Handover (+18 more)

### Community 75 - "Community 75"
Cohesion: 0.10
Nodes (19): File Map, Phase 4 — Public Front End, Phase 5 — Product Systems Polish, Phase 6 — Final QA + Packaging, Plugin — `takeaway-os/`, Self-Review Notes, Task 10: Version Bump + Final Commit, Task 11: Package ZIPs (+11 more)

### Community 76 - "Community 76"
Cohesion: 0.12
Nodes (17): Admin QA (Step 9), Backup Taken, Banner and Popup QA (Step 7), Code Fix Applied, Critical Check (Step 2), Duplicate Page Review (Step 4), Elementor Hijack Repair (Step 5), Public Page QA (Step 6) (+9 more)

### Community 77 - "Community 77"
Cohesion: 0.33
Nodes (6): Backup, Deployed via rsync, Guardrails, Pending verification (do on staging in browser), Smoke test results, v1.3.0 Deploy — 2026-06-15

### Community 78 - "Community 78"
Cohesion: 0.11
Nodes (18): 10. Compliance / Readiness Map, 11. Release Blockers (current, as of this audit), 12. Future Roadmap Items (explicitly out of scope — do not build), 13. Suggested Worker Assignments, 14. Implementation Tickets, 15. Acceptance Criteria (for this audit pass), 1. Current System Map, 2. Files / Classes by Responsibility (+10 more)

### Community 81 - "Community 81"
Cohesion: 0.17
Nodes (11): 1. Business logic placement: PASS, 2. WooCommerce template overrides: PASS (zero exist), 3. Order data access: PASS (HPOS-safe, no meta hacks), 4. Raw database queries: ONE found, benign, 5. CRM duplication: PASS (intentionally separate, not duplicated), 6. Maintainability: WATCH (not a release blocker), 7. Update-safety (Page Manager content): PASS, 8. Code smells scan: no blockers found (+3 more)

### Community 82 - "Community 82"
Cohesion: 0.20
Nodes (9): Basket preview (empty state), Design / UI Review — Worker 5 (Audit), Empty / placeholder states, Homepage, Menu page, Not yet visually confirmed (defer to Worker 7 / Release QA), Summary, Token / Consistency Check (source-level, not just visual) (+1 more)

### Community 83 - "Community 83"
Cohesion: 0.22
Nodes (8): 1. Utility bar contact links not centered — FIXED, 2. Collect/Delivery toggle did nothing — FIXED, 3. Toggle's active pill had no visual highlight — FIXED, 4. "Proceed to checkout" button rendered WooCommerce's default purple — FIXED, 5. Orders cockpit header had a "massive gap" between title and controls — FIXED, Live Testing Addendum — Bugs Found by Direct User Interaction, Not independently re-verified, Out of scope, logged separately

### Community 84 - "Community 84"
Cohesion: 0.22
Nodes (8): AJAX Handlers — full inventory, all four read in full, Capability Model, Honeypot / Rate-Limiting Coverage, Outstanding Item (carried, not a code defect), Secrets / Credentials Sweep, Security Review — Worker 3 (Audit), Summary, Uninstall / Data-Retention Safety — read in full (`uninstall.php`)

### Community 85 - "Community 85"
Cohesion: 0.32
Nodes (4): ttheme_body_classes(), ttheme_brand(), ttheme_fallback_nav(), ttheme_page_url()

### Community 86 - "Community 86"
Cohesion: 0.25
Nodes (7): Checkout Terms Visibility — Not Re-Verified This Session, Compliance / Readiness Register — Worker 6 (Audit), Do Later (v1.4+), Do Not Build Yet, Do Now (v1.3.x — already built, confirmed by source read), Gap — Needs a Decision, Not Yet Built, Summary

### Community 87 - "Community 87"
Cohesion: 0.29
Nodes (6): Accessibility Review — Worker 4 (Audit), Contrast (already fixed this session — confirmed, not re-litigated here), Keyboard / Focus / ARIA — component-by-component (read in full from `theme.js`), Other Findings, Summary, Why this matters specifically for the basket preview

## Knowledge Gaps
- **290 isolated node(s):** `summary`, `WP_Query`, `WP_Query`, `name`, `slug` (+285 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **24 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `TTOS_WooCommerce` connect `Community 4` to `Community 1`, `Community 2`, `Community 3`, `Community 5`, `Community 6`, `Community 8`, `Community 9`, `Community 11`, `Community 12`, `Community 46`, `Community 79`, `Community 14`?**
  _High betweenness centrality (0.105) - this node is a cross-community bridge._
- **Why does `TTOS_Settings` connect `Community 8` to `Community 3`, `Community 5`, `Community 6`, `Community 7`, `Community 11`, `Community 14`, `Community 79`, `Community 21`?**
  _High betweenness centrality (0.097) - this node is a cross-community bridge._
- **Why does `TTOS_Features` connect `Community 0` to `Community 1`, `Community 4`, `Community 5`, `Community 7`, `Community 8`, `Community 79`, `Community 47`?**
  _High betweenness centrality (0.081) - this node is a cross-community bridge._
- **Are the 5 inferred relationships involving `TTOS_Features` (e.g. with `.apply_order_action()` and `.customer_profile_panel()`) actually correct?**
  _`TTOS_Features` has 5 INFERRED edges - model-reasoned connections that need verification._
- **Are the 44 inferred relationships involving `TTOS_WooCommerce` (e.g. with `ttheme_account_allergen_options()` and `.ajax_order_action()`) actually correct?**
  _`TTOS_WooCommerce` has 44 INFERRED edges - model-reasoned connections that need verification._
- **Are the 11 inferred relationships involving `TTOS_Site_Content` (e.g. with `.migrate_site_content()` and `.config()`) actually correct?**
  _`TTOS_Site_Content` has 11 INFERRED edges - model-reasoned connections that need verification._
- **Are the 43 inferred relationships involving `TTOS_Settings` (e.g. with `tt_trading()` and `.migrate_branding_tokens()`) actually correct?**
  _`TTOS_Settings` has 43 INFERRED edges - model-reasoned connections that need verification._
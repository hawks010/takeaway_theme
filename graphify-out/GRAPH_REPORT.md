# Graph Report - Takeaway theme  (2026-06-14)

## Corpus Check
- 65 files · ~836,690 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 817 nodes · 981 edges · 73 communities (51 shown, 22 thin omitted)
- Extraction: 86% EXTRACTED · 14% INFERRED · 0% AMBIGUOUS · INFERRED: 142 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `93456e85`
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

## God Nodes (most connected - your core abstractions)
1. `TTOS_Features` - 88 edges
2. `TTOS_Admin` - 82 edges
3. `TTOS_Site_Content` - 65 edges
4. `TTOS_WooCommerce` - 65 edges
5. `TTOS_Settings` - 51 edges
6. `TTOS_Setup_Health` - 47 edges
7. `TTOS_Operations` - 46 edges
8. `TTOS_Production` - 36 edges
9. `TTOS_Page_Manager` - 33 edges
10. `TTOS_Plugin_Checker` - 25 edges

## Surprising Connections (you probably didn't know these)
- `tt_trading()` --calls--> `TTOS_Settings`  [INFERRED]
  takeaway-theme/inc/template-helpers.php → takeaway-os/includes/class-settings.php
- `tt_content()` --calls--> `ttos_get_site_content_value()`  [INFERRED]
  takeaway-theme/inc/template-helpers.php → takeaway-os/includes/class-site-content.php
- `tt_hours_summary()` --calls--> `ttos_get_opening_hours()`  [INFERRED]
  takeaway-theme/inc/template-helpers.php → takeaway-os/includes/class-site-content.php
- `tt_open_status()` --calls--> `ttos_get_opening_hours()`  [INFERRED]
  takeaway-theme/inc/template-helpers.php → takeaway-os/includes/class-site-content.php
- `tt_address_lines()` --calls--> `ttheme_business()`  [INFERRED]
  takeaway-theme/inc/template-helpers.php → takeaway-theme/functions.php

## Import Cycles
- None detected.

## Communities (73 total, 22 thin omitted)

### Community 10 - "Community 10"
Cohesion: 0.14
Nodes (21): cleanMessage(), dependenciesReady(), dependencyReady(), esc(), fieldName(), installRow(), orderFilters(), panelParts() (+13 more)

### Community 11 - "Community 11"
Cohesion: 0.12
Nodes (19): tt_active_offers(), tt_address_lines(), tt_business_name(), tt_content(), tt_cta_url(), tt_email(), tt_hours_summary(), tt_menu_url() (+11 more)

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

## Knowledge Gaps
- **153 isolated node(s):** `summary`, `WP_Query`, `WP_Query`, `name`, `slug` (+148 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **22 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `TTOS_Settings` connect `Community 8` to `Community 3`, `Community 5`, `Community 6`, `Community 7`, `Community 11`, `Community 14`, `Community 21`?**
  _High betweenness centrality (0.144) - this node is a cross-community bridge._
- **Why does `TTOS_Features` connect `Community 0` to `Community 1`, `Community 4`, `Community 5`, `Community 7`, `Community 8`, `Community 47`?**
  _High betweenness centrality (0.104) - this node is a cross-community bridge._
- **Why does `TTOS_Site_Content` connect `Community 2` to `Community 36`, `Community 6`, `Community 8`, `Community 11`, `Community 14`, `Community 21`, `Community 26`?**
  _High betweenness centrality (0.102) - this node is a cross-community bridge._
- **Are the 5 inferred relationships involving `TTOS_Features` (e.g. with `.apply_order_action()` and `.customer_profile_panel()`) actually correct?**
  _`TTOS_Features` has 5 INFERRED edges - model-reasoned connections that need verification._
- **Are the 8 inferred relationships involving `TTOS_Site_Content` (e.g. with `.migrate_site_content()` and `.config()`) actually correct?**
  _`TTOS_Site_Content` has 8 INFERRED edges - model-reasoned connections that need verification._
- **Are the 43 inferred relationships involving `TTOS_WooCommerce` (e.g. with `.ajax_order_action()` and `.category_builder_panel()`) actually correct?**
  _`TTOS_WooCommerce` has 43 INFERRED edges - model-reasoned connections that need verification._
- **Are the 38 inferred relationships involving `TTOS_Settings` (e.g. with `tt_trading()` and `.migrate_branding_tokens()`) actually correct?**
  _`TTOS_Settings` has 38 INFERRED edges - model-reasoned connections that need verification._
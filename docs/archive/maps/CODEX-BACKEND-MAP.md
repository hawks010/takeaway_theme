# Takeaway OS Backend Map

Updated: 2026-06-17

## Plugin Bootstrap

`takeaway-os/takeaway-os.php` is the single bootstrap. It loads the class list, registers activation/deactivation hooks, declares Woo HPOS compatibility, then calls `hooks()` on the major classes during `plugins_loaded`.

## Major Class Map

| Class | Purpose | Key methods | Main hooks / outputs | Data / risk | Status |
| --- | --- | --- | --- | --- | --- |
| `TTOS_Activator` | Activation, upgrades, roles, page creation, migrations | `activate()`, `maybe_upgrade()`, `add_roles()`, `create_pages()` | activation/deactivation hooks | Writes version option, roles, seeded settings/pages | PASS |
| `TTOS_Admin_Shell` | Shared admin chrome and nav | `primary_items()`, `render_start()`, `render_primary_nav()`, `render_secondary_nav()` | Admin shell renderer | Central nav truth now lives here | PASS |
| `TTOS_Admin` | Main admin screens and most operational POST handling | `menu()`, `handle_posts()`, `page_dashboard()`, `page_menu_builder()`, `page_orders()`, `page_customers()`, `page_reports()`, AJAX handlers | Admin pages, notices, order board AJAX | Large surface; most likely regression hotspot | PASS |
| `TTOS_Settings` | Core settings schema, token generation, body classes, site icon filter | `defaults()`, `get()`, `update_section()`, `brand_tokens()`, `print_brand_css()` | `wp_head`, `body_class`, `option_site_icon` | Central config source for business/branding/trading/modules | PASS |
| `TTOS_Site_Content` | Structured content CMS for homepage/menu/footer/policies/banner/popup | `hooks()`, `get()`, `update()`, `page()`, `migrate()` | Site Content admin screen | Stores structured content in single option | PASS |
| `TTOS_Page_Manager` | Generated page registry and repair behavior | `specs()`, `ensure()`, `ensure_all()`, `status()`, `sync_woocommerce_page_options()` | Page generation / repair | Safe marker-based page management | PASS |
| `TTOS_Shortcodes` | Public shortcode registration and menu rendering | `menu()`, `customer_portal()`, `order_tracker()`, `delivery_checker()`, `allergens()`, `contact()`, `policy()`, `kitchen_screen()` | Public shortcodes, global asset enqueue | Public ordering layer entry point | PASS |
| `TTOS_WooCommerce` | Woo integration, taxonomies, statuses, configurator, upsells, checkout extras | `register_order_statuses()`, `validate_selected_options()`, `render_modal_recommendations()`, `customer_product_history()` | Woo hooks across cart/checkout/order/admin | High business-critical surface | PASS |
| `TTOS_Operations` | Checkout flow settings, go-live, handover mode, logging | `defaults()`, `page_operations()`, `checkout_fields()`, `save_order_meta()`, `client_menu_lockdown()` | Admin operations/go-live screens, checkout fields | Launch governance and order meta layer | PASS |
| `TTOS_Setup_Health` | Setup checks and repair tooling | `menu()`, `page()`, `checks()`, action handlers | Setup Health screen | Good audit layer; staging currently 80% complete | PASS |
| `TTOS_Features` | Paid/optional features and pro modules | `page_features()`, meal deal / loyalty / integration handlers, account endpoint | Features admin screen, shortcodes, Woo hooks | Real settings layer; some modules still roadmap-only operationally | PARTIAL |
| `TTOS_Production` | Menu import/export, starter menu tools, production utilities | `page_production()`, `apply_starter_menu()`, exports | Production tools admin | Real tooling, but starter menu is generic | PARTIAL |
| `TTOS_Hardening` | Capability normalisation, module lock, system checks | `maybe_upgrade()`, `protect_module_settings()`, `system_checks()` | `admin_init` | Helps handover safety; not a licence server | PASS |
| `TTOS_Analytics` | Reports calculations and analytics helpers | hooks and report support methods | Admin reports | Useful, but depends on real order data depth | PASS |
| `TTOS_Public_UI` | Scheduled banner/popup renderer | `render_banner()`, `render_popup()` | `wp_body_open`, `wp_footer` | Safe schedule-aware public announcements | PASS |
| `TTOS_Onboarding` | Apply Woo/takeaway profile, starter category seeding | `apply_profile()`, `seed_terms()` | Launchpad/setup actions | Current logic is generic, not cuisine-aware | PARTIAL |

## Backend Truths That Matter

### Configurator And Upsells

- `TTOS_WooCommerce` validates option groups, stores item notes, applies extra pricing, saves order item meta, and auto-adds selected modal upsells as separate cart lines.
- Existing customer upsells are scored against recent Woo order history by billing email.
- New/unknown customer upsells fall back to popularity, add-on categories, cross-sells, upsells, and low-ticket bias.

Status: PASS

### Checkout / Order Flow

- `TTOS_Operations` adds fulfilment and requested-time fields into Woo checkout.
- It writes `_ttos_fulfilment_method` and `_ttos_requested_time` to orders.
- Admin order view, emails, and shipping labels are adjusted around this meta.

Status: PASS

### Admin Cockpit

- `TTOS_Admin` owns the dashboard, menu builder, order cockpit, kitchen screen, customer CRM, and reports pages.
- AJAX order board and order action handlers are capability-gated and nonce-gated.

Status: PASS

### Starter Menu / Onboarding

- `TTOS_Onboarding::apply_profile()` seeds categories and can invoke `TTOS_Production::apply_starter_menu()`.
- `TTOS_Production::apply_starter_menu()` still hard-codes a generic pizza/kebab/burger/drinks catalog.
- Cuisine currently does not branch this process.

Status: FAIL

### Packaging Layer

- Theme manifest and fallback metadata both point to `takeaway-os 1.3.11`.
- The local bundled plugin zip file itself is missing.

Status: FAIL

## Background / Non-UI Behavior

| Area | What exists | Status |
| --- | --- | --- |
| Cron / retries | `ttos_retry_integrations` scheduled every five minutes for feature-layer retries | PASS |
| Order logs | `ttos_operations_log` and integration logs retained in options | PASS |
| Customer history | Woo order history mined for CRM and upsell scoring | PASS |
| Role migration | Activation and hardening both normalise capabilities | PASS |
| Builder protection | Setup Health includes Elementor-disable / builder conflict checks | PASS |
| Licensing | Module lock exists as local protection, not full remote licensing | PARTIAL |

## Backend Risks

| Risk | Status | Notes |
| --- | --- | --- |
| Monolithic admin controller | RISK | `TTOS_Admin` carries a lot of unrelated post handlers and rendering |
| Generic starter seeding | FAIL | Biggest mismatch between marketing expectation and actual behavior |
| Missing packaged plugin zip | FAIL | Packaging cannot be trusted from local source alone |
| Optional feature flags vs fully finished flows | PARTIAL | Some modules have settings and UI before full production-grade delivery proof |

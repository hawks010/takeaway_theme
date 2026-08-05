# Takeaway OS Function, Hook, Shortcode And Endpoint Map

Updated: 2026-06-17

## Core Registration Map

| Type | Hook / endpoint | Callback | File / class | Purpose | Surface | Status |
| --- | --- | --- | --- | --- | --- | --- |
| activation | `register_activation_hook` | `TTOS_Activator::activate` | `takeaway-os/takeaway-os.php` | Seed roles, settings, pages, version | install | PASS |
| deactivation | `register_deactivation_hook` | `TTOS_Activator::deactivate` | `takeaway-os/takeaway-os.php` | Unschedule retries, flush rewrites | install | PASS |
| compat | `before_woocommerce_init` | anonymous callback | `takeaway-os/takeaway-os.php` | HPOS compatibility declaration | Woo init | PASS |
| bootstrap | `plugins_loaded` | anonymous callback invoking class `hooks()` | `takeaway-os/takeaway-os.php` | Main runtime wiring | mixed | PASS |

## Major WordPress / Woo Hook Usage

### Admin / Setup Layer

| Hook | Callback | Area | Purpose | Risk if broken | Status |
| --- | --- | --- | --- | --- | --- |
| `admin_menu` | `TTOS_Admin::menu` | admin | Registers core Takeaway OS screens | Entire admin cockpit navigation breaks | PASS |
| `admin_menu` | `TTOS_Features::menu` | admin | Registers Features screen | Feature builder hidden | PASS |
| `admin_menu` | `TTOS_Site_Content::menu` | admin | Registers Site Content | Structured content CMS hidden | PASS |
| `admin_menu` | `TTOS_Setup_Health::menu` | admin | Registers Setup Health | Health tooling hidden | PASS |
| `admin_menu` | `TTOS_Operations::menu` | admin | Registers Operations / Go Live | Ops and launch controls hidden | PASS |
| `admin_enqueue_scripts` | `TTOS_Admin::assets` | admin | Loads shared admin CSS/JS | Admin shell and cockpit tooling degrade | PASS |
| `in_admin_header` | `TTOS_Admin::suppress_foreign_notices` | admin | Keeps Takeaway screens uncluttered | UX noise, not data loss | PASS |
| `admin_head` | `TTOS_Admin::suppress_foreign_notices_css` | admin | Defensive notice hiding | UX noise | PASS |
| `admin_init` | `TTOS_Admin::handle_posts` | admin | Handles most core form submits | Settings/menu/orders may stop saving | PASS |
| `admin_init` | `TTOS_Features::handle_posts` | admin | Handles feature-layer forms | Feature settings break | PASS |
| `admin_init` | `TTOS_Operations::handle_posts` | admin | Saves checkout/handover/golive | Ops settings break | PASS |
| `admin_init` | `TTOS_Hardening::protect_module_settings` | admin | Prevents locked-module edits | Handover lock weakened | PASS |

### Frontend / Theme Layer

| Hook | Callback | Area | Purpose | Risk if broken | Status |
| --- | --- | --- | --- | --- | --- |
| `wp_enqueue_scripts` | `ttheme_enqueue` | frontend | Loads theme CSS/JS cascade | Site styling and interaction break | PASS |
| `wp_enqueue_scripts` | `TTOS_Shortcodes::assets` | frontend | Loads plugin frontend CSS/JS globally | Menu, modal, banner, popup behavior breaks | PASS |
| `wp_head` | `TTOS_Settings::print_brand_css` | frontend | Emits live brand tokens | Saved branding would not apply | PASS |
| `wp_head` | `ttheme_preload_home_hero_image` | frontend | Preloads homepage hero image | Performance only; not cuisine-aware | PASS |
| `wp_resource_hints` | `ttheme_resource_hints` | frontend | Preconnect/dns-prefetch hints | Performance only | PASS |
| `wp_body_open` | `TTOS_Public_UI::render_banner` | frontend | Announcement banner injection | Banner may vanish | PASS |
| `wp_footer` | `TTOS_Public_UI::render_banner_footer_fallback` | frontend | Banner fallback if theme misses `wp_body_open` | Banner may vanish | PASS |
| `wp_footer` | `TTOS_Public_UI::render_popup` | frontend | Announcement popup injection | Popup may vanish | PASS |
| `wp_footer` | theme accessibility helpers | frontend | Accessibility widgets and back-to-top | Assistive affordances vanish | PASS |

### WooCommerce Flow

| Hook | Callback | Purpose | Risk if broken | Status |
| --- | --- | --- | --- | --- |
| `woocommerce_add_to_cart_validation` | `TTOS_WooCommerce::validate_configured_add_to_cart` | Validates configurator options | Invalid orders could enter cart | PASS |
| `woocommerce_add_cart_item_data` | `TTOS_WooCommerce::add_configured_cart_item_data` | Stores selected options, notes, suggested extras | Configurator data lost | PASS |
| `woocommerce_before_calculate_totals` | `TTOS_WooCommerce::apply_configured_cart_prices` | Applies extra option pricing | Under/overcharging risk | PASS |
| `woocommerce_add_to_cart` | `TTOS_WooCommerce::maybe_add_modal_suggested_products` | Auto-add selected suggested extras | Upsell flow breaks | PASS |
| `woocommerce_checkout_create_order_line_item` | `TTOS_WooCommerce::save_configured_order_item_meta` | Writes option metadata to order line items | Kitchen detail lost | PASS |
| `woocommerce_cart_collaterals` | `TTOS_WooCommerce::cart_recommendations` | Replaces cross-sells with themed recs | Recommendation panel breaks | PASS |
| `woocommerce_checkout_after_customer_details` | `TTOS_WooCommerce::checkout_experience_panel` | Adds tracking preview and checkout recs | Branded checkout layer degrades | PASS |
| `woocommerce_checkout_fields` | `TTOS_Operations::checkout_fields` | Adds fulfilment/time controls | Fulfilment workflow breaks | PASS |
| `woocommerce_checkout_process` | `TTOS_Operations::validate_checkout` | Validates fulfilment/time | Bad order data risk | PASS |
| `woocommerce_checkout_create_order` | `TTOS_Operations::save_order_meta` | Saves fulfilment/time order meta | Order ops visibility breaks | PASS |
| `woocommerce_order_status_changed` | `TTOS_Operations::log_order_status` | Ops logging | Audit trail reduced | PASS |

## Shortcodes

| Shortcode | Callback | Main usage | Data source | Security / a11y note | Status |
| --- | --- | --- | --- | --- | --- |
| `[takeaway_menu]` | `TTOS_Shortcodes::menu` | Menu page | Woo products, product taxonomies, site content flags | Core public order surface; keyboard QA should continue | PASS |
| `[takeaway_customer_portal]` | `TTOS_Shortcodes::customer_portal` | Customer account area | Woo account/customer data | Auth-sensitive | PASS |
| `[takeaway_order_tracker]` | `TTOS_Shortcodes::order_tracker` | Tracker page | Woo orders and tracker query params | Must protect order lookup | PASS |
| `[takeaway_delivery_checker]` | `TTOS_Shortcodes::delivery_checker` | Delivery checker page | Trading settings/postcodes | Simple public checker | PASS |
| `[takeaway_allergens]` | `TTOS_Shortcodes::allergens` | Allergen info page | Product allergen taxonomy/meta | Important compliance surface | PASS |
| `[takeaway_contact]` | `TTOS_Shortcodes::contact` | Contact page | Business/contact details | Public form should keep nonce/rate limit | PASS |
| `[takeaway_policy]` | `TTOS_Shortcodes::policy` | Policy pages | `ttos_site_content[policies]` | Mostly content rendering | PASS |
| `[takeaway_kitchen_screen]` | `TTOS_Shortcodes::kitchen_screen` | Kitchen display page | Woo orders | Access control important | PASS |
| `[takeaway_meal_deals]` | `TTOS_Features::shortcode_meal_deals` | Meal deals page / menu extras | `ttos_meal_deals` and products | Depends on configured deals | PASS |
| `[takeaway_home_blocks]` | `TTOS_Features::shortcode_home_blocks` | Generated home page | Feature content + products | Presentation layer | PASS |
| `[takeaway_rewards]` | `TTOS_Features::shortcode_rewards` | Rewards page | loyalty/stamp settings and user/order data | Account-specific value | PASS |
| `[takeaway_go_live_checklist]` | `TTOS_Operations::shortcode_go_live` | Ops/go-live use | Ops settings | Mostly internal | PASS |
| `[takeaway_open_status]` | `TTOS_Operations::shortcode_open_status` | Open/closed snippets | Trading / opening hours | Public status correctness matters | PASS |

## AJAX / Admin-Post Endpoints

| Endpoint | Auth | Nonce / cap | Purpose | Sanitisation / limits | Status |
| --- | --- | --- | --- | --- | --- |
| `wp_ajax_tt_contact` | logged-in | nonce + form sanitisation | AJAX contact submission | sanitised fields + honeypot + transient rate limit | PASS |
| `wp_ajax_nopriv_tt_contact` | public | nonce + form sanitisation | public AJAX contact submission | same as above | PASS |
| `wp_ajax_ttos_install_plugin` | logged-in | plugin installer nonce | Install bundled/dependency plugins | admin-only intended | PASS |
| `wp_ajax_ttos_order_board` | logged-in | `ttos_order_board` nonce + `ttos_view_orders` cap | Refresh order board | capability-gated | PASS |
| `wp_ajax_ttos_order_action` | logged-in | `ttos_order_action` nonce + `ttos_update_orders` cap | Update order state | capability-gated + sanitised | PASS |
| `admin_post_ttheme_install_bundled_takeaway_os` | logged-in admin | nonce expected in installer flow | Theme-side bundled plugin installer | Packaging path currently incomplete locally | PARTIAL |
| `admin_post_ttos_export_menu_csv` | logged-in | admin-post nonce | Export menu CSV | source reviewed | PASS |
| `admin_post_ttos_export_site_content` | logged-in | admin-post nonce | Export structured content | source reviewed | PASS |
| `admin_post_ttos_export_orders_csv` | logged-in | admin-post nonce | Export orders | source reviewed | PASS |
| `admin_post_ttos_export_customers_csv` | logged-in | admin-post nonce | Export customers | source reviewed | PASS |
| `admin_post_ttos_export_campaign_contacts` | logged-in | admin-post nonce | Export campaign contacts | source reviewed | PASS |
| `admin_post_ttos_export_money_csv` | logged-in | admin-post nonce | Accounting exports | source reviewed | PASS |
| `admin_post_ttos_export_daily_close_csv` | logged-in | admin-post nonce | Daily close export | source reviewed | PASS |

REST routes found in this audit:

- None found in current source

Status: NOT FOUND

## Additional Theme Hooks Worth Tracking

| Hook | Purpose | Status |
| --- | --- | --- |
| `woocommerce_add_to_cart_fragments` | Updates basket count/preview fragments | PASS |
| `woocommerce_before_customer_login_form` / `woocommerce_after_customer_login_form` | Branded My Account login wrapper | PASS |
| `nav_menu_css_class` filter in contact module | Converts contact nav item into panel trigger | PASS |
| `template_redirect` in account module | Handles account actions such as delete-account flow | PASS |

# Takeaway OS Data Model Map

Updated: 2026-06-17

## Core Options

| Option key | Owner | Summary | Who edits | Notes | Status |
| --- | --- | --- | --- | --- | --- |
| `ttos_settings` | `TTOS_Settings` | Business, branding, trading, service links, module flags, data retention | settings-level users | Primary config store | PASS |
| `ttos_site_content` | `TTOS_Site_Content` | Homepage, menu page, business info, hours, delivery/collection, contact/map, reviews, offers, socials, footer, policies, banner, popup | settings-level users | Structured content CMS | PASS |
| `ttos_version` | `TTOS_Activator` / `TTOS_Hardening` | Installed plugin version | system | Used for migrations | PASS |
| `ttos_page_*` | `TTOS_Page_Manager` | Generated page registry IDs | system/admin tools | Includes menu, cart, checkout, tracker, policy pages | PASS |
| `ttos_operations_settings` | `TTOS_Operations` | Checkout flow, handover mode, launch checklist | settings-level users | Ops + launch state | PASS |
| `ttos_feature_settings` | `TTOS_Features` | Loyalty, SMS, printer, EPOS, accounting, advanced zones, content | module-capable users | Real settings even where live integrations are unfinished | PASS |
| `ttos_meal_deals` | `TTOS_Features` | Meal deal configuration | module-capable users | Required for meal-deal builder | PASS |
| `ttos_customer_profiles` | `TTOS_Admin` | CRM tags, notes, marketing opt flag, birthday | reports users | Customer CRM overlay | PASS |
| `ttos_customer_campaigns` | `TTOS_Admin` | Campaign history records | reports users | CRM marketing history | PASS |
| `ttos_loyalty_ledger` | `TTOS_Admin` / `TTOS_Features` | Manual and earned points adjustments | reports/module users | Important for rewards accuracy | PASS |
| `ttos_integration_log` | `TTOS_Features` / `TTOS_Operations` | Integration event log | admin | Useful for QA and support | PASS |
| `ttos_integration_queue` | `TTOS_Features` | Retry queue for failed outbound integrations | system/admin | Background job state | PASS |
| `ttos_module_lock_hash` | `TTOS_Admin` / `TTOS_Hardening` | Handover lock on paid modules | modules-capable/admin | Local protection only | PASS |

## `ttos_settings` Schema Highlights

| Section | Key examples | Status |
| --- | --- | --- |
| `business` | restaurant name, phone, email, address, FSA, VAT, `cuisine` | PASS |
| `branding` | logo, favicon, hero image, token colours, radii, modes, style skin, font overrides | PASS |
| `trading` | min order, delivery fee, free delivery threshold, radius, prep/delivery time, postcode list, enable flags, service charge | PASS |
| `service_links` | Stripe, Fluent SMTP, SMTP2GO, PrintNode, Twilio, Xero, QuickBooks links | PASS |
| `modules` | loyalty, meal deals, CRM Pro, accounting, analytics, QR ordering, etc. | PASS |
| `data_retention` | uninstall cleanup flags | PASS |

## `ttos_site_content` Sections

| Section | Purpose | Status |
| --- | --- | --- |
| `homepage` | hero, featured food, direct-order sections, review blocks | PASS |
| `menu_page` | menu filter/search/UX toggles and copy | PASS |
| `business_info` | identity, trust links, profile URLs | PASS |
| `opening_times` | structured opening hours | PASS |
| `delivery_collection` | service copy, estimate text, delivery/collection details | PASS |
| `contact_map` | contact/map content | PASS |
| `reviews` | review text and display settings | PASS |
| `offers` | offers/promotional content | PASS |
| `social_links` | public social URLs | PASS |
| `footer` | footer columns, labels, hide-empty behavior | PASS |
| `policies` | privacy, cookies, terms, refunds, delivery, accessibility, hygiene, business details | PASS |
| `banner` | announcement banner config | PASS |
| `popup` | announcement popup config | PASS |

## Generated Page Model

`TTOS_Page_Manager` manages marker-wrapped generated pages for:

- Home
- Menu
- Basket
- Checkout
- My Account
- Order Tracker
- Allergen Information
- Delivery Checker
- Meal Deals
- Rewards
- Contact
- Policy pages

Generated markers/meta:

- option registry: `ttos_page_<key>`
- page meta: `_ttos_generated_page = 1`
- page meta: `_ttos_page_key = <key>`

Status: PASS

## Product / Cart / Order Meta

| Meta key | Surface | Purpose | Status |
| --- | --- | --- | --- |
| `_ttos_menu_item` | product | Marks generated/managed menu items | PASS |
| `_ttos_option_groups` | product | Configurator group JSON | PASS |
| `_ttos_cost_price` | product | Internal costing | PASS |
| `_ttos_spice` | product | Menu spice marker | PASS |
| `_ttos_badges` | product | Badge text | PASS |
| `_ttos_allergens` | product | Legacy/raw allergen text | PASS |
| `_ttos_discount_note` | product | Promo note | PASS |
| `_featured` | product | Woo featured status | PASS |
| `_ttos_generated_coupon` | coupon | Marks generated customer coupons | PASS |
| `ttos_options` (cart item) | cart | Selected configurator options | PASS |
| `ttos_extra_total` (cart item) | cart | Added price delta | PASS |
| `ttos_item_note` (cart item) | cart | Kitchen note | PASS |
| `ttos_suggested_products` (cart item) | cart | Selected modal upsells | PASS |
| `_ttos_fulfilment_method` | order | delivery vs collection | PASS |
| `_ttos_requested_time` | order | ASAP or selected time | PASS |
| `_ttos_kitchen_note` | order | Internal kitchen/admin note | PASS |
| `_ttos_prep_minutes` | order | Prep time assigned from cockpit | PASS |
| `_ttos_due_ts` | order | Due timestamp | PASS |

## Taxonomies

| Taxonomy | Registered where | Used for | Admin UI | Status |
| --- | --- | --- | --- | --- |
| `product_cat` | Woo core | Menu grouping, nav, starter products, recommendation scoring | Woo standard | PASS |
| `ttos_allergen` | `TTOS_WooCommerce::register_taxonomies()` | Allergen filters and display | hidden custom taxonomy | PASS |
| `ttos_dietary` | `TTOS_WooCommerce::register_taxonomies()` | Dietary chips/filters | hidden custom taxonomy | PASS |

REST-facing custom taxonomy exposure:

- `show_in_rest` is false for `ttos_allergen` and `ttos_dietary`

Status: PASS

## Roles And Capabilities

| Role | Key capabilities | Intended screens | Status |
| --- | --- | --- | --- |
| `administrator` | all `ttos_*` caps including `ttos_modules` | full product and WordPress admin | PASS |
| `takeaway_owner` | access, manage, orders, menu, settings, reports, uploads | most Takeaway OS screens except paid-module unlock | PASS |
| `takeaway_manager` | access, manage, orders, menu, reports, uploads; no settings/modules | menu/orders/reports focused | PASS |
| `takeaway_kitchen` | access, view/update orders | kitchen/order screens | PASS |
| `takeaway_driver` | same as kitchen in current code | order/update screens | PASS |

Capability-gating notes:

- `Features` and `Add-ons` stay behind `ttos_modules`
- Menu work stays behind `ttos_manage_menu`
- Settings pages stay behind `ttos_manage_settings`
- Orders/Kitchen stay behind order caps

Live non-admin browser verification:

- Not available in this audit environment

Status: UNKNOWN

# Takeaway OS Frontend Map

Updated: 2026-06-17

## Verification Summary

- Source reviewed in `takeaway-theme/` and `takeaway-os/includes/class-shortcodes.php`, `class-woocommerce.php`, `class-public-ui.php`
- Live staging sweep covered `/`, `/menu/`, `/basket/`, `/checkout/`, `/order-tracker/`, `/rewards/`
- No browser console warnings or errors were returned in the sweep

## Public Feature Map

### 1. Global Header And Drawer

| Field | Detail |
| --- | --- |
| Feature name | Native branded header, utility strip, account, basket, CTA, mobile drawer |
| Status | PASS |
| Frontend location | All public pages via `header.php`, `template-parts/header/site-header.php`, `mobile-drawer.php` |
| Admin location | Branding and business settings in Takeaway OS |
| Code | `takeaway-theme/template-parts/header/site-header.php`, `mobile-drawer.php`, `assets/js/theme.js`, `assets/css/header.css` |
| Hooks / endpoints | Theme enqueue, `body_class`, Woo fragments |
| Data | `ttos_settings`, site content helpers, Woo cart session |
| How verified | Source + live homepage/menu/checkout |
| Security notes | Read-only display except account/menu toggles |
| Accessibility notes | Drawer and dropdown use JS close/focus patterns; more keyboard regression testing still worthwhile |
| Design/UI notes | Strong branded shell with utility bar, status pill, account pill, basket preview |
| Regression risk | Medium because header behavior spans JS, Woo fragments, and helper data |
| Blocks packaging? | no |
| Blocks paid production? | no |

### 2. Contact Mega Panel

| Field | Detail |
| --- | --- |
| Feature name | Header contact panel with AJAX form |
| Status | PASS |
| Frontend location | Header contact trigger |
| Admin location | Business/contact details and site content |
| Code | `takeaway-theme/inc/contact.php`, `assets/js/theme.js` |
| Hooks / endpoints | `wp_ajax_tt_contact`, `wp_ajax_nopriv_tt_contact` |
| Data | Contact form fields, `admin_email`, business helpers |
| How verified | Source + visible contact panel in DOM snapshot |
| Security notes | Nonce, honeypot, input sanitisation, transient rate limit |
| Accessibility notes | Modal/panel behavior exists; worth live keyboard-only sweep later |
| Design/UI notes | Good utility support pattern; blends into brand shell |
| Regression risk | Medium |
| Blocks packaging? | no |
| Blocks paid production? | no |

### 3. Homepage

| Field | Detail |
| --- | --- |
| Feature name | Branded homepage with hero, order method tabs, featured sections, CTA rails |
| Status | PASS |
| Frontend location | `/` |
| Admin location | Site Content, Business Settings, Branding |
| Code | `front-page.php`, `template-parts/home/*`, `inc/template-helpers.php`, `inc/performance.php` |
| Hooks / endpoints | `wp_head` hero preload, `tt_home_sections` filter |
| Data | `ttos_site_content`, `ttos_settings`, featured products/content |
| How verified | Source + live homepage |
| Security notes | Read-only output |
| Accessibility notes | Semantic headings present; tab and CTA behavior should keep being regression-tested |
| Design/UI notes | Strongest public surface; cuisine label appears in hero eyebrow |
| Regression risk | Medium |
| Blocks packaging? | no |
| Blocks paid production? | no |

### 4. Menu / Ordering Page

| Field | Detail |
| --- | --- |
| Feature name | Branded menu archive with category nav, search, filters, sticky basket |
| Status | PASS |
| Frontend location | `/menu/` |
| Admin location | Menu Builder, Site Content menu page settings |
| Code | `archive-product.php`, `TTOS_Shortcodes::menu()`, `TTOS_WooCommerce`, `frontend.css`, `frontend.js`, `assets/css/menu.css` |
| Hooks / endpoints | Woo product queries, cart fragments |
| Data | Woo products, `product_cat`, `ttos_dietary`, `ttos_allergen`, `ttos_site_content[menu_page]` |
| How verified | Source + live menu sweep |
| Security notes | Public product rendering only |
| Accessibility notes | Filter controls and modal flow need continued keyboard QA, but markup includes labels and grouped controls |
| Design/UI notes | Strong branded layout with browse rail and sticky basket prompt |
| Regression risk | High because this is the core sell path |
| Blocks packaging? | no |
| Blocks paid production? | no |

### 5. Product Configurator Modal

| Field | Detail |
| --- | --- |
| Feature name | Configure meal modal with option groups, note, quantity, total |
| Status | PASS |
| Frontend location | Menu product cards with option groups |
| Admin location | Menu Builder product editor |
| Code | `TTOS_Shortcodes::product_card()`, `TTOS_WooCommerce::validate_selected_options()`, `add_configured_cart_item_data()`, `apply_configured_cart_prices()` |
| Hooks / endpoints | `woocommerce_add_to_cart_validation`, `woocommerce_add_cart_item_data`, `woocommerce_before_calculate_totals`, `woocommerce_checkout_create_order_line_item` |
| Data | Product meta `_ttos_option_groups`, `_ttos_badges`, `_ttos_allergens`, cart item meta |
| How verified | Source + live modal open on staging |
| Security notes | Nonce and sanitisation present; validation enforces min/max choices |
| Accessibility notes | Dialog semantics present in DOM snapshot; full screen-reader pass still recommended |
| Design/UI notes | Clean modal and price summary; recent upsell insertion reduced dead whitespace but must stay visually balanced |
| Regression risk | High |
| Blocks packaging? | no |
| Blocks paid production? | no |

### 6. Smart Modal Upsells

| Field | Detail |
| --- | --- |
| Feature name | Suggested extras inside configurator modal |
| Status | PASS |
| Frontend location | Bottom of configurator modal |
| Admin location | None directly; driven by catalog and customer history |
| Code | `TTOS_WooCommerce::render_modal_recommendations()`, `modal_recommendation_data()`, `maybe_add_modal_suggested_products()` |
| Hooks / endpoints | `woocommerce_add_to_cart`, configured cart item data hooks |
| Data | Cross-sells, upsells, `total_sales`, Woo order history by billing email |
| How verified | Source + live modal shows suggestions on staging |
| Security notes | Candidate IDs are sanitized and filtered to simple purchasable products |
| Accessibility notes | Checkbox-based structure is accessible in principle; needs ongoing modal keyboard QA |
| Design/UI notes | Clear upsell block with separate rationale text |
| Regression risk | Medium/high |
| Blocks packaging? | no |
| Blocks paid production? | no |

### 7. Basket Page Recommendations

| Field | Detail |
| --- | --- |
| Feature name | Basket recommendations / complete-the-meal panel |
| Status | PASS |
| Frontend location | `/basket/` |
| Admin location | Indirect only |
| Code | `TTOS_WooCommerce::cart_recommendations()`, `render_recommendations()` |
| Hooks / endpoints | `woocommerce_cart_collaterals` |
| Data | Cart items, cross-sells, upsells, add-on product categories |
| How verified | Source + live basket page |
| Security notes | Add links rely on Woo add-to-cart flow |
| Accessibility notes | Needs standard link/button QA only |
| Design/UI notes | Coherent with modal upsells |
| Regression risk | Medium |
| Blocks packaging? | no |
| Blocks paid production? | no |

### 8. Checkout Experience Layer

| Field | Detail |
| --- | --- |
| Feature name | Delivery/collection control, requested time, map/tracking preview, checkout upsells |
| Status | PASS |
| Frontend location | `/checkout/` |
| Admin location | Operations -> Checkout, Delivery, Business Settings |
| Code | `TTOS_Operations::checkout_fields()`, `validate_checkout()`, `save_order_meta()`, `TTOS_WooCommerce::checkout_experience_panel()` |
| Hooks / endpoints | `woocommerce_checkout_fields`, `woocommerce_checkout_process`, `woocommerce_checkout_create_order`, `woocommerce_checkout_after_customer_details` |
| Data | `ttos_operations_settings`, `ttos_settings[trading]`, order meta `_ttos_fulfilment_method`, `_ttos_requested_time` |
| How verified | Source + live checkout page |
| Security notes | Checkout sanitisation present; payment readiness still external |
| Accessibility notes | Form fields use native Woo structures; final payment-gateway-specific QA still needed |
| Design/UI notes | Distinctive branded checkout with direct-order narrative |
| Regression risk | High |
| Blocks packaging? | no |
| Blocks paid production? | no, but live gateway setup still required |

### 9. Order Tracker

| Field | Detail |
| --- | --- |
| Feature name | Standalone order tracker page |
| Status | PASS |
| Frontend location | `/order-tracker/` |
| Admin location | Order cockpit updates drive usefulness |
| Code | `TTOS_Shortcodes::order_tracker()`, thank-you prompt in Woo layer |
| Hooks / endpoints | shortcode output, `woocommerce_thankyou` prompt |
| Data | Order id/key lookup, order status meta |
| How verified | Source + live page |
| Security notes | Must rely on order key gating rather than exposing broad order data |
| Accessibility notes | Simple form surface |
| Design/UI notes | Useful but operationally depends on order-state discipline |
| Regression risk | Medium |
| Blocks packaging? | no |
| Blocks paid production? | no |

### 10. Rewards / Account Enhancements

| Field | Detail |
| --- | --- |
| Feature name | Rewards page and branded my-account enhancements |
| Status | PARTIAL |
| Frontend location | `/rewards/`, Woo My Account dashboard |
| Admin location | Features, Customers/CRM |
| Code | `TTOS_Features`, `woocommerce/myaccount/dashboard.php` |
| Hooks / endpoints | account endpoint `takeaway-rewards`, Woo account menu filter |
| Data | `ttos_feature_settings`, loyalty ledger, order history |
| How verified | Source + live rewards page |
| Security notes | Customer-specific state depends on Woo identity |
| Accessibility notes | Mostly standard account/dashboard patterns |
| Design/UI notes | Good shell; business value depends on populated history |
| Regression risk | Medium |
| Blocks packaging? | no |
| Blocks paid production? | no |

### 11. Banner / Popup Announcements

| Field | Detail |
| --- | --- |
| Feature name | Scheduled public banner and popup |
| Status | PASS |
| Frontend location | Global body/footer injection |
| Admin location | Site Content -> Banner / Popup |
| Code | `TTOS_Public_UI` |
| Hooks / endpoints | `wp_body_open`, `wp_footer` |
| Data | `ttos_site_content[banner]`, `ttos_site_content[popup]` |
| How verified | Source, plus assets enqueued globally to support this behavior |
| Security notes | Browser-only dismissal storage, no personal server state |
| Accessibility notes | Dialog/region semantics present; needs future keyboard QA with active popup content |
| Design/UI notes | Flexible and schedule-aware |
| Regression risk | Low/medium |
| Blocks packaging? | no |
| Blocks paid production? | no |

## Frontend Gaps

| Gap | Status | Notes |
| --- | --- | --- |
| Cuisine-aware hero content beyond eyebrow | PARTIAL | Cuisine currently affects label more than actual seeded experience |
| Cuisine-aware starter menu preload | FAIL | Generic products still seeded regardless of cuisine |
| Staging content realism | NEEDS CLIENT SETUP | Placeholder socials, contact email, trust links remain |
| Staging menu cleanliness | RISK | Duplicate starter/demo items visible live |

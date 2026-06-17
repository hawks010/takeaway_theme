<?php

defined('ABSPATH') || exit;

final class TTOS_Onboarding {
    public static function apply_profile(): void {
        if (!current_user_can('ttos_manage_settings')) {
            wp_die('You do not have permission to apply the Takeaway OS profile.');
        }
        $business = TTOS_Settings::get('business');
        $trading  = TTOS_Settings::get('trading');

        update_option('woocommerce_currency', 'GBP');
        update_option('woocommerce_default_country', 'GB');
        update_option('woocommerce_allowed_countries', 'specific');
        update_option('woocommerce_specific_allowed_countries', array('GB'));
        update_option('woocommerce_weight_unit', 'kg');
        update_option('woocommerce_dimension_unit', 'cm');
        update_option('woocommerce_calc_taxes', 'no');
        update_option('woocommerce_prices_include_tax', 'no');
        update_option('woocommerce_enable_guest_checkout', 'yes');
        update_option('woocommerce_registration_generate_username', 'yes');
        update_option('woocommerce_registration_generate_password', 'yes');
        update_option('woocommerce_enable_coupons', 'yes');
        update_option('woocommerce_task_list_hidden', 'yes');
        update_option('woocommerce_show_marketplace_suggestions', 'no');
        update_option('woocommerce_allow_tracking', 'no');

        if (!empty($business['address_1'])) update_option('woocommerce_store_address', sanitize_text_field($business['address_1']));
        if (!empty($business['address_2'])) update_option('woocommerce_store_address_2', sanitize_text_field($business['address_2']));
        if (!empty($business['town'])) update_option('woocommerce_store_city', sanitize_text_field($business['town']));
        if (!empty($business['postcode'])) update_option('woocommerce_store_postcode', sanitize_text_field($business['postcode']));
        update_option('woocommerce_store_country', 'GB');

        // Owner-safe store type hints. WooCommerce may change these internally, but they help suppress onboarding noise.
        update_option('woocommerce_onboarding_profile', array(
            'completed' => true,
            'skipped' => true,
            'industry' => array('food-and-drink'),
            'product_types' => array('physical'),
            'business_extensions' => array(),
        ));

        TTOS_Page_Manager::ensure_all('fresh_if_unsafe');
        TTOS_Page_Manager::sync_woocommerce_page_options();
        self::seed_terms();
        if (post_type_exists('product') && class_exists('TTOS_Production') && (int) wp_count_posts('product')->publish === 0) {
            TTOS_Production::apply_starter_menu();
        }
        self::seed_shipping_methods($trading);
        self::enable_cod_gateway();
    }

    private static function seed_terms(): void {
        if (taxonomy_exists('product_cat')) {
            foreach (TTOS_Production::starter_pack_categories() as $name) {
                if (!term_exists($name, 'product_cat')) {
                    wp_insert_term($name, 'product_cat');
                }
            }
        }
        if (taxonomy_exists('ttos_allergen')) {
            foreach (TTOS_WooCommerce::allergen_list() as $slug => $label) {
                if (!term_exists($slug, 'ttos_allergen')) {
                    wp_insert_term($label, 'ttos_allergen', array('slug' => $slug));
                }
            }
        }
        if (taxonomy_exists('ttos_dietary')) {
            foreach (array('halal'=>'Halal','vegetarian'=>'Vegetarian','vegan'=>'Vegan','gluten-free'=>'Gluten-free','spicy'=>'Spicy','popular'=>'Popular') as $slug => $label) {
                if (!term_exists($slug, 'ttos_dietary')) {
                    wp_insert_term($label, 'ttos_dietary', array('slug' => $slug));
                }
            }
        }
    }

    private static function seed_shipping_methods(array $trading): void {
        if (!class_exists('WC_Shipping_Zones')) return;
        // Create a simple UK flat-rate zone only if no zones exist yet.
        $zones = WC_Shipping_Zones::get_zones();
        if (!empty($zones)) return;
        $zone = new WC_Shipping_Zone();
        $zone->set_zone_name('Local delivery');
        $zone->add_location('GB', 'country');
        $zone->save();
        $instance_id = $zone->add_shipping_method('flat_rate');
        $methods = $zone->get_shipping_methods();
        if (isset($methods[$instance_id])) {
            $methods[$instance_id]->set_post_data(array(
                'woocommerce_flat_rate_title' => 'Local delivery',
                'woocommerce_flat_rate_cost'  => wc_format_decimal($trading['delivery_fee'] ?? '1.50'),
            ));
            $methods[$instance_id]->process_admin_options();
        }
    }

    private static function enable_cod_gateway(): void {
        $existing = get_option('woocommerce_cod_settings', array());
        if (!is_array($existing)) {
            $existing = array();
        }
        // Only write if not already explicitly enabled.
        if (($existing['enabled'] ?? '') !== 'yes') {
            $existing['enabled']             = 'yes';
            $existing['title']               = $existing['title'] ?? __('Cash on Delivery', 'takeaway-os');
            $existing['description']         = $existing['description'] ?? __('Pay with cash when your order arrives.', 'takeaway-os');
            $existing['instructions']        = $existing['instructions'] ?? __('Pay with cash when your order arrives.', 'takeaway-os');
            $existing['enable_for_methods']  = $existing['enable_for_methods'] ?? array();
            $existing['enable_for_virtual']  = $existing['enable_for_virtual'] ?? 'no';
            update_option('woocommerce_cod_settings', $existing);
        }
    }

    public static function service_link(string $key): string {
        $links = TTOS_Settings::get('service_links');
        $url = $links[$key] ?? '';
        return $url ? esc_url($url) : '#';
    }
}

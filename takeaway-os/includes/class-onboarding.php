<?php

defined('ABSPATH') || exit;

final class TTOS_Onboarding {
    public static function apply_profile(): void {
        if (!current_user_can('ttos_manage_settings')) {
            wp_die('You do not have permission to apply the Takeaway OS profile.');
        }
        $business = TTOS_Settings::business_profile();

        // Applying the Takeaway profile repairs its own content, not the merchant's
        // commerce setup. WooCommerce owns currency, tax, accounts and gateways.
        // Do not mark WooCommerce onboarding complete or silently enable cash.
        TTOS_Settings::sync_business_runtime($business);

        TTOS_Page_Manager::ensure_all('fresh_if_unsafe');
        TTOS_Page_Manager::sync_woocommerce_page_options();
        self::seed_terms();
        if (post_type_exists('product') && class_exists('TTOS_Production') && (int) wp_count_posts('product')->publish === 0) {
            TTOS_Production::apply_starter_menu();
        }
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

    public static function service_link(string $key): string {
        $links = TTOS_Settings::get('service_links');
        $url = $links[$key] ?? '';
        return $url ? esc_url($url) : '#';
    }
}

<?php

defined('ABSPATH') || exit;

final class TTOS_Settings {
    public static function hooks(): void {
        add_action('wp_head', array(__CLASS__, 'print_brand_css'), 20);
    }

    public static function defaults(): array {
        return array(
            'business' => array(
                'restaurant_name' => get_bloginfo('name'),
                'tagline'         => get_bloginfo('description'),
                'phone'           => '',
                'email'           => get_option('admin_email'),
                'address_1'       => '',
                'address_2'       => '',
                'town'            => '',
                'postcode'        => '',
                'company_number'  => '',
                'vat_number'      => '',
                'fsa_rating'      => '',
                'cuisine'         => 'Takeaway',
            ),
            'branding' => array(
                'logo_id'       => 0,
                'hero_image_id' => 0,
                'primary'       => '#ff4000',
                'secondary'     => '#ffac00',
                'dark'          => '#1a1410',
                'cream'         => '#f9f4ee',
                'style_skin'    => 'charcoal',
            ),
            'trading' => array(
                'min_order'           => '12.00',
                'delivery_fee'        => '1.50',
                'free_delivery_over'  => '30.00',
                'delivery_radius'     => '7',
                'prep_time'           => '25',
                'delivery_time'       => '35',
                'delivery_postcodes'  => '',
                'collection_enabled'  => '1',
                'delivery_enabled'    => '1',
            ),

            'service_links' => array(
                'woocommerce_stripe' => 'https://woocommerce.com/products/stripe/',
                'fluent_smtp'        => 'https://fluentsmtp.com/',
                'smtp2go'            => 'https://www.smtp2go.com/',
                'printnode'          => 'https://www.printnode.com/',
                'twilio'             => 'https://www.twilio.com/',
                'xero'               => 'https://www.xero.com/uk/',
                'quickbooks'         => 'https://quickbooks.intuit.com/uk/',
            ),
            'modules' => array(
                'loyalty'          => false,
                'stamp_cards'      => false,
                'meal_deals'       => false,
                'sms_updates'      => false,
                'printer'          => false,
                'crm_pro'          => false,
                'accounting'       => false,
                'advanced_zones'   => false,
                'allergen_filters' => false,
                'inventory_lite'    => false,
                'analytics_pro'     => false,
                'promo_engine'      => false,
                'content_manager'   => false,
                'kds_pro'           => false,
                'epos_connector'   => false,
                'multi_location'   => false,
                'qr_ordering'      => false,
            ),
            'data_retention' => array(
                'erase_on_uninstall'       => '0',
                'delete_generated_pages'   => '0',
                'delete_menu_products'     => '0',
                'delete_generated_coupons' => '0',
                'delete_customer_meta'     => '0',
                'delete_roles'             => '1',
            ),
        );
    }

    public static function get(string $section = '', $key = null) {
        $settings = wp_parse_args(get_option('ttos_settings', array()), self::defaults());
        foreach (self::defaults() as $default_key => $default_value) {
            $settings[$default_key] = wp_parse_args($settings[$default_key] ?? array(), $default_value);
        }
        if ($section === '') {
            return $settings;
        }
        if ($key === null) {
            return $settings[$section] ?? array();
        }
        return $settings[$section][$key] ?? null;
    }

    public static function update_section(string $section, array $values): void {
        $settings = self::get();
        $settings[$section] = wp_parse_args($values, self::defaults()[$section] ?? array());
        update_option('ttos_settings', $settings, false);
    }

    public static function modules(): array {
        return self::get('modules');
    }

    public static function module_enabled(string $slug): bool {
        $modules = self::modules();
        return !empty($modules[$slug]);
    }

    public static function print_brand_css(): void {
        $brand = self::get('branding');
        $primary = sanitize_hex_color($brand['primary']) ?: '#ff4000';
        $secondary = sanitize_hex_color($brand['secondary']) ?: '#ffac00';
        $dark = sanitize_hex_color($brand['dark']) ?: '#1a1410';
        $cream = sanitize_hex_color($brand['cream']) ?: '#f9f4ee';
        echo '<style id="takeaway-os-brand">:root{--tt-primary:' . esc_html($primary) . ';--tt-secondary:' . esc_html($secondary) . ';--tt-dark:' . esc_html($dark) . ';--tt-cream:' . esc_html($cream) . ';}</style>';
    }
}

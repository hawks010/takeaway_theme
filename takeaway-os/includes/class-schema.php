<?php
/**
 * Takeaway OS JSON-LD Schema Output
 *
 * Outputs Restaurant / FoodEstablishment structured data on the frontend.
 * All fields are pulled live from WooCommerce options and TTOS settings so
 * the schema never contains hardcoded placeholder data.
 */

defined('ABSPATH') || exit;

final class TTOS_Schema {

    public static function hooks(): void {
        add_action('wp_head', array(__CLASS__, 'output_jsonld'), 2);
    }

    /**
     * Build and echo the JSON-LD <script> block.
     */
    public static function output_jsonld(): void {
        // Only on front-end pages where structured data makes sense
        if (is_admin() || is_feed() || is_robots()) {
            return;
        }

        $data = self::build_schema();
        if (empty($data)) {
            return;
        }

        echo '<script type="application/ld+json">'
            . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . '</script>' . "\n";
    }

    /**
     * Build the schema array, pulling every address field dynamically from
     * WooCommerce options so it always matches the store address configured
     * in WooCommerce → Settings → General.
     *
     * @return array  Schema.org FoodEstablishment graph node.
     */
    public static function build_schema(): array {
        $business = class_exists('TTOS_Settings') ? TTOS_Settings::get('business') : array();
        $trading  = class_exists('TTOS_Settings') ? TTOS_Settings::get('trading')  : array();

        // ------------------------------------------------------------------ //
        // Address — pulled live from WooCommerce options, never hardcoded.    //
        // ------------------------------------------------------------------ //
        $address_1 = (string) get_option('woocommerce_store_address',   '');
        $address_2 = (string) get_option('woocommerce_store_address_2', '');
        $city      = (string) get_option('woocommerce_store_city',      '');
        $postcode  = (string) get_option('woocommerce_store_postcode',  '');
        $country   = (string) get_option('woocommerce_default_country', 'GB');

        // Address is always pulled from WooCommerce options (the canonical store address).
        // TTOS business settings are NOT used for the address to avoid stale placeholder data.
        $street = trim($address_1 . ($address_2 ? ', ' . $address_2 : ''));

        $name  = (string) ($business['restaurant_name'] ?? get_bloginfo('name'));
        $phone = (string) ($business['phone'] ?? get_option('woocommerce_store_phone', ''));
        $email = (string) ($business['email'] ?? get_option('admin_email'));
        $url   = home_url('/');

        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => array('FoodEstablishment', 'Restaurant'),
            'name'     => $name,
            'url'      => $url,
        );

        if ($street || $city || $postcode) {
            $schema['address'] = array_filter(array(
                '@type'           => 'PostalAddress',
                'streetAddress'   => $street,
                'addressLocality' => $city,
                'postalCode'      => $postcode,
                'addressCountry'  => strtoupper(substr($country, 0, 2)),
            ));
        }

        if ($phone) $schema['telephone'] = $phone;
        if ($email) $schema['email']     = $email;

        // Cuisine
        $cuisine = (string) ($business['cuisine'] ?? '');
        if ($cuisine) $schema['servesCuisine'] = $cuisine;

        // Open for delivery / collection
        $delivery   = ($trading['delivery_enabled']   ?? '0') === '1';
        $collection = ($trading['collection_enabled'] ?? '0') === '1';
        if ($delivery)   $schema['hasDeliveryMethod'][] = 'http://purl.org/goodrelations/v1#DeliveryModeOwnFleet';
        if ($collection) $schema['hasDeliveryMethod'][] = 'http://purl.org/goodrelations/v1#DeliveryModeSelfPickup';

        // Minimum order
        $min_order = (float) ($trading['min_order'] ?? 0);
        if ($min_order > 0) {
            $schema['priceRange'] = 'Minimum delivery order £' . number_format($min_order, 2);
        }

        // Logo
        $logo_id = get_option('site_logo') ?: (int) get_theme_mod('custom_logo');
        if ($logo_id) {
            $logo_src = wp_get_attachment_image_src($logo_id, 'full');
            if ($logo_src) {
                $schema['logo'] = $logo_src[0];
            }
        }

        return $schema;
    }
}

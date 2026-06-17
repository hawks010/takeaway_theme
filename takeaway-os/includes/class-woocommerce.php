<?php

defined('ABSPATH') || exit;

final class TTOS_WooCommerce {
    public static function hooks(): void {
        add_action('init', array(__CLASS__, 'register_order_statuses'));
        add_action('init', array(__CLASS__, 'register_taxonomies'));
        add_filter('woocommerce_add_to_cart_validation', array(__CLASS__, 'validate_configured_add_to_cart'), 10, 3);
        add_filter('wc_order_statuses', array(__CLASS__, 'add_order_statuses'));
        add_filter('woocommerce_account_menu_items', array(__CLASS__, 'account_menu_items'));
        add_filter('woocommerce_add_cart_item_data', array(__CLASS__, 'add_configured_cart_item_data'), 10, 3);
        add_filter('woocommerce_get_item_data', array(__CLASS__, 'display_configured_cart_item_data'), 10, 2);
        add_action('woocommerce_before_calculate_totals', array(__CLASS__, 'apply_configured_cart_prices'), 20);
        add_action('woocommerce_add_to_cart', array(__CLASS__, 'maybe_add_modal_suggested_products'), 20, 6);
        add_action('woocommerce_checkout_create_order_line_item', array(__CLASS__, 'save_configured_order_item_meta'), 10, 4);
        remove_action('woocommerce_cart_collaterals', 'woocommerce_cross_sell_display');
        add_action('woocommerce_cart_collaterals', array(__CLASS__, 'cart_recommendations'), 5);
        add_action('woocommerce_checkout_after_customer_details', array(__CLASS__, 'checkout_experience_panel'), 20);
        add_action('woocommerce_thankyou', array(__CLASS__, 'thankyou_tracking_prompt'), 5);

        // Cash on Delivery: ensure the gateway is registered and enabled.
        add_filter('woocommerce_payment_gateways', array(__CLASS__, 'ensure_cod_gateway'));

        // Service charge: percentage-based fee added to cart totals.
        add_action('woocommerce_cart_calculate_fees', array(__CLASS__, 'apply_service_charge'));

        // Order comments / special instructions: rename the built-in field and ensure it is visible.
        add_filter('woocommerce_checkout_fields', array(__CLASS__, 'relabel_order_comments'), 20);

        // Previous order count: show on admin order view and in order emails.
        add_action('add_meta_boxes', array(__CLASS__, 'register_customer_history_meta_box'));
        add_filter('woocommerce_email_order_meta_fields', array(__CLASS__, 'email_customer_order_count'), 30, 3);
    }

    public static function active(): bool {
        return class_exists('WooCommerce') && function_exists('wc_get_order');
    }

    public static function register_taxonomies(): void {
        if (!post_type_exists('product')) {
            return;
        }
        register_taxonomy('ttos_allergen', array('product'), array(
            'label'        => __('Allergens', 'takeaway-os'),
            'public'       => false,
            'show_ui'      => false,
            'show_in_rest' => false,
            'hierarchical' => false,
            'rewrite'      => false,
        ));
        register_taxonomy('ttos_dietary', array('product'), array(
            'label'        => __('Dietary labels', 'takeaway-os'),
            'public'       => false,
            'show_ui'      => false,
            'show_in_rest' => false,
            'hierarchical' => false,
            'rewrite'      => false,
        ));
    }

    public static function allergen_list(): array {
        return array(
            'celery' => 'Celery', 'cereals-gluten' => 'Cereals containing gluten', 'crustaceans' => 'Crustaceans',
            'eggs' => 'Eggs', 'fish' => 'Fish', 'lupin' => 'Lupin', 'milk' => 'Milk', 'molluscs' => 'Molluscs',
            'mustard' => 'Mustard', 'nuts' => 'Nuts', 'peanuts' => 'Peanuts', 'sesame' => 'Sesame',
            'soya' => 'Soya', 'sulphites' => 'Sulphur dioxide/sulphites',
        );
    }

    public static function dietary_list(): array {
        return array('halal' => 'Halal', 'vegetarian' => 'Vegetarian', 'vegan' => 'Vegan', 'gluten-free' => 'Gluten-free', 'spicy' => 'Spicy', 'popular' => 'Popular');
    }

    public static function register_order_statuses(): void {
        if (!function_exists('register_post_status')) {
            return;
        }
        $statuses = array(
            'wc-ttos-accepted' => array('label' => 'Accepted', 'public' => true),
            'wc-ttos-prepping' => array('label' => 'Preparing', 'public' => true),
            'wc-ttos-ready'    => array('label' => 'Ready', 'public' => true),
            'wc-ttos-out'      => array('label' => 'Out for delivery', 'public' => true),
        );
        foreach ($statuses as $slug => $args) {
            register_post_status($slug, array(
                'label'                     => $args['label'],
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop($args['label'] . ' <span class="count">(%s)</span>', $args['label'] . ' <span class="count">(%s)</span>', 'takeaway-os'),
            ));
        }
    }

    public static function add_order_statuses(array $statuses): array {
        $new = array();
        foreach ($statuses as $key => $label) {
            $new[$key] = $label;
            if ($key === 'wc-processing') {
                $new['wc-ttos-accepted'] = __('Accepted', 'takeaway-os');
                $new['wc-ttos-prepping'] = __('Preparing', 'takeaway-os');
                $new['wc-ttos-ready']    = __('Ready', 'takeaway-os');
                $new['wc-ttos-out']      = __('Out for delivery', 'takeaway-os');
            }
        }
        return $new;
    }

    public static function account_menu_items(array $items): array {
        $items['takeaway-rewards'] = __('Rewards', 'takeaway-os');
        return $items;
    }

    public static function get_product_categories(): array {
        if (!taxonomy_exists('product_cat')) {
            return array();
        }
                $terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC'));
        if (is_wp_error($terms)) {
            return array();
        }
        usort($terms, function($a, $b) {
            $ao = (int) get_term_meta($a->term_id, 'order', true);
            $bo = (int) get_term_meta($b->term_id, 'order', true);
            if ($ao === $bo) {
                return strcasecmp($a->name, $b->name);
            }
            return $ao <=> $bo;
        });
        return $terms;
    }

    public static function product_form_data(int $product_id = 0): array {
        $defaults = array(
            'product_id'     => 0,
            'name'           => '',
            'category'       => '',
            'price'          => '',
            'sale_price'     => '',
            'cost_price'     => '',
            'description'    => '',
            'image_id'       => 0,
            'allergens'      => '',
            'badges'         => 'Popular, Halal',
            'spice'          => '',
            'discount_note'  => '',
            'dietary'        => array(),
            'featured'       => false,
            'limited_qty'    => '',
            'tax_status'     => 'taxable',
            'sort_order'     => '0',
            'option_groups'  => '[]',
            'sold_out'       => false,
            'hidden'         => false,
        );
        if (!$product_id || get_post_type($product_id) !== 'product') {
            return $defaults;
        }

        $terms = wp_get_post_terms($product_id, 'product_cat');
        $category = (!is_wp_error($terms) && !empty($terms)) ? $terms[0]->name : '';
        $option_groups = get_post_meta($product_id, '_ttos_option_groups', true);
        if (!$option_groups) {
            $legacy = get_post_meta($product_id, '_ttos_options', true);
            $option_groups = $legacy ? wp_json_encode(array(array(
                'name' => 'Extras',
                'type' => 'multiple',
                'required' => false,
                'min' => 0,
                'max' => 99,
                'options' => self::legacy_options_to_array($legacy),
            ))) : '[]';
        }

        return wp_parse_args(array(
            'product_id'     => $product_id,
            'name'           => get_the_title($product_id),
            'category'       => $category,
            'price'          => get_post_meta($product_id, '_regular_price', true),
            'sale_price'     => get_post_meta($product_id, '_sale_price', true),
            'cost_price'     => get_post_meta($product_id, '_ttos_cost_price', true),
            'description'    => get_post_field('post_content', $product_id),
            'image_id'       => get_post_thumbnail_id($product_id),
            'allergens'      => get_post_meta($product_id, '_ttos_allergens', true),
            'badges'         => get_post_meta($product_id, '_ttos_badges', true),
            'spice'          => get_post_meta($product_id, '_ttos_spice', true),
            'discount_note'  => get_post_meta($product_id, '_ttos_discount_note', true),
            'dietary'        => wp_get_object_terms($product_id, 'ttos_dietary', array('fields' => 'slugs')),
            'featured'       => get_post_meta($product_id, '_featured', true) === 'yes',
            'limited_qty'    => get_post_meta($product_id, '_manage_stock', true) === 'yes' ? get_post_meta($product_id, '_stock', true) : '',
            'tax_status'     => get_post_meta($product_id, '_tax_status', true) ?: 'taxable',
            'sort_order'     => (string) get_post_field('menu_order', $product_id),
            'option_groups'  => $option_groups,
            'sold_out'       => get_post_meta($product_id, '_stock_status', true) === 'outofstock',
            'hidden'         => get_post_status($product_id) === 'draft',
        ), $defaults);
    }

    private static function legacy_options_to_array(string $raw): array {
        $out = array();
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            preg_match('/^(.*?)(?:\s*\+\s*([0-9]+(?:\.[0-9]{1,2})?))?$/', $line, $m);
            $out[] = array(
                'label' => trim($m[1] ?? $line),
                'price' => isset($m[2]) ? (string) $m[2] : '0',
                'default' => false,
                'sold_out' => false,
            );
        }
        return $out;
    }

    public static function sanitise_option_groups($raw): string {
        $groups = json_decode((string) $raw, true);
        if (!is_array($groups)) {
            return '[]';
        }
        $clean = array();
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $name = sanitize_text_field($group['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $type = ($group['type'] ?? 'multiple') === 'single' ? 'single' : 'multiple';
            $options = array();
            foreach (($group['options'] ?? array()) as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $label = sanitize_text_field($option['label'] ?? '');
                if ($label === '') {
                    continue;
                }
                $options[] = array(
                    'label'    => $label,
                    'price'    => wc_format_decimal($option['price'] ?? 0),
                    'default'  => !empty($option['default']),
                    'sold_out' => !empty($option['sold_out']),
                );
            }
            $clean[] = array(
                'name'     => $name,
                'type'     => $type,
                'required' => !empty($group['required']),
                'min'      => max(0, absint($group['min'] ?? 0)),
                'max'      => max(0, absint($group['max'] ?? ($type === 'single' ? 1 : 99))),
                'options'  => $options,
            );
        }
        return wp_json_encode($clean);
    }

    public static function create_or_update_product(array $data): int {
        if (!post_type_exists('product')) {
            return 0;
        }

        $product_id = absint($data['product_id'] ?? 0);
        $sort_order = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
        $post_data = array(
            'post_title'   => sanitize_text_field($data['name'] ?? ''),
            'post_content' => wp_kses_post($data['description'] ?? ''),
            'post_excerpt' => wp_kses_post($data['description'] ?? ''),
            'post_type'    => 'product',
            'post_status'  => !empty($data['hidden']) ? 'draft' : 'publish',
            'menu_order'   => $sort_order,
        );
        if ($product_id) {
            $post_data['ID'] = $product_id;
            wp_update_post($post_data);
        } else {
            $product_id = wp_insert_post($post_data);
        }
        if (!$product_id || is_wp_error($product_id)) {
            return 0;
        }

        $regular_price = wc_format_decimal($data['price'] ?? 0);
        $sale_price = isset($data['sale_price']) && $data['sale_price'] !== '' ? wc_format_decimal($data['sale_price']) : '';
        $active_price = $sale_price !== '' ? $sale_price : $regular_price;

        update_post_meta($product_id, '_regular_price', $regular_price);
        update_post_meta($product_id, '_sale_price', $sale_price);
        update_post_meta($product_id, '_price', $active_price);
        $limited_qty = isset($data['limited_qty']) && $data['limited_qty'] !== '' ? max(0, absint($data['limited_qty'])) : '';
        if ($limited_qty !== '') {
            update_post_meta($product_id, '_manage_stock', 'yes');
            update_post_meta($product_id, '_stock', $limited_qty);
            update_post_meta($product_id, '_stock_status', (!empty($data['sold_out']) || $limited_qty < 1) ? 'outofstock' : 'instock');
        } else {
            update_post_meta($product_id, '_manage_stock', 'no');
            delete_post_meta($product_id, '_stock');
            update_post_meta($product_id, '_stock_status', !empty($data['sold_out']) ? 'outofstock' : 'instock');
        }
        update_post_meta($product_id, '_ttos_menu_item', '1');
        update_post_meta($product_id, '_tax_status', sanitize_key($data['tax_status'] ?? 'taxable'));
        update_post_meta($product_id, '_featured', !empty($data['featured']) ? 'yes' : 'no');
        update_post_meta($product_id, '_ttos_cost_price', wc_format_decimal($data['cost_price'] ?? ''));
        update_post_meta($product_id, '_ttos_spice', sanitize_text_field($data['spice'] ?? ''));
        update_post_meta($product_id, '_ttos_badges', sanitize_text_field($data['badges'] ?? ''));
        update_post_meta($product_id, '_ttos_allergens', sanitize_text_field($data['allergens'] ?? ''));
        update_post_meta($product_id, '_ttos_discount_note', sanitize_text_field($data['discount_note'] ?? ''));
        update_post_meta($product_id, '_ttos_option_groups', self::sanitise_option_groups($data['option_groups'] ?? '[]'));

        $image_id = absint($data['image_id'] ?? 0);
        if ($image_id) {
            set_post_thumbnail($product_id, $image_id);
        } else {
            delete_post_thumbnail($product_id);
        }

        $category = sanitize_text_field($data['category'] ?? '');
        if ($category !== '' && taxonomy_exists('product_cat')) {
            $term = term_exists($category, 'product_cat');
            if (!$term) {
                $term = wp_insert_term($category, 'product_cat');
            }
            if (!is_wp_error($term)) {
                $term_id = is_array($term) ? (int) $term['term_id'] : (int) $term;
                wp_set_object_terms($product_id, array($term_id), 'product_cat');
            }
        }

        if (taxonomy_exists('ttos_allergen')) {
            $allergen_slugs = array();
            if (!empty($data['allergen_slugs']) && is_array($data['allergen_slugs'])) {
                $allergen_slugs = array_map('sanitize_key', $data['allergen_slugs']);
            } elseif (!empty($data['allergens'])) {
                $allergen_slugs = array_map('sanitize_key', preg_split('/,/', (string) $data['allergens']));
            }
            wp_set_object_terms($product_id, array_filter($allergen_slugs), 'ttos_allergen', false);
        }

        if (taxonomy_exists('ttos_dietary')) {
            $dietary = !empty($data['dietary']) && is_array($data['dietary']) ? array_map('sanitize_key', $data['dietary']) : array();
            wp_set_object_terms($product_id, array_filter($dietary), 'ttos_dietary', false);
        }

        if (function_exists('wp_set_object_terms')) {
            wp_set_object_terms($product_id, 'simple', 'product_type');
        }

        return (int) $product_id;
    }


    public static function duplicate_product(int $product_id): int {
        if (!post_type_exists('product') || get_post_type($product_id) !== 'product') {
            return 0;
        }
        $source = get_post($product_id);
        if (!$source) {
            return 0;
        }
        $new_id = wp_insert_post(array(
            'post_title'   => $source->post_title . ' copy',
            'post_content' => $source->post_content,
            'post_excerpt' => $source->post_excerpt,
            'post_type'    => 'product',
            'post_status'  => 'draft',
            'menu_order'   => (int) $source->menu_order + 1,
        ));
        if (!$new_id || is_wp_error($new_id)) {
            return 0;
        }
        foreach (get_post_meta($product_id) as $key => $values) {
            if (is_protected_meta($key, 'post') || strpos($key, '_') === 0 || strpos($key, 'ttos') !== false) {
                foreach ($values as $value) {
                    add_post_meta($new_id, $key, maybe_unserialize($value));
                }
            }
        }
        foreach (array('product_cat','ttos_allergen','ttos_dietary') as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) continue;
            $terms = wp_get_object_terms($product_id, $taxonomy, array('fields' => 'ids'));
            if (!is_wp_error($terms)) wp_set_object_terms($new_id, $terms, $taxonomy);
        }
        $thumb = get_post_thumbnail_id($product_id);
        if ($thumb) set_post_thumbnail($new_id, $thumb);
        return (int) $new_id;
    }

    public static function get_option_groups(int $product_id): array {
        $raw = get_post_meta($product_id, '_ttos_option_groups', true);
        $groups = json_decode((string) $raw, true);
        return is_array($groups) ? $groups : array();
    }

    public static function validate_selected_options(int $product_id, array $posted): array {
        $groups = self::get_option_groups($product_id);
        $selected = array();
        $extra_total = 0.0;
        $errors = array();
        foreach ($groups as $g_index => $group) {
            if (!is_array($group)) continue;
            $group_name = sanitize_text_field($group['name'] ?? ('Option ' . ($g_index + 1)));
            $type = ($group['type'] ?? 'multiple') === 'single' ? 'single' : 'multiple';
            $choices = $posted[$g_index] ?? array();
            if (!is_array($choices)) $choices = array($choices);
            $choices = array_values(array_unique(array_map('sanitize_text_field', $choices)));
            $valid_labels = array();
            $count = 0;
            foreach (($group['options'] ?? array()) as $option) {
                if (!is_array($option)) continue;
                $label = sanitize_text_field($option['label'] ?? '');
                if ($label === '' || !in_array($label, $choices, true) || !empty($option['sold_out'])) continue;
                $valid_labels[] = $label;
                $count++;
                $price = (float) wc_format_decimal($option['price'] ?? 0);
                $selected[] = array('group' => $group_name, 'label' => $label, 'price' => $price);
                $extra_total += $price;
            }
            $min = !empty($group['required']) ? max(1, absint($group['min'] ?? 1)) : absint($group['min'] ?? 0);
            $max = absint($group['max'] ?? ($type === 'single' ? 1 : 99));
            if ($type === 'single') $max = 1;
            if ($count < $min) $errors[] = sprintf('%s requires at least %d choice(s).', $group_name, $min);
            if ($max > 0 && $count > $max) $errors[] = sprintf('%s allows up to %d choice(s).', $group_name, $max);
        }
        return array('items' => $selected, 'extra_total' => $extra_total, 'errors' => $errors);
    }

    public static function validate_configured_add_to_cart(bool $passed, int $product_id, int $quantity): bool {
        if (empty($_POST['ttos_configured_add'])) return $passed;
        if (empty($_POST['ttos_config_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ttos_config_nonce'])), 'ttos_configure_product_' . $product_id)) return false;
        $posted = isset($_POST['ttos_options']) && is_array($_POST['ttos_options']) ? wp_unslash($_POST['ttos_options']) : array();
        $validated = self::validate_selected_options($product_id, $posted);
        if (!empty($validated['errors'])) {
            foreach ($validated['errors'] as $error) wc_add_notice($error, 'error');
            return false;
        }
        return $passed;
    }

    public static function add_configured_cart_item_data(array $cart_item_data, int $product_id, int $variation_id): array {
        if (empty($_POST['ttos_configured_add']) || empty($_POST['ttos_config_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ttos_config_nonce'])), 'ttos_configure_product_' . $product_id)) {
            return $cart_item_data;
        }
        $posted = isset($_POST['ttos_options']) && is_array($_POST['ttos_options']) ? wp_unslash($_POST['ttos_options']) : array();
        $validated = self::validate_selected_options($product_id, $posted);
        $note = isset($_POST['ttos_item_note']) ? sanitize_textarea_field(wp_unslash($_POST['ttos_item_note'])) : '';
        if ($note !== '') {
            $cart_item_data['ttos_item_note'] = $note;
        }
        if (!empty($validated['items'])) {
            $cart_item_data['ttos_options'] = $validated['items'];
            $cart_item_data['ttos_extra_total'] = (float) $validated['extra_total'];
            $cart_item_data['ttos_unique_key'] = md5(wp_json_encode($validated['items']) . microtime(true));
        }
        $suggested_ids = isset($_POST['ttos_suggested_products']) && is_array($_POST['ttos_suggested_products'])
            ? array_values(array_unique(array_filter(array_map('absint', wp_unslash($_POST['ttos_suggested_products'])))))
            : array();
        if ($suggested_ids) {
            $cart_item_data['ttos_suggested_products'] = $suggested_ids;
        }
        return $cart_item_data;
    }

    public static function display_configured_cart_item_data(array $item_data, array $cart_item): array {
        if (empty($cart_item['ttos_options']) || !is_array($cart_item['ttos_options'])) return $item_data;
        if (!empty($cart_item['ttos_item_note'])) { $item_data[] = array('name' => 'Item note', 'value' => sanitize_textarea_field($cart_item['ttos_item_note'])); }
        foreach ($cart_item['ttos_options'] as $option) {
            $price = !empty($option['price']) ? ' +' . wc_price((float) $option['price']) : '';
            $item_data[] = array('name' => sanitize_text_field($option['group'] ?? 'Option'), 'value' => sanitize_text_field($option['label'] ?? '') . wp_strip_all_tags($price));
        }
        return $item_data;
    }

    public static function apply_configured_cart_prices($cart): void {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (!$cart || !method_exists($cart, 'get_cart')) return;
        foreach ($cart->get_cart() as $cart_item) {
            if (empty($cart_item['ttos_extra_total']) || empty($cart_item['data']) || !is_object($cart_item['data'])) continue;
            $product_id = (int) ($cart_item['product_id'] ?? 0);
            $base = get_post_meta($product_id, '_price', true);
            // Clamp to ≥ 0 — prevents negative session values from underpricing items.
            $cart_item['data']->set_price(max(0.0, (float) $base + (float) $cart_item['ttos_extra_total']));
        }
    }

    public static function save_configured_order_item_meta($item, string $cart_item_key, array $values, $order): void {
        if (!empty($values['ttos_item_note'])) { $item->add_meta_data('Item note', sanitize_textarea_field($values['ttos_item_note']), true); }
        if (empty($values['ttos_options']) || !is_array($values['ttos_options'])) return;
        foreach ($values['ttos_options'] as $option) {
            $label = sanitize_text_field($option['label'] ?? '');
            $group = sanitize_text_field($option['group'] ?? 'Option');
            $price = !empty($option['price']) ? ' +' . wc_price((float) $option['price']) : '';
            if ($label !== '') $item->add_meta_data($group, $label . wp_strip_all_tags($price), true);
        }
    }

    public static function maybe_add_modal_suggested_products(string $cart_item_key, int $product_id, int $quantity, int $variation_id, array $variation, array $cart_item_data): void {
        if (empty($cart_item_data['ttos_suggested_products']) || !function_exists('WC') || !WC()->cart) {
            return;
        }
        foreach ((array) $cart_item_data['ttos_suggested_products'] as $suggested_id) {
            $suggested_id = absint($suggested_id);
            if (!$suggested_id || $suggested_id === $product_id || !self::is_quick_add_recommendable_product($suggested_id)) {
                continue;
            }
            WC()->cart->add_to_cart($suggested_id, 1);
        }
    }

    public static function cart_recommendations(): void {
        self::render_recommendations('cart');
    }

    public static function checkout_experience_panel(): void {
        if (!function_exists('is_checkout') || !is_checkout() || (function_exists('is_order_received_page') && is_order_received_page())) {
            return;
        }

        $map_src = self::tracking_map_src();
        $estimate = self::checkout_estimate_text();
        echo '<section class="ttos-checkout-experience" aria-labelledby="ttos-checkout-experience-title">';
        echo '<div class="ttos-checkout-map-card">';
        echo '<div class="ttos-checkout-map-copy"><p class="ttos-panel-kicker">' . esc_html__('Tracking preview', 'takeaway-os') . '</p><h3 id="ttos-checkout-experience-title">' . esc_html__('Follow your food from kitchen to door', 'takeaway-os') . '</h3>';
        echo '<p>' . esc_html($estimate) . '</p><ol class="ttos-tracking-steps"><li>' . esc_html__('Order received', 'takeaway-os') . '</li><li>' . esc_html__('Kitchen accepts', 'takeaway-os') . '</li><li>' . esc_html__('Preparing', 'takeaway-os') . '</li><li>' . esc_html__('Ready or out for delivery', 'takeaway-os') . '</li></ol></div>';
        if ($map_src !== '') {
            echo '<div class="ttos-checkout-map-frame"><iframe title="' . esc_attr__('Restaurant map preview', 'takeaway-os') . '" src="' . esc_url($map_src) . '" loading="lazy"></iframe></div>';
        } else {
            echo '<div class="ttos-checkout-map-frame ttos-checkout-map-frame--empty"><span>' . esc_html__('Map appears here once the restaurant address or coordinates are configured.', 'takeaway-os') . '</span></div>';
        }
        echo '</div>';
        self::render_recommendations('checkout');
        echo '</section>';
    }

    public static function thankyou_tracking_prompt($order_id): void {
        if (!$order_id || !function_exists('wc_get_order')) {
            return;
        }
        $order = wc_get_order(absint($order_id));
        if (!$order) {
            return;
        }
        $track_url = add_query_arg(array(
            'ttos_order_id' => $order->get_id(),
            'ttos_key'      => $order->get_order_key(),
        ), self::tracker_page_url());

        $estimate = self::estimate_for_order($order);
        $method = sanitize_key((string) $order->get_meta('_ttos_fulfilment_method'));
        $method_label = $method === 'collection' ? __('collection', 'takeaway-os') : __('delivery', 'takeaway-os');

        echo '<section class="ttos-follow-tracking" aria-labelledby="ttos-follow-tracking-title">';
        echo '<div><p class="ttos-panel-kicker">' . esc_html__('Payment complete', 'takeaway-os') . '</p><h2 id="ttos-follow-tracking-title">' . esc_html__('Want to follow your order?', 'takeaway-os') . '</h2>';
        echo '<p>' . esc_html(sprintf(__('Estimated %1$s time: %2$s.', 'takeaway-os'), $method_label, $estimate)) . '</p></div>';
        echo '<div class="ttos-follow-tracking-actions"><a class="ttos-order-btn" href="' . esc_url($track_url) . '">' . esc_html__('Follow tracking', 'takeaway-os') . '</a><span>' . esc_html__('Status updates open in the order tracker.', 'takeaway-os') . '</span></div>';
        echo '</section>';
    }

    private static function render_recommendations(string $context): void {
        $products = self::recommendation_products($context === 'checkout' ? 3 : 4);
        if (!$products) {
            return;
        }

        $target = $context === 'checkout' && function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : (function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/basket/'));
        $title = $context === 'checkout' ? __('Still hungry?', 'takeaway-os') : __('Complete the meal', 'takeaway-os');
        $intro = $context === 'checkout'
            ? __('Quick add-ons picked from what is already in your basket.', 'takeaway-os')
            : __('Auto-picked sides and drinks that fit this order.', 'takeaway-os');
        $id = 'ttos-smart-upsells-' . sanitize_html_class($context);

        echo '<section class="ttos-smart-upsells ttos-smart-upsells--' . esc_attr($context) . '" aria-labelledby="' . esc_attr($id) . '">';
        echo '<div class="ttos-smart-upsells-head"><p class="ttos-panel-kicker">' . esc_html__('Recommended', 'takeaway-os') . '</p><h2 id="' . esc_attr($id) . '">' . esc_html($title) . '</h2><p>' . esc_html($intro) . '</p></div>';
        echo '<div class="ttos-smart-upsells-grid">';
        foreach ($products as $product) {
            $product_id = $product->get_id();
            $url = add_query_arg('add-to-cart', $product_id, $target);
            echo '<article class="ttos-smart-upsell">';
            echo '<a class="ttos-smart-upsell-media" href="' . esc_url(get_permalink($product_id)) . '" tabindex="-1" aria-hidden="true">' . wp_kses_post($product->get_image('woocommerce_thumbnail')) . '</a>';
            echo '<div class="ttos-smart-upsell-body"><h3>' . esc_html($product->get_name()) . '</h3><p>' . wp_kses_post($product->get_price_html()) . '</p></div>';
            echo '<a class="ttos-smart-upsell-add" href="' . esc_url($url) . '">' . esc_html__('Add', 'takeaway-os') . '</a>';
            echo '</article>';
        }
        echo '</div></section>';
    }

    public static function render_modal_recommendations(int $product_id): void {
        $data = self::modal_recommendation_data($product_id, 4);
        if (empty($data['products'])) {
            return;
        }
        $id = 'ttos-modal-upsells-' . $product_id;

        echo '<section class="ttos-smart-upsells ttos-modal-upsells" id="' . esc_attr($id) . '" aria-labelledby="' . esc_attr($id) . '-title" hidden>';
        echo '<div class="ttos-smart-upsells-head"><p class="ttos-panel-kicker">' . esc_html__('Suggested extras', 'takeaway-os') . '</p><h2 id="' . esc_attr($id) . '-title">' . esc_html($data['title']) . '</h2><p>' . esc_html($data['intro']) . '</p></div>';
        echo '<div class="ttos-smart-upsells-grid">';
        foreach ($data['products'] as $row) {
            $product = $row['product'];
            if (!$product) {
                continue;
            }
            $candidate_id = $product->get_id();
            $reason = !empty($row['history_match'])
                ? __('You have ordered this before.', 'takeaway-os')
                : __('Popular with this meal.', 'takeaway-os');

            echo '<label class="ttos-smart-upsell ttos-smart-upsell-pick">';
            echo '<input class="ttos-smart-upsell-check" type="checkbox" name="ttos_suggested_products[]" value="' . esc_attr((string) $candidate_id) . '">';
            echo '<span class="ttos-smart-upsell-media" aria-hidden="true">' . wp_kses_post($product->get_image('woocommerce_thumbnail')) . '</span>';
            echo '<span class="ttos-smart-upsell-body"><h3>' . esc_html($product->get_name()) . '</h3><p>' . wp_kses_post($product->get_price_html()) . '</p><small>' . esc_html($reason) . '</small></span>';
            echo '<span class="ttos-smart-upsell-toggle">' . esc_html__('Add', 'takeaway-os') . '</span>';
            echo '</label>';
        }
        echo '</div><p class="ttos-smart-upsells-footnote">' . esc_html__('Selected extras are added as separate basket items when you add this meal.', 'takeaway-os') . '</p></section>';
    }

    private static function recommendation_products(int $limit = 4): array {
        if (!function_exists('WC') || !WC()->cart || !function_exists('wc_get_products')) {
            return array();
        }

        $cart_ids = array();
        $cart_names = array();
        $cart_terms = array();
        $explicit = array();
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'] ?? null;
            if (!$product || !is_object($product) || !method_exists($product, 'get_id')) {
                continue;
            }
            $product_id = (int) $product->get_id();
            $cart_ids[] = $product_id;
            $cart_names[] = sanitize_title($product->get_name());
            foreach (array_merge($product->get_cross_sell_ids(), $product->get_upsell_ids()) as $related_id) {
                $explicit[(int) $related_id] = true;
            }
            $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
            if (!is_wp_error($terms)) {
                $cart_terms = array_merge($cart_terms, $terms);
            }
        }
        $cart_ids = array_values(array_unique(array_filter($cart_ids)));
        if (!$cart_ids) {
            return array();
        }
        $cart_terms = array_values(array_unique(array_filter($cart_terms)));

        $candidates = wc_get_products(array(
            'status'       => 'publish',
            'limit'        => 60,
            'exclude'      => $cart_ids,
            'stock_status' => 'instock',
            'orderby'      => 'menu_order',
            'order'        => 'ASC',
        ));

        $addon_terms = array('side', 'sides', 'drink', 'drinks', 'dessert', 'desserts', 'dip', 'dips', 'sauce', 'sauces', 'extras', 'meal-deals');
        $scored = array();
        $seen_names = array();
        foreach ($candidates as $candidate) {
            if (!$candidate || !$candidate->is_purchasable() || !$candidate->is_in_stock()) {
                continue;
            }
            $name_key = sanitize_title($candidate->get_name());
            if (in_array($name_key, $cart_names, true) || isset($seen_names[$name_key])) {
                continue;
            }
            $seen_names[$name_key] = true;

            $terms = wp_get_post_terms($candidate->get_id(), 'product_cat', array('fields' => 'slugs'));
            $terms = is_wp_error($terms) ? array() : $terms;
            $score = !empty($explicit[$candidate->get_id()]) ? 100 : 0;
            if (array_intersect($terms, $addon_terms)) {
                $score += 35;
            }
            if (array_intersect($terms, $cart_terms)) {
                $score += 8;
            }
            if ((float) $candidate->get_price() > 0 && (float) $candidate->get_price() <= 5) {
                $score += 6;
            }
            if ($score <= 0) {
                $score = 1;
            }
            $scored[] = array('product' => $candidate, 'score' => $score);
        }

        usort($scored, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return strcasecmp($a['product']->get_name(), $b['product']->get_name());
            }
            return $b['score'] <=> $a['score'];
        });

        return array_map(function ($row) {
            return $row['product'];
        }, array_slice($scored, 0, max(1, $limit)));
    }

    private static function modal_recommendation_data(int $product_id, int $limit = 4): array {
        $cache_key = $product_id . ':' . self::current_customer_email();
        static $cache = array();
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        if (!$product_id || !function_exists('wc_get_product')) {
            return $cache[$cache_key] = array('products' => array(), 'title' => '', 'intro' => '');
        }

        $reference_product = wc_get_product($product_id);
        if (!$reference_product) {
            return $cache[$cache_key] = array('products' => array(), 'title' => '', 'intro' => '');
        }

        $exclude_ids = array_merge(array($product_id), self::cart_product_ids());
        $reference_terms = self::product_term_slugs($product_id);
        $explicit = array_fill_keys(array_map('absint', array_merge($reference_product->get_cross_sell_ids(), $reference_product->get_upsell_ids())), true);
        $history = self::customer_product_history(self::current_customer_email());
        $scored = array();

        foreach (self::modal_candidate_products($exclude_ids) as $candidate) {
            $candidate_id = $candidate->get_id();
            $terms = self::product_term_slugs($candidate_id);
            $score = !empty($explicit[$candidate_id]) ? 100 : 0;

            if (array_intersect($terms, self::addon_term_slugs())) {
                $score += 35;
            }
            if (array_intersect($terms, $reference_terms)) {
                $score += 12;
            }
            $sales = absint(get_post_meta($candidate_id, 'total_sales', true));
            if ($sales > 0) {
                $score += min(40, (int) floor($sales / 3));
            }
            $history_match = !empty($history[$candidate_id]);
            if ($history_match) {
                $score += 120 + min(60, ((int) $history[$candidate_id]['qty']) * 18);
                $score += !empty($history[$candidate_id]['recent']) ? 18 : 0;
            }
            if ((float) $candidate->get_price() > 0 && (float) $candidate->get_price() <= 5) {
                $score += 8;
            }
            if ($score <= 0) {
                $score = 1;
            }

            $scored[] = array(
                'product' => $candidate,
                'score' => $score,
                'history_match' => $history_match,
            );
        }

        usort($scored, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return strcasecmp($a['product']->get_name(), $b['product']->get_name());
            }
            return $b['score'] <=> $a['score'];
        });

        $products = array_slice($scored, 0, max(1, $limit));
        $history_hits = count(array_filter($products, function ($row) {
            return !empty($row['history_match']);
        }));

        return $cache[$cache_key] = array(
            'products' => $products,
            'title' => $history_hits
                ? __('Based on your past orders', 'takeaway-os')
                : __('Popular extras for this meal', 'takeaway-os'),
            'intro' => $history_hits
                ? __('We found quick add-ons you have ordered before and that still fit this meal.', 'takeaway-os')
                : __('Top-selling sides, drinks and extras that pair well with this item.', 'takeaway-os'),
        );
    }

    private static function modal_candidate_products(array $exclude_ids): array {
        static $catalog = null;
        if ($catalog === null) {
            $catalog = function_exists('wc_get_products') ? wc_get_products(array(
                'status'       => 'publish',
                'limit'        => 80,
                'stock_status' => 'instock',
                'orderby'      => 'popularity',
                'order'        => 'DESC',
            )) : array();
        }

        $exclude_ids = array_fill_keys(array_map('absint', $exclude_ids), true);
        return array_values(array_filter($catalog, function ($candidate) use ($exclude_ids) {
            return $candidate
                && !empty($candidate)
                && !isset($exclude_ids[$candidate->get_id()])
                && self::is_quick_add_recommendable_product($candidate->get_id(), $candidate);
        }));
    }

    private static function is_quick_add_recommendable_product(int $product_id, $product = null): bool {
        $product = $product ?: wc_get_product($product_id);
        if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
            return false;
        }
        if (method_exists($product, 'is_type') && ($product->is_type('variable') || $product->is_type('grouped') || $product->is_type('external'))) {
            return false;
        }
        return empty(self::get_option_groups($product_id));
    }

    private static function cart_product_ids(): array {
        if (!function_exists('WC') || !WC()->cart) {
            return array();
        }
        $ids = array();
        foreach (WC()->cart->get_cart() as $cart_item) {
            $ids[] = absint($cart_item['product_id'] ?? 0);
        }
        return array_values(array_unique(array_filter($ids)));
    }

    private static function product_term_slugs(int $product_id): array {
        $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
        return is_wp_error($terms) ? array() : array_values(array_unique(array_filter($terms)));
    }

    private static function addon_term_slugs(): array {
        return array('side', 'sides', 'drink', 'drinks', 'dessert', 'desserts', 'dip', 'dips', 'sauce', 'sauces', 'extras', 'meal-deals');
    }

    private static function current_customer_email(): string {
        static $email = null;
        if ($email !== null) {
            return $email;
        }
        $email = '';
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $email = sanitize_email((string) ($user->user_email ?? ''));
        }
        if ($email === '' && function_exists('WC') && WC()->customer) {
            $email = sanitize_email((string) WC()->customer->get_billing_email());
        }
        return $email;
    }

    private static function customer_product_history(string $email): array {
        static $cache = array();
        if ($email === '') {
            return array();
        }
        if (isset($cache[$email])) {
            return $cache[$email];
        }
        if (!function_exists('wc_get_orders')) {
            return $cache[$email] = array();
        }

        $orders = wc_get_orders(array(
            'billing_email' => $email,
            'status'        => array('wc-completed', 'wc-processing', 'wc-ttos-accepted', 'wc-ttos-prepping', 'wc-ttos-ready', 'wc-ttos-out'),
            'limit'         => 25,
            'orderby'       => 'date',
            'order'         => 'DESC',
            'return'        => 'objects',
        ));
        $history = array();
        foreach ($orders as $index => $order) {
            if (!$order || !method_exists($order, 'get_items')) {
                continue;
            }
            foreach ($order->get_items('line_item') as $item) {
                $candidate_id = absint($item->get_product_id());
                if (!$candidate_id) {
                    continue;
                }
                if (!isset($history[$candidate_id])) {
                    $history[$candidate_id] = array('qty' => 0, 'recent' => false);
                }
                $history[$candidate_id]['qty'] += max(1, absint($item->get_quantity()));
                if ($index < 5) {
                    $history[$candidate_id]['recent'] = true;
                }
            }
        }
        return $cache[$email] = $history;
    }

    private static function checkout_estimate_text(): string {
        $delivery = self::site_content('delivery_collection', 'delivery_estimate_text');
        $collection = self::site_content('delivery_collection', 'collection_estimate_text');
        $trading = class_exists('TTOS_Settings') ? TTOS_Settings::get('trading') : array();
        if ($delivery === '' && !empty($trading['delivery_time'])) {
            $delivery = sprintf(__('Delivery around %s minutes', 'takeaway-os'), $trading['delivery_time']);
        }
        if ($collection === '' && !empty($trading['prep_time'])) {
            $collection = sprintf(__('Collection ready in around %s minutes', 'takeaway-os'), $trading['prep_time']);
        }
        $bits = array_filter(array($delivery, $collection));
        return $bits ? implode(' · ', $bits) : __('Tracking opens once the kitchen receives your paid order.', 'takeaway-os');
    }

    private static function estimate_for_order($order): string {
        $requested = (string) $order->get_meta('_ttos_requested_time');
        if ($requested !== '' && $requested !== 'asap') {
            $ts = strtotime($requested);
            return $ts ? date_i18n('D j M H:i', $ts) : $requested;
        }
        $method = sanitize_key((string) $order->get_meta('_ttos_fulfilment_method'));
        $trading = class_exists('TTOS_Settings') ? TTOS_Settings::get('trading') : array();
        if ($method === 'collection') {
            $mins = absint($trading['prep_time'] ?? 25);
            return sprintf(_n('%d minute', '%d minutes', $mins, 'takeaway-os'), $mins);
        }
        $mins = absint($trading['delivery_time'] ?? 35);
        return sprintf(_n('%d minute', '%d minutes', $mins, 'takeaway-os'), $mins);
    }

    private static function tracker_page_url(): string {
        $page_id = absint(get_option('ttos_page_tracker', 0));
        if ($page_id) {
            $url = get_permalink($page_id);
            if ($url) {
                return (string) $url;
            }
        }
        return home_url('/order-tracker/');
    }

    private static function tracking_map_src(): string {
        $lat = self::site_content('contact_map', 'lat');
        $lng = self::site_content('contact_map', 'lng');
        if ($lat !== '' && $lng !== '') {
            $lat_f = (float) $lat;
            $lng_f = (float) $lng;
            $bbox = sprintf('%F,%F,%F,%F', $lng_f - 0.004, $lat_f - 0.002, $lng_f + 0.004, $lat_f + 0.002);
            return 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode($bbox) . '&layer=mapnik&marker=' . rawurlencode($lat_f . ',' . $lng_f);
        }
        $address = self::business_address();
        if ($address === '') {
            return '';
        }
        return 'https://maps.google.com/maps?q=' . rawurlencode($address) . '&z=14&output=embed';
    }

    private static function business_address(): string {
        $business = class_exists('TTOS_Site_Content') ? TTOS_Site_Content::get('business_info') : array();
        if (!is_array($business)) {
            $business = array();
        }
        $settings = class_exists('TTOS_Settings') ? TTOS_Settings::get('business') : array();
        $parts = array();
        foreach (array('address_1', 'address_2', 'town', 'county', 'postcode') as $key) {
            $value = (string) (($business[$key] ?? '') ?: ($settings[$key] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }
        return implode(', ', $parts);
    }

    private static function site_content(string $section, string $key): string {
        if (!class_exists('TTOS_Site_Content')) {
            return '';
        }
        $value = TTOS_Site_Content::get($section, $key, '');
        return is_scalar($value) ? trim((string) $value) : '';
    }

    // -------------------------------------------------------------------------
    // Cash on Delivery
    // -------------------------------------------------------------------------

    /**
     * Ensure the WooCommerce COD gateway class is registered even when the
     * built-in option is not yet set. The option-level default written by
     * TTOS_Onboarding::apply_profile() is what actually enables it; this filter
     * just guarantees the class is in the registered list so WooCommerce can see
     * it on the checkout and in WC > Settings > Payments.
     */
    public static function ensure_cod_gateway(array $gateways): array {
        if (!in_array('WC_Gateway_COD', $gateways, true)) {
            $gateways[] = 'WC_Gateway_COD';
        }
        return $gateways;
    }

    // -------------------------------------------------------------------------
    // Service charge
    // -------------------------------------------------------------------------

    /**
     * Add a percentage-based service charge as a WooCommerce cart fee.
     * The rate is stored as a percentage in ttos_settings[trading][service_charge].
     * A value of 0 (or empty) means no fee is applied.
     */
    public static function apply_service_charge($cart): void {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        if (!$cart || !method_exists($cart, 'get_subtotal')) {
            return;
        }
        $trading = class_exists('TTOS_Settings') ? TTOS_Settings::get('trading') : array();
        $rate = (float) ($trading['service_charge'] ?? 0);
        if ($rate <= 0) {
            return;
        }
        $subtotal = (float) $cart->get_subtotal();
        if ($subtotal <= 0) {
            return;
        }
        $fee = round($subtotal * ($rate / 100), 2);
        $cart->add_fee(
            sprintf(__('Service charge (%s%%)', 'takeaway-os'), number_format($rate, 2)),
            $fee,
            false // not taxable by default; change to true if tax is required on the charge
        );
    }

    // -------------------------------------------------------------------------
    // Order comments / special instructions
    // -------------------------------------------------------------------------

    /**
     * Relabel the built-in WooCommerce order_comments field to "Special
     * Instructions / Allergies" and ensure it is visible (WooCommerce hides it
     * on some themes when there are no shipping methods).
     */
    public static function relabel_order_comments(array $fields): array {
        if (!isset($fields['order']['order_comments'])) {
            // Field absent — add it explicitly so it is always shown.
            $fields['order']['order_comments'] = array(
                'type'        => 'textarea',
                'label'       => __('Special Instructions / Allergies', 'takeaway-os'),
                'placeholder' => __('e.g. No onions, nut allergy, extra sauce…', 'takeaway-os'),
                'required'    => false,
                'class'       => array('notes'),
                'priority'    => 90,
            );
        } else {
            $fields['order']['order_comments']['label']       = __('Special Instructions / Allergies', 'takeaway-os');
            $fields['order']['order_comments']['placeholder'] = __('e.g. No onions, nut allergy, extra sauce…', 'takeaway-os');
        }
        return $fields;
    }

    // -------------------------------------------------------------------------
    // Previous order count (customer history)
    // -------------------------------------------------------------------------

    /**
     * Register a small meta box on the WooCommerce order edit screen showing how
     * many previous paid orders the billing-email customer has placed. Uses HPOS-
     * compatible wc_get_orders() — no WP_Query.
     */
    public static function register_customer_history_meta_box(): void {
        // HPOS registers order screens under the 'woocommerce_page_wc-orders' hook
        // as well as the legacy 'shop_order' post type.
        foreach (array('shop_order', 'woocommerce_page_wc-orders') as $screen) {
            add_meta_box(
                'ttos_customer_history',
                __('Customer history', 'takeaway-os'),
                array(__CLASS__, 'render_customer_history_meta_box'),
                $screen,
                'side',
                'default'
            );
        }
    }

    public static function render_customer_history_meta_box($post_or_order): void {
        if (!function_exists('wc_get_order')) {
            return;
        }
        // Accepts both a WC_Order (HPOS) and a WP_Post (legacy).
        $order = ($post_or_order instanceof WC_Order)
            ? $post_or_order
            : wc_get_order(is_object($post_or_order) ? $post_or_order->ID : absint($post_or_order));
        if (!$order) {
            return;
        }
        $email = sanitize_email((string) $order->get_billing_email());
        if ($email === '') {
            echo '<p>' . esc_html__('No billing email on this order.', 'takeaway-os') . '</p>';
            return;
        }
        $count = self::count_previous_orders($order->get_id(), $email);
        echo '<p>';
        echo wp_kses_post(
            sprintf(
                _n(
                    'This customer has placed <strong>%d previous order</strong>.',
                    'This customer has placed <strong>%d previous orders</strong>.',
                    $count,
                    'takeaway-os'
                ),
                $count
            )
        );
        echo '</p>';
        if ($count === 0) {
            echo '<p class="description">' . esc_html__('First-time customer.', 'takeaway-os') . '</p>';
        }
    }

    /**
     * Append the previous order count to WooCommerce order confirmation emails.
     * Only included in admin/kitchen emails (sent_to_admin === true).
     */
    public static function email_customer_order_count(array $fields, bool $sent_to_admin, $order): array {
        if (!$sent_to_admin) {
            return $fields;
        }
        if (!($order instanceof WC_Order)) {
            return $fields;
        }
        $email = sanitize_email((string) $order->get_billing_email());
        if ($email === '') {
            return $fields;
        }
        $count = self::count_previous_orders($order->get_id(), $email);
        $fields['ttos_customer_order_count'] = array(
            'label' => __('Customer order history', 'takeaway-os'),
            'value' => sprintf(
                _n('%d previous order', '%d previous orders', $count, 'takeaway-os'),
                $count
            ),
        );
        return $fields;
    }

    /**
     * Count completed/processing orders for a given email address, excluding the
     * current order. Uses wc_get_orders() for HPOS compatibility.
     */
    private static function count_previous_orders(int $current_order_id, string $email): int {
        if (!function_exists('wc_get_orders') || $email === '') {
            return 0;
        }
        $orders = wc_get_orders(array(
            'billing_email' => $email,
            'status'        => array('wc-completed', 'wc-processing', 'wc-ttos-accepted', 'wc-ttos-prepping', 'wc-ttos-ready', 'wc-ttos-out'),
            'limit'         => -1,
            'return'        => 'ids',
        ));
        // Exclude the current order from the count.
        $previous = array_filter((array) $orders, function ($id) use ($current_order_id) {
            return (int) $id !== $current_order_id;
        });
        return count($previous);
    }

}

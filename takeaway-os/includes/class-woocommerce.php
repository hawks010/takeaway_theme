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
        add_action('woocommerce_checkout_create_order_line_item', array(__CLASS__, 'save_configured_order_item_meta'), 10, 4);
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

}

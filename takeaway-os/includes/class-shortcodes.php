<?php

defined('ABSPATH') || exit;

final class TTOS_Shortcodes {
    public static function hooks(): void {
        add_shortcode('takeaway_menu', array(__CLASS__, 'menu'));
        add_shortcode('takeaway_customer_portal', array(__CLASS__, 'customer_portal'));
        add_shortcode('takeaway_order_tracker', array(__CLASS__, 'order_tracker'));
        add_shortcode('takeaway_delivery_checker', array(__CLASS__, 'delivery_checker'));
        add_shortcode('takeaway_allergens', array(__CLASS__, 'allergens'));
        add_shortcode('takeaway_kitchen_screen', array(__CLASS__, 'kitchen_screen'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function assets(): void {
        wp_enqueue_style('ttos-frontend', TTOS_URL . 'assets/frontend.css', array(), TTOS_VERSION);
        wp_enqueue_script('ttos-frontend', TTOS_URL . 'assets/frontend.js', array(), TTOS_VERSION, true);
    }

    public static function menu(): string {
        if (!TTOS_WooCommerce::active()) {
            return '<div class="ttos-front-card">WooCommerce is required before the menu can be displayed.</div>';
        }
        $cats = TTOS_WooCommerce::get_product_categories();
        ob_start();
        echo '<div class="ttos-menu-wrap"><div class="ttos-menu-head"><div><p>Order direct</p><h2>Menu</h2></div>' . self::delivery_checker() . '</div>';
        self::menu_tools();
        if ($cats) {
            echo '<div class="ttos-cat-tabs">';
            foreach ($cats as $cat) echo '<a href="#ttos-cat-' . esc_attr($cat->term_id) . '">' . esc_html($cat->name) . '</a>';
            echo '</div>';
        }
        $args = array('post_type' => 'product', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'menu_order title', 'order' => 'ASC');
        if ($cats) {
            foreach ($cats as $cat) self::products_for_category($cat);
        } else {
            self::products_loop($args);
        }
        self::sticky_cart_bar();
        echo '</div>';
        return ob_get_clean();
    }


    private static function menu_tools(): void {
        echo '<div class="ttos-menu-tools"><label class="ttos-menu-search"><span>Search menu</span><input type="search" class="ttos-menu-search-input" placeholder="Search kebab, pizza, chips…"></label>';
        if (taxonomy_exists('ttos_allergen') && TTOS_Settings::module_enabled('allergen_filters')) {
            $terms = get_terms(array('taxonomy' => 'ttos_allergen', 'hide_empty' => false));
            if (!is_wp_error($terms) && $terms) {
                echo '<div class="ttos-allergen-filter"><strong>Hide allergens:</strong>';
                foreach ($terms as $term) echo '<label><input type="checkbox" class="ttos-allergen-toggle" value="' . esc_attr($term->slug) . '"> ' . esc_html($term->name) . '</label>';
                echo '</div>';
            }
        }
        echo '</div>';
    }

    private static function sticky_cart_bar(): void {
        if (!function_exists('WC') || !WC()->cart) return;
        $count = WC()->cart->get_cart_contents_count();
        $subtotal = WC()->cart->get_cart_subtotal();
        echo '<aside class="ttos-sticky-cart" data-count="' . esc_attr((string) $count) . '"><div><strong>Your basket</strong><span>' . esc_html((string) $count) . ' item' . esc_html($count === 1 ? '' : 's') . ' · ' . wp_kses_post($subtotal) . '</span></div><a class="ttos-order-btn" href="' . esc_url(wc_get_cart_url()) . '">View basket</a></aside>';
    }

    private static function products_for_category($cat): void {
        echo '<section id="ttos-cat-' . esc_attr($cat->term_id) . '" class="ttos-menu-section"><h3>' . esc_html($cat->name) . '</h3>';
        self::products_loop(array('post_type' => 'product', 'posts_per_page' => -1, 'post_status' => 'publish', 'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cat->term_id))));
        echo '</section>';
    }

    private static function products_loop(array $args): void {
        $q = new WP_Query($args);
        echo '<div class="ttos-product-grid">';
        while ($q->have_posts()) { $q->the_post();
            $id = get_the_ID();
            $price = get_post_meta($id, '_price', true);
            $stock = get_post_meta($id, '_stock_status', true);
            $img = get_the_post_thumbnail_url($id, 'medium_large') ?: get_post_meta($id, '_ttos_image_url', true);
            $allergens = get_post_meta($id, '_ttos_allergens', true);
            $allergen_slugs = array();
            if (taxonomy_exists('ttos_allergen')) {
                $term_names = wp_get_object_terms($id, 'ttos_allergen', array('fields' => 'names'));
                $term_slugs = wp_get_object_terms($id, 'ttos_allergen', array('fields' => 'slugs'));
                if (!is_wp_error($term_names) && $term_names) $allergens = implode(', ', $term_names);
                if (!is_wp_error($term_slugs) && $term_slugs) $allergen_slugs = $term_slugs;
            }
            $badges = get_post_meta($id, '_ttos_badges', true);
            echo '<article class="ttos-product ' . ($stock === 'outofstock' ? 'is-sold' : '') . '" data-title="' . esc_attr(strtolower(get_the_title() . ' ' . wp_strip_all_tags(get_the_excerpt() ?: get_the_content()))) . '" data-allergens="' . esc_attr(implode(',', $allergen_slugs)) . '">';
            if ($img) echo '<img src="' . esc_url($img) . '" alt="' . esc_attr(get_the_title()) . '">';
            echo '<div class="ttos-product-body"><div class="ttos-product-title"><h4>' . esc_html(get_the_title()) . '</h4><strong>' . (function_exists('wc_price') ? wp_kses_post(wc_price((float) $price)) : '£' . esc_html($price)) . '</strong></div>';
            if ($badges) echo '<p class="ttos-badges">' . esc_html($badges) . '</p>';
            echo '<p>' . esc_html(wp_trim_words(get_the_excerpt() ?: get_the_content(), 18)) . '</p>';
            if ($allergens) echo '<small>Allergens: ' . esc_html($allergens) . '</small>';
            if ($stock === 'outofstock') {
                echo '<button disabled>Sold out today</button>';
            } else {
                self::add_to_basket_form($id);
            }
            echo '</div></article>';
        }
        wp_reset_postdata();
        echo '</div>';
    }


    private static function add_to_basket_form(int $product_id): void {
        $groups = TTOS_WooCommerce::get_option_groups($product_id);
        $modal_id = 'ttos-config-modal-' . $product_id . '-' . wp_rand(100, 999);

        if ($groups) {
            echo '<button type="button" class="ttos-order-btn ttos-open-config" data-modal="#' . esc_attr($modal_id) . '">Configure item</button>';
            echo '<div class="ttos-modal" id="' . esc_attr($modal_id) . '" aria-hidden="true"><div class="ttos-modal-backdrop" data-close-modal></div><div class="ttos-modal-panel" role="dialog" aria-modal="true" aria-label="Configure ' . esc_attr(get_the_title($product_id)) . '"><button type="button" class="ttos-modal-close" data-close-modal>×</button>';
            echo '<h3>' . esc_html(get_the_title($product_id)) . '</h3><p class="ttos-muted">Choose options, add notes, then add to basket.</p>';
        }

        $base_price = (float) get_post_meta($product_id, '_price', true);
        echo '<form class="ttos-config-form" method="post" data-base-price="' . esc_attr((string) $base_price) . '">';
        echo '<input type="hidden" name="add-to-cart" value="' . esc_attr((string) $product_id) . '">';
        echo '<input type="hidden" name="ttos_configured_add" value="1">';
        echo '<label class="ttos-qty">Qty <input type="number" name="quantity" value="1" min="1" max="20"></label>';
        wp_nonce_field('ttos_configure_product_' . $product_id, 'ttos_config_nonce');
        if ($groups) {
            foreach ($groups as $g_index => $group) {
                if (!is_array($group) || empty($group['options'])) continue;
                $type = ($group['type'] ?? 'multiple') === 'single' ? 'radio' : 'checkbox';
                $name = $type === 'radio' ? 'ttos_options[' . esc_attr((string) $g_index) . ']' : 'ttos_options[' . esc_attr((string) $g_index) . '][]';
                echo '<fieldset class="ttos-config-fieldset"><legend>' . esc_html($group['name'] ?? 'Options') . (!empty($group['required']) ? ' *' : '') . '</legend>';
                if (!empty($group['min']) || !empty($group['max'])) {
                    echo '<small class="ttos-choice-rule">Choose ' . esc_html((string) ($group['min'] ?? 0)) . '–' . esc_html((string) ($group['max'] ?? ($type === 'radio' ? 1 : 99))) . '</small>';
                }
                foreach ($group['options'] as $option) {
                    if (!is_array($option) || empty($option['label'])) continue;
                    $disabled = !empty($option['sold_out']);
                    $price = (float) ($option['price'] ?? 0);
                    echo '<label class="ttos-config-option"><input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($option['label']) . '" data-price="' . esc_attr((string) $price) . '" ' . checked(!empty($option['default']), true, false) . ' ' . disabled($disabled, true, false) . '> <span>' . esc_html($option['label']) . ($price > 0 ? ' +' . wp_strip_all_tags(wc_price($price)) : '') . ($disabled ? ' - sold out' : '') . '</span></label>';
                }
                echo '</fieldset>';
            }
        }
        echo '<label class="ttos-item-note">Notes <textarea name="ttos_item_note" rows="2" placeholder="No onion, sauce separate…"></textarea></label>';
        echo '<div class="ttos-config-total"><span>Total from</span><strong></strong></div><button class="ttos-order-btn" type="submit">Add to basket</button>';
        echo '</form>';

        if ($groups) {
            echo '</div></div>';
        }
    }

    public static function delivery_checker(): string {
        $trading = TTOS_Settings::get('trading');
        ob_start();
        echo '<form class="ttos-delivery-check" onsubmit="event.preventDefault();this.querySelector(\'.ttos-check-result\').textContent=\'Looks good. We will confirm at checkout.\';"><input maxlength="9" placeholder="Enter postcode"><button>Check</button><span class="ttos-check-result">Min order £' . esc_html($trading['min_order']) . ' · ~' . esc_html($trading['delivery_time']) . ' mins</span></form>';
        return ob_get_clean();
    }

    public static function customer_portal(): string {
        if (!is_user_logged_in()) {
            return '<div class="ttos-front-card"><h2>Your takeaway account</h2><p>Log in to view previous orders, saved addresses and rewards.</p>' . wp_login_form(array('echo' => false)) . '</div>';
        }
        ob_start();
        echo '<div class="ttos-portal"><h2>My orders</h2>';
        if (TTOS_WooCommerce::active()) {
            $orders = wc_get_orders(array('customer_id' => get_current_user_id(), 'limit' => 10));
            if ($orders) {
                echo '<div class="ttos-order-list">';
                foreach ($orders as $order) {
                    echo '<div class="ttos-front-card"><strong>#' . esc_html($order->get_id()) . '</strong><span>' . esc_html(wc_get_order_status_name($order->get_status())) . '</span><b>' . wp_kses_post($order->get_formatted_order_total()) . '</b></div>';
                }
                echo '</div>';
            } else echo '<p>No orders yet.</p>';
        }
        if (TTOS_Settings::module_enabled('loyalty') && class_exists('TTOS_Features')) echo do_shortcode('[takeaway_rewards]');
        self::sticky_cart_bar();
        echo '</div>';
        return ob_get_clean();
    }

    public static function order_tracker(): string {
        $message = '';
        if (!empty($_GET['ttos_order_id']) && TTOS_WooCommerce::active()) {
            $order = wc_get_order(absint($_GET['ttos_order_id']));
            if ($order) $message = '<div class="ttos-front-card"><h3>Order #' . esc_html($order->get_id()) . '</h3><p>Status: <strong>' . esc_html(wc_get_order_status_name($order->get_status())) . '</strong></p></div>';
        }
        return '<div class="ttos-front-card"><h2>Track your order</h2><form><input name="ttos_order_id" placeholder="Order number"><button>Track</button></form></div>' . $message;
    }

    public static function allergens(): string {
        return '<div class="ttos-front-card"><h2>Allergen information</h2><p>Please check each product card and contact the restaurant before ordering if you have a serious allergy.</p><p>Advanced filtering uses the 14 UK allergen groups stored on each menu item.</p></div>';
    }

    public static function kitchen_screen(): string {
        if (!current_user_can('ttos_view_orders') || !TTOS_WooCommerce::active()) return '';
        $orders = wc_get_orders(array('limit' => 15, 'status' => array('processing','ttos-accepted','ttos-prepping','ttos-ready')));
        ob_start();
        echo '<div class="ttos-kitchen-screen"><h2>Kitchen screen</h2>';
        foreach ($orders as $order) {
            echo '<div class="ttos-kitchen-ticket"><h3>#' . esc_html($order->get_id()) . ' · ' . esc_html(wc_get_order_status_name($order->get_status())) . '</h3><ul>';
            foreach ($order->get_items() as $item) echo '<li>' . esc_html($item->get_quantity()) . ' × ' . esc_html($item->get_name()) . '</li>';
            echo '</ul></div>';
        }
        self::sticky_cart_bar();
        echo '</div>';
        return ob_get_clean();
    }
}

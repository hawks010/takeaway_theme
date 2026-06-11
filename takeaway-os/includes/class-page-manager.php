<?php

defined('ABSPATH') || exit;

final class TTOS_Page_Manager {
    public static function specs(): array {
        return array(
            'home' => array(
                'title' => 'Home',
                'slug' => 'home',
                'content' => '[takeaway_home_blocks]',
                'label' => 'Homepage',
            ),
            'menu' => array(
                'title' => 'Menu',
                'slug' => 'menu',
                'content' => '[takeaway_menu]',
                'label' => 'Menu / ordering page',
            ),
            'cart' => array(
                'title' => 'Basket',
                'slug' => 'basket',
                'content' => '[woocommerce_cart]',
                'label' => 'Basket page',
            ),
            'checkout' => array(
                'title' => 'Checkout',
                'slug' => 'checkout',
                'content' => '[woocommerce_checkout]',
                'label' => 'Checkout page',
            ),
            'account' => array(
                'title' => 'My Account',
                'slug' => 'my-account',
                'content' => '[woocommerce_my_account]',
                'label' => 'Customer account page',
            ),
            'tracker' => array(
                'title' => 'Order Tracker',
                'slug' => 'order-tracker',
                'content' => '[takeaway_order_tracker]',
                'label' => 'Order tracking page',
            ),
            'allergens' => array(
                'title' => 'Allergen Information',
                'slug' => 'allergen-information',
                'content' => '[takeaway_allergens]',
                'label' => 'Allergen information page',
            ),
            'delivery' => array(
                'title' => 'Delivery Checker',
                'slug' => 'delivery-checker',
                'content' => '[takeaway_delivery_checker]',
                'label' => 'Delivery checker page',
            ),
            'meal_deals' => array(
                'title' => 'Meal Deals',
                'slug' => 'meal-deals',
                'content' => '[takeaway_meal_deals]',
                'label' => 'Meal deals page',
            ),
            'rewards' => array(
                'title' => 'Rewards',
                'slug' => 'rewards',
                'content' => '[takeaway_rewards]',
                'label' => 'Customer rewards page',
            ),
            'contact' => array(
                'title' => 'Contact',
                'slug' => 'contact',
                'content' => '[takeaway_contact]',
                'label' => 'Contact page',
            ),
            'policy_privacy' => array(
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'content' => '[takeaway_policy key="privacy"]',
                'label' => 'Privacy policy page',
            ),
            'policy_cookies' => array(
                'title' => 'Cookie Policy',
                'slug' => 'cookie-policy',
                'content' => '[takeaway_policy key="cookies"]',
                'label' => 'Cookie policy page',
            ),
            'policy_terms' => array(
                'title' => 'Terms & Conditions',
                'slug' => 'terms-and-conditions',
                'content' => '[takeaway_policy key="terms"]',
                'label' => 'Terms & conditions page',
            ),
            'policy_refunds' => array(
                'title' => 'Refunds & Cancellations',
                'slug' => 'refunds-cancellations',
                'content' => '[takeaway_policy key="refunds"]',
                'label' => 'Refunds policy page',
            ),
            'policy_delivery' => array(
                'title' => 'Delivery Policy',
                'slug' => 'delivery-policy',
                'content' => '[takeaway_policy key="delivery"]',
                'label' => 'Delivery policy page',
            ),
            'policy_accessibility' => array(
                'title' => 'Accessibility Statement',
                'slug' => 'accessibility-statement',
                'content' => '[takeaway_policy key="accessibility"]',
                'label' => 'Accessibility statement page',
            ),
            'policy_hygiene' => array(
                'title' => 'Food Hygiene & Safety',
                'slug' => 'food-hygiene',
                'content' => '[takeaway_policy key="hygiene"]',
                'label' => 'Food hygiene page',
            ),
            'policy_business' => array(
                'title' => 'Business Details',
                'slug' => 'business-details',
                'content' => '[takeaway_policy key="contact_details"]',
                'label' => 'Business details page',
            ),
        );
    }

    public static function marker(string $key): string {
        return '<!-- takeaway-os:' . sanitize_key($key) . ' -->';
    }

    public static function full_content(string $key, array $spec): string {
        $key = sanitize_key($key);
        return self::marker($key) . "\n" . '<div class="ttos-generated-page ttos-generated-page-' . esc_attr($key) . '">' . "\n" . $spec['content'] . "\n" . '</div>';
    }

    public static function option_name(string $key): string {
        return 'ttos_page_' . sanitize_key($key);
    }

    public static function get_page_id(string $key): int {
        $specs = self::specs();
        if (!isset($specs[$key])) return 0;
        $option = absint(get_option(self::option_name($key), 0));
        if ($option && get_post_type($option) === 'page') return $option;
        $by_slug = get_page_by_path($specs[$key]['slug']);
        if ($by_slug && $by_slug->post_type === 'page') return (int) $by_slug->ID;
        $by_title = get_page_by_title($specs[$key]['title'], OBJECT, 'page');
        return $by_title ? (int) $by_title->ID : 0;
    }

    public static function content_ready(int $page_id, string $key, array $spec): bool {
        if (!$page_id) return false;
        $content = (string) get_post_field('post_content', $page_id);
        if (strpos($content, self::marker($key)) !== false) return true;
        if (strpos($content, $spec['content']) !== false) return true;
        return false;
    }

    public static function status(string $key): array {
        $specs = self::specs();
        if (!isset($specs[$key])) return array('state' => 'missing', 'id' => 0, 'message' => 'Unknown page');
        $spec = $specs[$key];
        $id = self::get_page_id($key);
        if (!$id) return array('state' => 'missing', 'id' => 0, 'message' => 'Missing');
        update_option(self::option_name($key), $id, false);
        if (self::content_ready($id, $key, $spec)) return array('state' => 'ready', 'id' => $id, 'message' => 'Ready');
        return array('state' => 'needs_content', 'id' => $id, 'message' => 'Page exists, but Takeaway content is missing');
    }

    public static function ensure(string $key, string $mode = 'fresh_if_unsafe'): int {
        $specs = self::specs();
        if (!isset($specs[$key])) return 0;
        $spec = $specs[$key];
        $status = self::status($key);
        if ($status['state'] === 'ready') return (int) $status['id'];

        if ($status['state'] === 'needs_content' && $mode === 'append') {
            $id = (int) $status['id'];
            $old = (string) get_post_field('post_content', $id);
            wp_update_post(array('ID' => $id, 'post_content' => rtrim($old) . "\n\n" . self::full_content($key, $spec)));
            update_option(self::option_name($key), $id, false);
            return $id;
        }

        if ($status['state'] === 'needs_content' && $mode === 'replace') {
            $id = (int) $status['id'];
            wp_update_post(array('ID' => $id, 'post_content' => self::full_content($key, $spec)));
            update_option(self::option_name($key), $id, false);
            update_post_meta($id, '_ttos_generated_page', '1');
            update_post_meta($id, '_ttos_page_key', sanitize_key($key));
            return $id;
        }

        $slug = $spec['slug'];
        if ($status['state'] === 'needs_content') {
            $slug = 'takeaway-' . $slug;
        }
        $id = wp_insert_post(array(
            'post_title'   => $status['state'] === 'needs_content' ? 'Takeaway ' . $spec['title'] : $spec['title'],
            'post_name'    => $slug,
            'post_content' => self::full_content($key, $spec),
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ));
        if (!is_wp_error($id)) {
            update_option(self::option_name($key), (int) $id, false);
            update_post_meta((int) $id, '_ttos_generated_page', '1');
            update_post_meta((int) $id, '_ttos_page_key', sanitize_key($key));
            self::maybe_assign_woocommerce_pages($key, (int) $id);
            return (int) $id;
        }
        return 0;
    }

    public static function ensure_all(string $mode = 'fresh_if_unsafe'): array {
        $results = array();
        foreach (self::specs() as $key => $spec) {
            $results[$key] = self::ensure($key, $mode);
        }
        self::finalize_site_structure();
        return $results;
    }


    public static function summary(): array {
        $counts = array('total' => 0, 'ready' => 0, 'missing' => 0, 'needs_content' => 0);
        $rows = array();
        foreach (self::specs() as $key => $spec) {
            $counts['total']++;
            $status = self::status($key);
            $state = $status['state'] ?? 'missing';
            if (!isset($counts[$state])) $counts[$state] = 0;
            $counts[$state]++;
            $rows[$key] = $status;
        }
        $counts['problem_count'] = $counts['missing'] + $counts['needs_content'];
        return array('counts' => $counts, 'rows' => $rows);
    }

    public static function maybe_assign_front_page(string $key, int $page_id): void {
        if (!$page_id || $key !== 'home') return;
        update_option('show_on_front', 'page');
        update_option('page_on_front', $page_id);
        update_option('page_for_posts', 0);
    }

    public static function maybe_assign_woocommerce_pages(string $key, int $page_id): void {
        if (!$page_id) return;
        if ($key === 'menu') update_option('woocommerce_shop_page_id', $page_id);
        if ($key === 'cart') update_option('woocommerce_cart_page_id', $page_id);
        if ($key === 'checkout') update_option('woocommerce_checkout_page_id', $page_id);
        if ($key === 'account') update_option('woocommerce_myaccount_page_id', $page_id);
    }

    public static function sync_front_page_option(): void {
        $home_id = self::get_page_id('home');
        if ($home_id) self::maybe_assign_front_page('home', $home_id);
    }

    public static function sync_woocommerce_page_options(): void {
        foreach (array('menu', 'cart', 'checkout', 'account') as $key) {
            $id = self::get_page_id($key);
            if ($id) self::maybe_assign_woocommerce_pages($key, $id);
        }
    }

    public static function ensure_theme_menus(): void {
        if (!function_exists('wp_create_nav_menu') || !function_exists('wp_update_nav_menu_item')) {
            return;
        }

        $primary_id = self::ensure_nav_menu('Takeaway Primary', array('home', 'menu', 'meal_deals', 'rewards', 'tracker'));
        $footer_id  = self::ensure_nav_menu('Takeaway Footer', array('menu', 'delivery', 'allergens', 'cart', 'checkout', 'account'));

        $locations = get_theme_mod('nav_menu_locations', array());
        if (!is_array($locations)) $locations = array();
        if ($primary_id && empty($locations['primary'])) $locations['primary'] = $primary_id;
        if ($footer_id && empty($locations['footer'])) $locations['footer'] = $footer_id;
        set_theme_mod('nav_menu_locations', $locations);
    }

    private static function ensure_nav_menu(string $name, array $page_keys): int {
        $menu = wp_get_nav_menu_object($name);
        if (!$menu) {
            $created = wp_create_nav_menu($name);
            if (is_wp_error($created)) return 0;
            $menu_id = (int) $created;
        } else {
            $menu_id = (int) $menu->term_id;
        }

        $existing = wp_get_nav_menu_items($menu_id);
        $existing_objects = array();
        if (is_array($existing)) {
            foreach ($existing as $item) {
                if (!empty($item->object_id)) $existing_objects[] = (int) $item->object_id;
            }
        }

        foreach ($page_keys as $key) {
            $id = self::get_page_id($key);
            if (!$id || in_array($id, $existing_objects, true)) continue;
            wp_update_nav_menu_item($menu_id, 0, array(
                'menu-item-title'     => get_the_title($id),
                'menu-item-object-id' => $id,
                'menu-item-object'    => 'page',
                'menu-item-type'      => 'post_type',
                'menu-item-status'    => 'publish',
            ));
        }

        return $menu_id;
    }

    public static function finalize_site_structure(): void {
        self::sync_front_page_option();
        self::sync_woocommerce_page_options();
        self::ensure_theme_menus();
    }
}

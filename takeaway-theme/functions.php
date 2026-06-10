<?php

defined('ABSPATH') || exit;

define('TTHEME_VERSION', '0.2.6');
define('TTHEME_DIR', get_template_directory());
define('TTHEME_URL', get_template_directory_uri());

require_once TTHEME_DIR . '/inc/setup.php';
require_once TTHEME_DIR . '/inc/enqueue.php';
require_once TTHEME_DIR . '/inc/plugin-checklist.php';

add_action('after_setup_theme', 'ttheme_setup');
add_action('wp_enqueue_scripts', 'ttheme_enqueue');
add_action('admin_menu', 'ttheme_admin_menu');
add_action('admin_enqueue_scripts', 'ttheme_admin_assets');
add_action('admin_notices', 'ttheme_global_setup_modal');
add_action('after_switch_theme', 'ttheme_after_switch_theme');
add_action('admin_init', 'ttheme_activation_redirect');

function ttheme_after_switch_theme(): void {
    if (is_admin() && current_user_can('switch_themes')) {
        update_option('ttheme_needs_setup_redirect', time(), false);
        update_option('ttheme_setup_modal_pending', time(), false);
    }
}

function ttheme_activation_redirect(): void {
    if (!is_admin() || wp_doing_ajax()) return;
    if (!current_user_can('switch_themes')) return;
    if (!get_option('ttheme_needs_setup_redirect')) return;
    delete_option('ttheme_needs_setup_redirect');
    wp_safe_redirect(admin_url('themes.php?page=takeaway-theme-setup&ttheme_welcome=1'));
    exit;
}

function ttheme_business($key = '', $fallback = '') {
    $settings = get_option('ttos_settings', array());
    $business = isset($settings['business']) && is_array($settings['business']) ? $settings['business'] : array();
    if ($key === '') return $business;
    return isset($business[$key]) && $business[$key] !== '' ? $business[$key] : $fallback;
}

function ttheme_brand($key = '', $fallback = '') {
    $settings = get_option('ttos_settings', array());
    $brand = isset($settings['branding']) && is_array($settings['branding']) ? $settings['branding'] : array();
    if ($key === '') return $brand;
    return isset($brand[$key]) && $brand[$key] !== '' ? $brand[$key] : $fallback;
}


function ttheme_page_url(string $key, string $fallback = '/'): string {
    $id = absint(get_option('ttos_page_' . sanitize_key($key), 0));
    if ($id && get_post_type($id) === 'page') {
        return (string) get_permalink($id);
    }
    return home_url($fallback);
}

function ttheme_fallback_nav(string $location = 'primary'): void {
    $keys = $location === 'footer'
        ? array('menu' => 'Menu', 'delivery' => 'Delivery', 'allergens' => 'Allergens', 'account' => 'My Account')
        : array('home' => 'Home', 'menu' => 'Menu', 'meal_deals' => 'Meal Deals', 'rewards' => 'Rewards', 'tracker' => 'Track Order');
    echo '<ul class="tt-fallback-nav">';
    foreach ($keys as $key => $label) {
        $url = $key === 'home' ? home_url('/') : ttheme_page_url($key, '/' . str_replace('_', '-', $key) . '/');
        echo '<li><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
    }
    echo '</ul>';
}

add_filter('body_class', 'ttheme_body_classes');
function ttheme_body_classes(array $classes): array {
    $skin = sanitize_key(ttheme_brand('style_skin', 'charcoal'));
    $classes[] = 'tt-skin-' . ($skin ?: 'charcoal');
    if (function_exists('WC') && WC()->cart && WC()->cart->get_cart_contents_count() > 0) {
        $classes[] = 'tt-has-cart';
    }
    return $classes;
}

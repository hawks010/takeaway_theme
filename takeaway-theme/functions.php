<?php

defined('ABSPATH') || exit;

define('TTHEME_VERSION', '0.3.34');
define('TTHEME_DIR', get_template_directory());
define('TTHEME_URL', get_template_directory_uri());

require_once TTHEME_DIR . '/inc/theme-updater.php';
require_once TTHEME_DIR . '/inc/setup.php';
require_once TTHEME_DIR . '/inc/enqueue.php';
require_once TTHEME_DIR . '/inc/performance.php';
require_once TTHEME_DIR . '/inc/template-helpers.php';
require_once TTHEME_DIR . '/inc/plugin-checklist.php';
require_once TTHEME_DIR . '/inc/contact.php';
require_once TTHEME_DIR . '/inc/account.php';
require_once TTHEME_DIR . '/inc/amh-accessibility-toolkit.php';
require_once TTHEME_DIR . '/inc/amh-accessibility-universal.php';
require_once TTHEME_DIR . '/inc/back-to-top.php';

// Live basket updates in the header via WooCommerce cart fragments.
add_filter('woocommerce_add_to_cart_fragments', function (array $fragments): array {
    // Fragment 1: count badge.
    ob_start();
    tt_cart_count_badge();
    $fragments['.tt-cart-count'] = ob_get_clean();

    // Fragment 2: basket preview panel (line items, subtotal, CTAs).
    $inner = tt_cart_preview_html();
    $fragments['div#tt-cart-preview'] = '<div class="tt-cart-preview" id="tt-cart-preview"'
        . ' role="region" aria-label="' . esc_attr__('Basket preview', 'takeaway-theme') . '">'
        . $inner . '</div>';

    return $fragments;
});

add_action('woocommerce_before_customer_login_form', function (): void {
    $phone = tt_phone();
    $email = tt_email();
    $address = tt_address_lines();
    $socials = tt_social_links();
    ?>
    <section class="tt-auth-shell" aria-label="<?php esc_attr_e('Customer account access', 'takeaway-theme'); ?>">
        <aside class="tt-auth-brand">
            <p class="tt-auth-kicker"><?php esc_html_e('Order direct', 'takeaway-theme'); ?></p>
            <h2><?php esc_html_e('Your takeaway account', 'takeaway-theme'); ?></h2>
            <p><?php esc_html_e('Save your details, track orders faster, and keep rewards in one place.', 'takeaway-theme'); ?></p>
            <div class="tt-auth-contact">
                <?php if ($phone) : ?>
                    <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a>
                <?php endif; ?>
                <?php if ($email) : ?>
                    <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                <?php endif; ?>
                <?php if ($address) : ?>
                    <span><?php echo esc_html(implode(', ', $address)); ?></span>
                <?php endif; ?>
            </div>
            <?php if ($socials) : ?>
                <div class="tt-auth-socials" aria-label="<?php esc_attr_e('Social links', 'takeaway-theme'); ?>">
                    <?php foreach ($socials as $label => $url) : ?>
                        <a href="<?php echo esc_url($url); ?>" rel="noopener noreferrer" target="_blank"><?php echo esc_html($label); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>
        <div class="tt-auth-forms">
    <?php
});

add_action('woocommerce_after_customer_login_form', function (): void {
    echo '</div></section>';
});

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
        : array('menu' => 'Menu', 'meal_deals' => 'Meal Deals', 'rewards' => 'Rewards', 'contact' => 'Contact');
    echo '<ul class="tt-fallback-nav">';
    foreach ($keys as $key => $label) {
        $url = ttheme_page_url($key, '/' . str_replace('_', '-', $key) . '/');
        echo '<li><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
    }
    echo '</ul>';
}

// Remove the home-page item from the primary registered nav (logo already links home).
add_filter('wp_nav_menu_objects', function (array $items, $args): array {
    if (!isset($args->theme_location) || $args->theme_location !== 'primary') return $items;
    $home = trailingslashit(home_url('/'));
    return array_values(array_filter($items, function ($item) use ($home) {
        return trailingslashit((string) $item->url) !== $home;
    }));
}, 10, 2);

add_filter('body_class', 'ttheme_body_classes');
function ttheme_body_classes(array $classes): array {
    $skin = sanitize_key(ttheme_brand('style_skin', 'charcoal'));
    $classes[] = 'tt-skin-' . ($skin ?: 'charcoal');
    if (function_exists('WC') && WC()->cart && WC()->cart->get_cart_contents_count() > 0) {
        $classes[] = 'tt-has-cart';
    }
    return $classes;
}

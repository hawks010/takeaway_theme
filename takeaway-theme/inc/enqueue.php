<?php

defined('ABSPATH') || exit;

function ttheme_enqueue(): void {
    // Cascade: tokens (fallback values) -> base (primitives) -> theme (templates).
    // Takeaway OS prints live brand tokens inline in wp_head after these, so
    // saved branding always wins over the stylesheet fallbacks.
    wp_enqueue_style('takeaway-theme-tokens', TTHEME_URL . '/assets/css/tokens.css', array(), TTHEME_VERSION);
    wp_enqueue_style('takeaway-theme-base', TTHEME_URL . '/assets/css/base.css', array('takeaway-theme-tokens'), TTHEME_VERSION);
    wp_enqueue_style('takeaway-theme-header', TTHEME_URL . '/assets/css/header.css', array('takeaway-theme-base'), TTHEME_VERSION);
    wp_enqueue_style('takeaway-theme-footer', TTHEME_URL . '/assets/css/footer.css', array('takeaway-theme-base'), TTHEME_VERSION);
    if (is_front_page()) {
        wp_enqueue_style('takeaway-theme-home', TTHEME_URL . '/assets/css/home.css', array('takeaway-theme-base'), TTHEME_VERSION);
    }
    wp_enqueue_style('takeaway-theme', TTHEME_URL . '/assets/css/theme.css', array('takeaway-theme-base'), TTHEME_VERSION);

    $load_menu = false;
    $load_woo = false;

    if (class_exists('WooCommerce')) {
        $load_menu = function_exists('is_woocommerce') && is_woocommerce();
        $load_woo = $load_menu
            || (function_exists('is_cart') && is_cart())
            || (function_exists('is_checkout') && is_checkout())
            || (function_exists('is_account_page') && is_account_page());
    }

    if ($load_menu) {
        // Loaded after theme.css so the menu-specific tokens win over legacy rules.
        wp_enqueue_style('takeaway-theme-menu', TTHEME_URL . '/assets/css/menu.css', array('takeaway-theme'), TTHEME_VERSION);
    }

    if ($load_woo) {
        wp_enqueue_style('takeaway-theme-woo', TTHEME_URL . '/assets/css/woo.css', array('takeaway-theme'), TTHEME_VERSION);
    }

    wp_enqueue_style('takeaway-theme-utility', TTHEME_URL . '/assets/css/utility.css', array('takeaway-theme'), TTHEME_VERSION);
    wp_enqueue_script('takeaway-theme', TTHEME_URL . '/assets/js/theme.js', array(), TTHEME_VERSION, true);
}

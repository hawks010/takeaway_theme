<?php

defined('ABSPATH') || exit;

function ttheme_setup(): void {
    load_theme_textdomain('takeaway-theme', TTHEME_DIR . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', array('height' => 120, 'width' => 320, 'flex-height' => true, 'flex-width' => true));
    add_theme_support('woocommerce');
    add_theme_support('align-wide');
    register_nav_menus(array('primary' => __('Primary Menu', 'takeaway-theme'), 'footer' => __('Footer Menu', 'takeaway-theme')));
}

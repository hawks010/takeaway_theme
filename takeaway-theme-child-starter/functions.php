<?php

defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function (): void {
    $theme = wp_get_theme();
    $version = $theme instanceof WP_Theme ? (string) $theme->get('Version') : '0.1.0';

    wp_enqueue_style(
        'takeaway-theme-client-child',
        get_stylesheet_directory_uri() . '/assets/css/client-overrides.css',
        array('takeaway-theme-utility'),
        $version
    );
}, 30);


<?php

defined('ABSPATH') || exit;

function ttheme_enqueue(): void {
    wp_enqueue_style('takeaway-theme', TTHEME_URL . '/assets/css/theme.css', array(), TTHEME_VERSION);
    wp_enqueue_script('takeaway-theme', TTHEME_URL . '/assets/js/theme.js', array(), TTHEME_VERSION, true);
}

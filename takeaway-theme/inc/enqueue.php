<?php

defined('ABSPATH') || exit;

function ttheme_enqueue(): void {
    // Cascade: tokens (fallback values) -> base (primitives) -> theme (templates).
    // Takeaway OS prints live brand tokens inline in wp_head after these, so
    // saved branding always wins over the stylesheet fallbacks.
    wp_enqueue_style('takeaway-theme-tokens', TTHEME_URL . '/assets/css/tokens.css', array(), TTHEME_VERSION);
    wp_enqueue_style('takeaway-theme-base', TTHEME_URL . '/assets/css/base.css', array('takeaway-theme-tokens'), TTHEME_VERSION);
    wp_enqueue_style('takeaway-theme', TTHEME_URL . '/assets/css/theme.css', array('takeaway-theme-base'), TTHEME_VERSION);
    wp_enqueue_script('takeaway-theme', TTHEME_URL . '/assets/js/theme.js', array(), TTHEME_VERSION, true);
}

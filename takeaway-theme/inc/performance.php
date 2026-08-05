<?php
/**
 * Front-end resource hints and homepage hero preloads.
 */

defined('ABSPATH') || exit;

add_filter('wp_resource_hints', 'ttheme_resource_hints', 10, 2);
add_action('wp_head', 'ttheme_preload_home_hero_image', 1);

function ttheme_resource_hints(array $urls, string $relation_type): array {
    if ($relation_type === 'preconnect') {
        if (ttheme_accessibility_loads_fontawesome()) {
            $urls[] = array('href' => 'https://cdnjs.cloudflare.com', 'crossorigin' => 'anonymous');
        }

        if (ttheme_accessibility_enabled()) {
            $urls[] = array('href' => 'https://cdn.jsdelivr.net', 'crossorigin' => 'anonymous');
        }

        if (ttheme_home_uses_google_maps()) {
            $urls[] = 'https://maps.google.com';
            $urls[] = 'https://www.google.com';
        }
    }

    if ($relation_type === 'dns-prefetch' && ttheme_home_uses_google_maps()) {
        $urls[] = '//maps.google.com';
        $urls[] = '//www.google.com';
    }

    return $urls;
}

function ttheme_accessibility_enabled(): bool {
    $settings = get_option('amh_a11y_settings', array(
        'enabled' => true,
        'load_fontawesome' => true,
    ));

    return !isset($settings['enabled']) || !empty($settings['enabled']);
}

function ttheme_accessibility_loads_fontawesome(): bool {
    $settings = get_option('amh_a11y_settings', array(
        'enabled' => true,
        'load_fontawesome' => true,
    ));

    return (!isset($settings['enabled']) || !empty($settings['enabled']))
        && !empty($settings['load_fontawesome']);
}

function ttheme_home_uses_google_maps(): bool {
    if (!is_front_page()) {
        return false;
    }

    $provider = function_exists('tt_content')
        ? (string) tt_content('contact_map', 'map_provider', 'osm')
        : 'osm';

    $google_maps_url = function_exists('tt_content')
        ? (string) tt_content('contact_map', 'google_maps_url', '')
        : '';

    if ($provider === 'google' && $google_maps_url !== '') {
        return true;
    }

    if (function_exists('tt_address_lines')) {
        return !empty(tt_address_lines());
    }

    return false;
}

function ttheme_home_hero_image_id(): int {
    $image_id = absint(tt_content('homepage', 'hero_image_id', 0));
    if (!$image_id) {
        $image_id = absint(ttheme_brand('hero_image_id', 0));
    }
    return $image_id;
}

function ttheme_preload_home_hero_image(): void {
    if (!is_front_page()) {
        return;
    }

    $image_id = ttheme_home_hero_image_id();
    if (!$image_id) {
        return;
    }

    $src = wp_get_attachment_image_url($image_id, 'large');
    if (!$src) {
        return;
    }

    $srcset = wp_get_attachment_image_srcset($image_id, 'large');
    $sizes = '(min-width: 901px) 48vw, 92vw';

    echo '<link rel="preload" as="image" href="' . esc_url($src) . '"';
    if ($srcset) {
        echo ' imagesrcset="' . esc_attr($srcset) . '" imagesizes="' . esc_attr($sizes) . '"';
    }
    echo ' fetchpriority="high">' . "\n";
}

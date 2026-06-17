<?php
/**
 * Front-end resource hints and homepage hero preloads.
 */

defined('ABSPATH') || exit;

add_filter('wp_resource_hints', 'ttheme_resource_hints', 10, 2);
add_action('wp_head', 'ttheme_preload_home_hero_image', 1);

function ttheme_resource_hints(array $urls, string $relation_type): array {
    if ($relation_type === 'preconnect') {
        $urls[] = array('href' => 'https://cdnjs.cloudflare.com', 'crossorigin' => 'anonymous');
        $urls[] = array('href' => 'https://cdn.jsdelivr.net', 'crossorigin' => 'anonymous');
        if (is_front_page() || is_page()) {
            $urls[] = 'https://maps.google.com';
            $urls[] = 'https://www.google.com';
        }
    }

    if ($relation_type === 'dns-prefetch' && (is_front_page() || is_page())) {
        $urls[] = '//maps.google.com';
        $urls[] = '//www.google.com';
    }

    return $urls;
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

<?php
/**
 * Template Name: Takeaway Home V2
 *
 * Bespoke homepage concept that keeps live Takeaway Theme helpers and
 * WooCommerce-powered content while using a calmer, framed homepage layout.
 */

defined('ABSPATH') || exit;

get_header();

$business_name = tt_business_name();
$menu_url = tt_menu_url();
$status = tt_open_status();
$phone = tt_phone();
$email = tt_email();
$phone_href = $phone !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $phone) : '';
$address_lines = tt_address_lines();
$address_text = implode(', ', $address_lines);
$socials = tt_social_links();
$instagram_url = $socials['Instagram'] ?? '';
$instagram_shortcode = apply_filters('tt_home_v2_instagram_shortcode', '');

$account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : '';
$cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : '';

$logo_id = absint(ttheme_brand('logo_id', 0));
$footer_logo = absint(tt_content('footer', 'logo_id', 0));
if (!$footer_logo) {
    $footer_logo = $logo_id;
}
$footer_text = (string) tt_content('footer', 'text', '');

$delivery_enabled = (string) tt_content('delivery_collection', 'delivery_enabled', '1') === '1';
$collection_enabled = (string) tt_content('delivery_collection', 'collection_enabled', '1') === '1';
$delivery_estimate = (string) tt_content('delivery_collection', 'delivery_estimate_text', '');
$collection_estimate = (string) tt_content('delivery_collection', 'collection_estimate_text', '');

if ($delivery_estimate === '' && $delivery_enabled) {
    $mins = tt_trading('delivery_time');
    $delivery_estimate = $mins !== '' ? sprintf(__('Delivery %s mins', 'takeaway-theme'), $mins) : __('Delivery available', 'takeaway-theme');
}
if ($collection_estimate === '' && $collection_enabled) {
    $mins = tt_trading('prep_time');
    $collection_estimate = $mins !== '' ? sprintf(__('Collection %s mins', 'takeaway-theme'), $mins) : __('Collection available', 'takeaway-theme');
}

$hero_title = (string) tt_content('homepage', 'hero_title', '');
if ($hero_title === '') {
    $hero_title = __('Authentic. Always fresh.', 'takeaway-theme');
}
$hero_subtitle = (string) tt_content('homepage', 'hero_subtitle', '');
if ($hero_subtitle === '') {
    $hero_subtitle = __('Bold flavours, quality ingredients and proper takeaway.', 'takeaway-theme');
}
$hero_eyebrow = (string) tt_content('homepage', 'hero_eyebrow', __('Fresh, local, direct', 'takeaway-theme'));

$hero_image_id = absint(tt_content('homepage', 'hero_bg_image_id', 0));
if (!$hero_image_id) {
    $hero_image_id = absint(tt_content('homepage', 'hero_image_id', 0));
}
if (!$hero_image_id) {
    $hero_image_id = absint(ttheme_brand('hero_image_id', 0));
}

$tabs = array();
if ($delivery_enabled) {
    $tabs[] = array(
        'method' => 'delivery',
        'label' => __('Delivery', 'takeaway-theme'),
        'title' => __('Delivered to your door', 'takeaway-theme'),
        'text' => $delivery_estimate,
        'cta_href' => ttheme_page_url('delivery', '/delivery-checker/'),
        'cta_text' => __('Check delivery area', 'takeaway-theme'),
    );
}
if ($collection_enabled) {
    $tabs[] = array(
        'method' => 'collection',
        'label' => __('Collection', 'takeaway-theme'),
        'title' => __('Collect from us', 'takeaway-theme'),
        'text' => $address_text !== '' ? $address_text : $collection_estimate,
        'cta_href' => $menu_url,
        'cta_text' => __('Start collection order', 'takeaway-theme'),
    );
}
$first_tab = $tabs[0] ?? null;

$product_ids = array_map('absint', (array) tt_content('homepage', 'featured_product_ids', array()));
$category_ids = array_map('absint', (array) tt_content('homepage', 'featured_category_ids', array()));
$featured_products = array();

if (post_type_exists('product')) {
    if ($product_ids) {
        $product_query = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'post__in' => $product_ids,
            'orderby' => 'post__in',
            'numberposts' => 4,
        ));
    } elseif ($category_ids) {
        $product_query = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'numberposts' => 4,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category_ids,
                ),
            ),
        ));
    } else {
        $product_query = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'numberposts' => 4,
            'orderby' => 'date',
            'order' => 'DESC',
            'suppress_filters' => false,
        ));
    }

    foreach ($product_query as $index => $product_post) {
        $price = get_post_meta($product_post->ID, '_price', true);
        $thumb_id = (int) get_post_thumbnail_id($product_post->ID);
        $featured_products[] = array(
            'title' => $product_post->post_title,
            'excerpt' => wp_trim_words($product_post->post_excerpt ?: $product_post->post_content, 12),
            'price' => ($price !== '' && function_exists('wc_price')) ? wc_price((float) $price) : '',
            'permalink' => get_permalink($product_post),
            'image_id' => $thumb_id,
            'image' => tt_image($thumb_id, 'medium_large', 'tt-v2-pick__img', $product_post->post_title),
            'badge' => $index < 3 ? __('Popular', 'takeaway-theme') : '',
        );
    }
}

$hero_visual_bg_url = $hero_image_id ? (string) wp_get_attachment_image_url($hero_image_id, 'full') : '';
if ($hero_visual_bg_url === '' && $featured_products) {
    foreach ($featured_products as $featured_item) {
        if (!empty($featured_item['image_id'])) {
            $hero_visual_bg_url = (string) wp_get_attachment_image_url((int) $featured_item['image_id'], 'full');
            break;
        }
    }
}
if ($hero_visual_bg_url === '' && post_type_exists('product')) {
    $hero_product_with_image = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'numberposts' => 1,
        'meta_query' => array(
            array(
                'key' => '_thumbnail_id',
                'compare' => 'EXISTS',
            ),
        ),
        'orderby' => 'date',
        'order' => 'DESC',
        'suppress_filters' => false,
    ));
    if ($hero_product_with_image) {
        $hero_thumb_id = (int) get_post_thumbnail_id($hero_product_with_image[0]->ID);
        if ($hero_thumb_id) {
            $hero_visual_bg_url = (string) wp_get_attachment_image_url($hero_thumb_id, 'full');
        }
    }
}

$category_terms = array();
if (taxonomy_exists('product_cat')) {
    $category_terms = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => true,
        'number' => 9,
        'orderby' => 'count',
        'order' => 'DESC',
        'exclude' => array((int) get_option('default_product_cat')),
    ));
    if (is_wp_error($category_terms)) {
        $category_terms = array();
    }
}

$google_rating = (string) tt_content('business_info', 'google_rating', '');
$hygiene = (string) tt_content('business_info', 'hygiene_rating', '');
$mini_proof = array();
if ($delivery_estimate !== '') {
    $mini_proof[] = array(
        'title' => __('Delivery time', 'takeaway-theme'),
        'text' => $delivery_estimate,
        'icon' => 'clock',
    );
}
if ($collection_estimate !== '') {
    $mini_proof[] = array(
        'title' => __('Collection', 'takeaway-theme'),
        'text' => $collection_estimate,
        'icon' => 'bag',
    );
}
if ($google_rating !== '') {
    $mini_proof[] = array(
        'title' => __('Rated', 'takeaway-theme'),
        'text' => sprintf(__('%s on Google', 'takeaway-theme'), $google_rating . '★'),
        'icon' => 'star',
    );
}
$mini_proof[] = array(
    'title' => __('Secure payments', 'takeaway-theme'),
    'text' => __('Safe & encrypted', 'takeaway-theme'),
    'icon' => 'shield',
);
$mini_proof[] = array(
    'title' => __('Food hygiene', 'takeaway-theme'),
    'text' => $hygiene !== '' ? sprintf(__('%s/5 rating', 'takeaway-theme'), $hygiene) : __('Trusted local kitchen', 'takeaway-theme'),
    'icon' => 'heart',
);
$mini_proof = array_slice($mini_proof, 0, 3);

$proof_strip = array();
$proof_strip[] = array(
    'label' => __('Fresh ingredients', 'takeaway-theme'),
    'text' => __('Cooked daily', 'takeaway-theme'),
    'icon' => 'badge',
);
if ($google_rating !== '') {
    $proof_strip[] = array(
        'label' => __('Trusted by locals', 'takeaway-theme'),
        'text' => sprintf(__('%s on Google', 'takeaway-theme'), $google_rating . '★'),
        'icon' => 'leaf',
    );
}
$proof_strip[] = array(
    'label' => __('Secure payments', 'takeaway-theme'),
    'text' => __('Safe & encrypted', 'takeaway-theme'),
    'icon' => 'lock',
);
if ($hygiene !== '') {
    $proof_strip[] = array(
        'label' => __('Food hygiene', 'takeaway-theme'),
        'text' => sprintf(__('%s/5 rating', 'takeaway-theme'), $hygiene),
        'icon' => 'heart',
    );
} else {
    $proof_strip[] = array(
        'label' => __('Loved by you', 'takeaway-theme'),
        'text' => __('Top rated takeaway', 'takeaway-theme'),
        'icon' => 'heart',
    );
}
$proof_strip = array_slice($proof_strip, 0, 4);

$hours = tt_hours_summary();
$closure = function_exists('ttos_get_opening_hours') ? ttos_get_opening_hours() : array();
$closure_on = ($closure['temporary_closure'] ?? '0') === '1';
$closure_message = (string) ($closure['temporary_closure_message'] ?? '');

$provider = (string) tt_content('contact_map', 'map_provider', 'osm');
$lat = (string) tt_content('contact_map', 'lat', '');
$lng = (string) tt_content('contact_map', 'lng', '');
$google_maps_url = (string) tt_content('contact_map', 'google_maps_url', '');
$parking = (string) tt_content('contact_map', 'parking_note', '');
$access = (string) tt_content('contact_map', 'accessibility_note', '');

$has_osm = ($provider === 'osm' && $lat !== '' && $lng !== '');
$has_google = ($provider === 'google' && $google_maps_url !== '');
$fallback_map = '';
if (!$has_osm && !$has_google && $address_lines) {
    $fallback_map = 'https://maps.google.com/maps?q=' . rawurlencode($address_text) . '&z=15&output=embed';
}
$has_map = $has_osm || $has_google || $fallback_map !== '';
$map_src = '';
if ($has_osm) {
    $lat_f = (float) $lat;
    $lng_f = (float) $lng;
    $bbox = sprintf('%F,%F,%F,%F', $lng_f - 0.004, $lat_f - 0.002, $lng_f + 0.004, $lat_f + 0.002);
    $map_src = 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode($bbox) . '&layer=mapnik&marker=' . rawurlencode($lat_f . ',' . $lng_f);
} elseif ($fallback_map !== '') {
    $map_src = $fallback_map;
}

$review_items = array_values(array_filter((array) tt_content('reviews', 'items', array()), static function ($item) {
    return is_array($item) && (($item['text'] ?? '') !== '' || ($item['name'] ?? '') !== '');
}));
usort($review_items, static function ($a, $b) {
    return (int) ($b['featured'] ?? 0) <=> (int) ($a['featured'] ?? 0);
});

$quick_links = array_filter(array(
    __('Menu', 'takeaway-theme') => $menu_url,
    __('Delivery checker', 'takeaway-theme') => ttheme_page_url('delivery', '/delivery-checker/'),
    __('Track order', 'takeaway-theme') => ttheme_page_url('tracker', '/order-tracker/'),
    __('My account', 'takeaway-theme') => $account_url,
));

$legal_links = array();
$policy_specs = array(
    'policy_privacy' => __('Privacy policy', 'takeaway-theme'),
    'policy_terms' => __('Terms & conditions', 'takeaway-theme'),
    'policy_refunds' => __('Refunds', 'takeaway-theme'),
    'policy_delivery' => __('Delivery policy', 'takeaway-theme'),
);
foreach ($policy_specs as $key => $label) {
    $id = absint(get_option('ttos_page_' . $key, 0));
    if ($id && get_post_status($id) === 'publish') {
        $legal_links[$label] = get_permalink($id);
    }
}
if (!isset($legal_links[__('Privacy policy', 'takeaway-theme')]) && function_exists('get_privacy_policy_url') && get_privacy_policy_url()) {
    $legal_links[__('Privacy policy', 'takeaway-theme')] = get_privacy_policy_url();
}

$footer_categories = array();
foreach (array_slice($category_terms, 0, 4) as $term) {
    $link = get_term_link($term);
    if (!is_wp_error($link)) {
        $footer_categories[$term->name] = $link;
    }
}

$social_icon_keys = array(
    'Instagram' => 'instagram',
    'Facebook' => 'facebook',
    'TikTok' => 'tiktok',
    'WhatsApp' => 'whatsapp',
    'Google' => 'google',
    'TripAdvisor' => 'tripadvisor',
    'X' => 'twitter',
    'YouTube' => 'youtube',
);

$render_v2_icon = static function (string $icon): string {
    $icons = array(
        'clock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>',
        'star' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5l2.8 5.67 6.26.91-4.53 4.42 1.07 6.24L12 17.82 6.4 20.74l1.07-6.24-4.53-4.42 6.26-.91L12 3.5z"></path></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-2.9 8.39-7 9.7C7.9 19.39 5 15.5 5 11V6l7-3z"></path></svg>',
        'bag' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8h12l-1 12H7L6 8z"></path><path d="M9 9V7a3 3 0 0 1 6 0v2"></path></svg>',
        'badge' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M8 13l-1 8 5-3 5 3-1-8"></path></svg>',
        'leaf' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 4c-8 .5-13 4.5-15 12 4-.5 7-2 9-4-1.5 3-4 5.5-8 7 7 1 14-5.5 14-15z"></path></svg>',
        'lock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"></rect><path d="M8 11V8a4 4 0 0 1 8 0v3"></path></svg>',
        'heart' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.5-9-9a5.4 5.4 0 0 1 9-5 5.4 5.4 0 0 1 9 5c-2 4.5-9 9-9 9z"></path></svg>',
        'pin' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-6-5.33-6-11a6 6 0 0 1 12 0c0 5.67-6 11-6 11z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>',
        'phone' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.79 19.79 0 0 1 11.2 18.9a19.5 19.5 0 0 1-8.1-8.1A19.79 19.79 0 0 1 .08 2.18 2 2 0 0 1 2.06 0h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L6.1 7.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>',
        'mail' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M4 7l8 6 8-6"></path></svg>',
    );

    return $icons[$icon] ?? $icons['clock'];
};
?>

<div class="tt-home-v2">
    <div class="tt-v2-shell">
        <section class="tt-v2-topbar">
            <div class="tt-wrap tt-v2-topbar__inner">
                <div class="tt-v2-topbar__group">
                    <?php echo tt_open_status_pill(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </div>
                <div class="tt-v2-topbar__group tt-v2-topbar__group--center">
                    <?php if ($delivery_estimate !== '') : ?><span><?php echo esc_html($delivery_estimate); ?></span><?php endif; ?>
                    <?php if ($collection_estimate !== '') : ?><span><?php echo esc_html($collection_estimate); ?></span><?php endif; ?>
                </div>
                <div class="tt-v2-topbar__group tt-v2-topbar__group--right">
                    <?php if ($phone !== '') : ?>
                        <a href="<?php echo esc_url($phone_href); ?>"><?php echo esc_html($phone); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="tt-v2-header">
            <div class="tt-wrap tt-v2-header__inner">
                <a class="tt-v2-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr($business_name); ?>">
                    <?php if ($logo_id) : ?>
                        <span class="tt-v2-brand__mark"><?php echo tt_image($logo_id, 'medium', 'tt-v2-brand__logo', $business_name); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                    <?php else : ?>
                        <span class="tt-v2-brand__dot" aria-hidden="true"></span>
                    <?php endif; ?>
                    <span class="tt-v2-brand__text"><?php echo esc_html($business_name); ?></span>
                </a>

                <nav class="tt-v2-nav" aria-label="<?php esc_attr_e('Primary', 'takeaway-theme'); ?>">
                    <?php
                    if (has_nav_menu('primary')) {
                        wp_nav_menu(array(
                            'theme_location' => 'primary',
                            'container' => false,
                            'fallback_cb' => false,
                            'depth' => 1,
                        ));
                    } else {
                        ttheme_fallback_nav('primary');
                    }
                    ?>
                </nav>

                <div class="tt-v2-actions">
                    <?php if ($account_url) : ?>
                        <a class="tt-v2-action tt-v2-action--icon" href="<?php echo esc_url($account_url); ?>" aria-label="<?php esc_attr_e('Login', 'takeaway-theme'); ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"></path></svg>
                            <span><?php esc_html_e('Login', 'takeaway-theme'); ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($cart_url) : ?>
                        <a class="tt-v2-action" href="<?php echo esc_url($cart_url); ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                            <span><?php esc_html_e('Basket', 'takeaway-theme'); ?></span>
                            <?php tt_cart_count_badge(); ?>
                        </a>
                    <?php endif; ?>
                    <a class="tt-btn tt-v2-order-btn" href="<?php echo esc_url($menu_url); ?>"><?php esc_html_e('Order now', 'takeaway-theme'); ?></a>
                </div>
            </div>
        </section>

        <main class="tt-v2-main">
            <section class="tt-v2-hero<?php echo $hero_visual_bg_url !== '' ? ' has-hero-bg' : ' no-hero-bg'; ?>"<?php echo $hero_visual_bg_url !== '' ? ' style="--tt-hero-food-bg:url(' . esc_url($hero_visual_bg_url) . ')"' : ''; ?>>
                <div class="tt-wrap tt-v2-hero__grid">
                    <div class="tt-v2-hero__content">
                        <p class="tt-v2-eyebrow"><?php echo esc_html($hero_eyebrow); ?></p>
                        <h1><?php echo esc_html($hero_title); ?></h1>
                        <p class="tt-v2-hero__lede"><?php echo esc_html($hero_subtitle); ?></p>

                        <?php if ($first_tab) : ?>
                            <div class="tt-start-card tt-v2-start-card">
                                <div class="tt-method-switch" role="tablist" aria-label="<?php esc_attr_e('Order method', 'takeaway-theme'); ?>">
                                    <?php foreach ($tabs as $index => $tab) : ?>
                                        <button class="tt-method<?php echo $index === 0 ? ' active' : ''; ?>"
                                            type="button"
                                            role="tab"
                                            aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                                            data-method="<?php echo esc_attr($tab['method']); ?>"
                                            data-title="<?php echo esc_attr($tab['title']); ?>"
                                            data-text="<?php echo esc_attr($tab['text']); ?>"
                                            data-cta-href="<?php echo esc_attr($tab['cta_href']); ?>"
                                            data-cta-text="<?php echo esc_attr($tab['cta_text']); ?>">
                                            <?php echo esc_html($tab['label']); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <div class="tt-start-panel" role="tabpanel">
                                    <p class="tt-method-title"><?php echo esc_html($first_tab['title']); ?></p>
                                    <p class="tt-method-text"><?php echo esc_html($first_tab['text']); ?></p>
                                    <div class="tt-panel-actions">
                                        <a class="tt-btn tt-method-cta" href="<?php echo esc_url($first_tab['cta_href']); ?>"><?php echo esc_html($first_tab['cta_text']); ?></a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($mini_proof) : ?>
                            <div class="tt-v2-mini-proof" aria-label="<?php esc_attr_e('Highlights', 'takeaway-theme'); ?>">
                                <?php foreach ($mini_proof as $item) : ?>
                                    <div class="tt-v2-mini-proof__item">
                                        <span class="tt-v2-icon" aria-hidden="true"><?php echo $render_v2_icon($item['icon']); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                                        <div>
                                            <strong><?php echo esc_html($item['text']); ?></strong>
                                            <small><?php echo esc_html($item['title']); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="tt-v2-hero__visual">
                        <?php if ($hero_visual_bg_url === '') : ?>
                            <div class="tt-v2-hero__placeholder" aria-hidden="true">
                                <span><?php echo tt_food_placeholder_svg('cloche'); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <?php if ($category_terms) : ?>
                <section class="tt-v2-section tt-v2-mood">
                    <div class="tt-wrap">
                        <div class="tt-v2-section__head">
                            <h2><?php esc_html_e('What are you in the mood for?', 'takeaway-theme'); ?></h2>
                        </div>
                        <div class="tt-v2-mood__rail">
                            <?php foreach ($category_terms as $term) : ?>
                                <?php $term_link = get_term_link($term); ?>
                                <?php if (is_wp_error($term_link)) continue; ?>
                                <a class="tt-v2-mood__item" href="<?php echo esc_url($term_link); ?>">
                                    <span class="tt-v2-mood__icon" aria-hidden="true"><?php echo tt_food_placeholder_svg(tt_food_placeholder_category($term->name)); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                                    <span><?php echo esc_html($term->name); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($featured_products) : ?>
                <section class="tt-v2-section tt-v2-picks">
                    <div class="tt-wrap">
                        <div class="tt-v2-section__head">
                            <h2><?php esc_html_e("Chef's picks", 'takeaway-theme'); ?></h2>
                        </div>
                        <div class="tt-v2-picks__grid">
                            <?php foreach ($featured_products as $item) : ?>
                                <article class="tt-v2-pick">
                                    <a class="tt-v2-pick__media" href="<?php echo esc_url($item['permalink']); ?>">
                                        <?php if ($item['badge'] !== '') : ?><span class="tt-v2-pick__badge"><?php echo esc_html($item['badge']); ?></span><?php endif; ?>
                                        <?php if ($item['image'] !== '') : ?>
                                            <?php echo $item['image']; // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                        <?php else : ?>
                                            <span class="tt-v2-pick__fallback" aria-hidden="true"><?php echo tt_food_placeholder_svg(tt_food_placeholder_category($item['title'])); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <div class="tt-v2-pick__body">
                                        <h3><a href="<?php echo esc_url($item['permalink']); ?>"><?php echo esc_html($item['title']); ?></a></h3>
                                        <?php if ($item['excerpt'] !== '') : ?><p><?php echo esc_html($item['excerpt']); ?></p><?php endif; ?>
                                        <?php if ($item['price'] !== '') : ?><strong class="tt-v2-pick__price"><?php echo wp_kses_post($item['price']); ?></strong><?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($proof_strip) : ?>
                <section class="tt-v2-section tt-v2-proof">
                    <div class="tt-wrap">
                        <div class="tt-v2-proof__row">
                            <?php foreach ($proof_strip as $item) : ?>
                                <div class="tt-v2-proof__item">
                                    <span class="tt-v2-icon" aria-hidden="true"><?php echo $render_v2_icon($item['icon']); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                                    <div>
                                        <strong><?php echo esc_html($item['label']); ?></strong>
                                        <small><?php echo esc_html($item['text']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="tt-v2-section tt-v2-info">
                <div class="tt-wrap">
                    <div class="tt-v2-info__grid">
                        <div class="tt-v2-info__hours">
                            <h2><?php esc_html_e('Opening times', 'takeaway-theme'); ?></h2>
                            <?php if ($hours) : ?>
                                <ul class="tt-v2-hours">
                                    <?php foreach ($hours as $row) : ?>
                                        <li><span><?php echo esc_html($row['label']); ?></span><strong><?php echo esc_html($row['value']); ?></strong></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if ($closure_on && $closure_message !== '') : ?>
                                <p class="tt-v2-info__note"><?php echo esc_html($closure_message); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="tt-v2-info__find">
                            <h2><?php esc_html_e('Find us', 'takeaway-theme'); ?></h2>
                            <?php if ($has_map && $map_src !== '') : ?>
                                <div class="tt-v2-map">
                                    <iframe title="<?php echo esc_attr(sprintf(__('Map showing %s', 'takeaway-theme'), $business_name)); ?>" src="<?php echo esc_url($map_src); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                                </div>
                            <?php elseif ($has_google) : ?>
                                <div class="tt-v2-map tt-v2-map--link">
                                    <a class="tt-btn" href="<?php echo esc_url($google_maps_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open map & directions', 'takeaway-theme'); ?></a>
                                </div>
                            <?php endif; ?>

                            <div class="tt-v2-contact-actions">
                                <?php if ($phone !== '') : ?>
                                    <a class="tt-v2-contact-pill" href="<?php echo esc_url($phone_href); ?>">
                                        <span class="tt-v2-icon" aria-hidden="true"><?php echo $render_v2_icon('phone'); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                                        <span><?php echo esc_html($phone); ?></span>
                                    </a>
                                <?php endif; ?>
                                <?php if ($email !== '') : ?>
                                    <a class="tt-v2-contact-pill" href="mailto:<?php echo esc_attr($email); ?>">
                                        <span class="tt-v2-icon" aria-hidden="true"><?php echo $render_v2_icon('mail'); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                                        <span><?php echo esc_html($email); ?></span>
                                    </a>
                                <?php endif; ?>
                                <?php if ($google_maps_url !== '') : ?>
                                    <a class="tt-v2-contact-pill" href="<?php echo esc_url($google_maps_url); ?>" target="_blank" rel="noopener noreferrer">
                                        <span class="tt-v2-icon" aria-hidden="true"><?php echo $render_v2_icon('pin'); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                                        <span><?php echo esc_html($address_text !== '' ? $address_text : __('Directions', 'takeaway-theme')); ?></span>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <?php if ($parking !== '' || $access !== '') : ?>
                                <div class="tt-v2-info__meta">
                                    <?php if ($parking !== '') : ?><p><strong><?php esc_html_e('Parking:', 'takeaway-theme'); ?></strong> <?php echo esc_html($parking); ?></p><?php endif; ?>
                                    <?php if ($access !== '') : ?><p><strong><?php esc_html_e('Accessibility:', 'takeaway-theme'); ?></strong> <?php echo esc_html($access); ?></p><?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($socials) : ?>
                                <div class="tt-v2-socials" aria-label="<?php esc_attr_e('Social links', 'takeaway-theme'); ?>">
                                    <?php foreach ($socials as $label => $url) : ?>
                                        <?php $social_key = $social_icon_keys[$label] ?? sanitize_key($label); ?>
                                        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($label); ?>">
                                            <?php echo tt_social_svg($social_key); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <?php if ($instagram_shortcode !== '' || $review_items) : ?>
                <section class="tt-v2-section tt-v2-social-row">
                    <div class="tt-wrap">
                        <div class="tt-v2-section__head">
                            <h2><?php echo $instagram_shortcode !== '' ? esc_html__('Follow us on Instagram', 'takeaway-theme') : esc_html((string) tt_content('reviews', 'title', __('Customer feedback', 'takeaway-theme'))); ?></h2>
                        </div>

                        <?php if ($instagram_shortcode !== '') : ?>
                            <div class="tt-v2-social-row__instagram">
                                <?php echo do_shortcode($instagram_shortcode); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            </div>
                        <?php elseif ($review_items) : ?>
                            <div class="tt-v2-review-row">
                                <?php foreach (array_slice($review_items, 0, 5) as $review) : ?>
                                    <blockquote class="tt-v2-review">
                                        <?php echo tt_stars((string) ($review['rating'] ?? '')); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                        <?php if (!empty($review['text'])) : ?><p><?php echo esc_html($review['text']); ?></p><?php endif; ?>
                                        <?php if (!empty($review['name'])) : ?><cite><?php echo esc_html((string) $review['name']); ?></cite><?php endif; ?>
                                    </blockquote>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="tt-v2-footer">
                <div class="tt-wrap">
                    <div class="tt-v2-footer__grid">
                        <div class="tt-v2-footer__brand">
                            <?php if ($footer_logo) : ?>
                                <p class="tt-v2-footer__logo"><?php echo tt_image($footer_logo, 'medium', 'tt-v2-footer__logo-img', $business_name); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
                            <?php endif; ?>
                            <p class="tt-v2-footer__name"><?php echo esc_html($business_name); ?></p>
                            <p class="tt-v2-footer__text"><?php echo esc_html($footer_text !== '' ? wp_strip_all_tags($footer_text) : __('Authentic flavours. Made for you.', 'takeaway-theme')); ?></p>
                            <?php if ($socials) : ?>
                                <div class="tt-v2-socials" aria-label="<?php esc_attr_e('Footer social links', 'takeaway-theme'); ?>">
                                    <?php foreach ($socials as $label => $url) : ?>
                                        <?php $social_key = $social_icon_keys[$label] ?? sanitize_key($label); ?>
                                        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($label); ?>">
                                            <?php echo tt_social_svg($social_key); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($footer_categories) : ?>
                            <div class="tt-v2-footer__col">
                                <h3><?php esc_html_e('Menu', 'takeaway-theme'); ?></h3>
                                <ul>
                                    <?php foreach ($footer_categories as $label => $url) : ?>
                                        <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($quick_links) : ?>
                            <div class="tt-v2-footer__col">
                                <h3><?php esc_html_e('Order', 'takeaway-theme'); ?></h3>
                                <ul>
                                    <?php foreach ($quick_links as $label => $url) : ?>
                                        <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="tt-v2-footer__col">
                            <h3><?php esc_html_e('Contact', 'takeaway-theme'); ?></h3>
                            <ul>
                                <?php if ($address_text !== '') : ?><li><?php echo esc_html($address_text); ?></li><?php endif; ?>
                                <?php if ($phone !== '') : ?><li><a href="<?php echo esc_url($phone_href); ?>"><?php echo esc_html($phone); ?></a></li><?php endif; ?>
                                <?php if ($email !== '') : ?><li><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></li><?php endif; ?>
                            </ul>
                        </div>

                        <?php if ($legal_links) : ?>
                            <div class="tt-v2-footer__col">
                                <h3><?php esc_html_e('Legal', 'takeaway-theme'); ?></h3>
                                <ul>
                                    <?php foreach ($legal_links as $label => $url) : ?>
                                        <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="tt-v2-footer__bottom">
                        <span><?php echo esc_html(gmdate('Y')); ?> <?php echo esc_html($business_name); ?></span>
                        <div class="tt-v2-footer__bottom-links">
                            <?php foreach (array_slice($legal_links, 0, 3, true) as $label => $url) : ?>
                                <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<?php
get_footer();

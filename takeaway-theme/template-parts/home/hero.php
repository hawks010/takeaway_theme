<?php
/**
 * Homepage hero v0.3.4.
 *
 * Layout modes:
 * - editorial_split
 * - cinematic_photo
 * - product_mosaic
 */

defined('ABSPATH') || exit;

$hero_style = tt_brand_layout('hero_style', 'editorial_split');
$primary_cta_text = (string) tt_content('homepage', 'primary_cta_text', '');
$primary_cta_target = (string) tt_content('homepage', 'primary_cta_target', '');
$secondary_cta_text = (string) tt_content('homepage', 'secondary_cta_text', '');
$secondary_cta_target = (string) tt_content('homepage', 'secondary_cta_target', '');
$show_open_status = (string) tt_content('homepage', 'show_open_status', '1') === '1';
$show_trust_strip = (string) tt_content('homepage', 'show_trust_strip', '1') === '1';

$eyebrow = (string) tt_content('homepage', 'hero_eyebrow', '');
if ($eyebrow === '') {
    $cuisine = (string) ttheme_business('cuisine', '');
    $eyebrow = $cuisine !== '' ? $cuisine . ' · ' . __('Order direct', 'takeaway-theme') : __('Order direct', 'takeaway-theme');
}

$title = (string) tt_content('homepage', 'hero_title', tt_business_name());
$subtitle = (string) tt_content('homepage', 'hero_subtitle', __('Fresh food for collection and delivery, straight from our kitchen — no marketplace middleman.', 'takeaway-theme'));

$image_id = absint(tt_content('homepage', 'hero_image_id', 0));
if (!$image_id) {
    $image_id = absint(ttheme_brand('hero_image_id', 0));
}

$bg_id = absint(tt_content('homepage', 'hero_bg_image_id', 0));
$bg_url = $bg_id ? wp_get_attachment_image_url($bg_id, 'full') : '';

$delivery_on = (string) tt_content('delivery_collection', 'delivery_enabled', '1') === '1';
$collection_on = (string) tt_content('delivery_collection', 'collection_enabled', '1') === '1';

$ordering_status = tt_open_status();
$ordering_state = $ordering_status['state'];
$ordering_label = $ordering_status['label'];

$biz_info = class_exists('TTOS_Site_Content') ? TTOS_Site_Content::get('business_info') : array();
$biz_address = implode(', ', array_filter(array(
    (string) ($biz_info['address_1'] ?? ''),
    (string) ($biz_info['address_2'] ?? ''),
    (string) ($biz_info['town'] ?? ''),
    (string) ($biz_info['postcode'] ?? ''),
)));

$booking_tab_on = (string) tt_content('delivery_collection', 'table_booking_enabled', '0') === '1';
$booking_conf = class_exists('TTOS_Site_Content') ? TTOS_Site_Content::get('contact_map') : array();
$booking_enabled = ($booking_conf['booking_enabled'] ?? '0') === '1';
$show_booking_tab = $booking_tab_on && $booking_enabled;

$tabs = array();
if ($delivery_on) {
    $hints = array_filter(array(
        (string) tt_content('delivery_collection', 'delivery_estimate_text', ''),
        (string) tt_content('delivery_collection', 'min_order_text', ''),
        (string) tt_content('delivery_collection', 'free_delivery_text', ''),
    ));
    $tabs[] = array(
        'method'   => 'delivery',
        'label'    => __('Delivery', 'takeaway-theme'),
        'title'    => __('Delivered to your door', 'takeaway-theme'),
        'text'     => !empty($hints) ? implode(' · ', $hints) : __('Enter your postcode to check we cover your area, then browse the menu.', 'takeaway-theme'),
        'cta_href' => ttheme_page_url('delivery', '/delivery-checker/'),
        'cta_text' => __('Check my area', 'takeaway-theme'),
    );
}
if ($collection_on) {
    $collect_hints = array_filter(array(
        $biz_address !== '' ? $biz_address : __('Visit us in store to collect.', 'takeaway-theme'),
        (string) tt_content('delivery_collection', 'collection_estimate_text', ''),
    ));
    $tabs[] = array(
        'method'   => 'collection',
        'label'    => __('Collection', 'takeaway-theme'),
        'title'    => __('Pick up in store', 'takeaway-theme'),
        'text'     => implode(' · ', $collect_hints),
        'cta_href' => tt_menu_url(),
        'cta_text' => __('Start collection order', 'takeaway-theme'),
    );
}
if ($show_booking_tab) {
    $book_note = (string) ($booking_conf['booking_note'] ?? '');
    $tabs[] = array(
        'method'   => 'book_table',
        'label'    => __('Book table', 'takeaway-theme'),
        'title'    => __('Reserve a table', 'takeaway-theme'),
        'text'     => $book_note !== '' ? $book_note : __("Join us for a sit-down meal. We’d love to see you.", 'takeaway-theme'),
        'cta_href' => tt_cta_url((string) ($booking_conf['booking_target'] ?? '')),
        'cta_text' => (string) ($booking_conf['booking_cta_text'] ?? '') ?: __('Book a table', 'takeaway-theme'),
    );
}

$first_tab = !empty($tabs) ? $tabs[0] : null;
$hero_products = tt_home_hero_products(4);

if (!$image_id && !empty($hero_products[0]['image_id'])) {
    $image_id = (int) $hero_products[0]['image_id'];
}

$hero_meta = array();
if ($show_open_status && $ordering_label !== '') {
    $hero_meta[] = array(
        'label' => __('Ordering status', 'takeaway-theme'),
        'value' => $ordering_label,
    );
}

$meta_candidates = array(
    (string) tt_content('delivery_collection', 'delivery_estimate_text', ''),
    (string) tt_content('delivery_collection', 'collection_estimate_text', ''),
    (string) tt_content('delivery_collection', 'min_order_text', ''),
);
foreach ($meta_candidates as $value) {
    if ($value === '' || count($hero_meta) >= 3) {
        continue;
    }
    $hero_meta[] = array(
        'label' => __('Highlight', 'takeaway-theme'),
        'value' => $value,
    );
}

$hero_label = sprintf(__('Food from %s', 'takeaway-theme'), tt_business_name());
$hero_img = $image_id ? tt_image($image_id, 'large', 'tt-home-hero-img', $hero_label) : '';
$hero_placeholder_text = trim($title . ' ' . $eyebrow . ' ' . (string) ttheme_business('cuisine', ''));
$hero_trust_items = $show_trust_strip ? tt_home_trust_items(4) : array();

$hero_classes = array('tt-home-hero', 'tt-home-hero-' . $hero_style);
if ($bg_url) {
    $hero_classes[] = 'has-bg';
}
?>
<section class="<?php echo esc_attr(implode(' ', $hero_classes)); ?>"<?php echo $bg_url ? ' style="--tt-hero-bg:url(' . esc_url($bg_url) . ')"' : ''; ?>>
    <div class="tt-wrap tt-home-hero-grid">
        <div class="tt-home-hero-copy">
            <p class="tt-eyebrow"><?php echo esc_html($eyebrow); ?></p>
            <h1><?php echo esc_html($title); ?></h1>
            <p class="tt-home-hero-sub"><?php echo esc_html($subtitle); ?></p>

            <?php if (!empty($hero_meta)) : ?>
                <ul class="tt-home-hero-meta" aria-label="<?php esc_attr_e('Hero highlights', 'takeaway-theme'); ?>">
                    <?php foreach ($hero_meta as $meta) : ?>
                        <li>
                            <span><?php echo esc_html($meta['label']); ?></span>
                            <strong><?php echo esc_html($meta['value']); ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="tt-home-hero-actions">
                <a class="tt-btn tt-home-primary-cta" href="<?php echo esc_url(tt_cta_url($primary_cta_target)); ?>"><?php echo esc_html($primary_cta_text !== '' ? $primary_cta_text : __('Order from the menu', 'takeaway-theme')); ?></a>
                <a class="tt-btn ghost tt-home-secondary-cta" href="<?php echo esc_url(tt_cta_url($secondary_cta_target !== '' ? $secondary_cta_target : ttheme_page_url('delivery', '/delivery-checker/'))); ?>"><?php echo esc_html($secondary_cta_text !== '' ? $secondary_cta_text : __('Check delivery area', 'takeaway-theme')); ?></a>
            </div>

            <?php if (!empty($hero_trust_items)) : ?>
                <div class="tt-home-hero-trust" aria-label="<?php esc_attr_e('Hero trust badges', 'takeaway-theme'); ?>">
                    <?php foreach ($hero_trust_items as $item) : ?>
                        <span class="tt-home-hero-trust-item"><?php if ($item['icon']) : ?><span class="tt-trust-icon" aria-hidden="true"><?php echo esc_html($item['icon']); ?></span><?php endif; ?><?php echo $item['html']; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped upstream. ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($tabs)) : ?>
                <div class="tt-start-card">
                    <div class="tt-method-switch" role="tablist" aria-label="<?php esc_attr_e('Order method', 'takeaway-theme'); ?>">
                        <?php foreach ($tabs as $i => $tab) : ?>
                            <button class="tt-method<?php echo $i === 0 ? ' active' : ''; ?>"
                                type="button"
                                role="tab"
                                aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
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
                        <p class="tt-method-title"><?php echo $first_tab ? esc_html($first_tab['title']) : ''; ?></p>
                        <p class="tt-method-text"><?php echo $first_tab ? esc_html($first_tab['text']) : ''; ?></p>
                        <div class="tt-panel-actions">
                            <a class="tt-btn tt-method-cta" href="<?php echo $first_tab ? esc_url($first_tab['cta_href']) : '#'; ?>">
                                <?php echo $first_tab ? esc_html($first_tab['cta_text']) : ''; ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="tt-home-hero-visual">
            <?php if ($hero_style === 'product_mosaic' && !empty($hero_products)) : ?>
                <div class="tt-home-hero-mosaic" aria-label="<?php esc_attr_e('Popular from the menu', 'takeaway-theme'); ?>">
                    <div class="tt-home-hero-mosaic-head">
                        <p class="tt-board-kicker"><?php esc_html_e('Built from the live menu', 'takeaway-theme'); ?></p>
                        <h2><?php esc_html_e('Start with tonight’s favourites', 'takeaway-theme'); ?></h2>
                    </div>
                    <div class="tt-home-hero-mosaic-grid">
                        <?php foreach ($hero_products as $item) : ?>
                            <a class="tt-hero-mosaic-card" href="<?php echo esc_url($item['permalink']); ?>">
                                <span class="tt-hero-mosaic-media"><?php echo wp_kses_post($item['image']); ?></span>
                                <span class="tt-hero-mosaic-copy">
                                    <strong><?php echo esc_html($item['title']); ?></strong>
                                    <?php if ($item['excerpt'] !== '') : ?>
                                        <small><?php echo esc_html($item['excerpt']); ?></small>
                                    <?php endif; ?>
                                </span>
                                <?php if ($item['price'] !== '') : ?>
                                    <b><?php echo wp_kses_post($item['price']); ?></b>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <a class="tt-board-link" href="<?php echo esc_url(tt_menu_url()); ?>"><?php esc_html_e('Browse the full menu', 'takeaway-theme'); ?></a>
                </div>
            <?php elseif ($hero_img !== '') : ?>
                <div class="tt-home-hero-imgwrap">
                    <?php echo $hero_img; // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <?php if ($hero_style === 'cinematic_photo') : ?>
                        <div class="tt-home-hero-overlay">
                            <?php if ($ordering_label !== '') : ?>
                                <span class="tt-ordering-badge is-<?php echo esc_attr($ordering_state !== 'unknown' ? $ordering_state : 'closed'); ?>"><?php echo esc_html($ordering_label); ?></span>
                            <?php endif; ?>
                            <strong><?php esc_html_e('Photo-led homepage layout', 'takeaway-theme'); ?></strong>
                            <p><?php echo esc_html(!empty($hero_products[0]['excerpt']) ? $hero_products[0]['excerpt'] : __('Use this layout when you want the food photography to do the selling.', 'takeaway-theme')); ?></p>
                        </div>
                    <?php elseif (!empty($hero_products)) : ?>
                        <div class="tt-home-hero-note">
                            <span><?php esc_html_e('Popular now', 'takeaway-theme'); ?></span>
                            <strong><?php echo esc_html($hero_products[0]['title']); ?></strong>
                            <?php if (!empty($hero_products[0]['price'])) : ?>
                                <b><?php echo wp_kses_post($hero_products[0]['price']); ?></b>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="tt-home-hero-placeholder">
                    <?php echo tt_food_placeholder($hero_placeholder_text, '', $hero_label); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

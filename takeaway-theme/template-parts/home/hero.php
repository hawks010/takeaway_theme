<?php
/**
 * Homepage hero v0.3.3.
 * Method switcher card (delivery / collection / book table) replaces the
 * old fulfilment-pill + postcode-zone approach.
 */

defined('ABSPATH') || exit;

$eyebrow = (string) tt_content('homepage', 'hero_eyebrow', '');
if ($eyebrow === '') {
    $cuisine = (string) ttheme_business('cuisine', '');
    $eyebrow = $cuisine !== '' ? $cuisine . ' · ' . __('Order direct', 'takeaway-theme') : __('Order direct', 'takeaway-theme');
}
$title    = (string) tt_content('homepage', 'hero_title', tt_business_name());
$subtitle = (string) tt_content('homepage', 'hero_subtitle', __('Fresh food for collection and delivery, straight from our kitchen — no marketplace middleman.', 'takeaway-theme'));

$image_id = absint(tt_content('homepage', 'hero_image_id', 0));
if (!$image_id) $image_id = absint(ttheme_brand('hero_image_id', 0));
$bg_id  = absint(tt_content('homepage', 'hero_bg_image_id', 0));
$bg_url = $bg_id ? wp_get_attachment_image_url($bg_id, 'full') : '';

$delivery_on   = (string) tt_content('delivery_collection', 'delivery_enabled', '1') === '1';
$collection_on = (string) tt_content('delivery_collection', 'collection_enabled', '1') === '1';

// Ordering state (open / closed / preorder / unknown)
$ordering_status = tt_open_status();
$ordering_state  = $ordering_status['state'];
$ordering_label  = $ordering_status['label'];

// Business address for collection tab
$biz_info    = class_exists('TTOS_Site_Content') ? TTOS_Site_Content::get('business_info') : array();
$biz_address = implode(', ', array_filter(array(
    (string) ($biz_info['address_1'] ?? ''),
    (string) ($biz_info['address_2'] ?? ''),
    (string) ($biz_info['town']      ?? ''),
    (string) ($biz_info['postcode']  ?? ''),
)));

// Table booking tab: needs both the CRM toggle AND contact_map booking_enabled
$booking_tab_on   = (string) tt_content('delivery_collection', 'table_booking_enabled', '0') === '1';
$booking_conf     = class_exists('TTOS_Site_Content') ? TTOS_Site_Content::get('contact_map') : array();
$booking_enabled  = ($booking_conf['booking_enabled'] ?? '0') === '1';
$show_booking_tab = $booking_tab_on && $booking_enabled;

// Build method tabs
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

$hero_products = array();
if (!$image_id && post_type_exists('product')) {
    $hero_query = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'numberposts' => 3,
        'orderby' => 'date',
        'order' => 'DESC',
    ));
    foreach ($hero_query as $product_post) {
        $price = get_post_meta($product_post->ID, '_price', true);
        $hero_products[] = array(
            'title' => $product_post->post_title,
            'price' => ($price !== '' && function_exists('wc_price')) ? wc_price((float) $price) : '',
            'excerpt' => wp_trim_words($product_post->post_excerpt ?: $product_post->post_content, 8),
        );
    }
}
?>
<section class="tt-home-hero<?php echo $bg_url ? ' has-bg' : ''; ?>"<?php echo $bg_url ? ' style="--tt-hero-bg:url(' . esc_url($bg_url) . ')"' : ''; ?>>
    <div class="tt-wrap tt-home-hero-grid">
        <div class="tt-home-hero-copy">
            <p class="tt-eyebrow"><?php echo esc_html($eyebrow); ?></p>
            <h1><?php echo esc_html($title); ?></h1>
            <p class="tt-home-hero-sub"><?php echo esc_html($subtitle); ?></p>
            <div class="tt-home-hero-actions">
                <a class="tt-btn tt-home-primary-cta" href="<?php echo esc_url(tt_menu_url()); ?>"><?php esc_html_e('Order from the menu', 'takeaway-theme'); ?></a>
                <a class="tt-btn ghost tt-home-secondary-cta" href="<?php echo esc_url(ttheme_page_url('delivery', '/delivery-checker/')); ?>"><?php esc_html_e('Check delivery area', 'takeaway-theme'); ?></a>
            </div>

            <?php if (!empty($tabs)) : ?>
            <div class="tt-start-card">

                <div class="tt-method-switch" role="tablist"
                     aria-label="<?php esc_attr_e('Order method', 'takeaway-theme'); ?>">
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
                        <a class="tt-btn tt-method-cta"
                           href="<?php echo $first_tab ? esc_url($first_tab['cta_href']) : '#'; ?>">
                            <?php echo $first_tab ? esc_html($first_tab['cta_text']) : ''; ?>
                        </a>
                    </div>
                </div>

            </div><!-- /.tt-start-card -->
            <?php endif; ?>
        </div><!-- /.tt-home-hero-copy -->

        <div class="tt-home-hero-visual">
            <?php
            $hero_label = sprintf(__('Food from %s', 'takeaway-theme'), tt_business_name());
            $hero_img = $image_id ? tt_image($image_id, 'large', 'tt-home-hero-img', $hero_label) : '';
            if ($hero_img !== '') {
                echo '<div class="tt-home-hero-imgwrap">' . $hero_img . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
            } elseif ($hero_products) {
                echo '<div class="tt-home-hero-board" aria-label="' . esc_attr__('Today from the menu', 'takeaway-theme') . '">';
                echo '<p class="tt-board-kicker">' . esc_html__('Cooked to order', 'takeaway-theme') . '</p>';
                echo '<h2>' . esc_html__('Start with a favourite', 'takeaway-theme') . '</h2>';
                echo '<div class="tt-board-items">';
                foreach ($hero_products as $item) {
                    echo '<a href="' . esc_url(tt_menu_url()) . '" class="tt-board-item">';
                    echo '<span><strong>' . esc_html($item['title']) . '</strong>';
                    if ($item['excerpt'] !== '') echo '<small>' . esc_html($item['excerpt']) . '</small>';
                    echo '</span>';
                    if ($item['price'] !== '') echo '<b>' . wp_kses_post($item['price']) . '</b>';
                    echo '</a>';
                }
                echo '</div>';
                echo '<a class="tt-board-link" href="' . esc_url(tt_menu_url()) . '">' . esc_html__('Browse the full menu', 'takeaway-theme') . '</a>';
                echo '</div>';
            } else {
                $hero_placeholder_text = trim($title . ' ' . $eyebrow . ' ' . (string) ttheme_business('cuisine', ''));
                echo '<div class="tt-home-hero-placeholder">' . tt_food_placeholder($hero_placeholder_text, '', $hero_label) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
            }
            ?>
        </div><!-- /.tt-home-hero-visual -->
    </div><!-- /.tt-home-hero-grid -->
</section>

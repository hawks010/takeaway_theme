<?php
/** Homepage hero. Image from Site Content (fallback Branding); no image -> branded gradient visual. */

defined('ABSPATH') || exit;

$eyebrow  = (string) tt_content('homepage', 'hero_eyebrow', '');
if ($eyebrow === '') {
    $cuisine = (string) ttheme_business('cuisine', '');
    $eyebrow = $cuisine !== '' ? $cuisine . ' · ' . __('Order direct', 'takeaway-theme') : __('Order direct', 'takeaway-theme');
}
$title    = (string) tt_content('homepage', 'hero_title', tt_business_name());
$subtitle = (string) tt_content('homepage', 'hero_subtitle', __('Fresh food for collection and delivery, straight from our kitchen — no marketplace middleman.', 'takeaway-theme'));

$primary_text = (string) tt_content('homepage', 'primary_cta_text', __('Order now', 'takeaway-theme'));
$primary_url  = tt_cta_url((string) tt_content('homepage', 'primary_cta_target', ''));
$secondary_text = (string) tt_content('homepage', 'secondary_cta_text', __('View menu', 'takeaway-theme'));
$secondary_url  = tt_cta_url((string) tt_content('homepage', 'secondary_cta_target', ''));

$image_id = absint(tt_content('homepage', 'hero_image_id', 0));
if (!$image_id) $image_id = absint(ttheme_brand('hero_image_id', 0));
$bg_id = absint(tt_content('homepage', 'hero_bg_image_id', 0));
$bg_url = $bg_id ? wp_get_attachment_image_url($bg_id, 'full') : '';

$show_toggle   = (string) tt_content('homepage', 'show_fulfilment_toggle', '1') === '1';
$show_postcode = (string) tt_content('homepage', 'show_postcode_checker', '1') === '1';
$show_status   = (string) tt_content('homepage', 'show_open_status', '1') === '1';
$delivery_url  = ttheme_page_url('delivery', '/delivery-checker/');

$delivery_on   = (string) tt_content('delivery_collection', 'delivery_enabled', '1') === '1';
$collection_on = (string) tt_content('delivery_collection', 'collection_enabled', '1') === '1';
?>
<section class="tt-home-hero<?php echo $bg_url ? ' has-bg' : ''; ?>"<?php echo $bg_url ? ' style="--tt-hero-bg:url(' . esc_url($bg_url) . ')"' : ''; ?>>
    <div class="tt-wrap tt-home-hero-grid">
        <div class="tt-home-hero-copy">
            <?php if ($show_status) { echo tt_open_status_pill(); } // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <p class="tt-eyebrow"><?php echo esc_html($eyebrow); ?></p>
            <h1><?php echo esc_html($title); ?></h1>
            <p class="tt-home-hero-sub"><?php echo esc_html($subtitle); ?></p>

            <?php if ($show_toggle && ($delivery_on || $collection_on)) : ?>
                <div class="tt-fulfilment-toggle" role="group" aria-label="<?php esc_attr_e('Order type', 'takeaway-theme'); ?>">
                    <?php if ($collection_on) : ?><a class="tt-fulfilment-pill" href="<?php echo esc_url(add_query_arg('fulfilment', 'collection', tt_menu_url())); ?>"><?php esc_html_e('Pickup', 'takeaway-theme'); ?></a><?php endif; ?>
                    <?php if ($delivery_on) : ?><a class="tt-fulfilment-pill" href="<?php echo esc_url(add_query_arg('fulfilment', 'delivery', tt_menu_url())); ?>"><?php esc_html_e('Delivery', 'takeaway-theme'); ?></a><?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="tt-home-hero-actions">
                <a class="tt-btn" href="<?php echo esc_url($primary_url); ?>"><?php echo esc_html($primary_text !== '' ? $primary_text : __('Order now', 'takeaway-theme')); ?></a>
                <?php if ($secondary_text !== '') : ?>
                    <a class="tt-btn ghost" href="<?php echo esc_url($secondary_url); ?>"><?php echo esc_html($secondary_text); ?></a>
                <?php endif; ?>
            </div>

            <?php if ($show_postcode && $delivery_on) : ?>
                <form class="tt-postcode-check" method="get" action="<?php echo esc_url($delivery_url); ?>">
                    <label for="tt-hero-postcode"><?php esc_html_e('Check we deliver to you', 'takeaway-theme'); ?></label>
                    <div class="tt-postcode-check-row">
                        <input type="text" id="tt-hero-postcode" name="postcode" maxlength="9" autocomplete="postal-code" placeholder="<?php esc_attr_e('Your postcode', 'takeaway-theme'); ?>">
                        <button type="submit" class="tt-btn"><?php esc_html_e('Check', 'takeaway-theme'); ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="tt-home-hero-visual">
            <?php
            $hero_img = $image_id ? tt_image($image_id, 'large', 'tt-home-hero-img', sprintf(__('Food from %s', 'takeaway-theme'), tt_business_name())) : '';
            if ($hero_img !== '') {
                echo '<div class="tt-home-hero-imgwrap">' . $hero_img . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
            } else {
                // Branded abstract visual — token-driven, never a broken external image.
                echo '<div class="tt-home-hero-placeholder" aria-hidden="true"><span class="tt-blob tt-blob-1"></span><span class="tt-blob tt-blob-2"></span><span class="tt-blob tt-blob-3"></span></div>';
            }
            ?>
        </div>
    </div>
</section>

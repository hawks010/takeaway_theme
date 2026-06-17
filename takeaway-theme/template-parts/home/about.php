<?php
/** About the business — hidden when no title and no text. */

defined('ABSPATH') || exit;

$title = (string) tt_content('homepage', 'about_title', '');
$text  = (string) tt_content('homepage', 'about_text', '');
if ($title === '' && $text === '') return;

$image = tt_image(absint(tt_content('homepage', 'about_image_id', 0)), 'large', 'tt-about-img', $title !== '' ? $title : tt_business_name());
$is_restaurant = function_exists('ttos_get_business_type') && ttos_get_business_type() === 'restaurant_takeaway';
$booking_enabled = (string) tt_content('contact_map', 'booking_enabled', '0') === '1';
?>
<section class="tt-section tt-home-about">
    <div class="tt-wrap tt-about-grid<?php echo $image === '' ? ' no-image' : ''; ?>">
        <div class="tt-about-copy">
            <p class="tt-eyebrow"><?php esc_html_e('About us', 'takeaway-theme'); ?></p>
            <?php if ($title !== '') : ?><h2><?php echo esc_html($title); ?></h2><?php endif; ?>
            <?php if ($text !== '') : ?><div class="tt-about-text"><?php echo wp_kses_post(wpautop($text)); ?></div><?php endif; ?>
            <?php if ($is_restaurant && $booking_enabled) : ?>
                <p class="tt-about-dinein"><?php esc_html_e('Dining in? You can book a table too.', 'takeaway-theme'); ?> <a href="#tt-booking"><?php esc_html_e('Booking info', 'takeaway-theme'); ?></a></p>
            <?php endif; ?>
        </div>
        <?php if ($image !== '') : ?>
            <div class="tt-about-media"><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
        <?php endif; ?>
    </div>
</section>

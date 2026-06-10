<?php
/**
 * Booking teaser — links out via phone/WhatsApp/URL or shows a note.
 * Shows only when enabled on the homepage; never a booking engine.
 */

defined('ABSPATH') || exit;

if ((string) tt_content('homepage', 'show_booking', '0') !== '1') return;

$title = (string) tt_content('homepage', 'booking_title', __('Book a table', 'takeaway-theme'));
$text  = (string) tt_content('homepage', 'booking_text', '');
$cta_text = (string) tt_content('homepage', 'booking_cta_text', '');
$target   = (string) tt_content('homepage', 'booking_cta_target', '');

// Fallback to the Contact & Map booking configuration.
if ($target === '') {
    $type = (string) tt_content('contact_map', 'booking_type', 'phone');
    $cm_target = (string) tt_content('contact_map', 'booking_target', '');
    if ($cm_target !== '') {
        $target = $cm_target;
    } elseif ($type === 'phone' && tt_phone() !== '') {
        $target = 'tel:' . preg_replace('/[^0-9+]/', '', tt_phone());
    }
    if ($cta_text === '') $cta_text = (string) tt_content('contact_map', 'booking_cta_text', '');
}
$note = (string) tt_content('contact_map', 'booking_note', '');

if ($target === '' && $text === '' && $note === '') return;
if ($cta_text === '' && $target !== '') $cta_text = __('Book now', 'takeaway-theme');
?>
<section class="tt-section tt-home-booking" id="tt-booking">
    <div class="tt-wrap tt-booking-card tt-card">
        <div>
            <p class="tt-eyebrow"><?php esc_html_e('Dine with us', 'takeaway-theme'); ?></p>
            <h2><?php echo esc_html($title); ?></h2>
            <?php if ($text !== '') : ?><div class="tt-booking-text"><?php echo wp_kses_post(wpautop($text)); ?></div><?php endif; ?>
            <?php if ($note !== '') : ?><p class="tt-booking-note"><?php echo esc_html($note); ?></p><?php endif; ?>
        </div>
        <?php if ($target !== '') : ?>
            <a class="tt-btn tt-booking-cta" href="<?php echo esc_url(tt_cta_url($target)); ?>"><?php echo esc_html($cta_text); ?></a>
        <?php endif; ?>
    </div>
</section>

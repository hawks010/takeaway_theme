<?php
/** Bottom call-to-action — closes the funnel; always renders with safe fallbacks. */

defined('ABSPATH') || exit;

$title = (string) tt_content('homepage', 'bottom_cta_title', __('Hungry now?', 'takeaway-theme'));
$text  = (string) tt_content('homepage', 'bottom_cta_text', __('Browse the menu and order in a couple of minutes.', 'takeaway-theme'));
$button = (string) tt_content('homepage', 'bottom_cta_button_text', __('Order now', 'takeaway-theme'));
$url = tt_cta_url((string) tt_content('homepage', 'bottom_cta_url', ''));
?>
<section class="tt-section tt-home-bottomcta">
    <div class="tt-wrap tt-bottomcta-inner">
        <h2><?php echo esc_html($title); ?></h2>
        <?php if ($text !== '') : ?><div class="tt-bottomcta-text"><?php echo wp_kses_post(wpautop($text)); ?></div><?php endif; ?>
        <a class="tt-btn tt-bottomcta-btn" href="<?php echo esc_url($url); ?>"><?php echo esc_html($button !== '' ? $button : __('Order now', 'takeaway-theme')); ?></a>
    </div>
</section>

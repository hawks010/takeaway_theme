<?php
/**
 * Mobile navigation drawer. Toggled by .tt-drawer-toggle in the site header;
 * behaviour (ESC, overlay click, focus management, scroll lock) lives in
 * assets/js/theme.js.
 */

defined('ABSPATH') || exit;

$phone = tt_phone();
$account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : '';
$delivery_url = ttheme_page_url('delivery', '/delivery-checker/');
?>
<div class="tt-drawer" id="tt-mobile-drawer" hidden>
    <div class="tt-drawer-overlay" data-drawer-close tabindex="-1"></div>
    <div class="tt-drawer-panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Site menu', 'takeaway-theme'); ?>">
        <div class="tt-drawer-head">
            <span class="tt-drawer-title"><?php echo esc_html(tt_business_name()); ?></span>
            <button type="button" class="tt-drawer-close" data-drawer-close aria-label="<?php esc_attr_e('Close menu', 'takeaway-theme'); ?>">×</button>
        </div>
        <nav class="tt-drawer-nav" aria-label="<?php esc_attr_e('Mobile', 'takeaway-theme'); ?>">
            <?php
            if (has_nav_menu('primary')) {
                wp_nav_menu(array('theme_location' => 'primary', 'container' => false, 'fallback_cb' => false, 'depth' => 1));
            } else {
                ttheme_fallback_nav('primary');
            }
            ?>
        </nav>
        <div class="tt-drawer-links">
            <?php if ($account_url) : ?>
                <a href="<?php echo esc_url($account_url); ?>"><?php echo is_user_logged_in() ? esc_html__('My account', 'takeaway-theme') : esc_html__('Log in / register', 'takeaway-theme'); ?></a>
            <?php endif; ?>
            <a href="<?php echo esc_url($delivery_url); ?>"><?php esc_html_e('Check delivery area', 'takeaway-theme'); ?></a>
            <?php if ($phone) : ?>
                <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>"><?php echo esc_html(sprintf(__('Call %s', 'takeaway-theme'), $phone)); ?></a>
            <?php endif; ?>
        </div>
        <a class="tt-btn tt-drawer-order" href="<?php echo esc_url(tt_menu_url()); ?>"><?php esc_html_e('Order now', 'takeaway-theme'); ?></a>
    </div>
</div>

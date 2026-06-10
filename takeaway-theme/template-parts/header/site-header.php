<?php
/**
 * Site header: brand, nav, status, contact, basket, order CTA.
 * Style variant (solid/transparent) comes from the Branding body class.
 */

defined('ABSPATH') || exit;

$logo_id = absint(ttheme_brand('logo_id', 0));
$phone   = tt_phone();
$account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : '';
$cart_url    = function_exists('wc_get_cart_url') ? wc_get_cart_url() : '';
?>
<header class="tt-siteheader" id="site-header">
    <div class="tt-wrap tt-siteheader-inner">
        <a class="tt-siteheader-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(tt_business_name() . ' — ' . __('home', 'takeaway-theme')); ?>">
            <?php
            if ($logo_id) {
                echo tt_image($logo_id, 'medium', 'tt-siteheader-logo', tt_business_name());
            } elseif (has_custom_logo()) {
                the_custom_logo();
            } else {
                echo '<span class="tt-siteheader-name">' . esc_html(tt_business_name()) . '</span>';
            }
            ?>
        </a>

        <nav class="tt-siteheader-nav" aria-label="<?php esc_attr_e('Primary', 'takeaway-theme'); ?>">
            <?php
            if (has_nav_menu('primary')) {
                wp_nav_menu(array('theme_location' => 'primary', 'container' => false, 'fallback_cb' => false, 'depth' => 1));
            } else {
                ttheme_fallback_nav('primary');
            }
            ?>
        </nav>

        <div class="tt-siteheader-actions">
            <?php echo tt_open_status_pill(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper. ?>
            <?php if ($phone) : ?>
                <a class="tt-siteheader-phone" href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>">
                    <span aria-hidden="true">☎</span><span class="tt-siteheader-phone-number"><?php echo esc_html($phone); ?></span>
                </a>
            <?php endif; ?>
            <?php if ($account_url) : ?>
                <a class="tt-siteheader-account" href="<?php echo esc_url($account_url); ?>">
                    <?php echo is_user_logged_in() ? esc_html__('Account', 'takeaway-theme') : esc_html__('Log in', 'takeaway-theme'); ?>
                </a>
            <?php endif; ?>
            <?php if ($cart_url) : ?>
                <a class="tt-siteheader-basket" href="<?php echo esc_url($cart_url); ?>" aria-label="<?php esc_attr_e('View basket', 'takeaway-theme'); ?>">
                    <span aria-hidden="true">🧺</span><?php tt_cart_count_badge(); ?>
                </a>
            <?php endif; ?>
            <a class="tt-btn tt-siteheader-order" href="<?php echo esc_url(tt_menu_url()); ?>"><?php esc_html_e('Order now', 'takeaway-theme'); ?></a>
            <button type="button" class="tt-drawer-toggle" aria-expanded="false" aria-controls="tt-mobile-drawer" aria-label="<?php esc_attr_e('Open menu', 'takeaway-theme'); ?>">
                <span class="tt-drawer-toggle-bars" aria-hidden="true"><i></i><i></i><i></i></span>
            </button>
        </div>
    </div>
</header>

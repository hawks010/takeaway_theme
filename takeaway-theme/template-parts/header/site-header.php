<?php
/**
 * Site header v0.3.5
 *   Utility strip  (status pill · contacts centred · socials right)
 *   Main nav row   (logo · nav · account pill · basket · order CTA · hamburger)
 *   Contact panel  (mega-menu that drops from the sticky header)
 */

defined('ABSPATH') || exit;

$logo_id           = absint(ttheme_brand('logo_id', 0));
$header_style      = tt_brand_layout('header_style', 'utility_header');
$phone             = tt_phone();
$email             = tt_email();
$biz_name          = tt_business_name();
$show_phone        = ttheme_brand('show_header_phone', '1') !== '0';
$show_email        = ttheme_brand('show_header_email', '1') !== '0';
$show_hdr_socials  = ttheme_brand('show_header_socials', '1') !== '0';
$account_url       = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : '';
$cart_url          = function_exists('wc_get_cart_url') ? wc_get_cart_url() : '';

$soc_keys = array(
    'instagram'   => 'Instagram',
    'facebook'    => 'Facebook',
    'tiktok'      => 'TikTok',
    'twitter'     => 'X / Twitter',
    'youtube'     => 'YouTube',
    'whatsapp'    => 'WhatsApp',
    'google'      => 'Google',
    'tripadvisor' => 'Tripadvisor',
);
$socials = array();
foreach ($soc_keys as $key => $label) {
    $url = (string) tt_content('social_links', $key, '');
    if ($url !== '') {
        $socials[] = array('key' => $key, 'url' => $url, 'label' => $label);
    }
}
$has_contacts = ($show_phone && $phone) || ($show_email && $email);
$utility_classes = array('tt-wrap', 'tt-utility-top');
if (!empty($socials) && $show_hdr_socials) {
    $utility_classes[] = 'has-socials';
}
if ($has_contacts) {
    $utility_classes[] = 'has-contacts';
}

// Account: detect login state.
$is_logged_in  = is_user_logged_in();
$current_user  = $is_logged_in ? wp_get_current_user() : null;
$user_first    = $current_user ? ((string) $current_user->user_firstname ?: (string) $current_user->display_name) : '';
$orders_url    = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('orders') : $account_url;
$address_url   = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('edit-address') : $account_url;
$payment_url   = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('payment-methods') : $account_url;
$acct_edit_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('edit-account') : $account_url;
$logout_url    = function_exists('wc_logout_url') ? wc_logout_url(home_url('/')) : wp_logout_url(home_url('/'));
?>
<header class="tt-siteheader tt-siteheader-<?php echo esc_attr($header_style); ?>" id="site-header">

    <!-- ── Utility strip: collapses on scroll ─────────────────── -->
    <div class="tt-utility" id="tt-utility">
        <div class="<?php echo esc_attr(implode(' ', $utility_classes)); ?>">

            <a class="tt-mobile-top-brand"
               href="<?php echo esc_url(home_url('/')); ?>"
               aria-label="<?php echo esc_attr($biz_name . ' — ' . __('home', 'takeaway-theme')); ?>">
                <?php
                $initial = function_exists('mb_strtoupper') ? mb_strtoupper(mb_substr($biz_name, 0, 1)) : strtoupper(substr($biz_name, 0, 1));
                echo '<span class="tt-logo-mark" aria-hidden="true">' . esc_html($initial) . '</span>';
                echo '<span>' . esc_html($biz_name) . '</span>';
                ?>
            </a>

            <!-- Left: status pill -->
            <div class="tt-utility-status">
                <?php echo tt_open_status_pill(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            </div>

            <!-- Centre: contact links (hidden on narrow screens) -->
            <div class="tt-utility-contacts">
                <?php if ($show_phone && $phone) : ?>
                    <a class="tt-contact-link" href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 10.8 19.79 19.79 0 01.07 2.18 2 2 0 012.03 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                        <span><?php echo esc_html($phone); ?></span>
                    </a>
                <?php endif; ?>
                <?php if ($show_email && $email) : ?>
                    <a class="tt-contact-link" href="mailto:<?php echo esc_attr($email); ?>">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/></svg>
                        <span><?php echo esc_html($email); ?></span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Right: social icons only -->
            <div class="tt-utility-right">
                <?php if ($show_hdr_socials && !empty($socials)) : ?>
                    <nav class="tt-socials" aria-label="<?php esc_attr_e('Social links', 'takeaway-theme'); ?>">
                        <?php foreach ($socials as $s) : ?>
                            <a class="tt-social"
                               href="<?php echo esc_url($s['url']); ?>"
                               aria-label="<?php echo esc_attr($s['label']); ?>"
                               target="_blank" rel="noopener noreferrer">
                                <?php echo tt_social_svg($s['key']); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
            </div>

        </div><!-- /.tt-utility-top -->
    </div><!-- /.tt-utility -->

    <!-- ── Main nav row: always visible / sticky ─────────────── -->
    <div class="tt-wrap tt-siteheader-main tt-siteheader-main-<?php echo esc_attr($header_style); ?>">

        <a class="tt-siteheader-brand"
           href="<?php echo esc_url(home_url('/')); ?>"
           aria-label="<?php echo esc_attr($biz_name . ' — ' . __('home', 'takeaway-theme')); ?>">
            <?php
            if ($logo_id) {
                echo tt_image($logo_id, 'medium', 'tt-siteheader-logo', $biz_name); // phpcs:ignore
            } elseif (has_custom_logo()) {
                the_custom_logo();
            } else {
                $initial = function_exists('mb_strtoupper') ? mb_strtoupper(mb_substr($biz_name, 0, 1)) : strtoupper(substr($biz_name, 0, 1));
                echo '<span class="tt-logo-mark" aria-hidden="true">' . esc_html($initial) . '</span>';
                echo '<span class="tt-siteheader-name">' . esc_html($biz_name) . '</span>';
            }
            ?>
        </a>

        <nav class="tt-main-nav" aria-label="<?php esc_attr_e('Primary', 'takeaway-theme'); ?>">
            <?php
            if (has_nav_menu('primary')) {
                wp_nav_menu(array('theme_location' => 'primary', 'container' => false, 'fallback_cb' => false, 'depth' => 1));
            } else {
                ttheme_fallback_nav('primary');
            }
            ?>
        </nav>

        <div class="tt-siteheader-actions">

            <!-- Account: round icon (logged-out) or "Hello, Name 👋" pill (logged-in) -->
            <?php if ($account_url) : ?>
            <div class="tt-acct-main" id="tt-acct-main">
                <?php if ($is_logged_in && $current_user) : ?>
                    <button class="tt-acct-main-btn" id="tt-acct-main-btn" type="button"
                            aria-expanded="false" aria-haspopup="true"
                            aria-controls="tt-acct-main-drop"
                            aria-label="<?php echo esc_attr(sprintf(__('My account — %s', 'takeaway-theme'), $user_first)); ?>">
                        <span class="tt-acct-main-avatar" aria-hidden="true">
                            <svg class="tt-fa-user" viewBox="0 0 448 512" focusable="false" aria-hidden="true">
                                <path fill="currentColor" d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512h388.6c16.4 0 29.7-13.3 29.7-29.7C448 383.8 368.2 304 269.7 304h-91.4z"/>
                            </svg>
                        </span>
                        <span class="tt-acct-main-hello">
                            <?php
                            /* translators: %s: customer first name */
                            echo esc_html(sprintf(__('Hello, %s', 'takeaway-theme'), $user_first));
                            ?>
                            <span aria-hidden="true"> 👋</span>
                        </span>
                        <svg class="tt-chevron" width="11" height="11" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                             aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>

                    <div class="tt-acct-main-drop" id="tt-acct-main-drop"
                         aria-label="<?php esc_attr_e('My account', 'takeaway-theme'); ?>">
                        <div class="tt-dropdown-user">
                            <strong><?php echo esc_html($current_user->display_name); ?></strong>
                            <span><?php echo esc_html($current_user->user_email); ?></span>
                        </div>
                        <nav class="tt-dropdown-nav">
                            <a href="<?php echo esc_url($orders_url); ?>">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/></svg>
                                <?php esc_html_e('My Orders', 'takeaway-theme'); ?>
                            </a>
                            <a href="<?php echo esc_url($address_url); ?>">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                <?php esc_html_e('Addresses', 'takeaway-theme'); ?>
                            </a>
                            <a href="<?php echo esc_url($payment_url); ?>">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                <?php esc_html_e('Payment Methods', 'takeaway-theme'); ?>
                            </a>
                            <a href="<?php echo esc_url($acct_edit_url); ?>">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                                <?php esc_html_e('Account Details', 'takeaway-theme'); ?>
                            </a>
                        </nav>
                        <a href="<?php echo esc_url($logout_url); ?>" class="tt-dropdown-logout">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            <?php esc_html_e('Sign out', 'takeaway-theme'); ?>
                        </a>
                    </div>

                <?php else : ?>
                    <a class="tt-acct-icon" href="<?php echo esc_url($account_url); ?>"
                       aria-label="<?php esc_attr_e('Login to your account', 'takeaway-theme'); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($cart_url) : ?>
            <div class="tt-basket-wrap" id="tt-basket-wrap">
                <button class="tt-basket-btn" id="tt-basket-btn" type="button"
                        aria-expanded="false" aria-controls="tt-cart-preview">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                    <span class="tt-basket-label"><?php esc_html_e('Basket', 'takeaway-theme'); ?></span>
                    <?php tt_cart_count_badge(); ?>
                </button>
                <div class="tt-cart-preview" id="tt-cart-preview"
                     role="region" aria-label="<?php esc_attr_e('Basket preview', 'takeaway-theme'); ?>">
                    <?php echo tt_cart_preview_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </div>
            </div>
            <?php endif; ?>

            <a class="tt-btn tt-siteheader-order" href="<?php echo esc_url(tt_menu_url()); ?>">
                <span class="tt-order-full"><?php esc_html_e('Order now', 'takeaway-theme'); ?></span>
                <span class="tt-order-short"><?php esc_html_e('Order', 'takeaway-theme'); ?></span>
            </a>

            <button type="button"
                    class="tt-drawer-toggle"
                    aria-expanded="false"
                    aria-controls="tt-mobile-drawer"
                    aria-label="<?php esc_attr_e('Open menu', 'takeaway-theme'); ?>">
                <span class="tt-drawer-toggle-bars" aria-hidden="true"><i></i><i></i><i></i></span>
            </button>

        </div><!-- /.tt-siteheader-actions -->
    </div><!-- /.tt-siteheader-main -->

    <!-- ── Contact mega panel ─────────────────────────────────── -->
    <div class="tt-contact-panel" id="tt-contact-panel"
         role="region" aria-label="<?php esc_attr_e('Contact us', 'takeaway-theme'); ?>"
         aria-hidden="true">
        <div class="tt-wrap tt-cpanel-inner">

            <div class="tt-cpanel-copy">
                <h3 class="tt-cpanel-heading"><?php esc_html_e('Get in touch', 'takeaway-theme'); ?></h3>
                <p class="tt-cpanel-sub"><?php esc_html_e("We'd love to hear from you. Drop us a message and we'll get back to you shortly.", 'takeaway-theme'); ?></p>
                <div class="tt-cpanel-chips">
                    <?php if ($phone) : ?>
                        <a class="tt-contact-link" href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 10.8 19.79 19.79 0 01.07 2.18 2 2 0 012.03 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                            <span><?php echo esc_html($phone); ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($email) : ?>
                        <a class="tt-contact-link" href="mailto:<?php echo esc_attr($email); ?>">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/></svg>
                            <span><?php echo esc_html($email); ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tt-cpanel-form">
                <?php echo tt_contact_form_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            </div>

            <button class="tt-cpanel-close" type="button" id="tt-cpanel-close"
                    aria-label="<?php esc_attr_e('Close contact panel', 'takeaway-theme'); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

        </div><!-- /.tt-cpanel-inner -->
    </div><!-- /.tt-contact-panel -->

</header>

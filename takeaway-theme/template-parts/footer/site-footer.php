<?php
/**
 * Site footer. Every column hides gracefully when its content is missing;
 * visibility toggles come from Site Content → Footer.
 */

defined('ABSPATH') || exit;

$show = static function (string $key): bool {
    return (string) tt_content('footer', $key, '1') === '1';
};

$footer_logo = absint(tt_content('footer', 'logo_id', 0));
if (!$footer_logo) $footer_logo = absint(ttheme_brand('logo_id', 0));
$footer_text = (string) tt_content('footer', 'text', '');
$address = tt_address_lines();
$phone = tt_phone();
$email = tt_email();
$hours = tt_hours_summary();
$socials = $show('show_social') ? tt_social_links() : array();
$social_keys = array(
    'Instagram' => 'instagram',
    'Facebook' => 'facebook',
    'TikTok' => 'tiktok',
    'WhatsApp' => 'whatsapp',
    'Google' => 'google',
    'TripAdvisor' => 'tripadvisor',
    'X' => 'twitter',
    'YouTube' => 'youtube',
);

$hygiene = (string) tt_content('business_info', 'hygiene_rating', '');
if ($hygiene === '') $hygiene = (string) ttheme_business('fsa_rating', '');
$hygiene_url = (string) tt_content('business_info', 'hygiene_authority_url', '');
$google_url = (string) tt_content('business_info', 'google_url', '');
$tripadvisor_url = (string) tt_content('business_info', 'tripadvisor_url', '');

$quick_links = array(
    __('Menu', 'takeaway-theme')        => tt_menu_url(),
    __('Track order', 'takeaway-theme') => ttheme_page_url('tracker', '/order-tracker/'),
    __('Delivery checker', 'takeaway-theme') => ttheme_page_url('delivery', '/delivery-checker/'),
    __('My account', 'takeaway-theme')  => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : '',
);

// Legal links: prefer Takeaway-generated policy pages, hide what doesn't exist.
$tt_policy_link = static function (string $key): string {
    $id = absint(get_option('ttos_page_' . $key, 0));
    return ($id && get_post_status($id) === 'publish') ? (string) get_permalink($id) : '';
};
$legal_links = array();
if ($show('show_legal_links')) {
    $legal_specs = array(
        'policy_privacy' => __('Privacy policy', 'takeaway-theme'),
        'policy_cookies' => __('Cookies', 'takeaway-theme'),
        'policy_terms'   => __('Terms & conditions', 'takeaway-theme'),
        'policy_refunds' => __('Refunds', 'takeaway-theme'),
        'policy_delivery' => __('Delivery policy', 'takeaway-theme'),
        'policy_hygiene' => __('Food hygiene', 'takeaway-theme'),
        'policy_business' => __('Business details', 'takeaway-theme'),
    );
    foreach ($legal_specs as $key => $label) {
        $url = $tt_policy_link($key);
        if ($url !== '') $legal_links[$label] = $url;
    }
    if (!isset($legal_links[__('Privacy policy', 'takeaway-theme')]) && function_exists('get_privacy_policy_url') && get_privacy_policy_url()) {
        $legal_links[__('Privacy policy', 'takeaway-theme')] = get_privacy_policy_url();
    }
}

$footer_style = tt_brand_layout('footer_style', 'trust_led');
if ($show('show_allergen_link')) {
    $allergens_url = ttheme_page_url('allergens', '');
    if ($allergens_url !== home_url('')) $legal_links[__('Allergen information', 'takeaway-theme')] = $allergens_url;
}
if ($show('show_accessibility_link')) {
    $accessibility_url = $tt_policy_link('policy_accessibility');
    if ($accessibility_url !== '') $legal_links[__('Accessibility', 'takeaway-theme')] = $accessibility_url;
}
$trust_bits = array();
$trust_html = '';
if ($show('show_hygiene') && $hygiene !== '') {
    $badge = sprintf(__('Food hygiene rating %s/5', 'takeaway-theme'), $hygiene);
    $trust_bits[] = $hygiene_url ? '<a href="' . esc_url($hygiene_url) . '" rel="noopener noreferrer" target="_blank">' . esc_html($badge) . '</a>' : esc_html($badge);
}
if ($show('show_google') && $google_url !== '') {
    $trust_bits[] = '<a href="' . esc_url($google_url) . '" rel="noopener noreferrer" target="_blank">' . esc_html__('Find us on Google', 'takeaway-theme') . '</a>';
}
if ($show('show_tripadvisor') && $tripadvisor_url !== '') {
    $trust_bits[] = '<a href="' . esc_url($tripadvisor_url) . '" rel="noopener noreferrer" target="_blank">' . esc_html__('TripAdvisor', 'takeaway-theme') . '</a>';
}
if ($trust_bits) {
    $trust_html = '<div class="tt-sitefooter-trust">' . implode('<span aria-hidden="true"> · </span>', $trust_bits) . '</div>';
}
?>
<footer class="tt-sitefooter tt-sitefooter-<?php echo esc_attr($footer_style); ?>">
    <div class="tt-wrap">
        <?php if ($footer_style === 'editorial' && $trust_html !== '') : ?>
            <div class="tt-sitefooter-trust-wrap tt-sitefooter-trust-wrap-top">
                <?php echo $trust_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>
            </div>
        <?php endif; ?>
        <div class="tt-sitefooter-grid">
            <div class="tt-sitefooter-col tt-sitefooter-brandcol">
                <?php if ($footer_logo) : ?>
                    <p class="tt-sitefooter-logo"><?php echo tt_image($footer_logo, 'medium', '', tt_business_name()); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
                <?php endif; ?>
                <p class="tt-sitefooter-name"><?php echo esc_html(tt_business_name()); ?></p>
                <?php if ($footer_text !== '') : ?>
                    <div class="tt-sitefooter-text"><?php echo wp_kses_post(wpautop($footer_text)); ?></div>
                <?php else : ?>
                    <p class="tt-sitefooter-text"><?php esc_html_e('Order direct for collection and delivery.', 'takeaway-theme'); ?></p>
                <?php endif; ?>
                <?php if ($socials) : ?>
                    <ul class="tt-sitefooter-social" aria-label="<?php esc_attr_e('Social links', 'takeaway-theme'); ?>">
                        <?php foreach ($socials as $label => $url) : ?>
                            <?php $social_key = $social_keys[$label] ?? sanitize_key($label); ?>
                            <li>
                                <a href="<?php echo esc_url($url); ?>" rel="noopener noreferrer" target="_blank" aria-label="<?php echo esc_attr($label); ?>">
                                    <?php echo tt_social_svg($social_key); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                    <span class="screen-reader-text"><?php echo esc_html($label); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if (($show('show_contact') && ($address || $phone || $email)) || ($show('show_opening_times') && $hours)) : ?>
            <div class="tt-sitefooter-col tt-sitefooter-contactcol">
                <h3 class="tt-sitefooter-heading"><?php esc_html_e('Find us', 'takeaway-theme'); ?></h3>
                <?php if ($show('show_contact')) : ?>
                <?php if ($address) : ?><p class="tt-sitefooter-address"><?php echo esc_html(implode(', ', $address)); ?></p><?php endif; ?>
                <?php if ($phone) : ?><p><a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a></p><?php endif; ?>
                <?php if ($email) : ?><p><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></p><?php endif; ?>
                <?php endif; ?>
                <?php if ($show('show_opening_times') && $hours) : ?>
                <div class="tt-sitefooter-hours-wrap">
                    <h4><?php esc_html_e('Opening times', 'takeaway-theme'); ?></h4>
                <ul class="tt-sitefooter-hours">
                    <?php foreach ($hours as $row) : ?>
                        <li><span><?php echo esc_html($row['label']); ?></span><strong><?php echo esc_html($row['value']); ?></strong></li>
                    <?php endforeach; ?>
                </ul>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="tt-sitefooter-col tt-sitefooter-actions">
                <h3 class="tt-sitefooter-heading"><?php esc_html_e('Order direct', 'takeaway-theme'); ?></h3>
                <ul class="tt-sitefooter-links">
                    <?php foreach ($quick_links as $label => $url) : if (!$url) continue; ?>
                        <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <a class="tt-sitefooter-cta" href="<?php echo esc_url(tt_menu_url()); ?>"><?php esc_html_e('Start an order', 'takeaway-theme'); ?></a>
            </div>

            <?php if ($legal_links) : ?>
            <div class="tt-sitefooter-col tt-sitefooter-legal">
                <h3 class="tt-sitefooter-heading"><?php esc_html_e('Useful links', 'takeaway-theme'); ?></h3>
                <ul class="tt-sitefooter-links">
                    <?php foreach ($legal_links as $label => $url) : ?>
                        <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($footer_style !== 'editorial' && $trust_html !== '') : ?>
            <div class="tt-sitefooter-trust-wrap">
                <?php echo $trust_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>
            </div>
        <?php endif; ?>

        <div class="tt-sitefooter-bottom">
            <span class="iw-copy"><span class="iw-year">© 2025 - <?php echo esc_html(gmdate('Y')); ?></span><span class="iw-sep" aria-hidden="true"> · </span><span class="iw-client"><?php echo esc_html(tt_business_name()); ?>. <?php esc_html_e('All Rights Reserved.', 'takeaway-theme'); ?></span></span>
            <?php
            if ((string) tt_content('footer', 'show_built_by', '1') === '1') {
                $built_url = (string) tt_content('footer', 'built_by_url', 'https://inkfire.co.uk?utm_source=blueprint&utm_medium=footer_signature');
                echo '<span class="iw-signature tt-sitefooter-credit">';
                echo '<span class="iw-text">' . esc_html__('Built & Maintained by', 'takeaway-theme') . '</span>';
                echo '<a class="iw-agency-link" href="' . esc_url($built_url) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__('Built and Maintained by Inkfire Limited', 'takeaway-theme') . '">';
                echo '<span class="iw-icon" aria-hidden="true">IF</span>';
                echo '<span class="iw-brand">Inkfire Limited</span>';
                echo '</a></span>';
            }
            ?>
        </div>
    </div>
</footer>

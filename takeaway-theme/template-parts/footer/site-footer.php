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

$legal_links = array();
if ($show('show_legal_links') && function_exists('get_privacy_policy_url') && get_privacy_policy_url()) {
    $legal_links[__('Privacy policy', 'takeaway-theme')] = get_privacy_policy_url();
}
if ($show('show_allergen_link')) {
    $allergens_url = ttheme_page_url('allergens', '');
    if ($allergens_url !== home_url('')) $legal_links[__('Allergen information', 'takeaway-theme')] = $allergens_url;
}
if ($show('show_accessibility_link')) {
    $accessibility = get_page_by_path('accessibility-statement');
    if ($accessibility) $legal_links[__('Accessibility', 'takeaway-theme')] = get_permalink($accessibility);
}
?>
<footer class="tt-sitefooter tt-sitefooter-<?php echo esc_attr(sanitize_key(ttheme_brand('footer_style', 'dark') ?: 'dark')); ?>">
    <div class="tt-wrap">
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
                            <li><a href="<?php echo esc_url($url); ?>" rel="noopener noreferrer" target="_blank"><?php echo esc_html($label); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if ($show('show_contact') && ($address || $phone || $email)) : ?>
            <div class="tt-sitefooter-col">
                <h2 class="tt-sitefooter-heading"><?php esc_html_e('Find us', 'takeaway-theme'); ?></h2>
                <?php if ($address) : ?><p class="tt-sitefooter-address"><?php echo esc_html(implode(', ', $address)); ?></p><?php endif; ?>
                <?php if ($phone) : ?><p><a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a></p><?php endif; ?>
                <?php if ($email) : ?><p><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></p><?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($show('show_opening_times') && $hours) : ?>
            <div class="tt-sitefooter-col">
                <h2 class="tt-sitefooter-heading"><?php esc_html_e('Opening times', 'takeaway-theme'); ?></h2>
                <ul class="tt-sitefooter-hours">
                    <?php foreach ($hours as $row) : ?>
                        <li><span><?php echo esc_html($row['label']); ?></span><strong><?php echo esc_html($row['value']); ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="tt-sitefooter-col">
                <h2 class="tt-sitefooter-heading"><?php esc_html_e('Quick links', 'takeaway-theme'); ?></h2>
                <ul class="tt-sitefooter-links">
                    <?php foreach ($quick_links as $label => $url) : if (!$url) continue; ?>
                        <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a></li>
                    <?php endforeach; ?>
                    <?php foreach ($legal_links as $label => $url) : ?>
                        <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <?php
        $trust_bits = array();
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
        if ($trust_bits) :
        ?>
        <div class="tt-sitefooter-trust"><?php echo implode('<span aria-hidden="true"> · </span>', $trust_bits); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?></div>
        <?php endif; ?>

        <div class="tt-sitefooter-bottom">
            <span>© <?php echo esc_html(gmdate('Y')); ?> <?php echo esc_html(tt_business_name()); ?></span>
            <?php
            if ((string) tt_content('footer', 'show_built_by', '1') === '1') {
                $built_text = (string) tt_content('footer', 'built_by_text', 'Built by Inkfire');
                $built_url  = (string) tt_content('footer', 'built_by_url', 'https://inkfire.co.uk');
                if ($built_text !== '') {
                    echo '<span class="tt-sitefooter-credit">';
                    if ($built_url !== '') {
                        echo '<a href="' . esc_url($built_url) . '" rel="noopener noreferrer" target="_blank">' . esc_html($built_text) . '</a>';
                    } else {
                        echo esc_html($built_text);
                    }
                    echo '</span>';
                }
            }
            ?>
        </div>
    </div>
</footer>

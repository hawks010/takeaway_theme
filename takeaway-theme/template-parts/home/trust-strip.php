<?php
/** Trust strip: estimates, hygiene, Google/TripAdvisor. Hides empty badges; hides entirely when off or empty. */

defined('ABSPATH') || exit;

if ((string) tt_content('homepage', 'show_trust_strip', '1') !== '1') return;

$items = array();

$delivery_estimate = (string) tt_content('delivery_collection', 'delivery_estimate_text', '');
if ($delivery_estimate === '' && (string) tt_content('delivery_collection', 'delivery_enabled', '1') === '1') {
    $mins = tt_trading('delivery_time');
    if ($mins !== '') $delivery_estimate = sprintf(__('Delivery ~%s mins', 'takeaway-theme'), $mins);
}
if ($delivery_estimate !== '') $items[] = array('icon' => '🛵', 'html' => esc_html($delivery_estimate));

$collection_estimate = (string) tt_content('delivery_collection', 'collection_estimate_text', '');
if ($collection_estimate === '' && (string) tt_content('delivery_collection', 'collection_enabled', '1') === '1') {
    $mins = tt_trading('prep_time');
    if ($mins !== '') $collection_estimate = sprintf(__('Collection ~%s mins', 'takeaway-theme'), $mins);
}
if ($collection_estimate !== '') $items[] = array('icon' => '🛍', 'html' => esc_html($collection_estimate));

$hygiene = (string) tt_content('business_info', 'hygiene_rating', '');
if ($hygiene === '') $hygiene = (string) ttheme_business('fsa_rating', '');
if ($hygiene !== '') {
    $label = esc_html(sprintf(__('Food hygiene %s/5', 'takeaway-theme'), $hygiene));
    $url = (string) tt_content('business_info', 'hygiene_authority_url', '');
    $items[] = array('icon' => '✓', 'html' => $url ? '<a href="' . esc_url($url) . '" rel="noopener noreferrer" target="_blank">' . $label . '</a>' : $label);
}

$google_rating = (string) tt_content('business_info', 'google_rating', '');
$google_url = (string) tt_content('business_info', 'google_url', '');
if ($google_rating !== '') {
    $count = (string) tt_content('business_info', 'google_review_count', '');
    $label = esc_html(sprintf(__('%s on Google', 'takeaway-theme'), $google_rating . '★') . ($count !== '' ? ' (' . $count . ')' : ''));
    $items[] = array('icon' => '', 'html' => $google_url ? '<a href="' . esc_url($google_url) . '" rel="noopener noreferrer" target="_blank">' . $label . '</a>' : $label);
}

$ta_rating = (string) tt_content('business_info', 'tripadvisor_rating', '');
$ta_url = (string) tt_content('business_info', 'tripadvisor_url', '');
if ($ta_rating !== '') {
    $label = esc_html(sprintf(__('%s on TripAdvisor', 'takeaway-theme'), $ta_rating . '★'));
    $items[] = array('icon' => '', 'html' => $ta_url ? '<a href="' . esc_url($ta_url) . '" rel="noopener noreferrer" target="_blank">' . $label . '</a>' : $label);
}

$free_delivery = (string) tt_content('delivery_collection', 'free_delivery_text', '');
if ($free_delivery !== '') $items[] = array('icon' => '', 'html' => esc_html($free_delivery));

if (!$items) return;
?>
<section class="tt-trust-strip" aria-label="<?php esc_attr_e('Why order with us', 'takeaway-theme'); ?>">
    <div class="tt-wrap tt-trust-strip-row">
        <?php foreach ($items as $item) : ?>
            <span class="tt-trust-item"><?php if ($item['icon']) : ?><span class="tt-trust-icon" aria-hidden="true"><?php echo esc_html($item['icon']); ?></span><?php endif; ?><?php echo $item['html']; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?></span>
        <?php endforeach; ?>
    </div>
</section>

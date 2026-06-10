<?php
/** Why order direct — cards from Site Content with neutral fallbacks. */

defined('ABSPATH') || exit;

$title = (string) tt_content('homepage', 'why_direct_title', __('Why order direct?', 'takeaway-theme'));
$cards = (array) tt_content('homepage', 'why_direct_cards', array());
$cards = array_values(array_filter($cards, static function ($card) {
    return is_array($card) && (($card['title'] ?? '') !== '' || ($card['text'] ?? '') !== '');
}));

if (!$cards) {
    $cards = array(
        array('title' => __('Better value', 'takeaway-theme'), 'text' => __('No marketplace fees baked into your food — ordering direct keeps prices honest.', 'takeaway-theme')),
        array('title' => __('Straight to our kitchen', 'takeaway-theme'), 'text' => __('Your order goes directly to the people cooking it, not through a third party.', 'takeaway-theme')),
        array('title' => __('Support a local business', 'takeaway-theme'), 'text' => __('Every direct order supports the kitchen, not an app company.', 'takeaway-theme')),
    );
}
?>
<section class="tt-section tt-home-whydirect">
    <div class="tt-wrap">
        <p class="tt-eyebrow"><?php esc_html_e('Order direct', 'takeaway-theme'); ?></p>
        <h2><?php echo esc_html($title); ?></h2>
        <div class="tt-whydirect-grid">
            <?php foreach (array_slice($cards, 0, 6) as $index => $card) : ?>
                <div class="tt-card tt-whydirect-card">
                    <span class="tt-whydirect-num" aria-hidden="true"><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span>
                    <h3><?php echo esc_html((string) ($card['title'] ?? '')); ?></h3>
                    <p><?php echo esc_html((string) ($card['text'] ?? '')); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

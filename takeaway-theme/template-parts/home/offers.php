<?php
/** Offers/meal deals from Site Content — schedule-aware, hidden when empty. */

defined('ABSPATH') || exit;

$offers = tt_active_offers();
if (!$offers) return;
?>
<section class="tt-section tt-home-offers">
    <div class="tt-wrap">
        <p class="tt-eyebrow"><?php esc_html_e('Offers', 'takeaway-theme'); ?></p>
        <h2><?php esc_html_e('Deals on now', 'takeaway-theme'); ?></h2>
        <div class="tt-offers-grid">
            <?php foreach (array_slice($offers, 0, 6) as $offer) : ?>
                <article class="tt-card tt-offer-card">
                    <?php
                    $image = tt_image(absint($offer['image_id'] ?? 0), 'medium_large', 'tt-offer-img', (string) ($offer['title'] ?? ''));
                    if ($image !== '') echo '<div class="tt-offer-media">' . $image . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
                    ?>
                    <div class="tt-offer-body">
                        <h3><?php echo esc_html((string) ($offer['title'] ?? '')); ?></h3>
                        <?php if (!empty($offer['text'])) : ?><div class="tt-offer-text"><?php echo wp_kses_post(wpautop($offer['text'])); ?></div><?php endif; ?>
                        <?php if (!empty($offer['cta_text'])) : ?>
                            <a class="tt-btn" href="<?php echo esc_url(tt_cta_url((string) ($offer['cta_url'] ?? ''))); ?>"><?php echo esc_html($offer['cta_text']); ?></a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

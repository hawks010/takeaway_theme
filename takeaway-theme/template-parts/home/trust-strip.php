<?php
/** Trust strip: estimates, hygiene, Google/TripAdvisor. Hides empty badges; hides entirely when off or empty. */

defined('ABSPATH') || exit;

if ((string) tt_content('homepage', 'show_trust_strip', '1') !== '1') return;

$items = tt_home_trust_items();

if (!$items) return;
?>
<section class="tt-trust-strip" aria-label="<?php esc_attr_e('Why order with us', 'takeaway-theme'); ?>">
    <div class="tt-wrap tt-trust-strip-row">
        <?php foreach ($items as $item) : ?>
            <span class="tt-trust-item"><?php if ($item['icon']) : ?><span class="tt-trust-icon" aria-hidden="true"><?php echo esc_html($item['icon']); ?></span><?php endif; ?><?php echo $item['html']; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?></span>
        <?php endforeach; ?>
    </div>
</section>

<?php
/** Reviews — manual Site Content reviews, featured first. Hidden when off or empty. */

defined('ABSPATH') || exit;

if ((string) tt_content('homepage', 'show_reviews', '1') !== '1') return;

$items = (array) tt_content('reviews', 'items', array());
$items = array_values(array_filter($items, static function ($item) {
    return is_array($item) && (($item['text'] ?? '') !== '' || ($item['name'] ?? '') !== '');
}));
if (!$items) return;

usort($items, static function ($a, $b) {
    return (int) ($b['featured'] ?? 0) <=> (int) ($a['featured'] ?? 0);
});

$title = (string) tt_content('reviews', 'title', __('What customers say', 'takeaway-theme'));
$subtitle = (string) tt_content('reviews', 'subtitle', '');
$google_url = ((string) tt_content('reviews', 'show_google_link', '1') === '1') ? (string) tt_content('business_info', 'google_url', '') : '';
$ta_url = ((string) tt_content('reviews', 'show_tripadvisor_link', '1') === '1') ? (string) tt_content('business_info', 'tripadvisor_url', '') : '';
?>
<section class="tt-section tt-home-reviews">
    <div class="tt-wrap">
        <p class="tt-eyebrow"><?php esc_html_e('Reviews', 'takeaway-theme'); ?></p>
        <h2><?php echo esc_html($title); ?></h2>
        <?php if ($subtitle !== '') : ?><p class="tt-section-sub"><?php echo esc_html($subtitle); ?></p><?php endif; ?>
        <div class="tt-reviews-grid">
            <?php foreach (array_slice($items, 0, 6) as $review) : ?>
                <blockquote class="tt-card tt-review-card">
                    <?php echo tt_stars((string) ($review['rating'] ?? '')); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <?php if (!empty($review['text'])) : ?><p class="tt-review-text"><?php echo esc_html($review['text']); ?></p><?php endif; ?>
                    <footer class="tt-review-meta">
                        <cite><?php echo esc_html((string) ($review['name'] ?? '')); ?></cite>
                        <?php
                        $source = (string) ($review['source_label'] ?? '');
                        $source_url = (string) ($review['source_url'] ?? '');
                        if ($source !== '') {
                            echo $source_url !== ''
                                ? ' · <a href="' . esc_url($source_url) . '" rel="noopener noreferrer" target="_blank">' . esc_html($source) . '</a>'
                                : ' · <span>' . esc_html($source) . '</span>';
                        }
                        ?>
                    </footer>
                </blockquote>
            <?php endforeach; ?>
        </div>
        <?php if ($google_url !== '' || $ta_url !== '') : ?>
            <p class="tt-reviews-links">
                <?php if ($google_url !== '') : ?><a href="<?php echo esc_url($google_url); ?>" rel="noopener noreferrer" target="_blank"><?php esc_html_e('Read more on Google', 'takeaway-theme'); ?></a><?php endif; ?>
                <?php if ($ta_url !== '') : ?><a href="<?php echo esc_url($ta_url); ?>" rel="noopener noreferrer" target="_blank"><?php esc_html_e('Read more on TripAdvisor', 'takeaway-theme'); ?></a><?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
</section>

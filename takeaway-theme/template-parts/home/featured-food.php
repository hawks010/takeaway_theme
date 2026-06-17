<?php
/**
 * Featured food: selected products, else selected categories, else the latest
 * published products. A taster, never the full menu. Hidden when no products
 * exist at all.
 */

defined('ABSPATH') || exit;

if (!post_type_exists('product')) return;

$title = (string) tt_content('homepage', 'featured_title', __('House favourites', 'takeaway-theme'));
$subtitle = (string) tt_content('homepage', 'featured_subtitle', '');

$product_ids = array_map('absint', (array) tt_content('homepage', 'featured_product_ids', array()));
$category_ids = array_map('absint', (array) tt_content('homepage', 'featured_category_ids', array()));

$cards = array();

if ($product_ids) {
    $query = get_posts(array('post_type' => 'product', 'post_status' => 'publish', 'post__in' => $product_ids, 'orderby' => 'post__in', 'numberposts' => 6));
} elseif ($category_ids) {
    $query = get_posts(array('post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 6, 'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $category_ids))));
} else {
    $query = get_posts(array('post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 3, 'orderby' => 'date', 'order' => 'DESC'));
}

foreach ($query as $product_post) {
    $price = get_post_meta($product_post->ID, '_price', true);
    $cards[] = array(
        'title' => $product_post->post_title,
        'price' => ($price !== '' && function_exists('wc_price')) ? wc_price((float) $price) : '',
        'image' => tt_image((int) get_post_thumbnail_id($product_post->ID), 'medium_large', 'tt-featured-img', $product_post->post_title),
        'excerpt' => wp_trim_words($product_post->post_excerpt ?: $product_post->post_content, 14),
    );
}

if (!$cards) return;
?>
<section class="tt-section tt-home-featured">
    <div class="tt-wrap">
        <p class="tt-eyebrow"><?php esc_html_e('From the menu', 'takeaway-theme'); ?></p>
        <div class="tt-section-head">
            <h2><?php echo esc_html($title); ?></h2>
            <a class="tt-btn ghost tt-section-head-cta" href="<?php echo esc_url(tt_menu_url()); ?>"><?php esc_html_e('View full menu', 'takeaway-theme'); ?></a>
        </div>
        <?php if ($subtitle !== '') : ?><p class="tt-section-sub"><?php echo esc_html($subtitle); ?></p><?php endif; ?>
        <div class="tt-featured-grid">
            <?php foreach ($cards as $card) : ?>
                <article class="tt-card tt-featured-card">
                    <?php if ($card['image'] !== '') : ?>
                        <div class="tt-featured-media"><?php echo $card['image']; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
                    <?php else : ?>
                        <div class="tt-featured-media tt-featured-media-empty" aria-hidden="true"><span></span></div>
                    <?php endif; ?>
                    <div class="tt-featured-body">
                        <h3><?php echo esc_html($card['title']); ?></h3>
                        <?php if ($card['excerpt'] !== '') : ?><p><?php echo esc_html($card['excerpt']); ?></p><?php endif; ?>
                        <div class="tt-featured-foot">
                            <?php if ($card['price'] !== '') : ?><strong class="tt-featured-price"><?php echo wp_kses_post($card['price']); ?></strong><?php endif; ?>
                            <a class="tt-featured-link" href="<?php echo esc_url(tt_menu_url()); ?>"><?php esc_html_e('Order', 'takeaway-theme'); ?> <span aria-hidden="true">→</span></a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

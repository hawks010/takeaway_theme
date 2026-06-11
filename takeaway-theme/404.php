<?php
/** Branded 404 with friendly recovery routes. */

defined('ABSPATH') || exit;

get_header();

$contact_url = '';
if (class_exists('TTOS_Page_Manager')) {
    $contact_id = absint(get_option('ttos_page_contact', 0));
    if ($contact_id && get_post_status($contact_id) === 'publish') {
        $contact_url = (string) get_permalink($contact_id);
    }
}
$products = post_type_exists('product')
    ? get_posts(array('post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 3, 'orderby' => 'date', 'order' => 'DESC'))
    : array();
?>
<section class="tt-section tt-404">
    <div class="tt-wrap tt-404-inner">
        <p class="tt-eyebrow"><?php esc_html_e('Page not found', 'takeaway-theme'); ?></p>
        <h1><?php esc_html_e('Lost your appetite? Not for long.', 'takeaway-theme'); ?></h1>
        <p class="tt-404-sub"><?php esc_html_e('That page has moved or never existed — but the food is exactly where it should be.', 'takeaway-theme'); ?></p>
        <div class="tt-404-actions">
            <a class="tt-btn" href="<?php echo esc_url(tt_menu_url()); ?>"><?php esc_html_e('View menu', 'takeaway-theme'); ?></a>
            <a class="tt-btn ghost" href="<?php echo esc_url(ttheme_page_url('delivery', '/delivery-checker/')); ?>"><?php esc_html_e('Check delivery', 'takeaway-theme'); ?></a>
            <?php if ($contact_url !== '') : ?>
                <a class="tt-btn ghost" href="<?php echo esc_url($contact_url); ?>"><?php esc_html_e('Contact us', 'takeaway-theme'); ?></a>
            <?php endif; ?>
        </div>
        <?php if ($products) : ?>
            <div class="tt-404-products">
                <?php foreach ($products as $product_post) :
                    $price = get_post_meta($product_post->ID, '_price', true);
                    $img = tt_image((int) get_post_thumbnail_id($product_post->ID), 'medium_large', '', $product_post->post_title);
                ?>
                <a class="tt-card tt-404-product" href="<?php echo esc_url(tt_menu_url()); ?>">
                    <?php if ($img !== '') : ?>
                        <span class="tt-404-product-media"><?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                    <?php else : ?>
                        <span class="tt-404-product-media tt-featured-media-empty" aria-hidden="true"><span></span></span>
                    <?php endif; ?>
                    <span class="tt-404-product-body"><strong><?php echo esc_html($product_post->post_title); ?></strong>
                    <?php if ($price !== '' && function_exists('wc_price')) : ?><span class="tt-404-product-price"><?php echo wp_kses_post(wc_price((float) $price)); ?></span><?php endif; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php get_footer(); ?>

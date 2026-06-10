<?php
get_header();
?>
<section class="tt-page-head">
    <div class="tt-wrap">
        <h1><?php woocommerce_page_title(); ?></h1>
    </div>
</section>
<section class="tt-wrap tt-section">
<?php
if (function_exists('is_shop') && is_shop() && shortcode_exists('takeaway_menu')) {
    echo do_shortcode('[takeaway_menu]');
    if (shortcode_exists('takeaway_meal_deals')) {
        echo do_shortcode('[takeaway_meal_deals]');
    }
} else {
    woocommerce_content();
}
?>
</section>
<?php get_footer(); ?>

<?php get_header(); ?>
<?php
$hero_id = absint(ttheme_brand('hero_image_id', 0));
$hero = $hero_id ? wp_get_attachment_image_url($hero_id, 'full') : 'https://images.unsplash.com/photo-1544025162-d76538579e09?auto=format&fit=crop&w=900&q=80';
$restaurant = ttheme_business('restaurant_name', get_bloginfo('name'));
$cuisine = ttheme_business('cuisine', 'Takeaway');
?>
<section class="tt-hero">
    <div class="tt-hero-shape"></div>
    <div class="tt-wrap tt-hero-grid">
        <div class="tt-hero-copy">
            <p class="tt-eyebrow"><?php echo esc_html($cuisine); ?> · Order direct</p>
            <h1>Hot food.<br>Zero fuss.<br><span>Direct.</span></h1>
            <p class="tt-sub"><?php echo esc_html($restaurant); ?> is ready for direct ordering: collection, delivery, menu, rewards and customer accounts without the marketplace middleman.</p>
            <div class="tt-hero-actions">
                <a class="tt-btn" href="<?php echo esc_url(ttheme_page_url('menu', '/menu/')); ?>">Order now</a>
                <a class="tt-btn ghost" href="#why-direct">Why direct?</a>
            </div>
            <div class="tt-chips"><span>5★ hygiene ready</span><span>Delivery & collection</span><span>Rewards-ready</span></div>
        </div>
        <div class="tt-food-orb"><img src="<?php echo esc_url($hero); ?>" alt="Fresh takeaway food"></div>
    </div>
</section>
<section class="tt-strip" id="why-direct"><div class="tt-wrap">Order direct and keep the money in the restaurant, not the aggregator’s pocket.</div></section>
<?php if (shortcode_exists('takeaway_home_blocks')) : ?>
<section class="tt-wrap tt-section tt-home-content-blocks">
    <?php echo do_shortcode('[takeaway_home_blocks]'); ?>
</section>
<?php endif; ?>
<section class="tt-wrap tt-section">
    <p class="tt-eyebrow">Browse</p>
    <h2>Pick your obsession</h2>
    <?php echo do_shortcode('[takeaway_menu]'); ?>
</section>
<?php if (shortcode_exists('takeaway_meal_deals')) : ?>
<section class="tt-wrap tt-section">
    <?php echo do_shortcode('[takeaway_meal_deals]'); ?>
</section>
<?php endif; ?>
<section class="tt-section tt-dark"><div class="tt-wrap tt-two">
    <div><p class="tt-eyebrow">Simple ops</p><h2>Built for busy kitchens</h2><p>Menu availability, order flow, customer CRM, money reports and bolt-on modules live inside Takeaway OS.</p></div>
    <div class="tt-panel"><h3>Core system</h3><ul><li>WooCommerce order engine</li><li>Takeaway OS manager dashboard</li><li>Optional printer, SMS, loyalty and EPOS connectors</li></ul></div>
</div></section>
<?php get_footer(); ?>

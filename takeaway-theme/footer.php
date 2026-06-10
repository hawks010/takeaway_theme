<?php defined('ABSPATH') || exit; ?>
</main>
<footer class="tt-footer">
    <div class="tt-wrap tt-footer-grid">
        <div>
            <h2><?php echo esc_html(ttheme_business('restaurant_name', get_bloginfo('name'))); ?></h2>
            <p><?php echo esc_html(ttheme_business('tagline', get_bloginfo('description'))); ?></p>
            <?php if (has_nav_menu('footer')) : ?>
                <nav class="tt-footer-nav" aria-label="Footer">
                    <?php wp_nav_menu(array('theme_location' => 'footer', 'container' => false, 'fallback_cb' => false)); ?>
                </nav>
            <?php else : ?>
                <nav class="tt-footer-nav" aria-label="Footer"><?php ttheme_fallback_nav('footer'); ?></nav>
            <?php endif; ?>
        </div>
        <div>
            <h3>Find us</h3>
            <p><?php echo esc_html(ttheme_business('address_1', '')); ?><br><?php echo esc_html(ttheme_business('town', '')); ?><br><?php echo esc_html(ttheme_business('postcode', '')); ?></p>
        </div>
        <div>
            <h3>Order direct</h3>
            <p><a href="tel:<?php echo esc_attr(ttheme_business('phone', '')); ?>"><?php echo esc_html(ttheme_business('phone', '')); ?></a></p>
            <a class="tt-footer-order" href="<?php echo esc_url(ttheme_page_url('menu', '/menu/')); ?>">Start order</a>
        </div>
    </div>
    <div class="tt-wrap tt-credit">Powered by Takeaway Theme · Built by Inkfire</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>

<?php
/**
 * Shop / Menu page. Compact heading from Site Content (the menu is the star),
 * then the Takeaway ordering experience.
 */

defined('ABSPATH') || exit;

get_header();

$is_menu = function_exists('is_shop') && is_shop() && shortcode_exists('takeaway_menu');

if ($is_menu) :
    $eyebrow  = (string) tt_content('menu_page', 'eyebrow', __('Order direct', 'takeaway-theme'));
    $title    = (string) tt_content('menu_page', 'title', __('Menu', 'takeaway-theme'));
    $subtitle = (string) tt_content('menu_page', 'subtitle', '');
    $intro    = (string) tt_content('menu_page', 'intro_text', '');
    $footer_text = (string) tt_content('menu_page', 'footer_text', '');
    $show_postcode = (string) tt_content('menu_page', 'show_postcode_checker', '1') === '1'
        && (string) tt_content('delivery_collection', 'delivery_enabled', '1') === '1';
    $bg_id = absint(tt_content('menu_page', 'hero_image_id', 0));
    $bg_url = $bg_id ? wp_get_attachment_image_url($bg_id, 'full') : '';
?>
<section class="tt-pagehead tt-pagehead-menu<?php echo $bg_url ? ' has-bg' : ''; ?>"<?php echo $bg_url ? ' style="--tt-pagehead-bg:url(' . esc_url($bg_url) . ')"' : ''; ?>>
    <div class="tt-wrap tt-pagehead-row">
        <div>
            <p class="tt-eyebrow"><?php echo esc_html($eyebrow); ?></p>
            <h1><?php echo esc_html($title); ?></h1>
            <?php if ($subtitle !== '') : ?><p class="tt-pagehead-sub"><?php echo esc_html($subtitle); ?></p><?php endif; ?>
        </div>
        <?php if ($show_postcode) : ?>
            <form class="tt-postcode-check tt-pagehead-postcode" method="get" action="<?php echo esc_url(ttheme_page_url('delivery', '/delivery-checker/')); ?>">
                <label for="tt-menu-postcode"><?php esc_html_e('Check we deliver to you', 'takeaway-theme'); ?></label>
                <div class="tt-postcode-check-row">
                    <input type="text" id="tt-menu-postcode" name="postcode" maxlength="9" autocomplete="postal-code" placeholder="<?php esc_attr_e('Your postcode', 'takeaway-theme'); ?>">
                    <button type="submit" class="tt-btn"><?php esc_html_e('Check', 'takeaway-theme'); ?></button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
<section class="tt-wrap tt-menu-content">
    <?php
    if ($intro !== '') echo '<div class="tt-menu-intro">' . wp_kses_post(wpautop($intro)) . '</div>';
    echo do_shortcode('[takeaway_menu]');
    if (shortcode_exists('takeaway_meal_deals')) {
        echo do_shortcode('[takeaway_meal_deals]');
    }
    if ($footer_text !== '') echo '<div class="tt-menu-help">' . wp_kses_post(wpautop($footer_text)) . '</div>';
    ?>
</section>
<?php else : ?>
<section class="tt-pagehead">
    <div class="tt-wrap">
        <h1><?php woocommerce_page_title(); ?></h1>
    </div>
</section>
<section class="tt-wrap tt-section">
    <?php woocommerce_content(); ?>
</section>
<?php endif;

get_footer();

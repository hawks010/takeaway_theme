<?php
/**
 * Branded My Account dashboard.
 *
 * @version 4.4.0
 */

defined('ABSPATH') || exit;

$user = wp_get_current_user();
$first_name = trim((string) $user->first_name);
$display_name = $first_name !== '' ? $first_name : (string) $user->display_name;
$email = strtolower((string) $user->user_email);

$order_count = function_exists('wc_get_customer_order_count') ? (int) wc_get_customer_order_count($user->ID) : 0;
$total_spent = function_exists('wc_get_customer_total_spent') ? (float) wc_get_customer_total_spent($user->ID) : 0.0;
$recent_orders = function_exists('wc_get_orders') ? wc_get_orders(array(
    'customer_id' => $user->ID,
    'limit'       => 3,
    'orderby'     => 'date',
    'order'       => 'DESC',
    'return'      => 'objects',
)) : array();

$points = class_exists('TTOS_Features') && method_exists('TTOS_Features', 'loyalty_balance') ? (int) TTOS_Features::loyalty_balance($email) : 0;
$stamp_target = class_exists('TTOS_Features') && method_exists('TTOS_Features', 'get') ? max(1, (int) TTOS_Features::get('loyalty', 'stamp_target')) : 5;
$stamps = get_option('ttos_stamp_cards', array());
$stamp_count = is_array($stamps) ? (int) ($stamps[$email]['count'] ?? 0) : 0;
$stamp_progress = min(100, max(0, (int) round(($stamp_count / $stamp_target) * 100)));

$deal_cards = array();
$saved_deals = get_option('ttos_meal_deals', array());
if (is_array($saved_deals)) {
    foreach ($saved_deals as $deal) {
        if (empty($deal['active'])) {
            continue;
        }
        $product_id = absint($deal['product_id'] ?? 0);
        $deal_cards[] = array(
            'name' => (string) ($deal['name'] ?? __('Meal deal', 'takeaway-theme')),
            'text' => (string) ($deal['description'] ?? __('Built for your next order.', 'takeaway-theme')),
            'price' => (string) ($deal['price'] ?? ''),
            'url' => $product_id ? get_permalink($product_id) : tt_menu_url(),
            'image' => $product_id ? get_the_post_thumbnail($product_id, 'medium_large', array('class' => 'tt-account-deal-img')) : '',
        );
        if (count($deal_cards) >= 3) {
            break;
        }
    }
}
if (!$deal_cards) {
    $deal_cards = array(
        array('name' => __('Burger night box', 'takeaway-theme'), 'text' => __('Main, fries and a drink.', 'takeaway-theme'), 'price' => '9.99', 'url' => tt_menu_url(), 'image' => ''),
        array('name' => __('Family feast', 'takeaway-theme'), 'text' => __('Easy sharing for the table.', 'takeaway-theme'), 'price' => '24.00', 'url' => tt_menu_url(), 'image' => ''),
        array('name' => __('Midweek saver', 'takeaway-theme'), 'text' => __('A quick direct-order treat.', 'takeaway-theme'), 'price' => '12.50', 'url' => tt_menu_url(), 'image' => ''),
    );
}

$upsells = function_exists('wc_get_products') ? wc_get_products(array(
    'status' => 'publish',
    'limit'  => 4,
    'orderby' => 'popularity',
    'order' => 'DESC',
    'return' => 'objects',
)) : array();

$allergens = ttheme_account_allergen_options();
$saved_allergens = get_user_meta($user->ID, 'ttheme_allergen_preferences', true);
$saved_allergens = is_array($saved_allergens) ? array_map('sanitize_key', $saved_allergens) : array();
$booking_enabled = ttheme_account_booking_enabled();
$booking = $booking_enabled ? ttheme_account_booking_details() : array();
$is_protected_account = array_intersect(array('administrator', 'shop_manager'), (array) $user->roles);
?>

<section class="tt-account-premium" aria-label="<?php esc_attr_e('Account dashboard', 'takeaway-theme'); ?>">
    <div class="tt-account-hero">
        <div>
            <p class="tt-account-kicker"><?php esc_html_e('Direct account', 'takeaway-theme'); ?></p>
            <h2><?php echo esc_html(sprintf(__('Good to see you, %s', 'takeaway-theme'), $display_name)); ?> <span aria-hidden="true">✨</span></h2>
            <p><?php esc_html_e('Your orders, rewards, food notes and quick actions in one clean place.', 'takeaway-theme'); ?></p>
        </div>
        <a class="tt-account-primary" href="<?php echo esc_url(tt_menu_url()); ?>"><?php esc_html_e('Order your usual', 'takeaway-theme'); ?></a>
    </div>

    <div class="tt-account-deals" aria-label="<?php esc_attr_e('Featured deals', 'takeaway-theme'); ?>">
        <?php foreach ($deal_cards as $index => $deal) : ?>
            <a class="tt-account-deal" href="<?php echo esc_url($deal['url']); ?>">
                <span class="tt-account-deal-art" aria-hidden="true">
                    <?php echo $deal['image'] ? wp_kses_post($deal['image']) : '<span class="tt-account-deal-placeholder"></span>'; ?>
                </span>
                <span class="tt-account-deal-copy">
                    <strong><?php echo esc_html($deal['name']); ?></strong>
                    <small><?php echo esc_html($deal['text']); ?></small>
                </span>
                <?php if ($deal['price'] !== '') : ?>
                    <b><?php echo function_exists('wc_price') ? wp_kses_post(wc_price((float) $deal['price'])) : esc_html($deal['price']); ?></b>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="tt-account-grid">
        <section class="tt-account-panel tt-account-overview">
            <div class="tt-account-panel-head">
                <p class="tt-account-kicker"><?php esc_html_e('Today', 'takeaway-theme'); ?></p>
                <h3><?php esc_html_e('Account snapshot', 'takeaway-theme'); ?></h3>
            </div>
            <div class="tt-account-stats">
                <div><span><?php esc_html_e('Orders', 'takeaway-theme'); ?></span><strong><?php echo esc_html((string) $order_count); ?></strong></div>
                <div><span><?php esc_html_e('Spent direct', 'takeaway-theme'); ?></span><strong><?php echo function_exists('wc_price') ? wp_kses_post(wc_price($total_spent)) : esc_html(number_format($total_spent, 2)); ?></strong></div>
                <div><span><?php esc_html_e('Points', 'takeaway-theme'); ?></span><strong><?php echo esc_html((string) $points); ?></strong></div>
            </div>

            <div class="tt-account-orders">
                <h4><?php esc_html_e('Recent orders', 'takeaway-theme'); ?></h4>
                <?php if ($recent_orders) : ?>
                    <?php foreach ($recent_orders as $order) : ?>
                        <a class="tt-account-order" href="<?php echo esc_url($order->get_view_order_url()); ?>">
                            <span>
                                <strong><?php echo esc_html('#' . $order->get_order_number()); ?></strong>
                                <small><?php echo esc_html(wc_format_datetime($order->get_date_created(), 'd M Y')); ?></small>
                            </span>
                            <mark><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></mark>
                            <b><?php echo wp_kses_post($order->get_formatted_order_total()); ?></b>
                        </a>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="tt-account-empty">
                        <strong><?php esc_html_e('No orders yet', 'takeaway-theme'); ?></strong>
                        <span><?php esc_html_e('When you order direct, your recent meals will appear here.', 'takeaway-theme'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <aside class="tt-account-panel tt-account-rewards">
            <div class="tt-account-panel-head">
                <p class="tt-account-kicker"><?php esc_html_e('Rewards', 'takeaway-theme'); ?></p>
                <h3><span aria-hidden="true">🎁</span> <?php esc_html_e('Direct perks', 'takeaway-theme'); ?></h3>
            </div>
            <div class="tt-account-reward-meter">
                <span style="width: <?php echo esc_attr((string) $stamp_progress); ?>%"></span>
            </div>
            <p><?php echo esc_html(sprintf(__('%1$d of %2$d stamps toward your next reward.', 'takeaway-theme'), $stamp_count, $stamp_target)); ?></p>
            <a href="<?php echo esc_url(function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('takeaway-rewards') : tt_menu_url()); ?>"><?php esc_html_e('View rewards', 'takeaway-theme'); ?></a>
        </aside>

        <section class="tt-account-panel tt-account-upsells">
            <div class="tt-account-panel-head">
                <p class="tt-account-kicker"><?php esc_html_e('Add to next order', 'takeaway-theme'); ?></p>
                <h3><span aria-hidden="true">🔥</span> <?php esc_html_e('Quick picks', 'takeaway-theme'); ?></h3>
            </div>
            <div class="tt-account-upsell-list">
                <?php if ($upsells) : ?>
                    <?php foreach ($upsells as $product) : ?>
                        <a class="tt-account-upsell" href="<?php echo esc_url($product->get_permalink()); ?>">
                            <span><?php echo wp_kses_post($product->get_image('woocommerce_thumbnail')); ?></span>
                            <strong><?php echo esc_html($product->get_name()); ?></strong>
                            <b><?php echo wp_kses_post($product->get_price_html()); ?></b>
                        </a>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="tt-account-empty">
                        <strong><?php esc_html_e('Menu picks coming soon', 'takeaway-theme'); ?></strong>
                        <span><?php esc_html_e('Add menu items to show quick recommendations here.', 'takeaway-theme'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="tt-account-panel tt-account-preferences">
            <div class="tt-account-panel-head">
                <p class="tt-account-kicker"><?php esc_html_e('Food preferences', 'takeaway-theme'); ?></p>
                <h3><span aria-hidden="true">🥗</span> <?php esc_html_e('Allergy notes', 'takeaway-theme'); ?></h3>
            </div>
            <form method="post" class="tt-account-preference-form">
                <?php wp_nonce_field('ttheme_account_preferences', 'ttheme_account_preferences_nonce'); ?>
                <div class="tt-account-allergen-grid">
                    <?php foreach ($allergens as $key => $label) : ?>
                        <label>
                            <input type="checkbox" name="ttheme_allergens[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $saved_allergens, true)); ?>>
                            <span><?php echo esc_html($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p><?php esc_html_e('Saved notes help you reorder carefully. Always tell us about severe allergies before ordering.', 'takeaway-theme'); ?></p>
                <button type="submit" class="tt-account-secondary"><?php esc_html_e('Save preferences', 'takeaway-theme'); ?></button>
            </form>
        </section>

        <?php if ($booking_enabled) : ?>
            <section class="tt-account-panel tt-account-bookings">
                <div class="tt-account-panel-head">
                    <p class="tt-account-kicker"><?php esc_html_e('Tables', 'takeaway-theme'); ?></p>
                    <h3><span aria-hidden="true">📍</span> <?php esc_html_e('Bookings', 'takeaway-theme'); ?></h3>
                </div>
                <div class="tt-account-empty">
                    <strong><?php esc_html_e('No saved table bookings yet', 'takeaway-theme'); ?></strong>
                    <span><?php echo esc_html($booking['note']); ?></span>
                </div>
                <a class="tt-account-secondary" href="<?php echo esc_url($booking['url']); ?>"><?php echo esc_html($booking['label']); ?></a>
            </section>
        <?php endif; ?>

        <section class="tt-account-panel tt-account-danger">
            <div class="tt-account-panel-head">
                <p class="tt-account-kicker"><?php esc_html_e('Privacy', 'takeaway-theme'); ?></p>
                <h3><?php esc_html_e('Account controls', 'takeaway-theme'); ?></h3>
            </div>
            <?php if ($is_protected_account) : ?>
                <p><?php esc_html_e('This owner or manager account is protected from self-deletion.', 'takeaway-theme'); ?></p>
            <?php else : ?>
                <form method="post" class="tt-account-delete-form">
                    <?php wp_nonce_field('ttheme_account_delete', 'ttheme_account_delete_nonce'); ?>
                    <label for="ttheme-delete-email"><?php esc_html_e('Type your email to delete this account', 'takeaway-theme'); ?></label>
                    <input id="ttheme-delete-email" name="ttheme_delete_email" type="email" autocomplete="email" placeholder="<?php echo esc_attr($email); ?>">
                    <button type="submit"><?php esc_html_e('Delete account', 'takeaway-theme'); ?></button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</section>

<?php
do_action('woocommerce_account_dashboard');

<?php

defined('ABSPATH') || exit;

/**
 * Production feature layer for Takeaway OS.
 *
 * This class keeps the paid/optional modules out of the core setup wizard while
 * still making each feature testable in a normal WordPress/WooCommerce install.
 */
final class TTOS_Features {
    public static function hooks(): void {
        add_action('admin_menu', array(__CLASS__, 'menu'), 20);
        add_action('admin_init', array(__CLASS__, 'handle_posts'));
        add_action('admin_post_ttos_export_orders_csv', array(__CLASS__, 'export_orders_csv'));
        add_action('admin_post_ttos_export_customers_csv', array(__CLASS__, 'export_customers_csv'));
        add_filter('cron_schedules', array(__CLASS__, 'cron_schedules'));
        add_action('ttos_retry_integrations', array(__CLASS__, 'retry_queue_handler'));
        if (!wp_next_scheduled('ttos_retry_integrations')) {
            wp_schedule_event(time() + 300, 'ttos_every_five_minutes', 'ttos_retry_integrations');
        }

        add_shortcode('takeaway_meal_deals', array(__CLASS__, 'shortcode_meal_deals'));
        add_shortcode('takeaway_home_blocks', array(__CLASS__, 'shortcode_home_blocks'));
        add_shortcode('takeaway_rewards', array(__CLASS__, 'shortcode_rewards'));

        add_filter('woocommerce_add_to_cart_validation', array(__CLASS__, 'validate_meal_deal_add_to_cart'), 20, 3);
        add_filter('woocommerce_add_cart_item_data', array(__CLASS__, 'add_meal_deal_cart_item_data'), 20, 3);
        add_filter('woocommerce_get_item_data', array(__CLASS__, 'display_meal_deal_cart_item_data'), 20, 2);
        add_action('woocommerce_checkout_create_order_line_item', array(__CLASS__, 'save_meal_deal_order_item_meta'), 20, 4);

        add_action('woocommerce_new_order', array(__CLASS__, 'order_created_integrations'), 20, 2);
        add_action('woocommerce_after_order_status_changed', array(__CLASS__, 'order_status_integrations'), 20, 4);
        add_action('woocommerce_order_status_completed', array(__CLASS__, 'award_rewards'), 20, 1);
        add_action('woocommerce_cart_calculate_fees', array(__CLASS__, 'apply_advanced_zone_fee'), 30);
        add_action('woocommerce_checkout_process', array(__CLASS__, 'validate_advanced_zone_checkout'));

        add_action('init', array(__CLASS__, 'account_endpoint'));
        add_action('woocommerce_account_takeaway-rewards_endpoint', array(__CLASS__, 'account_rewards_endpoint'));
    }

    public static function menu(): void {
        add_submenu_page('takeaway-os', 'Feature Builder', 'Feature Builder', 'ttos_modules', 'takeaway-os-features', array(__CLASS__, 'page_features'));
    }

    public static function defaults(): array {
        return array(
            'loyalty' => array(
                'points_per_pound' => '1',
                'redeem_rate_pence' => '1',
                'stamp_target' => '5',
                'reward_coupon_amount' => '5.00',
            ),
            'sms' => array(
                'provider' => 'twilio',
                'twilio_sid' => '',
                'twilio_token' => '',
                'twilio_from' => '',
                'test_phone' => '',
                'send_on_accepted' => '1',
                'send_on_ready' => '1',
                'send_on_out' => '1',
                'send_on_cancelled' => '0',
                'accepted_template' => 'Your order #{order_id} has been accepted. Estimated total: {total}.',
                'ready_template' => 'Your order #{order_id} is ready. Thank you for ordering direct.',
                'out_template' => 'Your order #{order_id} is out for delivery.',
                'cancelled_template' => 'Sorry, order #{order_id} could not be accepted. Please contact us for help.',
            ),
            'printer' => array(
                'profile' => 'generic_webhook',
                'endpoint' => '',
                'api_key' => '',
                'send_on_new_order' => '1',
                'send_on_status_change' => '0',
                'timeout' => '15',
                'max_retries' => '3',
                'retry_delay' => '5',
                'ticket_format' => 'kitchen_ticket',
            ),
            'epos' => array(
                'profile' => 'generic_webhook',
                'endpoint' => '',
                'api_key' => '',
                'send_on_new_order' => '1',
                'send_on_status_change' => '1',
                'timeout' => '15',
                'max_retries' => '3',
                'retry_delay' => '5',
                'status_map' => "processing=processing\nttos-accepted=accepted\nttos-prepping=preparing\nttos-ready=ready\nttos-out=out_for_delivery\ncompleted=completed\ncancelled=cancelled",
            ),
            'accounting' => array(
                'vat_rate' => '20',
                'daily_email' => get_option('admin_email'),
            ),
            'advanced_zones' => array(
                'rules' => "Local|MK18|1.50|12.00|30.00\nNearby|MK17,OX27|3.00|18.00|45.00",
            ),
            'content' => array(
                'hero_eyebrow' => 'Order direct',
                'hero_title' => 'Fresh food, straight from us.',
                'hero_text' => 'Skip the middleman. Order collection or delivery direct from the restaurant.',
                'offer_title' => 'Free delivery on your first order',
                'offer_text' => 'Available on selected postcodes and order values.',
                'cta_label' => 'Order now',
                'cta_url' => '/menu/',
            ),
        );
    }

    public static function get(string $section = '', $key = null) {
        $settings = wp_parse_args(get_option('ttos_feature_settings', array()), self::defaults());
        foreach (self::defaults() as $default_key => $default_value) {
            $settings[$default_key] = wp_parse_args($settings[$default_key] ?? array(), $default_value);
        }
        if ($section === '') return $settings;
        if ($key === null) return $settings[$section] ?? array();
        return $settings[$section][$key] ?? null;
    }

    public static function update_section(string $section, array $values): void {
        $settings = self::get();
        $settings[$section] = wp_parse_args($values, self::defaults()[$section] ?? array());
        update_option('ttos_feature_settings', $settings, false);
    }

    public static function handle_posts(): void {
        if (!is_admin() || empty($_POST['ttos_action'])) return;
        if (!current_user_can('ttos_modules')) return;
        $action = sanitize_key($_POST['ttos_action']);
        if (strpos($action, 'feature_') !== 0) return;
        check_admin_referer('ttos_' . $action);

        if ($action === 'feature_save_settings') {
            $raw = wp_unslash($_POST['feature_settings'] ?? array());
            foreach (array('loyalty','sms','printer','epos','accounting','advanced_zones','content') as $section) {
                $clean = self::sanitize_feature_section($section, isset($raw[$section]) && is_array($raw[$section]) ? $raw[$section] : array());
                self::update_section($section, $clean);
            }
            self::redirect('feature-settings-saved');
        }

        if ($action === 'feature_save_deal') {
            self::save_meal_deal(wp_unslash($_POST['deal'] ?? array()));
            self::redirect('deal-saved', 'meal-deals');
        }

        if ($action === 'feature_delete_deal') {
            $deal_id = sanitize_key(wp_unslash($_POST['deal_id'] ?? ''));
            self::delete_meal_deal($deal_id);
            self::redirect('deal-deleted', 'meal-deals');
        }

        if ($action === 'feature_save_inventory') {
            self::save_inventory_rows(wp_unslash($_POST['inventory'] ?? array()));
            self::redirect('inventory-saved', 'inventory');
        }

        if ($action === 'feature_send_test_hooks') {
            self::send_test_hooks();
            self::redirect('test-hooks-sent', 'integrations');
        }

        if ($action === 'feature_send_test_sms') {
            self::send_test_sms();
            self::redirect('test-sms-sent', 'integrations');
        }

        if ($action === 'feature_retry_integrations') {
            self::process_retry_queue(true);
            self::redirect('integration-queue-retried', 'integrations');
        }

        if ($action === 'feature_clear_integration_log') {
            update_option('ttos_integration_log', array(), false);
            update_option('ttos_integration_queue', array(), false);
            self::redirect('integration-log-cleared', 'integrations');
        }
    }

    private static function sanitize_feature_section(string $section, array $raw): array {
        switch ($section) {
            case 'loyalty':
                return array(
                    'points_per_pound' => self::decimal($raw['points_per_pound'] ?? 1),
                    'redeem_rate_pence' => self::decimal($raw['redeem_rate_pence'] ?? 1),
                    'stamp_target' => (string) max(1, absint($raw['stamp_target'] ?? 5)),
                    'reward_coupon_amount' => self::decimal($raw['reward_coupon_amount'] ?? '5.00'),
                );
            case 'sms':
                return array(
                    'provider' => sanitize_key($raw['provider'] ?? 'twilio'),
                    'twilio_sid' => sanitize_text_field($raw['twilio_sid'] ?? ''),
                    'twilio_token' => sanitize_text_field($raw['twilio_token'] ?? ''),
                    'twilio_from' => sanitize_text_field($raw['twilio_from'] ?? ''),
                    'test_phone' => sanitize_text_field($raw['test_phone'] ?? ''),
                    'send_on_accepted' => !empty($raw['send_on_accepted']) ? '1' : '0',
                    'send_on_ready' => !empty($raw['send_on_ready']) ? '1' : '0',
                    'send_on_out' => !empty($raw['send_on_out']) ? '1' : '0',
                    'send_on_cancelled' => !empty($raw['send_on_cancelled']) ? '1' : '0',
                    'accepted_template' => sanitize_textarea_field($raw['accepted_template'] ?? ''),
                    'ready_template' => sanitize_textarea_field($raw['ready_template'] ?? ''),
                    'out_template' => sanitize_textarea_field($raw['out_template'] ?? ''),
                    'cancelled_template' => sanitize_textarea_field($raw['cancelled_template'] ?? ''),
                );
            case 'printer':
                return array(
                    'profile' => sanitize_key($raw['profile'] ?? 'generic_webhook'),
                    'endpoint' => esc_url_raw($raw['endpoint'] ?? ''),
                    'api_key' => sanitize_text_field($raw['api_key'] ?? ''),
                    'send_on_new_order' => !empty($raw['send_on_new_order']) ? '1' : '0',
                    'send_on_status_change' => !empty($raw['send_on_status_change']) ? '1' : '0',
                    'timeout' => (string) min(60, max(5, absint($raw['timeout'] ?? 15))),
                    'max_retries' => (string) min(10, max(0, absint($raw['max_retries'] ?? 3))),
                    'retry_delay' => (string) min(120, max(1, absint($raw['retry_delay'] ?? 5))),
                    'ticket_format' => sanitize_key($raw['ticket_format'] ?? 'kitchen_ticket'),
                );
            case 'epos':
                return array(
                    'profile' => sanitize_key($raw['profile'] ?? 'generic_webhook'),
                    'endpoint' => esc_url_raw($raw['endpoint'] ?? ''),
                    'api_key' => sanitize_text_field($raw['api_key'] ?? ''),
                    'send_on_new_order' => !empty($raw['send_on_new_order']) ? '1' : '0',
                    'send_on_status_change' => !empty($raw['send_on_status_change']) ? '1' : '0',
                    'timeout' => (string) min(60, max(5, absint($raw['timeout'] ?? 15))),
                    'max_retries' => (string) min(10, max(0, absint($raw['max_retries'] ?? 3))),
                    'retry_delay' => (string) min(120, max(1, absint($raw['retry_delay'] ?? 5))),
                    'status_map' => sanitize_textarea_field($raw['status_map'] ?? ''),
                );
            case 'accounting':
                return array(
                    'vat_rate' => self::decimal($raw['vat_rate'] ?? 20),
                    'daily_email' => sanitize_email($raw['daily_email'] ?? get_option('admin_email')),
                );
            case 'advanced_zones':
                return array('rules' => sanitize_textarea_field($raw['rules'] ?? ''));
            case 'content':
                return array(
                    'hero_eyebrow' => sanitize_text_field($raw['hero_eyebrow'] ?? ''),
                    'hero_title' => sanitize_text_field($raw['hero_title'] ?? ''),
                    'hero_text' => sanitize_textarea_field($raw['hero_text'] ?? ''),
                    'offer_title' => sanitize_text_field($raw['offer_title'] ?? ''),
                    'offer_text' => sanitize_textarea_field($raw['offer_text'] ?? ''),
                    'cta_label' => sanitize_text_field($raw['cta_label'] ?? ''),
                    'cta_url' => esc_url_raw($raw['cta_url'] ?? ''),
                );
        }
        return array_map('sanitize_text_field', $raw);
    }

    private static function redirect(string $notice, string $anchor = ''): void {
        $url = add_query_arg(array('page' => 'takeaway-os-features', 'ttos_notice' => $notice), admin_url('admin.php'));
        if ($anchor) $url .= '#' . sanitize_key($anchor);
        wp_safe_redirect($url);
        exit;
    }

    public static function page_features(): void {
        self::shell_start(
            'Feature builder',
            'Build, configure and test every paid Takeaway OS module before Codex gets the black box recorder.',
            self::secondary_nav_items()
        );
        $modules = TTOS_Settings::modules();
        self::feature_matrix($modules);
        self::meal_deals_panel();
        self::loyalty_panel();
        self::inventory_panel();
        self::delivery_zones_panel();
        self::analytics_panel();
        self::crm_panel();
        self::content_panel();
        self::integrations_panel();
        self::accounting_panel();
        self::shell_end();
    }

    private static function shell_start(string $title, string $subtitle = '', array $secondary_nav = array()): void {
        TTOS_Admin_Shell::render_start(array(
            'title' => $title,
            'subtitle' => $subtitle,
            'active' => 'takeaway-os-features',
            'secondary_nav' => $secondary_nav,
            'secondary_nav_label' => 'Sections',
            'secondary_nav_aria_label' => 'Feature Builder sections',
        ));
        if (!empty($_GET['ttos_notice'])) echo '<div class="ttos-notice">Saved. Feature systems updated.</div>';
    }

    private static function shell_end(): void { TTOS_Admin_Shell::render_end(); }

    private static function secondary_nav_items(): array {
        return array(
            array('label' => 'Matrix', 'url' => '#matrix', 'active' => false),
            array('label' => 'Meal deals', 'url' => '#meal-deals', 'active' => false),
            array('label' => 'Loyalty', 'url' => '#loyalty', 'active' => false),
            array('label' => 'Inventory', 'url' => '#inventory', 'active' => false),
            array('label' => 'Delivery zones', 'url' => '#delivery-zones', 'active' => false),
            array('label' => 'Analytics', 'url' => '#analytics-pro', 'active' => false),
            array('label' => 'CRM Pro', 'url' => '#crm-pro', 'active' => false),
            array('label' => 'Content', 'url' => '#content-manager', 'active' => false),
            array('label' => 'Integrations', 'url' => '#integrations', 'active' => false),
            array('label' => 'Accounting', 'url' => '#accounting', 'active' => false),
        );
    }

    private static function module_badge(string $slug): string {
        return TTOS_Settings::module_enabled($slug) ? '<span class="ttos-good">Enabled</span>' : '<span class="ttos-warn">Locked/off</span>';
    }

    private static function feature_matrix(array $modules): void {
        $items = class_exists('TTOS_Admin') && method_exists('TTOS_Admin', 'module_catalog') ? TTOS_Admin::module_catalog() : array();
        echo '<section id="matrix" class="ttos-card"><h2>Feature matrix</h2><p class="ttos-muted">These are the planned modules turned into testable surfaces. Anything unfinished is marked honestly here and kept out of client roles by default.</p><div class="ttos-feature-matrix">';
        foreach ($items as $slug => $item) {
            echo '<div><strong>' . esc_html($item['name']) . '</strong><p>' . esc_html($item['description']) . '</p><p class="ttos-muted">' . esc_html($item['note']) . '</p><p><span class="ttos-mini">' . esc_html($item['status']) . '</span> ' . self::module_badge($slug) . '</p></div>';
        }
        echo '</div></section>';
    }

    private static function meal_deals_panel(): void {
        $deals = self::meal_deals();
        $cats = TTOS_WooCommerce::active() ? TTOS_WooCommerce::get_product_categories() : array();
        echo '<section id="meal-deals" class="ttos-card"><h2>Meal deal builder ' . self::module_badge('meal_deals') . '</h2><p class="ttos-muted">Creates a fixed-price WooCommerce product for the deal, then stores customer component choices as cart/order meta. No fake bundle plugin needed for v1.</p>';
        echo '<form method="post" class="ttos-grid ttos-grid-2">';
        wp_nonce_field('ttos_feature_save_deal');
        echo '<input type="hidden" name="ttos_action" value="feature_save_deal">';
        self::field('Deal name', 'deal[name]', 'Burger Meal Deal');
        self::field('Fixed price (£)', 'deal[price]', '9.99', 'number');
        self::field('Short description', 'deal[description]', 'Choose a main, side and drink.');
        self::media_field('Deal image', 'deal[image_id]', 0, 'Choose deal image');
        echo '<label>Main categories<select name="deal[main_cats][]" multiple size="5">'; foreach ($cats as $cat) echo '<option value="' . esc_attr($cat->term_id) . '">' . esc_html($cat->name) . '</option>'; echo '</select></label>';
        echo '<label>Side categories<select name="deal[side_cats][]" multiple size="5">'; foreach ($cats as $cat) echo '<option value="' . esc_attr($cat->term_id) . '">' . esc_html($cat->name) . '</option>'; echo '</select></label>';
        echo '<label>Drink categories<select name="deal[drink_cats][]" multiple size="5">'; foreach ($cats as $cat) echo '<option value="' . esc_attr($cat->term_id) . '">' . esc_html($cat->name) . '</option>'; echo '</select></label>';
        echo '<label class="ttos-check"><input type="checkbox" name="deal[active]" value="1" checked> Active</label><div><button class="ttos-button">Create meal deal</button></div></form>';
        echo '<h3>Current deals</h3><table class="ttos-table"><thead><tr><th>Deal</th><th>Price</th><th>Woo product</th><th>Status</th><th></th></tr></thead><tbody>';
        foreach ($deals as $deal) {
            echo '<tr><td><strong>' . esc_html($deal['name']) . '</strong><br><small>' . esc_html($deal['description']) . '</small></td><td>' . esc_html(self::money($deal['price'])) . '</td><td>' . (!empty($deal['product_id']) ? '<a href="' . esc_url(get_edit_post_link((int) $deal['product_id'])) . '">#' . esc_html((string) $deal['product_id']) . '</a>' : '—') . '</td><td>' . (!empty($deal['active']) ? '<span class="ttos-good">Active</span>' : '<span class="ttos-warn">Hidden</span>') . '</td><td><form method="post">';
            wp_nonce_field('ttos_feature_delete_deal');
            echo '<input type="hidden" name="ttos_action" value="feature_delete_deal"><input type="hidden" name="deal_id" value="' . esc_attr($deal['id']) . '"><button class="ttos-mini">Delete</button></form></td></tr>';
        }
        if (!$deals) echo '<tr><td colspan="5"><span class="ttos-muted">No meal deals yet.</span></td></tr>';
        echo '</tbody></table><p><code>[takeaway_meal_deals]</code></p></section>';
    }

    private static function loyalty_panel(): void {
        $s = self::get('loyalty');
        echo '<section id="loyalty" class="ttos-card"><h2>Loyalty and stamp cards ' . self::module_badge('loyalty') . ' ' . self::module_badge('stamp_cards') . '</h2><form method="post">';
        wp_nonce_field('ttos_feature_save_settings');
        echo '<input type="hidden" name="ttos_action" value="feature_save_settings">';
        self::settings_hidden_except('loyalty');
        echo '<div class="ttos-grid ttos-grid-4">';
        self::field('Points per £1', 'feature_settings[loyalty][points_per_pound]', $s['points_per_pound'], 'number');
        self::field('Pence value per point', 'feature_settings[loyalty][redeem_rate_pence]', $s['redeem_rate_pence'], 'number');
        self::field('Stamps needed', 'feature_settings[loyalty][stamp_target]', $s['stamp_target'], 'number');
        self::field('Reward coupon amount (£)', 'feature_settings[loyalty][reward_coupon_amount]', $s['reward_coupon_amount'], 'number');
        echo '</div><button class="ttos-button">Save loyalty settings</button></form><p><code>[takeaway_rewards]</code> shows the customer balance.</p></section>';
    }

    private static function inventory_panel(): void {
        echo '<section id="inventory" class="ttos-card"><h2>Inventory Lite ' . self::module_badge('inventory_lite') . '</h2><p class="ttos-muted">This is availability and limited-count stock, not ingredient-level EPOS stock. Perfect for “wings sold out” and “12 cheesecakes left”.</p>';
        if (!TTOS_WooCommerce::active()) { echo '<p>WooCommerce required.</p></section>'; return; }
        $q = new WP_Query(array('post_type' => 'product', 'posts_per_page' => 60, 'post_status' => array('publish','draft'), 'orderby' => 'title', 'order' => 'ASC'));
        echo '<form method="post"><table class="ttos-table"><thead><tr><th>Item</th><th>Available</th><th>Limited qty</th><th>Hidden</th></tr></thead><tbody>';
        while ($q->have_posts()) { $q->the_post(); $id = get_the_ID();
            $stock = get_post_meta($id, '_stock_status', true); $manage = get_post_meta($id, '_manage_stock', true); $qty = $manage === 'yes' ? get_post_meta($id, '_stock', true) : '';
            echo '<tr><td><strong>' . esc_html(get_the_title()) . '</strong><input type="hidden" name="inventory[' . esc_attr((string) $id) . '][id]" value="' . esc_attr((string) $id) . '"></td><td><input type="checkbox" name="inventory[' . esc_attr((string) $id) . '][available]" value="1" ' . checked($stock !== 'outofstock', true, false) . '></td><td><input type="number" name="inventory[' . esc_attr((string) $id) . '][qty]" value="' . esc_attr((string) $qty) . '" placeholder="blank = unlimited"></td><td><input type="checkbox" name="inventory[' . esc_attr((string) $id) . '][hidden]" value="1" ' . checked(get_post_status($id) === 'draft', true, false) . '></td></tr>';
        }
        wp_reset_postdata();
        echo '</tbody></table>';
        wp_nonce_field('ttos_feature_save_inventory');
        echo '<input type="hidden" name="ttos_action" value="feature_save_inventory"><button class="ttos-button">Save inventory changes</button></form></section>';
    }

    private static function delivery_zones_panel(): void {
        $s = self::get('advanced_zones');
        echo '<section id="delivery-zones" class="ttos-card"><h2>Advanced delivery zones ' . self::module_badge('advanced_zones') . '</h2><p class="ttos-muted">One rule per line: <code>Name|postcode prefixes|fee|min order|free over</code>. Example: <code>Local|MK18|1.50|12.00|30.00</code></p><form method="post">';
        wp_nonce_field('ttos_feature_save_settings');
        echo '<input type="hidden" name="ttos_action" value="feature_save_settings">';
        self::settings_hidden_except('advanced_zones');
        echo '<label>Zone rules<textarea name="feature_settings[advanced_zones][rules]" rows="7">' . esc_textarea($s['rules']) . '</textarea></label><button class="ttos-button">Save zone rules</button></form></section>';
    }

    private static function analytics_panel(): void {
        $stats = self::analytics_snapshot();
        echo '<section id="analytics-pro" class="ttos-card"><h2>Analytics Pro ' . self::module_badge('analytics_pro') . '</h2><div class="ttos-grid ttos-grid-4">';
        self::metric('30-day orders', $stats['orders']); self::metric('30-day revenue', self::money($stats['revenue'])); self::metric('Average order', self::money($stats['aov'])); self::metric('Busiest hour', $stats['busy_hour']);
        echo '</div><div class="ttos-grid ttos-grid-2"><div><h3>Top items</h3><ol class="ttos-list">';
        foreach ($stats['top_items'] as $name => $qty) echo '<li><span>' . esc_html($name) . '</span><strong>' . esc_html((string) $qty) . '</strong></li>';
        if (!$stats['top_items']) echo '<li>No order data yet.</li>';
        echo '</ol></div><div><h3>Order statuses</h3><ol class="ttos-list">';
        foreach ($stats['statuses'] as $name => $qty) echo '<li><span>' . esc_html($name) . '</span><strong>' . esc_html((string) $qty) . '</strong></li>';
        echo '</ol></div></div></section>';
    }

    private static function crm_panel(): void {
        $customers = self::customer_snapshot();
        echo '<section id="crm-pro" class="ttos-card"><h2>Customer CRM Pro ' . self::module_badge('crm_pro') . '</h2><p class="ttos-muted">Top spenders, last-order dates and dormant customers from WooCommerce order history.</p><p><a class="ttos-button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=ttos_export_customers_csv'), 'ttos_export_customers_csv')) . '">Export customers CSV</a></p><table class="ttos-table"><thead><tr><th>Customer</th><th>Email</th><th>Orders</th><th>LTV</th><th>Last order</th><th>Status</th></tr></thead><tbody>';
        foreach ($customers as $email => $c) {
            echo '<tr><td>' . esc_html($c['name']) . '</td><td>' . esc_html($email) . '</td><td>' . esc_html((string) $c['orders']) . '</td><td>' . esc_html(self::money($c['total'])) . '</td><td>' . esc_html($c['last']) . '</td><td>' . ($c['dormant'] ? '<span class="ttos-warn">Dormant</span>' : '<span class="ttos-good">Active</span>') . '</td></tr>';
        }
        if (!$customers) echo '<tr><td colspan="6">No customers yet.</td></tr>';
        echo '</tbody></table></section>';
    }

    private static function content_panel(): void {
        $s = self::get('content');
        echo '<section id="content-manager" class="ttos-card"><h2>Owner-safe content manager ' . self::module_badge('content_manager') . '</h2><p class="ttos-muted">Use <code>[takeaway_home_blocks]</code> in the theme/homepage so owners can safely update offer text without touching Elementor or WordPress internals.</p><form method="post">';
        wp_nonce_field('ttos_feature_save_settings');
        echo '<input type="hidden" name="ttos_action" value="feature_save_settings">';
        self::settings_hidden_except('content');
        echo '<div class="ttos-grid ttos-grid-2">';
        self::field('Hero eyebrow', 'feature_settings[content][hero_eyebrow]', $s['hero_eyebrow']); self::field('Hero title', 'feature_settings[content][hero_title]', $s['hero_title']);
        echo '</div><label>Hero text<textarea name="feature_settings[content][hero_text]" rows="3">' . esc_textarea($s['hero_text']) . '</textarea></label><div class="ttos-grid ttos-grid-2">';
        self::field('Offer title', 'feature_settings[content][offer_title]', $s['offer_title']); self::field('CTA label', 'feature_settings[content][cta_label]', $s['cta_label']);
        echo '</div><label>Offer text<textarea name="feature_settings[content][offer_text]" rows="3">' . esc_textarea($s['offer_text']) . '</textarea></label>'; self::field('CTA URL', 'feature_settings[content][cta_url]', $s['cta_url'], 'url');
        echo '<button class="ttos-button">Save content blocks</button></form></section>';
    }

    private static function integrations_panel(): void {
        $sms = self::get('sms');
        $printer = self::get('printer');
        $epos = self::get('epos');
        $queue = self::integration_queue();
        $logs = get_option('ttos_integration_log', array()); if (!is_array($logs)) $logs = array();

        echo '<section id="integrations" class="ttos-card ttos-integration-suite"><h2>Integrations ' . self::module_badge('sms_updates') . ' ' . self::module_badge('printer') . ' ' . self::module_badge('epos_connector') . '</h2>';
        echo '<p class="ttos-muted">Printer, SMS and EPOS now use test buttons, connector profiles, detailed logs and a retry queue for failed webhook sends.</p>';
        echo '<div class="ttos-grid ttos-grid-4">';
        self::metric('Printer', self::integration_health_label('printer', $printer));
        self::metric('EPOS', self::integration_health_label('epos', $epos));
        self::metric('SMS', self::sms_health_label($sms));
        self::metric('Retry queue', count($queue) . ' waiting');
        echo '</div>';

        echo '<form method="post">';
        wp_nonce_field('ttos_feature_save_settings');
        echo '<input type="hidden" name="ttos_action" value="feature_save_settings">';
        self::settings_hidden_except('sms,printer,epos');

        echo '<div class="ttos-grid ttos-grid-3 ttos-integrations-grid">';

        echo '<div class="ttos-integration-card"><h3>SMS updates</h3><p class="ttos-muted">Twilio-powered customer status texts. Leave credentials empty during staging to log “would send” messages.</p>';
        echo '<div class="ttos-grid ttos-grid-1">';
        self::field('Twilio SID', 'feature_settings[sms][twilio_sid]', $sms['twilio_sid']);
        self::field('Twilio token', 'feature_settings[sms][twilio_token]', $sms['twilio_token'], 'password');
        self::field('Twilio from number', 'feature_settings[sms][twilio_from]', $sms['twilio_from']);
        self::field('Test phone number', 'feature_settings[sms][test_phone]', $sms['test_phone']);
        echo '</div><div class="ttos-check-grid">';
        echo '<label><input type="checkbox" name="feature_settings[sms][send_on_accepted]" value="1" ' . checked($sms['send_on_accepted'], '1', false) . '> Accepted</label>';
        echo '<label><input type="checkbox" name="feature_settings[sms][send_on_ready]" value="1" ' . checked($sms['send_on_ready'], '1', false) . '> Ready</label>';
        echo '<label><input type="checkbox" name="feature_settings[sms][send_on_out]" value="1" ' . checked($sms['send_on_out'], '1', false) . '> Out for delivery</label>';
        echo '<label><input type="checkbox" name="feature_settings[sms][send_on_cancelled]" value="1" ' . checked($sms['send_on_cancelled'], '1', false) . '> Cancelled</label>';
        echo '</div>';
        echo '<label>Accepted template<textarea name="feature_settings[sms][accepted_template]" rows="2">' . esc_textarea($sms['accepted_template']) . '</textarea></label>';
        echo '<label>Ready template<textarea name="feature_settings[sms][ready_template]" rows="2">' . esc_textarea($sms['ready_template']) . '</textarea></label>';
        echo '<label>Out for delivery template<textarea name="feature_settings[sms][out_template]" rows="2">' . esc_textarea($sms['out_template']) . '</textarea></label>';
        echo '<label>Cancelled template<textarea name="feature_settings[sms][cancelled_template]" rows="2">' . esc_textarea($sms['cancelled_template']) . '</textarea></label></div>';

        echo '<div class="ttos-integration-card"><h3>Printer connector</h3><p class="ttos-muted">Use a generic relay, PrintNode bridge or ESC/POS middleware endpoint. Failed sends are retried automatically.</p>';
        echo '<label>Connector profile<select name="feature_settings[printer][profile]">';
        foreach (self::printer_profiles() as $key => $label) echo '<option value="' . esc_attr($key) . '" ' . selected($printer['profile'], $key, false) . '>' . esc_html($label) . '</option>';
        echo '</select></label>';
        self::field('Printer/webhook endpoint', 'feature_settings[printer][endpoint]', $printer['endpoint'], 'url');
        self::field('Printer API key', 'feature_settings[printer][api_key]', $printer['api_key'], 'password');
        echo '<label>Ticket format<select name="feature_settings[printer][ticket_format]"><option value="kitchen_ticket" ' . selected($printer['ticket_format'], 'kitchen_ticket', false) . '>Kitchen ticket</option><option value="compact" ' . selected($printer['ticket_format'], 'compact', false) . '>Compact</option><option value="json_only" ' . selected($printer['ticket_format'], 'json_only', false) . '>JSON only</option></select></label>';
        echo '<div class="ttos-grid ttos-grid-3">';
        self::field('Timeout sec', 'feature_settings[printer][timeout]', $printer['timeout'], 'number');
        self::field('Max retries', 'feature_settings[printer][max_retries]', $printer['max_retries'], 'number');
        self::field('Retry delay min', 'feature_settings[printer][retry_delay]', $printer['retry_delay'], 'number');
        echo '</div><label class="ttos-check"><input type="checkbox" name="feature_settings[printer][send_on_new_order]" value="1" ' . checked($printer['send_on_new_order'], '1', false) . '> Send new orders to printer relay</label>';
        echo '<label class="ttos-check"><input type="checkbox" name="feature_settings[printer][send_on_status_change]" value="1" ' . checked($printer['send_on_status_change'], '1', false) . '> Send status changes to printer relay</label></div>';

        echo '<div class="ttos-integration-card"><h3>EPOS connector</h3><p class="ttos-muted">Generic JSON webhook now includes connector profiles and status mapping for EPOS middleware.</p>';
        echo '<label>Connector profile<select name="feature_settings[epos][profile]">';
        foreach (self::epos_profiles() as $key => $label) echo '<option value="' . esc_attr($key) . '" ' . selected($epos['profile'], $key, false) . '>' . esc_html($label) . '</option>';
        echo '</select></label>';
        self::field('EPOS webhook endpoint', 'feature_settings[epos][endpoint]', $epos['endpoint'], 'url');
        self::field('EPOS API key', 'feature_settings[epos][api_key]', $epos['api_key'], 'password');
        echo '<div class="ttos-grid ttos-grid-3">';
        self::field('Timeout sec', 'feature_settings[epos][timeout]', $epos['timeout'], 'number');
        self::field('Max retries', 'feature_settings[epos][max_retries]', $epos['max_retries'], 'number');
        self::field('Retry delay min', 'feature_settings[epos][retry_delay]', $epos['retry_delay'], 'number');
        echo '</div><label class="ttos-check"><input type="checkbox" name="feature_settings[epos][send_on_new_order]" value="1" ' . checked($epos['send_on_new_order'], '1', false) . '> Send new orders to EPOS</label>';
        echo '<label class="ttos-check"><input type="checkbox" name="feature_settings[epos][send_on_status_change]" value="1" ' . checked($epos['send_on_status_change'], '1', false) . '> Send status changes to EPOS</label>';
        echo '<label>Status map<textarea name="feature_settings[epos][status_map]" rows="7">' . esc_textarea($epos['status_map']) . '</textarea></label></div>';

        echo '</div><button class="ttos-button">Save integration settings</button></form>';

        echo '<div class="ttos-integration-actions">';
        echo '<form method="post">'; wp_nonce_field('ttos_feature_send_test_hooks'); echo '<input type="hidden" name="ttos_action" value="feature_send_test_hooks"><button class="ttos-button ttos-button-dark">Send printer/EPOS test payloads</button></form>';
        echo '<form method="post">'; wp_nonce_field('ttos_feature_send_test_sms'); echo '<input type="hidden" name="ttos_action" value="feature_send_test_sms"><button class="ttos-button ttos-button-dark">Send/log test SMS</button></form>';
        echo '<form method="post">'; wp_nonce_field('ttos_feature_retry_integrations'); echo '<input type="hidden" name="ttos_action" value="feature_retry_integrations"><button class="ttos-button">Retry failed queue now</button></form>';
        if (current_user_can('manage_options')) { echo '<form method="post">'; wp_nonce_field('ttos_feature_clear_integration_log'); echo '<input type="hidden" name="ttos_action" value="feature_clear_integration_log"><button class="ttos-button ttos-button-dark">Clear integration logs</button></form>'; }
        echo '</div>';

        echo '<div class="ttos-grid ttos-grid-2"><div><h3>Retry queue</h3>';
        self::integration_queue_table($queue);
        echo '</div><div><h3>Latest integration logs</h3>';
        self::integration_log_table(array_reverse(array_slice($logs, -15)));
        echo '</div></div></section>';
    }

    private static function accounting_panel(): void {
        $s = self::get('accounting');
        echo '<section id="accounting" class="ttos-card"><h2>Accounting export ' . self::module_badge('accounting') . '</h2><form method="post">'; wp_nonce_field('ttos_feature_save_settings'); echo '<input type="hidden" name="ttos_action" value="feature_save_settings">'; self::settings_hidden_except('accounting');
        echo '<div class="ttos-grid ttos-grid-2">'; self::field('VAT estimate rate (%)', 'feature_settings[accounting][vat_rate]', $s['vat_rate'], 'number'); self::field('Owner/accountant email', 'feature_settings[accounting][daily_email]', $s['daily_email'], 'email'); echo '</div><button class="ttos-button">Save accounting settings</button></form>';
        $daily = self::daily_close_snapshot();
        echo '<div class="ttos-grid ttos-grid-4">'; self::metric('Today gross', self::money($daily['gross'])); self::metric('Card', self::money($daily['card'])); self::metric('Cash', self::money($daily['cash'])); self::metric('VAT est.', self::money($daily['vat'])); echo '</div>';
        echo '<p><a class="ttos-button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=ttos_export_orders_csv'), 'ttos_export_orders_csv')) . '">Export orders CSV</a></p>';
        self::accounting_export_log_table();
        echo '</section>';
    }

    private static function settings_hidden_except(string $sections_csv): void {
        $keep = array_map('trim', explode(',', $sections_csv));
        $all = self::get();
        foreach ($all as $section => $values) {
            if (in_array($section, $keep, true)) continue;
            foreach ($values as $key => $value) {
                echo '<input type="hidden" name="feature_settings[' . esc_attr($section) . '][' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '">';
            }
        }
    }

    private static function field(string $label, string $name, $value = '', string $type = 'text'): void {
        echo '<label>' . esc_html($label) . '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '"></label>';
    }

    private static function media_field(string $label, string $name, int $value = 0, string $button = 'Choose image'): void {
        $id = 'ttos-media-' . md5($name . wp_rand());
        $thumb = $value ? wp_get_attachment_image_url($value, 'medium') : '';
        echo '<label class="ttos-media-field">' . esc_html($label) . '<input type="hidden" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '"><span class="ttos-media-preview" data-empty="No image selected">';
        echo $thumb ? '<img src="' . esc_url($thumb) . '" alt="">' : '<em>No image selected</em>';
        echo '</span><span class="ttos-media-actions"><button type="button" class="ttos-mini ttos-pick-media" data-target="#' . esc_attr($id) . '">' . esc_html($button) . '</button><button type="button" class="ttos-mini ttos-clear-media" data-target="#' . esc_attr($id) . '">Remove</button></span></label>';
    }

    private static function metric(string $label, $value): void { echo '<section class="ttos-metric"><span>' . esc_html($label) . '</span><strong>' . esc_html((string) $value) . '</strong></section>'; }
    private static function money($amount): string { return function_exists('wc_price') ? wp_strip_all_tags(wc_price((float) $amount)) : '£' . number_format((float) $amount, 2); }
    private static function decimal($value): string { return function_exists('wc_format_decimal') ? wc_format_decimal($value) : number_format((float) $value, 2, '.', ''); }

    private static function meal_deals(): array {
        $deals = get_option('ttos_meal_deals', array());
        return is_array($deals) ? $deals : array();
    }

    private static function save_meal_deal(array $raw): void {
        $id = 'deal_' . wp_generate_password(8, false, false);
        $name = sanitize_text_field($raw['name'] ?? 'Meal Deal');
        $price = self::decimal($raw['price'] ?? '0');
        $product_id = self::create_or_update_deal_product(0, $name, $price, sanitize_textarea_field($raw['description'] ?? ''), absint($raw['image_id'] ?? 0));
        $deal = array(
            'id' => $id,
            'name' => $name,
            'description' => sanitize_textarea_field($raw['description'] ?? ''),
            'price' => $price,
            'image_id' => absint($raw['image_id'] ?? 0),
            'main_cats' => array_map('absint', (array) ($raw['main_cats'] ?? array())),
            'side_cats' => array_map('absint', (array) ($raw['side_cats'] ?? array())),
            'drink_cats' => array_map('absint', (array) ($raw['drink_cats'] ?? array())),
            'active' => !empty($raw['active']),
            'product_id' => $product_id,
        );
        $deals = self::meal_deals();
        $deals[$id] = $deal;
        update_option('ttos_meal_deals', $deals, false);
    }

    private static function delete_meal_deal(string $deal_id): void {
        $deals = self::meal_deals();
        if (isset($deals[$deal_id])) {
            if (!empty($deals[$deal_id]['product_id'])) wp_trash_post((int) $deals[$deal_id]['product_id']);
            unset($deals[$deal_id]);
            update_option('ttos_meal_deals', $deals, false);
        }
    }

    private static function create_or_update_deal_product(int $product_id, string $name, string $price, string $description, int $image_id): int {
        if (!post_type_exists('product')) return 0;
        $post = array('post_title' => $name, 'post_content' => $description, 'post_excerpt' => $description, 'post_type' => 'product', 'post_status' => 'publish');
        $product_id = wp_insert_post($post);
        if (!$product_id || is_wp_error($product_id)) return 0;
        update_post_meta($product_id, '_regular_price', $price);
        update_post_meta($product_id, '_price', $price);
        update_post_meta($product_id, '_stock_status', 'instock');
        update_post_meta($product_id, '_ttos_menu_item', '1');
        update_post_meta($product_id, '_ttos_is_meal_deal', '1');
        if ($image_id) set_post_thumbnail($product_id, $image_id);
        wp_set_object_terms($product_id, 'simple', 'product_type');
        return (int) $product_id;
    }

    private static function products_for_cats(array $cat_ids): array {
        if (!$cat_ids || !TTOS_WooCommerce::active()) return array();
        $q = new WP_Query(array('post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 100, 'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => array_map('absint', $cat_ids)))));
        $out = array();
        while ($q->have_posts()) { $q->the_post(); $id = get_the_ID(); if (get_post_meta($id, '_ttos_is_meal_deal', true) === '1') continue; $out[$id] = get_the_title(); }
        wp_reset_postdata();
        return $out;
    }

    public static function shortcode_meal_deals(): string {
        if (!TTOS_Settings::module_enabled('meal_deals') || !TTOS_WooCommerce::active()) return '<div class="ttos-front-card">Meal deals are not enabled yet.</div>';
        $deals = array_filter(self::meal_deals(), function($d){ return !empty($d['active']) && !empty($d['product_id']); });
        ob_start(); echo '<div class="ttos-meal-deals"><h2>Meal deals</h2><div class="ttos-product-grid">';
        foreach ($deals as $deal) {
            $img = !empty($deal['image_id']) ? wp_get_attachment_image_url((int) $deal['image_id'], 'medium_large') : get_the_post_thumbnail_url((int) $deal['product_id'], 'medium_large');
            echo '<article class="ttos-product ttos-meal-deal">'; if ($img) echo '<img src="' . esc_url($img) . '" alt="' . esc_attr($deal['name']) . '">';
            echo '<div class="ttos-product-body"><div class="ttos-product-title"><h4>' . esc_html($deal['name']) . '</h4><strong>' . esc_html(self::money($deal['price'])) . '</strong></div><p>' . esc_html($deal['description']) . '</p><form method="post" class="ttos-config-form"><input type="hidden" name="add-to-cart" value="' . esc_attr((string) $deal['product_id']) . '"><input type="hidden" name="ttos_meal_deal_add" value="1"><input type="hidden" name="ttos_meal_deal_id" value="' . esc_attr($deal['id']) . '">';
            wp_nonce_field('ttos_meal_deal_' . $deal['id'], 'ttos_meal_deal_nonce');
            foreach (array('main' => 'Choose main', 'side' => 'Choose side', 'drink' => 'Choose drink') as $part => $label) {
                $products = self::products_for_cats((array) ($deal[$part . '_cats'] ?? array()));
                if (!$products) continue;
                echo '<label>' . esc_html($label) . '<select name="ttos_meal_parts[' . esc_attr($part) . ']" required><option value="">Choose…</option>'; foreach ($products as $id => $title) echo '<option value="' . esc_attr((string) $id) . '">' . esc_html($title) . '</option>'; echo '</select></label>';
            }
            echo '<button class="ttos-order-btn">Add deal</button></form></div></article>';
        }
        if (!$deals) echo '<div class="ttos-front-card">No meal deals are live yet.</div>';
        echo '</div></div>'; return ob_get_clean();
    }

    public static function validate_meal_deal_add_to_cart(bool $passed, int $product_id, int $quantity): bool {
        if (empty($_POST['ttos_meal_deal_add'])) return $passed;
        $deal_id = sanitize_key(wp_unslash($_POST['ttos_meal_deal_id'] ?? ''));
        if (empty($_POST['ttos_meal_deal_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ttos_meal_deal_nonce'])), 'ttos_meal_deal_' . $deal_id)) return false;
        $deals = self::meal_deals(); $deal = $deals[$deal_id] ?? null;
        if (!$deal || (int) ($deal['product_id'] ?? 0) !== $product_id) { wc_add_notice('Meal deal is unavailable.', 'error'); return false; }
        $parts = isset($_POST['ttos_meal_parts']) && is_array($_POST['ttos_meal_parts']) ? array_map('absint', wp_unslash($_POST['ttos_meal_parts'])) : array();
        foreach (array('main','side','drink') as $part) {
            if (!empty($deal[$part . '_cats']) && empty($parts[$part])) { wc_add_notice('Please choose a ' . $part . ' for the meal deal.', 'error'); return false; }
        }
        return $passed;
    }

    public static function add_meal_deal_cart_item_data(array $cart_item_data, int $product_id, int $variation_id): array {
        if (empty($_POST['ttos_meal_deal_add'])) return $cart_item_data;
        $deal_id = sanitize_key(wp_unslash($_POST['ttos_meal_deal_id'] ?? ''));
        $parts = isset($_POST['ttos_meal_parts']) && is_array($_POST['ttos_meal_parts']) ? array_map('absint', wp_unslash($_POST['ttos_meal_parts'])) : array();
        $cart_item_data['ttos_meal_deal_id'] = $deal_id;
        $cart_item_data['ttos_meal_parts'] = $parts;
        $cart_item_data['ttos_unique_key'] = md5($deal_id . wp_json_encode($parts) . microtime(true));
        return $cart_item_data;
    }

    public static function display_meal_deal_cart_item_data(array $item_data, array $cart_item): array {
        if (empty($cart_item['ttos_meal_parts']) || !is_array($cart_item['ttos_meal_parts'])) return $item_data;
        foreach ($cart_item['ttos_meal_parts'] as $part => $pid) if ($pid) $item_data[] = array('name' => ucfirst($part), 'value' => get_the_title((int) $pid));
        return $item_data;
    }

    public static function save_meal_deal_order_item_meta($item, string $cart_item_key, array $values, $order): void {
        if (empty($values['ttos_meal_parts']) || !is_array($values['ttos_meal_parts'])) return;
        foreach ($values['ttos_meal_parts'] as $part => $pid) if ($pid) $item->add_meta_data('Meal deal ' . ucfirst($part), get_the_title((int) $pid), true);
    }

    private static function save_inventory_rows(array $rows): void {
        foreach ($rows as $row) {
            $id = absint($row['id'] ?? 0); if (!$id || get_post_type($id) !== 'product') continue;
            $available = !empty($row['available']); $hidden = !empty($row['hidden']); $qty = isset($row['qty']) && $row['qty'] !== '' ? max(0, absint($row['qty'])) : '';
            wp_update_post(array('ID' => $id, 'post_status' => $hidden ? 'draft' : 'publish'));
            if ($qty !== '') { update_post_meta($id, '_manage_stock', 'yes'); update_post_meta($id, '_stock', $qty); update_post_meta($id, '_stock_status', ($available && $qty > 0) ? 'instock' : 'outofstock'); }
            else { update_post_meta($id, '_manage_stock', 'no'); update_post_meta($id, '_stock_status', $available ? 'instock' : 'outofstock'); }
        }
    }

    public static function analytics_snapshot(): array {
        $out = array('orders' => 0, 'revenue' => 0.0, 'aov' => 0.0, 'busy_hour' => '—', 'top_items' => array(), 'statuses' => array());
        if (!TTOS_WooCommerce::active()) return $out;
        $orders = wc_get_orders(array('limit' => 300, 'date_created' => '>' . (new WC_DateTime('-30 days'))->date('Y-m-d H:i:s'), 'return' => 'objects'));
        $hours = array();
        foreach ($orders as $order) {
            $out['orders']++; $out['revenue'] += (float) $order->get_total();
            $status = wc_get_order_status_name($order->get_status()); $out['statuses'][$status] = ($out['statuses'][$status] ?? 0) + 1;
            if ($order->get_date_created()) { $h = $order->get_date_created()->date_i18n('H:00'); $hours[$h] = ($hours[$h] ?? 0) + 1; }
            foreach ($order->get_items() as $item) { $name = $item->get_name(); $out['top_items'][$name] = ($out['top_items'][$name] ?? 0) + (int) $item->get_quantity(); }
        }
        arsort($out['top_items']); $out['top_items'] = array_slice($out['top_items'], 0, 10, true); arsort($hours); $out['busy_hour'] = $hours ? array_key_first($hours) : '—'; $out['aov'] = $out['orders'] ? $out['revenue'] / $out['orders'] : 0;
        return $out;
    }

    private static function customer_snapshot(): array {
        $customers = array(); if (!TTOS_WooCommerce::active()) return $customers;
        $orders = wc_get_orders(array('limit' => 300, 'return' => 'objects'));
        foreach ($orders as $order) {
            $email = strtolower($order->get_billing_email()); if (!$email) continue; if (!isset($customers[$email])) $customers[$email] = array('name'=>$order->get_formatted_billing_full_name(),'orders'=>0,'total'=>0.0,'last'=>'','last_ts'=>0,'dormant'=>false);
            $customers[$email]['orders']++; $customers[$email]['total'] += (float) $order->get_total(); $ts = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0; if ($ts > $customers[$email]['last_ts']) { $customers[$email]['last_ts'] = $ts; $customers[$email]['last'] = $order->get_date_created()->date_i18n('d M Y'); }
        }
        foreach ($customers as &$c) $c['dormant'] = $c['last_ts'] && $c['last_ts'] < strtotime('-60 days');
        uasort($customers, function($a,$b){ return $b['total'] <=> $a['total']; }); return $customers;
    }

    private static function daily_close_snapshot(): array {
        $out = array('gross'=>0.0,'card'=>0.0,'cash'=>0.0,'vat'=>0.0); if (!TTOS_WooCommerce::active()) return $out;
        $orders = wc_get_orders(array('limit'=>200,'date_created'=>'>'.gmdate('Y-m-d 00:00:00'),'return'=>'objects'));
        foreach ($orders as $order) { $total = (float) $order->get_total(); $out['gross'] += $total; $method = strtolower($order->get_payment_method()); if (strpos($method, 'cod') !== false || strpos($method, 'cash') !== false) $out['cash'] += $total; else $out['card'] += $total; }
        $rate = (float) self::get('accounting', 'vat_rate'); $out['vat'] = $rate > 0 ? $out['gross'] - ($out['gross'] / (1 + ($rate / 100))) : 0; return $out;
    }

    private static function delivery_rules(): array {
        $raw = (string) self::get('advanced_zones', 'rules'); $rules = array();
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line); if ($line === '') continue; $p = array_map('trim', explode('|', $line)); if (count($p) < 5) continue;
            $rules[] = array('name'=>$p[0], 'prefixes'=>array_filter(array_map('strtoupper', array_map('trim', explode(',', $p[1])))), 'fee'=>(float) $p[2], 'min'=>(float) $p[3], 'free'=>(float) $p[4]);
        }
        return $rules;
    }

    private static function zone_for_postcode(string $postcode): ?array {
        $pc = strtoupper(preg_replace('/\s+/', '', $postcode)); if ($pc === '') return null;
        foreach (self::delivery_rules() as $rule) foreach ($rule['prefixes'] as $prefix) if ($prefix !== '' && strpos($pc, preg_replace('/\s+/', '', $prefix)) === 0) return $rule;
        return null;
    }

    private static function current_fulfilment_method(): string {
        $method = '';

        if (isset($_POST['ttos_fulfilment_method'])) {
            $method = sanitize_key(wp_unslash($_POST['ttos_fulfilment_method']));
        } elseif (isset($_POST['post_data'])) {
            $posted = array();
            parse_str(wp_unslash($_POST['post_data']), $posted);
            $method = sanitize_key($posted['ttos_fulfilment_method'] ?? '');
        }

        if ($method !== '' && function_exists('WC') && WC()->session) {
            WC()->session->set('ttos_fulfilment_method', $method);
        }

        if ($method === '' && function_exists('WC') && WC()->session) {
            $method = sanitize_key((string) WC()->session->get('ttos_fulfilment_method', ''));
        }

        if ($method === '') {
            $ops = get_option('ttos_operations_settings', array());
            $method = sanitize_key($ops['checkout']['default_method'] ?? 'delivery');
        }

        return $method ?: 'delivery';
    }

    public static function apply_advanced_zone_fee($cart): void {
        if (!TTOS_Settings::module_enabled('advanced_zones') || is_admin() && !defined('DOING_AJAX') || !$cart) return;
        if (self::current_fulfilment_method() !== 'delivery') return;
        $postcode = WC()->customer ? WC()->customer->get_shipping_postcode() : ''; $zone = self::zone_for_postcode($postcode); if (!$zone) return;
        $subtotal = (float) $cart->get_subtotal(); if ($zone['free'] > 0 && $subtotal >= $zone['free']) return;
        if ($zone['fee'] > 0) $cart->add_fee('Delivery zone: ' . $zone['name'], $zone['fee']);
    }

    public static function validate_advanced_zone_checkout(): void {
        if (!TTOS_Settings::module_enabled('advanced_zones') || !function_exists('WC')) return;
        if (self::current_fulfilment_method() !== 'delivery') return;
        $postcode = isset($_POST['shipping_postcode']) ? sanitize_text_field(wp_unslash($_POST['shipping_postcode'])) : sanitize_text_field(wp_unslash($_POST['billing_postcode'] ?? ''));
        $zone = self::zone_for_postcode($postcode); if (!$zone) return;
        $subtotal = WC()->cart ? (float) WC()->cart->get_subtotal() : 0;
        if ($subtotal < (float) $zone['min']) wc_add_notice(sprintf('Minimum delivery order for %s is %s.', $zone['name'], self::money($zone['min'])), 'error');
    }

    public static function award_rewards(int $order_id): void {
        if (!TTOS_Settings::module_enabled('loyalty') && !TTOS_Settings::module_enabled('stamp_cards')) return;
        $order = wc_get_order($order_id); if (!$order || $order->get_meta('_ttos_rewards_awarded')) return;
        $email = strtolower($order->get_billing_email()); if (!$email) return;
        $points = (int) floor((float) $order->get_total() * (float) self::get('loyalty','points_per_pound'));
        if (TTOS_Settings::module_enabled('loyalty')) self::ledger_add($email, $points, 'Order #' . $order_id);
        if (TTOS_Settings::module_enabled('stamp_cards')) self::add_stamp($email, $order_id);
        $order->update_meta_data('_ttos_rewards_awarded', '1'); $order->save();
    }

    private static function ledger_add(string $email, int $points, string $note): void {
        $ledger = get_option('ttos_loyalty_ledger', array()); if (!is_array($ledger)) $ledger = array();
        $ledger[] = array('email'=>$email,'points'=>$points,'note'=>$note,'time'=>time()); update_option('ttos_loyalty_ledger', array_slice($ledger, -1000), false);
    }

    public static function loyalty_balance(string $email): int {
        $ledger = get_option('ttos_loyalty_ledger', array()); $balance = 0; if (is_array($ledger)) foreach ($ledger as $row) if (($row['email'] ?? '') === strtolower($email)) $balance += (int) ($row['points'] ?? 0); return $balance;
    }


    public static function manual_loyalty_adjustment(string $email, int $points, string $note = 'Manual CRM adjustment'): void {
        $email = strtolower(sanitize_email($email));
        if ($email === '' || $points === 0) return;
        self::ledger_add($email, $points, $note);
    }

    private static function add_stamp(string $email, int $order_id): void {
        $stamps = get_option('ttos_stamp_cards', array()); if (!is_array($stamps)) $stamps = array(); $count = (int) ($stamps[$email]['count'] ?? 0) + 1; $target = max(1, (int) self::get('loyalty','stamp_target'));
        $stamps[$email] = array('count'=>$count,'last_order'=>$order_id); if ($count >= $target && TTOS_WooCommerce::active()) { $stamps[$email]['count'] = 0; $stamps[$email]['last_coupon'] = self::create_reward_coupon($email); }
        update_option('ttos_stamp_cards', $stamps, false);
    }

    private static function create_reward_coupon(string $email): string {
        if (!post_type_exists('shop_coupon')) return '';
        $code = 'DIRECT-' . strtoupper(wp_generate_password(6, false, false)); $amount = self::get('loyalty','reward_coupon_amount');
        $id = wp_insert_post(array('post_title'=>$code,'post_type'=>'shop_coupon','post_status'=>'publish','post_excerpt'=>'Takeaway OS stamp card reward for ' . $email));
        if ($id && !is_wp_error($id)) { update_post_meta($id,'_ttos_generated_coupon','1'); update_post_meta($id,'discount_type','fixed_cart'); update_post_meta($id,'coupon_amount',$amount); update_post_meta($id,'usage_limit','1'); update_post_meta($id,'customer_email',array($email)); }
        return $code;
    }

    public static function shortcode_rewards(): string {
        if (!is_user_logged_in()) return '<div class="ttos-front-card"><h2>Rewards</h2><p>Please log in to view your rewards.</p></div>';
        $user = wp_get_current_user(); $email = strtolower($user->user_email); $balance = self::loyalty_balance($email); $stamps = get_option('ttos_stamp_cards', array()); $count = is_array($stamps) ? (int) ($stamps[$email]['count'] ?? 0) : 0; $target = max(1, (int) self::get('loyalty','stamp_target'));
        return '<div class="ttos-front-card"><h2>Your rewards</h2><p><strong>' . esc_html((string) $balance) . '</strong> points</p><p>Stamp card: ' . esc_html((string) $count) . ' / ' . esc_html((string) $target) . '</p></div>';
    }

    public static function account_endpoint(): void { add_rewrite_endpoint('takeaway-rewards', EP_ROOT | EP_PAGES); }
    public static function account_rewards_endpoint(): void { echo do_shortcode('[takeaway_rewards]'); }

    public static function cron_schedules(array $schedules): array {
        $schedules['ttos_every_five_minutes'] = array('interval' => 300, 'display' => 'Every five minutes for Takeaway OS');
        return $schedules;
    }

    public static function retry_queue_handler(): void {
        self::process_retry_queue(false);
    }

    private static function printer_profiles(): array {
        return array(
            'generic_webhook' => 'Generic webhook / relay',
            'printnode_bridge' => 'PrintNode bridge',
            'escpos_bridge' => 'ESC/POS bridge',
            'email_to_print' => 'Email-to-print relay',
        );
    }

    private static function epos_profiles(): array {
        return array(
            'generic_webhook' => 'Generic JSON webhook',
            'square_bridge' => 'Square bridge / middleware',
            'icrtouch_bridge' => 'ICRTouch bridge / middleware',
            'deliverect_bridge' => 'Deliverect bridge',
            'custom' => 'Custom connector',
        );
    }

    private static function integration_health_label(string $type, array $settings): string {
        if (empty($settings['endpoint'])) return 'Not configured';
        $queue = self::integration_queue();
        $waiting = 0;
        foreach ($queue as $item) if (($item['type'] ?? '') === $type) $waiting++;
        return $waiting ? 'Configured · ' . $waiting . ' retry' . ($waiting === 1 ? '' : 'ies') : 'Configured';
    }

    private static function sms_health_label(array $settings): string {
        if (empty($settings['twilio_sid']) || empty($settings['twilio_token']) || empty($settings['twilio_from'])) return 'Staging/log only';
        return 'Configured';
    }

    private static function integration_queue(): array {
        $queue = get_option('ttos_integration_queue', array());
        return is_array($queue) ? $queue : array();
    }

    private static function queue_webhook(string $type, array $settings, array $payload, string $error, int $code = 0): void {
        $max = max(0, absint($settings['max_retries'] ?? 3));
        if ($max <= 0) return;
        $delay = max(1, absint($settings['retry_delay'] ?? 5));
        $queue = self::integration_queue();
        $queue[] = array(
            'id' => uniqid('ttos_', true),
            'type' => $type,
            'profile' => sanitize_key($settings['profile'] ?? 'generic_webhook'),
            'endpoint' => esc_url_raw($settings['endpoint'] ?? ''),
            'settings' => $settings,
            'payload' => $payload,
            'attempts' => 0,
            'max_attempts' => $max,
            'next_run' => time() + ($delay * MINUTE_IN_SECONDS),
            'last_error' => $error,
            'last_code' => $code,
            'created' => time(),
        );
        update_option('ttos_integration_queue', array_slice($queue, -80), false);
        self::log($type, 'Queued retry: ' . $error . ($code ? ' · HTTP ' . $code : ''));
    }

    private static function process_retry_queue(bool $manual = false): void {
        $queue = self::integration_queue();
        if (!$queue) { self::log('retry', 'Retry queue is empty.'); return; }
        $now = time();
        $remaining = array();
        $processed = 0;
        foreach ($queue as $item) {
            $due = $manual || (int) ($item['next_run'] ?? 0) <= $now;
            if (!$due) { $remaining[] = $item; continue; }
            $processed++;
            $type = sanitize_key($item['type'] ?? 'webhook');
            $settings = is_array($item['settings'] ?? null) ? $item['settings'] : array('endpoint' => $item['endpoint'] ?? '');
            $payload = is_array($item['payload'] ?? null) ? $item['payload'] : array();
            $ok = self::post_webhook($type, $settings, $payload, 'retry', false);
            if (!$ok) {
                $item['attempts'] = (int) ($item['attempts'] ?? 0) + 1;
                $max = (int) ($item['max_attempts'] ?? 3);
                if ($item['attempts'] < $max) {
                    $delay = max(1, absint($settings['retry_delay'] ?? 5));
                    $item['next_run'] = time() + ($delay * MINUTE_IN_SECONDS);
                    $remaining[] = $item;
                } else {
                    self::log($type, 'Retry abandoned after ' . $max . ' attempts for ' . self::redact_url($item['endpoint'] ?? ''));
                }
            }
        }
        update_option('ttos_integration_queue', array_values($remaining), false);
        self::log('retry', 'Processed ' . $processed . ' queued integration item' . ($processed === 1 ? '' : 's') . '.');
    }

    private static function integration_queue_table(array $queue): void {
        if (!$queue) { echo '<p class="ttos-muted">No failed sends waiting. Tiny calm island.</p>'; return; }
        echo '<table class="ttos-table ttos-integration-table"><thead><tr><th>Type</th><th>Attempts</th><th>Next run</th><th>Last error</th></tr></thead><tbody>';
        foreach (array_reverse(array_slice($queue, -15)) as $item) {
            echo '<tr><td>' . esc_html(strtoupper($item['type'] ?? 'webhook')) . '<br><span class="ttos-muted">' . esc_html($item['profile'] ?? '') . '</span></td><td>' . esc_html((string) ($item['attempts'] ?? 0)) . ' / ' . esc_html((string) ($item['max_attempts'] ?? 0)) . '</td><td>' . esc_html(date_i18n('d M H:i', (int) ($item['next_run'] ?? time()))) . '</td><td>' . esc_html($item['last_error'] ?? '') . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function integration_log_table(array $rows): void {
        if (!$rows) { echo '<p class="ttos-muted">No integration logs yet.</p>'; return; }
        echo '<table class="ttos-table ttos-integration-table"><thead><tr><th>Time</th><th>Type</th><th>Message</th></tr></thead><tbody>';
        foreach ($rows as $row) echo '<tr><td>' . esc_html(date_i18n('d M H:i', (int) ($row['time'] ?? time()))) . '</td><td>' . esc_html(strtoupper($row['type'] ?? 'log')) . '</td><td>' . esc_html($row['message'] ?? '') . '</td></tr>';
        echo '</tbody></table>';
    }

    private static function redact_url(string $url): string {
        if (!$url) return '';
        $parts = wp_parse_url($url);
        if (!$parts || empty($parts['host'])) return $url;
        return ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . (!empty($parts['path']) ? $parts['path'] : '');
    }

    private static function mapped_epos_status(string $status): string {
        $map = array();
        foreach (preg_split('/\r\n|\r|\n/', (string) self::get('epos', 'status_map')) as $line) {
            if (strpos($line, '=') === false) continue;
            list($from, $to) = array_map('trim', explode('=', $line, 2));
            if ($from !== '') $map[$from] = $to;
        }
        return $map[$status] ?? $status;
    }

    private static function send_test_sms(): void {
        $settings = self::get('sms');
        $phone = trim((string) ($settings['test_phone'] ?? ''));
        if (!$phone) { self::log('sms', 'No test phone number set.'); return; }
        self::send_sms_message($phone, 'Takeaway OS test SMS. If this arrived, SMS delivery is working.', 'test');
    }

    private static function order_payload($order, string $event): array {
        $items = array();
        foreach ($order->get_items() as $item) {
            $meta = array();
            foreach ($item->get_meta_data() as $m) {
                $data = $m->get_data();
                $key = (string) ($data['key'] ?? '');
                if ($key === '' || strpos($key, '_') === 0) continue;
                $meta[] = array('key' => $key, 'value' => wp_strip_all_tags(is_scalar($data['value'] ?? '') ? (string) $data['value'] : wp_json_encode($data['value'])));
            }
            $items[] = array(
                'name' => $item->get_name(),
                'qty' => $item->get_quantity(),
                'subtotal' => $item->get_subtotal(),
                'total' => $item->get_total(),
                'meta' => $meta,
            );
        }
        $status = $order->get_status();
        return array(
            'source' => 'Takeaway OS',
            'version' => TTOS_VERSION,
            'event' => $event,
            'order_id' => $order->get_id(),
            'status' => $status,
            'epos_status' => self::mapped_epos_status($status),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'fulfilment' => array(
                'method' => $order->get_meta('_ttos_fulfilment_method') ?: 'unknown',
                'requested_time' => $order->get_meta('_ttos_requested_time') ?: 'asap',
                'is_preorder' => $order->get_meta('_ttos_is_preorder') === '1',
                'prep_minutes' => $order->get_meta('_ttos_prep_minutes'),
                'due_ts' => $order->get_meta('_ttos_due_ts'),
            ),
            'customer' => array(
                'name' => $order->get_formatted_billing_full_name(),
                'phone' => $order->get_billing_phone(),
                'email' => $order->get_billing_email(),
            ),
            'billing_address' => $order->get_formatted_billing_address(),
            'shipping_address' => $order->get_formatted_shipping_address(),
            'items' => $items,
            'notes' => array(
                'customer_note' => $order->get_customer_note(),
                'kitchen_note' => $order->get_meta('_ttos_kitchen_note'),
            ),
            'created' => $order->get_date_created() ? $order->get_date_created()->date('c') : '',
        );
    }

    public static function resend_order_integrations(int $order_id, string $event = 'manual_resend'): bool {
        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        if (!$order) {
            self::log('manual', 'Order #' . $order_id . ' not found for resend.');
            return false;
        }
        $payload = self::order_payload($order, $event);
        $sent = false;
        if (TTOS_Settings::module_enabled('printer')) {
            self::post_webhook('printer', self::get('printer'), $payload, 'manual', true);
            $sent = true;
        }
        if (TTOS_Settings::module_enabled('epos_connector')) {
            self::post_webhook('epos', self::get('epos'), $payload, 'manual', true);
            $sent = true;
        }
        if (TTOS_Settings::module_enabled('sms_updates')) {
            self::send_order_sms($order, $order->get_status());
            $sent = true;
        }
        if (!$sent) {
            self::log('manual', 'No enabled printer, EPOS or SMS modules for order #' . $order_id . '.');
        }
        return $sent;
    }

    public static function order_created_integrations($order_id, $order = null): void {
        $order = $order ?: wc_get_order($order_id); if (!$order) return; $payload = self::order_payload($order, 'new_order');
        if (TTOS_Settings::module_enabled('printer') && self::get('printer','send_on_new_order') === '1') self::post_webhook('printer', self::get('printer'), $payload, 'new_order', true);
        if (TTOS_Settings::module_enabled('epos_connector') && self::get('epos','send_on_new_order') === '1') self::post_webhook('epos', self::get('epos'), $payload, 'new_order', true);
    }

    public static function order_status_integrations(int $order_id, string $old_status, string $new_status, $order): void {
        if (!$order) return; $payload = self::order_payload($order, 'status_changed'); $payload['old_status'] = $old_status; $payload['new_status'] = $new_status;
        if (TTOS_Settings::module_enabled('printer') && self::get('printer','send_on_status_change') === '1') self::post_webhook('printer', self::get('printer'), $payload, 'status_changed', true);
        if (TTOS_Settings::module_enabled('epos_connector') && self::get('epos','send_on_status_change') === '1') self::post_webhook('epos', self::get('epos'), $payload, 'status_changed', true);
        if (TTOS_Settings::module_enabled('sms_updates')) self::send_order_sms($order, $new_status);
    }

    private static function post_webhook(string $type, array $settings, array $payload, string $context = 'live', bool $queue_on_fail = true): bool {
        $endpoint = esc_url_raw($settings['endpoint'] ?? '');
        if (!$endpoint) { self::log($type, 'No endpoint set.'); return false; }
        $headers = array('Content-Type'=>'application/json', 'X-Takeaway-OS-Version' => TTOS_VERSION, 'X-Takeaway-OS-Event' => (string) ($payload['event'] ?? $context));
        if (!empty($settings['api_key'])) $headers['Authorization'] = 'Bearer ' . $settings['api_key'];
        $payload['connector'] = array('type' => $type, 'profile' => sanitize_key($settings['profile'] ?? 'generic_webhook'), 'context' => $context);
        $res = wp_remote_post($endpoint, array('timeout'=>max(5, absint($settings['timeout'] ?? 15)), 'headers'=>$headers, 'body'=>wp_json_encode($payload)));
        if (is_wp_error($res)) {
            $err = $res->get_error_message();
            self::log($type, strtoupper($context) . ' failed: ' . $err . ' → ' . self::redact_url($endpoint));
            if ($queue_on_fail) self::queue_webhook($type, $settings, $payload, $err);
            return false;
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $body = trim((string) wp_remote_retrieve_body($res));
        $ok = $code >= 200 && $code < 300;
        $message = strtoupper($context) . ' HTTP ' . $code . ' → ' . self::redact_url($endpoint);
        if ($body !== '') $message .= ' · ' . wp_trim_words(wp_strip_all_tags($body), 12, '…');
        self::log($type, $message);
        if (!$ok && $queue_on_fail) self::queue_webhook($type, $settings, $payload, 'HTTP ' . $code, $code);
        return $ok;
    }

    private static function send_order_sms($order, string $status): void {
        $phone = $order->get_billing_phone(); if (!$phone) return; $settings = self::get('sms'); $template = '';
        if ($status === 'ttos-accepted' && $settings['send_on_accepted'] === '1') $template = $settings['accepted_template'];
        if ($status === 'ttos-ready' && $settings['send_on_ready'] === '1') $template = $settings['ready_template'];
        if ($status === 'ttos-out' && $settings['send_on_out'] === '1') $template = $settings['out_template'];
        if (in_array($status, array('cancelled','failed'), true) && $settings['send_on_cancelled'] === '1') $template = $settings['cancelled_template'];
        if (!$template) return;
        $message = str_replace(array('{order_id}','{total}','{status}','{name}'), array($order->get_id(), self::money($order->get_total()), wc_get_order_status_name($status), $order->get_billing_first_name()), $template);
        self::send_sms_message($phone, $message, $status);
    }

    private static function send_sms_message(string $phone, string $message, string $context = 'sms'): bool {
        $settings = self::get('sms');
        if (empty($settings['twilio_sid']) || empty($settings['twilio_token']) || empty($settings['twilio_from'])) { self::log('sms', 'Missing Twilio credentials. Would send to ' . $phone . ': ' . $message); return false; }
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($settings['twilio_sid']) . '/Messages.json';
        $res = wp_remote_post($url, array('timeout'=>15, 'headers'=>array('Authorization'=>'Basic ' . base64_encode($settings['twilio_sid'] . ':' . $settings['twilio_token'])), 'body'=>array('From'=>$settings['twilio_from'], 'To'=>$phone, 'Body'=>$message)));
        if (is_wp_error($res)) { self::log('sms', strtoupper($context) . ' failed: ' . $res->get_error_message()); return false; }
        $code = (int) wp_remote_retrieve_response_code($res);
        $body = trim((string) wp_remote_retrieve_body($res));
        self::log('sms', strtoupper($context) . ' HTTP ' . $code . ($body ? ' · ' . wp_trim_words(wp_strip_all_tags($body), 12, '…') : ''));
        return $code >= 200 && $code < 300;
    }

    private static function log(string $type, string $message): void {
        $log = get_option('ttos_integration_log', array()); if (!is_array($log)) $log = array(); $log[] = array('type'=>$type,'message'=>$message,'time'=>time()); update_option('ttos_integration_log', array_slice($log, -200), false);
    }

    private static function send_test_hooks(): void {
        $payload = array(
            'source' => 'Takeaway OS',
            'version' => TTOS_VERSION,
            'event' => 'test',
            'order_id' => 0,
            'status' => 'test',
            'epos_status' => 'test',
            'total' => '12.50',
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'GBP',
            'fulfilment' => array('method' => 'collection', 'requested_time' => 'asap'),
            'customer' => array('name'=>'Test Customer','phone'=>'07123456789','email'=>'test@example.com'),
            'items' => array(array('name'=>'Test burger','qty'=>1,'total'=>'8.50','meta'=>array(array('key'=>'Sauce','value'=>'Garlic'))), array('name'=>'Chips','qty'=>1,'total'=>'4.00','meta'=>array())),
        );
        if (TTOS_Settings::module_enabled('printer')) self::post_webhook('printer', self::get('printer'), $payload, 'test', true);
        if (TTOS_Settings::module_enabled('epos_connector')) self::post_webhook('epos', self::get('epos'), $payload, 'test', true);
        if (!TTOS_Settings::module_enabled('printer') && !TTOS_Settings::module_enabled('epos_connector')) self::log('test', 'No printer or EPOS module enabled for test payload.');
    }

    public static function shortcode_home_blocks(): string {
        $s = self::get('content');
        return '<section class="ttos-home-blocks"><p class="ttos-eyebrow">' . esc_html($s['hero_eyebrow']) . '</p><h1>' . esc_html($s['hero_title']) . '</h1><p>' . esc_html($s['hero_text']) . '</p><div class="ttos-home-offer"><strong>' . esc_html($s['offer_title']) . '</strong><span>' . esc_html($s['offer_text']) . '</span></div><a class="ttos-order-btn" href="' . esc_url($s['cta_url']) . '">' . esc_html($s['cta_label']) . '</a></section>';
    }

    public static function export_orders_csv(): void {
        if (!current_user_can('ttos_view_reports') || !check_admin_referer('ttos_export_orders_csv')) wp_die('Not allowed.');
        $rows = array();
        if (TTOS_WooCommerce::active()) {
            foreach (wc_get_orders(array('limit'=>1000,'return'=>'objects')) as $o) {
                $rows[] = array(
                    $o->get_id(),
                    $o->get_date_created() ? $o->get_date_created()->date('Y-m-d H:i:s') : '',
                    $o->get_status(),
                    $o->get_formatted_billing_full_name(),
                    $o->get_billing_email(),
                    $o->get_billing_phone(),
                    sanitize_key((string) $o->get_meta('_ttos_fulfilment_method')),
                    (string) $o->get_meta('_ttos_requested_time'),
                    $o->get_meta('_ttos_is_preorder') === '1' ? 'yes' : 'no',
                    $o->get_payment_method_title(),
                    $o->get_total(),
                    $o->get_total_tax(),
                    $o->get_shipping_total(),
                );
            }
        }
        self::record_accounting_export($rows ? 'success' : 'warning', sprintf('Orders export generated with %d row(s).', count($rows)), array('rows' => count($rows)));
        self::export_csv('takeaway-orders-' . gmdate('Y-m-d') . '.csv', array('order_id','date','status','customer','email','phone','fulfilment','requested_time','preorder','payment','total','tax','shipping'), $rows);
    }

    public static function export_customers_csv(): void {
        if (!current_user_can('ttos_view_reports') || !check_admin_referer('ttos_export_customers_csv')) wp_die('Not allowed.');
        $rows = array();
        foreach (self::customer_snapshot() as $email => $c) {
            $rows[] = array($c['name'], $email, $c['orders'], $c['total'], $c['last'], $c['dormant'] ? 'yes' : 'no');
        }
        self::record_accounting_export($rows ? 'success' : 'warning', sprintf('Customers export generated with %d row(s).', count($rows)), array('rows' => count($rows)));
        self::export_csv('takeaway-customers-' . gmdate('Y-m-d') . '.csv', array('name','email','orders','lifetime_value','last_order','dormant'), $rows);
    }

    private static function export_csv(string $filename, array $headers, array $rows): void {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    private static function record_accounting_export(string $status, string $message, array $context = array()): void {
        $log = get_option('ttos_accounting_export_log', array());
        if (!is_array($log)) {
            $log = array();
        }
        $log[] = array(
            'time'    => time(),
            'status'  => sanitize_key($status),
            'message' => sanitize_text_field($message),
            'context' => $context,
        );
        update_option('ttos_accounting_export_log', array_slice($log, -50), false);
    }

    private static function accounting_export_log_table(): void {
        $log = get_option('ttos_accounting_export_log', array());
        if (!is_array($log) || !$log) {
            echo '<p class="ttos-muted">No accounting exports logged yet.</p>';
            return;
        }
        echo '<h3>Recent export activity</h3><table class="ttos-table"><thead><tr><th>Time</th><th>Status</th><th>Message</th></tr></thead><tbody>';
        foreach (array_reverse(array_slice($log, -5)) as $row) {
            $status = (string) ($row['status'] ?? 'info');
            $class = $status === 'success' ? 'ttos-good' : ($status === 'warning' ? 'ttos-warn' : 'ttos-bad');
            echo '<tr><td>' . esc_html(date_i18n('d M Y H:i', (int) ($row['time'] ?? time()))) . '</td><td><span class="' . esc_attr($class) . '">' . esc_html(ucfirst($status)) . '</span></td><td>' . esc_html((string) ($row['message'] ?? '')) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
}

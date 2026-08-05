<?php

defined('ABSPATH') || exit;

/**
 * Production readiness layer for Takeaway OS.
 *
 * This class collects the final non-glamour systems: import/export, safe page
 * repair, emergency pause, inventory reset automation, campaign send/export and
 * handover reports. It deliberately uses WooCommerce as the order/product source
 * of truth while keeping owner-facing controls inside Takeaway OS.
 */
final class TTOS_Production {
    public static function hooks(): void {
        add_action('admin_menu', array(__CLASS__, 'menu'), 25);
        add_action('admin_init', array(__CLASS__, 'handle_posts'));
        add_action('admin_post_ttos_export_menu_csv', array(__CLASS__, 'export_menu_csv'));
        add_action('admin_post_ttos_export_campaign_contacts', array(__CLASS__, 'export_campaign_contacts'));
        add_action('admin_post_nopriv_ttos_campaign_unsubscribe', array(__CLASS__, 'handle_campaign_unsubscribe'));
        add_action('admin_post_ttos_campaign_unsubscribe', array(__CLASS__, 'handle_campaign_unsubscribe'));
        add_action('ttos_inventory_daily_reset', array(__CLASS__, 'inventory_daily_reset'));
        add_action('ttos_send_retention_email', array(__CLASS__, 'send_retention_email'), 10, 1);
        add_filter('woocommerce_add_to_cart_validation', array(__CLASS__, 'block_add_to_cart_if_paused'), 1, 2);
        add_action('woocommerce_checkout_process', array(__CLASS__, 'block_checkout_if_paused'));
        add_action('woocommerce_order_status_completed', array(__CLASS__, 'schedule_retention_after_completed_order'), 25, 1);
        add_action('wp_footer', array(__CLASS__, 'pause_banner'));

        if (!wp_next_scheduled('ttos_inventory_daily_reset')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'ttos_inventory_daily_reset');
        }
    }

    public static function menu(): void {
        add_submenu_page('takeaway-os', 'Production Tools', 'Production Tools', 'ttos_manage_settings', 'takeaway-os-production', array(__CLASS__, 'page'));
    }

    public static function defaults(): array {
        return array(
            'pause_enabled' => '0',
            'pause_message' => 'Online ordering is temporarily paused. Please call the restaurant to order.',
            'inventory_auto_reset' => '0',
            'low_stock_threshold' => '5',
            'campaign_from_name' => get_bloginfo('name'),
            'campaign_reply_to' => get_option('admin_email'),
            'retention_enabled' => '0',
            'retention_delay_days' => '30',
            'retention_coupon_amount' => '5.00',
            'retention_coupon_type' => 'fixed_cart',
            'retention_coupon_expiry_days' => '14',
            'handover_notes' => '',
        );
    }

    public static function get(string $key = '') {
        $settings = wp_parse_args(get_option('ttos_production_settings', array()), self::defaults());
        return $key === '' ? $settings : ($settings[$key] ?? null);
    }

    public static function update(array $values): void {
        update_option('ttos_production_settings', wp_parse_args($values, self::defaults()), false);
    }

    public static function handle_posts(): void {
        if (!is_admin() || empty($_POST['ttos_action'])) return;
        if (!current_user_can('ttos_manage_settings')) return;
        $action = sanitize_key(wp_unslash($_POST['ttos_action']));
        if (strpos($action, 'production_') !== 0) return;
        check_admin_referer('ttos_' . $action);

        if ($action === 'production_save_settings') {
            $raw = wp_unslash($_POST['production'] ?? array());
            self::update(array(
                'pause_enabled' => !empty($raw['pause_enabled']) ? '1' : '0',
                'pause_message' => sanitize_textarea_field($raw['pause_message'] ?? ''),
                'inventory_auto_reset' => !empty($raw['inventory_auto_reset']) ? '1' : '0',
                'low_stock_threshold' => (string) max(1, absint($raw['low_stock_threshold'] ?? 5)),
                'campaign_from_name' => sanitize_text_field($raw['campaign_from_name'] ?? get_bloginfo('name')),
                'campaign_reply_to' => sanitize_email($raw['campaign_reply_to'] ?? get_option('admin_email')),
                'retention_enabled' => !empty($raw['retention_enabled']) ? '1' : '0',
                'retention_delay_days' => (string) max(1, absint($raw['retention_delay_days'] ?? 30)),
                'retention_coupon_amount' => self::decimal($raw['retention_coupon_amount'] ?? '5.00'),
                'retention_coupon_type' => in_array(($raw['retention_coupon_type'] ?? 'fixed_cart'), array('fixed_cart', 'percent'), true) ? $raw['retention_coupon_type'] : 'fixed_cart',
                'retention_coupon_expiry_days' => (string) max(1, absint($raw['retention_coupon_expiry_days'] ?? 14)),
                'handover_notes' => sanitize_textarea_field($raw['handover_notes'] ?? ''),
            ));
            if (empty($raw['retention_enabled'])) {
                self::clear_all_retention_jobs();
            }
            self::redirect('production-settings-saved');
        }

        if ($action === 'production_apply_starter_menu') {
            self::apply_starter_menu();
            self::redirect('starter-menu-created', 'menu-tools');
        }

        if ($action === 'production_import_menu') {
            $result = self::import_menu_csv();
            if (is_wp_error($result)) {
                self::flash_notice('error', $result->get_error_message());
                self::redirect('menu-import-failed', 'menu-tools');
            }
            $notice_type = !empty($result['warnings']) ? 'warning' : 'success';
            self::flash_notice($notice_type, sprintf(__('Imported %1$d menu item(s): %2$d created, %3$d updated, %4$d skipped.', 'takeaway-os'), (int) $result['processed'], (int) $result['created'], (int) $result['updated'], (int) $result['skipped']));
            self::redirect('menu-imported', 'menu-tools');
        }

        if ($action === 'production_page_repair') {
            $mode = sanitize_key(wp_unslash($_POST['repair_mode'] ?? 'fresh_if_unsafe'));
            if (!in_array($mode, array('fresh_if_unsafe', 'append', 'replace'), true)) $mode = 'fresh_if_unsafe';
            TTOS_Page_Manager::ensure_all($mode);
            TTOS_Page_Manager::sync_woocommerce_page_options();
            self::redirect('pages-repaired', 'page-repair');
        }

        if ($action === 'production_reset_sold_out') {
            self::inventory_daily_reset(true);
            self::redirect('inventory-reset', 'inventory');
        }

        if ($action === 'production_send_campaign') {
            $campaign_id = sanitize_key(wp_unslash($_POST['campaign_id'] ?? ''));
            $result = self::send_campaign($campaign_id);
            $type = !empty($result['sent']) ? 'success' : 'warning';
            self::flash_notice($type, sprintf(__('Campaign processed: %1$d sent, %2$d skipped, %3$d invalid.', 'takeaway-os'), (int) ($result['sent'] ?? 0), (int) ($result['skipped'] ?? 0), (int) ($result['invalid'] ?? 0)));
            self::redirect('campaign-sent', 'campaigns');
        }
    }

    private static function redirect(string $notice, string $anchor = ''): void {
        $url = add_query_arg(array('page' => 'takeaway-os-production', 'ttos_notice' => $notice), admin_url('admin.php'));
        if ($anchor) $url .= '#' . sanitize_key($anchor);
        wp_safe_redirect($url);
        exit;
    }

    public static function page(): void {
        self::shell_start(
            'Production Tools',
            'Import menus, repair pages, pause ordering, reset stock and prep the final client handover.',
            self::secondary_nav_items()
        );
        self::readiness_panel();
        self::safety_panel();
        self::menu_tools_panel();
        self::inventory_panel();
        self::page_repair_panel();
        self::campaign_panel();
        self::handover_panel();
        self::shell_end();
    }

    private static function shell_start(string $title, string $subtitle = '', array $secondary_nav = array()): void {
        TTOS_Admin_Shell::render_start(array(
            'title' => $title,
            'subtitle' => $subtitle,
            'active' => 'takeaway-os-production',
            'secondary_nav' => $secondary_nav,
            'secondary_nav_label' => 'Sections',
            'secondary_nav_aria_label' => 'Production sections',
        ));
        if (!empty($_GET['ttos_notice'])) echo '<div class="ttos-notice">Saved. Production system updated.</div>';
        $flash = self::consume_flash_notice();
        if (is_array($flash) && !empty($flash['message'])) {
            $class = ($flash['type'] ?? 'success') === 'error' ? 'ttos-bad' : (($flash['type'] ?? 'success') === 'warning' ? 'ttos-warn' : 'ttos-good');
            echo '<div class="ttos-notice ' . esc_attr($class) . '">' . esc_html($flash['message']) . '</div>';
        }
    }

    private static function shell_end(): void { TTOS_Admin_Shell::render_end(); }

    private static function secondary_nav_items(): array {
        return array(
            array('label' => 'Readiness', 'url' => '#readiness', 'active' => false),
            array('label' => 'Safety', 'url' => '#safety', 'active' => false),
            array('label' => 'Menu tools', 'url' => '#menu-tools', 'active' => false),
            array('label' => 'Inventory', 'url' => '#inventory', 'active' => false),
            array('label' => 'Pages', 'url' => '#page-repair', 'active' => false),
            array('label' => 'Campaigns', 'url' => '#campaigns', 'active' => false),
            array('label' => 'Handover', 'url' => '#handover', 'active' => false),
        );
    }

    private static function readiness_panel(): void {
        $items = self::readiness_items();
        $done = 0;
        foreach ($items as $item) if (!empty($item['ok'])) $done++;
        $pct = $items ? round(($done / count($items)) * 100) : 0;
        echo '<section id="readiness" class="ttos-card"><h2>Production readiness</h2><p class="ttos-muted">A practical handover view. This does not replace Codex/browser testing, but it catches the common setup holes.</p>';
        echo '<div class="ttos-big-progress"><span style="width:' . esc_attr((string) $pct) . '%"></span></div><p><strong>' . esc_html((string) $pct) . '% ready</strong> · ' . esc_html((string) $done) . ' of ' . esc_html((string) count($items)) . ' checks passing.</p>';
        echo '<table class="ttos-table"><thead><tr><th>Area</th><th>Status</th><th>Next action</th></tr></thead><tbody>';
        foreach ($items as $item) {
            echo '<tr><td><strong>' . esc_html($item['label']) . '</strong></td><td>' . (!empty($item['ok']) ? '<span class="ttos-good">Ready</span>' : '<span class="ttos-warn">Needs work</span>') . '</td><td>' . esc_html($item['hint']) . '</td></tr>';
        }
        echo '</tbody></table></section>';
    }

    private static function readiness_items(): array {
        $modules = TTOS_Settings::modules();
        $menu_count = post_type_exists('product') ? (int) wp_count_posts('product')->publish : 0;
        $enabled_modules = count(array_filter($modules));
        return array(
            array('label' => 'WooCommerce engine', 'ok' => class_exists('WooCommerce'), 'hint' => 'Install/activate WooCommerce from Launchpad.'),
            array('label' => 'Required pages', 'ok' => class_exists('TTOS_Hardening') && TTOS_Hardening::check_summary()['critical'] === 0, 'hint' => 'Run safe page repair if old site content is still present.'),
            array('label' => 'Menu products', 'ok' => $menu_count >= 5, 'hint' => 'Import a CSV or create the starter menu.'),
            array('label' => 'Payments', 'ok' => self::payments_ready(), 'hint' => 'Enable Stripe/WooPayments/cash gateway before live orders.'),
            array('label' => 'Module lock', 'ok' => (string) get_option('ttos_module_lock_hash', '') !== '', 'hint' => 'Set a paid module lock before client handover.'),
            array('label' => 'Add-on plan', 'ok' => $enabled_modules > 0, 'hint' => 'Enable only the paid modules included in the client package.'),
            array('label' => 'Emergency pause configured', 'ok' => self::get('pause_message') !== '', 'hint' => 'Keep the pause message ready for Friday-night panic mode.'),
        );
    }

    private static function payments_ready(): bool {
        if (!class_exists('WC_Payment_Gateways')) return false;
        foreach (WC_Payment_Gateways::instance()->payment_gateways() as $gateway) {
            if (isset($gateway->enabled) && $gateway->enabled === 'yes') return true;
        }
        return false;
    }

    private static function safety_panel(): void {
        $s = self::get();
        echo '<section id="safety" class="ttos-card"><h2>Emergency ordering pause</h2><p class="ttos-muted">A safe kill-switch for testing, holidays, outages or kitchen chaos. When enabled, add-to-cart and checkout are blocked.</p><form method="post">';
        wp_nonce_field('ttos_production_save_settings');
        echo '<input type="hidden" name="ttos_action" value="production_save_settings">';
        echo '<label class="ttos-check"><input type="checkbox" name="production[pause_enabled]" value="1" ' . checked($s['pause_enabled'], '1', false) . '> Pause online ordering</label>';
        echo '<label>Pause message<textarea name="production[pause_message]" rows="3">' . esc_textarea($s['pause_message']) . '</textarea></label>';
        echo '<div class="ttos-grid ttos-grid-2"><label class="ttos-check"><input type="checkbox" name="production[inventory_auto_reset]" value="1" ' . checked($s['inventory_auto_reset'], '1', false) . '> Auto-reset sold-out items daily</label>';
        self::field('Low stock threshold', 'production[low_stock_threshold]', $s['low_stock_threshold'], 'number');
        echo '</div>';
        self::field('Campaign from name', 'production[campaign_from_name]', $s['campaign_from_name']);
        self::field('Campaign reply-to email', 'production[campaign_reply_to]', $s['campaign_reply_to'], 'email');
        echo '<div class="ttos-grid ttos-grid-4">';
        echo '<label class="ttos-check"><input type="checkbox" name="production[retention_enabled]" value="1" ' . checked($s['retention_enabled'], '1', false) . '> Send win-back emails after completed orders</label>';
        self::field('Retention delay days', 'production[retention_delay_days]', $s['retention_delay_days'], 'number');
        self::select('Retention coupon type', 'production[retention_coupon_type]', (string) $s['retention_coupon_type'], array('fixed_cart' => 'Fixed cart discount', 'percent' => 'Percentage discount'));
        self::field('Retention coupon amount', 'production[retention_coupon_amount]', $s['retention_coupon_amount'], 'number');
        self::field('Retention coupon expiry days', 'production[retention_coupon_expiry_days]', $s['retention_coupon_expiry_days'], 'number');
        echo '</div>';
        echo '<label>Handover notes<textarea name="production[handover_notes]" rows="4">' . esc_textarea($s['handover_notes']) . '</textarea></label><button class="ttos-button">Save production settings</button></form></section>';
    }

    private static function menu_tools_panel(): void {
        $pack = self::starter_pack_key();
        $pack_label = self::starter_pack_label($pack);
        echo '<section id="menu-tools" class="ttos-card"><h2>Menu import/export and starter profiles</h2><p class="ttos-muted">Fast setup for new restaurants: import CSV, export current menu, or create a realistic starter menu using the saved cuisine type where possible.</p>';
        echo '<div class="ttos-grid ttos-grid-3"><div><h3>Starter menu</h3><p class="ttos-muted">Creates a simple ' . esc_html($pack_label) . ' starter pack with safe generated markers. Existing client items are left alone.</p><form method="post">';
        wp_nonce_field('ttos_production_apply_starter_menu');
        echo '<input type="hidden" name="ttos_action" value="production_apply_starter_menu"><button class="ttos-button">Create starter menu</button></form></div>';
        echo '<div><h3>Import CSV</h3><p class="ttos-muted">Headers: <code>product_id</code> (optional), <code>name</code>, <code>category</code>, <code>price</code>, <code>sale_price</code>, <code>description</code>, <code>allergens</code>, <code>badges</code>, <code>sold_out</code>, <code>hidden</code>, and either <code>option_groups_json</code> or <code>option_preset</code>.</p><form method="post" enctype="multipart/form-data">';
        wp_nonce_field('ttos_production_import_menu');
        echo '<input type="hidden" name="ttos_action" value="production_import_menu"><input type="file" name="menu_csv" accept=".csv,text/csv"><button class="ttos-button">Import menu CSV</button></form></div>';
        echo '<div><h3>Export menu</h3><p class="ttos-muted">Downloads a CSV you can edit in a spreadsheet and re-import.</p><p><a class="ttos-button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=ttos_export_menu_csv'), 'ttos_export_menu_csv')) . '">Export current menu</a></p></div></div></section>';
    }

    private static function inventory_panel(): void {
        $threshold = max(1, absint(self::get('low_stock_threshold')));
        echo '<section id="inventory" class="ttos-card"><h2>Inventory Lite autopilot</h2><p class="ttos-muted">This is availability control, not a full EPOS stock system. It resets “sold out today” items that are not stock-managed, and highlights low stock products.</p>';
        echo '<form method="post">'; wp_nonce_field('ttos_production_reset_sold_out'); echo '<input type="hidden" name="ttos_action" value="production_reset_sold_out"><button class="ttos-button">Reset non-stock sold-out items now</button></form>';
        echo '<h3>Low stock watchlist</h3><table class="ttos-table"><thead><tr><th>Item</th><th>Stock</th><th>Status</th></tr></thead><tbody>';
        $found = false;
        if (post_type_exists('product')) {
            $q = new WP_Query(array('post_type' => 'product', 'post_status' => array('publish','draft'), 'posts_per_page' => 100, 'meta_query' => array(array('key' => '_manage_stock', 'value' => 'yes'))));
            while ($q->have_posts()) { $q->the_post(); $id = get_the_ID(); $stock = (int) get_post_meta($id, '_stock', true); if ($stock > $threshold) continue; $found = true;
                echo '<tr><td><strong>' . esc_html(get_the_title()) . '</strong></td><td>' . esc_html((string) $stock) . '</td><td>' . ($stock < 1 ? '<span class="ttos-bad">Sold out</span>' : '<span class="ttos-warn">Low</span>') . '</td></tr>';
            }
            wp_reset_postdata();
        }
        if (!$found) echo '<tr><td colspan="3"><span class="ttos-muted">No low-stock items at the current threshold.</span></td></tr>';
        echo '</tbody></table></section>';
    }

    private static function page_repair_panel(): void {
        echo '<section id="page-repair" class="ttos-card"><h2>Safe page repair</h2><p class="ttos-muted">If old client pages exist, choose how Takeaway OS should handle them. “Fresh if unsafe” is safest; “replace” is for controlled rebuilds only.</p>';
        echo '<table class="ttos-table"><thead><tr><th>Page</th><th>Status</th><th>Assigned</th></tr></thead><tbody>';
        foreach (TTOS_Page_Manager::specs() as $key => $spec) {
            $st = TTOS_Page_Manager::status($key);
            $class = $st['state'] === 'ready' ? 'ttos-good' : ($st['state'] === 'missing' ? 'ttos-bad' : 'ttos-warn');
            echo '<tr><td><strong>' . esc_html($spec['label']) . '</strong></td><td><span class="' . esc_attr($class) . '">' . esc_html($st['message']) . '</span></td><td>' . (!empty($st['id']) ? '<a href="' . esc_url(get_edit_post_link((int) $st['id'])) . '">#' . esc_html((string) $st['id']) . '</a>' : '—') . '</td></tr>';
        }
        echo '</tbody></table><form method="post" class="ttos-inline-form">';
        wp_nonce_field('ttos_production_page_repair');
        echo '<input type="hidden" name="ttos_action" value="production_page_repair"><select name="repair_mode"><option value="fresh_if_unsafe">Fresh if unsafe</option><option value="append">Append Takeaway content</option><option value="replace">Replace old page content</option></select><button class="ttos-button">Repair pages</button></form></section>';
    }

    private static function campaign_panel(): void {
        $campaigns = get_option('ttos_customer_campaigns', array());
        if (!is_array($campaigns)) $campaigns = array();
        echo '<section id="campaigns" class="ttos-card"><h2>Campaign send/export</h2><p class="ttos-muted">The CRM creates segment coupons. This panel can export contact lists or send a simple coupon email to opted-in customers.</p>';
        self::retention_jobs_table();
        echo '<table class="ttos-table"><thead><tr><th>Campaign</th><th>Coupon</th><th>Contacts</th><th>Actions</th></tr></thead><tbody>';
        foreach (array_reverse($campaigns) as $campaign) {
            $id = sanitize_key($campaign['id'] ?? '');
            echo '<tr><td><strong>' . esc_html($campaign['name'] ?? 'Campaign') . '</strong><br><small>' . esc_html($campaign['segment'] ?? '') . '</small></td><td><code>' . esc_html($campaign['code'] ?? '') . '</code></td><td>' . esc_html((string) ($campaign['email_count'] ?? 0)) . '</td><td><div class="ttos-table-actions"><form method="post">';
            wp_nonce_field('ttos_production_send_campaign');
            echo '<input type="hidden" name="ttos_action" value="production_send_campaign"><input type="hidden" name="campaign_id" value="' . esc_attr($id) . '"><button class="ttos-mini">Send email</button></form><a class="ttos-mini" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=ttos_export_campaign_contacts&campaign_id=' . rawurlencode($id)), 'ttos_export_campaign_contacts_' . $id)) . '">Export contacts</a></div></td></tr>';
        }
        if (!$campaigns) echo '<tr><td colspan="4"><span class="ttos-muted">No campaigns yet. Create one under Customers.</span></td></tr>';
        echo '</tbody></table></section>';
    }

    private static function handover_panel(): void {
        $modules = TTOS_Settings::modules();
        echo '<section id="handover" class="ttos-card"><h2>Client handover report</h2><p class="ttos-muted">Copy this into your client notes before handover. It shows what is enabled, what is locked, and what still needs live service credentials.</p><textarea readonly rows="12">';
        echo esc_textarea(self::handover_text($modules));
        echo '</textarea></section>';
    }

    private static function handover_text(array $modules): string {
        $enabled = array_keys(array_filter($modules));
        $locked = (string) get_option('ttos_module_lock_hash', '') !== '' ? 'Yes' : 'No';
        $lines = array(
            'Takeaway Theme / Takeaway OS handover',
            'Site: ' . home_url('/'),
            'Restaurant: ' . (string) TTOS_Settings::get('business', 'restaurant_name'),
            'Module lock set: ' . $locked,
            'Enabled paid modules: ' . ($enabled ? implode(', ', $enabled) : 'None'),
            'Ordering paused: ' . (self::get('pause_enabled') === '1' ? 'Yes' : 'No'),
            'WooCommerce active: ' . (class_exists('WooCommerce') ? 'Yes' : 'No'),
            'Payment gateway enabled: ' . (self::payments_ready() ? 'Yes' : 'No'),
            'Notes:',
            (string) self::get('handover_notes'),
        );
        return implode("\n", $lines);
    }

    private static function field(string $label, string $name, $value = '', string $type = 'text'): void {
        echo '<label>' . esc_html($label) . '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '"></label>';
    }

    private static function preset_groups(string $preset): string {
        $presets = array(
            'kebab' => array(
                array('name'=>'Choose sauce','type'=>'multiple','required'=>false,'min'=>0,'max'=>3,'options'=>array(array('label'=>'Garlic mayo','price'=>'0'),array('label'=>'Chilli sauce','price'=>'0'),array('label'=>'Burger sauce','price'=>'0'))),
                array('name'=>'Salad','type'=>'single','required'=>true,'min'=>1,'max'=>1,'options'=>array(array('label'=>'All salad','price'=>'0','default'=>true),array('label'=>'No salad','price'=>'0'),array('label'=>'No onion','price'=>'0'))),
            ),
            'pizza' => array(
                array('name'=>'Crust','type'=>'single','required'=>true,'min'=>1,'max'=>1,'options'=>array(array('label'=>'Thin crust','price'=>'0','default'=>true),array('label'=>'Deep pan','price'=>'1.50'),array('label'=>'Stuffed crust','price'=>'2.50'))),
                array('name'=>'Extra toppings','type'=>'multiple','required'=>false,'min'=>0,'max'=>6,'options'=>array(array('label'=>'Extra cheese','price'=>'1.50'),array('label'=>'Pepperoni','price'=>'1.50'),array('label'=>'Mushrooms','price'=>'1.00'),array('label'=>'Jalapeños','price'=>'1.00'))),
            ),
            'burger' => array(
                array('name'=>'Make it a meal','type'=>'single','required'=>false,'min'=>0,'max'=>1,'options'=>array(array('label'=>'Burger only','price'=>'0','default'=>true),array('label'=>'Meal with fries + drink','price'=>'3.50'))),
                array('name'=>'Extras','type'=>'multiple','required'=>false,'min'=>0,'max'=>4,'options'=>array(array('label'=>'Cheese','price'=>'1.00'),array('label'=>'Bacon','price'=>'1.50'),array('label'=>'Hash brown','price'=>'1.00'))),
            ),
        );
        return wp_json_encode($presets[$preset] ?? array());
    }

    public static function starter_pack_key(): string {
        $raw = strtolower(trim((string) TTOS_Settings::get('business', 'cuisine')));
        if ($raw === '') {
            return 'generic_takeaway';
        }

        $map = array(
            'pizza' => array('pizza', 'pizzeria'),
            'kebab' => array('kebab', 'kebabs', 'turkish', 'grill'),
            'fried_chicken_burgers' => array('fried_chicken_burgers', 'fried chicken', 'chicken', 'burgers', 'burger', 'fried chicken & burgers', 'fried chicken and burgers'),
            'indian' => array('indian', 'curry'),
            'chinese' => array('chinese', 'asian'),
            'fish_chips' => array('fish_chips', 'fish and chips', 'fish & chips', 'chip shop'),
            'dessert' => array('dessert', 'desserts', 'ice cream', 'waffles'),
            'generic_takeaway' => array('generic_takeaway', 'takeaway', 'generic'),
        );

        foreach ($map as $pack => $needles) {
            foreach ($needles as $needle) {
                if ($raw === $needle || strpos($raw, $needle) !== false) {
                    return $pack;
                }
            }
        }

        return 'generic_takeaway';
    }

    private static function starter_pack_label(string $pack): string {
        $labels = array(
            'pizza' => 'Pizza',
            'kebab' => 'Kebab',
            'fried_chicken_burgers' => 'Fried Chicken & Burgers',
            'indian' => 'Indian',
            'chinese' => 'Chinese',
            'fish_chips' => 'Fish & Chips',
            'dessert' => 'Dessert',
            'generic_takeaway' => 'Generic Takeaway',
        );
        return $labels[$pack] ?? $labels['generic_takeaway'];
    }

    public static function starter_pack_categories(string $pack = ''): array {
        $pack = $pack !== '' ? $pack : self::starter_pack_key();
        $packs = array(
            'pizza' => array('Pizza', 'Sides', 'Drinks', 'Desserts', 'Meal Deals'),
            'kebab' => array('Kebabs', 'Wraps', 'Sides', 'Drinks', 'Meal Deals'),
            'fried_chicken_burgers' => array('Chicken', 'Burgers', 'Sides', 'Drinks', 'Meal Deals'),
            'indian' => array('Curries', 'Rice', 'Breads', 'Sides', 'Drinks'),
            'chinese' => array('Mains', 'Rice & Noodles', 'Starters', 'Sides', 'Drinks'),
            'fish_chips' => array('Fish', 'Chips', 'Pies', 'Sides', 'Drinks'),
            'dessert' => array('Waffles', 'Cakes', 'Ice Cream', 'Shakes', 'Drinks'),
            'generic_takeaway' => array('Burgers', 'Kebabs', 'Pizza', 'Chicken', 'Sides', 'Drinks', 'Desserts', 'Meal Deals'),
        );
        return $packs[$pack] ?? $packs['generic_takeaway'];
    }

    private static function starter_pack_items(string $pack = ''): array {
        $pack = $pack !== '' ? $pack : self::starter_pack_key();

        $packs = array(
            'pizza' => array(
                array('name' => 'Margherita Pizza', 'category' => 'Pizza', 'price' => '8.99', 'description' => 'Tomato, mozzarella and oregano.', 'allergens' => 'gluten,milk', 'badges' => 'Vegetarian', 'preset' => 'pizza', 'dietary' => array('vegetarian')),
                array('name' => 'Pepperoni Pizza', 'category' => 'Pizza', 'price' => '10.99', 'description' => 'Pepperoni, mozzarella and tomato sauce.', 'allergens' => 'gluten,milk', 'badges' => 'Popular', 'preset' => 'pizza', 'dietary' => array('popular')),
                array('name' => 'Garlic Bread', 'category' => 'Sides', 'price' => '4.50', 'description' => 'Fresh baked garlic bread.', 'allergens' => 'gluten,milk', 'badges' => '', 'preset' => '', 'dietary' => array('vegetarian')),
                array('name' => 'Can of Drink', 'category' => 'Drinks', 'price' => '1.50', 'description' => 'Choose from available cans.', 'allergens' => '', 'badges' => '', 'preset' => '', 'dietary' => array()),
            ),
            'kebab' => array(
                array('name' => 'Mixed Doner Kebab', 'category' => 'Kebabs', 'price' => '9.50', 'description' => 'Lamb doner with salad and sauce.', 'allergens' => 'gluten', 'badges' => 'Popular, Halal', 'preset' => 'kebab', 'dietary' => array('halal', 'popular')),
                array('name' => 'Chicken Shish Kebab', 'category' => 'Kebabs', 'price' => '10.95', 'description' => 'Chargrilled chicken shish with salad.', 'allergens' => '', 'badges' => 'Halal', 'preset' => 'kebab', 'dietary' => array('halal')),
                array('name' => 'Lamb Doner Wrap', 'category' => 'Wraps', 'price' => '8.50', 'description' => 'Doner meat wrapped with salad and sauce.', 'allergens' => 'gluten', 'badges' => '', 'preset' => 'kebab', 'dietary' => array()),
                array('name' => 'Chips', 'category' => 'Sides', 'price' => '3.00', 'description' => 'Fresh hot chips.', 'allergens' => '', 'badges' => 'Vegetarian', 'preset' => '', 'dietary' => array('vegetarian')),
            ),
            'fried_chicken_burgers' => array(
                array('name' => 'Chicken Fillet Burger', 'category' => 'Burgers', 'price' => '7.50', 'description' => 'Crispy chicken fillet with mayo.', 'allergens' => 'gluten,egg', 'badges' => 'Popular', 'preset' => 'burger', 'dietary' => array('popular')),
                array('name' => 'Classic Burger', 'category' => 'Burgers', 'price' => '6.95', 'description' => 'Beef burger with lettuce and house sauce.', 'allergens' => 'gluten', 'badges' => '', 'preset' => 'burger', 'dietary' => array()),
                array('name' => 'Chicken Strips Meal', 'category' => 'Chicken', 'price' => '8.95', 'description' => 'Crispy chicken strips with fries and dip.', 'allergens' => 'gluten', 'badges' => 'Popular', 'preset' => '', 'dietary' => array('popular')),
                array('name' => 'Can of Drink', 'category' => 'Drinks', 'price' => '1.50', 'description' => 'Choose from available cans.', 'allergens' => '', 'badges' => '', 'preset' => '', 'dietary' => array()),
            ),
            'indian' => array(
                array('name' => 'Chicken Tikka Masala', 'category' => 'Curries', 'price' => '9.95', 'description' => 'Creamy tikka masala sauce with chicken tikka pieces.', 'allergens' => 'milk', 'badges' => 'Popular', 'preset' => '', 'dietary' => array('popular')),
                array('name' => 'Chicken Korma', 'category' => 'Curries', 'price' => '9.50', 'description' => 'Mild creamy curry with coconut notes.', 'allergens' => 'milk,nuts', 'badges' => '', 'preset' => '', 'dietary' => array()),
                array('name' => 'Pilau Rice', 'category' => 'Rice', 'price' => '3.50', 'description' => 'Fragrant basmati rice.', 'allergens' => '', 'badges' => 'Vegetarian', 'preset' => '', 'dietary' => array('vegetarian')),
                array('name' => 'Plain Naan', 'category' => 'Breads', 'price' => '2.95', 'description' => 'Fresh baked naan bread.', 'allergens' => 'gluten,milk', 'badges' => 'Vegetarian', 'preset' => '', 'dietary' => array('vegetarian')),
            ),
            'chinese' => array(
                array('name' => 'Chicken Chow Mein', 'category' => 'Rice & Noodles', 'price' => '8.95', 'description' => 'Soft noodles with chicken and vegetables.', 'allergens' => 'gluten,soya', 'badges' => 'Popular', 'preset' => '', 'dietary' => array('popular')),
                array('name' => 'Sweet & Sour Chicken', 'category' => 'Mains', 'price' => '9.50', 'description' => 'Chicken in a sweet and sour sauce.', 'allergens' => 'gluten', 'badges' => '', 'preset' => '', 'dietary' => array()),
                array('name' => 'Egg Fried Rice', 'category' => 'Rice & Noodles', 'price' => '3.95', 'description' => 'Classic egg fried rice.', 'allergens' => 'egg,soya', 'badges' => '', 'preset' => '', 'dietary' => array()),
                array('name' => 'Spring Rolls', 'category' => 'Starters', 'price' => '4.25', 'description' => 'Crispy vegetable spring rolls.', 'allergens' => 'gluten', 'badges' => 'Vegetarian', 'preset' => '', 'dietary' => array('vegetarian')),
            ),
            'fish_chips' => array(
                array('name' => 'Cod & Chips', 'category' => 'Fish', 'price' => '10.95', 'description' => 'Fresh battered cod with chips.', 'allergens' => 'gluten,fish', 'badges' => 'Popular', 'preset' => '', 'dietary' => array('popular')),
                array('name' => 'Large Chips', 'category' => 'Chips', 'price' => '3.80', 'description' => 'Freshly cooked chip shop chips.', 'allergens' => '', 'badges' => 'Vegetarian', 'preset' => '', 'dietary' => array('vegetarian')),
                array('name' => 'Steak Pie', 'category' => 'Pies', 'price' => '4.95', 'description' => 'Traditional steak pie.', 'allergens' => 'gluten', 'badges' => '', 'preset' => '', 'dietary' => array()),
                array('name' => 'Curry Sauce', 'category' => 'Sides', 'price' => '1.50', 'description' => 'Classic chip shop curry sauce.', 'allergens' => '', 'badges' => 'Popular', 'preset' => '', 'dietary' => array('popular')),
            ),
            'dessert' => array(
                array('name' => 'Strawberry Cheesecake', 'category' => 'Cakes', 'price' => '4.95', 'description' => 'Creamy cheesecake with strawberry topping.', 'allergens' => 'gluten,milk,egg', 'badges' => 'Popular', 'preset' => '', 'dietary' => array('popular')),
                array('name' => 'Nutella Waffle', 'category' => 'Waffles', 'price' => '6.50', 'description' => 'Warm waffle topped with Nutella.', 'allergens' => 'gluten,milk,egg', 'badges' => 'Popular', 'preset' => '', 'dietary' => array('popular')),
                array('name' => 'Vanilla Gelato', 'category' => 'Ice Cream', 'price' => '3.95', 'description' => 'Smooth vanilla gelato.', 'allergens' => 'milk', 'badges' => 'Vegetarian', 'preset' => '', 'dietary' => array('vegetarian')),
                array('name' => 'Oreo Milkshake', 'category' => 'Shakes', 'price' => '4.95', 'description' => 'Thick vanilla shake with Oreo crumb.', 'allergens' => 'milk,gluten', 'badges' => '', 'preset' => '', 'dietary' => array()),
            ),
            'generic_takeaway' => array(
                array('name' => 'Mixed Doner Kebab', 'category' => 'Kebabs', 'price' => '9.50', 'description' => 'Lamb doner with salad and sauce.', 'allergens' => 'gluten', 'badges' => 'Popular, Halal', 'preset' => 'kebab', 'dietary' => array('halal', 'popular')),
                array('name' => 'Chicken Shish Kebab', 'category' => 'Kebabs', 'price' => '10.95', 'description' => 'Chargrilled chicken shish with salad.', 'allergens' => '', 'badges' => 'Halal', 'preset' => 'kebab', 'dietary' => array('halal')),
                array('name' => 'Margherita Pizza', 'category' => 'Pizza', 'price' => '8.99', 'description' => 'Tomato, mozzarella and oregano.', 'allergens' => 'gluten,milk', 'badges' => 'Vegetarian', 'preset' => 'pizza', 'dietary' => array('vegetarian')),
                array('name' => 'Pepperoni Pizza', 'category' => 'Pizza', 'price' => '10.99', 'description' => 'Pepperoni, mozzarella and tomato sauce.', 'allergens' => 'gluten,milk', 'badges' => 'Popular', 'preset' => 'pizza', 'dietary' => array('popular')),
                array('name' => 'Classic Burger', 'category' => 'Burgers', 'price' => '6.95', 'description' => 'Beef burger with lettuce and house sauce.', 'allergens' => 'gluten', 'badges' => 'Popular', 'preset' => 'burger', 'dietary' => array('popular')),
                array('name' => 'Chicken Fillet Burger', 'category' => 'Burgers', 'price' => '7.50', 'description' => 'Crispy chicken fillet with mayo.', 'allergens' => 'gluten,egg', 'badges' => '', 'preset' => 'burger', 'dietary' => array()),
                array('name' => 'Chips', 'category' => 'Sides', 'price' => '3.00', 'description' => 'Fresh hot chips.', 'allergens' => '', 'badges' => 'Vegetarian', 'preset' => '', 'dietary' => array('vegetarian')),
                array('name' => 'Onion Rings', 'category' => 'Sides', 'price' => '3.50', 'description' => 'Crispy battered onion rings.', 'allergens' => 'gluten', 'badges' => 'Vegetarian', 'preset' => '', 'dietary' => array('vegetarian')),
                array('name' => 'Can of Drink', 'category' => 'Drinks', 'price' => '1.50', 'description' => 'Choose from available cans.', 'allergens' => '', 'badges' => '', 'preset' => '', 'dietary' => array()),
            ),
        );

        return $packs[$pack] ?? $packs['generic_takeaway'];
    }

    private static function mark_generated_term(int $term_id, string $pack): void {
        update_term_meta($term_id, '_ttos_generated_by', 'cuisine_starter_pack');
        update_term_meta($term_id, '_ttos_cuisine_pack', $pack);
        update_term_meta($term_id, '_ttos_generated_at', current_time('mysql'));
    }

    private static function ensure_pack_categories(string $pack): void {
        if (!taxonomy_exists('product_cat')) {
            return;
        }
        foreach (self::starter_pack_categories($pack) as $name) {
            $term = term_exists($name, 'product_cat');
            if (!$term) {
                $term = wp_insert_term($name, 'product_cat');
            }
            if (!is_wp_error($term) && $term) {
                $term_id = is_array($term) ? (int) $term['term_id'] : (int) $term;
                self::mark_generated_term($term_id, $pack);
            }
        }
    }

    private static function generated_product_id_by_title(string $title, string $pack): int {
        $posts = get_posts(array(
            'post_type' => 'product',
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array('key' => '_ttos_generated_by', 'value' => 'cuisine_starter_pack'),
            ),
            'fields' => 'ids',
        ));
        foreach ((array) $posts as $post_id) {
            if (strcasecmp((string) get_the_title($post_id), $title) !== 0) {
                continue;
            }
            $saved_pack = (string) get_post_meta((int) $post_id, '_ttos_cuisine_pack', true);
            if ($saved_pack === '' || $saved_pack === $pack) {
                return (int) $post_id;
            }
        }
        return 0;
    }

    private static function client_product_exists_with_title(string $title): bool {
        $posts = get_posts(array(
            'post_type' => 'product',
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));
        foreach ((array) $posts as $post_id) {
            if (strcasecmp((string) get_the_title($post_id), $title) !== 0) {
                continue;
            }
            if ((string) get_post_meta((int) $post_id, '_ttos_generated_by', true) !== 'cuisine_starter_pack') {
                return true;
            }
        }
        return false;
    }

    private static function mark_generated_product(int $product_id, string $pack): void {
        update_post_meta($product_id, '_ttos_generated_by', 'cuisine_starter_pack');
        update_post_meta($product_id, '_ttos_cuisine_pack', $pack);
        update_post_meta($product_id, '_ttos_generated_at', current_time('mysql'));
    }

    public static function apply_starter_menu(): void {
        if (!TTOS_WooCommerce::active()) return;

        $pack = self::starter_pack_key();
        self::ensure_pack_categories($pack);

        foreach (self::starter_pack_items($pack) as $i => $item) {
            $product_id = self::generated_product_id_by_title($item['name'], $pack);
            if (!$product_id && self::client_product_exists_with_title($item['name'])) {
                continue;
            }

            $product_id = TTOS_WooCommerce::create_or_update_product(array(
                'product_id' => $product_id,
                'name' => $item['name'],
                'category' => $item['category'],
                'price' => $item['price'],
                'description' => $item['description'],
                'allergens' => $item['allergens'],
                'badges' => $item['badges'],
                'option_groups' => self::preset_groups($item['preset']),
                'dietary' => $item['dietary'],
                'sort_order' => (string) ($i + 1),
            ));
            if ($product_id) {
                self::mark_generated_product($product_id, $pack);
            }
        }
        update_option('ttos_starter_products_created', true);
        update_option('ttos_starter_products_pack', $pack, false);
    }

    private static function import_menu_csv() {
        if (!TTOS_WooCommerce::active()) {
            return new WP_Error('ttos_menu_import_wc', __('WooCommerce must be active before importing menu items.', 'takeaway-os'));
        }
        $upload = TTOS_Hardening::stash_uploaded_file($_FILES['menu_csv'] ?? array(), array('csv'), 2 * 1024 * 1024, 'menu-import-');
        if (is_wp_error($upload)) {
            return $upload;
        }

        $summary = array('processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0);
        $errors = array();
        $handle = fopen($upload['path'], 'r');
        if (!$handle) {
            TTOS_Hardening::cleanup_import_file($upload['path']);
            return new WP_Error('ttos_menu_import_open', __('The uploaded CSV could not be opened.', 'takeaway-os'));
        }

        try {
            $headers = fgetcsv($handle);
            if (!$headers) {
                return new WP_Error('ttos_menu_import_headers', __('The CSV is empty or missing its header row.', 'takeaway-os'));
            }
            $headers = array_map(array(__CLASS__, 'normalise_csv_header'), $headers);
            if (!in_array('name', $headers, true)) {
                return new WP_Error('ttos_menu_import_name', __('The CSV must include a "name" column.', 'takeaway-os'));
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (!is_array($row) || self::csv_row_blank($row)) {
                    continue;
                }
                $data = array();
                foreach ($headers as $idx => $key) {
                    if ($key === '') {
                        continue;
                    }
                    $data[$key] = isset($row[$idx]) ? trim((string) $row[$idx]) : '';
                }
                $name = sanitize_text_field((string) ($data['name'] ?? ''));
                if ($name === '') {
                    $summary['skipped']++;
                    continue;
                }

                $option_groups = '[]';
                if (!empty($data['option_groups_json'])) {
                    $option_groups = TTOS_WooCommerce::sanitise_option_groups((string) $data['option_groups_json']);
                    if ($option_groups === '[]' && trim((string) $data['option_groups_json']) !== '[]') {
                        $errors[] = sprintf(__('Row "%s" has invalid option_groups_json and was skipped.', 'takeaway-os'), $name);
                        $summary['skipped']++;
                        continue;
                    }
                } elseif (!empty($data['option_preset'])) {
                    $option_groups = self::preset_groups(sanitize_key((string) $data['option_preset']));
                }

                $product_id = absint($data['product_id'] ?? 0);
                if (!$product_id) {
                    $product_id = self::match_existing_product($name, (string) ($data['category'] ?? ''));
                }
                $saved_id = TTOS_WooCommerce::create_or_update_product(array(
                    'product_id'    => $product_id,
                    'name'          => $name,
                    'category'      => sanitize_text_field((string) ($data['category'] ?? '')),
                    'price'         => $data['price'] ?? '0',
                    'sale_price'    => $data['sale_price'] ?? '',
                    'description'   => $data['description'] ?? '',
                    'allergens'     => $data['allergens'] ?? '',
                    'badges'        => $data['badges'] ?? '',
                    'sold_out'      => self::csv_bool($data['sold_out'] ?? ''),
                    'hidden'        => self::csv_bool($data['hidden'] ?? ''),
                    'option_groups' => $option_groups,
                ));
                if (!$saved_id) {
                    $errors[] = sprintf(__('Row "%s" could not be imported.', 'takeaway-os'), $name);
                    $summary['skipped']++;
                    continue;
                }
                $summary['processed']++;
                if ($product_id > 0) {
                    $summary['updated']++;
                } else {
                    $summary['created']++;
                }
            }
        } finally {
            fclose($handle);
            TTOS_Hardening::cleanup_import_file($upload['path']);
        }

        if (!$summary['processed']) {
            $message = $errors ? implode(' ', array_slice($errors, 0, 2)) : __('No menu rows were imported from that CSV.', 'takeaway-os');
            return new WP_Error('ttos_menu_import_empty', $message);
        }
        if ($errors) {
            $summary['warnings'] = $errors;
            self::log('import', 'Menu import completed with warnings', array('summary' => $summary, 'errors' => array_slice($errors, 0, 5)));
        } else {
            self::log('import', 'Menu import completed', $summary);
        }
        return $summary;
    }

    public static function export_menu_csv(): void {
        if (!current_user_can('ttos_manage_menu') || !check_admin_referer('ttos_export_menu_csv')) wp_die('Not allowed.');
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=takeaway-menu-' . gmdate('Y-m-d') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, array('product_id','name','category','price','sale_price','description','allergens','badges','sold_out','hidden','option_groups_json'));
        if (post_type_exists('product')) {
            $q = new WP_Query(array('post_type'=>'product','post_status'=>array('publish','draft'),'posts_per_page'=>-1,'orderby'=>'menu_order title','order'=>'ASC'));
            while ($q->have_posts()) { $q->the_post(); $id = get_the_ID();
                $terms = wp_get_post_terms($id, 'product_cat', array('fields'=>'names'));
                fputcsv($out, array($id, get_the_title(), !is_wp_error($terms) && $terms ? $terms[0] : '', get_post_meta($id,'_regular_price',true), get_post_meta($id,'_sale_price',true), wp_strip_all_tags(get_post_field('post_content',$id)), get_post_meta($id,'_ttos_allergens',true), get_post_meta($id,'_ttos_badges',true), get_post_meta($id,'_stock_status',true)==='outofstock' ? 'yes' : 'no', get_post_status($id)==='draft' ? 'yes' : 'no', get_post_meta($id,'_ttos_option_groups',true)));
            }
            wp_reset_postdata();
        }
        fclose($out); exit;
    }

    public static function inventory_daily_reset(bool $manual = false): void {
        if (!$manual && self::get('inventory_auto_reset') !== '1') return;
        if (!post_type_exists('product')) return;
        $q = new WP_Query(array('post_type'=>'product','post_status'=>array('publish','draft'),'posts_per_page'=>-1,'meta_query'=>array(array('key'=>'_stock_status','value'=>'outofstock'))));
        while ($q->have_posts()) { $q->the_post(); $id = get_the_ID();
            if (get_post_meta($id, '_manage_stock', true) === 'yes') continue;
            update_post_meta($id, '_stock_status', 'instock');
        }
        wp_reset_postdata();
        self::log('inventory', 'Non-stock sold-out items reset', array('manual'=>$manual));
    }

    private static function send_campaign(string $campaign_id): array {
        $campaigns = get_option('ttos_customer_campaigns', array());
        if (!is_array($campaigns)) return array('sent' => 0, 'skipped' => 0, 'invalid' => 0);
        $campaign = null;
        foreach ($campaigns as $item) if (($item['id'] ?? '') === $campaign_id) $campaign = $item;
        if (!$campaign) return array('sent' => 0, 'skipped' => 0, 'invalid' => 0);
        $profiles = get_option('ttos_customer_profiles', array());
        if (!is_array($profiles)) $profiles = array();
        $emails = array_slice(array_unique(array_map('sanitize_email', (array) ($campaign['emails'] ?? array()))), 0, 100);
        $sent = 0;
        $skipped = 0;
        $invalid = 0;
        $from = sanitize_text_field(self::get('campaign_from_name')) ?: get_bloginfo('name');
        $reply = sanitize_email(self::get('campaign_reply_to')) ?: get_option('admin_email');
        $headers = array('Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $from . ' <' . $reply . '>');
        foreach ($emails as $email) {
            if (!$email || !is_email($email)) {
                $invalid++;
                continue;
            }
            $profile = $profiles[strtolower($email)] ?? array();
            if (($profile['marketing_ok'] ?? '0') !== '1') {
                $skipped++;
                continue;
            }
            $unsubscribe = self::campaign_unsubscribe_url($email);
            $subject = ($campaign['name'] ?? 'Direct order offer') . ' - ' . get_bloginfo('name');
            $body = "Hi,\n\nHere is your direct-order offer from " . get_bloginfo('name') . ".\n\nCoupon code: " . ($campaign['code'] ?? '') . "\n\nOrder here: " . home_url('/menu/') . "\n\nIf you no longer want direct-order offers, unsubscribe here: " . $unsubscribe . "\n\nThank you for ordering direct.";
            if (wp_mail($email, $subject, $body, $headers)) {
                $sent++;
            } else {
                $invalid++;
            }
        }
        self::log('campaign', 'Campaign email processed', array('campaign'=>$campaign_id,'sent'=>$sent,'skipped'=>$skipped,'invalid'=>$invalid));
        return array('sent' => $sent, 'skipped' => $skipped, 'invalid' => $invalid);
    }

    public static function schedule_retention_after_completed_order(int $order_id): void {
        if (self::get('retention_enabled') !== '1' || !function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $email = strtolower(sanitize_email((string) $order->get_billing_email()));
        if ($email === '' || !is_email($email)) {
            self::log('retention', 'Skipped retention scheduling because the order email was missing.', array('order_id' => $order_id));
            return;
        }
        if (!self::customer_marketing_opt_in($email)) {
            self::clear_retention_job($email);
            self::log('retention', 'Skipped retention scheduling because marketing permission is not recorded.', array('order_id' => $order_id, 'email' => $email));
            return;
        }

        $delay_days = max(1, absint(self::get('retention_delay_days')));
        $send_at = time() + ($delay_days * DAY_IN_SECONDS);

        self::clear_retention_job($email);
        wp_schedule_single_event($send_at, 'ttos_send_retention_email', array($email));
        self::set_retention_job($email, array(
            'order_id' => $order_id,
            'scheduled_at' => time(),
            'scheduled_for' => $send_at,
        ));
        self::log('retention', 'Scheduled retention follow-up.', array('order_id' => $order_id, 'email' => $email, 'scheduled_for' => $send_at));
    }

    public static function send_retention_email(string $email): void {
        $email = strtolower(sanitize_email($email));
        if ($email === '' || !is_email($email)) {
            return;
        }

        if (self::get('retention_enabled') !== '1') {
            self::clear_retention_job($email);
            self::log('retention', 'Skipped retention send because the feature is disabled.', array('email' => $email));
            return;
        }
        if (!self::customer_marketing_opt_in($email)) {
            self::clear_retention_job($email);
            self::log('retention', 'Skipped retention send because the customer is unsubscribed.', array('email' => $email));
            return;
        }

        $delay_days = max(1, absint(self::get('retention_delay_days')));
        $latest_order_ts = self::latest_relevant_order_timestamp($email);
        if ($latest_order_ts > 0) {
            $eligible_at = $latest_order_ts + ($delay_days * DAY_IN_SECONDS);
            if ($eligible_at > time()) {
                self::clear_retention_job($email);
                wp_schedule_single_event($eligible_at, 'ttos_send_retention_email', array($email));
                self::set_retention_job($email, array(
                    'order_id' => 0,
                    'scheduled_at' => time(),
                    'scheduled_for' => $eligible_at,
                ));
                self::log('retention', 'Deferred retention send because the customer has ordered again recently.', array('email' => $email, 'scheduled_for' => $eligible_at));
                return;
            }
        }

        $coupon = self::create_retention_coupon($email);
        if ($coupon === '') {
            self::clear_retention_job($email);
            self::log('retention', 'Skipped retention send because a coupon could not be generated.', array('email' => $email));
            return;
        }

        $from = sanitize_text_field((string) self::get('campaign_from_name')) ?: get_bloginfo('name');
        $reply = sanitize_email((string) self::get('campaign_reply_to')) ?: get_option('admin_email');
        $unsubscribe = self::campaign_unsubscribe_url($email);
        $subject = sprintf(__('%s would love to see you again', 'takeaway-os'), get_bloginfo('name'));
        $body = "Hi,\n\nThanks again for ordering direct from " . get_bloginfo('name') . ". Here is a little thank-you for your next order.\n\nYour coupon: " . $coupon . "\nOrder here: " . home_url('/menu/') . "\n\nIf you no longer want direct-order offers, unsubscribe here: " . $unsubscribe . "\n\nSee you again soon.";
        $headers = array('Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $from . ' <' . $reply . '>');
        $sent = wp_mail($email, $subject, $body, $headers);

        self::clear_retention_job($email);
        self::log('retention', $sent ? 'Retention email sent.' : 'Retention email failed to send.', array('email' => $email, 'coupon' => $coupon));
    }

    public static function export_campaign_contacts(): void {
        $campaign_id = sanitize_key(wp_unslash($_GET['campaign_id'] ?? ''));
        if (!current_user_can('ttos_view_reports') || !check_admin_referer('ttos_export_campaign_contacts_' . $campaign_id)) wp_die('Not allowed.');
        $campaigns = get_option('ttos_customer_campaigns', array());
        $campaign = null;
        foreach ((array) $campaigns as $item) if (($item['id'] ?? '') === $campaign_id) $campaign = $item;
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=takeaway-campaign-contacts-' . gmdate('Y-m-d') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, array('email','coupon','campaign'));
        foreach ((array) ($campaign['emails'] ?? array()) as $email) fputcsv($out, array($email, $campaign['code'] ?? '', $campaign['name'] ?? ''));
        fclose($out); exit;
    }

    public static function block_add_to_cart_if_paused($passed, $product_id = 0): bool {
        if (self::get('pause_enabled') !== '1') return (bool) $passed;
        if (function_exists('wc_add_notice')) wc_add_notice(self::get('pause_message'), 'error');
        return false;
    }

    public static function block_checkout_if_paused(): void {
        if (self::get('pause_enabled') === '1' && function_exists('wc_add_notice')) wc_add_notice(self::get('pause_message'), 'error');
    }

    public static function pause_banner(): void {
        if (self::get('pause_enabled') !== '1') return;
        echo '<div class="ttos-pause-banner">' . esc_html((string) self::get('pause_message')) . '</div>';
    }

    private static function retention_jobs_table(): void {
        $jobs = self::retention_jobs();
        echo '<h3>Pending retention follow-ups</h3>';
        if (!$jobs) {
            echo '<p class="ttos-muted">No retention emails are currently queued.</p>';
            return;
        }
        echo '<table class="ttos-table"><thead><tr><th>Email</th><th>Scheduled for</th><th>Source order</th></tr></thead><tbody>';
        foreach ($jobs as $email => $job) {
            echo '<tr><td>' . esc_html((string) $email) . '</td><td>' . esc_html(!empty($job['scheduled_for']) ? date_i18n('d M Y H:i', (int) $job['scheduled_for']) : '—') . '</td><td>' . esc_html(!empty($job['order_id']) ? '#' . (string) $job['order_id'] : '—') . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function retention_jobs(): array {
        $jobs = get_option('ttos_retention_jobs', array());
        return is_array($jobs) ? $jobs : array();
    }

    private static function set_retention_job(string $email, array $data): void {
        $jobs = self::retention_jobs();
        $jobs[$email] = $data;
        update_option('ttos_retention_jobs', $jobs, false);
    }

    private static function clear_retention_job(string $email): void {
        wp_clear_scheduled_hook('ttos_send_retention_email', array($email));
        $jobs = self::retention_jobs();
        if (isset($jobs[$email])) {
            unset($jobs[$email]);
            update_option('ttos_retention_jobs', $jobs, false);
        }
    }

    private static function clear_all_retention_jobs(): void {
        foreach (array_keys(self::retention_jobs()) as $email) {
            wp_clear_scheduled_hook('ttos_send_retention_email', array($email));
        }
        update_option('ttos_retention_jobs', array(), false);
    }

    private static function customer_marketing_opt_in(string $email): bool {
        $profiles = get_option('ttos_customer_profiles', array());
        if (!is_array($profiles)) {
            return false;
        }
        $profile = $profiles[strtolower($email)] ?? array();
        return ($profile['marketing_ok'] ?? '0') === '1';
    }

    private static function latest_relevant_order_timestamp(string $email): int {
        if (!function_exists('wc_get_orders')) {
            return 0;
        }
        $orders = wc_get_orders(array(
            'limit' => 1,
            'billing_email' => $email,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
            'status' => array('completed', 'processing', 'on-hold', 'ttos-accepted', 'ttos-prepping', 'ttos-ready', 'ttos-out'),
        ));
        $order = is_array($orders) && !empty($orders[0]) ? $orders[0] : null;
        if (!$order || !$order->get_date_created()) {
            return 0;
        }
        return (int) $order->get_date_created()->getTimestamp();
    }

    private static function create_retention_coupon(string $email): string {
        if (!post_type_exists('shop_coupon')) {
            return '';
        }
        $amount = max(0.01, (float) self::decimal(self::get('retention_coupon_amount') ?: '5.00'));
        $type = in_array(self::get('retention_coupon_type'), array('fixed_cart', 'percent'), true) ? self::get('retention_coupon_type') : 'fixed_cart';
        $expiry_days = max(1, absint(self::get('retention_coupon_expiry_days')));
        $code = 'WELCOME-BACK-' . strtoupper(wp_generate_password(6, false, false));
        $coupon_id = wp_insert_post(array(
            'post_title' => $code,
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
            'post_excerpt' => 'Takeaway OS retention offer for ' . $email,
        ));
        if (!$coupon_id || is_wp_error($coupon_id)) {
            return '';
        }
        update_post_meta($coupon_id, '_ttos_generated_coupon', '1');
        update_post_meta($coupon_id, '_ttos_retention_coupon', '1');
        update_post_meta($coupon_id, 'discount_type', $type);
        update_post_meta($coupon_id, 'coupon_amount', self::decimal($amount));
        update_post_meta($coupon_id, 'usage_limit', '1');
        update_post_meta($coupon_id, 'usage_limit_per_user', '1');
        update_post_meta($coupon_id, 'individual_use', 'yes');
        update_post_meta($coupon_id, 'customer_email', array($email));
        update_post_meta($coupon_id, 'date_expires', time() + ($expiry_days * DAY_IN_SECONDS));
        return $code;
    }

    private static function log(string $type, string $message, array $context = array()): void {
        $log = get_option('ttos_production_log', array());
        if (!is_array($log)) $log = array();
        $log[] = array('time' => time(), 'type' => sanitize_key($type), 'message' => sanitize_text_field($message), 'context' => $context);
        update_option('ttos_production_log', array_slice($log, -300), false);
    }

    private static function flash_notice(string $type, string $message): void {
        set_transient('ttos_production_notice_' . get_current_user_id(), array(
            'type'    => sanitize_key($type),
            'message' => sanitize_text_field($message),
        ), 5 * MINUTE_IN_SECONDS);
    }

    private static function consume_flash_notice(): ?array {
        $key = 'ttos_production_notice_' . get_current_user_id();
        $notice = get_transient($key);
        delete_transient($key);
        return is_array($notice) ? $notice : null;
    }

    private static function select(string $label, string $name, string $value, array $options): void {
        echo '<label>' . esc_html($label) . '<select name="' . esc_attr($name) . '">';
        foreach ($options as $key => $option_label) {
            echo '<option value="' . esc_attr((string) $key) . '" ' . selected($value, (string) $key, false) . '>' . esc_html((string) $option_label) . '</option>';
        }
        echo '</select></label>';
    }

    private static function decimal($value): string {
        if (function_exists('wc_format_decimal')) {
            return wc_format_decimal($value);
        }
        return number_format((float) $value, 2, '.', '');
    }

    private static function normalise_csv_header(string $header): string {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
        return sanitize_key($header);
    }

    private static function csv_bool($value): bool {
        return in_array(strtolower(trim((string) $value)), array('1', 'yes', 'true', 'y'), true);
    }

    private static function csv_row_blank(array $row): bool {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    private static function match_existing_product(string $name, string $category = ''): int {
        $products = get_posts(array(
            'post_type'      => 'product',
            'post_status'    => array('publish', 'draft'),
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));
        foreach ((array) $products as $product_id) {
            if (strcasecmp((string) get_the_title($product_id), $name) !== 0) {
                continue;
            }
            if ($category !== '' && taxonomy_exists('product_cat')) {
                $terms = wp_get_post_terms((int) $product_id, 'product_cat', array('fields' => 'names'));
                $existing = !is_wp_error($terms) && $terms ? (string) $terms[0] : '';
                if ($existing !== '' && strcasecmp($existing, $category) !== 0) {
                    continue;
                }
            }
            return (int) $product_id;
        }
        return 0;
    }

    private static function campaign_unsubscribe_url(string $email): string {
        return add_query_arg(array(
            'action' => 'ttos_campaign_unsubscribe',
            'email'  => strtolower($email),
            'sig'    => self::campaign_unsubscribe_signature($email),
        ), admin_url('admin-post.php'));
    }

    private static function campaign_unsubscribe_signature(string $email): string {
        return hash_hmac('sha256', strtolower(trim($email)), wp_salt('auth'));
    }

    public static function handle_campaign_unsubscribe(): void {
        $email = sanitize_email(wp_unslash($_GET['email'] ?? ''));
        $sig = sanitize_text_field(wp_unslash($_GET['sig'] ?? ''));
        if (!$email || !is_email($email) || !$sig || !hash_equals(self::campaign_unsubscribe_signature($email), $sig)) {
            wp_die(esc_html__('That unsubscribe link is invalid or has expired.', 'takeaway-os'), esc_html__('Unsubscribe failed', 'takeaway-os'), array('response' => 400));
        }
        $profiles = get_option('ttos_customer_profiles', array());
        if (!is_array($profiles)) {
            $profiles = array();
        }
        $key = strtolower($email);
        $profiles[$key] = is_array($profiles[$key] ?? null) ? $profiles[$key] : array();
        $profiles[$key]['marketing_ok'] = '0';
        update_option('ttos_customer_profiles', $profiles, false);
        self::clear_retention_job($key);
        self::log('campaign', 'Customer unsubscribed from campaign emails', array('email' => $email));

        wp_die(
            '<p>' . esc_html__('You have been unsubscribed from future Takeaway OS campaign emails.', 'takeaway-os') . '</p><p><a href="' . esc_url(home_url('/')) . '">' . esc_html__('Return to the site', 'takeaway-os') . '</a></p>',
            esc_html__('Unsubscribed', 'takeaway-os'),
            array('response' => 200)
        );
    }

}

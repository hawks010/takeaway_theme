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
        add_action('ttos_inventory_daily_reset', array(__CLASS__, 'inventory_daily_reset'));
        add_filter('woocommerce_add_to_cart_validation', array(__CLASS__, 'block_add_to_cart_if_paused'), 1, 2);
        add_action('woocommerce_checkout_process', array(__CLASS__, 'block_checkout_if_paused'));
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
                'handover_notes' => sanitize_textarea_field($raw['handover_notes'] ?? ''),
            ));
            self::redirect('production-settings-saved');
        }

        if ($action === 'production_apply_starter_menu') {
            self::apply_starter_menu();
            self::redirect('starter-menu-created', 'menu-tools');
        }

        if ($action === 'production_import_menu') {
            self::import_menu_csv();
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
            self::send_campaign($campaign_id);
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
        self::shell_start('Production Tools', 'Import menus, repair pages, pause ordering, reset stock and prep the final client handover.');
        echo '<div class="ttos-feature-nav"><a href="#readiness">Readiness</a><a href="#safety">Safety</a><a href="#menu-tools">Menu tools</a><a href="#inventory">Inventory</a><a href="#page-repair">Pages</a><a href="#campaigns">Campaigns</a><a href="#handover">Handover</a></div>';
        self::readiness_panel();
        self::safety_panel();
        self::menu_tools_panel();
        self::inventory_panel();
        self::page_repair_panel();
        self::campaign_panel();
        self::handover_panel();
        self::shell_end();
    }

    private static function shell_start(string $title, string $subtitle = ''): void {
        echo '<div class="ttos-wrap"><div class="ttos-shell"><div class="ttos-top"><div><p class="ttos-eyebrow">Takeaway Theme</p><h1>' . esc_html($title) . '</h1>';
        if ($subtitle) echo '<p>' . esc_html($subtitle) . '</p>';
        echo '</div><a class="ttos-pill" href="' . esc_url(home_url('/')) . '" target="_blank">View site ↗</a></div>';
        if (!empty($_GET['ttos_notice'])) echo '<div class="ttos-notice">Saved. Production system updated.</div>';
        echo '<nav class="ttos-nav"><a href="' . esc_url(admin_url('admin.php?page=takeaway-os')) . '">Dashboard</a><a href="' . esc_url(admin_url('admin.php?page=takeaway-os-launchpad')) . '">Launchpad</a><a href="' . esc_url(admin_url('admin.php?page=takeaway-os-setup-health')) . '">Setup Health</a><a href="' . esc_url(admin_url('admin.php?page=takeaway-os-menu')) . '">Menu</a><a href="' . esc_url(admin_url('admin.php?page=takeaway-os-orders')) . '">Orders</a><a href="' . esc_url(admin_url('admin.php?page=takeaway-os-features')) . '">Features</a><a href="' . esc_url(admin_url('admin.php?page=takeaway-os-production')) . '" class="active">Production</a></nav>';
    }

    private static function shell_end(): void { echo '</div></div>'; }

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
        echo '<label>Handover notes<textarea name="production[handover_notes]" rows="4">' . esc_textarea($s['handover_notes']) . '</textarea></label><button class="ttos-button">Save production settings</button></form></section>';
    }

    private static function menu_tools_panel(): void {
        echo '<section id="menu-tools" class="ttos-card"><h2>Menu import/export and starter profiles</h2><p class="ttos-muted">Fast setup for new restaurants: import CSV, export current menu, or create a realistic starter menu with configured pizzas, kebabs and meal upgrades.</p>';
        echo '<div class="ttos-grid ttos-grid-3"><div><h3>Starter menu</h3><p class="ttos-muted">Creates sensible categories and items with option groups. Existing items are left alone.</p><form method="post">';
        wp_nonce_field('ttos_production_apply_starter_menu');
        echo '<input type="hidden" name="ttos_action" value="production_apply_starter_menu"><button class="ttos-button">Create starter menu</button></form></div>';
        echo '<div><h3>Import CSV</h3><p class="ttos-muted">Headers: name, category, price, description, allergens, badges, sold_out, hidden, option_preset.</p><form method="post" enctype="multipart/form-data">';
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

    public static function apply_starter_menu(): void {
        if (!TTOS_WooCommerce::active()) return;
        $items = array(
            array('Mixed Doner Kebab','Kebabs','9.50','Lamb doner with salad and sauce.','gluten','Popular, Halal','kebab'),
            array('Chicken Shish Kebab','Kebabs','10.95','Chargrilled chicken shish with salad.','','Halal','kebab'),
            array('Margherita Pizza','Pizza','8.99','Tomato, mozzarella and oregano.','gluten,milk','Vegetarian','pizza'),
            array('Pepperoni Pizza','Pizza','10.99','Pepperoni, mozzarella and tomato sauce.','gluten,milk','','pizza'),
            array('Classic Burger','Burgers','6.95','Beef burger with lettuce and house sauce.','gluten','Popular','burger'),
            array('Chicken Fillet Burger','Burgers','7.50','Crispy chicken fillet with mayo.','gluten,egg','','burger'),
            array('Chips','Sides','3.00','Fresh hot chips.','','Vegetarian',''),
            array('Onion Rings','Sides','3.50','Crispy battered onion rings.','gluten','Vegetarian',''),
            array('Can of Drink','Drinks','1.50','Choose from available cans.','','',''),
        );
        foreach ($items as $i => $row) {
            TTOS_WooCommerce::create_or_update_product(array(
                'name'=>$row[0], 'category'=>$row[1], 'price'=>$row[2], 'description'=>$row[3],
                'allergens'=>$row[4], 'badges'=>$row[5], 'option_groups'=>self::preset_groups($row[6]), 'sort_order'=>(string) ($i + 1),
            ));
        }
    }

    private static function import_menu_csv(): void {
        if (!TTOS_WooCommerce::active() || empty($_FILES['menu_csv']['tmp_name'])) return;
        $handle = fopen($_FILES['menu_csv']['tmp_name'], 'r');
        if (!$handle) return;
        $headers = fgetcsv($handle);
        if (!$headers) { fclose($handle); return; }
        $headers = array_map('sanitize_key', $headers);
        while (($row = fgetcsv($handle)) !== false) {
            $data = array();
            foreach ($headers as $idx => $key) $data[$key] = $row[$idx] ?? '';
            if (empty($data['name'])) continue;
            TTOS_WooCommerce::create_or_update_product(array(
                'name' => $data['name'] ?? '',
                'category' => $data['category'] ?? '',
                'price' => $data['price'] ?? '0',
                'description' => $data['description'] ?? '',
                'allergens' => $data['allergens'] ?? '',
                'badges' => $data['badges'] ?? '',
                'sold_out' => !empty($data['sold_out']) && in_array(strtolower((string) $data['sold_out']), array('1','yes','true'), true),
                'hidden' => !empty($data['hidden']) && in_array(strtolower((string) $data['hidden']), array('1','yes','true'), true),
                'option_groups' => self::preset_groups(sanitize_key($data['option_preset'] ?? '')),
            ));
        }
        fclose($handle);
    }

    public static function export_menu_csv(): void {
        if (!current_user_can('ttos_manage_menu') || !check_admin_referer('ttos_export_menu_csv')) wp_die('Not allowed.');
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=takeaway-menu-' . gmdate('Y-m-d') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, array('name','category','price','sale_price','description','allergens','badges','sold_out','hidden','option_groups_json'));
        if (post_type_exists('product')) {
            $q = new WP_Query(array('post_type'=>'product','post_status'=>array('publish','draft'),'posts_per_page'=>-1,'orderby'=>'menu_order title','order'=>'ASC'));
            while ($q->have_posts()) { $q->the_post(); $id = get_the_ID();
                $terms = wp_get_post_terms($id, 'product_cat', array('fields'=>'names'));
                fputcsv($out, array(get_the_title(), !is_wp_error($terms) && $terms ? $terms[0] : '', get_post_meta($id,'_regular_price',true), get_post_meta($id,'_sale_price',true), wp_strip_all_tags(get_post_field('post_content',$id)), get_post_meta($id,'_ttos_allergens',true), get_post_meta($id,'_ttos_badges',true), get_post_meta($id,'_stock_status',true)==='outofstock' ? 'yes' : 'no', get_post_status($id)==='draft' ? 'yes' : 'no', get_post_meta($id,'_ttos_option_groups',true)));
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

    private static function send_campaign(string $campaign_id): void {
        $campaigns = get_option('ttos_customer_campaigns', array());
        if (!is_array($campaigns)) return;
        $campaign = null;
        foreach ($campaigns as $item) if (($item['id'] ?? '') === $campaign_id) $campaign = $item;
        if (!$campaign) return;
        $profiles = get_option('ttos_customer_profiles', array());
        if (!is_array($profiles)) $profiles = array();
        $emails = array_slice(array_unique(array_map('sanitize_email', (array) ($campaign['emails'] ?? array()))), 0, 100);
        $sent = 0;
        $from = sanitize_text_field(self::get('campaign_from_name')) ?: get_bloginfo('name');
        $reply = sanitize_email(self::get('campaign_reply_to')) ?: get_option('admin_email');
        $headers = array('Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $from . ' <' . $reply . '>');
        foreach ($emails as $email) {
            if (!$email || !is_email($email)) continue;
            $profile = $profiles[strtolower($email)] ?? array();
            if (isset($profile['marketing_ok']) && $profile['marketing_ok'] !== '1') continue;
            $subject = ($campaign['name'] ?? 'Direct order offer') . ' - ' . get_bloginfo('name');
            $body = "Hi,\n\nHere is your direct-order offer from " . get_bloginfo('name') . ".\n\nCoupon code: " . ($campaign['code'] ?? '') . "\n\nOrder here: " . home_url('/menu/') . "\n\nThank you for ordering direct.";
            if (wp_mail($email, $subject, $body, $headers)) $sent++;
        }
        self::log('campaign', 'Campaign email sent', array('campaign'=>$campaign_id,'sent'=>$sent));
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
    private static function log(string $type, string $message, array $context = array()): void {
        $log = get_option('ttos_production_log', array());
        if (!is_array($log)) $log = array();
        $log[] = array('time' => time(), 'type' => sanitize_key($type), 'message' => sanitize_text_field($message), 'context' => $context);
        update_option('ttos_production_log', array_slice($log, -300), false);
    }

}

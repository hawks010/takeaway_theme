<?php

defined('ABSPATH') || exit;

/**
 * Production polish layer: checkout, handover, go-live, logs and owner-safe operations.
 */
final class TTOS_Operations {
    public static function hooks(): void {
        add_action('admin_menu', array(__CLASS__, 'menu'), 25);
        add_action('admin_init', array(__CLASS__, 'handle_posts'));
        add_action('admin_menu', array(__CLASS__, 'client_menu_lockdown'), 999);
        add_filter('body_class', array(__CLASS__, 'front_body_class'));

        add_filter('woocommerce_checkout_fields', array(__CLASS__, 'checkout_fields'));
        add_action('woocommerce_checkout_process', array(__CLASS__, 'validate_checkout'));
        add_action('woocommerce_checkout_create_order', array(__CLASS__, 'save_order_meta'), 20, 2);
        add_action('woocommerce_admin_order_data_after_billing_address', array(__CLASS__, 'admin_order_meta'));
        add_filter('woocommerce_email_order_meta_fields', array(__CLASS__, 'email_order_meta'), 20, 3);
        add_filter('woocommerce_order_get_shipping_method', array(__CLASS__, 'shipping_method_label'), 20, 2);

        add_action('woocommerce_order_status_changed', array(__CLASS__, 'log_order_status'), 30, 4);
        add_action('woocommerce_new_order', array(__CLASS__, 'log_new_order'), 30, 2);

        add_shortcode('takeaway_go_live_checklist', array(__CLASS__, 'shortcode_go_live'));
        add_shortcode('takeaway_open_status', array(__CLASS__, 'shortcode_open_status'));
    }

    public static function defaults(): array {
        return array(
            'checkout' => array(
                'force_choice' => '1',
                'default_method' => 'delivery',
                'time_mode' => 'asap_or_slot',
                'slot_interval' => '15',
                'lead_time_delivery' => '35',
                'lead_time_collection' => '20',
                'max_days_ahead' => '2',
                'delivery_label' => 'Delivery',
                'collection_label' => 'Collection',
            ),
            'handover' => array(
                'client_mode' => '0',
                'hide_wordpress_menus' => '1',
                'show_woo_to_owner' => '0',
                'lock_notice' => 'This site is in client mode. Contact your developer to change paid modules or advanced WordPress settings.',
            ),
            'go_live' => array(
                'test_order_done' => '0',
                'test_email_done' => '0',
                'test_payment_done' => '0',
                'legal_pages_done' => '0',
                'allergens_done' => '0',
                'domain_done' => '0',
                'backup_done' => '0',
            ),
        );
    }

    public static function get(string $section = '', $key = null) {
        $settings = wp_parse_args(get_option('ttos_operations_settings', array()), self::defaults());
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
        update_option('ttos_operations_settings', $settings, false);
    }

    public static function menu(): void {
        add_submenu_page('takeaway-os', 'Operations', 'Operations', 'ttos_manage_settings', 'takeaway-os-operations', array(__CLASS__, 'page_operations'));
        add_submenu_page('takeaway-os', 'Go Live', 'Go Live', 'ttos_manage_settings', 'takeaway-os-golive', array(__CLASS__, 'page_go_live'));
    }

    public static function handle_posts(): void {
        if (!is_admin() || empty($_POST['ttos_action'])) return;
        if (!current_user_can('ttos_manage_settings')) return;
        $action = sanitize_key(wp_unslash($_POST['ttos_action']));
        if (strpos($action, 'operations_') !== 0 && strpos($action, 'golive_') !== 0) return;
        check_admin_referer('ttos_' . $action);

        if ($action === 'operations_save_checkout') {
            $raw = wp_unslash($_POST['operations']['checkout'] ?? array());
            self::update_section('checkout', array(
                'force_choice' => !empty($raw['force_choice']) ? '1' : '0',
                'default_method' => in_array(($raw['default_method'] ?? 'delivery'), array('delivery','collection'), true) ? $raw['default_method'] : 'delivery',
                'time_mode' => in_array(($raw['time_mode'] ?? 'asap_or_slot'), array('asap','slot','asap_or_slot'), true) ? $raw['time_mode'] : 'asap_or_slot',
                'slot_interval' => (string) max(5, absint($raw['slot_interval'] ?? 15)),
                'lead_time_delivery' => (string) max(0, absint($raw['lead_time_delivery'] ?? 35)),
                'lead_time_collection' => (string) max(0, absint($raw['lead_time_collection'] ?? 20)),
                'max_days_ahead' => (string) max(0, absint($raw['max_days_ahead'] ?? 2)),
                'delivery_label' => sanitize_text_field($raw['delivery_label'] ?? 'Delivery'),
                'collection_label' => sanitize_text_field($raw['collection_label'] ?? 'Collection'),
            ));
            self::redirect('checkout-saved', 'checkout');
        }

        if ($action === 'operations_save_handover' && current_user_can('manage_options')) {
            $raw = wp_unslash($_POST['operations']['handover'] ?? array());
            self::update_section('handover', array(
                'client_mode' => !empty($raw['client_mode']) ? '1' : '0',
                'hide_wordpress_menus' => !empty($raw['hide_wordpress_menus']) ? '1' : '0',
                'show_woo_to_owner' => !empty($raw['show_woo_to_owner']) ? '1' : '0',
                'lock_notice' => sanitize_textarea_field($raw['lock_notice'] ?? ''),
            ));
            self::redirect('handover-saved', 'handover');
        }

        if ($action === 'golive_save') {
            $raw = wp_unslash($_POST['golive'] ?? array());
            $clean = array();
            foreach (self::defaults()['go_live'] as $key => $default) $clean[$key] = !empty($raw[$key]) ? '1' : '0';
            self::update_section('go_live', $clean);
            self::redirect('golive-saved', '', 'takeaway-os-golive');
        }

        if ($action === 'golive_clear_logs' && current_user_can('manage_options')) {
            delete_option('ttos_operations_log');
            delete_option('ttos_integration_log');
            self::redirect('logs-cleared', 'logs', 'takeaway-os-golive');
        }
    }

    private static function redirect(string $notice, string $anchor = '', string $page = 'takeaway-os-operations'): void {
        $url = add_query_arg(array('page' => $page, 'ttos_notice' => $notice), admin_url('admin.php'));
        if ($anchor) $url .= '#' . sanitize_key($anchor);
        wp_safe_redirect($url);
        exit;
    }

    public static function page_operations(): void {
        self::shell_start('Operations', 'Checkout, fulfilment, handover mode and owner-safe behaviour. This is the boring bit that stops Friday night chaos.');
        self::checkout_panel();
        self::handover_panel();
        self::status_panel();
        self::system_check_panel();
        self::shell_end();
    }

    public static function page_go_live(): void {
        self::shell_start('Go Live', 'Final launch checklist, system status, logs and export links.');
        self::go_live_panel();
        self::logs_panel();
        self::shell_end();
    }

    private static function shell_start(string $title, string $subtitle = ''): void {
        echo '<div class="ttos-wrap"><div class="ttos-shell"><div class="ttos-top"><div><p class="ttos-eyebrow">Takeaway OS</p><h1>' . esc_html($title) . '</h1>';
        if ($subtitle) echo '<p>' . esc_html($subtitle) . '</p>';
        echo '</div><a class="ttos-pill" href="' . esc_url(admin_url('admin.php?page=takeaway-os')) . '">Dashboard</a></div>';
        self::nav();
        if (!empty($_GET['ttos_notice'])) echo '<div class="ttos-notice">' . esc_html(ucfirst(str_replace('-', ' ', sanitize_key($_GET['ttos_notice'])))) . '</div>';
    }

    private static function shell_end(): void { echo '</div></div>'; }

    private static function nav(): void {
        $items = array(
            'takeaway-os' => 'Dashboard', 'takeaway-os-launchpad' => 'Launchpad', 'takeaway-os-setup-health' => 'Setup Health', 'takeaway-os-menu' => 'Menu', 'takeaway-os-orders' => 'Orders', 'takeaway-os-kitchen' => 'Kitchen',
            'takeaway-os-customers' => 'Customers', 'takeaway-os-reports' => 'Reports', 'takeaway-os-site-content' => 'Site Content', 'takeaway-os-features' => 'Features', 'takeaway-os-operations' => 'Operations', 'takeaway-os-golive' => 'Go Live', 'takeaway-os-modules' => 'Add-ons'
        );
        $current = isset($_GET['page']) ? sanitize_key($_GET['page']) : 'takeaway-os';
        echo '<nav class="ttos-nav">';
        foreach ($items as $slug => $label) {
            if ($slug === 'takeaway-os-modules' && !current_user_can('ttos_modules')) continue;
            echo '<a class="' . esc_attr($current === $slug ? 'active' : '') . '" href="' . esc_url(admin_url('admin.php?page=' . $slug)) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
    }

    private static function checkout_panel(): void {
        $s = self::get('checkout');
        echo '<section id="checkout" class="ttos-card"><h2>Takeaway checkout flow</h2><p class="ttos-muted">Adds delivery/collection and requested time fields to WooCommerce checkout, then saves them onto the order for kitchen, email and integrations.</p><form method="post">';
        wp_nonce_field('ttos_operations_save_checkout');
        echo '<input type="hidden" name="ttos_action" value="operations_save_checkout"><div class="ttos-grid ttos-grid-3">';
        self::select('Default method', 'operations[checkout][default_method]', $s['default_method'], array('delivery'=>'Delivery','collection'=>'Collection'));
        self::select('Time mode', 'operations[checkout][time_mode]', $s['time_mode'], array('asap_or_slot'=>'ASAP or selected time','asap'=>'ASAP only','slot'=>'Selected time only'));
        self::field('Slot interval minutes', 'operations[checkout][slot_interval]', $s['slot_interval'], 'number');
        self::field('Delivery lead time mins', 'operations[checkout][lead_time_delivery]', $s['lead_time_delivery'], 'number');
        self::field('Collection lead time mins', 'operations[checkout][lead_time_collection]', $s['lead_time_collection'], 'number');
        self::field('Max days ahead', 'operations[checkout][max_days_ahead]', $s['max_days_ahead'], 'number');
        self::field('Delivery label', 'operations[checkout][delivery_label]', $s['delivery_label']);
        self::field('Collection label', 'operations[checkout][collection_label]', $s['collection_label']);
        echo '</div><label class="ttos-check"><input type="checkbox" name="operations[checkout][force_choice]" value="1" ' . checked($s['force_choice'], '1', false) . '> Require customer to choose delivery or collection</label><button class="ttos-button">Save checkout flow</button></form></section>';
    }

    private static function handover_panel(): void {
        if (!current_user_can('manage_options')) return;
        $s = self::get('handover');
        echo '<section id="handover" class="ttos-card"><h2>Client handover mode</h2><p class="ttos-muted">Keeps restaurant users inside the Takeaway OS cockpit. Full Administrators can still access WordPress.</p><form method="post">';
        wp_nonce_field('ttos_operations_save_handover');
        echo '<input type="hidden" name="ttos_action" value="operations_save_handover">';
        echo '<label class="ttos-check"><input type="checkbox" name="operations[handover][client_mode]" value="1" ' . checked($s['client_mode'], '1', false) . '> Enable client mode</label>';
        echo '<label class="ttos-check"><input type="checkbox" name="operations[handover][hide_wordpress_menus]" value="1" ' . checked($s['hide_wordpress_menus'], '1', false) . '> Hide normal WordPress/Woo menus for non-admin restaurant users</label>';
        echo '<label class="ttos-check"><input type="checkbox" name="operations[handover][show_woo_to_owner]" value="1" ' . checked($s['show_woo_to_owner'], '1', false) . '> Allow owner role to see WooCommerce menus</label>';
        echo '<label>Client-mode notice<textarea name="operations[handover][lock_notice]" rows="3">' . esc_textarea($s['lock_notice']) . '</textarea></label>';
        echo '<button class="ttos-button">Save handover mode</button></form></section>';
    }

    private static function status_panel(): void {
        echo '<section class="ttos-card"><h2>Operational status</h2><div class="ttos-grid ttos-grid-4">';
        self::metric('WooCommerce', TTOS_WooCommerce::active() ? 'Active' : 'Missing');
        self::metric('Checkout pages', self::page_ready_count() . ' ready');
        self::metric('Client mode', self::get('handover','client_mode') === '1' ? 'On' : 'Off');
        self::metric('Open status', self::is_open_now() ? 'Open' : 'Closed/unknown');
        echo '</div></section>';
    }

    private static function go_live_panel(): void {
        $s = self::get('go_live');
        $items = array(
            'test_order_done' => 'Place a full test order', 'test_email_done' => 'Confirm order emails deliver', 'test_payment_done' => 'Confirm card/cash payment setup',
            'legal_pages_done' => 'Terms, privacy, cookies and accessibility checked', 'allergens_done' => 'Allergens checked on menu items', 'domain_done' => 'Domain/SSL/live URL checked', 'backup_done' => 'Backup/snapshot taken before launch'
        );
        echo '<section class="ttos-card"><h2>Launch checklist</h2><form method="post">';
        wp_nonce_field('ttos_golive_save');
        echo '<input type="hidden" name="ttos_action" value="golive_save"><div class="ttos-check-grid ttos-golive-grid">';
        foreach ($items as $key => $label) echo '<label><input type="checkbox" name="golive[' . esc_attr($key) . ']" value="1" ' . checked($s[$key], '1', false) . '> ' . esc_html($label) . '</label>';
        echo '</div><button class="ttos-button">Save checklist</button></form></section>';
        self::go_live_score($s, count($items));
        self::system_check_panel();
    }

    private static function go_live_score(array $s, int $total): void {
        $done = 0; foreach (self::defaults()['go_live'] as $key => $v) if (!empty($s[$key]) && $s[$key] === '1') $done++;
        $pct = $total ? round(($done / $total) * 100) : 0;
        echo '<section class="ttos-card"><h2>Launch readiness</h2><div class="ttos-big-progress"><span style="width:' . esc_attr((string) $pct) . '%"></span></div><p><strong>' . esc_html((string) $pct) . '% ready</strong> · ' . esc_html((string) $done) . ' of ' . esc_html((string) $total) . ' launch checks complete.</p></section>';
    }

    private static function logs_panel(): void {
        $ops = get_option('ttos_operations_log', array()); if (!is_array($ops)) $ops = array();
        $integrations = get_option('ttos_integration_log', array()); if (!is_array($integrations)) $integrations = array();
        echo '<section id="logs" class="ttos-card"><h2>Logs</h2><p class="ttos-muted">Latest order, printer, SMS and EPOS events. Useful when testing with Codex or a staging site.</p><div class="ttos-grid ttos-grid-2"><div><h3>Operations</h3>';
        self::log_table(array_reverse(array_slice($ops, -20)));
        echo '</div><div><h3>Integrations</h3>';
        self::log_table(array_reverse(array_slice($integrations, -20)));
        echo '</div></div>';
        if (current_user_can('manage_options')) { echo '<form method="post">'; wp_nonce_field('ttos_golive_clear_logs'); echo '<input type="hidden" name="ttos_action" value="golive_clear_logs"><button class="ttos-button ttos-button-dark">Clear logs</button></form>'; }
        echo '</section>';
    }

    private static function log_table(array $rows): void {
        if (!$rows) { echo '<p class="ttos-muted">No logs yet.</p>'; return; }
        echo '<table class="ttos-table"><thead><tr><th>Time</th><th>Type</th><th>Message</th></tr></thead><tbody>';
        foreach ($rows as $row) echo '<tr><td>' . esc_html(date_i18n('d M H:i', (int) ($row['time'] ?? time()))) . '</td><td>' . esc_html($row['type'] ?? 'log') . '</td><td>' . esc_html($row['message'] ?? '') . '</td></tr>';
        echo '</tbody></table>';
    }

    public static function checkout_fields(array $fields): array {
        if (!TTOS_WooCommerce::active()) return $fields;
        $checkout = self::get('checkout');
        $trading = TTOS_Settings::get('trading');
        $options = array();
        if ($trading['delivery_enabled'] === '1') $options['delivery'] = $checkout['delivery_label'];
        if ($trading['collection_enabled'] === '1') $options['collection'] = $checkout['collection_label'];
        if (!$options) return $fields;
        $fields['order']['ttos_fulfilment_method'] = array(
            'type' => 'select', 'label' => __('Delivery or collection', 'takeaway-os'), 'required' => $checkout['force_choice'] === '1',
            'options' => $options, 'default' => isset($options[$checkout['default_method']]) ? $checkout['default_method'] : array_key_first($options), 'priority' => 5,
        );
        $time_options = self::time_slot_options($checkout);
        if ($checkout['time_mode'] !== 'asap') {
            $fields['order']['ttos_requested_time'] = array('type'=>'select','label'=>__('Requested time', 'takeaway-os'),'required'=>$checkout['time_mode']==='slot','options'=>$time_options,'priority'=>6);
        } else {
            $fields['order']['ttos_requested_time'] = array('type'=>'hidden','default'=>'asap','priority'=>6);
        }
        return $fields;
    }

    private static function time_slot_options(array $checkout): array {
        $options = array('asap' => 'ASAP');
        if ($checkout['time_mode'] === 'slot') $options = array('' => 'Choose a time');
        $interval = max(5, (int) $checkout['slot_interval']);
        $days = max(0, (int) $checkout['max_days_ahead']);
        $start = time() + (max((int) $checkout['lead_time_delivery'], (int) $checkout['lead_time_collection']) * MINUTE_IN_SECONDS);
        $start = (int) ceil($start / ($interval * 60)) * ($interval * 60);
        $end = strtotime('+' . $days . ' days 23:59:00');
        for ($ts = $start; $ts <= $end; $ts += $interval * 60) {
            $key = gmdate('Y-m-d\TH:i', $ts);
            $label = date_i18n('D j M, H:i', $ts);
            $options[$key] = $label;
        }
        return array_slice($options, 0, 120, true);
    }

    public static function validate_checkout(): void {
        $trading = TTOS_Settings::get('trading');
        $method = sanitize_key(wp_unslash($_POST['ttos_fulfilment_method'] ?? ''));
        if ($method === '') return;
        if ($method === 'delivery' && $trading['delivery_enabled'] !== '1') wc_add_notice(__('Delivery is currently unavailable.', 'takeaway-os'), 'error');
        if ($method === 'collection' && $trading['collection_enabled'] !== '1') wc_add_notice(__('Collection is currently unavailable.', 'takeaway-os'), 'error');
        if ($method === 'delivery' && function_exists('WC') && WC()->cart) {
            $min = (float) ($trading['min_order'] ?? 0);
            if ($min > 0 && (float) WC()->cart->get_subtotal() < $min) wc_add_notice(sprintf(__('Minimum delivery order is £%s.', 'takeaway-os'), number_format($min, 2)), 'error');
            $allowed = self::basic_postcodes($trading['delivery_postcodes'] ?? '');
            if ($allowed) {
                $pc = strtoupper(preg_replace('/\s+/', '', sanitize_text_field(wp_unslash($_POST['shipping_postcode'] ?? $_POST['billing_postcode'] ?? ''))));
                $ok = false; foreach ($allowed as $prefix) if ($prefix !== '' && strpos($pc, $prefix) === 0) $ok = true;
                if (!$ok) wc_add_notice(__('Sorry, this postcode is outside the current delivery area.', 'takeaway-os'), 'error');
            }
        }
    }

    private static function basic_postcodes(string $raw): array {
        $out = array();
        foreach (preg_split('/[,\n\r]+/', $raw) as $part) {
            $part = strtoupper(preg_replace('/\s+/', '', trim($part)));
            if ($part && preg_match('/^[A-Z]{1,2}[0-9]/', $part)) $out[] = $part;
        }
        return array_unique($out);
    }

    /** Public accessor for the configured delivery postcode prefixes. */
    public static function postcode_prefixes(): array {
        $trading = TTOS_Settings::get('trading');
        return self::basic_postcodes((string) ($trading['delivery_postcodes'] ?? ''));
    }

    public static function save_order_meta($order, array $data): void {
        $method = sanitize_key(wp_unslash($_POST['ttos_fulfilment_method'] ?? ''));
        $time = sanitize_text_field(wp_unslash($_POST['ttos_requested_time'] ?? 'asap'));
        if ($method) $order->update_meta_data('_ttos_fulfilment_method', $method);
        if ($time) $order->update_meta_data('_ttos_requested_time', $time);
    }

    public static function admin_order_meta($order): void {
        $method = $order->get_meta('_ttos_fulfilment_method'); $time = $order->get_meta('_ttos_requested_time');
        if ($method || $time) echo '<p><strong>Takeaway:</strong><br>' . esc_html(ucfirst($method ?: '')) . ($time ? ' · ' . esc_html(self::format_time_value($time)) : '') . '</p>';
    }

    public static function email_order_meta(array $fields, bool $sent_to_admin, $order): array {
        $method = $order->get_meta('_ttos_fulfilment_method'); $time = $order->get_meta('_ttos_requested_time');
        if ($method) $fields['ttos_fulfilment_method'] = array('label'=>'Delivery/collection', 'value'=>ucfirst($method));
        if ($time) $fields['ttos_requested_time'] = array('label'=>'Requested time', 'value'=>self::format_time_value($time));
        return $fields;
    }

    public static function shipping_method_label(string $label, $order): string {
        if (!$order instanceof WC_Order) return $label;
        $method = $order->get_meta('_ttos_fulfilment_method');
        if ($method === 'collection') return 'Collection';
        if ($method === 'delivery') return $label ?: 'Delivery';
        return $label;
    }

    private static function format_time_value(string $value): string {
        if ($value === 'asap') return 'ASAP';
        $ts = strtotime($value);
        return $ts ? date_i18n('D j M H:i', $ts) : $value;
    }

    public static function log_new_order($order_id, $order = null): void {
        $order = $order ?: (function_exists('wc_get_order') ? wc_get_order($order_id) : null);
        self::log('order', 'New order #' . $order_id . ($order ? ' · ' . wp_strip_all_tags($order->get_formatted_order_total()) : ''));
    }

    public static function log_order_status(int $order_id, string $old, string $new, $order): void {
        self::log('status', 'Order #' . $order_id . ' changed from ' . $old . ' to ' . $new);
    }

    private static function log(string $type, string $message): void {
        $log = get_option('ttos_operations_log', array()); if (!is_array($log)) $log = array();
        $log[] = array('type'=>$type, 'message'=>$message, 'time'=>time());
        update_option('ttos_operations_log', array_slice($log, -200), false);
    }

    public static function client_menu_lockdown(): void {
        if (!is_admin() || current_user_can('manage_options')) return;
        if (self::get('handover','client_mode') !== '1' || self::get('handover','hide_wordpress_menus') !== '1') return;
        remove_menu_page('index.php'); remove_menu_page('edit.php'); remove_menu_page('upload.php'); remove_menu_page('edit.php?post_type=page'); remove_menu_page('edit-comments.php');
        remove_menu_page('themes.php'); remove_menu_page('plugins.php'); remove_menu_page('users.php'); remove_menu_page('tools.php'); remove_menu_page('options-general.php');
        if (self::get('handover','show_woo_to_owner') !== '1') { remove_menu_page('woocommerce'); remove_menu_page('edit.php?post_type=product'); }
    }

    public static function front_body_class(array $classes): array {
        $classes[] = 'takeaway-os-enabled';
        return $classes;
    }

    public static function shortcode_go_live(): string {
        $s = self::get('go_live'); $total = count(self::defaults()['go_live']); $done = 0; foreach (self::defaults()['go_live'] as $k=>$v) if (($s[$k] ?? '0') === '1') $done++;
        return '<div class="ttos-front-card"><h2>Launch readiness</h2><p>' . esc_html((string) $done) . ' / ' . esc_html((string) $total) . ' checks complete.</p></div>';
    }

    public static function shortcode_open_status(): string {
        $open = self::is_open_now();
        return '<span class="ttos-open-status ' . esc_attr($open ? 'is-open' : 'is-closed') . '">' . esc_html($open ? 'Open now' : 'Closed right now') . '</span>';
    }

    private static function is_open_now(): bool { return true; }

    private static function page_ready_count(): string {
        $count = 0;
        foreach (array('woocommerce_cart_page_id','woocommerce_checkout_page_id','woocommerce_myaccount_page_id') as $opt) {
            if ((int) get_option($opt) > 0) {
                $count++;
            }
        }
        return $count . '/3';
    }

    private static function system_check_panel(): void {
        if (!class_exists('TTOS_Hardening')) {
            return;
        }
        $checks = TTOS_Hardening::system_checks();
        echo '<section class="ttos-card"><h2>Critical system check</h2><p class="ttos-muted">Pre-flight checks for client handover. Fix anything marked critical before launch.</p><table class="ttos-table"><thead><tr><th>Status</th><th>Check</th><th>Detail</th></tr></thead><tbody>';
        foreach ($checks as $check) {
            $state = $check['status'] ?? 'warn';
            $class = $state === 'ok' ? 'ttos-good' : ($state === 'critical' ? 'ttos-bad' : 'ttos-warn');
            $label = $state === 'ok' ? 'OK' : ($state === 'critical' ? 'Critical' : 'Warning');
            echo '<tr><td><span class="' . esc_attr($class) . '">' . esc_html($label) . '</span></td><td><strong>' . esc_html($check['label'] ?? '') . '</strong></td><td>' . esc_html($check['message'] ?? '') . '</td></tr>';
        }
        echo '</tbody></table></section>';
    }

    private static function metric(string $label, string $value): void { echo '<section class="ttos-metric"><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong></section>'; }
    private static function field(string $label, string $name, $value = '', string $type = 'text'): void { echo '<label>' . esc_html($label) . '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '"></label>'; }
    private static function select(string $label, string $name, string $value, array $options): void { echo '<label>' . esc_html($label) . '<select name="' . esc_attr($name) . '">'; foreach ($options as $k=>$v) echo '<option value="' . esc_attr($k) . '" ' . selected($value, $k, false) . '>' . esc_html($v) . '</option>'; echo '</select></label>'; }
}

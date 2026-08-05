<?php

defined('ABSPATH') || exit;

/**
 * v1.0 hardening and handover checks.
 *
 * This does not replace a real licence server. It gives the local install a
 * production-safe pre-flight layer, role migration and module lock visibility
 * so the build can be tested before a hosted licensing service is added.
 */
final class TTOS_Hardening {
    public static function hooks(): void {
        add_action('admin_init', array(__CLASS__, 'maybe_upgrade'), 2);
        add_action('admin_init', array(__CLASS__, 'protect_module_settings'), 3);
        add_action('admin_init', array(__CLASS__, 'maybe_secure_import_dir'), 4);
    }

    public static function maybe_upgrade(): void {
        $stored = (string) get_option('ttos_version', '0.0.0');
        if (version_compare($stored, TTOS_VERSION, '>=')) {
            return;
        }
        self::ensure_capabilities();
        self::normalise_settings();
        update_option('ttos_version', TTOS_VERSION, false);
    }

    private static function ensure_capabilities(): void {
        $caps = array('ttos_access','ttos_manage','ttos_view_orders','ttos_update_orders','ttos_manage_menu','ttos_manage_settings','ttos_view_reports');
        foreach (array('takeaway_owner','takeaway_manager') as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach (array_merge(array('edit_posts'), $caps) as $cap) {
                    $role->add_cap($cap);
                }
                $role->add_cap('upload_files');
            }
        }
        foreach (array('takeaway_kitchen','takeaway_driver') as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach (array('read','edit_posts','ttos_access','ttos_view_orders','ttos_update_orders') as $cap) {
                    $role->add_cap($cap);
                }
            }
        }
        $admin = get_role('administrator');
        if ($admin) {
            foreach (array_merge($caps, array('ttos_modules')) as $cap) {
                $admin->add_cap($cap);
            }
        }
    }

    private static function normalise_settings(): void {
        if (class_exists('TTOS_Settings')) {
            $settings = TTOS_Settings::get();
            foreach (TTOS_Settings::defaults() as $section => $defaults) {
                $settings[$section] = wp_parse_args($settings[$section] ?? array(), $defaults);
            }
            update_option('ttos_settings', $settings, false);
        }
        if (class_exists('TTOS_Operations')) {
            $ops = TTOS_Operations::get();
            foreach (TTOS_Operations::defaults() as $section => $defaults) {
                $ops[$section] = wp_parse_args($ops[$section] ?? array(), $defaults);
            }
            update_option('ttos_operations_settings', $ops, false);
        }
        if (class_exists('TTOS_Features')) {
            $features = TTOS_Features::get();
            foreach (TTOS_Features::defaults() as $section => $defaults) {
                $features[$section] = wp_parse_args($features[$section] ?? array(), $defaults);
            }
            update_option('ttos_feature_settings', $features, false);
        }
    }

    public static function protect_module_settings(): void {
        if (empty($_POST['ttos_action'])) {
            return;
        }
        $action = sanitize_key(wp_unslash($_POST['ttos_action']));
        if ($action !== 'save_modules') {
            return;
        }
        if (current_user_can('manage_options')) {
            return;
        }
        $hash = (string) get_option('ttos_module_lock_hash', '');
        if ($hash !== '') {
            wp_die(esc_html__('Paid modules are locked for handover. Ask the site administrator to unlock them.', 'takeaway-os'));
        }
    }

    public static function maybe_secure_import_dir(): void {
        $dir = self::import_dir(false);
        if (!empty($dir['path']) && is_dir($dir['path'])) {
            self::write_import_dir_guards($dir['path']);
            self::cleanup_stale_imports($dir['path']);
        }
    }

    public static function import_dir(bool $create = true): array {
        $upload = wp_upload_dir();
        if (!empty($upload['error'])) {
            return array();
        }
        $path = trailingslashit($upload['basedir']) . 'ttos-imports';
        $url = trailingslashit($upload['baseurl']) . 'ttos-imports';
        if ($create && !is_dir($path)) {
            wp_mkdir_p($path);
        }
        if ($create && is_dir($path)) {
            self::write_import_dir_guards($path);
        }
        return array(
            'path' => $path,
            'url'  => $url,
        );
    }

    public static function stash_uploaded_file(array $file, array $allowed_extensions, int $max_bytes, string $prefix = 'ttos-import-') {
        if (empty($file['tmp_name']) || empty($file['name'])) {
            return new WP_Error('ttos_missing_upload', __('No upload was received.', 'takeaway-os'));
        }
        if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('ttos_upload_error', __('The uploaded file could not be processed.', 'takeaway-os'));
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('ttos_invalid_upload', __('The uploaded file is not valid.', 'takeaway-os'));
        }
        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($size <= 0 || $size > $max_bytes) {
            return new WP_Error('ttos_upload_size', __('The uploaded file is empty or too large.', 'takeaway-os'));
        }

        $extension = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed_extensions, true)) {
            return new WP_Error('ttos_upload_type', __('That file type is not allowed for this import.', 'takeaway-os'));
        }

        $dir = self::import_dir(true);
        if (empty($dir['path']) || !is_dir($dir['path'])) {
            return new WP_Error('ttos_import_dir', __('The secure import folder could not be created.', 'takeaway-os'));
        }

        $base_name = sanitize_file_name($prefix . gmdate('Ymd-His') . '-' . wp_generate_password(6, false, false) . '.' . $extension);
        $target = trailingslashit($dir['path']) . wp_unique_filename($dir['path'], $base_name);
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return new WP_Error('ttos_upload_move', __('The uploaded file could not be stored securely.', 'takeaway-os'));
        }
        @chmod($target, 0600);

        return array(
            'path' => $target,
            'url'  => trailingslashit($dir['url']) . basename($target),
            'name' => basename($target),
        );
    }

    public static function cleanup_import_file(string $path): void {
        $dir = self::import_dir(false);
        if (empty($dir['path']) || $path === '') {
            return;
        }
        $real_dir = realpath($dir['path']);
        $real_path = realpath($path);
        if (!$real_dir || !$real_path || strpos($real_path, $real_dir) !== 0) {
            return;
        }
        if (is_file($real_path)) {
            wp_delete_file($real_path);
        }
    }

    private static function write_import_dir_guards(string $path): void {
        if (!is_dir($path) || !is_writable($path)) {
            return;
        }
        $index = trailingslashit($path) . 'index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }
        $htaccess = trailingslashit($path) . '.htaccess';
        $rules = "Options -Indexes\n<FilesMatch \".*\">\nRequire all denied\n</FilesMatch>\n";
        if (!file_exists($htaccess) || trim((string) file_get_contents($htaccess)) !== trim($rules)) {
            file_put_contents($htaccess, $rules);
        }
        $web_config = trailingslashit($path) . 'web.config';
        $config = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n    <directoryBrowse enabled=\"false\" />\n    <security>\n      <authorization>\n        <remove users=\"*\" roles=\"\" verbs=\"\" />\n        <add accessType=\"Deny\" users=\"*\" />\n      </authorization>\n    </security>\n  </system.webServer>\n</configuration>\n";
        if (!file_exists($web_config) || trim((string) file_get_contents($web_config)) !== trim($config)) {
            file_put_contents($web_config, $config);
        }
    }

    private static function cleanup_stale_imports(string $path): void {
        $files = glob(trailingslashit($path) . '*');
        if (!is_array($files)) {
            return;
        }
        $cutoff = time() - DAY_IN_SECONDS;
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $name = basename($file);
            if (in_array($name, array('index.php', '.htaccess', 'web.config'), true)) {
                continue;
            }
            if ((int) @filemtime($file) < $cutoff) {
                wp_delete_file($file);
            }
        }
    }

    public static function check_summary(): array {
        $checks = self::system_checks();
        $critical = 0;
        $warnings = 0;
        foreach ($checks as $check) {
            if (($check['status'] ?? '') === 'critical') {
                $critical++;
            } elseif (($check['status'] ?? '') === 'warn') {
                $warnings++;
            }
        }
        if ($critical > 0) {
            return array(
                'critical' => $critical,
                'warnings' => $warnings,
                'label' => $critical . ' critical issue' . ($critical === 1 ? '' : 's'),
                'message' => 'Fix critical issues before client handover or live ordering.',
            );
        }
        if ($warnings > 0) {
            return array(
                'critical' => 0,
                'warnings' => $warnings,
                'label' => $warnings . ' warning' . ($warnings === 1 ? '' : 's'),
                'message' => 'No critical blockers found. Review warnings before launch.',
            );
        }
        return array(
            'critical' => 0,
            'warnings' => 0,
            'label' => 'Ready for controlled testing',
            'message' => 'No critical blockers detected by the built-in pre-flight checks.',
        );
    }

    public static function system_checks(): array {
        $checks = array();
        $checks[] = self::check('php', version_compare(PHP_VERSION, '7.4', '>='), 'PHP version', 'Running PHP ' . PHP_VERSION . '. PHP 7.4+ is required.', true);
        $checks[] = self::check('wordpress', version_compare(get_bloginfo('version'), '6.4', '>='), 'WordPress version', 'Running WordPress ' . get_bloginfo('version') . '. WordPress 6.4+ is required.', true);
        $checks[] = self::check('woocommerce', class_exists('WooCommerce'), 'WooCommerce active', class_exists('WooCommerce') ? 'WooCommerce is active.' : 'WooCommerce is missing or inactive. Ordering, checkout and menu products need WooCommerce.', true);
        $checks[] = self::check('roles', self::roles_ready(), 'Takeaway roles/capabilities', self::roles_ready() ? 'Takeaway roles and capabilities are present.' : 'Some Takeaway roles/capabilities are missing. Deactivate/reactivate or reload admin to run migration.', true);
        $checks[] = self::check('pages', self::pages_ready(), 'Required pages', self::pages_ready() ? 'Required pages are assigned and contain Takeaway/Woo shortcodes.' : 'Some required pages are missing or still contain old content. Run Launchpad page generation/repair.', true);
        $checks[] = self::check('checkout', self::checkout_ready(), 'Checkout configured', self::checkout_ready() ? 'Checkout/cart/account pages are assigned.' : 'WooCommerce checkout pages are not fully assigned.', true);
        $checks[] = self::check('payments', self::payments_ready(), 'Payment methods', self::payments_ready() ? 'At least one WooCommerce payment gateway appears enabled.' : 'No enabled WooCommerce payment gateway was detected. Enable Stripe, WooPayments, cash, or another gateway before live orders.', false);
        $checks[] = self::check('permalinks', get_option('permalink_structure') !== '', 'Permalinks', get_option('permalink_structure') !== '' ? 'Pretty permalinks enabled.' : 'Pretty permalinks are off. Recommended before launch.', false);
        $checks[] = self::check('admin_email', is_email(get_option('admin_email')), 'Admin email', is_email(get_option('admin_email')) ? 'Admin email looks valid.' : 'Admin email is missing or invalid.', false);
        $checks[] = self::check('ssl', is_ssl() || (defined('WP_ENVIRONMENT_TYPE') && wp_get_environment_type() !== 'production'), 'SSL', is_ssl() ? 'SSL is active.' : 'SSL is not detected in this admin session. Required for live card payments.', false);
        $checks[] = self::check('module_lock', (string) get_option('ttos_module_lock_hash', '') !== '', 'Module lock', (string) get_option('ttos_module_lock_hash', '') !== '' ? 'Paid module lock key exists.' : 'No paid module lock key set. Set one before final handover.', false);
        return $checks;
    }

    private static function check(string $id, bool $ok, string $label, string $message, bool $critical = false): array {
        return array(
            'id' => $id,
            'status' => $ok ? 'ok' : ($critical ? 'critical' : 'warn'),
            'label' => $label,
            'message' => $message,
        );
    }

    private static function roles_ready(): bool {
        $admin = get_role('administrator');
        if (!$admin || !$admin->has_cap('ttos_access') || !$admin->has_cap('ttos_modules')) {
            return false;
        }
        $owner = get_role('takeaway_owner');
        $manager = get_role('takeaway_manager');
        $kitchen = get_role('takeaway_kitchen');
        return $owner
            && $owner->has_cap('ttos_access')
            && $owner->has_cap('ttos_manage_menu')
            && $owner->has_cap('ttos_manage_settings')
            && !$owner->has_cap('ttos_modules')
            && $manager
            && $manager->has_cap('ttos_access')
            && $manager->has_cap('ttos_manage_menu')
            && !$manager->has_cap('ttos_manage_settings')
            && !$manager->has_cap('ttos_modules')
            && $kitchen
            && $kitchen->has_cap('ttos_view_orders')
            && $kitchen->has_cap('ttos_update_orders')
            && !$kitchen->has_cap('ttos_manage_settings');
    }

    private static function pages_ready(): bool {
        if (!class_exists('TTOS_Page_Manager')) {
            return false;
        }
        $required = array('menu','cart','checkout','account','tracker','allergens','delivery');
        foreach ($required as $key) {
            $status = TTOS_Page_Manager::status($key);
            if (($status['state'] ?? '') !== 'ready') {
                return false;
            }
        }
        return true;
    }

    private static function checkout_ready(): bool {
        foreach (array('woocommerce_cart_page_id','woocommerce_checkout_page_id','woocommerce_myaccount_page_id') as $option) {
            if ((int) get_option($option) <= 0) {
                return false;
            }
        }
        return true;
    }

    private static function payments_ready(): bool {
        if (!class_exists('WC_Payment_Gateways')) {
            return false;
        }
        $gateways = WC_Payment_Gateways::instance()->payment_gateways();
        foreach ($gateways as $gateway) {
            if (isset($gateway->enabled) && $gateway->enabled === 'yes') {
                return true;
            }
        }
        return false;
    }
}

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
                foreach ($caps as $cap) {
                    $role->add_cap($cap);
                }
                $role->add_cap('upload_files');
            }
        }
        foreach (array('takeaway_kitchen','takeaway_driver') as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach (array('read','ttos_access','ttos_view_orders','ttos_update_orders') as $cap) {
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

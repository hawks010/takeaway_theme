<?php

defined('ABSPATH') || exit;

final class TTOS_Activator {
    public static function activate(): void {
        self::add_roles();
        self::seed_settings();
        self::migrate_branding_tokens();
        self::create_pages();
        update_option('ttos_version', TTOS_VERSION, false);
        update_option('ttos_do_activation_redirect', '1', false);
        flush_rewrite_rules();
    }

    public static function maybe_upgrade(): void {
        $installed = (string) get_option('ttos_version', '');
        if ($installed === TTOS_VERSION) {
            return;
        }

        self::add_roles();
        self::seed_settings();
        self::migrate_branding_tokens();
        update_option('ttos_version', TTOS_VERSION, false);
    }

    /**
     * v1.3.0 token migration. Adds missing branding keys only — existing
     * saved values are never overwritten. New token values are seeded from
     * the closest legacy colour so upgraded sites keep their current look:
     * accent <- secondary, bg <- cream, text <- dark.
     */
    private static function migrate_branding_tokens(): void {
        $settings = get_option('ttos_settings', array());
        if (!is_array($settings)) {
            return;
        }
        $branding = isset($settings['branding']) && is_array($settings['branding']) ? $settings['branding'] : array();
        $defaults = TTOS_Settings::defaults()['branding'];
        $seed_from_legacy = array('accent' => 'secondary', 'bg' => 'cream', 'text' => 'dark');

        $changed = false;
        foreach ($defaults as $key => $default_value) {
            if (array_key_exists($key, $branding) && $branding[$key] !== '') {
                continue;
            }
            $value = $default_value;
            $legacy_key = $seed_from_legacy[$key] ?? '';
            if ($legacy_key && !empty($branding[$legacy_key])) {
                $value = $branding[$legacy_key];
            }
            $branding[$key] = $value;
            $changed = true;
        }

        if ($changed) {
            $settings['branding'] = $branding;
            update_option('ttos_settings', $settings, false);
        }
    }

    public static function deactivate(): void {
        $timestamp = wp_next_scheduled('ttos_retry_integrations');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'ttos_retry_integrations');
        }
        flush_rewrite_rules();
    }

    private static function add_roles(): void {
        $owner_caps = array(
            'read'                  => true,
            'ttos_access'           => true,
            'ttos_manage'           => true,
            'ttos_view_orders'      => true,
            'ttos_update_orders'    => true,
            'ttos_manage_menu'      => true,
            'ttos_manage_settings'  => true,
            'ttos_view_reports'     => true,
            'upload_files'          => true,
        );
        $manager_caps = array(
            'read'                  => true,
            'ttos_access'           => true,
            'ttos_manage'           => true,
            'ttos_view_orders'      => true,
            'ttos_update_orders'    => true,
            'ttos_manage_menu'      => true,
            'ttos_view_reports'     => true,
            'upload_files'          => true,
        );
        $kitchen_caps = array(
            'read'               => true,
            'ttos_access'        => true,
            'ttos_view_orders'   => true,
            'ttos_update_orders' => true,
        );

        add_role('takeaway_owner', __('Takeaway Owner', 'takeaway-os'), $owner_caps);
        add_role('takeaway_manager', __('Takeaway Manager', 'takeaway-os'), $manager_caps);
        add_role('takeaway_kitchen', __('Kitchen Staff', 'takeaway-os'), $kitchen_caps);
        add_role('takeaway_driver', __('Delivery Driver', 'takeaway-os'), $kitchen_caps);

        $managed_caps = array('ttos_access','ttos_manage','ttos_view_orders','ttos_update_orders','ttos_manage_menu','ttos_manage_settings','ttos_view_reports','ttos_modules');
        self::sync_role_caps('takeaway_owner', $owner_caps, $managed_caps);
        self::sync_role_caps('takeaway_manager', $manager_caps, $managed_caps);
        self::sync_role_caps('takeaway_kitchen', $kitchen_caps, $managed_caps);
        self::sync_role_caps('takeaway_driver', $kitchen_caps, $managed_caps);

        $admin = get_role('administrator');
        if ($admin) {
            foreach (array('ttos_access','ttos_manage','ttos_view_orders','ttos_update_orders','ttos_manage_menu','ttos_manage_settings','ttos_view_reports','ttos_modules') as $cap) {
                $admin->add_cap($cap);
            }
        }
    }

    private static function sync_role_caps(string $role_key, array $wanted_caps, array $managed_caps): void {
        $role = get_role($role_key);
        if (!$role) {
            return;
        }

        foreach ($wanted_caps as $cap => $grant) {
            if ($grant) {
                $role->add_cap($cap);
            }
        }

        foreach ($managed_caps as $cap) {
            if (empty($wanted_caps[$cap])) {
                $role->remove_cap($cap);
            }
        }
    }

    private static function seed_settings(): void {
        if (!get_option('ttos_settings')) {
            update_option('ttos_settings', TTOS_Settings::defaults(), false);
        }
    }

    private static function create_pages(): void {
        // Safe page creation: if an old page already exists but does not contain
        // the required shortcode/marker, create a fresh Takeaway page instead of
        // overwriting old site content.
        TTOS_Page_Manager::ensure_all('fresh_if_unsafe');
        TTOS_Page_Manager::sync_woocommerce_page_options();
    }
}

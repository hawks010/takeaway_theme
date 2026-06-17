<?php
/**
 * Takeaway OS uninstall routine.
 *
 * Safe by default: restaurant data is preserved unless the site administrator
 * enabled the explicit clean-uninstall setting inside Takeaway OS.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$settings  = get_option('ttos_settings', array());
$retention = array();
if (is_array($settings) && isset($settings['data_retention']) && is_array($settings['data_retention'])) {
    $retention = $settings['data_retention'];
}

define('TTOS_UNINSTALL_ERASE', !empty($retention['erase_on_uninstall']) && $retention['erase_on_uninstall'] === '1');

// Always clear scheduled jobs and one-shot activation flags when the plugin is removed.
foreach (array('ttos_retry_integrations', 'ttos_inventory_daily_reset') as $hook) {
    $timestamp = wp_next_scheduled($hook);
    while ($timestamp) {
        wp_unschedule_event($timestamp, $hook);
        $timestamp = wp_next_scheduled($hook);
    }
}
delete_option('ttos_do_activation_redirect');

if (!TTOS_UNINSTALL_ERASE) {
    // Preserve settings, menus, pages, orders and customer history for safe reinstall.
    return;
}

function ttos_uninstall_delete_posts_by_meta(string $post_type, string $meta_key, string $meta_value = '1'): void {
    $query = new WP_Query(array(
        'post_type'      => $post_type,
        'post_status'    => 'any',
        'posts_per_page' => 100,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'   => $meta_key,
                'value' => $meta_value,
            ),
        ),
    ));

    while ($query->have_posts()) {
        foreach ($query->posts as $post_id) {
            wp_delete_post((int) $post_id, true);
        }
        $query = new WP_Query(array(
            'post_type'      => $post_type,
            'post_status'    => 'any',
            'posts_per_page' => 100,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => $meta_key,
                    'value' => $meta_value,
                ),
            ),
        ));
    }
    wp_reset_postdata();
}

if (!empty($retention['delete_generated_pages']) && $retention['delete_generated_pages'] === '1') {
    ttos_uninstall_delete_posts_by_meta('page', '_ttos_generated_page', '1');
}

if (!empty($retention['delete_menu_products']) && $retention['delete_menu_products'] === '1') {
    ttos_uninstall_delete_posts_by_meta('product', '_ttos_menu_item', '1');
}

if (!empty($retention['delete_generated_coupons']) && $retention['delete_generated_coupons'] === '1') {
    ttos_uninstall_delete_posts_by_meta('shop_coupon', '_ttos_generated_coupon', '1');
}

if (!empty($retention['delete_customer_meta']) && $retention['delete_customer_meta'] === '1') {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ttos_%' OR meta_key LIKE '_ttos_%'");
}

if (!empty($retention['delete_roles']) && $retention['delete_roles'] === '1') {
    $caps = array('ttos_access','ttos_manage','ttos_view_orders','ttos_update_orders','ttos_manage_menu','ttos_manage_settings','ttos_view_reports','ttos_modules');
    foreach (wp_roles()->roles as $role_key => $role_data) {
        $role = get_role($role_key);
        if (!$role) {
            continue;
        }
        foreach ($caps as $cap) {
            $role->remove_cap($cap);
        }
    }
    foreach (array('takeaway_owner', 'takeaway_manager', 'takeaway_kitchen', 'takeaway_driver') as $role_key) {
        remove_role($role_key);
    }
}

// Delete all Takeaway OS options and transients after optional content clean-up.
global $wpdb;
$option_names = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'ttos\\_%' OR option_name LIKE '_transient_ttos\\_%' OR option_name LIKE '_transient_timeout_ttos\\_%'");
foreach ($option_names as $option_name) {
    delete_option($option_name);
}

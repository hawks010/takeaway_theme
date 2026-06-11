<?php
/**
 * Plugin Name:       Takeaway OS
 * Plugin URI:        https://inkfire.co.uk
 * Description:       A clean takeaway management overlay for WordPress and WooCommerce: launch wizard, menu builder, order cockpit, CRM, modules and restaurant settings.
 * Version:           1.3.0-dev.3
 * Author:            Inkfire
 * Author URI:        https://inkfire.co.uk
 * Text Domain:       takeaway-os
 * Requires at least: 6.4
 * Requires PHP:      7.4
 */

defined('ABSPATH') || exit;

define('TTOS_VERSION', '1.3.0-dev.3');
define('TTOS_FILE', __FILE__);
define('TTOS_DIR', plugin_dir_path(__FILE__));
define('TTOS_URL', plugin_dir_url(__FILE__));
define('TTOS_BASENAME', plugin_basename(__FILE__));

$ttos_files = array(
    'includes/class-settings.php',
    'includes/class-site-content.php',
    'includes/class-page-manager.php',
    'includes/class-activator.php',
    'includes/class-plugin-checker.php',
    'includes/class-setup-health.php',
    'includes/class-onboarding.php',
    'includes/class-woocommerce.php',
    'includes/class-analytics.php',
    'includes/class-features.php',
    'includes/class-operations.php',
    'includes/class-hardening.php',
    'includes/class-production.php',
    'includes/class-admin.php',
    'includes/class-shortcodes.php',
);

foreach ($ttos_files as $ttos_file) {
    require_once TTOS_DIR . $ttos_file;
}

register_activation_hook(TTOS_FILE, array('TTOS_Activator', 'activate'));
register_deactivation_hook(TTOS_FILE, array('TTOS_Activator', 'deactivate'));


add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', TTOS_FILE, true);
    }
});

add_action('plugins_loaded', function () {
    TTOS_Activator::maybe_upgrade();
    TTOS_Settings::hooks();
    TTOS_Site_Content::hooks();
    TTOS_Plugin_Checker::hooks();
    TTOS_Setup_Health::hooks();
    TTOS_WooCommerce::hooks();
    TTOS_Analytics::hooks();
    TTOS_Features::hooks();
    TTOS_Operations::hooks();
    TTOS_Hardening::hooks();
    TTOS_Production::hooks();
    TTOS_Admin::hooks();
    TTOS_Shortcodes::hooks();
});

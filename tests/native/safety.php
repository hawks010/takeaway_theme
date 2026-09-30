<?php
// CI fixture only. This is not distributed inside Takeaway OS.
if (!defined('TTOS_FIXTURE_ONLY') || TTOS_FIXTURE_ONLY !== true) { return; }
add_filter('pre_wp_mail', function ($return, $atts) { return true; }, 10, 2);
add_filter('pre_http_request', function () { return new WP_Error('fixture_network_disabled', 'External requests are disabled in this local test fixture.'); }, 1, 3);
add_action('init', function () {
    if (!isset($_GET['ttos_fixture_runtime']) || PHP_SAPI !== 'cli-server' || wp_get_environment_type() !== 'local' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') return;
    $ini = array();
    foreach (array('opcache.enable', 'opcache.enable_cli', 'opcache.jit', 'opcache.jit_buffer_size', 'pcre.jit', 'max_execution_time') as $key) $ini[$key] = ini_get($key);
    $status = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
    wp_send_json(array('php' => PHP_VERSION, 'sapi' => PHP_SAPI, 'pcre' => PCRE_VERSION, 'ini' => $ini, 'opcache_enabled' => $status !== false && !empty($status['opcache_enabled']), 'jit' => $status['jit'] ?? null));
}, 1);
add_action('plugins_loaded', function () {
    if (!class_exists('WC_Payment_Gateway')) return;
    class TTOS_Fixture_Gateway extends WC_Payment_Gateway {
        public function __construct() { $this->id = 'ttos_fixture'; $this->title = 'Offline test gateway'; $this->enabled = 'yes'; $this->has_fields = false; }
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $order->payment_complete('fixture-' . $order_id);
            WC()->cart->empty_cart();
            return array('result' => 'success', 'redirect' => $this->get_return_url($order));
        }
    }
    add_filter('woocommerce_payment_gateways', function ($gateways) { $gateways[] = 'TTOS_Fixture_Gateway'; return $gateways; });
}, 20);

// Only the disposable fixture exposes this helper to authenticated owners.
add_action('admin_init', function () {
    if (isset($_GET['ttos_fixture_rest_nonce']) && current_user_can('ttos_manage_settings')) {
        wp_send_json(array('nonce' => wp_create_nonce('wp_rest')));
    }
});

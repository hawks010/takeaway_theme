<?php

defined('ABSPATH') || exit;

final class TTOS_Plugin_Checker {
    public static function hooks(): void {
        add_action('wp_ajax_ttos_install_plugin', array(__CLASS__, 'ajax_install_plugin'));
    }

    public static function plugins(): array {
        return array(
            'woocommerce' => array(
                'name'        => 'WooCommerce',
                'file'        => 'woocommerce/woocommerce.php',
                'slug'        => 'woocommerce',
                'required'    => true,
                'description' => 'Orders, cart, checkout, customers, tax, coupons and refunds.',
                'depends'     => array(),
            ),
            'stripe' => array(
                'name'        => 'WooCommerce Stripe Payment Gateway',
                'file'        => 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php',
                'slug'        => 'woocommerce-gateway-stripe',
                'required'    => false,
                'description' => 'Recommended card, Apple Pay and Google Pay gateway. Install after WooCommerce is active.',
                'depends'     => array('woocommerce'),
            ),
            'fluent-smtp' => array(
                'name'        => 'FluentSMTP',
                'file'        => 'fluent-smtp/fluent-smtp.php',
                'slug'        => 'fluent-smtp',
                'required'    => false,
                'description' => 'Recommended reliable transactional email delivery.',
                'depends'     => array(),
            ),
        );
    }

    public static function plugin_by_key(string $key): ?array {
        $plugins = self::plugins();
        return $plugins[$key] ?? null;
    }

    public static function installer_payload(): array {
        $payload = array();
        foreach (self::plugins() as $key => $plugin) {
            $payload[$key] = array(
                'key'         => $key,
                'name'        => $plugin['name'],
                'slug'        => $plugin['slug'],
                'file'        => $plugin['file'],
                'required'    => !empty($plugin['required']),
                'description' => $plugin['description'],
                'status'      => self::status($plugin),
                'depends'     => $plugin['depends'] ?? array(),
            );
        }
        return $payload;
    }

    public static function required_complete(): bool {
        foreach (self::plugins() as $plugin) {
            if (!empty($plugin['required']) && self::status($plugin) !== 'active') {
                return false;
            }
        }
        return true;
    }

    public static function is_active(string $file): bool {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active($file);
    }

    public static function is_installed(string $file): bool {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        return isset($plugins[$file]);
    }

    public static function status(array $plugin): string {
        if (self::is_active($plugin['file'])) {
            return 'active';
        }
        if (self::is_installed($plugin['file'])) {
            return 'installed';
        }
        return 'missing';
    }

    public static function action_url(array $plugin): string {
        $status = self::status($plugin);
        if ($status === 'missing') {
            return wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=' . $plugin['slug']), 'install-plugin_' . $plugin['slug']);
        }
        if ($status === 'installed') {
            return wp_nonce_url(self_admin_url('plugins.php?action=activate&plugin=' . rawurlencode($plugin['file'])), 'activate-plugin_' . $plugin['file']);
        }
        return '';
    }

    private static function unmet_dependencies(array $plugin): array {
        $unmet = array();
        foreach (($plugin['depends'] ?? array()) as $dependency_key) {
            $dependency = self::plugin_by_key((string) $dependency_key);
            if ($dependency && self::status($dependency) !== 'active') {
                $unmet[] = $dependency['name'];
            }
        }
        return $unmet;
    }

    private static function clean_error_message($message): string {
        if (is_wp_error($message)) {
            $message = $message->get_error_message();
        }
        $message = wp_strip_all_tags((string) $message);
        $message = html_entity_decode($message, ENT_QUOTES, get_bloginfo('charset'));
        $message = preg_replace('/\s+/', ' ', $message);
        return trim($message) ?: 'The plugin could not be installed or activated.';
    }

    public static function ajax_install_plugin(): void {
        check_ajax_referer('ttos_plugin_installer', 'nonce');

        if (!current_user_can('install_plugins') || !current_user_can('activate_plugins')) {
            wp_send_json_error(array('message' => 'You need permission to install and activate plugins.'));
        }

        $key = sanitize_key(wp_unslash($_POST['key'] ?? ''));
        $slug = sanitize_key(wp_unslash($_POST['slug'] ?? ''));
        $plugins = self::plugins();
        $plugin = null;
        foreach ($plugins as $plugin_key => $item) {
            if ($plugin_key === $key || $item['slug'] === $slug) {
                $plugin = $item;
                $key = $plugin_key;
                break;
            }
        }

        if (!$plugin) {
            wp_send_json_error(array('message' => 'Unknown plugin requested.'));
        }

        $unmet = self::unmet_dependencies($plugin);
        if (!empty($unmet)) {
            wp_send_json_error(array(
                'message' => $plugin['name'] . ' needs this active first: ' . implode(', ', $unmet) . '.',
                'status'  => self::status($plugin),
                'code'    => 'dependency_pending',
            ));
        }

        $status = self::status($plugin);

        if ($status === 'missing') {
            $installed = self::install_from_org($plugin['slug']);
            if (is_wp_error($installed)) {
                wp_send_json_error(array('message' => self::clean_error_message($installed), 'status' => 'missing'));
            }
        }

        if (!self::is_active($plugin['file'])) {
            if (!function_exists('activate_plugin')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $result = activate_plugin($plugin['file']);
            if (is_wp_error($result)) {
                wp_send_json_error(array(
                    'message' => self::clean_error_message($result),
                    'status'  => self::status($plugin),
                    'code'    => 'activation_failed',
                ));
            }
        }

        $final_status = self::status($plugin);
        if ($final_status !== 'active') {
            wp_send_json_error(array(
                'message' => $plugin['name'] . ' installed, but WordPress did not report it as active. Try activating it again or check the Plugins screen.',
                'status'  => $final_status,
                'code'    => 'not_active_after_activation',
            ));
        }

        wp_send_json_success(array(
            'message'       => $plugin['name'] . ' is installed and active.',
            'status'        => $final_status,
            'required'      => !empty($plugin['required']),
            'complete'      => self::required_complete(),
            'reloadNeeded'  => $key === 'woocommerce',
        ));
    }

    private static function install_from_org(string $slug) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

        $api = plugins_api('plugin_information', array(
            'slug'   => $slug,
            'fields' => array(
                'sections' => false,
            ),
        ));

        if (is_wp_error($api)) {
            return $api;
        }
        if (empty($api->download_link)) {
            return new WP_Error('ttos_no_download', 'No download link was returned for this plugin.');
        }

        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);

        ob_start();
        $result = $upgrader->install($api->download_link);
        ob_end_clean();

        if (is_wp_error($result)) {
            return $result;
        }
        if (is_wp_error($skin->result)) {
            return $skin->result;
        }
        if (!$result) {
            return new WP_Error('ttos_install_failed', 'WordPress could not install the plugin. It may need FTP/filesystem credentials on this host.');
        }

        return true;
    }
}

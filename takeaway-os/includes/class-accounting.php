<?php

defined('ABSPATH') || exit;

/**
 * Accounting Bridge.
 *
 * Handles credential storage (encrypted) for Xero, QuickBooks Online,
 * and FreeAgent, plus a CSV export that works for all three.
 *
 * OAuth token exchange is done server-side to avoid exposing client secrets
 * in the browser. Each provider's redirect URI points to a WP REST endpoint
 * that completes the code exchange, stores the access/refresh tokens encrypted,
 * and redirects the admin back to the Settings > Payments page.
 *
 * For v1.3.x: credentials are stored, connection status is exposed, and CSV
 * export is fully functional. Live sync (posting invoices automatically) is
 * a v1.4 feature gated by the `accounting` module flag.
 */
final class TTOS_Accounting {

    const OPT_SETTINGS   = 'ttos_accounting_settings';
    const OPT_TOKENS     = 'ttos_accounting_tokens';
    const CRON_HOOK      = 'ttos_accounting_daily_export';
    const REDIRECT_ROUTE = '/accounting/oauth-callback';

    public static function hooks(): void {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_action('init', array(__CLASS__, 'schedule_cron'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'run_daily_export'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REST routes
    // ─────────────────────────────────────────────────────────────────────────

    public static function register_routes(): void {
        $auth = array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'handle_oauth_callback'),
            'permission_callback' => '__return_true', // verified inside by state param
            'args'                => array(
                'code'     => array('sanitize_callback' => 'sanitize_text_field'),
                'state'    => array('sanitize_callback' => 'sanitize_text_field'),
                'provider' => array('sanitize_callback' => 'sanitize_key'),
            ),
        );
        register_rest_route('ttos/v1', self::REDIRECT_ROUTE, $auth);

        register_rest_route('ttos/v1', '/accounting/status', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'handle_status'),
            'permission_callback' => array(TTOS_API::class, 'owner_permission'),
        ));

        register_rest_route('ttos/v1', '/accounting/export-csv', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'handle_csv_export'),
            'permission_callback' => array(TTOS_API::class, 'owner_permission'),
        ));

        register_rest_route('ttos/v1', '/accounting/disconnect', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_disconnect'),
            'permission_callback' => array(TTOS_API::class, 'owner_permission'),
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Settings helpers
    // ─────────────────────────────────────────────────────────────────────────

    public static function get_settings(): array {
        return (array) get_option(self::OPT_SETTINGS, array());
    }

    public static function save_settings(array $settings): void {
        // Encrypt client_secret before storage
        if (!empty($settings['client_secret'])) {
            $settings['client_secret_enc'] = TTOS_API::encrypt($settings['client_secret']);
            unset($settings['client_secret']);
        }
        update_option(self::OPT_SETTINGS, $settings, false);
    }

    public static function get_provider(): string {
        return sanitize_key(self::get_settings()['provider'] ?? '');
    }

    public static function is_connected(): bool {
        $tokens = (array) get_option(self::OPT_TOKENS, array());
        return !empty($tokens['access_token']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // OAuth flow helpers
    // ─────────────────────────────────────────────────────────────────────────

    public static function get_auth_url(string $provider): string {
        $settings = self::get_settings();
        $client_id = sanitize_text_field($settings['client_id'] ?? '');
        if (!$client_id || !$provider) return '';

        $redirect_uri = rest_url('ttos/v1' . self::REDIRECT_ROUTE);
        $state = wp_create_nonce('ttos_oauth_' . $provider);

        $endpoints = array(
            'xero'      => 'https://login.xero.com/identity/connect/authorize',
            'qbo'       => 'https://appcenter.intuit.com/connect/oauth2',
            'freeagent' => 'https://api.freeagent.com/v2/approve_app',
        );
        $scopes = array(
            'xero'      => 'openid profile email accounting.transactions accounting.contacts',
            'qbo'       => 'com.intuit.quickbooks.accounting openid profile email',
            'freeagent' => '',
        );

        $base = $endpoints[$provider] ?? '';
        if (!$base) return '';

        return add_query_arg(array(
            'response_type' => 'code',
            'client_id'     => rawurlencode($client_id),
            'redirect_uri'  => rawurlencode($redirect_uri),
            'scope'         => rawurlencode($scopes[$provider] ?? ''),
            'state'         => rawurlencode($state . '|' . $provider),
        ), $base);
    }

    public static function handle_oauth_callback(\WP_REST_Request $request): void {
        $code     = sanitize_text_field($request->get_param('code') ?? '');
        $state    = sanitize_text_field($request->get_param('state') ?? '');
        $parts    = explode('|', $state, 2);
        $nonce    = $parts[0] ?? '';
        $provider = sanitize_key($parts[1] ?? '');

        if (!wp_verify_nonce($nonce, 'ttos_oauth_' . $provider) || !$code || !$provider) {
            wp_safe_redirect(admin_url('admin.php?page=takeaway-os-payments&ttos_oauth_error=invalid_state'));
            exit;
        }

        $result = self::exchange_code($provider, $code);
        if (is_wp_error($result)) {
            wp_safe_redirect(admin_url('admin.php?page=takeaway-os-payments&ttos_oauth_error=' . rawurlencode($result->get_error_message())));
            exit;
        }

        // Store tokens encrypted
        $token_data = array(
            'access_token'  => TTOS_API::encrypt($result['access_token'] ?? ''),
            'refresh_token' => TTOS_API::encrypt($result['refresh_token'] ?? ''),
            'expires_at'    => time() + (int) ($result['expires_in'] ?? 3600),
            'provider'      => $provider,
        );
        if (!empty($result['x_refreshtoken_expires_in'])) {
            $token_data['refresh_expires_at'] = time() + (int) $result['x_refreshtoken_expires_in'];
        }
        // Xero returns tenant_id as separate call; store raw data for extended fields
        update_option(self::OPT_TOKENS, $token_data, false);

        wp_safe_redirect(admin_url('admin.php?page=takeaway-os-payments&ttos_oauth_ok=1'));
        exit;
    }

    private static function exchange_code(string $provider, string $code): array|\WP_Error {
        $settings     = self::get_settings();
        $client_id    = sanitize_text_field($settings['client_id'] ?? '');
        $client_secret_enc = $settings['client_secret_enc'] ?? '';
        $client_secret = $client_secret_enc ? TTOS_API::decrypt($client_secret_enc) : '';

        if (!$client_id || !$client_secret) {
            return new \WP_Error('missing_credentials', 'Client ID or secret not configured.');
        }

        $token_urls = array(
            'xero'      => 'https://identity.xero.com/connect/token',
            'qbo'       => 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer',
            'freeagent' => 'https://api.freeagent.com/v2/token_endpoint',
        );
        $url = $token_urls[$provider] ?? '';
        if (!$url) return new \WP_Error('unknown_provider', 'Unknown provider: ' . $provider);

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
            ),
            'body' => http_build_query(array(
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => rest_url('ttos/v1' . self::REDIRECT_ROUTE),
            )),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) return $response;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) {
            return new \WP_Error('token_error', $body['error_description'] ?? 'Token exchange failed.');
        }
        return $body;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REST handlers
    // ─────────────────────────────────────────────────────────────────────────

    public static function handle_status(\WP_REST_Request $request): \WP_REST_Response {
        $provider  = self::get_provider();
        $connected = self::is_connected();
        $auth_url  = $provider && !$connected ? self::get_auth_url($provider) : null;
        return new \WP_REST_Response(array(
            'provider'   => $provider,
            'connected'  => $connected,
            'auth_url'   => $auth_url,
            'export_url' => rest_url('ttos/v1/accounting/export-csv'),
        ));
    }

    public static function handle_disconnect(\WP_REST_Request $request): \WP_REST_Response {
        delete_option(self::OPT_TOKENS);
        return new \WP_REST_Response(array('disconnected' => true));
    }

    public static function handle_csv_export(\WP_REST_Request $request): void {
        $from = sanitize_text_field($request->get_param('from') ?? gmdate('Y-m-01'));
        $to   = sanitize_text_field($request->get_param('to')   ?? gmdate('Y-m-d'));
        $csv  = self::generate_csv($from, $to);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="ttos-orders-' . sanitize_file_name($from . '-' . $to) . '.csv"');
        header('Cache-Control: no-cache, no-store');
        echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput — CSV, not HTML
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CSV generation (HPOS-compatible)
    // ─────────────────────────────────────────────────────────────────────────

    public static function generate_csv(string $from = '', string $to = ''): string {
        if (!function_exists('wc_get_orders')) return '';

        $args = array(
            'limit'  => 500,
            'status' => array('wc-completed', 'wc-processing'),
            'return' => 'ids',
        );
        if ($from) $args['date_created'] = $from . '...' . ($to ?: gmdate('Y-m-d'));

        $order_ids = wc_get_orders($args);
        $rows      = array();
        $headers   = array('Date','Order #','Customer Name','Email','Items','Subtotal','Tax','Total','Payment Method','Fulfilment','Status');
        $rows[]    = self::csv_row($headers);

        foreach ($order_ids as $oid) {
            $order = wc_get_order($oid);
            if (!$order) continue;
            $items = array();
            foreach ($order->get_items() as $item) {
                $items[] = $item->get_quantity() . 'x ' . $item->get_name();
            }
            $rows[] = self::csv_row(array(
                $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
                $order->get_order_number(),
                trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                $order->get_billing_email(),
                implode('; ', $items),
                $order->get_subtotal(),
                $order->get_total_tax(),
                $order->get_total(),
                $order->get_payment_method_title(),
                $order->get_meta('_ttos_fulfilment_method') ?: 'delivery',
                wc_get_order_status_name($order->get_status()),
            ));
        }

        return implode("\r\n", $rows);
    }

    private static function csv_row(array $fields): string {
        return implode(',', array_map(function ($f) {
            $f = (string) $f;
            if (strpbrk($f, '",\n\r') !== false) {
                return '"' . str_replace('"', '""', $f) . '"';
            }
            return $f;
        }, $fields));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cron — daily auto-export (foundation for future live sync)
    // ─────────────────────────────────────────────────────────────────────────

    public static function schedule_cron(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK);
        }
    }

    public static function run_daily_export(): void {
        if (!TTOS_Settings::module_enabled('accounting')) return;
        if (!self::is_connected()) return;
        // In v1.4 this will POST the day's orders to the connected provider.
        // For now, store a daily CSV snapshot as a transient for the dashboard.
        $yesterday = gmdate('Y-m-d', strtotime('-1 day'));
        $csv = self::generate_csv($yesterday, $yesterday);
        set_transient('ttos_accounting_last_export', array(
            'date'  => $yesterday,
            'rows'  => substr_count($csv, "\r\n"),
            'bytes' => strlen($csv),
        ), 48 * HOUR_IN_SECONDS);
    }
}

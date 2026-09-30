<?php
/** Generic WooCommerce CSV downloads. No provider sync and no public export files. */
defined('ABSPATH') || exit;

final class TTOS_Accounting {
    const NAMESPACE = 'ttos/v1';
    const TABLE_NAME = 'ttos_accounting_log';

    public static function hooks(): void {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_action('admin_post_ttos_download_accounting_export', array(__CLASS__, 'download_export'));
    }

    public static function create_log_table(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            provider VARCHAR(64) NOT NULL DEFAULT '',
            exported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            row_count INT(11) NOT NULL DEFAULT 0,
            file_path TEXT NOT NULL,
            PRIMARY KEY (id),
            KEY idx_provider (provider),
            KEY idx_exported_at (exported_at)
        ) {$charset};");
    }

    public static function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/accounting/export', array(
            'methods' => 'POST', 'callback' => array(__CLASS__, 'rest_export'),
            'permission_callback' => array(__CLASS__, 'admin_permission'),
            'args' => array(
                'provider' => array('type' => 'string', 'default' => 'generic', 'sanitize_callback' => 'sanitize_key'),
                'from' => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'),
                'to' => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'),
            ),
        ));
    }

    public static function admin_permission(): bool {
        return current_user_can('ttos_manage_settings');
    }

    public static function rest_export(WP_REST_Request $request): WP_REST_Response {
        $from_string = (string) $request->get_param('from');
        $to_string = (string) $request->get_param('to');
        $from = self::parse_date($from_string ?: wp_date('Y-m-01'), false);
        $to = self::parse_date($to_string ?: wp_date('Y-m-d'), true);
        if (!$from || !$to || $from > $to) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Choose a valid date range using YYYY-MM-DD.'), 400);
        }
        $provider = sanitize_key((string) $request->get_param('provider')) ?: 'generic';
        $from_string = $from->format('Y-m-d');
        $to_string = $to->format('Y-m-d');
        $url = add_query_arg(array(
            'action' => 'ttos_download_accounting_export', 'provider' => $provider,
            'from' => $from_string, 'to' => $to_string,
            '_wpnonce' => wp_create_nonce(self::nonce_action($provider, $from_string, $to_string)),
        ), admin_url('admin-post.php'));
        return new WP_REST_Response(array('success' => true, 'download_url' => $url,
            'format' => 'generic_csv', 'message' => 'Downloads current WooCommerce records for this range; this is not provider synchronisation.'), 200);
    }

    private static function parse_date(string $value, bool $end): ?DateTime {
        $date = DateTime::createFromFormat('!Y-m-d', $value, wp_timezone());
        if (!$date || $date->format('Y-m-d') !== $value) return null;
        return $end ? $date->setTime(23, 59, 59) : $date;
    }

    private static function nonce_action(string $provider, string $from, string $to): string {
        return 'ttos_accounting_' . $provider . '_' . $from . '_' . $to;
    }

    /** Build into a temporary stream first so query errors never produce a success download. */
    public static function write_csv($stream, DateTime $from, DateTime $to): int {
        if (!is_resource($stream) || !function_exists('wc_get_orders')) {
            throw new RuntimeException('WooCommerce export is unavailable.');
        }
        fputcsv($stream, array('order_id', 'date', 'customer_email', 'currency', 'total', 'refunded_total', 'payment_method', 'status'), ',', '"', '');
        $count = 0;
        for ($page = 1; ; $page++) {
            $orders = wc_get_orders(array('type' => 'shop_order', 'limit' => 100, 'page' => $page,
                'date_created' => $from->getTimestamp() . '...' . $to->getTimestamp(),
                'orderby' => 'ID', 'order' => 'ASC', 'return' => 'objects'));
            if (!is_array($orders)) throw new RuntimeException('WooCommerce could not load export records.');
            foreach ($orders as $order) {
                if (!$order instanceof WC_Order) continue;
                $row = array($order->get_id(), $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
                    $order->get_billing_email(), $order->get_currency(), $order->get_total(), $order->get_total_refunded(),
                    $order->get_payment_method_title(), $order->get_status());
                if (false === fputcsv($stream, array_map(array(__CLASS__, 'csv_cell'), $row), ',', '"', '')) {
                    throw new RuntimeException('The export stream could not be written.');
                }
                $count++;
            }
            if (count($orders) < 100) break;
        }
        return $count;
    }

    public static function csv_cell($value): string {
        $value = (string) $value;
        // Preserve ordinary numbers; prevent spreadsheet execution of user-provided text.
        return preg_match('/^[\x00-\x20]*[=+@-]/', $value) && !is_numeric($value) ? "'" . $value : $value;
    }

    public static function download_export(): void {
        if (!self::admin_permission()) wp_die('Not allowed.', '', array('response' => 403));
        $args = array();
        foreach (array('provider', 'from', 'to') as $key) {
            $args[$key] = isset($_GET[$key]) && is_string($_GET[$key]) ? wp_unslash($_GET[$key]) : '';
        }
        $provider = sanitize_key($args['provider']) ?: 'generic';
        check_admin_referer(self::nonce_action($provider, $args['from'], $args['to']));
        $from = self::parse_date($args['from'], false);
        $to = self::parse_date($args['to'], true);
        if (!$from || !$to || $from > $to) wp_die('Invalid date range.', '', array('response' => 400));
        $stream = fopen('php://temp/maxmemory:2097152', 'w+');
        if (!$stream) wp_die('Export unavailable.', '', array('response' => 503));
        try {
            $count = self::write_csv($stream, $from, $to);
        } catch (Throwable $e) {
            fclose($stream);
            wp_die('The export could not be generated. Please try again.', '', array('response' => 503));
        }
        // Log only the fact of the export; no customer data or publicly addressable file.
        global $wpdb;
        $wpdb->insert($wpdb->prefix . self::TABLE_NAME, array('provider' => $provider,
            'exported_at' => current_time('mysql'), 'row_count' => $count, 'file_path' => ''), array('%s', '%s', '%d', '%s'));
        rewind($stream);
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="takeaway-orders-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv"');
        header('X-Content-Type-Options: nosniff');
        echo "\xEF\xBB\xBF";
        fpassthru($stream);
        fclose($stream);
        exit;
    }

    public static function get_log(int $limit = 20): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY exported_at DESC LIMIT %d", max(1, min(100, $limit))), ARRAY_A);
        return is_array($rows) ? $rows : array();
    }
}

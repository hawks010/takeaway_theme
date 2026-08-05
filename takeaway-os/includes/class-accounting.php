<?php
/**
 * Takeaway OS Accounting Bridge
 *
 * Provides WooCommerce order export to CSV for bookkeeping providers
 * (Xero, QuickBooks, Sage, etc.), an audit log table, and a REST endpoint.
 */

defined('ABSPATH') || exit;

final class TTOS_Accounting {

    const NAMESPACE  = 'ttos/v1';
    const TABLE_NAME = 'ttos_accounting_log';

    public static function hooks(): void {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    // -------------------------------------------------------------------------
    // Activation helpers
    // -------------------------------------------------------------------------

    /**
     * Create the accounting log table. Called on plugin activation and upgrade.
     */
    public static function create_log_table(): void {
        global $wpdb;

        $table   = $wpdb->prefix . self::TABLE_NAME;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            provider      VARCHAR(64)         NOT NULL DEFAULT '',
            exported_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            row_count     INT(11)             NOT NULL DEFAULT 0,
            file_path     TEXT                NOT NULL,
            PRIMARY KEY   (id),
            KEY idx_provider (provider),
            KEY idx_exported_at (exported_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    // -------------------------------------------------------------------------
    // REST routes
    // -------------------------------------------------------------------------

    public static function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/accounting/export', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'rest_export'),
            'permission_callback' => array(__CLASS__, 'admin_permission'),
            'args'                => array(
                'provider' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'generic',
                    'sanitize_callback' => 'sanitize_key',
                ),
                'from' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'to' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }

    public static function admin_permission(): bool {
        return current_user_can('ttos_manage_settings');
    }

    public static function rest_export(WP_REST_Request $request): WP_REST_Response {
        $provider   = (string) $request->get_param('provider') ?: 'generic';
        $from_str   = (string) $request->get_param('from');
        $to_str     = (string) $request->get_param('to');

        $timezone = wp_timezone();
        $from = $from_str
            ? new DateTime($from_str, $timezone)
            : new DateTime('first day of this month 00:00:00', $timezone);
        $to   = $to_str
            ? new DateTime($to_str, $timezone)
            : new DateTime('now', $timezone);

        $path = self::export_csv($provider, $from, $to);

        if (!$path || !file_exists($path)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Export failed.'), 500);
        }

        $upload      = wp_upload_dir();
        $base_dir    = trailingslashit($upload['basedir']);
        $base_url    = trailingslashit($upload['baseurl']);
        $relative    = str_replace($base_dir, '', $path);
        $download_url = $base_url . $relative;

        return new WP_REST_Response(array(
            'success'      => true,
            'file_path'    => $path,
            'download_url' => $download_url,
        ), 200);
    }

    // -------------------------------------------------------------------------
    // Core export method
    // -------------------------------------------------------------------------

    /**
     * Export WooCommerce orders to CSV and log the export.
     *
     * @param  string   $provider  Bookkeeping provider key (e.g. 'xero', 'quickbooks').
     * @param  DateTime $from      Start of date range (inclusive).
     * @param  DateTime $to        End of date range (inclusive).
     * @return string              Absolute path to the generated CSV file, or '' on failure.
     */
    public static function export_csv(string $provider, DateTime $from, DateTime $to): string {
        if (!function_exists('wc_get_orders')) {
            return '';
        }

        // Prepare output directory
        $upload     = wp_upload_dir();
        $export_dir = trailingslashit($upload['basedir']) . 'ttos-exports';
        if (!is_dir($export_dir)) {
            wp_mkdir_p($export_dir);
        }
        // Protect with index.php and .htaccess — exports contain sensitive data
        self::maybe_protect_export_dir($export_dir);

        $filename = 'ttos-' . sanitize_key($provider) . '-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '-' . gmdate('His') . '.csv';
        $path     = trailingslashit($export_dir) . $filename;

        // Query orders
        $orders = wc_get_orders(array(
            'limit'        => -1,
            'date_created' => $from->format('Y-m-d H:i:s') . '...' . $to->format('Y-m-d H:i:s'),
            'return'       => 'objects',
        ));

        if (!is_array($orders)) {
            $orders = array();
        }

        // Write CSV
        $handle = fopen($path, 'w');
        if (!$handle) {
            return '';
        }

        // UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, array(
            'order_id',
            'date',
            'customer_email',
            'total',
            'payment_method',
            'status',
        ));

        $row_count = 0;
        foreach ($orders as $order) {
            if (!($order instanceof WC_Order)) {
                continue;
            }
            fputcsv($handle, array(
                $order->get_id(),
                $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
                $order->get_billing_email(),
                $order->get_total(),
                $order->get_payment_method_title(),
                $order->get_status(),
            ));
            $row_count++;
        }

        fclose($handle);
        @chmod($path, 0600);

        // Log the export
        self::log_export($provider, $path, $row_count);

        return $path;
    }

    // -------------------------------------------------------------------------
    // Log helpers
    // -------------------------------------------------------------------------

    private static function log_export(string $provider, string $file_path, int $row_count): void {
        global $wpdb;

        // Ensure table exists
        self::create_log_table();

        $table = $wpdb->prefix . self::TABLE_NAME;
        $wpdb->insert(
            $table,
            array(
                'provider'    => $provider,
                'exported_at' => current_time('mysql'),
                'row_count'   => $row_count,
                'file_path'   => $file_path,
            ),
            array('%s', '%s', '%d', '%s')
        );
    }

    public static function get_log(int $limit = 20): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $rows  = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY exported_at DESC LIMIT %d", $limit),
            ARRAY_A
        );
        return is_array($rows) ? $rows : array();
    }

    // -------------------------------------------------------------------------
    // Directory protection
    // -------------------------------------------------------------------------

    private static function maybe_protect_export_dir(string $path): void {
        if (!is_dir($path)) {
            return;
        }
        $index = trailingslashit($path) . 'index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }
        $htaccess = trailingslashit($path) . '.htaccess';
        $rules    = "Order deny,allow\nDeny from all\n";
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, $rules);
        }
    }
}

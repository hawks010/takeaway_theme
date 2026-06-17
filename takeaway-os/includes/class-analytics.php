<?php

defined('ABSPATH') || exit;

/**
 * Owner-friendly analytics and money reporting for Takeaway OS.
 *
 * WooCommerce remains the financial source of truth. This layer reshapes order data
 * into the daily-close, payment split and menu-performance views takeaway owners
 * actually need during service and for accountant handover.
 */
final class TTOS_Analytics {
    public static function hooks(): void {
        add_action('admin_post_ttos_export_money_csv', array(__CLASS__, 'export_money_csv'));
        add_action('admin_post_ttos_export_daily_close_csv', array(__CLASS__, 'export_daily_close_csv'));
    }

    public static function snapshot(): array {
        $range = self::range('7days');
        $week = self::report($range['start'], $range['end']);
        $today_range = self::range('today');
        $today = self::report($today_range['start'], $today_range['end']);

        return array(
            'today_orders' => $today['summary']['orders'],
            'today_revenue' => $today['summary']['gross'],
            'week_orders' => $week['summary']['orders'],
            'week_revenue' => $week['summary']['gross'],
            'aov' => $week['summary']['aov'],
            'top_items' => wp_list_pluck($week['top_items'], 'qty', 'name'),
            'recent_orders' => self::recent_orders(),
        );
    }

    public static function range(string $preset = '7days', string $from = '', string $to = ''): array {
        $tz = wp_timezone();
        $today = new DateTimeImmutable('today', $tz);
        $end_today = $today->setTime(23, 59, 59);

        if ($preset === 'custom' && $from && $to) {
            $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $from . ' 00:00:00', $tz);
            $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $to . ' 23:59:59', $tz);
            if ($start && $end && $start <= $end) {
                return array('start' => $start->getTimestamp(), 'end' => $end->getTimestamp(), 'label' => date_i18n('d M Y', $start->getTimestamp()) . ' - ' . date_i18n('d M Y', $end->getTimestamp()));
            }
        }

        switch ($preset) {
            case 'today':
                $start = $today;
                $label = 'Today';
                break;
            case 'yesterday':
                $start = $today->modify('-1 day');
                $end_today = $start->setTime(23, 59, 59);
                $label = 'Yesterday';
                break;
            case '30days':
                $start = $today->modify('-29 days');
                $label = 'Last 30 days';
                break;
            case 'month':
                $start = new DateTimeImmutable('first day of this month 00:00:00', $tz);
                $label = 'This month';
                break;
            case '7days':
            default:
                $start = $today->modify('-6 days');
                $label = 'Last 7 days';
                break;
        }

        return array('start' => $start->getTimestamp(), 'end' => $end_today->getTimestamp(), 'label' => $label);
    }

    public static function report(int $start_ts, int $end_ts): array {
        $empty = self::empty_report($start_ts, $end_ts);
        if (!TTOS_WooCommerce::active() || !function_exists('wc_get_orders')) {
            return $empty;
        }

        $orders = self::orders_between($start_ts, $end_ts);
        $summary = $empty['summary'];
        $payment = array();
        $statuses = array();
        $fulfilment = array('delivery' => 0, 'collection' => 0, 'unknown' => 0);
        $hours = array_fill(0, 24, array('orders' => 0, 'gross' => 0.0));
        $days = array();
        $items = array();
        $customers = array();
        $daily_close = array();

        foreach ($orders as $order) {
            if (!is_object($order) || !method_exists($order, 'get_total')) continue;
            $created = $order->get_date_created();
            $created_ts = $created ? $created->getTimestamp() : time();
            $hour = (int) wp_date('G', $created_ts);
            $day_key = wp_date('Y-m-d', $created_ts);
            $day_label = wp_date('D d M', $created_ts);

            $gross = (float) $order->get_total();
            $refunds = method_exists($order, 'get_total_refunded') ? (float) $order->get_total_refunded() : 0.0;
            $discounts = method_exists($order, 'get_discount_total') ? (float) $order->get_discount_total() : 0.0;
            $delivery = method_exists($order, 'get_shipping_total') ? (float) $order->get_shipping_total() : 0.0;
            $tax = method_exists($order, 'get_total_tax') ? (float) $order->get_total_tax() : 0.0;
            $fees = 0.0;
            foreach ($order->get_items('fee') as $fee) {
                $fees += method_exists($fee, 'get_total') ? (float) $fee->get_total() : 0.0;
            }

            $summary['orders']++;
            $summary['gross'] += $gross;
            $summary['net'] += max(0, $gross - $refunds);
            $summary['refunds'] += $refunds;
            $summary['discounts'] += $discounts;
            $summary['delivery_fees'] += $delivery;
            $summary['fees'] += $fees;
            $summary['tax'] += $tax;
            $summary['items'] += (int) $order->get_item_count();

            $method = $order->get_payment_method_title() ?: $order->get_payment_method() ?: 'Unknown';
            if (!isset($payment[$method])) $payment[$method] = array('orders' => 0, 'gross' => 0.0);
            $payment[$method]['orders']++;
            $payment[$method]['gross'] += $gross;

            $method_slug = strtolower((string) $order->get_payment_method());
            if (in_array($method_slug, array('cod', 'cash', 'cheque'), true) || stripos($method, 'cash') !== false) {
                $summary['cash'] += $gross;
            } else {
                $summary['card'] += $gross;
            }

            $status = wc_get_order_status_name($order->get_status());
            if (!isset($statuses[$status])) $statuses[$status] = array('orders' => 0, 'gross' => 0.0);
            $statuses[$status]['orders']++;
            $statuses[$status]['gross'] += $gross;

            $mode = strtolower((string) $order->get_meta('_ttos_fulfilment_method'));
            if (!$mode) $mode = strtolower((string) $order->get_meta('_ttos_fulfilment_type'));
            if (!$mode) $mode = strtolower((string) $order->get_meta('_ttos_fulfilment'));
            if (strpos($mode, 'deliver') !== false) $mode = 'delivery';
            elseif (strpos($mode, 'collect') !== false) $mode = 'collection';
            else $mode = 'unknown';
            $fulfilment[$mode]++;

            $hours[$hour]['orders']++;
            $hours[$hour]['gross'] += $gross;

            if (!isset($days[$day_key])) $days[$day_key] = array('label' => $day_label, 'orders' => 0, 'gross' => 0.0, 'net' => 0.0, 'discounts' => 0.0, 'refunds' => 0.0);
            $days[$day_key]['orders']++;
            $days[$day_key]['gross'] += $gross;
            $days[$day_key]['net'] += max(0, $gross - $refunds);
            $days[$day_key]['discounts'] += $discounts;
            $days[$day_key]['refunds'] += $refunds;

            $email = strtolower((string) $order->get_billing_email());
            if ($email) $customers[$email] = true;

            foreach ($order->get_items('line_item') as $item) {
                $product_id = method_exists($item, 'get_product_id') ? (int) $item->get_product_id() : 0;
                $name = $item->get_name();
                $qty = (int) $item->get_quantity();
                $line_total = method_exists($item, 'get_total') ? (float) $item->get_total() : 0.0;
                $cost = $product_id ? (float) get_post_meta($product_id, '_ttos_cost_price', true) : 0.0;
                if (!isset($items[$name])) {
                    $items[$name] = array('name' => $name, 'qty' => 0, 'gross' => 0.0, 'estimated_cost' => 0.0, 'estimated_margin' => 0.0);
                }
                $items[$name]['qty'] += $qty;
                $items[$name]['gross'] += $line_total;
                if ($cost > 0) $items[$name]['estimated_cost'] += $cost * $qty;
            }
        }

        foreach ($items as &$item) {
            $item['estimated_margin'] = $item['estimated_cost'] > 0 ? $item['gross'] - $item['estimated_cost'] : 0.0;
        }
        unset($item);
        usort($items, function ($a, $b) { return $b['gross'] <=> $a['gross']; });
        uasort($payment, function ($a, $b) { return $b['gross'] <=> $a['gross']; });
        uasort($statuses, function ($a, $b) { return $b['orders'] <=> $a['orders']; });
        ksort($days);

        $summary['aov'] = $summary['orders'] ? $summary['gross'] / $summary['orders'] : 0.0;
        $summary['items_per_order'] = $summary['orders'] ? $summary['items'] / $summary['orders'] : 0.0;
        $summary['unique_customers'] = count($customers);
        $summary['vat_estimate'] = self::vat_estimate($summary['gross']);
        $summary['direct_savings_estimate'] = $summary['gross'] * 0.168;

        $busiest = array('hour' => null, 'orders' => 0, 'gross' => 0.0);
        foreach ($hours as $hour => $row) {
            if ($row['orders'] > $busiest['orders']) $busiest = array('hour' => $hour, 'orders' => $row['orders'], 'gross' => $row['gross']);
        }

        return array(
            'start' => $start_ts,
            'end' => $end_ts,
            'summary' => $summary,
            'payment_methods' => $payment,
            'statuses' => $statuses,
            'fulfilment' => $fulfilment,
            'hours' => $hours,
            'busiest_hour' => $busiest,
            'days' => $days,
            'top_items' => array_slice($items, 0, 12),
            'recent_orders' => self::recent_orders(),
        );
    }

    public static function orders_between(int $start_ts, int $end_ts): array {
        if (!function_exists('wc_get_orders')) return array();
        $orders = wc_get_orders(array(
            'limit' => -1,
            'status' => array_keys(wc_get_order_statuses()),
            'date_created' => '>' . $start_ts,
            'return' => 'objects',
        ));
        return array_values(array_filter($orders, function ($order) use ($start_ts, $end_ts) {
            if (!is_object($order) || !method_exists($order, 'get_date_created')) return false;
            $date = $order->get_date_created();
            if (!$date) return false;
            $ts = $date->getTimestamp();
            return $ts >= $start_ts && $ts <= $end_ts;
        }));
    }

    public static function recent_orders(): array {
        if (!TTOS_WooCommerce::active() || !function_exists('wc_get_orders')) return array();
        return wc_get_orders(array('limit' => 8, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects'));
    }

    public static function export_money_csv(): void {
        if (!current_user_can('ttos_view_reports') || !check_admin_referer('ttos_export_money_csv')) wp_die('Not allowed.');
        $preset = sanitize_key($_GET['preset'] ?? '7days');
        $from = sanitize_text_field(wp_unslash($_GET['from'] ?? ''));
        $to = sanitize_text_field(wp_unslash($_GET['to'] ?? ''));
        $range = self::range($preset, $from, $to);
        $report = self::report($range['start'], $range['end']);
        self::download_csv('takeaway-money-report-' . date('Y-m-d') . '.csv', self::money_csv_rows($report, $range['label']));
    }

    public static function export_daily_close_csv(): void {
        if (!current_user_can('ttos_view_reports') || !check_admin_referer('ttos_export_daily_close_csv')) wp_die('Not allowed.');
        $range = self::range('today');
        $report = self::report($range['start'], $range['end']);
        self::download_csv('takeaway-daily-close-' . date('Y-m-d') . '.csv', self::daily_close_rows($report));
    }

    private static function empty_report(int $start_ts, int $end_ts): array {
        return array(
            'start' => $start_ts,
            'end' => $end_ts,
            'summary' => array(
                'orders' => 0, 'gross' => 0.0, 'net' => 0.0, 'refunds' => 0.0, 'discounts' => 0.0,
                'delivery_fees' => 0.0, 'fees' => 0.0, 'tax' => 0.0, 'cash' => 0.0, 'card' => 0.0,
                'items' => 0, 'aov' => 0.0, 'items_per_order' => 0.0, 'unique_customers' => 0,
                'vat_estimate' => 0.0, 'direct_savings_estimate' => 0.0,
            ),
            'payment_methods' => array(), 'statuses' => array(), 'fulfilment' => array('delivery' => 0, 'collection' => 0, 'unknown' => 0),
            'hours' => array_fill(0, 24, array('orders' => 0, 'gross' => 0.0)), 'busiest_hour' => array('hour' => null, 'orders' => 0, 'gross' => 0.0),
            'days' => array(), 'top_items' => array(), 'recent_orders' => array(),
        );
    }

    private static function vat_estimate(float $gross): float {
        $rate = 20.0;
        if (class_exists('TTOS_Features')) {
            $feature_rate = (float) TTOS_Features::get('accounting', 'vat_rate');
            if ($feature_rate > 0) $rate = $feature_rate;
        }
        return $gross - ($gross / (1 + ($rate / 100)));
    }

    private static function money_csv_rows(array $report, string $label): array {
        $s = $report['summary'];
        $rows = array(
            array('Section', 'Metric', 'Value'),
            array('Range', 'Selected range', $label),
            array('Summary', 'Orders', $s['orders']),
            array('Summary', 'Gross sales', self::decimal($s['gross'])),
            array('Summary', 'Net after refunds', self::decimal($s['net'])),
            array('Summary', 'Refunds', self::decimal($s['refunds'])),
            array('Summary', 'Discounts', self::decimal($s['discounts'])),
            array('Summary', 'Delivery fees', self::decimal($s['delivery_fees'])),
            array('Summary', 'Fees', self::decimal($s['fees'])),
            array('Summary', 'VAT estimate', self::decimal($s['vat_estimate'])),
            array('Summary', 'Card/non-cash', self::decimal($s['card'])),
            array('Summary', 'Cash', self::decimal($s['cash'])),
            array('Summary', 'Average order value', self::decimal($s['aov'])),
            array('Summary', 'Unique customers', $s['unique_customers']),
            array('', '', ''),
            array('Payment methods', 'Method', 'Orders', 'Gross'),
        );
        foreach ($report['payment_methods'] as $method => $row) $rows[] = array('Payment methods', $method, $row['orders'], self::decimal($row['gross']));
        $rows[] = array('', '', '');
        $rows[] = array('Daily takings', 'Day', 'Orders', 'Gross', 'Net', 'Discounts', 'Refunds');
        foreach ($report['days'] as $row) $rows[] = array('Daily takings', $row['label'], $row['orders'], self::decimal($row['gross']), self::decimal($row['net']), self::decimal($row['discounts']), self::decimal($row['refunds']));
        $rows[] = array('', '', '');
        $rows[] = array('Top items', 'Item', 'Qty', 'Gross', 'Estimated cost', 'Estimated margin');
        foreach ($report['top_items'] as $row) $rows[] = array('Top items', $row['name'], $row['qty'], self::decimal($row['gross']), self::decimal($row['estimated_cost']), self::decimal($row['estimated_margin']));
        return $rows;
    }

    private static function daily_close_rows(array $report): array {
        $s = $report['summary'];
        $rows = array(
            array('Metric', 'Value'),
            array('Orders today', $s['orders']),
            array('Gross sales', self::decimal($s['gross'])),
            array('Net after refunds', self::decimal($s['net'])),
            array('Card/non-cash takings', self::decimal($s['card'])),
            array('Cash takings', self::decimal($s['cash'])),
            array('Refunds', self::decimal($s['refunds'])),
            array('Discounts', self::decimal($s['discounts'])),
            array('Delivery fees', self::decimal($s['delivery_fees'])),
            array('VAT estimate', self::decimal($s['vat_estimate'])),
            array('Average order value', self::decimal($s['aov'])),
            array('', ''),
            array('Payment method', 'Orders', 'Gross'),
        );
        foreach ($report['payment_methods'] as $method => $row) $rows[] = array($method, $row['orders'], self::decimal($row['gross']));
        return $rows;
    }

    private static function download_csv(string $filename, array $rows): void {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . sanitize_file_name($filename));
        $out = fopen('php://output', 'w');
        foreach ($rows as $row) fputcsv($out, $row);
        fclose($out);
        exit;
    }

    private static function decimal($amount): string {
        return number_format((float) $amount, 2, '.', '');
    }
}

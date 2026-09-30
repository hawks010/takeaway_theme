from pathlib import Path
root = Path('.')
def replace(path, old, new, count=1):
    p = root / path
    s = p.read_text()
    assert s.count(old) == count, (path, s.count(old))
    p.write_text(s.replace(old, new))
# These consumers display customer orders, not standalone refund objects.
for p in (root / 'takeaway-os/includes').glob('*.php'):
    s = p.read_text(); out = ''; pos = 0; needle = 'wc_get_orders(array('
    while True:
        i = s.find(needle, pos)
        if i < 0:
            out += s[pos:]; break
        start = i + len(needle); j = start; depth = 1; quote = None
        while j < len(s):
            ch = s[j]
            if quote:
                if ch == '\\': j += 2; continue
                if ch == quote: quote = None
            elif ch in "'\"": quote = ch
            elif ch == '(': depth += 1
            elif ch == ')':
                depth -= 1
                if depth == 0: break
            j += 1
        args = s[start:j]
        out += s[pos:start] + ("'type' => 'shop_order', " if "'type'" not in args else '')
        pos = start
    p.write_text(out.replace("wc_get_orders(array('type' => 'shop_order', \n", "wc_get_orders(array('type' => 'shop_order',\n"))
replace('takeaway-os/includes/class-admin.php', "if (!$order || !method_exists($order, 'get_id')) {", "if (!$order instanceof WC_Order) {")
replace('takeaway-os/includes/class-admin.php', 'private static function customer_snapshot_enhanced(int $limit = 300): array {', 'public static function customer_snapshot_enhanced(int $limit = 300): array {')
replace('takeaway-os/includes/class-admin.php', "self::dashboard_inline_stat('Card',", "self::dashboard_inline_stat('Non-cash',")
p = root / 'takeaway-os/includes/class-features.php'; s = p.read_text()
a = s.index('    public static function analytics_snapshot(): array {'); b = s.index('    private static function delivery_rules(): array {', a)
s = s[:a] + '''    public static function analytics_snapshot(): array {
        $range = TTOS_Analytics::range('30days');
        $report = TTOS_Analytics::report($range['start'], $range['end']);
        $items = array();
        foreach ($report['top_items'] as $item) $items[$item['name']] = $item['qty'];
        $statuses = array();
        foreach ($report['statuses'] as $name => $row) $statuses[$name] = $row['orders'];
        $hour = $report['busiest_hour']['hour'];
        return array('orders' => $report['summary']['orders'], 'revenue' => $report['summary']['gross'],
            'aov' => $report['summary']['aov'], 'busy_hour' => $hour === null ? '—' : sprintf('%02d:00', $hour),
            'top_items' => $items, 'statuses' => $statuses);
    }

    private static function customer_snapshot(): array {
        // Both owner screens and CSV exports use the same complete, paid-history definition.
        $customers = TTOS_Admin::customer_snapshot_enhanced();
        foreach ($customers as &$customer) $customer['dormant'] = $customer['status'] === 'dormant';
        unset($customer);
        return $customers;
    }

    private static function daily_close_snapshot(): array {
        $range = TTOS_Analytics::range('today');
        $summary = TTOS_Analytics::report($range['start'], $range['end'])['summary'];
        return array('gross' => $summary['gross'], 'card' => $summary['card'],
            'cash' => $summary['cash'], 'vat' => $summary['tax']);
    }

''' + s[b:]
s = s.replace("self::field('VAT estimate rate (%)', 'feature_settings[accounting][vat_rate]', $s['vat_rate'], 'number');", "echo '<p>Tax is read from WooCommerce orders. This screen never calculates or changes tax rates.</p>';")
s = s.replace("self::metric('VAT est.',", "self::metric('Recorded tax',").replace("self::metric('Card',", "self::metric('Non-cash',")
p.write_text(s)
replace('takeaway-os/includes/class-analytics.php', 'if ($start && $end && $start <= $end) {', "if ($start && $end && $start->format('Y-m-d') === $from && $end->format('Y-m-d') === $to && $start <= $end) {")
replace('takeaway-os/includes/class-analytics.php', "array('cod', 'cash', 'cheque')", "array('cod', 'cash')")
replace('tests/native/run.php', "must(count($tests) === 23, 'The native suite must execute every registered test.');", "require __DIR__ . '/recovery-regressions.php';\nmust(count($tests) === 28, 'The native suite must execute every registered test.');")
(root / 'tests/native/recovery-regressions.php').write_text('''<?php
// Loaded by the disposable native suite after its partial-refund fixture.
native_test('Recent-order query and dashboard never render refund objects as orders', function () {
    foreach (TTOS_Analytics::recent_orders() as $order) must($order instanceof WC_Order);
    ob_start();
    try { private_call('TTOS_Admin', 'dashboard_recent_orders', TTOS_Analytics::recent_orders()); }
    finally { ob_end_clean(); }
});
native_test('Legacy customer reports reuse the complete corrected paid history', function () {
    $customers = private_call('TTOS_Features', 'customer_snapshot');
    must(count($customers) >= 205);
    must(!isset($customers['unpaid@example.test']));
    must((float) $customers['refund@example.test']['total'] === 6.0);
});
native_test('Legacy analytics and daily close reuse native recorded totals', function () {
    $range = TTOS_Analytics::range('30days');
    $report = TTOS_Analytics::report($range['start'], $range['end']);
    must(TTOS_Features::analytics_snapshot()['orders'] === $report['summary']['orders']);
    $today = TTOS_Analytics::range('today');
    $summary = TTOS_Analytics::report($today['start'], $today['end'])['summary'];
    must(private_call('TTOS_Features', 'daily_close_snapshot')['vat'] === $summary['tax']);
});
native_test('Accounting stream spans native pages and needs no uploads file', function () {
    $stream = fopen('php://temp', 'w+');
    try {
        $count = TTOS_Accounting::write_csv($stream, new DateTime('2026-01-01', wp_timezone()), new DateTime('2026-01-02', wp_timezone()));
        must($count >= 208, 'Expected every order including the unpaid record, but not refund child objects.');
        rewind($stream); $csv = stream_get_contents($stream);
        must(strpos($csv, 'history-205@example.test') !== false);
        must(strpos($csv, 'currency,total,refunded_total') !== false);
        must(!is_dir(wp_upload_dir()['basedir'] . '/ttos-exports'), 'CSV created a public export directory.');
    } finally { fclose($stream); }
});
native_test('Spreadsheet formula-like values are data, not executable cells', function () {
    must(TTOS_Accounting::csv_cell('=HYPERLINK("https://example.test")')[0] === "'");
    must(TTOS_Accounting::csv_cell('  @SUM(1)')[0] === "'");
    must(TTOS_Accounting::csv_cell('-4.50') === '-4.50');
});
''')

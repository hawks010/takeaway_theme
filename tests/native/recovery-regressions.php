<?php
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

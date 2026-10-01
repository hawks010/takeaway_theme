<?php
if (!defined('TTOS_FIXTURE_ONLY') || TTOS_FIXTURE_ONLY !== true || wp_get_environment_type() !== 'local') {
    throw new RuntimeException('Use the disposable local WordPress fixture.');
}
$result = json_decode(file_get_contents('/tmp/ttos-http-result.json'), true);
parse_str((string) wp_parse_url($result['checkout_redirect'], PHP_URL_QUERY), $query);
$id = wc_get_order_id_by_order_key($query['key'] ?? '');
$order = $id ? wc_get_order($id) : false;
if (!$order || $order->get_status() !== 'processing' || !$order->get_date_paid()) throw new RuntimeException('Native payment completion was not preserved.');
if ($order->get_transaction_id() !== 'fixture-' . $id) throw new RuntimeException('Native transaction reference was changed.');
if ($order->get_meta('_ttos_fulfilment_method') !== 'collection') throw new RuntimeException('Fulfilment selection did not survive native checkout.');
if ((float) $order->get_total() !== 11.0) throw new RuntimeException('Unexpected native checkout total.');
echo "PASS native checkout persisted payment status, transaction reference, collection metadata and total\n";

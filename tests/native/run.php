<?php
// Run via WP-CLI on the disposable CI installation, never on a client site.
if (!defined('TTOS_FIXTURE_ONLY') || TTOS_FIXTURE_ONLY !== true || wp_get_environment_type() !== 'local') {
    throw new RuntimeException('Use the disposable local WordPress fixture.');
}
wp_set_current_user(1);
update_option('woocommerce_currency', 'GBP');
update_option('timezone_string', 'Europe/London');
TTOS_Settings::update_section('modules', array_fill_keys(array_keys(TTOS_Settings::defaults()['modules']), false));
$tests = array();
function native_test($name, $fn) { $GLOBALS['tests'][$name] = $fn; }
function must($condition, $message = 'Assertion failed') { if (!$condition) throw new RuntimeException($message); }
function private_call($class, $method, ...$args) { $r = new ReflectionMethod($class, $method); $r->setAccessible(true); return $r->invoke(null, ...$args); }
function api_request($path, $params = array(), $method = 'POST') { $r = new WP_REST_Request($method, $path); $r->set_header('content-type', 'application/json'); $r->set_body(wp_json_encode($params)); return rest_do_request($r); }
function fixture_order($email, $status = 'processing', $paid = true) {
    $order = new WC_Order();
    $order->set_currency('GBP'); $order->set_billing_email($email); $order->set_billing_first_name('Fixture');
    $order->set_status($status); $order->set_total('10.00'); $order->set_date_created('2026-01-01 12:00:00');
    if ($paid) $order->set_date_paid('2026-01-01 12:01:00');
    $order->save(); return $order;
}
$owner = wp_create_user('fixtureowner', getenv('TEST_OWNER_PASSWORD'), 'owner@example.test');
if (is_wp_error($owner)) throw new RuntimeException($owner->get_error_message());
(new WP_User($owner))->set_role('takeaway_owner');
$kitchen = wp_create_user('fixturekitchen', getenv('TEST_OWNER_PASSWORD'), 'kitchen@example.test');
(new WP_User($kitchen))->set_role('takeaway_kitchen');
$product_id = TTOS_WooCommerce::create_or_update_product(array('name'=>'Fixture meal', 'price'=>'10.00', 'tax_status'=>'none'));
if (!$product_id) throw new RuntimeException('Native product creation failed.');
$orders = array();
for ($i = 1; $i <= 205; $i++) $orders[] = fixture_order('history-' . $i . '@example.test');
$unpaid = fixture_order('unpaid@example.test', 'on-hold', false);
$refund_order = fixture_order('refund@example.test');
$legacy_order = fixture_order('legacy@example.test', 'ttos-accepted');
$board_order = $orders[0];

native_test('HPOS is genuinely enabled', function () { must(\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()); });
native_test('Menu save updates native product and lookup prices', function () use ($product_id) {
    global $wpdb;
    $saved = TTOS_WooCommerce::create_or_update_product(array('product_id'=>$product_id, 'name'=>'Fixture meal', 'price'=>'12.50', 'sale_price'=>'11.00', 'featured'=>true));
    must($saved === $product_id);
    $p = wc_get_product($product_id); must((float)$p->get_price() === 11.0); must($p->get_featured());
    $row = $wpdb->get_row($wpdb->prepare("SELECT min_price, stock_status FROM {$wpdb->prefix}wc_product_meta_lookup WHERE product_id = %d", $product_id));
    must($row && (float)$row->min_price === 11.0, 'Product price lookup did not follow native save.');
});
native_test('Stock controls preserve zero tracked quantity', function () use ($product_id) {
    $p = wc_get_product($product_id); $p->set_manage_stock(true); $p->set_stock_quantity(0); $p->set_stock_status('outofstock'); $p->save();
    TTOS_WooCommerce::set_product_state($product_id, 'available'); $p = wc_get_product($product_id);
    must($p->get_stock_quantity() === 0 && $p->get_stock_status() === 'outofstock');
    $p->set_manage_stock(false); $p->set_stock_quantity(null); $p->set_stock_status('instock'); $p->save();
});
native_test('Existing native variable product is preserved', function () {
    $p = new WC_Product_Variable(); $p->set_name('Native variable'); $p->save();
    must(TTOS_WooCommerce::create_or_update_product(array('product_id'=>$p->get_id(),'price'=>'8.00')) === 0);
    must(wc_get_product($p->get_id())->is_type('variable'));
});
native_test('HPOS pagination includes customers beyond first and second pages', function () {
    $c = private_call('TTOS_Admin', 'customer_snapshot_enhanced', 300);
    must(isset($c['history-1@example.test'], $c['history-101@example.test'], $c['history-205@example.test']));
    must(!isset($c['unpaid@example.test']));
});
native_test('Native partial refund reduces recorded customer spend', function () use ($refund_order) {
    $r = wc_create_refund(array('order_id'=>$refund_order->get_id(), 'amount'=>'4.00', 'reason'=>'Offline fixture', 'refund_payment'=>false, 'restock_items'=>false));
    must(!is_wp_error($r), is_wp_error($r) ? $r->get_error_message() : '');
    $c = private_call('TTOS_Admin', 'customer_snapshot_enhanced'); must((float)$c['refund@example.test']['total'] === 6.0);
});
native_test('Legacy kitchen records retain native paid-date evidence', function () { $c = private_call('TTOS_Admin', 'customer_snapshot_enhanced'); must((float)$c['legacy@example.test']['total'] === 10.0); });
native_test('Kitchen stages leave native paid status and transaction ID untouched', function () use ($board_order) {
    $board_order->set_transaction_id('existing-fixture-reference'); $board_order->save();
    foreach (array('ttos-accepted','ttos-prepping','ttos-ready','ttos-out') as $stage) {
        $r = private_call('TTOS_Admin', 'apply_order_action', $board_order->get_id(), $stage, 20);
        must($r['ok']); $o = wc_get_order($board_order->get_id()); must($o->get_status() === 'processing');
        must($o->get_transaction_id() === 'existing-fixture-reference'); must(TTOS_WooCommerce::kitchen_status($o) === $stage);
    }
});
native_test('Unpaid kitchen acceptance cannot mark a native order paid', function () use ($unpaid) {
    private_call('TTOS_Admin', 'apply_order_action', $unpaid->get_id(), 'ttos-accepted', 20);
    $o = wc_get_order($unpaid->get_id()); must($o->get_status() === 'on-hold'); must(!$o->get_date_paid());
});
native_test('Order board counts more than 100 active orders', function () { $counts = private_call('TTOS_Admin', 'order_board_counts'); must($counts['active'] >= 205); });
native_test('Native REST denies anonymous pause changes', function () { wp_set_current_user(0); $r = api_request('/ttos/v1/dashboard/pause', array('paused'=>true)); must(in_array($r->get_status(), array(401,403), true)); wp_set_current_user(1); });
native_test('Kitchen role cannot change business settings', function () use ($kitchen) { wp_set_current_user($kitchen); $r = api_request('/ttos/v1/dashboard/settings', array('trading.min_order'=>1)); must($r->get_status()===403); wp_set_current_user(1); });
native_test('Owner can pause and unpause without a separate approval', function () use ($owner) {
    wp_set_current_user($owner); must(api_request('/ttos/v1/dashboard/pause',array('paused'=>true))->get_status()===200);
    must(TTOS_Production::is_paused()); must(!TTOS_Production::block_add_to_cart_if_paused(true));
    must(api_request('/ttos/v1/dashboard/pause',array('paused'=>false))->get_status()===200); must(!TTOS_Production::is_paused()); wp_set_current_user(1);
});
native_test('Owner override updates the actual schedule', function () use ($owner) { wp_set_current_user($owner); must(api_request('/ttos/v1/dashboard/toggle-open',array('override'=>'force_closed'))->get_status()===200); must(!TTOS_Operations::ordering_state('delivery')['open']); wp_set_current_user(1); });
native_test('Invalid REST values do not partially save', function () { $before=TTOS_Settings::get(); $r=api_request('/ttos/v1/dashboard/settings',array('trading.min_order'=>99,'trading.delivery_enabled'=>'invalid')); must($r->get_status()===400); must(TTOS_Settings::get()===$before); });
native_test('Onboarding does not reset merchant commerce settings', function () {
    $settings=array('woocommerce_currency'=>'EUR','woocommerce_default_country'=>'IE','woocommerce_calc_taxes'=>'yes','woocommerce_prices_include_tax'=>'yes','woocommerce_cod_settings'=>array('enabled'=>'no'),'woocommerce_onboarding_profile'=>array('merchant'=>'preserve'));
    foreach($settings as $key=>$value)update_option($key,$value);
    TTOS_Onboarding::apply_profile(); foreach($settings as $key=>$value)must(get_option($key)===$value,$key.' changed');
    update_option('woocommerce_currency','GBP');update_option('woocommerce_default_country','GB');update_option('woocommerce_calc_taxes','no');
});
native_test('Administrator is not trapped by old module key', function () { update_option('ttos_module_lock_hash','old-key-hash'); must(private_call('TTOS_Admin','modules_unlocked')); });
native_test('Package preset changes modules only', function () { $before=get_option('woocommerce_cod_settings'); must(TTOS_Packages::apply('ordering')); must(get_option('woocommerce_cod_settings')===$before); must(!TTOS_Production::is_paused()); });
native_test('REST unsubscribe controls the actual sender and clears queued work', function () use ($owner) {
    TTOS_Production::record_marketing_permission('owner@example.test', true); $uid=TTOS_Retention::get_uid($owner);
    wp_schedule_single_event(time()+3600,'ttos_send_retention_email',array('owner@example.test'));
    $req=new WP_REST_Request('GET','/ttos/v1/retention/unsubscribe');$req->set_param('uid',$uid);$r=rest_do_request($req);
    must($r->get_status()===200);must(!TTOS_Production::customer_marketing_opt_in('owner@example.test'));
    must(!wp_next_scheduled('ttos_send_retention_email',array('owner@example.test')));
});
native_test('Unknown unsubscribe ID has no effect', function () { $req=new WP_REST_Request('GET','/ttos/v1/retention/unsubscribe');$req->set_param('uid','missing');must(rest_do_request($req)->get_status()===404); });
native_test('Accounting rejects invalid calendar ranges', function () { $r=api_request('/ttos/v1/accounting/export',array('from'=>'2026-02-30','to'=>'2026-03-02'));must($r->get_status()===400); });
native_test('Accounting export returns an authenticated handler, not a public data file', function () use ($owner) {
    wp_set_current_user($owner);$r=api_request('/ttos/v1/accounting/export',array('from'=>'2026-01-01','to'=>'2026-01-02','provider'=>'generic'));$data=$r->get_data();must($r->get_status()===200,wp_json_encode($data));
    must(strpos($data['download_url'],'admin-post.php')!==false);must(!isset($data['file_path']));must(strpos($data['download_url'],'_wpnonce=')!==false);
    $GLOBALS['fixture_export']=$data; wp_set_current_user(1);
});
$passed=0;$failed=0;
foreach($tests as $name=>$test){try{$test();echo "PASS $name\n";$passed++;}catch(Throwable $e){echo "FAIL $name: ".$e->getMessage()."\n";$failed++;}finally{wp_set_current_user(1);}}
// Prepare a native classic checkout fixture for a separate HTTP smoke test.
TTOS_Settings::update_section('modules',array_fill_keys(array_keys(TTOS_Settings::defaults()['modules']),false));
$trading=TTOS_Settings::get('trading');$trading['min_order']='0';$trading['collection_enabled']='1';$trading['delivery_enabled']='1';$trading['delivery_postcodes']='';TTOS_Settings::update_section('trading',$trading);
$hours=ttos_get_opening_hours();$hours['override']='force_open';$hours['temporary_closure']='0';TTOS_Site_Content::update_section('opening_times',$hours);TTOS_Production::set_paused(false);
$zone=new WC_Shipping_Zone(0);$zone->add_shipping_method('local_pickup');
$p=wc_get_product($product_id);$p->set_manage_stock(false);$p->set_stock_status('instock');$p->save();
update_option('woocommerce_enable_guest_checkout','yes');update_option('woocommerce_allowed_countries','all');update_option('woocommerce_currency','GBP');update_option('woocommerce_calc_taxes','no');update_option('woocommerce_coming_soon','no');
file_put_contents('/tmp/ttos-fixtures.json',wp_json_encode(array('product_id'=>$product_id,'owner_id'=>$owner,'export'=>$GLOBALS['fixture_export']??array(),'checkout_url'=>wc_get_checkout_url(),'cart_url'=>wc_get_cart_url())));
echo "NATIVE RESULT $passed passed, $failed failed, ".count($tests)." tests\n";
exit($failed?1:0);

<?php
/** Disposable shipping rehearsal. Never packaged or run on an existing shop. */
if (!defined('TTOS_FIXTURE_ONLY') || TTOS_FIXTURE_ONLY !== true || wp_get_environment_type() !== 'local') {
    throw new RuntimeException('Use the disposable local fixture.');
}
if (!defined('DOING_AJAX')) define('DOING_AJAX', true);
global $checks, $zones, $product, $coupon, $tax_id;
wc_load_cart();
wp_set_current_user(1);
$checks = array(); $zones = array(); $product = null; $coupon = null; $tax_id = null;
function shipping_test($name, $test) { $GLOBALS['checks'][$name] = $test; }
function shipping_must($ok, $message = 'Assertion failed') { if (!$ok) throw new RuntimeException($message); }
function shipping_equal($actual, $expected) { shipping_must(abs((float) $actual - $expected) < 0.001, 'Expected '.$expected.', got '.var_export($actual,true)); }
function shipping_zone($name, $order, $postcodes, $cost, $free = 0, $pickup = false) {
    $z = new WC_Shipping_Zone(); $z->set_zone_name($name); $z->set_zone_order($order); $z->add_location('GB', 'country');
    foreach ($postcodes as $pc) $z->add_location($pc, 'postcode');
    $z->save(); $GLOBALS['zones'][] = $z;
    $ids = array('flat_rate' => $z->add_shipping_method('flat_rate'));
    update_option('woocommerce_flat_rate_'.$ids['flat_rate'].'_settings', array('enabled'=>'yes','title'=>'Fixture delivery','cost'=>(string)$cost,'tax_status'=>'taxable'));
    if ($free) {
        $ids['free_shipping'] = $z->add_shipping_method('free_shipping');
        update_option('woocommerce_free_shipping_'.$ids['free_shipping'].'_settings', array('enabled'=>'yes','title'=>'Fixture free delivery','requires'=>'min_amount','min_amount'=>(string)$free,'ignore_discounts'=>'no'));
    }
    if ($pickup) {
        $ids['local_pickup'] = $z->add_shipping_method('local_pickup');
        update_option('woocommerce_local_pickup_'.$ids['local_pickup'].'_settings', array('enabled'=>'yes','title'=>'Fixture collection','cost'=>'0','tax_status'=>'taxable'));
    }
    WC_Cache_Helper::get_transient_version('shipping', true);
    return array('zone'=>$z, 'ids'=>$ids);
}
function shipping_modules($advanced) {
    $modules = TTOS_Settings::get('modules'); $modules['advanced_zones'] = $advanced;
    TTOS_Settings::update_section('modules', $modules);
}
function shipping_basket($price, $postcode, $method, $rate) {
    $_POST = array(); wc_clear_notices();
    WC()->cart->empty_cart();
    $p = $GLOBALS['product']; $p->set_regular_price((string)$price); $p->set_price((string)$price); $p->save();
    WC()->customer->set_billing_country('GB'); WC()->customer->set_shipping_country('GB');
    WC()->customer->set_billing_postcode($postcode); WC()->customer->set_shipping_postcode($postcode);
    WC()->customer->set_shipping_city('Fixture'); WC()->customer->set_shipping_address_1('1 Example Street');
    WC()->customer->set_calculated_shipping(true);
    TTOS_Operations::set_fulfilment_method($method);
    WC()->cart->add_to_cart($p->get_id());
    WC()->session->set('chosen_shipping_methods', array($rate));
    WC()->session->set('shipping_for_package_0', null);
    WC()->cart->calculate_totals();
}
function shipping_rate_id($fixture, $method) { return $method.':'.$fixture['ids'][$method]; }
function shipping_rates() { $p=WC()->shipping()->get_packages(); return isset($p[0]['rates']) ? $p[0]['rates'] : array(); }
function shipping_post($billing, $shipping, $different = false) {
    $_POST = array('ttos_fulfilment_method'=>'delivery','ttos_requested_time'=>'asap',
        'billing_first_name'=>'Fixture','billing_last_name'=>'Customer','billing_country'=>'GB','billing_postcode'=>$billing,
        'billing_address_1'=>'1 Example Street','billing_city'=>'Fixture','billing_email'=>'shipping@example.test','billing_phone'=>'02079460000',
        'shipping_first_name'=>'Fixture','shipping_last_name'=>'Customer','shipping_country'=>'GB','shipping_postcode'=>$shipping,
        'shipping_address_1'=>'2 Example Street','shipping_city'=>'Fixture');
    if ($different) $_POST['ship_to_different_address']='1';
    // Use WooCommerce's own address normalization and the actual registered hooks.
    wc_clear_notices();
    do_action('woocommerce_checkout_process');
    $data = WC()->checkout()->get_posted_data();
    $errors = new WP_Error();
    do_action('woocommerce_after_checkout_validation', $data, $errors);
    $messages = $errors->get_error_messages();
    foreach (wc_get_notices('error') as $row) $messages[] = wp_strip_all_tags($row['notice']);
    return array('data'=>$data, 'messages'=>implode(' ', $messages));
}
$option_names = array('woocommerce_currency','woocommerce_default_country','woocommerce_calc_taxes','woocommerce_prices_include_tax','woocommerce_tax_based_on','woocommerce_ship_to_destination','woocommerce_enable_coupons','ttos_settings','ttos_operations_settings','ttos_site_content','ttos_feature_settings');
$before=array(); foreach($option_names as $key) $before[$key]=get_option($key,null);
$order_count_before=wc_get_orders(array('type'=>'shop_order','limit'=>1,'paginate'=>true))->total;
try {
    foreach(array('woocommerce_currency'=>'GBP','woocommerce_default_country'=>'GB','woocommerce_calc_taxes'=>'no','woocommerce_prices_include_tax'=>'no','woocommerce_tax_based_on'=>'shipping','woocommerce_ship_to_destination'=>'shipping','woocommerce_enable_coupons'=>'yes') as $key=>$value) update_option($key,$value);
    $trading=TTOS_Settings::get('trading');
    TTOS_Settings::update_section('trading',array_merge($trading,array('min_order'=>'0','delivery_enabled'=>'1','collection_enabled'=>'1','delivery_postcodes'=>'MK18')));
    $hours=TTOS_Site_Content::get('opening_times'); $hours['override']='force_open'; $hours['temporary_closure']='0'; TTOS_Site_Content::update_section('opening_times',$hours);
    TTOS_Operations::update_section('checkout',array_merge(TTOS_Operations::get('checkout'),array('time_mode'=>'asap')));
    TTOS_Features::update_section('advanced_zones',array('rules'=>"Local|MK18|1.50|12.00|30.00\nNearby|MK17,OX27|3.00|18.00|45.00"));
    shipping_modules(false);
    $product=new WC_Product_Simple(); $product->set_name('Disposable shipping rehearsal'); $product->set_regular_price('20'); $product->set_tax_status('taxable'); $product->save();
    $broad=shipping_zone('Fixture broad GB',0,array(),0);
    $local=shipping_zone('Fixture MK18',1,array('MK18*'),1.50,30,true);
    $nearby=shipping_zone('Fixture nearby',2,array('MK17*','OX27*'),3,45,true);
    shipping_basket(20,'MK18 1AA','delivery',shipping_rate_id($broad,'flat_rate'));
    shipping_test('Broad GB zone first shadows more-specific postcode zones',function() use($broad){
        foreach(array('MK18 1AA','MK17 8AA','SW1A 1AA') as $pc) {
            $z=WC_Shipping_Zones::get_zone_matching_package(array('destination'=>array('country'=>'GB','state'=>'','postcode'=>$pc)));
            shipping_must($z->get_id()===$broad['zone']->get_id());
        }
    });
    shipping_test('Native zone reordering restores postcode matching without custom rate code',function() use($broad,$local,$nearby){
        $broad['zone']->set_zone_order(99); $broad['zone']->save(); WC_Cache_Helper::get_transient_version('shipping',true);
        foreach(array('MK18 1AA'=>$local,'MK17 8AA'=>$nearby,'OX27 1AA'=>$nearby,'SW1A 1AA'=>$broad) as $pc=>$expected) {
            $z=WC_Shipping_Zones::get_zone_matching_package(array('destination'=>array('country'=>'GB','state'=>'','postcode'=>$pc)));
            shipping_must($z->get_id()===$expected['zone']->get_id(),$pc.' matched wrong zone');
        }
    });
    shipping_test('Native-only local delivery is 1.50 with no Takeaway surcharge',function() use($local){
        shipping_modules(false); shipping_basket(20,'MK18 1AA','delivery',shipping_rate_id($local,'flat_rate'));
        shipping_equal(WC()->cart->get_shipping_total(),1.5); shipping_equal(WC()->cart->get_fee_total(),0); shipping_equal(WC()->cart->get_total('edit'),21.5);
    });
    shipping_test('Native collection plus collection preference has no shipping or zone fee',function() use($local){
        shipping_modules(true); shipping_basket(20,'MK18 1AA','collection',shipping_rate_id($local,'local_pickup'));
        shipping_equal(WC()->cart->get_shipping_total(),0); shipping_equal(WC()->cart->get_fee_total(),0); shipping_equal(WC()->cart->get_total('edit'),20);
    });
    shipping_test('Legacy zone fee stacks on native delivery: explicit configuration evidence',function() use($local){
        shipping_basket(20,'MK18 1AA','delivery',shipping_rate_id($local,'flat_rate'));
        shipping_equal(WC()->cart->get_shipping_total(),1.5); shipping_equal(WC()->cart->get_fee_total(),1.5); shipping_equal(WC()->cart->get_total('edit'),23);
        echo "OBSERVED native delivery 1.50 + legacy zone fee 1.50 = 3.00 delivery-related charges\n";
    });
    shipping_test('A collection preference alone does not override native charged shipping',function() use($local){
        shipping_basket(20,'MK18 1AA','collection',shipping_rate_id($local,'flat_rate'));
        shipping_equal(WC()->cart->get_shipping_total(),1.5); shipping_equal(WC()->cart->get_fee_total(),0);
    });
    shipping_test('Repeated native totals calculation does not duplicate the legacy fee',function() use($local){
        shipping_basket(20,'MK18 1AA','delivery',shipping_rate_id($local,'flat_rate'));
        WC()->cart->calculate_totals(); WC()->cart->calculate_totals(); shipping_equal(WC()->cart->get_fee_total(),1.5);
    });
    shipping_test('Nearby fee and native shipping remain separately visible',function() use($nearby){
        shipping_basket(20,'MK17 8AA','delivery',shipping_rate_id($nearby,'flat_rate'));
        shipping_equal(WC()->cart->get_shipping_total(),3); shipping_equal(WC()->cart->get_fee_total(),3);
    });
    shipping_test('Legacy zone fee waives at its existing pre-discount threshold',function() use($local){
        shipping_basket(30,'MK18 1AA','delivery',shipping_rate_id($local,'flat_rate'));
        shipping_equal(WC()->cart->get_fee_total(),0); shipping_must(isset(shipping_rates()[shipping_rate_id($local,'free_shipping')]));
    });
    shipping_test('Native free-shipping discount policy is retained, not overridden by Takeaway',function() use($local){
        $c=new WC_Coupon(); $c->set_code('fixture-shipping-ten'); $c->set_discount_type('fixed_cart'); $c->set_amount(10); $c->save(); $GLOBALS['coupon']=$c;
        WC()->cart->apply_coupon($c->get_code()); WC()->session->set('shipping_for_package_0',null); WC()->cart->calculate_totals();
        shipping_equal(WC()->cart->get_discount_total(),10); shipping_equal(WC()->cart->get_fee_total(),0);
        shipping_must(!isset(shipping_rates()[shipping_rate_id($local,'free_shipping')]),'Native free shipping should be unavailable after the coupon');
    });
    shipping_test('Native tax calculation remains authoritative with advanced fees off',function() use($local){
        shipping_modules(false); update_option('woocommerce_calc_taxes','yes');
        $GLOBALS['tax_id']=WC_Tax::_insert_tax_rate(array('tax_rate_country'=>'GB','tax_rate_state'=>'','tax_rate'=>'20.0000','tax_rate_name'=>'Fixture VAT','tax_rate_priority'=>1,'tax_rate_compound'=>0,'tax_rate_shipping'=>1,'tax_rate_order'=>0,'tax_rate_class'=>''));
        WC_Cache_Helper::invalidate_cache_group('taxes');
        shipping_basket(20,'MK18 1AA','delivery',shipping_rate_id($local,'flat_rate'));
        shipping_equal(WC()->cart->get_cart_contents_tax(),4); shipping_equal(WC()->cart->get_shipping_tax(),0.3); shipping_equal(WC()->cart->get_total('edit'),25.8);
        WC()->cart->apply_coupon($GLOBALS['coupon']->get_code()); WC()->cart->calculate_totals();
        shipping_equal(WC()->cart->get_total_tax(),2.3); shipping_equal(WC()->cart->get_total('edit'),13.8);
        update_option('woocommerce_calc_taxes','no');
    });
    shipping_test('Blank unused shipping postcode does not reject valid billing delivery',function() use($local){
        shipping_modules(false); shipping_basket(15,'MK18 1AA','delivery',shipping_rate_id($local,'flat_rate'));
        $r=shipping_post('MK18 1AA',''); shipping_must($r['data']['shipping_postcode']==='MK18 1AA');
        shipping_must(strpos($r['messages'],'outside the current delivery area')===false,$r['messages']);
    });
    shipping_test('Stale unchecked shipping address cannot reject an allowed destination',function(){
        $r=shipping_post('MK18 1AA','SW1A 1AA'); shipping_must($r['data']['shipping_postcode']==='MK18 1AA');
        shipping_must(strpos($r['messages'],'outside the current delivery area')===false,$r['messages']);
    });
    shipping_test('Stale unchecked shipping address cannot bypass delivery area validation',function(){
        $r=shipping_post('SW1A 1AA','MK18 1AA'); shipping_must($r['data']['shipping_postcode']==='SW1A 1AA');
        shipping_must(strpos($r['messages'],'outside the current delivery area')!==false,'Out-of-area billing destination was accepted');
    });
    shipping_test('Genuinely separate shipping address is used for delivery validation',function(){
        $allowed=shipping_post('SW1A 1AA','MK18 1AA',true); shipping_must(strpos($allowed['messages'],'outside the current delivery area')===false,$allowed['messages']);
        $blocked=shipping_post('MK18 1AA','SW1A 1AA',true); shipping_must(strpos($blocked['messages'],'outside the current delivery area')!==false);
    });
    shipping_test('WooCommerce force-billing destination setting remains authoritative',function(){
        update_option('woocommerce_ship_to_destination','billing_only');
        try { $r=shipping_post('MK18 1AA','SW1A 1AA',true); shipping_must($r['data']['shipping_postcode']==='MK18 1AA'); shipping_must(strpos($r['messages'],'outside the current delivery area')===false,$r['messages']); }
        finally {update_option('woocommerce_ship_to_destination','shipping');}
    });
    shipping_test('Advanced minimum uses actual billing destination, not stale hidden zone',function(){
        shipping_modules(true); $r=shipping_post('MK18 1AA','MK17 8AA');
        shipping_must(strpos($r['messages'],'Minimum delivery order for Nearby')===false,$r['messages']);
    });
    shipping_test('Advanced minimum cannot be bypassed using blank unused shipping postcode',function(){
        $r=shipping_post('MK17 8AA',''); shipping_must(strpos($r['messages'],'Minimum delivery order for Nearby')!==false,'Nearby minimum was bypassed');
    });
    shipping_test('Advanced minimum uses an explicitly selected separate address',function(){
        $r=shipping_post('MK18 1AA','MK17 8AA',true); shipping_must(strpos($r['messages'],'Minimum delivery order for Nearby')!==false);
    });
    shipping_test('Order shipping label does not hide the actual native method',function(){
        $order=new WC_Order(); $order->update_meta_data('_ttos_fulfilment_method','collection');
        shipping_must(TTOS_Operations::shipping_method_label('Fixture native delivery',$order)==='Fixture native delivery');
        shipping_must($order->get_meta('_ttos_fulfilment_method')==='collection');
    });
    shipping_must(count($checks)===20,'Every shipping test must be registered');
    $passed=0; $failed=0;
    foreach($checks as $name=>$check) {
        try{$check();echo "PASS $name\n";$passed++;}catch(Throwable $e){echo "FAIL $name: ".$e->getMessage()."\n";$failed++;}
        $_POST=array();wc_clear_notices();
    }
    shipping_must(wc_get_orders(array('type'=>'shop_order','limit'=>1,'paginate'=>true))->total===$order_count_before,'Shipping rehearsal must not create orders');
    echo "SHIPPING RESULT $passed passed, $failed failed, ".count($checks)." tests; zero orders created\n";
} finally {
    $_POST=array();WC()->cart->empty_cart();wc_clear_notices();
    foreach($zones as $zone) $zone->delete();
    if($product)$product->delete(true); if($coupon)$coupon->delete(true); if($tax_id)WC_Tax::_delete_tax_rate($tax_id);
    foreach($before as $key=>$value){if($value===null)delete_option($key);else update_option($key,$value);}
    WC_Cache_Helper::get_transient_version('shipping',true); WC_Cache_Helper::invalidate_cache_group('taxes');
}
exit($failed ? 1 : 0);

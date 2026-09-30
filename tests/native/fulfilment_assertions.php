<?php
// Disposable fixture only; this file is not included in the plugin package.
if (!defined('TTOS_FIXTURE_ONLY') || TTOS_FIXTURE_ONLY !== true || wp_get_environment_type() !== 'local') {
    throw new RuntimeException('Use the disposable local fixture.');
}
wc_load_cart();
$before = TTOS_Settings::get('trading');
$session_before = WC()->session->get('ttos_fulfilment_method', null);
function fulfilment_check($condition, $name) {
    if (!$condition) throw new RuntimeException($name);
    $GLOBALS['fulfilment_checks']++;
    echo "PASS $name\n";
}
$GLOBALS['fulfilment_checks'] = 0;
try {
    TTOS_Settings::update_section('trading', array_merge($before, array('delivery_enabled'=>'1', 'collection_enabled'=>'1')));
    $commerce = array();
    foreach (array('woocommerce_currency','woocommerce_calc_taxes','woocommerce_stripe_settings','woocommerce_cod_settings') as $key) $commerce[$key]=get_option($key);
    $shipping_before = WC()->session->get('chosen_shipping_methods');
    fulfilment_check(TTOS_Operations::set_fulfilment_method('collection'), 'Collection can be saved in the native session');
    fulfilment_check(TTOS_Operations::current_checkout_method() === 'collection', 'Menu reads saved collection');
    $fields=TTOS_Operations::checkout_fields(array());
    fulfilment_check($fields['order']['ttos_fulfilment_method']['default'] === 'collection', 'Checkout defaults to the saved collection choice');
    $r=new ReflectionMethod('TTOS_Features','current_fulfilment_method');$r->setAccessible(true);
    fulfilment_check($r->invoke(null)==='collection', 'Delivery fee reader shares the same collection choice');
    TTOS_Operations::sync_checkout_method('ttos_fulfilment_method=delivery&billing_city=Example');
    fulfilment_check(WC()->session->get('ttos_fulfilment_method') === 'delivery', 'Native checkout review updates the same session');
    TTOS_Operations::sync_checkout_method('ttos_fulfilment_method[]=collection');
    fulfilment_check(WC()->session->get('ttos_fulfilment_method') === 'delivery', 'Malformed review payload preserves previous choice');
    fulfilment_check(!TTOS_Operations::set_fulfilment_method('invalid'), 'Unknown choices are rejected');
    TTOS_Settings::update_section('trading', array_merge($before, array('delivery_enabled'=>'0','collection_enabled'=>'1')));
    fulfilment_check(!TTOS_Operations::set_fulfilment_method('delivery') && TTOS_Operations::current_checkout_method()==='collection', 'Disabled delivery is rejected and stale preference has an available fallback');
    fulfilment_check(WC()->session->get('chosen_shipping_methods') === $shipping_before, 'Preference does not replace native shipping selection');
    foreach($commerce as $key=>$value) fulfilment_check(get_option($key)===$value, 'Preference preserves '.$key);
    echo 'FULFILMENT ASSERTIONS '.$GLOBALS['fulfilment_checks']." passed\n";
} finally {
    $_POST=array();
    TTOS_Settings::update_section('trading', $before);
    WC()->session->set('ttos_fulfilment_method', $session_before);
}

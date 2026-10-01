<?php
// Disposable fixture only; this file is not included in the plugin package.
if (!defined('TTOS_FIXTURE_ONLY') || TTOS_FIXTURE_ONLY !== true || wp_get_environment_type() !== 'local') {
    throw new RuntimeException('Use the disposable local fixture.');
}
wc_load_cart();
$before = TTOS_Settings::get('trading');
$checkout_before = TTOS_Operations::get('checkout');
$hours_before = TTOS_Site_Content::get('opening_times');
$session_before = WC()->session->get('ttos_fulfilment_method', null);
$product = null;
$cart_key = null;
function fulfilment_check($condition, $name) {
    if (!$condition) throw new RuntimeException($name);
    $GLOBALS['fulfilment_checks']++;
    echo "PASS $name\n";
}
$GLOBALS['fulfilment_checks'] = 0;
function fulfilment_selected_time($html) {
    $tags = new WP_HTML_Tag_Processor($html);
    while ($tags->next_tag(array('tag_name'=>'OPTION'))) {
        if ($tags->get_attribute('selected') !== null) return $tags->get_attribute('value');
    }
    return '';
}
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
    $_POST['ttos_fulfilment_method'] = 'collection';
    TTOS_Operations::sync_posted_checkout_method();
    fulfilment_check(WC()->session->get('ttos_fulfilment_method') === 'collection', 'Native form checkout saves collection in the same session');
    $_POST['ttos_fulfilment_method'] = array('delivery');
    TTOS_Operations::sync_posted_checkout_method();
    fulfilment_check(WC()->session->get('ttos_fulfilment_method') === 'collection', 'Non-scalar native form method preserves the saved choice');
    $_POST = array();
    fulfilment_check(!TTOS_Operations::set_fulfilment_method('invalid'), 'Unknown choices are rejected');
    TTOS_Settings::update_section('trading', array_merge($before, array('delivery_enabled'=>'0','collection_enabled'=>'1')));
    fulfilment_check(!TTOS_Operations::set_fulfilment_method('delivery') && TTOS_Operations::current_checkout_method()==='collection', 'Disabled delivery is rejected and stale preference has an available fallback');
    fulfilment_check(WC()->session->get('chosen_shipping_methods') === $shipping_before, 'Preference does not replace native shipping selection');
    foreach($commerce as $key=>$value) fulfilment_check(get_option($key)===$value, 'Preference preserves '.$key);

    TTOS_Settings::update_section('trading', array_merge($before, array('delivery_enabled'=>'1', 'collection_enabled'=>'1')));
    $hours = $hours_before;
    $hours['override'] = 'normal';
    $hours['temporary_closure'] = '0';
    foreach ($hours['days'] as &$day) {
        $day = array_merge($day, array('closed'=>'0','open'=>'08:00','close'=>'23:00','collection_open'=>'08:00','collection_close'=>'23:00','delivery_open'=>'16:00','delivery_close'=>'23:00'));
    }
    unset($day);
    TTOS_Site_Content::update_section('opening_times', $hours);
    $checkout = array_merge($checkout_before, array('time_mode'=>'slot','lead_time_delivery'=>'0','lead_time_collection'=>'0','preorder_enabled'=>'1','max_days_ahead'=>'2'));
    TTOS_Operations::update_section('checkout', $checkout);
    $product = new WC_Product_Simple();
    $product->set_name('Disposable fulfilment slot fixture');
    $product->set_regular_price('10');
    $product->save();
    $cart_key = WC()->cart->add_to_cart($product->get_id());
    fulfilment_check((bool) $cart_key, 'Slot fixture uses a real nonempty WooCommerce cart');
    $shipping_after_cart = WC()->session->get('chosen_shipping_methods');
    $tomorrow = (new DateTimeImmutable('tomorrow', wp_timezone()))->format('Y-m-d');
    $morning = $tomorrow . 'T08:00';
    $evening = $tomorrow . 'T20:00';
    $original_fragments = array('.native-review'=>'unchanged');
    TTOS_Operations::sync_checkout_method('ttos_fulfilment_method=collection');
    $_POST['post_data'] = 'ttos_fulfilment_method=collection&ttos_requested_time=' . rawurlencode($morning);
    $fragments = apply_filters('woocommerce_update_order_review_fragments', $original_fragments);
    fulfilment_check(isset($fragments['#ttos_requested_time_field']), 'Native checkout review includes the time-field fragment');
    fulfilment_check($fragments['.native-review'] === 'unchanged', 'Time refresh preserves native review fragments');
    fulfilment_check(fulfilment_selected_time($fragments['#ttos_requested_time_field']) === $morning, 'Collection retains its valid morning slot');
    TTOS_Operations::sync_checkout_method('ttos_fulfilment_method=delivery');
    $_POST['post_data'] = 'ttos_fulfilment_method=delivery&ttos_requested_time=' . rawurlencode($morning);
    $delivery = apply_filters('woocommerce_update_order_review_fragments', $original_fragments)['#ttos_requested_time_field'];
    fulfilment_check(strpos($delivery, $morning) === false, 'Delivery refresh removes collection-only morning slots');
    fulfilment_check(fulfilment_selected_time($delivery) === '', 'An unavailable scheduled time is not retained');
    $_POST['post_data'] = 'ttos_fulfilment_method=delivery&ttos_requested_time=' . rawurlencode($evening);
    $delivery = TTOS_Operations::checkout_time_fragment($original_fragments)['#ttos_requested_time_field'];
    fulfilment_check(fulfilment_selected_time($delivery) === $evening, 'A still-valid scheduled time survives totals refresh');
    $_POST['post_data'] = 'ttos_requested_time[]=invalid';
    $delivery = TTOS_Operations::checkout_time_fragment($original_fragments)['#ttos_requested_time_field'];
    fulfilment_check(fulfilment_selected_time($delivery) === '', 'Non-scalar requested time is ignored');
    TTOS_Operations::update_section('checkout', array_merge($checkout, array('time_mode'=>'asap')));
    $hours['override'] = 'force_open';
    TTOS_Site_Content::update_section('opening_times', $hours);
    $asap = TTOS_Operations::checkout_time_fragment($original_fragments)['#ttos_requested_time_field'];
    fulfilment_check(strpos($asap, 'type="hidden"') !== false && strpos($asap, 'value="asap"') !== false, 'ASAP-only refresh keeps the native hidden field');
    fulfilment_check(WC()->session->get('chosen_shipping_methods') === $shipping_after_cart, 'Slot refresh does not select a native shipping rate');
    WC()->cart->remove_cart_item($cart_key);
    $cart_key = null;
    if (WC()->cart->is_empty()) fulfilment_check(TTOS_Operations::checkout_time_fragment($original_fragments) === $original_fragments, 'Expired empty-cart review remains owned by WooCommerce');
    echo 'FULFILMENT ASSERTIONS '.$GLOBALS['fulfilment_checks']." passed\n";
} finally {
    if ($cart_key) WC()->cart->remove_cart_item($cart_key);
    if ($product) $product->delete(true);
    $_POST=array();
    TTOS_Settings::update_section('trading', $before);
    TTOS_Operations::update_section('checkout', $checkout_before);
    TTOS_Site_Content::update_section('opening_times', $hours_before);
    WC()->session->set('ttos_fulfilment_method', $session_before);
}

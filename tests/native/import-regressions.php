<?php
// Included only by the disposable native test runner.
native_test('JSON contact import writes the owner CRM without creating login accounts', function () {
    $result = TTOS_Import::import_customers(array(array('email' => 'new-contact@example.test', 'first_name' => 'New', 'last_name' => 'Contact', 'phone' => '02079460000', 'marketing_ok' => '1')));
    must($result['created'] === 1 && !$result['errors']);
    must(!get_user_by('email', 'new-contact@example.test'));
    $snapshot = TTOS_Admin::customer_snapshot_enhanced();
    must($snapshot['new-contact@example.test']['name'] === 'New Contact');
    must(TTOS_Production::customer_marketing_opt_in('new-contact@example.test'));
});
native_test('Contact import cannot change an existing staff account or role', function () {
    $before = get_user_by('email', 'owner@example.test');
    $name = get_user_meta($before->ID, 'first_name', true);
    TTOS_Import::import_customers(array(array('email' => 'owner@example.test', 'first_name' => 'Contact record only', 'phone' => '02079460001')));
    $after = get_userdata($before->ID);
    must($after->roles === $before->roles && $after->user_pass === $before->user_pass);
    must(get_user_meta($before->ID, 'first_name', true) === $name);
});
native_test('Omitted contact fields preserve notes, phone and recorded consent', function () {
    TTOS_Import::import_customers(array(array('email' => 'new-contact@example.test', 'internal_notes' => 'Preserve this note')));
    $profile = get_option('ttos_customer_profiles')['new-contact@example.test'];
    must($profile['phone'] === '02079460000' && $profile['internal_notes'] === 'Preserve this note');
    must($profile['marketing_ok'] === '1');
});
native_test('Bulk import cannot resubscribe either guests or registered opt-outs', function () {
    TTOS_Production::unsubscribe_customer('guest-opt-out@example.test');
    $r = TTOS_Import::import_customers(array(array('email' => 'guest-opt-out@example.test', 'marketing_ok' => '1'), array('email' => 'owner@example.test', 'marketing_ok' => '1')));
    must($r['processed'] === 2 && count($r['warnings']) === 2);
    must(!TTOS_Production::customer_marketing_opt_in('guest-opt-out@example.test'));
    must(!TTOS_Production::customer_marketing_opt_in('owner@example.test'));
});
native_test('Explicit imported opt-out cancels the same native follow-up job', function () {
    wp_schedule_single_event(time() + 3600, 'ttos_send_retention_email', array('new-contact@example.test'));
    TTOS_Import::import_customers(array(array('email' => 'new-contact@example.test', 'marketing_ok' => '0')));
    must(!wp_next_scheduled('ttos_send_retention_email', array('new-contact@example.test')));
    must(!TTOS_Production::customer_marketing_opt_in('new-contact@example.test'));
});
native_test('Malformed customer rows are skipped without mutation or fatal errors', function () {
    $before = get_option('ttos_customer_profiles');
    $result = TTOS_Import::import_customers(array(array('email' => array('bad@example.test')), 'not a row', array('email' => 'new-contact@example.test', 'marketing_ok' => 'maybe', 'name' => 'Do not save')));
    must($result['skipped'] === 3 && $result['processed'] === 0);
    must(get_option('ttos_customer_profiles') === $before);
});
native_test('General privacy consent is not silently treated as marketing opt-in', function () {
    $result = TTOS_Import::import_customers(array(array('email' => 'privacy-only@example.test', 'gdpr_consent' => '1')));
    must($result['created'] === 1 && count($result['warnings']) === 1);
    must(!TTOS_Production::customer_marketing_opt_in('privacy-only@example.test'));
});
native_test('CSV contact import shares JSON semantics including existing opt-outs', function () {
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, "\xEF\xBB\xBFemail,name,marketing_ok\nguest-opt-out@example.test,CSV guest,1\ncsv-new@example.test,CSV new,1\n");
    rewind($stream);
    try { $result = TTOS_Import::import_customer_stream($stream); } finally { fclose($stream); }
    must($result['processed'] === 2 && $result['created'] === 1);
    must(!TTOS_Production::customer_marketing_opt_in('guest-opt-out@example.test'));
    must(TTOS_Production::customer_marketing_opt_in('csv-new@example.test'));
    must(!get_user_by('email', 'csv-new@example.test'));
});
native_test('Menu JSON import creates native products and repeated SKU does not duplicate', function () {
    $row = array('name' => 'Imported fixture meal', 'sku' => 'import-fixture-sku', 'price' => '8.25', 'status' => 'draft', 'allergens' => 'milk');
    $r = TTOS_Import::import_menu(array($row)); must($r['created'] === 1 && !$r['errors']);
    $id = wc_get_product_id_by_sku('import-fixture-sku'); must($id > 0);
    $r = TTOS_Import::import_menu(array($row)); must($r['updated'] === 1 && $r['created'] === 0);
    must(wc_get_product_id_by_sku('import-fixture-sku') === $id);
    must((float) wc_get_product($id)->get_price() === 8.25);
});
native_test('Partial menu import preserves price, visibility, allergens and tracked stock', function () {
    $id = wc_get_product_id_by_sku('import-fixture-sku');
    $p = wc_get_product($id); $p->set_manage_stock(true); $p->set_stock_quantity(3); $p->save();
    $r = TTOS_Import::import_menu(array(array('sku' => 'import-fixture-sku', 'description' => 'Only this changes')));
    must($r['updated'] === 1 && !$r['errors']);
    $p = wc_get_product($id);
    must((float) $p->get_regular_price() === 8.25 && $p->get_status() === 'draft');
    must($p->get_meta('_ttos_allergens') === 'milk' && $p->get_stock_quantity() === 3 && $p->get_manage_stock());
});
native_test('Invalid menu rows and native variable products are not partially overwritten', function () {
    $id = wc_get_product_id_by_sku('import-fixture-sku');
    $variable = new WC_Product_Variable(); $variable->set_name('Import native variable'); $variable->save();
    $r = TTOS_Import::import_menu(array(array('product_id' => $id, 'name' => 'Do not save', 'price' => '-3'), array('product_id' => $id, 'status' => 'invented'), array('name' => 'No price given'), array('price' => array('4')), array('product_id' => $variable->get_id(), 'price' => '2')));
    must($r['skipped'] === 5 && !$r['processed']);
    must(wc_get_product($id)->get_name() === 'Imported fixture meal');
    must(wc_get_product($variable->get_id())->is_type('variable'));
});
native_test('Import REST uses existing permissions without a second approval or key', function () {
    $owner = get_user_by('login', 'fixtureowner');
    wp_set_current_user($owner->ID);
    $r = api_request('/ttos/v1/import/menu', array('rows' => array(array('name' => 'Explicit zero fixture', 'price' => '0'))));
    must($r->get_status() === 200 && $r->get_data()['created'] === 1);
    $kitchen = get_user_by('login', 'fixturekitchen');
    wp_set_current_user($kitchen->ID);
    must(api_request('/ttos/v1/import/customers', array('rows' => array()))->get_status() === 403);
    wp_set_current_user(0);
    must(in_array(api_request('/ttos/v1/import/menu', array('rows' => array()))->get_status(), array(401,403), true));
});

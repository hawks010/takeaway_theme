from pathlib import Path
import json, zipfile, hashlib
r=Path('.')
def replace(path, old, new, count=1):
 p=r/path;s=p.read_text();assert s.count(old)==count,(path,s.count(old),old[:65]);p.write_text(s.replace(old,new))
ops='takeaway-os/includes/class-operations.php'
replace(ops,"        add_filter('woocommerce_checkout_fields', array(__CLASS__, 'checkout_fields'));", "        add_action('template_redirect', array(__CLASS__, 'handle_fulfilment_selection'), 5);\n        add_action('woocommerce_checkout_update_order_review', array(__CLASS__, 'sync_checkout_method'), 5);\n        add_filter('woocommerce_checkout_fields', array(__CLASS__, 'checkout_fields'));")
replace(ops,"        $fields['order']['ttos_fulfilment_method'] = array(","        $method = self::current_checkout_method(array_keys($options));\n        $fields['order']['ttos_fulfilment_method'] = array(")
replace(ops,"'options' => $options, 'default' => isset($options[$checkout['default_method']]) ? $checkout['default_method'] : array_key_first($options), 'priority' => 5,", "'options' => $options, 'default' => $method, 'priority' => 5,\n            'class' => array('form-row-wide', 'update_totals_on_change'),")
replace(ops,"\n        $method = self::current_checkout_method(array_keys($options));\n        $state = self::ordering_state($method);","\n        $state = self::ordering_state($method);")
p=r/ops;s=p.read_text();a=s.index('    private static function current_checkout_method(');b=s.index('    private static function closed_notice_message(',a)
s=s[:a]+'''    /** The same available choices used by checkout and the menu form. */
    public static function available_fulfilment_methods(): array {
        $trading = TTOS_Settings::get('trading');
        $methods = array();
        foreach (array('delivery', 'collection') as $method) {
            if (($trading[$method . '_enabled'] ?? '0') === '1') $methods[] = $method;
        }
        return $methods;
    }

    /** Read the existing WooCommerce session; do not create a session on page views. */
    public static function current_checkout_method(array $allowed = array()): string {
        $allowed = $allowed ?: self::available_fulfilment_methods();
        if (!$allowed) return '';
        $method = is_string($_POST['ttos_fulfilment_method'] ?? null)
            ? wp_unslash($_POST['ttos_fulfilment_method']) : '';
        if (!in_array($method, $allowed, true) && function_exists('WC') && WC()->session) {
            $method = WC()->session->get('ttos_fulfilment_method', '');
        }
        if (!in_array($method, $allowed, true) && function_exists('ttos_get_opening_hours')) {
            $hours = ttos_get_opening_hours();
            $method = $hours['default_fulfilment'] ?? '';
        }
        if (!in_array($method, $allowed, true)) $method = self::get('checkout', 'default_method');
        return in_array($method, $allowed, true) ? $method : (string) reset($allowed);
    }

    /** Writes only our existing preference, never shipping rates, totals or payments. */
    public static function set_fulfilment_method($method): bool {
        if (!is_string($method) || !in_array($method, self::available_fulfilment_methods(), true)
            || !function_exists('WC') || !WC()->session) return false;
        WC()->session->set('ttos_fulfilment_method', $method);
        return true;
    }

    /** WooCommerce has already checked its own update-order-review nonce. */
    public static function sync_checkout_method($post_data): void {
        if (!is_string($post_data)) return;
        $posted = array();
        parse_str($post_data, $posted);
        self::set_fulfilment_method($posted['ttos_fulfilment_method'] ?? null);
    }

    /** Ordinary POST/redirect/GET: it also works with JavaScript disabled. */
    public static function handle_fulfilment_selection(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
            || ($_POST['ttos_action'] ?? '') !== 'select_fulfilment') return;
        $nonce = is_string($_POST['ttos_fulfilment_nonce'] ?? null)
            ? wp_unslash($_POST['ttos_fulfilment_nonce']) : '';
        if (!wp_verify_nonce($nonce, 'ttos_select_fulfilment')) {
            wp_die(__('Your selection was not saved. Return to the menu, reload it and try again.', 'takeaway-os'), '', array('response' => 403));
        }
        if (!function_exists('WC') || !WC()->session) {
            wp_die(__('Ordering is temporarily unavailable. Please try again.', 'takeaway-os'), '', array('response' => 503));
        }
        $method = is_string($_POST['ttos_fulfilment_method'] ?? null)
            ? wp_unslash($_POST['ttos_fulfilment_method']) : null;
        if (!self::set_fulfilment_method($method)) {
            wp_die(__('This order type is unavailable. Return to the menu and choose an available option.', 'takeaway-os'), '', array('response' => 400));
        }
        // Retain a guest's choice even before their first item is added.
        WC()->session->set_customer_session_cookie(true);
        nocache_headers();
        wp_safe_redirect(wc_get_page_permalink('shop'), 303);
        exit;
    }

'''+s[b:];p.write_text(s)
p=r/'takeaway-os/includes/class-features.php';s=p.read_text();a=s.index('    private static function current_fulfilment_method(): string {');b=s.index('    public static function apply_advanced_zone_fee(',a)
s=s[:a]+'''    private static function current_fulfilment_method(): string {
        return TTOS_Operations::current_checkout_method();
    }

'''+s[b:];p.write_text(s)
archive='takeaway-theme/archive-product.php'
replace(archive,"    $delivery_on_menu   = (string) tt_content('delivery_collection', 'delivery_enabled', '1') === '1';\n    $collection_on_menu = (string) tt_content('delivery_collection', 'collection_enabled', '1') === '1';", "    $has_fulfilment_form = is_callable(array('TTOS_Operations', 'available_fulfilment_methods'));\n    $methods = $has_fulfilment_form ? TTOS_Operations::available_fulfilment_methods() : array();\n    $delivery_on_menu = $has_fulfilment_form ? in_array('delivery', $methods, true) : (string) tt_content('delivery_collection', 'delivery_enabled', '1') === '1';\n    $collection_on_menu = $has_fulfilment_form ? in_array('collection', $methods, true) : (string) tt_content('delivery_collection', 'collection_enabled', '1') === '1';")
replace(archive,"    $req_fulfilment = sanitize_key(isset($_GET['fulfilment']) ? $_GET['fulfilment'] : '');\n    $menu_default_mode = ($req_fulfilment === 'collection' && $collection_on_menu) ? 'collection'\n        : ($delivery_on_menu ? 'delivery' : 'collection');", "    $menu_default_mode = $has_fulfilment_form ? TTOS_Operations::current_checkout_method()\n        : ($delivery_on_menu ? 'delivery' : 'collection');")
p=r/archive;s=p.read_text();a=s.index('            <?php if ($delivery_on_menu && $collection_on_menu) : ?>');b=s.index('            <?php if ($delivery_on_menu && $show_postcode) : ?>',a)
s=s[:a]+'''            <?php if ($delivery_on_menu && $collection_on_menu && $has_fulfilment_form) : ?>
            <form method="post" action="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="tt-fulfilment-toggle" role="group" aria-label="<?php esc_attr_e('Order type', 'takeaway-theme'); ?>">
                <input type="hidden" name="ttos_action" value="select_fulfilment">
                <?php wp_nonce_field('ttos_select_fulfilment', 'ttos_fulfilment_nonce', false); ?>
                <button type="submit" name="ttos_fulfilment_method" value="collection" class="tt-fulfilment-pill<?php echo $menu_default_mode === 'collection' ? ' is-active' : ''; ?>" aria-pressed="<?php echo $menu_default_mode === 'collection' ? 'true' : 'false'; ?>"><?php esc_html_e('Collect', 'takeaway-theme'); ?></button>
                <button type="submit" name="ttos_fulfilment_method" value="delivery" class="tt-fulfilment-pill<?php echo $menu_default_mode === 'delivery' ? ' is-active' : ''; ?>" aria-pressed="<?php echo $menu_default_mode === 'delivery' ? 'true' : 'false'; ?>"><?php esc_html_e('Delivery', 'takeaway-theme'); ?></button>
            </form>
            <?php endif; ?>
'''+s[b:];p.write_text(s)
p=r/'takeaway-theme/assets/js/theme.js';s=p.read_text();a=s.index('    /* ── Fulfilment toggle');b=s.index('    /* ── Mobile drawer',a);p.write_text(s[:a]+s[b:])
replace('takeaway-os/takeaway-os.php','1.3.13-rc.1','1.3.13-rc.2',2)
replace('takeaway-theme/functions.php',"define('TTHEME_VERSION', '0.3.34');","define('TTHEME_VERSION', '0.3.35-rc.1');")
replace('takeaway-theme/style.css','Version: 0.3.34','Version: 0.3.35-rc.1')
bundle=r/'takeaway-theme/inc/bundled-plugins/takeaway-os.zip'
with zipfile.ZipFile(bundle) as old: names=[i.filename for i in old.infolist() if not i.is_dir()]
assert len(names)==50 and all(n.startswith('takeaway-os/') and (r/n).is_file() for n in names)
with zipfile.ZipFile(bundle,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
 for name in sorted(names):
  info=zipfile.ZipInfo(name,date_time=(2026,9,30,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16;z.writestr(info,(r/name).read_bytes())
manifest=r/'takeaway-theme/inc/bundled-plugins/manifest.json';data=json.loads(manifest.read_text());data['takeaway-os']['version']='1.3.13-rc.2';manifest.write_text(json.dumps(data,indent=2)+'\n')
with zipfile.ZipFile(bundle) as z:
 assert z.testzip() is None
 for name in names: assert z.read(name)==(r/name).read_bytes()
print('Package files',len(names),'SHA256',hashlib.sha256(bundle.read_bytes()).hexdigest())

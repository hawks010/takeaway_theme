<?php
/** Import into the existing CRM profile store and native menu-product service. */
defined('ABSPATH') || exit;

final class TTOS_Import {
    const NAMESPACE = 'ttos/v1';
    const BATCH_SIZE = 200;

    public static function hooks(): void {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function register_routes(): void {
        foreach (array('customers', 'menu') as $kind) {
            register_rest_route(self::NAMESPACE, '/import/' . $kind, array(
                'methods' => 'POST', 'callback' => array(__CLASS__, 'rest_import_' . $kind),
                'permission_callback' => array(__CLASS__, $kind === 'menu' ? 'menu_permission' : 'customer_permission'),
                'args' => array('rows' => array('required' => true, 'type' => 'array', 'maxItems' => self::BATCH_SIZE)),
            ));
        }
    }

    public static function admin_permission(): bool { return current_user_can('ttos_manage_settings'); }
    public static function menu_permission(): bool { return current_user_can('ttos_manage_menu'); }
    public static function customer_permission(): bool { return current_user_can('ttos_view_reports'); }

    public static function rest_import_customers(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(self::import_customers((array) $request->get_param('rows')), 200);
    }

    public static function rest_import_menu(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(self::import_menu((array) $request->get_param('rows')), 200);
    }

    private static function result(): array {
        return array('processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array(), 'warnings' => array());
    }

    private static function clean_row($row): ?array {
        if (!is_array($row)) return null;
        $clean = array();
        foreach ($row as $key => $value) {
            if (!is_string($key) || (!is_scalar($value) && $value !== null)) return null;
            $clean[$key] = is_bool($value) ? ($value ? '1' : '0') : trim((string) $value);
        }
        return $clean;
    }

    private static function boolean(string $value): ?bool {
        $value = strtolower($value);
        if (in_array($value, array('1', 'yes', 'true', 'y'), true)) return true;
        if (in_array($value, array('', '0', 'no', 'false', 'n'), true)) return false;
        return null;
    }

    private static function reject(array &$result, int $number, string $message): void {
        $result['skipped']++;
        $result['errors'][] = 'Row ' . $number . ': ' . $message;
    }

    /** Import contacts, not WordPress logins. Omitted values preserve existing fields. */
    public static function import_customers(array $rows): array {
        $result = self::result();
        $result['storage'] = 'crm_profiles';
        if (count($rows) > self::BATCH_SIZE) {
            $result['errors'][] = 'Send customer imports in batches of at most 200 rows.';
            return $result;
        }
        foreach (array_values($rows) as $index => $raw) {
            $number = $index + 1;
            $row = self::clean_row($raw);
            if ($row === null) { self::reject($result, $number, 'Expected simple field values.'); continue; }
            $email = strtolower((string) ($row['email'] ?? ''));
            if (!is_email($email)) { self::reject($result, $number, 'Invalid or missing email.'); continue; }
            $allowed = array_key_exists('marketing_ok', $row) ? self::boolean($row['marketing_ok']) : null;
            if (array_key_exists('marketing_ok', $row) && $allowed === null) {
                self::reject($result, $number, 'Marketing permission must be yes/no or 1/0.'); continue;
            }
            $profiles = get_option('ttos_customer_profiles', array());
            if (!is_array($profiles)) $profiles = array();
            $exists = isset($profiles[$email]) && is_array($profiles[$email]);
            $profile = $exists ? $profiles[$email] : array('marketing_ok' => '0');
            if (!array_key_exists('name', $row) && (isset($row['first_name']) || isset($row['last_name']))) {
                $row['name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            }
            foreach (array('name', 'phone', 'postcode', 'tags', 'birthday', 'internal_notes') as $field) {
                if (array_key_exists($field, $row)) {
                    $profile[$field] = $field === 'internal_notes' ? sanitize_textarea_field($row[$field]) : sanitize_text_field($row[$field]);
                }
            }
            if (isset($row['gdpr_consent']) && !array_key_exists('marketing_ok', $row)) {
                $result['warnings'][] = 'Row ' . $number . ': general privacy consent was not treated as marketing permission.';
            }
            // A bulk list cannot silently resubscribe someone already recorded as opted out.
            $user = get_user_by('email', $email);
            $opted_out = ($exists && ($profile['marketing_ok'] ?? '0') !== '1')
                || ($user && get_user_meta($user->ID, '_ttos_retention_opt_out', true) === '1');
            if ($allowed === true && $opted_out) {
                $allowed = false;
                $result['warnings'][] = 'Row ' . $number . ': the existing opt-out was preserved; record fresh consent in the individual profile.';
            }
            $profile['updated'] = time();
            $profiles[$email] = $profile;
            update_option('ttos_customer_profiles', $profiles, false);
            if ($allowed !== null) TTOS_Production::record_marketing_permission($email, $allowed);
            $result['processed']++;
            $result[$exists ? 'updated' : 'created']++;
        }
        return $result;
    }

    /** The CSV form uses exactly the same field and permission-record semantics as JSON imports. */
    public static function import_customer_stream($stream): array {
        $result = self::result();
        if (!is_resource($stream)) { $result['errors'][] = 'The CSV could not be read.'; return $result; }
        $headers = fgetcsv($stream, 0, ',', '"', '');
        if (!is_array($headers)) { $result['errors'][] = 'The CSV is empty.'; return $result; }
        $headers = array_map(static function ($header) {
            return sanitize_key(preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $header)));
        }, $headers);
        if (!in_array('email', $headers, true)) { $result['errors'][] = 'The CSV needs an email column.'; return $result; }
        $line = 1;
        while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $line++;
            if (!array_filter($values, static function ($v) { return trim((string) $v) !== ''; })) continue;
            $row = array();
            foreach ($headers as $key => $field) {
                if ($field !== '') $row[$field] = $values[$key] ?? '';
            }
            $item = self::import_customers(array($row));
            foreach (array('processed', 'created', 'updated', 'skipped') as $key) $result[$key] += $item[$key];
            foreach (array('errors', 'warnings') as $key) {
                foreach ($item[$key] as $message) $result[$key][] = 'CSV line ' . $line . ': ' . preg_replace('/^Row 1: /', '', $message);
            }
        }
        return $result;
    }

    /** Match an explicit ID, SKU, or unambiguous name; use the same save service as Menu Builder. */
    public static function import_menu(array $rows): array {
        $result = self::result();
        if (!function_exists('wc_get_product')) { $result['errors'][] = 'WooCommerce is not active.'; return $result; }
        if (count($rows) > self::BATCH_SIZE) { $result['errors'][] = 'Send menu imports in batches of at most 200 rows.'; return $result; }
        foreach (array_values($rows) as $index => $raw) {
            $number = $index + 1;
            $row = self::clean_row($raw);
            if ($row === null) { self::reject($result, $number, 'Expected simple field values.'); continue; }
            if ((isset($raw['price']) && is_bool($raw['price'])) || (isset($raw['sale_price']) && is_bool($raw['sale_price']))) {
                self::reject($result, $number, 'Prices must be numbers, not boolean flags.'); continue;
            }
            $name = sanitize_text_field($row['name'] ?? '');
            $sku = sanitize_text_field($row['sku'] ?? '');
            $id = 0;
            if (!empty($row['product_id'])) {
                if (!ctype_digit($row['product_id'])) { self::reject($result, $number, 'Invalid product ID.'); continue; }
                $id = (int) $row['product_id'];
                if (!wc_get_product($id)) { self::reject($result, $number, 'Product ID not found.'); continue; }
            } elseif ($sku !== '') {
                $id = (int) wc_get_product_id_by_sku($sku);
            }
            if (!$id && $name !== '') {
                $found = get_posts(array('post_type' => 'product', 'title' => $name, 'numberposts' => 2,
                    'fields' => 'ids', 'post_status' => array('publish', 'draft')));
                if (count($found) > 1) { self::reject($result, $number, 'More than one product has this name; supply a product ID or SKU.'); continue; }
                $id = $found ? (int) $found[0] : 0;
            }
            $existing = $id ? wc_get_product($id) : false;
            if ($existing && !$existing->is_type('simple')) { self::reject($result, $number, 'Manage this product type in WooCommerce; its existing configuration was preserved.'); continue; }
            if (!$existing && ($name === '' || !array_key_exists('price', $row) || $row['price'] === '')) {
                self::reject($result, $number, 'New menu items require a name and an explicit price.'); continue;
            }
            $data = array('product_id' => $id);
            if (array_key_exists('name', $row)) $data['name'] = $name;
            if (array_key_exists('sku', $row)) $data['sku'] = $sku;
            $invalid = false;
            foreach (array('price', 'sale_price') as $field) {
                if (!array_key_exists($field, $row)) continue;
                if ($field === 'price' && $row[$field] === '') continue;
                if ($row[$field] !== '' && (!is_numeric($row[$field]) || !is_finite((float) $row[$field]) || (float) $row[$field] < 0)) {
                    self::reject($result, $number, 'Prices must be non-negative numbers.'); $invalid = true; break;
                }
                $data[$field] = $row[$field];
            }
            if ($invalid) continue;
            if (array_key_exists('status', $row)) {
                if (!in_array($row['status'], array('publish', 'draft'), true)) { self::reject($result, $number, 'Status must be publish or draft.'); continue; }
                $data['hidden'] = $row['status'] === 'draft';
            }
            foreach (array('description', 'category', 'allergens', 'badges') as $field) {
                if (array_key_exists($field, $row)) $data[$field] = $row[$field];
            }
            if (!isset($data['description']) && isset($row['short_description'])) $data['description'] = $row['short_description'];
            $image_url = (string) ($row['image_url'] ?? '');
            if ($image_url !== '' && (!filter_var($image_url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) wp_parse_url($image_url, PHP_URL_SCHEME)), array('https', 'http'), true))) {
                self::reject($result, $number, 'Invalid image URL.'); continue;
            }
            $saved = TTOS_WooCommerce::create_or_update_product($data);
            if (!$saved) { self::reject($result, $number, 'WooCommerce could not save this product; check its fields and SKU.'); continue; }
            $result['processed']++;
            $result[$existing ? 'updated' : 'created']++;
            if ($image_url !== '') {
                $image = self::maybe_sideload_image($saved, $image_url);
                if (is_wp_error($image)) $result['warnings'][] = 'Row ' . $number . ': product saved, but its image could not be imported.';
            }
        }
        return $result;
    }

    private static function maybe_sideload_image(int $product_id, string $url) {
        $product = wc_get_product($product_id);
        if (!$product) return new WP_Error('ttos_product_missing', 'Product not found.');
        $existing = $product->get_image_id();
        if ($existing && get_post_meta($existing, '_ttos_source_url', true) === $url) return $existing;
        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        $attachment = media_sideload_image($url, $product_id, null, 'id');
        if (is_wp_error($attachment)) return $attachment;
        $product->set_image_id($attachment);
        $product->save();
        update_post_meta($attachment, '_ttos_source_url', $url);
        return $attachment;
    }
}

<?php
/**
 * Takeaway OS Import Engine
 *
 * REST-accessible import layer for customers and menu products.
 * Intentionally stateless: each call receives rows and returns a result summary.
 */

defined('ABSPATH') || exit;

final class TTOS_Import {

    const NAMESPACE = 'ttos/v1';

    public static function hooks(): void {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/import/customers', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'rest_import_customers'),
            'permission_callback' => array(__CLASS__, 'admin_permission'),
            'args'                => array(
                'rows' => array('required' => true, 'type' => 'array'),
            ),
        ));

        register_rest_route(self::NAMESPACE, '/import/menu', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'rest_import_menu'),
            'permission_callback' => array(__CLASS__, 'admin_permission'),
            'args'                => array(
                'rows' => array('required' => true, 'type' => 'array'),
            ),
        ));
    }

    public static function admin_permission(): bool {
        return current_user_can('ttos_manage_settings');
    }

    // -------------------------------------------------------------------------
    // REST callbacks
    // -------------------------------------------------------------------------

    public static function rest_import_customers(WP_REST_Request $request): WP_REST_Response {
        $rows = (array) $request->get_param('rows');
        $result = self::import_customers($rows);
        return rest_ensure_response($result);
    }

    public static function rest_import_menu(WP_REST_Request $request): WP_REST_Response {
        $rows = (array) $request->get_param('rows');
        $result = self::import_menu($rows);
        return rest_ensure_response($result);
    }

    // -------------------------------------------------------------------------
    // Public business-logic methods (also callable directly from PHP)
    // -------------------------------------------------------------------------

    /**
     * Upsert WordPress users from an array of row arrays.
     * Each row must contain at minimum: email
     * Optional columns: first_name, last_name, phone, gdpr_consent (1/0)
     *
     * @param  array $rows  Array of associative arrays (one per customer).
     * @return array        ['created' => n, 'updated' => n, 'errors' => [...]]
     */
    public static function import_customers(array $rows): array {
        $created = 0;
        $updated = 0;
        $errors  = array();

        foreach ($rows as $index => $row) {
            $row  = array_map('trim', (array) $row);
            $email = isset($row['email']) ? sanitize_email($row['email']) : '';

            if (!is_email($email)) {
                $errors[] = "Row " . ((int) $index + 1) . ": invalid or missing email — skipped.";
                continue;
            }

            $first_name = sanitize_text_field($row['first_name'] ?? '');
            $last_name  = sanitize_text_field($row['last_name'] ?? '');
            $phone      = sanitize_text_field($row['phone'] ?? '');
            $consent    = !empty($row['gdpr_consent']) && $row['gdpr_consent'] !== '0' ? '1' : '0';

            $existing = get_user_by('email', $email);

            if ($existing) {
                // Update existing user meta
                if ($first_name) update_user_meta($existing->ID, 'first_name', $first_name);
                if ($last_name)  update_user_meta($existing->ID, 'last_name', $last_name);
                if ($phone)      update_user_meta($existing->ID, 'billing_phone', $phone);
                update_user_meta($existing->ID, '_ttos_gdpr_consent', $consent);
                $updated++;
            } else {
                // Create new customer account
                $username = sanitize_user(strtolower(str_replace('@', '.', $email)), true);
                if (username_exists($username)) {
                    $username .= '.' . wp_generate_password(4, false, false);
                }

                $user_id = wp_insert_user(array(
                    'user_login' => $username,
                    'user_email' => $email,
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'role'       => 'customer',
                    'user_pass'  => wp_generate_password(24),
                ));

                if (is_wp_error($user_id)) {
                    $errors[] = "Row " . ((int) $index + 1) . " ({$email}): " . $user_id->get_error_message();
                    continue;
                }

                if ($phone) update_user_meta($user_id, 'billing_phone', $phone);
                update_user_meta($user_id, '_ttos_gdpr_consent', $consent);
                $created++;
            }
        }

        return array(
            'created' => $created,
            'updated' => $updated,
            'errors'  => $errors,
        );
    }

    /**
     * Upsert WooCommerce products from an array of row arrays.
     * Matches on SKU first, then name.
     * Optional columns: sku, name, description, price, category, image_url, status
     *
     * @param  array $rows  Array of associative arrays (one per product).
     * @return array        ['created' => n, 'updated' => n, 'errors' => [...]]
     */
    public static function import_menu(array $rows): array {
        if (!class_exists('WC_Product')) {
            return array('created' => 0, 'updated' => 0, 'errors' => array('WooCommerce is not active.'));
        }

        $created = 0;
        $updated = 0;
        $errors  = array();

        foreach ($rows as $index => $row) {
            $row  = array_map('trim', (array) $row);
            $name = sanitize_text_field($row['name'] ?? '');

            if ($name === '') {
                $errors[] = "Row " . ((int) $index + 1) . ": missing name — skipped.";
                continue;
            }

            $sku         = sanitize_text_field($row['sku'] ?? '');
            $description = sanitize_textarea_field($row['description'] ?? $row['short_description'] ?? '');
            $price       = wc_format_decimal($row['price'] ?? '0', 2);
            $category    = sanitize_text_field($row['category'] ?? '');
            $image_url   = esc_url_raw($row['image_url'] ?? '');
            $status      = in_array($row['status'] ?? 'publish', array('publish', 'draft'), true) ? $row['status'] : 'publish';

            // Find existing product by SKU, then by title
            $product_id = 0;
            if ($sku !== '') {
                $product_id = (int) wc_get_product_id_by_sku($sku);
            }
            if (!$product_id) {
                $found = get_posts(array(
                    'post_type'   => 'product',
                    'title'       => $name,
                    'numberposts' => 1,
                    'fields'      => 'ids',
                    'post_status' => array('publish', 'draft'),
                ));
                $product_id = !empty($found) ? (int) $found[0] : 0;
            }

            $is_new = ($product_id === 0);

            $product = $product_id ? wc_get_product($product_id) : new WC_Product_Simple();
            if (!$product) {
                $errors[] = "Row " . ((int) $index + 1) . " ({$name}): could not load product.";
                continue;
            }

            $product->set_name($name);
            $product->set_status($status);
            if ($description)  $product->set_description($description);
            if ($price !== '')  $product->set_regular_price($price);
            if ($sku !== '')    $product->set_sku($sku);

            // Assign category
            if ($category !== '') {
                $term = term_exists($category, 'product_cat');
                if (!$term) {
                    $term = wp_insert_term($category, 'product_cat');
                }
                if (!is_wp_error($term)) {
                    $term_id = (int) (is_array($term) ? $term['term_id'] : $term);
                    $product->set_category_ids(array($term_id));
                }
            }

            $saved_id = $product->save();
            if (!$saved_id || is_wp_error($saved_id)) {
                $errors[] = "Row " . ((int) $index + 1) . " ({$name}): save failed.";
                continue;
            }

            // Sideload image if provided and not already imported from same URL
            if ($image_url !== '') {
                self::maybe_sideload_image($saved_id, $image_url);
            }

            $is_new ? $created++ : $updated++;
        }

        return array(
            'created' => $created,
            'updated' => $updated,
            'errors'  => $errors,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Sideload an image into the media library and attach it to a product,
     * deduplicating by the source URL stored in _ttos_source_url post meta.
     */
    private static function maybe_sideload_image(int $product_id, string $url): void {
        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Dedup: skip if an attachment with this source URL is already set
        $existing_thumb = (int) get_post_thumbnail_id($product_id);
        if ($existing_thumb) {
            $stored_url = get_post_meta($existing_thumb, '_ttos_source_url', true);
            if ($stored_url === $url) {
                return; // already imported from same URL
            }
        }

        $attachment_id = media_sideload_image($url, $product_id, null, 'id');
        if (is_wp_error($attachment_id)) {
            return;
        }

        set_post_thumbnail($product_id, $attachment_id);
        update_post_meta($attachment_id, '_ttos_source_url', $url);
    }
}

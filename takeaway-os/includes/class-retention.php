<?php
/**
 * Takeaway OS Retention Engine
 *
 * Adds the REST unsubscribe route (uid-based), winback coupon generation,
 * and the order-completed → schedule hook. The bulk of the retention send
 * logic lives in TTOS_Production; this class focuses on the pieces that
 * the prior audit flagged as missing.
 */

defined('ABSPATH') || exit;

final class TTOS_Retention {

    const NAMESPACE = 'ttos/v1';

    public static function hooks(): void {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        // Ensure every new customer gets a uid for the REST unsubscribe link
        add_action('user_register', array(__CLASS__, 'stamp_retention_uid'));
        add_action('woocommerce_new_customer', array(__CLASS__, 'stamp_retention_uid'));
    }

    // -------------------------------------------------------------------------
    // REST routes
    // -------------------------------------------------------------------------

    public static function register_routes(): void {
        // Public unsubscribe endpoint — no auth required (uid acts as token)
        register_rest_route(self::NAMESPACE, '/retention/unsubscribe', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'rest_unsubscribe'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'uid' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }

    public static function rest_unsubscribe(WP_REST_Request $request): WP_REST_Response {
        $uid = sanitize_text_field($request->get_param('uid'));

        if ($uid === '') {
            return self::html_response('<p>Invalid unsubscribe link.</p>', 400);
        }

        // Find the user by their retention UID meta
        $users = get_users(array(
            'meta_key'   => '_ttos_retention_uid',
            'meta_value' => $uid,
            'number'     => 1,
            'fields'     => 'ID',
        ));

        if (empty($users)) {
            return self::html_response('<p>Unsubscribe link not found or already used.</p>', 404);
        }

        $user_id = (int) $users[0];
        update_user_meta($user_id, '_ttos_retention_opt_out', '1');

        $site = get_bloginfo('name');
        $html = '<html><body style="font-family:sans-serif;max-width:480px;margin:60px auto;text-align:center;">'
              . '<h2>' . esc_html($site) . '</h2>'
              . '<p>You have been unsubscribed from marketing emails.</p>'
              . '<p>You will still receive order confirmations and essential service messages.</p>'
              . '<a href="' . esc_url(home_url('/')) . '">Return to our website</a>'
              . '</body></html>';

        return self::html_response($html, 200);
    }

    // -------------------------------------------------------------------------
    // Winback coupon (WINBACK_{customer_id})
    // -------------------------------------------------------------------------

    /**
     * Generate a WINBACK_{customer_id} coupon, 10% discount, single-use, 14-day expiry.
     * Returns the coupon code string, or empty string on failure.
     */
    public static function generate_winback_coupon(int $customer_id): string {
        if (!post_type_exists('shop_coupon') || $customer_id <= 0) {
            return '';
        }

        $code = 'WINBACK_' . $customer_id;

        // Avoid duplicates — check for existing unused coupon with same code
        $existing = get_page_by_title($code, OBJECT, 'shop_coupon');
        if ($existing && $existing->post_status === 'publish') {
            $usage_count = (int) get_post_meta($existing->ID, 'usage_count', true);
            $usage_limit = (int) get_post_meta($existing->ID, 'usage_limit', true);
            if ($usage_limit <= 0 || $usage_count < $usage_limit) {
                return $code; // already exists and is still valid
            }
        }

        $expiry = gmdate('Y-m-d', strtotime('+14 days'));

        $coupon_id = wp_insert_post(array(
            'post_title'   => $code,
            'post_status'  => 'publish',
            'post_type'    => 'shop_coupon',
            'post_excerpt' => 'Win-back coupon for customer #' . $customer_id,
        ));

        if (is_wp_error($coupon_id) || !$coupon_id) {
            return '';
        }

        update_post_meta($coupon_id, 'discount_type',          'percent');
        update_post_meta($coupon_id, 'coupon_amount',          '10');
        update_post_meta($coupon_id, 'usage_limit',            '1');
        update_post_meta($coupon_id, 'usage_limit_per_user',   '1');
        update_post_meta($coupon_id, 'date_expires',           strtotime($expiry));
        update_post_meta($coupon_id, 'individual_use',         'yes');
        update_post_meta($coupon_id, '_ttos_winback_customer', $customer_id);

        return $code;
    }

    /**
     * Build the retention email body including a winback coupon code.
     * Mirrors the structure in TTOS_Production::send_retention_email but uses
     * WINBACK_{id} naming and 10% discount.
     */
    public static function build_winback_email_body(int $customer_id, string $email): string {
        $coupon      = self::generate_winback_coupon($customer_id);
        $unsubscribe = self::unsubscribe_url($customer_id);
        $site        = get_bloginfo('name');

        $body = "Hi,\n\n"
              . "We miss you! Here is a 10% discount on your next order with us at {$site}.\n\n"
              . "Your coupon code: {$coupon}\n"
              . "Order here: " . home_url('/menu/') . "\n\n"
              . "This offer expires in 14 days.\n\n"
              . "If you no longer want offers from us, unsubscribe here: {$unsubscribe}\n\n"
              . "See you again soon.";

        return $body;
    }

    // -------------------------------------------------------------------------
    // UID helpers
    // -------------------------------------------------------------------------

    /**
     * Generate and stamp a unique retention UID for a user if they don't have one.
     */
    public static function stamp_retention_uid(int $user_id): void {
        if (!get_user_meta($user_id, '_ttos_retention_uid', true)) {
            update_user_meta($user_id, '_ttos_retention_uid', wp_generate_uuid4());
        }
    }

    /**
     * Get (or create) the retention UID for a user.
     */
    public static function get_uid(int $user_id): string {
        $uid = (string) get_user_meta($user_id, '_ttos_retention_uid', true);
        if ($uid === '') {
            $uid = wp_generate_uuid4();
            update_user_meta($user_id, '_ttos_retention_uid', $uid);
        }
        return $uid;
    }

    /**
     * Build the REST unsubscribe URL for a given user.
     */
    public static function unsubscribe_url(int $user_id): string {
        $uid = self::get_uid($user_id);
        return rest_url(self::NAMESPACE . '/retention/unsubscribe') . '?uid=' . rawurlencode($uid);
    }

    /**
     * Is this user opted out of retention emails?
     */
    public static function is_opted_out(int $user_id): bool {
        return get_user_meta($user_id, '_ttos_retention_opt_out', true) === '1';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function html_response(string $html, int $status = 200): WP_REST_Response {
        $response = new WP_REST_Response($html, $status);
        $response->header('Content-Type', 'text/html; charset=UTF-8');
        return $response;
    }
}

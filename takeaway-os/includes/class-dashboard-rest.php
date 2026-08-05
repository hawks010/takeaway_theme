<?php
/**
 * Takeaway OS Dashboard REST API
 *
 * All routes in this class are admin-only (ttos_manage_settings capability).
 * Provides JSON endpoints for the owner dashboard quick-actions panel.
 */

defined('ABSPATH') || exit;

final class TTOS_Dashboard_REST {

    const NAMESPACE = 'ttos/v1';

    public static function hooks(): void {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    // -------------------------------------------------------------------------
    // Shared permission — NEVER __return_true on write routes
    // -------------------------------------------------------------------------

    public static function admin_permission(): bool {
        return current_user_can('ttos_manage_settings');
    }

    // -------------------------------------------------------------------------
    // Routes
    // -------------------------------------------------------------------------

    public static function register_routes(): void {
        // Toggle open/closed override
        register_rest_route(self::NAMESPACE, '/dashboard/toggle-open', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'toggle_open'),
            'permission_callback' => array(__CLASS__, 'admin_permission'),
            'args'                => array(
                'override' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'enum'              => array('force_open', 'force_closed', 'preorder', 'normal'),
                    'sanitize_callback' => 'sanitize_key',
                ),
            ),
        ));

        // Dashboard snapshot (read-only)
        register_rest_route(self::NAMESPACE, '/dashboard/snapshot', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'snapshot'),
            'permission_callback' => array(__CLASS__, 'admin_permission'),
        ));

        // Pause / unpause ordering
        register_rest_route(self::NAMESPACE, '/dashboard/pause', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'set_pause'),
            'permission_callback' => array(__CLASS__, 'admin_permission'),
            'args'                => array(
                'paused' => array(
                    'required' => true,
                    'type'     => 'boolean',
                ),
            ),
        ));

        // Quick settings patch
        register_rest_route(self::NAMESPACE, '/dashboard/settings', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'patch_settings'),
            'permission_callback' => array(__CLASS__, 'admin_permission'),
        ));
    }

    // -------------------------------------------------------------------------
    // Callbacks
    // -------------------------------------------------------------------------

    public static function toggle_open(WP_REST_Request $request): WP_REST_Response {
        $override = sanitize_key($request->get_param('override'));
        $allowed  = array('force_open', 'force_closed', 'preorder', 'normal');

        if (!in_array($override, $allowed, true)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid override value.'), 400);
        }

        $hours = function_exists('ttos_get_opening_hours') ? ttos_get_opening_hours() : array();
        $hours['override'] = $override;

        if (function_exists('ttos_save_opening_hours')) {
            ttos_save_opening_hours($hours);
        } else {
            update_option('ttos_opening_hours', $hours, false);
        }

        $label = array(
            'force_open'   => 'Force open',
            'force_closed' => 'Force closed',
            'preorder'     => 'Pre-order only',
            'normal'       => 'Normal schedule',
        )[$override] ?? $override;

        return new WP_REST_Response(array(
            'success'  => true,
            'override' => $override,
            'label'    => $label,
        ), 200);
    }

    public static function snapshot(WP_REST_Request $request): WP_REST_Response {
        $data = array(
            'ordering_open' => false,
            'override'      => 'normal',
            'paused'        => (bool) get_option('ttos_ordering_paused', false),
        );

        if (class_exists('TTOS_Operations')) {
            $state             = TTOS_Operations::ordering_state();
            $data['ordering_open'] = !empty($state['open']);
            $data['label']     = $state['label'] ?? '';
        }

        $hours = function_exists('ttos_get_opening_hours') ? ttos_get_opening_hours() : array();
        $data['override'] = sanitize_key($hours['override'] ?? 'normal');

        return new WP_REST_Response($data, 200);
    }

    public static function set_pause(WP_REST_Request $request): WP_REST_Response {
        $paused = (bool) $request->get_param('paused');
        update_option('ttos_ordering_paused', $paused ? '1' : '0', false);

        return new WP_REST_Response(array(
            'success' => true,
            'paused'  => $paused,
        ), 200);
    }

    public static function patch_settings(WP_REST_Request $request): WP_REST_Response {
        $body    = $request->get_json_params();
        $updated = array();

        // Only permit a safe whitelist of patchable settings
        $allowed_keys = array('trading.delivery_enabled', 'trading.collection_enabled', 'trading.min_order');

        foreach ((array) $body as $key => $value) {
            if (!in_array($key, $allowed_keys, true)) {
                continue;
            }
            [$section, $field] = explode('.', $key, 2);
            if (class_exists('TTOS_Settings')) {
                $s          = TTOS_Settings::get($section);
                $s[$field]  = sanitize_text_field((string) $value);
                update_option('ttos_settings', array_merge(TTOS_Settings::get(), array($section => $s)), false);
                $updated[] = $key;
            }
        }

        return new WP_REST_Response(array('success' => true, 'updated' => $updated), 200);
    }
}

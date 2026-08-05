<?php

defined('ABSPATH') || exit;

final class TTOS_Intake_REST {
    const NAMESPACE = 'takeaway-os/v1';

    public static function hooks(): void {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/intake/record', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'record'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE, '/intake/save-step', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'save_step'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE, '/intake/submit', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'submit'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE, '/intake/upload', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'upload'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE, '/intake/connect/(?P<provider>[a-z0-9_-]+)/start', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'start_connect'),
            'permission_callback' => '__return_true',
        ));
    }

    public static function record(WP_REST_Request $request) {
        $payload = TTOS_Client_Intake::rest_load_record(
            sanitize_text_field((string) $request->get_param('intake_id')),
            sanitize_text_field((string) $request->get_param('token'))
        );
        return self::response($payload);
    }

    public static function save_step(WP_REST_Request $request) {
        $payload = TTOS_Client_Intake::rest_save_step(
            sanitize_text_field((string) $request->get_param('intake_id')),
            sanitize_text_field((string) $request->get_param('token')),
            sanitize_key((string) $request->get_param('step_id')),
            $request->get_param('data')
        );
        return self::response($payload);
    }

    public static function submit(WP_REST_Request $request) {
        $payload = TTOS_Client_Intake::rest_submit(
            sanitize_text_field((string) $request->get_param('intake_id')),
            sanitize_text_field((string) $request->get_param('token')),
            $request->get_param('data')
        );
        return self::response($payload);
    }

    public static function upload(WP_REST_Request $request) {
        $payload = TTOS_Client_Intake::rest_upload(
            sanitize_text_field((string) $request->get_param('intake_id')),
            sanitize_text_field((string) $request->get_param('token')),
            sanitize_key((string) $request->get_param('field'))
        );
        return self::response($payload);
    }

    public static function start_connect(WP_REST_Request $request) {
        $payload = TTOS_Client_Intake::rest_start_connect(
            sanitize_text_field((string) $request->get_param('intake_id')),
            sanitize_text_field((string) $request->get_param('token')),
            sanitize_key((string) $request['provider'])
        );
        return self::response($payload);
    }

    private static function response($payload): WP_REST_Response {
        if (is_wp_error($payload)) {
            $data = $payload->get_error_data();
            $body = array(
                'code' => $payload->get_error_code(),
                'message' => $payload->get_error_message(),
            );
            if (is_array($data)) {
                $body = array_merge($data, $body);
            }
            return new WP_REST_Response($body, self::status_code($payload));
        }
        return new WP_REST_Response($payload, 200);
    }

    private static function status_code(WP_Error $error): int {
        $data = $error->get_error_data();
        if (is_array($data) && !empty($data['status'])) {
            return (int) $data['status'];
        }
        return 400;
    }
}

<?php

defined('ABSPATH') || exit;

final class TTOS_OAuth_Connectors {
    public static function provider_map(): array {
        return array(
            'stripe' => array(
                'label' => 'Stripe',
                'type' => 'payments',
                'client_id_constant' => 'TTOS_STRIPE_CONNECT_CLIENT_ID',
                'secret_constant' => 'TTOS_STRIPE_CONNECT_SECRET',
                'connect_url' => 'https://dashboard.stripe.com/register',
            ),
            'square' => array(
                'label' => 'Square',
                'type' => 'payments',
                'connect_url' => 'https://squareup.com/gb/en',
            ),
            'sumup' => array(
                'label' => 'SumUp',
                'type' => 'payments',
                'connect_url' => 'https://www.sumup.com/en-gb/',
            ),
            'paypal' => array(
                'label' => 'PayPal',
                'type' => 'payments',
                'connect_url' => 'https://www.paypal.com/uk/business/accept-payments',
            ),
            'open_banking' => array(
                'label' => 'Open Banking',
                'type' => 'payments',
                'connect_url' => 'https://openbanking.org.uk/',
            ),
            'gmail' => array(
                'label' => 'Gmail / Google Workspace',
                'type' => 'email',
                'client_id_constant' => 'TTOS_GOOGLE_OAUTH_CLIENT_ID',
                'secret_constant' => 'TTOS_GOOGLE_OAUTH_SECRET',
                'connect_url' => 'https://workspace.google.com/gmail/',
            ),
            'outlook' => array(
                'label' => 'Outlook / Microsoft 365',
                'type' => 'email',
                'client_id_constant' => 'TTOS_MICROSOFT_OAUTH_CLIENT_ID',
                'secret_constant' => 'TTOS_MICROSOFT_OAUTH_SECRET',
                'connect_url' => 'https://www.microsoft.com/en-gb/microsoft-365/outlook/email-and-calendar-software-microsoft-outlook',
            ),
            'mailchimp' => array(
                'label' => 'Mailchimp',
                'type' => 'newsletter',
                'client_id_constant' => 'TTOS_MAILCHIMP_CLIENT_ID',
                'secret_constant' => 'TTOS_MAILCHIMP_SECRET',
                'connect_url' => 'https://mailchimp.com/',
            ),
            'xero' => array(
                'label' => 'Xero',
                'type' => 'accounting',
                'connect_url' => 'https://www.xero.com/uk/',
            ),
            'quickbooks' => array(
                'label' => 'QuickBooks',
                'type' => 'accounting',
                'connect_url' => 'https://quickbooks.intuit.com/uk/',
            ),
            'freeagent' => array(
                'label' => 'FreeAgent',
                'type' => 'accounting',
                'connect_url' => 'https://www.freeagent.com/en/',
            ),
        );
    }

    public static function provider(string $provider): array {
        $provider = sanitize_key($provider);
        $map = self::provider_map();
        return $map[$provider] ?? array();
    }

    public static function available(string $provider): bool {
        $config = self::provider($provider);
        if (!$config) {
            return false;
        }
        $client_constant = (string) ($config['client_id_constant'] ?? '');
        $secret_constant = (string) ($config['secret_constant'] ?? '');
        if ($client_constant === '' && $secret_constant === '') {
            return !empty($config['connect_url']);
        }
        if ($client_constant !== '' && !defined($client_constant)) {
            return false;
        }
        if ($secret_constant !== '' && !defined($secret_constant)) {
            return false;
        }
        return true;
    }

    public static function referral_url(string $provider): string {
        $config = self::provider($provider);
        $default = (string) ($config['connect_url'] ?? '');
        $links = (array) get_option('ttos_referral_links', array());
        $configured = isset($links[$provider]) ? esc_url_raw((string) $links[$provider]) : '';
        $url = $configured !== '' ? $configured : $default;
        return (string) apply_filters('ttos_connector_referral_url', $url, $provider, $config);
    }

    public static function status_payload(): array {
        $payload = array();
        foreach (self::provider_map() as $provider => $config) {
            $available = self::available($provider);
            $payload[$provider] = array(
                'label' => (string) ($config['label'] ?? ucfirst($provider)),
                'type' => (string) ($config['type'] ?? 'general'),
                'available' => $available,
                'connect_url' => self::referral_url($provider),
                'message' => $available
                    ? __('Ready to connect.', 'takeaway-os')
                    : __('Not yet available - contact your developer.', 'takeaway-os'),
            );
        }
        return $payload;
    }

    public static function start(string $provider) {
        $config = self::provider($provider);
        if (!$config) {
            return new WP_Error('ttos_connector_missing', __('That connector is not registered in this build yet.', 'takeaway-os'));
        }
        if (!self::available($provider)) {
            return new WP_Error('ttos_connector_unavailable', __('This connector is not configured on the server yet. Contact your developer.', 'takeaway-os'));
        }

        return array(
            'provider' => sanitize_key($provider),
            'label' => (string) ($config['label'] ?? ucfirst($provider)),
            'connect_url' => esc_url_raw(self::referral_url($provider)),
            'mode' => !empty($config['client_id_constant']) ? 'oauth_scaffold' : 'provider_link',
            'message' => !empty($config['client_id_constant'])
                ? __('Connector credentials are present. Complete the provider OAuth callback wiring before using this in production.', 'takeaway-os')
                : __('This step currently opens the provider signup/connect page rather than a full OAuth callback flow.', 'takeaway-os'),
        );
    }
}

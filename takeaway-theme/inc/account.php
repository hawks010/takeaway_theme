<?php
/**
 * Branded customer account helpers.
 */

defined('ABSPATH') || exit;

add_action('template_redirect', 'ttheme_account_handle_actions');

function ttheme_account_handle_actions(): void {
    if (!is_user_logged_in() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (function_exists('is_account_page') && !is_account_page()) {
        return;
    }

    if (isset($_POST['ttheme_account_preferences_nonce'])) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ttheme_account_preferences_nonce'])), 'ttheme_account_preferences')) {
            return;
        }
        $allowed = array_keys(ttheme_account_allergen_options());
        $raw = isset($_POST['ttheme_allergens']) && is_array($_POST['ttheme_allergens']) ? wp_unslash($_POST['ttheme_allergens']) : array();
        $prefs = array_values(array_intersect($allowed, array_map('sanitize_key', $raw)));
        update_user_meta(get_current_user_id(), 'ttheme_allergen_preferences', $prefs);
        if (function_exists('wc_add_notice')) {
            wc_add_notice(__('Preference notes saved for next time.', 'takeaway-theme'), 'success');
        }
        wp_safe_redirect(function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('dashboard') : home_url('/my-account/'));
        exit;
    }

    if (isset($_POST['ttheme_account_delete_nonce'])) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ttheme_account_delete_nonce'])), 'ttheme_account_delete')) {
            return;
        }
        $user = wp_get_current_user();
        $protected_roles = array('administrator', 'shop_manager');
        if (array_intersect($protected_roles, (array) $user->roles)) {
            if (function_exists('wc_add_notice')) {
                wc_add_notice(__('Owner and manager accounts are protected. Ask another admin to handle deletion.', 'takeaway-theme'), 'error');
            }
            wp_safe_redirect(function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('dashboard') : home_url('/my-account/'));
            exit;
        }
        $typed_email = isset($_POST['ttheme_delete_email']) ? sanitize_email(wp_unslash($_POST['ttheme_delete_email'])) : '';
        if (strtolower($typed_email) !== strtolower($user->user_email)) {
            if (function_exists('wc_add_notice')) {
                wc_add_notice(__('Type your account email to confirm deletion.', 'takeaway-theme'), 'error');
            }
            wp_safe_redirect(function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('dashboard') : home_url('/my-account/'));
            exit;
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        $user_id = (int) $user->ID;
        wp_logout();
        wp_delete_user($user_id);
        wp_safe_redirect(add_query_arg('account-deleted', '1', home_url('/')));
        exit;
    }
}

function ttheme_account_allergen_options(): array {
    if (class_exists('TTOS_WooCommerce') && method_exists('TTOS_WooCommerce', 'allergen_list')) {
        return TTOS_WooCommerce::allergen_list();
    }
    return array(
        'celery' => 'Celery',
        'cereals-gluten' => 'Cereals containing gluten',
        'crustaceans' => 'Crustaceans',
        'eggs' => 'Eggs',
        'fish' => 'Fish',
        'lupin' => 'Lupin',
        'milk' => 'Milk',
        'molluscs' => 'Molluscs',
        'mustard' => 'Mustard',
        'nuts' => 'Nuts',
        'peanuts' => 'Peanuts',
        'sesame' => 'Sesame',
        'soya' => 'Soya',
        'sulphites' => 'Sulphur dioxide/sulphites',
    );
}

function ttheme_account_booking_enabled(): bool {
    $booking_enabled = (string) tt_content('contact_map', 'booking_enabled', '0') === '1';
    $booking_tab = (string) tt_content('delivery_collection', 'table_booking_enabled', '0') === '1';
    return $booking_enabled || $booking_tab;
}

function ttheme_account_booking_details(): array {
    $target = (string) tt_content('contact_map', 'booking_target', '');
    $text = (string) tt_content('contact_map', 'booking_cta_text', '');
    $note = (string) tt_content('contact_map', 'booking_note', '');
    return array(
        'url' => tt_cta_url($target),
        'label' => $text !== '' ? $text : __('Book a table', 'takeaway-theme'),
        'note' => $note !== '' ? $note : __('Table booking is enabled. Use the booking link or call the restaurant for availability.', 'takeaway-theme'),
    );
}

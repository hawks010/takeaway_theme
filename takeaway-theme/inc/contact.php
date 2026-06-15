<?php
/**
 * Header contact form — AJAX handler, form HTML, and nav filter.
 * Part of takeaway-theme; no direct access.
 */

defined('ABSPATH') || exit;

/* ── HTML builder ──────────────────────────────────────────── */

function tt_contact_form_html(): string {
    $nonce = wp_create_nonce('tt_contact_submit');
    $phone = tt_phone();
    $email = tt_email();

    ob_start();
    ?>
    <form class="tt-contact-form"
          id="tt-contact-form"
          novalidate
          data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
          data-nonce="<?php echo esc_attr($nonce); ?>">
        <?php /* Honeypot — hidden from real users, caught server-side */ ?>
        <div class="tt-cf-hp" aria-hidden="true">
            <input type="text" name="website" tabindex="-1" autocomplete="off">
        </div>

        <div class="tt-cf-fields">
            <div class="tt-cf-field">
                <label class="tt-cf-label" for="tt-cf-name">
                    <?php esc_html_e('Your name', 'takeaway-theme'); ?>
                    <span class="tt-cf-req" aria-hidden="true"> *</span>
                </label>
                <input class="tt-cf-input" type="text" id="tt-cf-name" name="name"
                       required autocomplete="name" aria-required="true"
                       placeholder="<?php esc_attr_e('e.g. Jane Smith', 'takeaway-theme'); ?>">
            </div>
            <div class="tt-cf-field">
                <label class="tt-cf-label" for="tt-cf-email">
                    <?php esc_html_e('Email address', 'takeaway-theme'); ?>
                    <span class="tt-cf-req" aria-hidden="true"> *</span>
                </label>
                <input class="tt-cf-input" type="email" id="tt-cf-email" name="email"
                       required autocomplete="email" aria-required="true"
                       placeholder="<?php esc_attr_e('you@example.com', 'takeaway-theme'); ?>">
            </div>
            <div class="tt-cf-field tt-cf-field--full">
                <label class="tt-cf-label" for="tt-cf-message">
                    <?php esc_html_e('Message', 'takeaway-theme'); ?>
                    <span class="tt-cf-req" aria-hidden="true"> *</span>
                </label>
                <textarea class="tt-cf-input tt-cf-textarea"
                          id="tt-cf-message" name="message"
                          required aria-required="true" rows="3"
                          placeholder="<?php esc_attr_e('How can we help?', 'takeaway-theme'); ?>"></textarea>
            </div>
        </div>

        <div class="tt-cf-footer">
            <button class="tt-btn tt-cf-submit" type="submit">
                <span class="tt-cf-submit-text"><?php esc_html_e('Send message', 'takeaway-theme'); ?></span>
                <svg class="tt-cf-spinner" width="16" height="16" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"
                            stroke-dasharray="31.4" stroke-dashoffset="25" opacity=".3"/>
                    <path d="M12 2a10 10 0 0 1 10 10"/>
                </svg>
            </button>
            <p class="tt-cf-msg" id="tt-cf-msg" role="status" aria-live="polite" hidden></p>
        </div>
    </form>
    <?php
    return (string) ob_get_clean();
}

/* ── AJAX handler ──────────────────────────────────────────── */

function tt_handle_contact(): void {
    if (!check_ajax_referer('tt_contact_submit', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security check failed. Please refresh the page and try again.', 'takeaway-theme')], 403);
    }

    // Honeypot — silent success so bots get no signal.
    if (!empty($_POST['website'])) {
        wp_send_json_success(['message' => __("Thanks! We'll be in touch soon.", 'takeaway-theme')]);
    }

    // Rate-limit: 1 submission per IP per 60 seconds.
    $ip  = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
    $key = 'tt_contact_rl_' . md5($ip);
    if (get_transient($key)) {
        wp_send_json_error(['message' => __('Please wait a moment before sending another message.', 'takeaway-theme')], 429);
    }

    $name    = sanitize_text_field(wp_unslash($_POST['name']    ?? ''));
    $email   = sanitize_email(wp_unslash($_POST['email']        ?? ''));
    $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

    if ('' === $name || '' === $message) {
        wp_send_json_error(['message' => __('Please fill in all required fields.', 'takeaway-theme')], 400);
    }
    if (!is_email($email)) {
        wp_send_json_error(['message' => __('Please enter a valid email address.', 'takeaway-theme')], 400);
    }

    $site    = (string) get_bloginfo('name');
    $to      = (string) get_option('admin_email');
    $subject = sprintf('[%s] Contact message from %s', $site, $name);
    $body    = sprintf("Name: %s\nEmail: %s\n\nMessage:\n%s", $name, $email, $message);
    $headers = ['Reply-To: ' . $name . ' <' . $email . '>'];

    if (wp_mail($to, $subject, $body, $headers)) {
        set_transient($key, 1, 60);
        wp_send_json_success(['message' => __("Thanks! We'll be in touch soon.", 'takeaway-theme')]);
    } else {
        wp_send_json_error(['message' => __('Something went wrong. Please try calling us instead.', 'takeaway-theme')]);
    }
}
add_action('wp_ajax_tt_contact',        'tt_handle_contact');
add_action('wp_ajax_nopriv_tt_contact', 'tt_handle_contact');

/* ── Nav filter — tag the "Contact" item so JS can target it ── */

add_filter('nav_menu_css_class', function (array $classes, WP_Post $item, stdClass $args): array {
    if (
        isset($args->theme_location) &&
        'primary' === $args->theme_location &&
        'contact' === mb_strtolower(trim($item->title), 'UTF-8')
    ) {
        $classes[] = 'tt-contact-trigger';
    }
    return $classes;
}, 10, 3);

<?php

defined('ABSPATH') || exit;

/**
 * Public banner + popup rendering (v1.3.0). Both are configured in the Site
 * Content CRM, disabled by default, schedule-aware, and dismissal state lives
 * in the browser (no per-visitor server state). Popups never appear on
 * checkout or cart unless explicitly allowed.
 */
final class TTOS_Public_UI {

    public static function hooks(): void {
        // Primary: emit banner inside <body> before page content.
        add_action('wp_body_open', array(__CLASS__, 'render_banner'), 5);
        // Fallback: if the active theme does not call wp_body_open the banner
        // would silently never appear. A second hook at wp_footer priority 8
        // (after body open has had its chance) guarantees rendering. A flag
        // prevents double output.
        add_action('wp_footer', array(__CLASS__, 'render_banner_footer_fallback'), 8);
        // Priority 5: the popup markup must be in the DOM before footer
        // scripts print at priority 20, or frontend.js finds nothing.
        add_action('wp_footer', array(__CLASS__, 'render_popup'), 5);
        // Inline popup JS: runs after the popup markup (priority 5) is output.
        add_action('wp_footer', array(__CLASS__, 'render_popup_inline_js'), 20);
    }

    /** Tracks whether the banner was already emitted via wp_body_open. */
    private static bool $banner_rendered = false;

    /* ------------------------------------------------------------------ */

    private static function config(string $section): array {
        if (!class_exists('TTOS_Site_Content')) return array();
        $config = TTOS_Site_Content::get($section);
        return is_array($config) ? $config : array();
    }

    private static function within_schedule(array $config): bool {
        $now = (string) current_time('Y-m-d\TH:i');
        if (!empty($config['start']) && $now < $config['start']) return false;
        if (!empty($config['end']) && $now > $config['end']) return false;
        return true;
    }

    /** Current page id with front-page mapping for targeting checks. */
    private static function current_page_id(): int {
        if (is_front_page()) return (int) get_option('page_on_front');
        return is_page() ? (int) get_queried_object_id() : 0;
    }

    private static function targets_current_page(array $page_ids): bool {
        $page_ids = array_map('intval', $page_ids);
        if (!$page_ids) return true;
        return in_array(self::current_page_id(), $page_ids, true);
    }

    /** Content fingerprint so changed announcements re-appear after dismissal. */
    private static function fingerprint(array $config, array $keys): string {
        $parts = array();
        foreach ($keys as $key) $parts[] = (string) ($config[$key] ?? '');
        return substr(md5(implode('|', $parts)), 0, 12);
    }

    /* ------------------------------------------------------------------ *
     * Banner
     * ------------------------------------------------------------------ */

    public static function render_banner(): void {
        if (is_admin()) return;
        $c = self::config('banner');
        if (($c['enabled'] ?? '0') !== '1') return;
        if (!self::within_schedule($c)) return;
        if (($c['pages'] ?? 'all') === 'selected' && !self::targets_current_page((array) ($c['page_ids'] ?? array()))) return;

        $title = trim((string) ($c['title'] ?? ''));
        $message = trim((string) ($c['message'] ?? ''));
        if ($title === '' && $message === '') return;

        $style = in_array($c['style'] ?? 'info', array('info', 'warning', 'offer', 'closed'), true) ? $c['style'] : 'info';
        $dismissible = ($c['dismissible'] ?? '1') === '1';
        $hash = self::fingerprint($c, array('title', 'message', 'cta_text', 'cta_url', 'style', 'start', 'end'));

        echo '<div class="ttos-banner is-' . esc_attr($style) . '" role="region" aria-label="' . esc_attr__('Site announcement', 'takeaway-os') . '" data-ttos-banner="' . esc_attr($hash) . '" data-remember="' . esc_attr(($c['remember_dismissal'] ?? '1') === '1' ? '1' : '0') . '" hidden>';
        echo '<div class="ttos-banner-inner">';
        echo '<p class="ttos-banner-text">';
        if ($title !== '') echo '<strong>' . esc_html($title) . '</strong> ';
        if ($message !== '') echo wp_kses_post($message);
        echo '</p>';
        if (!empty($c['cta_text']) && !empty($c['cta_url'])) {
            echo '<a class="ttos-banner-cta" href="' . esc_url($c['cta_url']) . '">' . esc_html($c['cta_text']) . '</a>';
        }
        if ($dismissible) {
            echo '<button type="button" class="ttos-banner-close" data-ttos-banner-close aria-label="' . esc_attr__('Dismiss announcement', 'takeaway-os') . '">×</button>';
        }
        echo '</div></div>';
    }

    /* ------------------------------------------------------------------ *
     * Popup
     * ------------------------------------------------------------------ */

    public static function render_popup(): void {
        if (is_admin()) return;
        $c = self::config('popup');
        if (($c['enabled'] ?? '0') !== '1') return;
        if (!self::within_schedule($c)) return;
        if (!self::targets_current_page((array) ($c['page_ids'] ?? array()))) return;

        // Never interrupt the purchase path unless explicitly allowed.
        if (($c['allow_on_checkout'] ?? '0') !== '1' && function_exists('is_checkout') && (is_checkout() || is_cart())) {
            return;
        }

        $title = trim((string) ($c['title'] ?? ''));
        $message = trim((string) ($c['message'] ?? ''));
        if ($title === '' && $message === '') return;

        $type = in_array($c['type'] ?? 'notice', array('offer', 'notice', 'newsletter', 'closure', 'loyalty'), true) ? $c['type'] : 'notice';
        $frequency = in_array($c['frequency'] ?? 'session', array('session', 'day', 'week'), true) ? $c['frequency'] : 'session';
        $delay = min(120, max(0, absint($c['delay'] ?? 3)));
        $hash = self::fingerprint($c, array('title', 'message', 'cta_text', 'cta_url', 'type', 'start', 'end'));
        $image = '';
        $image_id = absint($c['image_id'] ?? 0);
        if ($image_id && wp_attachment_is_image($image_id)) {
            $image = wp_get_attachment_image($image_id, 'medium_large', false, array('class' => 'ttos-popup-img', 'alt' => $title !== '' ? $title : __('Offer', 'takeaway-os')));
        }

        echo '<div class="ttos-popup is-' . esc_attr($type) . '" data-ttos-popup="' . esc_attr($hash) . '" data-delay="' . esc_attr((string) $delay) . '" data-frequency="' . esc_attr($frequency) . '" hidden>';
        echo '<div class="ttos-popup-backdrop" data-ttos-popup-close tabindex="-1"></div>';
        echo '<div class="ttos-popup-panel" role="dialog" aria-modal="true" aria-label="' . esc_attr($title !== '' ? $title : __('Announcement', 'takeaway-os')) . '">';
        if (($c['dismissible'] ?? '1') === '1') {
            echo '<button type="button" class="ttos-popup-close" data-ttos-popup-close aria-label="' . esc_attr__('Close', 'takeaway-os') . '">×</button>';
        }
        if ($image !== '') echo '<div class="ttos-popup-media">' . $image . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
        echo '<div class="ttos-popup-body">';
        if ($title !== '') echo '<h2 class="ttos-popup-title">' . esc_html($title) . '</h2>';
        if ($message !== '') echo '<div class="ttos-popup-message">' . wp_kses_post(wpautop($message)) . '</div>';
        if (!empty($c['cta_text']) && !empty($c['cta_url'])) {
            echo '<a class="ttos-order-btn ttos-popup-cta" href="' . esc_url($c['cta_url']) . '">' . esc_html($c['cta_text']) . '</a>';
        }
        echo '</div></div></div>';
    }
}

<?php
/**
 * Template helpers for Takeaway Theme v0.3.0.
 *
 * Thin accessors over the Takeaway OS Site Content CRM and Branding settings
 * with safe fallbacks, so template parts never echo raw option data and the
 * theme keeps working (neutrally) when the plugin is inactive.
 */

defined('ABSPATH') || exit;

/** Trading setting from Takeaway OS (legacy section), '' when plugin inactive. */
function tt_trading(string $key): string {
    if (!class_exists('TTOS_Settings')) return '';
    return (string) TTOS_Settings::get('trading', $key);
}

/** Site Content value with plugin-inactive safety. */
function tt_content(string $section, string $key, $fallback = '') {
    if (function_exists('ttos_get_site_content_value')) {
        $value = ttos_get_site_content_value($section, $key, $fallback);
        return ($value === '' || $value === null || $value === array()) ? $fallback : $value;
    }
    return $fallback;
}

function tt_business_name(): string {
    $name = (string) tt_content('business_info', 'business_name', '');
    if ($name === '') $name = (string) ttheme_business('restaurant_name', '');
    return $name !== '' ? $name : get_bloginfo('name');
}

function tt_phone(): string {
    $phone = (string) tt_content('business_info', 'phone', '');
    return $phone !== '' ? $phone : (string) ttheme_business('phone', '');
}

function tt_email(): string {
    $email = (string) tt_content('business_info', 'email', '');
    return $email !== '' ? $email : (string) ttheme_business('email', '');
}

/** Address lines (non-empty only). */
function tt_address_lines(): array {
    $keys = array('address_1', 'address_2', 'town', 'county', 'postcode');
    $lines = array();
    foreach ($keys as $key) {
        $value = (string) tt_content('business_info', $key, '');
        if ($value === '') $value = (string) ttheme_business($key, '');
        if ($value !== '') $lines[] = $value;
    }
    return $lines;
}

function tt_menu_url(): string {
    return ttheme_page_url('menu', '/menu/');
}

/** CTA target: pass through tel:/mailto:/wa.me/paths, default to menu. */
function tt_cta_url(string $target): string {
    $target = trim($target);
    if ($target === '') return tt_menu_url();
    if (preg_match('#^(tel:|mailto:|https?://|/|\#)#i', $target)) return $target;
    return tt_menu_url();
}

/**
 * Open/closed state from the structured Site Content hours.
 * Returns ['state' => 'open'|'closed'|'unknown', 'label' => string].
 */
function tt_open_status(): array {
    if (!function_exists('ttos_get_opening_hours')) {
        return array('state' => 'unknown', 'label' => '');
    }
    $hours = ttos_get_opening_hours();
    if (($hours['temporary_closure'] ?? '0') === '1') {
        $message = (string) ($hours['temporary_closure_message'] ?? '');
        return array('state' => 'closed', 'label' => $message !== '' ? $message : __('Temporarily closed', 'takeaway-theme'));
    }
    $override = $hours['override'] ?? 'normal';
    if ($override === 'force_open') return array('state' => 'open', 'label' => __('Open now', 'takeaway-theme'));
    if ($override === 'force_closed') return array('state' => 'closed', 'label' => __('Closed today', 'takeaway-theme'));

    $day = strtolower((string) current_time('l'));
    $today = $hours['days'][$day] ?? array();
    if (($today['closed'] ?? '0') === '1') {
        return array('state' => 'closed', 'label' => __('Closed today', 'takeaway-theme'));
    }
    $open = (string) ($today['open'] ?? '');
    $close = (string) ($today['close'] ?? '');
    if ($open === '' || $close === '') {
        return array('state' => 'unknown', 'label' => '');
    }
    $now = (string) current_time('H:i');
    $is_open = ($close > $open) ? ($now >= $open && $now < $close) : ($now >= $open || $now < $close);
    if ($is_open) {
        return array('state' => 'open', 'label' => sprintf(__('Open until %s', 'takeaway-theme'), $close));
    }
    if ($now < $open) {
        return array('state' => 'closed', 'label' => sprintf(__('Opens at %s', 'takeaway-theme'), $open));
    }
    return array('state' => 'closed', 'label' => __('Closed', 'takeaway-theme'));
}

/** Open-status pill markup, empty string when state unknown. */
function tt_open_status_pill(): string {
    $status = tt_open_status();
    if ($status['state'] === 'unknown' || $status['label'] === '') return '';
    return '<span class="tt-open-pill is-' . esc_attr($status['state']) . '">' . esc_html($status['label']) . '</span>';
}

/**
 * Weekly hours grouped into rows of consecutive days sharing the same times,
 * e.g. [['Tue – Sun', '17:00 – 22:00'], ['Mon', 'Closed']].
 */
function tt_hours_summary(): array {
    if (!function_exists('ttos_get_opening_hours')) return array();
    $hours = ttos_get_opening_hours();
    $days = array(
        'monday' => __('Mon', 'takeaway-theme'), 'tuesday' => __('Tue', 'takeaway-theme'), 'wednesday' => __('Wed', 'takeaway-theme'),
        'thursday' => __('Thu', 'takeaway-theme'), 'friday' => __('Fri', 'takeaway-theme'), 'saturday' => __('Sat', 'takeaway-theme'), 'sunday' => __('Sun', 'takeaway-theme'),
    );
    $rows = array();
    $configured = false;
    foreach ($days as $key => $label) {
        $day = $hours['days'][$key] ?? array();
        if (($day['closed'] ?? '0') === '1') {
            $value = __('Closed', 'takeaway-theme');
            $configured = true;
        } elseif (!empty($day['open']) && !empty($day['close'])) {
            $value = $day['open'] . ' – ' . $day['close'];
            $configured = true;
        } else {
            $value = '';
        }
        $rows[] = array('label' => $label, 'value' => $value);
    }
    if (!$configured) return array();

    $grouped = array();
    foreach ($rows as $row) {
        $last = count($grouped) - 1;
        if ($last >= 0 && $grouped[$last]['value'] === $row['value']) {
            $grouped[$last]['to'] = $row['label'];
        } else {
            $grouped[] = array('from' => $row['label'], 'to' => '', 'value' => $row['value']);
        }
    }
    $out = array();
    foreach ($grouped as $group) {
        $label = $group['to'] !== '' ? $group['from'] . ' – ' . $group['to'] : $group['from'];
        $out[] = array('label' => $label, 'value' => $group['value'] !== '' ? $group['value'] : __('Call for hours', 'takeaway-theme'));
    }
    return $out;
}

/** Attachment image with alt fallback; empty string when missing. */
function tt_image(int $attachment_id, string $size = 'large', string $class = '', string $alt_fallback = ''): string {
    if (!$attachment_id || get_post_type($attachment_id) !== 'attachment') return '';
    $alt = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
    return wp_get_attachment_image($attachment_id, $size, false, array(
        'class' => $class,
        'alt'   => $alt !== '' ? $alt : $alt_fallback,
        'loading' => 'lazy',
    ));
}

/** Accessible star rating (no colour-only meaning). */
function tt_stars(string $rating): string {
    if ($rating === '' || !is_numeric($rating)) return '';
    $value = max(0, min(5, (float) $rating));
    $full = (int) floor($value);
    $half = ($value - $full) >= 0.5;
    $out = '<span class="tt-stars" role="img" aria-label="' . esc_attr(sprintf(__('Rated %s out of 5', 'takeaway-theme'), $rating)) . '">';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $full) $out .= '<span class="tt-star is-full" aria-hidden="true">★</span>';
        elseif ($i === $full + 1 && $half) $out .= '<span class="tt-star is-half" aria-hidden="true">★</span>';
        else $out .= '<span class="tt-star" aria-hidden="true">☆</span>';
    }
    return $out . '</span>';
}

/** Basket count badge (echoed; replaced live via Woo cart fragments). */
function tt_cart_count_badge(): void {
    $count = (function_exists('WC') && WC()->cart) ? (int) WC()->cart->get_cart_contents_count() : 0;
    echo '<span class="tt-cart-count" data-count="' . esc_attr((string) $count) . '">' . esc_html((string) $count) . '</span>';
}

/** Offer rows that are enabled and inside their schedule window. */
function tt_active_offers(): array {
    $items = tt_content('offers', 'items', array());
    if (!is_array($items)) return array();
    $now = (string) current_time('Y-m-d\TH:i');
    $active = array();
    foreach ($items as $offer) {
        if (!is_array($offer) || ($offer['enabled'] ?? '0') !== '1') continue;
        if (!empty($offer['start']) && $now < $offer['start']) continue;
        if (!empty($offer['end']) && $now > $offer['end']) continue;
        $active[] = $offer;
    }
    return $active;
}

/** Social links with values only. */
function tt_social_links(): array {
    $labels = array(
        'instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp',
        'google' => 'Google', 'tripadvisor' => 'TripAdvisor', 'twitter' => 'X', 'youtube' => 'YouTube',
    );
    $out = array();
    foreach ($labels as $key => $label) {
        $url = (string) tt_content('social_links', $key, '');
        if ($url !== '') $out[$label] = $url;
    }
    return $out;
}

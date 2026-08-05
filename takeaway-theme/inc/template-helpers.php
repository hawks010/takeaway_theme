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
    if (class_exists('TTOS_Operations') && method_exists('TTOS_Operations', 'ordering_state')) {
        $state = TTOS_Operations::ordering_state();
        $mode = !empty($state['open']) ? 'open' : (!empty($state['preorder_enabled']) ? 'preorder' : 'closed');
        return array(
            'state' => $mode,
            'label' => (string) ($state['label'] ?? ''),
        );
    }
    if (!function_exists('ttos_get_opening_hours')) {
        return array('state' => 'unknown', 'label' => '');
    }
    $hours = ttos_get_opening_hours();
    if (($hours['temporary_closure'] ?? '0') === '1') {
        $message = (string) ($hours['temporary_closure_message'] ?? '');
        return array('state' => 'closed', 'label' => $message !== '' ? $message : __('Temporarily closed', 'takeaway-theme'));
    }
    $override = $hours['override'] ?? 'normal';
    if ($override === 'force_open')  return array('state' => 'open',     'label' => __('Open now', 'takeaway-theme'));
    if ($override === 'force_closed') return array('state' => 'closed',  'label' => __('Closed today', 'takeaway-theme'));
    if ($override === 'preorder')   return array('state' => 'preorder', 'label' => __('Pre-order open', 'takeaway-theme'));

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
    if (!$attachment_id || get_post_type($attachment_id) !== 'attachment') {
        return tt_food_placeholder_from_context($class, $alt_fallback);
    }
    $alt = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
    $attrs = array(
        'class' => $class,
        'alt'   => $alt !== '' ? $alt : $alt_fallback,
        'loading' => 'lazy',
    );
    if (strpos($class, 'tt-home-hero-img') !== false) {
        $attrs['loading'] = 'eager';
        $attrs['fetchpriority'] = 'high';
        $attrs['decoding'] = 'async';
        $attrs['sizes'] = '(min-width: 901px) 48vw, 92vw';
    }
    return wp_get_attachment_image($attachment_id, $size, false, $attrs);
}

/** Theme-owned media contexts that should render a food placeholder when empty. */
function tt_food_placeholder_from_context(string $class = '', string $label = ''): string {
    $contexts = array(
        'tt-home-hero-img',
        'tt-featured-img',
        'tt-offer-img',
        'tt-food-media',
    );

    foreach ($contexts as $context) {
        if (strpos($class, $context) !== false) {
            return tt_food_placeholder($label, trim($class . ' tt-food-placeholder-media'));
        }
    }

    return '';
}

/** Keyword map for category-aware fallback food illustrations. */
function tt_food_placeholder_keyword_map(): array {
    return array(
        'burger' => array('burger', 'cheeseburger', 'beef burger', 'chicken burger', 'stacker'),
        'pizza' => array('pizza', 'margherita', 'pepperoni', 'calzone', 'garlic bread'),
        'noodles' => array('noodle', 'ramen', 'udon', 'pad thai', 'chow mein', 'lo mein'),
        'curry-bowl' => array('curry', 'korma', 'masala', 'jalfrezi', 'balti', 'biryani', 'rice bowl', 'curry bowl'),
        'kebab-wrap' => array('kebab', 'doner', 'donner', 'shawarma', 'gyro', 'wrap', 'pitta', 'falafel wrap'),
        'fish-and-chips' => array('fish', 'chips', 'cod', 'haddock', 'scampi'),
        'chicken-box' => array('chicken', 'wings', 'strips', 'bucket', 'box meal', 'nugget', 'popcorn chicken'),
        'sushi' => array('sushi', 'maki', 'nigiri', 'sashimi', 'temaki'),
        'dessert' => array('dessert', 'cake', 'brownie', 'cookie', 'ice cream', 'gelato', 'churro', 'donut', 'cheesecake'),
        'drink' => array('drink', 'cola', 'coke', 'sprite', 'fanta', 'water', 'juice', 'milkshake', 'coffee', 'tea'),
        'cloche' => array(),
    );
}

/** Match menu text to the most appropriate placeholder category. */
function tt_food_placeholder_category(string $text = ''): string {
    $haystack = strtolower(trim(wp_strip_all_tags(html_entity_decode($text, ENT_QUOTES, 'UTF-8'))));
    if ($haystack === '') {
        return 'cloche';
    }

    foreach (tt_food_placeholder_keyword_map() as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($haystack, $keyword) !== false) {
                return $category;
            }
        }
    }

    return 'cloche';
}

/** Human-readable labels for placeholder chips. */
function tt_food_placeholder_label(string $category): string {
    $labels = array(
        'burger' => __('Burger', 'takeaway-theme'),
        'pizza' => __('Pizza', 'takeaway-theme'),
        'noodles' => __('Noodles', 'takeaway-theme'),
        'curry-bowl' => __('Curry bowl', 'takeaway-theme'),
        'kebab-wrap' => __('Kebab wrap', 'takeaway-theme'),
        'fish-and-chips' => __('Fish and chips', 'takeaway-theme'),
        'chicken-box' => __('Chicken box', 'takeaway-theme'),
        'sushi' => __('Sushi', 'takeaway-theme'),
        'dessert' => __('Dessert', 'takeaway-theme'),
        'drink' => __('Drink', 'takeaway-theme'),
        'cloche' => __('Freshly prepared', 'takeaway-theme'),
    );

    return $labels[$category] ?? $labels['cloche'];
}

/** Inline SVG illustration for food placeholders. */
function tt_food_placeholder_svg(string $category): string {
    switch ($category) {
        case 'burger':
            return '<svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M24 50c2-13 16-22 36-22s34 9 36 22"/><path d="M25 59h70"/><path d="M30 67h60"/><path d="M26 79h68c0 8-6 13-14 13H40c-8 0-14-5-14-13Z"/><path d="M38 50h2"/><path d="M54 46h2"/><path d="M70 50h2"/><path d="M82 46h2"/></svg>';
        case 'pizza':
            return '<svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M27 29c20-6 46-6 66 0L60 92 27 29Z"/><path d="M38 44c13-3 31-3 44 0"/><circle cx="51" cy="52" r="4"/><circle cx="68" cy="60" r="4"/><circle cx="58" cy="69" r="4"/></svg>';
        case 'noodles':
            return '<svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M42 25l16 18"/><path d="M76 24 63 43"/><path d="M30 63h60"/><path d="M37 48c0 8 4 12 8 12s8-4 8-12"/><path d="M57 48c0 8 4 12 8 12s8-4 8-12"/><path d="M77 48c0 8 4 12 8 12"/><path d="M38 64c3 18 11 27 22 27s19-9 22-27"/></svg>';
        case 'curry-bowl':
            return '<svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M41 30c0-5 4-9 9-9"/><path d="M60 26c0-6 4-10 10-10"/><path d="M76 32c0-4 3-8 8-8"/><path d="M26 62h68c-2 18-16 31-34 31S28 80 26 62Z"/><path d="M22 62h76"/><path d="M34 54c6 3 11 4 16 4s10-1 16-4 11-4 16-4"/></svg>';
        case 'kebab-wrap':
            return '<svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M43 26c22 10 38 27 41 52"/><path d="M41 28c-4 24 4 47 19 66"/><path d="M34 39c11 2 23 9 33 20"/><path d="M29 54c10 1 22 7 32 16"/><path d="M52 96c18-2 29-10 35-25"/></svg>';
        case 'fish-and-chips':
            return '<svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M26 55c12-13 28-18 40-12 3 1 6 1 9 0l11-5-2 11c-1 3-1 6 1 9 7 12 2 28-11 40-12-5-23-13-31-24-5-7-11-13-17-19Z"/><circle cx="68" cy="53" r="1.5"/><path d="M26 86h28"/><path d="M33 86 29 65"/><path d="M40 86 36 63"/><path d="M47 86 44 68"/></svg>';
        case 'chicken-box':
            return '<svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M30 44h60l-6 42H36l-6-42Z"/><path d="M39 44V33h42v11"/><path d="M66 61c2-7 8-11 14-9 5 2 7 8 4 13-3 5-8 7-14 5"/><path d="M48 62c-5-3-7-9-5-14 3-5 9-7 14-4 5 3 7 9 5 14-2 5-8 7-14 4Z"/></svg>';
        case 'sushi':
            return '<svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="25" y="48" width="28" height="28" rx="9"/><rect x="67" y="48" width="28" height="28" rx="9"/><path d="M33 48c2-10 8-15 14-15s12 5 14 15"/><path d="M75 48c2-10 8-15 14-15s12 5 14 15"/><path d="M37 62h4"/><path d="M79 62h4"/></svg>';
        case 'dessert':
            return '<svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M40 78h40"/><path d="M46 78 52 50h16l6 28"/><path d="M45 50c0-10 7-17 15-17s15 7 15 17"/><path d="M60 23v10"/><path d="M54 28c0-4 3-7 6-7"/><path d="M66 28c0-4-3-7-6-7"/></svg>';
        case 'drink':
            return '<svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M46 35h28l-4 50H50l-4-50Z"/><path d="M55 35 74 20"/><path d="M54 50h12"/><path d="M53 62h14"/><path d="M52 74h16"/></svg>';
        case 'cloche':
        default:
            return '<svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M24 72h72"/><path d="M32 72c0-16 13-29 28-29s28 13 28 29"/><path d="M58 39h4"/><path d="M40 81h40"/></svg>';
    }
}

/** Render a category-aware inline SVG placeholder for missing food imagery. */
function tt_food_placeholder(string $text = '', string $class = '', string $aria_label = ''): string {
    $category = tt_food_placeholder_category($text);
    $label = tt_food_placeholder_label($category);
    $accessible_label = $aria_label !== '' ? $aria_label : sprintf(__('%s illustration', 'takeaway-theme'), $label);
    $classes = trim('tt-food-placeholder ' . $class);

    return '<div class="' . esc_attr($classes) . '" role="img" aria-label="' . esc_attr($accessible_label) . '" data-food-category="' . esc_attr($category) . '">'
        . '<span class="tt-food-placeholder-art" aria-hidden="true">' . tt_food_placeholder_svg($category) . '</span>'
        . '<span class="tt-food-placeholder-label">' . esc_html($label) . '</span>'
        . '</div>';
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

/**
 * Basket preview panel inner HTML — used in header template and as a WC fragment.
 * When the cart has items: line items + subtotal + View Basket / Checkout CTAs.
 * When empty: empty state + Browse Menu CTA.
 */
function tt_cart_preview_html(): string {
    $cart_url     = function_exists('wc_get_cart_url')     ? wc_get_cart_url()     : '';
    $checkout_url = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '';
    $menu_url     = tt_menu_url();
    $has_wc       = function_exists('WC') && WC()->cart;
    $count        = $has_wc ? (int) WC()->cart->get_cart_contents_count() : 0;

    ob_start();

    if (!$has_wc || $count === 0) {
        // Empty state.
        echo '<div class="tt-cpreview-empty">';
        echo '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>';
        echo '<p>' . esc_html__('Your basket is empty', 'takeaway-theme') . '</p>';
        echo '<a class="tt-btn" href="' . esc_url($menu_url) . '">' . esc_html__('Browse menu', 'takeaway-theme') . '</a>';
        echo '</div>';
    } else {
        // Line items.
        echo '<ul class="tt-cpreview-items" aria-label="' . esc_attr__('Basket items', 'takeaway-theme') . '">';
        foreach (WC()->cart->get_cart() as $item) {
            $product = $item['data'] ?? null;
            if (!$product) continue;
            $name  = method_exists($product, 'get_name') ? (string) $product->get_name() : '';
            $qty   = (int) ($item['quantity'] ?? 1);
            $total = function_exists('wc_price') ? wc_price((float) ($item['line_total'] ?? 0)) : '';
            echo '<li class="tt-cpreview-item">';
            echo '<span class="tt-cpreview-qty" aria-hidden="true">' . esc_html((string) $qty) . '×</span>';
            echo '<span class="tt-cpreview-name">' . esc_html($name) . '</span>';
            if ($total !== '') {
                echo '<span class="tt-cpreview-price">' . wp_kses_post($total) . '</span>';
            }
            echo '</li>';
        }
        echo '</ul>';

        // Subtotal.
        if (function_exists('wc_price')) {
            $subtotal = (float) WC()->cart->get_subtotal();
            echo '<div class="tt-cpreview-subtotal">';
            echo '<span>' . esc_html__('Subtotal', 'takeaway-theme') . '</span>';
            echo '<strong>' . wp_kses_post(wc_price($subtotal)) . '</strong>';
            echo '</div>';
        }

        // CTAs.
        echo '<div class="tt-cart-btns">';
        if ($cart_url !== '') {
            echo '<a class="tt-btn ghost" href="' . esc_url($cart_url) . '">' . esc_html__('View basket', 'takeaway-theme') . '</a>';
        }
        if ($checkout_url !== '') {
            echo '<a class="tt-btn" href="' . esc_url($checkout_url) . '">' . esc_html__('Checkout', 'takeaway-theme') . '</a>';
        }
        echo '</div>';
    }

    return ob_get_clean();
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

/** SVG icon path for a social platform key. Returns empty string for unknown keys. */
function tt_social_svg(string $platform): string {
    $paths = array(
        'instagram'   => 'M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4c0 3.2-2.6 5.8-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8C2 4.6 4.6 2 7.8 2zm-.2 2C5.6 4 4 5.6 4 7.6v8.8C4 18.4 5.6 20 7.6 20h8.8c2 0 3.6-1.6 3.6-3.6V7.6C20 5.6 18.4 4 16.4 4H7.6zm9.65 1.5a1.25 1.25 0 110 2.5 1.25 1.25 0 010-2.5zM12 7a5 5 0 110 10A5 5 0 0112 7zm0 2a3 3 0 100 6 3 3 0 000-6z',
        'facebook'    => 'M12 2.04C6.5 2.04 2 6.53 2 12.06c0 5 3.66 9.15 8.44 9.9v-7h-2.54v-2.9h2.54V9.85c0-2.51 1.49-3.89 3.78-3.89 1.09 0 2.23.19 2.23.19v2.47h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.45 2.9h-2.33v7a10 10 0 008.44-9.9C22 6.53 17.5 2.04 12 2.04z',
        'tiktok'      => 'M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.27 6.27 0 00-.79-.05A6.34 6.34 0 003.15 15.3a6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.26 8.26 0 004.84 1.55V6.79a4.85 4.85 0 01-1.07-.1z',
        'twitter'     => 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.742l7.732-8.852L1.256 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z',
        'youtube'     => 'M21.543 6.498C22 8.28 22 12 22 12s0 3.72-.457 5.502c-.254.985-.997 1.76-1.938 2.022C17.896 20 12 20 12 20s-5.893 0-7.605-.476c-.945-.266-1.687-1.04-1.938-2.022C2 15.72 2 12 2 12s0-3.72.457-5.502c.254-.985.997-1.76 1.938-2.022C6.107 4 12 4 12 4s5.896 0 7.605.476c.945.266 1.687 1.04 1.938 2.022zM10 15.5l6-3.5-6-3.5v7z',
        'whatsapp'    => 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z',
        'google'      => 'M21.35 11.1h-9.17v2.73h6.51c-.33 3.81-3.5 5.44-6.5 5.44C8.36 19.27 5 16.25 5 12c0-4.1 3.2-7.27 7.2-7.27 3.09 0 4.9 1.97 4.9 1.97L19 4.72S16.56 2 12.1 2C6.42 2 2.03 6.8 2.03 12c0 5.05 4.13 10 10.22 10 5.35 0 9.25-3.67 9.25-9.09 0-1.15-.15-1.81-.15-1.81z',
        'tripadvisor' => 'M12 2a10 10 0 100 20A10 10 0 0012 2zm-3 7a4 4 0 110 8A4 4 0 019 9zm6 0a4 4 0 110 8 4 4 0 010-8zM9 11a2 2 0 110 4 2 2 0 010-4zm6 0a2 2 0 110 4 2 2 0 010-4z',
    );
    if (!isset($paths[$platform])) return '';
    return '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="' . esc_attr($paths[$platform]) . '"/></svg>';
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

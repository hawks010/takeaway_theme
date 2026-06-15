<?php

defined('ABSPATH') || exit;

final class TTOS_Shortcodes {
    public static function hooks(): void {
        add_shortcode('takeaway_menu', array(__CLASS__, 'menu'));
        add_shortcode('takeaway_customer_portal', array(__CLASS__, 'customer_portal'));
        add_shortcode('takeaway_order_tracker', array(__CLASS__, 'order_tracker'));
        add_shortcode('takeaway_delivery_checker', array(__CLASS__, 'delivery_checker'));
        add_shortcode('takeaway_allergens', array(__CLASS__, 'allergens'));
        add_shortcode('takeaway_contact', array(__CLASS__, 'contact'));
        add_shortcode('takeaway_policy', array(__CLASS__, 'policy'));
        add_shortcode('takeaway_kitchen_screen', array(__CLASS__, 'kitchen_screen'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function assets(): void {
        // frontend.js and frontend.css must load on every public page because
        // the banner and popup are injected via wp_body_open / wp_footer hooks
        // (TTOS_Public_UI), which fire regardless of page content or shortcodes.
        // Making this conditional on shortcode presence would break banner/popup
        // on pages that contain neither shortcode.
        wp_enqueue_style('ttos-frontend', TTOS_URL . 'assets/frontend.css', array(), TTOS_VERSION);
        wp_enqueue_script('ttos-frontend', TTOS_URL . 'assets/frontend.js', array(), TTOS_VERSION, true);
    }

    /** Site Content reader with plugin-internal safety. */
    private static function sc(string $section, string $key, $fallback = '') {
        if (!class_exists('TTOS_Site_Content')) return $fallback;
        $value = TTOS_Site_Content::get($section, $key, $fallback);
        return ($value === '' || $value === null || $value === array()) ? $fallback : $value;
    }

    /* ------------------------------------------------------------------ *
     * Menu / ordering experience
     * ------------------------------------------------------------------ */

    public static function menu(): string {
        if (!TTOS_WooCommerce::active()) {
            return '<div class="ttos-front-card">WooCommerce is required before the menu can be displayed.</div>';
        }
        $show_search    = self::sc('menu_page', 'show_search', '1') === '1';
        $show_dietary   = self::sc('menu_page', 'show_dietary_filters', '1') === '1';
        $show_allergens = self::sc('menu_page', 'show_allergen_filters', '1') === '1' && taxonomy_exists('ttos_allergen') && TTOS_Settings::module_enabled('allergen_filters');
        $show_sticky    = self::sc('menu_page', 'show_sticky_basket', '1') === '1';
        $empty_mode     = (string) self::sc('menu_page', 'empty_categories', 'hide');
        $is_manager     = current_user_can('ttos_manage_menu');

        // Categories, excluding "Uncategorized" from public output, with the
        // product query prepared up-front so empty ones can be hidden.
        $cats = array();
        foreach (TTOS_WooCommerce::get_product_categories() as $cat) {
            if ($cat->slug === 'uncategorized') continue;
            $query = new WP_Query(array(
                'post_type' => 'product', 'posts_per_page' => -1, 'post_status' => 'publish',
                'orderby' => 'menu_order title', 'order' => 'ASC',
                'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cat->term_id)),
            ));
            $has = $query->have_posts();
            if (!$has && !($empty_mode === 'admin_helper' && $is_manager)) {
                continue;
            }
            $cats[] = array('term' => $cat, 'query' => $query, 'empty' => !$has);
        }

        ob_start();
        echo '<div class="ttos-menu-wrap">';

        // Tools: search + filters.
        if ($show_search || $show_dietary || $show_allergens) {
            echo '<div class="ttos-menu-toolbar">';
            if ($show_search) {
                echo '<label class="ttos-filter-search-wrap"><span class="screen-reader-text">' . esc_html__('Search menu', 'takeaway-os') . '</span>';
                echo '<input type="search" class="ttos-filter-search" placeholder="' . esc_attr__('Search the menu…', 'takeaway-os') . '"></label>';
            }
            if ($show_dietary && taxonomy_exists('ttos_dietary')) {
                $diet_terms = get_terms(array('taxonomy' => 'ttos_dietary', 'hide_empty' => true));
                if (!is_wp_error($diet_terms) && $diet_terms) {
                    echo '<div class="ttos-filter-diets" role="group" aria-label="' . esc_attr__('Dietary filters', 'takeaway-os') . '">';
                    foreach ($diet_terms as $term) {
                        echo '<button type="button" class="ttos-filter-diet" data-diet="' . esc_attr($term->slug) . '" aria-pressed="false">' . esc_html($term->name) . '</button>';
                    }
                    echo '</div>';
                }
            }
            if ($show_allergens) {
                $terms = get_terms(array('taxonomy' => 'ttos_allergen', 'hide_empty' => false));
                if (!is_wp_error($terms) && $terms) {
                    echo '<details class="ttos-filter-allergens"><summary>' . esc_html__('Hide allergens', 'takeaway-os') . '</summary><div class="ttos-filter-allergens-list" role="group" aria-label="' . esc_attr__('Filter by allergens', 'takeaway-os') . '">';
                    foreach ($terms as $term) {
                        echo '<label><input type="checkbox" class="ttos-filter-allergen" value="' . esc_attr($term->slug) . '"> ' . esc_html($term->name) . '</label>';
                    }
                    echo '</div></details>';
                }
            }
            echo '</div>';
        }

        // Sticky category navigation.
        if (count($cats) > 1) {
            echo '<nav class="ttos-cat-nav" aria-label="' . esc_attr__('Menu categories', 'takeaway-os') . '"><div class="ttos-cat-nav-row">';
            foreach ($cats as $entry) {
                echo '<a href="#ttos-cat-' . esc_attr($entry['term']->term_id) . '">' . esc_html($entry['term']->name) . '</a>';
            }
            echo '</div></nav>';
        }

        if (!$cats) {
            // No public categories with products: keep the page honest but never leak admin copy.
            if ($is_manager) {
                echo '<div class="ttos-admin-note">' . esc_html__('No menu items are live yet. Add products in Takeaway OS → Menu Builder or create the starter menu in Launchpad. Customers see a friendly message instead of this note.', 'takeaway-os') . '</div>';
            } else {
                echo '<div class="ttos-front-card">' . esc_html__('Our online menu is being updated. Please call us to order.', 'takeaway-os') . '</div>';
            }
        }

        foreach ($cats as $entry) {
            self::category_section($entry['term'], $entry['query'], $entry['empty'], $is_manager);
        }

        if ($show_sticky) self::sticky_cart_bar();
        echo '</div>';
        return ob_get_clean();
    }

    private static function category_section($cat, WP_Query $query, bool $is_empty, bool $is_manager): void {
        echo '<section id="ttos-cat-' . esc_attr($cat->term_id) . '" class="ttos-menu-section">';
        echo '<h2 class="ttos-menu-section-title">' . esc_html($cat->name) . '</h2>';
        if ($cat->description) {
            echo '<p class="ttos-menu-section-desc">' . esc_html($cat->description) . '</p>';
        }
        if ($is_empty) {
            if ($is_manager) {
                echo '<div class="ttos-admin-note">' . esc_html__('Empty category — hidden from customers. Add products or remove the category. (Only staff can see this note.)', 'takeaway-os') . '</div>';
            }
            echo '</section>';
            return;
        }
        echo '<div class="ttos-product-grid">';
        while ($query->have_posts()) {
            $query->the_post();
            self::product_card(get_the_ID());
        }
        wp_reset_postdata();
        echo '</div></section>';
    }

    private static function product_card(int $id): void {
        $price = get_post_meta($id, '_price', true);
        $stock = get_post_meta($id, '_stock_status', true);
        $show_popular = self::sc('menu_page', 'show_popular_badges', '1') === '1';

        $allergens = get_post_meta($id, '_ttos_allergens', true);
        $allergen_slugs = array();
        if (taxonomy_exists('ttos_allergen')) {
            $names = wp_get_object_terms($id, 'ttos_allergen', array('fields' => 'names'));
            $slugs = wp_get_object_terms($id, 'ttos_allergen', array('fields' => 'slugs'));
            if (!is_wp_error($names) && $names) $allergens = implode(', ', $names);
            if (!is_wp_error($slugs) && $slugs) $allergen_slugs = $slugs;
        }
        $diet_slugs = array();
        $diet_names = array();
        if (taxonomy_exists('ttos_dietary')) {
            $slugs = wp_get_object_terms($id, 'ttos_dietary', array('fields' => 'slugs'));
            $names = wp_get_object_terms($id, 'ttos_dietary', array('fields' => 'names'));
            if (!is_wp_error($slugs) && $slugs) $diet_slugs = $slugs;
            if (!is_wp_error($names) && $names) $diet_names = $names;
        }
        $badges_meta = (string) get_post_meta($id, '_ttos_badges', true);

        echo '<article class="ttos-product' . ($stock === 'outofstock' ? ' is-sold' : '') . '" data-title="' . esc_attr(strtolower(get_the_title() . ' ' . wp_strip_all_tags(get_the_excerpt() ?: get_the_content()))) . '" data-allergens="' . esc_attr(implode(',', $allergen_slugs)) . '" data-diets="' . esc_attr(implode(',', $diet_slugs)) . '">';

        $thumb = get_the_post_thumbnail($id, 'medium_large', array('class' => 'ttos-product-img', 'loading' => 'lazy', 'alt' => get_the_title()));
        if ($thumb) {
            echo '<div class="ttos-product-media">' . $thumb . '</div>';
        } else {
            $legacy = (string) get_post_meta($id, '_ttos_image_url', true);
            if ($legacy !== '') {
                echo '<div class="ttos-product-media"><img class="ttos-product-img" loading="lazy" src="' . esc_url($legacy) . '" alt="' . esc_attr(get_the_title()) . '"></div>';
            } else {
                echo '<div class="ttos-product-media ttos-product-media-empty" aria-hidden="true"><span></span></div>';
            }
        }

        echo '<div class="ttos-product-body">';
        echo '<div class="ttos-product-title"><h3>' . esc_html(get_the_title()) . '</h3><strong class="ttos-product-price">' . (function_exists('wc_price') ? wp_kses_post(wc_price((float) $price)) : '£' . esc_html($price)) . '</strong></div>';

        $chips = array();
        foreach ($diet_names as $i => $name) {
            $slug = $diet_slugs[$i] ?? sanitize_title($name);
            if ($slug === 'popular' && !$show_popular) continue;
            $chips[] = '<span class="ttos-chip ttos-chip-' . esc_attr($slug) . '">' . esc_html($name) . '</span>';
        }
        if ($badges_meta !== '') $chips[] = '<span class="ttos-chip">' . esc_html($badges_meta) . '</span>';
        if ($chips) echo '<p class="ttos-product-chips">' . implode('', $chips) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.

        $excerpt = wp_trim_words(get_the_excerpt() ?: get_the_content(), 18);
        if ($excerpt) echo '<p class="ttos-product-desc">' . esc_html($excerpt) . '</p>';
        if ($allergens) echo '<small class="ttos-product-allergens">' . esc_html__('Allergens:', 'takeaway-os') . ' ' . esc_html($allergens) . '</small>';

        if ($stock === 'outofstock') {
            echo '<button class="ttos-order-btn is-disabled" disabled>' . esc_html__('Sold out today', 'takeaway-os') . '</button>';
        } else {
            self::add_to_basket_form($id);
        }
        echo '</div></article>';
    }

    private static function sticky_cart_bar(): void {
        if (!function_exists('WC') || !WC()->cart) return;
        $count = WC()->cart->get_cart_contents_count();
        $subtotal = wp_strip_all_tags(wc_price((float) WC()->cart->get_subtotal()));
        echo '<aside class="ttos-sticky-cart" data-count="' . esc_attr((string) $count) . '"><div class="ttos-sticky-cart-info"><strong>' . esc_html__('Your basket', 'takeaway-os') . '</strong><span>' . esc_html(sprintf(_n('%d item', '%d items', $count, 'takeaway-os'), $count)) . ' · ' . esc_html($subtotal) . '</span></div><a class="ttos-order-btn" href="' . esc_url(wc_get_cart_url()) . '">' . esc_html__('View basket', 'takeaway-os') . '</a></aside>';
    }

    private static function add_to_basket_form(int $product_id): void {
        $groups = TTOS_WooCommerce::get_option_groups($product_id);
        $modal_id = 'ttos-config-modal-' . $product_id . '-' . wp_rand(100, 999);

        if ($groups) {
            $title_id = esc_attr($modal_id) . '-title';
            echo '<button type="button" class="ttos-order-btn ttos-open-config" data-modal="#' . esc_attr($modal_id) . '">Configure item</button>';
            echo '<div class="ttos-modal" id="' . esc_attr($modal_id) . '" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr($title_id) . '" aria-hidden="true"><div class="ttos-modal-backdrop" data-close-modal></div><div class="ttos-modal-panel"><button type="button" class="ttos-modal-close" data-close-modal>×</button>';
            echo '<h3 id="' . esc_attr($title_id) . '">' . esc_html(get_the_title($product_id)) . '</h3><p class="ttos-muted">Choose options, add notes, then add to basket.</p>';
        }

        $base_price = (float) get_post_meta($product_id, '_price', true);
        echo '<form class="ttos-config-form" method="post" data-base-price="' . esc_attr((string) $base_price) . '">';
        echo '<input type="hidden" name="add-to-cart" value="' . esc_attr((string) $product_id) . '">';
        echo '<input type="hidden" name="ttos_configured_add" value="1">';
        echo '<label class="ttos-qty">Qty <input type="number" name="quantity" value="1" min="1" max="20"></label>';
        wp_nonce_field('ttos_configure_product_' . $product_id, 'ttos_config_nonce');
        if ($groups) {
            foreach ($groups as $g_index => $group) {
                if (!is_array($group) || empty($group['options'])) continue;
                $type = ($group['type'] ?? 'multiple') === 'single' ? 'radio' : 'checkbox';
                $name = $type === 'radio' ? 'ttos_options[' . esc_attr((string) $g_index) . ']' : 'ttos_options[' . esc_attr((string) $g_index) . '][]';
                echo '<fieldset class="ttos-config-fieldset"><legend>' . esc_html($group['name'] ?? 'Options') . (!empty($group['required']) ? ' *' : '') . '</legend>';
                if (!empty($group['min']) || !empty($group['max'])) {
                    echo '<small class="ttos-choice-rule">Choose ' . esc_html((string) ($group['min'] ?? 0)) . '–' . esc_html((string) ($group['max'] ?? ($type === 'radio' ? 1 : 99))) . '</small>';
                }
                foreach ($group['options'] as $option) {
                    if (!is_array($option) || empty($option['label'])) continue;
                    $disabled = !empty($option['sold_out']);
                    $price = (float) ($option['price'] ?? 0);
                    echo '<label class="ttos-config-option"><input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($option['label']) . '" data-price="' . esc_attr((string) $price) . '" ' . checked(!empty($option['default']), true, false) . ' ' . disabled($disabled, true, false) . '> <span>' . esc_html($option['label']) . ($price > 0 ? ' +' . wp_strip_all_tags(wc_price($price)) : '') . ($disabled ? ' - sold out' : '') . '</span></label>';
                }
                echo '</fieldset>';
            }
        }
        echo '<label class="ttos-item-note">Notes <textarea name="ttos_item_note" rows="2" placeholder="No onion, sauce separate…"></textarea></label>';
        echo '<div class="ttos-config-total"><span>Total from</span><strong></strong></div><button class="ttos-order-btn" type="submit">Add to basket</button>';
        echo '</form>';

        if ($groups) {
            echo '</div></div>';
        }
    }

    /* ------------------------------------------------------------------ *
     * Delivery checker — honest server-side prefix check, no fake JS.
     * ------------------------------------------------------------------ */

    public static function delivery_checker(): string {
        $trading = TTOS_Settings::get('trading');
        $zone_summary = (string) self::sc('delivery_collection', 'zone_summary', '');
        $min_text = (string) self::sc('delivery_collection', 'min_order_text', '');
        if ($min_text === '' && !empty($trading['min_order'])) {
            $min_text = sprintf(__('Minimum delivery order £%s', 'takeaway-os'), $trading['min_order']);
        }
        $estimate = (string) self::sc('delivery_collection', 'delivery_estimate_text', '');
        if ($estimate === '' && !empty($trading['delivery_time'])) {
            $estimate = sprintf(__('Delivery in around %s minutes', 'takeaway-os'), $trading['delivery_time']);
        }

        $prefixes = class_exists('TTOS_Operations') ? TTOS_Operations::postcode_prefixes() : array();
        $postcode_raw = isset($_GET['postcode']) ? sanitize_text_field(wp_unslash($_GET['postcode'])) : '';
        $postcode = strtoupper(preg_replace('/\s+/', '', $postcode_raw));

        $result_html = '';
        if ($postcode !== '') {
            if (!preg_match('/^[A-Z]{1,2}[0-9][A-Z0-9]?[0-9][A-Z]{2}$/', $postcode) && !preg_match('/^[A-Z]{1,2}[0-9][A-Z0-9]?$/', $postcode)) {
                $result_html = '<div class="ttos-check-result is-unknown" role="status">' . esc_html__('That does not look like a UK postcode — please check and try again.', 'takeaway-os') . '</div>';
            } elseif (!$prefixes) {
                $result_html = '<div class="ttos-check-result is-unknown" role="status">' . esc_html__('We could not check that automatically. Delivery availability is confirmed at checkout, or call us to ask.', 'takeaway-os') . '</div>';
            } else {
                $match = false;
                foreach ($prefixes as $prefix) {
                    if ($prefix !== '' && strpos($postcode, $prefix) === 0) { $match = true; break; }
                }
                if ($match) {
                    $result_html = '<div class="ttos-check-result is-yes" role="status"><strong>' . esc_html(sprintf(__('Good news — we deliver to %s.', 'takeaway-os'), $postcode_raw)) . '</strong>'
                        . ($min_text !== '' ? ' ' . esc_html($min_text) . '.' : '')
                        . ($estimate !== '' ? ' ' . esc_html($estimate) . '.' : '')
                        . ' <a href="' . esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/menu/')) . '">' . esc_html__('Start your order', 'takeaway-os') . '</a></div>';
                } else {
                    $result_html = '<div class="ttos-check-result is-no" role="status"><strong>' . esc_html(sprintf(__('Sorry, %s looks outside our delivery area.', 'takeaway-os'), $postcode_raw)) . '</strong> ' . esc_html__('Collection is always available.', 'takeaway-os') . '</div>';
                }
            }
        }

        ob_start();
        echo '<div class="ttos-delivery-checker">';
        echo '<form class="ttos-delivery-check" method="get" action="">';
        echo '<label for="ttos-check-postcode">' . esc_html__('Enter your postcode', 'takeaway-os') . '</label>';
        echo '<div class="ttos-delivery-check-row"><input type="text" id="ttos-check-postcode" name="postcode" maxlength="9" autocomplete="postal-code" value="' . esc_attr($postcode_raw) . '" placeholder="' . esc_attr__('e.g. MK18 1AA', 'takeaway-os') . '"><button type="submit" class="ttos-order-btn">' . esc_html__('Check', 'takeaway-os') . '</button></div>';
        echo '</form>';
        echo $result_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
        if ($zone_summary !== '') echo '<p class="ttos-check-zone">' . esc_html($zone_summary) . '</p>';
        $bits = array_filter(array($min_text, $estimate, (string) self::sc('delivery_collection', 'free_delivery_text', '')));
        if ($bits) echo '<p class="ttos-check-meta">' . esc_html(implode(' · ', $bits)) . '</p>';
        echo '</div>';
        return ob_get_clean();
    }

    /* ------------------------------------------------------------------ *
     * Utility pages
     * ------------------------------------------------------------------ */

    public static function order_tracker(): string {
        $message = '';
        if (!empty($_GET['ttos_order_id']) && TTOS_WooCommerce::active()) {
            $order = wc_get_order(absint($_GET['ttos_order_id']));
            if ($order) {
                $message = '<div class="ttos-front-card ttos-tracker-result" role="status"><h3>' . esc_html__('Order', 'takeaway-os') . ' #' . esc_html($order->get_id()) . '</h3><p class="ttos-tracker-status">' . esc_html(wc_get_order_status_name($order->get_status())) . '</p></div>';
            } else {
                $message = '<div class="ttos-front-card ttos-tracker-result" role="status">' . esc_html__('We could not find that order number. Check the number on your confirmation, or call us.', 'takeaway-os') . '</div>';
            }
        }
        return '<div class="ttos-front-card ttos-tracker"><h2>' . esc_html__('Track your order', 'takeaway-os') . '</h2><form method="get" class="ttos-tracker-form"><label for="ttos-track-id">' . esc_html__('Order number', 'takeaway-os') . '</label><div class="ttos-tracker-row"><input type="number" id="ttos-track-id" name="ttos_order_id" min="1" placeholder="e.g. 466"><button class="ttos-order-btn">' . esc_html__('Track', 'takeaway-os') . '</button></div></form></div>' . $message;
    }

    public static function allergens(): string {
        $content = '';
        if (class_exists('TTOS_Site_Content')) {
            $policy = TTOS_Site_Content::get('policies', 'allergens', array());
            if (is_array($policy) && !empty($policy['content'])) {
                $content = wp_kses_post(wpautop($policy['content']));
            }
        }
        if ($content === '') {
            $content = '<p>' . esc_html__('Please check each menu item for allergen details and contact the restaurant before ordering if you have a serious allergy.', 'takeaway-os') . '</p>';
        }
        $phone = (string) self::sc('business_info', 'phone', TTOS_Settings::get('business', 'phone'));
        $out = '<div class="ttos-policy ttos-allergens-page">' . $content;
        if ($phone !== '') {
            $out .= '<p class="ttos-allergens-call"><a class="ttos-order-btn" href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone)) . '">' . esc_html(sprintf(__('Allergy question? Call %s', 'takeaway-os'), $phone)) . '</a></p>';
        }
        return $out . '</div>';
    }

    public static function contact(): string {
        $intro = (string) self::sc('contact_map', 'intro', '');
        $business = class_exists('TTOS_Site_Content') ? TTOS_Site_Content::get('business_info') : array();
        $address = array();
        foreach (array('address_1', 'address_2', 'town', 'county', 'postcode') as $key) {
            if (!empty($business[$key])) $address[] = $business[$key];
        }
        $phone = (string) ($business['phone'] ?? '');
        $email = (string) ($business['email'] ?? '');
        $whatsapp = (string) ($business['whatsapp'] ?? '');
        $parking = (string) self::sc('contact_map', 'parking_note', '');
        $access = (string) self::sc('contact_map', 'accessibility_note', '');

        $provider = (string) self::sc('contact_map', 'map_provider', 'osm');
        $lat = (string) self::sc('contact_map', 'lat', '');
        $lng = (string) self::sc('contact_map', 'lng', '');
        $gmaps = (string) self::sc('contact_map', 'google_maps_url', '');

        ob_start();
        echo '<div class="ttos-contact-page">';
        if ($intro !== '') echo '<div class="ttos-contact-intro">' . wp_kses_post(wpautop($intro)) . '</div>';
        echo '<div class="ttos-contact-cols">';
        echo '<div class="ttos-front-card">';
        if ($address) echo '<address class="ttos-contact-address">' . esc_html(implode("\n", $address)) . '</address>';
        echo '<ul class="ttos-contact-links">';
        if ($phone !== '') echo '<li><a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone)) . '">' . esc_html($phone) . '</a></li>';
        if ($whatsapp !== '') echo '<li><a href="' . esc_url('https://wa.me/' . preg_replace('/[^0-9]/', '', $whatsapp)) . '" rel="noopener noreferrer" target="_blank">' . esc_html__('WhatsApp us', 'takeaway-os') . '</a></li>';
        if ($email !== '') echo '<li><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></li>';
        echo '</ul>';
        if ($parking !== '') echo '<p class="ttos-contact-note"><strong>' . esc_html__('Parking:', 'takeaway-os') . '</strong> ' . esc_html($parking) . '</p>';
        if ($access !== '') echo '<p class="ttos-contact-note"><strong>' . esc_html__('Accessibility:', 'takeaway-os') . '</strong> ' . esc_html($access) . '</p>';

        // Opening hours summary.
        if (class_exists('TTOS_Site_Content')) {
            $days = TTOS_Site_Content::get('opening_times', 'days', array());
            $rows = array();
            foreach (array('monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed', 'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun') as $key => $label) {
                $day = $days[$key] ?? array();
                if (($day['closed'] ?? '0') === '1') $rows[$label] = __('Closed', 'takeaway-os');
                elseif (!empty($day['open']) && !empty($day['close'])) $rows[$label] = $day['open'] . ' – ' . $day['close'];
            }
            if ($rows) {
                echo '<h3 class="ttos-contact-hours-title">' . esc_html__('Opening times', 'takeaway-os') . '</h3><ul class="ttos-contact-hours">';
                foreach ($rows as $label => $value) echo '<li><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong></li>';
                echo '</ul>';
            }
        }
        echo '</div>';

        if ($provider === 'osm' && $lat !== '' && $lng !== '') {
            $lat_f = (float) $lat; $lng_f = (float) $lng;
            $bbox = sprintf('%F,%F,%F,%F', $lng_f - 0.004, $lat_f - 0.002, $lng_f + 0.004, $lat_f + 0.002);
            $src = 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode($bbox) . '&layer=mapnik&marker=' . rawurlencode($lat_f . ',' . $lng_f);
            echo '<div class="ttos-contact-map"><iframe title="' . esc_attr__('Map', 'takeaway-os') . '" src="' . esc_url($src) . '" loading="lazy"></iframe></div>';
        } elseif ($gmaps !== '') {
            echo '<div class="ttos-contact-map ttos-contact-map-link"><a class="ttos-order-btn" href="' . esc_url($gmaps) . '" rel="noopener noreferrer" target="_blank">' . esc_html__('Open map & directions', 'takeaway-os') . '</a></div>';
        }
        echo '</div></div>';
        return ob_get_clean();
    }

    /**
     * Policy page body from Site Content. Customers see the policy text;
     * editors also see a review reminder. Never overwrites page content —
     * pages only hold this shortcode.
     */
    public static function policy($atts): string {
        $atts = shortcode_atts(array('key' => ''), $atts, 'takeaway_policy');
        $key = sanitize_key($atts['key']);
        $valid = array('privacy', 'cookies', 'terms', 'refunds', 'delivery', 'allergens', 'accessibility', 'hygiene', 'contact_details');
        if (!in_array($key, $valid, true) || !class_exists('TTOS_Site_Content')) return '';
        $policy = TTOS_Site_Content::get('policies', $key, array());
        if (!is_array($policy)) return '';
        $content = trim((string) ($policy['content'] ?? ''));

        // Substitute CRM tokens with live business data so policy pages update
        // automatically when business details are filled in via Site Content.
        if (class_exists('TTOS_Site_Content')) {
            $business = TTOS_Site_Content::get('business_info');
            $biz_address = implode(', ', array_filter(array(
                (string) ($business['address_1'] ?? ''),
                (string) ($business['address_2'] ?? ''),
                (string) ($business['town'] ?? ''),
                (string) ($business['county'] ?? ''),
                (string) ($business['postcode'] ?? ''),
            )));
            $tokens = array(
                '{business_name}' => (string) ($business['business_name'] ?? get_bloginfo('name')),
                '{phone}'         => (string) ($business['phone'] ?? ''),
                '{email}'         => (string) ($business['email'] ?? ''),
                '{address}'       => $biz_address ?: get_bloginfo('name'),
                '{website}'       => esc_url(home_url('/')),
            );
            $content = str_replace(array_keys($tokens), array_values($tokens), $content);
        }

        // Convert lightweight markdown headings so policy text can use ## / ### syntax.
        $content = preg_replace('/^## (.+)$/m',  '<h3>$1</h3>', $content);
        $content = preg_replace('/^### (.+)$/m', '<h4>$1</h4>', $content);
        // Bold: **text**
        $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);

        $out = '<div class="ttos-policy">';
        if (current_user_can('ttos_manage_settings')) {
            $out .= '<div class="ttos-admin-note">' . esc_html__('Starter content only. Review before production. Edit in Takeaway OS → Site Content → Policies. (Only staff see this note.)', 'takeaway-os') . '</div>';
        }
        $out .= $content !== '' ? wp_kses_post(wpautop($content)) : '<p>' . esc_html__('This policy is being prepared. Please contact us with any questions.', 'takeaway-os') . '</p>';

        // Business details block where it belongs.
        if ($key === 'contact_details' || $key === 'hygiene') {
            $business = TTOS_Site_Content::get('business_info');
            $rows = array(
                __('Business name', 'takeaway-os') => (string) ($business['business_name'] ?? ''),
                __('Trading name', 'takeaway-os') => (string) ($business['trading_name'] ?? ''),
                __('Address', 'takeaway-os') => implode(', ', array_filter(array($business['address_1'] ?? '', $business['address_2'] ?? '', $business['town'] ?? '', $business['county'] ?? '', $business['postcode'] ?? ''))),
                __('Phone', 'takeaway-os') => (string) ($business['phone'] ?? ''),
                __('Email', 'takeaway-os') => (string) ($business['email'] ?? ''),
                __('Company number', 'takeaway-os') => (string) ($business['company_number'] ?? ''),
                __('VAT number', 'takeaway-os') => (string) ($business['vat_number'] ?? ''),
                __('Food hygiene rating', 'takeaway-os') => (string) ($business['hygiene_rating'] ?? ''),
            );
            $rows = array_filter($rows);
            if ($rows) {
                $out .= '<h3>' . esc_html__('Business details', 'takeaway-os') . '</h3><ul class="ttos-policy-details">';
                foreach ($rows as $label => $value) {
                    $out .= '<li><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong></li>';
                }
                $out .= '</ul>';
            }
        }
        return $out . '</div>';
    }

    /* ------------------------------------------------------------------ *
     * Existing account/kitchen shortcodes
     * ------------------------------------------------------------------ */

    public static function customer_portal(): string {
        if (!is_user_logged_in()) {
            return '<div class="ttos-front-card"><h2>Your takeaway account</h2><p>Log in to view previous orders, saved addresses and rewards.</p>' . wp_login_form(array('echo' => false)) . '</div>';
        }
        ob_start();
        echo '<div class="ttos-portal"><h2>My orders</h2>';
        if (TTOS_WooCommerce::active()) {
            $orders = wc_get_orders(array('customer_id' => get_current_user_id(), 'limit' => 10));
            if ($orders) {
                echo '<div class="ttos-order-list">';
                foreach ($orders as $order) {
                    echo '<div class="ttos-front-card"><strong>#' . esc_html($order->get_id()) . '</strong><span>' . esc_html(wc_get_order_status_name($order->get_status())) . '</span><b>' . wp_kses_post($order->get_formatted_order_total()) . '</b></div>';
                }
                echo '</div>';
            } else echo '<p>No orders yet.</p>';
        }
        if (TTOS_Settings::module_enabled('loyalty') && class_exists('TTOS_Features')) echo do_shortcode('[takeaway_rewards]');
        self::sticky_cart_bar();
        echo '</div>';
        return ob_get_clean();
    }

    public static function kitchen_screen(): string {
        if (!current_user_can('ttos_view_orders') || !TTOS_WooCommerce::active()) return '';
        $orders = wc_get_orders(array('limit' => 15, 'status' => array('processing','ttos-accepted','ttos-prepping','ttos-ready')));
        ob_start();
        echo '<div class="ttos-kitchen-screen"><h2>Kitchen screen</h2>';
        foreach ($orders as $order) {
            echo '<div class="ttos-kitchen-ticket"><h3>#' . esc_html($order->get_id()) . ' · ' . esc_html(wc_get_order_status_name($order->get_status())) . '</h3><ul>';
            foreach ($order->get_items() as $item) echo '<li>' . esc_html($item->get_quantity()) . ' × ' . esc_html($item->get_name()) . '</li>';
            echo '</ul></div>';
        }
        self::sticky_cart_bar();
        echo '</div>';
        return ob_get_clean();
    }
}

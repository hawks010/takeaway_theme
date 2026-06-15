<?php

defined('ABSPATH') || exit;

final class TTOS_Setup_Health {
    public static function hooks(): void {
        add_action('admin_menu', array(__CLASS__, 'menu'), 24);
        add_action('admin_init', array(__CLASS__, 'handle_posts'));
    }

    public static function menu(): void {
        add_submenu_page('takeaway-os', 'Setup Health', 'Setup Health', 'ttos_manage_settings', 'takeaway-os-setup-health', array(__CLASS__, 'page'));
    }

    public static function handle_posts(): void {
        if (!is_admin() || empty($_POST['ttos_action']) || !current_user_can('ttos_manage_settings')) {
            return;
        }

        $action = sanitize_key(wp_unslash($_POST['ttos_action']));
        $actions = array(
            'setup_health_repair_pages',
            'setup_health_replace_pages',
            'setup_health_assign_core',
            'setup_health_create_starter',
            'setup_health_reset_launchpad',
            'setup_health_run_email_test',
            'setup_health_test_order_mode',
            'setup_health_disable_builder',
        );

        if (!in_array($action, $actions, true)) {
            return;
        }

        check_admin_referer('ttos_' . $action);

        if ($action === 'setup_health_repair_pages') {
            TTOS_Page_Manager::ensure_all('fresh_if_unsafe');
            TTOS_Page_Manager::sync_front_page_option();
            TTOS_Page_Manager::sync_woocommerce_page_options();
            TTOS_Page_Manager::ensure_theme_menus();
            self::redirect('repair-complete', 'actions');
        }

        if ($action === 'setup_health_replace_pages') {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('Only administrators can replace existing page content.', 'takeaway-os'));
            }
            TTOS_Page_Manager::ensure_all('replace');
            TTOS_Page_Manager::sync_front_page_option();
            TTOS_Page_Manager::sync_woocommerce_page_options();
            TTOS_Page_Manager::ensure_theme_menus();
            self::redirect('replace-complete', 'actions');
        }

        if ($action === 'setup_health_assign_core') {
            foreach (array('home', 'menu', 'cart', 'checkout', 'account') as $key) {
                TTOS_Page_Manager::ensure($key, 'fresh_if_unsafe');
            }
            TTOS_Page_Manager::sync_front_page_option();
            TTOS_Page_Manager::sync_woocommerce_page_options();
            TTOS_Page_Manager::ensure_theme_menus();
            self::redirect('assign-complete', 'actions');
        }

        if ($action === 'setup_health_create_starter') {
            TTOS_Page_Manager::ensure_all('fresh_if_unsafe');
            TTOS_Page_Manager::ensure_theme_menus();
            if (class_exists('TTOS_Onboarding')) {
                TTOS_Onboarding::apply_profile();
            }
            if (class_exists('TTOS_Production')) {
                TTOS_Production::apply_starter_menu();
            }
            self::redirect('starter-created', 'actions');
        }

        if ($action === 'setup_health_reset_launchpad') {
            if (class_exists('TTOS_Operations')) {
                TTOS_Operations::update_section('go_live', TTOS_Operations::defaults()['go_live']);
            }
            update_option('ttheme_setup_modal_pending', time(), false);
            self::redirect('launchpad-reset', 'actions');
        }

        if ($action === 'setup_health_run_email_test') {
            $admin_email = sanitize_email((string) get_option('admin_email'));
            if (!$admin_email || !is_email($admin_email)) {
                self::redirect('email-missing', 'actions');
            }
            $delivery = self::email_delivery_status();
            if ($delivery['status'] !== 'pass') {
                self::redirect('email-not-configured', 'actions');
            }
            $subject = 'Takeaway OS test email';
            $message = "This is a Takeaway OS setup health email test.\n\nSite: " . home_url('/') . "\nTime: " . current_time('mysql');
            $sent = wp_mail($admin_email, $subject, $message);
            self::redirect($sent ? 'email-sent' : 'email-failed', 'actions');
        }

        if ($action === 'setup_health_disable_builder') {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('Only administrators can disable page-builder templates.', 'takeaway-os'));
            }
            $conditions = get_option('elementor_pro_theme_builder_conditions', array());
            $drafted = 0;
            if (is_array($conditions)) {
                foreach ($conditions as $location => $templates) {
                    if (!is_array($templates)) continue;
                    foreach (array_keys($templates) as $template_id) {
                        $template_id = absint($template_id);
                        if ($template_id && get_post_type($template_id) === 'elementor_library' && get_post_status($template_id) === 'publish') {
                            wp_update_post(array('ID' => $template_id, 'post_status' => 'draft'));
                            $drafted++;
                        }
                    }
                }
            }
            update_option('elementor_pro_theme_builder_conditions', array());
            self::redirect('builder-disabled', 'actions');
        }

        if ($action === 'setup_health_test_order_mode') {
            if (!TTOS_WooCommerce::active()) {
                self::redirect('test-order-guidance', 'actions');
            }
            self::redirect('test-order-guidance', 'actions');
        }
    }

    private static function redirect(string $notice, string $anchor = ''): void {
        $url = add_query_arg(
            array(
                'page' => 'takeaway-os-setup-health',
                'ttos_notice' => $notice,
            ),
            admin_url('admin.php')
        );
        if ($anchor) {
            $url .= '#' . sanitize_key($anchor);
        }
        wp_safe_redirect($url);
        exit;
    }

    public static function page(): void {
        $summary = self::summary();
        self::shell_start('Setup Health', 'Repair the public site, check assignments, and confirm what is still missing before preview or staging handoff.');
        self::summary_panel($summary);
        self::actions_panel();
        self::checks_panel($summary['checks']);
        self::shell_end();
    }

    public static function summary(): array {
        $checks = self::checks();
        $pass = 0;
        $warn = 0;
        $fail = 0;
        $pending = array();

        foreach ($checks as $check) {
            if ($check['status'] === 'pass') {
                $pass++;
                continue;
            }
            if ($check['status'] === 'warn') {
                $warn++;
            } else {
                $fail++;
            }
            $pending[] = $check['label'];
        }

        $total = count($checks);
        $progress = $total > 0 ? (int) round(($pass / $total) * 100) : 0;

        return array(
            'checks' => $checks,
            'pass' => $pass,
            'warn' => $warn,
            'fail' => $fail,
            'total' => $total,
            'progress' => $progress,
            'pending' => $pending,
            'core_ready' => self::basic_site_ready($checks),
        );
    }

    public static function checks(): array {
        $checks = array();
        $wc_active = TTOS_WooCommerce::active();
        $theme_active = self::theme_active();
        $primary_menu_exists = self::nav_menu_exists('Takeaway Primary');
        $footer_menu_exists = self::nav_menu_exists('Takeaway Footer');
        $menu_locations = get_theme_mod('nav_menu_locations', array());
        if (!is_array($menu_locations)) {
            $menu_locations = array();
        }

        $checks[] = self::make_check('ttos_active', 'Takeaway OS active', is_plugin_active(TTOS_BASENAME), 'Takeaway OS is active.', 'Takeaway OS must be active for the launchpad, pages, and order overlay to work.', true);
        $checks[] = self::make_check('woocommerce_active', 'WooCommerce active', $wc_active, $wc_active ? 'WooCommerce is active.' : 'WooCommerce must be active before menu, basket, checkout, and order handling can work.', 'Install or activate WooCommerce from Launchpad.', true);
        $checks[] = self::make_check('woocommerce_version', 'WooCommerce version detected', $wc_active, $wc_active ? 'WooCommerce ' . WC()->version . ' detected.' : 'WooCommerce version cannot be checked until the plugin is active.', 'Install WooCommerce to complete this check.');

        $checks[] = self::page_check('home', 'Homepage exists', 'The homepage record exists.', 'Create or repair the Takeaway homepage.');
        $checks[] = self::assignment_check('home_assigned', 'Homepage is assigned as the WordPress static front page', self::page_is_front_page('home'), 'The Home page is the static front page.', 'Assign Home as the static front page.', true);
        $checks[] = self::page_check('menu', 'Menu page exists', 'The menu page exists.', 'Create or repair the menu page.');
        $checks[] = self::assignment_check('menu_assigned', 'Menu page is assigned as WooCommerce shop page', self::page_matches_option('menu', 'woocommerce_shop_page_id'), 'The Menu page is assigned as the WooCommerce shop page.', 'Assign the Menu page as the WooCommerce shop page.', true);
        $checks[] = self::page_check('cart', 'Basket page exists', 'The Basket page exists.', 'Create or repair the Basket page.');
        $checks[] = self::assignment_check('cart_assigned', 'Basket page is assigned as WooCommerce cart page', self::page_matches_option('cart', 'woocommerce_cart_page_id'), 'The Basket page is assigned as the WooCommerce cart page.', 'Assign the Basket page in WooCommerce.', true);
        $checks[] = self::page_check('checkout', 'Checkout page exists', 'The Checkout page exists.', 'Create or repair the Checkout page.');
        $checks[] = self::assignment_check('checkout_assigned', 'Checkout page is assigned as WooCommerce checkout page', self::page_matches_option('checkout', 'woocommerce_checkout_page_id'), 'The Checkout page is assigned as the WooCommerce checkout page.', 'Assign the Checkout page in WooCommerce.', true);
        $checks[] = self::page_check('account', 'My Account page exists', 'The My Account page exists.', 'Create or repair the My Account page.');
        $checks[] = self::assignment_check('account_assigned', 'My Account page is assigned as WooCommerce account page', self::page_matches_option('account', 'woocommerce_myaccount_page_id'), 'The My Account page is assigned as the WooCommerce account page.', 'Assign the My Account page in WooCommerce.', true);
        $checks[] = self::content_check('tracker', 'Order Tracker page exists and contains correct Takeaway shortcode/marker');
        $checks[] = self::content_check('allergens', 'Allergens page exists and contains correct shortcode/marker');
        $checks[] = self::content_check('delivery', 'Delivery Checker page exists and contains correct shortcode/marker');
        $checks[] = self::content_check('meal_deals', 'Meal Deals page exists and contains correct shortcode/marker');
        $checks[] = self::content_check('rewards', 'Rewards page exists and contains correct shortcode/marker');
        $checks[] = self::make_check('primary_menu_exists', 'Header menu exists', $primary_menu_exists, 'Takeaway Primary exists.', 'Create or repair the header menu.', true);
        $checks[] = self::make_check('primary_menu_assigned', 'Header menu is assigned to the theme primary menu location', !empty($menu_locations['primary']), 'The primary menu location has a menu assigned.', 'Assign a menu to the primary theme location.', true);
        $checks[] = self::make_check('footer_menu_exists', 'Footer menu exists', $footer_menu_exists, 'Takeaway Footer exists.', 'Create or repair the footer menu.', true);
        $checks[] = self::make_check('footer_menu_assigned', 'Footer menu is assigned to the theme footer menu location', !empty($menu_locations['footer']), 'The footer menu location has a menu assigned.', 'Assign a menu to the footer theme location.', true);
        $checks[] = self::make_check('permalinks', 'Permalinks are not plain if possible', get_option('permalink_structure') !== '', 'Pretty permalinks are enabled.', 'Plain permalinks are still enabled. Recommended before public testing.');
        $checks[] = self::make_check('rest_api', 'REST API appears available', function_exists('rest_url') && function_exists('rest_get_server'), 'REST API functions are available.', 'REST API support could not be confirmed from this install.');
        $checks[] = self::make_check('wp_cron', 'WP Cron appears available', !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON, 'WP Cron is enabled.', 'WP Cron is disabled or unknown. Server cron may still be required.');
        $checks[] = self::make_check('ssl', 'SSL detected on live URL', stripos(home_url('/'), 'https://') === 0, 'The site URL uses HTTPS.', 'The site URL is not using HTTPS yet.');
        $checks[] = self::status_from_tuple('payment_gateway_usable', 'Payment gateway usable for checkout', self::payment_gateway_status());
        $checks[] = self::status_from_tuple('stripe_plugin', 'Stripe gateway plugin status', self::stripe_plugin_status());
        $checks[] = self::status_from_tuple('stripe_mode', 'Stripe connection and mode status', self::stripe_mode_status());
        $checks[] = self::status_from_tuple('manual_gateway', 'Manual/test gateway available for staging', self::manual_gateway_status());
        $checks[] = self::status_from_tuple('smtp_plugin', 'SMTP/email plugin status', self::smtp_plugin_status());
        $checks[] = self::status_from_tuple('smtp_configured', 'SMTP delivery configured', self::email_delivery_status());
        $checks[] = self::status_from_tuple('woocommerce_order_email', 'WooCommerce order emails available', self::woocommerce_order_email_status());
        $checks[] = self::status_from_tuple('admin_email', 'WordPress admin email present', self::admin_email_status());
        $checks[] = self::status_from_tuple('email_sender', 'WooCommerce email sender details sane', self::email_sender_status());
        $checks[] = self::status_from_tuple('handover_roles', 'Handover roles and capabilities hardened', self::handover_role_status());
        $checks[] = self::make_check('starter_menu', 'Starter menu content exists', self::starter_content_exists(), 'Published menu products exist.', 'Create starter content or add real menu products.', true);
        $checks[] = self::make_check('theme_active', 'Theme is active', $theme_active, 'The Takeaway theme is active.', 'The site is not currently using the Takeaway theme.', true);

        foreach (self::site_content_checks() as $check) {
            $checks[] = $check;
        }

        return $checks;
    }

    /**
     * v1.3.0 Site Content checks. Content gaps are warnings only — this is
     * a beta/staging build, missing content must not hard-fail Setup Health.
     */
    private static function site_content_checks(): array {
        if (!class_exists('TTOS_Site_Content')) {
            return array();
        }
        $checks = array();

        $option_exists = is_array(get_option('ttos_site_content'));
        $checks[] = self::make_check('site_content_option', 'Site Content storage initialised', $option_exists, 'The Site Content option exists.', 'Open Takeaway OS → Site Content and save any tab to initialise it.');

        $business_name = (string) TTOS_Site_Content::get('business_info', 'business_name', '');
        if ($business_name === '') {
            $business_name = (string) TTOS_Settings::get('business', 'restaurant_name');
        }
        $checks[] = self::make_check('site_content_business_name', 'Business name available for templates', trim($business_name) !== '', 'A business name is available (Site Content or Business Settings).', 'Add the business name in Takeaway OS → Site Content → Business Info.');

        $hours = TTOS_Site_Content::get('opening_times');
        $hours_configured = false;
        foreach ((array) ($hours['days'] ?? array()) as $day) {
            if (!empty($day['closed']) && $day['closed'] === '1') { $hours_configured = true; break; }
            if (!empty($day['open']) && !empty($day['close'])) { $hours_configured = true; break; }
        }
        $checks[] = self::make_check('site_content_hours', 'Opening hours configured', $hours_configured, 'Weekly opening hours are set.', 'Fill in opening hours in Takeaway OS → Site Content → Opening Times.');

        $hero_title = (string) TTOS_Site_Content::get('homepage', 'hero_title', '');
        $checks[] = self::make_check('site_content_hero', 'Homepage hero headline available', trim($hero_title . $business_name) !== '', 'A hero title or business name is available for the homepage.', 'Add a hero title in Site Content → Homepage, or set the business name.');

        $footer = TTOS_Site_Content::get('footer');
        $business = TTOS_Site_Content::get('business_info');
        $footer_ready = trim((string) $footer['text']) !== ''
            || trim((string) $business['phone']) !== ''
            || trim((string) $business['email']) !== ''
            || trim((string) TTOS_Settings::get('business', 'email')) !== '';
        $checks[] = self::make_check('site_content_footer', 'Footer basics present', $footer_ready, 'Footer has text or business contact details to show.', 'Add footer text or business contact details in Site Content.');

        $hygiene = trim((string) $business['hygiene_rating']);
        if ($hygiene === '') {
            $hygiene = trim((string) TTOS_Settings::get('business', 'fsa_rating'));
        }
        $checks[] = self::make_check('site_content_hygiene', 'Food hygiene rating recorded', $hygiene !== '', 'A food hygiene rating is recorded.', 'Add the FSA hygiene rating in Site Content → Business Info. Optional but strongly recommended for trust.');

        $checks[] = self::schedule_check('banner');
        $checks[] = self::schedule_check('popup');

        foreach (self::integrity_checks() as $check) {
            $checks[] = $check;
        }

        return $checks;
    }

    /**
     * v1.3.0 integrity checks: page-builder hijacks, duplicate pages and
     * placeholder identity. The Elementor check is critical because a
     * leftover theme-builder template silently removes the theme header
     * and footer on every page (observed in the field).
     */
    private static function integrity_checks(): array {
        $checks = array();

        $conditions = get_option('elementor_pro_theme_builder_conditions', array());
        $hijack = is_array($conditions) && array_filter($conditions);
        $checks[] = self::make_check(
            'builder_hijack',
            'No page-builder header/footer hijack',
            !$hijack,
            'No Elementor theme-builder templates are overriding the theme header/footer.',
            'Elementor Pro theme-builder conditions are registered and will replace the Takeaway theme header/footer on every page. Use the repair action below to disable them.',
            true
        );

        $checks[] = self::duplicate_page_check('home', 'page_on_front', 'Home');
        $checks[] = self::duplicate_page_check('account', 'woocommerce_myaccount_page_id', 'My Account');
        $checks[] = self::duplicate_page_check('menu', 'woocommerce_shop_page_id', 'Menu');
        $checks[] = self::duplicate_page_check('cart', 'woocommerce_cart_page_id', 'Cart');
        $checks[] = self::duplicate_page_check('checkout', 'woocommerce_checkout_page_id', 'Checkout');

        $blogname = strtolower(trim((string) get_option('blogname')));
        $placeholder = in_array($blogname, array('', 'blueprint', 'takeaaway', 'wordpress', 'my wordpress blog', 'just another wordpress site', 'test'), true);
        $checks[] = self::make_check('site_identity', 'Site identity is not a placeholder', !$placeholder, 'The site title looks like a real business identity.', 'The WordPress site title still looks like a placeholder ("' . esc_html(get_option('blogname')) . '"). Set the business name in Takeaway OS → Business Settings.');

        $policy_keys = array('policy_privacy', 'policy_cookies', 'policy_terms', 'policy_refunds', 'policy_delivery', 'policy_accessibility', 'policy_hygiene', 'policy_business', 'contact');
        $missing = array();
        foreach ($policy_keys as $key) {
            $status = TTOS_Page_Manager::status($key);
            if (($status['state'] ?? 'missing') !== 'ready') $missing[] = $key;
        }
        $checks[] = self::make_check('policy_pages', 'Policy and contact pages generated', !$missing, 'All policy/contact pages exist with their Takeaway content markers.', count($missing) . ' policy/contact pages are missing. Run "Create / repair public pages" below.');

        // --- New checks added v1.3.0+ ---

        // elementor_hijack: per-template published check (distinct from builder_hijack which only checks whether conditions are non-empty)
        $elementor_conditions = get_option('elementor_pro_theme_builder_conditions', array());
        $hijack_template_id = 0;
        if (is_array($elementor_conditions)) {
            foreach ($elementor_conditions as $location => $templates) {
                if (!is_array($templates)) continue;
                foreach ($templates as $template_id => $conds) {
                    if (!empty($conds['include']['general'])) {
                        $post = get_post(absint($template_id));
                        if ($post && $post->post_status === 'publish') {
                            $hijack_template_id = (int) $template_id;
                            break 2;
                        }
                    }
                }
            }
        }
        $checks[] = array(
            'id'      => 'elementor_hijack',
            'label'   => 'Elementor template overriding site header/footer',
            'status'  => $hijack_template_id === 0 ? 'pass' : 'fail',
            'message' => $hijack_template_id === 0
                ? 'No published Elementor Pro theme-builder template has a site-wide condition.'
                : 'An Elementor Pro theme builder template (ID ' . $hijack_template_id . ') has site-wide conditions and is published. This prevents the Takeaway theme header/footer from rendering.',
            'hint'    => $hijack_template_id === 0 ? '' : 'Use the "Disable conflicting page-builder templates" repair action above to set the template to draft.',
        );

        // blogname_typo: catches the "Takeaaway" double-a variant (separate from the site_identity placeholder check)
        $raw_blogname = (string) get_bloginfo('name');
        $has_typo = strpos($raw_blogname, 'Takeaaway') !== false;
        $checks[] = self::make_check(
            'blogname_typo',
            'Site name contains a typo ("Takeaaway")',
            !$has_typo,
            'Site name does not contain the "Takeaaway" double-a typo.',
            'The site name "' . esc_html($raw_blogname) . '" has a double-a typo. It will appear in browser tabs, emails, and receipts. Fix it in Settings → General.'
        );

        // duplicate_wc_pages: slug-based duplicate published page check for WooCommerce core slugs
        $wc_slugs  = array('cart', 'checkout', 'my-account', 'shop');
        $slug_dupes = array();
        foreach ($wc_slugs as $slug) {
            $slug_pages = get_posts(array(
                'post_type'   => 'page',
                'post_status' => 'publish',
                'name'        => $slug,
                'numberposts' => 10,
                'fields'      => 'ids',
            ));
            if (count($slug_pages) > 1) {
                $slug_dupes[] = $slug . ' (' . count($slug_pages) . ' pages)';
            }
        }
        $checks[] = self::make_check(
            'duplicate_wc_pages',
            'Duplicate WooCommerce pages detected',
            empty($slug_dupes),
            'No duplicate published pages share a WooCommerce core slug (cart, checkout, my-account, shop).',
            'Multiple published pages share a WooCommerce slug: ' . implode(', ', $slug_dupes) . '. WooCommerce may use the wrong one. Review and draft the duplicates manually.'
        );

        // duplicate_starter_products: flags an unusually high product count that suggests demo products were imported twice
        if (post_type_exists('product') && get_option('ttos_starter_products_created')) {
            $product_count = (int) (wp_count_posts('product')->publish ?? 0);
            $checks[] = self::make_check(
                'duplicate_starter_products',
                'Duplicate starter/demo products detected',
                $product_count < 50,
                'Published product count (' . $product_count . ') is within the expected starter range.',
                'There are ' . $product_count . ' published products — more than the starter data would normally create. Demo products may have been imported more than once. Review WooCommerce → Products.'
            );
        }

        // Section header for accessibility colour checks
        $checks[] = array(
            'id'          => 'a11y_brand_header',
            'status'      => 'hint',
            'label'       => 'Accessibility — Brand Colours',
            'description' => 'WCAG 2.1 AA contrast ratios for the colours set in Branding. Fix any failures before handing over to a client.',
            'message'     => 'WCAG 2.1 AA contrast ratios for the colours set in Branding. Fix any failures before handing over to a client.',
            'hint'        => '',
        );

        foreach (self::a11y_brand_checks() as $check) {
            $checks[] = $check;
        }

        return $checks;
    }

    /**
     * Warn when extra published pages share the title of an assigned core
     * page — the classic leftover-site trap (old "Home"/"My Account" pages
     * still published next to the generated ones).
     */
    private static function duplicate_page_check(string $key, string $option_name, string $title): array {
        $assigned = absint(get_option($option_name, 0));
        $duplicates = array();
        $matches = get_posts(array(
            'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 20,
            'title' => $title, 'fields' => 'ids',
        ));
        foreach ((array) $matches as $page_id) {
            $page_id = (int) $page_id;
            if ($page_id && $page_id !== $assigned && !get_post_meta($page_id, '_ttos_generated_page', true)) {
                $duplicates[] = '#' . $page_id;
            }
        }
        return self::make_check(
            'duplicate_' . $key,
            'No duplicate "' . $title . '" pages',
            !$duplicates,
            'No stray published pages share the "' . $title . '" title.',
            'Published page(s) ' . implode(', ', $duplicates) . ' also use the title "' . $title . '" but are not the assigned page. Customers may land on the wrong one — review and draft them manually (nothing is deleted automatically).'
        );
    }

    private static function schedule_check(string $section): array {
        $config = TTOS_Site_Content::get($section);
        $enabled = ($config['enabled'] ?? '0') === '1';
        $label = $section === 'banner' ? 'Banner schedule valid' : 'Popup schedule valid';
        if (!$enabled) {
            return self::make_check('site_content_' . $section . '_schedule', $label, true, ucfirst($section) . ' is disabled — no schedule to validate.', '');
        }
        $start = (string) ($config['start'] ?? '');
        $end   = (string) ($config['end'] ?? '');
        $valid = true;
        $problem = '';
        if ($start !== '' && $end !== '' && strtotime($end) <= strtotime($start)) {
            $valid = false;
            $problem = 'The end date/time is before the start date/time.';
        } elseif ($end !== '' && strtotime($end) < time()) {
            $valid = false;
            $problem = 'The end date/time is in the past — it will never show.';
        }
        if ($valid && trim((string) ($config['title'] ?? '') . (string) ($config['message'] ?? '')) === '') {
            $valid = false;
            $problem = 'It is enabled but has no title or message.';
        }
        return self::make_check('site_content_' . $section . '_schedule', $label, $valid, ucfirst($section) . ' schedule looks valid.', $problem . ' Fix it in Site Content → ' . ucfirst($section) . '.');
    }

    public static function launchpad_summary_card(): string {
        $summary = self::summary();
        $pending = array_slice($summary['pending'], 0, 5);

        ob_start();
        echo '<section class="ttos-card ttos-setup-health-summary"><div class="ttos-card-head"><div><p class="ttos-eyebrow">Setup Health</p><h2>' . esc_html((string) $summary['progress']) . '% complete</h2><p class="ttos-muted">Pass: ' . esc_html((string) $summary['pass']) . ' · Warnings: ' . esc_html((string) $summary['warn']) . ' · Failures: ' . esc_html((string) $summary['fail']) . '</p></div>';
        echo '<div class="ttos-installer-actions"><a class="ttos-button" href="' . esc_url(admin_url('admin.php?page=takeaway-os-setup-health')) . '">Open Setup Health</a><button type="button" class="ttos-button ttos-wizard-next" data-next="pages">Finish public site pages</button>';
        if ($summary['core_ready']) {
            echo '<a class="ttos-button ttos-button-dark" href="' . esc_url(home_url('/')) . '" target="_blank" rel="noreferrer noopener">View site</a>';
        }
        echo '</div></div><div class="ttos-big-progress"><span style="width:' . esc_attr((string) $summary['progress']) . '%"></span></div>';
        if ($pending) {
            echo '<p class="ttos-muted">Still missing: ' . esc_html(implode(', ', $pending)) . (count($summary['pending']) > count($pending) ? ' ...' : '') . '</p>';
        } else {
            echo '<p class="ttos-good">Core public pages, menus, and plugin setup checks are all passing.</p>';
        }
        echo '</section>';
        return ob_get_clean();
    }

    private static function summary_panel(array $summary): void {
        echo '<section class="ttos-card"><div class="ttos-card-head"><div><p class="ttos-eyebrow">Setup Readiness</p><h2>' . esc_html((string) $summary['progress']) . '% complete</h2><p class="ttos-muted">This score is based on the setup checks below, not on external browser or payment-provider validation.</p></div><div class="ttos-installer-actions"><a class="ttos-button" href="' . esc_url(admin_url('admin.php?page=takeaway-os-launchpad')) . '">Open Launchpad</a>';
        if ($summary['core_ready']) {
            echo '<a class="ttos-button ttos-button-dark" href="' . esc_url(home_url('/')) . '" target="_blank" rel="noreferrer noopener">View site</a>';
        }
        echo '</div></div><div class="ttos-grid ttos-grid-3"><div class="ttos-mini-card"><strong>' . esc_html((string) $summary['pass']) . '</strong><span>Passing checks</span></div><div class="ttos-mini-card"><strong>' . esc_html((string) $summary['warn']) . '</strong><span>Warnings</span></div><div class="ttos-mini-card"><strong>' . esc_html((string) $summary['fail']) . '</strong><span>Failures</span></div></div><div class="ttos-big-progress"><span style="width:' . esc_attr((string) $summary['progress']) . '%"></span></div>';
        if ($summary['pending']) {
            echo '<div class="ttos-callout"><strong>Still missing:</strong> ' . esc_html(implode(', ', array_slice($summary['pending'], 0, 8))) . (count($summary['pending']) > 8 ? ' ...' : '') . '</div>';
        }
        echo '</section>';
    }

    private static function actions_panel(): void {
        echo '<section id="actions" class="ttos-card"><div class="ttos-card-head"><div><h2>Repair and testing actions</h2><p class="ttos-muted">These actions keep old content safe by default and only replace page content when an administrator explicitly requests it.</p></div></div><div class="ttos-grid ttos-grid-2">';

        self::action_form('setup_health_repair_pages', 'Repair pages and menus', 'Creates missing Takeaway pages, keeps old content safe, assigns homepage/WooCommerce pages, and builds header/footer menus.');
        self::action_form('setup_health_assign_core', 'Assign homepage and WooCommerce pages', 'Re-syncs Home, Menu, Basket, Checkout, and My Account assignments without a destructive reset.');
        self::action_form('setup_health_create_starter', 'Create starter content', 'Applies the Takeaway profile, starter categories, pages, and sample menu products for preview.');
        self::action_form('setup_health_reset_launchpad', 'Reset Launchpad progress', 'Resets the Go Live checklist and re-flags the theme setup prompt for another guided pass.');

        echo '<div class="ttos-mini-card"><h3>View site</h3><p class="ttos-muted">Open the front end in a new tab to review the current public setup.</p><a class="ttos-button" href="' . esc_url(home_url('/')) . '" target="_blank" rel="noreferrer noopener">View site</a></div>';
        echo '<div class="ttos-mini-card"><h3>Open Launchpad</h3><p class="ttos-muted">Jump back into the guided setup flow.</p><a class="ttos-button" href="' . esc_url(admin_url('admin.php?page=takeaway-os-launchpad')) . '">Open Launchpad</a></div>';

        self::action_form('setup_health_run_email_test', 'Run email test', 'Sends a basic test email to the current WordPress admin email if email sending is available.');
        self::action_form('setup_health_test_order_mode', 'Run test order mode setup', 'Shows guidance for enabling a manual/test payment route before placing a staging order.');

        if (current_user_can('manage_options')) {
            $builder_conditions = get_option('elementor_pro_theme_builder_conditions', array());
            if (is_array($builder_conditions) && array_filter($builder_conditions)) {
                self::action_form('setup_health_disable_builder', 'Disable conflicting page-builder templates', 'Admin-only: drafts the Elementor theme-builder templates hijacking the theme header/footer and clears their display conditions. Templates are drafted, never deleted.');
            }
            self::action_form('setup_health_replace_pages', 'Replace content repair', 'Admin-only: replace existing Takeaway-generated page content instead of creating a fresh safe page.');
        }

        echo '</div></section>';
    }

    private static function checks_panel(array $checks): void {
        echo '<section class="ttos-card"><div class="ttos-card-head"><div><h2>Health checks</h2><p class="ttos-muted">Pass/warn/fail cards for the current install state.</p></div></div><div class="ttos-setup-health-grid">';
        foreach ($checks as $check) {
            $class = $check['status'] === 'pass' ? 'ttos-good' : ($check['status'] === 'warn' ? 'ttos-warn' : 'ttos-bad');
            echo '<article class="ttos-setup-health-card ' . esc_attr('is-' . $check['status']) . '"><div class="ttos-card-head"><h3>' . esc_html($check['label']) . '</h3><span class="' . esc_attr($class) . '">' . esc_html(strtoupper($check['status'])) . '</span></div><p>' . esc_html($check['message']) . '</p>';
            if (!empty($check['hint'])) {
                echo '<small class="ttos-muted">' . esc_html($check['hint']) . '</small>';
            }
            echo '</article>';
        }
        echo '</div></section>';
    }

    private static function action_form(string $action, string $label, string $description): void {
        echo '<div class="ttos-mini-card"><h3>' . esc_html($label) . '</h3><p class="ttos-muted">' . esc_html($description) . '</p><form method="post">';
        wp_nonce_field('ttos_' . $action);
        echo '<input type="hidden" name="ttos_action" value="' . esc_attr($action) . '"><button class="ttos-button">' . esc_html($label) . '</button></form></div>';
    }

    private static function shell_start(string $title, string $subtitle = ''): void {
        echo '<div class="ttos-wrap"><div class="ttos-shell"><div class="ttos-top"><div><p class="ttos-eyebrow">Takeaway OS</p><h1>' . esc_html($title) . '</h1>';
        if ($subtitle) {
            echo '<p>' . esc_html($subtitle) . '</p>';
        }
        echo '</div><a class="ttos-pill" href="' . esc_url(home_url('/')) . '" target="_blank" rel="noreferrer noopener">View site ↗</a></div>';
        self::nav();
        self::notices();
    }

    private static function shell_end(): void {
        echo '</div></div>';
    }

    private static function nav(): void {
        $items = array(
            'takeaway-os' => 'Dashboard',
            'takeaway-os-launchpad' => 'Launchpad',
            'takeaway-os-setup-health' => 'Setup Health',
            'takeaway-os-menu' => 'Menu',
            'takeaway-os-orders' => 'Orders',
            'takeaway-os-kitchen' => 'Kitchen',
            'takeaway-os-customers' => 'Customers',
            'takeaway-os-reports' => 'Reports',
            'takeaway-os-site-content' => 'Site Content',
            'takeaway-os-settings' => 'Settings',
            'takeaway-os-payments' => 'Payments',
            'takeaway-os-delivery' => 'Delivery',
            'takeaway-os-modules' => 'Add-ons',
            'takeaway-os-production' => 'Production',
        );
        $current = isset($_GET['page']) ? sanitize_key($_GET['page']) : 'takeaway-os-setup-health';
        echo '<nav class="ttos-nav">';
        foreach ($items as $slug => $label) {
            if ($slug === 'takeaway-os-modules' && !current_user_can('ttos_modules')) {
                continue;
            }
            echo '<a class="' . esc_attr($current === $slug ? 'active' : '') . '" href="' . esc_url(admin_url('admin.php?page=' . $slug)) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
    }

    private static function notices(): void {
        $notice = sanitize_key($_GET['ttos_notice'] ?? '');
        if ($notice === '') {
            return;
        }

        $messages = array(
            'repair-complete' => 'Setup repair completed in safe mode.',
            'replace-complete' => 'Takeaway page content was replaced on the current mapped pages.',
            'assign-complete' => 'Homepage, WooCommerce pages, and menu locations were reassigned.',
            'starter-created' => 'Starter setup content was created or refreshed.',
            'launchpad-reset' => 'Launchpad progress was reset.',
            'email-sent' => 'A setup health test email was sent to the admin email address.',
            'email-failed' => 'The test email could not be sent. Check your email plugin or mail transport.',
            'email-missing' => 'A valid admin email is required before sending a test email.',
            'email-not-configured' => 'Email delivery is not configured yet. Configure SMTP before running a delivery test.',
            'builder-disabled' => 'Conflicting page-builder templates were drafted and their display conditions cleared. Nothing was deleted.',
            'test-order-guidance' => 'Next step: open WooCommerce payment settings, enable a safe test/manual gateway, then place a checkout test from the public site.',
        );

        if (isset($messages[$notice])) {
            echo '<div class="ttos-notice">' . esc_html($messages[$notice]) . '</div>';
        }
    }

    private static function make_check(string $id, string $label, bool $pass, string $pass_message, string $fail_message, bool $critical = false): array {
        return array(
            'id' => $id,
            'label' => $label,
            'status' => $pass ? 'pass' : ($critical ? 'fail' : 'warn'),
            'message' => $pass ? $pass_message : $fail_message,
            'hint' => $pass ? '' : ($critical ? 'Use the repair actions above or Launchpad to fix this.' : 'Recommended before staging or live checkout testing.'),
        );
    }

    private static function status_from_tuple(string $id, string $label, array $tuple): array {
        return array(
            'id' => $id,
            'label' => $label,
            'status' => $tuple['status'],
            'message' => $tuple['message'],
            'hint' => $tuple['hint'],
        );
    }

    private static function page_check(string $key, string $label, string $pass_message, string $fail_message): array {
        $status = TTOS_Page_Manager::status($key);
        $ready = !empty($status['id']);
        return self::make_check($key . '_exists', $label, $ready, $pass_message, $fail_message, true);
    }

    private static function assignment_check(string $id, string $label, bool $pass, string $pass_message, string $fail_message, bool $critical = false): array {
        return self::make_check($id, $label, $pass, $pass_message, $fail_message, $critical);
    }

    private static function content_check(string $key, string $label): array {
        $status = TTOS_Page_Manager::status($key);
        $pass = ($status['state'] ?? '') === 'ready';
        return self::make_check($key . '_content', $label, $pass, 'The page exists and contains the expected Takeaway content marker.', 'The page is missing or does not contain the expected Takeaway content yet.', true);
    }

    private static function page_is_front_page(string $key): bool {
        $id = TTOS_Page_Manager::get_page_id($key);
        return $id > 0 && get_option('show_on_front') === 'page' && (int) get_option('page_on_front') === $id;
    }

    private static function page_matches_option(string $key, string $option_name): bool {
        $id = TTOS_Page_Manager::get_page_id($key);
        return $id > 0 && (int) get_option($option_name) === $id;
    }

    private static function nav_menu_exists(string $menu_name): bool {
        return (bool) wp_get_nav_menu_object($menu_name);
    }

    private static function starter_content_exists(): bool {
        if (!post_type_exists('product')) {
            return false;
        }
        $count = wp_count_posts('product');
        return $count && !empty($count->publish);
    }

    private static function payment_gateway_status(): array {
        if (!TTOS_WooCommerce::active() || !class_exists('WC_Payment_Gateways')) {
            return array(
                'status' => 'fail',
                'message' => 'No active payment gateway check is possible until WooCommerce is active.',
                'hint' => 'Activate WooCommerce first, then configure a test/manual or real payment gateway.',
            );
        }

        $enabled = array();
        $available = array();
        foreach (self::payment_gateways() as $gateway) {
            $title = self::gateway_label($gateway);
            $available[] = $title;
            if (isset($gateway->enabled) && $gateway->enabled === 'yes') {
                $enabled[] = $title;
            }
        }

        if ($enabled) {
            return array(
                'status' => 'pass',
                'message' => 'Enabled checkout gateway(s): ' . implode(', ', $enabled) . '.',
                'hint' => '',
            );
        }

        return array(
            'status' => 'warn',
            'message' => $available ? 'Gateways are installed but none are enabled for checkout yet.' : 'No payment gateways are available yet.',
            'hint' => 'Enable Stripe, WooPayments, COD, or another safe test gateway before checkout testing.',
        );
    }

    private static function stripe_plugin_status(): array {
        $file = 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php';
        if (TTOS_Plugin_Checker::is_active($file)) {
            return array(
                'status' => 'pass',
                'message' => 'WooCommerce Stripe Gateway is installed and active.',
                'hint' => '',
            );
        }
        if (TTOS_Plugin_Checker::is_installed($file)) {
            return array(
                'status' => 'warn',
                'message' => 'WooCommerce Stripe Gateway is installed but inactive.',
                'hint' => 'Activate Stripe before card-payment testing.',
            );
        }
        return array(
            'status' => 'warn',
            'message' => 'WooCommerce Stripe Gateway is not installed.',
            'hint' => 'Install Stripe before live card-payment setup.',
        );
    }

    private static function stripe_mode_status(): array {
        if (!TTOS_Plugin_Checker::is_active('woocommerce-gateway-stripe/woocommerce-gateway-stripe.php')) {
            return array(
                'status' => 'warn',
                'message' => 'Stripe mode cannot be checked until the Stripe gateway is active.',
                'hint' => 'Activate Stripe, then confirm test mode or live credentials in WooCommerce payment settings.',
            );
        }

        $settings = get_option('woocommerce_stripe_settings', array());
        if (!is_array($settings)) {
            $settings = array();
        }
        $enabled = ($settings['enabled'] ?? 'no') === 'yes';
        $testmode = ($settings['testmode'] ?? 'no') === 'yes';
        $has_credentials = !empty($settings['api_credentials']) || !empty($settings['publishable_key']) || !empty($settings['test_publishable_key']);

        if (!$enabled) {
            return array(
                'status' => 'warn',
                'message' => 'Stripe is active but disabled in WooCommerce. Mode detected: ' . ($testmode ? 'test mode' : 'live mode') . '.',
                'hint' => 'For beta, keep Stripe in test mode if testing cards. For paid production, enable and verify live card payments.',
            );
        }

        if (!$has_credentials) {
            return array(
                'status' => 'warn',
                'message' => 'Stripe is enabled but no credentials were detected.',
                'hint' => 'Connect Stripe or enter test keys before card-payment testing.',
            );
        }

        return array(
            'status' => 'pass',
            'message' => 'Stripe is enabled in ' . ($testmode ? 'test mode' : 'live mode') . '.',
            'hint' => $testmode ? 'Suitable for staging card tests. Live payments still need separate production verification.' : 'Live mode detected. Confirm real payment capture before paid production.',
        );
    }

    private static function manual_gateway_status(): array {
        if (!TTOS_WooCommerce::active() || !class_exists('WC_Payment_Gateways')) {
            return array(
                'status' => 'warn',
                'message' => 'Manual gateway status cannot be checked until WooCommerce is active.',
                'hint' => 'Activate WooCommerce first.',
            );
        }

        $manual = array();
        foreach (self::payment_gateways() as $gateway) {
            $id = isset($gateway->id) ? (string) $gateway->id : '';
            if (in_array($id, array('bacs', 'cod', 'cheque'), true) && isset($gateway->enabled) && $gateway->enabled === 'yes') {
                $manual[] = self::gateway_label($gateway);
            }
        }

        if ($manual) {
            return array(
                'status' => 'pass',
                'message' => 'Manual/test staging gateway enabled: ' . implode(', ', $manual) . '.',
                'hint' => '',
            );
        }

        return array(
            'status' => 'warn',
            'message' => 'No BACS/COD/cheque staging gateway is enabled.',
            'hint' => 'Keep a manual/test gateway available for staging orders without live card charges.',
        );
    }

    private static function payment_gateways(): array {
        if (!TTOS_WooCommerce::active() || !class_exists('WC_Payment_Gateways')) {
            return array();
        }
        return WC_Payment_Gateways::instance()->payment_gateways();
    }

    private static function gateway_label($gateway): string {
        if (is_object($gateway) && method_exists($gateway, 'get_title')) {
            $title = (string) $gateway->get_title();
            if ($title !== '') {
                return $title;
            }
        }
        return is_object($gateway) && isset($gateway->id) ? (string) $gateway->id : 'Unknown gateway';
    }

    private static function smtp_plugin_status(): array {
        $plugins = array(
            'fluent-smtp/fluent-smtp.php' => 'FluentSMTP',
            'wp-mail-smtp/wp_mail_smtp.php' => 'WP Mail SMTP',
            'post-smtp/postman-smtp.php' => 'Post SMTP',
        );
        $active = array();
        $installed = array();
        foreach ($plugins as $file => $label) {
            if (is_plugin_active($file)) {
                $active[] = $label;
                continue;
            }
            if (file_exists(WP_PLUGIN_DIR . '/' . $file)) {
                $installed[] = $label;
            }
        }

        if ($active) {
            return array(
                'status' => 'pass',
                'message' => 'Active email plugin: ' . implode(', ', $active) . '.',
                'hint' => '',
            );
        }
        if ($installed) {
            return array(
                'status' => 'warn',
                'message' => 'Email plugin installed but inactive: ' . implode(', ', $installed) . '.',
                'hint' => 'Activate and configure an SMTP/email delivery plugin before live orders.',
            );
        }

        return array(
            'status' => 'warn',
            'message' => 'No SMTP/email delivery plugin was detected.',
            'hint' => 'Recommended before staging or production email/order testing.',
        );
    }

    private static function email_delivery_status(): array {
        $active = self::active_smtp_plugins();
        if (!$active) {
            return array(
                'status' => 'warn',
                'message' => 'No active SMTP plugin was detected, so delivery cannot be trusted for beta handover.',
                'hint' => 'Configure FluentSMTP, WP Mail SMTP, or another transactional email plugin.',
            );
        }

        if (self::smtp_configured()) {
            return array(
                'status' => 'pass',
                'message' => 'SMTP plugin appears to have at least one saved connection/settings record.',
                'hint' => '',
            );
        }

        return array(
            'status' => 'warn',
            'message' => 'SMTP plugin active but no saved connection/settings record was detected.',
            'hint' => 'Configure SMTP credentials, then use the Setup Health email test.',
        );
    }

    private static function active_smtp_plugins(): array {
        $plugins = array(
            'fluent-smtp/fluent-smtp.php' => 'FluentSMTP',
            'wp-mail-smtp/wp_mail_smtp.php' => 'WP Mail SMTP',
            'post-smtp/postman-smtp.php' => 'Post SMTP',
        );
        $active = array();
        foreach ($plugins as $file => $label) {
            if (is_plugin_active($file)) {
                $active[] = $label;
            }
        }
        return $active;
    }

    private static function smtp_configured(): bool {
        foreach (array('fluentsmtp_connections','fluentmail-settings','wp_mail_smtp','postman_options') as $name) {
            $value = get_option($name, null);
            if (is_array($value) && !empty($value)) {
                return true;
            }
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }
        return false;
    }

    private static function woocommerce_order_email_status(): array {
        if (!TTOS_WooCommerce::active() || !function_exists('WC')) {
            return array(
                'status' => 'warn',
                'message' => 'WooCommerce order emails cannot be checked until WooCommerce is active.',
                'hint' => 'Activate WooCommerce first.',
            );
        }

        $mailer = WC()->mailer();
        $emails = $mailer ? $mailer->get_emails() : array();
        $new_order = $emails['WC_Email_New_Order'] ?? null;
        if ($new_order && method_exists($new_order, 'is_enabled') && $new_order->is_enabled()) {
            return array(
                'status' => 'pass',
                'message' => 'WooCommerce new-order admin email is available and enabled.',
                'hint' => '',
            );
        }

        return array(
            'status' => 'warn',
            'message' => 'WooCommerce new-order admin email is unavailable or disabled.',
            'hint' => 'Check WooCommerce email settings before beta handover.',
        );
    }

    private static function admin_email_status(): array {
        $email = sanitize_email((string) get_option('admin_email'));
        if ($email && is_email($email)) {
            return array(
                'status' => 'pass',
                'message' => 'Admin email is set to ' . $email . '.',
                'hint' => '',
            );
        }

        return array(
            'status' => 'warn',
            'message' => 'WordPress admin email is missing or invalid.',
            'hint' => 'Set a monitored admin email before order testing.',
        );
    }

    private static function email_sender_status(): array {
        $from_email = sanitize_email((string) get_option('woocommerce_email_from_address'));
        $from_name = trim((string) get_option('woocommerce_email_from_name'));
        if ($from_email && is_email($from_email) && $from_name !== '') {
            return array(
                'status' => 'pass',
                'message' => 'WooCommerce emails send as "' . $from_name . '" <' . $from_email . '>.',
                'hint' => '',
            );
        }

        return array(
            'status' => 'warn',
            'message' => 'WooCommerce email from-name or from-address is missing/invalid.',
            'hint' => 'Set a recognizable restaurant sender before live order emails.',
        );
    }

    private static function handover_role_status(): array {
        $problems = array();
        $admin = get_role('administrator');
        $owner = get_role('takeaway_owner');
        $manager = get_role('takeaway_manager');
        $kitchen = get_role('takeaway_kitchen');

        if (!$admin || !$admin->has_cap('manage_options') || !$admin->has_cap('ttos_modules')) {
            $problems[] = 'administrator lacks full Takeaway/admin access';
        }
        if (!$owner || !$owner->has_cap('ttos_access') || !$owner->has_cap('ttos_manage_settings') || $owner->has_cap('ttos_modules') || $owner->has_cap('manage_options') || $owner->has_cap('delete_plugins')) {
            $problems[] = 'owner role is not constrained correctly';
        }
        if (!$manager || !$manager->has_cap('ttos_access') || !$manager->has_cap('ttos_manage_menu') || $manager->has_cap('ttos_manage_settings') || $manager->has_cap('ttos_modules') || $manager->has_cap('manage_options')) {
            $problems[] = 'manager role is not constrained correctly';
        }
        if (!$kitchen || !$kitchen->has_cap('ttos_view_orders') || !$kitchen->has_cap('ttos_update_orders') || $kitchen->has_cap('ttos_manage_menu') || $kitchen->has_cap('ttos_manage_settings') || $kitchen->has_cap('ttos_view_reports') || $kitchen->has_cap('ttos_modules')) {
            $problems[] = 'kitchen role is not constrained correctly';
        }

        if (!$problems) {
            return array(
                'status' => 'pass',
                'message' => 'Administrator, owner, manager, and kitchen capabilities match the handover model.',
                'hint' => '',
            );
        }

        return array(
            'status' => 'fail',
            'message' => implode('; ', $problems) . '.',
            'hint' => 'Load wp-admin once after update so Takeaway OS can sync role capabilities.',
        );
    }

    /**
     * Calculates the WCAG 2.1 contrast ratio between two hex colours.
     * Returns a value like 4.52 (rounded to 2 decimal places).
     */
    private static function contrast_ratio(string $hex1, string $hex2): float {
        $lum = static function(string $hex): float {
            $hex = ltrim($hex, '#');
            if (strlen($hex) !== 6) return 0.0;
            $r = hexdec(substr($hex, 0, 2)) / 255;
            $g = hexdec(substr($hex, 2, 2)) / 255;
            $b = hexdec(substr($hex, 4, 2)) / 255;
            $lin = static function(float $c): float {
                return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            };
            return 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
        };
        $l1 = $lum($hex1);
        $l2 = $lum($hex2);
        if ($l1 < $l2) [$l1, $l2] = [$l2, $l1];
        return round(($l1 + 0.05) / ($l2 + 0.05), 2);
    }

    /**
     * WCAG 2.1 AA contrast checks for the colours stored in Branding settings.
     * AA body text requires 4.5:1; large text / UI components need 3:1.
     */
    private static function a11y_brand_checks(): array {
        $checks = array();

        if (!class_exists('TTOS_Settings') || !method_exists('TTOS_Settings', 'brand_tokens')) {
            return $checks;
        }

        $t = TTOS_Settings::brand_tokens();

        // CHECK 1 — brand_primary_on_white
        $ratio = self::contrast_ratio($t['primary'], '#ffffff');
        if ($ratio >= 4.5) {
            $checks[] = array(
                'id'      => 'brand_primary_on_white',
                'status'  => 'pass',
                'label'   => 'Brand colour passes text contrast on white — ' . $ratio . ':1',
                'message' => 'The primary brand colour meets WCAG AA for normal text on a white background.',
                'hint'    => '',
            );
        } elseif ($ratio >= 3.0) {
            $checks[] = array(
                'id'      => 'brand_primary_on_white',
                'status'  => 'warn',
                'label'   => 'Brand colour is marginal for small text — ' . $ratio . ':1 (need 4.5:1 for body copy, 3:1 for headings ≥18px/bold)',
                'message' => 'The primary brand colour meets the large-text threshold (3:1) but falls short of AA for normal body text (4.5:1) on white.',
                'hint'    => 'Darken the primary colour in Branding settings if it is used for body text or prices.',
            );
        } else {
            $checks[] = array(
                'id'          => 'brand_primary_on_white',
                'status'      => 'fail',
                'label'       => 'Brand colour fails WCAG AA on white — ' . $ratio . ':1',
                'message'     => 'Small text and prices in your brand colour are illegible for users with low vision. Darken the primary colour in Branding settings. Target: ≥4.5:1.',
                'hint'        => '',
            );
        }

        // CHECK 2 — brand_primary_on_bg
        $ratio = self::contrast_ratio($t['primary'], $t['bg']);
        if ($ratio >= 4.5) {
            $checks[] = array(
                'id'      => 'brand_primary_on_bg',
                'status'  => 'pass',
                'label'   => 'Brand colour passes text contrast on site background — ' . $ratio . ':1',
                'message' => 'The primary brand colour meets WCAG AA for normal text on the site background.',
                'hint'    => '',
            );
        } elseif ($ratio >= 3.0) {
            $checks[] = array(
                'id'      => 'brand_primary_on_bg',
                'status'  => 'warn',
                'label'   => 'Brand colour is marginal for small text on site background — ' . $ratio . ':1 (need 4.5:1 for body copy, 3:1 for headings ≥18px/bold)',
                'message' => 'The primary brand colour meets the large-text threshold (3:1) but falls short of AA for normal body text (4.5:1) on the site background.',
                'hint'    => 'Darken the primary colour in Branding settings if it is used for body text or prices on the background.',
            );
        } else {
            $checks[] = array(
                'id'      => 'brand_primary_on_bg',
                'status'  => 'fail',
                'label'   => 'Brand colour fails WCAG AA on site background — ' . $ratio . ':1',
                'message' => 'Small text and prices in your brand colour are illegible for users with low vision. Darken the primary colour in Branding settings. Target: ≥4.5:1.',
                'hint'    => '',
            );
        }

        // CHECK 3 — brand_cta_white_text (white text on primary-coloured buttons)
        $ratio = self::contrast_ratio('#ffffff', $t['primary']);
        if ($ratio >= 4.5) {
            $checks[] = array(
                'id'      => 'brand_cta_white_text',
                'status'  => 'pass',
                'label'   => 'White text on brand-colour buttons passes WCAG AA — ' . $ratio . ':1',
                'message' => 'White button labels on the primary brand colour meet the AA contrast requirement.',
                'hint'    => '',
            );
        } elseif ($ratio >= 3.0) {
            $checks[] = array(
                'id'      => 'brand_cta_white_text',
                'status'  => 'warn',
                'label'   => 'White text on brand buttons passes large-text threshold (' . $ratio . ':1) but fails for small text',
                'message' => 'White text on primary-coloured buttons meets 3:1 for large text but not 4.5:1 for small labels. Use a darker brand colour or switch button text to dark.',
                'hint'    => 'Darken the primary colour in Branding settings or use dark text on the button.',
            );
        } else {
            $checks[] = array(
                'id'      => 'brand_cta_white_text',
                'status'  => 'fail',
                'label'   => 'White text on brand-colour buttons fails contrast — ' . $ratio . ':1',
                'message' => 'White button labels on the primary brand colour are illegible for many users. Darken the primary colour or use dark text on buttons.',
                'hint'    => 'Darken the primary colour in Branding settings or switch to dark button text.',
            );
        }

        // CHECK 4 — brand_success_color
        $ratio = self::contrast_ratio($t['success'], '#ffffff');
        if ($ratio >= 4.5) {
            $checks[] = array(
                'id'      => 'brand_success_color',
                'status'  => 'pass',
                'label'   => 'Success colour passes WCAG AA on white — ' . $ratio . ':1',
                'message' => 'The success colour meets AA contrast for normal text on white.',
                'hint'    => '',
            );
        } elseif ($ratio >= 3.0) {
            $checks[] = array(
                'id'      => 'brand_success_color',
                'status'  => 'warn',
                'label'   => 'Success colour is marginal on white — ' . $ratio . ':1 (need 4.5:1 for body copy, 3:1 for headings ≥18px/bold)',
                'message' => 'The success colour meets the large-text threshold but not AA for small text on white.',
                'hint'    => 'Consider darkening the success colour in Branding settings.',
            );
        } else {
            $checks[] = array(
                'id'      => 'brand_success_color',
                'status'  => 'fail',
                'label'   => 'Success colour fails WCAG AA on white — ' . $ratio . ':1',
                'message' => 'Success/confirmation messages in this colour are hard to read. Darken the success colour in Branding settings.',
                'hint'    => 'Darken the success colour in Branding settings. Target: ≥4.5:1.',
            );
        }

        // CHECK 5 — brand_warning_color
        $ratio = self::contrast_ratio($t['warning'], '#ffffff');
        if ($ratio >= 4.5) {
            $checks[] = array(
                'id'      => 'brand_warning_color',
                'status'  => 'pass',
                'label'   => 'Warning colour passes WCAG AA on white — ' . $ratio . ':1',
                'message' => 'The warning colour meets AA contrast for normal text on white.',
                'hint'    => '',
            );
        } elseif ($ratio >= 3.0) {
            $checks[] = array(
                'id'      => 'brand_warning_color',
                'status'  => 'warn',
                'label'   => 'Warning colour is marginal on white — ' . $ratio . ':1 (need 4.5:1 for body copy, 3:1 for headings ≥18px/bold)',
                'message' => 'The warning colour meets the large-text threshold but not AA for small text on white.',
                'hint'    => 'Consider darkening the warning colour in Branding settings.',
            );
        } else {
            $checks[] = array(
                'id'      => 'brand_warning_color',
                'status'  => 'fail',
                'label'   => 'Warning colour fails WCAG AA on white — ' . $ratio . ':1',
                'message' => 'Success/confirmation messages in this colour are hard to read. Darken the warning colour in Branding settings.',
                'hint'    => 'Darken the warning colour in Branding settings. Target: ≥4.5:1.',
            );
        }

        // CHECK 6 — brand_input_border (UI components need 3:1 per WCAG 1.4.11)
        $ratio = self::contrast_ratio($t['border'], $t['bg']);
        if ($ratio >= 3.0) {
            $checks[] = array(
                'id'      => 'brand_input_border',
                'status'  => 'pass',
                'label'   => 'Form input borders meet WCAG 1.4.11 against page background — ' . $ratio . ':1',
                'message' => 'Input, select, and stepper boundaries have sufficient contrast against the background.',
                'hint'    => '',
            );
        } else {
            $checks[] = array(
                'id'      => 'brand_input_border',
                'status'  => 'fail',
                'label'   => 'Form input borders are too faint against the page background — ' . $ratio . ':1',
                'message' => 'Input, select, and stepper boundaries need 3:1 contrast. Darken the Border colour in Branding settings, or check the border-input token in your theme.',
                'hint'    => 'Darken the Border colour in Branding settings. Target: ≥3:1 (WCAG 1.4.11 non-text contrast).',
            );
        }

        return $checks;
    }

    private static function theme_active(): bool {
        $theme = wp_get_theme();
        $name = strtolower((string) $theme->get('Name'));
        return strpos($name, 'takeaway') !== false || $theme->get_stylesheet() === 'takeaway-theme' || $theme->get_template() === 'takeaway-theme';
    }

    private static function basic_site_ready(array $checks): bool {
        $required_ids = array(
            'ttos_active',
            'woocommerce_active',
            'home_exists',
            'home_assigned',
            'menu_exists',
            'menu_assigned',
            'cart_exists',
            'cart_assigned',
            'checkout_exists',
            'checkout_assigned',
            'account_exists',
            'account_assigned',
            'primary_menu_exists',
            'primary_menu_assigned',
            'footer_menu_exists',
            'footer_menu_assigned',
            'theme_active',
        );

        foreach ($checks as $check) {
            if (in_array($check['id'], $required_ids, true) && $check['status'] === 'fail') {
                return false;
            }
        }

        return true;
    }
}

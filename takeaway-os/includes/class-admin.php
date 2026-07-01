<?php

defined('ABSPATH') || exit;

final class TTOS_Admin {
    public static function hooks(): void {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_menu', array(__CLASS__, 'order_submenu'), 999);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('admin_init', array(__CLASS__, 'activation_redirect'), 1);
        add_action('admin_init', array(__CLASS__, 'handle_posts'));
        add_action('admin_init', array(__CLASS__, 'crm_mode_redirect'));
        add_action('admin_bar_menu', array(__CLASS__, 'admin_bar'), 100);
        add_action('wp_ajax_ttos_order_board', array(__CLASS__, 'ajax_order_board'));
        add_action('wp_ajax_ttos_order_action', array(__CLASS__, 'ajax_order_action'));
        add_action('admin_post_ttos_export_customer_profiles_csv', array(__CLASS__, 'export_customer_profiles_csv'));
        add_action('in_admin_header', array(__CLASS__, 'suppress_foreign_notices'), 1000);
        add_action('admin_head', array(__CLASS__, 'suppress_foreign_notices_css'));
    }

    /**
     * Hide third-party admin notices on Takeaway OS screens only. The owner
     * dashboard should not be buried under SEO/host/SMTP banners. Notices
     * still show everywhere else in wp-admin; Takeaway OS renders its own
     * notices inside the shell, not via admin_notices.
     */
    public static function suppress_foreign_notices(): void {
        if (!function_exists('get_current_screen')) return;
        $screen = get_current_screen();
        if (!$screen || strpos((string) $screen->id, 'takeaway-os') === false) return;
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
    }

    /**
     * CSS-layer notice suppression — belt-and-braces guard for notices that
     * register after in_admin_header fires. Scoped to Takeaway OS screens only.
     */
    public static function suppress_foreign_notices_css(): void {
        if (!function_exists('get_current_screen')) return;
        $screen = get_current_screen();
        if (!$screen) return;
        $id   = (string) $screen->id;
        $base = (string) $screen->base;
        if (strpos($id, 'ttos') === false && strpos($id, 'takeaway') === false && strpos($base, 'takeaway') === false) return;
        echo '<style>.notice:not(.ttos-notice),.update-nag,.updated:not(.ttos-updated),.notice-warning:not(.ttos-notice),.notice-error:not(.ttos-notice){display:none!important;}</style>';
    }

    public static function menu(): void {
        add_menu_page('Takeaway OS', 'Takeaway OS', 'ttos_access', 'takeaway-os', array(__CLASS__, 'page_dashboard'), 'dashicons-store', 3);
        add_submenu_page('takeaway-os', 'Launchpad', 'Launchpad', 'ttos_manage_settings', 'takeaway-os-launchpad', array(__CLASS__, 'page_launchpad'));
        add_submenu_page('takeaway-os', 'Menu Builder', 'Menu', 'ttos_manage_menu', 'takeaway-os-menu', array(__CLASS__, 'page_menu_builder'));
        add_submenu_page('takeaway-os', 'Orders', 'Orders', 'ttos_view_orders', 'takeaway-os-orders', array(__CLASS__, 'page_orders'));
        add_submenu_page('takeaway-os', 'Takeaway Tickets', 'Takeaway Tickets', 'ttos_view_orders', 'takeaway-os-kitchen', array(__CLASS__, 'page_kitchen'));
        add_submenu_page('takeaway-os', 'Customers / CRM', 'Customers / CRM', 'ttos_view_reports', 'takeaway-os-customers', array(__CLASS__, 'page_customers'));
        add_submenu_page('takeaway-os', 'Reports', 'Reports', 'ttos_view_reports', 'takeaway-os-reports', array(__CLASS__, 'page_reports'));
        add_submenu_page('takeaway-os', 'Settings / Branding', 'Settings / Branding', 'ttos_manage_settings', 'takeaway-os-settings', array(__CLASS__, 'page_settings'));
        add_submenu_page('takeaway-os', 'Payments', 'Payments', 'ttos_manage_settings', 'takeaway-os-payments', array(__CLASS__, 'page_payments'));
        add_submenu_page('takeaway-os', 'Delivery', 'Delivery', 'ttos_manage_settings', 'takeaway-os-delivery', array(__CLASS__, 'page_delivery'));
        add_submenu_page('takeaway-os', 'Add-ons', 'Add-ons', 'ttos_modules', 'takeaway-os-modules', array(__CLASS__, 'page_modules'));
    }

    public static function order_submenu(): void {
        global $submenu;
        if (empty($submenu['takeaway-os']) || !is_array($submenu['takeaway-os'])) {
            return;
        }

        $desired = array(
            'takeaway-os',
            'takeaway-os-launchpad',
            'takeaway-os-setup-health',
            'takeaway-os-menu',
            'takeaway-os-orders',
            'takeaway-os-kitchen',
            'takeaway-os-customers',
            'takeaway-os-reports',
            'takeaway-os-site-content',
            'takeaway-os-client-intake',
            'takeaway-os-delivery',
            'takeaway-os-payments',
            'takeaway-os-settings',
            'takeaway-os-modules',
            'takeaway-os-features',
            'takeaway-os-operations',
            'takeaway-os-golive',
            'takeaway-os-production',
        );

        $existing = array();
        foreach ($submenu['takeaway-os'] as $item) {
            if (!empty($item[2])) {
                $existing[$item[2]] = $item;
            }
        }

        $ordered = array();
        foreach ($desired as $slug) {
            if (isset($existing[$slug])) {
                $ordered[] = $existing[$slug];
                unset($existing[$slug]);
            }
        }
        foreach ($existing as $item) {
            $ordered[] = $item;
        }
        $submenu['takeaway-os'] = $ordered;
    }

    public static function assets(string $hook): void {
        if (strpos($hook, 'takeaway-os') === false) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style('ttos-admin', TTOS_URL . 'assets/admin.css', array(), TTOS_VERSION . '-admin-shell-4');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('ttos-admin', TTOS_URL . 'assets/admin.js', array('jquery', 'jquery-ui-sortable'), TTOS_VERSION . '-admin-shell-3', true);
        wp_localize_script('ttos-admin', 'TTOSInstaller', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ttos_plugin_installer'),
            'plugins' => TTOS_Plugin_Checker::installer_payload(),
            'reloadUrl' => admin_url('admin.php?page=takeaway-os-launchpad'),
            'orderBoardNonce' => wp_create_nonce('ttos_order_board'),
            'orderActionNonce' => wp_create_nonce('ttos_order_action'),
            'orderAlertText' => 'New takeaway order received',
        ));
    }


    public static function activation_redirect(): void {
        if (!get_option('ttos_do_activation_redirect')) {
            return;
        }
        delete_option('ttos_do_activation_redirect');
        if (wp_doing_ajax() || is_network_admin() || !current_user_can('ttos_manage_settings')) {
            return;
        }
        if (isset($_GET['activate-multi'])) {
            return;
        }
        wp_safe_redirect(admin_url('admin.php?page=takeaway-os-launchpad'));
        exit;
    }

    public static function crm_mode_redirect(): void {
        if (!is_admin() || wp_doing_ajax() || current_user_can('manage_options') || !current_user_can('ttos_access')) {
            return;
        }
        global $pagenow;
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        $allowed_pages = array('takeaway-os','takeaway-os-launchpad','takeaway-os-setup-health','takeaway-os-menu','takeaway-os-orders','takeaway-os-kitchen','takeaway-os-customers','takeaway-os-reports','takeaway-os-site-content','takeaway-os-client-intake','takeaway-os-settings','takeaway-os-payments','takeaway-os-delivery','takeaway-os-features','takeaway-os-operations','takeaway-os-golive','takeaway-os-modules','takeaway-os-production');
        if ($pagenow === 'admin.php' && in_array($page, $allowed_pages, true)) {
            return;
        }
        wp_safe_redirect(admin_url('admin.php?page=takeaway-os'));
        exit;
    }

    public static function admin_bar($bar): void {
        if (current_user_can('ttos_access')) {
            $bar->add_node(array('id' => 'ttos-dashboard', 'title' => 'Takeaway OS', 'href' => admin_url('admin.php?page=takeaway-os')));
        }
    }

    public static function handle_posts(): void {
        if (!is_admin() || empty($_POST['ttos_action'])) {
            return;
        }
        if (!current_user_can('ttos_access')) {
            wp_die('You do not have permission to manage Takeaway OS.');
        }
        $action = sanitize_key(wp_unslash($_POST['ttos_action']));
        check_admin_referer('ttos_' . $action);

        if ($action === 'save_business' && current_user_can('ttos_manage_settings')) {
            $business = array_map('sanitize_text_field', wp_unslash($_POST['business'] ?? array()));
            TTOS_Settings::update_section('business', $business);
            TTOS_Settings::sync_business_runtime($business);
            TTOS_Settings::sync_business_to_site_content($business);
            self::redirect_notice('settings-saved');
        }

        if ($action === 'save_branding' && current_user_can('ttos_manage_settings')) {
            $raw = wp_unslash($_POST['branding'] ?? array());
            if (!is_array($raw)) $raw = array();
            // Merge over the currently saved section so keys the form did not
            // post (including legacy secondary/dark/cream) are preserved
            // instead of falling back to defaults.
            $branding = TTOS_Settings::get('branding');
            foreach (array('logo_id', 'favicon_id', 'hero_image_id') as $key) {
                if (array_key_exists($key, $raw)) $branding[$key] = absint($raw[$key]);
            }
            foreach (array('primary','accent','bg','surface','surface_soft','text','muted','border','success','warning','error','secondary','dark','cream') as $key) {
                if (!array_key_exists($key, $raw)) continue;
                $hex = sanitize_hex_color((string) $raw[$key]);
                if ($hex) $branding[$key] = $hex; // invalid colours keep the previous value
            }
            foreach (array('radius_sm', 'radius_md', 'radius_lg') as $key) {
                if (!array_key_exists($key, $raw)) continue;
                $branding[$key] = (string) min(60, absint($raw[$key]));
            }
            $choices = array(
                'shadow'       => array('none', 'soft', 'strong'),
                'default_mode' => array('light', 'dark', 'system'),
                'header_style' => array('solid', 'transparent'),
                'hero_style'   => array('angled', 'minimal', 'photo'),
                'card_style'   => array('soft', 'outlined', 'flat'),
                'footer_style' => array('dark', 'light'),
                'style_skin'   => array('charcoal', 'burger', 'pizza', 'clean'),
            );
            foreach ($choices as $key => $allowed) {
                if (!array_key_exists($key, $raw)) continue;
                $value = sanitize_key((string) $raw[$key]);
                if (in_array($value, $allowed, true)) $branding[$key] = $value;
            }
            // Header visibility toggles (checkboxes — absent from POST when unchecked).
            foreach (array('show_header_phone', 'show_header_email', 'show_header_socials') as $key) {
                $branding[$key] = !empty($raw[$key]) ? '1' : '0';
            }
            // Font overrides — strip to safe CSS font-family characters.
            foreach (array('font_heading', 'font_body') as $key) {
                if (!array_key_exists($key, $raw)) continue;
                $branding[$key] = preg_replace('/[^a-zA-Z0-9 ,\-\'"]+/', '', (string) $raw[$key]);
            }
            TTOS_Settings::update_section('branding', $branding);
            self::redirect_notice('branding-saved');
        }

        if ($action === 'save_trading' && current_user_can('ttos_manage_settings')) {
            $raw = wp_unslash($_POST['trading'] ?? array());
            if (!is_array($raw)) { $raw = array(); }
            // Monetary/numeric fields require scalar values — reject nested arrays and non-numeric input.
            $numeric_fields = array('min_order', 'delivery_fee', 'free_delivery_over', 'delivery_radius', 'prep_time', 'delivery_time', 'service_charge');
            $trading = array();
            foreach ($raw as $key => $value) {
                if (!is_scalar($value)) continue;
                if (in_array($key, $numeric_fields, true)) {
                    if (!is_numeric($value)) continue;
                    $trading[$key] = (string) abs((float) $value);
                } else {
                    $trading[$key] = sanitize_text_field($value);
                }
            }
            $trading['collection_enabled'] = !empty($raw['collection_enabled']) ? '1' : '0';
            $trading['delivery_enabled'] = !empty($raw['delivery_enabled']) ? '1' : '0';
            TTOS_Settings::update_section('trading', $trading);
            self::redirect_notice('delivery-saved');
        }

        if ($action === 'save_data_retention' && current_user_can('manage_options')) {
            $raw = wp_unslash($_POST['data_retention'] ?? array());
            TTOS_Settings::update_section('data_retention', array(
                'erase_on_uninstall'       => !empty($raw['erase_on_uninstall']) ? '1' : '0',
                'delete_generated_pages'   => !empty($raw['delete_generated_pages']) ? '1' : '0',
                'delete_menu_products'     => !empty($raw['delete_menu_products']) ? '1' : '0',
                'delete_generated_coupons' => !empty($raw['delete_generated_coupons']) ? '1' : '0',
                'delete_customer_meta'     => !empty($raw['delete_customer_meta']) ? '1' : '0',
                'delete_roles'             => !empty($raw['delete_roles']) ? '1' : '0',
            ));
            self::redirect_notice('data-retention-saved', 'takeaway-os-settings');
        }

        if ($action === 'generate_pages' && current_user_can('ttos_manage_settings')) {
            TTOS_Page_Manager::ensure_all('fresh_if_unsafe');
            TTOS_Page_Manager::sync_woocommerce_page_options();
            self::redirect_notice('pages-generated');
        }

        if ($action === 'create_starter_menu' && current_user_can('ttos_manage_menu')) {
            if (TTOS_WooCommerce::active() && class_exists('TTOS_Production')) {
                TTOS_Production::apply_starter_menu();
            }
            self::redirect_notice('starter-menu-created');
        }

        if ($action === 'apply_woocommerce_profile' && current_user_can('ttos_manage_settings')) {
            if (TTOS_WooCommerce::active()) {
                TTOS_Onboarding::apply_profile();
                self::redirect_notice('profile-applied', 'takeaway-os-launchpad');
            }
            self::redirect_notice('woocommerce-required', 'takeaway-os-launchpad');
        }

        if ($action === 'save_service_links' && current_user_can('ttos_modules')) {
            $raw = wp_unslash($_POST['service_links'] ?? array());
            $links = array();
            foreach (TTOS_Settings::defaults()['service_links'] as $key => $default) {
                $links[$key] = esc_url_raw($raw[$key] ?? $default);
            }
            TTOS_Settings::update_section('service_links', $links);
            self::redirect_notice('service-links-saved', 'takeaway-os-modules','takeaway-os-production');
        }

        if ($action === 'save_category' && current_user_can('ttos_manage_menu')) {
            if (taxonomy_exists('product_cat')) {
                $raw = wp_unslash($_POST['category'] ?? array());
                self::save_menu_category($raw);
            }
            self::redirect_notice('category-saved', 'takeaway-os-menu');
        }

        if ($action === 'quick_product_status' && current_user_can('ttos_manage_menu')) {
            $product_id = absint(wp_unslash($_POST['product_id'] ?? 0));
            $status = sanitize_key(wp_unslash($_POST['quick_status'] ?? ''));
            if ($product_id && get_post_type($product_id) === 'product') {
                if ($status === 'soldout') update_post_meta($product_id, '_stock_status', 'outofstock');
                if ($status === 'available') update_post_meta($product_id, '_stock_status', 'instock');
                if ($status === 'hide') wp_update_post(array('ID' => $product_id, 'post_status' => 'draft'));
                if ($status === 'show') wp_update_post(array('ID' => $product_id, 'post_status' => 'publish'));
            }
            self::redirect_notice('menu-saved', 'takeaway-os-menu');
        }


        if ($action === 'duplicate_product' && current_user_can('ttos_manage_menu')) {
            $product_id = absint(wp_unslash($_POST['product_id'] ?? 0));
            if ($product_id && get_post_type($product_id) === 'product') {
                TTOS_WooCommerce::duplicate_product($product_id);
            }
            self::redirect_notice('menu-saved', 'takeaway-os-menu');
        }

        if ($action === 'bulk_menu_action' && current_user_can('ttos_manage_menu')) {
            $ids_raw = sanitize_text_field(wp_unslash($_POST['product_ids'] ?? ''));
            $ids = array_filter(array_map('absint', explode(',', $ids_raw)));
            $bulk = sanitize_key(wp_unslash($_POST['bulk_action'] ?? ''));
            foreach ($ids as $product_id) {
                if (get_post_type($product_id) !== 'product') continue;
                if ($bulk === 'available') update_post_meta($product_id, '_stock_status', 'instock');
                if ($bulk === 'soldout') update_post_meta($product_id, '_stock_status', 'outofstock');
                if ($bulk === 'hide') wp_update_post(array('ID' => $product_id, 'post_status' => 'draft'));
                if ($bulk === 'show') wp_update_post(array('ID' => $product_id, 'post_status' => 'publish'));
                if ($bulk === 'feature') update_post_meta($product_id, '_featured', 'yes');
                if ($bulk === 'unfeature') update_post_meta($product_id, '_featured', 'no');
            }
            self::redirect_notice('menu-saved', 'takeaway-os-menu');
        }

        if ($action === 'save_product_order' && current_user_can('ttos_manage_menu')) {
            $raw = sanitize_text_field(wp_unslash($_POST['product_order'] ?? ''));
            $pairs = array_filter(explode(',', $raw));
            foreach ($pairs as $pair) {
                $parts = array_map('trim', explode(':', $pair));
                if (count($parts) !== 2) continue;
                $product_id = absint($parts[0]);
                $order = (int) $parts[1];
                if ($product_id && get_post_type($product_id) === 'product') {
                    wp_update_post(array('ID' => $product_id, 'menu_order' => $order));
                }
            }
            self::redirect_notice('menu-saved', 'takeaway-os-menu');
        }

        if ($action === 'save_category_order' && current_user_can('ttos_manage_menu')) {
            $raw = sanitize_text_field(wp_unslash($_POST['category_order'] ?? ''));
            $pairs = array_filter(explode(',', $raw));
            foreach ($pairs as $pair) {
                $parts = array_map('trim', explode(':', $pair));
                if (count($parts) !== 2) continue;
                $term_id = absint($parts[0]);
                $order = (int) $parts[1];
                if ($term_id) update_term_meta($term_id, 'order', $order);
            }
            self::redirect_notice('category-saved', 'takeaway-os-menu');
        }

        if ($action === 'unlock_modules' && current_user_can('ttos_modules')) {
            $key = sanitize_text_field(wp_unslash($_POST['module_key'] ?? ''));
            if (self::module_key_valid($key)) {
                set_transient(self::module_unlock_transient(), '1', HOUR_IN_SECONDS * 2);
                self::redirect_notice('modules-unlocked', 'takeaway-os-modules','takeaway-os-production');
            }
            self::redirect_notice('modules-locked', 'takeaway-os-modules','takeaway-os-production');
        }

        if ($action === 'lock_modules' && current_user_can('ttos_modules')) {
            delete_transient(self::module_unlock_transient());
            self::redirect_notice('modules-locked', 'takeaway-os-modules','takeaway-os-production');
        }

        if ($action === 'save_module_lock' && current_user_can('ttos_modules')) {
            $key = trim(sanitize_text_field(wp_unslash($_POST['module_key_new'] ?? '')));
            if ($key !== '') {
                update_option('ttos_module_lock_hash', wp_hash_password($key), false);
                set_transient(self::module_unlock_transient(), '1', HOUR_IN_SECONDS * 2);
            }
            self::redirect_notice('module-lock-saved', 'takeaway-os-modules','takeaway-os-production');
        }

        if ($action === 'save_modules' && current_user_can('ttos_modules')) {
            if (!self::modules_unlocked()) {
                self::redirect_notice('modules-locked', 'takeaway-os-modules','takeaway-os-production');
            }
            $modules = array();
            $catalog = self::module_catalog();
            foreach (TTOS_Settings::defaults()['modules'] as $slug => $default) {
                $toggleable = !empty($catalog[$slug]['toggleable']);
                $modules[$slug] = $toggleable ? !empty($_POST['modules'][$slug]) : false;
            }
            TTOS_Settings::update_section('modules', $modules);
            self::redirect_notice('modules-saved');
        }

        if ($action === 'save_product' && current_user_can('ttos_manage_menu')) {
            if (!TTOS_WooCommerce::active()) {
                self::redirect_notice('woocommerce-required');
            }
            $data = wp_unslash($_POST['product'] ?? array());
            TTOS_WooCommerce::create_or_update_product($data);
            self::redirect_notice('menu-saved', 'takeaway-os-menu');
        }

        if ($action === 'update_order_status' && current_user_can('ttos_update_orders')) {
            $order_id = absint(wp_unslash($_POST['order_id'] ?? 0));
            $status = sanitize_key(wp_unslash($_POST['status'] ?? ''));
            $prep = absint(wp_unslash($_POST['prep_minutes'] ?? 0));
            $note = sanitize_textarea_field(wp_unslash($_POST['kitchen_note'] ?? ''));
            $resend = !empty($_POST['resend_integrations']);
            if ($order_id && TTOS_WooCommerce::active()) {
                self::apply_order_action($order_id, $status, $prep, $note, $resend);
            }
            self::redirect_notice('order-updated', 'takeaway-os-orders');
        }


        if ($action === 'save_customer_profile' && current_user_can('ttos_view_reports')) {
            $email = sanitize_email(wp_unslash($_POST['customer_email'] ?? ''));
            if ($email) {
                $profiles = self::customer_profiles();
                $key = strtolower($email);
                $existing = is_array($profiles[$key] ?? null) ? $profiles[$key] : array();
                $profiles[$key] = array(
                    'name'           => sanitize_text_field(wp_unslash($_POST['customer_name'] ?? ($existing['name'] ?? ''))),
                    'phone'          => sanitize_text_field(wp_unslash($_POST['customer_phone'] ?? ($existing['phone'] ?? ''))),
                    'postcode'       => sanitize_text_field(wp_unslash($_POST['customer_postcode'] ?? ($existing['postcode'] ?? ''))),
                    'tags'           => sanitize_text_field(wp_unslash($_POST['customer_tags'] ?? '')),
                    'internal_notes' => sanitize_textarea_field(wp_unslash($_POST['customer_notes'] ?? '')),
                    'marketing_ok'   => !empty($_POST['marketing_ok']) ? '1' : '0',
                    'birthday'       => sanitize_text_field(wp_unslash($_POST['birthday'] ?? '')),
                    'updated'        => time(),
                );
                update_option('ttos_customer_profiles', $profiles, false);
            }
            wp_safe_redirect(add_query_arg(array('page' => 'takeaway-os-customers', 'customer' => rawurlencode($email), 'ttos_notice' => 'customer-saved'), admin_url('admin.php')));
            exit;
        }

        if ($action === 'import_customer_profiles' && current_user_can('ttos_view_reports')) {
            $result = self::import_customer_profiles();
            if (is_wp_error($result)) {
                self::flash_notice('error', $result->get_error_message());
            } else {
                self::flash_notice(
                    !empty($result['warnings']) ? 'warning' : 'success',
                    sprintf(
                        __('Imported %1$d customer profile(s): %2$d created, %3$d updated, %4$d skipped.', 'takeaway-os'),
                        (int) $result['processed'],
                        (int) $result['created'],
                        (int) $result['updated'],
                        (int) $result['skipped']
                    )
                );
            }
            self::redirect_notice('customer-imported', 'takeaway-os-customers');
        }

        if ($action === 'adjust_customer_loyalty' && current_user_can('ttos_view_reports')) {
            $email = sanitize_email(wp_unslash($_POST['customer_email'] ?? ''));
            $points = (int) wp_unslash($_POST['points'] ?? 0);
            $note = sanitize_text_field(wp_unslash($_POST['points_note'] ?? 'Manual CRM adjustment'));
            if ($email && $points !== 0) {
                if (class_exists('TTOS_Features') && method_exists('TTOS_Features', 'manual_loyalty_adjustment')) {
                    TTOS_Features::manual_loyalty_adjustment($email, $points, $note);
                } else {
                    $ledger = get_option('ttos_loyalty_ledger', array());
                    if (!is_array($ledger)) $ledger = array();
                    $ledger[] = array('email' => strtolower($email), 'points' => $points, 'note' => $note, 'time' => time());
                    update_option('ttos_loyalty_ledger', array_slice($ledger, -1500), false);
                }
            }
            wp_safe_redirect(add_query_arg(array('page' => 'takeaway-os-customers', 'customer' => rawurlencode($email), 'ttos_notice' => 'loyalty-adjusted'), admin_url('admin.php')));
            exit;
        }

        if ($action === 'create_customer_coupon' && current_user_can('ttos_view_reports')) {
            $email = sanitize_email(wp_unslash($_POST['customer_email'] ?? ''));
            $amount = (float) wp_unslash($_POST['coupon_amount'] ?? 0);
            $type = sanitize_key(wp_unslash($_POST['coupon_type'] ?? 'fixed_cart'));
            $days = absint(wp_unslash($_POST['expires_days'] ?? 30));
            if ($email && $amount > 0) {
                self::create_campaign_coupon(array($email), $amount, $type, $days, 'Customer reward');
            }
            wp_safe_redirect(add_query_arg(array('page' => 'takeaway-os-customers', 'customer' => rawurlencode($email), 'ttos_notice' => 'coupon-created'), admin_url('admin.php')));
            exit;
        }

        if ($action === 'create_customer_campaign' && current_user_can('ttos_view_reports')) {
            $segment = sanitize_key(wp_unslash($_POST['campaign_segment'] ?? 'all'));
            $name = sanitize_text_field(wp_unslash($_POST['campaign_name'] ?? 'Direct-order campaign'));
            $amount = (float) wp_unslash($_POST['campaign_amount'] ?? 5);
            $type = sanitize_key(wp_unslash($_POST['campaign_type'] ?? 'fixed_cart'));
            $days = absint(wp_unslash($_POST['campaign_expires_days'] ?? 14));
            $emails = self::customer_emails_for_segment($segment);
            if ($emails && $amount > 0) {
                $code = self::create_campaign_coupon($emails, $amount, $type, $days, $name);
                $campaigns = get_option('ttos_customer_campaigns', array());
                if (!is_array($campaigns)) $campaigns = array();
                $campaigns[] = array(
                    'id' => 'camp_' . wp_generate_password(8, false, false),
                    'name' => $name,
                    'segment' => $segment,
                    'code' => $code,
                    'amount' => $amount,
                    'type' => $type,
                    'expires_days' => $days,
                    'email_count' => count($emails),
                    'emails' => $emails,
                    'created' => time(),
                );
                update_option('ttos_customer_campaigns', array_slice($campaigns, -50), false);
            }
            self::redirect_notice('campaign-created', 'takeaway-os-customers');
        }
    }


    private static function module_unlock_transient(): string {
        return 'ttos_modules_unlocked_' . get_current_user_id();
    }

    private static function module_key_valid(string $key): bool {
        $hash = (string) get_option('ttos_module_lock_hash', '');
        if ($hash === '') {
            return true;
        }
        return $key !== '' && wp_check_password($key, $hash);
    }

    private static function modules_unlocked(): bool {
        $hash = (string) get_option('ttos_module_lock_hash', '');
        if ($hash === '') {
            return true;
        }
        return get_transient(self::module_unlock_transient()) === '1';
    }

    private static function module_lock_exists(): bool {
        return (string) get_option('ttos_module_lock_hash', '') !== '';
    }

    private static function redirect_notice(string $notice, string $page = ''): void {
        $page = $page ?: (isset($_GET['page']) ? sanitize_key($_GET['page']) : 'takeaway-os');
        if (!empty($_POST['ttos_return'])) {
            $page = sanitize_key(wp_unslash($_POST['ttos_return']));
        }
        $url = add_query_arg(array('page' => $page, 'ttos_notice' => $notice), admin_url('admin.php'));
        if (!empty($_POST['ttos_anchor'])) {
            $url .= '#' . sanitize_key(wp_unslash($_POST['ttos_anchor']));
        }
        wp_safe_redirect($url);
        exit;
    }

    private static function shell_start(string $title, string $subtitle = ''): void {
        TTOS_Admin_Shell::render_start(array(
            'title' => $title,
            'subtitle' => $subtitle,
            'active' => TTOS_Admin_Shell::current_page(),
        ));
        self::notices();
    }

    private static function shell_end(): void {
        TTOS_Admin_Shell::render_end();
    }

    private static function notices(): void {
        $flash = self::consume_flash_notice();
        if (is_array($flash) && !empty($flash['message'])) {
            echo '<div class="ttos-notice is-' . esc_attr((string) ($flash['type'] ?? 'success')) . '">' . esc_html((string) $flash['message']) . '</div>';
            return;
        }
        if (empty($_GET['ttos_notice'])) return;
        $notice = sanitize_key($_GET['ttos_notice']);
        $messages = array(
            'settings-saved'       => array('type' => 'success', 'text' => __('Settings saved.', 'takeaway-os')),
            'branding-saved'       => array('type' => 'success', 'text' => __('Branding saved.', 'takeaway-os')),
            'delivery-saved'       => array('type' => 'success', 'text' => __('Delivery & collection settings saved.', 'takeaway-os')),
            'trading-saved'        => array('type' => 'success', 'text' => __('Trading settings saved.', 'takeaway-os')),
            'business-saved'       => array('type' => 'success', 'text' => __('Business details saved.', 'takeaway-os')),
            'content-saved'        => array('type' => 'success', 'text' => __('Site content saved.', 'takeaway-os')),
            'woocommerce-required' => array('type' => 'error',   'text' => __('WooCommerce must be active to use this feature.', 'takeaway-os')),
            'modules-locked'       => array('type' => 'error',   'text' => __('That module key is invalid.', 'takeaway-os')),
            'modules-unlocked'     => array('type' => 'success', 'text' => __('Add-ons unlocked.', 'takeaway-os')),
            'campaign-created'     => array('type' => 'success', 'text' => __('Campaign created.', 'takeaway-os')),
            'customer-imported'    => array('type' => 'success', 'text' => __('Customer import finished.', 'takeaway-os')),
            'key-invalid'          => array('type' => 'error',   'text' => __('Invalid licence key.', 'takeaway-os')),
            'startup-invalid'      => array('type' => 'error',   'text' => __('Add a site name and a valid client email before sending the first content form.', 'takeaway-os')),
            'startup-reset'        => array('type' => 'success', 'text' => __('Developer startup has been reset. Open Launchpad to run it again for the next build.', 'takeaway-os')),
        );
        $m = $messages[$notice] ?? array('type' => 'success', 'text' => __('Saved.', 'takeaway-os'));
        echo '<div class="ttos-notice is-' . esc_attr($m['type']) . '">' . esc_html($m['text']) . '</div>';
    }

    private static function flash_notice(string $type, string $message): void {
        set_transient(
            'ttos_admin_notice_' . get_current_user_id(),
            array(
                'type'    => sanitize_key($type),
                'message' => sanitize_text_field($message),
            ),
            5 * MINUTE_IN_SECONDS
        );
    }

    private static function consume_flash_notice(): ?array {
        $key = 'ttos_admin_notice_' . get_current_user_id();
        $notice = get_transient($key);
        if (!is_array($notice)) {
            return null;
        }
        delete_transient($key);
        return $notice;
    }

    private static function nav(): void {
        TTOS_Admin_Shell::render_primary_nav(TTOS_Admin_Shell::current_page());
    }

    public static function page_dashboard(): void {
        self::shell_start('Owner CRM dashboard', 'Revenue, repeat-customer health and quick actions for service, marketing and growth.');
        $dashboard = self::dashboard_snapshot();
        $today = $dashboard['today']['summary'];
        $week = $dashboard['week']['summary'];
        $month = $dashboard['month']['summary'];

        echo '<section class="ttos-card ttos-dashboard-hero"><div class="ttos-dashboard-hero-grid"><div class="ttos-dashboard-hero-copy">';
        echo '<p class="ttos-eyebrow">Restaurant pulse</p><h2>Turn direct orders into repeat customers</h2><p class="ttos-muted">This home screen blends sales, customer memory and owner next steps, so staff do not have to bounce across WooCommerce and WordPress to understand how the restaurant is performing.</p>';
        echo '<div class="ttos-big-number"><strong>' . esc_html(self::money($today['gross'])) . '</strong><span>Today · ' . esc_html((string) $today['orders']) . ' orders · AOV ' . esc_html(self::money($today['aov'])) . '</span></div>';
        echo '<div class="ttos-dashboard-chip-row">';
        self::dashboard_chip('New customers 30d', (string) $dashboard['new_customers_30']);
        self::dashboard_chip('Repeat customers 30d', (string) $dashboard['repeat_customers_30']);
        self::dashboard_chip('Marketing ready', (string) $dashboard['segment_counts']['marketing']);
        self::dashboard_chip('Ordering', !empty($dashboard['ordering_state']['label']) ? (string) $dashboard['ordering_state']['label'] : 'Unknown');
        echo '</div></div>';
        echo '<div class="ttos-dashboard-actions"><span class="ttos-section-header">Quick links</span>';
        foreach (self::dashboard_quick_links() as $link) {
            echo '<a class="ttos-dashboard-action" href="' . esc_url($link['url']) . '"><strong>' . esc_html($link['label']) . '</strong><small>' . esc_html($link['description']) . '</small></a>';
        }
        echo '</div></div></section>';

        echo '<div class="ttos-grid ttos-grid-4">';
        self::metric('7-day revenue', self::money($week['gross']));
        self::metric('30-day revenue', self::money($month['gross']));
        self::metric('Customers 30d', (string) $month['unique_customers']);
        self::metric('Average order', self::money($month['aov']));
        echo '</div>';
        echo '<div class="ttos-grid ttos-grid-4">';
        self::metric('New customers 30d', (string) $dashboard['new_customers_30']);
        self::metric('Repeat customers 30d', (string) $dashboard['repeat_customers_30']);
        self::metric('Dormant customers', (string) $dashboard['segment_counts']['dormant']);
        self::metric('Marketing ready', (string) $dashboard['segment_counts']['marketing']);
        echo '</div>';

        echo '<div class="ttos-grid ttos-grid-2">';
        echo '<section class="ttos-card"><div class="ttos-card-head"><div><h2>Revenue tracker</h2><p class="ttos-muted">Last 30 days of sales, customer activity and direct-order value.</p></div><p class="ttos-dashboard-card-link"><a class="ttos-mini" href="' . esc_url(admin_url('admin.php?page=takeaway-os-reports')) . '">Open reports</a></p></div>';
        self::dashboard_revenue_chart($dashboard['month']['days']);
        echo '<div class="ttos-dashboard-inline-stats">';
        self::dashboard_inline_stat('Net sales', self::money($month['net']));
        self::dashboard_inline_stat('Direct-order savings est.', self::money($month['direct_savings_estimate']));
        self::dashboard_inline_stat('Card', self::money($month['card']));
        self::dashboard_inline_stat('Cash', self::money($month['cash']));
        echo '</div></section>';

        echo '<section class="ttos-card"><div class="ttos-card-head"><div><h2>Owner watchlist</h2><p class="ttos-muted">The fast checks that stop service, setup or follow-up issues from being missed.</p></div><p class="ttos-dashboard-open-state">' . self::dashboard_ordering_badge($dashboard['ordering_state']) . '</p></div>';
        self::dashboard_watchlist($dashboard);
        echo '</section></div>';

        echo '<div class="ttos-grid ttos-grid-2">';
        echo '<section class="ttos-card"><div class="ttos-card-head"><div><h2>Recent customers</h2><p class="ttos-muted">Keep an eye on new diners, return visits and who might need a follow-up.</p></div><p class="ttos-dashboard-card-link"><a class="ttos-mini" href="' . esc_url(admin_url('admin.php?page=takeaway-os-customers')) . '">Open CRM</a></p></div>';
        self::dashboard_recent_customers($dashboard['recent_customers']);
        echo '</section>';

        echo '<section class="ttos-card"><div class="ttos-card-head"><div><h2>Recent orders</h2><p class="ttos-muted">The latest order flow, payment mix and fulfilment activity.</p></div><p class="ttos-dashboard-card-link"><a class="ttos-mini" href="' . esc_url(admin_url('admin.php?page=takeaway-os-orders')) . '">Open orders</a></p></div>';
        self::dashboard_recent_orders($dashboard['recent_orders']);
        echo '</section></div>';

        echo '<div class="ttos-grid ttos-grid-3">';
        echo '<section class="ttos-card"><div class="ttos-card-head"><div><h2>Customer segments</h2><p class="ttos-muted">Jump straight into the lists most likely to need action.</p></div><p class="ttos-dashboard-card-link"><a class="ttos-mini" href="' . esc_url(admin_url('admin.php?page=takeaway-os-customers&segment=marketing')) . '">Build campaign</a></p></div>';
        self::dashboard_segments($dashboard['segment_counts']);
        echo '</section>';

        echo '<section class="ttos-card"><div class="ttos-card-head"><div><h2>Top sellers this month</h2><p class="ttos-muted">Popular lines worth protecting, bundling or upselling.</p></div><p class="ttos-dashboard-card-link"><a class="ttos-mini" href="' . esc_url(admin_url('admin.php?page=takeaway-os-menu')) . '">Open menu</a></p></div>';
        self::dashboard_top_items($dashboard['month']['top_items']);
        echo '</section>';

        echo '<section class="ttos-card"><div class="ttos-card-head"><div><h2>System checklist</h2><p class="ttos-muted">Foundation plugins that keep checkout, payments and comms running.</p></div><p class="ttos-dashboard-card-link"><a class="ttos-mini" href="' . esc_url(admin_url('admin.php?page=takeaway-os-launchpad')) . '">Open launchpad</a></p></div>';
        foreach (TTOS_Plugin_Checker::plugins() as $plugin) {
            self::plugin_row($plugin);
        }
        echo '</section></div>';
        self::shell_end();
    }

    private static function dashboard_snapshot(): array {
        $today_range = TTOS_Analytics::range('today');
        $week_range = TTOS_Analytics::range('7days');
        $month_range = TTOS_Analytics::range('30days');
        $today = TTOS_Analytics::report($today_range['start'], $today_range['end']);
        $week = TTOS_Analytics::report($week_range['start'], $week_range['end']);
        $month = TTOS_Analytics::report($month_range['start'], $month_range['end']);
        $customers = TTOS_WooCommerce::active() ? self::customer_snapshot_enhanced(500) : array();
        $segment_counts = self::customer_segment_counts($customers);
        $month_start = (int) $month_range['start'];
        $new_customers = 0;
        $repeat_customers = 0;

        foreach ($customers as $customer) {
            $first_ts = (int) ($customer['first_ts'] ?? 0);
            $last_ts = (int) ($customer['last_ts'] ?? 0);
            $orders = (int) ($customer['orders'] ?? 0);
            if ($first_ts >= $month_start) {
                $new_customers++;
            }
            if ($last_ts >= $month_start && $orders >= 2) {
                $repeat_customers++;
            }
        }

        $recent_customers = array_slice($customers, 0, 6, true);
        $page_summary = class_exists('TTOS_Page_Manager') ? TTOS_Page_Manager::summary() : array('counts' => array());
        $hardening = class_exists('TTOS_Hardening') ? TTOS_Hardening::check_summary() : array();
        $ordering_state = class_exists('TTOS_Operations') ? TTOS_Operations::ordering_state() : array();

        return array(
            'today' => $today,
            'week' => $week,
            'month' => $month,
            'segment_counts' => $segment_counts,
            'new_customers_30' => $new_customers,
            'repeat_customers_30' => $repeat_customers,
            'recent_customers' => $recent_customers,
            'recent_orders' => $month['recent_orders'],
            'page_summary' => $page_summary,
            'hardening' => $hardening,
            'ordering_state' => $ordering_state,
        );
    }

    private static function dashboard_quick_links(): array {
        return array(
            array(
                'label' => 'Orders',
                'description' => 'Live service board and prep flow.',
                'url' => admin_url('admin.php?page=takeaway-os-orders'),
            ),
            array(
                'label' => 'Customers / CRM',
                'description' => 'Profiles, notes, loyalty and campaigns.',
                'url' => admin_url('admin.php?page=takeaway-os-customers'),
            ),
            array(
                'label' => 'Reports',
                'description' => 'Revenue, payment split and daily close.',
                'url' => admin_url('admin.php?page=takeaway-os-reports'),
            ),
            array(
                'label' => 'Menu',
                'description' => 'Update products, pricing and combos.',
                'url' => admin_url('admin.php?page=takeaway-os-menu'),
            ),
            array(
                'label' => 'Site content',
                'description' => 'Edit homepage, offers and policies.',
                'url' => admin_url('admin.php?page=takeaway-os-site-content'),
            ),
            array(
                'label' => 'Setup Health',
                'description' => 'Check page wiring and repair setup.',
                'url' => admin_url('admin.php?page=takeaway-os-setup-health'),
            ),
        );
    }

    private static function dashboard_chip(string $label, string $value): void {
        echo '<span class="ttos-dashboard-chip"><small>' . esc_html($label) . '</small><strong>' . esc_html($value) . '</strong></span>';
    }

    private static function dashboard_inline_stat(string $label, string $value): void {
        echo '<div class="ttos-dashboard-inline-stat"><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong></div>';
    }

    private static function dashboard_revenue_chart(array $days): void {
        if (!$days) {
            echo '<p class="ttos-muted">No order data yet. Once direct orders start landing, this revenue tracker will fill itself in.</p>';
            return;
        }

        $max = 0.0;
        foreach ($days as $row) {
            $max = max($max, (float) ($row['gross'] ?? 0));
        }

        echo '<div class="ttos-dashboard-chart" role="img" aria-label="Revenue for the last 30 days">';
        $index = 0;
        foreach ($days as $day => $row) {
            $gross = (float) ($row['gross'] ?? 0);
            $orders = (int) ($row['orders'] ?? 0);
            $height = $max > 0 ? max(10, (int) round(($gross / $max) * 148)) : 10;
            $label = !empty($row['label']) ? (string) $row['label'] : (string) $day;
            $tick = $index % 5 === 0 ? wp_date('j M', strtotime((string) $day)) : '';
            echo '<span class="ttos-dashboard-chart-bar" title="' . esc_attr($label . ' · ' . self::money($gross) . ' · ' . $orders . ' orders') . '"><i style="height:' . esc_attr((string) $height) . 'px"></i><small>' . esc_html($tick) . '</small></span>';
            $index++;
        }
        echo '</div>';
    }

    private static function dashboard_watchlist(array $dashboard): void {
        $hardening = $dashboard['hardening'];
        $pages = $dashboard['page_summary']['counts'] ?? array();
        $status_class = !empty($hardening['critical']) ? 'ttos-bad' : (!empty($hardening['warnings']) ? 'ttos-warn' : 'ttos-good');

        echo '<div class="ttos-dashboard-watchlist">';
        if ($hardening) {
            echo '<div class="ttos-dashboard-watch-item"><strong>System readiness</strong><span class="' . esc_attr($status_class) . '">' . esc_html((string) ($hardening['label'] ?? 'Unknown')) . '</span><p class="ttos-muted">' . esc_html((string) ($hardening['message'] ?? '')) . '</p><a class="ttos-mini" href="' . esc_url(admin_url('admin.php?page=takeaway-os-golive')) . '">Open system check</a></div>';
        }
        echo '<div class="ttos-dashboard-watch-item"><strong>Public pages</strong><span>' . esc_html((string) ($pages['ready'] ?? 0)) . '/' . esc_html((string) ($pages['total'] ?? 0)) . ' ready</span><p class="ttos-muted">' . (!empty($pages['problem_count']) ? 'Some required pages are still missing, blank or still using old content.' : 'Menu, basket, checkout and account pages are wired into Takeaway OS.') . '</p><a class="ttos-mini" href="' . esc_url(admin_url('admin.php?page=takeaway-os-setup-health')) . '">Open Setup Health</a></div>';
        echo '<div class="ttos-dashboard-watch-item"><strong>Customer follow-up</strong><span>' . esc_html((string) $dashboard['segment_counts']['dormant']) . ' dormant</span><p class="ttos-muted">Dormant and marketing-ready diners are the easiest direct-order win-back audience.</p><a class="ttos-mini" href="' . esc_url(admin_url('admin.php?page=takeaway-os-customers&segment=dormant')) . '">Open dormant customers</a></div>';
        echo '</div>';
    }

    private static function dashboard_ordering_badge(array $state): string {
        $label = !empty($state['label']) ? (string) $state['label'] : 'Ordering status unknown';
        $status = !empty($state['open']) ? ' is-open' : ' is-closed';
        return '<span class="ttos-open-status' . esc_attr($status) . '">' . esc_html($label) . '</span>';
    }

    private static function dashboard_recent_customers(array $customers): void {
        if (!$customers) {
            echo '<p class="ttos-muted">No customer history yet. Once orders start landing, this panel will show the latest diners and their status.</p>';
            return;
        }

        echo '<div class="ttos-dashboard-list">';
        foreach ($customers as $email => $customer) {
            $name = (string) ($customer['name'] ?: 'Guest customer');
            $meta = trim(($customer['last'] ?: 'No orders yet') . ' · ' . self::money((float) ($customer['total'] ?? 0)));
            $url = add_query_arg(array('page' => 'takeaway-os-customers', 'customer' => rawurlencode((string) $email)), admin_url('admin.php'));
            echo '<div class="ttos-dashboard-list-item"><div><strong>' . esc_html($name) . '</strong><small>' . esc_html((string) $email) . '</small><p class="ttos-muted">' . esc_html($meta) . '</p></div><div class="ttos-dashboard-list-side">' . self::customer_status_badge((string) ($customer['status'] ?? 'new')) . '<a class="ttos-mini" href="' . esc_url($url) . '">Open</a></div></div>';
        }
        echo '</div>';
    }

    private static function dashboard_recent_orders(array $orders): void {
        if (!$orders) {
            echo '<p class="ttos-muted">No recent orders yet.</p>';
            return;
        }

        echo '<div class="ttos-dashboard-list">';
        foreach (array_slice($orders, 0, 6) as $order) {
            if (!$order || !method_exists($order, 'get_id')) {
                continue;
            }
            $customer = trim((string) $order->get_formatted_billing_full_name());
            if ($customer === '') {
                $customer = (string) ($order->get_billing_email() ?: 'Guest customer');
            }
            $fulfilment = (string) ($order->get_meta('_ttos_fulfilment_method') ?: $order->get_meta('_ttos_fulfilment_type') ?: '');
            $fulfilment = $fulfilment ? ucfirst(strtolower($fulfilment)) : 'Order';
            $date = $order->get_date_created();
            $placed = $date ? $date->date_i18n('d M · H:i') : '';
            echo '<div class="ttos-dashboard-list-item"><div><strong>#' . esc_html((string) $order->get_id()) . ' · ' . esc_html($customer) . '</strong><small>' . esc_html($fulfilment . ($placed ? ' · ' . $placed : '')) . '</small><p class="ttos-muted">' . esc_html(wc_get_order_status_name($order->get_status())) . '</p></div><div class="ttos-dashboard-list-side"><span class="ttos-module-state">' . esc_html(self::money((float) $order->get_total())) . '</span><a class="ttos-mini" href="' . esc_url(admin_url('admin.php?page=takeaway-os-orders')) . '">Open</a></div></div>';
        }
        echo '</div>';
    }

    private static function dashboard_segments(array $counts): void {
        $segments = array(
            'vip' => 'VIP customers',
            'regular' => 'Regulars',
            'new' => 'New customers',
            'dormant' => 'Dormant',
            'marketing' => 'Marketing OK',
        );

        echo '<div class="ttos-dashboard-segments">';
        foreach ($segments as $segment => $label) {
            $url = admin_url('admin.php?page=takeaway-os-customers&segment=' . rawurlencode($segment));
            echo '<a class="ttos-dashboard-segment" href="' . esc_url($url) . '"><span>' . esc_html($label) . '</span><strong>' . esc_html((string) ($counts[$segment] ?? 0)) . '</strong></a>';
        }
        echo '</div>';
    }

    private static function dashboard_top_items(array $items): void {
        if (!$items) {
            echo '<p class="ttos-muted">No menu sales yet. As orders come in, this panel will surface the products worth bundling and protecting.</p>';
            return;
        }

        echo '<ol class="ttos-list">';
        foreach (array_slice($items, 0, 6) as $item) {
            echo '<li><span><strong>' . esc_html((string) ($item['name'] ?? 'Item')) . '</strong><br><small class="ttos-muted">' . esc_html(self::money((float) ($item['gross'] ?? 0))) . ' revenue</small></span><strong>' . esc_html((string) ((int) ($item['qty'] ?? 0))) . ' sold</strong></li>';
        }
        echo '</ol>';
    }

    public static function page_launchpad(): void {
        self::shell_start('Launchpad', 'Set up the whole restaurant from one place. After the wizard, the tabs become the normal edit screens.');
        TTOS_Client_Intake::render_startup_panel();
        self::setup_alerts_panel();
        $required_done = TTOS_Plugin_Checker::required_complete();
        echo '<div class="ttos-wizard" data-required-complete="' . esc_attr($required_done ? '1' : '0') . '">';
        echo '<aside class="ttos-wizard-rail"><div class="ttos-steps">';
        $steps = array(
            'required' => array('Required plugins', 'Install WooCommerce first.'),
            'business' => array('Business details', 'Site name, address and legal basics.'),
            'branding' => array('Branding', 'Logo, hero image, colours and skin.'),
            'delivery' => array('Delivery and collection', 'Fees, radius, postcodes and prep times.'),
            'menu' => array('Menu builder', 'Create the first menu items.'),
            'pages' => array('Pages', 'Create/repair required public pages.'),
            'payments' => array('Payments', 'Install gateways and check settings.'),
            'golive' => array('Go live', 'Test order, email and launch checks.'),
        );
        $i = 1;
        foreach ($steps as $slug => $step) {
            $class = $i === 1 ? ' is-active' : '';
            echo '<button type="button" class="ttos-step ttos-wizard-step' . esc_attr($class) . '" data-step="' . esc_attr($slug) . '"><b>' . esc_html(sprintf('%02d', $i)) . '</b><span><strong>' . esc_html($step[0]) . '</strong><small>' . esc_html($step[1]) . '</small></span><em>Open</em></button>';
            $i++;
        }
        echo '</div></aside><main class="ttos-wizard-main">';

        self::wizard_panel_start('required', true);
        self::plugin_installer_panel();
        self::wizard_panel_end();

        self::wizard_panel_start('business');
        self::business_form('takeaway-os-launchpad', 'branding', 'Save and continue →');
        self::wizard_panel_end();

        self::wizard_panel_start('branding');
        self::branding_form('takeaway-os-launchpad', 'delivery', 'Save and continue →');
        self::wizard_panel_end();

        self::wizard_panel_start('delivery');
        self::delivery_form('takeaway-os-launchpad', 'menu', 'Save and continue →');
        self::wizard_panel_end();

        self::wizard_panel_start('menu');
        if (!TTOS_WooCommerce::active()) {
            echo '<section class="ttos-card"><h2>Menu builder</h2><p class="ttos-muted">WooCommerce must be installed and active before menu items can be created. Go back to Required plugins first.</p><button type="button" class="ttos-button ttos-wizard-next" data-next="required">Back to required plugins</button></section>';
        } else {
            echo '<section class="ttos-card"><h2>Menu builder</h2><p class="ttos-muted">Add a starter item now. You can build the full menu later from the Menu tab.</p>';
            self::menu_form('takeaway-os-launchpad', 'pages', 'Save item and continue →');
            echo '</section><section class="ttos-card"><h2>Current menu</h2>';
            self::product_table();
            echo '</section>';
        }
        self::wizard_panel_end();

        self::wizard_panel_start('pages');
        self::pages_wizard_panel();
        self::wizard_panel_end();

        self::wizard_panel_start('payments');
        self::payments_wizard_panel();
        self::wizard_panel_end();

        self::wizard_panel_start('golive');
        self::go_live_panel();
        self::wizard_panel_end();

        echo '</main></div>';
        self::shell_end();
    }


    private static function setup_alerts_panel(): void {
        if (class_exists('TTOS_Setup_Health')) {
            echo TTOS_Setup_Health::launchpad_summary_card();
        }

        $summary = TTOS_Page_Manager::summary();
        $problem_count = (int) ($summary['counts']['problem_count'] ?? 0);
        $product_count = post_type_exists('product') ? (int) wp_count_posts('product')->publish : 0;
        if (!$problem_count && $product_count > 0) {
            return;
        }
        echo '<section class="ttos-card ttos-setup-alert"><div class="ttos-installer-head"><div><p class="ttos-eyebrow">Setup shortcut</p><h2>Finish the public site pages</h2><p class="ttos-muted">Generated pages are what make Home, Menu, Basket, Checkout, Rewards and Delivery Checker show the Takeaway Theme layout, then assign the homepage and default WooCommerce pages. If an old page exists, safe mode creates a fresh Takeaway page instead of overwriting old content.</p></div>';
        echo '<div class="ttos-installer-actions"><a class="ttos-button" href="' . esc_url(admin_url('admin.php?page=takeaway-os-setup-health')) . '">Open Setup Health</a><button type="button" class="ttos-button ttos-wizard-next" data-next="pages">Open pages step</button></div></div>';
        echo '<div class="ttos-grid ttos-grid-2">';
        echo '<div class="ttos-mini-card"><strong>' . esc_html((string) $problem_count) . '</strong><span>Pages missing or needing Takeaway content</span>';
        echo '<form method="post" style="margin-top:12px">';
        wp_nonce_field('ttos_generate_pages');
        echo '<input type="hidden" name="ttos_action" value="generate_pages"><input type="hidden" name="ttos_return" value="takeaway-os-launchpad"><input type="hidden" name="ttos_anchor" value="pages"><button class="ttos-button">Create / repair pages now</button></form></div>';
        echo '<div class="ttos-mini-card"><strong>' . esc_html((string) $product_count) . '</strong><span>Live menu items</span>';
        if (TTOS_WooCommerce::active() && $product_count === 0 && class_exists('TTOS_Production')) {
            echo '<form method="post" style="margin-top:12px">';
            wp_nonce_field('ttos_create_starter_menu');
            echo '<input type="hidden" name="ttos_action" value="create_starter_menu"><input type="hidden" name="ttos_return" value="takeaway-os-launchpad"><input type="hidden" name="ttos_anchor" value="menu"><button class="ttos-button ttos-button-dark">Create starter menu</button></form>';
        } else {
            echo '<p><a class="ttos-mini" href="' . esc_url(admin_url('admin.php?page=takeaway-os-menu')) . '">Open menu builder</a></p>';
        }
        echo '</div></div></section>';
    }

    private static function wizard_panel_start(string $slug, bool $active = false): void {
        echo '<div id="ttos-step-' . esc_attr($slug) . '" class="ttos-wizard-panel' . esc_attr($active ? ' is-active' : '') . '" data-panel="' . esc_attr($slug) . '">';
    }

    private static function wizard_panel_end(): void {
        echo '</div>';
    }

    private static function plugin_installer_panel(): void {
        $required_done = TTOS_Plugin_Checker::required_complete();
        echo '<section class="ttos-card ttos-installer" data-required-complete="' . esc_attr($required_done ? '1' : '0') . '">';
        echo '<div class="ttos-installer-head"><div><p class="ttos-eyebrow">System check</p><h2>Required foundation</h2><p class="ttos-muted">Install and activate WooCommerce first. Once it is active, the wizard refreshes so payment gateways can activate cleanly on the next request.</p></div>';
        echo '<button type="button" class="ttos-button ttos-next-step ttos-wizard-next ' . esc_attr($required_done ? '' : 'is-disabled') . '" data-next="business" aria-disabled="' . esc_attr($required_done ? 'false' : 'true') . '">Next: business details →</button></div>';

        echo '<div class="ttos-plugin-section-title"><span>Required</span><small>Must be active before setup continues</small></div>';
        echo '<div class="ttos-plugin-list">';
        foreach (TTOS_Plugin_Checker::plugins() as $key => $plugin) {
            if (!empty($plugin['required'])) {
                self::plugin_installer_row($key, $plugin);
            }
        }
        echo '</div>';
        echo '<div class="ttos-progress-wrap" aria-live="polite"><div class="ttos-progress"><span style="width:0%"></span></div><strong class="ttos-progress-label">Ready</strong></div>';
        echo '<div class="ttos-installer-actions"><button type="button" class="ttos-button" id="ttos-install-required">Install / activate required plugins</button><span class="ttos-muted">Stripe, SMTP and other tools are handled in the Payments step after WooCommerce is loaded.</span></div>';
        if (TTOS_WooCommerce::active()) {
            echo '<form method="post" class="ttos-installer-actions">';
            wp_nonce_field('ttos_apply_woocommerce_profile');
            echo '<input type="hidden" name="ttos_action" value="apply_woocommerce_profile"><button class="ttos-button ttos-button-dark">Apply Takeaway WooCommerce profile</button><span class="ttos-muted">Sets GBP/UK defaults, checkout basics, starter categories, homepage, WooCommerce pages, header/footer menus and a local delivery zone.</span></form>';
        }
        echo '</section>';
    }

    private static function plugin_installer_row(string $key, array $plugin): void {
        $status = TTOS_Plugin_Checker::status($plugin);
        $label = $status === 'active' ? 'Active' : ($status === 'installed' ? 'Installed, not active' : 'Missing');
        $class = $status === 'active' ? 'ttos-good' : ($plugin['required'] ? 'ttos-bad' : 'ttos-warn');
        $depends = !empty($plugin['depends']) ? implode(',', array_map('sanitize_key', (array) $plugin['depends'])) : '';
        echo '<div class="ttos-plugin-row ttos-install-row" data-key="' . esc_attr($key) . '" data-slug="' . esc_attr($plugin['slug']) . '" data-file="' . esc_attr($plugin['file']) . '" data-required="' . esc_attr(!empty($plugin['required']) ? '1' : '0') . '" data-depends="' . esc_attr($depends) . '">';
        echo '<div><strong>' . esc_html($plugin['name']) . '</strong><p>' . esc_html($plugin['description']) . '</p></div>';
        echo '<span class="' . esc_attr($class) . ' ttos-status">' . esc_html($label) . '</span>';
        if (current_user_can('install_plugins') && $status !== 'active') {
            echo '<button type="button" class="ttos-mini ttos-install-one">' . esc_html($status === 'missing' ? 'Install' : 'Activate') . '</button>';
        } else {
            echo '<span></span>';
        }
        echo '</div>';
    }

    private static function business_form(string $return = '', string $anchor = '', string $button = 'Save business'): void {
        $business = TTOS_Settings::get('business');
        echo '<section class="ttos-card"><h2>Business details</h2><p class="ttos-muted">These values update the restaurant profile and the normal WordPress site title/tagline behind the scenes.</p><form method="post">';
        wp_nonce_field('ttos_save_business');
        echo '<input type="hidden" name="ttos_action" value="save_business">';
        if ($return) echo '<input type="hidden" name="ttos_return" value="' . esc_attr($return) . '"><input type="hidden" name="ttos_anchor" value="' . esc_attr($anchor) . '">';
        echo '<div class="ttos-grid ttos-grid-2">';
        self::field('Restaurant name', 'business[restaurant_name]', $business['restaurant_name']);
        self::field('Tagline', 'business[tagline]', $business['tagline']);
        self::field('Phone', 'business[phone]', $business['phone']);
        self::field('Email', 'business[email]', $business['email'], 'email');
        self::field('Address line 1', 'business[address_1]', $business['address_1']);
        self::field('Address line 2', 'business[address_2]', $business['address_2']);
        self::field('Town', 'business[town]', $business['town']);
        self::field('Postcode', 'business[postcode]', $business['postcode']);
        self::field('Cuisine type', 'business[cuisine]', $business['cuisine']);
        self::field('Food hygiene rating', 'business[fsa_rating]', $business['fsa_rating']);
        self::field('VAT number', 'business[vat_number]', $business['vat_number']);
        self::field('Company number', 'business[company_number]', $business['company_number'] ?? '');
        echo '</div><button class="ttos-button">' . esc_html($button) . '</button></form></section>';
    }

    private static function branding_presets(): array {
        return array(
            'flame' => array('label' => 'Flame', 'fills' => array(
                'primary' => '#ff4000', 'accent' => '#ffac00', 'bg' => '#f9f4ee', 'surface' => '#ffffff', 'surface_soft' => '#f1eae0',
                'text' => '#1a1410', 'muted' => '#6f655e', 'border' => '#e8dfd4', 'success' => '#18a844', 'warning' => '#c47d0e', 'error' => '#b33a3a',
                'radius_sm' => '10', 'radius_md' => '16', 'radius_lg' => '24', 'shadow' => 'soft',
            )),
            'charcoal' => array('label' => 'Charcoal', 'fills' => array(
                'primary' => '#e2451c', 'accent' => '#f5a623', 'bg' => '#f6f5f3', 'surface' => '#ffffff', 'surface_soft' => '#eceae6',
                'text' => '#1c1a17', 'muted' => '#6e6862', 'border' => '#e2ded8', 'success' => '#18a844', 'warning' => '#c47d0e', 'error' => '#b33a3a',
                'radius_sm' => '10', 'radius_md' => '16', 'radius_lg' => '24', 'shadow' => 'soft',
            )),
            'fresh_green' => array('label' => 'Fresh Green', 'fills' => array(
                'primary' => '#1f9d55', 'accent' => '#ffc23c', 'bg' => '#f6faf4', 'surface' => '#ffffff', 'surface_soft' => '#e9f3e4',
                'text' => '#15211a', 'muted' => '#5f6f64', 'border' => '#dce8d8', 'success' => '#1f9d55', 'warning' => '#c47d0e', 'error' => '#c0392b',
                'radius_sm' => '10', 'radius_md' => '16', 'radius_lg' => '24', 'shadow' => 'soft',
            )),
            'midnight' => array('label' => 'Midnight', 'fills' => array(
                'primary' => '#4f7cff', 'accent' => '#ffd166', 'bg' => '#f4f6fb', 'surface' => '#ffffff', 'surface_soft' => '#e9edf6',
                'text' => '#101523', 'muted' => '#5d6577', 'border' => '#dde3ef', 'success' => '#18a844', 'warning' => '#c47d0e', 'error' => '#b33a3a',
                'radius_sm' => '12', 'radius_md' => '18', 'radius_lg' => '26', 'shadow' => 'strong', 'default_mode' => 'dark',
            )),
            'cream_tomato' => array('label' => 'Cream & Tomato', 'fills' => array(
                'primary' => '#d62828', 'accent' => '#f77f00', 'bg' => '#fdf6ec', 'surface' => '#fffdf8', 'surface_soft' => '#f6ead8',
                'text' => '#271c19', 'muted' => '#75655c', 'border' => '#ecdcc8', 'success' => '#18a844', 'warning' => '#c47d0e', 'error' => '#b33a3a',
                'radius_sm' => '12', 'radius_md' => '18', 'radius_lg' => '28', 'shadow' => 'soft',
            )),
            'minimal_mono' => array('label' => 'Minimal Mono', 'fills' => array(
                'primary' => '#111111', 'accent' => '#555555', 'bg' => '#fafafa', 'surface' => '#ffffff', 'surface_soft' => '#f0f0f0',
                'text' => '#111111', 'muted' => '#707070', 'border' => '#e3e3e3', 'success' => '#1f9d55', 'warning' => '#b88217', 'error' => '#c0392b',
                'radius_sm' => '6', 'radius_md' => '10', 'radius_lg' => '14', 'shadow' => 'none',
            )),
        );
    }

    private static function select_field(string $label, string $name, string $value, array $options): void {
        echo '<label>' . esc_html($label) . '<select name="' . esc_attr($name) . '">';
        foreach ($options as $key => $option_label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select></label>';
    }

    private static function branding_form(string $return = '', string $anchor = '', string $button = 'Save branding'): void {
        $branding = TTOS_Settings::get('branding');
        $tokens = TTOS_Settings::brand_tokens();
        echo '<section class="ttos-card ttos-branding-form" id="branding"><h2>Branding</h2><p class="ttos-muted">Owner-safe design controls. Colours, shape and mode become design tokens the whole front end uses. Images come from the media gallery; we store attachment IDs in the background.</p><form method="post">';
        wp_nonce_field('ttos_save_branding');
        echo '<input type="hidden" name="ttos_action" value="save_branding">';
        if ($return) echo '<input type="hidden" name="ttos_return" value="' . esc_attr($return) . '"><input type="hidden" name="ttos_anchor" value="' . esc_attr($anchor) . '">';

        echo '<h3 class="ttos-brand-subhead">Quick presets</h3><p class="ttos-muted">Applying a preset replaces the colour, radius and shadow fields below. Nothing is stored until you save.</p><div class="ttos-brand-presets">';
        foreach (self::branding_presets() as $key => $preset) {
            $fills = $preset['fills'];
            echo '<button type="button" class="ttos-brand-preset" data-preset="' . esc_attr(wp_json_encode($fills)) . '">'
                . '<span class="ttos-preset-swatches"><i style="background:' . esc_attr($fills['primary']) . '"></i><i style="background:' . esc_attr($fills['accent']) . '"></i><i style="background:' . esc_attr($fills['bg']) . '"></i><i style="background:' . esc_attr($fills['text']) . '"></i></span>'
                . esc_html($preset['label']) . '</button>';
        }
        echo '</div>';

        echo '<h3 class="ttos-brand-subhead">Images</h3><div class="ttos-grid ttos-grid-3">';
        self::media_field('Logo', 'branding[logo_id]', absint($branding['logo_id']), 'Choose logo');
        self::media_field('Favicon (square)', 'branding[favicon_id]', absint($branding['favicon_id']), 'Choose favicon');
        self::media_field('Hero image', 'branding[hero_image_id]', absint($branding['hero_image_id']), 'Choose hero image');
        echo '</div>';

        echo '<h3 class="ttos-brand-subhead">Colours</h3><div class="ttos-brand-colour-grid">';
        $colour_fields = array(
            'primary' => 'Primary', 'accent' => 'Accent', 'bg' => 'Background', 'surface' => 'Surface',
            'surface_soft' => 'Soft surface', 'text' => 'Text', 'muted' => 'Muted text', 'border' => 'Border',
            'success' => 'Success', 'warning' => 'Warning', 'error' => 'Error',
        );
        foreach ($colour_fields as $key => $label) {
            self::field($label, 'branding[' . $key . ']', $branding[$key], 'color');
        }
        echo '</div>';

        echo '<h3 class="ttos-brand-subhead">Shape & depth</h3><div class="ttos-grid ttos-grid-4">';
        self::field('Radius small (px)', 'branding[radius_sm]', $branding['radius_sm'], 'number');
        self::field('Radius medium (px)', 'branding[radius_md]', $branding['radius_md'], 'number');
        self::field('Radius large (px)', 'branding[radius_lg]', $branding['radius_lg'], 'number');
        self::select_field('Shadow', 'branding[shadow]', $tokens['shadow'], array('none' => 'None', 'soft' => 'Soft', 'strong' => 'Strong'));
        echo '</div>';

        echo '<h3 class="ttos-brand-subhead">Mode & template styles</h3><div class="ttos-grid ttos-grid-3">';
        self::select_field('Default mode', 'branding[default_mode]', $tokens['default_mode'], array('light' => 'Light', 'dark' => 'Dark', 'system' => 'Match device (system)'));
        self::select_field('Header style', 'branding[header_style]', $tokens['header_style'], array('solid' => 'Solid', 'transparent' => 'Transparent over hero'));
        self::select_field('Hero style', 'branding[hero_style]', $tokens['hero_style'], array('angled' => 'Angled split', 'minimal' => 'Minimal', 'photo' => 'Full photo'));
        self::select_field('Card style', 'branding[card_style]', $tokens['card_style'], array('soft' => 'Soft shadow', 'outlined' => 'Outlined', 'flat' => 'Flat'));
        self::select_field('Footer style', 'branding[footer_style]', $tokens['footer_style'], array('dark' => 'Dark', 'light' => 'Light'));
        echo '</div>';
        echo '<p class="ttos-muted">Dark and system modes apply to the new token-driven templates as they roll out; current pages stay light until then.</p>';

        echo '<h3 class="ttos-brand-subhead">Typography</h3>';
        echo '<p class="ttos-muted">Override fonts for headings and body text. Leave blank to use the theme default. Enter any CSS font-family stack — e.g. <code>Georgia, serif</code> or a Google Font name like <code>Playfair Display</code>.</p>';
        echo '<div class="ttos-grid ttos-grid-2">';
        self::field('Heading font (H1–H6)', 'branding[font_heading]', $tokens['font_heading'] ?? '');
        self::field('Body font (paragraphs & UI)', 'branding[font_body]', $tokens['font_body'] ?? '');
        echo '</div>';

        echo '<h3 class="ttos-brand-subhead">Header contact strip</h3>';
        echo '<p class="ttos-muted">Control which contact details and icons appear in the utility bar above the main navigation.</p>';
        echo '<div class="ttos-grid ttos-grid-3">';
        self::toggle_field('Show phone number', 'branding[show_header_phone]', $branding['show_header_phone'] ?? '1');
        self::toggle_field('Show email address', 'branding[show_header_email]', $branding['show_header_email'] ?? '1');
        self::toggle_field('Show social icons', 'branding[show_header_socials]', $branding['show_header_socials'] ?? '1');
        echo '</div>';

        echo '<h3 class="ttos-brand-subhead">Live preview</h3>';
        $preview_style = '--tt-primary:' . esc_attr($tokens['primary']) . ';--tt-accent:' . esc_attr($tokens['accent']) . ';--tt-bg:' . esc_attr($tokens['bg']) . ';--tt-surface:' . esc_attr($tokens['surface']) . ';--tt-surface-soft:' . esc_attr($tokens['surface_soft']) . ';--tt-text:' . esc_attr($tokens['text']) . ';--tt-muted:' . esc_attr($tokens['muted']) . ';--tt-border:' . esc_attr($tokens['border']) . ';--tt-success:' . esc_attr($tokens['success']) . ';--tt-warning:' . esc_attr($tokens['warning']) . ';--tt-error:' . esc_attr($tokens['error']) . ';--tt-radius-sm:' . absint($tokens['radius_sm']) . 'px;--tt-radius-md:' . absint($tokens['radius_md']) . 'px;--tt-radius-lg:' . absint($tokens['radius_lg']) . 'px';
        echo '<div class="ttos-brand-preview" style="' . $preview_style . '">'
            . '<div class="ttos-brand-preview-card">'
            . '<p class="ttos-brand-preview-eyebrow">Eyebrow label</p>'
            . '<h4>Your restaurant, your brand</h4>'
            . '<p class="ttos-brand-preview-muted">Muted supporting text shows secondary copy contrast.</p>'
            . '<span class="ttos-brand-preview-btn">Order now</span>'
            . '<span class="ttos-brand-preview-chip">Accent chip</span>'
            . '<span class="ttos-brand-preview-dots"><i class="is-success"></i><i class="is-warning"></i><i class="is-error"></i></span>'
            . '</div></div>';

        echo '<button class="ttos-button">' . esc_html($button) . '</button></form></section>';
    }

    private static function delivery_form(string $return = '', string $anchor = '', string $button = 'Save delivery rules'): void {
        $trading = TTOS_Settings::get('trading');
        echo '<section class="ttos-card"><h2>Delivery and collection</h2><p class="ttos-muted">This covers availability and promises. Full ingredient inventory stays as a future EPOS connector, not a v0.1 burden.</p><form method="post">';
        wp_nonce_field('ttos_save_trading');
        echo '<input type="hidden" name="ttos_action" value="save_trading">';
        if ($return) echo '<input type="hidden" name="ttos_return" value="' . esc_attr($return) . '"><input type="hidden" name="ttos_anchor" value="' . esc_attr($anchor) . '">';
        echo '<div class="ttos-grid ttos-grid-3">';
        self::field('Minimum order (£)', 'trading[min_order]', $trading['min_order']);
        self::field('Delivery fee (£)', 'trading[delivery_fee]', $trading['delivery_fee']);
        self::field('Free delivery over (£)', 'trading[free_delivery_over]', $trading['free_delivery_over']);
        self::field('Delivery radius (miles)', 'trading[delivery_radius]', $trading['delivery_radius']);
        self::field('Prep time (mins)', 'trading[prep_time]', $trading['prep_time']);
        self::field('Delivery promise (mins)', 'trading[delivery_time]', $trading['delivery_time']);
        self::field('Service charge (%)', 'trading[service_charge]', $trading['service_charge'] ?? '0', 'number');
        echo '</div><label>Delivery postcode notes<textarea name="trading[delivery_postcodes]" rows="5">' . esc_textarea($trading['delivery_postcodes']) . '</textarea></label>';
        echo '<label class="ttos-check"><input type="checkbox" name="trading[delivery_enabled]" value="1" ' . checked($trading['delivery_enabled'], '1', false) . '> Delivery enabled</label>';
        echo '<label class="ttos-check"><input type="checkbox" name="trading[collection_enabled]" value="1" ' . checked($trading['collection_enabled'], '1', false) . '> Collection enabled</label>';
        echo '<button class="ttos-button">' . esc_html($button) . '</button></form></section>';
    }

    private static function menu_form(string $return = '', string $anchor = '', string $button = 'Save menu item', int $product_id = 0): void {
        $product = TTOS_WooCommerce::product_form_data($product_id);
        $categories = TTOS_WooCommerce::get_product_categories();
        $allergen_terms = taxonomy_exists('ttos_allergen') ? get_terms(array('taxonomy' => 'ttos_allergen', 'hide_empty' => false)) : array();
        $dietary_terms = taxonomy_exists('ttos_dietary') ? get_terms(array('taxonomy' => 'ttos_dietary', 'hide_empty' => false)) : array();
        $selected_allergens = taxonomy_exists('ttos_allergen') && $product_id ? wp_get_object_terms($product_id, 'ttos_allergen', array('fields' => 'slugs')) : array();
        $selected_dietary = is_array($product['dietary']) ? $product['dietary'] : array();

        echo '<form method="post" class="ttos-menu-form">';
        wp_nonce_field('ttos_save_product');
        echo '<input type="hidden" name="ttos_action" value="save_product">';
        echo '<input type="hidden" name="product[product_id]" value="' . esc_attr((string) $product['product_id']) . '">';
        if ($return) echo '<input type="hidden" name="ttos_return" value="' . esc_attr($return) . '"><input type="hidden" name="ttos_anchor" value="' . esc_attr($anchor) . '">';

        echo '<div class="ttos-menu-layout"><div class="ttos-menu-main">';
        echo '<div class="ttos-menu-toolbar"><div><h3>Menu item</h3><p class="ttos-muted">Each item becomes a normal WooCommerce product. Takeaway OS hides the retail noise and adds food configuration.</p></div><div class="ttos-preset-actions"><button type="button" class="ttos-mini ttos-apply-preset" data-preset="kebab">Kebab preset</button><button type="button" class="ttos-mini ttos-apply-preset" data-preset="pizza">Pizza preset</button><button type="button" class="ttos-mini ttos-apply-preset" data-preset="burger">Burger meal preset</button></div></div>';

        echo '<div class="ttos-grid ttos-grid-2">';
        self::field('Item name', 'product[name]', $product['name']);
        echo '<label>Category<select name="product[category]"><option value="">Choose category…</option>';
        foreach ($categories as $term) {
            echo '<option value="' . esc_attr($term->name) . '" ' . selected($product['category'], $term->name, false) . '>' . esc_html($term->name) . '</option>';
        }
        echo '</select></label>';
        self::field('Base price (£)', 'product[price]', $product['price'], 'number');
        self::field('Sale price (£)', 'product[sale_price]', $product['sale_price'], 'number');
        echo '</div>';
        echo '<label>Description<textarea name="product[description]" rows="4" placeholder="Freshly cooked, includes salad and sauce…">' . esc_textarea($product['description']) . '</textarea></label>';

        echo '<div class="ttos-grid ttos-grid-2">';
        self::media_field('Food image', 'product[image_id]', absint($product['image_id']), 'Choose food image');
        echo '<div class="ttos-stack">';
        self::field('Badges', 'product[badges]', $product['badges']);
        self::field('Spice level', 'product[spice]', $product['spice']);
        self::field('Discount / promo note', 'product[discount_note]', $product['discount_note']);
        echo '</div></div>';

        echo '<div class="ttos-grid ttos-grid-2">';
        echo '<section class="ttos-mini-card"><h3>Allergens</h3><p class="ttos-muted">Tick the 14 UK allergen groups. These are stored as product taxonomy terms and mirrored into item meta for quick display.</p><div class="ttos-check-grid">';
        if (!is_wp_error($allergen_terms)) foreach ($allergen_terms as $term) {
            echo '<label><input type="checkbox" name="product[allergen_slugs][]" value="' . esc_attr($term->slug) . '" ' . checked(in_array($term->slug, $selected_allergens, true), true, false) . '> ' . esc_html($term->name) . '</label>';
        }
        echo '</div></section>';
        echo '<section class="ttos-mini-card"><h3>Dietary / menu labels</h3><p class="ttos-muted">Used for badges and future filters.</p><div class="ttos-check-grid">';
        if (!is_wp_error($dietary_terms)) foreach ($dietary_terms as $term) {
            echo '<label><input type="checkbox" name="product[dietary][]" value="' . esc_attr($term->slug) . '" ' . checked(in_array($term->slug, $selected_dietary, true), true, false) . '> ' . esc_html($term->name) . '</label>';
        }
        echo '</div></section></div>';

        echo '<section class="ttos-option-builder" data-initial="' . esc_attr($product['option_groups']) . '">';
        echo '<div class="ttos-option-head"><div><h3>Configurator</h3><p class="ttos-muted">Build kebab sauce/salad choices, pizza crust/toppings, burger upgrades, drink sizes and meal deals. Required/min/max rules are enforced at add-to-cart, not just shown on screen.</p></div><button type="button" class="ttos-mini ttos-add-group">Add option group</button></div>';
        echo '<input type="hidden" class="ttos-option-json" name="product[option_groups]" value="' . esc_attr($product['option_groups']) . '">';
        echo '<div class="ttos-option-groups"></div>';
        echo '</section>';
        echo '</div><aside class="ttos-menu-side">';
        self::field('Sort order', 'product[sort_order]', $product['sort_order'], 'number');
        self::field('Estimated cost (£)', 'product[cost_price]', $product['cost_price'], 'number');
        self::field('Limited quantity today', 'product[limited_qty]', $product['limited_qty'], 'number');
        echo '<label>Tax status<select name="product[tax_status]"><option value="taxable" ' . selected($product['tax_status'], 'taxable', false) . '>Taxable</option><option value="none" ' . selected($product['tax_status'], 'none', false) . '>No tax</option></select></label>';
        echo '<label class="ttos-check"><input type="checkbox" name="product[featured]" value="1" ' . checked(!empty($product['featured']), true, false) . '> Featured / promoted item</label>';
        echo '<label class="ttos-check"><input type="checkbox" name="product[sold_out]" value="1" ' . checked(!empty($product['sold_out']), true, false) . '> Sold out today</label>';
        echo '<label class="ttos-check"><input type="checkbox" name="product[hidden]" value="1" ' . checked(!empty($product['hidden']), true, false) . '> Hide from menu</label>';
        echo '<div class="ttos-callout"><strong>Inventory rule:</strong> standard sites use availability and limited counts. Full ingredient stock should stay a paid EPOS/inventory connector module.</div>';
        echo '<button class="ttos-button">' . esc_html($button) . '</button>';
        if (!empty($product['product_id'])) {
            echo '<a class="ttos-mini ttos-button-dark" href="' . esc_url(get_edit_post_link((int) $product['product_id'])) . '">Advanced Woo edit</a>';
        }
        echo '</aside></div></form>';
    }

    private static function category_builder_panel(): void {
        if (!taxonomy_exists('product_cat')) return;
        echo '<section class="ttos-card"><h2>Menu sections</h2><p class="ttos-muted">Create and reorder owner-friendly menu sections. These are WooCommerce product categories underneath, but this view keeps the restaurant setup clean.</p><div class="ttos-grid ttos-grid-2"><form method="post">';
        wp_nonce_field('ttos_save_category');
        echo '<input type="hidden" name="ttos_action" value="save_category">';
        self::field('Category name', 'category[name]', '');
        self::field('Sort order', 'category[order]', '0', 'number');
        self::media_field('Category image', 'category[image_id]', 0, 'Choose category image');
        echo '<label>Description<textarea name="category[description]" rows="3"></textarea></label><button class="ttos-button">Save category</button></form>';
        echo '<div><h3>Current sections</h3><p class="ttos-muted">Drag the handles, then save the order. This controls the front-end category order.</p><form method="post" class="ttos-category-order-form">';
        wp_nonce_field('ttos_save_category_order');
        echo '<input type="hidden" name="ttos_action" value="save_category_order"><input type="hidden" class="ttos-category-order-field" name="category_order" value=""><div class="ttos-category-list ttos-sortable-categories">';
        foreach (TTOS_WooCommerce::get_product_categories() as $index => $term) {
            $thumb_id = absint(get_term_meta($term->term_id, 'thumbnail_id', true));
            $order = (int) get_term_meta($term->term_id, 'order', true);
            echo '<div class="ttos-category-pill" data-term-id="' . esc_attr((string) $term->term_id) . '" data-order="' . esc_attr((string) $order) . '"><span class="ttos-drag">☰</span>';
            echo ($thumb_id ? wp_get_attachment_image($thumb_id, 'thumbnail') : '<span class="ttos-cat-placeholder">🍟</span>');
            echo '<strong>' . esc_html($term->name) . '</strong><small>' . esc_html((string) $term->count) . ' items</small></div>';
        }
        echo '</div><button class="ttos-button ttos-save-category-order" style="margin-top:12px">Save section order</button></form></div></div></section>';
    }

    private static function save_menu_category(array $raw): void {
        $name = sanitize_text_field($raw['name'] ?? '');
        if ($name === '') return;
        $term = term_exists($name, 'product_cat');
        $args = array('description' => sanitize_textarea_field($raw['description'] ?? ''));
        if (!$term) $term = wp_insert_term($name, 'product_cat', $args);
        elseif (!is_wp_error($term)) wp_update_term(is_array($term) ? (int) $term['term_id'] : (int) $term, 'product_cat', $args);
        if (!is_wp_error($term)) {
            $term_id = is_array($term) ? (int) $term['term_id'] : (int) $term;
            update_term_meta($term_id, 'order', (int) ($raw['order'] ?? 0));
            update_term_meta($term_id, 'thumbnail_id', absint($raw['image_id'] ?? 0));
        }
    }

    private static function pages_wizard_panel(): void {
        echo '<section class="ttos-card"><div class="ttos-installer-head"><div><h2>Public pages</h2><p class="ttos-muted">Takeaway OS checks for the required shortcode/marker before calling a page ready. If an old page exists with old content, we create a fresh Takeaway page instead of overwriting it.</p></div>';
        echo '<button type="button" class="ttos-button ttos-wizard-next" data-next="payments">Next: payments →</button></div>';
        self::page_status_table();
        echo '<form method="post" class="ttos-installer-actions">';
        wp_nonce_field('ttos_generate_pages');
        echo '<input type="hidden" name="ttos_action" value="generate_pages"><input type="hidden" name="ttos_return" value="takeaway-os-launchpad"><input type="hidden" name="ttos_anchor" value="pages">';
        echo '<button class="ttos-button">Create / repair pages and assign homepage</button><span class="ttos-muted">Safe mode: old pages are not overwritten. Fresh Takeaway pages are created when content is missing, then homepage/header/footer/Woo pages are assigned.</span></form>';
        echo '</section>';
    }

    private static function page_status_table(): void {
        echo '<table class="ttos-table ttos-page-table"><thead><tr><th>Page</th><th>Status</th><th>Used page</th><th>Action</th></tr></thead><tbody>';
        foreach (TTOS_Page_Manager::specs() as $key => $spec) {
            $status = TTOS_Page_Manager::status($key);
            $id = (int) $status['id'];
            $class = $status['state'] === 'ready' ? 'ttos-good' : ($status['state'] === 'missing' ? 'ttos-bad' : 'ttos-warn');
            echo '<tr><td><strong>' . esc_html($spec['label']) . '</strong><br><small>' . esc_html($spec['content']) . '</small></td>';
            echo '<td><span class="' . esc_attr($class) . '">' . esc_html($status['message']) . '</span></td>';
            if ($id) {
                echo '<td><a href="' . esc_url(get_edit_post_link($id)) . '">' . esc_html(get_the_title($id)) . '</a><br><small>ID ' . esc_html((string) $id) . '</small></td>';
                echo '<td><a class="ttos-mini" target="_blank" href="' . esc_url(get_permalink($id)) . '">View</a></td>';
            } else {
                echo '<td><span class="ttos-muted">Not created yet</span></td><td><span class="ttos-muted">Will create</span></td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private static function payments_wizard_panel(): void {
        echo '<section class="ttos-card ttos-installer"><div class="ttos-installer-head"><div><p class="ttos-eyebrow">Payments</p><h2>Payment and email tools</h2><p class="ttos-muted">WooCommerce must be active and loaded before the Stripe gateway activates. If WooCommerce was just installed, this screen will refresh first.</p></div><button type="button" class="ttos-button ttos-wizard-next" data-next="golive">Next: go live →</button></div>';
        echo '<div class="ttos-plugin-section-title"><span>Recommended tools</span><small>Optional, but useful for a real restaurant install</small></div><div class="ttos-plugin-list">';
        foreach (array('stripe','fluent-smtp') as $key) {
            $plugins = TTOS_Plugin_Checker::plugins();
            if (isset($plugins[$key])) self::plugin_installer_row($key, $plugins[$key]);
        }
        echo '</div><div class="ttos-progress-wrap" aria-live="polite"><div class="ttos-progress"><span style="width:0%"></span></div><strong class="ttos-progress-label">Ready</strong></div>';
        echo '<p class="ttos-muted">After the gateway plugin is active, configure live/test keys in WooCommerce. Later we can replace this with a cleaner branded settings bridge.</p>';
        echo '<p><a class="ttos-button" href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout')) . '">Open WooCommerce payment settings</a></p></section>';
    }

    private static function go_live_panel(): void {
        echo '<section class="ttos-card"><p class="ttos-eyebrow">Final checks</p><h2>Go live checklist</h2><ul class="ttos-go-live">';
        echo '<li><span>Required plugins active</span><strong class="' . esc_attr(TTOS_Plugin_Checker::required_complete() ? 'ttos-good' : 'ttos-bad') . '">' . esc_html(TTOS_Plugin_Checker::required_complete() ? 'Ready' : 'Needs work') . '</strong></li>';
        echo '<li><span>Business details saved</span><strong>Check</strong></li><li><span>Branding saved</span><strong>Check</strong></li><li><span>Delivery/collection rules saved</span><strong>Check</strong></li><li><span>Test product created</span><strong>Check menu</strong></li><li><span>Test checkout/order email</span><strong>Run before launch</strong></li>';
        echo '</ul><div class="ttos-installer-actions"><a class="ttos-button" href="' . esc_url(home_url('/')) . '" target="_blank">View site ↗</a><a class="ttos-button ttos-button-dark" href="' . esc_url(admin_url('admin.php?page=takeaway-os')) . '">Open dashboard</a></div></section>';
    }

    public static function page_settings(): void {
        self::shell_start('Settings / Branding', 'The essentials owners should be allowed to edit without seeing WordPress internals.');
        $business = TTOS_Settings::get('business');
        echo '<div class="ttos-grid ttos-grid-2"><section class="ttos-card"><h2>Business details</h2><form method="post">';
        wp_nonce_field('ttos_save_business');
        echo '<input type="hidden" name="ttos_action" value="save_business">';
        self::field('Restaurant name', 'business[restaurant_name]', $business['restaurant_name']);
        self::field('Tagline', 'business[tagline]', $business['tagline']);
        self::field('Phone', 'business[phone]', $business['phone']);
        self::field('Email', 'business[email]', $business['email'], 'email');
        self::field('Address line 1', 'business[address_1]', $business['address_1']);
        self::field('Address line 2', 'business[address_2]', $business['address_2']);
        self::field('Town', 'business[town]', $business['town']);
        self::field('Postcode', 'business[postcode]', $business['postcode']);
        self::field('Cuisine type', 'business[cuisine]', $business['cuisine']);
        self::field('Food hygiene rating', 'business[fsa_rating]', $business['fsa_rating']);
        self::field('VAT number', 'business[vat_number]', $business['vat_number']);
        echo '<button class="ttos-button">Save business</button></form></section></div>';

        // Branding uses the shared token-aware form (also used by Launchpad)
        // and gets the full width — the colour grid and preview need it.
        self::branding_form('takeaway-os-settings', 'branding');
        TTOS_Client_Intake::render_settings_panel();

        if (current_user_can('manage_options')) {
            $retention = TTOS_Settings::get('data_retention');
            echo '<section class="ttos-card"><h2>Data retention & uninstall</h2><p class="ttos-muted">Choose what happens when Takeaway OS is deleted. By default, restaurant data stays on the server so a mistaken delete does not wipe orders, products or settings.</p><form method="post">';
            wp_nonce_field('ttos_save_data_retention');
            echo '<input type="hidden" name="ttos_action" value="save_data_retention">';
            echo '<label class="ttos-check"><input type="checkbox" name="data_retention[erase_on_uninstall]" value="1" ' . checked($retention['erase_on_uninstall'], '1', false) . '> Erase Takeaway OS data when the plugin is deleted</label>';
            echo '<p class="ttos-muted">The options below only run if the erase switch above is enabled.</p>';
            echo '<div class="ttos-grid ttos-grid-2">';
            echo '<label class="ttos-check"><input type="checkbox" name="data_retention[delete_generated_pages]" value="1" ' . checked($retention['delete_generated_pages'], '1', false) . '> Delete Takeaway-generated pages</label>';
            echo '<label class="ttos-check"><input type="checkbox" name="data_retention[delete_menu_products]" value="1" ' . checked($retention['delete_menu_products'], '1', false) . '> Delete Takeaway menu products</label>';
            echo '<label class="ttos-check"><input type="checkbox" name="data_retention[delete_generated_coupons]" value="1" ' . checked($retention['delete_generated_coupons'], '1', false) . '> Delete generated loyalty/campaign coupons</label>';
            echo '<label class="ttos-check"><input type="checkbox" name="data_retention[delete_customer_meta]" value="1" ' . checked($retention['delete_customer_meta'], '1', false) . '> Delete Takeaway customer notes/tags/points</label>';
            echo '<label class="ttos-check"><input type="checkbox" name="data_retention[delete_roles]" value="1" ' . checked($retention['delete_roles'], '1', false) . '> Remove Takeaway OS roles and capabilities</label>';
            echo '</div><div class="ttos-callout"><strong>Safe default:</strong> leave erase off for client installs. Turn it on only when you intentionally want a clean uninstall.</div><button class="ttos-button">Save uninstall settings</button></form></section>';
        }

        self::shell_end();
    }

    public static function page_delivery(): void {
        self::shell_start('Delivery & collection', 'Availability without EPOS, simple trading rules, and customer-facing delivery promises.');
        $trading = TTOS_Settings::get('trading');
        echo '<section class="ttos-card"><form method="post">';
        wp_nonce_field('ttos_save_trading');
        echo '<input type="hidden" name="ttos_action" value="save_trading"><div class="ttos-grid ttos-grid-3">';
        self::field('Minimum order (£)', 'trading[min_order]', $trading['min_order']);
        self::field('Delivery fee (£)', 'trading[delivery_fee]', $trading['delivery_fee']);
        self::field('Free delivery over (£)', 'trading[free_delivery_over]', $trading['free_delivery_over']);
        self::field('Delivery radius (miles)', 'trading[delivery_radius]', $trading['delivery_radius']);
        self::field('Prep time (mins)', 'trading[prep_time]', $trading['prep_time']);
        self::field('Delivery promise (mins)', 'trading[delivery_time]', $trading['delivery_time']);
        self::field('Service charge (%)', 'trading[service_charge]', $trading['service_charge'] ?? '0', 'number');
        echo '</div>';
        echo '<label>Delivery postcode notes<textarea name="trading[delivery_postcodes]" rows="5">' . esc_textarea($trading['delivery_postcodes']) . '</textarea></label>';
        echo '<label class="ttos-check"><input type="checkbox" name="trading[delivery_enabled]" value="1" ' . checked($trading['delivery_enabled'], '1', false) . '> Delivery enabled</label>';
        echo '<label class="ttos-check"><input type="checkbox" name="trading[collection_enabled]" value="1" ' . checked($trading['collection_enabled'], '1', false) . '> Collection enabled</label>';
        echo '<button class="ttos-button">Save delivery rules</button></form></section>';
        self::shell_end();
    }

    public static function page_payments(): void {
        self::shell_start('Payments', 'Gateway status lives here. Raw WooCommerce gateway configuration stays restricted to full administrators.');
        echo '<section class="ttos-card"><h2>Gateway checklist</h2>';
        foreach (array('woocommerce','stripe') as $key) self::plugin_row(TTOS_Plugin_Checker::plugins()[$key], true);
        echo '<div class="ttos-callout"><strong>Recommended:</strong> use this screen for readiness checks and owner-safe status. Live gateway setup, keys, and advanced WooCommerce payment config should be handled by a full administrator.</div>';
        if (current_user_can('manage_options')) {
            echo '<p><a class="ttos-button" href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout')) . '">Open WooCommerce payment settings</a></p>';
        } else {
            echo '<p class="ttos-muted">Gateway configuration is administrator-only. Owners can monitor readiness here without leaving the Takeaway OS portal.</p>';
        }
        echo '</section>';
        self::shell_end();
    }

    public static function page_modules(): void {
        self::shell_start('Add-ons', 'Admin-only switchboard for paid modules. Anything unfinished is clearly marked and kept out of client hands.');
        $locked = self::module_lock_exists();
        $unlocked = self::modules_unlocked();

        if ($locked && !$unlocked) {
            echo '<section class="ttos-card ttos-lock-card"><p class="ttos-eyebrow">Protected</p><h2>Add-ons are locked</h2><p class="ttos-muted">Enter the module key to change paid feature access. Restaurant owners can use enabled features, but they cannot unlock unfinished or unpaid modules.</p><form method="post">';
            wp_nonce_field('ttos_unlock_modules');
            echo '<input type="hidden" name="ttos_action" value="unlock_modules">';
            self::field('Module key', 'module_key', '', 'password');
            echo '<button class="ttos-button">Unlock add-ons</button></form></section>';
            self::shell_end();
            return;
        }

        $modules = TTOS_Settings::modules();
        $catalog = self::module_catalog();
        echo '<div class="ttos-grid ttos-grid-2"><section class="ttos-card"><h2>Paid feature switchboard</h2><p class="ttos-muted">Only enabled modules are shown to the client-facing CRM. Use this as your quote/build toggle panel.</p><form method="post">';
        wp_nonce_field('ttos_save_modules');
        echo '<input type="hidden" name="ttos_action" value="save_modules"><div class="ttos-modules">';
        foreach ($catalog as $slug => $info) {
            $enabled = !empty($modules[$slug]);
            $toggleable = !empty($info['toggleable']);
            $state_label = $enabled ? 'Enabled' : ($toggleable ? 'Off' : 'Locked');
            echo '<label class="ttos-module ' . esc_attr($enabled ? 'is-enabled' : 'is-disabled') . '">';
            echo '<input type="checkbox" name="modules[' . esc_attr($slug) . ']" value="1" ' . checked($enabled, true, false) . ' ' . disabled($toggleable, false, false) . '>';
            echo '<i class="ttos-switch" aria-hidden="true"><em></em></i><span><strong>' . esc_html($info['name']) . '</strong><small>' . esc_html($info['description']) . '</small><small>' . esc_html($info['note']) . '</small></span><b>' . esc_html($info['price']) . '</b><u class="ttos-module-state">' . esc_html($state_label . ' · ' . $info['status']) . '</u></label>';
        }
        echo '</div><button class="ttos-button">Save add-ons</button></form></section>';

        echo '<section class="ttos-card"><h2>Affiliate / signup links</h2><p class="ttos-muted">Use your agency/referral URLs here. The setup screens can send clients to the right service through your link where the vendor allows affiliate referrals.</p><form method="post">';
        wp_nonce_field('ttos_save_service_links');
        echo '<input type="hidden" name="ttos_action" value="save_service_links">';
        foreach (TTOS_Settings::get('service_links') as $key => $url) self::field(ucwords(str_replace('_', ' ', $key)), 'service_links[' . $key . ']', $url, 'url');
        echo '<button class="ttos-button">Save signup links</button></form></section>';

        echo '<section class="ttos-card"><h2>Handover lock</h2><p class="ttos-muted">Set or change the module key when the site is ready to hand over. This is a product lock for normal users, not a military bunker if someone has server/database access.</p><form method="post">';
        wp_nonce_field('ttos_save_module_lock');
        echo '<input type="hidden" name="ttos_action" value="save_module_lock">';
        self::field($locked ? 'Change module key' : 'Create module key', 'module_key_new', '', 'password');
        echo '<button class="ttos-button">' . esc_html($locked ? 'Change lock key' : 'Create lock key') . '</button></form>';
        if ($locked) {
            echo '<form method="post" class="ttos-lock-now">';
            wp_nonce_field('ttos_lock_modules');
            echo '<input type="hidden" name="ttos_action" value="lock_modules"><button class="ttos-button ttos-button-dark">Lock add-ons now</button></form>';
        }
        echo '<div class="ttos-callout"><strong>Recommended handover:</strong> give clients the Takeaway Owner role, not full Administrator. Full administrators can always edit files/plugins, so the role model is part of the lock.</div></section></div>';
        self::shell_end();
    }

    public static function page_menu_builder(): void {
        self::shell_start('Menu builder', 'A simple food-first layer over WooCommerce products, built for quick menu edits.');
        if (!TTOS_WooCommerce::active()) {
            echo '<section class="ttos-card"><h2>WooCommerce required</h2><p>Install and activate WooCommerce before creating menu items.</p></section>';
            self::shell_end();
            return;
        }
        $product_id = absint($_GET['product_id'] ?? 0);
        self::category_builder_panel();
        echo '<section class="ttos-card"><h2>' . esc_html($product_id ? 'Edit menu item' : 'Add menu item') . '</h2><p class="ttos-muted">Production menu builder: image, description, categories, allergens, dietary labels, discounts, limited availability and configurable choices all mapped safely onto WooCommerce.</p>';
        self::menu_form('', '', $product_id ? 'Update menu item' : 'Save menu item', $product_id);
        echo '</section>';
        echo '<section class="ttos-card"><h2>Current menu</h2><p class="ttos-muted">Grouped by category. Select rows for bulk actions, drag items by changing sort numbers, or duplicate a good item as a starter template.</p>';
        self::menu_bulk_toolbar();
        self::product_table();
        echo '</section>';
        self::shell_end();
    }


    private static function menu_bulk_toolbar(): void {
        echo '<div class="ttos-menu-bulkbar"><form method="post" class="ttos-menu-bulk-form">';
        wp_nonce_field('ttos_bulk_menu_action');
        echo '<input type="hidden" name="ttos_action" value="bulk_menu_action"><input type="hidden" class="ttos-selected-products" name="product_ids" value="">';
        echo '<select name="bulk_action"><option value="available">Mark available</option><option value="soldout">Mark sold out today</option><option value="hide">Hide from menu</option><option value="show">Show on menu</option><option value="feature">Mark featured</option><option value="unfeature">Remove featured</option></select><button class="ttos-button">Apply to selected</button><span class="ttos-muted ttos-selected-count">No items selected</span></form>';
        echo '<form method="post" class="ttos-product-order-form">';
        wp_nonce_field('ttos_save_product_order');
        echo '<input type="hidden" name="ttos_action" value="save_product_order"><input type="hidden" class="ttos-product-order-field" name="product_order" value=""><button class="ttos-button ttos-button-dark">Save item sort order</button></form></div>';
    }

    private static function product_table(): void {
        $cats = TTOS_WooCommerce::get_product_categories();
        $printed = false;
        if ($cats) {
            foreach ($cats as $cat) {
                $q = new WP_Query(array(
                    'post_type' => 'product', 'posts_per_page' => 100, 'post_status' => array('publish','draft'),
                    'orderby' => array('menu_order' => 'ASC', 'title' => 'ASC'),
                    'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cat->term_id)),
                ));
                if (!$q->have_posts()) continue;
                $printed = true;
                echo '<h3 class="ttos-menu-group-title">' . esc_html($cat->name) . '</h3>';
                self::product_table_loop($q);
            }
        }
        if (!$printed) {
            $q = new WP_Query(array('post_type' => 'product', 'posts_per_page' => 100, 'post_status' => array('publish','draft'), 'orderby' => array('menu_order' => 'ASC', 'title' => 'ASC')));
            if (!$q->have_posts()) { echo '<p class="ttos-muted">No menu items yet.</p>'; return; }
            self::product_table_loop($q);
        }
    }

    private static function product_table_loop(WP_Query $q): void {
        echo '<table class="ttos-table ttos-menu-table"><thead><tr><th><input type="checkbox" class="ttos-product-select-all" aria-label="Select all items"></th><th>Sort</th><th>Item</th><th>Price</th><th>Labels</th><th>Status</th><th>Quick actions</th></tr></thead><tbody>';
        while ($q->have_posts()) { $q->the_post();
            $id = get_the_ID();
            $price = get_post_meta($id, '_price', true);
            $stock = get_post_meta($id, '_stock_status', true);
            $menu_order = (int) get_post_field('menu_order', $id);
            $edit_url = add_query_arg(array('page' => 'takeaway-os-menu', 'product_id' => $id), admin_url('admin.php'));
            $diet = taxonomy_exists('ttos_dietary') ? wp_get_object_terms($id, 'ttos_dietary', array('fields' => 'names')) : array();
            $opts = TTOS_WooCommerce::get_option_groups($id);
            $featured = get_post_meta($id, '_featured', true) === 'yes';
            echo '<tr class="ttos-product-row" data-product-id="' . esc_attr((string) $id) . '"><td><input type="checkbox" class="ttos-product-select" value="' . esc_attr((string) $id) . '" aria-label="Select ' . esc_attr(get_the_title()) . '"></td>';
            echo '<td><input type="number" class="ttos-sort-input" value="' . esc_attr((string) $menu_order) . '" aria-label="Sort order"></td>';
            echo '<td><strong>' . esc_html(get_the_title()) . '</strong><br><small>ID ' . esc_html((string) $id) . ' · ' . esc_html(count($opts)) . ' config group(s)' . ($featured ? ' · Featured' : '') . '</small></td><td>' . esc_html(self::money($price)) . '</td><td>' . esc_html(!is_wp_error($diet) && $diet ? implode(', ', $diet) : '—') . '</td><td>' . ($stock === 'outofstock' ? '<span class="ttos-bad">Sold out</span>' : '<span class="ttos-good">Available</span>') . (get_post_status($id) === 'draft' ? '<br><span class="ttos-warn">Hidden</span>' : '') . '</td><td><div class="ttos-table-actions"><a class="ttos-mini" href="' . esc_url($edit_url) . '">Edit</a>';
            foreach (array('available' => 'Available', 'soldout' => 'Sold out', get_post_status($id) === 'draft' ? 'show' : 'hide' => get_post_status($id) === 'draft' ? 'Show' : 'Hide') as $status => $label) {
                echo '<form method="post" class="ttos-inline-form">'; wp_nonce_field('ttos_quick_product_status');
                echo '<input type="hidden" name="ttos_action" value="quick_product_status"><input type="hidden" name="product_id" value="' . esc_attr((string) $id) . '"><input type="hidden" name="quick_status" value="' . esc_attr($status) . '"><button class="ttos-mini">' . esc_html($label) . '</button></form>';
            }
            echo '<form method="post" class="ttos-inline-form">'; wp_nonce_field('ttos_duplicate_product');
            echo '<input type="hidden" name="ttos_action" value="duplicate_product"><input type="hidden" name="product_id" value="' . esc_attr((string) $id) . '"><button class="ttos-mini ttos-button-dark">Duplicate</button></form>';
            echo '</div></td></tr>';
        }
        wp_reset_postdata();
        echo '</tbody></table>';
    }

    public static function page_orders(): void {
        self::shell_start('Orders', 'Live order cockpit with filters, prep timers, manual resend and late-order warnings. Keep this open during service.');
        if (!TTOS_WooCommerce::active()) {
            echo '<section class="ttos-card"><p>WooCommerce is required for orders.</p></section>';
            self::shell_end(); return;
        }
        self::order_cockpit_toolbar('orders');
        echo '<div class="ttos-order-board" data-refresh="1" data-context="orders">' . self::order_board_html('orders') . '</div>';
        self::shell_end();
    }

    public static function page_kitchen(): void {
        self::shell_start('Takeaway Tickets', 'Live kitchen ticket board with big order cards, prep timers, sound alerts and one-tap status changes.');
        if (!TTOS_WooCommerce::active()) {
            echo '<section class="ttos-card"><p>WooCommerce is required for the Takeaway Tickets board.</p></section>';
            self::shell_end(); return;
        }
        if (self::tickets_fullscreen()) {
            echo '<style>#wpadminbar,#adminmenumain,#wpfooter,.ttos-primary-nav,.ttos-admin-header{display:none!important;}#wpcontent,#wpbody-content{margin:0!important;padding:0!important;} .ttos-wrap{padding:0!important;max-width:none!important;} .ttos-shell{border:none!important;border-radius:0!important;box-shadow:none!important;min-height:100vh;} .ttos-kitchen-mode{padding:20px;}</style>';
        }
        echo '<div class="ttos-kitchen-mode">';
        self::order_cockpit_toolbar('kitchen');
        echo '<div class="ttos-order-board" data-refresh="1" data-context="kitchen">' . self::order_board_html('kitchen') . '</div>';
        echo '</div>';
        self::shell_end();
    }

    private static function order_cockpit_toolbar(string $context = 'orders'): void {
        echo '<section class="ttos-card ttos-order-cockpit-head"><div><h2>' . ($context === 'kitchen' ? 'Live ticket board' : 'Live order cockpit') . '</h2><p class="ttos-muted">New orders ping, prep timers warn, and every action logs into Operations. Friday night, but with fewer paper comets.</p></div>';
        echo '<div class="ttos-order-tools">';
        echo '<label class="ttos-check ttos-autorefresh"><input type="checkbox" class="ttos-order-autorefresh" checked> Auto-refresh</label>';
        echo '<label class="ttos-check"><input type="checkbox" class="ttos-order-sound" checked> Sound alert</label>';
        echo '<label>Fulfilment<select class="ttos-order-filter" data-filter="method"><option value="all">All</option><option value="delivery">Delivery</option><option value="collection">Collection</option></select></label>';
        echo '<label>Timing<select class="ttos-order-filter" data-filter="timing"><option value="all">All</option><option value="asap">ASAP</option><option value="scheduled">Scheduled</option></select></label>';
        if ($context === 'kitchen') {
            echo '<a class="ttos-mini" href="' . esc_url(admin_url('admin.php?page=takeaway-os-kitchen&ttos_view=fullscreen')) . '" target="_blank" rel="noreferrer noopener">Open full-screen</a>';
        }
        echo '<button type="button" class="ttos-mini ttos-test-alert">Test ping</button>';
        echo '<a class="ttos-mini" href="' . esc_url(admin_url('admin.php?page=takeaway-os-golive#logs')) . '">Logs</a>';
        echo '</div></section>';
    }

    public static function ajax_order_board(): void {
        if (!current_user_can('ttos_view_orders') || !check_ajax_referer('ttos_order_board', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Not allowed.'), 403);
        }
        $context = sanitize_key(wp_unslash($_POST['context'] ?? 'orders'));
        $method = sanitize_key(wp_unslash($_POST['method'] ?? 'all'));
        $timing = sanitize_key(wp_unslash($_POST['timing'] ?? 'all'));
        $counts = self::order_board_counts($method, $timing);
        wp_send_json_success(array('html' => self::order_board_html($context, $method, $timing), 'time' => current_time('H:i:s'), 'counts' => $counts));
    }

    public static function ajax_order_action(): void {
        if (!current_user_can('ttos_update_orders') || !check_ajax_referer('ttos_order_action', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Not allowed.'), 403);
        }
        $order_id = absint(wp_unslash($_POST['order_id'] ?? 0));
        $status = sanitize_key(wp_unslash($_POST['status'] ?? ''));
        $prep = absint(wp_unslash($_POST['prep_minutes'] ?? 0));
        $note = sanitize_textarea_field(wp_unslash($_POST['kitchen_note'] ?? ''));
        $resend = !empty($_POST['resend_integrations']);
        if (!$order_id || !TTOS_WooCommerce::active()) {
            wp_send_json_error(array('message' => 'Missing order or WooCommerce.'), 400);
        }
        $result = self::apply_order_action($order_id, $status, $prep, $note, $resend);
        if (!$result['ok']) {
            wp_send_json_error(array('message' => $result['message']), 400);
        }
        $context = sanitize_key(wp_unslash($_POST['context'] ?? 'orders'));
        $method = sanitize_key(wp_unslash($_POST['method_filter'] ?? 'all'));
        $timing = sanitize_key(wp_unslash($_POST['timing_filter'] ?? 'all'));
        wp_send_json_success(array('message' => $result['message'], 'html' => self::order_board_html($context, $method, $timing), 'counts' => self::order_board_counts($method, $timing)));
    }

    private static function apply_order_action(int $order_id, string $status = '', int $prep = 0, string $note = '', bool $resend = false): array {
        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        if (!$order) return array('ok' => false, 'message' => 'Order not found.');
        if ($note !== '') {
            $order->update_meta_data('_ttos_kitchen_note', $note);
        }
        if ($prep > 0) {
            $order->update_meta_data('_ttos_prep_minutes', $prep);
            $order->update_meta_data('_ttos_due_ts', time() + ($prep * MINUTE_IN_SECONDS));
        }
        if ($status === 'cancelled') {
            $order->update_status('cancelled', 'Rejected from Takeaway OS.');
            self::log_operation('order', 'Order #' . $order_id . ' rejected/cancelled from cockpit.');
        } elseif ($status !== '') {
            $valid = array('pending','processing','on-hold','ttos-accepted','ttos-prepping','ttos-ready','ttos-out','completed','cancelled');
            if (!in_array($status, $valid, true)) return array('ok' => false, 'message' => 'Invalid order status.');
            $order->update_status($status, 'Updated from Takeaway OS cockpit.');
            self::log_operation('order', 'Order #' . $order_id . ' moved to ' . wc_get_order_status_name($status) . ($prep ? ' · prep ' . $prep . 'm' : ''));
        } else {
            $order->save();
        }
        if ($resend && class_exists('TTOS_Features')) {
            TTOS_Features::resend_order_integrations($order_id, 'manual_resend');
            self::log_operation('integration', 'Manual resend requested for order #' . $order_id . '.');
        }
        return array('ok' => true, 'message' => 'Order #' . $order_id . ' updated.');
    }

    private static function order_board_counts(string $method = 'all', string $timing = 'all'): array {
        $out = array('new' => 0, 'active' => 0, 'late' => 0);
        if (!TTOS_WooCommerce::active()) return $out;
        $orders = wc_get_orders(array('limit' => 100, 'orderby' => 'date', 'order' => 'DESC', 'status' => array('pending','processing','on-hold','ttos-accepted','ttos-prepping','ttos-ready','ttos-out'), 'return' => 'objects'));
        foreach ($orders as $order) {
            if (!self::order_matches_filters($order, $method, $timing)) continue;
            $status = $order->get_status();
            if (in_array($status, array('pending','processing','on-hold'), true)) $out['new']++;
            if (!in_array($status, array('completed','cancelled','refunded','failed'), true)) $out['active']++;
            if (self::order_is_late($order)) $out['late']++;
        }
        return $out;
    }

    private static function order_board_html(string $context = 'orders', string $method = 'all', string $timing = 'all'): string {
        if (!TTOS_WooCommerce::active()) return '<section class="ttos-card"><p>WooCommerce is required.</p></section>';
        $statuses = array(
            'new' => array('label' => 'New', 'query' => array('pending','processing','on-hold')),
            'accepted' => array('label' => 'Accepted', 'query' => array('ttos-accepted')),
            'prepping' => array('label' => 'Preparing', 'query' => array('ttos-prepping')),
            'ready' => array('label' => 'Ready / out', 'query' => array('ttos-ready','ttos-out')),
        );
        if ($context === 'kitchen') {
            $statuses = array(
                'new' => array('label' => 'New', 'query' => array('pending','processing','on-hold')),
                'prepping' => array('label' => 'Cooking', 'query' => array('ttos-accepted','ttos-prepping')),
                'ready' => array('label' => 'Ready', 'query' => array('ttos-ready')),
                'out' => array('label' => 'Out / done', 'query' => array('ttos-out')),
            );
        }
        ob_start();
        $counts = self::order_board_counts($method, $timing);
        echo '<div class="ttos-order-board-meta"><span>Last loaded: ' . esc_html(current_time('H:i:s')) . '</span><span>' . esc_html((string) $counts['active']) . ' active</span><span class="' . esc_attr($counts['late'] ? 'ttos-bad' : 'ttos-good') . '">' . esc_html((string) $counts['late']) . ' late</span></div><div class="ttos-order-columns ' . esc_attr($context === 'kitchen' ? 'is-kitchen' : '') . '">';
        foreach ($statuses as $key => $group) {
            $orders = wc_get_orders(array('limit' => 25, 'orderby' => 'date', 'order' => 'ASC', 'status' => $group['query'], 'return' => 'objects'));
            $orders = array_values(array_filter($orders, function($order) use ($method, $timing) { return self::order_matches_filters($order, $method, $timing); }));
            echo '<section class="ttos-order-column ttos-order-column-' . esc_attr($key) . '"><h2>' . esc_html($group['label']) . ' <span>' . esc_html((string) count($orders)) . '</span></h2>';
            if (!$orders) echo '<p class="ttos-muted ttos-empty-column">Nothing here.</p>';
            foreach ($orders as $order) self::order_card($order, $context);
            echo '</section>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    private static function order_matches_filters($order, string $method = 'all', string $timing = 'all'): bool {
        $fulfilment = self::order_fulfilment_method($order);
        if (in_array($method, array('delivery','collection'), true) && $fulfilment !== $method) return false;
        $requested = (string) $order->get_meta('_ttos_requested_time');
        if ($timing === 'asap' && $requested !== 'asap') return false;
        if ($timing === 'scheduled' && ($requested === '' || $requested === 'asap')) return false;
        return true;
    }

    private static function order_card($order, string $context = 'orders'): void {
        $status = $order->get_status();
        $created = $order->get_date_created();
        $minutes = $created ? floor((time() - $created->getTimestamp()) / 60) : 0;
        $fulfilment = self::order_fulfilment_method($order);
        $requested = (string) $order->get_meta('_ttos_requested_time');
        $prep = (int) $order->get_meta('_ttos_prep_minutes');
        $due_ts = (int) $order->get_meta('_ttos_due_ts');
        $is_preorder = $order->get_meta('_ttos_is_preorder') === '1';
        $late = self::order_is_late($order);
        $newish = in_array($status, array('pending','processing','on-hold'), true) && $minutes <= 5;
        $classes = array('ttos-order-card', 'status-' . $status, 'method-' . $fulfilment);
        if ($late) $classes[] = 'is-late';
        if ($newish) $classes[] = 'is-new';
        echo '<article class="' . esc_attr(implode(' ', $classes)) . '" data-order-id="' . esc_attr((string) $order->get_id()) . '"><header><div><strong>#' . esc_html((string) $order->get_id()) . '</strong><small>' . esc_html($created ? $created->date_i18n('H:i') : '') . ' · ' . esc_html((string) $minutes) . 'm ago</small></div><b>' . wp_kses_post($order->get_formatted_order_total()) . '</b></header>';
        echo '<div class="ttos-order-badges"><span class="ttos-fulfilment ' . esc_attr($fulfilment) . '">' . esc_html(ucfirst($fulfilment)) . '</span><span>' . esc_html(self::format_requested_time($requested)) . '</span>' . ($is_preorder ? '<span class="ttos-good">Pre-order</span>' : '') . '<span>' . esc_html(wc_get_order_status_name($status)) . '</span><span>' . esc_html(self::payment_status_label($order)) . '</span><span>' . esc_html($order->get_payment_method_title() ?: 'Payment pending') . '</span>' . ($prep ? '<span>Prep ' . esc_html((string) $prep) . 'm</span>' : '') . ($due_ts ? '<span class="' . esc_attr($late ? 'ttos-bad' : 'ttos-good') . '">' . esc_html(self::due_label($due_ts)) . '</span>' : '') . '</div>';
        echo '<p class="ttos-order-customer">' . esc_html($order->get_formatted_billing_full_name() ?: 'Guest customer') . '<br><small>' . esc_html($order->get_billing_phone()) . ($order->get_billing_email() ? ' · ' . esc_html($order->get_billing_email()) : '') . '</small></p>';
        if ($fulfilment === 'delivery') {
            $address = $order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address();
            if ($address) echo '<div class="ttos-order-address">' . wp_kses_post($address) . '</div>';
        }
        echo '<ul class="ttos-order-items">';
        foreach ($order->get_items() as $item) {
            echo '<li><span>' . esc_html((string) $item->get_quantity()) . '×</span><strong>' . esc_html($item->get_name()) . '</strong>';
            $meta_lines = array();
            foreach ($item->get_meta_data() as $meta) {
                $key = is_object($meta) ? $meta->key : '';
                $value = is_object($meta) ? $meta->value : '';
                if ($key === '' || strpos($key, '_') === 0) continue;
                if (is_array($value) || is_object($value)) continue;
                $meta_lines[] = $key . ': ' . $value;
            }
            if ($meta_lines) echo '<small>' . esc_html(implode(' · ', $meta_lines)) . '</small>';
            echo '</li>';
        }
        echo '</ul>';
        if ($order->get_customer_note()) echo '<div class="ttos-order-note">Customer note: ' . esc_html($order->get_customer_note()) . '</div>';
        $kitchen_note = (string) $order->get_meta('_ttos_kitchen_note');
        if ($kitchen_note) echo '<div class="ttos-order-note ttos-kitchen-note">Kitchen note: ' . esc_html($kitchen_note) . '</div>';
        self::order_action_form($order, $context);
        echo '</article>';
    }

    private static function order_action_form($order, string $context = 'orders'): void {
        $status = $order->get_status();
        $order_id = (int) $order->get_id();
        echo '<form method="post" class="ttos-order-actions ttos-order-action-form">';
        wp_nonce_field('ttos_update_order_status');
        echo '<input type="hidden" name="ttos_action" value="update_order_status"><input type="hidden" name="order_id" value="' . esc_attr((string) $order_id) . '"><input type="hidden" name="context" value="' . esc_attr($context) . '">';
        echo '<input type="hidden" name="prep_minutes" class="ttos-prep-field" value="20">';
        if (in_array($status, array('pending','processing','on-hold'), true)) {
            echo '<div class="ttos-prep-picks"><span>Accept with prep:</span>';
            foreach (array(15,20,30,45,60) as $mins) echo '<button type="submit" class="ttos-mini ttos-prep-pick" name="status" value="ttos-accepted" data-prep="' . esc_attr((string) $mins) . '">' . esc_html((string) $mins) . 'm</button>';
            echo '<button type="submit" class="ttos-mini ttos-reject" name="status" value="cancelled">Reject</button></div>';
        } elseif ($status === 'ttos-accepted') {
            echo '<button class="ttos-mini" name="status" value="ttos-prepping">Start prep</button><button class="ttos-mini" name="status" value="ttos-ready">Mark ready</button>';
        } elseif ($status === 'ttos-prepping') {
            echo '<button class="ttos-mini" name="status" value="ttos-ready">Ready</button>';
        } elseif ($status === 'ttos-ready') {
            echo '<button class="ttos-mini" name="status" value="ttos-out">Out for delivery</button><button class="ttos-mini" name="status" value="completed">Collected / done</button>';
        } elseif ($status === 'ttos-out') {
            echo '<button class="ttos-mini" name="status" value="completed">Delivered / done</button>';
        } else {
            echo '<button class="ttos-mini" name="status" value="ttos-accepted">Reopen</button>';
        }
        echo '<button class="ttos-mini ttos-button-dark" name="resend_integrations" value="1">Re-send</button>';
        echo '<details class="ttos-order-details"><summary>Kitchen note</summary><textarea name="kitchen_note" rows="2" placeholder="Internal note, e.g. call customer, no salad, check payment.">' . esc_textarea((string) $order->get_meta('_ttos_kitchen_note')) . '</textarea><button class="ttos-mini" name="status" value="">Save note</button></details>';
        echo '</form>';
    }

    private static function order_fulfilment_method($order): string {
        $method = (string) $order->get_meta('_ttos_fulfilment_method');
        if (in_array($method, array('delivery','collection'), true)) return $method;
        $shipping = strtolower((string) $order->get_shipping_method());
        return strpos($shipping, 'collection') !== false || strpos($shipping, 'pickup') !== false ? 'collection' : 'delivery';
    }

    private static function format_requested_time(string $requested): string {
        if ($requested === '' || $requested === 'asap') return 'ASAP';
        $ts = strtotime($requested);
        return $ts ? date_i18n('D H:i', $ts) : $requested;
    }

    private static function order_is_late($order): bool {
        $status = $order->get_status();
        if (in_array($status, array('completed','cancelled','refunded','failed'), true)) return false;
        $due_ts = (int) $order->get_meta('_ttos_due_ts');
        if ($due_ts > 0) return time() > ($due_ts + (5 * MINUTE_IN_SECONDS));
        $created = $order->get_date_created();
        $prep = (int) $order->get_meta('_ttos_prep_minutes');
        if (!$created || $prep <= 0) return false;
        return time() > ($created->getTimestamp() + (($prep + 10) * MINUTE_IN_SECONDS));
    }

    private static function due_label(int $due_ts): string {
        $diff = $due_ts - time();
        if ($diff >= 0) return 'Due in ' . ceil($diff / 60) . 'm';
        return 'Late ' . ceil(abs($diff) / 60) . 'm';
    }

    private static function payment_status_label($order): string {
        return $order->is_paid() ? 'Paid' : 'Unpaid';
    }

    private static function tickets_fullscreen(): bool {
        return isset($_GET['ttos_view']) && sanitize_key(wp_unslash($_GET['ttos_view'])) === 'fullscreen';
    }

    private static function log_operation(string $type, string $message): void {
        $log = get_option('ttos_operations_log', array());
        if (!is_array($log)) $log = array();
        $log[] = array('type' => $type, 'message' => $message, 'time' => time());
        update_option('ttos_operations_log', array_slice($log, -250), false);
    }

    public static function page_customers(): void {
        self::shell_start('Customers / CRM', 'CRM layer: profiles, segments, notes, loyalty adjustments and direct-order campaigns.');
        if (!TTOS_WooCommerce::active()) {
            echo '<section class="ttos-card"><p>WooCommerce is required for customer reports.</p></section>';
            self::shell_end();
            return;
        }

        $selected_email = isset($_GET['customer']) ? sanitize_email(wp_unslash($_GET['customer'])) : '';
        $segment = isset($_GET['segment']) ? sanitize_key($_GET['segment']) : 'all';
        $search = isset($_GET['customer_search']) ? sanitize_text_field(wp_unslash($_GET['customer_search'])) : '';
        $customers = self::customer_snapshot_enhanced(300);
        $counts = self::customer_segment_counts($customers);

        echo '<div class="ttos-grid ttos-grid-4">';
        self::metric('Customers', count($customers));
        self::metric('VIPs', $counts['vip'] ?? 0);
        self::metric('Dormant', $counts['dormant'] ?? 0);
        self::metric('Campaigns', count(self::customer_campaigns()));
        echo '</div>';

        if ($selected_email && isset($customers[strtolower($selected_email)])) {
            self::customer_profile_panel(strtolower($selected_email), $customers[strtolower($selected_email)]);
        }

        self::customer_import_panel();
        self::campaign_builder_panel();

        echo '<section class="ttos-card"><div class="ttos-order-cockpit-head"><div><h2>Customer segments</h2><p class="ttos-muted">Filter the list, open a customer profile, then add notes, loyalty adjustments or a targeted reward.</p></div>';
        echo '<form method="get" class="ttos-inline-form"><input type="hidden" name="page" value="takeaway-os-customers">';
        echo '<select name="segment">';
        $segments = array('all' => 'All customers', 'vip' => 'VIP', 'regular' => 'Regular', 'new' => 'New', 'dormant' => 'Dormant', 'marketing' => 'Marketing OK');
        foreach ($segments as $key => $label) echo '<option value="' . esc_attr($key) . '" ' . selected($segment, $key, false) . '>' . esc_html($label) . '</option>';
        echo '</select><input type="search" name="customer_search" value="' . esc_attr($search) . '" placeholder="Search name/email"><button class="ttos-mini">Filter</button></form></div>';

        $filtered = self::filter_customers($customers, $segment, $search);
        echo '<table class="ttos-table ttos-crm-table"><thead><tr><th>Customer</th><th>Email</th><th>Orders</th><th>LTV</th><th>Average</th><th>Last order</th><th>Status</th><th>Tags</th><th></th></tr></thead><tbody>';
        foreach ($filtered as $email => $c) {
            echo '<tr><td><strong>' . esc_html($c['name'] ?: 'Guest customer') . '</strong><br><small>' . esc_html($c['phone']) . '</small></td><td>' . esc_html($email) . '</td><td>' . esc_html((string) $c['orders']) . '</td><td>' . esc_html(self::money($c['total'])) . '</td><td>' . esc_html(self::money($c['aov'])) . '</td><td>' . esc_html($c['last']) . '</td><td>' . self::customer_status_badge($c['status']) . '</td><td>' . esc_html($c['tags']) . '</td><td><a class="ttos-mini" href="' . esc_url(add_query_arg(array('page' => 'takeaway-os-customers', 'customer' => rawurlencode($email)), admin_url('admin.php'))) . '">Open</a></td></tr>';
        }
        if (!$filtered) echo '<tr><td colspan="9"><span class="ttos-muted">No customers match this filter yet.</span></td></tr>';
        echo '</tbody></table></section>';

        self::campaign_history_panel();
        self::shell_end();
    }

    private static function customer_profiles(): array {
        $profiles = get_option('ttos_customer_profiles', array());
        return is_array($profiles) ? $profiles : array();
    }

    private static function customer_campaigns(): array {
        $campaigns = get_option('ttos_customer_campaigns', array());
        return is_array($campaigns) ? $campaigns : array();
    }

    private static function customer_snapshot_enhanced(int $limit = 300): array {
        if (!function_exists('wc_get_orders')) return array();
        $orders = wc_get_orders(array('limit' => $limit, 'return' => 'objects', 'orderby' => 'date', 'order' => 'DESC'));
        $profiles = self::customer_profiles();
        $customers = array();
        foreach ($orders as $order) {
            if (!$order || in_array($order->get_status(), array('cancelled','refunded','failed'), true)) continue;
            $email = strtolower($order->get_billing_email());
            if (!$email) continue;
            if (!isset($customers[$email])) {
                $customers[$email] = array(
                    'name' => trim($order->get_formatted_billing_full_name()),
                    'phone' => $order->get_billing_phone(),
                    'postcode' => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
                    'orders' => 0,
                    'total' => 0.0,
                    'aov' => 0.0,
                    'last' => '',
                    'last_ts' => 0,
                    'first_ts' => 0,
                    'status' => 'new',
                    'tags' => '',
                    'internal_notes' => '',
                    'marketing_ok' => '0',
                    'birthday' => '',
                    'items' => array(),
                    'order_ids' => array(),
                );
            }
            $ts = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
            $customers[$email]['orders']++;
            $customers[$email]['total'] += (float) $order->get_total();
            $customers[$email]['order_ids'][] = $order->get_id();
            if ($ts > $customers[$email]['last_ts']) {
                $customers[$email]['last_ts'] = $ts;
                $customers[$email]['last'] = $order->get_date_created() ? $order->get_date_created()->date_i18n('d M Y') : '';
                $customers[$email]['name'] = trim($order->get_formatted_billing_full_name()) ?: $customers[$email]['name'];
                $customers[$email]['phone'] = $order->get_billing_phone() ?: $customers[$email]['phone'];
                $customers[$email]['postcode'] = ($order->get_shipping_postcode() ?: $order->get_billing_postcode()) ?: $customers[$email]['postcode'];
            }
            if (!$customers[$email]['first_ts'] || ($ts && $ts < $customers[$email]['first_ts'])) $customers[$email]['first_ts'] = $ts;
            foreach ($order->get_items() as $item) {
                $name = $item->get_name();
                if (!isset($customers[$email]['items'][$name])) $customers[$email]['items'][$name] = 0;
                $customers[$email]['items'][$name] += (int) $item->get_quantity();
            }
        }
        foreach ($customers as $email => &$c) {
            $profile = $profiles[$email] ?? array();
            $c['name'] = trim((string) ($c['name'] ?: ($profile['name'] ?? '')));
            $c['phone'] = (string) ($c['phone'] ?: ($profile['phone'] ?? ''));
            $c['postcode'] = (string) ($c['postcode'] ?: ($profile['postcode'] ?? ''));
            $c['tags'] = (string) ($profile['tags'] ?? '');
            $c['internal_notes'] = (string) ($profile['internal_notes'] ?? '');
            $c['marketing_ok'] = (string) ($profile['marketing_ok'] ?? '0');
            $c['birthday'] = (string) ($profile['birthday'] ?? '');
            $c['aov'] = $c['orders'] ? $c['total'] / $c['orders'] : 0;
            arsort($c['items']);
            $days_since = $c['last_ts'] ? floor((time() - $c['last_ts']) / DAY_IN_SECONDS) : 9999;
            if ($days_since >= 60) $c['status'] = 'dormant';
            elseif ($c['total'] >= 150 || $c['orders'] >= 10) $c['status'] = 'vip';
            elseif ($c['orders'] >= 3) $c['status'] = 'regular';
            else $c['status'] = 'new';
        }
        unset($c);
        foreach ($profiles as $email => $profile) {
            $email = strtolower(sanitize_email((string) $email));
            if ($email === '' || isset($customers[$email])) {
                continue;
            }
            $customers[$email] = array(
                'name' => sanitize_text_field((string) ($profile['name'] ?? '')),
                'phone' => sanitize_text_field((string) ($profile['phone'] ?? '')),
                'postcode' => sanitize_text_field((string) ($profile['postcode'] ?? '')),
                'orders' => 0,
                'total' => 0.0,
                'aov' => 0.0,
                'last' => '',
                'last_ts' => 0,
                'first_ts' => 0,
                'status' => 'new',
                'tags' => (string) ($profile['tags'] ?? ''),
                'internal_notes' => (string) ($profile['internal_notes'] ?? ''),
                'marketing_ok' => (string) ($profile['marketing_ok'] ?? '0'),
                'birthday' => (string) ($profile['birthday'] ?? ''),
                'items' => array(),
                'order_ids' => array(),
            );
        }
        uasort($customers, function($a, $b){ return $b['last_ts'] <=> $a['last_ts']; });
        return $customers;
    }

    private static function customer_segment_counts(array $customers): array {
        $counts = array('all' => count($customers), 'vip' => 0, 'regular' => 0, 'new' => 0, 'dormant' => 0, 'marketing' => 0);
        foreach ($customers as $c) {
            if (isset($counts[$c['status']])) $counts[$c['status']]++;
            if (!empty($c['marketing_ok']) && $c['marketing_ok'] === '1') $counts['marketing']++;
        }
        return $counts;
    }

    private static function filter_customers(array $customers, string $segment, string $search = ''): array {
        $out = array();
        $search_l = strtolower($search);
        foreach ($customers as $email => $c) {
            if ($segment !== 'all') {
                if ($segment === 'marketing' && ($c['marketing_ok'] ?? '0') !== '1') continue;
                if ($segment !== 'marketing' && ($c['status'] ?? '') !== $segment) continue;
            }
            if ($search_l && strpos(strtolower($email . ' ' . $c['name'] . ' ' . $c['phone'] . ' ' . $c['tags']), $search_l) === false) continue;
            $out[$email] = $c;
        }
        return $out;
    }

    private static function customer_emails_for_segment(string $segment): array {
        $customers = self::customer_snapshot_enhanced(500);
        return array_keys(self::filter_customers($customers, $segment, ''));
    }

    private static function customer_status_badge(string $status): string {
        $labels = array('vip' => 'VIP', 'regular' => 'Regular', 'new' => 'New', 'dormant' => 'Dormant');
        $class = $status === 'dormant' ? 'ttos-warn' : ($status === 'vip' ? 'ttos-good' : 'ttos-module-state');
        return '<span class="' . esc_attr($class) . '">' . esc_html($labels[$status] ?? ucfirst($status)) . '</span>';
    }

    private static function customer_profile_panel(string $email, array $customer): void {
        $ledger = get_option('ttos_loyalty_ledger', array());
        $balance = 0;
        $rows = array();
        if (is_array($ledger)) {
            foreach (array_reverse($ledger) as $row) {
                if (($row['email'] ?? '') !== $email) continue;
                $balance += (int) ($row['points'] ?? 0);
                $rows[] = $row;
                if (count($rows) >= 8) break;
            }
            if (class_exists('TTOS_Features') && method_exists('TTOS_Features', 'loyalty_balance')) $balance = TTOS_Features::loyalty_balance($email);
        }
        echo '<section class="ttos-card ttos-customer-profile"><div class="ttos-order-cockpit-head"><div><p class="ttos-eyebrow">Customer profile</p><h2>' . esc_html($customer['name'] ?: $email) . '</h2><p class="ttos-muted">' . esc_html($email) . ' · ' . esc_html($customer['phone']) . ' · ' . esc_html($customer['postcode']) . '</p></div><a class="ttos-mini" href="' . esc_url(admin_url('admin.php?page=takeaway-os-customers')) . '">Close profile</a></div>';
        echo '<div class="ttos-grid ttos-grid-4">';
        self::metric('Orders', $customer['orders']); self::metric('Lifetime value', self::money($customer['total'])); self::metric('Average order', self::money($customer['aov'])); self::metric('Points', $balance);
        echo '</div><div class="ttos-grid ttos-grid-2">';
        echo '<div><h3>Favourite items</h3><ol class="ttos-list">';
        foreach (array_slice($customer['items'], 0, 6, true) as $name => $qty) echo '<li><span>' . esc_html($name) . '</span><strong>' . esc_html((string) $qty) . '</strong></li>';
        if (!$customer['items']) echo '<li>No item history yet.</li>';
        echo '</ol><h3>Recent loyalty ledger</h3><ol class="ttos-list">';
        foreach ($rows as $row) echo '<li><span>' . esc_html($row['note'] ?? 'Points') . '<br><small>' . esc_html(!empty($row['time']) ? date_i18n('d M Y H:i', (int) $row['time']) : '') . '</small></span><strong>' . esc_html((string) ($row['points'] ?? 0)) . '</strong></li>';
        if (!$rows) echo '<li>No loyalty activity yet.</li>';
        echo '</ol></div>';

        echo '<div><h3>CRM notes</h3><form method="post">';
        wp_nonce_field('ttos_save_customer_profile');
        echo '<input type="hidden" name="ttos_action" value="save_customer_profile"><input type="hidden" name="customer_email" value="' . esc_attr($email) . '">';
        self::field('Name', 'customer_name', $customer['name']);
        self::field('Phone', 'customer_phone', $customer['phone']);
        self::field('Postcode', 'customer_postcode', $customer['postcode']);
        self::field('Tags', 'customer_tags', $customer['tags']);
        self::field('Birthday / useful date', 'birthday', $customer['birthday']);
        echo '<label>Internal notes<textarea name="customer_notes" rows="5">' . esc_textarea($customer['internal_notes']) . '</textarea></label>';
        echo '<label class="ttos-check"><input type="checkbox" name="marketing_ok" value="1" ' . checked($customer['marketing_ok'], '1', false) . '> Marketing permission recorded</label><button class="ttos-button">Save customer profile</button></form>';
        echo '<hr><h3>Loyalty adjustment</h3><form method="post" class="ttos-inline-form">';
        wp_nonce_field('ttos_adjust_customer_loyalty');
        echo '<input type="hidden" name="ttos_action" value="adjust_customer_loyalty"><input type="hidden" name="customer_email" value="' . esc_attr($email) . '"><input type="number" name="points" placeholder="+50 or -20"><input type="text" name="points_note" placeholder="Reason"><button class="ttos-mini">Apply</button></form>';
        echo '<h3>One-customer coupon</h3><form method="post" class="ttos-inline-form">';
        wp_nonce_field('ttos_create_customer_coupon');
        echo '<input type="hidden" name="ttos_action" value="create_customer_coupon"><input type="hidden" name="customer_email" value="' . esc_attr($email) . '"><select name="coupon_type"><option value="fixed_cart">£ off basket</option><option value="percent">% off</option></select><input type="number" name="coupon_amount" step="0.01" value="5"><input type="number" name="expires_days" value="30"><button class="ttos-mini">Create reward</button></form></div></div></section>';
    }

    private static function campaign_builder_panel(): void {
        $enabled = TTOS_Settings::module_enabled('promo_engine') || TTOS_Settings::module_enabled('crm_pro');
        echo '<section class="ttos-card"><h2>Direct-order campaign builder ' . ($enabled ? '<span class="ttos-good">Enabled</span>' : '<span class="ttos-warn">Add-on off</span>') . '</h2><p class="ttos-muted">Creates a WooCommerce coupon restricted to the chosen customer segment. You can then use the email list for SMS/email follow-up without handing customer data to aggregators.</p>';
        echo '<form method="post" class="ttos-grid ttos-grid-3">';
        wp_nonce_field('ttos_create_customer_campaign');
        echo '<input type="hidden" name="ttos_action" value="create_customer_campaign">';
        self::field('Campaign name', 'campaign_name', 'We miss you - order direct');
        echo '<label>Segment<select name="campaign_segment"><option value="dormant">Dormant customers</option><option value="vip">VIP customers</option><option value="regular">Regular customers</option><option value="new">New customers</option><option value="marketing">Marketing OK</option><option value="all">All customers</option></select></label>';
        echo '<label>Discount type<select name="campaign_type"><option value="fixed_cart">£ off basket</option><option value="percent">% off basket</option></select></label>';
        self::field('Discount amount', 'campaign_amount', '5', 'number');
        self::field('Expires in days', 'campaign_expires_days', '14', 'number');
        echo '<label>Action<span class="ttos-muted" style="display:block;margin-top:6px">Coupon will be customer-email restricted.</span><button class="ttos-button" ' . disabled(!$enabled, true, false) . '>Create campaign coupon</button></label></form></section>';
    }

    private static function customer_import_panel(): void {
        $export_url = wp_nonce_url(admin_url('admin-post.php?action=ttos_export_customer_profiles_csv'), 'ttos_export_customer_profiles_csv');
        echo '<section class="ttos-card"><h2>Customer import / export</h2><p class="ttos-muted">Upload CRM leads or existing takeaway customers into the same profile store used by tags, notes and marketing preferences. Order history is still read from WooCommerce.</p>';
        echo '<div class="ttos-grid ttos-grid-2"><div><h3>Import customer CSV</h3><p class="ttos-muted">Required header: <code>email</code>. Optional headers: <code>name</code>, <code>phone</code>, <code>postcode</code>, <code>tags</code>, <code>internal_notes</code>, <code>marketing_ok</code>, <code>birthday</code>.</p><form method="post" enctype="multipart/form-data">';
        wp_nonce_field('ttos_import_customer_profiles');
        echo '<input type="hidden" name="ttos_action" value="import_customer_profiles"><input type="file" name="customer_csv" accept=".csv,text/csv"><button class="ttos-button">Import customer CSV</button></form></div>';
        echo '<div><h3>Export customer CRM CSV</h3><p class="ttos-muted">Downloads the current CRM snapshot, including imported profiles, marketing flags and WooCommerce order totals for round-tripping.</p><p><a class="ttos-button" href="' . esc_url($export_url) . '">Export customer CRM CSV</a></p></div></div></section>';
    }

    private static function campaign_history_panel(): void {
        $campaigns = array_reverse(self::customer_campaigns());
        echo '<section class="ttos-card"><h2>Campaign history</h2><table class="ttos-table"><thead><tr><th>Campaign</th><th>Segment</th><th>Coupon</th><th>Customers</th><th>Created</th></tr></thead><tbody>';
        foreach ($campaigns as $campaign) {
            echo '<tr><td><strong>' . esc_html($campaign['name'] ?? '') . '</strong><br><textarea readonly rows="2">' . esc_textarea(implode(', ', (array) ($campaign['emails'] ?? array()))) . '</textarea></td><td>' . esc_html($campaign['segment'] ?? '') . '</td><td><code>' . esc_html($campaign['code'] ?? '') . '</code></td><td>' . esc_html((string) ($campaign['email_count'] ?? 0)) . '</td><td>' . esc_html(!empty($campaign['created']) ? date_i18n('d M Y H:i', (int) $campaign['created']) : '') . '</td></tr>';
        }
        if (!$campaigns) echo '<tr><td colspan="5"><span class="ttos-muted">No campaigns yet. Build one above once you have order history.</span></td></tr>';
        echo '</tbody></table></section>';
    }

    private static function create_campaign_coupon(array $emails, float $amount, string $type, int $expires_days, string $label): string {
        if (!post_type_exists('shop_coupon')) return '';
        $emails = array_values(array_filter(array_unique(array_map('sanitize_email', $emails))));
        if (!$emails) return '';
        $type = in_array($type, array('fixed_cart','percent'), true) ? $type : 'fixed_cart';
        $code = 'DIRECT-' . strtoupper(wp_generate_password(7, false, false));
        $id = wp_insert_post(array('post_title' => $code, 'post_type' => 'shop_coupon', 'post_status' => 'publish', 'post_excerpt' => $label));
        if ($id && !is_wp_error($id)) {
            update_post_meta($id, '_ttos_generated_coupon', '1');
            update_post_meta($id, 'discount_type', $type);
            update_post_meta($id, 'coupon_amount', wc_format_decimal($amount));
            update_post_meta($id, 'usage_limit_per_user', '1');
            update_post_meta($id, 'customer_email', $emails);
            update_post_meta($id, 'date_expires', time() + max(1, $expires_days) * DAY_IN_SECONDS);
        }
        return $code;
    }

    private static function import_customer_profiles() {
        $upload = TTOS_Hardening::stash_uploaded_file($_FILES['customer_csv'] ?? array(), array('csv'), 2 * 1024 * 1024, 'customer-import-');
        if (is_wp_error($upload)) {
            return $upload;
        }

        $profiles = self::customer_profiles();
        $summary = array('processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0);
        $warnings = array();
        $handle = fopen($upload['path'], 'r');
        if (!$handle) {
            TTOS_Hardening::cleanup_import_file($upload['path']);
            return new WP_Error('ttos_customer_import_open', __('The uploaded customer CSV could not be opened.', 'takeaway-os'));
        }

        try {
            $headers = fgetcsv($handle);
            if (!$headers) {
                return new WP_Error('ttos_customer_import_headers', __('The customer CSV is empty or missing its header row.', 'takeaway-os'));
            }
            $headers = array_map(array(__CLASS__, 'normalise_csv_header'), $headers);
            if (!in_array('email', $headers, true)) {
                return new WP_Error('ttos_customer_import_email', __('The customer CSV must include an "email" column.', 'takeaway-os'));
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (!is_array($row) || self::csv_row_blank($row)) {
                    continue;
                }
                $data = array();
                foreach ($headers as $index => $key) {
                    if ($key === '') {
                        continue;
                    }
                    $data[$key] = isset($row[$index]) ? trim((string) $row[$index]) : '';
                }

                $email = strtolower(sanitize_email((string) ($data['email'] ?? '')));
                if ($email === '' || !is_email($email)) {
                    $warnings[] = __('A row was skipped because the email address was missing or invalid.', 'takeaway-os');
                    $summary['skipped']++;
                    continue;
                }

                $existing = is_array($profiles[$email] ?? null) ? $profiles[$email] : array();
                $profiles[$email] = array(
                    'name'           => sanitize_text_field((string) ($data['name'] ?? ($existing['name'] ?? ''))),
                    'phone'          => sanitize_text_field((string) ($data['phone'] ?? ($existing['phone'] ?? ''))),
                    'postcode'       => sanitize_text_field((string) ($data['postcode'] ?? ($existing['postcode'] ?? ''))),
                    'tags'           => sanitize_text_field((string) ($data['tags'] ?? ($existing['tags'] ?? ''))),
                    'internal_notes' => sanitize_textarea_field((string) ($data['internal_notes'] ?? ($existing['internal_notes'] ?? ''))),
                    'marketing_ok'   => self::csv_bool((string) ($data['marketing_ok'] ?? ($existing['marketing_ok'] ?? '0'))) ? '1' : '0',
                    'birthday'       => sanitize_text_field((string) ($data['birthday'] ?? ($existing['birthday'] ?? ''))),
                    'updated'        => time(),
                );
                $summary['processed']++;
                if ($existing) {
                    $summary['updated']++;
                } else {
                    $summary['created']++;
                }
            }
        } finally {
            fclose($handle);
            TTOS_Hardening::cleanup_import_file($upload['path']);
        }

        if (!$summary['processed']) {
            return new WP_Error('ttos_customer_import_empty', __('No customer profiles were imported from that CSV.', 'takeaway-os'));
        }

        update_option('ttos_customer_profiles', $profiles, false);
        if ($warnings) {
            $summary['warnings'] = $warnings;
        }
        return $summary;
    }

    public static function export_customer_profiles_csv(): void {
        if (!current_user_can('ttos_view_reports') || !check_admin_referer('ttos_export_customer_profiles_csv')) {
            wp_die('Not allowed.');
        }
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=takeaway-customer-crm-' . gmdate('Y-m-d') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, array('email','name','phone','postcode','tags','internal_notes','marketing_ok','birthday','orders','lifetime_value','last_order','status'));
        foreach (self::customer_snapshot_enhanced(500) as $email => $customer) {
            fputcsv($out, array(
                $email,
                $customer['name'],
                $customer['phone'],
                $customer['postcode'],
                $customer['tags'],
                $customer['internal_notes'],
                $customer['marketing_ok'],
                $customer['birthday'],
                $customer['orders'],
                wc_format_decimal((float) $customer['total'], 2),
                $customer['last'],
                $customer['status'],
            ));
        }
        fclose($out);
        exit;
    }

    private static function normalise_csv_header(string $header): string {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);
        return trim((string) $header, '_');
    }

    private static function csv_row_blank(array $row): bool {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    private static function csv_bool(string $value): bool {
        return in_array(strtolower(trim($value)), array('1', 'yes', 'true', 'y', 'on'), true);
    }

    public static function page_reports(): void {
        self::shell_start('Reports', 'Analytics, daily close and money-side reporting for owners and accountants. WooCommerce stays the financial source of truth.');

        if (!TTOS_WooCommerce::active()) {
            echo '<section class="ttos-card"><h2>WooCommerce required</h2><p class="ttos-muted">Install and activate WooCommerce before reports can read order data.</p></section>';
            self::shell_end();
            return;
        }

        $preset = sanitize_key($_GET['ttos_range'] ?? '7days');
        $from = sanitize_text_field(wp_unslash($_GET['ttos_from'] ?? ''));
        $to = sanitize_text_field(wp_unslash($_GET['ttos_to'] ?? ''));
        $range = TTOS_Analytics::range($preset, $from, $to);
        $report = TTOS_Analytics::report($range['start'], $range['end']);
        $summary = $report['summary'];
        $analytics_on = TTOS_Settings::module_enabled('analytics_pro');
        $accounting_on = TTOS_Settings::module_enabled('accounting');

        echo '<section class="ttos-card ttos-report-filter"><form method="get" class="ttos-grid ttos-grid-4"><input type="hidden" name="page" value="takeaway-os-reports">';
        echo '<label>Date range<select name="ttos_range"><option value="today" ' . selected($preset, 'today', false) . '>Today</option><option value="yesterday" ' . selected($preset, 'yesterday', false) . '>Yesterday</option><option value="7days" ' . selected($preset, '7days', false) . '>Last 7 days</option><option value="30days" ' . selected($preset, '30days', false) . '>Last 30 days</option><option value="month" ' . selected($preset, 'month', false) . '>This month</option><option value="custom" ' . selected($preset, 'custom', false) . '>Custom</option></select></label>';
        echo '<label>From<input type="date" name="ttos_from" value="' . esc_attr($from) . '"></label><label>To<input type="date" name="ttos_to" value="' . esc_attr($to) . '"></label><label>Action<span class="ttos-muted" style="display:block;margin-top:6px">' . esc_html($range['label']) . '</span><button class="ttos-button">Update report</button></label></form></section>';

        echo '<div class="ttos-grid ttos-grid-4">';
        self::metric('Orders', $summary['orders']);
        self::metric('Gross sales', self::money($summary['gross']));
        self::metric('Net after refunds', self::money($summary['net']));
        self::metric('Average order', self::money($summary['aov']));
        echo '</div><div class="ttos-grid ttos-grid-4">';
        self::metric('Card / non-cash', self::money($summary['card']));
        self::metric('Cash', self::money($summary['cash']));
        self::metric('Discounts', self::money($summary['discounts']));
        self::metric('Refunds', self::money($summary['refunds']));
        echo '</div><div class="ttos-grid ttos-grid-4">';
        self::metric('Delivery fees', self::money($summary['delivery_fees']));
        self::metric('VAT estimate', self::money($summary['vat_estimate']));
        self::metric('Unique customers', $summary['unique_customers']);
        self::metric('JE fee avoided est.', self::money($summary['direct_savings_estimate']));
        echo '</div>';

        $export_args = array('action' => 'ttos_export_money_csv', 'preset' => $preset, 'from' => $from, 'to' => $to);
        $export_url = wp_nonce_url(add_query_arg($export_args, admin_url('admin-post.php')), 'ttos_export_money_csv');
        $daily_url = wp_nonce_url(add_query_arg(array('action' => 'ttos_export_daily_close_csv'), admin_url('admin-post.php')), 'ttos_export_daily_close_csv');
        echo '<section class="ttos-card"><div class="ttos-card-head"><div><h2>Accounting exports ' . ($accounting_on ? '<span class="ttos-good">Enabled</span>' : '<span class="ttos-warn">Add-on off</span>') . '</h2><p class="ttos-muted">Exports are accountant-friendly summaries. They do not replace Xero, QuickBooks or proper bookkeeping.</p></div><p><a class="ttos-button" href="' . esc_url($export_url) . '">Export selected range CSV</a> <a class="ttos-mini" href="' . esc_url($daily_url) . '">Daily close CSV</a></p></div></section>';

        echo '<div class="ttos-grid ttos-grid-2">';
        echo '<section class="ttos-card"><h2>Payment split</h2>';
        self::report_bar_table($report['payment_methods'], 'gross', $summary['gross'], 'money');
        echo '</section>';

        echo '<section class="ttos-card"><h2>Fulfilment split</h2>';
        $fulfilment_rows = array();
        foreach ($report['fulfilment'] as $name => $count) $fulfilment_rows[ucfirst($name)] = array('orders' => $count);
        self::report_bar_table($fulfilment_rows, 'orders', max(1, (int) $summary['orders']), 'number');
        echo '</section>';
        echo '</div>';

        echo '<div class="ttos-grid ttos-grid-2">';
        echo '<section class="ttos-card"><h2>Status split</h2>';
        self::report_bar_table($report['statuses'], 'orders', max(1, (int) $summary['orders']), 'number');
        echo '</section>';

        echo '<section class="ttos-card"><h2>Busiest hour</h2>';
        $busiest = $report['busiest_hour'];
        if ($busiest['hour'] === null) {
            echo '<p class="ttos-muted">No orders in this range yet.</p>';
        } else {
            echo '<div class="ttos-big-number"><strong>' . esc_html(sprintf('%02d:00', (int) $busiest['hour'])) . '</strong><span>' . esc_html((string) $busiest['orders']) . ' orders · ' . esc_html(self::money($busiest['gross'])) . '</span></div>';
            self::hour_chart($report['hours']);
        }
        echo '</section>';
        echo '</div>';

        echo '<section class="ttos-card"><h2>Daily takings</h2><table class="ttos-table"><thead><tr><th>Day</th><th>Orders</th><th>Gross</th><th>Net</th><th>Discounts</th><th>Refunds</th></tr></thead><tbody>';
        foreach ($report['days'] as $day) {
            echo '<tr><td><strong>' . esc_html($day['label']) . '</strong></td><td>' . esc_html((string) $day['orders']) . '</td><td>' . esc_html(self::money($day['gross'])) . '</td><td>' . esc_html(self::money($day['net'])) . '</td><td>' . esc_html(self::money($day['discounts'])) . '</td><td>' . esc_html(self::money($day['refunds'])) . '</td></tr>';
        }
        if (!$report['days']) echo '<tr><td colspan="6"><span class="ttos-muted">No orders in this range.</span></td></tr>';
        echo '</tbody></table></section>';

        echo '<section class="ttos-card"><h2>Top menu items ' . ($analytics_on ? '<span class="ttos-good">Analytics Pro</span>' : '<span class="ttos-warn">Analytics add-on off</span>') . '</h2><table class="ttos-table"><thead><tr><th>Item</th><th>Qty</th><th>Gross</th><th>Est. cost</th><th>Est. margin</th></tr></thead><tbody>';
        foreach ($report['top_items'] as $item) {
            echo '<tr><td><strong>' . esc_html($item['name']) . '</strong></td><td>' . esc_html((string) $item['qty']) . '</td><td>' . esc_html(self::money($item['gross'])) . '</td><td>' . esc_html($item['estimated_cost'] > 0 ? self::money($item['estimated_cost']) : 'Add cost price') . '</td><td>' . esc_html($item['estimated_cost'] > 0 ? self::money($item['estimated_margin']) : '—') . '</td></tr>';
        }
        if (!$report['top_items']) echo '<tr><td colspan="5"><span class="ttos-muted">No menu sales yet. Add cost prices in Menu Builder to unlock margin hints.</span></td></tr>';
        echo '</tbody></table></section>';

        self::shell_end();
    }

    private static function report_bar_table(array $rows, string $value_key, float $total, string $format = 'number'): void {
        if (!$rows) { echo '<p class="ttos-muted">No data in this range yet.</p>'; return; }
        echo '<div class="ttos-report-bars">';
        foreach ($rows as $label => $row) {
            $value = (float) ($row[$value_key] ?? 0);
            $pct = $total > 0 ? min(100, round(($value / $total) * 100)) : 0;
            $display = $format === 'money' ? self::money($value) : (string) (int) $value;
            echo '<div class="ttos-report-bar"><div><strong>' . esc_html((string) $label) . '</strong><span>' . esc_html($display) . '</span></div><i><b style="width:' . esc_attr((string) $pct) . '%"></b></i></div>';
        }
        echo '</div>';
    }

    private static function hour_chart(array $hours): void {
        $max = 0;
        foreach ($hours as $row) $max = max($max, (int) ($row['orders'] ?? 0));
        echo '<div class="ttos-hour-chart">';
        foreach ($hours as $hour => $row) {
            $orders = (int) ($row['orders'] ?? 0);
            $height = $max > 0 ? max(6, round(($orders / $max) * 70)) : 6;
            echo '<span title="' . esc_attr(sprintf('%02d:00 · %d orders', $hour, $orders)) . '"><i style="height:' . esc_attr((string) $height) . 'px"></i><small>' . esc_html($hour % 3 === 0 ? (string) $hour : '') . '</small></span>';
        }
        echo '</div>';
    }

    private static function metric(string $label, $value): void {
        echo '<section class="ttos-metric"><span>' . esc_html($label) . '</span><strong>' . esc_html((string) $value) . '</strong></section>';
    }

    private static function field(string $label, string $name, $value = '', string $type = 'text'): void {
        echo '<label>' . esc_html($label) . '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '"></label>';
    }

    private static function toggle_field(string $label, string $name, string $value = '1'): void {
        $checked = $value === '1' ? ' checked' : '';
        echo '<label class="ttos-toggle-label"><input type="checkbox" name="' . esc_attr($name) . '" value="1"' . $checked . '> ' . esc_html($label) . '</label>';
    }

    private static function media_field(string $label, string $name, int $value = 0, string $button = 'Choose image'): void {
        $id = 'ttos-media-' . md5($name . wp_rand());
        $thumb = $value ? wp_get_attachment_image_url($value, 'medium') : '';
        echo '<label class="ttos-media-field">' . esc_html($label);
        echo '<input type="hidden" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '">';
        echo '<span class="ttos-media-preview" data-empty="No image selected">';
        if ($thumb) {
            echo '<img src="' . esc_url($thumb) . '" alt="">';
        } else {
            echo '<em>No image selected</em>';
        }
        echo '</span><span class="ttos-media-actions"><button type="button" class="ttos-mini ttos-pick-media" data-target="#' . esc_attr($id) . '">' . esc_html($button) . '</button><button type="button" class="ttos-mini ttos-clear-media" data-target="#' . esc_attr($id) . '">Remove</button></span>';
        echo '</label>';
    }

    private static function plugin_row(array $plugin, bool $with_action = false): void {
        $status = TTOS_Plugin_Checker::status($plugin);
        $label = $status === 'active' ? 'Active' : ($status === 'installed' ? 'Installed' : 'Missing');
        $class = $status === 'active' ? 'ttos-good' : ($plugin['required'] ? 'ttos-bad' : 'ttos-warn');
        echo '<div class="ttos-plugin-row"><div><strong>' . esc_html($plugin['name']) . '</strong><p>' . esc_html($plugin['description']) . '</p></div><span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
        if ($with_action && $status !== 'active' && current_user_can('install_plugins')) {
            echo '<a class="ttos-mini" href="' . esc_url(TTOS_Plugin_Checker::action_url($plugin)) . '">' . ($status === 'missing' ? 'Install' : 'Activate') . '</a>';
        }
        echo '</div>';
    }

    public static function module_catalog(): array {
        return array(
            'loyalty' => array('name' => 'Loyalty points', 'description' => 'Earn/redeem points on direct orders.', 'price' => '£250', 'status' => 'PRO READY', 'note' => 'Safe to enable for production.', 'toggleable' => true),
            'stamp_cards' => array('name' => 'Stamp cards', 'description' => 'Five stamps, free item, simple repeat engine.', 'price' => '£200', 'status' => 'PRO READY', 'note' => 'Safe to enable for production.', 'toggleable' => true),
            'meal_deals' => array('name' => 'Meal deal builder', 'description' => 'Main + side + drink guided bundles.', 'price' => '£350', 'status' => 'PRO READY', 'note' => 'Safe to enable for production.', 'toggleable' => true),
            'sms_updates' => array('name' => 'SMS updates', 'description' => 'Order accepted/preparing/ready messages.', 'price' => '£150 + SMS', 'status' => 'PRO PARTIAL', 'note' => 'Configuration surface exists, but delivery must be verified before sale.', 'toggleable' => false),
            'printer' => array('name' => 'Printer connector', 'description' => 'Kitchen printer/relay setup.', 'price' => '£250', 'status' => 'ADMIN-ONLY STUB', 'note' => 'Integration settings exist for future use. Not live by default.', 'toggleable' => false),
            'crm_pro' => array('name' => 'CRM Pro', 'description' => 'Dormant customers, top spenders and campaigns.', 'price' => '£350', 'status' => 'PRO READY', 'note' => 'Safe to enable for production.', 'toggleable' => true),
            'accounting' => array('name' => 'Accounting export', 'description' => 'Daily close, CSV, Xero/QuickBooks later.', 'price' => '£300', 'status' => 'PRO READY', 'note' => 'CSV export is production-ready; third-party integrations remain future work.', 'toggleable' => true),
            'advanced_zones' => array('name' => 'Advanced delivery zones', 'description' => 'Fees/minimums by postcode area.', 'price' => '£250', 'status' => 'PRO READY', 'note' => 'Safe to enable for production.', 'toggleable' => true),
            'allergen_filters' => array('name' => 'Allergen filtering', 'description' => 'Customer-facing filters and warnings.', 'price' => '£250', 'status' => 'PRO PARTIAL', 'note' => 'Allergen data is mapped, but the public filter layer is not a finished paid surface.', 'toggleable' => false),
            'inventory_lite' => array('name' => 'Inventory Lite', 'description' => 'Sold-out toggles, limited item counts and daily availability.', 'price' => '£250', 'status' => 'PRO READY', 'note' => 'Safe to enable for production.', 'toggleable' => true),
            'analytics_pro' => array('name' => 'Analytics Pro', 'description' => 'Best sellers, quiet hours, margin hints and owner reports.', 'price' => '£350', 'status' => 'PRO READY', 'note' => 'Safe to enable for production.', 'toggleable' => true),
            'promo_engine' => array('name' => 'Promo engine', 'description' => 'Timed discounts, first-order offers and direct-order campaigns.', 'price' => '£300', 'status' => 'PRO PARTIAL', 'note' => 'Manual campaigns and basic win-back automation are live; broader timed-promo automation still needs finishing.', 'toggleable' => false),
            'content_manager' => array('name' => 'Content manager', 'description' => 'Owner-safe homepage sections, images and offer blocks.', 'price' => '£250', 'status' => 'CORE INCLUDED', 'note' => 'This behaviour is now part of the core CRM/site content experience.', 'toggleable' => true),
            'kds_pro' => array('name' => 'Kitchen Display Pro', 'description' => 'Large-screen kitchen board, filters and prep timers.', 'price' => '£350', 'status' => 'PRO PARTIAL', 'note' => 'Core tickets are live; enhanced KDS packaging should stay disabled until separated cleanly.', 'toggleable' => false),
            'epos_connector' => array('name' => 'EPOS connector', 'description' => 'Square/ICRTouch/webhook mapping.', 'price' => '£500+', 'status' => 'ADMIN-ONLY STUB', 'note' => 'Integration settings exist for future use. Not active until configured and verified.', 'toggleable' => false),
            'multi_location' => array('name' => 'Multi-location', 'description' => 'Separate stores, hours, zones and menus.', 'price' => '£750+', 'status' => 'FUTURE ROADMAP', 'note' => 'Not a live production feature in v1.3.x.', 'toggleable' => false),
            'qr_ordering' => array('name' => 'QR table ordering', 'description' => 'Eat-in table flow.', 'price' => '£500', 'status' => 'FUTURE ROADMAP', 'note' => 'Not a live production feature in v1.3.x.', 'toggleable' => false),
        );
    }

    private static function money($amount): string {
        if (function_exists('wc_price')) return wp_strip_all_tags(wc_price((float) $amount));
        return '£' . number_format((float) $amount, 2);
    }
}

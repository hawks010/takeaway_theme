<?php

defined('ABSPATH') || exit;

final class TTOS_Settings {
    public static function hooks(): void {
        add_action('wp_head', array(__CLASS__, 'print_brand_css'), 20);
        add_filter('body_class', array(__CLASS__, 'body_classes'));
        add_filter('option_site_icon', array(__CLASS__, 'filter_site_icon'));
    }

    public static function defaults(): array {
        return array(
            'business' => array(
                'restaurant_name' => get_bloginfo('name'),
                'tagline'         => get_bloginfo('description'),
                'phone'           => '',
                'email'           => get_option('admin_email'),
                'address_1'       => '',
                'address_2'       => '',
                'town'            => '',
                'postcode'        => '',
                'company_number'  => '',
                'vat_number'      => '',
                'fsa_rating'      => '',
                'cuisine'         => 'Takeaway',
            ),
            'branding' => array(
                'logo_id'       => 0,
                'favicon_id'    => 0,
                'hero_image_id' => 0,
                'primary'       => '#c53000',
                'accent'        => '#ffac00',
                'bg'            => '#f9f4ee',
                'surface'       => '#ffffff',
                'surface_soft'  => '#f1eae0',
                'text'          => '#1a1410',
                'muted'         => '#6f655e',
                'border'        => '#e8dfd4',
                'success'       => '#157a35',
                'warning'       => '#8a5200',
                'error'         => '#b33a3a',
                'radius_sm'     => '25',
                'radius_md'     => '25',
                'radius_lg'     => '25',
                'shadow'        => 'soft',
                'default_mode'  => 'light',
                'header_style'  => 'solid',
                'hero_style'    => 'angled',
                'card_style'    => 'soft',
                'footer_style'  => 'dark',
                'font_heading'  => '',
                'font_body'     => '',
                // Legacy v0.2.x keys. Kept stored so old CSS aliases and
                // saved client values keep working; new installs mirror the
                // token values above.
                'secondary'     => '#ffac00',
                'dark'          => '#1a1410',
                'cream'         => '#f9f4ee',
                'style_skin'    => 'charcoal',
            ),
            'trading' => array(
                'min_order'           => '12.00',
                'delivery_fee'        => '1.50',
                'free_delivery_over'  => '30.00',
                'delivery_radius'     => '7',
                'prep_time'           => '25',
                'delivery_time'       => '35',
                'delivery_postcodes'  => '',
                'collection_enabled'  => '1',
                'delivery_enabled'    => '1',
                'service_charge'      => '0',
            ),

            'service_links' => array(
                'woocommerce_stripe' => 'https://woocommerce.com/products/stripe/',
                'fluent_smtp'        => 'https://fluentsmtp.com/',
                'smtp2go'            => 'https://www.smtp2go.com/',
                'printnode'          => 'https://www.printnode.com/',
                'twilio'             => 'https://www.twilio.com/',
                'xero'               => 'https://www.xero.com/uk/',
                'quickbooks'         => 'https://quickbooks.intuit.com/uk/',
            ),
            'modules' => array(
                'loyalty'          => false,
                'stamp_cards'      => false,
                'meal_deals'       => false,
                'sms_updates'      => false,
                'printer'          => false,
                'crm_pro'          => false,
                'accounting'       => false,
                'advanced_zones'   => false,
                'allergen_filters' => false,
                'inventory_lite'    => false,
                'analytics_pro'     => false,
                'promo_engine'      => false,
                'content_manager'   => false,
                'kds_pro'           => false,
                'epos_connector'   => false,
                'multi_location'   => false,
                'qr_ordering'      => false,
            ),
            'data_retention' => array(
                'erase_on_uninstall'       => '0',
                'delete_generated_pages'   => '0',
                'delete_menu_products'     => '0',
                'delete_generated_coupons' => '0',
                'delete_customer_meta'     => '0',
                'delete_roles'             => '1',
            ),
        );
    }

    public static function get(string $section = '', $key = null) {
        $settings = wp_parse_args(get_option('ttos_settings', array()), self::defaults());
        foreach (self::defaults() as $default_key => $default_value) {
            $settings[$default_key] = wp_parse_args($settings[$default_key] ?? array(), $default_value);
        }
        if ($section === '') {
            return $settings;
        }
        if ($key === null) {
            return $settings[$section] ?? array();
        }
        return $settings[$section][$key] ?? null;
    }

    public static function update_section(string $section, array $values): void {
        $settings = self::get();
        $settings[$section] = wp_parse_args($values, self::defaults()[$section] ?? array());
        update_option('ttos_settings', $settings, false);
    }

    public static function business_profile(): array {
        $business = self::get('business');
        if (class_exists('TTOS_Site_Content')) {
            $content = TTOS_Site_Content::get('business_info');
            if (is_array($content)) {
                $map = array(
                    'restaurant_name' => 'business_name',
                    'phone'           => 'phone',
                    'email'           => 'email',
                    'address_1'       => 'address_1',
                    'address_2'       => 'address_2',
                    'town'            => 'town',
                    'postcode'        => 'postcode',
                    'company_number'  => 'company_number',
                    'vat_number'      => 'vat_number',
                    'fsa_rating'      => 'hygiene_rating',
                );
                foreach ($map as $business_key => $content_key) {
                    if (!empty($content[$content_key])) {
                        $business[$business_key] = sanitize_text_field((string) $content[$content_key]);
                    }
                }
            }
        }
        return $business;
    }

    public static function sync_business_runtime(array $business): void {
        if (!empty($business['restaurant_name'])) {
            update_option('blogname', sanitize_text_field((string) $business['restaurant_name']));
        }
        if (array_key_exists('tagline', $business)) {
            update_option('blogdescription', sanitize_text_field((string) $business['tagline']));
        }
        if (!empty($business['email']) && is_email($business['email'])) {
            update_option('admin_email', sanitize_email((string) $business['email']));
        }
        if (!empty($business['address_1'])) {
            update_option('woocommerce_store_address', sanitize_text_field((string) $business['address_1']));
        }
        if (array_key_exists('address_2', $business)) {
            update_option('woocommerce_store_address_2', sanitize_text_field((string) $business['address_2']));
        }
        if (!empty($business['town'])) {
            update_option('woocommerce_store_city', sanitize_text_field((string) $business['town']));
        }
        if (!empty($business['postcode'])) {
            update_option('woocommerce_store_postcode', sanitize_text_field((string) $business['postcode']));
        }
        update_option('woocommerce_store_country', 'GB');
    }

    public static function sync_business_to_site_content(array $business): void {
        if (!class_exists('TTOS_Site_Content')) {
            return;
        }
        $content = TTOS_Site_Content::get('business_info');
        if (!is_array($content)) {
            $content = array();
        }
        $map = array(
            'restaurant_name' => 'business_name',
            'phone'           => 'phone',
            'email'           => 'email',
            'address_1'       => 'address_1',
            'address_2'       => 'address_2',
            'town'            => 'town',
            'postcode'        => 'postcode',
            'company_number'  => 'company_number',
            'vat_number'      => 'vat_number',
            'fsa_rating'      => 'hygiene_rating',
        );
        foreach ($map as $business_key => $content_key) {
            if (!array_key_exists($business_key, $business)) {
                continue;
            }
            $content[$content_key] = sanitize_text_field((string) $business[$business_key]);
        }
        TTOS_Site_Content::update_section('business_info', $content);
    }

    public static function sync_business_from_site_content(array $content): void {
        $business = self::get('business');
        $map = array(
            'business_name'   => 'restaurant_name',
            'phone'           => 'phone',
            'email'           => 'email',
            'address_1'       => 'address_1',
            'address_2'       => 'address_2',
            'town'            => 'town',
            'postcode'        => 'postcode',
            'company_number'  => 'company_number',
            'vat_number'      => 'vat_number',
            'hygiene_rating'  => 'fsa_rating',
        );
        foreach ($map as $content_key => $business_key) {
            if (!array_key_exists($content_key, $content)) {
                continue;
            }
            $business[$business_key] = sanitize_text_field((string) $content[$content_key]);
        }
        self::update_section('business', $business);
        self::sync_business_runtime($business);
    }

    public static function modules(): array {
        $modules = self::get('modules');
        foreach (self::production_locked_modules() as $slug) {
            $modules[$slug] = false;
        }
        return $modules;
    }

    public static function module_enabled(string $slug): bool {
        $modules = self::modules();
        return !empty($modules[$slug]);
    }

    /**
     * Modules below are intentionally forced off in v1.3.x production mode.
     * Their settings may exist for future/admin work, but they should not be
     * treated as live sellable features until separately signed off.
     */
    private static function production_locked_modules(): array {
        return array(
            'sms_updates',
            'printer',
            'allergen_filters',
            'promo_engine',
            'kds_pro',
            'epos_connector',
            'multi_location',
            'qr_ordering',
        );
    }

    /**
     * Sanitised brand token values. Single source of truth for the front-end
     * token set; the admin preview and theme rely on the same values.
     */
    public static function brand_tokens(): array {
        $branding = self::get('branding');
        $defaults = self::defaults()['branding'];

        $hex = static function ($value, string $fallback): string {
            $colour = sanitize_hex_color((string) $value);
            return $colour ?: $fallback;
        };
        $radius = static function ($value, $fallback): int {
            $px = absint($value);
            return ($px >= 0 && $px <= 60) ? $px : absint($fallback);
        };
        $choice = static function ($value, array $allowed, string $fallback): string {
            $key = sanitize_key((string) $value);
            return in_array($key, $allowed, true) ? $key : $fallback;
        };

        $tokens = array();
        foreach (array('primary','accent','bg','surface','surface_soft','text','muted','border','success','warning','error','secondary','dark','cream') as $key) {
            $tokens[$key] = $hex($branding[$key] ?? '', $defaults[$key]);
        }
        $tokens['radius_sm'] = $radius($branding['radius_sm'] ?? '', $defaults['radius_sm']);
        $tokens['radius_md'] = $radius($branding['radius_md'] ?? '', $defaults['radius_md']);
        $tokens['radius_lg'] = $radius($branding['radius_lg'] ?? '', $defaults['radius_lg']);
        $safe_font = static function (string $value): string {
            return preg_replace('/[^a-zA-Z0-9 ,\-\'"]+/', '', $value);
        };
        $tokens['font_heading'] = $safe_font((string) ($branding['font_heading'] ?? ''));
        $tokens['font_body']    = $safe_font((string) ($branding['font_body'] ?? ''));
        $tokens['shadow']       = $choice($branding['shadow'] ?? '', array('none', 'soft', 'strong'), 'soft');
        $tokens['default_mode'] = $choice($branding['default_mode'] ?? '', array('light', 'dark', 'system'), 'light');
        $tokens['header_style'] = $choice($branding['header_style'] ?? '', array('solid', 'transparent'), 'solid');
        $tokens['hero_style']   = $choice($branding['hero_style'] ?? '', array('angled', 'minimal', 'photo'), 'angled');
        $tokens['card_style']   = $choice($branding['card_style'] ?? '', array('soft', 'outlined', 'flat'), 'soft');
        $tokens['footer_style'] = $choice($branding['footer_style'] ?? '', array('dark', 'light'), 'dark');
        return $tokens;
    }

    private static function shadow_value(string $level, bool $dark_mode = false): string {
        if ($level === 'none') {
            return 'none';
        }
        if ($dark_mode) {
            return $level === 'strong' ? '0 26px 70px rgba(0,0,0,.6)' : '0 16px 44px rgba(0,0,0,.45)';
        }
        return $level === 'strong' ? '0 26px 70px rgba(26,20,16,.16)' : '0 16px 44px rgba(26,20,16,.08)';
    }

    /**
     * Fixed dark-mode surface palette. Brand colours stay constant between
     * modes; only surfaces, text and depth swap. Per-token dark overrides can
     * become settings later without changing the emitted variable names.
     */
    private static function dark_palette(): array {
        return array(
            'bg'           => '#131010',
            'surface'      => '#1e1916',
            'surface_soft' => '#29221d',
            'text'         => '#f6f0e9',
            'muted'        => '#b6aaa0',
            'border'       => '#3b332c',
        );
    }

    public static function print_brand_css(): void {
        $t = self::brand_tokens();

        $light_vars = '--tt-primary:' . $t['primary']
            . ';--tt-accent:' . $t['accent']
            . ';--tt-bg:' . $t['bg']
            . ';--tt-surface:' . $t['surface']
            . ';--tt-surface-soft:' . $t['surface_soft']
            . ';--tt-text:' . $t['text']
            . ';--tt-muted:' . $t['muted']
            . ';--tt-border:' . $t['border']
            . ';--tt-success:' . $t['success']
            . ';--tt-warning:' . $t['warning']
            . ';--tt-error:' . $t['error']
            . ';--tt-radius-sm:' . $t['radius_sm'] . 'px'
            . ';--tt-radius-md:' . $t['radius_md'] . 'px'
            . ';--tt-radius-lg:' . $t['radius_lg'] . 'px'
            . ';--tt-shadow:' . self::shadow_value($t['shadow'])
            . ($t['font_heading'] !== '' ? ';--tt-font-heading:' . $t['font_heading'] : '')
            . ($t['font_body']    !== '' ? ';--tt-font-body:'    . $t['font_body']    : '')
            // Legacy aliases for v0.2.x CSS. These stay pinned to the stored
            // legacy values in every mode so the old front end never flips
            // half-dark; new --tt-* consumers handle modes properly.
            . ';--tt-secondary:' . $t['secondary']
            . ';--tt-dark:' . $t['dark']
            . ';--tt-cream:' . $t['cream']
            . ';--tt-cream2:' . $t['surface_soft']
            . ';--tt-radius-full:999px'
            . ';--tt-border-input:' . self::darker_border($t['border'], $t['bg']);

        $dark = self::dark_palette();
        $dark_vars = '--tt-bg:' . $dark['bg']
            . ';--tt-surface:' . $dark['surface']
            . ';--tt-surface-soft:' . $dark['surface_soft']
            . ';--tt-text:' . $dark['text']
            . ';--tt-muted:' . $dark['muted']
            . ';--tt-border:' . $dark['border']
            . ';--tt-shadow:' . self::shadow_value($t['shadow'], true);

        $css = ':root{' . $light_vars . '}';
        if ($t['default_mode'] === 'dark') {
            $css .= ':root{' . $dark_vars . '}';
        } elseif ($t['default_mode'] === 'system') {
            $css .= '@media (prefers-color-scheme: dark){:root{' . $dark_vars . '}}';
        }

        echo '<style id="takeaway-os-brand">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput -- values sanitised in brand_tokens().
    }

    public static function body_classes(array $classes): array {
        $t = self::brand_tokens();
        $classes[] = 'tt-mode-' . $t['default_mode'];
        $classes[] = 'tt-style-header-' . $t['header_style'];
        $classes[] = 'tt-style-hero-' . $t['hero_style'];
        $classes[] = 'tt-style-card-' . $t['card_style'];
        $classes[] = 'tt-style-footer-' . $t['footer_style'];
        return $classes;
    }

    /**
     * Branding favicon wins at runtime when set. The stored WordPress
     * site_icon option is never modified, so clearing the branding field
     * restores whatever the site had before.
     */
    public static function filter_site_icon($value) {
        $favicon_id = absint(self::get('branding', 'favicon_id'));
        if ($favicon_id && wp_attachment_is_image($favicon_id)) {
            return $favicon_id;
        }
        return $value;
    }

    /**
     * Expose the computed --tt-border-input value for external callers
     * (e.g. Setup Health a11y checks). Returns the same value that is
     * emitted into the page <style> block.
     */
    public static function computed_border_input(): string {
        $t = self::brand_tokens();
        return self::darker_border($t['border'], $t['bg']);
    }

    private static function darker_border(string $border, string $bg): string {
        // Returns a form-input-safe border color: if the stored border is too light
        // for 3:1 UI-component contrast against the bg, fall back to a fixed darker value.
        // Verified safe value: #96857a passes 3.24:1 on the default cream bg (#f9f4ee)
        // and 3.47:1 on white — satisfying WCAG 1.4.11 non-text contrast in both cases.
        $safe = '#96857a';
        // Use the stored border if it's clearly darker than the bg (crude check via hex brightness).
        $b = ltrim($border, '#');
        $g = ltrim($bg, '#');
        if (strlen($b) === 6 && strlen($g) === 6) {
            $b_lum = (hexdec(substr($b,0,2)) * 299 + hexdec(substr($b,2,2)) * 587 + hexdec(substr($b,4,2)) * 114) / 1000;
            $g_lum = (hexdec(substr($g,0,2)) * 299 + hexdec(substr($g,2,2)) * 587 + hexdec(substr($g,4,2)) * 114) / 1000;
            if ($b_lum < ($g_lum - 60)) return $border; // border is sufficiently darker than bg
        }
        return $safe;
    }
}

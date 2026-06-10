<?php

defined('ABSPATH') || exit;

add_action('admin_post_ttheme_install_bundled_takeaway_os', 'ttheme_install_bundled_takeaway_os');

function ttheme_is_setup_screen(): bool {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    return $screen && $screen->id === 'appearance_page_takeaway-theme-setup';
}

function ttheme_admin_menu(): void {
    add_theme_page('Takeaway Theme Setup', 'Takeaway Theme Setup', 'manage_options', 'takeaway-theme-setup', 'ttheme_setup_page');
}

function ttheme_admin_assets($hook): void {
    $is_setup = $hook === 'appearance_page_takeaway-theme-setup';
    if (!$is_setup && !ttheme_setup_needs_attention()) {
        return;
    }

    wp_enqueue_style('takeaway-theme-admin', TTHEME_URL . '/assets/css/admin.css', array(), TTHEME_VERSION);
    wp_register_script('takeaway-theme-admin', '', array(), TTHEME_VERSION, true);
    wp_enqueue_script('takeaway-theme-admin');

    $show_modal = isset($_GET['ttheme_welcome']) || ttheme_setup_needs_attention();
    wp_add_inline_script('takeaway-theme-admin', 'window.TakeawayThemeSetup = ' . wp_json_encode(array('showModal' => $show_modal)) . ';');
    wp_add_inline_script('takeaway-theme-admin', "document.addEventListener('DOMContentLoaded',function(){var modal=document.querySelector('[data-ttheme-wizard-modal]');if(!modal)return;if(window.TakeawayThemeSetup&&window.TakeawayThemeSetup.showModal){modal.classList.add('is-open');}document.querySelectorAll('[data-ttheme-close-modal]').forEach(function(btn){btn.addEventListener('click',function(){modal.classList.remove('is-open');});});});");
}

function ttheme_bundled_takeaway_os_zip(): string {
    return TTHEME_DIR . '/inc/bundled-plugins/takeaway-os.zip';
}

function ttheme_bundled_manifest(): array {
    $manifest = TTHEME_DIR . '/inc/bundled-plugins/manifest.json';
    if (is_readable($manifest)) {
        $decoded = json_decode((string) file_get_contents($manifest), true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return array(
        'takeaway-os' => array(
            'name'      => 'Takeaway OS',
            'slug'      => 'takeaway-os',
            'main_file' => 'takeaway-os/takeaway-os.php',
            'version'   => '1.2.5',
            'package'   => 'takeaway-os.zip',
        ),
    );
}

function ttheme_takeaway_os_manifest(): array {
    return wp_parse_args(
        ttheme_bundled_manifest()['takeaway-os'] ?? array(),
        array(
            'name'      => 'Takeaway OS',
            'slug'      => 'takeaway-os',
            'main_file' => 'takeaway-os/takeaway-os.php',
            'version'   => '1.2.5',
            'package'   => 'takeaway-os.zip',
        )
    );
}

function ttheme_plugin_rows(): array {
    return array(
        array('Takeaway OS', 'takeaway-os/takeaway-os.php', 'takeaway-os', 'The restaurant cockpit, menu builder, CRM and module switchboard.', true, true),
        array('WooCommerce', 'woocommerce/woocommerce.php', 'woocommerce', 'Cart, checkout, orders, customers, tax and payment gateway layer.', true, false),
        array('WooCommerce Stripe Payment Gateway', 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php', 'woocommerce-gateway-stripe', 'Recommended card payment gateway.', false, false),
        array('FluentSMTP', 'fluent-smtp/fluent-smtp.php', 'fluent-smtp', 'Recommended reliable order emails.', false, false),
    );
}

function ttheme_get_plugins(): array {
    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    return get_plugins();
}

function ttheme_resolve_plugin_file(string $preferred_file, string $plugin_name = ''): string {
    $plugins = ttheme_get_plugins();
    if (isset($plugins[$preferred_file])) {
        return $preferred_file;
    }

    if ($plugin_name !== '') {
        foreach ($plugins as $file => $data) {
            if (!empty($data['Name']) && $data['Name'] === $plugin_name) {
                return $file;
            }
        }
    }

    return $preferred_file;
}

function ttheme_plugin_active($file, string $name = ''): bool {
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    return is_plugin_active(ttheme_resolve_plugin_file($file, $name));
}

function ttheme_plugin_installed($file, string $name = ''): bool {
    $plugins = ttheme_get_plugins();
    $resolved = ttheme_resolve_plugin_file($file, $name);
    return isset($plugins[$resolved]);
}

function ttheme_plugin_action($file, $slug, string $name = ''): string {
    $resolved = ttheme_resolve_plugin_file($file, $name);
    if (!ttheme_plugin_installed($file, $name)) {
        return wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=' . $slug), 'install-plugin_' . $slug);
    }

    if (!ttheme_plugin_active($file, $name)) {
        return wp_nonce_url(self_admin_url('plugins.php?action=activate&plugin=' . rawurlencode($resolved)), 'activate-plugin_' . $resolved);
    }

    return '';
}

function ttheme_takeaway_os_installed_data(): array {
    $manifest = ttheme_takeaway_os_manifest();
    $preferred_file = (string) $manifest['main_file'];
    $resolved = ttheme_resolve_plugin_file($preferred_file, 'Takeaway OS');
    $installed = ttheme_plugin_installed($preferred_file, 'Takeaway OS');
    $active = $installed && ttheme_plugin_active($preferred_file, 'Takeaway OS');
    $version = '';
    $plugins = ttheme_get_plugins();

    if ($installed && isset($plugins[$resolved]['Version'])) {
        $version = (string) $plugins[$resolved]['Version'];
    }

    if ($active && defined('TTOS_VERSION') && is_string(TTOS_VERSION) && TTOS_VERSION !== '') {
        $version = TTOS_VERSION;
    }

    if ($installed && $version === '') {
        $plugin_file = WP_PLUGIN_DIR . '/' . $resolved;
        if (is_readable($plugin_file)) {
            if (!function_exists('get_plugin_data')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $plugin_data = get_plugin_data($plugin_file, false, false);
            $version = (string) ($plugin_data['Version'] ?? '');
        }
    }

    return array(
        'preferred_file' => $preferred_file,
        'resolved_file'  => $resolved,
        'installed'      => $installed,
        'active'         => $active,
        'version'        => $version,
    );
}

function ttheme_takeaway_os_status(): array {
    $manifest = ttheme_takeaway_os_manifest();
    $installed = ttheme_takeaway_os_installed_data();
    $zip_ok = is_readable(ttheme_bundled_takeaway_os_zip());
    $bundled_version = (string) ($manifest['version'] ?? '');
    $installed_version = (string) ($installed['version'] ?? '');

    $status = array(
        'state'             => 'missing',
        'label'             => 'Missing',
        'class'             => 'bad',
        'button_label'      => 'Install Takeaway OS',
        'button_enabled'    => $zip_ok,
        'open_launchpad'    => false,
        'message'           => $zip_ok ? 'Bundled inside this theme package.' : 'The bundled plugin ZIP is missing or unreadable.',
        'bundled_version'   => $bundled_version,
        'installed_version' => $installed_version,
        'installed'         => !empty($installed['installed']),
        'active'            => !empty($installed['active']),
        'plugin_file'       => (string) ($installed['resolved_file'] ?? $manifest['main_file']),
        'zip_ok'            => $zip_ok,
    );

    if (!$zip_ok) {
        $status['state'] = 'bundle_missing';
        $status['label'] = 'Bundled ZIP missing';
        return $status;
    }

    if (!$status['installed']) {
        return $status;
    }

    if ($installed_version !== '' && $bundled_version !== '') {
        $compare = version_compare($installed_version, $bundled_version);
        if ($compare < 0) {
            $status['state'] = 'update_available';
            $status['label'] = 'Update available';
            $status['class'] = 'warn';
            $status['button_label'] = 'Update bundled Takeaway OS';
            $status['message'] = 'Installed ' . $installed_version . ' is older than bundled ' . $bundled_version . '.';
            return $status;
        }
        if ($compare > 0) {
            $status['state'] = 'newer_than_bundled';
            $status['label'] = 'Newer than bundled';
            $status['class'] = 'warn';
            $status['button_label'] = $status['active'] ? 'Open Launchpad' : 'Activate Takeaway OS';
            $status['open_launchpad'] = $status['active'];
            $status['message'] = 'Installed ' . $installed_version . ' is newer than bundled ' . $bundled_version . '.';
            return $status;
        }
    }

    if ($status['active']) {
        $status['state'] = 'active';
        $status['label'] = 'Active';
        $status['class'] = 'ok';
        $status['button_label'] = 'Open Launchpad';
        $status['open_launchpad'] = true;
        $status['message'] = $installed_version !== '' ? 'Installed and active at ' . $installed_version . '.' : 'Installed and active.';
        return $status;
    }

    $status['state'] = 'installed';
    $status['label'] = 'Installed';
    $status['class'] = 'warn';
    $status['button_label'] = 'Activate Takeaway OS';
    $status['message'] = $installed_version !== '' ? 'Installed at ' . $installed_version . ' but not active.' : 'Installed but not active.';
    return $status;
}

function ttheme_takeaway_os_status_message(): string {
    $status = ttheme_takeaway_os_status();
    $versions = array();

    if ($status['bundled_version'] !== '') {
        $versions[] = 'Bundled ' . $status['bundled_version'];
    }
    if ($status['installed_version'] !== '') {
        $versions[] = 'Installed ' . $status['installed_version'];
    }

    return trim($status['message'] . (empty($versions) ? '' : ' ' . implode(' · ', $versions)));
}

function ttheme_takeaway_os_button_html(): string {
    if (!current_user_can('install_plugins')) {
        return '';
    }

    $status = ttheme_takeaway_os_status();
    if (!empty($status['open_launchpad'])) {
        return '<a class="ttheme-btn primary" href="' . esc_url(admin_url('admin.php?page=takeaway-os-launchpad')) . '">' . esc_html($status['button_label']) . '</a>';
    }

    $html = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ttheme-inline-form">';
    $html .= wp_nonce_field('ttheme_install_bundled_takeaway_os', '_wpnonce', true, false);
    $html .= '<input type="hidden" name="action" value="ttheme_install_bundled_takeaway_os">';
    $html .= '<button class="ttheme-btn primary" ' . disabled(empty($status['button_enabled']), true, false) . '>' . esc_html($status['button_label']) . '</button>';
    if (!$status['zip_ok']) {
        $html .= '<span class="bad">Bundled ZIP missing</span>';
    }
    $html .= '</form>';
    return $html;
}

function ttheme_setup_needs_attention(): bool {
    if (!current_user_can('manage_options')) {
        return false;
    }

    $os_status = ttheme_takeaway_os_status();
    $wc_active = ttheme_plugin_active('woocommerce/woocommerce.php', 'WooCommerce');
    if ($os_status['active'] && $wc_active) {
        delete_option('ttheme_setup_modal_pending');
        return false;
    }
    if (get_option('ttheme_setup_modal_pending')) {
        return true;
    }
    if (!$os_status['active']) {
        return true;
    }
    if (!$wc_active) {
        return true;
    }

    return false;
}

function ttheme_install_bundled_takeaway_os(): void {
    if (!current_user_can('install_plugins') || !current_user_can('activate_plugins')) {
        wp_die(esc_html__('You do not have permission to install or activate plugins.', 'takeaway-theme'));
    }
    check_admin_referer('ttheme_install_bundled_takeaway_os');

    $setup_redirect = admin_url('themes.php?page=takeaway-theme-setup');
    $launchpad_redirect = admin_url('admin.php?page=takeaway-os-launchpad');
    $zip = ttheme_bundled_takeaway_os_zip();
    if (!is_readable($zip)) {
        wp_safe_redirect(add_query_arg('ttheme_notice', 'bundle-missing', $setup_redirect));
        exit;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    $status = ttheme_takeaway_os_status();
    $plugin_file = $status['plugin_file'];
    $was_active = !empty($status['active']);
    $skin = new Automatic_Upgrader_Skin();
    $upgrader = new Plugin_Upgrader($skin);
    $notice = 'takeaway-os-ready';
    $redirect = $setup_redirect;

    if ($status['state'] === 'missing') {
        $result = $upgrader->install($zip, array('overwrite_package' => true));
        wp_clean_plugins_cache(false);
        if (is_wp_error($result) || !$result) {
            wp_safe_redirect(add_query_arg('ttheme_notice', 'bundle-install-failed', $setup_redirect));
            exit;
        }
        $plugin_file = ttheme_takeaway_os_status()['plugin_file'];
        $activate = activate_plugin($plugin_file);
        if (is_wp_error($activate)) {
            wp_safe_redirect(add_query_arg('ttheme_notice', 'bundle-activate-failed', $setup_redirect));
            exit;
        }
        $redirect = $launchpad_redirect;
    } elseif ($status['state'] === 'update_available') {
        $result = $upgrader->install($zip, array('overwrite_package' => true));
        wp_clean_plugins_cache(false);
        if (is_wp_error($result) || !$result) {
            wp_safe_redirect(add_query_arg('ttheme_notice', 'bundle-update-failed', $setup_redirect));
            exit;
        }
        $plugin_file = ttheme_takeaway_os_status()['plugin_file'];
        if ($was_active) {
            $activate = activate_plugin($plugin_file);
            if (is_wp_error($activate)) {
                wp_safe_redirect(add_query_arg('ttheme_notice', 'bundle-activate-failed', $setup_redirect));
                exit;
            }
            $redirect = $launchpad_redirect;
        }
        $notice = 'bundle-updated';
    } elseif (!$status['active']) {
        $activate = activate_plugin($plugin_file);
        if (is_wp_error($activate)) {
            wp_safe_redirect(add_query_arg('ttheme_notice', 'bundle-activate-failed', $setup_redirect));
            exit;
        }
        $redirect = $launchpad_redirect;
    } elseif ($status['state'] === 'newer_than_bundled') {
        $notice = 'bundle-newer-installed';
    } else {
        $redirect = $launchpad_redirect;
    }

    wp_safe_redirect(add_query_arg('ttheme_notice', $notice, $redirect));
    exit;
}

function ttheme_setup_notice(): void {
    $notice = sanitize_key($_GET['ttheme_notice'] ?? '');
    if (!$notice) {
        return;
    }

    $messages = array(
        'bundle-missing'         => 'Bundled Takeaway OS package is missing from the theme folder.',
        'bundle-install-failed'  => 'Takeaway OS could not be installed from the bundled package.',
        'bundle-update-failed'   => 'Takeaway OS was detected, but the bundled update could not be applied.',
        'bundle-activate-failed' => 'Takeaway OS was installed or updated but could not be activated.',
        'bundle-updated'         => 'Takeaway OS was updated from the bundled package.',
        'bundle-newer-installed' => 'A newer version of Takeaway OS is already installed, so the bundled copy was not used.',
        'takeaway-os-ready'      => 'Takeaway OS is installed and active.',
    );

    if (isset($messages[$notice])) {
        echo '<div class="ttheme-notice"><strong>' . esc_html($messages[$notice]) . '</strong></div>';
    }
}

function ttheme_setup_wizard_modal(): void {
    $os_status = ttheme_takeaway_os_status();
    $wc_active = ttheme_plugin_active('woocommerce/woocommerce.php', 'WooCommerce');

    echo '<div class="ttheme-modal" data-ttheme-wizard-modal aria-hidden="true">';
    echo '<div class="ttheme-modal-backdrop" data-ttheme-close-modal></div>';
    echo '<div class="ttheme-modal-panel" role="dialog" aria-modal="true" aria-labelledby="ttheme-modal-title">';
    echo '<button type="button" class="ttheme-modal-close" data-ttheme-close-modal aria-label="Close setup wizard">×</button>';
    echo '<div class="ttheme-kicker">Quick install wizard</div>';
    echo '<h2 id="ttheme-modal-title">Set up Takeaway Theme</h2>';
    echo '<p class="ttheme-modal-lede">This pop-up appears after the theme is activated so you are not left hunting through WordPress. Install the bundled Takeaway OS engine now, then Launchpad will guide WooCommerce, pages, payments, menu and go-live checks.</p>';
    echo '<div class="ttheme-setup-steps">';
    echo '<div class="ttheme-step ' . ($os_status['active'] ? 'done' : 'active') . '"><span>1</span><div><strong>Install Takeaway OS</strong><p>' . esc_html(ttheme_takeaway_os_status_message()) . '</p></div></div>';
    echo '<div class="ttheme-step ' . ($os_status['active'] ? ($wc_active ? 'done' : 'active') : '') . '"><span>2</span><div><strong>Install WooCommerce</strong><p>' . ($wc_active ? 'WooCommerce is active.' : 'Handled next inside Takeaway OS Launchpad.') . '</p></div></div>';
    echo '<div class="ttheme-step"><span>3</span><div><strong>Finish Launchpad</strong><p>Business details, branding, menu, payments and go-live checks.</p></div></div>';
    echo '</div>';
    echo '<div class="ttheme-modal-actions">';
    echo ttheme_takeaway_os_button_html();
    echo '<button type="button" class="ttheme-btn ghost" data-ttheme-close-modal>Look around first</button>';
    echo '</div>';
    echo '</div></div>';
}

function ttheme_global_setup_modal(): void {
    if (ttheme_is_setup_screen() || !ttheme_setup_needs_attention()) {
        return;
    }

    echo '<div class="ttheme-global-bootstrap" aria-live="polite">';
    ttheme_setup_wizard_modal();
    echo '</div>';
}

function ttheme_setup_page(): void {
    $os_status = ttheme_takeaway_os_status();

    echo '<div class="ttheme-admin"><h1>Takeaway Theme Setup</h1>';
    ttheme_setup_wizard_modal();
    ttheme_setup_notice();
    echo '<p>Upload the theme once, then follow the pop-up setup wizard. It installs the bundled Takeaway OS engine first, then Launchpad handles WooCommerce, payments and optional tools.</p>';
    echo '<div class="ttheme-card"><h2>Bundled install</h2><p>Takeaway OS is included inside this theme package for quick private distribution. It still installs as a normal standalone plugin, so data and updates stay separate from the theme.</p>';
    echo '<p><span class="' . esc_attr($os_status['class']) . '">' . esc_html($os_status['label']) . '</span> ' . esc_html(ttheme_takeaway_os_status_message()) . '</p>';
    echo '<p>' . ttheme_takeaway_os_button_html() . '</p>';
    echo '</div><div class="ttheme-card"><h2>Plugin checklist</h2>';
    foreach (ttheme_plugin_rows() as $row) {
        list($name, $file, $slug, $desc, $required, $bundled) = $row;
        if ($bundled) {
            echo '<div class="ttheme-row"><div><strong>' . esc_html($name) . '</strong><p>' . esc_html($desc) . '</p><small>' . esc_html(ttheme_takeaway_os_status_message()) . '</small></div><span class="' . esc_attr($os_status['class']) . '">' . esc_html($os_status['label']) . '</span>';
            if (current_user_can('install_plugins')) {
                echo ttheme_takeaway_os_button_html();
            }
            echo '</div>';
            continue;
        }

        $active = ttheme_plugin_active($file, $name);
        $installed = ttheme_plugin_installed($file, $name);
        $status = $active ? 'Active' : ($installed ? 'Installed' : 'Missing');
        echo '<div class="ttheme-row"><div><strong>' . esc_html($name) . '</strong><p>' . esc_html($desc) . '</p></div><span class="' . ($active ? 'ok' : ($required ? 'bad' : 'warn')) . '">' . esc_html($status) . '</span>';
        if (!$active && current_user_can('install_plugins')) {
            echo '<a class="ttheme-btn" href="' . esc_url(ttheme_plugin_action($file, $slug, $name)) . '">' . esc_html($installed ? 'Activate' : 'Install') . '</a>';
        }
        echo '</div>';
    }
    echo '</div><p><a class="ttheme-btn primary" href="' . esc_url(admin_url('admin.php?page=takeaway-os-launchpad')) . '">Open Takeaway OS Launchpad</a></p></div>';
}

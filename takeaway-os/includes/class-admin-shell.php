<?php

defined('ABSPATH') || exit;

final class TTOS_Admin_Shell {
    public static function primary_items(): array {
        return array(
            array('slug' => 'takeaway-os', 'label' => 'Dashboard', 'cap' => 'ttos_access'),
            array('slug' => 'takeaway-os-launchpad', 'label' => 'Launchpad', 'cap' => 'ttos_manage_settings'),
            array('slug' => 'takeaway-os-setup-health', 'label' => 'Setup Health', 'cap' => 'ttos_manage_settings'),
            array('slug' => 'takeaway-os-menu', 'label' => 'Menu', 'cap' => 'ttos_manage_menu'),
            array('slug' => 'takeaway-os-orders', 'label' => 'Orders', 'cap' => 'ttos_view_orders'),
            array('slug' => 'takeaway-os-kitchen', 'label' => 'Takeaway Tickets', 'cap' => 'ttos_view_orders'),
            array('slug' => 'takeaway-os-customers', 'label' => 'Customers / CRM', 'cap' => 'ttos_view_reports'),
            array('slug' => 'takeaway-os-reports', 'label' => 'Reports', 'cap' => 'ttos_view_reports'),
            array('slug' => 'takeaway-os-brand-guide', 'label' => 'Brand Guide', 'cap' => 'ttos_manage_settings'),
            array('slug' => 'takeaway-os-site-content', 'label' => 'Site Content', 'cap' => 'ttos_manage_settings'),
            array('slug' => 'takeaway-os-delivery', 'label' => 'Delivery', 'cap' => 'ttos_manage_settings'),
            array('slug' => 'takeaway-os-payments', 'label' => 'Payments', 'cap' => 'ttos_manage_settings'),
            array('slug' => 'takeaway-os-settings', 'label' => 'Settings', 'cap' => 'ttos_manage_settings'),
            array('slug' => 'takeaway-os-modules', 'label' => 'Add-ons', 'cap' => 'ttos_modules'),
            array('slug' => 'takeaway-os-features', 'label' => 'Feature Builder', 'cap' => 'ttos_modules'),
            array('slug' => 'takeaway-os-operations', 'label' => 'Operations', 'cap' => 'ttos_manage_settings'),
            array('slug' => 'takeaway-os-golive', 'label' => 'Go Live', 'cap' => 'ttos_manage_settings'),
            array('slug' => 'takeaway-os-production', 'label' => 'Production Tools', 'cap' => 'ttos_manage_settings'),
        );
    }

    public static function current_page(): string {
        return isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'takeaway-os';
    }

    public static function render_start(array $args): void {
        $title = isset($args['title']) ? (string) $args['title'] : '';
        $subtitle = isset($args['subtitle']) ? (string) $args['subtitle'] : '';
        $eyebrow = isset($args['eyebrow']) ? (string) $args['eyebrow'] : 'Takeaway OS';
        $active = isset($args['active']) ? sanitize_key((string) $args['active']) : self::current_page();
        $view_url = array_key_exists('view_url', $args) ? (string) $args['view_url'] : home_url('/');
        $view_label = isset($args['view_label']) ? (string) $args['view_label'] : 'View site ↗';
        $view_target = isset($args['view_target']) ? (string) $args['view_target'] : '_blank';
        $view_rel = isset($args['view_rel']) ? (string) $args['view_rel'] : 'noreferrer noopener';

        echo '<div class="ttos-wrap"><div class="ttos-shell ttos-admin-shell">';
        echo '<div class="ttos-top ttos-admin-header"><div><p class="ttos-eyebrow">' . esc_html($eyebrow) . '</p><h1>' . esc_html($title) . '</h1>';
        if ($subtitle !== '') {
            echo '<p>' . esc_html($subtitle) . '</p>';
        }
        echo '</div>';
        if ($view_url !== '') {
            echo '<a class="ttos-pill" href="' . esc_url($view_url) . '"';
            if ($view_target !== '') {
                echo ' target="' . esc_attr($view_target) . '"';
            }
            if ($view_rel !== '') {
                echo ' rel="' . esc_attr($view_rel) . '"';
            }
            echo '>' . esc_html($view_label) . '</a>';
        }
        echo '</div>';

        self::render_primary_nav($active);

        if (!empty($args['secondary_nav']) && is_array($args['secondary_nav'])) {
            self::render_secondary_nav(
                $args['secondary_nav'],
                array(
                    'label' => isset($args['secondary_nav_label']) ? (string) $args['secondary_nav_label'] : 'Sections',
                    'aria_label' => isset($args['secondary_nav_aria_label']) ? (string) $args['secondary_nav_aria_label'] : 'Sections',
                )
            );
        }
    }

    public static function render_end(): void {
        echo '</div></div>';
    }

    public static function render_primary_nav(string $active = ''): void {
        $active = $active ? sanitize_key($active) : self::current_page();
        echo '<nav class="ttos-nav ttos-primary-nav" aria-label="Takeaway OS sections">';
        foreach (self::primary_items() as $item) {
            if (!empty($item['cap']) && !current_user_can($item['cap'])) {
                continue;
            }
            $slug = sanitize_key((string) $item['slug']);
            $is_active = $active === $slug;
            echo '<a class="' . esc_attr($is_active ? 'active' : '') . '"';
            if ($is_active) {
                echo ' aria-current="page"';
            }
            echo ' href="' . esc_url(admin_url('admin.php?page=' . $slug)) . '">' . esc_html((string) $item['label']) . '</a>';
        }
        echo '</nav>';
    }

    public static function render_secondary_nav(array $items, array $args = array()): void {
        $label = isset($args['label']) ? (string) $args['label'] : 'Sections';
        $aria_label = isset($args['aria_label']) ? (string) $args['aria_label'] : $label;

        echo '<div class="ttos-secondary-nav">';
        echo '<span class="ttos-secondary-nav-label">' . esc_html($label) . '</span>';
        echo '<nav class="ttos-secondary-nav-list" aria-label="' . esc_attr($aria_label) . '">';
        foreach ($items as $item) {
            if (empty($item['label']) || empty($item['url'])) {
                continue;
            }
            $is_active = !empty($item['active']);
            echo '<a class="ttos-secondary-nav-item' . ($is_active ? ' active' : '') . '"';
            if ($is_active) {
                echo ' aria-current="page"';
            }
            echo ' href="' . esc_url((string) $item['url']) . '">' . esc_html((string) $item['label']) . '</a>';
        }
        echo '</nav></div>';
    }
}

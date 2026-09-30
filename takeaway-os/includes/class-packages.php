<?php

defined('ABSPATH') || exit;

/** Local module presets only. Never a checkout licence or remote dependency. */
final class TTOS_Packages {
    public static function profiles(): array {
        $base = array('content_manager' => true, 'allergen_filters' => true, 'inventory_lite' => true);
        return array(
            'website' => array('label' => 'Website', 'modules' => array_merge($base, array('meal_deals' => false, 'loyalty' => false, 'stamp_cards' => false, 'crm_pro' => false, 'analytics_pro' => false, 'promo_engine' => false))),
            'ordering' => array('label' => 'Ordering', 'modules' => array_merge($base, array('meal_deals' => true, 'loyalty' => false, 'stamp_cards' => false, 'crm_pro' => false, 'analytics_pro' => false, 'promo_engine' => false))),
            'growth' => array('label' => 'Growth', 'modules' => array_merge($base, array('meal_deals' => true, 'loyalty' => true, 'stamp_cards' => true, 'crm_pro' => true, 'analytics_pro' => true, 'promo_engine' => true))),
        );
    }

    public static function apply(string $profile): bool {
        $profiles = self::profiles();
        if (!current_user_can('manage_options') || !isset($profiles[$profile])) return false;
        // Deliberately preserve separately configured printer, SMS, EPOS and
        // accounting modules, as well as all customer data and gateway settings.
        $modules = TTOS_Settings::modules();
        TTOS_Settings::update_section('modules', array_merge($modules, $profiles[$profile]['modules']));
        update_option('ttos_package_profile', $profile, false);
        return true;
    }
}

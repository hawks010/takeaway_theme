<?php
/**
 * Homepage: a sales funnel orchestrated from section template parts.
 * Sections read the Site Content CRM and hide themselves when optional
 * content is missing. The full menu grid intentionally does NOT render
 * here — ordering lives on the Menu page.
 */

defined('ABSPATH') || exit;

get_header();

$tt_home_sections = apply_filters('tt_home_sections', array(
    'hero',
    'trust-strip',
    'featured-food',
    'why-direct',
    'offers',
    'about',
    'booking',
    'opening-hours',
    'contact-map',
    'reviews',
    'newsletter',
    'bottom-cta',
));

foreach ($tt_home_sections as $tt_home_section) {
    get_template_part('template-parts/home/' . $tt_home_section);
}

get_footer();

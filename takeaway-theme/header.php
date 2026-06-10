<?php defined('ABSPATH') || exit; ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="tt-header" id="site-header">
    <div class="tt-wrap tt-header-inner">
        <a class="tt-brand" href="<?php echo esc_url(home_url('/')); ?>">
            <?php
            $tt_logo_id = absint(ttheme_brand('logo_id', 0));
            if (has_custom_logo()) {
                the_custom_logo();
            } elseif ($tt_logo_id) {
                echo wp_get_attachment_image($tt_logo_id, 'medium', false, array('class' => 'tt-brand-logo', 'alt' => esc_attr(ttheme_business('restaurant_name', get_bloginfo('name')))));
            } else { ?>
                <span class="tt-dot"></span><strong><?php echo esc_html(ttheme_business('restaurant_name', get_bloginfo('name'))); ?></strong>
            <?php } ?>
            <small><?php echo esc_html(ttheme_business('tagline', get_bloginfo('description'))); ?></small>
        </a>
        <nav class="tt-nav" aria-label="Primary">
            <?php
            if (has_nav_menu('primary')) {
                wp_nav_menu(array('theme_location' => 'primary', 'container' => false, 'fallback_cb' => false));
            } else {
                ttheme_fallback_nav('primary');
            }
            ?>
        </nav>
        <div class="tt-header-actions">
            <?php if (shortcode_exists('takeaway_open_status')) { echo do_shortcode('[takeaway_open_status]'); } else { ?><span class="tt-status">Open today</span><?php } ?>
            <a class="tt-order" href="<?php echo esc_url(ttheme_page_url('menu', '/menu/')); ?>">Order now</a>
        </div>
    </div>
</header>
<main id="content">

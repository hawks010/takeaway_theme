<?php
/**
 * Main template file for Takeaway Theme.
 *
 * WordPress requires a root index.php file for classic standalone themes.
 */

defined('ABSPATH') || exit;

get_header();
?>

<main id="primary" class="site-main tt-main">
    <div class="tt-wrap tt-page-wrap">
        <?php if (have_posts()) : ?>
            <?php while (have_posts()) : the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('tt-content-card'); ?>>
                    <?php if (! is_front_page()) : ?>
                        <h1><?php the_title(); ?></h1>
                    <?php endif; ?>
                    <div class="tt-entry-content">
                        <?php the_content(); ?>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php else : ?>
            <article class="tt-content-card">
                <h1><?php esc_html_e('Nothing found', 'takeaway-theme'); ?></h1>
                <p><?php esc_html_e('Ready for your first takeaway page.', 'takeaway-theme'); ?></p>
            </article>
        <?php endif; ?>
    </div>
</main>

<?php
get_footer();

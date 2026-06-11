<?php
/** Generic page template: compact heading + content area (token-driven). */

defined('ABSPATH') || exit;

get_header();
?>
<section class="tt-pagehead">
    <div class="tt-wrap">
        <h1><?php the_title(); ?></h1>
    </div>
</section>
<section class="tt-wrap tt-pagecontent">
    <?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
</section>
<?php get_footer(); ?>

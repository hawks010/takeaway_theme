<?php get_header(); ?>
<section class="tt-page-head"><div class="tt-wrap"><h1><?php the_title(); ?></h1></div></section>
<section class="tt-wrap tt-content">
<?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
</section>
<?php get_footer(); ?>

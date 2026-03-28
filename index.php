<?php
/**
 * ExamHub — index.php
 * Fallback template. Redirects to relevant templates.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="container-xl py-4">
  <?php
  if ( have_posts() ) :
    while ( have_posts() ) : the_post();
      get_template_part( 'template-parts/content', get_post_type() );
    endwhile;
    the_posts_navigation();
  else :
    get_template_part( 'template-parts/content', 'none' );
  endif;
  ?>
</div>
<?php
get_footer();

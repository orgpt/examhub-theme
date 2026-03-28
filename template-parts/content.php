<?php
/**
 * Default content template fallback.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'eh-content-fallback' ); ?>>
  <header class="mb-3">
    <h1 class="eh-page-title mb-2"><?php the_title(); ?></h1>
  </header>

  <div class="entry-content">
    <?php
    the_content();

    wp_link_pages(
      [
        'before' => '<div class="mt-3">' . esc_html__( 'Pages:', 'examhub' ),
        'after'  => '</div>',
      ]
    );
    ?>
  </div>
</article>

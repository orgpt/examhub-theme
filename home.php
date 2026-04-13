<?php
/**
 * Blog index template.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

get_header();

$posts_page_id    = (int) get_option( 'page_for_posts' );
$blog_title       = $posts_page_id ? get_the_title( $posts_page_id ) : __( 'ExamHub Blog', 'examhub' );
$blog_description = $posts_page_id ? get_post_field( 'post_excerpt', $posts_page_id ) : '';

if ( ! $blog_description ) {
    $blog_description = __( 'Practical study strategies, exam guidance, and smarter learning tips for students who want consistent progress.', 'examhub' );
}

$current_page = max( 1, get_query_var( 'paged', 1 ) );
$recent_posts = new WP_Query(
    [
        'post_type'           => 'post',
        'posts_per_page'      => 4,
        'post_status'         => 'publish',
        'ignore_sticky_posts' => true,
        'post__not_in'        => get_option( 'sticky_posts' ),
    ]
);
?>
<section class="eh-blog-shell eh-blog-index py-4 py-lg-5">
  <div class="container-xl">
    <header class="eh-blog-hero mb-4 mb-lg-5">
      <div class="row g-4 align-items-end">
        <div class="col-lg-8">
          <span class="eh-blog-kicker"><?php esc_html_e( 'Blog', 'examhub' ); ?></span>
          <h1 class="eh-blog-hero-title"><?php echo esc_html( $blog_title ); ?></h1>
          <p class="eh-blog-hero-text mb-0"><?php echo esc_html( $blog_description ); ?></p>
        </div>
        <div class="col-lg-4">
          <div class="eh-blog-hero-panel">
            <div class="eh-blog-hero-stat">
              <strong><?php echo esc_html( (string) wp_count_posts( 'post' )->publish ); ?>+</strong>
              <span><?php esc_html_e( 'Published articles', 'examhub' ); ?></span>
            </div>
            <div class="eh-blog-hero-stat">
              <strong><?php echo esc_html( (string) wp_count_terms( [ 'taxonomy' => 'category', 'hide_empty' => true ] ) ); ?></strong>
              <span><?php esc_html_e( 'Active categories', 'examhub' ); ?></span>
            </div>
          </div>
        </div>
      </div>
    </header>

    <div class="row g-4 g-xl-5">
      <div class="col-xl-8">
        <?php if ( have_posts() ) : ?>
          <div class="eh-blog-feed">
            <?php
            $post_index = 0;

            while ( have_posts() ) :
                the_post();
                $post_index++;
                $is_featured = 1 === $current_page && 1 === $post_index;
                $category    = get_the_category();
                $thumb_size  = $is_featured ? 'large' : 'medium_large';
                $reading_time = max( 1, (int) ceil( str_word_count( wp_strip_all_tags( get_the_content() ) ) / 200 ) );
                ?>
              <article id="post-<?php the_ID(); ?>" <?php post_class( $is_featured ? 'eh-blog-featured' : 'eh-blog-card' ); ?>>
                <a class="eh-blog-card-media" href="<?php the_permalink(); ?>" aria-label="<?php the_title_attribute(); ?>">
                  <?php if ( has_post_thumbnail() ) : ?>
                    <?php the_post_thumbnail( $thumb_size, [ 'loading' => $is_featured ? 'eager' : 'lazy' ] ); ?>
                  <?php else : ?>
                    <span class="eh-blog-card-placeholder">
                      <i class="bi bi-journal-richtext"></i>
                    </span>
                  <?php endif; ?>
                </a>

                <div class="eh-blog-card-content">
                  <div class="eh-blog-card-meta">
                    <?php if ( ! empty( $category ) ) : ?>
                      <a class="eh-blog-pill" href="<?php echo esc_url( get_category_link( $category[0]->term_id ) ); ?>">
                        <?php echo esc_html( $category[0]->name ); ?>
                      </a>
                    <?php endif; ?>
                    <span><i class="bi bi-calendar3"></i><?php echo esc_html( get_the_date() ); ?></span>
                    <span><i class="bi bi-clock-history"></i><?php echo esc_html( sprintf( _n( '%s min read', '%s mins read', $reading_time, 'examhub' ), $reading_time ) ); ?></span>
                  </div>

                  <h2 class="eh-blog-card-title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                  </h2>

                  <p class="eh-blog-card-excerpt">
                    <?php echo esc_html( wp_trim_words( get_the_excerpt(), $is_featured ? 34 : 22 ) ); ?>
                  </p>

                  <div class="eh-blog-card-footer">
                    <div class="eh-blog-author-inline">
                      <span class="eh-blog-author-avatar"><?php echo get_avatar( get_the_author_meta( 'ID' ), 44 ); ?></span>
                      <div>
                        <strong><?php the_author(); ?></strong>
                        <span><?php esc_html_e( 'Article author', 'examhub' ); ?></span>
                      </div>
                    </div>
                    <a class="eh-blog-readmore" href="<?php the_permalink(); ?>">
                      <?php esc_html_e( 'Read article', 'examhub' ); ?>
                      <i class="bi bi-arrow-left-short"></i>
                    </a>
                  </div>
                </div>
              </article>
            <?php endwhile; ?>
          </div>

          <nav class="eh-blog-pagination mt-4 mt-lg-5" aria-label="<?php esc_attr_e( 'Posts navigation', 'examhub' ); ?>">
            <?php
            the_posts_pagination(
                [
                    'mid_size'           => 1,
                    'prev_text'          => '<i class="bi bi-arrow-right"></i>',
                    'next_text'          => '<i class="bi bi-arrow-left"></i>',
                    'screen_reader_text' => __( 'Posts navigation', 'examhub' ),
                ]
            );
            ?>
          </nav>
        <?php else : ?>
          <?php get_template_part( 'template-parts/content', 'none' ); ?>
        <?php endif; ?>
      </div>

      <aside class="col-xl-4">
        <div class="eh-blog-sidebar">
          <section class="eh-blog-sidebar-card">
            <h2><?php esc_html_e( 'Search the blog', 'examhub' ); ?></h2>
            <form role="search" method="get" class="eh-blog-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
              <label class="screen-reader-text" for="eh-blog-search"><?php esc_html_e( 'Search for:', 'examhub' ); ?></label>
              <input id="eh-blog-search" type="search" class="form-control" placeholder="<?php esc_attr_e( 'Search articles, topics, tips...', 'examhub' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s">
              <input type="hidden" name="post_type" value="post">
              <button class="btn btn-primary" type="submit"><?php esc_html_e( 'Search', 'examhub' ); ?></button>
            </form>
          </section>

          <section class="eh-blog-sidebar-card">
            <div class="eh-blog-sidebar-heading">
              <h2><?php esc_html_e( 'Popular categories', 'examhub' ); ?></h2>
              <span><?php esc_html_e( 'Browse by topic', 'examhub' ); ?></span>
            </div>
            <div class="eh-blog-topic-cloud">
              <?php foreach ( get_categories( [ 'orderby' => 'count', 'order' => 'DESC', 'number' => 8 ] ) as $term ) : ?>
                <a href="<?php echo esc_url( get_category_link( $term->term_id ) ); ?>" class="eh-blog-topic">
                  <span><?php echo esc_html( $term->name ); ?></span>
                  <strong><?php echo esc_html( (string) $term->count ); ?></strong>
                </a>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="eh-blog-sidebar-card">
            <div class="eh-blog-sidebar-heading">
              <h2><?php esc_html_e( 'Fresh reads', 'examhub' ); ?></h2>
              <span><?php esc_html_e( 'Start with these', 'examhub' ); ?></span>
            </div>
            <div class="eh-blog-mini-list">
              <?php if ( $recent_posts->have_posts() ) : ?>
                <?php while ( $recent_posts->have_posts() ) : $recent_posts->the_post(); ?>
                  <a class="eh-blog-mini-post" href="<?php the_permalink(); ?>">
                    <span class="eh-blog-mini-date"><?php echo esc_html( get_the_date() ); ?></span>
                    <strong><?php the_title(); ?></strong>
                  </a>
                <?php endwhile; ?>
                <?php wp_reset_postdata(); ?>
              <?php endif; ?>
            </div>
          </section>

          <section class="eh-blog-sidebar-card eh-blog-cta">
            <span class="eh-blog-kicker"><?php esc_html_e( 'Study smarter', 'examhub' ); ?></span>
            <h2><?php esc_html_e( 'Turn reading into progress', 'examhub' ); ?></h2>
            <p><?php esc_html_e( 'Use the blog for strategy, then jump into practice exams to apply what you learned immediately.', 'examhub' ); ?></p>
            <a href="<?php echo esc_url( get_post_type_archive_link( 'eh_exam' ) ); ?>" class="btn btn-primary">
              <?php esc_html_e( 'Explore exams', 'examhub' ); ?>
            </a>
          </section>
        </div>
      </aside>
    </div>
  </div>
</section>
<?php
get_footer();

<?php
/**
 * Archive template.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

$is_blog_archive = is_category() || is_tag() || is_author() || is_date();

if ( ! $is_blog_archive ) {
    get_header();
    ?>
    <div class="container-xl py-4">
      <?php if ( have_posts() ) : ?>
        <?php while ( have_posts() ) : the_post(); ?>
          <?php get_template_part( 'template-parts/content', get_post_type() ); ?>
        <?php endwhile; ?>
        <?php the_posts_navigation(); ?>
      <?php else : ?>
        <?php get_template_part( 'template-parts/content', 'none' ); ?>
      <?php endif; ?>
    </div>
    <?php
    get_footer();
    return;
}

get_header();

$archive_title       = get_the_archive_title();
$archive_description = wp_strip_all_tags( get_the_archive_description() );
$recent_posts        = new WP_Query(
    [
        'post_type'           => 'post',
        'posts_per_page'      => 4,
        'post_status'         => 'publish',
        'ignore_sticky_posts' => true,
    ]
);

if ( ! $archive_description ) {
    $archive_description = __( 'مجموعة مركزة من المقالات تساعدك على الوصول إلى الأفكار والأساليب والنصائح المناسبة لهذا الموضوع.', 'examhub' );
}
?>
<section class="eh-blog-shell eh-blog-archive py-4 py-lg-5">
  <div class="container-xl">
    <header class="eh-blog-hero mb-4 mb-lg-5">
      <div class="row g-4 align-items-end">
        <div class="col-lg-8">
          <span class="eh-blog-kicker"><?php esc_html_e( 'الأرشيف', 'examhub' ); ?></span>
          <h1 class="eh-blog-hero-title"><?php echo esc_html( $archive_title ); ?></h1>
          <p class="eh-blog-hero-text mb-0"><?php echo esc_html( $archive_description ); ?></p>
        </div>
        <div class="col-lg-4">
          <div class="eh-blog-hero-panel">
            <div class="eh-blog-hero-stat">
              <strong><?php echo esc_html( (string) $wp_query->found_posts ); ?></strong>
              <span><?php esc_html_e( 'مقالة مطابقة', 'examhub' ); ?></span>
            </div>
            <div class="eh-blog-hero-stat">
              <strong><?php echo esc_html( get_post_type_object( 'post' )->labels->name ); ?></strong>
              <span><?php esc_html_e( 'نوع المحتوى', 'examhub' ); ?></span>
            </div>
          </div>
        </div>
      </div>
    </header>

    <div class="row g-4 g-xl-5">
      <div class="col-xl-8">
        <?php if ( have_posts() ) : ?>
          <div class="eh-blog-feed eh-blog-grid">
            <?php while ( have_posts() ) : the_post(); ?>
              <?php $category = get_the_category(); ?>
              <article id="post-<?php the_ID(); ?>" <?php post_class( 'eh-blog-card' ); ?>>
                <a class="eh-blog-card-media" href="<?php the_permalink(); ?>" aria-label="<?php the_title_attribute(); ?>">
                  <?php if ( has_post_thumbnail() ) : ?>
                    <?php the_post_thumbnail( 'medium_large', [ 'loading' => 'lazy' ] ); ?>
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
                  </div>
                  <h2 class="eh-blog-card-title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                  </h2>
                  <p class="eh-blog-card-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
                  <div class="eh-blog-card-footer">
                    <div class="eh-blog-author-inline">
                      <span class="eh-blog-author-avatar"><?php echo get_avatar( get_the_author_meta( 'ID' ), 44 ); ?></span>
                      <div>
                        <strong><?php the_author(); ?></strong>
                        <span><?php esc_html_e( 'الكاتب', 'examhub' ); ?></span>
                      </div>
                    </div>
                    <a class="eh-blog-readmore" href="<?php the_permalink(); ?>">
                      <?php esc_html_e( 'اقرأ المقال', 'examhub' ); ?>
                      <i class="bi bi-arrow-left-short"></i>
                    </a>
                  </div>
                </div>
              </article>
            <?php endwhile; ?>
          </div>

          <nav class="eh-blog-pagination mt-4 mt-lg-5" aria-label="<?php esc_attr_e( 'التنقل بين الأرشيف', 'examhub' ); ?>">
            <?php
            the_posts_pagination(
                [
                    'mid_size'           => 1,
                    'prev_text'          => '<i class="bi bi-arrow-right"></i>',
                    'next_text'          => '<i class="bi bi-arrow-left"></i>',
                    'screen_reader_text' => __( 'التنقل بين الأرشيف', 'examhub' ),
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
            <h2><?php esc_html_e( 'ابحث في المدونة', 'examhub' ); ?></h2>
            <form role="search" method="get" class="eh-blog-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
              <label class="screen-reader-text" for="eh-archive-search"><?php esc_html_e( 'ابحث عن:', 'examhub' ); ?></label>
              <input id="eh-archive-search" type="search" class="form-control" placeholder="<?php esc_attr_e( 'ابحث عن المقالات...', 'examhub' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s">
              <input type="hidden" name="post_type" value="post">
              <button class="btn btn-primary" type="submit"><?php esc_html_e( 'بحث', 'examhub' ); ?></button>
            </form>
          </section>

          <section class="eh-blog-sidebar-card">
            <div class="eh-blog-sidebar-heading">
              <h2><?php esc_html_e( 'أحدث المقالات', 'examhub' ); ?></h2>
              <span><?php esc_html_e( 'واصل القراءة', 'examhub' ); ?></span>
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

          <section class="eh-blog-sidebar-card">
            <div class="eh-blog-sidebar-heading">
              <h2><?php esc_html_e( 'التصنيفات', 'examhub' ); ?></h2>
              <span><?php esc_html_e( 'اكتشف المزيد من المواضيع', 'examhub' ); ?></span>
            </div>
            <div class="eh-blog-topic-cloud">
              <?php foreach ( get_categories( [ 'orderby' => 'name', 'order' => 'ASC', 'number' => 10 ] ) as $term ) : ?>
                <a href="<?php echo esc_url( get_category_link( $term->term_id ) ); ?>" class="eh-blog-topic">
                  <span><?php echo esc_html( $term->name ); ?></span>
                  <strong><?php echo esc_html( (string) $term->count ); ?></strong>
                </a>
              <?php endforeach; ?>
            </div>
          </section>
        </div>
      </aside>
    </div>
  </div>
</section>
<?php
get_footer();

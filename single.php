<?php
/**
 * Single post template.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
    the_post();

    $post_id         = get_the_ID();
    $categories      = get_the_category();
    $tags            = get_the_tags();
    $author_id       = (int) get_the_author_meta( 'ID' );
    $author_url      = get_author_posts_url( $author_id );
    $reading_minutes = max( 1, (int) ceil( str_word_count( wp_strip_all_tags( get_the_content() ) ) / 200 ) );
    $posts_page_id   = (int) get_option( 'page_for_posts' );
    $blog_url        = $posts_page_id ? get_permalink( $posts_page_id ) : home_url( '/' );
    $permalink       = get_permalink();
    $encoded_url     = rawurlencode( $permalink );
    $encoded_title   = rawurlencode( get_the_title() );
    $content_data    = examhub_prepare_article_toc( apply_filters( 'the_content', get_the_content() ) );
    $article_content = $content_data['content'];
    $toc_items       = $content_data['items'];
    $share_links     = [
        [
            'label' => __( 'فيسبوك', 'examhub' ),
            'icon'  => 'bi-facebook',
            'url'   => 'https://www.facebook.com/sharer/sharer.php?u=' . $encoded_url,
        ],
        [
            'label' => __( 'X', 'examhub' ),
            'icon'  => 'bi-twitter-x',
            'url'   => 'https://twitter.com/intent/tweet?url=' . $encoded_url . '&text=' . $encoded_title,
        ],
        [
            'label' => __( 'واتساب', 'examhub' ),
            'icon'  => 'bi-whatsapp',
            'url'   => examhub_get_whatsapp_url( '', get_the_title() . ' ' . $permalink ),
        ],
        [
            'label' => __( 'تيليجرام', 'examhub' ),
            'icon'  => 'bi-telegram',
            'url'   => 'https://t.me/share/url?url=' . $encoded_url . '&text=' . $encoded_title,
        ],
    ];

    $related_post_args = [
        'post_type'           => 'post',
        'posts_per_page'      => 3,
        'post__not_in'        => [ $post_id ],
        'ignore_sticky_posts' => true,
    ];

    if ( ! empty( $categories ) ) {
        $related_post_args['category__in'] = wp_list_pluck( $categories, 'term_id' );
    }

    $related_posts = new WP_Query( $related_post_args );
    ?>
  <section class="eh-blog-shell eh-blog-single py-4 py-lg-5">
    <div class="container-xl">
      <nav class="eh-blog-breadcrumb mb-3" aria-label="<?php esc_attr_e( 'مسار التنقل', 'examhub' ); ?>">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'الرئيسية', 'examhub' ); ?></a>
        <span>/</span>
        <a href="<?php echo esc_url( $blog_url ); ?>"><?php esc_html_e( 'المدونة', 'examhub' ); ?></a>
        <span>/</span>
        <span><?php the_title(); ?></span>
      </nav>

      <article id="post-<?php the_ID(); ?>" <?php post_class( 'eh-article' ); ?>>
        <header class="eh-article-hero mb-4 mb-lg-5">
          <div class="row g-4 align-items-end">
            <div class="col-lg-8">
              <div class="eh-blog-card-meta mb-3">
                <?php if ( ! empty( $categories ) ) : ?>
                  <?php foreach ( $categories as $category ) : ?>
                    <a class="eh-blog-pill" href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>">
                      <?php echo esc_html( $category->name ); ?>
                    </a>
                  <?php endforeach; ?>
                <?php endif; ?>
                <span><i class="bi bi-calendar3"></i><?php echo esc_html( get_the_date() ); ?></span>
                <span><i class="bi bi-clock-history"></i><?php echo esc_html( sprintf( _n( '%s دقيقة قراءة', '%s دقائق قراءة', $reading_minutes, 'examhub' ), $reading_minutes ) ); ?></span>
              </div>

              <h1 class="eh-article-title"><?php the_title(); ?></h1>

              <?php if ( has_excerpt() ) : ?>
                <p class="eh-article-lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
              <?php endif; ?>

              <div class="eh-article-author-row">
                <a class="eh-article-author" href="<?php echo esc_url( $author_url ); ?>">
                  <span class="eh-blog-author-avatar"><?php echo get_avatar( $author_id, 56 ); ?></span>
                  <span>
                    <strong><?php the_author(); ?></strong>
                    <small><?php esc_html_e( 'نشر بواسطة', 'examhub' ); ?></small>
                  </span>
                </a>
                <div class="eh-article-actions">
                  <?php foreach ( $share_links as $share_link ) : ?>
                    <a class="eh-share-link" target="_blank" rel="noopener" href="<?php echo esc_url( $share_link['url'] ); ?>">
                      <i class="bi <?php echo esc_attr( $share_link['icon'] ); ?>"></i>
                      <span><?php echo esc_html( $share_link['label'] ); ?></span>
                    </a>
                  <?php endforeach; ?>
                  <button type="button" class="eh-share-link" data-share-copy="<?php echo esc_url( $permalink ); ?>">
                    <i class="bi bi-link-45deg"></i>
                    <span><?php esc_html_e( 'نسخ الرابط', 'examhub' ); ?></span>
                  </button>
                </div>
              </div>
            </div>

            <div class="col-lg-4">
              <div class="eh-article-highlight">
                <strong><?php esc_html_e( 'لماذا هذا المقال مهم؟', 'examhub' ); ?></strong>
                <p class="mb-0"><?php esc_html_e( 'مصمم ليمنحك فهمًا سريعًا، وفوائد عملية، وقرارات أفضل قبل جلستك القادمة في التدريب أو الامتحان.', 'examhub' ); ?></p>
              </div>
            </div>
          </div>

          <?php if ( has_post_thumbnail() ) : ?>
            <div class="eh-article-featured-image mt-4">
              <?php the_post_thumbnail( 'large', [ 'loading' => 'eager' ] ); ?>
            </div>
          <?php endif; ?>
        </header>

        <div class="row g-4 g-xl-5">
          <div class="col-xl-8">
            <div class="eh-article-body">
              <div class="entry-content">
                <?php
                echo $article_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

                wp_link_pages(
                    [
                        'before' => '<div class="eh-article-pages">',
                        'after'  => '</div>',
                    ]
                );
                ?>
              </div>

              <?php if ( ! empty( $tags ) ) : ?>
                <footer class="eh-article-tags">
                  <?php foreach ( $tags as $tag ) : ?>
                    <a href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>" class="eh-blog-pill">
                      #<?php echo esc_html( $tag->name ); ?>
                    </a>
                  <?php endforeach; ?>
                </footer>
              <?php endif; ?>
            </div>

            <?php if ( $related_posts->have_posts() ) : ?>
              <section class="eh-related-posts mt-4 mt-lg-5">
                <div class="eh-blog-section-heading">
                  <div>
                    <span class="eh-blog-kicker"><?php esc_html_e( 'واصل القراءة', 'examhub' ); ?></span>
                    <h2><?php esc_html_e( 'مقالات ذات صلة', 'examhub' ); ?></h2>
                  </div>
                </div>
                <div class="eh-blog-grid">
                  <?php while ( $related_posts->have_posts() ) : $related_posts->the_post(); ?>
                    <?php $related_categories = get_the_category(); ?>
                    <article id="post-<?php the_ID(); ?>" <?php post_class( 'eh-blog-card' ); ?>>
                      <a class="eh-blog-card-media" href="<?php the_permalink(); ?>">
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
                          <?php if ( ! empty( $related_categories ) ) : ?>
                            <a class="eh-blog-pill" href="<?php echo esc_url( get_category_link( $related_categories[0]->term_id ) ); ?>">
                              <?php echo esc_html( $related_categories[0]->name ); ?>
                            </a>
                          <?php endif; ?>
                          <span><i class="bi bi-calendar3"></i><?php echo esc_html( get_the_date() ); ?></span>
                        </div>
                        <h3 class="eh-blog-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                        <p class="eh-blog-card-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
                      </div>
                    </article>
                  <?php endwhile; ?>
                  <?php wp_reset_postdata(); ?>
                </div>
              </section>
            <?php endif; ?>
          </div>

          <aside class="col-xl-4">
            <div class="eh-blog-sidebar eh-blog-sidebar-sticky">
              <?php if ( ! empty( $toc_items ) ) : ?>
                <section class="eh-blog-sidebar-card eh-article-toc-card">
                  <button class="eh-article-toc-toggle" type="button" aria-expanded="true" aria-controls="eh-article-toc">
                    <span>
                      <i class="bi bi-list-ul"></i>
                      <?php esc_html_e( 'جدول المحتويات', 'examhub' ); ?>
                    </span>
                    <i class="bi bi-chevron-up"></i>
                  </button>
                  <nav id="eh-article-toc" class="eh-article-toc" aria-label="<?php esc_attr_e( 'جدول محتويات المقال', 'examhub' ); ?>">
                    <?php foreach ( $toc_items as $toc_item ) : ?>
                      <a class="eh-article-toc-link eh-article-toc-level-<?php echo esc_attr( $toc_item['level'] ); ?>" href="#<?php echo esc_attr( $toc_item['id'] ); ?>">
                        <?php echo esc_html( $toc_item['title'] ); ?>
                      </a>
                    <?php endforeach; ?>
                  </nav>
                </section>
              <?php endif; ?>

              <section class="eh-blog-sidebar-card eh-article-share-card">
                <div class="eh-blog-sidebar-heading">
                  <h2><?php esc_html_e( 'شارك المقال', 'examhub' ); ?></h2>
                  <span><?php esc_html_e( 'انشر الفائدة', 'examhub' ); ?></span>
                </div>
                <div class="eh-article-share-grid">
                  <?php foreach ( $share_links as $share_link ) : ?>
                    <a class="eh-share-link" target="_blank" rel="noopener" href="<?php echo esc_url( $share_link['url'] ); ?>">
                      <i class="bi <?php echo esc_attr( $share_link['icon'] ); ?>"></i>
                      <span><?php echo esc_html( $share_link['label'] ); ?></span>
                    </a>
                  <?php endforeach; ?>
                  <button type="button" class="eh-share-link" data-share-copy="<?php echo esc_url( $permalink ); ?>">
                    <i class="bi bi-link-45deg"></i>
                    <span><?php esc_html_e( 'نسخ الرابط', 'examhub' ); ?></span>
                  </button>
                </div>
              </section>

              <section class="eh-blog-sidebar-card">
                <div class="eh-blog-sidebar-heading">
                  <h2><?php esc_html_e( 'عن الكاتب', 'examhub' ); ?></h2>
                  <span><?php esc_html_e( 'تعرّف على الكاتب', 'examhub' ); ?></span>
                </div>
                <a class="eh-article-author-card" href="<?php echo esc_url( $author_url ); ?>">
                  <span class="eh-blog-author-avatar"><?php echo get_avatar( $author_id, 72 ); ?></span>
                  <div>
                    <strong><?php the_author(); ?></strong>
                    <p><?php echo esc_html( wp_trim_words( get_the_author_meta( 'description', $author_id ), 24, '...' ) ?: __( 'يشارك أفكارًا مفيدة لتحسين التعلّم، وزيادة الثقة في الامتحانات، وتحقيق تقدم دراسي مستمر.', 'examhub' ) ); ?></p>
                  </div>
                </a>
              </section>

              <section class="eh-blog-sidebar-card">
                <div class="eh-blog-sidebar-heading">
                  <h2><?php esc_html_e( 'ملخص سريع للمقال', 'examhub' ); ?></h2>
                  <span><?php esc_html_e( 'معلومات سريعة', 'examhub' ); ?></span>
                </div>
                <div class="eh-article-facts">
                  <div>
                    <span><?php esc_html_e( 'تاريخ النشر', 'examhub' ); ?></span>
                    <strong><?php echo esc_html( get_the_date() ); ?></strong>
                  </div>
                  <div>
                    <span><?php esc_html_e( 'آخر تحديث', 'examhub' ); ?></span>
                    <strong><?php echo esc_html( get_the_modified_date() ); ?></strong>
                  </div>
                  <div>
                    <span><?php esc_html_e( 'مدة القراءة', 'examhub' ); ?></span>
                    <strong><?php echo esc_html( sprintf( _n( '%s دقيقة', '%s دقائق', $reading_minutes, 'examhub' ), $reading_minutes ) ); ?></strong>
                  </div>
                </div>
              </section>

              <section class="eh-blog-sidebar-card eh-blog-cta">
                <span class="eh-blog-kicker"><?php esc_html_e( 'الخطوة التالية', 'examhub' ); ?></span>
                <h2><?php esc_html_e( 'حوّل الفكرة إلى تطبيق', 'examhub' ); ?></h2>
                <p><?php esc_html_e( 'بعد القراءة، انتقل إلى التدريب المستهدف لتثبيت الفكرة وهي ما زالت حاضرة في ذهنك.', 'examhub' ); ?></p>
                <a href="<?php echo esc_url( get_post_type_archive_link( 'eh_exam' ) ); ?>" class="btn btn-primary">
                  <?php esc_html_e( 'ابدأ التدريب', 'examhub' ); ?>
                </a>
              </section>
            </div>
          </aside>
        </div>
      </article>
    </div>
  </section>
<?php endwhile; ?>
<?php wp_reset_postdata(); ?>
<?php
get_footer();


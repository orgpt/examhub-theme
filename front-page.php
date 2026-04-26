<?php
/**
 * ExamHub - Front Page Landing Template
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;
get_header();

$plans   = examhub_get_all_plans();
$user_id = get_current_user_id();
$sub     = $user_id ? examhub_get_user_subscription_status( $user_id ) : [ 'state' => 'free', 'plan_id' => null ];
$blog_page    = get_page_by_path( 'blog' );
$blog_url     = $blog_page ? get_permalink( $blog_page ) : home_url( '/blog' );
$latest_exams = new WP_Query(
  [
    'post_type'              => 'eh_exam',
    'post_status'            => 'publish',
    'posts_per_page'         => 4,
    'orderby'                => 'rand',
    'ignore_sticky_posts'    => true,
    'no_found_rows'          => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => false,
  ]
);
$latest_posts = new WP_Query(
  [
    'post_type'              => 'post',
    'post_status'            => 'publish',
    'posts_per_page'         => 3,
    'ignore_sticky_posts'    => true,
    'no_found_rows'          => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => false,
  ]
);

usort( $plans, fn($a, $b) => (int)($a['plan_priority'] ?? 0) - (int)($b['plan_priority'] ?? 0) );
?>

<div class="container-xl py-4 eh-landing-page">

  <section class="eh-landing-hero">
    <div class="eh-landing-glow"></div>
    <span class="eh-landing-chip">منصة المراجعة النهائية</span>
    <h1>ذاكر بذكاء، وابدأ الامتحان بثقة</h1>
    <p>
      أكبر منصة تدريب امتحانات تفاعلية للمنهج المصري مع تصحيح فوري، شرح تفصيلي، وتتبع مستواك خطوة بخطوة.
    </p>
    <div class="eh-landing-cta">
      <a href="<?php echo esc_url( get_post_type_archive_link( 'eh_exam' ) ); ?>" class="btn btn-primary btn-lg">
        ابدأ التدريب الآن
      </a>
      <a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="btn btn-ghost btn-lg">
        شاهد خطط الاشتراك
      </a>
    </div>
    <div class="eh-landing-stats">
      <div class="eh-landing-stat">
        <strong><?php echo number_format_i18n( (int) wp_count_posts( 'eh_exam' )->publish ); ?>+</strong>
        <span>امتحان تدريبي</span>
      </div>
      <div class="eh-landing-stat">
        <strong><?php echo number_format_i18n( (int) wp_count_posts( 'eh_question' )->publish ); ?>+</strong>
        <span>امتحان متنوع</span>
      </div>
      <div class="eh-landing-stat">
        <strong>24/7</strong>
        <span>متاح في أي وقت</span>
      </div>
    </div>
  </section>

  <section class="eh-landing-features mt-5">
    <h2 class="eh-landing-section-title">لماذا المراجعة النهائية؟</h2>
    <div class="row g-3">
      <div class="col-md-6 col-lg-3">
        <div class="eh-landing-feature-card">
          <i class="bi bi-lightning-charge-fill"></i>
          <h3>تصحيح فوري</h3>
          <p>اعرف نتيجتك مباشرة بعد كل امتحان مع تحليل واضح لنقاط القوة والضعف.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="eh-landing-feature-card">
          <i class="bi bi-robot"></i>
          <h3>مساعد ذكي</h3>
          <p>شرح مبسّط لأصعب الامتحانات وتوجيهك لأفضل طريقة حل في وقت أقل.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="eh-landing-feature-card">
          <i class="bi bi-graph-up-arrow"></i>
          <h3>تتبع تقدّمك</h3>
          <p>لوحة أداء متكاملة تساعدك تعرف مستواك الحقيقي وتتابع تطورك يوميًا.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="eh-landing-feature-card">
          <i class="bi bi-trophy-fill"></i>
          <h3>تحديات وترتيب</h3>
          <p>تحديات يومية ونظام نقاط يحفزك تحافظ على الاستمرار وتتصدر.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="eh-landing-pricing mt-5">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <h2 class="eh-landing-section-title mb-0">لفترة محدودة للثانوية العامة</h2>
      <a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="btn btn-sm btn-ghost">مقارنة كل الخطط</a>
    </div>
    <div class="eh-offer-countdown mb-4" data-countdown-duration="7200" aria-label="عرض لفترة محدودة ينتهي خلال ساعتين">
      <div class="eh-offer-countdown-copy">
        <strong>خصم الثانوية العامة ينتهي خلال</strong>
      </div>
      <div class="eh-offer-countdown-timer">
        <div class="eh-countdown-unit">
          <strong data-countdown-hours>02</strong>
          <span>ساعة</span>
        </div>
        <div class="eh-countdown-separator">:</div>
        <div class="eh-countdown-unit">
          <strong data-countdown-minutes>00</strong>
          <span>دقيقة</span>
        </div>
        <div class="eh-countdown-separator">:</div>
        <div class="eh-countdown-unit">
          <strong data-countdown-seconds>00</strong>
          <span>ثانية</span>
        </div>
      </div>
    </div>

    <div class="row g-4 justify-content-center">
      <div class="col-lg-3 col-md-6">
        <div class="eh-plan-card">
          <div class="plan-name">مجاني</div>
          <div class="plan-price">0 <span>دائمًا</span></div>
          <ul class="plan-features">
            <li>امتحان واحد يوميا</li>
            <li class="unavailable">الذكاء الاصطناعي</li>
            <li class="unavailable">الشرح التفصيلي</li>
          </ul>
          <?php if ( ! $user_id ) : ?>
            <a href="<?php echo esc_url( wp_registration_url() ); ?>" class="btn btn-outline-primary w-100">إنشاء حساب</a>
          <?php else : ?>
            <a href="<?php echo esc_url( get_post_type_archive_link( 'eh_exam' ) ); ?>" class="btn btn-ghost w-100">ابدأ المراجعة</a>
          <?php endif; ?>
        </div>
      </div>

      <?php foreach ( array_slice( $plans, 0, 3 ) as $plan ) :
        $is_current  = $user_id && $sub['plan_id'] === $plan['plan_slug'];
        $is_featured = ! empty( $plan['plan_featured'] );
        $offer       = examhub_get_plan_offer_prices( $plan );
        $duration    = (int) ( $plan['plan_duration_days'] ?? 30 );
      ?>
      <div class="col-lg-3 col-md-6">
        <div class="eh-plan-card <?php echo $is_featured ? 'featured' : ''; ?>">
          <?php if ( $is_featured ) : ?>
            <div class="plan-badge">الأشهر</div>
          <?php endif; ?>
          <div class="plan-name mt-3"><?php echo esc_html( $plan['plan_name_ar'] ?: $plan['plan_name'] ); ?></div>
          <?php if ( $offer['discount'] ) : ?>
            <div class="plan-discount-badge">خصم <?php echo esc_html( $offer['discount'] ); ?>%</div>
          <?php endif; ?>
          <?php if ( $offer['regular'] > $offer['current'] ) : ?>
            <div class="plan-old-price">بدلًا من <del><?php echo number_format_i18n( $offer['regular'] ); ?> جنيه</del></div>
          <?php endif; ?>
          <div class="plan-price mt-2">
            <?php echo number_format_i18n( $offer['current'] ); ?>
            <span>جنيه<?php if ( $duration ) echo ' / ' . $duration . ' يوم'; ?></span>
          </div>
          <ul class="plan-features">
            <?php $plan_limit = (int) ( $plan['plan_exams_limit'] ?? $plan['plan_questions_limit'] ?? 0 ); ?>
            <li><?php echo ! empty( $plan['plan_unlimited'] ) ? 'امتحانات غير محدودة' : ( $plan_limit . ' امتحان/يوم' ); ?></li>
            <li class="<?php echo empty( $plan['plan_explanation_access'] ) ? 'unavailable' : ''; ?>">الشرح التفصيلي</li>
            <li class="<?php echo empty( $plan['plan_ai_access'] ) ? 'unavailable' : ''; ?>">الذكاء الاصطناعي</li>
            <li class="<?php echo empty( $plan['plan_download_access'] ) ? 'unavailable' : ''; ?>">تحميل PDF</li>
          </ul>

          <?php if ( $is_current ) : ?>
            <button class="btn btn-ghost w-100" disabled>خطتك الحالية</button>
          <?php else : ?>
            <a href="<?php echo esc_url( home_url( '/checkout?plan=' . esc_attr( $plan['plan_slug'] ) ) ); ?>" class="btn btn-primary w-100">
              اشترك دلوقتي وابدأ فورًا
            </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ( $latest_exams->have_posts() ) : ?>
  <section class="eh-landing-exams mt-5">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <span class="eh-blog-kicker mb-2">أحدث الإضافات</span>
        <h2 class="eh-landing-section-title mb-0">أحدث الامتحانات</h2>
      </div>
      <a href="<?php echo esc_url( get_post_type_archive_link( 'eh_exam' ) ); ?>" class="btn btn-sm btn-ghost">عرض كل الامتحانات</a>
    </div>

    <div class="row g-3">
      <?php while ( $latest_exams->have_posts() ) : $latest_exams->the_post(); ?>
        <?php
        $exam_id       = get_the_ID();
        $grade_id      = (int) get_field( 'exam_grade', $exam_id );
        $subject_id    = (int) get_field( 'exam_subject', $exam_id );
        $duration      = (int) get_field( 'exam_duration_minutes', $exam_id );
        $difficulty    = get_field( 'exam_difficulty', $exam_id ) ?: 'mixed';
        $access_level  = get_field( 'exam_access', $exam_id ) ?: 'free_limit';
        $timer_type    = get_field( 'timer_type', $exam_id );
        $q_count       = examhub_get_exam_question_count( $exam_id );
        $xp_reward     = (int) get_field( 'exam_xp_reward', $exam_id );
        $grade_name    = $grade_id ? ( get_field( 'grade_name_ar', $grade_id ) ?: get_the_title( $grade_id ) ) : '';
        $subject_name  = $subject_id ? ( get_field( 'subject_name_ar', $subject_id ) ?: get_the_title( $subject_id ) ) : '';
        $subject_color = $subject_id ? ( get_field( 'subject_color', $subject_id ) ?: '#4361ee' ) : '#4361ee';
        $exam_sub      = $user_id ? examhub_get_user_subscription_status( $user_id ) : null;
        $is_locked     = false;

        if ( $access_level === 'subscribed' && ( ! $exam_sub || ! in_array( $exam_sub['state'], [ 'active', 'trial', 'lifetime' ], true ) ) ) {
          $is_locked = true;
        } elseif ( $access_level === 'free_limit' && $user_id && examhub_get_remaining_questions( $user_id ) <= 0 ) {
          $is_locked = true;
        }

        $has_taken   = $user_id ? examhub_user_has_taken_exam( $exam_id, $user_id ) : false;
        $best_result = $has_taken ? examhub_get_best_result( $exam_id, $user_id ) : null;
        $best_pct    = $best_result ? (float) get_field( 'percentage', $best_result ) : null;
        ?>
        <div class="col-sm-6 col-xl-3">
          <article class="eh-exam-card <?php echo $is_locked ? 'locked' : ''; ?> <?php echo $has_taken ? 'completed' : ''; ?>" data-exam-id="<?php echo esc_attr( $exam_id ); ?>">
            <div class="card-thumb" style="--subject-color: <?php echo esc_attr( $subject_color ); ?>">
              <?php if ( has_post_thumbnail() ) : ?>
                <img src="<?php the_post_thumbnail_url( 'exam-thumbnail' ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
              <?php else : ?>
                <div style="background: linear-gradient(135deg, <?php echo esc_attr( $subject_color ); ?>22, <?php echo esc_attr( $subject_color ); ?>11); height:100%; display:flex; align-items:center; justify-content:center;">
                  <i class="bi bi-clipboard-check" style="font-size:2.5rem; color:<?php echo esc_attr( $subject_color ); ?>; opacity:0.5;"></i>
                </div>
              <?php endif; ?>

              <?php if ( $is_locked ) : ?>
                <i class="bi bi-lock-fill lock-icon"></i>
              <?php endif; ?>

              <span class="badge badge-<?php echo esc_attr( $difficulty ); ?>" style="position:absolute; top:10px; right:10px;">
                <?php echo esc_html( examhub_difficulty_label( $difficulty ) ); ?>
              </span>

              <?php if ( $has_taken && $best_pct !== null ) : ?>
                <span class="badge" style="position:absolute; top:10px; left:10px; background:var(--eh-bg-secondary); color:var(--eh-text-primary);">
                  <i class="bi bi-trophy-fill" style="color:var(--eh-gold);"></i>
                  <?php echo number_format( $best_pct, 0 ); ?>%
                </span>
              <?php endif; ?>

              <?php if ( $has_taken ) : ?>
                <span class="badge eh-exam-completed-badge" style="position:absolute; bottom:10px; left:10px;">
                  <i class="bi bi-check2-circle me-1"></i>
                  تم الحل
                </span>
              <?php endif; ?>
            </div>

            <div class="card-body">
              <?php if ( $subject_name ) : ?>
                <div class="mb-2">
                  <span class="badge" style="background: <?php echo esc_attr( $subject_color ); ?>20; color: <?php echo esc_attr( $subject_color ); ?>; border: 1px solid <?php echo esc_attr( $subject_color ); ?>40;">
                    <?php echo esc_html( $subject_name ); ?>
                  </span>
                  <?php if ( $grade_name ) : ?>
                    <span class="badge badge-accent ms-1"><?php echo esc_html( $grade_name ); ?></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <h3 class="exam-title">
                <a href="<?php the_permalink(); ?>" class="<?php echo $has_taken ? 'text-muted' : 'text-light'; ?>">
                  <?php the_title(); ?>
                </a>
              </h3>

              <div class="exam-meta">
                <?php if ( $q_count ) : ?>
                  <span class="exam-meta-item">
                    <i class="bi bi-question-circle"></i>
                    <?php printf( esc_html__( '%d سؤال', 'examhub' ), $q_count ); ?>
                  </span>
                <?php endif; ?>

                <?php if ( $duration && $timer_type === 'exam' ) : ?>
                  <span class="exam-meta-item">
                    <i class="bi bi-clock"></i>
                    <?php echo esc_html( examhub_format_duration( $duration ) ); ?>
                  </span>
                <?php endif; ?>

                <?php if ( $xp_reward ) : ?>
                  <span class="exam-meta-item" style="color:var(--eh-accent);">
                    <i class="bi bi-lightning-fill"></i>
                    <?php echo (int) $xp_reward; ?> XP
                  </span>
                <?php endif; ?>
              </div>

              <div class="card-footer-action">
                <?php if ( $is_locked ) : ?>
                  <a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="btn btn-sm btn-outline-primary w-100" data-exam-id="<?php echo esc_attr( $exam_id ); ?>">
                    <i class="bi bi-lock me-1"></i>
                    اشترك للوصول
                  </a>
                <?php elseif ( $has_taken ) : ?>
                  <a href="<?php the_permalink(); ?>" class="btn btn-sm btn-secondary w-100">
                    <i class="bi bi-arrow-repeat me-1"></i>
                    إعادة الامتحان
                  </a>
                <?php else : ?>
                  <a href="<?php the_permalink(); ?>" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-play-fill me-1"></i>
                    ابدأ الامتحان
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </article>
        </div>
      <?php endwhile; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php wp_reset_postdata(); ?>

  <?php if ( $latest_posts->have_posts() ) : ?>
  <section class="eh-landing-news mt-5">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <span class="eh-blog-kicker mb-2">آخر الأخبار</span>
        <h2 class="eh-landing-section-title mb-0">أحدث المقالات من المدونة</h2>
      </div>
      <a href="<?php echo esc_url( $blog_url ); ?>" class="btn btn-sm btn-ghost">عرض كل المقالات</a>
    </div>

    <div class="row g-4">
      <?php while ( $latest_posts->have_posts() ) : $latest_posts->the_post(); ?>
        <?php $category = get_the_category(); ?>
        <div class="col-md-6 col-xl-4">
          <article <?php post_class( 'eh-blog-card eh-landing-news-card' ); ?>>
            <a class="eh-blog-card-media" href="<?php the_permalink(); ?>" aria-label="<?php the_title_attribute(); ?>">
              <?php if ( has_post_thumbnail() ) : ?>
                <?php the_post_thumbnail( 'medium_large', [ 'loading' => 'lazy' ] ); ?>
              <?php else : ?>
                <span class="eh-blog-card-placeholder">
                  <i class="bi bi-newspaper"></i>
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

              <h3 class="eh-blog-card-title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
              </h3>

              <p class="eh-blog-card-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>

              <div class="eh-blog-card-footer">
                <a class="eh-blog-readmore" href="<?php the_permalink(); ?>">
                  اقرأ المزيد
                  <i class="bi bi-arrow-left-short"></i>
                </a>
              </div>
            </div>
          </article>
        </div>
      <?php endwhile; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="eh-landing-final-cta mt-5">
    <h2>جاهز تبدأ التفوق؟</h2>
    <p>ابدأ الآن واختبر نفسك في آلاف الامتحانات المصممة على المنهج المصري.</p>
    <div class="eh-landing-cta">
      <a href="<?php echo esc_url( get_post_type_archive_link( 'eh_exam' ) ); ?>" class="btn btn-primary btn-lg">ابدأ المراجعة الآن</a>
      <a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="btn btn-outline-primary btn-lg">اختر خطتك</a>
    </div>
  </section>

</div>

<?php wp_reset_postdata(); ?>
<?php get_footer(); ?>

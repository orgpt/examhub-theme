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
        <span>سؤال متنوع</span>
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
          <p>شرح مبسّط للأسئلة الصعبة وتوجيهك لأفضل طريقة حل في وقت أقل.</p>
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
      <h2 class="eh-landing-section-title mb-0">خطط الأسعار</h2>
      <a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="btn btn-sm btn-ghost">مقارنة كل الخطط</a>
    </div>

    <div class="row g-4 justify-content-center">
      <div class="col-lg-3 col-md-6">
        <div class="eh-plan-card">
          <div class="plan-name">مجاني</div>
          <div class="plan-price">0 <span>دائمًا</span></div>
          <ul class="plan-features">
            <li><?php echo (int) ( get_field( 'free_questions_per_day', 'option' ) ?: 10 ); ?> سؤال يوميًا</li>
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
        $price       = (float) ( $plan['plan_price'] ?? 0 );
        $duration    = (int) ( $plan['plan_duration_days'] ?? 30 );
      ?>
      <div class="col-lg-3 col-md-6">
        <div class="eh-plan-card <?php echo $is_featured ? 'featured' : ''; ?>">
          <?php if ( $is_featured ) : ?>
            <div class="plan-badge">الأشهر</div>
          <?php endif; ?>
          <div class="plan-name mt-3"><?php echo esc_html( $plan['plan_name_ar'] ?: $plan['plan_name'] ); ?></div>
          <div class="plan-price mt-2">
            <?php echo number_format_i18n( $price ); ?>
            <span>جنيه<?php if ( $duration ) echo ' / ' . $duration . ' يوم'; ?></span>
          </div>
          <ul class="plan-features">
            <li><?php echo ! empty( $plan['plan_unlimited'] ) ? 'أسئلة غير محدودة' : ( (int) ( $plan['plan_questions_limit'] ?: 100 ) . ' سؤال يوميًا' ); ?></li>
            <li class="<?php echo empty( $plan['plan_explanation_access'] ) ? 'unavailable' : ''; ?>">الشرح التفصيلي</li>
            <li class="<?php echo empty( $plan['plan_ai_access'] ) ? 'unavailable' : ''; ?>">الذكاء الاصطناعي</li>
            <li class="<?php echo empty( $plan['plan_download_access'] ) ? 'unavailable' : ''; ?>">تحميل PDF</li>
          </ul>

          <?php if ( $is_current ) : ?>
            <button class="btn btn-ghost w-100" disabled>خطتك الحالية</button>
          <?php else : ?>
            <a href="<?php echo esc_url( home_url( '/checkout?plan=' . esc_attr( $plan['plan_slug'] ) ) ); ?>" class="btn btn-primary w-100">
              اشترك الآن
            </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="eh-landing-final-cta mt-5">
    <h2>جاهز تبدأ التفوق؟</h2>
    <p>ابدأ الآن واختبر نفسك في آلاف الأسئلة والامتحانات المصممة على المنهج المصري.</p>
    <div class="eh-landing-cta">
      <a href="<?php echo esc_url( get_post_type_archive_link( 'eh_exam' ) ); ?>" class="btn btn-primary btn-lg">ابدأ المراجعة الآن</a>
      <a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="btn btn-outline-primary btn-lg">اختر خطتك</a>
    </div>
  </section>

</div>

<?php get_footer(); ?>

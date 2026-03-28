<?php
/**
 * Template Name: Pricing / Subscription Plans
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;
get_header();

$plans      = examhub_get_all_plans();
$user_id    = get_current_user_id();
$sub        = $user_id ? examhub_get_user_subscription_status( $user_id ) : [ 'state' => 'free', 'plan_id' => null ];
$methods    = examhub_get_enabled_payment_methods();

usort( $plans, fn($a,$b) => (int)($a['plan_priority']??0) - (int)($b['plan_priority']??0) );
?>

<div class="container-xl py-4">

  <!-- Page header -->
  <div class="text-center mb-5">
    <h1 class="fw-bold" style="font-size:2.2rem;"><?php esc_html_e( 'اختر خطتك', 'examhub' ); ?></h1>
    <p class="text-muted" style="max-width:520px;margin:0 auto;">
      <?php esc_html_e( 'ابدأ مجاناً بـ 10 أسئلة يومياً. اشترك للوصول الكامل لآلاف الامتحانات.', 'examhub' ); ?>
    </p>

    <?php if ( $user_id && $sub['state'] !== 'free' ) : ?>
    <div class="alert alert-info d-inline-flex align-items-center gap-2 mt-3 py-2 px-4" style="border-radius:20px;">
      <i class="bi bi-star-fill"></i>
      <?php
      printf(
        esc_html__( 'اشتراكك الحالي: %s', 'examhub' ),
        '<strong>' . esc_html( $sub['plan_name'] ) . '</strong>'
      );
      if ( $sub['days_left'] && $sub['days_left'] < 9999 ) {
        echo ' — ' . sprintf( esc_html__( 'ينتهي خلال %d يوم', 'examhub' ), $sub['days_left'] );
      }
      ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Plan cards -->
  <div class="row g-4 justify-content-center mb-5">

    <!-- Free plan (always shown) -->
    <div class="col-lg-3 col-md-6">
      <div class="eh-plan-card">
        <div class="plan-name"><?php esc_html_e( 'مجاني', 'examhub' ); ?></div>
        <div class="plan-price">0 <span><?php esc_html_e( 'دائماً', 'examhub' ); ?></span></div>
        <ul class="plan-features">
          <li><?php printf( esc_html__( '%d أسئلة يومياً', 'examhub' ), (int)(get_field('free_questions_per_day','option')?:10) ); ?></li>
          <li><?php esc_html_e( 'الوصول لعينات الامتحانات', 'examhub' ); ?></li>
          <li class="unavailable"><?php esc_html_e( 'الشرح التفصيلي', 'examhub' ); ?></li>
          <li class="unavailable"><?php esc_html_e( 'الذكاء الاصطناعي', 'examhub' ); ?></li>
          <li class="unavailable"><?php esc_html_e( 'المتصدرون', 'examhub' ); ?></li>
        </ul>
        <?php if ( ! $user_id ) : ?>
          <a href="<?php echo esc_url( wp_registration_url() ); ?>" class="btn btn-outline-primary w-100">
            <?php esc_html_e( 'إنشاء حساب', 'examhub' ); ?>
          </a>
        <?php else : ?>
          <button class="btn btn-ghost w-100" disabled><?php esc_html_e( 'خطتك الحالية', 'examhub' ); ?></button>
        <?php endif; ?>
      </div>
    </div>

    <?php foreach ( $plans as $plan ) :
      $is_current  = $user_id && $sub['plan_id'] === $plan['plan_slug'];
      $is_featured = ! empty( $plan['plan_featured'] );
      $price       = (float) ( $plan['plan_price'] ?? 0 );
      $duration    = (int)   ( $plan['plan_duration_days'] ?? 30 );
      $features    = array_filter( explode( "\n", $plan['plan_features_list'] ?? '' ) );
      ?>
    <div class="col-lg-3 col-md-6">
      <div class="eh-plan-card <?php echo $is_featured ? 'featured' : ''; ?>">
        <?php if ( $is_featured ) : ?>
          <div class="plan-badge"><?php esc_html_e( 'الأشهر', 'examhub' ); ?></div>
        <?php endif; ?>

        <div class="plan-name mt-3"><?php echo esc_html( $plan['plan_name_ar'] ?: $plan['plan_name'] ); ?></div>

        <div class="plan-price mt-2">
          <?php echo number_format( $price ); ?>
          <span>
            <?php esc_html_e( 'جنيه', 'examhub' ); ?>
            <?php if ( $duration ) echo ' / ' . $duration . ' ' . esc_html__( 'يوم', 'examhub' ); ?>
          </span>
        </div>

        <?php if ( $plan['plan_description'] ) : ?>
          <p class="text-muted small mt-2"><?php echo esc_html( $plan['plan_description'] ); ?></p>
        <?php endif; ?>

        <ul class="plan-features">
          <?php if ( ! empty( $plan['plan_unlimited'] ) ) : ?>
            <li><?php esc_html_e( 'أسئلة غير محدودة', 'examhub' ); ?></li>
          <?php elseif ( $plan['plan_questions_limit'] ) : ?>
            <li><?php echo (int)$plan['plan_questions_limit']; ?> <?php esc_html_e( 'سؤال يومياً', 'examhub' ); ?></li>
          <?php endif; ?>

          <li <?php echo empty( $plan['plan_explanation_access'] ) ? 'class="unavailable"' : ''; ?>>
            <?php esc_html_e( 'الشرح التفصيلي', 'examhub' ); ?>
          </li>
          <li <?php echo empty( $plan['plan_ai_access'] ) ? 'class="unavailable"' : ''; ?>>
            <?php esc_html_e( 'الذكاء الاصطناعي', 'examhub' ); ?>
          </li>
          <li <?php echo empty( $plan['plan_download_access'] ) ? 'class="unavailable"' : ''; ?>>
            <?php esc_html_e( 'تحميل PDF', 'examhub' ); ?>
          </li>
          <li <?php echo empty( $plan['plan_leaderboard_access'] ) ? 'class="unavailable"' : ''; ?>>
            <?php esc_html_e( 'المتصدرون', 'examhub' ); ?>
          </li>

          <?php foreach ( $features as $feat ) : ?>
            <li><?php echo esc_html( trim( $feat ) ); ?></li>
          <?php endforeach; ?>
        </ul>

        <?php if ( $is_current ) : ?>
          <button class="btn btn-ghost w-100" disabled>
            <i class="bi bi-check-circle me-1"></i><?php esc_html_e( 'خطتك الحالية', 'examhub' ); ?>
          </button>
        <?php elseif ( $user_id ) : ?>
          <a href="<?php echo esc_url( home_url( '/checkout?plan=' . esc_attr( $plan['plan_slug'] ) ) ); ?>"
             class="btn <?php echo $is_featured ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
            <?php
            if ( $sub['state'] === 'free' ) esc_html_e( 'اشترك الآن', 'examhub' );
            else esc_html_e( 'الترقية', 'examhub' );
            ?>
          </a>
        <?php else : ?>
          <a href="<?php echo esc_url( wp_registration_url() . '?redirect_to=' . urlencode( home_url( '/checkout?plan=' . $plan['plan_slug'] ) ) ); ?>"
             class="btn <?php echo $is_featured ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
            <?php esc_html_e( 'ابدأ الآن', 'examhub' ); ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div><!-- .row -->

  <!-- Feature comparison table -->
  <div class="card mb-5">
    <div class="card-body p-0">
      <h4 class="p-4 mb-0 border-bottom border-eh"><?php esc_html_e( 'مقارنة الخطط', 'examhub' ); ?></h4>
      <div class="table-responsive">
        <table class="table mb-0 eh-pricing-compare-table">
          <thead>
            <tr>
              <th><?php esc_html_e( 'الميزة', 'examhub' ); ?></th>
              <th class="text-center"><?php esc_html_e( 'مجاني', 'examhub' ); ?></th>
              <?php foreach ( $plans as $plan ) : ?>
                <th class="text-center"><?php echo esc_html( $plan['plan_name_ar'] ?: $plan['plan_name'] ); ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php
            $features_compare = [
              [ 'label' => __('الأسئلة اليومية','examhub'), 'key' => 'questions'],
              [ 'label' => __('الشرح التفصيلي','examhub'), 'key' => 'explanation'],
              [ 'label' => __('الذكاء الاصطناعي','examhub'), 'key' => 'ai'],
              [ 'label' => __('تحميل PDF','examhub'), 'key' => 'download'],
              [ 'label' => __('المتصدرون','examhub'), 'key' => 'leaderboard'],
            ];
            foreach ( $features_compare as $feat ) : ?>
            <tr>
              <td><?php echo esc_html( $feat['label'] ); ?></td>
              <td class="text-center">
                <?php
                switch($feat['key']) {
                  case 'questions': echo (int)(get_field('free_questions_per_day','option')?:10) . '/يوم'; break;
                  default: echo '<span class="text-muted">—</span>';
                }
                ?>
              </td>
              <?php foreach ( $plans as $plan ) : ?>
              <td class="text-center">
                <?php
                switch($feat['key']) {
                  case 'questions':
                    echo ! empty($plan['plan_unlimited']) ? esc_html__('غير محدود','examhub') : ( $plan['plan_questions_limit'] ? (int)$plan['plan_questions_limit'].'/يوم' : esc_html__('غير محدود','examhub') );
                    break;
                  case 'explanation':
                    echo ! empty($plan['plan_explanation_access']) ? '<span class="text-success">✓</span>' : '<span class="text-muted">—</span>'; break;
                  case 'ai':
                    echo ! empty($plan['plan_ai_access']) ? '<span class="text-success">✓</span>' : '<span class="text-muted">—</span>'; break;
                  case 'download':
                    echo ! empty($plan['plan_download_access']) ? '<span class="text-success">✓</span>' : '<span class="text-muted">—</span>'; break;
                  case 'leaderboard':
                    echo ! empty($plan['plan_leaderboard_access']) ? '<span class="text-success">✓</span>' : '<span class="text-muted">—</span>'; break;
                }
                ?>
              </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- FAQ -->
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <h3 class="mb-4"><?php esc_html_e( 'أسئلة شائعة', 'examhub' ); ?></h3>
      <div class="accordion eh-pricing-faq" id="pricingFAQ">
        <?php
        $faqs = [
          [ __('هل يمكنني الإلغاء في أي وقت؟','examhub'), __('نعم، يمكنك إلغاء اشتراكك في أي وقت من لوحة التحكم. ستستمر في الاستفادة حتى نهاية فترة الفوترة الحالية.','examhub') ],
          [ __('كيف يتم تجديد الاشتراك؟','examhub'), __('لا يتجدد الاشتراك تلقائياً. ستتلقى إشعاراً قبل انتهائه لتتمكن من التجديد.','examhub') ],
          [ __('ما طرق الدفع المتاحة؟','examhub'), __('نقبل بطاقات الدفع عبر Fawaterk، Vodafone Cash، والحوالة البنكية وInstaPay.','examhub') ],
          [ __('هل هناك استرداد؟','examhub'), __('نعم، نقدم ضمان استرداد الأموال خلال 7 أيام من الاشتراك إذا لم تكن راضياً.','examhub') ],
        ];
        foreach ( $faqs as $i => $faq ) : ?>
        <div class="accordion-item bg-card border-eh mb-2" style="border-radius:var(--eh-radius)!important;">
          <h2 class="accordion-header">
            <button class="accordion-button <?php echo $i > 0 ? 'collapsed' : ''; ?> bg-transparent text-light" type="button"
              data-bs-toggle="collapse" data-bs-target="#faq<?php echo $i; ?>">
              <?php echo esc_html( $faq[0] ); ?>
            </button>
          </h2>
          <div id="faq<?php echo $i; ?>" class="accordion-collapse collapse <?php echo $i === 0 ? 'show' : ''; ?>" data-bs-parent="#pricingFAQ">
            <div class="accordion-body"><?php echo esc_html( $faq[1] ); ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div><!-- .container-xl -->

<?php get_footer(); ?>

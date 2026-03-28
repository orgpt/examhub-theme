<?php
/**
 * Template Name: Subscription
 * Current subscription status, upgrade/downgrade, payment history.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( home_url( '/subscription' ) ) );
    exit;
}

$user_id  = get_current_user_id();
$sub      = examhub_get_user_subscription_status( $user_id );
$plans    = examhub_get_all_plans();
$sub_id   = get_user_meta( $user_id, 'eh_active_sub_id', true );

// Check for payment success/failure redirect
$payment_success = isset( $_GET['payment_success'] );
$payment_failed  = isset( $_GET['payment_failed'] );

usort( $plans, fn($a,$b) => (int)($a['plan_priority']??0) - (int)($b['plan_priority']??0) );

// Get payment history
$payments = get_posts( [
    'post_type'      => 'eh_payment',
    'author'         => $user_id,
    'posts_per_page' => 10,
    'orderby'        => 'date',
    'order'          => 'DESC',
] );

get_header();
?>

<div class="container-xl py-4">

  <div class="eh-page-header">
    <h1 class="eh-page-title"><i class="bi bi-star me-2 text-accent"></i><?php esc_html_e( 'اشتراكي', 'examhub' ); ?></h1>
  </div>

  <?php if ( $payment_success ) : ?>
  <div class="alert alert-success mb-4 d-flex align-items-center gap-2">
    <i class="bi bi-check-circle-fill fs-4"></i>
    <div>
      <strong><?php esc_html_e( 'تم الدفع بنجاح! 🎉', 'examhub' ); ?></strong><br>
      <small><?php esc_html_e( 'تم تفعيل اشتراكك. يمكنك الآن الوصول لجميع المميزات.', 'examhub' ); ?></small>
    </div>
  </div>
  <?php endif; ?>

  <?php if ( $payment_failed ) : ?>
  <div class="alert alert-danger mb-4 d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-circle-fill fs-4"></i>
    <div>
      <strong><?php esc_html_e( 'فشلت عملية الدفع.', 'examhub' ); ?></strong><br>
      <small><?php esc_html_e( 'يرجى المحاولة مجدداً أو اختيار طريقة دفع أخرى.', 'examhub' ); ?></small>
    </div>
  </div>
  <?php endif; ?>

  <div class="row g-4">

    <!-- Current subscription -->
    <div class="col-lg-5">

      <!-- Status card -->
      <div class="card mb-4">
        <div class="card-body">
          <div class="eh-section-title mb-3"><i class="bi bi-star-fill icon"></i><?php esc_html_e( 'حالة الاشتراك', 'examhub' ); ?></div>

          <?php if ( $sub['state'] === 'free' ) : ?>
            <div class="text-center py-3">
              <div style="font-size:3rem;margin-bottom:1rem;">🔓</div>
              <h5 class="fw-bold"><?php esc_html_e( 'الخطة المجانية', 'examhub' ); ?></h5>
              <p class="text-muted small">
                <?php printf( esc_html__( '%d أسئلة يومياً — تجدد كل يوم', 'examhub' ), (int)(get_field('free_questions_per_day','option')?:10) ); ?>
              </p>
              <a href="<?php echo esc_url( home_url('/pricing') ); ?>" class="btn btn-primary mt-2">
                <i class="bi bi-lightning-fill me-1"></i><?php esc_html_e('ترقية الاشتراك','examhub'); ?>
              </a>
            </div>

          <?php else : ?>

            <?php
            $state_colors = [
              'active'   => ['var(--eh-success)', 'bi-check-circle-fill'],
              'trial'    => ['var(--eh-info)',    'bi-hourglass-split'],
              'lifetime' => ['var(--eh-gold)',    'bi-infinity'],
              'expired'  => ['var(--eh-danger)',  'bi-x-circle-fill'],
              'cancelled'=> ['var(--eh-text-muted)','bi-x-circle'],
            ];
            [$color, $icon_cls] = $state_colors[$sub['state']] ?? ['var(--eh-text-muted)', 'bi-circle'];

            $state_labels = [
              'active'    => __( 'نشط', 'examhub' ),
              'trial'     => __( 'تجريبي', 'examhub' ),
              'lifetime'  => __( 'مدى الحياة', 'examhub' ),
              'expired'   => __( 'منتهي', 'examhub' ),
              'cancelled' => __( 'ملغي', 'examhub' ),
            ];
            ?>

            <div class="d-flex align-items-center gap-3 mb-4 p-3 rounded-eh" style="background:var(--eh-bg-tertiary);">
              <i class="bi <?php echo $icon_cls; ?>" style="font-size:2rem;color:<?php echo $color; ?>;"></i>
              <div>
                <div class="fw-bold" style="color:<?php echo $color; ?>;"><?php echo esc_html( $state_labels[$sub['state']] ?? $sub['state'] ); ?></div>
                <div class="fw-bold fs-5"><?php echo esc_html( $sub['plan_name'] ); ?></div>
              </div>
            </div>

            <div class="row g-2 text-center mb-3">
              <div class="col-6">
                <div class="p-2 rounded-eh" style="background:var(--eh-bg-tertiary);">
                  <div class="fw-bold text-accent">
                    <?php echo $sub['unlimited'] ? '∞' : number_format( $sub['questions_limit'] ); ?>
                  </div>
                  <small class="text-muted"><?php esc_html_e( 'أسئلة/يوم', 'examhub' ); ?></small>
                </div>
              </div>
              <div class="col-6">
                <div class="p-2 rounded-eh" style="background:var(--eh-bg-tertiary);">
                  <div class="fw-bold text-accent">
                    <?php echo $sub['ai_access'] ? '✓' : '—'; ?>
                  </div>
                  <small class="text-muted"><?php esc_html_e( 'ذكاء اصطناعي', 'examhub' ); ?></small>
                </div>
              </div>
            </div>

            <?php if ( $sub['expires_at'] && $sub['days_left'] < 9999 ) : ?>
            <div class="mb-3">
              <div class="d-flex justify-content-between mb-1">
                <small class="text-muted"><?php esc_html_e( 'ينتهي في', 'examhub' ); ?></small>
                <small class="<?php echo $sub['days_left'] <= 7 ? 'text-warning fw-bold' : 'text-light'; ?>">
                  <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $sub['expires_at'] ) ) ); ?>
                </small>
              </div>
              <?php if ( $sub['days_left'] <= 30 ) : ?>
              <div class="progress" style="height:4px;">
                <div class="progress-bar <?php echo $sub['days_left'] <= 7 ? 'bg-danger' : ($sub['days_left'] <= 14 ? 'bg-warning' : ''); ?>"
                     style="width:<?php echo round($sub['days_left']/30*100); ?>%">
                </div>
              </div>
              <?php if ( $sub['days_left'] <= 7 ) : ?>
                <small class="text-warning mt-1 d-block">⚠️ <?php printf( esc_html__('ينتهي خلال %d أيام!','examhub'), $sub['days_left'] ); ?></small>
              <?php endif; ?>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="d-flex gap-2 flex-wrap">
              <?php if ( in_array( $sub['state'], ['active','trial'] ) ) : ?>
                <a href="<?php echo esc_url( home_url('/pricing') ); ?>" class="btn btn-outline-primary btn-sm">
                  <i class="bi bi-arrow-up-circle me-1"></i><?php esc_html_e('ترقية','examhub'); ?>
                </a>
                <button type="button" id="btn-renew" class="btn btn-primary btn-sm"
                  onclick="window.location='<?php echo esc_js(home_url('/checkout?plan='.$sub['plan_id'])); ?>'">
                  <?php esc_html_e('تجديد','examhub'); ?>
                </button>
                <button type="button" id="btn-cancel-subscription" class="btn btn-ghost btn-sm text-danger">
                  <?php esc_html_e('إلغاء الاشتراك','examhub'); ?>
                </button>
              <?php elseif ( $sub['state'] === 'expired' ) : ?>
                <a href="<?php echo esc_url( home_url('/pricing') ); ?>" class="btn btn-primary btn-sm">
                  <i class="bi bi-arrow-repeat me-1"></i><?php esc_html_e('تجديد الاشتراك','examhub'); ?>
                </a>
              <?php endif; ?>
            </div>

          <?php endif; ?>

        </div>
      </div><!-- .card -->

      <!-- Features overview -->
      <div class="card">
        <div class="card-body">
          <div class="eh-section-title mb-3"><i class="bi bi-check-circle icon"></i><?php esc_html_e( 'مميزاتك', 'examhub' ); ?></div>
          <ul class="list-unstyled mb-0">
            <?php
            $features = [
              [ esc_html__('أسئلة يومياً','examhub'),         $sub['unlimited'] ? esc_html__('غير محدود','examhub') : number_format($sub['questions_limit']), true ],
              [ esc_html__('الشرح التفصيلي','examhub'),       $sub['explanation_access'] ? '✓' : '—', $sub['explanation_access'] ],
              [ esc_html__('الذكاء الاصطناعي','examhub'),     $sub['ai_access'] ? '✓' : '—',          $sub['ai_access'] ],
              [ esc_html__('تحميل PDF','examhub'),             $sub['download_access'] ? '✓' : '—',    $sub['download_access'] ],
              [ esc_html__('المتصدرون','examhub'),             $sub['leaderboard_access'] ? '✓' : '—', $sub['leaderboard_access'] ],
            ];
            foreach ( $features as [$label, $val, $active] ) :
            ?>
            <li class="d-flex justify-content-between py-2 border-bottom border-eh">
              <span class="<?php echo $active ? 'text-light' : 'text-muted'; ?>"><?php echo $label; ?></span>
              <span class="<?php echo $active ? 'text-success fw-bold' : 'text-muted'; ?>"><?php echo $val; ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

    </div><!-- .col-lg-5 -->

    <!-- Right: Available plans + payment history -->
    <div class="col-lg-7">

      <!-- Available upgrade plans -->
      <?php if ( $sub['state'] !== 'lifetime' ) : ?>
      <div class="mb-4">
        <h5 class="mb-3"><?php esc_html_e( 'الخطط المتاحة', 'examhub' ); ?></h5>
        <div class="row g-3">
          <?php foreach ( $plans as $plan ) :
            $is_current = $sub['plan_id'] === $plan['plan_slug'];
          ?>
          <div class="col-md-6">
            <div class="card h-100 <?php echo $is_current ? 'border-accent' : ''; ?>" style="<?php echo $is_current ? 'border-color:var(--eh-accent)!important;' : ''; ?>">
              <div class="card-body">
                <?php if ( ! empty($plan['plan_featured']) ) : ?><span class="badge badge-accent mb-2"><?php esc_html_e('الأشهر','examhub'); ?></span><?php endif; ?>
                <?php if ( $is_current ) : ?><span class="badge badge-success mb-2"><?php esc_html_e('خطتك الحالية','examhub'); ?></span><?php endif; ?>
                <div class="fw-bold mb-1"><?php echo esc_html($plan['plan_name_ar']?:$plan['plan_name']); ?></div>
                <div class="text-accent fw-bold fs-4"><?php echo number_format($plan['plan_price']); ?> <small class="text-muted fs-6">جنيه / <?php echo (int)$plan['plan_duration_days']; ?> يوم</small></div>
                <?php if ( ! $is_current ) : ?>
                <a href="<?php echo esc_url(home_url('/checkout?plan='.esc_attr($plan['plan_slug']))); ?>" class="btn btn-outline-primary btn-sm mt-3 w-100">
                  <?php echo $sub['state'] === 'free' ? esc_html__('اشترك','examhub') : esc_html__('الترقية','examhub'); ?>
                </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Payment history -->
      <div class="card">
        <div class="card-body">
          <div class="eh-section-title mb-3"><i class="bi bi-receipt icon"></i><?php esc_html_e( 'سجل المدفوعات', 'examhub' ); ?></div>

          <?php if ( empty( $payments ) ) : ?>
            <p class="text-muted"><?php esc_html_e( 'لا توجد مدفوعات بعد.', 'examhub' ); ?></p>
          <?php else : ?>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th><?php esc_html_e( 'التاريخ', 'examhub' ); ?></th>
                  <th><?php esc_html_e( 'الخطة', 'examhub' ); ?></th>
                  <th><?php esc_html_e( 'المبلغ', 'examhub' ); ?></th>
                  <th><?php esc_html_e( 'الطريقة', 'examhub' ); ?></th>
                  <th><?php esc_html_e( 'الحالة', 'examhub' ); ?></th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ( $payments as $p ) :
                  $status  = get_field( 'payment_status', $p->ID );
                  $colors  = [ 'paid' => 'success', 'pending' => 'warning', 'awaiting_review' => 'info', 'failed' => 'danger', 'cancelled' => 'secondary', 'refunded' => 'info' ];
                  $labels  = [ 'paid' => 'مدفوع ✓', 'pending' => 'معلق', 'awaiting_review' => 'قيد المراجعة', 'failed' => 'فشل', 'cancelled' => 'ملغي', 'refunded' => 'مُسترد' ];
                  $methods = [ 'fawaterk' => '💳', 'vodafone_cash' => '📱', 'bank_transfer' => '🏦', 'instapay' => '⚡', 'wallet' => '👛' ];
                  $inv_url = get_field( 'invoice_url', $p->ID );
                ?>
                <tr>
                  <td class="text-muted small"><?php echo get_the_date( 'd/m/Y', $p->ID ); ?></td>
                  <td><?php echo esc_html( get_field('pay_plan_id', $p->ID) ); ?></td>
                  <td><?php echo number_format((float)get_field('amount_egp',$p->ID),2); ?> ج</td>
                  <td><?php echo esc_html( $methods[get_field('payment_method',$p->ID)] ?? '—' ); ?></td>
                  <td><span class="badge badge-<?php echo $colors[$status]??'secondary'; ?>"><?php echo esc_html( $labels[$status] ?? $status ); ?></span></td>
                  <td>
                    <?php if ( $inv_url ) : ?>
                      <a href="<?php echo esc_url($inv_url); ?>" target="_blank" class="btn btn-ghost btn-sm py-0">📄</a>
                    <?php endif; ?>

                    <?php if ( $status === 'awaiting_review' ) : ?>
                      <span class="text-muted small"><?php esc_html_e('قيد المراجعة','examhub'); ?></span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- .col-lg-7 -->
  </div><!-- .row -->
</div><!-- .container-xl -->

<?php get_footer(); ?>

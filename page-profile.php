<?php
/**
 * Template Name: Profile
 * Student profile: personal info, default grade, avatar, password change.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( home_url( '/profile' ) ) );
    exit;
}

$user_id  = get_current_user_id();
$user     = wp_get_current_user();
$xp       = (int) get_user_meta( $user_id, 'eh_xp', true );
$level    = examhub_get_user_level( $xp );
$streak   = (int) get_user_meta( $user_id, 'eh_streak', true );
$sub      = examhub_get_user_subscription_status( $user_id );
$badges   = examhub_get_user_badges( $user_id );
$my_rank  = examhub_get_user_rank( $user_id );
$avatar   = get_avatar_url( $user_id, [ 'size' => 200 ] );

// All grades for default grade picker
$all_grades = get_posts( [
    'post_type'      => 'eh_grade',
    'posts_per_page' => 100,
    'orderby'        => 'meta_value_num',
    'meta_key'       => 'grade_number',
    'order'          => 'ASC',
] );

$default_grade = get_user_meta( $user_id, 'eh_default_grade', true );

// Handle POST save
$success_msg = '';
$error_msg   = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['eh_profile_nonce'] ) ) {
    if ( ! wp_verify_nonce( $_POST['eh_profile_nonce'], 'eh_save_profile_' . $user_id ) ) {
        $error_msg = __( 'طلب غير صالح.', 'examhub' );
    } else {
        $action = sanitize_text_field( $_POST['profile_action'] ?? 'info' );

        if ( $action === 'info' ) {
            $first   = sanitize_text_field( $_POST['first_name']    ?? '' );
            $last    = sanitize_text_field( $_POST['last_name']     ?? '' );
            $phone   = sanitize_text_field( $_POST['phone']         ?? '' );
            $grade   = (int) ( $_POST['default_grade']             ?? 0 );
            $display = trim( "$first $last" ) ?: $user->user_login;

            wp_update_user( [
                'ID'           => $user_id,
                'first_name'   => $first,
                'last_name'    => $last,
                'display_name' => $display,
            ] );

            update_user_meta( $user_id, 'billing_phone',    $phone );
            update_user_meta( $user_id, 'eh_default_grade', $grade );
            update_user_meta( $user_id, 'eh_grade_id',      $grade );

            $user         = wp_get_current_user();
            $default_grade = $grade;
            $success_msg  = __( 'تم حفظ البيانات بنجاح.', 'examhub' );

        } elseif ( $action === 'password' ) {
            $cur  = $_POST['current_password']  ?? '';
            $new  = $_POST['new_password']      ?? '';
            $conf = $_POST['confirm_password']  ?? '';

            if ( ! wp_check_password( $cur, $user->user_pass, $user_id ) ) {
                $error_msg = __( 'كلمة المرور الحالية غير صحيحة.', 'examhub' );
            } elseif ( strlen( $new ) < 8 ) {
                $error_msg = __( 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل.', 'examhub' );
            } elseif ( $new !== $conf ) {
                $error_msg = __( 'كلمتا المرور غير متطابقتان.', 'examhub' );
            } else {
                wp_set_password( $new, $user_id );
                wp_set_auth_cookie( $user_id );
                $success_msg = __( 'تم تغيير كلمة المرور بنجاح.', 'examhub' );
            }
        }
    }
}

get_header();
?>

<div class="container-xl py-4">

  <div class="eh-page-header">
    <h1 class="eh-page-title"><i class="bi bi-person-circle me-2 text-accent"></i><?php esc_html_e( 'الملف الشخصي', 'examhub' ); ?></h1>
  </div>

  <?php if ( $success_msg ) : ?>
    <div class="alert alert-success mb-4"><i class="bi bi-check-circle me-2"></i><?php echo esc_html( $success_msg ); ?></div>
  <?php endif; ?>
  <?php if ( $error_msg ) : ?>
    <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-circle me-2"></i><?php echo esc_html( $error_msg ); ?></div>
  <?php endif; ?>

  <div class="row g-4">

    <!-- Left: Stats card -->
    <div class="col-lg-4">

      <!-- Avatar + user card -->
      <div class="card mb-3">
        <div class="card-body text-center py-4">
          <div class="position-relative d-inline-block mb-3">
            <img src="<?php echo esc_url( $avatar ); ?>" alt="" class="rounded-circle border" style="width:90px;height:90px;object-fit:cover;border-color:var(--eh-accent)!important;border-width:3px!important;">
            <span class="position-absolute bottom-0 end-0 badge rounded-pill" style="background:var(--eh-accent);font-size:.65rem;padding:.3em .5em;">
              <?php echo esc_html( $level['name'] ); ?>
            </span>
          </div>
          <h4 class="fw-bold mb-0"><?php echo esc_html( $user->display_name ); ?></h4>
          <p class="text-muted small"><?php echo esc_html( $user->user_email ); ?></p>

          <!-- XP bar -->
          <div class="eh-xp-progress px-2 mb-3">
            <div class="d-flex justify-content-between mb-1">
              <small class="text-accent fw-bold"><?php echo number_format( $xp ); ?> XP</small>
              <?php if ( $level['next_level_xp'] ) : ?>
                <small class="text-muted"><?php echo number_format( $level['next_level_xp'] ); ?> XP</small>
              <?php endif; ?>
            </div>
            <div class="progress"><div class="progress-bar" style="width:<?php echo $level['progress_pct']; ?>%"></div></div>
            <?php if ( $level['next_level_name'] ) : ?>
              <small class="text-muted mt-1 d-block"><?php printf( esc_html__( 'حتى %s', 'examhub' ), $level['next_level_name'] ); ?></small>
            <?php endif; ?>
          </div>

          <!-- Quick stats -->
          <div class="row g-2 text-center">
            <div class="col-4">
              <div class="fw-bold text-accent"><?php echo $streak; ?>🔥</div>
              <small class="text-muted"><?php esc_html_e( 'سلسلة', 'examhub' ); ?></small>
            </div>
            <div class="col-4">
              <div class="fw-bold"><?php echo $my_rank ? '#' . $my_rank : '—'; ?></div>
              <small class="text-muted"><?php esc_html_e( 'الترتيب', 'examhub' ); ?></small>
            </div>
            <div class="col-4">
              <div class="fw-bold"><?php echo count( array_filter( $badges, fn($b) => $b['earned'] ) ); ?></div>
              <small class="text-muted"><?php esc_html_e( 'شارات', 'examhub' ); ?></small>
            </div>
          </div>
        </div>
      </div>

      <!-- Subscription card -->
      <div class="card mb-3">
        <div class="card-body">
          <div class="eh-section-title"><i class="bi bi-star icon"></i><?php esc_html_e( 'الاشتراك', 'examhub' ); ?></div>
          <?php if ( $sub['state'] === 'free' ) : ?>
            <p class="text-muted small"><?php esc_html_e( 'أنت على الخطة المجانية.', 'examhub' ); ?></p>
            <a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="btn btn-primary btn-sm w-100">
              <i class="bi bi-lightning-fill me-1"></i><?php esc_html_e( 'ترقية الاشتراك', 'examhub' ); ?>
            </a>
          <?php else : ?>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted small"><?php esc_html_e( 'الخطة', 'examhub' ); ?></span>
              <span class="badge badge-success"><?php echo esc_html( $sub['plan_name'] ); ?></span>
            </div>
            <?php if ( $sub['days_left'] && $sub['days_left'] < 9999 ) : ?>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted small"><?php esc_html_e( 'ينتهي', 'examhub' ); ?></span>
              <span class="<?php echo $sub['days_left'] <= 7 ? 'text-warning' : 'text-light'; ?> small fw-bold">
                <?php printf( esc_html__( 'خلال %d يوم', 'examhub' ), $sub['days_left'] ); ?>
              </span>
            </div>
            <?php endif; ?>
            <div class="d-flex gap-2 mt-2">
              <a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="btn btn-outline-primary btn-sm flex-1">
                <?php esc_html_e( 'تجديد', 'examhub' ); ?>
              </a>
              <button type="button" id="btn-cancel-subscription" class="btn btn-ghost btn-sm">
                <?php esc_html_e( 'إلغاء', 'examhub' ); ?>
              </button>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Badges card -->
      <div class="card">
        <div class="card-body">
          <div class="eh-section-title"><i class="bi bi-award icon"></i><?php esc_html_e( 'شاراتي', 'examhub' ); ?></div>
          <?php if ( empty( $badges ) ) : ?>
            <p class="text-muted small"><?php esc_html_e( 'لم تحصل على شارات بعد. أجرِ امتحانات!', 'examhub' ); ?></p>
          <?php else : ?>
            <div class="row g-2">
              <?php foreach ( array_slice( $badges, 0, 9 ) as $badge ) : ?>
              <div class="col-4">
                <div class="eh-achievement-badge <?php echo ! $badge['earned'] ? 'locked' : ''; ?>"
                     title="<?php echo esc_attr( $badge['name'] ); ?><?php echo ! $badge['earned'] ? ' (غير مكتسبة)' : ''; ?>"
                     data-bs-toggle="tooltip">
                  <?php if ( $badge['icon_url'] ) : ?>
                    <img src="<?php echo esc_url( $badge['icon_url'] ); ?>" alt="<?php echo esc_attr( $badge['name'] ); ?>">
                  <?php else : ?>
                    <span style="font-size:2rem;">🏅</span>
                  <?php endif; ?>
                  <span class="badge-name"><?php echo esc_html( $badge['name'] ); ?></span>
                  <?php if ( $badge['xp'] > 0 ) : ?>
                    <span class="badge-xp">+<?php echo $badge['xp']; ?> XP</span>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- .col-lg-4 -->

    <!-- Right: Edit forms -->
    <div class="col-lg-8">

      <!-- Tab navigation -->
      <ul class="nav nav-pills mb-4" id="profile-tabs">
        <li class="nav-item">
          <a class="nav-link active" href="#tab-info" data-bs-toggle="pill"><?php esc_html_e( 'البيانات الشخصية', 'examhub' ); ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#tab-password" data-bs-toggle="pill"><?php esc_html_e( 'كلمة المرور', 'examhub' ); ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#tab-invoices" data-bs-toggle="pill"><?php esc_html_e( 'الفواتير', 'examhub' ); ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#tab-xp-log" data-bs-toggle="pill"><?php esc_html_e( 'سجل XP', 'examhub' ); ?></a>
        </li>
      </ul>

      <div class="tab-content">

        <!-- Personal Info Tab -->
        <div class="tab-pane fade show active" id="tab-info">
          <div class="card">
            <div class="card-body">
              <form method="post">
                <?php wp_nonce_field( 'eh_save_profile_' . $user_id, 'eh_profile_nonce' ); ?>
                <input type="hidden" name="profile_action" value="info">

                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label"><?php esc_html_e( 'الاسم الأول', 'examhub' ); ?></label>
                    <input type="text" name="first_name" class="form-control" value="<?php echo esc_attr( $user->first_name ); ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label"><?php esc_html_e( 'الاسم الأخير', 'examhub' ); ?></label>
                    <input type="text" name="last_name" class="form-control" value="<?php echo esc_attr( $user->last_name ); ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label"><?php esc_html_e( 'البريد الإلكتروني', 'examhub' ); ?></label>
                    <input type="email" class="form-control" value="<?php echo esc_attr( $user->user_email ); ?>" disabled readonly>
                    <div class="form-text"><?php esc_html_e( 'لا يمكن تغيير البريد الإلكتروني.', 'examhub' ); ?></div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label"><?php esc_html_e( 'رقم الهاتف', 'examhub' ); ?></label>
                    <input type="tel" name="phone" class="form-control" value="<?php echo esc_attr( get_user_meta( $user_id, 'billing_phone', true ) ); ?>" placeholder="010xxxxxxxx" dir="ltr">
                  </div>
                  <div class="col-12">
                    <label class="form-label"><?php esc_html_e( 'صفي الدراسي', 'examhub' ); ?></label>
                    <select name="default_grade" class="form-select">
                      <option value=""><?php esc_html_e( '— اختر صفك —', 'examhub' ); ?></option>
                      <?php foreach ( $all_grades as $grade ) :
                        $grade_name = get_field( 'grade_name_ar', $grade->ID ) ?: $grade->post_title;
                      ?>
                        <option value="<?php echo $grade->ID; ?>" <?php selected( $default_grade, $grade->ID ); ?>>
                          <?php echo esc_html( $grade_name ); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <div class="form-text"><?php esc_html_e( 'يُستخدم لتخصيص توصيات الامتحانات.', 'examhub' ); ?></div>
                  </div>
                </div>

                <div class="mt-4">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i><?php esc_html_e( 'حفظ البيانات', 'examhub' ); ?>
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Password Tab -->
        <div class="tab-pane fade" id="tab-password">
          <div class="card">
            <div class="card-body">
              <form method="post">
                <?php wp_nonce_field( 'eh_save_profile_' . $user_id, 'eh_profile_nonce' ); ?>
                <input type="hidden" name="profile_action" value="password">

                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label"><?php esc_html_e( 'كلمة المرور الحالية', 'examhub' ); ?></label>
                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password" dir="ltr">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label"><?php esc_html_e( 'كلمة المرور الجديدة', 'examhub' ); ?></label>
                    <input type="password" name="new_password" id="new_password" class="form-control" required minlength="8" autocomplete="new-password" dir="ltr">
                    <div class="form-text"><?php esc_html_e( '8 أحرف على الأقل.', 'examhub' ); ?></div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label"><?php esc_html_e( 'تأكيد كلمة المرور', 'examhub' ); ?></label>
                    <input type="password" name="confirm_password" class="form-control" required autocomplete="new-password" dir="ltr">
                  </div>
                </div>

                <!-- Password strength meter -->
                <div class="mt-3" id="password-strength-wrap" style="display:none;">
                  <div class="d-flex align-items-center gap-2 mb-1">
                    <div style="flex:1;height:6px;background:var(--eh-border);border-radius:50px;overflow:hidden;">
                      <div id="password-strength-bar" style="height:100%;border-radius:50px;width:0%;transition:width .3s,background .3s;"></div>
                    </div>
                    <small id="password-strength-label" class="text-muted"></small>
                  </div>
                </div>

                <div class="mt-4">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-shield-lock me-1"></i><?php esc_html_e( 'تغيير كلمة المرور', 'examhub' ); ?>
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Invoices Tab -->
        <div class="tab-pane fade" id="tab-invoices">
          <div class="card">
            <div class="card-body">
              <div class="eh-section-title mb-3"><i class="bi bi-receipt icon"></i><?php esc_html_e( 'سجل الفواتير', 'examhub' ); ?></div>
              <div id="invoices-container">
                <button type="button" class="btn btn-ghost btn-sm" id="btn-load-invoices">
                  <i class="bi bi-cloud-download me-1"></i><?php esc_html_e( 'تحميل الفواتير', 'examhub' ); ?>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- XP Log Tab -->
        <div class="tab-pane fade" id="tab-xp-log">
          <div class="card">
            <div class="card-body">
              <div class="eh-section-title mb-3"><i class="bi bi-lightning icon"></i><?php esc_html_e( 'سجل نقاط XP', 'examhub' ); ?></div>
              <?php
              $xp_log = array_reverse( (array) get_user_meta( $user_id, 'eh_xp_log', true ) );
              if ( empty( $xp_log ) ) :
              ?>
                <p class="text-muted"><?php esc_html_e( 'لا توجد سجلات بعد.', 'examhub' ); ?></p>
              <?php else : ?>
              <div class="table-responsive">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th><?php esc_html_e( 'التاريخ', 'examhub' ); ?></th>
                      <th><?php esc_html_e( 'السبب', 'examhub' ); ?></th>
                      <th><?php esc_html_e( 'XP', 'examhub' ); ?></th>
                      <th><?php esc_html_e( 'الإجمالي', 'examhub' ); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ( array_slice( $xp_log, 0, 30 ) as $entry ) : ?>
                    <tr>
                      <td class="text-muted small"><?php echo date_i18n( 'd/m/Y H:i', $entry['timestamp'] ?? 0 ); ?></td>
                      <td><?php echo esc_html( $entry['reason'] ?? '' ); ?></td>
                      <td class="text-success fw-bold">+<?php echo (int)($entry['amount'] ?? 0); ?></td>
                      <td class="text-muted"><?php echo number_format( $entry['total'] ?? 0 ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div><!-- .tab-content -->
    </div><!-- .col-lg-8 -->
  </div><!-- .row -->
</div><!-- .container-xl -->

<script>
(function($){
  // Password strength meter
  $('#new_password').on('input', function(){
    const val = $(this).val();
    const wrap = $('#password-strength-wrap').toggle(val.length > 0);
    const bar  = $('#password-strength-bar');
    const lbl  = $('#password-strength-label');
    let score  = 0;
    if(val.length >= 8) score++;
    if(/[A-Z]/.test(val)) score++;
    if(/[0-9]/.test(val)) score++;
    if(/[^A-Za-z0-9]/.test(val)) score++;
    const colors = ['var(--eh-danger)','var(--eh-warning)','var(--eh-info)','var(--eh-success)'];
    const labels = ['ضعيفة','مقبولة','جيدة','قوية'];
    bar.css({width:(score/4*100)+'%',background:colors[score-1]||colors[0]});
    lbl.text(labels[score-1]||labels[0]);
  });

  // Auto-load invoices when tab shown
  $('a[href="#tab-invoices"]').on('shown.bs.tab', function(){
    if(!$('#invoices-container table').length) $('#btn-load-invoices').trigger('click');
  });
})(jQuery);
</script>

<?php get_footer(); ?>

<?php
/**
 * ExamHub — single-eh_exam.php
 * Exam overview + start screen. The exam itself runs via exam-take.php
 * accessed through a query var (?take=1).
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

$exam_id = get_the_ID();
$user_id = get_current_user_id();

// If ?take=1, show the exam screen
if ( isset( $_GET['take'] ) ) {
    get_template_part( 'template-parts/exam/exam-screen' );
    return;
}

// If result_id is set, show result screen
if ( isset( $_GET['result'] ) ) {
    get_template_part( 'template-parts/exam/result-screen' );
    return;
}

get_header();

// Access check
$access = is_user_logged_in() ? examhub_verify_exam_access( $exam_id, $user_id ) : null;
$is_locked = $access instanceof WP_Error;
$lock_code = $is_locked ? $access->get_error_code() : null;

// Meta
$grade_id      = (int) get_field( 'exam_grade', $exam_id );
$subject_id    = (int) get_field( 'exam_subject', $exam_id );
$duration      = (int) get_field( 'exam_duration_minutes', $exam_id );
$difficulty    = get_field( 'exam_difficulty', $exam_id ) ?: 'mixed';
$timer_type    = get_field( 'timer_type', $exam_id );
$q_count       = examhub_get_exam_question_count( $exam_id );
$pass_pct      = get_field( 'pass_percentage', $exam_id ) ?: 50;
$random_q      = get_field( 'random_questions', $exam_id );
$random_a      = get_field( 'random_answers', $exam_id );
$allow_skip    = get_field( 'allow_skip', $exam_id );
$allow_review  = get_field( 'allow_mark_review', $exam_id );
$allow_resume  = get_field( 'allow_resume', $exam_id );
$xp_reward     = (int) get_field( 'exam_xp_reward', $exam_id );
$show_exp      = get_field( 'show_explanation', $exam_id );
$max_attempts  = (int) get_field( 'max_attempts', $exam_id );
$exam_type     = get_field( 'exam_type', $exam_id ) ?: 'standard';
$secret_required = examhub_exam_requires_secret_code( $exam_id );
$secret_unlocked = $user_id ? examhub_user_has_valid_exam_secret( $exam_id, $user_id ) : false;
$secret_notice   = sanitize_text_field( wp_unslash( $_GET['exam_secret'] ?? '' ) );

$grade_name    = $grade_id   ? ( get_field( 'grade_name_ar', $grade_id )   ?: get_the_title( $grade_id )   ) : '';
$subject_name  = $subject_id ? ( get_field( 'subject_name_ar', $subject_id ) ?: get_the_title( $subject_id ) ) : '';
$subject_color = $subject_id ? ( get_field( 'subject_color', $subject_id ) ?: '#4361ee' ) : '#4361ee';

// User exam history
$attempt_count = $user_id ? examhub_get_exam_attempt_count( $exam_id, $user_id ) : 0;
$best_result   = $user_id ? examhub_get_best_result( $exam_id, $user_id ) : null;
$best_pct      = $best_result ? (float) get_field( 'percentage', $best_result ) : null;

// In-progress session
$in_progress   = $user_id ? examhub_get_in_progress_result( $exam_id, $user_id ) : null;
?>

<div class="container" style="max-width:800px; padding-top:2.5rem; padding-bottom:3rem;">

  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb" style="font-size:.85rem;">
      <li class="breadcrumb-item"><a href="<?php echo home_url(); ?>"><?php esc_html_e( 'الرئيسية', 'examhub' ); ?></a></li>
      <li class="breadcrumb-item"><a href="<?php echo get_post_type_archive_link( 'eh_exam' ); ?>"><?php esc_html_e( 'الامتحانات', 'examhub' ); ?></a></li>
      <?php if ( $subject_name ) : ?>
        <li class="breadcrumb-item"><?php echo esc_html( $subject_name ); ?></li>
      <?php endif; ?>
      <li class="breadcrumb-item active"><?php the_title(); ?></li>
    </ol>
  </nav>

  <!-- Exam header card -->
  <div class="card mb-4" style="border-top: 3px solid <?php echo esc_attr( $subject_color ); ?>">
    <div class="card-body p-4">

      <div class="d-flex gap-2 flex-wrap mb-3">
        <?php if ( $subject_name ) : ?>
          <span class="badge" style="background:<?php echo esc_attr( $subject_color ); ?>20; color:<?php echo esc_attr( $subject_color ); ?>; border:1px solid <?php echo esc_attr( $subject_color ); ?>40;">
            <?php echo esc_html( $subject_name ); ?>
          </span>
        <?php endif; ?>
        <?php if ( $grade_name ) : ?>
          <span class="badge badge-accent"><?php echo esc_html( $grade_name ); ?></span>
        <?php endif; ?>
        <span class="badge badge-<?php echo esc_attr( $difficulty ); ?>"><?php echo esc_html( examhub_difficulty_label( $difficulty ) ); ?></span>
        <?php if ( $exam_type === 'daily' ) : ?>
          <span class="badge" style="background:var(--eh-warning-bg); color:var(--eh-warning); border:1px solid rgba(245,158,11,.3);">
            🔥 <?php esc_html_e( 'تحدي يومي', 'examhub' ); ?>
          </span>
        <?php endif; ?>
      </div>

      <h1 class="h3 fw-bold text-light mb-2"><?php the_title(); ?></h1>

      <?php if ( get_the_excerpt() ) : ?>
        <p class="text-muted mb-0"><?php the_excerpt(); ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="row g-3 mb-4">

    <!-- Info cards -->
    <div class="col-6 col-sm-3">
      <div class="card text-center p-3">
        <div class="fs-2 mb-1 text-accent"><i class="bi bi-question-circle"></i></div>
        <div class="fw-bold text-light"><?php echo $q_count; ?></div>
        <small class="text-muted"><?php esc_html_e( 'سؤال', 'examhub' ); ?></small>
      </div>
    </div>

    <div class="col-6 col-sm-3">
      <div class="card text-center p-3">
        <div class="fs-2 mb-1" style="color:var(--eh-warning);">
          <i class="bi bi-clock<?php echo $timer_type === 'none' ? '-history' : ''; ?>"></i>
        </div>
        <div class="fw-bold text-light">
          <?php
          if ( $timer_type === 'none' ) {
              esc_html_e( 'بلا مؤقت', 'examhub' );
          } elseif ( $timer_type === 'per_question' ) {
              $sec = (int) get_field( 'seconds_per_question', $exam_id );
              echo $sec . ' ' . __( 'ثانية/سؤال', 'examhub' );
          } else {
              echo esc_html( examhub_format_duration( $duration ) );
          }
          ?>
        </div>
        <small class="text-muted"><?php esc_html_e( 'المدة', 'examhub' ); ?></small>
      </div>
    </div>

    <div class="col-6 col-sm-3">
      <div class="card text-center p-3">
        <div class="fs-2 mb-1" style="color:var(--eh-success);"><i class="bi bi-check-circle"></i></div>
        <div class="fw-bold text-light"><?php echo $pass_pct; ?>%</div>
        <small class="text-muted"><?php esc_html_e( 'نسبة النجاح', 'examhub' ); ?></small>
      </div>
    </div>

    <div class="col-6 col-sm-3">
      <div class="card text-center p-3">
        <div class="fs-2 mb-1 text-accent"><i class="bi bi-lightning-fill"></i></div>
        <div class="fw-bold text-light"><?php echo $xp_reward; ?></div>
        <small class="text-muted">XP</small>
      </div>
    </div>

  </div>

  <!-- Features list -->
  <div class="card mb-4">
    <div class="card-body p-4">
      <h5 class="fw-bold text-light mb-3"><?php esc_html_e( 'خصائص الامتحان', 'examhub' ); ?></h5>
      <div class="row g-2">
        <?php
        $features = [
            $random_q   ? [ 'icon' => 'shuffle',       'label' => __( 'أسئلة عشوائية', 'examhub' ),     'on' => true  ] : null,
            $random_a   ? [ 'icon' => 'arrow-left-right', 'label' => __( 'إجابات عشوائية', 'examhub' ),   'on' => true  ] : null,
            $allow_skip ? [ 'icon' => 'skip-forward',   'label' => __( 'تخطي الأسئلة', 'examhub' ),      'on' => true  ] : null,
            $allow_review ? [ 'icon' => 'flag',         'label' => __( 'وضع علامة للمراجعة', 'examhub' ),'on' => true  ] : null,
            $allow_resume ? [ 'icon' => 'play-circle',  'label' => __( 'استكمال الامتحان', 'examhub' ),   'on' => true  ] : null,
            $show_exp   ? [ 'icon' => 'lightbulb',      'label' => __( 'شرح بعد التسليم', 'examhub' ),   'on' => true  ] : null,
            [ 'icon' => 'bar-chart', 'label' => __( 'تحليل الأداء', 'examhub' ), 'on' => true ],
        ];
        foreach ( array_filter( $features ) as $feat ) : ?>
          <div class="col-6 col-sm-4">
            <div class="d-flex align-items-center gap-2 small">
              <i class="bi bi-<?php echo esc_attr( $feat['icon'] ); ?> text-accent"></i>
              <span class="text-secondary"><?php echo esc_html( $feat['label'] ); ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Previous attempt info -->
  <?php if ( $best_pct !== null ) : ?>
    <div class="alert alert-info mb-4 d-flex align-items-center gap-3">
      <i class="bi bi-trophy-fill fs-4" style="color:var(--eh-gold);"></i>
      <div>
        <strong><?php esc_html_e( 'أفضل نتيجة لك:', 'examhub' ); ?></strong>
        <?php echo number_format( $best_pct, 1 ); ?>%
        <?php printf( esc_html__( '(%d محاولة)', 'examhub' ), $attempt_count ); ?>
        <a href="<?php echo get_permalink( $best_result ); ?>" class="small ms-2">
          <?php esc_html_e( 'عرض النتيجة', 'examhub' ); ?>
        </a>
      </div>
    </div>
  <?php endif; ?>

  <!-- Resume banner -->
  <?php if ( $in_progress ) : ?>
    <div class="card mb-4" style="border-color:var(--eh-warning); background:var(--eh-warning-bg);">
      <div class="card-body p-3 d-flex align-items-center justify-content-between gap-3">
        <div>
          <i class="bi bi-pause-circle me-2" style="color:var(--eh-warning);"></i>
          <strong style="color:var(--eh-warning);"><?php esc_html_e( 'لديك جلسة غير مكتملة', 'examhub' ); ?></strong>
          <p class="mb-0 small text-muted"><?php esc_html_e( 'يمكنك متابعة الامتحان من حيث توقفت.', 'examhub' ); ?></p>
        </div>
        <a href="<?php echo add_query_arg( 'take', 1, get_permalink() ); ?>" class="btn btn-warning btn-sm flex-shrink-0">
          <i class="bi bi-play-fill me-1"></i>
          <?php esc_html_e( 'استكمل', 'examhub' ); ?>
        </a>
      </div>
    </div>
  <?php endif; ?>

  <!-- Start CTA -->
  <div class="text-center">
    <?php if ( ! is_user_logged_in() ) : ?>
      <a href="<?php echo wp_login_url( get_permalink() ); ?>" class="btn btn-primary btn-lg px-5">
        <i class="bi bi-person me-2"></i>
        <?php esc_html_e( 'سجل دخول للبدء', 'examhub' ); ?>
      </a>

    <?php elseif ( $secret_required && ! $secret_unlocked ) : ?>
      <div class="card mx-auto mb-3 text-start" style="max-width:560px;">
        <div class="card-body p-4">
          <h5 class="fw-bold text-light mb-2"><?php esc_html_e( 'هذا الامتحان محمي بكود سري', 'examhub' ); ?></h5>
          <p class="text-muted mb-3"><?php esc_html_e( 'أدخل الكود الذي أرسله لك المدرس حتى تتمكن من بدء الامتحان.', 'examhub' ); ?></p>
          <?php if ( 'invalid' === $secret_notice ) : ?>
            <div class="alert alert-danger py-2"><?php esc_html_e( 'الكود غير صحيح. حاول مرة أخرى.', 'examhub' ); ?></div>
          <?php elseif ( 'unlocked' === $secret_notice ) : ?>
            <div class="alert alert-success py-2"><?php esc_html_e( 'تم التحقق من الكود بنجاح. يمكنك الآن بدء الامتحان.', 'examhub' ); ?></div>
          <?php endif; ?>
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'examhub_exam_secret_unlock', 'examhub_exam_secret_nonce' ); ?>
            <input type="hidden" name="action" value="examhub_exam_secret_unlock">
            <input type="hidden" name="exam_id" value="<?php echo esc_attr( $exam_id ); ?>">
            <div class="d-flex flex-wrap gap-2 justify-content-center">
              <input type="text" name="exam_secret_code" class="form-control" style="max-width:240px;" placeholder="<?php esc_attr_e( 'أدخل كود الامتحان', 'examhub' ); ?>" required>
              <button type="submit" class="btn btn-primary"><?php esc_html_e( 'تأكيد الكود', 'examhub' ); ?></button>
            </div>
          </form>
        </div>
      </div>

    <?php elseif ( $is_locked ) : ?>
      <?php if ( $lock_code === 'max_attempts' ) : ?>
        <div class="alert alert-warning">
          <?php esc_html_e( 'لقد استنفدت الحد الأقصى من المحاولات لهذا الامتحان.', 'examhub' ); ?>
        </div>
      <?php else : ?>
        <a href="<?php echo home_url( '/pricing' ); ?>" class="btn btn-primary btn-lg px-5">
          <i class="bi bi-star me-2"></i>
          <?php esc_html_e( 'اشترك للوصول إلى هذا الامتحان', 'examhub' ); ?>
        </a>
        <p class="text-muted small mt-2"><?php esc_html_e( 'هذا الامتحان للمشتركين فقط', 'examhub' ); ?></p>
      <?php endif; ?>

    <?php else : ?>
      <a href="<?php echo add_query_arg( 'take', 1, get_permalink() ); ?>"
        class="btn btn-primary btn-lg px-5 mb-2" id="btn-start-exam">
        <i class="bi bi-play-fill me-2"></i>
        <?php echo $in_progress ? esc_html__( 'استكمل الامتحان', 'examhub' ) : esc_html__( 'ابدأ الامتحان الآن', 'examhub' ); ?>
      </a>
      <?php if ( $max_attempts > 0 ) : ?>
        <p class="small text-muted">
          <?php printf(
              esc_html__( 'المحاولات: %d / %d', 'examhub' ),
              $attempt_count, $max_attempts
          ); ?>
        </p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</div>

<?php get_footer(); ?>

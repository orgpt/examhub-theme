<?php
/**
 * ExamHub — template-parts/exam/exam-screen.php
 * Full-page exam UI: question renderer, timer, progress, autosave.
 * Loaded when ?take=1 is present on a single exam.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}

$exam_id = get_the_ID();
$user_id = get_current_user_id();

$access = examhub_verify_exam_access( $exam_id, $user_id );
if ( is_wp_error( $access ) ) {
    wp_redirect( get_permalink() );
    exit;
}

// Exam settings
$timer_type      = get_field( 'timer_type', $exam_id ) ?: 'none';
$duration_min    = (int) get_field( 'exam_duration_minutes', $exam_id );
$sec_per_q       = (int) get_field( 'seconds_per_question', $exam_id ) ?: 60;
$allow_skip      = (bool) get_field( 'allow_skip', $exam_id );
$allow_review    = (bool) get_field( 'allow_mark_review', $exam_id );

// Pass to JS
$js_config = [
    'exam_id'          => $exam_id,
    'timer_type'       => $timer_type,
    'duration_seconds' => $duration_min * 60,
    'sec_per_question' => $sec_per_q,
    'allow_skip'       => $allow_skip,
    'allow_review'     => $allow_review,
    'allow_resume'     => (bool) get_field( 'allow_resume', $exam_id ),
    'autosave_interval'=> 30,
    'ajax_url'         => admin_url( 'admin-ajax.php' ),
    'nonce'            => wp_create_nonce( 'examhub_ajax' ),
    'exam_url'         => get_permalink( $exam_id ),
    'result_base_url'  => add_query_arg( 'result', '', get_permalink( $exam_id ) ),
];

// Enqueue exam CSS + JS
wp_enqueue_style(  'examhub-exam', EXAMHUB_ASSETS . 'css/exam.css', [], EXAMHUB_VERSION );
wp_enqueue_script( 'sortablejs', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js', [], '1.15.2', true );
wp_enqueue_script( 'examhub-exam-engine', EXAMHUB_ASSETS . 'js/exam-engine.js', [ 'jquery', 'sortablejs' ], EXAMHUB_VERSION, true );
wp_localize_script( 'examhub-exam-engine', 'examhubConfig', $js_config );

// Output minimal HTML — exam engine builds UI via JS
?><!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title><?php the_title(); ?> — <?php bloginfo( 'name' ); ?></title>
<?php wp_head(); ?>
</head>
<body class="examhub-theme dark-theme exam-mode">
<?php wp_body_open(); ?>

<!-- ═══════════════════════════════════════════════════════════════════
  EXAM SHELL — all content injected by exam-engine.js
══════════════════════════════════════════════════════════════════════ -->
<div id="eh-exam-app" class="eh-exam-app">

  <!-- Top bar -->
  <header class="eh-exam-header">
    <div class="eh-exam-header-inner">

      <!-- Exit button -->
      <button id="btn-exam-exit" class="btn btn-ghost btn-sm" title="<?php esc_attr_e( 'الخروج', 'examhub' ); ?>">
        <i class="bi bi-x-lg"></i>
        <span class="d-none d-md-inline ms-1"><?php esc_html_e( 'خروج', 'examhub' ); ?></span>
      </button>

      <!-- Title + progress -->
      <div class="eh-exam-title-block">
        <h1 class="eh-exam-name"><?php the_title(); ?></h1>
        <div class="eh-exam-progress-text">
          <span id="q-current">1</span> / <span id="q-total">...</span>
        </div>
      </div>

      <!-- Timer -->
      <div class="eh-exam-timer-block" id="exam-timer-block">
        <i class="bi bi-clock me-1"></i>
        <span id="exam-timer" class="eh-timer-value">--:--</span>
      </div>

    </div>

    <!-- Progress bar -->
    <div class="eh-exam-progress-bar-track">
      <div class="eh-exam-progress-bar-fill" id="exam-progress-bar" style="width:0%"></div>
    </div>
  </header>

  <!-- Main question area -->
  <main class="eh-exam-main" id="exam-main">

    <!-- Loading state (shown while JS initialises) -->
    <div id="exam-loading" class="eh-exam-loading-screen">
      <div class="eh-loading-spinner"></div>
      <p class="mt-3 text-muted"><?php esc_html_e( 'جاري تحميل الامتحان...', 'examhub' ); ?></p>
    </div>

    <!-- Question container (rendered by JS) -->
    <div id="question-container" style="display:none;">

      <!-- Question header -->
      <div class="eh-question-meta" id="question-meta">
        <span class="badge badge-accent" id="q-type-badge"></span>
        <span class="badge" id="q-points-badge"></span>
        <span class="badge" id="q-diff-badge"></span>
        <?php if ( $allow_review ) : ?>
          <button class="btn btn-ghost btn-sm eh-review-btn" id="btn-mark-review">
            <i class="bi bi-flag me-1" id="review-icon"></i>
            <span id="review-label"><?php esc_html_e( 'للمراجعة', 'examhub' ); ?></span>
          </button>
        <?php endif; ?>
      </div>

      <!-- Question text -->
      <div class="eh-question-body">
        <div id="question-text" class="eh-question-text"></div>
        <div id="question-body-content" class="eh-question-body-content" style="display:none;"></div>
        <div id="question-image-wrap" style="display:none;">
          <img id="question-image" src="" alt="" class="eh-question-image">
        </div>
        <div id="question-math-wrap" style="display:none;">
          <div id="question-math" class="eh-question-math"></div>
        </div>
      </div>

      <!-- Per-question timer (shown for per_question mode) -->
      <?php if ( $timer_type === 'per_question' ) : ?>
        <div class="eh-question-timer" id="question-timer-block">
          <div class="eh-qtimer-bar-track">
            <div class="eh-qtimer-bar-fill" id="question-timer-bar" style="width:100%;"></div>
          </div>
          <span id="question-timer-secs" class="eh-qtimer-text">--</span>
        </div>
      <?php endif; ?>

      <!-- Answer area (dynamic per type) -->
      <div class="eh-answer-area" id="answer-area">
        <!-- Injected by JS based on question type -->
      </div>

    </div><!-- #question-container -->

  </main><!-- .eh-exam-main -->

  <!-- Bottom navigation bar -->
  <footer class="eh-exam-footer">
    <div class="eh-exam-footer-inner">

      <!-- Question dot navigator -->
      <button class="btn btn-ghost btn-sm" id="btn-q-nav-toggle" title="<?php esc_attr_e( 'التنقل بين الأسئلة', 'examhub' ); ?>">
        <i class="bi bi-grid-3x3-gap"></i>
        <span id="answered-count" class="badge badge-accent ms-1">0</span>
      </button>

      <!-- Nav buttons -->
      <div class="d-flex gap-2 align-items-center">
        <button class="btn btn-ghost" id="btn-prev" disabled>
          <i class="bi bi-chevron-right"></i>
          <span class="d-none d-sm-inline"><?php esc_html_e( 'السابق', 'examhub' ); ?></span>
        </button>

        <?php if ( $allow_skip ) : ?>
          <button class="btn btn-ghost btn-sm" id="btn-skip">
            <?php esc_html_e( 'تخطي', 'examhub' ); ?>
            <i class="bi bi-chevron-left"></i>
          </button>
        <?php endif; ?>

        <button class="btn btn-primary" id="btn-next">
          <span class="d-none d-sm-inline"><?php esc_html_e( 'التالي', 'examhub' ); ?></span>
          <i class="bi bi-chevron-left"></i>
        </button>
      </div>

      <!-- Submit -->
      <button class="btn btn-success" id="btn-submit-exam">
        <i class="bi bi-check-circle me-1"></i>
        <span class="d-none d-sm-inline"><?php esc_html_e( 'تسليم', 'examhub' ); ?></span>
      </button>

    </div>
  </footer>

  <!-- Question navigator panel (shown on toggle) -->
  <div class="eh-q-navigator" id="q-navigator" style="display:none;">
    <div class="eh-q-nav-header">
      <span><?php esc_html_e( 'التنقل بين الأسئلة', 'examhub' ); ?></span>
      <button class="btn btn-ghost btn-sm" id="btn-close-nav"><i class="bi bi-x"></i></button>
    </div>
    <div class="eh-q-nav-legend">
      <span class="eh-dot answered"></span> <?php esc_html_e( 'أجبت', 'examhub' ); ?>
      <span class="eh-dot current ms-3"></span> <?php esc_html_e( 'الحالي', 'examhub' ); ?>
      <span class="eh-dot review ms-3"></span> <?php esc_html_e( 'للمراجعة', 'examhub' ); ?>
      <span class="eh-dot unanswered ms-3"></span> <?php esc_html_e( 'لم تجب', 'examhub' ); ?>
    </div>
    <div class="eh-q-dots" id="q-dots"></div>
  </div>

  <!-- Autosave indicator -->
  <div id="autosave-indicator" class="eh-autosave-indicator" style="display:none;">
    <i class="bi bi-cloud-check me-1"></i>
    <span><?php esc_html_e( 'تم الحفظ', 'examhub' ); ?></span>
  </div>

  <!-- Submit confirm modal -->
  <div class="eh-modal-overlay" id="submit-modal" style="display:none;">
    <div class="eh-modal-box">
      <div class="eh-modal-icon text-success"><i class="bi bi-check-circle fs-1"></i></div>
      <h4 class="text-light mb-2"><?php esc_html_e( 'تسليم الامتحان؟', 'examhub' ); ?></h4>
      <p class="text-muted mb-1" id="submit-modal-answered"></p>
      <p class="text-muted small" id="submit-modal-unanswered"></p>
      <div id="submit-modal-review-warn" class="alert alert-warning small py-2" style="display:none;">
        <i class="bi bi-flag me-1"></i>
        <?php esc_html_e( 'لديك أسئلة موضوعة للمراجعة.', 'examhub' ); ?>
      </div>
      <div class="d-flex gap-2 justify-content-center mt-4">
        <button class="btn btn-ghost" id="btn-cancel-submit"><?php esc_html_e( 'مراجعة', 'examhub' ); ?></button>
        <button class="btn btn-success px-4" id="btn-confirm-submit">
          <i class="bi bi-check-circle me-1"></i>
          <?php esc_html_e( 'تسليم نهائي', 'examhub' ); ?>
        </button>
      </div>
    </div>
  </div>

  <!-- Submitting overlay -->
  <div class="eh-modal-overlay" id="submitting-overlay" style="display:none;">
    <div class="eh-modal-box text-center">
      <div class="eh-loading mb-3" style="width:40px;height:40px;border-width:3px;"></div>
      <h5 class="text-light"><?php esc_html_e( 'جاري التصحيح...', 'examhub' ); ?></h5>
    </div>
  </div>

</div><!-- #eh-exam-app -->

<?php wp_footer(); ?>
</body>
</html>

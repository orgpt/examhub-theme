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
$subject_id = (int) get_field( 'exam_subject', $exam_id );
$subject_rtl = $subject_id ? (bool) get_field( 'subject_rtl', $subject_id ) : true;
$page_dir = $subject_rtl ? 'rtl' : 'ltr';
$body_classes = 'examhub-theme dark-theme exam-mode exam-subject-' . $page_dir;
$ui = $subject_rtl ? [
    'exit_title'           => __( 'الخروج', 'examhub' ),
    'exit'                 => __( 'خروج', 'examhub' ),
    'loading_exam'         => __( 'جاري تحميل الامتحان...', 'examhub' ),
    'review'               => __( 'للمراجعة', 'examhub' ),
    'question_nav'         => __( 'التنقل بين الأسئلة', 'examhub' ),
    'previous'             => __( 'السابق', 'examhub' ),
    'skip'                 => __( 'تخطي', 'examhub' ),
    'next'                 => __( 'التالي', 'examhub' ),
    'submit'               => __( 'تسليم', 'examhub' ),
    'answered'             => __( 'أجبت', 'examhub' ),
    'current'              => __( 'الحالي', 'examhub' ),
    'unanswered'           => __( 'لم تجب', 'examhub' ),
    'saved'                => __( 'تم الحفظ', 'examhub' ),
    'submit_exam'          => __( 'تسليم الامتحان؟', 'examhub' ),
    'review_warning'       => __( 'لديك أسئلة موضوعة للمراجعة.', 'examhub' ),
    'review_action'        => __( 'مراجعة', 'examhub' ),
    'final_submit'         => __( 'تسليم نهائي', 'examhub' ),
    'grading'              => __( 'جاري التصحيح...', 'examhub' ),
] : [
    'exit_title'           => 'Exit exam',
    'exit'                 => 'Exit',
    'loading_exam'         => 'Loading exam...',
    'review'               => 'Review',
    'question_nav'         => 'Question navigator',
    'previous'             => 'Previous',
    'skip'                 => 'Skip',
    'next'                 => 'Next',
    'submit'               => 'Submit',
    'answered'             => 'Answered',
    'current'              => 'Current',
    'unanswered'           => 'Unanswered',
    'saved'                => 'Saved',
    'submit_exam'          => 'Submit exam?',
    'review_warning'       => 'You have questions marked for review.',
    'review_action'        => 'Review',
    'final_submit'         => 'Final submit',
    'grading'              => 'Grading...',
];

if ( ! $subject_rtl ) {
    $body_classes .= ' ltr en';
}

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
    'is_rtl'           => $subject_rtl,
    'i18n'             => $subject_rtl ? [
        'start_error'              => __( 'حدث خطأ في بدء الامتحان.', 'examhub' ),
        'connection_error'         => __( 'تعذر الاتصال بالخادم. يرجى التحقق من الإنترنت.', 'examhub' ),
        'point_singular'           => __( 'درجة', 'examhub' ),
        'point_plural'             => __( 'درجات', 'examhub' ),
        'true_label'               => 'صح ✓',
        'false_label'              => 'خطأ ✕',
        'matching_placeholder'     => '— اختر —',
        'essay_placeholder'        => __( 'اكتب إجابتك هنا...', 'examhub' ),
        'word_count_unit'          => __( 'كلمة', 'examhub' ),
        'exit_confirm'             => __( 'هل تريد الخروج من الامتحان؟ سيتم حفظ تقدمك.', 'examhub' ),
        'next_button'              => __( 'التالي', 'examhub' ),
        'submit_button'            => __( 'تسليم', 'examhub' ),
        'question_label'           => __( 'سؤال', 'examhub' ),
        'submit_modal_answered'    => __( 'أجبت على %1$s من %2$s سؤال', 'examhub' ),
        'submit_modal_unanswered'  => __( 'لم تجب على %s سؤال', 'examhub' ),
        'submit_error'             => __( 'حدث خطأ. حاول مجدداً.', 'examhub' ),
        'network_error'            => __( 'خطأ في الاتصال. يرجى التحقق من الإنترنت.', 'examhub' ),
        'type_mcq'                 => __( 'اختيار متعدد', 'examhub' ),
        'type_correct'             => __( 'الصحيح', 'examhub' ),
        'type_true_false'          => __( 'صح/خطأ', 'examhub' ),
        'type_fill_blank'          => __( 'اكمل', 'examhub' ),
        'type_matching'            => __( 'مطابقة', 'examhub' ),
        'type_ordering'            => __( 'ترتيب', 'examhub' ),
        'type_essay'               => __( 'مقال', 'examhub' ),
        'type_image'               => __( 'صورة', 'examhub' ),
        'type_math'                => __( 'رياضيات', 'examhub' ),
        'diff_easy'                => __( 'سهل', 'examhub' ),
        'diff_medium'              => __( 'متوسط', 'examhub' ),
        'diff_hard'                => __( 'صعب', 'examhub' ),
        'back'                     => __( 'رجوع', 'examhub' ),
    ] : [
        'start_error'              => 'There was an error starting the exam.',
        'connection_error'         => 'Unable to reach the server. Please check your internet connection.',
        'point_singular'           => 'point',
        'point_plural'             => 'points',
        'true_label'               => 'True ✓',
        'false_label'              => 'False ✕',
        'matching_placeholder'     => '— Select —',
        'essay_placeholder'        => 'Write your answer here...',
        'word_count_unit'          => 'words',
        'exit_confirm'             => 'Do you want to leave the exam? Your progress will be saved.',
        'next_button'              => 'Next',
        'submit_button'            => 'Submit',
        'question_label'           => 'Question',
        'submit_modal_answered'    => 'You answered %1$s of %2$s questions',
        'submit_modal_unanswered'  => 'You left %s questions unanswered',
        'submit_error'             => 'Something went wrong. Please try again.',
        'network_error'            => 'Connection error. Please check your internet connection.',
        'type_mcq'                 => 'Multiple choice',
        'type_correct'             => 'Correct answer',
        'type_true_false'          => 'True/False',
        'type_fill_blank'          => 'Fill in the blank',
        'type_matching'            => 'Matching',
        'type_ordering'            => 'Ordering',
        'type_essay'               => 'Essay',
        'type_image'               => 'Image',
        'type_math'                => 'Math',
        'diff_easy'                => 'Easy',
        'diff_medium'              => 'Medium',
        'diff_hard'                => 'Hard',
        'back'                     => 'Back',
    ],
];

$exam_css_ver = file_exists( EXAMHUB_DIR . '/assets/css/exam.css' )
    ? filemtime( EXAMHUB_DIR . '/assets/css/exam.css' )
    : EXAMHUB_VERSION;
$exam_js_ver = file_exists( EXAMHUB_DIR . '/assets/js/exam-engine.js' )
    ? filemtime( EXAMHUB_DIR . '/assets/js/exam-engine.js' )
    : EXAMHUB_VERSION;
$exam_ltr_js_ver = file_exists( EXAMHUB_DIR . '/assets/js/exam-engine-ltr-ui.js' )
    ? filemtime( EXAMHUB_DIR . '/assets/js/exam-engine-ltr-ui.js' )
    : EXAMHUB_VERSION;

// Enqueue exam CSS + JS
wp_enqueue_style(  'examhub-exam', EXAMHUB_ASSETS . 'css/exam.css', [], $exam_css_ver );
wp_enqueue_script( 'sortablejs', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js', [], '1.15.2', true );
wp_enqueue_script( 'examhub-exam-engine', EXAMHUB_ASSETS . 'js/exam-engine.js', [ 'jquery', 'sortablejs' ], $exam_js_ver, true );
wp_localize_script( 'examhub-exam-engine', 'examhubConfig', $js_config );

if ( ! $subject_rtl ) {
    wp_enqueue_script( 'examhub-exam-ltr-ui', EXAMHUB_ASSETS . 'js/exam-engine-ltr-ui.js', [ 'jquery', 'examhub-exam-engine' ], $exam_ltr_js_ver, true );
    wp_localize_script( 'examhub-exam-ltr-ui', 'examhubExamLtrUi', [
        'review'                 => $ui['review'],
        'previous'               => $ui['previous'],
        'next'                   => $ui['next'],
        'submit'                 => $ui['submit'],
        'skip'                   => $ui['skip'],
        'question_nav'           => $ui['question_nav'],
        'answered'               => $ui['answered'],
        'current'                => $ui['current'],
        'unanswered'             => $ui['unanswered'],
        'saved'                  => $ui['saved'],
        'submit_exam'            => $ui['submit_exam'],
        'review_warning'         => $ui['review_warning'],
        'review_action'          => $ui['review_action'],
        'final_submit'           => $ui['final_submit'],
        'grading'                => $ui['grading'],
        'point_singular'         => 'point',
        'point_plural'           => 'points',
        'type_mcq'               => 'Multiple choice',
        'type_correct'           => 'Correct answer',
        'type_true_false'        => 'True/False',
        'type_fill_blank'        => 'Fill in the blank',
        'type_matching'          => 'Matching',
        'type_ordering'          => 'Ordering',
        'type_essay'             => 'Essay',
        'type_image'             => 'Image',
        'type_math'              => 'Math',
        'diff_easy'              => 'Easy',
        'diff_medium'            => 'Medium',
        'diff_hard'              => 'Hard',
        'true_label'             => 'True ✓',
        'false_label'            => 'False ✕',
        'matching_placeholder'   => '— Select —',
        'essay_placeholder'      => 'Write your answer here...',
        'word_count_unit'        => 'words',
        'exit_confirm'           => 'Do you want to leave the exam? Your progress will be saved.',
        'submit_modal_answered'  => 'You answered %1$s of %2$s questions',
        'submit_modal_unanswered'=> 'You left %s questions unanswered',
        'question_label'         => 'Question',
        'back'                   => 'Back',
        'start_error'            => 'There was an error starting the exam.',
        'connection_error'       => 'Unable to reach the server. Please check your internet connection.',
        'submit_error'           => 'Something went wrong. Please try again.',
        'network_error'          => 'Connection error. Please check your internet connection.',
    ] );
}

// Output minimal HTML — exam engine builds UI via JS
?><!DOCTYPE html>
<html <?php language_attributes(); ?> dir="<?php echo esc_attr( $page_dir ); ?>">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title><?php the_title(); ?> — <?php bloginfo( 'name' ); ?></title>
<?php wp_head(); ?>
</head>
<body class="<?php echo esc_attr( $body_classes ); ?>">
<?php wp_body_open(); ?>

<!-- ═══════════════════════════════════════════════════════════════════
  EXAM SHELL — all content injected by exam-engine.js
══════════════════════════════════════════════════════════════════════ -->
<div id="eh-exam-app" class="eh-exam-app">

  <!-- Top bar -->
  <header class="eh-exam-header">
    <div class="eh-exam-header-inner">

      <!-- Exit button -->
      <button id="btn-exam-exit" class="btn btn-ghost btn-sm" title="<?php echo esc_attr( $ui['exit_title'] ); ?>">
        <i class="bi bi-x-lg"></i>
        <span class="d-none d-md-inline ms-1"><?php echo esc_html( $ui['exit'] ); ?></span>
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
      <p class="mt-3 text-muted"><?php echo esc_html( $ui['loading_exam'] ); ?></p>
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
            <span id="review-label"><?php echo esc_html( $ui['review'] ); ?></span>
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
      <button class="btn btn-ghost btn-sm" id="btn-q-nav-toggle" title="<?php echo esc_attr( $ui['question_nav'] ); ?>">
        <i class="bi bi-grid-3x3-gap"></i>
        <span id="answered-count" class="badge badge-accent ms-1">0</span>
      </button>

      <!-- Nav buttons -->
      <div class="d-flex gap-2 align-items-center">
        <button class="btn btn-ghost" id="btn-prev" disabled>
          <i class="bi bi-chevron-right"></i>
          <span class="d-none d-sm-inline"><?php echo esc_html( $ui['previous'] ); ?></span>
        </button>

        <?php if ( $allow_skip ) : ?>
          <button class="btn btn-ghost btn-sm" id="btn-skip">
            <?php echo esc_html( $ui['skip'] ); ?>
            <i class="bi bi-chevron-left"></i>
          </button>
        <?php endif; ?>

        <button class="btn btn-primary" id="btn-next">
          <span class="d-none d-sm-inline"><?php echo esc_html( $ui['next'] ); ?></span>
          <i class="bi bi-chevron-left"></i>
        </button>
      </div>

      <!-- Submit -->
      <button class="btn btn-success" id="btn-submit-exam">
        <i class="bi bi-check-circle me-1"></i>
        <span class="d-none d-sm-inline"><?php echo esc_html( $ui['submit'] ); ?></span>
      </button>

    </div>
  </footer>

  <!-- Question navigator panel (shown on toggle) -->
  <div class="eh-q-navigator" id="q-navigator" style="display:none;">
    <div class="eh-q-nav-header">
      <span><?php echo esc_html( $ui['question_nav'] ); ?></span>
      <button class="btn btn-ghost btn-sm" id="btn-close-nav"><i class="bi bi-x"></i></button>
    </div>
    <div class="eh-q-nav-legend">
      <span class="eh-dot answered"></span> <?php echo esc_html( $ui['answered'] ); ?>
      <span class="eh-dot current ms-3"></span> <?php echo esc_html( $ui['current'] ); ?>
      <span class="eh-dot review ms-3"></span> <?php echo esc_html( $ui['review'] ); ?>
      <span class="eh-dot unanswered ms-3"></span> <?php echo esc_html( $ui['unanswered'] ); ?>
    </div>
    <div class="eh-q-dots" id="q-dots"></div>
  </div>

  <!-- Autosave indicator -->
  <div id="autosave-indicator" class="eh-autosave-indicator" style="display:none;">
    <i class="bi bi-cloud-check me-1"></i>
    <span><?php echo esc_html( $ui['saved'] ); ?></span>
  </div>

  <!-- Submit confirm modal -->
  <div class="eh-modal-overlay" id="submit-modal" style="display:none;">
    <div class="eh-modal-box">
      <div class="eh-modal-icon text-success"><i class="bi bi-check-circle fs-1"></i></div>
      <h4 class="text-light mb-2"><?php echo esc_html( $ui['submit_exam'] ); ?></h4>
      <p class="text-muted mb-1" id="submit-modal-answered"></p>
      <p class="text-muted small" id="submit-modal-unanswered"></p>
      <div id="submit-modal-review-warn" class="alert alert-warning small py-2" style="display:none;">
        <i class="bi bi-flag me-1"></i>
        <?php echo esc_html( $ui['review_warning'] ); ?>
      </div>
      <div class="d-flex gap-2 justify-content-center mt-4">
        <button class="btn btn-ghost" id="btn-cancel-submit"><?php echo esc_html( $ui['review_action'] ); ?></button>
        <button class="btn btn-success px-4" id="btn-confirm-submit">
          <i class="bi bi-check-circle me-1"></i>
          <?php echo esc_html( $ui['final_submit'] ); ?>
        </button>
      </div>
    </div>
  </div>

  <!-- Submitting overlay -->
  <div class="eh-modal-overlay" id="submitting-overlay" style="display:none;">
    <div class="eh-modal-box text-center">
      <div class="eh-loading mb-3" style="width:40px;height:40px;border-width:3px;"></div>
      <h5 class="text-light"><?php echo esc_html( $ui['grading'] ); ?></h5>
    </div>
  </div>

</div><!-- #eh-exam-app -->

<?php wp_footer(); ?>
</body>
</html>

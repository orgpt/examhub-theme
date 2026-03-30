<?php
/**
 * ExamHub — template-parts/exam/result-screen.php
 * Result screen shown after exam submission.
 * Loaded when ?result=ID is present on a single exam.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

$result_id = (int) ( $_GET['result'] ?? 0 );
$exam_id   = get_the_ID();
$user_id   = get_current_user_id();

if ( ! $result_id ) {
    wp_redirect( get_permalink( $exam_id ) );
    exit;
}

// Ownership check
if ( ! examhub_verify_result_ownership( $result_id, $user_id ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'ليس لديك صلاحية الوصول لهذه النتيجة.', 'examhub' ), 403 );
}

// Result data
$score      = (float) get_field( 'score',        $result_id );
$total      = (float) get_field( 'total_points', $result_id );
$pct        = (float) get_field( 'percentage',   $result_id );
$passed     = (bool)  get_field( 'passed',        $result_id );
$time_sec   = (int)   get_field( 'time_taken_seconds', $result_id );
$xp_earned  = (int)   get_field( 'xp_earned',    $result_id );
$attempt    = (int)   get_field( 'attempt_number', $result_id );
$status     = get_field( 'result_status', $result_id );

$grading    = get_post_meta( $result_id, '_eh_grading', true );
if ( ! is_array( $grading ) ) {
    $answers_json = get_field( 'answers_json', $result_id );
    $answers_data = json_decode( $answers_json, true ) ?: [];
    $grading      = $answers_data['_grading'] ?? [];
}
$show_exp   = (bool) get_field( 'show_explanation', $exam_id );
$sub        = examhub_get_user_subscription_status( $user_id );
$can_explain = $show_exp && $sub['explanation_access'];

// Exam meta
$pass_pct   = (float) ( get_field( 'pass_percentage', $exam_id ) ?: 50 );
$subject_id = (int) get_field( 'exam_subject', $exam_id );
$subject_color = $subject_id ? get_field( 'subject_color', $subject_id ) ?: '#4361ee' : '#4361ee';
$duration_sec = (int) get_field( 'exam_duration_minutes', $exam_id ) * 60;

// Score circle color
$score_color = $pct >= $pass_pct ? 'var(--eh-success)' : 'var(--eh-danger)';
$pass_label  = $passed ? __( 'ناجح', 'examhub' ) : __( 'راسب', 'examhub' );
$status_label = $status === 'timed_out' ? __( 'انتهى الوقت', 'examhub' ) : __( 'تم التسليم', 'examhub' );
$status_class = $status === 'timed_out' ? 'text-warning' : 'text-success';

if ( ! function_exists( 'examhub_result_is_correct' ) ) {
    function examhub_result_is_correct( $detail ) {
        if ( array_key_exists( 'is_correct', $detail ) ) {
            return $detail['is_correct'];
        }

        return $detail['correct'] ?? false;
    }
}

if ( ! function_exists( 'examhub_result_answer_label' ) ) {
    function examhub_result_answer_label( $answer, $type, $q_id ) {
        if ( $answer === null || $answer === '' || $answer === [] ) {
            return null;
        }

        switch ( $type ) {
            case 'mcq':
            case 'correct':
            case 'image':
                $choices = get_field( 'answers', $q_id );
                if ( is_array( $choices ) ) {
                    foreach ( $choices as $idx => $choice ) {
                        $choice_text = $choice['answer_text'] ?? '';
                        if ( (string) $answer === (string) $idx || (string) $answer === md5( $choice_text . $q_id ) ) {
                            return $choice_text;
                        }
                    }
                }
                return is_array( $answer ) ? ( $answer['text'] ?? implode( ' / ', array_filter( $answer ) ) ) : (string) $answer;

            case 'true_false':
                return $answer === 'true' ? __( 'صح', 'examhub' ) : __( 'خطأ', 'examhub' );

            default:
                return is_array( $answer ) ? implode( ' / ', array_map( 'strval', $answer ) ) : (string) $answer;
        }
    }
}

if ( ! function_exists( 'examhub_result_correct_answer_label' ) ) {
    function examhub_result_correct_answer_label( $answer, $type, $q_id ) {
        if ( $answer === null || $answer === '' || $answer === [] ) {
            return null;
        }

        if ( in_array( $type, [ 'mcq', 'correct', 'image' ], true ) && is_array( $answer ) ) {
            return $answer['text'] ?? implode( ' / ', array_filter( $answer ) );
        }

        return examhub_result_answer_label( $answer, $type, $q_id );
    }
}

// Time format
if ( $time_sec < 0 ) {
    $time_sec = 0;
}
if ( $duration_sec > 0 && $time_sec > ( $duration_sec * 4 ) ) {
    $time_sec = $duration_sec;
}
$time_h   = floor( $time_sec / 3600 );
$time_min = floor( ( $time_sec % 3600 ) / 60 );
$time_s   = $time_sec % 60;
$time_display = $time_h > 0 ? sprintf( '%d:%02d:%02d', $time_h, $time_min, $time_s ) : sprintf( '%d:%02d', $time_min, $time_s );

if ( $status === 'timed_out' && $duration_sec > 0 && $time_sec < $duration_sec ) {
    $status_label = __( 'تم التسليم', 'examhub' );
    $status_class = 'text-success';
}

// Build analytics from grading details
$subject_stats = []; // subject_id => [correct, total]
$lesson_stats  = []; // lesson_id => [correct, total]
$diff_stats    = [ 'easy' => [0,0], 'medium' => [0,0], 'hard' => [0,0] ];
$wrong_qs = [];

if ( is_array( $grading ) ) {
    foreach ( $grading as $q_id => $d ) {
        $is_correct = examhub_result_is_correct( $d );
        $sid = $d['subject_id'] ?? 0;
        $lid = $d['lesson_id']  ?? 0;
        $dif = $d['difficulty'] ?? 'medium';

        if ( $sid ) {
            $subject_stats[ $sid ] = $subject_stats[ $sid ] ?? [0,0];
            $subject_stats[ $sid ][1]++;
            if ( $is_correct ) $subject_stats[ $sid ][0]++;
        }
        if ( $lid ) {
            $lesson_stats[ $lid ] = $lesson_stats[ $lid ] ?? [0,0];
            $lesson_stats[ $lid ][1]++;
            if ( $is_correct ) $lesson_stats[ $lid ][0]++;
        }
        if ( isset( $diff_stats[ $dif ] ) ) {
            $diff_stats[ $dif ][1]++;
            if ( $is_correct ) $diff_stats[ $dif ][0]++;
        }
        if ( ! $is_correct ) {
            $wrong_qs[] = $d;
        }
    }
}

// Detect weak lessons
$weak_lessons = [];
foreach ( $lesson_stats as $lid => $stat ) {
    if ( $stat[1] > 0 && ( $stat[0] / $stat[1] ) < 0.6 ) {
        $weak_lessons[ $lid ] = $stat;
    }
}

get_header();
?>

<div class="container" style="max-width:860px; padding-top:2.5rem; padding-bottom:4rem;">

  <!-- ─── Score Card ──────────────────────────────────────────────────── -->
  <div class="card mb-4 text-center" style="border-top: 3px solid <?php echo esc_attr( $score_color ); ?>">
    <div class="card-body p-4 p-md-5">

      <!-- Pass/Fail pill -->
      <div class="mb-3">
        <span class="badge fs-6 px-4 py-2" style="background: <?php echo $passed ? 'var(--eh-success-bg)' : 'var(--eh-danger-bg)'; ?>; color: <?php echo esc_attr( $score_color ); ?>; border: 1px solid <?php echo esc_attr( $score_color ); ?>40;">
          <?php if ( $passed ) : ?>
            <i class="bi bi-check-circle-fill me-1"></i>
          <?php else : ?>
            <i class="bi bi-x-circle-fill me-1"></i>
          <?php endif; ?>
          <?php echo esc_html( $pass_label ); ?>
        </span>
      </div>

      <!-- Score circle (SVG) -->
      <div class="d-flex justify-content-center mb-4">
        <?php
        $r = 56; $cx = 70; $cy = 70;
        $circumference = 2 * M_PI * $r;
        $dash = ( $pct / 100 ) * $circumference;
        $gap  = $circumference - $dash;
        ?>
        <svg width="140" height="140" viewBox="0 0 140 140">
          <circle cx="<?php echo $cx; ?>" cy="<?php echo $cy; ?>" r="<?php echo $r; ?>"
            fill="none" stroke="var(--eh-bg-tertiary)" stroke-width="10"/>
          <circle cx="<?php echo $cx; ?>" cy="<?php echo $cy; ?>" r="<?php echo $r; ?>"
            fill="none" stroke="<?php echo esc_attr( $score_color ); ?>" stroke-width="10"
            stroke-linecap="round"
            stroke-dasharray="<?php echo $dash; ?> <?php echo $gap; ?>"
            transform="rotate(-90 <?php echo $cx; ?> <?php echo $cy; ?>)"
            style="transition: stroke-dasharray 1s ease;"/>
          <text x="<?php echo $cx; ?>" y="<?php echo $cy - 6; ?>" text-anchor="middle"
            fill="<?php echo esc_attr( $score_color ); ?>" font-size="24" font-weight="800"
            font-family="Cairo, sans-serif">
            <?php echo number_format( $pct, 0 ); ?>%
          </text>
          <text x="<?php echo $cx; ?>" y="<?php echo $cy + 14; ?>" text-anchor="middle"
            fill="var(--eh-text-muted)" font-size="11"
            font-family="Cairo, sans-serif">
            <?php printf( '%s / %s', number_format( $score, 0 ), number_format( $total, 0 ) ); ?>
          </text>
        </svg>
      </div>

      <!-- Exam title -->
      <h2 class="h4 fw-bold text-light mb-1"><?php the_title(); ?></h2>
      <p class="text-muted small mb-3">
        <?php printf( esc_html__( 'المحاولة رقم %d', 'examhub' ), $attempt ); ?>
        · <span class="<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
      </p>

      <!-- Stats row -->
      <div class="row g-3 justify-content-center mb-4">
        <div class="col-6 col-sm-3">
          <div class="eh-stat-card flex-column text-center p-2 small">
            <div class="stat-icon icon-success mx-auto mb-1">
              <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="stat-value" style="font-size:1.2rem;"><?php echo is_array( $grading ) ? count( array_filter( $grading, 'examhub_result_is_correct' ) ) : '--'; ?></div>
            <div class="stat-label"><?php esc_html_e( 'صحيح', 'examhub' ); ?></div>
          </div>
        </div>
        <div class="col-6 col-sm-3">
          <div class="eh-stat-card flex-column text-center p-2 small">
            <div class="stat-icon icon-danger mx-auto mb-1">
              <i class="bi bi-x-circle-fill"></i>
            </div>
            <div class="stat-value" style="font-size:1.2rem;"><?php echo count( $wrong_qs ); ?></div>
            <div class="stat-label"><?php esc_html_e( 'خطأ', 'examhub' ); ?></div>
          </div>
        </div>
        <div class="col-6 col-sm-3">
          <div class="eh-stat-card flex-column text-center p-2 small">
            <div class="stat-icon icon-warning mx-auto mb-1">
              <i class="bi bi-clock-fill"></i>
            </div>
            <div class="stat-value" style="font-size:1.2rem;"><?php echo esc_html( $time_display ); ?></div>
            <div class="stat-label"><?php esc_html_e( 'الوقت', 'examhub' ); ?></div>
          </div>
        </div>
        <div class="col-6 col-sm-3">
          <div class="eh-stat-card flex-column text-center p-2 small">
            <div class="stat-icon icon-accent mx-auto mb-1">
              <i class="bi bi-lightning-fill"></i>
            </div>
            <div class="stat-value" style="font-size:1.2rem; color:var(--eh-accent);">+<?php echo $xp_earned; ?></div>
            <div class="stat-label">XP</div>
          </div>
        </div>
      </div>

      <!-- Action buttons -->
      <div class="d-flex gap-2 flex-wrap justify-content-center">
        <a href="<?php echo get_permalink( $exam_id ); ?>" class="btn btn-ghost">
          <i class="bi bi-arrow-repeat me-1"></i>
          <?php esc_html_e( 'إعادة الامتحان', 'examhub' ); ?>
        </a>
        <a href="<?php echo get_post_type_archive_link( 'eh_exam' ); ?>" class="btn btn-ghost">
          <i class="bi bi-grid me-1"></i>
          <?php esc_html_e( 'امتحانات أخرى', 'examhub' ); ?>
        </a>
        <a href="<?php echo home_url( '/dashboard' ); ?>" class="btn btn-primary">
          <i class="bi bi-speedometer2 me-1"></i>
          <?php esc_html_e( 'لوحة التحكم', 'examhub' ); ?>
        </a>
      </div>

    </div>
  </div>

  <!-- ─── Analytics ──────────────────────────────────────────────────── -->
  <div class="row g-3 mb-4">

    <!-- Difficulty breakdown -->
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-body p-3">
          <h6 class="eh-section-title">
            <i class="bi bi-bar-chart icon"></i>
            <?php esc_html_e( 'الأداء حسب الصعوبة', 'examhub' ); ?>
          </h6>
          <?php
          $diff_labels = [ 'easy' => 'سهل', 'medium' => 'متوسط', 'hard' => 'صعب' ];
          foreach ( $diff_stats as $diff => $stat ) :
            if ( $stat[1] === 0 ) continue;
            $pct_d = round( $stat[0] / $stat[1] * 100 );
            $color_d = $pct_d >= 70 ? 'var(--eh-success)' : ( $pct_d >= 40 ? 'var(--eh-warning)' : 'var(--eh-danger)' );
          ?>
            <div class="mb-3">
              <div class="d-flex justify-content-between small mb-1">
                <span class="text-secondary"><?php echo esc_html( $diff_labels[ $diff ] ?? $diff ); ?></span>
                <span class="fw-bold" style="color:<?php echo esc_attr( $color_d ); ?>"><?php echo $pct_d; ?>%</span>
              </div>
              <div class="progress" style="height:8px;">
                <div class="progress-bar" style="width:<?php echo $pct_d; ?>%; background:<?php echo esc_attr( $color_d ); ?>;"></div>
              </div>
              <div class="small text-muted mt-1">
                <?php printf( '%d / %d صحيح', $stat[0], $stat[1] ); ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Weak lessons -->
    <?php if ( ! empty( $weak_lessons ) ) : ?>
    <div class="col-md-6">
      <div class="card h-100" style="border-color:var(--eh-danger);">
        <div class="card-body p-3">
          <h6 class="eh-section-title">
            <i class="bi bi-exclamation-triangle icon" style="color:var(--eh-warning);"></i>
            <?php esc_html_e( 'دروس تحتاج مراجعة', 'examhub' ); ?>
          </h6>
          <?php foreach ( $weak_lessons as $lid => $stat ) :
            $lesson = get_post( $lid );
            if ( ! $lesson ) continue;
            $pct_l = round( $stat[0] / $stat[1] * 100 );
          ?>
            <div class="d-flex align-items-center justify-content-between mb-2 p-2 rounded-eh" style="background:var(--eh-bg-tertiary);">
              <span class="small text-secondary"><?php echo esc_html( $lesson->post_title ); ?></span>
              <span class="badge badge-danger"><?php echo $pct_l; ?>%</span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- ─── Question Review ──────────────────────────────────────────── -->
  <?php if ( is_array( $grading ) && ! empty( $grading ) ) : ?>
  <div class="card mb-4">
    <div class="card-body p-3">

      <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="eh-section-title mb-0">
          <i class="bi bi-list-check icon"></i>
          <?php esc_html_e( 'مراجعة الإجابات', 'examhub' ); ?>
        </h5>
        <div class="d-flex gap-2">
          <button class="btn btn-ghost btn-sm eh-filter-review" data-filter="all"><?php esc_html_e( 'الكل', 'examhub' ); ?></button>
          <button class="btn btn-ghost btn-sm eh-filter-review" data-filter="wrong"><?php esc_html_e( 'الخطأ فقط', 'examhub' ); ?></button>
        </div>
      </div>

      <div id="review-questions-list">
        <?php
        $q_num = 0;
        foreach ( $grading as $q_id => $d ) :
          $q_num++;
          $is_correct  = examhub_result_is_correct( $d );
          $q_text      = $d['question_text'] ?? '';
          $q_type      = $d['type'] ?? 'mcq';
          $user_ans    = examhub_result_answer_label( $d['user_answer'] ?? null, $q_type, $q_id );
          $correct_ans = examhub_result_correct_answer_label( $d['correct_answer'] ?? null, $q_type, $q_id );
          $explanation = $d['explanation'];
          ?>
          <div class="eh-review-item <?php echo $is_correct ? 'correct' : 'wrong'; ?>"
            data-status="<?php echo $is_correct ? 'correct' : 'wrong'; ?>">

            <div class="d-flex align-items-start gap-3 mb-2">
              <span class="flex-shrink-0">
                <?php if ( $is_correct ) : ?>
                  <i class="bi bi-check-circle-fill fs-5" style="color:var(--eh-success);"></i>
                <?php elseif ( $is_correct === null ) : ?>
                  <i class="bi bi-dash-circle-fill fs-5" style="color:var(--eh-warning);"></i>
                <?php else : ?>
                  <i class="bi bi-x-circle-fill fs-5" style="color:var(--eh-danger);"></i>
                <?php endif; ?>
              </span>
              <div class="flex-grow-1">
                <span class="small text-muted"><?php printf( __( 'س %d', 'examhub' ), $q_num ); ?></span>
                <p class="mb-2 text-light" style="font-size:.95rem;"><?php echo esc_html( $q_text ); ?></p>

                <?php if ( $user_ans !== null ) : ?>
                  <div class="small mb-1">
                    <span class="text-muted"><?php esc_html_e( 'إجابتك:', 'examhub' ); ?></span>
                    <span class="<?php echo $is_correct ? 'text-success' : 'text-danger'; ?> fw-bold">
                      <?php echo esc_html( $user_ans ); ?>
                    </span>
                  </div>
                <?php endif; ?>

                <?php if ( ! $is_correct && $correct_ans ) : ?>
                  <div class="small mb-1">
                    <span class="text-muted"><?php esc_html_e( 'الإجابة الصحيحة:', 'examhub' ); ?></span>
                    <span class="text-success fw-bold"><?php echo esc_html( $correct_ans ); ?></span>
                  </div>
                <?php endif; ?>

                <?php if ( $explanation && $can_explain ) : ?>
                  <div class="eh-explanation-block mt-2">
                    <div class="eh-explanation-label">
                      <i class="bi bi-lightbulb-fill"></i>
                      <?php esc_html_e( 'الشرح', 'examhub' ); ?>
                    </div>
                    <div class="eh-explanation-text"><?php echo wp_kses_post( $explanation ); ?></div>
                  </div>
                <?php elseif ( $explanation && ! $can_explain ) : ?>
                  <div class="mt-2 p-2 rounded-eh small" style="background:var(--eh-accent-light); border:1px solid var(--eh-accent-glow);">
                    <i class="bi bi-lock me-1 text-accent"></i>
                    <a href="<?php echo home_url( '/pricing' ); ?>" class="text-accent">
                      <?php esc_html_e( 'اشترك للاطلاع على الشرح', 'examhub' ); ?>
                    </a>
                  </div>
                <?php endif; ?>

              </div>
            </div>

          </div>
          <hr class="eh-divider">
        <?php endforeach; ?>
      </div>

    </div>
  </div>
  <?php endif; ?>

</div>

<script>
// Filter review items
document.querySelectorAll('.eh-filter-review').forEach(btn => {
  btn.addEventListener('click', function() {
    const filter = this.dataset.filter;
    document.querySelectorAll('.eh-review-item').forEach(item => {
      item.style.display = (filter === 'all' || item.dataset.status === filter) ? '' : 'none';
    });
    document.querySelectorAll('.eh-filter-review').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
  });
});
</script>

<?php get_footer(); ?>

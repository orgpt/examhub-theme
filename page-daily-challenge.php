<?php
/**
 * Template Name: Daily Challenge
 * Shows today's daily challenge exam with streak and reward info.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( home_url( '/daily-challenge' ) ) );
    exit;
}

$user_id    = get_current_user_id();
$today      = date( 'Y-m-d' );
$streak     = (int) get_user_meta( $user_id, 'eh_streak', true );
$last_act   = get_user_meta( $user_id, 'eh_last_activity', true );
$done_today = $last_act === $today;
$xp         = (int) get_user_meta( $user_id, 'eh_xp', true );
$level      = examhub_get_user_level( $xp );

// Get today's challenge
$challenge_exam_id = examhub_get_daily_challenge();

// Daily reward claimed?
$last_reward  = get_user_meta( $user_id, 'eh_last_daily_reward', true );
$reward_claimed = $last_reward === $today;
$daily_xp     = (int) ( get_field( 'xp_daily_reward', 'option' ) ?: 10 );
$streak_xp    = (int) ( get_field( 'xp_streak_bonus_per_day', 'option' ) ?: 5 );

// Streak milestones
$milestones = [ 3 => '🌟', 7 => '🔥', 14 => '💎', 30 => '👑' ];
$next_milestone = null;
foreach ( $milestones as $days => $icon ) {
    if ( $streak < $days ) { $next_milestone = [ 'days' => $days, 'icon' => $icon, 'left' => $days - $streak ]; break; }
}

get_header();
?>

<div class="container-xl py-4">

  <div class="eh-page-header">
    <h1 class="eh-page-title">⚡ <?php esc_html_e( 'التحدي اليومي', 'examhub' ); ?></h1>
    <p class="eh-page-subtitle"><?php esc_html_e( 'تحدَّ نفسك يومياً وحافظ على سلسلتك!', 'examhub' ); ?></p>
  </div>

  <div class="row g-4">

    <!-- Left: Streak & reward -->
    <div class="col-lg-4">

      <!-- Streak card -->
      <div class="card mb-3">
        <div class="card-body text-center py-4">
          <div style="font-size:4rem;line-height:1;margin-bottom:.5rem;">🔥</div>
          <div style="font-size:3rem;font-weight:800;color:var(--eh-warning);"><?php echo $streak; ?></div>
          <div class="text-muted"><?php esc_html_e( 'يوم متواصل', 'examhub' ); ?></div>

          <?php if ( $next_milestone ) : ?>
          <div class="mt-3 p-3 rounded-eh" style="background:var(--eh-warning-bg);border:1px solid rgba(245,158,11,.3);">
            <small class="text-warning">
              <?php printf(
                esc_html__( '%d أيام أخرى للوصول لـ %s', 'examhub' ),
                $next_milestone['left'],
                $next_milestone['icon']
              ); ?>
            </small>
          </div>
          <?php endif; ?>

          <!-- Streak milestones -->
          <div class="d-flex justify-content-center gap-3 mt-3">
            <?php foreach ( $milestones as $days => $icon ) : ?>
            <div class="text-center" title="<?php echo $days; ?> أيام">
              <div style="font-size:1.5rem;<?php echo $streak >= $days ? '' : 'opacity:.3;'; ?>"><?php echo $icon; ?></div>
              <small class="text-muted"><?php echo $days; ?>y</small>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Daily reward card -->
      <div class="card mb-3">
        <div class="card-body">
          <div class="eh-section-title"><i class="bi bi-gift icon"></i><?php esc_html_e( 'المكافأة اليومية', 'examhub' ); ?></div>

          <div class="text-center py-2">
            <div class="fw-bold text-accent mb-1" style="font-size:1.5rem;">+<?php echo $daily_xp; ?> XP</div>
            <div class="text-muted small"><?php esc_html_e( 'مكافأة الدخول اليومي', 'examhub' ); ?></div>

            <?php if ( $streak > 1 ) : ?>
            <div class="mt-2 fw-bold text-warning">+<?php echo min( $streak, 7 ) * $streak_xp; ?> XP</div>
            <div class="text-muted small"><?php printf( esc_html__( 'مكافأة السلسلة (%d أيام)', 'examhub' ), $streak ); ?></div>
            <?php endif; ?>
          </div>

          <?php if ( $reward_claimed ) : ?>
          <button class="btn btn-ghost w-100 mt-2" disabled>
            <i class="bi bi-check-circle-fill text-success me-1"></i>
            <?php esc_html_e( 'تم الاستلام اليوم ✓', 'examhub' ); ?>
          </button>
          <?php else : ?>
          <button class="btn btn-primary w-100 mt-2" id="btn-claim-daily-reward">
            <i class="bi bi-lightning-fill me-1"></i>
            <?php esc_html_e( 'استلم مكافأة اليوم', 'examhub' ); ?>
          </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- XP Level -->
      <div class="card">
        <div class="card-body">
          <div class="eh-section-title"><i class="bi bi-bar-chart icon"></i><?php esc_html_e( 'مستواك', 'examhub' ); ?></div>
          <div class="eh-level-badge mb-2"><i class="bi bi-lightning-fill"></i><?php echo esc_html( $level['name'] ); ?></div>
          <div class="eh-xp-progress">
            <div class="d-flex justify-content-between mb-1">
              <small><?php echo number_format( $xp ); ?> XP</small>
              <?php if ( $level['next_level_xp'] ) : ?>
              <small class="text-muted"><?php echo number_format( $level['next_level_xp'] ); ?></small>
              <?php endif; ?>
            </div>
            <div class="progress"><div class="progress-bar" style="width:<?php echo $level['progress_pct']; ?>%"></div></div>
          </div>
        </div>
      </div>

    </div><!-- .col-lg-4 -->

    <!-- Right: Daily challenge exam -->
    <div class="col-lg-8">

      <?php if ( $challenge_exam_id ) :
        $exam_id      = $challenge_exam_id;
        $q_count      = examhub_get_exam_question_count( $exam_id );
        $duration     = (int) get_field( 'exam_duration_minutes', $exam_id );
        $subject_id   = (int) get_field( 'exam_subject', $exam_id );
        $grade_id     = (int) get_field( 'exam_grade', $exam_id );
        $xp_reward    = (int) get_field( 'exam_xp_reward', $exam_id );
        $thumb        = get_the_post_thumbnail_url( $exam_id, 'exam-thumbnail' );
        $already_done = examhub_user_has_taken_exam( $exam_id, $user_id );
        $best_result  = examhub_get_best_result( $exam_id, $user_id );
        $best_pct     = $best_result ? (float) get_field( 'percentage', $best_result ) : null;
      ?>

      <div class="card mb-4">
        <?php if ( $thumb ) : ?>
          <div style="height:200px;overflow:hidden;border-radius:var(--eh-radius-lg) var(--eh-radius-lg) 0 0;">
            <img src="<?php echo esc_url( $thumb ); ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
          </div>
        <?php else : ?>
          <div style="height:120px;background:linear-gradient(135deg,var(--eh-accent),#7c3aed);border-radius:var(--eh-radius-lg) var(--eh-radius-lg) 0 0;display:flex;align-items:center;justify-content:center;font-size:3rem;">⚡</div>
        <?php endif; ?>

        <div class="card-body">
          <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
            <div>
              <span class="badge badge-accent mb-2"><?php esc_html_e( 'تحدي اليوم', 'examhub' ); ?></span>
              <h3 class="fw-bold"><?php echo esc_html( get_the_title( $exam_id ) ); ?></h3>
              <div class="d-flex gap-3 text-muted small flex-wrap">
                <?php if ( $grade_id ) : ?><span><i class="bi bi-mortarboard me-1"></i><?php echo esc_html( get_the_title( $grade_id ) ); ?></span><?php endif; ?>
                <?php if ( $subject_id ) : ?><span><i class="bi bi-book me-1"></i><?php echo esc_html( get_the_title( $subject_id ) ); ?></span><?php endif; ?>
                <span><i class="bi bi-question-circle me-1"></i><?php echo $q_count; ?> <?php esc_html_e( 'سؤال', 'examhub' ); ?></span>
                <?php if ( $duration ) : ?><span><i class="bi bi-clock me-1"></i><?php echo $duration; ?> <?php esc_html_e( 'دقيقة', 'examhub' ); ?></span><?php endif; ?>
                <span class="text-accent"><i class="bi bi-lightning-fill me-1"></i>+<?php echo $xp_reward; ?> XP</span>
              </div>
            </div>
            <?php if ( $already_done && $best_pct !== null ) : ?>
            <div class="text-center flex-shrink-0">
              <div class="fw-bold" style="font-size:1.5rem;color:<?php echo $best_pct >= 50 ? 'var(--eh-success)' : 'var(--eh-danger)'; ?>;">
                <?php echo $best_pct; ?>%
              </div>
              <small class="text-muted"><?php esc_html_e( 'أفضل نتيجة', 'examhub' ); ?></small>
            </div>
            <?php endif; ?>
          </div>

          <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo esc_url( add_query_arg( 'take', '1', get_permalink( $exam_id ) ) ); ?>"
               class="btn btn-primary btn-lg">
              <?php echo $already_done ? '🔁 ' . esc_html__( 'حاول مجدداً', 'examhub' ) : '⚡ ' . esc_html__( 'ابدأ التحدي', 'examhub' ); ?>
            </a>
            <a href="<?php echo esc_url( get_permalink( $exam_id ) ); ?>" class="btn btn-ghost">
              <?php esc_html_e( 'تفاصيل الامتحان', 'examhub' ); ?>
            </a>
          </div>
        </div>
      </div><!-- .card -->

      <?php else : ?>
      <div class="eh-empty-state card py-5">
        <div class="empty-icon">📅</div>
        <h3><?php esc_html_e( 'لا يوجد تحدي اليوم', 'examhub' ); ?></h3>
        <p class="text-muted"><?php esc_html_e( 'تحقق مجدداً غداً. سيتم إضافة تحدي جديد كل يوم.', 'examhub' ); ?></p>
        <a href="<?php echo esc_url( get_post_type_archive_link( 'eh_exam' ) ); ?>" class="btn btn-primary mx-auto mt-3" style="max-width:200px;">
          <?php esc_html_e( 'تصفح الامتحانات', 'examhub' ); ?>
        </a>
      </div>
      <?php endif; ?>

      <!-- Week streak calendar -->
      <div class="card">
        <div class="card-body">
          <div class="eh-section-title"><i class="bi bi-calendar-week icon"></i><?php esc_html_e( 'نشاطك هذا الأسبوع', 'examhub' ); ?></div>
          <div class="d-flex gap-2 justify-content-center">
            <?php
            for ( $i = 6; $i >= 0; $i-- ) :
              $day_date  = date( 'Y-m-d', strtotime( "-{$i} days" ) );
              $day_name  = date_i18n( 'D', strtotime( "-{$i} days" ) );
              // Check if user was active that day (simplified check via streak)
              $is_today  = $i === 0;
              $is_active = $i === 0 ? $done_today : false; // Could extend with a log
            ?>
            <div class="text-center">
              <div style="width:42px;height:42px;border-radius:var(--eh-radius);border:1px solid var(--eh-border);
                background:<?php echo $is_active ? 'var(--eh-success)' : ($is_today ? 'var(--eh-accent-light)' : 'var(--eh-bg-tertiary)'); ?>;
                display:flex;align-items:center;justify-content:center;margin-bottom:.35rem;
                border-color:<?php echo $is_today ? 'var(--eh-accent)' : 'var(--eh-border)'; ?>;">
                <?php echo $is_active ? '✓' : ''; ?>
              </div>
              <small class="text-muted" style="font-size:.7rem;"><?php echo $day_name; ?></small>
            </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>

    </div><!-- .col-lg-8 -->
  </div><!-- .row -->
</div><!-- .container -->

<?php get_footer(); ?>

<?php
/**
 * ExamHub - page-dashboard.php
 * Student dashboard: stats, recent exams, charts, achievements.
 * Apply to any page with slug 'dashboard'.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( home_url( '/dashboard' ) ) );
    exit;
}

$user_id    = get_current_user_id();
$user       = wp_get_current_user();
$xp         = (int) get_user_meta( $user_id, 'eh_xp', true );
$level      = examhub_get_user_level( $xp );
$streak     = (int) get_user_meta( $user_id, 'eh_streak', true );
$sub        = examhub_get_user_subscription_status( $user_id );
$analytics  = examhub_get_user_analytics( $user_id, 30 );
$remaining  = examhub_get_remaining_questions( $user_id );

// Leaderboard rank
$board      = examhub_get_leaderboard( 200 );
$my_rank    = 0;
foreach ( $board as $entry ) {
    if ( $entry['user_id'] == $user_id ) { $my_rank = $entry['rank']; break; }
}

// Badges
$user_badges  = (array) get_user_meta( $user_id, 'eh_badges', true );
$all_badges   = get_posts( [ 'post_type' => 'eh_badge', 'posts_per_page' => -1, 'post_status' => 'publish' ] );

// In-progress exams
$in_progress = get_posts( [
    'post_type'      => 'eh_result',
    'author'         => $user_id,
    'posts_per_page' => 3,
    'meta_query'     => [ [ 'key' => 'result_status', 'value' => 'in_progress' ] ],
] );

// Recent results
$recent_results = get_posts( [
    'post_type'      => 'eh_result',
    'author'         => $user_id,
    'posts_per_page' => 5,
    'meta_query'     => [
        [ 'key' => 'result_status', 'value' => ['submitted','timed_out'], 'compare' => 'IN' ],
    ],
] );

// Recommended exams (based on grade)
$user_grade  = get_user_meta( $user_id, 'eh_default_grade', true );
$user_grade_name = $user_grade ? ( get_field( 'grade_name_ar', $user_grade ) ?: get_the_title( $user_grade ) ) : '';
$recommended = get_posts( [
    'post_type'      => 'eh_exam',
    'posts_per_page' => $user_grade ? 8 : 4,
    'post_status'    => 'publish',
    'meta_query'     => $user_grade ? [ [ 'key' => 'exam_grade', 'value' => $user_grade ] ] : [],
    'orderby'        => $user_grade ? 'date' : 'rand',
    'order'          => 'DESC',
] );

get_header();
?>

<div class="container-xl">

  <div class="eh-page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div>
        <h1 class="eh-page-title">
          <?php printf( esc_html__( 'مرحباً، %s', 'examhub' ), esc_html( $user->display_name ) ); ?>
        </h1>
        <p class="eh-page-subtitle mb-0">
          <?php esc_html_e( 'لوحة التحكم الخاصة بك', 'examhub' ); ?>
        </p>
      </div>
      <!-- Subscription status -->
      <?php if ( $sub['state'] === 'subscribed' && $sub['expires_at'] ) : ?>
        <div class="badge py-2 px-3" style="background:var(--eh-success-bg); color:var(--eh-success); border:1px solid rgba(34,197,94,.3); font-size:.85rem;">
          <i class="bi bi-star-fill me-1"></i>
          <?php echo esc_html( $sub['plan_name'] ); ?>
          · <?php printf( esc_html__( 'ينتهي بعد %d يوم', 'examhub' ), $sub['days_left'] ); ?>
        </div>
      <?php elseif ( $sub['state'] === 'free' ) : ?>
        <a href="<?php echo home_url( '/pricing' ); ?>" class="btn btn-primary">
          <i class="bi bi-star me-1"></i>
          <?php esc_html_e( 'ترقية الحساب', 'examhub' ); ?>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- إحصائيات -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="eh-stat-card">
        <div class="stat-icon icon-accent"><i class="bi bi-clipboard-check-fill"></i></div>
        <div>
          <div class="stat-value"><?php echo number_format( $analytics['total_exams'] ); ?></div>
          <div class="stat-label"><?php esc_html_e( 'امتحان أديته', 'examhub' ); ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="eh-stat-card">
        <div class="stat-icon icon-success"><i class="bi bi-check-circle-fill"></i></div>
        <div>
          <div class="stat-value"><?php echo $analytics['accuracy_pct']; ?>%</div>
          <div class="stat-label"><?php esc_html_e( 'دقة الإجابات', 'examhub' ); ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="eh-stat-card">
        <div class="stat-icon icon-gold"><i class="bi bi-lightning-fill"></i></div>
        <div>
          <div class="stat-value" style="color:var(--eh-accent);"><?php echo number_format( $xp ); ?></div>
          <div class="stat-label">XP · <?php echo esc_html( $level['name'] ); ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="eh-stat-card">
        <div class="stat-icon" style="background:rgba(245,158,11,.15); color:var(--eh-warning);">🔥</div>
        <div>
          <div class="stat-value"><?php echo $streak; ?></div>
          <div class="stat-label"><?php esc_html_e( 'أيام متتالية', 'examhub' ); ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">

    <!-- العمود الأيسر -->
    <div class="col-lg-8">

      <!-- XP Level Card -->
      <div class="eh-level-card mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
          <div>
            <div class="eh-level-badge">
              <i class="bi bi-star-fill"></i>
              <?php echo esc_html( $level['name'] ); ?>
            </div>
            <div class="fw-bold text-light fs-5"><?php echo number_format( $xp ); ?> XP</div>
          </div>
          <?php if ( $my_rank ) : ?>
            <div class="text-center">
              <div class="fw-bold text-accent fs-4">#<?php echo $my_rank; ?></div>
              <small class="text-muted"><?php esc_html_e( 'ترتيبك', 'examhub' ); ?></small>
            </div>
          <?php endif; ?>
        </div>
        <?php if ( $level['next_level_xp'] ) : ?>
          <div class="eh-xp-progress">
            <div class="progress">
              <div class="progress-bar bg-accent" style="width:<?php echo $level['progress_pct']; ?>%; background:var(--eh-accent)!important;"></div>
            </div>
          </div>
          <div class="eh-xp-text d-flex justify-content-between mt-1">
            <span><?php echo esc_html( $level['name'] ); ?></span>
            <span><?php printf( '%s / %s XP', number_format( $xp ), number_format( $level['next_level_xp'] ) ); ?></span>
            <span><?php echo esc_html( $level['next_level_name'] ); ?></span>
          </div>
        <?php endif; ?>
      </div>

      <!-- Daily Progress Chart -->
      <div class="card mb-4">
        <div class="card-body p-3">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="eh-section-title mb-0">
              <i class="bi bi-graph-up-arrow icon"></i>
              <?php esc_html_e( 'النشاط اليومي (30 يوم)', 'examhub' ); ?>
            </h6>
            <span class="small text-muted"><?php printf( esc_html__( 'المعدل اليومي: %s امتحان', 'examhub' ), number_format( $analytics['total_exams'] / 30, 1 ) ); ?></span>
          </div>
          <canvas id="activity-chart" height="80"></canvas>
        </div>
      </div>

      <!-- Subject Performance -->
      <?php if ( ! empty( $analytics['subject_stats'] ) ) : ?>
      <div class="card mb-4">
        <div class="card-body p-3">
          <h6 class="eh-section-title mb-3">
            <i class="bi bi-book icon"></i>
            <?php esc_html_e( 'الأداء حسب المادة', 'examhub' ); ?>
          </h6>
          <?php foreach ( $analytics['subject_stats'] as $sid => $stat ) : ?>
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small fw-600 text-secondary"><?php echo esc_html( $stat['name'] ); ?></span>
                <div class="d-flex align-items-center gap-2">
                  <span class="small text-muted"><?php printf( '%d/%d', $stat['correct'], $stat['total'] ); ?></span>
                  <span class="badge <?php echo $stat['accuracy'] >= 70 ? 'badge-success' : ( $stat['accuracy'] >= 40 ? 'badge-warning' : 'badge-danger' ); ?>">
                    <?php echo $stat['accuracy']; ?>%
                  </span>
                </div>
              </div>
              <div class="progress" style="height:6px;">
                <div class="progress-bar"
                  style="width:<?php echo $stat['accuracy']; ?>%; background:<?php echo esc_attr( $stat['color'] ); ?>;">
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Recommended Exams -->
      <?php if ( ! empty( $recommended ) ) : ?>
      <div class="card mb-4">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="eh-section-title mb-0">
              <i class="bi bi-stars icon"></i>
              <?php
              if ( $user_grade_name ) {
                printf( esc_html__( 'امتحانات صفك: %s', 'examhub' ), esc_html( $user_grade_name ) );
              } else {
                esc_html_e( 'امتحانات مقترحة لك', 'examhub' );
              }
              ?>
            </h6>
            <a href="<?php echo get_post_type_archive_link( 'eh_exam' ); ?>" class="small text-accent">
              <?php esc_html_e( 'الكل', 'examhub' ); ?>
            </a>
          </div>
          <?php if ( ! $user_grade_name ) : ?>
            <div class="alert alert-info py-2 px-3 mb-3">
              <?php esc_html_e( 'لإظهار امتحانات صفك مباشرة، اختر صفك الدراسي من الملف الشخصي.', 'examhub' ); ?>
            </div>
          <?php endif; ?>
          <div class="row g-2">
            <?php foreach ( $recommended as $rec_exam ) :
              setup_postdata( $GLOBALS['post'] = $rec_exam );
              get_template_part( 'template-parts/cards/exam-card' );
            endforeach;
            wp_reset_postdata(); ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <!-- العمود الأيمن -->
    <div class="col-lg-4">

      <!-- Continue exams -->
      <?php if ( ! empty( $in_progress ) ) : ?>
      <div class="card mb-3" style="border-color:var(--eh-warning);">
        <div class="card-body p-3">
          <h6 class="eh-section-title mb-3">
            <i class="bi bi-pause-circle icon" style="color:var(--eh-warning);"></i>
            <?php esc_html_e( 'استكمل الامتحان', 'examhub' ); ?>
          </h6>
          <?php foreach ( $in_progress as $ip ) :
            $ip_exam_id = (int) get_field( 'result_exam_id', $ip->ID );
            $ip_exam    = get_post( $ip_exam_id );
            if ( ! $ip_exam ) continue;
          ?>
            <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded-eh" style="background:var(--eh-bg-tertiary);">
              <i class="bi bi-play-circle-fill text-warning"></i>
              <div class="flex-grow-1 overflow-hidden">
                <div class="small fw-bold text-light text-truncate"><?php echo esc_html( $ip_exam->post_title ); ?></div>
              </div>
              <a href="<?php echo add_query_arg( 'take', 1, get_permalink( $ip_exam_id ) ); ?>" class="btn btn-warning btn-sm flex-shrink-0">
                <?php esc_html_e( 'استكمل', 'examhub' ); ?>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Free limit -->
      <?php if ( $sub['state'] === 'free' ) : ?>
      <div class="card mb-3" style="border-color:var(--eh-accent-glow);">
        <div class="card-body p-3 text-center">
          <div class="fs-3 fw-bold text-accent"><?php echo $remaining; ?></div>
          <div class="small text-muted mb-2"><?php esc_html_e( 'امتحان متبقي اليوم', 'examhub' ); ?></div>
          <div class="progress mb-3" style="height:6px;">
            <div class="progress-bar" style="width:<?php echo ( $remaining / max( 1, (int) ( $sub['exams_limit'] ?? $sub['questions_limit'] ?? 1 ) ) ) * 100; ?>%; background:var(--eh-accent)!important;"></div>
          </div>
          <a href="<?php echo home_url( '/pricing' ); ?>" class="btn btn-primary btn-sm w-100">
            <i class="bi bi-star me-1"></i>
            <?php esc_html_e( 'امتحانات غير محدودة', 'examhub' ); ?>
          </a>
        </div>
      </div>
      <?php endif; ?>

      <!-- Weak subjects -->
      <?php if ( ! empty( $analytics['weak_subjects'] ) ) : ?>
      <div class="card mb-3" style="border-color:rgba(239,68,68,.3);">
        <div class="card-body p-3">
          <h6 class="eh-section-title mb-3">
            <i class="bi bi-exclamation-triangle icon" style="color:var(--eh-warning);"></i>
            <?php esc_html_e( 'نقاط الضعف', 'examhub' ); ?>
          </h6>
          <?php foreach ( $analytics['weak_subjects'] as $sid => $stat ) : ?>
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="small text-secondary"><?php echo esc_html( $stat['name'] ); ?></span>
              <span class="badge badge-danger"><?php echo $stat['accuracy']; ?>%</span>
            </div>
          <?php endforeach; ?>
          <a href="<?php echo home_url( '/dashboard?tab=weak' ); ?>" class="btn btn-ghost btn-sm w-100 mt-1">
            <i class="bi bi-lightning me-1"></i>
            <?php esc_html_e( 'ابدأ امتحان نقاط الضعف', 'examhub' ); ?>
          </a>
        </div>
      </div>
      <?php endif; ?>

      <!-- Achievements (badges) -->
      <div class="card mb-3">
        <div class="card-body p-3">
          <h6 class="eh-section-title mb-3">
            <i class="bi bi-award-fill icon" style="color:var(--eh-gold);"></i>
            <?php esc_html_e( 'الإنجازات', 'examhub' ); ?>
          </h6>
          <div class="row g-2">
            <?php foreach ( array_slice( $all_badges, 0, 8 ) as $badge ) :
              $has = in_array( $badge->ID, $user_badges );
              $icon = get_field( 'badge_icon', $badge->ID );
            ?>
              <div class="col-3">
                <div class="eh-achievement-badge <?php echo $has ? '' : 'locked'; ?>" title="<?php echo esc_attr( $badge->post_title ); ?>">
                  <?php if ( $icon ) : ?>
                    <img src="<?php echo esc_url( $icon ); ?>" alt="<?php echo esc_attr( $badge->post_title ); ?>">
                  <?php else : ?>
                    <i class="bi bi-award-fill" style="font-size:2rem; color:<?php echo $has ? 'var(--eh-gold)' : 'var(--eh-text-disabled)'; ?>;"></i>
                  <?php endif; ?>
                  <span class="badge-name"><?php echo esc_html( mb_substr( $badge->post_title, 0, 8 ) ); ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ( count( $all_badges ) > 8 ) : ?>
            <a href="<?php echo home_url( '/achievements' ); ?>" class="btn btn-ghost btn-sm w-100 mt-2">
              <?php printf( esc_html__( 'عرض الكل (%d)', 'examhub' ), count( $all_badges ) ); ?>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Recent results -->
      <?php if ( ! empty( $recent_results ) ) : ?>
      <div class="card">
        <div class="card-body p-3">
          <h6 class="eh-section-title mb-3">
            <i class="bi bi-clock-history icon"></i>
            <?php esc_html_e( 'آخر النتائج', 'examhub' ); ?>
          </h6>
          <?php foreach ( $recent_results as $result ) :
            $res_exam_id = (int) get_field( 'result_exam_id', $result->ID );
            $res_pct     = (float) get_field( 'percentage', $result->ID );
            $res_passed  = (bool)  get_field( 'passed',     $result->ID );
            $res_exam    = get_post( $res_exam_id );
            if ( ! $res_exam ) continue;
          ?>
            <div class="d-flex align-items-center gap-2 mb-2">
              <i class="bi bi-circle-fill small" style="color:<?php echo $res_passed ? 'var(--eh-success)' : 'var(--eh-danger)'; ?>"></i>
              <div class="flex-grow-1 overflow-hidden">
                <a href="<?php echo get_permalink( $res_exam_id ) . '?result=' . $result->ID; ?>"
                  class="small text-secondary text-truncate d-block"
                  style="max-width:180px;">
                  <?php echo esc_html( $res_exam->post_title ); ?>
                </a>
              </div>
              <span class="small fw-bold <?php echo $res_passed ? 'text-success' : 'text-danger'; ?>">
                <?php echo number_format( $res_pct, 0 ); ?>%
              </span>
            </div>
          <?php endforeach; ?>
          <a href="<?php echo home_url( '/my-results' ); ?>" class="btn btn-ghost btn-sm w-100 mt-1">
            <?php esc_html_e( 'كل النتائج', 'examhub' ); ?>
          </a>
        </div>
      </div>
      <?php endif; ?>

    </div>

  </div><!-- .row -->
</div>

<!-- Chart.js activity chart data -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const ctx = document.getElementById('activity-chart');
  if (!ctx || typeof Chart === 'undefined') return;

  const activity = <?php echo json_encode( $analytics['daily_activity'] ); ?>;
  const labels   = Object.keys(activity).map(d => {
    const date = new Date(d);
    return date.toLocaleDateString('ar-EG', { month: 'short', day: 'numeric' });
  });
  const data = Object.values(activity);

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        data,
        backgroundColor: 'rgba(67, 97, 238, 0.5)',
        borderColor: 'rgba(67, 97, 238, 0.8)',
        borderWidth: 1,
        borderRadius: 4,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        x: {
          ticks: { color: '#6270a0', font: { size: 10 }, maxTicksLimit: 10 },
          grid: { color: 'rgba(42, 48, 80, 0.6)' },
        },
        y: {
          ticks: { color: '#6270a0', stepSize: 1 },
          grid: { color: 'rgba(42, 48, 80, 0.6)' },
          min: 0,
        }
      }
    }
  });
});
</script>

<?php get_footer(); ?>


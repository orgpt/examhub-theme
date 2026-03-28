<?php
/**
 * Template Name: Leaderboard
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

if ( ! get_field( 'leaderboard_enabled', 'option' ) ) {
    wp_redirect( home_url() ); exit;
}

$current_user_id = get_current_user_id();
$sub = $current_user_id ? examhub_get_user_subscription_status( $current_user_id ) : null;

// Check access
if ( $sub && ! $sub['leaderboard_access'] ) {
    // Show upgrade prompt
}

// Tabs: global, grade
$tab      = sanitize_text_field( $_GET['tab']      ?? 'global' );
$grade_id = (int) ( $_GET['grade_id'] ?? 0 );
$limit    = (int) ( get_field( 'leaderboard_top_count', 'option' ) ?: 50 );

$board    = examhub_get_leaderboard( $tab, $grade_id, $limit );
$grades   = get_posts( [ 'post_type' => 'eh_grade', 'posts_per_page' => 50, 'orderby' => 'title', 'order' => 'ASC' ] );
$my_rank  = $current_user_id ? examhub_get_user_rank( $current_user_id ) : null;

get_header();
?>

<div class="container-xl py-4">

  <div class="eh-page-header d-flex align-items-center justify-content-between">
    <div>
      <h1 class="eh-page-title"><i class="bi bi-trophy-fill text-warning me-2"></i><?php esc_html_e( 'المتصدرون', 'examhub' ); ?></h1>
      <p class="eh-page-subtitle"><?php esc_html_e( 'أفضل الطلاب هذا الشهر', 'examhub' ); ?></p>
    </div>
    <?php if ( $my_rank ) : ?>
    <div class="text-center">
      <div class="fw-bold text-accent" style="font-size:1.5rem;">#<?php echo $my_rank; ?></div>
      <small class="text-muted"><?php esc_html_e( 'ترتيبك', 'examhub' ); ?></small>
    </div>
    <?php endif; ?>
  </div>

  <!-- Filter row -->
  <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
    <nav class="nav nav-pills">
      <a class="nav-link <?php echo $tab === 'global' ? 'active' : ''; ?>" href="?tab=global">
        🌐 <?php esc_html_e( 'عام', 'examhub' ); ?>
      </a>
      <a class="nav-link <?php echo $tab === 'grade' ? 'active' : ''; ?>" href="?tab=grade">
        📚 <?php esc_html_e( 'حسب الصف', 'examhub' ); ?>
      </a>
    </nav>

    <?php if ( $tab === 'grade' ) : ?>
    <form method="get" class="d-flex gap-2">
      <input type="hidden" name="tab" value="grade">
      <select name="grade_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:180px;">
        <option value=""><?php esc_html_e( '— اختر الصف —', 'examhub' ); ?></option>
        <?php foreach ( $grades as $g ) : ?>
          <option value="<?php echo $g->ID; ?>" <?php selected( $grade_id, $g->ID ); ?>>
            <?php echo esc_html( get_field( 'grade_name_ar', $g->ID ) ?: $g->post_title ); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>
  </div>

  <!-- Leaderboard -->
  <div class="card">
    <div class="card-body p-0">

      <?php if ( empty( $board ) ) : ?>
        <div class="eh-empty-state">
          <div class="empty-icon"><i class="bi bi-trophy"></i></div>
          <h3><?php esc_html_e( 'لا توجد بيانات بعد', 'examhub' ); ?></h3>
          <p><?php esc_html_e( 'أجرِ امتحانات لتظهر هنا!', 'examhub' ); ?></p>
        </div>
      <?php else : ?>

        <!-- Top 3 podium -->
        <?php if ( count( $board ) >= 3 ) : ?>
        <div class="d-flex justify-content-center align-items-end gap-3 py-4 px-3" style="background:var(--eh-bg-secondary);border-bottom:1px solid var(--eh-border);">
          <?php
          $podium = array_slice( $board, 0, 3 );
          $order  = [1, 0, 2]; // 2nd, 1st, 3rd visual order
          $heights= [ '80px', '110px', '60px' ];
          $colors = [ 'var(--eh-silver)', 'var(--eh-gold)', 'var(--eh-bronze)' ];
          foreach ( $order as $idx ) :
            $p = $podium[$idx] ?? null;
            if ( ! $p ) continue;
          ?>
          <div class="text-center" style="flex:1;max-width:140px;">
            <img src="<?php echo esc_url($p['avatar']); ?>" alt="" class="rounded-circle mb-2 border" style="width:60px;height:60px;border-color:<?php echo $colors[$idx]; ?>!important;border-width:3px!important;">
            <div class="fw-bold small" style="color:<?php echo $colors[$idx]; ?>">#<?php echo $p['rank']; ?></div>
            <div class="fw-bold small"><?php echo esc_html($p['name']); ?></div>
            <div class="small text-muted"><?php echo number_format($p['xp']); ?> XP</div>
            <div style="background:<?php echo $colors[$idx]; ?>;height:<?php echo $heights[$idx]; ?>;border-radius:var(--eh-radius-sm) var(--eh-radius-sm) 0 0;margin-top:0.5rem;opacity:.7;"></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Full list -->
        <div class="p-3">
          <?php foreach ( $board as $row ) :
            $rank_class = $row['rank'] === 1 ? 'rank-1 gold' : ( $row['rank'] === 2 ? 'rank-2 silver' : ( $row['rank'] === 3 ? 'rank-3 bronze' : '' ) );
            $is_current = $row['is_current'] ?? false;
          ?>
          <div class="eh-leaderboard-item <?php echo $rank_class; ?> <?php echo $is_current ? 'is-current-user' : ''; ?>">
            <div class="lb-rank <?php echo str_replace('rank-', '', $rank_class); ?>">
              <?php
              if ( $row['rank'] === 1 ) echo '🥇';
              elseif ( $row['rank'] === 2 ) echo '🥈';
              elseif ( $row['rank'] === 3 ) echo '🥉';
              else echo '#' . $row['rank'];
              ?>
            </div>
            <img src="<?php echo esc_url($row['avatar']); ?>" class="lb-avatar" alt="">
            <div class="flex-1">
              <div class="lb-name">
                <?php echo esc_html($row['name']); ?>
                <?php if ( $is_current ) echo ' <span class="badge badge-accent ms-1">' . esc_html__('أنت','examhub') . '</span>'; ?>
              </div>
              <?php if ( $row['grade'] ) : ?>
                <div class="lb-grade"><?php echo esc_html($row['grade']); ?></div>
              <?php endif; ?>
            </div>
            <div class="lb-xp ms-auto text-end">
              <div class="lb-xp-val"><?php echo number_format($row['xp']); ?> XP</div>
              <div class="lb-xp-label"><?php echo esc_html($row['level']); ?></div>
            </div>
            <?php if ( $row['streak'] > 0 ) : ?>
            <div class="text-warning ms-2" title="<?php esc_attr_e('سلسلة الأيام','examhub'); ?>">
              🔥 <?php echo $row['streak']; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

      <?php endif; ?>
    </div>
  </div><!-- .card -->

  <?php if ( ! $current_user_id ) : ?>
  <div class="text-center mt-4">
    <p class="text-muted"><?php esc_html_e( 'سجّل الدخول وابدأ الامتحانات لتظهر في المتصدرين!', 'examhub' ); ?></p>
    <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="btn btn-primary">
      <?php esc_html_e( 'سجّل الدخول', 'examhub' ); ?>
    </a>
  </div>
  <?php endif; ?>

</div><!-- .container-xl -->

<?php get_footer(); ?>

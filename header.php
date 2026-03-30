<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="theme-color" content="#0d0f14">
<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
// Don't show navbar on distraction-free exam mode
$is_exam_mode = is_singular( 'eh_exam' ) && get_query_var( 'exam_mode' ) === 'focus';
?>

<?php if ( ! $is_exam_mode ) : ?>
<nav class="eh-navbar navbar navbar-expand-lg" id="eh-main-navbar">
  <div class="container-xl">

    <!-- Logo -->
    <a class="navbar-brand" href="<?php echo home_url(); ?>">
      <?php
      $logo_url = get_field( 'site_logo_dark', 'option' );
      if ( $logo_url ) : ?>
        <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php bloginfo( 'name' ); ?>" height="36">
      <?php else : ?>
        <span>المراجعة النهائية</span>
      <?php endif; ?>
    </a>

    <!-- Mobile toggle -->
    <button class="navbar-toggler border-0" type="button"
      data-bs-toggle="collapse" data-bs-target="#ehNavCollapse"
      aria-expanded="false" aria-label="<?php esc_attr_e( 'تبديل القائمة', 'examhub' ); ?>">
      <i class="bi bi-list text-light fs-4"></i>
    </button>

    <div class="collapse navbar-collapse" id="ehNavCollapse">

      <!-- Main navigation -->
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-1">
        <li class="nav-item">
          <a class="nav-link <?php echo is_home() || is_front_page() ? 'active' : ''; ?>" href="<?php echo home_url(); ?>">
            <i class="bi bi-house me-1"></i><?php esc_html_e( 'الرئيسية', 'examhub' ); ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo is_post_type_archive( 'eh_exam' ) ? 'active' : ''; ?>" href="<?php echo get_post_type_archive_link( 'eh_exam' ); ?>">
            <i class="bi bi-clipboard-check me-1"></i><?php esc_html_e( 'الامتحانات', 'examhub' ); ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?php echo home_url( '/leaderboard' ); ?>">
            <i class="bi bi-trophy me-1"></i><?php esc_html_e( 'المتصدرون', 'examhub' ); ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?php echo home_url( '/pricing' ); ?>">
            <i class="bi bi-star me-1"></i><?php esc_html_e( 'الاشتراك', 'examhub' ); ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?php echo home_url( '/affiliate' ); ?>">
            <i class="bi bi-megaphone me-1"></i><?php esc_html_e( 'أفلييت', 'examhub' ); ?>
          </a>
        </li>
      </ul>

      <!-- Right side: user info -->
      <div class="d-flex align-items-center gap-2">

        <?php if ( is_user_logged_in() ) :
          $user_id   = get_current_user_id();
          $xp        = (int) get_user_meta( $user_id, 'eh_xp', true );
          $sub       = examhub_get_user_subscription_status( $user_id );
          ?>

          <div class="eh-desktop-quick-links d-none d-xl-flex align-items-center gap-2">
            <a href="<?php echo esc_url( home_url( '/dashboard' ) ); ?>" class="eh-quick-link">
              <i class="bi bi-speedometer2"></i><span><?php esc_html_e( 'لوحة التحكم', 'examhub' ); ?></span>
            </a>
            <a href="<?php echo esc_url( home_url( '/my-results' ) ); ?>" class="eh-quick-link">
              <i class="bi bi-bar-chart"></i><span><?php esc_html_e( 'نتائجي', 'examhub' ); ?></span>
            </a>
            <a href="<?php echo esc_url( home_url( '/profile' ) ); ?>" class="eh-quick-link">
              <i class="bi bi-person"></i><span><?php esc_html_e( 'الملف الشخصي', 'examhub' ); ?></span>
            </a>
            <a href="<?php echo esc_url( home_url( '/profile/?tab=affiliate' ) ); ?>" class="eh-quick-link">
              <i class="bi bi-megaphone"></i><span><?php esc_html_e( 'أفلييت', 'examhub' ); ?></span>
            </a>
          </div>

          <!-- XP badge -->
          <span class="eh-xp-badge d-none d-md-flex align-items-center gap-1">
            <i class="bi bi-lightning-fill"></i>
            <?php echo number_format( $xp ); ?> XP
          </span>

          <!-- Streak -->
          <?php
          $streak = (int) get_user_meta( $user_id, 'eh_streak', true );
          if ( $streak > 0 ) : ?>
            <span class="eh-xp-badge d-none d-md-flex align-items-center gap-1" style="color: var(--eh-warning); border-color: rgba(245,158,11,.3); background: rgba(245,158,11,.1);">
              🔥 <?php echo $streak; ?>
            </span>
          <?php endif; ?>

          <!-- Subscription status pill -->
          <?php if ( $sub['state'] === 'subscribed' ) : ?>
            <span class="badge" style="background: rgba(34,197,94,.15); color: var(--eh-success); border: 1px solid rgba(34,197,94,.3); font-size: 0.72rem;">
              <i class="bi bi-star-fill me-1"></i><?php echo esc_html( $sub['plan_name'] ); ?>
            </span>
          <?php elseif ( $sub['state'] === 'free' ) : ?>
            <a href="<?php echo home_url( '/pricing' ); ?>" class="btn btn-sm btn-primary">
              <?php esc_html_e( 'ترقية', 'examhub' ); ?>
            </a>
          <?php endif; ?>

          <!-- Logout icon -->
          <a class="btn btn-ghost btn-sm p-2" href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" title="<?php esc_attr_e( 'تسجيل الخروج', 'examhub' ); ?>" aria-label="<?php esc_attr_e( 'تسجيل الخروج', 'examhub' ); ?>">
            <i class="bi bi-box-arrow-right"></i>
          </a>

        <?php else : ?>
          <a href="<?php echo wp_login_url( get_permalink() ); ?>" class="btn btn-ghost btn-sm">
            <i class="bi bi-person me-1"></i><?php esc_html_e( 'دخول', 'examhub' ); ?>
          </a>
          <a href="<?php echo wp_registration_url(); ?>" class="btn btn-primary btn-sm">
            <?php esc_html_e( 'إنشاء حساب', 'examhub' ); ?>
          </a>
        <?php endif; ?>

      </div>
    </div>
  </div>
</nav>

<!-- Mobile bottom nav -->
<?php if ( is_user_logged_in() ) : ?>
<nav class="eh-mobile-nav" id="eh-mobile-nav" aria-label="<?php esc_attr_e( 'التنقل السريع', 'examhub' ); ?>">
  <a href="<?php echo home_url(); ?>" class="eh-mobile-nav-item <?php echo is_front_page() ? 'active' : ''; ?>">
    <i class="bi bi-house"></i><span><?php esc_html_e( 'الرئيسية', 'examhub' ); ?></span>
  </a>
  <a href="<?php echo get_post_type_archive_link( 'eh_exam' ); ?>" class="eh-mobile-nav-item <?php echo is_post_type_archive( 'eh_exam' ) ? 'active' : ''; ?>">
    <i class="bi bi-clipboard-check"></i><span><?php esc_html_e( 'امتحانات', 'examhub' ); ?></span>
  </a>
  <a href="<?php echo home_url( '/dashboard' ); ?>" class="eh-mobile-nav-item">
    <i class="bi bi-speedometer2"></i><span><?php esc_html_e( 'لوحتي', 'examhub' ); ?></span>
  </a>
  <a href="<?php echo home_url( '/leaderboard' ); ?>" class="eh-mobile-nav-item">
    <i class="bi bi-trophy"></i><span><?php esc_html_e( 'متصدرون', 'examhub' ); ?></span>
  </a>
  <a href="<?php echo home_url( '/profile' ); ?>" class="eh-mobile-nav-item">
    <i class="bi bi-person-circle"></i><span><?php esc_html_e( 'حسابي', 'examhub' ); ?></span>
  </a>
  <a href="<?php echo home_url( '/affiliate' ); ?>" class="eh-mobile-nav-item">
    <i class="bi bi-megaphone"></i><span><?php esc_html_e( 'أفلييت', 'examhub' ); ?></span>
  </a>
</nav>
<?php endif; ?>

<?php endif; // !$is_exam_mode ?>

<main class="eh-main" id="eh-content">

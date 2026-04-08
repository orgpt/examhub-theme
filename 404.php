<?php
/**
 * ExamHub - 404 Template
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<section class="eh-404-page">
  <div class="container-xl">
    <div class="eh-404-shell">
      <div class="eh-404-glow eh-404-glow-one" aria-hidden="true"></div>
      <div class="eh-404-glow eh-404-glow-two" aria-hidden="true"></div>

      <div class="row g-4 align-items-center">
        <div class="col-lg-7">
          <div class="eh-404-content">
            <span class="eh-404-chip"><?php esc_html_e( 'الصفحة غير موجودة', 'examhub' ); ?></span>
            <div class="eh-404-code">404</div>
            <h1><?php esc_html_e( 'يبدو أن الرابط اختفى في منتصف الطريق', 'examhub' ); ?></h1>
            <p class="eh-404-lead">
              <?php esc_html_e( 'لا تقلق، ما زال بإمكانك الرجوع بسرعة إلى أهم أقسام ExamHub ومتابعة التدريب بدون أي تعقيد.', 'examhub' ); ?>
            </p>

            <div class="eh-404-actions">
              <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-primary btn-lg">
                <i class="bi bi-house-door-fill ms-2"></i>
                <?php esc_html_e( 'العودة للرئيسية', 'examhub' ); ?>
              </a>
              <a href="<?php echo esc_url( get_post_type_archive_link( 'eh_exam' ) ); ?>" class="btn btn-ghost btn-lg">
                <i class="bi bi-clipboard-check ms-2"></i>
                <?php esc_html_e( 'تصفح الامتحانات', 'examhub' ); ?>
              </a>
            </div>

            <form class="eh-404-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
              <label class="screen-reader-text" for="eh-404-search-field"><?php esc_html_e( 'ابحث في الموقع', 'examhub' ); ?></label>
              <div class="eh-404-search-box">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input
                  id="eh-404-search-field"
                  type="search"
                  class="form-control"
                  name="s"
                  value="<?php echo esc_attr( get_search_query() ); ?>"
                  placeholder="<?php esc_attr_e( 'ابحث عن امتحان، مادة، أو خطة اشتراك...', 'examhub' ); ?>"
                >
                <button type="submit" class="btn btn-primary">
                  <?php esc_html_e( 'بحث', 'examhub' ); ?>
                </button>
              </div>
            </form>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="eh-404-panel">
            <div class="eh-404-orbit" aria-hidden="true">
              <span></span>
              <span></span>
              <span></span>
            </div>

            <div class="eh-404-card-grid">
              <a class="eh-404-card" href="<?php echo esc_url( get_post_type_archive_link( 'eh_exam' ) ); ?>">
                <div class="eh-404-card-icon"><i class="bi bi-journal-check"></i></div>
                <strong><?php esc_html_e( 'بنك الامتحانات', 'examhub' ); ?></strong>
                <span><?php esc_html_e( 'ابدأ من أحدث الامتحانات والتدريبات.', 'examhub' ); ?></span>
              </a>

              <a class="eh-404-card" href="<?php echo esc_url( home_url( '/daily-challenge' ) ); ?>">
                <div class="eh-404-card-icon"><i class="bi bi-lightning-charge-fill"></i></div>
                <strong><?php esc_html_e( 'التحدي اليومي', 'examhub' ); ?></strong>
                <span><?php esc_html_e( 'جلسة سريعة للحفاظ على الاستمرارية.', 'examhub' ); ?></span>
              </a>

              <a class="eh-404-card" href="<?php echo esc_url( home_url( '/leaderboard' ) ); ?>">
                <div class="eh-404-card-icon"><i class="bi bi-trophy-fill"></i></div>
                <strong><?php esc_html_e( 'لوحة المتصدرين', 'examhub' ); ?></strong>
                <span><?php esc_html_e( 'شاهد ترتيب الطلاب والمنافسة الحالية.', 'examhub' ); ?></span>
              </a>

              <a class="eh-404-card" href="<?php echo esc_url( home_url( '/pricing' ) ); ?>">
                <div class="eh-404-card-icon"><i class="bi bi-stars"></i></div>
                <strong><?php esc_html_e( 'خطط الاشتراك', 'examhub' ); ?></strong>
                <span><?php esc_html_e( 'تعرف على المزايا المناسبة لمستواك.', 'examhub' ); ?></span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php
get_footer();

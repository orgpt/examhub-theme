<?php
/**
 * ExamHub - Affiliate landing page.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

get_header();

$ref_code      = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';
$ref_user      = $ref_code ? examhub_get_user_by_affiliate_code( $ref_code ) : null;
$is_logged_in  = is_user_logged_in();
$affiliate_url = $is_logged_in ? home_url( '/profile/?tab=affiliate' ) : wp_registration_url();

if ( ! $is_logged_in && $ref_code ) {
    $affiliate_url = add_query_arg( 'ref', $ref_code, $affiliate_url );
}
?>

<div class="container-xl py-4 eh-landing-page">
  <section class="eh-landing-hero">
    <div class="eh-landing-glow"></div>
    <span class="eh-landing-chip"><?php esc_html_e( 'Affiliate Program', 'examhub' ); ?></span>
    <h1><?php esc_html_e( 'شارك المنصة واكسب عمولة 10% على كل عملية شراء', 'examhub' ); ?></h1>
    <p>
      <?php
      if ( $ref_user ) {
          printf( esc_html__( 'تمت دعوتك من خلال %s. سجّل وابدأ استخدام المنصة، وبعد إنشاء حسابك يمكنك أنت أيضًا مشاركة رابطك الخاص.', 'examhub' ), esc_html( $ref_user->display_name ) );
      } else {
          esc_html_e( 'كل مستخدم داخل المنصة يملك رابط أفلييت خاص به. انسخ الرابط أو أرسل دعوة بالإيميل وابدأ في جمع العمولات تلقائيًا.', 'examhub' );
      }
      ?>
    </p>
    <div class="eh-landing-cta">
      <a href="<?php echo esc_url( $affiliate_url ); ?>" class="btn btn-primary btn-lg">
        <?php echo $is_logged_in ? esc_html__( 'افتح لوحة الأفلييت', 'examhub' ) : esc_html__( 'ابدأ الآن', 'examhub' ); ?>
      </a>
      <a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>" class="btn btn-ghost btn-lg">
        <?php esc_html_e( 'شاهد الاشتراكات', 'examhub' ); ?>
      </a>
    </div>
  </section>

  <section class="mt-5">
    <h2 class="eh-landing-section-title"><?php esc_html_e( 'كيف يعمل؟', 'examhub' ); ?></h2>
    <div class="row g-3">
      <div class="col-md-4">
        <div class="eh-landing-feature-card">
          <i class="bi bi-link-45deg"></i>
          <h3><?php esc_html_e( 'رابط شخصي', 'examhub' ); ?></h3>
          <p><?php esc_html_e( 'لكل عضو رابط إحالة جاهز للنسخ والمشاركة في أي مكان.', 'examhub' ); ?></p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="eh-landing-feature-card">
          <i class="bi bi-envelope-heart"></i>
          <h3><?php esc_html_e( 'دعوات بالإيميل', 'examhub' ); ?></h3>
          <p><?php esc_html_e( 'يمكنك إرسال دعوة مباشرة من حسابك مع نموذج إيميل احترافي.', 'examhub' ); ?></p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="eh-landing-feature-card">
          <i class="bi bi-percent"></i>
          <h3><?php esc_html_e( 'عمولة واضحة', 'examhub' ); ?></h3>
          <p><?php esc_html_e( 'تحصل على 10% من قيمة كل عملية شراء مؤكدة تتم عبر رابطك.', 'examhub' ); ?></p>
        </div>
      </div>
    </div>
  </section>

  <section class="eh-landing-final-cta mt-5">
    <h2><?php esc_html_e( 'ابدأ مشاركة رابطك خلال دقائق', 'examhub' ); ?></h2>
    <p><?php esc_html_e( 'النظام مدمج مباشرة داخل حسابك: رابط، دعوات، عمولات، وتنبيهات إيميل تلقائية.', 'examhub' ); ?></p>
    <div class="eh-landing-cta">
      <a href="<?php echo esc_url( $affiliate_url ); ?>" class="btn btn-primary btn-lg"><?php echo $is_logged_in ? esc_html__( 'اذهب إلى حسابي', 'examhub' ) : esc_html__( 'إنشاء حساب', 'examhub' ); ?></a>
    </div>
  </section>
</div>

<?php get_footer(); ?>

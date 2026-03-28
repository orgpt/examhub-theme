<?php
/**
 * ExamHub - page-login.php
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

if ( is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/dashboard' ) );
	exit;
}

$error_code  = isset( $_GET['auth_error'] ) ? sanitize_key( wp_unslash( $_GET['auth_error'] ) ) : '';
$notice_code = isset( $_GET['auth_notice'] ) ? sanitize_key( wp_unslash( $_GET['auth_notice'] ) ) : '';
$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/dashboard' );

$error_map = array(
	'invalid_nonce'         => __( 'الجلسة انتهت. حاول مرة أخرى.', 'examhub' ),
	'missing_fields'        => __( 'من فضلك املأ كل الحقول المطلوبة.', 'examhub' ),
	'invalid_login'         => __( 'بيانات الدخول غير صحيحة.', 'examhub' ),
	'google_not_configured' => __( 'تسجيل الدخول عبر Google غير مُعد بعد.', 'examhub' ),
	'google_failed'         => __( 'تعذر تسجيل الدخول عبر Google. حاول مرة أخرى.', 'examhub' ),
);

$notice_map = array(
	'password_reset' => __( 'تم تغيير كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.', 'examhub' ),
);

get_header();
?>

<div class="container-xl">
	<section class="eh-auth-wrap">
		<div class="eh-auth-card">
			<div class="eh-auth-brand text-center">
				<h1 class="mb-1"><?php esc_html_e( 'تسجيل الدخول', 'examhub' ); ?></h1>
				<p class="mb-0"><?php esc_html_e( 'ادخل إلى حسابك لمتابعة المراجعة النهائية', 'examhub' ); ?></p>
			</div>

			<?php if ( $error_code && isset( $error_map[ $error_code ] ) ) : ?>
				<div class="alert alert-danger"><?php echo esc_html( $error_map[ $error_code ] ); ?></div>
			<?php endif; ?>

			<?php if ( $notice_code && isset( $notice_map[ $notice_code ] ) ) : ?>
				<div class="alert alert-success"><?php echo esc_html( $notice_map[ $notice_code ] ); ?></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eh-auth-form">
				<input type="hidden" name="action" value="examhub_auth_login">
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>">
				<?php wp_nonce_field( 'examhub_auth_login', 'eh_login_nonce' ); ?>

				<div>
					<label class="form-label"><?php esc_html_e( 'البريد الإلكتروني أو اسم المستخدم', 'examhub' ); ?></label>
					<input class="form-control" type="text" name="user_login" required>
				</div>

				<div>
					<label class="form-label"><?php esc_html_e( 'كلمة المرور', 'examhub' ); ?></label>
					<input class="form-control" type="password" name="user_pass" required>
				</div>

				<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
					<label class="d-inline-flex align-items-center gap-2">
						<input type="checkbox" name="rememberme" value="1">
						<span class="small text-secondary"><?php esc_html_e( 'تذكرني', 'examhub' ); ?></span>
					</label>
					<a href="<?php echo esc_url( home_url( '/reset-password' ) ); ?>" class="small"><?php esc_html_e( 'نسيت كلمة المرور؟', 'examhub' ); ?></a>
				</div>

				<button type="submit" class="btn btn-primary w-100">
					<i class="bi bi-box-arrow-in-right me-1"></i>
					<?php esc_html_e( 'دخول', 'examhub' ); ?>
				</button>
			</form>

			<?php if ( function_exists( 'examhub_is_google_login_enabled' ) && examhub_is_google_login_enabled() ) : ?>
				<div class="eh-auth-sep"><span><?php esc_html_e( 'أو', 'examhub' ); ?></span></div>
				<a class="btn eh-google-btn w-100" href="<?php echo esc_url( add_query_arg( array( 'redirect_to' => $redirect_to ), home_url( '/google-auth-start/' ) ) ); ?>">
					<i class="bi bi-google me-2"></i><?php esc_html_e( 'المتابعة عبر Google', 'examhub' ); ?>
				</a>
			<?php endif; ?>

			<p class="eh-auth-switch mb-0">
				<?php esc_html_e( 'ليس لديك حساب؟', 'examhub' ); ?>
				<a href="<?php echo esc_url( home_url( '/register' ) ); ?>"><?php esc_html_e( 'إنشاء حساب جديد', 'examhub' ); ?></a>
			</p>
		</div>
	</section>
</div>

<?php get_footer(); ?>

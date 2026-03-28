<?php
/**
 * ExamHub - page-register.php
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

if ( is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/dashboard' ) );
	exit;
}

$error_code  = isset( $_GET['auth_error'] ) ? sanitize_key( wp_unslash( $_GET['auth_error'] ) ) : '';
$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/dashboard' );

$error_map = array(
	'registration_disabled' => __( 'تسجيل الحسابات الجديدة متوقف حالياً.', 'examhub' ),
	'invalid_nonce'         => __( 'الجلسة انتهت. حاول مرة أخرى.', 'examhub' ),
	'missing_fields'        => __( 'من فضلك املأ كل الحقول المطلوبة.', 'examhub' ),
	'invalid_email'         => __( 'البريد الإلكتروني غير صحيح.', 'examhub' ),
	'email_exists'          => __( 'هذا البريد مستخدم بالفعل.', 'examhub' ),
	'weak_password'         => __( 'كلمة المرور يجب أن تكون 8 أحرف أو أكثر.', 'examhub' ),
	'password_mismatch'     => __( 'كلمتا المرور غير متطابقتين.', 'examhub' ),
	'register_failed'       => __( 'تعذر إنشاء الحساب. حاول مرة أخرى.', 'examhub' ),
);

get_header();
?>

<div class="container-xl">
	<section class="eh-auth-wrap">
		<div class="eh-auth-card">
			<div class="eh-auth-brand text-center">
				<h1 class="mb-1"><?php esc_html_e( 'إنشاء حساب', 'examhub' ); ?></h1>
				<p class="mb-0"><?php esc_html_e( 'ابدأ رحلتك التعليمية مع المراجعة النهائية', 'examhub' ); ?></p>
			</div>

			<?php if ( $error_code && isset( $error_map[ $error_code ] ) ) : ?>
				<div class="alert alert-danger"><?php echo esc_html( $error_map[ $error_code ] ); ?></div>
			<?php endif; ?>

			<?php if ( ! get_option( 'users_can_register' ) ) : ?>
				<div class="alert alert-warning mb-3"><?php esc_html_e( 'التسجيل مغلق حالياً من إعدادات WordPress.', 'examhub' ); ?></div>
				<a href="<?php echo esc_url( home_url( '/login' ) ); ?>" class="btn btn-primary w-100"><?php esc_html_e( 'العودة لتسجيل الدخول', 'examhub' ); ?></a>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eh-auth-form">
					<input type="hidden" name="action" value="examhub_auth_register">
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>">
					<?php wp_nonce_field( 'examhub_auth_register', 'eh_register_nonce' ); ?>

					<div>
						<label class="form-label"><?php esc_html_e( 'الاسم الكامل', 'examhub' ); ?></label>
						<input class="form-control" type="text" name="display_name" required>
					</div>

					<div>
						<label class="form-label"><?php esc_html_e( 'البريد الإلكتروني', 'examhub' ); ?></label>
						<input class="form-control" type="email" name="user_email" required dir="ltr">
					</div>

					<div>
						<label class="form-label"><?php esc_html_e( 'كلمة المرور', 'examhub' ); ?></label>
						<input class="form-control" type="password" name="user_pass" minlength="8" required dir="ltr">
					</div>

					<div>
						<label class="form-label"><?php esc_html_e( 'تأكيد كلمة المرور', 'examhub' ); ?></label>
						<input class="form-control" type="password" name="user_pass_confirm" minlength="8" required dir="ltr">
					</div>

					<button type="submit" class="btn btn-primary w-100">
						<i class="bi bi-person-plus me-1"></i>
						<?php esc_html_e( 'إنشاء الحساب', 'examhub' ); ?>
					</button>
				</form>

				<?php if ( function_exists( 'examhub_is_google_login_enabled' ) && examhub_is_google_login_enabled() ) : ?>
					<div class="eh-auth-sep"><span><?php esc_html_e( 'أو', 'examhub' ); ?></span></div>
					<a class="btn eh-google-btn w-100" href="<?php echo esc_url( add_query_arg( array( 'redirect_to' => $redirect_to ), home_url( '/google-auth-start/' ) ) ); ?>">
						<i class="bi bi-google me-2"></i><?php esc_html_e( 'إنشاء حساب عبر Google', 'examhub' ); ?>
					</a>
				<?php endif; ?>
			<?php endif; ?>

			<p class="eh-auth-switch mb-0">
				<?php esc_html_e( 'لديك حساب بالفعل؟', 'examhub' ); ?>
				<a href="<?php echo esc_url( home_url( '/login' ) ); ?>"><?php esc_html_e( 'تسجيل الدخول', 'examhub' ); ?></a>
			</p>
		</div>
	</section>
</div>

<?php get_footer(); ?>

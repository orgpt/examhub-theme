<?php
/**
 * ExamHub - page-reset-password.php
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

$login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';
$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

$is_reset_mode = ! empty( $login ) && ! empty( $key );

$error_map = array(
	'invalid_nonce'       => __( 'الجلسة انتهت. حاول مرة أخرى.', 'examhub' ),
	'missing_fields'      => __( 'من فضلك املأ كل الحقول المطلوبة.', 'examhub' ),
	'reset_request_failed'=> __( 'تعذر إرسال رسالة الاستعادة. تأكد من البريد/اسم المستخدم.', 'examhub' ),
	'password_mismatch'   => __( 'كلمتا المرور غير متطابقتين.', 'examhub' ),
	'weak_password'       => __( 'كلمة المرور يجب أن تكون 8 أحرف أو أكثر.', 'examhub' ),
	'invalid_key'         => __( 'رابط إعادة التعيين غير صالح أو منتهي الصلاحية.', 'examhub' ),
);

$notice_map = array(
	'reset_sent' => __( 'تم إرسال رابط إعادة التعيين إلى بريدك الإلكتروني.', 'examhub' ),
);

if ( $is_reset_mode ) {
	$check = check_password_reset_key( $key, $login );
	if ( is_wp_error( $check ) ) {
		$is_reset_mode = false;
		$error_code    = 'invalid_key';
	}
}

get_header();
?>

<div class="container-xl">
	<section class="eh-auth-wrap">
		<div class="eh-auth-card">
			<div class="eh-auth-brand text-center">
				<?php if ( $is_reset_mode ) : ?>
					<h1 class="mb-1"><?php esc_html_e( 'تعيين كلمة مرور جديدة', 'examhub' ); ?></h1>
					<p class="mb-0"><?php esc_html_e( 'اكتب كلمة مرور جديدة ثم سجل الدخول', 'examhub' ); ?></p>
				<?php else : ?>
					<h1 class="mb-1"><?php esc_html_e( 'استعادة كلمة المرور', 'examhub' ); ?></h1>
					<p class="mb-0"><?php esc_html_e( 'أدخل بريدك الإلكتروني وسنرسل لك رابط الاستعادة', 'examhub' ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( $error_code && isset( $error_map[ $error_code ] ) ) : ?>
				<div class="alert alert-danger"><?php echo esc_html( $error_map[ $error_code ] ); ?></div>
			<?php endif; ?>

			<?php if ( $notice_code && isset( $notice_map[ $notice_code ] ) ) : ?>
				<div class="alert alert-success"><?php echo esc_html( $notice_map[ $notice_code ] ); ?></div>
			<?php endif; ?>

			<?php if ( $is_reset_mode ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eh-auth-form">
					<input type="hidden" name="action" value="examhub_auth_resetpass">
					<input type="hidden" name="login" value="<?php echo esc_attr( $login ); ?>">
					<input type="hidden" name="key" value="<?php echo esc_attr( $key ); ?>">
					<?php wp_nonce_field( 'examhub_auth_resetpass', 'eh_resetpass_nonce' ); ?>

					<div>
						<label class="form-label"><?php esc_html_e( 'كلمة المرور الجديدة', 'examhub' ); ?></label>
						<input class="form-control" type="password" name="user_pass" minlength="8" required dir="ltr">
					</div>

					<div>
						<label class="form-label"><?php esc_html_e( 'تأكيد كلمة المرور', 'examhub' ); ?></label>
						<input class="form-control" type="password" name="user_pass_confirm" minlength="8" required dir="ltr">
					</div>

					<button type="submit" class="btn btn-primary w-100">
						<i class="bi bi-shield-lock me-1"></i>
						<?php esc_html_e( 'حفظ كلمة المرور', 'examhub' ); ?>
					</button>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eh-auth-form">
					<input type="hidden" name="action" value="examhub_auth_lostpassword">
					<?php wp_nonce_field( 'examhub_auth_lostpassword', 'eh_lostpass_nonce' ); ?>

					<div>
						<label class="form-label"><?php esc_html_e( 'البريد الإلكتروني أو اسم المستخدم', 'examhub' ); ?></label>
						<input class="form-control" type="text" name="user_login" required>
					</div>

					<button type="submit" class="btn btn-primary w-100">
						<i class="bi bi-envelope me-1"></i>
						<?php esc_html_e( 'إرسال رابط الاستعادة', 'examhub' ); ?>
					</button>
				</form>
			<?php endif; ?>

			<p class="eh-auth-switch mb-0">
				<a href="<?php echo esc_url( home_url( '/login' ) ); ?>"><?php esc_html_e( 'العودة إلى تسجيل الدخول', 'examhub' ); ?></a>
			</p>
		</div>
	</section>
</div>

<?php get_footer(); ?>


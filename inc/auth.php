<?php
/**
 * ExamHub - Authentication (custom login/register/reset + Google OAuth).
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get auth page URL by slug.
 *
 * @param string $slug
 * @param array  $args
 * @return string
 */
function examhub_auth_page_url( $slug, $args = array() ) {
	$url = home_url( '/' . trim( $slug, '/' ) . '/' );
	if ( ! empty( $args ) ) {
		$url = add_query_arg( $args, $url );
	}
	return $url;
}

/**
 * Ensure required auth pages exist.
 */
function examhub_ensure_auth_pages() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( get_option( 'examhub_auth_pages_created' ) ) {
		return;
	}

	$pages = array(
		'login'          => 'تسجيل الدخول',
		'register'       => 'إنشاء حساب',
		'reset-password' => 'استعادة كلمة المرور',
	);

	foreach ( $pages as $slug => $title ) {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing ) {
			continue;
		}

		wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);
	}

	update_option( 'examhub_auth_pages_created', 1 );
}
add_action( 'admin_init', 'examhub_ensure_auth_pages' );

/**
 * Redirect wp-login endpoints to custom auth pages.
 */
function examhub_redirect_wp_login() {
	if ( 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
		return;
	}

	if ( isset( $_GET['interim-login'] ) ) {
		return;
	}

	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';

	if ( in_array( $action, array( 'logout', 'postpass', 'confirmaction' ), true ) ) {
		return;
	}

	$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';

	if ( 'register' === $action ) {
		wp_safe_redirect( examhub_auth_page_url( 'register' ) );
		exit;
	}

	if ( in_array( $action, array( 'lostpassword', 'retrievepassword' ), true ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'reset-password' ) );
		exit;
	}

	if ( in_array( $action, array( 'rp', 'resetpass' ), true ) ) {
		$args = array(
			'login' => isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '',
			'key'   => isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '',
		);
		wp_safe_redirect( examhub_auth_page_url( 'reset-password', $args ) );
		exit;
	}

	$args = array();
	if ( $redirect_to ) {
		$args['redirect_to'] = $redirect_to;
	}

	wp_safe_redirect( examhub_auth_page_url( 'login', $args ) );
	exit;
}
add_action( 'login_init', 'examhub_redirect_wp_login' );

add_filter(
	'login_url',
	function( $login_url, $redirect ) {
		$args = array();
		if ( $redirect ) {
			$args['redirect_to'] = $redirect;
		}
		return examhub_auth_page_url( 'login', $args );
	},
	10,
	2
);

add_filter(
	'register_url',
	function() {
		return examhub_auth_page_url( 'register' );
	}
);

add_filter(
	'lostpassword_url',
	function( $url, $redirect ) {
		$args = array();
		if ( $redirect ) {
			$args['redirect_to'] = $redirect;
		}
		return examhub_auth_page_url( 'reset-password', $args );
	},
	10,
	2
);

/**
 * Login action.
 */
function examhub_handle_auth_login() {
	if ( ! isset( $_POST['eh_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eh_login_nonce'] ) ), 'examhub_auth_login' ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'login', array( 'auth_error' => 'invalid_nonce' ) ) );
		exit;
	}

	$identifier = isset( $_POST['user_login'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) ) : '';
	$password   = isset( $_POST['user_pass'] ) ? (string) $_POST['user_pass'] : '';
	$remember   = ! empty( $_POST['rememberme'] );
	$redirect   = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/dashboard' );

	if ( empty( $identifier ) || empty( $password ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'login', array( 'auth_error' => 'missing_fields' ) ) );
		exit;
	}

	$username = $identifier;
	if ( is_email( $identifier ) ) {
		$user = get_user_by( 'email', $identifier );
		if ( $user ) {
			$username = $user->user_login;
		}
	}

	$creds = array(
		'user_login'    => $username,
		'user_password' => $password,
		'remember'      => $remember,
	);

	$signon = wp_signon( $creds, is_ssl() );

	if ( is_wp_error( $signon ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'login', array( 'auth_error' => 'invalid_login' ) ) );
		exit;
	}

	wp_safe_redirect( $redirect ? $redirect : home_url( '/dashboard' ) );
	exit;
}
add_action( 'admin_post_nopriv_examhub_auth_login', 'examhub_handle_auth_login' );

/**
 * Register action.
 */
function examhub_handle_auth_register() {
	if ( ! get_option( 'users_can_register' ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'register', array( 'auth_error' => 'registration_disabled' ) ) );
		exit;
	}

	if ( ! isset( $_POST['eh_register_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eh_register_nonce'] ) ), 'examhub_auth_register' ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'register', array( 'auth_error' => 'invalid_nonce' ) ) );
		exit;
	}

	$name       = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
	$email      = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
	$password   = isset( $_POST['user_pass'] ) ? (string) $_POST['user_pass'] : '';
	$confirm    = isset( $_POST['user_pass_confirm'] ) ? (string) $_POST['user_pass_confirm'] : '';
	$redirect   = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/dashboard' );

	if ( empty( $name ) || empty( $email ) || empty( $password ) || empty( $confirm ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'register', array( 'auth_error' => 'missing_fields' ) ) );
		exit;
	}

	if ( ! is_email( $email ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'register', array( 'auth_error' => 'invalid_email' ) ) );
		exit;
	}

	if ( email_exists( $email ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'register', array( 'auth_error' => 'email_exists' ) ) );
		exit;
	}

	if ( strlen( $password ) < 8 ) {
		wp_safe_redirect( examhub_auth_page_url( 'register', array( 'auth_error' => 'weak_password' ) ) );
		exit;
	}

	if ( $password !== $confirm ) {
		wp_safe_redirect( examhub_auth_page_url( 'register', array( 'auth_error' => 'password_mismatch' ) ) );
		exit;
	}

	$base_username = sanitize_user( current( explode( '@', $email ) ), true );
	$username      = $base_username ? $base_username : 'student';
	$suffix        = 1;

	while ( username_exists( $username ) ) {
		$username = $base_username . $suffix;
		$suffix++;
	}

	$user_id = wp_insert_user(
		array(
			'user_login'   => $username,
			'user_pass'    => $password,
			'user_email'   => $email,
			'display_name' => $name,
			'first_name'   => $name,
			'role'         => 'subscriber',
		)
	);

	if ( is_wp_error( $user_id ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'register', array( 'auth_error' => 'register_failed' ) ) );
		exit;
	}

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true );

	wp_safe_redirect( $redirect ? $redirect : home_url( '/dashboard' ) );
	exit;
}
add_action( 'admin_post_nopriv_examhub_auth_register', 'examhub_handle_auth_register' );

/**
 * Lost password action.
 */
function examhub_handle_auth_lostpassword() {
	if ( ! isset( $_POST['eh_lostpass_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eh_lostpass_nonce'] ) ), 'examhub_auth_lostpassword' ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'reset-password', array( 'auth_error' => 'invalid_nonce' ) ) );
		exit;
	}

	$identifier = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
	if ( empty( $identifier ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'reset-password', array( 'auth_error' => 'missing_fields' ) ) );
		exit;
	}

	$result = retrieve_password( $identifier );

	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'reset-password', array( 'auth_error' => 'reset_request_failed' ) ) );
		exit;
	}

	wp_safe_redirect( examhub_auth_page_url( 'reset-password', array( 'auth_notice' => 'reset_sent' ) ) );
	exit;
}
add_action( 'admin_post_nopriv_examhub_auth_lostpassword', 'examhub_handle_auth_lostpassword' );

/**
 * Reset password action.
 */
function examhub_handle_auth_resetpass() {
	if ( ! isset( $_POST['eh_resetpass_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eh_resetpass_nonce'] ) ), 'examhub_auth_resetpass' ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'reset-password', array( 'auth_error' => 'invalid_nonce' ) ) );
		exit;
	}

	$login    = isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : '';
	$key      = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
	$password = isset( $_POST['user_pass'] ) ? (string) $_POST['user_pass'] : '';
	$confirm  = isset( $_POST['user_pass_confirm'] ) ? (string) $_POST['user_pass_confirm'] : '';

	if ( empty( $login ) || empty( $key ) || empty( $password ) || empty( $confirm ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'reset-password', array( 'auth_error' => 'missing_fields' ) ) );
		exit;
	}

	if ( $password !== $confirm ) {
		wp_safe_redirect(
			examhub_auth_page_url(
				'reset-password',
				array(
					'login'      => rawurlencode( $login ),
					'key'        => rawurlencode( $key ),
					'auth_error' => 'password_mismatch',
				)
			)
		);
		exit;
	}

	if ( strlen( $password ) < 8 ) {
		wp_safe_redirect(
			examhub_auth_page_url(
				'reset-password',
				array(
					'login'      => rawurlencode( $login ),
					'key'        => rawurlencode( $key ),
					'auth_error' => 'weak_password',
				)
			)
		);
		exit;
	}

	$user = check_password_reset_key( $key, $login );
	if ( is_wp_error( $user ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'reset-password', array( 'auth_error' => 'invalid_key' ) ) );
		exit;
	}

	reset_password( $user, $password );

	wp_safe_redirect( examhub_auth_page_url( 'login', array( 'auth_notice' => 'password_reset' ) ) );
	exit;
}
add_action( 'admin_post_nopriv_examhub_auth_resetpass', 'examhub_handle_auth_resetpass' );

/**
 * Read Google OAuth credentials from constants/options/ACF options.
 *
 * @return array
 */
function examhub_google_oauth_config() {
	$client_id = defined( 'EXAMHUB_GOOGLE_CLIENT_ID' ) ? EXAMHUB_GOOGLE_CLIENT_ID : '';
	$secret    = defined( 'EXAMHUB_GOOGLE_CLIENT_SECRET' ) ? EXAMHUB_GOOGLE_CLIENT_SECRET : '';

	if ( ! $client_id ) {
		$client_id = (string) get_option( 'examhub_google_client_id', '' );
	}
	if ( ! $secret ) {
		$secret = (string) get_option( 'examhub_google_client_secret', '' );
	}

	if ( function_exists( 'get_field' ) ) {
		if ( ! $client_id ) {
			$client_id = (string) get_field( 'google_client_id', 'option' );
		}
		if ( ! $secret ) {
			$secret = (string) get_field( 'google_client_secret', 'option' );
		}
	}

	return array(
		'client_id'    => trim( $client_id ),
		'client_secret'=> trim( $secret ),
		'redirect_uri' => home_url( '/?examhub_google_callback=1' ),
		'enabled'      => ! empty( $client_id ) && ! empty( $secret ),
	);
}

/**
 * Helper for templates.
 *
 * @return bool
 */
function examhub_is_google_login_enabled() {
	$config = examhub_google_oauth_config();
	return ! empty( $config['enabled'] );
}

/**
 * Add Google OAuth settings in Settings > General.
 */
function examhub_register_google_oauth_settings() {
	register_setting(
		'general',
		'examhub_google_client_id',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'general',
		'examhub_google_client_secret',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	add_settings_field(
		'examhub_google_client_id',
		__( 'ExamHub Google Client ID', 'examhub' ),
		function() {
			$value = (string) get_option( 'examhub_google_client_id', '' );
			echo '<input type="text" id="examhub_google_client_id" name="examhub_google_client_id" value="' . esc_attr( $value ) . '" class="regular-text" dir="ltr" />';
		},
		'general'
	);

	add_settings_field(
		'examhub_google_client_secret',
		__( 'ExamHub Google Client Secret', 'examhub' ),
		function() {
			$value = (string) get_option( 'examhub_google_client_secret', '' );
			echo '<input type="text" id="examhub_google_client_secret" name="examhub_google_client_secret" value="' . esc_attr( $value ) . '" class="regular-text" dir="ltr" />';
			echo '<p class="description">' . esc_html__( 'Google redirect URI:', 'examhub' ) . ' <code>' . esc_html( home_url( '/?examhub_google_callback=1' ) ) . '</code></p>';
		},
		'general'
	);
}
add_action( 'admin_init', 'examhub_register_google_oauth_settings' );

/**
 * Frontend Google OAuth endpoints to avoid /wp-admin redirects.
 */
function examhub_google_oauth_front_controller() {
	if ( isset( $_GET['examhub_google_start'] ) ) {
		examhub_google_oauth_start();
		exit;
	}

	if ( isset( $_GET['examhub_google_callback'] ) ) {
		examhub_google_oauth_callback();
		exit;
	}
}
add_action( 'init', 'examhub_google_oauth_front_controller' );

/**
 * Start Google OAuth flow.
 */
function examhub_google_oauth_start() {
	$config = examhub_google_oauth_config();
	if ( empty( $config['enabled'] ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'login', array( 'auth_error' => 'google_not_configured' ) ) );
		exit;
	}

	$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/dashboard' );
	$state       = wp_generate_password( 32, false, false );

	set_transient(
		'examhub_google_state_' . $state,
		array(
			'redirect_to' => $redirect_to,
			'created_at'  => time(),
		),
		15 * MINUTE_IN_SECONDS
	);

	$params = array(
		'client_id'     => $config['client_id'],
		'redirect_uri'  => $config['redirect_uri'],
		'response_type' => 'code',
		'scope'         => 'openid email profile',
		'state'         => $state,
		'access_type'   => 'online',
		'prompt'        => 'select_account',
	);

	$auth_url = add_query_arg( $params, 'https://accounts.google.com/o/oauth2/v2/auth' );

	wp_safe_redirect( $auth_url );
	exit;
}
add_action( 'admin_post_nopriv_examhub_google_start', 'examhub_google_oauth_start' );
add_action( 'admin_post_examhub_google_start', 'examhub_google_oauth_start' );

/**
 * Google OAuth callback.
 */
function examhub_google_oauth_callback() {
	$config = examhub_google_oauth_config();
	if ( empty( $config['enabled'] ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'login', array( 'auth_error' => 'google_not_configured' ) ) );
		exit;
	}

	$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
	$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

	if ( empty( $state ) || empty( $code ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'login', array( 'auth_error' => 'google_failed' ) ) );
		exit;
	}

	$state_data = get_transient( 'examhub_google_state_' . $state );
	delete_transient( 'examhub_google_state_' . $state );

	if ( empty( $state_data ) || empty( $state_data['redirect_to'] ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'login', array( 'auth_error' => 'google_failed' ) ) );
		exit;
	}

	$token_response = wp_remote_post(
		'https://oauth2.googleapis.com/token',
		array(
			'timeout' => 20,
			'body'    => array(
				'code'          => $code,
				'client_id'     => $config['client_id'],
				'client_secret' => $config['client_secret'],
				'redirect_uri'  => $config['redirect_uri'],
				'grant_type'    => 'authorization_code',
			),
		)
	);

	if ( is_wp_error( $token_response ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'login', array( 'auth_error' => 'google_failed' ) ) );
		exit;
	}

	$token_body   = json_decode( wp_remote_retrieve_body( $token_response ), true );
	$access_token = isset( $token_body['access_token'] ) ? $token_body['access_token'] : '';

	if ( empty( $access_token ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'login', array( 'auth_error' => 'google_failed' ) ) );
		exit;
	}

	$userinfo_response = wp_remote_get(
		'https://openidconnect.googleapis.com/v1/userinfo',
		array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
			),
		)
	);

	if ( is_wp_error( $userinfo_response ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'login', array( 'auth_error' => 'google_failed' ) ) );
		exit;
	}

	$userinfo = json_decode( wp_remote_retrieve_body( $userinfo_response ), true );
	$email    = isset( $userinfo['email'] ) ? sanitize_email( $userinfo['email'] ) : '';
	$name     = isset( $userinfo['name'] ) ? sanitize_text_field( $userinfo['name'] ) : '';
	$sub      = isset( $userinfo['sub'] ) ? sanitize_text_field( $userinfo['sub'] ) : '';
	$picture  = isset( $userinfo['picture'] ) ? esc_url_raw( $userinfo['picture'] ) : '';

	if ( empty( $email ) || ! is_email( $email ) ) {
		wp_safe_redirect( examhub_auth_page_url( 'login', array( 'auth_error' => 'google_failed' ) ) );
		exit;
	}

	$user = get_user_by( 'email', $email );

	if ( ! $user ) {
		$base_username = sanitize_user( current( explode( '@', $email ) ), true );
		$username      = $base_username ? $base_username : 'student';
		$suffix        = 1;

		while ( username_exists( $username ) ) {
			$username = $base_username . $suffix;
			$suffix++;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'user_email'   => $email,
				'display_name' => $name ? $name : $username,
				'first_name'   => $name,
				'role'         => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			wp_safe_redirect( examhub_auth_page_url( 'login', array( 'auth_error' => 'google_failed' ) ) );
			exit;
		}

		$user = get_user_by( 'id', $user_id );
	}

	if ( ! $user ) {
		wp_safe_redirect( examhub_auth_page_url( 'login', array( 'auth_error' => 'google_failed' ) ) );
		exit;
	}

	if ( $sub ) {
		update_user_meta( $user->ID, 'examhub_google_sub', $sub );
	}
	if ( $picture ) {
		update_user_meta( $user->ID, 'examhub_google_picture', $picture );
	}

	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true );

	wp_safe_redirect( $state_data['redirect_to'] );
	exit;
}
add_action( 'admin_post_nopriv_examhub_google_callback', 'examhub_google_oauth_callback' );
add_action( 'admin_post_examhub_google_callback', 'examhub_google_oauth_callback' );

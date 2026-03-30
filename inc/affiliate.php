<?php
/**
 * ExamHub - Affiliate system.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

function examhub_get_affiliate_rate() {
    $rate = function_exists( 'get_field' ) ? (float) get_field( 'affiliate_rate', 'option' ) : 0;
    if ( $rate <= 0 ) {
        $rate = 10;
    }

    return min( 100, max( 0, $rate ) );
}

function examhub_ensure_affiliate_page() {
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $page = get_page_by_path( 'affiliate', OBJECT, 'page' );
    if ( $page ) {
        return;
    }

    wp_insert_post(
        [
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'Affiliate',
            'post_name'    => 'affiliate',
            'post_content' => '',
        ]
    );
}
add_action( 'admin_init', 'examhub_ensure_affiliate_page' );

function examhub_get_affiliate_cookie_days() {
    $days = function_exists( 'get_field' ) ? (int) get_field( 'affiliate_cookie_days', 'option' ) : 0;
    return $days > 0 ? $days : 30;
}

function examhub_generate_affiliate_code( $user_id ) {
    return strtoupper( substr( md5( 'eh-affiliate-' . $user_id . wp_generate_password( 8, false ) ), 0, 10 ) );
}

function examhub_get_affiliate_code( $user_id, $create = true ) {
    $code = (string) get_user_meta( $user_id, 'eh_affiliate_code', true );
    if ( $code || ! $create ) {
        return $code;
    }

    $code = examhub_generate_affiliate_code( $user_id );
    update_user_meta( $user_id, 'eh_affiliate_code', $code );

    return $code;
}

function examhub_get_user_by_affiliate_code( $code ) {
    $users = get_users(
        [
            'number'     => 1,
            'fields'     => 'all',
            'meta_key'   => 'eh_affiliate_code',
            'meta_value' => strtoupper( sanitize_text_field( $code ) ),
        ]
    );

    return $users ? $users[0] : null;
}

function examhub_get_affiliate_url( $user_id = 0 ) {
    if ( ! $user_id ) {
        $user_id = get_current_user_id();
    }

    if ( ! $user_id ) {
        return home_url( '/affiliate/' );
    }

    return add_query_arg( 'ref', examhub_get_affiliate_code( $user_id ), home_url( '/affiliate/' ) );
}

function examhub_get_pending_affiliate_referrer_id() {
    if ( isset( $_COOKIE['eh_affiliate_referrer'] ) ) {
        return (int) $_COOKIE['eh_affiliate_referrer'];
    }

    return 0;
}

function examhub_set_affiliate_cookie( $referrer_id ) {
    $expires = time() + ( DAY_IN_SECONDS * examhub_get_affiliate_cookie_days() );
    setcookie( 'eh_affiliate_referrer', (string) (int) $referrer_id, $expires, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
    $_COOKIE['eh_affiliate_referrer'] = (string) (int) $referrer_id;
}

function examhub_capture_affiliate_referral() {
    if ( is_admin() || wp_doing_ajax() ) {
        return;
    }

    $code = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';
    if ( ! $code ) {
        return;
    }

    $affiliate_user = examhub_get_user_by_affiliate_code( $code );
    if ( ! $affiliate_user ) {
        return;
    }

    $current_user_id = get_current_user_id();
    if ( $current_user_id && $current_user_id === (int) $affiliate_user->ID ) {
        return;
    }

    examhub_set_affiliate_cookie( $affiliate_user->ID );

    if ( $current_user_id && ! get_user_meta( $current_user_id, 'eh_affiliate_referrer_id', true ) ) {
        update_user_meta( $current_user_id, 'eh_affiliate_referrer_id', $affiliate_user->ID );
        update_user_meta( $current_user_id, 'eh_affiliate_referred_at', current_time( 'mysql' ) );
    }
}
add_action( 'init', 'examhub_capture_affiliate_referral', 6 );

function examhub_assign_affiliate_referrer_to_new_user( $user_id ) {
    $referrer_id = examhub_get_pending_affiliate_referrer_id();
    if ( ! $referrer_id || $referrer_id === (int) $user_id ) {
        return;
    }

    if ( ! get_user_meta( $user_id, 'eh_affiliate_referrer_id', true ) ) {
        update_user_meta( $user_id, 'eh_affiliate_referrer_id', $referrer_id );
        update_user_meta( $user_id, 'eh_affiliate_referred_at', current_time( 'mysql' ) );
    }
}
add_action( 'user_register', 'examhub_assign_affiliate_referrer_to_new_user', 20 );

function examhub_get_user_affiliate_referrer_id( $user_id ) {
    return (int) get_user_meta( $user_id, 'eh_affiliate_referrer_id', true );
}

function examhub_prepare_payment_affiliate_data( $payment_id, $user_id, $amount_total ) {
    $referrer_id = examhub_get_user_affiliate_referrer_id( $user_id );
    if ( ! $referrer_id || $referrer_id === (int) $user_id ) {
        return;
    }

    $commission = round( (float) $amount_total * ( examhub_get_affiliate_rate() / 100 ), 2 );
    update_post_meta( $payment_id, '_eh_affiliate_referrer_id', $referrer_id );
    update_post_meta( $payment_id, '_eh_affiliate_rate', examhub_get_affiliate_rate() );
    update_post_meta( $payment_id, '_eh_affiliate_commission', $commission );
}

function examhub_register_affiliate_sale( $payment_id, $buyer_id, $plan_id ) {
    $referrer_id = (int) get_post_meta( $payment_id, '_eh_affiliate_referrer_id', true );
    if ( ! $referrer_id || $referrer_id === (int) $buyer_id ) {
        return;
    }

    $existing = get_posts(
        [
            'post_type'      => 'eh_affiliate_referral',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'   => '_eh_payment_id',
                    'value' => $payment_id,
                ],
            ],
        ]
    );

    if ( $existing ) {
        return;
    }

    $amount     = (float) get_field( 'amount_egp', $payment_id );
    $commission = (float) get_post_meta( $payment_id, '_eh_affiliate_commission', true );

    $referral_id = wp_insert_post(
        [
            'post_type'   => 'eh_affiliate_referral',
            'post_status' => 'publish',
            'post_author' => $referrer_id,
            'post_title'  => sprintf( 'Referral #%d - Payment #%d', $referrer_id, $payment_id ),
        ]
    );

    if ( is_wp_error( $referral_id ) ) {
        return;
    }

    update_post_meta( $referral_id, '_eh_payment_id', $payment_id );
    update_post_meta( $referral_id, '_eh_buyer_id', $buyer_id );
    update_post_meta( $referral_id, '_eh_referrer_id', $referrer_id );
    update_post_meta( $referral_id, '_eh_plan_id', $plan_id );
    update_post_meta( $referral_id, '_eh_amount', $amount );
    update_post_meta( $referral_id, '_eh_commission', $commission );
    update_post_meta( $referral_id, '_eh_status', 'approved' );
    update_post_meta( $referral_id, '_eh_recorded_at', current_time( 'mysql' ) );

    update_user_meta( $referrer_id, 'eh_affiliate_last_sale_at', current_time( 'mysql' ) );

    if ( function_exists( 'examhub_send_affiliate_sale_email' ) ) {
        examhub_send_affiliate_sale_email( $referrer_id, $buyer_id, $amount, $commission );
    }
}
add_action( 'examhub_payment_paid', 'examhub_register_affiliate_sale', 10, 3 );

function examhub_get_affiliate_stats( $user_id ) {
    $referrals = get_posts(
        [
            'post_type'      => 'eh_affiliate_referral',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'author'         => $user_id,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]
    );

    $invites = get_posts(
        [
            'post_type'      => 'eh_affiliate_invite',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'author'         => $user_id,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]
    );

    $total_sales      = 0.0;
    $total_commission = 0.0;

    foreach ( $referrals as $referral ) {
        $total_sales      += (float) get_post_meta( $referral->ID, '_eh_amount', true );
        $total_commission += (float) get_post_meta( $referral->ID, '_eh_commission', true );
    }

    return [
        'referrals'        => $referrals,
        'invites'          => $invites,
        'count'            => count( $referrals ),
        'invite_count'     => count( $invites ),
        'total_sales'      => $total_sales,
        'total_commission' => $total_commission,
        'affiliate_url'    => examhub_get_affiliate_url( $user_id ),
        'rate'             => examhub_get_affiliate_rate(),
    ];
}

function examhub_create_affiliate_invite( $user_id, $email ) {
    $invite_id = wp_insert_post(
        [
            'post_type'   => 'eh_affiliate_invite',
            'post_status' => 'publish',
            'post_author' => $user_id,
            'post_title'  => sprintf( 'Invite %s', $email ),
        ]
    );

    if ( is_wp_error( $invite_id ) ) {
        return $invite_id;
    }

    $token = wp_generate_password( 24, false, false );
    update_post_meta( $invite_id, '_eh_invited_email', $email );
    update_post_meta( $invite_id, '_eh_invite_token', $token );
    update_post_meta( $invite_id, '_eh_invite_status', 'sent' );
    update_post_meta( $invite_id, '_eh_affiliate_url', examhub_get_affiliate_url( $user_id ) );
    update_post_meta( $invite_id, '_eh_sent_at', current_time( 'mysql' ) );

    return $invite_id;
}

function examhub_ajax_send_affiliate_invite() {
    examhub_verify_ajax_nonce( 'examhub_ajax' );

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        wp_send_json_error( [ 'message' => __( 'يجب تسجيل الدخول أولًا.', 'examhub' ) ], 401 );
    }

    $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
    if ( ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => __( 'أدخل بريدًا إلكترونيًا صحيحًا.', 'examhub' ) ] );
    }

    if ( ! examhub_rate_limit( 'affiliate_invite_' . $user_id, 10, HOUR_IN_SECONDS ) ) {
        wp_send_json_error( [ 'message' => __( 'أرسلت عددًا كبيرًا من الدعوات. حاول لاحقًا.', 'examhub' ) ] );
    }

    $invite_id = examhub_create_affiliate_invite( $user_id, $email );
    if ( is_wp_error( $invite_id ) ) {
        wp_send_json_error( [ 'message' => __( 'تعذر إنشاء الدعوة.', 'examhub' ) ] );
    }

    if ( function_exists( 'examhub_send_affiliate_invite_email' ) ) {
        examhub_send_affiliate_invite_email( $invite_id );
    }

    wp_send_json_success(
        [
            'message' => __( 'تم إرسال الدعوة بنجاح.', 'examhub' ),
            'email'   => $email,
        ]
    );
}
add_action( 'wp_ajax_eh_send_affiliate_invite', 'examhub_ajax_send_affiliate_invite' );

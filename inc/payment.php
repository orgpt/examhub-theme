<?php
/**
 * ExamHub — Payment Router
 * Shared payment logic: create order, route to gateway, handle response.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════════
// CREATE PAYMENT ORDER (AJAX)
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_eh_create_payment', 'examhub_ajax_create_payment' );
function examhub_ajax_create_payment() {
    check_ajax_referer( 'examhub_ajax', 'nonce' );

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        wp_send_json_error( [ 'message' => __( 'يجب تسجيل الدخول.', 'examhub' ) ], 401 );
    }

    $plan_id = sanitize_text_field( $_POST['plan_id'] ?? '' );
    $method  = sanitize_text_field( $_POST['method']  ?? '' );

    if ( ! $plan_id || ! $method ) {
        wp_send_json_error( [ 'message' => __( 'بيانات غير مكتملة.', 'examhub' ) ] );
    }

    $plan = examhub_get_plan_by_id( $plan_id );
    if ( ! $plan || empty( $plan['plan_active'] ) ) {
        wp_send_json_error( [ 'message' => __( 'الخطة غير متاحة.', 'examhub' ) ] );
    }

    // Rate limiting
    if ( ! examhub_rate_limit( "payment_{$user_id}", 5, 300 ) ) {
        wp_send_json_error( [ 'message' => __( 'حاولت كثيراً. انتظر قليلاً.', 'examhub' ) ] );
    }

    // Check method enabled
    if ( ! examhub_is_payment_method_enabled( $method ) ) {
        wp_send_json_error( [ 'message' => __( 'طريقة الدفع هذه غير متاحة.', 'examhub' ) ] );
    }

    // Calculate amount with tax/fee
    $amount   = examhub_calculate_amount( (float) $plan['plan_price'] );
    $user     = get_userdata( $user_id );

    // Create payment record (pending)
    $payment_id = wp_insert_post( [
        'post_type'   => 'eh_payment',
        'post_title'  => sprintf( 'دفعة - %s - مستخدم %d - %s', $plan_id, $user_id, date( 'Y-m-d H:i:s' ) ),
        'post_status' => 'publish',
        'post_author' => $user_id,
    ] );

    if ( is_wp_error( $payment_id ) ) {
        wp_send_json_error( [ 'message' => __( 'خطأ في إنشاء الطلب.', 'examhub' ) ] );
    }

    update_field( 'pay_user_id',      $user_id,       $payment_id );
    update_field( 'pay_plan_id',      $plan_id,       $payment_id );
    update_field( 'amount_egp',       $amount['total'], $payment_id );
    update_field( 'payment_method',   $method,         $payment_id );
    update_field( 'payment_status',   'pending',        $payment_id );
    update_field( 'tax_amount',       $amount['tax'],   $payment_id );
    update_field( 'processing_fee',   $amount['fee'],   $payment_id );

    // Route to specific gateway
    switch ( $method ) {
        case 'fawaterk':
            $result = examhub_fawaterk_create_invoice( $payment_id, $plan, $user, $amount );
            break;

        case 'vodafone_cash':
            $result = examhub_vodafone_get_instructions( $payment_id, $plan, $amount );
            break;

        case 'bank_transfer':
        case 'instapay':
        case 'wallet':
            $result = examhub_manual_get_instructions( $payment_id, $method, $plan, $amount );
            break;

        default:
            wp_send_json_error( [ 'message' => __( 'طريقة دفع غير مدعومة.', 'examhub' ) ] );
    }

    if ( is_wp_error( $result ) ) {
        update_field( 'payment_status', 'failed', $payment_id );
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }

    wp_send_json_success( array_merge( $result, [ 'payment_id' => $payment_id ] ) );
}

// ═══════════════════════════════════════════════════════════════════════════════
// AMOUNT CALCULATOR
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Calculate final amount including tax and processing fee.
 *
 * @param float $base
 * @return array base, tax, fee, total
 */
function examhub_calculate_amount( $base ) {
    $tax_pct  = (float) ( get_field( 'tax_percentage', 'option' ) ?: 0 );
    $fee_pct  = (float) ( get_field( 'processing_fee_pct', 'option' ) ?: 0 );

    $tax  = round( $base * $tax_pct  / 100, 2 );
    $fee  = round( $base * $fee_pct  / 100, 2 );
    $total = round( $base + $tax + $fee, 2 );

    return compact( 'base', 'tax', 'fee', 'total' );
}

// ═══════════════════════════════════════════════════════════════════════════════
// METHOD AVAILABILITY
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Check if a payment method is enabled in settings.
 */
function examhub_is_payment_method_enabled( $method ) {
    $map = [
        'fawaterk'      => 'fawaterk_enabled',
        'vodafone_cash' => 'vodafone_cash_enabled',
        'bank_transfer' => 'manual_payment_enabled',
        'instapay'      => 'manual_payment_enabled',
        'wallet'        => 'manual_payment_enabled',
    ];
    $field = $map[ $method ] ?? null;
    if ( ! $field ) return false;
    return (bool) get_field( $field, 'option' );
}

/**
 * Get list of enabled payment methods with display info.
 */
function examhub_get_enabled_payment_methods() {
    $methods = [];

    if ( get_field( 'fawaterk_enabled', 'option' ) ) {
        $methods[] = [
            'key'   => 'fawaterk',
            'label' => 'بطاقة بنكية / فوتركirt',
            'icon'  => 'bi-credit-card',
            'desc'  => __( 'Visa, Mastercard, Meeza — دفع آمن فوري', 'examhub' ),
            'instant' => true,
        ];
    }

    if ( get_field( 'vodafone_cash_enabled', 'option' ) ) {
        $methods[] = [
            'key'   => 'vodafone_cash',
            'label' => 'Vodafone Cash',
            'icon'  => 'bi-phone',
            'desc'  => __( 'تحويل على رقم فودافون كاش', 'examhub' ),
            'instant' => false,
        ];
    }

    if ( get_field( 'manual_payment_enabled', 'option' ) ) {
        if ( get_field( 'bank_account', 'option' ) ) {
            $methods[] = [
                'key'   => 'bank_transfer',
                'label' => __( 'حوالة بنكية', 'examhub' ),
                'icon'  => 'bi-bank',
                'desc'  => __( 'تحويل على الحساب البنكي', 'examhub' ),
                'instant' => false,
            ];
        }
        if ( get_field( 'instapay_username', 'option' ) ) {
            $methods[] = [
                'key'   => 'instapay',
                'label' => 'InstaPay',
                'icon'  => 'bi-lightning',
                'desc'  => __( 'تحويل عبر InstaPay', 'examhub' ),
                'instant' => false,
            ];
        }
        if ( get_field( 'wallet_number', 'option' ) ) {
            $methods[] = [
                'key'   => 'wallet',
                'label' => __( 'محفظة إلكترونية', 'examhub' ),
                'icon'  => 'bi-wallet2',
                'desc'  => __( 'تحويل على رقم المحفظة', 'examhub' ),
                'instant' => false,
            ];
        }
    }

    return $methods;
}

// ═══════════════════════════════════════════════════════════════════════════════
// PAYMENT STATUS UPDATE (shared)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Mark payment as paid and activate subscription.
 *
 * @param int    $payment_id
 * @param string $transaction_id
 * @return bool
 */
function examhub_mark_payment_paid( $payment_id, $transaction_id = '' ) {
    $user_id = (int) get_field( 'pay_user_id', $payment_id );
    $plan_id = get_field( 'pay_plan_id', $payment_id );

    if ( ! $user_id || ! $plan_id ) return false;

    // Idempotency — don't double-activate
    $current_status = get_field( 'payment_status', $payment_id );
    if ( $current_status === 'paid' ) return true;

    update_field( 'payment_status',  'paid',          $payment_id );
    update_field( 'transaction_id',  $transaction_id, $payment_id );

    $sub_id = examhub_activate_subscription( $user_id, $plan_id, $payment_id );

    if ( is_wp_error( $sub_id ) ) {
        examhub_log( "Failed to activate subscription after payment {$payment_id}: " . $sub_id->get_error_message() );
        return false;
    }

    do_action( 'examhub_payment_paid', $payment_id, $user_id, $plan_id );

    return true;
}

// ═══════════════════════════════════════════════════════════════════════════════
// ADMIN: APPROVE / REJECT MANUAL PAYMENTS
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_eh_admin_approve_payment', 'examhub_ajax_admin_approve_payment' );
function examhub_ajax_admin_approve_payment() {
    check_ajax_referer( 'examhub_admin_ajax', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

    $payment_id     = (int) ( $_POST['payment_id'] ?? 0 );
    $transaction_id = sanitize_text_field( $_POST['transaction_id'] ?? '' );

    if ( ! $payment_id ) wp_send_json_error( [ 'message' => 'معرف الدفعة مطلوب.' ] );

    $success = examhub_mark_payment_paid( $payment_id, $transaction_id );

    if ( $success ) {
        wp_send_json_success( [ 'message' => __( 'تم قبول الدفعة وتفعيل الاشتراك.', 'examhub' ) ] );
    } else {
        wp_send_json_error( [ 'message' => __( 'حدث خطأ. تحقق من سجلات النظام.', 'examhub' ) ] );
    }
}

add_action( 'wp_ajax_eh_admin_reject_payment', 'examhub_ajax_admin_reject_payment' );
function examhub_ajax_admin_reject_payment() {
    check_ajax_referer( 'examhub_admin_ajax', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

    $payment_id = (int) ( $_POST['payment_id'] ?? 0 );
    $reason     = sanitize_text_field( $_POST['reason'] ?? '' );

    if ( ! $payment_id ) wp_send_json_error();

    update_field( 'payment_status', 'failed',  $payment_id );
    update_field( 'admin_notes',    $reason,   $payment_id );

    $user_id = (int) get_field( 'pay_user_id', $payment_id );
    if ( $user_id ) {
        $user = get_userdata( $user_id );
        if ( $user ) {
            wp_mail(
                $user->user_email,
                sprintf( __( 'تم رفض طلب دفعك في %s', 'examhub' ), get_bloginfo( 'name' ) ),
                sprintf( "مرحباً %s،\n\nلم يتم قبول دفعتك.\nالسبب: %s\n\nللمحاولة مجدداً: %s",
                    $user->display_name, $reason ?: __( 'لم يُحدد', 'examhub' ), home_url( '/pricing' )
                )
            );
        }
    }

    do_action( 'examhub_payment_rejected', $payment_id, $user_id );

    wp_send_json_success( [ 'message' => __( 'تم رفض الدفعة وإشعار المستخدم.', 'examhub' ) ] );
}

// ═══════════════════════════════════════════════════════════════════════════════
// PAYMENT LOGS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Log a payment event (stored as post meta on the payment post).
 */
function examhub_log_payment_event( $payment_id, $event, $data = [] ) {
    $log = get_post_meta( $payment_id, '_eh_payment_log', true );
    if ( ! is_array( $log ) ) $log = [];

    $log[] = [
        'event'     => $event,
        'data'      => $data,
        'timestamp' => current_time( 'timestamp' ),
        'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    update_post_meta( $payment_id, '_eh_payment_log', $log );
}

/**
 * Auto-cancel unpaid invoices after configured hours.
 */
function examhub_cancel_expired_invoices() {
    $expiry_hours = (int) ( get_field( 'invoice_expiry_hours', 'option' ) ?: 24 );
    $auto_cancel  = (bool) get_field( 'auto_cancel_pending', 'option' );

    if ( ! $auto_cancel ) return;

    $cutoff = date( 'Y-m-d H:i:s', strtotime( "-{$expiry_hours} hours" ) );

    $pending = get_posts( [
        'post_type'      => 'eh_payment',
        'posts_per_page' => 50,
        'date_query'     => [ [ 'before' => $cutoff ] ],
        'meta_query'     => [
            [ 'key' => 'payment_status', 'value' => [ 'pending', 'awaiting_review' ], 'compare' => 'IN' ],
        ],
    ] );

    foreach ( $pending as $p ) {
        update_field( 'payment_status', 'cancelled', $p->ID );
        examhub_log_payment_event( $p->ID, 'auto_cancelled', [ 'reason' => 'expiry' ] );
    }
}
add_action( 'examhub_daily_cron', 'examhub_cancel_expired_invoices' );

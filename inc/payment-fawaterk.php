<?php
/**
 * ExamHub — Fawaterk Payment Gateway
 * Creates invoices, handles webhooks, verifies payment status.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════════
// FAWATERK API CLIENT
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Create a Fawaterk invoice.
 *
 * @param int   $payment_id
 * @param array $plan
 * @param WP_User $user
 * @param array $amount
 * @return array|WP_Error { redirect_url, invoice_key }
 */
function examhub_fawaterk_create_invoice( $payment_id, $plan, $user, $amount ) {
    $api_key    = get_field( 'fawaterk_api_key', 'option' );
    $test_mode  = (bool) get_field( 'fawaterk_test_mode', 'option' );

    if ( ! $api_key ) {
        return new WP_Error( 'no_api_key', __( 'لم يتم إعداد Fawaterk API. تواصل مع الإدارة.', 'examhub' ) );
    }

    $base_url = $test_mode
        ? 'https://staging.fawaterk.com/api/v2'
        : 'https://app.fawaterk.com/api/v2';

    $callback_url = add_query_arg( [
        'eh_payment' => 'fawaterk',
        'pid'        => $payment_id,
    ], home_url( '/eh-payment-webhook/' ) );

    $success_url  = add_query_arg( [ 'payment_success' => 1, 'pid' => $payment_id ], home_url( '/subscription/' ) );
    $failure_url  = add_query_arg( [ 'payment_failed'  => 1, 'pid' => $payment_id ], home_url( '/checkout/' ) );

    $payload = [
        'cartTotal'      => (string) $amount['total'],
        'currency'       => 'EGP',
        'customer'       => [
            'first_name' => $user->first_name ?: $user->display_name,
            'last_name'  => $user->last_name  ?: '',
            'email'      => $user->user_email,
            'phone'      => get_user_meta( $user->ID, 'billing_phone', true ) ?: '',
        ],
        'redirectionUrls' => [
            'successUrl'  => $success_url,
            'failUrl'     => $failure_url,
            'pendingUrl'  => $failure_url,
        ],
        'callbackUrl'    => $callback_url,
        'cartItems'      => [
            [
                'name'     => $plan['plan_name'] ?? 'Subscription',
                'price'    => (string) $amount['base'],
                'quantity' => '1',
            ],
        ],
        'orderReference' => 'EH-' . $payment_id . '-' . time(),
        'sendEmail'      => true,
    ];

    $response = wp_remote_post( $base_url . '/invoices/create', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ],
        'body'    => json_encode( $payload ),
        'timeout' => 30,
    ] );

    if ( is_wp_error( $response ) ) {
        examhub_log_payment_event( $payment_id, 'fawaterk_api_error', [ 'error' => $response->get_error_message() ] );
        return new WP_Error( 'api_error', __( 'خطأ في الاتصال ببوابة الدفع. حاول مجدداً.', 'examhub' ) );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $code = wp_remote_retrieve_response_code( $response );

    examhub_log_payment_event( $payment_id, 'fawaterk_invoice_created', [ 'code' => $code, 'response' => $body ] );

    if ( $code !== 200 || empty( $body['data']['url'] ) ) {
        $error_msg = $body['message'] ?? $body['error'] ?? __( 'فشل إنشاء الفاتورة.', 'examhub' );
        return new WP_Error( 'invoice_failed', $error_msg );
    }

    $invoice_url = $body['data']['url'];
    $invoice_key = $body['data']['invoiceKey'] ?? $body['data']['key'] ?? '';

    // Save to payment record
    update_field( 'invoice_url',    $invoice_url, $payment_id );
    update_field( 'transaction_id', $invoice_key, $payment_id );

    return [
        'type'         => 'redirect',
        'redirect_url' => $invoice_url,
        'invoice_key'  => $invoice_key,
    ];
}

// ═══════════════════════════════════════════════════════════════════════════════
// FAWATERK WEBHOOK
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Register webhook endpoint via WordPress rewrite.
 */
add_action( 'init', 'examhub_register_webhook_endpoints' );
function examhub_register_webhook_endpoints() {
    add_rewrite_rule( '^eh-payment-webhook/?$', 'index.php?eh_webhook=1', 'top' );
    add_rewrite_tag( '%eh_webhook%', '([^&]+)' );
}

add_action( 'template_redirect', 'examhub_handle_webhook_request' );
function examhub_handle_webhook_request() {
    if ( ! get_query_var( 'eh_webhook' ) ) return;

    $gateway = sanitize_text_field( $_GET['eh_payment'] ?? $_POST['eh_payment'] ?? '' );

    switch ( $gateway ) {
        case 'fawaterk':
            examhub_fawaterk_handle_webhook();
            break;
    }

    exit;
}

/**
 * Process Fawaterk webhook callback.
 */
function examhub_fawaterk_handle_webhook() {
    $raw_body  = file_get_contents( 'php://input' );
    $signature = $_SERVER['HTTP_X_FAWATERK_SIGNATURE'] ?? $_SERVER['HTTP_SIGNATURE'] ?? '';
    $payload   = json_decode( $raw_body, true );

    $payment_id = (int) ( $_GET['pid'] ?? $payload['order_reference'] ?? 0 );

    // Parse EH-{id}-{ts} format
    if ( ! $payment_id && ! empty( $payload['orderReference'] ) ) {
        preg_match( '/EH-(\d+)-/', $payload['orderReference'], $m );
        $payment_id = (int) ( $m[1] ?? 0 );
    }

    if ( ! $payment_id ) {
        http_response_code( 400 );
        echo 'Missing payment ID';
        exit;
    }

    // Verify signature
    $secret = get_field( 'fawaterk_webhook_key', 'option' );
    if ( $secret && $signature ) {
        $expected = hash_hmac( 'sha256', $raw_body, $secret );
        if ( ! hash_equals( $expected, $signature ) ) {
            examhub_log_payment_event( $payment_id, 'fawaterk_webhook_invalid_sig' );
            http_response_code( 401 );
            echo 'Invalid signature';
            exit;
        }
    }

    examhub_log_payment_event( $payment_id, 'fawaterk_webhook_received', [ 'payload' => $payload ] );

    $status     = $payload['status'] ?? $payload['payment_status'] ?? '';
    $invoice_key = $payload['invoiceKey'] ?? $payload['key'] ?? '';

    if ( in_array( strtolower( $status ), [ 'paid', 'success', 'successful' ], true ) ) {
        examhub_mark_payment_paid( $payment_id, $invoice_key );
    } elseif ( in_array( strtolower( $status ), [ 'refunded', 'refund' ], true ) ) {
        examhub_mark_payment_refunded( $payment_id, 'fawaterk_refund' );
    } elseif ( in_array( strtolower( $status ), [ 'failed', 'fail', 'cancelled' ], true ) ) {
        update_field( 'payment_status', 'failed', $payment_id );
        examhub_log_payment_event( $payment_id, 'fawaterk_payment_failed', [ 'status' => $status ] );
    }

    http_response_code( 200 );
    echo json_encode( [ 'received' => true ] );
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
// VERIFY PAYMENT STATUS (Polling fallback)
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_eh_verify_fawaterk_payment', 'examhub_ajax_verify_fawaterk_payment' );
function examhub_ajax_verify_fawaterk_payment() {
    check_ajax_referer( 'examhub_ajax', 'nonce' );

    $payment_id = (int) ( $_POST['payment_id'] ?? 0 );
    $user_id    = get_current_user_id();

    if ( ! $payment_id ) wp_send_json_error();

    $stored_user = (int) get_field( 'pay_user_id', $payment_id );
    if ( $stored_user !== $user_id ) wp_send_json_error( [], 403 );

    $status = get_field( 'payment_status', $payment_id );

    wp_send_json_success( [
        'status'     => $status,
        'is_paid'    => $status === 'paid',
        'invoice_url'=> get_field( 'invoice_url', $payment_id ),
    ] );
}

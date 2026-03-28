<?php
/**
 * ExamHub — Vodafone Cash Payment
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get Vodafone Cash instructions for a payment.
 *
 * @param int   $payment_id
 * @param array $plan
 * @param array $amount
 * @return array
 */
function examhub_vodafone_get_instructions( $payment_id, $plan, $amount ) {
    $vc_number = get_field( 'vodafone_cash_number', 'option' );
    $vc_name   = get_field( 'vodafone_cash_name', 'option' );
    $instructions = get_field( 'vodafone_cash_instructions', 'option' );

    update_field( 'payment_status', 'awaiting_review', $payment_id );

    return [
        'type'          => 'manual',
        'method'        => 'vodafone_cash',
        'instructions'  => $instructions,
        'account_number'=> $vc_number,
        'account_name'  => $vc_name,
        'amount'        => $amount['total'],
        'reference'     => 'EH-' . $payment_id,
        'upload_url'    => admin_url( 'admin-ajax.php' ),
    ];
}

// Upload proof image + phone + reference
add_action( 'wp_ajax_eh_vodafone_submit_proof', 'examhub_ajax_vodafone_submit_proof' );
function examhub_ajax_vodafone_submit_proof() {
    check_ajax_referer( 'examhub_ajax', 'nonce' );

    $user_id    = get_current_user_id();
    $payment_id = (int) ( $_POST['payment_id'] ?? 0 );
    $phone      = sanitize_text_field( $_POST['phone'] ?? '' );
    $reference  = sanitize_text_field( $_POST['reference'] ?? '' );

    if ( ! $user_id || ! $payment_id ) {
        wp_send_json_error( [ 'message' => 'بيانات غير مكتملة.' ] );
    }

    // Verify ownership
    $stored_user = (int) get_field( 'pay_user_id', $payment_id );
    if ( $stored_user !== $user_id ) {
        wp_send_json_error( [], 403 );
    }

    // Handle screenshot upload
    $attachment_id = 0;
    if ( ! empty( $_FILES['proof'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload( 'proof', 0 );
        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( [ 'message' => __( 'فشل رفع الصورة.', 'examhub' ) ] );
        }
    }

    update_field( 'payment_status', 'awaiting_review', $payment_id );
    update_field( 'payer_phone',    $phone,             $payment_id );
    if ( $reference ) update_field( 'transaction_id', $reference, $payment_id );
    if ( $attachment_id ) update_field( 'payment_proof', $attachment_id, $payment_id );

    examhub_log_payment_event( $payment_id, 'vodafone_proof_submitted', [
        'phone'     => $phone,
        'reference' => $reference,
        'has_image' => (bool) $attachment_id,
    ] );

    // Notify admin
    examhub_notify_admin_pending_payment( $payment_id );

    wp_send_json_success( [
        'message' => __( 'تم إرسال إثبات الدفع. سيتم مراجعته خلال 24 ساعة.', 'examhub' ),
    ] );
}

/**
 * Notify admin of pending manual payment.
 */
function examhub_notify_admin_pending_payment( $payment_id ) {
    $admin_email  = get_field( 'payment_notify_email', 'option' ) ?: get_option( 'admin_email' );
    $user_id      = (int) get_field( 'pay_user_id', $payment_id );
    $user         = get_userdata( $user_id );
    $method       = get_field( 'payment_method', $payment_id );
    $amount       = get_field( 'amount_egp', $payment_id );
    $plan_id      = get_field( 'pay_plan_id', $payment_id );
    $admin_url    = admin_url( "post.php?post={$payment_id}&action=edit" );

    wp_mail(
        $admin_email,
        sprintf( __( '💰 طلب دفع يستحق المراجعة #%d', 'examhub' ), $payment_id ),
        sprintf(
            "المستخدم: %s (%s)\nالخطة: %s\nالمبلغ: %s جنيه\nالطريقة: %s\n\nمراجعة: %s",
            $user ? $user->display_name : "ID:{$user_id}",
            $user ? $user->user_email : '',
            $plan_id, $amount, $method, $admin_url
        )
    );
}

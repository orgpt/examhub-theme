<?php
/**
 * ExamHub — Manual Payment Methods
 * Bank transfer, InstaPay, wallet — admin review workflow.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get manual payment instructions.
 *
 * @param int    $payment_id
 * @param string $method  bank_transfer | instapay | wallet
 * @param array  $plan
 * @param array  $amount
 * @return array
 */
function examhub_manual_get_instructions( $payment_id, $method, $plan, $amount ) {
    $details = [];

    switch ( $method ) {
        case 'bank_transfer':
            $details = [
                'bank_name'    => get_field( 'bank_name', 'option' ),
                'account'      => get_field( 'bank_account', 'option' ),
                'instructions' => __( 'قم بتحويل المبلغ على رقم الحساب أدناه ثم ارفع صورة إيصال التحويل.', 'examhub' ),
            ];
            break;

        case 'instapay':
            $details = [
                'username'     => get_field( 'instapay_username', 'option' ),
                'instructions' => __( 'قم بإرسال المبلغ على ID الإنستاباي أدناه ثم ارفع صورة الإيصال.', 'examhub' ),
            ];
            break;

        case 'wallet':
            $details = [
                'number'       => get_field( 'wallet_number', 'option' ),
                'instructions' => __( 'قم بتحويل المبلغ على رقم المحفظة أدناه ثم ارفع إثبات الدفع.', 'examhub' ),
            ];
            break;
    }

    update_field( 'payment_status', 'awaiting_review', $payment_id );

    return array_merge( $details, [
        'type'      => 'manual',
        'method'    => $method,
        'amount'    => $amount['total'],
        'reference' => 'EH-' . $payment_id,
    ] );
}

// AJAX: submit manual payment proof
add_action( 'wp_ajax_eh_manual_submit_proof', 'examhub_ajax_manual_submit_proof' );
function examhub_ajax_manual_submit_proof() {
    check_ajax_referer( 'examhub_ajax', 'nonce' );

    $user_id    = get_current_user_id();
    $payment_id = (int) ( $_POST['payment_id'] ?? 0 );
    $reference  = sanitize_text_field( $_POST['reference'] ?? '' );
    $phone      = sanitize_text_field( $_POST['phone'] ?? '' );
    $notes      = sanitize_textarea_field( $_POST['notes'] ?? '' );

    if ( ! $user_id || ! $payment_id ) {
        wp_send_json_error( [ 'message' => 'بيانات غير مكتملة.' ] );
    }

    $stored_user = (int) get_field( 'pay_user_id', $payment_id );
    if ( $stored_user !== $user_id ) {
        wp_send_json_error( [], 403 );
    }

    // Handle file upload
    $attachment_id = 0;
    if ( ! empty( $_FILES['proof'] ) && ! empty( $_FILES['proof']['name'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload( 'proof', 0 );
        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( [ 'message' => __( 'فشل رفع الملف. تأكد من أن الملف صورة صالحة.', 'examhub' ) ] );
        }
    }

    if ( $reference ) update_field( 'transaction_id', $reference, $payment_id );
    if ( $phone )     update_field( 'payer_phone',    $phone,     $payment_id );
    if ( $notes )     update_field( 'admin_notes',    $notes,     $payment_id );
    if ( $attachment_id ) update_field( 'payment_proof', $attachment_id, $payment_id );

    update_field( 'payment_status', 'awaiting_review', $payment_id );

    examhub_log_payment_event( $payment_id, 'manual_proof_submitted', compact( 'reference', 'phone' ) );
    examhub_notify_admin_pending_payment( $payment_id );

    wp_send_json_success( [
        'message' => __( 'تم إرسال إثبات الدفع. سيتم مراجعته من الإدارة خلال 24 ساعة.', 'examhub' ),
        'status'  => 'awaiting_review',
    ] );
}

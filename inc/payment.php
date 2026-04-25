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
    examhub_prepare_payment_affiliate_data( $payment_id, $user_id, $amount['total'] );

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

    do_action( 'examhub_payment_created', $payment_id, [
        'user_id' => $user_id,
        'plan_id' => $plan_id,
        'amount'  => $amount['total'],
        'method'  => $method,
    ] );

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

/**
 * Mark payment as refunded and revoke any linked subscription.
 *
 * @param int    $payment_id
 * @param string $reason
 * @return bool
 */
function examhub_mark_payment_refunded( $payment_id, $reason = '' ) {
    $payment_id = (int) $payment_id;
    if ( ! $payment_id ) return false;

    if ( get_field( 'payment_status', $payment_id ) !== 'refunded' ) {
        update_field( 'payment_status', 'refunded', $payment_id );
    }

    if ( '' !== $reason ) {
        update_field( 'admin_notes', $reason, $payment_id );
    }

    $user_id = (int) get_field( 'pay_user_id', $payment_id );
    $subs    = get_posts( [
        'post_type'      => 'eh_subscription',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'author'         => $user_id,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'   => 'sub_payment_id',
                'value' => $payment_id,
            ],
            [
                'key'     => 'sub_status',
                'value'   => [ 'active', 'trial', 'lifetime' ],
                'compare' => 'IN',
            ],
        ],
    ] );

    foreach ( $subs as $sub ) {
        update_field( 'sub_status', 'cancelled', $sub->ID );
        do_action( 'examhub_subscription_cancelled', $sub->ID, $user_id, 'refunded' );
    }

    if ( $user_id ) {
        $revoked_sub_ids = wp_list_pluck( $subs, 'ID' );
        $active_sub_id   = (int) get_user_meta( $user_id, 'eh_active_sub_id', true );

        if ( ! $active_sub_id || in_array( $active_sub_id, $revoked_sub_ids, true ) ) {
            delete_user_meta( $user_id, 'eh_active_sub_id' );
            delete_user_meta( $user_id, 'eh_active_plan_id' );
            delete_user_meta( $user_id, 'eh_sub_expires' );
            delete_user_meta( $user_id, 'eh_lifetime' );
        }
    }

    examhub_log_payment_event( $payment_id, 'payment_refunded', [ 'reason' => $reason ] );
    do_action( 'examhub_payment_refunded', $payment_id, $user_id, $reason, $subs );

    return true;
}

// ═══════════════════════════════════════════════════════════════════════════════
// ADMIN: APPROVE / REJECT MANUAL PAYMENTS
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'save_post_eh_payment', 'examhub_sync_refunded_payment_subscription', 20, 3 );
function examhub_sync_refunded_payment_subscription( $post_id, $post, $update ) {
    if ( ! $update || wp_is_post_revision( $post_id ) ) {
        return;
    }

    if ( 'refunded' !== get_field( 'payment_status', $post_id ) ) {
        return;
    }

    examhub_mark_payment_refunded( $post_id, 'admin_refund_sync' );
}

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
function examhub_can_manage_manual_payments() {
    return current_user_can( 'edit_eh_payments' ) || current_user_can( 'manage_options' );
}

add_action( 'admin_menu', 'examhub_register_manual_payment_admin_page' );
function examhub_register_manual_payment_admin_page() {
    add_submenu_page(
        'edit.php?post_type=eh_payment',
        __( 'Add Manual Subscription', 'examhub' ),
        __( 'Add Manual Subscription', 'examhub' ),
        'edit_eh_payments',
        'examhub-manual-payment',
        'examhub_render_manual_payment_admin_page'
    );
}

add_filter( 'views_edit-eh_payment', 'examhub_add_manual_payment_view_link' );
function examhub_add_manual_payment_view_link( $views ) {
    if ( ! examhub_can_manage_manual_payments() ) {
        return $views;
    }

    $views['manual_subscription'] = sprintf(
        '<a href="%s">%s</a>',
        esc_url( admin_url( 'edit.php?post_type=eh_payment&page=examhub-manual-payment' ) ),
        esc_html__( 'Add Manual Subscription', 'examhub' )
    );

    return $views;
}

function examhub_render_manual_payment_admin_page() {
    if ( ! examhub_can_manage_manual_payments() ) {
        wp_die( esc_html__( 'You are not allowed to access this page.', 'examhub' ) );
    }

    $plans   = function_exists( 'examhub_get_all_plans' ) ? examhub_get_all_plans() : [];
    $success = isset( $_GET['manual_added'] ) ? sanitize_text_field( wp_unslash( $_GET['manual_added'] ) ) : '';
    $error   = isset( $_GET['manual_error'] ) ? sanitize_text_field( wp_unslash( $_GET['manual_error'] ) ) : '';
    $payment = isset( $_GET['payment_id'] ) ? absint( $_GET['payment_id'] ) : 0;
    $sub     = isset( $_GET['sub_id'] ) ? absint( $_GET['sub_id'] ) : 0;
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Add Manual Subscription', 'examhub' ); ?></h1>
        <p><?php esc_html_e( 'Create a paid payment record manually and activate a subscription for a user immediately.', 'examhub' ); ?></p>

        <?php if ( '1' === $success ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html( sprintf( __( 'Manual subscription created successfully. Payment #%1$d and subscription #%2$d are now active.', 'examhub' ), $payment, $sub ) ); ?></p>
            </div>
        <?php endif; ?>

        <?php if ( $error ) : ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'examhub_create_manual_payment', 'examhub_manual_payment_nonce' ); ?>
            <input type="hidden" name="action" value="examhub_create_manual_payment">

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="examhub-manual-user"><?php esc_html_e( 'User', 'examhub' ); ?></label></th>
                        <td>
                            <?php
                            wp_dropdown_users( [
                                'name'              => 'user_id',
                                'id'                => 'examhub-manual-user',
                                'show_option_none'  => __( 'Select a user', 'examhub' ),
                                'option_none_value' => '',
                                'selected'          => 0,
                                'class'             => 'regular-text',
                            ] );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="examhub-manual-plan"><?php esc_html_e( 'Plan', 'examhub' ); ?></label></th>
                        <td>
                            <select name="plan_id" id="examhub-manual-plan" class="regular-text" required>
                                <option value=""><?php esc_html_e( 'Select a plan', 'examhub' ); ?></option>
                                <?php foreach ( $plans as $plan ) : ?>
                                    <?php
                                    $plan_slug  = sanitize_text_field( $plan['plan_slug'] ?? '' );
                                    $plan_name  = $plan['plan_name_ar'] ?? $plan['plan_name'] ?? $plan_slug;
                                    $plan_price = isset( $plan['plan_price'] ) ? (float) $plan['plan_price'] : 0;
                                    if ( '' === $plan_slug ) {
                                        continue;
                                    }
                                    ?>
                                    <option value="<?php echo esc_attr( $plan_slug ); ?>"><?php echo esc_html( sprintf( '%s - %.2f', $plan_name, $plan_price ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Only active subscription plans are listed here.', 'examhub' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="examhub-manual-amount"><?php esc_html_e( 'Amount (EGP)', 'examhub' ); ?></label></th>
                        <td>
                            <input type="number" step="0.01" min="0" name="amount_egp" id="examhub-manual-amount" class="regular-text" placeholder="0.00">
                            <p class="description"><?php esc_html_e( 'Leave empty to use the plan price.', 'examhub' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="examhub-manual-transaction"><?php esc_html_e( 'Reference / Transaction ID', 'examhub' ); ?></label></th>
                        <td><input type="text" name="transaction_id" id="examhub-manual-transaction" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="examhub-manual-notes"><?php esc_html_e( 'Admin Notes', 'examhub' ); ?></label></th>
                        <td><textarea name="admin_notes" id="examhub-manual-notes" rows="5" class="large-text"></textarea></td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button( __( 'Create Payment and Activate Subscription', 'examhub' ) ); ?>
        </form>
    </div>
    <?php
}

add_action( 'admin_post_examhub_create_manual_payment', 'examhub_handle_manual_payment_admin_post' );
function examhub_handle_manual_payment_admin_post() {
    if ( ! examhub_can_manage_manual_payments() ) {
        wp_die( esc_html__( 'You are not allowed to do this action.', 'examhub' ) );
    }

    check_admin_referer( 'examhub_create_manual_payment', 'examhub_manual_payment_nonce' );

    $redirect_url = admin_url( 'edit.php?post_type=eh_payment&page=examhub-manual-payment' );
    $user_id      = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    $plan_id      = isset( $_POST['plan_id'] ) ? sanitize_text_field( wp_unslash( $_POST['plan_id'] ) ) : '';
    $amount_raw   = isset( $_POST['amount_egp'] ) ? wp_unslash( $_POST['amount_egp'] ) : '';
    $amount_egp   = '' === $amount_raw ? null : (float) $amount_raw;
    $transaction  = isset( $_POST['transaction_id'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_id'] ) ) : '';
    $admin_notes  = isset( $_POST['admin_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ) ) : '';

    $user = $user_id ? get_userdata( $user_id ) : false;
    $plan = $plan_id ? examhub_get_plan_by_id( $plan_id ) : null;

    if ( ! $user ) {
        wp_safe_redirect( add_query_arg( 'manual_error', __( 'Please choose a valid user.', 'examhub' ), $redirect_url ) );
        exit;
    }

    if ( ! $plan || empty( $plan['plan_active'] ) ) {
        wp_safe_redirect( add_query_arg( 'manual_error', __( 'Please choose a valid active plan.', 'examhub' ), $redirect_url ) );
        exit;
    }

    if ( null === $amount_egp ) {
        $amount_egp = (float) ( $plan['plan_price'] ?? 0 );
    }

    $payment_id = examhub_create_payment( [
        'user_id'         => $user_id,
        'pay_user_id'     => $user_id,
        'pay_plan_id'     => $plan_id,
        'amount_egp'      => max( 0, $amount_egp ),
        'payment_method'  => 'admin_manual',
        'payment_status'  => 'pending',
        'transaction_id'  => $transaction,
        'admin_notes'     => $admin_notes,
        'tax_amount'      => 0,
        'processing_fee'  => 0,
    ] );

    if ( is_wp_error( $payment_id ) ) {
        wp_safe_redirect( add_query_arg( 'manual_error', $payment_id->get_error_message() , $redirect_url ) );
        exit;
    }

    wp_update_post( [
        'ID'         => $payment_id,
        'post_title' => sprintf( 'دفعة - %s - مستخدم %d - %s', $plan_id, $user_id, current_time( 'Y-m-d H:i:s' ) ),
    ] );

    if ( ! examhub_mark_payment_paid( $payment_id, $transaction ) ) {
        wp_safe_redirect( add_query_arg( 'manual_error', __( 'The payment was created, but the subscription could not be activated.', 'examhub' ), $redirect_url ) );
        exit;
    }

    $sub_id = (int) get_user_meta( $user_id, 'eh_active_sub_id', true );
    examhub_log_payment_event( $payment_id, 'admin_manual_subscription_created', [
        'admin_id' => get_current_user_id(),
        'user_id'  => $user_id,
        'plan_id'  => $plan_id,
        'sub_id'   => $sub_id,
    ] );

    wp_safe_redirect( add_query_arg( [
        'manual_added' => '1',
        'payment_id'   => $payment_id,
        'sub_id'       => $sub_id,
    ], $redirect_url ) );
    exit;
}

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

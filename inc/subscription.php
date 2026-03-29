<?php
/**
 * ExamHub — Subscription System
 * Plan enforcement, daily limits, paywall, upgrade/downgrade, expiry.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════════
// SUBSCRIPTION ACTIVATION
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Activate subscription after payment confirmed.
 *
 * @param int    $user_id
 * @param string $plan_id   Plan slug from ACF options
 * @param int    $payment_id Payment post ID
 * @return int|WP_Error Subscription post ID
 */
function examhub_activate_subscription( $user_id, $plan_id, $payment_id = 0 ) {
    // Cancel any existing active subscription for same user
    examhub_cancel_existing_subscription( $user_id );

    $plan = examhub_get_plan_by_id( $plan_id );
    if ( ! $plan ) {
        return new WP_Error( 'invalid_plan', __( 'الخطة غير موجودة.', 'examhub' ) );
    }

    $duration = (int) ( $plan['plan_duration_days'] ?? 30 );
    $unlimited = ! empty( $plan['plan_unlimited'] );

    $sub_id = wp_insert_post( [
        'post_type'   => 'eh_subscription',
        'post_title'  => sprintf( 'اشتراك - %s - مستخدم %d', $plan['plan_name'] ?? $plan_id, $user_id ),
        'post_status' => 'publish',
        'post_author' => $user_id,
    ] );

    if ( is_wp_error( $sub_id ) ) return $sub_id;

    $start_dt = current_time( 'Y-m-d H:i:s' );
    $end_dt   = $unlimited ? null : date( 'Y-m-d H:i:s', strtotime( "+{$duration} days" ) );

    update_field( 'sub_user_id',       $user_id,                  $sub_id );
    update_field( 'plan_name',         $plan['plan_name'] ?? '',   $sub_id );
    update_field( 'plan_id',           $plan_id,                   $sub_id );
    update_field( 'sub_status',        $unlimited ? 'lifetime' : 'active', $sub_id );
    update_field( 'sub_start_date',    $start_dt,                  $sub_id );
    update_field( 'sub_end_date',      $end_dt,                    $sub_id );
    update_field( 'sub_payment_id',    $payment_id,                $sub_id );
    update_field( 'daily_questions_used', 0,                       $sub_id );
    update_field( 'daily_reset_date',  date( 'Y-m-d' ),            $sub_id );

    // Store subscription meta on user for fast access
    update_user_meta( $user_id, 'eh_active_sub_id',   $sub_id );
    update_user_meta( $user_id, 'eh_active_plan_id',  $plan_id );
    update_user_meta( $user_id, 'eh_sub_expires',     $end_dt ?? 'lifetime' );

    if ( $unlimited ) {
        update_user_meta( $user_id, 'eh_lifetime', 1 );
    }

    // Award first-subscription XP
    examhub_add_xp( $user_id, 100, 'مكافأة الاشتراك الأول' );

    do_action( 'examhub_subscription_activated', $sub_id, $user_id, $plan_id, $payment_id );

    // Send activation notification
    examhub_send_subscription_email( $user_id, 'activated', $plan, $end_dt );

    examhub_log( "Subscription activated: user={$user_id} plan={$plan_id} sub={$sub_id}" );

    return $sub_id;
}

/**
 * Cancel existing active subscription for a user.
 */
function examhub_cancel_existing_subscription( $user_id, $reason = 'upgraded' ) {
    $subs = get_posts( [
        'post_type'      => 'eh_subscription',
        'author'         => $user_id,
        'posts_per_page' => 5,
        'meta_query'     => [
            [ 'key' => 'sub_status', 'value' => [ 'active', 'trial' ], 'compare' => 'IN' ],
        ],
    ] );

    foreach ( $subs as $sub ) {
        update_field( 'sub_status', 'cancelled', $sub->ID );
        do_action( 'examhub_subscription_cancelled', $sub->ID, $user_id, $reason );
    }

    delete_user_meta( $user_id, 'eh_active_sub_id' );
    delete_user_meta( $user_id, 'eh_active_plan_id' );
    delete_user_meta( $user_id, 'eh_sub_expires' );
}

/**
 * Expire a subscription (called by cron or webhook).
 */
function examhub_expire_subscription( $sub_id ) {
    update_field( 'sub_status', 'expired', $sub_id );
    $user_id = (int) get_field( 'sub_user_id', $sub_id );
    if ( $user_id ) {
        delete_user_meta( $user_id, 'eh_active_sub_id' );
        delete_user_meta( $user_id, 'eh_active_plan_id' );
        delete_user_meta( $user_id, 'eh_sub_expires' );
        delete_user_meta( $user_id, 'eh_lifetime' );

        examhub_send_subscription_email( $user_id, 'expired' );
        do_action( 'examhub_subscription_expired', $sub_id, $user_id );
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// DAILY CRON — CHECK EXPIRY
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'examhub_daily_cron', 'examhub_process_subscription_expiry' );

function examhub_process_subscription_expiry() {
    global $wpdb;

    // Find active subs where end_date < now
    $expired_meta = $wpdb->get_col( $wpdb->prepare(
        "SELECT pm.post_id
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = pm.post_id
            AND pm2.meta_key = 'sub_status' AND pm2.meta_value IN ('active','trial')
        WHERE pm.meta_key = 'sub_end_date'
          AND pm.meta_value != ''
          AND pm.meta_value < %s
        LIMIT 100",
        current_time( 'mysql' )
    ) );

    foreach ( $expired_meta as $sub_id ) {
        examhub_expire_subscription( (int) $sub_id );
    }

    examhub_log( 'Expiry cron: processed ' . count( $expired_meta ) . ' subscriptions.' );
}

// Register cron
if ( ! wp_next_scheduled( 'examhub_daily_cron' ) ) {
    wp_schedule_event( time(), 'hourly', 'examhub_daily_cron' );
}

// ═══════════════════════════════════════════════════════════════════════════════
// AJAX — SUBSCRIPTION MANAGEMENT
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_eh_cancel_subscription', 'examhub_ajax_cancel_subscription' );
function examhub_ajax_cancel_subscription() {
    check_ajax_referer( 'examhub_ajax', 'nonce' );
    $user_id = get_current_user_id();
    if ( ! $user_id ) wp_send_json_error();

    examhub_cancel_existing_subscription( $user_id, 'user_cancelled' );

    wp_send_json_success( [ 'message' => __( 'تم إلغاء الاشتراك. يمكنك الاستمرار حتى انتهاء فترة الفوترة الحالية.', 'examhub' ) ] );
}

add_action( 'wp_ajax_eh_get_subscription_status', 'examhub_ajax_get_subscription_status' );
function examhub_ajax_get_subscription_status() {
    check_ajax_referer( 'examhub_ajax', 'nonce' );
    $user_id = get_current_user_id();
    wp_send_json_success( examhub_get_user_subscription_status( $user_id ) );
}

add_action( 'wp_ajax_eh_get_invoice_history', 'examhub_ajax_get_invoice_history' );
function examhub_ajax_get_invoice_history() {
    check_ajax_referer( 'examhub_ajax', 'nonce' );
    $user_id = get_current_user_id();
    if ( ! $user_id ) wp_send_json_error();

    $payments = get_posts( [
        'post_type'      => 'eh_payment',
        'author'         => $user_id,
        'posts_per_page' => 20,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [
            [ 'key' => 'payment_status', 'value' => [ 'paid', 'refunded' ], 'compare' => 'IN' ],
        ],
    ] );

    $history = [];
    foreach ( $payments as $p ) {
        $history[] = [
            'id'         => $p->ID,
            'date'       => get_the_date( 'd/m/Y', $p->ID ),
            'amount'     => get_field( 'amount_egp', $p->ID ),
            'method'     => get_field( 'payment_method', $p->ID ),
            'status'     => get_field( 'payment_status', $p->ID ),
            'invoice_url'=> get_field( 'invoice_url', $p->ID ),
            'plan'       => get_field( 'pay_plan_id', $p->ID ),
        ];
    }

    wp_send_json_success( [ 'history' => $history ] );
}

// ═══════════════════════════════════════════════════════════════════════════════
// EMAIL NOTIFICATIONS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Send subscription lifecycle email.
 */
function examhub_send_subscription_email( $user_id, $type, $plan = [], $expires = null ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) return;

    $site_name = get_bloginfo( 'name' );
    $from_email = get_option( 'admin_email' );

    $subjects = [
        'activated' => sprintf( __( '🎉 تم تفعيل اشتراكك في %s', 'examhub' ), $site_name ),
        'expired'   => sprintf( __( '⏰ انتهى اشتراكك في %s', 'examhub' ), $site_name ),
        'expiring_soon' => sprintf( __( '⚠️ اشتراكك سينتهي قريباً في %s', 'examhub' ), $site_name ),
        'cancelled' => sprintf( __( 'تم إلغاء اشتراكك في %s', 'examhub' ), $site_name ),
    ];

    $subject = $subjects[ $type ] ?? $subjects['activated'];
    $plan_name = $plan['plan_name_ar'] ?? $plan['plan_name'] ?? '';
    $expires_formatted = $expires ? date_i18n( 'd/m/Y', strtotime( $expires ) ) : '';

    $messages = [
        'activated' => sprintf(
            "مرحباً %s،\n\nتم تفعيل اشتراك %s بنجاح.\nينتهي في: %s\n\nيمكنك الآن الوصول لجميع الامتحانات.\n\n%s",
            $user->display_name, $plan_name, $expires_formatted, home_url()
        ),
        'expired' => sprintf(
            "مرحباً %s،\n\nانتهى اشتراكك. للتجديد والاستمرار في التعلم:\n%s\n\nشكراً لك.",
            $user->display_name, home_url( '/pricing' )
        ),
        'expiring_soon' => sprintf(
            "مرحباً %s،\n\nاشتراكك سينتهي في %s. جدد الآن للاستمرار:\n%s",
            $user->display_name, $expires_formatted, home_url( '/pricing' )
        ),
        'cancelled' => sprintf(
            "مرحباً %s،\n\nتم إلغاء اشتراكك. يمكنك الاشتراك مجدداً:\n%s",
            $user->display_name, home_url( '/pricing' )
        ),
    ];

    $message = $messages[ $type ] ?? '';

    wp_mail(
        $user->user_email,
        $subject,
        $message,
        [ 'Content-Type: text/plain; charset=UTF-8', "From: {$site_name} <{$from_email}>" ]
    );
}

// ═══════════════════════════════════════════════════════════════════════════════
// TEMPLATE HELPERS — PAYWALL
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Render paywall popup HTML.
 */
function examhub_render_paywall_popup( $context = 'question_limit' ) {
    $plans    = examhub_get_all_plans();
    $limit    = (int) ( get_field( 'free_exams_per_day', 'option' ) ?: get_field( 'free_questions_per_day', 'option' ) ?: 1 );
    $user_id  = get_current_user_id();
    $sub      = $user_id ? examhub_get_user_subscription_status( $user_id ) : [ 'state' => 'free' ];

    $messages = [
        'question_limit' => sprintf(
            __( 'لقد استخدمت %d امتحان مجاني اليوم. اشترك للوصول الكامل.', 'examhub' ),
            $limit
        ),
        'subscription_required' => __( 'هذا الامتحان للمشتركين فقط.', 'examhub' ),
        'ai_required'           => __( 'الذكاء الاصطناعي متاح للمشتركين المميزين.', 'examhub' ),
    ];

    ob_start();
    ?>
    <div class="eh-paywall-overlay" id="eh-paywall-modal" style="display:none;" aria-modal="true" role="dialog">
      <div class="eh-paywall-box">
        <button class="btn-close position-absolute top-0 end-0 m-3" id="eh-paywall-close" aria-label="<?php esc_attr_e('إغلاق','examhub'); ?>"></button>

        <div class="text-center mb-4">
          <div class="mb-3" style="font-size:3rem;">🔒</div>
          <h4 class="fw-bold text-light"><?php esc_html_e( 'ترقية حسابك', 'examhub' ); ?></h4>
          <p class="text-muted" id="paywall-message"><?php echo esc_html( $messages[ $context ] ?? $messages['question_limit'] ); ?></p>
        </div>

        <?php if ( ! empty( $plans ) ) :
          // Show up to 3 plans
          $featured = array_filter( $plans, fn($p) => !empty($p['plan_featured']) );
          $display  = ! empty( $featured ) ? array_slice( $featured, 0, 1 ) : array_slice( $plans, 0, 3 );
          foreach ( $display as $plan ) : ?>
            <div class="mb-3 p-3 rounded-eh" style="border:1px solid var(--eh-accent); background:var(--eh-accent-light);">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-bold text-light"><?php echo esc_html( $plan['plan_name_ar'] ?? $plan['plan_name'] ); ?></span>
                <span class="text-accent fw-bold">
                  <?php echo number_format( $plan['plan_price'] ); ?> <?php esc_html_e( 'جنيه', 'examhub' ); ?>
                  <?php if ( $plan['plan_duration_days'] ) : ?>
                    <small class="text-muted">/ <?php echo (int)$plan['plan_duration_days']; ?> <?php esc_html_e('يوم','examhub'); ?></small>
                  <?php endif; ?>
                </span>
              </div>
              <a href="<?php echo esc_url( home_url( '/checkout?plan=' . esc_attr( $plan['plan_slug'] ) ) ); ?>"
                 class="btn btn-primary w-100">
                <?php esc_html_e( 'اشترك الآن', 'examhub' ); ?>
              </a>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="text-center">
          <a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="text-muted small">
            <?php esc_html_e( 'عرض جميع الخطط', 'examhub' ); ?> →
          </a>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
add_action( 'wp_footer', function() {
    if ( is_user_logged_in() ) {
        echo examhub_render_paywall_popup();
    }
} );

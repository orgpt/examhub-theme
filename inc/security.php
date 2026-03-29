<?php
/**
 * ExamHub — Security
 * Nonce validation, rate limiting, input sanitization, server-side checks.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

// ─── Rate Limiting (simple transient-based) ───────────────────────────────────

/**
 * Check and increment rate limit for an action.
 *
 * @param string $key     Unique action key (e.g. 'exam_submit_{user_id}')
 * @param int    $limit   Max allowed per window
 * @param int    $window  Window in seconds
 * @return bool  True if allowed, false if rate limited
 */
function examhub_rate_limit( $key, $limit = 10, $window = 60 ) {
    $transient_key = 'eh_rl_' . md5( $key );
    $count = (int) get_transient( $transient_key );

    if ( $count >= $limit ) {
        return false;
    }

    if ( $count === 0 ) {
        set_transient( $transient_key, 1, $window );
    } else {
        // Increment without resetting expiry (approximate)
        set_transient( $transient_key, $count + 1, $window );
    }

    return true;
}

// ─── Input Sanitization Helpers ───────────────────────────────────────────────

/**
 * Sanitize integer from POST.
 */
function examhub_post_int( $key, $default = 0 ) {
    return isset( $_POST[ $key ] ) ? (int) $_POST[ $key ] : $default;
}

/**
 * Sanitize string from POST.
 */
function examhub_post_str( $key, $default = '' ) {
    return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $default;
}

/**
 * Sanitize JSON from POST (for exam answers).
 *
 * @param string $key
 * @return array|null
 */
function examhub_post_json( $key ) {
    if ( ! isset( $_POST[ $key ] ) ) return null;
    $raw  = wp_unslash( $_POST[ $key ] );
    $data = json_decode( $raw, true );
    return is_array( $data ) ? $data : null;
}

// ─── Exam Security ────────────────────────────────────────────────────────────

/**
 * Verify exam access for current user.
 * Returns true if user can access the exam.
 *
 * @param int $exam_id
 * @param int $user_id
 * @return true|WP_Error
 */
function examhub_verify_exam_access( $exam_id, $user_id = 0 ) {
    if ( ! $user_id ) $user_id = get_current_user_id();

    if ( ! $user_id ) {
        return new WP_Error( 'not_logged_in', __( 'يجب تسجيل الدخول أولاً.', 'examhub' ) );
    }

    $exam = get_post( $exam_id );
    if ( ! $exam || $exam->post_type !== 'eh_exam' || $exam->post_status !== 'publish' ) {
        return new WP_Error( 'invalid_exam', __( 'الامتحان غير موجود.', 'examhub' ) );
    }

    $access_level = get_field( 'exam_access', $exam_id ) ?: 'free_limit';
    $sub          = examhub_get_user_subscription_status( $user_id );
    $is_paid_user = in_array( $sub['state'], [ 'subscribed', 'trial', 'lifetime' ], true );

    if ( $access_level === 'free' ) {
        return true;
    }

    if ( $access_level === 'subscribed' && ! $is_paid_user ) {
        return new WP_Error( 'subscription_required', __( 'هذا الامتحان للمشتركين فقط.', 'examhub' ) );
    }

    if ( $access_level === 'free_limit' && ! $is_paid_user ) {
        $free_plan_enabled = (bool) get_field( 'exam_free_plan_enabled', $exam_id );
        if ( ! $free_plan_enabled ) {
            return new WP_Error( 'subscription_required', __( 'هذا الامتحان غير متاح للخطة المجانية.', 'examhub' ) );
        }

        if ( function_exists( 'examhub_user_can_start_exam' ) && ! examhub_user_can_start_exam( $user_id ) ) {
            return new WP_Error( 'limit_reached', __( 'لقد وصلت إلى حد الامتحانات اليومية للخطة المجانية.', 'examhub' ) );
        }
    }

    // Check attempts limit
    $max_attempts = (int) get_field( 'max_attempts', $exam_id );
    if ( $max_attempts > 0 ) {
        $attempt_count = examhub_get_exam_attempt_count( $exam_id, $user_id );
        if ( $attempt_count >= $max_attempts ) {
            return new WP_Error( 'max_attempts', __( 'لقد استنفدت الحد الأقصى من المحاولات.', 'examhub' ) );
        }
    }

    // Check subscription attempts limit
    if ( $sub['attempts_limit'] > 0 ) {
        // Per-day attempt limit (could be expanded)
    }

    return true;
}

/**
 * Get exam attempt count for user.
 *
 * @param int $exam_id
 * @param int $user_id
 * @return int
 */
function examhub_get_exam_attempt_count( $exam_id, $user_id ) {
    $results = get_posts( [
        'post_type'      => 'eh_result',
        'author'         => $user_id,
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [ 'key' => 'result_exam_id', 'value' => $exam_id ],
            [ 'key' => 'result_status',  'value' => [ 'submitted', 'timed_out' ], 'compare' => 'IN' ],
        ],
    ] );
    return count( $results );
}

/**
 * Verify an in-progress exam session belongs to the user.
 *
 * @param int $result_id
 * @param int $user_id
 * @return bool
 */
function examhub_verify_result_ownership( $result_id, $user_id ) {
    $result = get_post( $result_id );
    if ( ! $result || $result->post_type !== 'eh_result' ) return false;
    return (int) $result->post_author === (int) $user_id;
}

// ─── Prevent exam bypass ─────────────────────────────────────────────────────

/**
 * Prevent direct access to result posts.
 */
function examhub_restrict_result_access() {
    if ( ! is_singular( 'eh_result' ) ) return;
    if ( ! is_user_logged_in() ) {
        wp_redirect( wp_login_url( get_permalink() ) );
        exit;
    }
    global $post;
    if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'ليس لديك صلاحية الوصول لهذه النتيجة.', 'examhub' ), 403 );
    }
}
add_action( 'template_redirect', 'examhub_restrict_result_access' );

/**
 * Remove question post type from public sitemaps / search.
 */
function examhub_exclude_from_search( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) return;
    if ( $query->is_search() ) {
        $excluded = [ 'eh_question', 'eh_result', 'eh_subscription', 'eh_payment' ];
        $existing = (array) $query->get( 'post_type' );
        if ( empty( $existing ) ) $existing = [ 'post', 'page' ];
        $query->set( 'post_type', array_diff( $existing, $excluded ) );
    }
}
add_action( 'pre_get_posts', 'examhub_exclude_from_search' );

<?php
/**
 * ExamHub — Security
 * Nonce validation, rate limiting, input sanitization, server-side checks.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalize a license key before hashing/comparison.
 *
 * @param string $license_key Raw license key.
 * @return string
 */
function examhub_normalize_license_key( $license_key ) {
    $license_key = strtoupper( trim( (string) $license_key ) );
    return preg_replace( '/[^A-Z0-9]/', '', $license_key );
}

/**
 * Hash a normalized license key.
 *
 * @param string $license_key Raw license key.
 * @return string
 */
function examhub_hash_license_key( $license_key ) {
    $normalized = examhub_normalize_license_key( $license_key );

    if ( '' === $normalized ) {
        return '';
    }

    return hash( 'sha256', $normalized );
}

/**
 * Get saved theme activation hash from the database.
 *
 * @return string
 */
function examhub_get_saved_license_hash() {
    return (string) get_option( EXAMHUB_LICENSE_OPTION, '' );
}

/**
 * Check whether the theme is activated with the correct license key.
 *
 * @return bool
 */
function examhub_is_theme_activated() {
    $saved_hash = examhub_get_saved_license_hash();

    if ( '' === $saved_hash || '' === EXAMHUB_LICENSE_HASH ) {
        return false;
    }

    return hash_equals( EXAMHUB_LICENSE_HASH, $saved_hash );
}

/**
 * Validate a license key value against the bundled hash.
 *
 * @param string $license_key Raw license key.
 * @return bool
 */
function examhub_validate_license_key( $license_key ) {
    $license_hash = examhub_hash_license_key( $license_key );

    if ( '' === $license_hash || '' === EXAMHUB_LICENSE_HASH ) {
        return false;
    }

    return hash_equals( EXAMHUB_LICENSE_HASH, $license_hash );
}

/**
 * Register theme activation setting.
 */
function examhub_register_license_setting() {
    register_setting(
        'examhub_license',
        EXAMHUB_LICENSE_OPTION,
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
            'show_in_rest'      => false,
        ]
    );
}
add_action( 'admin_init', 'examhub_register_license_setting' );

/**
 * Add activation page under Appearance.
 */
function examhub_add_license_page() {
    add_theme_page(
        __( 'Theme Activation', 'examhub' ),
        __( 'Theme Activation', 'examhub' ),
        'manage_options',
        'examhub-theme-activation',
        'examhub_render_license_page'
    );
}
add_action( 'admin_menu', 'examhub_add_license_page' );

/**
 * Handle theme activation form submission.
 */
function examhub_handle_license_form_submission() {
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( ! isset( $_POST['examhub_activate_theme'] ) ) {
        return;
    }

    check_admin_referer( 'examhub_activate_theme_action', 'examhub_activate_theme_nonce' );

    $license_key = isset( $_POST['examhub_license_key'] )
        ? sanitize_text_field( wp_unslash( $_POST['examhub_license_key'] ) )
        : '';

    if ( examhub_validate_license_key( $license_key ) ) {
        update_option( EXAMHUB_LICENSE_OPTION, examhub_hash_license_key( $license_key ), false );

        add_settings_error(
            'examhub_license',
            'examhub_license_success',
            __( 'Theme activated successfully.', 'examhub' ),
            'updated'
        );
    } else {
        delete_option( EXAMHUB_LICENSE_OPTION );

        add_settings_error(
            'examhub_license',
            'examhub_license_error',
            __( 'Invalid activation key. The theme remains locked.', 'examhub' ),
            'error'
        );
    }
}
add_action( 'admin_init', 'examhub_handle_license_form_submission' );

/**
 * Render the theme activation page.
 */
function examhub_render_license_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $is_activated = examhub_is_theme_activated();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Theme Activation', 'examhub' ); ?></h1>
        <p>
            <?php
            echo esc_html(
                $is_activated
                    ? __( 'This theme is activated on this site.', 'examhub' )
                    : __( 'Enter the activation key to unlock the theme on this site.', 'examhub' )
            );
            ?>
        </p>

        <?php settings_errors( 'examhub_license' ); ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'examhub_activate_theme_action', 'examhub_activate_theme_nonce' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="examhub_license_key"><?php esc_html_e( 'Activation Key', 'examhub' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="examhub_license_key"
                            name="examhub_license_key"
                            class="regular-text"
                            autocomplete="off"
                            placeholder="EHUB-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX"
                        />
                        <p class="description">
                            <?php esc_html_e( 'The stored database value is hashed and cannot be reversed back to the original key.', 'examhub' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Activate Theme', 'examhub' ), 'primary', 'examhub_activate_theme' ); ?>
        </form>
    </div>
    <?php
}

/**
 * Show an admin notice while the theme is locked.
 */
function examhub_license_admin_notice() {
    if ( ! current_user_can( 'manage_options' ) || examhub_is_theme_activated() ) {
        return;
    }

    $activation_url = admin_url( 'themes.php?page=examhub-theme-activation' );
    $message        = sprintf(
        __( 'ExamHub is locked until you enter a valid activation key. Open <a href="%s">Theme Activation</a> to unlock it.', 'examhub' ),
        esc_url( $activation_url )
    );
    ?>
    <div class="notice notice-error">
        <p>
            <?php echo wp_kses_post( $message ); ?>
        </p>
    </div>
    <?php
}
add_action( 'admin_notices', 'examhub_license_admin_notice' );

/**
 * Block frontend access when the theme is not activated.
 */
function examhub_enforce_theme_activation() {
    if ( examhub_is_theme_activated() ) {
        return;
    }

    if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }

    if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
        return;
    }

    wp_die(
        esc_html__( 'This copy of the theme is locked. Please activate it from Appearance > Theme Activation.', 'examhub' ),
        esc_html__( 'Theme Locked', 'examhub' ),
        [ 'response' => 403 ]
    );
}
add_action( 'template_redirect', 'examhub_enforce_theme_activation', 0 );

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
    $is_paid_user = in_array( $sub['state'], [ 'active', 'trial', 'lifetime' ], true );

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

<?php
/**
 * ExamHub — Enqueue Scripts & Styles
 * Loads Bootstrap 5, custom CSS, and JS bundles.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

function examhub_enqueue_assets() {

    $ver = EXAMHUB_VERSION;
    $is_rtl = is_rtl();
    $main_css_ver   = file_exists( EXAMHUB_DIR . '/assets/css/main.css' ) ? filemtime( EXAMHUB_DIR . '/assets/css/main.css' ) : $ver;
    $main_js_ver    = file_exists( EXAMHUB_DIR . '/assets/js/main.js' ) ? filemtime( EXAMHUB_DIR . '/assets/js/main.js' ) : $ver;
    $filter_js_ver  = file_exists( EXAMHUB_DIR . '/assets/js/filter.js' ) ? filemtime( EXAMHUB_DIR . '/assets/js/filter.js' ) : $ver;
    $exam_css_ver   = file_exists( EXAMHUB_DIR . '/assets/css/exam.css' ) ? filemtime( EXAMHUB_DIR . '/assets/css/exam.css' ) : $ver;
    $exam_js_ver    = file_exists( EXAMHUB_DIR . '/assets/js/exam-engine.js' ) ? filemtime( EXAMHUB_DIR . '/assets/js/exam-engine.js' ) : $ver;

    // ─── Styles ────────────────────────────────────────────────────────────

    // Bootstrap 5 RTL or LTR
    if ( $is_rtl ) {
        wp_enqueue_style( 'bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css', [], '5.3.2' );
    } else {
        wp_enqueue_style( 'bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css', [], '5.3.2' );
    }

    // Bootstrap Icons
    wp_enqueue_style( 'bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css', [], '1.11.3' );

    // Google Fonts — Arabic + Latin
    wp_enqueue_style( 'examhub-fonts',
        'https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&family=Cairo:wght@300;400;600;700&display=swap',
        [], null
    );

    // Chart.js for analytics
    wp_enqueue_style( 'examhub-main', EXAMHUB_ASSETS . 'css/main.css', [ 'bootstrap' ], $main_css_ver );

    // Exam-specific styles (only on exam pages)
    if ( is_singular( 'eh_exam' ) || examhub_is_exam_page() ) {
        wp_enqueue_style( 'examhub-exam', EXAMHUB_ASSETS . 'css/exam.css', [ 'examhub-main' ], $exam_css_ver );
    }

    // Dashboard styles
    if ( examhub_is_dashboard_page() ) {
        wp_enqueue_style( 'examhub-dashboard', EXAMHUB_ASSETS . 'css/dashboard.css', [ 'examhub-main' ], $ver );
    }

    // MathJax for math equations
    if ( examhub_page_has_math() ) {
        wp_enqueue_script( 'mathjax-config', EXAMHUB_ASSETS . 'js/mathjax-config.js', [], $ver, false );
        wp_enqueue_script( 'mathjax', 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js', [ 'mathjax-config' ], '3', false );
    }

    // ─── Scripts ───────────────────────────────────────────────────────────

    // Bootstrap 5 JS
    wp_enqueue_script( 'bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js', [], '5.3.2', true );

    // Chart.js (only on analytics/dashboard pages)
    if ( examhub_is_dashboard_page() || is_page( 'analytics' ) ) {
        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', [], '4.4.0', true );
    }

    // SortableJS for ordering questions
    if ( is_singular( 'eh_exam' ) ) {
        wp_enqueue_script( 'sortablejs', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js', [], '1.15.2', true );
    }

    // Main theme JS
    wp_enqueue_script( 'examhub-main', EXAMHUB_ASSETS . 'js/main.js', [ 'jquery', 'bootstrap' ], $main_js_ver, true );

    // Archive exam filter (system > stage > grade > ...)
    if ( is_post_type_archive( 'eh_exam' ) || examhub_is_exam_page() ) {
        wp_enqueue_script( 'examhub-filter', EXAMHUB_ASSETS . 'js/filter.js', [ 'jquery', 'examhub-main' ], $filter_js_ver, true );
    }

    // Exam engine JS (only on exam pages)
    if ( is_singular( 'eh_exam' ) || examhub_is_exam_page() ) {
        wp_enqueue_script( 'examhub-exam-engine', EXAMHUB_ASSETS . 'js/exam-engine.js', [ 'examhub-main', 'sortablejs' ], $exam_js_ver, true );

        // Localize exam data for JS
        $exam_id = get_queried_object_id();
        wp_localize_script( 'examhub-exam-engine', 'examhubExam', examhub_get_exam_js_config( $exam_id ) );
    }

    // Subscription/payment JS
    if ( examhub_is_pricing_page() || examhub_is_checkout_page() ) {
        wp_enqueue_script( 'examhub-payment', EXAMHUB_ASSETS . 'js/payment.js', [ 'examhub-main' ], $ver, true );
    }

    // Global AJAX config
    wp_localize_script( 'examhub-main', 'examhubAjax', [
        'url'              => admin_url( 'admin-ajax.php' ),
        'nonce'            => wp_create_nonce( 'examhub_ajax' ),
        'user_id'          => get_current_user_id(),
        'is_logged_in'     => is_user_logged_in(),
        'login_url'        => function_exists( 'examhub_auth_page_url' ) ? examhub_auth_page_url( 'login', [ 'redirect_to' => home_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ) ] ) : wp_login_url(),
        'site_url'         => get_site_url(),
        'rest_url'         => rest_url( 'examhub/v1/' ),
        'rest_nonce'       => wp_create_nonce( 'wp_rest' ),
        'i18n'             => [
            'confirm_submit'   => __( 'هل تريد تسليم الامتحان؟', 'examhub' ),
            'time_warning'     => __( 'تبقى دقيقة واحدة!', 'examhub' ),
            'time_up'          => __( 'انتهى الوقت! سيتم تسليم الامتحان تلقائياً.', 'examhub' ),
            'connection_error' => __( 'خطأ في الاتصال. يرجى التحقق من الإنترنت.', 'examhub' ),
            'saved'            => __( 'تم الحفظ', 'examhub' ),
            'loading'          => __( 'جاري التحميل...', 'examhub' ),
            'free_exam_limit'  => (int) ( get_field( 'free_exams_per_day', 'option' ) ?: get_field( 'free_questions_per_day', 'option' ) ?: 1 ),
        ],
        'autosave_interval' => 30, // seconds
    ] );

}
add_action( 'wp_enqueue_scripts', 'examhub_enqueue_assets' );

/**
 * Admin enqueue.
 */
function examhub_admin_enqueue( $hook ) {
    $admin_css_ver = file_exists( EXAMHUB_DIR . '/assets/css/admin.css' ) ? filemtime( EXAMHUB_DIR . '/assets/css/admin.css' ) : EXAMHUB_VERSION;
    $admin_js_ver  = file_exists( EXAMHUB_DIR . '/assets/js/admin.js' ) ? filemtime( EXAMHUB_DIR . '/assets/js/admin.js' ) : EXAMHUB_VERSION;

    wp_enqueue_style( 'examhub-admin', EXAMHUB_ASSETS . 'css/admin.css', [], $admin_css_ver );
    wp_enqueue_script( 'examhub-admin', EXAMHUB_ASSETS . 'js/admin.js', [ 'jquery' ], $admin_js_ver, true );

    wp_localize_script( 'examhub-admin', 'examhubAdmin', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'examhub_admin_ajax' ),
    ] );
}
add_action( 'admin_enqueue_scripts', 'examhub_admin_enqueue' );

/**
 * Helper: Get exam JS config.
 */
function examhub_get_exam_js_config( $exam_id ) {
    if ( ! $exam_id ) return [];

    return [
        'exam_id'          => $exam_id,
        'timer_type'       => get_field( 'timer_type', $exam_id ) ?: 'none',
        'duration_minutes' => (int) get_field( 'exam_duration_minutes', $exam_id ),
        'seconds_per_q'    => (int) get_field( 'seconds_per_question', $exam_id ),
        'allow_skip'       => (bool) get_field( 'allow_skip', $exam_id ),
        'allow_review'     => (bool) get_field( 'allow_mark_review', $exam_id ),
        'allow_resume'     => (bool) get_field( 'allow_resume', $exam_id ),
        'autosave_url'     => admin_url( 'admin-ajax.php' ),
        'nonce'            => wp_create_nonce( 'examhub_exam_' . $exam_id ),
    ];
}

// Helper page detection functions
function examhub_is_exam_page()      { return is_page( [ 'exam', 'take-exam', 'امتحان' ] ); }
function examhub_is_dashboard_page() { return is_page( [ 'dashboard', 'my-account', 'student-dashboard', 'لوحة-التحكم' ] ); }
function examhub_is_pricing_page()   { return is_page( [ 'pricing', 'plans', 'subscribe', 'الاشتراك' ] ); }
function examhub_is_checkout_page()  { return is_page( [ 'checkout', 'payment', 'الدفع' ] ); }
function examhub_page_has_math() {
    global $post;
    if ( ! $post ) return false;
    if ( is_singular( 'eh_exam' ) || is_singular( 'eh_question' ) ) return true;
    return false;
}

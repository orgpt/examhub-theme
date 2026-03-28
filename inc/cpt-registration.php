<?php
/**
 * ExamHub — Custom Post Types Registration
 * Registers all CPTs: education systems, stages, grades, subjects,
 * units, lessons, exams, questions, results, subscriptions, payments.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register all ExamHub Custom Post Types.
 */
function examhub_register_cpts() {

    // ─── 1. Education System ───────────────────────────────────────────────
    register_post_type( 'eh_education_system', [
        'labels'       => examhub_cpt_labels( 'نظام تعليمي', 'أنظمة تعليمية', 'Education System', 'Education Systems' ),
        'public'       => true,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => true,
        'supports'     => [ 'title', 'thumbnail', 'editor' ],
        'menu_icon'    => 'dashicons-welcome-learn-more',
        'rewrite'      => [ 'slug' => 'education-system' ],
        'has_archive'  => false,
    ] );

    // ─── 2. Stage (المرحلة) ─────────────────────────────────────────────────
    register_post_type( 'eh_stage', [
        'labels'       => examhub_cpt_labels( 'مرحلة', 'مراحل', 'Stage', 'Stages' ),
        'public'       => true,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => true,
        'supports'     => [ 'title', 'thumbnail', 'editor', 'page-attributes' ],
        'menu_icon'    => 'dashicons-category',
        'rewrite'      => [ 'slug' => 'stage' ],
        'has_archive'  => false,
    ] );

    // ─── 3. Grade (الصف) ───────────────────────────────────────────────────
    register_post_type( 'eh_grade', [
        'labels'       => examhub_cpt_labels( 'صف دراسي', 'صفوف دراسية', 'Grade', 'Grades' ),
        'public'       => true,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => true,
        'supports'     => [ 'title', 'thumbnail', 'page-attributes' ],
        'menu_icon'    => 'dashicons-editor-ol',
        'rewrite'      => [ 'slug' => 'grade' ],
        'has_archive'  => false,
    ] );

    // ─── 4. Subject (المادة) ───────────────────────────────────────────────
    register_post_type( 'eh_subject', [
        'labels'       => examhub_cpt_labels( 'مادة', 'مواد دراسية', 'Subject', 'Subjects' ),
        'public'       => true,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => true,
        'supports'     => [ 'title', 'thumbnail', 'editor' ],
        'menu_icon'    => 'dashicons-book',
        'rewrite'      => [ 'slug' => 'subject' ],
        'has_archive'  => true,
    ] );

    // ─── 5. Unit (الوحدة) ──────────────────────────────────────────────────
    register_post_type( 'eh_unit', [
        'labels'       => examhub_cpt_labels( 'وحدة', 'وحدات', 'Unit', 'Units' ),
        'public'       => true,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => true,
        'supports'     => [ 'title', 'page-attributes' ],
        'menu_icon'    => 'dashicons-networking',
        'rewrite'      => [ 'slug' => 'unit' ],
        'has_archive'  => false,
    ] );

    // ─── 6. Lesson (الدرس) ─────────────────────────────────────────────────
    register_post_type( 'eh_lesson', [
        'labels'       => examhub_cpt_labels( 'درس', 'دروس', 'Lesson', 'Lessons' ),
        'public'       => true,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => true,
        'supports'     => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
        'menu_icon'    => 'dashicons-media-document',
        'rewrite'      => [ 'slug' => 'lesson' ],
        'has_archive'  => false,
    ] );

    // ─── 7. Exam (الامتحان) ────────────────────────────────────────────────
    register_post_type( 'eh_exam', [
        'labels'       => examhub_cpt_labels( 'امتحان', 'امتحانات', 'Exam', 'Exams' ),
        'public'       => true,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => true,
        'supports'     => [ 'title', 'thumbnail', 'editor', 'author' ],
        'menu_icon'    => 'dashicons-clipboard',
        'rewrite'      => [ 'slug' => 'exam' ],
        'has_archive'  => true,
    ] );

    // ─── 8. Question (السؤال) ──────────────────────────────────────────────
    register_post_type( 'eh_question', [
        'labels'       => examhub_cpt_labels( 'سؤال', 'أسئلة', 'Question', 'Questions' ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => true,
        'supports'     => [ 'title', 'editor', 'author' ],
        'menu_icon'    => 'dashicons-editor-help',
        'rewrite'      => [ 'slug' => 'question' ],
        'has_archive'  => false,
        'capability_type' => 'post',
    ] );

    // ─── 9. Result (النتيجة) ──────────────────────────────────────────────
    register_post_type( 'eh_result', [
        'labels'       => examhub_cpt_labels( 'نتيجة', 'نتائج', 'Result', 'Results' ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => false,
        'supports'     => [ 'title', 'author' ],
        'menu_icon'    => 'dashicons-chart-bar',
        'rewrite'      => false,
        'has_archive'  => false,
        'capabilities' => [
            'create_posts' => 'do_not_allow', // Only created programmatically
        ],
    ] );

    // ─── 10. Subscription (الاشتراك) ──────────────────────────────────────
    register_post_type( 'eh_subscription', [
        'labels'       => examhub_cpt_labels( 'اشتراك', 'اشتراكات', 'Subscription', 'Subscriptions' ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => false,
        'supports'     => [ 'title', 'author' ],
        'menu_icon'    => 'dashicons-star-filled',
        'rewrite'      => false,
        'capabilities' => [
            'create_posts' => 'do_not_allow',
        ],
    ] );

    // ─── 11. Payment (الدفع) ──────────────────────────────────────────────
    register_post_type( 'eh_payment', [
        'labels'       => examhub_cpt_labels( 'عملية دفع', 'عمليات دفع', 'Payment', 'Payments' ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => false,
        'supports'     => [ 'title', 'author' ],
        'menu_icon'    => 'dashicons-money-alt',
        'rewrite'      => false,
        'capabilities' => [
            'create_posts' => 'do_not_allow',
        ],
    ] );

    // ─── 12. Badge (الشارة) ───────────────────────────────────────────────
    register_post_type( 'eh_badge', [
        'labels'       => examhub_cpt_labels( 'شارة', 'شارات', 'Badge', 'Badges' ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => false,
        'supports'     => [ 'title', 'thumbnail', 'editor' ],
        'menu_icon'    => 'dashicons-awards',
        'rewrite'      => false,
    ] );
}
add_action( 'init', 'examhub_register_cpts', 5 );

/**
 * Add admin menu parent for ExamHub content.
 */
function examhub_add_admin_menu() {
    add_menu_page(
        __( 'ExamHub Content', 'examhub' ),
        __( 'ExamHub Content', 'examhub' ),
        'manage_options',
        'examhub-content',
        '__return_null',
        'dashicons-welcome-learn-more',
        20
    );
}
add_action( 'admin_menu', 'examhub_add_admin_menu' );

/**
 * Helper: Build standard CPT labels array.
 *
 * @param string $singular_ar Arabic singular
 * @param string $plural_ar   Arabic plural
 * @param string $singular_en English singular
 * @param string $plural_en   English plural
 * @return array
 */
function examhub_cpt_labels( $singular_ar, $plural_ar, $singular_en, $plural_en ) {
    return [
        'name'               => _x( $plural_en, 'post type general name', 'examhub' ),
        'singular_name'      => _x( $singular_en, 'post type singular name', 'examhub' ),
        'menu_name'          => _x( $plural_en, 'admin menu', 'examhub' ),
        'add_new'            => __( 'Add New', 'examhub' ),
        'add_new_item'       => sprintf( __( 'Add New %s', 'examhub' ), $singular_en ),
        'edit_item'          => sprintf( __( 'Edit %s', 'examhub' ), $singular_en ),
        'new_item'           => sprintf( __( 'New %s', 'examhub' ), $singular_en ),
        'view_item'          => sprintf( __( 'View %s', 'examhub' ), $singular_en ),
        'search_items'       => sprintf( __( 'Search %s', 'examhub' ), $plural_en ),
        'not_found'          => sprintf( __( 'No %s found', 'examhub' ), strtolower( $plural_en ) ),
        'not_found_in_trash' => sprintf( __( 'No %s in Trash', 'examhub' ), strtolower( $plural_en ) ),
        'all_items'          => sprintf( __( 'All %s', 'examhub' ), $plural_en ),
        'archives'           => sprintf( __( '%s Archives', 'examhub' ), $singular_en ),
        'name_ar'            => $plural_ar,
        'singular_name_ar'   => $singular_ar,
    ];
}

/**
 * Flush rewrite rules on theme activation (run once).
 */
function examhub_flush_rewrite_on_activation() {
    examhub_register_cpts();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'examhub_flush_rewrite_on_activation' );

// Also flush on theme switch
add_action( 'after_switch_theme', function() {
    examhub_register_cpts();
    flush_rewrite_rules();
} );

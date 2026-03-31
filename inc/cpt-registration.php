<?php
/**
 * ExamHub - Custom Post Types Registration
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

function examhub_get_core_capabilities() {
    return [
        'access_content' => 'examhub_access_content',
        'import_content' => 'examhub_import_content',
    ];
}

function examhub_get_cpt_capabilities( $singular, $plural, $create = true ) {
    return [
        'edit_post'              => "edit_{$singular}",
        'read_post'              => "read_{$singular}",
        'delete_post'            => "delete_{$singular}",
        'edit_posts'             => "edit_{$plural}",
        'edit_others_posts'      => "edit_others_{$plural}",
        'publish_posts'          => "publish_{$plural}",
        'read_private_posts'     => "read_private_{$plural}",
        'delete_posts'           => "delete_{$plural}",
        'delete_private_posts'   => "delete_private_{$plural}",
        'delete_published_posts' => "delete_published_{$plural}",
        'delete_others_posts'    => "delete_others_{$plural}",
        'edit_private_posts'     => "edit_private_{$plural}",
        'edit_published_posts'   => "edit_published_{$plural}",
        'create_posts'           => $create ? "edit_{$plural}" : 'do_not_allow',
    ];
}

function examhub_get_cpt_capability_map() {
    return [
        'eh_education_system'   => examhub_get_cpt_capabilities( 'eh_education_system', 'eh_education_systems' ),
        'eh_stage'              => examhub_get_cpt_capabilities( 'eh_stage', 'eh_stages' ),
        'eh_grade'              => examhub_get_cpt_capabilities( 'eh_grade', 'eh_grades' ),
        'eh_subject'            => examhub_get_cpt_capabilities( 'eh_subject', 'eh_subjects' ),
        'eh_lesson'             => examhub_get_cpt_capabilities( 'eh_lesson', 'eh_lessons' ),
        'eh_exam'               => examhub_get_cpt_capabilities( 'eh_exam', 'eh_exams' ),
        'eh_question'           => examhub_get_cpt_capabilities( 'eh_question', 'eh_questions' ),
        'eh_result'             => examhub_get_cpt_capabilities( 'eh_result', 'eh_results', false ),
        'eh_subscription'       => examhub_get_cpt_capabilities( 'eh_subscription', 'eh_subscriptions', false ),
        'eh_payment'            => examhub_get_cpt_capabilities( 'eh_payment', 'eh_payments', false ),
        'eh_badge'              => examhub_get_cpt_capabilities( 'eh_badge', 'eh_badges' ),
        'eh_affiliate_referral' => examhub_get_cpt_capabilities( 'eh_affiliate_referral', 'eh_affiliate_referrals', false ),
        'eh_affiliate_invite'   => examhub_get_cpt_capabilities( 'eh_affiliate_invite', 'eh_affiliate_invites', false ),
    ];
}

/**
 * Register all ExamHub Custom Post Types.
 */
function examhub_register_cpts() {
    $caps_map = examhub_get_cpt_capability_map();

    // 1. Education System
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
        'capabilities' => $caps_map['eh_education_system'],
        'map_meta_cap' => true,
    ] );

    // 2. Stage
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
        'capabilities' => $caps_map['eh_stage'],
        'map_meta_cap' => true,
    ] );

    // 3. Grade
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
        'capabilities' => $caps_map['eh_grade'],
        'map_meta_cap' => true,
    ] );

    // 4. Subject
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
        'capabilities' => $caps_map['eh_subject'],
        'map_meta_cap' => true,
    ] );

    // 5. Unit (legacy, no longer used in selection flow)
    register_post_type( 'eh_unit', [
        'labels'       => examhub_cpt_labels( 'وحدة', 'وحدات', 'Unit', 'Units' ),
        'public'       => false,
        'show_ui'      => false,
        'show_in_menu' => false,
        'show_in_rest' => false,
        'supports'     => [ 'title', 'page-attributes' ],
        'menu_icon'    => 'dashicons-networking',
        'rewrite'      => [ 'slug' => 'unit' ],
        'has_archive'  => false,
    ] );

    // 6. Question Group (stored in eh_lesson for backward compatibility)
    register_post_type( 'eh_lesson', [
        'labels'       => examhub_cpt_labels( 'مجموعة أسئلة', 'مجموعات الأسئلة', 'Question Group', 'Question Groups' ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => false,
        'supports'     => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
        'menu_icon'    => 'dashicons-media-document',
        'rewrite'      => [ 'slug' => 'question-group' ],
        'has_archive'  => false,
        'capabilities' => $caps_map['eh_lesson'],
        'map_meta_cap' => true,
    ] );

    // 7. Exam
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
        'capabilities' => $caps_map['eh_exam'],
        'map_meta_cap' => true,
    ] );

    // 8. Question
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
        'capabilities' => $caps_map['eh_question'],
        'map_meta_cap' => true,
    ] );

    // 9. Result
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
        'capabilities' => $caps_map['eh_result'],
        'map_meta_cap' => true,
    ] );

    // 10. Subscription
    register_post_type( 'eh_subscription', [
        'labels'       => examhub_cpt_labels( 'اشتراك', 'اشتراكات', 'Subscription', 'Subscriptions' ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => false,
        'supports'     => [ 'title', 'author' ],
        'menu_icon'    => 'dashicons-star-filled',
        'rewrite'      => false,
        'capabilities' => $caps_map['eh_subscription'],
        'map_meta_cap' => true,
    ] );

    // 11. Payment
    register_post_type( 'eh_payment', [
        'labels'       => examhub_cpt_labels( 'عملية دفع', 'عمليات دفع', 'Payment', 'Payments' ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => false,
        'supports'     => [ 'title', 'author' ],
        'menu_icon'    => 'dashicons-money-alt',
        'rewrite'      => false,
        'capabilities' => $caps_map['eh_payment'],
        'map_meta_cap' => true,
    ] );

    // 12. Badge
    register_post_type( 'eh_badge', [
        'labels'       => examhub_cpt_labels( 'شارة', 'شارات', 'Badge', 'Badges' ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => false,
        'supports'     => [ 'title', 'thumbnail', 'editor' ],
        'menu_icon'    => 'dashicons-awards',
        'rewrite'      => false,
        'capabilities' => $caps_map['eh_badge'],
        'map_meta_cap' => true,
    ] );

    // 13. Affiliate Referral
    register_post_type( 'eh_affiliate_referral', [
        'labels'       => examhub_cpt_labels( 'إحالة أفلييت', 'إحالات أفلييت', 'Affiliate Referral', 'Affiliate Referrals' ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => false,
        'supports'     => [ 'title', 'author' ],
        'menu_icon'    => 'dashicons-megaphone',
        'rewrite'      => false,
        'capabilities' => $caps_map['eh_affiliate_referral'],
        'map_meta_cap' => true,
    ] );

    // 14. Affiliate Invite
    register_post_type( 'eh_affiliate_invite', [
        'labels'       => examhub_cpt_labels( 'دعوة أفلييت', 'دعوات أفلييت', 'Affiliate Invite', 'Affiliate Invites' ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => 'examhub-content',
        'show_in_rest' => false,
        'supports'     => [ 'title', 'author' ],
        'menu_icon'    => 'dashicons-email-alt',
        'rewrite'      => false,
        'capabilities' => $caps_map['eh_affiliate_invite'],
        'map_meta_cap' => true,
    ] );
}
add_action( 'init', 'examhub_register_cpts', 5 );

/**
 * Add admin menu parent for ExamHub content.
 */
function examhub_add_admin_menu() {
    $core_caps = examhub_get_core_capabilities();

    add_menu_page(
        __( 'ExamHub Content', 'examhub' ),
        __( 'ExamHub Content', 'examhub' ),
        $core_caps['access_content'],
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


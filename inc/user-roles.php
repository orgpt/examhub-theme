<?php
/**
 * ExamHub — User Roles & Capabilities
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register custom roles on theme activation.
 */
function examhub_register_roles() {
    // Student role
    if ( ! get_role( 'eh_student' ) ) {
        add_role( 'eh_student', __( 'طالب', 'examhub' ), [
            'read'             => true,
            'eh_take_exam'     => true,
            'eh_view_results'  => true,
        ] );
    }

    // Content Manager
    if ( ! get_role( 'eh_content_manager' ) ) {
        add_role( 'eh_content_manager', __( 'مدير محتوى', 'examhub' ), [
            'read'                      => true,
            'upload_files'              => true,
            'edit_posts'                => true,
            'edit_published_posts'      => true,
            'publish_posts'             => true,
            'delete_posts'              => true,
            'edit_eh_questions'         => true,
            'edit_eh_exams'             => true,
            'manage_eh_question_bank'   => true,
        ] );
    }

    // Add ExamHub caps to administrator
    $admin = get_role( 'administrator' );
    if ( $admin ) {
        $examhub_caps = [
            'eh_take_exam',
            'eh_view_results',
            'eh_manage_subscriptions',
            'eh_manage_payments',
            'eh_manage_question_bank',
            'eh_approve_payments',
            'eh_view_analytics',
            'edit_eh_questions',
            'edit_eh_exams',
        ];
        foreach ( $examhub_caps as $cap ) {
            $admin->add_cap( $cap );
        }
    }
}
add_action( 'init', 'examhub_register_roles' );

/**
 * Auto-assign student role to new registrations.
 */
function examhub_set_default_role( $user_id ) {
    $user = new WP_User( $user_id );
    $user->set_role( 'eh_student' );
}
add_action( 'user_register', 'examhub_set_default_role' );

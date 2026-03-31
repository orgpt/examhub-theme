<?php
/**
 * ExamHub - User Roles & Capabilities
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register and sync custom roles/capabilities.
 */
function examhub_register_roles() {
    $core_caps = function_exists( 'examhub_get_core_capabilities' )
        ? examhub_get_core_capabilities()
        : [
            'access_content' => 'examhub_access_content',
            'import_content' => 'examhub_import_content',
        ];

    $cpt_caps = [];
    if ( function_exists( 'examhub_get_cpt_capability_map' ) ) {
        foreach ( examhub_get_cpt_capability_map() as $post_type_caps ) {
            foreach ( $post_type_caps as $cap ) {
                if ( 'do_not_allow' !== $cap ) {
                    $cpt_caps[] = $cap;
                }
            }
        }
        $cpt_caps = array_values( array_unique( $cpt_caps ) );
    }

    if ( ! get_role( 'eh_student' ) ) {
        add_role( 'eh_student', __( 'طالب', 'examhub' ), [
            'read'            => true,
            'eh_take_exam'    => true,
            'eh_view_results' => true,
        ] );
    }

    if ( ! get_role( 'eh_content_manager' ) ) {
        add_role( 'eh_content_manager', __( 'مدير محتوى', 'examhub' ), [
            'read' => true,
        ] );
    }

    $content_manager = get_role( 'eh_content_manager' );
    if ( $content_manager ) {
        $content_caps = array_merge(
            [
                'read',
                'upload_files',
                'edit_posts',
                'edit_published_posts',
                'publish_posts',
                'delete_posts',
                'edit_eh_questions',
                'edit_eh_exams',
                'manage_eh_question_bank',
                $core_caps['access_content'],
                $core_caps['import_content'],
            ],
            $cpt_caps
        );

        foreach ( array_unique( $content_caps ) as $cap ) {
            $content_manager->add_cap( $cap );
        }
    }

    $admin = get_role( 'administrator' );
    if ( $admin ) {
        $admin_caps = array_merge(
            [
                'eh_take_exam',
                'eh_view_results',
                'eh_manage_subscriptions',
                'eh_manage_payments',
                'eh_manage_question_bank',
                'eh_approve_payments',
                'eh_view_analytics',
                'edit_eh_questions',
                'edit_eh_exams',
                $core_caps['access_content'],
                $core_caps['import_content'],
            ],
            $cpt_caps
        );

        foreach ( array_unique( $admin_caps ) as $cap ) {
            $admin->add_cap( $cap );
        }
    }
}
add_action( 'init', 'examhub_register_roles' );

/**
 * Auto-assign student role to new registrations.
 *
 * @param int $user_id User ID.
 */
function examhub_set_default_role( $user_id ) {
    $user = new WP_User( $user_id );
    $user->set_role( 'eh_student' );
}
add_action( 'user_register', 'examhub_set_default_role' );

<?php
/**
 * ExamHub - Waitlist subscriptions for empty exam states.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

function examhub_get_waitlist_context( $args = [] ) {
    $context = wp_parse_args(
        $args,
        [
            'education_system' => 0,
            'stage'            => 0,
            'grade'            => 0,
            'subject'          => 0,
            'difficulty'       => '',
        ]
    );

    $context['type']             = 'exam_waitlist';
    $context['education_system'] = (int) $context['education_system'];
    $context['stage']            = (int) $context['stage'];
    $context['grade']            = (int) $context['grade'];
    $context['subject']          = (int) $context['subject'];
    $context['difficulty']       = sanitize_key( $context['difficulty'] );
    $context['joined']           = is_user_logged_in() && examhub_user_has_waitlist_subscription( get_current_user_id(), $context );

    return $context;
}

function examhub_get_user_waitlists( $user_id ) {
    $entries = get_user_meta( $user_id, 'eh_exam_waitlist', false );

    if ( ! is_array( $entries ) ) {
        return [];
    }

    return array_values(
        array_filter(
            $entries,
            static fn( $entry ) => is_array( $entry ) && ! empty( $entry['waitlist_id'] )
        )
    );
}

function examhub_normalize_waitlist( $data ) {
    return [
        'education_system' => (int) ( $data['education_system'] ?? $data['system_id'] ?? 0 ),
        'stage'            => (int) ( $data['stage'] ?? $data['stage_id'] ?? 0 ),
        'grade'            => (int) ( $data['grade'] ?? $data['grade_id'] ?? 0 ),
        'subject'          => (int) ( $data['subject'] ?? $data['subject_id'] ?? 0 ),
        'difficulty'       => sanitize_key( (string) ( $data['difficulty'] ?? '' ) ),
    ];
}

function examhub_waitlist_signature( $data ) {
    $data = examhub_normalize_waitlist( $data );
    return implode( ':', [ $data['education_system'], $data['stage'], $data['grade'], $data['subject'], $data['difficulty'] ] );
}

function examhub_user_has_waitlist_subscription( $user_id, $data ) {
    $target = examhub_waitlist_signature( $data );

    foreach ( examhub_get_user_waitlists( $user_id ) as $entry ) {
        if ( examhub_waitlist_signature( $entry ) === $target ) {
            return true;
        }
    }

    return false;
}

function examhub_waitlist_matches_exam( $entry, $exam_id ) {
    $entry = examhub_normalize_waitlist( $entry );

    $exam_context = [
        'education_system' => (int) get_field( 'exam_education_system', $exam_id ),
        'stage'            => (int) get_field( 'exam_stage', $exam_id ),
        'grade'            => (int) get_field( 'exam_grade', $exam_id ),
        'subject'          => (int) get_field( 'exam_subject', $exam_id ),
        'difficulty'       => sanitize_key( (string) get_field( 'exam_difficulty', $exam_id ) ),
    ];

    foreach ( $entry as $key => $value ) {
        if ( empty( $value ) ) {
            continue;
        }

        if ( (string) $exam_context[ $key ] !== (string) $value ) {
            return false;
        }
    }

    return true;
}

function examhub_waitlist_label_for_context( $context ) {
    $parts = [];

    $map = [
        'education_system' => 'eh_education_system',
        'stage'            => 'eh_stage',
        'grade'            => 'eh_grade',
        'subject'          => 'eh_subject',
    ];

    foreach ( $map as $key => $post_type ) {
        $id = (int) ( $context[ $key ] ?? 0 );
        if ( $id ) {
            $parts[] = get_the_title( $id );
        }
    }

    if ( ! empty( $context['difficulty'] ) ) {
        $parts[] = examhub_difficulty_label( $context['difficulty'] );
    }

    return implode( ' - ', array_filter( $parts ) );
}

function examhub_store_waitlist_notification_log( $user_id, $exam_id ) {
    $log   = get_user_meta( $user_id, 'eh_waitlist_notifications', true );
    $log   = is_array( $log ) ? $log : [];
    $log[] = [
        'exam_id'    => (int) $exam_id,
        'sent_at'    => current_time( 'mysql' ),
        'title'      => get_the_title( $exam_id ),
        'exam_url'   => get_permalink( $exam_id ),
    ];
    $log = array_slice( $log, -20 );

    update_user_meta( $user_id, 'eh_waitlist_notifications', $log );
}

function examhub_send_waitlist_exam_available_email( $user_id, $exam_id, $entry ) {
    if ( ! examhub_user_allows_email( $user_id, 'product_updates' ) ) {
        return false;
    }

    $user = get_userdata( $user_id );
    if ( ! $user || ! is_email( $user->user_email ) ) {
        return false;
    }

    $label = examhub_waitlist_label_for_context( $entry );

    return examhub_send_email(
        $user->user_email,
        __( 'تم إضافة امتحان جديد مناسب لك', 'examhub' ),
        [
            'preheader' => __( 'هناك امتحان جديد مطابق لاختياراتك في قائمة الانتظار.', 'examhub' ),
            'heading'   => __( 'وصل امتحان جديد إلى قائمتك', 'examhub' ),
            'intro'     => $label
                ? sprintf( __( 'أضفنا امتحانًا جديدًا ضمن المسار الذي طلبت متابعته: %s', 'examhub' ), $label )
                : __( 'أضفنا امتحانًا جديدًا ضمن المسار الذي طلبت متابعته.', 'examhub' ),
            'body'      => sprintf(
                '%s <strong>%s</strong><br>%s',
                esc_html__( 'الامتحان المتاح الآن:', 'examhub' ),
                esc_html( get_the_title( $exam_id ) ),
                esc_html__( 'ابدأ التدريب الآن قبل امتلاء قائمتك بامتحانات جديدة.', 'examhub' )
            ),
            'cta_label' => __( 'ابدأ الامتحان الآن', 'examhub' ),
            'cta_url'   => get_permalink( $exam_id ),
        ]
    );
}

add_action( 'wp_ajax_eh_join_exam_waitlist', 'examhub_ajax_join_exam_waitlist' );
add_action( 'wp_ajax_nopriv_eh_join_exam_waitlist', 'examhub_ajax_join_exam_waitlist' );
function examhub_ajax_join_exam_waitlist() {
    check_ajax_referer( 'examhub_ajax', 'nonce' );

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        wp_send_json_error( [ 'message' => __( 'يجب تسجيل الدخول أولاً للانضمام لقائمة الانتظار.', 'examhub' ) ], 401 );
    }

    $entry = [
        'waitlist_id'       => wp_generate_uuid4(),
        'education_system'  => (int) ( $_POST['system_id'] ?? 0 ),
        'stage'             => (int) ( $_POST['stage_id'] ?? 0 ),
        'grade'             => (int) ( $_POST['grade_id'] ?? 0 ),
        'subject'           => (int) ( $_POST['subject_id'] ?? 0 ),
        'difficulty'        => sanitize_key( (string) ( $_POST['difficulty'] ?? '' ) ),
        'created_at'        => current_time( 'mysql' ),
        'notified_exam_ids' => [],
    ];

    if ( examhub_user_has_waitlist_subscription( $user_id, $entry ) ) {
        wp_send_json_success( [ 'message' => __( 'أنت منضم بالفعل لهذه القائمة، وسنرسل لك إشعارًا فور توفر امتحانات جديدة.', 'examhub' ) ] );
    }

    add_user_meta( $user_id, 'eh_exam_waitlist', $entry, false );

    wp_send_json_success( [ 'message' => __( 'تم تسجيلك بنجاح. سنرسل لك إشعارًا فور نزول امتحانات جديدة مناسبة لك.', 'examhub' ) ] );
}

add_action(
    'transition_post_status',
    function( $new_status, $old_status, $post ) {
        if ( 'eh_exam' !== $post->post_type || 'publish' !== $new_status || 'publish' === $old_status ) {
            return;
        }

        $user_ids = get_users(
            [
                'fields'   => 'ids',
                'meta_key' => 'eh_exam_waitlist',
            ]
        );

        foreach ( $user_ids as $user_id ) {
            $entries = examhub_get_user_waitlists( $user_id );

            foreach ( $entries as $entry ) {
                if ( ! examhub_waitlist_matches_exam( $entry, $post->ID ) ) {
                    continue;
                }

                $already_notified = in_array( $post->ID, array_map( 'intval', (array) ( $entry['notified_exam_ids'] ?? [] ) ), true );
                if ( $already_notified ) {
                    continue;
                }

                $updated = $entry;
                $updated['notified_exam_ids']   = array_values( array_unique( array_merge( (array) ( $entry['notified_exam_ids'] ?? [] ), [ $post->ID ] ) ) );
                $updated['last_notified_at']    = current_time( 'mysql' );
                $updated['last_notified_exam']  = $post->ID;

                update_user_meta( $user_id, 'eh_exam_waitlist', $updated, $entry );
                examhub_store_waitlist_notification_log( $user_id, $post->ID );
                examhub_send_waitlist_exam_available_email( $user_id, $post->ID, $entry );
            }
        }
    },
    10,
    3
);

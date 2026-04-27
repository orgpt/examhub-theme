<?php
/**
 * ExamHub - Teacher publishing workflow.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'examhub_register_teacher_rewrites', 25 );
function examhub_register_teacher_rewrites() {
    add_rewrite_tag( '%eh_teachers_page%', '([0-1])' );
    add_rewrite_rule( '^for-teachers/?$', 'index.php?eh_teachers_page=1', 'top' );
}

add_filter( 'query_vars', 'examhub_register_teacher_query_vars' );
function examhub_register_teacher_query_vars( $vars ) {
    $vars[] = 'eh_teachers_page';
    return $vars;
}

add_filter( 'template_include', 'examhub_teacher_template_loader' );
function examhub_teacher_template_loader( $template ) {
    if ( get_query_var( 'eh_teachers_page' ) ) {
        return EXAMHUB_DIR . '/page-for-teachers.php';
    }

    return $template;
}

function examhub_get_teachers_page_url() {
    return home_url( '/?eh_teachers_page=1' );
}

add_action( 'acf/init', 'examhub_register_teacher_request_fields', 35 );
function examhub_register_teacher_request_fields() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key'    => 'group_examhub_teacher_request',
        'title'  => 'بيانات طلب المدرس',
        'fields' => [
            [ 'key' => 'field_teacher_name', 'label' => 'اسم المدرس', 'name' => 'teacher_name', 'type' => 'text' ],
            [ 'key' => 'field_teacher_year', 'label' => 'العام الدراسي', 'name' => 'teacher_academic_year', 'type' => 'text' ],
            [ 'key' => 'field_teacher_subject', 'label' => 'المادة', 'name' => 'teacher_subject', 'type' => 'text' ],
            [ 'key' => 'field_teacher_exam_title', 'label' => 'عنوان الامتحان', 'name' => 'teacher_exam_title', 'type' => 'text' ],
            [ 'key' => 'field_teacher_email', 'label' => 'إيميل المدرس', 'name' => 'teacher_email', 'type' => 'email' ],
            [ 'key' => 'field_teacher_phone', 'label' => 'رقم الهاتف', 'name' => 'teacher_phone', 'type' => 'text' ],
            [ 'key' => 'field_teacher_secret_enabled', 'label' => 'تفعيل كود امتحان سري', 'name' => 'teacher_secret_enabled', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0 ],
            [ 'key' => 'field_teacher_secret_code', 'label' => 'الكود السري المولد', 'name' => 'teacher_secret_code', 'type' => 'text' ],
            [ 'key' => 'field_teacher_file', 'label' => 'ورقة الامتحان', 'name' => 'teacher_exam_file', 'type' => 'file', 'return_format' => 'id', 'mime_types' => 'pdf,doc,docx,jpg,jpeg,png,webp' ],
            [ 'key' => 'field_teacher_status', 'label' => 'الحالة', 'name' => 'teacher_request_status', 'type' => 'select', 'choices' => [ 'pending' => 'قيد المراجعة', 'reviewed' => 'تمت المراجعة', 'published' => 'تم النشر', 'rejected' => 'مرفوض' ], 'default_value' => 'pending' ],
            [ 'key' => 'field_teacher_notes', 'label' => 'ملاحظات الإدارة', 'name' => 'teacher_admin_notes', 'type' => 'textarea', 'rows' => 4 ],
            [ 'key' => 'field_teacher_related_exam', 'label' => 'الامتحان المنشور', 'name' => 'teacher_related_exam', 'type' => 'post_object', 'post_type' => [ 'eh_exam' ], 'return_format' => 'id', 'ui' => 1 ],
            [ 'key' => 'field_teacher_sent_at', 'label' => 'تاريخ إرسال الإيميل', 'name' => 'teacher_email_sent_at', 'type' => 'date_time_picker' ],
        ],
        'location' => [
            [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'eh_teacher_request' ] ],
        ],
    ] );
}

add_action( 'admin_post_nopriv_examhub_teacher_submit', 'examhub_handle_teacher_submission' );
add_action( 'admin_post_examhub_teacher_submit', 'examhub_handle_teacher_submission' );
add_action( 'admin_post_nopriv_examhub_exam_secret_unlock', 'examhub_handle_exam_secret_unlock' );
add_action( 'admin_post_examhub_exam_secret_unlock', 'examhub_handle_exam_secret_unlock' );
function examhub_handle_teacher_submission() {
    check_admin_referer( 'examhub_teacher_submit', 'examhub_teacher_nonce' );

    $redirect = examhub_get_teachers_page_url();
    $teacher_name  = sanitize_text_field( wp_unslash( $_POST['teacher_name'] ?? '' ) );
    $academic_year = sanitize_text_field( wp_unslash( $_POST['teacher_academic_year'] ?? '' ) );
    $subject       = sanitize_text_field( wp_unslash( $_POST['teacher_subject'] ?? '' ) );
    $exam_title    = sanitize_text_field( wp_unslash( $_POST['teacher_exam_title'] ?? '' ) );
    $teacher_email = sanitize_email( wp_unslash( $_POST['teacher_email'] ?? '' ) );
    $teacher_phone = sanitize_text_field( wp_unslash( $_POST['teacher_phone'] ?? '' ) );
    $secret_enabled = ! empty( $_POST['teacher_secret_enabled'] );

    if ( '' === $teacher_name || '' === $academic_year || '' === $subject || ! is_email( $teacher_email ) || '' === $teacher_phone ) {
        wp_safe_redirect( add_query_arg( 'teacher_submit', 'missing', $redirect ) );
        exit;
    }

    if ( empty( $_FILES['teacher_exam_file']['name'] ) ) {
        wp_safe_redirect( add_query_arg( 'teacher_submit', 'missing_file', $redirect ) );
        exit;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $request_id = wp_insert_post( [
        'post_type'   => 'eh_teacher_request',
        'post_status' => 'publish',
        'post_title'  => $exam_title ? $exam_title : sprintf( 'طلب مدرس - %s - %s', $teacher_name, $subject ),
        'post_author' => is_user_logged_in() ? get_current_user_id() : 0,
    ] );

    if ( is_wp_error( $request_id ) ) {
        wp_safe_redirect( add_query_arg( 'teacher_submit', 'failed', $redirect ) );
        exit;
    }

    $attachment_id = media_handle_upload( 'teacher_exam_file', $request_id );
    if ( is_wp_error( $attachment_id ) ) {
        wp_delete_post( $request_id, true );
        wp_safe_redirect( add_query_arg( 'teacher_submit', 'upload_failed', $redirect ) );
        exit;
    }

    update_field( 'teacher_name', $teacher_name, $request_id );
    update_field( 'teacher_academic_year', $academic_year, $request_id );
    update_field( 'teacher_subject', $subject, $request_id );
    update_field( 'teacher_exam_title', $exam_title, $request_id );
    update_field( 'teacher_email', $teacher_email, $request_id );
    update_field( 'teacher_phone', $teacher_phone, $request_id );
    update_field( 'teacher_secret_enabled', $secret_enabled ? 1 : 0, $request_id );
    update_field( 'teacher_secret_code', $secret_enabled ? examhub_generate_exam_secret_code() : '', $request_id );
    update_field( 'teacher_exam_file', $attachment_id, $request_id );
    update_field( 'teacher_request_status', 'pending', $request_id );

    $notify_email = get_option( 'admin_email' );
    if ( function_exists( 'examhub_send_email' ) ) {
        examhub_send_email(
            $notify_email,
            __( 'طلب امتحان جديد من مدرس', 'examhub' ),
            [
                'heading' => __( 'تم استلام طلب مدرس جديد', 'examhub' ),
                'intro'   => __( 'يوجد طلب جديد بانتظار المراجعة من لوحة التحكم.', 'examhub' ),
                'body'    => sprintf(
                    '%s <strong>%s</strong><br>%s <strong>%s</strong><br>%s <strong>%s</strong><br>%s <strong>%s</strong>',
                    esc_html__( 'المدرس:', 'examhub' ),
                    esc_html( $teacher_name ),
                    esc_html__( 'المادة:', 'examhub' ),
                    esc_html( $subject ),
                    esc_html__( 'العام الدراسي:', 'examhub' ),
                    esc_html( $academic_year ),
                    esc_html__( 'الإيميل:', 'examhub' ),
                    esc_html( $teacher_email )
                ),
                'cta_label' => __( 'فتح الطلب', 'examhub' ),
                'cta_url'   => admin_url( 'post.php?post=' . $request_id . '&action=edit' ),
            ]
        );
    }

    wp_safe_redirect( add_query_arg( 'teacher_submit', 'success', $redirect ) );
    exit;
}

function examhub_handle_exam_secret_unlock() {
    check_admin_referer( 'examhub_exam_secret_unlock', 'examhub_exam_secret_nonce' );

    $exam_id   = absint( $_POST['exam_id'] ?? 0 );
    $secret    = strtoupper( sanitize_text_field( wp_unslash( $_POST['exam_secret_code'] ?? '' ) ) );
    $redirect  = $exam_id ? get_permalink( $exam_id ) : home_url();

    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( $redirect ) );
        exit;
    }

    if ( ! $exam_id || 'eh_exam' !== get_post_type( $exam_id ) || ! examhub_exam_requires_secret_code( $exam_id ) ) {
        wp_safe_redirect( add_query_arg( 'exam_secret', 'invalid_exam', $redirect ) );
        exit;
    }

    if ( '' === $secret || ! hash_equals( examhub_get_exam_secret_code( $exam_id ), $secret ) ) {
        delete_user_meta( get_current_user_id(), examhub_get_exam_secret_user_meta_key( $exam_id ) );
        wp_safe_redirect( add_query_arg( 'exam_secret', 'invalid', $redirect ) );
        exit;
    }

    update_user_meta( get_current_user_id(), examhub_get_exam_secret_user_meta_key( $exam_id ), $secret );
    wp_safe_redirect( add_query_arg( 'exam_secret', 'unlocked', $redirect ) );
    exit;
}

add_action( 'acf/save_post', 'examhub_maybe_send_teacher_exam_link', 30 );
function examhub_maybe_send_teacher_exam_link( $post_id ) {
    if ( ! is_numeric( $post_id ) ) {
        return;
    }

    $post_id = (int) $post_id;
    if ( 'eh_teacher_request' !== get_post_type( $post_id ) ) {
        return;
    }

    $status    = (string) get_field( 'teacher_request_status', $post_id );
    $exam_id   = (int) get_field( 'teacher_related_exam', $post_id );
    $email     = (string) get_field( 'teacher_email', $post_id );
    $sent_at   = (string) get_field( 'teacher_email_sent_at', $post_id );
    $secret_enabled = (bool) get_field( 'teacher_secret_enabled', $post_id );
    $secret_code    = strtoupper( sanitize_text_field( (string) get_field( 'teacher_secret_code', $post_id ) ) );

    if ( 'published' !== $status || ! $exam_id || ! is_email( $email ) || '' !== trim( $sent_at ) ) {
        return;
    }

    $exam_link = get_permalink( $exam_id );
    if ( ! $exam_link || ! function_exists( 'examhub_send_email' ) ) {
        return;
    }

    $teacher_name = (string) get_field( 'teacher_name', $post_id );
    $exam_title   = get_the_title( $exam_id );
    examhub_sync_teacher_secret_code_to_exam( $post_id, $exam_id );

    $code_block = '';
    if ( $secret_enabled && $secret_code ) {
        $code_block = sprintf(
            '<br><br>%s <strong style="font-size:20px;letter-spacing:.08em;">%s</strong><br>%s',
            esc_html__( 'كود الدخول السري:', 'examhub' ),
            esc_html( $secret_code ),
            esc_html__( 'شارك هذا الكود مع طلابك فقط، ولن يتمكنوا من بدء الامتحان بدونه.', 'examhub' )
        );
    }

    $sent = examhub_send_email(
        $email,
        __( 'تم نشر امتحانك وإرسال الرابط', 'examhub' ),
        [
            'preheader' => __( 'رابط الامتحان أصبح جاهزًا الآن.', 'examhub' ),
            'heading'   => __( 'تم نشر الامتحان بنجاح', 'examhub' ),
            'intro'     => sprintf( __( 'أهلًا %s، تم تجهيز الامتحان ورفعه على المنصة.', 'examhub' ), $teacher_name ?: __( 'أستاذنا', 'examhub' ) ),
            'body'      => sprintf(
                '%s <strong>%s</strong><br>%s <a href="%s">%s</a><br><br>%s%s',
                esc_html__( 'عنوان الامتحان:', 'examhub' ),
                esc_html( $exam_title ),
                esc_html__( 'رابط الامتحان:', 'examhub' ),
                esc_url( $exam_link ),
                esc_html( $exam_link ),
                esc_html__( 'يمكنك الآن مشاركة الرابط مع طلابك مباشرة.', 'examhub' ),
                $code_block
            ),
            'cta_label' => __( 'فتح الامتحان', 'examhub' ),
            'cta_url'   => $exam_link,
        ]
    );

    if ( $sent ) {
        update_field( 'teacher_email_sent_at', current_time( 'Y-m-d H:i:s' ), $post_id );
    }
}

function examhub_generate_exam_secret_code( $length = 6 ) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code     = '';
    $max      = strlen( $alphabet ) - 1;

    for ( $i = 0; $i < $length; $i++ ) {
        $code .= $alphabet[ wp_rand( 0, $max ) ];
    }

    return $code;
}

function examhub_sync_teacher_secret_code_to_exam( $request_id, $exam_id ) {
    $secret_enabled = (bool) get_field( 'teacher_secret_enabled', $request_id );
    $secret_code    = strtoupper( sanitize_text_field( (string) get_field( 'teacher_secret_code', $request_id ) ) );

    if ( ! $secret_enabled || '' === $secret_code ) {
        return;
    }

    update_field( 'exam_secret_enabled', 1, $exam_id );
    update_field( 'exam_secret_code', $secret_code, $exam_id );
}

add_filter( 'manage_eh_teacher_request_posts_columns', 'examhub_teacher_request_columns' );
function examhub_teacher_request_columns( $columns ) {
    $columns['teacher_name']   = 'المدرس';
    $columns['teacher_subject']= 'المادة';
    $columns['teacher_status'] = 'الحالة';
    $columns['teacher_exam']   = 'الامتحان';
    return $columns;
}

add_action( 'manage_eh_teacher_request_posts_custom_column', 'examhub_teacher_request_column_content', 10, 2 );
function examhub_teacher_request_column_content( $column, $post_id ) {
    if ( 'teacher_name' === $column ) {
        echo esc_html( (string) get_field( 'teacher_name', $post_id ) );
    }

    if ( 'teacher_subject' === $column ) {
        echo esc_html( (string) get_field( 'teacher_subject', $post_id ) );
    }

    if ( 'teacher_status' === $column ) {
        echo esc_html( (string) get_field( 'teacher_request_status', $post_id ) );
    }

    if ( 'teacher_exam' === $column ) {
        $exam_id = (int) get_field( 'teacher_related_exam', $post_id );
        if ( $exam_id ) {
            printf(
                '<a href="%s">%s</a>',
                esc_url( get_edit_post_link( $exam_id ) ),
                esc_html( get_the_title( $exam_id ) )
            );
        } else {
            echo '—';
        }
    }
}

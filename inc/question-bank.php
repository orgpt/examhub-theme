<?php
/**
 * ExamHub — Question Bank Utilities
 * Bulk edit, duplicate detection, admin column enhancements.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════════
// DUPLICATE DETECTION
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Find duplicate questions based on text similarity.
 * Called on save_post for eh_question.
 */
add_action( 'save_post_eh_question', 'examhub_detect_question_duplicate', 20, 2 );

function examhub_detect_question_duplicate( $post_id, $post ) {
    if ( $post->post_status === 'auto-draft' ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

    $text = get_field( 'question_text', $post_id );
    if ( ! $text ) return;

    // Normalize text for comparison
    $normalized = preg_replace( '/\s+/', ' ', mb_strtolower( trim( $text ) ) );

    global $wpdb;
    $similar = $wpdb->get_col( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'question_text'
        WHERE p.post_type = 'eh_question'
          AND p.post_status = 'publish'
          AND p.ID != %d
          AND pm.meta_value LIKE %s
        LIMIT 5",
        $post_id,
        '%' . $wpdb->esc_like( substr( $normalized, 0, 30 ) ) . '%'
    ) );

    if ( ! empty( $similar ) ) {
        update_field( 'is_duplicate', 1, $post_id );
        update_field( 'duplicate_of', (int) $similar[0], $post_id );
        // Add admin notice meta
        update_post_meta( $post_id, '_eh_possible_duplicate', $similar[0] );
    } else {
        update_field( 'is_duplicate', 0, $post_id );
        delete_post_meta( $post_id, '_eh_possible_duplicate' );
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// ADMIN COLUMNS — QUESTIONS
// ═══════════════════════════════════════════════════════════════════════════════

add_filter( 'manage_eh_question_posts_columns', 'examhub_question_columns' );
function examhub_question_columns( $cols ) {
    return [
        'cb'           => $cols['cb'],
        'title'        => __( 'نص السؤال', 'examhub' ),
        'q_type'       => __( 'النوع', 'examhub' ),
        'q_difficulty' => __( 'الصعوبة', 'examhub' ),
        'q_subject'    => __( 'المادة', 'examhub' ),
        'q_grade'      => __( 'الصف', 'examhub' ),
        'q_book'       => __( 'الكتاب', 'examhub' ),
        'q_ai'         => __( 'AI', 'examhub' ),
        'q_dup'        => __( 'مكرر', 'examhub' ),
        'q_usage'      => __( 'استخدام', 'examhub' ),
        'date'         => __( 'التاريخ', 'examhub' ),
    ];
}

add_action( 'manage_eh_question_posts_custom_column', 'examhub_question_column_content', 10, 2 );
function examhub_question_column_content( $col, $post_id ) {
    switch ( $col ) {
        case 'q_type':
            $types = [
                'mcq' => '📋', 'true_false' => '✓✗', 'correct' => '✅',
                'fill_blank' => '___', 'matching' => '🔗', 'ordering' => '📊',
                'essay' => '✍️', 'image' => '🖼️', 'math' => '∑',
            ];
            $t = get_field( 'question_type', $post_id );
            echo esc_html( ( $types[$t] ?? '?' ) . ' ' . $t );
            break;

        case 'q_difficulty':
            $d    = get_field( 'difficulty', $post_id );
            $colors = [ 'easy' => 'green', 'medium' => 'orange', 'hard' => 'red' ];
            $color = $colors[$d] ?? 'gray';
            echo "<span style='color:{$color};font-weight:bold;'>" . esc_html( examhub_difficulty_label( $d ) ) . '</span>';
            break;

        case 'q_subject':
            $sid = get_field( 'subject', $post_id );
            echo $sid ? esc_html( get_the_title( $sid ) ) : '—';
            break;

        case 'q_grade':
            $gid = get_field( 'grade', $post_id );
            echo $gid ? esc_html( get_field( 'grade_name_ar', $gid ) ?: get_the_title( $gid ) ) : '—';
            break;

        case 'q_book':
            $books = [
                'moasir' => 'المعاصر', 'imtihan' => 'الامتحان',
                'selah_tilmeed' => 'سلاح', 'ministry' => 'وزارة',
            ];
            echo esc_html( $books[ get_field( 'book_source', $post_id ) ] ?? '—' );
            break;

        case 'q_ai':
            echo get_field( 'ai_generated', $post_id ) ? '🤖' : '—';
            break;

        case 'q_dup':
            $dup = get_field( 'is_duplicate', $post_id );
            if ( $dup ) {
                $dup_id = get_field( 'duplicate_of', $post_id );
                echo '<span style="color:red;">⚠️ مكرر</span>';
                if ( $dup_id ) echo ' <a href="' . get_edit_post_link( $dup_id ) . '">#' . $dup_id . '</a>';
            } else {
                echo '✓';
            }
            break;

        case 'q_usage':
            echo (int) get_field( 'usage_count', $post_id );
            break;
    }
}

add_filter( 'manage_edit-eh_question_sortable_columns', 'examhub_question_sortable_columns' );
function examhub_question_sortable_columns( $cols ) {
    $cols['q_difficulty'] = 'difficulty';
    $cols['q_usage']      = 'usage_count';
    return $cols;
}

// Question Group admin columns (for easier data auditing)
add_filter( 'manage_eh_lesson_posts_columns', function( $cols ) {
    return [
        'cb'          => $cols['cb'],
        'title'       => __( 'Question Group', 'examhub' ),
        'les_subject' => __( 'Subject', 'examhub' ),
        'date'        => __( 'Date', 'examhub' ),
    ];
} );

add_action( 'manage_eh_lesson_posts_custom_column', function( $col, $post_id ) {
    if ( $col === 'les_subject' ) {
        $sid = (int) get_field( 'lesson_subject', $post_id );
        echo $sid ? esc_html( get_the_title( $sid ) ) : '-';
    }
}, 10, 2 );

// ═══════════════════════════════════════════════════════════════════════════════
// ADMIN COLUMNS — EXAMS
// ═══════════════════════════════════════════════════════════════════════════════

add_filter( 'manage_eh_exam_posts_columns', 'examhub_exam_columns' );
function examhub_exam_columns( $cols ) {
    return [
        'cb'           => $cols['cb'],
        'title'        => __( 'عنوان الامتحان', 'examhub' ),
        'ex_grade'     => __( 'الصف', 'examhub' ),
        'ex_subject'   => __( 'المادة', 'examhub' ),
        'ex_questions' => __( 'الأسئلة', 'examhub' ),
        'ex_timer'     => __( 'الوقت', 'examhub' ),
        'ex_access'    => __( 'الوصول', 'examhub' ),
        'ex_results'   => __( 'النتائج', 'examhub' ),
        'date'         => __( 'التاريخ', 'examhub' ),
    ];
}

add_action( 'manage_eh_exam_posts_custom_column', 'examhub_exam_column_content', 10, 2 );
function examhub_exam_column_content( $col, $post_id ) {
    switch ( $col ) {
        case 'ex_grade':
            $gid = get_field( 'exam_grade', $post_id );
            echo $gid ? esc_html( get_field( 'grade_name_ar', $gid ) ?: get_the_title( $gid ) ) : '—';
            break;

        case 'ex_subject':
            $sid = get_field( 'exam_subject', $post_id );
            echo $sid ? esc_html( get_the_title( $sid ) ) : '—';
            break;

        case 'ex_questions':
            $q = get_field( 'exam_questions', $post_id );
            echo is_array( $q ) ? count( $q ) : '0';
            break;

        case 'ex_timer':
            $type = get_field( 'timer_type', $post_id );
            if ( $type === 'exam' ) {
                echo (int) get_field( 'exam_duration_minutes', $post_id ) . ' دقيقة';
            } elseif ( $type === 'per_question' ) {
                echo (int) get_field( 'seconds_per_question', $post_id ) . 'ث/سؤال';
            } else {
                echo '—';
            }
            break;

        case 'ex_access':
            $access = get_field( 'exam_access', $post_id );
            $labels = [ 'free' => '🟢 مجاني', 'free_limit' => '🟡 محدود', 'subscribed' => '🔵 مشتركون' ];
            echo esc_html( $labels[$access] ?? $access );
            break;

        case 'ex_results':
            global $wpdb;
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='result_exam_id' AND pm.meta_value=%s
                WHERE p.post_type='eh_result' AND p.post_status='publish'", $post_id
            ) );
            echo (int) $count . ' محاولة';
            break;
    }
}

/**
 * Read field value from current ACF request (if present), fallback to saved value.
 */
function examhub_get_acf_request_value( $field_key, $saved_field_name, $post_id, $extra_keys = [] ) {
    $candidates = [];

    foreach ( $extra_keys as $extra_key ) {
        if ( isset( $_POST[ $extra_key ] ) ) {
            $candidates[] = $_POST[ $extra_key ];
        }
        if ( isset( $_POST['query'][ $extra_key ] ) ) {
            $candidates[] = $_POST['query'][ $extra_key ];
        }
    }

    if ( isset( $_POST['acf'] ) && is_array( $_POST['acf'] ) ) {
        $candidates[] = $_POST['acf'][ $field_key ] ?? null;
    }
    if ( isset( $_POST['query']['acf'] ) && is_array( $_POST['query']['acf'] ) ) {
        $candidates[] = $_POST['query']['acf'][ $field_key ] ?? null;
    }
    if ( isset( $_POST['query'][ $field_key ] ) ) {
        $candidates[] = $_POST['query'][ $field_key ];
    }
    if ( isset( $_POST[ $field_key ] ) ) {
        $candidates[] = $_POST[ $field_key ];
    }

    foreach ( $candidates as $value ) {
        $value = is_array( $value ) ? reset( $value ) : $value;
        $value = (int) $value;
        if ( $value > 0 ) {
            return $value;
        }
    }

    return (int) get_field( $saved_field_name, $post_id );
}

/**
 * Check if question group belongs to the selected subject.
 */
function examhub_lesson_matches_subject( $lesson_id, $subject_id ) {
    $lesson_id  = (int) $lesson_id;
    $subject_id = (int) $subject_id;

    if ( ! $lesson_id || ! $subject_id ) {
        return false;
    }

    $lesson_subject = (int) get_field( 'lesson_subject', $lesson_id );
    return $lesson_subject === $subject_id;
}

/**
 * Filter "subject" picker to selected grade.
 */
function examhub_filter_subject_by_grade( $args, $field, $post_id ) {
    $is_exam_field     = ( $field['key'] ?? '' ) === 'field_ex_subject';
    $grade_field_key   = $is_exam_field ? 'field_ex_grade' : 'field_q_grade';
    $saved_grade_field = $is_exam_field ? 'exam_grade' : 'grade';
    $grade_id          = examhub_get_acf_request_value( $grade_field_key, $saved_grade_field, $post_id, [ 'grade_id' ] );

    if ( ! $grade_id ) {
        $args['post__in'] = [ 0 ];
        return $args;
    }

    $subject_ids = get_posts( [
        'post_type'      => 'eh_subject',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'subject_grade',
                'value'   => $grade_id,
                'compare' => '=',
            ],
        ],
    ] );

    $args['post__in']     = ! empty( $subject_ids ) ? $subject_ids : [ 0 ];
    $args['post_status']  = 'publish';
    $args['orderby']      = 'title';
    $args['order']        = 'ASC';
    unset( $args['meta_query'] );

    return $args;
}
add_filter( 'acf/fields/post_object/query/key=field_ex_subject', 'examhub_filter_subject_by_grade', 10, 3 );
add_filter( 'acf/fields/post_object/query/key=field_q_subject', 'examhub_filter_subject_by_grade', 10, 3 );

/**
 * Filter "question group" picker to selected subject.
 */
function examhub_filter_lesson_by_subject( $args, $field, $post_id ) {
    $is_exam_field      = ( $field['key'] ?? '' ) === 'field_ex_lesson';
    $subject_field_key  = $is_exam_field ? 'field_ex_subject' : 'field_q_subject';
    $saved_subject_name = $is_exam_field ? 'exam_subject' : 'subject';
    $subject_id         = examhub_get_acf_request_value( $subject_field_key, $saved_subject_name, $post_id, [ 'subject_id' ] );

    if ( ! $subject_id ) {
        $args['post__in'] = [ 0 ];
        return $args;
    }

    $lesson_ids = get_posts( [
        'post_type'      => 'eh_lesson',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'lesson_subject',
                'value'   => $subject_id,
                'compare' => '=',
            ],
        ],
    ] );

    $args['post__in']     = ! empty( $lesson_ids ) ? $lesson_ids : [ 0 ];
    $args['post_status']  = 'publish';
    $args['orderby']      = 'title';
    $args['order']        = 'ASC';
    unset( $args['meta_query'] );

    return $args;
}
add_filter( 'acf/fields/post_object/query/key=field_ex_lesson', 'examhub_filter_lesson_by_subject', 10, 3 );
add_filter( 'acf/fields/post_object/query/key=field_q_lesson', 'examhub_filter_lesson_by_subject', 10, 3 );

/**
 * Ensure selected exam lesson is compatible with selected exam subject.
 */
add_filter( 'acf/update_value/key=field_ex_lesson', 'examhub_validate_exam_lesson_subject', 10, 3 );
function examhub_validate_exam_lesson_subject( $value, $post_id, $field ) {
    $lesson_id  = (int) $value;
    $subject_id = examhub_get_acf_request_value( 'field_ex_subject', 'exam_subject', $post_id, [ 'subject_id' ] );

    if ( ! $lesson_id || ! $subject_id ) {
        return $value;
    }

    return examhub_lesson_matches_subject( $lesson_id, $subject_id ) ? $value : '';
}

/**
 * Ensure selected question lesson is compatible with selected question subject.
 */
add_filter( 'acf/update_value/key=field_q_lesson', 'examhub_validate_question_lesson_subject', 10, 3 );
function examhub_validate_question_lesson_subject( $value, $post_id, $field ) {
    $lesson_id  = (int) $value;
    $subject_id = examhub_get_acf_request_value( 'field_q_subject', 'subject', $post_id, [ 'subject_id' ] );

    if ( ! $lesson_id || ! $subject_id ) {
        return $value;
    }

    return examhub_lesson_matches_subject( $lesson_id, $subject_id ) ? $value : '';
}

/**
 * Filter exam question picker by selected grade + subject + lesson.
 * Show published questions only.
 */
add_filter( 'acf/fields/relationship/query/key=field_ex_questions', 'examhub_filter_exam_questions_by_exam_meta', 10, 3 );
function examhub_filter_exam_questions_by_exam_meta( $args, $field, $post_id ) {
    $grade_id   = examhub_get_acf_request_value( 'field_ex_grade', 'exam_grade', $post_id, [ 'grade_id' ] );
    $subject_id = examhub_get_acf_request_value( 'field_ex_subject', 'exam_subject', $post_id, [ 'subject_id' ] );
    $lesson_id  = examhub_get_acf_request_value( 'field_ex_lesson', 'exam_lesson', $post_id, [ 'lesson_id' ] );

    if ( ! $grade_id || ! $subject_id || ! $lesson_id ) {
        $args['post__in'] = [ 0 ];
        return $args;
    }

    $question_ids = get_posts( [
        'post_type'      => 'eh_question',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => 'grade',
                'value'   => $grade_id,
                'compare' => '=',
            ],
            [
                'key'     => 'subject',
                'value'   => $subject_id,
                'compare' => '=',
            ],
            [
                'key'     => 'lesson',
                'value'   => $lesson_id,
                'compare' => '=',
            ],
        ],
    ] );

    $args['post__in']     = ! empty( $question_ids ) ? $question_ids : [ 0 ];
    $args['post_status']  = 'publish';
    $args['orderby']      = 'date';
    $args['order']        = 'DESC';
    unset( $args['meta_query'] );

    $args['posts_per_page'] = 100;
    return $args;
}

// ═══════════════════════════════════════════════════════════════════════════════
// ADMIN COLUMNS — SUBSCRIPTIONS
// ═══════════════════════════════════════════════════════════════════════════════

add_filter( 'manage_eh_subscription_posts_columns', 'examhub_sub_columns' );
function examhub_sub_columns( $cols ) {
    return [
        'cb'         => $cols['cb'],
        'title'      => __( 'الاشتراك', 'examhub' ),
        'sub_user'   => __( 'المستخدم', 'examhub' ),
        'sub_plan'   => __( 'الخطة', 'examhub' ),
        'sub_status' => __( 'الحالة', 'examhub' ),
        'sub_start'  => __( 'البداية', 'examhub' ),
        'sub_end'    => __( 'الانتهاء', 'examhub' ),
    ];
}

add_action( 'manage_eh_subscription_posts_custom_column', 'examhub_sub_column_content', 10, 2 );
function examhub_sub_column_content( $col, $post_id ) {
    switch ( $col ) {
        case 'sub_user':
            $uid  = (int) get_field( 'sub_user_id', $post_id );
            $user = get_userdata( $uid );
            if ( $user ) {
                echo '<a href="' . get_edit_user_link( $uid ) . '">' . esc_html( $user->display_name ) . '</a>';
                echo '<br><small>' . esc_html( $user->user_email ) . '</small>';
            }
            break;

        case 'sub_plan':
            echo esc_html( get_field( 'plan_name', $post_id ) ?: get_field( 'plan_id', $post_id ) );
            break;

        case 'sub_status':
            $status = get_field( 'sub_status', $post_id );
            $colors = [ 'active' => 'green', 'expired' => 'red', 'cancelled' => 'gray', 'trial' => 'blue', 'lifetime' => 'gold', 'pending' => 'orange' ];
            $color  = $colors[$status] ?? 'gray';
            echo "<span style='color:{$color};font-weight:bold;'>" . esc_html( $status ) . '</span>';
            break;

        case 'sub_start':
            echo esc_html( get_field( 'sub_start_date', $post_id ) ?: '—' );
            break;

        case 'sub_end':
            $end = get_field( 'sub_end_date', $post_id );
            if ( ! $end ) { echo '<em>مدى الحياة</em>'; break; }
            $ts  = strtotime( $end );
            $color = $ts < time() ? 'red' : ( $ts < time() + 7 * DAY_IN_SECONDS ? 'orange' : 'inherit' );
            echo "<span style='color:{$color};'>" . esc_html( $end ) . '</span>';
            break;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// ADMIN COLUMNS — PAYMENTS
// ═══════════════════════════════════════════════════════════════════════════════

add_filter( 'manage_eh_payment_posts_columns', 'examhub_payment_columns' );
function examhub_payment_columns( $cols ) {
    return [
        'cb'          => $cols['cb'],
        'title'       => __( 'الدفعة', 'examhub' ),
        'pay_user'    => __( 'المستخدم', 'examhub' ),
        'pay_amount'  => __( 'المبلغ', 'examhub' ),
        'pay_method'  => __( 'الطريقة', 'examhub' ),
        'pay_status'  => __( 'الحالة', 'examhub' ),
        'pay_actions' => __( 'إجراءات', 'examhub' ),
        'date'        => __( 'التاريخ', 'examhub' ),
    ];
}

add_action( 'manage_eh_payment_posts_custom_column', 'examhub_payment_column_content', 10, 2 );
function examhub_payment_column_content( $col, $post_id ) {
    switch ( $col ) {
        case 'pay_user':
            $uid  = (int) get_field( 'pay_user_id', $post_id );
            $user = get_userdata( $uid );
            echo $user ? esc_html( $user->display_name ) . '<br><small>' . esc_html( $user->user_email ) . '</small>' : "ID:{$uid}";
            break;

        case 'pay_amount':
            echo number_format( (float) get_field( 'amount_egp', $post_id ), 2 ) . ' ج';
            break;

        case 'pay_method':
            $methods = [
                'fawaterk' => '💳 Fawaterk', 'vodafone_cash' => '📱 VodafoneCash',
                'bank_transfer' => '🏦 بنك', 'instapay' => '⚡ InstaPay', 'wallet' => '👛 محفظة',
            ];
            echo esc_html( $methods[ get_field( 'payment_method', $post_id ) ] ?? '—' );
            break;

        case 'pay_status':
            $status = get_field( 'payment_status', $post_id );
            $colors = [ 'paid' => 'green', 'pending' => 'orange', 'awaiting_review' => 'blue', 'failed' => 'red', 'cancelled' => 'gray', 'refunded' => 'purple' ];
            $color  = $colors[$status] ?? 'inherit';
            $labels = [ 'paid' => 'مدفوع ✓', 'pending' => 'معلق', 'awaiting_review' => 'قيد المراجعة', 'failed' => 'فشل', 'cancelled' => 'ملغي', 'refunded' => 'مُسترد' ];
            echo "<span style='color:{$color};font-weight:bold;'>" . esc_html( $labels[$status] ?? $status ) . '</span>';
            break;

        case 'pay_actions':
            $status = get_field( 'payment_status', $post_id );
            if ( $status === 'awaiting_review' ) {
                echo '<button type="button" class="button button-primary eh-approve-payment" data-id="' . $post_id . '">' . esc_html__( '✓ قبول', 'examhub' ) . '</button> ';
                echo '<button type="button" class="button eh-reject-payment" data-id="' . $post_id . '">' . esc_html__( '✗ رفض', 'examhub' ) . '</button>';

                $proof_id = get_field( 'payment_proof', $post_id );
                if ( $proof_id ) {
                    echo '<br><a href="' . wp_get_attachment_url( $proof_id ) . '" target="_blank" class="button" style="margin-top:4px;">' . esc_html__( '🖼 الإيصال', 'examhub' ) . '</a>';
                }
            }
            break;
    }
}


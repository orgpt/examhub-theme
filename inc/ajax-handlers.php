<?php
/**
 * ExamHub — AJAX Handlers
 * All wp_ajax_ and wp_ajax_nopriv_ endpoints.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════
// HIERARCHY / FILTERING AJAX
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Get stages by education system.
 */
add_action( 'wp_ajax_eh_get_stages',        'examhub_ajax_get_stages' );
add_action( 'wp_ajax_nopriv_eh_get_stages', 'examhub_ajax_get_stages' );
function examhub_ajax_get_stages() {
    check_ajax_referer( 'examhub_ajax', 'nonce' );
    $system_id = (int) $_POST['system_id'];
    $stages = examhub_get_children_of( 'eh_stage', 'stage_education_system', $system_id );
    wp_send_json_success( examhub_format_posts_for_select( $stages ) );
}

/**
 * Get grades by stage.
 */
add_action( 'wp_ajax_eh_get_grades',        'examhub_ajax_get_grades' );
add_action( 'wp_ajax_nopriv_eh_get_grades', 'examhub_ajax_get_grades' );
function examhub_ajax_get_grades() {
    check_ajax_referer( 'examhub_ajax', 'nonce' );
    $stage_id = (int) $_POST['stage_id'];
    $grades   = examhub_get_children_of( 'eh_grade', 'grade_stage', $stage_id );
    // Sort by grade number
    usort( $grades, fn( $a, $b ) =>
        (int) get_field( 'grade_number', $a->ID ) - (int) get_field( 'grade_number', $b->ID )
    );
    wp_send_json_success( examhub_format_posts_for_select( $grades, 'grade_name_ar' ) );
}

/**
 * Get subjects by grade.
 */
add_action( 'wp_ajax_eh_get_subjects',        'examhub_ajax_get_subjects' );
add_action( 'wp_ajax_nopriv_eh_get_subjects', 'examhub_ajax_get_subjects' );
function examhub_ajax_get_subjects() {
    check_ajax_referer( 'examhub_ajax', 'nonce' );
    $grade_id = (int) $_POST['grade_id'];
    $subjects = examhub_get_children_of( 'eh_subject', 'subject_grade', $grade_id );
    $result   = [];
    foreach ( $subjects as $s ) {
        $result[] = [
            'id'    => $s->ID,
            'label' => get_field( 'subject_name_ar', $s->ID ) ?: $s->post_title,
            'color' => get_field( 'subject_color', $s->ID ) ?: '#4361ee',
            'icon'  => get_field( 'subject_icon', $s->ID ) ?: '',
        ];
    }
    wp_send_json_success( $result );
}

/**
 * Get units by subject.
 */
add_action( 'wp_ajax_eh_get_units',        'examhub_ajax_get_units' );
add_action( 'wp_ajax_nopriv_eh_get_units', 'examhub_ajax_get_units' );
function examhub_ajax_get_units() {
    check_ajax_referer( 'examhub_ajax', 'nonce' );
    $subject_id = (int) $_POST['subject_id'];
    $units      = examhub_get_children_of( 'eh_unit', 'unit_subject', $subject_id );
    usort( $units, fn( $a, $b ) =>
        (int) get_field( 'unit_order', $a->ID ) - (int) get_field( 'unit_order', $b->ID )
    );
    wp_send_json_success( examhub_format_posts_for_select( $units ) );
}

/**
 * Get lessons by unit.
 */
add_action( 'wp_ajax_eh_get_lessons',        'examhub_ajax_get_lessons' );
add_action( 'wp_ajax_nopriv_eh_get_lessons', 'examhub_ajax_get_lessons' );
function examhub_ajax_get_lessons() {
    check_ajax_referer( 'examhub_ajax', 'nonce' );
    $unit_id = (int) $_POST['unit_id'];
    $lessons = examhub_get_children_of( 'eh_lesson', 'lesson_unit', $unit_id );
    usort( $lessons, fn( $a, $b ) =>
        (int) get_field( 'lesson_order', $a->ID ) - (int) get_field( 'lesson_order', $b->ID )
    );
    wp_send_json_success( examhub_format_posts_for_select( $lessons ) );
}

/**
 * Filter exams via AJAX (returns rendered HTML cards).
 */
add_action( 'wp_ajax_eh_filter_exams',        'examhub_ajax_filter_exams' );
add_action( 'wp_ajax_nopriv_eh_filter_exams', 'examhub_ajax_filter_exams' );
function examhub_ajax_filter_exams() {
    check_ajax_referer( 'examhub_ajax', 'nonce' );

    $args = [
        'education_system' => (int) ( $_POST['system_id']  ?? 0 ),
        'stage'            => (int) ( $_POST['stage_id']   ?? 0 ),
        'grade'            => (int) ( $_POST['grade_id']   ?? 0 ),
        'subject'          => (int) ( $_POST['subject_id'] ?? 0 ),
        'difficulty'       => sanitize_text_field( $_POST['difficulty'] ?? '' ),
        'paged'            => (int) ( $_POST['paged'] ?? 1 ),
        'per_page'         => 12,
    ];

    $query = examhub_get_exams_query( $args );

    ob_start();
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            get_template_part( 'template-parts/cards/exam-card' );
        }
        wp_reset_postdata();
    } else {
        get_template_part( 'template-parts/content', 'none' );
    }
    $html = ob_get_clean();

    wp_send_json_success( [
        'html'       => $html,
        'found'      => $query->found_posts,
        'max_pages'  => $query->max_num_pages,
        'paged'      => $args['paged'],
    ] );
}

// ═══════════════════════════════════════════════════════════════════════════
// EXAM ENGINE AJAX
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Start or resume an exam session.
 */
add_action( 'wp_ajax_eh_start_exam', 'examhub_ajax_start_exam' );
function examhub_ajax_start_exam() {
    examhub_verify_ajax_nonce( 'examhub_ajax' );

    $user_id = get_current_user_id();
    if ( ! $user_id ) wp_send_json_error( [ 'message' => __( 'يجب تسجيل الدخول.', 'examhub' ) ], 401 );

    $exam_id = examhub_post_int( 'exam_id' );
    $access  = examhub_verify_exam_access( $exam_id, $user_id );
    if ( is_wp_error( $access ) ) {
        wp_send_json_error( [ 'message' => $access->get_error_message(), 'code' => $access->get_error_code() ], 403 );
    }

    // Rate limit: max 5 exam starts per minute
    if ( ! examhub_rate_limit( "start_exam_{$user_id}", 5, 60 ) ) {
        wp_send_json_error( [ 'message' => __( 'طلبات كثيرة. حاول مجدداً.', 'examhub' ) ], 429 );
    }

    // Check for existing in-progress session
    $allow_resume = (bool) get_field( 'allow_resume', $exam_id );
    if ( $allow_resume ) {
        $existing = examhub_get_in_progress_result( $exam_id, $user_id );
        if ( $existing ) {
            $session_data = examhub_get_exam_session_data( $existing );
            wp_send_json_success( array_merge( $session_data, [ 'resumed' => true ] ) );
        }
    }

    // Build question list
    $question_ids = examhub_build_exam_question_list( $exam_id );
    if ( empty( $question_ids ) ) {
        wp_send_json_error( [ 'message' => __( 'لا توجد أسئلة في هذا الامتحان.', 'examhub' ) ] );
    }

    // Create result post
    $attempt = examhub_get_exam_attempt_count( $exam_id, $user_id ) + 1;
    $result_id = wp_insert_post( [
        'post_type'   => 'eh_result',
        'post_title'  => "نتيجة: امتحان #{$exam_id} - مستخدم #{$user_id}",
        'post_status' => 'publish',
        'post_author' => $user_id,
    ] );

    if ( is_wp_error( $result_id ) ) {
        wp_send_json_error( [ 'message' => __( 'حدث خطأ. حاول مجدداً.', 'examhub' ) ] );
    }

    $now = current_time( 'mysql' );
    update_field( 'result_exam_id',  $exam_id,             $result_id );
    update_field( 'result_user_id',  $user_id,             $result_id );
    update_field( 'result_status',   'in_progress',        $result_id );
    update_field( 'started_at',      $now,                 $result_id );
    update_field( 'attempt_number',  $attempt,             $result_id );
    update_field( 'answers_json',    json_encode( [] ),    $result_id );

    // Store question order in session meta
    update_post_meta( $result_id, '_eh_question_order', $question_ids );

    // Return session data
    wp_send_json_success( [
        'result_id'    => $result_id,
        'question_ids' => $question_ids,
        'total'        => count( $question_ids ),
        'resumed'      => false,
        'started_at'   => $now,
        'exam_nonce'   => wp_create_nonce( 'examhub_exam_' . $exam_id . '_' . $result_id ),
    ] );
}

/**
 * Load a single question for the exam.
 */
add_action( 'wp_ajax_eh_load_question', 'examhub_ajax_load_question' );
function examhub_ajax_load_question() {
    examhub_verify_ajax_nonce( 'examhub_ajax' );

    $user_id   = get_current_user_id();
    $result_id = examhub_post_int( 'result_id' );
    $q_index   = examhub_post_int( 'question_index' );

    if ( ! examhub_verify_result_ownership( $result_id, $user_id ) ) {
        wp_send_json_error( [ 'message' => __( 'وصول غير مصرح.', 'examhub' ) ], 403 );
    }

    $question_ids = get_post_meta( $result_id, '_eh_question_order', true );
    if ( ! is_array( $question_ids ) || ! isset( $question_ids[ $q_index ] ) ) {
        wp_send_json_error( [ 'message' => __( 'السؤال غير موجود.', 'examhub' ) ] );
    }

    $q_id       = $question_ids[ $q_index ];
    $exam_id    = (int) get_field( 'result_exam_id', $result_id );
    $random_ans = (bool) get_field( 'random_answers', $exam_id );

    $question_data = examhub_build_question_payload( $q_id, $random_ans );

    // Load saved answer if any
    $answers_json  = get_field( 'answers_json', $result_id );
    $answers       = $answers_json ? json_decode( $answers_json, true ) : [];
    $saved_answer  = $answers[ $q_id ] ?? null;

    wp_send_json_success( array_merge( $question_data, [
        'saved_answer'   => $saved_answer,
        'question_index' => $q_index,
        'total'          => count( $question_ids ),
        'is_reviewed'    => in_array( $q_id, (array) get_post_meta( $result_id, '_eh_review_list', true ) ),
    ] ) );
}

/**
 * Autosave answer for a question.
 */
add_action( 'wp_ajax_eh_save_answer', 'examhub_ajax_save_answer' );
function examhub_ajax_save_answer() {
    examhub_verify_ajax_nonce( 'examhub_ajax' );

    $user_id   = get_current_user_id();
    $result_id = examhub_post_int( 'result_id' );
    $q_id      = examhub_post_int( 'question_id' );
    $answer    = $_POST['answer'] ?? null; // Will be sanitized per type below

    if ( ! examhub_verify_result_ownership( $result_id, $user_id ) ) {
        wp_send_json_error( [ 'message' => __( 'وصول غير مصرح.', 'examhub' ) ], 403 );
    }

    // Sanitize answer based on question type
    $q_type = get_field( 'question_type', $q_id );
    $answer = examhub_sanitize_answer( $answer, $q_type );

    // Load existing answers
    $answers_json = get_field( 'answers_json', $result_id );
    $answers      = $answers_json ? json_decode( $answers_json, true ) : [];
    if ( ! is_array( $answers ) ) $answers = [];

    $answers[ $q_id ] = [
        'value'      => $answer,
        'saved_at'   => current_time( 'timestamp' ),
        'q_type'     => $q_type,
    ];

    update_field( 'answers_json', json_encode( $answers, JSON_UNESCAPED_UNICODE ), $result_id );

    wp_send_json_success( [ 'saved' => true, 'question_id' => $q_id ] );
}

/**
 * Toggle mark-for-review on a question.
 */
add_action( 'wp_ajax_eh_toggle_review', 'examhub_ajax_toggle_review' );
function examhub_ajax_toggle_review() {
    examhub_verify_ajax_nonce( 'examhub_ajax' );
    $user_id   = get_current_user_id();
    $result_id = examhub_post_int( 'result_id' );
    $q_id      = examhub_post_int( 'question_id' );

    if ( ! examhub_verify_result_ownership( $result_id, $user_id ) ) {
        wp_send_json_error( [], 403 );
    }

    $list = (array) get_post_meta( $result_id, '_eh_review_list', true );
    if ( in_array( $q_id, $list ) ) {
        $list = array_diff( $list, [ $q_id ] );
        $marked = false;
    } else {
        $list[] = $q_id;
        $marked = true;
    }
    update_post_meta( $result_id, '_eh_review_list', array_values( $list ) );
    wp_send_json_success( [ 'marked' => $marked, 'review_list' => $list ] );
}

/**
 * Submit exam — grade, save result, award XP.
 */
add_action( 'wp_ajax_eh_submit_exam', 'examhub_ajax_submit_exam' );
function examhub_ajax_submit_exam() {
    examhub_verify_ajax_nonce( 'examhub_ajax' );

    $user_id   = get_current_user_id();
    $result_id = examhub_post_int( 'result_id' );
    $timed_out = (bool) ( $_POST['timed_out'] ?? false );

    if ( ! examhub_verify_result_ownership( $result_id, $user_id ) ) {
        wp_send_json_error( [ 'message' => __( 'وصول غير مصرح.', 'examhub' ) ], 403 );
    }

    // Prevent double-submit
    $status = get_field( 'result_status', $result_id );
    if ( in_array( $status, [ 'submitted', 'timed_out' ] ) ) {
        wp_send_json_error( [ 'message' => __( 'تم تسليم الامتحان مسبقاً.', 'examhub' ) ] );
    }

    $exam_id      = (int) get_field( 'result_exam_id', $result_id );
    $answers_json = get_field( 'answers_json', $result_id );
    $answers      = $answers_json ? json_decode( $answers_json, true ) : [];
    $question_ids = (array) get_post_meta( $result_id, '_eh_question_order', true );

    // Grade the exam
    $grading = examhub_grade_exam( $exam_id, $question_ids, $answers );

    // Time taken
    $started  = get_field( 'started_at', $result_id );
    $time_sec = $started ? ( time() - strtotime( $started ) ) : 0;

    // Save results
    $final_status = $timed_out ? 'timed_out' : 'submitted';
    update_field( 'result_status',    $final_status,               $result_id );
    update_field( 'score',            $grading['score'],           $result_id );
    update_field( 'total_points',     $grading['total'],           $result_id );
    update_field( 'percentage',       $grading['percentage'],      $result_id );
    update_field( 'passed',           $grading['passed'],          $result_id );
    update_field( 'time_taken_seconds', $time_sec,                 $result_id );
    update_field( 'submitted_at',     current_time( 'mysql' ),     $result_id );
    // Save detailed per-question grading
    update_post_meta( $result_id, '_eh_grading', $grading['details'] );

    // Award XP
    $xp_earned = examhub_calculate_exam_xp( $exam_id, $grading );
    examhub_add_xp( $user_id, $xp_earned, "امتحان #{$exam_id}" );
    update_field( 'xp_earned', $xp_earned, $result_id );

    // Update streak/activity (the old examhub_update_streak() helper does not exist).
    if ( function_exists( 'examhub_record_daily_activity' ) ) {
        examhub_record_daily_activity( $user_id );
    }

    // Check badges
    do_action( 'examhub_exam_submitted', $result_id, $user_id, $grading );

    // Update daily question count
    $q_count = count( $question_ids );
    $current  = (int) get_user_meta( $user_id, 'eh_daily_questions', true );
    update_user_meta( $user_id, 'eh_daily_questions', $current + $q_count );

    wp_send_json_success( [
        'result_id'   => $result_id,
        'score'       => $grading['score'],
        'total'       => $grading['total'],
        'percentage'  => $grading['percentage'],
        'passed'      => $grading['passed'],
        'xp_earned'   => $xp_earned,
        'result_url'  => get_permalink( $result_id ) ?: home_url( '/result/?id=' . $result_id ),
        'time_taken'  => $time_sec,
    ] );
}

/**
 * Get exam progress (for resume).
 */
add_action( 'wp_ajax_eh_get_progress', 'examhub_ajax_get_progress' );
function examhub_ajax_get_progress() {
    examhub_verify_ajax_nonce( 'examhub_ajax' );
    $user_id   = get_current_user_id();
    $result_id = examhub_post_int( 'result_id' );

    if ( ! examhub_verify_result_ownership( $result_id, $user_id ) ) {
        wp_send_json_error( [], 403 );
    }

    $answers_json = get_field( 'answers_json', $result_id );
    $answers      = $answers_json ? json_decode( $answers_json, true ) : [];
    $question_ids = (array) get_post_meta( $result_id, '_eh_question_order', true );
    $review_list  = (array) get_post_meta( $result_id, '_eh_review_list', true );

    $answered_ids = array_keys( $answers );
    $answered_cnt = count( array_filter( $answered_ids, fn( $id ) => isset( $answers[ $id ]['value'] ) && $answers[ $id ]['value'] !== null ) );

    wp_send_json_success( [
        'answered_count' => $answered_cnt,
        'total'          => count( $question_ids ),
        'review_list'    => $review_list,
        'answered_ids'   => $answered_ids,
        'question_ids'   => $question_ids,
    ] );
}

// ═══════════════════════════════════════════════════════════════════════════
// SUBSCRIPTION AJAX
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Check if user can access more questions today.
 */
add_action( 'wp_ajax_eh_check_limit', 'examhub_ajax_check_limit' );
function examhub_ajax_check_limit() {
    examhub_verify_ajax_nonce( 'examhub_ajax' );
    $user_id   = get_current_user_id();
    $remaining = examhub_get_remaining_questions( $user_id );
    $sub       = examhub_get_user_subscription_status( $user_id );

    wp_send_json_success( [
        'remaining'       => $remaining,
        'can_access'      => $remaining > 0,
        'subscription'    => $sub['state'],
        'plan_name'       => $sub['plan_name'],
        'daily_limit'     => $sub['questions_limit'],
        'unlimited'       => $sub['unlimited'],
    ] );
}

// ═══════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS FOR AJAX
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Get children posts filtered by a meta field value.
 */
function examhub_get_children_of( $post_type, $meta_key, $meta_value ) {
    return get_posts( [
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => [ 'meta_value_num' => 'ASC', 'title' => 'ASC' ],
        'meta_query'     => [
            [ 'key' => $meta_key, 'value' => $meta_value, 'compare' => '=' ],
        ],
    ] );
}

/**
 * Format posts array into select-friendly [ id, label ] objects.
 */
function examhub_format_posts_for_select( $posts, $name_field = null ) {
    $result = [];
    foreach ( $posts as $post ) {
        $label = $name_field
            ? ( get_field( $name_field, $post->ID ) ?: $post->post_title )
            : $post->post_title;
        $result[] = [ 'id' => $post->ID, 'label' => $label ];
    }
    return $result;
}

/**
 * Build shuffled question list for an exam session.
 */
function examhub_build_exam_question_list( $exam_id, $randomize = null ) {
    $q_ids     = get_field( 'exam_questions', $exam_id ) ?: [];
    if ( $randomize === null ) {
        $randomize = (bool) get_field( 'random_questions', $exam_id );
    }
    if ( $randomize ) shuffle( $q_ids );
    return array_map( 'intval', $q_ids );
}

/**
 * Build question payload for sending to JS (no correct answers).
 */
function examhub_build_question_payload( $q_id, $random_answers = false ) {
    $type    = get_field( 'question_type', $q_id );
    $text    = get_field( 'question_text', $q_id );
    $image   = get_field( 'question_image', $q_id );
    $math    = get_field( 'question_math', $q_id );
    $points  = (int) get_field( 'question_points', $q_id ) ?: 1;
    $diff    = get_field( 'difficulty', $q_id );

    $payload = [
        'id'         => $q_id,
        'type'       => $type,
        'text'       => $text,
        'points'     => $points,
        'difficulty' => $diff,
        'image'      => $image ? $image['url'] : null,
        'math'       => $math,
    ];

    switch ( $type ) {
        case 'mcq':
        case 'correct':
            $answers = get_field( 'answers', $q_id ) ?: [];
            // Strip correct answer indicators
            $clean = array_map( fn( $a ) => [
                'id'    => md5( $a['answer_text'] . $q_id ),
                'text'  => $a['answer_text'],
                'image' => $a['answer_image'] ?? null,
            ], $answers );
            if ( $random_answers ) shuffle( $clean );
            $payload['answers'] = $clean;
            break;

        case 'true_false':
            $payload['answers'] = [
                [ 'id' => 'true',  'text' => __( 'صح ✓', 'examhub' ) ],
                [ 'id' => 'false', 'text' => __( 'خطأ ✗', 'examhub' ) ],
            ];
            break;

        case 'matching':
            $pairs = get_field( 'matching_pairs', $q_id ) ?: [];
            $left  = array_column( $pairs, 'left' );
            $right = array_column( $pairs, 'right' );
            if ( $random_answers ) shuffle( $right );
            $payload['matching_left']  = $left;
            $payload['matching_right'] = $right;
            $payload['pair_count']     = count( $pairs );
            break;

        case 'ordering':
            $items = get_field( 'ordering_items', $q_id ) ?: [];
            $items = array_column( $items, 'item' );
            if ( $random_answers ) shuffle( $items );
            $payload['ordering_items'] = $items;
            break;

        case 'fill_blank':
            // Replace blanks in text with input markers
            $payload['text'] = preg_replace( '/___+/', '[[BLANK]]', $text );
            $blank_count     = substr_count( $text, '___' ) ?: 1;
            $payload['blank_count'] = $blank_count;
            break;

        case 'essay':
            $payload['max_words'] = get_post_meta( $q_id, '_essay_max_words', true ) ?: 300;
            break;
    }

    return $payload;
}

/**
 * Sanitize answer value based on question type.
 */
function examhub_sanitize_answer( $answer, $q_type ) {
    switch ( $q_type ) {
        case 'mcq':
        case 'correct':
        case 'true_false':
            return sanitize_text_field( (string) $answer );

        case 'fill_blank':
            if ( is_array( $answer ) ) {
                return array_map( 'sanitize_text_field', $answer );
            }
            return sanitize_text_field( (string) $answer );

        case 'matching':
        case 'ordering':
            if ( is_array( $answer ) ) {
                return array_map( 'sanitize_text_field', $answer );
            }
            return [];

        case 'essay':
            return wp_kses( (string) $answer, [] ); // Plain text only

        default:
            return sanitize_text_field( (string) $answer );
    }
}

/**
 * Grade all questions in an exam.
 *
 * @param int   $exam_id
 * @param array $question_ids
 * @param array $answers      [ q_id => [ 'value' => ..., 'q_type' => ... ] ]
 * @return array score, total, percentage, passed, details
 */
function examhub_grade_exam( $exam_id, $question_ids, $answers ) {
    $score    = 0;
    $total    = 0;
    $details  = [];
    $pass_pct = (float) ( get_field( 'pass_percentage', $exam_id ) ?: 50 );

    foreach ( $question_ids as $q_id ) {
        $points  = (int) get_field( 'question_points', $q_id ) ?: 1;
        $type    = get_field( 'question_type', $q_id );
        $total  += $points;

        $user_answer = $answers[ $q_id ]['value'] ?? null;
        $is_correct  = examhub_check_answer( $q_id, $type, $user_answer );
        $earned      = $is_correct ? $points : 0;
        $score      += $earned;

        // Fetch correct answer for the result screen
        $correct_display = examhub_get_correct_answer_display( $q_id, $type );

        $details[ $q_id ] = [
            'q_id'            => $q_id,
            'type'            => $type,
            'points'          => $points,
            'earned'          => $earned,
            'is_correct'      => $is_correct,
            'correct'         => $is_correct,
            'user_answer'     => $user_answer,
            'correct_answer'  => $correct_display,
            'explanation'     => get_field( 'explanation', $q_id ),
            'question_text'   => get_field( 'question_text', $q_id ),
            'difficulty'      => get_field( 'difficulty', $q_id ),
            'subject_id'      => (int) get_field( 'subject', $q_id ),
            'lesson_id'       => (int) get_field( 'lesson', $q_id ),
        ];
    }

    $percentage = $total > 0 ? round( $score / $total * 100, 1 ) : 0;
    $passed     = $percentage >= $pass_pct;

    $detail = $details;
    return compact( 'score', 'total', 'percentage', 'passed', 'details', 'detail' );
}

/**
 * Check if a user's answer is correct.
 */
function examhub_check_answer( $q_id, $type, $user_answer ) {
    if ( $user_answer === null || $user_answer === '' ) return false;

    switch ( $type ) {
        case 'mcq':
        case 'correct':
        case 'image':
            $answers = get_field( 'answers', $q_id ) ?: [];
            foreach ( $answers as $a ) {
                if ( $a['is_correct'] && md5( $a['answer_text'] . $q_id ) === $user_answer ) {
                    return true;
                }
            }
            return false;

        case 'true_false':
            $correct = get_field( 'tf_correct_answer', $q_id );
            return strtolower( (string) $user_answer ) === strtolower( (string) $correct );

        case 'fill_blank':
            $correct_raw = get_field( 'blank_answer', $q_id );
            $corrects    = array_map( 'trim', explode( '|', $correct_raw ) );
            if ( is_array( $user_answer ) ) {
                foreach ( $user_answer as $i => $val ) {
                    if ( ! isset( $corrects[ $i ] ) ) return false;
                    if ( mb_strtolower( trim( $val ) ) !== mb_strtolower( $corrects[ $i ] ) ) return false;
                }
                return true;
            }
            return in_array( mb_strtolower( trim( $user_answer ) ), array_map( 'mb_strtolower', $corrects ) );

        case 'matching':
            $pairs = get_field( 'matching_pairs', $q_id ) ?: [];
            if ( ! is_array( $user_answer ) || count( $user_answer ) !== count( $pairs ) ) return false;
            foreach ( $pairs as $i => $pair ) {
                $expected = $pair['right'];
                $left     = $pair['left'] ?? '';
                $actual   = $user_answer[ $i ] ?? ( $left !== '' ? ( $user_answer[ $left ] ?? '' ) : '' );
                if ( $actual !== $expected ) return false;
            }
            return true;

        case 'ordering':
            $items = get_field( 'ordering_items', $q_id ) ?: [];
            $correct_order = array_column( $items, 'item' );
            if ( ! is_array( $user_answer ) ) return false;
            return $user_answer === $correct_order;

        case 'math':
            $correct = trim( (string) get_field( 'blank_answer', $q_id ) );
            $clean   = preg_replace( '/\s+/', '', (string) $user_answer );
            $correct_clean = preg_replace( '/\s+/', '', $correct );
            return mb_strtolower( $clean ) === mb_strtolower( $correct_clean );

        case 'essay':
            return null; // Needs manual grading — return null = pending
    }

    return false;
}

/**
 * Get display-safe correct answer for result screen.
 */
function examhub_get_correct_answer_display( $q_id, $type ) {
    switch ( $type ) {
        case 'mcq':
        case 'correct':
        case 'image':
            $answers = get_field( 'answers', $q_id ) ?: [];
            foreach ( $answers as $a ) {
                if ( $a['is_correct'] ) return $a['answer_text'];
            }
            return '';

        case 'true_false':
            $val = get_field( 'tf_correct_answer', $q_id );
            return $val === 'true' ? __( 'صح ✓', 'examhub' ) : __( 'خطأ ✗', 'examhub' );

        case 'fill_blank':
        case 'math':
            return get_field( 'blank_answer', $q_id );

        case 'matching':
            $pairs = get_field( 'matching_pairs', $q_id ) ?: [];
            return array_map( fn( $p ) => $p['left'] . ' → ' . $p['right'], $pairs );

        case 'ordering':
            $items = get_field( 'ordering_items', $q_id ) ?: [];
            return array_column( $items, 'item' );
    }
    return '';
}

/**
 * Get an in-progress result for user+exam.
 */
function examhub_get_in_progress_result( $exam_id, $user_id ) {
    $results = get_posts( [
        'post_type'      => 'eh_result',
        'author'         => $user_id,
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [
            [ 'key' => 'result_exam_id', 'value' => $exam_id ],
            [ 'key' => 'result_status',  'value' => 'in_progress' ],
        ],
    ] );
    return $results ? $results[0]->ID : null;
}

/**
 * Build session data for resuming.
 */
function examhub_get_exam_session_data( $result_id ) {
    $question_ids = (array) get_post_meta( $result_id, '_eh_question_order', true );
    $answers_json = get_field( 'answers_json', $result_id );
    $answers      = $answers_json ? json_decode( $answers_json, true ) : [];

    return [
        'result_id'    => $result_id,
        'question_ids' => $question_ids,
        'total'        => count( $question_ids ),
        'answers'      => $answers,
        'review_list'  => (array) get_post_meta( $result_id, '_eh_review_list', true ),
        'started_at'   => get_field( 'started_at', $result_id ),
        'exam_nonce'   => wp_create_nonce( 'examhub_exam_' . get_field( 'result_exam_id', $result_id ) . '_' . $result_id ),
    ];
}

/**
 * Calculate XP reward for exam submission.
 */
function examhub_calculate_exam_xp( $exam_id, $grading ) {
    $base_xp      = (int) ( get_field( 'exam_xp_reward', $exam_id ) ?: get_field( 'xp_per_exam', 'option' ) ?: 20 );
    $correct_xp   = (int) ( get_field( 'xp_per_correct_answer', 'option' ) ?: 2 );
    $perfect_xp   = (int) ( get_field( 'xp_perfect_score', 'option' ) ?: 50 );

    // Count correct answers
    $correct_count = count( array_filter( $grading['details'], fn( $d ) => $d['is_correct'] ) );

    $xp  = $base_xp;
    $xp += $correct_count * $correct_xp;
    if ( $grading['percentage'] >= 100 ) $xp += $perfect_xp;

    return $xp;
}

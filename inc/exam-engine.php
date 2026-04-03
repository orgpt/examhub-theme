<?php
/**
 * ExamHub — Exam Engine (Server-Side)
 * Handles: exam session creation/resume, question shuffling,
 * answer storage, grading, scoring, pass/fail, XP reward.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════════
// SESSION MANAGEMENT
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Start or resume an exam session.
 * Returns result post ID + question list + resume state.
 *
 * @param int $exam_id
 * @param int $user_id
 * @return array|WP_Error
 */
function examhub_start_exam_session( $exam_id, $user_id ) {
    // Access check
    $access = examhub_verify_exam_access( $exam_id, $user_id );
    if ( is_wp_error( $access ) ) return $access;

    $allow_resume    = (bool) get_field( 'allow_resume', $exam_id );
    $random_questions = (bool) get_field( 'random_questions', $exam_id );
    $random_answers  = (bool) get_field( 'random_answers', $exam_id );
    $timer_type      = get_field( 'timer_type', $exam_id ) ?: 'none';
    $duration_min    = (int) get_field( 'exam_duration_minutes', $exam_id );
    $sec_per_q       = (int) get_field( 'seconds_per_question', $exam_id ) ?: 60;
    $access_level    = get_field( 'exam_access', $exam_id ) ?: 'free_limit';

    // Check for in-progress session (resume)
    if ( $allow_resume ) {
        $existing = examhub_find_in_progress_result( $exam_id, $user_id );
        if ( $existing ) {
            return examhub_build_session_response( $existing, true, $random_answers );
        }
    }

    // Build question list
    $question_ids = examhub_build_exam_question_list( $exam_id, $random_questions );
    if ( empty( $question_ids ) ) {
        return new WP_Error( 'no_questions', __( 'هذا الامتحان لا يحتوي على أسئلة بعد.', 'examhub' ) );
    }

    // Count against daily limit for free_limit exams
    if ( $access_level === 'free_limit' ) {
        if ( ! examhub_user_can_start_exam( $user_id ) ) {
            return new WP_Error( 'limit_reached', __( 'لقد وصلت إلى الحد اليومي المجاني. يرجى الاشتراك للاستمرار.', 'examhub' ) );
        }
    }

    // Create result post
    $attempt = examhub_get_exam_attempt_count( $exam_id, $user_id ) + 1;

    $result_id = wp_insert_post( [
        'post_type'   => 'eh_result',
        'post_title'  => sprintf( 'نتيجة - امتحان %d - مستخدم %d - محاولة %d', $exam_id, $user_id, $attempt ),
        'post_status' => 'publish',
        'post_author' => $user_id,
    ] );

    if ( is_wp_error( $result_id ) ) return $result_id;

    $started_at = current_time( 'Y-m-d H:i:s' );
    $answers_json = json_encode( [] );

    update_field( 'result_exam_id',   $exam_id,     $result_id );
    update_field( 'result_user_id',   $user_id,     $result_id );
    update_field( 'result_status',    'in_progress', $result_id );
    update_field( 'started_at',       $started_at,  $result_id );
    update_field( 'attempt_number',   $attempt,     $result_id );
    update_field( 'answers_json',     json_encode( [
        '_question_ids' => $question_ids,
        '_answers'      => [],
        '_review_list'  => [],
        '_random_answers' => $random_answers,
    ] ), $result_id );

    return examhub_build_session_response( $result_id, false, $random_answers );
}

/**
 * Build the session response array for JS consumption.
 */
function examhub_build_session_response( $result_id, $resumed, $random_answers ) {
    $raw = get_field( 'answers_json', $result_id );
    $data = json_decode( $raw, true ) ?: [];
    $question_ids = $data['_question_ids'] ?? [];
    $answers      = $data['_answers']      ?? [];
    $review_list  = $data['_review_list']  ?? [];

    return [
        'result_id'    => $result_id,
        'question_ids' => $question_ids,
        'answers'      => $answers,
        'review_list'  => $review_list,
        'resumed'      => $resumed,
        'started_at'   => get_field( 'started_at', $result_id ),
        'total'        => count( $question_ids ),
    ];
}

/**
 * Find an in-progress result for a user + exam.
 */
function examhub_find_in_progress_result( $exam_id, $user_id ) {
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
    return ! empty( $results ) ? $results[0]->ID : null;
}

/**
 * Build the ordered question ID list for an exam.
 * Respects random_questions setting.
 *
 * @param int  $exam_id
 * @param bool $random
 * @return array
 */
if ( ! function_exists( 'examhub_build_exam_question_list' ) ) {
function examhub_build_exam_question_list( $exam_id, $random = false ) {
    $questions = get_field( 'exam_questions', $exam_id );
    if ( ! is_array( $questions ) ) return [];

    $ids = array_map( 'intval', $questions );
    if ( $random ) shuffle( $ids );

    return $ids;
}
}

// ═══════════════════════════════════════════════════════════════════════════════
// ANSWER SAVE / AUTOSAVE
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Save a single answer to the result record.
 *
 * @param int    $result_id
 * @param int    $question_id
 * @param mixed  $answer
 * @param int    $user_id
 * @return array|WP_Error
 */
function examhub_save_answer( $result_id, $question_id, $answer, $user_id ) {
    // Ownership
    if ( ! examhub_verify_result_ownership( $result_id, $user_id ) ) {
        return new WP_Error( 'unauthorized', __( 'غير مصرح.', 'examhub' ) );
    }

    // Not already submitted
    $status = get_field( 'result_status', $result_id );
    if ( $status !== 'in_progress' ) {
        return new WP_Error( 'already_submitted', __( 'تم تسليم الامتحان بالفعل.', 'examhub' ) );
    }

    $raw  = get_field( 'answers_json', $result_id );
    $data = json_decode( $raw, true ) ?: [];

    $q_type = get_field( 'question_type', $question_id );
    $sanitized = examhub_sanitize_answer( $answer, $q_type );

    if ( ! isset( $data['_answers'] ) ) $data['_answers'] = [];
    $data['_answers'][ $question_id ] = [
        'value'      => $sanitized,
        'saved_at'   => current_time( 'timestamp' ),
        'q_type'     => $q_type,
    ];

    update_field( 'answers_json', json_encode( $data ), $result_id );

    return [
        'saved'       => true,
        'question_id' => $question_id,
        'answered'    => count( $data['_answers'] ),
        'total'       => count( $data['_question_ids'] ?? [] ),
    ];
}

/**
 * Toggle mark-for-review on a question.
 */
function examhub_toggle_review( $result_id, $question_id, $user_id ) {
    if ( ! examhub_verify_result_ownership( $result_id, $user_id ) ) {
        return new WP_Error( 'unauthorized', '' );
    }

    $raw  = get_field( 'answers_json', $result_id );
    $data = json_decode( $raw, true ) ?: [];

    if ( ! isset( $data['_review_list'] ) ) $data['_review_list'] = [];

    $pos = array_search( $question_id, $data['_review_list'] );
    if ( $pos !== false ) {
        array_splice( $data['_review_list'], $pos, 1 );
        $is_reviewed = false;
    } else {
        $data['_review_list'][] = $question_id;
        $is_reviewed = true;
    }

    update_field( 'answers_json', json_encode( $data ), $result_id );

    return [ 'is_reviewed' => $is_reviewed, 'review_list' => $data['_review_list'] ];
}

// ═══════════════════════════════════════════════════════════════════════════════
// GRADING & SUBMISSION
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Submit and grade an exam.
 *
 * @param int  $result_id
 * @param int  $user_id
 * @param bool $timed_out
 * @return array|WP_Error
 */
function examhub_submit_exam( $result_id, $user_id, $timed_out = false ) {
    // Ownership & status check
    if ( ! examhub_verify_result_ownership( $result_id, $user_id ) ) {
        return new WP_Error( 'unauthorized', __( 'غير مصرح.', 'examhub' ) );
    }

    $status = get_field( 'result_status', $result_id );
    if ( $status !== 'in_progress' ) {
        return new WP_Error( 'already_submitted', __( 'تم تسليم الامتحان بالفعل.', 'examhub' ) );
    }

    $exam_id = (int) get_field( 'result_exam_id', $result_id );
    $raw     = get_field( 'answers_json', $result_id );
    $data    = json_decode( $raw, true ) ?: [];

    $question_ids = $data['_question_ids'] ?? [];
    $answers      = $data['_answers']      ?? [];

    // Grade
    $grading = examhub_grade_exam( $exam_id, $question_ids, $answers );

    // Calculate time taken
    $started_str  = get_field( 'started_at', $result_id );
    $started_ts   = $started_str ? strtotime( $started_str ) : time();
    $time_taken   = time() - $started_ts;

    $pass_pct    = (float) ( get_field( 'pass_percentage', $exam_id ) ?: 50 );
    $passed      = $grading['percentage'] >= $pass_pct;
    $final_status = $timed_out ? 'timed_out' : 'submitted';

    // Persist result fields
    update_field( 'score',             $grading['score'],        $result_id );
    update_field( 'total_points',      $grading['total'],        $result_id );
    update_field( 'percentage',        $grading['percentage'],   $result_id );
    update_field( 'passed',            $passed,                  $result_id );
    update_field( 'time_taken_seconds',$time_taken,              $result_id );
    update_field( 'submitted_at',      current_time( 'Y-m-d H:i:s' ), $result_id );
    update_field( 'result_status',     $final_status,            $result_id );

    // Store grading breakdown in answers_json
    $data['_grading']    = $grading['detail'] ?? ( $grading['details'] ?? [] );
    $data['_submitted']  = true;
    $data['_timed_out']  = $timed_out;
    update_field( 'answers_json', json_encode( $data ), $result_id );

    // Award XP
    $xp_earned = 0;
    if ( get_field( 'gamification_enabled', 'option' ) ) {
        $xp_earned = examhub_award_exam_xp( $exam_id, $result_id, $user_id, $grading, $passed );
    }
    update_field( 'xp_earned', $xp_earned, $result_id );

    // Update streak & daily progress
    examhub_record_daily_activity( $user_id );

    // Fire gamification hooks (badges etc.)
    do_action( 'examhub_exam_submitted', $exam_id, $result_id, $user_id, $grading, $passed );

    // Update analytics cache
    examhub_invalidate_user_analytics_cache( $user_id );

    return [
        'result_id'  => $result_id,
        'score'      => $grading['score'],
        'total'      => $grading['total'],
        'percentage' => $grading['percentage'],
        'passed'     => $passed,
        'xp_earned'  => $xp_earned,
        'status'     => $final_status,
    ];
}

/**
 * Grade the exam: score each question, compute totals.
 *
 * @param int   $exam_id
 * @param array $question_ids
 * @param array $answers  { q_id => { value, q_type } }
 * @return array score, total, percentage, detail[]
 */
if ( ! function_exists( 'examhub_grade_exam' ) ) {
function examhub_grade_exam( $exam_id, $question_ids, $answers ) {
    $score  = 0;
    $total  = 0;
    $detail = [];

    foreach ( $question_ids as $q_id ) {
        $q_type = get_field( 'question_type', $q_id );
        $points = (float) ( get_field( 'question_points', $q_id ) ?: 1 );
        $total += $points;

        // Essay = manual grading, skip auto-scoring
        if ( $q_type === 'essay' ) {
            $detail[ $q_id ] = [
                'type'        => $q_type,
                'points'      => $points,
                'earned'      => 0,
                'correct'     => null,  // null = pending manual
                'user_answer' => $answers[ $q_id ]['value'] ?? null,
                'needs_manual' => true,
            ];
            continue;
        }

        $user_answer = $answers[ $q_id ]['value'] ?? null;
        $is_correct  = examhub_check_answer( $q_id, $q_type, $user_answer );
        $earned      = $is_correct ? $points : 0;
        $score      += $earned;

        $detail[ $q_id ] = [
            'type'           => $q_type,
            'points'         => $points,
            'earned'         => $earned,
            'correct'        => $is_correct,
            'user_answer'    => $user_answer,
            'correct_answer' => examhub_get_correct_answer_display( $q_id, $q_type ),
            'explanation'    => get_field( 'explanation', $q_id ),
        ];
    }

    $percentage = $total > 0 ? round( $score / $total * 100, 1 ) : 0;

    return compact( 'score', 'total', 'percentage', 'detail' );
}
}

/**
 * Check if a user answer is correct for a given question.
 *
 * @param int    $q_id
 * @param string $type
 * @param mixed  $user_answer
 * @return bool
 */
if ( ! function_exists( 'examhub_check_answer' ) ) {
function examhub_check_answer( $q_id, $type, $user_answer ) {
    if ( $user_answer === null || $user_answer === '' ) return false;

    switch ( $type ) {

        case 'mcq':
        case 'correct':
        case 'image':
            // User answer = index of selected answer (0-based)
            $answers = get_field( 'answers', $q_id );
            if ( ! is_array( $answers ) ) return false;
            $idx = (int) $user_answer;
            return isset( $answers[ $idx ] ) && ! empty( $answers[ $idx ]['is_correct'] );

        case 'true_false':
            // User answer = 'true' or 'false'
            $correct = get_field( 'tf_correct_answer', $q_id );
            return strtolower( (string) $user_answer ) === strtolower( (string) $correct );

        case 'fill_blank':
            // User answer = string; correct = pipe-separated acceptable answers
            $correct_raw = get_field( 'blank_answer', $q_id );
            $correct_list = array_map( 'trim', explode( '|', $correct_raw ) );
            $user_clean   = trim( (string) $user_answer );
            foreach ( $correct_list as $c ) {
                if ( mb_strtolower( $user_clean ) === mb_strtolower( $c ) ) return true;
            }
            return false;

        case 'matching':
            // User answer = array { left => right }
            if ( ! is_array( $user_answer ) ) return false;
            $pairs = get_field( 'matching_pairs', $q_id );
            if ( ! is_array( $pairs ) ) return false;
            foreach ( $pairs as $pair ) {
                $left  = $pair['left'] ?? '';
                $right = $pair['right'] ?? '';
                if ( ( $user_answer[ $left ] ?? '' ) !== $right ) return false;
            }
            return true;

        case 'ordering':
            // User answer = array of items in user-specified order
            if ( ! is_array( $user_answer ) ) return false;
            $items = get_field( 'ordering_items', $q_id );
            if ( ! is_array( $items ) ) return false;
            $correct_order = array_column( $items, 'item' );
            return array_values( $user_answer ) === array_values( $correct_order );

        case 'math':
            // Treat like fill_blank — normalize whitespace
            $correct = trim( get_field( 'blank_answer', $q_id ) );
            $clean   = preg_replace( '/\s+/', '', (string) $user_answer );
            $correct_clean = preg_replace( '/\s+/', '', $correct );
            return mb_strtolower( $clean ) === mb_strtolower( $correct_clean );

        default:
    return false;
}
}
}

/**
 * Get the correct answer display value for a question.
 * Used for showing the correct answer on result screen.
 *
 * @param int    $q_id
 * @param string $type
 * @return mixed
 */
if ( ! function_exists( 'examhub_get_correct_answer_display' ) ) {
function examhub_get_correct_answer_display( $q_id, $type ) {
    switch ( $type ) {
        case 'mcq':
        case 'correct':
        case 'image':
            $answers = get_field( 'answers', $q_id );
            if ( ! is_array( $answers ) ) return null;
            foreach ( $answers as $i => $ans ) {
                if ( ! empty( $ans['is_correct'] ) ) {
                    return [ 'index' => $i, 'text' => $ans['answer_text'] ];
                }
            }
            return null;

        case 'true_false':
            $v = get_field( 'tf_correct_answer', $q_id );
            return $v === 'true' ? __( 'صح ✓', 'examhub' ) : __( 'خطأ ✗', 'examhub' );

        case 'fill_blank':
        case 'math':
            return get_field( 'blank_answer', $q_id );

        case 'matching':
            $pairs = get_field( 'matching_pairs', $q_id );
            return is_array( $pairs ) ? $pairs : [];

        case 'ordering':
            $items = get_field( 'ordering_items', $q_id );
            return is_array( $items ) ? array_column( $items, 'item' ) : [];

        default:
            return null;
    }
}
}

/**
 * Sanitize user answer based on question type.
 *
 * @param mixed  $raw
 * @param string $type
 * @return mixed
 */
if ( ! function_exists( 'examhub_sanitize_answer' ) ) {
function examhub_sanitize_answer( $raw, $type ) {
    switch ( $type ) {
        case 'mcq':
        case 'correct':
        case 'image':
            return (int) $raw;

        case 'true_false':
            return in_array( $raw, [ 'true', 'false' ] ) ? $raw : null;

        case 'fill_blank':
        case 'math':
            return sanitize_text_field( (string) $raw );

        case 'essay':
            return wp_kses_post( (string) $raw );

        case 'matching':
            if ( ! is_array( $raw ) ) {
                $decoded = json_decode( stripslashes( $raw ), true );
                $raw = is_array( $decoded ) ? $decoded : [];
            }
            $clean = [];
            foreach ( $raw as $k => $v ) {
                $clean[ sanitize_text_field( $k ) ] = sanitize_text_field( $v );
            }
            return $clean;

        case 'ordering':
            if ( ! is_array( $raw ) ) {
                $decoded = json_decode( stripslashes( $raw ), true );
                $raw = is_array( $decoded ) ? $decoded : [];
            }
            return array_map( 'sanitize_text_field', $raw );

        default:
            return sanitize_text_field( (string) $raw );
    }
}
}

// ═══════════════════════════════════════════════════════════════════════════════
// QUESTION DATA FOR JS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Build a question payload safe to send to frontend.
 * Omits correct answers; includes everything needed to render the UI.
 *
 * @param int  $q_id
 * @param bool $random_answers
 * @return array
 */
if ( ! function_exists( 'examhub_build_question_payload' ) ) {
function examhub_build_question_payload( $q_id, $random_answers = false ) {
    $type        = get_field( 'question_type', $q_id );
    $text        = get_field( 'question_text', $q_id );
    $text_en     = get_field( 'question_text_en', $q_id );
    $body_raw    = get_post_field( 'post_content', $q_id );
    $difficulty  = get_field( 'difficulty', $q_id );
    $image       = get_field( 'question_image', $q_id );
    $math        = get_field( 'question_math', $q_id );
    $points      = (float) ( get_field( 'question_points', $q_id ) ?: 1 );
    $body        = '' !== trim( (string) $body_raw ) ? apply_filters( 'the_content', $body_raw ) : '';

    $payload = compact( 'type', 'text', 'text_en', 'body', 'difficulty', 'math', 'points' );
    $payload['id'] = $q_id;

    if ( $image && is_array( $image ) ) {
        $payload['image'] = $image['url'] ?? null;
    }

    switch ( $type ) {
        case 'mcq':
        case 'correct':
        case 'image':
            $answers = get_field( 'answers', $q_id );
            if ( is_array( $answers ) ) {
                // Build sanitized list WITHOUT is_correct flag
                $list = array_map( fn( $a, $i ) => [
                    'index' => $i,
                    'text'  => $a['answer_text'] ?? '',
                    'image' => $a['answer_image'] ?? null,
                ], $answers, array_keys( $answers ) );

                if ( $random_answers ) {
                    shuffle( $list );
                }
                $payload['answers'] = $list;
            }
            break;

        case 'true_false':
            $payload['answers'] = [
                [ 'index' => 'true',  'text' => __( 'صح', 'examhub' ) ],
                [ 'index' => 'false', 'text' => __( 'خطأ', 'examhub' ) ],
            ];
            break;

        case 'fill_blank':
            // Nothing extra needed — rendered as text input
            break;

        case 'matching':
            $pairs = get_field( 'matching_pairs', $q_id );
            if ( is_array( $pairs ) ) {
                $lefts  = array_column( $pairs, 'left' );
                $rights = array_column( $pairs, 'right' );
                if ( $random_answers ) shuffle( $rights );
                $payload['matching_left']  = $lefts;
                $payload['matching_right'] = $rights;
            }
            break;

        case 'ordering':
            $items = get_field( 'ordering_items', $q_id );
            if ( is_array( $items ) ) {
                $list = array_column( $items, 'item' );
                shuffle( $list ); // Always shuffle for ordering
                $payload['ordering_items'] = $list;
            }
            break;

        case 'math':
            // Nothing extra — rendered with LaTeX
            break;

        case 'essay':
            $payload['word_limit'] = 500;
            break;
    }

    return $payload;
}
}

// ═══════════════════════════════════════════════════════════════════════════════
// XP & GAMIFICATION
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Award XP for completing an exam.
 *
 * @return int XP earned
 */
function examhub_award_exam_xp( $exam_id, $result_id, $user_id, $grading, $passed ) {
    $xp_exam    = (int) ( get_field( 'xp_per_exam', 'option' ) ?: 20 );
    $xp_correct = (int) ( get_field( 'xp_per_correct_answer', 'option' ) ?: 2 );
    $xp_perfect = (int) ( get_field( 'xp_perfect_score', 'option' ) ?: 50 );
    $exam_xp_bonus = (int) ( get_field( 'exam_xp_reward', $exam_id ) ?: 0 );

    $correct_count = 0;
    $grading_rows = $grading['detail'] ?? ( $grading['details'] ?? [] );
    foreach ( $grading_rows as $detail ) {
        $is_correct = $detail['correct'] ?? ( $detail['is_correct'] ?? false );
        if ( $is_correct === true ) $correct_count++;
    }

    $earned = $xp_exam + ( $correct_count * $xp_correct ) + $exam_xp_bonus;

    // Perfect score bonus
    if ( $grading['percentage'] >= 100 ) {
        $earned += $xp_perfect;
    }

    if ( $earned > 0 ) {
        examhub_add_xp( $user_id, $earned, sprintf( 'امتحان #%d', $exam_id ) );
    }

    return $earned;
}

/**
 * Record daily activity (for streaks).
 */
function examhub_record_daily_activity( $user_id ) {
    $today     = date( 'Y-m-d' );
    $last_day  = get_user_meta( $user_id, 'eh_last_activity', true );
    $streak    = (int) get_user_meta( $user_id, 'eh_streak', true );
    $yesterday = date( 'Y-m-d', strtotime( '-1 day' ) );

    if ( $last_day === $today ) {
        // Already active today — no change
        return;
    }

    if ( $last_day === $yesterday ) {
        $streak++;
    } else {
        $streak = 1; // Reset streak
    }

    update_user_meta( $user_id, 'eh_last_activity', $today );
    update_user_meta( $user_id, 'eh_streak', $streak );

    // Streak XP bonus
    $streak_bonus_xp = (int) ( get_field( 'xp_streak_bonus_per_day', 'option' ) ?: 5 );
    if ( $streak_bonus_xp > 0 ) {
        examhub_add_xp( $user_id, $streak_bonus_xp * min( $streak, 7 ), "سلسلة {$streak} أيام" );
    }

    // Daily reward XP
    $daily_xp = (int) ( get_field( 'xp_daily_reward', 'option' ) ?: 10 );
    $last_daily_reward = get_user_meta( $user_id, 'eh_last_daily_reward', true );
    if ( $last_daily_reward !== $today ) {
        examhub_add_xp( $user_id, $daily_xp, 'مكافأة يومية' );
        update_user_meta( $user_id, 'eh_last_daily_reward', $today );
    }

    do_action( 'examhub_streak_updated', $user_id, $streak );
}

/**
 * Invalidate user analytics cache.
 */
function examhub_invalidate_user_analytics_cache( $user_id ) {
    delete_transient( 'eh_analytics_' . $user_id );
    delete_transient( 'eh_weak_areas_' . $user_id );
    delete_transient( 'eh_leaderboard_global' );
    delete_transient( 'eh_leaderboard_' . (int) get_user_meta( $user_id, 'eh_grade_id', true ) );
}

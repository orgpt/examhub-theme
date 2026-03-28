<?php
/**
 * ExamHub — Analytics Engine
 * Performance analytics, weak point detection, subject stats.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get complete user performance analytics.
 *
 * @param int $user_id
 * @param int $days  Lookback period (0 = all time)
 * @return array
 */
function examhub_get_user_analytics( $user_id, $days = 30 ) {
    $results = examhub_get_user_results( $user_id, $days );

    if ( empty( $results ) ) {
        return [
            'total_exams'      => 0,
            'total_questions'  => 0,
            'correct_total'    => 0,
            'accuracy_pct'     => 0,
            'avg_score'        => 0,
            'exams_passed'     => 0,
            'pass_rate'        => 0,
            'total_xp'         => (int) get_user_meta( $user_id, 'eh_xp', true ),
            'streak'           => (int) get_user_meta( $user_id, 'eh_streak', true ),
            'subject_stats'    => [],
            'lesson_stats'     => [],
            'weak_subjects'    => [],
            'weak_lessons'     => [],
            'daily_activity'   => [],
            'recent_results'   => [],
            'diff_breakdown'   => [],
        ];
    }

    $subject_stats = [];
    $lesson_stats  = [];
    $diff_breakdown = [ 'easy' => [0,0], 'medium' => [0,0], 'hard' => [0,0] ];
    $total_q       = 0;
    $total_correct = 0;
    $scores        = [];
    $passed_count  = 0;
    $daily_activity = [];

    foreach ( $results as $result ) {
        $result_id  = $result->ID;
        $grading    = get_post_meta( $result_id, '_eh_grading', true );
        $percentage = (float) get_field( 'percentage',   $result_id );
        $passed     = (bool)  get_field( 'passed',       $result_id );
        $submitted  = get_field( 'submitted_at', $result_id );
        $day        = $submitted ? date( 'Y-m-d', strtotime( $submitted ) ) : null;

        $scores[] = $percentage;
        if ( $passed ) $passed_count++;

        // Daily activity
        if ( $day ) {
            $daily_activity[ $day ] = ( $daily_activity[ $day ] ?? 0 ) + 1;
        }

        if ( ! is_array( $grading ) ) continue;

        foreach ( $grading as $q_id => $d ) {
            $total_q++;
            $sid = $d['subject_id'] ?? 0;
            $lid = $d['lesson_id']  ?? 0;
            $dif = $d['difficulty'] ?? 'medium';

            if ( $d['is_correct'] ) $total_correct++;

            if ( $sid ) {
                $subject_stats[ $sid ] = $subject_stats[ $sid ] ?? ['correct' => 0, 'total' => 0, 'name' => ''];
                $subject_stats[ $sid ]['total']++;
                if ( $d['is_correct'] ) $subject_stats[ $sid ]['correct']++;
                if ( empty( $subject_stats[ $sid ]['name'] ) ) {
                    $subject_stats[ $sid ]['name'] = get_field( 'subject_name_ar', $sid ) ?: get_the_title( $sid );
                    $subject_stats[ $sid ]['color'] = get_field( 'subject_color', $sid ) ?: '#4361ee';
                }
            }

            if ( $lid ) {
                $lesson_stats[ $lid ] = $lesson_stats[ $lid ] ?? ['correct' => 0, 'total' => 0, 'name' => ''];
                $lesson_stats[ $lid ]['total']++;
                if ( $d['is_correct'] ) $lesson_stats[ $lid ]['correct']++;
                if ( empty( $lesson_stats[ $lid ]['name'] ) ) {
                    $lesson_stats[ $lid ]['name'] = get_the_title( $lid );
                }
            }

            if ( isset( $diff_breakdown[ $dif ] ) ) {
                $diff_breakdown[ $dif ][1]++;
                if ( $d['is_correct'] ) $diff_breakdown[ $dif ][0]++;
            }
        }
    }

    // Add accuracy to subject/lesson stats
    foreach ( $subject_stats as &$s ) {
        $s['accuracy'] = $s['total'] > 0 ? round( $s['correct'] / $s['total'] * 100 ) : 0;
    }
    foreach ( $lesson_stats as &$l ) {
        $l['accuracy'] = $l['total'] > 0 ? round( $l['correct'] / $l['total'] * 100 ) : 0;
    }

    // Weak subjects/lessons (<60% accuracy)
    $weak_subjects = array_filter( $subject_stats, fn($s) => $s['accuracy'] < 60 );
    $weak_lessons  = array_filter( $lesson_stats,  fn($l) => $l['accuracy'] < 60 );
    uasort( $weak_subjects, fn($a,$b) => $a['accuracy'] - $b['accuracy'] );
    uasort( $weak_lessons,  fn($a,$b) => $a['accuracy'] - $b['accuracy'] );

    // Diff breakdown percentages
    foreach ( $diff_breakdown as &$dd ) {
        $dd['pct'] = $dd[1] > 0 ? round( $dd[0] / $dd[1] * 100 ) : 0;
    }

    // Sort daily activity (last 30 days)
    ksort( $daily_activity );
    $last_30 = [];
    for ( $i = ( $days ?: 30 ) - 1; $i >= 0; $i-- ) {
        $d = date( 'Y-m-d', strtotime( "-{$i} days" ) );
        $last_30[ $d ] = $daily_activity[ $d ] ?? 0;
    }

    return [
        'total_exams'     => count( $results ),
        'total_questions' => $total_q,
        'correct_total'   => $total_correct,
        'accuracy_pct'    => $total_q > 0 ? round( $total_correct / $total_q * 100 ) : 0,
        'avg_score'       => count( $scores ) > 0 ? round( array_sum( $scores ) / count( $scores ), 1 ) : 0,
        'exams_passed'    => $passed_count,
        'pass_rate'       => count( $results ) > 0 ? round( $passed_count / count( $results ) * 100 ) : 0,
        'total_xp'        => (int) get_user_meta( $user_id, 'eh_xp', true ),
        'streak'          => (int) get_user_meta( $user_id, 'eh_streak', true ),
        'subject_stats'   => $subject_stats,
        'lesson_stats'    => $lesson_stats,
        'weak_subjects'   => array_slice( $weak_subjects, 0, 5, true ),
        'weak_lessons'    => array_slice( $weak_lessons, 0, 5, true ),
        'daily_activity'  => $last_30,
        'recent_results'  => array_slice( $results, 0, 5 ),
        'diff_breakdown'  => $diff_breakdown,
    ];
}

/**
 * Get user's submitted results within a timeframe.
 *
 * @param int $user_id
 * @param int $days  0 = all time
 * @return WP_Post[]
 */
function examhub_get_user_results( $user_id, $days = 0 ) {
    $args = [
        'post_type'      => 'eh_result',
        'author'         => $user_id,
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'post_status'    => 'publish',
        'meta_query'     => [
            [
                'key'     => 'result_status',
                'value'   => [ 'submitted', 'timed_out' ],
                'compare' => 'IN',
            ],
        ],
    ];

    if ( $days > 0 ) {
        $args['date_query'] = [
            [
                'after'     => date( 'Y-m-d', strtotime( "-{$days} days" ) ),
                'inclusive' => true,
            ],
        ];
    }

    return get_posts( $args );
}

/**
 * Get global leaderboard.
 *
 * @param int    $limit
 * @param string $scope 'global' | 'grade'
 * @param int    $grade_id
 * @return array [ user_id, xp, name, avatar, grade, rank ]
 */
if ( ! function_exists( 'examhub_get_leaderboard' ) ) {
function examhub_get_leaderboard( $limit = 50, $scope = 'global', $grade_id = 0 ) {
    global $wpdb;

    if ( $scope === 'grade' && $grade_id ) {
        // Students who have taken exams in this grade
        $grade_users = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT p.post_author FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'result_exam_id'
            INNER JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = pm.meta_value AND pm2.meta_key = 'exam_grade' AND pm2.meta_value = %s
            WHERE p.post_type = 'eh_result' AND p.post_status = 'publish'",
            $grade_id
        ) );

        if ( empty( $grade_users ) ) return [];

        $users = get_users( [
            'include' => $grade_users,
            'number'  => $limit * 2,
            'meta_key'   => 'eh_xp',
            'orderby' => 'meta_value_num',
            'order'   => 'DESC',
        ] );
    } else {
        $users = get_users( [
            'meta_key'   => 'eh_xp',
            'meta_compare' => '>',
            'meta_value' => 0,
            'meta_type'  => 'NUMERIC',
            'orderby'    => 'meta_value_num',
            'order'      => 'DESC',
            'number'     => $limit,
        ] );
    }

    $board = [];
    $rank  = 1;
    foreach ( $users as $user ) {
        $xp = (int) get_user_meta( $user->ID, 'eh_xp', true );
        if ( ! $xp ) continue;
        $board[] = [
            'rank'   => $rank++,
            'user_id'=> $user->ID,
            'name'   => $user->display_name,
            'xp'     => $xp,
            'avatar' => get_avatar_url( $user->ID, [ 'size' => 50 ] ),
            'level'  => examhub_get_user_level( $xp )['name'],
        ];
        if ( count( $board ) >= $limit ) break;
    }

    return $board;
}
}

/**
 * Generate AI-powered weak area exam for a user.
 *
 * @param int $user_id
 * @param int $limit  Number of questions
 * @return int|null  Exam post ID or null
 */
function examhub_generate_weak_area_exam( $user_id, $limit = 10 ) {
    $analytics = examhub_get_user_analytics( $user_id, 60 );
    $weak_lessons = $analytics['weak_lessons'];

    if ( empty( $weak_lessons ) ) return null;

    $lesson_ids = array_keys( $weak_lessons );
    $q_ids = [];

    foreach ( $lesson_ids as $lid ) {
        $qs = get_posts( [
            'post_type'      => 'eh_question',
            'posts_per_page' => ceil( $limit / count( $lesson_ids ) ) + 2,
            'meta_query'     => [
                [ 'key' => 'lesson', 'value' => $lid ],
                [ 'key' => 'difficulty', 'value' => 'easy' ], // Start with easier for weak areas
            ],
            'orderby' => 'rand',
            'fields'  => 'ids',
        ] );
        $q_ids = array_merge( $q_ids, $qs );
        if ( count( $q_ids ) >= $limit ) break;
    }

    if ( empty( $q_ids ) ) return null;
    $q_ids = array_slice( array_unique( $q_ids ), 0, $limit );

    // Create temporary exam post
    $exam_id = wp_insert_post( [
        'post_type'   => 'eh_exam',
        'post_title'  => __( 'امتحان نقاط الضعف — مخصص', 'examhub' ),
        'post_status' => 'publish',
        'post_author' => $user_id,
        'meta_input'  => [
            '_eh_generated_for' => $user_id,
            '_eh_weak_area_exam'=> 1,
        ],
    ] );

    if ( is_wp_error( $exam_id ) ) return null;

    update_field( 'exam_questions', $q_ids, $exam_id );
    update_field( 'exam_type', 'weak_area', $exam_id );
    update_field( 'random_questions', true, $exam_id );
    update_field( 'random_answers', true, $exam_id );
    update_field( 'show_explanation', true, $exam_id );
    update_field( 'timer_type', 'none', $exam_id );
    update_field( 'exam_xp_reward', 30, $exam_id );

    return $exam_id;
}

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

/**
 * Track successful logins for admin analytics.
 *
 * @param string  $user_login
 * @param WP_User $user
 */
function examhub_track_user_login( $user_login, $user ) {
    if ( ! $user instanceof WP_User ) {
        return;
    }

    $today       = wp_date( 'Y-m-d', current_time( 'timestamp' ) );
    $login_stats = get_option( 'eh_login_daily_stats', [] );
    if ( ! is_array( $login_stats ) ) {
        $login_stats = [];
    }
    $login_stats[ $today ] = (int) ( $login_stats[ $today ] ?? 0 ) + 1;
    ksort( $login_stats );
    if ( count( $login_stats ) > 400 ) {
        $login_stats = array_slice( $login_stats, -400, null, true );
    }
    update_option( 'eh_login_daily_stats', $login_stats, false );

    update_user_meta( $user->ID, 'eh_last_login', current_time( 'mysql' ) );
    update_user_meta( $user->ID, 'eh_login_count', (int) get_user_meta( $user->ID, 'eh_login_count', true ) + 1 );
}
add_action( 'wp_login', 'examhub_track_user_login', 10, 2 );

/**
 * Register analytics page in WP admin.
 */
function examhub_register_admin_analytics_page() {
    add_submenu_page(
        'examhub-content',
        __( 'Analytics', 'examhub' ),
        __( 'Analytics', 'examhub' ),
        'eh_view_analytics',
        'examhub-analytics',
        'examhub_render_admin_analytics_page'
    );
}
add_action( 'admin_menu', 'examhub_register_admin_analytics_page' );

/**
 * Get date labels and zero-filled series.
 *
 * @param int $days
 * @return array
 */
function examhub_get_admin_analytics_day_map( $days ) {
    $days   = max( 1, (int) $days );
    $labels = [];

    for ( $i = $days - 1; $i >= 0; $i-- ) {
        $date            = wp_date( 'Y-m-d', strtotime( "-{$i} days", current_time( 'timestamp' ) ) );
        $labels[ $date ] = 0;
    }

    return $labels;
}

/**
 * Build a map of day => value from SQL results.
 *
 * @param array $rows
 * @param int   $days
 * @param string $value_key
 * @return array
 */
function examhub_map_daily_rows( $rows, $days, $value_key = 'count' ) {
    $series = examhub_get_admin_analytics_day_map( $days );

    foreach ( $rows as $row ) {
        $day = (string) ( $row['day'] ?? '' );
        if ( isset( $series[ $day ] ) ) {
            $series[ $day ] = is_numeric( $row[ $value_key ] ?? null ) ? (float) $row[ $value_key ] : 0;
        }
    }

    return $series;
}

/**
 * Gather global admin analytics.
 *
 * @param int $days
 * @return array
 */
function examhub_get_admin_analytics( $days = 30 ) {
    global $wpdb;

    $days        = max( 1, (int) $days );
    $start_ts    = strtotime( '-' . ( $days - 1 ) . ' days', current_time( 'timestamp' ) );
    $start_mysql = wp_date( 'Y-m-d 00:00:00', $start_ts );
    $users_table = $wpdb->users;
    $posts_table = $wpdb->posts;
    $meta_table  = $wpdb->postmeta;
    $usermeta    = $wpdb->usermeta;

    $total_users = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$users_table}" );
    $new_users   = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(ID) FROM {$users_table} WHERE user_registered >= %s",
        $start_mysql
    ) );

    $login_users = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT user_id) FROM {$usermeta}
         WHERE meta_key = 'eh_last_login'
           AND meta_value >= %s",
        $start_mysql
    ) );

    $total_payments = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$posts_table} WHERE post_type = 'eh_payment' AND post_status = 'publish'" );
    $paid_payments  = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT p.ID)
         FROM {$posts_table} p
         INNER JOIN {$meta_table} pm ON pm.post_id = p.ID
         WHERE p.post_type = 'eh_payment'
           AND p.post_status = 'publish'
           AND pm.meta_key = 'payment_status'
           AND pm.meta_value = 'paid'"
    );
    $refunded_payments = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT p.ID)
         FROM {$posts_table} p
         INNER JOIN {$meta_table} pm ON pm.post_id = p.ID
         WHERE p.post_type = 'eh_payment'
           AND p.post_status = 'publish'
           AND pm.meta_key = 'payment_status'
           AND pm.meta_value = 'refunded'"
    );

    $paid_users = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT p.post_author)
         FROM {$posts_table} p
         INNER JOIN {$meta_table} pm ON pm.post_id = p.ID
         WHERE p.post_type = 'eh_subscription'
           AND p.post_status = 'publish'
           AND pm.meta_key = 'sub_status'
           AND pm.meta_value IN ('active','trial','lifetime')"
    );

    $period_revenue = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(CAST(amount.meta_value AS DECIMAL(12,2))), 0)
         FROM {$posts_table} p
         INNER JOIN {$meta_table} status ON status.post_id = p.ID AND status.meta_key = 'payment_status' AND status.meta_value = 'paid'
         INNER JOIN {$meta_table} amount ON amount.post_id = p.ID AND amount.meta_key = 'amount_egp'
         WHERE p.post_type = 'eh_payment'
           AND p.post_status = 'publish'
           AND p.post_date >= %s",
        $start_mysql
    ) );

    $lifetime_revenue = (float) $wpdb->get_var(
        "SELECT COALESCE(SUM(CAST(amount.meta_value AS DECIMAL(12,2))), 0)
         FROM {$posts_table} p
         INNER JOIN {$meta_table} status ON status.post_id = p.ID AND status.meta_key = 'payment_status' AND status.meta_value = 'paid'
         INNER JOIN {$meta_table} amount ON amount.post_id = p.ID AND amount.meta_key = 'amount_egp'
         WHERE p.post_type = 'eh_payment'
           AND p.post_status = 'publish'"
    );

    $submitted_results = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT p.ID)
         FROM {$posts_table} p
         INNER JOIN {$meta_table} pm ON pm.post_id = p.ID
         WHERE p.post_type = 'eh_result'
           AND p.post_status = 'publish'
           AND pm.meta_key = 'result_status'
           AND pm.meta_value IN ('submitted','timed_out')
           AND p.post_date >= %s",
        $start_mysql
    ) );

    $avg_score = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(AVG(CAST(pm.meta_value AS DECIMAL(10,2))), 0)
         FROM {$posts_table} p
         INNER JOIN {$meta_table} status ON status.post_id = p.ID AND status.meta_key = 'result_status' AND status.meta_value IN ('submitted','timed_out')
         INNER JOIN {$meta_table} pm ON pm.post_id = p.ID AND pm.meta_key = 'percentage'
         WHERE p.post_type = 'eh_result'
           AND p.post_status = 'publish'
           AND p.post_date >= %s",
        $start_mysql
    ) );

    $pass_rate = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(AVG(CASE WHEN pm.meta_value = '1' THEN 100 ELSE 0 END), 0)
         FROM {$posts_table} p
         INNER JOIN {$meta_table} status ON status.post_id = p.ID AND status.meta_key = 'result_status' AND status.meta_value IN ('submitted','timed_out')
         INNER JOIN {$meta_table} pm ON pm.post_id = p.ID AND pm.meta_key = 'passed'
         WHERE p.post_type = 'eh_result'
           AND p.post_status = 'publish'
           AND p.post_date >= %s",
        $start_mysql
    ) );

    $login_stats = get_option( 'eh_login_daily_stats', [] );
    $logins_rows = [];
    if ( is_array( $login_stats ) ) {
        foreach ( $login_stats as $day => $count ) {
            if ( $day >= wp_date( 'Y-m-d', $start_ts ) ) {
                $logins_rows[] = [
                    'day'   => $day,
                    'count' => (int) $count,
                ];
            }
        }
    }

    $registrations_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT DATE(user_registered) AS day, COUNT(ID) AS count
         FROM {$users_table}
         WHERE user_registered >= %s
         GROUP BY DATE(user_registered)
         ORDER BY day ASC",
        $start_mysql
    ), ARRAY_A );

    $payments_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT DATE(p.post_date) AS day, COALESCE(SUM(CAST(amount.meta_value AS DECIMAL(12,2))), 0) AS revenue
         FROM {$posts_table} p
         INNER JOIN {$meta_table} status ON status.post_id = p.ID AND status.meta_key = 'payment_status' AND status.meta_value = 'paid'
         INNER JOIN {$meta_table} amount ON amount.post_id = p.ID AND amount.meta_key = 'amount_egp'
         WHERE p.post_type = 'eh_payment'
           AND p.post_status = 'publish'
           AND p.post_date >= %s
         GROUP BY DATE(p.post_date)
         ORDER BY day ASC",
        $start_mysql
    ), ARRAY_A );

    $results_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT DATE(p.post_date) AS day, COUNT(DISTINCT p.ID) AS count
         FROM {$posts_table} p
         INNER JOIN {$meta_table} status ON status.post_id = p.ID AND status.meta_key = 'result_status' AND status.meta_value IN ('submitted','timed_out')
         WHERE p.post_type = 'eh_result'
           AND p.post_status = 'publish'
           AND p.post_date >= %s
         GROUP BY DATE(p.post_date)
         ORDER BY day ASC",
        $start_mysql
    ), ARRAY_A );

    $payment_methods = $wpdb->get_results( $wpdb->prepare(
        "SELECT method.meta_value AS method, COUNT(DISTINCT p.ID) AS orders,
                COALESCE(SUM(CAST(amount.meta_value AS DECIMAL(12,2))), 0) AS revenue
         FROM {$posts_table} p
         INNER JOIN {$meta_table} status ON status.post_id = p.ID AND status.meta_key = 'payment_status' AND status.meta_value = 'paid'
         INNER JOIN {$meta_table} method ON method.post_id = p.ID AND method.meta_key = 'payment_method'
         INNER JOIN {$meta_table} amount ON amount.post_id = p.ID AND amount.meta_key = 'amount_egp'
         WHERE p.post_type = 'eh_payment'
           AND p.post_status = 'publish'
           AND p.post_date >= %s
         GROUP BY method.meta_value
         ORDER BY revenue DESC, orders DESC",
        $start_mysql
    ), ARRAY_A );

    $top_plans = $wpdb->get_results( $wpdb->prepare(
        "SELECT plan.meta_value AS plan_id, COUNT(DISTINCT p.ID) AS orders,
                COALESCE(SUM(CAST(amount.meta_value AS DECIMAL(12,2))), 0) AS revenue
         FROM {$posts_table} p
         INNER JOIN {$meta_table} status ON status.post_id = p.ID AND status.meta_key = 'payment_status' AND status.meta_value = 'paid'
         INNER JOIN {$meta_table} plan ON plan.post_id = p.ID AND plan.meta_key = 'pay_plan_id'
         INNER JOIN {$meta_table} amount ON amount.post_id = p.ID AND amount.meta_key = 'amount_egp'
         WHERE p.post_type = 'eh_payment'
           AND p.post_status = 'publish'
           AND p.post_date >= %s
         GROUP BY plan.meta_value
         ORDER BY revenue DESC, orders DESC
         LIMIT 8",
        $start_mysql
    ), ARRAY_A );

    $top_subjects = $wpdb->get_results( $wpdb->prepare(
        "SELECT subj.meta_value AS subject_id, COUNT(DISTINCT r.ID) AS attempts,
                COALESCE(AVG(CAST(score.meta_value AS DECIMAL(10,2))), 0) AS avg_score
         FROM {$posts_table} r
         INNER JOIN {$meta_table} r_status ON r_status.post_id = r.ID AND r_status.meta_key = 'result_status' AND r_status.meta_value IN ('submitted','timed_out')
         INNER JOIN {$meta_table} exam_link ON exam_link.post_id = r.ID AND exam_link.meta_key = 'result_exam_id'
         INNER JOIN {$meta_table} subj ON subj.post_id = exam_link.meta_value AND subj.meta_key = 'exam_subject'
         LEFT JOIN {$meta_table} score ON score.post_id = r.ID AND score.meta_key = 'percentage'
         WHERE r.post_type = 'eh_result'
           AND r.post_status = 'publish'
           AND r.post_date >= %s
         GROUP BY subj.meta_value
         ORDER BY attempts DESC, avg_score DESC
         LIMIT 8",
        $start_mysql
    ), ARRAY_A );

    $top_exams = $wpdb->get_results( $wpdb->prepare(
        "SELECT exam_link.meta_value AS exam_id, COUNT(DISTINCT r.ID) AS attempts,
                COALESCE(AVG(CAST(score.meta_value AS DECIMAL(10,2))), 0) AS avg_score
         FROM {$posts_table} r
         INNER JOIN {$meta_table} r_status ON r_status.post_id = r.ID AND r_status.meta_key = 'result_status' AND r_status.meta_value IN ('submitted','timed_out')
         INNER JOIN {$meta_table} exam_link ON exam_link.post_id = r.ID AND exam_link.meta_key = 'result_exam_id'
         LEFT JOIN {$meta_table} score ON score.post_id = r.ID AND score.meta_key = 'percentage'
         WHERE r.post_type = 'eh_result'
           AND r.post_status = 'publish'
           AND r.post_date >= %s
         GROUP BY exam_link.meta_value
         ORDER BY attempts DESC, avg_score DESC
         LIMIT 8",
        $start_mysql
    ), ARRAY_A );

    $recent_activity = $wpdb->get_results(
        "(SELECT p.post_date AS activity_date, 'payment' AS activity_type, p.ID AS object_id, p.post_author AS user_id
          FROM {$posts_table} p
          INNER JOIN {$meta_table} pm ON pm.post_id = p.ID AND pm.meta_key = 'payment_status' AND pm.meta_value IN ('paid','refunded')
          WHERE p.post_type = 'eh_payment' AND p.post_status = 'publish')
         UNION ALL
         (SELECT r.post_date AS activity_date, 'exam' AS activity_type, r.ID AS object_id, r.post_author AS user_id
          FROM {$posts_table} r
          INNER JOIN {$meta_table} rm ON rm.post_id = r.ID AND rm.meta_key = 'result_status' AND rm.meta_value IN ('submitted','timed_out')
          WHERE r.post_type = 'eh_result' AND r.post_status = 'publish')
         ORDER BY activity_date DESC
         LIMIT 12",
        ARRAY_A
    );

    return [
        'days'       => $days,
        'overview'   => [
            'total_users'         => $total_users,
            'new_users'           => $new_users,
            'active_users'        => $login_users,
            'paid_users'          => $paid_users,
            'conversion_rate'     => $total_users > 0 ? round( ( $paid_users / $total_users ) * 100, 1 ) : 0,
            'total_payments'      => $total_payments,
            'paid_payments'       => $paid_payments,
            'refunded_payments'   => $refunded_payments,
            'refund_rate'         => $paid_payments > 0 ? round( ( $refunded_payments / $paid_payments ) * 100, 1 ) : 0,
            'period_revenue'      => $period_revenue,
            'lifetime_revenue'    => $lifetime_revenue,
            'submitted_results'   => $submitted_results,
            'avg_score'           => round( $avg_score, 1 ),
            'pass_rate'           => round( $pass_rate, 1 ),
        ],
        'series'     => [
            'logins'        => examhub_map_daily_rows( $logins_rows, $days ),
            'registrations' => examhub_map_daily_rows( $registrations_rows, $days ),
            'payments'      => examhub_map_daily_rows( $payments_rows, $days, 'revenue' ),
            'results'       => examhub_map_daily_rows( $results_rows, $days ),
        ],
        'payment_methods' => $payment_methods,
        'top_plans'       => $top_plans,
        'top_subjects'    => $top_subjects,
        'top_exams'       => $top_exams,
        'recent_activity' => $recent_activity,
    ];
}

/**
 * Render admin analytics page.
 */
function examhub_render_admin_analytics_page() {
    if ( ! current_user_can( 'eh_view_analytics' ) ) {
        wp_die( esc_html__( 'You do not have permission to view analytics.', 'examhub' ), 403 );
    }

    $range      = isset( $_GET['range'] ) ? (int) $_GET['range'] : 30;
    $allowed    = [ 7, 30, 90, 365 ];
    $range      = in_array( $range, $allowed, true ) ? $range : 30;
    $analytics  = examhub_get_admin_analytics( $range );
    $overview   = $analytics['overview'];
    $labels     = array_keys( $analytics['series']['logins'] );
    $chart_data = [
        'labels'        => array_map( static fn( $day ) => wp_date( 'M j', strtotime( $day ) ), $labels ),
        'logins'        => array_values( $analytics['series']['logins'] ),
        'registrations' => array_values( $analytics['series']['registrations'] ),
        'payments'      => array_values( $analytics['series']['payments'] ),
        'results'       => array_values( $analytics['series']['results'] ),
    ];
    ?>
    <div class="wrap eh-admin-analytics">
        <h1><?php esc_html_e( 'ExamHub Analytics', 'examhub' ); ?></h1>
        <p class="description"><?php esc_html_e( 'Track revenue, engagement, learning activity, and platform performance from one screen.', 'examhub' ); ?></p>

        <div class="eh-admin-analytics-toolbar">
            <?php foreach ( $allowed as $days ) : ?>
                <?php
                $url   = add_query_arg( 'range', $days, menu_page_url( 'examhub-analytics', false ) );
                $class = $days === $range ? 'button button-primary' : 'button';
                ?>
                <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>">
                    <?php
                    printf(
                        /* translators: %d = days */
                        esc_html__( 'Last %d days', 'examhub' ),
                        $days
                    );
                    ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="eh-analytics-card-grid">
            <div class="eh-analytics-card">
                <span class="eh-analytics-label"><?php esc_html_e( 'Revenue', 'examhub' ); ?></span>
                <strong><?php echo esc_html( number_format_i18n( $overview['period_revenue'], 2 ) ); ?> EGP</strong>
                <small><?php printf( esc_html__( 'Lifetime: %s EGP', 'examhub' ), number_format_i18n( $overview['lifetime_revenue'], 2 ) ); ?></small>
            </div>
            <div class="eh-analytics-card">
                <span class="eh-analytics-label"><?php esc_html_e( 'Active Users', 'examhub' ); ?></span>
                <strong><?php echo esc_html( number_format_i18n( $overview['active_users'] ) ); ?></strong>
                <small><?php printf( esc_html__( 'New users: %s', 'examhub' ), number_format_i18n( $overview['new_users'] ) ); ?></small>
            </div>
            <div class="eh-analytics-card">
                <span class="eh-analytics-label"><?php esc_html_e( 'Paid Users', 'examhub' ); ?></span>
                <strong><?php echo esc_html( number_format_i18n( $overview['paid_users'] ) ); ?></strong>
                <small><?php printf( esc_html__( 'Conversion: %s%%', 'examhub' ), number_format_i18n( $overview['conversion_rate'], 1 ) ); ?></small>
            </div>
            <div class="eh-analytics-card">
                <span class="eh-analytics-label"><?php esc_html_e( 'Exam Completions', 'examhub' ); ?></span>
                <strong><?php echo esc_html( number_format_i18n( $overview['submitted_results'] ) ); ?></strong>
                <small><?php printf( esc_html__( 'Pass rate: %s%%', 'examhub' ), number_format_i18n( $overview['pass_rate'], 1 ) ); ?></small>
            </div>
            <div class="eh-analytics-card">
                <span class="eh-analytics-label"><?php esc_html_e( 'Payments', 'examhub' ); ?></span>
                <strong><?php echo esc_html( number_format_i18n( $overview['paid_payments'] ) ); ?></strong>
                <small><?php printf( esc_html__( 'Refund rate: %s%%', 'examhub' ), number_format_i18n( $overview['refund_rate'], 1 ) ); ?></small>
            </div>
            <div class="eh-analytics-card">
                <span class="eh-analytics-label"><?php esc_html_e( 'Average Score', 'examhub' ); ?></span>
                <strong><?php echo esc_html( number_format_i18n( $overview['avg_score'], 1 ) ); ?>%</strong>
                <small><?php printf( esc_html__( 'Total users: %s', 'examhub' ), number_format_i18n( $overview['total_users'] ) ); ?></small>
            </div>
        </div>

        <div class="eh-analytics-chart-grid">
            <div class="postbox">
                <div class="inside">
                    <h2><?php esc_html_e( 'User Activity', 'examhub' ); ?></h2>
                    <div class="eh-admin-chart-wrap">
                        <canvas id="eh-analytics-users-chart"></canvas>
                    </div>
                </div>
            </div>
            <div class="postbox">
                <div class="inside">
                    <h2><?php esc_html_e( 'Revenue and Exam Activity', 'examhub' ); ?></h2>
                    <div class="eh-admin-chart-wrap">
                        <canvas id="eh-analytics-business-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="eh-analytics-table-grid">
            <div class="postbox">
                <div class="inside">
                    <h2><?php esc_html_e( 'Top Plans', 'examhub' ); ?></h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Plan', 'examhub' ); ?></th>
                                <th><?php esc_html_e( 'Orders', 'examhub' ); ?></th>
                                <th><?php esc_html_e( 'Revenue', 'examhub' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $analytics['top_plans'] ) ) : ?>
                                <tr><td colspan="3"><?php esc_html_e( 'No paid plans in this range yet.', 'examhub' ); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $analytics['top_plans'] as $row ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( $row['plan_id'] ?: '—' ); ?></td>
                                        <td><?php echo esc_html( number_format_i18n( (int) $row['orders'] ) ); ?></td>
                                        <td><?php echo esc_html( number_format_i18n( (float) $row['revenue'], 2 ) ); ?> EGP</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="postbox">
                <div class="inside">
                    <h2><?php esc_html_e( 'Payment Methods', 'examhub' ); ?></h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Method', 'examhub' ); ?></th>
                                <th><?php esc_html_e( 'Orders', 'examhub' ); ?></th>
                                <th><?php esc_html_e( 'Revenue', 'examhub' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $analytics['payment_methods'] ) ) : ?>
                                <tr><td colspan="3"><?php esc_html_e( 'No payment data for this range yet.', 'examhub' ); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $analytics['payment_methods'] as $row ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( $row['method'] ?: '—' ); ?></td>
                                        <td><?php echo esc_html( number_format_i18n( (int) $row['orders'] ) ); ?></td>
                                        <td><?php echo esc_html( number_format_i18n( (float) $row['revenue'], 2 ) ); ?> EGP</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="postbox">
                <div class="inside">
                    <h2><?php esc_html_e( 'Top Subjects by Attempts', 'examhub' ); ?></h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Subject', 'examhub' ); ?></th>
                                <th><?php esc_html_e( 'Attempts', 'examhub' ); ?></th>
                                <th><?php esc_html_e( 'Avg Score', 'examhub' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $analytics['top_subjects'] ) ) : ?>
                                <tr><td colspan="3"><?php esc_html_e( 'No subject activity in this range yet.', 'examhub' ); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $analytics['top_subjects'] as $row ) : ?>
                                    <?php
                                    $subject_id   = (int) ( $row['subject_id'] ?? 0 );
                                    $subject_name = $subject_id ? ( get_field( 'subject_name_ar', $subject_id ) ?: get_the_title( $subject_id ) ) : __( 'Unknown', 'examhub' );
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html( $subject_name ); ?></td>
                                        <td><?php echo esc_html( number_format_i18n( (int) $row['attempts'] ) ); ?></td>
                                        <td><?php echo esc_html( number_format_i18n( (float) $row['avg_score'], 1 ) ); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="postbox">
                <div class="inside">
                    <h2><?php esc_html_e( 'Top Exams', 'examhub' ); ?></h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Exam', 'examhub' ); ?></th>
                                <th><?php esc_html_e( 'Attempts', 'examhub' ); ?></th>
                                <th><?php esc_html_e( 'Avg Score', 'examhub' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $analytics['top_exams'] ) ) : ?>
                                <tr><td colspan="3"><?php esc_html_e( 'No exam activity in this range yet.', 'examhub' ); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $analytics['top_exams'] as $row ) : ?>
                                    <?php $exam_id = (int) ( $row['exam_id'] ?? 0 ); ?>
                                    <tr>
                                        <td><?php echo esc_html( $exam_id ? get_the_title( $exam_id ) : __( 'Unknown exam', 'examhub' ) ); ?></td>
                                        <td><?php echo esc_html( number_format_i18n( (int) $row['attempts'] ) ); ?></td>
                                        <td><?php echo esc_html( number_format_i18n( (float) $row['avg_score'], 1 ) ); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="postbox">
            <div class="inside">
                <h2><?php esc_html_e( 'Recent Platform Activity', 'examhub' ); ?></h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'When', 'examhub' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'examhub' ); ?></th>
                            <th><?php esc_html_e( 'User', 'examhub' ); ?></th>
                            <th><?php esc_html_e( 'Details', 'examhub' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $analytics['recent_activity'] ) ) : ?>
                            <tr><td colspan="4"><?php esc_html_e( 'No recent activity found.', 'examhub' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $analytics['recent_activity'] as $item ) : ?>
                                <?php
                                $user    = ! empty( $item['user_id'] ) ? get_userdata( (int) $item['user_id'] ) : null;
                                $type    = (string) $item['activity_type'];
                                $details = $type === 'payment' ? get_field( 'pay_plan_id', (int) $item['object_id'] ) : get_the_title( (int) get_field( 'result_exam_id', (int) $item['object_id'] ) );
                                ?>
                                <tr>
                                    <td><?php echo esc_html( wp_date( 'Y-m-d H:i', strtotime( $item['activity_date'] ) ) ); ?></td>
                                    <td><?php echo esc_html( ucfirst( $type ) ); ?></td>
                                    <td><?php echo esc_html( $user ? $user->display_name : __( 'Unknown user', 'examhub' ) ); ?></td>
                                    <td><?php echo esc_html( $details ?: '—' ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') return;

        const chartData = <?php echo wp_json_encode( $chart_data ); ?>;

        const usersCtx = document.getElementById('eh-analytics-users-chart');
        if (usersCtx) {
            new Chart(usersCtx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Logins',
                            data: chartData.logins,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37,99,235,.12)',
                            tension: 0.35,
                            fill: true
                        },
                        {
                            label: 'Registrations',
                            data: chartData.registrations,
                            borderColor: '#16a34a',
                            backgroundColor: 'rgba(22,163,74,.12)',
                            tension: 0.35,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        }

        const businessCtx = document.getElementById('eh-analytics-business-chart');
        if (businessCtx) {
            new Chart(businessCtx, {
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Exam completions',
                            data: chartData.results,
                            backgroundColor: 'rgba(124,58,237,.25)',
                            borderColor: '#7c3aed',
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            type: 'line',
                            label: 'Revenue (EGP)',
                            data: chartData.payments,
                            borderColor: '#ea580c',
                            backgroundColor: 'rgba(234,88,12,.1)',
                            tension: 0.35,
                            fill: true,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left'
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        }
    });
    </script>
    <?php
}

<?php
/**
 * ExamHub — Gamification
 * Badge awards, leaderboard generation, streak system, XP logs.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════════
// BADGE SYSTEM
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Check and award any badges a user has earned.
 * Called after exam submission, XP updates, streak changes.
 *
 * @param int $user_id
 */
function examhub_check_and_award_badges( $user_id ) {
    $badges = get_posts( [
        'post_type'      => 'eh_badge',
        'posts_per_page' => 100,
        'post_status'    => 'publish',
    ] );

    if ( empty( $badges ) ) return;

    $earned_badges = (array) get_user_meta( $user_id, 'eh_badges', true );

    foreach ( $badges as $badge ) {
        $bid = $badge->ID;
        if ( in_array( $bid, $earned_badges ) ) continue; // Already earned

        $trigger   = get_field( 'badge_trigger', $bid );
        $threshold = (int) get_field( 'badge_threshold', $bid );
        $should_award = false;

        switch ( $trigger ) {
            case 'first_exam':
                $count = examhub_count_user_exams( $user_id );
                $should_award = $count >= 1;
                break;

            case 'perfect_score':
                $should_award = examhub_user_has_perfect_score( $user_id );
                break;

            case 'streak_3':
                $should_award = (int) get_user_meta( $user_id, 'eh_streak', true ) >= 3;
                break;

            case 'streak_7':
                $should_award = (int) get_user_meta( $user_id, 'eh_streak', true ) >= 7;
                break;

            case 'streak_30':
                $should_award = (int) get_user_meta( $user_id, 'eh_streak', true ) >= 30;
                break;

            case 'top_leaderboard':
                $rank = examhub_get_user_rank( $user_id );
                $should_award = $rank !== null && $rank <= 3;
                break;

            case 'xp_milestone':
                $xp = (int) get_user_meta( $user_id, 'eh_xp', true );
                $should_award = $xp >= $threshold;
                break;

            case 'exams_count':
                $should_award = examhub_count_user_exams( $user_id ) >= $threshold;
                break;
        }

        if ( $should_award ) {
            examhub_award_badge( $user_id, $bid );
        }
    }
}
add_action( 'examhub_exam_submitted',   fn( $eid, $rid, $uid ) => examhub_check_and_award_badges( $uid ), 10, 3 );
add_action( 'examhub_xp_added',         fn( $uid ) => examhub_check_and_award_badges( $uid ), 10, 1 );
add_action( 'examhub_streak_updated',   fn( $uid ) => examhub_check_and_award_badges( $uid ), 10, 1 );

/**
 * Award a badge to a user.
 */
function examhub_award_badge( $user_id, $badge_id ) {
    $earned_badges = (array) get_user_meta( $user_id, 'eh_badges', true );
    if ( in_array( $badge_id, $earned_badges ) ) return;

    $earned_badges[] = $badge_id;
    update_user_meta( $user_id, 'eh_badges', $earned_badges );

    // Award XP
    $xp_reward = (int) get_field( 'badge_xp_reward', $badge_id );
    if ( $xp_reward > 0 ) {
        examhub_add_xp( $user_id, $xp_reward, 'شارة: ' . get_the_title( $badge_id ) );
    }

    do_action( 'examhub_badge_awarded', $user_id, $badge_id );
    examhub_log( "Badge awarded: user={$user_id} badge={$badge_id}" );
}

/**
 * Get all badges with earned status for a user.
 *
 * @param int $user_id
 * @return array
 */
function examhub_get_user_badges( $user_id ) {
    $all_badges    = get_posts( [ 'post_type' => 'eh_badge', 'posts_per_page' => 100, 'post_status' => 'publish' ] );
    $earned_ids    = (array) get_user_meta( $user_id, 'eh_badges', true );

    $result = [];
    foreach ( $all_badges as $badge ) {
        $result[] = [
            'id'       => $badge->ID,
            'name'     => $badge->post_title,
            'icon_url' => get_field( 'badge_icon', $badge->ID ),
            'xp'       => (int) get_field( 'badge_xp_reward', $badge->ID ),
            'rare'     => (bool) get_field( 'is_rare', $badge->ID ),
            'earned'   => in_array( $badge->ID, $earned_ids ),
            'earned_at'=> null, // Could extend to store timestamp
        ];
    }

    // Earned first
    usort( $result, fn( $a, $b ) => $b['earned'] - $a['earned'] );

    return $result;
}

// ═══════════════════════════════════════════════════════════════════════════════
// LEADERBOARD
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Get leaderboard (cached).
 *
 * @param string $type     global | grade
 * @param int    $grade_id Grade post ID for grade leaderboard
 * @param int    $limit
 * @return array
 */
function examhub_get_leaderboard( $type = 'global', $grade_id = 0, $limit = 50 ) {
    // Backward compatibility:
    // legacy order was ( $limit, $scope, $grade_id ).
    if ( is_numeric( $type ) ) {
        $legacy_limit = (int) $type;
        $legacy_scope = is_string( $grade_id ) ? $grade_id : 'global';
        $legacy_grade = (int) $limit;
        $type     = $legacy_scope;
        $grade_id = $legacy_grade;
        $limit    = $legacy_limit;
    }

    $cache_key = $type === 'grade'
        ? "eh_leaderboard_grade_{$grade_id}"
        : 'eh_leaderboard_global';

    $cached = get_transient( $cache_key );
    if ( $cached !== false ) return $cached;

    global $wpdb;

    if ( $type === 'grade' && $grade_id ) {
        // Users who have set their grade
        $user_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta}
            WHERE meta_key = 'eh_grade_id' AND meta_value = %s
            LIMIT 1000",
            $grade_id
        ) );

        $board = examhub_build_leaderboard_from_users( $user_ids, $limit );
    } else {
        // Global — top XP earners among students
        $board = examhub_build_global_leaderboard( $limit );
    }

    set_transient( $cache_key, $board, 5 * MINUTE_IN_SECONDS );

    return $board;
}

/**
 * Build global leaderboard from user meta.
 */
function examhub_build_global_leaderboard( $limit ) {
    global $wpdb;

    $results = $wpdb->get_results( $wpdb->prepare(
        "SELECT u.ID as user_id, u.display_name,
            MAX(CASE WHEN um.meta_key = 'eh_xp' THEN CAST(um.meta_value AS UNSIGNED) END) as xp,
            MAX(CASE WHEN um.meta_key = 'eh_streak' THEN CAST(um.meta_value AS UNSIGNED) END) as streak,
            MAX(CASE WHEN um.meta_key = 'eh_grade_id' THEN um.meta_value END) as grade_id
        FROM {$wpdb->users} u
        INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = 'eh_xp'
        INNER JOIN {$wpdb->usermeta} ur ON ur.user_id = u.ID AND ur.meta_key = %s AND ur.meta_value = %s
        GROUP BY u.ID
        ORDER BY xp DESC
        LIMIT %d",
        $wpdb->get_blog_prefix() . 'capabilities',
        '%eh_student%',
        $limit
    ) );

    return examhub_format_leaderboard_rows( $results );
}

/**
 * Build leaderboard from specific user IDs.
 */
function examhub_build_leaderboard_from_users( $user_ids, $limit ) {
    if ( empty( $user_ids ) ) return [];

    $board = [];
    foreach ( $user_ids as $uid ) {
        $xp = (int) get_user_meta( $uid, 'eh_xp', true );
        if ( $xp === 0 ) continue;
        $user = get_userdata( $uid );
        if ( ! $user ) continue;
        $board[] = (object) [
            'user_id'      => $uid,
            'display_name' => $user->display_name,
            'xp'           => $xp,
            'streak'       => (int) get_user_meta( $uid, 'eh_streak', true ),
            'grade_id'     => get_user_meta( $uid, 'eh_grade_id', true ),
        ];
    }

    usort( $board, fn( $a, $b ) => $b->xp - $a->xp );
    return examhub_format_leaderboard_rows( array_slice( $board, 0, $limit ) );
}

/**
 * Format leaderboard rows for output.
 */
function examhub_format_leaderboard_rows( $rows ) {
    $current_user_id = get_current_user_id();
    $formatted = [];

    foreach ( $rows as $i => $row ) {
        $uid = (int) $row->user_id;
        $grade_name = $row->grade_id ? get_the_title( (int) $row->grade_id ) : '';
        $level_info = examhub_get_user_level( (int) $row->xp );

        $formatted[] = [
            'rank'       => $i + 1,
            'user_id'    => $uid,
            'name'       => $row->display_name,
            'avatar'     => get_avatar_url( $uid, [ 'size' => 60 ] ),
            'xp'         => (int) $row->xp,
            'streak'     => (int) ( $row->streak ?? 0 ),
            'grade'      => $grade_name,
            'level'      => $level_info['name'],
            'is_current' => $uid === $current_user_id,
        ];
    }

    return $formatted;
}

/**
 * Get current user's rank in global leaderboard.
 */
function examhub_get_user_rank( $user_id ) {
    $board = examhub_get_leaderboard( 'global', 0, 200 );
    foreach ( $board as $row ) {
        if ( (int) $row['user_id'] === (int) $user_id ) {
            return $row['rank'];
        }
    }
    return null;
}

// ═══════════════════════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════════════════════

function examhub_count_user_exams( $user_id ) {
    return (int) ( new WP_Query( [
        'post_type'      => 'eh_result',
        'author'         => $user_id,
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [ [ 'key' => 'result_status', 'value' => 'submitted' ] ],
    ] ) )->found_posts;
}

function examhub_user_has_perfect_score( $user_id ) {
    $results = get_posts( [
        'post_type'      => 'eh_result',
        'author'         => $user_id,
        'posts_per_page' => 1,
        'meta_query'     => [
            [ 'key' => 'percentage',    'value' => 100,         'compare' => '>=', 'type' => 'NUMERIC' ],
            [ 'key' => 'result_status', 'value' => 'submitted' ],
        ],
    ] );
    return ! empty( $results );
}

// ═══════════════════════════════════════════════════════════════════════════════
// DAILY CHALLENGE
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Get or create today's daily challenge exam.
 *
 * @return int|null Exam post ID
 */
function examhub_get_daily_challenge() {
    if ( ! get_field( 'daily_challenge_enabled', 'option' ) ) return null;

    $today_key = 'eh_daily_challenge_' . date( 'Y-m-d' );
    $cached    = get_transient( $today_key );
    if ( $cached ) return (int) $cached;

    // Look for a manually-flagged daily challenge exam
    $manual = get_posts( [
        'post_type'      => 'eh_exam',
        'posts_per_page' => 1,
        'date_query'     => [ [ 'after' => 'yesterday', 'inclusive' => true ] ],
        'meta_query'     => [ [ 'key' => 'exam_type', 'value' => 'daily' ] ],
    ] );

    if ( ! empty( $manual ) ) {
        set_transient( $today_key, $manual[0]->ID, DAY_IN_SECONDS );
        return $manual[0]->ID;
    }

    return null;
}

/**
 * Get user's dashboard stats.
 */
function examhub_get_user_dashboard_stats( $user_id ) {
    $xp      = (int) get_user_meta( $user_id, 'eh_xp', true );
    $streak  = (int) get_user_meta( $user_id, 'eh_streak', true );
    $level   = examhub_get_user_level( $xp );
    $rank    = examhub_get_user_rank( $user_id );
    $badges  = examhub_get_user_badges( $user_id );
    $sub     = examhub_get_user_subscription_status( $user_id );

    $total_exams = examhub_count_user_exams( $user_id );

    // Today's activity
    $last_activity = get_user_meta( $user_id, 'eh_last_activity', true );
    $active_today  = $last_activity === date( 'Y-m-d' );

    // Recent results
    $recent_results = get_posts( [
        'post_type'      => 'eh_result',
        'author'         => $user_id,
        'posts_per_page' => 5,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [ [ 'key' => 'result_status', 'value' => 'submitted' ] ],
    ] );

    return [
        'xp'               => $xp,
        'streak'           => $streak,
        'level'            => $level,
        'rank'             => $rank,
        'badges_earned'    => count( array_filter( $badges, fn($b) => $b['earned'] ) ),
        'badges_total'     => count( $badges ),
        'total_exams'      => $total_exams,
        'active_today'     => $active_today,
        'subscription'     => $sub,
        'remaining_questions' => examhub_get_remaining_questions( $user_id ),
        'recent_results'   => $recent_results,
    ];
}

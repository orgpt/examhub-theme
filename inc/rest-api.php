<?php
/**
 * ExamHub — REST API Extensions
 * Custom REST endpoints for mobile/SPA consumption.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'examhub_register_rest_routes' );

function examhub_register_rest_routes() {
    $ns = 'examhub/v1';

    // Hierarchy
    register_rest_route( $ns, '/stages/(?P<system_id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'examhub_rest_get_stages',
        'permission_callback' => '__return_true',
        'args'                => [ 'system_id' => [ 'validate_callback' => fn($v) => is_numeric($v) ] ],
    ] );

    register_rest_route( $ns, '/grades/(?P<stage_id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'examhub_rest_get_grades',
        'permission_callback' => '__return_true',
    ] );

    register_rest_route( $ns, '/subjects/(?P<grade_id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'examhub_rest_get_subjects',
        'permission_callback' => '__return_true',
    ] );

    register_rest_route( $ns, '/exams', [
        'methods'             => 'GET',
        'callback'            => 'examhub_rest_get_exams',
        'permission_callback' => '__return_true',
    ] );

    register_rest_route( $ns, '/exams/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'examhub_rest_get_exam',
        'permission_callback' => '__return_true',
    ] );

    // User
    register_rest_route( $ns, '/me', [
        'methods'             => 'GET',
        'callback'            => 'examhub_rest_get_me',
        'permission_callback' => 'is_user_logged_in',
    ] );

    register_rest_route( $ns, '/me/analytics', [
        'methods'             => 'GET',
        'callback'            => 'examhub_rest_get_analytics',
        'permission_callback' => 'is_user_logged_in',
    ] );

    register_rest_route( $ns, '/me/results', [
        'methods'             => 'GET',
        'callback'            => 'examhub_rest_get_results',
        'permission_callback' => 'is_user_logged_in',
    ] );

    // Leaderboard
    register_rest_route( $ns, '/leaderboard', [
        'methods'             => 'GET',
        'callback'            => 'examhub_rest_leaderboard',
        'permission_callback' => '__return_true',
    ] );

    // Plans
    register_rest_route( $ns, '/plans', [
        'methods'             => 'GET',
        'callback'            => 'examhub_rest_get_plans',
        'permission_callback' => '__return_true',
    ] );
}

// ─── Callbacks ────────────────────────────────────────────────────────────────

function examhub_rest_get_stages( WP_REST_Request $req ) {
    $system_id = (int) $req->get_param( 'system_id' );
    $stages    = examhub_get_children_of( 'eh_stage', 'stage_education_system', $system_id );
    return rest_ensure_response( examhub_format_posts_for_select( $stages, 'stage_name_ar' ) );
}

function examhub_rest_get_grades( WP_REST_Request $req ) {
    $stage_id = (int) $req->get_param( 'stage_id' );
    $grades   = examhub_get_children_of( 'eh_grade', 'grade_stage', $stage_id );
    return rest_ensure_response( examhub_format_posts_for_select( $grades, 'grade_name_ar' ) );
}

function examhub_rest_get_subjects( WP_REST_Request $req ) {
    $grade_id = (int) $req->get_param( 'grade_id' );
    $subjects = examhub_get_children_of( 'eh_subject', 'subject_grade', $grade_id );
    return rest_ensure_response( examhub_format_posts_for_select( $subjects, 'subject_name_ar' ) );
}

function examhub_rest_get_exams( WP_REST_Request $req ) {
    $query = examhub_get_exams_query( [
        'grade'   => (int) $req->get_param( 'grade_id' ),
        'subject' => (int) $req->get_param( 'subject_id' ),
        'paged'   => (int) ( $req->get_param( 'page' ) ?: 1 ),
        'per_page'=> min( 50, (int) ( $req->get_param( 'per_page' ) ?: 12 ) ),
    ] );

    $data = [];
    foreach ( $query->posts as $post ) {
        $data[] = examhub_rest_format_exam( $post->ID );
    }

    return rest_ensure_response( [
        'exams'      => $data,
        'total'      => $query->found_posts,
        'max_pages'  => $query->max_num_pages,
    ] );
}

function examhub_rest_get_exam( WP_REST_Request $req ) {
    $id   = (int) $req->get_param( 'id' );
    $post = get_post( $id );
    if ( ! $post || $post->post_type !== 'eh_exam' ) {
        return new WP_Error( 'not_found', 'Exam not found', [ 'status' => 404 ] );
    }
    return rest_ensure_response( examhub_rest_format_exam( $id, true ) );
}

function examhub_rest_get_me( WP_REST_Request $req ) {
    $uid  = get_current_user_id();
    $user = wp_get_current_user();
    $sub  = examhub_get_user_subscription_status( $uid );
    $xp   = (int) get_user_meta( $uid, 'eh_xp', true );

    return rest_ensure_response( [
        'id'           => $uid,
        'name'         => $user->display_name,
        'email'        => $user->user_email,
        'avatar'       => get_avatar_url( $uid ),
        'xp'           => $xp,
        'level'        => examhub_get_user_level( $xp ),
        'streak'       => (int) get_user_meta( $uid, 'eh_streak', true ),
        'subscription' => $sub,
        'remaining_questions' => examhub_get_remaining_questions( $uid ),
    ] );
}

function examhub_rest_get_analytics( WP_REST_Request $req ) {
    return rest_ensure_response( examhub_get_user_analytics( get_current_user_id() ) );
}

function examhub_rest_get_results( WP_REST_Request $req ) {
    $uid   = get_current_user_id();
    $paged = (int) ( $req->get_param( 'page' ) ?: 1 );

    $results = get_posts( [
        'post_type'      => 'eh_result',
        'author'         => $uid,
        'posts_per_page' => 10,
        'paged'          => $paged,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [ [ 'key' => 'result_status', 'value' => 'submitted' ] ],
    ] );

    $data = array_map( fn($r) => [
        'id'         => $r->ID,
        'exam_id'    => (int) get_field( 'result_exam_id', $r->ID ),
        'exam_title' => get_the_title( (int) get_field( 'result_exam_id', $r->ID ) ),
        'score'      => (float) get_field( 'score',      $r->ID ),
        'total'      => (float) get_field( 'total_points', $r->ID ),
        'percentage' => (float) get_field( 'percentage', $r->ID ),
        'passed'     => (bool)  get_field( 'passed',     $r->ID ),
        'date'       => $r->post_date,
    ], $results );

    return rest_ensure_response( [ 'results' => $data ] );
}

function examhub_rest_leaderboard( WP_REST_Request $req ) {
    $type     = sanitize_text_field( $req->get_param( 'type' ) ?: 'global' );
    $grade_id = (int) $req->get_param( 'grade_id' );
    return rest_ensure_response( examhub_get_leaderboard( $type, $grade_id, 50 ) );
}

function examhub_rest_get_plans( WP_REST_Request $req ) {
    $plans = examhub_get_all_plans();
    usort( $plans, fn($a,$b) => (int)($a['plan_priority']??0) - (int)($b['plan_priority']??0) );
    return rest_ensure_response( array_values( $plans ) );
}

// ─── Format helpers ───────────────────────────────────────────────────────────

function examhub_rest_format_exam( $exam_id, $detailed = false ) {
    $data = [
        'id'            => $exam_id,
        'title'         => get_the_title( $exam_id ),
        'slug'          => get_post_field( 'post_name', $exam_id ),
        'url'           => get_permalink( $exam_id ),
        'thumbnail'     => get_the_post_thumbnail_url( $exam_id, 'exam-thumbnail' ),
        'grade_id'      => (int) get_field( 'exam_grade',   $exam_id ),
        'subject_id'    => (int) get_field( 'exam_subject', $exam_id ),
        'grade'         => get_the_title( (int) get_field( 'exam_grade',   $exam_id ) ),
        'subject'       => get_the_title( (int) get_field( 'exam_subject', $exam_id ) ),
        'difficulty'    => get_field( 'exam_difficulty', $exam_id ),
        'timer_type'    => get_field( 'timer_type',      $exam_id ),
        'duration_min'  => (int) get_field( 'exam_duration_minutes', $exam_id ),
        'question_count'=> examhub_get_exam_question_count( $exam_id ),
        'access'        => get_field( 'exam_access',     $exam_id ),
        'xp_reward'     => (int) get_field( 'exam_xp_reward', $exam_id ),
        'date'          => get_post_field( 'post_date', $exam_id ),
    ];

    if ( $detailed ) {
        $data['allow_skip']   = (bool) get_field( 'allow_skip',        $exam_id );
        $data['allow_resume'] = (bool) get_field( 'allow_resume',      $exam_id );
        $data['pass_pct']     = (float) get_field( 'pass_percentage',  $exam_id );
        $data['description']  = get_post_field( 'post_content',        $exam_id );
    }

    return $data;
}

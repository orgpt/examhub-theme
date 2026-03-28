<?php
/**
 * ExamHub — Template Hooks
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

// Redirect question singles (not public)
add_action( 'template_redirect', function() {
    if ( is_singular( 'eh_question' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_redirect( home_url() ); exit;
    }
    // Redirect subscription/payment CPT singles
    if ( is_singular( [ 'eh_subscription', 'eh_payment' ] ) ) {
        wp_redirect( home_url( '/dashboard' ) ); exit;
    }
} );

// Inject exam config into localize data on exam single
add_filter( 'examhub_exam_js_config', function( $config, $exam_id ) {
    $config['exam_url']       = get_permalink( $exam_id );
    $config['ajax_url']       = admin_url( 'admin-ajax.php' );
    $config['nonce']          = wp_create_nonce( 'examhub_ajax' );
    $config['duration_seconds'] = (int)get_field('exam_duration_minutes',$exam_id) * 60;
    $config['sec_per_question'] = (int)get_field('seconds_per_question',$exam_id) ?: 60;
    return $config;
}, 10, 2 );

// SEO: Set exam description
add_filter( 'document_title_parts', function( $title ) {
    if ( is_singular( 'eh_exam' ) ) {
        $grade   = get_the_title( (int) get_field( 'exam_grade',   get_the_ID() ) );
        $subject = get_the_title( (int) get_field( 'exam_subject', get_the_ID() ) );
        if ( $grade && $subject ) {
            $title['tagline'] = "{$subject} — {$grade}";
        }
    }
    return $title;
} );

// Increment question usage count when included in exam
add_action( 'examhub_exam_submitted', function( $exam_id, $result_id, $user_id ) {
    $q_ids = examhub_build_exam_question_list( $exam_id );
    foreach ( $q_ids as $qid ) {
        $current = (int) get_field( 'usage_count', $qid );
        update_field( 'usage_count', $current + 1, $qid );
    }
}, 10, 3 );

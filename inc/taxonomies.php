<?php
/**
 * ExamHub — Custom Taxonomies
 * Registers shared taxonomies used across CPTs for tagging and discovery.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'examhub_register_taxonomies', 5 );

function examhub_register_taxonomies() {

    // ─── Question Tags ─────────────────────────────────────────────────────
    register_taxonomy( 'eh_tag', [ 'eh_question', 'eh_exam' ], [
        'label'             => __( 'وسوم الأسئلة', 'examhub' ),
        'labels'            => [
            'name'          => __( 'الوسوم', 'examhub' ),
            'singular_name' => __( 'وسم', 'examhub' ),
            'add_new_item'  => __( 'أضف وسماً جديداً', 'examhub' ),
            'search_items'  => __( 'ابحث عن وسم', 'examhub' ),
        ],
        'hierarchical'      => false,
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => [ 'slug' => 'question-tag' ],
    ] );

    // ─── Academic Year ──────────────────────────────────────────────────────
    register_taxonomy( 'eh_academic_year', [ 'eh_exam', 'eh_question' ], [
        'label'             => __( 'العام الدراسي', 'examhub' ),
        'hierarchical'      => false,
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => [ 'slug' => 'academic-year' ],
    ] );

    // ─── Exam Type Taxonomy (optional classification) ──────────────────────
    register_taxonomy( 'eh_exam_type_tax', [ 'eh_exam' ], [
        'label'        => __( 'نوع الامتحان', 'examhub' ),
        'hierarchical' => true,
        'public'       => true,
        'show_ui'      => true,
        'show_in_rest' => true,
        'rewrite'      => [ 'slug' => 'exam-type' ],
    ] );
}

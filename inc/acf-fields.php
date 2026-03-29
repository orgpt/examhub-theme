<?php
/**
 * ExamHub — ACF Field Groups (PHP Registration)
 * All field groups registered via acf_add_local_field_group() so they work
 * without the ACF JSON sync. JSON exports will override these when present.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', 'examhub_register_acf_fields' );

function examhub_register_acf_fields() {

    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. QUESTION FIELDS
    // ═══════════════════════════════════════════════════════════════════════
    acf_add_local_field_group( [
        'key'      => 'group_question',
        'title'    => 'بيانات السؤال / Question Data',
        'fields'   => [

            // Question Type
            [
                'key'           => 'field_q_type',
                'label'         => 'نوع السؤال',
                'name'          => 'question_type',
                'type'          => 'select',
                'required'      => 1,
                'choices'       => [
                    'mcq'          => 'اختيار من متعدد (MCQ)',
                    'true_false'   => 'صح / خطأ',
                    'correct'      => 'اختر الإجابة الصحيحة',
                    'fill_blank'   => 'اكمل الفراغ',
                    'matching'     => 'مطابقة',
                    'ordering'     => 'ترتيب',
                    'essay'        => 'مقال',
                    'image'        => 'سؤال بالصورة',
                    'math'         => 'معادلة رياضية',
                ],
                'default_value' => 'mcq',
                'allow_null'    => 0,
            ],

            // Question Text (Arabic + English)
            [
                'key'   => 'field_q_text',
                'label' => 'نص السؤال',
                'name'  => 'question_text',
                'type'  => 'textarea',
                'rows'  => 4,
                'required' => 1,
            ],
            [
                'key'   => 'field_q_text_en',
                'label' => 'نص السؤال (إنجليزي)',
                'name'  => 'question_text_en',
                'type'  => 'textarea',
                'rows'  => 4,
            ],

            // Question Image
            [
                'key'           => 'field_q_image',
                'label'         => 'صورة السؤال',
                'name'          => 'question_image',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'conditional_logic' => [
                    [ [ 'field' => 'field_q_type', 'operator' => '==', 'value' => 'image' ] ],
                ],
            ],

            // Math Equation
            [
                'key'   => 'field_q_math',
                'label' => 'المعادلة (LaTeX)',
                'name'  => 'question_math',
                'type'  => 'text',
                'instructions' => 'أدخل المعادلة بصيغة LaTeX مثل: \\frac{1}{2}',
                'conditional_logic' => [
                    [ [ 'field' => 'field_q_type', 'operator' => '==', 'value' => 'math' ] ],
                ],
            ],

            // Answers Repeater (MCQ / Correct Answer)
            [
                'key'          => 'field_q_answers',
                'label'        => 'الإجابات',
                'name'         => 'answers',
                'type'         => 'repeater',
                'min'          => 2,
                'max'          => 6,
                'layout'       => 'block',
                'button_label' => 'أضف إجابة',
                'conditional_logic' => [
                    [ [ 'field' => 'field_q_type', 'operator' => '==', 'value' => 'mcq' ] ],
                    [ [ 'field' => 'field_q_type', 'operator' => '==', 'value' => 'correct' ] ],
                ],
                'sub_fields' => [
                    [
                        'key'   => 'field_q_ans_text',
                        'label' => 'نص الإجابة',
                        'name'  => 'answer_text',
                        'type'  => 'text',
                        'required' => 1,
                    ],
                    [
                        'key'   => 'field_q_ans_image',
                        'label' => 'صورة الإجابة (اختياري)',
                        'name'  => 'answer_image',
                        'type'  => 'image',
                        'return_format' => 'url',
                    ],
                    [
                        'key'     => 'field_q_ans_correct',
                        'label'   => 'إجابة صحيحة؟',
                        'name'    => 'is_correct',
                        'type'    => 'true_false',
                        'ui'      => 1,
                        'default_value' => 0,
                    ],
                ],
            ],

            // True/False Correct Answer
            [
                'key'     => 'field_q_tf_correct',
                'label'   => 'الإجابة الصحيحة',
                'name'    => 'tf_correct_answer',
                'type'    => 'select',
                'choices' => [ 'true' => 'صح ✓', 'false' => 'خطأ ✗' ],
                'conditional_logic' => [
                    [ [ 'field' => 'field_q_type', 'operator' => '==', 'value' => 'true_false' ] ],
                ],
            ],

            // Fill in Blank
            [
                'key'   => 'field_q_blank_answer',
                'label' => 'إجابة الفراغ',
                'name'  => 'blank_answer',
                'type'  => 'text',
                'instructions' => 'للفراغات المتعددة افصل بينها بـ | مثل: كلمة1|كلمة2',
                'conditional_logic' => [
                    [ [ 'field' => 'field_q_type', 'operator' => '==', 'value' => 'fill_blank' ] ],
                ],
            ],

            // Matching Pairs
            [
                'key'          => 'field_q_matching',
                'label'        => 'أزواج المطابقة',
                'name'         => 'matching_pairs',
                'type'         => 'repeater',
                'min'          => 2,
                'layout'       => 'table',
                'button_label' => 'أضف زوج',
                'conditional_logic' => [
                    [ [ 'field' => 'field_q_type', 'operator' => '==', 'value' => 'matching' ] ],
                ],
                'sub_fields' => [
                    [ 'key' => 'field_q_match_left',  'label' => 'يسار', 'name' => 'left',  'type' => 'text' ],
                    [ 'key' => 'field_q_match_right', 'label' => 'يمين', 'name' => 'right', 'type' => 'text' ],
                ],
            ],

            // Ordering Items
            [
                'key'          => 'field_q_ordering',
                'label'        => 'عناصر الترتيب (الترتيب الصحيح من الأعلى)',
                'name'         => 'ordering_items',
                'type'         => 'repeater',
                'min'          => 2,
                'layout'       => 'block',
                'button_label' => 'أضف عنصر',
                'conditional_logic' => [
                    [ [ 'field' => 'field_q_type', 'operator' => '==', 'value' => 'ordering' ] ],
                ],
                'sub_fields' => [
                    [ 'key' => 'field_q_order_item', 'label' => 'العنصر', 'name' => 'item', 'type' => 'text' ],
                ],
            ],

            // ─── Classification ────────────────────────────────────────────

            // Education System (post object)
            [
                'key'           => 'field_q_edu_system',
                'label'         => 'النظام التعليمي',
                'name'          => 'education_system',
                'type'          => 'post_object',
                'post_type'     => [ 'eh_education_system' ],
                'return_format' => 'id',
                'ui'            => 1,
                'required'      => 1,
            ],
            [
                'key'           => 'field_q_stage',
                'label'         => 'المرحلة',
                'name'          => 'stage',
                'type'          => 'post_object',
                'post_type'     => [ 'eh_stage' ],
                'return_format' => 'id',
                'ui'            => 1,
                'required'      => 1,
            ],
            [
                'key'           => 'field_q_grade',
                'label'         => 'الصف',
                'name'          => 'grade',
                'type'          => 'post_object',
                'post_type'     => [ 'eh_grade' ],
                'return_format' => 'id',
                'ui'            => 1,
                'required'      => 1,
            ],
            [
                'key'           => 'field_q_subject',
                'label'         => 'المادة',
                'name'          => 'subject',
                'type'          => 'post_object',
                'post_type'     => [ 'eh_subject' ],
                'return_format' => 'id',
                'ui'            => 1,
                'ajax'          => 1,
                'required'      => 1,
            ],
            [
                'key'           => 'field_q_lesson',
                'label'         => 'الدرس',
                'name'          => 'lesson',
                'type'          => 'post_object',
                'post_type'     => [ 'eh_lesson' ],
                'return_format' => 'id',
                'ui'            => 1,
                'ajax'          => 1,
            ],

            // Difficulty
            [
                'key'     => 'field_q_difficulty',
                'label'   => 'مستوى الصعوبة',
                'name'    => 'difficulty',
                'type'    => 'select',
                'choices' => [
                    'easy'   => 'سهل',
                    'medium' => 'متوسط',
                    'hard'   => 'صعب',
                ],
                'default_value' => 'medium',
            ],

            // Book Source
            [
                'key'     => 'field_q_book_source',
                'label'   => 'مصدر الكتاب',
                'name'    => 'book_source',
                'type'    => 'select',
                'choices' => [
                    'moasir'         => 'المعاصر',
                    'imtihan'        => 'الامتحان',
                    'selah_tilmeed'  => 'سلاح التلميذ',
                    'elimtihan'      => 'الامتحان (ثان)',
                    'ministry'       => 'منهج وزارة التربية والتعليم',
                    'other'          => 'أخرى',
                ],
                'allow_null' => 1,
            ],

            // Tags
            [
                'key'   => 'field_q_tags',
                'label' => 'الوسوم',
                'name'  => 'question_tags',
                'type'  => 'text',
                'instructions' => 'أدخل الوسوم مفصولة بفواصل',
            ],

            // Academic Year
            [
                'key'   => 'field_q_year',
                'label' => 'العام الدراسي',
                'name'  => 'academic_year',
                'type'  => 'text',
                'placeholder' => '2024/2025',
            ],

            // Points
            [
                'key'     => 'field_q_points',
                'label'   => 'درجات السؤال',
                'name'    => 'question_points',
                'type'    => 'number',
                'default_value' => 1,
                'min'     => 1,
                'max'     => 100,
            ],

            // Explanation
            [
                'key'   => 'field_q_explanation',
                'label' => 'الشرح / التوضيح',
                'name'  => 'explanation',
                'type'  => 'wysiwyg',
                'tabs'  => 'all',
                'toolbar' => 'basic',
                'media_upload' => 1,
            ],

            // AI Flags
            [
                'key'     => 'field_q_ai_generated',
                'label'   => 'تم توليده بالذكاء الاصطناعي؟',
                'name'    => 'ai_generated',
                'type'    => 'true_false',
                'ui'      => 1,
                'default_value' => 0,
            ],
            [
                'key'   => 'field_q_ai_model',
                'label' => 'نموذج الذكاء الاصطناعي المستخدم',
                'name'  => 'ai_model',
                'type'  => 'text',
                'conditional_logic' => [
                    [ [ 'field' => 'field_q_ai_generated', 'operator' => '==', 'value' => 1 ] ],
                ],
            ],

            // Duplicate flag
            [
                'key'     => 'field_q_duplicate',
                'label'   => 'سؤال مكرر؟',
                'name'    => 'is_duplicate',
                'type'    => 'true_false',
                'ui'      => 1,
                'default_value' => 0,
            ],
            [
                'key'           => 'field_q_duplicate_of',
                'label'         => 'مكرر من سؤال',
                'name'          => 'duplicate_of',
                'type'          => 'post_object',
                'post_type'     => [ 'eh_question' ],
                'return_format' => 'id',
                'conditional_logic' => [
                    [ [ 'field' => 'field_q_duplicate', 'operator' => '==', 'value' => 1 ] ],
                ],
            ],

            // Usage count (read-only, auto-updated)
            [
                'key'       => 'field_q_usage',
                'label'     => 'عدد الاستخدامات',
                'name'      => 'usage_count',
                'type'      => 'number',
                'default_value' => 0,
                'readonly'  => 1,
            ],
        ],
        'location' => [
            [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'eh_question' ] ],
        ],
        'menu_order'  => 0,
        'position'    => 'normal',
        'style'       => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
    ] );

    // ═══════════════════════════════════════════════════════════════════════
    // 2. EXAM FIELDS
    // ═══════════════════════════════════════════════════════════════════════
    acf_add_local_field_group( [
        'key'    => 'group_exam',
        'title'  => 'إعدادات الامتحان / Exam Settings',
        'fields' => [

            // Classification
            [
                'key' => 'field_ex_edu_system', 'label' => 'النظام التعليمي', 'name' => 'exam_education_system',
                'type' => 'post_object', 'post_type' => [ 'eh_education_system' ], 'return_format' => 'id', 'ui' => 1, 'required' => 1,
            ],
            [
                'key' => 'field_ex_stage', 'label' => 'المرحلة', 'name' => 'exam_stage',
                'type' => 'post_object', 'post_type' => [ 'eh_stage' ], 'return_format' => 'id', 'ui' => 1, 'required' => 1,
            ],
            [
                'key' => 'field_ex_grade', 'label' => 'الصف', 'name' => 'exam_grade',
                'type' => 'post_object', 'post_type' => [ 'eh_grade' ], 'return_format' => 'id', 'ui' => 1, 'required' => 1,
            ],
            [
                'key' => 'field_ex_subject', 'label' => 'المادة', 'name' => 'exam_subject',
                'type' => 'post_object', 'post_type' => [ 'eh_subject' ], 'return_format' => 'id', 'ui' => 1, 'ajax' => 1, 'required' => 1,
            ],
            [
                'key' => 'field_ex_lesson', 'label' => 'الدرس', 'name' => 'exam_lesson',
                'type' => 'post_object', 'post_type' => [ 'eh_lesson' ], 'return_format' => 'id', 'ui' => 1, 'ajax' => 1,
            ],

            // Academic year
            [ 'key' => 'field_ex_year', 'label' => 'العام الدراسي', 'name' => 'exam_academic_year', 'type' => 'text', 'placeholder' => '2024/2025' ],

            // Questions (relationship)
            [
                'key'           => 'field_ex_questions',
                'label'         => 'الأسئلة',
                'name'          => 'exam_questions',
                'type'          => 'relationship',
                'post_type'     => [ 'eh_question' ],
                'return_format' => 'id',
                'filters'       => [ 'search', 'post_type' ],
                'elements'      => [ 'featured_image' ],
                'min'           => 1,
            ],

            // Timer settings
            [
                'key'     => 'field_ex_timer_type',
                'label'   => 'نوع المؤقت',
                'name'    => 'timer_type',
                'type'    => 'select',
                'choices' => [ 'none' => 'بدون مؤقت', 'exam' => 'مؤقت للامتحان', 'per_question' => 'مؤقت لكل سؤال' ],
                'default_value' => 'exam',
            ],
            [
                'key'   => 'field_ex_duration',
                'label' => 'مدة الامتحان (بالدقائق)',
                'name'  => 'exam_duration_minutes',
                'type'  => 'number',
                'default_value' => 30,
                'min'   => 1,
                'conditional_logic' => [
                    [ [ 'field' => 'field_ex_timer_type', 'operator' => '==', 'value' => 'exam' ] ],
                ],
            ],
            [
                'key'   => 'field_ex_q_time',
                'label' => 'وقت كل سؤال (بالثواني)',
                'name'  => 'seconds_per_question',
                'type'  => 'number',
                'default_value' => 60,
                'min'   => 10,
                'conditional_logic' => [
                    [ [ 'field' => 'field_ex_timer_type', 'operator' => '==', 'value' => 'per_question' ] ],
                ],
            ],

            // Pass percentage
            [ 'key' => 'field_ex_pass_pct', 'label' => 'نسبة النجاح (%)', 'name' => 'pass_percentage', 'type' => 'number', 'default_value' => 50, 'min' => 0, 'max' => 100 ],

            // Options
            [ 'key' => 'field_ex_random_q', 'label' => 'عشوائية الأسئلة', 'name' => 'random_questions', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0 ],
            [ 'key' => 'field_ex_random_a', 'label' => 'عشوائية الإجابات', 'name' => 'random_answers', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0 ],
            [ 'key' => 'field_ex_show_exp', 'label' => 'إظهار الشرح بعد التسليم', 'name' => 'show_explanation', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_ex_allow_skip', 'label' => 'السماح بتخطي السؤال', 'name' => 'allow_skip', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_ex_allow_review', 'label' => 'وضع علامة للمراجعة', 'name' => 'allow_mark_review', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_ex_resume', 'label' => 'السماح باستكمال الامتحان', 'name' => 'allow_resume', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_ex_retakes', 'label' => 'عدد المحاولات المسموح بها (0 = غير محدود)', 'name' => 'max_attempts', 'type' => 'number', 'default_value' => 0, 'min' => 0 ],

            // Access control
            [
                'key'     => 'field_ex_access',
                'label'   => 'مستوى الوصول',
                'name'    => 'exam_access',
                'type'    => 'select',
                'choices' => [ 'free' => 'مجاني للجميع', 'free_limit' => 'محدود (يطبق حد الامتحانات اليومية)', 'subscribed' => 'للمشتركين فقط' ],
                'default_value' => 'free_limit',
            ],
            [
                'key'   => 'field_ex_free_plan_enabled',
                'label' => 'متاح للخطة المجانية',
                'name'  => 'exam_free_plan_enabled',
                'type'  => 'true_false',
                'ui'    => 1,
                'default_value' => 0,
                'instructions'  => 'عند التفعيل: يمكن للمستخدم المجاني دخول هذا الامتحان ضمن الحد اليومي.',
            ],

            // Difficulty & type
            [
                'key'     => 'field_ex_difficulty',
                'label'   => 'مستوى الصعوبة',
                'name'    => 'exam_difficulty',
                'type'    => 'select',
                'choices' => [ 'easy' => 'سهل', 'medium' => 'متوسط', 'hard' => 'صعب', 'mixed' => 'متنوع' ],
                'default_value' => 'mixed',
            ],
            [
                'key'     => 'field_ex_type',
                'label'   => 'نوع الامتحان',
                'name'    => 'exam_type',
                'type'    => 'select',
                'choices' => [ 'standard' => 'قياسي', 'daily' => 'تحدي يومي', 'ai_generated' => 'توليد ذكي', 'weak_area' => 'نقاط الضعف', 'mock' => 'امتحان تجريبي' ],
                'default_value' => 'standard',
            ],

            // XP reward
            [ 'key' => 'field_ex_xp', 'label' => 'نقاط XP للإنجاز', 'name' => 'exam_xp_reward', 'type' => 'number', 'default_value' => 50, 'min' => 0 ],
        ],
        'location' => [
            [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'eh_exam' ] ],
        ],
        'position' => 'normal',
    ] );

    // ═══════════════════════════════════════════════════════════════════════
    // 3. HIERARCHY FIELDS (Education System / Stage / Grade / Subject / Unit / Lesson)
    // ═══════════════════════════════════════════════════════════════════════
    acf_add_local_field_group( [
        'key'    => 'group_education_system',
        'title'  => 'بيانات النظام التعليمي',
        'fields' => [
            [ 'key' => 'field_es_name_ar', 'label' => 'الاسم بالعربي', 'name' => 'name_ar', 'type' => 'text', 'required' => 1 ],
            [ 'key' => 'field_es_code',    'label' => 'الكود',         'name' => 'system_code', 'type' => 'text' ],
            [ 'key' => 'field_es_active',  'label' => 'نشط',           'name' => 'is_active', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_es_order',   'label' => 'الترتيب',       'name' => 'display_order', 'type' => 'number', 'default_value' => 0 ],
            [ 'key' => 'field_es_color',   'label' => 'اللون',         'name' => 'system_color', 'type' => 'color_picker' ],
        ],
        'location' => [
            [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'eh_education_system' ] ],
        ],
    ] );

    acf_add_local_field_group( [
        'key'    => 'group_stage',
        'title'  => 'بيانات المرحلة',
        'fields' => [
            [ 'key' => 'field_st_edu', 'label' => 'النظام التعليمي', 'name' => 'stage_education_system', 'type' => 'post_object', 'post_type' => [ 'eh_education_system' ], 'return_format' => 'id', 'ui' => 1, 'required' => 1 ],
            [ 'key' => 'field_st_name_ar', 'label' => 'الاسم بالعربي', 'name' => 'stage_name_ar', 'type' => 'text', 'required' => 1 ],
            [ 'key' => 'field_st_order', 'label' => 'الترتيب', 'name' => 'stage_order', 'type' => 'number', 'default_value' => 0 ],
        ],
        'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'eh_stage' ] ] ],
    ] );

    acf_add_local_field_group( [
        'key'    => 'group_grade',
        'title'  => 'بيانات الصف',
        'fields' => [
            [ 'key' => 'field_gr_stage', 'label' => 'المرحلة', 'name' => 'grade_stage', 'type' => 'post_object', 'post_type' => [ 'eh_stage' ], 'return_format' => 'id', 'ui' => 1, 'required' => 1 ],
            [ 'key' => 'field_gr_edu', 'label' => 'النظام التعليمي', 'name' => 'grade_education_system', 'type' => 'post_object', 'post_type' => [ 'eh_education_system' ], 'return_format' => 'id', 'ui' => 1, 'required' => 1 ],
            [ 'key' => 'field_gr_name_ar', 'label' => 'الاسم بالعربي', 'name' => 'grade_name_ar', 'type' => 'text', 'required' => 1 ],
            [ 'key' => 'field_gr_number', 'label' => 'رقم الصف', 'name' => 'grade_number', 'type' => 'number', 'min' => 1, 'max' => 12 ],
        ],
        'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'eh_grade' ] ] ],
    ] );

    acf_add_local_field_group( [
        'key'    => 'group_subject',
        'title'  => 'بيانات المادة',
        'fields' => [
            [ 'key' => 'field_sub_grade', 'label' => 'الصف', 'name' => 'subject_grade', 'type' => 'post_object', 'post_type' => [ 'eh_grade' ], 'return_format' => 'id', 'ui' => 1, 'required' => 1 ],
            [ 'key' => 'field_sub_name_ar', 'label' => 'الاسم بالعربي', 'name' => 'subject_name_ar', 'type' => 'text', 'required' => 1 ],
            [ 'key' => 'field_sub_icon', 'label' => 'أيقونة', 'name' => 'subject_icon', 'type' => 'image', 'return_format' => 'url' ],
            [ 'key' => 'field_sub_color', 'label' => 'اللون', 'name' => 'subject_color', 'type' => 'color_picker' ],
            [ 'key' => 'field_sub_is_rtl', 'label' => 'كتابة من اليمين لليسار', 'name' => 'subject_rtl', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
        ],
        'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'eh_subject' ] ] ],
    ] );

    acf_add_local_field_group( [
        'key'    => 'group_unit',
        'title'  => 'بيانات الوحدة',
        'fields' => [
            [ 'key' => 'field_un_subject', 'label' => 'المادة', 'name' => 'unit_subject', 'type' => 'post_object', 'post_type' => [ 'eh_subject' ], 'return_format' => 'id', 'ui' => 1, 'required' => 1 ],
            [ 'key' => 'field_un_order', 'label' => 'الترتيب', 'name' => 'unit_order', 'type' => 'number', 'default_value' => 0 ],
        ],
        'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'eh_unit' ] ] ],
    ] );

    acf_add_local_field_group( [
        'key'    => 'group_lesson',
        'title'  => 'بيانات الدرس',
        'fields' => [
            [ 'key' => 'field_les_unit', 'label' => 'الوحدة', 'name' => 'lesson_unit', 'type' => 'post_object', 'post_type' => [ 'eh_unit' ], 'return_format' => 'id', 'ui' => 1, 'required' => 1 ],
            [ 'key' => 'field_les_subject', 'label' => 'المادة', 'name' => 'lesson_subject', 'type' => 'post_object', 'post_type' => [ 'eh_subject' ], 'return_format' => 'id', 'ui' => 1, 'required' => 1 ],
            [ 'key' => 'field_les_order', 'label' => 'الترتيب', 'name' => 'lesson_order', 'type' => 'number', 'default_value' => 0 ],
        ],
        'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'eh_lesson' ] ] ],
    ] );

    // ═══════════════════════════════════════════════════════════════════════
    // 4. RESULT FIELDS
    // ═══════════════════════════════════════════════════════════════════════
    acf_add_local_field_group( [
        'key'    => 'group_result',
        'title'  => 'بيانات النتيجة',
        'fields' => [
            [ 'key' => 'field_res_exam',       'label' => 'الامتحان',     'name' => 'result_exam_id',    'type' => 'number' ],
            [ 'key' => 'field_res_user',       'label' => 'المستخدم',    'name' => 'result_user_id',    'type' => 'number' ],
            [ 'key' => 'field_res_score',      'label' => 'الدرجة',      'name' => 'score',             'type' => 'number' ],
            [ 'key' => 'field_res_total',      'label' => 'إجمالي الدرجات', 'name' => 'total_points',   'type' => 'number' ],
            [ 'key' => 'field_res_pct',        'label' => 'النسبة %',     'name' => 'percentage',        'type' => 'number' ],
            [ 'key' => 'field_res_passed',     'label' => 'نجح؟',         'name' => 'passed',            'type' => 'true_false', 'ui' => 1 ],
            [ 'key' => 'field_res_time_taken', 'label' => 'الوقت المستغرق (ثواني)', 'name' => 'time_taken_seconds', 'type' => 'number' ],
            [ 'key' => 'field_res_answers',    'label' => 'الإجابات (JSON)', 'name' => 'answers_json', 'type' => 'textarea' ],
            [ 'key' => 'field_res_started_at', 'label' => 'بدأ في',       'name' => 'started_at',        'type' => 'date_time_picker' ],
            [ 'key' => 'field_res_submitted_at', 'label' => 'سُلَّم في',  'name' => 'submitted_at',      'type' => 'date_time_picker' ],
            [ 'key' => 'field_res_status',     'label' => 'الحالة',       'name' => 'result_status',     'type' => 'select',
              'choices' => [ 'in_progress' => 'جارٍ', 'submitted' => 'مُسلَّم', 'timed_out' => 'انتهى الوقت', 'abandoned' => 'منتهي' ] ],
            [ 'key' => 'field_res_xp',         'label' => 'XP مكتسبة',   'name' => 'xp_earned',         'type' => 'number', 'default_value' => 0 ],
            [ 'key' => 'field_res_attempt',    'label' => 'رقم المحاولة', 'name' => 'attempt_number',    'type' => 'number', 'default_value' => 1 ],
        ],
        'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'eh_result' ] ] ],
    ] );

    // ═══════════════════════════════════════════════════════════════════════
    // 5. SUBSCRIPTION FIELDS
    // ═══════════════════════════════════════════════════════════════════════
    acf_add_local_field_group( [
        'key'    => 'group_subscription',
        'title'  => 'بيانات الاشتراك',
        'fields' => [
            [ 'key' => 'field_sub_user_id',     'label' => 'المستخدم',        'name' => 'sub_user_id',     'type' => 'number' ],
            [ 'key' => 'field_sub_plan_name',   'label' => 'اسم الخطة',       'name' => 'plan_name',       'type' => 'text' ],
            [ 'key' => 'field_sub_plan_id',     'label' => 'معرف الخطة',       'name' => 'plan_id',         'type' => 'number' ],
            [ 'key' => 'field_sub_status',      'label' => 'الحالة',          'name' => 'sub_status',      'type' => 'select',
              'choices' => [ 'active' => 'نشط', 'expired' => 'منتهي', 'cancelled' => 'ملغي', 'pending' => 'معلق', 'trial' => 'تجريبي', 'lifetime' => 'مدى الحياة' ] ],
            [ 'key' => 'field_sub_start',       'label' => 'تاريخ البداية',   'name' => 'sub_start_date',  'type' => 'date_time_picker' ],
            [ 'key' => 'field_sub_end',         'label' => 'تاريخ الانتهاء',  'name' => 'sub_end_date',    'type' => 'date_time_picker' ],
            [ 'key' => 'field_sub_payment_id',  'label' => 'معرف الدفع',      'name' => 'sub_payment_id',  'type' => 'number' ],
            [ 'key' => 'field_sub_auto_renew',  'label' => 'تجديد تلقائي',    'name' => 'auto_renew',      'type' => 'true_false', 'ui' => 1 ],
            [ 'key' => 'field_sub_questions_used', 'label' => 'أسئلة مستخدمة اليوم', 'name' => 'daily_questions_used', 'type' => 'number', 'default_value' => 0 ],
            [ 'key' => 'field_sub_last_reset',  'label' => 'آخر تصفير يومي', 'name' => 'daily_reset_date', 'type' => 'date_picker' ],
        ],
        'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'eh_subscription' ] ] ],
    ] );

    // ═══════════════════════════════════════════════════════════════════════
    // 6. PAYMENT FIELDS
    // ═══════════════════════════════════════════════════════════════════════
    acf_add_local_field_group( [
        'key'    => 'group_payment',
        'title'  => 'بيانات الدفع',
        'fields' => [
            [ 'key' => 'field_pay_user',        'label' => 'المستخدم',       'name' => 'pay_user_id',      'type' => 'number' ],
            [ 'key' => 'field_pay_plan',        'label' => 'الخطة',          'name' => 'pay_plan_id',      'type' => 'number' ],
            [ 'key' => 'field_pay_amount',      'label' => 'المبلغ (جنيه)', 'name' => 'amount_egp',       'type' => 'number' ],
            [ 'key' => 'field_pay_method',      'label' => 'طريقة الدفع',    'name' => 'payment_method',   'type' => 'select',
              'choices' => [ 'fawaterk' => 'Fawaterk', 'vodafone_cash' => 'Vodafone Cash', 'bank_transfer' => 'حوالة بنكية', 'instapay' => 'InstaPay', 'wallet' => 'محفظة' ] ],
            [ 'key' => 'field_pay_status',      'label' => 'حالة الدفع',     'name' => 'payment_status',   'type' => 'select',
              'choices' => [ 'pending' => 'معلق', 'paid' => 'مدفوع', 'failed' => 'فشل', 'refunded' => 'مُسترد', 'cancelled' => 'ملغي', 'awaiting_review' => 'قيد المراجعة' ] ],
            [ 'key' => 'field_pay_transaction', 'label' => 'رقم المعاملة',   'name' => 'transaction_id',   'type' => 'text' ],
            [ 'key' => 'field_pay_invoice_url', 'label' => 'رابط الفاتورة',  'name' => 'invoice_url',      'type' => 'url' ],
            [ 'key' => 'field_pay_proof',       'label' => 'إثبات الدفع',    'name' => 'payment_proof',    'type' => 'file', 'return_format' => 'id' ],
            [ 'key' => 'field_pay_phone',       'label' => 'رقم الهاتف',     'name' => 'payer_phone',      'type' => 'text' ],
            [ 'key' => 'field_pay_notes',       'label' => 'ملاحظات إدارية', 'name' => 'admin_notes',      'type' => 'textarea' ],
            [ 'key' => 'field_pay_webhook',     'label' => 'بيانات Webhook',  'name' => 'webhook_payload',  'type' => 'textarea' ],
            [ 'key' => 'field_pay_tax',         'label' => 'الضريبة',         'name' => 'tax_amount',       'type' => 'number', 'default_value' => 0 ],
            [ 'key' => 'field_pay_fee',         'label' => 'رسوم المعالجة',   'name' => 'processing_fee',   'type' => 'number', 'default_value' => 0 ],
        ],
        'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'eh_payment' ] ] ],
    ] );

    // ═══════════════════════════════════════════════════════════════════════
    // 7. BADGE FIELDS
    // ═══════════════════════════════════════════════════════════════════════
    acf_add_local_field_group( [
        'key'    => 'group_badge',
        'title'  => 'بيانات الشارة',
        'fields' => [
            [ 'key' => 'field_bg_icon', 'label' => 'أيقونة الشارة', 'name' => 'badge_icon', 'type' => 'image', 'return_format' => 'url' ],
            [ 'key' => 'field_bg_trigger', 'label' => 'شرط المنح', 'name' => 'badge_trigger', 'type' => 'select',
              'choices' => [
                  'first_exam'     => 'أول امتحان',
                  'perfect_score'  => 'درجة مثالية',
                  'streak_3'       => 'سلسلة 3 أيام',
                  'streak_7'       => 'سلسلة 7 أيام',
                  'streak_30'      => 'سلسلة 30 يوم',
                  'top_leaderboard'=> 'الأول في المتصدرين',
                  'xp_milestone'   => 'إنجاز XP',
                  'exams_count'    => 'عدد امتحانات',
                  'custom'         => 'مخصص',
              ] ],
            [ 'key' => 'field_bg_threshold', 'label' => 'القيمة المطلوبة', 'name' => 'badge_threshold', 'type' => 'number', 'default_value' => 1 ],
            [ 'key' => 'field_bg_xp', 'label' => 'XP مكافأة', 'name' => 'badge_xp_reward', 'type' => 'number', 'default_value' => 0 ],
            [ 'key' => 'field_bg_rare', 'label' => 'شارة نادرة؟', 'name' => 'is_rare', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0 ],
        ],
        'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'eh_badge' ] ] ],
    ] );
}

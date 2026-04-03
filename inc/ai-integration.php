<?php
/**
 * ExamHub — DeepSeek AI Integration
 * PDF-to-questions, OCR cleanup, explanation generation,
 * difficulty classification, question rephrasing, AI tutor.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════════
// DEEPSEEK API CLIENT
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Make a request to the AI provider.
 *
 * @param string $system_prompt
 * @param string $user_message
 * @param array  $options
 * @return string|WP_Error  AI response text
 */
function examhub_ai_request( $system_prompt, $user_message, $options = [] ) {
    if ( ! get_field( 'ai_enabled', 'option' ) ) {
        return new WP_Error( 'ai_disabled', __( 'الذكاء الاصطناعي معطل.', 'examhub' ) );
    }

    $api_key     = get_field( 'ai_api_key', 'option' );
    $base_url    = rtrim( get_field( 'ai_base_url', 'option' ) ?: 'https://api.deepseek.com', '/' );
    $model       = get_field( 'ai_model', 'option' ) ?: 'deepseek-chat';
    $max_tokens  = (int) ( $options['max_tokens'] ?? get_field( 'ai_max_tokens', 'option' ) ?? 2000 );
    $temperature = (float) ( $options['temperature'] ?? get_field( 'ai_temperature', 'option' ) ?? 0.7 );

    if ( ! $api_key ) {
        return new WP_Error( 'no_api_key', __( 'لم يتم إعداد مفتاح API للذكاء الاصطناعي.', 'examhub' ) );
    }

    // Daily request limit check
    $daily_limit = (int) ( get_field( 'ai_daily_request_limit', 'option' ) ?: 1000 );
    $today_count = (int) get_transient( 'eh_ai_requests_today' );
    if ( $today_count >= $daily_limit ) {
        return new WP_Error( 'rate_limited', __( 'تجاوز النظام الحد اليومي لطلبات الذكاء الاصطناعي.', 'examhub' ) );
    }

    $payload = [
        'model'       => $model,
        'max_tokens'  => $max_tokens,
        'temperature' => $temperature,
        'messages'    => [
            [ 'role' => 'system', 'content' => $system_prompt ],
            [ 'role' => 'user',   'content' => $user_message  ],
        ],
    ];

    if ( ! empty( $options['json_mode'] ) ) {
        $payload['response_format'] = [ 'type' => 'json_object' ];
    }

    $response = wp_remote_post( $base_url . '/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body'    => json_encode( $payload ),
        'timeout' => 90,
    ] );

    if ( is_wp_error( $response ) ) {
        examhub_log( 'AI API error: ' . $response->get_error_message() );
        return $response;
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code !== 200 ) {
        $msg = $body['error']['message'] ?? "HTTP {$code}";
        return new WP_Error( 'ai_api_error', $msg );
    }

    // Increment counter
    set_transient( 'eh_ai_requests_today', $today_count + 1, DAY_IN_SECONDS );

    return $body['choices'][0]['message']['content'] ?? '';
}

// ═══════════════════════════════════════════════════════════════════════════════
// EXTRACT QUESTIONS FROM TEXT
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Extract MCQ questions from raw Arabic text (OCR output).
 *
 * @param string $raw_text
 * @param array  $context  grade, subject, lesson hints
 * @return array|WP_Error  Array of question data
 */
function examhub_ai_extract_questions( $raw_text, $context = [] ) {
    $context_str = '';
    if ( ! empty( $context['grade'] ) )   $context_str .= "الصف: {$context['grade']}\n";
    if ( ! empty( $context['subject'] ) ) $context_str .= "المادة: {$context['subject']}\n";
    if ( ! empty( $context['lesson'] ) )  $context_str .= "الدرس: {$context['lesson']}\n";

    $system = get_field( 'ai_system_prompt', 'option' )
        ?: 'أنت مساعد تعليمي متخصص في المناهج الدراسية المصرية. قدم إجابات دقيقة وواضحة باللغة العربية.';

    $system .= "\n\nمهمتك: استخراج الأسئلة من النص وتنسيقها كـ JSON.";

    $prompt = <<<PROMPT
استخرج جميع الأسئلة من النص أدناه وأعد تنسيقها.

{$context_str}

قواعد مهمة:
1. أعد الناتج فقط كـ JSON array بدون أي نص إضافي
2. كل عنصر يجب أن يحتوي على: question_text, type, answers (array), correct_answer_index, explanation, difficulty
3. أنواع الأسئلة: mcq (اختيار متعدد), true_false (صح/خطأ), fill_blank (اكمل الفراغ)
4. لأسئلة صح/خطأ: correct_answer يكون "true" أو "false"
5. درجة الصعوبة: easy / medium / hard
6. أصلح أخطاء OCR الواضحة

النص:
{$raw_text}

أعد JSON array فقط:
PROMPT;

    $response = examhub_ai_request( $system, $prompt, [ 'json_mode' => false, 'temperature' => 0.3, 'max_tokens' => 4000 ] );

    if ( is_wp_error( $response ) ) return $response;

    // Parse JSON from response
    $clean = preg_replace( '/^```(?:json)?|```$/m', '', trim( $response ) );
    $questions = json_decode( $clean, true );

    if ( ! is_array( $questions ) ) {
        // Try to extract JSON array from the response
        preg_match( '/\[.*\]/s', $response, $match );
        if ( $match ) {
            $questions = json_decode( $match[0], true );
        }
    }

    if ( ! is_array( $questions ) ) {
        return new WP_Error( 'parse_error', __( 'لم يتمكن الذكاء الاصطناعي من استخراج الأسئلة. تحقق من جودة النص.', 'examhub' ) );
    }

    $normalized = examhub_ai_normalize_questions( $questions );
    if ( empty( $normalized ) ) {
        return new WP_Error( 'parse_error', __( 'تعذر تجهيز بيانات الأسئلة من استجابة الذكاء الاصطناعي.', 'examhub' ) );
    }

    return $normalized;
}

/**
 * Normalize model output into stable question schema.
 *
 * @param array $questions
 * @return array
 */
function examhub_ai_normalize_questions( $questions ) {
    if ( ! is_array( $questions ) ) {
        return [];
    }

    // Some models return { questions: [...] } instead of a direct array.
    if ( isset( $questions['questions'] ) && is_array( $questions['questions'] ) ) {
        $questions = $questions['questions'];
    }

    $normalized = [];
    foreach ( $questions as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }

        $question_text = sanitize_textarea_field(
            $row['question_text'] ?? $row['question'] ?? $row['text'] ?? ''
        );
        if ( '' === $question_text ) {
            continue;
        }

        $type = examhub_ai_normalize_type(
            $row['type'] ?? $row['question_type'] ?? $row['kind'] ?? 'mcq'
        );
        $difficulty = examhub_ai_normalize_difficulty(
            $row['difficulty'] ?? $row['level'] ?? 'medium'
        );

        $answers = [];
        if ( isset( $row['answers'] ) && is_array( $row['answers'] ) ) {
            foreach ( $row['answers'] as $a ) {
                if ( is_array( $a ) ) {
                    $answer_text = sanitize_text_field( $a['answer_text'] ?? $a['text'] ?? '' );
                    if ( '' === $answer_text ) {
                        continue;
                    }
                    $answers[] = [
                        'answer_text' => $answer_text,
                        'is_correct'  => ! empty( $a['is_correct'] ),
                    ];
                } elseif ( is_string( $a ) ) {
                    $a = sanitize_text_field( $a );
                    if ( '' !== $a ) {
                        $answers[] = [ 'answer_text' => $a, 'is_correct' => false ];
                    }
                }
            }
        }

        $correct_index = $row['correct_answer_index'] ?? $row['correct_index'] ?? null;
        if ( is_numeric( $correct_index ) ) {
            $correct_index = (int) $correct_index;
            if ( $correct_index > 0 ) {
                $correct_index--; // accept 1-based indexes.
            }
            if ( isset( $answers[ $correct_index ] ) ) {
                foreach ( $answers as $i => $a ) {
                    $answers[ $i ]['is_correct'] = ( $i === $correct_index );
                }
            }
        }

        $correct_answer = $row['correct_answer'] ?? $row['answer'] ?? '';
        if ( 'true_false' === $type ) {
            $tf = strtolower( trim( (string) $correct_answer ) );
            if ( in_array( $tf, [ '1', 'true', 'صح', 'صحيح', 'yes' ], true ) ) {
                $correct_answer = 'true';
            } elseif ( in_array( $tf, [ '0', 'false', 'خطأ', 'خطا', 'wrong', 'no' ], true ) ) {
                $correct_answer = 'false';
            } else {
                $correct_answer = 'false';
            }
        }

        if ( 'fill_blank' === $type && '' === (string) $correct_answer ) {
            $correct_answer = sanitize_text_field( $row['blank_answer'] ?? $row['model_answer'] ?? '' );
        }

        $normalized[] = [
            'question_text'         => $question_text,
            'body'                  => wp_kses_post( (string) ( $row['body'] ?? $row['question_body'] ?? $row['content'] ?? $row['body_html'] ?? '' ) ),
            'type'                  => $type,
            'answers'               => $answers,
            'correct_answer_index'  => is_numeric( $correct_index ) ? (int) $correct_index : null,
            'correct_answer'        => (string) $correct_answer,
            'explanation'           => sanitize_textarea_field( $row['explanation'] ?? $row['hint'] ?? '' ),
            'difficulty'            => $difficulty,
        ];
    }

    return $normalized;
}

/**
 * Normalize AI type labels.
 *
 * @param string $type
 * @return string
 */
function examhub_ai_normalize_type( $type ) {
    $type = strtolower( trim( (string) $type ) );
    if ( in_array( $type, [ 'true_false', 'truefalse', 'tf', 'صح_خطأ', 'صح/خطأ', 'صح-خطأ' ], true ) ) {
        return 'true_false';
    }
    if ( in_array( $type, [ 'fill_blank', 'fill-in-the-blank', 'blank', 'اكمل', 'إكمال' ], true ) ) {
        return 'fill_blank';
    }
    return 'mcq';
}

/**
 * Normalize AI difficulty labels.
 *
 * @param string $difficulty
 * @return string
 */
function examhub_ai_normalize_difficulty( $difficulty ) {
    $difficulty = strtolower( trim( (string) $difficulty ) );
    if ( in_array( $difficulty, [ 'easy', 'سهل' ], true ) ) {
        return 'easy';
    }
    if ( in_array( $difficulty, [ 'hard', 'صعب' ], true ) ) {
        return 'hard';
    }
    return 'medium';
}

// ═══════════════════════════════════════════════════════════════════════════════
// GENERATE EXPLANATION
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Generate explanation for a question.
 *
 * @param int $q_id
 * @return string|WP_Error
 */
function examhub_ai_generate_explanation( $q_id ) {
    $text       = get_field( 'question_text', $q_id );
    $type       = get_field( 'question_type', $q_id );
    $answers    = get_field( 'answers', $q_id );
    $correct    = examhub_get_correct_answer_display( $q_id, $type );
    $subject    = get_the_title( (int) get_field( 'subject', $q_id ) );
    $difficulty = get_field( 'difficulty', $q_id );

    $answers_str = '';
    if ( is_array( $answers ) ) {
        foreach ( $answers as $i => $a ) {
            $marker = ! empty( $a['is_correct'] ) ? ' ✓' : '';
            $answers_str .= ( $i + 1 ) . '. ' . ( $a['answer_text'] ?? '' ) . $marker . "\n";
        }
    }

    $prompt = "السؤال: {$text}\n";
    if ( $answers_str ) $prompt .= "الإجابات:\n{$answers_str}";
    if ( is_string( $correct ) ) $prompt .= "\nالإجابة الصحيحة: {$correct}";
    $prompt .= "\nالمادة: {$subject}\n\n";
    $prompt .= "اكتب شرحاً تعليمياً واضحاً ومفيداً للإجابة الصحيحة بالعربية في 3-5 جمل. ركز على لماذا هذه الإجابة صحيحة.";

    $system = 'أنت معلم خبير في المناهج المصرية. اشرح الإجابات بأسلوب واضح ومشجع.';

    return examhub_ai_request( $system, $prompt, [ 'temperature' => 0.5 ] );
}

// ═══════════════════════════════════════════════════════════════════════════════
// GENERATE SIMILAR QUESTIONS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Generate similar questions to an existing one.
 *
 * @param int $q_id
 * @param int $count
 * @return array|WP_Error
 */
function examhub_ai_generate_similar( $q_id, $count = 3 ) {
    $text       = get_field( 'question_text', $q_id );
    $type       = get_field( 'question_type', $q_id );
    $answers    = get_field( 'answers', $q_id );
    $subject    = get_the_title( (int) get_field( 'subject', $q_id ) );
    $difficulty = get_field( 'difficulty', $q_id );

    $answers_str = '';
    if ( is_array( $answers ) ) {
        foreach ( $answers as $i => $a ) {
            $marker = ! empty( $a['is_correct'] ) ? ' (صحيح)' : '';
            $answers_str .= ( $i + 1 ) . '. ' . ( $a['answer_text'] ?? '' ) . $marker . "\n";
        }
    }

    $prompt = <<<PROMPT
أنشئ {$count} أسئلة مشابهة للسؤال أدناه، بنفس النوع ومستوى الصعوبة.

السؤال الأصلي: {$text}
الإجابات: {$answers_str}
المادة: {$subject}
الصعوبة: {$difficulty}
النوع: {$type}

أعد JSON array فقط. كل عنصر يحتوي:
- question_text: نص السؤال
- answers: مصفوفة من { answer_text, is_correct }
- explanation: شرح موجز
- difficulty: easy/medium/hard
PROMPT;

    $system = 'أنت خبير في إنشاء أسئلة تعليمية للمناهج المصرية. أنشئ أسئلة متنوعة وإجابات واضحة.';

    $response = examhub_ai_request( $system, $prompt, [ 'temperature' => 0.8, 'max_tokens' => 3000 ] );
    if ( is_wp_error( $response ) ) return $response;

    $clean     = preg_replace( '/^```(?:json)?|```$/m', '', trim( $response ) );
    $questions = json_decode( $clean, true );

    if ( ! is_array( $questions ) ) {
        preg_match( '/\[.*\]/s', $response, $match );
        if ( $match ) $questions = json_decode( $match[0], true );
    }

    return is_array( $questions ) ? $questions : new WP_Error( 'parse_error', 'فشل تحليل الاستجابة.' );
}

// ═══════════════════════════════════════════════════════════════════════════════
// AI TUTOR
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Get AI tutor response for a specific question.
 *
 * @param int    $q_id
 * @param string $user_question
 * @return string|WP_Error
 */
function examhub_ai_tutor_response( $q_id, $user_question = '' ) {
    $q_text      = get_field( 'question_text', $q_id );
    $q_type      = get_field( 'question_type', $q_id );
    $explanation = get_field( 'explanation', $q_id );
    $subject     = get_the_title( (int) get_field( 'subject', $q_id ) );
    $lesson      = get_the_title( (int) get_field( 'lesson', $q_id ) );

    $context = "السؤال: {$q_text}\n";
    if ( $explanation ) $context .= "الشرح المتاح: {$explanation}\n";
    if ( $subject )     $context .= "المادة: {$subject}\n";
    if ( $lesson )      $context .= "الدرس: {$lesson}\n";

    $message = $user_question
        ? "السياق:\n{$context}\n\nسؤال الطالب: {$user_question}"
        : "اشرح لي هذا السؤال بتفصيل:\n{$context}";

    $system = 'أنت مدرس خصوصي ذكي ومتحمس. أجب على أسئلة الطلاب بأسلوب واضح وبسيط. استخدم أمثلة ملموسة واشجع الطالب.';

    return examhub_ai_request( $system, $message, [ 'temperature' => 0.6 ] );
}

// ═══════════════════════════════════════════════════════════════════════════════
// CLASSIFY DIFFICULTY
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Auto-classify question difficulty.
 *
 * @param string $question_text
 * @param string $subject
 * @return string easy|medium|hard
 */
function examhub_ai_classify_difficulty( $question_text, $subject = '' ) {
    $prompt = "صنّف مستوى صعوبة السؤال التالي:\n";
    if ( $subject ) $prompt .= "المادة: {$subject}\n";
    $prompt .= "السؤال: {$question_text}\n\n";
    $prompt .= "أجب بكلمة واحدة فقط: easy أو medium أو hard";

    $system   = 'أنت خبير في تقييم مستوى أسئلة الامتحانات المصرية.';
    $response = examhub_ai_request( $system, $prompt, [ 'temperature' => 0.1, 'max_tokens' => 10 ] );

    if ( is_wp_error( $response ) ) return 'medium';

    $clean = strtolower( trim( $response ) );
    return in_array( $clean, [ 'easy', 'medium', 'hard' ] ) ? $clean : 'medium';
}

// ═══════════════════════════════════════════════════════════════════════════════
// GENERATE STUDY PLAN
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Generate a personalized study plan based on weak areas.
 *
 * @param int $user_id
 * @return string|WP_Error
 */
function examhub_ai_generate_study_plan( $user_id ) {
    $analytics  = examhub_get_user_analytics( $user_id );
    $weak_areas = $analytics['weak_subjects'] ?? [];

    if ( empty( $weak_areas ) ) {
        return __( 'أداؤك ممتاز في جميع المواد! استمر في المراجعة المنتظمة.', 'examhub' );
    }

    $weak_str = '';
    foreach ( $weak_areas as $area ) {
        $weak_str .= "- {$area['subject']}: نسبة الإجابات الصحيحة {$area['accuracy']}%\n";
    }

    $prompt = <<<PROMPT
أنشئ خطة دراسة شخصية أسبوعية لطالب بناءً على نقاط ضعفه:

نقاط الضعف:
{$weak_str}

الخطة يجب أن:
1. تكون واقعية ومنظمة
2. تركز على تحسين نقاط الضعف
3. تتضمن مراجعة وتدريبات عملية
4. تكون بالعربية وبأسلوب مشجع

أكتب الخطة بشكل نقاط واضحة للأيام 7.
PROMPT;

    $system = 'أنت مستشار تعليمي خبير. أنشئ خططاً دراسية فعالة وقابلة للتنفيذ.';

    return examhub_ai_request( $system, $prompt, [ 'temperature' => 0.7, 'max_tokens' => 1500 ] );
}

// ═══════════════════════════════════════════════════════════════════════════════
// AJAX HANDLERS FOR AI FEATURES
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_eh_ai_generate_explanation', 'examhub_ajax_ai_generate_explanation' );
function examhub_ajax_ai_generate_explanation() {
    check_ajax_referer( 'examhub_admin_ajax', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( [], 403 );

    $q_id = (int) ( $_POST['question_id'] ?? 0 );
    if ( ! $q_id ) wp_send_json_error();

    $explanation = examhub_ai_generate_explanation( $q_id );
    if ( is_wp_error( $explanation ) ) {
        wp_send_json_error( [ 'message' => $explanation->get_error_message() ] );
    }

    // Auto-save to question
    if ( ! empty( $_POST['save'] ) ) {
        update_field( 'explanation', wp_kses_post( $explanation ), $q_id );
    }

    wp_send_json_success( [ 'explanation' => $explanation ] );
}

add_action( 'wp_ajax_eh_ai_generate_similar', 'examhub_ajax_ai_generate_similar' );
function examhub_ajax_ai_generate_similar() {
    check_ajax_referer( 'examhub_admin_ajax', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( [], 403 );

    $q_id  = (int) ( $_POST['question_id'] ?? 0 );
    $count = min( 5, (int) ( $_POST['count'] ?? 3 ) );

    $questions = examhub_ai_generate_similar( $q_id, $count );
    if ( is_wp_error( $questions ) ) {
        wp_send_json_error( [ 'message' => $questions->get_error_message() ] );
    }

    wp_send_json_success( [ 'questions' => $questions ] );
}

add_action( 'wp_ajax_eh_ai_study_plan', 'examhub_ajax_ai_study_plan' );
function examhub_ajax_ai_study_plan() {
    check_ajax_referer( 'examhub_ajax', 'nonce' );
    $user_id = get_current_user_id();
    if ( ! $user_id ) wp_send_json_error();

    $sub = examhub_get_user_subscription_status( $user_id );
    if ( ! $sub['ai_access'] ) {
        wp_send_json_error( [ 'message' => 'خطة الدراسة الذكية متاحة للمشتركين المميزين.', 'upgrade' => true ] );
    }

    if ( ! examhub_rate_limit( "study_plan_{$user_id}", 3, 3600 ) ) {
        wp_send_json_error( [ 'message' => 'يمكنك طلب خطة دراسة 3 مرات فقط في الساعة.' ] );
    }

    $plan = examhub_ai_generate_study_plan( $user_id );
    if ( is_wp_error( $plan ) ) {
        wp_send_json_error( [ 'message' => $plan->get_error_message() ] );
    }

    wp_send_json_success( [ 'plan' => $plan ] );
}

/**
 * Upload PDF to AI OCR endpoint and return extracted text.
 *
 * @param string $pdf_path
 * @return string|WP_Error
 */
function examhub_ai_extract_pdf_text_remote( $pdf_path ) {
    if ( ! get_field( 'ai_enabled', 'option' ) ) {
        return new WP_Error( 'ai_disabled', __( 'الذكاء الاصطناعي معطل.', 'examhub' ) );
    }

    if ( ! get_field( 'ai_ocr_enabled', 'option' ) ) {
        return new WP_Error( 'ocr_disabled', __( 'OCR عبر API غير مفعل.', 'examhub' ) );
    }

    if ( ! file_exists( $pdf_path ) || ! is_readable( $pdf_path ) ) {
        return new WP_Error( 'file_missing', __( 'ملف PDF غير متاح للقراءة.', 'examhub' ) );
    }

    $api_key  = get_field( 'ai_api_key', 'option' );
    $base_url = rtrim( get_field( 'ai_base_url', 'option' ) ?: 'https://api.deepseek.com', '/' );
    $model    = get_field( 'ai_model', 'option' ) ?: 'deepseek-chat';
    $endpoint = trim( (string) get_field( 'ai_ocr_endpoint', 'option' ) );
    if ( '' === $endpoint ) {
        return new WP_Error(
            'ocr_endpoint_missing',
            __( 'OCR API endpoint غير مضبوط. أضف OCR Gateway endpoint في إعدادات AI أو فعّل OCR المحلي على السيرفر.', 'examhub' )
        );
    }

    if ( ! $api_key ) {
        return new WP_Error( 'no_api_key', __( 'لم يتم إعداد API Key.', 'examhub' ) );
    }

    $target_url = $endpoint;
    if ( ! preg_match( '#^https?://#i', $endpoint ) ) {
        if ( '/' !== substr( $endpoint, 0, 1 ) ) {
            $endpoint = '/' . $endpoint;
        }
        $target_url = $base_url . $endpoint;
    }

    $file_bytes = file_get_contents( $pdf_path );
    if ( false === $file_bytes || '' === $file_bytes ) {
        return new WP_Error( 'file_read', __( 'تعذر قراءة ملف PDF.', 'examhub' ) );
    }

    $boundary = '----examhub' . wp_generate_password( 16, false, false );
    $eol      = "\r\n";
    $fname    = basename( $pdf_path );

    $parts = '';
    $parts .= '--' . $boundary . $eol;
    $parts .= 'Content-Disposition: form-data; name="model"' . $eol . $eol;
    $parts .= $model . $eol;

    $parts .= '--' . $boundary . $eol;
    $parts .= 'Content-Disposition: form-data; name="language"' . $eol . $eol;
    $parts .= 'ar,en' . $eol;

    $parts .= '--' . $boundary . $eol;
    $parts .= 'Content-Disposition: form-data; name="file"; filename="' . $fname . '"' . $eol;
    $parts .= 'Content-Type: application/pdf' . $eol . $eol;

    $body = $parts . $file_bytes . $eol . '--' . $boundary . '--' . $eol;

    $response = wp_remote_post( $target_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
            'Accept'        => 'application/json',
        ],
        'body'    => $body,
        'timeout' => 180,
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code    = wp_remote_retrieve_response_code( $response );
    $raw     = wp_remote_retrieve_body( $response );
    $json    = json_decode( $raw, true );

    if ( $code < 200 || $code >= 300 ) {
        $msg = '';
        if ( is_array( $json ) ) {
            $msg = $json['error']['message'] ?? $json['message'] ?? '';
        }
        if ( '' === $msg ) {
            $msg = 'HTTP ' . $code;
        }
        return new WP_Error(
            'ocr_api_error',
            sprintf(
                __( 'فشل OCR API (%1$s): %2$s. تحقق من OCR endpoint (خطأ 404 يعني endpoint غير موجود).', 'examhub' ),
                $target_url,
                $msg
            )
        );
    }

    $text = '';
    if ( is_array( $json ) ) {
        $text = examhub_ai_extract_ocr_text_from_response( $json );
    }
    if ( '' === trim( $text ) ) {
        // As a fallback, use raw response for endpoints that return plain text.
        $text = is_string( $raw ) ? $raw : '';
    }

    $text = trim( (string) $text );
    if ( '' === $text ) {
        return new WP_Error( 'ocr_empty', __( 'OCR API returned empty text.', 'examhub' ) );
    }

    return $text;
}

/**
 * Parse OCR response payload from different provider shapes.
 *
 * @param array $json
 * @return string
 */
function examhub_ai_extract_ocr_text_from_response( $json ) {
    $candidates = [];

    if ( ! empty( $json['text'] ) ) {
        $candidates[] = $json['text'];
    }
    if ( ! empty( $json['content'] ) ) {
        $candidates[] = $json['content'];
    }
    if ( ! empty( $json['data']['text'] ) ) {
        $candidates[] = $json['data']['text'];
    }
    if ( ! empty( $json['result']['text'] ) ) {
        $candidates[] = $json['result']['text'];
    }
    if ( ! empty( $json['choices'][0]['message']['content'] ) ) {
        $candidates[] = $json['choices'][0]['message']['content'];
    }

    if ( ! empty( $json['pages'] ) && is_array( $json['pages'] ) ) {
        $pages = [];
        foreach ( $json['pages'] as $p ) {
            if ( is_array( $p ) && ! empty( $p['text'] ) ) {
                $pages[] = $p['text'];
            } elseif ( is_string( $p ) ) {
                $pages[] = $p;
            }
        }
        if ( ! empty( $pages ) ) {
            $candidates[] = implode( "\n", $pages );
        }
    }

    foreach ( $candidates as $value ) {
        if ( is_string( $value ) && '' !== trim( $value ) ) {
            return $value;
        }
    }

    return '';
}

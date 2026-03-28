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

    return $questions;
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

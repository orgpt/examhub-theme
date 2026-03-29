<?php
/**
 * ExamHub — ACF Options Page Field Groups
 * Global site settings: payment gateways, AI credentials,
 * subscription plans, gamification config.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', 'examhub_register_options_fields', 20 );

function examhub_register_options_fields() {

    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. GENERAL SETTINGS
    // ═══════════════════════════════════════════════════════════════════════
    acf_add_local_field_group( [
        'key'    => 'group_options_general',
        'title'  => 'الإعدادات العامة',
        'fields' => [
            [ 'key' => 'field_opt_site_name',   'label' => 'اسم المنصة',           'name' => 'site_display_name', 'type' => 'text', 'default_value' => 'ExamHub Pro' ],
            [ 'key' => 'field_opt_logo',         'label' => 'الشعار الرئيسي',       'name' => 'site_logo',         'type' => 'image', 'return_format' => 'url' ],
            [ 'key' => 'field_opt_logo_dark',    'label' => 'الشعار (وضع مظلم)',    'name' => 'site_logo_dark',    'type' => 'image', 'return_format' => 'url' ],
            [ 'key' => 'field_opt_free_limit',   'label' => 'الامتحانات المجانية/يوم', 'name' => 'free_exams_per_day', 'type' => 'number', 'default_value' => 1, 'min' => 1 ],
            [ 'key' => 'field_opt_maintenance',  'label' => 'وضع الصيانة',          'name' => 'maintenance_mode',  'type' => 'true_false', 'ui' => 1, 'default_value' => 0 ],
            [ 'key' => 'field_opt_default_lang', 'label' => 'اللغة الافتراضية',     'name' => 'default_language',  'type' => 'select',
              'choices' => [ 'ar' => 'العربية', 'en' => 'English' ], 'default_value' => 'ar' ],
        ],
        'location' => [ [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'examhub-settings' ] ] ],
    ] );

    // ═══════════════════════════════════════════════════════════════════════
    // 2. SUBSCRIPTION PLANS (repeater)
    // ═══════════════════════════════════════════════════════════════════════
    acf_add_local_field_group( [
        'key'    => 'group_options_plans',
        'title'  => 'خطط الاشتراك / Subscription Plans',
        'fields' => [
            [
                'key'          => 'field_plans_repeater',
                'label'        => 'الخطط',
                'name'         => 'subscription_plans',
                'type'         => 'repeater',
                'layout'       => 'block',
                'button_label' => 'أضف خطة',
                'sub_fields'   => [
                    [ 'key' => 'field_plan_id',            'label' => 'معرف الخطة (slug)',    'name' => 'plan_slug',         'type' => 'text',    'required' => 1 ],
                    [ 'key' => 'field_plan_name',          'label' => 'اسم الخطة',            'name' => 'plan_name',         'type' => 'text',    'required' => 1 ],
                    [ 'key' => 'field_plan_name_ar',       'label' => 'الاسم بالعربي',        'name' => 'plan_name_ar',      'type' => 'text',    'required' => 1 ],
                    [ 'key' => 'field_plan_price',         'label' => 'السعر (جنيه)',         'name' => 'plan_price',        'type' => 'number',  'min' => 0, 'required' => 1 ],
                    [ 'key' => 'field_plan_duration',      'label' => 'المدة (أيام)',          'name' => 'plan_duration_days','type' => 'number',  'min' => 1 ],
                    [ 'key' => 'field_plan_unlimited',     'label' => 'غير محدود',             'name' => 'plan_unlimited',    'type' => 'true_false', 'ui' => 1 ],
                    [ 'key' => 'field_plan_q_limit',       'label' => 'حد الامتحانات/يوم',       'name' => 'plan_exams_limit', 'type' => 'number', 'default_value' => 0 ],
                    [ 'key' => 'field_plan_ai',            'label' => 'الوصول للذكاء الاصطناعي', 'name' => 'plan_ai_access', 'type' => 'true_false', 'ui' => 1 ],
                    [ 'key' => 'field_plan_explanation',   'label' => 'الشرح التفصيلي',       'name' => 'plan_explanation_access', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
                    [ 'key' => 'field_plan_download',      'label' => 'تحميل PDF',             'name' => 'plan_download_access', 'type' => 'true_false', 'ui' => 1 ],
                    [ 'key' => 'field_plan_leaderboard',   'label' => 'المتصدرون',             'name' => 'plan_leaderboard_access', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
                    [ 'key' => 'field_plan_attempts',      'label' => 'عدد المحاولات (0=غير محدود)', 'name' => 'plan_attempts_limit', 'type' => 'number', 'default_value' => 0 ],
                    [ 'key' => 'field_plan_priority',      'label' => 'الأولوية (ترتيب العرض)', 'name' => 'plan_priority',  'type' => 'number', 'default_value' => 0 ],
                    [ 'key' => 'field_plan_featured',      'label' => 'مميز؟',                 'name' => 'plan_featured',     'type' => 'true_false', 'ui' => 1 ],
                    [ 'key' => 'field_plan_badge',         'label' => 'لون الشارة',            'name' => 'plan_badge_color',  'type' => 'color_picker' ],
                    [ 'key' => 'field_plan_description',   'label' => 'الوصف',                 'name' => 'plan_description',  'type' => 'textarea', 'rows' => 3 ],
                    [ 'key' => 'field_plan_features',      'label' => 'المزايا (سطر لكل ميزة)', 'name' => 'plan_features_list', 'type' => 'textarea', 'rows' => 5 ],
                    [ 'key' => 'field_plan_grade_restrict','label' => 'تقييد الصف (اتركه فارغاً للكل)', 'name' => 'plan_grade_restriction', 'type' => 'post_object', 'post_type' => ['eh_grade'], 'multiple' => 1, 'return_format' => 'id' ],
                    [ 'key' => 'field_plan_subject_restrict', 'label' => 'تقييد المادة', 'name' => 'plan_subject_restriction', 'type' => 'post_object', 'post_type' => ['eh_subject'], 'multiple' => 1, 'return_format' => 'id' ],
                    [ 'key' => 'field_plan_active',        'label' => 'مفعل',                  'name' => 'plan_active',       'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
                ],
            ],
        ],
        'location' => [ [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'acf-options-plans' ] ] ],
    ] );

    // ═══════════════════════════════════════════════════════════════════════
    // 3. PAYMENT SETTINGS
    // ═══════════════════════════════════════════════════════════════════════
    acf_add_local_field_group( [
        'key'    => 'group_options_payment',
        'title'  => 'إعدادات الدفع / Payment Settings',
        'fields' => [

            // Fawaterk
            [ 'key' => 'field_fw_enabled',     'label' => 'تفعيل Fawaterk',        'name' => 'fawaterk_enabled',     'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_fw_api_key',     'label' => 'Fawaterk API Key',       'name' => 'fawaterk_api_key',     'type' => 'password' ],
            [ 'key' => 'field_fw_secret',      'label' => 'Fawaterk Secret',        'name' => 'fawaterk_secret',      'type' => 'password' ],
            [ 'key' => 'field_fw_webhook_key', 'label' => 'Webhook Verification Key','name' => 'fawaterk_webhook_key', 'type' => 'password' ],
            [ 'key' => 'field_fw_test_mode',   'label' => 'وضع الاختبار',           'name' => 'fawaterk_test_mode',   'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],

            // Vodafone Cash
            [ 'key' => 'field_vc_enabled',     'label' => 'تفعيل Vodafone Cash',   'name' => 'vodafone_cash_enabled','type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_vc_number',      'label' => 'رقم Vodafone Cash',     'name' => 'vodafone_cash_number', 'type' => 'text', 'placeholder' => '010xxxxxxxx' ],
            [ 'key' => 'field_vc_name',        'label' => 'اسم صاحب الحساب',       'name' => 'vodafone_cash_name',   'type' => 'text' ],
            [ 'key' => 'field_vc_instructions','label' => 'تعليمات الدفع',          'name' => 'vodafone_cash_instructions', 'type' => 'wysiwyg' ],

            // Manual Methods
            [ 'key' => 'field_manual_enabled', 'label' => 'تفعيل الدفع اليدوي',   'name' => 'manual_payment_enabled','type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_bank_name',      'label' => 'اسم البنك',              'name' => 'bank_name',            'type' => 'text' ],
            [ 'key' => 'field_bank_account',   'label' => 'رقم الحساب / IBAN',     'name' => 'bank_account',         'type' => 'text' ],
            [ 'key' => 'field_instapay_id',    'label' => 'InstaPay Username/@',    'name' => 'instapay_username',    'type' => 'text' ],
            [ 'key' => 'field_wallet_number',  'label' => 'رقم المحفظة',           'name' => 'wallet_number',        'type' => 'text' ],

            // Global payment config
            [ 'key' => 'field_tax_pct',        'label' => 'نسبة الضريبة (%)',       'name' => 'tax_percentage',       'type' => 'number', 'default_value' => 0, 'min' => 0, 'max' => 100 ],
            [ 'key' => 'field_proc_fee',       'label' => 'رسوم المعالجة (%)',      'name' => 'processing_fee_pct',   'type' => 'number', 'default_value' => 0 ],
            [ 'key' => 'field_invoice_expiry', 'label' => 'صلاحية الفاتورة (ساعات)', 'name' => 'invoice_expiry_hours', 'type' => 'number', 'default_value' => 24 ],
            [ 'key' => 'field_auto_cancel',    'label' => 'إلغاء تلقائي للطلبات المعلقة', 'name' => 'auto_cancel_pending', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_currency',       'label' => 'العملة',                  'name' => 'payment_currency',     'type' => 'select',
              'choices' => [ 'EGP' => 'جنيه مصري (EGP)', 'USD' => 'دولار (USD)', 'SAR' => 'ريال (SAR)' ], 'default_value' => 'EGP' ],
            [ 'key' => 'field_notify_email',   'label' => 'إيميل الإشعارات',         'name' => 'payment_notify_email', 'type' => 'email' ],
        ],
        'location' => [
            [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'acf-options-payment-settings' ] ],
            [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'acf-options-payments' ] ],
        ],
    ] );

    // ═══════════════════════════════════════════════════════════════════════
    // 4. AI / DEEPSEEK SETTINGS
    // ═══════════════════════════════════════════════════════════════════════
    acf_add_local_field_group( [
        'key'    => 'group_options_ai',
        'title'  => 'إعدادات الذكاء الاصطناعي / AI Settings',
        'fields' => [
            [ 'key' => 'field_ai_enabled',       'label' => 'تفعيل الذكاء الاصطناعي', 'name' => 'ai_enabled',          'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_ai_provider',      'label' => 'مزود الذكاء الاصطناعي',   'name' => 'ai_provider',         'type' => 'select',
              'choices' => [ 'deepseek' => 'DeepSeek', 'openai' => 'OpenAI', 'gemini' => 'Google Gemini' ], 'default_value' => 'deepseek' ],
            [ 'key' => 'field_ai_api_key',       'label' => 'API Key',                   'name' => 'ai_api_key',          'type' => 'password' ],
            [ 'key' => 'field_ai_base_url',      'label' => 'Base URL',                  'name' => 'ai_base_url',         'type' => 'url', 'default_value' => 'https://api.deepseek.com' ],
            [ 'key' => 'field_ai_model',         'label' => 'النموذج',                   'name' => 'ai_model',            'type' => 'text', 'default_value' => 'deepseek-chat' ],
            [ 'key' => 'field_ai_max_tokens',    'label' => 'حد الرموز',                 'name' => 'ai_max_tokens',       'type' => 'number', 'default_value' => 2000 ],
            [ 'key' => 'field_ai_temperature',   'label' => 'درجة الإبداع (0-2)',         'name' => 'ai_temperature',      'type' => 'number', 'default_value' => 0.7, 'step' => '0.1' ],
            [ 'key' => 'field_ai_ocr_enabled',   'label' => 'تفعيل OCR العربي',          'name' => 'ai_ocr_enabled',      'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_ai_auto_explain',  'label' => 'توليد الشرح تلقائياً',      'name' => 'ai_auto_explain',     'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_ai_daily_limit',   'label' => 'حد الطلبات اليومية',         'name' => 'ai_daily_request_limit', 'type' => 'number', 'default_value' => 1000 ],
            [ 'key' => 'field_ai_system_prompt', 'label' => 'System Prompt الافتراضي',    'name' => 'ai_system_prompt',    'type' => 'textarea', 'rows' => 5,
              'default_value' => 'أنت مساعد تعليمي متخصص في المناهج الدراسية المصرية. قدم إجابات دقيقة وواضحة باللغة العربية.' ],
        ],
        'location' => [ [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'acf-options-ai-deepseek' ] ] ],
    ] );

    // ═══════════════════════════════════════════════════════════════════════
    // 5. GAMIFICATION SETTINGS
    // ═══════════════════════════════════════════════════════════════════════
    acf_add_local_field_group( [
        'key'    => 'group_options_gamification',
        'title'  => 'إعدادات الألعاب / Gamification Settings',
        'fields' => [
            [ 'key' => 'field_gam_enabled',      'label' => 'تفعيل الألعاب',              'name' => 'gamification_enabled',     'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_gam_xp_exam',      'label' => 'XP لإتمام امتحان',           'name' => 'xp_per_exam',              'type' => 'number', 'default_value' => 20 ],
            [ 'key' => 'field_gam_xp_correct',   'label' => 'XP لكل إجابة صحيحة',        'name' => 'xp_per_correct_answer',    'type' => 'number', 'default_value' => 2 ],
            [ 'key' => 'field_gam_xp_perfect',   'label' => 'XP لدرجة مثالية',           'name' => 'xp_perfect_score',         'type' => 'number', 'default_value' => 50 ],
            [ 'key' => 'field_gam_xp_daily',     'label' => 'XP مكافأة يومية',            'name' => 'xp_daily_reward',          'type' => 'number', 'default_value' => 10 ],
            [ 'key' => 'field_gam_streak_bonus',  'label' => 'XP مكافأة السلسلة/يوم',    'name' => 'xp_streak_bonus_per_day',  'type' => 'number', 'default_value' => 5 ],
            [ 'key' => 'field_gam_levels',        'label' => 'جدول المستويات (XP لكل مستوى)', 'name' => 'levels_xp_table',    'type' => 'textarea', 'rows' => 5,
              'instructions' => 'سطر لكل مستوى بصيغة: اسم_المستوى|XP_المطلوب مثال: مبتدئ|0', 'default_value' => "مبتدئ|0\nمتوسط|500\nمتقدم|2000\nخبير|5000\nأسطورة|15000" ],
            [ 'key' => 'field_gam_daily_challenge', 'label' => 'تفعيل التحدي اليومي',    'name' => 'daily_challenge_enabled',  'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_gam_daily_q_count', 'label' => 'أسئلة التحدي اليومي',       'name' => 'daily_challenge_questions','type' => 'number', 'default_value' => 10 ],
            [ 'key' => 'field_gam_leaderboard',   'label' => 'تفعيل المتصدرين',            'name' => 'leaderboard_enabled',      'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_gam_lb_top',        'label' => 'عدد المتصدرين المعروضين',   'name' => 'leaderboard_top_count',    'type' => 'number', 'default_value' => 50 ],
        ],
        'location' => [
            [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'acf-options-gamification-settings' ] ],
            [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'acf-options-gamification' ] ],
        ],
    ] );
}

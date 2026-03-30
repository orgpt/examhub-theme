<?php
/**
 * ExamHub - Email templates and notifications.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

function examhub_get_mail_from_name() {
    $name = function_exists( 'get_field' ) ? (string) get_field( 'email_from_name', 'option' ) : '';
    return $name ? $name : get_bloginfo( 'name' );
}

function examhub_get_mail_from_address() {
    $email = function_exists( 'get_field' ) ? (string) get_field( 'email_from_address', 'option' ) : '';
    return is_email( $email ) ? $email : get_option( 'admin_email' );
}

function examhub_render_email_template( $args ) {
    $defaults = [
        'preheader'   => '',
        'heading'     => '',
        'intro'       => '',
        'body'        => '',
        'cta_label'   => '',
        'cta_url'     => '',
        'footer_note' => __( 'هذه رسالة آلية من منصة المراجعة النهائية.', 'examhub' ),
    ];

    $args = wp_parse_args( $args, $defaults );

    ob_start();
    ?>
    <div style="background:#f5f7fb;padding:24px 12px;font-family:Cairo,Tajawal,Arial,sans-serif;direction:rtl;text-align:right;color:#172033;">
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;"><?php echo esc_html( $args['preheader'] ); ?></div>
        <div style="max-width:680px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #e4e8f2;">
            <div style="background:linear-gradient(135deg,#10234c 0%,#1f4db8 100%);padding:28px 32px;color:#ffffff;">
                <div style="font-size:13px;letter-spacing:.08em;opacity:.75;margin-bottom:8px;"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
                <h1 style="margin:0;font-size:28px;line-height:1.4;"><?php echo esc_html( $args['heading'] ); ?></h1>
                <?php if ( $args['intro'] ) : ?>
                    <p style="margin:12px 0 0;font-size:16px;line-height:1.9;color:rgba(255,255,255,.9);"><?php echo nl2br( esc_html( $args['intro'] ) ); ?></p>
                <?php endif; ?>
            </div>
            <div style="padding:32px;">
                <div style="font-size:15px;line-height:2;color:#33415c;"><?php echo wp_kses_post( wpautop( $args['body'] ) ); ?></div>
                <?php if ( $args['cta_label'] && $args['cta_url'] ) : ?>
                    <div style="margin-top:28px;">
                        <a href="<?php echo esc_url( $args['cta_url'] ); ?>" style="display:inline-block;background:#1f4db8;color:#ffffff;text-decoration:none;padding:14px 22px;border-radius:14px;font-weight:700;"><?php echo esc_html( $args['cta_label'] ); ?></a>
                    </div>
                <?php endif; ?>
            </div>
            <div style="padding:18px 32px;background:#f8faff;border-top:1px solid #e4e8f2;font-size:13px;color:#6b7894;line-height:1.8;">
                <?php echo esc_html( $args['footer_note'] ); ?><br>
                <a href="<?php echo esc_url( home_url() ); ?>" style="color:#1f4db8;text-decoration:none;"><?php echo esc_html( home_url() ); ?></a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function examhub_send_email( $to, $subject, $template_args ) {
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        sprintf( 'From: %s <%s>', examhub_get_mail_from_name(), examhub_get_mail_from_address() ),
    ];

    return wp_mail( $to, $subject, examhub_render_email_template( $template_args ), $headers );
}

function examhub_get_user_email_preferences( $user_id ) {
    $defaults = [
        'order_created'   => 1,
        'purchase_success'=> 1,
        'daily_digest'    => 1,
        'product_updates' => 1,
        'affiliate'       => 1,
    ];

    $saved = get_user_meta( $user_id, 'eh_email_preferences', true );
    if ( ! is_array( $saved ) ) {
        $saved = [];
    }

    return array_merge( $defaults, $saved );
}

function examhub_user_allows_email( $user_id, $key ) {
    $prefs = examhub_get_user_email_preferences( $user_id );
    return ! empty( $prefs[ $key ] );
}

function examhub_send_affiliate_invite_email( $invite_id ) {
    $invite = get_post( $invite_id );
    if ( ! $invite ) {
        return false;
    }

    $user = get_userdata( $invite->post_author );
    if ( ! $user ) {
        return false;
    }

    $email         = (string) get_post_meta( $invite_id, '_eh_invited_email', true );
    $affiliate_url = (string) get_post_meta( $invite_id, '_eh_affiliate_url', true );

    return examhub_send_email(
        $email,
        sprintf( __( 'دعوة خاصة من %s للانضمام إلى %s', 'examhub' ), $user->display_name, get_bloginfo( 'name' ) ),
        [
            'preheader' => __( 'ابدأ تدريبك الآن عبر دعوة خاصة.', 'examhub' ),
            'heading'   => sprintf( __( '%s رشح لك منصة %s', 'examhub' ), $user->display_name, get_bloginfo( 'name' ) ),
            'intro'     => __( 'تمت دعوتك لتجربة منصة تدريب الامتحانات والانضمام بخطوات بسيطة.', 'examhub' ),
            'body'      => sprintf(
                '%s<br><br>%s<br><br><strong>%s</strong>',
                esc_html__( 'هتلاقي امتحانات تفاعلية، تصحيح فوري، تتبع أداء يومي، وخطط مراجعة تساعدك تذاكر بشكل أذكى.', 'examhub' ),
                esc_html__( 'بمجرد فتح الرابط وإنشاء حساب، هيتم ربط الدعوة تلقائيًا بحسابك.', 'examhub' ),
                esc_html__( 'الدعوة صالحة الآن ويمكنك البدء فورًا.', 'examhub' )
            ),
            'cta_label' => __( 'افتح الدعوة الآن', 'examhub' ),
            'cta_url'   => $affiliate_url,
        ]
    );
}

function examhub_send_affiliate_sale_email( $affiliate_user_id, $buyer_id, $amount, $commission ) {
    if ( ! examhub_user_allows_email( $affiliate_user_id, 'affiliate' ) ) {
        return false;
    }

    $user  = get_userdata( $affiliate_user_id );
    $buyer = get_userdata( $buyer_id );
    if ( ! $user ) {
        return false;
    }

    return examhub_send_email(
        $user->user_email,
        __( 'تم تسجيل عمولة أفلييت جديدة', 'examhub' ),
        [
            'preheader' => __( 'أضفنا عمولة جديدة إلى حسابك.', 'examhub' ),
            'heading'   => __( 'مبروك، لديك عمولة جديدة', 'examhub' ),
            'intro'     => sprintf( __( 'تم اعتماد عملية شراء جديدة عن طريق رابطك بنسبة %s%%.', 'examhub' ), number_format_i18n( examhub_get_affiliate_rate(), 0 ) ),
            'body'      => sprintf(
                '%s <strong>%s</strong><br>%s <strong>%s جنيه</strong><br>%s <strong>%s جنيه</strong>',
                esc_html__( 'العضو:', 'examhub' ),
                esc_html( $buyer ? $buyer->display_name : '#' . $buyer_id ),
                esc_html__( 'قيمة الشراء:', 'examhub' ),
                esc_html( number_format_i18n( $amount, 2 ) ),
                esc_html__( 'العمولة:', 'examhub' ),
                esc_html( number_format_i18n( $commission, 2 ) )
            ),
            'cta_label' => __( 'عرض صفحة الأفلييت', 'examhub' ),
            'cta_url'   => home_url( '/profile/?tab=affiliate' ),
        ]
    );
}

function examhub_send_payment_created_email( $payment_id, $data = [] ) {
    $user_id = isset( $data['user_id'] ) ? (int) $data['user_id'] : (int) get_field( 'pay_user_id', $payment_id );
    if ( ! $user_id || ! examhub_user_allows_email( $user_id, 'order_created' ) ) {
        return;
    }

    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return;
    }

    $plan_id = $data['plan_id'] ?? get_field( 'pay_plan_id', $payment_id );
    $plan    = function_exists( 'examhub_get_plan_by_id' ) ? examhub_get_plan_by_id( $plan_id ) : null;
    $amount  = isset( $data['amount'] ) ? (float) $data['amount'] : (float) get_field( 'amount_egp', $payment_id );
    $method  = $data['method'] ?? get_field( 'payment_method', $payment_id );

    examhub_send_email(
        $user->user_email,
        __( 'تم استلام طلبك بنجاح', 'examhub' ),
        [
            'preheader' => __( 'استلمنا طلب الاشتراك الخاص بك.', 'examhub' ),
            'heading'   => __( 'تم تسجيل طلبك', 'examhub' ),
            'intro'     => __( 'بدأنا تجهيز طلبك الآن، وستصلك رسالة أخرى فور تأكيد عملية الدفع.', 'examhub' ),
            'body'      => sprintf(
                '%s <strong>#%d</strong><br>%s <strong>%s</strong><br>%s <strong>%s جنيه</strong><br>%s <strong>%s</strong>',
                esc_html__( 'رقم الطلب:', 'examhub' ),
                $payment_id,
                esc_html__( 'الخطة:', 'examhub' ),
                esc_html( $plan['plan_name_ar'] ?? $plan['plan_name'] ?? $plan_id ),
                esc_html__( 'المبلغ:', 'examhub' ),
                esc_html( number_format_i18n( $amount, 2 ) ),
                esc_html__( 'طريقة الدفع:', 'examhub' ),
                esc_html( $method )
            ),
            'cta_label' => __( 'متابعة الاشتراك', 'examhub' ),
            'cta_url'   => home_url( '/subscription/' ),
        ]
    );

    $notify_email = function_exists( 'get_field' ) ? get_field( 'payment_notify_email', 'option' ) : '';
    $notify_email = is_email( $notify_email ) ? $notify_email : get_option( 'admin_email' );

    examhub_send_email(
        $notify_email,
        __( 'طلب شراء جديد على الموقع', 'examhub' ),
        [
            'heading' => __( 'طلب شراء جديد', 'examhub' ),
            'intro'   => __( 'تم إنشاء طلب جديد على الموقع ويحتاج للمتابعة.', 'examhub' ),
            'body'    => sprintf(
                '%s <strong>#%d</strong><br>%s <strong>%s</strong><br>%s <strong>%s جنيه</strong>',
                esc_html__( 'رقم الطلب:', 'examhub' ),
                $payment_id,
                esc_html__( 'العضو:', 'examhub' ),
                esc_html( $user->display_name . ' - ' . $user->user_email ),
                esc_html__( 'المبلغ:', 'examhub' ),
                esc_html( number_format_i18n( $amount, 2 ) )
            ),
        ]
    );
}
add_action( 'examhub_payment_created', 'examhub_send_payment_created_email', 10, 2 );

function examhub_send_payment_paid_email( $payment_id, $user_id, $plan_id ) {
    if ( ! $user_id || ! examhub_user_allows_email( $user_id, 'purchase_success' ) ) {
        return;
    }

    $user = get_userdata( $user_id );
    $plan = function_exists( 'examhub_get_plan_by_id' ) ? examhub_get_plan_by_id( $plan_id ) : null;
    if ( ! $user ) {
        return;
    }

    examhub_send_email(
        $user->user_email,
        __( 'تم تأكيد عملية الشراء وتفعيل اشتراكك', 'examhub' ),
        [
            'preheader' => __( 'اشتراكك أصبح فعالًا الآن.', 'examhub' ),
            'heading'   => __( 'تم تأكيد الدفع بنجاح', 'examhub' ),
            'intro'     => __( 'اشتراكك أصبح مفعلًا ويمكنك الاستفادة من المزايا فورًا.', 'examhub' ),
            'body'      => sprintf(
                '%s <strong>#%d</strong><br>%s <strong>%s</strong><br>%s',
                esc_html__( 'رقم العملية:', 'examhub' ),
                $payment_id,
                esc_html__( 'الخطة:', 'examhub' ),
                esc_html( $plan['plan_name_ar'] ?? $plan['plan_name'] ?? $plan_id ),
                esc_html__( 'يمكنك الآن الدخول إلى لوحة التحكم وبدء التدريب مباشرة.', 'examhub' )
            ),
            'cta_label' => __( 'اذهب إلى لوحة التحكم', 'examhub' ),
            'cta_url'   => home_url( '/dashboard/' ),
        ]
    );
}
add_action( 'examhub_payment_paid', 'examhub_send_payment_paid_email', 20, 3 );

function examhub_build_daily_digest_for_user( $user_id ) {
    $grade_id = (int) get_user_meta( $user_id, 'eh_default_grade', true );

    $exam_args = [
        'post_type'      => 'eh_exam',
        'posts_per_page' => 5,
        'post_status'    => 'publish',
        'date_query'     => [
            [
                'after' => gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - DAY_IN_SECONDS ),
            ],
        ],
    ];

    if ( $grade_id ) {
        $exam_args['meta_query'] = [
            [
                'key'   => 'exam_grade',
                'value' => $grade_id,
            ],
        ];
    }

    $new_exams = get_posts( $exam_args );
    $recommended = get_posts(
        [
            'post_type'      => 'eh_exam',
            'posts_per_page' => 3,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => $grade_id ? [ [ 'key' => 'exam_grade', 'value' => $grade_id ] ] : [],
        ]
    );

    $updates = function_exists( 'get_field' ) ? (string) get_field( 'daily_digest_updates', 'option' ) : '';
    $plan    = [];

    foreach ( $recommended as $exam ) {
        $plan[] = sprintf( '• %s', $exam->post_title );
    }

    $new_exam_lines = [];
    foreach ( $new_exams as $exam ) {
        $new_exam_lines[] = sprintf( '• %s', $exam->post_title );
    }

    return [
        'new_exams'   => $new_exam_lines,
        'daily_plan'  => $plan,
        'updates'     => $updates,
    ];
}

function examhub_send_daily_digest_emails() {
    $enabled = function_exists( 'get_field' ) ? get_field( 'daily_digest_enabled', 'option' ) : 1;
    if ( ! $enabled ) {
        return;
    }

    $today = current_time( 'Y-m-d' );
    $users = get_users( [ 'role__in' => [ 'subscriber', 'student', 'administrator' ] ] );

    foreach ( $users as $user ) {
        if ( ! examhub_user_allows_email( $user->ID, 'daily_digest' ) ) {
            continue;
        }

        if ( get_user_meta( $user->ID, 'eh_daily_digest_sent_on', true ) === $today ) {
            continue;
        }

        $digest = examhub_build_daily_digest_for_user( $user->ID );
        $parts  = [];

        if ( $digest['new_exams'] ) {
            $parts[] = '<strong>' . esc_html__( 'امتحانات جديدة اليوم', 'examhub' ) . '</strong><br>' . implode( '<br>', array_map( 'esc_html', $digest['new_exams'] ) );
        }

        if ( $digest['daily_plan'] ) {
            $parts[] = '<strong>' . esc_html__( 'مخططك اليومي المقترح', 'examhub' ) . '</strong><br>' . implode( '<br>', array_map( 'esc_html', $digest['daily_plan'] ) );
        }

        if ( $digest['updates'] && examhub_user_allows_email( $user->ID, 'product_updates' ) ) {
            $parts[] = '<strong>' . esc_html__( 'تحسينات وتحديثات', 'examhub' ) . '</strong><br>' . nl2br( esc_html( $digest['updates'] ) );
        }

        if ( empty( $parts ) ) {
            continue;
        }

        examhub_send_email(
            $user->user_email,
            __( 'ملخصك اليومي من المنصة', 'examhub' ),
            [
                'preheader' => __( 'أحدث الامتحانات والمخطط اليومي في رسالة واحدة.', 'examhub' ),
                'heading'   => __( 'ملخصك اليومي جاهز', 'examhub' ),
                'intro'     => __( 'جهزنا لك أهم ما تحتاجه اليوم لتكمل مذاكرتك بشكل منظم.', 'examhub' ),
                'body'      => implode( '<br><br>', $parts ),
                'cta_label' => __( 'ابدأ من لوحة التحكم', 'examhub' ),
                'cta_url'   => home_url( '/dashboard/' ),
            ]
        );

        update_user_meta( $user->ID, 'eh_daily_digest_sent_on', $today );
    }
}
add_action( 'examhub_daily_cron', 'examhub_send_daily_digest_emails', 20 );

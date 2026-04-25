<?php
/**
 * ExamHub — Helpers
 * Shared utility functions used across all modules.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read a plan slug from post meta, with fallback to raw meta values.
 *
 * Some older field definitions stored plan slugs in ACF number fields, so we
 * fall back to the underlying post meta when ACF returns an empty value.
 *
 * @param int    $post_id
 * @param string $meta_key
 * @return string
 */
function examhub_get_plan_slug_from_meta( $post_id, $meta_key ) {
    $value = get_field( $meta_key, $post_id );

    if ( is_scalar( $value ) ) {
        $value = sanitize_text_field( (string) $value );
        if ( '' !== $value ) {
            return $value;
        }
    }

    $raw_value = get_post_meta( $post_id, $meta_key, true );
    if ( is_scalar( $raw_value ) ) {
        return sanitize_text_field( (string) $raw_value );
    }

    return '';
}

// ─── Subscription Status ──────────────────────────────────────────────────────

/**
 * Get user subscription status.
 * Returns array: state, plan_name, plan_id, expires_at, days_left.
 *
 * @param int $user_id
 * @return array
 */
function examhub_normalize_question_body( $body ) {
    if ( is_string( $body ) ) {
        return wp_kses_post( $body );
    }

    if ( ! is_array( $body ) ) {
        return '';
    }

    $html = '';

    foreach ( $body as $block ) {
        if ( is_string( $block ) ) {
            $text = trim( $block );
            if ( '' !== $text ) {
                $html .= '<p>' . esc_html( $text ) . '</p>';
            }
            continue;
        }

        if ( ! is_array( $block ) ) {
            continue;
        }

        $kind  = strtolower( trim( (string) ( $block['kind'] ?? 'text' ) ) );
        $value = trim( (string) ( $block['value'] ?? $block['text'] ?? '' ) );

        if ( '' === $value ) {
            continue;
        }

        switch ( $kind ) {
            case 'latex':
            case 'math':
                $html .= '<div dir="ltr">\\[' . esc_html( $value ) . '\\]</div>';
                break;

            case 'html':
                $html .= wp_kses_post( $value );
                break;

            case 'text':
            default:
                $html .= '<p>' . esc_html( $value ) . '</p>';
                break;
        }
    }

    return $html;
}

function examhub_get_user_subscription_status( $user_id = 0 ) {
    if ( ! $user_id ) $user_id = get_current_user_id();

    $default = [
        'state'        => 'free',
        'plan_name'    => __( 'مجاني', 'examhub' ),
        'plan_id'      => null,
        'expires_at'   => null,
        'days_left'    => 0,
        'unlimited'    => false,
        'ai_access'    => false,
        'explanation_access' => true,
        'download_access'    => false,
        'leaderboard_access' => true,
        'exams_limit'        => (int) ( get_field( 'free_exams_per_day', 'option' ) ?: get_field( 'free_questions_per_day', 'option' ) ?: 1 ),
        'questions_limit'    => (int) ( get_field( 'free_exams_per_day', 'option' ) ?: get_field( 'free_questions_per_day', 'option' ) ?: 1 ),
        'attempts_limit'     => 0,
    ];

    if ( ! $user_id ) return $default;

    // Check lifetime
    $lifetime = false;
    if ( $lifetime ) {
        return array_merge( $default, [
            'state'     => 'lifetime',
            'plan_name' => __( 'مدى الحياة', 'examhub' ),
            'unlimited' => true,
            'ai_access' => true,
            'explanation_access' => true,
            'download_access'    => true,
            'exams_limit'        => 9999,
            'questions_limit'    => 9999,
        ] );
    }

    // Get latest active subscription
    $subs = get_posts( [
        'post_type'      => 'eh_subscription',
        'author'         => $user_id,
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'post_status'    => 'publish',
        'meta_query'     => [
            [
                'key'     => 'sub_status',
                'value'   => [ 'active', 'trial', 'lifetime' ],
                'compare' => 'IN',
            ],
        ],
    ] );

    if ( empty( $subs ) ) {
        if ( function_exists( 'examhub_restore_subscription_from_latest_payment' ) ) {
            $restored = examhub_restore_subscription_from_latest_payment( $user_id );
            if ( $restored && ! is_wp_error( $restored ) ) {
                return examhub_get_user_subscription_status( $user_id );
            }
        }

        delete_user_meta( $user_id, 'eh_active_sub_id' );
        delete_user_meta( $user_id, 'eh_active_plan_id' );
        delete_user_meta( $user_id, 'eh_sub_expires' );
        delete_user_meta( $user_id, 'eh_lifetime' );
        return $default;
    }

    $sub        = $subs[0];
    $status     = get_field( 'sub_status', $sub->ID );
    $end_date   = get_field( 'sub_end_date', $sub->ID );
    $payment_id = (int) get_field( 'sub_payment_id', $sub->ID );
    $plan_id    = examhub_get_plan_slug_from_meta( $sub->ID, 'plan_id' );

    if ( '' === $plan_id && $payment_id ) {
        $plan_id = examhub_get_plan_slug_from_meta( $payment_id, 'pay_plan_id' );
    }

    if ( $payment_id && 'refunded' === get_field( 'payment_status', $payment_id ) ) {
        update_field( 'sub_status', 'cancelled', $sub->ID );
        delete_user_meta( $user_id, 'eh_active_sub_id' );
        delete_user_meta( $user_id, 'eh_active_plan_id' );
        delete_user_meta( $user_id, 'eh_sub_expires' );
        delete_user_meta( $user_id, 'eh_lifetime' );
        return $default;
    }

    if ( $end_date ) {
        $end_ts   = strtotime( $end_date );
        $days_left = ceil( ( $end_ts - time() ) / DAY_IN_SECONDS );

        if ( $days_left < 0 ) {
            // Expired — update status
            wp_update_post( [ 'ID' => $sub->ID ] );
            update_field( 'sub_status', 'expired', $sub->ID );
            delete_user_meta( $user_id, 'eh_active_sub_id' );
            delete_user_meta( $user_id, 'eh_active_plan_id' );
            delete_user_meta( $user_id, 'eh_sub_expires' );
            delete_user_meta( $user_id, 'eh_lifetime' );
            return $default;
        }
    } else {
        $days_left = 9999; // Unlimited
    }

    // Get plan details from options
    $plan = examhub_get_plan_by_id( $plan_id ) ?: [];

    update_user_meta( $user_id, 'eh_active_sub_id', $sub->ID );
    update_user_meta( $user_id, 'eh_active_plan_id', $plan_id );
    update_user_meta( $user_id, 'eh_sub_expires', $end_date ?: 'lifetime' );

    if ( 'lifetime' === $status && ! $end_date ) {
        update_user_meta( $user_id, 'eh_lifetime', 1 );
    } else {
        delete_user_meta( $user_id, 'eh_lifetime' );
    }

    return [
        'state'              => $status,
        'plan_name'          => $plan['plan_name_ar'] ?? get_field( 'plan_name', $sub->ID ),
        'plan_id'            => $plan_id,
        'subscription_id'    => $sub->ID,
        'expires_at'         => $end_date,
        'days_left'          => $days_left,
        'unlimited'          => (bool) ( $plan['plan_unlimited'] ?? false ),
        'ai_access'          => (bool) ( $plan['plan_ai_access'] ?? false ),
        'explanation_access' => (bool) ( $plan['plan_explanation_access'] ?? true ),
        'download_access'    => (bool) ( $plan['plan_download_access'] ?? false ),
        'leaderboard_access' => (bool) ( $plan['plan_leaderboard_access'] ?? true ),
        'exams_limit'        => (int) ( $plan['plan_exams_limit'] ?? $plan['plan_questions_limit'] ?? 9999 ),
        'questions_limit'    => (int) ( $plan['plan_exams_limit'] ?? $plan['plan_questions_limit'] ?? 9999 ),
        'attempts_limit'     => (int) ( $plan['plan_attempts_limit'] ?? 0 ),
    ];
}

/**
 * Get plan config by plan ID (slug).
 *
 * @param string $plan_id
 * @return array|null
 */
function examhub_get_plan_by_id( $plan_id ) {
    $plans = get_field( 'subscription_plans', 'option' );
    if ( ! is_array( $plans ) ) return null;
    foreach ( $plans as $plan ) {
        if ( ( $plan['plan_slug'] ?? '' ) === $plan_id ) {
            return $plan;
        }
    }
    return null;
}

/**
 * Get all active plans.
 */
function examhub_get_all_plans() {
    $plans = get_field( 'subscription_plans', 'option' );
    if ( ! is_array( $plans ) ) return [];
    return array_filter( $plans, fn( $p ) => ! empty( $p['plan_active'] ) );
}

/**
 * Get the visual offer prices for a subscription plan.
 *
 * The active checkout price remains plan_price. The regular price can be
 * supplied later via plan_regular_price/plan_before_discount_price, otherwise
 * a rounded marketing price is derived for the limited-time offer display.
 */
function examhub_get_plan_offer_prices( $plan ) {
    $current = (float) ( $plan['plan_price'] ?? 0 );
    $regular = (float) (
        $plan['plan_regular_price']
        ?? $plan['plan_before_discount_price']
        ?? $plan['plan_old_price']
        ?? 0
    );

    if ( $current > 0 && $regular <= $current ) {
        $regular = max( $current + 1, round( ( $current * 1.5 ) / 10 ) * 10 );
    }

    $discount = ( $regular > $current && $regular > 0 )
        ? max( 1, round( ( ( $regular - $current ) / $regular ) * 100 ) )
        : 0;

    return [
        'current'  => $current,
        'regular'  => $regular,
        'discount' => $discount,
    ];
}

// ─── Daily Exam Limit ────────────────────────────────────────────────────────

/**
 * Check if user can start an exam (based on daily plan limit).
 *
 * @param int $user_id
 * @return bool
 */
function examhub_user_can_access_question( $user_id = 0 ) {
    if ( ! $user_id ) $user_id = get_current_user_id();
    if ( ! $user_id ) return false;

    $sub = examhub_get_user_subscription_status( $user_id );

    if ( in_array( $sub['state'], [ 'active', 'trial', 'lifetime' ], true ) && $sub['unlimited'] ) {
        return true;
    }

    // Reset daily counter if needed.
    $last_reset = get_user_meta( $user_id, 'eh_daily_reset', true );
    $today      = date( 'Y-m-d' );

    if ( $last_reset !== $today ) {
        update_user_meta( $user_id, 'eh_daily_exams', 0 );
        update_user_meta( $user_id, 'eh_daily_reset', $today );
    }

    $used  = (int) get_user_meta( $user_id, 'eh_daily_exams', true );
    $limit = (int) ( $sub['exams_limit'] ?? 0 );
    if ( $limit <= 0 ) {
        $limit = (int) ( get_field( 'free_exams_per_day', 'option' ) ?: get_field( 'free_questions_per_day', 'option' ) ?: 1 );
    }

    return $used < $limit;
}

/**
 * Increment daily exam count for user.
 *
 * @param int $user_id
 * @return int New count
 */
function examhub_increment_question_count( $user_id = 0, $amount = 1 ) {
    if ( ! $user_id ) $user_id = get_current_user_id();
    $current = (int) get_user_meta( $user_id, 'eh_daily_exams', true );
    $new_val = max( 0, $current + (int) $amount );
    update_user_meta( $user_id, 'eh_daily_exams', $new_val );
    return $new_val;
}

/**
 * Get remaining exams for today.
 */
function examhub_get_remaining_questions( $user_id = 0 ) {
    if ( ! $user_id ) $user_id = get_current_user_id();
    $sub   = examhub_get_user_subscription_status( $user_id );
    if ( $sub['unlimited'] ) return 9999;
    $last_reset = get_user_meta( $user_id, 'eh_daily_reset', true );
    $today      = date( 'Y-m-d' );
    if ( $last_reset !== $today ) {
        update_user_meta( $user_id, 'eh_daily_exams', 0 );
        update_user_meta( $user_id, 'eh_daily_reset', $today );
    }
    $used  = (int) get_user_meta( $user_id, 'eh_daily_exams', true );
    $limit = (int) ( $sub['exams_limit'] ?? 0 );
    if ( $limit <= 0 ) {
        $limit = (int) ( get_field( 'free_exams_per_day', 'option' ) ?: get_field( 'free_questions_per_day', 'option' ) ?: 1 );
    }
    return max( 0, $limit - $used );
}

/**
 * Alias helpers with exam terminology.
 */
function examhub_user_can_start_exam( $user_id = 0 ) {
    return examhub_user_can_access_question( $user_id );
}

function examhub_increment_exam_count( $user_id = 0, $amount = 1 ) {
    return examhub_increment_question_count( $user_id, $amount );
}

function examhub_get_remaining_exams( $user_id = 0 ) {
    return examhub_get_remaining_questions( $user_id );
}

// ─── Gamification ────────────────────────────────────────────────────────────

/**
 * Get user level based on XP.
 *
 * @param int $xp
 * @return array name, current_level_xp, next_level_xp, progress_pct
 */
function examhub_get_user_level( $xp ) {
    $levels_raw = get_field( 'levels_xp_table', 'option' );
    $levels = [];

    if ( $levels_raw ) {
        foreach ( explode( "\n", trim( $levels_raw ) ) as $line ) {
            $parts = explode( '|', trim( $line ) );
            if ( count( $parts ) === 2 ) {
                $levels[] = [ 'name' => trim( $parts[0] ), 'xp' => (int) trim( $parts[1] ) ];
            }
        }
    }

    if ( empty( $levels ) ) {
        $levels = [
            [ 'name' => 'مبتدئ',   'xp' => 0 ],
            [ 'name' => 'متوسط',   'xp' => 500 ],
            [ 'name' => 'متقدم',   'xp' => 2000 ],
            [ 'name' => 'خبير',    'xp' => 5000 ],
            [ 'name' => 'أسطورة',  'xp' => 15000 ],
        ];
    }

    $current_level  = $levels[0];
    $next_level     = null;

    foreach ( $levels as $i => $level ) {
        if ( $xp >= $level['xp'] ) {
            $current_level = $level;
            $next_level    = $levels[ $i + 1 ] ?? null;
        }
    }

    $progress = 0;
    if ( $next_level ) {
        $range    = $next_level['xp'] - $current_level['xp'];
        $earned   = $xp - $current_level['xp'];
        $progress = $range > 0 ? min( 100, round( $earned / $range * 100 ) ) : 100;
    } else {
        $progress = 100;
    }

    return [
        'name'             => $current_level['name'],
        'current_level_xp' => $current_level['xp'],
        'next_level_xp'    => $next_level ? $next_level['xp'] : null,
        'next_level_name'  => $next_level ? $next_level['name'] : null,
        'progress_pct'     => $progress,
    ];
}

/**
 * Add XP to user.
 *
 * @param int    $user_id
 * @param int    $amount
 * @param string $reason
 * @return int New total XP
 */
function examhub_add_xp( $user_id, $amount, $reason = '' ) {
    $current = (int) get_user_meta( $user_id, 'eh_xp', true );
    $new_xp  = $current + $amount;
    update_user_meta( $user_id, 'eh_xp', $new_xp );

    // Log XP transaction
    $log = get_user_meta( $user_id, 'eh_xp_log', true );
    if ( ! is_array( $log ) ) $log = [];
    $log[] = [
        'amount'    => $amount,
        'reason'    => $reason,
        'timestamp' => current_time( 'timestamp' ),
        'total'     => $new_xp,
    ];
    // Keep last 50 entries
    if ( count( $log ) > 50 ) $log = array_slice( $log, -50 );
    update_user_meta( $user_id, 'eh_xp_log', $log );

    do_action( 'examhub_xp_added', $user_id, $amount, $new_xp, $reason );

    return $new_xp;
}

// ─── Template Helpers ─────────────────────────────────────────────────────────

/**
 * Add stable heading anchors to article content and return table-of-contents items.
 *
 * @param string $content Filtered post content.
 * @return array
 */
function examhub_prepare_article_toc( $content ) {
    if ( ! is_string( $content ) || false === stripos( $content, '<h' ) ) {
        return [
            'content' => (string) $content,
            'items'   => [],
        ];
    }

    $items = [];
    $used  = [];

    $content = preg_replace_callback(
        '/<h([2-4])\b([^>]*)>(.*?)<\/h\1>/is',
        static function ( $matches ) use ( &$items, &$used ) {
            $level        = (int) $matches[1];
            $attributes   = (string) $matches[2];
            $heading_html = (string) $matches[3];
            $title        = trim( wp_strip_all_tags( $heading_html ) );

            if ( '' === $title ) {
                return $matches[0];
            }

            $id = '';
            if ( preg_match( '/\sid=(["\'])(.*?)\1/i', $attributes, $id_match ) ) {
                $id = sanitize_title( $id_match[2] );
            }

            if ( '' === $id || isset( $used[ $id ] ) ) {
                $id = sanitize_title( $title );
                if ( '' === $id ) {
                    $id = 'article-section';
                }
            }

            $base   = $id;
            $suffix = 2;
            while ( isset( $used[ $id ] ) ) {
                $id = $base . '-' . $suffix;
                $suffix++;
            }
            $used[ $id ] = true;

            $items[] = [
                'level' => $level,
                'id'    => $id,
                'title' => $title,
            ];

            $attributes = preg_replace( '/\sid=(["\']).*?\1/i', '', $attributes );

            return '<h' . $level . $attributes . ' id="' . esc_attr( $id ) . '">' . $heading_html . '</h' . $level . '>';
        },
        $content
    );

    return [
        'content' => $content,
        'items'   => $items,
    ];
}

/**
 * Get difficulty label in Arabic.
 */
function examhub_difficulty_label( $difficulty ) {
    return [
        'easy'   => __( 'سهل', 'examhub' ),
        'medium' => __( 'متوسط', 'examhub' ),
        'hard'   => __( 'صعب', 'examhub' ),
        'mixed'  => __( 'متنوع', 'examhub' ),
    ][ $difficulty ] ?? $difficulty;
}

/**
 * Format duration in minutes to readable string.
 */
function examhub_format_duration( $minutes ) {
    if ( $minutes < 60 ) {
        return sprintf( __( '%d دقيقة', 'examhub' ), $minutes );
    }
    $h = floor( $minutes / 60 );
    $m = $minutes % 60;
    return $m > 0
        ? sprintf( __( '%d ساعة و%d دقيقة', 'examhub' ), $h, $m )
        : sprintf( __( '%d ساعة', 'examhub' ), $h );
}

/**
 * Get question count for an exam post.
 */
function examhub_get_exam_question_count( $exam_id ) {
    $questions = get_field( 'exam_questions', $exam_id );
    return is_array( $questions ) ? count( $questions ) : 0;
}

/**
 * Check if user has taken an exam before.
 */
function examhub_user_has_taken_exam( $exam_id, $user_id = 0 ) {
    if ( ! $user_id ) $user_id = get_current_user_id();
    if ( ! $user_id ) return false;

    $results = get_posts( [
        'post_type'      => 'eh_result',
        'author'         => $user_id,
        'posts_per_page' => 1,
        'meta_query'     => [
            [ 'key' => 'result_exam_id', 'value' => $exam_id ],
            [ 'key' => 'result_status', 'value' => 'submitted' ],
        ],
    ] );

    return ! empty( $results );
}

/**
 * Get user's best result for an exam.
 */
function examhub_get_best_result( $exam_id, $user_id = 0 ) {
    if ( ! $user_id ) $user_id = get_current_user_id();
    if ( ! $user_id ) return null;

    global $wpdb;
    $result_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm1 ON pm1.post_id = p.ID AND pm1.meta_key = 'result_exam_id' AND pm1.meta_value = %s
        INNER JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = p.ID AND pm2.meta_key = 'result_status' AND pm2.meta_value = 'submitted'
        WHERE p.post_type = 'eh_result' AND p.post_author = %d",
        $exam_id, $user_id
    ) );

    if ( empty( $result_ids ) ) return null;

    $best = null;
    $best_pct = -1;

    foreach ( $result_ids as $rid ) {
        $pct = (float) get_field( 'percentage', $rid );
        if ( $pct > $best_pct ) {
            $best_pct = $pct;
            $best     = $rid;
        }
    }

    return $best;
}

/**
 * Get paginated exams with filtering.
 *
 * @param array $args
 * @return WP_Query
 */
function examhub_get_exams_query( $args = [] ) {
    $defaults = [
        'education_system' => 0,
        'stage'            => 0,
        'grade'            => 0,
        'subject'          => 0,
        'year'             => '',
        'difficulty'       => '',
        'paged'            => 1,
        'per_page'         => 12,
        'orderby'          => 'date',
        'order'            => 'DESC',
    ];

    $args = wp_parse_args( $args, $defaults );

    $meta_query = [ 'relation' => 'AND' ];

    if ( $args['education_system'] ) {
        $meta_query[] = [ 'key' => 'exam_education_system', 'value' => $args['education_system'] ];
    }
    if ( $args['stage'] ) {
        $meta_query[] = [ 'key' => 'exam_stage', 'value' => $args['stage'] ];
    }
    if ( $args['grade'] ) {
        $meta_query[] = [ 'key' => 'exam_grade', 'value' => $args['grade'] ];
    }
    if ( $args['subject'] ) {
        $meta_query[] = [ 'key' => 'exam_subject', 'value' => $args['subject'] ];
    }
    if ( $args['difficulty'] ) {
        $meta_query[] = [ 'key' => 'exam_difficulty', 'value' => $args['difficulty'] ];
    }

    return new WP_Query( [
        'post_type'      => 'eh_exam',
        'post_status'    => 'publish',
        'paged'          => $args['paged'],
        'posts_per_page' => $args['per_page'],
        'orderby'        => $args['orderby'],
        'order'          => $args['order'],
        'meta_query'     => count( $meta_query ) > 1 ? $meta_query : [],
    ] );
}

/**
 * Create a subscription post programmatically.
 *
 * @param array $data
 * @return int|WP_Error Post ID
 */
function examhub_create_subscription( $data ) {
    $plan     = examhub_get_plan_by_id( $data['plan_id'] );
    $duration = $plan ? (int) $plan['plan_duration_days'] : 30;

    $post_id = wp_insert_post( [
        'post_type'   => 'eh_subscription',
        'post_title'  => sprintf( 'اشتراك #%d - %s', $data['user_id'], current_time( 'Y-m-d' ) ),
        'post_status' => 'publish',
        'post_author' => $data['user_id'],
    ] );

    if ( is_wp_error( $post_id ) ) return $post_id;

    update_field( 'sub_user_id',    $data['user_id'],          $post_id );
    update_field( 'plan_name',      $plan['plan_name'] ?? '',  $post_id );
    update_field( 'plan_id',        $data['plan_id'],           $post_id );
    update_field( 'sub_status',     $data['status'] ?? 'active', $post_id );
    update_field( 'sub_start_date', current_time( 'Y-m-d H:i:s' ), $post_id );
    update_field( 'sub_payment_id', $data['payment_id'] ?? 0, $post_id );

    if ( $duration > 0 ) {
        $end = date( 'Y-m-d H:i:s', strtotime( "+{$duration} days" ) );
        update_field( 'sub_end_date', $end, $post_id );
    }

    do_action( 'examhub_subscription_created', $post_id, $data );

    return $post_id;
}

/**
 * Create a payment record.
 *
 * @param array $data
 * @return int|WP_Error
 */
function examhub_create_payment( $data ) {
    $post_id = wp_insert_post( [
        'post_type'   => 'eh_payment',
        'post_title'  => sprintf( 'دفعة #%s', uniqid() ),
        'post_status' => 'publish',
        'post_author' => $data['user_id'],
    ] );

    if ( is_wp_error( $post_id ) ) return $post_id;

    $fields = [
        'pay_user_id', 'pay_plan_id', 'amount_egp', 'payment_method',
        'payment_status', 'transaction_id', 'invoice_url', 'payer_phone',
        'admin_notes', 'webhook_payload', 'tax_amount', 'processing_fee',
    ];

    foreach ( $fields as $field ) {
        if ( isset( $data[ $field ] ) ) {
            update_field( $field, $data[ $field ], $post_id );
        }
    }

    do_action( 'examhub_payment_created', $post_id, $data );

    return $post_id;
}

/**
 * Sanitize & validate nonce for AJAX.
 * Dies with JSON error if invalid.
 *
 * @param string $action
 * @param string $nonce_key  $_POST key for nonce
 */
function examhub_verify_ajax_nonce( $action = 'examhub_ajax', $nonce_key = 'nonce' ) {
    $nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, $action ) ) {
        wp_send_json_error( [ 'message' => __( 'طلب غير صالح.', 'examhub' ) ], 403 );
    }
}

/**
 * Log to WP debug log only in debug mode.
 */
function examhub_log( $message, $context = [] ) {
    if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) return;
    $ctx_str = $context ? ' ' . json_encode( $context, JSON_UNESCAPED_UNICODE ) : '';
    error_log( '[ExamHub] ' . $message . $ctx_str );
}

/**
 * Get a deterministic fallback avatar SVG as data URI.
 *
 * @param string $text
 * @param int    $size
 * @return string
 */
function examhub_avatar_placeholder_data_uri( $text = 'U', $size = 96 ) {
    $char = strtoupper( trim( (string) $text ) );
    if ( function_exists( 'mb_substr' ) ) {
        $char = mb_substr( $char, 0, 1 );
    } else {
        $char = substr( $char, 0, 1 );
    }
    if ( '' === $char ) {
        $char = 'U';
    }

    $safe_char = esc_html( $char );
    $font_size = max( 14, (int) round( $size * 0.44 ) );

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 100 100">'
        . '<defs><linearGradient id="g" x1="0" x2="1" y1="0" y2="1"><stop offset="0%" stop-color="#4361ee"/><stop offset="100%" stop-color="#1f2b5c"/></linearGradient></defs>'
        . '<rect width="100" height="100" rx="50" fill="url(#g)"/>'
        . '<text x="50" y="57" dominant-baseline="middle" text-anchor="middle" font-family="Cairo,Tajawal,Arial,sans-serif" font-size="' . $font_size . '" font-weight="700" fill="#ffffff">'
        . $safe_char
        . '</text></svg>';

    return 'data:image/svg+xml;base64,' . base64_encode( $svg );
}

/**
 * Get user avatar URL with robust fallback.
 * Priority: Google image meta > real WP avatar > generated initials avatar.
 *
 * @param int $user_id
 * @param int $size
 * @return string
 */
function examhub_get_user_avatar_url( $user_id = 0, $size = 96 ) {
    if ( ! $user_id ) {
        $user_id = get_current_user_id();
    }

    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return examhub_avatar_placeholder_data_uri( 'U', $size );
    }

    $google_avatar = (string) get_user_meta( $user_id, 'examhub_google_picture', true );
    if ( $google_avatar && filter_var( $google_avatar, FILTER_VALIDATE_URL ) ) {
        return add_query_arg( 'sz', (int) $size, $google_avatar );
    }

    $avatar_data = get_avatar_data(
        $user_id,
        array(
            'size'    => (int) $size,
            'default' => '404',
        )
    );

    if ( ! empty( $avatar_data['found_avatar'] ) && ! empty( $avatar_data['url'] ) ) {
        return $avatar_data['url'];
    }

    $name = $user->display_name ? $user->display_name : $user->user_login;
    return examhub_avatar_placeholder_data_uri( $name, $size );
}

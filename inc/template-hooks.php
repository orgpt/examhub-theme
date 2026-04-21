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

/**
 * Get the default social share image URL.
 */
function examhub_get_default_social_share_image() {
    return EXAMHUB_ASSETS . 'images/social-share-default.png';
}

/**
 * Build theme social share metadata.
 */
function examhub_get_social_share_meta() {
    $image         = examhub_get_default_social_share_image();
    $title         = wp_get_document_title();
    $description   = get_bloginfo( 'description' );
    $request_uri   = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
    $url           = home_url( $request_uri );
    $type          = 'website';

    if ( is_singular() ) {
        $post_id = get_queried_object_id();
        $type    = 'article';
        $url     = get_permalink( $post_id );

        if ( has_excerpt( $post_id ) ) {
            $description = get_the_excerpt( $post_id );
        } elseif ( is_singular( 'eh_exam' ) ) {
            $description = __( 'اختبر مستواك، اجمع XP، وتحدى أصحابك على امتحانكم.', 'examhub' );
        }

        if ( ! is_singular( 'eh_exam' ) && has_post_thumbnail( $post_id ) ) {
            $thumbnail = get_the_post_thumbnail_url( $post_id, 'full' );
            if ( $thumbnail ) {
                $image = $thumbnail;
            }
        }
    }

    $description = $description ?: __( 'تدرب بذكاء، راقب تقدمك، وتحدى أصحابك على امتحانكم.', 'examhub' );

    return [
        'title'       => $title,
        'description' => wp_strip_all_tags( $description ),
        'url'         => $url,
        'image'       => $image,
        'type'        => $type,
    ];
}

/**
 * Output default social share metadata when an SEO plugin is not handling it.
 */
add_action( 'wp_head', function() {
    if ( is_admin() || is_feed() || defined( 'RANK_MATH_VERSION' ) ) {
        return;
    }

    $meta = examhub_get_social_share_meta();
    ?>
    <meta property="og:locale" content="<?php echo esc_attr( get_locale() ); ?>">
    <meta property="og:type" content="<?php echo esc_attr( $meta['type'] ); ?>">
    <meta property="og:site_name" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
    <meta property="og:title" content="<?php echo esc_attr( $meta['title'] ); ?>">
    <meta property="og:description" content="<?php echo esc_attr( $meta['description'] ); ?>">
    <meta property="og:url" content="<?php echo esc_url( $meta['url'] ); ?>">
    <meta property="og:image" content="<?php echo esc_url( $meta['image'] ); ?>">
    <meta property="og:image:secure_url" content="<?php echo esc_url( $meta['image'] ); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo esc_attr( $meta['title'] ); ?>">
    <meta name="twitter:description" content="<?php echo esc_attr( $meta['description'] ); ?>">
    <meta name="twitter:image" content="<?php echo esc_url( $meta['image'] ); ?>">
    <?php
}, 2 );

/**
 * Tell Rank Math to use the custom share card for exam pages.
 */
add_filter( 'rank_math/opengraph/facebook/image', function( $image ) {
    if ( is_singular( 'eh_exam' ) ) {
        return examhub_get_default_social_share_image();
    }

    return $image;
} );

add_filter( 'rank_math/opengraph/twitter/image', function( $image ) {
    if ( is_singular( 'eh_exam' ) ) {
        return examhub_get_default_social_share_image();
    }

    return $image;
} );

add_filter( 'rank_math/opengraph/pre_set_content_image', function( $return, $post_id ) {
    if ( is_singular( 'eh_exam' ) || 'eh_exam' === get_post_type( $post_id ) ) {
        return true;
    }

    return $return;
}, 10, 2 );

add_filter( 'rank_math/opengraph/twitter/card_type', function( $type ) {
    if ( is_singular( 'eh_exam' ) ) {
        return 'summary_large_image';
    }

    return $type;
} );

// Increment question usage count when included in exam
add_action( 'examhub_exam_submitted', function( $exam_id, $result_id, $user_id ) {
    $q_ids = examhub_build_exam_question_list( $exam_id );
    foreach ( $q_ids as $qid ) {
        $current = (int) get_field( 'usage_count', $qid );
        update_field( 'usage_count', $current + 1, $qid );
    }
}, 10, 3 );

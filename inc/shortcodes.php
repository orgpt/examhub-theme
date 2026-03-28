<?php
/**
 * ExamHub — Shortcodes
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

// [examhub_filter] — full cascading filter bar
add_shortcode( 'examhub_filter', function( $atts ) {
    ob_start();
    get_template_part( 'template-parts/filter-bar' );
    return ob_get_clean();
} );

// [examhub_exams grade_id="X" subject_id="Y" limit="12"]
add_shortcode( 'examhub_exams', function( $atts ) {
    $atts  = shortcode_atts( [ 'grade_id' => 0, 'subject_id' => 0, 'limit' => 12 ], $atts );
    $query = examhub_get_exams_query( [ 'grade' => (int)$atts['grade_id'], 'subject' => (int)$atts['subject_id'], 'per_page' => (int)$atts['limit'] ] );
    ob_start();
    if ( $query->have_posts() ) {
        echo '<div class="row g-3">';
        while ( $query->have_posts() ) {
            $query->the_post();
            get_template_part( 'template-parts/cards/exam-card' );
        }
        echo '</div>';
        wp_reset_postdata();
    }
    return ob_get_clean();
} );

// [examhub_leaderboard type="global" limit="10"]
add_shortcode( 'examhub_leaderboard', function( $atts ) {
    $atts  = shortcode_atts( [ 'type' => 'global', 'grade_id' => 0, 'limit' => 10 ], $atts );
    $board = examhub_get_leaderboard( $atts['type'], (int)$atts['grade_id'], (int)$atts['limit'] );
    ob_start();
    get_template_part( 'template-parts/leaderboard-widget', null, [ 'board' => $board ] );
    return ob_get_clean();
} );

// [examhub_user_stats] — XP, level, streak widget
add_shortcode( 'examhub_user_stats', function() {
    if ( ! is_user_logged_in() ) return '';
    $uid   = get_current_user_id();
    $xp    = (int) get_user_meta( $uid, 'eh_xp', true );
    $level = examhub_get_user_level( $xp );
    ob_start();
    ?>
    <div class="eh-level-card">
        <div class="eh-level-badge"><i class="bi bi-lightning-fill"></i><?php echo esc_html($level['name']); ?></div>
        <div class="eh-xp-progress">
            <div class="d-flex justify-content-between mb-1">
                <small><?php echo number_format($xp); ?> XP</small>
                <?php if($level['next_level_xp']): ?><small><?php echo number_format($level['next_level_xp']); ?> XP</small><?php endif; ?>
            </div>
            <div class="progress"><div class="progress-bar" style="width:<?php echo $level['progress_pct']; ?>%"></div></div>
        </div>
        <?php $streak = (int) get_user_meta( $uid, 'eh_streak', true ); if($streak): ?>
        <div class="mt-2 small">🔥 سلسلة <?php echo $streak; ?> <?php esc_html_e('أيام','examhub'); ?></div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
} );

// [examhub_pricing] — Subscription plans cards
add_shortcode( 'examhub_pricing', function() {
    $plans = examhub_get_all_plans();
    usort( $plans, fn($a,$b) => (int)($a['plan_priority']??0) - (int)($b['plan_priority']??0) );
    ob_start();
    echo '<div class="row g-4">';
    foreach ( $plans as $plan ) {
        get_template_part( 'template-parts/plan-card', null, [ 'plan' => $plan ] );
    }
    echo '</div>';
    return ob_get_clean();
} );

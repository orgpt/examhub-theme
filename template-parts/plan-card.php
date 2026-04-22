<?php
/**
 * ExamHub — template-parts/plan-card.php
 * Single subscription plan card.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

$plan        = $args['plan'] ?? [];
$user_id     = get_current_user_id();
$sub         = $user_id ? examhub_get_user_subscription_status( $user_id ) : [ 'state' => 'free', 'plan_id' => null ];
$is_current  = $user_id && $sub['plan_id'] === ($plan['plan_slug'] ?? '');
$is_featured = ! empty( $plan['plan_featured'] );
$features    = array_filter( explode( "\n", $plan['plan_features_list'] ?? '' ) );
$offer       = examhub_get_plan_offer_prices( $plan );
$duration    = (int)($plan['plan_duration_days'] ?? 30);
?>
<div class="col-md-6 col-lg-4">
  <div class="eh-plan-card <?php echo $is_featured ? 'featured' : ''; ?> h-100">
    <?php if ( $is_featured ) : ?>
      <div class="plan-badge"><?php esc_html_e('الأشهر','examhub'); ?></div>
    <?php endif; ?>
    <?php if ( $is_current ) : ?>
      <div class="plan-badge" style="background:var(--eh-success);"><?php esc_html_e('خطتك الحالية','examhub'); ?></div>
    <?php endif; ?>

    <div class="plan-name mt-3"><?php echo esc_html($plan['plan_name_ar']?:$plan['plan_name']); ?></div>
    <?php if ( $offer['discount'] ) : ?>
      <div class="plan-discount-badge"><?php printf( esc_html__( 'خصم %s%%', 'examhub' ), esc_html( $offer['discount'] ) ); ?></div>
    <?php endif; ?>
    <?php if ( $offer['regular'] > $offer['current'] ) : ?>
      <div class="plan-old-price">
        <?php esc_html_e( 'بدلًا من', 'examhub' ); ?>
        <del><?php echo esc_html( number_format_i18n( $offer['regular'] ) ); ?> <?php esc_html_e( 'جنيه', 'examhub' ); ?></del>
      </div>
    <?php endif; ?>
    <div class="plan-price mt-2">
      <?php echo number_format_i18n($offer['current']); ?>
      <span><?php esc_html_e('جنيه','examhub'); ?><?php if($duration) echo ' / '.$duration.' '.esc_html__('يوم','examhub'); ?></span>
    </div>
    <?php if($plan['plan_description']) : ?><p class="text-muted small mt-2"><?php echo esc_html($plan['plan_description']); ?></p><?php endif; ?>

    <ul class="plan-features">
      <?php $plan_limit = (int) ( $plan['plan_exams_limit'] ?? $plan['plan_questions_limit'] ?? 0 ); ?>
      <li><?php echo !empty($plan['plan_unlimited']) ? esc_html__('امتحانات غير محدودة','examhub') : ($plan_limit.' '.esc_html__('امتحان/يوم','examhub')); ?></li>
      <li <?php echo empty($plan['plan_explanation_access'])? 'class="unavailable"':''; ?>><?php esc_html_e('الشرح التفصيلي','examhub'); ?></li>
      <li <?php echo empty($plan['plan_ai_access'])? 'class="unavailable"':''; ?>><?php esc_html_e('الذكاء الاصطناعي','examhub'); ?></li>
      <li <?php echo empty($plan['plan_download_access'])? 'class="unavailable"':''; ?>><?php esc_html_e('تحميل PDF','examhub'); ?></li>
      <?php foreach(array_slice($features,0,4) as $feat): ?><li><?php echo esc_html(str_replace('أسئلة','امتحانات', trim($feat))); ?></li><?php endforeach; ?>
    </ul>

    <?php if($is_current): ?>
      <button class="btn btn-ghost w-100" disabled><?php esc_html_e('خطتك الحالية ✓','examhub'); ?></button>
    <?php elseif($user_id): ?>
      <a href="<?php echo esc_url(home_url('/checkout?plan='.esc_attr($plan['plan_slug']))); ?>"
         class="btn <?php echo $is_featured ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
        <?php esc_html_e( 'اشترك دلوقتي وابدأ فورًا', 'examhub' ); ?>
      </a>
    <?php else: ?>
      <a href="<?php echo esc_url(wp_registration_url()); ?>"
         class="btn <?php echo $is_featured ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
        <?php esc_html_e( 'اشترك دلوقتي وابدأ فورًا', 'examhub' ); ?>
      </a>
    <?php endif; ?>
  </div>
</div>

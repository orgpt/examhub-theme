<?php
/**
 * Fallback template when no content exists.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

$context = get_query_var( 'examhub_empty_state_context', [] );

if ( ! empty( $context['type'] ) && 'exam_waitlist' === $context['type'] ) :
  $button_label = ! empty( $context['joined'] ) ? __( 'تم الانضمام لقائمة الانتظار', 'examhub' ) : __( 'انضم لقائمة الانتظار', 'examhub' );
  $helper_text  = ! empty( $context['joined'] )
    ? __( 'سنتواصل معك فور نزول امتحانات جديدة مطابقة لاختياراتك الحالية.', 'examhub' )
    : __( 'سجّل اهتمامك الآن وسنرسل لك إشعارًا فور إضافة امتحانات جديدة مناسبة لهذا المسار.', 'examhub' );
  ?>
  <section class="eh-empty-state eh-empty-state-waitlist text-center">
    <div class="empty-icon">
      <i class="bi bi-hourglass-split"></i>
    </div>
    <h3><?php esc_html_e( 'لا توجد امتحانات متاحة الآن', 'examhub' ); ?></h3>
    <p><?php esc_html_e( 'هذا المسار لم تُضف له امتحانات بعد، لكن يمكنك الانضمام لقائمة الانتظار وسنبلغك مباشرة عند توفرها.', 'examhub' ); ?></p>
    <button
      type="button"
      class="btn btn-primary eh-waitlist-btn js-eh-join-waitlist"
      data-system-id="<?php echo esc_attr( $context['education_system'] ?? 0 ); ?>"
      data-stage-id="<?php echo esc_attr( $context['stage'] ?? 0 ); ?>"
      data-grade-id="<?php echo esc_attr( $context['grade'] ?? 0 ); ?>"
      data-subject-id="<?php echo esc_attr( $context['subject'] ?? 0 ); ?>"
      data-difficulty="<?php echo esc_attr( $context['difficulty'] ?? '' ); ?>"
      data-joined="<?php echo ! empty( $context['joined'] ) ? '1' : '0'; ?>"
      <?php disabled( ! empty( $context['joined'] ) ); ?>
    >
      <i class="bi bi-bell me-2"></i>
      <?php echo esc_html( $button_label ); ?>
    </button>
    <p class="eh-empty-state-promo mb-0"><?php echo esc_html( $helper_text ); ?></p>
  </section>
  <?php
  return;
endif;
?>
<section class="eh-empty-state">
  <div class="empty-icon">
    <i class="bi bi-inboxes"></i>
  </div>
  <h3><?php esc_html_e( 'لا يوجد محتوى منشور بعد.', 'examhub' ); ?></h3>
  <p><?php esc_html_e( 'ابدأ بنشر مقالات جديدة أو راجع إعدادات القراءة في ووردبريس لإظهار المحتوى هنا.', 'examhub' ); ?></p>
</section>

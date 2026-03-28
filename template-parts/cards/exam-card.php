<?php
/**
 * ExamHub — template-parts/cards/exam-card.php
 * Reusable exam card for archive and AJAX responses.
 *
 * @package ExamHub
 */

$exam_id   = get_the_ID();
$user_id   = get_current_user_id();

// Meta
$grade_id      = (int) get_field( 'exam_grade',   $exam_id );
$subject_id    = (int) get_field( 'exam_subject',  $exam_id );
$duration      = (int) get_field( 'exam_duration_minutes', $exam_id );
$difficulty    = get_field( 'exam_difficulty',    $exam_id ) ?: 'mixed';
$access_level  = get_field( 'exam_access',         $exam_id ) ?: 'free_limit';
$timer_type    = get_field( 'timer_type',           $exam_id );
$q_count       = examhub_get_exam_question_count(  $exam_id );
$xp_reward     = (int) get_field( 'exam_xp_reward', $exam_id );

// Labels
$grade_name   = $grade_id   ? ( get_field( 'grade_name_ar',   $grade_id )   ?: get_the_title( $grade_id )   ) : '';
$subject_name = $subject_id ? ( get_field( 'subject_name_ar', $subject_id ) ?: get_the_title( $subject_id ) ) : '';
$subject_color = $subject_id ? ( get_field( 'subject_color',  $subject_id ) ?: '#4361ee' ) : '#4361ee';

// User state
$sub         = $user_id ? examhub_get_user_subscription_status( $user_id ) : null;
$is_locked   = false;
if ( $access_level === 'subscribed' && ( ! $sub || ! in_array( $sub['state'], [ 'subscribed', 'trial', 'lifetime' ] ) ) ) {
    $is_locked = true;
} elseif ( $access_level === 'free_limit' && $user_id && examhub_get_remaining_questions( $user_id ) <= 0 ) {
    $is_locked = true;
}

// Previous attempt
$has_taken  = $user_id ? examhub_user_has_taken_exam( $exam_id, $user_id ) : false;
$best_result = $has_taken ? examhub_get_best_result( $exam_id, $user_id ) : null;
$best_pct    = $best_result ? (float) get_field( 'percentage', $best_result ) : null;
?>

<div class="col-sm-6 col-xl-4">
  <article class="eh-exam-card <?php echo $is_locked ? 'locked' : ''; ?>" data-exam-id="<?php echo esc_attr( $exam_id ); ?>">

    <!-- Thumbnail -->
    <div class="card-thumb" style="--subject-color: <?php echo esc_attr( $subject_color ); ?>">
      <?php if ( has_post_thumbnail() ) : ?>
        <img src="<?php the_post_thumbnail_url( 'exam-thumbnail' ); ?>"
          alt="<?php the_title_attribute(); ?>" loading="lazy">
      <?php else : ?>
        <!-- Subject color placeholder -->
        <div style="background: linear-gradient(135deg, <?php echo esc_attr( $subject_color ); ?>22, <?php echo esc_attr( $subject_color ); ?>11); height:100%; display:flex; align-items:center; justify-content:center;">
          <i class="bi bi-clipboard-check" style="font-size:2.5rem; color:<?php echo esc_attr( $subject_color ); ?>; opacity:0.5;"></i>
        </div>
      <?php endif; ?>

      <?php if ( $is_locked ) : ?>
        <i class="bi bi-lock-fill lock-icon"></i>
      <?php endif; ?>

      <!-- Difficulty badge on thumb -->
      <span class="badge badge-<?php echo esc_attr( $difficulty ); ?>"
        style="position:absolute; top:10px; right:10px;">
        <?php echo esc_html( examhub_difficulty_label( $difficulty ) ); ?>
      </span>

      <?php if ( $has_taken && $best_pct !== null ) : ?>
        <!-- Best score badge -->
        <span class="badge" style="position:absolute; top:10px; left:10px; background:var(--eh-bg-secondary); color:var(--eh-text-primary);">
          <i class="bi bi-trophy-fill" style="color:var(--eh-gold);"></i>
          <?php echo number_format( $best_pct, 0 ); ?>%
        </span>
      <?php endif; ?>
    </div>

    <!-- Body -->
    <div class="card-body">
      <!-- Subject pill -->
      <?php if ( $subject_name ) : ?>
        <div class="mb-2">
          <span class="badge" style="background: <?php echo esc_attr( $subject_color ); ?>20; color: <?php echo esc_attr( $subject_color ); ?>; border: 1px solid <?php echo esc_attr( $subject_color ); ?>40;">
            <?php echo esc_html( $subject_name ); ?>
          </span>
          <?php if ( $grade_name ) : ?>
            <span class="badge badge-accent ms-1"><?php echo esc_html( $grade_name ); ?></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Title -->
      <h3 class="exam-title">
        <a href="<?php the_permalink(); ?>" class="text-light stretched-link">
          <?php the_title(); ?>
        </a>
      </h3>

      <!-- Meta row -->
      <div class="exam-meta">
        <?php if ( $q_count ) : ?>
          <span class="exam-meta-item">
            <i class="bi bi-question-circle"></i>
            <?php printf( esc_html__( '%d سؤال', 'examhub' ), $q_count ); ?>
          </span>
        <?php endif; ?>

        <?php if ( $duration && $timer_type === 'exam' ) : ?>
          <span class="exam-meta-item">
            <i class="bi bi-clock"></i>
            <?php echo esc_html( examhub_format_duration( $duration ) ); ?>
          </span>
        <?php endif; ?>

        <?php if ( $xp_reward ) : ?>
          <span class="exam-meta-item" style="color:var(--eh-accent);">
            <i class="bi bi-lightning-fill"></i>
            <?php echo $xp_reward; ?> XP
          </span>
        <?php endif; ?>
      </div>

      <!-- CTA -->
      <div class="card-footer-action">
        <?php if ( $is_locked ) : ?>
          <a href="<?php echo home_url( '/pricing' ); ?>"
            class="btn btn-sm btn-outline-primary w-100"
            data-exam-id="<?php echo esc_attr( $exam_id ); ?>">
            <i class="bi bi-lock me-1"></i>
            <?php esc_html_e( 'اشترك للوصول', 'examhub' ); ?>
          </a>
        <?php elseif ( $has_taken ) : ?>
          <a href="<?php the_permalink(); ?>" class="btn btn-sm btn-ghost w-100">
            <i class="bi bi-arrow-repeat me-1"></i>
            <?php esc_html_e( 'إعادة الامتحان', 'examhub' ); ?>
          </a>
        <?php else : ?>
          <a href="<?php the_permalink(); ?>" class="btn btn-sm btn-primary w-100">
            <i class="bi bi-play-fill me-1"></i>
            <?php esc_html_e( 'ابدأ الامتحان', 'examhub' ); ?>
          </a>
        <?php endif; ?>
      </div>

    </div><!-- .card-body -->
  </article>
</div>

<?php
/**
 * ExamHub — Filter Bar Template Part
 * Cascading filter: System → Stage → Grade → Year → Subject → Difficulty
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

$systems = get_posts( [
    'post_type'      => 'eh_education_system',
    'posts_per_page' => 20,
    'orderby'        => 'meta_value_num',
    'meta_key'       => 'display_order',
    'order'          => 'ASC',
] );

// Build academic years list (taxonomy)
$years = get_terms( [ 'taxonomy' => 'eh_year', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'DESC' ] );
?>

<div class="eh-filter-bar" id="eh-filter-bar">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <span class="eh-section-title mb-0">
      <i class="bi bi-funnel icon"></i>
      <?php esc_html_e( 'تصفية الامتحانات', 'examhub' ); ?>
    </span>
    <button type="button" class="btn btn-ghost btn-sm" id="eh-filter-reset">
      <i class="bi bi-arrow-counterclockwise me-1"></i><?php esc_html_e( 'إعادة ضبط', 'examhub' ); ?>
    </button>
  </div>

  <div class="row g-2">

    <!-- Education System -->
    <div class="col-6 col-md-4 col-lg-2">
      <label class="form-label small text-muted"><?php esc_html_e( 'النظام', 'examhub' ); ?></label>
      <select id="filter-system" class="form-select form-select-sm" data-placeholder="<?php esc_attr_e( 'كل الأنظمة', 'examhub' ); ?>">
        <option value=""><?php esc_html_e( 'كل الأنظمة', 'examhub' ); ?></option>
        <?php foreach ( $systems as $sys ) : ?>
          <option value="<?php echo $sys->ID; ?>">
            <?php echo esc_html( get_field( 'name_ar', $sys->ID ) ?: $sys->post_title ); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Stage -->
    <div class="col-6 col-md-4 col-lg-2">
      <label class="form-label small text-muted"><?php esc_html_e( 'المرحلة', 'examhub' ); ?></label>
      <select id="filter-stage" class="form-select form-select-sm" data-placeholder="<?php esc_attr_e( 'كل المراحل', 'examhub' ); ?>">
        <option value=""><?php esc_html_e( 'كل المراحل', 'examhub' ); ?></option>
      </select>
    </div>

    <!-- Grade -->
    <div class="col-6 col-md-4 col-lg-2">
      <label class="form-label small text-muted"><?php esc_html_e( 'الصف', 'examhub' ); ?></label>
      <select id="filter-grade" class="form-select form-select-sm" data-placeholder="<?php esc_attr_e( 'كل الصفوف', 'examhub' ); ?>">
        <option value=""><?php esc_html_e( 'كل الصفوف', 'examhub' ); ?></option>
      </select>
    </div>

    <!-- Academic Year -->
    <?php if ( ! is_wp_error( $years ) && ! empty( $years ) ) : ?>
    <div class="col-6 col-md-4 col-lg-2">
      <label class="form-label small text-muted"><?php esc_html_e( 'العام', 'examhub' ); ?></label>
      <select id="filter-year" class="form-select form-select-sm">
        <option value=""><?php esc_html_e( 'كل الأعوام', 'examhub' ); ?></option>
        <?php foreach ( $years as $year ) : ?>
          <option value="<?php echo esc_attr( $year->slug ); ?>"><?php echo esc_html( $year->name ); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <!-- Subject -->
    <div class="col-6 col-md-4 col-lg-2">
      <label class="form-label small text-muted"><?php esc_html_e( 'المادة', 'examhub' ); ?></label>
      <select id="filter-subject" class="form-select form-select-sm" data-placeholder="<?php esc_attr_e( 'كل المواد', 'examhub' ); ?>">
        <option value=""><?php esc_html_e( 'كل المواد', 'examhub' ); ?></option>
      </select>
    </div>

    <!-- Difficulty -->
    <div class="col-6 col-md-4 col-lg-2">
      <label class="form-label small text-muted"><?php esc_html_e( 'الصعوبة', 'examhub' ); ?></label>
      <select id="filter-difficulty" class="form-select form-select-sm">
        <option value=""><?php esc_html_e( 'كل المستويات', 'examhub' ); ?></option>
        <option value="easy"><?php esc_html_e( 'سهل', 'examhub' ); ?></option>
        <option value="medium"><?php esc_html_e( 'متوسط', 'examhub' ); ?></option>
        <option value="hard"><?php esc_html_e( 'صعب', 'examhub' ); ?></option>
      </select>
    </div>

  </div><!-- .row -->
</div><!-- .eh-filter-bar -->

<script>
jQuery(function($){
  $('#eh-filter-reset').on('click', function(){
    $('#eh-filter-bar select').each(function(){
      $(this).prop('selectedIndex', 0);
    });
    // Reset cascading selects to empty
    ['#filter-stage','#filter-grade','#filter-subject'].forEach(function(sel){
      const placeholder = $(sel).data('placeholder') || '— اختر —';
      $(sel).html(`<option value="">${placeholder}</option>`);
    });
    // Trigger filter update
    $('#filter-system').trigger('change');
  });
});
</script>

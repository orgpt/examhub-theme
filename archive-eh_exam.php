<?php
/**
 * ExamHub â€” archive-eh_exam.php
 * Exam listing page with cascade filter and AJAX loading.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;
get_header();

// Get pre-selected filter values from URL
$sel_system  = (int) ( $_GET['system']  ?? 0 );
$sel_stage   = (int) ( $_GET['stage']   ?? 0 );
$sel_grade   = (int) ( $_GET['grade']   ?? 0 );
$sel_subject = (int) ( $_GET['subject'] ?? 0 );
$sel_unit    = (int) ( $_GET['unit']    ?? 0 );
$sel_lesson  = (int) ( $_GET['lesson']  ?? 0 );
$sel_diff    = sanitize_text_field( $_GET['difficulty'] ?? '' );

// Load all education systems
$edu_systems = get_posts( [
    'post_type'      => 'eh_education_system',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => [ 'meta_value_num' => 'ASC', 'title' => 'ASC' ],
] );
?>

<div class="container-xl">

  <!-- Page header -->
  <div class="eh-page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div>
        <h1 class="eh-page-title">
          <i class="bi bi-clipboard-check me-2 text-accent"></i>
          <?php esc_html_e( 'Ø§Ù„Ø§Ù…ØªØ­Ø§Ù†Ø§Øª', 'examhub' ); ?>
        </h1>
        <p class="eh-page-subtitle mb-0">
          <?php
          $total = wp_count_posts( 'eh_exam' )->publish;
          printf( esc_html__( '%s Ø§Ù…ØªØ­Ø§Ù† Ù…ØªØ§Ø­ Ù„Ù„ØªØ¯Ø±ÙŠØ¨', 'examhub' ), number_format( $total ) );
          ?>
        </p>
      </div>
      <!-- Search -->
      <div class="d-flex gap-2">
        <div class="input-group" style="width:260px;">
          <span class="input-group-text" style="background:var(--eh-bg-input); border-color:var(--eh-border); color:var(--eh-text-muted);">
            <i class="bi bi-search"></i>
          </span>
          <input type="search" id="eh-exam-search" class="form-control"
            placeholder="<?php esc_attr_e( 'Ø§Ø¨Ø­Ø« Ø¹Ù† Ø§Ù…ØªØ­Ø§Ù†...', 'examhub' ); ?>"
            value="<?php echo esc_attr( $_GET['s'] ?? '' ); ?>">
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">

    <!-- â”€â”€â”€ Filter Sidebar (desktop) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
    <div class="col-lg-3 d-none d-lg-block">
      <div class="eh-filter-sidebar" id="eh-filter-sidebar">

        <!-- Education System -->
        <div class="card mb-3">
          <div class="card-body p-3">
            <h6 class="eh-section-title mb-3">
              <i class="bi bi-mortarboard icon"></i>
              <?php esc_html_e( 'Ø§Ù„Ù†Ø¸Ø§Ù… Ø§Ù„ØªØ¹Ù„ÙŠÙ…ÙŠ', 'examhub' ); ?>
            </h6>
            <div class="d-flex flex-column gap-2" id="system-buttons">
              <?php foreach ( $edu_systems as $sys ) :
                $color = get_field( 'system_color', $sys->ID ) ?: '#4361ee';
                $name  = get_field( 'name_ar', $sys->ID ) ?: $sys->post_title;
                $active = $sel_system === $sys->ID;
              ?>
              <button type="button" class="btn btn-ghost text-start eh-system-btn <?php echo $active ? 'active' : ''; ?>"
                data-id="<?php echo esc_attr( $sys->ID ); ?>"
                style="<?php echo $active ? "border-color:{$color}; color:{$color}; background: rgba(" . examhub_hex_to_rgb( $color ) . ", .12);" : ''; ?>">
                <i class="bi bi-circle-fill me-2" style="color:<?php echo esc_attr( $color ); ?>; font-size:0.5rem;"></i>
                <?php echo esc_html( $name ); ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Cascade selects -->
        <div class="card mb-3" id="stage-card" style="display:none!important;">
          <div class="card-body p-3">
            <label class="form-label"><?php esc_html_e( 'Ø§Ù„Ù…Ø±Ø­Ù„Ø©', 'examhub' ); ?></label>
            <select class="form-select" id="sel-stage">
              <option value=""><?php esc_html_e( 'Ø§Ø®ØªØ± Ø§Ù„Ù…Ø±Ø­Ù„Ø©', 'examhub' ); ?></option>
            </select>
          </div>
        </div>

        <div class="card mb-3" id="grade-card" style="display:none!important;">
          <div class="card-body p-3">
            <label class="form-label"><?php esc_html_e( 'Ø§Ù„ØµÙ', 'examhub' ); ?></label>
            <select class="form-select" id="sel-grade">
              <option value=""><?php esc_html_e( 'Ø§Ø®ØªØ± Ø§Ù„ØµÙ', 'examhub' ); ?></option>
            </select>
          </div>
        </div>

        <div class="card mb-3" id="subject-card" style="display:none!important;">
          <div class="card-body p-3">
            <label class="form-label"><?php esc_html_e( 'Ø§Ù„Ù…Ø§Ø¯Ø©', 'examhub' ); ?></label>
            <div id="subject-chips" class="d-flex flex-wrap gap-2"></div>
          </div>
        </div>

        <div class="card mb-3" id="unit-card" style="display:none!important;">
          <div class="card-body p-3">
            <label class="form-label"><?php esc_html_e( 'Ø§Ù„ÙˆØ­Ø¯Ø©', 'examhub' ); ?></label>
            <select class="form-select" id="sel-unit">
              <option value=""><?php esc_html_e( 'ÙƒÙ„ Ø§Ù„ÙˆØ­Ø¯Ø§Øª', 'examhub' ); ?></option>
            </select>
          </div>
        </div>

        <div class="card mb-3" id="lesson-card" style="display:none!important;">
          <div class="card-body p-3">
            <label class="form-label"><?php esc_html_e( 'Ø§Ù„Ø¯Ø±Ø³', 'examhub' ); ?></label>
            <select class="form-select" id="sel-lesson">
              <option value=""><?php esc_html_e( 'ÙƒÙ„ Ø§Ù„Ø¯Ø±ÙˆØ³', 'examhub' ); ?></option>
            </select>
          </div>
        </div>

        <!-- Difficulty filter -->
        <div class="card mb-3">
          <div class="card-body p-3">
            <h6 class="eh-section-title mb-3">
              <i class="bi bi-bar-chart-fill icon"></i>
              <?php esc_html_e( 'Ù…Ø³ØªÙˆÙ‰ Ø§Ù„ØµØ¹ÙˆØ¨Ø©', 'examhub' ); ?>
            </h6>
            <div class="d-flex flex-wrap gap-2">
              <button type="button" class="btn badge badge-easy eh-diff-btn <?php echo $sel_diff === 'easy' ? 'active' : ''; ?>" data-diff="easy">
                <?php esc_html_e( 'Ø³Ù‡Ù„', 'examhub' ); ?>
              </button>
              <button type="button" class="btn badge badge-medium eh-diff-btn <?php echo $sel_diff === 'medium' ? 'active' : ''; ?>" data-diff="medium">
                <?php esc_html_e( 'Ù…ØªÙˆØ³Ø·', 'examhub' ); ?>
              </button>
              <button type="button" class="btn badge badge-hard eh-diff-btn <?php echo $sel_diff === 'hard' ? 'active' : ''; ?>" data-diff="hard">
                <?php esc_html_e( 'ØµØ¹Ø¨', 'examhub' ); ?>
              </button>
            </div>
          </div>
        </div>

        <!-- Clear filters -->
        <button type="button" class="btn btn-ghost btn-sm w-100" id="btn-clear-filters">
          <i class="bi bi-x-circle me-1"></i>
          <?php esc_html_e( 'Ù…Ø³Ø­ Ø§Ù„ØªØµÙÙŠØ©', 'examhub' ); ?>
        </button>

      </div>
    </div>

    <!-- â”€â”€â”€ Exam Grid â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
    <div class="col-lg-9">

      <!-- Mobile filter bar (compact) -->
      <div class="d-lg-none mb-3">
        <button class="btn btn-ghost btn-sm w-100" data-bs-toggle="offcanvas" data-bs-target="#filterOffcanvas">
          <i class="bi bi-sliders me-2"></i>
          <?php esc_html_e( 'ØªØµÙÙŠØ© Ø§Ù„Ù†ØªØ§Ø¦Ø¬', 'examhub' ); ?>
          <span id="mobile-filter-count" class="badge badge-accent ms-2" style="display:none;">0</span>
        </button>
      </div>

      <!-- Active filter breadcrumb -->
      <div id="filter-breadcrumb" class="d-flex flex-wrap gap-2 mb-3" style="display:none!important;"></div>

      <!-- Sort bar -->
      <div class="d-flex align-items-center justify-content-between mb-3">
        <span id="results-count" class="small text-muted"></span>
        <select class="form-select form-select-sm" id="sel-sort" style="width:auto;">
          <option value="date_desc"><?php esc_html_e( 'Ø§Ù„Ø£Ø­Ø¯Ø«', 'examhub' ); ?></option>
          <option value="date_asc"><?php esc_html_e( 'Ø§Ù„Ø£Ù‚Ø¯Ù…', 'examhub' ); ?></option>
          <option value="popular"><?php esc_html_e( 'Ø§Ù„Ø£ÙƒØ«Ø± Ù…Ø­Ø§ÙˆÙ„Ø©', 'examhub' ); ?></option>
        </select>
      </div>

      <!-- Exam cards grid -->
      <div class="row g-3" id="exam-grid">
        <?php
        // Initial load
        $initial_query = examhub_get_exams_query( [
            'education_system' => $sel_system,
            'grade'            => $sel_grade,
            'subject'          => $sel_subject,
        ] );

        if ( $initial_query->have_posts() ) :
          while ( $initial_query->have_posts() ) :
            $initial_query->the_post();
            get_template_part( 'template-parts/cards/exam-card' );
          endwhile;
          wp_reset_postdata();
        else :
          get_template_part( 'template-parts/content', 'none' );
        endif;
        ?>
      </div>

      <!-- Loading skeleton -->
      <div id="exam-grid-loading" style="display:none;">
        <div class="row g-3">
          <?php for ( $i = 0; $i < 6; $i++ ) : ?>
          <div class="col-sm-6 col-xl-4">
            <div class="card" style="height:260px;">
              <div class="eh-skeleton" style="height:160px;"></div>
              <div class="card-body">
                <div class="eh-skeleton mb-2" style="height:14px;width:80%;"></div>
                <div class="eh-skeleton" style="height:12px;width:50%;"></div>
              </div>
            </div>
          </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Pagination -->
      <div id="exam-pagination" class="mt-4 d-flex justify-content-center">
        <?php
        $big = 999999;
        echo paginate_links( [
            'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
            'format'    => '?paged=%#%',
            'current'   => max( 1, get_query_var( 'paged' ) ),
            'total'     => $initial_query->max_num_pages,
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'type'      => 'list',
        ] );
        ?>
      </div>

    </div>
  </div><!-- .row -->
</div>

<!-- Mobile filter offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="filterOffcanvas"
  style="background:var(--eh-bg-secondary); max-width:320px; width:90vw;">
  <div class="offcanvas-header border-bottom" style="border-color:var(--eh-border)!important;">
    <h5 class="offcanvas-title text-light"><?php esc_html_e( 'ØªØµÙÙŠØ©', 'examhub' ); ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body" id="mobile-filter-body">
    <?php // Mobile filter cloned via JS from sidebar ?>
  </div>
</div>

<?php
wp_footer_data( 'examhubFilterConfig', [
    'initial_system'  => $sel_system,
    'initial_stage'   => $sel_stage,
    'initial_grade'   => $sel_grade,
    'initial_subject' => $sel_subject,
    'initial_unit'    => $sel_unit,
    'initial_lesson'  => $sel_lesson,
    'initial_diff'    => $sel_diff,
] );

get_footer();

/**
 * Pass data to the page footer for JS pickup.
 */
function wp_footer_data( $key, $data ) {
    add_action( 'wp_footer', function() use ( $key, $data ) {
        echo '<script>window.' . esc_js( $key ) . ' = ' . json_encode( $data ) . ';</script>';
    } );
}


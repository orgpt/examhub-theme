<?php
/**
 * ExamHub - page-my-results.php
 * User results listing page (slug: my-results).
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( home_url( '/my-results' ) ) );
	exit;
}

$user_id          = get_current_user_id();
$allowed_statuses = array( 'submitted', 'timed_out', 'in_progress' );
$status_filter    = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'all';
$paged            = max( 1, get_query_var( 'paged', 1 ) );
$per_page         = 12;

if ( ! in_array( $status_filter, array_merge( array( 'all' ), $allowed_statuses ), true ) ) {
	$status_filter = 'all';
}

$meta_query = array();
if ( 'all' === $status_filter ) {
	$meta_query[] = array(
		'key'     => 'result_status',
		'value'   => array( 'submitted', 'timed_out', 'in_progress' ),
		'compare' => 'IN',
	);
} else {
	$meta_query[] = array(
		'key'   => 'result_status',
		'value' => $status_filter,
	);
}

$results_query = new WP_Query(
	array(
		'post_type'      => 'eh_result',
		'post_status'    => 'publish',
		'author'         => $user_id,
		'posts_per_page' => $per_page,
		'paged'          => $paged,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => $meta_query,
	)
);

// Summary stats.
$all_result_ids = get_posts(
	array(
		'post_type'      => 'eh_result',
		'post_status'    => 'publish',
		'author'         => $user_id,
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'     => 'result_status',
				'value'   => array( 'submitted', 'timed_out' ),
				'compare' => 'IN',
			),
		),
	)
);

$total_results = count( $all_result_ids );
$avg_score     = 0;
$best_score    = 0;
$passed_count  = 0;

if ( $total_results > 0 ) {
	$total_score_sum = 0;

	foreach ( $all_result_ids as $rid ) {
		$pct    = (float) get_field( 'percentage', $rid );
		$passed = (bool) get_field( 'passed', $rid );

		$total_score_sum += $pct;
		$best_score       = max( $best_score, $pct );

		if ( $passed ) {
			$passed_count++;
		}
	}

	$avg_score = round( $total_score_sum / $total_results, 1 );
}

$pass_rate = $total_results > 0 ? round( ( $passed_count / $total_results ) * 100 ) : 0;

function examhub_result_status_label( $status ) {
	$map = array(
		'submitted'   => __( 'مكتمل', 'examhub' ),
		'timed_out'   => __( 'انتهى الوقت', 'examhub' ),
		'in_progress' => __( 'قيد الحل', 'examhub' ),
	);

	return isset( $map[ $status ] ) ? $map[ $status ] : __( 'غير معروف', 'examhub' );
}

function examhub_result_status_badge_class( $status ) {
	$map = array(
		'submitted'   => 'badge-success',
		'timed_out'   => 'badge-warning',
		'in_progress' => 'badge-info',
	);

	return isset( $map[ $status ] ) ? $map[ $status ] : 'badge-accent';
}

get_header();
?>

<div class="container-xl">
	<div class="eh-page-header">
		<div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
			<div>
				<h1 class="eh-page-title mb-1"><?php esc_html_e( 'نتائجي', 'examhub' ); ?></h1>
				<p class="eh-page-subtitle mb-0"><?php esc_html_e( 'تابع أداءك واعرف نقاط قوتك وضعفك', 'examhub' ); ?></p>
			</div>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'eh_exam' ) ?: home_url( '/exam' ) ); ?>" class="btn btn-primary">
				<i class="bi bi-play-circle me-1"></i>
				<?php esc_html_e( 'ابدأ امتحان جديد', 'examhub' ); ?>
			</a>
		</div>
	</div>

	<div class="row g-3 mb-4">
		<div class="col-6 col-lg-3">
			<div class="eh-stat-card">
				<div class="stat-icon icon-accent"><i class="bi bi-clipboard-check-fill"></i></div>
				<div>
					<div class="stat-value"><?php echo esc_html( number_format( $total_results ) ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'امتحان مكتمل', 'examhub' ); ?></div>
				</div>
			</div>
		</div>
		<div class="col-6 col-lg-3">
			<div class="eh-stat-card">
				<div class="stat-icon icon-success"><i class="bi bi-graph-up-arrow"></i></div>
				<div>
					<div class="stat-value"><?php echo esc_html( number_format( $avg_score, 1 ) ); ?>%</div>
					<div class="stat-label"><?php esc_html_e( 'متوسط النتيجة', 'examhub' ); ?></div>
				</div>
			</div>
		</div>
		<div class="col-6 col-lg-3">
			<div class="eh-stat-card">
				<div class="stat-icon" style="background:var(--eh-warning-bg); color:var(--eh-warning);"><i class="bi bi-trophy"></i></div>
				<div>
					<div class="stat-value"><?php echo esc_html( number_format( $best_score, 1 ) ); ?>%</div>
					<div class="stat-label"><?php esc_html_e( 'أفضل نتيجة', 'examhub' ); ?></div>
				</div>
			</div>
		</div>
		<div class="col-6 col-lg-3">
			<div class="eh-stat-card">
				<div class="stat-icon" style="background:var(--eh-info-bg); color:var(--eh-info);"><i class="bi bi-patch-check"></i></div>
				<div>
					<div class="stat-value"><?php echo esc_html( number_format( $pass_rate ) ); ?>%</div>
					<div class="stat-label"><?php esc_html_e( 'معدل النجاح', 'examhub' ); ?></div>
				</div>
			</div>
		</div>
	</div>

	<div class="card mb-3">
		<div class="card-body p-3">
			<form method="get" class="d-flex align-items-center gap-2 flex-wrap">
				<label for="result-status" class="form-label mb-0"><?php esc_html_e( 'فلترة بالحالة', 'examhub' ); ?></label>
				<select id="result-status" name="status" class="form-select" style="max-width: 220px;">
					<option value="all" <?php selected( $status_filter, 'all' ); ?>><?php esc_html_e( 'كل الحالات', 'examhub' ); ?></option>
					<option value="submitted" <?php selected( $status_filter, 'submitted' ); ?>><?php esc_html_e( 'مكتمل', 'examhub' ); ?></option>
					<option value="timed_out" <?php selected( $status_filter, 'timed_out' ); ?>><?php esc_html_e( 'انتهى الوقت', 'examhub' ); ?></option>
					<option value="in_progress" <?php selected( $status_filter, 'in_progress' ); ?>><?php esc_html_e( 'قيد الحل', 'examhub' ); ?></option>
				</select>
				<button class="btn btn-primary btn-sm" type="submit">
					<i class="bi bi-funnel me-1"></i>
					<?php esc_html_e( 'تطبيق', 'examhub' ); ?>
				</button>
			</form>
		</div>
	</div>

	<?php if ( $results_query->have_posts() ) : ?>
		<div class="card">
			<div class="table-responsive">
				<table class="table table-hover align-middle mb-0">
					<thead>
						<tr>
							<th><?php esc_html_e( 'الامتحان', 'examhub' ); ?></th>
							<th><?php esc_html_e( 'الحالة', 'examhub' ); ?></th>
							<th><?php esc_html_e( 'النتيجة', 'examhub' ); ?></th>
							<th><?php esc_html_e( 'التاريخ', 'examhub' ); ?></th>
							<th class="text-center"><?php esc_html_e( 'إجراء', 'examhub' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						while ( $results_query->have_posts() ) :
							$results_query->the_post();

							$result_id    = get_the_ID();
							$exam_id      = (int) get_field( 'result_exam_id', $result_id );
							$status       = (string) get_field( 'result_status', $result_id );
							$percentage   = (float) get_field( 'percentage', $result_id );
							$exam_title   = $exam_id ? get_the_title( $exam_id ) : __( 'امتحان غير متاح', 'examhub' );
							$exam_link    = $exam_id ? get_permalink( $exam_id ) : '';
							$result_link  = $exam_link ? add_query_arg( 'result', $result_id, $exam_link ) : '';
							$status_label = examhub_result_status_label( $status );
							$status_class = examhub_result_status_badge_class( $status );
							?>
							<tr>
								<td>
									<div class="fw-bold text-light"><?php echo esc_html( $exam_title ); ?></div>
								</td>
								<td>
									<span class="badge <?php echo esc_attr( $status_class ); ?>">
										<?php echo esc_html( $status_label ); ?>
									</span>
								</td>
								<td>
									<?php if ( in_array( $status, array( 'submitted', 'timed_out' ), true ) ) : ?>
										<span class="fw-bold <?php echo $percentage >= 50 ? 'text-success' : 'text-danger'; ?>">
											<?php echo esc_html( number_format( $percentage, 1 ) ); ?>%
										</span>
									<?php else : ?>
										<span class="text-muted"><?php esc_html_e( '—', 'examhub' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<span class="text-secondary"><?php echo esc_html( get_the_date( 'Y/m/d - g:i a', $result_id ) ); ?></span>
								</td>
								<td class="text-center">
									<?php if ( $result_link ) : ?>
										<a href="<?php echo esc_url( $result_link ); ?>" class="btn btn-ghost btn-sm">
											<?php esc_html_e( 'عرض', 'examhub' ); ?>
										</a>
									<?php else : ?>
										<span class="text-muted"><?php esc_html_e( 'غير متاح', 'examhub' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endwhile; ?>
					</tbody>
				</table>
			</div>
		</div>

		<?php
		$pagination = paginate_links(
			array(
				'total'     => max( 1, (int) $results_query->max_num_pages ),
				'current'   => $paged,
				'type'      => 'array',
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
				'add_args'  => array(
					'status' => $status_filter,
				),
			)
		);
		?>
		<?php if ( ! empty( $pagination ) ) : ?>
			<nav class="mt-3" aria-label="<?php esc_attr_e( 'نتائج الصفحات', 'examhub' ); ?>">
				<ul class="pagination justify-content-center mb-0">
					<?php foreach ( $pagination as $page_link ) : ?>
						<li class="page-item"><?php echo wp_kses_post( str_replace( 'page-numbers', 'page-link', $page_link ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
	<?php else : ?>
		<div class="card">
			<div class="card-body">
				<div class="eh-empty-state py-5">
					<div class="empty-icon"><i class="bi bi-bar-chart-line"></i></div>
					<h3 class="mb-2"><?php esc_html_e( 'لا توجد نتائج بعد', 'examhub' ); ?></h3>
					<p class="mb-3"><?php esc_html_e( 'ابدأ أول امتحان لك وسيظهر هنا تلقائيًا', 'examhub' ); ?></p>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'eh_exam' ) ?: home_url( '/exam' ) ); ?>" class="btn btn-primary">
						<?php esc_html_e( 'ابدأ امتحان الآن', 'examhub' ); ?>
					</a>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>

<?php
wp_reset_postdata();
get_footer();


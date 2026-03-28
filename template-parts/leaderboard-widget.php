<?php
/**
 * ExamHub — template-parts/leaderboard-widget.php
 * Compact leaderboard widget (e.g. in dashboard sidebar).
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

$board       = $args['board'] ?? [];
$current_uid = get_current_user_id();
$show_limit  = $args['limit'] ?? 10;

if ( empty( $board ) ) :
?>
  <p class="text-muted small"><?php esc_html_e( 'لا توجد بيانات بعد.', 'examhub' ); ?></p>
<?php return; endif; ?>

<div class="eh-leaderboard-widget">
  <?php foreach ( array_slice( $board, 0, $show_limit ) as $row ) :
    $is_current = $current_uid && (int)$row['user_id'] === $current_uid;
  ?>
  <div class="eh-leaderboard-item <?php echo $row['rank']<=3 ? 'rank-'.$row['rank'] : ''; ?> <?php echo $is_current ? 'is-current-user' : ''; ?>" style="padding:.6rem .75rem;">
    <div class="lb-rank <?php echo $row['rank']===1?'gold':($row['rank']===2?'silver':($row['rank']===3?'bronze':'')); ?>" style="width:28px;font-size:.85rem;">
      <?php echo $row['rank']===1?'🥇':($row['rank']===2?'🥈':($row['rank']===3?'🥉':'#'.$row['rank'])); ?>
    </div>
    <img src="<?php echo esc_url($row['avatar']); ?>" class="lb-avatar" style="width:30px;height:30px;" alt="">
    <div style="flex:1;min-width:0;">
      <div class="lb-name" style="font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
        <?php echo esc_html($row['name']); ?>
        <?php if($is_current): ?><span class="badge badge-accent" style="font-size:.6rem;">أنت</span><?php endif; ?>
      </div>
    </div>
    <div class="text-end" style="flex-shrink:0;">
      <div class="lb-xp-val" style="font-size:.82rem;"><?php echo number_format($row['xp']); ?></div>
      <div class="lb-xp-label" style="font-size:.7rem;">XP</div>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="text-center mt-2">
    <a href="<?php echo esc_url(home_url('/leaderboard')); ?>" class="btn btn-ghost btn-sm w-100">
      <?php esc_html_e('عرض الكل','examhub'); ?> →
    </a>
  </div>
</div>

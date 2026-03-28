<?php
/**
 * Fallback template when no content exists.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="eh-empty-state">
  <div class="empty-icon">
    <i class="bi bi-inboxes"></i>
  </div>
  <h3><?php esc_html_e( 'No content found yet.', 'examhub' ); ?></h3>
  <p><?php esc_html_e( 'Set a homepage in Settings > Reading, or publish content to show it here.', 'examhub' ); ?></p>
</section>

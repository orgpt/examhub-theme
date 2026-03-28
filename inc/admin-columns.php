<?php
/**
 * ExamHub - Admin Columns Extra (payment approve JS)
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_footer', function() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'eh_payment' ) return;
    ?>
    <script>
    jQuery(function($){
        $(document).on('click', '.eh-approve-payment', function(e){
            e.preventDefault();
            e.stopPropagation();

            const id  = $(this).data('id');
            const txn = prompt('Transaction ID (optional):', '');
            if (txn === null) return;

            $.post(ajaxurl, {
                action: 'eh_admin_approve_payment',
                nonce: '<?php echo wp_create_nonce("examhub_admin_ajax"); ?>',
                payment_id: id,
                transaction_id: txn
            }, function(r){
                r.success ? location.reload() : alert(r.data?.message || 'Error');
            });
        });

        $(document).on('click', '.eh-reject-payment', function(e){
            e.preventDefault();
            e.stopPropagation();

            const id  = $(this).data('id');
            const why = prompt('Reject reason:', '');
            if (!why) return;

            $.post(ajaxurl, {
                action: 'eh_admin_reject_payment',
                nonce: '<?php echo wp_create_nonce("examhub_admin_ajax"); ?>',
                payment_id: id,
                reason: why
            }, function(r){
                r.success ? location.reload() : alert(r.data?.message || 'Error');
            });
        });
    });
    </script>
    <?php
} );

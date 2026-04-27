<?php
defined( 'ABSPATH' ) || exit;

$order_id  = absint( get_query_var( 'eh_book_order' ) );
$order_key = sanitize_text_field( (string) get_query_var( 'eh_book_key' ) );
$order     = examhub_get_book_order( $order_id, $order_key );

get_header();
?>

<div class="container-xl py-4">
  <?php if ( ! $order ) : ?>
    <div class="card p-5 text-center">
      <h1 class="h4 mb-2">تعذر العثور على الطلب</h1>
      <p class="text-muted mb-3">تأكد من رابط التأكيد أو تواصل مع الإدارة.</p>
      <a class="btn btn-primary" href="<?php echo esc_url( examhub_get_books_archive_url() ); ?>">العودة للكتب</a>
    </div>
  <?php else : ?>
    <?php
    $items = json_decode( (string) get_field( 'order_items_json', $order_id ), true );
    $items = is_array( $items ) ? $items : [];
    $payment_method = (string) get_field( 'order_payment_method', $order_id );
    $instructions   = examhub_get_book_payment_instructions( $payment_method );
    ?>
    <div class="card">
      <div class="card-body p-4 p-lg-5">
        <span class="badge bg-success mb-3">تم استلام الطلب</span>
        <h1 class="eh-page-title mb-2">شكراً، تم تسجيل طلبك بنجاح</h1>
        <p class="eh-page-subtitle">رقم الطلب: #<?php echo esc_html( (string) $order_id ); ?></p>

        <div class="row g-4 mt-1">
          <div class="col-lg-7">
            <h2 class="h5 mb-3">الكتب المطلوبة</h2>
            <?php foreach ( $items as $item ) : ?>
              <div class="d-flex justify-content-between gap-3 mb-2">
                <span><?php echo esc_html( $item['title'] ?? '' ); ?> × <?php echo esc_html( (string) ( $item['qty'] ?? 0 ) ); ?></span>
                <strong><?php echo esc_html( number_format_i18n( (float) ( $item['subtotal'] ?? 0 ), 2 ) ); ?> ج</strong>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="col-lg-5">
            <div class="card h-100">
              <div class="card-body">
                <h2 class="h5 mb-3">ملخص الدفع</h2>
                <div class="d-flex justify-content-between mb-2"><span>الإجمالي</span><strong><?php echo esc_html( number_format_i18n( (float) get_field( 'order_subtotal', $order_id ), 2 ) ); ?> ج</strong></div>
                <div class="d-flex justify-content-between mb-2"><span>الشحن</span><strong><?php echo esc_html( number_format_i18n( (float) get_field( 'order_shipping', $order_id ), 2 ) ); ?> ج</strong></div>
                <div class="d-flex justify-content-between mb-3"><span>الإجمالي النهائي</span><strong><?php echo esc_html( number_format_i18n( (float) get_field( 'order_total', $order_id ), 2 ) ); ?> ج</strong></div>
                <div class="mb-2"><strong>طريقة الدفع:</strong> <?php echo esc_html( (string) get_field( 'order_payment_method', $order_id ) ); ?></div>
                <div><strong>طريقة الشحن:</strong> <?php echo esc_html( (string) get_field( 'order_shipping_method', $order_id ) ); ?></div>
                <?php if ( $instructions ) : ?>
                  <hr>
                  <h3 class="h6">تعليمات الدفع</h3>
                  <pre class="eh-book-pre"><?php echo esc_html( $instructions ); ?></pre>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-4 d-flex flex-wrap gap-2">
          <a class="btn btn-primary" href="<?php echo esc_url( examhub_get_books_archive_url() ); ?>">طلب كتب أخرى</a>
          <a class="btn btn-outline-light" href="<?php echo esc_url( home_url() ); ?>">العودة للرئيسية</a>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php get_footer(); ?>

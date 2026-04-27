<?php
defined( 'ABSPATH' ) || exit;

$shipping_methods = examhub_get_enabled_book_shipping_methods();
$payment_methods  = examhub_get_enabled_book_payment_methods();
$selected_shipping = isset( $_GET['shipping_method'] ) ? sanitize_text_field( wp_unslash( $_GET['shipping_method'] ) ) : ( array_key_first( $shipping_methods ) ?: 'delivery' );
$totals = examhub_get_book_cart_totals( $selected_shipping );
$items  = $totals['items'];

if ( empty( $items ) ) {
    wp_safe_redirect( examhub_get_books_cart_url() );
    exit;
}

get_header();
?>

<div class="container-xl py-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
      <h1 class="eh-page-title mb-1">إتمام طلب الكتب</h1>
      <p class="eh-page-subtitle mb-0">أدخل بيانات الشحن والدفع لحفظ الطلب.</p>
    </div>
    <a class="btn btn-outline-light" href="<?php echo esc_url( examhub_get_books_cart_url() ); ?>">العودة للسلة</a>
  </div>

  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'examhub_book_place_order', 'examhub_book_nonce' ); ?>
    <input type="hidden" name="action" value="examhub_book_place_order">

    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card mb-4">
          <div class="card-body">
            <h2 class="h5 mb-3">بيانات العميل</h2>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">الاسم</label>
                <input class="form-control" type="text" name="customer_name" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">رقم الهاتف</label>
                <input class="form-control" type="text" name="customer_phone" required>
              </div>
              <div class="col-12">
                <label class="form-label">البريد الإلكتروني</label>
                <input class="form-control" type="email" name="customer_email">
              </div>
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-body">
            <h2 class="h5 mb-3">الشحن</h2>
            <div class="row g-3">
              <?php foreach ( $shipping_methods as $method_key => $method_label ) : ?>
                <div class="col-12">
                  <label class="eh-check-choice">
                    <input type="radio" name="shipping_method" value="<?php echo esc_attr( $method_key ); ?>" <?php checked( $selected_shipping, $method_key ); ?>>
                    <span><?php echo esc_html( $method_label ); ?></span>
                  </label>
                </div>
              <?php endforeach; ?>
              <div class="col-md-4">
                <label class="form-label">المحافظة</label>
                <input class="form-control" type="text" name="customer_governorate">
              </div>
              <div class="col-md-4">
                <label class="form-label">المدينة / المركز</label>
                <input class="form-control" type="text" name="customer_city">
              </div>
              <div class="col-md-4">
                <label class="form-label">العنوان</label>
                <input class="form-control" type="text" name="customer_address">
              </div>
              <div class="col-12">
                <label class="form-label">ملاحظات إضافية</label>
                <textarea class="form-control" rows="4" name="customer_notes"></textarea>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            <h2 class="h5 mb-3">الدفع</h2>
            <div class="row g-3">
              <?php foreach ( $payment_methods as $method_key => $method ) : ?>
                <div class="col-12">
                  <label class="eh-check-choice">
                    <input type="radio" name="payment_method" value="<?php echo esc_attr( $method_key ); ?>" <?php checked( array_key_first( $payment_methods ), $method_key ); ?>>
                    <span>
                      <strong><?php echo esc_html( $method['label'] ); ?></strong>
                      <small class="d-block text-muted"><?php echo esc_html( $method['desc'] ); ?></small>
                    </span>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card">
          <div class="card-body">
            <h2 class="h5 mb-3">ملخص الطلب</h2>
            <?php foreach ( $items as $item ) : ?>
              <div class="d-flex justify-content-between gap-2 mb-2">
                <span><?php echo esc_html( $item['title'] ); ?> × <?php echo esc_html( (string) $item['qty'] ); ?></span>
                <strong><?php echo esc_html( number_format_i18n( $item['subtotal'], 2 ) ); ?> ج</strong>
              </div>
            <?php endforeach; ?>
            <hr>
            <div class="d-flex justify-content-between mb-2"><span>الإجمالي</span><strong><?php echo esc_html( number_format_i18n( $totals['subtotal'], 2 ) ); ?> ج</strong></div>
            <div class="d-flex justify-content-between mb-2"><span>الشحن</span><strong><?php echo esc_html( number_format_i18n( $totals['shipping'], 2 ) ); ?> ج</strong></div>
            <div class="d-flex justify-content-between mb-3"><span>الإجمالي النهائي</span><strong class="text-accent"><?php echo esc_html( number_format_i18n( $totals['total'], 2 ) ); ?> ج</strong></div>
            <button class="btn btn-primary w-100" type="submit">تأكيد الطلب</button>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<?php get_footer(); ?>

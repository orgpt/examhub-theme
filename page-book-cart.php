<?php
defined( 'ABSPATH' ) || exit;

$totals = examhub_get_book_cart_totals();
$items  = $totals['items'];

get_header();
?>

<div class="container-xl py-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
      <h1 class="eh-page-title mb-1">سلة كتب خارجية</h1>
      <p class="eh-page-subtitle mb-0">راجع الكتب قبل إتمام الطلب.</p>
    </div>
    <a class="btn btn-outline-light" href="<?php echo esc_url( examhub_get_books_archive_url() ); ?>">مواصلة التسوق</a>
  </div>

  <?php if ( empty( $items ) ) : ?>
    <div class="card p-5 text-center">
      <h2 class="h5 mb-2">السلة فارغة</h2>
      <p class="text-muted mb-3">ابدأ بإضافة الكتب التي تريد شراءها.</p>
      <a class="btn btn-primary" href="<?php echo esc_url( examhub_get_books_archive_url() ); ?>">عرض الكتب</a>
    </div>
  <?php else : ?>
    <div class="row g-4">
      <div class="col-lg-8">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <?php wp_nonce_field( 'examhub_book_update_cart', 'examhub_book_nonce' ); ?>
          <input type="hidden" name="action" value="examhub_book_update_cart">

          <div class="card">
            <div class="card-body p-0">
              <?php foreach ( $items as $item ) : ?>
                <div class="eh-cart-item">
                  <div class="eh-cart-item__media">
                    <?php if ( $item['thumb'] ) : ?>
                      <img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>">
                    <?php else : ?>
                      <span><i class="bi bi-book"></i></span>
                    <?php endif; ?>
                  </div>

                  <div class="eh-cart-item__body">
                    <a class="eh-cart-item__title" href="<?php echo esc_url( $item['link'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
                    <?php if ( $item['author'] ) : ?>
                      <div class="text-muted small"><?php echo esc_html( $item['author'] ); ?></div>
                    <?php endif; ?>
                    <div class="eh-cart-item__meta"><?php echo esc_html( number_format_i18n( $item['price']['current'], 2 ) ); ?> ج للنسخة</div>
                  </div>

                  <div class="eh-cart-item__qty">
                    <input type="number" min="0" name="qty[<?php echo esc_attr( $item['id'] ); ?>]" value="<?php echo esc_attr( $item['qty'] ); ?>" class="form-control">
                  </div>

                  <div class="eh-cart-item__subtotal">
                    <?php echo esc_html( number_format_i18n( $item['subtotal'], 2 ) ); ?> ج
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="mt-3">
            <button class="btn btn-outline-light" type="submit">تحديث السلة</button>
          </div>
        </form>
      </div>

      <div class="col-lg-4">
        <div class="card">
          <div class="card-body">
            <h2 class="h5 mb-3">ملخص الطلب</h2>
            <div class="d-flex justify-content-between mb-2"><span>إجمالي الكتب</span><strong><?php echo esc_html( number_format_i18n( $totals['subtotal'], 2 ) ); ?> ج</strong></div>
            <div class="d-flex justify-content-between mb-2"><span>الشحن</span><strong>يُحسب في الخطوة التالية</strong></div>
            <div class="d-flex justify-content-between mb-3"><span>عدد القطع</span><strong><?php echo esc_html( (string) examhub_get_book_cart_count() ); ?></strong></div>
            <a class="btn btn-primary w-100" href="<?php echo esc_url( examhub_get_books_checkout_url() ); ?>">إتمام الطلب</a>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php get_footer(); ?>

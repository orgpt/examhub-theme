<?php
defined( 'ABSPATH' ) || exit;

get_header();

the_post();

$book_id  = get_the_ID();
$price    = examhub_get_book_price_data( $book_id );
$grade    = (int) get_field( 'book_grade', $book_id );
$subject  = (int) get_field( 'book_subject', $book_id );
$author   = (string) get_field( 'book_author', $book_id );
$publisher = (string) get_field( 'book_publisher', $book_id );
$sku      = (string) get_field( 'book_sku', $book_id );
$track    = (bool) get_field( 'book_track_stock', $book_id );
$stock    = (int) get_field( 'book_stock_qty', $book_id );
$short_description = (string) get_field( 'book_short_description', $book_id );
$long_description  = (string) get_field( 'book_long_description', $book_id );
$terms    = get_field( 'book_store_terms', 'option' );
?>

<div class="container-xl py-4">
  <div class="row g-4 align-items-start">
    <div class="col-lg-5">
      <div class="eh-book-single__media">
        <?php if ( has_post_thumbnail() ) : ?>
          <?php the_post_thumbnail( 'large', [ 'loading' => 'eager' ] ); ?>
        <?php else : ?>
          <div class="eh-book-single__placeholder"><i class="bi bi-book"></i></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="eh-book-single">
        <a class="eh-book-single__back" href="<?php echo esc_url( examhub_get_books_archive_url() ); ?>">العودة لكل الكتب</a>
        <h1 class="eh-page-title mb-2"><?php the_title(); ?></h1>

        <?php if ( $short_description ) : ?>
          <p class="eh-book-single__summary mb-3"><?php echo esc_html( $short_description ); ?></p>
        <?php endif; ?>

        <div class="eh-book-single__chips mb-3">
          <?php if ( $grade ) : ?><span><?php echo esc_html( get_the_title( $grade ) ); ?></span><?php endif; ?>
          <?php if ( $subject ) : ?><span><?php echo esc_html( get_the_title( $subject ) ); ?></span><?php endif; ?>
          <?php if ( $author ) : ?><span><?php echo esc_html( $author ); ?></span><?php endif; ?>
        </div>

        <div class="eh-book-single__price mb-3">
          <strong><?php echo esc_html( number_format_i18n( $price['current'], 2 ) ); ?> جنيه</strong>
          <?php if ( $price['discount'] > 0 ) : ?>
            <span><?php echo esc_html( number_format_i18n( $price['regular'], 2 ) ); ?> جنيه</span>
            <em>خصم <?php echo esc_html( $price['discount'] ); ?>%</em>
          <?php endif; ?>
        </div>

        <div class="eh-book-single__info card mb-3">
          <div class="card-body">
            <?php if ( $publisher ) : ?><div><strong>دار النشر:</strong> <?php echo esc_html( $publisher ); ?></div><?php endif; ?>
            <?php if ( $sku ) : ?><div><strong>الكود:</strong> <?php echo esc_html( $sku ); ?></div><?php endif; ?>
            <div><strong>المخزون:</strong> <?php echo $track ? esc_html( (string) $stock ) : 'متاح'; ?></div>
          </div>
        </div>

        <div class="eh-book-single__content card mb-3">
          <div class="card-body">
            <h2 class="h6 mb-3">تفاصيل الكتاب</h2>
            <?php
            if ( $long_description ) {
                echo wp_kses_post( $long_description );
            } else {
                the_content();
            }
            ?>
          </div>
        </div>

        <?php if ( examhub_is_book_available( $book_id ) ) : ?>
          <form class="eh-book-single__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'examhub_book_add_to_cart', 'examhub_book_nonce' ); ?>
            <input type="hidden" name="action" value="examhub_book_add_to_cart">
            <input type="hidden" name="book_id" value="<?php echo esc_attr( $book_id ); ?>">
            <div class="d-flex flex-wrap gap-2">
              <input type="number" class="form-control" style="max-width:120px;" min="1" max="<?php echo esc_attr( $track ? max( 1, $stock ) : 99 ); ?>" name="quantity" value="1">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-cart-plus me-1"></i>أضف إلى السلة
              </button>
              <a href="<?php echo esc_url( examhub_get_books_cart_url() ); ?>" class="btn btn-outline-light">عرض السلة</a>
            </div>
          </form>
        <?php else : ?>
          <div class="alert alert-warning mb-0">هذا الكتاب غير متاح حالياً.</div>
        <?php endif; ?>

        <?php if ( $terms ) : ?>
          <div class="card mt-3">
            <div class="card-body">
              <h2 class="h6 mb-2">سياسة الطلب</h2>
              <p class="mb-0 text-muted"><?php echo esc_html( $terms ); ?></p>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php get_footer(); ?>

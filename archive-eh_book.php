<?php
defined( 'ABSPATH' ) || exit;

get_header();

$store_title  = get_field( 'book_store_title', 'option' ) ?: 'كتب خارجية';
$store_intro  = get_field( 'book_store_intro', 'option' ) ?: 'اختر الكتاب المناسب واطلبه مباشرة من داخل الموقع.';
$store_notice = get_field( 'book_store_notice', 'option' );
?>

<div class="container-xl py-4">
  <div class="eh-books-hero">
    <div>
      <span class="eh-books-hero__eyebrow">Book Store</span>
      <h1 class="eh-page-title mb-2"><?php echo esc_html( $store_title ); ?></h1>
      <p class="eh-page-subtitle mb-0"><?php echo esc_html( $store_intro ); ?></p>
    </div>
    <a class="btn btn-outline-light" href="<?php echo esc_url( examhub_get_books_cart_url() ); ?>">
      <i class="bi bi-cart3 me-1"></i>
      <?php printf( 'السلة (%d)', examhub_get_book_cart_count() ); ?>
    </a>
  </div>

  <?php if ( $store_notice ) : ?>
    <div class="alert alert-info mt-3"><?php echo esc_html( $store_notice ); ?></div>
  <?php endif; ?>

  <?php if ( have_posts() ) : ?>
    <div class="row g-4 mt-1">
      <?php while ( have_posts() ) : the_post(); ?>
        <div class="col-12 col-md-6 col-xl-4">
          <?php get_template_part( 'template-parts/cards/book-card' ); ?>
        </div>
      <?php endwhile; ?>
    </div>

    <div class="mt-4">
      <?php the_posts_pagination(); ?>
    </div>
  <?php else : ?>
    <div class="card p-4 text-center">
      <h2 class="h5 mb-2">لا توجد كتب مضافة حالياً</h2>
      <p class="text-muted mb-0">أضف منتجات من لوحة التحكم لتظهر هنا مباشرة.</p>
    </div>
  <?php endif; ?>
</div>

<?php get_footer(); ?>

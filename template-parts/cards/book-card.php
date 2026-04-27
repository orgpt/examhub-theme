<?php
defined( 'ABSPATH' ) || exit;

$book_id = get_the_ID();
$price   = examhub_get_book_price_data( $book_id );
$grade   = (int) get_field( 'book_grade', $book_id );
$subject = (int) get_field( 'book_subject', $book_id );
$badge   = (string) get_field( 'book_badge', $book_id );
$stock   = (int) get_field( 'book_stock_qty', $book_id );
$track   = (bool) get_field( 'book_track_stock', $book_id );
?>
<article class="eh-book-card h-100">
  <a class="eh-book-card__media" href="<?php the_permalink(); ?>">
    <?php if ( has_post_thumbnail() ) : ?>
      <?php the_post_thumbnail( 'large', [ 'loading' => 'lazy' ] ); ?>
    <?php else : ?>
      <span class="eh-book-card__placeholder"><i class="bi bi-book"></i></span>
    <?php endif; ?>
    <?php if ( $badge ) : ?>
      <span class="eh-book-card__badge"><?php echo esc_html( $badge ); ?></span>
    <?php endif; ?>
  </a>

  <div class="eh-book-card__body">
    <div class="eh-book-card__meta">
      <?php if ( $grade ) : ?>
        <span><?php echo esc_html( get_the_title( $grade ) ); ?></span>
      <?php endif; ?>
      <?php if ( $subject ) : ?>
        <span><?php echo esc_html( get_the_title( $subject ) ); ?></span>
      <?php endif; ?>
    </div>

    <h3 class="eh-book-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

    <p class="eh-book-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt() ?: get_the_content( null, false ), 18 ) ); ?></p>

    <div class="eh-book-card__footer">
      <div class="eh-book-card__price">
        <strong><?php echo esc_html( number_format_i18n( $price['current'], 2 ) ); ?> ج</strong>
        <?php if ( $price['discount'] > 0 ) : ?>
          <span><?php echo esc_html( number_format_i18n( $price['regular'], 2 ) ); ?> ج</span>
        <?php endif; ?>
      </div>

      <div class="eh-book-card__actions">
        <?php if ( examhub_is_book_available( $book_id ) ) : ?>
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'examhub_book_add_to_cart', 'examhub_book_nonce' ); ?>
            <input type="hidden" name="action" value="examhub_book_add_to_cart">
            <input type="hidden" name="book_id" value="<?php echo esc_attr( $book_id ); ?>">
            <input type="hidden" name="quantity" value="1">
            <button type="submit" class="btn btn-primary btn-sm"><?php esc_html_e( 'أضف للسلة', 'examhub' ); ?></button>
          </form>
        <?php else : ?>
          <span class="eh-book-card__soldout"><?php echo $track && $stock < 1 ? 'نفد المخزون' : 'غير متاح'; ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</article>

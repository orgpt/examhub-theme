</main><!-- #eh-content -->

<?php
$is_exam_mode = is_singular( 'eh_exam' ) && get_query_var( 'exam_mode' ) === 'focus';
$blog_page    = get_page_by_path( 'blog' );
$blog_url     = $blog_page ? get_permalink( $blog_page ) : home_url( '/blog' );
if ( ! $is_exam_mode ) :
?>
<footer class="eh-footer" role="contentinfo">
  <div class="container-xl">
    <div class="row gy-4 pb-3">

      <!-- Brand col -->
      <div class="col-lg-4">
        <div class="mb-3">
          <?php
          $logo = get_field( 'site_logo_dark', 'option' );
          if ( $logo ) : ?>
            <img src="<?php echo esc_url( $logo ); ?>" alt="<?php bloginfo( 'name' ); ?>" height="32" class="mb-2">
          <?php else : ?>
            <span class="fw-bold text-light fs-5"><?php bloginfo( 'name' ); ?></span>
          <?php endif; ?>
        </div>
        <p class="small"><?php esc_html_e( 'منصة تدريبية متكاملة لطلاب الثانوية العامة والأزهر والتعليم الخاص.', 'examhub' ); ?></p>
        <!-- Social links -->
        <div class="d-flex gap-2 mt-3">
          <?php
          $socials = [
            'facebook'  => [ 'icon' => 'bi-facebook',  'label' => 'Facebook' ],
            'youtube'   => [ 'icon' => 'bi-youtube',   'label' => 'YouTube' ],
            'whatsapp'  => [ 'icon' => 'bi-whatsapp',  'label' => 'WhatsApp' ],
            'telegram'  => [ 'icon' => 'bi-telegram',  'label' => 'Telegram' ],
          ];
          foreach ( $socials as $key => $social ) :
            $url = get_field( 'social_' . $key, 'option' );
            if ( $url ) : ?>
              <a href="<?php echo esc_url( $url ); ?>" class="btn btn-ghost btn-sm px-2" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( $social['label'] ); ?>">
                <i class="bi <?php echo esc_attr( $social['icon'] ); ?>"></i>
              </a>
            <?php endif;
          endforeach; ?>
        </div>
      </div>

      <!-- Quick links -->
      <div class="col-6 col-lg-2">
        <h6 class="text-light fw-bold mb-3"><?php esc_html_e( 'روابط سريعة', 'examhub' ); ?></h6>
        <ul class="list-unstyled mb-0 small">
          <li class="mb-2"><a href="<?php echo get_post_type_archive_link( 'eh_exam' ); ?>"><?php esc_html_e( 'الامتحانات', 'examhub' ); ?></a></li>
          <li class="mb-2"><a href="<?php echo esc_url( $blog_url ); ?>"><?php esc_html_e( 'المدونة', 'examhub' ); ?></a></li>
          <li class="mb-2"><a href="<?php echo home_url( '/leaderboard' ); ?>"><?php esc_html_e( 'المتصدرون', 'examhub' ); ?></a></li>
          <li class="mb-2"><a href="<?php echo home_url( '/pricing' ); ?>"><?php esc_html_e( 'الاشتراك', 'examhub' ); ?></a></li>
          <li class="mb-2"><a href="<?php echo home_url( '/affiliate' ); ?>"><?php esc_html_e( 'الأفلييت', 'examhub' ); ?></a></li>
          <li class="mb-2"><a href="<?php echo home_url( '/daily-challenge' ); ?>"><?php esc_html_e( 'التحدي اليومي', 'examhub' ); ?></a></li>
        </ul>
      </div>

      <!-- Legal -->
      <div class="col-6 col-lg-2">
        <h6 class="text-light fw-bold mb-3"><?php esc_html_e( 'قانوني', 'examhub' ); ?></h6>
        <ul class="list-unstyled mb-0 small">
          <li class="mb-2"><a href="<?php echo home_url( '/terms' ); ?>"><?php esc_html_e( 'الشروط والأحكام', 'examhub' ); ?></a></li>
          <li class="mb-2"><a href="<?php echo home_url( '/privacy' ); ?>"><?php esc_html_e( 'سياسة الخصوصية', 'examhub' ); ?></a></li>
          <li class="mb-2"><a href="<?php echo home_url( '/refund' ); ?>"><?php esc_html_e( 'سياسة الاسترداد', 'examhub' ); ?></a></li>
        </ul>
      </div>

      <!-- Contact -->
      <div class="col-lg-4">
        <h6 class="text-light fw-bold mb-3"><?php esc_html_e( 'تواصل معنا', 'examhub' ); ?></h6>
        <?php $email = get_field( 'contact_email', 'option' ); if ( $email ) : ?>
          <p class="small mb-1"><i class="bi bi-envelope me-2 text-accent"></i><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></p>
        <?php endif; ?>
        <?php $phone = get_field( 'contact_phone', 'option' ); if ( $phone ) : ?>
          <p class="small mb-1"><i class="bi bi-telephone me-2 text-accent"></i><?php echo esc_html( $phone ); ?></p>
        <?php endif; ?>
        <p class="small mb-0 mt-3">
          <i class="bi bi-whatsapp me-2 text-accent"></i>
          <a href="https://wa.me/201090094039" target="_blank" rel="noopener">واتساب</a>
        </p>
      </div>

    </div><!-- .row -->

    <hr class="eh-divider">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center py-2">
      <small>&copy; <?php echo date( 'Y' ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'جميع الحقوق محفوظة.', 'examhub' ); ?></small>
      <small class="mt-2 mt-md-0">
        <?php
        printf(
          esc_html__( 'مدعوم بـ %s', 'examhub' ),
          '<span class="text-accent">DeepSeek AI</span>'
        );
        ?>
      </small>
    </div>

  </div>
</footer>
<?php endif; // !$is_exam_mode ?>

<?php wp_footer(); ?>
</body>
</html>

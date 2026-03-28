<?php
/**
 * Template Name: Checkout
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() . '?plan=' . sanitize_text_field( $_GET['plan'] ?? '' ) ) );
    exit;
}

$plan_slug = sanitize_text_field( $_GET['plan'] ?? '' );
$plan      = $plan_slug ? examhub_get_plan_by_id( $plan_slug ) : null;

if ( ! $plan || empty( $plan['plan_active'] ) ) {
    wp_redirect( home_url( '/pricing' ) );
    exit;
}

$user_id = get_current_user_id();
$user    = wp_get_current_user();
$methods = examhub_get_enabled_payment_methods();
$amount  = examhub_calculate_amount( (float) $plan['plan_price'] );
$sub     = examhub_get_user_subscription_status( $user_id );

get_header();
?>

<div class="container-xl py-4">

  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>"><?php esc_html_e( 'الاشتراك', 'examhub' ); ?></a></li>
      <li class="breadcrumb-item active"><?php esc_html_e( 'إتمام الدفع', 'examhub' ); ?></li>
    </ol>
  </nav>

  <div class="eh-checkout-layout">

    <!-- Payment methods -->
    <div>
      <h2 class="mb-4"><?php esc_html_e( 'طريقة الدفع', 'examhub' ); ?></h2>

      <?php if ( empty( $methods ) ) : ?>
        <div class="alert alert-warning"><?php esc_html_e( 'لا توجد طرق دفع متاحة حالياً.', 'examhub' ); ?></div>
      <?php else : ?>

      <!-- Method selection -->
      <div id="payment-methods-list" class="mb-4">
        <?php foreach ( $methods as $m ) : ?>
        <div class="eh-method-card <?php echo $m === $methods[0] ? 'selected' : ''; ?>"
             data-method="<?php echo esc_attr( $m['key'] ); ?>">
          <div class="method-icon"><i class="bi <?php echo esc_attr( $m['icon'] ); ?>"></i></div>
          <div>
            <div class="method-name"><?php echo esc_html( $m['label'] ); ?></div>
            <div class="method-desc"><?php echo esc_html( $m['desc'] ); ?></div>
            <?php if ( $m['instant'] ) : ?>
              <span class="instant-badge"><i class="bi bi-lightning-fill"></i> <?php esc_html_e( 'تفعيل فوري', 'examhub' ); ?></span>
            <?php else : ?>
              <span class="instant-badge text-warning"><i class="bi bi-clock"></i> <?php esc_html_e( 'مراجعة يدوية', 'examhub' ); ?></span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Dynamic method instructions -->
      <div id="method-instructions" class="card p-4 mb-4" style="display:none;"></div>

      <!-- Proof upload form (for manual methods) -->
      <div id="manual-proof-form" style="display:none;">
        <h5 class="mb-3"><?php esc_html_e( 'إثبات الدفع', 'examhub' ); ?></h5>
        <div class="mb-3">
          <label class="form-label"><?php esc_html_e( 'رقم المرجع / المعاملة', 'examhub' ); ?></label>
          <input type="text" id="proof-reference" class="form-control" placeholder="<?php esc_attr_e( 'مثال: TXN123456', 'examhub' ); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label"><?php esc_html_e( 'رقم هاتفك', 'examhub' ); ?></label>
          <input type="tel" id="proof-phone" class="form-control" value="<?php echo esc_attr( get_user_meta( $user_id, 'billing_phone', true ) ); ?>" placeholder="010xxxxxxxx">
        </div>
        <div class="mb-3">
          <label class="form-label"><?php esc_html_e( 'صورة الإيصال', 'examhub' ); ?></label>
          <input type="file" id="proof-file" class="form-control" accept="image/*,.pdf">
          <div class="form-text"><?php esc_html_e( 'ارفع صورة أو PDF للإيصال (اختياري لكن يُسرّع المراجعة)', 'examhub' ); ?></div>
        </div>
        <button type="button" id="submit-manual-proof" class="btn btn-primary">
          <i class="bi bi-send me-1"></i><?php esc_html_e( 'إرسال إثبات الدفع', 'examhub' ); ?>
        </button>
      </div>

      <!-- Primary pay button -->
      <button type="button" id="btn-pay-now" class="btn btn-primary btn-lg w-100" style="display:none;">
        <i class="bi bi-credit-card me-1"></i>
        <?php printf( esc_html__( 'ادفع %s جنيه الآن', 'examhub' ), number_format( $amount['total'], 2 ) ); ?>
      </button>

      <?php endif; ?>

      <!-- Status messages -->
      <div id="checkout-status" class="mt-3" style="display:none;"></div>

    </div>

    <!-- Order summary -->
    <div>
      <div class="eh-order-summary">
        <h4 class="mb-4"><?php esc_html_e( 'ملخص الطلب', 'examhub' ); ?></h4>

        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted"><?php esc_html_e( 'الخطة', 'examhub' ); ?></span>
          <strong><?php echo esc_html( $plan['plan_name_ar'] ?: $plan['plan_name'] ); ?></strong>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted"><?php esc_html_e( 'المدة', 'examhub' ); ?></span>
          <span>
            <?php
            $dur = (int)($plan['plan_duration_days']??0);
            echo $dur ? $dur . ' ' . esc_html__( 'يوم', 'examhub' ) : esc_html__( 'مدى الحياة', 'examhub' );
            ?>
          </span>
        </div>

        <?php if ( $plan['plan_description'] ) : ?>
        <p class="text-muted small mt-1"><?php echo esc_html( $plan['plan_description'] ); ?></p>
        <?php endif; ?>

        <hr class="eh-divider">

        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted"><?php esc_html_e( 'السعر الأساسي', 'examhub' ); ?></span>
          <span><?php echo number_format( $amount['base'], 2 ); ?> <?php esc_html_e( 'ج', 'examhub' ); ?></span>
        </div>
        <?php if ( $amount['tax'] > 0 ) : ?>
        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted"><?php esc_html_e( 'الضريبة', 'examhub' ); ?></span>
          <span><?php echo number_format( $amount['tax'], 2 ); ?> <?php esc_html_e( 'ج', 'examhub' ); ?></span>
        </div>
        <?php endif; ?>
        <?php if ( $amount['fee'] > 0 ) : ?>
        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted"><?php esc_html_e( 'رسوم المعالجة', 'examhub' ); ?></span>
          <span><?php echo number_format( $amount['fee'], 2 ); ?> <?php esc_html_e( 'ج', 'examhub' ); ?></span>
        </div>
        <?php endif; ?>

        <hr class="eh-divider">
        <div class="d-flex justify-content-between">
          <strong><?php esc_html_e( 'الإجمالي', 'examhub' ); ?></strong>
          <strong class="text-accent" style="font-size:1.2rem;"><?php echo number_format( $amount['total'], 2 ); ?> <?php esc_html_e( 'جنيه', 'examhub' ); ?></strong>
        </div>

        <hr class="eh-divider">

        <!-- Plan features summary -->
        <ul class="list-unstyled small mb-0">
          <?php if ( ! empty( $plan['plan_unlimited'] ) ) : ?>
            <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i><?php esc_html_e( 'أسئلة غير محدودة', 'examhub' ); ?></li>
          <?php endif; ?>
          <?php if ( ! empty( $plan['plan_ai_access'] ) ) : ?>
            <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i><?php esc_html_e( 'الذكاء الاصطناعي', 'examhub' ); ?></li>
          <?php endif; ?>
          <?php if ( ! empty( $plan['plan_explanation_access'] ) ) : ?>
            <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i><?php esc_html_e( 'شرح تفصيلي لكل إجابة', 'examhub' ); ?></li>
          <?php endif; ?>
          <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i><?php esc_html_e( 'دعم فني متاح', 'examhub' ); ?></li>
          <li><i class="bi bi-check-circle-fill text-success me-2"></i><?php esc_html_e( 'استرداد خلال 7 أيام', 'examhub' ); ?></li>
        </ul>

      </div><!-- .eh-order-summary -->
    </div>

  </div><!-- .eh-checkout-layout -->

</div><!-- .container -->

<script>
(function($){
  const PLAN_ID  = '<?php echo esc_js( $plan_slug ); ?>';
  const NONCE    = '<?php echo wp_create_nonce("examhub_ajax"); ?>';
  const AJAX_URL = '<?php echo admin_url("admin-ajax.php"); ?>';

  let selectedMethod = '<?php echo esc_js( $methods[0]['key'] ?? '' ); ?>';
  let paymentId      = null;

  // Vodafone/manual instructions
  const manualInstructions = {
    vodafone_cash: {
      title: 'Vodafone Cash',
      body : `<p><strong>اسم الحساب:</strong> <?php echo esc_js( get_field('vodafone_cash_name','option') ); ?></p>
              <p><strong>الرقم:</strong> <code style="font-size:1.2rem;"><?php echo esc_js( get_field('vodafone_cash_number','option') ); ?></code></p>
              <p class="text-muted small"><?php echo esc_js( wp_strip_all_tags(get_field('vodafone_cash_instructions','option') ?: 'حوّل المبلغ ثم ارفع الإيصال.') ); ?></p>`,
    },
    bank_transfer: {
      title: 'حوالة بنكية',
      body : `<p><strong>البنك:</strong> <?php echo esc_js( get_field('bank_name','option') ); ?></p>
              <p><strong>رقم الحساب:</strong> <code><?php echo esc_js( get_field('bank_account','option') ); ?></code></p>`,
    },
    instapay: {
      title: 'InstaPay',
      body : `<p><strong>InstaPay ID:</strong> <code style="font-size:1.1rem;"><?php echo esc_js( get_field('instapay_username','option') ); ?></code></p>`,
    },
    wallet: {
      title: 'محفظة إلكترونية',
      body : `<p><strong>رقم المحفظة:</strong> <code style="font-size:1.1rem;"><?php echo esc_js( get_field('wallet_number','option') ); ?></code></p>`,
    },
  };

  // Method selection
  $(document).on('click', '.eh-method-card', function(){
    $('.eh-method-card').removeClass('selected');
    $(this).addClass('selected');
    selectedMethod = $(this).data('method');
    updateUI();
  });

  function updateUI(){
    const isInstant = selectedMethod === 'fawaterk';
    $('#btn-pay-now').show().text(isInstant
      ? `ادفع ${parseFloat('<?php echo $amount["total"]; ?>').toFixed(2)} جنيه الآن`
      : 'عرض تعليمات الدفع'
    );
    if(manualInstructions[selectedMethod]){
      const info = manualInstructions[selectedMethod];
      $('#method-instructions').show().html(`<h5><i class="bi bi-info-circle me-2 text-accent"></i>${info.title}</h5>${info.body}`);
      $('#manual-proof-form').show();
      $('#btn-pay-now').text('عرض بيانات الدفع');
    } else {
      $('#method-instructions').hide();
      $('#manual-proof-form').hide();
    }
  }

  // Initial UI
  updateUI();

  // Pay button
  $('#btn-pay-now').on('click', function(){
    if(selectedMethod === 'fawaterk'){
      initiatePayment();
    } else {
      initiateManualPayment();
    }
  });

  function initiatePayment(){
    const $btn = $('#btn-pay-now').prop('disabled', true).text('جاري الإنشاء...');
    $.post(AJAX_URL, {
      action: 'eh_create_payment',
      nonce: NONCE,
      plan_id: PLAN_ID,
      method: selectedMethod,
    }, function(res){
      if(res.success){
        paymentId = res.data.payment_id;
        if(res.data.type === 'redirect' && res.data.redirect_url){
          showStatus('success', 'جاري التحويل لبوابة الدفع...');
          setTimeout(()=>{ window.location.href = res.data.redirect_url; }, 1000);
        }
      } else {
        showStatus('error', res.data?.message || 'حدث خطأ. حاول مجدداً.');
        $btn.prop('disabled', false).text('ادفع الآن');
      }
    });
  }

  function initiateManualPayment(){
    const $btn = $('#btn-pay-now').prop('disabled', true).text('جاري...');
    $.post(AJAX_URL, {
      action: 'eh_create_payment',
      nonce: NONCE,
      plan_id: PLAN_ID,
      method: selectedMethod,
    }, function(res){
      $btn.prop('disabled', false).text('عرض بيانات الدفع');
      if(res.success){
        paymentId = res.data.payment_id;
        showStatus('info', `رقم الطلب: <strong>EH-${paymentId}</strong> — قم بالتحويل ثم ارفع الإيصال أدناه.`);
      } else {
        showStatus('error', res.data?.message || 'خطأ.');
      }
    });
  }

  // Submit manual proof
  $('#submit-manual-proof').on('click', function(){
    if(!paymentId){ showStatus('error', 'ابدأ بالضغط على زر عرض بيانات الدفع أولاً.'); return; }
    const fd = new FormData();
    fd.append('action',     'eh_vodafone_submit_proof');
    fd.append('nonce',      NONCE);
    fd.append('payment_id', paymentId);
    fd.append('reference',  $('#proof-reference').val());
    fd.append('phone',      $('#proof-phone').val());
    const file = document.getElementById('proof-file').files[0];
    if(file) fd.append('proof', file);

    const $btn = $(this).prop('disabled', true).text('جاري الإرسال...');
    $.ajax({ url: AJAX_URL, type: 'POST', data: fd, processData: false, contentType: false,
      success: function(res){
        if(res.success){
          showStatus('success', '✅ ' + res.data.message);
          setTimeout(()=>{ window.location.href = '<?php echo esc_js(home_url("/subscription")); ?>'; }, 3000);
        } else {
          showStatus('error', res.data?.message || 'خطأ.');
          $btn.prop('disabled', false).text('إرسال إثبات الدفع');
        }
      }
    });
  });

  function showStatus(type, msg){
    const colors = {success:'var(--eh-success-bg)',error:'var(--eh-danger-bg)',info:'var(--eh-info-bg)'};
    const bcolor = {success:'var(--eh-success)',error:'var(--eh-danger)',info:'var(--eh-info)'};
    $('#checkout-status').show().html(`<div style="background:${colors[type]};border:1px solid ${bcolor[type]};border-radius:var(--eh-radius);padding:1rem;">${msg}</div>`);
  }

})(jQuery);
</script>

<?php get_footer(); ?>

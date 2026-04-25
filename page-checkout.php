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
$methods = examhub_get_enabled_payment_methods();
$methods = array_values( array_filter( $methods, static function( $method ) {
    return ( $method['key'] ?? '' ) !== 'wallet';
} ) );
$amount  = examhub_calculate_amount( (float) $plan['plan_price'] );

get_header();
?>

<div class="container-xl py-4">

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>"><?php esc_html_e( 'الاشتراك', 'examhub' ); ?></a></li>
      <li class="breadcrumb-item active"><?php esc_html_e( 'إتمام الدفع', 'examhub' ); ?></li>
    </ol>
  </nav>

  <div class="eh-checkout-layout">

    <div>
      <h2 class="mb-4"><?php esc_html_e( 'طريقة الدفع', 'examhub' ); ?></h2>

      <?php if ( empty( $methods ) ) : ?>
        <div class="alert alert-warning"><?php esc_html_e( 'لا توجد طرق دفع متاحة حالياً.', 'examhub' ); ?></div>
      <?php else : ?>

      <div id="payment-methods-list" class="mb-4">
        <?php foreach ( $methods as $index => $m ) : ?>
        <?php
        if ( 'vodafone_cash' === ( $m['key'] ?? '' ) ) {
            $m['label'] = __( 'محفظة إلكترونية', 'examhub' );
            $m['desc']  = __( 'تحويل على رقم محفظة إلكترونية', 'examhub' );
        }
        ?>
        <div class="eh-method-card <?php echo 0 === $index ? 'selected' : ''; ?>"
             data-method="<?php echo esc_attr( $m['key'] ); ?>"
             data-instant="<?php echo ! empty( $m['instant'] ) ? '1' : '0'; ?>">
          <div class="method-icon"><i class="bi <?php echo esc_attr( $m['icon'] ); ?>"></i></div>
          <div>
            <div class="method-name"><?php echo esc_html( $m['label'] ); ?></div>
            <div class="method-desc"><?php echo esc_html( $m['desc'] ); ?></div>
            <?php if ( ! empty( $m['instant'] ) ) : ?>
              <span class="instant-badge"><i class="bi bi-lightning-fill"></i> <?php esc_html_e( 'تفعيل فوري', 'examhub' ); ?></span>
            <?php else : ?>
              <span class="instant-badge text-warning"><i class="bi bi-clock"></i> <?php esc_html_e( 'مراجعة يدوية', 'examhub' ); ?></span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div id="method-instructions" class="card p-4 mb-4" style="display:none;"></div>

      <div id="manual-proof-form" style="display:none;">
        <div class="eh-proof-steps">
          <div class="eh-proof-step"><span class="eh-proof-step-num">1</span><span><?php esc_html_e( 'قم بعمل التحويل أولاً', 'examhub' ); ?></span></div>
          <div class="eh-proof-step"><span class="eh-proof-step-num">2</span><span><?php esc_html_e( 'ثم املأ البيانات أدناه', 'examhub' ); ?></span></div>
          <div class="eh-proof-step"><span class="eh-proof-step-num">3</span><span><?php esc_html_e( 'أكد الإرسال بعد كتابة البيانات', 'examhub' ); ?></span></div>
        </div>

        <div class="eh-proof-fields">
          <label class="eh-proof-field">
            <span class="eh-proof-label eh-proof-label-help">
              <span><?php esc_html_e( 'رقم المعاملة', 'examhub' ); ?></span>
              <button type="button" class="eh-proof-help-btn" id="transaction-help-btn" aria-expanded="false" aria-controls="transaction-help-tooltip">
                <i class="bi bi-question-circle"></i>
                <span><?php esc_html_e( 'كيفية الحصول عليه', 'examhub' ); ?></span>
              </button>
            </span>
            <span class="eh-proof-input-wrap">
              <i class="bi bi-upc-scan"></i>
              <input type="text" id="proof-reference" class="form-control eh-proof-input" placeholder="<?php esc_attr_e( 'مثال: TXN123456', 'examhub' ); ?>">
            </span>
            <span class="eh-proof-help-tooltip" id="transaction-help-tooltip" hidden>
              <span class="eh-proof-help-tooltip-title"><?php esc_html_e( 'مكان رقم المعاملة', 'examhub' ); ?></span>
              <img src="<?php echo esc_url( EXAMHUB_ASSETS . 'images/transaction-number.webp' ); ?>" alt="<?php esc_attr_e( 'توضيح مكان رقم المعاملة', 'examhub' ); ?>" class="eh-proof-help-image">
            </span>
          </label>

          <label class="eh-proof-field">
            <span class="eh-proof-label"><?php esc_html_e( 'رقم الهاتف المحول منه', 'examhub' ); ?></span>
            <span class="eh-proof-input-wrap">
              <i class="bi bi-phone"></i>
              <input type="tel" id="proof-phone" class="form-control eh-proof-input" value="<?php echo esc_attr( get_user_meta( $user_id, 'billing_phone', true ) ); ?>" placeholder="010xxxxxxxxxx">
            </span>
          </label>
        </div>

      </div>

      <button type="button" id="btn-pay-now" class="btn btn-primary btn-lg w-100" style="display:none;">
        <i class="bi bi-shield-check me-1"></i>
        <?php printf( esc_html__( 'ادفع %s جنيه الآن', 'examhub' ), number_format( $amount['total'], 2 ) ); ?>
      </button>

      <?php endif; ?>

      <div id="checkout-status" class="mt-3" style="display:none;"></div>
    </div>

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
            $dur = (int) ( $plan['plan_duration_days'] ?? 0 );
            echo $dur ? esc_html( $dur . ' ' . __( 'يوم', 'examhub' ) ) : esc_html__( 'مدى الحياة', 'examhub' );
            ?>
          </span>
        </div>

        <?php if ( ! empty( $plan['plan_description'] ) ) : ?>
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

        <ul class="list-unstyled small mb-0">
          <?php if ( ! empty( $plan['plan_unlimited'] ) ) : ?>
            <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i><?php esc_html_e( 'امتحانات غير محدودة', 'examhub' ); ?></li>
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
      </div>
    </div>

  </div>
</div>

<script>
(function($){
  const PLAN_ID = '<?php echo esc_js( $plan_slug ); ?>';
  const NONCE = '<?php echo esc_js( wp_create_nonce( 'examhub_ajax' ) ); ?>';
  const AJAX_URL = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
  const SUBSCRIPTION_URL = '<?php echo esc_js( home_url( '/subscription' ) ); ?>';

  let selectedMethod = '<?php echo esc_js( $methods[0]['key'] ?? '' ); ?>';
  let paymentId = null;

  const manualInstructions = {
    vodafone_cash: {
      title: '<?php echo esc_js( __( 'محفظة إلكترونية', 'examhub' ) ); ?>',
      body: `<div class="eh-proof-instruction-card">
        <div class="eh-proof-instruction-head">
          <span class="eh-proof-instruction-method"><?php echo esc_js( __( 'محفظة إلكترونية', 'examhub' ) ); ?></span>
          <span class="eh-proof-instruction-badge"><?php echo esc_js( __( 'تحويل يدوي آمن', 'examhub' ) ); ?></span>
        </div>
        <div class="eh-proof-instruction-line"><strong><?php echo esc_js( __( 'اسم الحساب', 'examhub' ) ); ?>:</strong> <?php echo esc_js( get_field( 'vodafone_cash_name', 'option' ) ); ?></div>
        <div class="eh-proof-instruction-line"><strong><?php echo esc_js( __( 'الرقم', 'examhub' ) ); ?>:</strong> <code><?php echo esc_js( get_field( 'vodafone_cash_number', 'option' ) ); ?></code></div>
        <div class="eh-proof-instruction-text"><?php echo esc_js( __( 'تحويل على رقم محفظة إلكترونية', 'examhub' ) ); ?></div>
      </div>`
    },
    instapay: {
      title: 'InstaPay',
      body: `<div class="eh-proof-instruction-card">
        <div class="eh-proof-instruction-head">
          <span class="eh-proof-instruction-method">InstaPay</span>
          <span class="eh-proof-instruction-badge"><?php echo esc_js( __( 'تحويل يدوي آمن', 'examhub' ) ); ?></span>
        </div>
        <div class="eh-proof-instruction-line"><strong>InstaPay ID:</strong> <code><?php echo esc_js( get_field( 'instapay_username', 'option' ) ); ?></code></div>
      </div>`
    },
    bank_transfer: {
      title: '<?php echo esc_js( __( 'حوالة بنكية', 'examhub' ) ); ?>',
      body: `<div class="eh-proof-instruction-card">
        <div class="eh-proof-instruction-head">
          <span class="eh-proof-instruction-method"><?php echo esc_js( __( 'حوالة بنكية', 'examhub' ) ); ?></span>
          <span class="eh-proof-instruction-badge"><?php echo esc_js( __( 'تحويل يدوي آمن', 'examhub' ) ); ?></span>
        </div>
        <div class="eh-proof-instruction-line"><strong><?php echo esc_js( __( 'البنك', 'examhub' ) ); ?>:</strong> <?php echo esc_js( get_field( 'bank_name', 'option' ) ); ?></div>
        <div class="eh-proof-instruction-line"><strong><?php echo esc_js( __( 'رقم الحساب', 'examhub' ) ); ?>:</strong> <code><?php echo esc_js( get_field( 'bank_account', 'option' ) ); ?></code></div>
      </div>`
    }
  };

  function isManualMethod() {
    return !!manualInstructions[selectedMethod];
  }

  function getManualAction() {
    return selectedMethod === 'vodafone_cash' ? 'eh_vodafone_submit_proof' : 'eh_manual_submit_proof';
  }

  function validateManualForm() {
    if (!isManualMethod()) {
      return true;
    }

    const hasReference = $.trim($('#proof-reference').val()).length > 0;
    const hasPhone = $.trim($('#proof-phone').val()).length > 0;
    const valid = hasReference && hasPhone;

    $('#btn-pay-now')
      .prop('disabled', !valid)
      .toggleClass('eh-btn-disabled', !valid);

    return valid;
  }

  function updateUI() {
    if (isManualMethod()) {
      const info = manualInstructions[selectedMethod];
      $('#method-instructions').show().html(`<h5><i class="bi bi-stars me-2 text-accent"></i>${info.title}</h5>${info.body}`);
      $('#manual-proof-form').show();
      $('#btn-pay-now')
        .show()
        .html('<i class="bi bi-shield-check me-1"></i><?php echo esc_js( __( 'تأكيد وإرسال الدفع', 'examhub' ) ); ?>')
        .prop('disabled', true)
        .addClass('eh-btn-disabled');
      validateManualForm();
    } else {
      $('#method-instructions').hide().empty();
      $('#manual-proof-form').hide();
      $('#btn-pay-now')
        .show()
        .html(`<i class="bi bi-credit-card me-1"></i><?php echo esc_js( sprintf( __( 'ادفع %s جنيه الآن', 'examhub' ), number_format( $amount['total'], 2 ) ) ); ?>`)
        .prop('disabled', false)
        .removeClass('eh-btn-disabled');
    }
  }

  async function createPayment() {
    return $.post(AJAX_URL, {
      action: 'eh_create_payment',
      nonce: NONCE,
      plan_id: PLAN_ID,
      method: selectedMethod
    });
  }

  async function submitManualProof(currentPaymentId) {
    const fd = new FormData();
    fd.append('action', getManualAction());
    fd.append('nonce', NONCE);
    fd.append('payment_id', currentPaymentId);
    fd.append('reference', $('#proof-reference').val());
    fd.append('phone', $('#proof-phone').val());

    return $.ajax({
      url: AJAX_URL,
      type: 'POST',
      data: fd,
      processData: false,
      contentType: false
    });
  }

  function showStatus(type, msg) {
    const colors = {
      success: 'var(--eh-success-bg)',
      error: 'var(--eh-danger-bg)',
      info: 'rgba(6, 182, 212, 0.14)'
    };
    const borders = {
      success: 'var(--eh-success)',
      error: 'var(--eh-danger)',
      info: 'rgba(6, 182, 212, 0.55)'
    };

    $('#checkout-status')
      .show()
      .html(`<div style="background:${colors[type]};border:1px solid ${borders[type]};border-radius:var(--eh-radius-lg);padding:1rem 1.1rem;">${msg}</div>`);
  }

  $(document).on('click', '.eh-method-card', function() {
    $('.eh-method-card').removeClass('selected');
    $(this).addClass('selected');
    selectedMethod = $(this).data('method');
    paymentId = null;
    updateUI();
  });

  $('#proof-reference, #proof-phone').on('input', validateManualForm);

  $('#transaction-help-btn').on('click', function(e) {
    e.preventDefault();
    const $tooltip = $('#transaction-help-tooltip');
    const willShow = $tooltip.is('[hidden]');
    $tooltip.attr('hidden', !willShow);
    $(this).attr('aria-expanded', willShow ? 'true' : 'false');
  });

  $(document).on('click', function(e) {
    if (!$(e.target).closest('.eh-proof-field').length) {
      $('#transaction-help-tooltip').attr('hidden', true);
      $('#transaction-help-btn').attr('aria-expanded', 'false');
    }
  });

  $('#btn-pay-now').on('click', async function() {
    const $btn = $(this);

    if (isManualMethod()) {
      if (!validateManualForm()) {
        showStatus('error', '<?php echo esc_js( __( 'أكمل رقم المعاملة ورقم الهاتف أولاً.', 'examhub' ) ); ?>');
        return;
      }

      $btn.prop('disabled', true).removeClass('eh-btn-disabled').text('<?php echo esc_js( __( 'جاري تأكيد الدفع...', 'examhub' ) ); ?>');

      try {
        const createRes = await createPayment();
        if (!createRes.success) {
          showStatus('error', createRes.data?.message || '<?php echo esc_js( __( 'حدث خطأ. حاول مجدداً.', 'examhub' ) ); ?>');
          updateUI();
          return;
        }

        paymentId = createRes.data.payment_id;
        const proofRes = await submitManualProof(paymentId);

        if (proofRes.success) {
          showStatus('success', '✅ ' + proofRes.data.message);
          setTimeout(() => { window.location.href = SUBSCRIPTION_URL; }, 2500);
          return;
        }

        showStatus('error', proofRes.data?.message || '<?php echo esc_js( __( 'تعذر إرسال الإثبات.', 'examhub' ) ); ?>');
      } catch (error) {
        showStatus('error', '<?php echo esc_js( __( 'تعذر إكمال العملية الآن. حاول مرة أخرى.', 'examhub' ) ); ?>');
      }

      updateUI();
      return;
    }

    $btn.prop('disabled', true).text('<?php echo esc_js( __( 'جاري الإنشاء...', 'examhub' ) ); ?>');

    try {
      const res = await createPayment();
      if (res.success && res.data.type === 'redirect' && res.data.redirect_url) {
        showStatus('info', '<?php echo esc_js( __( 'جاري التحويل لبوابة الدفع...', 'examhub' ) ); ?>');
        setTimeout(() => { window.location.href = res.data.redirect_url; }, 800);
        return;
      }

      showStatus('error', res.data?.message || '<?php echo esc_js( __( 'حدث خطأ. حاول مجدداً.', 'examhub' ) ); ?>');
    } catch (error) {
      showStatus('error', '<?php echo esc_js( __( 'تعذر بدء عملية الدفع حالياً.', 'examhub' ) ); ?>');
    }

    updateUI();
  });

  updateUI();
})(jQuery);
</script>

<?php get_footer(); ?>

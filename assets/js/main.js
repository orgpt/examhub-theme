/**
 * ExamHub Pro — Main JavaScript
 * Filter bar, paywall, gamification UI, general helpers.
 */
(function ($) {
  'use strict';

  const AJAX = window.examhubAjax || {};
  const INSTALL_PROMPT = window.examhubInstallPrompt || {};
  let deferredInstallPrompt = null;

  // ─── Document Ready ──────────────────────────────────────────────────────────
  $(function () {
    initFilterBar();
    initPaywall();
    initDailyReward();
    initTooltips();
    initMobileNav();
    initExamCards();
    initAITutor();
    initLazyLoad();
    initInvoiceHistory();
    initSubscriptionActions();
    initInstallPrompt();
  });

  // ═══════════════════════════════════════════════════════════════════════════
  // CASCADING FILTER BAR
  // ═══════════════════════════════════════════════════════════════════════════

  function initFilterBar() {
    const $filter = $('#eh-filter-bar');
    if (!$filter.length) return;

    const selectors = {
      system:  '#filter-system',
      stage:   '#filter-stage',
      grade:   '#filter-grade',
      year:    '#filter-year',
      subject: '#filter-subject',
    };

    // System → Stage
    $(document).on('change', selectors.system, function () {
      const sysId = $(this).val();
      resetSelects(['stage', 'grade', 'subject'], selectors);
      if (!sysId) return filterExams();
      loadOptions('eh_get_stages', { system_id: sysId }, selectors.stage, filterExams);
    });

    // Stage → Grade
    $(document).on('change', selectors.stage, function () {
      const stageId = $(this).val();
      resetSelects(['grade', 'subject'], selectors);
      if (!stageId) return filterExams();
      loadOptions('eh_get_grades', { stage_id: stageId }, selectors.grade, filterExams);
    });

    // Grade → Subject
    $(document).on('change', selectors.grade, function () {
      const gradeId = $(this).val();
      resetSelects(['subject'], selectors);
      filterExams();
      if (!gradeId) return;
      loadOptions('eh_get_subjects', { grade_id: gradeId }, selectors.subject);
    });

    // Subject
    $(document).on('change', selectors.subject, function () {
      filterExams();
    });

    $(document).on('change', [selectors.year, '#filter-difficulty'].join(','), filterExams);

    // Load more
    $(document).on('click', '#eh-load-more-exams', function () {
      const $btn  = $(this);
      const paged = parseInt($btn.data('paged')) + 1;
      filterExams(paged, true);
      $btn.data('paged', paged);
    });
  }

  function loadOptions(action, data, target, callback) {
    const $sel = $(target);
    $sel.html('<option value="">جاري التحميل...</option>').prop('disabled', true);

    $.post(AJAX.url, Object.assign({ action, nonce: AJAX.nonce }, data), function (res) {
      $sel.prop('disabled', false);
      if (res.success && res.data.length) {
        const placeholder = $sel.data('placeholder') || '— اختر —';
        let opts = `<option value="">${placeholder}</option>`;
        res.data.forEach(item => {
          opts += `<option value="${item.id}">${escHtml(item.name)}</option>`;
        });
        $sel.html(opts);
      } else {
        $sel.html('<option value="">— لا يوجد —</option>');
      }
      if (callback) callback();
    });
  }

  function resetSelects(keys, selectors) {
    keys.forEach(k => resetSelect(selectors[k]));
  }

  function resetSelect(selector) {
    const $sel = $(selector);
    const placeholder = $sel.data('placeholder') || '— اختر —';
    $sel.html(`<option value="">${placeholder}</option>`).prop('disabled', false);
  }

  let filterTimer = null;
  function filterExams(paged = 1, append = false) {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(function () {
      const $grid = $('#eh-exams-grid');
      if (!$grid.length) return;

      const params = {
        action:     'eh_filter_exams',
        nonce:      AJAX.nonce,
        system_id:  $('#filter-system').val()     || 0,
        stage_id:   $('#filter-stage').val()      || 0,
        grade_id:   $('#filter-grade').val()      || 0,
        subject_id: $('#filter-subject').val()    || 0,
        difficulty: $('#filter-difficulty').val() || '',
        paged,
      };

      if (!append) {
        $grid.addClass('opacity-50');
        $('#eh-exams-loading').show();
      }

      $.post(AJAX.url, params, function (res) {
        $grid.removeClass('opacity-50');
        $('#eh-exams-loading').hide();

        if (res.success) {
          if (append) {
            $grid.append(res.data.html);
          } else {
            $grid.html(res.data.html);
          }

          // Update load more button
          const $more = $('#eh-load-more-exams');
          if (paged >= res.data.max_pages) {
            $more.hide();
          } else {
            $more.show().data('paged', paged);
          }

          // Update count
          $('#eh-exams-count').text(res.data.found);
        }
      });
    }, 300);
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // PAYWALL
  // ═══════════════════════════════════════════════════════════════════════════

  function initPaywall() {
    // Show paywall when triggered
    $(document).on('click', '[data-paywall]', function (e) {
      e.preventDefault();
      const context = $(this).data('paywall') || 'question_limit';
      showPaywall(context);
    });

    // Close paywall
    $(document).on('click', '#eh-paywall-close, #eh-paywall-modal', function (e) {
      if (e.target === this) {
        $('#eh-paywall-modal').fadeOut(200);
      }
    });

    // Check limit on page load for exam pages
    if ($('#eh-exam-app').length && AJAX.is_logged_in) {
      checkQuestionLimit();
    }
  }

  window.showPaywall = function (context) {
    const $modal = $('#eh-paywall-modal');
    if (!$modal.length) return;

    const messages = {
      question_limit: `لقد استخدمت الحد اليومي المجاني (${AJAX.i18n?.free_exam_limit || 1} امتحان). اشترك للمتابعة.`,
      subscription_required: 'هذا الامتحان للمشتركين فقط.',
      ai_required: 'الذكاء الاصطناعي متاح للمشتركين المميزين.',
    };

    $('#paywall-message').text(messages[context] || messages.question_limit);
    $modal.fadeIn(200);
  };

  function checkQuestionLimit() {
    $.post(AJAX.url, { action: 'eh_check_limit', nonce: AJAX.nonce }, function (res) {
      if (res.success && !res.data.can_access) {
        showPaywall('question_limit');
      }
    });
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // DAILY REWARD
  // ═══════════════════════════════════════════════════════════════════════════

  function initDailyReward() {
    $(document).on('click', '#btn-claim-daily-reward', function () {
      const $btn = $(this).prop('disabled', true);
      $.post(AJAX.url, { action: 'eh_claim_daily_reward', nonce: AJAX.nonce }, function (res) {
        if (res.success) {
          showToast(`+${res.data.xp_earned} XP 🎉 مكافأة يومية!`, 'success');
          $btn.text('تم الاستلام ✓');
          // Update XP display
          const $xpEl = $('.eh-xp-badge, #user-xp-display');
          $xpEl.each(function () {
            const cur = parseInt($(this).text().replace(/[^0-9]/g, ''));
            $(this).text((cur + res.data.xp_earned).toLocaleString('ar-EG') + ' XP');
          });
        } else {
          showToast(res.data?.message || 'تم الاستلام مسبقاً', 'info');
          $btn.prop('disabled', false);
        }
      });
    });
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // AI TUTOR
  // ═══════════════════════════════════════════════════════════════════════════

  function initAITutor() {
    $(document).on('click', '#btn-ai-tutor', function () {
      const qId     = $(this).data('question-id');
      const $box    = $('#ai-tutor-box');
      const $resp   = $('#ai-tutor-response');
      const $loader = $('#ai-tutor-loading');

      $box.show();
      $resp.hide();
      $loader.show();

      $.post(AJAX.url, {
        action:      'eh_ai_tutor',
        nonce:       AJAX.nonce,
        question_id: qId,
        user_question: '',
      }, function (res) {
        $loader.hide();
        if (res.success) {
          $resp.show().html(marked.parse ? marked.parse(res.data.response) : res.data.response);
        } else if (res.data?.upgrade) {
          $resp.show().html(`<div class="alert-warning p-3 rounded">${escHtml(res.data.message)} <a href="/pricing">اشترك الآن</a></div>`);
        } else {
          $resp.show().text(res.data?.message || 'حدث خطأ.');
        }
      });
    });

    // Follow-up question
    $(document).on('keydown', '#ai-followup-input', function (e) {
      if (e.key !== 'Enter' || e.shiftKey) return;
      e.preventDefault();
      const qId      = $('#btn-ai-tutor').data('question-id');
      const question = $(this).val().trim();
      if (!question) return;
      $(this).val('');
      $('#ai-tutor-loading').show();
      $('#ai-tutor-response').hide();

      $.post(AJAX.url, {
        action:        'eh_ai_tutor',
        nonce:         AJAX.nonce,
        question_id:   qId,
        user_question: question,
      }, function (res) {
        $('#ai-tutor-loading').hide();
        if (res.success) {
          $('#ai-tutor-response').show().html(marked.parse ? marked.parse(res.data.response) : res.data.response);
        }
      });
    });
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // EXAM CARDS
  // ═══════════════════════════════════════════════════════════════════════════

  function initExamCards() {
    // Hover effect on exam cards is CSS-only; add any JS interactions here
    $(document).on('click', '.eh-exam-card.locked', function (e) {
      e.preventDefault();
      showPaywall('subscription_required');
    });
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // SUBSCRIPTION ACTIONS
  // ═══════════════════════════════════════════════════════════════════════════

  function initSubscriptionActions() {
    $(document).on('click', '#btn-cancel-subscription', function () {
      if (!confirm('هل أنت متأكد من إلغاء الاشتراك؟')) return;
      const $btn = $(this).prop('disabled', true).text('جاري...');
      $.post(AJAX.url, { action: 'eh_cancel_subscription', nonce: AJAX.nonce }, function (res) {
        if (res.success) {
          showToast(res.data.message, 'success');
          setTimeout(() => location.reload(), 2000);
        } else {
          $btn.prop('disabled', false).text('إلغاء الاشتراك');
          showToast(res.data?.message || 'خطأ.', 'error');
        }
      });
    });

    // Invoice history
    $(document).on('click', '#btn-load-invoices', function () {
      $.post(AJAX.url, { action: 'eh_get_invoice_history', nonce: AJAX.nonce }, function (res) {
        if (!res.success || !res.data.history.length) {
          $('#invoices-container').html('<p class="text-muted">لا توجد فواتير.</p>');
          return;
        }
        let html = '<div class="table-responsive"><table class="table"><thead><tr><th>التاريخ</th><th>الخطة</th><th>المبلغ</th><th>الحالة</th><th></th></tr></thead><tbody>';
        res.data.history.forEach(inv => {
          html += `<tr>
            <td>${escHtml(inv.date)}</td>
            <td>${escHtml(inv.plan)}</td>
            <td>${parseFloat(inv.amount).toFixed(2)} ج</td>
            <td>${escHtml(inv.status)}</td>
            <td>${inv.invoice_url ? `<a href="${escHtml(inv.invoice_url)}" target="_blank" class="btn btn-sm btn-ghost">📄</a>` : ''}</td>
          </tr>`;
        });
        html += '</tbody></table></div>';
        $('#invoices-container').html(html);
      });
    });
  }

  function initInvoiceHistory() {
    // Auto-trigger if the container is visible
    if ($('#invoices-container').length && $('#btn-load-invoices').length === 0) {
      $('#btn-load-invoices').trigger('click');
    }
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // LAZY LOAD (Intersection Observer)
  // ═══════════════════════════════════════════════════════════════════════════

  function initLazyLoad() {
    if (!('IntersectionObserver' in window)) return;

    const observer = new IntersectionObserver(function (entries) {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const img = entry.target;
          if (img.dataset.src) {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
          }
          observer.unobserve(img);
        }
      });
    }, { rootMargin: '200px 0px' });

    document.querySelectorAll('img[data-src]').forEach(img => observer.observe(img));
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // MOBILE NAV
  // ═══════════════════════════════════════════════════════════════════════════

  function initMobileNav() {
    // Highlight active mobile nav item
    const path = window.location.pathname;
    $('.eh-mobile-nav-item').each(function () {
      const href = $(this).attr('href');
      if (href && path.indexOf(href) !== -1 && href !== '/') {
        $(this).addClass('active');
      }
    });
  }

  // INSTALL PROMPT
  function initInstallPrompt() {
    const promptEl = document.getElementById('eh-install-prompt');
    if (!promptEl || !isMobileOrTablet() || isStandaloneMode()) return;

    const actionButton = document.getElementById('eh-install-prompt-action');
    const dismissButton = document.getElementById('eh-install-prompt-dismiss');
    const closeButton = document.getElementById('eh-install-prompt-close');
    const guideEl = document.getElementById('eh-install-prompt-guide');

    const showPrompt = function () {
      promptEl.hidden = false;
      promptEl.classList.add('is-visible');
    };

    const hidePrompt = function () {
      promptEl.classList.remove('is-visible');
      window.setTimeout(function () {
        if (!promptEl.classList.contains('is-visible')) {
          promptEl.hidden = true;
        }
      }, 180);
    };

    const showGuide = function (title, message) {
      if (!guideEl) return;

      guideEl.hidden = false;
      guideEl.innerHTML = `
        <strong>${escHtml(title)}</strong>
        <span>${escHtml(message)}</span>
      `;
      showPrompt();
    };

    window.addEventListener('beforeinstallprompt', function (event) {
      event.preventDefault();
      deferredInstallPrompt = event;
      showPrompt();
    });

    window.addEventListener('appinstalled', function () {
      hidePrompt();
    });

    actionButton?.addEventListener('click', async function () {
      if (deferredInstallPrompt) {
        deferredInstallPrompt.prompt();

        try {
          await deferredInstallPrompt.userChoice;
        } catch (error) {
          // Ignore cancellation and keep the fallback notice available.
        }

        deferredInstallPrompt = null;
        return;
      }

      if (isIosDevice()) {
        showGuide(
          INSTALL_PROMPT.i18n?.unsupportedTitle || 'How to add it',
          INSTALL_PROMPT.i18n?.iosInstructions || 'In Safari, tap the Share button, then choose Add to Home Screen.'
        );
        return;
      }

      if (isAndroidDevice()) {
        showGuide(
          INSTALL_PROMPT.i18n?.unsupportedTitle || 'How to add it',
          INSTALL_PROMPT.i18n?.androidInstructions || 'From your browser menu, choose Install app or Add to Home screen.'
        );
        return;
      }

      showGuide(
        INSTALL_PROMPT.i18n?.unsupportedTitle || 'How to add it',
        INSTALL_PROMPT.i18n?.genericInstructions || 'Use your browser menu and choose Add to Home screen to save the shortcut.'
      );
    });

    [dismissButton, closeButton].forEach(function (button) {
      button?.addEventListener('click', hidePrompt);
    });

    window.setTimeout(showPrompt, 1200);
  }

  function isMobileOrTablet() {
    return window.matchMedia('(max-width: 1024px)').matches || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Tablet/i.test(navigator.userAgent);
  }

  function isStandaloneMode() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  }

  function isIosDevice() {
    return /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  }

  function isAndroidDevice() {
    return /Android/i.test(navigator.userAgent);
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // BOOTSTRAP TOOLTIPS
  // ═══════════════════════════════════════════════════════════════════════════

  function initTooltips() {
    const tooltipEls = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipEls.forEach(el => {
      if (typeof bootstrap !== 'undefined') new bootstrap.Tooltip(el);
    });
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // TOAST NOTIFICATIONS
  // ═══════════════════════════════════════════════════════════════════════════

  window.showToast = function (message, type = 'info') {
    const colors = { success: 'var(--eh-success)', error: 'var(--eh-danger)', warning: 'var(--eh-warning)', info: 'var(--eh-info)' };
    const color  = colors[type] || colors.info;
    const id     = 'toast-' + Date.now();

    const $toast = $(`
      <div id="${id}" style="
        position:fixed;bottom:80px;left:50%;transform:translateX(-50%);
        background:var(--eh-bg-card);border:1px solid ${color};color:var(--eh-text-primary);
        border-radius:var(--eh-radius-lg);padding:.75rem 1.5rem;z-index:9999;
        box-shadow:var(--eh-shadow-lg);font-size:.9rem;font-weight:500;
        animation:slideUp .2s ease;max-width:90vw;text-align:center;">
        ${escHtml(message)}
      </div>
    `);

    $('body').append($toast);
    setTimeout(() => $toast.fadeOut(300, () => $toast.remove()), 3500);
  };

  // ═══════════════════════════════════════════════════════════════════════════
  // HELPERS
  // ═══════════════════════════════════════════════════════════════════════════

  function escHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

})(jQuery);

// CSS for toast animation
const _style = document.createElement('style');
_style.textContent = '@keyframes slideUp { from { opacity:0; transform:translateX(-50%) translateY(20px); } to { opacity:1; transform:translateX(-50%) translateY(0); } }';
document.head.appendChild(_style);

(function($) {
  'use strict';

  const T = window.examhubExamLtrUi || {};
  let currentQuestionMeta = null;

  function text(key) {
    return T[key] || '';
  }

  function format(template, ...values) {
    return String(template || '')
      .replace(/%(\d+)\$s/g, (_, index) => values[Number(index) - 1] ?? '')
      .replace(/%s/g, () => values.shift() ?? '');
  }

  function setText(selector, value) {
    if (!value) return;
    const $el = $(selector);
    if ($el.length) {
      $el.text(value);
    }
  }

  function replaceTrailingText($el, value) {
    if (!$el.length || !value) return;
    const textNodes = $el.contents().filter(function() {
      return this.nodeType === 3;
    });

    if (textNodes.length) {
      textNodes.each(function() {
        this.textContent = ' ' + value;
      });
    } else {
      $el.append(document.createTextNode(' ' + value));
    }
  }

  function typeLabel(type) {
    const labels = {
      mcq: text('type_mcq'),
      correct: text('type_correct'),
      true_false: text('type_true_false'),
      fill_blank: text('type_fill_blank'),
      matching: text('type_matching'),
      ordering: text('type_ordering'),
      essay: text('type_essay'),
      image: text('type_image'),
      math: text('type_math')
    };

    return labels[type] || type;
  }

  function diffLabel(diff) {
    const labels = {
      easy: text('diff_easy'),
      medium: text('diff_medium'),
      hard: text('diff_hard')
    };

    return labels[diff] || diff;
  }

  function translateStaticUi() {
    setText('#review-label', text('review'));
    setText('#btn-prev .d-none.d-sm-inline', text('previous'));
    setText('#btn-next .d-none.d-sm-inline', text('next'));
    setText('#btn-submit-exam .d-none.d-sm-inline', text('submit'));
    setText('#q-navigator .eh-q-nav-header span:first', text('question_nav'));
    setText('#autosave-indicator span', text('saved'));
    setText('#submit-modal h4', text('submit_exam'));
    setText('#btn-cancel-submit', text('review_action'));
    setText('#submitting-overlay h5', text('grading'));

    replaceTrailingText($('#btn-skip'), text('skip'));
    replaceTrailingText($('#btn-confirm-submit'), text('final_submit'));
    replaceTrailingText($('#submit-modal-review-warn'), text('review_warning'));

    const $legend = $('#q-navigator .eh-q-nav-legend');
    if ($legend.length && !$legend.data('ltrTranslated')) {
      $legend.html(
        `<span class="eh-dot answered"></span> ${text('answered')}
         <span class="eh-dot current ms-3"></span> ${text('current')}
         <span class="eh-dot review ms-3"></span> ${text('review')}
         <span class="eh-dot unanswered ms-3"></span> ${text('unanswered')}`
      );
      $legend.data('ltrTranslated', true);
    }
  }

  function translateDynamicUi() {
    const $typeBadge = $('#q-type-badge');
    if ($typeBadge.length && currentQuestionMeta?.type) {
      $typeBadge.text(typeLabel(currentQuestionMeta.type));
    }

    const $points = $('#q-points-badge');
    if ($points.length) {
      const match = $points.text().trim().match(/^(\d+)/);
      if (match) {
        const count = Number(match[1]);
        $points.text(`${count} ${count === 1 ? text('point_singular') : text('point_plural')}`);
      }
    }

    const $diff = $('#q-diff-badge');
    if ($diff.length && currentQuestionMeta?.difficulty) {
      $diff.text(diffLabel(currentQuestionMeta.difficulty));
    }

    $('.eh-tf-btn').each(function() {
      const value = $(this).data('val');
      $(this).text(value === 'true' ? text('true_label') : text('false_label'));
    });

    $('.eh-match-right select').each(function() {
      const $firstOption = $(this).find('option').first();
      if ($firstOption.length) {
        $firstOption.text(text('matching_placeholder'));
      }
    });

    $('.eh-essay-textarea').attr('placeholder', text('essay_placeholder'));

    $('.eh-essay-word-count').each(function() {
      const match = $(this).text().trim().match(/^(\d+)/);
      const count = match ? Number(match[1]) : 0;
      $(this).text(`${count} ${text('word_count_unit')}`);
    });

    const current = Number($('#q-current').text()) || 0;
    const total = Number($('#q-total').text()) || 0;
    if (total > 0) {
      $('#btn-next').html(
        current >= total
          ? `<i class="bi bi-check-circle me-1"></i>${text('submit')}`
          : `<span class="d-none d-sm-inline">${text('next')}</span><i class="bi bi-chevron-left"></i>`
      );
    }

    $('#q-dots .eh-q-dot').each(function(index) {
      $(this).attr('title', `${text('question_label')} ${index + 1}`);
    });

    const answeredCount = Number($('#answered-count').text()) || 0;
    if ($('#submit-modal').is(':visible')) {
      $('#submit-modal-answered').text(format(text('submit_modal_answered'), answeredCount, total));

      const unanswered = Math.max(0, total - answeredCount);
      if (unanswered > 0) {
        $('#submit-modal-unanswered').text(format(text('submit_modal_unanswered'), unanswered)).show();
      } else {
        $('#submit-modal-unanswered').hide();
      }
    }

    $('#exam-main a.btn.btn-ghost.mt-3').text(text('back'));
  }

  function bindExitOverride() {
    $(document).off('click.examhubLtrExit', '#btn-exam-exit');
    $(document).on('click.examhubLtrExit', '#btn-exam-exit', function(e) {
      e.preventDefault();
      e.stopImmediatePropagation();

      if (window.confirm(text('exit_confirm'))) {
        window.location = window.examhubConfig?.exam_url || '/';
      }
    });
  }

  function bindAjaxHooks() {
    $(document).ajaxSuccess(function(_event, _xhr, settings, response) {
      if (!settings?.data) return;

      const payload = String(settings.data);
      if (payload.includes('action=eh_load_question') && response?.data) {
        currentQuestionMeta = {
          type: response.data.type || '',
          difficulty: response.data.difficulty || ''
        };
      }

      window.setTimeout(translateDynamicUi, 0);
    });
  }

  function startObserver() {
    const target = document.getElementById('eh-exam-app');
    if (!target) return;

    const observer = new MutationObserver(function() {
      translateStaticUi();
      translateDynamicUi();
    });

    observer.observe(target, {
      childList: true,
      subtree: true,
      characterData: true
    });
  }

  $(function() {
    if (!$('#eh-exam-app').length) return;

    translateStaticUi();
    translateDynamicUi();
    bindExitOverride();
    bindAjaxHooks();
    startObserver();
  });
})(jQuery);

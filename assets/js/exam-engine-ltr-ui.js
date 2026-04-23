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
    $('#review-label').text(text('review'));
    $('#q-navigator .eh-q-nav-header span').first().text(text('question_nav'));
    $('#autosave-indicator span').text(text('saved'));
    $('#submit-modal h4').text(text('submit_exam'));
    $('#btn-cancel-submit').text(text('review_action'));
    $('#submitting-overlay h5').text(text('grading'));

    const $legend = $('#q-navigator .eh-q-nav-legend');
    if ($legend.length) {
      $legend.html(
        `<span class="eh-dot answered"></span> ${text('answered')}
         <span class="eh-dot current ms-3"></span> ${text('current')}
         <span class="eh-dot review ms-3"></span> ${text('review')}
         <span class="eh-dot unanswered ms-3"></span> ${text('unanswered')}`
      );
    }

    const $reviewWarn = $('#submit-modal-review-warn');
    if ($reviewWarn.length) {
      $reviewWarn.html(`<i class="bi bi-flag me-1"></i>${text('review_warning')}`);
    }

    const $confirm = $('#btn-confirm-submit');
    if ($confirm.length) {
      $confirm.html(`<i class="bi bi-check-circle me-1"></i>${text('final_submit')}`);
    }
  }

  function translateDynamicUi() {
    if (currentQuestionMeta?.type) {
      $('#q-type-badge').text(typeLabel(currentQuestionMeta.type));
    }

    if (currentQuestionMeta?.difficulty) {
      $('#q-diff-badge').text(diffLabel(currentQuestionMeta.difficulty));
    }

    const $points = $('#q-points-badge');
    const pointMatch = $points.text().trim().match(/^(\d+)/);
    if ($points.length && pointMatch) {
      const count = Number(pointMatch[1]);
      $points.text(`${count} ${count === 1 ? text('point_singular') : text('point_plural')}`);
    }

    $('.eh-tf-btn').each(function() {
      const value = $(this).data('val');
      $(this).text(value === 'true' ? text('true_label') : text('false_label'));
    });

    $('.eh-match-right select').each(function() {
      $(this).find('option').first().text(text('matching_placeholder'));
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
          : `<span class="d-none d-sm-inline">${text('next')}</span><i class="bi bi-chevron-right"></i>`
      );
    }

    $('#q-dots .eh-q-dot').each(function(index) {
      $(this).attr('title', `${text('question_label')} ${index + 1}`);
    });

    if ($('#submit-modal').is(':visible')) {
      const answeredCount = Number($('#answered-count').text()) || 0;
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
    $('#btn-exam-exit').off('click.examhubLtrUi').on('click.examhubLtrUi', function(e) {
      e.preventDefault();
      e.stopImmediatePropagation();

      if (window.confirm(text('exit_confirm'))) {
        window.location = window.examhubConfig?.exam_url || '/';
      }
    });
  }

  function bindHooks() {
    $(document).ajaxSuccess(function(_event, _xhr, settings, response) {
      const payload = String(settings?.data || '');

      if (payload.includes('action=eh_load_question') && response?.data) {
        currentQuestionMeta = {
          type: response.data.type || '',
          difficulty: response.data.difficulty || ''
        };
      }

      window.setTimeout(translateDynamicUi, 0);
    });

    $('#btn-submit-exam, #btn-next, #btn-skip, #btn-q-nav-toggle').on('click.examhubLtrUi', function() {
      window.setTimeout(translateDynamicUi, 0);
    });
  }

  $(function() {
    if (!$('#eh-exam-app').length) return;

    translateStaticUi();
    translateDynamicUi();
    bindExitOverride();
    bindHooks();
  });
})(jQuery);

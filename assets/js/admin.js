/**
 * ExamHub — Admin JavaScript
 * AI generate buttons, question bank tools, payment management.
 */
(function ($) {
  'use strict';

  const Admin = window.examhubAdmin || {};

  $(function () {
    initAIButtons();
    initBulkQuestionTools();
    initPaymentActions();
    initAcfDependencies();
  });

  // ─── AI Generate Explanation ─────────────────────────────────────────────────
  function initAIButtons() {

    // Generate explanation button (on question edit screen)
    if ($('#acf-field_q_explanation').length) {
      const $btn = $('<button type="button" class="button" id="eh-gen-explanation" style="margin-top:6px;"><span>🤖 توليد الشرح تلقائياً</span></button>');
      $('#acf-field_q_explanation').closest('.acf-field').append($btn);

      $btn.on('click', function () {
        const postId = $('#post_ID').val();
        if (!postId) { alert('احفظ السؤال أولاً.'); return; }
        $(this).prop('disabled', true).find('span').text('جاري التوليد...');

        $.post(Admin.ajax_url, {
          action:      'eh_ai_generate_explanation',
          nonce:       Admin.nonce,
          question_id: postId,
          save:        1,
        }, function (res) {
          $('#eh-gen-explanation').prop('disabled', false).find('span').text('🤖 توليد الشرح تلقائياً');
          if (res.success) {
            // Insert into ACF wysiwyg
            const iframe = document.querySelector('#acf-field_q_explanation_ifr');
            if (iframe) {
              iframe.contentDocument.body.innerHTML = res.data.explanation;
            }
            alert('✅ تم توليد الشرح وحفظه!');
          } else {
            alert('خطأ: ' + (res.data?.message || 'فشل التوليد.'));
          }
        });
      });
    }

    // Generate similar questions
    if ($('#post').length && $('body').hasClass('post-type-eh_question')) {
      const $meta = $('<div style="padding:12px;background:#f9f9f9;border:1px solid #ddd;margin:12px 0;border-radius:4px;">'
        + '<h4 style="margin:0 0 8px;">🔁 توليد أسئلة مشابهة</h4>'
        + '<input type="number" id="eh-similar-count" value="3" min="1" max="5" style="width:60px;margin-left:8px;"> '
        + '<button type="button" class="button" id="eh-gen-similar">توليد</button>'
        + '<div id="eh-similar-results" style="margin-top:12px;"></div>'
        + '</div>');

      $('#submitdiv').before($meta);

      $('#eh-gen-similar').on('click', function () {
        const postId = $('#post_ID').val();
        const count  = $('#eh-similar-count').val();
        $(this).prop('disabled', true).text('جاري...');

        $.post(Admin.ajax_url, {
          action:      'eh_ai_generate_similar',
          nonce:       Admin.nonce,
          question_id: postId,
          count:       count,
        }, function (res) {
          $('#eh-gen-similar').prop('disabled', false).text('توليد');
          if (res.success) {
            let html = `<p><strong>${res.data.questions.length} أسئلة مشابهة:</strong></p>`;
            res.data.questions.forEach((q, i) => {
              html += `<div style="border:1px solid #ddd;border-radius:3px;padding:8px;margin:4px 0;background:#fff;">
                <strong>${i + 1}. </strong>${q.question_text}<br>
                <small style="color:#666;">${(q.answers||[]).map((a,j)=>`${j+1}.${a.answer_text||a}`).join(' | ')}</small>
              </div>`;
            });
            html += `<p><small>يمكنك نسخ هذه الأسئلة يدوياً أو نقلها إلى بنك الأسئلة.</small></p>`;
            $('#eh-similar-results').html(html);
          } else {
            alert('خطأ: ' + (res.data?.message || ''));
          }
        });
      });
    }
  }

  // ─── Bulk Question Tools ──────────────────────────────────────────────────────
  function initBulkQuestionTools() {
    // Add bulk action: "Mark as reviewed"
    $('<option value="eh_bulk_publish">✅ نشر المحدد</option>').appendTo('select[name="action"], select[name="action2"]');

    $(document).on('submit', '#posts-filter', function (e) {
      const action = $('select[name="action"]').val() || $('select[name="action2"]').val();
      if (action !== 'eh_bulk_publish') return;

      e.preventDefault();
      const ids = $('input[name="post[]"]:checked').map(function () { return $(this).val(); }).get();

      if (!ids.length) { alert('حدد أسئلة أولاً.'); return; }
      if (!confirm(`هل تريد نشر ${ids.length} سؤال؟`)) return;

      let done = 0;
      ids.forEach(id => {
        $.post(Admin.ajax_url, {
          action:  'eh_admin_publish_question',
          nonce:   Admin.nonce,
          post_id: id,
        }, function () {
          done++;
          if (done === ids.length) location.reload();
        });
      });
    });
  }

  // ─── Payment Actions ──────────────────────────────────────────────────────────
  function initPaymentActions() {
    // Already handled inline in admin-columns.php
    // This is a fallback for any additional payment UI
  }

  function initAcfDependencies() {
    if (typeof window.acf === 'undefined' || typeof window.acf.addFilter !== 'function') {
      return;
    }

    const keyToName = {
      field_ex_grade: 'grade_id',
      field_ex_subject: 'subject_id',
      field_ex_lesson: 'lesson_id',
      field_q_grade: 'grade_id',
      field_q_subject: 'subject_id',
      field_q_lesson: 'lesson_id',
    };

    function getFieldValue(fieldKey) {
      const $field = $('.acf-field[data-key="' + fieldKey + '"]');
      if (!$field.length) return 0;
      const $input = $field.find('select, input[type="hidden"], input[type="text"]').first();
      if (!$input.length) return 0;
      return parseInt($input.val(), 10) || 0;
    }

    function clearField(fieldKey) {
      const $field = $('.acf-field[data-key="' + fieldKey + '"]');
      if (!$field.length) return;
      const $input = $field.find('select').first();
      if ($input.length) {
        $input.val('').trigger('change');
      }
      const $hidden = $field.find('input[type="hidden"]').first();
      if ($hidden.length) {
        $hidden.val('');
      }
    }

    $(document).on('change', '.acf-field[data-key="field_ex_grade"] select', function () {
      clearField('field_ex_subject');
      clearField('field_ex_lesson');
    });

    $(document).on('change', '.acf-field[data-key="field_ex_subject"] select', function () {
      clearField('field_ex_lesson');
    });

    $(document).on('change', '.acf-field[data-key="field_q_grade"] select', function () {
      clearField('field_q_subject');
      clearField('field_q_lesson');
    });

    $(document).on('change', '.acf-field[data-key="field_q_subject"] select', function () {
      clearField('field_q_lesson');
    });

    window.acf.addFilter('select2_ajax_data', function (data, args, $input, field) {
      if (!field || typeof field.get !== 'function') {
        return data;
      }

      const key = field.get('key');
      if (!keyToName[key]) {
        return data;
      }

      if (key === 'field_ex_subject') {
        data.grade_id = getFieldValue('field_ex_grade');
      } else if (key === 'field_ex_lesson') {
        data.subject_id = getFieldValue('field_ex_subject');
      } else if (key === 'field_q_subject') {
        data.grade_id = getFieldValue('field_q_grade');
      } else if (key === 'field_q_lesson') {
        data.subject_id = getFieldValue('field_q_subject');
      } else if (key === 'field_ex_questions') {
        data.grade_id = getFieldValue('field_ex_grade');
        data.subject_id = getFieldValue('field_ex_subject');
        data.lesson_id = getFieldValue('field_ex_lesson');
      }

      return data;
    });
  }

})(jQuery);

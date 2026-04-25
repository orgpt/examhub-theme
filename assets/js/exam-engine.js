/**
 * ExamHub — Exam Engine
 * Handles: session init/resume, question rendering (all types),
 * timer (exam + per-question), autosave, navigation, submit.
 */
(function($) {
  'use strict';

  // ─── Config & State ─────────────────────────────────────────────────────────
  const C = window.examhubConfig || {};
  const AJAX = window.examhubAjax || {};
  const I18N = C.i18n || {};

  const state = {
    resultId:      null,
    questions:     [],      // Array of question IDs
    current:       0,       // Current question index (0-based)
    answers:       {},      // { q_id: value }
    reviewList:    [],      // [q_id, ...]
    answered:      new Set(),
    totalSecs:     C.duration_seconds || 0,
    timeRemaining: C.duration_seconds || 0,
    qTimeRemaining: C.sec_per_question || 60,
    timerInterval: null,
    qTimerInterval: null,
    autosaveTimer: null,
    lastSaved:     null,
    submitted:     false,
    started_at:    null,
    resuming:      false,
  };

  // Letter labels for MCQ
  const LETTERS = ['أ','ب','ج','د','هـ','و'];

  // ─── Init ───────────────────────────────────────────────────────────────────
  $(function() {
    if (!$('#eh-exam-app').length) return;
    bindAntiCopyGuards();
    initExam();
    bindUI();
  });

  async function initExam() {
    showLoading(true);
    try {
      const res = await ajaxPost('eh_start_exam', { exam_id: C.exam_id });
      if (!res.success) {
        showError(res.data?.message || 'حدث خطأ في بدء الامتحان.');
        return;
      }
      const d = res.data;
      state.resultId  = d.result_id;
      state.questions = d.question_ids;
      state.resuming  = d.resumed;
      state.started_at = d.started_at;

      if (d.resumed && d.answers) {
        Object.entries(d.answers).forEach(([qid, a]) => {
          if (a.value !== null && a.value !== undefined) {
            state.answers[qid]  = a.value;
            state.answered.add(parseInt(qid));
          }
        });
        state.reviewList = d.review_list || [];
        // Find first unanswered question
        const firstUnanswered = state.questions.findIndex(qid => !state.answered.has(qid));
        state.current = firstUnanswered >= 0 ? firstUnanswered : 0;
      }

      // Timer: for exam timer we compute remaining time
      if (C.timer_type === 'exam') {
        const startedAtTs = Number(d.started_at_ts || 0);
        if (startedAtTs > 0) {
          const elapsed = Math.max(0, Math.floor(Date.now() / 1000) - startedAtTs);
          state.timeRemaining = Math.max(0, C.duration_seconds - elapsed);
        } else if (d.started_at) {
          const parsedStartedAt = Date.parse(String(d.started_at).replace(' ', 'T'));
          if (!Number.isNaN(parsedStartedAt)) {
            const elapsed = Math.max(0, Math.floor((Date.now() - parsedStartedAt) / 1000));
            state.timeRemaining = Math.max(0, C.duration_seconds - elapsed);
          }
        }
      }

      $('#q-total').text(state.questions.length);
      showLoading(false);
      startTimers();
      await loadQuestion(state.current);
      startAutosave();

    } catch(e) {
      showError('تعذر الاتصال بالخادم. يرجى التحقق من الإنترنت.');
      console.error('[ExamHub]', e);
    }
  }

  // ─── Timer ──────────────────────────────────────────────────────────────────
  function startTimers() {
    if (C.timer_type === 'exam') {
      if (state.timeRemaining <= 0) { autoSubmit(); return; }
      $('#exam-timer-block').show();
      state.timerInterval = setInterval(() => {
        state.timeRemaining--;
        updateTimerDisplay();
        if (state.timeRemaining <= 0) { clearInterval(state.timerInterval); autoSubmit(); }
      }, 1000);
      updateTimerDisplay();
    } else if (C.timer_type === 'per_question') {
      $('#exam-timer-block').hide();
    } else {
      $('#exam-timer-block').hide();
    }
  }

  function startQuestionTimer() {
    if (C.timer_type !== 'per_question') return;
    clearInterval(state.qTimerInterval);
    state.qTimeRemaining = C.sec_per_question;
    updateQuestionTimerDisplay();
    state.qTimerInterval = setInterval(() => {
      state.qTimeRemaining--;
      updateQuestionTimerDisplay();
      if (state.qTimeRemaining <= 0) {
        clearInterval(state.qTimerInterval);
        // Auto-advance to next question
        if (state.current < state.questions.length - 1) {
          goToQuestion(state.current + 1);
        } else {
          triggerSubmitModal();
        }
      }
    }, 1000);
  }

  function updateTimerDisplay() {
    const m = Math.floor(state.timeRemaining / 60);
    const s = state.timeRemaining % 60;
    const str = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    $('#exam-timer').text(str);
    const $block = $('#exam-timer-block');
    $block.removeClass('warning danger');
    if (state.timeRemaining <= 60)       $block.addClass('danger');
    else if (state.timeRemaining <= 300) $block.addClass('warning');
  }

  function updateQuestionTimerDisplay() {
    const pct = (state.qTimeRemaining / C.sec_per_question) * 100;
    $('#question-timer-secs').text(state.qTimeRemaining);
    const $bar = $('#question-timer-bar');
    $bar.css('width', pct + '%');
    $bar.removeClass('low critical');
    if (pct <= 20)      $bar.addClass('critical');
    else if (pct <= 40) $bar.addClass('low');
  }

  function parseOrderingItemsFromHtml(html) {
    if (!html) return [];
    const doc = document.createElement('div');
    doc.innerHTML = html;

    const listItems = Array.from(doc.querySelectorAll('li'))
      .map((node) => node.textContent.replace(/\s+/g, ' ').trim())
      .filter(Boolean);

    if (listItems.length >= 2) {
      return listItems;
    }

    const text = (doc.textContent || '').replace(/\r/g, '');
    const matches = [...text.matchAll(/(?:^|\n)\s*\d+[\.\-\)]\s*(.+?)(?=(?:\n\s*\d+[\.\-\)]\s*)|$)/gs)];
    return matches
      .map((match) => (match[1] || '').replace(/\s+/g, ' ').trim())
      .filter(Boolean);
  }

  function stripOrderingListFromBody(html) {
    if (!html) return html;
    const doc = document.createElement('div');
    doc.innerHTML = html;

    doc.querySelectorAll('ol, ul').forEach((node) => node.remove());

    Array.from(doc.querySelectorAll('p, div, strong, h1, h2, h3, h4, h5, h6'))
      .filter((node) => {
        const text = (node.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
        return text === 'items to order:' ||
          text === 'items to order' ||
          text === 'arrange the following:' ||
          text === 'رتب العناصر التالية' ||
          text === 'رتب ما يلي';
      })
      .forEach((node) => node.remove());

    Array.from(doc.querySelectorAll('p, div, li, span'))
      .filter((node) => /^\s*\d+[\.\-\)]\s+/.test((node.textContent || '').trim()))
      .forEach((node) => node.remove());

    Array.from(doc.querySelectorAll('p, div'))
      .filter((node) => !(node.textContent || '').replace(/\s+/g, ' ').trim())
      .forEach((node) => node.remove());

    return doc.innerHTML.trim();
  }

  // ─── Question Loading ────────────────────────────────────────────────────────
  async function loadQuestion(index) {
    const qId = state.questions[index];
    if (!qId) return;

    state.current = index;

    try {
      const res = await ajaxPost('eh_load_question', {
        result_id:      state.resultId,
        question_index: index,
      });
      if (!res.success) return;
      const q = res.data;

      renderQuestion(q);
      updateNavUI();
      startQuestionTimer();

    } catch(e) {
      console.error('[ExamHub] Failed to load question', e);
    }
  }

  // ─── Question Rendering ──────────────────────────────────────────────────────
  function renderQuestion(q) {
    // Update header
    $('#q-current').text(state.current + 1);
    $('#q-type-badge').text(typeLabel(q.type));
    $('#q-points-badge').text(q.points + (q.points === 1 ? ' درجة' : ' درجات'));
    updateDiffBadge(q.difficulty);

    // Review button
    const isReview = state.reviewList.includes(q.id);
    $('#btn-mark-review').toggleClass('active', isReview);
    $('#review-icon').toggleClass('bi-flag-fill', isReview).toggleClass('bi-flag', !isReview);
    $('#btn-mark-review').attr('data-qid', q.id);

    // Question text
    $('#question-text').html(q.text || '');
    const orderingItems = Array.isArray(q.ordering_items) && q.ordering_items.length
      ? q.ordering_items
      : parseOrderingItemsFromHtml(q.body);
    if (q.type === 'ordering') {
      q.ordering_items = orderingItems;
    }

    const bodyHtml = q.type === 'ordering' && orderingItems.length ? stripOrderingListFromBody(q.body) : q.body;
    if (bodyHtml) {
      $('#question-body-content').html(bodyHtml).show();
    } else {
      $('#question-body-content').empty().hide();
    }

    // Image
    if (q.image) {
      $('#question-image').attr('src', q.image).attr('alt', '');
      $('#question-image-wrap').show();
    } else {
      $('#question-image-wrap').hide();
    }

    // Math
    if (q.math) {
      $('#question-math').html('\\(' + q.math + '\\)');
      $('#question-math-wrap').show();
    } else {
      $('#question-math-wrap').hide();
    }

    if (window.MathJax && (q.math || q.body)) {
      MathJax.typesetPromise();
    }

    // Saved answer
    const savedAnswer = state.answers[q.id] !== undefined ? state.answers[q.id] : q.saved_answer;

    // Render answer area by type
    const $area = $('#answer-area').empty();
    switch(q.type) {
      case 'mcq':
      case 'correct':
        renderMCQ($area, q, savedAnswer);
        break;
      case 'true_false':
        renderTrueFalse($area, q, savedAnswer);
        break;
      case 'fill_blank':
        renderFillBlank($area, q, savedAnswer);
        break;
      case 'matching':
        renderMatching($area, q, savedAnswer);
        break;
      case 'ordering':
        renderOrdering($area, q, savedAnswer);
        break;
      case 'essay':
        renderEssay($area, q, savedAnswer);
        break;
      default:
        renderMCQ($area, q, savedAnswer);
    }

    // Show container
    $('#question-container').show();

    // Update progress
    updateProgress();
    updateDotNavigator();
  }

  function renderMCQ($area, q, savedAnswer) {
    q.answers.forEach((a, i) => {
      const answerId = a.id ?? a.index ?? i;
      const isSelected = savedAnswer === answerId;
      const $opt = $(`
        <button class="eh-answer-option ${isSelected ? 'selected' : ''}"
          data-aid="${escHtml(answerId)}" data-qid="${q.id}" data-qtype="${q.type}">
          <span class="option-letter">${LETTERS[i] || (i+1)}</span>
          <span class="option-text">${escHtml(a.text)}</span>
          ${a.image ? `<img src="${escHtml(a.image)}" style="height:60px;border-radius:4px;margin-right:auto;">` : ''}
        </button>
      `);
      $opt.on('click', function() {
        const aid = $(this).data('aid');
        state.answers[q.id] = aid;
        state.answered.add(q.id);
        $('.eh-answer-option').removeClass('selected');
        $(this).addClass('selected').find('.option-letter').css('background', 'var(--eh-accent)').css('color','#fff');
        saveAnswer(q.id, aid, q.type);
      });
      $area.append($opt);
    });
  }

  function renderTrueFalse($area, q, savedAnswer) {
    const $cont = $('<div class="eh-tf-options"></div>');
    ['true','false'].forEach(val => {
      const label = val === 'true' ? 'صح ✓' : 'خطأ ✗';
      const cls   = val === 'true' ? 'true-btn' : 'false-btn';
      const sel   = savedAnswer === val ? 'selected' : '';
      const $btn  = $(`<button class="eh-tf-btn ${cls} ${sel}" data-val="${val}">${label}</button>`);
      $btn.on('click', function() {
        $('.eh-tf-btn').removeClass('selected');
        $(this).addClass('selected');
        const v = $(this).data('val');
        state.answers[q.id] = v;
        state.answered.add(q.id);
        saveAnswer(q.id, v, 'true_false');
      });
      $cont.append($btn);
    });
    $area.append($cont);
  }

  function renderFillBlank($area, q, savedAnswer) {
    const parts = (q.text || '').split('[[BLANK]]');
    const $wrap = $('<div class="eh-fill-blank-wrapper"></div>');
    const inputs = [];
    parts.forEach((part, i) => {
      $wrap.append(document.createTextNode(part));
      if (i < parts.length - 1) {
        const $inp = $(`<input type="text" class="eh-fill-blank-input"
          placeholder="..." data-index="${i}"
          value="${savedAnswer && savedAnswer[i] ? escHtml(savedAnswer[i]) : ''}">`);
        $inp.on('input', function() {
          const vals = [];
          $area.find('.eh-fill-blank-input').each(function() { vals.push($(this).val()); });
          state.answers[q.id] = vals;
          if (vals.some(v => v.trim())) state.answered.add(q.id);
          saveAnswer(q.id, vals, 'fill_blank');
        });
        inputs.push($inp);
        $wrap.append($inp);
      }
    });
    $area.append($wrap);
  }

  function renderMatching($area, q, savedAnswer) {
    const $grid = $('<div class="eh-matching-grid"></div>');
    q.matching_left.forEach((left, i) => {
      const $left  = $(`<div class="eh-match-left">${escHtml(left)}</div>`);
      const $arrow = $('<div class="eh-match-arrow">→</div>');
      const $right = $('<div class="eh-match-right"></div>');
      const $sel   = $('<select></select>');
      $sel.append('<option value="">— اختر —</option>');
      q.matching_right.forEach(r => {
        const selected = savedAnswer && savedAnswer[i] === r ? 'selected' : '';
        $sel.append(`<option value="${escHtml(r)}" ${selected}>${escHtml(r)}</option>`);
      });
      $sel.on('change', function() {
        const vals = [];
        $area.find('select').each(function() { vals.push($(this).val() || ''); });
        state.answers[q.id] = vals;
        if (vals.some(v => v)) state.answered.add(q.id);
        saveAnswer(q.id, vals, 'matching');
      });
      $right.append($sel);
      $grid.append($left, $arrow, $right);
    });
    $area.append($grid);
  }

  function renderOrdering($area, q, savedAnswer) {
    const fallbackItems = Array.isArray(q.ordering_items) && q.ordering_items.length
      ? q.ordering_items
      : parseOrderingItemsFromHtml(q.body);
    const items = savedAnswer && Array.isArray(savedAnswer) ? savedAnswer : fallbackItems;
    if (!items.length) {
      $area.append('<div class="eh-answer-help">Unable to load ordering items for this question.</div>');
      return;
    }
    const $list = $('<ul class="eh-ordering-list" id="eh-ordering-list"></ul>');
    items.forEach((item, i) => {
      const $li = $(`
        <li class="eh-ordering-item" data-item="${escHtml(item)}">
          <span class="drag-handle"><i class="bi bi-grip-vertical"></i></span>
          <span class="eh-ordering-num">${i+1}</span>
          <span class="eh-ordering-text">${escHtml(item)}</span>
          <div class="eh-ordering-actions">
            <button type="button" class="eh-ordering-move" data-dir="up" aria-label="Move up">
              <i class="bi bi-chevron-up"></i>
            </button>
            <button type="button" class="eh-ordering-move" data-dir="down" aria-label="Move down">
              <i class="bi bi-chevron-down"></i>
            </button>
          </div>
        </li>
      `);
      $list.append($li);
    });
    $area.append($list);

    const syncOrderingState = () => {
      const order = [];
      $list.find('.eh-ordering-item').each(function(i) {
        $(this).find('.eh-ordering-num').text(i + 1);
        order.push($(this).data('item'));
      });
      state.answers[q.id] = order;
      state.answered.add(q.id);
      saveAnswer(q.id, order, 'ordering');
    };

    $list.on('click', '.eh-ordering-move', function() {
      const dir = $(this).data('dir');
      const $item = $(this).closest('.eh-ordering-item');
      if (dir === 'up') {
        const $prev = $item.prev('.eh-ordering-item');
        if ($prev.length) {
          $item.insertBefore($prev);
          syncOrderingState();
        }
      } else if (dir === 'down') {
        const $next = $item.next('.eh-ordering-item');
        if ($next.length) {
          $item.insertAfter($next);
          syncOrderingState();
        }
      }
    });

    if (typeof Sortable !== 'undefined' && $list[0]) {
      Sortable.create($list[0], {
        animation: 200,
        handle: '.drag-handle',
        onEnd() {
          syncOrderingState();
        }
      });
    }
  }

  function renderEssay($area, q, savedAnswer) {
    const $ta = $(`
      <textarea class="eh-essay-textarea"
        placeholder="${'اكتب إجابتك هنا...'}"
        rows="7">${savedAnswer ? escHtml(savedAnswer) : ''}</textarea>
    `);
    const $wc = $('<div class="eh-essay-word-count">0 كلمة</div>');
    $ta.on('input', function() {
      const val   = $(this).val();
      const words = val.trim() ? val.trim().split(/\s+/).length : 0;
      $wc.text(words + ' كلمة');
      state.answers[q.id] = val;
      if (val.trim()) state.answered.add(q.id);
      saveAnswer(q.id, val, 'essay');
    });
    $area.append($ta, $wc);
  }

  // ─── Save Answer (debounced) ─────────────────────────────────────────────────
  const saveTimers = {};
  function saveAnswer(qId, value, type) {
    clearTimeout(saveTimers[qId]);
    saveTimers[qId] = setTimeout(() => {
      ajaxPost('eh_save_answer', {
        result_id:   state.resultId,
        question_id: qId,
        answer:      JSON.stringify(value),
        q_type:      type,
      }).then(() => showAutosave());
    }, 600);
  }

  // ─── Autosave ────────────────────────────────────────────────────────────────
  function startAutosave() {
    state.autosaveTimer = setInterval(() => {
      const qId  = state.questions[state.current];
      const ans  = state.answers[qId];
      if (ans !== undefined && ans !== state.lastSaved) {
        ajaxPost('eh_save_answer', {
          result_id:   state.resultId,
          question_id: qId,
          answer:      JSON.stringify(ans),
          q_type:      '',
        }).then(() => { state.lastSaved = ans; showAutosave(); });
      }
    }, (C.autosave_interval || 30) * 1000);
  }

  function showAutosave() {
    const $ind = $('#autosave-indicator');
    $ind.show();
    clearTimeout(window._autosaveFade);
    window._autosaveFade = setTimeout(() => $ind.fadeOut(400), 2000);
  }

  // ─── Navigation ──────────────────────────────────────────────────────────────
  function bindUI() {
    // Next
    $('#btn-next').on('click', () => {
      if (state.current < state.questions.length - 1) goToQuestion(state.current + 1);
      else triggerSubmitModal();
    });
    // Prev
    $('#btn-prev').on('click', () => {
      if (state.current > 0) goToQuestion(state.current - 1);
    });
    // Skip
    $('#btn-skip').on('click', () => {
      if (state.current < state.questions.length - 1) goToQuestion(state.current + 1);
      else triggerSubmitModal();
    });
    // Mark review
    $(document).on('click', '#btn-mark-review', function() {
      const qId = parseInt($(this).data('qid'));
      ajaxPost('eh_toggle_review', { result_id: state.resultId, question_id: qId })
        .then(res => {
          if (res.success) {
            state.reviewList = res.data.review_list;
            const isMarked   = res.data.marked;
            $('#btn-mark-review').toggleClass('active', isMarked);
            $('#review-icon').toggleClass('bi-flag-fill', isMarked).toggleClass('bi-flag', !isMarked);
            updateDotNavigator();
          }
        });
    });
    // Q-nav toggle
    $('#btn-q-nav-toggle').on('click', () => $('#q-navigator').toggle());
    $('#btn-close-nav').on('click',   () => $('#q-navigator').hide());

    // Submit
    $('#btn-submit-exam').on('click', triggerSubmitModal);
    $('#btn-cancel-submit').on('click', () => $('#submit-modal').hide());
    $('#btn-confirm-submit').on('click', submitExam);

    // Exit
    $('#btn-exam-exit').on('click', () => {
      if (confirm('هل تريد الخروج من الامتحان؟ سيتم حفظ تقدمك.')) {
        window.location = C.exam_url;
      }
    });
  }

  function bindAntiCopyGuards() {
    const $app = $('#eh-exam-app');
    if (!$app.length) return;

    $app.on('contextmenu', function(e) {
      e.preventDefault();
    });

    $app.on('copy cut', function(e) {
      if (isEditableTarget(e.target)) return;
      e.preventDefault();
    });

    $app.on('selectstart', function(e) {
      if (isEditableTarget(e.target)) return;
      e.preventDefault();
    });

    $(document).on('keydown.examhubExamGuard', function(e) {
      if (!$('#eh-exam-app').length) return;
      if (isEditableTarget(e.target)) return;
      if (!(e.ctrlKey || e.metaKey)) return;

      const key = String(e.key || '').toLowerCase();
      if (['a', 'c', 'x', 's', 'u', 'p'].includes(key)) {
        e.preventDefault();
      }
    });
  }

  function goToQuestion(index) {
    state.current = index;
    loadQuestion(index);
    $('#q-navigator').hide();
    $('html,body').animate({ scrollTop: 0 }, 150);
  }

  function updateNavUI() {
    $('#btn-prev').prop('disabled', state.current === 0);
    const isLast = state.current === state.questions.length - 1;
    $('#btn-next').html(isLast
      ? '<i class="bi bi-check-circle me-1"></i>تسليم'
      : `<span class="d-none d-sm-inline">التالي</span><i class="bi bi-chevron-left"></i>`
    );
    $('#q-current').text(state.current + 1);
  }

  function updateProgress() {
    const pct = ((state.current + 1) / state.questions.length) * 100;
    $('#exam-progress-bar').css('width', pct + '%');
    const answered = state.answered.size;
    $('#answered-count').text(answered);
  }

  function updateDotNavigator() {
    const $dots = $('#q-dots').empty();
    state.questions.forEach((qId, i) => {
      let cls = '';
      if (i === state.current)          cls = 'current';
      else if (state.answered.has(qId)) cls = 'answered';
      if (state.reviewList.includes(qId)) cls = 'review';

      const $dot = $(`<button class="eh-q-dot ${cls}" title="سؤال ${i+1}">${i+1}</button>`);
      $dot.on('click', () => goToQuestion(i));
      $dots.append($dot);
    });
  }

  // ─── Submit ──────────────────────────────────────────────────────────────────
  function triggerSubmitModal() {
    const total    = state.questions.length;
    const answered = state.answered.size;
    const unans    = total - answered;
    const hasReview = state.reviewList.length > 0;

    $('#submit-modal-answered').text(`أجبت على ${answered} من ${total} سؤال`);
    if (unans > 0) {
      $('#submit-modal-unanswered').text(`لم تجب على ${unans} سؤال`).show();
    } else {
      $('#submit-modal-unanswered').hide();
    }
    $('#submit-modal-review-warn').toggle(hasReview);
    $('#submit-modal').show();
  }

  async function submitExam(timedOut = false) {
    if (state.submitted) return;
    state.submitted = true;

    clearInterval(state.timerInterval);
    clearInterval(state.qTimerInterval);
    clearInterval(state.autosaveTimer);

    $('#submit-modal').hide();
    $('#submitting-overlay').show();

    try {
      const res = await ajaxPost('eh_submit_exam', {
        result_id: state.resultId,
        timed_out: timedOut ? 1 : 0,
      });
      if (res.success) {
        const d = res.data;
        const url = `${C.exam_url}?result=${d.result_id}`;
        window.location = url;
      } else {
        state.submitted = false;
        $('#submitting-overlay').hide();
        alert(res.data?.message || 'حدث خطأ. حاول مجدداً.');
      }
    } catch(e) {
      state.submitted = false;
      $('#submitting-overlay').hide();
      alert('خطأ في الاتصال. يرجى التحقق من الإنترنت.');
    }
  }

  function autoSubmit() {
    submitExam(true);
  }

  // ─── Helpers ──────────────────────────────────────────────────────────────────
  function typeLabel(type) {
    const labels = {
      mcq: 'اختيار متعدد', correct: 'الصحيح', true_false: 'صح/خطأ',
      fill_blank: 'اكمل', matching: 'مطابقة', ordering: 'ترتيب',
      essay: 'مقال', image: 'صورة', math: 'رياضيات',
    };
    return labels[type] || type;
  }

  function updateDiffBadge(diff) {
    const map = { easy: ['سهل','badge-easy'], medium: ['متوسط','badge-medium'], hard: ['صعب','badge-hard'] };
    const [label, cls] = map[diff] || ['', ''];
    $('#q-diff-badge').text(label).attr('class', 'badge ' + cls);
  }

  function showLoading(show) {
    $('#exam-loading').toggle(show);
    $('#question-container').toggle(!show);
  }

  function showError(msg) {
    showLoading(false);
    $('#exam-main').html(`
      <div class="text-center py-5">
        <i class="bi bi-exclamation-triangle fs-1 text-warning mb-3 d-block"></i>
        <h4 class="text-light">${escHtml(msg)}</h4>
        <a href="${C.exam_url}" class="btn btn-ghost mt-3">رجوع</a>
      </div>
    `);
  }

  function ajaxPost(action, data) {
    return $.ajax({
      url: C.ajax_url,
      type: 'POST',
      data: Object.assign({ action, nonce: C.nonce }, data),
    });
  }

  function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function isEditableTarget(target) {
    return $(target).closest('input, textarea, select, [contenteditable="true"]').length > 0;
  }

})(jQuery);

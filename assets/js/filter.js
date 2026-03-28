/**
 * ExamHub — Filter & Hierarchy JS
 * Cascade dropdowns, AJAX exam loading, filter state management.
 */
(function($) {
  'use strict';

  // ─── State ─────────────────────────────────────────────────────────────────
  const state = {
    system_id:  window.examhubFilterConfig?.initial_system  || 0,
    stage_id:   window.examhubFilterConfig?.initial_stage   || 0,
    grade_id:   window.examhubFilterConfig?.initial_grade   || 0,
    subject_id: window.examhubFilterConfig?.initial_subject || 0,
    unit_id:    window.examhubFilterConfig?.initial_unit    || 0,
    lesson_id:  window.examhubFilterConfig?.initial_lesson  || 0,
    difficulty: window.examhubFilterConfig?.initial_diff    || '',
    paged:      1,
    loading:    false,
  };

  let searchTimer;

  // ─── Init ──────────────────────────────────────────────────────────────────
  $(function() {
    if (!$('#exam-grid').length) return;

    // Restore state from URL
    const params = new URLSearchParams(window.location.search);
    ['system_id','stage_id','grade_id','subject_id','unit_id','lesson_id','difficulty'].forEach(k => {
      const v = params.get(k.replace('_id',''));
      if (v) state[k] = parseInt(v) || v;
    });

    // Bind events
    bindSystemButtons();
    bindCascadeSelects();
    bindDifficultyButtons();
    bindSearch();
    bindSort();
    bindClearFilters();

    // If state has pre-selected system, trigger cascade
    if (state.system_id) {
      loadStages(state.system_id).then(() => {
        if (state.stage_id) {
          $('#sel-stage').val(state.stage_id).trigger('change');
        }
      });
    }
  });

  // ─── System Buttons ─────────────────────────────────────────────────────────
  function bindSystemButtons() {
    $(document).on('click', '.eh-system-btn', function() {
      const id = parseInt($(this).data('id'));
      const isActive = $(this).hasClass('active');

      // Toggle
      if (isActive) {
        resetFrom('system');
        return;
      }

      $('.eh-system-btn').removeClass('active').removeAttr('style');
      $(this).addClass('active');
      const color = $(this).find('i').css('color');
      $(this).css({
        'border-color': color,
        'background': hexToRgba(color, 0.12),
        'color': color,
      });

      state.system_id = id;
      state.stage_id = state.grade_id = state.subject_id = state.unit_id = state.lesson_id = 0;
      state.paged = 1;

      hideCards(['#stage-card','#grade-card','#subject-card','#unit-card','#lesson-card']);
      loadStages(id);
      fetchExams();
    });
  }

  // ─── Cascade Selects ──────────────────────────────────────────────────────
  function bindCascadeSelects() {
    $('#sel-stage').on('change', function() {
      const id = parseInt($(this).val()) || 0;
      state.stage_id = id;
      state.grade_id = state.subject_id = state.unit_id = state.lesson_id = 0;
      state.paged = 1;
      hideCards(['#grade-card','#subject-card','#unit-card','#lesson-card']);
      if (id) loadGrades(id);
      fetchExams();
    });

    $('#sel-grade').on('change', function() {
      const id = parseInt($(this).val()) || 0;
      state.grade_id = id;
      state.subject_id = state.unit_id = state.lesson_id = 0;
      state.paged = 1;
      hideCards(['#subject-card','#unit-card','#lesson-card']);
      if (id) loadSubjects(id);
      fetchExams();
    });

    $('#sel-unit').on('change', function() {
      const id = parseInt($(this).val()) || 0;
      state.unit_id = id;
      state.lesson_id = 0;
      state.paged = 1;
      hideCards(['#lesson-card']);
      if (id) loadLessons(id);
      fetchExams();
    });

    $('#sel-lesson').on('change', function() {
      state.lesson_id = parseInt($(this).val()) || 0;
      state.paged = 1;
      fetchExams();
    });
  }

  // ─── Difficulty ───────────────────────────────────────────────────────────
  function bindDifficultyButtons() {
    $(document).on('click', '.eh-diff-btn', function() {
      const diff = $(this).data('diff');
      if (state.difficulty === diff) {
        state.difficulty = '';
        $(this).removeClass('active');
      } else {
        state.difficulty = diff;
        $('.eh-diff-btn').removeClass('active');
        $(this).addClass('active');
      }
      state.paged = 1;
      fetchExams();
    });
  }

  // ─── Search ───────────────────────────────────────────────────────────────
  function bindSearch() {
    $('#eh-exam-search').on('input', function() {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        state.search = $(this).val();
        state.paged = 1;
        fetchExams();
      }, 400);
    });
  }

  // ─── Sort ─────────────────────────────────────────────────────────────────
  function bindSort() {
    $('#sel-sort').on('change', function() {
      state.sort = $(this).val();
      state.paged = 1;
      fetchExams();
    });
  }

  // ─── Clear Filters ────────────────────────────────────────────────────────
  function bindClearFilters() {
    $('#btn-clear-filters').on('click', function() {
      resetFrom('system');
      $('.eh-system-btn').removeClass('active').removeAttr('style');
      $('.eh-diff-btn').removeClass('active');
      $('#eh-exam-search').val('');
      fetchExams();
    });
  }

  // ─── AJAX Loaders ────────────────────────────────────────────────────────
  function loadStages(systemId) {
    return ajaxGet('eh_get_stages', { system_id: systemId }).then(items => {
      populateSelect('#sel-stage', items, window.examhubAjax.i18n.loading || 'اختر المرحلة');
      if (items.length) showCard('#stage-card');
      return items;
    });
  }

  function loadGrades(stageId) {
    return ajaxGet('eh_get_grades', { stage_id: stageId }).then(items => {
      populateSelect('#sel-grade', items, 'اختر الصف');
      if (items.length) showCard('#grade-card');
    });
  }

  function loadSubjects(gradeId) {
    return ajaxGet('eh_get_subjects', { grade_id: gradeId }).then(items => {
      renderSubjectChips(items);
      if (items.length) showCard('#subject-card');
    });
  }

  function loadUnits(subjectId) {
    return ajaxGet('eh_get_units', { subject_id: subjectId }).then(items => {
      populateSelect('#sel-unit', items, 'كل الوحدات');
      if (items.length) showCard('#unit-card');
    });
  }

  function loadLessons(unitId) {
    return ajaxGet('eh_get_lessons', { unit_id: unitId }).then(items => {
      populateSelect('#sel-lesson', items, 'كل الدروس');
      if (items.length) showCard('#lesson-card');
    });
  }

  // ─── Fetch & Render Exams ─────────────────────────────────────────────────
  function fetchExams() {
    if (state.loading) return;
    state.loading = true;

    $('#exam-grid').hide();
    $('#exam-grid-loading').show();

    $.ajax({
      url: window.examhubAjax.url,
      type: 'POST',
      data: {
        action:     'eh_filter_exams',
        nonce:      window.examhubAjax.nonce,
        system_id:  state.system_id,
        stage_id:   state.stage_id,
        grade_id:   state.grade_id,
        subject_id: state.subject_id,
        unit_id:    state.unit_id,
        lesson_id:  state.lesson_id,
        difficulty: state.difficulty,
        search:     state.search || '',
        sort:       state.sort || 'date_desc',
        paged:      state.paged,
      },
      success(res) {
        if (res.success) {
          $('#exam-grid').html(res.data.html).show();
          updateResultsCount(res.data.found);
          renderPagination(res.data.max_pages, res.data.paged);
          updateFilterBreadcrumb();
          updateURL();
        }
      },
      error() {
        $('#exam-grid').show();
        showToast('حدث خطأ في التحميل.', 'danger');
      },
      complete() {
        state.loading = false;
        $('#exam-grid-loading').hide();
      }
    });
  }

  // ─── Subject Chips ────────────────────────────────────────────────────────
  function renderSubjectChips(subjects) {
    const $cont = $('#subject-chips').empty();
    subjects.forEach(s => {
      const $chip = $(`
        <button class="btn badge" data-id="${s.id}"
          style="background:${s.color}20; color:${s.color}; border:1px solid ${s.color}40;">
          ${escHtml(s.label)}
        </button>
      `);
      $chip.on('click', function() {
        if (state.subject_id === s.id) {
          state.subject_id = 0;
          $(this).removeClass('active');
          hideCards(['#unit-card','#lesson-card']);
        } else {
          state.subject_id = s.id;
          $('#subject-chips .badge').removeClass('active').css({'border-width':'1px','font-weight':'600'});
          $(this).addClass('active').css({'border-width':'2px','font-weight':'700'});
          loadUnits(s.id);
        }
        state.paged = 1;
        fetchExams();
      });
      $cont.append($chip);
    });
  }

  // ─── Pagination ───────────────────────────────────────────────────────────
  function renderPagination(maxPages, currentPage) {
    if (maxPages <= 1) { $('#exam-pagination').html(''); return; }
    let html = '<nav><ul class="pagination pagination-sm justify-content-center">';
    for (let p = 1; p <= maxPages; p++) {
      html += `<li class="page-item ${p === currentPage ? 'active' : ''}">
        <button class="page-link eh-page-btn"
          style="background:${p === currentPage ? 'var(--eh-accent)' : 'var(--eh-bg-tertiary)'}; border-color:var(--eh-border); color:${p === currentPage ? '#fff' : 'var(--eh-text-secondary)'};"
          data-page="${p}">${p}</button>
      </li>`;
    }
    html += '</ul></nav>';
    $('#exam-pagination').html(html);
    $(document).off('click','.eh-page-btn').on('click','.eh-page-btn', function() {
      state.paged = parseInt($(this).data('page'));
      fetchExams();
      $('html,body').animate({ scrollTop: $('#exam-grid').offset().top - 80 }, 300);
    });
  }

  // ─── Helpers ──────────────────────────────────────────────────────────────
  function populateSelect(selector, items, placeholder) {
    const $sel = $(selector).empty().append(`<option value="">${placeholder}</option>`);
    items.forEach(item => $sel.append(`<option value="${item.id}">${escHtml(item.label)}</option>`));
  }

  function showCard(selector) { $(selector).css('display',''); }
  function hideCards(selectors) { selectors.forEach(s => $(s).css('display','none!important')); }

  function resetFrom(level) {
    const levels = ['system','stage','grade','subject','unit','lesson'];
    const idx = levels.indexOf(level);
    levels.slice(idx).forEach(l => { state[l + '_id'] = 0; });
    state.difficulty = '';
    state.paged = 1;
    hideCards(['#stage-card','#grade-card','#subject-card','#unit-card','#lesson-card']);
  }

  function updateResultsCount(count) {
    $('#results-count').text(count > 0 ? `${count.toLocaleString('ar-EG')} امتحان` : 'لا توجد نتائج');
  }

  function updateFilterBreadcrumb() {
    const $bc = $('#filter-breadcrumb');
    $bc.empty();
    if (!state.system_id && !state.grade_id && !state.subject_id && !state.difficulty) {
      $bc.hide(); return;
    }
    $bc.show();
    // Could add named crumbs here via state
  }

  function updateURL() {
    const params = new URLSearchParams();
    if (state.system_id)  params.set('system',  state.system_id);
    if (state.stage_id)   params.set('stage',   state.stage_id);
    if (state.grade_id)   params.set('grade',   state.grade_id);
    if (state.subject_id) params.set('subject', state.subject_id);
    if (state.unit_id)    params.set('unit',    state.unit_id);
    if (state.lesson_id)  params.set('lesson',  state.lesson_id);
    if (state.difficulty) params.set('difficulty', state.difficulty);
    const newUrl = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
    history.replaceState(null, '', newUrl);
  }

  function ajaxGet(action, data) {
    return $.ajax({
      url: window.examhubAjax.url,
      type: 'POST',
      data: Object.assign({ action, nonce: window.examhubAjax.nonce }, data),
    }).then(res => res.success ? res.data : []);
  }

  function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function hexToRgba(hex, alpha) {
    const r = parseInt(hex.slice(1,3),16);
    const g = parseInt(hex.slice(3,5),16);
    const b = parseInt(hex.slice(5,7),16);
    return `rgba(${r},${g},${b},${alpha})`;
  }

  // Expose showToast globally
  window.showToast = function(msg, type = 'success') {
    const $t = $(`
      <div class="alert alert-${type} position-fixed shadow-lg" role="alert"
        style="bottom:80px; left:50%; transform:translateX(-50%); z-index:9999; min-width:260px; text-align:center; animation: fadeIn .2s ease;">
        ${msg}
      </div>
    `);
    $('body').append($t);
    setTimeout(() => $t.fadeOut(300, () => $t.remove()), 3000);
  };

})(jQuery);
